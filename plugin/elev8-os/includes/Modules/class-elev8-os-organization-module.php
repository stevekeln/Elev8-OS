<?php
/** CEO-facing Organization Engine workspace. */
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Organization_Module {
    private const OPTION_PAGE_ID = 'elev8_os_organization_page_id';
    private const SLUG = 'organization';
    private const SHORTCODE = 'elev8_organization';

    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'admin_menu'], 29);
        add_shortcode(self::SHORTCODE, [__CLASS__, 'shortcode']);
        add_action('init', [__CLASS__, 'ensure_page'], 36);
        add_action('wp_enqueue_scripts', [__CLASS__, 'assets']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'admin_assets']);
        add_filter('elev8_os_application_shell_frontend', [__CLASS__, 'application_shell']);
        add_filter('elev8_os_command_palette_commands', [__CLASS__, 'command'], 26, 2);
        add_action('admin_post_elev8_os_save_org_unit', [__CLASS__, 'save_unit']);
        add_action('admin_post_elev8_os_set_org_unit_status', [__CLASS__, 'set_unit_status']);
        add_action('admin_post_elev8_os_save_org_assignment', [__CLASS__, 'save_assignment']);
        add_action('admin_post_elev8_os_remove_org_assignment', [__CLASS__, 'remove_assignment']);
    }

    public static function activate(): void { Elev8_OS_Organization_Service::activate(); self::ensure_page(true); }

    public static function admin_menu(): void {
        add_submenu_page('elev8-os', __('Organization', 'elev8-os'), __('Organization', 'elev8-os'), 'manage_options', 'elev8-organization', [__CLASS__, 'render_admin']);
    }

    public static function ensure_page(bool $force = false): void {
        $id = absint(get_option(self::OPTION_PAGE_ID));
        if ($id && get_post($id) instanceof WP_Post) { return; }
        $page = get_page_by_path(self::SLUG, OBJECT, 'page');
        if ($page instanceof WP_Post && $page->post_status !== 'trash') { update_option(self::OPTION_PAGE_ID, (int) $page->ID, false); return; }
        if (!$force && !current_user_can('manage_options')) { return; }
        $id = wp_insert_post(['post_title'=>__('Organization','elev8-os'),'post_name'=>self::SLUG,'post_content'=>'['.self::SHORTCODE.']','post_status'=>'publish','post_type'=>'page','post_author'=>get_current_user_id(),'comment_status'=>'closed'], true);
        if (!is_wp_error($id) && $id > 0) { update_option(self::OPTION_PAGE_ID, (int) $id, false); }
    }

    public static function url(array $args = []): string {
        $id = absint(get_option(self::OPTION_PAGE_ID));
        $url = $id ? (string) get_permalink($id) : home_url('/' . self::SLUG . '/');
        return $args ? add_query_arg($args, $url) : $url;
    }

    public static function application_shell(bool $show): bool { return $show || is_page(absint(get_option(self::OPTION_PAGE_ID))) || is_page(self::SLUG); }

    public static function assets(): void {
        if (is_page(absint(get_option(self::OPTION_PAGE_ID))) || is_page(self::SLUG)) { wp_enqueue_style('elev8-organization', ELEV8_OS_URL . 'assets/css/organization.css', [], ELEV8_OS_VERSION); wp_enqueue_script('elev8-organization-user-search', ELEV8_OS_URL . 'assets/js/organization-user-search.js', [], ELEV8_OS_VERSION, true); }
    }
    public static function admin_assets(string $hook): void {
        if ($hook === 'elev8-os_page_elev8-organization') { wp_enqueue_style('elev8-organization', ELEV8_OS_URL . 'assets/css/organization.css', [], ELEV8_OS_VERSION); wp_enqueue_script('elev8-organization-user-search', ELEV8_OS_URL . 'assets/js/organization-user-search.js', [], ELEV8_OS_VERSION, true); }
    }

    public static function shortcode(): string {
        if (!is_user_logged_in()) { return '<p>' . esc_html__('Please sign in to view the organization.', 'elev8-os') . '</p>'; }
        if (!Elev8_OS_Organization_Service::can_view()) { return '<p>' . esc_html__('You do not have access to the Organization workspace.', 'elev8-os') . '</p>'; }
        return self::content(false);
    }

    public static function render_admin(): void {
        if (!Elev8_OS_Organization_Service::can_manage()) { wp_die(esc_html__('You do not have permission to manage the organization.', 'elev8-os')); }
        echo '<div class="wrap">' . self::content(true) . '</div>';
    }

    private static function content(bool $admin): string {
        $can_manage = Elev8_OS_Organization_Service::can_manage();
        $stats = Elev8_OS_Organization_Service::stats();
        $effective_user = class_exists('Elev8_OS_Preview_Service') ? Elev8_OS_Preview_Service::effective_user() : wp_get_current_user();
        $hierarchy = $effective_user instanceof WP_User ? Elev8_OS_Organization_Service::hierarchy_for_user($effective_user, 'active') : [];
        $mode = sanitize_key((string) ($_GET['org_mode'] ?? ''));
        $selected_id = $mode === 'new' ? 0 : absint($_GET['unit_id'] ?? 0);
        $selected = $selected_id ? Elev8_OS_Organization_Service::get_unit($selected_id) : [];
        if ($selected && !$can_manage && (!$effective_user instanceof WP_User || !Elev8_OS_Organization_Service::user_in_scope((int) $effective_user->ID, $selected_id))) { $selected = []; $selected_id = 0; }
        $assignments = $selected ? Elev8_OS_Organization_Service::assignments_for_unit($selected_id, false) : [];
        $notice = sanitize_key((string) ($_GET['org_notice'] ?? ''));
        ob_start(); ?>
        <main class="elev8-org<?php echo $admin ? ' is-admin' : ''; ?>">
            <?php if ($notice) : ?><div class="elev8-org__notice"><?php echo esc_html(self::notice_text($notice)); ?></div><?php endif; ?>
            <header class="elev8-org__hero">
                <div><p class="elev8-org__eyebrow"><?php esc_html_e('Organization Engine', 'elev8-os'); ?></p><h1><?php esc_html_e('Company Map', 'elev8-os'); ?></h1><p><?php esc_html_e('Businesses, brands, locations, departments, teams, and people connected through one configurable organization graph.', 'elev8-os'); ?></p></div>
                <?php if ($can_manage) : ?><a class="button button-primary" href="<?php echo esc_url(self::url(['org_mode' => 'new']) . '#new-organization-unit'); ?>"><?php esc_html_e('Add Organization Unit', 'elev8-os'); ?></a><?php endif; ?>
            </header>
            <section class="elev8-org__stats">
                <article><strong><?php echo esc_html((string) $stats['counts']['business']); ?></strong><span><?php esc_html_e('Businesses', 'elev8-os'); ?></span></article>
                <article><strong><?php echo esc_html((string) $stats['counts']['brand']); ?></strong><span><?php esc_html_e('Brands', 'elev8-os'); ?></span></article>
                <article><strong><?php echo esc_html((string) $stats['counts']['location']); ?></strong><span><?php esc_html_e('Locations', 'elev8-os'); ?></span></article>
                <article><strong><?php echo esc_html((string) $stats['counts']['department']); ?></strong><span><?php esc_html_e('Departments', 'elev8-os'); ?></span></article>
                <article><strong><?php echo esc_html((string) $stats['counts']['team']); ?></strong><span><?php esc_html_e('Teams', 'elev8-os'); ?></span></article>
                <article><strong><?php echo esc_html((string) $stats['active_assignments']); ?></strong><span><?php esc_html_e('Person assignments', 'elev8-os'); ?></span></article>
            </section>
            <div class="elev8-org__layout">
                <section class="elev8-org__panel"><div class="elev8-org__panel-head"><h2><?php esc_html_e('Organization Structure', 'elev8-os'); ?></h2><p><?php esc_html_e('Click a unit to view its people, responsibilities, and details.', 'elev8-os'); ?></p></div>
                    <?php if (!$hierarchy) : ?><div class="elev8-org__empty"><h3><?php esc_html_e('Build your company map', 'elev8-os'); ?></h3><p><?php esc_html_e('Start with a Business, then connect brands, locations, departments, and teams. Nothing is hardcoded, so the same engine can represent one company or many.', 'elev8-os'); ?></p></div>
                    <?php else : echo self::tree($hierarchy, $selected_id); endif; ?>
                </section>
                <aside class="elev8-org__panel elev8-org__detail">
                    <?php if ($selected) : self::render_detail($selected, $assignments, $can_manage); else : ?><div class="elev8-org__empty"><h3><?php esc_html_e('Select an organization unit', 'elev8-os'); ?></h3><p><?php esc_html_e('The selected unit will show its parent, address, status, assigned people, and responsibilities.', 'elev8-os'); ?></p></div><?php endif; ?>
                </aside>
            </div>
            <?php if ($can_manage) : self::render_create_forms($selected); endif; ?>
        </main>
        <?php return (string) ob_get_clean();
    }

    private static function tree(array $nodes, int $selected_id): string {
        $html = '<ul class="elev8-org__tree">';
        foreach ($nodes as $node) {
            $url = self::url(['unit_id' => (int) $node['id']]);
            $html .= '<li><a class="elev8-org__node' . ((int) $node['id'] === $selected_id ? ' is-selected' : '') . '" href="' . esc_url($url) . '"><span class="elev8-org__type">' . esc_html((string) $node['type_label']) . '</span><strong>' . esc_html((string) $node['name']) . '</strong><small>' . esc_html((string) $node['status']) . '</small></a>';
            if (!empty($node['children'])) { $html .= self::tree($node['children'], $selected_id); }
            $html .= '</li>';
        }
        return $html . '</ul>';
    }

    private static function render_detail(array $unit, array $assignments, bool $can_manage): void { ?>
        <div class="elev8-org__panel-head"><p class="elev8-org__eyebrow"><?php echo esc_html((string) $unit['type_label']); ?></p><h2><?php echo esc_html((string) $unit['name']); ?></h2><span class="elev8-org__status is-<?php echo esc_attr((string) $unit['status']); ?>"><?php echo esc_html(ucfirst((string) $unit['status'])); ?></span></div>
        <?php if ($unit['description']) : ?><div class="elev8-org__description"><?php echo wp_kses_post(wpautop((string) $unit['description'])); ?></div><?php endif; ?>
        <dl class="elev8-org__facts"><div><dt><?php esc_html_e('Code', 'elev8-os'); ?></dt><dd><?php echo esc_html((string) $unit['code'] ?: __('Unavailable', 'elev8-os')); ?></dd></div><div><dt><?php esc_html_e('Address', 'elev8-os'); ?></dt><dd><?php echo nl2br(esc_html((string) $unit['address'] ?: __('Unavailable', 'elev8-os'))); ?></dd></div><div><dt><?php esc_html_e('Timezone', 'elev8-os'); ?></dt><dd><?php echo esc_html((string) $unit['timezone'] ?: __('Unavailable', 'elev8-os')); ?></dd></div></dl>
        <div class="elev8-org__people"><h3><?php esc_html_e('People & Responsibilities', 'elev8-os'); ?></h3>
            <?php if (!$assignments) : ?><p><?php esc_html_e('No people are assigned to this unit yet.', 'elev8-os'); ?></p><?php else : foreach ($assignments as $assignment) : ?>
                <article><div><strong><?php echo esc_html((string) $assignment['user_name']); ?></strong><span><?php echo esc_html((string) $assignment['assignment_type_label']); ?><?php if ($assignment['responsibility']) : ?> · <?php echo esc_html((string) $assignment['responsibility']); ?><?php endif; ?></span></div><span class="elev8-org__status <?php echo $assignment['active'] ? 'is-active' : 'is-inactive'; ?>"><?php echo esc_html($assignment['active'] ? __('Active', 'elev8-os') : __('Inactive', 'elev8-os')); ?></span>
                <?php if ($can_manage) : ?><a class="elev8-org__remove" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=elev8_os_remove_org_assignment&assignment_id=' . (int) $assignment['id']), 'elev8_os_remove_org_assignment_' . (int) $assignment['id'])); ?>"><?php esc_html_e('Remove', 'elev8-os'); ?></a><?php endif; ?>
                </article>
            <?php endforeach; endif; ?>
        </div>
        <?php if ($can_manage) : ?><div class="elev8-org__detail-actions"><a class="button" href="#edit-organization-unit"><?php esc_html_e('Edit Unit', 'elev8-os'); ?></a><a class="button" href="#assign-person"><?php esc_html_e('Assign Person', 'elev8-os'); ?></a><a class="button" href="<?php echo esc_url(Elev8_OS_Workspace_Service::url('organization', (int) $unit['id'])); ?>"><?php esc_html_e('Open Workspace', 'elev8-os'); ?></a></div><?php endif; ?>
    <?php }

    private static function render_create_forms(array $selected): void {
        $all_units = Elev8_OS_Organization_Service::units(['status' => 'all']);
        $users = get_users(['orderby' => 'display_name', 'order' => 'ASC']);
        ?>
        <section class="elev8-org__forms">
            <form id="<?php echo $selected ? 'edit-organization-unit' : 'new-organization-unit'; ?>" class="elev8-org__form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="elev8_os_save_org_unit"><?php wp_nonce_field('elev8_os_save_org_unit'); ?>
                <input type="hidden" name="unit_id" value="<?php echo esc_attr((string) ($selected['id'] ?? 0)); ?>">
                <h2><?php echo $selected ? esc_html__('Edit Organization Unit', 'elev8-os') : esc_html__('Add Organization Unit', 'elev8-os'); ?></h2>
                <label><?php esc_html_e('Name', 'elev8-os'); ?><input required name="name" value="<?php echo esc_attr((string) ($selected['name'] ?? '')); ?>"></label>
                <label><?php esc_html_e('Type', 'elev8-os'); ?><select required name="type"><?php foreach (Elev8_OS_Organization_Service::unit_types() as $key => $label) : ?><option value="<?php echo esc_attr($key); ?>" <?php selected((string) ($selected['type'] ?? ''), $key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
                <label><?php esc_html_e('Parent organization unit', 'elev8-os'); ?><select name="parent_id"><option value="0"><?php esc_html_e('None — top level', 'elev8-os'); ?></option><?php foreach ($all_units as $unit) : if ((int) ($selected['id'] ?? 0) === (int) $unit['id']) { continue; } ?><option value="<?php echo esc_attr((string) $unit['id']); ?>" <?php selected((int) ($selected['parent_id'] ?? 0), (int) $unit['id']); ?>><?php echo esc_html($unit['type_label'] . ' — ' . $unit['name']); ?></option><?php endforeach; ?></select></label>
                <label><?php esc_html_e('Status', 'elev8-os'); ?><select name="status"><option value="active" <?php selected((string) ($selected['status'] ?? 'active'), 'active'); ?>><?php esc_html_e('Active', 'elev8-os'); ?></option><option value="inactive" <?php selected((string) ($selected['status'] ?? ''), 'inactive'); ?>><?php esc_html_e('Inactive', 'elev8-os'); ?></option><option value="archived" <?php selected((string) ($selected['status'] ?? ''), 'archived'); ?>><?php esc_html_e('Archived', 'elev8-os'); ?></option></select></label>
                <label><?php esc_html_e('Internal code', 'elev8-os'); ?><input name="code" value="<?php echo esc_attr((string) ($selected['code'] ?? '')); ?>"></label>
                <label><?php esc_html_e('Timezone', 'elev8-os'); ?><input name="timezone" value="<?php echo esc_attr((string) ($selected['timezone'] ?? wp_timezone_string())); ?>"></label>
                <label class="is-wide"><?php esc_html_e('Description', 'elev8-os'); ?><textarea name="description" rows="4"><?php echo esc_textarea((string) ($selected['description'] ?? '')); ?></textarea></label>
                <label class="is-wide"><?php esc_html_e('Address or operating location', 'elev8-os'); ?><textarea name="address" rows="3"><?php echo esc_textarea((string) ($selected['address'] ?? '')); ?></textarea></label>
                <button class="button button-primary" type="submit"><?php esc_html_e('Save Organization Unit', 'elev8-os'); ?></button>
            </form>
            <?php if ($selected) : ?>
            <form id="assign-person" class="elev8-org__form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="elev8_os_save_org_assignment"><?php wp_nonce_field('elev8_os_save_org_assignment'); ?><input type="hidden" name="unit_id" value="<?php echo esc_attr((string) $selected['id']); ?>">
                <h2><?php esc_html_e('Assign Person', 'elev8-os'); ?></h2>
                <label class="is-wide"><?php esc_html_e('Find person', 'elev8-os'); ?><input type="search" data-elev8-org-user-search placeholder="<?php esc_attr_e('Type a name, email, or username…', 'elev8-os'); ?>" autocomplete="off"></label><label><?php esc_html_e('Person', 'elev8-os'); ?><select required name="user_id" data-elev8-org-user-select><option value=""><?php esc_html_e('Choose a WordPress user', 'elev8-os'); ?></option><?php foreach ($users as $user) : ?><option value="<?php echo esc_attr((string) $user->ID); ?>" data-search="<?php echo esc_attr(strtolower($user->display_name . ' ' . $user->user_email . ' ' . $user->user_login)); ?>"><?php echo esc_html($user->display_name . ' — ' . $user->user_email); ?></option><?php endforeach; ?></select></label>
                <label><?php esc_html_e('Assignment type', 'elev8-os'); ?><select name="assignment_type"><?php foreach (Elev8_OS_Organization_Service::assignment_types() as $key => $label) : ?><option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
                <label class="is-wide"><?php esc_html_e('Responsibility', 'elev8-os'); ?><input name="responsibility" placeholder="<?php esc_attr_e('Example: Production QC, class approvals, store opening', 'elev8-os'); ?>"></label>
                <label><input type="checkbox" name="is_primary" value="1"> <?php esc_html_e('Primary assignment', 'elev8-os'); ?></label><input type="hidden" name="active" value="1">
                <label><?php esc_html_e('Start date', 'elev8-os'); ?><input type="date" name="start_date"></label><label><?php esc_html_e('End date', 'elev8-os'); ?><input type="date" name="end_date"></label>
                <button class="button button-primary" type="submit"><?php esc_html_e('Save Person Assignment', 'elev8-os'); ?></button>
            </form>
            <?php endif; ?>
        </section>
    <?php }

    public static function save_unit(): void {
        if (!Elev8_OS_Organization_Service::can_manage()) { wp_die(esc_html__('Permission denied.', 'elev8-os')); }
        check_admin_referer('elev8_os_save_org_unit');
        $unit_id = absint($_POST['unit_id'] ?? 0);
        $saved = Elev8_OS_Organization_Service::save_unit([
            'existing_id' => $unit_id, 'name' => wp_unslash($_POST['name'] ?? ''), 'type' => wp_unslash($_POST['type'] ?? ''), 'parent_id' => absint($_POST['parent_id'] ?? 0), 'status' => wp_unslash($_POST['status'] ?? 'active'), 'code' => wp_unslash($_POST['code'] ?? ''), 'timezone' => wp_unslash($_POST['timezone'] ?? ''), 'description' => wp_unslash($_POST['description'] ?? ''), 'address' => wp_unslash($_POST['address'] ?? ''),
        ], $unit_id);
        wp_safe_redirect(self::url(['unit_id' => $saved, 'org_notice' => $saved ? 'unit_saved' : 'save_failed'])); exit;
    }

    public static function set_unit_status(): void {
        $unit_id = absint($_GET['unit_id'] ?? 0); check_admin_referer('elev8_os_set_org_unit_status_' . $unit_id);
        $ok = Elev8_OS_Organization_Service::set_status($unit_id, sanitize_key((string) ($_GET['status'] ?? '')));
        wp_safe_redirect(self::url(['unit_id' => $unit_id, 'org_notice' => $ok ? 'status_saved' : 'save_failed'])); exit;
    }

    public static function save_assignment(): void {
        if (!Elev8_OS_Organization_Service::can_manage()) { wp_die(esc_html__('Permission denied.', 'elev8-os')); }
        check_admin_referer('elev8_os_save_org_assignment');
        $unit_id = absint($_POST['unit_id'] ?? 0);
        $saved = Elev8_OS_Organization_Service::save_assignment(['user_id'=>absint($_POST['user_id'] ?? 0),'unit_id'=>$unit_id,'assignment_type'=>wp_unslash($_POST['assignment_type'] ?? 'member'),'responsibility'=>wp_unslash($_POST['responsibility'] ?? ''),'is_primary'=>!empty($_POST['is_primary']),'active'=>true,'start_date'=>wp_unslash($_POST['start_date'] ?? ''),'end_date'=>wp_unslash($_POST['end_date'] ?? '')]);
        wp_safe_redirect(self::url(['unit_id' => $unit_id, 'org_notice' => $saved ? 'assignment_saved' : 'save_failed'])); exit;
    }

    public static function remove_assignment(): void {
        $assignment_id = absint($_GET['assignment_id'] ?? 0); check_admin_referer('elev8_os_remove_org_assignment_' . $assignment_id);
        $assignment = Elev8_OS_Organization_Service::get_assignment($assignment_id);
        $ok = Elev8_OS_Organization_Service::remove_assignment($assignment_id);
        wp_safe_redirect(self::url(['unit_id' => absint($assignment['unit_id'] ?? 0), 'org_notice' => $ok ? 'assignment_removed' : 'save_failed'])); exit;
    }

    public static function command(array $commands, WP_User $user): array {
        if (Elev8_OS_Access_Service::user_can('view_organization', $user)) { $commands[] = ['id'=>'organization','label'=>__('Organization','elev8-os'),'description'=>__('Open businesses, brands, locations, departments, teams, and person assignments.','elev8-os'),'url'=>self::url(),'group'=>'organization','icon'=>'🏢','type'=>'command']; }
        return $commands;
    }

    private static function notice_text(string $notice): string {
        $notices = ['unit_saved'=>__('Organization unit saved.','elev8-os'),'status_saved'=>__('Organization status updated.','elev8-os'),'assignment_saved'=>__('Person assignment saved.','elev8-os'),'assignment_removed'=>__('Person assignment removed.','elev8-os'),'save_failed'=>__('The organization change could not be saved.','elev8-os')];
        return $notices[$notice] ?? '';
    }
}

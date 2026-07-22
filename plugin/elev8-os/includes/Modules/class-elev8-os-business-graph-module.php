<?php
/** CEO-facing Business Graph Registry and ownership diagnostics. */
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Business_Graph_Module {
    private const OPTION_PAGE_ID = 'elev8_os_business_graph_page_id';
    private const SLUG = 'business-graph';
    private const SHORTCODE = 'elev8_business_graph';

    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'admin_menu'], 30);
        add_shortcode(self::SHORTCODE, [__CLASS__, 'shortcode']);
        add_action('init', [__CLASS__, 'ensure_page'], 37);
        add_action('wp_enqueue_scripts', [__CLASS__, 'assets']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'admin_assets']);
        add_filter('elev8_os_application_shell_frontend', [__CLASS__, 'application_shell']);
        add_filter('elev8_os_command_palette_commands', [__CLASS__, 'command'], 27, 2);
    }

    public static function activate(): void { self::ensure_page(true); }

    public static function admin_menu(): void {
        add_submenu_page('elev8-os', __('Business Graph', 'elev8-os'), __('Business Graph', 'elev8-os'), 'manage_options', 'elev8-business-graph', [__CLASS__, 'render_admin']);
    }

    public static function ensure_page(bool $force = false): void {
        $id = absint(get_option(self::OPTION_PAGE_ID));
        if ($id && get_post($id) instanceof WP_Post) { return; }
        $page = get_page_by_path(self::SLUG, OBJECT, 'page');
        if ($page instanceof WP_Post && $page->post_status !== 'trash') { update_option(self::OPTION_PAGE_ID, (int) $page->ID, false); return; }
        if (!$force && !current_user_can('manage_options')) { return; }
        $id = wp_insert_post(['post_title'=>__('Business Graph','elev8-os'),'post_name'=>self::SLUG,'post_content'=>'['.self::SHORTCODE.']','post_status'=>'publish','post_type'=>'page','post_author'=>get_current_user_id(),'comment_status'=>'closed'], true);
        if (!is_wp_error($id) && $id > 0) { update_option(self::OPTION_PAGE_ID, (int) $id, false); }
    }

    public static function url(): string {
        $id = absint(get_option(self::OPTION_PAGE_ID));
        return $id ? (string) get_permalink($id) : home_url('/' . self::SLUG . '/');
    }

    public static function application_shell(bool $show): bool { return $show || is_page(absint(get_option(self::OPTION_PAGE_ID))) || is_page(self::SLUG); }
    public static function assets(): void { if (is_page(absint(get_option(self::OPTION_PAGE_ID))) || is_page(self::SLUG)) { wp_enqueue_style('elev8-business-graph', ELEV8_OS_URL . 'assets/css/business-graph.css', [], ELEV8_OS_VERSION); } }
    public static function admin_assets(string $hook): void { if ($hook === 'elev8-os_page_elev8-business-graph') { wp_enqueue_style('elev8-business-graph', ELEV8_OS_URL . 'assets/css/business-graph.css', [], ELEV8_OS_VERSION); } }

    public static function shortcode(): string {
        if (!is_user_logged_in()) { return '<p>' . esc_html__('Please sign in to view the Business Graph.', 'elev8-os') . '</p>'; }
        if (!current_user_can('manage_options')) { return '<p>' . esc_html__('The Business Graph Registry is currently available to the CEO and platform administrators.', 'elev8-os') . '</p>'; }
        return self::content(false);
    }

    public static function render_admin(): void {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('You do not have permission to view the Business Graph.', 'elev8-os')); }
        echo '<div class="wrap">' . self::content(true) . '</div>';
    }

    private static function content(bool $admin): string {
        $objects = Elev8_OS_Business_Graph_Registry_Service::objects();
        $relationships = Elev8_OS_Business_Graph_Registry_Service::relationships();
        $diagnostics = Elev8_OS_Business_Graph_Registry_Service::diagnostics();
        $filter = sanitize_key((string) ($_GET['engine'] ?? ''));
        ob_start(); ?>
        <main class="elev8-graph<?php echo $admin ? ' is-admin' : ''; ?>">
            <header class="elev8-graph__hero">
                <div><p class="elev8-graph__eyebrow"><?php esc_html_e('Business Graph Registry', 'elev8-os'); ?></p><h1><?php esc_html_e('Ownership before features', 'elev8-os'); ?></h1><p><?php esc_html_e('Every business object declares its owning engine, authoritative system, organization scope, and permitted relationships before other engines use it.', 'elev8-os'); ?></p></div>
                <a class="button" href="<?php echo esc_url(class_exists('Elev8_OS_Business_Blueprint_Module') ? Elev8_OS_Business_Blueprint_Module::url() : '#'); ?>"><?php esc_html_e('Open Blueprint', 'elev8-os'); ?></a>
            </header>
            <section class="elev8-graph__stats">
                <article><strong><?php echo esc_html((string) $diagnostics['object_count']); ?></strong><span><?php esc_html_e('Registered objects', 'elev8-os'); ?></span></article>
                <article><strong><?php echo esc_html((string) $diagnostics['relationship_count']); ?></strong><span><?php esc_html_e('Relationship types', 'elev8-os'); ?></span></article>
                <article><strong><?php echo esc_html((string) count($diagnostics['engines'])); ?></strong><span><?php esc_html_e('Owning engines', 'elev8-os'); ?></span></article>
                <article><strong><?php echo esc_html((string) count($diagnostics['authorities'])); ?></strong><span><?php esc_html_e('Authoritative systems', 'elev8-os'); ?></span></article>
            </section>
            <section class="elev8-graph__panel">
                <div class="elev8-graph__panel-head"><div><h2><?php esc_html_e('Object Registry', 'elev8-os'); ?></h2><p><?php esc_html_e('This registry prevents duplicate ownership and makes engine boundaries explicit.', 'elev8-os'); ?></p></div>
                    <form method="get"><label><?php esc_html_e('Engine', 'elev8-os'); ?><select name="engine" onchange="this.form.submit()"><option value=""><?php esc_html_e('All engines', 'elev8-os'); ?></option><?php foreach (array_keys($diagnostics['engines']) as $engine) : ?><option value="<?php echo esc_attr(sanitize_key($engine)); ?>" <?php selected($filter, sanitize_key($engine)); ?>><?php echo esc_html($engine); ?></option><?php endforeach; ?></select></label></form>
                </div>
                <div class="elev8-graph__objects">
                    <?php foreach ($objects as $type => $object) : if ($filter && sanitize_key((string) $object['engine']) !== $filter) { continue; } ?>
                    <article class="elev8-graph__object">
                        <div class="elev8-graph__object-title"><span><?php echo esc_html($type); ?></span><h3><?php echo esc_html((string) $object['label']); ?></h3></div>
                        <dl><div><dt><?php esc_html_e('Owning engine', 'elev8-os'); ?></dt><dd><?php echo esc_html((string) $object['engine']); ?></dd></div><div><dt><?php esc_html_e('Authority', 'elev8-os'); ?></dt><dd><?php echo esc_html((string) $object['authoritative_system']); ?></dd></div><div><dt><?php esc_html_e('Source type', 'elev8-os'); ?></dt><dd><?php echo esc_html((string) $object['source_type']); ?></dd></div><div><dt><?php esc_html_e('Organization scoped', 'elev8-os'); ?></dt><dd><?php echo !empty($object['organization_scoped']) ? esc_html__('Yes', 'elev8-os') : esc_html__('No', 'elev8-os'); ?></dd></div></dl>
                        <p><?php echo esc_html((string) $object['notes']); ?></p>
                    </article>
                    <?php endforeach; ?>
                </div>
            </section>
            <section class="elev8-graph__panel"><div class="elev8-graph__panel-head"><div><h2><?php esc_html_e('Relationship Registry', 'elev8-os'); ?></h2><p><?php esc_html_e('New explicit relationships are validated here before they are stored.', 'elev8-os'); ?></p></div></div>
                <div class="elev8-graph__relationships"><?php foreach ($relationships as $kind => $relationship) : ?><article><div><code><?php echo esc_html($kind); ?></code><h3><?php echo esc_html((string) $relationship['label']); ?></h3></div><p><strong><?php esc_html_e('From:', 'elev8-os'); ?></strong> <?php echo esc_html(implode(', ', (array) $relationship['from'])); ?><br><strong><?php esc_html_e('To:', 'elev8-os'); ?></strong> <?php echo esc_html(implode(', ', (array) $relationship['to'])); ?></p><small><?php echo esc_html((string) $relationship['notes']); ?></small></article><?php endforeach; ?></div>
            </section>
            <section class="elev8-graph__panel"><div class="elev8-graph__panel-head"><div><h2><?php esc_html_e('Authority Summary', 'elev8-os'); ?></h2><p><?php esc_html_e('Elev8 OS links to authoritative records rather than cloning them.', 'elev8-os'); ?></p></div></div><div class="elev8-graph__authority"><?php foreach ($diagnostics['authorities'] as $authority => $count) : ?><article><strong><?php echo esc_html((string) $count); ?></strong><span><?php echo esc_html($authority); ?></span></article><?php endforeach; ?></div></section>
        </main>
        <?php return (string) ob_get_clean();
    }

    public static function command(array $commands, WP_User $user): array {
        if (user_can($user, 'manage_options')) { $commands[] = ['id'=>'business_graph','label'=>__('Business Graph','elev8-os'),'description'=>__('Review object ownership, authoritative systems, and allowed relationships.','elev8-os'),'url'=>self::url(),'group'=>'architecture','icon'=>'🕸️','type'=>'command']; }
        return $commands;
    }
}

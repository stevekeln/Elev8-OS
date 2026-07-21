<?php
/** Universal frontend workspace for Elev8 OS records. */
if (!defined('ABSPATH')) { exit; }
final class Elev8_OS_Workspace_Module {
    private const OPTION_PAGE_ID = 'elev8_os_workspace_page_id';
    private const SLUG = 'elev8-workspace';

    public static function init(): void {
        add_shortcode('elev8_os_workspace', [__CLASS__, 'shortcode']);
        add_action('admin_init', [__CLASS__, 'ensure_page_for_admin']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue']);
        add_filter('elev8_os_application_shell_frontend', [__CLASS__, 'shell_page']);
        add_filter('elev8_os_command_palette_commands', [__CLASS__, 'command'], 10, 2);
        add_action('admin_post_elev8_os_connect_workspace_record', [__CLASS__, 'connect_record']);
        add_action('admin_post_elev8_os_disconnect_workspace_record', [__CLASS__, 'disconnect_record']);
    }
    public static function activate(): void { self::ensure_page(true); }
    public static function ensure_page_for_admin(): void { if (current_user_can('manage_options')) { self::ensure_page(true); } }
    public static function enqueue(): void { if (self::is_page()) { wp_enqueue_style('elev8-os-workspace', ELEV8_OS_URL . 'assets/css/workspace.css', [], ELEV8_OS_VERSION); } }
    public static function url(): string { $id = absint(get_option(self::OPTION_PAGE_ID)); return $id && get_post_status($id) ? (string) get_permalink($id) : home_url('/' . self::SLUG . '/'); }
    public static function is_page(): bool { $id = absint(get_option(self::OPTION_PAGE_ID)); return ($id && is_page($id)) || is_page(self::SLUG); }
    public static function shell_page(bool $render): bool { return $render || self::is_page(); }

    public static function shortcode(): string {
        if (!is_user_logged_in()) { return '<p>' . esc_html__('Please sign in to open this workspace.', 'elev8-os') . '</p>'; }
        $type = Elev8_OS_Workspace_Service::normalize_type((string) ($_GET['workspace_type'] ?? ''));
        $id = absint($_GET['workspace_id'] ?? 0);
        if ($type === '' || $id < 1) { return self::empty_state(); }
        $user = wp_get_current_user();
        if (!Elev8_OS_Workspace_Service::can_view($type, $id, $user)) { return '<p>' . esc_html__('You do not have permission to open this workspace.', 'elev8-os') . '</p>'; }

        $summary = Elev8_OS_Workspace_Service::summary($type, $id);
        $details = Elev8_OS_Workspace_Service::source_details($type, $id);
        $activities = Elev8_OS_Workspace_Service::activities($type, $id);
        $work = Elev8_OS_Workspace_Service::work_items($type, $id);
        $conversations = array_filter(Elev8_OS_Workspace_Service::conversations($type, $id), static function($thread) use ($user): bool { return Elev8_OS_Conversation_Service::can_view((int) $thread->ID, $user); });
        $related = Elev8_OS_Workspace_Service::related_records($type, $id);
        $people = Elev8_OS_Workspace_Service::people($type, $id);
        $files = Elev8_OS_Workspace_Service::files($type, $id);
        $impact = Elev8_OS_Workspace_Service::relationship_impact($type, $id);
        $can_manage_relationships = class_exists('Elev8_OS_Relationship_Service') && Elev8_OS_Relationship_Service::can_manage($user);

        ob_start(); ?>
        <main class="elev8-workspace">
            <nav class="elev8-workspace__crumbs"><a href="<?php echo esc_url(class_exists('Elev8_OS_Portal_Page_Manager') ? Elev8_OS_Portal_Page_Manager::get_url('dashboard') : home_url('/')); ?>"><?php esc_html_e('My Dashboard', 'elev8-os'); ?></a><span>›</span><strong><?php echo esc_html($summary['label']); ?></strong></nav>
            <header class="elev8-workspace__hero">
                <div><p><?php esc_html_e('ELEV8 OS WORKSPACE', 'elev8-os'); ?></p><h1><?php echo esc_html($summary['title']); ?></h1><div class="elev8-workspace__meta"><span><?php echo esc_html($summary['label']); ?></span><strong><?php echo esc_html($summary['status']); ?></strong></div><?php if ($summary['description']) : ?><p class="elev8-workspace__description"><?php echo esc_html($summary['description']); ?></p><?php endif; ?></div>
                <div class="elev8-workspace__hero-actions"><?php if ($summary['source_url']) : ?><a href="<?php echo esc_url($summary['source_url']); ?>"><?php esc_html_e('Open Source Record', 'elev8-os'); ?></a><?php endif; ?><a href="<?php echo esc_url(Elev8_OS_Action_Center_Module::url()); ?>"><?php esc_html_e('My Actions', 'elev8-os'); ?></a></div>
            </header>

            <section class="elev8-workspace__metrics">
                <article><strong><?php echo count($activities); ?></strong><span><?php esc_html_e('Timeline Entries', 'elev8-os'); ?></span></article>
                <article><strong><?php echo count($work); ?></strong><span><?php esc_html_e('Actions', 'elev8-os'); ?></span></article>
                <article><strong><?php echo count($conversations); ?></strong><span><?php esc_html_e('Conversations', 'elev8-os'); ?></span></article>
                <article><strong><?php echo count($related); ?></strong><span><?php esc_html_e('Related Records', 'elev8-os'); ?></span></article>
            </section>

            <?php if (!empty($_GET['relationship_saved'])) : ?><div class="elev8-workspace__notice elev8-workspace__notice--success"><?php esc_html_e('Relationship saved.', 'elev8-os'); ?></div><?php endif; ?>
            <?php if (!empty($_GET['relationship_removed'])) : ?><div class="elev8-workspace__notice elev8-workspace__notice--success"><?php esc_html_e('Relationship removed.', 'elev8-os'); ?></div><?php endif; ?>
            <?php if (!empty($_GET['relationship_error'])) : ?><div class="elev8-workspace__notice elev8-workspace__notice--error"><?php esc_html_e('The relationship could not be saved. Confirm the record type, record ID, and your access.', 'elev8-os'); ?></div><?php endif; ?>

            <section class="elev8-workspace__panel elev8-workspace__impact">
                <header><div><p><?php esc_html_e('RELATIONSHIP ENGINE', 'elev8-os'); ?></p><h2><?php esc_html_e('Operational impact', 'elev8-os'); ?></h2></div></header>
                <div class="elev8-workspace__impact-grid">
                    <article><strong><?php echo esc_html((string) ($impact['total'] ?? 0)); ?></strong><span><?php esc_html_e('Explicit Links', 'elev8-os'); ?></span></article>
                    <article><strong><?php echo esc_html((string) ($impact['depends_on'] ?? 0)); ?></strong><span><?php esc_html_e('Dependencies', 'elev8-os'); ?></span></article>
                    <article><strong><?php echo esc_html((string) ($impact['blocks'] ?? 0)); ?></strong><span><?php esc_html_e('Records Blocked', 'elev8-os'); ?></span></article>
                    <article><strong><?php echo esc_html((string) ($impact['people'] ?? 0)); ?></strong><span><?php esc_html_e('Connected People', 'elev8-os'); ?></span></article>
                </div>
                <p class="elev8-workspace__impact-note"><?php esc_html_e('Explicit relationships supplement inferred links. Source records remain owned by their original Elev8 OS engines.', 'elev8-os'); ?></p>
            </section>

            <?php if ($details !== '') : ?><section class="elev8-workspace__panel elev8-workspace__source-details"><header><h2><?php esc_html_e('Source Details', 'elev8-os'); ?></h2></header><div><?php echo wp_kses_post(wpautop($details)); ?></div></section><?php endif; ?>

            <div class="elev8-workspace__grid">
                <section class="elev8-workspace__panel elev8-workspace__timeline"><header><div><p><?php esc_html_e('UNIVERSAL ACTIVITY TIMELINE', 'elev8-os'); ?></p><h2><?php esc_html_e('What happened', 'elev8-os'); ?></h2></div></header>
                    <?php if (!$activities) : ?><div class="elev8-workspace__empty"><?php esc_html_e('No verified timeline activity is connected yet.', 'elev8-os'); ?></div><?php endif; ?>
                    <?php foreach ($activities as $activity) : ?><article><time><?php echo esc_html(get_the_date('M j, Y g:i a', $activity)); ?></time><div><strong><?php echo esc_html(get_the_title($activity)); ?></strong><?php if ($activity->post_content) : ?><p><?php echo esc_html(wp_trim_words(wp_strip_all_tags($activity->post_content), 30)); ?></p><?php endif; ?></div></article><?php endforeach; ?>
                </section>

                <aside class="elev8-workspace__side">
                    <?php self::render_work($work); ?>
                    <?php self::render_conversations($conversations); ?>
                    <?php self::render_related($related, $type, $id, $can_manage_relationships); ?>
                    <?php if ($can_manage_relationships) { self::render_relationship_form($type, $id); } ?>
                    <?php self::render_people($people); ?>
                    <?php self::render_files($files); ?>
                </aside>
            </div>
        </main>
        <?php return (string) ob_get_clean();
    }

    private static function render_work(array $items): void { ?>
        <section class="elev8-workspace__panel"><header><h2><?php esc_html_e('Actions', 'elev8-os'); ?></h2><a href="<?php echo esc_url(Elev8_OS_Action_Center_Module::url()); ?>"><?php esc_html_e('Open Action Center', 'elev8-os'); ?></a></header><?php if (!$items) : ?><div class="elev8-workspace__empty"><?php esc_html_e('No actions are connected.', 'elev8-os'); ?></div><?php endif; ?><?php foreach ($items as $item) : $status = (string) get_post_meta($item->ID, '_elev8_work_status', true) ?: 'new'; ?><a class="elev8-workspace__record" href="<?php echo esc_url(Elev8_OS_Workspace_Service::url('work', (int) $item->ID)); ?>"><span><?php echo esc_html(Elev8_OS_Work_Service::statuses()[$status] ?? ucfirst($status)); ?></span><strong><?php echo esc_html(get_the_title($item)); ?></strong></a><?php endforeach; ?></section>
    <?php }
    private static function render_conversations(array $items): void { ?>
        <section class="elev8-workspace__panel"><header><h2><?php esc_html_e('Conversations', 'elev8-os'); ?></h2><a href="<?php echo esc_url(Elev8_OS_Conversations_Module::url()); ?>"><?php esc_html_e('Open Center', 'elev8-os'); ?></a></header><?php if (!$items) : ?><div class="elev8-workspace__empty"><?php esc_html_e('No conversations are connected.', 'elev8-os'); ?></div><?php endif; ?><?php foreach ($items as $item) : ?><a class="elev8-workspace__record" href="<?php echo esc_url(Elev8_OS_Workspace_Service::url('conversation', (int) $item->ID)); ?>"><span><?php echo esc_html(ucfirst((string) get_post_meta($item->ID, '_elev8_conversation_status', true) ?: 'open')); ?></span><strong><?php echo esc_html(get_the_title($item)); ?></strong></a><?php endforeach; ?></section>
    <?php }
    private static function render_related(array $items, string $source_type, int $source_id, bool $can_manage): void { ?>
        <section class="elev8-workspace__panel"><header><h2><?php esc_html_e('Related Records', 'elev8-os'); ?></h2></header><?php if (!$items) : ?><div class="elev8-workspace__empty"><?php esc_html_e('No related records are connected yet.', 'elev8-os'); ?></div><?php endif; ?><?php foreach ($items as $item) : ?><div class="elev8-workspace__relationship"><a class="elev8-workspace__record" href="<?php echo esc_url($item['url']); ?>"><span><?php if (!empty($item['kind_label'])) : ?><?php echo esc_html($item['kind_label']); ?> · <?php endif; ?><?php echo esc_html($item['label']); ?> · <?php echo esc_html($item['status']); ?></span><strong><?php echo esc_html($item['title']); ?></strong><?php if (!empty($item['note'])) : ?><small><?php echo esc_html($item['note']); ?></small><?php endif; ?></a><?php if ($can_manage && !empty($item['relationship_id'])) : ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="elev8-workspace__disconnect"><input type="hidden" name="action" value="elev8_os_disconnect_workspace_record"><input type="hidden" name="relationship_id" value="<?php echo esc_attr((string) $item['relationship_id']); ?>"><input type="hidden" name="source_type" value="<?php echo esc_attr($source_type); ?>"><input type="hidden" name="source_id" value="<?php echo esc_attr((string) $source_id); ?>"><?php wp_nonce_field('elev8_os_disconnect_workspace_record_' . (int) $item['relationship_id']); ?><button type="submit"><?php esc_html_e('Remove', 'elev8-os'); ?></button></form><?php endif; ?></div><?php endforeach; ?></section>
    <?php }

    private static function render_relationship_form(string $source_type, int $source_id): void { ?>
        <section class="elev8-workspace__panel elev8-workspace__connect"><header><div><p><?php esc_html_e('BUSINESS GRAPH', 'elev8-os'); ?></p><h2><?php esc_html_e('Connect another record', 'elev8-os'); ?></h2></div></header>
            <p><?php esc_html_e('Create a trusted, two-way relationship without copying either record. Open the target workspace to find its record type and ID in the URL.', 'elev8-os'); ?></p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="elev8_os_connect_workspace_record">
                <input type="hidden" name="source_type" value="<?php echo esc_attr($source_type); ?>">
                <input type="hidden" name="source_id" value="<?php echo esc_attr((string) $source_id); ?>">
                <?php wp_nonce_field('elev8_os_connect_workspace_record_' . $source_type . '_' . $source_id); ?>
                <label><span><?php esc_html_e('Relationship', 'elev8-os'); ?></span><select name="relationship_kind"><?php foreach (Elev8_OS_Relationship_Service::kinds() as $kind => $label) : ?><option value="<?php echo esc_attr($kind); ?>"><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
                <label><span><?php esc_html_e('Target record type', 'elev8-os'); ?></span><select name="target_type"><?php foreach (Elev8_OS_Workspace_Service::types() as $target_type => $label) : ?><option value="<?php echo esc_attr($target_type); ?>"><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
                <label><span><?php esc_html_e('Target record ID', 'elev8-os'); ?></span><input type="number" min="1" name="target_id" required></label>
                <label class="elev8-workspace__connect-note"><span><?php esc_html_e('Relationship note (optional)', 'elev8-os'); ?></span><textarea name="relationship_note" rows="3" placeholder="<?php echo esc_attr__('Why are these records connected?', 'elev8-os'); ?>"></textarea></label>
                <button type="submit"><?php esc_html_e('Connect Record', 'elev8-os'); ?></button>
            </form>
        </section>
    <?php }
    private static function render_people(array $items): void { ?>
        <section class="elev8-workspace__panel"><header><h2><?php esc_html_e('Related People', 'elev8-os'); ?></h2></header><?php if (!$items) : ?><div class="elev8-workspace__empty"><?php esc_html_e('No people are connected yet.', 'elev8-os'); ?></div><?php endif; ?><?php foreach ($items as $user) : ?><a class="elev8-workspace__person" href="<?php echo esc_url(Elev8_OS_Workspace_Service::url('person', (int) $user->ID)); ?>"><?php echo get_avatar($user->ID, 42); ?><span><strong><?php echo esc_html($user->display_name); ?></strong><small><?php echo esc_html($user->user_email); ?></small></span></a><?php endforeach; ?></section>
    <?php }
    private static function render_files(array $items): void { ?>
        <section class="elev8-workspace__panel"><header><h2><?php esc_html_e('Files', 'elev8-os'); ?></h2></header><?php if (!$items) : ?><div class="elev8-workspace__empty"><?php esc_html_e('No files are connected yet.', 'elev8-os'); ?></div><?php endif; ?><?php foreach ($items as $file) : $url = wp_get_attachment_url($file->ID); ?><a class="elev8-workspace__record" href="<?php echo esc_url($url ?: '#'); ?>" target="_blank" rel="noopener"><span><?php echo esc_html(get_post_mime_type($file) ?: __('File', 'elev8-os')); ?></span><strong><?php echo esc_html(get_the_title($file)); ?></strong></a><?php endforeach; ?></section>
    <?php }
    public static function connect_record(): void {
        if (!is_user_logged_in()) { auth_redirect(); }
        $source_type = Elev8_OS_Workspace_Service::normalize_type((string) ($_POST['source_type'] ?? ''));
        $source_id = absint($_POST['source_id'] ?? 0);
        check_admin_referer('elev8_os_connect_workspace_record_' . $source_type . '_' . $source_id);
        $redirect = Elev8_OS_Workspace_Service::url($source_type, $source_id);
        if (!Elev8_OS_Relationship_Service::can_manage() || !Elev8_OS_Workspace_Service::can_view($source_type, $source_id)) {
            wp_safe_redirect(add_query_arg('relationship_error', 1, $redirect)); exit;
        }
        $target_type = Elev8_OS_Workspace_Service::normalize_type((string) ($_POST['target_type'] ?? ''));
        $target_id = absint($_POST['target_id'] ?? 0);
        $kind = sanitize_key((string) ($_POST['relationship_kind'] ?? 'related_to'));
        $note = sanitize_textarea_field(wp_unslash((string) ($_POST['relationship_note'] ?? '')));
        $saved = Elev8_OS_Relationship_Service::connect($source_type, $source_id, $target_type, $target_id, $kind, $note);
        wp_safe_redirect(add_query_arg($saved ? 'relationship_saved' : 'relationship_error', 1, $redirect)); exit;
    }

    public static function disconnect_record(): void {
        if (!is_user_logged_in()) { auth_redirect(); }
        $relationship_id = absint($_POST['relationship_id'] ?? 0);
        check_admin_referer('elev8_os_disconnect_workspace_record_' . $relationship_id);
        $source_type = Elev8_OS_Workspace_Service::normalize_type((string) ($_POST['source_type'] ?? ''));
        $source_id = absint($_POST['source_id'] ?? 0);
        $redirect = Elev8_OS_Workspace_Service::url($source_type, $source_id);
        $removed = Elev8_OS_Relationship_Service::disconnect($relationship_id);
        wp_safe_redirect(add_query_arg($removed ? 'relationship_removed' : 'relationship_error', 1, $redirect)); exit;
    }

    private static function empty_state(): string { return '<main class="elev8-workspace"><header class="elev8-workspace__hero"><div><p>ELEV8 OS WORKSPACE</p><h1>' . esc_html__('Open the thing. Everything related is here.', 'elev8-os') . '</h1><p class="elev8-workspace__description">' . esc_html__('Use an Open Workspace button on a work item, conversation, manager log, event application, or person.', 'elev8-os') . '</p></div></header></main>'; }
    public static function command(array $commands, WP_User $user): array { $commands[] = ['id'=>'workspace-engine','label'=>__('Workspace Engine','elev8-os'),'description'=>__('Open the universal workspace foundation.','elev8-os'),'url'=>self::url(),'group'=>'operations','icon'=>'◫','type'=>'command']; return $commands; }
    private static function ensure_page(bool $create): int { $id = absint(get_option(self::OPTION_PAGE_ID)); if ($id && get_post_status($id)) { return $id; } $page = get_page_by_path(self::SLUG, OBJECT, 'page'); if ($page instanceof WP_Post) { update_option(self::OPTION_PAGE_ID, $page->ID, false); return (int) $page->ID; } if (!$create) { return 0; } $id = wp_insert_post(['post_title'=>__('Elev8 OS Workspace','elev8-os'),'post_name'=>self::SLUG,'post_content'=>'[elev8_os_workspace]','post_status'=>'publish','post_type'=>'page','comment_status'=>'closed'], true); if (!is_wp_error($id) && $id > 0) { update_option(self::OPTION_PAGE_ID, (int) $id, false); return (int) $id; } return 0; }
}

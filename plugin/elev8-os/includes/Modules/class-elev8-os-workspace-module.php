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

            <?php if ($details !== '') : ?><section class="elev8-workspace__panel elev8-workspace__source-details"><header><h2><?php esc_html_e('Source Details', 'elev8-os'); ?></h2></header><div><?php echo wp_kses_post(wpautop($details)); ?></div></section><?php endif; ?>

            <div class="elev8-workspace__grid">
                <section class="elev8-workspace__panel elev8-workspace__timeline"><header><div><p><?php esc_html_e('UNIVERSAL ACTIVITY TIMELINE', 'elev8-os'); ?></p><h2><?php esc_html_e('What happened', 'elev8-os'); ?></h2></div></header>
                    <?php if (!$activities) : ?><div class="elev8-workspace__empty"><?php esc_html_e('No verified timeline activity is connected yet.', 'elev8-os'); ?></div><?php endif; ?>
                    <?php foreach ($activities as $activity) : ?><article><time><?php echo esc_html(get_the_date('M j, Y g:i a', $activity)); ?></time><div><strong><?php echo esc_html(get_the_title($activity)); ?></strong><?php if ($activity->post_content) : ?><p><?php echo esc_html(wp_trim_words(wp_strip_all_tags($activity->post_content), 30)); ?></p><?php endif; ?></div></article><?php endforeach; ?>
                </section>

                <aside class="elev8-workspace__side">
                    <?php self::render_work($work); ?>
                    <?php self::render_conversations($conversations); ?>
                    <?php self::render_related($related); ?>
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
    private static function render_related(array $items): void { ?>
        <section class="elev8-workspace__panel"><header><h2><?php esc_html_e('Related Records', 'elev8-os'); ?></h2></header><?php if (!$items) : ?><div class="elev8-workspace__empty"><?php esc_html_e('No related records are connected yet.', 'elev8-os'); ?></div><?php endif; ?><?php foreach ($items as $item) : ?><a class="elev8-workspace__record" href="<?php echo esc_url($item['url']); ?>"><span><?php echo esc_html($item['label']); ?> · <?php echo esc_html($item['status']); ?></span><strong><?php echo esc_html($item['title']); ?></strong></a><?php endforeach; ?></section>
    <?php }
    private static function render_people(array $items): void { ?>
        <section class="elev8-workspace__panel"><header><h2><?php esc_html_e('Related People', 'elev8-os'); ?></h2></header><?php if (!$items) : ?><div class="elev8-workspace__empty"><?php esc_html_e('No people are connected yet.', 'elev8-os'); ?></div><?php endif; ?><?php foreach ($items as $user) : ?><a class="elev8-workspace__person" href="<?php echo esc_url(Elev8_OS_Workspace_Service::url('person', (int) $user->ID)); ?>"><?php echo get_avatar($user->ID, 42); ?><span><strong><?php echo esc_html($user->display_name); ?></strong><small><?php echo esc_html($user->user_email); ?></small></span></a><?php endforeach; ?></section>
    <?php }
    private static function render_files(array $items): void { ?>
        <section class="elev8-workspace__panel"><header><h2><?php esc_html_e('Files', 'elev8-os'); ?></h2></header><?php if (!$items) : ?><div class="elev8-workspace__empty"><?php esc_html_e('No files are connected yet.', 'elev8-os'); ?></div><?php endif; ?><?php foreach ($items as $file) : $url = wp_get_attachment_url($file->ID); ?><a class="elev8-workspace__record" href="<?php echo esc_url($url ?: '#'); ?>" target="_blank" rel="noopener"><span><?php echo esc_html(get_post_mime_type($file) ?: __('File', 'elev8-os')); ?></span><strong><?php echo esc_html(get_the_title($file)); ?></strong></a><?php endforeach; ?></section>
    <?php }
    private static function empty_state(): string { return '<main class="elev8-workspace"><header class="elev8-workspace__hero"><div><p>ELEV8 OS WORKSPACE</p><h1>' . esc_html__('Open the thing. Everything related is here.', 'elev8-os') . '</h1><p class="elev8-workspace__description">' . esc_html__('Use an Open Workspace button on a work item, conversation, manager log, event application, or person.', 'elev8-os') . '</p></div></header></main>'; }
    public static function command(array $commands, WP_User $user): array { $commands[] = ['id'=>'workspace-engine','label'=>__('Workspace Engine','elev8-os'),'description'=>__('Open the universal workspace foundation.','elev8-os'),'url'=>self::url(),'group'=>'operations','icon'=>'◫','type'=>'command']; return $commands; }
    private static function ensure_page(bool $create): int { $id = absint(get_option(self::OPTION_PAGE_ID)); if ($id && get_post_status($id)) { return $id; } $page = get_page_by_path(self::SLUG, OBJECT, 'page'); if ($page instanceof WP_Post) { update_option(self::OPTION_PAGE_ID, $page->ID, false); return (int) $page->ID; } if (!$create) { return 0; } $id = wp_insert_post(['post_title'=>__('Elev8 OS Workspace','elev8-os'),'post_name'=>self::SLUG,'post_content'=>'[elev8_os_workspace]','post_status'=>'publish','post_type'=>'page','comment_status'=>'closed'], true); if (!is_wp_error($id) && $id > 0) { update_option(self::OPTION_PAGE_ID, (int) $id, false); return (int) $id; } return 0; }
}

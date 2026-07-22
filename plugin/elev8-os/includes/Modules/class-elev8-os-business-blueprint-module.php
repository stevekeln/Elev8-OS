<?php
if (!defined('ABSPATH')) { exit; }

/** CEO-facing, repository-backed Business Blueprint workspace. */
final class Elev8_OS_Business_Blueprint_Module {
    private const OPTION_PAGE_ID = 'elev8_os_business_blueprint_page_id';
    private const SLUG = 'business-blueprint';
    private const SHORTCODE = 'elev8_business_blueprint';

    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'admin_menu'], 28);
        add_shortcode(self::SHORTCODE, [__CLASS__, 'shortcode']);
        add_action('init', [__CLASS__, 'ensure_page'], 35);
        add_action('wp_enqueue_scripts', [__CLASS__, 'assets']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'admin_assets']);
        add_filter('elev8_os_command_palette_commands', [__CLASS__, 'command'], 25, 2);
        add_action('admin_post_elev8_os_download_blueprint', [__CLASS__, 'download']);
        add_filter('elev8_os_application_shell_frontend', [__CLASS__, 'application_shell']);
    }

    public static function activate(): void { self::ensure_page(true); }

    public static function application_shell(bool $show): bool {
        return $show || is_page(absint(get_option(self::OPTION_PAGE_ID))) || is_page(self::SLUG);
    }

    public static function admin_menu(): void {
        add_submenu_page('elev8-os', __('Business Blueprint', 'elev8-os'), __('Business Blueprint', 'elev8-os'), 'manage_options', 'elev8-business-blueprint', [__CLASS__, 'render_admin']);
    }

    public static function ensure_page(bool $force = false): void {
        $id = absint(get_option(self::OPTION_PAGE_ID));
        if ($id && get_post($id) instanceof WP_Post) { return; }
        $page = get_page_by_path(self::SLUG, OBJECT, 'page');
        if ($page instanceof WP_Post && $page->post_status !== 'trash') { update_option(self::OPTION_PAGE_ID, (int) $page->ID, false); return; }
        if (!$force && !current_user_can('manage_options')) { return; }
        $id = wp_insert_post(['post_title'=>__('Business Blueprint','elev8-os'),'post_name'=>self::SLUG,'post_content'=>'['.self::SHORTCODE.']','post_status'=>'publish','post_type'=>'page','post_author'=>get_current_user_id(),'comment_status'=>'closed'], true);
        if (!is_wp_error($id) && $id > 0) { update_option(self::OPTION_PAGE_ID, (int) $id, false); }
    }

    public static function url(): string {
        $id = absint(get_option(self::OPTION_PAGE_ID));
        return $id ? (string) get_permalink($id) : home_url('/' . self::SLUG . '/');
    }

    public static function assets(): void {
        if (is_page(absint(get_option(self::OPTION_PAGE_ID))) || is_page(self::SLUG)) {
            wp_enqueue_style('elev8-business-blueprint', ELEV8_OS_URL . 'assets/css/business-blueprint.css', [], ELEV8_OS_VERSION);
        }
    }

    public static function admin_assets(string $hook): void {
        if ($hook === 'elev8-os_page_elev8-business-blueprint') {
            wp_enqueue_style('elev8-business-blueprint', ELEV8_OS_URL . 'assets/css/business-blueprint.css', [], ELEV8_OS_VERSION);
        }
    }

    public static function shortcode(): string {
        if (!is_user_logged_in()) { return '<p>' . esc_html__('Please sign in to view the Business Blueprint.', 'elev8-os') . '</p>'; }
        if (!current_user_can('manage_options')) { return '<p>' . esc_html__('The Business Blueprint is currently available to the CEO and platform administrators.', 'elev8-os') . '</p>'; }
        return self::content(false);
    }

    public static function render_admin(): void {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('You do not have permission to view the Business Blueprint.', 'elev8-os')); }
        echo '<div class="wrap">' . self::content(true) . '</div>';
    }

    private static function content(bool $admin): string {
        $sections = Elev8_OS_Business_Blueprint_Service::sections();
        $summary = Elev8_OS_Business_Blueprint_Service::summary();
        $download = wp_nonce_url(admin_url('admin-post.php?action=elev8_os_download_blueprint'), 'elev8_os_download_blueprint');
        ob_start(); ?>
        <main class="elev8-blueprint<?php echo $admin ? ' is-admin' : ''; ?>">
            <header class="elev8-blueprint__hero">
                <div><p class="elev8-blueprint__eyebrow"><?php esc_html_e('Architecture is the product', 'elev8-os'); ?></p><h1><?php esc_html_e('Business Blueprint', 'elev8-os'); ?></h1><p><?php esc_html_e('The governing architecture, Business Graph, engine registry, decisions, roadmap, and institutional memory of Elev8 OS.', 'elev8-os'); ?></p></div>
                <a class="button button-primary" href="<?php echo esc_url($download); ?>"><?php esc_html_e('Download Blueprint', 'elev8-os'); ?></a>
            </header>
            <section class="elev8-blueprint__stats">
                <article><strong><?php echo esc_html((string)$summary['major_engines']); ?></strong><span><?php esc_html_e('Major engines', 'elev8-os'); ?></span></article>
                <article><strong><?php echo esc_html((string)$summary['supporting_engines']); ?></strong><span><?php esc_html_e('Supporting engines', 'elev8-os'); ?></span></article>
                <article><strong><?php echo esc_html((string)$summary['decisions']); ?></strong><span><?php esc_html_e('Architecture decisions', 'elev8-os'); ?></span></article>
                <article><strong><?php echo esc_html((string)$summary['open_questions']); ?></strong><span><?php esc_html_e('Open questions', 'elev8-os'); ?></span></article>
            </section>
            <nav class="elev8-blueprint__nav" aria-label="<?php esc_attr_e('Blueprint sections', 'elev8-os'); ?>">
                <?php foreach ($sections as $title => $body) : $anchor = sanitize_title($title); ?><a href="#<?php echo esc_attr($anchor); ?>"><?php echo esc_html($title); ?></a><?php endforeach; ?>
            </nav>
            <section class="elev8-blueprint__sections">
                <?php foreach ($sections as $title => $body) : $anchor = sanitize_title($title); ?>
                    <article id="<?php echo esc_attr($anchor); ?>" class="elev8-blueprint__section"><h2><?php echo esc_html($title); ?></h2><div><?php echo Elev8_OS_Business_Blueprint_Service::render_markdown($body); ?></div></article>
                <?php endforeach; ?>
            </section>
            <footer class="elev8-blueprint__footer"><strong><?php esc_html_e('Development protocol:', 'elev8-os'); ?></strong> <?php esc_html_e('Every session begins by reading this Blueprint and ends by updating it.', 'elev8-os'); ?></footer>
        </main>
        <?php return (string) ob_get_clean();
    }

    public static function command(array $commands, WP_User $user): array {
        if (user_can($user, 'manage_options')) { $commands[] = ['id'=>'business_blueprint','label'=>__('Business Blueprint','elev8-os'),'description'=>__('Open the governing architecture and Business Graph.','elev8-os'),'url'=>self::url(),'group'=>'architecture','icon'=>'🧭','type'=>'command']; }
        return $commands;
    }

    public static function download(): void {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('You do not have permission to download the Blueprint.', 'elev8-os')); }
        check_admin_referer('elev8_os_download_blueprint');
        $contents = Elev8_OS_Business_Blueprint_Service::contents();
        if ($contents === '') { wp_die(esc_html__('The Blueprint file is unavailable.', 'elev8-os')); }
        nocache_headers(); header('Content-Type: text/markdown; charset=utf-8'); header('Content-Disposition: attachment; filename="BUSINESS_BLUEPRINT.md"'); echo $contents; exit;
    }
}

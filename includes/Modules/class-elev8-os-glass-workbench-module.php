<?php
if (!defined('ABSPATH')) { exit; }

/** Frontend workbench home for the dedicated Elev8 Glassblower role. */
final class Elev8_OS_Glass_Workbench_Module {
    private const OPTION_PAGE_ID = 'elev8_os_glass_workbench_page_id';
    private const SLUG = 'glass-workbench';
    private const SHORTCODE = 'elev8_glass_workbench';

    public static function init(): void {
        add_shortcode(self::SHORTCODE, [__CLASS__, 'shortcode']);
        add_action('init', [__CLASS__, 'ensure_page'], 31);
        add_action('wp_enqueue_scripts', [__CLASS__, 'assets']);
    }

    public static function ensure_page(): void {
        $id = absint(get_option(self::OPTION_PAGE_ID));
        if ($id && get_post($id) instanceof WP_Post) { return; }
        $page = get_page_by_path(self::SLUG, OBJECT, 'page');
        if ($page instanceof WP_Post && $page->post_status !== 'trash') {
            update_option(self::OPTION_PAGE_ID, (int) $page->ID, false);
            return;
        }
        if (!current_user_can('manage_options')) { return; }
        $id = wp_insert_post([
            'post_title' => __('Glass Workbench', 'elev8-os'),
            'post_name' => self::SLUG,
            'post_content' => '[' . self::SHORTCODE . ']',
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_author' => get_current_user_id(),
            'comment_status' => 'closed',
        ], true);
        if (!is_wp_error($id) && $id > 0) { update_option(self::OPTION_PAGE_ID, (int) $id, false); }
    }

    public static function url(): string {
        $id = absint(get_option(self::OPTION_PAGE_ID));
        return $id ? get_permalink($id) : home_url('/' . self::SLUG . '/');
    }

    public static function is_current(): bool {
        return is_page(absint(get_option(self::OPTION_PAGE_ID))) || is_page(self::SLUG);
    }

    public static function assets(): void {
        if (!self::is_current()) { return; }
        wp_enqueue_style('elev8-os-artist-dashboard', ELEV8_OS_URL . 'assets/css/artist-dashboard.css', [], ELEV8_OS_VERSION);
        wp_enqueue_style('elev8-os-experience-engine', ELEV8_OS_URL . 'assets/css/experience-engine.css', [], ELEV8_OS_VERSION);
    }

    public static function shortcode(): string {
        if (!is_user_logged_in()) { return '<div class="elev8-experience-message">' . esc_html__('Please sign in to open your workbench.', 'elev8-os') . '</div>'; }
        $user = class_exists('Elev8_OS_Preview_Service') ? Elev8_OS_Preview_Service::effective_user() : wp_get_current_user();
        if (!$user instanceof WP_User || !Elev8_OS_Access_Service::user_can('glass_work', $user) || Elev8_OS_Access_Service::user_can('view_glass_dashboard', $user)) {
            return '<div class="elev8-experience-message">' . esc_html__('You do not have access to the Glass Workbench.', 'elev8-os') . '</div>';
        }
        ob_start();
        echo '<div class="elev8-workbench-shell"><div class="elev8-workbench-intro"><p class="elev8-eyebrow">' . esc_html__('Workbench Mode', 'elev8-os') . '</p><h1>' . esc_html__('My Glass Workbench', 'elev8-os') . '</h1><p>' . esc_html__('Your assigned production, tracked pay, and direct line to the Glass Manager in one phone-first workspace.', 'elev8-os') . '</p></div>';
        Elev8_OS_Glassblower_Dashboard_Module::render($user);
        echo '</div>';
        return (string) ob_get_clean();
    }
}

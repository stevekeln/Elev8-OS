<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Owns the WordPress pages used by the Artist Portal.
 *
 * Page IDs are the source of truth. Slugs are only used for discovery and
 * initial creation, so renaming a page does not break portal navigation.
 */
final class Elev8_OS_Portal_Page_Manager {

    private const ADMIN_PAGE_SLUG = 'elev8-portal-setup';

    /** @return array<string,array<string,string>> */
    public static function definitions(): array {
        return [
            'dashboard' => [
                'option' => 'elev8_os_artist_dashboard_page_id',
                'title' => __('Artist Dashboard', 'elev8-os'),
                'slug' => 'artist-dashboard',
                'shortcode' => 'elev8_artist_dashboard',
            ],
            'classes' => [
                'option' => 'elev8_os_artist_classes_page_id',
                'title' => __('My Classes', 'elev8-os'),
                'slug' => 'artist-classes',
                'shortcode' => 'elev8_artist_classes',
            ],
            'artwork' => [
                'option' => 'elev8_os_artist_artwork_page_id',
                'title' => __('My Artwork', 'elev8-os'),
                'slug' => 'artist-artwork',
                'shortcode' => 'elev8_artist_artwork',
            ],
            'students' => [
                'option' => 'elev8_os_artist_students_page_id',
                'title' => __('Students', 'elev8-os'),
                'slug' => 'artist-students',
                'shortcode' => 'elev8_artist_students',
            ],
            'waitlist' => [
                'option' => 'elev8_os_artist_waitlist_page_id',
                'title' => __('Waitlist', 'elev8-os'),
                'slug' => 'artist-waitlist',
                'shortcode' => 'elev8_artist_waitlist',
            ],
            'website' => [
                'option' => 'elev8_os_artist_website_page_id',
                'title' => __('My Website', 'elev8-os'),
                'slug' => 'artist-website',
                'shortcode' => 'elev8_artist_website',
            ],
            'edit_website' => [
                'option' => 'elev8_os_artist_edit_website_page_id',
                'title' => __('Edit Website', 'elev8-os'),
                'slug' => 'artist-edit-website',
                'shortcode' => 'elev8_artist_edit_website',
            ],
        ];
    }

    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'register_admin_menu'], 30);
        add_action('admin_init', [__CLASS__, 'ensure_pages_for_admin'], 20);
        add_action('admin_post_elev8_os_repair_portal_pages', [__CLASS__, 'handle_repair']);
    }

    public static function activate(): void {
        self::ensure_all(true);
    }

    public static function ensure_pages_for_admin(): void {
        if (!current_user_can('manage_options')) {
            return;
        }
        self::ensure_all(true);
    }

    public static function ensure_all(bool $allow_create = true): void {
        foreach (array_keys(self::definitions()) as $key) {
            self::ensure_page($key, $allow_create);
        }
    }

    public static function get_page_id(string $key): int {
        $definition = self::definition($key);
        if ($definition === null) {
            return 0;
        }

        $page_id = absint(get_option($definition['option']));
        if (self::is_valid_page($page_id)) {
            return $page_id;
        }

        return self::ensure_page($key, false);
    }

    public static function get_url(string $key): string {
        $page_id = self::get_page_id($key);
        if ($page_id > 0) {
            $permalink = get_permalink($page_id);
            if (is_string($permalink) && $permalink !== '') {
                return $permalink;
            }
        }

        $definition = self::definition($key);
        return $definition === null ? home_url('/') : home_url('/' . $definition['slug'] . '/');
    }

    public static function is_current_page(string $key): bool {
        $page_id = self::get_page_id($key);
        if ($page_id > 0 && is_page($page_id)) {
            return true;
        }

        $definition = self::definition($key);
        return $definition !== null && is_page($definition['slug']);
    }

    public static function ensure_page(string $key, bool $allow_create = true): int {
        $definition = self::definition($key);
        if ($definition === null) {
            return 0;
        }

        $stored_id = absint(get_option($definition['option']));
        if (self::is_valid_page($stored_id)) {
            self::ensure_shortcode($stored_id, $definition['shortcode']);
            return $stored_id;
        }

        $page = get_page_by_path($definition['slug'], OBJECT, 'page');
        if ($page instanceof WP_Post && $page->post_status !== 'trash') {
            if ($page->post_status !== 'publish') {
                wp_update_post(['ID' => (int) $page->ID, 'post_status' => 'publish']);
            }
            self::ensure_shortcode((int) $page->ID, $definition['shortcode']);
            update_option($definition['option'], (int) $page->ID, false);
            return (int) $page->ID;
        }

        $shortcode_page = self::find_page_by_shortcode($definition['shortcode']);
        if ($shortcode_page instanceof WP_Post) {
            update_option($definition['option'], (int) $shortcode_page->ID, false);
            return (int) $shortcode_page->ID;
        }

        if (!$allow_create || !current_user_can('manage_options')) {
            delete_option($definition['option']);
            return 0;
        }

        $page_id = wp_insert_post([
            'post_title' => $definition['title'],
            'post_name' => $definition['slug'],
            'post_content' => '[' . $definition['shortcode'] . ']',
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_author' => get_current_user_id(),
            'comment_status' => 'closed',
        ], true);

        if (is_wp_error($page_id) || $page_id <= 0) {
            return 0;
        }

        update_option($definition['option'], (int) $page_id, false);
        return (int) $page_id;
    }

    public static function register_admin_menu(): void {
        add_submenu_page(
            'elev8-os',
            __('Portal Setup', 'elev8-os'),
            __('Portal Setup', 'elev8-os'),
            'manage_options',
            self::ADMIN_PAGE_SLUG,
            [__CLASS__, 'render_admin_page']
        );
    }

    public static function render_admin_page(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to view this page.', 'elev8-os'));
        }

        $definitions = self::definitions();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Artist Portal Setup', 'elev8-os'); ?></h1>
            <p><?php esc_html_e('Elev8 OS stores the exact WordPress page ID for each portal screen. Renaming a page or changing its permalink will not break navigation.', 'elev8-os'); ?></p>

            <?php if (isset($_GET['elev8_repaired']) && $_GET['elev8_repaired'] === '1') : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Portal pages were checked and repaired.', 'elev8-os'); ?></p></div>
            <?php endif; ?>

            <table class="widefat striped" style="max-width:1000px;margin-top:20px">
                <thead><tr><th><?php esc_html_e('Portal page', 'elev8-os'); ?></th><th><?php esc_html_e('Status', 'elev8-os'); ?></th><th><?php esc_html_e('Page ID', 'elev8-os'); ?></th><th><?php esc_html_e('URL', 'elev8-os'); ?></th><th><?php esc_html_e('Shortcode', 'elev8-os'); ?></th></tr></thead>
                <tbody>
                <?php foreach ($definitions as $key => $definition) : ?>
                    <?php $page_id = self::get_page_id($key); $valid = self::is_valid_page($page_id); ?>
                    <tr>
                        <td><strong><?php echo esc_html($definition['title']); ?></strong></td>
                        <td><?php echo $valid ? '<span style="color:#008a20">&#10003; ' . esc_html__('Ready', 'elev8-os') . '</span>' : '<span style="color:#b32d2e">&#9888; ' . esc_html__('Missing', 'elev8-os') . '</span>'; ?></td>
                        <td><?php echo $valid ? esc_html((string) $page_id) : '&mdash;'; ?></td>
                        <td><?php if ($valid) : ?><a href="<?php echo esc_url(get_permalink($page_id)); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html(get_permalink($page_id)); ?></a><?php else : ?>&mdash;<?php endif; ?></td>
                        <td><code>[<?php echo esc_html($definition['shortcode']); ?>]</code></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:20px">
                <input type="hidden" name="action" value="elev8_os_repair_portal_pages">
                <?php wp_nonce_field('elev8_os_repair_portal_pages'); ?>
                <?php submit_button(__('Check and Repair Portal Pages', 'elev8-os'), 'primary', 'submit', false); ?>
            </form>
        </div>
        <?php
    }

    public static function handle_repair(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to do this.', 'elev8-os'));
        }
        check_admin_referer('elev8_os_repair_portal_pages');
        self::ensure_all(true);
        wp_safe_redirect(add_query_arg(['page' => self::ADMIN_PAGE_SLUG, 'elev8_repaired' => '1'], admin_url('admin.php')));
        exit;
    }

    /** @return array<string,string>|null */
    private static function definition(string $key): ?array {
        $definitions = self::definitions();
        return isset($definitions[$key]) ? $definitions[$key] : null;
    }

    private static function is_valid_page(int $page_id): bool {
        if ($page_id <= 0) {
            return false;
        }
        $post = get_post($page_id);
        return $post instanceof WP_Post && $post->post_type === 'page' && in_array($post->post_status, ['publish', 'private'], true);
    }

    private static function ensure_shortcode(int $page_id, string $shortcode): void {
        $post = get_post($page_id);
        if (!($post instanceof WP_Post) || $post->post_type !== 'page') {
            return;
        }
        if (has_shortcode((string) $post->post_content, $shortcode)) {
            return;
        }
        wp_update_post([
            'ID' => $page_id,
            'post_content' => '[' . $shortcode . ']',
        ]);
    }

    private static function find_page_by_shortcode(string $shortcode): ?WP_Post {
        $pages = get_posts([
            'post_type' => 'page',
            'post_status' => ['publish', 'private'],
            'posts_per_page' => 100,
            'orderby' => 'ID',
            'order' => 'ASC',
            's' => '[' . $shortcode,
        ]);

        foreach ($pages as $page) {
            if ($page instanceof WP_Post && has_shortcode((string) $page->post_content, $shortcode)) {
                return $page;
            }
        }
        return null;
    }
}

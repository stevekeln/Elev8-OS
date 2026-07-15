<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Reusable Artist Portal navigation and link data.
 *
 * Links are saved on the WordPress user so Elev8 OS owns the relationship.
 * Amelia remains one integration rather than the source of portal navigation.
 */
final class Elev8_OS_Artist_Portal_Module {

    private const META_PUBLIC_PAGE = 'elev8_os_public_artist_page_url';
    private const META_EDIT_PAGE = 'elev8_os_edit_artist_page_url';
    private const META_CLASSES = 'elev8_os_artist_classes_url';
    private const META_BOOKING = 'elev8_os_artist_booking_url';
    private const WEBSITE_PAGE_OPTION = 'elev8_os_artist_website_page_id';
    private const WEBSITE_PAGE_SLUG = 'artist-website';
    private const WEBSITE_SHORTCODE = 'elev8_artist_website';
    private const EMPLOYEE_META_KEY = 'elev8_os_amelia_employee_id';

    public static function init(): void {
        add_action('init', [__CLASS__, 'register_website_shortcode']);
        add_action('init', [__CLASS__, 'ensure_website_page'], 31);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_assets']);

        add_action('show_user_profile', [__CLASS__, 'render_profile_fields']);
        add_action('edit_user_profile', [__CLASS__, 'render_profile_fields']);
        add_action('personal_options_update', [__CLASS__, 'save_profile_fields']);
        add_action('edit_user_profile_update', [__CLASS__, 'save_profile_fields']);
    }

    public static function status(): string {
        return 'active';
    }

    public static function enqueue_assets(): void {
        if (!is_user_logged_in()) {
            return;
        }

        if (!is_page('artist-dashboard') && !self::is_website_page()) {
            return;
        }

        wp_enqueue_style(
            'elev8-os-artist-portal',
            ELEV8_OS_URL . 'assets/css/artist-portal.css',
            [],
            ELEV8_OS_VERSION
        );
    }

    public static function enqueue_admin_assets(string $hook): void {
        if ($hook !== 'elev8-os_page_elev8-artist-dashboard') {
            return;
        }

        wp_enqueue_style(
            'elev8-os-artist-portal',
            ELEV8_OS_URL . 'assets/css/artist-portal.css',
            [],
            ELEV8_OS_VERSION
        );
    }


    public static function register_website_shortcode(): void {
        add_shortcode(self::WEBSITE_SHORTCODE, [__CLASS__, 'website_shortcode']);
    }

    public static function ensure_website_page(): void {
        $page_id = (int) get_option(self::WEBSITE_PAGE_OPTION);
        if ($page_id > 0 && get_post_status($page_id)) {
            return;
        }

        $existing = get_page_by_path(self::WEBSITE_PAGE_SLUG);
        if ($existing instanceof WP_Post) {
            update_option(self::WEBSITE_PAGE_OPTION, (int) $existing->ID, false);
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        $page_id = wp_insert_post([
            'post_title' => __('My Website', 'elev8-os'),
            'post_name' => self::WEBSITE_PAGE_SLUG,
            'post_content' => '[' . self::WEBSITE_SHORTCODE . ']',
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_author' => get_current_user_id(),
        ], true);

        if (!is_wp_error($page_id) && $page_id > 0) {
            update_option(self::WEBSITE_PAGE_OPTION, (int) $page_id, false);
        }
    }

    public static function website_shortcode(): string {
        if (!is_user_logged_in()) {
            return sprintf(
                '<div class="elev8-dashboard-login"><p>%1$s</p><p><a class="button" href="%2$s">%3$s</a></p></div>',
                esc_html__('Please log in to view your artist website.', 'elev8-os'),
                esc_url(wp_login_url(self::website_url())),
                esc_html__('Log In', 'elev8-os')
            );
        }

        $user = wp_get_current_user();
        $artist = self::find_artist_for_user($user);
        ob_start();
        ?>
        <div class="elev8-artist-dashboard elev8-artist-website">
            <?php self::render_navigation('website'); ?>

            <header class="elev8-dashboard-header elev8-website-header">
                <div>
                    <p class="elev8-eyebrow"><?php esc_html_e('Artist Portal', 'elev8-os'); ?></p>
                    <h1><?php esc_html_e('My Website', 'elev8-os'); ?></h1>
                    <p><?php esc_html_e('This is how customers see your public artist profile.', 'elev8-os'); ?></p>
                </div>
                <span class="elev8-dashboard-badge"><?php esc_html_e('Public preview', 'elev8-os'); ?></span>
            </header>

            <?php if (!$artist) : ?>
                <div class="elev8-dashboard-warning">
                    <p><strong><?php esc_html_e('Your account is not connected to an Amelia artist.', 'elev8-os'); ?></strong><br><?php esc_html_e('Ask an administrator to map this WordPress account to the correct Amelia employee.', 'elev8-os'); ?></p>
                </div>
            <?php else : ?>
                <?php
                $slug = self::artist_slug($artist);
                $public_url = home_url('/artists/' . $slug . '/');
                $edit_url = self::edit_profile_url($user);
                ?>
                <section class="elev8-website-toolbar" aria-label="<?php esc_attr_e('Website actions', 'elev8-os'); ?>">
                    <div>
                        <strong><?php esc_html_e('Your public page', 'elev8-os'); ?></strong>
                        <span><?php echo esc_html($public_url); ?></span>
                    </div>
                    <div class="elev8-website-actions">
                        <button type="button" class="elev8-copy-public-link" data-link="<?php echo esc_attr($public_url); ?>"><?php esc_html_e('Copy Link', 'elev8-os'); ?></button>
                        <a class="elev8-secondary-button" href="<?php echo esc_url($public_url); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Open Public Page', 'elev8-os'); ?></a>
                        <a class="elev8-primary-button" href="<?php echo esc_url($edit_url); ?>"><?php esc_html_e('Edit My Profile', 'elev8-os'); ?></a>
                    </div>
                </section>

                <section class="elev8-website-preview" aria-labelledby="elev8-preview-title">
                    <div class="elev8-panel-heading">
                        <div>
                            <p class="elev8-eyebrow"><?php esc_html_e('Live preview', 'elev8-os'); ?></p>
                            <h2 id="elev8-preview-title"><?php esc_html_e('What customers see', 'elev8-os'); ?></h2>
                        </div>
                    </div>
                    <div class="elev8-public-preview-frame">
                        <?php echo do_shortcode('[elev8_artist_profile artist="' . esc_attr($slug) . '"]'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                </section>

                <script>
                (function(){
                    var button=document.querySelector('.elev8-copy-public-link');
                    if(!button){return;}
                    button.addEventListener('click',function(){
                        var link=this.getAttribute('data-link');
                        if(navigator.clipboard){
                            navigator.clipboard.writeText(link).then(function(){button.textContent='<?php echo esc_js(__('Copied!', 'elev8-os')); ?>';setTimeout(function(){button.textContent='<?php echo esc_js(__('Copy Link', 'elev8-os')); ?>';},1500);});
                        }else{window.prompt('<?php echo esc_js(__('Copy this link:', 'elev8-os')); ?>',link);}
                    });
                }());
                </script>
            <?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private static function website_url(): string {
        $page_id = (int) get_option(self::WEBSITE_PAGE_OPTION);
        if ($page_id > 0 && get_post_status($page_id)) {
            return (string) get_permalink($page_id);
        }
        return home_url('/' . self::WEBSITE_PAGE_SLUG . '/');
    }

    private static function is_website_page(): bool {
        $page_id = (int) get_option(self::WEBSITE_PAGE_OPTION);
        return ($page_id > 0 && is_page($page_id)) || is_page(self::WEBSITE_PAGE_SLUG);
    }

    private static function edit_profile_url(WP_User $user): string {
        $saved = esc_url_raw((string) get_user_meta($user->ID, self::META_EDIT_PAGE, true));
        if ($saved !== '') {
            return $saved;
        }
        return get_edit_user_link($user->ID) ?: admin_url('profile.php');
    }

    /** @return array<string,mixed>|null */
    private static function find_artist_for_user(WP_User $user): ?array {
        global $wpdb;
        $table = $wpdb->prefix . 'amelia_users';
        if (!self::table_exists($table)) {
            return null;
        }
        $columns = self::table_columns($table);
        if (!in_array('id', $columns, true)) {
            return null;
        }
        $select = ['id'];
        foreach (['firstName', 'lastName', 'email'] as $column) {
            if (in_array($column, $columns, true)) {
                $select[] = $column;
            }
        }
        $select_sql = implode(', ', array_map(static function(string $column): string { return "`{$column}`"; }, $select));
        $type_sql = in_array('type', $columns, true) ? " AND LOWER(COALESCE(`type`,'')) IN ('provider','employee')" : '';
        $mapped_id = max(0, (int) get_user_meta($user->ID, self::EMPLOYEE_META_KEY, true));
        if ($mapped_id > 0) {
            $row = $wpdb->get_row($wpdb->prepare("SELECT {$select_sql} FROM `{$table}` WHERE `id` = %d{$type_sql} LIMIT 1", $mapped_id), ARRAY_A);
            if (is_array($row)) { return $row; }
        }
        $email = sanitize_email((string) $user->user_email);
        if ($email === '' || !in_array('email', $columns, true)) { return null; }
        $row = $wpdb->get_row($wpdb->prepare("SELECT {$select_sql} FROM `{$table}` WHERE LOWER(`email`) = LOWER(%s){$type_sql} LIMIT 1", $email), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    /** @param array<string,mixed> $artist */
    private static function artist_slug(array $artist): string {
        $name = trim((string) ($artist['firstName'] ?? '') . ' ' . (string) ($artist['lastName'] ?? ''));
        if ($name === '') {
            $name = 'artist-' . absint($artist['id'] ?? 0);
        }
        return sanitize_title($name);
    }

    /** @return string[] */
    private static function table_columns(string $table): array {
        global $wpdb;
        $columns = $wpdb->get_col("DESCRIBE `{$table}`", 0);
        return is_array($columns) ? array_map('strval', $columns) : [];
    }

    private static function table_exists(string $table): bool {
        global $wpdb;
        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        return $found === $table;
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public static function navigation_items(?WP_User $user = null): array {
        $user = $user ?: wp_get_current_user();

        $dashboard_url = home_url('/artist-dashboard/');
        $website_url = self::website_url();
        $public_url = esc_url_raw((string) get_user_meta($user->ID, self::META_PUBLIC_PAGE, true));
        $edit_url = esc_url_raw((string) get_user_meta($user->ID, self::META_EDIT_PAGE, true));
        $classes_url = esc_url_raw((string) get_user_meta($user->ID, self::META_CLASSES, true));
        $booking_url = esc_url_raw((string) get_user_meta($user->ID, self::META_BOOKING, true));

        return [
            'dashboard' => [
                'label' => __('Dashboard', 'elev8-os'),
                'icon' => 'dashboard',
                'url' => $dashboard_url,
                'enabled' => true,
            ],
            'classes' => [
                'label' => __('My Classes', 'elev8-os'),
                'icon' => 'calendar-alt',
                'url' => $classes_url,
                'enabled' => $classes_url !== '',
            ],
            'website' => [
                'label' => __('My Website', 'elev8-os'),
                'icon' => 'admin-site-alt3',
                'url' => $website_url,
                'enabled' => true,
            ],
            'edit_website' => [
                'label' => __('Edit Website', 'elev8-os'),
                'icon' => 'edit-page',
                'url' => $edit_url,
                'enabled' => $edit_url !== '',
            ],
            'booking' => [
                'label' => __('Booking Link', 'elev8-os'),
                'icon' => 'admin-links',
                'url' => $booking_url,
                'enabled' => $booking_url !== '',
                'new_tab' => true,
            ],
            'earnings' => [
                'label' => __('Earnings', 'elev8-os'),
                'icon' => 'money-alt',
                'url' => '',
                'enabled' => false,
            ],
            'students' => [
                'label' => __('Students', 'elev8-os'),
                'icon' => 'groups',
                'url' => '',
                'enabled' => false,
            ],
            'referrals' => [
                'label' => __('Referrals', 'elev8-os'),
                'icon' => 'megaphone',
                'url' => '',
                'enabled' => false,
            ],
            'tax_documents' => [
                'label' => __('Tax Documents', 'elev8-os'),
                'icon' => 'media-document',
                'url' => '',
                'enabled' => false,
            ],
            'settings' => [
                'label' => __('Settings', 'elev8-os'),
                'icon' => 'admin-generic',
                'url' => '',
                'enabled' => false,
            ],
        ];
    }

    public static function render_navigation(string $active = 'dashboard'): void {
        $items = self::navigation_items();
        ?>
        <nav class="elev8-portal-nav" aria-label="<?php esc_attr_e('Artist Portal', 'elev8-os'); ?>">
            <div class="elev8-portal-brand">
                <span class="elev8-portal-brand-mark">E8</span>
                <span>
                    <strong><?php esc_html_e('Elev8 OS', 'elev8-os'); ?></strong>
                    <small><?php esc_html_e('Artist Portal', 'elev8-os'); ?></small>
                </span>
            </div>

            <div class="elev8-portal-links">
                <?php foreach ($items as $key => $item) : ?>
                    <?php
                    $classes = ['elev8-portal-link'];
                    if ($key === $active) {
                        $classes[] = 'is-active';
                    }
                    if (!$item['enabled']) {
                        $classes[] = 'is-disabled';
                    }
                    ?>
                    <?php if ($item['enabled']) : ?>
                        <a
                            class="<?php echo esc_attr(implode(' ', $classes)); ?>"
                            href="<?php echo esc_url($item['url']); ?>"
                            <?php echo !empty($item['new_tab']) ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>
                        >
                            <span class="dashicons dashicons-<?php echo esc_attr($item['icon']); ?>" aria-hidden="true"></span>
                            <span><?php echo esc_html($item['label']); ?></span>
                        </a>
                    <?php else : ?>
                        <span class="<?php echo esc_attr(implode(' ', $classes)); ?>" aria-disabled="true">
                            <span class="dashicons dashicons-<?php echo esc_attr($item['icon']); ?>" aria-hidden="true"></span>
                            <span><?php echo esc_html($item['label']); ?></span>
                            <small><?php esc_html_e('Coming soon', 'elev8-os'); ?></small>
                        </span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </nav>
        <?php
    }

    public static function render_profile_fields(WP_User $user): void {
        if (!current_user_can('edit_user', $user->ID)) {
            return;
        }

        wp_nonce_field('elev8_os_artist_portal_links', 'elev8_os_artist_portal_nonce');
        ?>
        <h2><?php esc_html_e('Elev8 OS Artist Portal Links', 'elev8-os'); ?></h2>
        <p><?php esc_html_e('These links control the working buttons shown in this artist’s portal.', 'elev8-os'); ?></p>

        <table class="form-table" role="presentation">
            <?php
            self::render_url_field($user, self::META_PUBLIC_PAGE, __('Public artist page', 'elev8-os'));
            self::render_url_field($user, self::META_EDIT_PAGE, __('Edit artist page', 'elev8-os'));
            self::render_url_field($user, self::META_CLASSES, __('My Classes page', 'elev8-os'));
            self::render_url_field($user, self::META_BOOKING, __('Booking link', 'elev8-os'));
            ?>
        </table>
        <?php
    }

    public static function save_profile_fields(int $user_id): void {
        if (!current_user_can('edit_user', $user_id)) {
            return;
        }

        if (
            empty($_POST['elev8_os_artist_portal_nonce']) ||
            !wp_verify_nonce(
                sanitize_text_field(wp_unslash($_POST['elev8_os_artist_portal_nonce'])),
                'elev8_os_artist_portal_links'
            )
        ) {
            return;
        }

        foreach ([
            self::META_PUBLIC_PAGE,
            self::META_EDIT_PAGE,
            self::META_CLASSES,
            self::META_BOOKING,
        ] as $meta_key) {
            $value = isset($_POST[$meta_key])
                ? esc_url_raw(wp_unslash($_POST[$meta_key]))
                : '';

            if ($value === '') {
                delete_user_meta($user_id, $meta_key);
            } else {
                update_user_meta($user_id, $meta_key, $value);
            }
        }
    }

    private static function render_url_field(WP_User $user, string $meta_key, string $label): void {
        $value = (string) get_user_meta($user->ID, $meta_key, true);
        ?>
        <tr>
            <th><label for="<?php echo esc_attr($meta_key); ?>"><?php echo esc_html($label); ?></label></th>
            <td>
                <input
                    type="url"
                    class="regular-text"
                    id="<?php echo esc_attr($meta_key); ?>"
                    name="<?php echo esc_attr($meta_key); ?>"
                    value="<?php echo esc_attr($value); ?>"
                    placeholder="https://"
                >
            </td>
        </tr>
        <?php
    }
}

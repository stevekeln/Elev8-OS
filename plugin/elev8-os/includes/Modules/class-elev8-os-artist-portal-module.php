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

    public static function init(): void {
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

        if (!is_page('artist-dashboard')) {
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

    /**
     * @return array<string,array<string,mixed>>
     */
    public static function navigation_items(?WP_User $user = null): array {
        $user = $user ?: wp_get_current_user();

        $dashboard_url = home_url('/artist-dashboard/');
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
                'url' => $public_url,
                'enabled' => $public_url !== '',
                'new_tab' => true,
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

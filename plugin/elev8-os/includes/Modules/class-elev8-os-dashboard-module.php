<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Logged-in Artist Dashboard.
 *
 * This module intentionally owns the artist-facing dashboard so the legacy
 * compatibility class does not need to grow further.
 */
final class Elev8_OS_Dashboard_Module {

    private const PAGE_SLUG = 'elev8-artist-dashboard';

    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'register_menu'], 20);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_filter('login_redirect', [__CLASS__, 'login_redirect'], 20, 3);
    }

    public static function status(): string {
        return 'active';
    }

    public static function register_menu(): void {
        add_submenu_page(
            'elev8-os',
            __('Artist Dashboard', 'elev8-os'),
            __('My Dashboard', 'elev8-os'),
            'read',
            self::PAGE_SLUG,
            [__CLASS__, 'render']
        );

        // Artists may not have access to the administrator-only parent menu.
        // This creates a separate top-level entry for any logged-in artist.
        if (!current_user_can('manage_options')) {
            add_menu_page(
                __('Artist Dashboard', 'elev8-os'),
                __('Elev8 OS', 'elev8-os'),
                'read',
                self::PAGE_SLUG,
                [__CLASS__, 'render'],
                'dashicons-art',
                3
            );
        }
    }

    public static function enqueue_assets(string $hook): void {
        if (!in_array($hook, [
            'elev8-os_page_' . self::PAGE_SLUG,
            'toplevel_page_' . self::PAGE_SLUG,
        ], true)) {
            return;
        }

        wp_enqueue_style(
            'elev8-os-artist-dashboard',
            ELEV8_OS_URL . 'assets/css/artist-dashboard.css',
            [],
            ELEV8_OS_VERSION
        );
    }

    public static function login_redirect(string $redirect_to, string $requested_redirect_to, $user): string {
        if (!($user instanceof WP_User) || is_wp_error($user)) {
            return $redirect_to;
        }

        if ($user->has_cap('manage_options')) {
            return $redirect_to;
        }

        $artist = self::find_artist_for_user($user);
        if (!$artist) {
            return $redirect_to;
        }

        return admin_url('admin.php?page=' . self::PAGE_SLUG);
    }

    public static function render(): void {
        if (!current_user_can('read')) {
            wp_die(esc_html__('You do not have permission to view this page.', 'elev8-os'));
        }

        $user = wp_get_current_user();
        $artist = self::find_artist_for_user($user);
        $first_name = $artist
            ? trim((string) ($artist['firstName'] ?? ''))
            : trim((string) $user->first_name);

        if ($first_name === '') {
            $first_name = $user->display_name ?: __('Artist', 'elev8-os');
        }

        $upcoming_count = $artist
            ? self::get_upcoming_class_count((int) $artist['id'])
            : 0;
        ?>
        <div class="wrap elev8-artist-dashboard">
            <header class="elev8-dashboard-header">
                <div>
                    <p class="elev8-eyebrow"><?php esc_html_e('Elev8 OS', 'elev8-os'); ?></p>
                    <h1>
                        <?php
                        echo esc_html(
                            sprintf(
                                /* translators: %s: artist first name */
                                __('Welcome back, %s!', 'elev8-os'),
                                $first_name
                            )
                        );
                        ?>
                    </h1>
                    <p><?php esc_html_e('Your creative business at a glance.', 'elev8-os'); ?></p>
                </div>
                <span class="elev8-dashboard-badge"><?php esc_html_e('Founders Edition', 'elev8-os'); ?></span>
            </header>

            <?php if (!$artist) : ?>
                <div class="notice notice-warning inline elev8-link-warning">
                    <p>
                        <strong><?php esc_html_e('Your Elev8 OS account is not connected to an Amelia artist record yet.', 'elev8-os'); ?></strong><br>
                        <?php
                        echo esc_html(
                            sprintf(
                                /* translators: %s: account email address */
                                __('Ask an administrator to match your WordPress email (%s) to your Amelia artist email.', 'elev8-os'),
                                $user->user_email
                            )
                        );
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <section class="elev8-welcome-card">
                <div>
                    <span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>
                    <div>
                        <p class="elev8-card-label"><?php esc_html_e('Upcoming Classes', 'elev8-os'); ?></p>
                        <strong><?php echo esc_html(number_format_i18n($upcoming_count)); ?></strong>
                        <p>
                            <?php
                            echo esc_html(
                                _n(
                                    'class currently scheduled',
                                    'classes currently scheduled',
                                    $upcoming_count,
                                    'elev8-os'
                                )
                            );
                            ?>
                        </p>
                    </div>
                </div>
            </section>

            <section class="elev8-dashboard-grid" aria-label="<?php esc_attr_e('Dashboard modules', 'elev8-os'); ?>">
                <?php self::render_placeholder_card('calendar-alt', __('Upcoming Classes', 'elev8-os'), __('Your next classes and enrollment will appear here.', 'elev8-os')); ?>
                <?php self::render_placeholder_card('chart-bar', __('My Statistics', 'elev8-os'), __('Classes, students, revenue, and average class size.', 'elev8-os')); ?>
                <?php self::render_placeholder_card('money-alt', __('Earnings', 'elev8-os'), __('Estimated and pending payouts will appear here.', 'elev8-os')); ?>
                <?php self::render_placeholder_card('admin-links', __('Quick Actions', 'elev8-os'), __('Profile, schedule, referrals, and tax documents.', 'elev8-os')); ?>
                <?php self::render_placeholder_card('bell', __('Notifications', 'elev8-os'), __('Important updates and items needing attention.', 'elev8-os')); ?>
            </section>
        </div>
        <?php
    }

    private static function render_placeholder_card(string $icon, string $title, string $description): void {
        ?>
        <article class="elev8-dashboard-card">
            <span class="dashicons dashicons-<?php echo esc_attr($icon); ?>" aria-hidden="true"></span>
            <div>
                <h2><?php echo esc_html($title); ?></h2>
                <p><?php echo esc_html($description); ?></p>
                <span class="elev8-coming-soon"><?php esc_html_e('Coming next', 'elev8-os'); ?></span>
            </div>
        </article>
        <?php
    }

    /**
     * Match the current WordPress user to Amelia by email.
     *
     * @return array<string,mixed>|null
     */
    private static function find_artist_for_user(WP_User $user): ?array {
        global $wpdb;

        $email = sanitize_email((string) $user->user_email);
        if ($email === '') {
            return null;
        }

        $table = $wpdb->prefix . 'amelia_users';
        if (!self::table_exists($table)) {
            return null;
        }

        $columns = $wpdb->get_col("DESCRIBE `{$table}`", 0);
        if (!in_array('email', $columns, true)) {
            return null;
        }

        $type_sql = in_array('type', $columns, true)
            ? " AND (`type` = 'provider' OR `type` = 'employee')"
            : '';

        $artist = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT `id`, `firstName`, `lastName`, `email`
                 FROM `{$table}`
                 WHERE LOWER(`email`) = LOWER(%s) {$type_sql}
                 LIMIT 1",
                $email
            ),
            ARRAY_A
        );

        return is_array($artist) ? $artist : null;
    }

    private static function get_upcoming_class_count(int $artist_id): int {
        global $wpdb;

        if ($artist_id < 1) {
            return 0;
        }

        $appointments = $wpdb->prefix . 'amelia_appointments';
        if (!self::table_exists($appointments)) {
            return 0;
        }

        $columns = $wpdb->get_col("DESCRIBE `{$appointments}`", 0);
        if (!in_array('providerId', $columns, true) || !in_array('bookingStart', $columns, true)) {
            return 0;
        }

        $status_sql = '';
        if (in_array('status', $columns, true)) {
            $status_sql = " AND LOWER(COALESCE(`status`, '')) NOT IN ('canceled', 'cancelled', 'rejected')";
        }

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT `id`)
                 FROM `{$appointments}`
                 WHERE `providerId` = %d
                   AND `bookingStart` >= %s
                   {$status_sql}",
                $artist_id,
                current_time('mysql')
            )
        );

        return max(0, (int) $count);
    }

    private static function table_exists(string $table): bool {
        global $wpdb;

        $found = $wpdb->get_var(
            $wpdb->prepare('SHOW TABLES LIKE %s', $table)
        );

        return $found === $table;
    }
}

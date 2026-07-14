<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Logged-in Artist Dashboard.
 *
 * The artist experience lives on the public-facing website rather than inside
 * wp-admin because Amelia Employee accounts may be redirected away from the
 * WordPress administration area.
 */
final class Elev8_OS_Dashboard_Module {

    private const ADMIN_PAGE_SLUG = 'elev8-artist-dashboard';
    private const FRONTEND_PAGE_SLUG = 'artist-dashboard';
    private const FRONTEND_SHORTCODE = 'elev8_artist_dashboard';
    private const PAGE_OPTION = 'elev8_os_artist_dashboard_page_id';

    public static function init(): void {
        add_action('init', [__CLASS__, 'register_shortcode']);
        add_action('init', [__CLASS__, 'ensure_frontend_page'], 30);
        add_action('admin_menu', [__CLASS__, 'register_admin_menu'], 20);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_frontend_assets']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_assets']);
        add_filter('login_redirect', [__CLASS__, 'login_redirect'], 999, 3);
    }

    public static function status(): string {
        return 'active';
    }

    public static function register_shortcode(): void {
        add_shortcode(self::FRONTEND_SHORTCODE, [__CLASS__, 'shortcode']);
    }

    public static function ensure_frontend_page(): void {
        $page_id = (int) get_option(self::PAGE_OPTION);

        if ($page_id > 0 && get_post_status($page_id)) {
            return;
        }

        $existing = get_page_by_path(self::FRONTEND_PAGE_SLUG);
        if ($existing instanceof WP_Post) {
            update_option(self::PAGE_OPTION, (int) $existing->ID, false);
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        $page_id = wp_insert_post(
            [
                'post_title'   => __('Artist Dashboard', 'elev8-os'),
                'post_name'    => self::FRONTEND_PAGE_SLUG,
                'post_content' => '[' . self::FRONTEND_SHORTCODE . ']',
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_author'  => get_current_user_id(),
            ],
            true
        );

        if (!is_wp_error($page_id) && $page_id > 0) {
            update_option(self::PAGE_OPTION, (int) $page_id, false);
        }
    }

    public static function register_admin_menu(): void {
        add_submenu_page(
            'elev8-os',
            __('Artist Dashboard', 'elev8-os'),
            __('Artist Dashboard', 'elev8-os'),
            'manage_options',
            self::ADMIN_PAGE_SLUG,
            [__CLASS__, 'render_admin_preview']
        );
    }

    public static function enqueue_frontend_assets(): void {
        if (!self::is_dashboard_page()) {
            return;
        }

        wp_enqueue_style('dashicons');
        wp_enqueue_style(
            'elev8-os-artist-dashboard',
            ELEV8_OS_URL . 'assets/css/artist-dashboard.css',
            [],
            ELEV8_OS_VERSION
        );
    }

    public static function enqueue_admin_assets(string $hook): void {
        if ($hook !== 'elev8-os_page_' . self::ADMIN_PAGE_SLUG) {
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

        if (!self::is_artist_user($user)) {
            return $redirect_to;
        }

        return self::dashboard_url();
    }

    public static function shortcode(): string {
        if (!is_user_logged_in()) {
            $login_url = wp_login_url(self::dashboard_url());

            return sprintf(
                '<div class="elev8-dashboard-login"><p>%1$s</p><p><a class="button" href="%2$s">%3$s</a></p></div>',
                esc_html__('Please log in to view your Elev8 OS dashboard.', 'elev8-os'),
                esc_url($login_url),
                esc_html__('Log In', 'elev8-os')
            );
        }

        ob_start();
        self::render_dashboard();
        return (string) ob_get_clean();
    }

    public static function render_admin_preview(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to view this page.', 'elev8-os'));
        }

        echo '<div class="wrap">';
        self::render_dashboard();
        echo '</div>';
    }

    private static function render_dashboard(): void {
        $user = wp_get_current_user();
        $artist = Elev8_OS_Amelia::find_provider_for_user($user);
        $first_name = $artist
            ? trim((string) ($artist['firstName'] ?? ''))
            : trim((string) $user->first_name);

        if ($first_name === '') {
            $first_name = $user->display_name ?: __('Artist', 'elev8-os');
        }

        $artist_id = $artist ? (int) ($artist['id'] ?? 0) : 0;
        $data = $artist_id > 0
            ? Elev8_OS_Amelia::provider_dashboard($artist_id)
            : [
                'available' => false,
                'upcoming_classes' => 0,
                'booked_students' => 0,
                'pending_bookings' => 0,
                'cancelled_bookings' => 0,
                'average_class_size' => 0.0,
                'appointments' => [],
                'diagnostics' => [],
            ];
        ?>
        <div class="elev8-artist-dashboard">
            <?php Elev8_OS_Artist_Portal_Module::render_navigation('dashboard'); ?>
            <header class="elev8-dashboard-header">
                <div>
                    <p class="elev8-eyebrow"><?php esc_html_e('Elev8 OS', 'elev8-os'); ?></p>
                    <h1><?php echo esc_html(sprintf(__('Welcome back, %s!', 'elev8-os'), $first_name)); ?></h1>
                    <p><?php esc_html_e('Your creative business at a glance.', 'elev8-os'); ?></p>
                </div>
                <span class="elev8-dashboard-badge"><?php esc_html_e('Founders Edition', 'elev8-os'); ?></span>
            </header>

            <?php if (!$artist) : ?>
                <div class="elev8-dashboard-warning">
                    <p>
                        <strong><?php esc_html_e('Your Elev8 OS account is not connected to an Amelia artist record yet.', 'elev8-os'); ?></strong><br>
                        <?php echo esc_html(sprintf(__('Ask an administrator to match your WordPress email (%s) to your Amelia artist email.', 'elev8-os'), $user->user_email)); ?>
                    </p>
                </div>
            <?php elseif (!$data['available']) : ?>
                <div class="elev8-dashboard-warning">
                    <p><strong><?php esc_html_e('Live Amelia statistics are temporarily unavailable.', 'elev8-os'); ?></strong></p>
                </div>
            <?php endif; ?>

            <section class="elev8-dashboard-grid elev8-dashboard-summary elev8-dashboard-summary-live" aria-label="<?php esc_attr_e('Artist live statistics', 'elev8-os'); ?>">
                <?php
                self::render_metric_card('calendar-alt', __('Upcoming Class Dates', 'elev8-os'), (int) $data['upcoming_classes'], __('future class dates scheduled', 'elev8-os'));
                self::render_metric_card('groups', __('Students Booked', 'elev8-os'), (int) $data['booked_students'], __('students on upcoming classes', 'elev8-os'));
                self::render_metric_card('clock', __('Pending', 'elev8-os'), (int) $data['pending_bookings'], __('students awaiting confirmation', 'elev8-os'));
                self::render_metric_card('dismiss', __('Cancelled', 'elev8-os'), (int) $data['cancelled_bookings'], __('cancelled students on upcoming classes', 'elev8-os'));
                ?>
            </section>

            <section class="elev8-dashboard-panel" aria-labelledby="elev8-upcoming-classes-title">
                <div class="elev8-dashboard-panel-header">
                    <div>
                        <p class="elev8-card-label"><?php esc_html_e('Live Amelia Data', 'elev8-os'); ?></p>
                        <h2 id="elev8-upcoming-classes-title"><?php esc_html_e('Upcoming Classes', 'elev8-os'); ?></h2>
                    </div>
                    <span class="elev8-average-class-size">
                        <?php echo esc_html(sprintf(__('Average class size: %s', 'elev8-os'), number_format_i18n((float) $data['average_class_size'], 1))); ?>
                    </span>
                </div>

                <?php self::render_upcoming_appointments((array) $data['appointments']); ?>
            </section>

            <section class="elev8-dashboard-grid" aria-label="<?php esc_attr_e('Dashboard modules', 'elev8-os'); ?>">
                <?php self::render_placeholder_card('chart-bar', __('My Statistics', 'elev8-os'), __('Attendance, revenue, refunds, and historical trends.', 'elev8-os')); ?>
                <?php self::render_placeholder_card('money-alt', __('Earnings', 'elev8-os'), __('Estimated and pending payouts will appear here.', 'elev8-os')); ?>
                <?php self::render_placeholder_card('bell', __('Notifications', 'elev8-os'), __('Important updates and items needing attention.', 'elev8-os')); ?>
            </section>
        </div>
        <?php
    }

    /** @param array<int,array<string,mixed>> $appointments */
    private static function render_upcoming_appointments(array $appointments): void {
        if (!$appointments) {
            echo '<div class="elev8-dashboard-empty">';
            echo '<span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>';
            echo '<p>' . esc_html__('No upcoming class dates were found for this artist.', 'elev8-os') . '</p>';
            echo '</div>';
            return;
        }
        ?>
        <div class="elev8-upcoming-table-wrap">
            <table class="elev8-upcoming-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Class', 'elev8-os'); ?></th>
                        <th><?php esc_html_e('Date and Time', 'elev8-os'); ?></th>
                        <th><?php esc_html_e('Students', 'elev8-os'); ?></th>
                        <th><?php esc_html_e('Status', 'elev8-os'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($appointments as $appointment) : ?>
                        <?php
                        $starts_at = (string) ($appointment['starts_at'] ?? '');
                        $timestamp = $starts_at !== '' ? strtotime($starts_at) : false;
                        $service = trim((string) ($appointment['service_name'] ?? ''));
                        $status = trim((string) ($appointment['status'] ?? ''));
                        $booked = max(0, (int) ($appointment['booked_students'] ?? 0));
                        $capacity = max(0, (int) ($appointment['capacity'] ?? 0));
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html($service !== '' ? $service : __('Class', 'elev8-os')); ?></strong></td>
                            <td>
                                <?php
                                echo $timestamp
                                    ? esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), $timestamp))
                                    : esc_html($starts_at);
                                ?>
                            </td>
                            <td><?php echo esc_html($capacity > 0 ? sprintf('%d / %d', $booked, $capacity) : (string) $booked); ?></td>
                            <td><span class="elev8-status-pill"><?php echo esc_html($status !== '' ? ucfirst($status) : __('Scheduled', 'elev8-os')); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private static function render_metric_card(string $icon, string $title, int $value, string $description): void {
        ?>
        <article class="elev8-welcome-card">
            <div>
                <span class="dashicons dashicons-<?php echo esc_attr($icon); ?>" aria-hidden="true"></span>
                <div>
                    <p class="elev8-card-label"><?php echo esc_html($title); ?></p>
                    <strong><?php echo esc_html(number_format_i18n($value)); ?></strong>
                    <p><?php echo esc_html($description); ?></p>
                </div>
            </div>
        </article>
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

    private static function is_artist_user(WP_User $user): bool {
        if (self::find_artist_for_user($user)) {
            return true;
        }

        foreach ((array) $user->roles as $role) {
            $normalized = strtolower(str_replace(['_', '-'], ' ', (string) $role));

            if (strpos($normalized, 'amelia') !== false && (
                strpos($normalized, 'employee') !== false ||
                strpos($normalized, 'provider') !== false
            )) {
                return true;
            }
        }

        return false;
    }

    /**
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

        $columns = self::table_columns($table);
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

    /**
     * Count scheduled service/date assignments for an Amelia provider.
     *
     * This restores the behavior verified on Elev8 Arts, where Heather's
     * three scheduled dates appear as three provider-to-service assignments.
     */
    private static function get_active_service_count(int $artist_id): int {
        global $wpdb;

        $relation_tables = [
            $wpdb->prefix . 'amelia_providers_to_services',
            $wpdb->prefix . 'amelia_services_providers',
            $wpdb->prefix . 'amelia_providers_services',
        ];

        $relation_table = '';

        foreach ($relation_tables as $candidate) {
            if (self::table_exists($candidate)) {
                $relation_table = $candidate;
                break;
            }
        }

        if ($relation_table === '') {
            return 0;
        }

        $columns = self::table_columns($relation_table);
        $provider_column = self::first_existing_column(
            $columns,
            ['userId', 'providerId', 'employeeId', 'provider_id', 'user_id']
        );

        if (!$provider_column) {
            return 0;
        }

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*)
                 FROM `{$relation_table}`
                 WHERE `{$provider_column}` = %d",
                $artist_id
            )
        );

        return max(0, (int) $count);
    }

    /**
     * Count future Amelia appointments that have actually been booked.
     */
    private static function get_upcoming_booking_count(int $artist_id): int {
        global $wpdb;

        $appointments = $wpdb->prefix . 'amelia_appointments';

        if (!self::table_exists($appointments)) {
            return 0;
        }

        $columns = self::table_columns($appointments);
        $provider_id = self::first_existing_column(
            $columns,
            ['providerId', 'provider_id', 'employeeId']
        );
        $booking_start = self::first_existing_column(
            $columns,
            ['bookingStart', 'booking_start', 'start']
        );

        if (!$provider_id || !$booking_start) {
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
                 WHERE `{$provider_id}` = %d
                   AND `{$booking_start}` >= %s
                   {$status_sql}",
                $artist_id,
                current_time('mysql')
            )
        );

        return max(0, (int) $count);
    }

    private static function dashboard_url(): string {
        $page_id = (int) get_option(self::PAGE_OPTION);

        if ($page_id > 0 && get_post_status($page_id)) {
            return get_permalink($page_id);
        }

        return home_url('/' . self::FRONTEND_PAGE_SLUG . '/');
    }

    private static function is_dashboard_page(): bool {
        $page_id = (int) get_option(self::PAGE_OPTION);

        return ($page_id > 0 && is_page($page_id)) || is_page(self::FRONTEND_PAGE_SLUG);
    }

    /**
     * @return array<int,string>
     */
    private static function table_columns(string $table): array {
        global $wpdb;

        $columns = $wpdb->get_col("DESCRIBE `{$table}`", 0);

        return is_array($columns) ? array_map('strval', $columns) : [];
    }

    /**
     * @param array<int,string> $available
     * @param array<int,string> $candidates
     */
    private static function first_existing_column(array $available, array $candidates): ?string {
        foreach ($candidates as $candidate) {
            if (in_array($candidate, $available, true)) {
                return $candidate;
            }
        }

        return null;
    }

    private static function table_exists(string $table): bool {
        global $wpdb;

        $found = $wpdb->get_var(
            $wpdb->prepare('SHOW TABLES LIKE %s', $table)
        );

        return $found === $table;
    }
}

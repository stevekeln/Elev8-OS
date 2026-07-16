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
            __('Artist Dashboard Preview', 'elev8-os'),
            __('Artist Dashboard Preview', 'elev8-os'),
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
        $snapshot = class_exists('Elev8_OS_My_Classes_Module')
            ? Elev8_OS_My_Classes_Module::get_dashboard_snapshot($user)
            : [
                'available' => false,
                'reason' => __('The class schedule service is unavailable.', 'elev8-os'),
                'summary' => [],
                'upcoming' => [],
                'artist' => null,
            ];
        $artist = isset($snapshot['artist']) && is_array($snapshot['artist']) ? $snapshot['artist'] : self::find_artist_for_user($user);
        $first_name = $artist ? trim((string) ($artist['firstName'] ?? '')) : trim((string) $user->first_name);
        if ($first_name === '') { $first_name = $user->display_name ?: __('Artist', 'elev8-os'); }
        $summary = isset($snapshot['summary']) && is_array($snapshot['summary']) ? $snapshot['summary'] : [];
        $upcoming = isset($snapshot['upcoming']) && is_array($snapshot['upcoming']) ? $snapshot['upcoming'] : [];
        $next_class = $upcoming[0] ?? null;
        $classes_url = Elev8_OS_Portal_Page_Manager::get_url('classes');
        $students_url = Elev8_OS_Portal_Page_Manager::get_url('students');
        $website_url = Elev8_OS_Portal_Page_Manager::get_url('website');
        $edit_website_url = Elev8_OS_Portal_Page_Manager::get_url('edit_website');
        ?>
        <div class="elev8-artist-dashboard elev8-dashboard-v2">
            <?php Elev8_OS_Artist_Portal_Module::render_navigation('dashboard'); ?>
            <header class="elev8-dashboard-header elev8-dashboard-hero">
                <div>
                    <p class="elev8-eyebrow"><?php esc_html_e('Artist Portal', 'elev8-os'); ?></p>
                    <h1><?php echo esc_html(sprintf(__('Welcome back, %s!', 'elev8-os'), $first_name)); ?></h1>
                    <p><?php echo esc_html(wp_date('l, F j')); ?> · <?php esc_html_e('Here is what needs your attention.', 'elev8-os'); ?></p>
                </div>
                <span class="elev8-dashboard-badge"><?php esc_html_e('Founders Edition', 'elev8-os'); ?></span>
            </header>

            <?php if (empty($snapshot['available'])) : ?>
                <div class="elev8-dashboard-warning"><p><strong><?php esc_html_e('Your verified class information is unavailable.', 'elev8-os'); ?></strong><br><?php echo esc_html((string) ($snapshot['reason'] ?? __('No diagnostic was supplied.', 'elev8-os'))); ?></p></div>
            <?php endif; ?>

            <section class="elev8-dashboard-grid elev8-dashboard-summary elev8-dashboard-summary-v2" aria-label="<?php esc_attr_e('Artist summary', 'elev8-os'); ?>">
                <?php self::render_value_card('calendar-alt', __('Upcoming Classes', 'elev8-os'), $summary['upcoming_count'] ?? null, __('Future class dates assigned to you', 'elev8-os')); ?>
                <?php self::render_value_card('groups', __('Students Enrolled', 'elev8-os'), $summary['student_count'] ?? null, __('Across your upcoming classes', 'elev8-os')); ?>
                <?php self::render_value_card('tickets-alt', __('Seats Available', 'elev8-os'), $summary['seats_available'] ?? null, __('Across classes with verified capacity', 'elev8-os')); ?>
                <?php self::render_value_card('money-alt', __('Booked Value', 'elev8-os'), $summary['booked_value'] ?? null, __('Scheduled booking value, not payout', 'elev8-os'), true); ?>
            </section>

            <div class="elev8-dashboard-main-grid">
                <section class="elev8-dashboard-panel elev8-next-class-panel">
                    <div class="elev8-panel-heading"><div><p class="elev8-eyebrow"><?php esc_html_e('Up next', 'elev8-os'); ?></p><h2><?php esc_html_e('Next Class', 'elev8-os'); ?></h2></div><a href="<?php echo esc_url($classes_url); ?>"><?php esc_html_e('View all classes', 'elev8-os'); ?></a></div>
                    <?php if (!$next_class) : ?>
                        <div class="elev8-dashboard-empty"><span class="dashicons dashicons-calendar-alt"></span><h3><?php esc_html_e('No upcoming class found', 'elev8-os'); ?></h3><p><?php esc_html_e('Your next verified Amelia class will appear here.', 'elev8-os'); ?></p></div>
                    <?php else : ?>
                        <?php $ts = strtotime((string) $next_class['start']); ?>
                        <article class="elev8-next-class">
                            <div class="elev8-next-class-date"><span><?php echo esc_html($ts ? wp_date('M', $ts) : ''); ?></span><strong><?php echo esc_html($ts ? wp_date('j', $ts) : ''); ?></strong></div>
                            <div class="elev8-next-class-body"><h3><?php echo esc_html((string) $next_class['name']); ?></h3><p><span class="dashicons dashicons-clock"></span><?php echo esc_html($ts ? wp_date(get_option('date_format').' '.get_option('time_format'), $ts) : (string) $next_class['start']); ?></p><?php if ((string)$next_class['location'] !== '') : ?><p><span class="dashicons dashicons-location"></span><?php echo esc_html((string)$next_class['location']); ?></p><?php endif; ?><div class="elev8-next-class-facts"><span><strong><?php echo esc_html(number_format_i18n((int)$next_class['students'])); ?></strong> <?php esc_html_e('students', 'elev8-os'); ?></span><span><strong><?php echo $next_class['seats_left'] === null ? esc_html__('Unavailable','elev8-os') : esc_html(number_format_i18n((int)$next_class['seats_left'])); ?></strong> <?php esc_html_e('seats left', 'elev8-os'); ?></span></div></div>
                        </article>
                    <?php endif; ?>
                </section>

                <section class="elev8-dashboard-panel">
                    <div class="elev8-panel-heading"><div><p class="elev8-eyebrow"><?php esc_html_e('Shortcuts', 'elev8-os'); ?></p><h2><?php esc_html_e('Quick Actions', 'elev8-os'); ?></h2></div></div>
                    <div class="elev8-quick-actions">
                        <?php self::render_action_link('calendar-alt', __('Manage My Classes', 'elev8-os'), __('Schedule, enrollment, and booking links', 'elev8-os'), $classes_url); ?>
                        <?php self::render_action_link('groups', __('View My Students', 'elev8-os'), __('Open class rosters and contact details', 'elev8-os'), $students_url); ?>
                        <?php self::render_action_link('admin-home', __('View My Website', 'elev8-os'), __('See what customers see', 'elev8-os'), $website_url); ?>
                        <?php self::render_action_link('edit', __('Edit My Website', 'elev8-os'), __('Update your bio, links, and profile', 'elev8-os'), $edit_website_url); ?>
                    </div>
                </section>
            </div>

            <section class="elev8-dashboard-panel elev8-dashboard-status-panel">
                <div class="elev8-panel-heading"><div><p class="elev8-eyebrow"><?php esc_html_e('Today', 'elev8-os'); ?></p><h2><?php esc_html_e('Your Artist Checklist', 'elev8-os'); ?></h2></div></div>
                <div class="elev8-artist-checklist">
                    <div class="is-complete"><span class="dashicons dashicons-yes-alt"></span><div><strong><?php esc_html_e('Artist account connected', 'elev8-os'); ?></strong><p><?php echo $artist ? esc_html__('Your WordPress account is mapped to Amelia.', 'elev8-os') : esc_html__('Connection unavailable.', 'elev8-os'); ?></p></div></div>
                    <div class="<?php echo $next_class ? 'is-complete' : 'is-pending'; ?>"><span class="dashicons dashicons-<?php echo $next_class ? 'yes-alt' : 'warning'; ?>"></span><div><strong><?php esc_html_e('Upcoming schedule', 'elev8-os'); ?></strong><p><?php echo $next_class ? esc_html__('Your next class is ready to review.', 'elev8-os') : esc_html__('No upcoming class is currently verified.', 'elev8-os'); ?></p></div></div>
                    <div class="is-ready"><span class="dashicons dashicons-admin-page"></span><div><strong><?php esc_html_e('Keep your public page current', 'elev8-os'); ?></strong><p><?php esc_html_e('Review your bio, links, and class information regularly.', 'elev8-os'); ?></p></div><a href="<?php echo esc_url($edit_website_url); ?>"><?php esc_html_e('Review website', 'elev8-os'); ?></a></div>
                </div>
            </section>
        </div>
        <?php
    }

    /** @param int|float|null $value */
    private static function render_value_card(string $icon, string $title, $value, string $description, bool $money = false): void {
        $available = is_numeric($value);
        $display = __('Unavailable', 'elev8-os');
        if ($available) { $display = $money ? self::format_money((float)$value) : number_format_i18n((int)$value); }
        ?>
        <article class="elev8-welcome-card <?php echo $available ? '' : 'is-unavailable'; ?>"><div><span class="dashicons dashicons-<?php echo esc_attr($icon); ?>"></span><div><p class="elev8-card-label"><?php echo esc_html($title); ?></p><strong><?php echo esc_html($display); ?></strong><p><?php echo esc_html($description); ?></p></div></div></article>
        <?php
    }

    private static function render_action_link(string $icon, string $title, string $description, string $url): void {
        ?><a class="elev8-quick-action" href="<?php echo esc_url($url); ?>"><span class="dashicons dashicons-<?php echo esc_attr($icon); ?>"></span><span><strong><?php echo esc_html($title); ?></strong><small><?php echo esc_html($description); ?></small></span><span class="dashicons dashicons-arrow-right-alt2"></span></a><?php
    }

    private static function format_money(float $value): string {
        if (function_exists('wc_price')) { return wp_strip_all_tags((string) wc_price($value)); }
        return '$' . number_format_i18n($value, 2);
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
        $table = $wpdb->prefix . 'amelia_users';
        if (!self::table_exists($table)) { return null; }
        $columns = self::table_columns($table);
        if (!in_array('id', $columns, true)) { return null; }
        $select = ['id'];
        foreach (['firstName','lastName','email'] as $column) { if (in_array($column,$columns,true)) { $select[]=$column; } }
        $select_sql = implode(', ', array_map(static fn(string $column): string => "`{$column}`", $select));
        $type_sql = in_array('type',$columns,true) ? " AND LOWER(COALESCE(`type`,'')) IN ('provider','employee')" : '';
        $mapped_id = max(0,(int)get_user_meta($user->ID,'elev8_os_amelia_employee_id',true));
        if ($mapped_id > 0) {
            $mapped = $wpdb->get_row($wpdb->prepare("SELECT {$select_sql} FROM `{$table}` WHERE `id`=%d{$type_sql} LIMIT 1",$mapped_id),ARRAY_A);
            if (is_array($mapped)) { return $mapped; }
        }
        $email=sanitize_email((string)$user->user_email);
        if ($email==='' || !in_array('email',$columns,true)) { return null; }
        $artist=$wpdb->get_row($wpdb->prepare("SELECT {$select_sql} FROM `{$table}` WHERE LOWER(`email`)=LOWER(%s){$type_sql} LIMIT 1",$email),ARRAY_A);
        return is_array($artist)?$artist:null;
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
        return Elev8_OS_Portal_Page_Manager::get_url('dashboard');
    }

    private static function is_dashboard_page(): bool {
        return Elev8_OS_Portal_Page_Manager::is_current_page('dashboard');
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

<?php
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS {
    const VERSION = '6.3.0'; // Legacy compatibility; UI and assets use ELEV8_OS_VERSION.
    const OPTION_PROFILES = 'elev8_os_artist_profiles';
    const OPTION_PAYOUTS = 'elev8_os_artist_payouts';
    const OPTION_RULES = 'elev8_os_teacher_rules';
    const OPTION_REFERRALS = 'elev8_os_referrals';
    const OPTION_DEV_ITEMS = 'elev8_os_dev_items';
    const OPTION_RELEASES = 'elev8_os_releases';

    /** @var array<int,string> */
    private static $upcoming_diagnostics = [];

    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'admin_menu'], 10);
        add_action('admin_menu', [__CLASS__, 'register_development_menu'], 90);
        add_action('admin_menu', [__CLASS__, 'reorder_admin_submenu'], 999);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('admin_post_elev8_os_save_rule', [__CLASS__, 'save_rule']);
        add_action('admin_post_elev8_os_delete_rule', [__CLASS__, 'delete_rule']);
        add_action('admin_post_elev8_os_save_artist_profile', [__CLASS__, 'save_artist_profile']);
        add_action('admin_post_elev8_os_save_print_settings', [__CLASS__, 'save_print_settings']);
        add_action('admin_post_elev8_os_print_artist', [__CLASS__, 'print_artist_action']);
        add_action('admin_post_elev8_os_print_artwork', [__CLASS__, 'print_artwork_action']);
        add_action('admin_post_elev8_os_save_payout', [__CLASS__, 'save_payout']);
        add_action('admin_post_elev8_os_save_dev_item', [__CLASS__, 'save_dev_item']);
        add_action('admin_post_elev8_os_delete_dev_item', [__CLASS__, 'delete_dev_item']);
        add_action('admin_post_elev8_os_save_release', [__CLASS__, 'save_release']);
        add_shortcode('elev8_artist_portal', [__CLASS__, 'artist_portal_shortcode']);
        add_shortcode('elev8_artist_profile', [__CLASS__, 'artist_profile_shortcode']);
        add_action('init', [__CLASS__, 'add_rewrite_rules']);
        add_action('init', [__CLASS__, 'maybe_flush_rewrite_rules'], 99);
        add_filter('query_vars', [__CLASS__, 'query_vars']);
        add_action('template_redirect', [__CLASS__, 'capture_referral'], 5);
        add_action('template_redirect', [__CLASS__, 'render_print_route'], 18);
        add_action('template_redirect', [__CLASS__, 'render_asset_route'], 19);
        add_action('template_redirect', [__CLASS__, 'render_public_route'], 20);
        add_action('wp_enqueue_scripts', [__CLASS__, 'front_assets']);
        add_action('woocommerce_payment_complete', [__CLASS__, 'record_woocommerce_referral']);
        add_action('woocommerce_order_status_completed', [__CLASS__, 'record_woocommerce_referral']);
    }

    public static function activate(): void {
        self::seed_development_data();
        if (class_exists('Elev8_OS_Portal_Page_Manager')) {
            Elev8_OS_Portal_Page_Manager::activate();
        }
        if (class_exists('Elev8_OS_Waitlist_Module')) {
            Elev8_OS_Waitlist_Module::activate();
        }
        if (class_exists('Elev8_OS_My_Artwork_Module')) {
            Elev8_OS_My_Artwork_Module::activate();
        }
        if (class_exists('Elev8_OS_Gallery_Operations_Service')) {
            Elev8_OS_Gallery_Operations_Service::activate();
        }
        if (class_exists('Elev8_OS_Student_Relationship_Service')) {
            Elev8_OS_Student_Relationship_Service::activate();
        }
        if (class_exists('Elev8_OS_Marketing_Service')) {
            Elev8_OS_Marketing_Service::activate();
        }
        if (class_exists('Elev8_OS_Content_Studio_Service')) {
            Elev8_OS_Content_Studio_Service::activate();
        }
        if (class_exists('Elev8_OS_Daily_Operations_Service')) {
            Elev8_OS_Daily_Operations_Service::activate();
        }
        if (class_exists('Elev8_OS_Access_Service')) {
            Elev8_OS_Access_Service::activate();
        }
        if (class_exists('Elev8_OS_Community_Outreach_Module')) {
            Elev8_OS_Community_Outreach_Module::activate();
        }
        if (class_exists('Elev8_OS_Checkin_Center_Module')) {
            Elev8_OS_Checkin_Center_Module::activate();
        }
        if (class_exists('Elev8_OS_Class_Demand_Module')) {
            Elev8_OS_Class_Demand_Module::activate();
        }
        if (class_exists('Elev8_OS_Opportunity_Module')) {
            Elev8_OS_Opportunity_Module::activate();
        }
        self::add_rewrite_rules();
        flush_rewrite_rules();
    }

    public static function admin_menu(): void {
        add_menu_page(
            'Elev8 OS',
            'Elev8 OS',
            'manage_options',
            'elev8-os',
            [__CLASS__, 'render_dashboard'],
            'dashicons-chart-area',
            3
        );



        add_submenu_page(
            'elev8-os',
            'Artists',
            'Artists',
            'manage_options',
            'elev8-artist-portal',
            [__CLASS__, 'render_artist_portal_admin']
        );

        add_submenu_page(
            'elev8-os',
            'Print Center',
            'Print Center',
            'manage_options',
            'elev8-print-center',
            [__CLASS__, 'render_print_center']
        );

        add_submenu_page(
            'elev8-os',
            'Print & Identity',
            'Print Settings',
            'manage_options',
            'elev8-print-identity',
            [__CLASS__, 'render_print_identity_settings']
        );
    }


    public static function register_development_menu(): void {
        add_submenu_page(
            'elev8-os',
            'Development Center',
            'Development',
            'manage_options',
            'elev8-development',
            [__CLASS__, 'render_development']
        );
    }


    /**
     * Keep the Elev8 OS owner menu focused on the pages used most often.
     * WordPress builds submenu items across several modules, so ordering is
     * applied once after every module has registered its page.
     */
    public static function reorder_admin_submenu(): void {
        global $submenu;

        if (empty($submenu['elev8-os']) || !is_array($submenu['elev8-os'])) {
            return;
        }

        $priority = [
            'elev8-os'               => 10,
            'elev8-ceo-dashboard'    => 20,
            'elev8-daily-operations' => 25,
            'elev8-artist-dashboard' => 30,
            'elev8-class-demand'     => 40,
            'elev8-class-requests'   => 50,
            'elev8-artist-portal'    => 60,
            'elev8-growth-center'    => 70,
            'elev8-gallery-operations' => 80,
            'elev8-community-outreach' => 85,
            'elev8-business-intelligence' => 90,
            'elev8-content-studio'   => 100,
            'elev8-print-center'     => 110,
            'elev8-print-identity'   => 120,
            'elev8-employee-mapping' => 200,
            'elev8-portal-setup'     => 210,
            'elev8-growth-settings'  => 220,
            'elev8-system-status'    => 230,
            'elev8-development'      => 240,
        ];

        $original_order = [];
        foreach ($submenu['elev8-os'] as $index => $item) {
            $slug = isset($item[2]) ? (string) $item[2] : '';
            if (!isset($original_order[$slug])) {
                $original_order[$slug] = (int) $index;
            }
        }

        usort($submenu['elev8-os'], static function (array $left, array $right) use ($priority, $original_order): int {
            $left_slug = isset($left[2]) ? (string) $left[2] : '';
            $right_slug = isset($right[2]) ? (string) $right[2] : '';
            $left_priority = $priority[$left_slug] ?? (500 + ($original_order[$left_slug] ?? 0));
            $right_priority = $priority[$right_slug] ?? (500 + ($original_order[$right_slug] ?? 0));
            return $left_priority <=> $right_priority;
        });
    }

    public static function enqueue_assets(string $hook): void {
        if (!in_array($hook, ['toplevel_page_elev8-os', 'elev8-os_page_elev8-artist-portal', 'elev8-os_page_elev8-development', 'elev8-os_page_elev8-system-status', 'elev8-os_page_elev8-print-identity', 'elev8-os_page_elev8-print-center'], true)) {
            return;
        }

        wp_enqueue_style(
            'elev8-os-admin',
            ELEV8_OS_URL . 'assets/css/admin.css',
            [],
            ELEV8_OS_VERSION
        );

        if ($hook === 'elev8-os_page_elev8-print-identity') {
            wp_enqueue_media();
        }

        if ($hook === 'elev8-os_page_elev8-artist-portal') {
            wp_enqueue_script(
                'elev8-os-searchable-user-select',
                ELEV8_OS_URL . 'assets/js/searchable-user-select.js',
                [],
                ELEV8_OS_VERSION,
                true
            );
        }
    }

    private static function table(string $suffix): string {
        global $wpdb;
        return $wpdb->prefix . 'amelia_' . $suffix;
    }

    private static function table_exists(string $table): bool {
        global $wpdb;
        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        return $found === $table;
    }

    private static function existing_tables(): array {
        $names = [
            'appointments',
            'customer_bookings',
            'users',
            'services',
            'payments',
            'locations',
        ];

        $result = [];
        foreach ($names as $name) {
            $table = self::table($name);
            $result[$name] = [
                'table' => $table,
                'exists' => self::table_exists($table),
            ];
        }
        return $result;
    }

    private static function column_exists(string $table, string $column): bool {
        global $wpdb;
        if (!self::table_exists($table)) {
            return false;
        }

        $columns = $wpdb->get_col("DESCRIBE `{$table}`", 0);
        return in_array($column, $columns, true);
    }

    private static function get_rules(): array {
        $rules = get_option(self::OPTION_RULES, []);
        return is_array($rules) ? $rules : [];
    }

    public static function save_rule(): void {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to do this.');
        }

        check_admin_referer('elev8_os_save_rule');

        $employee_id = isset($_POST['employee_id']) ? absint($_POST['employee_id']) : 0;
        $employee_name = isset($_POST['employee_name']) ? sanitize_text_field(wp_unslash($_POST['employee_name'])) : '';
        $rule_type = isset($_POST['rule_type']) ? sanitize_key($_POST['rule_type']) : 'free';

        $allowed = ['tiered_partnership', 'percent_elev8', 'free', 'host_fee', 'percent_teacher', 'fixed_teacher'];
        if (!in_array($rule_type, $allowed, true)) {
            $rule_type = 'tiered_partnership';
        }

        $key = $employee_id > 0 ? 'employee_' . $employee_id : 'name_' . sanitize_title($employee_name);
        if ($key === 'name_') {
            wp_safe_redirect(add_query_arg(['page' => 'elev8-os', 'message' => 'missing_teacher'], admin_url('admin.php')));
            exit;
        }

        $rules = self::get_rules();
        $rules[$key] = [
            'employee_id' => $employee_id,
            'employee_name' => $employee_name,
            'rule_type' => $rule_type,
            'base_elev8_percent' => isset($_POST['base_elev8_percent']) ? (float) $_POST['base_elev8_percent'] : 40,
            'elev8_cap' => isset($_POST['elev8_cap']) ? (float) $_POST['elev8_cap'] : 100,
            'after_cap_elev8_percent' => isset($_POST['after_cap_elev8_percent']) ? (float) $_POST['after_cap_elev8_percent'] : 15,
            'elev8_percent' => isset($_POST['elev8_percent']) ? (float) $_POST['elev8_percent'] : 15,
            'host_fee' => isset($_POST['host_fee']) ? (float) $_POST['host_fee'] : 0,
            'threshold' => isset($_POST['threshold']) ? absint($_POST['threshold']) : 0,
            'extra_percent' => isset($_POST['extra_percent']) ? (float) $_POST['extra_percent'] : 0,
            'teacher_percent' => isset($_POST['teacher_percent']) ? (float) $_POST['teacher_percent'] : 0,
            'fixed_teacher' => isset($_POST['fixed_teacher']) ? (float) $_POST['fixed_teacher'] : 0,
        ];

        update_option(self::OPTION_RULES, $rules, false);

        wp_safe_redirect(add_query_arg(['page' => 'elev8-os', 'message' => 'saved'], admin_url('admin.php')));
        exit;
    }

    public static function delete_rule(): void {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to do this.');
        }

        check_admin_referer('elev8_os_delete_rule');

        $rule_key = isset($_GET['rule_key']) ? sanitize_text_field(wp_unslash($_GET['rule_key'])) : '';
        $rules = self::get_rules();
        if (isset($rules[$rule_key])) {
            unset($rules[$rule_key]);
            update_option(self::OPTION_RULES, $rules, false);
        }

        wp_safe_redirect(add_query_arg(['page' => 'elev8-os', 'message' => 'deleted'], admin_url('admin.php')));
        exit;
    }

    private static function get_employees(): array {
        global $wpdb;
        $users = self::table('users');
        if (!self::table_exists($users)) {
            return [];
        }

        $has_type = self::column_exists($users, 'type');
        $where = $has_type ? "WHERE `type` = 'provider'" : '';

        return $wpdb->get_results(
            "SELECT `id`, `firstName`, `lastName`
             FROM `{$users}`
             {$where}
             ORDER BY `firstName`, `lastName`",
            ARRAY_A
        ) ?: [];
    }

    private static function date_range(): array {
        $month = isset($_GET['elev8_month']) ? sanitize_text_field(wp_unslash($_GET['elev8_month'])) : current_time('Y-m');
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = current_time('Y-m');
        }

        $start = $month . '-01 00:00:00';
        $date = DateTime::createFromFormat('Y-m-d H:i:s', $start);
        if (!$date) {
            $date = new DateTime('first day of this month midnight');
        }
        $end_date = clone $date;
        $end_date->modify('first day of next month');

        return [
            'month' => $month,
            'start' => $date->format('Y-m-d H:i:s'),
            'end' => $end_date->format('Y-m-d H:i:s'),
        ];
    }

    private static function get_monthly_rows(array $range): array {
        global $wpdb;

        $appointments = self::table('appointments');
        $bookings = self::table('customer_bookings');
        $users = self::table('users');
        $services = self::table('services');
        $payments = self::table('payments');

        foreach ([$appointments, $bookings, $users, $services] as $required) {
            if (!self::table_exists($required)) {
                return [];
            }
        }

        $payment_join = '';
        $payment_select = 'NULL AS payment_amount, NULL AS payment_status';
        if (self::table_exists($payments) && self::column_exists($payments, 'customerBookingId')) {
            $payment_join = "LEFT JOIN `{$payments}` p ON p.`customerBookingId` = cb.`id`";
            $payment_select = 'p.`amount` AS payment_amount, p.`status` AS payment_status';
        }

        $persons_select = self::column_exists($bookings, 'persons') ? 'cb.`persons`' : '1 AS persons';
        $price_select = self::column_exists($bookings, 'price') ? 'cb.`price`' : 's.`price` AS price';
        $booking_status = self::column_exists($bookings, 'status') ? 'cb.`status` AS booking_status' : "'' AS booking_status";

        $sql = $wpdb->prepare(
            "SELECT
                a.`id` AS appointment_id,
                a.`bookingStart`,
                a.`providerId`,
                a.`serviceId`,
                u.`firstName`,
                u.`lastName`,
                s.`name` AS service_name,
                {$persons_select},
                {$price_select},
                {$booking_status},
                {$payment_select}
             FROM `{$appointments}` a
             INNER JOIN `{$bookings}` cb ON cb.`appointmentId` = a.`id`
             LEFT JOIN `{$users}` u ON u.`id` = a.`providerId`
             LEFT JOIN `{$services}` s ON s.`id` = a.`serviceId`
             {$payment_join}
             WHERE a.`bookingStart` >= %s
               AND a.`bookingStart` < %s
             ORDER BY a.`bookingStart` ASC",
            $range['start'],
            $range['end']
        );

        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    private static function normalize_rows(array $rows): array {
        $grouped = [];

        foreach ($rows as $row) {
            $employee_id = (int) ($row['providerId'] ?? 0);
            $teacher = trim(($row['firstName'] ?? '') . ' ' . ($row['lastName'] ?? ''));
            if ($teacher === '') {
                $teacher = 'Unassigned';
            }

            $appointment_id = (int) ($row['appointment_id'] ?? 0);
            $key = $employee_id . '|' . $appointment_id;

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'employee_id' => $employee_id,
                    'teacher' => $teacher,
                    'appointment_id' => $appointment_id,
                    'date' => $row['bookingStart'] ?? '',
                    'service' => $row['service_name'] ?? '',
                    'customers' => 0,
                    'gross' => 0.0,
                    'refunds' => 0.0,
                ];
            }

            $persons = max(1, (int) ($row['persons'] ?? 1));
            $status = strtolower((string) ($row['booking_status'] ?? ''));
            $payment_status = strtolower((string) ($row['payment_status'] ?? ''));
            $is_refund = str_contains($status, 'cancel') ||
                         str_contains($status, 'reject') ||
                         str_contains($payment_status, 'refund');

            $payment_amount = $row['payment_amount'];
            if ($payment_amount !== null && $payment_amount !== '') {
                $amount = (float) $payment_amount;
            } else {
                $amount = (float) ($row['price'] ?? 0) * $persons;
            }

            $grouped[$key]['customers'] += $persons;
            if ($is_refund) {
                $grouped[$key]['refunds'] += abs($amount);
            } else {
                $grouped[$key]['gross'] += $amount;
            }
        }

        return array_values($grouped);
    }

    private static function find_rule(array $class, array $rules): array {
        $by_id = 'employee_' . (int) $class['employee_id'];
        if (isset($rules[$by_id])) {
            return $rules[$by_id];
        }

        $by_name = 'name_' . sanitize_title($class['teacher']);
        if (isset($rules[$by_name])) {
            return $rules[$by_name];
        }

        return [
            'rule_type' => 'free',
            'base_elev8_percent' => 40,
            'elev8_cap' => 100,
            'after_cap_elev8_percent' => 15,
            'elev8_percent' => 15,
            'host_fee' => 0,
            'threshold' => 0,
            'extra_percent' => 0,
            'teacher_percent' => 100,
            'fixed_teacher' => 0,
        ];
    }

    private static function calculate_class(array $class, array $rule): array {
        $net = max(0, (float) $class['gross'] - (float) $class['refunds']);
        $teacher = $net;
        $elev8 = 0.0;

        switch ($rule['rule_type'] ?? 'free') {
            case 'tiered_partnership':
                $base_percent = min(100, max(0, (float) ($rule['base_elev8_percent'] ?? 40))) / 100;
                $cap = max(0, (float) ($rule['elev8_cap'] ?? 100));
                $after_percent = min(100, max(0, (float) ($rule['after_cap_elev8_percent'] ?? 15))) / 100;
                if ($base_percent <= 0) {
                    $elev8 = $net * $after_percent;
                } else {
                    $revenue_to_cap = $cap / $base_percent;
                    $elev8 = ($net <= $revenue_to_cap)
                        ? $net * $base_percent
                        : $cap + (($net - $revenue_to_cap) * $after_percent);
                }
                $elev8 = min($net, max(0, $elev8));
                $teacher = max(0, $net - $elev8);
                break;

            case 'percent_elev8':
                $elev8_percent = min(100, max(0, (float) ($rule['elev8_percent'] ?? 15))) / 100;
                $elev8 = $net * $elev8_percent;
                $teacher = max(0, $net - $elev8);
                break;

            case 'host_fee':
                $host_fee = max(0, (float) ($rule['host_fee'] ?? 0));
                $threshold = max(0, (int) ($rule['threshold'] ?? 0));
                $extra_percent = max(0, (float) ($rule['extra_percent'] ?? 0)) / 100;
                $customers = max(0, (int) $class['customers']);

                $base = min($net, $host_fee);
                $extra_customers = max(0, $customers - $threshold);
                $average_paid = $customers > 0 ? $net / $customers : 0;
                $extra = $extra_customers * $average_paid * $extra_percent;

                $elev8 = min($net, $base + $extra);
                $teacher = max(0, $net - $elev8);
                break;

            case 'percent_teacher':
                $teacher_percent = min(100, max(0, (float) ($rule['teacher_percent'] ?? 0))) / 100;
                $teacher = $net * $teacher_percent;
                $elev8 = $net - $teacher;
                break;

            case 'fixed_teacher':
                $teacher = min($net, max(0, (float) ($rule['fixed_teacher'] ?? 0)));
                $elev8 = $net - $teacher;
                break;

            case 'free':
            default:
                $teacher = $net;
                $elev8 = 0;
                break;
        }

        return [
            'net' => $net,
            'teacher' => $teacher,
            'elev8' => $elev8,
        ];
    }

    private static function summarize(array $classes, array $rules): array {
        $summary = [];
        $totals = [
            'classes' => 0,
            'customers' => 0,
            'gross' => 0.0,
            'refunds' => 0.0,
            'teacher' => 0.0,
            'elev8' => 0.0,
        ];

        foreach ($classes as $class) {
            $rule = self::find_rule($class, $rules);
            $calc = self::calculate_class($class, $rule);
            $teacher = $class['teacher'];

            if (!isset($summary[$teacher])) {
                $summary[$teacher] = [
                    'classes' => 0,
                    'customers' => 0,
                    'gross' => 0.0,
                    'refunds' => 0.0,
                    'teacher' => 0.0,
                    'elev8' => 0.0,
                ];
            }

            $summary[$teacher]['classes']++;
            $summary[$teacher]['customers'] += $class['customers'];
            $summary[$teacher]['gross'] += $class['gross'];
            $summary[$teacher]['refunds'] += $class['refunds'];
            $summary[$teacher]['teacher'] += $calc['teacher'];
            $summary[$teacher]['elev8'] += $calc['elev8'];

            $totals['classes']++;
            $totals['customers'] += $class['customers'];
            $totals['gross'] += $class['gross'];
            $totals['refunds'] += $class['refunds'];
            $totals['teacher'] += $calc['teacher'];
            $totals['elev8'] += $calc['elev8'];
        }

        ksort($summary);
        return [$summary, $totals];
    }

    private static function money(float $amount): string {
        return '$' . number_format($amount, 2);
    }

    private static function rule_description(array $rule): string {
        switch ($rule['rule_type'] ?? 'free') {
            case 'tiered_partnership':
                return sprintf(
                    'Elev8 receives %s%% until Elev8 has received %s, then %s%% of additional net revenue.',
                    number_format((float) ($rule['base_elev8_percent'] ?? 40), 1),
                    self::money((float) ($rule['elev8_cap'] ?? 100)),
                    number_format((float) ($rule['after_cap_elev8_percent'] ?? 15), 1)
                );
            case 'percent_elev8':
                return sprintf(
                    'Elev8 receives %s%% of net revenue; the artist side receives %s%%.',
                    number_format((float) ($rule['elev8_percent'] ?? 15), 1),
                    number_format(100 - min(100, max(0, (float) ($rule['elev8_percent'] ?? 15))), 1)
                );
            case 'host_fee':
                return sprintf(
                    'Artist keeps sales and pays Elev8 %s per class, plus %s%% for each customer after %d.',
                    self::money((float) ($rule['host_fee'] ?? 0)),
                    number_format((float) ($rule['extra_percent'] ?? 0), 1),
                    (int) ($rule['threshold'] ?? 0)
                );
            case 'percent_teacher':
                return sprintf('Artist receives %s%% of net revenue.', number_format((float) ($rule['teacher_percent'] ?? 0), 1));
            case 'fixed_teacher':
                return sprintf('Artist receives %s per class.', self::money((float) ($rule['fixed_teacher'] ?? 0)));
            default:
                return 'Artist keeps all net class revenue; Elev8 receives no host fee.';
        }
    }


    private static function artist_name_for_rule(array $rule, array $employees = []): string {
        $employee_id = (int) ($rule['employee_id'] ?? 0);
        if ($employee_id > 0) {
            if (!$employees) {
                $employees = self::get_employees();
            }
            foreach ($employees as $employee) {
                if ((int) $employee['id'] === $employee_id) {
                    $name = trim(($employee['firstName'] ?? '') . ' ' . ($employee['lastName'] ?? ''));
                    if ($name !== '') {
                        return $name;
                    }
                }
            }
        }
        $typed = trim((string) ($rule['employee_name'] ?? ''));
        return $typed !== '' ? $typed : 'Unassigned artist';
    }

    private static function get_profiles(): array {
        $profiles = get_option(self::OPTION_PROFILES, []);
        return is_array($profiles) ? $profiles : [];
    }

    public static function save_artist_profile(): void {
        if (!current_user_can('manage_options')) wp_die('You do not have permission to do this.');
        check_admin_referer('elev8_os_save_artist_profile');
        $employee_id = isset($_POST['employee_id']) ? absint($_POST['employee_id']) : 0;
        if (!$employee_id) wp_die('Please select an Elev8 Member Artist.');
        $profiles = self::get_profiles();
        $requested_user_id = isset($_POST['wp_user_id']) ? absint($_POST['wp_user_id']) : 0;
        if ($requested_user_id > 0) {
            $mapped_artist_id = class_exists('Elev8_OS_Identity_Service')
                ? Elev8_OS_Identity_Service::artist_id_for_user_id($requested_user_id)
                : 0;
            if ($mapped_artist_id > 0 && $mapped_artist_id !== $employee_id) {
                wp_die(esc_html__('That WordPress account is already approved for another artist. Change it through Artist Mapping before connecting it here.', 'elev8-os'));
            }
            foreach ($profiles as $existing_artist_id => $existing_profile) {
                if (absint($existing_artist_id) === $employee_id || !is_array($existing_profile)) { continue; }
                if (absint($existing_profile['wp_user_id'] ?? 0) === $requested_user_id) {
                    wp_die(esc_html__('That WordPress account is already connected to another artist profile. Disconnect the earlier profile first.', 'elev8-os'));
                }
            }
        }
        $profiles[$employee_id] = [
            'wp_user_id' => $requested_user_id,
            'bio' => isset($_POST['bio']) ? sanitize_textarea_field(wp_unslash($_POST['bio'])) : '',
            'medium' => isset($_POST['medium']) ? sanitize_text_field(wp_unslash($_POST['medium'])) : '',
            'website' => isset($_POST['website']) ? esc_url_raw(wp_unslash($_POST['website'])) : '',
            'social' => isset($_POST['social_1_url']) ? esc_url_raw(wp_unslash($_POST['social_1_url'])) : (isset($_POST['social']) ? esc_url_raw(wp_unslash($_POST['social'])) : ''),
            'social_1_name' => isset($_POST['social_1_name']) ? sanitize_text_field(wp_unslash($_POST['social_1_name'])) : '',
            'social_1_url' => isset($_POST['social_1_url']) ? esc_url_raw(wp_unslash($_POST['social_1_url'])) : '',
            'social_2_name' => isset($_POST['social_2_name']) ? sanitize_text_field(wp_unslash($_POST['social_2_name'])) : '',
            'social_2_url' => isset($_POST['social_2_url']) ? esc_url_raw(wp_unslash($_POST['social_2_url'])) : '',
            'social_3_name' => isset($_POST['social_3_name']) ? sanitize_text_field(wp_unslash($_POST['social_3_name'])) : '',
            'social_3_url' => isset($_POST['social_3_url']) ? esc_url_raw(wp_unslash($_POST['social_3_url'])) : '',
            'social_4_name' => isset($_POST['social_4_name']) ? sanitize_text_field(wp_unslash($_POST['social_4_name'])) : '',
            'social_4_url' => isset($_POST['social_4_url']) ? esc_url_raw(wp_unslash($_POST['social_4_url'])) : '',
            'payment_1_name' => isset($_POST['payment_1_name']) ? sanitize_text_field(wp_unslash($_POST['payment_1_name'])) : '',
            'payment_1_url' => isset($_POST['payment_1_url']) ? self::sanitize_public_link(wp_unslash($_POST['payment_1_url'])) : '',
            'payment_2_name' => isset($_POST['payment_2_name']) ? sanitize_text_field(wp_unslash($_POST['payment_2_name'])) : '',
            'payment_2_url' => isset($_POST['payment_2_url']) ? self::sanitize_public_link(wp_unslash($_POST['payment_2_url'])) : '',
            'payment_3_name' => isset($_POST['payment_3_name']) ? sanitize_text_field(wp_unslash($_POST['payment_3_name'])) : '',
            'payment_3_url' => isset($_POST['payment_3_url']) ? self::sanitize_public_link(wp_unslash($_POST['payment_3_url'])) : '',
            'payment_4_name' => isset($_POST['payment_4_name']) ? sanitize_text_field(wp_unslash($_POST['payment_4_name'])) : '',
            'payment_4_url' => isset($_POST['payment_4_url']) ? self::sanitize_public_link(wp_unslash($_POST['payment_4_url'])) : '',
            'contact_1_name' => isset($_POST['contact_1_name']) ? sanitize_text_field(wp_unslash($_POST['contact_1_name'])) : '',
            'contact_1_url' => isset($_POST['contact_1_url']) ? self::sanitize_contact_link(wp_unslash($_POST['contact_1_url'])) : '',
            'contact_2_name' => isset($_POST['contact_2_name']) ? sanitize_text_field(wp_unslash($_POST['contact_2_name'])) : '',
            'contact_2_url' => isset($_POST['contact_2_url']) ? self::sanitize_contact_link(wp_unslash($_POST['contact_2_url'])) : '',
            'contact_3_name' => isset($_POST['contact_3_name']) ? sanitize_text_field(wp_unslash($_POST['contact_3_name'])) : '',
            'contact_3_url' => isset($_POST['contact_3_url']) ? self::sanitize_contact_link(wp_unslash($_POST['contact_3_url'])) : '',
            'contact_4_name' => isset($_POST['contact_4_name']) ? sanitize_text_field(wp_unslash($_POST['contact_4_name'])) : '',
            'contact_4_url' => isset($_POST['contact_4_url']) ? self::sanitize_contact_link(wp_unslash($_POST['contact_4_url'])) : '',
            'specialties' => isset($_POST['specialties']) ? sanitize_text_field(wp_unslash($_POST['specialties'])) : '',
            'experience' => isset($_POST['experience']) ? sanitize_text_field(wp_unslash($_POST['experience'])) : '',
            'status' => isset($_POST['status']) && $_POST['status'] === 'inactive' ? 'inactive' : 'active',
            'w9_status' => isset($_POST['w9_status']) ? sanitize_key($_POST['w9_status']) : 'not_received',
            'agreement_url' => isset($_POST['agreement_url']) ? esc_url_raw(wp_unslash($_POST['agreement_url'])) : '',
            'tax_document_url' => isset($_POST['tax_document_url']) ? esc_url_raw(wp_unslash($_POST['tax_document_url'])) : '',
            'announcement' => isset($_POST['announcement']) ? sanitize_textarea_field(wp_unslash($_POST['announcement'])) : '',
            'public_enabled' => isset($_POST['public_enabled']) ? 1 : 0,
            'profile_photo' => isset($_POST['profile_photo']) ? esc_url_raw(wp_unslash($_POST['profile_photo'])) : (string) ($profiles[$employee_id]['profile_photo'] ?? ''),
            'cover_image' => isset($_POST['cover_image']) ? esc_url_raw(wp_unslash($_POST['cover_image'])) : (string) ($profiles[$employee_id]['cover_image'] ?? ''),
            'gallery' => isset($_POST['gallery']) ? sanitize_textarea_field(wp_unslash($_POST['gallery'])) : (string) ($profiles[$employee_id]['gallery'] ?? ''),
            'booking_url' => isset($_POST['booking_url']) ? esc_url_raw(wp_unslash($_POST['booking_url'])) : '',
            'booking_destination' => isset($_POST['booking_destination']) && in_array($_POST['booking_destination'], ['category', 'custom', 'appointments'], true) ? sanitize_key($_POST['booking_destination']) : 'category',
            'booking_button_label' => isset($_POST['booking_button_label']) ? sanitize_text_field(wp_unslash($_POST['booking_button_label'])) : 'Book Now with This Artist',
            'referral_percent' => isset($_POST['referral_percent']) ? max(0, min(100, (float) $_POST['referral_percent'])) : 0,
            'manual_upcoming_classes' => isset($_POST['manual_upcoming_classes']) ? sanitize_textarea_field(wp_unslash($_POST['manual_upcoming_classes'])) : '',
        ];

        $profiles[$employee_id]['profile_photo'] = self::process_artist_image_upload(
            'profile_photo_upload',
            (string) ($profiles[$employee_id]['profile_photo'] ?? ''),
            isset($_POST['remove_profile_photo'])
        );
        $profiles[$employee_id]['cover_image'] = self::process_artist_image_upload(
            'cover_image_upload',
            (string) ($profiles[$employee_id]['cover_image'] ?? ''),
            isset($_POST['remove_cover_image'])
        );

        update_option(self::OPTION_PROFILES, $profiles, false);

        // Artist pages are public-facing and may be cached by WordPress, LiteSpeed,
        // a CDN, or the browser. Purge the artist URL immediately so profile and
        // social-link edits appear as soon as the profile is saved.
        wp_cache_delete(self::OPTION_PROFILES, 'options');
        if (function_exists('clean_url_cache')) {
            clean_url_cache(self::public_artist_url($employee_id));
        }
        do_action('litespeed_purge_url', self::public_artist_url($employee_id));
        do_action('litespeed_purge_all');

        wp_safe_redirect(add_query_arg(['page'=>'elev8-artist-portal','artist_id'=>$employee_id,'message'=>'profile_saved'], admin_url('admin.php')));
        exit;
    }

    /**
     * Store an uploaded artist image in the WordPress Media Library.
     * Existing URL-based profiles remain fully supported.
     */
    private static function process_artist_image_upload(string $field, string $current_url, bool $remove): string {
        if ($remove) {
            return '';
        }

        if (empty($_FILES[$field]) || !is_array($_FILES[$field]) || (int) ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return esc_url_raw($current_url);
        }

        if ((int) ($_FILES[$field]['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return esc_url_raw($current_url);
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attachment_id = media_handle_upload($field, 0);
        if (is_wp_error($attachment_id)) {
            return esc_url_raw($current_url);
        }

        $url = wp_get_attachment_image_url((int) $attachment_id, 'full');
        return $url ? esc_url_raw($url) : esc_url_raw($current_url);
    }

    private static function employee_name(int $employee_id): string {
        foreach (self::get_employees() as $employee) {
            if ((int) $employee['id'] === $employee_id) {
                return trim(($employee['firstName'] ?? '') . ' ' . ($employee['lastName'] ?? ''));
            }
        }
        return 'Artist';
    }

    private static function artist_slug(int $employee_id): string {
        return sanitize_title(self::employee_name($employee_id));
    }

    private static function employee_id_from_slug(string $slug): int {
        foreach (self::get_employees() as $employee) {
            $id = (int) $employee['id'];
            if (self::artist_slug($id) === sanitize_title($slug)) return $id;
        }
        return 0;
    }

    public static function add_rewrite_rules(): void {
        add_rewrite_rule('^artists/([^/]+)/?$', 'index.php?elev8_artist=$matches[1]', 'top');
        add_rewrite_rule('^artwork/([0-9]+)/?([^/]*)/?$', 'index.php?elev8_asset=$matches[1]', 'top');
    }

    public static function maybe_flush_rewrite_rules(): void {
        $version = (string) get_option('elev8_os_rewrite_version', '');
        if ($version !== ELEV8_OS_VERSION) {
            flush_rewrite_rules(false);
            update_option('elev8_os_rewrite_version', ELEV8_OS_VERSION, false);
        }
    }

    public static function query_vars(array $vars): array {
        $vars[] = 'elev8_artist';
        $vars[] = 'elev8_asset';
        return $vars;
    }

    public static function front_assets(): void {
        if (get_query_var('elev8_artist') || get_query_var('elev8_asset') || has_shortcode((string) get_post_field('post_content', get_queried_object_id()), 'elev8_artist_profile')) {
            wp_enqueue_style('elev8-os-admin', ELEV8_OS_URL . 'assets/css/admin.css', [], ELEV8_OS_VERSION);
        }
    }

    private static function get_referrals(): array {
        $data = get_option(self::OPTION_REFERRALS, ['clicks'=>[], 'conversions'=>[]]);
        if (!is_array($data)) $data = [];
        $data['clicks'] = isset($data['clicks']) && is_array($data['clicks']) ? $data['clicks'] : [];
        $data['conversions'] = isset($data['conversions']) && is_array($data['conversions']) ? $data['conversions'] : [];
        return $data;
    }

    public static function capture_referral(): void {
        $slug = isset($_GET['elev8_ref']) ? sanitize_title(wp_unslash($_GET['elev8_ref'])) : '';
        if (!$slug) return;
        $employee_id = self::employee_id_from_slug($slug);
        if (!$employee_id) return;
        $profiles = self::get_profiles();
        if (empty($profiles[$employee_id]['public_enabled'])) return;
        setcookie('elev8_ref', $slug, time() + DAY_IN_SECONDS * 30, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true);
        $_COOKIE['elev8_ref'] = $slug;
        $referrals = self::get_referrals();
        $referrals['clicks'][] = ['artist_id'=>$employee_id, 'time'=>current_time('mysql'), 'url'=>esc_url_raw(home_url(add_query_arg([], $GLOBALS['wp']->request ?? '')))];
        if (count($referrals['clicks']) > 5000) $referrals['clicks'] = array_slice($referrals['clicks'], -5000);
        update_option(self::OPTION_REFERRALS, $referrals, false);
    }

    public static function record_woocommerce_referral($order_id): void {
        if (!$order_id || empty($_COOKIE['elev8_ref']) || !function_exists('wc_get_order')) return;
        $order = wc_get_order($order_id);
        if (!$order || $order->get_meta('_elev8_referral_recorded')) return;
        $slug = sanitize_title(wp_unslash($_COOKIE['elev8_ref']));
        $employee_id = self::employee_id_from_slug($slug);
        if (!$employee_id) return;
        $profiles = self::get_profiles();
        $percent = (float) ($profiles[$employee_id]['referral_percent'] ?? 0);
        if ($percent <= 0) return;
        $total = (float) $order->get_total();
        $commission = round($total * ($percent / 100), 2);
        $referrals = self::get_referrals();
        $referrals['conversions'][] = ['artist_id'=>$employee_id, 'time'=>current_time('mysql'), 'order_id'=>(int)$order_id, 'sale_total'=>$total, 'commission'=>$commission];
        update_option(self::OPTION_REFERRALS, $referrals, false);
        $order->update_meta_data('_elev8_referral_recorded', 1);
        $order->update_meta_data('_elev8_referral_artist_id', $employee_id);
        $order->update_meta_data('_elev8_referral_commission', $commission);
        $order->save();
    }

    private static function referral_totals(int $employee_id): array {
        $data = self::get_referrals(); $clicks = 0; $sales = 0.0; $commission = 0.0;
        foreach ($data['clicks'] as $row) if ((int)($row['artist_id'] ?? 0) === $employee_id) $clicks++;
        foreach ($data['conversions'] as $row) if ((int)($row['artist_id'] ?? 0) === $employee_id) { $sales += (float)($row['sale_total'] ?? 0); $commission += (float)($row['commission'] ?? 0); }
        return compact('clicks','sales','commission');
    }

    private static function public_artist_url(int $employee_id, bool $referral=false): string {
        $url = home_url('/artists/' . self::artist_slug($employee_id) . '/');
        return $referral ? add_query_arg('elev8_ref', self::artist_slug($employee_id), $url) : $url;
    }

    private static function sanitize_public_link(string $value): string {
        $value = trim($value);
        if ($value === '') return '';
        if (preg_match('/^(mailto:|tel:)/i', $value)) {
            return sanitize_text_field($value);
        }
        if (!preg_match('#^https?://#i', $value)) {
            $value = 'https://' . ltrim($value, '/');
        }
        return esc_url_raw($value, ['http', 'https']);
    }

    private static function sanitize_contact_link(string $value): string {
        $value = trim($value);
        if ($value === '') return '';

        // Remove a browser-added web prefix before detecting an email or phone.
        $detect = preg_replace('#^https?://#i', '', $value);
        $detect = trim((string) $detect);

        if (preg_match('/^mailto:/i', $detect)) {
            $email = sanitize_email(substr($detect, 7));
            return $email !== '' ? 'mailto:' . $email : '';
        }
        if (is_email($detect)) {
            return 'mailto:' . sanitize_email($detect);
        }

        if (preg_match('/^tel:/i', $detect)) {
            $detect = substr($detect, 4);
        }
        $phone = preg_replace('/[^0-9+]/', '', $detect);
        $digits = preg_replace('/\D/', '', (string) $phone);
        if (strlen((string) $digits) >= 7 && strlen((string) $digits) <= 15) {
            return 'tel:' . $phone;
        }

        return self::sanitize_public_link($value);
    }

    private static function named_links(array $profile, string $prefix, string $fallback_label): array {
        $links = [];
        for ($i = 1; $i <= 4; $i++) {
            $url = trim((string) ($profile[$prefix . '_' . $i . '_url'] ?? ''));
            $name = trim((string) ($profile[$prefix . '_' . $i . '_name'] ?? ''));
            if ($prefix === 'contact' && $url !== '') {
                $url = self::sanitize_contact_link($url);
            }
            if ($url !== '') {
                $links[] = ['name' => $name !== '' ? $name : $fallback_label . ' ' . $i, 'url' => $url];
            }
        }
        return $links;
    }

    private static function social_links(array $profile): array {
        $links = [];
        for ($i = 1; $i <= 4; $i++) {
            $url = trim((string) ($profile['social_' . $i . '_url'] ?? ''));
            $name = trim((string) ($profile['social_' . $i . '_name'] ?? ''));
            if ($url !== '') {
                $links[] = ['name' => $name !== '' ? $name : 'Social link ' . $i, 'url' => $url];
            }
        }
        // Keep profiles made in earlier versions working.
        if (!$links && !empty($profile['social'])) {
            $links[] = ['name' => 'Social media', 'url' => (string) $profile['social']];
        }
        return $links;
    }

    private static function public_profile_content(int $employee_id): string {
        $profiles = self::get_profiles(); $profile = $profiles[$employee_id] ?? [];
        if (empty($profile['public_enabled']) || ($profile['status'] ?? 'active') !== 'active') return '<div class="elev8-empty">This artist profile is not public.</div>';
        $name = self::employee_name($employee_id); $upcoming = self::upcoming_classes($employee_id); $socials = self::social_links($profile); $payments = self::named_links($profile, 'payment', 'Payment'); $contacts = self::named_links($profile, 'contact', 'Contact');
        $booking_url = trim((string) ($profile['booking_url'] ?? ''));
        $booking_label = trim((string) ($profile['booking_button_label'] ?? ''));
        if ($booking_label === '') $booking_label = 'Book Now with This Artist';
        $owner_user_id = Elev8_OS_Identity_Service::user_id_for_artist($employee_id);
        if ($owner_user_id <= 0) {
            $owner_user_id = absint($profile['wp_user_id'] ?? 0);
        }
        $store_assets = $owner_user_id > 0 ? Elev8_OS_Asset_Service::get_public_for_owner($owner_user_id) : [];
        ob_start(); ?>
        <div class="elev8-os elev8-public-profile">
          <?php if(!empty($profile['cover_image'])):?><div class="elev8-cover" style="background-image:url('<?php echo esc_url($profile['cover_image']);?>')"></div><?php endif;?>
          <div class="elev8-public-head"><?php if(!empty($profile['profile_photo'])):?><img src="<?php echo esc_url($profile['profile_photo']);?>" alt="<?php echo esc_attr($name);?>"><?php endif;?><div><h1><?php echo esc_html($name);?></h1><p><?php echo esc_html($profile['medium']??'Elev8 Member Artist');?></p></div></div>
          <div class="elev8-grid"><div class="elev8-panel"><h2>About the artist</h2><p><?php echo nl2br(esc_html($profile['bio']??''));?></p><p><strong>Specialties:</strong> <?php echo esc_html($profile['specialties']??'');?></p><p><strong>Experience:</strong> <?php echo esc_html($profile['experience']??'');?></p></div><div class="elev8-panel"><h2>Connect</h2><?php if(!empty($profile['website'])):?><p><a href="<?php echo esc_url($profile['website']);?>" target="_blank" rel="noopener">Artist website</a></p><?php endif;?><?php foreach($socials as $social):?><p><a href="<?php echo esc_url($social['url']);?>" target="_blank" rel="noopener"><?php echo esc_html($social['name']);?></a></p><?php endforeach;?><?php if($booking_url!==''):?><p><a class="button button-primary" href="<?php echo esc_url(add_query_arg('elev8_ref',self::artist_slug($employee_id),$booking_url));?>"><?php echo esc_html($booking_label);?></a></p><?php endif;?><p class="description">Bookings made from this page are automatically credited to this artist when referral commissions are enabled.</p></div></div>
          <?php if($contacts || $payments):?><div class="elev8-grid elev8-public-links"><?php if($contacts):?><div class="elev8-panel elev8-contact-panel"><h2>Contact</h2><?php foreach($contacts as $contact): $contact_url=(string)$contact['url']; $is_email=(bool)preg_match('/^mailto:/i',$contact_url); $is_phone=(bool)preg_match('/^tel:/i',$contact_url); $copy_value=$is_email?substr($contact_url,7):($is_phone?substr($contact_url,4):$contact_url);?><div class="elev8-contact-action"><a class="elev8-contact-primary" href="<?php echo esc_url($contact_url, ['http','https','mailto','tel']);?>"<?php echo preg_match('/^https?:/i',$contact_url)?' target="_blank" rel="noopener"':'';?>><?php echo esc_html($contact['name']);?></a><?php if($is_email||$is_phone):?><button type="button" class="elev8-copy-contact" data-copy="<?php echo esc_attr($copy_value);?>" aria-label="<?php echo esc_attr(sprintf('Copy %s', $contact['name']));?>">Copy</button><?php endif;?></div><?php endforeach;?><p class="elev8-contact-help">Email opens your default mail app. Phone opens your device's calling app. Use Copy when no app is configured.</p></div><?php endif;?><?php if($payments):?><div class="elev8-panel"><h2>Payments & Support</h2><?php foreach($payments as $payment):?><p><a class="button" href="<?php echo esc_url($payment['url'], ['http','https','mailto','tel']);?>" target="_blank" rel="noopener"><?php echo esc_html($payment['name']);?></a></p><?php endforeach;?></div><?php endif;?></div><?php endif;?>
          <div class="elev8-share-tools"><button type="button" class="button elev8-copy-link" data-link="<?php echo esc_attr(self::public_artist_url($employee_id,true));?>">Copy page link</button><button type="button" class="button elev8-open-qr" data-qr="<?php echo esc_attr(Elev8_OS_Print_Service::qr_image_url(self::public_artist_url($employee_id,true),320));?>" data-name="<?php echo esc_attr($name);?>">Open QR code</button></div>
          <div class="elev8-qr-modal" hidden><div class="elev8-qr-dialog"><button type="button" class="elev8-close-qr" aria-label="Close QR code">&times;</button><h2>Scan to visit <?php echo esc_html($name);?></h2><img alt="QR code for <?php echo esc_attr($name);?>"><p>This QR code includes the artist's referral tracking link.</p></div></div>
          <?php if($store_assets):?><section class="elev8-panel elev8-artist-store"><?php $store_cart_url=Elev8_OS_WooCommerce::get_cart_url(); $store_checkout_url=Elev8_OS_WooCommerce::get_checkout_url();?><div class="elev8-store-heading"><div><p class="elev8-store-eyebrow">Available at Elev8 Arts</p><h2>Artwork &amp; Products</h2><p>Purchase online and Elev8 Arts will remove the item from display, pack it, and prepare it for pickup or delivery.</p></div><?php if($store_cart_url!==''):?><div class="elev8-store-cart-links"><a class="button elev8-view-cart-button" href="<?php echo esc_url($store_cart_url);?>">View Cart</a><?php if($store_checkout_url!==''):?><a class="button button-primary elev8-checkout-link" href="<?php echo esc_url($store_checkout_url);?>">Checkout</a><?php endif;?></div><?php endif;?></div><div class="elev8-store-grid"><?php foreach($store_assets as $asset): $image=wp_get_attachment_image_url((int)$asset['image_attachment_id'],'large'); $status=(string)$asset['status']; $is_sold=($status==='sold'); $is_reserved=($status==='reserved'); $purchase=Elev8_OS_WooCommerce::get_purchase_data($asset); $product_url=(string)$purchase['url']; $asset_url=Elev8_OS_Asset_Service::get_public_url($asset);?><article class="elev8-store-card<?php echo $is_sold?' is-sold':($is_reserved?' is-reserved':'');?>"><?php if($asset_url!==''):?><a class="elev8-store-image-link" href="<?php echo esc_url($asset_url);?>"><?php endif;?><div class="elev8-store-image"><?php if($image):?><img src="<?php echo esc_url($image);?>" alt="<?php echo esc_attr((string)$asset['title']);?>"><?php else:?><div class="elev8-store-image-missing">Image unavailable</div><?php endif;?><?php if($is_sold):?><span class="elev8-store-badge">Sold</span><?php elseif($is_reserved):?><span class="elev8-store-badge">Reserved</span><?php endif;?></div><?php if($asset_url!==''):?></a><?php endif;?><div class="elev8-store-body"><div class="elev8-store-title-row"><h3><?php if($asset_url!==''):?><a href="<?php echo esc_url($asset_url);?>"><?php endif;?><?php echo esc_html((string)$asset['title']);?><?php if($asset_url!==''):?></a><?php endif;?></h3><strong><?php echo $asset['price']===null?'Price unavailable':esc_html('$'.number_format_i18n((float)$asset['price'],2));?></strong></div><?php $meta=array_filter([(string)$asset['medium'],(string)$asset['dimensions']]); if($meta):?><p class="elev8-store-meta"><?php echo esc_html(implode(' · ',$meta));?></p><?php endif;?><?php if((string)$asset['description']!==''):?><p><?php echo esc_html(wp_trim_words((string)$asset['description'],32));?></p><?php endif;?><div class="elev8-store-actions"><?php if(!empty($purchase['purchasable'])):?><a class="button button-primary elev8-buy-button" href="<?php echo esc_url((string)$purchase['add_to_cart']);?>">Add to cart</a><?php if($asset_url!==''):?><a class="elev8-details-link" href="<?php echo esc_url($asset_url);?>">View details</a><?php endif;?><?php elseif($is_reserved):?><span class="elev8-unavailable-label">Currently reserved</span><?php elseif($is_sold):?><span class="elev8-unavailable-label">This item has sold</span><?php elseif($contacts): $contact=$contacts[0];?><a class="button" href="<?php echo esc_url($contact['url'],['http','https','mailto','tel']);?>"<?php echo preg_match('/^https?:/i',$contact['url'])?' target="_blank" rel="noopener"':'';?>>Ask about this item</a><?php else:?><span class="elev8-unavailable-label">Online purchase unavailable</span><?php endif;?></div></div></article><?php endforeach;?></div></section><?php endif;?>
          <div class="elev8-panel elev8-book-artist"><h2>Book with <?php echo esc_html($name); ?></h2><p>See this artist's available classes, dates, and booking options.</p><?php if($booking_url!==''):?><p class="elev8-booking-cta"><a class="button button-primary" href="<?php echo esc_url(add_query_arg('elev8_ref',self::artist_slug($employee_id),$booking_url));?>"><?php echo esc_html($booking_label);?></a></p><?php elseif(current_user_can('manage_options')):?><div class="elev8-empty">Admin: add a Booking Page URL in Elev8 OS → Artist Portal so customers can book with this artist.</div><?php endif;?></div>
          <script>(function(){var root=document.currentScript.closest('.elev8-public-profile');if(!root)return;function copyText(value,button,original){if(navigator.clipboard&&window.isSecureContext){navigator.clipboard.writeText(value).then(function(){button.textContent='Copied!';setTimeout(function(){button.textContent=original;},1500);});}else{window.prompt('Copy this contact information:',value);}}var copy=root.querySelector('.elev8-copy-link');if(copy)copy.addEventListener('click',function(){copyText(this.getAttribute('data-link'),this,'Copy page link');});root.querySelectorAll('.elev8-copy-contact').forEach(function(button){button.addEventListener('click',function(){copyText(this.getAttribute('data-copy'),this,'Copy');});});var open=root.querySelector('.elev8-open-qr'),modal=root.querySelector('.elev8-qr-modal'),close=root.querySelector('.elev8-close-qr');if(open&&modal){open.addEventListener('click',function(){var img=modal.querySelector('img');img.src=this.getAttribute('data-qr');modal.hidden=false;document.body.classList.add('elev8-qr-open');});}if(close&&modal)close.addEventListener('click',function(){modal.hidden=true;document.body.classList.remove('elev8-qr-open');});if(modal)modal.addEventListener('click',function(e){if(e.target===modal){modal.hidden=true;document.body.classList.remove('elev8-qr-open');}});})();</script>
        </div><?php return ob_get_clean();
    }

    private static function employee_id_from_owner_user(int $owner_user_id): int {
        if ($owner_user_id <= 0) return 0;

        $mapped_employee_id = Elev8_OS_Identity_Service::artist_id_for_user_id($owner_user_id);
        if ($mapped_employee_id > 0) return $mapped_employee_id;

        // Backward-compatible fallback for profiles created before Artist Mapping.
        foreach (self::get_profiles() as $employee_id => $profile) {
            if (absint($profile['wp_user_id'] ?? 0) === $owner_user_id) return absint($employee_id);
        }
        return 0;
    }

    private static function public_asset_content(array $asset): string {
        $owner_user_id = absint($asset['owner_user_id'] ?? 0);
        $employee_id = self::employee_id_from_owner_user($owner_user_id);
        $profiles = self::get_profiles();
        $profile = $employee_id > 0 ? ($profiles[$employee_id] ?? []) : [];
        $artist_name = $employee_id > 0 ? self::employee_name($employee_id) : get_the_author_meta('display_name', $owner_user_id);
        $artist_url = $employee_id > 0 ? self::public_artist_url($employee_id, true) : '';
        $image = wp_get_attachment_image_url(absint($asset['image_attachment_id'] ?? 0), 'full');
        $gallery_ids = Elev8_OS_Asset_Service::get_gallery_attachment_ids($asset);
        $purchase = Elev8_OS_WooCommerce::get_purchase_data($asset);
        $status = sanitize_key((string) ($asset['status'] ?? 'draft'));
        $video_embed = !empty($asset['video_url']) ? wp_oembed_get((string) $asset['video_url'], ['width' => 1000]) : '';
        $more_assets = [];
        foreach (Elev8_OS_Asset_Service::get_public_for_owner($owner_user_id) as $candidate) {
            if (absint($candidate['id'] ?? 0) !== absint($asset['id'] ?? 0)) $more_assets[] = $candidate;
            if (count($more_assets) >= 3) break;
        }
        $share_url = Elev8_OS_Asset_Service::get_public_url($asset);
        $share_text = rawurlencode((string) $asset['title'] . ($artist_name !== '' ? ' by ' . $artist_name : ''));
        $documents = [
            'Certificate of Authenticity' => absint($asset['certificate_attachment_id'] ?? 0),
            'Care Instructions' => absint($asset['care_attachment_id'] ?? 0),
            'Specification Sheet' => absint($asset['spec_attachment_id'] ?? 0),
        ];
        ob_start(); ?>
        <div class="elev8-os elev8-asset-experience">
          <nav class="elev8-asset-nav"><?php if($artist_url!==''):?><a href="<?php echo esc_url($artist_url);?>">&larr; More from <?php echo esc_html($artist_name);?></a><?php endif;?><span><?php echo esc_html((string)$asset['asset_number']);?></span></nav>
          <section class="elev8-asset-hero">
            <div class="elev8-asset-media-column">
              <div class="elev8-asset-hero-image" data-elev8-hero><?php if($image):?><img src="<?php echo esc_url($image);?>" alt="<?php echo esc_attr((string)$asset['title']);?>"><?php else:?><div class="elev8-store-image-missing">Image unavailable</div><?php endif;?></div>
              <?php if($gallery_ids):?><div class="elev8-asset-thumbnails"><?php if($image):?><button type="button" class="is-active" data-image="<?php echo esc_url($image);?>"><img src="<?php echo esc_url((string)wp_get_attachment_image_url(absint($asset['image_attachment_id']),'thumbnail'));?>" alt="Primary image"></button><?php endif;?><?php foreach($gallery_ids as $gallery_id): $full=wp_get_attachment_image_url($gallery_id,'full'); $thumb=wp_get_attachment_image_url($gallery_id,'thumbnail'); if(!$full||!$thumb)continue;?><button type="button" data-image="<?php echo esc_url($full);?>"><img src="<?php echo esc_url($thumb);?>" alt="Additional view of <?php echo esc_attr((string)$asset['title']);?>"></button><?php endforeach;?></div><?php endif;?>
            </div>
            <div class="elev8-asset-purchase-panel">
              <p class="elev8-store-eyebrow"><?php echo !empty($asset['is_featured']) ? 'Featured at Elev8 Arts' : 'Available at Elev8 Arts';?></p>
              <h1><?php echo esc_html((string)$asset['title']);?></h1>
              <?php if($artist_name!==''):?><p class="elev8-asset-byline">By <?php if($artist_url!==''):?><a href="<?php echo esc_url($artist_url);?>"><?php endif;?><?php echo esc_html($artist_name);?><?php if($artist_url!==''):?></a><?php endif;?></p><?php endif;?>
              <p class="elev8-asset-price"><?php echo $asset['price']===null?'Price unavailable':esc_html('$'.number_format_i18n((float)$asset['price'],2));?></p>
              <dl class="elev8-asset-facts"><div><dt>Status</dt><dd><?php echo esc_html(ucfirst($status));?></dd></div><?php foreach(['medium'=>'Medium','materials'=>'Materials','dimensions'=>'Dimensions','year_created'=>'Year','collection_name'=>'Collection'] as $key=>$label): if((string)($asset[$key]??'')==='')continue;?><div><dt><?php echo esc_html($label);?></dt><dd><?php echo esc_html((string)$asset[$key]);?></dd></div><?php endforeach;?><div><dt>Asset number</dt><dd><?php echo esc_html((string)$asset['asset_number']);?></dd></div></dl>
              <div class="elev8-asset-buy-actions"><?php if(!empty($purchase['purchasable'])):?><a class="button button-primary elev8-asset-primary-buy" href="<?php echo esc_url((string)$purchase['add_to_cart']);?>">Add to cart</a><?php elseif($status==='reserved'):?><span class="elev8-unavailable-label">Currently reserved</span><?php elseif($status==='sold'):?><span class="elev8-unavailable-label">This item has sold</span><?php else:?><span class="elev8-unavailable-label">Online purchase unavailable</span><?php endif;?><?php if((string)$purchase['cart_url']!==''):?><a href="<?php echo esc_url((string)$purchase['cart_url']);?>">View cart</a><?php endif;?><?php $checkout=Elev8_OS_WooCommerce::get_checkout_url(); if($checkout!==''):?><a href="<?php echo esc_url($checkout);?>">Checkout</a><?php endif;?></div>
              <p class="elev8-asset-fulfillment">Purchase online and Elev8 Arts will remove the item from display, pack it, and prepare it for pickup or delivery.</p>
              <div class="elev8-asset-share"><strong>Share this piece</strong><a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo rawurlencode($share_url);?>" target="_blank" rel="noopener">Facebook</a><a href="https://www.pinterest.com/pin/create/button/?url=<?php echo rawurlencode($share_url);?>&description=<?php echo $share_text;?>" target="_blank" rel="noopener">Pinterest</a><button type="button" data-copy-url="<?php echo esc_attr($share_url);?>">Copy link</button></div>
            </div>
          </section>
          <?php if((string)$asset['special_story']!==''):?><section class="elev8-panel elev8-asset-special"><p class="elev8-store-eyebrow">What makes this piece special</p><blockquote><?php echo nl2br(esc_html((string)$asset['special_story']));?></blockquote></section><?php endif;?>
          <?php if((string)$asset['description']!=='' || (string)$asset['artwork_story']!==''):?><section class="elev8-panel elev8-asset-story"><h2>About this piece</h2><?php if((string)$asset['description']!==''):?><p><?php echo nl2br(esc_html((string)$asset['description']));?></p><?php endif;?><?php if((string)$asset['artwork_story']!==''):?><h3>The story behind the work</h3><p><?php echo nl2br(esc_html((string)$asset['artwork_story']));?></p><?php endif;?></section><?php endif;?>
          <?php if($video_embed):?><section class="elev8-panel elev8-asset-video"><h2>Watch the story or process</h2><div class="elev8-responsive-video"><?php echo wp_kses_post($video_embed);?></div></section><?php endif;?>
          <?php $has_docs=false; foreach($documents as $doc_id){if($doc_id>0&&wp_get_attachment_url($doc_id)){$has_docs=true;break;}} if($has_docs):?><section class="elev8-panel elev8-asset-documents"><h2>Artwork documents</h2><div><?php foreach($documents as $label=>$doc_id): $doc_url=$doc_id>0?wp_get_attachment_url($doc_id):''; if(!$doc_url)continue;?><a class="button" href="<?php echo esc_url($doc_url);?>" target="_blank" rel="noopener"><?php echo esc_html($label);?></a><?php endforeach;?></div></section><?php endif;?>
          <?php if((string)$asset['asset_tags']!==''):?><section class="elev8-asset-tags" aria-label="Artwork tags"><?php foreach(array_filter(array_map('trim',explode(',',(string)$asset['asset_tags']))) as $tag):?><span><?php echo esc_html($tag);?></span><?php endforeach;?></section><?php endif;?>
          <?php if($employee_id>0 && (!empty($profile['bio']) || !empty($profile['profile_photo']))):?><section class="elev8-panel elev8-asset-artist"><div><?php if(!empty($profile['profile_photo'])):?><img src="<?php echo esc_url((string)$profile['profile_photo']);?>" alt="<?php echo esc_attr($artist_name);?>"><?php endif;?></div><div><p class="elev8-store-eyebrow">Meet the artist</p><h2><?php echo esc_html($artist_name);?></h2><?php if(!empty($profile['bio'])):?><p><?php echo esc_html(wp_trim_words((string)$profile['bio'],70));?></p><?php endif;?><?php if($artist_url!==''):?><a href="<?php echo esc_url($artist_url);?>">View artist storefront</a><?php endif;?></div></section><?php endif;?>
          <?php if($more_assets):?><section class="elev8-panel elev8-asset-more"><h2>More from <?php echo esc_html($artist_name);?></h2><div class="elev8-store-grid"><?php foreach($more_assets as $more): $more_image=wp_get_attachment_image_url(absint($more['image_attachment_id']),'medium_large'); $more_url=Elev8_OS_Asset_Service::get_public_url($more);?><article class="elev8-store-card"><a class="elev8-store-image-link" href="<?php echo esc_url($more_url);?>"><div class="elev8-store-image"><?php if($more_image):?><img src="<?php echo esc_url($more_image);?>" alt="<?php echo esc_attr((string)$more['title']);?>"><?php else:?><div class="elev8-store-image-missing">Image unavailable</div><?php endif;?></div></a><div class="elev8-store-body"><div class="elev8-store-title-row"><h3><a href="<?php echo esc_url($more_url);?>"><?php echo esc_html((string)$more['title']);?></a></h3><strong><?php echo $more['price']===null?'Price unavailable':esc_html('$'.number_format_i18n((float)$more['price'],2));?></strong></div></div></article><?php endforeach;?></div></section><?php endif;?>
          <script>(function(){var root=document.currentScript.closest('.elev8-asset-experience');if(!root)return;var hero=root.querySelector('[data-elev8-hero] img');root.querySelectorAll('.elev8-asset-thumbnails button').forEach(function(btn){btn.addEventListener('click',function(){if(hero)hero.src=this.getAttribute('data-image');root.querySelectorAll('.elev8-asset-thumbnails button').forEach(function(b){b.classList.remove('is-active');});this.classList.add('is-active');});});var copy=root.querySelector('[data-copy-url]');if(copy)copy.addEventListener('click',function(){var url=this.getAttribute('data-copy-url');if(navigator.clipboard){navigator.clipboard.writeText(url).then(function(){copy.textContent='Copied!';setTimeout(function(){copy.textContent='Copy link';},1500);});}else{window.prompt('Copy this link:',url);}});})();</script>
        </div><?php return ob_get_clean();
    }

    public static function save_print_settings(): void {
        if (!current_user_can('manage_options')) { wp_die('You do not have permission to do this.'); }
        check_admin_referer('elev8_os_save_print_settings');
        Elev8_OS_Print_Service::save_settings([
            'background_url' => isset($_POST['background_url']) ? wp_unslash($_POST['background_url']) : '',
            'logo_url' => isset($_POST['logo_url']) ? wp_unslash($_POST['logo_url']) : '',
            'theme' => isset($_POST['theme']) ? wp_unslash($_POST['theme']) : 'classic',
            'instruction' => isset($_POST['instruction']) ? wp_unslash($_POST['instruction']) : '',
        ]);
        wp_safe_redirect(add_query_arg(['page'=>'elev8-print-identity','updated'=>'1'], admin_url('admin.php')));
        exit;
    }

    public static function render_print_identity_settings(): void {
        if (!current_user_can('manage_options')) { return; }
        $settings = Elev8_OS_Print_Service::get_settings();
        ?>
        <div class="wrap elev8-os elev8-print-settings"><h1>Print &amp; Identity System</h1><p>Set the standard visual identity used on every artist display card. Artist content always comes from the approved Elev8 artist profile.</p>
        <?php if(isset($_GET['updated'])):?><div class="notice notice-success is-dismissible"><p>Print identity settings saved.</p></div><?php endif;?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><input type="hidden" name="action" value="elev8_os_save_print_settings"><?php wp_nonce_field('elev8_os_save_print_settings'); ?>
        <div class="elev8-panel"><h2>Standard Artist Card Theme</h2><table class="form-table"><tr><th><label for="elev8-print-theme">Theme</label></th><td><select id="elev8-print-theme" name="theme"><option value="lavender" <?php selected($settings['theme'],'lavender');?>>Elev8 Lavender — signature artist identity</option><option value="minimal" <?php selected($settings['theme'],'minimal');?>>Elev8 Minimal — clean white</option><option value="ink" <?php selected($settings['theme'],'ink');?>>Ink Saver — black and white</option></select></td></tr>
        <tr><th><label for="elev8-background-url">Paisley background</label></th><td><input class="regular-text" id="elev8-background-url" name="background_url" value="<?php echo esc_attr($settings['background_url']);?>" type="url"><button type="button" class="button elev8-media-pick" data-target="elev8-background-url">Choose image</button><p class="description">Stored for backward compatibility. The official Elev8 print standard now uses a clean white background.</p></td></tr>
        <tr><th><label for="elev8-logo-url">Elev8 Arts logo</label></th><td><input class="regular-text" id="elev8-logo-url" name="logo_url" value="<?php echo esc_attr($settings['logo_url']);?>" type="url"><button type="button" class="button elev8-media-pick" data-target="elev8-logo-url">Choose image</button></td></tr>
        <tr><th><label for="elev8-instruction">QR instruction</label></th><td><input class="regular-text" id="elev8-instruction" name="instruction" value="<?php echo esc_attr($settings['instruction']);?>" type="text"></td></tr></table></div>
        <?php submit_button('Save Print Identity');?></form>
        <div class="elev8-panel"><h2>Available print templates</h2><p><strong>Artist Identity Displays:</strong> 8.5 × 5.5 feature card, 5 × 7 table display, and 3 × 1 small labels.</p><p><strong>Artist QR Code:</strong> large standalone QR page for labels and signs.</p><p><strong>Artwork Labels:</strong> 3 × 3 story labels and 3 × 1 compact labels, individually or on letter sheets.</p><p><strong>PDF:</strong> use Download / Save PDF and select “Save as PDF” in the browser print window.</p></div>
        <script>(function(){document.querySelectorAll('.elev8-media-pick').forEach(function(button){button.addEventListener('click',function(){var target=document.getElementById(this.dataset.target);var frame=wp.media({title:'Choose print identity image',button:{text:'Use this image'},multiple:false});frame.on('select',function(){var item=frame.state().get('selection').first().toJSON();target.value=item.url;});frame.open();});});})();</script></div><?php
    }

    public static function render_print_center(): void {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('You do not have permission to access the Print Center.', 'elev8-os')); }
        $profiles = self::get_profiles();
        $artists = [];
        foreach ($profiles as $employee_id => $profile) {
            if (($profile['status'] ?? 'active') !== 'active') { continue; }
            $artists[] = [
                'id' => absint($employee_id),
                'name' => self::employee_name(absint($employee_id)),
                'medium' => (string)($profile['medium'] ?? ''),
                'public' => !empty($profile['public_enabled']),
            ];
        }
        usort($artists, static fn(array $a,array $b): int => strcasecmp($a['name'],$b['name']));
        $assets = Elev8_OS_Asset_Service::get_all(5000);
        $print_assets = [];
        foreach ($assets as $asset) {
            $artist_id = Elev8_OS_Identity_Service::artist_id_for_user_id(absint($asset['owner_user_id'] ?? 0));
            if ($artist_id <= 0) { continue; }
            $print_assets[] = [
                'asset' => $asset,
                'artist_id' => $artist_id,
                'artist_name' => self::employee_name($artist_id),
            ];
        }
        usort($print_assets, static function(array $a, array $b): int {
            $artist_compare = strcasecmp((string)$a['artist_name'], (string)$b['artist_name']);
            if ($artist_compare !== 0) { return $artist_compare; }
            return strcasecmp((string)($a['asset']['title'] ?? ''), (string)($b['asset']['title'] ?? ''));
        });
        ?>
        <div class="wrap elev8-os elev8-print-center">
          <h1>Gallery Print Center</h1>
          <p class="description">Artists maintain their profiles and artwork records. Only an Elev8 OS administrator can preview, download, or print the approved gallery materials.</p>
          <div class="elev8-print-center-grid">
            <section class="elev8-panel">
              <h2>Artist Cards</h2>
              <p>Print a minimal half-sheet artist introduction or a large profile QR code.</p>
              <form method="get" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" target="_blank">
                <input type="hidden" name="action" value="elev8_os_print_artist">
                <?php wp_nonce_field('elev8_os_print_artist', '_wpnonce', false); ?>
                <label><strong>Choose artist</strong><select name="artist_id" required><option value="">Select an artist…</option><?php foreach($artists as $artist): ?><option value="<?php echo esc_attr((string)$artist['id']); ?>"<?php disabled(!$artist['public']); ?>><?php echo esc_html($artist['name'] . ($artist['medium'] !== '' ? ' — '.$artist['medium'] : '') . (!$artist['public'] ? ' — profile not public' : '')); ?></option><?php endforeach; ?></select></label>
                <label><strong>Print format</strong><select name="print_format"><option value="artist-card">Artist display card — 8.5 × 5.5</option><option value="artist-card-two">Two cards — letter sheet</option><option value="artist-qr">Artist QR code only</option></select></label>
                <button class="button button-primary" type="submit">Preview Artist Print</button>
              </form>
            </section>
            <section class="elev8-panel">
              <h2>Artwork Gallery Labels</h2>
              <p>Print the artwork title, artist name, details, and a tracked QR code that opens the artwork page.</p>
              <form method="get" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" target="_blank" data-elev8-artwork-print-form>
                <input type="hidden" name="action" value="elev8_os_print_artwork">
                <?php wp_nonce_field('elev8_os_print_artwork', '_wpnonce', false); ?>
                <label><strong>Choose artist</strong><select name="artist_id" required data-elev8-print-artist><option value="">Select an artist…</option><?php foreach($artists as $artist): ?><option value="<?php echo esc_attr((string)$artist['id']); ?>"><?php echo esc_html($artist['name'] . ($artist['medium'] !== '' ? ' — '.$artist['medium'] : '')); ?></option><?php endforeach; ?></select></label>
                <label><strong>Choose artwork</strong><select name="asset_id" required data-elev8-print-artwork disabled><option value="">Select an artist first…</option><?php foreach($print_assets as $item): $asset=$item['asset']; ?><option value="<?php echo esc_attr((string)$asset['id']); ?>" data-artist-id="<?php echo esc_attr((string)$item['artist_id']); ?>" hidden><?php echo esc_html((string)$asset['title'].' — '.ucfirst((string)$asset['status'])); ?></option><?php endforeach; ?></select><span class="description elev8-print-artwork-message" data-elev8-print-artwork-message>Select an artist to see only their artwork.</span></label>
                <label><strong>Print format</strong><select name="print_format"><option value="artwork-label">Artwork QR label — 3 × 3</option><option value="artwork-label-two">Two 3 × 3 labels — letter sheet</option><option value="artwork-qr">Artwork QR label — 3 × 3</option></select></label>
                <button class="button button-primary" type="submit" data-elev8-print-artwork-submit disabled>Preview Artwork Print</button>
              </form>
            </section>
          </div>
          <div class="elev8-panel"><h2>Consistent Gallery Workflow</h2><p><strong>Artist:</strong> completes the profile, biography, artwork title, story, materials, dimensions, price, and image.</p><p><strong>Owner:</strong> reviews the information, opens Print Center, previews the branded result, and prints it.</p><p>Templates are controlled by Elev8 OS so every label and artist card has the same Elev8 Arts identity.</p></div>
          <script>(function(){var form=document.querySelector('[data-elev8-artwork-print-form]');if(!form)return;var artist=form.querySelector('[data-elev8-print-artist]');var artwork=form.querySelector('[data-elev8-print-artwork]');var message=form.querySelector('[data-elev8-print-artwork-message]');var submit=form.querySelector('[data-elev8-print-artwork-submit]');var options=Array.prototype.slice.call(artwork.querySelectorAll('option[data-artist-id]'));function refresh(){var artistId=artist.value;var count=0;artwork.value='';options.forEach(function(option){var match=artistId!==''&&option.getAttribute('data-artist-id')===artistId;option.hidden=!match;option.disabled=!match;if(match)count++;});if(artistId===''){artwork.options[0].text='Select an artist first…';artwork.disabled=true;submit.disabled=true;message.textContent='Select an artist to see only their artwork.';return;}if(count===0){artwork.options[0].text='No artwork found for this artist';artwork.disabled=true;submit.disabled=true;message.textContent='This artist has no artwork available to print yet.';return;}artwork.options[0].text='Select artwork…';artwork.disabled=false;submit.disabled=true;message.textContent=count+' artwork '+(count===1?'item':'items')+' found for this artist.';}artist.addEventListener('change',refresh);artwork.addEventListener('change',function(){submit.disabled=this.value==='';});refresh();})();</script>
        </div><?php
    }

    public static function print_artist_action(): void {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('Only an Elev8 OS administrator can print artist materials.', 'elev8-os')); }
        check_admin_referer('elev8_os_print_artist');
        $employee_id = absint($_GET['artist_id'] ?? 0);
        $format = sanitize_key((string)($_GET['print_format'] ?? 'artist-card'));
        $profiles = self::get_profiles(); $profile = $profiles[$employee_id] ?? [];
        if ($employee_id <= 0 || empty($profile['public_enabled']) || ($profile['status'] ?? 'active') !== 'active') { wp_die(esc_html__('Choose an active public artist profile.', 'elev8-os')); }
        $print_bio = trim((string) ($profile['bio'] ?? ''));
        if ($print_bio === '') {
            $print_bio = trim((string) ($profile['short_description'] ?? $profile['description'] ?? ''));
        }
        if ($print_bio === '' && !empty($profile['wp_user_id'])) {
            $print_user = get_userdata(absint($profile['wp_user_id']));
            if ($print_user) { $print_bio = trim((string) $print_user->description); }
        }
        if ($print_bio === '') {
            $print_bio = trim(implode(' ', array_filter([(string) ($profile['specialties'] ?? ''), (string) ($profile['experience'] ?? '')])));
        }
        Elev8_OS_Print_Service::render([
            'name'=>self::employee_name($employee_id),'bio'=>$print_bio,'medium'=>(string)($profile['medium']??''),'photo'=>(string)($profile['profile_photo']??''),
            'profile_url'=>self::public_artist_url($employee_id,true),'canonical_url'=>admin_url('admin.php?page=elev8-print-center'),
        ], $format==='artist-qr'?'qr':'artist-card', $format==='artist-card-two');
    }

    public static function print_artwork_action(): void {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('Only an Elev8 OS administrator can print artwork materials.', 'elev8-os')); }
        check_admin_referer('elev8_os_print_artwork');
        $selected_artist_id = absint($_GET['artist_id'] ?? 0);
        $asset = Elev8_OS_Asset_Service::get(absint($_GET['asset_id'] ?? 0));
        if (!$asset) { wp_die(esc_html__('Artwork could not be found.', 'elev8-os')); }
        $artist_id = Elev8_OS_Identity_Service::artist_id_for_user_id(absint($asset['owner_user_id'] ?? 0));
        if ($selected_artist_id <= 0 || $artist_id !== $selected_artist_id) {
            wp_die(esc_html__('The selected artwork does not belong to the selected artist. Return to Print Center and choose the artist again.', 'elev8-os'));
        }
        $artist_name = self::employee_name($artist_id);
        $format = sanitize_key((string)($_GET['print_format'] ?? 'artwork-label'));
        Elev8_OS_Print_Service::render_artwork($asset, $artist_name, $format, admin_url('admin.php?page=elev8-print-center'));
    }

    public static function render_print_route(): void {
        $mode = isset($_GET['elev8_print']) ? sanitize_key(wp_unslash($_GET['elev8_print'])) : '';
        if (!in_array($mode, ['artist-card','qr'], true)) { return; }
        $slug = get_query_var('elev8_artist');
        if (!$slug) { return; }
        $employee_id = self::employee_id_from_slug((string)$slug);
        if (!$employee_id) { return; }
        if (!is_user_logged_in()) {
            auth_redirect();
            exit;
        }
        $can_print = current_user_can('manage_options');
        if (!$can_print) {
            $mapped_artist_id = Elev8_OS_Identity_Service::artist_id_for_user_id(get_current_user_id());
            $can_print = $mapped_artist_id > 0 && $mapped_artist_id === $employee_id;
        }
        if (!$can_print) {
            wp_die(esc_html__('You may only print materials for your own artist profile.', 'elev8-os'), 403);
        }
        $profiles = self::get_profiles();
        $profile = $profiles[$employee_id] ?? [];
        if (empty($profile['public_enabled']) || ($profile['status'] ?? 'active') !== 'active') { return; }
        $canonical = self::public_artist_url($employee_id, false);
        $print_bio = trim((string) ($profile['bio'] ?? ''));
        if ($print_bio === '') { $print_bio = trim((string) ($profile['short_description'] ?? $profile['description'] ?? '')); }
        if ($print_bio === '' && !empty($profile['wp_user_id'])) {
            $print_user = get_userdata(absint($profile['wp_user_id']));
            if ($print_user) { $print_bio = trim((string) $print_user->description); }
        }
        if ($print_bio === '') { $print_bio = trim(implode(' ', array_filter([(string) ($profile['specialties'] ?? ''), (string) ($profile['experience'] ?? '')]))); }
        Elev8_OS_Print_Service::render([
            'name' => self::employee_name($employee_id),
            'bio' => $print_bio,
            'medium' => (string)($profile['medium'] ?? ''),
            'photo' => (string)($profile['profile_photo'] ?? ''),
            'profile_url' => self::public_artist_url($employee_id, true),
            'canonical_url' => $canonical,
        ], $mode, isset($_GET['elev8_two_up']) && '1' === sanitize_text_field(wp_unslash($_GET['elev8_two_up'])));
    }

    public static function render_asset_route(): void {
        $asset_id = absint(get_query_var('elev8_asset'));
        if ($asset_id <= 0) return;
        $asset = Elev8_OS_Asset_Service::get($asset_id);
        $preview_nonce = sanitize_text_field(wp_unslash((string) ($_GET['elev8_preview'] ?? '')));
        $can_preview = $asset && is_user_logged_in() && absint($asset['owner_user_id'] ?? 0) === get_current_user_id() && wp_verify_nonce($preview_nonce, 'elev8_asset_preview_' . $asset_id);
        $is_public = $asset && !empty($asset['public_visibility']) && in_array((string)$asset['status'], ['available','reserved','sold'], true);
        if (!$is_public && !$can_preview) {
            global $wp_query; $wp_query->set_404(); status_header(404); return;
        }
        if ($is_public && !$can_preview) Elev8_OS_Asset_Service::record_public_view($asset_id, isset($_GET['elev8_qr']) && '1' === sanitize_text_field(wp_unslash((string)$_GET['elev8_qr'])));
        status_header(200); nocache_headers();
        wp_enqueue_style('elev8-os-admin', ELEV8_OS_URL . 'assets/css/admin.css', [], ELEV8_OS_VERSION);
        $seo_title = (string) $asset['title'];
        $seo_description = wp_trim_words((string) (($asset['special_story'] ?? '') ?: ($asset['description'] ?? '') ?: ($asset['artwork_story'] ?? '')), 28, '');
        $seo_image = wp_get_attachment_image_url(absint($asset['image_attachment_id'] ?? 0), 'large');
        $seo_url = Elev8_OS_Asset_Service::get_public_url($asset);
        add_filter('pre_get_document_title', static function () use ($seo_title): string { return $seo_title . ' | Elev8 Arts'; });
        add_action('wp_head', static function () use ($seo_title, $seo_description, $seo_image, $seo_url): void {
            if ($seo_description !== '') echo '<meta name="description" content="' . esc_attr($seo_description) . '">' . "\n";
            echo '<meta property="og:type" content="product"><meta property="og:title" content="' . esc_attr($seo_title) . '"><meta property="og:url" content="' . esc_url($seo_url) . '">' . "\n";
            if ($seo_description !== '') echo '<meta property="og:description" content="' . esc_attr($seo_description) . '">' . "\n";
            if ($seo_image) echo '<meta property="og:image" content="' . esc_url($seo_image) . '">' . "\n";
        }, 2);
        get_header();
        echo '<main class="elev8-public-page elev8-public-asset-page">' . self::public_asset_content($asset) . '</main>';
        get_footer(); exit;
    }

    public static function render_public_route(): void {
        $slug = get_query_var('elev8_artist');
        if (!$slug) return;
        $employee_id = self::employee_id_from_slug((string)$slug);
        if (!$employee_id) {
            global $wp_query; $wp_query->set_404(); status_header(404); return;
        }
        status_header(200);
        nocache_headers();
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        do_action('litespeed_control_set_nocache', 'Elev8 artist profile');
        wp_enqueue_style('elev8-os-admin', ELEV8_OS_URL . 'assets/css/admin.css', [], ELEV8_OS_VERSION);
        get_header();
        echo '<main class="elev8-public-page">' . self::public_profile_content($employee_id) . '</main>';
        get_footer();
        exit;
    }

    public static function artist_profile_shortcode(array $atts=[]): string {
        $atts = shortcode_atts(['artist'=>''], $atts, 'elev8_artist_profile');
        $slug = $atts['artist'] ?: get_query_var('elev8_artist');
        $employee_id = self::employee_id_from_slug((string)$slug);
        if (!$employee_id) return '<div class="elev8-empty">Artist not found.</div>';
        wp_enqueue_style('elev8-os-admin', ELEV8_OS_URL . 'assets/css/admin.css', [], ELEV8_OS_VERSION);
        return self::public_profile_content($employee_id);
    }

    private static function get_payouts(): array {
        $payouts = get_option(self::OPTION_PAYOUTS, []);
        return is_array($payouts) ? $payouts : [];
    }

    public static function save_payout(): void {
        if (!current_user_can('manage_options')) wp_die('You do not have permission to do this.');
        check_admin_referer('elev8_os_save_payout');
        $employee_id = isset($_POST['employee_id']) ? absint($_POST['employee_id']) : 0;
        $month = isset($_POST['payout_month']) ? sanitize_text_field(wp_unslash($_POST['payout_month'])) : '';
        if (!$employee_id || !preg_match('/^\d{4}-\d{2}$/', $month)) wp_die('Artist and payout month are required.');
        $payouts = self::get_payouts();
        $payouts[$employee_id][$month] = [
            'status' => isset($_POST['payout_status']) && $_POST['payout_status'] === 'paid' ? 'paid' : 'unpaid',
            'paid_date' => isset($_POST['paid_date']) ? sanitize_text_field(wp_unslash($_POST['paid_date'])) : '',
            'note' => isset($_POST['payout_note']) ? sanitize_text_field(wp_unslash($_POST['payout_note'])) : '',
        ];
        update_option(self::OPTION_PAYOUTS, $payouts, false);
        wp_safe_redirect(add_query_arg(['page'=>'elev8-artist-portal','artist_id'=>$employee_id,'elev8_month'=>$month,'message'=>'payout_saved'], admin_url('admin.php')));
        exit;
    }

    private static function classes_for_artist(int $employee_id, string $month = ''): array {
        if ($month && preg_match('/^\d{4}-\d{2}$/', $month)) {
            $_GET['elev8_month'] = $month;
        }
        $range = self::date_range();
        $classes = self::normalize_rows(self::get_monthly_rows($range));
        return array_values(array_filter($classes, static fn($c) => (int) $c['employee_id'] === $employee_id));
    }

    private static function first_existing_column(string $table, array $candidates): string {
        foreach ($candidates as $column) {
            if (self::column_exists($table, $column)) return $column;
        }
        return '';
    }

    private static function amelia_event_provider_tables(): array {
        global $wpdb;
        $like = $wpdb->esc_like($wpdb->prefix . 'amelia_') . '%';
        $tables = $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $like)) ?: [];
        $preferred = [
            self::table('events_providers'),
            self::table('providers_to_events'),
            self::table('events_to_providers'),
            self::table('event_providers'),
            self::table('events_periods_to_providers'),
            self::table('providers_to_events_periods'),
            self::table('event_periods_providers'),
        ];
        foreach ($tables as $table) {
            $lower = strtolower((string) $table);
            if (str_contains($lower, 'event') && (str_contains($lower, 'provider') || str_contains($lower, 'user'))) {
                $preferred[] = $table;
            }
        }
        return array_values(array_unique(array_filter($preferred, [__CLASS__, 'table_exists'])));
    }

    private static function assignment_value_contains_employee($value, int $employee_id): bool {
        if ($value === null || $value === '') return false;
        if (is_numeric($value)) return (int) $value === $employee_id;

        if (is_array($value) || is_object($value)) {
            foreach ((array) $value as $key => $item) {
                if (self::assignment_value_contains_employee($key, $employee_id)
                    || self::assignment_value_contains_employee($item, $employee_id)) return true;
            }
            return false;
        }

        $text = trim((string) $value);
        $decoded = json_decode($text, true);
        if (json_last_error() === JSON_ERROR_NONE && $decoded !== null) {
            return self::assignment_value_contains_employee($decoded, $employee_id);
        }

        $unserialized = maybe_unserialize($text);
        if ($unserialized !== $text) {
            return self::assignment_value_contains_employee($unserialized, $employee_id);
        }

        return preg_match('/(^|[^0-9])' . preg_quote((string) $employee_id, '/') . '([^0-9]|$)/', $text) === 1;
    }

    /**
     * Discover Amelia tables that can map a provider/employee to a service.
     * Amelia table names vary between releases, so this must be runtime-driven.
     *
     * @return array<int,string>
     */
    private static function amelia_provider_service_tables(): array {
        global $wpdb;

        $preferred = [
            self::table('providers_to_services'),
            self::table('services_providers'),
            self::table('providers_services'),
        ];

        $tables = $wpdb->get_col(
            $wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($wpdb->prefix . 'amelia_') . '%')
        ) ?: [];

        foreach ($tables as $table) {
            $table = (string) $table;
            if (!self::table_exists($table)) {
                continue;
            }

            $provider_column = self::first_existing_column(
                $table,
                ['userId', 'providerId', 'employeeId', 'provider_id', 'user_id', 'provider', 'employee']
            );
            $service_column = self::first_existing_column(
                $table,
                ['serviceId', 'service_id', 'service']
            );

            if ($provider_column && $service_column) {
                $preferred[] = $table;
            }
        }

        return array_values(array_unique(array_filter($preferred, [__CLASS__, 'table_exists'])));
    }

    /**
     * Extract only explicit future date/time strings from an Amelia service
     * description. A year is never invented: abbreviated dates are accepted
     * only when the same description contains an explicit four-digit year.
     *
     * @return array<int,string>
     */
    /**
     * Read explicit future occurrences from one Amelia service record.
     *
     * This parser is intentionally scoped to the supplied service only. It
     * supports Amelia's formatted "Time & Location / Other dates" text and
     * clearly written weekly date ranges such as "June 11 - 25 & July 9 - 30,
     * 2026". When no time is written, the occurrence is marked date-only
     * rather than inventing a time.
     *
     * @return array<int,array{bookingStart:string,date_only:bool}>
     */
    private static function explicit_service_occurrences(string $description, string $service_name = ''): array {
        $text = preg_replace('/<\s*(?:br|\/p|\/div|\/li|\/h[1-6])\s*\/?>/i', "\n", $description);
        $text = html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(["\xC2\xA0", "\r", "\t"], [' ', "\n", ' '], $text);
        $text = wp_strip_all_tags($text);
        $text = preg_replace('/[ ]+/u', ' ', (string) $text);
        $text = preg_replace('/\n[ ]*/u', "\n", (string) $text);
        $text = trim((string) $text);
        if ($text === '') return [];

        $timezone = wp_timezone();
        $now = new DateTimeImmutable('now', $timezone);
        $today = $now->setTime(0, 0, 0);

        $parse_local = static function (string $month, string $day, string $year, string $time = '') use ($timezone): ?DateTimeImmutable {
            $time = trim((string) preg_replace('/\s+/u', ' ', trim($time)));
            $candidate = trim(sprintf('%s %s %s%s', $month, $day, $year, $time !== '' ? ' ' . $time : ''));
            $formats = $time !== ''
                ? ['F j Y g:i A', 'M j Y g:i A']
                : ['F j Y', 'M j Y'];
            foreach ($formats as $format) {
                $date = DateTimeImmutable::createFromFormat('!' . $format, $candidate, $timezone);
                $errors = DateTimeImmutable::getLastErrors();
                if ($date instanceof DateTimeImmutable && ($errors === false || ((int) $errors['warning_count'] === 0 && (int) $errors['error_count'] === 0))) {
                    return $date;
                }
            }
            return null;
        };

        $occurrences = [];
        $add = static function (DateTimeImmutable $date, bool $date_only) use (&$occurrences, $now, $today): void {
            if (($date_only && $date < $today) || (!$date_only && $date < $now)) return;
            $key = $date->format('Y-m-d H:i:s') . '|' . ($date_only ? '1' : '0');
            $occurrences[$key] = [
                'bookingStart' => $date->format('Y-m-d H:i:s'),
                'date_only' => $date_only,
            ];
        };

        // Fully qualified Amelia date/time entries.
        $full_pattern = '/\b(?:Mon|Tue|Wed|Thu|Fri|Sat|Sun)(?:day)?[,]?\s*([A-Z][a-z]{2,8})\s+(\d{1,2})[,]?\s+(20\d{2})[,]?\s+(\d{1,2}:\d{2}\s*[AP]M)\b/i';
        $full_pattern_no_weekday = '/\b([A-Z][a-z]{2,8})\s+(\d{1,2})[,]?\s+(20\d{2})[,]?\s+(\d{1,2}:\d{2}\s*[AP]M)\b/i';
        $full_matches = [];
        preg_match_all($full_pattern, $text, $full_matches, PREG_SET_ORDER);
        if (!$full_matches) preg_match_all($full_pattern_no_weekday, $text, $full_matches, PREG_SET_ORDER);

        $context_year = '';
        $default_time = '';
        foreach ($full_matches as $match) {
            $date = $parse_local((string) $match[1], (string) $match[2], (string) $match[3], (string) $match[4]);
            if ($date) {
                $context_year = (string) $match[3];
                $default_time = (string) $match[4];
                $add($date, false);
            }
        }

        if ($context_year === '' && preg_match('/\b(20\d{2})\b/', $text, $year_match)) {
            $context_year = (string) $year_match[1];
        }
        if ($default_time === '' && preg_match('/\b(\d{1,2}:\d{2}\s*[AP]M)\b/i', $text, $time_match)) {
            $default_time = (string) $time_match[1];
        }

        // Amelia often omits the year from "Other dates".
        if ($context_year !== '') {
            $partial_pattern = '/\b(?:Mon|Tue|Wed|Thu|Fri|Sat|Sun)(?:day)?[,]?\s*([A-Z][a-z]{2,8})\s+(\d{1,2})[,]?\s+(\d{1,2}:\d{2}\s*[AP]M)\b/i';
            if (preg_match_all($partial_pattern, $text, $partial_matches, PREG_SET_ORDER)) {
                foreach ($partial_matches as $match) {
                    $date = $parse_local((string) $match[1], (string) $match[2], $context_year, (string) $match[3]);
                    if ($date) $add($date, false);
                }
            }
        }

        // Weekly ranges written in service descriptions, for example:
        // "June 11 - 25 & July 9 - 30, 2026". Each range remains scoped to
        // this service and is expanded in seven-day increments.
        if ($context_year !== '') {
            $range_pattern = '/\b([A-Z][a-z]{2,8})\s+(\d{1,2})\s*(?:-|–|—|to)\s*(\d{1,2})(?:\s*,?\s*(20\d{2}))?/i';
            if (preg_match_all($range_pattern, $text, $range_matches, PREG_SET_ORDER)) {
                foreach ($range_matches as $match) {
                    $year = !empty($match[4]) ? (string) $match[4] : $context_year;
                    $start_date = $parse_local((string) $match[1], (string) $match[2], $year, $default_time);
                    $end_date = $parse_local((string) $match[1], (string) $match[3], $year, $default_time);
                    if (!$start_date || !$end_date || $end_date < $start_date) continue;
                    for ($cursor = $start_date; $cursor <= $end_date; $cursor = $cursor->modify('+7 days')) {
                        $add($cursor, $default_time === '');
                    }
                }
            }
        }

        // Standalone explicit dates with a year, even when no time is supplied.
        $date_only_pattern = '/\b([A-Z][a-z]{2,8})\s+(\d{1,2})[,]?\s+(20\d{2})\b/i';
        if (preg_match_all($date_only_pattern, $text, $date_matches, PREG_SET_ORDER)) {
            foreach ($date_matches as $match) {
                $date = $parse_local((string) $match[1], (string) $match[2], (string) $match[3], $default_time);
                if ($date) $add($date, $default_time === '');
            }
        }

        // A service title containing one past date is authoritative for a
        // one-time class. Do not let stale copied future text keep an expired
        // workshop alive. Multi-week journeys/series remain eligible because
        // their description may explicitly contain later dates.
        if ($service_name !== ''
            && !preg_match('/\b(?:\d+\s*[- ]?week|series|journey|sessions|course)\b/i', $service_name)
            && preg_match('/\b([A-Z][a-z]{2,8})\s+(\d{1,2})(?:[,]?\s*(20\d{2}))?\b/i', $service_name, $title_match)) {
            $title_year = !empty($title_match[3]) ? (string) $title_match[3] : ($context_year !== '' ? $context_year : $now->format('Y'));
            $title_date = $parse_local((string) $title_match[1], (string) $title_match[2], $title_year);
            if ($title_date && $title_date < $today) return [];
        }

        uasort($occurrences, static function (array $a, array $b): int {
            return strcmp($a['bookingStart'], $b['bookingStart']);
        });
        return array_values($occurrences);
    }

    /** @return array<int,string> */
    private static function explicit_service_dates(string $description, string $service_name = ''): array {
        return array_map(static fn(array $item): string => $item['bookingStart'], self::explicit_service_occurrences($description, $service_name));
    }

    private static function upcoming_classes(int $employee_id): array {
        global $wpdb;
        self::$upcoming_diagnostics = [];
        $rows = [];
        $now = current_time('mysql');

        // Standard Amelia service appointments.
        $appointments = self::table('appointments');
        $services = self::table('services');
        if (self::table_exists($appointments) && self::table_exists($services)
            && self::column_exists($appointments, 'providerId')
            && self::column_exists($appointments, 'bookingStart')) {
            $status_sql = '';
            if (self::column_exists($appointments, 'status')) {
                $status_sql = " AND (a.status IS NULL OR a.status NOT IN ('canceled','cancelled','rejected'))";
            }
            $sql = $wpdb->prepare(
                "SELECT a.id, a.bookingStart, s.name AS service_name, '' AS booking_url
                 FROM `{$appointments}` a
                 LEFT JOIN `{$services}` s ON s.id = a.serviceId
                 WHERE a.providerId = %d AND a.bookingStart >= %s {$status_sql}
                 ORDER BY a.bookingStart ASC LIMIT 100",
                $employee_id,
                $now
            );
            $appointment_rows = $wpdb->get_results($sql, ARRAY_A) ?: [];
            $rows = array_merge($rows, $appointment_rows);
            self::$upcoming_diagnostics[] = sprintf(
                'Future Amelia appointments found for employee %d: %d',
                $employee_id,
                count($appointment_rows)
            );
        } else {
            self::$upcoming_diagnostics[] = 'The Amelia appointments source was unavailable or missing required provider/date columns.';
        }

        // Amelia service assignments identify which services belong to an artist,
        // but assignment-table date columns are not reliable schedule sources across
        // Amelia versions. Some installations store created/updated metadata in columns
        // named simply "date" or "start", which previously caused dates from one service
        // to be attached to another. Use the relationship only for identity, then parse
        // each assigned service's own explicit schedule independently.
        $relation_tables = self::amelia_provider_service_tables();
        self::$upcoming_diagnostics[] = sprintf(
            'Provider-to-service tables discovered at runtime: %s',
            $relation_tables ? implode(', ', $relation_tables) : 'none'
        );

        $assigned_service_ids = [];
        $description_column = self::table_exists($services)
            ? self::first_existing_column($services, ['description', 'details', 'content'])
            : null;

        if (self::table_exists($services) && $description_column
            && self::column_exists($services, 'id') && self::column_exists($services, 'name')) {
            foreach ($relation_tables as $relation_table) {
                $provider_column = self::first_existing_column(
                    $relation_table,
                    ['userId', 'providerId', 'employeeId', 'provider_id', 'user_id', 'provider', 'employee']
                );
                $service_column = self::first_existing_column(
                    $relation_table,
                    ['serviceId', 'service_id', 'service']
                );
                if (!$provider_column || !$service_column) {
                    continue;
                }

                $assigned_services = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT DISTINCT s.id, s.name, s.`{$description_column}` AS description
                         FROM `{$relation_table}` r
                         INNER JOIN `{$services}` s ON s.id = r.`{$service_column}`
                         WHERE r.`{$provider_column}` = %d",
                        $employee_id
                    ),
                    ARRAY_A
                ) ?: [];

                $dates_from_table = 0;
                foreach ($assigned_services as $assigned_service) {
                    $service_id = (int) ($assigned_service['id'] ?? 0);
                    if ($service_id <= 0 || isset($assigned_service_ids[$service_id])) {
                        continue;
                    }
                    $assigned_service_ids[$service_id] = true;
                    $explicit_occurrences = self::explicit_service_occurrences((string) ($assigned_service['description'] ?? ''), (string) ($assigned_service['name'] ?? ''));
                    foreach ($explicit_occurrences as $date_index => $occurrence) {
                        $explicit_date = (string) $occurrence['bookingStart'];
                        $rows[] = [
                            'id' => 'service-' . $service_id . '-' . $date_index,
                            'bookingStart' => $explicit_date,
                            'service_name' => (string) ($assigned_service['name'] ?? 'Class'),
                            'booking_url' => '',
                            'date_only' => !empty($occurrence['date_only']),
                        ];
                        $dates_from_table++;
                    }
                    self::$upcoming_diagnostics[] = sprintf(
                        'Assigned service %d (%s): %d verified future date(s).',
                        $service_id,
                        (string) ($assigned_service['name'] ?? 'Class'),
                        count($explicit_occurrences)
                    );
                }
                self::$upcoming_diagnostics[] = sprintf(
                    'Verified service-description dates found through %s: %d',
                    $relation_table,
                    $dates_from_table
                );
            }
        }

        // Final service-assignment compatibility path. Some Amelia releases keep
        // assigned employees directly inside a service column as JSON, serialized data,
        // or a comma-separated value rather than a junction table.
        $has_description_rows = !empty($assigned_service_ids);
        if (!$has_description_rows && self::table_exists($services)) {
            $service_columns = $wpdb->get_col("SHOW COLUMNS FROM `{$services}`", 0) ?: [];
            $description_column = self::first_existing_column($services, ['description', 'details', 'content']);
            $assignment_columns = array_values(array_filter($service_columns, static function ($column) {
                return preg_match('/(provider|employee|staff|user|assignee|teacher|artist)/i', (string) $column) === 1;
            }));

            if ($description_column && $assignment_columns
                && self::column_exists($services, 'id') && self::column_exists($services, 'name')) {
                $service_rows = $wpdb->get_results(
                    "SELECT * FROM `{$services}` ORDER BY `id` ASC LIMIT 500",
                    ARRAY_A
                ) ?: [];
                $embedded_dates_found = 0;
                foreach ($service_rows as $service_row) {
                    $assigned = false;
                    foreach ($assignment_columns as $assignment_column) {
                        if (self::assignment_value_contains_employee($service_row[$assignment_column] ?? null, $employee_id)) {
                            $assigned = true;
                            break;
                        }
                    }
                    if (!$assigned) {
                        continue;
                    }
                    foreach (self::explicit_service_occurrences((string) ($service_row[$description_column] ?? ''), (string) ($service_row['name'] ?? '')) as $date_index => $occurrence) {
                        $explicit_date = (string) $occurrence['bookingStart'];
                        $rows[] = [
                            'id' => 'embedded-service-' . (int) ($service_row['id'] ?? 0) . '-' . $date_index,
                            'bookingStart' => $explicit_date,
                            'service_name' => (string) ($service_row['name'] ?? 'Class'),
                            'booking_url' => '',
                            'date_only' => !empty($occurrence['date_only']),
                        ];
                        $embedded_dates_found++;
                    }
                }
                self::$upcoming_diagnostics[] = sprintf(
                    'Explicit future dates read from services with embedded employee assignments: %d',
                    $embedded_dates_found
                );
            }
        }

        // Amelia Events can attach providers either to the whole event or to an
        // individual event period/date. Detect both layouts because recurring
        // events such as "Other dates" commonly use a period-provider junction.
        $events = self::table('events');
        $periods = self::table('events_periods');
        if (self::table_exists($events) && self::table_exists($periods)) {
            $period_id = self::first_existing_column($periods, ['id', 'periodId', 'eventPeriodId']);
            $period_event = self::first_existing_column($periods, ['eventId', 'event_id', 'event']);
            $period_start = self::first_existing_column($periods, ['periodStart', 'start', 'startDate', 'bookingStart', 'startDateTime', 'dateStart']);
            $event_name = self::first_existing_column($events, ['name', 'title', 'eventName']);
            $event_id = self::first_existing_column($events, ['id', 'eventId']);

            if ($period_event && $period_start && $event_name && $event_id) {
                $event_rows = [];

                foreach (self::amelia_event_provider_tables() as $provider_table) {
                    $provider_user = self::first_existing_column($provider_table, ['userId', 'providerId', 'employeeId', 'user_id', 'provider_id', 'provider']);
                    if (!$provider_user) continue;

                    // Provider is attached to the event itself.
                    $provider_event = self::first_existing_column($provider_table, ['eventId', 'event_id', 'event']);
                    if ($provider_event) {
                        $sql = $wpdb->prepare(
                            "SELECT p.{$period_id} AS id, p.{$period_start} AS bookingStart,
                                    e.{$event_name} AS service_name, '' AS booking_url
                             FROM `{$periods}` p
                             INNER JOIN `{$events}` e ON e.{$event_id} = p.{$period_event}
                             INNER JOIN `{$provider_table}` ep ON ep.{$provider_event} = e.{$event_id}
                             WHERE ep.{$provider_user} = %d AND p.{$period_start} >= %s
                             ORDER BY p.{$period_start} ASC LIMIT 100",
                            $employee_id,
                            $now
                        );
                        $event_rows = array_merge($event_rows, $wpdb->get_results($sql, ARRAY_A) ?: []);
                    }

                    // Provider is attached to one event period/date.
                    $provider_period = self::first_existing_column($provider_table, ['eventPeriodId', 'periodId', 'event_period_id', 'event_period', 'period_id']);
                    if ($provider_period && $period_id) {
                        $sql = $wpdb->prepare(
                            "SELECT p.{$period_id} AS id, p.{$period_start} AS bookingStart,
                                    e.{$event_name} AS service_name, '' AS booking_url
                             FROM `{$periods}` p
                             INNER JOIN `{$events}` e ON e.{$event_id} = p.{$period_event}
                             INNER JOIN `{$provider_table}` ep ON ep.{$provider_period} = p.{$period_id}
                             WHERE ep.{$provider_user} = %d AND p.{$period_start} >= %s
                             ORDER BY p.{$period_start} ASC LIMIT 100",
                            $employee_id,
                            $now
                        );
                        $event_rows = array_merge($event_rows, $wpdb->get_results($sql, ARRAY_A) ?: []);
                    }
                }

                // Some Amelia revisions store the provider directly on a period.
                $period_provider = self::first_existing_column($periods, ['providerId', 'employeeId', 'userId', 'provider_id']);
                if ($period_provider) {
                    $sql = $wpdb->prepare(
                        "SELECT p.{$period_id} AS id, p.{$period_start} AS bookingStart,
                                e.{$event_name} AS service_name, '' AS booking_url
                         FROM `{$periods}` p
                         INNER JOIN `{$events}` e ON e.{$event_id} = p.{$period_event}
                         WHERE p.{$period_provider} = %d AND p.{$period_start} >= %s
                         ORDER BY p.{$period_start} ASC LIMIT 100",
                        $employee_id,
                        $now
                    );
                    $event_rows = array_merge($event_rows, $wpdb->get_results($sql, ARRAY_A) ?: []);
                }

                // Older layouts store the provider directly on the event.
                $event_provider = self::first_existing_column($events, ['providerId', 'employeeId', 'userId', 'provider_id']);
                if ($event_provider) {
                    $sql = $wpdb->prepare(
                        "SELECT p.{$period_id} AS id, p.{$period_start} AS bookingStart,
                                e.{$event_name} AS service_name, '' AS booking_url
                         FROM `{$periods}` p
                         INNER JOIN `{$events}` e ON e.{$event_id} = p.{$period_event}
                         WHERE e.{$event_provider} = %d AND p.{$period_start} >= %s
                         ORDER BY p.{$period_start} ASC LIMIT 100",
                        $employee_id,
                        $now
                    );
                    $event_rows = array_merge($event_rows, $wpdb->get_results($sql, ARRAY_A) ?: []);
                }

                // Final compatibility fallback: some Amelia Event versions keep the
                // assigned artist inside an event/period field (often JSON, serialized,
                // comma-separated, or an organizer/provider column) instead of a junction table.
                // Read future periods and test only assignment-like columns for this employee ID.
                $event_columns = $wpdb->get_col("SHOW COLUMNS FROM `{$events}`", 0) ?: [];
                $period_columns = $wpdb->get_col("SHOW COLUMNS FROM `{$periods}`", 0) ?: [];
                $assignment_pattern = '/(provider|employee|staff|organizer|user|assignee|host|teacher|artist)/i';
                $event_assignment_columns = array_values(array_filter($event_columns, static function ($column) use ($assignment_pattern) {
                    return preg_match($assignment_pattern, (string) $column) === 1;
                }));
                $period_assignment_columns = array_values(array_filter($period_columns, static function ($column) use ($assignment_pattern) {
                    return preg_match($assignment_pattern, (string) $column) === 1;
                }));

                if ($event_assignment_columns || $period_assignment_columns) {
                    $future_sql = $wpdb->prepare(
                        "SELECT e.*, p.*, p.{$period_id} AS elev8_period_id,
                                p.{$period_start} AS elev8_booking_start,
                                e.{$event_name} AS elev8_service_name
                         FROM `{$periods}` p
                         INNER JOIN `{$events}` e ON e.{$event_id} = p.{$period_event}
                         WHERE p.{$period_start} >= %s
                         ORDER BY p.{$period_start} ASC LIMIT 300",
                        $now
                    );
                    foreach ($wpdb->get_results($future_sql, ARRAY_A) ?: [] as $future_row) {
                        $matched = false;
                        foreach (array_merge($event_assignment_columns, $period_assignment_columns) as $assignment_column) {
                            if (!array_key_exists($assignment_column, $future_row)) continue;
                            if (self::assignment_value_contains_employee($future_row[$assignment_column], $employee_id)) {
                                $matched = true;
                                break;
                            }
                        }
                        if ($matched) {
                            $event_rows[] = [
                                'id' => $future_row['elev8_period_id'] ?? 0,
                                'bookingStart' => $future_row['elev8_booking_start'] ?? '',
                                'service_name' => $future_row['elev8_service_name'] ?? 'Class',
                                'booking_url' => '',
                            ];
                        }
                    }
                }

                $rows = array_merge($rows, $event_rows);
            }
        }

        // Optional manual fallback.
        $profiles = self::get_profiles();
        $manual = (string) ($profiles[$employee_id]['manual_upcoming_classes'] ?? '');
        foreach (preg_split('/\r\n|\r|\n/', $manual) ?: [] as $index => $line) {
            $line = trim($line);
            if ($line === '') continue;
            $parts = array_map('trim', explode('|', $line, 3));
            $timestamp = strtotime($parts[0] ?? '');
            if (!$timestamp || $timestamp < current_time('timestamp')) continue;
            $rows[] = [
                'id' => 'manual-' . $index,
                'bookingStart' => wp_date('Y-m-d H:i:s', $timestamp),
                'service_name' => $parts[1] ?? 'Class',
                'booking_url' => isset($parts[2]) ? esc_url_raw($parts[2]) : '',
            ];
        }

        usort($rows, static function ($a, $b) {
            return strcmp((string) ($a['bookingStart'] ?? ''), (string) ($b['bookingStart'] ?? ''));
        });
        $deduped = [];
        foreach ($rows as $row) {
            $key = strtolower(trim((string) ($row['service_name'] ?? ''))) . '|' . (string) ($row['bookingStart'] ?? '');
            $deduped[$key] = $row;
        }
        return array_slice(array_values($deduped), 0, 30);
    }

    private static function employee_id_for_current_user(): int {
        $uid=get_current_user_id();
        foreach (self::get_profiles() as $employee_id=>$profile) {
            if ((int)($profile['wp_user_id']??0)===$uid && ($profile['status']??'active')==='active') return (int)$employee_id;
        }
        return 0;
    }

    private static function render_portal_content(int $employee_id, bool $admin=false): string {
        $employees=self::get_employees(); $name='Artist';
        foreach($employees as $e){ if((int)$e['id']===$employee_id){$name=trim($e['firstName'].' '.$e['lastName']);break;} }
        $month=isset($_GET['elev8_month'])?sanitize_text_field(wp_unslash($_GET['elev8_month'])):current_time('Y-m');
        if(!preg_match('/^\d{4}-\d{2}$/',$month))$month=current_time('Y-m');
        $classes=self::classes_for_artist($employee_id,$month); $rules=self::get_rules();
        $tot=['classes'=>0,'customers'=>0,'gross'=>0.0,'refunds'=>0.0,'teacher'=>0.0,'elev8'=>0.0];
        foreach($classes as &$c){$calc=self::calculate_class($c,self::find_rule($c,$rules));$c['calc']=$calc;$tot['classes']++;$tot['customers']+=$c['customers'];$tot['gross']+=$c['gross'];$tot['refunds']+=$c['refunds'];$tot['teacher']+=$calc['teacher'];$tot['elev8']+=$calc['elev8'];} unset($c);
        $avg=$tot['classes']?($tot['customers']/$tot['classes']):0;
        $profile=self::get_profiles()[$employee_id]??[]; $upcoming=self::upcoming_classes($employee_id); $socials=self::social_links($profile);
        $payout=self::get_payouts()[$employee_id][$month]??['status'=>'unpaid','paid_date'=>'','note'=>''];
        ob_start(); ?>
        <div class="elev8-os elev8-portal">
          <div class="elev8-header"><div><h1><?php echo esc_html($name); ?> — Artist Portal</h1><p>Your Elev8 Arts classes, students, earnings, schedule, and documents.</p></div><span class="elev8-version"><?php echo esc_html(date_i18n('F Y',strtotime($month.'-01'))); ?></span></div>
          <?php if(!empty($profile['announcement'])):?><div class="elev8-private-note"><strong>Message from Elev8:</strong> <?php echo nl2br(esc_html($profile['announcement'])); ?></div><?php endif; ?>
          <form method="get" class="elev8-filter"><?php if($admin):?><input type="hidden" name="page" value="elev8-artist-portal"><input type="hidden" name="artist_id" value="<?php echo esc_attr((string)$employee_id); ?>"><?php endif;?><label>Report month</label><input type="month" name="elev8_month" value="<?php echo esc_attr($month); ?>"><button class="button button-primary">View month</button></form>
          <div class="elev8-cards"><div class="elev8-card"><span>Classes taught</span><strong><?php echo $tot['classes']; ?></strong></div><div class="elev8-card"><span>Students</span><strong><?php echo $tot['customers']; ?></strong></div><div class="elev8-card"><span>Gross revenue</span><strong><?php echo esc_html(self::money($tot['gross'])); ?></strong></div><div class="elev8-card"><span>Artist earnings</span><strong><?php echo esc_html(self::money($tot['teacher'])); ?></strong></div><div class="elev8-card"><span>Elev8 share</span><strong><?php echo esc_html(self::money($tot['elev8'])); ?></strong></div><div class="elev8-card"><span>Average class size</span><strong><?php echo esc_html(number_format($avg,1)); ?></strong></div></div>
          <?php $ref=self::referral_totals($employee_id); ?><div class="elev8-cards"><div class="elev8-card"><span>Referral clicks</span><strong><?php echo (int)$ref['clicks'];?></strong></div><div class="elev8-card"><span>Referral sales</span><strong><?php echo esc_html(self::money($ref['sales']));?></strong></div><div class="elev8-card"><span>Referral earnings</span><strong><?php echo esc_html(self::money($ref['commission']));?></strong></div></div><div class="elev8-private-note"><strong>Your public page:</strong> <a href="<?php echo esc_url(self::public_artist_url($employee_id));?>" target="_blank"><?php echo esc_html(self::public_artist_url($employee_id));?></a><br><strong>Your referral link:</strong> <input readonly value="<?php echo esc_attr(self::public_artist_url($employee_id,true));?>" onclick="this.select()"></div>
          <div class="elev8-grid"><div class="elev8-panel"><h2>Upcoming classes</h2><?php if($upcoming):?><table class="widefat striped"><thead><tr><th>Date</th><th>Class</th></tr></thead><tbody><?php foreach($upcoming as $u):?><tr><td><?php echo esc_html(!empty($u['date_only']) ? mysql2date('M j, Y',$u['bookingStart']) : mysql2date('M j, Y g:i a',$u['bookingStart']));?></td><td><?php echo esc_html($u['service_name']?:'Class');?></td></tr><?php endforeach;?></tbody></table><?php else:?><div class="elev8-empty">No upcoming classes found.</div><?php endif;?><?php if($admin && current_user_can('manage_options')):?><details class="elev8-diagnostics"><summary>Upcoming class diagnostics</summary><ul><?php foreach(self::$upcoming_diagnostics as $diagnostic):?><li><?php echo esc_html($diagnostic);?></li><?php endforeach;?></ul><p><strong>Selected Amelia employee ID:</strong> <?php echo (int)$employee_id;?></p></details><?php endif;?></div>
          <div class="elev8-panel"><h2>Payout status</h2><p class="elev8-big-status"><?php echo ($payout['status']==='paid')?'✓ Paid':'Pending'; ?></p><?php if($payout['paid_date']):?><p>Paid <?php echo esc_html($payout['paid_date']);?></p><?php endif;?><p><?php echo esc_html($payout['note']??'');?></p><p><strong>Amount for this month:</strong> <?php echo esc_html(self::money($tot['teacher']));?></p></div></div>
          <div class="elev8-panel"><h2>Class history</h2><?php if($classes):?><div class="elev8-table-wrap"><table class="widefat striped"><thead><tr><th>Date</th><th>Class</th><th>Students</th><th>Gross</th><th>Refunds</th><th>Your earnings</th><th>Elev8</th></tr></thead><tbody><?php foreach($classes as $c):?><tr><td><?php echo esc_html(mysql2date('M j, Y g:i a',$c['date']));?></td><td><?php echo esc_html($c['service']);?></td><td><?php echo (int)$c['customers'];?></td><td><?php echo esc_html(self::money($c['gross']));?></td><td><?php echo esc_html(self::money($c['refunds']));?></td><td><?php echo esc_html(self::money($c['calc']['teacher']));?></td><td><?php echo esc_html(self::money($c['calc']['elev8']));?></td></tr><?php endforeach;?></tbody></table></div><?php else:?><div class="elev8-empty">No classes found for this month.</div><?php endif;?></div>
          <div class="elev8-grid"><div class="elev8-panel"><h2>Profile</h2><p><strong>Medium:</strong> <?php echo esc_html($profile['medium']??''); ?></p><p><strong>Teaching specialties:</strong> <?php echo esc_html($profile['specialties']??''); ?></p><p><strong>Experience:</strong> <?php echo esc_html($profile['experience']??''); ?></p><p><?php echo nl2br(esc_html($profile['bio']??'')); ?></p><?php if(!empty($profile['website'])):?><p><a href="<?php echo esc_url($profile['website']);?>" target="_blank" rel="noopener">Website</a></p><?php endif;?><?php foreach($socials as $social):?><p><a href="<?php echo esc_url($social['url']);?>" target="_blank" rel="noopener"><?php echo esc_html($social['name']);?></a></p><?php endforeach;?></div>
          <div class="elev8-panel"><h2>Documents & tax information</h2><p><strong>W-9 status:</strong> <?php echo esc_html(ucwords(str_replace('_',' ',$profile['w9_status']??'not_received')));?></p><?php if(!empty($profile['agreement_url'])):?><p><a href="<?php echo esc_url($profile['agreement_url']);?>" target="_blank" rel="noopener">Artist agreement</a></p><?php endif;?><?php if(!empty($profile['tax_document_url'])):?><p><a href="<?php echo esc_url($profile['tax_document_url']);?>" target="_blank" rel="noopener">Tax document</a></p><?php endif;?><p class="description">Sensitive tax numbers are not stored in Elev8 OS.</p></div></div>
        </div><?php return ob_get_clean();
    }

    public static function artist_portal_shortcode(): string {
        if (!is_user_logged_in()) return '<div class="elev8-empty">Please log in to view your Elev8 Artist Portal.</div>';
        $employee_id=self::employee_id_for_current_user();
        if(!$employee_id && current_user_can('manage_options') && isset($_GET['artist_id'])) $employee_id=absint($_GET['artist_id']);
        if(!$employee_id) return '<div class="elev8-empty">Your WordPress account has not yet been connected to an Elev8 Member Artist profile.</div>';
        wp_enqueue_style('elev8-os-admin',ELEV8_OS_URL . 'assets/css/admin.css',[],ELEV8_OS_VERSION);
        return self::render_portal_content($employee_id,false);
    }

    public static function render_artist_portal_admin(): void {
        if(!current_user_can('manage_options'))return;
        $employees=self::get_employees(); $profiles=self::get_profiles();
        $artist_id=isset($_GET['artist_id'])?absint($_GET['artist_id']):((int)($employees[0]['id']??0));
        $profile=$profiles[$artist_id]??[]; $users=get_users(['orderby'=>'display_name','order'=>'ASC']);
        $message=isset($_GET['message'])?sanitize_key($_GET['message']):'';
        echo '<div class="wrap elev8-os"><div class="elev8-header"><div><h1>Artists</h1><p>Manage artist profiles, partnership rules, payouts, documents, referrals, and public pages from one place.</p></div><span class="elev8-version">Version '.esc_html(ELEV8_OS_VERSION).'</span></div>';
        if($message) echo '<div class="notice notice-success is-dismissible"><p>Artist portal updated.</p></div>';
        echo '<form method="get" class="elev8-filter"><input type="hidden" name="page" value="elev8-artist-portal"><label>Elev8 Member Artist</label><select name="artist_id">';
        foreach($employees as $e){$n=trim($e['firstName'].' '.$e['lastName']);echo '<option value="'.esc_attr($e['id']).'" '.selected($artist_id,(int)$e['id'],false).'>'.esc_html($n).'</option>';}
        echo '</select><button class="button button-primary">Open artist</button></form>';
        if($artist_id){ echo self::render_portal_content($artist_id,true); ?>
        <div class="elev8-grid"><div class="elev8-panel"><h2>Artist profile & login</h2><form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><input type="hidden" name="action" value="elev8_os_save_artist_profile"><input type="hidden" name="employee_id" value="<?php echo esc_attr((string)$artist_id);?>"><?php wp_nonce_field('elev8_os_save_artist_profile');?><?php Elev8_OS_User_Search_Component::render(['name'=>'wp_user_id','id'=>'elev8-connected-account','selected'=>(int)($profile['wp_user_id']??0),'artist_id'=>(int)$artist_id,'label'=>'Connected Account']);?><label>Status</label><select name="status"><option value="active" <?php selected($profile['status']??'active','active');?>>Active</option><option value="inactive" <?php selected($profile['status']??'active','inactive');?>>Inactive</option></select><label>Bio</label><textarea name="bio" rows="5"><?php echo esc_textarea($profile['bio']??'');?></textarea><label>Medium</label><input name="medium" value="<?php echo esc_attr($profile['medium']??'');?>"><label>Teaching specialties</label><input name="specialties" value="<?php echo esc_attr($profile['specialties']??'');?>"><label>Years/experience</label><input name="experience" value="<?php echo esc_attr($profile['experience']??'');?>"><label>Website</label><input type="url" name="website" value="<?php echo esc_attr($profile['website']??'');?>"><h3>Social links</h3><p class="description">Add up to four links. Name each one Facebook, YouTube, Pinterest, Etsy, Instagram, or anything else.</p><?php for($social_i=1;$social_i<=4;$social_i++): $legacy_social=($social_i===1?($profile['social']??''):'');?><div class="elev8-social-row"><label>Social <?php echo $social_i;?> name</label><input type="text" name="social_<?php echo $social_i;?>_name" placeholder="Facebook" value="<?php echo esc_attr($profile['social_'.$social_i.'_name']??($social_i===1&&!empty($legacy_social)?'Social media':''));?>"><label>Social <?php echo $social_i;?> link</label><input type="url" name="social_<?php echo $social_i;?>_url" placeholder="https://" value="<?php echo esc_attr($profile['social_'.$social_i.'_url']??$legacy_social);?>"></div><?php endfor;?><h3>Payment links</h3><p class="description">Add up to four payment or support links, such as Venmo, PayPal, Cash App, Patreon, or a tip page.</p><?php for($link_i=1;$link_i<=4;$link_i++):?><div class="elev8-social-row"><label>Payment <?php echo $link_i;?> name</label><input type="text" name="payment_<?php echo $link_i;?>_name" placeholder="Venmo" value="<?php echo esc_attr($profile['payment_'.$link_i.'_name']??'');?>"><label>Payment <?php echo $link_i;?> link</label><input type="text" name="payment_<?php echo $link_i;?>_url" placeholder="https://venmo.com/u/name" value="<?php echo esc_attr($profile['payment_'.$link_i.'_url']??'');?>"></div><?php endfor;?><h3>Public contact links</h3><p class="description">Add up to four contact methods. For email, enter just the email address. For phone, enter just the phone number. Elev8 OS will automatically make them clickable. You can also enter a webpage or contact-form link.</p><?php for($link_i=1;$link_i<=4;$link_i++):?><div class="elev8-social-row"><label>Contact <?php echo $link_i;?> name</label><input type="text" name="contact_<?php echo $link_i;?>_name" placeholder="Email me" value="<?php echo esc_attr($profile['contact_'.$link_i.'_name']??'');?>"><label>Contact <?php echo $link_i;?> link</label><input type="text" name="contact_<?php echo $link_i;?>_url" placeholder="artist@example.com or 719-555-1212" value="<?php echo esc_attr($profile['contact_'.$link_i.'_url']??'');?>"></div><?php endfor;?><label><input type="checkbox" name="public_enabled" value="1" <?php checked(!empty($profile['public_enabled']));?>> Make public artist webpage active</label><h3>Artist images</h3><p class="description">Upload images directly. Elev8 OS stores them in the WordPress Media Library and publishes them automatically.</p><label>Profile photo</label><?php if(!empty($profile['profile_photo'])):?><div class="elev8-artist-image-preview"><img src="<?php echo esc_url($profile['profile_photo']);?>" alt="Current profile photo" style="max-width:160px;height:auto;border-radius:12px;display:block;margin:8px 0;"><label><input type="checkbox" name="remove_profile_photo" value="1"> Remove current profile photo</label></div><?php endif;?><input type="file" name="profile_photo_upload" accept="image/*"><details><summary>Advanced: use an image URL</summary><input type="url" name="profile_photo" value="<?php echo esc_attr($profile['profile_photo']??'');?>" placeholder="https://"></details><label>Hero / cover image</label><?php if(!empty($profile['cover_image'])):?><div class="elev8-artist-image-preview"><img src="<?php echo esc_url($profile['cover_image']);?>" alt="Current hero image" style="max-width:420px;width:100%;height:auto;border-radius:12px;display:block;margin:8px 0;"><label><input type="checkbox" name="remove_cover_image" value="1"> Remove current hero image</label></div><?php endif;?><input type="file" name="cover_image_upload" accept="image/*"><details><summary>Advanced: use an image URL</summary><input type="url" name="cover_image" value="<?php echo esc_attr($profile['cover_image']??'');?>" placeholder="https://"></details><p class="description"><strong>Artwork storefront:</strong> Public products are managed once through the artist's My Artwork page. Legacy gallery data is preserved but no longer displayed.</p><h3>Booking destination</h3><p class="description">Choose where customers should go to see availability and book this artist's classes. This link is also used as the fallback for every listed class.</p><label>Booking destination type</label><select name="booking_destination"><option value="category" <?php selected($profile['booking_destination']??'category','category');?>>Amelia category page</option><option value="custom" <?php selected($profile['booking_destination']??'category','custom');?>>Custom booking page</option><option value="appointments" <?php selected($profile['booking_destination']??'category','appointments');?>>General appointment page</option></select><label>Booking page URL</label><input type="url" name="booking_url" placeholder="https://elev8arts.com/wellness-classes/" value="<?php echo esc_attr($profile['booking_url']??'');?>"><label>Booking button text</label><input type="text" name="booking_button_label" value="<?php echo esc_attr($profile['booking_button_label']??'Book Now with This Artist');?>"><label>Manual upcoming classes (optional fallback)</label><textarea name="manual_upcoming_classes" rows="5" placeholder="2026-08-15 18:00 | Herbal Workshop | https://elev8arts.com/book/"><?php echo esc_textarea($profile['manual_upcoming_classes']??'');?></textarea><p class="description">One class per line: date and time | class name | optional booking URL. Elev8 OS will combine these with upcoming Amelia appointments and Amelia events.</p><label>Referral commission percentage</label><input type="number" step="0.01" min="0" max="100" name="referral_percent" value="<?php echo esc_attr((string)($profile['referral_percent']??0));?>"><p class="description">This artist can earn this percentage when their referral link leads to a WooCommerce-paid booking or sale, including an event taught by another artist.</p><label>W-9 status</label><select name="w9_status"><option value="not_received" <?php selected($profile['w9_status']??'not_received','not_received');?>>Not received</option><option value="received" <?php selected($profile['w9_status']??'not_received','received');?>>Received</option><option value="needs_update" <?php selected($profile['w9_status']??'not_received','needs_update');?>>Needs update</option></select><label>Artist agreement URL</label><input type="url" name="agreement_url" value="<?php echo esc_attr($profile['agreement_url']??'');?>"><label>Tax document URL</label><input type="url" name="tax_document_url" value="<?php echo esc_attr($profile['tax_document_url']??'');?>"><label>Message shown in portal</label><textarea name="announcement" rows="4"><?php echo esc_textarea($profile['announcement']??'');?></textarea><p><button class="button button-primary">Save artist profile</button></p></form></div>
        <div class="elev8-panel"><h2>Record payout</h2><form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><input type="hidden" name="action" value="elev8_os_save_payout"><input type="hidden" name="employee_id" value="<?php echo esc_attr((string)$artist_id);?>"><?php wp_nonce_field('elev8_os_save_payout');?><label>Payout month</label><input type="month" name="payout_month" value="<?php echo esc_attr(isset($_GET['elev8_month'])?$_GET['elev8_month']:current_time('Y-m'));?>"><label>Status</label><select name="payout_status"><option value="unpaid">Pending</option><option value="paid">Paid</option></select><label>Paid date</label><input type="date" name="paid_date"><label>Note</label><input name="payout_note"><p><button class="button button-primary">Save payout status</button></p></form><hr><h3>Portal page setup</h3><p>Create a normal WordPress page and place this shortcode in it:</p><code>[elev8_artist_portal]</code><p>Public artist pages automatically use the artist's first and last name, such as <code>/artists/nicole-casaus/</code>. You can also embed one with <code>[elev8_artist_profile artist="nicole-casaus"]</code>.</p><p>Only the connected artist can see their own information. Administrators can preview any artist here.</p></div></div>
        <?php } echo '</div>';
    }


    public static function render_system_status(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $tables = self::existing_tables();
        $modules = [
            'Core bootstrap' => true,
            'Artist portal' => true,
            'Partnerships and payouts' => true,
            'Public artist profiles' => true,
            'Referral tracking' => true,
            'Development center' => true,
            'Amelia integration' => !empty($tables['users']['exists']) && !empty($tables['services']['exists']),
            'WooCommerce integration' => class_exists('WooCommerce'),
            'Native waitlist' => false,
            'CRM' => false,
            'CEO dashboard' => false,
        ];

        echo '<div class="wrap elev8-os-wrap"><h1>Elev8 OS System Status</h1>';
        echo '<p><strong>Version:</strong> ' . esc_html(ELEV8_OS_VERSION) . ' &nbsp; <strong>Architecture:</strong> Founders Foundation</p>';
        echo '<div class="elev8-card"><h2>Modules</h2><table class="widefat striped"><thead><tr><th>Module</th><th>Status</th></tr></thead><tbody>';
        foreach ($modules as $name => $ready) {
            echo '<tr><td>' . esc_html($name) . '</td><td>' . ($ready ? '<span style="color:#16833b;font-weight:700">Ready</span>' : '<span style="color:#8a5a00;font-weight:700">Planned</span>') . '</td></tr>';
        }
        echo '</tbody></table></div>';
        echo '<div class="elev8-card"><h2>Integration diagnostics</h2><table class="widefat striped"><thead><tr><th>Amelia table</th><th>Status</th></tr></thead><tbody>';
        foreach ($tables as $name => $info) {
            echo '<tr><td>' . esc_html($name) . '</td><td>' . (!empty($info['exists']) ? 'Found' : 'Not found') . '</td></tr>';
        }
        echo '</tbody></table></div>';
        echo '<p>This release reorganizes the codebase without removing the working Version 4.99 features. Future modules will be moved out of the legacy compatibility class in controlled steps.</p></div>';
    }

    public static function render_dashboard(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $tables = self::existing_tables();
        $range = self::date_range();
        $raw_rows = self::get_monthly_rows($range);
        $classes = self::normalize_rows($raw_rows);
        $rules = self::get_rules();
        [$summary, $totals] = self::summarize($classes, $rules);
        $employees = self::get_employees();

        $message = isset($_GET['message']) ? sanitize_key($_GET['message']) : '';
        ?>
        <div class="wrap elev8-os">
            <div class="elev8-header">
                <div>
                    <h1>Elev8 OS</h1>
                    <p>Private Amelia class totals and artist partnership dashboard.</p>
                </div>
                <span class="elev8-version">Version <?php echo esc_html(ELEV8_OS_VERSION); ?></span>
            </div>

            <?php if ($message === 'saved') : ?>
                <div class="notice notice-success is-dismissible"><p>Artist rule saved.</p></div>
            <?php elseif ($message === 'deleted') : ?>
                <div class="notice notice-success is-dismissible"><p>Artist rule deleted.</p></div>
            <?php elseif ($message === 'missing_teacher') : ?>
                <div class="notice notice-error is-dismissible"><p>Please select or enter a teacher.</p></div>
            <?php endif; ?>

            <div class="elev8-private-note">
                <strong>Private:</strong> this page is restricted to WordPress administrators with the
                <code>manage_options</code> permission.
            </div>

            <form method="get" class="elev8-filter">
                <input type="hidden" name="page" value="elev8-os">
                <label for="elev8_month">Report month</label>
                <input id="elev8_month" type="month" name="elev8_month" value="<?php echo esc_attr($range['month']); ?>">
                <button class="button button-primary">View month</button>
            </form>

            <div class="elev8-cards">
                <div class="elev8-card"><span>Class revenue</span><strong><?php echo esc_html(self::money($totals['gross'])); ?></strong></div>
                <div class="elev8-card"><span>Refunds/cancellations</span><strong><?php echo esc_html(self::money($totals['refunds'])); ?></strong></div>
                <div class="elev8-card"><span>Artist keeps</span><strong><?php echo esc_html(self::money($totals['teacher'])); ?></strong></div>
                <div class="elev8-card"><span>Elev8 share</span><strong><?php echo esc_html(self::money($totals['elev8'])); ?></strong></div>
            </div>

            <div class="elev8-panel">
                <h2>Monthly artist summary</h2>
                <?php if (!$classes) : ?>
                    <div class="elev8-empty">
                        No Amelia class records were found for this month. Check the diagnostics below.
                    </div>
                <?php else : ?>
                    <div class="elev8-table-wrap">
                        <table class="widefat striped">
                            <thead>
                                <tr>
                                    <th>Artist</th>
                                    <th>Classes</th>
                                    <th>Customers</th>
                                    <th>Gross</th>
                                    <th>Refunds</th>
                                    <th>Artist keeps</th>
                                    <th>Elev8 share</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($summary as $teacher => $row) : ?>
                                <tr>
                                    <td><strong><?php echo esc_html($teacher); ?></strong></td>
                                    <td><?php echo esc_html((string) $row['classes']); ?></td>
                                    <td><?php echo esc_html((string) $row['customers']); ?></td>
                                    <td><?php echo esc_html(self::money($row['gross'])); ?></td>
                                    <td><?php echo esc_html(self::money($row['refunds'])); ?></td>
                                    <td><?php echo esc_html(self::money($row['teacher'])); ?></td>
                                    <td><?php echo esc_html(self::money($row['elev8'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div class="elev8-grid">
                <div class="elev8-panel">
                    <h2>Add or update artist partnership rule</h2>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="elev8_os_save_rule">
                        <?php wp_nonce_field('elev8_os_save_rule'); ?>

                        <label for="employee_id">Elev8 Member Artist</label>
                        <select name="employee_id" id="employee_id">
                            <option value="0">Use typed artist name instead</option>
                            <?php foreach ($employees as $employee) :
                                $name = trim($employee['firstName'] . ' ' . $employee['lastName']); ?>
                                <option value="<?php echo esc_attr((string) $employee['id']); ?>">
                                    <?php echo esc_html($name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <label for="employee_name">Artist name</label>
                        <input type="text" name="employee_name" id="employee_name" placeholder="Used if no Amelia employee is selected">

                        <label for="rule_type">Partnership model</label>
                        <select name="rule_type" id="rule_type">
                            <option value="tiered_partnership">Standard Elev8 partnership: 40% to $100, then 15%</option>
                            <option value="percent_elev8">Elev8 receives a straight percentage</option>
                            <option value="host_fee">Artist keeps sales and pays Elev8 a host fee</option>
                            <option value="free">Artist keeps all class revenue</option>
                            <option value="percent_teacher">Teacher receives a percentage</option>
                            <option value="fixed_teacher">Teacher receives a fixed amount per class</option>
                        </select>

                        <div class="elev8-rule-box">
                            <h3>Standard Elev8 partnership</h3>
                            <p>Elev8 receives the base percentage until Elev8 has received the cap amount. Revenue above that point uses the lower after-cap percentage.</p>
                            <div class="elev8-fields elev8-fields-three">
                                <div><label for="base_elev8_percent">Elev8 base percentage</label><input type="number" step="0.01" min="0" max="100" name="base_elev8_percent" id="base_elev8_percent" value="40"></div>
                                <div><label for="elev8_cap">Elev8 cap before lower split</label><input type="number" step="0.01" min="0" name="elev8_cap" id="elev8_cap" value="100"></div>
                                <div><label for="after_cap_elev8_percent">Elev8 percentage after cap</label><input type="number" step="0.01" min="0" max="100" name="after_cap_elev8_percent" id="after_cap_elev8_percent" value="15"></div>
                            </div>
                            <div class="elev8-example"><strong>Your current plan:</strong> Elev8 receives 40% until Elev8 has received $100. After that, Elev8 receives 15% and the artist receives 85% of the remaining revenue.</div>
                        </div>

                        <div class="elev8-rule-box">
                            <h3>Member artist / glassblower percentage</h3>
                            <p>Use this when Elev8 should keep the same percentage from every class, such as 15% for member-artist-taught glassblowing classes.</p>
                            <div class="elev8-fields">
                                <div>
                                    <label for="elev8_percent">Elev8 straight percentage</label>
                                    <input type="number" step="0.01" min="0" max="100" name="elev8_percent" id="elev8_percent" value="15">
                                </div>
                            </div>
                            <div class="elev8-example"><strong>Glassblower example:</strong> Choose “Elev8 receives a straight percentage” and enter 15. Elev8 receives 15% and the artist/class side receives 85%.</div>
                        </div>
                        <details class="elev8-legacy-rules"><summary>Other partnership options</summary>
                        <div class="elev8-fields">
                            <div>
                                <label for="host_fee">Base host fee per class</label>
                                <input type="number" step="0.01" min="0" name="host_fee" id="host_fee" value="100">
                            </div>
                            <div>
                                <label for="threshold">Customer threshold</label>
                                <input type="number" min="0" name="threshold" id="threshold" value="10">
                            </div>
                            <div>
                                <label for="extra_percent">Extra Elev8 % after threshold</label>
                                <input type="number" step="0.01" min="0" max="100" name="extra_percent" id="extra_percent" value="15">
                            </div>
                            <div>
                                <label for="teacher_percent">Teacher percentage</label>
                                <input type="number" step="0.01" min="0" max="100" name="teacher_percent" id="teacher_percent" value="50">
                            </div>
                            <div>
                                <label for="fixed_teacher">Fixed teacher pay per class</label>
                                <input type="number" step="0.01" min="0" name="fixed_teacher" id="fixed_teacher" value="100">
                            </div>
                        </div></details>

                        <p class="description">
                            For Elev8 member glassblowers: choose “Elev8 receives a straight percentage” and enter 15%. For independent artists, use the standard tiered partnership.
                        </p>
                        <button class="button button-primary">Save artist partnership</button>
                    </form>
                </div>

                <div class="elev8-panel">
                    <h2>Saved artist partnership rules</h2>
                    <?php if (!$rules) : ?>
                        <div class="elev8-empty">No artist rules saved. Artists default to keeping all net revenue.</div>
                    <?php else : ?>
                        <?php foreach ($rules as $key => $rule) : ?>
                            <div class="elev8-rule">
                                <div>
                                    <strong><?php echo esc_html(self::artist_name_for_rule($rule, $employees)); ?></strong>
                                    <p><?php echo esc_html(self::rule_description($rule)); ?></p>
                                </div>
                                <a class="button button-link-delete"
                                   href="<?php echo esc_url(wp_nonce_url(
                                       add_query_arg([
                                           'action' => 'elev8_os_delete_rule',
                                           'rule_key' => $key,
                                       ], admin_url('admin-post.php')),
                                       'elev8_os_delete_rule'
                                   )); ?>">
                                    Delete
                                </a>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="elev8-panel">
                <h2>Amelia connection diagnostics</h2>
                <p>This first version reads Amelia's WordPress database in read-only mode. It never changes Amelia bookings.</p>
                <div class="elev8-diagnostics">
                    <?php foreach ($tables as $name => $info) : ?>
                        <div class="<?php echo $info['exists'] ? 'ok' : 'missing'; ?>">
                            <span><?php echo $info['exists'] ? '✓' : '×'; ?></span>
                            <code><?php echo esc_html($info['table']); ?></code>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p class="description">
                    Records read for <?php echo esc_html($range['month']); ?>:
                    <strong><?php echo esc_html((string) count($raw_rows)); ?></strong> booking/payment rows,
                    combined into <strong><?php echo esc_html((string) count($classes)); ?></strong> classes.
                </p>
            </div>

            <div class="elev8-panel">
                <h2>Class detail</h2>
                <?php if ($classes) : ?>
                    <div class="elev8-table-wrap">
                        <table class="widefat striped">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Artist</th>
                                    <th>Class</th>
                                    <th>Customers</th>
                                    <th>Gross</th>
                                    <th>Refunds</th>
                                    <th>Artist keeps</th>
                                    <th>Elev8 share</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($classes as $class) :
                                $rule = self::find_rule($class, $rules);
                                $calc = self::calculate_class($class, $rule); ?>
                                <tr>
                                    <td><?php echo esc_html(mysql2date('M j, Y g:i a', $class['date'])); ?></td>
                                    <td><?php echo esc_html($class['teacher']); ?></td>
                                    <td><?php echo esc_html($class['service']); ?></td>
                                    <td><?php echo esc_html((string) $class['customers']); ?></td>
                                    <td><?php echo esc_html(self::money($class['gross'])); ?></td>
                                    <td><?php echo esc_html(self::money($class['refunds'])); ?></td>
                                    <td><?php echo esc_html(self::money($calc['teacher'])); ?></td>
                                    <td><?php echo esc_html(self::money($calc['elev8'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else : ?>
                    <div class="elev8-empty">No class details to display for this month.</div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    private static function seed_development_data(): void {
        $items = get_option(self::OPTION_DEV_ITEMS, []);
        if (is_array($items) && $items) return;
        $now = current_time('mysql');
        $seed = [
            ['type'=>'feature','title'=>'Artist profiles and public pages','reason'=>'Give every Elev8 Member Artist one profile that powers their public webpage and private portal.','phase'=>'Foundation','priority'=>'high','status'=>'released','target_version'=>'4.x'],
            ['type'=>'feature','title'=>'Partnership and payout rules','reason'=>'Calculate artist and Elev8 shares consistently without spreadsheets.','phase'=>'Foundation','priority'=>'critical','status'=>'released','target_version'=>'4.x'],
            ['type'=>'feature','title'=>'Artist contact, payment, booking, referral and QR tools','reason'=>'Help artists promote themselves, accept support, and guide customers into booking.','phase'=>'Growth','priority'=>'high','status'=>'released','target_version'=>'4.99'],
            ['type'=>'feature','title'=>'Development Center and roadmap','reason'=>'Keep every problem, opportunity, bug, reason, priority, milestone, and release organized inside Elev8 OS.','phase'=>'Foundation','priority'=>'critical','status'=>'released','target_version'=>'4.99'],
            ['type'=>'problem','title'=>'Amelia has no dependable class waitlist','reason'=>'The current fake waitlist class sends confusing unbooked-class emails after the class date passes.','phase'=>'Growth','priority'=>'critical','status'=>'planned','target_version'=>'Founders Edition','notes'=>'Build an Elev8 OS waitlist that stores interest without creating an Amelia booking.'],
            ['type'=>'opportunity','title'=>'Elev8 OS Waitlist Engine','reason'=>'Capture demand, notify people when seats open, and recommend when another class should be added.','phase'=>'Growth','priority'=>'critical','status'=>'planned','target_version'=>'Founders Edition'],
            ['type'=>'opportunity','title'=>'Notify me when a class opens','reason'=>'Capture customer interest even when no class date is currently available.','phase'=>'Growth','priority'=>'high','status'=>'planned','target_version'=>'Founders Edition'],
            ['type'=>'opportunity','title'=>'CEO dashboard','reason'=>'Show revenue, attendance, payouts, occupancy, refunds, and business health in one place.','phase'=>'Intelligence','priority'=>'high','status'=>'planned','target_version'=>'Founders Edition'],
            ['type'=>'opportunity','title'=>'Class profitability','reason'=>'Include card fees, advertising, supplies, artist payout, and true net profit for each class.','phase'=>'Intelligence','priority'=>'high','status'=>'planned','target_version'=>'Founders Edition'],
            ['type'=>'opportunity','title'=>'Class simulator','reason'=>'Test price, attendance, partnership split, fees, and projected profit before publishing a class.','phase'=>'Intelligence','priority'=>'high','status'=>'planned','target_version'=>'Founders Edition'],
            ['type'=>'opportunity','title'=>'Customer CRM','reason'=>'Understand class history, lifetime spend, favorite artists, repeat visits, interests, tags, and follow-up opportunities.','phase'=>'Intelligence','priority'=>'high','status'=>'planned','target_version'=>'Milestone 2'],
            ['type'=>'opportunity','title'=>'Elev8 Brain recommendations','reason'=>'Surface problems and opportunities automatically instead of making the owner hunt through reports.','phase'=>'Automation','priority'=>'high','status'=>'idea','target_version'=>'Milestone 4'],
            ['type'=>'opportunity','title'=>'Marketing Center','reason'=>'Connect class demand, QR scans, referrals, social media, email, flyers, and campaign results.','phase'=>'Growth','priority'=>'medium','status'=>'idea','target_version'=>'Milestone 3'],
            ['type'=>'opportunity','title'=>'Inventory and equipment tracking','reason'=>'Track supplies, class consumption, maintenance, service dates, and replacement needs.','phase'=>'Platform','priority'=>'medium','status'=>'idea','target_version'=>'Milestone 5'],
            ['type'=>'opportunity','title'=>'Studio setup wizard','reason'=>'Make installation and Amelia connection simple for Elev8 and future studios.','phase'=>'Platform','priority'=>'medium','status'=>'idea','target_version'=>'Founders Edition'],
            ['type'=>'idea','title'=>'Marketplace and multi-location platform','reason'=>'Let customers browse artists and classes by medium, level, date, and location.','phase'=>'Platform','priority'=>'low','status'=>'idea','target_version'=>'Future'],
        ];
        $out=[];
        foreach($seed as $i=>$item){$item['id']='seed_'.($i+1);$item['requested_by']='Steve';$item['notes']='';$item['created_at']=$now;$item['updated_at']=$now;$out[$item['id']]=$item;}
        update_option(self::OPTION_DEV_ITEMS,$out,false);
        update_option(self::OPTION_RELEASES,[
            '4.99.0'=>['version'=>'4.99.0','date'=>current_time('Y-m-d'),'added'=>'Vision Edition Development Center, Opportunity Board, Problem Library, philosophy, milestones, roadmap, project health, and release notes.','changed'=>'Elev8 OS is now organized around real problems, opportunities, measurable benefits, and the promise to make Elev8 Arts easier to run.','fixed'=>'','known'=>'Vision modules are roadmap prototypes and planning data. Existing artist, booking, partnership, referral, QR, contact, payment and portal tools remain the working foundation.']
        ],false);
    }

    private static function dev_items(): array {
        self::seed_development_data();
        if (class_exists('Elev8_OS_Portal_Page_Manager')) {
            Elev8_OS_Portal_Page_Manager::activate();
        }
        if (class_exists('Elev8_OS_Waitlist_Module')) {
            Elev8_OS_Waitlist_Module::activate();
        }
        if (class_exists('Elev8_OS_My_Artwork_Module')) {
            Elev8_OS_My_Artwork_Module::activate();
        }
        if (class_exists('Elev8_OS_Gallery_Operations_Service')) {
            Elev8_OS_Gallery_Operations_Service::activate();
        }
        if (class_exists('Elev8_OS_Student_Relationship_Service')) {
            Elev8_OS_Student_Relationship_Service::activate();
        }
        if (class_exists('Elev8_OS_Marketing_Service')) {
            Elev8_OS_Marketing_Service::activate();
        }
        if (class_exists('Elev8_OS_Content_Studio_Service')) {
            Elev8_OS_Content_Studio_Service::activate();
        }
        if (class_exists('Elev8_OS_Daily_Operations_Service')) {
            Elev8_OS_Daily_Operations_Service::activate();
        }
        if (class_exists('Elev8_OS_Access_Service')) {
            Elev8_OS_Access_Service::activate();
        }
        if (class_exists('Elev8_OS_Community_Outreach_Module')) {
            Elev8_OS_Community_Outreach_Module::activate();
        }
        if (class_exists('Elev8_OS_Checkin_Center_Module')) {
            Elev8_OS_Checkin_Center_Module::activate();
        }
        if (class_exists('Elev8_OS_Class_Demand_Module')) {
            Elev8_OS_Class_Demand_Module::activate();
        }
        if (class_exists('Elev8_OS_Opportunity_Module')) {
            Elev8_OS_Opportunity_Module::activate();
        }
        $items=get_option(self::OPTION_DEV_ITEMS,[]);
        return is_array($items)?$items:[];
    }

    public static function save_dev_item(): void {
        if(!current_user_can('manage_options')) wp_die('You do not have permission to do this.');
        check_admin_referer('elev8_os_save_dev_item');
        $items=self::dev_items();
        $id=isset($_POST['item_id'])?sanitize_key(wp_unslash($_POST['item_id'])):'';
        if(!$id) $id='item_'.str_replace('.','_',uniqid('',true));
        $allowed_type=['opportunity','problem','feature','bug','idea']; $allowed_status=['idea','planned','in_progress','testing','released','resolved']; $allowed_priority=['low','medium','high','critical'];
        $old=$items[$id]??[];
        $type=sanitize_key(wp_unslash($_POST['type']??'feature')); if(!in_array($type,$allowed_type,true))$type='feature';
        $status=sanitize_key(wp_unslash($_POST['status']??'idea')); if(!in_array($status,$allowed_status,true))$status='idea';
        $priority=sanitize_key(wp_unslash($_POST['priority']??'medium')); if(!in_array($priority,$allowed_priority,true))$priority='medium';
        $items[$id]=[
            'id'=>$id,'type'=>$type,'title'=>sanitize_text_field(wp_unslash($_POST['title']??'')),
            'reason'=>sanitize_textarea_field(wp_unslash($_POST['reason']??'')),'phase'=>sanitize_text_field(wp_unslash($_POST['phase']??'Foundation')),
            'priority'=>$priority,'status'=>$status,'target_version'=>sanitize_text_field(wp_unslash($_POST['target_version']??'')),
            'requested_by'=>sanitize_text_field(wp_unslash($_POST['requested_by']??'Steve')),'notes'=>sanitize_textarea_field(wp_unslash($_POST['notes']??'')),
            'created_at'=>$old['created_at']??current_time('mysql'),'updated_at'=>current_time('mysql')
        ];
        update_option(self::OPTION_DEV_ITEMS,$items,false);
        wp_safe_redirect(add_query_arg(['page'=>'elev8-development','message'=>'item_saved'],admin_url('admin.php'))); exit;
    }

    public static function delete_dev_item(): void {
        if(!current_user_can('manage_options')) wp_die('You do not have permission to do this.');
        check_admin_referer('elev8_os_delete_dev_item');
        $id=sanitize_key(wp_unslash($_GET['item_id']??'')); $items=self::dev_items(); unset($items[$id]); update_option(self::OPTION_DEV_ITEMS,$items,false);
        wp_safe_redirect(add_query_arg(['page'=>'elev8-development','message'=>'item_deleted'],admin_url('admin.php'))); exit;
    }

    public static function save_release(): void {
        if(!current_user_can('manage_options')) wp_die('You do not have permission to do this.');
        check_admin_referer('elev8_os_save_release');
        $version=sanitize_text_field(wp_unslash($_POST['version']??'')); if(!$version){wp_safe_redirect(add_query_arg(['page'=>'elev8-development'],admin_url('admin.php')));exit;}
        $r=get_option(self::OPTION_RELEASES,[]); if(!is_array($r))$r=[];
        $r[$version]=['version'=>$version,'date'=>sanitize_text_field(wp_unslash($_POST['date']??current_time('Y-m-d'))),'added'=>sanitize_textarea_field(wp_unslash($_POST['added']??'')),'changed'=>sanitize_textarea_field(wp_unslash($_POST['changed']??'')),'fixed'=>sanitize_textarea_field(wp_unslash($_POST['fixed']??'')),'known'=>sanitize_textarea_field(wp_unslash($_POST['known']??''))];
        update_option(self::OPTION_RELEASES,$r,false);
        wp_safe_redirect(add_query_arg(['page'=>'elev8-development','message'=>'release_saved'],admin_url('admin.php'))); exit;
    }

    private static function status_label(string $s): string { return ucwords(str_replace('_',' ',$s)); }

    public static function render_development(): void {
        if(!current_user_can('manage_options'))return;
        $items=self::dev_items(); $releases=get_option(self::OPTION_RELEASES,[]); if(!is_array($releases))$releases=[];
        $phase_order=['Foundation','Intelligence','Growth','Automation','Platform']; $phases=[];
        foreach($phase_order as $phase)$phases[$phase]=[];
        foreach($items as $item){$phase=$item['phase']?:'Foundation'; if(!isset($phases[$phase]))$phases[$phase]=[];$phases[$phase][]=$item;}
        $open_bugs=count(array_filter($items,fn($i)=>$i['type']==='bug'&&!in_array($i['status'],['resolved','released'],true)));
        $completed=count(array_filter($items,fn($i)=>in_array($i['status'],['released','resolved'],true)));
        $total=count($items); $progress=$total?(int)round($completed/$total*100):0;
        $message=sanitize_key(wp_unslash($_GET['message']??''));
        ?>
        <div class="wrap elev8-os elev8-development">
          <div class="elev8-header"><div><h1>Elev8 OS Development Center</h1><p>The Vision Edition roadmap, Opportunity Board, Problem Library, bugs, milestones, and release history.</p></div><span class="elev8-version">Version <?php echo esc_html(ELEV8_OS_VERSION);?></span></div>
          <?php if($message):?><div class="notice notice-success is-dismissible"><p>Development Center updated.</p></div><?php endif;?>
          <div class="elev8-cards">
            <div class="elev8-card"><span>Roadmap complete</span><strong><?php echo esc_html($progress);?>%</strong></div>
            <div class="elev8-card"><span>Open bugs</span><strong><?php echo esc_html($open_bugs);?></strong></div>
            <div class="elev8-card"><span>Completed items</span><strong><?php echo esc_html($completed);?></strong></div>
            <div class="elev8-card"><span>Total tracked</span><strong><?php echo esc_html($total);?></strong></div>
          </div>
          <div class="elev8-panel elev8-vision"><h2>The Elev8 OS Philosophy</h2><p class="elev8-mission"><strong>Mission:</strong> Help creative businesses spend less time managing and more time creating.</p><div class="elev8-principles"><span>Save time</span><span>Increase revenue</span><span>Improve the artist experience</span><span>Improve the customer experience</span><span>Improve decisions</span></div><blockquote><strong>Golden Rule:</strong> We do not build software to add features. We build software to solve real problems.</blockquote><p><strong>Artist promise:</strong> Elev8 OS should help every artist become more successful than they would be on their own.</p><p><strong>North-star question:</strong> Did this version make Elev8 Arts easier to run?</p></div>
          <div class="elev8-grid">
            <div class="elev8-panel"><h2>Vision modules</h2><div class="elev8-module-list"><p><strong>CEO Dashboard</strong><br><small>Revenue, profit, occupancy, payouts, alerts and business health.</small></p><p><strong>Waitlist Engine</strong><br><small>Join waitlist, notify-me demand, seat alerts and add-a-class recommendations.</small></p><p><strong>CRM</strong><br><small>Class history, interests, lifetime spend, last visit, tags and notes.</small></p><p><strong>Class Simulator</strong><br><small>Price, seats, attendance, split, fees, supplies and projected profit.</small></p></div></div>
            <div class="elev8-panel"><h2>Elev8 Brain preview</h2><p>The future assistant will surface what needs attention before the owner has to hunt for it.</p><ul><li>Classes that need promotion</li><li>Waitlists large enough for another session</li><li>Students who have not returned</li><li>Upcoming artist payouts</li><li>Low inventory and equipment maintenance</li></ul><p><span class="elev8-status">Vision prototype — not active yet</span></p></div>
          </div>
          <div class="elev8-panel"><h2>Visual roadmap</h2><div class="elev8-roadmap-grid">
          <?php foreach($phases as $phase=>$phase_items): $done=count(array_filter($phase_items,fn($i)=>in_array($i['status'],['released','resolved'],true)));$pct=count($phase_items)?(int)round($done/count($phase_items)*100):0;?>
            <div class="elev8-roadmap-phase"><div class="elev8-roadmap-title"><strong><?php echo esc_html($phase);?></strong><span><?php echo esc_html($pct);?>%</span></div><div class="elev8-progress"><i style="width:<?php echo esc_attr($pct);?>%"></i></div><small><?php echo esc_html($done.' of '.count($phase_items).' complete');?></small></div>
          <?php endforeach;?></div></div>
          <div class="elev8-grid">
            <div class="elev8-panel"><h2>Add opportunity, problem, bug, or idea</h2><form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><input type="hidden" name="action" value="elev8_os_save_dev_item"><?php wp_nonce_field('elev8_os_save_dev_item');?><label>Type</label><select name="type"><option value="opportunity">Opportunity</option><option value="problem">Problem</option><option value="feature">Feature</option><option value="bug">Bug</option><option value="idea">Future idea</option></select><label>Title</label><input type="text" name="title" required><label>Reason</label><textarea name="reason" rows="4" placeholder="Why does this need to exist?"></textarea><div class="elev8-fields"><div><label>Phase</label><select name="phase"><?php foreach($phase_order as $p):?><option><?php echo esc_html($p);?></option><?php endforeach;?></select></div><div><label>Priority</label><select name="priority"><option>low</option><option selected>medium</option><option>high</option><option>critical</option></select></div><div><label>Status</label><select name="status"><option value="idea">Idea</option><option value="planned">Planned</option><option value="in_progress">In progress</option><option value="testing">Testing</option><option value="released">Released</option><option value="resolved">Resolved</option></select></div><div><label>Target version</label><input type="text" name="target_version" placeholder="5.1"></div></div><label>Requested by</label><input type="text" name="requested_by" value="Steve"><label>Notes</label><textarea name="notes" rows="3"></textarea><p><button class="button button-primary">Add to roadmap</button></p></form></div>
            <div class="elev8-panel"><h2>Project health</h2><p><strong>Plugin version:</strong> <?php echo esc_html(ELEV8_OS_VERSION);?></p><p><strong>Database storage:</strong> WordPress options (no new custom tables yet)</p><p><strong>Amelia integration:</strong> Read-only booking, service, provider, and payment data</p><p><strong>Next planned release:</strong> Founders Edition architecture and GitHub foundation</p><p><strong>Known limitation:</strong> Amelia data structures can vary by booking type and plugin version; diagnostics remain important.</p></div>
          </div>
          <div class="elev8-panel"><h2>Tracked work</h2><div class="elev8-dev-list">
          <?php foreach($items as $id=>$item):?><details class="elev8-dev-item" <?php echo $item['status']==='in_progress'?'open':'';?>><summary><span class="elev8-badge elev8-<?php echo esc_attr($item['type']);?>"><?php echo esc_html(self::status_label($item['type']));?></span><strong><?php echo esc_html($item['title']);?></strong><span class="elev8-status"><?php echo esc_html(self::status_label($item['status']));?></span><span class="elev8-priority elev8-priority-<?php echo esc_attr($item['priority']);?>"><?php echo esc_html($item['priority']);?></span></summary><form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><input type="hidden" name="action" value="elev8_os_save_dev_item"><input type="hidden" name="item_id" value="<?php echo esc_attr($id);?>"><?php wp_nonce_field('elev8_os_save_dev_item');?><div class="elev8-fields"><div><label>Type</label><select name="type"><?php foreach(['opportunity','problem','feature','bug','idea'] as $v):?><option value="<?php echo esc_attr($v);?>" <?php selected($item['type'],$v);?>><?php echo esc_html(self::status_label($v));?></option><?php endforeach;?></select></div><div><label>Status</label><select name="status"><?php foreach(['idea','planned','in_progress','testing','released','resolved'] as $v):?><option value="<?php echo esc_attr($v);?>" <?php selected($item['status'],$v);?>><?php echo esc_html(self::status_label($v));?></option><?php endforeach;?></select></div><div><label>Phase</label><input type="text" name="phase" value="<?php echo esc_attr($item['phase']);?>"></div><div><label>Priority</label><select name="priority"><?php foreach(['low','medium','high','critical'] as $v):?><option <?php selected($item['priority'],$v);?>><?php echo esc_html($v);?></option><?php endforeach;?></select></div></div><label>Title</label><input type="text" name="title" value="<?php echo esc_attr($item['title']);?>"><label>Reason</label><textarea name="reason" rows="3"><?php echo esc_textarea($item['reason']);?></textarea><div class="elev8-fields"><div><label>Target version</label><input type="text" name="target_version" value="<?php echo esc_attr($item['target_version']);?>"></div><div><label>Requested by</label><input type="text" name="requested_by" value="<?php echo esc_attr($item['requested_by']);?>"></div></div><label>Notes</label><textarea name="notes" rows="3"><?php echo esc_textarea($item['notes']);?></textarea><p><button class="button button-primary">Save item</button> <a class="button button-link-delete" href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action'=>'elev8_os_delete_dev_item','item_id'=>$id],admin_url('admin-post.php')),'elev8_os_delete_dev_item'));?>">Delete</a></p></form></details><?php endforeach;?></div></div>
          <div class="elev8-grid"><div class="elev8-panel"><h2>Add release notes</h2><form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><input type="hidden" name="action" value="elev8_os_save_release"><?php wp_nonce_field('elev8_os_save_release');?><label>Version</label><input type="text" name="version" placeholder="5.1.0" required><label>Date</label><input type="date" name="date" value="<?php echo esc_attr(current_time('Y-m-d'));?>"><label>Added</label><textarea name="added" rows="3"></textarea><label>Changed</label><textarea name="changed" rows="3"></textarea><label>Fixed</label><textarea name="fixed" rows="3"></textarea><label>Known issues</label><textarea name="known" rows="3"></textarea><p><button class="button button-primary">Save release</button></p></form></div><div class="elev8-panel"><h2>Release history</h2><?php krsort($releases);foreach($releases as $release):?><div class="elev8-release"><h3><?php echo esc_html($release['version']);?> <small><?php echo esc_html($release['date']);?></small></h3><?php foreach(['added'=>'Added','changed'=>'Changed','fixed'=>'Fixed','known'=>'Known issues'] as $key=>$label):if(!empty($release[$key])):?><p><strong><?php echo esc_html($label);?>:</strong><br><?php echo nl2br(esc_html($release[$key]));?></p><?php endif;endforeach;?></div><?php endforeach;?></div></div>
        </div><?php
    }

}

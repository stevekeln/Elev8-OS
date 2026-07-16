<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Artist-facing class schedule backed by verified Amelia data.
 */
final class Elev8_OS_My_Classes_Module {

    private const SHORTCODE = 'elev8_artist_classes';
    private const EMPLOYEE_META_KEY = 'elev8_os_amelia_employee_id';

    public static function init(): void {
        add_action('init', [__CLASS__, 'register_shortcode']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    public static function register_shortcode(): void {
        add_shortcode(self::SHORTCODE, [__CLASS__, 'shortcode']);
    }

    /**
     * Verified, reusable artist schedule snapshot for portal dashboards.
     *
     * @return array<string,mixed>
     */
    public static function get_dashboard_snapshot(WP_User $user): array {
        $artist = self::find_artist_for_user($user);

        if (!$artist) {
            return self::unavailable_result(__('Your WordPress account is not connected to an Amelia artist.', 'elev8-os'));
        }

        $result = self::load_classes((int) $artist['id']);
        $result['artist'] = $artist;

        return $result;
    }

    public static function enqueue_assets(): void {
        if (!Elev8_OS_Portal_Page_Manager::is_current_page('classes')) {
            return;
        }

        wp_enqueue_style('dashicons');
        wp_enqueue_style(
            'elev8-os-my-classes',
            ELEV8_OS_URL . 'assets/css/artist-classes.css',
            ['elev8-os-artist-portal'],
            ELEV8_OS_VERSION
        );
        wp_enqueue_script(
            'elev8-os-artist-classes',
            ELEV8_OS_URL . 'assets/js/artist-classes.js',
            [],
            ELEV8_OS_VERSION,
            true
        );
    }

    public static function shortcode(): string {
        if (!is_user_logged_in()) {
            return sprintf(
                '<div class="elev8-dashboard-login"><p>%1$s</p><p><a class="button" href="%2$s">%3$s</a></p></div>',
                esc_html__('Please log in to view your classes.', 'elev8-os'),
                esc_url(wp_login_url(Elev8_OS_Portal_Page_Manager::get_url('classes'))),
                esc_html__('Log In', 'elev8-os')
            );
        }

        $artist = self::find_artist_for_user(wp_get_current_user());
        $result = $artist ? self::load_classes((int) $artist['id']) : self::unavailable_result(__('Your WordPress account is not connected to an Amelia artist.', 'elev8-os'));

        ob_start();
        ?>
        <div class="elev8-artist-dashboard elev8-my-classes">
            <?php Elev8_OS_Artist_Portal_Module::render_navigation('classes'); ?>

            <header class="elev8-dashboard-header">
                <div>
                    <p class="elev8-eyebrow"><?php esc_html_e('Artist Portal', 'elev8-os'); ?></p>
                    <h1><?php esc_html_e('My Classes', 'elev8-os'); ?></h1>
                    <p><?php esc_html_e('Your verified Amelia schedule, enrollment, and available seats in one place.', 'elev8-os'); ?></p>
                </div>
                <span class="elev8-dashboard-badge"><?php esc_html_e('Amelia connected', 'elev8-os'); ?></span>
            </header>

            <?php if (!$result['available']) : ?>
                <div class="elev8-dashboard-warning">
                    <p><strong><?php esc_html_e('Class information is unavailable.', 'elev8-os'); ?></strong><br><?php echo esc_html($result['reason']); ?></p>
                </div>
            <?php else : ?>
                <?php $summary = $result['summary']; ?>
                <section class="elev8-classes-summary" aria-label="<?php esc_attr_e('Class summary', 'elev8-os'); ?>">
                    <?php self::render_summary_card(__('Upcoming class dates', 'elev8-os'), $summary['upcoming_count'], __('Verified future Amelia appointments', 'elev8-os')); ?>
                    <?php self::render_summary_card(__('Students enrolled', 'elev8-os'), $summary['student_count'], __('Across upcoming classes', 'elev8-os')); ?>
                    <?php self::render_summary_card(__('Available seats', 'elev8-os'), $summary['seats_available'], $summary['seats_available'] === null ? __('Unavailable because capacity was not detected', 'elev8-os') : __('Across classes with verified capacity', 'elev8-os')); ?>
                    <?php self::render_summary_card(__('Booked value', 'elev8-os'), $summary['booked_value'], $summary['booked_value'] === null ? __('Unavailable because booking amounts were not detected', 'elev8-os') : __('Booked value, not recognized revenue', 'elev8-os'), true); ?>
                </section>

                <section class="elev8-classes-section">
                    <div class="elev8-panel-heading">
                        <div>
                            <p class="elev8-eyebrow"><?php esc_html_e('Schedule', 'elev8-os'); ?></p>
                            <h2><?php esc_html_e('Upcoming Classes', 'elev8-os'); ?></h2>
                        </div>
                    </div>
                    <?php self::render_class_list($result['upcoming'], true); ?>
                </section>

                <section class="elev8-classes-section">
                    <div class="elev8-panel-heading">
                        <div>
                            <p class="elev8-eyebrow"><?php esc_html_e('History', 'elev8-os'); ?></p>
                            <h2><?php esc_html_e('Recent Past Classes', 'elev8-os'); ?></h2>
                        </div>
                    </div>
                    <?php self::render_class_list($result['past'], false); ?>
                </section>
            <?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /** @param mixed $value */
    private static function render_summary_card(string $label, $value, string $description, bool $money = false): void {
        $display = __('Unavailable', 'elev8-os');
        if ($value !== null) {
            $display = $money ? self::format_money((float) $value) : number_format_i18n((int) $value);
        }
        ?>
        <article class="elev8-class-summary-card">
            <span><?php echo esc_html($label); ?></span>
            <strong><?php echo esc_html($display); ?></strong>
            <p><?php echo esc_html($description); ?></p>
        </article>
        <?php
    }

    /** @param array<int,array<string,mixed>> $classes */
    private static function render_class_list(array $classes, bool $upcoming): void {
        if (!$classes) {
            ?>
            <div class="elev8-class-empty">
                <span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>
                <h3><?php echo esc_html($upcoming ? __('No upcoming classes found', 'elev8-os') : __('No recent past classes found', 'elev8-os')); ?></h3>
                <p><?php echo esc_html($upcoming ? __('When Amelia assigns a future class to you, it will appear here.', 'elev8-os') : __('Completed classes will appear here after their scheduled date.', 'elev8-os')); ?></p>
            </div>
            <?php
            return;
        }

        echo '<div class="elev8-class-list">';
        foreach ($classes as $class) {
            self::render_class_card($class, $upcoming);
        }
        echo '</div>';
    }

    /** @param array<string,mixed> $class */
    private static function render_class_card(array $class, bool $upcoming): void {
        $timestamp = strtotime((string) $class['start']);
        $month = $timestamp ? wp_date('M', $timestamp) : '';
        $day = $timestamp ? wp_date('j', $timestamp) : '';
        $date_time = $timestamp ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), $timestamp) : (string) $class['start'];
        ?>
        <article class="elev8-class-card">
            <div class="elev8-class-date"><span><?php echo esc_html($month); ?></span><strong><?php echo esc_html($day); ?></strong></div>
            <div class="elev8-class-main">
                <div class="elev8-class-title-row">
                    <div>
                        <h3><?php echo esc_html((string) $class['name']); ?></h3>
                        <p><span class="dashicons dashicons-clock" aria-hidden="true"></span> <?php echo esc_html($date_time); ?></p>
                        <?php if ($class['location'] !== '') : ?><p><span class="dashicons dashicons-location" aria-hidden="true"></span> <?php echo esc_html((string) $class['location']); ?></p><?php endif; ?>
                    </div>
                    <span class="elev8-class-status <?php echo $upcoming ? 'is-upcoming' : 'is-complete'; ?>"><?php echo esc_html($upcoming ? __('Upcoming', 'elev8-os') : __('Completed', 'elev8-os')); ?></span>
                </div>

                <div class="elev8-class-facts">
                    <div><span><?php esc_html_e('Students', 'elev8-os'); ?></span><strong><?php echo esc_html(number_format_i18n((int) $class['students'])); ?></strong></div>
                    <div><span><?php esc_html_e('Capacity', 'elev8-os'); ?></span><strong><?php echo $class['capacity'] === null ? esc_html__('Unavailable', 'elev8-os') : esc_html(number_format_i18n((int) $class['capacity'])); ?></strong></div>
                    <div><span><?php esc_html_e('Seats left', 'elev8-os'); ?></span><strong><?php echo $class['seats_left'] === null ? esc_html__('Unavailable', 'elev8-os') : esc_html(number_format_i18n((int) $class['seats_left'])); ?></strong></div>
                    <div><span><?php esc_html_e('Booked value', 'elev8-os'); ?></span><strong><?php echo $class['booked_value'] === null ? esc_html__('Unavailable', 'elev8-os') : esc_html(self::format_money((float) $class['booked_value'])); ?></strong></div>
                </div>

                <div class="elev8-class-actions">
                    <?php if ($class['booking_url'] !== '') : ?>
                        <button type="button" class="elev8-copy-class-link" data-link="<?php echo esc_attr((string) $class['booking_url']); ?>"><?php esc_html_e('Copy Booking Link', 'elev8-os'); ?></button>
                        <a href="<?php echo esc_url((string) $class['booking_url']); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Open Booking Page', 'elev8-os'); ?></a>
                    <?php else : ?>
                        <span class="elev8-action-unavailable"><?php esc_html_e('Booking link unavailable', 'elev8-os'); ?></span>
                    <?php endif; ?>
                    <?php if ((int) $class['id'] > 0) : ?>
                        <a href="<?php echo esc_url(add_query_arg('appointment_id', (int) $class['id'], Elev8_OS_Portal_Page_Manager::get_url('students'))); ?>"><?php esc_html_e('View Students', 'elev8-os'); ?></a>
                    <?php else : ?>
                        <span class="elev8-action-unavailable"><?php esc_html_e('Roster available after Amelia creates this class date', 'elev8-os'); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </article>
        <?php
    }

    /** @return array<string,mixed> */
    private static function load_classes(int $artist_id): array {
        global $wpdb;
        $appointments = $wpdb->prefix . 'amelia_appointments';
        if (!self::table_exists($appointments)) {
            return self::unavailable_result(__('The Amelia appointments table was not found.', 'elev8-os'));
        }

        $appointment_columns = self::table_columns($appointments);
        $id_col = self::first_existing_column($appointment_columns, ['id']);
        $provider_col = self::first_existing_column($appointment_columns, ['providerId', 'provider_id', 'employeeId']);
        $start_col = self::first_existing_column($appointment_columns, ['bookingStart', 'booking_start', 'start']);
        if (!$id_col || !$provider_col || !$start_col) {
            return self::unavailable_result(__('Required Amelia appointment columns could not be verified.', 'elev8-os'));
        }

        $service_col = self::first_existing_column($appointment_columns, ['serviceId', 'service_id']);
        $location_col = self::first_existing_column($appointment_columns, ['locationId', 'location_id']);
        $capacity_col = self::first_existing_column($appointment_columns, ['maxCapacity', 'max_capacity', 'capacity']);
        $status_col = self::first_existing_column($appointment_columns, ['status']);

        $select = ["a.`{$id_col}` AS appointment_id", "a.`{$start_col}` AS booking_start"];
        $select[] = $service_col ? "a.`{$service_col}` AS service_id" : 'NULL AS service_id';
        $select[] = $location_col ? "a.`{$location_col}` AS location_id" : 'NULL AS location_id';
        $select[] = $capacity_col ? "a.`{$capacity_col}` AS appointment_capacity" : 'NULL AS appointment_capacity';
        $select[] = $status_col ? "a.`{$status_col}` AS appointment_status" : "'' AS appointment_status";

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT ' . implode(', ', $select) . " FROM `{$appointments}` a WHERE a.`{$provider_col}` = %d ORDER BY a.`{$start_col}` DESC LIMIT 250",
                $artist_id
            ),
            ARRAY_A
        );
        if (!is_array($rows)) {
            return self::unavailable_result(__('Amelia appointments could not be read.', 'elev8-os'));
        }

        $service_names = self::service_map();
        $service_capacities = self::service_capacity_map();
        $location_names = self::location_map();
        $bookings = self::booking_aggregates();
        $booking_base = self::artist_booking_url();
        $now = current_time('timestamp');
        $upcoming = [];
        $past = [];

        foreach ($rows as $row) {
            $status = strtolower((string) ($row['appointment_status'] ?? ''));
            if (in_array($status, ['canceled', 'cancelled', 'rejected'], true)) {
                continue;
            }
            $appointment_id = (int) ($row['appointment_id'] ?? 0);
            $service_id = (int) ($row['service_id'] ?? 0);
            $capacity = self::nullable_positive_int($row['appointment_capacity'] ?? null);
            if ($capacity === null && isset($service_capacities[$service_id])) {
                $capacity = $service_capacities[$service_id];
            }
            $aggregate = $bookings[$appointment_id] ?? ['students' => 0, 'booked_value' => null];
            $students = (int) $aggregate['students'];
            $start = (string) ($row['booking_start'] ?? '');
            $timestamp = strtotime($start);
            $class = [
                'id' => $appointment_id,
                'name' => $service_names[$service_id] ?? __('Class', 'elev8-os'),
                'start' => $start,
                'location' => $location_names[(int) ($row['location_id'] ?? 0)] ?? '',
                'students' => $students,
                'capacity' => $capacity,
                'seats_left' => $capacity === null ? null : max(0, $capacity - $students),
                'booked_value' => $aggregate['booked_value'],
                'booking_url' => $booking_base,
            ];
            // Upcoming classes are normalized through the shared Class Discovery
            // service below. Keep this direct appointment query for class history only.
            if ($timestamp === false || $timestamp < $now) {
                $past[] = $class;
            }
        }

        // Appointment-first discovery with verified service-date fallback. This is
        // required for recurring Amelia services whose future dates exist in the
        // assigned service record before Amelia creates appointment rows.
        if (class_exists('Elev8_OS_Class_Discovery')) {
            foreach (Elev8_OS_Class_Discovery::upcoming_for_employee($artist_id) as $occurrence) {
                $appointment_id = max(0, (int) ($occurrence['appointment_id'] ?? 0));
                $aggregate = $appointment_id > 0
                    ? ($bookings[$appointment_id] ?? ['students' => 0, 'booked_value' => null])
                    : ['students' => 0, 'booked_value' => null];
                $start = (string) ($occurrence['sort_start'] ?? '');
                if ($start === '') { continue; }

                $upcoming[] = [
                    'id' => $appointment_id,
                    'name' => (string) ($occurrence['name'] ?? __('Class', 'elev8-os')),
                    'start' => $start,
                    'date_only' => !empty($occurrence['date_only']),
                    'location' => '',
                    'students' => max(0, (int) ($occurrence['booked'] ?? $aggregate['students'] ?? 0)),
                    'capacity' => isset($occurrence['capacity']) && $occurrence['capacity'] !== null ? (int) $occurrence['capacity'] : null,
                    'seats_left' => isset($occurrence['seats_left']) && $occurrence['seats_left'] !== null ? (int) $occurrence['seats_left'] : null,
                    'booked_value' => $aggregate['booked_value'] ?? null,
                    'booking_url' => $booking_base,
                    'source' => (string) ($occurrence['source'] ?? 'unknown'),
                ];
            }
        }

        usort($upcoming, static fn(array $a, array $b): int => strcmp((string) $a['start'], (string) $b['start']));
        usort($past, static fn(array $a, array $b): int => strcmp((string) $b['start'], (string) $a['start']));
        $past = array_slice($past, 0, 12);

        $student_count = 0;
        $seats_available = 0;
        $capacity_available = false;
        $booked_value = 0.0;
        $value_available = false;
        foreach ($upcoming as $class) {
            $student_count += (int) $class['students'];
            if ($class['seats_left'] !== null) {
                $seats_available += (int) $class['seats_left'];
                $capacity_available = true;
            }
            if ($class['booked_value'] !== null) {
                $booked_value += (float) $class['booked_value'];
                $value_available = true;
            }
        }

        return [
            'available' => true,
            'reason' => '',
            'summary' => [
                'upcoming_count' => count($upcoming),
                'student_count' => $student_count,
                'seats_available' => $capacity_available ? $seats_available : null,
                'booked_value' => $value_available ? $booked_value : null,
            ],
            'upcoming' => $upcoming,
            'past' => $past,
        ];
    }

    /** @return array<int,array{students:int,booked_value:?float}> */
    private static function booking_aggregates(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'amelia_customer_bookings';
        if (!self::table_exists($table)) {
            return [];
        }
        $columns = self::table_columns($table);
        $appointment_col = self::first_existing_column($columns, ['appointmentId', 'appointment_id']);
        if (!$appointment_col) {
            return [];
        }
        $persons_col = self::first_existing_column($columns, ['persons', 'personsCount', 'persons_count']);
        $price_col = self::first_existing_column($columns, ['price', 'amount', 'paymentAmount', 'payment_amount']);
        $status_col = self::first_existing_column($columns, ['status']);
        $status_sql = $status_col ? " WHERE LOWER(COALESCE(`{$status_col}`,'')) NOT IN ('canceled','cancelled','rejected')" : '';
        $select_students = $persons_col ? "SUM(COALESCE(`{$persons_col}`,1))" : 'COUNT(*)';
        $select_value = $price_col ? ", SUM(COALESCE(`{$price_col}`,0)) AS booked_value" : ', NULL AS booked_value';
        $rows = $wpdb->get_results("SELECT `{$appointment_col}` AS appointment_id, {$select_students} AS students{$select_value} FROM `{$table}`{$status_sql} GROUP BY `{$appointment_col}`", ARRAY_A);
        $map = [];
        foreach ((array) $rows as $row) {
            $map[(int) $row['appointment_id']] = [
                'students' => max(0, (int) $row['students']),
                'booked_value' => $price_col ? (float) $row['booked_value'] : null,
            ];
        }
        return $map;
    }

    /** @return array<int,string> */
    private static function service_map(): array {
        return self::simple_id_label_map('amelia_services', ['name', 'title']);
    }

    /** @return array<int,int> */
    private static function service_capacity_map(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'amelia_services';
        if (!self::table_exists($table)) { return []; }
        $columns = self::table_columns($table);
        $capacity_col = self::first_existing_column($columns, ['maxCapacity', 'max_capacity', 'capacity']);
        if (!$capacity_col || !in_array('id', $columns, true)) { return []; }
        $rows = $wpdb->get_results("SELECT `id`, `{$capacity_col}` AS capacity FROM `{$table}`", ARRAY_A);
        $map = [];
        foreach ((array) $rows as $row) {
            $capacity = self::nullable_positive_int($row['capacity'] ?? null);
            if ($capacity !== null) { $map[(int) $row['id']] = $capacity; }
        }
        return $map;
    }

    /** @return array<int,string> */
    private static function location_map(): array {
        return self::simple_id_label_map('amelia_locations', ['name', 'address']);
    }

    /** @param string[] $label_candidates @return array<int,string> */
    private static function simple_id_label_map(string $suffix, array $label_candidates): array {
        global $wpdb;
        $table = $wpdb->prefix . $suffix;
        if (!self::table_exists($table)) { return []; }
        $columns = self::table_columns($table);
        $label_col = self::first_existing_column($columns, $label_candidates);
        if (!$label_col || !in_array('id', $columns, true)) { return []; }
        $rows = $wpdb->get_results("SELECT `id`, `{$label_col}` AS label FROM `{$table}`", ARRAY_A);
        $map = [];
        foreach ((array) $rows as $row) { $map[(int) $row['id']] = (string) $row['label']; }
        return $map;
    }

    private static function artist_booking_url(): string {
        $user = wp_get_current_user();
        $url = esc_url_raw((string) get_user_meta($user->ID, 'elev8_os_artist_booking_url', true));
        return $url;
    }

    /** @return array<string,mixed>|null */
    private static function find_artist_for_user(WP_User $user): ?array {
        global $wpdb;
        $table = $wpdb->prefix . 'amelia_users';
        if (!self::table_exists($table)) { return null; }
        $columns = self::table_columns($table);
        if (!in_array('id', $columns, true)) { return null; }
        $select = ['id'];
        foreach (['firstName', 'lastName', 'email'] as $column) {
            if (in_array($column, $columns, true)) { $select[] = $column; }
        }
        $select_sql = implode(', ', array_map(static fn(string $column): string => "`{$column}`", $select));
        $type_sql = in_array('type', $columns, true) ? " AND LOWER(COALESCE(`type`,'')) IN ('provider','employee')" : '';
        $mapped_id = max(0, (int) get_user_meta($user->ID, self::EMPLOYEE_META_KEY, true));
        if ($mapped_id > 0) {
            $row = $wpdb->get_row($wpdb->prepare("SELECT {$select_sql} FROM `{$table}` WHERE `id`=%d{$type_sql} LIMIT 1", $mapped_id), ARRAY_A);
            if (is_array($row)) { return $row; }
        }
        $email = sanitize_email((string) $user->user_email);
        if ($email === '' || !in_array('email', $columns, true)) { return null; }
        $row = $wpdb->get_row($wpdb->prepare("SELECT {$select_sql} FROM `{$table}` WHERE LOWER(`email`)=LOWER(%s){$type_sql} LIMIT 1", $email), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    /** @return array<string,mixed> */
    private static function unavailable_result(string $reason): array {
        return ['available' => false, 'reason' => $reason, 'summary' => [], 'upcoming' => [], 'past' => []];
    }

    /** @return string[] */
    private static function table_columns(string $table): array {
        global $wpdb;
        $columns = $wpdb->get_col("DESCRIBE `{$table}`", 0);
        return is_array($columns) ? array_map('strval', $columns) : [];
    }

    /** @param string[] $available @param string[] $candidates */
    private static function first_existing_column(array $available, array $candidates): ?string {
        foreach ($candidates as $candidate) {
            if (in_array($candidate, $available, true)) { return $candidate; }
        }
        return null;
    }

    private static function table_exists(string $table): bool {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    /** @param mixed $value */
    private static function nullable_positive_int($value): ?int {
        if ($value === null || $value === '') { return null; }
        $number = (int) $value;
        return $number > 0 ? $number : null;
    }

    private static function format_money(float $value): string {
        if (function_exists('wc_price')) {
            return wp_strip_all_tags((string) wc_price($value));
        }
        return '$' . number_format_i18n($value, 2);
    }
}

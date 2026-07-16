<?php
/**
 * Elev8 OS Business Intelligence service.
 *
 * Provides reusable, read-only business metrics from discovered Amelia data.
 * Every metric fails closed when the required schema cannot be verified.
 *
 * @package Elev8OS
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Elev8_OS_Business_Intelligence {

    /**
     * Return verified opportunity intelligence from the Opportunity Engine.
     *
     * @return array<string,mixed>
     */
    public static function get_opportunity_report(): array {
        if (!class_exists('Elev8_OS_Opportunity_Service')) {
            return [
                'opportunities' => [],
                'metrics' => [
                    'opportunity_count' => self::unavailable_metric(__('Opportunity Engine is unavailable.', 'elev8-os')),
                    'people_waiting' => self::unavailable_metric(__('Opportunity Engine is unavailable.', 'elev8-os')),
                    'seats_waiting' => self::unavailable_metric(__('Opportunity Engine is unavailable.', 'elev8-os')),
                    'classes_without_teacher' => self::unavailable_metric(__('Opportunity Engine is unavailable.', 'elev8-os')),
                    'potential_revenue' => self::unavailable_metric(__('Opportunity Engine is unavailable.', 'elev8-os')),
                ],
            ];
        }
        return Elev8_OS_Opportunity_Service::intelligence();
    }

    /**
     * Return the owner-facing Business Intelligence report.
     *
     * @return array<string,mixed>
     */
    public static function get_dashboard_report(): array {
        $schema = self::discover_schema();
        $now = current_time('timestamp');
        $today_start = wp_date('Y-m-d 00:00:00', $now);
        $tomorrow_start = wp_date('Y-m-d 00:00:00', strtotime('+1 day', $now));
        $month_start = wp_date('Y-m-01 00:00:00', $now);
        $next_month_start = wp_date('Y-m-01 00:00:00', strtotime('first day of next month', $now));
        $previous_month_start = wp_date('Y-m-01 00:00:00', strtotime('first day of previous month', $now));

        $classes_today = self::scheduled_class_count($schema, $today_start, $tomorrow_start);
        $classes_month = self::scheduled_class_count($schema, $month_start, $next_month_start);
        $students_today = self::student_count($schema, $today_start, $tomorrow_start);
        $students_month = self::student_count($schema, $month_start, $next_month_start);
        $pending = self::booking_status_count($schema, ['pending']);
        $cancelled = self::booking_status_count($schema, ['canceled', 'cancelled', 'rejected']);
        $all_status_bookings = self::booking_status_count($schema, null);
        $booked_value_month = self::booked_value($schema, $month_start, $next_month_start);
        $booked_value_previous_month = self::booked_value($schema, $previous_month_start, $month_start);
        $upcoming_booked_value = self::booked_value($schema, current_time('mysql'), null);
        $bookings_month = self::booking_count_in_range($schema, $month_start, $next_month_start);
        $upcoming = self::upcoming_class_dates($schema, 10);

        return [
            'generated_at' => current_time('mysql'),
            'timezone'     => wp_timezone_string(),
            'metrics'      => [
                'classes_today' => $classes_today,
                'classes_month' => $classes_month,
                'students_today' => $students_today,
                'students_month' => $students_month,
                'pending_bookings' => $pending,
                'cancelled_bookings' => $cancelled,
                'cancellation_rate' => self::rate_metric(
                    $cancelled,
                    $all_status_bookings,
                    __('Cancelled bookings divided by all bookings with a detected status.', 'elev8-os')
                ),
                'average_class_size' => self::average_metric(
                    $students_month,
                    $classes_month,
                    __('Students booked this month divided by scheduled class occurrences this month.', 'elev8-os')
                ),

                // Backward-compatible V1 key.
                'booked_value' => $booked_value_month,

                // Business Intelligence V2 financial metrics.
                'booked_value_month' => $booked_value_month,
                'booked_value_previous_month' => $booked_value_previous_month,
                'booked_value_change' => self::financial_change_metric(
                    $booked_value_month,
                    $booked_value_previous_month
                ),
                'upcoming_booked_value' => $upcoming_booked_value,
                'average_ticket_value' => self::financial_ratio_metric(
                    $booked_value_month,
                    $bookings_month,
                    __('Booked value this month divided by non-cancelled booking records this month.', 'elev8-os')
                ),
                'booked_value_per_student' => self::financial_ratio_metric(
                    $booked_value_month,
                    $students_month,
                    __('Booked value this month divided by students booked this month.', 'elev8-os')
                ),
                'recognized_revenue' => self::unavailable_metric(
                    __('Recognized revenue requires a dedicated revenue-recognition policy and is intentionally not calculated by the Business Intelligence service.', 'elev8-os')
                ),
            ],
            'upcoming_class_dates' => $upcoming,
            'diagnostics' => [
                'access' => 'read-only',
                'amelia_tables_discovered' => count($schema['tables']),
                'detected_sources' => $schema['detected_sources'],
                'notes' => array_values(array_unique($schema['notes'])),
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function discover_schema(): array {
        global $wpdb;

        $tables = [];
        $detected_sources = [];
        $notes = [];

        $query = $wpdb->prepare(
            'SELECT TABLE_NAME
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = %s
               AND TABLE_NAME LIKE %s
             ORDER BY TABLE_NAME ASC',
            (string) $wpdb->dbname,
            '%' . $wpdb->esc_like('amelia') . '%'
        );

        $table_names = $wpdb->get_col($query);
        if (!is_array($table_names)) {
            $table_names = [];
        }

        foreach ($table_names as $table_name) {
            $table_name = (string) $table_name;
            if (!self::is_safe_identifier($table_name)) {
                continue;
            }

            $columns = self::column_metadata($table_name);
            $column_map = [];

            foreach ($columns as $column) {
                $name = isset($column['COLUMN_NAME']) ? (string) $column['COLUMN_NAME'] : '';
                if ($name !== '') {
                    $column_map[strtolower($name)] = $column;
                }
            }

            $short_name = self::short_table_name($table_name);
            $tables[$short_name] = [
                'name' => $table_name,
                'short_name' => $short_name,
                'columns' => $column_map,
            ];
        }

        foreach (['amelia_events_periods', 'amelia_appointments', 'amelia_customer_bookings', 'amelia_payments'] as $source) {
            if (isset($tables[$source])) {
                $detected_sources[] = $source;
            }
        }

        if ($tables === []) {
            $notes[] = __('No Amelia database tables were discovered.', 'elev8-os');
        }

        return [
            'tables' => $tables,
            'detected_sources' => $detected_sources,
            'notes' => $notes,
        ];
    }

    /**
     * Count scheduled class occurrences from every independently verified source.
     *
     * @param array<string,mixed> $schema
     * @return array<string,mixed>
     */
    private static function scheduled_class_count(array &$schema, string $start, string $end): array {
        $counts = [];
        $reasons = [];

        $periods = self::table($schema, 'amelia_events_periods');
        if ($periods) {
            $date_column = self::unique_column(
                $periods,
                ['periodStart', 'start', 'startDate', 'startDateTime', 'dateTime']
            );

            if ($date_column) {
                $counts[] = self::count_rows_in_range($periods['name'], $date_column, $start, $end);
            } else {
                $reasons[] = __('Event periods table was found, but no reliable start-date column was detected.', 'elev8-os');
            }
        }

        $appointments = self::table($schema, 'amelia_appointments');
        if ($appointments) {
            $date_column = self::unique_column(
                $appointments,
                ['bookingStart', 'start', 'startDate', 'startDateTime', 'dateTime']
            );

            if ($date_column) {
                $counts[] = self::count_rows_in_range($appointments['name'], $date_column, $start, $end);
            } else {
                $reasons[] = __('Appointments table was found, but no reliable start-date column was detected.', 'elev8-os');
            }
        }

        if ($counts === []) {
            return self::unavailable_metric(
                $reasons !== [] ? implode(' ', $reasons) : __('No verified class schedule source was detected.', 'elev8-os')
            );
        }

        return self::available_metric(
            array_sum($counts),
            'high',
            __('Scheduled occurrences detected from Amelia event periods and/or appointments.', 'elev8-os')
        );
    }

    /**
     * Count booked students linked to appointments in a date range.
     *
     * @param array<string,mixed> $schema
     * @return array<string,mixed>
     */
    private static function student_count(array &$schema, string $start, string $end): array {
        global $wpdb;

        $bookings = self::table($schema, 'amelia_customer_bookings');
        $appointments = self::table($schema, 'amelia_appointments');

        if (!$bookings || !$appointments) {
            return self::unavailable_metric(
                __('Reliable student counts require both the customer bookings and appointments tables.', 'elev8-os')
            );
        }

        $booking_appointment = self::unique_column($bookings, ['appointmentId', 'appointment_id']);
        $appointment_id = self::unique_column($appointments, ['id']);
        $appointment_start = self::unique_column(
            $appointments,
            ['bookingStart', 'start', 'startDate', 'startDateTime', 'dateTime']
        );

        if (!$booking_appointment || !$appointment_id || !$appointment_start) {
            return self::unavailable_metric(
                __('The bookings-to-appointments relationship or appointment start date could not be verified.', 'elev8-os')
            );
        }

        $persons = self::unique_column($bookings, ['persons', 'personsCount', 'persons_count', 'quantity']);
        $status = self::unique_column($bookings, ['status']);

        $value_expression = $persons
            ? 'COALESCE(b.`' . $persons . '`, 1)'
            : '1';

        $status_sql = '';
        if ($status) {
            $status_sql = " AND LOWER(COALESCE(b.`{$status}`, '')) NOT IN ('canceled', 'cancelled', 'rejected')";
        }

        $sql = $wpdb->prepare(
            "SELECT SUM({$value_expression})
             FROM `{$bookings['name']}` b
             INNER JOIN `{$appointments['name']}` a
                ON b.`{$booking_appointment}` = a.`{$appointment_id}`
             WHERE a.`{$appointment_start}` >= %s
               AND a.`{$appointment_start}` < %s
               {$status_sql}",
            $start,
            $end
        );

        $value = $wpdb->get_var($sql);
        if ($value === null) {
            $value = 0;
        }

        return self::available_metric(
            is_numeric($value) ? (int) $value : 0,
            $persons ? 'high' : 'medium',
            $persons
                ? __('Student quantity uses the detected persons field and excludes detected cancelled statuses.', 'elev8-os')
                : __('No persons field was detected, so each booking is counted as one student.', 'elev8-os')
        );
    }

    /**
     * Count booking records by detected status.
     *
     * Passing null returns all bookings that have a non-empty detected status.
     *
     * @param array<string,mixed> $schema
     * @param string[]|null $statuses
     * @return array<string,mixed>
     */
    private static function booking_status_count(array &$schema, ?array $statuses): array {
        global $wpdb;

        $bookings = self::table($schema, 'amelia_customer_bookings');
        if (!$bookings) {
            return self::unavailable_metric(__('Customer bookings table was not detected.', 'elev8-os'));
        }

        $status_column = self::unique_column($bookings, ['status']);
        if (!$status_column) {
            return self::unavailable_metric(__('No reliable booking status column was detected.', 'elev8-os'));
        }

        if ($statuses === null) {
            $sql = "SELECT COUNT(*) FROM `{$bookings['name']}`
                    WHERE `{$status_column}` IS NOT NULL
                      AND TRIM(`{$status_column}`) <> ''";
            $value = $wpdb->get_var($sql);
        } else {
            $normalized = array_values(array_unique(array_map('strtolower', $statuses)));
            $placeholders = implode(', ', array_fill(0, count($normalized), '%s'));
            $sql = $wpdb->prepare(
                "SELECT COUNT(*) FROM `{$bookings['name']}`
                 WHERE LOWER(`{$status_column}`) IN ({$placeholders})",
                ...$normalized
            );
            $value = $wpdb->get_var($sql);
        }

        return self::available_metric(
            is_numeric($value) ? (int) $value : 0,
            'high',
            __('Calculated from the detected customer booking status column.', 'elev8-os')
        );
    }

    /**
     * Calculate booked value for bookings linked to appointments in a range.
     *
     * This is booked value, not recognized revenue.
     *
     * @param array<string,mixed> $schema
     * @return array<string,mixed>
     */
    private static function booked_value(array &$schema, string $start, ?string $end): array {
        global $wpdb;

        $bookings = self::table($schema, 'amelia_customer_bookings');
        $appointments = self::table($schema, 'amelia_appointments');

        if (!$bookings || !$appointments) {
            return self::unavailable_metric(
                __('Booked value requires verified customer bookings and appointments tables.', 'elev8-os')
            );
        }

        $amount = self::unique_numeric_column($bookings, ['price', 'amount', 'total', 'totalPrice']);
        $booking_appointment = self::unique_column($bookings, ['appointmentId', 'appointment_id']);
        $appointment_id = self::unique_column($appointments, ['id']);
        $appointment_start = self::unique_column(
            $appointments,
            ['bookingStart', 'start', 'startDate', 'startDateTime', 'dateTime']
        );

        if (!$amount || !$booking_appointment || !$appointment_id || !$appointment_start) {
            return self::unavailable_metric(
                __('A reliable booking amount, appointment relationship, or appointment date column was not detected.', 'elev8-os')
            );
        }

        $status = self::unique_column($bookings, ['status']);
        $status_sql = $status
            ? " AND LOWER(COALESCE(b.`{$status}`, '')) NOT IN ('canceled', 'cancelled', 'rejected')"
            : '';

        $where_sql = "a.`{$appointment_start}` >= %s";
        $parameters = [$start];

        if ($end !== null) {
            $where_sql .= " AND a.`{$appointment_start}` < %s";
            $parameters[] = $end;
        }

        $sql = $wpdb->prepare(
            "SELECT SUM(COALESCE(b.`{$amount}`, 0))
             FROM `{$bookings['name']}` b
             INNER JOIN `{$appointments['name']}` a
                ON b.`{$booking_appointment}` = a.`{$appointment_id}`
             WHERE {$where_sql}
               {$status_sql}",
            ...$parameters
        );

        $value = $wpdb->get_var($sql);

        return [
            'available' => true,
            'value' => is_numeric($value) ? (float) $value : 0.0,
            'format' => 'currency',
            'confidence' => 'medium',
            'diagnostic' => __('Booked value is based on detected booking amounts and excludes detected cancelled statuses. It is not recognized revenue and does not account for payment settlement, refunds, fees, or payout rules.', 'elev8-os'),
        ];
    }

    /**
     * Count non-cancelled booking records linked to appointments in a range.
     *
     * @param array<string,mixed> $schema
     * @return array<string,mixed>
     */
    private static function booking_count_in_range(array &$schema, string $start, ?string $end): array {
        global $wpdb;

        $bookings = self::table($schema, 'amelia_customer_bookings');
        $appointments = self::table($schema, 'amelia_appointments');

        if (!$bookings || !$appointments) {
            return self::unavailable_metric(
                __('Average ticket value requires verified customer bookings and appointments tables.', 'elev8-os')
            );
        }

        $booking_appointment = self::unique_column($bookings, ['appointmentId', 'appointment_id']);
        $appointment_id = self::unique_column($appointments, ['id']);
        $appointment_start = self::unique_column(
            $appointments,
            ['bookingStart', 'start', 'startDate', 'startDateTime', 'dateTime']
        );

        if (!$booking_appointment || !$appointment_id || !$appointment_start) {
            return self::unavailable_metric(
                __('The bookings-to-appointments relationship or appointment date could not be verified.', 'elev8-os')
            );
        }

        $status = self::unique_column($bookings, ['status']);
        $status_sql = $status
            ? " AND LOWER(COALESCE(b.`{$status}`, '')) NOT IN ('canceled', 'cancelled', 'rejected')"
            : '';

        $where_sql = "a.`{$appointment_start}` >= %s";
        $parameters = [$start];

        if ($end !== null) {
            $where_sql .= " AND a.`{$appointment_start}` < %s";
            $parameters[] = $end;
        }

        $sql = $wpdb->prepare(
            "SELECT COUNT(*)
             FROM `{$bookings['name']}` b
             INNER JOIN `{$appointments['name']}` a
                ON b.`{$booking_appointment}` = a.`{$appointment_id}`
             WHERE {$where_sql}
               {$status_sql}",
            ...$parameters
        );

        $value = $wpdb->get_var($sql);

        return self::available_metric(
            is_numeric($value) ? (int) $value : 0,
            $status ? 'high' : 'medium',
            $status
                ? __('Calculated from booking records in the selected period with detected cancelled statuses excluded.', 'elev8-os')
                : __('No booking status column was detected, so all booking records in the selected period are counted.', 'elev8-os')
        );
    }

    /**
     * Return upcoming dates from verified schedule sources.
     *
     * @param array<string,mixed> $schema
     * @return array<string,mixed>
     */
    private static function upcoming_class_dates(array &$schema, int $limit): array {
        global $wpdb;

        $items = [];
        $now = current_time('mysql');

        foreach ([
            ['table' => 'amelia_events_periods', 'dates' => ['periodStart', 'start', 'startDate', 'startDateTime', 'dateTime'], 'source' => 'event'],
            ['table' => 'amelia_appointments', 'dates' => ['bookingStart', 'start', 'startDate', 'startDateTime', 'dateTime'], 'source' => 'appointment'],
        ] as $definition) {
            $table = self::table($schema, $definition['table']);
            if (!$table) {
                continue;
            }

            $date_column = self::unique_column($table, $definition['dates']);
            if (!$date_column) {
                continue;
            }

            $sql = $wpdb->prepare(
                "SELECT `{$date_column}` AS class_date
                 FROM `{$table['name']}`
                 WHERE `{$date_column}` >= %s
                 ORDER BY `{$date_column}` ASC
                 LIMIT %d",
                $now,
                $limit
            );

            $rows = $wpdb->get_col($sql);
            if (!is_array($rows)) {
                continue;
            }

            foreach ($rows as $date) {
                $date = (string) $date;
                if ($date === '') {
                    continue;
                }

                $items[] = [
                    'date' => $date,
                    'source' => $definition['source'],
                ];
            }
        }

        usort(
            $items,
            static function (array $a, array $b): int {
                return strcmp((string) $a['date'], (string) $b['date']);
            }
        );

        $items = array_slice($items, 0, $limit);

        if ($items === []) {
            return [
                'available' => false,
                'items' => [],
                'confidence' => 'unavailable',
                'diagnostic' => __('No verified upcoming schedule dates could be read.', 'elev8-os'),
            ];
        }

        return [
            'available' => true,
            'items' => $items,
            'confidence' => 'high',
            'diagnostic' => __('Dates come from detected Amelia event-period and/or appointment start columns.', 'elev8-os'),
        ];
    }

    /**
     * @param array<string,mixed> $numerator
     * @param array<string,mixed> $denominator
     * @return array<string,mixed>
     */
    private static function rate_metric(array $numerator, array $denominator, string $diagnostic): array {
        if (empty($numerator['available']) || empty($denominator['available'])) {
            return self::unavailable_metric(
                __('The cancellation rate cannot be calculated because one or more required booking metrics are unavailable.', 'elev8-os')
            );
        }

        $total = (float) $denominator['value'];
        $cancelled = (float) $numerator['value'];

        return [
            'available' => true,
            'value' => $total > 0 ? ($cancelled / $total) * 100 : 0.0,
            'format' => 'percent',
            'confidence' => 'high',
            'diagnostic' => $diagnostic,
        ];
    }

    /**
     * @param array<string,mixed> $numerator
     * @param array<string,mixed> $denominator
     * @return array<string,mixed>
     */
    private static function average_metric(array $numerator, array $denominator, string $diagnostic): array {
        if (empty($numerator['available']) || empty($denominator['available'])) {
            return self::unavailable_metric(
                __('Average class size cannot be calculated because one or more required metrics are unavailable.', 'elev8-os')
            );
        }

        $classes = (float) $denominator['value'];
        $students = (float) $numerator['value'];

        return [
            'available' => true,
            'value' => $classes > 0 ? $students / $classes : 0.0,
            'format' => 'decimal',
            'confidence' => 'medium',
            'diagnostic' => $diagnostic,
        ];
    }

    /**
     * Compare the current booked value with the previous month.
     *
     * @param array<string,mixed> $current
     * @param array<string,mixed> $previous
     * @return array<string,mixed>
     */
    private static function financial_change_metric(array $current, array $previous): array {
        if (empty($current['available']) || empty($previous['available'])) {
            return self::unavailable_metric(
                __('Booked value change cannot be calculated because one or both monthly booked-value metrics are unavailable.', 'elev8-os')
            );
        }

        $current_value = (float) ($current['value'] ?? 0);
        $previous_value = (float) ($previous['value'] ?? 0);

        if ($previous_value <= 0.0) {
            return [
                'available' => false,
                'value' => null,
                'format' => 'unavailable',
                'confidence' => 'unavailable',
                'diagnostic' => $current_value > 0.0
                    ? __('This month has booked value, but the previous month has no comparable booked value. A percentage change would be misleading.', 'elev8-os')
                    : __('Neither this month nor the previous month has comparable booked value.', 'elev8-os'),
            ];
        }

        $change = (($current_value - $previous_value) / $previous_value) * 100;

        return [
            'available' => true,
            'value' => $change,
            'format' => 'signed_percent',
            'confidence' => 'medium',
            'diagnostic' => sprintf(
                __('This month: %1$s. Previous month: %2$s. Change is based on booked value, not recognized revenue.', 'elev8-os'),
                self::plain_currency($current_value),
                self::plain_currency($previous_value)
            ),
            'comparison' => [
                'current' => $current_value,
                'previous' => $previous_value,
            ],
        ];
    }

    private static function plain_currency(float $value): string {
        $symbol = apply_filters('elev8_os_currency_symbol', '$');

        return (string) $symbol . number_format_i18n($value, 2);
    }

    /**
     * Divide a financial metric by a verified count metric.
     *
     * @param array<string,mixed> $financial
     * @param array<string,mixed> $count
     * @return array<string,mixed>
     */
    private static function financial_ratio_metric(array $financial, array $count, string $diagnostic): array {
        if (empty($financial['available']) || empty($count['available'])) {
            return self::unavailable_metric(
                __('This financial average cannot be calculated because one or more required metrics are unavailable.', 'elev8-os')
            );
        }

        $denominator = (float) ($count['value'] ?? 0);
        $value = (float) ($financial['value'] ?? 0);

        return [
            'available' => true,
            'value' => $denominator > 0 ? $value / $denominator : 0.0,
            'format' => 'currency',
            'confidence' => 'medium',
            'diagnostic' => $diagnostic,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function available_metric($value, string $confidence, string $diagnostic): array {
        return [
            'available' => true,
            'value' => $value,
            'format' => 'number',
            'confidence' => $confidence,
            'diagnostic' => $diagnostic,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function unavailable_metric(string $diagnostic): array {
        return [
            'available' => false,
            'value' => null,
            'format' => 'unavailable',
            'confidence' => 'unavailable',
            'diagnostic' => $diagnostic,
        ];
    }

    /**
     * @param array<string,mixed> $schema
     * @return array<string,mixed>|null
     */
    private static function table(array $schema, string $short_name): ?array {
        $table = $schema['tables'][$short_name] ?? null;
        return is_array($table) ? $table : null;
    }

    /**
     * Find exactly one matching column from runtime metadata.
     *
     * @param array<string,mixed> $table
     * @param string[] $candidates
     */
    private static function unique_column(array $table, array $candidates): ?string {
        $columns = isset($table['columns']) && is_array($table['columns']) ? $table['columns'] : [];
        $matches = [];

        foreach ($candidates as $candidate) {
            $key = strtolower($candidate);
            if (isset($columns[$key])) {
                $matches[] = (string) $columns[$key]['COLUMN_NAME'];
            }
        }

        $matches = array_values(array_unique($matches));
        return count($matches) === 1 ? $matches[0] : null;
    }

    /**
     * @param array<string,mixed> $table
     * @param string[] $candidates
     */
    private static function unique_numeric_column(array $table, array $candidates): ?string {
        $column = self::unique_column($table, $candidates);
        if (!$column) {
            return null;
        }

        $metadata = $table['columns'][strtolower($column)] ?? [];
        $type = strtolower((string) ($metadata['DATA_TYPE'] ?? ''));

        return in_array($type, ['tinyint', 'smallint', 'mediumint', 'int', 'bigint', 'decimal', 'numeric', 'float', 'double', 'real'], true)
            ? $column
            : null;
    }

    private static function count_rows_in_range(string $table, string $column, string $start, string $end): int {
        global $wpdb;

        if (!self::is_safe_identifier($table) || !self::is_safe_identifier($column)) {
            return 0;
        }

        $sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM `{$table}`
             WHERE `{$column}` >= %s
               AND `{$column}` < %s",
            $start,
            $end
        );

        $value = $wpdb->get_var($sql);
        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function column_metadata(string $table_name): array {
        global $wpdb;

        if (!self::is_safe_identifier($table_name)) {
            return [];
        }

        $query = $wpdb->prepare(
            'SELECT COLUMN_NAME, COLUMN_TYPE, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_KEY, EXTRA, ORDINAL_POSITION
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = %s
               AND TABLE_NAME = %s
             ORDER BY ORDINAL_POSITION ASC',
            (string) $wpdb->dbname,
            $table_name
        );

        $rows = $wpdb->get_results($query, ARRAY_A);
        return is_array($rows) ? $rows : [];
    }

    private static function short_table_name(string $table_name): string {
        global $wpdb;

        if (strpos($table_name, (string) $wpdb->prefix) === 0) {
            return substr($table_name, strlen((string) $wpdb->prefix));
        }

        return $table_name;
    }

    private static function is_safe_identifier(string $identifier): bool {
        return (bool) preg_match('/^[A-Za-z0-9_]+$/', $identifier);
    }
}

<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Read-only Amelia integration service.
 *
 * Amelia remains an external integration. This class discovers the available
 * schema at runtime and returns normalized data to Elev8 OS modules.
 */
final class Elev8_OS_Amelia {

    /** @var array<string,array<int,string>> */
    private static array $column_cache = [];

    public static function is_available(): bool {
        return self::table_exists(self::table('users'))
            && self::table_exists(self::table('appointments'));
    }

    /**
     * Match a WordPress account to an Amelia provider using email.
     *
     * @return array<string,mixed>|null
     */
    public static function find_provider_for_user(WP_User $user): ?array {
        global $wpdb;

        $email = sanitize_email((string) $user->user_email);
        $table = self::table('users');

        if ($email === '' || !self::table_exists($table)) {
            return null;
        }

        $columns = self::columns($table);
        $email_column = self::first_column($columns, ['email']);
        $id_column = self::first_column($columns, ['id']);

        if (!$email_column || !$id_column) {
            return null;
        }

        $select = [$id_column];
        foreach (['firstName', 'lastName', 'email', 'type'] as $column) {
            if (in_array($column, $columns, true)) {
                $select[] = $column;
            }
        }

        $type_sql = '';
        if (in_array('type', $columns, true)) {
            $type_sql = " AND LOWER(COALESCE(`type`, '')) IN ('provider', 'employee')";
        }

        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT ' . self::identifier_list($select) . "
                 FROM `{$table}`
                 WHERE LOWER(`{$email_column}`) = LOWER(%s){$type_sql}
                 LIMIT 1",
                $email
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /**
     * Return normalized live statistics for one Amelia provider.
     *
     * @return array<string,mixed>
     */
    public static function provider_dashboard(int $provider_id, int $limit = 10): array {
        $empty = [
            'available' => false,
            'upcoming_classes' => 0,
            'booked_students' => 0,
            'pending_bookings' => 0,
            'cancelled_bookings' => 0,
            'average_class_size' => 0.0,
            'appointments' => [],
            'diagnostics' => [],
        ];

        if ($provider_id <= 0 || !self::is_available()) {
            return $empty;
        }

        $schema = self::schema();
        if (!$schema['ready']) {
            $empty['diagnostics'] = $schema['diagnostics'];
            return $empty;
        }

        $appointments = self::upcoming_appointments($provider_id, $schema, $limit);
        $upcoming_count = self::upcoming_appointment_count($provider_id, $schema);
        $stats = self::booking_statistics($provider_id, $schema);

        return [
            'available' => true,
            'upcoming_classes' => $upcoming_count,
            'booked_students' => $stats['booked_students'],
            'pending_bookings' => $stats['pending_bookings'],
            'cancelled_bookings' => $stats['cancelled_bookings'],
            'average_class_size' => $upcoming_count > 0
                ? round($stats['booked_students'] / $upcoming_count, 1)
                : 0.0,
            'appointments' => $appointments,
            'diagnostics' => array_values(array_unique(array_merge(
                $schema['diagnostics'],
                $stats['diagnostics']
            ))),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function schema(): array {
        $appointments = self::table('appointments');
        $bookings = self::table('customer_bookings');
        $services = self::table('services');

        $appointment_columns = self::columns($appointments);
        $booking_columns = self::columns($bookings);
        $service_columns = self::columns($services);
        $diagnostics = [];

        $schema = [
            'ready' => false,
            'appointments_table' => $appointments,
            'bookings_table' => $bookings,
            'services_table' => self::table_exists($services) ? $services : '',
            'appointment_id' => self::first_column($appointment_columns, ['id']),
            'appointment_provider' => self::first_column($appointment_columns, ['providerId', 'provider_id', 'employeeId', 'employee_id']),
            'appointment_start' => self::first_column($appointment_columns, ['bookingStart', 'booking_start', 'start', 'startDateTime']),
            'appointment_end' => self::first_column($appointment_columns, ['bookingEnd', 'booking_end', 'end', 'endDateTime']),
            'appointment_service' => self::first_column($appointment_columns, ['serviceId', 'service_id']),
            'appointment_status' => self::first_column($appointment_columns, ['status']),
            'appointment_capacity' => self::first_column($appointment_columns, ['maxCapacity', 'max_capacity', 'capacity']),
            'booking_id' => self::first_column($booking_columns, ['id']),
            'booking_appointment' => self::first_column($booking_columns, ['appointmentId', 'appointment_id']),
            'booking_status' => self::first_column($booking_columns, ['status']),
            'booking_persons' => self::first_column($booking_columns, ['persons', 'personsCount', 'persons_count', 'quantity']),
            'service_id' => self::first_column($service_columns, ['id']),
            'service_name' => self::first_column($service_columns, ['name', 'title']),
            'diagnostics' => &$diagnostics,
        ];

        if (!self::table_exists($appointments)) {
            $diagnostics[] = 'Amelia appointments table was not found.';
        }
        if (!self::table_exists($bookings)) {
            $diagnostics[] = 'Amelia customer bookings table was not found.';
        }

        foreach ([
            'appointment_id',
            'appointment_provider',
            'appointment_start',
            'booking_appointment',
        ] as $required) {
            if (empty($schema[$required])) {
                $diagnostics[] = sprintf('Required Amelia field could not be identified: %s.', $required);
            }
        }

        $schema['ready'] = empty($diagnostics);
        return $schema;
    }


    /**
     * @param array<string,mixed> $schema
     */
    private static function upcoming_appointment_count(int $provider_id, array $schema): int {
        global $wpdb;

        $appointments = $schema['appointments_table'];
        $provider = $schema['appointment_provider'];
        $start = $schema['appointment_start'];
        $status = $schema['appointment_status'];
        $status_sql = $status
            ? " AND LOWER(COALESCE(`{$status}`, '')) NOT IN ('canceled', 'cancelled', 'rejected')"
            : '';

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*)
                 FROM `{$appointments}`
                 WHERE `{$provider}` = %d
                   AND `{$start}` >= %s
                   {$status_sql}",
                $provider_id,
                current_time('mysql')
            )
        );

        return max(0, (int) $count);
    }

    /**
     * @param array<string,mixed> $schema
     * @return array<int,array<string,mixed>>
     */
    private static function upcoming_appointments(int $provider_id, array $schema, int $limit): array {
        global $wpdb;

        $appointments = $schema['appointments_table'];
        $bookings = $schema['bookings_table'];
        $services = $schema['services_table'];

        $appointment_id = $schema['appointment_id'];
        $provider = $schema['appointment_provider'];
        $start = $schema['appointment_start'];
        $end = $schema['appointment_end'];
        $service_id = $schema['appointment_service'];
        $appointment_status = $schema['appointment_status'];
        $capacity = $schema['appointment_capacity'];
        $booking_appointment = $schema['booking_appointment'];
        $booking_status = $schema['booking_status'];
        $persons = $schema['booking_persons'];

        $student_expression = $persons
            ? "SUM(CASE WHEN " . self::active_booking_condition('b', $booking_status) . " THEN GREATEST(COALESCE(b.`{$persons}`, 1), 1) ELSE 0 END)"
            : "SUM(CASE WHEN " . self::active_booking_condition('b', $booking_status) . " THEN 1 ELSE 0 END)";

        $select = [
            "a.`{$appointment_id}` AS appointment_id",
            "a.`{$start}` AS starts_at",
            $end ? "a.`{$end}` AS ends_at" : "NULL AS ends_at",
            $appointment_status ? "a.`{$appointment_status}` AS appointment_status" : "'' AS appointment_status",
            $capacity ? "a.`{$capacity}` AS capacity" : 'NULL AS capacity',
            "COALESCE({$student_expression}, 0) AS booked_students",
        ];

        $joins = [
            "LEFT JOIN `{$bookings}` b ON b.`{$booking_appointment}` = a.`{$appointment_id}`",
        ];

        if ($services && $service_id && $schema['service_id'] && $schema['service_name']) {
            $select[] = "s.`{$schema['service_name']}` AS service_name";
            $joins[] = "LEFT JOIN `{$services}` s ON s.`{$schema['service_id']}` = a.`{$service_id}`";
        } else {
            $select[] = "'' AS service_name";
        }

        $appointment_filter = '';
        if ($appointment_status) {
            $appointment_filter = " AND LOWER(COALESCE(a.`{$appointment_status}`, '')) NOT IN ('canceled', 'cancelled', 'rejected')";
        }

        $limit = max(1, min(50, $limit));
        $sql = $wpdb->prepare(
            'SELECT ' . implode(', ', $select) . "
             FROM `{$appointments}` a
             " . implode("\n", $joins) . "
             WHERE a.`{$provider}` = %d
               AND a.`{$start}` >= %s
               {$appointment_filter}
             GROUP BY a.`{$appointment_id}`
             ORDER BY a.`{$start}` ASC
             LIMIT {$limit}",
            $provider_id,
            current_time('mysql')
        );

        $rows = $wpdb->get_results($sql, ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }

        return array_map(
            static function (array $row): array {
                return [
                    'appointment_id' => (int) ($row['appointment_id'] ?? 0),
                    'service_name' => sanitize_text_field((string) ($row['service_name'] ?? '')),
                    'starts_at' => (string) ($row['starts_at'] ?? ''),
                    'ends_at' => (string) ($row['ends_at'] ?? ''),
                    'status' => sanitize_text_field((string) ($row['appointment_status'] ?? '')),
                    'capacity' => isset($row['capacity']) ? max(0, (int) $row['capacity']) : 0,
                    'booked_students' => max(0, (int) ($row['booked_students'] ?? 0)),
                ];
            },
            $rows
        );
    }

    /**
     * @param array<string,mixed> $schema
     * @return array<string,mixed>
     */
    private static function booking_statistics(int $provider_id, array $schema): array {
        global $wpdb;

        $appointments = $schema['appointments_table'];
        $bookings = $schema['bookings_table'];
        $appointment_id = $schema['appointment_id'];
        $provider = $schema['appointment_provider'];
        $start = $schema['appointment_start'];
        $appointment_status = $schema['appointment_status'];
        $booking_appointment = $schema['booking_appointment'];
        $booking_status = $schema['booking_status'];
        $persons = $schema['booking_persons'];
        $diagnostics = [];

        $persons_value = $persons ? "GREATEST(COALESCE(b.`{$persons}`, 1), 1)" : '1';
        $status_value = $booking_status ? "LOWER(COALESCE(b.`{$booking_status}`, ''))" : "''";

        $appointment_filter = '';
        if ($appointment_status) {
            $appointment_filter = " AND LOWER(COALESCE(a.`{$appointment_status}`, '')) NOT IN ('canceled', 'cancelled', 'rejected')";
        }

        $sql = $wpdb->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN {$status_value} NOT IN ('canceled', 'cancelled', 'rejected') THEN {$persons_value} ELSE 0 END), 0) AS booked_students,
                COALESCE(SUM(CASE WHEN {$status_value} IN ('pending', 'waiting') THEN {$persons_value} ELSE 0 END), 0) AS pending_bookings,
                COALESCE(SUM(CASE WHEN {$status_value} IN ('canceled', 'cancelled', 'rejected') THEN {$persons_value} ELSE 0 END), 0) AS cancelled_bookings
             FROM `{$appointments}` a
             INNER JOIN `{$bookings}` b ON b.`{$booking_appointment}` = a.`{$appointment_id}`
             WHERE a.`{$provider}` = %d
               AND a.`{$start}` >= %s
               {$appointment_filter}",
            $provider_id,
            current_time('mysql')
        );

        $row = $wpdb->get_row($sql, ARRAY_A);
        if (!is_array($row)) {
            $diagnostics[] = 'Unable to read Amelia booking statistics.';
            $row = [];
        }

        return [
            'booked_students' => max(0, (int) ($row['booked_students'] ?? 0)),
            'pending_bookings' => max(0, (int) ($row['pending_bookings'] ?? 0)),
            'cancelled_bookings' => max(0, (int) ($row['cancelled_bookings'] ?? 0)),
            'diagnostics' => $diagnostics,
        ];
    }

    private static function active_booking_condition(string $alias, ?string $status_column): string {
        if (!$status_column) {
            return '1=1';
        }

        return "LOWER(COALESCE({$alias}.`{$status_column}`, '')) NOT IN ('canceled', 'cancelled', 'rejected')";
    }

    private static function table(string $suffix): string {
        global $wpdb;
        return $wpdb->prefix . 'amelia_' . $suffix;
    }

    private static function table_exists(string $table): bool {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    /** @return array<int,string> */
    private static function columns(string $table): array {
        global $wpdb;

        if ($table === '' || !self::table_exists($table)) {
            return [];
        }

        if (isset(self::$column_cache[$table])) {
            return self::$column_cache[$table];
        }

        $columns = $wpdb->get_col("DESCRIBE `{$table}`", 0);
        self::$column_cache[$table] = is_array($columns)
            ? array_values(array_map('strval', $columns))
            : [];

        return self::$column_cache[$table];
    }

    /**
     * @param array<int,string> $available
     * @param array<int,string> $candidates
     */
    private static function first_column(array $available, array $candidates): ?string {
        foreach ($candidates as $candidate) {
            if (in_array($candidate, $available, true)) {
                return $candidate;
            }
        }
        return null;
    }

    /** @param array<int,string> $identifiers */
    private static function identifier_list(array $identifiers): string {
        return implode(', ', array_map(static fn(string $value): string => "`{$value}`", $identifiers));
    }
}

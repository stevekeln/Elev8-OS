<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Normalizes upcoming Amelia class occurrences for an artist.
 *
 * Real appointment records are preferred. When Amelia has not generated an
 * appointment yet, verified future dates are read from that artist's assigned
 * service record. No date, time, capacity, or appointment ID is invented.
 */
final class Elev8_OS_Class_Discovery {

    /** @return array<int,array<string,mixed>> */
    public static function upcoming_for_employee(int $employee_id): array {
        if ($employee_id <= 0) { return []; }

        $appointments = self::appointment_occurrences($employee_id);
        $service_occurrences = self::service_occurrences($employee_id);

        $results = [];
        $seen = [];
        foreach (array_merge($appointments, $service_occurrences) as $occurrence) {
            $dedupe = (int) $occurrence['service_id'] . '|' . (string) $occurrence['class_date'] . '|' . (string) $occurrence['class_time'];
            if (isset($seen[$dedupe])) { continue; }
            $seen[$dedupe] = true;
            $results[] = $occurrence;
        }

        usort($results, static function (array $a, array $b): int {
            return strcmp((string) $a['sort_start'], (string) $b['sort_start']);
        });
        return $results;
    }

    /** @return array<int,array<string,mixed>> */
    private static function appointment_occurrences(int $employee_id): array {
        global $wpdb;
        $table = $wpdb->prefix . 'amelia_appointments';
        if (!self::table_exists($table)) { return []; }
        $columns = self::table_columns($table);
        $id_col = self::first_column($columns, ['id']);
        $provider_col = self::first_column($columns, ['providerId', 'provider_id', 'employeeId']);
        $service_col = self::first_column($columns, ['serviceId', 'service_id']);
        $start_col = self::first_column($columns, ['bookingStart', 'booking_start', 'start']);
        $capacity_col = self::first_column($columns, ['maxCapacity', 'max_capacity', 'capacity']);
        $status_col = self::first_column($columns, ['status']);
        if (!$id_col || !$provider_col || !$start_col) { return []; }

        $select = ["`{$id_col}` AS appointment_id", "`{$start_col}` AS booking_start"];
        $select[] = $service_col ? "`{$service_col}` AS service_id" : '0 AS service_id';
        $select[] = $capacity_col ? "`{$capacity_col}` AS capacity" : 'NULL AS capacity';
        $status_sql = $status_col ? " AND LOWER(COALESCE(`{$status_col}`,'')) NOT IN ('canceled','cancelled','rejected')" : '';
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT ' . implode(',', $select) . " FROM `{$table}` WHERE `{$provider_col}`=%d AND `{$start_col}` >= %s{$status_sql} ORDER BY `{$start_col}` ASC LIMIT 300",
            $employee_id,
            current_time('mysql')
        ), ARRAY_A) ?: [];

        $services = self::service_details();
        $booked = self::booked_seats();
        $results = [];
        foreach ($rows as $row) {
            $appointment_id = (int) ($row['appointment_id'] ?? 0);
            $service_id = (int) ($row['service_id'] ?? 0);
            $local = self::local_datetime((string) ($row['booking_start'] ?? ''));
            if (!$local) { continue; }
            $capacity = self::positive_int($row['capacity'] ?? null);
            if ($capacity === null) { $capacity = $services[$service_id]['capacity'] ?? null; }
            $booked_seats = (int) ($booked[$appointment_id] ?? 0);
            $results[] = self::normalized(
                'appointment:' . $appointment_id,
                $appointment_id,
                $employee_id,
                $service_id,
                $services[$service_id]['name'] ?? __('Class', 'elev8-os'),
                $local,
                false,
                $capacity,
                $booked_seats,
                'appointment'
            );
        }
        return $results;
    }

    /** @return array<int,array<string,mixed>> */
    private static function service_occurrences(int $employee_id): array {
        global $wpdb;
        $services_table = $wpdb->prefix . 'amelia_services';
        if (!self::table_exists($services_table)) { return []; }
        $service_columns = self::table_columns($services_table);
        $name_col = self::first_column($service_columns, ['name', 'title']);
        $description_col = self::first_column($service_columns, ['description', 'details', 'content']);
        $capacity_col = self::first_column($service_columns, ['maxCapacity', 'max_capacity', 'capacity']);
        if (!in_array('id', $service_columns, true) || !$name_col || !$description_col) { return []; }

        $assigned = [];
        foreach (self::provider_service_tables() as $relation_table) {
            $columns = self::table_columns($relation_table);
            $provider_col = self::first_column($columns, ['userId', 'providerId', 'employeeId', 'provider_id', 'user_id', 'provider', 'employee']);
            $service_col = self::first_column($columns, ['serviceId', 'service_id', 'service']);
            if (!$provider_col || !$service_col) { continue; }
            $ids = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT `{$service_col}` FROM `{$relation_table}` WHERE `{$provider_col}`=%d",
                $employee_id
            )) ?: [];
            foreach ($ids as $id) { if ((int) $id > 0) { $assigned[(int) $id] = true; } }
        }

        // Compatibility for Amelia versions storing assigned employees in a service field.
        if (!$assigned) {
            $assignment_columns = array_values(array_filter($service_columns, static function ($column): bool {
                return preg_match('/(provider|employee|staff|user|assignee|teacher|artist)/i', (string) $column) === 1;
            }));
            if ($assignment_columns) {
                foreach ($wpdb->get_results("SELECT * FROM `{$services_table}` ORDER BY `id` ASC LIMIT 500", ARRAY_A) ?: [] as $row) {
                    foreach ($assignment_columns as $column) {
                        if (self::contains_employee($row[$column] ?? null, $employee_id)) {
                            $assigned[(int) $row['id']] = true;
                            break;
                        }
                    }
                }
            }
        }
        if (!$assigned) { return []; }

        $select = ['`id`', "`{$name_col}` AS service_name", "`{$description_col}` AS service_description"];
        $select[] = $capacity_col ? "`{$capacity_col}` AS capacity" : 'NULL AS capacity';
        $placeholders = implode(',', array_fill(0, count($assigned), '%d'));
        $query = $wpdb->prepare(
            'SELECT ' . implode(',', $select) . " FROM `{$services_table}` WHERE `id` IN ({$placeholders})",
            ...array_keys($assigned)
        );
        $rows = $wpdb->get_results($query, ARRAY_A) ?: [];

        $results = [];
        foreach ($rows as $row) {
            $service_id = (int) $row['id'];
            $name = (string) $row['service_name'];
            $capacity = self::positive_int($row['capacity'] ?? null);
            foreach (self::explicit_occurrences((string) $row['service_description'], $name) as $occurrence) {
                $date = $occurrence['date'];
                $results[] = self::normalized(
                    'service:' . $service_id . ':' . $date->format('YmdHis'),
                    0,
                    $employee_id,
                    $service_id,
                    $name,
                    $date,
                    (bool) $occurrence['date_only'],
                    $capacity,
                    0,
                    'service'
                );
            }
        }
        return $results;
    }

    /** @return array<string,mixed> */
    private static function normalized(string $key, int $appointment_id, int $employee_id, int $service_id, string $name, DateTimeImmutable $date, bool $date_only, ?int $capacity, int $booked, string $source): array {
        return [
            'occurrence_key' => $key,
            'appointment_id' => $appointment_id,
            'employee_id' => $employee_id,
            'service_id' => $service_id,
            'name' => $name,
            'class_date' => $date->format('Y-m-d'),
            'class_time' => $date_only ? '' : $date->format('H:i:s'),
            'display' => $date_only
                ? wp_date(get_option('date_format'), $date->getTimestamp(), wp_timezone())
                : wp_date(get_option('date_format') . ' ' . get_option('time_format'), $date->getTimestamp(), wp_timezone()),
            'sort_start' => $date->format('Y-m-d H:i:s'),
            'date_only' => $date_only,
            'capacity' => $capacity,
            'booked' => $booked,
            'seats_left' => $capacity === null ? null : max(0, $capacity - $booked),
            'source' => $source,
        ];
    }

    private static function local_datetime(string $value): ?DateTimeImmutable {
        $value = trim($value);
        if ($value === '') { return null; }
        try { return new DateTimeImmutable($value, wp_timezone()); } catch (Exception $e) { return null; }
    }

    /** @return array<int,array{date:DateTimeImmutable,date_only:bool}> */
    private static function explicit_occurrences(string $description, string $service_name): array {
        $text = preg_replace('/<\s*(?:br|\/p|\/div|\/li|\/h[1-6])\s*\/?>/i', "\n", $description);
        $text = html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(["\xC2\xA0", "\r", "\t"], [' ', "\n", ' '], $text);
        $text = wp_strip_all_tags($text);
        $text = preg_replace('/[ ]+/u', ' ', (string) $text);
        $text = preg_replace('/\n[ ]*/u', "\n", (string) $text);
        $text = trim((string) $text);
        if ($text === '') { return []; }

        $tz = wp_timezone();
        $now = new DateTimeImmutable('now', $tz);
        $today = $now->setTime(0, 0, 0);
        $parse = static function (string $month, string $day, string $year, string $time = '') use ($tz): ?DateTimeImmutable {
            $candidate = trim($month . ' ' . $day . ' ' . $year . ($time !== '' ? ' ' . trim((string) preg_replace('/\s+/u', ' ', $time)) : ''));
            foreach ($time !== '' ? ['F j Y g:i A', 'M j Y g:i A'] : ['F j Y', 'M j Y'] as $format) {
                $date = DateTimeImmutable::createFromFormat('!' . $format, $candidate, $tz);
                $errors = DateTimeImmutable::getLastErrors();
                if ($date && ($errors === false || ((int) $errors['warning_count'] === 0 && (int) $errors['error_count'] === 0))) { return $date; }
            }
            return null;
        };
        $items = [];
        $add = static function (?DateTimeImmutable $date, bool $date_only) use (&$items, $now, $today): void {
            if (!$date || ($date_only ? $date < $today : $date < $now)) { return; }
            $items[$date->format('Y-m-d H:i:s') . '|' . (int) $date_only] = ['date' => $date, 'date_only' => $date_only];
        };

        $context_year = '';
        $default_time = '';
        $patterns = [
            '/\b(?:Mon|Tue|Wed|Thu|Fri|Sat|Sun)(?:day)?[,]?\s*([A-Z][a-z]{2,8})\s+(\d{1,2})[,]?\s+(20\d{2})[,]?\s+(\d{1,2}:\d{2}\s*[AP]M)\b/i',
            '/\b([A-Z][a-z]{2,8})\s+(\d{1,2})[,]?\s+(20\d{2})[,]?\s+(\d{1,2}:\d{2}\s*[AP]M)\b/i',
        ];
        $matches = [];
        foreach ($patterns as $pattern) { preg_match_all($pattern, $text, $matches, PREG_SET_ORDER); if ($matches) { break; } }
        foreach ($matches as $match) {
            $context_year = (string) $match[3];
            $default_time = (string) $match[4];
            $add($parse((string) $match[1], (string) $match[2], $context_year, $default_time), false);
        }
        if ($context_year === '' && preg_match('/\b(20\d{2})\b/', $text, $year_match)) { $context_year = (string) $year_match[1]; }
        if ($default_time === '' && preg_match('/\b(\d{1,2}:\d{2}\s*[AP]M)\b/i', $text, $time_match)) { $default_time = (string) $time_match[1]; }

        if ($context_year !== '' && preg_match_all('/\b(?:Mon|Tue|Wed|Thu|Fri|Sat|Sun)(?:day)?[,]?\s*([A-Z][a-z]{2,8})\s+(\d{1,2})[,]?\s+(\d{1,2}:\d{2}\s*[AP]M)\b/i', $text, $partial, PREG_SET_ORDER)) {
            foreach ($partial as $match) { $add($parse((string) $match[1], (string) $match[2], $context_year, (string) $match[3]), false); }
        }
        if ($context_year !== '' && preg_match_all('/\b([A-Z][a-z]{2,8})\s+(\d{1,2})\s*(?:-|–|—|to)\s*(\d{1,2})(?:\s*,?\s*(20\d{2}))?/i', $text, $ranges, PREG_SET_ORDER)) {
            foreach ($ranges as $match) {
                $year = !empty($match[4]) ? (string) $match[4] : $context_year;
                $start = $parse((string) $match[1], (string) $match[2], $year, $default_time);
                $end = $parse((string) $match[1], (string) $match[3], $year, $default_time);
                if (!$start || !$end || $end < $start) { continue; }
                for ($cursor = $start; $cursor <= $end; $cursor = $cursor->modify('+7 days')) { $add($cursor, $default_time === ''); }
            }
        }
        if (preg_match_all('/\b([A-Z][a-z]{2,8})\s+(\d{1,2})[,]?\s+(20\d{2})\b/i', $text, $dated, PREG_SET_ORDER)) {
            foreach ($dated as $match) { $add($parse((string) $match[1], (string) $match[2], (string) $match[3], $default_time), $default_time === ''); }
        }

        if ($service_name !== ''
            && !preg_match('/\b(?:\d+\s*[- ]?week|series|journey|sessions|course)\b/i', $service_name)
            && preg_match('/\b([A-Z][a-z]{2,8})\s+(\d{1,2})(?:[,]?\s*(20\d{2}))?\b/i', $service_name, $title)) {
            $year = !empty($title[3]) ? (string) $title[3] : ($context_year !== '' ? $context_year : $now->format('Y'));
            $title_date = $parse((string) $title[1], (string) $title[2], $year);
            if ($title_date && $title_date < $today) { return []; }
        }

        uasort($items, static fn(array $a, array $b): int => $a['date'] <=> $b['date']);
        return array_values($items);
    }

    /** @return array<int,string> */
    private static function provider_service_tables(): array {
        global $wpdb;
        $tables = $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($wpdb->prefix . 'amelia_') . '%')) ?: [];
        $valid = [];
        foreach ($tables as $table) {
            $columns = self::table_columns((string) $table);
            if (self::first_column($columns, ['userId', 'providerId', 'employeeId', 'provider_id', 'user_id', 'provider', 'employee'])
                && self::first_column($columns, ['serviceId', 'service_id', 'service'])) { $valid[] = (string) $table; }
        }
        return array_values(array_unique($valid));
    }

    private static function contains_employee($value, int $employee_id): bool {
        if ($value === null || $value === '') { return false; }
        if (is_numeric($value)) { return (int) $value === $employee_id; }
        if (is_array($value) || is_object($value)) {
            foreach ((array) $value as $key => $item) { if (self::contains_employee($key, $employee_id) || self::contains_employee($item, $employee_id)) { return true; } }
            return false;
        }
        $text = trim((string) $value);
        $decoded = json_decode($text, true);
        if (json_last_error() === JSON_ERROR_NONE && $decoded !== null) { return self::contains_employee($decoded, $employee_id); }
        $unserialized = maybe_unserialize($text);
        if ($unserialized !== $text) { return self::contains_employee($unserialized, $employee_id); }
        return preg_match('/(^|[^0-9])' . preg_quote((string) $employee_id, '/') . '([^0-9]|$)/', $text) === 1;
    }

    /** @return array<int,array{name:string,capacity:?int}> */
    private static function service_details(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'amelia_services';
        if (!self::table_exists($table)) { return []; }
        $columns = self::table_columns($table);
        $name = self::first_column($columns, ['name', 'title']);
        $capacity = self::first_column($columns, ['maxCapacity', 'max_capacity', 'capacity']);
        if (!in_array('id', $columns, true)) { return []; }
        $select = ['`id`', $name ? "`{$name}` AS name" : "'' AS name", $capacity ? "`{$capacity}` AS capacity" : 'NULL AS capacity'];
        $map = [];
        foreach ($wpdb->get_results('SELECT ' . implode(',', $select) . " FROM `{$table}`", ARRAY_A) ?: [] as $row) {
            $map[(int) $row['id']] = ['name' => (string) $row['name'], 'capacity' => self::positive_int($row['capacity'] ?? null)];
        }
        return $map;
    }

    /** @return array<int,int> */
    private static function booked_seats(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'amelia_customer_bookings';
        if (!self::table_exists($table)) { return []; }
        $columns = self::table_columns($table);
        $appointment = self::first_column($columns, ['appointmentId', 'appointment_id']);
        if (!$appointment) { return []; }
        $persons = self::first_column($columns, ['persons', 'personsCount', 'persons_count']);
        $status = self::first_column($columns, ['status']);
        $sum = $persons ? "SUM(COALESCE(`{$persons}`,1))" : 'COUNT(*)';
        $where = $status ? " WHERE LOWER(COALESCE(`{$status}`,'')) NOT IN ('canceled','cancelled','rejected')" : '';
        $map = [];
        foreach ($wpdb->get_results("SELECT `{$appointment}` AS appointment_id, {$sum} AS seats FROM `{$table}`{$where} GROUP BY `{$appointment}`", ARRAY_A) ?: [] as $row) { $map[(int) $row['appointment_id']] = max(0, (int) $row['seats']); }
        return $map;
    }

    private static function table_exists(string $table): bool { global $wpdb; return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table; }
    /** @return array<int,string> */
    private static function table_columns(string $table): array { global $wpdb; return $wpdb->get_col("SHOW COLUMNS FROM `{$table}`", 0) ?: []; }
    private static function first_column(array $columns, array $candidates): string { foreach ($candidates as $candidate) { if (in_array($candidate, $columns, true)) { return $candidate; } } return ''; }
    private static function positive_int($value): ?int { if ($value === null || $value === '' || !is_numeric($value)) { return null; } $value = (int) $value; return $value > 0 ? $value : null; }
}

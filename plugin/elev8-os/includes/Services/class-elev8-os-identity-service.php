<?php
/**
 * Central identity resolver for Elev8 OS.
 *
 * Explicit WordPress user to Amelia employee mapping always wins. Email is
 * used only when no valid explicit mapping exists.
 */
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Identity_Service {
    private const EMPLOYEE_META = 'elev8_os_amelia_employee_id';

    /** @var array<int,array<string,mixed>|null> */
    private static array $request_cache = [];

    /** @return array<string,mixed>|null */
    public static function current_artist(): ?array {
        return self::artist_for_user(wp_get_current_user());
    }

    public static function current_artist_id(): int {
        $artist = self::current_artist();
        return is_array($artist) ? absint($artist['id'] ?? 0) : 0;
    }

    /** @return array<string,mixed>|null */
    public static function artist_for_user(WP_User $user): ?array {
        $user_id = absint($user->ID);
        if ($user_id <= 0) { return null; }
        if (array_key_exists($user_id, self::$request_cache)) {
            return self::$request_cache[$user_id];
        }

        global $wpdb;
        $table = $wpdb->prefix . 'amelia_users';
        if (!self::table_exists($table)) {
            return self::$request_cache[$user_id] = null;
        }

        $columns = self::table_columns($table);
        if (!in_array('id', $columns, true)) {
            return self::$request_cache[$user_id] = null;
        }

        $select = ['id'];
        foreach (['firstName', 'lastName', 'email', 'type'] as $column) {
            if (in_array($column, $columns, true)) { $select[] = $column; }
        }
        $select_sql = implode(', ', array_map(static fn(string $column): string => "`{$column}`", $select));
        $type_sql = in_array('type', $columns, true)
            ? " AND LOWER(COALESCE(`type`,'')) IN ('provider','employee')"
            : '';

        $mapped_id = absint(get_user_meta($user_id, self::EMPLOYEE_META, true));
        if ($mapped_id > 0) {
            $row = $wpdb->get_row(
                $wpdb->prepare("SELECT {$select_sql} FROM `{$table}` WHERE `id` = %d{$type_sql} LIMIT 1", $mapped_id),
                ARRAY_A
            );
            if (is_array($row)) {
                $row['_identity_source'] = 'explicit_mapping';
                return self::$request_cache[$user_id] = $row;
            }
        }

        $email = sanitize_email((string) $user->user_email);
        if ($email === '' || !in_array('email', $columns, true)) {
            return self::$request_cache[$user_id] = null;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT {$select_sql} FROM `{$table}` WHERE LOWER(`email`) = LOWER(%s){$type_sql} LIMIT 1", $email),
            ARRAY_A
        );
        if (is_array($row)) { $row['_identity_source'] = 'email_fallback'; }
        return self::$request_cache[$user_id] = (is_array($row) ? $row : null);
    }

    /**
     * Resolve the approved WordPress account for an Amelia artist.
     * Explicit Artist Mapping is the source of truth.
     */
    public static function user_id_for_artist(int $artist_id): int {
        $artist_id = absint($artist_id);
        if ($artist_id <= 0) { return 0; }

        $users = get_users([
            'meta_key'   => self::EMPLOYEE_META,
            'meta_value' => (string) $artist_id,
            'number'     => 2,
            'fields'     => 'ids',
        ]);

        if (!is_array($users) || count($users) !== 1) { return 0; }
        return absint($users[0]);
    }

    /** Resolve an artist ID from an explicitly approved WordPress account. */
    public static function artist_id_for_user_id(int $user_id): int {
        $user_id = absint($user_id);
        if ($user_id <= 0) { return 0; }
        return absint(get_user_meta($user_id, self::EMPLOYEE_META, true));
    }

    public static function clear_request_cache(?int $user_id = null): void {
        if ($user_id === null) { self::$request_cache = []; return; }
        unset(self::$request_cache[absint($user_id)]);
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
}

<?php
/** Centralized settings access for Elev8 OS-owned options. */
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Settings_Service {
    private const PREFIX = 'elev8_os_';

    public static function get(string $key, $default = null) {
        return get_option(self::option_name($key), $default);
    }

    public static function set(string $key, $value, bool $autoload = false): bool {
        return update_option(self::option_name($key), $value, $autoload);
    }

    public static function delete(string $key): bool {
        return delete_option(self::option_name($key));
    }

    public static function option_name(string $key): string {
        $key = sanitize_key($key);
        return str_starts_with($key, self::PREFIX) ? $key : self::PREFIX . $key;
    }
}

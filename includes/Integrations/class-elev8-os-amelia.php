<?php
if (!defined('ABSPATH')) { exit; }
final class Elev8_OS_Amelia {
    public static function is_available(): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'amelia_users';
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }
}

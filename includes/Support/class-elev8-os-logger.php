<?php
if (!defined('ABSPATH')) { exit; }
final class Elev8_OS_Logger {
    public static function error(string $message, array $context = []): void {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Elev8 OS] ' . $message . ($context ? ' ' . wp_json_encode($context) : ''));
        }
    }
}

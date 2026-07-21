<?php
/**
 * Site-wide responsive layout guard for Elev8 Arts and Elev8 OS.
 *
 * @package Elev8OS
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Elev8_OS_Site_Layout_Guard_Module {

    public static function init(): void {
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue'], 1);
        add_filter('body_class', [__CLASS__, 'body_class']);
        add_filter('language_attributes', [__CLASS__, 'language_attributes']);
    }

    public static function enqueue(): void {
        if (is_admin()) {
            return;
        }

        wp_enqueue_style(
            'elev8-os-site-layout-guard',
            ELEV8_OS_URL . 'assets/css/site-layout-guard.css',
            [],
            ELEV8_OS_VERSION
        );
    }

    /** @param array<int,string> $classes */
    public static function body_class(array $classes): array {
        if (!is_admin()) {
            $classes[] = 'elev8-layout-guard';
        }

        return array_values(array_unique($classes));
    }

    public static function language_attributes(string $output): string {
        if (is_admin() || strpos($output, 'elev8-layout-guard') !== false) {
            return $output;
        }

        return trim($output . ' class="elev8-layout-guard"');
    }
}

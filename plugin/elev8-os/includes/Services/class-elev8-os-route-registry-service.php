<?php
/**
 * Canonical route registry for Elev8 OS application destinations.
 *
 * Modules and workspaces request a route by identifier instead of inventing
 * URLs or calling optional module helpers directly.
 */
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Route_Registry_Service {
    private static array $routes = [];

    public static function init(): void {
        add_action('init', [__CLASS__, 'register_core_routes'], 12);
    }

    public static function register(string $id, $resolver): void {
        $id = sanitize_key($id);
        if ($id === '' || (!is_string($resolver) && !is_callable($resolver))) { return; }
        self::$routes[$id] = $resolver;
    }

    public static function register_core_routes(): void {
        self::register('dashboard', static function(): string {
            return class_exists('Elev8_OS_Unified_Dashboard_Service')
                ? Elev8_OS_Unified_Dashboard_Service::url()
                : home_url('/elev8-workspace/');
        });
        self::register('artist-workspace', static function(): string {
            return class_exists('Elev8_OS_Unified_Dashboard_Service')
                ? Elev8_OS_Unified_Dashboard_Service::url('artist')
                : add_query_arg('workspace', 'artist', home_url('/elev8-workspace/'));
        });
        self::register('conversations', home_url('/elev8-conversations/'));
        self::register('problem-report', static function(): string {
            return class_exists('Elev8_OS_Problem_Report_Module')
                ? Elev8_OS_Problem_Report_Module::page_url()
                : home_url('/report-a-problem/');
        });
    }

    public static function has(string $id): bool {
        return array_key_exists(sanitize_key($id), self::$routes);
    }

    public static function url(string $id, array $args = []): string {
        $id = sanitize_key($id);
        if (!self::has($id)) {
            return class_exists('Elev8_OS_Unified_Dashboard_Service')
                ? Elev8_OS_Unified_Dashboard_Service::url()
                : home_url('/elev8-workspace/');
        }
        $resolver = self::$routes[$id];
        $url = is_callable($resolver) ? (string) call_user_func($resolver) : (string) $resolver;
        return $args ? add_query_arg($args, $url) : $url;
    }

    public static function all(): array {
        return self::$routes;
    }
}

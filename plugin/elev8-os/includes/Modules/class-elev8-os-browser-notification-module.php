<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Browser notification support without an installable-app prompt.
 *
 * Keeps the service worker needed by class alerts and future Elev8 OS
 * notifications while deliberately omitting a web-app manifest or PWA UI.
 */
final class Elev8_OS_Browser_Notification_Module {
    private const SERVICE_WORKER_QUERY = 'elev8_os_notification_worker';

    public static function init(): void {
        add_action('template_redirect', [__CLASS__, 'serve_worker'], 0);
        add_action('wp_enqueue_scripts', [__CLASS__, 'assets']);
    }

    public static function assets(): void {
        if (is_admin() || !is_user_logged_in()) { return; }
        wp_enqueue_script(
            'elev8-os-browser-notifications',
            ELEV8_OS_URL . 'assets/js/browser-notifications.js',
            [],
            ELEV8_OS_VERSION,
            true
        );
        wp_localize_script('elev8-os-browser-notifications', 'Elev8OSBrowserNotifications', [
            'serviceWorkerUrl' => self::worker_url(),
            'serviceWorkerScope' => self::scope(),
        ]);
    }

    public static function serve_worker(): void {
        if (!isset($_GET[self::SERVICE_WORKER_QUERY])) { return; }
        $home = class_exists('Elev8_OS_Workspace_Resolver_Service')
            ? Elev8_OS_Workspace_Resolver_Service::destination(null, true)
            : home_url('/');
        nocache_headers();
        header('Content-Type: application/javascript; charset=UTF-8');
        header('Service-Worker-Allowed: ' . self::scope());
        echo "self.addEventListener('install',event=>{self.skipWaiting();});\n";
        echo "self.addEventListener('activate',event=>{event.waitUntil(self.clients.claim());});\n";
        echo "self.addEventListener('notificationclick',event=>{event.notification.close();const url=(event.notification.data&&event.notification.data.url)||'" . esc_js($home) . "';event.waitUntil(clients.matchAll({type:'window',includeUncontrolled:true}).then(list=>{for(const client of list){if('focus' in client){client.navigate(url);return client.focus();}}return clients.openWindow(url);}));});\n";
        exit;
    }

    private static function worker_url(): string {
        return add_query_arg(self::SERVICE_WORKER_QUERY, '1', home_url('/'));
    }

    private static function scope(): string {
        $path = (string) wp_parse_url(home_url('/'), PHP_URL_PATH);
        return $path !== '' ? trailingslashit($path) : '/';
    }
}

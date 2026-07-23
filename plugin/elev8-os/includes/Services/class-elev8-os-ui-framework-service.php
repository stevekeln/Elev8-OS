<?php
/**
 * Elev8 OS presentation registry and theme-pack resolver.
 * Engines expose capability; this service exposes presentation context only.
 */
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_UI_Framework_Service {
    public const OPTION = 'elev8_os_ui_framework';

    public static function init(): void {
        add_filter('body_class', [__CLASS__, 'body_classes']);
        add_filter('admin_body_class', [__CLASS__, 'admin_body_classes']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin']);
    }

    public static function defaults(): array {
        return [
            'enabled' => 1,
            'theme_pack' => 'business',
            'role_shells' => 1,
            'legacy_bridge' => 1,
        ];
    }

    public static function settings(): array {
        return wp_parse_args((array) get_option(self::OPTION, []), self::defaults());
    }

    public static function enabled(): bool {
        return !empty(self::settings()['enabled']);
    }

    public static function is_os_request(): bool {
        if (!is_user_logged_in()) { return false; }
        if (is_admin()) {
            $page = sanitize_key((string) ($_GET['page'] ?? ''));
            return $page !== '' && (strpos($page, 'elev8') === 0 || $page === 'elev8-os');
        }
        if (class_exists('Elev8_OS_Clean_App_Module') && Elev8_OS_Clean_App_Module::is_request()) { return true; }
        $path = trim((string) wp_parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
        return (bool) preg_match('~(^|/)(elev8|artist|glass|shop|manager|volunteer|event|teacher|production|checkin|mobile)(-|/|$)~i', $path);
    }

    public static function shell_for(?WP_User $user = null): string {
        $user = $user ?: wp_get_current_user();
        $roles = array_map('sanitize_key', (array) $user->roles);
        if (in_array('administrator', $roles, true) || in_array('ceo', $roles, true)) { return 'executive'; }
        foreach (['glass_manager','glassblower','studio_manager'] as $role) { if (in_array($role, $roles, true)) { return 'studio'; } }
        foreach (['shop_manager','shop_employee','retail_employee'] as $role) { if (in_array($role, $roles, true)) { return 'retail'; } }
        foreach (['artist','elev8_artist','teacher'] as $role) { if (in_array($role, $roles, true)) { return 'artist'; } }
        foreach (['volunteer','event_staff','event_host','dj'] as $role) { if (in_array($role, $roles, true)) { return 'event'; } }
        return 'business';
    }

    public static function theme_pack(): string {
        $allowed = ['business','studio','retail','executive'];
        $pack = sanitize_key((string) (self::settings()['theme_pack'] ?? 'business'));
        return in_array($pack, $allowed, true) ? $pack : 'business';
    }

    public static function body_classes(array $classes): array {
        if (!self::enabled() || !self::is_os_request()) { return $classes; }
        $classes[] = 'elev8-ui';
        $classes[] = 'elev8-ui--theme-' . self::theme_pack();
        if (!empty(self::settings()['role_shells'])) { $classes[] = 'elev8-ui--shell-' . self::shell_for(); }
        if (!empty(self::settings()['legacy_bridge'])) { $classes[] = 'elev8-ui--legacy-bridge'; }
        return array_values(array_unique($classes));
    }

    public static function admin_body_classes(string $classes): string {
        if (!self::enabled() || !self::is_os_request()) { return $classes; }
        return trim($classes . ' elev8-ui elev8-ui--theme-' . self::theme_pack() . ' elev8-ui--shell-' . self::shell_for() . ' elev8-ui--legacy-bridge');
    }

    public static function enqueue(): void {
        if (!self::enabled() || !self::is_os_request()) { return; }
        self::enqueue_assets();
    }

    public static function enqueue_admin(): void {
        if (!self::enabled() || !self::is_os_request()) { return; }
        self::enqueue_assets();
    }

    private static function enqueue_assets(): void {
        wp_enqueue_style('elev8-os-ui-tokens', ELEV8_OS_URL . 'assets/css/ui-tokens.css', [], ELEV8_OS_VERSION);
        wp_enqueue_style('elev8-os-ui-components', ELEV8_OS_URL . 'assets/css/ui-components.css', ['elev8-os-ui-tokens'], ELEV8_OS_VERSION);
        wp_enqueue_style('elev8-os-ui-shells', ELEV8_OS_URL . 'assets/css/ui-shells.css', ['elev8-os-ui-components'], ELEV8_OS_VERSION);
    }
}

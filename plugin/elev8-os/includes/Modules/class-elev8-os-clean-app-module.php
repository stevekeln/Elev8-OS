<?php
/**
 * Theme-isolated Elev8 OS application gateway.
 *
 * WordPress continues to own authentication and data. Elev8 OS owns the
 * presentation for managed application screens so public-theme CSS, headers,
 * footers, notices, and mobile offsets cannot leak into the employee app.
 *
 * @package Elev8OS
 */
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Clean_App_Module {
    private const QUERY_VAR = 'elev8_app';

    public static function init(): void {
        add_filter('query_vars', [__CLASS__, 'query_vars']);
        add_action('template_redirect', [__CLASS__, 'require_login'], 0);
        add_filter('template_include', [__CLASS__, 'template_include'], 99999);
        add_filter('show_admin_bar', [__CLASS__, 'hide_admin_bar'], 99999);
        add_action('wp_enqueue_scripts', [__CLASS__, 'isolate_assets'], 99999);
        add_filter('body_class', [__CLASS__, 'body_class']);
    }

    public static function query_vars(array $vars): array {
        $vars[] = self::QUERY_VAR;
        return $vars;
    }

    public static function is_request(): bool {
        return !is_admin() && sanitize_key((string) get_query_var(self::QUERY_VAR)) !== '';
    }

    public static function screen(): string {
        return sanitize_key((string) get_query_var(self::QUERY_VAR));
    }

    public static function url(string $screen = 'home', array $args = []): string {
        $args = array_merge([self::QUERY_VAR => sanitize_key($screen)], $args);
        if (class_exists('Elev8_OS_Preview_Service') && Elev8_OS_Preview_Service::is_clean_request()) {
            $args['elev8_clean_preview'] = '1';
        }
        return add_query_arg($args, home_url('/'));
    }

    public static function require_login(): void {
        if (!self::is_request()) { return; }
        if (!is_user_logged_in()) {
            auth_redirect();
        }
    }

    public static function template_include(string $template): string {
        if (!self::is_request()) { return $template; }
        $clean = ELEV8_OS_DIR . 'templates/clean-app.php';
        return is_readable($clean) ? $clean : $template;
    }

    public static function hide_admin_bar($show): bool {
        return self::is_request() ? false : (bool) $show;
    }

    public static function isolate_assets(): void {
        if (!self::is_request()) { return; }

        global $wp_styles;
        if ($wp_styles instanceof WP_Styles) {
            foreach ((array) $wp_styles->queue as $handle) {
                if (!self::keep_style((string) $handle)) {
                    wp_dequeue_style((string) $handle);
                }
            }
        }

        wp_enqueue_style('dashicons');
        wp_enqueue_style('elev8-os-clean-app', ELEV8_OS_URL . 'assets/css/clean-app.css', [], ELEV8_OS_VERSION);
        wp_enqueue_style('elev8-os-business-intelligence-dashboard', ELEV8_OS_URL . 'assets/css/business-intelligence-dashboard.css', [], ELEV8_OS_VERSION);
    }

    private static function keep_style(string $handle): bool {
        return $handle === 'dashicons' || strpos($handle, 'elev8-os') === 0 || strpos($handle, 'elev8_') === 0;
    }

    public static function body_class(array $classes): array {
        if (self::is_request()) {
            $classes[] = 'elev8-clean-app';
            $classes[] = 'elev8-clean-app--' . self::screen();
        }
        return $classes;
    }

    public static function title(): string {
        switch (self::screen()) {
            case 'ceo': return __('CEO Dashboard', 'elev8-os');
            default: return __('Elev8 OS', 'elev8-os');
        }
    }

    public static function render_screen(): void {
        switch (self::screen()) {
            case 'ceo':
                if (class_exists('Elev8_OS_CEO_Dashboard_Module')) {
                    Elev8_OS_CEO_Dashboard_Module::render_page();
                    return;
                }
                break;
            case 'home':
            default:
                $user = class_exists('Elev8_OS_Preview_Service') ? Elev8_OS_Preview_Service::effective_user() : wp_get_current_user();
                $destination = class_exists('Elev8_OS_Workspace_Resolver_Service') ? Elev8_OS_Workspace_Resolver_Service::primary_destination_for($user) : home_url('/');
                if ($destination !== self::url('home')) {
                    wp_safe_redirect($destination);
                    exit;
                }
        }
        echo '<div class="elev8-panel"><h1>' . esc_html__('Elev8 OS screen unavailable', 'elev8-os') . '</h1><p>' . esc_html__('This workspace could not be loaded.', 'elev8-os') . '</p></div>';
    }
}

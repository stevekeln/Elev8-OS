<?php
if (!defined('ABSPATH')) { exit; }

/** Shared mobile application controls for all Elev8 OS role workspaces. */
final class Elev8_OS_Experience_Engine_Module {
    public static function init(): void {
        add_action('wp_enqueue_scripts', [__CLASS__, 'assets']);
        add_action('wp_footer', [__CLASS__, 'render_mobile_dock'], 50);
        add_filter('body_class', [__CLASS__, 'body_class']);
    }

    public static function assets(): void {
        if (!self::should_render()) { return; }
        wp_enqueue_style('dashicons');
        wp_enqueue_style('elev8-os-experience-engine', ELEV8_OS_URL . 'assets/css/experience-engine.css', [], ELEV8_OS_VERSION);
    }

    public static function body_class(array $classes): array {
        if (self::should_render()) {
            $classes[] = 'elev8-experience-engine';
            if (class_exists('Elev8_OS_Workspace_Resolver_Service')) {
                $classes[] = 'elev8-experience-' . sanitize_html_class(Elev8_OS_Workspace_Resolver_Service::role_key());
            }
        }
        return $classes;
    }

    public static function render_mobile_dock(): void {
        if (!self::should_render() || !class_exists('Elev8_OS_Experience_Service')) { return; }
        $user = class_exists('Elev8_OS_Preview_Service') ? Elev8_OS_Preview_Service::effective_user() : wp_get_current_user();
        $links = Elev8_OS_Experience_Service::role_shortcuts($user);
        if (!$links) { return; }
        echo '<nav class="elev8-experience-dock" aria-label="' . esc_attr__('Elev8 OS quick navigation', 'elev8-os') . '">';
        foreach ($links as $link) {
            echo '<a href="' . esc_url($link['url']) . '"><span class="dashicons ' . esc_attr($link['icon']) . '" aria-hidden="true"></span><span>' . esc_html($link['label']) . '</span></a>';
        }
        echo '</nav>';
    }

    private static function should_render(): bool {
        if (is_admin() || wp_doing_ajax() || !is_user_logged_in()) { return false; }
        $scheme = is_ssl() ? 'https' : 'http';
        $host = sanitize_text_field((string) ($_SERVER['HTTP_HOST'] ?? wp_parse_url(home_url('/'), PHP_URL_HOST)));
        $uri = (string) wp_unslash($_SERVER['REQUEST_URI'] ?? '/');
        return class_exists('Elev8_OS_Experience_Service') && Elev8_OS_Experience_Service::is_managed_workspace_url($scheme . '://' . $host . $uri);
    }
}

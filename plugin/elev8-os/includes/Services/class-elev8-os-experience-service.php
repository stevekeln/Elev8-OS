<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Shared role experience state for Elev8 OS frontend workspaces.
 *
 * Keeps role landing, last-workspace memory, and role shortcuts consistent
 * without moving business logic out of the modules that own it.
 */
final class Elev8_OS_Experience_Service {
    private const META_LAST_WORKSPACE = '_elev8_os_last_workspace_url';
    private const META_LAST_SEEN = '_elev8_os_last_workspace_seen_at';

    public static function init(): void {
        add_action('template_redirect', [__CLASS__, 'remember_current_workspace'], 99);
    }

    public static function remember_current_workspace(): void {
        if (is_admin() || wp_doing_ajax() || !is_user_logged_in() || is_404()) { return; }
        if (class_exists('Elev8_OS_Preview_Service') && Elev8_OS_Preview_Service::is_active()) { return; }

        $user = wp_get_current_user();
        if (!$user instanceof WP_User || $user->ID <= 0) { return; }

        $url = self::current_url();
        if (!self::is_managed_workspace_url($url)) { return; }
        if (!class_exists('Elev8_OS_Workspace_Resolver_Service')) { return; }

        Elev8_OS_Workspace_Resolver_Service::remember($url, $user);
        update_user_meta($user->ID, self::META_LAST_SEEN, current_time('mysql', true));
    }

    public static function last_workspace(?WP_User $user = null): string {
        $user = $user ?: wp_get_current_user();
        if (!$user instanceof WP_User || $user->ID <= 0) { return ''; }
        return esc_url_raw((string) get_user_meta($user->ID, self::META_LAST_WORKSPACE, true));
    }

    public static function last_seen(?WP_User $user = null): string {
        $user = $user ?: wp_get_current_user();
        if (!$user instanceof WP_User || $user->ID <= 0) { return ''; }
        return sanitize_text_field((string) get_user_meta($user->ID, self::META_LAST_SEEN, true));
    }

    /** @return array<int,array{label:string,url:string,icon:string}> */
    public static function role_shortcuts(?WP_User $user = null): array {
        $user = $user ?: wp_get_current_user();
        if (!$user instanceof WP_User || !class_exists('Elev8_OS_Workspace_Resolver_Service')) { return []; }

        $role = Elev8_OS_Workspace_Resolver_Service::role_key($user);
        $home = Elev8_OS_Workspace_Resolver_Service::primary_destination_for($user);
        $links = [['label' => __('Home', 'elev8-os'), 'url' => $home, 'icon' => 'dashicons-admin-home']];

        if (class_exists('Elev8_OS_Action_Center_Module') && Elev8_OS_Access_Service::user_can('view_work', $user)) {
            $links[] = ['label' => __('Actions', 'elev8-os'), 'url' => Elev8_OS_Action_Center_Module::url(), 'icon' => 'dashicons-yes-alt'];
        }
        if (class_exists('Elev8_OS_Conversations_Module') && Elev8_OS_Access_Service::user_can('view_conversations', $user)) {
            $links[] = ['label' => __('Messages', 'elev8-os'), 'url' => Elev8_OS_Conversations_Module::url(), 'icon' => 'dashicons-format-chat'];
        }
        if ($role === 'glass_manager' && class_exists('Elev8_OS_Glass_Manager_Suite_Module')) {
            $links[] = ['label' => __('Board', 'elev8-os'), 'url' => Elev8_OS_Glass_Manager_Suite_Module::url(['suite_tool'=>'operations','view'=>'board']), 'icon' => 'dashicons-screenoptions'];
            $links[] = ['label' => __('Approvals', 'elev8-os'), 'url' => Elev8_OS_Glass_Manager_Suite_Module::url(['suite_tool'=>'approvals']), 'icon' => 'dashicons-calendar-alt'];
        } elseif ($role === 'glassblower' && class_exists('Elev8_OS_Glass_Workbench_Module')) {
            $links[] = ['label' => __('Workbench', 'elev8-os'), 'url' => Elev8_OS_Glass_Workbench_Module::url(), 'icon' => 'dashicons-hammer'];
        } elseif (in_array($role, ['artist','teacher'], true) && class_exists('Elev8_OS_Portal_Page_Manager')) {
            $links[] = ['label' => __('Classes', 'elev8-os'), 'url' => Elev8_OS_Portal_Page_Manager::get_url('classes'), 'icon' => 'dashicons-calendar-alt'];
        }

        return array_slice($links, 0, 5);
    }

    public static function is_managed_workspace_url(string $url): bool {
        $path = strtolower((string) wp_parse_url($url, PHP_URL_PATH));
        if ($path === '') { return false; }
        foreach (['/glass-manager','/glass-workbench','/artist-dashboard','/elev8-actions','/elev8-conversations','/elev8-workspace','/mobile-home','/checkin'] as $needle) {
            if (strpos($path, $needle) !== false) { return true; }
        }
        return false;
    }

    private static function current_url(): string {
        $scheme = is_ssl() ? 'https' : 'http';
        $host = sanitize_text_field((string) ($_SERVER['HTTP_HOST'] ?? wp_parse_url(home_url('/'), PHP_URL_HOST)));
        $uri = (string) wp_unslash($_SERVER['REQUEST_URI'] ?? '/');
        return esc_url_raw($scheme . '://' . $host . $uri);
    }
}

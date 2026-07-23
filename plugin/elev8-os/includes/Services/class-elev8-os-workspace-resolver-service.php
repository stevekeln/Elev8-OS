<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Central source of truth for role-aware Elev8 OS landing destinations.
 *
 * WordPress owns authentication and roles. Elev8 OS Access Service owns
 * capabilities. This resolver translates those capabilities into one stable
 * application home so login, headers, preview, mobile launch, and legacy
 * dashboard links cannot disagree.
 */
final class Elev8_OS_Workspace_Resolver_Service {
    private const META_LAST_WORKSPACE = '_elev8_os_last_workspace_url';

    public static function init(): void {
        add_filter('login_redirect', [__CLASS__, 'filter_login_redirect'], 10000, 3);
        add_filter('um_login_redirect_url', [__CLASS__, 'filter_um_redirect'], 10000, 2);
        add_action('um_on_login_before_redirect', [__CLASS__, 'um_before_redirect'], 10000, 1);
        add_action('template_redirect', [__CLASS__, 'redirect_legacy_dashboard'], 2);
        add_action('set_user_role', [__CLASS__, 'clear_user_memory'], 10, 3);
        add_action('add_user_role', [__CLASS__, 'clear_user_memory'], 10, 2);
        add_action('remove_user_role', [__CLASS__, 'clear_user_memory'], 10, 2);
        add_action('admin_menu', [__CLASS__, 'register_diagnostics'], 95);
    }

    public static function destination(?WP_User $user = null, bool $allow_remembered = false): string {
        $user = $user ?: (class_exists('Elev8_OS_Preview_Service') ? Elev8_OS_Preview_Service::effective_user() : wp_get_current_user());
        if (!$user instanceof WP_User || $user->ID <= 0) { return home_url('/'); }

        $primary = self::primary_destination($user);
        if (!$allow_remembered) { return $primary; }

        $remembered = (string) get_user_meta($user->ID, self::META_LAST_WORKSPACE, true);
        if ($remembered !== '' && self::is_allowed_workspace($remembered, $user)) {
            return $remembered;
        }
        return $primary;
    }

    public static function primary_destination_for(?WP_User $user = null): string {
        $user = $user ?: wp_get_current_user();
        if (!$user instanceof WP_User || $user->ID <= 0) { return home_url('/'); }
        return self::primary_destination($user);
    }

    public static function role_key(?WP_User $user = null): string {
        $user = $user ?: wp_get_current_user();
        if (!$user instanceof WP_User) { return 'team'; }
        if (Elev8_OS_Access_Service::user_can('view_ceo_dashboard', $user)) { return 'owner'; }
        if (Elev8_OS_Access_Service::user_can('view_glass_dashboard', $user)) { return 'glass_manager'; }
        if (Elev8_OS_Access_Service::user_can('view_manager_dashboard', $user)) { return 'shop_manager'; }
        if (Elev8_OS_Access_Service::uses_event_host_home($user)) { return 'event_host'; }
        if (Elev8_OS_Access_Service::user_can('glass_work', $user)) { return 'glassblower'; }
        if (Elev8_OS_Access_Service::is_teacher($user)) { return 'teacher'; }
        if (Elev8_OS_Access_Service::user_can('view_artist_dashboard', $user)) { return 'artist'; }
        if (Elev8_OS_Access_Service::user_can('view_volunteer_portal', $user)) { return 'volunteer'; }
        if (Elev8_OS_Access_Service::user_can('manage_retail_operations', $user) || Elev8_OS_Access_Service::user_can('submit_retail_log', $user)) { return 'retail'; }
        return 'team';
    }

    public static function role_label(?WP_User $user = null): string {
        $labels = [
            'owner' => __('Owner', 'elev8-os'),
            'glass_manager' => __('Glass Manager', 'elev8-os'),
            'shop_manager' => __('Shop Manager', 'elev8-os'),
            'event_host' => __('Event Host', 'elev8-os'),
            'glassblower' => __('Glassblower', 'elev8-os'),
            'teacher' => __('Teacher', 'elev8-os'),
            'artist' => __('Artist', 'elev8-os'),
            'volunteer' => __('Volunteer', 'elev8-os'),
            'retail' => __('Retail Employee', 'elev8-os'),
            'team' => __('Elev8 Team', 'elev8-os'),
        ];
        return $labels[self::role_key($user)] ?? $labels['team'];
    }

    public static function filter_login_redirect(string $redirect_to, string $requested_redirect_to, $user): string {
        if (!$user instanceof WP_User || is_wp_error($user)) { return $redirect_to; }
        if (user_can($user, 'manage_options') && !self::has_operational_role($user)) { return $redirect_to; }
        if (!self::has_operational_role($user)) { return $redirect_to; }
        return self::destination($user);
    }

    public static function filter_um_redirect(string $redirect_url, int $user_id): string {
        $user = get_user_by('id', $user_id);
        return $user instanceof WP_User && self::has_operational_role($user) ? self::destination($user) : $redirect_url;
    }

    public static function um_before_redirect(int $user_id): void {
        $user = get_user_by('id', $user_id);
        if (!$user instanceof WP_User || !self::has_operational_role($user)) { return; }
        wp_safe_redirect(self::destination($user));
        exit;
    }

    public static function redirect_legacy_dashboard(): void {
        if (is_admin() || wp_doing_ajax() || !is_user_logged_in()) { return; }
        if (!class_exists('Elev8_OS_Portal_Page_Manager') || !Elev8_OS_Portal_Page_Manager::is_current_page('dashboard')) { return; }
        $user = class_exists('Elev8_OS_Preview_Service') ? Elev8_OS_Preview_Service::effective_user() : wp_get_current_user();
        if (!$user instanceof WP_User) { return; }
        $destination = self::destination($user);
        $current = home_url((string) wp_parse_url((string) wp_unslash($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH));
        if (self::same_destination($current, $destination)) { return; }
        // Artist/teacher/event/manager dashboards intentionally share this page.
        if (!in_array(self::role_key($user), ['owner','glass_manager'], true)) { return; }
        wp_safe_redirect($destination);
        exit;
    }

    public static function remember(string $url, ?WP_User $user = null): void {
        $user = $user ?: wp_get_current_user();
        if (!$user instanceof WP_User || !$user->ID || !self::is_allowed_workspace($url, $user)) { return; }
        update_user_meta($user->ID, self::META_LAST_WORKSPACE, esc_url_raw($url));
    }

    public static function clear_user_memory(int $user_id): void {
        delete_user_meta($user_id, self::META_LAST_WORKSPACE);
    }

    public static function register_diagnostics(): void {
        add_submenu_page('elev8-os', __('Workspace Diagnostics', 'elev8-os'), __('Workspace Diagnostics', 'elev8-os'), 'manage_options', 'elev8-workspace-diagnostics', [__CLASS__, 'render_diagnostics']);
    }

    public static function render_diagnostics(): void {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('You do not have permission to view this page.', 'elev8-os')); }
        $users = get_users(['orderby' => 'display_name', 'order' => 'ASC']);
        echo '<div class="wrap"><h1>' . esc_html__('Workspace Diagnostics', 'elev8-os') . '</h1><p>' . esc_html__('The centralized resolver result for each Elev8 OS user.', 'elev8-os') . '</p>';
        echo '<table class="widefat striped"><thead><tr><th>User</th><th>WordPress roles</th><th>Resolved role</th><th>Destination</th></tr></thead><tbody>';
        foreach ($users as $user) {
            if (!$user instanceof WP_User || !self::has_operational_role($user)) { continue; }
            echo '<tr><td>' . esc_html($user->display_name . ' (' . $user->user_email . ')') . '</td><td>' . esc_html(implode(', ', $user->roles)) . '</td><td>' . esc_html(self::role_label($user)) . '</td><td><code>' . esc_html(self::destination($user)) . '</code></td></tr>';
        }
        echo '</tbody></table></div>';
    }

    private static function primary_destination(WP_User $user): string {
        switch (self::role_key($user)) {
            case 'owner': return class_exists('Elev8_OS_Clean_App_Module') ? Elev8_OS_Clean_App_Module::url('ceo') : admin_url('admin.php?page=elev8-ceo-dashboard');
            case 'glass_manager': return class_exists('Elev8_OS_Glass_Manager_Suite_Module') ? Elev8_OS_Glass_Manager_Suite_Module::url() : home_url('/glass-manager/');
            case 'glassblower': return class_exists('Elev8_OS_Glass_Workbench_Module') ? Elev8_OS_Glass_Workbench_Module::url() : home_url('/glass-workbench/');
            case 'retail':
                return class_exists('Elev8_OS_Workspace_Runtime_Module') ? Elev8_OS_Workspace_Runtime_Module::url() : (class_exists('Elev8_OS_Mobile_Home_Module') ? Elev8_OS_Mobile_Home_Module::get_url() : home_url('/'));
            case 'shop_manager':
            case 'event_host':
            case 'teacher':
            case 'artist':
            case 'volunteer':
                return class_exists('Elev8_OS_Portal_Page_Manager') ? Elev8_OS_Portal_Page_Manager::get_url('dashboard') : home_url('/artist-dashboard/');
            default:
                return class_exists('Elev8_OS_Mobile_Home_Module') ? Elev8_OS_Mobile_Home_Module::get_url() : home_url('/');
        }
    }

    private static function has_operational_role(WP_User $user): bool {
        return self::role_key($user) !== 'team';
    }

    private static function is_allowed_workspace(string $url, WP_User $user): bool {
        $host = (string) wp_parse_url($url, PHP_URL_HOST);
        $site_host = (string) wp_parse_url(home_url('/'), PHP_URL_HOST);
        if ($host !== '' && strtolower($host) !== strtolower($site_host)) { return false; }
        $role = self::role_key($user);
        if ($role === 'glass_manager') { return strpos($url, '/glass-manager') !== false; }
        if ($role === 'glassblower') { return strpos($url, '/glass-workbench') !== false || strpos($url, '/elev8-actions') !== false || strpos($url, '/elev8-conversations') !== false || strpos($url, '/elev8-workspace') !== false; }
        return class_exists('Elev8_OS_Experience_Service') ? Elev8_OS_Experience_Service::is_managed_workspace_url($url) : true;
    }

    private static function same_destination(string $left, string $right): bool {
        $normalize = static function(string $url): string {
            $path = (string) wp_parse_url($url, PHP_URL_PATH);
            return untrailingslashit($path);
        };
        return $normalize($left) === $normalize($right);
    }
}

<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Founder-only role and user preview context.
 *
 * Preview changes only the effective Elev8 OS identity used by dashboards and
 * the centralized Access Service. WordPress authentication remains the owner,
 * so no passwords or account switching are required.
 */
final class Elev8_OS_Preview_Service {
    private const META_TARGET = '_elev8_os_preview_target_user';
    private const META_ROLE = '_elev8_os_preview_role';

    public static function init(): void {
        add_action('admin_post_elev8_os_start_preview', [__CLASS__, 'start_preview']);
        add_action('admin_post_elev8_os_stop_preview', [__CLASS__, 'stop_preview']);
        add_filter('pre_wp_mail', [__CLASS__, 'suppress_mail_during_preview'], 10, 2);
        add_filter('elev8_os_notifications_suppressed', [__CLASS__, 'notifications_suppressed']);
        add_filter('show_admin_bar', [__CLASS__, 'filter_admin_bar']);
        add_action('admin_init', [__CLASS__, 'protect_preview_writes'], 1);
        add_filter('rest_pre_dispatch', [__CLASS__, 'protect_preview_rest_writes'], 10, 3);
    }

    public static function can_preview(?WP_User $user = null): bool {
        $user = $user ?: wp_get_current_user();
        return $user instanceof WP_User && $user->ID > 0 && user_can($user, 'manage_options');
    }

    public static function is_active(): bool {
        if (!self::can_preview()) { return false; }
        return self::target_user() instanceof WP_User;
    }

    public static function target_user(): ?WP_User {
        if (!self::can_preview()) { return null; }
        $owner_id = get_current_user_id();
        $target_id = absint(get_user_meta($owner_id, self::META_TARGET, true));
        if ($target_id <= 0 || $target_id === $owner_id) { return null; }
        $target = get_user_by('id', $target_id);
        return $target instanceof WP_User ? $target : null;
    }

    public static function effective_user(?WP_User $fallback = null): WP_User {
        $target = self::target_user();
        if ($target instanceof WP_User) { return $target; }
        if ($fallback instanceof WP_User) { return $fallback; }
        return wp_get_current_user();
    }


    public static function is_clean_request(): bool {
        return self::is_active() && isset($_GET['elev8_clean_preview']) && (string) $_GET['elev8_clean_preview'] === '1';
    }

    public static function filter_admin_bar($show): bool {
        return self::is_clean_request() ? false : (bool) $show;
    }

    public static function clean_url(string $url, bool $force = false): string {
        if ($url === '') { return $url; }
        if ($force || self::is_clean_request()) {
            return add_query_arg('elev8_clean_preview', '1', $url);
        }
        return $url;
    }

    public static function selected_role(): string {
        if (!self::can_preview()) { return ''; }
        return sanitize_key((string) get_user_meta(get_current_user_id(), self::META_ROLE, true));
    }

    /** @return array<string,string> */
    public static function roles(): array {
        return [
            'owner' => __('CEO / Owner', 'elev8-os'),
            'shop_manager' => __('Shop Manager', 'elev8-os'),
            'glass_manager' => __('Glass Manager', 'elev8-os'),
            'glassblower' => __('Glassblower', 'elev8-os'),
            'artist' => __('Artist', 'elev8-os'),
            'teacher' => __('Teacher', 'elev8-os'),
            'event_host' => __('Event Host / DJ', 'elev8-os'),
            'volunteer' => __('Volunteer', 'elev8-os'),
            'retail' => __('Retail Employee', 'elev8-os'),
        ];
    }

    /** @return array<int,WP_User> */
    public static function users_for_role(string $role_key): array {
        $role_key = sanitize_key($role_key);
        $users = get_users(['orderby' => 'display_name', 'order' => 'ASC']);
        $matched = [];
        foreach ($users as $user) {
            if ($user instanceof WP_User && self::matches_role($user, $role_key)) {
                $matched[] = $user;
            }
        }
        return $matched;
    }

    public static function role_key(WP_User $user): string {
        foreach (array_keys(self::roles()) as $key) {
            if (self::matches_role($user, $key)) { return $key; }
        }
        return 'artist';
    }

    public static function dashboard_url(WP_User $user): string {
        if (class_exists('Elev8_OS_Workspace_Resolver_Service')) {
            return self::clean_url(Elev8_OS_Workspace_Resolver_Service::destination($user));
        }
        return self::clean_url(home_url('/artist-dashboard/'));
    }

    public static function preview_page_url(array $args = []): string {
        return add_query_arg($args, admin_url('admin.php?page=elev8-role-preview'));
    }

    public static function stop_url(string $redirect = ''): string {
        $url = wp_nonce_url(admin_url('admin-post.php?action=elev8_os_stop_preview'), 'elev8_os_stop_preview');
        if ($redirect !== '') { $url = add_query_arg('redirect_to', rawurlencode($redirect), $url); }
        return $url;
    }

    /** @return array<int,array{label:string,url:string}> */
    public static function jump_links(WP_User $user): array {
        $links = [['label' => __('Dashboard', 'elev8-os'), 'url' => self::dashboard_url($user)]];
        if (class_exists('Elev8_OS_Action_Center_Module') && Elev8_OS_Access_Service::user_can('view_work', $user)) {
            $links[] = ['label' => __('My Actions', 'elev8-os'), 'url' => Elev8_OS_Action_Center_Module::url()];
        }
        if (class_exists('Elev8_OS_Conversations_Module') && Elev8_OS_Access_Service::user_can('view_conversations', $user)) {
            $links[] = ['label' => __('Conversations', 'elev8-os'), 'url' => Elev8_OS_Conversations_Module::url()];
        }
        if (Elev8_OS_Access_Service::user_can('view_glass_dashboard', $user)) {
            $links[] = ['label' => __('Glass Operations', 'elev8-os'), 'url' => (class_exists('Elev8_OS_Glass_Manager_Suite_Module') ? Elev8_OS_Glass_Manager_Suite_Module::url() : admin_url('admin.php?page=elev8-glass-operations'))];
            $links[] = ['label' => __('Glass Classes', 'elev8-os'), 'url' => class_exists('Elev8_OS_Portal_Page_Manager') ? Elev8_OS_Portal_Page_Manager::get_url('classes') : self::dashboard_url($user)];
            $links[] = ['label' => __('Production Board', 'elev8-os'), 'url' => (class_exists('Elev8_OS_Glass_Manager_Suite_Module') ? Elev8_OS_Glass_Manager_Suite_Module::url(['suite_tool'=>'operations','view'=>'board']) : admin_url('admin.php?page=elev8-glass-operations&view=board'))];
        }
        if (Elev8_OS_Access_Service::uses_event_host_home($user)) {
            $links[] = ['label' => __('Open Mic Check-In', 'elev8-os'), 'url' => home_url('/checkin/?type=open_mic')];
            $links[] = ['label' => __('Bingo Reservations', 'elev8-os'), 'url' => add_query_arg('elev8_event_host_view', 'reservations', self::dashboard_url($user))];
        }
        if (Elev8_OS_Access_Service::user_can('view_artist_dashboard', $user)) {
            if (class_exists('Elev8_OS_Portal_Page_Manager')) {
                $links[] = ['label' => __('My Artwork', 'elev8-os'), 'url' => Elev8_OS_Portal_Page_Manager::get_url('artwork')];
                $links[] = ['label' => __('My Classes', 'elev8-os'), 'url' => Elev8_OS_Portal_Page_Manager::get_url('classes')];
            }
        }
        if (class_exists('Elev8_OS_Public_Profile_Service')) {
            $links[] = ['label' => __('Public Profile Editor', 'elev8-os'), 'url' => Elev8_OS_Public_Profile_Service::editor_url((int) $user->ID)];
        }
        foreach ($links as &$link) { $link['url'] = self::clean_url($link['url']); }
        unset($link);
        return $links;
    }

    public static function start_preview(): void {
        if (!self::can_preview()) { wp_die(esc_html__('You do not have permission to preview roles.', 'elev8-os')); }
        check_admin_referer('elev8_os_start_preview');
        $target_id = absint($_POST['target_user_id'] ?? 0);
        $role = sanitize_key((string) ($_POST['preview_role'] ?? ''));
        $target = get_user_by('id', $target_id);
        if (!$target instanceof WP_User || !self::matches_role($target, $role)) {
            wp_safe_redirect(self::preview_page_url(['preview_role' => $role, 'elev8_preview_error' => 'invalid_user']));
            exit;
        }
        update_user_meta(get_current_user_id(), self::META_TARGET, $target_id);
        update_user_meta(get_current_user_id(), self::META_ROLE, $role);
        $destination = sanitize_key((string) ($_POST['preview_destination'] ?? 'dashboard'));
        $url = self::clean_url(self::dashboard_url($target), true);
        if ($destination === 'profile' && class_exists('Elev8_OS_Public_Profile_Service')) {
            $url = self::clean_url(Elev8_OS_Public_Profile_Service::editor_url($target_id), true);
        }
        wp_safe_redirect($url);
        exit;
    }

    public static function stop_preview(): void {
        if (!self::can_preview()) { wp_die(esc_html__('You do not have permission to exit preview mode.', 'elev8-os')); }
        check_admin_referer('elev8_os_stop_preview');
        delete_user_meta(get_current_user_id(), self::META_TARGET);
        delete_user_meta(get_current_user_id(), self::META_ROLE);
        $redirect = isset($_GET['redirect_to']) ? rawurldecode((string) wp_unslash($_GET['redirect_to'])) : admin_url('admin.php?page=elev8-role-preview');
        wp_safe_redirect(wp_validate_redirect($redirect, admin_url('admin.php?page=elev8-role-preview')));
        exit;
    }


    public static function protect_preview_writes(): void {
        if (!self::is_active() || strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') { return; }
        $action = sanitize_key((string) ($_REQUEST['action'] ?? ''));
        if (in_array($action, ['elev8_os_stop_preview', 'elev8_submit_problem', 'heartbeat'], true)) { return; }
        wp_die(esc_html__('Preview mode is read-only. Exit preview before changing business data.', 'elev8-os'), esc_html__('Preview Mode', 'elev8-os'), ['response' => 403, 'back_link' => true]);
    }

    public static function protect_preview_rest_writes($result, WP_REST_Server $server, WP_REST_Request $request) {
        if (!self::is_active() || in_array($request->get_method(), ['GET', 'HEAD', 'OPTIONS'], true)) { return $result; }
        return new WP_Error('elev8_preview_read_only', __('Preview mode is read-only. Exit preview before changing business data.', 'elev8-os'), ['status' => 403]);
    }

    public static function suppress_mail_during_preview($return, array $atts) {
        return self::is_active() ? true : $return;
    }

    public static function notifications_suppressed($suppressed): bool {
        return self::is_active() ? true : (bool) $suppressed;
    }

    private static function matches_role(WP_User $user, string $role_key): bool {
        if (!class_exists('Elev8_OS_Access_Service')) { return false; }
        if (user_can($user, 'manage_options') && $role_key !== 'owner') { return false; }
        switch ($role_key) {
            case 'owner': return Elev8_OS_Access_Service::user_can('view_ceo_dashboard', $user);
            case 'shop_manager': return Elev8_OS_Access_Service::user_can('view_manager_dashboard', $user) && !Elev8_OS_Access_Service::user_can('view_glass_dashboard', $user);
            case 'glass_manager': return Elev8_OS_Access_Service::user_can('view_glass_dashboard', $user);
            case 'glassblower': return Elev8_OS_Access_Service::user_can('glass_work', $user) && !Elev8_OS_Access_Service::user_can('view_glass_dashboard', $user);
            case 'teacher': return Elev8_OS_Access_Service::is_teacher($user);
            case 'event_host': return Elev8_OS_Access_Service::uses_event_host_home($user);
            case 'volunteer': return Elev8_OS_Access_Service::user_can('view_volunteer_portal', $user);
            case 'retail':
                $roles = array_map('sanitize_key', (array) $user->roles);
                $legacy_retail_roles = ['elev8_retail_employee', 'shop_employee', 'retail_employee', 'author', 'contributor'];
                return Elev8_OS_Access_Service::user_can('submit_retail_log', $user)
                    || Elev8_OS_Access_Service::user_can('manage_retail_operations', $user)
                    || (bool) array_intersect($legacy_retail_roles, $roles);
            case 'artist': return Elev8_OS_Access_Service::user_can('view_artist_dashboard', $user) && !Elev8_OS_Access_Service::is_teacher($user);
        }
        return false;
    }
}

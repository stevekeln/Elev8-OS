<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Single source of truth for Elev8 OS business access.
 *
 * WordPress owns authentication, users and roles. This service translates
 * those roles plus per-user overrides into reusable business capabilities.
 * Elev8 OS modules should ask this service for access instead of inspecting
 * role names or calling current_user_can() directly.
 */
final class Elev8_OS_Access_Service {
    public const ROLE_OWNER = 'elev8_owner';
    public const ROLE_TEACHER = 'elev8_teacher';
    public const ROLE_ARTIST = 'elev8_artist';
    public const ROLE_VOLUNTEER = 'elev8_volunteer';
    public const ROLE_EVENT_STAFF = 'elev8_event_staff';
    public const ROLE_DJ = 'elev8_open_mic_dj'; // Backward-compatible legacy role.
    public const ROLE_RETAIL = 'elev8_retail_employee';
    public const ROLE_GLASS_MANAGER = 'elev8_glass_manager';
    public const ROLE_GLASS_BLOWER = 'elev8_glass_blower';
    public const ROLE_SHIPPING = 'elev8_shipping';
    public const ROLE_CUSTOMER_SERVICE = 'elev8_customer_service';
    public const ROLE_OPERATIONS_MANAGER = 'elev8_operations_manager';
    public const ROLE_IT_SUPPORT = 'elev8_it_support';

    public const META_ALLOW = '_elev8_access_allow';
    public const META_DENY = '_elev8_access_deny';
    public const META_ACTIVE = '_elev8_os_active';
    public const META_DEPARTMENT = '_elev8_os_department';

    /** @var array<string,string> */
    private static array $permission_map = [
        'platform_admin' => 'manage_options',
        'view_ceo_dashboard' => 'elev8_view_ceo_dashboard',
        'view_manager_dashboard' => 'elev8_view_manager_dashboard',
        'submit_manager_log' => 'elev8_submit_manager_log',
        'submit_retail_log' => 'elev8_submit_retail_log',
        'submit_artist_log' => 'elev8_submit_artist_log',
        'submit_event_log' => 'elev8_submit_event_log',
        'submit_maintenance_log' => 'elev8_submit_maintenance_log',
        'submit_vendor_log' => 'elev8_submit_vendor_log',
        'manage_daily_operations' => 'elev8_manage_daily_operations',
        'manage_daily_operations_templates' => 'elev8_manage_daily_operations_templates',
        'manage_events' => 'elev8_manage_events',
        'manage_artists' => 'elev8_manage_artists',
        'view_artist_dashboard' => 'elev8_view_artist_dashboard',
        'view_artist_opportunities' => 'elev8_view_artist_opportunities',
        'view_artist_classes' => 'elev8_view_artist_classes',
        'view_artist_reports' => 'elev8_view_artist_reports',
        'manage_inventory' => 'elev8_manage_inventory',
        'manage_retail_operations' => 'elev8_manage_retail_operations',
        'view_glass_dashboard' => 'elev8_view_glass_dashboard',
        'manage_glass_orders' => 'elev8_manage_glass_orders',
        'manage_glass_production' => 'elev8_manage_glass_production',
        'manage_blower_payouts' => 'elev8_manage_blower_payouts',
        'glass_work' => 'elev8_glass_work',
        'view_volunteer_portal' => 'elev8_view_volunteer_portal',
        'manage_bingo' => 'elev8_manage_bingo', // Legacy alias.
        'manage_reservations' => 'elev8_manage_reservations',
        'manage_event_applications' => 'elev8_manage_event_applications',
        'view_assigned_reservations' => 'elev8_view_assigned_reservations',
        'manage_checkins' => 'elev8_manage_checkins',
        'view_reports' => 'elev8_view_reports',
        'manage_relationships' => 'elev8_manage_relationships',
        'manage_unified_intake' => 'elev8_manage_unified_intake',
        'receive_assignments' => 'elev8_receive_assignments',
        'view_business_memory' => 'elev8_view_business_memory',
        'manage_business_memory' => 'elev8_manage_business_memory',
        'view_work' => 'elev8_view_work',
        'manage_work' => 'elev8_manage_work',
        'receive_work' => 'elev8_receive_work',
        'view_conversations' => 'elev8_view_conversations',
        'manage_conversations' => 'elev8_manage_conversations',
        'view_organization' => 'elev8_view_organization',
        'manage_organization' => 'elev8_manage_organization',
        'view_operations' => 'elev8_view_operations',
        'manage_operations' => 'elev8_manage_operations',
        'view_shipping_workspace' => 'elev8_view_shipping_workspace',
        'scan_shipping_orders' => 'elev8_scan_shipping_orders',
        'view_customer_service_workspace' => 'elev8_view_customer_service_workspace',
        'search_customer_orders' => 'elev8_search_customer_orders',
        'view_operations_manager_workspace' => 'elev8_view_operations_manager_workspace',
        'view_it_support_workspace' => 'elev8_view_it_support_workspace',
    ];

    public static function init(): void {
        add_action('init', [__CLASS__, 'ensure_roles'], 5);
    }

    public static function activate(): void {
        self::ensure_roles();
    }

    public static function capability(string $permission): string {
        return self::$permission_map[$permission] ?? sanitize_key($permission);
    }

    public static function can_access(string $permission, ?WP_User $user = null): bool {
        return self::user_can($permission, $user);
    }

    public static function user_can(string $permission, ?WP_User $user = null): bool {
        $user = $user ?: (class_exists('Elev8_OS_Preview_Service') ? Elev8_OS_Preview_Service::effective_user() : wp_get_current_user());
        if (!$user instanceof WP_User || $user->ID <= 0) { return false; }

        $capability = self::capability($permission);
        $denied = array_map('sanitize_key', (array) get_user_meta($user->ID, self::META_DENY, true));
        if (in_array($permission, $denied, true) || in_array($capability, $denied, true)) { return false; }

        $allowed = array_map('sanitize_key', (array) get_user_meta($user->ID, self::META_ALLOW, true));
        if (in_array($permission, $allowed, true) || in_array($capability, $allowed, true)) { return true; }

        if ($permission !== 'platform_admin' && get_user_meta($user->ID, self::META_ACTIVE, true) === '0') { return false; }

        if (user_can($user, 'manage_options')) { return true; }
        if (user_can($user, $capability)) { return true; }

        // Artist identity remains a trusted Elev8 OS source while legacy user
        // accounts are migrated to explicit capabilities.
        if (in_array($permission, ['view_artist_dashboard','view_artist_opportunities','view_artist_classes','view_artist_reports','submit_artist_log'], true)
            && class_exists('Elev8_OS_Identity_Service')
            && Elev8_OS_Identity_Service::artist_for_user($user) !== null) {
            return true;
        }
        return false;
    }


    /**
     * Capability check constrained to an Organization Engine scope.
     * Global administrators/owners retain global access; other users must have
     * both the capability and an active assignment to the unit or its parent.
     */
    public static function user_can_in_scope(string $permission, int $organization_unit_id, ?WP_User $user = null): bool {
        $user = $user ?: (class_exists('Elev8_OS_Preview_Service') ? Elev8_OS_Preview_Service::effective_user() : wp_get_current_user());
        if (!$user instanceof WP_User || $user->ID < 1 || $organization_unit_id < 1) { return false; }
        if (!self::user_can($permission, $user)) { return false; }
        if (user_can($user, 'manage_options') || self::user_can('view_ceo_dashboard', $user)) { return true; }
        return class_exists('Elev8_OS_Organization_Service') && Elev8_OS_Organization_Service::user_in_scope((int) $user->ID, $organization_unit_id);
    }

    public static function can_edit_user(int $user_id): bool {
        return current_user_can('edit_user', $user_id);
    }

    public static function ensure_roles(): void {
        add_role(self::ROLE_OWNER, __('Elev8 Owner', 'elev8-os'), ['read' => true]);
        add_role(self::ROLE_TEACHER, __('Elev8 Teacher', 'elev8-os'), ['read' => true]);
        add_role(self::ROLE_ARTIST, __('Elev8 Artist', 'elev8-os'), ['read' => true]);
        add_role(self::ROLE_VOLUNTEER, __('Elev8 Volunteer', 'elev8-os'), ['read' => true]);
        add_role(self::ROLE_EVENT_STAFF, __('Elev8 Event Staff', 'elev8-os'), ['read' => true]);
        add_role(self::ROLE_DJ, __('Elev8 Open Mic DJ', 'elev8-os'), ['read' => true]);
        add_role(self::ROLE_RETAIL, __('Elev8 Retail Employee', 'elev8-os'), ['read' => true]);
        add_role(self::ROLE_GLASS_MANAGER, __('Elev8 Glass Manager', 'elev8-os'), ['read' => true]);
        add_role(self::ROLE_GLASS_BLOWER, __('Elev8 Glass Blower', 'elev8-os'), ['read' => true]);
        add_role(self::ROLE_SHIPPING, __('Elev8 Shipping & Fulfillment', 'elev8-os'), ['read' => true]);
        add_role(self::ROLE_CUSTOMER_SERVICE, __('Elev8 Customer Service', 'elev8-os'), ['read' => true]);
        add_role(self::ROLE_OPERATIONS_MANAGER, __('Elev8 Operations Manager', 'elev8-os'), ['read' => true]);
        add_role(self::ROLE_IT_SUPPORT, __('Elev8 IT Support', 'elev8-os'), ['read' => true]);

        $all = array_values(array_unique(array_filter(self::$permission_map, static fn(string $cap): bool => $cap !== 'manage_options')));
        self::grant_to_role('administrator', $all);
        self::grant_to_role(self::ROLE_OWNER, $all);

        $manager = [
            'elev8_view_manager_dashboard','elev8_submit_manager_log','elev8_manage_daily_operations',
            'elev8_manage_events','elev8_manage_retail_operations','elev8_manage_inventory',
            'elev8_manage_checkins','elev8_manage_relationships','elev8_manage_reservations','elev8_manage_event_applications','elev8_manage_work','elev8_view_work','elev8_receive_work','elev8_receive_assignments',
            'elev8_view_business_memory','elev8_view_reports','elev8_view_conversations','elev8_manage_conversations','elev8_view_organization','elev8_view_operations','elev8_manage_operations',
        ];
        self::grant_to_role('shop_manager', $manager);
        self::grant_to_role('editor', $manager); // Legacy manager compatibility.

        $operations_manager = [
            'elev8_view_operations_manager_workspace',
            'elev8_view_it_support_workspace',
            'elev8_view_manager_dashboard',
            'elev8_submit_manager_log',
            'elev8_manage_daily_operations',
            'elev8_manage_retail_operations',
            'elev8_manage_inventory',
            'elev8_view_shipping_workspace',
            'elev8_scan_shipping_orders',
            'elev8_view_customer_service_workspace',
            'elev8_search_customer_orders',
            'elev8_view_operations',
            'elev8_manage_operations',
            'elev8_view_work',
            'elev8_manage_work',
            'elev8_receive_work',
            'elev8_receive_assignments',
            'elev8_view_conversations',
            'elev8_manage_conversations',
            'elev8_view_business_memory',
            'elev8_view_reports',
        ];
        self::grant_to_role(self::ROLE_OPERATIONS_MANAGER, $operations_manager);

        self::grant_to_role(self::ROLE_IT_SUPPORT, ['elev8_view_it_support_workspace','elev8_view_work','elev8_receive_work','elev8_receive_assignments','elev8_view_conversations']);

        self::grant_to_role(self::ROLE_RETAIL, ['elev8_submit_retail_log','elev8_manage_retail_operations','elev8_view_operations','elev8_view_work','elev8_receive_work','elev8_receive_assignments','elev8_view_conversations']);
        self::grant_to_role('author', ['elev8_submit_retail_log','elev8_view_operations','elev8_view_work','elev8_receive_work','elev8_receive_assignments','elev8_view_conversations']);
        self::grant_to_role('contributor', ['elev8_submit_retail_log','elev8_view_operations','elev8_view_work','elev8_receive_work','elev8_receive_assignments','elev8_view_conversations']);

        $artist = ['elev8_view_operations','elev8_view_artist_dashboard','elev8_view_artist_opportunities','elev8_view_artist_classes','elev8_view_artist_reports','elev8_submit_artist_log','elev8_view_assigned_reservations','elev8_view_work','elev8_receive_work','elev8_receive_assignments','elev8_view_conversations'];
        self::grant_to_role(self::ROLE_ARTIST, $artist);
        self::grant_to_role(self::ROLE_TEACHER, $artist);

        self::grant_to_role(self::ROLE_EVENT_STAFF, ['elev8_view_operations','elev8_manage_events','elev8_manage_bingo','elev8_manage_reservations','elev8_manage_event_applications','elev8_view_assigned_reservations','elev8_submit_event_log','elev8_view_work','elev8_receive_work','elev8_receive_assignments','elev8_view_conversations']);
        self::grant_to_role(self::ROLE_DJ, ['elev8_view_operations','elev8_manage_events','elev8_view_assigned_reservations','elev8_submit_event_log','elev8_view_work','elev8_receive_work','elev8_receive_assignments','elev8_view_conversations']);
        self::grant_to_role(self::ROLE_VOLUNTEER, ['elev8_view_volunteer_portal','elev8_view_operations','elev8_view_work','elev8_receive_work','elev8_receive_assignments','elev8_view_conversations']);

        self::grant_to_role(self::ROLE_GLASS_MANAGER, ['elev8_view_operations','elev8_manage_operations','elev8_view_glass_dashboard','elev8_manage_glass_orders','elev8_manage_glass_production','elev8_manage_blower_payouts','elev8_glass_work','elev8_view_artist_classes','elev8_view_work','elev8_receive_work','elev8_receive_assignments','elev8_view_conversations','elev8_view_organization']);
        self::grant_to_role(self::ROLE_GLASS_BLOWER, ['elev8_view_operations','elev8_glass_work','elev8_view_work','elev8_receive_work','elev8_receive_assignments','elev8_view_conversations']);

        self::grant_to_role(self::ROLE_SHIPPING, ['elev8_view_shipping_workspace','elev8_scan_shipping_orders','elev8_view_operations','elev8_view_work','elev8_receive_work','elev8_receive_assignments','elev8_view_conversations']);
        self::grant_to_role(self::ROLE_CUSTOMER_SERVICE, ['elev8_view_customer_service_workspace','elev8_search_customer_orders','elev8_view_operations','elev8_view_work','elev8_receive_work','elev8_receive_assignments','elev8_view_conversations']);

        self::assign_foundation_glass_manager();
    }

    private static function grant_to_role(string $role_name, array $capabilities): void {
        $role = get_role($role_name);
        if (!$role) { return; }
        foreach ($capabilities as $capability) { $role->add_cap($capability); }
    }

    private static function assign_foundation_glass_manager(): void {
        $email = (string) apply_filters('elev8_os_foundation_glass_manager_email', 'glass@elev8premier.com');
        $user = $email ? get_user_by('email', $email) : false;
        if (!$user instanceof WP_User || self::user_can('view_glass_dashboard', $user)) { return; }
        $user->add_role(self::ROLE_GLASS_MANAGER);
    }

    public static function is_owner(WP_User $user): bool { return self::user_can('view_ceo_dashboard', $user); }
    public static function is_manager(WP_User $user): bool { return self::user_can('view_manager_dashboard', $user); }
    public static function is_retail_employee(WP_User $user): bool { return self::user_can('submit_retail_log', $user) && !self::is_manager($user); }
    public static function is_teacher(WP_User $user): bool { return self::has_role($user, self::ROLE_TEACHER); }
    public static function is_dj(WP_User $user): bool { return self::user_can('submit_event_log', $user); }

    /**
     * Whether this user should receive the event-host Operational Home.
     *
     * This is capability-driven so future event roles can reuse the same home
     * without dashboard code inspecting WordPress role names.
     */
    public static function uses_event_host_home(WP_User $user): bool {
        return self::user_can('submit_event_log', $user)
            && !self::is_owner($user)
            && !self::is_manager($user)
            && !self::is_artist($user);
    }
    public static function is_artist(WP_User $user): bool { return self::user_can('view_artist_dashboard', $user); }
    public static function can_distribute_flyers(WP_User $user): bool { return self::user_can('manage_relationships', $user) || self::user_can('manage_events', $user) || self::is_retail_employee($user); }

    public static function allowed_operations_templates(WP_User $user): array {
        $map = [
            'manager' => 'submit_manager_log',
            'retail' => 'submit_retail_log',
            'artist' => 'submit_artist_log',
            'maintenance' => 'submit_maintenance_log',
            'vendor' => 'submit_vendor_log',
            'event' => 'submit_event_log',
        ];
        $allowed = [];
        foreach ($map as $template => $permission) {
            if (self::user_can($permission, $user)) { $allowed[] = $template; }
        }
        if (self::user_can('manage_daily_operations', $user)) {
            foreach (['manager','retail','artist','maintenance','vendor','event'] as $template) { $allowed[] = $template; }
        }
        return array_values(array_unique($allowed));
    }

    public static function can_use_operations_template(string $template_key, ?WP_User $user = null): bool {
        $user = $user ?: (class_exists('Elev8_OS_Preview_Service') ? Elev8_OS_Preview_Service::effective_user() : wp_get_current_user());
        return in_array($template_key, self::allowed_operations_templates($user), true);
    }

    /** Active organization employment is also authoritative for assignment and communication eligibility. */
    public static function can_receive_assignment(?WP_User $user = null): bool {
        $user = $user instanceof WP_User ? $user : wp_get_current_user();
        if (!$user instanceof WP_User || !$user->exists()) { return false; }
        if (self::user_can('receive_assignments', $user)) { return true; }
        return class_exists('Elev8_OS_Organization_Service')
            && !empty(Elev8_OS_Organization_Service::assignments_for_user((int) $user->ID, true));
    }

    /** @return array<string,array<int,WP_User>> */
    public static function assignment_users_grouped(): array {
        $groups = ['Management'=>[], 'Event Staff'=>[], 'Teachers'=>[], 'Artists'=>[], 'Shop Employees'=>[], 'Glass Team'=>[]];
        foreach (get_users(['orderby'=>'display_name','order'=>'ASC']) as $user) {
            if (!self::can_receive_assignment($user)) { continue; }
            if (self::user_can('view_manager_dashboard', $user) || self::user_can('view_ceo_dashboard', $user)) { $groups['Management'][] = $user; }
            elseif (self::user_can('manage_events', $user) || self::user_can('manage_bingo', $user)) { $groups['Event Staff'][] = $user; }
            elseif (self::has_role($user, self::ROLE_TEACHER)) { $groups['Teachers'][] = $user; }
            elseif (self::user_can('view_artist_dashboard', $user)) { $groups['Artists'][] = $user; }
            elseif (self::user_can('view_glass_dashboard', $user) || self::user_can('glass_work', $user)) { $groups['Glass Team'][] = $user; }
            else { $groups['Shop Employees'][] = $user; }
        }
        return array_filter($groups);
    }

    private static function has_role(WP_User $user, string $role): bool {
        return in_array($role, (array) $user->roles, true);
    }
}

<?php
/**
 * Registry of role-aware workspace definitions.
 * This is presentation metadata only; engines remain authoritative.
 */
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Workspace_Definition_Service {
    private static $definitions = [];

    public static function init(): void {
        add_action('init', [__CLASS__, 'register_core_workspaces'], 25);
    }

    public static function register(string $id, array $definition): void {
        $id = sanitize_key($id);
        if ($id === '') { return; }
        $definition = wp_parse_args($definition, [
            'label' => $id,
            'description' => '',
            'shell' => 'business',
            'capability' => '',
            'roles' => [],
            'widgets' => [],
            'actions' => [],
            'navigation' => [],
            'priority' => 50,
        ]);
        $definition['id'] = $id;
        self::$definitions[$id] = $definition;
    }

    public static function all(): array {
        $definitions = apply_filters('elev8_os_workspace_definitions', self::$definitions);
        uasort($definitions, static function(array $a, array $b): int {
            return ((int) $a['priority']) <=> ((int) $b['priority']);
        });
        return $definitions;
    }

    public static function get(string $id): ?array {
        $all = self::all();
        $id = sanitize_key($id);
        return isset($all[$id]) ? $all[$id] : null;
    }

    public static function can_view(array $workspace, ?WP_User $user = null): bool {
        $user = $user ?: wp_get_current_user();
        if (!$user || !$user->exists()) { return false; }
        $capability = sanitize_key((string) ($workspace['capability'] ?? ''));
        if ($capability !== '') {
            if (class_exists('Elev8_OS_Access_Service') && Elev8_OS_Access_Service::user_can($capability, $user)) { return true; }
            if (user_can($user, $capability)) { return true; }
        }
        $roles = array_map('sanitize_key', (array) ($workspace['roles'] ?? []));
        return (bool) array_intersect($roles, array_map('sanitize_key', (array) $user->roles));
    }

    public static function accessible(?WP_User $user = null): array {
        $user = $user ?: wp_get_current_user();
        if (!$user instanceof WP_User || !$user->exists()) { return []; }
        return array_filter(self::all(), static function(array $workspace) use ($user): bool {
            return self::can_view($workspace, $user);
        });
    }

    public static function resolve(?WP_User $user = null): array {
        $user = $user ?: wp_get_current_user();
        if (!$user instanceof WP_User || !$user->exists()) {
            return self::get('business') ?: [
                'id' => 'business', 'label' => __('Business Workspace', 'elev8-os'), 'widgets' => ['workspace_welcome','quick_actions'], 'actions' => []
            ];
        }

        $requested = isset($_GET['workspace']) ? sanitize_key(wp_unslash((string) $_GET['workspace'])) : '';
        if ($requested !== '') {
            $workspace = self::get($requested);
            if ($workspace && self::can_view($workspace, $user)) {
                if (!class_exists('Elev8_OS_Preview_Service') || !Elev8_OS_Preview_Service::is_previewing()) {
                    update_user_meta($user->ID, '_elev8_os_default_workspace', $requested);
                }
                return $workspace;
            }
        }

        $default = sanitize_key((string) get_user_meta($user->ID, '_elev8_os_default_workspace', true));
        if ($default !== '') {
            $workspace = self::get($default);
            if ($workspace && self::can_view($workspace, $user)) { return $workspace; }
        }

        foreach (self::all() as $workspace) {
            if (self::can_view($workspace, $user)) { return $workspace; }
        }
        return self::get('business') ?: [
            'id' => 'business', 'label' => __('Business Workspace', 'elev8-os'), 'widgets' => ['workspace_welcome','quick_actions'], 'actions' => []
        ];
    }

    public static function register_core_workspaces(): void {
        $dashboard = class_exists('Elev8_OS_Clean_App_Module') ? Elev8_OS_Clean_App_Module::url('ceo') : admin_url('admin.php?page=elev8-ceo-dashboard');
        self::register('executive', [
            'label' => __('Executive Workspace', 'elev8-os'),
            'description' => __('A focused owner view for verified decisions, risk, opportunity, and follow-through.', 'elev8-os'),
            'shell' => 'executive',
            'roles' => ['administrator','ceo'],
            'widgets' => ['workspace_welcome','quick_actions'],
            'actions' => [
                ['label' => __('Open CEO Dashboard', 'elev8-os'), 'url' => $dashboard],
                ['label' => __('Review Problem Reports', 'elev8-os'), 'url' => admin_url('admin.php?page=elev8-problem-reports')],
            ],
            'priority' => 10,
        ]);
        self::register('studio', [
            'label' => __('Studio Workspace', 'elev8-os'),
            'description' => __('Production, quality, assignments, readiness, and accurate pay in one operational view.', 'elev8-os'),
            'shell' => 'studio',
            'roles' => ['glass_manager','studio_manager','glassblower'],
            'widgets' => ['studio_pulse','studio_today','quick_actions'],
            'actions' => [
                ['label' => __('Production Board', 'elev8-os'), 'url' => class_exists('Elev8_OS_Glass_Manager_Suite_Module') ? Elev8_OS_Glass_Manager_Suite_Module::url(['suite_tool'=>'operations','view'=>'board']) : home_url('/glass-manager/')],
                ['label' => __('New Production Job', 'elev8-os'), 'url' => class_exists('Elev8_OS_Glass_Manager_Suite_Module') ? Elev8_OS_Glass_Manager_Suite_Module::url(['suite_tool'=>'operations','view'=>'new-job']) : home_url('/glass-manager/')],
                ['label' => __('Repair Intake', 'elev8-os'), 'url' => class_exists('Elev8_OS_Glass_Manager_Suite_Module') ? Elev8_OS_Glass_Manager_Suite_Module::url(['suite_tool'=>'operations','view'=>'repair-intake']) : home_url('/glass-manager/')],
                ['label' => __('Memorial Intake', 'elev8-os'), 'url' => class_exists('Elev8_OS_Glass_Manager_Suite_Module') ? Elev8_OS_Glass_Manager_Suite_Module::url(['suite_tool'=>'operations','view'=>'memorial-intake']) : home_url('/glass-manager/')],
                ['label' => __('Fast Pay & Pay Sheets', 'elev8-os'), 'url' => class_exists('Elev8_OS_Glass_Manager_Suite_Module') ? Elev8_OS_Glass_Manager_Suite_Module::url(['suite_tool'=>'operations','view'=>'payouts']) : home_url('/glass-manager/')],
                ['label' => __('Production Products', 'elev8-os'), 'url' => class_exists('Elev8_OS_Glass_Manager_Suite_Module') ? Elev8_OS_Glass_Manager_Suite_Module::url(['suite_tool'=>'catalog']) : home_url('/glass-manager/')],
                ['label' => __('Materials', 'elev8-os'), 'url' => class_exists('Elev8_OS_Glass_Manager_Suite_Module') ? Elev8_OS_Glass_Manager_Suite_Module::url(['suite_tool'=>'catalog','view'=>'materials']) : home_url('/glass-manager/')],
                ['label' => __('Compensation Profiles', 'elev8-os'), 'url' => class_exists('Elev8_OS_Glass_Manager_Suite_Module') ? Elev8_OS_Glass_Manager_Suite_Module::url(['suite_tool'=>'catalog','view'=>'compensation']) : home_url('/glass-manager/')],
                ['label' => __('Class Approvals', 'elev8-os'), 'url' => class_exists('Elev8_OS_Glass_Manager_Suite_Module') ? Elev8_OS_Glass_Manager_Suite_Module::url(['suite_tool'=>'approvals']) : home_url('/glass-manager/')],
                ['label' => __('Glass Classes', 'elev8-os'), 'url' => class_exists('Elev8_OS_Portal_Page_Manager') ? Elev8_OS_Portal_Page_Manager::get_url('classes') : home_url('/')],
            ],
            'priority' => 20,
        ]);

        self::register('operations_manager', [
            'label' => __('Operations Manager Workspace', 'elev8-os'),
            'description' => __('Coordinate daily execution across retail, shipping, customer service, IT support, and assigned management work.', 'elev8-os'),
            'shell' => 'operations-manager',
            'capability' => 'view_operations_manager_workspace',
            'roles' => ['elev8_operations_manager'],
            'widgets' => ['workspace_welcome','operations_manager_overview','quick_actions'],
            'actions' => [
                ['label' => __('Open Manager Dashboard', 'elev8-os'), 'url' => admin_url('admin.php?page=elev8-manager-dashboard')],
                ['label' => __('Shipping & Fulfillment', 'elev8-os'), 'url' => add_query_arg('workspace', 'shipping', home_url('/elev8-workspace/'))],
                ['label' => __('Customer Service', 'elev8-os'), 'url' => add_query_arg('workspace', 'customer_service', home_url('/elev8-workspace/'))],
                ['label' => __('Conversations', 'elev8-os'), 'url' => home_url('/elev8-conversations/')],
            ],
            'priority' => 15,
        ]);

        self::register('it_support', [
            'label' => __('IT Support Workspace', 'elev8-os'),
            'description' => __('Support devices, user access, reported problems, and reliable day-to-day use of Elev8 OS.', 'elev8-os'),
            'shell' => 'it-support',
            'capability' => 'view_it_support_workspace',
            'roles' => ['elev8_it_support'],
            'widgets' => ['workspace_welcome','quick_actions'],
            'actions' => [
                ['label' => __('My Devices', 'elev8-os'), 'url' => class_exists('Elev8_OS_Device_Session_Module') ? Elev8_OS_Device_Session_Module::page_url() : home_url('/my-devices/')],
                ['label' => __('Report a Problem', 'elev8-os'), 'url' => class_exists('Elev8_OS_Problem_Report_Module') ? Elev8_OS_Problem_Report_Module::url() : home_url('/report-a-problem/')],
                ['label' => __('Conversations', 'elev8-os'), 'url' => home_url('/elev8-conversations/')],
            ],
            'priority' => 24,
        ]);

        self::register('shipping', [
            'label' => __('Shipping & Fulfillment Workspace', 'elev8-os'),
            'description' => __('Scan an order from the pick list, verify what belongs in the box, and keep fulfillment work connected to the order.', 'elev8-os'),
            'shell' => 'shipping',
            'capability' => 'view_shipping_workspace',
            'roles' => ['elev8_shipping'],
            'widgets' => ['workspace_welcome','shipping_order_capture','quick_actions'],
            'actions' => [
                ['label' => __('Scan or Enter an Order', 'elev8-os'), 'url' => class_exists('Elev8_OS_Order_Capture_Module') ? Elev8_OS_Order_Capture_Module::url('shipping') : home_url('/elev8-order-capture/')],
                ['label' => __('Messages', 'elev8-os'), 'url' => home_url('/elev8-conversations/')],
            ],
            'priority' => 25,
        ]);
        self::register('customer_service', [
            'label' => __('Customer Service Workspace', 'elev8-os'),
            'description' => __('Find a customer order, review the order context, and keep follow-up connected to conversations and assigned work.', 'elev8-os'),
            'shell' => 'customer-service',
            'capability' => 'view_customer_service_workspace',
            'roles' => ['elev8_customer_service'],
            'widgets' => ['workspace_welcome','customer_order_lookup','quick_actions'],
            'actions' => [
                ['label' => __('Find an Order', 'elev8-os'), 'url' => class_exists('Elev8_OS_Order_Capture_Module') ? Elev8_OS_Order_Capture_Module::url('customer-service') : home_url('/elev8-order-capture/')],
                ['label' => __('Open Conversations', 'elev8-os'), 'url' => home_url('/elev8-conversations/')],
            ],
            'priority' => 26,
        ]);
        self::register('shop_manager', [
            'label' => __('Shop Manager Workspace', 'elev8-os'),
            'description' => __('Lead the store, follow up on daily operations, support employees, and keep customer issues moving.', 'elev8-os'),
            'shell' => 'retail',
            'capability' => 'view_manager_dashboard',
            'roles' => ['elev8_shop_manager','shop_manager'],
            'widgets' => ['workspace_welcome','quick_actions'],
            'actions' => [
                ['label' => __('Open Manager Dashboard Tools', 'elev8-os'), 'url' => admin_url('admin.php?page=elev8-manager-dashboard')],
                ['label' => __('Manager Operations Log', 'elev8-os'), 'url' => admin_url('admin.php?page=elev8-daily-operations')],
                ['label' => __('Conversations', 'elev8-os'), 'url' => home_url('/elev8-conversations/')],
            ],
            'priority' => 28,
        ]);

        self::register('retail', [
            'label' => __('Retail Workspace', 'elev8-os'),
            'description' => __('A phone-first workspace for customer service, store operations, and daily execution.', 'elev8-os'),
            'shell' => 'retail',
            'capability' => 'manage_retail_operations',
            'roles' => ['shop_manager','shop_employee','retail_employee','author','contributor'],
            'widgets' => ['workspace_welcome','retail_shift','quick_actions'],
            'actions' => [
                ['label' => __('Start Retail Log', 'elev8-os'), 'url' => admin_url('admin.php?page=elev8-daily-operations')],
                ['label' => __('Conversations', 'elev8-os'), 'url' => home_url('/elev8-conversations/')],
            ],
            'priority' => 30,
        ]);
        self::register('artist', [
            'label' => __('Artist Workspace', 'elev8-os'),
            'description' => __('Classes, students, artwork, opportunities, and business growth.', 'elev8-os'),
            'shell' => 'artist',
            'roles' => ['artist','elev8_artist','teacher'],
            'widgets' => ['workspace_welcome','quick_actions'],
            'actions' => [['label' => __('Open Artist Workspace', 'elev8-os'), 'url' => class_exists('Elev8_OS_Route_Registry_Service') ? Elev8_OS_Route_Registry_Service::url('artist-workspace') : add_query_arg('workspace', 'artist', home_url('/elev8-workspace/'))]],
            'priority' => 40,
        ]);
        self::register('event', [
            'label' => __('Event Workspace', 'elev8-os'),
            'description' => __('Event execution, reservations, check-in, volunteers, and follow-up.', 'elev8-os'),
            'shell' => 'event',
            'roles' => ['volunteer','event_staff','event_host','dj'],
            'widgets' => ['workspace_welcome','quick_actions'],
            'actions' => [['label' => __('Open Today', 'elev8-os'), 'url' => home_url('/elev8-today/')]],
            'priority' => 50,
        ]);
        self::register('business', [
            'label' => __('Business Workspace', 'elev8-os'),
            'description' => __('Shared operating access based on the current user’s permissions.', 'elev8-os'),
            'shell' => 'business',
            'roles' => [],
            'widgets' => ['workspace_welcome','quick_actions'],
            'actions' => [['label' => __('Open My Dashboard', 'elev8-os'), 'url' => $dashboard]],
            'priority' => 999,
        ]);
    }
}

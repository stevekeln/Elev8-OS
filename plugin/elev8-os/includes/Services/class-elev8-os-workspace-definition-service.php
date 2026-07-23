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

    public static function resolve(?WP_User $user = null): array {
        $user = $user ?: wp_get_current_user();
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
            'widgets' => ['workspace_welcome','studio_pulse','quick_actions'],
            'actions' => [
                ['label' => __('Open Production', 'elev8-os'), 'url' => home_url('/elev8-production/')],
                ['label' => __('Open Readiness', 'elev8-os'), 'url' => home_url('/elev8-readiness/')],
            ],
            'priority' => 20,
        ]);
        self::register('retail', [
            'label' => __('Retail Workspace', 'elev8-os'),
            'description' => __('A phone-first workspace for customer service, store operations, and daily execution.', 'elev8-os'),
            'shell' => 'retail',
            'roles' => ['shop_manager','shop_employee','retail_employee','author','contributor'],
            'widgets' => ['workspace_welcome','quick_actions'],
            'actions' => [
                ['label' => __('Open My Dashboard', 'elev8-os'), 'url' => $dashboard],
            ],
            'priority' => 30,
        ]);
        self::register('artist', [
            'label' => __('Artist Workspace', 'elev8-os'),
            'description' => __('Classes, students, artwork, opportunities, and business growth.', 'elev8-os'),
            'shell' => 'artist',
            'roles' => ['artist','elev8_artist','teacher'],
            'widgets' => ['workspace_welcome','quick_actions'],
            'actions' => [['label' => __('Open Artist Portal', 'elev8-os'), 'url' => home_url('/artist-portal/')]],
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

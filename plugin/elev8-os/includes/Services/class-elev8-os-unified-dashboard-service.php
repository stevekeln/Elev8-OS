<?php
/**
 * Canonical application dashboard and legacy-surface migration registry.
 *
 * Elev8 OS has one dashboard route. Roles and permissions determine which
 * workspace definition and widgets appear inside the shared application shell.
 * Legacy pages remain available as tools until their capabilities are migrated.
 */
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Unified_Dashboard_Service {
    private const PAGE_SLUG = 'elev8-workspace';

    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'register_admin_page'], 96);
    }

    public static function url(string $workspace = ''): string {
        $url = class_exists('Elev8_OS_Workspace_Runtime_Module')
            ? Elev8_OS_Workspace_Runtime_Module::url()
            : home_url('/' . self::PAGE_SLUG . '/');
        return $workspace !== '' ? add_query_arg('workspace', sanitize_key($workspace), $url) : $url;
    }

    public static function migration_inventory(): array {
        $items = [
            ['id' => 'ceo-dashboard', 'label' => __('CEO Dashboard', 'elev8-os'), 'type' => 'dashboard', 'target' => 'executive', 'status' => 'bridge'],
            ['id' => 'glass-manager', 'label' => __('Glass Manager Home', 'elev8-os'), 'type' => 'dashboard', 'target' => 'studio', 'status' => 'partial'],
            ['id' => 'shop-manager', 'label' => __('Shop Manager Dashboard', 'elev8-os'), 'type' => 'dashboard', 'target' => 'shop_manager', 'status' => 'bridge'],
            ['id' => 'artist-dashboard', 'label' => __('Artist Dashboard', 'elev8-os'), 'type' => 'dashboard', 'target' => 'artist', 'status' => 'bridge'],
            ['id' => 'shipping', 'label' => __('Shipping & Fulfillment', 'elev8-os'), 'type' => 'workspace', 'target' => 'shipping', 'status' => 'native'],
            ['id' => 'customer-service', 'label' => __('Customer Service', 'elev8-os'), 'type' => 'workspace', 'target' => 'customer_service', 'status' => 'native'],
            ['id' => 'operations-manager', 'label' => __('Operations Manager', 'elev8-os'), 'type' => 'workspace', 'target' => 'operations_manager', 'status' => 'native'],
            ['id' => 'conversations', 'label' => __('Conversations', 'elev8-os'), 'type' => 'shared_action', 'target' => 'messages', 'status' => 'shared'],
            ['id' => 'actions', 'label' => __('Actions', 'elev8-os'), 'type' => 'shared_action', 'target' => 'actions', 'status' => 'shared'],
            ['id' => 'problem-reports', 'label' => __('Report a Problem', 'elev8-os'), 'type' => 'shared_action', 'target' => 'report', 'status' => 'shared'],
        ];
        return apply_filters('elev8_os_unified_dashboard_migration_inventory', $items);
    }

    public static function register_admin_page(): void {
        add_submenu_page(
            'elev8-os',
            __('Unified Dashboard Migration', 'elev8-os'),
            __('Dashboard Migration', 'elev8-os'),
            'manage_options',
            'elev8-unified-dashboard-migration',
            [__CLASS__, 'render_admin_page']
        );
    }

    public static function render_admin_page(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to view this page.', 'elev8-os'));
        }
        $labels = [
            'native' => __('Native', 'elev8-os'),
            'shared' => __('Shared service', 'elev8-os'),
            'partial' => __('Partially migrated', 'elev8-os'),
            'bridge' => __('Legacy bridge', 'elev8-os'),
        ];
        echo '<div class="wrap"><h1>' . esc_html__('Unified Dashboard Migration', 'elev8-os') . '</h1>';
        echo '<p>' . esc_html__('Elev8 OS has one application dashboard. This inventory tracks older pages while their capabilities move into role-aware widgets, actions, workflows, reports, or settings.', 'elev8-os') . '</p>';
        echo '<p><a class="button button-primary" href="' . esc_url(self::url()) . '">' . esc_html__('Open Unified Dashboard', 'elev8-os') . '</a></p>';
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Surface', 'elev8-os') . '</th><th>' . esc_html__('Classification', 'elev8-os') . '</th><th>' . esc_html__('Target', 'elev8-os') . '</th><th>' . esc_html__('Status', 'elev8-os') . '</th></tr></thead><tbody>';
        foreach (self::migration_inventory() as $item) {
            $status = (string) ($item['status'] ?? 'bridge');
            echo '<tr><td><strong>' . esc_html((string) ($item['label'] ?? '')) . '</strong></td><td>' . esc_html((string) ($item['type'] ?? '')) . '</td><td><code>' . esc_html((string) ($item['target'] ?? '')) . '</code></td><td>' . esc_html($labels[$status] ?? $status) . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }
}

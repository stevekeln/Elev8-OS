<?php
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Workspace_Platform_Module {
    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue']);
    }

    public static function menu(): void {
        add_submenu_page('elev8-os', __('Workspace Platform', 'elev8-os'), __('Workspace Platform', 'elev8-os'), 'manage_options', 'elev8-workspace-platform', [__CLASS__, 'render']);
    }

    public static function enqueue(string $hook): void {
        if (strpos($hook, 'elev8-workspace-platform') === false) { return; }
        wp_enqueue_style('elev8-os-workspace-platform', ELEV8_OS_URL . 'assets/css/workspace-platform.css', ['elev8-os-ui-components'], ELEV8_OS_VERSION);
    }

    public static function render(): void {
        if (!current_user_can('manage_options')) { return; }
        $workspace = Elev8_OS_Workspace_Definition_Service::resolve();
        $workspaces = Elev8_OS_Workspace_Definition_Service::all();
        $widgets = Elev8_OS_Widget_Registry_Service::all();
        ?>
        <div class="wrap elev8-workspace-platform-admin">
            <h1><?php esc_html_e('Elev8 Experience Platform', 'elev8-os'); ?></h1>
            <p><?php esc_html_e('21.0 separates workspaces, widgets, responsive layout, and theme presentation from business engines. Existing dashboards remain active while they migrate one at a time.', 'elev8-os'); ?></p>
            <div class="elev8-platform-summary">
                <div><strong><?php echo esc_html((string) count($workspaces)); ?></strong><span><?php esc_html_e('Workspace definitions', 'elev8-os'); ?></span></div>
                <div><strong><?php echo esc_html((string) count($widgets)); ?></strong><span><?php esc_html_e('Registered widgets', 'elev8-os'); ?></span></div>
                <div><strong><?php echo esc_html((string) ($workspace['label'] ?? '')); ?></strong><span><?php esc_html_e('Resolved for you', 'elev8-os'); ?></span></div>
            </div>
            <p><a class="button button-primary" href="<?php echo esc_url(Elev8_OS_Workspace_Runtime_Module::url()); ?>"><?php esc_html_e('Open My Live Workspace', 'elev8-os'); ?></a></p>
            <h2><?php esc_html_e('Live Foundation Preview', 'elev8-os'); ?></h2>
            <?php echo Elev8_OS_Responsive_Grid_Service::render($workspace); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <h2><?php esc_html_e('Registered Workspaces', 'elev8-os'); ?></h2>
            <div class="elev8-platform-table-wrap"><table class="widefat striped"><thead><tr><th><?php esc_html_e('Workspace', 'elev8-os'); ?></th><th><?php esc_html_e('Shell', 'elev8-os'); ?></th><th><?php esc_html_e('Widgets', 'elev8-os'); ?></th><th><?php esc_html_e('Roles / capability', 'elev8-os'); ?></th></tr></thead><tbody>
                <?php foreach ($workspaces as $item) : ?><tr><td><strong><?php echo esc_html((string) $item['label']); ?></strong><br><small><?php echo esc_html((string) $item['description']); ?></small></td><td><?php echo esc_html((string) $item['shell']); ?></td><td><?php echo esc_html(implode(', ', (array) $item['widgets'])); ?></td><td><?php echo esc_html(implode(', ', (array) $item['roles']) . ((string) $item['capability'] !== '' ? ' / ' . (string) $item['capability'] : '')); ?></td></tr><?php endforeach; ?>
            </tbody></table></div>
        </div>
        <?php
    }
}

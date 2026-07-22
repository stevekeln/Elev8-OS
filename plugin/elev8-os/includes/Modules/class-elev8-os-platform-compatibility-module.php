<?php
/**
 * Platform Compatibility and Plugin Migration Readiness workspace.
 *
 * @package Elev8OS
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Elev8_OS_Platform_Compatibility_Module {

    private const PAGE_SLUG = 'elev8-platform-compatibility';
    private const EXPORT_ACTION = 'elev8_os_export_plugin_usage';
    private const REFRESH_ACTION = 'elev8_os_refresh_plugin_usage';
    private const NONCE = 'elev8_os_plugin_usage';

    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'register_page'], 82);
        add_action('admin_post_' . self::EXPORT_ACTION, [__CLASS__, 'export_json']);
        add_action('admin_post_' . self::REFRESH_ACTION, [__CLASS__, 'refresh']);
    }

    public static function register_page(): void {
        add_submenu_page(
            'elev8-os',
            __('Platform Compatibility', 'elev8-os'),
            __('Compatibility', 'elev8-os'),
            'manage_options',
            self::PAGE_SLUG,
            [__CLASS__, 'render_page']
        );
    }

    public static function render_page(): void {
        self::authorize();
        $report = Elev8_OS_Plugin_Usage_Discovery_Service::get_report();
        $refresh = wp_nonce_url(admin_url('admin-post.php?action=' . self::REFRESH_ACTION), self::NONCE);
        $export = wp_nonce_url(admin_url('admin-post.php?action=' . self::EXPORT_ACTION), self::NONCE);
        $plugins = isset($report['plugins']) && is_array($report['plugins']) ? $report['plugins'] : [];
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Platform Compatibility & Migration Readiness', 'elev8-os'); ?></h1>
            <p><?php esc_html_e('Read-only discovery of plugin dependencies. This screen never activates, deactivates, updates, or deletes a plugin.', 'elev8-os'); ?></p>
            <p>
                <a class="button button-primary" href="<?php echo esc_url($refresh); ?>"><?php esc_html_e('Run fresh scan', 'elev8-os'); ?></a>
                <a class="button" href="<?php echo esc_url($export); ?>"><?php esc_html_e('Export audit JSON', 'elev8-os'); ?></a>
            </p>
            <div style="display:flex;gap:12px;flex-wrap:wrap;margin:18px 0;">
                <?php self::metric(__('Installed plugins', 'elev8-os'), (string) ($report['plugin_count'] ?? 0)); ?>
                <?php self::metric(__('Active plugins', 'elev8-os'), (string) ($report['active_count'] ?? 0)); ?>
                <?php self::metric(__('Content records scanned', 'elev8-os'), (string) ($report['inventory']['content_records_scanned'] ?? 0)); ?>
                <?php self::metric(__('Custom tables', 'elev8-os'), (string) count($report['inventory']['custom_tables'] ?? [])); ?>
                <?php self::metric(__('Cron hooks', 'elev8-os'), (string) count($report['inventory']['cron_hooks'] ?? [])); ?>
            </div>
            <div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px;margin-bottom:18px;">
                <strong><?php esc_html_e('Migration boundary', 'elev8-os'); ?></strong>
                <p><?php esc_html_e('A plugin is not safe to retire merely because this scan finds nothing. Local migration testing, data verification, public-page validation, external integration review, and rollback remain mandatory.', 'elev8-os'); ?></p>
            </div>
            <table class="widefat striped">
                <thead><tr>
                    <th><?php esc_html_e('Plugin', 'elev8-os'); ?></th>
                    <th><?php esc_html_e('Status', 'elev8-os'); ?></th>
                    <th><?php esc_html_e('Recommendation', 'elev8-os'); ?></th>
                    <th><?php esc_html_e('Readiness', 'elev8-os'); ?></th>
                    <th><?php esc_html_e('Evidence found', 'elev8-os'); ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ($plugins as $plugin) : ?>
                    <tr>
                        <td><strong><?php echo esc_html((string) $plugin['name']); ?></strong><br><small><?php echo esc_html((string) $plugin['file']); ?> · <?php echo esc_html((string) $plugin['version']); ?></small></td>
                        <td><?php echo !empty($plugin['active']) ? esc_html__('Active', 'elev8-os') : esc_html__('Inactive', 'elev8-os'); ?></td>
                        <td><strong><?php echo esc_html((string) $plugin['disposition']); ?></strong><br><small><?php echo esc_html((string) $plugin['reason']); ?></small></td>
                        <td><?php echo esc_html((string) $plugin['readiness']); ?><br><small><?php echo esc_html((string) $plugin['next_evidence']); ?></small></td>
                        <td><?php self::render_findings((array) $plugin['findings']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p><small><?php echo esc_html(sprintf(__('Generated at %s UTC. Results are cached for six hours unless a fresh scan is requested.', 'elev8-os'), (string) ($report['generated_at_utc'] ?? ''))); ?></small></p>
        </div>
        <?php
    }

    /** @param array<string,array<int,array<string,mixed>>> $findings */
    private static function render_findings(array $findings): void {
        $labels = [
            'shortcodes' => __('Shortcodes', 'elev8-os'),
            'content_blocks' => __('Blocks in content', 'elev8-os'),
            'custom_tables' => __('Database tables', 'elev8-os'),
            'cron_hooks' => __('Scheduled hooks', 'elev8-os'),
            'registered_blocks' => __('Registered blocks', 'elev8-os'),
            'custom_post_types' => __('Custom post types', 'elev8-os'),
        ];
        $shown = false;
        foreach ($labels as $key => $label) {
            $items = isset($findings[$key]) && is_array($findings[$key]) ? $findings[$key] : [];
            if (!$items) {
                continue;
            }
            $shown = true;
            echo '<details style="margin-bottom:5px"><summary>' . esc_html($label . ': ' . count($items)) . '</summary><ul style="margin:6px 0 0 18px">';
            foreach (array_slice($items, 0, 12) as $item) {
                $value = isset($item['value']) ? (string) $item['value'] : '';
                $title = isset($item['title']) && $item['title'] !== '' ? ' — ' . (string) $item['title'] : '';
                echo '<li><code>' . esc_html($value) . '</code>' . esc_html($title) . '</li>';
            }
            if (count($items) > 12) {
                echo '<li>' . esc_html(sprintf(__('and %d more in the JSON export', 'elev8-os'), count($items) - 12)) . '</li>';
            }
            echo '</ul></details>';
        }
        if (!$shown) {
            echo '<span aria-label="No direct evidence found">—</span>';
        }
    }

    private static function metric(string $label, string $value): void {
        echo '<div style="min-width:150px;background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:14px"><div style="font-size:24px;font-weight:700">' . esc_html($value) . '</div><div>' . esc_html($label) . '</div></div>';
    }

    public static function refresh(): void {
        self::authorize();
        check_admin_referer(self::NONCE);
        Elev8_OS_Plugin_Usage_Discovery_Service::clear_cache();
        Elev8_OS_Plugin_Usage_Discovery_Service::get_report(true);
        wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE_SLUG));
        exit;
    }

    public static function export_json(): void {
        self::authorize();
        check_admin_referer(self::NONCE);
        $report = Elev8_OS_Plugin_Usage_Discovery_Service::get_report(true);
        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="elev8-os-plugin-usage-' . gmdate('Y-m-d-His') . '.json"');
        echo wp_json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private static function authorize(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to view this page.', 'elev8-os'));
        }
    }
}

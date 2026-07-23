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
    private const SAVE_PLAN_ACTION = 'elev8_os_save_plugin_migration_plan';
    private const NONCE = 'elev8_os_plugin_usage';
    private const PLAN_NONCE = 'elev8_os_plugin_migration_plan';

    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'register_page'], 82);
        add_action('admin_post_' . self::EXPORT_ACTION, [__CLASS__, 'export_json']);
        add_action('admin_post_' . self::REFRESH_ACTION, [__CLASS__, 'refresh']);
        add_action('admin_post_' . self::SAVE_PLAN_ACTION, [__CLASS__, 'save_plan']);
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
        $view = isset($_GET['view']) ? sanitize_key((string) wp_unslash($_GET['view'])) : 'discovery';
        $view = in_array($view, ['discovery', 'plans', 'edit'], true) ? $view : 'discovery';
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Platform Compatibility & Migration Readiness', 'elev8-os'); ?></h1>
            <nav class="nav-tab-wrapper" style="margin-bottom:18px;">
                <a class="nav-tab <?php echo $view === 'discovery' ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url(self::page_url('discovery')); ?>"><?php esc_html_e('Dependency Discovery', 'elev8-os'); ?></a>
                <a class="nav-tab <?php echo in_array($view, ['plans', 'edit'], true) ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url(self::page_url('plans')); ?>"><?php esc_html_e('Migration Plans', 'elev8-os'); ?></a>
            </nav>
            <?php self::render_notice(); ?>
            <?php
            if ($view === 'edit') {
                self::render_plan_editor();
            } elseif ($view === 'plans') {
                self::render_plans();
            } else {
                self::render_discovery();
            }
            ?>
        </div>
        <?php
    }

    private static function render_discovery(): void {
        $report = Elev8_OS_Plugin_Usage_Discovery_Service::get_report();
        $refresh = wp_nonce_url(admin_url('admin-post.php?action=' . self::REFRESH_ACTION), self::NONCE);
        $export = wp_nonce_url(admin_url('admin-post.php?action=' . self::EXPORT_ACTION), self::NONCE);
        $plugins = isset($report['plugins']) && is_array($report['plugins']) ? $report['plugins'] : [];
        ?>
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
            <p><?php esc_html_e('A plugin is not safe to retire merely because this scan finds nothing. Create an administrator-confirmed migration plan before any Local rehearsal or retirement decision.', 'elev8-os'); ?></p>
        </div>
        <table class="widefat striped">
            <thead><tr>
                <th><?php esc_html_e('Plugin', 'elev8-os'); ?></th>
                <th><?php esc_html_e('Status', 'elev8-os'); ?></th>
                <th><?php esc_html_e('Recommendation', 'elev8-os'); ?></th>
                <th><?php esc_html_e('Readiness', 'elev8-os'); ?></th>
                <th><?php esc_html_e('Evidence found', 'elev8-os'); ?></th>
                <th><?php esc_html_e('Migration plan', 'elev8-os'); ?></th>
            </tr></thead>
            <tbody>
            <?php foreach ($plugins as $plugin) :
                $plan = Elev8_OS_Plugin_Migration_Plan_Service::get_by_plugin((string) $plugin['file']);
                ?>
                <tr>
                    <td><strong><?php echo esc_html((string) $plugin['name']); ?></strong><br><small><?php echo esc_html((string) $plugin['file']); ?> · <?php echo esc_html((string) $plugin['version']); ?></small></td>
                    <td><?php echo !empty($plugin['active']) ? esc_html__('Active', 'elev8-os') : esc_html__('Inactive', 'elev8-os'); ?></td>
                    <td><strong><?php echo esc_html((string) $plugin['disposition']); ?></strong><br><small><?php echo esc_html((string) $plugin['reason']); ?></small></td>
                    <td><?php echo esc_html((string) $plugin['readiness']); ?><br><small><?php echo esc_html((string) $plugin['next_evidence']); ?></small></td>
                    <td><?php self::render_findings((array) $plugin['findings']); ?></td>
                    <td>
                        <?php if ($plan) : ?>
                            <strong><?php echo esc_html(self::stage_label((string) $plan['stage'])); ?></strong><br>
                            <a href="<?php echo esc_url(self::edit_url((string) $plugin['file'])); ?>"><?php esc_html_e('Review plan', 'elev8-os'); ?></a>
                        <?php else : ?>
                            <a class="button button-small" href="<?php echo esc_url(self::edit_url((string) $plugin['file'])); ?>"><?php esc_html_e('Create plan', 'elev8-os'); ?></a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p><small><?php echo esc_html(sprintf(__('Generated at %s UTC. Results are cached for six hours unless a fresh scan is requested.', 'elev8-os'), (string) ($report['generated_at_utc'] ?? ''))); ?></small></p>
        <?php
    }

    private static function render_plans(): void {
        $plans = Elev8_OS_Plugin_Migration_Plan_Service::all();
        ?>
        <p><?php esc_html_e('Administrator-confirmed plans document what each plugin owns, the intended replacement boundary, migration evidence, blockers, Local rehearsal results, and rollback instructions.', 'elev8-os'); ?></p>
        <?php if (!$plans) : ?>
            <div class="notice notice-info inline"><p><?php esc_html_e('No migration plans have been created. Start from Dependency Discovery and choose Create plan beside a plugin.', 'elev8-os'); ?></p></div>
        <?php else : ?>
            <table class="widefat striped">
                <thead><tr>
                    <th><?php esc_html_e('Plugin', 'elev8-os'); ?></th>
                    <th><?php esc_html_e('Ownership', 'elev8-os'); ?></th>
                    <th><?php esc_html_e('Replacement Engine', 'elev8-os'); ?></th>
                    <th><?php esc_html_e('Stage', 'elev8-os'); ?></th>
                    <th><?php esc_html_e('Plan completeness', 'elev8-os'); ?></th>
                    <th><?php esc_html_e('Updated', 'elev8-os'); ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ($plans as $plan) : $readiness = Elev8_OS_Plugin_Migration_Plan_Service::readiness($plan); ?>
                    <tr>
                        <td><strong><a href="<?php echo esc_url(self::edit_url((string) $plan['plugin_file'])); ?>"><?php echo esc_html((string) $plan['plugin_name']); ?></a></strong><br><small><?php echo esc_html((string) $plan['plugin_file']); ?></small></td>
                        <td><?php echo esc_html(self::ownership_label((string) $plan['ownership_status'])); ?></td>
                        <td><?php echo esc_html(self::engine_label((string) $plan['replacement_engine'])); ?></td>
                        <td><?php echo esc_html(self::stage_label((string) $plan['stage'])); ?></td>
                        <td><strong><?php echo esc_html((string) $readiness['percent'] . '%'); ?></strong><?php if (!$readiness['complete']) : ?><br><small><?php echo esc_html(sprintf(__('%d required areas incomplete', 'elev8-os'), count($readiness['missing']))); ?></small><?php endif; ?></td>
                        <td><?php echo esc_html((string) $plan['updated_at_utc']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif;
    }

    private static function render_plan_editor(): void {
        $plugin_file = isset($_GET['plugin']) ? sanitize_text_field((string) wp_unslash($_GET['plugin'])) : '';
        $report = Elev8_OS_Plugin_Usage_Discovery_Service::get_report();
        $plugin = null;
        foreach ((array) ($report['plugins'] ?? []) as $candidate) {
            if ((string) ($candidate['file'] ?? '') === $plugin_file) {
                $plugin = $candidate;
                break;
            }
        }
        if (!$plugin) {
            echo '<div class="notice notice-error inline"><p>' . esc_html__('The selected plugin was not found in the current installed-plugin inventory.', 'elev8-os') . '</p></div>';
            return;
        }

        $plan = Elev8_OS_Plugin_Migration_Plan_Service::get_by_plugin($plugin_file) ?: [
            'plugin_file' => $plugin_file,
            'plugin_name' => (string) $plugin['name'],
            'ownership_status' => 'unconfirmed',
            'stage' => 'discovery',
            'current_owner' => '',
            'replacement_engine' => '',
            'capabilities_owned' => '',
            'authoritative_data' => '',
            'data_migration' => '',
            'pages_workflows' => '',
            'external_dependencies' => '',
            'retirement_blockers' => '',
            'local_rehearsal' => '',
            'rollback_plan' => '',
            'validation_results' => '',
            'final_approval_notes' => '',
        ];
        $readiness = Elev8_OS_Plugin_Migration_Plan_Service::readiness($plan);
        ?>
        <p><a href="<?php echo esc_url(self::page_url('plans')); ?>">&larr; <?php esc_html_e('Back to migration plans', 'elev8-os'); ?></a></p>
        <h2><?php echo esc_html((string) $plugin['name']); ?></h2>
        <p><code><?php echo esc_html($plugin_file); ?></code> · <?php echo !empty($plugin['active']) ? esc_html__('Active', 'elev8-os') : esc_html__('Inactive', 'elev8-os'); ?></p>
        <div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px;margin:16px 0;">
            <strong><?php echo esc_html(sprintf(__('Plan completeness: %d%%', 'elev8-os'), $readiness['percent'])); ?></strong>
            <?php if (!$readiness['complete']) : ?><p><?php echo esc_html(__('Still required: ', 'elev8-os') . implode(', ', $readiness['missing'])); ?></p><?php else : ?><p><?php esc_html_e('The required planning areas are documented. Final approval still requires successful Local validation and an intentional human decision.', 'elev8-os'); ?></p><?php endif; ?>
        </div>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="<?php echo esc_attr(self::SAVE_PLAN_ACTION); ?>">
            <input type="hidden" name="plugin_file" value="<?php echo esc_attr($plugin_file); ?>">
            <input type="hidden" name="plugin_name" value="<?php echo esc_attr((string) $plugin['name']); ?>">
            <?php wp_nonce_field(self::PLAN_NONCE); ?>
            <table class="form-table" role="presentation">
                <tr><th><label for="ownership_status"><?php esc_html_e('Confirmed ownership', 'elev8-os'); ?></label></th><td><?php self::select('ownership_status', Elev8_OS_Plugin_Migration_Plan_Service::ownership_statuses(), (string) $plan['ownership_status']); ?><p class="description"><?php esc_html_e('Document whether this plugin remains authoritative, shares a boundary, or is planned for replacement.', 'elev8-os'); ?></p></td></tr>
                <tr><th><label for="current_owner"><?php esc_html_e('Current capability owner', 'elev8-os'); ?></label></th><td><?php self::textarea('current_owner', $plan, 'Example: Amelia owns schedules, providers, bookings, and appointment status.'); ?></td></tr>
                <tr><th><label for="replacement_engine"><?php esc_html_e('Replacement Elev8 OS Engine', 'elev8-os'); ?></label></th><td><?php self::select('replacement_engine', Elev8_OS_Plugin_Migration_Plan_Service::engines(), (string) $plan['replacement_engine']); ?></td></tr>
                <tr><th><label for="capabilities_owned"><?php esc_html_e('Capabilities currently owned', 'elev8-os'); ?></label></th><td><?php self::textarea('capabilities_owned', $plan, 'List the screens, workflows, APIs, scheduled jobs, or services the plugin provides.'); ?></td></tr>
                <tr><th><label for="authoritative_data"><?php esc_html_e('Authoritative data and storage', 'elev8-os'); ?></label></th><td><?php self::textarea('authoritative_data', $plan, 'Document custom tables, post types, user meta, options, remote systems, and ownership boundaries.'); ?></td></tr>
                <tr><th><label for="data_migration"><?php esc_html_e('Required data migration', 'elev8-os'); ?></label></th><td><?php self::textarea('data_migration', $plan, 'Describe mappings, import order, validation totals, and what must never be duplicated.'); ?></td></tr>
                <tr><th><label for="pages_workflows"><?php esc_html_e('Pages and workflows to test', 'elev8-os'); ?></label></th><td><?php self::textarea('pages_workflows', $plan, 'List public pages, admin screens, shortcodes, forms, emails, cron jobs, and role workflows.'); ?></td></tr>
                <tr><th><label for="external_dependencies"><?php esc_html_e('External dependencies', 'elev8-os'); ?></label></th><td><?php self::textarea('external_dependencies', $plan, 'Document webhooks, Make scenarios, licenses, DNS, APIs, payment providers, or outside accounts.'); ?></td></tr>
                <tr><th><label for="retirement_blockers"><?php esc_html_e('Retirement blockers', 'elev8-os'); ?></label></th><td><?php self::textarea('retirement_blockers', $plan, 'State what must be solved before Local rehearsal or retirement. Use “None identified” only after review.'); ?></td></tr>
                <tr><th><label for="local_rehearsal"><?php esc_html_e('Local rehearsal record', 'elev8-os'); ?></label></th><td><?php self::textarea('local_rehearsal', $plan, 'Record date, build, tester, steps performed, and whether the plugin remained active or was temporarily disabled in Local.'); ?></td></tr>
                <tr><th><label for="validation_results"><?php esc_html_e('Validation results', 'elev8-os'); ?></label></th><td><?php self::textarea('validation_results', $plan, 'Record passed and failed checks, data counts, public-page results, and unresolved defects.'); ?></td></tr>
                <tr><th><label for="rollback_plan"><?php esc_html_e('Rollback instructions', 'elev8-os'); ?></label></th><td><?php self::textarea('rollback_plan', $plan, 'Document backup, reactivation, data restoration, cache clearing, and verification steps.'); ?></td></tr>
                <tr><th><label for="stage"><?php esc_html_e('Migration stage', 'elev8-os'); ?></label></th><td><?php self::select('stage', Elev8_OS_Plugin_Migration_Plan_Service::stages(), (string) $plan['stage']); ?></td></tr>
                <tr><th><label for="final_approval_notes"><?php esc_html_e('Final approval notes', 'elev8-os'); ?></label></th><td><?php self::textarea('final_approval_notes', $plan, 'Name the approver, approval boundary, date, and exact retirement authorization.'); ?></td></tr>
            </table>
            <?php submit_button(__('Save migration plan', 'elev8-os')); ?>
        </form>
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

    /** @param array<string,string> $options */
    private static function select(string $name, array $options, string $selected): void {
        echo '<select name="' . esc_attr($name) . '" id="' . esc_attr($name) . '">';
        foreach ($options as $value => $label) {
            echo '<option value="' . esc_attr($value) . '" ' . selected($selected, $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
    }

    /** @param array<string,mixed> $plan */
    private static function textarea(string $name, array $plan, string $placeholder): void {
        $value = isset($plan[$name]) ? (string) $plan[$name] : '';
        echo '<textarea class="large-text" rows="4" name="' . esc_attr($name) . '" id="' . esc_attr($name) . '" placeholder="' . esc_attr($placeholder) . '">' . esc_textarea($value) . '</textarea>';
    }

    public static function save_plan(): void {
        self::authorize();
        check_admin_referer(self::PLAN_NONCE);
        $data = wp_unslash($_POST);
        $result = Elev8_OS_Plugin_Migration_Plan_Service::save(is_array($data) ? $data : []);
        $plugin_file = isset($data['plugin_file']) ? sanitize_text_field((string) $data['plugin_file']) : '';
        $status = is_wp_error($result) ? 'error' : 'saved';
        wp_safe_redirect(add_query_arg(['page' => self::PAGE_SLUG, 'view' => 'edit', 'plugin' => $plugin_file, 'elev8_notice' => $status], admin_url('admin.php')));
        exit;
    }

    public static function refresh(): void {
        self::authorize();
        check_admin_referer(self::NONCE);
        Elev8_OS_Plugin_Usage_Discovery_Service::clear_cache();
        Elev8_OS_Plugin_Usage_Discovery_Service::get_report(true);
        wp_safe_redirect(self::page_url('discovery'));
        exit;
    }

    public static function export_json(): void {
        self::authorize();
        check_admin_referer(self::NONCE);
        $report = Elev8_OS_Plugin_Usage_Discovery_Service::get_report(true);
        $report['administrator_confirmed_migration_plans'] = Elev8_OS_Plugin_Migration_Plan_Service::all();
        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="elev8-os-plugin-usage-' . gmdate('Y-m-d-His') . '.json"');
        echo wp_json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private static function render_notice(): void {
        $notice = isset($_GET['elev8_notice']) ? sanitize_key((string) wp_unslash($_GET['elev8_notice'])) : '';
        if ($notice === 'saved') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Migration plan saved.', 'elev8-os') . '</p></div>';
        } elseif ($notice === 'error') {
            echo '<div class="notice notice-error"><p>' . esc_html__('The migration plan could not be saved.', 'elev8-os') . '</p></div>';
        }
    }

    private static function page_url(string $view): string {
        return add_query_arg(['page' => self::PAGE_SLUG, 'view' => $view], admin_url('admin.php'));
    }

    private static function edit_url(string $plugin_file): string {
        return add_query_arg(['page' => self::PAGE_SLUG, 'view' => 'edit', 'plugin' => $plugin_file], admin_url('admin.php'));
    }

    private static function stage_label(string $value): string {
        $all = Elev8_OS_Plugin_Migration_Plan_Service::stages();
        return $all[$value] ?? $value;
    }

    private static function ownership_label(string $value): string {
        $all = Elev8_OS_Plugin_Migration_Plan_Service::ownership_statuses();
        return $all[$value] ?? $value;
    }

    private static function engine_label(string $value): string {
        $all = Elev8_OS_Plugin_Migration_Plan_Service::engines();
        return $all[$value] ?? $value;
    }

    private static function authorize(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to view this page.', 'elev8-os'));
        }
    }
}

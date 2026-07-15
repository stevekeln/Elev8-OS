<?php
/**
 * Elev8 OS System Inspector.
 *
 * Read-only diagnostics and Amelia schema discovery for administrators.
 *
 * @package Elev8OS
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Elev8_OS_System_Inspector_Module {

    private const PAGE_SLUG = 'elev8-system-status';
    private const EXPORT_ACTION = 'elev8_os_export_system_inspector';
    private const EXPORT_NONCE_ACTION = 'elev8_os_export_system_inspector';

    /**
     * Register module hooks.
     */
    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'register_admin_page'], 40);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('admin_post_' . self::EXPORT_ACTION, [__CLASS__, 'export_json']);
    }

    /**
     * Register the existing System Status submenu.
     */
    public static function register_admin_page(): void {
        add_submenu_page(
            'elev8-os',
            __('Elev8 OS System Status', 'elev8-os'),
            __('System Status', 'elev8-os'),
            'manage_options',
            self::PAGE_SLUG,
            [__CLASS__, 'render_page']
        );
    }

    /**
     * Load inspector styles only on this screen.
     */
    public static function enqueue_assets(string $hook_suffix): void {
        if ($hook_suffix !== 'elev8-os_page_' . self::PAGE_SLUG) {
            return;
        }

        wp_enqueue_style(
            'elev8-os-system-inspector',
            ELEV8_OS_URL . 'assets/css/system-inspector.css',
            [],
            ELEV8_OS_VERSION
        );
    }

    /**
     * Render the diagnostic screen.
     */
    public static function render_page(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to view this page.', 'elev8-os'));
        }

        $report = self::build_report();
        $export_url = wp_nonce_url(
            admin_url('admin-post.php?action=' . self::EXPORT_ACTION),
            self::EXPORT_NONCE_ACTION
        );
        ?>
        <div class="wrap elev8-system-inspector">
            <h1><?php esc_html_e('Elev8 OS System Status', 'elev8-os'); ?></h1>
            <p class="elev8-system-meta">
                <?php
                echo esc_html(
                    sprintf(
                        __('Version: %1$s  Architecture: %2$s', 'elev8-os'),
                        defined('ELEV8_OS_VERSION') ? ELEV8_OS_VERSION : __('Unknown', 'elev8-os'),
                        __('Founders Foundation', 'elev8-os')
                    )
                );
                ?>
            </p>

            <?php self::render_modules(); ?>

            <section class="elev8-inspector-card">
                <div class="elev8-inspector-card__header">
                    <div>
                        <h2><?php esc_html_e('Amelia Database Discovery', 'elev8-os'); ?></h2>
                        <p><?php esc_html_e('Read-only inspection of the Amelia installation and database schema. No records are modified.', 'elev8-os'); ?></p>
                    </div>
                    <div class="elev8-inspector-actions">
                        <a class="button" href="<?php echo esc_url(self::page_url()); ?>">
                            <?php esc_html_e('Refresh Scan', 'elev8-os'); ?>
                        </a>
                        <a class="button button-primary" href="<?php echo esc_url($export_url); ?>">
                            <?php esc_html_e('Export JSON', 'elev8-os'); ?>
                        </a>
                    </div>
                </div>

                <?php self::render_summary($report); ?>
                <?php self::render_tables($report); ?>
                <?php self::render_relationships($report); ?>
            </section>
        </div>
        <?php
    }

    /**
     * Render existing module status information.
     */
    private static function render_modules(): void {
        $modules = [
            __('Core bootstrap', 'elev8-os')              => __('Ready', 'elev8-os'),
            __('Artist portal', 'elev8-os')               => __('Ready', 'elev8-os'),
            __('Partnerships and payouts', 'elev8-os')    => __('Ready', 'elev8-os'),
            __('Public artist profiles', 'elev8-os')      => __('Ready', 'elev8-os'),
            __('Referral tracking', 'elev8-os')           => __('Ready', 'elev8-os'),
            __('Development center', 'elev8-os')          => __('Ready', 'elev8-os'),
            __('Amelia integration', 'elev8-os')          => __('Ready', 'elev8-os'),
            __('WooCommerce integration', 'elev8-os')     => __('Ready', 'elev8-os'),
            __('Native waitlist', 'elev8-os')             => __('Planned', 'elev8-os'),
            __('CRM', 'elev8-os')                         => __('Planned', 'elev8-os'),
            __('CEO dashboard', 'elev8-os')               => __('Planned', 'elev8-os'),
        ];
        ?>
        <section class="elev8-inspector-card">
            <h2><?php esc_html_e('Modules', 'elev8-os'); ?></h2>
            <div class="elev8-table-wrap">
                <table class="widefat striped">
                    <thead>
                    <tr>
                        <th><?php esc_html_e('Module', 'elev8-os'); ?></th>
                        <th><?php esc_html_e('Status', 'elev8-os'); ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($modules as $module => $status) : ?>
                        <tr>
                            <td><?php echo esc_html($module); ?></td>
                            <td>
                                <span class="elev8-status elev8-status--<?php echo esc_attr(strtolower($status)); ?>">
                                    <?php echo esc_html($status); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php
    }

    /**
     * Build a complete, read-only discovery report.
     *
     * @return array<string,mixed>
     */
    private static function build_report(): array {
        global $wpdb;

        $tables = self::discover_amelia_tables();
        $table_reports = [];

        foreach ($tables as $table_name) {
            $columns = self::get_table_columns($table_name);
            $indexes = self::get_table_indexes($table_name);

            $table_reports[] = [
                'name'          => $table_name,
                'short_name'    => self::short_table_name($table_name),
                'record_count'  => self::get_record_count($table_name),
                'columns'       => $columns,
                'indexes'       => $indexes,
                'primary_keys'  => self::extract_primary_keys($indexes),
            ];
        }

        return [
            'generated_at_utc' => gmdate('c'),
            'site_url'         => site_url(),
            'wordpress_version'=> get_bloginfo('version'),
            'php_version'      => PHP_VERSION,
            'database_name'    => (string) $wpdb->dbname,
            'table_prefix'     => (string) $wpdb->prefix,
            'amelia'           => self::detect_amelia_plugin(),
            'tables'           => $table_reports,
            'relationships'    => self::infer_relationships($table_reports),
        ];
    }

    /**
     * Detect active or installed Amelia plugin metadata without assuming a path.
     *
     * @return array<string,mixed>
     */
    private static function detect_amelia_plugin(): array {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugins = get_plugins();
        $active_plugins = (array) get_option('active_plugins', []);
        $network_active = is_multisite() ? (array) get_site_option('active_sitewide_plugins', []) : [];

        foreach ($plugins as $plugin_file => $data) {
            $name = isset($data['Name']) ? (string) $data['Name'] : '';
            $text_domain = isset($data['TextDomain']) ? (string) $data['TextDomain'] : '';
            $haystack = strtolower($plugin_file . ' ' . $name . ' ' . $text_domain);

            if (strpos($haystack, 'amelia') === false) {
                continue;
            }

            return [
                'detected'    => true,
                'name'        => $name,
                'version'     => isset($data['Version']) ? (string) $data['Version'] : '',
                'plugin_file' => $plugin_file,
                'active'      => in_array($plugin_file, $active_plugins, true) || isset($network_active[$plugin_file]),
            ];
        }

        return [
            'detected'    => false,
            'name'        => '',
            'version'     => '',
            'plugin_file' => '',
            'active'      => false,
        ];
    }

    /**
     * Discover tables by database metadata, not hard-coded Amelia table names.
     *
     * @return string[]
     */
    private static function discover_amelia_tables(): array {
        global $wpdb;

        $database_name = (string) $wpdb->dbname;
        $pattern = '%' . $wpdb->esc_like('amelia') . '%';

        $query = $wpdb->prepare(
            'SELECT TABLE_NAME
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = %s
               AND TABLE_NAME LIKE %s
             ORDER BY TABLE_NAME ASC',
            $database_name,
            $pattern
        );

        $tables = $wpdb->get_col($query);

        if (!is_array($tables)) {
            return [];
        }

        return array_values(array_filter(array_map('strval', $tables), [__CLASS__, 'is_safe_identifier']));
    }

    /**
     * Read column metadata from information_schema.
     *
     * @return array<int,array<string,mixed>>
     */
    private static function get_table_columns(string $table_name): array {
        global $wpdb;

        if (!self::is_safe_identifier($table_name)) {
            return [];
        }

        $query = $wpdb->prepare(
            'SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_KEY, EXTRA, ORDINAL_POSITION
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = %s
               AND TABLE_NAME = %s
             ORDER BY ORDINAL_POSITION ASC',
            (string) $wpdb->dbname,
            $table_name
        );

        $rows = $wpdb->get_results($query, ARRAY_A);
        return is_array($rows) ? $rows : [];
    }

    /**
     * Read index metadata safely.
     *
     * @return array<int,array<string,mixed>>
     */
    private static function get_table_indexes(string $table_name): array {
        global $wpdb;

        if (!self::is_safe_identifier($table_name)) {
            return [];
        }

        $rows = $wpdb->get_results('SHOW INDEX FROM `' . $table_name . '`', ARRAY_A);
        return is_array($rows) ? $rows : [];
    }

    /**
     * Count records in a discovered table.
     */
    private static function get_record_count(string $table_name): int {
        global $wpdb;

        if (!self::is_safe_identifier($table_name)) {
            return 0;
        }

        $count = $wpdb->get_var('SELECT COUNT(*) FROM `' . $table_name . '`');
        return is_numeric($count) ? (int) $count : 0;
    }

    /**
     * Extract primary key column names from SHOW INDEX results.
     *
     * @param array<int,array<string,mixed>> $indexes
     * @return string[]
     */
    private static function extract_primary_keys(array $indexes): array {
        $keys = [];

        foreach ($indexes as $index) {
            if (($index['Key_name'] ?? '') !== 'PRIMARY') {
                continue;
            }

            $column = isset($index['Column_name']) ? (string) $index['Column_name'] : '';
            if ($column !== '') {
                $keys[] = $column;
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * Infer likely relationships only from matching ID-style column names.
     * These are labeled as likely, never asserted as true foreign keys.
     *
     * @param array<int,array<string,mixed>> $tables
     * @return array<int,array<string,string>>
     */
    private static function infer_relationships(array $tables): array {
        $primary_key_map = [];
        $column_map = [];

        foreach ($tables as $table) {
            $table_name = (string) ($table['name'] ?? '');
            $primary_keys = (array) ($table['primary_keys'] ?? []);

            foreach ($primary_keys as $key) {
                $primary_key_map[strtolower((string) $key)][] = $table_name;
            }

            foreach ((array) ($table['columns'] ?? []) as $column) {
                $column_name = isset($column['COLUMN_NAME']) ? (string) $column['COLUMN_NAME'] : '';
                if ($column_name !== '') {
                    $column_map[$table_name][] = $column_name;
                }
            }
        }

        $relationships = [];

        foreach ($column_map as $source_table => $columns) {
            foreach ($columns as $column) {
                $column_lower = strtolower($column);

                if ($column_lower === 'id' || !preg_match('/(?:id|_id)$/i', $column)) {
                    continue;
                }

                $candidate_keys = [$column_lower, 'id'];
                foreach ($candidate_keys as $candidate_key) {
                    foreach (($primary_key_map[$candidate_key] ?? []) as $target_table) {
                        if ($target_table === $source_table) {
                            continue;
                        }

                        if (!self::table_name_matches_column($target_table, $column)) {
                            continue;
                        }

                        $relationships[] = [
                            'source_table'  => $source_table,
                            'source_column' => $column,
                            'target_table'  => $target_table,
                            'target_column' => $candidate_key,
                            'confidence'    => 'likely',
                        ];
                    }
                }
            }
        }

        $unique = [];
        foreach ($relationships as $relationship) {
            $key = implode('|', $relationship);
            $unique[$key] = $relationship;
        }

        return array_values($unique);
    }

    /**
     * Compare an ID column to a table name conservatively.
     */
    private static function table_name_matches_column(string $table_name, string $column_name): bool {
        $table = strtolower(self::short_table_name($table_name));
        $column = strtolower(preg_replace('/_?id$/i', '', $column_name));

        if ($column === '') {
            return false;
        }

        $table_tokens = preg_split('/[^a-z0-9]+/', $table) ?: [];
        $last_token = end($table_tokens);
        $singular = is_string($last_token) ? rtrim($last_token, 's') : '';

        return $column === $last_token
            || $column === $singular
            || strpos($table, $column) !== false;
    }

    /**
     * Render report summary cards.
     *
     * @param array<string,mixed> $report
     */
    private static function render_summary(array $report): void {
        $amelia = (array) ($report['amelia'] ?? []);
        $tables = (array) ($report['tables'] ?? []);
        ?>
        <div class="elev8-inspector-summary">
            <?php self::render_summary_item(__('Amelia', 'elev8-os'), !empty($amelia['detected']) ? (string) ($amelia['name'] ?? __('Detected', 'elev8-os')) : __('Not detected', 'elev8-os')); ?>
            <?php self::render_summary_item(__('Version', 'elev8-os'), (string) ($amelia['version'] ?? __('Unknown', 'elev8-os')) ?: __('Unknown', 'elev8-os')); ?>
            <?php self::render_summary_item(__('Plugin status', 'elev8-os'), !empty($amelia['active']) ? __('Active', 'elev8-os') : __('Inactive or unknown', 'elev8-os')); ?>
            <?php self::render_summary_item(__('Database prefix', 'elev8-os'), (string) ($report['table_prefix'] ?? '')); ?>
            <?php self::render_summary_item(__('Amelia tables', 'elev8-os'), number_format_i18n(count($tables))); ?>
        </div>
        <?php
    }

    private static function render_summary_item(string $label, string $value): void {
        ?>
        <div class="elev8-inspector-summary__item">
            <span><?php echo esc_html($label); ?></span>
            <strong><?php echo esc_html($value); ?></strong>
        </div>
        <?php
    }

    /**
     * Render discovered tables and column details.
     *
     * @param array<string,mixed> $report
     */
    private static function render_tables(array $report): void {
        $tables = (array) ($report['tables'] ?? []);
        ?>
        <h3><?php esc_html_e('Detected Amelia Tables', 'elev8-os'); ?></h3>

        <?php if ($tables === []) : ?>
            <div class="notice notice-warning inline">
                <p><?php esc_html_e('No tables containing “amelia” were found in this WordPress database.', 'elev8-os'); ?></p>
            </div>
            <?php return; ?>
        <?php endif; ?>

        <div class="elev8-table-wrap">
            <table class="widefat striped elev8-inspector-table">
                <thead>
                <tr>
                    <th><?php esc_html_e('Table', 'elev8-os'); ?></th>
                    <th><?php esc_html_e('Records', 'elev8-os'); ?></th>
                    <th><?php esc_html_e('Columns', 'elev8-os'); ?></th>
                    <th><?php esc_html_e('Primary key', 'elev8-os'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($tables as $table) : ?>
                    <tr>
                        <td><code><?php echo esc_html((string) ($table['name'] ?? '')); ?></code></td>
                        <td><?php echo esc_html(number_format_i18n((int) ($table['record_count'] ?? 0))); ?></td>
                        <td><?php echo esc_html(number_format_i18n(count((array) ($table['columns'] ?? [])))); ?></td>
                        <td><code><?php echo esc_html(implode(', ', (array) ($table['primary_keys'] ?? [])) ?: '—'); ?></code></td>
                    </tr>
                    <tr class="elev8-inspector-detail-row">
                        <td colspan="4">
                            <details>
                                <summary><?php esc_html_e('View columns and indexes', 'elev8-os'); ?></summary>
                                <?php self::render_column_table((array) ($table['columns'] ?? [])); ?>
                            </details>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * @param array<int,array<string,mixed>> $columns
     */
    private static function render_column_table(array $columns): void {
        ?>
        <div class="elev8-table-wrap elev8-column-table-wrap">
            <table class="widefat striped">
                <thead>
                <tr>
                    <th><?php esc_html_e('Column', 'elev8-os'); ?></th>
                    <th><?php esc_html_e('Type', 'elev8-os'); ?></th>
                    <th><?php esc_html_e('Nullable', 'elev8-os'); ?></th>
                    <th><?php esc_html_e('Key', 'elev8-os'); ?></th>
                    <th><?php esc_html_e('Extra', 'elev8-os'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($columns as $column) : ?>
                    <tr>
                        <td><code><?php echo esc_html((string) ($column['COLUMN_NAME'] ?? '')); ?></code></td>
                        <td><code><?php echo esc_html((string) ($column['COLUMN_TYPE'] ?? '')); ?></code></td>
                        <td><?php echo esc_html((string) ($column['IS_NULLABLE'] ?? '')); ?></td>
                        <td><?php echo esc_html((string) ($column['COLUMN_KEY'] ?? '')); ?></td>
                        <td><?php echo esc_html((string) ($column['EXTRA'] ?? '')); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Render conservative relationship suggestions.
     *
     * @param array<string,mixed> $report
     */
    private static function render_relationships(array $report): void {
        $relationships = (array) ($report['relationships'] ?? []);
        ?>
        <h3><?php esc_html_e('Likely Relationships', 'elev8-os'); ?></h3>
        <p class="description">
            <?php esc_html_e('These are naming-based suggestions for investigation. They are not treated as verified foreign keys until live data confirms them.', 'elev8-os'); ?>
        </p>

        <?php if ($relationships === []) : ?>
            <p><?php esc_html_e('No likely relationships were inferred from primary keys and ID-style column names.', 'elev8-os'); ?></p>
            <?php return; ?>
        <?php endif; ?>

        <div class="elev8-table-wrap">
            <table class="widefat striped">
                <thead>
                <tr>
                    <th><?php esc_html_e('Source', 'elev8-os'); ?></th>
                    <th><?php esc_html_e('Target', 'elev8-os'); ?></th>
                    <th><?php esc_html_e('Confidence', 'elev8-os'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($relationships as $relationship) : ?>
                    <tr>
                        <td><code><?php echo esc_html((string) ($relationship['source_table'] ?? '') . '.' . (string) ($relationship['source_column'] ?? '')); ?></code></td>
                        <td><code><?php echo esc_html((string) ($relationship['target_table'] ?? '') . '.' . (string) ($relationship['target_column'] ?? '')); ?></code></td>
                        <td><?php echo esc_html(ucfirst((string) ($relationship['confidence'] ?? 'likely'))); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Export the same report as downloadable JSON.
     */
    public static function export_json(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to export this report.', 'elev8-os'));
        }

        check_admin_referer(self::EXPORT_NONCE_ACTION);

        $report = self::build_report();
        $json = wp_json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (!is_string($json)) {
            wp_die(esc_html__('The System Inspector report could not be encoded.', 'elev8-os'));
        }

        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="elev8-os-amelia-discovery-' . gmdate('Y-m-d-His') . '.json"');
        header('Content-Length: ' . strlen($json));
        echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON download.
        exit;
    }

    /**
     * Return current admin page URL.
     */
    private static function page_url(): string {
        return admin_url('admin.php?page=' . self::PAGE_SLUG);
    }

    /**
     * Remove the WordPress prefix from a table name for display/inference.
     */
    private static function short_table_name(string $table_name): string {
        global $wpdb;

        if (strpos($table_name, (string) $wpdb->prefix) === 0) {
            return substr($table_name, strlen((string) $wpdb->prefix));
        }

        return $table_name;
    }

    /**
     * Permit only safe SQL identifiers discovered from information_schema.
     */
    private static function is_safe_identifier(string $identifier): bool {
        return (bool) preg_match('/^[A-Za-z0-9_]+$/', $identifier);
    }
}

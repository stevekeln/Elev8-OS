<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Read-only administrator tool for inspecting the current Elev8 OS,
 * WordPress, PHP, plugin, and Amelia database environment.
 */
final class Elev8_OS_System_Inspector_Module {

    private const PAGE_SLUG = 'elev8-os-system-inspector';

    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'register_admin_page'], 30);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    public static function status(): string {
        return 'active';
    }

    public static function register_admin_page(): void {
        add_submenu_page(
            'elev8-os',
            __('System Inspector', 'elev8-os'),
            __('System Inspector', 'elev8-os'),
            'manage_options',
            self::PAGE_SLUG,
            [__CLASS__, 'render']
        );
    }

    public static function enqueue_assets(string $hook): void {
        if ($hook !== 'elev8-os_page_' . self::PAGE_SLUG) {
            return;
        }

        wp_enqueue_style(
            'elev8-os-system-inspector',
            ELEV8_OS_URL . 'assets/css/system-inspector.css',
            [],
            ELEV8_OS_VERSION
        );
    }

    public static function render(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to view this page.', 'elev8-os'));
        }

        $system = self::system_information();
        $plugins = self::plugin_information();
        $tables = self::amelia_tables();
        $relationships = self::detect_relationships($tables);
        ?>
        <div class="wrap elev8-system-inspector">
            <header class="elev8-inspector-header">
                <div>
                    <p class="elev8-inspector-eyebrow"><?php esc_html_e('Elev8 OS', 'elev8-os'); ?></p>
                    <h1><?php esc_html_e('System Inspector', 'elev8-os'); ?></h1>
                    <p><?php esc_html_e('Read-only technical information for diagnosing compatibility and database structure.', 'elev8-os'); ?></p>
                </div>
                <span class="elev8-inspector-badge"><?php esc_html_e('Administrator Only', 'elev8-os'); ?></span>
            </header>

            <div class="notice notice-info inline">
                <p>
                    <strong><?php esc_html_e('Privacy-safe inspection:', 'elev8-os'); ?></strong>
                    <?php esc_html_e('This screen shows versions, table names, row counts, and column names only. It does not display customer records, passwords, payment information, or database credentials.', 'elev8-os'); ?>
                </p>
            </div>

            <section class="elev8-inspector-grid">
                <?php self::render_information_card(__('System', 'elev8-os'), $system); ?>
                <?php self::render_information_card(__('Plugins', 'elev8-os'), $plugins); ?>
            </section>

            <section class="elev8-inspector-section">
                <div class="elev8-inspector-section-heading">
                    <div>
                        <h2><?php esc_html_e('Detected Amelia Relationships', 'elev8-os'); ?></h2>
                        <p><?php esc_html_e('These classifications are based on table and column names. They are diagnostic suggestions, not database changes.', 'elev8-os'); ?></p>
                    </div>
                </div>

                <div class="elev8-relationship-grid">
                    <?php foreach ($relationships as $label => $result) : ?>
                        <article class="elev8-relationship-card">
                            <span class="dashicons <?php echo $result['found'] ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>" aria-hidden="true"></span>
                            <div>
                                <h3><?php echo esc_html($label); ?></h3>
                                <?php if ($result['found']) : ?>
                                    <code><?php echo esc_html($result['table']); ?></code>
                                    <?php if (!empty($result['reason'])) : ?>
                                        <p><?php echo esc_html($result['reason']); ?></p>
                                    <?php endif; ?>
                                <?php else : ?>
                                    <p><?php esc_html_e('No likely table detected.', 'elev8-os'); ?></p>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="elev8-inspector-section">
                <div class="elev8-inspector-section-heading">
                    <div>
                        <h2><?php esc_html_e('Amelia Database Tables', 'elev8-os'); ?></h2>
                        <p>
                            <?php
                            echo esc_html(
                                sprintf(
                                    _n(
                                        '%s Amelia table found.',
                                        '%s Amelia tables found.',
                                        count($tables),
                                        'elev8-os'
                                    ),
                                    number_format_i18n(count($tables))
                                )
                            );
                            ?>
                        </p>
                    </div>
                </div>

                <?php if (empty($tables)) : ?>
                    <div class="notice notice-warning inline">
                        <p><?php esc_html_e('No Amelia database tables were found using the current WordPress database prefix.', 'elev8-os'); ?></p>
                    </div>
                <?php else : ?>
                    <div class="elev8-table-list">
                        <?php foreach ($tables as $table) : ?>
                            <details class="elev8-table-card">
                                <summary>
                                    <span>
                                        <strong><?php echo esc_html($table['name']); ?></strong>
                                        <small>
                                            <?php
                                            echo esc_html(
                                                sprintf(
                                                    _n(
                                                        '%s row',
                                                        '%s rows',
                                                        $table['rows'],
                                                        'elev8-os'
                                                    ),
                                                    number_format_i18n($table['rows'])
                                                )
                                            );
                                            ?>
                                        </small>
                                    </span>
                                    <span class="elev8-table-purpose"><?php echo esc_html($table['purpose']); ?></span>
                                </summary>

                                <div class="elev8-table-columns">
                                    <h3><?php esc_html_e('Columns', 'elev8-os'); ?></h3>
                                    <?php if (empty($table['columns'])) : ?>
                                        <p><?php esc_html_e('No columns could be read.', 'elev8-os'); ?></p>
                                    <?php else : ?>
                                        <div class="elev8-column-tags">
                                            <?php foreach ($table['columns'] as $column) : ?>
                                                <code><?php echo esc_html($column); ?></code>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </details>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
        <?php
    }

    /**
     * @return array<string,string>
     */
    private static function system_information(): array {
        global $wpdb;

        return [
            __('WordPress version', 'elev8-os') => get_bloginfo('version'),
            __('PHP version', 'elev8-os') => PHP_VERSION,
            __('Elev8 OS version', 'elev8-os') => defined('ELEV8_OS_VERSION') ? ELEV8_OS_VERSION : __('Unknown', 'elev8-os'),
            __('Database prefix', 'elev8-os') => $wpdb->prefix,
            __('Site URL', 'elev8-os') => home_url('/'),
            __('Multisite', 'elev8-os') => is_multisite() ? __('Yes', 'elev8-os') : __('No', 'elev8-os'),
        ];
    }

    /**
     * @return array<string,string>
     */
    private static function plugin_information(): array {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugins = get_plugins();
        $active = (array) get_option('active_plugins', []);
        $amelia = self::find_plugin_by_keywords($plugins, ['amelia', 'ameliabooking']);
        $woocommerce = self::find_plugin_by_keywords($plugins, ['woocommerce']);

        return [
            __('Amelia status', 'elev8-os') => self::plugin_status_text($amelia, $active),
            __('Amelia version', 'elev8-os') => $amelia['version'] ?: __('Not detected', 'elev8-os'),
            __('WooCommerce status', 'elev8-os') => self::plugin_status_text($woocommerce, $active),
            __('Active plugin count', 'elev8-os') => (string) count($active),
        ];
    }

    /**
     * @param array<string,array<string,mixed>> $plugins
     * @param array<int,string> $keywords
     * @return array{file:string,version:string}
     */
    private static function find_plugin_by_keywords(array $plugins, array $keywords): array {
        foreach ($plugins as $file => $data) {
            $haystack = strtolower(
                $file . ' ' .
                (string) ($data['Name'] ?? '') . ' ' .
                (string) ($data['TextDomain'] ?? '')
            );

            foreach ($keywords as $keyword) {
                if (strpos($haystack, strtolower($keyword)) !== false) {
                    return [
                        'file' => (string) $file,
                        'version' => (string) ($data['Version'] ?? ''),
                    ];
                }
            }
        }

        return ['file' => '', 'version' => ''];
    }

    /**
     * @param array{file:string,version:string} $plugin
     * @param array<int,string> $active
     */
    private static function plugin_status_text(array $plugin, array $active): string {
        if ($plugin['file'] === '') {
            return __('Not detected', 'elev8-os');
        }

        return in_array($plugin['file'], $active, true)
            ? __('Active', 'elev8-os')
            : __('Installed but inactive', 'elev8-os');
    }

    /**
     * @return array<int,array{name:string,rows:int,columns:array<int,string>,purpose:string}>
     */
    private static function amelia_tables(): array {
        global $wpdb;

        $pattern = $wpdb->esc_like($wpdb->prefix . 'amelia_') . '%';
        $names = $wpdb->get_col(
            $wpdb->prepare('SHOW TABLES LIKE %s', $pattern)
        );

        if (!is_array($names)) {
            return [];
        }

        sort($names, SORT_NATURAL | SORT_FLAG_CASE);

        $tables = [];

        foreach ($names as $name) {
            $safe_name = self::validate_table_name((string) $name);

            if ($safe_name === '') {
                continue;
            }

            $rows = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$safe_name}`");
            $columns = $wpdb->get_col("DESCRIBE `{$safe_name}`", 0);

            $tables[] = [
                'name' => $safe_name,
                'rows' => max(0, $rows),
                'columns' => is_array($columns) ? array_map('strval', $columns) : [],
                'purpose' => self::classify_table($safe_name, is_array($columns) ? $columns : []),
            ];
        }

        return $tables;
    }

    private static function validate_table_name(string $table): string {
        global $wpdb;

        if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            return '';
        }

        if (strpos($table, $wpdb->prefix . 'amelia_') !== 0) {
            return '';
        }

        return $table;
    }

    /**
     * @param array<int,string> $columns
     */
    private static function classify_table(string $table, array $columns): string {
        $name = strtolower($table);
        $column_text = strtolower(implode(' ', $columns));

        if (strpos($name, 'customer_book') !== false || strpos($name, 'booking') !== false) {
            return __('Likely customer bookings', 'elev8-os');
        }

        if (strpos($name, 'appointment') !== false) {
            return __('Likely appointments or scheduled bookings', 'elev8-os');
        }

        if (strpos($name, 'provider') !== false && strpos($name, 'service') !== false) {
            return __('Likely artist-to-service assignments', 'elev8-os');
        }

        if (strpos($name, 'service') !== false) {
            return __('Likely services or class offerings', 'elev8-os');
        }

        if (strpos($name, 'event') !== false || strpos($column_text, 'periodstart') !== false) {
            return __('Likely events or scheduled dates', 'elev8-os');
        }

        if (strpos($name, 'user') !== false || strpos($column_text, 'firstname') !== false) {
            return __('Likely artists, employees, or customers', 'elev8-os');
        }

        if (strpos($name, 'location') !== false) {
            return __('Likely locations', 'elev8-os');
        }

        if (strpos($name, 'payment') !== false) {
            return __('Likely payment records', 'elev8-os');
        }

        return __('Purpose not yet classified', 'elev8-os');
    }

    /**
     * @param array<int,array{name:string,rows:int,columns:array<int,string>,purpose:string}> $tables
     * @return array<string,array{found:bool,table:string,reason:string}>
     */
    private static function detect_relationships(array $tables): array {
        return [
            __('Artist or Employee Table', 'elev8-os') => self::find_best_table(
                $tables,
                ['amelia_users'],
                ['firstName', 'lastName', 'email']
            ),
            __('Service Table', 'elev8-os') => self::find_best_table(
                $tables,
                ['amelia_services'],
                ['name', 'duration', 'price']
            ),
            __('Artist-to-Service Assignment', 'elev8-os') => self::find_best_table(
                $tables,
                ['providers_to_services', 'services_providers', 'providers_services'],
                ['providerId', 'userId', 'serviceId']
            ),
            __('Scheduled Date or Event Table', 'elev8-os') => self::find_best_table(
                $tables,
                ['events_periods', 'events'],
                ['periodStart', 'eventId', 'bookingStart']
            ),
            __('Appointment Table', 'elev8-os') => self::find_best_table(
                $tables,
                ['appointments'],
                ['providerId', 'bookingStart']
            ),
            __('Customer Booking Table', 'elev8-os') => self::find_best_table(
                $tables,
                ['customer_bookings', 'bookings'],
                ['customerId', 'appointmentId']
            ),
        ];
    }

    /**
     * @param array<int,array{name:string,rows:int,columns:array<int,string>,purpose:string}> $tables
     * @param array<int,string> $name_fragments
     * @param array<int,string> $column_candidates
     * @return array{found:bool,table:string,reason:string}
     */
    private static function find_best_table(
        array $tables,
        array $name_fragments,
        array $column_candidates
    ): array {
        $best = null;
        $best_score = 0;
        $best_reasons = [];

        foreach ($tables as $table) {
            $score = 0;
            $reasons = [];
            $lower_name = strtolower($table['name']);
            $lower_columns = array_map('strtolower', $table['columns']);

            foreach ($name_fragments as $fragment) {
                if (strpos($lower_name, strtolower($fragment)) !== false) {
                    $score += 4;
                    $reasons[] = sprintf(
                        /* translators: %s: matching table-name fragment */
                        __('name contains “%s”', 'elev8-os'),
                        $fragment
                    );
                }
            }

            foreach ($column_candidates as $column) {
                if (in_array(strtolower($column), $lower_columns, true)) {
                    $score += 1;
                    $reasons[] = sprintf(
                        /* translators: %s: matching database column */
                        __('has %s column', 'elev8-os'),
                        $column
                    );
                }
            }

            if ($score > $best_score) {
                $best = $table;
                $best_score = $score;
                $best_reasons = $reasons;
            }
        }

        if ($best === null || $best_score < 2) {
            return [
                'found' => false,
                'table' => '',
                'reason' => '',
            ];
        }

        return [
            'found' => true,
            'table' => $best['name'],
            'reason' => implode(', ', array_slice($best_reasons, 0, 3)),
        ];
    }

    /**
     * @param array<string,string> $items
     */
    private static function render_information_card(string $title, array $items): void {
        ?>
        <article class="elev8-inspector-card">
            <h2><?php echo esc_html($title); ?></h2>
            <dl>
                <?php foreach ($items as $label => $value) : ?>
                    <div>
                        <dt><?php echo esc_html($label); ?></dt>
                        <dd><?php echo esc_html($value); ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        </article>
        <?php
    }
}

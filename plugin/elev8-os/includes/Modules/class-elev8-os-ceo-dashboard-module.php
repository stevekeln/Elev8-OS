<?php
/**
 * Elev8 OS CEO Dashboard module.
 *
 * Owner-facing dashboard that consumes the reusable Business Intelligence
 * service. This first increment intentionally contains one verified KPI.
 *
 * @package Elev8OS
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Elev8_OS_CEO_Dashboard_Module {

    private const PAGE_SLUG = 'elev8-ceo-dashboard';

    /**
     * Register module hooks.
     */
    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'register_admin_page'], 40);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    /**
     * Register the CEO Dashboard beneath Elev8 OS.
     */
    public static function register_admin_page(): void {
        add_submenu_page(
            'elev8-os',
            __('Elev8 OS CEO Dashboard', 'elev8-os'),
            __('CEO Dashboard', 'elev8-os'),
            'manage_options',
            self::PAGE_SLUG,
            [__CLASS__, 'render_page']
        );
    }


    /**
     * Keep owner tools available while reducing CEO sidebar clutter.
     * The Daily Operations page remains registered and accessible from the
     * CEO workspace, but administrators do not need a second left-menu item.
     */
    public static function hide_owner_workspace_pages(): void {
        // Intentionally left visible. WordPress admin pages must remain registered
        // and reachable while the CEO workspace provides an additional navigation path.
    }

    /**
     * Render the shared CEO workspace navigation.
     */
    public static function render_workspace_navigation(string $active = 'overview'): void {
        $items = [
            'overview' => [__('Overview', 'elev8-os'), admin_url('admin.php?page=' . self::PAGE_SLUG)],
            'intelligence' => [__('Business Intelligence', 'elev8-os'), admin_url('admin.php?page=elev8-business-intelligence')],
            'operations' => [__('Business Memory', 'elev8-os'), admin_url('admin.php?page=' . self::PAGE_SLUG . '&view=memory')],
            'opportunities' => [__('Opportunities', 'elev8-os'), admin_url('admin.php?page=' . self::PAGE_SLUG . '&view=opportunities')],
            'class-requests' => [__('Class Requests', 'elev8-os'), admin_url('admin.php?page=' . self::PAGE_SLUG . '&view=class-requests')],
            'growth' => [__('Growth', 'elev8-os'), admin_url('admin.php?page=elev8-growth-center')],
            'system' => [__('System Health', 'elev8-os'), admin_url('admin.php?page=elev8-system-inspector')],
        ];

        echo '<nav class="elev8-ceo-workspace-nav" aria-label="' . esc_attr__('CEO workspace', 'elev8-os') . '">';
        foreach ($items as $key => $item) {
            $class = $key === $active ? 'is-active' : '';
            echo '<a class="' . esc_attr($class) . '" href="' . esc_url($item[1]) . '">' . esc_html($item[0]) . '</a>';
        }
        echo '</nav>';
    }

    /**
     * Reuse the existing Business Intelligence dashboard stylesheet.
     */
    public static function enqueue_assets(string $hook_suffix): void {
        if ($hook_suffix !== 'elev8-os_page_' . self::PAGE_SLUG) {
            return;
        }

        wp_enqueue_style('dashicons');

        wp_enqueue_style(
            'elev8-os-business-intelligence-dashboard',
            ELEV8_OS_URL . 'assets/css/business-intelligence-dashboard.css',
            [],
            ELEV8_OS_VERSION
        );
    }

    /**
     * Render the CEO Dashboard.
     */
    public static function render_page(): void {
        if (!Elev8_OS_Access_Service::user_can('view_ceo_dashboard')) {
            wp_die(esc_html__('You do not have permission to view this dashboard.', 'elev8-os'));
        }

        $view = sanitize_key((string) ($_GET['view'] ?? 'overview'));

        if ($view === 'memory' && class_exists('Elev8_OS_Daily_Operations_Module')) {
            Elev8_OS_Daily_Operations_Module::render();
            return;
        }

        if ($view === 'class-requests' && class_exists('Elev8_OS_Class_Demand_Manager_Module')) {
            Elev8_OS_Class_Demand_Manager_Module::render();
            return;
        }

        if ($view === 'opportunities' && class_exists('Elev8_OS_Opportunity_Module')) {
            Elev8_OS_Opportunity_Module::render();
            return;
        }

        if (!class_exists('Elev8_OS_Business_Intelligence')) {
            self::render_missing_service_notice();
            return;
        }

        $report = Elev8_OS_Business_Intelligence::get_dashboard_report();
        $metrics = isset($report['metrics']) && is_array($report['metrics'])
            ? $report['metrics']
            : [];

        $booked_value = $metrics['booked_value_month']
            ?? $metrics['booked_value']
            ?? [];
        $booked_value_change = $metrics['booked_value_change'] ?? [];
        $release_information = class_exists('Elev8_OS_Release_Information_Service')
            ? Elev8_OS_Release_Information_Service::get_release_information()
            : [
                'available' => false,
                'diagnostic' => __('Release information service is unavailable.', 'elev8-os'),
            ];

        $operational_summary = class_exists('Elev8_OS_Dashboard_Service')
            ? Elev8_OS_Dashboard_Service::summary(wp_get_current_user())
            : [];
        $operational_priorities = class_exists('Elev8_OS_Dashboard_Service')
            ? Elev8_OS_Dashboard_Service::priorities($operational_summary)
            : [];

        ?>
        <div class="wrap elev8-bi-dashboard">
            <header class="elev8-bi-header">
                <div>
                    <p class="elev8-bi-eyebrow"><?php esc_html_e('Elev8 OS', 'elev8-os'); ?></p>
                    <h1><?php esc_html_e('CEO Dashboard', 'elev8-os'); ?></h1>
                    <p>
                        <?php
                        esc_html_e(
                            'A focused owner view for the most important operating decisions. Each KPI must come from a verified Elev8 OS service.',
                            'elev8-os'
                        );
                        ?>
                    </p>
                </div>

                <div class="elev8-bi-header__meta">
                    <span class="elev8-bi-badge"><?php esc_html_e('Foundation', 'elev8-os'); ?></span>
                    <span>
                        <?php
                        echo esc_html(
                            sprintf(
                                __('Updated %s', 'elev8-os'),
                                self::format_generated_at((string) ($report['generated_at'] ?? ''))
                            )
                        );
                        ?>
                    </span>
                </div>
            </header>

            <?php self::render_operational_home($operational_summary, $operational_priorities); ?>

            <section class="elev8-ceo-launchpad" aria-labelledby="elev8-ceo-launchpad-heading">
                <div class="elev8-bi-section__heading">
                    <div>
                        <h2 id="elev8-ceo-launchpad-heading"><?php esc_html_e('CEO Command Center', 'elev8-os'); ?></h2>
                        <p><?php esc_html_e('Open the part of the business that needs your attention without searching through the WordPress sidebar.', 'elev8-os'); ?></p>
                    </div>
                </div>
                <div class="elev8-ceo-launchpad__grid">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG . '&view=memory')); ?>"><span class="dashicons dashicons-clipboard"></span><strong><?php esc_html_e('Business Memory', 'elev8-os'); ?></strong><small><?php esc_html_e('Daily brief, operating logs, issues, and trends.', 'elev8-os'); ?></small></a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=elev8-business-intelligence')); ?>"><span class="dashicons dashicons-chart-area"></span><strong><?php esc_html_e('Business Intelligence', 'elev8-os'); ?></strong><small><?php esc_html_e('Verified KPIs and decision support.', 'elev8-os'); ?></small></a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG . '&view=class-requests')); ?>"><span class="dashicons dashicons-lightbulb"></span><strong><?php esc_html_e('Class Requests', 'elev8-os'); ?></strong><small><?php esc_html_e('Customer demand and class opportunities.', 'elev8-os'); ?></small></a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG . '&view=opportunities')); ?>"><span class="dashicons dashicons-megaphone"></span><strong><?php esc_html_e('Opportunities', 'elev8-os'); ?></strong><small><?php esc_html_e('Actions that can help artists and the business grow.', 'elev8-os'); ?></small></a>
                    <?php if (class_exists('Elev8_OS_Bingo_Reservations_Module')) : ?>
                    <a href="<?php echo esc_url(Elev8_OS_Bingo_Reservations_Module::admin_url()); ?>"><span class="dashicons dashicons-tickets-alt"></span><strong><?php esc_html_e('Reservations', 'elev8-os'); ?></strong><small><?php echo esc_html(sprintf(__('%1$d need attention · %2$d upcoming this week', 'elev8-os'), Elev8_OS_Bingo_Reservations_Module::attention_count(), Elev8_OS_Bingo_Reservations_Module::upcoming_count())); ?></small></a>
                    <?php endif; ?>
                    <?php if (class_exists('Elev8_OS_Event_Applications_Module')) : ?>
                    <a href="<?php echo esc_url(Elev8_OS_Event_Applications_Module::admin_url()); ?>"><span class="dashicons dashicons-forms"></span><strong><?php esc_html_e('Event Applications', 'elev8-os'); ?></strong><small><?php echo esc_html(sprintf(__('%1$d need attention · %2$d awaiting agreement', 'elev8-os'), Elev8_OS_Event_Applications_Module::attention_count(), Elev8_OS_Event_Applications_Module::awaiting_agreement_count())); ?></small></a>
                    <?php endif; ?>
                    <?php if (class_exists('Elev8_OS_Work_Service') && class_exists('Elev8_OS_Work_Module')) : $work_counts = Elev8_OS_Work_Service::counts(); ?>
                    <a href="<?php echo esc_url(Elev8_OS_Work_Module::team_url()); ?>"><span class="dashicons dashicons-list-view"></span><strong><?php esc_html_e('Work', 'elev8-os'); ?></strong><small><?php echo esc_html(sprintf(__('%1$d overdue · %2$d due today · %3$d active', 'elev8-os'), $work_counts['overdue'], $work_counts['due_today'], $work_counts['active'])); ?></small></a>
                    <?php endif; ?>
                </div>
            </section>

            <section class="elev8-bi-section" aria-labelledby="elev8-ceo-money-heading">
                <div class="elev8-bi-section__heading">
                    <div>
                        <h2 id="elev8-ceo-money-heading"><?php esc_html_e('Money at a Glance', 'elev8-os'); ?></h2>
                        <p>
                            <?php
                            esc_html_e(
                                'Booked value is scheduled booking value, not recognized revenue, settled cash, payout, or profit.',
                                'elev8-os'
                            );
                            ?>
                        </p>
                    </div>
                </div>

                <div class="elev8-bi-grid elev8-bi-grid--two">
                    <?php
                    self::render_metric_card(
                        __('Booked Value This Month', 'elev8-os'),
                        $booked_value,
                        'money-alt'
                    );

                    self::render_metric_card(
                        __('Booked Value vs Last Month', 'elev8-os'),
                        $booked_value_change,
                        'chart-line'
                    );
                    ?>
                </div>
            </section>

            <section class="elev8-bi-section" aria-labelledby="elev8-ceo-system-heading">
                <div class="elev8-bi-section__heading">
                    <div>
                        <h2 id="elev8-ceo-system-heading"><?php esc_html_e('System Information', 'elev8-os'); ?></h2>
                        <p><?php esc_html_e('Verified release details generated by the Elev8 OS release builder.', 'elev8-os'); ?></p>
                    </div>
                </div>

                <?php self::render_release_information($release_information); ?>
            </section>
        </div>
        <?php
    }


    /**
     * Render the role-aware Operational Home using verified shared services.
     *
     * @param array<string,mixed> $summary
     * @param array<int,array<string,string|int>> $priorities
     */
    private static function render_operational_home(array $summary, array $priorities): void {
        $my = is_array($summary['my_work'] ?? null) ? $summary['my_work'] : [];
        $team = is_array($summary['team_work'] ?? null) ? $summary['team_work'] : [];
        $reservations = is_array($summary['reservations'] ?? null) ? $summary['reservations'] : [];
        $applications = is_array($summary['applications'] ?? null) ? $summary['applications'] : [];
        $work_url = class_exists('Elev8_OS_Work_Module') ? Elev8_OS_Work_Module::my_url() : '#';
        $team_url = class_exists('Elev8_OS_Work_Module') ? Elev8_OS_Work_Module::team_url() : '#';
        ?>
        <section class="elev8-operational-brief" aria-label="<?php esc_attr_e('Operational summary', 'elev8-os'); ?>">
            <a class="elev8-operational-stat <?php echo (int) ($summary['needs_attention'] ?? 0) > 0 ? 'is-alert' : ''; ?>" href="#elev8-needs-attention">
                <span><?php esc_html_e('Needs Attention', 'elev8-os'); ?></span>
                <strong><?php echo (int) ($summary['needs_attention'] ?? 0); ?></strong>
                <span><?php esc_html_e('Verified items requiring review', 'elev8-os'); ?></span>
            </a>
            <a class="elev8-operational-stat <?php echo (int) ($my['overdue'] ?? 0) > 0 ? 'is-alert' : ''; ?>" href="<?php echo esc_url($work_url); ?>">
                <span><?php esc_html_e('My Work', 'elev8-os'); ?></span>
                <strong><?php echo (int) ($my['active'] ?? 0); ?></strong>
                <span><?php echo esc_html(sprintf(__('%1$d overdue · %2$d due today', 'elev8-os'), (int) ($my['overdue'] ?? 0), (int) ($my['due_today'] ?? 0))); ?></span>
            </a>
            <a class="elev8-operational-stat <?php echo (int) ($team['unassigned'] ?? 0) > 0 ? 'is-warning' : ''; ?>" href="<?php echo esc_url($team_url); ?>">
                <span><?php esc_html_e('Team Work', 'elev8-os'); ?></span>
                <strong><?php echo (int) ($team['active'] ?? 0); ?></strong>
                <span><?php echo esc_html(sprintf(__('%1$d unassigned · %2$d waiting', 'elev8-os'), (int) ($team['unassigned'] ?? 0), (int) ($team['waiting'] ?? 0))); ?></span>
            </a>
            <a class="elev8-operational-stat <?php echo (int) ($reservations['attention'] ?? 0) > 0 ? 'is-warning' : ''; ?>" href="<?php echo esc_url(class_exists('Elev8_OS_Bingo_Reservations_Module') ? Elev8_OS_Bingo_Reservations_Module::admin_url() : '#'); ?>">
                <span><?php esc_html_e('Reservations', 'elev8-os'); ?></span>
                <strong><?php echo (int) ($reservations['attention'] ?? 0); ?></strong>
                <span><?php echo esc_html(sprintf(__('%d upcoming this week', 'elev8-os'), (int) ($reservations['upcoming'] ?? 0))); ?></span>
            </a>
        </section>

        <section class="elev8-operational-layout">
            <article class="elev8-operational-panel" id="elev8-needs-attention">
                <h2><?php esc_html_e('Needs Attention', 'elev8-os'); ?></h2>
                <p><?php esc_html_e('The most urgent verified work across Elev8 OS.', 'elev8-os'); ?></p>
                <?php if ($priorities) : ?>
                    <ul class="elev8-priority-list">
                        <?php foreach ($priorities as $priority) : ?>
                            <li class="elev8-priority-<?php echo esc_attr((string) $priority['severity']); ?>">
                                <a href="<?php echo esc_url((string) $priority['url']); ?>">
                                    <span class="elev8-priority-count"><?php echo (int) $priority['count']; ?></span>
                                    <strong><?php echo esc_html((string) $priority['label']); ?></strong>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else : ?>
                    <div class="elev8-empty-state"><?php esc_html_e('No verified urgent items are waiting right now.', 'elev8-os'); ?></div>
                <?php endif; ?>
            </article>

            <article class="elev8-operational-panel">
                <h2><?php esc_html_e("Today's Business", 'elev8-os'); ?></h2>
                <p><?php esc_html_e('Live operational signals from connected Elev8 OS services.', 'elev8-os'); ?></p>
                <div class="elev8-operational-grid">
                    <a class="elev8-operational-card" href="<?php echo esc_url($work_url); ?>"><strong><?php esc_html_e('My Work', 'elev8-os'); ?></strong><small><?php echo esc_html(sprintf(__('%1$d active · %2$d waiting', 'elev8-os'), (int) ($my['active'] ?? 0), (int) ($my['waiting'] ?? 0))); ?></small></a>
                    <a class="elev8-operational-card" href="<?php echo esc_url($team_url); ?>"><strong><?php esc_html_e('Team Work', 'elev8-os'); ?></strong><small><?php echo esc_html(sprintf(__('%1$d active · %2$d overdue', 'elev8-os'), (int) ($team['active'] ?? 0), (int) ($team['overdue'] ?? 0))); ?></small></a>
                    <a class="elev8-operational-card" href="<?php echo esc_url(class_exists('Elev8_OS_Event_Applications_Module') ? Elev8_OS_Event_Applications_Module::admin_url() : '#'); ?>"><strong><?php esc_html_e('Event Applications', 'elev8-os'); ?></strong><small><?php echo esc_html(sprintf(__('%1$d need attention · %2$d awaiting agreement', 'elev8-os'), (int) ($applications['attention'] ?? 0), (int) ($applications['awaiting_agreement'] ?? 0))); ?></small></a>
                    <div class="elev8-operational-card elev8-operational-unavailable"><strong><?php esc_html_e('Sales Snapshot', 'elev8-os'); ?></strong><small><?php esc_html_e('Unavailable — verified aggregation is not connected yet.', 'elev8-os'); ?></small></div>
                    <div class="elev8-operational-card elev8-operational-unavailable"><strong><?php esc_html_e('Upcoming Events', 'elev8-os'); ?></strong><small><?php esc_html_e('Unavailable — shared event calendar is not connected yet.', 'elev8-os'); ?></small></div>
                    <div class="elev8-operational-card elev8-operational-unavailable"><strong><?php esc_html_e('Notifications', 'elev8-os'); ?></strong><small><?php esc_html_e('Unavailable — role-aware counts are not connected yet.', 'elev8-os'); ?></small></div>
                </div>
            </article>
        </section>
        <?php
    }

    /**
     * @param array<string,mixed> $release
     */
    private static function render_release_information(array $release): void {
        $available = !empty($release['available']);
        $unavailable = __('Unavailable', 'elev8-os');
        $version = self::release_value($release, 'version', $unavailable);
        $build = self::release_value($release, 'build', $unavailable);
        $branch = self::release_value($release, 'branch', $unavailable);
        $commit = self::release_value($release, 'commit', $unavailable);
        $php_syntax = self::release_value($release, 'php_syntax', $unavailable);
        $package = self::release_value($release, 'package', $unavailable);
        $built_at = self::format_release_date((string) ($release['built_at_utc'] ?? ''), $unavailable);
        $php_file_count = isset($release['php_file_count']) && is_numeric($release['php_file_count'])
            ? number_format_i18n((int) $release['php_file_count'])
            : $unavailable;

        if (array_key_exists('working_tree_dirty', $release) && $release['working_tree_dirty'] !== null) {
            $source_state = $release['working_tree_dirty']
                ? __('Uncommitted changes included', 'elev8-os')
                : __('Clean Git working tree', 'elev8-os');
        } else {
            $source_state = $unavailable;
        }
        ?>
        <div class="elev8-bi-grid elev8-bi-grid--two">
            <?php
            self::render_system_card(__('Installed Version', 'elev8-os'), $version, 'admin-plugins');
            self::render_system_card(__('Build ID', 'elev8-os'), $build, 'hammer');
            self::render_system_card(__('Build Date', 'elev8-os'), $built_at, 'calendar-alt');
            self::render_system_card(__('PHP Validation', 'elev8-os'), $php_syntax, 'yes-alt');
            self::render_system_card(__('PHP Files Validated', 'elev8-os'), $php_file_count, 'media-code');
            self::render_system_card(__('Git Branch', 'elev8-os'), $branch, 'networking');
            self::render_system_card(__('Git Commit', 'elev8-os'), $commit, 'editor-code');
            self::render_system_card(__('Source State', 'elev8-os'), $source_state, 'info-outline');
            self::render_system_card(__('Release Package', 'elev8-os'), $package, 'archive');
            ?>
        </div>
        <p class="elev8-bi-metric__diagnostic">
            <?php echo esc_html((string) ($release['diagnostic'] ?? $unavailable)); ?>
            <?php if (!$available) : ?>
                <?php esc_html_e(' Rebuild the plugin with the standard Elev8 OS release builder to restore verified metadata.', 'elev8-os'); ?>
            <?php endif; ?>
        </p>
        <?php
    }

    private static function render_system_card(string $label, string $value, string $icon): void {
        ?>
        <article class="elev8-bi-metric">
            <div class="elev8-bi-metric__top">
                <span class="dashicons dashicons-<?php echo esc_attr($icon); ?>" aria-hidden="true"></span>
            </div>
            <p class="elev8-bi-metric__label"><?php echo esc_html($label); ?></p>
            <strong class="elev8-bi-metric__value"><?php echo esc_html($value); ?></strong>
        </article>
        <?php
    }

    /**
     * @param array<string,mixed> $release
     */
    private static function release_value(array $release, string $key, string $fallback): string {
        return isset($release[$key]) && is_scalar($release[$key]) && trim((string) $release[$key]) !== ''
            ? trim((string) $release[$key])
            : $fallback;
    }

    private static function format_release_date(string $date, string $fallback): string {
        if ($date === '') {
            return $fallback;
        }

        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return $fallback;
        }

        return wp_date(get_option('date_format') . ' ' . get_option('time_format'), $timestamp);
    }

    /**
     * @param array<string,mixed> $metric
     */
    private static function render_metric_card(string $label, array $metric, string $icon): void {
        $available = !empty($metric['available']);
        $confidence = $available
            ? sanitize_html_class((string) ($metric['confidence'] ?? 'unknown'))
            : 'unavailable';
        $diagnostic = (string) (
            $metric['diagnostic']
            ?? __('No diagnostic information was supplied.', 'elev8-os')
        );
        ?>
        <article class="elev8-bi-metric <?php echo $available ? '' : 'is-unavailable'; ?>">
            <div class="elev8-bi-metric__top">
                <span class="dashicons dashicons-<?php echo esc_attr($icon); ?>" aria-hidden="true"></span>
                <span class="elev8-bi-confidence elev8-bi-confidence--<?php echo esc_attr($confidence); ?>">
                    <?php echo esc_html(self::confidence_label($confidence)); ?>
                </span>
            </div>

            <p class="elev8-bi-metric__label"><?php echo esc_html($label); ?></p>

            <strong class="elev8-bi-metric__value">
                <?php
                echo esc_html(
                    $available
                        ? self::format_metric_value($metric)
                        : __('Unavailable', 'elev8-os')
                );
                ?>
            </strong>

            <p class="elev8-bi-metric__diagnostic"><?php echo esc_html($diagnostic); ?></p>
        </article>
        <?php
    }


    /**
     * @param array<string,mixed> $metric
     */
    private static function format_metric_value(array $metric): string {
        $value = $metric['value'] ?? null;
        $format = (string) ($metric['format'] ?? 'number');

        if (!is_numeric($value)) {
            return __('Unavailable', 'elev8-os');
        }

        if ($format === 'currency') {
            if (function_exists('wc_price')) {
                return wp_strip_all_tags((string) wc_price((float) $value));
            }

            $symbol = apply_filters('elev8_os_currency_symbol', '$');
            return (string) $symbol . number_format_i18n((float) $value, 2);
        }

        if ($format === 'percent') {
            return number_format_i18n((float) $value, 1) . '%';
        }

        if ($format === 'signed_percent') {
            $numeric_value = (float) $value;
            $prefix = $numeric_value > 0 ? '+' : '';

            return $prefix . number_format_i18n($numeric_value, 1) . '%';
        }

        return number_format_i18n(
            (float) $value,
            (float) $value === floor((float) $value) ? 0 : 1
        );
    }

    private static function confidence_label(string $confidence): string {
        switch ($confidence) {
            case 'high':
                return __('High confidence', 'elev8-os');

            case 'medium':
                return __('Medium confidence', 'elev8-os');

            case 'low':
                return __('Low confidence', 'elev8-os');

            case 'unavailable':
                return __('Unavailable', 'elev8-os');

            default:
                return __('Unknown confidence', 'elev8-os');
        }
    }

    private static function format_generated_at(string $generated_at): string {
        if ($generated_at === '') {
            return __('just now', 'elev8-os');
        }

        $timestamp = strtotime($generated_at);

        if ($timestamp === false) {
            return $generated_at;
        }

        return wp_date(
            get_option('date_format') . ' ' . get_option('time_format'),
            $timestamp
        );
    }

    private static function render_missing_service_notice(): void {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('CEO Dashboard', 'elev8-os'); ?></h1>
            <div class="notice notice-error">
                <p>
                    <?php
                    esc_html_e(
                        'The Business Intelligence service could not be loaded. The CEO Dashboard cannot calculate verified KPIs without it.',
                        'elev8-os'
                    );
                    ?>
                </p>
            </div>
        </div>
        <?php
    }
}

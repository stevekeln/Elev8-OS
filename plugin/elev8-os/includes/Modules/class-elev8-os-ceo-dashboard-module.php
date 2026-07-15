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
        add_action('admin_menu', [__CLASS__, 'register_admin_page'], 42);
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
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to view this dashboard.', 'elev8-os'));
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
        </div>
        <?php
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

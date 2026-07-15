<?php
/**
 * Elev8 OS Business Intelligence Dashboard module.
 *
 * Owner-facing, read-only dashboard. This module never queries Amelia
 * directly; all metrics come from Elev8_OS_Business_Intelligence.
 *
 * @package Elev8OS
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Elev8_OS_Business_Intelligence_Dashboard_Module {

    private const PAGE_SLUG = 'elev8-business-intelligence';

    /**
     * Register module hooks.
     */
    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'register_admin_page'], 45);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    /**
     * Register the owner-facing dashboard beneath Elev8 OS.
     */
    public static function register_admin_page(): void {
        add_submenu_page(
            'elev8-os',
            __('Elev8 OS Business Intelligence', 'elev8-os'),
            __('Business Intelligence', 'elev8-os'),
            'manage_options',
            self::PAGE_SLUG,
            [__CLASS__, 'render_page']
        );
    }

    /**
     * Load dashboard styles only on this screen.
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
     * Render the dashboard.
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
        $upcoming = isset($report['upcoming_class_dates']) && is_array($report['upcoming_class_dates'])
            ? $report['upcoming_class_dates']
            : [];
        $diagnostics = isset($report['diagnostics']) && is_array($report['diagnostics'])
            ? $report['diagnostics']
            : [];

        ?>
        <div class="wrap elev8-bi-dashboard">
            <header class="elev8-bi-header">
                <div>
                    <p class="elev8-bi-eyebrow"><?php esc_html_e('Elev8 OS', 'elev8-os'); ?></p>
                    <h1><?php esc_html_e('Business Intelligence', 'elev8-os'); ?></h1>
                    <p>
                        <?php
                        esc_html_e(
                            'Read-only operational visibility from verified Amelia data. Unavailable metrics are never presented as zero.',
                            'elev8-os'
                        );
                        ?>
                    </p>
                </div>

                <div class="elev8-bi-header__meta">
                    <span class="elev8-bi-badge"><?php esc_html_e('Version 1', 'elev8-os'); ?></span>
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
                    <span>
                        <?php
                        echo esc_html(
                            sprintf(
                                __('Timezone: %s', 'elev8-os'),
                                (string) ($report['timezone'] ?? wp_timezone_string())
                            )
                        );
                        ?>
                    </span>
                </div>
            </header>

            <section class="elev8-bi-section" aria-labelledby="elev8-bi-operations-heading">
                <div class="elev8-bi-section__heading">
                    <div>
                        <h2 id="elev8-bi-operations-heading"><?php esc_html_e('Operations', 'elev8-os'); ?></h2>
                        <p><?php esc_html_e('Scheduled classes and students currently booked.', 'elev8-os'); ?></p>
                    </div>
                </div>

                <div class="elev8-bi-grid">
                    <?php
                    self::render_metric_card(
                        __('Classes Today', 'elev8-os'),
                        $metrics['classes_today'] ?? [],
                        'calendar-alt'
                    );
                    self::render_metric_card(
                        __('Classes This Month', 'elev8-os'),
                        $metrics['classes_month'] ?? [],
                        'calendar'
                    );
                    self::render_metric_card(
                        __('Students Booked Today', 'elev8-os'),
                        $metrics['students_today'] ?? [],
                        'groups'
                    );
                    self::render_metric_card(
                        __('Students Booked This Month', 'elev8-os'),
                        $metrics['students_month'] ?? [],
                        'businessperson'
                    );
                    ?>
                </div>
            </section>

            <section class="elev8-bi-section" aria-labelledby="elev8-bi-bookings-heading">
                <div class="elev8-bi-section__heading">
                    <div>
                        <h2 id="elev8-bi-bookings-heading"><?php esc_html_e('Bookings', 'elev8-os'); ?></h2>
                        <p><?php esc_html_e('Booking status and attendance indicators.', 'elev8-os'); ?></p>
                    </div>
                </div>

                <div class="elev8-bi-grid">
                    <?php
                    self::render_metric_card(
                        __('Pending Bookings', 'elev8-os'),
                        $metrics['pending_bookings'] ?? [],
                        'clock'
                    );
                    self::render_metric_card(
                        __('Cancelled Bookings', 'elev8-os'),
                        $metrics['cancelled_bookings'] ?? [],
                        'dismiss'
                    );
                    self::render_metric_card(
                        __('Cancellation Rate', 'elev8-os'),
                        $metrics['cancellation_rate'] ?? [],
                        'chart-line'
                    );
                    self::render_metric_card(
                        __('Average Class Size', 'elev8-os'),
                        $metrics['average_class_size'] ?? [],
                        'chart-bar'
                    );
                    ?>
                </div>
            </section>

            <section class="elev8-bi-section" aria-labelledby="elev8-bi-value-heading">
                <div class="elev8-bi-section__heading">
                    <div>
                        <h2 id="elev8-bi-value-heading"><?php esc_html_e('Value and Revenue', 'elev8-os'); ?></h2>
                        <p>
                            <?php
                            esc_html_e(
                                'Booked value is not the same as recognized revenue. Payout calculations are intentionally excluded.',
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
                        $metrics['booked_value'] ?? [],
                        'money-alt'
                    );
                    self::render_metric_card(
                        __('Recognized Revenue', 'elev8-os'),
                        $metrics['recognized_revenue'] ?? [],
                        'bank'
                    );
                    ?>
                </div>
            </section>

            <div class="elev8-bi-columns">
                <section class="elev8-bi-section" aria-labelledby="elev8-bi-upcoming-heading">
                    <div class="elev8-bi-section__heading">
                        <div>
                            <h2 id="elev8-bi-upcoming-heading"><?php esc_html_e('Upcoming Class Dates', 'elev8-os'); ?></h2>
                            <p><?php esc_html_e('The next verified Amelia schedule dates.', 'elev8-os'); ?></p>
                        </div>
                    </div>

                    <?php self::render_upcoming_dates($upcoming); ?>
                </section>

                <section class="elev8-bi-section" aria-labelledby="elev8-bi-diagnostics-heading">
                    <div class="elev8-bi-section__heading">
                        <div>
                            <h2 id="elev8-bi-diagnostics-heading"><?php esc_html_e('Data Diagnostics', 'elev8-os'); ?></h2>
                            <p><?php esc_html_e('What the dashboard could reliably detect.', 'elev8-os'); ?></p>
                        </div>
                    </div>

                    <?php self::render_diagnostics($diagnostics); ?>
                </section>
            </div>
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
        $diagnostic = (string) ($metric['diagnostic'] ?? __('No diagnostic information was supplied.', 'elev8-os'));
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

        switch ($format) {
            case 'currency':
                return self::format_currency((float) $value);

            case 'percent':
                return number_format_i18n((float) $value, 1) . '%';

            case 'decimal':
                return number_format_i18n((float) $value, 1);

            case 'number':
            default:
                return number_format_i18n((float) $value, (float) $value === floor((float) $value) ? 0 : 1);
        }
    }

    private static function format_currency(float $value): string {
        if (function_exists('wc_price')) {
            return wp_strip_all_tags((string) wc_price($value));
        }

        $symbol = apply_filters('elev8_os_currency_symbol', '$');
        return (string) $symbol . number_format_i18n($value, 2);
    }

    /**
     * @param array<string,mixed> $upcoming
     */
    private static function render_upcoming_dates(array $upcoming): void {
        $items = isset($upcoming['items']) && is_array($upcoming['items'])
            ? $upcoming['items']
            : [];

        if (empty($upcoming['available']) || $items === []) {
            ?>
            <div class="elev8-bi-empty">
                <strong><?php esc_html_e('Unavailable', 'elev8-os'); ?></strong>
                <p>
                    <?php
                    echo esc_html(
                        (string) ($upcoming['diagnostic'] ?? __('No reliable upcoming class dates were detected.', 'elev8-os'))
                    );
                    ?>
                </p>
            </div>
            <?php
            return;
        }
        ?>
        <ol class="elev8-bi-upcoming">
            <?php foreach ($items as $item) : ?>
                <?php
                $raw_date = (string) ($item['date'] ?? '');
                $source = (string) ($item['source'] ?? 'schedule');
                ?>
                <li>
                    <span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>
                    <div>
                        <strong><?php echo esc_html(self::format_class_date($raw_date)); ?></strong>
                        <span>
                            <?php
                            echo esc_html(
                                $source === 'event'
                                    ? __('Amelia event', 'elev8-os')
                                    : __('Amelia appointment', 'elev8-os')
                            );
                            ?>
                        </span>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>
        <p class="elev8-bi-footnote">
            <?php echo esc_html((string) ($upcoming['diagnostic'] ?? '')); ?>
        </p>
        <?php
    }

    /**
     * @param array<string,mixed> $diagnostics
     */
    private static function render_diagnostics(array $diagnostics): void {
        $sources = isset($diagnostics['detected_sources']) && is_array($diagnostics['detected_sources'])
            ? $diagnostics['detected_sources']
            : [];
        $notes = isset($diagnostics['notes']) && is_array($diagnostics['notes'])
            ? $diagnostics['notes']
            : [];
        ?>
        <dl class="elev8-bi-diagnostics">
            <div>
                <dt><?php esc_html_e('Access', 'elev8-os'); ?></dt>
                <dd><?php echo esc_html(ucfirst((string) ($diagnostics['access'] ?? 'read-only'))); ?></dd>
            </div>
            <div>
                <dt><?php esc_html_e('Amelia Tables', 'elev8-os'); ?></dt>
                <dd><?php echo esc_html(number_format_i18n((int) ($diagnostics['amelia_tables_discovered'] ?? 0))); ?></dd>
            </div>
            <div>
                <dt><?php esc_html_e('Verified Sources', 'elev8-os'); ?></dt>
                <dd><?php echo esc_html(number_format_i18n(count($sources))); ?></dd>
            </div>
        </dl>

        <h3><?php esc_html_e('Detected Sources', 'elev8-os'); ?></h3>

        <?php if ($sources === []) : ?>
            <p><?php esc_html_e('No supported Amelia sources were verified.', 'elev8-os'); ?></p>
        <?php else : ?>
            <ul class="elev8-bi-source-list">
                <?php foreach ($sources as $source) : ?>
                    <li><code><?php echo esc_html((string) $source); ?></code></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($notes !== []) : ?>
            <h3><?php esc_html_e('Notes', 'elev8-os'); ?></h3>
            <ul class="elev8-bi-note-list">
                <?php foreach ($notes as $note) : ?>
                    <li><?php echo esc_html((string) $note); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php
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

    private static function format_class_date(string $raw_date): string {
        if ($raw_date === '') {
            return __('Unknown date', 'elev8-os');
        }

        $timestamp = strtotime($raw_date);
        if ($timestamp === false) {
            return $raw_date;
        }

        return wp_date(
            get_option('date_format') . ' ' . get_option('time_format'),
            $timestamp
        );
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
            <h1><?php esc_html_e('Business Intelligence', 'elev8-os'); ?></h1>
            <div class="notice notice-error">
                <p>
                    <?php
                    esc_html_e(
                        'The Business Intelligence service could not be loaded. Confirm that the service file is present and included before this module.',
                        'elev8-os'
                    );
                    ?>
                </p>
            </div>
        </div>
        <?php
    }
}

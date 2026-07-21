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

        $workflow_summary = class_exists('Elev8_OS_Workflow_Service')
            ? Elev8_OS_Workflow_Service::summary(7)
            : ['available' => false];
        $operational_priorities = class_exists('Elev8_OS_Dashboard_Service')
            ? Elev8_OS_Dashboard_Service::priorities($operational_summary)
            : [];
        $daily_brief = class_exists('Elev8_OS_Daily_Brief_Service')
            ? Elev8_OS_Daily_Brief_Service::build($operational_summary, $metrics, wp_get_current_user())
            : [];

        $executive_intelligence = class_exists('Elev8_OS_Executive_Intelligence_Service')
            ? Elev8_OS_Executive_Intelligence_Service::build($operational_summary, $metrics)
            : [
                'brief' => [],
                'decisions' => [],
                'wins' => [],
                'timeline' => [],
                'opportunities' => [],
            ];

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

            <?php self::render_daily_brief($daily_brief); ?>

            <?php if (class_exists('Elev8_OS_Coaching_Service')) { Elev8_OS_Coaching_Service::render(wp_get_current_user(), __('CEO Recommended Next Actions', 'elev8-os')); } ?>

            <?php self::render_operational_home($operational_summary, $operational_priorities, $executive_intelligence); ?>


            <?php self::render_workflow_health($workflow_summary); ?>

            <section class="elev8-ceo-launchpad elev8-bi-section" aria-labelledby="elev8-ceo-launchpad-heading">
                <div class="elev8-bi-section__heading">
                    <div>
                        <p class="elev8-bi-eyebrow"><?php esc_html_e('CEO Tools', 'elev8-os'); ?></p>
                        <h2 id="elev8-ceo-launchpad-heading"><?php esc_html_e('Open a Workspace', 'elev8-os'); ?></h2>
                        <p><?php esc_html_e('Use these when you need to go deeper. The Attention Center above should be your first stop.', 'elev8-os'); ?></p>
                    </div>
                </div>
                <div class="elev8-ceo-launchpad__grid">
                    <?php self::render_command_card('clipboard', __('Business Memory', 'elev8-os'), __('Operating logs, owner notes, issues, and follow-up.', 'elev8-os'), admin_url('admin.php?page=' . self::PAGE_SLUG . '&view=memory')); ?>
                    <?php self::render_command_card('chart-area', __('Business Intelligence', 'elev8-os'), __('Verified KPIs, confidence, trends, and decision support.', 'elev8-os'), admin_url('admin.php?page=elev8-business-intelligence')); ?>
                    <?php self::render_command_card('lightbulb', __('Class Requests', 'elev8-os'), __('Customer demand and potential class opportunities.', 'elev8-os'), admin_url('admin.php?page=' . self::PAGE_SLUG . '&view=class-requests')); ?>
                    <?php self::render_command_card('megaphone', __('Opportunities', 'elev8-os'), __('Actions that can help artists and the business grow.', 'elev8-os'), admin_url('admin.php?page=' . self::PAGE_SLUG . '&view=opportunities')); ?>
                </div>
                <div class="elev8-ceo-tool-row" aria-label="<?php esc_attr_e('Additional CEO tools', 'elev8-os'); ?>">
                    <?php if (class_exists('Elev8_OS_Bingo_Reservations_Module')) : ?><a href="<?php echo esc_url(Elev8_OS_Bingo_Reservations_Module::admin_url()); ?>"><span class="dashicons dashicons-tickets-alt"></span><?php esc_html_e('Reservations', 'elev8-os'); ?></a><?php endif; ?>
                    <?php if (class_exists('Elev8_OS_Event_Applications_Module')) : ?><a href="<?php echo esc_url(Elev8_OS_Event_Applications_Module::admin_url()); ?>"><span class="dashicons dashicons-forms"></span><?php esc_html_e('Event Applications', 'elev8-os'); ?></a><?php endif; ?>
                    <?php if (class_exists('Elev8_OS_Work_Module')) : ?><a href="<?php echo esc_url(Elev8_OS_Work_Module::team_url()); ?>"><span class="dashicons dashicons-list-view"></span><?php esc_html_e('Work', 'elev8-os'); ?></a><?php endif; ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=elev8-growth-center')); ?>"><span class="dashicons dashicons-chart-line"></span><?php esc_html_e('Growth', 'elev8-os'); ?></a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=elev8-system-inspector')); ?>"><span class="dashicons dashicons-admin-tools"></span><?php esc_html_e('System Health', 'elev8-os'); ?></a>
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


    /** Render the rule-based morning reading brief. */
    private static function render_daily_brief(array $brief): void {
        if (!$brief) { return; }
        $pulse = is_array($brief['pulse'] ?? null) ? $brief['pulse'] : [];
        $confidence = is_array($brief['confidence'] ?? null) ? $brief['confidence'] : [];
        $explanations = is_array($brief['explanations'] ?? null) ? $brief['explanations'] : [];
        ?>
        <section class="elev8-daily-brief" aria-labelledby="elev8-daily-brief-heading">
            <header class="elev8-daily-brief__header">
                <div>
                    <p class="elev8-bi-eyebrow"><?php esc_html_e('Daily Executive Brief', 'elev8-os'); ?></p>
                    <h2 id="elev8-daily-brief-heading"><?php echo esc_html((string) ($brief['greeting'] ?? __('Good day.', 'elev8-os'))); ?></h2>
                    <p><?php echo esc_html((string) ($brief['date_label'] ?? '')); ?></p>
                </div>
                <div class="elev8-daily-brief__confidence">
                    <span><?php esc_html_e('Confidence', 'elev8-os'); ?></span>
                    <strong><?php echo esc_html((string) ($confidence['label'] ?? __('Unavailable', 'elev8-os'))); ?></strong>
                    <small><?php echo esc_html(sprintf(__('%d trusted sources', 'elev8-os'), (int) ($confidence['sources'] ?? 0))); ?></small>
                    <?php self::render_why($explanations['confidence'] ?? []); ?>
                </div>
            </header>

            <div class="elev8-daily-brief__pulse elev8-daily-brief__pulse--<?php echo esc_attr(sanitize_html_class((string) ($pulse['status'] ?? 'healthy'))); ?>">
                <span><?php esc_html_e('Business Pulse', 'elev8-os'); ?></span>
                <strong><?php echo esc_html((string) ($pulse['label'] ?? __('Unavailable', 'elev8-os'))); ?></strong>
                <p><?php echo esc_html((string) ($pulse['reason'] ?? __('Unavailable', 'elev8-os'))); ?></p>
                <?php self::render_why($explanations['pulse'] ?? []); ?>
            </div>

            <div class="elev8-daily-brief__grid">
                <?php self::render_brief_list(__('Yesterday', 'elev8-os'), (array) ($brief['yesterday'] ?? []), 'calendar-alt', $explanations['yesterday'] ?? []); ?>
                <?php self::render_attention_brief((array) ($brief['attention'] ?? [])); ?>
                <?php self::render_brief_list(__('Wins', 'elev8-os'), (array) ($brief['wins'] ?? []), 'awards'); ?>
                <?php self::render_brief_list(__('Risks', 'elev8-os'), (array) ($brief['risks'] ?? []), 'warning'); ?>
            </div>

            <div class="elev8-daily-brief__bottom">
                <div class="elev8-daily-brief__panel">
                    <h3><span class="dashicons dashicons-lightbulb"></span><?php esc_html_e('Today’s Opportunities', 'elev8-os'); ?></h3>
                    <ul><?php foreach ((array) ($brief['opportunities'] ?? []) as $item) : ?><li><?php if (!empty($item['url'])) : ?><a href="<?php echo esc_url((string) $item['url']); ?>"><?php echo esc_html((string) ($item['title'] ?? '')); ?></a><?php else : ?><?php echo esc_html((string) ($item['title'] ?? '')); ?><?php endif; ?></li><?php endforeach; ?></ul>
                </div>
                <div class="elev8-daily-brief__panel elev8-daily-brief__focus">
                    <h3><span class="dashicons dashicons-flag"></span><?php esc_html_e('Today’s Focus', 'elev8-os'); ?></h3>
                    <ol><?php foreach ((array) ($brief['focus'] ?? []) as $item) : ?><li><?php echo esc_html((string) $item); ?></li><?php endforeach; ?></ol>
                </div>
            </div>

            <?php if (!empty($brief['timeline'])) : ?>
                <details class="elev8-daily-brief__timeline">
                    <summary><?php esc_html_e('View Yesterday’s Timeline', 'elev8-os'); ?></summary>
                    <div><?php foreach ((array) $brief['timeline'] as $item) : ?><article><time><?php echo esc_html((string) ($item['time'] ?? '')); ?></time><span><strong><?php echo esc_html((string) ($item['title'] ?? '')); ?></strong><small><?php echo esc_html((string) ($item['detail'] ?? '')); ?></small></span></article><?php endforeach; ?></div>
                </details>
            <?php endif; ?>
        </section>
        <?php
    }

    private static function render_brief_list(string $title, array $items, string $icon, array $why = []): void {
        ?>
        <article class="elev8-daily-brief__panel">
            <h3><span class="dashicons dashicons-<?php echo esc_attr($icon); ?>"></span><?php echo esc_html($title); ?></h3>
            <ul><?php foreach ($items as $item) : ?><li><?php echo esc_html((string) $item); ?></li><?php endforeach; ?></ul>
            <?php self::render_why($why); ?>
        </article>
        <?php
    }

    private static function render_attention_brief(array $items): void {
        ?>
        <article class="elev8-daily-brief__panel">
            <h3><span class="dashicons dashicons-bell"></span><?php esc_html_e('Needs Your Attention', 'elev8-os'); ?></h3>
            <?php if ($items) : ?><ul><?php foreach ($items as $item) : ?><li class="is-<?php echo esc_attr(sanitize_html_class((string) ($item['severity'] ?? 'normal'))); ?>"><?php if (!empty($item['url'])) : ?><a href="<?php echo esc_url((string) $item['url']); ?>"><?php echo esc_html((string) ($item['title'] ?? '')); ?></a><?php else : ?><?php echo esc_html((string) ($item['title'] ?? '')); ?><?php endif; ?><?php if (!empty($item['summary'])) : ?><small><?php echo esc_html((string) $item['summary']); ?></small><?php endif; ?></li><?php endforeach; ?></ul><?php else : ?><p><?php esc_html_e('No verified urgent items are waiting.', 'elev8-os'); ?></p><?php endif; ?>
        </article>
        <?php
    }

    private static function render_why(array $why): void {
        if (!$why) { return; }
        ?><details class="elev8-why"><summary><?php esc_html_e('Why?', 'elev8-os'); ?></summary><div><strong><?php echo esc_html((string) ($why['title'] ?? __('Why?', 'elev8-os'))); ?></strong><p><?php echo esc_html((string) ($why['body'] ?? __('Unavailable', 'elev8-os'))); ?></p></div></details><?php
    }

    /**
     * Render the role-aware Operational Home using verified shared services.
     *
     * @param array<string,mixed> $summary
     * @param array<int,array<string,string|int>> $priorities
     */
    private static function render_operational_home(array $summary, array $priorities, array $intelligence): void {
        $attention = is_array($summary['attention'] ?? null) ? $summary['attention'] : [];
        $total = (int) ($attention['total'] ?? 0);
        $critical = (int) ($attention['critical'] ?? 0);
        $high = (int) ($attention['high'] ?? 0);
        $pulse_class = $critical > 0 ? 'is-critical' : ($high > 0 || $total > 0 ? 'is-busy' : 'is-healthy');
        $pulse_label = $critical > 0
            ? __('Action Required', 'elev8-os')
            : ($total > 0 ? __('Needs Attention', 'elev8-os') : __('Healthy', 'elev8-os'));
        $brief = is_array($intelligence['brief'] ?? null) ? $intelligence['brief'] : [];
        $decisions = is_array($intelligence['decisions'] ?? null) ? $intelligence['decisions'] : [];
        $wins = is_array($intelligence['wins'] ?? null) ? $intelligence['wins'] : [];
        $timeline = is_array($intelligence['timeline'] ?? null) ? $intelligence['timeline'] : [];
        $opportunities = is_array($intelligence['opportunities'] ?? null) ? $intelligence['opportunities'] : [];
        ?>
        <section class="elev8-executive-brief" aria-labelledby="elev8-executive-brief-heading">
            <div class="elev8-executive-brief__main">
                <p class="elev8-bi-eyebrow"><?php esc_html_e('Executive Brief', 'elev8-os'); ?></p>
                <h2 id="elev8-executive-brief-heading"><?php echo esc_html((string) ($brief['greeting'] ?? __('Good day, Steve.', 'elev8-os'))); ?></h2>
                <h3><?php echo esc_html((string) ($brief['headline'] ?? __('Your verified business briefing is ready.', 'elev8-os'))); ?></h3>
                <?php if (!empty($brief['lines']) && is_array($brief['lines'])) : ?>
                    <ul class="elev8-executive-brief__list">
                        <?php foreach ($brief['lines'] as $line) : ?><li><?php echo esc_html((string) $line); ?></li><?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <div class="elev8-business-pulse <?php echo esc_attr($pulse_class); ?>">
                <span><?php esc_html_e('Business Pulse', 'elev8-os'); ?></span>
                <strong><?php echo esc_html($pulse_label); ?></strong>
                <small><?php echo esc_html($total > 0 ? sprintf(_n('%d verified item is waiting.', '%d verified items are waiting.', $total, 'elev8-os'), $total) : __('No verified urgent items are waiting.', 'elev8-os')); ?></small>
            </div>
        </section>

        <section class="elev8-decision-center" aria-labelledby="elev8-decision-heading">
            <div class="elev8-attention-center__header">
                <div>
                    <p class="elev8-bi-eyebrow"><?php esc_html_e('Waiting on You', 'elev8-os'); ?></p>
                    <h2 id="elev8-decision-heading"><?php esc_html_e('CEO Decision Queue', 'elev8-os'); ?></h2>
                    <p><?php esc_html_e('The verified notes, reviews, and work items that need your direction next.', 'elev8-os'); ?></p>
                </div>
                <div class="elev8-attention-total"><strong><?php echo esc_html((string) count($decisions)); ?></strong><span><?php esc_html_e('decisions', 'elev8-os'); ?></span></div>
            </div>
            <?php if ($decisions) : ?>
                <div class="elev8-decision-list">
                    <?php foreach ($decisions as $decision) :
                        $severity = sanitize_html_class((string) ($decision['severity'] ?? 'normal'));
                        $created_at = (string) ($decision['created_at'] ?? '');
                        $timestamp = $created_at !== '' ? strtotime($created_at) : false;
                    ?>
                        <article class="elev8-decision-card elev8-decision-card--<?php echo esc_attr($severity); ?>">
                            <span class="dashicons dashicons-<?php echo esc_attr((string) ($decision['icon'] ?? 'yes-alt')); ?>" aria-hidden="true"></span>
                            <div class="elev8-decision-card__body">
                                <div class="elev8-decision-card__meta"><strong><?php echo esc_html(ucfirst($severity)); ?></strong><?php if ($timestamp) : ?><span><?php echo esc_html(human_time_diff($timestamp, current_time('timestamp')) . ' ' . __('ago', 'elev8-os')); ?></span><?php endif; ?></div>
                                <h3><?php echo esc_html((string) ($decision['title'] ?? __('Decision required', 'elev8-os'))); ?></h3>
                                <?php if (!empty($decision['summary'])) : ?><p><?php echo esc_html((string) $decision['summary']); ?></p><?php endif; ?>
                                <?php if (!empty($decision['source'])) : ?><small><?php echo esc_html((string) $decision['source']); ?></small><?php endif; ?>
                            </div>
                            <a class="button button-primary" href="<?php echo esc_url((string) ($decision['url'] ?? '')); ?>"><?php echo esc_html((string) ($decision['action'] ?? __('Review', 'elev8-os'))); ?></a>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <div class="elev8-attention-empty"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span><div><strong><?php esc_html_e('No verified decisions are waiting.', 'elev8-os'); ?></strong><p><?php esc_html_e('New manager notes, applications, reservations, and overdue work will appear here automatically.', 'elev8-os'); ?></p></div></div>
            <?php endif; ?>
        </section>

        <div class="elev8-executive-grid">
            <?php self::render_intelligence_panel(__('Recent Wins', 'elev8-os'), __('Verified progress worth recognizing.', 'elev8-os'), $wins, 'awards'); ?>
            <?php self::render_intelligence_panel(__('Opportunities', 'elev8-os'), __('Verified next moves that can build momentum.', 'elev8-os'), $opportunities, 'lightbulb'); ?>
        </div>

        <section class="elev8-executive-timeline" aria-labelledby="elev8-executive-timeline-heading">
            <div class="elev8-bi-section__heading"><div><p class="elev8-bi-eyebrow"><?php esc_html_e('What Changed', 'elev8-os'); ?></p><h2 id="elev8-executive-timeline-heading"><?php esc_html_e('Executive Timeline', 'elev8-os'); ?></h2><p><?php esc_html_e('Recent verified activity currently represented in the shared Attention Center.', 'elev8-os'); ?></p></div></div>
            <?php if ($timeline) : ?><div class="elev8-timeline-list">
                <?php foreach ($timeline as $entry) : $ts = strtotime((string) ($entry['created_at'] ?? '')); ?>
                    <a href="<?php echo esc_url((string) ($entry['url'] ?? '')); ?>" class="elev8-timeline-item"><span class="dashicons dashicons-<?php echo esc_attr((string) ($entry['icon'] ?? 'clock')); ?>"></span><time><?php echo esc_html($ts ? wp_date(get_option('time_format'), $ts) : __('Unavailable', 'elev8-os')); ?></time><span><strong><?php echo esc_html((string) ($entry['title'] ?? __('Activity', 'elev8-os'))); ?></strong><small><?php echo esc_html((string) ($entry['source'] ?? __('Elev8 OS', 'elev8-os'))); ?></small></span></a>
                <?php endforeach; ?>
            </div><?php else : ?><p class="elev8-bi-metric__diagnostic"><?php esc_html_e('No verified recent activity is available yet.', 'elev8-os'); ?></p><?php endif; ?>
        </section>
        <?php
    }

    /** @param array<int,array<string,mixed>> $items */
    /** Render the first visible Workflow Engine health card. */
    private static function render_workflow_health(array $summary): void {
        $available = !empty($summary['available']);
        $failed = (int)($summary['failed'] ?? 0);
        $status = !$available ? __('Unavailable', 'elev8-os') : ($failed > 0 ? __('Needs Attention', 'elev8-os') : __('Healthy', 'elev8-os'));
        $explanation = class_exists('Elev8_OS_Explanation_Service')
            ? Elev8_OS_Explanation_Service::workflow_health($summary)
            : ['title'=>__('Why?', 'elev8-os'),'body'=>(string)($summary['why'] ?? '')];
        ?>
        <section class="elev8-bi-section elev8-workflow-health" aria-labelledby="elev8-workflow-health-heading">
            <div class="elev8-bi-section__heading">
                <div>
                    <p class="elev8-bi-eyebrow"><?php esc_html_e('Workflow Engine', 'elev8-os'); ?></p>
                    <h2 id="elev8-workflow-health-heading"><?php esc_html_e('Business Coordination', 'elev8-os'); ?></h2>
                    <p><?php esc_html_e('Verified system events are beginning to route themselves into the next operational action.', 'elev8-os'); ?></p>
                </div>
                <span class="elev8-workflow-health__status <?php echo $failed > 0 ? 'is-warning' : 'is-healthy'; ?>"><?php echo esc_html($status); ?></span>
            </div>
            <div class="elev8-workflow-health__grid">
                <div><strong><?php echo $available ? esc_html((string)($summary['active_workflows'] ?? 0)) : esc_html__('Unavailable', 'elev8-os'); ?></strong><span><?php esc_html_e('Active workflows', 'elev8-os'); ?></span></div>
                <div><strong><?php echo $available ? esc_html((string)($summary['runs'] ?? 0)) : esc_html__('Unavailable', 'elev8-os'); ?></strong><span><?php esc_html_e('Runs in 7 days', 'elev8-os'); ?></span></div>
                <div><strong><?php echo $available ? esc_html((string)($summary['completed'] ?? 0)) : esc_html__('Unavailable', 'elev8-os'); ?></strong><span><?php esc_html_e('Completed', 'elev8-os'); ?></span></div>
                <div><strong><?php echo $available ? esc_html((string)$failed) : esc_html__('Unavailable', 'elev8-os'); ?></strong><span><?php esc_html_e('Failed', 'elev8-os'); ?></span></div>
            </div>
            <details class="elev8-why">
                <summary><?php esc_html_e('Why?', 'elev8-os'); ?></summary>
                <div><strong><?php echo esc_html((string)$explanation['title']); ?></strong><p><?php echo esc_html((string)$explanation['body']); ?></p></div>
            </details>
        </section>
        <?php
    }

    private static function render_intelligence_panel(string $title, string $description, array $items, string $icon): void {
        ?>
        <section class="elev8-intelligence-panel">
            <div class="elev8-intelligence-panel__heading"><span class="dashicons dashicons-<?php echo esc_attr($icon); ?>"></span><div><h2><?php echo esc_html($title); ?></h2><p><?php echo esc_html($description); ?></p></div></div>
            <?php if ($items) : ?><div class="elev8-intelligence-panel__items">
                <?php foreach ($items as $item) : $url = (string) ($item['url'] ?? ''); ?>
                    <<?php echo $url !== '' ? 'a href="' . esc_url($url) . '"' : 'div'; ?> class="elev8-intelligence-item">
                        <span class="dashicons dashicons-<?php echo esc_attr((string) ($item['icon'] ?? 'yes-alt')); ?>"></span><span><strong><?php echo esc_html((string) ($item['title'] ?? __('Unavailable', 'elev8-os'))); ?></strong><small><?php echo esc_html((string) ($item['detail'] ?? '')); ?></small></span>
                    </<?php echo $url !== '' ? 'a' : 'div'; ?>>
                <?php endforeach; ?>
            </div><?php else : ?><p class="elev8-bi-metric__diagnostic"><?php esc_html_e('Unavailable', 'elev8-os'); ?></p><?php endif; ?>
        </section>
        <?php
    }

    private static function render_command_card(string $icon, string $title, string $description, string $url): void {
        ?>
        <a class="elev8-ceo-command-card" href="<?php echo esc_url($url); ?>">
            <span class="elev8-ceo-command-card__icon dashicons dashicons-<?php echo esc_attr($icon); ?>" aria-hidden="true"></span>
            <span class="elev8-ceo-command-card__body"><strong><?php echo esc_html($title); ?></strong><small><?php echo esc_html($description); ?></small></span>
            <span class="elev8-ceo-command-card__action"><?php esc_html_e('Open', 'elev8-os'); ?> <span class="dashicons dashicons-arrow-right-alt2"></span></span>
        </a>
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
            <?php $why = class_exists('Elev8_OS_Explanation_Service') ? Elev8_OS_Explanation_Service::metric($label, $metric) : ['title'=>__('Why?', 'elev8-os'),'body'=>$diagnostic]; ?>
            <details class="elev8-why elev8-why--metric">
                <summary><?php esc_html_e('Why?', 'elev8-os'); ?></summary>
                <div><strong><?php echo esc_html((string)$why['title']); ?></strong><p><?php echo esc_html((string)$why['body']); ?></p></div>
            </details>
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

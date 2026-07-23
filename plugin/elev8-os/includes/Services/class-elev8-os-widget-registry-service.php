<?php
/**
 * Shared widget registry for Elev8 OS workspaces.
 * Widgets expose presentation-ready projections without owning engine data.
 */
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Widget_Registry_Service {
    private static $widgets = [];

    public static function init(): void {
        add_action('init', [__CLASS__, 'register_core_widgets'], 20);
    }

    public static function register(string $id, array $definition): void {
        $id = sanitize_key($id);
        if ($id === '') { return; }
        $defaults = [
            'label' => $id,
            'description' => '',
            'engine' => 'platform',
            'size' => 'medium',
            'priority' => 50,
            'capability' => '',
            'render_callback' => null,
            'data_callback' => null,
        ];
        $definition = wp_parse_args($definition, $defaults);
        $definition['id'] = $id;
        self::$widgets[$id] = $definition;
    }

    public static function get(string $id): ?array {
        $id = sanitize_key($id);
        return isset(self::$widgets[$id]) ? self::$widgets[$id] : null;
    }

    public static function all(): array {
        $widgets = apply_filters('elev8_os_widgets', self::$widgets);
        uasort($widgets, static function(array $a, array $b): int {
            return ((int) $a['priority']) <=> ((int) $b['priority']);
        });
        return $widgets;
    }

    public static function can_view(array $widget, ?WP_User $user = null): bool {
        $user = $user ?: wp_get_current_user();
        $capability = sanitize_key((string) ($widget['capability'] ?? ''));
        if ($capability === '') { return is_user_logged_in(); }
        if (class_exists('Elev8_OS_Access_Service')) {
            return Elev8_OS_Access_Service::user_can($capability, $user);
        }
        return user_can($user, $capability);
    }

    public static function render(string $id, array $context = []): string {
        $widget = self::get($id);
        if (!$widget || !self::can_view($widget, $context['user'] ?? null)) { return ''; }
        $data = [];
        if (is_callable($widget['data_callback'])) {
            $data = (array) call_user_func($widget['data_callback'], $context, $widget);
        }
        ob_start();
        if (is_callable($widget['render_callback'])) {
            call_user_func($widget['render_callback'], $data, $context, $widget);
        } else {
            self::render_placeholder($data, $context, $widget);
        }
        return (string) ob_get_clean();
    }

    public static function register_core_widgets(): void {
        self::register('workspace_welcome', [
            'label' => __('Workspace Welcome', 'elev8-os'),
            'description' => __('Role-aware introduction and current workspace context.', 'elev8-os'),
            'engine' => 'organization',
            'size' => 'wide',
            'priority' => 10,
            'data_callback' => static function(array $context): array {
                $user = $context['user'] ?? wp_get_current_user();
                return [
                    'title' => sprintf(__('Welcome, %s', 'elev8-os'), $user instanceof WP_User ? $user->display_name : ''),
                    'body' => (string) ($context['workspace']['description'] ?? __('Your operating workspace is ready.', 'elev8-os')),
                ];
            },
        ]);
        self::register('quick_actions', [
            'label' => __('Quick Actions', 'elev8-os'),
            'description' => __('High-value actions supplied by the current workspace.', 'elev8-os'),
            'engine' => 'workflow',
            'size' => 'wide',
            'priority' => 20,
            'render_callback' => [__CLASS__, 'render_quick_actions'],
        ]);
        self::register('retail_shift', [
            'label' => __('Retail Shift', 'elev8-os'),
            'description' => __('The essential starting point for a retail employee shift.', 'elev8-os'),
            'engine' => 'operations',
            'size' => 'wide',
            'priority' => 15,
            'capability' => 'submit_retail_log',
            'render_callback' => [__CLASS__, 'render_retail_shift'],
        ]);
        self::register('studio_pulse', [
            'label' => __('Studio Pulse', 'elev8-os'),
            'description' => __('Verified production, deadline, and pay signals from Glass Operations.', 'elev8-os'),
            'engine' => 'operations',
            'size' => 'wide',
            'priority' => 15,
            'capability' => 'view_glass_dashboard',
            'data_callback' => [__CLASS__, 'studio_pulse_data'],
            'render_callback' => [__CLASS__, 'render_studio_pulse'],
        ]);
        self::register('report_problem', [
            'label' => __('Report a Problem', 'elev8-os'),
            'description' => __('Universal product and experience feedback intake.', 'elev8-os'),
            'engine' => 'intelligence',
            'size' => 'small',
            'priority' => 90,
            'render_callback' => [__CLASS__, 'render_report_problem'],
        ]);
    }

    public static function render_placeholder(array $data, array $context, array $widget): void {
        $title = (string) ($data['title'] ?? $widget['label']);
        $body = (string) ($data['body'] ?? $widget['description']);
        ?>
        <article class="elev8-workspace-widget" data-elev8-widget="<?php echo esc_attr($widget['id']); ?>">
            <span class="elev8-workspace-widget__engine"><?php echo esc_html(strtoupper((string) $widget['engine'])); ?></span>
            <h2><?php echo esc_html($title); ?></h2>
            <?php if ($body !== '') : ?><p><?php echo esc_html($body); ?></p><?php endif; ?>
        </article>
        <?php
    }

    public static function render_quick_actions(array $data, array $context, array $widget): void {
        $actions = (array) ($context['workspace']['actions'] ?? []);
        ?>
        <article class="elev8-workspace-widget" data-elev8-widget="quick_actions">
            <span class="elev8-workspace-widget__engine"><?php esc_html_e('WORKFLOW', 'elev8-os'); ?></span>
            <h2><?php esc_html_e('Quick Actions', 'elev8-os'); ?></h2>
            <div class="elev8-workspace-actions">
                <?php foreach ($actions as $action) :
                    $url = (string) ($action['url'] ?? '');
                    $label = (string) ($action['label'] ?? '');
                    if ($url === '' || $label === '') { continue; }
                    ?>
                    <a class="elev8-ui-button" href="<?php echo esc_url($url); ?>"><?php echo esc_html($label); ?></a>
                <?php endforeach; ?>
                <?php if (!$actions) : ?><p><?php esc_html_e('No quick actions are configured for this workspace yet.', 'elev8-os'); ?></p><?php endif; ?>
            </div>
        </article>
        <?php
    }


    public static function studio_pulse_data(array $context): array {
        if (!class_exists('Elev8_OS_Glass_Operations_Service')) { return []; }
        return Elev8_OS_Glass_Operations_Service::summary();
    }

    public static function render_studio_pulse(array $data, array $context, array $widget): void {
        $items = [
            ['value' => (int) ($data['open_jobs'] ?? 0), 'label' => __('Open jobs', 'elev8-os')],
            ['value' => (int) ($data['overdue'] ?? 0), 'label' => __('Overdue', 'elev8-os')],
            ['value' => (int) ($data['cremation_ready'] ?? 0), 'label' => __('Memorial jobs ready', 'elev8-os')],
            ['value' => '$' . number_format_i18n((float) ($data['pending_payout'] ?? 0), 2), 'label' => __('Pending pay', 'elev8-os')],
        ];
        ?>
        <article class="elev8-workspace-widget" data-elev8-widget="studio_pulse">
            <span class="elev8-workspace-widget__engine"><?php esc_html_e('OPERATIONS', 'elev8-os'); ?></span>
            <h2><?php esc_html_e('Studio Pulse', 'elev8-os'); ?></h2>
            <div class="elev8-workspace-metrics">
                <?php foreach ($items as $item): ?><div><strong><?php echo esc_html((string) $item['value']); ?></strong><span><?php echo esc_html((string) $item['label']); ?></span></div><?php endforeach; ?>
            </div>
            <?php if (class_exists('Elev8_OS_Glass_Manager_Suite_Module')): ?><a class="elev8-ui-button" href="<?php echo esc_url(Elev8_OS_Glass_Manager_Suite_Module::url()); ?>"><?php esc_html_e('Open Glass Operations', 'elev8-os'); ?></a><?php endif; ?>
        </article>
        <?php
    }

    public static function render_retail_shift(array $data, array $context, array $widget): void {
        $log_url = class_exists('Elev8_OS_Checkin_Center_Module') ? add_query_arg(['type'=>'retail','team'=>'1'], Elev8_OS_Checkin_Center_Module::page_url()) : home_url('/');
        $actions_url = home_url('/elev8-actions/');
        $messages_url = home_url('/elev8-conversations/');
        ?>
        <article class="elev8-workspace-widget" data-elev8-widget="retail_shift">
            <span class="elev8-workspace-widget__engine"><?php esc_html_e('OPERATIONS', 'elev8-os'); ?></span>
            <h2><?php esc_html_e('Ready for your shift', 'elev8-os'); ?></h2>
            <p><?php esc_html_e('Use this workspace for today’s work—not your public profile. Start your retail log, check assigned actions, and open team messages.', 'elev8-os'); ?></p>
            <div class="elev8-workspace-actions">
                <a class="elev8-ui-button elev8-ui-button--primary" href="<?php echo esc_url($log_url); ?>"><?php esc_html_e('Start Retail Log', 'elev8-os'); ?></a>
                <a class="elev8-ui-button" href="<?php echo esc_url($actions_url); ?>"><?php esc_html_e('My Actions', 'elev8-os'); ?></a>
                <a class="elev8-ui-button" href="<?php echo esc_url($messages_url); ?>"><?php esc_html_e('Messages', 'elev8-os'); ?></a>
            </div>
        </article>
        <?php
    }

    public static function render_report_problem(array $data, array $context, array $widget): void {
        $return_to = class_exists('Elev8_OS_Problem_Report_Module') ? Elev8_OS_Problem_Report_Module::current_request_url() : '';
        $url = class_exists('Elev8_OS_Problem_Report_Module') ? Elev8_OS_Problem_Report_Module::page_url($return_to) : home_url('/report-a-problem/');
        ?>
        <article class="elev8-workspace-widget elev8-workspace-widget--attention" data-elev8-widget="report_problem">
            <span class="elev8-workspace-widget__engine"><?php esc_html_e('PRODUCT INTELLIGENCE', 'elev8-os'); ?></span>
            <h2><?php esc_html_e('Something not working?', 'elev8-os'); ?></h2>
            <p><?php esc_html_e('Report it once. Elev8 OS will capture the current page and help group repeated issues.', 'elev8-os'); ?></p>
            <a class="elev8-ui-button elev8-ui-button--primary" href="<?php echo esc_url($url); ?>"><?php esc_html_e('Report a Problem', 'elev8-os'); ?></a>
        </article>
        <?php
    }
}

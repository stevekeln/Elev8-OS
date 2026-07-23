<?php
if (!defined('ABSPATH')) { exit; }

/** Personal Daily Assistant preferences and governed delivery through Communication and Automation. */
final class Elev8_OS_Daily_Assistant_Delivery_Service {
    public const CRON_HOOK = 'elev8_os_deliver_daily_assistant_briefings';
    private const META_SETTINGS = 'elev8_os_daily_assistant_delivery';
    private const META_LAST_EMAIL = 'elev8_os_daily_assistant_last_email';
    private const META_LAST_IN_APP = 'elev8_os_daily_assistant_last_in_app';

    public static function init(): void {
        add_action('init', [__CLASS__, 'ensure_schedule']);
        add_action(self::CRON_HOOK, [__CLASS__, 'run']);
        add_filter('elev8_os_business_graph_relationships', [__CLASS__, 'register_graph_relationships']);
    }

    public static function ensure_schedule(): void {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 300, 'hourly', self::CRON_HOOK);
        }
    }

    /** @return array<string,mixed> */
    public static function settings(int $user_id): array {
        $stored = get_user_meta($user_id, self::META_SETTINGS, true);
        $stored = is_array($stored) ? $stored : [];
        return wp_parse_args($stored, [
            'enabled' => false,
            'hour' => 8,
            'days' => 'weekdays',
            'channels' => ['in_app'],
            'categories' => ['work','conversations','attention','coaching'],
            'reminders' => 'overdue',
        ]);
    }

    public static function save_settings(int $user_id, array $input): void {
        if ($user_id <= 0) { return; }
        $days = sanitize_key((string) ($input['days'] ?? 'weekdays'));
        if (!in_array($days, ['daily','weekdays'], true)) { $days = 'weekdays'; }
        $channels = array_values(array_intersect(['in_app','email'], array_map('sanitize_key', (array) ($input['channels'] ?? []))));
        if (!$channels) { $channels = ['in_app']; }
        $categories = array_values(array_intersect(['work','conversations','attention','coaching'], array_map('sanitize_key', (array) ($input['categories'] ?? []))));
        if (!$categories) { $categories = ['work','conversations','attention','coaching']; }
        $reminders = sanitize_key((string) ($input['reminders'] ?? 'overdue'));
        if (!in_array($reminders, ['none','overdue','all_focus'], true)) { $reminders = 'overdue'; }
        update_user_meta($user_id, self::META_SETTINGS, [
            'enabled' => !empty($input['enabled']),
            'hour' => max(0, min(23, (int) ($input['hour'] ?? 8))),
            'days' => $days,
            'channels' => $channels,
            'categories' => $categories,
            'reminders' => $reminders,
        ]);
    }

    public static function run(): void {
        foreach (get_users(['fields'=>'all']) as $user) {
            if (!$user instanceof WP_User || !$user->ID) { continue; }
            $settings = self::settings((int) $user->ID);
            if (empty($settings['enabled']) || !self::is_due($user, $settings)) { continue; }
            self::deliver($user, $settings);
        }
    }

    /** @param array<string,mixed>|null $settings */
    public static function deliver(WP_User $user, ?array $settings = null): array {
        $settings = $settings ?: self::settings((int) $user->ID);
        $brief = Elev8_OS_Proactive_Daily_Assistant_Service::briefing($user, (array) ($settings['categories'] ?? []));
        $result = ['email'=>false,'in_app'=>false];
        $channels = (array) ($settings['channels'] ?? ['in_app']);
        if (in_array('in_app', $channels, true)) {
            update_user_meta((int) $user->ID, self::META_LAST_IN_APP, current_time('mysql'));
            $result['in_app'] = true;
        }
        if (in_array('email', $channels, true) && is_email($user->user_email)) {
            $result['email'] = Elev8_OS_Notification_Service::send_email(
                $user->user_email,
                sprintf(__('Your Elev8 OS focus for %s', 'elev8-os'), wp_date(get_option('date_format'))),
                self::html($user, $brief, $settings),
                ['Content-Type: text/html; charset=UTF-8']
            );
            if ($result['email']) { update_user_meta((int) $user->ID, self::META_LAST_EMAIL, current_time('mysql')); }
        }
        do_action('elev8_os_daily_assistant_delivered', (int) $user->ID, $brief, $settings, $result);
        return $result;
    }

    /** @return array<string,string> */
    public static function delivery_status(int $user_id): array {
        return [
            'last_email' => (string) get_user_meta($user_id, self::META_LAST_EMAIL, true),
            'last_in_app' => (string) get_user_meta($user_id, self::META_LAST_IN_APP, true),
        ];
    }

    /** @param array<string,mixed> $settings */
    private static function is_due(WP_User $user, array $settings): bool {
        if ((int) current_time('G') !== (int) ($settings['hour'] ?? 8)) { return false; }
        if (($settings['days'] ?? 'weekdays') === 'weekdays' && in_array((int) current_time('N'), [6,7], true)) { return false; }
        $last = max(
            (string) get_user_meta((int) $user->ID, self::META_LAST_EMAIL, true),
            (string) get_user_meta((int) $user->ID, self::META_LAST_IN_APP, true)
        );
        return $last === '' || substr($last, 0, 10) !== current_time('Y-m-d');
    }

    /** @param array<string,mixed> $brief @param array<string,mixed> $settings */
    private static function html(WP_User $user, array $brief, array $settings): string {
        $url = class_exists('Elev8_OS_Proactive_Daily_Assistant_Module') ? Elev8_OS_Proactive_Daily_Assistant_Module::url() : home_url('/');
        $focus = (array) ($brief['focus'] ?? []);
        $html = '<div style="font-family:Arial,sans-serif;max-width:720px;margin:auto;color:#24163d">';
        $html .= '<h1>'.esc_html(sprintf(__('%1$s, %2$s.', 'elev8-os'), (string) ($brief['greeting'] ?? __('Hello','elev8-os')), (string) ($brief['name'] ?? $user->display_name))).'</h1>';
        $html .= '<p>'.esc_html__('Here is your governed personal focus for today.', 'elev8-os').'</p>';
        if (!$focus) { $html .= '<p>'.esc_html__('Nothing urgent is competing for your attention.', 'elev8-os').'</p>'; }
        foreach (array_slice($focus, 0, 5) as $item) {
            $html .= '<div style="border:1px solid #ddd;border-radius:10px;padding:14px;margin:10px 0"><strong>'.esc_html((string) ($item['title'] ?? '')).'</strong><p>'.esc_html((string) ($item['summary'] ?? '')).'</p></div>';
        }
        if (($settings['reminders'] ?? 'overdue') !== 'none' && (int) ($brief['work']['overdue'] ?? 0) > 0) {
            $html .= '<p><strong>'.esc_html(sprintf(_n('%d Work Item is overdue.', '%d Work Items are overdue.', (int) $brief['work']['overdue'], 'elev8-os'), (int) $brief['work']['overdue'])).'</strong></p>';
        }
        $html .= '<p><a style="display:inline-block;background:#5b21b6;color:#fff;padding:12px 18px;border-radius:8px;text-decoration:none" href="'.esc_url($url).'">'.esc_html__('Open Today','elev8-os').'</a></p>';
        $html .= '<p style="font-size:12px;color:#666">'.esc_html__('This briefing is a personal read model. It does not create or change work, decisions, conversations, or source records.', 'elev8-os').'</p></div>';
        return $html;
    }

    public static function register_graph_relationships(array $relationships): array {
        $relationships['daily_assistant_projects_delivery'] = [
            'label' => __('Projects a governed personal briefing delivery', 'elev8-os'),
            'from' => ['daily_assistant_projection'],
            'to' => ['communication_delivery'],
            'directional' => true,
            'notes' => __('Delivery timing and channels belong to personal preference governance. Communication transports the projection without changing its source evidence.', 'elev8-os'),
        ];
        return $relationships;
    }
}

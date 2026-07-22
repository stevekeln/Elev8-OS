<?php
if (!defined('ABSPATH')) { exit; }

/** Scheduled Executive Brief delivery through the Communication Engine boundary. */
final class Elev8_OS_Executive_Brief_Delivery_Service {
    public const CRON_HOOK = 'elev8_os_deliver_executive_briefs';
    private const META_SETTINGS = 'elev8_os_executive_brief_delivery';
    private const META_LAST_SENT = 'elev8_os_executive_brief_last_sent';

    public static function init(): void {
        add_action('init', [__CLASS__, 'ensure_schedule']);
        add_action(self::CRON_HOOK, [__CLASS__, 'run']);
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
            'hour' => 7,
            'days' => 'daily',
        ]);
    }

    public static function save_settings(int $user_id, array $settings): void {
        $days = sanitize_key((string) ($settings['days'] ?? 'daily'));
        if (!in_array($days, ['daily', 'weekdays'], true)) { $days = 'daily'; }
        update_user_meta($user_id, self::META_SETTINGS, [
            'enabled' => !empty($settings['enabled']),
            'hour' => max(0, min(23, (int) ($settings['hour'] ?? 7))),
            'days' => $days,
        ]);
    }

    public static function run(): void {
        $users = get_users(['fields' => 'all']);
        foreach ($users as $user) {
            if (!$user instanceof WP_User || !self::can_receive($user)) { continue; }
            $settings = self::settings((int) $user->ID);
            if (empty($settings['enabled']) || !self::is_due($user, $settings)) { continue; }
            self::deliver($user);
        }
    }

    public static function deliver(WP_User $user): bool {
        if (!self::can_receive($user) || !is_email($user->user_email)) { return false; }
        $report = Elev8_OS_Executive_Intelligence_Read_Model_Service::report();
        $subject = sprintf(__('Elev8 OS Executive Brief — %s', 'elev8-os'), wp_date(get_option('date_format')));
        $message = self::html($user, $report);
        $sent = Elev8_OS_Notification_Service::send_email(
            $user->user_email,
            $subject,
            $message,
            ['Content-Type: text/html; charset=UTF-8']
        );
        if ($sent) {
            update_user_meta((int) $user->ID, self::META_LAST_SENT, current_time('mysql'));
            do_action('elev8_os_executive_brief_delivered', (int) $user->ID, $report);
        }
        return $sent;
    }

    private static function is_due(WP_User $user, array $settings): bool {
        $now_hour = (int) current_time('G');
        if ($now_hour !== (int) $settings['hour']) { return false; }
        if ($settings['days'] === 'weekdays' && in_array((int) current_time('N'), [6, 7], true)) { return false; }
        $last = (string) get_user_meta((int) $user->ID, self::META_LAST_SENT, true);
        return $last === '' || substr($last, 0, 10) !== current_time('Y-m-d');
    }

    private static function can_receive(WP_User $user): bool {
        return user_can($user, 'manage_options')
            || (class_exists('Elev8_OS_Access_Service') && Elev8_OS_Access_Service::user_can('view_ceo_dashboard', $user));
    }

    /** @param array<string,mixed> $report */
    private static function html(WP_User $user, array $report): string {
        $attention = (array) ($report['attention'] ?? []);
        $recommendations = (array) ($report['recommendation_summary'] ?? []);
        $patterns = (array) ($report['pattern_summary'] ?? []);
        $url = class_exists('Elev8_OS_Observation_Registry_Module') ? add_query_arg('view', 'executive', Elev8_OS_Observation_Registry_Module::url()) : home_url('/');
        $html = '<div style="font-family:Arial,sans-serif;max-width:720px;margin:auto;color:#24163d">';
        $html .= '<h1>'.esc_html(sprintf(__('Good morning, %s.', 'elev8-os'), $user->display_name)).'</h1>';
        $html .= '<p>'.esc_html__('Here is the governed intelligence currently deserving executive attention.', 'elev8-os').'</p>';
        $html .= '<div style="display:flex;gap:16px;flex-wrap:wrap">';
        $html .= self::metric(__('Active risks', 'elev8-os'), (int) ($patterns['risks'] ?? 0));
        $html .= self::metric(__('Opportunities', 'elev8-os'), (int) ($patterns['opportunities'] ?? 0));
        $html .= self::metric(__('Decisions waiting', 'elev8-os'), (int) ($recommendations['proposed'] ?? 0));
        $html .= self::metric(__('Outcomes waiting', 'elev8-os'), (int) ($recommendations['awaiting_outcome'] ?? 0));
        $html .= '</div><h2>'.esc_html__('What deserves attention now', 'elev8-os').'</h2>';
        if (!$attention) { $html .= '<p>'.esc_html__('No governed intelligence currently requires executive attention.', 'elev8-os').'</p>'; }
        foreach (array_slice($attention, 0, 7) as $item) {
            $html .= '<div style="border:1px solid #ddd;border-radius:10px;padding:14px;margin:10px 0"><strong>'.esc_html((string) ($item['title'] ?? '')).'</strong><p>'.esc_html((string) ($item['reason'] ?? '')).'</p></div>';
        }
        $html .= '<p><a style="display:inline-block;background:#5b21b6;color:#fff;padding:12px 18px;border-radius:8px;text-decoration:none" href="'.esc_url($url).'">'.esc_html__('Open Executive Intelligence', 'elev8-os').'</a></p></div>';
        return $html;
    }

    private static function metric(string $label, int $value): string {
        return '<div style="border:1px solid #ddd;border-radius:10px;padding:12px;min-width:120px"><strong style="font-size:24px">'.(int) $value.'</strong><div>'.esc_html($label).'</div></div>';
    }
}

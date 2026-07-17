<?php
/**
 * Shared recommendation workflow and history service.
 *
 * Recommendation definitions remain rule-driven. This service owns only the
 * artist's interaction state so dashboards, reports, notifications, and a
 * future AI coach can reuse one trustworthy workflow.
 */
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Recommendation_State_Service {
    private const META_KEY = 'elev8_os_recommendation_states';
    private const HISTORY_LIMIT = 100;
    private const VALID_STATUSES = ['not_started', 'in_progress', 'completed', 'dismissed', 'hidden'];

    public static function init(): void {
        add_action('admin_post_elev8_recommendation_state', [__CLASS__, 'handle_request']);
    }

    /** @return array<string,mixed> */
    public static function get_state(int $user_id, string $recommendation_id): array {
        $store = self::get_store($user_id);
        $id = sanitize_key($recommendation_id);
        $state = isset($store['items'][$id]) && is_array($store['items'][$id]) ? $store['items'][$id] : [];
        return wp_parse_args($state, [
            'status' => 'not_started',
            'priority' => null,
            'expires_at' => '',
            'updated_at' => '',
            'completed_at' => '',
            'dismissed_at' => '',
        ]);
    }

    /** @param array<int,array<string,mixed>> $recommendations @return array<int,array<string,mixed>> */
    public static function apply_states(int $user_id, array $recommendations): array {
        $visible = [];
        foreach ($recommendations as $recommendation) {
            if (!is_array($recommendation)) { continue; }
            $id = sanitize_key((string) ($recommendation['id'] ?? ''));
            if ($id === '') { continue; }
            $state = self::get_state($user_id, $id);
            if (self::is_expired($state)) {
                $state = self::transition($user_id, $id, 'not_started', $recommendation, false);
            }
            $recommendation['state'] = $state['status'];
            $recommendation['state_record'] = $state;
            if (!in_array($state['status'], ['completed', 'dismissed', 'hidden'], true)) {
                $visible[] = $recommendation;
            }
        }
        return $visible;
    }

    /** @return array<string,mixed> */
    public static function transition(int $user_id, string $recommendation_id, string $status, array $recommendation = [], bool $record = true): array {
        $id = sanitize_key($recommendation_id);
        $status = sanitize_key($status);
        if ($user_id <= 0 || $id === '' || !in_array($status, self::VALID_STATUSES, true)) {
            return [];
        }

        $store = self::get_store($user_id);
        $now = current_time('mysql');
        $previous = isset($store['items'][$id]) && is_array($store['items'][$id]) ? $store['items'][$id] : [];
        $state = wp_parse_args($previous, [
            'status' => 'not_started', 'priority' => null, 'expires_at' => '',
            'updated_at' => '', 'completed_at' => '', 'dismissed_at' => '',
        ]);
        $state['status'] = $status;
        $state['updated_at'] = $now;
        if (isset($recommendation['priority'])) { $state['priority'] = sanitize_key((string) $recommendation['priority']); }
        if (isset($recommendation['expires_at'])) { $state['expires_at'] = sanitize_text_field((string) $recommendation['expires_at']); }
        if ($status === 'completed') { $state['completed_at'] = $now; }
        if ($status === 'dismissed') { $state['dismissed_at'] = $now; }
        if ($status === 'not_started') { $state['completed_at'] = ''; $state['dismissed_at'] = ''; }

        $store['items'][$id] = $state;
        if ($record) {
            $event = self::event_for_status($status);
            if ($event !== '') {
                array_unshift($store['history'], [
                    'recommendation_id' => $id,
                    'type' => $event,
                    'label' => sanitize_text_field((string) ($recommendation['title'] ?? $id)),
                    'occurred_at' => $now,
                ]);
                $store['history'] = array_slice($store['history'], 0, self::HISTORY_LIMIT);
                do_action('elev8_os_recommendation_state_changed', $user_id, $id, $status, $recommendation, $state);
                if ($status === 'completed' && class_exists('Elev8_OS_Achievement_Service')) {
                    Elev8_OS_Achievement_Service::recommendation_trigger($user_id, $id, $recommendation);
                }
            }
        }
        update_user_meta($user_id, self::META_KEY, $store);
        return $state;
    }

    /** @return array<int,array<string,string>> */
    public static function get_history(int $user_id, int $limit = 8): array {
        $store = self::get_store($user_id);
        return array_slice(array_values(array_filter($store['history'], 'is_array')), 0, max(1, min(50, $limit)));
    }

    public static function handle_request(): void {
        if (!is_user_logged_in()) { auth_redirect(); }
        $user_id = get_current_user_id();
        check_admin_referer('elev8_recommendation_state_' . $user_id);
        $id = sanitize_key((string) ($_POST['recommendation_id'] ?? ''));
        $status = sanitize_key((string) ($_POST['recommendation_status'] ?? ''));
        $title = sanitize_text_field((string) ($_POST['recommendation_title'] ?? ''));
        $priority = sanitize_key((string) ($_POST['recommendation_priority'] ?? ''));
        self::transition($user_id, $id, $status, ['id' => $id, 'title' => $title, 'priority' => $priority]);
        $redirect = wp_validate_redirect((string) ($_POST['redirect_to'] ?? ''), home_url('/'));
        $message = $status === 'completed' ? 'completed' : ($status === 'dismissed' ? 'dismissed' : 'started');
        wp_safe_redirect(add_query_arg('elev8_recommendation', $message, $redirect));
        exit;
    }

    /** @return array{items:array<string,array<string,mixed>>,history:array<int,array<string,string>>} */
    private static function get_store(int $user_id): array {
        $store = get_user_meta($user_id, self::META_KEY, true);
        if (!is_array($store)) { $store = []; }
        return [
            'items' => isset($store['items']) && is_array($store['items']) ? $store['items'] : [],
            'history' => isset($store['history']) && is_array($store['history']) ? $store['history'] : [],
        ];
    }

    private static function is_expired(array $state): bool {
        $expires = trim((string) ($state['expires_at'] ?? ''));
        return $expires !== '' && strtotime($expires) !== false && strtotime($expires) < current_time('timestamp');
    }

    private static function event_for_status(string $status): string {
        return ['in_progress' => 'recommendation_started', 'completed' => 'recommendation_completed', 'dismissed' => 'recommendation_dismissed'][$status] ?? '';
    }
}

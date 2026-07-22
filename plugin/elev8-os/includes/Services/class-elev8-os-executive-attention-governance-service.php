<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Stores human governance over ranked Executive Intelligence attention items.
 * The underlying Pattern or Recommendation remains authoritative.
 */
final class Elev8_OS_Executive_Attention_Governance_Service {
    public const POST_TYPE = 'elev8_exec_attention';

    public static function init(): void {
        add_action('init', [__CLASS__, 'register_post_type']);
    }

    public static function register_post_type(): void {
        register_post_type(self::POST_TYPE, [
            'labels' => ['name' => __('Executive Attention Decisions', 'elev8-os')],
            'public' => false,
            'show_ui' => false,
            'show_in_rest' => false,
            'supports' => ['title'],
        ]);
    }

    /** @return array<string,mixed> */
    public static function state(string $item_key): array {
        $post_id = self::find($item_key);
        if (!$post_id) {
            return self::empty_state($item_key);
        }
        $timeline = get_post_meta($post_id, '_elev8_timeline', true);
        return [
            'id' => $post_id,
            'item_key' => $item_key,
            'status' => (string) get_post_meta($post_id, '_elev8_status', true) ?: 'open',
            'defer_until' => (string) get_post_meta($post_id, '_elev8_defer_until', true),
            'notes' => (string) get_post_meta($post_id, '_elev8_notes', true),
            'updated_by' => (int) get_post_meta($post_id, '_elev8_updated_by', true),
            'updated_at' => (string) get_post_meta($post_id, '_elev8_updated_at', true),
            'timeline' => is_array($timeline) ? $timeline : [],
        ];
    }

    /** @return true|WP_Error */
    public static function decide(string $item_key, string $status, int $user_id, string $notes = '', string $defer_until = '') {
        $item_key = sanitize_text_field($item_key);
        $status = sanitize_key($status);
        if ($item_key === '') { return new WP_Error('elev8_attention_key', __('The attention item is missing.', 'elev8-os')); }
        if (!in_array($status, ['open', 'acknowledged', 'deferred', 'resolved'], true)) {
            return new WP_Error('elev8_attention_status', __('The attention decision is invalid.', 'elev8-os'));
        }
        $defer_until = $status === 'deferred' ? self::normalize_date($defer_until) : '';
        if ($status === 'deferred' && $defer_until === '') {
            return new WP_Error('elev8_attention_defer', __('Choose a valid defer date.', 'elev8-os'));
        }

        $post_id = self::find($item_key);
        if (!$post_id) {
            $post_id = wp_insert_post([
                'post_type' => self::POST_TYPE,
                'post_status' => 'publish',
                'post_title' => sprintf(__('Executive attention: %s', 'elev8-os'), $item_key),
            ], true);
            if (is_wp_error($post_id)) { return $post_id; }
            update_post_meta((int) $post_id, '_elev8_item_key', $item_key);
        }

        $now = current_time('mysql');
        update_post_meta((int) $post_id, '_elev8_status', $status);
        update_post_meta((int) $post_id, '_elev8_defer_until', $defer_until);
        update_post_meta((int) $post_id, '_elev8_notes', sanitize_textarea_field($notes));
        update_post_meta((int) $post_id, '_elev8_updated_by', $user_id);
        update_post_meta((int) $post_id, '_elev8_updated_at', $now);

        $timeline = get_post_meta((int) $post_id, '_elev8_timeline', true);
        $timeline = is_array($timeline) ? $timeline : [];
        $timeline[] = [
            'status' => $status,
            'defer_until' => $defer_until,
            'notes' => sanitize_textarea_field($notes),
            'user_id' => $user_id,
            'occurred_at' => $now,
        ];
        update_post_meta((int) $post_id, '_elev8_timeline', array_slice($timeline, -100));
        do_action('elev8_os_executive_attention_decided', $item_key, $status, (int) $post_id, $user_id);
        return true;
    }

    /** @param array<int,array<string,mixed>> $items @return array<int,array<string,mixed>> */
    public static function decorate(array $items, bool $actionable_only = false): array {
        $today = current_time('Y-m-d');
        $result = [];
        foreach ($items as $item) {
            $key = (string) ($item['item_key'] ?? '');
            $state = $key !== '' ? self::state($key) : self::empty_state($key);
            $item['governance'] = $state;
            $is_future_deferred = $state['status'] === 'deferred' && $state['defer_until'] !== '' && $state['defer_until'] > $today;
            if ($actionable_only && ($state['status'] === 'resolved' || $is_future_deferred)) { continue; }
            $result[] = $item;
        }
        return $result;
    }

    /** @return array<int,array<string,mixed>> */
    public static function timeline(int $limit = 30): array {
        $posts = get_posts([
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => max(1, min(100, $limit)),
            'orderby' => 'modified',
            'order' => 'DESC',
        ]);
        $events = [];
        foreach ($posts as $post) {
            $state = self::state((string) get_post_meta($post->ID, '_elev8_item_key', true));
            foreach ((array) $state['timeline'] as $event) {
                $event['item_key'] = $state['item_key'];
                $events[] = $event;
            }
        }
        usort($events, static function(array $a, array $b): int {
            return strcmp((string) ($b['occurred_at'] ?? ''), (string) ($a['occurred_at'] ?? ''));
        });
        return array_slice($events, 0, $limit);
    }

    private static function find(string $item_key): int {
        if ($item_key === '') { return 0; }
        $posts = get_posts([
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_key' => '_elev8_item_key',
            'meta_value' => $item_key,
            'no_found_rows' => true,
        ]);
        return $posts ? (int) $posts[0] : 0;
    }

    /** @return array<string,mixed> */
    private static function empty_state(string $item_key): array {
        return ['id'=>0, 'item_key'=>$item_key, 'status'=>'open', 'defer_until'=>'', 'notes'=>'', 'updated_by'=>0, 'updated_at'=>'', 'timeline'=>[]];
    }

    private static function normalize_date(string $date): string {
        $date = sanitize_text_field($date);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { return ''; }
        $parts = array_map('intval', explode('-', $date));
        return checkdate($parts[1], $parts[2], $parts[0]) ? $date : '';
    }
}

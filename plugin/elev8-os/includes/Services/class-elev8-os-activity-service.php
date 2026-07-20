<?php
/**
 * Shared activity and timeline boundary for Elev8 OS business records.
 *
 * Activities are immutable history entries. Source records remain authoritative;
 * this service stores only the operational event needed by CRM, Intake, Business
 * Memory, dashboards, and future intelligence.
 */
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Activity_Service {
    const POST_TYPE = 'elev8_activity';

    public static function init(): void {
        add_action('init', [__CLASS__, 'register_post_type']);
    }

    public static function activate(): void {
        self::register_post_type();
    }

    public static function register_post_type(): void {
        register_post_type(self::POST_TYPE, [
            'labels' => ['name' => __('Activities', 'elev8-os'), 'singular_name' => __('Activity', 'elev8-os')],
            'public' => false,
            'show_ui' => false,
            'show_in_rest' => false,
            'supports' => ['title', 'editor', 'author'],
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ]);
    }

    /**
     * Record an immutable business activity.
     *
     * Supported keys: type, label, details, person_id, object_id, object_type,
     * source, actor_user_id, metadata.
     */
    public static function record(array $data): int {
        $label = sanitize_text_field((string) ($data['label'] ?? __('Business activity', 'elev8-os')));
        $details = sanitize_textarea_field((string) ($data['details'] ?? ''));
        $activity_id = wp_insert_post([
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'post_title' => $label,
            'post_content' => $details,
            'post_author' => absint($data['actor_user_id'] ?? get_current_user_id()),
        ], true);

        if (is_wp_error($activity_id)) { return 0; }

        $meta = [
            '_elev8_activity_type' => sanitize_key((string) ($data['type'] ?? 'general')),
            '_elev8_activity_person_id' => absint($data['person_id'] ?? 0),
            '_elev8_activity_object_id' => absint($data['object_id'] ?? 0),
            '_elev8_activity_object_type' => sanitize_key((string) ($data['object_type'] ?? '')),
            '_elev8_activity_source' => sanitize_text_field((string) ($data['source'] ?? '')),
            '_elev8_activity_actor_user_id' => absint($data['actor_user_id'] ?? get_current_user_id()),
            '_elev8_activity_metadata' => is_array($data['metadata'] ?? null) ? $data['metadata'] : [],
        ];
        foreach ($meta as $key => $value) { update_post_meta((int) $activity_id, $key, $value); }

        do_action('elev8_os_activity_recorded', (int) $activity_id, $data);
        return (int) $activity_id;
    }

    public static function for_object(int $object_id, string $object_type = '', int $limit = 50): array {
        if ($object_id < 1) { return []; }
        $query = [
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => max(1, min(200, $limit)),
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => [[
                'key' => '_elev8_activity_object_id',
                'value' => $object_id,
                'compare' => '=',
                'type' => 'NUMERIC',
            ]],
        ];
        if ($object_type !== '') {
            $query['meta_query'][] = [
                'key' => '_elev8_activity_object_type',
                'value' => sanitize_key($object_type),
                'compare' => '=',
            ];
        }
        return get_posts($query);
    }

    public static function for_person(int $person_id, int $limit = 100): array {
        if ($person_id < 1) { return []; }
        return get_posts([
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => max(1, min(200, $limit)),
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_key' => '_elev8_activity_person_id',
            'meta_value' => $person_id,
        ]);
    }

    public static function record_opportunity(int $opportunity_id, string $type, string $label, string $details = '', int $interest_id = 0): bool {
        if ($opportunity_id <= 0 || !class_exists('Elev8_OS_Opportunity_Activity_Service')) { return false; }
        Elev8_OS_Opportunity_Activity_Service::record($opportunity_id, sanitize_key($type), $label, $details, $interest_id);
        return true;
    }
}

<?php
/**
 * Relationship Engine for trusted links between Elev8 OS records.
 *
 * Relationships are stored independently from their source records so the
 * authoritative modules remain unchanged. Each relationship is directional
 * in storage but queried from either endpoint.
 */
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Relationship_Service {
    public const POST_TYPE = 'elev8_relation';

    public static function init(): void {
        add_action('init', [__CLASS__, 'register_post_type']);
    }

    public static function activate(): void {
        self::register_post_type();
    }

    public static function register_post_type(): void {
        register_post_type(self::POST_TYPE, [
            'labels' => ['name' => __('Relationships', 'elev8-os')],
            'public' => false,
            'show_ui' => false,
            'show_in_menu' => false,
            'supports' => ['title', 'author'],
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ]);
    }

    public static function kinds(): array {
        return (array) apply_filters('elev8_os_relationship_kinds', [
            'related_to' => __('Related to', 'elev8-os'),
            'belongs_to' => __('Belongs to', 'elev8-os'),
            'supports' => __('Supports', 'elev8-os'),
            'depends_on' => __('Depends on', 'elev8-os'),
            'blocks' => __('Blocks', 'elev8-os'),
            'follow_up_for' => __('Follow-up for', 'elev8-os'),
            'participant_in' => __('Participant in', 'elev8-os'),
        ]);
    }

    public static function can_manage(?WP_User $user = null): bool {
        $user = $user instanceof WP_User ? $user : wp_get_current_user();
        if (!$user instanceof WP_User || $user->ID < 1) { return false; }
        return current_user_can('manage_options')
            || Elev8_OS_Access_Service::user_can('manage_business_memory', $user)
            || Elev8_OS_Access_Service::user_can('manage_work', $user);
    }

    public static function connect(string $from_type, int $from_id, string $to_type, int $to_id, string $kind = 'related_to', string $note = ''): int {
        $from_type = Elev8_OS_Workspace_Service::normalize_type($from_type);
        $to_type = Elev8_OS_Workspace_Service::normalize_type($to_type);
        $from_id = absint($from_id);
        $to_id = absint($to_id);
        $kind = sanitize_key($kind);
        if (!$from_type || !$to_type || $from_id < 1 || $to_id < 1 || ($from_type === $to_type && $from_id === $to_id)) { return 0; }
        if (!isset(self::kinds()[$kind])) { $kind = 'related_to'; }
        if (class_exists('Elev8_OS_Business_Graph_Registry_Service')) {
            $validation = Elev8_OS_Business_Graph_Registry_Service::validate_connection($from_type, $to_type, $kind);
            if (empty($validation['valid'])) {
                do_action('elev8_os_business_graph_relationship_rejected', $from_type, $from_id, $to_type, $to_id, $kind, $validation);
                return 0;
            }
        }
        if (!Elev8_OS_Workspace_Service::can_view($from_type, $from_id) || !Elev8_OS_Workspace_Service::can_view($to_type, $to_id)) { return 0; }

        $existing = self::find_exact($from_type, $from_id, $to_type, $to_id, $kind);
        if ($existing > 0) {
            if ($note !== '') { update_post_meta($existing, '_elev8_relation_note', sanitize_textarea_field($note)); }
            return $existing;
        }

        $from = Elev8_OS_Workspace_Service::summary($from_type, $from_id);
        $to = Elev8_OS_Workspace_Service::summary($to_type, $to_id);
        $relation_id = wp_insert_post([
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'post_title' => sprintf('%s → %s', (string) $from['title'], (string) $to['title']),
            'post_author' => get_current_user_id(),
        ], true);
        if (is_wp_error($relation_id)) { return 0; }

        $meta = [
            '_elev8_relation_from_type' => $from_type,
            '_elev8_relation_from_id' => $from_id,
            '_elev8_relation_to_type' => $to_type,
            '_elev8_relation_to_id' => $to_id,
            '_elev8_relation_kind' => $kind,
            '_elev8_relation_note' => sanitize_textarea_field($note),
            '_elev8_relation_created_by' => get_current_user_id(),
            '_elev8_relation_from_engine' => class_exists('Elev8_OS_Business_Graph_Registry_Service') ? Elev8_OS_Business_Graph_Registry_Service::owning_engine($from_type) : '',
            '_elev8_relation_to_engine' => class_exists('Elev8_OS_Business_Graph_Registry_Service') ? Elev8_OS_Business_Graph_Registry_Service::owning_engine($to_type) : '',
            '_elev8_relation_from_authority' => class_exists('Elev8_OS_Business_Graph_Registry_Service') ? Elev8_OS_Business_Graph_Registry_Service::authoritative_system($from_type) : '',
            '_elev8_relation_to_authority' => class_exists('Elev8_OS_Business_Graph_Registry_Service') ? Elev8_OS_Business_Graph_Registry_Service::authoritative_system($to_type) : '',
            '_elev8_relation_organization_unit_id' => class_exists('Elev8_OS_Business_Graph_Registry_Service') ? (Elev8_OS_Business_Graph_Registry_Service::organization_scope_for($from_type, $from_id) ?: Elev8_OS_Business_Graph_Registry_Service::organization_scope_for($to_type, $to_id)) : 0,
        ];
        foreach ($meta as $key => $value) { update_post_meta((int) $relation_id, $key, $value); }

        if (class_exists('Elev8_OS_Activity_Service')) {
            Elev8_OS_Activity_Service::record([
                'type' => 'relationship_created',
                'label' => __('Related record connected', 'elev8-os'),
                'details' => sprintf(__('%1$s connected to %2$s as “%3$s”.', 'elev8-os'), (string) $from['title'], (string) $to['title'], self::kinds()[$kind]),
                'object_id' => $from_id,
                'object_type' => self::activity_object_type($from_type),
                'source' => 'relationship-engine',
                'actor_user_id' => get_current_user_id(),
                'metadata' => ['relationship_id' => (int) $relation_id, 'related_type' => $to_type, 'related_id' => $to_id],
            ]);
        }
        do_action('elev8_os_relationship_created', (int) $relation_id, $meta);
        return (int) $relation_id;
    }

    public static function disconnect(int $relationship_id): bool {
        $relationship_id = absint($relationship_id);
        if ($relationship_id < 1 || get_post_type($relationship_id) !== self::POST_TYPE || !self::can_manage()) { return false; }
        $data = self::get($relationship_id);
        $deleted = (bool) wp_delete_post($relationship_id, true);
        if ($deleted && $data && class_exists('Elev8_OS_Activity_Service')) {
            Elev8_OS_Activity_Service::record([
                'type' => 'relationship_removed',
                'label' => __('Related record disconnected', 'elev8-os'),
                'details' => __('A manual workspace relationship was removed.', 'elev8-os'),
                'object_id' => (int) $data['from_id'],
                'object_type' => self::activity_object_type((string) $data['from_type']),
                'source' => 'relationship-engine',
                'actor_user_id' => get_current_user_id(),
            ]);
        }
        return $deleted;
    }

    public static function for_record(string $type, int $id): array {
        $type = Elev8_OS_Workspace_Service::normalize_type($type);
        $id = absint($id);
        if (!$type || $id < 1) { return []; }
        $query = new WP_Query([
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => 200,
            'orderby' => 'date',
            'order' => 'DESC',
            'no_found_rows' => true,
            'meta_query' => [
                'relation' => 'OR',
                [
                    'relation' => 'AND',
                    ['key' => '_elev8_relation_from_type', 'value' => $type],
                    ['key' => '_elev8_relation_from_id', 'value' => $id, 'type' => 'NUMERIC'],
                ],
                [
                    'relation' => 'AND',
                    ['key' => '_elev8_relation_to_type', 'value' => $type],
                    ['key' => '_elev8_relation_to_id', 'value' => $id, 'type' => 'NUMERIC'],
                ],
            ],
        ]);
        $items = [];
        foreach ($query->posts as $post) {
            $data = self::get((int) $post->ID);
            if (!$data) { continue; }
            $is_from = $data['from_type'] === $type && (int) $data['from_id'] === $id;
            $other_type = $is_from ? $data['to_type'] : $data['from_type'];
            $other_id = $is_from ? (int) $data['to_id'] : (int) $data['from_id'];
            if (!Elev8_OS_Workspace_Service::can_view($other_type, $other_id)) { continue; }
            $summary = Elev8_OS_Workspace_Service::summary($other_type, $other_id);
            $items[] = [
                'relationship_id' => (int) $post->ID,
                'kind' => $data['kind'],
                'kind_label' => self::kinds()[$data['kind']] ?? ucfirst(str_replace('_', ' ', $data['kind'])),
                'direction' => $is_from ? 'outgoing' : 'incoming',
                'note' => $data['note'],
                'type' => $other_type,
                'id' => $other_id,
                'label' => $summary['label'],
                'title' => $summary['title'],
                'status' => $summary['status'],
                'url' => Elev8_OS_Workspace_Service::url($other_type, $other_id),
            ];
        }
        return $items;
    }

    public static function impact_summary(string $type, int $id): array {
        $relationships = self::for_record($type, $id);
        $counts = ['total' => count($relationships), 'blocks' => 0, 'depends_on' => 0, 'people' => 0, 'work' => 0, 'conversations' => 0];
        foreach ($relationships as $item) {
            if ($item['kind'] === 'blocks') { $counts['blocks']++; }
            if ($item['kind'] === 'depends_on') { $counts['depends_on']++; }
            if ($item['type'] === 'person') { $counts['people']++; }
            if ($item['type'] === 'work') { $counts['work']++; }
            if ($item['type'] === 'conversation') { $counts['conversations']++; }
        }
        return $counts;
    }

    public static function get(int $relationship_id): array {
        if (get_post_type($relationship_id) !== self::POST_TYPE) { return []; }
        return [
            'id' => $relationship_id,
            'from_type' => Elev8_OS_Workspace_Service::normalize_type((string) get_post_meta($relationship_id, '_elev8_relation_from_type', true)),
            'from_id' => absint(get_post_meta($relationship_id, '_elev8_relation_from_id', true)),
            'to_type' => Elev8_OS_Workspace_Service::normalize_type((string) get_post_meta($relationship_id, '_elev8_relation_to_type', true)),
            'to_id' => absint(get_post_meta($relationship_id, '_elev8_relation_to_id', true)),
            'kind' => sanitize_key((string) get_post_meta($relationship_id, '_elev8_relation_kind', true)),
            'note' => (string) get_post_meta($relationship_id, '_elev8_relation_note', true),
        ];
    }

    private static function find_exact(string $from_type, int $from_id, string $to_type, int $to_id, string $kind): int {
        $posts = get_posts([
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_query' => [
                ['key' => '_elev8_relation_from_type', 'value' => $from_type],
                ['key' => '_elev8_relation_from_id', 'value' => $from_id, 'type' => 'NUMERIC'],
                ['key' => '_elev8_relation_to_type', 'value' => $to_type],
                ['key' => '_elev8_relation_to_id', 'value' => $to_id, 'type' => 'NUMERIC'],
                ['key' => '_elev8_relation_kind', 'value' => $kind],
            ],
        ]);
        return $posts ? (int) $posts[0] : 0;
    }

    private static function activity_object_type(string $type): string {
        $map = [
            'work' => class_exists('Elev8_OS_Work_Service') ? Elev8_OS_Work_Service::POST_TYPE : 'elev8_work_item',
            'conversation' => class_exists('Elev8_OS_Conversation_Service') ? Elev8_OS_Conversation_Service::THREAD_POST_TYPE : 'elev8_conversation',
            'manager_log' => class_exists('Elev8_OS_Daily_Operations_Service') ? Elev8_OS_Daily_Operations_Service::POST_TYPE : 'elev8_ops_log',
            'event_application' => 'elev8_event_app',
            'person' => 'person',
        ];
        return $map[$type] ?? $type;
    }
}

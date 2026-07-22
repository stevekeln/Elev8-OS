<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Converts governed executive attention into an explicit follow-through record.
 *
 * The record preserves the executive decision. Delegated reviews and scheduled
 * follow-ups create normal Operations Work Items. Recommendation execution is
 * routed through the existing Recommendation approval boundary.
 */
final class Elev8_OS_Executive_Decision_Follow_Through_Service {
    public const POST_TYPE = 'elev8_exec_follow';

    public static function init(): void {
        add_action('init', [__CLASS__, 'register_post_type']);
        add_filter('elev8_os_business_graph_objects', [__CLASS__, 'register_graph_objects']);
        add_filter('elev8_os_business_graph_relationships', [__CLASS__, 'register_graph_relationships']);
    }

    public static function register_post_type(): void {
        register_post_type(self::POST_TYPE, [
            'labels' => ['name' => __('Executive Follow-through', 'elev8-os')],
            'public' => false,
            'show_ui' => false,
            'show_in_rest' => false,
            'supports' => ['title', 'editor'],
        ]);
    }

    /** @return true|WP_Error */
    public static function create(array $args) {
        $args = wp_parse_args($args, [
            'item_key' => '', 'source_type' => '', 'source_id' => 0, 'kind' => 'formal_decision',
            'title' => '', 'notes' => '', 'owner_user_id' => 0, 'due_date' => '',
            'organization_unit_id' => 0, 'created_by_user_id' => get_current_user_id(),
        ]);
        $item_key = sanitize_text_field((string) $args['item_key']);
        $source_type = sanitize_key((string) $args['source_type']);
        $source_id = absint($args['source_id']);
        $kind = sanitize_key((string) $args['kind']);
        if ($item_key === '' || $source_type === '' || !$source_id) {
            return new WP_Error('elev8_follow_source', __('The executive attention source is incomplete.', 'elev8-os'));
        }
        if (!in_array($kind, ['formal_decision', 'delegated_review', 'approved_action', 'scheduled_followup'], true)) {
            return new WP_Error('elev8_follow_kind', __('The follow-through type is invalid.', 'elev8-os'));
        }
        $fingerprint = hash('sha256', $item_key.'|'.$kind);
        $existing = self::find($fingerprint);
        if ($existing) { return true; }

        $title = sanitize_text_field((string) $args['title']);
        if ($title === '') { $title = __('Executive follow-through', 'elev8-os'); }
        $id = wp_insert_post([
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'post_title' => $title,
            'post_content' => sanitize_textarea_field((string) $args['notes']),
        ], true);
        if (is_wp_error($id)) { return $id; }
        $id = (int) $id;

        $due_date = self::normalize_date((string) $args['due_date']);
        update_post_meta($id, '_elev8_item_key', $item_key);
        update_post_meta($id, '_elev8_fingerprint', $fingerprint);
        update_post_meta($id, '_elev8_source_type', $source_type);
        update_post_meta($id, '_elev8_source_id', $source_id);
        update_post_meta($id, '_elev8_kind', $kind);
        update_post_meta($id, '_elev8_status', 'open');
        update_post_meta($id, '_elev8_owner_user_id', absint($args['owner_user_id']));
        update_post_meta($id, '_elev8_due_date', $due_date);
        update_post_meta($id, '_elev8_organization_unit_id', absint($args['organization_unit_id']));
        update_post_meta($id, '_elev8_created_by_user_id', absint($args['created_by_user_id']));
        update_post_meta($id, '_elev8_created_at', current_time('mysql'));

        $work_id = 0;
        if ($kind === 'approved_action') {
            if ($source_type !== 'recommendation' || !class_exists('Elev8_OS_Intelligence_Recommendation_Service')) {
                wp_delete_post($id, true);
                return new WP_Error('elev8_follow_action_source', __('Approved operational action is available only for a governed Recommendation.', 'elev8-os'));
            }
            $recommendation = Elev8_OS_Intelligence_Recommendation_Service::get($source_id);
            if (!$recommendation) {
                wp_delete_post($id, true);
                return new WP_Error('elev8_follow_recommendation', __('The Recommendation could not be found.', 'elev8-os'));
            }
            if ((string) ($recommendation['status'] ?? '') !== 'approved') {
                $result = Elev8_OS_Intelligence_Recommendation_Service::decide(
                    $source_id,
                    'approved',
                    absint($args['created_by_user_id']),
                    (string) $args['notes'],
                    absint($args['owner_user_id'])
                );
                if (is_wp_error($result)) { wp_delete_post($id, true); return $result; }
                $recommendation = Elev8_OS_Intelligence_Recommendation_Service::get($source_id);
            }
            $work_id = absint($recommendation['work_item_id'] ?? 0);
        } elseif (in_array($kind, ['delegated_review', 'scheduled_followup'], true)) {
            if (!class_exists('Elev8_OS_Operations_Engine_Service')) {
                wp_delete_post($id, true);
                return new WP_Error('elev8_follow_operations', __('The Operations Engine is unavailable.', 'elev8-os'));
            }
            $description = sanitize_textarea_field((string) $args['notes']);
            if ($description === '') {
                $description = $kind === 'delegated_review'
                    ? __('Review the supporting executive intelligence and return a documented recommendation.', 'elev8-os')
                    : __('Revisit this executive attention item and document the next decision.', 'elev8-os');
            }
            $work_id = Elev8_OS_Operations_Engine_Service::create_work([
                'title' => $title,
                'description' => $description,
                'type' => $kind === 'delegated_review' ? 'approval' : 'general',
                'status' => 'requested',
                'priority' => 'high',
                'owner_user_id' => absint($args['owner_user_id']),
                'organization_unit_id' => absint($args['organization_unit_id']),
                'due_date' => $due_date,
                'source_type' => 'executive_follow_through',
                'source_id' => $id,
                'workflow_key' => 'executive_follow_through',
                'step_key' => $kind,
                'requested_by_user_id' => absint($args['created_by_user_id']),
            ]);
            if (is_wp_error($work_id)) { wp_delete_post($id, true); return $work_id; }
            $work_id = absint($work_id);
        }
        if ($work_id) { update_post_meta($id, '_elev8_work_item_id', $work_id); }
        do_action('elev8_os_executive_follow_through_created', $id, $kind, $work_id);
        return true;
    }

    public static function find(string $fingerprint): int {
        $ids = get_posts([
            'post_type' => self::POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => 1,
            'fields' => 'ids', 'meta_query' => [['key' => '_elev8_fingerprint', 'value' => $fingerprint]],
        ]);
        return $ids ? (int) $ids[0] : 0;
    }

    /** @return array<int,array<string,mixed>> */
    public static function timeline(int $limit = 30): array {
        $posts = get_posts(['post_type' => self::POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => max(1, min(100, $limit)), 'orderby' => 'date', 'order' => 'DESC']);
        $items = [];
        foreach ($posts as $post) {
            $owner_id = absint(get_post_meta($post->ID, '_elev8_owner_user_id', true));
            $owner = $owner_id ? get_user_by('id', $owner_id) : false;
            $items[] = [
                'id' => (int) $post->ID,
                'title' => get_the_title($post),
                'notes' => (string) $post->post_content,
                'item_key' => (string) get_post_meta($post->ID, '_elev8_item_key', true),
                'kind' => (string) get_post_meta($post->ID, '_elev8_kind', true),
                'status' => (string) get_post_meta($post->ID, '_elev8_status', true) ?: 'open',
                'owner_name' => $owner instanceof WP_User ? $owner->display_name : __('Unassigned', 'elev8-os'),
                'due_date' => (string) get_post_meta($post->ID, '_elev8_due_date', true),
                'work_item_id' => absint(get_post_meta($post->ID, '_elev8_work_item_id', true)),
                'source_type' => (string) get_post_meta($post->ID, '_elev8_source_type', true),
                'source_id' => absint(get_post_meta($post->ID, '_elev8_source_id', true)),
                'created_at' => (string) get_post_meta($post->ID, '_elev8_created_at', true),
                'completed_at' => (string) get_post_meta($post->ID, '_elev8_completed_at', true),
            ];
        }
        return $items;
    }

    private static function normalize_date(string $value): string {
        $value = sanitize_text_field($value);
        if ($value === '') { return ''; }
        $date = DateTime::createFromFormat('Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $value : '';
    }

    public static function register_graph_objects(array $objects): array {
        $objects['executive_decision_follow_through'] = [
            'label' => __('Executive Decision Follow-through', 'elev8-os'), 'engine' => 'Intelligence',
            'authoritative_system' => 'elev8_os', 'source_type' => self::POST_TYPE,
            'organization_scoped' => true,
            'notes' => __('Governed evidence that executive attention became a formal decision, delegated review, approved action, or scheduled follow-up.', 'elev8-os'),
        ];
        return $objects;
    }

    public static function register_graph_relationships(array $relationships): array {
        $relationships['executive_attention_followed_through_as'] = [
            'label' => __('Followed through as', 'elev8-os'),
            'from' => ['pattern', 'recommendation'], 'to' => ['executive_decision_follow_through'],
            'directional' => true,
            'notes' => __('Connects governed executive attention to the explicit follow-through chosen by a leader.', 'elev8-os'),
        ];
        $relationships['executive_follow_through_executes_as'] = [
            'label' => __('Executes as', 'elev8-os'),
            'from' => ['executive_decision_follow_through'], 'to' => ['work_item'],
            'directional' => true,
            'notes' => __('Created only for delegated review, scheduled follow-up, or an approved Recommendation action.', 'elev8-os'),
        ];
        return $relationships;
    }
}

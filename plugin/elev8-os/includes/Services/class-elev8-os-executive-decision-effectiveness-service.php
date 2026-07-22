<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Measures whether executive follow-through produced the intended result.
 *
 * Recommendation-backed actions continue to use Recommendation Outcome as the
 * authoritative measurement. Other executive decisions use this dedicated
 * governance outcome so Work Items and source intelligence are not duplicated.
 */
final class Elev8_OS_Executive_Decision_Effectiveness_Service {
    public const POST_TYPE = 'elev8_exec_outcome';

    public static function init(): void {
        add_action('init', [__CLASS__, 'register_post_type']);
        add_action('elev8_os_operations_work_status_changed', [__CLASS__, 'work_status_changed'], 20, 3);
        add_action('elev8_os_recommendation_outcome_created', [__CLASS__, 'recommendation_outcome_created'], 20, 3);
        add_filter('elev8_os_business_graph_objects', [__CLASS__, 'register_graph_objects']);
        add_filter('elev8_os_business_graph_relationships', [__CLASS__, 'register_graph_relationships']);
    }

    public static function register_post_type(): void {
        register_post_type(self::POST_TYPE, [
            'labels' => ['name' => __('Executive Decision Outcomes', 'elev8-os')],
            'public' => false,
            'show_ui' => false,
            'show_in_rest' => false,
            'supports' => ['title', 'editor'],
        ]);
    }

    public static function recommendation_outcome_created(int $outcome_id, int $recommendation_id, int $work_id): void {
        $ids = get_posts([
            'post_type' => Elev8_OS_Executive_Decision_Follow_Through_Service::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => 20,
            'fields' => 'ids',
            'meta_query' => [
                'relation' => 'AND',
                ['key' => '_elev8_kind', 'value' => 'approved_action'],
                ['key' => '_elev8_source_type', 'value' => 'recommendation'],
                ['key' => '_elev8_source_id', 'value' => $recommendation_id, 'type' => 'NUMERIC'],
            ],
        ]);
        foreach ($ids as $follow_id) {
            update_post_meta((int) $follow_id, '_elev8_status', 'completed');
            update_post_meta((int) $follow_id, '_elev8_completed_at', current_time('mysql'));
            if ($work_id) { update_post_meta((int) $follow_id, '_elev8_work_item_id', $work_id); }
            do_action('elev8_os_executive_follow_through_completed', (int) $follow_id, $work_id, $outcome_id);
        }
    }

    public static function work_status_changed(int $work_id, string $status, string $before): void {
        if ($status !== 'completed' || $before === 'completed') { return; }
        $source_type = (string) get_post_meta($work_id, '_elev8_work_source_type', true);
        $follow_id = absint(get_post_meta($work_id, '_elev8_work_source_id', true));
        if ($source_type !== 'executive_follow_through' || !$follow_id) { return; }
        self::complete_follow_through($follow_id, $work_id);
    }

    /** @return int|WP_Error */
    public static function complete_follow_through(int $follow_id, int $work_id = 0) {
        $follow = get_post($follow_id);
        if (!$follow instanceof WP_Post || $follow->post_type !== Elev8_OS_Executive_Decision_Follow_Through_Service::POST_TYPE) {
            return new WP_Error('elev8_exec_follow_missing', __('Executive follow-through could not be found.', 'elev8-os'));
        }
        $work_id = $work_id ?: absint(get_post_meta($follow_id, '_elev8_work_item_id', true));
        update_post_meta($follow_id, '_elev8_status', 'completed');
        update_post_meta($follow_id, '_elev8_completed_at', current_time('mysql'));
        if ($work_id) { update_post_meta($follow_id, '_elev8_work_item_id', $work_id); }

        $kind = (string) get_post_meta($follow_id, '_elev8_kind', true);
        $source_type = (string) get_post_meta($follow_id, '_elev8_source_type', true);
        $source_id = absint(get_post_meta($follow_id, '_elev8_source_id', true));

        // Approved Recommendation actions already have a governed Outcome object.
        if ($kind === 'approved_action' && $source_type === 'recommendation' && $source_id) {
            if (class_exists('Elev8_OS_Recommendation_Outcome_Service')) {
                Elev8_OS_Recommendation_Outcome_Service::ensure_for_recommendation($source_id, $work_id);
            }
            do_action('elev8_os_executive_follow_through_completed', $follow_id, $work_id, 0);
            return 0;
        }

        $outcome_id = self::ensure($follow_id, $work_id);
        if (!is_wp_error($outcome_id)) {
            do_action('elev8_os_executive_follow_through_completed', $follow_id, $work_id, (int) $outcome_id);
        }
        return $outcome_id;
    }

    /** @return int|WP_Error */
    public static function ensure(int $follow_id, int $work_id = 0) {
        $existing = self::find_by_follow_through($follow_id);
        $follow = get_post($follow_id);
        if (!$follow instanceof WP_Post || $follow->post_type !== Elev8_OS_Executive_Decision_Follow_Through_Service::POST_TYPE) {
            return new WP_Error('elev8_exec_follow_missing', __('Executive follow-through could not be found.', 'elev8-os'));
        }
        $post = [
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'post_title' => sprintf(__('Decision outcome: %s', 'elev8-os'), get_the_title($follow_id)),
            'post_content' => __('Follow-through completed. Decision effectiveness is awaiting leader review.', 'elev8-os'),
        ];
        if ($existing) { $post['ID'] = $existing; }
        $id = wp_insert_post($post, true);
        if (is_wp_error($id)) { return $id; }
        $id = (int) $id;
        update_post_meta($id, '_elev8_follow_through_id', $follow_id);
        update_post_meta($id, '_elev8_work_item_id', $work_id ?: absint(get_post_meta($follow_id, '_elev8_work_item_id', true)));
        update_post_meta($id, '_elev8_organization_unit_id', absint(get_post_meta($follow_id, '_elev8_organization_unit_id', true)));
        if (!get_post_meta($id, '_elev8_effectiveness_result', true)) { update_post_meta($id, '_elev8_effectiveness_result', 'unknown'); }
        if (!get_post_meta($id, '_elev8_completed_at', true)) { update_post_meta($id, '_elev8_completed_at', current_time('mysql')); }
        return $id;
    }

    /** @return true|WP_Error */
    public static function record(int $follow_id, string $result, int $user_id, string $notes = '', string $metric_name = '', string $before = '', string $after = '') {
        $allowed = ['effective', 'partial', 'no_change', 'ineffective', 'unknown'];
        $result = sanitize_key($result);
        if (!in_array($result, $allowed, true)) {
            return new WP_Error('elev8_exec_effectiveness_invalid', __('Decision effectiveness result is invalid.', 'elev8-os'));
        }
        $id = self::ensure($follow_id);
        if (is_wp_error($id)) { return $id; }
        update_post_meta($id, '_elev8_effectiveness_result', $result);
        update_post_meta($id, '_elev8_effectiveness_notes', sanitize_textarea_field($notes));
        update_post_meta($id, '_elev8_metric_name', sanitize_text_field($metric_name));
        update_post_meta($id, '_elev8_metric_before', sanitize_text_field($before));
        update_post_meta($id, '_elev8_metric_after', sanitize_text_field($after));
        update_post_meta($id, '_elev8_recorded_by_user_id', absint($user_id));
        update_post_meta($id, '_elev8_recorded_at', current_time('mysql'));
        do_action('elev8_os_executive_decision_effectiveness_recorded', $id, $follow_id, $result, $user_id);
        return true;
    }

    public static function find_by_follow_through(int $follow_id): int {
        $ids = get_posts([
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_query' => [['key' => '_elev8_follow_through_id', 'value' => $follow_id, 'type' => 'NUMERIC']],
        ]);
        return $ids ? (int) $ids[0] : 0;
    }

    /** @return array<string,mixed> */
    public static function get_for_follow_through(int $follow_id): array {
        $id = self::find_by_follow_through($follow_id);
        if (!$id) { return []; }
        return [
            'id' => $id,
            'follow_through_id' => $follow_id,
            'work_item_id' => absint(get_post_meta($id, '_elev8_work_item_id', true)),
            'result' => (string) get_post_meta($id, '_elev8_effectiveness_result', true) ?: 'unknown',
            'notes' => (string) get_post_meta($id, '_elev8_effectiveness_notes', true),
            'metric_name' => (string) get_post_meta($id, '_elev8_metric_name', true),
            'metric_before' => (string) get_post_meta($id, '_elev8_metric_before', true),
            'metric_after' => (string) get_post_meta($id, '_elev8_metric_after', true),
            'recorded_by_user_id' => absint(get_post_meta($id, '_elev8_recorded_by_user_id', true)),
            'recorded_at' => (string) get_post_meta($id, '_elev8_recorded_at', true),
        ];
    }

    /** @return array<string,int|float> */
    public static function summary(int $organization_unit_id = 0): array {
        $meta = ['relation' => 'AND'];
        if ($organization_unit_id) { $meta[] = ['key' => '_elev8_organization_unit_id', 'value' => $organization_unit_id, 'type' => 'NUMERIC']; }
        $ids = get_posts(['post_type' => self::POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => 500, 'fields' => 'ids', 'meta_query' => $meta]);
        $summary = ['total' => count($ids), 'effective' => 0, 'partial' => 0, 'no_change' => 0, 'ineffective' => 0, 'unknown' => 0, 'measured' => 0, 'effectiveness_rate' => 0.0];
        foreach ($ids as $id) {
            $result = (string) get_post_meta($id, '_elev8_effectiveness_result', true) ?: 'unknown';
            if (isset($summary[$result])) { $summary[$result]++; }
            if ($result !== 'unknown') { $summary['measured']++; }
        }
        $positive = $summary['effective'] + ($summary['partial'] * 0.5);
        $summary['effectiveness_rate'] = $summary['measured'] > 0 ? round(($positive / $summary['measured']) * 100, 1) : 0.0;
        return $summary;
    }

    public static function register_graph_objects(array $objects): array {
        $objects['executive_decision_outcome'] = [
            'label' => __('Executive Decision Outcome', 'elev8-os'),
            'engine' => 'Intelligence',
            'authoritative_system' => 'elev8_os',
            'source_type' => self::POST_TYPE,
            'organization_scoped' => true,
            'notes' => __('Measured effectiveness of executive follow-through that is not already governed by Recommendation Outcome.', 'elev8-os'),
        ];
        return $objects;
    }

    public static function register_graph_relationships(array $relationships): array {
        $relationships['executive_follow_through_has_outcome'] = [
            'label' => __('Has decision outcome', 'elev8-os'),
            'from' => ['executive_decision_follow_through'],
            'to' => ['executive_decision_outcome'],
            'directional' => true,
            'notes' => __('Connects completed executive follow-through to its measured effectiveness.', 'elev8-os'),
        ];
        $relationships['executive_decision_outcome_evidenced_by_work'] = [
            'label' => __('Evidenced by work', 'elev8-os'),
            'from' => ['executive_decision_outcome'],
            'to' => ['work_item'],
            'directional' => true,
            'notes' => __('Connects effectiveness measurement to the completed delegated review or scheduled follow-up.', 'elev8-os'),
        ];
        return $relationships;
    }
}

<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Promotes acknowledged Patterns into explainable, human-governed Recommendations.
 *
 * Recommendations remain intelligence records until a leader explicitly approves
 * execution. Approval creates one linked Operations Work Item; rejection never
 * changes the Pattern, supporting Observations, or authoritative source records.
 */
final class Elev8_OS_Intelligence_Recommendation_Service {
    public const POST_TYPE = 'elev8_intel_rec';
    public const META_PATTERN_ID = '_elev8_intel_rec_pattern_id';
    public const META_FINGERPRINT = '_elev8_intel_rec_fingerprint';
    public const META_ORGANIZATION = '_elev8_intel_rec_organization_unit_id';
    public const META_CLASSIFICATION = '_elev8_intel_rec_classification';
    public const META_SEVERITY = '_elev8_intel_rec_severity';
    public const META_CONFIDENCE = '_elev8_intel_rec_confidence';
    public const META_EXPECTED_BENEFIT = '_elev8_intel_rec_expected_benefit';
    public const META_SUGGESTED_ACTION = '_elev8_intel_rec_suggested_action';
    public const META_SUGGESTED_OWNER = '_elev8_intel_rec_suggested_owner_user_id';
    public const META_EVIDENCE_IDS = '_elev8_intel_rec_observation_ids';
    public const META_STATUS = '_elev8_intel_rec_status';
    public const META_DECIDED_BY = '_elev8_intel_rec_decided_by_user_id';
    public const META_DECIDED_AT = '_elev8_intel_rec_decided_at';
    public const META_DECISION_NOTES = '_elev8_intel_rec_decision_notes';
    public const META_WORK_ID = '_elev8_intel_rec_work_item_id';

    public static function init(): void {
        add_action('init', [__CLASS__, 'register_post_type']);
        add_filter('elev8_os_business_graph_objects', [__CLASS__, 'register_graph_objects']);
        add_filter('elev8_os_business_graph_relationships', [__CLASS__, 'register_graph_relationships']);
    }

    public static function register_post_type(): void {
        register_post_type(self::POST_TYPE, [
            'labels' => ['name' => __('Recommendations', 'elev8-os'), 'singular_name' => __('Recommendation', 'elev8-os')],
            'public' => false,
            'show_ui' => false,
            'show_in_rest' => false,
            'supports' => ['title', 'editor'],
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ]);
    }

    /** Promote one acknowledged Pattern into one stable Recommendation. */
    public static function promote_pattern(int $pattern_id, int $user_id = 0) {
        if (!class_exists('Elev8_OS_Pattern_Detection_Service')) {
            return new WP_Error('pattern_service_unavailable', __('Pattern Detection is unavailable.', 'elev8-os'));
        }
        $pattern = Elev8_OS_Pattern_Detection_Service::get($pattern_id);
        if (!$pattern) { return new WP_Error('invalid_pattern', __('The Pattern could not be found.', 'elev8-os')); }
        if (($pattern['status'] ?? '') !== 'acknowledged') {
            return new WP_Error('pattern_not_acknowledged', __('A Pattern must be acknowledged before it can become a Recommendation.', 'elev8-os'));
        }

        $fingerprint = hash('sha256', 'pattern|'.$pattern_id);
        $existing = self::find($fingerprint);
        $classification = sanitize_key((string)($pattern['classification'] ?? 'follow_up'));
        $guidance = self::guidance($classification, (string)($pattern['severity'] ?? 'normal'), (string)($pattern['trend'] ?? 'stable'));
        $title = sprintf(__('Recommendation: %s', 'elev8-os'), (string)$pattern['title']);
        $summary = sprintf(
            __('Review and act on a %1$s Pattern supported by %2$d confirmed Observations. %3$s', 'elev8-os'),
            str_replace('_', ' ', $classification),
            (int)($pattern['occurrence_count'] ?? 0),
            $guidance['action']
        );
        $post = ['post_type'=>self::POST_TYPE,'post_status'=>'publish','post_title'=>$title,'post_content'=>$summary];
        if ($existing) { $post['ID'] = $existing; }
        $id = wp_insert_post($post, true);
        if (is_wp_error($id)) { return $id; }
        $id = (int)$id;

        update_post_meta($id, self::META_PATTERN_ID, $pattern_id);
        update_post_meta($id, self::META_FINGERPRINT, $fingerprint);
        update_post_meta($id, self::META_ORGANIZATION, absint($pattern['organization_unit_id'] ?? 0));
        update_post_meta($id, self::META_CLASSIFICATION, $classification);
        update_post_meta($id, self::META_SEVERITY, sanitize_key((string)($pattern['severity'] ?? 'normal')));
        update_post_meta($id, self::META_CONFIDENCE, absint($pattern['confidence'] ?? 0));
        update_post_meta($id, self::META_EXPECTED_BENEFIT, $guidance['benefit']);
        update_post_meta($id, self::META_SUGGESTED_ACTION, $guidance['action']);
        update_post_meta($id, self::META_EVIDENCE_IDS, array_map('absint', (array)($pattern['observation_ids'] ?? [])));
        if (!get_post_meta($id, self::META_STATUS, true)) { update_post_meta($id, self::META_STATUS, 'proposed'); }
        if ($user_id && !get_post_meta($id, self::META_SUGGESTED_OWNER, true)) {
            update_post_meta($id, self::META_SUGGESTED_OWNER, absint(apply_filters('elev8_os_recommendation_suggested_owner_user_id', 0, $pattern, $user_id)));
        }
        do_action('elev8_os_intelligence_recommendation_promoted', $id, $pattern_id);
        return $id;
    }

    /** @return array<string,string> */
    private static function guidance(string $classification, string $severity, string $trend): array {
        $increasing = $trend === 'increasing' ? __(' The frequency is increasing, so review it promptly.', 'elev8-os') : '';
        switch ($classification) {
            case 'risk':
                return ['action'=>__('Assign an owner to investigate the repeated risk, identify its root cause, and define a corrective action with a due date.', 'elev8-os').$increasing,'benefit'=>__('Reduces repeat failures, operational disruption, safety exposure, and unresolved risk.', 'elev8-os')];
            case 'opportunity':
                return ['action'=>__('Validate demand, estimate effort and value, and choose a small measurable next step.', 'elev8-os').$increasing,'benefit'=>__('Turns repeated demand into a testable growth opportunity without committing resources prematurely.', 'elev8-os')];
            case 'achievement':
                return ['action'=>__('Confirm what produced the repeated positive result and decide whether it should be recognized, documented, or repeated.', 'elev8-os'),'benefit'=>__('Preserves successful behavior and makes positive operating practices reusable.', 'elev8-os')];
            default:
                return ['action'=>__('Assign an owner to review the supporting evidence and define the appropriate next action.', 'elev8-os').$increasing,'benefit'=>__('Prevents recurring follow-up from remaining unresolved or becoming disconnected from accountable work.', 'elev8-os')];
        }
    }

    public static function find(string $fingerprint): int {
        $ids = get_posts(['post_type'=>self::POST_TYPE,'post_status'=>'publish','posts_per_page'=>1,'fields'=>'ids','meta_query'=>[['key'=>self::META_FINGERPRINT,'value'=>$fingerprint]]]);
        return $ids ? (int)$ids[0] : 0;
    }

    /** @return array<string,mixed> */
    public static function get(int $id): array {
        $post = get_post($id);
        if (!$post instanceof WP_Post || $post->post_type !== self::POST_TYPE) { return []; }
        $owner_id = absint(get_post_meta($id,self::META_SUGGESTED_OWNER,true));
        $owner = $owner_id ? get_user_by('id',$owner_id) : false;
        return [
            'id'=>$id,'title'=>get_the_title($id),'summary'=>(string)$post->post_content,
            'pattern_id'=>absint(get_post_meta($id,self::META_PATTERN_ID,true)),
            'organization_unit_id'=>absint(get_post_meta($id,self::META_ORGANIZATION,true)),
            'classification'=>(string)get_post_meta($id,self::META_CLASSIFICATION,true),
            'severity'=>(string)get_post_meta($id,self::META_SEVERITY,true) ?: 'normal',
            'confidence'=>absint(get_post_meta($id,self::META_CONFIDENCE,true)),
            'expected_benefit'=>(string)get_post_meta($id,self::META_EXPECTED_BENEFIT,true),
            'suggested_action'=>(string)get_post_meta($id,self::META_SUGGESTED_ACTION,true),
            'suggested_owner_user_id'=>$owner_id,
            'suggested_owner_name'=>$owner instanceof WP_User ? $owner->display_name : __('Unassigned', 'elev8-os'),
            'observation_ids'=>(array)get_post_meta($id,self::META_EVIDENCE_IDS,true),
            'status'=>(string)get_post_meta($id,self::META_STATUS,true) ?: 'proposed',
            'decided_by_user_id'=>absint(get_post_meta($id,self::META_DECIDED_BY,true)),
            'decided_at'=>(string)get_post_meta($id,self::META_DECIDED_AT,true),
            'decision_notes'=>(string)get_post_meta($id,self::META_DECISION_NOTES,true),
            'work_item_id'=>absint(get_post_meta($id,self::META_WORK_ID,true)),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public static function query(array $filters = []): array {
        $filters = wp_parse_args($filters,['status'=>'','classification'=>'','organization_unit_id'=>0,'posts_per_page'=>200]);
        $meta=['relation'=>'AND'];
        if ($filters['status'] !== '') { $meta[]=['key'=>self::META_STATUS,'value'=>sanitize_key((string)$filters['status'])]; }
        if ($filters['classification'] !== '') { $meta[]=['key'=>self::META_CLASSIFICATION,'value'=>sanitize_key((string)$filters['classification'])]; }
        if ($filters['organization_unit_id']) { $meta[]=['key'=>self::META_ORGANIZATION,'value'=>absint($filters['organization_unit_id']),'type'=>'NUMERIC']; }
        $posts=get_posts(['post_type'=>self::POST_TYPE,'post_status'=>'publish','posts_per_page'=>max(1,min(500,(int)$filters['posts_per_page'])),'orderby'=>'date','order'=>'DESC','meta_query'=>$meta]);
        return array_values(array_filter(array_map(static fn(WP_Post $post): array => self::get((int)$post->ID),$posts)));
    }

    /** Approve, reject, or return a Recommendation to proposed state. */
    public static function decide(int $id, string $status, int $user_id, string $notes = '', int $owner_user_id = 0) {
        if (get_post_type($id) !== self::POST_TYPE) { return new WP_Error('invalid_recommendation', __('The Recommendation could not be found.', 'elev8-os')); }
        $status = sanitize_key($status);
        if (!in_array($status,['proposed','approved','rejected'],true)) { return new WP_Error('invalid_recommendation_status', __('The Recommendation status is invalid.', 'elev8-os')); }
        if ($owner_user_id) { update_post_meta($id,self::META_SUGGESTED_OWNER,absint($owner_user_id)); }
        if ($status === 'approved') {
            $work_id = self::create_execution_work($id, $user_id);
            if (is_wp_error($work_id)) { return $work_id; }
        }
        update_post_meta($id,self::META_STATUS,$status);
        update_post_meta($id,self::META_DECIDED_BY,absint($user_id));
        update_post_meta($id,self::META_DECIDED_AT,current_time('mysql'));
        update_post_meta($id,self::META_DECISION_NOTES,sanitize_textarea_field($notes));
        do_action('elev8_os_intelligence_recommendation_decided',$id,$status,$user_id);
        return true;
    }

    private static function create_execution_work(int $id, int $user_id) {
        $existing = absint(get_post_meta($id,self::META_WORK_ID,true));
        if ($existing && get_post_status($existing)) { return $existing; }
        if (!class_exists('Elev8_OS_Operations_Engine_Service')) { return new WP_Error('operations_unavailable', __('The Operations Engine is unavailable.', 'elev8-os')); }
        $item = self::get($id);
        $priority = in_array($item['severity'],['critical','high'],true) ? 'high' : 'normal';
        $days = $item['severity'] === 'critical' ? 2 : 7;
        $description = $item['suggested_action']."\n\n".__('Expected benefit:', 'elev8-os').' '.$item['expected_benefit']."\n\n".sprintf(__('Evidence: Pattern #%1$d supported by %2$d confirmed Observations.', 'elev8-os'),(int)$item['pattern_id'],count((array)$item['observation_ids']));
        $work_id = Elev8_OS_Operations_Engine_Service::create_work([
            'title'=>$item['title'],'description'=>$description,'type'=>'general','status'=>'requested','priority'=>$priority,
            'owner_user_id'=>absint($item['suggested_owner_user_id']),'organization_unit_id'=>absint($item['organization_unit_id']),
            'due_date'=>wp_date('Y-m-d',time()+DAY_IN_SECONDS*$days),'source_type'=>'intelligence_recommendation','source_id'=>$id,
            'workflow_key'=>'intelligence_recommendation','step_key'=>'execute','requested_by_user_id'=>$user_id,
        ]);
        if (is_wp_error($work_id)) { return $work_id; }
        update_post_meta($id,self::META_WORK_ID,absint($work_id));
        return $work_id;
    }

    public static function register_graph_objects(array $objects): array {
        $objects['recommendation']=['label'=>__('Recommendation','elev8-os'),'engine'=>'Intelligence','authoritative_system'=>'elev8_os','source_type'=>self::POST_TYPE,'organization_scoped'=>true,'notes'=>__('Explainable proposed action promoted from an acknowledged Pattern.','elev8-os')];
        return $objects;
    }
    public static function register_graph_relationships(array $relationships): array {
        $relationships['recommendation_derived_from']=['label'=>__('Derived from','elev8-os'),'from'=>['recommendation'],'to'=>['pattern'],'directional'=>true,'notes'=>__('Connects a Recommendation to the acknowledged Pattern that supports it.','elev8-os')];
        $relationships['recommendation_executes_as']=['label'=>__('Executes as','elev8-os'),'from'=>['recommendation'],'to'=>['work_item'],'directional'=>true,'notes'=>__('Created only after a leader explicitly approves execution.','elev8-os')];
        return $relationships;
    }
}

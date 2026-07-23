<?php
if (!defined('ABSPATH')) { exit; }

/** Tracks the measurable result of an approved Recommendation after execution. */
final class Elev8_OS_Recommendation_Outcome_Service {
    public const POST_TYPE = 'elev8_intel_outcome';
    public const META_RECOMMENDATION_ID = '_elev8_outcome_recommendation_id';
    public const META_WORK_ID = '_elev8_outcome_work_item_id';
    public const META_ORGANIZATION = '_elev8_outcome_organization_unit_id';
    public const META_RESULT = '_elev8_outcome_result';
    public const META_NOTES = '_elev8_outcome_notes';
    public const META_METRIC_NAME = '_elev8_outcome_metric_name';
    public const META_METRIC_BEFORE = '_elev8_outcome_metric_before';
    public const META_METRIC_AFTER = '_elev8_outcome_metric_after';
    public const META_RECORDED_BY = '_elev8_outcome_recorded_by_user_id';
    public const META_RECORDED_AT = '_elev8_outcome_recorded_at';
    public const META_COMPLETED_AT = '_elev8_outcome_work_completed_at';

    public static function init(): void {
        add_action('init', [__CLASS__, 'register_post_type']);
        add_action('elev8_os_operations_work_status_changed', [__CLASS__, 'work_status_changed'], 10, 3);
        add_filter('elev8_os_business_graph_objects', [__CLASS__, 'register_graph_objects']);
        add_filter('elev8_os_business_graph_relationships', [__CLASS__, 'register_graph_relationships']);
    }

    public static function register_post_type(): void {
        register_post_type(self::POST_TYPE, [
            'labels'=>['name'=>__('Recommendation Outcomes','elev8-os'),'singular_name'=>__('Recommendation Outcome','elev8-os')],
            'public'=>false,'show_ui'=>false,'show_in_rest'=>false,'supports'=>['title','editor'],
            'capability_type'=>'post','map_meta_cap'=>true,
        ]);
    }

    public static function work_status_changed(int $work_id, string $status, string $before): void {
        if ($status !== 'completed' || $before === 'completed') { return; }
        $source_type = (string)get_post_meta($work_id, '_elev8_work_source_type', true);
        $recommendation_id = absint(get_post_meta($work_id, '_elev8_work_source_id', true));
        if ($source_type !== 'intelligence_recommendation' || !$recommendation_id) { return; }
        self::ensure_for_recommendation($recommendation_id, $work_id);
    }

    public static function ensure_for_recommendation(int $recommendation_id, int $work_id = 0) {
        if (!class_exists('Elev8_OS_Intelligence_Recommendation_Service')) {
            return new WP_Error('recommendation_service_unavailable', __('Recommendation service is unavailable.', 'elev8-os'));
        }
        $recommendation = Elev8_OS_Intelligence_Recommendation_Service::get($recommendation_id);
        if (!$recommendation) { return new WP_Error('invalid_recommendation', __('Recommendation not found.', 'elev8-os')); }
        $work_id = $work_id ?: absint($recommendation['work_item_id'] ?? 0);
        if (!$work_id) { return new WP_Error('recommendation_has_no_work', __('Recommendation does not have approved work.', 'elev8-os')); }
        $existing = self::find_by_recommendation($recommendation_id);
        $post = [
            'post_type'=>self::POST_TYPE,'post_status'=>'publish',
            'post_title'=>sprintf(__('Outcome: %s','elev8-os'), (string)$recommendation['title']),
            'post_content'=>__('Execution completed. Outcome measurement is awaiting leader review.','elev8-os'),
        ];
        if ($existing) { $post['ID']=$existing; }
        $id=wp_insert_post($post,true); if(is_wp_error($id)){return $id;} $id=(int)$id;
        update_post_meta($id,self::META_RECOMMENDATION_ID,$recommendation_id);
        update_post_meta($id,self::META_WORK_ID,$work_id);
        update_post_meta($id,self::META_ORGANIZATION,absint($recommendation['organization_unit_id']??0));
        if(!get_post_meta($id,self::META_RESULT,true)){update_post_meta($id,self::META_RESULT,'unknown');}
        if(!get_post_meta($id,self::META_COMPLETED_AT,true)){
            $completed=(string)get_post_meta($work_id,'_elev8_work_completed_at',true);
            update_post_meta($id,self::META_COMPLETED_AT,$completed?:current_time('mysql'));
        }
        do_action('elev8_os_recommendation_outcome_created',$id,$recommendation_id,$work_id);
        return $id;
    }

    public static function record(int $recommendation_id, string $result, int $user_id, string $notes='', string $metric_name='', string $before='', string $after='') {
        $allowed=['successful','partial','no_change','unsuccessful','unknown'];
        $result=sanitize_key($result);
        if(!in_array($result,$allowed,true)){return new WP_Error('invalid_outcome_result',__('Outcome result is invalid.','elev8-os'));}
        $id=self::ensure_for_recommendation($recommendation_id); if(is_wp_error($id)){return $id;}
        update_post_meta($id,self::META_RESULT,$result);
        update_post_meta($id,self::META_NOTES,sanitize_textarea_field($notes));
        update_post_meta($id,self::META_METRIC_NAME,sanitize_text_field($metric_name));
        update_post_meta($id,self::META_METRIC_BEFORE,sanitize_text_field($before));
        update_post_meta($id,self::META_METRIC_AFTER,sanitize_text_field($after));
        update_post_meta($id,self::META_RECORDED_BY,absint($user_id));
        update_post_meta($id,self::META_RECORDED_AT,current_time('mysql'));
        do_action('elev8_os_recommendation_outcome_recorded',$id,$recommendation_id,$result,$user_id);
        return true;
    }

    public static function find_by_recommendation(int $recommendation_id): int {
        $ids=get_posts(['post_type'=>self::POST_TYPE,'post_status'=>'publish','posts_per_page'=>1,'fields'=>'ids','meta_query'=>[['key'=>self::META_RECOMMENDATION_ID,'value'=>$recommendation_id,'type'=>'NUMERIC']]]);
        return $ids?(int)$ids[0]:0;
    }

    /** @return array<string,mixed> */
    public static function get_for_recommendation(int $recommendation_id): array {
        $id=self::find_by_recommendation($recommendation_id); if(!$id){return [];}
        return [
            'id'=>$id,'recommendation_id'=>$recommendation_id,
            'work_item_id'=>absint(get_post_meta($id,self::META_WORK_ID,true)),
            'organization_unit_id'=>absint(get_post_meta($id,self::META_ORGANIZATION,true)),
            'result'=>(string)get_post_meta($id,self::META_RESULT,true)?:'unknown',
            'notes'=>(string)get_post_meta($id,self::META_NOTES,true),
            'metric_name'=>(string)get_post_meta($id,self::META_METRIC_NAME,true),
            'metric_before'=>(string)get_post_meta($id,self::META_METRIC_BEFORE,true),
            'metric_after'=>(string)get_post_meta($id,self::META_METRIC_AFTER,true),
            'recorded_by_user_id'=>absint(get_post_meta($id,self::META_RECORDED_BY,true)),
            'recorded_at'=>(string)get_post_meta($id,self::META_RECORDED_AT,true),
            'work_completed_at'=>(string)get_post_meta($id,self::META_COMPLETED_AT,true),
        ];
    }

    /** @return array<string,int|float> */
    public static function summary(int $organization_unit_id=0): array {
        $meta=['relation'=>'AND']; if($organization_unit_id){$meta[]=['key'=>self::META_ORGANIZATION,'value'=>$organization_unit_id,'type'=>'NUMERIC'];}
        $ids=get_posts(['post_type'=>self::POST_TYPE,'post_status'=>'publish','posts_per_page'=>500,'fields'=>'ids','meta_query'=>$meta]);
        $summary=['total'=>count($ids),'successful'=>0,'partial'=>0,'no_change'=>0,'unsuccessful'=>0,'unknown'=>0,'measured'=>0,'success_rate'=>0.0];
        foreach($ids as $id){$result=(string)get_post_meta($id,self::META_RESULT,true)?:'unknown'; if(isset($summary[$result])){$summary[$result]++;} if($result!=='unknown'){$summary['measured']++;}}
        $positive=$summary['successful']+($summary['partial']*0.5);
        $summary['success_rate']=$summary['measured']>0?round(($positive/$summary['measured'])*100,1):0.0;
        return $summary;
    }

    public static function register_graph_objects(array $objects): array {
        $objects['recommendation_outcome']=['label'=>__('Recommendation Outcome','elev8-os'),'engine'=>'Intelligence','authoritative_system'=>'elev8_os','source_type'=>self::POST_TYPE,'organization_scoped'=>true,'notes'=>__('Measured result of approved recommendation execution.','elev8-os')];
        return $objects;
    }
    public static function register_graph_relationships(array $relationships): array {
        $relationships['recommendation_has_outcome']=['label'=>__('Has outcome','elev8-os'),'from'=>['recommendation'],'to'=>['recommendation_outcome'],'directional'=>true,'notes'=>__('Connects approved intelligence to its measured result.','elev8-os')];
        $relationships['outcome_evidenced_by_work']=['label'=>__('Evidenced by work','elev8-os'),'from'=>['recommendation_outcome'],'to'=>['work_item'],'directional'=>true,'notes'=>__('Connects outcome measurement to the completed execution record.','elev8-os')];
        return $relationships;
    }
}

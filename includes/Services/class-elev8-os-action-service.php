<?php
if (!defined('ABSPATH')) { exit; }
final class Elev8_OS_Action_Service {
    public static function work_items(WP_User $user, bool $team = false): array {
        if (!class_exists('Elev8_OS_Work_Service')) { return []; }
        $meta = [['key'=>'_elev8_work_status','value'=>['new','assigned','in_progress','waiting'],'compare'=>'IN']];
        if (!$team) { $meta[]=['key'=>'_elev8_work_owner_user_id','value'=>$user->ID,'type'=>'NUMERIC']; }
        return get_posts(['post_type'=>Elev8_OS_Work_Service::POST_TYPE,'post_status'=>'publish','posts_per_page'=>50,'orderby'=>'meta_value','meta_key'=>'_elev8_work_due_date','order'=>'ASC','meta_query'=>$meta]);
    }
    public static function manager_logs(int $limit = 12): array {
        if (!class_exists('Elev8_OS_Daily_Operations_Service')) { return []; }
        return get_posts(['post_type'=>Elev8_OS_Daily_Operations_Service::POST_TYPE,'post_status'=>'publish','posts_per_page'=>$limit,'orderby'=>'date','order'=>'DESC','meta_query'=>[['key'=>Elev8_OS_Daily_Operations_Service::META_TEMPLATE,'value'=>'manager']]]);
    }
    public static function manager_log_url(int $id): string { return add_query_arg(['page'=>'elev8-daily-operations','view'=>'entry','entry_id'=>$id],admin_url('admin.php')); }
}

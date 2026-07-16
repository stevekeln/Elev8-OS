<?php
if (!defined('ABSPATH')) { exit; }

/** Single dashboard snapshot consumed by UI, reports, and future AI. */
final class Elev8_OS_Artist_Business_Service {
    /** @return array<string,mixed> */
    public static function get_snapshot(WP_User $user): array {
        $class_snapshot = class_exists('Elev8_OS_My_Classes_Module') ? Elev8_OS_My_Classes_Module::get_dashboard_snapshot($user) : ['available'=>false,'summary'=>[],'upcoming'=>[],'artist'=>null,'reason'=>__('Class service unavailable.','elev8-os')];
        $assets = class_exists('Elev8_OS_Asset_Service') ? Elev8_OS_Asset_Service::get_for_owner((int)$user->ID) : [];
        $asset_metrics = self::asset_metrics($assets);
        $sales = self::sales_metrics($user, $assets);
        $profile = self::profile_metrics($user, $class_snapshot['artist'] ?? null);
        $classes = is_array($class_snapshot['summary']??null) ? $class_snapshot['summary'] : [];
        $signals=[
            'profile'=>$profile['score'],
            'artwork'=>min(100, ((int)$asset_metrics['total']*20) + ((int)$asset_metrics['complete_count']*10)),
            'classes'=>min(100, ((int)($classes['upcoming_count']??0)*25) + ((int)($classes['student_count']??0)*5)),
            'sales'=>min(100, ((int)$sales['count_all']*15)),
            'engagement'=>min(100, ((int)$asset_metrics['views']*2) + ((int)$asset_metrics['qr_scans']*5)),
            'website'=>$profile['score'],
        ];
        $snapshot=[
            'generated_at'=>current_time('mysql'), 'user_id'=>(int)$user->ID,
            'artist'=>$class_snapshot['artist']??null, 'class_available'=>!empty($class_snapshot['available']),
            'class_reason'=>(string)($class_snapshot['reason']??''), 'upcoming'=>(array)($class_snapshot['upcoming']??[]),
            'classes'=>$classes, 'assets'=>$asset_metrics, 'sales'=>$sales, 'profile'=>$profile,
        ];
        $snapshot['score']=Elev8_OS_Business_Score_Engine::calculate($signals);
        $snapshot['recommendations']=Elev8_OS_Recommendation_Engine::recommend($snapshot);
        $snapshot['achievements']=Elev8_OS_Achievement_Service::evaluate($snapshot);
        $snapshot['growth_plan']=Elev8_OS_Growth_Plan_Service::build($snapshot);
        return $snapshot;
    }
    /** @param array<int,array<string,mixed>> $assets */
    private static function asset_metrics(array $assets): array {
        $m=['total'=>count($assets),'public'=>0,'available'=>0,'sold'=>0,'low_inventory'=>0,'views'=>0,'qr_scans'=>0,'complete_count'=>0,'most_viewed'=>null,'recent'=>[]];
        foreach($assets as $a){
            if(!empty($a['public_visibility']))$m['public']++;
            if(($a['status']??'')==='available')$m['available']++;
            if(($a['status']??'')==='sold')$m['sold']++;
            if(($a['status']??'')==='available' && (int)($a['quantity']??0)<=1)$m['low_inventory']++;
            $m['views']+=(int)($a['public_view_count']??0); $m['qr_scans']+=(int)($a['qr_scan_count']??0);
            if(class_exists('Elev8_OS_Asset_Service') && Elev8_OS_Asset_Service::calculate_completeness($a)>=80)$m['complete_count']++;
            if($m['most_viewed']===null || (int)($a['public_view_count']??0)>(int)($m['most_viewed']['public_view_count']??0))$m['most_viewed']=$a;
        }
        $m['recent']=array_slice($assets,0,3); return $m;
    }
    /** @param array<int,array<string,mixed>> $assets */
    private static function sales_metrics(WP_User $user,array $assets): array {
        $fallback=['available'=>false,'count_month'=>null,'revenue_month'=>null,'count_all'=>(int)array_sum(array_map(static fn($a)=>(($a['status']??'')==='sold'?1:0),$assets)),'revenue_all'=>null,'recent'=>[],'reason'=>__('WooCommerce paid-order data is unavailable.','elev8-os')];
        if(!function_exists('wc_get_orders'))return $fallback;
        $product_map=[]; foreach($assets as $a){$pid=absint($a['wc_product_id']??0);if($pid>0)$product_map[$pid]=$a;}
        if(!$product_map)return array_merge($fallback,['available'=>true,'count_month'=>0,'revenue_month'=>0.0,'count_all'=>0,'revenue_all'=>0.0,'reason'=>'']);
        $orders=wc_get_orders(['status'=>['processing','completed'],'limit'=>-1,'return'=>'objects']);
        $month_start=strtotime(wp_date('Y-m-01 00:00:00')); $out=['available'=>true,'count_month'=>0,'revenue_month'=>0.0,'count_all'=>0,'revenue_all'=>0.0,'recent'=>[],'reason'=>''];
        foreach($orders as $order){foreach($order->get_items() as $item){$pid=(int)$item->get_product_id();if(!isset($product_map[$pid]))continue;$total=(float)$item->get_total();$out['count_all']+=(int)$item->get_quantity();$out['revenue_all']+=$total;$created=$order->get_date_created();$ts=$created?$created->getTimestamp():0;if($ts>=$month_start){$out['count_month']+=(int)$item->get_quantity();$out['revenue_month']+=$total;}if(count($out['recent'])<3)$out['recent'][]=['title'=>$item->get_name(),'total'=>$total,'date'=>$ts];}}
        return $out;
    }
    /** @param mixed $artist */
    private static function profile_metrics(WP_User $user,$artist): array {
        $checks=[trim((string)$user->display_name)!=='',sanitize_email((string)$user->user_email)!=='',trim((string)get_user_meta($user->ID,'description',true))!=='',is_array($artist)];
        $score=(int)round((array_sum(array_map(static fn($v)=>$v?1:0,$checks))/count($checks))*100);
        return ['score'=>$score,'complete'=>$score>=75];
    }
}

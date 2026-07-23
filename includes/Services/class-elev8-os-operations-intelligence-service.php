<?php
if (!defined('ABSPATH')) { exit; }
final class Elev8_OS_Operations_Intelligence_Service {
    public static function daily_brief(string $date = ''): array {
        $date = $date ?: current_time('Y-m-d');
        $q = new WP_Query(['post_type'=>Elev8_OS_Daily_Operations_Service::POST_TYPE,'post_status'=>'publish','posts_per_page'=>100,'date_query'=>[['after'=>$date.' 00:00:00','before'=>$date.' 23:59:59','inclusive'=>true]]]);
        $summary=[];$urgent=[];$waiting=[];$completed=[];$signals=[];
        foreach($q->posts as $post){
            $e=Elev8_OS_Daily_Operations_Service::entry((int)$post->ID); if(!$e) continue;
            $template=Elev8_OS_Daily_Operations_Service::template($e['template_key']);
            $author=get_the_author_meta('display_name',(int)$post->post_author);
            $summary[]=sprintf('%s submitted %s%s.',$author,$template['name']??'an operating log',$e['location']?' for '.$e['location']:'');
            if($e['attention']){$urgent[]=$post->post_title;}
            if($e['status']==='waiting'){$waiting[]=$post->post_title;}
            if($e['status']==='completed'){$completed[]=$post->post_title;}
            foreach($e['fields'] as $key=>$value){ if(trim((string)$value)===''||$value==='No') continue; $signals[]=strtolower((string)$value); }
        }
        $recommendations=self::rule_recommendations($signals);
        return ['date'=>$date,'entry_count'=>(int)$q->found_posts,'summary'=>array_slice($summary,0,8),'urgent'=>array_slice($urgent,0,8),'waiting'=>array_slice($waiting,0,8),'completed'=>array_slice($completed,0,8),'recommendations'=>$recommendations];
    }
    private static function rule_recommendations(array $signals): array {
        $text=implode(' ',$signals); $rules=[
            'parking'=>__('Parking was mentioned. Review access, signs, and customer parking instructions.','elev8-os'),
            'pottery'=>__('Pottery demand was mentioned. Consider validating demand with a class opportunity.','elev8-os'),
            'beginner'=>__('Beginner offerings were mentioned. Review whether another beginner session should be added.','elev8-os'),
            'inventory'=>__('Inventory concerns were mentioned. Review low-stock items before the next shift.','elev8-os'),
            'complaint'=>__('A customer complaint was reported. Assign an owner and document the resolution.','elev8-os'),
            'repair'=>__('A repair need was mentioned. Confirm priority, assignment, and completion date.','elev8-os'),
        ]; $out=[]; foreach($rules as $needle=>$message){if(strpos($text,$needle)!==false)$out[]=$message;} return array_values(array_unique($out));
    }
    public static function trend_terms(int $days=30): array {
        $after=date('Y-m-d',current_time('timestamp')-($days*DAY_IN_SECONDS));
        $q=new WP_Query(['post_type'=>Elev8_OS_Daily_Operations_Service::POST_TYPE,'post_status'=>'publish','posts_per_page'=>300,'date_query'=>[['after'=>$after,'inclusive'=>true]]]);
        $terms=['parking','inventory','beginner','complaint','maintenance','repair','customer request','supplies','equipment','class'];$counts=array_fill_keys($terms,0);
        foreach($q->posts as $p){$text=strtolower(wp_strip_all_tags($p->post_content));foreach($terms as $term){$counts[$term]+=substr_count($text,$term);}}
        arsort($counts); return array_filter($counts);
    }
}

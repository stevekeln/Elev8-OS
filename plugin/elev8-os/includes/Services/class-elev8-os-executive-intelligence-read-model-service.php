<?php
if (!defined('ABSPATH')) { exit; }

/** Explainable executive read model assembled from governed Intelligence evidence. */
final class Elev8_OS_Executive_Intelligence_Read_Model_Service {
    /** @return array<string,mixed> */
    public static function report(int $organization_unit_id=0): array {
        $patterns=class_exists('Elev8_OS_Pattern_Detection_Service')?Elev8_OS_Pattern_Detection_Service::query(['status'=>'','organization_unit_id'=>$organization_unit_id,'posts_per_page'=>500]):[];
        $recommendations=class_exists('Elev8_OS_Intelligence_Recommendation_Service')?Elev8_OS_Intelligence_Recommendation_Service::query(['organization_unit_id'=>$organization_unit_id,'posts_per_page'=>500]):[];
        $observations=self::observation_summary($organization_unit_id);
        $performance=class_exists('Elev8_OS_Business_Score_Service')?Elev8_OS_Business_Score_Service::recommendation_performance($organization_unit_id):['available'=>false,'score'=>null,'label'=>__('Unavailable','elev8-os'),'explanations'=>[]];
        $active=array_values(array_filter($patterns,static fn(array $p):bool=>in_array((string)($p['status']??''),['active','acknowledged'],true)));
        $risks=self::rank($active,'risk'); $opportunities=self::rank($active,'opportunity');
        $rec=self::recommendation_summary($recommendations);
        return ['generated_at'=>current_time('mysql'),'organization_unit_id'=>$organization_unit_id,'observation_summary'=>$observations,
            'pattern_summary'=>['active'=>count(array_filter($active,static fn(array $p):bool=>($p['status']??'')==='active')),'acknowledged'=>count(array_filter($active,static fn(array $p):bool=>($p['status']??'')==='acknowledged')),'risks'=>count($risks),'opportunities'=>count($opportunities)],
            'recommendation_summary'=>$rec,'performance'=>$performance,'top_risks'=>array_slice($risks,0,5),'top_opportunities'=>array_slice($opportunities,0,5),
            'attention'=>array_slice(self::attention($risks,$opportunities,$recommendations),0,7),'confidence'=>self::confidence($observations,$patterns,$recommendations,$performance)];
    }
    private static function observation_summary(int $org): array {
        if(!class_exists('Elev8_OS_Observation_Service')){return [];}
        $items=Elev8_OS_Observation_Service::query(['organization_unit_id'=>$org,'posts_per_page'=>500]);
        $s=['total'=>count($items),'risk'=>0,'opportunity'=>0,'decision'=>0,'achievement'=>0,'follow_up'=>0,'critical'=>0,'high'=>0];
        foreach($items as $item){foreach((array)($item['classifications']??[]) as $c){if(isset($s[$c])){$s[$c]++;}}$v=(string)($item['severity']??'normal');if(isset($s[$v])){$s[$v]++;}}
        return $s;
    }
    private static function rank(array $patterns,string $classification): array {
        $items=array_values(array_filter($patterns,static fn(array $p):bool=>(string)($p['classification']??'')===$classification));
        foreach($items as &$item){$item['executive_score']=self::score($item);}unset($item);
        usort($items,static fn(array $a,array $b):int=>((int)$b['executive_score'])<=>((int)$a['executive_score'])); return $items;
    }
    private static function score(array $p): int {
        $rank=['low'=>10,'normal'=>25,'high'=>55,'critical'=>85];$score=$rank[(string)($p['severity']??'normal')]??25;
        $score+=min(30,max(0,(int)($p['occurrence_count']??0)*4));$score+=(($p['trend']??'')==='increasing'?15:0);$score+=(($p['status']??'')==='acknowledged'?5:0);$score+=(int)round(((int)($p['confidence']??0))/10);return min(100,$score);
    }
    private static function recommendation_summary(array $items): array {
        $s=['total'=>count($items),'proposed'=>0,'approved'=>0,'rejected'=>0,'awaiting_outcome'=>0,'measured'=>0];
        foreach($items as $item){$status=(string)($item['status']??'proposed');if(isset($s[$status])){$s[$status]++;}if($status==='approved'){$o=is_array($item['outcome']??null)?$item['outcome']:[];$r=(string)($o['result']??'');if($r===''||$r==='unknown'){$s['awaiting_outcome']++;}else{$s['measured']++;}}} return $s;
    }
    private static function attention(array $risks,array $opportunities,array $recommendations): array {
        $items=[];
        foreach(array_slice($risks,0,3) as $p){$items[]=['kind'=>'risk','priority'=>(int)$p['executive_score'],'title'=>(string)$p['title'],'reason'=>sprintf(__('%1$s risk supported by %2$d confirmed observations; trend is %3$s.','elev8-os'),ucfirst((string)$p['severity']),(int)$p['occurrence_count'],(string)$p['trend']),'target'=>'patterns'];}
        foreach($recommendations as $r){$status=(string)($r['status']??'proposed');if($status==='proposed'){$items[]=['kind'=>'decision','priority'=>75+(($r['severity']??'')==='critical'?20:0),'title'=>(string)$r['title'],'reason'=>__('A governed Recommendation is waiting for an executive decision.','elev8-os'),'target'=>'recommendations'];}elseif($status==='approved'){$o=is_array($r['outcome']??null)?$r['outcome']:[];if($o&&(string)($o['result']??'unknown')==='unknown'){$items[]=['kind'=>'measurement','priority'=>62,'title'=>(string)$r['title'],'reason'=>__('The approved action still needs a measured business outcome.','elev8-os'),'target'=>'recommendations'];}}}
        foreach(array_slice($opportunities,0,2) as $p){$items[]=['kind'=>'opportunity','priority'=>max(35,(int)$p['executive_score']-10),'title'=>(string)$p['title'],'reason'=>sprintf(__('%1$d confirmed observations support this %2$s opportunity.','elev8-os'),(int)$p['occurrence_count'],(string)$p['trend']),'target'=>'patterns'];}
        usort($items,static fn(array $a,array $b):int=>((int)$b['priority'])<=>((int)$a['priority']));return $items;
    }
    private static function confidence(array $o,array $p,array $r,array $performance): array {
        $sources=(!empty($o)?1:0)+(!empty($p)?1:0)+(!empty($r)?1:0)+(!empty($performance['available'])?1:0);$level=$sources>=4?'high':($sources>=2?'medium':'low');
        return ['level'=>$level,'label'=>ucfirst($level),'sources'=>$sources,'explanation'=>sprintf(__('Executive Intelligence is using %d governed evidence layers. Missing reviewed observations, patterns, recommendations, or measured outcomes lowers confidence.','elev8-os'),$sources)];
    }
}

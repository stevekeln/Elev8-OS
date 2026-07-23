<?php
if (!defined('ABSPATH')) { exit; }

/** Explainable intelligence performance score derived from governed outcomes. */
final class Elev8_OS_Business_Score_Service {
    /** @return array<string,mixed> */
    public static function recommendation_performance(int $organization_unit_id=0): array {
        if(!class_exists('Elev8_OS_Recommendation_Outcome_Service')){return ['available'=>false,'score'=>null,'label'=>__('Unavailable','elev8-os'),'explanations'=>[]];}
        $s=Elev8_OS_Recommendation_Outcome_Service::summary($organization_unit_id);
        if((int)$s['measured']===0){return ['available'=>false,'score'=>null,'label'=>__('Awaiting measured outcomes','elev8-os'),'summary'=>$s,'explanations'=>[__('Complete approved work and record an outcome to establish a score.','elev8-os')]];}
        $score=(int)round((float)$s['success_rate']);
        $explanations=[
            sprintf(_n('%d outcome has been measured.','%d outcomes have been measured.',(int)$s['measured'],'elev8-os'),(int)$s['measured']),
            sprintf(_n('%d recommendation was successful.','%d recommendations were successful.',(int)$s['successful'],'elev8-os'),(int)$s['successful']),
        ];
        if((int)$s['partial']>0){$explanations[]=sprintf(_n('%d recommendation was partially successful.','%d recommendations were partially successful.',(int)$s['partial'],'elev8-os'),(int)$s['partial']);}
        if((int)$s['unsuccessful']>0){$explanations[]=sprintf(_n('%d recommendation was unsuccessful.','%d recommendations were unsuccessful.',(int)$s['unsuccessful'],'elev8-os'),(int)$s['unsuccessful']);}
        return ['available'=>true,'score'=>$score,'label'=>self::label($score),'summary'=>$s,'explanations'=>$explanations];
    }
    private static function label(int $score): string { if($score>=85)return __('Highly effective','elev8-os'); if($score>=70)return __('Effective','elev8-os'); if($score>=50)return __('Mixed results','elev8-os'); return __('Needs review','elev8-os'); }
}

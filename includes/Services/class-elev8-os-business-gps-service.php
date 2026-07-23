<?php
if (!defined('ABSPATH')) { exit; }

/** Builds one reusable Business GPS model for artist, owner, API, and future AI clients. */
final class Elev8_OS_Business_GPS_Service {
    /** @param array<string,mixed> $snapshot @return array<string,mixed> */
    public static function build(WP_User $user,array $snapshot): array {
        $opportunities=Elev8_OS_Opportunity_Engine::build($snapshot);
        $items=(array)($opportunities['items']??[]); $best=$items[0]??null;
        $score=(array)($snapshot['score']??[]);
        $sales=(array)($snapshot['sales']??[]); $classes=(array)($snapshot['classes']??[]);
        $revenue=is_numeric($sales['revenue_month']??null)?(float)$sales['revenue_month']:null;
        if (is_numeric($classes['booked_value']??null)) $revenue=($revenue??0)+(float)$classes['booked_value'];
        return [
            'score'=>$score['score']??null,'score_label'=>$score['label']??__('Unavailable','elev8-os'),
            'revenue_month'=>$revenue,'highest_opportunity'=>$best,'opportunities'=>$opportunities,
            'biggest_risk'=>self::risk($snapshot),'timeline'=>Elev8_OS_Business_Event_Service::timeline($snapshot),
            'calendar'=>Elev8_OS_Content_Calendar_Service::week($snapshot),'scheduling'=>Elev8_OS_Predictive_Scheduling_Service::analyze($snapshot),
        ];
    }
    /** @param array<string,mixed> $snapshot */
    private static function risk(array $snapshot): array {
        $score=(int)($snapshot['score']['score']??0); $recs=(array)($snapshot['recommendations']??[]);
        if (!empty($recs[0])) return ['title'=>(string)($recs[0]['title']??__('Needs attention','elev8-os')),'detail'=>(string)($recs[0]['message']??'')];
        if ($score<70) return ['title'=>__('Business score needs attention','elev8-os'),'detail'=>__('Open the score categories to see the weakest verified area.','elev8-os')];
        return ['title'=>__('No major verified risk','elev8-os'),'detail'=>__('Continue the current plan and review new data as it arrives.','elev8-os')];
    }
}

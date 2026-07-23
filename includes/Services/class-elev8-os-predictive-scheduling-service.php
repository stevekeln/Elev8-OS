<?php
if (!defined('ABSPATH')) { exit; }

/** Conservative scheduling guidance. Predictions remain unavailable without sufficient verified history. */
final class Elev8_OS_Predictive_Scheduling_Service {
    /** @param array<string,mixed> $snapshot @return array<string,mixed> */
    public static function analyze(array $snapshot): array {
        $classes=(array)($snapshot['classes']??[]); $upcoming=(array)($snapshot['upcoming']??[]);
        $count=(int)($classes['upcoming_count']??0); $students=(int)($classes['student_count']??0);
        $seats=is_numeric($classes['seats_available']??null)?(int)$classes['seats_available']:null;
        if ($count===0) return ['available'=>true,'status'=>'gap','title'=>__('Schedule the next class', 'elev8-os'),'message'=>__('No upcoming Amelia class is verified for this artist.', 'elev8-os'),'confidence'=>'high'];
        if ($seats!==null && $seats<=2 && $students>0) return ['available'=>true,'status'=>'demand','title'=>__('Consider another class date', 'elev8-os'),'message'=>__('Current verified classes have two or fewer seats remaining. Demand may support another session.', 'elev8-os'),'confidence'=>'medium'];
        if (count($upcoming)<3) return ['available'=>false,'status'=>'history','title'=>__('More history needed', 'elev8-os'),'message'=>__('Predictive day-and-time recommendations will appear after Elev8 OS has enough verified class history.', 'elev8-os'),'confidence'=>'unavailable'];
        return ['available'=>false,'status'=>'history','title'=>__('No scheduling change recommended', 'elev8-os'),'message'=>__('Current verified data does not support a stronger scheduling recommendation yet.', 'elev8-os'),'confidence'=>'unavailable'];
    }
}

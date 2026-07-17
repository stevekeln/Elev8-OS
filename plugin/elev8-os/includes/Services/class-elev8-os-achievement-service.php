<?php
if (!defined('ABSPATH')) { exit; }

/** Reusable milestone evaluator. Achievements are calculated from verified facts. */
final class Elev8_OS_Achievement_Service {
    /** Future achievement providers can inspect verified recommendation completions here. */
    public static function recommendation_trigger(int $user_id, string $recommendation_id, array $recommendation = []): array {
        return apply_filters('elev8_os_recommendation_achievement_triggers', [], $user_id, sanitize_key($recommendation_id), $recommendation);
    }
    /** @param array<string,mixed> $snapshot @return array<int,array<string,mixed>> */
    public static function evaluate(array $snapshot): array {
        $assets=(array)($snapshot['assets']??[]); $sales=(array)($snapshot['sales']??[]); $classes=(array)($snapshot['classes']??[]);
        $defs=[
            ['key'=>'first_artwork','title'=>__('First Artwork','elev8-os'),'earned'=>(int)($assets['total']??0)>=1,'current'=>(int)($assets['total']??0),'target'=>1],
            ['key'=>'first_sale','title'=>__('First Sale','elev8-os'),'earned'=>(int)($sales['count_all']??0)>=1,'current'=>(int)($sales['count_all']??0),'target'=>1],
            ['key'=>'ten_sales','title'=>__('10 Sales','elev8-os'),'earned'=>(int)($sales['count_all']??0)>=10,'current'=>(int)($sales['count_all']??0),'target'=>10],
            ['key'=>'hundred_students','title'=>__('100 Students','elev8-os'),'earned'=>(int)($classes['students_all']??0)>=100,'current'=>(int)($classes['students_all']??0),'target'=>100],
            ['key'=>'ten_k_revenue','title'=>__('$10,000 Revenue','elev8-os'),'earned'=>(float)($sales['revenue_all']??0)>=10000,'current'=>(float)($sales['revenue_all']??0),'target'=>10000],
        ];
        return $defs;
    }
}

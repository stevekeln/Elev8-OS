<?php
if (!defined('ABSPATH')) { exit; }

/** Reusable milestone evaluator. Achievements are calculated from verified facts. */
final class Elev8_OS_Achievement_Service {
    /** @param array<string,mixed> $snapshot @return array<int,array<string,mixed>> */
    public static function evaluate(array $snapshot): array {
        $assets=(array)($snapshot['assets']??[]); $sales=(array)($snapshot['sales']??[]); $classes=(array)($snapshot['classes']??[]);
        $defs=[
            ['key'=>'first_artwork','title'=>__('First Artwork','elev8-os'),'earned'=>(int)($assets['total']??0)>=1],
            ['key'=>'first_sale','title'=>__('First Sale','elev8-os'),'earned'=>(int)($sales['count_all']??0)>=1],
            ['key'=>'ten_sales','title'=>__('10 Sales','elev8-os'),'earned'=>(int)($sales['count_all']??0)>=10],
            ['key'=>'hundred_students','title'=>__('100 Students','elev8-os'),'earned'=>(int)($classes['students_all']??0)>=100],
            ['key'=>'ten_k_revenue','title'=>__('$10,000 Revenue','elev8-os'),'earned'=>(float)($sales['revenue_all']??0)>=10000],
        ];
        return $defs;
    }
}

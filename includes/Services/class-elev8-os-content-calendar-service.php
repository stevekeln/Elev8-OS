<?php
if (!defined('ABSPATH')) { exit; }

/** Produces a practical seven-day content plan from current verified priorities. */
final class Elev8_OS_Content_Calendar_Service {
    /** @param array<string,mixed> $snapshot @return array<int,array<string,mixed>> */
    public static function week(array $snapshot): array {
        $recommendations = (array) ($snapshot['recommendations'] ?? []);
        $defaults = [
            ['type'=>'social','title'=>__('Share available artwork', 'elev8-os'),'goal'=>'sell_artwork'],
            ['type'=>'email','title'=>__('Invite students to an upcoming class', 'elev8-os'),'goal'=>'fill_class'],
            ['type'=>'story','title'=>__('Share a behind-the-scenes moment', 'elev8-os'),'goal'=>'custom'],
            ['type'=>'profile','title'=>__('Refresh your artist story', 'elev8-os'),'goal'=>'introduce_artist'],
            ['type'=>'social','title'=>__('Promote an Elev8 event', 'elev8-os'),'goal'=>'announce_event'],
            ['type'=>'follow_up','title'=>__('Reconnect with past students', 'elev8-os'),'goal'=>'bring_customers_back'],
            ['type'=>'review','title'=>__('Review results and plan next week', 'elev8-os'),'goal'=>'custom'],
        ];
        if (!empty($recommendations[0]['action'])) {
            $action = (string) $recommendations[0]['action'];
            $defaults[0] = ['type'=>'priority','title'=>(string)($recommendations[0]['title'] ?? __('Complete the best next action','elev8-os')),'goal'=>self::goal($action)];
        }
        $out=[]; $start=current_time('timestamp');
        foreach ($defaults as $i=>$item) {
            $out[]=$item+['date'=>wp_date('Y-m-d',$start+DAY_IN_SECONDS*$i),'day'=>wp_date('D',$start+DAY_IN_SECONDS*$i),'url'=>class_exists('Elev8_OS_Marketing_Launcher')?Elev8_OS_Marketing_Launcher::url((string)$item['goal']):'#'];
        }
        return $out;
    }
    private static function goal(string $action): string { return $action==='classes'?'fill_class':($action==='artwork'?'sell_artwork':($action==='website'?'introduce_artist':'custom')); }
}

<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Converts business actions into safe, preconfigured Elev8 OS destinations.
 * This is the shared bridge between recommendations and Content Studio.
 */
final class Elev8_OS_Marketing_Launcher {
    public static function url(string $action, array $context = []): string {
        $action = sanitize_key($action);
        $base = class_exists('Elev8_OS_Portal_Page_Manager')
            ? Elev8_OS_Portal_Page_Manager::get_url('content_studio')
            : home_url('/artist-content-studio/');

        $goals = [
            'classes' => 'fill_class', 'fill_class' => 'fill_class', 'promote_class' => 'fill_class',
            'artwork' => 'sell_artwork', 'sell_artwork' => 'sell_artwork',
            'event' => 'announce_event', 'announce_event' => 'announce_event',
            'students' => 'bring_back', 'bring_back' => 'bring_back',
            'website' => 'introduce_artist', 'profile' => 'introduce_artist',
            'referral' => 'referral',
        ];

        if (isset($goals[$action])) {
            $args = ['campaign_goal' => $goals[$action], 'elev8_source' => 'growth_center'];
            foreach (['class_id','asset_id','event_id'] as $key) {
                if (!empty($context[$key])) { $args[$key] = absint($context[$key]); }
            }
            return add_query_arg($args, $base) . '#elev8-campaign-builder';
        }

        $pages = [
            'add_artwork' => ['artwork', '#elev8-artwork-editor'],
            'manage_classes' => ['classes', ''],
            'view_students' => ['students', ''],
            'edit_profile' => ['edit_website', ''],
            'print' => ['print_center', ''],
        ];
        if (isset($pages[$action]) && class_exists('Elev8_OS_Portal_Page_Manager')) {
            return Elev8_OS_Portal_Page_Manager::get_url($pages[$action][0]) . $pages[$action][1];
        }
        return $base;
    }
}

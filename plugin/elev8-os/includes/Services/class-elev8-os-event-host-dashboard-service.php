<?php
/**
 * Verified operational home data for event hosts.
 *
 * Event-specific schedules and assignments remain configurable. This service
 * only exposes records already stored by trusted Elev8 OS engines.
 *
 * @package Elev8OS
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Elev8_OS_Event_Host_Dashboard_Service {

    public const META_PUBLIC_PROFILE_STATUS = '_elev8_public_host_profile_status';

    /** @return array<string,mixed> */
    public static function snapshot(?WP_User $user = null): array {
        $user = $user ?: wp_get_current_user();
        $user_id = (int) $user->ID;
        $can_manage_reservations = Elev8_OS_Access_Service::user_can('manage_reservations', $user)
            || Elev8_OS_Access_Service::user_can('manage_bingo', $user);
        $reservation_owner = $can_manage_reservations ? 0 : $user_id;

        $open_mic = self::open_mic_snapshot();
        $bingo = self::bingo_snapshot($reservation_owner);
        $attention = (int) ($open_mic['new'] ?? 0) + (int) ($bingo['attention'] ?? 0);

        return [
            'available' => true,
            'user_id' => $user_id,
            'display_name' => self::display_name($user),
            'public_profile' => [
                'published' => get_user_meta($user_id, self::META_PUBLIC_PROFILE_STATUS, true) === 'published',
                'diagnostic' => __('A public event-host profile has not been published for this account.', 'elev8-os'),
            ],
            'attention' => $attention,
            'open_mic' => $open_mic,
            'bingo' => $bingo,
            'event_log_url' => add_query_arg(['type' => 'event', 'team' => '1'], Elev8_OS_Checkin_Center_Module::page_url()),
            'open_mic_form_url' => add_query_arg('type', 'open_mic', Elev8_OS_Checkin_Center_Module::page_url()),
            'reservations_url' => class_exists('Elev8_OS_Bingo_Reservations_Module')
                ? Elev8_OS_Bingo_Reservations_Module::admin_url()
                : '',
            'generated_at' => current_time('mysql'),
        ];
    }

    /** @return array<string,mixed> */
    private static function open_mic_snapshot(): array {
        if (!class_exists('Elev8_OS_Daily_Operations_Service')) {
            return ['available' => false, 'total' => null, 'new' => null, 'recent' => []];
        }

        $query = new WP_Query([
            'post_type' => Elev8_OS_Daily_Operations_Service::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => 5,
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => [[
                'key' => Elev8_OS_Daily_Operations_Service::META_TEMPLATE,
                'value' => 'open_mic',
            ]],
            'no_found_rows' => false,
        ]);

        $new_query = new WP_Query([
            'post_type' => Elev8_OS_Daily_Operations_Service::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_query' => [
                ['key' => Elev8_OS_Daily_Operations_Service::META_TEMPLATE, 'value' => 'open_mic'],
                ['key' => Elev8_OS_Daily_Operations_Service::META_STATUS, 'value' => 'new'],
            ],
            'no_found_rows' => false,
        ]);

        $recent = [];
        foreach ($query->posts as $post) {
            if (!$post instanceof WP_Post) {
                continue;
            }
            $fields = get_post_meta($post->ID, Elev8_OS_Daily_Operations_Service::META_FIELDS, true);
            $fields = is_array($fields) ? $fields : [];
            $recent[] = [
                'id' => (int) $post->ID,
                'name' => (string) get_post_meta($post->ID, Elev8_OS_Daily_Operations_Service::META_GUEST_NAME, true),
                'attendee_type' => (string) ($fields['attendee_type'] ?? ''),
                'performed' => (string) ($fields['performed'] ?? ''),
                'interest' => (string) ($fields['perform_next_time'] ?? ''),
                'date' => get_the_date('', $post),
            ];
        }

        return [
            'available' => true,
            'total' => (int) $query->found_posts,
            'new' => (int) $new_query->found_posts,
            'recent' => $recent,
        ];
    }

    /** @return array<string,mixed> */
    private static function bingo_snapshot(int $assigned_user_id): array {
        if (!class_exists('Elev8_OS_Bingo_Reservations_Module')) {
            return ['available' => false, 'attention' => null, 'upcoming' => null];
        }

        return [
            'available' => true,
            'attention' => Elev8_OS_Bingo_Reservations_Module::attention_count($assigned_user_id),
            'upcoming' => Elev8_OS_Bingo_Reservations_Module::upcoming_count(30, $assigned_user_id),
        ];
    }

    private static function display_name(WP_User $user): string {
        $nickname = trim((string) get_user_meta($user->ID, 'nickname', true));
        if ($nickname !== '') {
            return $nickname;
        }
        return $user->display_name !== '' ? $user->display_name : __('Event Host', 'elev8-os');
    }
}

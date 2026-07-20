<?php
/**
 * Shared operational dashboard data for Elev8 OS.
 *
 * This service is intentionally limited to verified values exposed by current
 * Elev8 OS modules. Dashboards should render "Unavailable" rather than infer
 * data that is not yet supplied by a trusted service.
 *
 * @package Elev8OS
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Elev8_OS_Dashboard_Service {

    /**
     * Build the role-aware operational summary for a user.
     *
     * @return array<string,mixed>
     */
    public static function summary(?WP_User $user = null): array {
        $user = $user ?: wp_get_current_user();
        $user_id = (int) $user->ID;
        $can_manage_work = Elev8_OS_Access_Service::user_can('manage_work', $user);
        $can_manage_reservations = Elev8_OS_Access_Service::user_can('manage_reservations', $user)
            || Elev8_OS_Access_Service::user_can('manage_bingo', $user);

        $my_work = class_exists('Elev8_OS_Work_Service')
            ? Elev8_OS_Work_Service::counts($user_id)
            : self::unavailable_work_counts();
        $team_work = ($can_manage_work && class_exists('Elev8_OS_Work_Service'))
            ? Elev8_OS_Work_Service::counts()
            : self::unavailable_work_counts();

        $reservation_owner = $can_manage_reservations ? 0 : $user_id;
        $reservations = [
            'available' => class_exists('Elev8_OS_Bingo_Reservations_Module'),
            'attention' => class_exists('Elev8_OS_Bingo_Reservations_Module')
                ? Elev8_OS_Bingo_Reservations_Module::attention_count($reservation_owner)
                : null,
            'upcoming' => class_exists('Elev8_OS_Bingo_Reservations_Module')
                ? Elev8_OS_Bingo_Reservations_Module::upcoming_count(7, $reservation_owner)
                : null,
        ];

        $applications = [
            'available' => class_exists('Elev8_OS_Event_Applications_Module')
                && Elev8_OS_Access_Service::user_can('manage_events', $user),
            'attention' => null,
            'awaiting_agreement' => null,
        ];
        if ($applications['available']) {
            $applications['attention'] = Elev8_OS_Event_Applications_Module::attention_count();
            $applications['awaiting_agreement'] = Elev8_OS_Event_Applications_Module::awaiting_agreement_count();
        }

        $needs_attention = 0;
        if (!empty($my_work['available'])) {
            $needs_attention += (int) $my_work['overdue'] + (int) $my_work['due_today'];
        }
        if ($can_manage_work && !empty($team_work['available'])) {
            $needs_attention += (int) $team_work['unassigned'];
        }
        if ($reservations['available']) {
            $needs_attention += (int) $reservations['attention'];
        }
        if ($applications['available']) {
            $needs_attention += (int) $applications['attention'];
        }

        return [
            'generated_at' => current_time('mysql'),
            'user_id' => $user_id,
            'needs_attention' => $needs_attention,
            'my_work' => $my_work,
            'team_work' => $team_work,
            'reservations' => $reservations,
            'applications' => $applications,
            'can_manage_work' => $can_manage_work,
            'sales' => [
                'available' => false,
                'hemp' => null,
                'gallery' => null,
                'diagnostic' => __('Verified daily sales aggregation is not connected yet.', 'elev8-os'),
            ],
            'upcoming_events' => [
                'available' => false,
                'diagnostic' => __('A shared event calendar service is not connected yet.', 'elev8-os'),
            ],
            'notifications' => [
                'available' => false,
                'diagnostic' => __('Role-aware notification counts are not connected yet.', 'elev8-os'),
            ],
        ];
    }

    /**
     * Return priority messages generated only from verified data.
     *
     * @param array<string,mixed> $summary
     * @return array<int,array<string,string|int>>
     */
    public static function priorities(array $summary): array {
        $items = [];
        $my = $summary['my_work'] ?? [];
        $team = $summary['team_work'] ?? [];
        $reservations = $summary['reservations'] ?? [];
        $applications = $summary['applications'] ?? [];

        if (!empty($my['available']) && (int) ($my['overdue'] ?? 0) > 0) {
            $items[] = self::priority('critical', (int) $my['overdue'], __('My overdue work', 'elev8-os'), class_exists('Elev8_OS_Work_Module') ? Elev8_OS_Work_Module::my_url() : '');
        }
        if (!empty($my['available']) && (int) ($my['due_today'] ?? 0) > 0) {
            $items[] = self::priority('high', (int) $my['due_today'], __('My work due today', 'elev8-os'), class_exists('Elev8_OS_Work_Module') ? Elev8_OS_Work_Module::my_url() : '');
        }
        if (!empty($summary['can_manage_work']) && !empty($team['available']) && (int) ($team['unassigned'] ?? 0) > 0) {
            $items[] = self::priority('high', (int) $team['unassigned'], __('Unassigned team work', 'elev8-os'), class_exists('Elev8_OS_Work_Module') ? Elev8_OS_Work_Module::team_url() : '');
        }
        if (!empty($reservations['available']) && (int) ($reservations['attention'] ?? 0) > 0) {
            $items[] = self::priority('normal', (int) $reservations['attention'], __('Reservations needing attention', 'elev8-os'), Elev8_OS_Bingo_Reservations_Module::admin_url());
        }
        if (!empty($applications['available']) && (int) ($applications['attention'] ?? 0) > 0) {
            $items[] = self::priority('normal', (int) $applications['attention'], __('Event applications needing attention', 'elev8-os'), Elev8_OS_Event_Applications_Module::admin_url());
        }

        return $items;
    }

    /** @return array<string,int|bool> */
    private static function unavailable_work_counts(): array {
        return [
            'available' => false,
            'active' => 0,
            'overdue' => 0,
            'due_today' => 0,
            'unassigned' => 0,
            'waiting' => 0,
        ];
    }

    /** @return array<string,string|int> */
    private static function priority(string $severity, int $count, string $label, string $url): array {
        return compact('severity', 'count', 'label', 'url');
    }
}

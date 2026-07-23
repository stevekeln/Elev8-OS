<?php
if (!defined('ABSPATH')) { exit; }

/** Amelia-backed class decision adapter for the Operations Contributor framework. */
final class Elev8_OS_Class_Approval_Contributor {
    public const KEY = 'class_approval';

    public static function init(): void {
        add_filter('elev8_os_operations_contributors', [__CLASS__, 'register']);
        add_action('elev8_os_class_booking_changed', [__CLASS__, 'sync'], 10, 2);
    }

    public static function register(array $contributors): array {
        $contributors[self::KEY] = [
            'label' => __('Class Approval', 'elev8-os'),
            'source_type' => 'booking',
            'work_type' => 'approval',
            'resolve' => [__CLASS__, 'resolve'],
            'steps' => [__CLASS__, 'steps'],
        ];
        return $contributors;
    }

    public static function sync(int $booking_id, array $booking = []): void {
        if ($booking_id < 1) { return; }
        do_action('elev8_os_operations_source_changed', self::KEY, $booking_id, ['source' => $booking]);
    }

    /** @return array<string,mixed> */
    public static function resolve(int $booking_id, array $context = []): array {
        $booking = is_array($context['source'] ?? null) && absint($context['source']['booking_id'] ?? 0) === $booking_id
            ? $context['source']
            : Elev8_OS_Class_Approval_Service::booking_record($booking_id);
        if (!$booking) { return []; }

        $status = sanitize_key((string)($booking['booking_status'] ?? ''));
        $start = (string)($booking['booking_start'] ?? '');
        $start_ts = $start !== '' ? strtotime($start) : false;
        $urgent_hours = max(1, absint(Elev8_OS_Class_Approval_Service::settings()['urgent_hours'] ?? 24));
        $hours_until = $start_ts ? (($start_ts - current_time('timestamp')) / HOUR_IN_SECONDS) : null;

        return [
            'id' => $booking_id,
            'status' => $status,
            'priority' => $hours_until !== null && $hours_until <= $urgent_hours ? 'urgent' : 'high',
            'due_date' => $start_ts ? wp_date('Y-m-d', $start_ts) : '',
            'owner_user_id' => self::wordpress_owner(absint($booking['provider_id'] ?? 0)),
            'organization_unit_id' => absint(apply_filters('elev8_os_amelia_booking_organization_unit_id', 0, $booking_id, $booking)),
            'booking' => $booking,
        ];
    }

    /** @return array<string,array<string,mixed>> */
    public static function steps(array $source): array {
        $booking = (array)($source['booking'] ?? []);
        $status = sanitize_key((string)($source['status'] ?? ''));
        $active = in_array($status, ['pending', 'waiting', 'waiting_for_approval'], true);
        $service = sanitize_text_field((string)($booking['service'] ?? __('Class', 'elev8-os')));
        $customer = sanitize_text_field((string)($booking['customer']['name'] ?? __('Customer', 'elev8-os')));
        $start = (string)($booking['booking_start'] ?? '');
        $when = $start !== '' && strtotime($start)
            ? wp_date('M j, Y · ' . get_option('time_format'), strtotime($start))
            : __('Schedule unavailable', 'elev8-os');

        return [
            'decision' => [
                'active' => $active,
                'title' => sprintf(__('Decide class booking — %1$s — %2$s', 'elev8-os'), $service, $customer),
                'description' => sprintf(
                    __('Amelia booking #%1$d requires an operational decision for %2$s. Amelia remains authoritative for booking status and schedule.', 'elev8-os'),
                    absint($source['id'] ?? 0),
                    $when
                ),
                'type' => 'approval',
                'owner_user_id' => absint($source['owner_user_id'] ?? 0),
                'due_date' => (string)($source['due_date'] ?? ''),
                'priority' => (string)($source['priority'] ?? 'high'),
                'checklist' => [
                    __('Review requested class, date, and attendance', 'elev8-os'),
                    __('Confirm teacher and operational capacity', 'elev8-os'),
                    __('Review customer details and special information', 'elev8-os'),
                    __('Approve, move, or cancel in the Class Approval Center', 'elev8-os'),
                ],
                'required_approvals' => [__('Class booking decision recorded in Amelia', 'elev8-os')],
                'completion_rules' => [
                    __('Amelia booking is no longer pending approval', 'elev8-os'),
                    __('Any schedule change or cancellation reason is recorded in the authoritative booking workflow', 'elev8-os'),
                ],
                'escalation' => ['after_days' => 0, 'priority' => 'urgent', 'notify_capability' => 'view_glass_dashboard'],
            ],
        ];
    }

    private static function wordpress_owner(int $provider_id): int {
        if ($provider_id < 1) { return 0; }
        $users = get_users([
            'number' => 1,
            'fields' => 'ids',
            'meta_key' => 'elev8_os_amelia_employee_id',
            'meta_value' => $provider_id,
        ]);
        return $users ? absint($users[0]) : 0;
    }
}

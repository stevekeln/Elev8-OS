<?php
if (!defined('ABSPATH')) { exit; }

/** Event application adapter for the shared Operations Contributor framework. */
final class Elev8_OS_Event_Operations_Contributor {
    public const KEY = 'event_operations';

    public static function init(): void {
        add_filter('elev8_os_operations_contributors', [__CLASS__, 'register']);
        add_action('elev8_os_event_application_changed', [__CLASS__, 'sync'], 10, 2);
    }

    public static function register(array $contributors): array {
        $contributors[self::KEY] = [
            'label' => __('Event Operations', 'elev8-os'),
            'source_type' => 'event_application',
            'work_type' => 'event',
            'resolve' => [__CLASS__, 'resolve'],
            'steps' => [__CLASS__, 'steps'],
        ];
        return $contributors;
    }

    /** @return array<int,int> */
    public static function sync(int $application_id, array $application = []): array {
        if ($application_id < 1) { return []; }
        $result = Elev8_OS_Operations_Contributor_Service::sync_source(self::KEY, $application_id, ['source' => $application]);
        return is_wp_error($result) ? [] : array_map('absint', $result);
    }

    /** @return array<string,mixed> */
    public static function resolve(int $application_id, array $context = []): array {
        if (get_post_type($application_id) !== 'elev8_event_app') { return []; }
        $m = static fn(string $key) => get_post_meta($application_id, '_elev8_event_app_' . $key, true);
        $status = sanitize_key((string) $m('status')) ?: 'new';
        $preferred_date = sanitize_text_field((string) $m('preferred_date_1'));
        $follow_up = sanitize_text_field((string) $m('follow_up'));
        return [
            'id' => $application_id,
            'status' => $status,
            'owner_user_id' => absint($m('assigned_user')),
            'due_date' => $follow_up ?: $preferred_date,
            'priority' => in_array($status, ['new','review','contacted','agreement_needed'], true) ? 'high' : 'normal',
            'organization_unit_id' => absint(apply_filters('elev8_os_event_application_organization_unit_id', 0, $application_id)),
            'person_id' => absint($m('person_id')),
            'customer_person_id' => absint($m('person_id')),
            'relationship_id' => absint($m('relationship_id')),
            'organization_name' => sanitize_text_field((string) $m('organization_name')),
            'contact_name' => sanitize_text_field((string) $m('contact_name')),
            'preferred_date' => $preferred_date,
            'follow_up' => $follow_up,
        ];
    }

    /** @return array<string,array<string,mixed>> */
    public static function steps(array $source): array {
        $status = sanitize_key((string) ($source['status'] ?? 'new'));
        $label = (string) ($source['organization_name'] ?? '') ?: sprintf(__('Application #%d', 'elev8-os'), absint($source['id'] ?? 0));
        $owner = absint($source['owner_user_id'] ?? 0);
        $due = (string) ($source['due_date'] ?? '');
        $review_active = in_array($status, ['new','review','contacted'], true);
        $planning_active = in_array($status, ['approved','agreement_needed','planning','scheduled'], true);

        return [
            'review' => [
                'active' => $review_active,
                'title' => sprintf(__('Review event application — %s', 'elev8-os'), $label),
                'description' => sprintf(__('Review event application #%d and record the authoritative decision in Event Applications.', 'elev8-os'), absint($source['id'] ?? 0)),
                'type' => 'approval', 'owner_user_id' => $owner, 'due_date' => $due, 'priority' => 'high',
                'checklist' => [
                    __('Review event concept, host, attendance, and preferred dates', 'elev8-os'),
                    __('Contact the applicant and clarify operational requirements', 'elev8-os'),
                    __('Confirm the responsible internal owner', 'elev8-os'),
                    __('Record approval or decline in Event Applications', 'elev8-os'),
                ],
                'required_approvals' => [__('Event application decision recorded', 'elev8-os')],
                'completion_rules' => [__('Application is approved, declined, or archived in the authoritative event workflow', 'elev8-os')],
                'escalation' => ['after_days'=>2, 'priority'=>'urgent', 'notify_capability'=>'manage_event_applications'],
            ],
            'planning' => [
                'active' => $planning_active,
                'title' => sprintf(__('Plan and deliver event — %s', 'elev8-os'), $label),
                'description' => sprintf(__('Coordinate the approved event application #%d without copying its authoritative application data.', 'elev8-os'), absint($source['id'] ?? 0)),
                'type' => 'event', 'owner_user_id' => $owner, 'due_date' => $due, 'priority' => $status === 'agreement_needed' ? 'high' : 'normal',
                'checklist' => [
                    __('Confirm agreement and event date', 'elev8-os'),
                    __('Assign event lead and required staff', 'elev8-os'),
                    __('Confirm setup, inventory, equipment, and safety needs', 'elev8-os'),
                    __('Coordinate marketing and applicant communications', 'elev8-os'),
                    __('Complete event-day readiness confirmation', 'elev8-os'),
                    __('Record post-event follow-up and outcome', 'elev8-os'),
                ],
                'required_approvals' => [__('Event readiness approved by responsible lead', 'elev8-os')],
                'completion_rules' => [__('Event application is marked completed, declined, or archived', 'elev8-os'), __('Post-event follow-up is recorded', 'elev8-os')],
                'escalation' => ['after_days'=>3, 'priority'=>'high', 'notify_capability'=>'manage_events'],
            ],
        ];
    }
}

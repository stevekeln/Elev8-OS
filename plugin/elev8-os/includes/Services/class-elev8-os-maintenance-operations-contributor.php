<?php
if (!defined('ABSPATH')) { exit; }

/** Maintenance adapter for the shared Operations Contributor framework. */
final class Elev8_OS_Maintenance_Operations_Contributor {
    public const KEY = 'maintenance_operations';

    public static function init(): void {
        add_filter('elev8_os_operations_contributors', [__CLASS__, 'register']);
        add_action('elev8_os_maintenance_record_changed', [__CLASS__, 'sync'], 10, 2);
    }

    public static function register(array $contributors): array {
        $contributors[self::KEY] = [
            'label' => __('Maintenance Operations', 'elev8-os'),
            'source_type' => 'maintenance_record',
            'work_type' => 'maintenance',
            'resolve' => [__CLASS__, 'resolve'],
            'steps' => [__CLASS__, 'steps'],
        ];
        return $contributors;
    }

    /** @param array<string,mixed> $record @return array<int,int> */
    public static function sync(int $record_id, array $record = []): array {
        if ($record_id < 1) { return []; }
        $result = Elev8_OS_Operations_Contributor_Service::sync_source(self::KEY, $record_id, ['source' => $record]);
        return is_wp_error($result) ? [] : array_map('absint', $result);
    }

    /** @return array<string,mixed> */
    public static function resolve(int $record_id, array $context = []): array {
        $record = is_array($context['source'] ?? null) && !empty($context['source']['id'])
            ? $context['source']
            : Elev8_OS_Maintenance_Service::get($record_id);
        if (!$record) { return []; }
        return [
            'id' => $record_id,
            'status' => sanitize_key((string)($record['status'] ?? 'open')),
            'owner_user_id' => absint($record['owner_user_id'] ?? 0),
            'requested_by_user_id' => absint($record['requested_by_user_id'] ?? 0),
            'due_date' => sanitize_text_field((string)($record['due_date'] ?? '')),
            'priority' => sanitize_key((string)($record['priority'] ?? 'normal')),
            'organization_unit_id' => absint($record['organization_unit_id'] ?? 0),
            'maintenance_type' => sanitize_key((string)($record['maintenance_type'] ?? 'maintenance_request')),
            'asset_id' => absint($record['asset_id'] ?? 0),
            'asset_type' => sanitize_key((string)($record['asset_type'] ?? '')),
            'asset_label' => sanitize_text_field((string)($record['asset_label'] ?? '')),
            'location_label' => sanitize_text_field((string)($record['location_label'] ?? '')),
            'description' => sanitize_textarea_field((string)($record['description'] ?? '')),
            'recurrence_days' => absint($record['recurrence_days'] ?? 0),
        ];
    }

    /** @return array<string,array<string,mixed>> */
    public static function steps(array $source): array {
        $type = sanitize_key((string)($source['maintenance_type'] ?? 'maintenance_request'));
        $status = sanitize_key((string)($source['status'] ?? 'open'));
        $active = !in_array($status, ['resolved', 'cancelled', 'archived'], true);
        $label = self::subject_label($source);
        $base = [
            'active' => $active,
            'owner_user_id' => absint($source['owner_user_id'] ?? 0),
            'due_date' => (string)($source['due_date'] ?? ''),
            'priority' => (string)($source['priority'] ?? 'normal'),
            'status' => self::work_status($status),
            'type' => 'maintenance',
        ];
        $definitions = [
            'maintenance_request' => [
                'title' => sprintf(__('Resolve maintenance request — %s', 'elev8-os'), $label),
                'description' => self::description($source, __('Diagnose and resolve the reported equipment or facility issue.', 'elev8-os')),
                'checklist' => [
                    __('Confirm the reported condition and exact location', 'elev8-os'),
                    __('Assess safety risk and stop use when necessary', 'elev8-os'),
                    __('Determine repair, vendor, parts, and access requirements', 'elev8-os'),
                    __('Complete or coordinate the repair', 'elev8-os'),
                    __('Test the result and document final condition', 'elev8-os'),
                ],
                'required_approvals' => [__('Repair completion accepted by the responsible lead', 'elev8-os')],
                'completion_rules' => [__('The reported issue is resolved or intentionally closed with a documented disposition', 'elev8-os')],
                'escalation' => ['after_days' => 1, 'priority' => 'urgent', 'notify_capability' => 'manage_operations'],
            ],
            'asset_repair' => [
                'title' => sprintf(__('Repair asset — %s', 'elev8-os'), $label),
                'description' => self::description($source, __('Restore the linked asset to safe, verified service and preserve its maintenance history.', 'elev8-os')),
                'checklist' => [
                    __('Identify the asset and record its condition before service', 'elev8-os'),
                    __('Remove the asset from service when continued use is unsafe', 'elev8-os'),
                    __('Diagnose failure and identify parts or outside service needed', 'elev8-os'),
                    __('Complete the repair and record work performed', 'elev8-os'),
                    __('Test safe operation and return-to-service status', 'elev8-os'),
                ],
                'required_approvals' => [__('Asset return to service approved', 'elev8-os')],
                'completion_rules' => [__('Repair evidence and final service condition are recorded', 'elev8-os')],
                'escalation' => ['after_days' => 1, 'priority' => 'urgent', 'notify_capability' => 'manage_operations'],
            ],
            'preventive_maintenance' => [
                'title' => sprintf(__('Perform preventive maintenance — %s', 'elev8-os'), $label),
                'description' => self::description($source, __('Complete scheduled service before failure and preserve the next service interval.', 'elev8-os')),
                'checklist' => [
                    __('Review the applicable service procedure and prior history', 'elev8-os'),
                    __('Secure equipment and prepare the service area', 'elev8-os'),
                    __('Complete required cleaning, adjustment, replacement, and lubrication steps', 'elev8-os'),
                    __('Inspect for developing wear, leaks, damage, or unsafe conditions', 'elev8-os'),
                    __('Test operation and record service results', 'elev8-os'),
                ],
                'required_approvals' => [__('Preventive service completion confirmed', 'elev8-os')],
                'completion_rules' => [__('Service evidence is complete and the next recurring due date is established when applicable', 'elev8-os')],
                'escalation' => ['after_days' => 2, 'priority' => 'high', 'notify_capability' => 'manage_operations'],
            ],
            'inspection' => [
                'title' => sprintf(__('Complete inspection — %s', 'elev8-os'), $label),
                'description' => self::description($source, __('Inspect the subject against its applicable operational standard and create follow-up work for any exception.', 'elev8-os')),
                'checklist' => [
                    __('Review the inspection scope and prior findings', 'elev8-os'),
                    __('Inspect each required area or component', 'elev8-os'),
                    __('Record passed, failed, and not-applicable findings', 'elev8-os'),
                    __('Create maintenance follow-up for every unresolved exception', 'elev8-os'),
                    __('Confirm inspection completion and next due date', 'elev8-os'),
                ],
                'required_approvals' => [__('Inspection results reviewed by the responsible lead', 'elev8-os')],
                'completion_rules' => [__('All required inspection findings are recorded and failures have accountable follow-up', 'elev8-os')],
                'escalation' => ['after_days' => 2, 'priority' => 'high', 'notify_capability' => 'manage_operations'],
            ],
            'safety_check' => [
                'title' => sprintf(__('Complete safety check — %s', 'elev8-os'), $label),
                'description' => self::description($source, __('Verify that the equipment, facility, or work area is safe for continued operation.', 'elev8-os')),
                'checklist' => [
                    __('Review known hazards and prior safety findings', 'elev8-os'),
                    __('Inspect guards, controls, utilities, access, and emergency provisions', 'elev8-os'),
                    __('Immediately isolate any unsafe condition', 'elev8-os'),
                    __('Create accountable corrective work for every failed item', 'elev8-os'),
                    __('Record the final safe-use determination', 'elev8-os'),
                ],
                'required_approvals' => [__('Safe-use determination approved', 'elev8-os')],
                'completion_rules' => [__('The safety result is documented and no failed condition remains without assigned corrective work', 'elev8-os')],
                'escalation' => ['after_days' => 0, 'priority' => 'urgent', 'notify_capability' => 'manage_operations'],
            ],
        ];
        $definition = $definitions[$type] ?? $definitions['maintenance_request'];
        return ['execution' => array_merge($base, $definition)];
    }

    private static function subject_label(array $source): string {
        $asset = trim((string)($source['asset_label'] ?? ''));
        $location = trim((string)($source['location_label'] ?? ''));
        if ($asset !== '' && $location !== '') { return $asset . ' — ' . $location; }
        if ($asset !== '') { return $asset; }
        if ($location !== '') { return $location; }
        return sprintf(__('Maintenance record #%d', 'elev8-os'), absint($source['id'] ?? 0));
    }

    private static function description(array $source, string $fallback): string {
        $description = trim((string)($source['description'] ?? ''));
        return $description !== '' ? $description : $fallback;
    }

    private static function work_status(string $status): string {
        $map = ['assigned' => 'assigned', 'in_progress' => 'in_progress', 'waiting' => 'waiting'];
        return $map[$status] ?? 'requested';
    }
}

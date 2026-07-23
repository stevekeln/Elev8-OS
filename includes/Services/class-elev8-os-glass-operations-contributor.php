<?php
if (!defined('ABSPATH')) { exit; }

/** First Operations Contributor Adapter: Glass Production, Repair and Memorial. */
final class Elev8_OS_Glass_Operations_Contributor {
    public const KEY = 'glass_operations';

    public static function init(): void {
        add_filter('elev8_os_operations_contributors', [__CLASS__, 'register']);
        add_action('elev8_os_glass_job_saved', [__CLASS__, 'sync'], 10, 2);
        add_action('elev8_os_glass_job_updated', [__CLASS__, 'sync'], 10, 2);
    }

    public static function register(array $contributors): array {
        $contributors[self::KEY] = [
            'label' => __('Glass Operations', 'elev8-os'),
            'source_type' => 'glass_job',
            'work_type' => 'production',
            'resolve' => [__CLASS__, 'resolve'],
            'steps' => [__CLASS__, 'steps'],
        ];
        return $contributors;
    }

    public static function sync(int $job_id, array $job = []): void {
        do_action('elev8_os_operations_source_changed', self::KEY, $job_id, ['source' => $job]);
    }

    /** @return array<string,mixed> */
    public static function resolve(int $job_id, array $context = []): array {
        $job = is_array($context['source'] ?? null) && absint($context['source']['id'] ?? 0) === $job_id
            ? $context['source']
            : Elev8_OS_Glass_Operations_Service::job($job_id);
        if (!$job) { return []; }
        return [
            'id' => $job_id,
            'status' => sanitize_key((string)($job['status'] ?? 'new')),
            'priority' => sanitize_key((string)($job['priority'] ?? 'normal')),
            'due_date' => (string)($job['due_date'] ?? ''),
            'owner_user_id' => absint($job['assigned_user_id'] ?? 0),
            'requested_by_user_id' => absint($job['created_by'] ?? 0),
            'organization_unit_id' => 0,
            'job' => $job,
        ];
    }

    /** @return array<string,array<string,mixed>> */
    public static function steps(array $source): array {
        $job = $source['job'];
        $status = sanitize_key((string)$source['status']);
        $type = self::work_type((string)($job['job_type'] ?? 'production'));
        $label = self::label($job);
        $active = !in_array($status, ['completed','cancelled'], true);
        $checklist = self::checklist($type);
        $approvals = $type === 'memorial' ? [__('Custody reconciliation confirmed', 'elev8-os'), __('Final release approved', 'elev8-os')] : [__('Quality control approved', 'elev8-os')];
        $rules = $type === 'memorial'
            ? [__('All production is complete', 'elev8-os'), __('Ashes reconciliation is confirmed', 'elev8-os'), __('Release recipient and method are documented', 'elev8-os')]
            : [__('Required production is complete', 'elev8-os'), __('Quality control is approved', 'elev8-os')];

        return [
            'execute' => [
                'active' => $active,
                'title' => sprintf(__('Complete %1$s — %2$s', 'elev8-os'), ucfirst($type), $label),
                'description' => sprintf(__('Operational work contributed by authoritative glass job #%d. Update the glass job to change source state.', 'elev8-os'), (int)$source['id']),
                'type' => $type,
                'owner_user_id' => $source['owner_user_id'],
                'due_date' => $source['due_date'],
                'priority' => $source['priority'],
                'checklist' => $checklist,
                'required_approvals' => $approvals,
                'completion_rules' => $rules,
                'escalation' => ['after_days'=>1, 'priority'=>'high', 'notify_capability'=>'manage_operations'],
            ],
        ];
    }

    private static function work_type(string $job_type): string {
        $type = sanitize_key($job_type);
        if (in_array($type, ['cremation','memorial'], true)) { return 'memorial'; }
        if ($type === 'repair') { return 'repair'; }
        return 'production';
    }

    /** @return array<int,string> */
    private static function checklist(string $type): array {
        if ($type === 'memorial') {
            return [__('Verify custody intake', 'elev8-os'), __('Confirm materials and design', 'elev8-os'), __('Complete production', 'elev8-os'), __('Perform quality control', 'elev8-os'), __('Reconcile and document remaining ashes', 'elev8-os'), __('Prepare approved release', 'elev8-os')];
        }
        if ($type === 'repair') {
            return [__('Evaluate repairability', 'elev8-os'), __('Prepare and approve quote when required', 'elev8-os'), __('Complete repair', 'elev8-os'), __('Perform quality control', 'elev8-os'), __('Prepare pickup or shipment', 'elev8-os')];
        }
        return [__('Confirm production requirements', 'elev8-os'), __('Assign qualified glassworker', 'elev8-os'), __('Complete production', 'elev8-os'), __('Perform quality control', 'elev8-os'), __('Approve completion', 'elev8-os')];
    }

    private static function label(array $job): string {
        $parts = array_filter([
            sanitize_text_field((string)($job['product_name'] ?? '')),
            sanitize_text_field((string)($job['customer_name'] ?? '')),
            !empty($job['order_number']) ? '#' . sanitize_text_field((string)$job['order_number']) : '',
        ]);
        return $parts ? implode(' — ', $parts) : sprintf(__('Glass Job #%d', 'elev8-os'), absint($job['id'] ?? 0));
    }
}

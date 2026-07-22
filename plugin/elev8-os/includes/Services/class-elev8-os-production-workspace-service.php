<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Reusable read model for production-oriented workspaces.
 *
 * Glass Operations remains authoritative. This service projects the queue,
 * daily brief, quality evidence, and fulfillment handoff into one workspace.
 */
final class Elev8_OS_Production_Workspace_Service {
    public static function init(): void {}

    public static function can_view(?WP_User $user = null): bool {
        $user = $user instanceof WP_User ? $user : wp_get_current_user();
        if (!$user->exists()) { return false; }
        return user_can($user, 'manage_options')
            || (class_exists('Elev8_OS_Access_Service') && Elev8_OS_Access_Service::user_can('view_glass_dashboard', $user));
    }

    /** @return array<string,mixed> */
    public static function snapshot(array $filters = []): array {
        $jobs = self::glass_jobs($filters);
        $today = current_time('Y-m-d');
        $metrics = ['ready'=>0,'running'=>0,'waiting'=>0,'blocked'=>0,'late'=>0,'quality'=>0,'fulfillment'=>0,'completed_today'=>0];
        $queue = [];
        foreach ($jobs as $job) {
            $status = (string) ($job['status'] ?? 'new');
            $due = (string) ($job['due_date'] ?? '');
            $is_late = $due !== '' && $due < $today && !in_array($status, ['completed','cancelled'], true);
            if (in_array($status, ['ready_for_production','assigned'], true)) { $metrics['ready']++; }
            if ($status === 'in_production') { $metrics['running']++; }
            if (in_array($status, ['waiting','waiting_customer_info','waiting_ashes'], true)) { $metrics['waiting']++; }
            if (in_array($status, ['waiting_customer_info','waiting_ashes'], true)) { $metrics['blocked']++; }
            if ($status === 'quality_control' || in_array((string)($job['qc_status'] ?? ''), ['changes_required','rejected'], true)) { $metrics['quality']++; }
            if (in_array($status, ['ready_for_pickup','ready_to_ship'], true)) { $metrics['fulfillment']++; }
            if ($is_late) { $metrics['late']++; }
            if ($status === 'completed' && !empty($job['updated_at']) && substr((string)$job['updated_at'], 0, 10) === $today) { $metrics['completed_today']++; }
            $queue[] = self::normalize_job($job, $is_late);
        }

        $snapshot = [
            'metrics' => $metrics,
            'brief' => self::daily_brief($queue, $metrics),
            'queue' => $queue,
            'workers' => class_exists('Elev8_OS_Glass_Operations_Service') ? Elev8_OS_Glass_Operations_Service::glass_workers() : [],
            'statuses' => class_exists('Elev8_OS_Glass_Operations_Service') ? Elev8_OS_Glass_Operations_Service::workflow_statuses() : [],
            'quality_statuses' => class_exists('Elev8_OS_Glass_Operations_Service') ? Elev8_OS_Glass_Operations_Service::quality_statuses() : [],
            'fulfillment_statuses' => class_exists('Elev8_OS_Glass_Operations_Service') ? Elev8_OS_Glass_Operations_Service::fulfillment_statuses() : [],
            'workstations' => class_exists('Elev8_OS_Production_Coordination_Service') ? Elev8_OS_Production_Coordination_Service::workstations() : [],
            'cycles' => class_exists('Elev8_OS_Production_Coordination_Service') ? Elev8_OS_Production_Coordination_Service::cycles() : [],
            'capacity' => class_exists('Elev8_OS_Production_Coordination_Service') ? Elev8_OS_Production_Coordination_Service::capacity_snapshot() : ['active_cycles'=>0,'running_cycles'=>0,'cooling_cycles'=>0,'cycles'=>[]],
            'configuration' => 'glass',
        ];
        return apply_filters('elev8_os_production_workspace_snapshot', $snapshot, $filters);
    }

    /** @return array<int,array<string,mixed>> */
    private static function glass_jobs(array $filters): array {
        if (!class_exists('Elev8_OS_Glass_Operations_Service')) { return []; }
        $args = [];
        foreach (['assigned_user_id','priority','search','source'] as $key) {
            if (!empty($filters[$key])) { $args[$key] = $filters[$key]; }
        }
        if (!empty($filters['overdue'])) { $args['overdue'] = true; }
        $jobs = Elev8_OS_Glass_Operations_Service::board_jobs($args);
        if (!empty($filters['status'])) {
            $status = sanitize_key((string)$filters['status']);
            $jobs = array_values(array_filter($jobs, static fn(array $job): bool => ($job['status'] ?? '') === $status));
        }
        if (!empty($filters['job_type'])) {
            $type = sanitize_key((string)$filters['job_type']);
            $jobs = array_values(array_filter($jobs, static fn(array $job): bool => ($job['job_type'] ?? '') === $type));
        }
        return $jobs;
    }

    /** @return array<string,mixed> */
    private static function normalize_job(array $job, bool $is_late): array {
        $worker = !empty($job['assigned_user_id']) ? get_user_by('id', (int)$job['assigned_user_id']) : false;
        $type = sanitize_key((string)($job['job_type'] ?? 'production'));
        $title = trim((string)($job['product_name'] ?? ''));
        if ($title === '') { $title = ucwords(str_replace('_', ' ', $type)) . ' #' . (int)$job['id']; }
        return [
            'id' => (int)$job['id'], 'title' => $title, 'type' => $type,
            'customer' => (string)($job['customer_name'] ?? ''), 'order_number' => (string)($job['order_number'] ?? ''),
            'status' => (string)($job['status'] ?? 'new'), 'priority' => (string)($job['priority'] ?? 'normal'),
            'due_date' => (string)($job['due_date'] ?? ''), 'assigned_user_id' => (int)($job['assigned_user_id'] ?? 0),
            'assigned_name' => $worker instanceof WP_User ? $worker->display_name : __('Unassigned', 'elev8-os'),
            'planned_units' => (float)($job['planned_units'] ?? $job['quantity'] ?? 0),
            'completed_units' => (float)($job['completed_units'] ?? 0), 'is_late' => $is_late,
            'is_blocked' => in_array((string)($job['status'] ?? ''), ['waiting_customer_info','waiting_ashes'], true),
            'qc_status' => (string)($job['qc_status'] ?? 'not_reviewed'), 'qc_notes' => (string)($job['qc_notes'] ?? ''),
            'fulfillment_status' => (string)($job['fulfillment_status'] ?? 'not_ready'),
            'fulfillment_method' => (string)($job['fulfillment_method'] ?? ''),
            'fulfillment_notes' => (string)($job['fulfillment_notes'] ?? ''), 'source' => (string)($job['source'] ?? 'glass_job'),
            'allocation' => class_exists('Elev8_OS_Production_Coordination_Service') ? Elev8_OS_Production_Coordination_Service::allocation_for_job((int)$job['id']) : null,
        ];
    }

    /** @return array<int,string> */
    private static function daily_brief(array $queue, array $metrics): array {
        $brief = [];
        if ($metrics['late']) { $brief[] = sprintf(_n('%d production job is late.','%d production jobs are late.',$metrics['late'],'elev8-os'), $metrics['late']); }
        if ($metrics['blocked']) { $brief[] = sprintf(_n('%d job is blocked by missing information.','%d jobs are blocked by missing information.',$metrics['blocked'],'elev8-os'), $metrics['blocked']); }
        if ($metrics['quality']) { $brief[] = sprintf(_n('%d job needs quality attention.','%d jobs need quality attention.',$metrics['quality'],'elev8-os'), $metrics['quality']); }
        if ($metrics['fulfillment']) { $brief[] = sprintf(_n('%d finished job needs pickup or shipping handoff.','%d finished jobs need pickup or shipping handoff.',$metrics['fulfillment'],'elev8-os'), $metrics['fulfillment']); }
        $unassigned = count(array_filter($queue, static fn(array $item): bool => empty($item['assigned_user_id']) && !in_array($item['status'], ['completed','cancelled'], true)));
        if ($unassigned) { $brief[] = sprintf(_n('%d open job is unassigned.','%d open jobs are unassigned.',$unassigned,'elev8-os'), $unassigned); }
        if (!$brief) { $brief[] = __('No critical production exceptions require manager attention right now.', 'elev8-os'); }
        return $brief;
    }

    public static function update_job(int $job_id, string $status, int $assigned_user_id): bool|WP_Error {
        if (!self::can_view()) { return new WP_Error('production_access', __('You do not have permission to manage production.', 'elev8-os')); }
        return Elev8_OS_Glass_Operations_Service::move_board_job($job_id, $status, $assigned_user_id);
    }

    public static function review_quality(int $job_id, string $status, string $notes): bool|WP_Error {
        if (!self::can_view()) { return new WP_Error('production_access', __('You do not have permission to review production quality.', 'elev8-os')); }
        return Elev8_OS_Glass_Operations_Service::review_quality($job_id, $status, $notes);
    }

    public static function record_fulfillment(int $job_id, string $status, string $method, string $notes): bool|WP_Error {
        if (!self::can_view()) { return new WP_Error('production_access', __('You do not have permission to record production handoff.', 'elev8-os')); }
        return Elev8_OS_Glass_Operations_Service::record_fulfillment($job_id, $status, $method, $notes);
    }

    public static function save_workstation(array $data): int|WP_Error {
        if (!class_exists('Elev8_OS_Production_Coordination_Service')) { return new WP_Error('production_coordination_missing', __('Production coordination is unavailable.', 'elev8-os')); }
        return Elev8_OS_Production_Coordination_Service::save_workstation($data);
    }

    public static function save_cycle(array $data): int|WP_Error {
        if (!class_exists('Elev8_OS_Production_Coordination_Service')) { return new WP_Error('production_coordination_missing', __('Production coordination is unavailable.', 'elev8-os')); }
        return Elev8_OS_Production_Coordination_Service::save_cycle($data);
    }

    public static function assign_workstation(array $data): bool|WP_Error {
        if (!class_exists('Elev8_OS_Production_Coordination_Service')) { return new WP_Error('production_coordination_missing', __('Production coordination is unavailable.', 'elev8-os')); }
        return Elev8_OS_Production_Coordination_Service::assign_job($data);
    }

    /** @return array<string,int>|WP_Error */
    public static function sync_compensation(int $job_id): array|WP_Error {
        if (!self::can_view()) { return new WP_Error('production_access', __('You do not have permission to manage production compensation evidence.', 'elev8-os')); }
        if (!class_exists('Elev8_OS_Glass_Operations_Service')) { return new WP_Error('production_glass_missing', __('Glass Operations is unavailable.', 'elev8-os')); }
        return Elev8_OS_Glass_Operations_Service::sync_completed_job_compensation($job_id);
    }

}

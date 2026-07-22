<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Reusable read model for production-oriented workspaces.
 *
 * The first adapter projects Glass Operations jobs. Future industries may add
 * adapters through the documented filters without duplicating source records.
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
        $metrics = ['ready'=>0,'running'=>0,'waiting'=>0,'blocked'=>0,'late'=>0,'quality'=>0,'completed_today'=>0];
        $queue = [];
        foreach ($jobs as $job) {
            $status = (string) ($job['status'] ?? 'new');
            $due = (string) ($job['due_date'] ?? '');
            $is_late = $due !== '' && $due < $today && !in_array($status, ['completed','cancelled'], true);
            if (in_array($status, ['ready_for_production','assigned'], true)) { $metrics['ready']++; }
            if ($status === 'in_production') { $metrics['running']++; }
            if (in_array($status, ['waiting','waiting_customer_info','waiting_ashes'], true)) { $metrics['waiting']++; }
            if (in_array($status, ['waiting_customer_info','waiting_ashes'], true)) { $metrics['blocked']++; }
            if ($status === 'quality_control') { $metrics['quality']++; }
            if ($is_late) { $metrics['late']++; }
            if ($status === 'completed' && !empty($job['updated_at']) && substr((string)$job['updated_at'], 0, 10) === $today) { $metrics['completed_today']++; }
            $queue[] = self::normalize_job($job, $is_late);
        }

        $snapshot = [
            'metrics' => $metrics,
            'queue' => $queue,
            'workers' => class_exists('Elev8_OS_Glass_Operations_Service') ? Elev8_OS_Glass_Operations_Service::glass_workers() : [],
            'statuses' => class_exists('Elev8_OS_Glass_Operations_Service') ? Elev8_OS_Glass_Operations_Service::workflow_statuses() : [],
            'configuration' => 'glass',
        ];
        return apply_filters('elev8_os_production_workspace_snapshot', $snapshot, $filters);
    }

    /** @return array<int,array<string,mixed>> */
    private static function glass_jobs(array $filters): array {
        if (!class_exists('Elev8_OS_Glass_Operations_Service')) { return []; }
        $args = [];
        foreach (['assigned_user_id','priority','search'] as $key) {
            if (!empty($filters[$key])) { $args[$key] = $filters[$key]; }
        }
        if (!empty($filters['overdue'])) { $args['overdue'] = true; }
        $jobs = Elev8_OS_Glass_Operations_Service::board_jobs($args);
        if (!empty($filters['status'])) {
            $status = sanitize_key((string)$filters['status']);
            $jobs = array_values(array_filter($jobs, static fn(array $job): bool => ($job['status'] ?? '') === $status));
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
            'id' => (int)$job['id'],
            'title' => $title,
            'type' => $type,
            'customer' => (string)($job['customer_name'] ?? ''),
            'order_number' => (string)($job['order_number'] ?? ''),
            'status' => (string)($job['status'] ?? 'new'),
            'priority' => (string)($job['priority'] ?? 'normal'),
            'due_date' => (string)($job['due_date'] ?? ''),
            'assigned_user_id' => (int)($job['assigned_user_id'] ?? 0),
            'assigned_name' => $worker instanceof WP_User ? $worker->display_name : __('Unassigned', 'elev8-os'),
            'planned_units' => (float)($job['planned_units'] ?? $job['quantity'] ?? 0),
            'completed_units' => (float)($job['completed_units'] ?? 0),
            'is_late' => $is_late,
            'is_blocked' => in_array((string)($job['status'] ?? ''), ['waiting_customer_info','waiting_ashes'], true),
            'source' => 'glass_job',
        ];
    }

    public static function update_job(int $job_id, string $status, int $assigned_user_id): bool|WP_Error {
        if (!self::can_view()) { return new WP_Error('production_access', __('You do not have permission to manage production.', 'elev8-os')); }
        return Elev8_OS_Glass_Operations_Service::move_board_job($job_id, $status, $assigned_user_id);
    }
}

<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Builds a verified, rule-based operational briefing for the Glass Manager.
 *
 * This service does not own production data. It reads the Glass Operations
 * source tables and returns presentation-ready totals and attention signals.
 */
final class Elev8_OS_Glass_Manager_Brief_Service {

    public static function build(): array {
        global $wpdb;

        $tables = Elev8_OS_Glass_Operations_Service::tables();
        $today = current_time('Y-m-d');
        $jobs = Elev8_OS_Glass_Operations_Service::board_jobs([]);
        $workers = Elev8_OS_Glass_Operations_Service::glass_workers();
        $workload = Elev8_OS_Glass_Operations_Service::board_workload($jobs, $workers);

        $active_jobs = array_values(array_filter($jobs, static function(array $job): bool {
            return !in_array($job['status'], ['completed', 'cancelled'], true);
        }));

        $metrics = [
            'open_jobs' => count($active_jobs),
            'due_today' => 0,
            'overdue' => 0,
            'unassigned' => 0,
            'in_production' => 0,
            'waiting_qc' => 0,
            'waiting_customer' => 0,
            'waiting_ashes' => 0,
            'ready_to_finish' => 0,
            'urgent' => 0,
            'pending_payout_count' => 0,
            'pending_payout_total' => 0.0,
            'approved_unexported_total' => 0.0,
            'rework_lines' => 0,
            'qc_lines' => 0,
        ];

        foreach ($active_jobs as $job) {
            if (!empty($job['due_date']) && $job['due_date'] === $today) { $metrics['due_today']++; }
            if (!empty($job['due_date']) && $job['due_date'] < $today) { $metrics['overdue']++; }
            if (empty($job['assigned_user_id'])) { $metrics['unassigned']++; }
            if ($job['status'] === 'in_production') { $metrics['in_production']++; }
            if ($job['status'] === 'quality_control') { $metrics['waiting_qc']++; }
            if ($job['status'] === 'waiting_customer_info') { $metrics['waiting_customer']++; }
            if ($job['status'] === 'waiting_ashes') { $metrics['waiting_ashes']++; }
            if (in_array($job['status'], ['ready_for_pickup', 'ready_to_ship'], true)) { $metrics['ready_to_finish']++; }
            if ($job['priority'] === 'urgent') { $metrics['urgent']++; }
        }

        $metrics['pending_payout_count'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tables['entries']} WHERE approval_status='pending'");
        $metrics['pending_payout_total'] = (float) $wpdb->get_var("SELECT COALESCE(SUM(total),0) FROM {$tables['entries']} WHERE approval_status='pending'");
        $metrics['approved_unexported_total'] = (float) $wpdb->get_var("SELECT COALESCE(SUM(total),0) FROM {$tables['entries']} WHERE approval_status='approved' AND pay_period_id=0");
        $metrics['rework_lines'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tables['job_lines']} WHERE qc_status='rework'");
        $metrics['qc_lines'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tables['job_lines']} WHERE quantity_completed>0 AND qc_status='not_reviewed'");

        $attention = self::attention_items($active_jobs, $metrics, $today);
        if (class_exists('Elev8_OS_Repair_Memorial_Service')) { $attention = array_merge(Elev8_OS_Repair_Memorial_Service::attention_items(), $attention); }
        $risk_points = ($metrics['overdue'] * 3) + ($metrics['urgent'] * 3) + ($metrics['unassigned'] * 2) + ($metrics['rework_lines'] * 2) + ($metrics['qc_lines']) + ($metrics['pending_payout_count'] > 0 ? 1 : 0);
        $pulse = $risk_points >= 8 ? 'action_required' : ($risk_points >= 3 ? 'needs_attention' : 'healthy');

        return [
            'generated_at' => current_time('mysql'),
            'today' => $today,
            'pulse' => $pulse,
            'risk_points' => $risk_points,
            'metrics' => $metrics,
            'attention' => $attention,
            'workload' => array_values($workload),
            'recent_jobs' => array_slice($jobs, 0, 8),
            'closeout' => self::closeout_items($metrics),
        ];
    }

    private static function attention_items(array $jobs, array $metrics, string $today): array {
        $items = [];

        foreach ($jobs as $job) {
            $title = $job['product_name'] ?: ('Production job #' . (int) $job['id']);
            if (!empty($job['due_date']) && $job['due_date'] < $today) {
                $items[] = [
                    'severity' => 'critical',
                    'kind' => 'job',
                    'job_id' => (int) $job['id'],
                    'title' => $title . ' is overdue',
                    'detail' => 'Due ' . $job['due_date'] . ($job['customer_name'] ? ' for ' . $job['customer_name'] : '') . '.',
                    'action' => 'Open job',
                ];
            } elseif ($job['priority'] === 'urgent') {
                $items[] = [
                    'severity' => 'high',
                    'kind' => 'job',
                    'job_id' => (int) $job['id'],
                    'title' => 'Urgent: ' . $title,
                    'detail' => empty($job['assigned_user_id']) ? 'This urgent job is not assigned.' : 'This job is marked urgent.',
                    'action' => 'Review job',
                ];
            } elseif (empty($job['assigned_user_id']) && in_array($job['status'], ['new', 'ready_for_production'], true)) {
                $items[] = [
                    'severity' => 'normal',
                    'kind' => 'job',
                    'job_id' => (int) $job['id'],
                    'title' => $title . ' needs a glassblower',
                    'detail' => 'Ready production work is waiting for assignment.',
                    'action' => 'Assign job',
                ];
            }
        }

        if ($metrics['qc_lines'] > 0) {
            $items[] = [
                'severity' => 'high',
                'kind' => 'board',
                'title' => $metrics['qc_lines'] . ' completed line(s) need QC',
                'detail' => 'Completed production has not yet received a QC decision.',
                'action' => 'Review board',
            ];
        }
        if ($metrics['rework_lines'] > 0) {
            $items[] = [
                'severity' => 'high',
                'kind' => 'board',
                'title' => $metrics['rework_lines'] . ' production line(s) require rework',
                'detail' => 'Rework should be assigned before the related jobs can close.',
                'action' => 'Review rework',
            ];
        }
        if ($metrics['pending_payout_count'] > 0) {
            $items[] = [
                'severity' => 'normal',
                'kind' => 'payouts',
                'title' => $metrics['pending_payout_count'] . ' pay entr' . ($metrics['pending_payout_count'] === 1 ? 'y' : 'ies') . ' need approval',
                'detail' => '$' . number_format_i18n($metrics['pending_payout_total'], 2) . ' is waiting for manager review.',
                'action' => 'Review pay sheets',
            ];
        }
        if ($metrics['waiting_ashes'] > 0) {
            $items[] = [
                'severity' => 'normal',
                'kind' => 'board',
                'title' => $metrics['waiting_ashes'] . ' memorial job(s) are waiting on ashes',
                'detail' => 'These jobs cannot enter production until chain-of-custody intake is complete.',
                'action' => 'Open board',
            ];
        }
        if ($metrics['waiting_customer'] > 0) {
            $items[] = [
                'severity' => 'normal',
                'kind' => 'board',
                'title' => $metrics['waiting_customer'] . ' job(s) are waiting on customer information',
                'detail' => 'A customer detail or approval is blocking production.',
                'action' => 'Open board',
            ];
        }

        $weight = ['critical' => 0, 'high' => 1, 'normal' => 2];
        usort($items, static function(array $a, array $b) use ($weight): int {
            return ($weight[$a['severity']] ?? 9) <=> ($weight[$b['severity']] ?? 9);
        });
        return array_slice($items, 0, 12);
    }

    private static function closeout_items(array $metrics): array {
        return [
            ['label' => 'All urgent and overdue jobs reviewed', 'complete' => $metrics['urgent'] === 0 && $metrics['overdue'] === 0, 'kind' => 'board'],
            ['label' => 'Ready work assigned to a glassblower', 'complete' => $metrics['unassigned'] === 0, 'kind' => 'board'],
            ['label' => 'Completed production reviewed for QC', 'complete' => $metrics['qc_lines'] === 0 && $metrics['rework_lines'] === 0, 'kind' => 'board'],
            ['label' => 'Glassblower pay entries reviewed', 'complete' => $metrics['pending_payout_count'] === 0, 'kind' => 'payouts'],
            ['label' => 'Customer and memorial blockers reviewed', 'complete' => $metrics['waiting_customer'] === 0 && $metrics['waiting_ashes'] === 0, 'kind' => 'board'],
        ];
    }
}

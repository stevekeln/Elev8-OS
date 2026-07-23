<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Reusable SOP execution evidence for canonical Work Items.
 *
 * Contributor contracts describe what must happen. This service owns only the
 * execution evidence proving checklist steps and approvals were completed.
 */
final class Elev8_OS_SOP_Execution_Service {
    public const META_CHECKLIST_PROGRESS = '_elev8_work_checklist_progress';
    public const META_APPROVAL_EVIDENCE = '_elev8_work_approval_evidence';
    public const META_TIMELINE = '_elev8_work_execution_timeline';

    public static function init(): void {
        add_filter('elev8_os_business_graph_objects', [__CLASS__, 'register_graph_objects']);
    }

    /** Preserve compatible evidence whenever a contributor refreshes its contract. */
    public static function reconcile_contract(int $work_id, array $checklist, array $approvals): void {
        $progress = self::reconcile_items((array) get_post_meta($work_id, self::META_CHECKLIST_PROGRESS, true), $checklist, 'completed');
        $evidence = self::reconcile_items((array) get_post_meta($work_id, self::META_APPROVAL_EVIDENCE, true), $approvals, 'approved');
        update_post_meta($work_id, self::META_CHECKLIST_PROGRESS, $progress);
        update_post_meta($work_id, self::META_APPROVAL_EVIDENCE, $evidence);
    }

    /** @return array<string,mixed> */
    public static function execution(int $work_id): array {
        $checklist = self::contract_items((array) get_post_meta($work_id, Elev8_OS_Operations_Contributor_Service::META_CHECKLIST, true));
        $approvals = self::contract_items((array) get_post_meta($work_id, Elev8_OS_Operations_Contributor_Service::META_APPROVALS, true));
        $progress = (array) get_post_meta($work_id, self::META_CHECKLIST_PROGRESS, true);
        $evidence = (array) get_post_meta($work_id, self::META_APPROVAL_EVIDENCE, true);
        return [
            'checklist' => self::merge_state($checklist, $progress, 'completed'),
            'approvals' => self::merge_state($approvals, $evidence, 'approved'),
            'timeline' => array_values((array) get_post_meta($work_id, self::META_TIMELINE, true)),
            'ready' => self::is_ready_from_state($checklist, $approvals, $progress, $evidence),
        ];
    }

    /** @param array<string,mixed> $input */
    public static function save(int $work_id, array $input, int $actor_user_id) {
        if (get_post_type($work_id) !== Elev8_OS_Work_Service::POST_TYPE) {
            return new WP_Error('invalid_work_item', __('Invalid work item.', 'elev8-os'));
        }
        $checklist = self::contract_items((array) get_post_meta($work_id, Elev8_OS_Operations_Contributor_Service::META_CHECKLIST, true));
        $approvals = self::contract_items((array) get_post_meta($work_id, Elev8_OS_Operations_Contributor_Service::META_APPROVALS, true));
        $old_progress = (array) get_post_meta($work_id, self::META_CHECKLIST_PROGRESS, true);
        $old_evidence = (array) get_post_meta($work_id, self::META_APPROVAL_EVIDENCE, true);
        $submitted_checks = array_map('sanitize_key', (array) ($input['checklist'] ?? []));
        $submitted_approvals = array_map('sanitize_key', (array) ($input['approvals'] ?? []));
        $approval_notes = (array) ($input['approval_notes'] ?? []);
        $now = current_time('mysql');

        $progress = [];
        foreach ($checklist as $id => $label) {
            $completed = in_array($id, $submitted_checks, true);
            $previous = is_array($old_progress[$id] ?? null) ? $old_progress[$id] : [];
            $progress[$id] = [
                'label' => $label,
                'completed' => $completed,
                'completed_at' => $completed ? ((string) ($previous['completed_at'] ?? '') ?: $now) : '',
                'completed_by' => $completed ? (absint($previous['completed_by'] ?? 0) ?: $actor_user_id) : 0,
            ];
            if ($completed !== !empty($previous['completed'])) {
                self::record_timeline($work_id, $completed ? 'checklist_completed' : 'checklist_reopened', $label, $actor_user_id);
            }
        }

        $evidence = [];
        foreach ($approvals as $id => $label) {
            $approved = in_array($id, $submitted_approvals, true);
            $previous = is_array($old_evidence[$id] ?? null) ? $old_evidence[$id] : [];
            $note = sanitize_textarea_field((string) ($approval_notes[$id] ?? ($previous['note'] ?? '')));
            $evidence[$id] = [
                'label' => $label,
                'approved' => $approved,
                'approved_at' => $approved ? ((string) ($previous['approved_at'] ?? '') ?: $now) : '',
                'approved_by' => $approved ? (absint($previous['approved_by'] ?? 0) ?: $actor_user_id) : 0,
                'note' => $note,
            ];
            if ($approved !== !empty($previous['approved'])) {
                self::record_timeline($work_id, $approved ? 'approval_recorded' : 'approval_reopened', $label, $actor_user_id, $note);
            }
        }

        update_post_meta($work_id, self::META_CHECKLIST_PROGRESS, $progress);
        update_post_meta($work_id, self::META_APPROVAL_EVIDENCE, $evidence);
        return true;
    }

    public static function validate_completion(int $work_id) {
        $execution = self::execution($work_id);
        if ($execution['ready']) { return true; }
        $missing = [];
        foreach ($execution['checklist'] as $item) { if (empty($item['completed'])) { $missing[] = (string) $item['label']; } }
        foreach ($execution['approvals'] as $item) { if (empty($item['approved'])) { $missing[] = (string) $item['label']; } }
        return new WP_Error('execution_evidence_incomplete', sprintf(__('Complete the required execution evidence before closing this work item: %s', 'elev8-os'), implode(', ', $missing)));
    }

    /** @return array<string,string> */
    private static function contract_items(array $labels): array {
        $items = [];
        foreach ($labels as $index => $label) {
            $label = sanitize_text_field((string) $label);
            if ($label === '') { continue; }
            $id = sanitize_key(substr(md5($label), 0, 10) . '-' . sanitize_title($label));
            if ($id === '') { $id = 'item-' . absint($index); }
            $items[$id] = $label;
        }
        return $items;
    }

    private static function reconcile_items(array $existing, array $labels, string $state_key): array {
        $out = [];
        foreach (self::contract_items($labels) as $id => $label) {
            $state = is_array($existing[$id] ?? null) ? $existing[$id] : [];
            $state['label'] = $label;
            $state[$state_key] = !empty($state[$state_key]);
            $out[$id] = $state;
        }
        return $out;
    }

    private static function merge_state(array $contract, array $state, string $state_key): array {
        $out = [];
        foreach ($contract as $id => $label) {
            $entry = is_array($state[$id] ?? null) ? $state[$id] : [];
            $entry['id'] = $id;
            $entry['label'] = $label;
            $entry[$state_key] = !empty($entry[$state_key]);
            $out[] = $entry;
        }
        return $out;
    }

    private static function is_ready_from_state(array $checklist, array $approvals, array $progress, array $evidence): bool {
        foreach ($checklist as $id => $_label) { if (empty($progress[$id]['completed'])) { return false; } }
        foreach ($approvals as $id => $_label) { if (empty($evidence[$id]['approved'])) { return false; } }
        return true;
    }

    private static function record_timeline(int $work_id, string $type, string $label, int $actor_user_id, string $note = ''): void {
        $timeline = (array) get_post_meta($work_id, self::META_TIMELINE, true);
        $timeline[] = ['type'=>sanitize_key($type),'label'=>sanitize_text_field($label),'note'=>sanitize_textarea_field($note),'actor_user_id'=>$actor_user_id,'created_at'=>current_time('mysql')];
        if (count($timeline) > 250) { $timeline = array_slice($timeline, -250); }
        update_post_meta($work_id, self::META_TIMELINE, array_values($timeline));
        if (class_exists('Elev8_OS_Activity_Service')) {
            Elev8_OS_Activity_Service::record(['type'=>$type,'label'=>$label,'details'=>$note,'object_id'=>$work_id,'object_type'=>Elev8_OS_Work_Service::POST_TYPE,'source'=>'sop_execution','actor_user_id'=>$actor_user_id]);
        }
    }

    public static function register_graph_objects(array $objects): array {
        $objects['sop_execution'] = [
            'label' => __('SOP Execution', 'elev8-os'),
            'engine' => 'Workflow',
            'authority' => 'elev8_os',
            'scope' => 'organization',
            'notes' => 'Execution evidence linked to a canonical Work Item; it does not duplicate the source operational record.',
        ];
        return $objects;
    }
}

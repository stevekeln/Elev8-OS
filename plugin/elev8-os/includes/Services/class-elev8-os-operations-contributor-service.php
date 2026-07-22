<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Registry and synchronization layer for operational source records that
 * contribute Work Items without surrendering ownership of source data.
 */
final class Elev8_OS_Operations_Contributor_Service {
    public const META_CONTRIBUTOR = '_elev8_work_contributor_key';
    public const META_CHECKLIST = '_elev8_work_checklist';
    public const META_APPROVALS = '_elev8_work_required_approvals';
    public const META_COMPLETION_RULES = '_elev8_work_completion_rules';
    public const META_ESCALATION = '_elev8_work_escalation';
    public const META_SOURCE_STATUS = '_elev8_work_source_status';
    public const META_LAST_SYNCED_AT = '_elev8_work_last_synced_at';

    public static function init(): void {
        add_action('elev8_os_operations_source_changed', [__CLASS__, 'sync_source'], 10, 3);
        add_filter('elev8_os_business_graph_objects', [__CLASS__, 'register_graph_objects']);
    }

    /** @return array<string,array<string,mixed>> */
    public static function contributors(): array {
        $contributors = [];
        return apply_filters('elev8_os_operations_contributors', $contributors);
    }

    /**
     * Synchronize one authoritative source record into its contributed work.
     *
     * @param array<string,mixed> $context
     * @return array<int,int>|WP_Error
     */
    public static function sync_source(string $contributor_key, int $source_id, array $context = []) {
        $key = sanitize_key($contributor_key);
        $definition = self::contributors()[$key] ?? null;
        if (!$definition || $source_id < 1) {
            return new WP_Error('invalid_operations_contributor', __('The operations contributor could not be resolved.', 'elev8-os'));
        }
        $resolver = $definition['resolve'] ?? null;
        if (!is_callable($resolver)) {
            return new WP_Error('invalid_operations_contributor_resolver', __('The operations contributor has no source resolver.', 'elev8-os'));
        }
        $source = call_user_func($resolver, $source_id, $context);
        if (!is_array($source) || empty($source['id'])) {
            return new WP_Error('operations_source_unavailable', __('The authoritative source record is unavailable.', 'elev8-os'));
        }

        $steps = $definition['steps'] ?? [];
        if (is_callable($steps)) { $steps = call_user_func($steps, $source, $context); }
        if (!is_array($steps)) { $steps = []; }

        $ids = [];
        foreach ($steps as $step_key => $step) {
            if (!is_array($step)) { continue; }
            $step_key = sanitize_key(is_string($step_key) ? $step_key : (string)($step['step_key'] ?? ''));
            if ($step_key === '') { continue; }
            $active = !array_key_exists('active', $step) || (bool)$step['active'];
            $existing = self::find_work($key, $source_id, $step_key);
            if (!$active) {
                if ($existing) { self::close_obsolete_work($existing, (string)($source['status'] ?? '')); }
                continue;
            }

            $work_args = [
                'title' => $step['title'] ?? '',
                'description' => $step['description'] ?? '',
                'type' => $step['type'] ?? ($definition['work_type'] ?? 'general'),
                'owner_user_id' => absint($step['owner_user_id'] ?? ($source['owner_user_id'] ?? 0)),
                'due_date' => $step['due_date'] ?? ($source['due_date'] ?? ''),
                'priority' => $step['priority'] ?? ($source['priority'] ?? 'normal'),
                'status' => $step['status'] ?? self::map_source_status((string)($source['status'] ?? '')),
                'source_type' => $definition['source_type'] ?? $key,
                'source_id' => $source_id,
                'workflow_key' => $key,
                'step_key' => $step_key,
                'organization_unit_id' => absint($source['organization_unit_id'] ?? 0),
                'requested_by_user_id' => absint($source['requested_by_user_id'] ?? 0),
                'customer_person_id' => absint($source['customer_person_id'] ?? 0),
            ];
            $work_id = $existing ?: Elev8_OS_Operations_Engine_Service::create_work($work_args);
            if (is_wp_error($work_id)) { return $work_id; }
            $work_id = (int)$work_id;
            if ($existing) {
                Elev8_OS_Work_Service::update($work_id, [
                    'title' => $work_args['title'],
                    'description' => $work_args['description'],
                    'owner_user_id' => $work_args['owner_user_id'],
                    'due_date' => $work_args['due_date'],
                    'priority' => $work_args['priority'],
                    'status' => $work_args['status'],
                    'work_type' => $work_args['type'],
                    'organization_unit_id' => $work_args['organization_unit_id'],
                ]);
            }
            self::store_execution_contract($work_id, $key, $step, (string)($source['status'] ?? ''));
            $ids[] = $work_id;
        }
        return $ids;
    }

    /** @param array<string,mixed> $step */
    private static function store_execution_contract(int $work_id, string $key, array $step, string $source_status): void {
        update_post_meta($work_id, self::META_CONTRIBUTOR, $key);
        update_post_meta($work_id, self::META_CHECKLIST, self::clean_list($step['checklist'] ?? []));
        update_post_meta($work_id, self::META_APPROVALS, self::clean_list($step['required_approvals'] ?? []));
        update_post_meta($work_id, self::META_COMPLETION_RULES, self::clean_list($step['completion_rules'] ?? []));
        update_post_meta($work_id, self::META_ESCALATION, self::clean_escalation($step['escalation'] ?? []));
        update_post_meta($work_id, self::META_SOURCE_STATUS, sanitize_key($source_status));
        update_post_meta($work_id, self::META_LAST_SYNCED_AT, current_time('mysql'));
        if (class_exists('Elev8_OS_SOP_Execution_Service')) {
            Elev8_OS_SOP_Execution_Service::reconcile_contract(
                $work_id,
                self::clean_list($step['checklist'] ?? []),
                self::clean_list($step['required_approvals'] ?? [])
            );
        }
    }

    private static function find_work(string $key, int $source_id, string $step_key): int {
        $posts = get_posts([
            'post_type' => Elev8_OS_Work_Service::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_query' => [
                ['key' => self::META_CONTRIBUTOR, 'value' => $key],
                ['key' => '_elev8_work_source_id', 'value' => $source_id, 'type' => 'NUMERIC'],
                ['key' => '_elev8_work_step_key', 'value' => $step_key],
            ],
        ]);
        return $posts ? (int)$posts[0] : 0;
    }

    private static function close_obsolete_work(int $work_id, string $source_status): void {
        $current = (string)get_post_meta($work_id, '_elev8_work_status', true);
        if (!in_array($current, ['completed','cancelled','archived'], true)) {
            Elev8_OS_Work_Service::update($work_id, ['status' => $source_status === 'cancelled' ? 'cancelled' : 'completed']);
        }
        update_post_meta($work_id, self::META_SOURCE_STATUS, sanitize_key($source_status));
        update_post_meta($work_id, self::META_LAST_SYNCED_AT, current_time('mysql'));
    }

    private static function map_source_status(string $status): string {
        $map = [
            'new'=>'requested', 'ready_for_production'=>'queued', 'assigned'=>'assigned', 'in_production'=>'in_progress',
            'waiting'=>'waiting', 'waiting_customer_info'=>'waiting', 'waiting_ashes'=>'waiting', 'quality_control'=>'review',
            'ready_for_pickup'=>'approved', 'ready_to_ship'=>'approved', 'completed'=>'completed', 'cancelled'=>'cancelled',
        ];
        return $map[sanitize_key($status)] ?? 'requested';
    }

    /** @return array<int,string> */
    private static function clean_list($items): array {
        if (!is_array($items)) { return []; }
        $clean = [];
        foreach ($items as $item) {
            $item = sanitize_text_field((string)$item);
            if ($item !== '') { $clean[] = $item; }
        }
        return array_values(array_unique($clean));
    }

    /** @return array<string,mixed> */
    private static function clean_escalation($value): array {
        if (!is_array($value)) { return []; }
        return [
            'after_days' => max(0, absint($value['after_days'] ?? 0)),
            'priority' => isset(Elev8_OS_Work_Service::priorities()[sanitize_key((string)($value['priority'] ?? ''))]) ? sanitize_key((string)$value['priority']) : '',
            'notify_capability' => sanitize_key((string)($value['notify_capability'] ?? '')),
        ];
    }

    public static function register_graph_objects(array $objects): array {
        $objects['operations_contributor'] = [
            'label' => __('Operations Contributor', 'elev8-os'),
            'engine' => 'operations',
            'authority' => 'elev8_os',
            'scope' => 'organization',
        ];
        return $objects;
    }
}

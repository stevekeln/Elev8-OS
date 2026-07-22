<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Canonical maintenance records for equipment, facilities, inspections, and safety work.
 *
 * Assets and facilities remain authoritative in their owning systems. This service owns
 * only the maintenance condition, schedule, service history, and operational state.
 */
final class Elev8_OS_Maintenance_Service {
    public const POST_TYPE = 'elev8_maintenance';
    public const CRON_HOOK = 'elev8_os_maintenance_due_scan';
    private const META_PREFIX = '_elev8_maintenance_';

    public static function init(): void {
        add_action('init', [__CLASS__, 'register_post_type']);
        add_action('init', [__CLASS__, 'ensure_scan_scheduled'], 30);
        add_action(self::CRON_HOOK, [__CLASS__, 'scan_due_records']);
        add_action('elev8_os_operations_entry_created', [__CLASS__, 'capture_maintenance_log'], 10, 3);
        add_filter('elev8_os_business_graph_objects', [__CLASS__, 'register_graph_objects']);
    }

    public static function register_post_type(): void {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name' => __('Maintenance Records', 'elev8-os'),
                'singular_name' => __('Maintenance Record', 'elev8-os'),
            ],
            'public' => false,
            'show_ui' => false,
            'show_in_rest' => false,
            'supports' => ['title'],
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ]);
    }

    public static function ensure_scan_scheduled(): void {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK);
        }
    }

    /**
     * Create or update a canonical maintenance record.
     *
     * @param array<string,mixed> $args
     * @return int|WP_Error
     */
    public static function upsert(array $args) {
        $type = sanitize_key((string)($args['maintenance_type'] ?? 'maintenance_request'));
        $allowed = ['maintenance_request', 'asset_repair', 'preventive_maintenance', 'inspection', 'safety_check'];
        if (!in_array($type, $allowed, true)) {
            return new WP_Error('invalid_maintenance_type', __('The maintenance type is invalid.', 'elev8-os'));
        }

        $source_type = sanitize_key((string)($args['source_type'] ?? 'maintenance')) ?: 'maintenance';
        $source_id = absint($args['source_id'] ?? 0);
        $organization_unit_id = absint($args['organization_unit_id'] ?? 0);
        $record_id = absint($args['id'] ?? 0);
        if ($record_id < 1 && $source_id > 0) {
            $record_id = self::find_open_record($type, $source_type, $source_id, $organization_unit_id);
        }

        $title = sanitize_text_field((string)($args['title'] ?? ''));
        $asset_label = sanitize_text_field((string)($args['asset_label'] ?? ''));
        if ($title === '') {
            $title = $asset_label !== ''
                ? sprintf(__('%1$s — %2$s', 'elev8-os'), self::type_label($type), $asset_label)
                : self::type_label($type);
        }

        if ($record_id < 1) {
            $record_id = wp_insert_post([
                'post_type' => self::POST_TYPE,
                'post_status' => 'publish',
                'post_title' => $title,
            ], true);
            if (is_wp_error($record_id)) { return $record_id; }
        } else {
            wp_update_post(['ID' => $record_id, 'post_title' => $title]);
        }
        $record_id = absint($record_id);

        $status = sanitize_key((string)($args['status'] ?? 'open')) ?: 'open';
        if (!in_array($status, ['open', 'assigned', 'in_progress', 'waiting', 'resolved', 'cancelled'], true)) {
            $status = 'open';
        }
        $priority = sanitize_key((string)($args['priority'] ?? 'normal')) ?: 'normal';
        if (!in_array($priority, ['low', 'normal', 'high', 'urgent'], true)) { $priority = 'normal'; }
        $due_date = self::clean_date((string)($args['due_date'] ?? ''));

        $fields = [
            'maintenance_type' => $type,
            'source_type' => $source_type,
            'source_id' => $source_id,
            'status' => $status,
            'priority' => $priority,
            'owner_user_id' => absint($args['owner_user_id'] ?? 0),
            'requested_by_user_id' => absint($args['requested_by_user_id'] ?? get_current_user_id()),
            'organization_unit_id' => $organization_unit_id,
            'asset_id' => absint($args['asset_id'] ?? 0),
            'asset_type' => sanitize_key((string)($args['asset_type'] ?? '')),
            'asset_label' => $asset_label,
            'location_label' => sanitize_text_field((string)($args['location_label'] ?? '')),
            'description' => sanitize_textarea_field((string)($args['description'] ?? '')),
            'due_date' => $due_date,
            'recurrence_days' => max(0, absint($args['recurrence_days'] ?? 0)),
            'last_service_date' => self::clean_date((string)($args['last_service_date'] ?? '')),
            'completed_date' => self::clean_date((string)($args['completed_date'] ?? '')),
            'resolution_notes' => sanitize_textarea_field((string)($args['resolution_notes'] ?? '')),
            'context' => is_array($args['context'] ?? null) ? self::clean_context($args['context']) : [],
            'updated_at' => current_time('mysql'),
        ];
        if (!get_post_meta($record_id, self::META_PREFIX . 'created_at', true)) {
            $fields['created_at'] = current_time('mysql');
        }
        foreach ($fields as $key => $value) {
            update_post_meta($record_id, self::META_PREFIX . $key, $value);
        }

        do_action('elev8_os_maintenance_record_changed', $record_id, self::get($record_id));
        return $record_id;
    }

    /** @return array<string,mixed> */
    public static function get(int $record_id): array {
        if (get_post_type($record_id) !== self::POST_TYPE) { return []; }
        $keys = [
            'maintenance_type', 'source_type', 'source_id', 'status', 'priority',
            'owner_user_id', 'requested_by_user_id', 'organization_unit_id',
            'asset_id', 'asset_type', 'asset_label', 'location_label', 'description',
            'due_date', 'recurrence_days', 'last_service_date', 'completed_date',
            'resolution_notes', 'context', 'created_at', 'updated_at', 'resolved_at',
            'resolved_by_user_id', 'parent_record_id',
        ];
        $data = ['id' => $record_id, 'title' => get_the_title($record_id)];
        foreach ($keys as $key) {
            $data[$key] = get_post_meta($record_id, self::META_PREFIX . $key, true);
        }
        return $data;
    }

    /**
     * Resolve a record and, when configured, create the next recurring service instance.
     *
     * @return int|false Next recurring record ID, or false when none was created.
     */
    public static function resolve(int $record_id, string $notes = '') {
        $record = self::get($record_id);
        if (!$record) { return false; }

        $today = current_time('Y-m-d');
        update_post_meta($record_id, self::META_PREFIX . 'status', 'resolved');
        update_post_meta($record_id, self::META_PREFIX . 'completed_date', $today);
        update_post_meta($record_id, self::META_PREFIX . 'last_service_date', $today);
        update_post_meta($record_id, self::META_PREFIX . 'resolved_at', current_time('mysql'));
        update_post_meta($record_id, self::META_PREFIX . 'resolved_by_user_id', get_current_user_id());
        if ($notes !== '') {
            update_post_meta($record_id, self::META_PREFIX . 'resolution_notes', sanitize_textarea_field($notes));
        }
        do_action('elev8_os_maintenance_record_changed', $record_id, self::get($record_id));

        $days = absint($record['recurrence_days'] ?? 0);
        if ($days < 1 || in_array((string)($record['maintenance_type'] ?? ''), ['maintenance_request', 'asset_repair'], true)) {
            return false;
        }
        $next_due = wp_date('Y-m-d', current_time('timestamp') + ($days * DAY_IN_SECONDS));
        $next = self::upsert([
            'maintenance_type' => $record['maintenance_type'],
            'source_type' => $record['source_type'],
            'source_id' => absint($record['source_id'] ?? 0),
            'status' => 'open',
            'priority' => $record['priority'],
            'owner_user_id' => absint($record['owner_user_id'] ?? 0),
            'requested_by_user_id' => absint($record['requested_by_user_id'] ?? 0),
            'organization_unit_id' => absint($record['organization_unit_id'] ?? 0),
            'asset_id' => absint($record['asset_id'] ?? 0),
            'asset_type' => $record['asset_type'],
            'asset_label' => $record['asset_label'],
            'location_label' => $record['location_label'],
            'description' => $record['description'],
            'due_date' => $next_due,
            'recurrence_days' => $days,
            'last_service_date' => $today,
            'context' => is_array($record['context'] ?? null) ? $record['context'] : [],
        ]);
        if (!is_wp_error($next)) {
            update_post_meta((int)$next, self::META_PREFIX . 'parent_record_id', $record_id);
            return (int)$next;
        }
        return false;
    }

    public static function cancel(int $record_id, string $notes = ''): bool {
        if (get_post_type($record_id) !== self::POST_TYPE) { return false; }
        update_post_meta($record_id, self::META_PREFIX . 'status', 'cancelled');
        if ($notes !== '') { update_post_meta($record_id, self::META_PREFIX . 'resolution_notes', sanitize_textarea_field($notes)); }
        update_post_meta($record_id, self::META_PREFIX . 'updated_at', current_time('mysql'));
        do_action('elev8_os_maintenance_record_changed', $record_id, self::get($record_id));
        return true;
    }

    /** @return array<int,array<string,mixed>> */
    public static function get_asset_history(int $asset_id, int $limit = 100): array {
        if ($asset_id < 1) { return []; }
        $ids = get_posts([
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => max(1, min(500, $limit)),
            'fields' => 'ids',
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_key' => self::META_PREFIX . 'asset_id',
            'meta_value' => $asset_id,
            'meta_type' => 'NUMERIC',
        ]);
        return array_values(array_filter(array_map([__CLASS__, 'get'], array_map('absint', $ids))));
    }

    /** Daily synchronization and overdue escalation for open maintenance records. */
    public static function scan_due_records(): int {
        $ids = get_posts([
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => 500,
            'fields' => 'ids',
            'meta_query' => [[
                'key' => self::META_PREFIX . 'status',
                'value' => ['open', 'assigned', 'in_progress', 'waiting'],
                'compare' => 'IN',
            ]],
        ]);
        $today = current_time('Y-m-d');
        foreach ($ids as $id) {
            $record = self::get((int)$id);
            if (!empty($record['due_date']) && (string)$record['due_date'] < $today && !in_array((string)$record['priority'], ['urgent'], true)) {
                update_post_meta((int)$id, self::META_PREFIX . 'priority', 'urgent');
                $record = self::get((int)$id);
            }
            do_action('elev8_os_maintenance_record_changed', (int)$id, $record);
        }
        return count($ids);
    }

    /**
     * Convert the existing Maintenance Log into the canonical maintenance source.
     *
     * @param array<string,mixed> $values
     */
    public static function capture_maintenance_log(int $entry_id, string $template_key, array $values): void {
        if ($template_key !== 'maintenance' || $entry_id < 1) { return; }
        $status_map = [
            'reported' => 'open', 'assigned' => 'assigned', 'in_progress' => 'in_progress',
            'waiting' => 'waiting', 'completed' => 'resolved',
        ];
        $raw_status = sanitize_key(str_replace(' ', '_', strtolower((string)($values['work_status'] ?? 'reported'))));
        $priority = sanitize_key(strtolower((string)($values['priority'] ?? 'normal')));
        $author = absint(get_post_field('post_author', $entry_id));
        $record_id = self::upsert([
            'maintenance_type' => 'maintenance_request',
            'source_type' => 'operations_log',
            'source_id' => $entry_id,
            'status' => $status_map[$raw_status] ?? 'open',
            'priority' => in_array($priority, ['low', 'normal', 'high', 'urgent'], true) ? $priority : 'normal',
            'requested_by_user_id' => $author,
            'asset_label' => sanitize_text_field((string)($values['equipment'] ?? '')),
            'location_label' => sanitize_text_field((string)($values['problem_location'] ?? '')),
            'description' => sanitize_textarea_field((string)($values['problem'] ?? '')),
            'completed_date' => self::clean_date((string)($values['completed_date'] ?? '')),
            'context' => [
                'assigned_to_label' => sanitize_text_field((string)($values['assigned_to'] ?? '')),
                'operations_log_id' => $entry_id,
            ],
        ]);
        if (!is_wp_error($record_id) && ($status_map[$raw_status] ?? '') === 'resolved') {
            self::resolve((int)$record_id, __('Completed through the Maintenance Log.', 'elev8-os'));
        }
    }

    public static function register_graph_objects(array $objects): array {
        $objects['maintenance_record'] = [
            'label' => __('Maintenance Record', 'elev8-os'),
            'engine' => 'operations',
            'authority' => 'elev8_os',
            'scope' => 'organization',
        ];
        return $objects;
    }

    private static function find_open_record(string $type, string $source_type, int $source_id, int $organization_unit_id): int {
        $meta_query = [
            ['key' => self::META_PREFIX . 'maintenance_type', 'value' => $type],
            ['key' => self::META_PREFIX . 'source_type', 'value' => $source_type],
            ['key' => self::META_PREFIX . 'source_id', 'value' => $source_id, 'type' => 'NUMERIC'],
            ['key' => self::META_PREFIX . 'status', 'value' => ['open', 'assigned', 'in_progress', 'waiting'], 'compare' => 'IN'],
        ];
        if ($organization_unit_id > 0) {
            $meta_query[] = ['key' => self::META_PREFIX . 'organization_unit_id', 'value' => $organization_unit_id, 'type' => 'NUMERIC'];
        }
        $ids = get_posts([
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_query' => $meta_query,
        ]);
        return $ids ? absint($ids[0]) : 0;
    }

    private static function type_label(string $type): string {
        $labels = [
            'maintenance_request' => __('Maintenance request', 'elev8-os'),
            'asset_repair' => __('Asset repair', 'elev8-os'),
            'preventive_maintenance' => __('Preventive maintenance', 'elev8-os'),
            'inspection' => __('Inspection', 'elev8-os'),
            'safety_check' => __('Safety check', 'elev8-os'),
        ];
        return $labels[$type] ?? __('Maintenance', 'elev8-os');
    }

    private static function clean_date(string $date): string {
        $date = sanitize_text_field($date);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : '';
    }

    /** @param array<string,mixed> $context @return array<string,mixed> */
    private static function clean_context(array $context): array {
        $clean = [];
        foreach ($context as $key => $value) {
            $key = sanitize_key((string)$key);
            if ($key === '') { continue; }
            if (is_scalar($value) || $value === null) {
                $clean[$key] = sanitize_text_field((string)$value);
            }
        }
        return $clean;
    }
}

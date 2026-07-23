<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Configurable workstation and production-cycle coordination.
 *
 * This service owns coordination evidence only. Glass Operations remains
 * authoritative for production jobs, and Assets remains authoritative for
 * physical equipment connected to a workstation.
 */
final class Elev8_OS_Production_Coordination_Service {
    private const DB_VERSION = '1.0.0';
    private const OPTION_DB_VERSION = 'elev8_os_production_coordination_db_version';

    public static function init(): void {
        add_action('init', [__CLASS__, 'maybe_install'], 8);
    }

    public static function maybe_install(): void {
        if (get_option(self::OPTION_DB_VERSION) !== self::DB_VERSION) {
            self::install();
        }
    }

    /** @return array<string,string> */
    public static function tables(): array {
        global $wpdb;
        return [
            'workstations' => $wpdb->prefix . 'elev8_production_workstations',
            'cycles' => $wpdb->prefix . 'elev8_production_cycles',
            'allocations' => $wpdb->prefix . 'elev8_production_allocations',
        ];
    }

    private static function install(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $t = self::tables();
        $c = $wpdb->get_charset_collate();

        dbDelta("CREATE TABLE {$t['workstations']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(190) NOT NULL DEFAULT '',
            workstation_type varchar(40) NOT NULL DEFAULT 'other',
            organization_unit_id bigint(20) unsigned NOT NULL DEFAULT 0,
            asset_id bigint(20) unsigned NOT NULL DEFAULT 0,
            capacity_units decimal(10,2) NOT NULL DEFAULT 1.00,
            active tinyint(1) NOT NULL DEFAULT 1,
            notes text NOT NULL,
            created_by bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY active_type (active,workstation_type),
            KEY organization_unit_id (organization_unit_id),
            KEY asset_id (asset_id)
        ) {$c};");

        dbDelta("CREATE TABLE {$t['cycles']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            workstation_id bigint(20) unsigned NOT NULL DEFAULT 0,
            cycle_type varchar(40) NOT NULL DEFAULT 'production',
            scheduled_start datetime NULL,
            scheduled_end datetime NULL,
            status varchar(30) NOT NULL DEFAULT 'planned',
            capacity_units decimal(10,2) NOT NULL DEFAULT 1.00,
            notes text NOT NULL,
            created_by bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY workstation_status (workstation_id,status),
            KEY scheduled_start (scheduled_start)
        ) {$c};");

        dbDelta("CREATE TABLE {$t['allocations']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            job_id bigint(20) unsigned NOT NULL DEFAULT 0,
            workstation_id bigint(20) unsigned NOT NULL DEFAULT 0,
            cycle_id bigint(20) unsigned NOT NULL DEFAULT 0,
            allocation_status varchar(30) NOT NULL DEFAULT 'planned',
            planned_start datetime NULL,
            planned_end datetime NULL,
            notes text NOT NULL,
            created_by bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY job_allocation (job_id),
            KEY workstation_id (workstation_id),
            KEY cycle_id (cycle_id),
            KEY allocation_status (allocation_status)
        ) {$c};");

        update_option(self::OPTION_DB_VERSION, self::DB_VERSION, false);
    }

    /** @return array<string,string> */
    public static function workstation_types(): array {
        return apply_filters('elev8_os_production_workstation_types', [
            'torch_bench' => __('Torch bench', 'elev8-os'),
            'kiln' => __('Kiln', 'elev8-os'),
            'annealer' => __('Annealer', 'elev8-os'),
            'cold_work' => __('Cold-working station', 'elev8-os'),
            'packing' => __('Packing / fulfillment', 'elev8-os'),
            'quality' => __('Quality-review station', 'elev8-os'),
            'other' => __('Other workstation', 'elev8-os'),
        ]);
    }

    /** @return array<string,string> */
    public static function cycle_statuses(): array {
        return [
            'planned' => __('Planned', 'elev8-os'),
            'loading' => __('Loading', 'elev8-os'),
            'running' => __('Running', 'elev8-os'),
            'cooling' => __('Cooling', 'elev8-os'),
            'complete' => __('Complete', 'elev8-os'),
            'cancelled' => __('Cancelled', 'elev8-os'),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public static function workstations(bool $active_only = true): array {
        global $wpdb;
        $t = self::tables();
        $where = $active_only ? 'WHERE active=1' : '';
        return $wpdb->get_results("SELECT * FROM {$t['workstations']} {$where} ORDER BY active DESC,name ASC", ARRAY_A) ?: [];
    }

    public static function save_workstation(array $data): int|WP_Error {
        if (!class_exists('Elev8_OS_Production_Workspace_Service') || !Elev8_OS_Production_Workspace_Service::can_view()) {
            return new WP_Error('production_workstation_access', __('You do not have permission to manage workstations.', 'elev8-os'));
        }
        global $wpdb;
        $t = self::tables();
        $id = absint($data['workstation_id'] ?? 0);
        $name = sanitize_text_field($data['name'] ?? '');
        if ($name === '') { return new WP_Error('production_workstation_name', __('Enter a workstation name.', 'elev8-os')); }
        $type = sanitize_key($data['workstation_type'] ?? 'other');
        if (!isset(self::workstation_types()[$type])) { $type = 'other'; }
        $now = current_time('mysql');
        $row = [
            'name' => $name,
            'workstation_type' => $type,
            'organization_unit_id' => absint($data['organization_unit_id'] ?? 0),
            'asset_id' => absint($data['asset_id'] ?? 0),
            'capacity_units' => max(0.01, (float)($data['capacity_units'] ?? 1)),
            'active' => empty($data['active']) ? 0 : 1,
            'notes' => sanitize_textarea_field($data['notes'] ?? ''),
            'updated_at' => $now,
        ];
        if ($id) {
            $ok = $wpdb->update($t['workstations'], $row, ['id' => $id]);
            return $ok === false ? new WP_Error('production_workstation_save', __('The workstation could not be updated.', 'elev8-os')) : $id;
        }
        $row['created_by'] = get_current_user_id();
        $row['created_at'] = $now;
        $ok = $wpdb->insert($t['workstations'], $row);
        return $ok ? (int)$wpdb->insert_id : new WP_Error('production_workstation_save', __('The workstation could not be created.', 'elev8-os'));
    }

    /** @return array<int,array<string,mixed>> */
    public static function cycles(array $args = []): array {
        global $wpdb;
        $t = self::tables();
        $where = ['1=1']; $params = [];
        if (!empty($args['workstation_id'])) { $where[] = 'c.workstation_id=%d'; $params[] = absint($args['workstation_id']); }
        if (!empty($args['status'])) { $where[] = 'c.status=%s'; $params[] = sanitize_key($args['status']); }
        $sql = "SELECT c.*,w.name workstation_name,w.workstation_type FROM {$t['cycles']} c LEFT JOIN {$t['workstations']} w ON w.id=c.workstation_id WHERE " . implode(' AND ', $where) . " ORDER BY c.scheduled_start IS NULL,c.scheduled_start ASC,c.id DESC LIMIT 100";
        if ($params) { $sql = $wpdb->prepare($sql, $params); }
        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    public static function save_cycle(array $data): int|WP_Error {
        if (!class_exists('Elev8_OS_Production_Workspace_Service') || !Elev8_OS_Production_Workspace_Service::can_view()) {
            return new WP_Error('production_cycle_access', __('You do not have permission to manage production cycles.', 'elev8-os'));
        }
        global $wpdb;
        $t = self::tables();
        $workstation_id = absint($data['workstation_id'] ?? 0);
        if (!$workstation_id) { return new WP_Error('production_cycle_workstation', __('Choose a workstation.', 'elev8-os')); }
        $statuses = self::cycle_statuses();
        $status = sanitize_key($data['status'] ?? 'planned');
        if (!isset($statuses[$status])) { $status = 'planned'; }
        $now = current_time('mysql');
        $row = [
            'workstation_id' => $workstation_id,
            'cycle_type' => sanitize_key($data['cycle_type'] ?? 'production'),
            'scheduled_start' => self::datetime_or_null($data['scheduled_start'] ?? ''),
            'scheduled_end' => self::datetime_or_null($data['scheduled_end'] ?? ''),
            'status' => $status,
            'capacity_units' => max(0.01, (float)($data['capacity_units'] ?? 1)),
            'notes' => sanitize_textarea_field($data['notes'] ?? ''),
            'created_by' => get_current_user_id(),
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $ok = $wpdb->insert($t['cycles'], $row);
        return $ok ? (int)$wpdb->insert_id : new WP_Error('production_cycle_save', __('The production cycle could not be created.', 'elev8-os'));
    }

    /** @return array<string,mixed>|null */
    public static function allocation_for_job(int $job_id): ?array {
        global $wpdb;
        $t = self::tables();
        $row = $wpdb->get_row($wpdb->prepare("SELECT a.*,w.name workstation_name,w.workstation_type,c.cycle_type,c.status cycle_status,c.scheduled_start cycle_start,c.scheduled_end cycle_end FROM {$t['allocations']} a LEFT JOIN {$t['workstations']} w ON w.id=a.workstation_id LEFT JOIN {$t['cycles']} c ON c.id=a.cycle_id WHERE a.job_id=%d", $job_id), ARRAY_A);
        return $row ?: null;
    }

    public static function assign_job(array $data): bool|WP_Error {
        if (!class_exists('Elev8_OS_Production_Workspace_Service') || !Elev8_OS_Production_Workspace_Service::can_view()) {
            return new WP_Error('production_allocation_access', __('You do not have permission to coordinate production.', 'elev8-os'));
        }
        global $wpdb;
        $t = self::tables();
        $job_id = absint($data['job_id'] ?? 0);
        if (!$job_id || !class_exists('Elev8_OS_Glass_Operations_Service') || !Elev8_OS_Glass_Operations_Service::job($job_id)) {
            return new WP_Error('production_allocation_job', __('Production job not found.', 'elev8-os'));
        }
        $now = current_time('mysql');
        $row = [
            'job_id' => $job_id,
            'workstation_id' => absint($data['workstation_id'] ?? 0),
            'cycle_id' => absint($data['cycle_id'] ?? 0),
            'allocation_status' => sanitize_key($data['allocation_status'] ?? 'planned'),
            'planned_start' => self::datetime_or_null($data['planned_start'] ?? ''),
            'planned_end' => self::datetime_or_null($data['planned_end'] ?? ''),
            'notes' => sanitize_textarea_field($data['notes'] ?? ''),
            'updated_at' => $now,
        ];
        $existing = self::allocation_for_job($job_id);
        if ($existing) {
            $ok = $wpdb->update($t['allocations'], $row, ['job_id' => $job_id]);
        } else {
            $row['created_by'] = get_current_user_id();
            $row['created_at'] = $now;
            $ok = $wpdb->insert($t['allocations'], $row);
        }
        return $ok === false ? new WP_Error('production_allocation_save', __('The production allocation could not be saved.', 'elev8-os')) : true;
    }

    /** @return array<string,mixed> */
    public static function capacity_snapshot(): array {
        $cycles = self::cycles();
        $today = current_time('Y-m-d');
        $active = array_values(array_filter($cycles, static function(array $cycle) use ($today): bool {
            return !in_array($cycle['status'], ['complete','cancelled'], true)
                && (empty($cycle['scheduled_end']) || substr((string)$cycle['scheduled_end'], 0, 10) >= $today);
        }));
        return [
            'active_cycles' => count($active),
            'running_cycles' => count(array_filter($active, static fn(array $cycle): bool => $cycle['status'] === 'running')),
            'cooling_cycles' => count(array_filter($active, static fn(array $cycle): bool => $cycle['status'] === 'cooling')),
            'cycles' => $active,
        ];
    }

    private static function datetime_or_null(string $value): ?string {
        $value = sanitize_text_field($value);
        if ($value === '') { return null; }
        $timestamp = strtotime($value);
        return $timestamp ? wp_date('Y-m-d H:i:s', $timestamp, wp_timezone()) : null;
    }
}

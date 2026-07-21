<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Repair and memorial workflow data layered on top of Glass Operations jobs.
 * Glass jobs remain the source of truth for assignment, production lines, pay and QC.
 */
final class Elev8_OS_Repair_Memorial_Service {
    const DB_VERSION = '1.0.0';
    const OPTION_DB_VERSION = 'elev8_os_repair_memorial_db_version';

    public static function init(): void { add_action('init', [__CLASS__, 'maybe_install'], 8); }
    public static function activate(): void { self::install(); }

    public static function tables(): array {
        global $wpdb;
        return [
            'cases' => $wpdb->prefix . 'elev8_glass_cases',
            'custody' => $wpdb->prefix . 'elev8_glass_custody_events',
            'updates' => $wpdb->prefix . 'elev8_glass_customer_updates',
        ];
    }

    public static function maybe_install(): void {
        if (get_option(self::OPTION_DB_VERSION) !== self::DB_VERSION) { self::install(); }
    }

    private static function install(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $t = self::tables();
        $c = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE {$t['cases']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            job_id bigint(20) unsigned NOT NULL,
            case_type varchar(30) NOT NULL DEFAULT 'repair',
            case_status varchar(50) NOT NULL DEFAULT 'received',
            receiving_location varchar(190) NOT NULL DEFAULT '',
            received_at datetime NULL,
            received_by bigint(20) unsigned NOT NULL DEFAULT 0,
            piece_description text NOT NULL,
            damage_description text NOT NULL,
            requested_work text NOT NULL,
            repairability varchar(30) NOT NULL DEFAULT 'unknown',
            risk_notice text NOT NULL,
            quote_amount decimal(12,2) NOT NULL DEFAULT 0.00,
            quote_status varchar(30) NOT NULL DEFAULT 'not_required',
            approval_deadline date NULL,
            payment_status varchar(30) NOT NULL DEFAULT 'unknown',
            ashes_amount_received decimal(12,4) NOT NULL DEFAULT 0.0000,
            ashes_amount_used decimal(12,4) NOT NULL DEFAULT 0.0000,
            ashes_amount_returned decimal(12,4) NOT NULL DEFAULT 0.0000,
            ashes_unit varchar(30) NOT NULL DEFAULT 'teaspoon',
            ashes_estimated tinyint(1) NOT NULL DEFAULT 1,
            reconciliation_confirmed tinyint(1) NOT NULL DEFAULT 0,
            storage_location varchar(190) NOT NULL DEFAULT '',
            container_description text NOT NULL,
            final_recipient varchar(190) NOT NULL DEFAULT '',
            release_method varchar(50) NOT NULL DEFAULT '',
            intake_photo_ids longtext NOT NULL,
            created_by bigint(20) unsigned NOT NULL DEFAULT 0,
            updated_by bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY job_id (job_id),
            KEY type_status (case_type,case_status),
            KEY quote_status (quote_status),
            KEY reconciliation (reconciliation_confirmed)
        ) {$c};");
        dbDelta("CREATE TABLE {$t['custody']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            job_id bigint(20) unsigned NOT NULL,
            event_type varchar(60) NOT NULL,
            event_label varchar(190) NOT NULL,
            event_location varchar(190) NOT NULL DEFAULT '',
            notes text NOT NULL,
            attachment_id bigint(20) unsigned NOT NULL DEFAULT 0,
            created_by bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY job_time (job_id,created_at),
            KEY event_type (event_type)
        ) {$c};");
        dbDelta("CREATE TABLE {$t['updates']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            job_id bigint(20) unsigned NOT NULL,
            template_key varchar(60) NOT NULL DEFAULT '',
            subject varchar(190) NOT NULL DEFAULT '',
            message text NOT NULL,
            status varchar(30) NOT NULL DEFAULT 'draft',
            created_by bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY job_status (job_id,status)
        ) {$c};");
        update_option(self::OPTION_DB_VERSION, self::DB_VERSION, false);
    }

    public static function case_statuses(string $type): array {
        if ($type === 'memorial') {
            return [
                'received' => 'Received', 'intake_verified' => 'Intake Verified', 'waiting_ashes' => 'Waiting on Ashes',
                'stored' => 'Stored Securely', 'assigned' => 'Assigned', 'in_production' => 'In Production',
                'quality_control' => 'Quality Control', 'reconciliation_required' => 'Reconciliation Required',
                'ready_for_release' => 'Ready for Release', 'shipped' => 'Shipped', 'completed' => 'Completed',
            ];
        }
        return [
            'received' => 'Received', 'needs_evaluation' => 'Needs Evaluation', 'quote_required' => 'Quote Required',
            'waiting_customer' => 'Waiting for Customer', 'approved' => 'Approved', 'not_repairable' => 'Not Repairable',
            'assigned' => 'Assigned', 'in_repair' => 'In Repair', 'quality_control' => 'Quality Control',
            'ready_for_pickup' => 'Ready for Pickup', 'ready_to_ship' => 'Ready to Ship',
            'completed' => 'Completed', 'returned_unrepaired' => 'Returned Unrepaired',
        ];
    }

    public static function case_for_job(int $job_id): ?array {
        global $wpdb; $t = self::tables();
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['cases']} WHERE job_id=%d", $job_id), ARRAY_A);
        if (!$row) { return null; }
        $row['intake_photo_ids'] = array_values(array_filter(array_map('absint', json_decode((string)$row['intake_photo_ids'], true) ?: [])));
        return $row;
    }

    public static function save_case(int $job_id, array $data, array $files = []): bool|WP_Error {
        global $wpdb; $t = self::tables();
        $job = Elev8_OS_Glass_Operations_Service::job($job_id);
        if (!$job) { return new WP_Error('glass_case_job', 'Production job not found.'); }
        $type = sanitize_key($data['case_type'] ?? ($job['job_type'] === 'cremation' ? 'memorial' : 'repair'));
        if (!in_array($type, ['repair','memorial'], true)) { return new WP_Error('glass_case_type', 'Choose repair or memorial.'); }
        $existing = self::case_for_job($job_id);
        $photo_ids = $existing['intake_photo_ids'] ?? [];
        $photo_ids = array_values(array_unique(array_merge($photo_ids, self::handle_uploads($files))));
        $status = sanitize_key($data['case_status'] ?? 'received');
        if (!array_key_exists($status, self::case_statuses($type))) { $status = 'received'; }
        $row = [
            'job_id' => $job_id,
            'case_type' => $type,
            'case_status' => $status,
            'receiving_location' => sanitize_text_field($data['receiving_location'] ?? ''),
            'received_at' => self::datetime_or_null($data['received_at'] ?? ''),
            'received_by' => absint($data['received_by'] ?? get_current_user_id()),
            'piece_description' => sanitize_textarea_field($data['piece_description'] ?? ''),
            'damage_description' => sanitize_textarea_field($data['damage_description'] ?? ''),
            'requested_work' => sanitize_textarea_field($data['requested_work'] ?? ''),
            'repairability' => sanitize_key($data['repairability'] ?? 'unknown'),
            'risk_notice' => sanitize_textarea_field($data['risk_notice'] ?? ''),
            'quote_amount' => max(0, (float)($data['quote_amount'] ?? 0)),
            'quote_status' => sanitize_key($data['quote_status'] ?? 'not_required'),
            'approval_deadline' => self::date_or_null($data['approval_deadline'] ?? ''),
            'payment_status' => sanitize_key($data['payment_status'] ?? 'unknown'),
            'ashes_amount_received' => max(0, (float)($data['ashes_amount_received'] ?? 0)),
            'ashes_amount_used' => max(0, (float)($data['ashes_amount_used'] ?? 0)),
            'ashes_amount_returned' => max(0, (float)($data['ashes_amount_returned'] ?? 0)),
            'ashes_unit' => sanitize_key($data['ashes_unit'] ?? 'teaspoon'),
            'ashes_estimated' => !empty($data['ashes_estimated']) ? 1 : 0,
            'reconciliation_confirmed' => !empty($data['reconciliation_confirmed']) ? 1 : 0,
            'storage_location' => sanitize_text_field($data['storage_location'] ?? ''),
            'container_description' => sanitize_textarea_field($data['container_description'] ?? ''),
            'final_recipient' => sanitize_text_field($data['final_recipient'] ?? ''),
            'release_method' => sanitize_key($data['release_method'] ?? ''),
            'intake_photo_ids' => wp_json_encode($photo_ids),
            'updated_by' => get_current_user_id(),
            'updated_at' => current_time('mysql'),
        ];
        if ($type === 'memorial' && $status === 'completed' && empty($row['reconciliation_confirmed'])) {
            return new WP_Error('glass_case_reconcile', 'Confirm remaining ashes reconciliation before completing this memorial case.');
        }
        if ($existing) {
            $ok = false !== $wpdb->update($t['cases'], $row, ['job_id' => $job_id]);
        } else {
            $row['created_by'] = get_current_user_id(); $row['created_at'] = current_time('mysql');
            $ok = (bool)$wpdb->insert($t['cases'], $row);
        }
        if ($ok) {
            self::record_activity($job_id, 'glass_case_updated', ucfirst($type) . ' case updated', 'Case status: ' . ($status ?: 'received') . '.');
        }
        return $ok;
    }

    public static function add_custody_event(int $job_id, array $data, array $files = []): int|WP_Error {
        global $wpdb; $t = self::tables();
        $case = self::case_for_job($job_id);
        if (!$case || $case['case_type'] !== 'memorial') { return new WP_Error('glass_custody_case', 'Custody events require a memorial case.'); }
        $uploads = self::handle_uploads($files);
        $label = sanitize_text_field($data['event_label'] ?? '');
        if ($label === '') { return new WP_Error('glass_custody_label', 'Enter a custody event.'); }
        $row = [
            'job_id' => $job_id,
            'event_type' => sanitize_key($data['event_type'] ?? 'note'),
            'event_label' => $label,
            'event_location' => sanitize_text_field($data['event_location'] ?? ''),
            'notes' => sanitize_textarea_field($data['notes'] ?? ''),
            'attachment_id' => (int)($uploads[0] ?? 0),
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql'),
        ];
        if (!$wpdb->insert($t['custody'], $row)) { return new WP_Error('glass_custody_save', 'Custody event could not be recorded.'); }
        self::record_activity($job_id, 'glass_custody_event', $label, $row['notes']);
        return (int)$wpdb->insert_id;
    }

    public static function custody_events(int $job_id): array {
        global $wpdb; $t = self::tables();
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$t['custody']} WHERE job_id=%d ORDER BY created_at DESC,id DESC", $job_id), ARRAY_A) ?: [];
    }

    public static function templates(array $job, ?array $case): array {
        $name = $job['customer_name'] ?: 'there';
        $type = $case['case_type'] ?? ($job['job_type'] === 'cremation' ? 'memorial' : 'repair');
        $base = [
            'received' => ['We received your item', "Hello {$name}, we safely received your {$type} order and created production job #{$job['id']}."],
            'started' => ['Production has started', "Hello {$name}, work has started on production job #{$job['id']}."],
            'qc' => ['Quality control', "Hello {$name}, production job #{$job['id']} is now in quality control."],
            'ready' => ['Ready', "Hello {$name}, production job #{$job['id']} is complete and ready for the documented pickup or shipping method."],
        ];
        if ($type === 'repair') {
            $base['quote'] = ['Repair quote ready', "Hello {$name}, the evaluation for repair job #{$job['id']} is complete. Please contact us to review and approve the quote."];
        } else {
            $base['ashes'] = ['Memorial remains received safely', "Hello {$name}, we safely received the remains for memorial job #{$job['id']} and documented the intake in our custody record."];
        }
        return $base;
    }

    public static function attention_items(): array {
        global $wpdb; $t = self::tables(); $today = current_time('Y-m-d');
        $rows = $wpdb->get_results("SELECT c.*,j.product_name,j.customer_name,j.due_date,j.status AS job_status FROM {$t['cases']} c INNER JOIN " . Elev8_OS_Glass_Operations_Service::tables()['jobs'] . " j ON j.id=c.job_id WHERE j.status NOT IN ('completed','cancelled') ORDER BY c.updated_at ASC", ARRAY_A) ?: [];
        $items = [];
        foreach ($rows as $row) {
            $title = $row['product_name'] ?: ('Job #' . $row['job_id']);
            if ($row['case_type'] === 'repair' && in_array($row['case_status'], ['needs_evaluation','quote_required'], true)) {
                $items[] = ['severity'=>'high','kind'=>'job','job_id'=>(int)$row['job_id'],'title'=>$title . ' needs repair evaluation','detail'=>'Repair intake or quote is incomplete.','action'=>'Open repair'];
            }
            if ($row['case_type'] === 'repair' && $row['quote_status'] === 'waiting_customer' && !empty($row['approval_deadline']) && $row['approval_deadline'] < $today) {
                $items[] = ['severity'=>'high','kind'=>'job','job_id'=>(int)$row['job_id'],'title'=>$title . ' customer approval is overdue','detail'=>'Approval deadline was ' . $row['approval_deadline'] . '.','action'=>'Follow up'];
            }
            if ($row['case_type'] === 'memorial' && (empty($row['received_at']) || empty($row['storage_location']) || empty($row['container_description']))) {
                $items[] = ['severity'=>'critical','kind'=>'job','job_id'=>(int)$row['job_id'],'title'=>$title . ' has incomplete custody intake','detail'=>'Received date, container description and secure storage location are required.','action'=>'Complete custody'];
            }
            if ($row['case_type'] === 'memorial' && in_array($row['case_status'], ['reconciliation_required','ready_for_release'], true) && empty($row['reconciliation_confirmed'])) {
                $items[] = ['severity'=>'critical','kind'=>'job','job_id'=>(int)$row['job_id'],'title'=>$title . ' needs ashes reconciliation','detail'=>'Confirm amount received, used and returned before release.','action'=>'Reconcile'];
            }
        }
        return $items;
    }

    private static function handle_uploads(array $files): array {
        if (empty($files['name'])) { return []; }
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $ids = [];
        $names = is_array($files['name']) ? $files['name'] : [$files['name']];
        foreach ($names as $i => $name) {
            if (!$name) { continue; }
            $_FILES['elev8_case_upload'] = [
                'name' => $name,
                'type' => is_array($files['type']) ? ($files['type'][$i] ?? '') : ($files['type'] ?? ''),
                'tmp_name' => is_array($files['tmp_name']) ? ($files['tmp_name'][$i] ?? '') : ($files['tmp_name'] ?? ''),
                'error' => is_array($files['error']) ? ($files['error'][$i] ?? 0) : ($files['error'] ?? 0),
                'size' => is_array($files['size']) ? ($files['size'][$i] ?? 0) : ($files['size'] ?? 0),
            ];
            $id = media_handle_upload('elev8_case_upload', 0);
            if (!is_wp_error($id)) { $ids[] = (int)$id; }
        }
        unset($_FILES['elev8_case_upload']);
        return $ids;
    }

    private static function record_activity(int $job_id, string $type, string $label, string $details = ''): void {
        if (!class_exists('Elev8_OS_Activity_Service')) { return; }
        Elev8_OS_Activity_Service::record(['type'=>$type,'label'=>$label,'details'=>$details,'object_id'=>$job_id,'object_type'=>'glass_job','source'=>'repair_memorial']);
    }
    private static function date_or_null(string $value): ?string { $value = sanitize_text_field($value); return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null; }
    private static function datetime_or_null(string $value): ?string { $value = sanitize_text_field($value); if (!$value) { return null; } $ts = strtotime($value); return $ts ? wp_date('Y-m-d H:i:s', $ts) : null; }
}

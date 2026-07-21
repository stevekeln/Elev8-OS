<?php
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Glass_Operations_Service {
    const DB_VERSION = '2.1.0';
    const OPTION_DB_VERSION = 'elev8_os_glass_ops_db_version';

    public static function init(): void {
        add_action('init', [__CLASS__, 'maybe_install'], 7);
        add_action('init', [__CLASS__, 'ensure_foundation_blowers'], 12);
    }

    public static function activate(): void { self::install(); }

    public static function tables(): array {
        global $wpdb;
        return [
            'jobs' => $wpdb->prefix . 'elev8_glass_jobs',
            'entries' => $wpdb->prefix . 'elev8_glass_work_entries',
            'pay_periods' => $wpdb->prefix . 'elev8_glass_pay_periods',
            'job_lines' => $wpdb->prefix . 'elev8_glass_job_lines',
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
        dbDelta("CREATE TABLE {$t['jobs']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            job_type varchar(30) NOT NULL DEFAULT 'production',
            order_number varchar(100) NOT NULL DEFAULT '',
            customer_name varchar(190) NOT NULL DEFAULT '',
            customer_email varchar(190) NOT NULL DEFAULT '',
            customer_phone varchar(80) NOT NULL DEFAULT '',
            memorial_name varchar(190) NOT NULL DEFAULT '',
            product_name varchar(190) NOT NULL DEFAULT '',
            quantity int(10) unsigned NOT NULL DEFAULT 1,
            colors varchar(255) NOT NULL DEFAULT '',
            engraving varchar(255) NOT NULL DEFAULT '',
            ashes_status varchar(40) NOT NULL DEFAULT 'not_applicable',
            return_instructions text NOT NULL,
            special_notes text NOT NULL,
            status varchar(40) NOT NULL DEFAULT 'new',
            priority varchar(20) NOT NULL DEFAULT 'normal',
            due_date date NULL,
            assigned_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            source varchar(40) NOT NULL DEFAULT 'manual',
            source_id bigint(20) unsigned NOT NULL DEFAULT 0,
            created_by bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY status_due (status,due_date),
            KEY assigned_user (assigned_user_id),
            KEY job_type (job_type),
            KEY source_record (source,source_id)
        ) {$c};");
        dbDelta("CREATE TABLE {$t['entries']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            job_id bigint(20) unsigned NOT NULL DEFAULT 0,
            job_line_id bigint(20) unsigned NOT NULL DEFAULT 0,
            production_product_id bigint(20) unsigned NOT NULL DEFAULT 0,
            blower_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            item_name varchar(190) NOT NULL DEFAULT '',
            quantity decimal(10,2) NOT NULL DEFAULT 1.00,
            pay_method varchar(30) NOT NULL DEFAULT 'piece_rate',
            rate decimal(12,2) NOT NULL DEFAULT 0.00,
            minutes decimal(10,2) NOT NULL DEFAULT 0.00,
            bonus decimal(12,2) NOT NULL DEFAULT 0.00,
            adjustment decimal(12,2) NOT NULL DEFAULT 0.00,
            total decimal(12,2) NOT NULL DEFAULT 0.00,
            notes text NOT NULL,
            snapshot_json longtext NOT NULL,
            work_date date NOT NULL,
            approval_status varchar(20) NOT NULL DEFAULT 'pending',
            pay_period_id bigint(20) unsigned NOT NULL DEFAULT 0,
            created_by bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY blower_period (blower_user_id,pay_period_id),
            KEY job_id (job_id),
            KEY job_line_id (job_line_id),
            KEY production_product_id (production_product_id),
            KEY approval_status (approval_status)
        ) {$c};");

        dbDelta("CREATE TABLE {$t['job_lines']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            job_id bigint(20) unsigned NOT NULL DEFAULT 0,
            production_product_id bigint(20) unsigned NOT NULL DEFAULT 0,
            product_version int(10) unsigned NOT NULL DEFAULT 0,
            item_name varchar(190) NOT NULL DEFAULT '',
            quantity decimal(10,2) NOT NULL DEFAULT 1.00,
            compensation_method varchar(30) NOT NULL DEFAULT 'hourly',
            piecework_rate decimal(12,2) NOT NULL DEFAULT 0.00,
            piecework_unit varchar(30) NOT NULL DEFAULT 'piece',
            estimated_minutes decimal(10,2) NOT NULL DEFAULT 0.00,
            material_cost decimal(12,2) NOT NULL DEFAULT 0.00,
            snapshot_json longtext NOT NULL,
            status varchar(30) NOT NULL DEFAULT 'planned',
            quantity_completed decimal(10,2) NOT NULL DEFAULT 0.00,
            quantity_rejected decimal(10,2) NOT NULL DEFAULT 0.00,
            actual_minutes decimal(10,2) NOT NULL DEFAULT 0.00,
            qc_status varchar(30) NOT NULL DEFAULT 'not_reviewed',
            manager_approved tinyint(1) NOT NULL DEFAULT 0,
            payroll_approved tinyint(1) NOT NULL DEFAULT 0,
            created_by bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY job_id (job_id),
            KEY production_product_id (production_product_id),
            KEY status (status)
        ) {$c};");

        dbDelta("CREATE TABLE {$t['pay_periods']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            period_start date NOT NULL,
            period_end date NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'open',
            closed_by bigint(20) unsigned NOT NULL DEFAULT 0,
            closed_at datetime NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY status_dates (status,period_start,period_end)
        ) {$c};");
        update_option(self::OPTION_DB_VERSION, self::DB_VERSION, false);
    }

    public static function save_job(array $data): int|WP_Error {
        global $wpdb; $t = self::tables(); $now = current_time('mysql');
        $row = [
            'job_type'=>in_array(($data['job_type']??''),['production','cremation','repair','memorial'],true)?$data['job_type']:'production',
            'order_number'=>sanitize_text_field($data['order_number']??''),
            'customer_name'=>sanitize_text_field($data['customer_name']??''),
            'customer_email'=>sanitize_email($data['customer_email']??''),
            'customer_phone'=>sanitize_text_field($data['customer_phone']??''),
            'memorial_name'=>sanitize_text_field($data['memorial_name']??''),
            'product_name'=>sanitize_text_field($data['product_name']??''),
            'quantity'=>max(1,absint($data['quantity']??1)),
            'colors'=>sanitize_text_field($data['colors']??''),
            'engraving'=>sanitize_text_field($data['engraving']??''),
            'ashes_status'=>sanitize_key($data['ashes_status']??'not_applicable'),
            'return_instructions'=>sanitize_textarea_field($data['return_instructions']??''),
            'special_notes'=>sanitize_textarea_field($data['special_notes']??''),
            'status'=>sanitize_key($data['status']??'new'),
            'priority'=>sanitize_key($data['priority']??'normal'),
            'due_date'=>self::date_or_null($data['due_date']??''),
            'assigned_user_id'=>absint($data['assigned_user_id']??0),
            'source'=>sanitize_key($data['source']??'manual'),
            'source_id'=>absint($data['source_id']??0),
            'created_by'=>get_current_user_id(), 'created_at'=>$now, 'updated_at'=>$now,
        ];
        $ok=$wpdb->insert($t['jobs'],$row);
        return $ok ? (int)$wpdb->insert_id : new WP_Error('glass_job_save','The glass job could not be saved.');
    }

    public static function workflow_statuses(): array {
        return [
            'new' => 'New',
            'waiting_customer_info' => 'Waiting on Customer',
            'waiting_ashes' => 'Waiting on Ashes',
            'ready_for_production' => 'Ready',
            'assigned' => 'Assigned',
            'in_production' => 'In Production',
            'waiting' => 'Waiting',
            'quality_control' => 'QC',
            'ready_for_pickup' => 'Ready for Pickup',
            'ready_to_ship' => 'Ready to Ship',
            'completed' => 'Complete',
            'cancelled' => 'Cancelled',
        ];
    }

    public static function board_jobs(array $args = []): array {
        global $wpdb;
        $t = self::tables();
        $where = ["j.status <> 'cancelled'"];
        $params = [];
        if (!empty($args['assigned_user_id'])) { $where[] = 'j.assigned_user_id=%d'; $params[] = absint($args['assigned_user_id']); }
        if (!empty($args['source'])) { $where[] = 'j.source=%s'; $params[] = sanitize_key($args['source']); }
        if (!empty($args['priority'])) { $where[] = 'j.priority=%s'; $params[] = sanitize_key($args['priority']); }
        if (!empty($args['overdue'])) { $where[] = "j.status NOT IN ('completed','cancelled') AND j.due_date IS NOT NULL AND j.due_date < %s"; $params[] = current_time('Y-m-d'); }
        if (!empty($args['search'])) {
            $like = '%' . $wpdb->esc_like(sanitize_text_field($args['search'])) . '%';
            $where[] = '(j.product_name LIKE %s OR j.customer_name LIKE %s OR j.order_number LIKE %s OR j.special_notes LIKE %s)';
            array_push($params, $like, $like, $like, $like);
        }
        $sql = "SELECT j.*, COUNT(l.id) AS line_count, COALESCE(SUM(l.quantity),0) AS planned_units, COALESCE(SUM(l.quantity_completed),0) AS completed_units
                FROM {$t['jobs']} j
                LEFT JOIN {$t['job_lines']} l ON l.job_id=j.id
                WHERE " . implode(' AND ', $where) . "
                GROUP BY j.id
                ORDER BY FIELD(j.priority,'urgent','high','normal','low'), j.due_date IS NULL, j.due_date ASC, j.id DESC";
        if ($params) { $sql = $wpdb->prepare($sql, $params); }
        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    public static function board_workload(array $jobs, array $workers): array {
        $out = [0 => ['label' => 'Unassigned', 'open' => 0, 'overdue' => 0, 'due_today' => 0]];
        foreach ($workers as $worker) {
            $out[(int) $worker->ID] = ['label' => $worker->display_name, 'open' => 0, 'overdue' => 0, 'due_today' => 0];
        }
        $today = current_time('Y-m-d');
        foreach ($jobs as $job) {
            if (in_array($job['status'], ['completed','cancelled'], true)) { continue; }
            $uid = (int) $job['assigned_user_id'];
            if (!isset($out[$uid])) { $out[$uid] = ['label' => 'Other user', 'open' => 0, 'overdue' => 0, 'due_today' => 0]; }
            $out[$uid]['open']++;
            if (!empty($job['due_date']) && $job['due_date'] < $today) { $out[$uid]['overdue']++; }
            if (!empty($job['due_date']) && $job['due_date'] === $today) { $out[$uid]['due_today']++; }
        }
        return $out;
    }

    public static function move_board_job(int $job_id, string $status, int $assigned_user_id): bool|WP_Error {
        $allowed = array_keys(self::workflow_statuses());
        if (!in_array($status, $allowed, true)) { return new WP_Error('glass_board_status', 'Invalid production status.'); }
        if ($assigned_user_id > 0) {
            $valid = false;
            foreach (self::glass_workers() as $worker) { if ((int) $worker->ID === $assigned_user_id) { $valid = true; break; } }
            if (!$valid) { return new WP_Error('glass_board_blower', 'Choose an active glassblower.'); }
        }
        $ok = self::update_job($job_id, ['status' => $status, 'assigned_user_id' => $assigned_user_id]);
        if (!$ok) { return new WP_Error('glass_board_update', 'The production job could not be updated.'); }
        if (class_exists('Elev8_OS_Activity_Service')) {
            Elev8_OS_Activity_Service::record([
                'type' => 'glass_job_board_updated',
                'label' => 'Production job moved on board',
                'details' => 'Status changed to ' . ucwords(str_replace('_', ' ', $status)) . '.',
                'object_id' => $job_id,
                'object_type' => 'glass_job',
                'source' => 'glass_production_board',
                'metadata' => [
                    'status' => $status,
                    'assigned_user_id' => $assigned_user_id,
                ],
            ]);
        }
        return true;
    }

    public static function update_job(int $id,array $data): bool {
        global $wpdb; $t=self::tables(); $allowed=[];
        foreach(['status','priority','ashes_status'] as $k) if(isset($data[$k])) $allowed[$k]=sanitize_key($data[$k]);
        if(isset($data['assigned_user_id'])) $allowed['assigned_user_id']=absint($data['assigned_user_id']);
        if(isset($data['due_date'])) $allowed['due_date']=self::date_or_null($data['due_date']);
        $allowed['updated_at']=current_time('mysql');
        return false !== $wpdb->update($t['jobs'],$allowed,['id'=>$id]);
    }

    public static function jobs(array $args=[]): array {
        global $wpdb; $t=self::tables(); $where=['1=1']; $params=[];
        if(!empty($args['job_type'])){$where[]='job_type=%s';$params[]=sanitize_key($args['job_type']);}
        if(!empty($args['status'])){$where[]='status=%s';$params[]=sanitize_key($args['status']);}
        if(!empty($args['assigned_user_id'])){$where[]='assigned_user_id=%d';$params[]=absint($args['assigned_user_id']);}
        $limit=max(1,min(200,absint($args['limit']??100)));
        $sql="SELECT * FROM {$t['jobs']} WHERE ".implode(' AND ',$where)." ORDER BY FIELD(priority,'urgent','high','normal','low'), due_date IS NULL, due_date ASC, id DESC LIMIT {$limit}";
        if($params)$sql=$wpdb->prepare($sql,$params);
        return $wpdb->get_results($sql,ARRAY_A)?:[];
    }

    public static function job(int $id): ?array { global $wpdb;$t=self::tables();$r=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['jobs']} WHERE id=%d",$id),ARRAY_A);return $r?:null; }

    public static function save_entry(array $data): int|WP_Error {
        global $wpdb; $t = self::tables();
        $blower_id = absint($data['blower_user_id'] ?? 0);
        if (!$blower_id) { return new WP_Error('glass_entry_user', 'Choose a blower.'); }
        $line_id = absint($data['job_line_id'] ?? 0);
        $line = $line_id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['job_lines']} WHERE id=%d", $line_id), ARRAY_A) : null;
        $method = sanitize_key($data['pay_method'] ?? ($line['compensation_method'] ?? 'piece_rate'));
        if ($method === 'piecework') { $method = 'piece_rate'; }
        $qty = max(0, (float)($data['quantity'] ?? ($line['quantity_completed'] ?? 0)));
        $minutes = max(0, (float)($data['minutes'] ?? ($line['actual_minutes'] ?? 0)));
        $rate = max(0, (float)($data['rate'] ?? 0));
        $profile = class_exists('Elev8_OS_Production_Catalog_Service') ? Elev8_OS_Production_Catalog_Service::compensation_profile($blower_id) : null;
        if ($rate <= 0 && $method === 'hourly') { $rate = (float)($profile['hourly_rate'] ?? 0); }
        if ($rate <= 0 && $method === 'piece_rate' && $line) { $rate = (float)($line['piecework_rate'] ?? 0); }
        if ($rate <= 0) { return new WP_Error('glass_entry_rate', 'No valid hourly or piecework rate is available.'); }
        $bonus = (float)($data['bonus'] ?? 0); $adj = (float)($data['adjustment'] ?? 0);
        $base = $method === 'hourly' ? ($minutes / 60) * $rate : $qty * $rate;
        $total = round($base + $bonus + $adj, 2);
        $snapshot = ['profile' => $profile, 'job_line' => $line, 'calculated_at' => current_time('mysql')];
        $row = [
            'job_id' => absint($data['job_id'] ?? 0),
            'job_line_id' => $line_id,
            'production_product_id' => absint($line['production_product_id'] ?? 0),
            'blower_user_id' => $blower_id,
            'item_name' => sanitize_text_field($data['item_name'] ?? ($line['item_name'] ?? '')),
            'quantity' => $qty,
            'pay_method' => $method,
            'rate' => $rate,
            'minutes' => $minutes,
            'bonus' => $bonus,
            'adjustment' => $adj,
            'total' => $total,
            'notes' => sanitize_textarea_field($data['notes'] ?? ''),
            'snapshot_json' => wp_json_encode($snapshot),
            'work_date' => self::date_or_null($data['work_date'] ?? '') ?: current_time('Y-m-d'),
            'approval_status' => 'pending',
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql'),
        ];
        $ok = $wpdb->insert($t['entries'], $row);
        return $ok ? (int)$wpdb->insert_id : new WP_Error('glass_entry_save', 'The payout entry could not be saved.');
    }

    public static function entries(array $args=[]): array {
        global $wpdb;$t=self::tables();$where=['1=1'];$params=[];
        if(!empty($args['blower_user_id'])){$where[]='blower_user_id=%d';$params[]=absint($args['blower_user_id']);}
        if(!empty($args['approval_status'])){$where[]='approval_status=%s';$params[]=sanitize_key($args['approval_status']);}
        $sql="SELECT * FROM {$t['entries']} WHERE ".implode(' AND ',$where)." ORDER BY work_date DESC,id DESC LIMIT 200";if($params)$sql=$wpdb->prepare($sql,$params);
        return $wpdb->get_results($sql,ARRAY_A)?:[];
    }

    public static function approve_entry(int $id,string $status): bool { global $wpdb;$t=self::tables();return false!==$wpdb->update($t['entries'],['approval_status'=>in_array($status,['approved','rejected','pending'],true)?$status:'pending'],['id'=>$id]); }

    public static function summary(): array {
        global $wpdb;$t=self::tables();$today=current_time('Y-m-d');
        return [
            'open_jobs'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM {$t['jobs']} WHERE status NOT IN ('completed','cancelled')"),
            'cremation_ready'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM {$t['jobs']} WHERE job_type='cremation' AND status NOT IN ('completed','cancelled') AND ashes_status='received'"),
            'overdue'=>(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$t['jobs']} WHERE status NOT IN ('completed','cancelled') AND due_date IS NOT NULL AND due_date<%s",$today)),
            'pending_payout'=>(float)$wpdb->get_var("SELECT COALESCE(SUM(total),0) FROM {$t['entries']} WHERE approval_status='pending'"),
            'approved_payout'=>(float)$wpdb->get_var("SELECT COALESCE(SUM(total),0) FROM {$t['entries']} WHERE approval_status='approved' AND pay_period_id=0"),
        ];
    }

    public static function glass_workers(): array {
        global $wpdb;
        $catalog = class_exists('Elev8_OS_Production_Catalog_Service') ? Elev8_OS_Production_Catalog_Service::tables() : [];
        if (empty($catalog['compensation_profiles'])) { return []; }
        $ids = $wpdb->get_col("SELECT user_id FROM {$catalog['compensation_profiles']} WHERE active=1 ORDER BY id ASC") ?: [];
        $out = [];
        foreach ($ids as $id) {
            $user = get_userdata((int) $id);
            if ($user instanceof WP_User && user_can($user, 'elev8_glass_work')) { $out[] = $user; }
        }
        usort($out, static fn(WP_User $a, WP_User $b): int => strcasecmp($a->display_name, $b->display_name));
        return $out;
    }

    public static function ensure_foundation_blowers(): void {
        $emails = [
            'shimkus92@gmail.com' => 'Nick',
            'adamelev8@gmail.com' => 'Adam',
        ];
        foreach ($emails as $email => $label) {
            $user = get_user_by('email', $email);
            if (!($user instanceof WP_User)) { continue; }
            if (!in_array(Elev8_OS_Access_Service::ROLE_GLASS_BLOWER, (array) $user->roles, true)) {
                $user->add_role(Elev8_OS_Access_Service::ROLE_GLASS_BLOWER);
            }
            if (class_exists('Elev8_OS_Production_Catalog_Service')) {
                Elev8_OS_Production_Catalog_Service::save_compensation_profile([
                    'user_id' => $user->ID,
                    'hourly_rate' => 18,
                    'piecework_eligible' => 1,
                    'active' => 1,
                    'effective_date' => current_time('Y-m-d'),
                    'notes' => $label . ' foundation glassblower profile.',
                ]);
            }
        }
    }

    public static function job_lines(int $job_id): array {
        global $wpdb; $t = self::tables();
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$t['job_lines']} WHERE job_id=%d ORDER BY id ASC", $job_id), ARRAY_A) ?: [];
    }

    public static function save_job_line(array $data): int|WP_Error {
        global $wpdb; $t = self::tables();
        $job_id = absint($data['job_id'] ?? 0);
        $product_id = absint($data['production_product_id'] ?? 0);
        if (!$job_id || !$product_id || !class_exists('Elev8_OS_Production_Catalog_Service')) {
            return new WP_Error('glass_job_line_missing', 'Choose a production product.');
        }
        $product = Elev8_OS_Production_Catalog_Service::product($product_id);
        if (!$product) { return new WP_Error('glass_job_line_product', 'Production product not found.'); }
        $materials = (array) ($product['materials'] ?? []);
        $material_cost = 0.0;
        foreach ($materials as $m) { $material_cost += (float) ($m['calculated_cost'] ?? 0); }
        $snapshot = [
            'product_id' => $product_id,
            'version_number' => (int) ($product['version_number'] ?? 1),
            'product_code' => (string) ($product['product_code'] ?? ''),
            'product_name' => (string) ($product['product_name'] ?? ''),
            'compensation_method' => (string) ($product['compensation_method'] ?? 'hourly'),
            'piecework_rate' => (float) ($product['piecework_rate'] ?? 0),
            'piecework_unit' => (string) ($product['piecework_unit'] ?? 'piece'),
            'estimated_minutes' => (float) ($product['estimated_minutes'] ?? 0),
            'material_cost' => $material_cost,
            'materials' => $materials,
            'captured_at' => current_time('mysql'),
        ];
        $now = current_time('mysql');
        $row = [
            'job_id' => $job_id,
            'production_product_id' => $product_id,
            'product_version' => (int) $snapshot['version_number'],
            'item_name' => sanitize_text_field($product['product_name']),
            'quantity' => max(0.01, (float) ($data['quantity'] ?? 1)),
            'compensation_method' => sanitize_key($product['compensation_method']),
            'piecework_rate' => (float) $product['piecework_rate'],
            'piecework_unit' => sanitize_key($product['piecework_unit']),
            'estimated_minutes' => (float) $product['estimated_minutes'],
            'material_cost' => $material_cost,
            'snapshot_json' => wp_json_encode($snapshot),
            'status' => 'planned',
            'created_by' => get_current_user_id(),
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $ok = $wpdb->insert($t['job_lines'], $row);
        return $ok ? (int) $wpdb->insert_id : new WP_Error('glass_job_line_save', 'Production line could not be saved.');
    }

    public static function update_job_line(int $id, array $data): bool {
        global $wpdb; $t = self::tables();
        $allowed = [
            'status' => sanitize_key($data['status'] ?? 'planned'),
            'quantity_completed' => max(0, (float) ($data['quantity_completed'] ?? 0)),
            'quantity_rejected' => max(0, (float) ($data['quantity_rejected'] ?? 0)),
            'actual_minutes' => max(0, (float) ($data['actual_minutes'] ?? 0)),
            'qc_status' => sanitize_key($data['qc_status'] ?? 'not_reviewed'),
            'manager_approved' => empty($data['manager_approved']) ? 0 : 1,
            'payroll_approved' => empty($data['payroll_approved']) ? 0 : 1,
            'updated_at' => current_time('mysql'),
        ];
        return false !== $wpdb->update($t['job_lines'], $allowed, ['id' => $id]);
    }

    public static function blower_pay_summary(int $user_id, string $start = '', string $end = ''): array {
        global $wpdb; $t = self::tables();
        $where = ['blower_user_id=%d']; $params = [$user_id];
        if ($start) { $where[] = 'work_date >= %s'; $params[] = $start; }
        if ($end) { $where[] = 'work_date <= %s'; $params[] = $end; }
        $sql = $wpdb->prepare("SELECT * FROM {$t['entries']} WHERE " . implode(' AND ', $where) . " ORDER BY work_date DESC,id DESC", $params);
        $entries = $wpdb->get_results($sql, ARRAY_A) ?: [];
        $summary = ['hourly' => 0.0, 'piecework' => 0.0, 'pending' => 0.0, 'approved' => 0.0, 'entries' => $entries];
        foreach ($entries as $entry) {
            $total = (float) $entry['total'];
            if ($entry['pay_method'] === 'hourly') { $summary['hourly'] += $total; } else { $summary['piecework'] += $total; }
            if ($entry['approval_status'] === 'approved') { $summary['approved'] += $total; } elseif ($entry['approval_status'] === 'pending') { $summary['pending'] += $total; }
        }
        return $summary;
    }

    public static function import_woocommerce_cremation_orders(): int {
        if(!function_exists('wc_get_orders'))return 0;$count=0;
        $orders=wc_get_orders(['limit'=>50,'orderby'=>'date','order'=>'DESC','status'=>array_keys(wc_get_order_statuses())]);
        global $wpdb;$t=self::tables();
        foreach($orders as $order){$products=[];$is=false;foreach($order->get_items() as $item){$name=$item->get_name();$products[]=$name.' × '.$item->get_quantity();if(preg_match('/cremation|memorial|eternal\s*(peace|release)|ashes/i',$name))$is=true;}if(!$is)continue;
            $exists=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$t['jobs']} WHERE source='woocommerce' AND source_id=%d",$order->get_id()));if($exists)continue;
            self::save_job(['job_type'=>'cremation','order_number'=>$order->get_order_number(),'customer_name'=>$order->get_formatted_billing_full_name(),'customer_email'=>$order->get_billing_email(),'customer_phone'=>$order->get_billing_phone(),'product_name'=>implode(', ',$products),'quantity'=>1,'special_notes'=>$order->get_customer_note(),'status'=>'new','priority'=>'normal','ashes_status'=>'waiting','source'=>'woocommerce','source_id'=>$order->get_id()]);$count++;
        }
        return $count;
    }

    private static function date_or_null($date): ?string { $date=sanitize_text_field((string)$date);return preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)?$date:null; }
}

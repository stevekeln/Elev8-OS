<?php
if (!defined('ABSPATH')) { exit; }

/** Amelia-backed pending class decisions and reusable escalation boundary. */
final class Elev8_OS_Class_Approval_Service {
    private const SEEN_OPTION = 'elev8_os_class_approval_seen';
    private const SETTINGS_OPTION = 'elev8_os_class_approval_settings';
    private const CRON_HOOK = 'elev8_os_scan_pending_classes';

    public static function init(): void {
        add_filter('cron_schedules', [__CLASS__, 'cron_schedules']);
        add_action(self::CRON_HOOK, [__CLASS__, 'scan_and_notify']);
        if (!wp_next_scheduled(self::CRON_HOOK)) { wp_schedule_event(time() + 60, 'elev8_every_five_minutes', self::CRON_HOOK); }
    }

    public static function activate(): void {
        if (!wp_next_scheduled(self::CRON_HOOK)) { wp_schedule_event(time() + 60, 'elev8_every_five_minutes', self::CRON_HOOK); }
    }

    public static function cron_schedules(array $schedules): array {
        $schedules['elev8_every_five_minutes'] = ['interval' => 300, 'display' => __('Every five minutes', 'elev8-os')];
        return $schedules;
    }

    public static function settings(): array {
        return wp_parse_args((array) get_option(self::SETTINGS_OPTION, []), [
            'urgent_hours' => 24,
            'email_fallback' => 1,
        ]);
    }

    /** @return array<int,array<string,mixed>> */
    public static function pending_for_current_user(): array {
        $user = class_exists('Elev8_OS_Preview_Service') ? Elev8_OS_Preview_Service::effective_user() : wp_get_current_user();
        if (!$user instanceof WP_User || !$user->exists()) { return []; }
        $all_glass = class_exists('Elev8_OS_Access_Service') && Elev8_OS_Access_Service::user_can('view_glass_dashboard', $user);
        $provider_id = max(0, (int) get_user_meta($user->ID, 'elev8_os_amelia_employee_id', true));
        return self::pending_bookings($all_glass ? 0 : $provider_id, $all_glass);
    }

    /** @return array<int,array<string,mixed>> */
    public static function pending_bookings(int $provider_id = 0, bool $glass_only = false): array {
        global $wpdb;
        $bookings = $wpdb->prefix . 'amelia_customer_bookings';
        $appointments = $wpdb->prefix . 'amelia_appointments';
        if (!self::table_exists($bookings) || !self::table_exists($appointments)) { return []; }

        $bc = self::columns($bookings); $ac = self::columns($appointments);
        $booking_id = self::first($bc, ['id']);
        $appointment_fk = self::first($bc, ['appointmentId','appointment_id']);
        $booking_status = self::first($bc, ['status']);
        $persons = self::first($bc, ['persons','personsCount','persons_count']);
        $customer_fk = self::first($bc, ['customerId','customer_id']);
        $booking_info = self::first($bc, ['info','customFields','custom_fields']);
        $appointment_id = self::first($ac, ['id']);
        $provider_col = self::first($ac, ['providerId','provider_id','employeeId']);
        $service_col = self::first($ac, ['serviceId','service_id']);
        $start_col = self::first($ac, ['bookingStart','booking_start','start']);
        $end_col = self::first($ac, ['bookingEnd','booking_end','end']);
        $location_col = self::first($ac, ['locationId','location_id']);
        if (!$booking_id || !$appointment_fk || !$booking_status || !$appointment_id || !$start_col) { return []; }

        $select = [
            "b.`{$booking_id}` booking_id", "b.`{$booking_status}` booking_status",
            "a.`{$appointment_id}` appointment_id", "a.`{$start_col}` booking_start",
            $end_col ? "a.`{$end_col}` booking_end" : "'' booking_end",
            $persons ? "b.`{$persons}` persons" : '1 persons',
            $provider_col ? "a.`{$provider_col}` provider_id" : '0 provider_id',
            $service_col ? "a.`{$service_col}` service_id" : '0 service_id',
            $location_col ? "a.`{$location_col}` location_id" : '0 location_id',
            $customer_fk ? "b.`{$customer_fk}` customer_id" : '0 customer_id',
            $booking_info ? "b.`{$booking_info}` booking_info" : "'' booking_info",
        ];
        $where = ["LOWER(COALESCE(b.`{$booking_status}`,'')) IN ('pending','waiting','waiting_for_approval')"];
        $params = [];
        if ($provider_id > 0 && $provider_col) { $where[] = "a.`{$provider_col}`=%d"; $params[] = $provider_id; }
        if ($glass_only && $service_col) {
            $ids = self::glass_service_ids();
            if ($ids) { $where[] = 'a.`' . $service_col . '` IN (' . implode(',', array_map('absint', $ids)) . ')'; }
        }
        $sql = 'SELECT ' . implode(',', $select) . " FROM `{$bookings}` b INNER JOIN `{$appointments}` a ON a.`{$appointment_id}`=b.`{$appointment_fk}` WHERE " . implode(' AND ', $where) . " ORDER BY a.`{$start_col}` ASC LIMIT 250";
        if ($params) { $sql = $wpdb->prepare($sql, ...$params); }
        $rows = $wpdb->get_results($sql, ARRAY_A) ?: [];
        $services = self::label_map('amelia_services', ['name','title']);
        $locations = self::label_map('amelia_locations', ['name','address']);
        $providers = self::provider_map();
        $customers = self::customer_map();
        $urgent_hours = max(1, (int) self::settings()['urgent_hours']);
        $now = current_time('timestamp');
        foreach ($rows as &$row) {
            $start_ts = strtotime((string) $row['booking_start']);
            $hours = $start_ts ? (($start_ts - $now) / HOUR_IN_SECONDS) : null;
            $row['service'] = $services[(int) $row['service_id']] ?? __('Class', 'elev8-os');
            $row['location'] = $locations[(int) $row['location_id']] ?? __('Unavailable', 'elev8-os');
            $row['teacher'] = $providers[(int) $row['provider_id']] ?? __('Unassigned', 'elev8-os');
            $row['customer'] = $customers[(int) $row['customer_id']] ?? ['name'=>__('Customer', 'elev8-os'),'email'=>'','phone'=>''];
            $row['urgent'] = $hours !== null && $hours <= $urgent_hours;
            $row['hours_until'] = $hours;
        }
        unset($row);
        return $rows;
    }

    public static function approve(int $booking_id): bool { return self::set_booking_status($booking_id, 'approved', 'class_booking_approved'); }
    public static function cancel(int $booking_id, string $reason): bool {
        $ok = self::set_booking_status($booking_id, 'canceled', 'class_booking_cancelled', $reason);
        return $ok;
    }

    public static function move(int $booking_id, string $new_start): bool {
        global $wpdb;
        $record = self::find_booking($booking_id); if (!$record) { return false; }
        $appointments = $wpdb->prefix . 'amelia_appointments'; $cols = self::columns($appointments);
        $start_col = self::first($cols, ['bookingStart','booking_start','start']);
        $end_col = self::first($cols, ['bookingEnd','booking_end','end']);
        if (!$start_col) { return false; }
        $old_start = strtotime((string) $record['booking_start']); $old_end = strtotime((string) $record['booking_end']);
        $new_ts = strtotime($new_start); if (!$new_ts) { return false; }
        $data = [$start_col => wp_date('Y-m-d H:i:s', $new_ts)];
        $formats = ['%s'];
        if ($end_col && $old_start && $old_end && $old_end > $old_start) { $data[$end_col] = wp_date('Y-m-d H:i:s', $new_ts + ($old_end - $old_start)); $formats[] = '%s'; }
        $ok = $wpdb->update($appointments, $data, ['id'=>(int)$record['appointment_id']], $formats, ['%d']);
        if ($ok === false) { return false; }
        self::record_activity($booking_id, 'class_booking_moved', __('Class booking moved', 'elev8-os'), sprintf('%s → %s', (string)$record['booking_start'], $data[$start_col]));
        return true;
    }

    public static function scan_and_notify(): void {
        $rows = self::pending_bookings(0, true); if (!$rows) { return; }
        $seen = (array) get_option(self::SEEN_OPTION, []); $changed = false;
        foreach ($rows as $row) {
            $id = (int)$row['booking_id']; if ($id < 1 || isset($seen[$id])) { continue; }
            $seen[$id] = time(); $changed = true;
            self::notify_glass_managers($row);
            self::record_activity($id, 'class_booking_pending', __('New pending class booking', 'elev8-os'), (string)$row['service']);
        }
        if ($changed) { update_option(self::SEEN_OPTION, array_slice($seen, -1000, null, true), false); }
    }

    private static function notify_glass_managers(array $row): void {
        if (empty(self::settings()['email_fallback']) || !class_exists('Elev8_OS_Notification_Service')) { return; }
        $url = class_exists('Elev8_OS_Glass_Manager_Suite_Module') ? Elev8_OS_Glass_Manager_Suite_Module::url(['suite_tool'=>'approvals']) : home_url('/glass-manager/');
        foreach (get_users(['fields'=>'all']) as $user) {
            if (!$user instanceof WP_User || !Elev8_OS_Access_Service::user_can('view_glass_dashboard', $user)) { continue; }
            Elev8_OS_Notification_Service::send_email($user->user_email, sprintf('[Elev8 OS] %s', __('New class booking needs a decision', 'elev8-os')), sprintf("%s\n%s\n%s\n\n%s", $row['service'], wp_date('l, F j, Y · ' . get_option('time_format'), strtotime($row['booking_start'])), $row['customer']['name'] ?? '', $url));
        }
    }

    private static function set_booking_status(int $booking_id, string $status, string $activity, string $details=''): bool {
        global $wpdb; $table=$wpdb->prefix.'amelia_customer_bookings'; $cols=self::columns($table); $status_col=self::first($cols,['status']);
        if (!$status_col || !$booking_id) { return false; }
        $ok=$wpdb->update($table,[$status_col=>$status],['id'=>$booking_id],['%s'],['%d']); if($ok===false){return false;}
        self::record_activity($booking_id,$activity,ucwords(str_replace('_',' ',$activity)),$details); return true;
    }

    private static function record_activity(int $id,string $type,string $label,string $details=''): void {
        if(class_exists('Elev8_OS_Activity_Service')) Elev8_OS_Activity_Service::record(['type'=>$type,'label'=>$label,'details'=>$details,'object_id'=>$id,'object_type'=>'amelia_booking','source'=>'amelia','actor_user_id'=>get_current_user_id()]);
    }

    private static function find_booking(int $id): ?array { foreach(self::pending_bookings(0,false) as $r){if((int)$r['booking_id']===$id)return $r;} return null; }
    private static function glass_service_ids(): array {
        global $wpdb; $table=$wpdb->prefix.'amelia_services'; if(!self::table_exists($table))return[]; $cols=self::columns($table); $name=self::first($cols,['name','title']); $desc=self::first($cols,['description','details','content']); if(!$name)return[];
        $rows=$wpdb->get_results('SELECT `id`,`'.$name.'` name'.($desc?',`'.$desc.'` description':",'' description").' FROM `'.$table.'`',ARRAY_A)?:[]; $ids=[];
        foreach($rows as $r){$h=strtolower(wp_strip_all_tags(($r['name']??'').' '.($r['description']??''))); foreach(['glassblowing','glass blowing','liquid arts','lampwork','flamework','torch class','glass 101'] as $k){if(strpos($h,$k)!==false){$ids[]=(int)$r['id'];break;}}} return array_values(array_unique($ids));
    }
    private static function customer_map(): array { global $wpdb;$t=$wpdb->prefix.'amelia_users';if(!self::table_exists($t))return[];$c=self::columns($t);$rows=$wpdb->get_results('SELECT `id`,'.(in_array('firstName',$c,true)?'`firstName`':'\'\'').' first_name,'.(in_array('lastName',$c,true)?'`lastName`':'\'\'').' last_name,'.(in_array('email',$c,true)?'`email`':'\'\'').' email,'.(in_array('phone',$c,true)?'`phone`':'\'\'').' phone FROM `'.$t.'`',ARRAY_A)?:[];$m=[];foreach($rows as$r){$m[(int)$r['id']]=['name'=>trim(($r['first_name']??'').' '.($r['last_name']??'')),'email'=>$r['email']??'','phone'=>$r['phone']??''];}return$m; }
    private static function provider_map(): array { global $wpdb;$t=$wpdb->prefix.'amelia_users';if(!self::table_exists($t))return[];$c=self::columns($t);$rows=$wpdb->get_results('SELECT `id`,'.(in_array('firstName',$c,true)?'`firstName`':'\'\'').' first_name,'.(in_array('lastName',$c,true)?'`lastName`':'\'\'').' last_name FROM `'.$t.'`',ARRAY_A)?:[];$m=[];foreach($rows as$r)$m[(int)$r['id']]=trim(($r['first_name']??'').' '.($r['last_name']??''));return$m; }
    private static function label_map(string $suffix,array $labels):array{global$wpdb;$t=$wpdb->prefix.$suffix;if(!self::table_exists($t))return[];$c=self::columns($t);$l=self::first($c,$labels);if(!$l||!in_array('id',$c,true))return[];$m=[];foreach($wpdb->get_results("SELECT `id`,`{$l}` label FROM `{$t}`",ARRAY_A)?:[]as$r)$m[(int)$r['id']]=(string)$r['label'];return$m;}
    private static function table_exists(string $t):bool{global$wpdb;return$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$t))===$t;}
    private static function columns(string $t):array{global$wpdb;$c=$wpdb->get_col("DESCRIBE `{$t}`",0);return is_array($c)?array_map('strval',$c):[];}
    private static function first(array $available,array $candidates):?string{foreach($candidates as$c)if(in_array($c,$available,true))return$c;return null;}
}

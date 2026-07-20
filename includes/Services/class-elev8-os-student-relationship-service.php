<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Verified artist/student relationships built from Amelia bookings.
 * Elev8 OS stores only artist-owned CRM enrichment (notes, tags, timeline),
 * while Amelia remains the source of truth for bookings and customer identity.
 */
final class Elev8_OS_Student_Relationship_Service {
    private const EMPLOYEE_META_KEY = 'elev8_os_amelia_employee_id';
    private const DB_VERSION = '1.0.0';
    private const DB_OPTION = 'elev8_os_student_relationship_db_version';

    public static function init(): void {
        add_action('init', [__CLASS__, 'maybe_upgrade'], 5);
    }

    public static function activate(): void { self::install_schema(); }

    public static function maybe_upgrade(): void {
        if ((string) get_option(self::DB_OPTION, '') !== self::DB_VERSION) { self::install_schema(); }
    }

    private static function install_schema(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $notes = self::table('student_notes');
        $tags = self::table('student_tags');
        $timeline = self::table('student_timeline');
        dbDelta("CREATE TABLE {$notes} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            artist_user_id bigint(20) unsigned NOT NULL,
            customer_key varchar(80) NOT NULL,
            note_text longtext NOT NULL,
            created_by bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY artist_customer (artist_user_id, customer_key)
        ) {$charset};");
        dbDelta("CREATE TABLE {$tags} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            artist_user_id bigint(20) unsigned NOT NULL,
            customer_key varchar(80) NOT NULL,
            tag_slug varchar(100) NOT NULL,
            tag_label varchar(150) NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY unique_tag (artist_user_id, customer_key, tag_slug),
            KEY artist_customer (artist_user_id, customer_key)
        ) {$charset};");
        dbDelta("CREATE TABLE {$timeline} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            artist_user_id bigint(20) unsigned NOT NULL,
            customer_key varchar(80) NOT NULL,
            event_type varchar(60) NOT NULL,
            event_title varchar(255) NOT NULL,
            event_detail longtext NULL,
            event_date datetime NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY artist_customer_date (artist_user_id, customer_key, event_date)
        ) {$charset};");
        update_option(self::DB_OPTION, self::DB_VERSION, false);
    }

    /** @return array<string,mixed> */
    public static function get_snapshot(WP_User $user): array {
        $students = self::get_students($user);
        if (!$students['available']) {
            return ['available'=>false,'reason'=>$students['reason'],'total'=>0,'repeat'=>0,'new_this_month'=>0,'inactive'=>0,'upcoming'=>0,'students'=>[]];
        }
        $now = current_time('timestamp');
        $month_start = strtotime(wp_date('Y-m-01 00:00:00', $now));
        $repeat = $new = $inactive = $upcoming = 0;
        foreach ($students['students'] as $student) {
            if ((int) $student['classes_attended'] >= 2) { $repeat++; }
            $first = $student['first_class_at'] ? strtotime((string) $student['first_class_at']) : 0;
            if ($first >= $month_start) { $new++; }
            $last = $student['last_class_at'] ? strtotime((string) $student['last_class_at']) : 0;
            if ($last > 0 && $last < strtotime('-90 days', $now)) { $inactive++; }
            if ((int) $student['upcoming_bookings'] > 0) { $upcoming++; }
        }
        return ['available'=>true,'reason'=>'','total'=>count($students['students']),'repeat'=>$repeat,'new_this_month'=>$new,'inactive'=>$inactive,'upcoming'=>$upcoming,'students'=>$students['students']];
    }

    /** @return array<string,mixed> */
    public static function get_students(WP_User $user): array {
        global $wpdb;
        $provider_id = self::provider_id($user);
        if ($provider_id <= 0) { return self::unavailable(__('Your WordPress account is not connected to an Amelia artist.', 'elev8-os')); }
        $appointments = $wpdb->prefix . 'amelia_appointments';
        $bookings = $wpdb->prefix . 'amelia_customer_bookings';
        $users = $wpdb->prefix . 'amelia_users';
        $services = $wpdb->prefix . 'amelia_services';
        if (!self::table_exists($appointments) || !self::table_exists($bookings) || !self::table_exists($users)) {
            return self::unavailable(__('Required Amelia customer and booking tables were not found.', 'elev8-os'));
        }
        $ac=self::columns($appointments); $bc=self::columns($bookings); $uc=self::columns($users); $sc=self::table_exists($services)?self::columns($services):[];
        $aid=self::first($ac,['id']); $provider=self::first($ac,['providerId','provider_id','employeeId']); $start=self::first($ac,['bookingStart','booking_start','start']); $service=self::first($ac,['serviceId','service_id']);
        $b_appt=self::first($bc,['appointmentId','appointment_id']); $customer=self::first($bc,['customerId','customer_id']); $status=self::first($bc,['status']); $persons=self::first($bc,['persons','personsCount','persons_count']); $created=self::first($bc,['created','createdAt','created_at']); $price=self::first($bc,['price','amount','total']);
        if (!$aid || !$provider || !$start || !$b_appt || !$customer || !in_array('id',$uc,true)) { return self::unavailable(__('Required Amelia relationship columns could not be verified.', 'elev8-os')); }
        $first=self::first($uc,['firstName','first_name']); $last=self::first($uc,['lastName','last_name']); $email=self::first($uc,['email']); $phone=self::first($uc,['phone']);
        $service_name=self::first($sc,['name','title']);
        $select=["b.`{$customer}` AS customer_id","a.`{$aid}` AS appointment_id","a.`{$start}` AS class_start"];
        if($status){$select[]="b.`{$status}` AS booking_status";} if($persons){$select[]="b.`{$persons}` AS seats";} if($created){$select[]="b.`{$created}` AS booked_at";} if($price){$select[]="b.`{$price}` AS booked_value";}
        if($first){$select[]="u.`{$first}` AS first_name";} if($last){$select[]="u.`{$last}` AS last_name";} if($email){$select[]="u.`{$email}` AS email";} if($phone){$select[]="u.`{$phone}` AS phone";}
        $join_service=''; if($service && $service_name && in_array('id',$sc,true)){ $select[]="s.`{$service_name}` AS service_name"; $join_service=" LEFT JOIN `{$services}` s ON s.`id`=a.`{$service}`"; }
        $sql='SELECT '.implode(', ',$select)." FROM `{$appointments}` a INNER JOIN `{$bookings}` b ON b.`{$b_appt}`=a.`{$aid}` INNER JOIN `{$users}` u ON u.`id`=b.`{$customer}`{$join_service} WHERE a.`{$provider}`=%d ORDER BY a.`{$start}` DESC";
        $rows=$wpdb->get_results($wpdb->prepare($sql,$provider_id),ARRAY_A);
        if(!is_array($rows)){ return self::unavailable(__('Amelia student relationships could not be read.', 'elev8-os')); }
        $now=current_time('timestamp'); $out=[];
        foreach($rows as $row){
            $booking_status=strtolower((string)($row['booking_status']??'')); if(in_array($booking_status,['canceled','cancelled','rejected'],true)){continue;}
            $email_value=sanitize_email((string)($row['email']??'')); $customer_id=absint($row['customer_id']??0); $key=$customer_id>0?'amelia:'.$customer_id:'email:'.sha1(strtolower($email_value));
            if(!isset($out[$key])){$out[$key]=['customer_key'=>$key,'amelia_customer_id'=>$customer_id,'name'=>trim((string)($row['first_name']??'').' '.(string)($row['last_name']??'')),'email'=>$email_value,'phone'=>sanitize_text_field((string)($row['phone']??'')),'first_class_at'=>'','last_class_at'=>'','last_class_name'=>'','classes_attended'=>0,'bookings_total'=>0,'upcoming_bookings'=>0,'seats_total'=>0,'lifetime_value'=>0.0,'tags'=>[],'notes_count'=>0];}
            $ts=strtotime((string)$row['class_start']); $seats=max(1,(int)($row['seats']??1)); $out[$key]['bookings_total']++; $out[$key]['seats_total']+=$seats; $out[$key]['lifetime_value']+=(float)($row['booked_value']??0);
            if($ts>$now){$out[$key]['upcoming_bookings']++;} else {$out[$key]['classes_attended']++; if($out[$key]['last_class_at']===''||$ts>strtotime((string)$out[$key]['last_class_at'])){$out[$key]['last_class_at']=(string)$row['class_start'];$out[$key]['last_class_name']=(string)($row['service_name']??__('Class','elev8-os'));}}
            if($out[$key]['first_class_at']===''||$ts<strtotime((string)$out[$key]['first_class_at'])){$out[$key]['first_class_at']=(string)$row['class_start'];}
        }
        $students=array_values($out); self::attach_enrichment((int)$user->ID,$students);
        usort($students,static function($a,$b){return strcasecmp((string)$a['name'],(string)$b['name']);});
        return ['available'=>true,'reason'=>'','provider_id'=>$provider_id,'students'=>$students];
    }

    /** @return array<string,mixed>|null */
    public static function get_student(WP_User $user,string $key): ?array {
        $result=self::get_students($user); if(!$result['available']){return null;} foreach($result['students'] as $student){if(hash_equals((string)$student['customer_key'],$key)){ $student['notes']=self::get_notes((int)$user->ID,$key); $student['timeline']=self::get_timeline($user,$student); return $student; }} return null;
    }

    public static function add_note(int $artist_user_id,string $customer_key,string $note): bool {
        global $wpdb; $note=trim(wp_strip_all_tags($note)); if($artist_user_id<=0||$customer_key===''||$note===''){return false;}
        $ok=$wpdb->insert(self::table('student_notes'),['artist_user_id'=>$artist_user_id,'customer_key'=>$customer_key,'note_text'=>$note,'created_by'=>get_current_user_id(),'created_at'=>current_time('mysql')],['%d','%s','%s','%d','%s']);
        if($ok!==false){self::add_timeline($artist_user_id,$customer_key,'note_added',__('Note added','elev8-os'),$note);}
        return $ok!==false;
    }

    /** @param string[] $labels */
    public static function replace_tags(int $artist_user_id,string $customer_key,array $labels): bool {
        global $wpdb; $table=self::table('student_tags'); $wpdb->delete($table,['artist_user_id'=>$artist_user_id,'customer_key'=>$customer_key],['%d','%s']);
        $saved=[]; foreach($labels as $label){$label=trim(sanitize_text_field($label)); if($label===''){continue;} $slug=sanitize_title($label); if($slug===''||isset($saved[$slug])){continue;} $saved[$slug]=true; $wpdb->insert($table,['artist_user_id'=>$artist_user_id,'customer_key'=>$customer_key,'tag_slug'=>$slug,'tag_label'=>$label,'created_at'=>current_time('mysql')],['%d','%s','%s','%s','%s']);}
        self::add_timeline($artist_user_id,$customer_key,'tags_updated',__('Tags updated','elev8-os'),implode(', ',array_keys($saved))); return true;
    }

    /** @return array<int,array<string,mixed>> */
    private static function get_notes(int $artist_user_id,string $key): array { global $wpdb; return (array)$wpdb->get_results($wpdb->prepare('SELECT * FROM `'.self::table('student_notes').'` WHERE artist_user_id=%d AND customer_key=%s ORDER BY created_at DESC',$artist_user_id,$key),ARRAY_A); }

    /** @return array<int,array<string,mixed>> */
    private static function get_timeline(WP_User $user,array $student): array {
        global $wpdb; $events=(array)$wpdb->get_results($wpdb->prepare('SELECT event_type,event_title,event_detail,event_date FROM `'.self::table('student_timeline').'` WHERE artist_user_id=%d AND customer_key=%s ORDER BY event_date DESC LIMIT 30',(int)$user->ID,(string)$student['customer_key']),ARRAY_A);
        if($student['first_class_at']!==''){$events[]=['event_type'=>'first_class','event_title'=>__('First class relationship','elev8-os'),'event_detail'=>'','event_date'=>$student['first_class_at']];}
        if($student['last_class_at']!==''){$events[]=['event_type'=>'class','event_title'=>sprintf(__('Attended %s','elev8-os'),(string)$student['last_class_name']),'event_detail'=>'','event_date'=>$student['last_class_at']];}
        usort($events,static fn($a,$b)=>strcmp((string)$b['event_date'],(string)$a['event_date'])); return array_slice($events,0,30);
    }

    private static function add_timeline(int $artist_user_id,string $key,string $type,string $title,string $detail=''): void { global $wpdb; $wpdb->insert(self::table('student_timeline'),['artist_user_id'=>$artist_user_id,'customer_key'=>$key,'event_type'=>$type,'event_title'=>$title,'event_detail'=>$detail,'event_date'=>current_time('mysql'),'created_at'=>current_time('mysql')],['%d','%s','%s','%s','%s','%s']); }

    /** @param array<int,array<string,mixed>> $students */
    private static function attach_enrichment(int $artist_user_id,array &$students): void {
        global $wpdb; if(!$students){return;} $tags=(array)$wpdb->get_results($wpdb->prepare('SELECT customer_key,tag_label FROM `'.self::table('student_tags').'` WHERE artist_user_id=%d ORDER BY tag_label',$artist_user_id),ARRAY_A); $notes=(array)$wpdb->get_results($wpdb->prepare('SELECT customer_key,COUNT(*) AS total FROM `'.self::table('student_notes').'` WHERE artist_user_id=%d GROUP BY customer_key',$artist_user_id),ARRAY_A);
        $tag_map=[];$note_map=[];foreach($tags as $row){$tag_map[(string)$row['customer_key']][]=(string)$row['tag_label'];}foreach($notes as $row){$note_map[(string)$row['customer_key']]=(int)$row['total'];}
        foreach($students as &$student){$key=(string)$student['customer_key'];$student['tags']=$tag_map[$key]??[];$student['notes_count']=$note_map[$key]??0;if((int)$student['classes_attended']>=2&&!in_array('Repeat Student',$student['tags'],true)){$student['tags'][]='Repeat Student';}}
    }

    private static function provider_id(WP_User $user): int { global $wpdb; $mapped=absint(get_user_meta($user->ID,self::EMPLOYEE_META_KEY,true)); if($mapped>0){return $mapped;} $table=$wpdb->prefix.'amelia_users'; if(!self::table_exists($table)||!in_array('email',self::columns($table),true)){return 0;} return absint($wpdb->get_var($wpdb->prepare("SELECT id FROM `{$table}` WHERE LOWER(email)=LOWER(%s) LIMIT 1",sanitize_email($user->user_email)))); }
    private static function table(string $suffix): string { global $wpdb; return $wpdb->prefix.'elev8_os_'.$suffix; }
    private static function table_exists(string $table): bool { global $wpdb; return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$table))===$table; }
    /** @return string[] */ private static function columns(string $table): array { global $wpdb; $v=$wpdb->get_col("DESCRIBE `{$table}`",0); return is_array($v)?array_map('strval',$v):[]; }
    private static function first(array $available,array $candidates): ?string { foreach($candidates as $candidate){if(in_array($candidate,$available,true)){return $candidate;}} return null; }
    /** @return array<string,mixed> */ private static function unavailable(string $reason): array { return ['available'=>false,'reason'=>$reason,'students'=>[]]; }
}

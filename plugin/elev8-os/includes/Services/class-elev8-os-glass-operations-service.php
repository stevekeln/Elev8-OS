<?php
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Glass_Operations_Service {
    const DB_VERSION = '1.0.0';
    const OPTION_DB_VERSION = 'elev8_os_glass_ops_db_version';

    public static function init(): void {
        add_action('init', [__CLASS__, 'maybe_install'], 7);
    }

    public static function activate(): void { self::install(); }

    public static function tables(): array {
        global $wpdb;
        return [
            'jobs' => $wpdb->prefix . 'elev8_glass_jobs',
            'entries' => $wpdb->prefix . 'elev8_glass_work_entries',
            'pay_periods' => $wpdb->prefix . 'elev8_glass_pay_periods',
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
            work_date date NOT NULL,
            approval_status varchar(20) NOT NULL DEFAULT 'pending',
            pay_period_id bigint(20) unsigned NOT NULL DEFAULT 0,
            created_by bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY blower_period (blower_user_id,pay_period_id),
            KEY job_id (job_id),
            KEY approval_status (approval_status)
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
            'job_type'=>in_array(($data['job_type']??''),['production','cremation'],true)?$data['job_type']:'production',
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
        global $wpdb;$t=self::tables();$method=sanitize_key($data['pay_method']??'piece_rate');
        $qty=max(0,(float)($data['quantity']??0));$rate=max(0,(float)($data['rate']??0));$minutes=max(0,(float)($data['minutes']??0));$bonus=(float)($data['bonus']??0);$adj=(float)($data['adjustment']??0);
        $base=$method==='hourly' ? ($minutes/60)*$rate : ($method==='fixed' ? $rate : $qty*$rate);
        $total=round($base+$bonus+$adj,2);
        $row=['job_id'=>absint($data['job_id']??0),'blower_user_id'=>absint($data['blower_user_id']??0),'item_name'=>sanitize_text_field($data['item_name']??''),'quantity'=>$qty,'pay_method'=>$method,'rate'=>$rate,'minutes'=>$minutes,'bonus'=>$bonus,'adjustment'=>$adj,'total'=>$total,'notes'=>sanitize_textarea_field($data['notes']??''),'work_date'=>self::date_or_null($data['work_date']??'')?:current_time('Y-m-d'),'approval_status'=>'pending','created_by'=>get_current_user_id(),'created_at'=>current_time('mysql')];
        if(!$row['blower_user_id'])return new WP_Error('glass_entry_user','Choose a blower.');
        $ok=$wpdb->insert($t['entries'],$row);return $ok?(int)$wpdb->insert_id:new WP_Error('glass_entry_save','The payout entry could not be saved.');
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
        $users=get_users(['orderby'=>'display_name','order'=>'ASC']);$out=[];
        foreach($users as $u){if(user_can($u,'elev8_glass_work')||user_can($u,'elev8_manage_glass')||user_can($u,'manage_options'))$out[]=$u;}
        return $out;
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

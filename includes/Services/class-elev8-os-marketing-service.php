<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Artist-owned campaign records and safe audience delivery.
 * WordPress/wp_mail remains the configured delivery boundary.
 */
final class Elev8_OS_Marketing_Service {
    private const DB_VERSION = '1.0.0';
    private const DB_OPTION = 'elev8_os_marketing_db_version';
    private const MAX_RECIPIENTS = 250;

    public static function init(): void {
        add_action('init', [__CLASS__, 'maybe_upgrade'], 5);
        add_action('template_redirect', [__CLASS__, 'handle_unsubscribe'], 3);
    }
    public static function activate(): void { self::install_schema(); }
    public static function maybe_upgrade(): void {
        if ((string) get_option(self::DB_OPTION, '') !== self::DB_VERSION) { self::install_schema(); }
    }
    private static function install_schema(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $campaigns = self::table('campaigns');
        $recipients = self::table('campaign_recipients');
        $unsub = self::table('email_unsubscribes');
        dbDelta("CREATE TABLE {$campaigns} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            artist_user_id bigint(20) unsigned NOT NULL,
            campaign_type varchar(60) NOT NULL DEFAULT 'custom',
            subject varchar(255) NOT NULL,
            message longtext NOT NULL,
            audience_type varchar(60) NOT NULL DEFAULT 'all',
            audience_value varchar(190) NOT NULL DEFAULT '',
            promoted_url text NULL,
            referral_url text NULL,
            status varchar(30) NOT NULL DEFAULT 'draft',
            recipient_count int unsigned NOT NULL DEFAULT 0,
            sent_count int unsigned NOT NULL DEFAULT 0,
            failed_count int unsigned NOT NULL DEFAULT 0,
            skipped_count int unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            sent_at datetime NULL,
            PRIMARY KEY (id),
            KEY artist_status (artist_user_id, status),
            KEY sent_at (sent_at)
        ) {$charset};");
        dbDelta("CREATE TABLE {$recipients} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            campaign_id bigint(20) unsigned NOT NULL,
            customer_key varchar(80) NOT NULL,
            email varchar(190) NOT NULL,
            recipient_name varchar(190) NOT NULL DEFAULT '',
            delivery_status varchar(30) NOT NULL DEFAULT 'pending',
            error_message text NULL,
            sent_at datetime NULL,
            PRIMARY KEY (id),
            UNIQUE KEY campaign_email (campaign_id, email),
            KEY campaign_status (campaign_id, delivery_status)
        ) {$charset};");
        dbDelta("CREATE TABLE {$unsub} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            email varchar(190) NOT NULL,
            artist_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            token varchar(64) NOT NULL,
            unsubscribed_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY artist_email (artist_user_id, email),
            UNIQUE KEY token (token)
        ) {$charset};");
        update_option(self::DB_OPTION, self::DB_VERSION, false);
    }

    /** @return array<string,array<string,string>> */
    public static function templates(): array {
        return [
            'new_class' => ['label'=>__('New Class Announcement','elev8-os'),'subject'=>__('A new class from {{artist_name}}','elev8-os'),'message'=>__('Hi {{first_name}},\n\nI have a new class available and would love to create with you again.\n\n{{promotion_link}}\n\nHope to see you there!\n\n{{artist_name}}','elev8-os')],
            'thank_you' => ['label'=>__('Thanks for Attending','elev8-os'),'subject'=>__('Thank you for creating with me','elev8-os'),'message'=>__('Hi {{first_name}},\n\nThank you for joining my class. I hope you enjoyed the experience and feel proud of what you made.\n\nYou can stay connected with my work here:\n{{artist_profile}}\n\n{{artist_name}}','elev8-os')],
            'come_back' => ['label'=>__('Come Back Soon','elev8-os'),'subject'=>__('Let’s create together again','elev8-os'),'message'=>__('Hi {{first_name}},\n\nIt has been a little while since we created together. I would love to welcome you back to an upcoming class.\n\n{{promotion_link}}\n\n{{artist_name}}','elev8-os')],
            'art_walk' => ['label'=>__('Art Walk Invitation','elev8-os'),'subject'=>__('Join me at the next Elev8 Art Walk','elev8-os'),'message'=>__('Hi {{first_name}},\n\nI will be part of the next Elev8 Art Walk. Come see local art, meet artists, and enjoy the community.\n\n{{promotion_link}}\n\n{{artist_name}}','elev8-os')],
            'new_artwork' => ['label'=>__('New Artwork Available','elev8-os'),'subject'=>__('See my newest artwork','elev8-os'),'message'=>__('Hi {{first_name}},\n\nI just added a new piece of artwork and wanted you to be among the first to see it.\n\n{{promotion_link}}\n\n{{artist_name}}','elev8-os')],
            'promote_artist' => ['label'=>__('Promote Another Artist’s Class','elev8-os'),'subject'=>__('A class I think you will enjoy','elev8-os'),'message'=>__('Hi {{first_name}},\n\nI found another Elev8 artist experience that I think you may enjoy.\n\n{{promotion_link}}\n\nThis link lets Elev8 know I introduced you.\n\n{{artist_name}}','elev8-os')],
            'custom' => ['label'=>__('Blank Email','elev8-os'),'subject'=>'','message'=>''],
        ];
    }

    /** @return array<string,mixed> */
    public static function audience(WP_User $artist, string $type, string $value=''): array {
        $result = Elev8_OS_Student_Relationship_Service::get_students($artist);
        if (empty($result['available'])) { return ['available'=>false,'reason'=>(string)($result['reason']??__('Students unavailable.','elev8-os')),'students'=>[]]; }
        $now = current_time('timestamp');
        $students = array_values(array_filter((array)$result['students'], static function(array $student) use ($type,$value,$now): bool {
            if (empty($student['email']) || !is_email((string)$student['email'])) { return false; }
            if ($type === 'repeat') { return (int)$student['classes_attended'] >= 2; }
            if ($type === 'new') { return !empty($student['first_class_at']) && strtotime((string)$student['first_class_at']) >= strtotime('-30 days',$now); }
            if ($type === 'upcoming') { return (int)$student['upcoming_bookings'] > 0; }
            if ($type === 'inactive') { return !empty($student['last_class_at']) && strtotime((string)$student['last_class_at']) < strtotime('-90 days',$now); }
            if ($type === 'tag') { return in_array($value,(array)$student['tags'],true); }
            return true;
        }));
        $unique=[];
        foreach ($students as $student) { $email=strtolower(sanitize_email((string)$student['email'])); if($email!==''&&!isset($unique[$email])){$unique[$email]=$student;} }
        return ['available'=>true,'reason'=>'','students'=>array_values($unique),'count'=>count($unique)];
    }

    /** @param array<string,mixed> $data */
    public static function save_campaign(int $artist_user_id, array $data, string $status='draft'): int {
        global $wpdb;
        $now=current_time('mysql');
        $wpdb->insert(self::table('campaigns'),[
            'artist_user_id'=>$artist_user_id,
            'campaign_type'=>sanitize_key((string)($data['campaign_type']??'custom')),
            'subject'=>sanitize_text_field((string)($data['subject']??'')),
            'message'=>wp_kses_post((string)($data['message']??'')),
            'audience_type'=>sanitize_key((string)($data['audience_type']??'all')),
            'audience_value'=>sanitize_text_field((string)($data['audience_value']??'')),
            'promoted_url'=>esc_url_raw((string)($data['promoted_url']??'')),
            'referral_url'=>esc_url_raw((string)($data['referral_url']??'')),
            'status'=>$status,'created_at'=>$now,'updated_at'=>$now,
        ],['%d','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s']);
        return (int)$wpdb->insert_id;
    }

    /** @return array<string,mixed>|null */
    public static function get_campaign(int $id,int $artist_user_id): ?array { global $wpdb; $row=$wpdb->get_row($wpdb->prepare('SELECT * FROM `'.self::table('campaigns').'` WHERE id=%d AND artist_user_id=%d',$id,$artist_user_id),ARRAY_A); return is_array($row)?$row:null; }
    /** @return array<int,array<string,mixed>> */
    public static function campaigns(int $artist_user_id,int $limit=30): array { global $wpdb; return (array)$wpdb->get_results($wpdb->prepare('SELECT * FROM `'.self::table('campaigns').'` WHERE artist_user_id=%d ORDER BY created_at DESC LIMIT %d',$artist_user_id,$limit),ARRAY_A); }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    public static function send_campaign(WP_User $artist,array $data,bool $test=false): array {
        $audience = self::audience($artist,(string)($data['audience_type']??'all'),(string)($data['audience_value']??''));
        if (!$test && empty($audience['available'])) { return ['ok'=>false,'message'=>(string)$audience['reason']]; }
        $students = $test ? [['customer_key'=>'test','name'=>$artist->display_name,'email'=>$artist->user_email,'tags'=>[]]] : (array)$audience['students'];
        if (count($students)>self::MAX_RECIPIENTS) { return ['ok'=>false,'message'=>sprintf(__('Audience exceeds the safe batch limit of %d recipients.','elev8-os'),self::MAX_RECIPIENTS)]; }
        $status=$test?'test':'sending'; $campaign_id=self::save_campaign((int)$artist->ID,$data,$status);
        $sent=0;$failed=0;$skipped=0;
        foreach($students as $student){
            $email=sanitize_email((string)$student['email']);
            if(!$test&&self::is_unsubscribed($email,(int)$artist->ID)){$skipped++;self::record_recipient($campaign_id,$student,'skipped',__('Unsubscribed','elev8-os'));continue;}
            $subject=self::merge((string)$data['subject'],$artist,$student,$data);
            $body=nl2br(esc_html(self::merge(wp_strip_all_tags((string)$data['message']),$artist,$student,$data)));
            $unsub=self::unsubscribe_url($email,(int)$artist->ID);
            $body.='<hr><p style="font-size:12px;color:#666">'.esc_html__('Sent through Elev8 OS on behalf of','elev8-os').' '.esc_html($artist->display_name).'. <a href="'.esc_url($unsub).'">'.esc_html__('Unsubscribe','elev8-os').'</a></p>';
            $headers=['Content-Type: text/html; charset=UTF-8'];
            $ok=wp_mail($email,$subject,$body,$headers);
            if($ok){$sent++;self::record_recipient($campaign_id,$student,'sent','');}else{$failed++;self::record_recipient($campaign_id,$student,'failed',__('wp_mail returned false.','elev8-os'));}
        }
        global $wpdb;$wpdb->update(self::table('campaigns'),['status'=>$test?'test_sent':'sent','recipient_count'=>count($students),'sent_count'=>$sent,'failed_count'=>$failed,'skipped_count'=>$skipped,'sent_at'=>current_time('mysql'),'updated_at'=>current_time('mysql')],['id'=>$campaign_id],['%s','%d','%d','%d','%d','%s','%s'],['%d']);
        return ['ok'=>$failed===0,'campaign_id'=>$campaign_id,'sent'=>$sent,'failed'=>$failed,'skipped'=>$skipped,'message'=>sprintf(__('Sent %1$d, failed %2$d, skipped %3$d.','elev8-os'),$sent,$failed,$skipped)];
    }

    /** @param array<string,mixed> $student @param array<string,mixed> $data */
    private static function merge(string $text,WP_User $artist,array $student,array $data): string {
        $profile=(string)get_user_meta($artist->ID,'elev8_os_public_artist_page_url',true);
        $promo=(string)($data['referral_url']??$data['promoted_url']??'');
        $first=trim(explode(' ',(string)($student['name']??''))[0]??''); if($first===''){$first=__('there','elev8-os');}
        return strtr($text,['{{first_name}}'=>$first,'{{artist_name}}'=>$artist->display_name,'{{artist_profile}}'=>$profile,'{{promotion_link}}'=>$promo]);
    }
    /** @param array<string,mixed> $student */
    private static function record_recipient(int $campaign_id,array $student,string $status,string $error): void { global $wpdb;$wpdb->insert(self::table('campaign_recipients'),['campaign_id'=>$campaign_id,'customer_key'=>(string)($student['customer_key']??''),'email'=>sanitize_email((string)($student['email']??'')),'recipient_name'=>sanitize_text_field((string)($student['name']??'')),'delivery_status'=>$status,'error_message'=>$error,'sent_at'=>$status==='sent'?current_time('mysql'):null],['%d','%s','%s','%s','%s','%s','%s']); }
    private static function unsubscribe_url(string $email,int $artist_user_id): string { $token=hash_hmac('sha256',strtolower($email).'|'.$artist_user_id,wp_salt('auth')); return add_query_arg(['elev8_unsubscribe'=>$token,'email'=>rawurlencode($email),'artist'=>$artist_user_id],home_url('/')); }
    private static function is_unsubscribed(string $email,int $artist_user_id): bool { global $wpdb;return (bool)$wpdb->get_var($wpdb->prepare('SELECT id FROM `'.self::table('email_unsubscribes').'` WHERE email=%s AND (artist_user_id=0 OR artist_user_id=%d) LIMIT 1',strtolower($email),$artist_user_id)); }
    public static function handle_unsubscribe(): void {
        if(empty($_GET['elev8_unsubscribe'])||empty($_GET['email'])){return;}
        $email=sanitize_email(wp_unslash($_GET['email']));$artist=absint($_GET['artist']??0);$token=sanitize_text_field(wp_unslash($_GET['elev8_unsubscribe']));
        $expected=hash_hmac('sha256',strtolower($email).'|'.$artist,wp_salt('auth'));
        if($email===''||!hash_equals($expected,$token)){wp_die(esc_html__('This unsubscribe link is invalid.','elev8-os'));}
        global $wpdb;$wpdb->replace(self::table('email_unsubscribes'),['email'=>strtolower($email),'artist_user_id'=>$artist,'token'=>$token,'unsubscribed_at'=>current_time('mysql')],['%s','%d','%s','%s']);
        wp_die('<h1>'.esc_html__('You are unsubscribed.','elev8-os').'</h1><p>'.esc_html__('You will no longer receive artist marketing emails from this sender.','elev8-os').'</p>',esc_html__('Email preferences updated','elev8-os'),['response'=>200]);
    }
    public static function referral_url(int $artist_user_id,string $url): string { if($url===''){return '';} return add_query_arg(['elev8_ref'=>$artist_user_id,'elev8_source'=>'artist_campaign'],$url); }
    public static function unique_tags(WP_User $artist): array { $r=Elev8_OS_Student_Relationship_Service::get_students($artist);$tags=[];foreach((array)($r['students']??[]) as $s){foreach((array)$s['tags'] as $tag){$tags[(string)$tag]=(string)$tag;}}natcasesort($tags);return array_values($tags); }
    private static function table(string $suffix): string { global $wpdb;return $wpdb->prefix.'elev8_os_'.$suffix; }
}

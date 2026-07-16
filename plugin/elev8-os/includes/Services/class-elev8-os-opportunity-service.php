<?php
if (!defined('ABSPATH')) { exit; }

/**
 * WordPress-owned source of truth for business opportunities and customer demand.
 */
final class Elev8_OS_Opportunity_Service {
    private const DB_VERSION = '1.0.0';
    private const DB_OPTION = 'elev8_os_opportunity_db_version';

    public static function activate(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $opportunities = self::opportunities_table();
        $interest = self::interest_table();

        dbDelta("CREATE TABLE {$opportunities} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            type varchar(40) NOT NULL DEFAULT 'class',
            title varchar(190) NOT NULL,
            category varchar(120) NOT NULL DEFAULT '',
            description text NULL,
            status varchar(40) NOT NULL DEFAULT 'idea',
            teacher_needed tinyint(1) unsigned NOT NULL DEFAULT 0,
            teacher_assigned varchar(190) NOT NULL DEFAULT '',
            teacher_contact varchar(190) NOT NULL DEFAULT '',
            preferred_day varchar(120) NOT NULL DEFAULT '',
            preferred_time varchar(120) NOT NULL DEFAULT '',
            difficulty varchar(80) NOT NULL DEFAULT '',
            supplies_needed text NULL,
            estimated_price decimal(12,2) DEFAULT NULL,
            estimated_duration decimal(8,2) DEFAULT NULL,
            notes longtext NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY type_status (type,status),
            KEY category (category),
            KEY teacher_needed (teacher_needed)
        ) {$charset};");

        dbDelta("CREATE TABLE {$interest} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            opportunity_id bigint(20) unsigned NOT NULL,
            customer_name varchar(190) NOT NULL,
            customer_email varchar(190) NOT NULL DEFAULT '',
            customer_phone varchar(80) NOT NULL DEFAULT '',
            seats_requested smallint(5) unsigned NOT NULL DEFAULT 1,
            preferred_days varchar(190) NOT NULL DEFAULT '',
            preferred_times varchar(190) NOT NULL DEFAULT '',
            notes text NULL,
            source varchar(120) NOT NULL DEFAULT 'admin',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY opportunity_id (opportunity_id),
            KEY customer_email (customer_email),
            KEY created_at (created_at)
        ) {$charset};");

        update_option(self::DB_OPTION, self::DB_VERSION, false);
    }

    public static function maybe_upgrade(): void {
        if ((string) get_option(self::DB_OPTION, '') !== self::DB_VERSION) { self::activate(); }
    }

    public static function opportunities_table(): string { global $wpdb; return $wpdb->prefix . 'elev8_opportunities'; }
    public static function interest_table(): string { global $wpdb; return $wpdb->prefix . 'elev8_opportunity_interest'; }

    public static function statuses(): array {
        return ['idea'=>'Idea','research'=>'Research','teacher-needed'=>'Teacher Needed','recruiting'=>'Recruiting','planning'=>'Planning','scheduled'=>'Scheduled','active'=>'Active','completed'=>'Completed','cancelled'=>'Cancelled','archived'=>'Archived'];
    }

    public static function all(): array {
        global $wpdb;
        $o = self::opportunities_table();
        $i = self::interest_table();
        $sql = "SELECT o.*, COUNT(i.id) AS interested_people, COALESCE(SUM(i.seats_requested),0) AS interested_seats
                FROM {$o} o LEFT JOIN {$i} i ON i.opportunity_id=o.id
                GROUP BY o.id ORDER BY o.updated_at DESC, o.id DESC";
        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    public static function find(int $id): ?array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::opportunities_table() . ' WHERE id=%d', $id), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    public static function interests(int $opportunity_id): array {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . self::interest_table() . ' WHERE opportunity_id=%d ORDER BY created_at DESC', $opportunity_id), ARRAY_A) ?: [];
    }

    public static function save(array $data): int {
        global $wpdb;
        $id = absint($data['id'] ?? 0);
        $price = ($data['estimated_price'] ?? '') === '' ? null : round((float) $data['estimated_price'], 2);
        $duration = ($data['estimated_duration'] ?? '') === '' ? null : round((float) $data['estimated_duration'], 2);
        $now = current_time('mysql');
        $row = [
            'type'=>'class',
            'title'=>sanitize_text_field($data['title'] ?? ''),
            'category'=>sanitize_text_field($data['category'] ?? ''),
            'description'=>sanitize_textarea_field($data['description'] ?? ''),
            'status'=>array_key_exists(sanitize_key($data['status'] ?? ''), self::statuses()) ? sanitize_key($data['status']) : 'idea',
            'teacher_needed'=>empty($data['teacher_needed']) ? 0 : 1,
            'teacher_assigned'=>sanitize_text_field($data['teacher_assigned'] ?? ''),
            'teacher_contact'=>sanitize_text_field($data['teacher_contact'] ?? ''),
            'preferred_day'=>sanitize_text_field($data['preferred_day'] ?? ''),
            'preferred_time'=>sanitize_text_field($data['preferred_time'] ?? ''),
            'difficulty'=>sanitize_text_field($data['difficulty'] ?? ''),
            'supplies_needed'=>sanitize_textarea_field($data['supplies_needed'] ?? ''),
            'estimated_price'=>$price,
            'estimated_duration'=>$duration,
            'notes'=>sanitize_textarea_field($data['notes'] ?? ''),
            'updated_at'=>$now,
        ];
        if ($row['title'] === '') { return 0; }
        if ($id > 0) {
            $wpdb->update(self::opportunities_table(), $row, ['id'=>$id]);
            return $id;
        }
        $row['created_at'] = $now;
        $wpdb->insert(self::opportunities_table(), $row);
        return (int) $wpdb->insert_id;
    }

    public static function add_interest(array $data): int {
        global $wpdb;
        $opportunity_id = absint($data['opportunity_id'] ?? 0);
        if ($opportunity_id <= 0 || !self::find($opportunity_id)) { return 0; }
        $name = sanitize_text_field($data['customer_name'] ?? '');
        if ($name === '') { return 0; }
        $now = current_time('mysql');
        $wpdb->insert(self::interest_table(), [
            'opportunity_id'=>$opportunity_id,
            'customer_name'=>$name,
            'customer_email'=>sanitize_email($data['customer_email'] ?? ''),
            'customer_phone'=>sanitize_text_field($data['customer_phone'] ?? ''),
            'seats_requested'=>max(1, min(50, absint($data['seats_requested'] ?? 1))),
            'preferred_days'=>sanitize_text_field($data['preferred_days'] ?? ''),
            'preferred_times'=>sanitize_text_field($data['preferred_times'] ?? ''),
            'notes'=>sanitize_textarea_field($data['notes'] ?? ''),
            'source'=>sanitize_text_field($data['source'] ?? 'admin'),
            'created_at'=>$now,
            'updated_at'=>$now,
        ]);
        return (int) $wpdb->insert_id;
    }

    public static function delete_interest(int $id): bool {
        global $wpdb;
        return false !== $wpdb->delete(self::interest_table(), ['id'=>$id], ['%d']);
    }

    public static function metrics(): array {
        global $wpdb;
        $o = self::opportunities_table(); $i = self::interest_table();
        $ideas = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$o} WHERE status NOT IN ('cancelled','archived','completed')");
        $people = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$i}");
        $seats = (int) $wpdb->get_var("SELECT COALESCE(SUM(seats_requested),0) FROM {$i}");
        $teacher = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$o} WHERE teacher_needed=1 AND status NOT IN ('cancelled','archived','completed')");
        $missing_price = (int) $wpdb->get_var("SELECT COUNT(DISTINCT o.id) FROM {$o} o INNER JOIN {$i} i ON i.opportunity_id=o.id WHERE o.estimated_price IS NULL");
        $revenue = $missing_price > 0 ? null : (float) $wpdb->get_var("SELECT COALESCE(SUM(i.seats_requested * o.estimated_price),0) FROM {$i} i INNER JOIN {$o} o ON o.id=i.opportunity_id");
        return ['active_ideas'=>$ideas,'people_waiting'=>$people,'seats_requested'=>$seats,'teacher_needed'=>$teacher,'potential_revenue'=>$revenue,'revenue_available'=>$missing_price===0];
    }
}

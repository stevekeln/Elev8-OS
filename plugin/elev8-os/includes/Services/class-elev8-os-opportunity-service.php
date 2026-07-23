<?php
/**
 * Elev8 OS Opportunity Engine service.
 *
 * Owns persistence and trusted calculations for business opportunities.
 */
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Opportunity_Service {
    private const DB_VERSION = '1.1.0';
    private const DB_OPTION = 'elev8_os_opportunity_db_version';

    public static function activate(): void {
        self::create_tables();
        update_option(self::DB_OPTION, self::DB_VERSION, false);
    }

    public static function maybe_upgrade(): void {
        if (get_option(self::DB_OPTION) !== self::DB_VERSION) { self::activate(); }
    }

    private static function opportunities_table(): string { global $wpdb; return $wpdb->prefix . 'elev8_opportunities'; }
    private static function interests_table(): string { global $wpdb; return $wpdb->prefix . 'elev8_opportunity_interest'; }

    private static function create_tables(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $opportunities = self::opportunities_table();
        $interest = self::interests_table();

        dbDelta("CREATE TABLE {$opportunities} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            type varchar(40) NOT NULL DEFAULT 'class',
            title varchar(190) NOT NULL,
            category varchar(120) NOT NULL DEFAULT '',
            description text NULL,
            status varchar(40) NOT NULL DEFAULT 'idea',
            teacher_needed tinyint(1) unsigned NOT NULL DEFAULT 0,
            teacher_id bigint(20) unsigned NOT NULL DEFAULT 0,
            teacher_contact varchar(190) NOT NULL DEFAULT '',
            interview_status varchar(40) NOT NULL DEFAULT '',
            preferred_day varchar(80) NOT NULL DEFAULT '',
            preferred_time varchar(80) NOT NULL DEFAULT '',
            difficulty varchar(40) NOT NULL DEFAULT '',
            supplies_needed text NULL,
            estimated_price decimal(12,2) DEFAULT NULL,
            estimated_duration decimal(8,2) DEFAULT NULL,
            internal_notes text NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY type_status (type,status),
            KEY teacher_needed (teacher_needed),
            KEY category (category)
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
            source varchar(80) NOT NULL DEFAULT 'admin',
            crm_status varchar(40) NOT NULL DEFAULT 'new',
            follow_up_date date DEFAULT NULL,
            last_contacted_at datetime DEFAULT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY opportunity_id (opportunity_id),
            KEY created_at (created_at)
        ) {$charset};");
    }

    /** Backward-compatible service contract for the Class Demand module. */
    public static function save(array $data): int {
        return self::save_opportunity($data);
    }

    public static function find(int $id): ?array {
        return self::get($id);
    }

    /** @return array<string,mixed> */
    public static function metrics(): array {
        $intelligence = self::intelligence();
        $metrics = is_array($intelligence['metrics'] ?? null) ? $intelligence['metrics'] : [];
        $value = static function (string $key, $default = 0) use ($metrics) {
            $metric = $metrics[$key] ?? null;
            return is_array($metric) && array_key_exists('value', $metric) ? $metric['value'] : $default;
        };
        $available = static function (string $key) use ($metrics): bool {
            $metric = $metrics[$key] ?? null;
            return !is_array($metric) || !array_key_exists('available', $metric) || (bool) $metric['available'];
        };
        return [
            'active_ideas' => (int) $value('opportunity_count', 0),
            'people_waiting' => (int) $value('people_waiting', 0),
            'seats_requested' => (int) $value('seats_waiting', 0),
            'teacher_needed' => (int) $value('classes_without_teacher', 0),
            'potential_revenue' => (float) $value('potential_revenue', 0),
            'revenue_available' => $available('potential_revenue'),
        ];
    }

    public static function save_opportunity(array $data): int {
        global $wpdb;
        $id = absint($data['id'] ?? 0);
        $price = ($data['estimated_price'] ?? '') === '' ? null : (float) $data['estimated_price'];
        $duration = ($data['estimated_duration'] ?? '') === '' ? null : (float) $data['estimated_duration'];
        $record = [
            'type' => self::allowed((string) ($data['type'] ?? 'class'), ['class','event','teacher','product','artist','corporate','community'], 'class'),
            'title' => sanitize_text_field((string) ($data['title'] ?? '')),
            'category' => sanitize_text_field((string) ($data['category'] ?? '')),
            'description' => sanitize_textarea_field((string) ($data['description'] ?? '')),
            'status' => self::allowed((string) ($data['status'] ?? 'idea'), self::statuses(), 'idea'),
            'teacher_needed' => empty($data['teacher_needed']) ? 0 : 1,
            'teacher_id' => absint($data['teacher_id'] ?? 0),
            'teacher_contact' => sanitize_text_field((string) ($data['teacher_contact'] ?? '')),
            'interview_status' => sanitize_text_field((string) ($data['interview_status'] ?? '')),
            'preferred_day' => sanitize_text_field((string) ($data['preferred_day'] ?? '')),
            'preferred_time' => sanitize_text_field((string) ($data['preferred_time'] ?? '')),
            'difficulty' => sanitize_text_field((string) ($data['difficulty'] ?? '')),
            'supplies_needed' => sanitize_textarea_field((string) ($data['supplies_needed'] ?? '')),
            'estimated_price' => $price,
            'estimated_duration' => $duration,
            'internal_notes' => sanitize_textarea_field((string) ($data['internal_notes'] ?? '')),
            'updated_at' => current_time('mysql'),
        ];
        if ($record['title'] === '') { return 0; }
        if ($id > 0) {
            $wpdb->update(self::opportunities_table(), $record, ['id' => $id]);
            return $id;
        }
        $record['created_at'] = current_time('mysql');
        $wpdb->insert(self::opportunities_table(), $record);
        return (int) $wpdb->insert_id;
    }

    public static function delete_opportunity(int $id): bool {
        global $wpdb;
        if ($id <= 0 || !self::get($id)) { return false; }
        $wpdb->delete(self::interests_table(), ['opportunity_id' => $id], ['%d']);
        return false !== $wpdb->delete(self::opportunities_table(), ['id' => $id], ['%d']);
    }

    public static function delete_interest(int $id): bool {
        global $wpdb;
        if ($id <= 0) { return false; }
        return false !== $wpdb->delete(self::interests_table(), ['id' => $id], ['%d']);
    }

    public static function add_interest(array $data): int {
        global $wpdb;
        $opportunity_id = absint($data['opportunity_id'] ?? 0);
        $name = sanitize_text_field((string) ($data['customer_name'] ?? ''));
        if ($opportunity_id <= 0 || $name === '' || !self::get($opportunity_id)) { return 0; }
        $wpdb->insert(self::interests_table(), [
            'opportunity_id' => $opportunity_id,
            'customer_name' => $name,
            'customer_email' => sanitize_email((string) ($data['customer_email'] ?? '')),
            'customer_phone' => sanitize_text_field((string) ($data['customer_phone'] ?? '')),
            'seats_requested' => max(1, absint($data['seats_requested'] ?? 1)),
            'preferred_days' => sanitize_text_field((string) ($data['preferred_days'] ?? '')),
            'preferred_times' => sanitize_text_field((string) ($data['preferred_times'] ?? '')),
            'notes' => sanitize_textarea_field((string) ($data['notes'] ?? '')),
            'source' => sanitize_text_field((string) ($data['source'] ?? 'admin')),
            'crm_status' => self::allowed((string) ($data['crm_status'] ?? 'new'), self::interest_statuses(), 'new'),
            'follow_up_date' => self::sanitize_date((string) ($data['follow_up_date'] ?? '')),
            'last_contacted_at' => null,
            'created_at' => current_time('mysql'),
        ]);
        return (int) $wpdb->insert_id;
    }


    public static function update_interest(array $data): bool {
        global $wpdb;
        $id = absint($data['interest_id'] ?? 0);
        if ($id <= 0) { return false; }
        $record = [
            'crm_status' => self::allowed((string) ($data['crm_status'] ?? 'new'), self::interest_statuses(), 'new'),
            'follow_up_date' => self::sanitize_date((string) ($data['follow_up_date'] ?? '')),
            'notes' => sanitize_textarea_field((string) ($data['notes'] ?? '')),
        ];
        if (!empty($data['mark_contacted'])) {
            $record['last_contacted_at'] = current_time('mysql');
        }
        return false !== $wpdb->update(self::interests_table(), $record, ['id' => $id]);
    }

    public static function get(int $id): ?array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::opportunities_table() . ' WHERE id = %d', $id), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    public static function all(): array {
        global $wpdb;
        $sql = 'SELECT o.*, COALESCE(i.people_waiting,0) people_waiting, COALESCE(i.seats_waiting,0) seats_waiting
                FROM ' . self::opportunities_table() . ' o
                LEFT JOIN (SELECT opportunity_id, COUNT(*) people_waiting, SUM(seats_requested) seats_waiting FROM ' . self::interests_table() . ' GROUP BY opportunity_id) i ON i.opportunity_id=o.id
                ORDER BY seats_waiting DESC, o.updated_at DESC';
        $rows = $wpdb->get_results($sql, ARRAY_A);
        return is_array($rows) ? $rows : [];
    }

    public static function get_interest(int $id): ?array {
        global $wpdb;
        if ($id <= 0) { return null; }
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::interests_table() . ' WHERE id=%d', $id), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    public static function interests(int $opportunity_id): array {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . self::interests_table() . ' WHERE opportunity_id=%d ORDER BY created_at DESC', $opportunity_id), ARRAY_A);
        return is_array($rows) ? $rows : [];
    }

    public static function intelligence(): array {
        $items = self::all();
        $people = 0; $seats = 0; $potential = 0.0; $priced = true; $teacher_needed = 0;
        foreach ($items as &$item) {
            $item['people_waiting'] = (int) $item['people_waiting'];
            $item['seats_waiting'] = (int) $item['seats_waiting'];
            $people += $item['people_waiting']; $seats += $item['seats_waiting'];
            if (!empty($item['teacher_needed']) && empty($item['teacher_id'])) { $teacher_needed++; }
            if (($item['estimated_price'] === null || $item['estimated_price'] === '') && $item['seats_waiting'] > 0) { $priced = false; $item['potential_revenue'] = null; }
            elseif ($item['estimated_price'] === null || $item['estimated_price'] === '') { $item['potential_revenue'] = 0.0; }
            else { $item['potential_revenue'] = (float) $item['estimated_price'] * $item['seats_waiting']; $potential += $item['potential_revenue']; }
            $item['priority_score'] = $item['seats_waiting'];
        }
        unset($item);
        return [
            'opportunities' => $items,
            'metrics' => [
                'opportunity_count' => self::metric(count($items), true, 'number', __('Elev8 OS opportunity records.', 'elev8-os')),
                'people_waiting' => self::metric($people, true, 'number', __('Unique interest records across all opportunities.', 'elev8-os')),
                'seats_waiting' => self::metric($seats, true, 'number', __('Total requested seats across all opportunities.', 'elev8-os')),
                'classes_without_teacher' => self::metric($teacher_needed, true, 'number', __('Opportunities marked teacher needed without an assigned teacher ID.', 'elev8-os')),
                'potential_revenue' => self::metric($priced ? $potential : null, $priced, 'currency', $priced ? __('Estimated price multiplied by requested seats.', 'elev8-os') : __('Unavailable because one or more opportunities with interest do not have a verified estimated price.', 'elev8-os')),
            ],
        ];
    }

    public static function statuses(): array { return ['idea','research','teacher_needed','recruiting','planning','scheduled','active','completed','archived','cancelled']; }
    public static function interest_statuses(): array { return ['new','contacted','qualified','scheduled','converted','not_interested']; }
    private static function sanitize_date(string $value): ?string {
        $value = trim($value);
        if ($value === '') { return null; }
        $date = DateTime::createFromFormat('Y-m-d', $value);
        return ($date && $date->format('Y-m-d') === $value) ? $value : null;
    }
    private static function allowed(string $value, array $allowed, string $default): string { return in_array($value, $allowed, true) ? $value : $default; }
    private static function metric($value, bool $available, string $format, string $diagnostic): array { return ['available'=>$available,'value'=>$value,'format'=>$format,'confidence'=>$available?'high':'unavailable','diagnostic'=>$diagnostic]; }
}

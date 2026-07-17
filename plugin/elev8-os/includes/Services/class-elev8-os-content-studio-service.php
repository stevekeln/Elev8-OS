<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Content Studio data layer.
 *
 * Elev8 OS owns reusable content assets. Delivery channels consume these
 * records later; this service intentionally contains no email/social logic.
 */
final class Elev8_OS_Content_Studio_Service {
    private const DB_VERSION = '1.1.0';
    private const DB_OPTION = 'elev8_os_content_studio_db_version';

    public static function init(): void {
        add_action('admin_init', [__CLASS__, 'maybe_upgrade']);
    }

    public static function activate(): void {
        self::install_schema();
        self::seed_defaults();
    }

    public static function maybe_upgrade(): void {
        if (!current_user_can('manage_options')) { return; }
        if ((string) get_option(self::DB_OPTION, '') !== self::DB_VERSION) {
            self::install_schema();
            self::seed_defaults();
        }
    }

    private static function install_schema(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $categories = self::table('content_categories');
        $templates = self::table('content_templates');
        $campaigns = self::table('content_campaigns');
        $revisions = self::table('content_template_revisions');

        dbDelta("CREATE TABLE {$categories} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            owner_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            name varchar(120) NOT NULL,
            slug varchar(140) NOT NULL,
            description text NULL,
            sort_order int(11) NOT NULL DEFAULT 0,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY owner_slug (owner_user_id,slug),
            KEY owner_active (owner_user_id,is_active)
        ) {$charset};");

        dbDelta("CREATE TABLE {$templates} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            owner_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            source_template_id bigint(20) unsigned NOT NULL DEFAULT 0,
            category_id bigint(20) unsigned NOT NULL DEFAULT 0,
            name varchar(180) NOT NULL,
            slug varchar(200) NOT NULL,
            description text NULL,
            subject varchar(255) NULL,
            headline varchar(255) NULL,
            body longtext NULL,
            cta_label varchar(120) NULL,
            cta_url text NULL,
            featured_image_id bigint(20) unsigned NOT NULL DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'active',
            is_favorite tinyint(1) NOT NULL DEFAULT 0,
            usage_count bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY owner_status (owner_user_id,status),
            KEY category_id (category_id),
            KEY source_template_id (source_template_id),
            KEY owner_name (owner_user_id,name)
        ) {$charset};");


        dbDelta("CREATE TABLE {$campaigns} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            owner_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            template_id bigint(20) unsigned NOT NULL DEFAULT 0,
            goal varchar(60) NOT NULL DEFAULT 'custom',
            audience_key varchar(60) NOT NULL DEFAULT 'all_students',
            title varchar(180) NOT NULL,
            subject varchar(255) NULL,
            headline varchar(255) NULL,
            body longtext NULL,
            cta_label varchar(120) NULL,
            cta_url text NULL,
            include_artist_profile tinyint(1) NOT NULL DEFAULT 1,
            include_upcoming_classes tinyint(1) NOT NULL DEFAULT 1,
            include_events tinyint(1) NOT NULL DEFAULT 1,
            include_referral tinyint(1) NOT NULL DEFAULT 1,
            status varchar(20) NOT NULL DEFAULT 'draft',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY owner_status (owner_user_id,status),
            KEY template_id (template_id)
        ) {$charset};");

        dbDelta("CREATE TABLE {$revisions} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            template_id bigint(20) unsigned NOT NULL,
            owner_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            version_number int unsigned NOT NULL DEFAULT 1,
            snapshot longtext NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY template_version (template_id,version_number),
            KEY owner_created (owner_user_id,created_at)
        ) {$charset};");

        update_option(self::DB_OPTION, self::DB_VERSION, false);
    }

    private static function seed_defaults(): void {
        $category_names = [
            'Classes', 'Artwork', 'Events', 'Follow-up', 'Sales',
            'Newsletters', 'Holidays', 'Thank You', 'Referrals',
        ];
        foreach ($category_names as $index => $name) {
            self::ensure_category(0, $name, '', ($index + 1) * 10);
        }

        if (self::count_templates(0) > 0) { return; }
        $seeds = [
            ['New Class Announcement', 'Classes', 'A new class from {{artist_name}}', 'Come create with me!', "Hi {{student_first_name}},\n\nI have a new class coming up and would love to create with you.\n\n{{class_name}}\n{{class_date}}\n\n{{promotion_link}}", 'Reserve My Seat'],
            ['Thanks for Attending', 'Thank You', 'Thank you for creating with me', 'Thank you!', "Hi {{student_first_name}},\n\nThank you for joining my class. I loved creating with you and hope to see you again soon.\n\n{{artist_name}}", 'See More Classes'],
            ['Come Back Soon', 'Follow-up', 'Let’s create together again', 'Ready for your next project?', "Hi {{student_first_name}},\n\nIt has been a little while since we created together. I have new experiences available and would love to see you again.\n\n{{promotion_link}}", 'View Upcoming Classes'],
            ['Art Walk Invitation', 'Events', 'Join us at the next Art Walk', 'Art, music, community', "Hi {{student_first_name}},\n\nThe next Elev8 Art Walk is coming up. Come see local artists, live demonstrations, music, vendors, and more.\n\n{{art_walk_date}}\n{{promotion_link}}", 'Art Walk Details'],
            ['New Artwork Available', 'Artwork', 'New artwork from {{artist_name}}', 'See what I just created', "Hi {{student_first_name}},\n\nI just added a new piece and wanted you to be among the first to see it.\n\n{{promotion_link}}", 'View Artwork'],
            ['Promote Another Artist', 'Referrals', 'A class I think you will enjoy', 'Something special from another artist', "Hi {{student_first_name}},\n\nI found another Elev8 artist experience that I think you may enjoy.\n\n{{promotion_link}}\n\nThis link lets Elev8 know I introduced you.", 'View Experience'],
        ];
        foreach ($seeds as $seed) {
            $category = self::get_category_by_slug(0, sanitize_title($seed[1]));
            self::save_template(0, [
                'name' => $seed[0], 'category_id' => (int) ($category['id'] ?? 0),
                'subject' => $seed[2], 'headline' => $seed[3], 'body' => $seed[4],
                'cta_label' => $seed[5], 'status' => 'active',
                'description' => 'Elev8 OS shared starter template.',
            ]);
        }
    }

    public static function ensure_category(int $owner_user_id, string $name, string $description = '', int $sort_order = 0): int {
        global $wpdb;
        $name = sanitize_text_field($name);
        if ($name === '') { return 0; }
        $slug = sanitize_title($name);
        $existing = self::get_category_by_slug($owner_user_id, $slug);
        if ($existing) { return (int) $existing['id']; }
        $now = current_time('mysql');
        $wpdb->insert(self::table('content_categories'), [
            'owner_user_id' => $owner_user_id, 'name' => $name, 'slug' => $slug,
            'description' => sanitize_textarea_field($description), 'sort_order' => $sort_order,
            'is_active' => 1, 'created_at' => $now, 'updated_at' => $now,
        ], ['%d','%s','%s','%s','%d','%d','%s','%s']);
        return (int) $wpdb->insert_id;
    }

    /** @return array<string,mixed>|null */
    public static function get_category_by_slug(int $owner_user_id, string $slug): ?array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM `' . self::table('content_categories') . '` WHERE owner_user_id=%d AND slug=%s LIMIT 1',
            $owner_user_id, $slug
        ), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    /** @return array<int,array<string,mixed>> */
    public static function categories(int $owner_user_id, bool $include_shared = true): array {
        global $wpdb;
        if ($include_shared && $owner_user_id > 0) {
            return (array) $wpdb->get_results($wpdb->prepare(
                'SELECT * FROM `' . self::table('content_categories') . '` WHERE is_active=1 AND owner_user_id IN (0,%d) ORDER BY owner_user_id ASC, sort_order ASC, name ASC',
                $owner_user_id
            ), ARRAY_A);
        }
        return (array) $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM `' . self::table('content_categories') . '` WHERE is_active=1 AND owner_user_id=%d ORDER BY sort_order ASC, name ASC',
            $owner_user_id
        ), ARRAY_A);
    }

    /** @param array<string,mixed> $data */
    public static function save_template(int $owner_user_id, array $data, int $template_id = 0): int {
        global $wpdb;
        $name = sanitize_text_field((string) ($data['name'] ?? ''));
        if ($name === '') { return 0; }
        $allowed_status = ['active', 'draft', 'archived'];
        $status = sanitize_key((string) ($data['status'] ?? 'active'));
        if (!in_array($status, $allowed_status, true)) { $status = 'draft'; }
        $record = [
            'owner_user_id' => $owner_user_id,
            'source_template_id' => absint($data['source_template_id'] ?? 0),
            'category_id' => absint($data['category_id'] ?? 0),
            'name' => $name,
            'slug' => sanitize_title($name),
            'description' => sanitize_textarea_field((string) ($data['description'] ?? '')),
            'subject' => sanitize_text_field((string) ($data['subject'] ?? '')),
            'headline' => sanitize_text_field((string) ($data['headline'] ?? '')),
            'body' => wp_kses_post((string) ($data['body'] ?? '')),
            'cta_label' => sanitize_text_field((string) ($data['cta_label'] ?? '')),
            'cta_url' => esc_url_raw((string) ($data['cta_url'] ?? '')),
            'featured_image_id' => absint($data['featured_image_id'] ?? 0),
            'status' => $status,
            'is_favorite' => empty($data['is_favorite']) ? 0 : 1,
            'updated_at' => current_time('mysql'),
        ];
        if ($template_id > 0) {
            self::capture_revision($template_id, $owner_user_id);
            unset($record['owner_user_id']);
            $wpdb->update(self::table('content_templates'), $record, ['id' => $template_id, 'owner_user_id' => $owner_user_id]);
            return $template_id;
        }
        $record['created_at'] = current_time('mysql');
        $wpdb->insert(self::table('content_templates'), $record);
        return (int) $wpdb->insert_id;
    }

    /** @return array<string,mixed>|null */
    public static function get_template(int $template_id, int $owner_user_id, bool $allow_shared = false): ?array {
        global $wpdb;
        $owners = $allow_shared && $owner_user_id > 0 ? [0, $owner_user_id] : [$owner_user_id];
        $placeholders = implode(',', array_fill(0, count($owners), '%d'));
        $params = array_merge([$template_id], $owners);
        $sql = $wpdb->prepare('SELECT t.*, c.name AS category_name FROM `' . self::table('content_templates') . '` t LEFT JOIN `' . self::table('content_categories') . '` c ON c.id=t.category_id WHERE t.id=%d AND t.owner_user_id IN (' . $placeholders . ') LIMIT 1', $params);
        $row = $wpdb->get_row($sql, ARRAY_A);
        return is_array($row) ? $row : null;
    }

    /** @param array<string,mixed> $filters @return array<int,array<string,mixed>> */
    public static function templates(int $owner_user_id, array $filters = [], bool $include_shared = true): array {
        global $wpdb;
        $where = [];
        $params = [];
        if ($include_shared && $owner_user_id > 0) {
            $where[] = 't.owner_user_id IN (0,%d)'; $params[] = $owner_user_id;
        } else {
            $where[] = 't.owner_user_id=%d'; $params[] = $owner_user_id;
        }
        $status = sanitize_key((string) ($filters['status'] ?? ''));
        if ($status !== '' && in_array($status, ['active','draft','archived'], true)) { $where[] = 't.status=%s'; $params[] = $status; }
        $category_id = absint($filters['category_id'] ?? 0);
        if ($category_id > 0) { $where[] = 't.category_id=%d'; $params[] = $category_id; }
        $search = sanitize_text_field((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = '(t.name LIKE %s OR t.description LIKE %s OR t.subject LIKE %s OR t.headline LIKE %s OR t.body LIKE %s)';
            array_push($params, $like, $like, $like, $like, $like);
        }
        $sql = 'SELECT t.*, c.name AS category_name FROM `' . self::table('content_templates') . '` t LEFT JOIN `' . self::table('content_categories') . '` c ON c.id=t.category_id WHERE ' . implode(' AND ', $where) . ' ORDER BY t.is_favorite DESC, t.owner_user_id ASC, t.updated_at DESC, t.name ASC';
        return (array) $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
    }

    public static function count_templates(int $owner_user_id): int {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM `' . self::table('content_templates') . '` WHERE owner_user_id=%d', $owner_user_id));
    }

    public static function duplicate_template(int $template_id, int $source_owner_id, int $new_owner_id): int {
        $source = self::get_template($template_id, $source_owner_id, true);
        if (!$source) { return 0; }
        return self::save_template($new_owner_id, [
            'source_template_id' => $template_id,
            'category_id' => (int) $source['category_id'],
            'name' => sprintf(__('%s Copy', 'elev8-os'), (string) $source['name']),
            'description' => (string) $source['description'], 'subject' => (string) $source['subject'],
            'headline' => (string) $source['headline'], 'body' => (string) $source['body'],
            'cta_label' => (string) $source['cta_label'], 'cta_url' => (string) $source['cta_url'],
            'featured_image_id' => (int) $source['featured_image_id'], 'status' => 'draft',
        ]);
    }

    public static function set_status(int $template_id, int $owner_user_id, string $status): bool {
        global $wpdb;
        if (!in_array($status, ['active','draft','archived'], true)) { return false; }
        return false !== $wpdb->update(self::table('content_templates'), ['status' => $status, 'updated_at' => current_time('mysql')], ['id' => $template_id, 'owner_user_id' => $owner_user_id], ['%s','%s'], ['%d','%d']);
    }

    public static function delete_template(int $template_id, int $owner_user_id): bool {
        global $wpdb;
        return false !== $wpdb->delete(self::table('content_templates'), ['id' => $template_id, 'owner_user_id' => $owner_user_id], ['%d','%d']);
    }


    /** @return array<string,array<string,string>> */
    public static function campaign_goals(): array {
        return [
            'fill_class' => ['label'=>__('Fill a Class','elev8-os'),'description'=>__('Promote an upcoming class and make reserving a seat easy.','elev8-os'),'category'=>'classes'],
            'sell_artwork' => ['label'=>__('Sell Artwork','elev8-os'),'description'=>__('Share a finished piece and guide customers to the artwork page.','elev8-os'),'category'=>'artwork'],
            'announce_event' => ['label'=>__('Announce an Event','elev8-os'),'description'=>__('Invite your community to an Elev8 event or artist appearance.','elev8-os'),'category'=>'events'],
            'bring_back' => ['label'=>__('Bring Customers Back','elev8-os'),'description'=>__('Reconnect with people who have not attended recently.','elev8-os'),'category'=>'follow-up'],
            'introduce_artist' => ['label'=>__('Introduce Myself','elev8-os'),'description'=>__('Tell your story and help customers discover your work.','elev8-os'),'category'=>'newsletters'],
            'referral' => ['label'=>__('Recommend Another Artist','elev8-os'),'description'=>__('Share another Elev8 experience with automatic referral tracking.','elev8-os'),'category'=>'referrals'],
            'custom' => ['label'=>__('Create Something Else','elev8-os'),'description'=>__('Start from a reusable template and shape it for your goal.','elev8-os'),'category'=>''],
        ];
    }

    /** @return array<string,string> */
    public static function audiences(): array {
        return [
            'all_students'=>__('Everyone I have taught','elev8-os'),
            'past_students'=>__('People who attended before','elev8-os'),
            'upcoming_students'=>__('People already coming to a class','elev8-os'),
            'new_students'=>__('New students','elev8-os'),
            'returning_students'=>__('Returning students','elev8-os'),
            'needs_follow_up'=>__('People I have not seen lately','elev8-os'),
        ];
    }

    /** @param array<string,mixed> $data */
    public static function save_campaign(int $owner_user_id, array $data, int $campaign_id = 0): int {
        global $wpdb;
        $goals = self::campaign_goals();
        $audiences = self::audiences();
        $goal = sanitize_key((string)($data['goal'] ?? 'custom'));
        $audience = sanitize_key((string)($data['audience_key'] ?? 'all_students'));
        if (!isset($goals[$goal])) { $goal = 'custom'; }
        if (!isset($audiences[$audience])) { $audience = 'all_students'; }
        $title = sanitize_text_field((string)($data['title'] ?? ''));
        if ($title === '') { $title = $goals[$goal]['label']; }
        $record = [
            'owner_user_id'=>$owner_user_id,
            'template_id'=>absint($data['template_id'] ?? 0),
            'goal'=>$goal,
            'audience_key'=>$audience,
            'title'=>$title,
            'subject'=>sanitize_text_field((string)($data['subject'] ?? '')),
            'headline'=>sanitize_text_field((string)($data['headline'] ?? '')),
            'body'=>wp_kses_post((string)($data['body'] ?? '')),
            'cta_label'=>sanitize_text_field((string)($data['cta_label'] ?? '')),
            'cta_url'=>esc_url_raw((string)($data['cta_url'] ?? '')),
            'include_artist_profile'=>empty($data['include_artist_profile'])?0:1,
            'include_upcoming_classes'=>empty($data['include_upcoming_classes'])?0:1,
            'include_events'=>empty($data['include_events'])?0:1,
            'include_referral'=>empty($data['include_referral'])?0:1,
            'status'=>'draft',
            'updated_at'=>current_time('mysql'),
        ];
        if ($campaign_id > 0) {
            unset($record['owner_user_id']);
            $wpdb->update(self::table('content_campaigns'),$record,['id'=>$campaign_id,'owner_user_id'=>$owner_user_id]);
            return $campaign_id;
        }
        $record['created_at']=current_time('mysql');
        $wpdb->insert(self::table('content_campaigns'),$record);
        return (int)$wpdb->insert_id;
    }

    /** @return array<string,mixed>|null */
    public static function get_campaign(int $campaign_id, int $owner_user_id): ?array {
        global $wpdb;
        $row=$wpdb->get_row($wpdb->prepare('SELECT * FROM `'.self::table('content_campaigns').'` WHERE id=%d AND owner_user_id=%d LIMIT 1',$campaign_id,$owner_user_id),ARRAY_A);
        return is_array($row)?$row:null;
    }

    /** @return array<int,array<string,mixed>> */
    public static function campaigns(int $owner_user_id, int $limit=12): array {
        global $wpdb;
        return (array)$wpdb->get_results($wpdb->prepare('SELECT * FROM `'.self::table('content_campaigns').'` WHERE owner_user_id=%d ORDER BY updated_at DESC LIMIT %d',$owner_user_id,max(1,$limit)),ARRAY_A);
    }

    /** @return array<int,array<string,mixed>> */
    public static function revisions(int $template_id, int $owner_user_id, int $limit=10): array {
        global $wpdb;
        return (array)$wpdb->get_results($wpdb->prepare('SELECT * FROM `'.self::table('content_template_revisions').'` WHERE template_id=%d AND owner_user_id=%d ORDER BY version_number DESC LIMIT %d',$template_id,$owner_user_id,max(1,$limit)),ARRAY_A);
    }

    private static function capture_revision(int $template_id, int $owner_user_id): void {
        global $wpdb;
        $current=self::get_template($template_id,$owner_user_id,false);
        if (!$current) { return; }
        $next=1+(int)$wpdb->get_var($wpdb->prepare('SELECT MAX(version_number) FROM `'.self::table('content_template_revisions').'` WHERE template_id=%d',$template_id));
        $wpdb->insert(self::table('content_template_revisions'),[
            'template_id'=>$template_id,
            'owner_user_id'=>$owner_user_id,
            'version_number'=>$next,
            'snapshot'=>wp_json_encode($current),
            'created_at'=>current_time('mysql'),
        ],['%d','%d','%d','%s','%s']);
    }

    private static function table(string $suffix): string {
        global $wpdb;
        return $wpdb->prefix . 'elev8_os_' . $suffix;
    }
}

<?php
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Waitlist_Module {
    private const DB_VERSION = '1.1.0';
    private const DB_OPTION = 'elev8_os_waitlist_db_version';
    private const SHORTCODE = 'elev8_artist_waitlist';
    private const ADMIN_SLUG = 'elev8-waitlists';
    private const EMPLOYEE_META = 'elev8_os_amelia_employee_id';

    public static function init(): void {
        add_shortcode(self::SHORTCODE, [__CLASS__, 'shortcode']);
        add_action('admin_menu', [__CLASS__, 'admin_menu'], 40);
        add_action('admin_init', [__CLASS__, 'maybe_upgrade']);
        add_action('admin_post_elev8_os_waitlist_save', [__CLASS__, 'handle_save']);
        add_action('admin_post_elev8_os_waitlist_status', [__CLASS__, 'handle_status']);
        add_action('admin_post_elev8_os_waitlist_delete', [__CLASS__, 'handle_delete']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    public static function status(): string { return 'active'; }

    public static function activate(): void {
        self::create_table();
        update_option(self::DB_OPTION, self::DB_VERSION, false);
    }

    public static function maybe_upgrade(): void {
        if (get_option(self::DB_OPTION) !== self::DB_VERSION) {
            self::activate();
        }
    }

    private static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'elev8_waitlist';
    }

    private static function create_table(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $table = self::table();
        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            appointment_id bigint(20) unsigned NOT NULL DEFAULT 0,
            employee_id bigint(20) unsigned NOT NULL DEFAULT 0,
            service_id bigint(20) unsigned NOT NULL DEFAULT 0,
            class_label varchar(190) NOT NULL DEFAULT '',
            class_date date DEFAULT NULL,
            class_time time DEFAULT NULL,
            customer_name varchar(190) NOT NULL,
            customer_email varchar(190) NOT NULL DEFAULT '',
            customer_phone varchar(80) NOT NULL DEFAULT '',
            seats_requested smallint(5) unsigned NOT NULL DEFAULT 1,
            notes text NULL,
            status varchar(30) NOT NULL DEFAULT 'waiting',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY appointment_id (appointment_id),
            KEY employee_id (employee_id),
            KEY service_date (service_id,class_date),
            KEY status (status)
        ) {$charset};";
        dbDelta($sql);
    }

    public static function admin_menu(): void {
        add_submenu_page('elev8-os', __('Waitlists', 'elev8-os'), __('Waitlists', 'elev8-os'), 'manage_options', self::ADMIN_SLUG, [__CLASS__, 'render_admin']);
    }

    public static function enqueue_assets(): void {
        if (!is_user_logged_in() || !class_exists('Elev8_OS_Portal_Page_Manager') || !Elev8_OS_Portal_Page_Manager::is_current_page('waitlist')) { return; }
        wp_enqueue_style('elev8-os-artist-portal', ELEV8_OS_URL . 'assets/css/artist-portal.css', [], ELEV8_OS_VERSION);
        wp_enqueue_style('elev8-os-waitlist', ELEV8_OS_URL . 'assets/css/artist-waitlist.css', [], ELEV8_OS_VERSION);
    }

    public static function shortcode(): string {
        if (!is_user_logged_in()) {
            return '<div class="elev8-dashboard-login"><p>' . esc_html__('Please log in to view your waitlist.', 'elev8-os') . '</p></div>';
        }
        $user = wp_get_current_user();
        $employee_id = absint(get_user_meta($user->ID, self::EMPLOYEE_META, true));
        if ($employee_id <= 0 && !current_user_can('manage_options')) {
            return '<div class="elev8-dashboard-warning"><p><strong>' . esc_html__('Your account is not connected to an Amelia artist.', 'elev8-os') . '</strong></p></div>';
        }
        ob_start();
        echo '<div class="elev8-artist-dashboard elev8-waitlist">';
        if (class_exists('Elev8_OS_Artist_Portal_Module')) { Elev8_OS_Artist_Portal_Module::render_navigation('waitlist'); }
        self::render_content($employee_id, false);
        echo '</div>';
        return (string) ob_get_clean();
    }

    public static function render_admin(): void {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('You do not have permission to view this page.', 'elev8-os')); }
        $employees = self::employees();
        $employee_id = absint($_GET['employee_id'] ?? 0);
        if ($employee_id <= 0 && $employees) { $employee_id = (int) $employees[0]['id']; }

        echo '<div class="wrap"><h1>' . esc_html__('Waitlists', 'elev8-os') . '</h1><p>' . esc_html__('Choose an artist and upcoming Amelia class. Elev8 OS fills the verified class information automatically.', 'elev8-os') . '</p>';
        echo '<form method="get" class="elev8-waitlist-artist-filter"><input type="hidden" name="page" value="' . esc_attr(self::ADMIN_SLUG) . '"><label for="elev8-waitlist-employee"><strong>' . esc_html__('Artist', 'elev8-os') . '</strong></label><select id="elev8-waitlist-employee" name="employee_id">';
        foreach ($employees as $employee) {
            echo '<option value="' . esc_attr((string) $employee['id']) . '" ' . selected($employee_id, (int) $employee['id'], false) . '>' . esc_html($employee['name']) . '</option>';
        }
        echo '</select><button class="button" type="submit">' . esc_html__('View classes', 'elev8-os') . '</button></form>';
        self::render_content($employee_id, true);
        echo '</div>';
    }

    private static function render_content(int $employee_id, bool $admin): void {
        $entries = self::entries($employee_id);
        $classes = $employee_id > 0 ? self::upcoming_classes($employee_id) : [];
        $redirect = $admin
            ? add_query_arg(['page' => self::ADMIN_SLUG, 'employee_id' => $employee_id], admin_url('admin.php'))
            : Elev8_OS_Portal_Page_Manager::get_url('waitlist');
        if (isset($_GET['elev8_waitlist_saved'])) { echo '<div class="notice notice-success"><p>' . esc_html__('Waitlist updated.', 'elev8-os') . '</p></div>'; }
        ?>
        <header class="elev8-dashboard-header"><div><p class="elev8-eyebrow"><?php esc_html_e('Artist Portal', 'elev8-os'); ?></p><h1><?php esc_html_e('Waitlist', 'elev8-os'); ?></h1><p><?php esc_html_e('Add customers to a verified upcoming Amelia class without retyping the class details.', 'elev8-os'); ?></p></div><span class="elev8-dashboard-badge"><?php esc_html_e('Amelia integrated', 'elev8-os'); ?></span></header>
        <section class="elev8-waitlist-panel">
          <h2><?php esc_html_e('Add a customer', 'elev8-os'); ?></h2>
          <?php if ($employee_id <= 0) : ?>
            <div class="notice notice-warning inline"><p><?php esc_html_e('Select an artist before adding a customer.', 'elev8-os'); ?></p></div>
          <?php elseif (!$classes) : ?>
            <div class="notice notice-warning inline"><p><?php esc_html_e('No future Amelia appointment records were found for this artist. Create or schedule the class in Amelia first.', 'elev8-os'); ?></p></div>
          <?php else : ?>
          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="elev8-waitlist-form">
            <input type="hidden" name="action" value="elev8_os_waitlist_save"><input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect); ?>"><input type="hidden" name="employee_id" value="<?php echo esc_attr((string) $employee_id); ?>">
            <?php wp_nonce_field('elev8_os_waitlist_save'); ?>
            <label class="elev8-waitlist-class-select"><?php esc_html_e('Upcoming class', 'elev8-os'); ?><select required name="appointment_id"><option value=""><?php esc_html_e('Choose a class date', 'elev8-os'); ?></option><?php foreach ($classes as $class) : ?><option value="<?php echo esc_attr((string) $class['appointment_id']); ?>"><?php echo esc_html(self::class_option_label($class)); ?></option><?php endforeach; ?></select></label>
            <label><?php esc_html_e('Customer name', 'elev8-os'); ?><input required type="text" name="customer_name" maxlength="190"></label>
            <label><?php esc_html_e('Email', 'elev8-os'); ?><input type="email" name="customer_email" maxlength="190"></label>
            <label><?php esc_html_e('Phone', 'elev8-os'); ?><input type="text" name="customer_phone" maxlength="80"></label>
            <label><?php esc_html_e('Seats requested', 'elev8-os'); ?><input type="number" min="1" max="50" name="seats_requested" value="1"></label>
            <label class="elev8-waitlist-notes"><?php esc_html_e('Notes', 'elev8-os'); ?><textarea name="notes" rows="3"></textarea></label>
            <div class="elev8-waitlist-submit"><button class="button button-primary" type="submit"><?php esc_html_e('Add to Waitlist', 'elev8-os'); ?></button></div>
          </form>
          <?php endif; ?>
        </section>
        <section class="elev8-waitlist-panel"><h2><?php esc_html_e('Current entries', 'elev8-os'); ?></h2>
        <?php if (!$entries) : ?><p><?php esc_html_e('No waitlist entries yet.', 'elev8-os'); ?></p><?php else : ?>
        <div class="elev8-waitlist-table-wrap"><table class="widefat striped elev8-waitlist-table"><thead><tr><th><?php esc_html_e('Customer', 'elev8-os'); ?></th><th><?php esc_html_e('Class', 'elev8-os'); ?></th><th><?php esc_html_e('Seats', 'elev8-os'); ?></th><th><?php esc_html_e('Status', 'elev8-os'); ?></th><th><?php esc_html_e('Actions', 'elev8-os'); ?></th></tr></thead><tbody>
        <?php foreach ($entries as $entry) : ?><tr><td><strong><?php echo esc_html($entry->customer_name); ?></strong><br><small><?php echo esc_html(trim($entry->customer_email . ' ' . $entry->customer_phone)); ?></small></td><td><?php echo esc_html($entry->class_label); ?><br><small><?php echo esc_html(self::format_occurrence($entry)); ?></small></td><td><?php echo esc_html((string) $entry->seats_requested); ?></td><td><?php echo esc_html(ucfirst($entry->status)); ?></td><td><form class="elev8-inline-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="elev8_os_waitlist_status"><input type="hidden" name="id" value="<?php echo esc_attr((string) $entry->id); ?>"><input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect); ?>"><?php wp_nonce_field('elev8_os_waitlist_status_' . $entry->id); ?><select name="status"><?php foreach (self::statuses() as $status) : ?><option value="<?php echo esc_attr($status); ?>" <?php selected($entry->status,$status); ?>><?php echo esc_html(ucfirst($status)); ?></option><?php endforeach; ?></select><button class="button" type="submit"><?php esc_html_e('Update', 'elev8-os'); ?></button></form><form class="elev8-inline-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js(__('Remove this waitlist entry?', 'elev8-os')); ?>');"><input type="hidden" name="action" value="elev8_os_waitlist_delete"><input type="hidden" name="id" value="<?php echo esc_attr((string) $entry->id); ?>"><input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect); ?>"><?php wp_nonce_field('elev8_os_waitlist_delete_' . $entry->id); ?><button class="button-link-delete" type="submit"><?php esc_html_e('Remove', 'elev8-os'); ?></button></form></td></tr><?php endforeach; ?>
        </tbody></table></div><?php endif; ?></section>
        <?php
    }

    public static function handle_save(): void {
        self::require_user();
        check_admin_referer('elev8_os_waitlist_save');
        $employee_id = absint($_POST['employee_id'] ?? 0);
        self::assert_employee_scope($employee_id);
        $appointment_id = absint($_POST['appointment_id'] ?? 0);
        $class = self::appointment_for_employee($appointment_id, $employee_id);
        if (!$class) {
            wp_die(esc_html__('The selected Amelia class could not be verified for this artist.', 'elev8-os'));
        }
        $customer_name = sanitize_text_field(wp_unslash($_POST['customer_name'] ?? ''));
        if ($customer_name === '') { wp_die(esc_html__('Customer name is required.', 'elev8-os')); }
        global $wpdb;
        $now = current_time('mysql');
        $wpdb->insert(self::table(), [
            'appointment_id' => $appointment_id,
            'employee_id' => $employee_id,
            'service_id' => (int) $class['service_id'],
            'class_label' => (string) $class['name'],
            'class_date' => (string) $class['class_date'],
            'class_time' => (string) $class['class_time'],
            'customer_name' => $customer_name,
            'customer_email' => sanitize_email(wp_unslash($_POST['customer_email'] ?? '')),
            'customer_phone' => sanitize_text_field(wp_unslash($_POST['customer_phone'] ?? '')),
            'seats_requested' => max(1, min(50, absint($_POST['seats_requested'] ?? 1))),
            'notes' => sanitize_textarea_field(wp_unslash($_POST['notes'] ?? '')),
            'status' => 'waiting', 'created_at' => $now, 'updated_at' => $now,
        ], ['%d','%d','%d','%s','%s','%s','%s','%s','%s','%d','%s','%s','%s','%s']);
        self::redirect();
    }

    public static function handle_status(): void {
        self::require_user(); $id=absint($_POST['id']??0); check_admin_referer('elev8_os_waitlist_status_'.$id); $entry=self::entry($id); self::assert_entry_scope($entry);
        $status=sanitize_key($_POST['status']??'waiting'); if(!in_array($status,self::statuses(),true)){$status='waiting';}
        global $wpdb; $wpdb->update(self::table(),['status'=>$status,'updated_at'=>current_time('mysql')],['id'=>$id],['%s','%s'],['%d']); self::redirect();
    }

    public static function handle_delete(): void {
        self::require_user(); $id=absint($_POST['id']??0); check_admin_referer('elev8_os_waitlist_delete_'.$id); $entry=self::entry($id); self::assert_entry_scope($entry);
        global $wpdb; $wpdb->delete(self::table(),['id'=>$id],['%d']); self::redirect();
    }

    private static function employees(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'amelia_users';
        if (!self::table_exists($table)) { return []; }
        $columns = self::table_columns($table);
        if (!in_array('id', $columns, true)) { return []; }
        $select = ['`id`'];
        foreach (['firstName','lastName','email','type','status'] as $column) { if (in_array($column, $columns, true)) { $select[] = "`{$column}`"; } }
        $where = in_array('type', $columns, true) ? " WHERE LOWER(COALESCE(`type`,'')) IN ('provider','employee')" : '';
        $rows = $wpdb->get_results('SELECT ' . implode(',', $select) . " FROM `{$table}`{$where} ORDER BY `id` ASC", ARRAY_A);
        $employees = [];
        foreach ((array) $rows as $row) {
            $name = trim((string) ($row['firstName'] ?? '') . ' ' . (string) ($row['lastName'] ?? ''));
            if ($name === '') { $name = (string) ($row['email'] ?? ('Artist #' . (int) $row['id'])); }
            $employees[] = ['id' => (int) $row['id'], 'name' => $name];
        }
        return $employees;
    }

    private static function upcoming_classes(int $employee_id): array {
        global $wpdb;
        $appointments = $wpdb->prefix . 'amelia_appointments';
        if ($employee_id <= 0 || !self::table_exists($appointments)) { return []; }
        $columns = self::table_columns($appointments);
        $id_col = self::first_existing_column($columns, ['id']);
        $provider_col = self::first_existing_column($columns, ['providerId','provider_id','employeeId']);
        $service_col = self::first_existing_column($columns, ['serviceId','service_id']);
        $start_col = self::first_existing_column($columns, ['bookingStart','booking_start','start']);
        $capacity_col = self::first_existing_column($columns, ['maxCapacity','max_capacity','capacity']);
        $status_col = self::first_existing_column($columns, ['status']);
        if (!$id_col || !$provider_col || !$start_col) { return []; }
        $select = ["`{$id_col}` AS appointment_id", "`{$provider_col}` AS employee_id", "`{$start_col}` AS booking_start"];
        $select[] = $service_col ? "`{$service_col}` AS service_id" : '0 AS service_id';
        $select[] = $capacity_col ? "`{$capacity_col}` AS capacity" : 'NULL AS capacity';
        $where_status = $status_col ? " AND LOWER(COALESCE(`{$status_col}`,'')) NOT IN ('canceled','cancelled','rejected')" : '';
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT ' . implode(',', $select) . " FROM `{$appointments}` WHERE `{$provider_col}`=%d AND `{$start_col}` >= %s{$where_status} ORDER BY `{$start_col}` ASC LIMIT 250",
            $employee_id, current_time('mysql')
        ), ARRAY_A);
        $services = self::service_details();
        $booked = self::booked_seats();
        $classes = [];
        foreach ((array) $rows as $row) {
            $appointment_id = (int) $row['appointment_id'];
            $service_id = (int) $row['service_id'];
            $start = (string) $row['booking_start'];
            $timestamp = strtotime($start);
            if ($timestamp === false) { continue; }
            $capacity = self::positive_int_or_null($row['capacity'] ?? null);
            if ($capacity === null && isset($services[$service_id]['capacity'])) { $capacity = $services[$service_id]['capacity']; }
            $booked_seats = (int) ($booked[$appointment_id] ?? 0);
            $classes[] = [
                'appointment_id' => $appointment_id,
                'employee_id' => $employee_id,
                'service_id' => $service_id,
                'name' => $services[$service_id]['name'] ?? __('Class', 'elev8-os'),
                'class_date' => wp_date('Y-m-d', $timestamp),
                'class_time' => wp_date('H:i:s', $timestamp),
                'display' => wp_date(get_option('date_format') . ' ' . get_option('time_format'), $timestamp),
                'capacity' => $capacity,
                'booked' => $booked_seats,
                'seats_left' => $capacity === null ? null : max(0, $capacity - $booked_seats),
            ];
        }
        return $classes;
    }

    private static function appointment_for_employee(int $appointment_id, int $employee_id): ?array {
        if ($appointment_id <= 0 || $employee_id <= 0) { return null; }
        foreach (self::upcoming_classes($employee_id) as $class) {
            if ((int) $class['appointment_id'] === $appointment_id) { return $class; }
        }
        return null;
    }

    private static function service_details(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'amelia_services';
        if (!self::table_exists($table)) { return []; }
        $columns = self::table_columns($table);
        if (!in_array('id', $columns, true)) { return []; }
        $name_col = self::first_existing_column($columns, ['name','title']);
        $capacity_col = self::first_existing_column($columns, ['maxCapacity','max_capacity','capacity']);
        $select = ['`id`'];
        $select[] = $name_col ? "`{$name_col}` AS name" : "'' AS name";
        $select[] = $capacity_col ? "`{$capacity_col}` AS capacity" : 'NULL AS capacity';
        $rows = $wpdb->get_results('SELECT ' . implode(',', $select) . " FROM `{$table}`", ARRAY_A);
        $map = [];
        foreach ((array) $rows as $row) { $map[(int) $row['id']] = ['name' => (string) $row['name'], 'capacity' => self::positive_int_or_null($row['capacity'] ?? null)]; }
        return $map;
    }

    private static function booked_seats(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'amelia_customer_bookings';
        if (!self::table_exists($table)) { return []; }
        $columns = self::table_columns($table);
        $appointment_col = self::first_existing_column($columns, ['appointmentId','appointment_id']);
        if (!$appointment_col) { return []; }
        $persons_col = self::first_existing_column($columns, ['persons','personsCount','persons_count']);
        $status_col = self::first_existing_column($columns, ['status']);
        $sum = $persons_col ? "SUM(COALESCE(`{$persons_col}`,1))" : 'COUNT(*)';
        $where = $status_col ? " WHERE LOWER(COALESCE(`{$status_col}`,'')) NOT IN ('canceled','cancelled','rejected')" : '';
        $rows = $wpdb->get_results("SELECT `{$appointment_col}` AS appointment_id, {$sum} AS seats FROM `{$table}`{$where} GROUP BY `{$appointment_col}`", ARRAY_A);
        $map = [];
        foreach ((array) $rows as $row) { $map[(int) $row['appointment_id']] = max(0, (int) $row['seats']); }
        return $map;
    }

    private static function class_option_label(array $class): string {
        $seat_text = $class['seats_left'] === null
            ? __('capacity unavailable', 'elev8-os')
            : sprintf(_n('%d seat left', '%d seats left', (int) $class['seats_left'], 'elev8-os'), (int) $class['seats_left']);
        return sprintf('%s — %s — %s', (string) $class['name'], (string) $class['display'], $seat_text);
    }

    private static function entries(int $employee_id): array { global $wpdb; $table=self::table(); if($employee_id>0){return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE employee_id=%d ORDER BY class_date IS NULL, class_date ASC, created_at ASC",$employee_id))?:[];} return $wpdb->get_results("SELECT * FROM {$table} ORDER BY class_date IS NULL, class_date ASC, created_at ASC")?:[]; }
    private static function entry(int $id){ global $wpdb; return $wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::table().' WHERE id=%d',$id)); }
    private static function statuses(): array { return ['waiting','contacted','booked','declined','removed']; }
    private static function require_user(): void { if(!is_user_logged_in()){wp_die(esc_html__('You must be logged in.','elev8-os'));} }
    private static function assert_employee_scope(int $employee_id): void { if(current_user_can('manage_options')){return;} $mapped=absint(get_user_meta(get_current_user_id(),self::EMPLOYEE_META,true)); if($mapped<=0||$mapped!==$employee_id){wp_die(esc_html__('You do not have permission to manage this waitlist.','elev8-os'));} }
    private static function assert_entry_scope($entry): void { if(!$entry){wp_die(esc_html__('Waitlist entry not found.','elev8-os'));} self::assert_employee_scope((int)$entry->employee_id); }
    private static function redirect(): void { $url=esc_url_raw(wp_unslash($_POST['redirect_to']??'')); if(!$url){$url=admin_url('admin.php?page='.self::ADMIN_SLUG);} wp_safe_redirect(add_query_arg('elev8_waitlist_saved','1',$url)); exit; }
    private static function format_occurrence($entry): string { $parts=[]; if($entry->class_date){$ts=strtotime($entry->class_date.' 12:00:00');$parts[]=wp_date(get_option('date_format'),$ts);} if($entry->class_time){$ts=strtotime('1970-01-01 '.$entry->class_time);$parts[]=wp_date(get_option('time_format'),$ts);} return $parts?implode(' at ',$parts):__('Date not set','elev8-os'); }
    private static function positive_int_or_null($value): ?int { if ($value === null || $value === '' || !is_numeric($value)) { return null; } $int = (int) $value; return $int > 0 ? $int : null; }
    private static function table_exists(string $table): bool { global $wpdb; return (string) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table; }
    private static function table_columns(string $table): array { global $wpdb; $rows=$wpdb->get_results("SHOW COLUMNS FROM `{$table}`",ARRAY_A); return array_values(array_filter(array_map(static fn(array $row): string => (string)($row['Field']??''),(array)$rows))); }
    private static function first_existing_column(array $columns, array $candidates): ?string { foreach($candidates as $candidate){if(in_array($candidate,$columns,true)){return $candidate;}} return null; }
}

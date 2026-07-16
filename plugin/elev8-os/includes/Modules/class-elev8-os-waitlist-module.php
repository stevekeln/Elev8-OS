<?php
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Waitlist_Module {
    private const DB_VERSION = '1.0.0';
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
        echo '<div class="wrap"><h1>' . esc_html__('Waitlists', 'elev8-os') . '</h1><p>' . esc_html__('Phase 1 provides a reliable Elev8-owned waitlist. Class integration and automatic notifications will be added in later milestones.', 'elev8-os') . '</p>';
        self::render_content(0, true);
        echo '</div>';
    }

    private static function render_content(int $employee_id, bool $admin): void {
        $entries = self::entries($employee_id);
        $redirect = $admin ? admin_url('admin.php?page=' . self::ADMIN_SLUG) : Elev8_OS_Portal_Page_Manager::get_url('waitlist');
        if (isset($_GET['elev8_waitlist_saved'])) { echo '<div class="notice notice-success"><p>' . esc_html__('Waitlist updated.', 'elev8-os') . '</p></div>'; }
        ?>
        <header class="elev8-dashboard-header"><div><p class="elev8-eyebrow"><?php esc_html_e('Artist Portal', 'elev8-os'); ?></p><h1><?php esc_html_e('Waitlist', 'elev8-os'); ?></h1><p><?php esc_html_e('Track customers waiting for a class. Automated seat matching comes in a later phase.', 'elev8-os'); ?></p></div><span class="elev8-dashboard-badge"><?php esc_html_e('Phase 1', 'elev8-os'); ?></span></header>
        <section class="elev8-waitlist-panel">
          <h2><?php esc_html_e('Add a customer', 'elev8-os'); ?></h2>
          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="elev8-waitlist-form">
            <input type="hidden" name="action" value="elev8_os_waitlist_save"><input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect); ?>">
            <?php wp_nonce_field('elev8_os_waitlist_save'); ?>
            <?php if ($admin) : ?><label><?php esc_html_e('Amelia employee ID', 'elev8-os'); ?><input type="number" min="0" name="employee_id" value="0"></label><?php else : ?><input type="hidden" name="employee_id" value="<?php echo esc_attr((string)$employee_id); ?>"><?php endif; ?>
            <label><?php esc_html_e('Class or experience', 'elev8-os'); ?><input required type="text" name="class_label" maxlength="190"></label>
            <label><?php esc_html_e('Class date', 'elev8-os'); ?><input type="date" name="class_date"></label>
            <label><?php esc_html_e('Class time', 'elev8-os'); ?><input type="time" name="class_time"></label>
            <label><?php esc_html_e('Customer name', 'elev8-os'); ?><input required type="text" name="customer_name" maxlength="190"></label>
            <label><?php esc_html_e('Email', 'elev8-os'); ?><input type="email" name="customer_email" maxlength="190"></label>
            <label><?php esc_html_e('Phone', 'elev8-os'); ?><input type="text" name="customer_phone" maxlength="80"></label>
            <label><?php esc_html_e('Seats requested', 'elev8-os'); ?><input type="number" min="1" max="50" name="seats_requested" value="1"></label>
            <label class="elev8-waitlist-notes"><?php esc_html_e('Notes', 'elev8-os'); ?><textarea name="notes" rows="3"></textarea></label>
            <div><button class="button button-primary" type="submit"><?php esc_html_e('Add to Waitlist', 'elev8-os'); ?></button></div>
          </form>
        </section>
        <section class="elev8-waitlist-panel"><h2><?php esc_html_e('Current entries', 'elev8-os'); ?></h2>
        <?php if (!$entries) : ?><p><?php esc_html_e('No waitlist entries yet.', 'elev8-os'); ?></p><?php else : ?>
        <div class="elev8-waitlist-table-wrap"><table class="widefat striped elev8-waitlist-table"><thead><tr><th><?php esc_html_e('Customer', 'elev8-os'); ?></th><th><?php esc_html_e('Class', 'elev8-os'); ?></th><th><?php esc_html_e('Seats', 'elev8-os'); ?></th><th><?php esc_html_e('Status', 'elev8-os'); ?></th><th><?php esc_html_e('Actions', 'elev8-os'); ?></th></tr></thead><tbody>
        <?php foreach ($entries as $entry) : ?><tr><td><strong><?php echo esc_html($entry->customer_name); ?></strong><br><small><?php echo esc_html(trim($entry->customer_email . ' ' . $entry->customer_phone)); ?></small></td><td><?php echo esc_html($entry->class_label); ?><br><small><?php echo esc_html(self::format_occurrence($entry)); ?></small></td><td><?php echo esc_html((string)$entry->seats_requested); ?></td><td><?php echo esc_html(ucfirst($entry->status)); ?></td><td><form class="elev8-inline-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="elev8_os_waitlist_status"><input type="hidden" name="id" value="<?php echo esc_attr((string)$entry->id); ?>"><input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect); ?>"><?php wp_nonce_field('elev8_os_waitlist_status_' . $entry->id); ?><select name="status"><?php foreach (self::statuses() as $status) : ?><option value="<?php echo esc_attr($status); ?>" <?php selected($entry->status,$status); ?>><?php echo esc_html(ucfirst($status)); ?></option><?php endforeach; ?></select><button class="button" type="submit"><?php esc_html_e('Update', 'elev8-os'); ?></button></form><form class="elev8-inline-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js(__('Remove this waitlist entry?', 'elev8-os')); ?>');"><input type="hidden" name="action" value="elev8_os_waitlist_delete"><input type="hidden" name="id" value="<?php echo esc_attr((string)$entry->id); ?>"><input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect); ?>"><?php wp_nonce_field('elev8_os_waitlist_delete_' . $entry->id); ?><button class="button-link-delete" type="submit"><?php esc_html_e('Remove', 'elev8-os'); ?></button></form></td></tr><?php endforeach; ?>
        </tbody></table></div><?php endif; ?></section>
        <?php
    }

    public static function handle_save(): void {
        self::require_user(); check_admin_referer('elev8_os_waitlist_save');
        $employee_id = absint($_POST['employee_id'] ?? 0); self::assert_employee_scope($employee_id);
        global $wpdb; $now = current_time('mysql');
        $wpdb->insert(self::table(), [
            'employee_id'=>$employee_id,
            'service_id'=>0,
            'class_label'=>sanitize_text_field(wp_unslash($_POST['class_label'] ?? '')),
            'class_date'=>self::date_value($_POST['class_date'] ?? ''),
            'class_time'=>self::time_value($_POST['class_time'] ?? ''),
            'customer_name'=>sanitize_text_field(wp_unslash($_POST['customer_name'] ?? '')),
            'customer_email'=>sanitize_email(wp_unslash($_POST['customer_email'] ?? '')),
            'customer_phone'=>sanitize_text_field(wp_unslash($_POST['customer_phone'] ?? '')),
            'seats_requested'=>max(1,min(50,absint($_POST['seats_requested'] ?? 1))),
            'notes'=>sanitize_textarea_field(wp_unslash($_POST['notes'] ?? '')),
            'status'=>'waiting','created_at'=>$now,'updated_at'=>$now,
        ], ['%d','%d','%s','%s','%s','%s','%s','%s','%d','%s','%s','%s','%s']);
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

    private static function entries(int $employee_id): array { global $wpdb; $table=self::table(); if($employee_id>0){return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE employee_id=%d ORDER BY class_date IS NULL, class_date ASC, created_at ASC",$employee_id))?:[];} return $wpdb->get_results("SELECT * FROM {$table} ORDER BY class_date IS NULL, class_date ASC, created_at ASC")?:[]; }
    private static function entry(int $id){ global $wpdb; return $wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::table().' WHERE id=%d',$id)); }
    private static function statuses(): array { return ['waiting','contacted','booked','declined','removed']; }
    private static function require_user(): void { if(!is_user_logged_in()){wp_die(esc_html__('You must be logged in.','elev8-os'));} }
    private static function assert_employee_scope(int $employee_id): void { if(current_user_can('manage_options')){return;} $mapped=absint(get_user_meta(get_current_user_id(),self::EMPLOYEE_META,true)); if($mapped<=0||$mapped!==$employee_id){wp_die(esc_html__('You do not have permission to manage this waitlist.','elev8-os'));} }
    private static function assert_entry_scope($entry): void { if(!$entry){wp_die(esc_html__('Waitlist entry not found.','elev8-os'));} self::assert_employee_scope((int)$entry->employee_id); }
    private static function redirect(): void { $url=esc_url_raw(wp_unslash($_POST['redirect_to']??'')); if(!$url){$url=admin_url('admin.php?page='.self::ADMIN_SLUG);} wp_safe_redirect(add_query_arg('elev8_waitlist_saved','1',$url)); exit; }
    private static function date_value($value){$value=sanitize_text_field(wp_unslash($value)); return preg_match('/^\d{4}-\d{2}-\d{2}$/',$value)?$value:null;}
    private static function time_value($value){$value=sanitize_text_field(wp_unslash($value)); return preg_match('/^\d{2}:\d{2}$/',$value)?$value.':00':null;}
    private static function format_occurrence($entry): string { $parts=[]; if($entry->class_date){$ts=strtotime($entry->class_date.' 12:00:00');$parts[]=wp_date(get_option('date_format'),$ts);} if($entry->class_time){$ts=strtotime('1970-01-01 '.$entry->class_time);$parts[]=wp_date(get_option('time_format'),$ts);} return $parts?implode(' at ',$parts):__('Date not set','elev8-os'); }
}

<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Teacher-facing Class Requests workspace.
 *
 * This module intentionally does not read Amelia classes. Class requests and
 * pre-schedule customer interest belong to the Elev8 OS Opportunity Engine.
 */
final class Elev8_OS_Waitlist_Module {
    private const SHORTCODE = 'elev8_artist_waitlist';
    private const ADMIN_SLUG = 'elev8-waitlists';
    private const EMPLOYEE_META = 'elev8_os_amelia_employee_id';

    public static function init(): void {
        add_shortcode(self::SHORTCODE, [__CLASS__, 'shortcode']);
        add_action('admin_menu', [__CLASS__, 'admin_menu'], 40);
        add_action('admin_post_elev8_os_class_request_add', [__CLASS__, 'handle_add_request']);
        add_action('admin_post_elev8_os_class_idea_suggest', [__CLASS__, 'handle_suggest_idea']);
        add_action('admin_post_elev8_os_class_request_update', [__CLASS__, 'handle_update_request']);
        add_action('admin_post_elev8_os_class_request_delete', [__CLASS__, 'handle_delete_request']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    public static function status(): string { return 'active'; }
    public static function activate(): void {}
    public static function maybe_upgrade(): void {}

    public static function admin_menu(): void {
        add_submenu_page('elev8-os', __('Class Requests', 'elev8-os'), __('Class Requests', 'elev8-os'), 'manage_options', self::ADMIN_SLUG, [__CLASS__, 'render_admin']);
    }

    public static function enqueue_assets(): void {
        if (!is_user_logged_in() || !class_exists('Elev8_OS_Portal_Page_Manager') || !Elev8_OS_Portal_Page_Manager::is_current_page('waitlist')) { return; }
        wp_enqueue_style('elev8-os-artist-portal', ELEV8_OS_URL . 'assets/css/artist-portal.css', [], ELEV8_OS_VERSION);
        wp_enqueue_style('elev8-os-waitlist', ELEV8_OS_URL . 'assets/css/artist-waitlist.css', ['elev8-os-artist-portal'], ELEV8_OS_VERSION);
    }

    public static function shortcode(): string {
        if (!is_user_logged_in()) {
            return '<div class="elev8-dashboard-login"><p>' . esc_html__('Please log in to view class requests.', 'elev8-os') . '</p></div>';
        }
        if (!class_exists('Elev8_OS_Opportunity_Service')) {
            return '<div class="elev8-dashboard-warning"><p><strong>' . esc_html__('Class Requests are temporarily unavailable.', 'elev8-os') . '</strong></p></div>';
        }
        $user = wp_get_current_user();
        $teacher_id = self::resolve_teacher_id($user);
        $admin_preview = current_user_can('manage_options');
        if ($admin_preview && isset($_GET['employee_id'])) { $teacher_id = absint($_GET['employee_id']); }
        if ($teacher_id <= 0 && !$admin_preview) {
            return '<div class="elev8-dashboard-warning"><p><strong>' . esc_html__('Your account is not connected to an Elev8 Member Artist record.', 'elev8-os') . '</strong></p></div>';
        }
        ob_start();
        echo '<div class="elev8-artist-dashboard elev8-waitlist elev8-class-requests">';
        if (class_exists('Elev8_OS_Artist_Portal_Module')) { Elev8_OS_Artist_Portal_Module::render_navigation('waitlist'); }
        self::render_content($teacher_id, false, $admin_preview);
        echo '</div>';
        return (string) ob_get_clean();
    }

    public static function render_admin(): void {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('You do not have permission to view this page.', 'elev8-os')); }
        $teacher_id = absint($_GET['employee_id'] ?? 0);
        echo '<div class="wrap"><h1>' . esc_html__('Class Requests', 'elev8-os') . '</h1><p>' . esc_html__('Manage pre-schedule class ideas and customer interest owned by Elev8 OS. This page does not use Amelia class dates.', 'elev8-os') . '</p>';
        self::render_content($teacher_id, true, false);
        echo '</div>';
    }

    private static function render_content(int $teacher_id, bool $admin, bool $admin_preview): void {
        $opportunities = self::available_opportunities($teacher_id, $admin);
        $requests = self::request_rows($teacher_id, $admin);
        $people = count($requests);
        $seats = array_sum(array_map(static fn(array $r): int => (int) $r['seats_requested'], $requests));
        $idea_count = count($opportunities);
        $new_count = count(array_filter($requests, static fn(array $r): bool => (string) $r['crm_status'] === 'new'));
        $redirect = $admin ? add_query_arg(['page'=>self::ADMIN_SLUG,'employee_id'=>$teacher_id], admin_url('admin.php')) : Elev8_OS_Portal_Page_Manager::get_url('waitlist');

        if (isset($_GET['class_request_saved'])) { self::notice(__('Class request saved.', 'elev8-os')); }
        if (isset($_GET['class_idea_saved'])) { self::notice(__('Class idea suggested.', 'elev8-os')); }
        if (isset($_GET['class_request_updated'])) { self::notice(__('Customer request updated.', 'elev8-os')); }
        if (isset($_GET['class_request_deleted'])) { self::notice(__('Customer request removed.', 'elev8-os')); }
        if (isset($_GET['class_request_error'])) { self::notice(__('The class request could not be saved. Please verify the required fields and try again.', 'elev8-os'), true); }
        ?>
        <header class="elev8-dashboard-header elev8-waitlist-header">
            <div>
                <p class="elev8-eyebrow"><?php esc_html_e('Artist Portal', 'elev8-os'); ?></p>
                <h1><?php esc_html_e('Class Requests', 'elev8-os'); ?></h1>
                <p><?php esc_html_e('Collect demand before a class is scheduled. Choose an Elev8 OS class idea or suggest a completely new one.', 'elev8-os'); ?></p>
            </div>
            <span class="elev8-dashboard-badge"><?php esc_html_e('Powered by Opportunity Engine', 'elev8-os'); ?></span>
        </header>

        <section class="elev8-waitlist-metrics" aria-label="<?php esc_attr_e('Class request summary', 'elev8-os'); ?>">
            <?php self::metric(__('People interested', 'elev8-os'), $people, __('Customer interest records connected to class ideas.', 'elev8-os')); ?>
            <?php self::metric(__('Requested seats', 'elev8-os'), $seats, __('Total seats customers may purchase.', 'elev8-os')); ?>
            <?php self::metric(__('Class ideas', 'elev8-os'), $idea_count, __('Available Elev8 OS ideas, not Amelia classes.', 'elev8-os')); ?>
            <?php self::metric(__('New requests', 'elev8-os'), $new_count, __('Requests that have not been contacted yet.', 'elev8-os')); ?>
        </section>

        <div class="elev8-waitlist-workspace">
            <section class="elev8-waitlist-panel">
                <div class="elev8-waitlist-section-heading"><div><p class="elev8-eyebrow"><?php esc_html_e('Customer demand', 'elev8-os'); ?></p><h2><?php esc_html_e('Add a class request', 'elev8-os'); ?></h2></div></div>
                <p class="elev8-request-help"><?php esc_html_e('Select an existing class idea, or type a new class suggestion below. A new suggestion becomes an Opportunity Engine record automatically.', 'elev8-os'); ?></p>
                <form class="elev8-waitlist-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="elev8_os_class_request_add">
                    <input type="hidden" name="teacher_id" value="<?php echo esc_attr((string) $teacher_id); ?>">
                    <input type="hidden" name="redirect_to" value="<?php echo esc_url($redirect); ?>">
                    <?php wp_nonce_field('elev8_os_class_request_add'); ?>
                    <label class="elev8-waitlist-class-select"><span><?php esc_html_e('Existing class idea', 'elev8-os'); ?></span><select name="opportunity_id"><option value="0"><?php esc_html_e('Choose an idea, or suggest a new class below', 'elev8-os'); ?></option><?php foreach ($opportunities as $o): ?><option value="<?php echo esc_attr((string) $o['id']); ?>"><?php echo esc_html((string) $o['title']); ?><?php echo $o['category'] ? ' — ' . esc_html((string) $o['category']) : ''; ?></option><?php endforeach; ?></select></label>
                    <div class="elev8-request-or"><span><?php esc_html_e('OR', 'elev8-os'); ?></span></div>
                    <label class="elev8-waitlist-class-select"><span><?php esc_html_e('Suggest a new class', 'elev8-os'); ?></span><input type="text" name="new_class_title" placeholder="<?php esc_attr_e('Example: Beginner pottery wheel', 'elev8-os'); ?>"></label>
                    <label><span><?php esc_html_e('Category', 'elev8-os'); ?></span><input type="text" name="new_class_category" placeholder="<?php esc_attr_e('Pottery, stained glass, wellness…', 'elev8-os'); ?>"></label>
                    <label><span><?php esc_html_e('Customer name', 'elev8-os'); ?> *</span><input type="text" name="customer_name" required></label>
                    <label><span><?php esc_html_e('Email', 'elev8-os'); ?></span><input type="email" name="customer_email"></label>
                    <label><span><?php esc_html_e('Phone', 'elev8-os'); ?></span><input type="text" name="customer_phone"></label>
                    <label><span><?php esc_html_e('Seats requested', 'elev8-os'); ?></span><input type="number" min="1" name="seats_requested" value="1"></label>
                    <label><span><?php esc_html_e('Preferred days', 'elev8-os'); ?></span><input type="text" name="preferred_days" placeholder="<?php esc_attr_e('Saturday, weekday evenings…', 'elev8-os'); ?>"></label>
                    <label><span><?php esc_html_e('Preferred times', 'elev8-os'); ?></span><input type="text" name="preferred_times" placeholder="<?php esc_attr_e('Morning, after 5 PM…', 'elev8-os'); ?>"></label>
                    <label class="elev8-waitlist-notes"><span><?php esc_html_e('Notes', 'elev8-os'); ?></span><textarea name="notes" rows="3"></textarea></label>
                    <div class="elev8-waitlist-submit"><button class="elev8-waitlist-primary" type="submit"><?php esc_html_e('Save Class Request', 'elev8-os'); ?></button></div>
                </form>
            </section>

            <section class="elev8-waitlist-panel">
                <div class="elev8-waitlist-section-heading"><div><p class="elev8-eyebrow"><?php esc_html_e('Teacher idea', 'elev8-os'); ?></p><h2><?php esc_html_e('Suggest a class without a customer', 'elev8-os'); ?></h2></div></div>
                <p class="elev8-request-help"><?php esc_html_e('Use this when you want to teach something new. It enters the Opportunity Engine for planning and approval.', 'elev8-os'); ?></p>
                <form class="elev8-waitlist-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="elev8_os_class_idea_suggest"><input type="hidden" name="teacher_id" value="<?php echo esc_attr((string) $teacher_id); ?>"><input type="hidden" name="redirect_to" value="<?php echo esc_url($redirect); ?>"><?php wp_nonce_field('elev8_os_class_idea_suggest'); ?>
                    <label class="elev8-waitlist-class-select"><span><?php esc_html_e('Class name', 'elev8-os'); ?> *</span><input type="text" name="title" required></label>
                    <label><span><?php esc_html_e('Category', 'elev8-os'); ?></span><input type="text" name="category"></label>
                    <label><span><?php esc_html_e('Estimated price per seat', 'elev8-os'); ?></span><input type="number" min="0" step="0.01" name="estimated_price"></label>
                    <label><span><?php esc_html_e('Estimated duration in hours', 'elev8-os'); ?></span><input type="number" min="0" step="0.25" name="estimated_duration"></label>
                    <label><span><?php esc_html_e('Preferred day', 'elev8-os'); ?></span><input type="text" name="preferred_day"></label>
                    <label><span><?php esc_html_e('Preferred time', 'elev8-os'); ?></span><input type="text" name="preferred_time"></label>
                    <label class="elev8-waitlist-notes"><span><?php esc_html_e('Description', 'elev8-os'); ?></span><textarea name="description" rows="4"></textarea></label>
                    <div class="elev8-waitlist-submit"><button class="elev8-waitlist-primary" type="submit"><?php esc_html_e('Suggest Class Idea', 'elev8-os'); ?></button></div>
                </form>
            </section>
        </div>

        <section class="elev8-waitlist-panel elev8-request-list-panel">
            <div class="elev8-waitlist-section-heading"><div><p class="elev8-eyebrow"><?php esc_html_e('Current demand', 'elev8-os'); ?></p><h2><?php esc_html_e('Customer class requests', 'elev8-os'); ?></h2></div><span class="elev8-waitlist-count"><?php echo esc_html((string) $people); ?></span></div>
            <?php if (!$requests): ?><div class="elev8-waitlist-empty"><strong><?php esc_html_e('No class requests yet', 'elev8-os'); ?></strong><p><?php esc_html_e('Add the first customer request above.', 'elev8-os'); ?></p></div><?php else: ?><div class="elev8-waitlist-cards"><?php foreach ($requests as $r) { self::request_card($r, $redirect); } ?></div><?php endif; ?>
        </section>
        <?php
    }

    public static function handle_add_request(): void {
        self::authorize('elev8_os_class_request_add');
        $teacher_id = absint($_POST['teacher_id'] ?? 0);
        self::verify_teacher_scope($teacher_id);
        $opportunity_id = absint($_POST['opportunity_id'] ?? 0);
        $new_title = sanitize_text_field(wp_unslash((string) ($_POST['new_class_title'] ?? '')));
        if ($opportunity_id <= 0 && $new_title !== '') {
            $opportunity_id = Elev8_OS_Opportunity_Service::save_opportunity([
                'type'=>'class','title'=>$new_title,'category'=>wp_unslash($_POST['new_class_category'] ?? ''),'status'=>'idea','teacher_id'=>$teacher_id,'teacher_needed'=>0,
            ]);
            self::record($opportunity_id, 'teacher_class_suggested', __('Class idea suggested', 'elev8-os'), __('Created while adding a customer class request.', 'elev8-os'));
        }
        $interest_id = Elev8_OS_Opportunity_Service::add_interest([
            'opportunity_id'=>$opportunity_id,'customer_name'=>wp_unslash($_POST['customer_name'] ?? ''),'customer_email'=>wp_unslash($_POST['customer_email'] ?? ''),'customer_phone'=>wp_unslash($_POST['customer_phone'] ?? ''),'seats_requested'=>$_POST['seats_requested'] ?? 1,'preferred_days'=>wp_unslash($_POST['preferred_days'] ?? ''),'preferred_times'=>wp_unslash($_POST['preferred_times'] ?? ''),'notes'=>wp_unslash($_POST['notes'] ?? ''),'source'=>'artist_portal','crm_status'=>'new',
        ]);
        if ($interest_id > 0) { self::record($opportunity_id, 'customer_class_request_added', __('Customer class request added', 'elev8-os'), sanitize_text_field(wp_unslash((string) ($_POST['customer_name'] ?? ''))), $interest_id); }
        self::redirect($interest_id > 0 ? 'class_request_saved' : 'class_request_error');
    }

    public static function handle_suggest_idea(): void {
        self::authorize('elev8_os_class_idea_suggest');
        $teacher_id = absint($_POST['teacher_id'] ?? 0); self::verify_teacher_scope($teacher_id);
        $id = Elev8_OS_Opportunity_Service::save_opportunity(['type'=>'class','title'=>wp_unslash($_POST['title'] ?? ''),'category'=>wp_unslash($_POST['category'] ?? ''),'description'=>wp_unslash($_POST['description'] ?? ''),'status'=>'idea','teacher_id'=>$teacher_id,'teacher_needed'=>0,'estimated_price'=>$_POST['estimated_price'] ?? '','estimated_duration'=>$_POST['estimated_duration'] ?? '','preferred_day'=>wp_unslash($_POST['preferred_day'] ?? ''),'preferred_time'=>wp_unslash($_POST['preferred_time'] ?? '')]);
        if ($id > 0) { self::record($id, 'teacher_class_suggested', __('Teacher suggested a class', 'elev8-os'), __('Submitted from the Artist Portal Class Requests page.', 'elev8-os')); }
        self::redirect($id > 0 ? 'class_idea_saved' : 'class_request_error');
    }

    public static function handle_update_request(): void {
        self::authorize('elev8_os_class_request_update');
        $interest_id = absint($_POST['interest_id'] ?? 0); $before = Elev8_OS_Opportunity_Service::get_interest($interest_id);
        if (!$before) { self::redirect('class_request_error'); }
        self::verify_opportunity_access((int) $before['opportunity_id']);
        $ok = Elev8_OS_Opportunity_Service::update_interest(wp_unslash($_POST));
        if ($ok) { self::record((int) $before['opportunity_id'], 'customer_class_request_updated', __('Customer class request updated', 'elev8-os'), '', $interest_id); }
        self::redirect($ok ? 'class_request_updated' : 'class_request_error');
    }

    public static function handle_delete_request(): void {
        self::authorize('elev8_os_class_request_delete');
        $interest_id = absint($_POST['interest_id'] ?? 0); $before = Elev8_OS_Opportunity_Service::get_interest($interest_id);
        if (!$before) { self::redirect('class_request_error'); }
        self::verify_opportunity_access((int) $before['opportunity_id']);
        $ok = Elev8_OS_Opportunity_Service::delete_interest($interest_id);
        if ($ok) { self::record((int) $before['opportunity_id'], 'customer_class_request_deleted', __('Customer class request deleted', 'elev8-os'), (string) $before['customer_name'], $interest_id); }
        self::redirect($ok ? 'class_request_deleted' : 'class_request_error');
    }

    private static function available_opportunities(int $teacher_id, bool $admin): array {
        $all = Elev8_OS_Opportunity_Service::all();
        return array_values(array_filter($all, static function(array $o) use ($teacher_id, $admin): bool {
            if ((string) ($o['type'] ?? '') !== 'class' || in_array((string) ($o['status'] ?? ''), ['completed','archived','cancelled'], true)) { return false; }
            if ($admin || $teacher_id <= 0) { return true; }
            $assigned = (int) ($o['teacher_id'] ?? 0);
            return $assigned === 0 || $assigned === $teacher_id;
        }));
    }

    private static function request_rows(int $teacher_id, bool $admin): array {
        $rows=[];
        foreach (Elev8_OS_Opportunity_Service::all() as $o) {
            if ((string) ($o['type'] ?? '') !== 'class') { continue; }
            if (!$admin && $teacher_id > 0 && (int) ($o['teacher_id'] ?? 0) !== $teacher_id) { continue; }
            foreach (Elev8_OS_Opportunity_Service::interests((int) $o['id']) as $i) { $i['opportunity_title']=$o['title']; $i['category']=$o['category']; $rows[]=$i; }
        }
        usort($rows, static fn(array $a,array $b): int => strcmp((string)$b['created_at'], (string)$a['created_at']));
        return $rows;
    }

    private static function request_card(array $r, string $redirect): void {
        $email=sanitize_email((string)$r['customer_email']); $phone=sanitize_text_field((string)$r['customer_phone']);
        ?>
        <article class="elev8-waitlist-customer-card">
            <div class="elev8-waitlist-customer-main"><span class="elev8-waitlist-avatar"><?php echo esc_html(strtoupper(substr((string)$r['customer_name'],0,1))); ?></span><div><h3><?php echo esc_html((string)$r['customer_name']); ?></h3><div class="elev8-waitlist-contact-links"><?php if($email):?><a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a><?php endif; ?><?php if($phone):?><a href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/','',$phone)); ?>"><?php echo esc_html($phone); ?></a><?php endif; ?></div></div></div>
            <div class="elev8-waitlist-class-info"><span><?php esc_html_e('Requested class', 'elev8-os'); ?></span><strong><?php echo esc_html((string)$r['opportunity_title']); ?></strong><?php if($r['category']):?><small><?php echo esc_html((string)$r['category']); ?></small><?php endif; ?></div>
            <div class="elev8-waitlist-seat-info"><span><?php esc_html_e('Seats', 'elev8-os'); ?></span><strong><?php echo esc_html((string)$r['seats_requested']); ?></strong></div>
            <div class="elev8-waitlist-card-actions"><form class="elev8-waitlist-status-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="elev8_os_class_request_update"><input type="hidden" name="interest_id" value="<?php echo esc_attr((string)$r['id']); ?>"><input type="hidden" name="redirect_to" value="<?php echo esc_url($redirect); ?>"><?php wp_nonce_field('elev8_os_class_request_update'); ?><label><span><?php esc_html_e('Status', 'elev8-os'); ?></span><select name="crm_status"><?php foreach(Elev8_OS_Opportunity_Service::interest_statuses() as $s):?><option value="<?php echo esc_attr($s); ?>" <?php selected((string)$r['crm_status'],$s); ?>><?php echo esc_html(ucwords(str_replace('_',' ',$s))); ?></option><?php endforeach; ?></select></label><input type="hidden" name="follow_up_date" value="<?php echo esc_attr((string)$r['follow_up_date']); ?>"><input type="hidden" name="notes" value="<?php echo esc_attr((string)$r['notes']); ?>"><button type="submit"><?php esc_html_e('Save Status', 'elev8-os'); ?></button></form><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js(__('Remove this customer request?', 'elev8-os')); ?>');"><input type="hidden" name="action" value="elev8_os_class_request_delete"><input type="hidden" name="interest_id" value="<?php echo esc_attr((string)$r['id']); ?>"><input type="hidden" name="redirect_to" value="<?php echo esc_url($redirect); ?>"><?php wp_nonce_field('elev8_os_class_request_delete'); ?><button class="elev8-waitlist-remove" type="submit"><?php esc_html_e('Remove', 'elev8-os'); ?></button></form></div>
        </article><?php
    }

    private static function metric(string $label, int $value, string $description): void { echo '<article class="elev8-waitlist-metric"><span>'.esc_html($label).'</span><strong>'.esc_html(number_format_i18n($value)).'</strong><p>'.esc_html($description).'</p></article>'; }
    private static function notice(string $message, bool $error=false): void { echo '<div class="elev8-waitlist-notice'.($error?' is-error':'').'" role="status">'.esc_html($message).'</div>'; }
    private static function authorize(string $action): void { if (!is_user_logged_in()) { auth_redirect(); } check_admin_referer($action); }

    /** Resolve the current teacher through the shared Artist Portal identity path. */
    private static function resolve_teacher_id(WP_User $user): int {
        if (class_exists('Elev8_OS_Artist_Portal_Module') && method_exists('Elev8_OS_Artist_Portal_Module', 'find_artist_for_user')) {
            $artist = Elev8_OS_Artist_Portal_Module::find_artist_for_user($user);
            $artist_id = is_array($artist) ? absint($artist['id'] ?? 0) : 0;
            if ($artist_id > 0) {
                return $artist_id;
            }
        }

        return absint(get_user_meta($user->ID, self::EMPLOYEE_META, true));
    }

    private static function verify_teacher_scope(int $teacher_id): void { if (current_user_can('manage_options')) { return; } $mapped=self::resolve_teacher_id(wp_get_current_user()); if ($teacher_id<=0 || $mapped!==$teacher_id) { wp_die(esc_html__('You do not have permission to manage this teacher record.', 'elev8-os')); } }
    private static function verify_opportunity_access(int $opportunity_id): void { if (current_user_can('manage_options')) { return; } $o=Elev8_OS_Opportunity_Service::get($opportunity_id); $mapped=self::resolve_teacher_id(wp_get_current_user()); if (!$o || (int)$o['teacher_id']!==$mapped) { wp_die(esc_html__('You do not have permission to manage this class request.', 'elev8-os')); } }
    private static function redirect(string $flag): void { $url=esc_url_raw(wp_unslash((string)($_POST['redirect_to'] ?? ''))); if(!$url){$url=class_exists('Elev8_OS_Portal_Page_Manager')?Elev8_OS_Portal_Page_Manager::get_url('waitlist'):home_url('/');} wp_safe_redirect(add_query_arg($flag,1,$url)); exit; }
    private static function record(int $opportunity_id,string $type,string $label,string $details='',int $interest_id=0): void { if($opportunity_id>0 && class_exists('Elev8_OS_Opportunity_Activity_Service')){Elev8_OS_Opportunity_Activity_Service::record($opportunity_id,$type,$label,$details,$interest_id);} }
}

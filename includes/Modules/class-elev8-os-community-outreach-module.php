<?php
if (!defined('ABSPATH')) { exit; }

/**
 * CRM-backed flyer delivery and community outreach MVP.
 * Organizations are stored once and campaign stops reference those records.
 */
final class Elev8_OS_Community_Outreach_Module {
    private const ORG = 'elev8_crm_org';
    private const CAMPAIGN = 'elev8_outreach';
    private const PAGE = 'elev8-community-outreach';

    public static function init(): void {
        add_action('init', [__CLASS__, 'register_post_types']);
        add_action('admin_menu', [__CLASS__, 'admin_menu']);
        add_action('admin_post_elev8_os_save_outreach_org', [__CLASS__, 'save_org']);
        add_action('admin_post_elev8_os_save_outreach_campaign', [__CLASS__, 'save_campaign']);
        add_action('admin_post_elev8_os_update_outreach_stop', [__CLASS__, 'update_stop']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'assets']);
    }

    public static function activate(): void { self::register_post_types(); }

    public static function register_post_types(): void {
        register_post_type(self::ORG, [
            'labels' => ['name' => __('CRM Organizations', 'elev8-os'), 'singular_name' => __('CRM Organization', 'elev8-os')],
            'public' => false, 'show_ui' => false, 'supports' => ['title', 'editor'], 'show_in_rest' => false,
        ]);
        register_post_type(self::CAMPAIGN, [
            'labels' => ['name' => __('Outreach Campaigns', 'elev8-os'), 'singular_name' => __('Outreach Campaign', 'elev8-os')],
            'public' => false, 'show_ui' => false, 'supports' => ['title'], 'show_in_rest' => false,
        ]);
    }

    public static function admin_menu(): void {
        add_submenu_page('elev8-os', __('Community Outreach', 'elev8-os'), __('Community Outreach', 'elev8-os'), 'read', self::PAGE, [__CLASS__, 'render']);
    }

    public static function assets(string $hook): void {
        if ($hook !== 'elev8-os_page_' . self::PAGE) { return; }
        wp_enqueue_style('elev8-os-community-outreach', ELEV8_OS_URL . 'assets/css/community-outreach.css', [], ELEV8_OS_VERSION);
    }

    public static function render(): void {
        if (!is_user_logged_in()) { wp_die(__('Sign in required.', 'elev8-os')); }
        $user = wp_get_current_user();
        $can_manage = user_can($user, 'manage_options') || (class_exists('Elev8_OS_Access_Service') && Elev8_OS_Access_Service::is_manager($user));
        $campaign_id = absint($_GET['campaign'] ?? 0);
        echo '<div class="wrap elev8-outreach"><h1>Community Outreach</h1><p class="elev8-lead">Build a reusable CRM list of dispensaries and community businesses, assign flyer campaigns, and record every delivery as relationship history.</p>';
        self::notice();
        if ($campaign_id) { self::render_campaign($campaign_id, $can_manage); }
        else { self::render_dashboard($can_manage); }
        echo '</div>';
    }

    private static function render_dashboard(bool $can_manage): void {
        $orgs = get_posts(['post_type' => self::ORG, 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC']);
        $campaigns = get_posts(['post_type' => self::CAMPAIGN, 'post_status' => 'publish', 'numberposts' => 20, 'orderby' => 'date', 'order' => 'DESC']);
        if ($can_manage) {
            echo '<div class="elev8-outreach-grid"><section class="elev8-panel"><h2>Add CRM Location</h2>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('elev8_save_outreach_org');
            echo '<input type="hidden" name="action" value="elev8_os_save_outreach_org">';
            self::input('name','Business name',true); self::input('type','Business type (dispensary, coffee shop, retail, etc.)',true); self::input('address','Address',true); self::input('contact','Best contact'); self::input('phone','Phone'); self::input('email','Email'); self::input('best_time','Best day/time to visit');
            echo '<label>Allows flyers<select name="allows_flyers"><option value="unknown">Unknown</option><option value="yes">Yes</option><option value="no">No</option></select></label><label>Placement or restrictions<textarea name="placement" rows="2"></textarea></label><label>CRM notes<textarea name="notes" rows="3"></textarea></label><button class="button button-primary">Save Location</button></form></section>';
            echo '<section class="elev8-panel"><h2>Create Flyer Campaign</h2><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('elev8_save_outreach_campaign');
            echo '<input type="hidden" name="action" value="elev8_os_save_outreach_campaign">'; self::input('name','Campaign name',true); self::input('due_date','Target completion date',false,'date'); self::input('flyer_name','Flyer or promotion',true); echo '<label>Locations<select name="org_ids[]" multiple size="10" required>';
            foreach ($orgs as $org) { echo '<option value="' . (int)$org->ID . '">' . esc_html($org->post_title . ' — ' . get_post_meta($org->ID,'_elev8_address',true)) . '</option>'; }
            echo '</select><small>Hold Ctrl or Command to select multiple locations.</small></label><button class="button button-primary">Create Campaign</button></form></section></div>';
        }
        echo '<section class="elev8-panel"><h2>Active Campaigns</h2>';
        if (!$campaigns) { echo '<p>No outreach campaigns yet.</p>'; }
        else { echo '<div class="elev8-campaign-list">'; foreach ($campaigns as $campaign) {
            $stops = (array)get_post_meta($campaign->ID,'_elev8_stops',true); $done = 0; foreach ($stops as $stop) { if (($stop['status'] ?? '') === 'delivered') { $done++; } }
            echo '<a href="' . esc_url(add_query_arg(['page'=>self::PAGE,'campaign'=>$campaign->ID], admin_url('admin.php'))) . '"><strong>' . esc_html($campaign->post_title) . '</strong><span>' . (int)$done . ' of ' . count($stops) . ' delivered</span></a>';
        } echo '</div>'; } echo '</section>';

        echo '<section class="elev8-panel"><h2>CRM Flyer Locations</h2><div class="elev8-org-list">';
        if (!$orgs) { echo '<p>No locations saved yet.</p>'; }
        foreach ($orgs as $org) { $allows = get_post_meta($org->ID,'_elev8_allows_flyers',true) ?: 'unknown'; echo '<article><strong>' . esc_html($org->post_title) . '</strong><span>' . esc_html(get_post_meta($org->ID,'_elev8_type',true)) . '</span><p>' . esc_html(get_post_meta($org->ID,'_elev8_address',true)) . '</p><small>Flyers: ' . esc_html(ucfirst($allows)) . ' · Last delivery: ' . esc_html(get_post_meta($org->ID,'_elev8_last_delivery',true) ?: 'Unavailable') . '</small></article>'; }
        echo '</div></section>';
    }

    private static function render_campaign(int $campaign_id, bool $can_manage): void {
        $campaign = get_post($campaign_id); if (!$campaign || $campaign->post_type !== self::CAMPAIGN) { echo '<p>Campaign unavailable.</p>'; return; }
        $stops = (array)get_post_meta($campaign_id,'_elev8_stops',true);
        echo '<p><a href="' . esc_url(admin_url('admin.php?page=' . self::PAGE)) . '">← All outreach</a></p><h2>' . esc_html($campaign->post_title) . '</h2><p>Promotion: <strong>' . esc_html(get_post_meta($campaign_id,'_elev8_flyer_name',true)) . '</strong> · Target: ' . esc_html(get_post_meta($campaign_id,'_elev8_due_date',true) ?: 'Unavailable') . '</p><div class="elev8-stop-list">';
        foreach ($stops as $index => $stop) { $org = get_post(absint($stop['org_id'] ?? 0)); if (!$org) { continue; } $status = $stop['status'] ?? 'pending';
            echo '<article class="elev8-stop is-' . esc_attr($status) . '"><div><strong>' . esc_html($org->post_title) . '</strong><p>' . esc_html(get_post_meta($org->ID,'_elev8_address',true)) . '</p><small>' . esc_html(get_post_meta($org->ID,'_elev8_placement',true) ?: 'Flyer placement details unavailable.') . '</small></div>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">'; wp_nonce_field('elev8_update_outreach_stop_' . $campaign_id); echo '<input type="hidden" name="action" value="elev8_os_update_outreach_stop"><input type="hidden" name="campaign_id" value="' . (int)$campaign_id . '"><input type="hidden" name="stop_index" value="' . (int)$index . '"><label>Status<select name="status"><option value="pending"' . selected($status,'pending',false) . '>Pending</option><option value="delivered"' . selected($status,'delivered',false) . '>Delivered</option><option value="closed"' . selected($status,'closed',false) . '>Business closed</option><option value="refused"' . selected($status,'refused',false) . '>No longer allows flyers</option><option value="follow_up"' . selected($status,'follow_up',false) . '>Follow-up needed</option></select></label><label>Quantity<input type="number" min="0" name="quantity" value="' . esc_attr((string)($stop['quantity'] ?? '')) . '"></label><label>Contact / notes<textarea name="notes" rows="2">' . esc_textarea((string)($stop['notes'] ?? '')) . '</textarea></label><button class="button button-primary">Save Stop</button></form></article>';
        }
        echo '</div>';
    }

    public static function save_org(): void {
        self::require_manager('elev8_save_outreach_org');
        $name = sanitize_text_field(wp_unslash($_POST['name'] ?? '')); if ($name === '') { self::redirect('error'); }
        $id = wp_insert_post(['post_type'=>self::ORG,'post_status'=>'publish','post_title'=>$name,'post_content'=>sanitize_textarea_field(wp_unslash($_POST['notes'] ?? ''))], true);
        if (is_wp_error($id)) { self::redirect('error'); }
        foreach (['type','address','contact','phone','email','best_time','allows_flyers','placement'] as $key) { update_post_meta($id,'_elev8_' . $key, sanitize_text_field(wp_unslash($_POST[$key] ?? ''))); }
        self::redirect('org_saved');
    }

    public static function save_campaign(): void {
        self::require_manager('elev8_save_outreach_campaign');
        $name = sanitize_text_field(wp_unslash($_POST['name'] ?? '')); $org_ids = array_values(array_filter(array_map('absint',(array)($_POST['org_ids'] ?? [])))); if ($name === '' || !$org_ids) { self::redirect('error'); }
        $id = wp_insert_post(['post_type'=>self::CAMPAIGN,'post_status'=>'publish','post_title'=>$name], true); if (is_wp_error($id)) { self::redirect('error'); }
        update_post_meta($id,'_elev8_due_date',sanitize_text_field(wp_unslash($_POST['due_date'] ?? ''))); update_post_meta($id,'_elev8_flyer_name',sanitize_text_field(wp_unslash($_POST['flyer_name'] ?? '')));
        $stops = []; foreach ($org_ids as $org_id) { if (get_post_type($org_id) === self::ORG) { $stops[] = ['org_id'=>$org_id,'status'=>'pending','quantity'=>'','notes'=>'','updated_by'=>0,'updated_at'=>'']; } }
        update_post_meta($id,'_elev8_stops',$stops); self::redirect('campaign_saved');
    }

    public static function update_stop(): void {
        if (!is_user_logged_in()) { auth_redirect(); }
        $campaign_id = absint($_POST['campaign_id'] ?? 0); check_admin_referer('elev8_update_outreach_stop_' . $campaign_id);
        $user = wp_get_current_user(); if (!user_can($user,'manage_options') && !(class_exists('Elev8_OS_Access_Service') && Elev8_OS_Access_Service::can_distribute_flyers($user))) { wp_die(__('You cannot update outreach stops.', 'elev8-os')); }
        $stops = (array)get_post_meta($campaign_id,'_elev8_stops',true); $index = absint($_POST['stop_index'] ?? -1); if (!isset($stops[$index])) { self::redirect('error',$campaign_id); }
        $allowed = ['pending','delivered','closed','refused','follow_up']; $status = sanitize_key($_POST['status'] ?? 'pending'); if (!in_array($status,$allowed,true)) { $status='pending'; }
        $stops[$index]['status']=$status; $stops[$index]['quantity']=absint($_POST['quantity'] ?? 0); $stops[$index]['notes']=sanitize_textarea_field(wp_unslash($_POST['notes'] ?? '')); $stops[$index]['updated_by']=get_current_user_id(); $stops[$index]['updated_at']=current_time('mysql'); update_post_meta($campaign_id,'_elev8_stops',$stops);
        $org_id=absint($stops[$index]['org_id'] ?? 0); if ($status==='delivered' && $org_id) { update_post_meta($org_id,'_elev8_last_delivery',current_time('Y-m-d')); update_post_meta($org_id,'_elev8_last_delivery_notes',$stops[$index]['notes']); }
        self::redirect('stop_saved',$campaign_id);
    }

    private static function require_manager(string $nonce): void { if (!is_user_logged_in()) { auth_redirect(); } check_admin_referer($nonce); $u=wp_get_current_user(); if (!user_can($u,'manage_options') && !(class_exists('Elev8_OS_Access_Service') && Elev8_OS_Access_Service::is_manager($u))) { wp_die(__('Manager access required.', 'elev8-os')); } }
    private static function redirect(string $message, int $campaign=0): void { $args=['page'=>self::PAGE,'elev8_notice'=>$message]; if ($campaign) {$args['campaign']=$campaign;} wp_safe_redirect(add_query_arg($args,admin_url('admin.php'))); exit; }
    private static function notice(): void { $n=sanitize_key($_GET['elev8_notice'] ?? ''); $map=['org_saved'=>'Location saved.','campaign_saved'=>'Campaign created.','stop_saved'=>'Outreach stop updated.','error'=>'The request could not be saved.']; if(isset($map[$n])) echo '<div class="notice ' . ($n==='error'?'notice-error':'notice-success') . '"><p>' . esc_html($map[$n]) . '</p></div>'; }
    private static function input(string $name,string $label,bool $required=false,string $type='text'): void { echo '<label>' . esc_html($label) . '<input type="' . esc_attr($type) . '" name="' . esc_attr($name) . '"' . ($required?' required':'') . '></label>'; }
}

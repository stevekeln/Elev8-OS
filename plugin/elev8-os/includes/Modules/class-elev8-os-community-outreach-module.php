<?php
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Community_Outreach_Module {
    private const ORG = 'elev8_relationship';
    private const CAMPAIGN = 'elev8_outreach';
    private const PAGE = 'elev8-community-outreach';
    private const SEEDED = 'elev8_os_outreach_seed_20260720';

    public static function init(): void {
        add_action('init', [__CLASS__, 'register_post_types']);
        add_action('admin_menu', [__CLASS__, 'admin_menu']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'assets']);
        foreach (['save_org','save_campaign','update_stop','save_visit'] as $action) {
            add_action('admin_post_elev8_os_' . $action, [__CLASS__, $action]);
        }
        add_action('admin_init', [__CLASS__, 'maybe_seed']);
    }

    public static function activate(): void { self::register_post_types(); self::seed(); }

    public static function register_post_types(): void {
        register_post_type(self::ORG, ['public'=>false,'show_ui'=>false,'supports'=>['title','editor'],'show_in_rest'=>false]);
        register_post_type(self::CAMPAIGN, ['public'=>false,'show_ui'=>false,'supports'=>['title'],'show_in_rest'=>false]);
    }

    public static function admin_menu(): void {
        add_submenu_page('elev8-os', 'Relationships & Outreach', 'Relationships', 'read', self::PAGE, [__CLASS__, 'render']);
    }

    public static function assets(string $hook): void {
        if ($hook === 'elev8-os_page_' . self::PAGE) {
            wp_enqueue_style('elev8-os-community-outreach', ELEV8_OS_URL . 'assets/css/community-outreach.css', [], ELEV8_OS_VERSION);
        }
    }

    public static function maybe_seed(): void {
        if (current_user_can('manage_options') && !get_option(self::SEEDED)) { self::seed(); }
    }

    private static function seed(): void {
        if (get_option(self::SEEDED)) { return; }
        $file = ELEV8_OS_DIR . 'includes/Data/community-outreach-seed.php';
        if (!is_readable($file)) { return; }
        $items = require $file;
        foreach ((array)$items as $item) {
            $name = sanitize_text_field($item['name'] ?? ''); if ($name === '') { continue; }
            $existing = get_page_by_title($name, OBJECT, self::ORG); if ($existing) { continue; }
            $id = wp_insert_post(['post_type'=>self::ORG,'post_status'=>'publish','post_title'=>$name,'post_content'=>sanitize_textarea_field($item['notes'] ?? '')], true);
            if (!is_wp_error($id)) {
                update_post_meta($id,'_elev8_type',sanitize_text_field($item['type'] ?? 'Dispensary'));
                update_post_meta($id,'_elev8_allows_flyers','unknown');
                update_post_meta($id,'_elev8_relationship_level','never_met');
            }
        }
        update_option(self::SEEDED, current_time('mysql'), false);
    }

    public static function render(): void {
        if (!is_user_logged_in()) { wp_die('Sign in required.'); }
        $can_manage = current_user_can('manage_options') || (class_exists('Elev8_OS_Access_Service') && Elev8_OS_Access_Service::is_manager(wp_get_current_user()));
        $campaign = absint($_GET['campaign'] ?? 0); $org = absint($_GET['org'] ?? 0);
        echo '<div class="wrap elev8-outreach"><h1>Relationships & Outreach</h1><p class="elev8-lead">One CRM for flyer locations, community partners, referrals, wholesale prospects, sponsors, and relationship history.</p>';
        self::notice();
        if ($campaign) self::render_campaign($campaign); elseif ($org) self::render_org($org, $can_manage); else self::render_dashboard($can_manage);
        echo '</div>';
    }

    private static function render_dashboard(bool $can_manage): void {
        $orgs = get_posts(['post_type'=>self::ORG,'post_status'=>'publish','numberposts'=>-1,'orderby'=>'title','order'=>'ASC']);
        $campaigns = get_posts(['post_type'=>self::CAMPAIGN,'post_status'=>'publish','numberposts'=>20,'orderby'=>'date','order'=>'DESC']);
        $follow=0;$active=0;$unknown=0;
        foreach($orgs as $o){$r=get_post_meta($o->ID,'_elev8_relationship_level',true);if($r==='needs_follow_up')$follow++;if($r && $r!=='never_met')$active++;if((get_post_meta($o->ID,'_elev8_allows_flyers',true)?:'unknown')==='unknown')$unknown++;}
        echo '<div class="elev8-stats"><article><strong>'.count($orgs).'</strong><span>Relationships</span></article><article><strong>'.$active.'</strong><span>Active</span></article><article><strong>'.$follow.'</strong><span>Need follow-up</span></article><article><strong>'.$unknown.'</strong><span>Flyer status unknown</span></article></div>';
        if ($can_manage) {
            echo '<div class="elev8-outreach-grid"><section class="elev8-panel"><h2>Add Relationship</h2><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('elev8_save_org');echo '<input type="hidden" name="action" value="elev8_os_save_org">';
            self::input('name','Business or organization name',true);self::input('type','Type');self::input('address','Address');self::input('contact','Primary contact');self::input('phone','Phone');self::input('email','Email','', 'email');
            echo '<label>Allows flyers<select name="allows_flyers"><option value="unknown">Unknown</option><option value="yes">Yes</option><option value="sometimes">Sometimes</option><option value="no">No</option></select></label><label>Relationship<select name="relationship_level"><option value="never_met">Never met</option><option value="cold">Cold</option><option value="friend">Friend</option><option value="good_customer">Good customer</option><option value="future_opportunity">Future opportunity</option><option value="needs_follow_up">Needs follow-up</option></select></label><label>Notes<textarea name="notes" rows="4"></textarea></label><button class="button button-primary">Save Relationship</button></form></section>';
            echo '<section class="elev8-panel"><h2>Create Campaign</h2><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('elev8_save_campaign');echo '<input type="hidden" name="action" value="elev8_os_save_campaign">';self::input('name','Campaign name',true);self::input('promotion','Flyer or promotion',true);self::input('due_date','Target date',false,'date');echo '<label>Locations<select name="org_ids[]" multiple size="12" required>';foreach($orgs as $o)echo '<option value="'.$o->ID.'">'.esc_html($o->post_title).'</option>';echo '</select></label><button class="button button-primary">Create Campaign</button></form></section></div>';
        }
        echo '<section class="elev8-panel"><h2>Campaigns</h2><div class="elev8-campaign-list">'; if(!$campaigns)echo '<p>No campaigns yet.</p>'; foreach($campaigns as $c){$st=(array)get_post_meta($c->ID,'_elev8_stops',true);$done=count(array_filter($st,fn($x)=>($x['status']??'')==='delivered'));echo '<a href="'.esc_url(admin_url('admin.php?page='.self::PAGE.'&campaign='.$c->ID)).'"><strong>'.esc_html($c->post_title).'</strong><span>'.$done.' of '.count($st).' delivered</span></a>';} echo '</div></section>';
        echo '<section class="elev8-panel"><h2>Relationship Directory</h2><div class="elev8-org-list">';foreach($orgs as $o){$type=get_post_meta($o->ID,'_elev8_type',true)?:'Unavailable';$last=get_post_meta($o->ID,'_elev8_last_visit',true)?:'Unavailable';$allows=get_post_meta($o->ID,'_elev8_allows_flyers',true)?:'unknown';echo '<article><a href="'.esc_url(admin_url('admin.php?page='.self::PAGE.'&org='.$o->ID)).'"><strong>'.esc_html($o->post_title).'</strong></a><span>'.esc_html($type).'</span><p>'.esc_html(get_post_meta($o->ID,'_elev8_address',true)?:'Address unavailable').'</p><small>Flyers: '.esc_html(ucfirst($allows)).' · Last visit: '.esc_html($last).'</small></article>';}echo '</div></section>';
    }

    private static function render_org(int $id,bool $can_manage): void {
        $o=get_post($id);if(!$o||$o->post_type!==self::ORG){echo '<p>Relationship unavailable.</p>';return;}
        echo '<p><a href="'.esc_url(admin_url('admin.php?page='.self::PAGE)).'">← Relationships</a></p><section class="elev8-panel"><h2>'.esc_html($o->post_title).'</h2>';
        $address=get_post_meta($id,'_elev8_address',true);if($address)echo '<p><a target="_blank" rel="noopener" href="'.esc_url('https://www.google.com/maps/search/?api=1&query='.rawurlencode($address)).'">Open directions</a></p>';
        echo '<dl class="elev8-details">';foreach(['type'=>'Type','address'=>'Address','contact'=>'Contact','phone'=>'Phone','email'=>'Email','allows_flyers'=>'Allows flyers','relationship_level'=>'Relationship','best_time'=>'Best visit time','placement'=>'Placement / restrictions','next_follow_up'=>'Next follow-up'] as $k=>$label){$v=get_post_meta($id,'_elev8_'.$k,true)?:'Unavailable';echo '<dt>'.esc_html($label).'</dt><dd>'.esc_html($v).'</dd>';}echo '</dl><h3>Existing notes</h3><p>'.nl2br(esc_html($o->post_content?:'Unavailable')).'</p></section>';
        echo '<section class="elev8-panel"><h2>Record Visit</h2><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('elev8_save_visit_'.$id);echo '<input type="hidden" name="action" value="elev8_os_save_visit"><input type="hidden" name="org_id" value="'.$id.'">';self::input('visit_date','Visit date',true,'date');self::input('quantity','Flyers delivered',false,'number');self::input('contact','Person spoken with');echo '<label>Outcome<select name="outcome"><option value="delivered">Delivered</option><option value="follow_up">Follow-up needed</option><option value="closed">Closed</option><option value="refused">No longer accepts flyers</option><option value="relationship">Relationship conversation</option></select></label><label>Visit notes<textarea name="notes" rows="4"></textarea></label><button class="button button-primary">Save Visit</button></form></section>';
        $visits=(array)get_post_meta($id,'_elev8_visit_history',true);echo '<section class="elev8-panel"><h2>Visit History</h2>';if(!$visits)echo '<p>No visits recorded yet.</p>';foreach(array_reverse($visits) as $v)echo '<article class="elev8-history"><strong>'.esc_html($v['date']??'Unavailable').'</strong> — '.esc_html($v['outcome']??'Unavailable').'<p>'.esc_html($v['notes']??'').'</p><small>'.esc_html($v['contact']??'').' · '.absint($v['quantity']??0).' flyers</small></article>';echo '</section>';
    }

    private static function render_campaign(int $id): void {
        $c=get_post($id);if(!$c||$c->post_type!==self::CAMPAIGN){echo '<p>Campaign unavailable.</p>';return;}$stops=(array)get_post_meta($id,'_elev8_stops',true);echo '<p><a href="'.esc_url(admin_url('admin.php?page='.self::PAGE)).'">← Relationships</a></p><h2>'.esc_html($c->post_title).'</h2><p>Promotion: <strong>'.esc_html(get_post_meta($id,'_elev8_promotion',true)?:'Unavailable').'</strong></p><div class="elev8-stop-list">';foreach($stops as $i=>$s){$o=get_post(absint($s['org_id']??0));if(!$o)continue;$status=$s['status']??'pending';echo '<article class="elev8-stop is-'.esc_attr($status).'"><div><strong>'.esc_html($o->post_title).'</strong><p>'.esc_html(get_post_meta($o->ID,'_elev8_address',true)?:'Address unavailable').'</p></div><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('elev8_update_stop_'.$id);echo '<input type="hidden" name="action" value="elev8_os_update_stop"><input type="hidden" name="campaign_id" value="'.$id.'"><input type="hidden" name="stop_index" value="'.$i.'"><label>Status<select name="status">';foreach(['pending'=>'Pending','delivered'=>'Delivered','closed'=>'Closed','refused'=>'No flyers','follow_up'=>'Follow-up'] as $v=>$l)echo '<option value="'.$v.'" '.selected($status,$v,false).'>'.$l.'</option>';echo '</select></label><input type="number" min="0" name="quantity" placeholder="Quantity" value="'.esc_attr($s['quantity']??'').'"><textarea name="notes" rows="2" placeholder="Contact and notes">'.esc_textarea($s['notes']??'').'</textarea><button class="button button-primary">Save Stop</button></form></article>';}echo '</div>';
    }

    public static function save_org(): void { self::manager('elev8_save_org');$name=sanitize_text_field(wp_unslash($_POST['name']??''));if(!$name)self::redirect('error');$id=wp_insert_post(['post_type'=>self::ORG,'post_status'=>'publish','post_title'=>$name,'post_content'=>sanitize_textarea_field(wp_unslash($_POST['notes']??''))],true);if(is_wp_error($id))self::redirect('error');foreach(['type','address','contact','phone','email','allows_flyers','relationship_level'] as $k)update_post_meta($id,'_elev8_'.$k,sanitize_text_field(wp_unslash($_POST[$k]??'')));self::redirect('org_saved'); }
    public static function save_campaign(): void { self::manager('elev8_save_campaign');$name=sanitize_text_field(wp_unslash($_POST['name']??''));$ids=array_values(array_filter(array_map('absint',(array)($_POST['org_ids']??[]))));if(!$name||!$ids)self::redirect('error');$id=wp_insert_post(['post_type'=>self::CAMPAIGN,'post_status'=>'publish','post_title'=>$name],true);if(is_wp_error($id))self::redirect('error');update_post_meta($id,'_elev8_promotion',sanitize_text_field(wp_unslash($_POST['promotion']??'')));update_post_meta($id,'_elev8_due_date',sanitize_text_field(wp_unslash($_POST['due_date']??'')));update_post_meta($id,'_elev8_stops',array_map(fn($x)=>['org_id'=>$x,'status'=>'pending','quantity'=>0,'notes'=>''], $ids));self::redirect('campaign_saved'); }
    public static function update_stop(): void { $id=absint($_POST['campaign_id']??0);check_admin_referer('elev8_update_stop_'.$id);$st=(array)get_post_meta($id,'_elev8_stops',true);$i=absint($_POST['stop_index']??-1);if(!isset($st[$i]))self::redirect('error',$id);$status=sanitize_key($_POST['status']??'pending');$st[$i]['status']=$status;$st[$i]['quantity']=absint($_POST['quantity']??0);$st[$i]['notes']=sanitize_textarea_field(wp_unslash($_POST['notes']??''));update_post_meta($id,'_elev8_stops',$st);$org=absint($st[$i]['org_id']??0);if($status==='delivered'&&$org){update_post_meta($org,'_elev8_last_visit',current_time('Y-m-d'));}self::redirect('stop_saved',$id); }
    public static function save_visit(): void { $id=absint($_POST['org_id']??0);check_admin_referer('elev8_save_visit_'.$id);$history=(array)get_post_meta($id,'_elev8_visit_history',true);$v=['date'=>sanitize_text_field($_POST['visit_date']??''),'quantity'=>absint($_POST['quantity']??0),'contact'=>sanitize_text_field(wp_unslash($_POST['contact']??'')),'outcome'=>sanitize_key($_POST['outcome']??''),'notes'=>sanitize_textarea_field(wp_unslash($_POST['notes']??'')),'user_id'=>get_current_user_id()];$history[]=$v;update_post_meta($id,'_elev8_visit_history',$history);update_post_meta($id,'_elev8_last_visit',$v['date']);if($v['outcome']==='refused')update_post_meta($id,'_elev8_allows_flyers','no');if($v['outcome']==='follow_up')update_post_meta($id,'_elev8_relationship_level','needs_follow_up');self::redirect('visit_saved',0,$id); }

    private static function manager(string $nonce): void { check_admin_referer($nonce);$u=wp_get_current_user();if(!current_user_can('manage_options')&&!(class_exists('Elev8_OS_Access_Service')&&Elev8_OS_Access_Service::is_manager($u)))wp_die('Manager access required.'); }
    private static function redirect(string $n,int $campaign=0,int $org=0): void {$a=['page'=>self::PAGE,'elev8_notice'=>$n];if($campaign)$a['campaign']=$campaign;if($org)$a['org']=$org;wp_safe_redirect(add_query_arg($a,admin_url('admin.php')));exit;}
    private static function notice(): void {$n=sanitize_key($_GET['elev8_notice']??'');$m=['org_saved'=>'Relationship saved.','campaign_saved'=>'Campaign created.','stop_saved'=>'Campaign stop updated.','visit_saved'=>'Visit saved to relationship history.','error'=>'The request could not be saved.'];if(isset($m[$n]))echo '<div class="notice '.($n==='error'?'notice-error':'notice-success').'"><p>'.esc_html($m[$n]).'</p></div>';}
    private static function input(string $name,string $label,$required=false,string $type='text'): void {echo '<label>'.esc_html($label).'<input type="'.esc_attr($type).'" name="'.esc_attr($name).'"'.($required?' required':'').'></label>';}
}

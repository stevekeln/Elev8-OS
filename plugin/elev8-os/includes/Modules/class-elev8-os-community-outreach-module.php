<?php
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Community_Outreach_Module {
    private const ORG = 'elev8_relationship';
    private const CAMPAIGN = 'elev8_outreach';
    private const PAGE = 'elev8-community-outreach';
    private const SEEDED = 'elev8_os_outreach_seed_20260720';
    private const VENDOR_SEEDED = 'elev8_os_vendor_seed_20260720_v1';

    public static function init(): void {
        add_action('init', [__CLASS__, 'register_post_types']);
        add_action('admin_menu', [__CLASS__, 'admin_menu']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'assets']);
        foreach (['save_org','save_campaign','update_stop','save_visit'] as $action) {
            add_action('admin_post_elev8_os_' . $action, [__CLASS__, $action]);
        }
        add_action('admin_init', [__CLASS__, 'maybe_seed']);
    }

    public static function activate(): void {
        self::register_post_types();
        self::seed_relationships();
        self::seed_vendors();
    }

    public static function register_post_types(): void {
        register_post_type(self::ORG, [
            'public' => false,
            'show_ui' => false,
            'supports' => ['title','editor','thumbnail'],
            'show_in_rest' => false,
        ]);
        register_post_type(self::CAMPAIGN, [
            'public' => false,
            'show_ui' => false,
            'supports' => ['title'],
            'show_in_rest' => false,
        ]);
    }

    public static function admin_menu(): void {
        add_submenu_page('elev8-os', 'Relationships & Outreach', 'Relationships', 'read', self::PAGE, [__CLASS__, 'render']);
    }

    public static function assets(string $hook): void {
        if ($hook !== 'elev8-os_page_' . self::PAGE) { return; }
        wp_enqueue_style('elev8-os-community-outreach', ELEV8_OS_URL . 'assets/css/community-outreach.css', [], ELEV8_OS_VERSION);
        wp_enqueue_media();
        wp_add_inline_script('jquery-core', "
            jQuery(function($){
                $(document).on('click','.elev8-logo-select',function(e){
                    e.preventDefault();
                    var box=$(this).closest('.elev8-logo-field');
                    var frame=wp.media({title:'Choose relationship logo',button:{text:'Use this logo'},multiple:false});
                    frame.on('select',function(){
                        var item=frame.state().get('selection').first().toJSON();
                        box.find('input[name=logo_id]').val(item.id);
                        box.find('.elev8-logo-preview').html('<img src=\"'+item.url+'\" alt=\"Logo preview\">');
                    });
                    frame.open();
                });
                $(document).on('click','.elev8-logo-remove',function(e){
                    e.preventDefault();
                    var box=$(this).closest('.elev8-logo-field');
                    box.find('input[name=logo_id]').val('0');
                    box.find('.elev8-logo-preview').empty();
                });
            });
        ");
    }

    public static function maybe_seed(): void {
        if (!current_user_can('manage_options')) { return; }
        self::seed_relationships();
        self::seed_vendors();
    }

    private static function seed_relationships(): void {
        if (get_option(self::SEEDED)) { return; }
        self::seed_file(ELEV8_OS_DIR . 'includes/Data/community-outreach-seed.php');
        update_option(self::SEEDED, current_time('mysql'), false);
    }

    private static function seed_vendors(): void {
        if (get_option(self::VENDOR_SEEDED)) { return; }
        self::seed_file(ELEV8_OS_DIR . 'includes/Data/community-vendor-seed.php');
        update_option(self::VENDOR_SEEDED, current_time('mysql'), false);
    }

    private static function seed_file(string $file): void {
        if (!is_readable($file)) { return; }
        $items = require $file;
        foreach ((array) $items as $item) {
            $name = sanitize_text_field($item['name'] ?? '');
            if ($name === '') { continue; }

            $id = self::find_existing_relationship($item);
            if (!$id) {
                $id = wp_insert_post([
                    'post_type' => self::ORG,
                    'post_status' => 'publish',
                    'post_title' => $name,
                    'post_content' => sanitize_textarea_field($item['notes'] ?? ''),
                ], true);
                if (is_wp_error($id)) { continue; }
            } elseif (!empty($item['notes'])) {
                $post = get_post($id);
                $existing_notes = $post ? trim((string) $post->post_content) : '';
                $new_notes = sanitize_textarea_field($item['notes']);
                if ($new_notes !== '' && stripos($existing_notes, $new_notes) === false) {
                    wp_update_post(['ID' => $id, 'post_content' => trim($existing_notes . "\n" . $new_notes)]);
                }
            }

            $map = [
                'type' => '_elev8_type',
                'contact' => '_elev8_contact',
                'phone' => '_elev8_phone',
                'email' => '_elev8_email',
                'social' => '_elev8_social',
                'description' => '_elev8_description',
                'consignment' => '_elev8_consignment',
            ];
            foreach ($map as $source => $meta) {
                $value = sanitize_text_field($item[$source] ?? '');
                if ($value === '') { continue; }
                $existing = (string) get_post_meta($id, $meta, true);
                if ($source === 'type' && $value === 'Food Vendor') {
                    update_post_meta($id, $meta, $value);
                } elseif ($existing === '') {
                    update_post_meta($id, $meta, $value);
                }
            }
            if (!get_post_meta($id, '_elev8_allows_flyers', true)) {
                update_post_meta($id, '_elev8_allows_flyers', 'unknown');
            }
            if (!get_post_meta($id, '_elev8_relationship_level', true)) {
                update_post_meta($id, '_elev8_relationship_level', 'never_met');
            }
        }
    }

    private static function find_existing_relationship(array $item): int {
        $email = strtolower(trim((string) ($item['email'] ?? '')));
        if ($email !== '') {
            $ids = get_posts([
                'post_type' => self::ORG,
                'post_status' => 'publish',
                'numberposts' => 1,
                'fields' => 'ids',
                'meta_key' => '_elev8_email',
                'meta_value' => $email,
            ]);
            if ($ids) { return (int) $ids[0]; }
        }
        $existing = get_page_by_title(sanitize_text_field($item['name'] ?? ''), OBJECT, self::ORG);
        return $existing ? (int) $existing->ID : 0;
    }

    public static function render(): void {
        if (!is_user_logged_in()) { wp_die('Sign in required.'); }
        $can_manage = self::can_manage();
        $campaign = absint($_GET['campaign'] ?? 0);
        $org = absint($_GET['org'] ?? 0);

        echo '<div class="wrap elev8-outreach"><h1>Relationships & Outreach</h1>';
        echo '<p class="elev8-lead">One CRM for vendors, food vendors, flyer locations, community partners, referrals, sponsors, and relationship history.</p>';
        self::notice();
        if ($campaign) {
            self::render_campaign($campaign);
        } elseif ($org) {
            self::render_org($org, $can_manage);
        } else {
            self::render_dashboard($can_manage);
        }
        echo '</div>';
    }

    private static function render_dashboard(bool $can_manage): void {
        $orgs = get_posts(['post_type'=>self::ORG,'post_status'=>'publish','numberposts'=>-1,'orderby'=>'title','order'=>'ASC']);
        $campaigns = get_posts(['post_type'=>self::CAMPAIGN,'post_status'=>'publish','numberposts'=>20,'orderby'=>'date','order'=>'DESC']);
        $follow=0; $active=0; $unknown=0; $vendors=0; $food=0;
        foreach ($orgs as $o) {
            $r = get_post_meta($o->ID,'_elev8_relationship_level',true);
            $type = get_post_meta($o->ID,'_elev8_type',true);
            if ($r === 'needs_follow_up') { $follow++; }
            if ($r && $r !== 'never_met') { $active++; }
            if ((get_post_meta($o->ID,'_elev8_allows_flyers',true) ?: 'unknown') === 'unknown') { $unknown++; }
            if ($type === 'Vendor') { $vendors++; }
            if ($type === 'Food Vendor') { $food++; }
        }
        echo '<div class="elev8-stats">';
        foreach ([
            [count($orgs),'Relationships'],[$vendors,'Vendors'],[$food,'Food vendors'],[$follow,'Need follow-up'],[$unknown,'Flyer status unknown']
        ] as $stat) {
            echo '<article><strong>'.esc_html((string)$stat[0]).'</strong><span>'.esc_html($stat[1]).'</span></article>';
        }
        echo '</div>';

        if ($can_manage) {
            echo '<div class="elev8-outreach-grid"><section class="elev8-panel"><h2>Add Relationship</h2>';
            self::relationship_form(0);
            echo '</section><section class="elev8-panel"><h2>Create Campaign</h2><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
            wp_nonce_field('elev8_save_campaign');
            echo '<input type="hidden" name="action" value="elev8_os_save_campaign">';
            self::input('name','Campaign name','',true);
            self::input('promotion','Flyer or promotion','',true);
            self::input('due_date','Target date','','', 'date');
            echo '<label>Locations<select name="org_ids[]" multiple size="12" required>';
            foreach($orgs as $o) { echo '<option value="'.$o->ID.'">'.esc_html($o->post_title).'</option>'; }
            echo '</select></label><button class="button button-primary">Create Campaign</button></form></section></div>';
        }

        echo '<section class="elev8-panel"><h2>Campaigns</h2><div class="elev8-campaign-list">';
        if (!$campaigns) { echo '<p>No campaigns yet.</p>'; }
        foreach ($campaigns as $c) {
            $st=(array)get_post_meta($c->ID,'_elev8_stops',true);
            $done=count(array_filter($st,static fn($x)=>($x['status']??'')==='delivered'));
            echo '<a href="'.esc_url(admin_url('admin.php?page='.self::PAGE.'&campaign='.$c->ID)).'"><strong>'.esc_html($c->post_title).'</strong><span>'.$done.' of '.count($st).' delivered</span></a>';
        }
        echo '</div></section>';

        $filter = sanitize_text_field(wp_unslash($_GET['relationship_type'] ?? ''));
        echo '<section class="elev8-panel"><div class="elev8-directory-heading"><h2>Relationship Directory</h2><form method="get"><input type="hidden" name="page" value="'.esc_attr(self::PAGE).'"><select name="relationship_type"><option value="">All types</option>';
        foreach (['Vendor','Food Vendor','Dispensary','Community Partner','Sponsor','Wholesale Prospect','Referral Partner'] as $type) {
            echo '<option value="'.esc_attr($type).'"'.selected($filter,$type,false).'>'.esc_html($type).'</option>';
        }
        echo '</select><button class="button">Filter</button></form></div><div class="elev8-org-list">';
        foreach ($orgs as $o) {
            $type=get_post_meta($o->ID,'_elev8_type',true)?:'Unavailable';
            if ($filter !== '' && $filter !== $type) { continue; }
            $last=get_post_meta($o->ID,'_elev8_last_visit',true)?:'Unavailable';
            $allows=get_post_meta($o->ID,'_elev8_allows_flyers',true)?:'unknown';
            $logo=self::logo_html($o->ID,'thumbnail');
            echo '<article>'.$logo.'<div><a href="'.esc_url(admin_url('admin.php?page='.self::PAGE.'&org='.$o->ID)).'"><strong>'.esc_html($o->post_title).'</strong></a><span>'.esc_html($type).'</span><p>'.esc_html(get_post_meta($o->ID,'_elev8_description',true)?:get_post_meta($o->ID,'_elev8_address',true)?:'Details unavailable').'</p><small>Flyers: '.esc_html(ucfirst($allows)).' · Last visit: '.esc_html($last).'</small></div></article>';
        }
        echo '</div></section>';
    }

    private static function render_org(int $id, bool $can_manage): void {
        $o=get_post($id);
        if(!$o || $o->post_type!==self::ORG){ echo '<p>Relationship unavailable.</p>'; return; }
        echo '<p><a href="'.esc_url(admin_url('admin.php?page='.self::PAGE)).'">← Relationships</a></p>';
        $logo = self::logo_html($id, 'medium');
        echo '<section class="elev8-panel elev8-relationship-profile">'.$logo.'<div><h2>'.esc_html($o->post_title).'</h2>';
        $address=get_post_meta($id,'_elev8_address',true);
        if($address) { echo '<p><a target="_blank" rel="noopener" href="'.esc_url('https://www.google.com/maps/search/?api=1&query='.rawurlencode($address)).'">Open directions</a></p>'; }
        echo '<dl class="elev8-details">';
        foreach([
            'type'=>'Type','address'=>'Address','contact'=>'Contact','phone'=>'Phone','email'=>'Email','social'=>'Social media',
            'description'=>'What they make / offer','consignment'=>'Consignment interest','allows_flyers'=>'Allows flyers',
            'relationship_level'=>'Relationship','best_time'=>'Best visit time','placement'=>'Placement / restrictions','next_follow_up'=>'Next follow-up'
        ] as $k=>$label){
            $v=get_post_meta($id,'_elev8_'.$k,true)?:'Unavailable';
            echo '<dt>'.esc_html($label).'</dt><dd>'.nl2br(esc_html($v)).'</dd>';
        }
        echo '</dl><h3>Existing notes</h3><p>'.nl2br(esc_html($o->post_content?:'Unavailable')).'</p></div></section>';

        if ($can_manage) {
            echo '<section class="elev8-panel"><h2>Edit Relationship</h2>';
            self::relationship_form($id);
            echo '</section>';
        }

        echo '<section class="elev8-panel"><h2>Record Visit</h2><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        wp_nonce_field('elev8_save_visit_'.$id);
        echo '<input type="hidden" name="action" value="elev8_os_save_visit"><input type="hidden" name="org_id" value="'.$id.'">';
        self::input('visit_date','Visit date',current_time('Y-m-d'),true,'date');
        self::input('quantity','Flyers delivered','',false,'number');
        self::input('contact','Person spoken with');
        echo '<label>Outcome<select name="outcome"><option value="delivered">Delivered</option><option value="follow_up">Follow-up needed</option><option value="closed">Closed</option><option value="refused">No longer accepts flyers</option><option value="relationship">Relationship conversation</option></select></label>';
        echo '<label>Visit notes<textarea name="notes" rows="4"></textarea></label><button class="button button-primary">Save Visit</button></form></section>';

        $visits=(array)get_post_meta($id,'_elev8_visit_history',true);
        echo '<section class="elev8-panel"><h2>Visit History</h2>';
        if(!$visits){ echo '<p>No visits recorded.</p>'; }
        foreach(array_reverse($visits) as $v){
            $user=get_userdata(absint($v['user_id']??0));
            echo '<article class="elev8-visit"><strong>'.esc_html($v['date']??'Date unavailable').'</strong><span>'.esc_html(ucwords(str_replace('_',' ',$v['outcome']??''))).'</span><p>'.esc_html($v['notes']??'').'</p><small>'.absint($v['quantity']??0).' flyers · '.esc_html($v['contact']??'No contact').' · '.esc_html($user?$user->display_name:'User unavailable').'</small></article>';
        }
        echo '</section>';
    }

    private static function relationship_form(int $id): void {
        $post = $id ? get_post($id) : null;
        $meta = static function(string $key) use ($id): string {
            return $id ? (string)get_post_meta($id,'_elev8_'.$key,true) : '';
        };
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        wp_nonce_field('elev8_save_org');
        echo '<input type="hidden" name="action" value="elev8_os_save_org"><input type="hidden" name="org_id" value="'.esc_attr((string)$id).'">';
        self::input('name','Business or organization name',$post?$post->post_title:'',true);
        echo '<label>Type<select name="type">';
        foreach(['Vendor','Food Vendor','Dispensary','Community Partner','Sponsor','Wholesale Prospect','Referral Partner','Artist','Teacher','Other'] as $type){
            echo '<option value="'.esc_attr($type).'"'.selected($meta('type'),$type,false).'>'.esc_html($type).'</option>';
        }
        echo '</select></label>';
        self::input('address','Address',$meta('address'));
        self::input('contact','Primary contact',$meta('contact'));
        self::input('phone','Phone',$meta('phone'));
        self::input('email','Email',$meta('email'),false,'email');
        self::input('social','Social media / links',$meta('social'));
        echo '<label>What they make or offer<textarea name="description" rows="3">'.esc_textarea($meta('description')).'</textarea></label>';
        self::input('consignment','Consignment interest',$meta('consignment'));
        echo '<label>Allows flyers<select name="allows_flyers">';
        foreach(['unknown'=>'Unknown','yes'=>'Yes','sometimes'=>'Sometimes','no'=>'No'] as $value=>$label){ echo '<option value="'.$value.'"'.selected($meta('allows_flyers')?:'unknown',$value,false).'>'.$label.'</option>'; }
        echo '</select></label><label>Relationship<select name="relationship_level">';
        foreach(['never_met'=>'Never met','cold'=>'Cold','friend'=>'Friend','good_customer'=>'Good customer','future_opportunity'=>'Future opportunity','needs_follow_up'=>'Needs follow-up'] as $value=>$label){ echo '<option value="'.$value.'"'.selected($meta('relationship_level')?:'never_met',$value,false).'>'.$label.'</option>'; }
        echo '</select></label>';
        self::input('best_time','Best visit time',$meta('best_time'));
        self::input('placement','Placement / restrictions',$meta('placement'));
        self::input('next_follow_up','Next follow-up',$meta('next_follow_up'),false,'date');
        $logo_id=absint($meta('logo_id'));
        echo '<div class="elev8-logo-field"><label>Logo</label><input type="hidden" name="logo_id" value="'.$logo_id.'"><div class="elev8-logo-preview">'.($logo_id?wp_get_attachment_image($logo_id,'medium'):'').'</div><p><button class="button elev8-logo-select">Choose logo</button> <button class="button-link-delete elev8-logo-remove">Remove</button></p></div>';
        echo '<label>Notes<textarea name="notes" rows="4">'.esc_textarea($post?$post->post_content:'').'</textarea></label><button class="button button-primary">'.($id?'Update Relationship':'Save Relationship').'</button></form>';
    }

    private static function render_campaign(int $id): void {
        $c=get_post($id);
        if(!$c || $c->post_type!==self::CAMPAIGN){ echo '<p>Campaign unavailable.</p>'; return; }
        $st=(array)get_post_meta($id,'_elev8_stops',true);
        echo '<p><a href="'.esc_url(admin_url('admin.php?page='.self::PAGE)).'">← Relationships</a></p><section class="elev8-panel"><h2>'.esc_html($c->post_title).'</h2><p>'.esc_html(get_post_meta($id,'_elev8_promotion',true)).' · Target '.esc_html(get_post_meta($id,'_elev8_due_date',true)?:'Unavailable').'</p></section><section class="elev8-panel"><h2>Route & Stops</h2>';
        foreach($st as $i=>$x){
            $o=get_post(absint($x['org_id']??0)); if(!$o)continue;
            echo '<form class="elev8-stop" method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
            wp_nonce_field('elev8_update_stop_'.$id);
            echo '<input type="hidden" name="action" value="elev8_os_update_stop"><input type="hidden" name="campaign_id" value="'.$id.'"><input type="hidden" name="stop_index" value="'.$i.'"><div><strong>'.esc_html($o->post_title).'</strong><small>'.esc_html(get_post_meta($o->ID,'_elev8_address',true)?:'Address unavailable').'</small></div><select name="status">';
            foreach(['pending'=>'Pending','delivered'=>'Delivered','closed'=>'Closed','refused'=>'No longer accepts flyers','follow_up'=>'Follow-up needed'] as $k=>$v)echo '<option value="'.$k.'"'.selected($x['status']??'pending',$k,false).'>'.$v.'</option>';
            echo '</select><input name="quantity" type="number" min="0" value="'.absint($x['quantity']??0).'" placeholder="Qty"><input name="notes" value="'.esc_attr($x['notes']??'').'" placeholder="Visit notes"><button class="button">Save</button></form>';
        }
        echo '</section>';
    }

    public static function save_org(): void {
        self::manager('elev8_save_org');
        $id=absint($_POST['org_id']??0);
        $name=sanitize_text_field(wp_unslash($_POST['name']??''));
        if(!$name){ self::redirect('error',0,$id); }
        $payload=['post_type'=>self::ORG,'post_status'=>'publish','post_title'=>$name,'post_content'=>sanitize_textarea_field(wp_unslash($_POST['notes']??''))];
        if($id){ $payload['ID']=$id; $saved=wp_update_post($payload,true); } else { $saved=wp_insert_post($payload,true); }
        if(is_wp_error($saved)){ self::redirect('error',0,$id); }
        $id=(int)$saved;
        foreach(['type','address','contact','phone','email','social','description','consignment','allows_flyers','relationship_level','best_time','placement','next_follow_up'] as $k){
            update_post_meta($id,'_elev8_'.$k,sanitize_text_field(wp_unslash($_POST[$k]??'')));
        }
        update_post_meta($id,'_elev8_logo_id',absint($_POST['logo_id']??0));
        self::redirect('org_saved',0,$id);
    }

    public static function save_campaign(): void {
        self::manager('elev8_save_campaign');
        $name=sanitize_text_field(wp_unslash($_POST['name']??''));
        $ids=array_values(array_filter(array_map('absint',(array)($_POST['org_ids']??[]))));
        if(!$name||!$ids)self::redirect('error');
        $id=wp_insert_post(['post_type'=>self::CAMPAIGN,'post_status'=>'publish','post_title'=>$name],true);
        if(is_wp_error($id))self::redirect('error');
        update_post_meta($id,'_elev8_promotion',sanitize_text_field(wp_unslash($_POST['promotion']??'')));
        update_post_meta($id,'_elev8_due_date',sanitize_text_field(wp_unslash($_POST['due_date']??'')));
        update_post_meta($id,'_elev8_stops',array_map(static fn($x)=>['org_id'=>$x,'status'=>'pending','quantity'=>0,'notes'=>''], $ids));
        self::redirect('campaign_saved');
    }

    public static function update_stop(): void {
        $id=absint($_POST['campaign_id']??0);
        check_admin_referer('elev8_update_stop_'.$id);
        $st=(array)get_post_meta($id,'_elev8_stops',true);
        $i=absint($_POST['stop_index']??-1);
        if(!isset($st[$i]))self::redirect('error',$id);
        $status=sanitize_key($_POST['status']??'pending');
        $st[$i]['status']=$status;
        $st[$i]['quantity']=absint($_POST['quantity']??0);
        $st[$i]['notes']=sanitize_textarea_field(wp_unslash($_POST['notes']??''));
        update_post_meta($id,'_elev8_stops',$st);
        $org=absint($st[$i]['org_id']??0);
        if($status==='delivered'&&$org){update_post_meta($org,'_elev8_last_visit',current_time('Y-m-d'));}
        self::redirect('stop_saved',$id);
    }

    public static function save_visit(): void {
        $id=absint($_POST['org_id']??0);
        check_admin_referer('elev8_save_visit_'.$id);
        $history=(array)get_post_meta($id,'_elev8_visit_history',true);
        $v=['date'=>sanitize_text_field($_POST['visit_date']??''),'quantity'=>absint($_POST['quantity']??0),'contact'=>sanitize_text_field(wp_unslash($_POST['contact']??'')),'outcome'=>sanitize_key($_POST['outcome']??''),'notes'=>sanitize_textarea_field(wp_unslash($_POST['notes']??'')),'user_id'=>get_current_user_id()];
        $history[]=$v;
        update_post_meta($id,'_elev8_visit_history',$history);
        update_post_meta($id,'_elev8_last_visit',$v['date']);
        if($v['outcome']==='refused')update_post_meta($id,'_elev8_allows_flyers','no');
        if($v['outcome']==='follow_up')update_post_meta($id,'_elev8_relationship_level','needs_follow_up');
        self::redirect('visit_saved',0,$id);
    }

    private static function logo_html(int $id, string $size): string {
        $logo_id=absint(get_post_meta($id,'_elev8_logo_id',true));
        return $logo_id ? '<div class="elev8-relationship-logo">'.wp_get_attachment_image($logo_id,$size,false,['alt'=>get_the_title($id).' logo']).'</div>' : '';
    }

    private static function can_manage(): bool {
        return current_user_can('manage_options') || (class_exists('Elev8_OS_Access_Service') && Elev8_OS_Access_Service::is_manager(wp_get_current_user()));
    }

    private static function manager(string $nonce): void {
        check_admin_referer($nonce);
        if(!self::can_manage())wp_die('Manager access required.');
    }

    private static function redirect(string $n,int $campaign=0,int $org=0): void {
        $a=['page'=>self::PAGE,'elev8_notice'=>$n];
        if($campaign)$a['campaign']=$campaign;
        if($org)$a['org']=$org;
        wp_safe_redirect(add_query_arg($a,admin_url('admin.php')));
        exit;
    }

    private static function notice(): void {
        $n=sanitize_key($_GET['elev8_notice']??'');
        $m=['org_saved'=>'Relationship saved.','campaign_saved'=>'Campaign created.','stop_saved'=>'Campaign stop updated.','visit_saved'=>'Visit saved to relationship history.','error'=>'The request could not be saved.'];
        if(isset($m[$n]))echo '<div class="notice '.($n==='error'?'notice-error':'notice-success').'"><p>'.esc_html($m[$n]).'</p></div>';
    }

    private static function input(string $name,string $label,string $value='',$required=false,string $type='text'): void {
        echo '<label>'.esc_html($label).'<input type="'.esc_attr($type).'" name="'.esc_attr($name).'" value="'.esc_attr($value).'"'.($required?' required':'').'></label>';
    }
}

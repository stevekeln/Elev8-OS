<?php
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Class_Demand_Module {
    private const SLUG = 'elev8-class-demand';

    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'admin_menu'], 35);
        add_action('admin_init', ['Elev8_OS_Opportunity_Service', 'maybe_upgrade']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'assets']);
        add_action('admin_post_elev8_os_opportunity_save', [__CLASS__, 'handle_save']);
        add_action('admin_post_elev8_os_interest_save', [__CLASS__, 'handle_interest']);
        add_action('admin_post_elev8_os_interest_delete', [__CLASS__, 'handle_interest_delete']);
    }
    public static function activate(): void { Elev8_OS_Opportunity_Service::activate(); }
    public static function admin_menu(): void { add_submenu_page('elev8-os','Class Demand','Class Demand','manage_options',self::SLUG,[__CLASS__,'render']); }
    public static function assets(string $hook): void {
        if ($hook !== 'elev8-os_page_' . self::SLUG) { return; }
        wp_enqueue_style('elev8-os-class-demand', ELEV8_OS_URL . 'assets/css/class-demand.css', [], ELEV8_OS_VERSION);
    }
    private static function url(array $args=[]): string { return add_query_arg(array_merge(['page'=>self::SLUG],$args), admin_url('admin.php')); }
    private static function require_admin(): void { if (!current_user_can('manage_options')) { wp_die(esc_html__('You do not have permission to manage class demand.','elev8-os')); } }

    public static function handle_save(): void {
        self::require_admin(); check_admin_referer('elev8_os_opportunity_save');
        $id = Elev8_OS_Opportunity_Service::save(wp_unslash($_POST));
        wp_safe_redirect(self::url(['saved'=>$id>0?'1':'0','edit'=>$id])); exit;
    }
    public static function handle_interest(): void {
        self::require_admin(); check_admin_referer('elev8_os_interest_save');
        $opportunity_id=absint($_POST['opportunity_id']??0); Elev8_OS_Opportunity_Service::add_interest(wp_unslash($_POST));
        wp_safe_redirect(self::url(['edit'=>$opportunity_id,'interest_saved'=>'1'])); exit;
    }
    public static function handle_interest_delete(): void {
        self::require_admin(); $id=absint($_POST['id']??0); $opportunity_id=absint($_POST['opportunity_id']??0); check_admin_referer('elev8_os_interest_delete_'.$id);
        Elev8_OS_Opportunity_Service::delete_interest($id); wp_safe_redirect(self::url(['edit'=>$opportunity_id,'interest_deleted'=>'1'])); exit;
    }

    public static function render(): void {
        self::require_admin();
        $edit_id=absint($_GET['edit']??0); $current=$edit_id?Elev8_OS_Opportunity_Service::find($edit_id):null;
        $metrics=Elev8_OS_Opportunity_Service::metrics(); $opportunities=Elev8_OS_Opportunity_Service::all();
        echo '<div class="wrap elev8-demand"><h1>'.esc_html__('Class Demand Manager','elev8-os').'</h1><p class="description">'.esc_html__('Capture customer demand before a class exists. Elev8 OS owns this data; Amelia remains the scheduling integration.','elev8-os').'</p>';
        if(isset($_GET['saved'])) echo '<div class="notice notice-success is-dismissible"><p>'.esc_html__('Class idea saved.','elev8-os').'</p></div>';
        self::render_metrics($metrics);
        echo '<div class="elev8-demand-layout"><section class="elev8-demand-card">'; self::render_form($current); echo '</section><section class="elev8-demand-card"><h2>'.esc_html__('Class ideas','elev8-os').'</h2>'; self::render_list($opportunities); echo '</section></div>';
        if($current){ echo '<section class="elev8-demand-card elev8-demand-interest">'; self::render_interest($current); echo '</section>'; }
        echo '</div>';
    }

    private static function render_metrics(array $m): void {
        $revenue=$m['revenue_available']?'$'.number_format_i18n((float)$m['potential_revenue'],2):__('Unavailable','elev8-os');
        echo '<div class="elev8-demand-metrics">';
        foreach ([['Active ideas',$m['active_ideas']],['People interested',$m['people_waiting']],['Seats requested',$m['seats_requested']],['Need a teacher',$m['teacher_needed']],['Potential revenue',$revenue]] as $item) {
            echo '<div class="elev8-demand-metric"><span>'.esc_html__($item[0],'elev8-os').'</span><strong>'.esc_html((string)$item[1]).'</strong></div>';
        }
        echo '</div>';
    }

    private static function render_form(?array $v): void {
        $v=$v?:[]; $g=static fn($k,$d='')=>(string)($v[$k]??$d);
        echo '<h2>'.($v?esc_html__('Edit class idea','elev8-os'):esc_html__('Add a new class idea','elev8-os')).'</h2><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="elev8_os_opportunity_save"><input type="hidden" name="id" value="'.esc_attr($g('id','0')).'">'; wp_nonce_field('elev8_os_opportunity_save');
        echo '<div class="elev8-demand-fields"><p class="wide"><label>Class name *</label><input required type="text" name="title" value="'.esc_attr($g('title')).'"></p><p><label>Category</label><input type="text" name="category" value="'.esc_attr($g('category')).'"></p><p><label>Status</label><select name="status">'; foreach(Elev8_OS_Opportunity_Service::statuses() as $k=>$label) echo '<option value="'.esc_attr($k).'" '.selected($g('status','idea'),$k,false).'>'.esc_html($label).'</option>'; echo '</select></p>';
        echo '<p class="wide"><label>Description</label><textarea name="description" rows="3">'.esc_textarea($g('description')).'</textarea></p><p><label>Estimated price per seat</label><input type="number" min="0" step="0.01" name="estimated_price" value="'.esc_attr($g('estimated_price')).'"></p><p><label>Estimated duration (hours)</label><input type="number" min="0" step="0.25" name="estimated_duration" value="'.esc_attr($g('estimated_duration')).'"></p><p><label>Preferred day</label><input type="text" name="preferred_day" value="'.esc_attr($g('preferred_day')).'"></p><p><label>Preferred time</label><input type="text" name="preferred_time" value="'.esc_attr($g('preferred_time')).'"></p><p><label>Difficulty</label><input type="text" name="difficulty" value="'.esc_attr($g('difficulty')).'"></p><p class="checkbox"><label><input type="checkbox" name="teacher_needed" value="1" '.checked($g('teacher_needed','0'),'1',false).'> Teacher needed</label></p><p><label>Assigned teacher</label><input type="text" name="teacher_assigned" value="'.esc_attr($g('teacher_assigned')).'"></p><p><label>Teacher contact</label><input type="text" name="teacher_contact" value="'.esc_attr($g('teacher_contact')).'"></p><p class="wide"><label>Supplies needed</label><textarea name="supplies_needed" rows="2">'.esc_textarea($g('supplies_needed')).'</textarea></p><p class="wide"><label>Internal notes</label><textarea name="notes" rows="3">'.esc_textarea($g('notes')).'</textarea></p></div><p><button class="button button-primary" type="submit">'.esc_html__('Save class idea','elev8-os').'</button> '; if($v) echo '<a class="button" href="'.esc_url(self::url()).'">'.esc_html__('Add another','elev8-os').'</a>'; echo '</p></form>';
    }

    private static function render_list(array $rows): void {
        if(!$rows){echo '<p>'.esc_html__('No class ideas yet. Add the first idea using the form.','elev8-os').'</p>';return;}
        echo '<div class="elev8-demand-table-wrap"><table class="widefat striped"><thead><tr><th>Class</th><th>Status</th><th>Interest</th><th>Potential</th><th></th></tr></thead><tbody>';
        foreach($rows as $r){$potential=$r['estimated_price']===null&&((int)$r['interested_seats']>0)?__('Unavailable','elev8-os'):'$'.number_format_i18n((float)$r['estimated_price']*(int)$r['interested_seats'],2); echo '<tr><td><strong>'.esc_html($r['title']).'</strong><br><small>'.esc_html($r['category']?:'Uncategorized').(!empty($r['teacher_needed'])?' • Teacher needed':'').'</small></td><td>'.esc_html(Elev8_OS_Opportunity_Service::statuses()[$r['status']]??$r['status']).'</td><td>'.esc_html((string)$r['interested_people']).' people / '.esc_html((string)$r['interested_seats']).' seats</td><td>'.esc_html($potential).'</td><td><a class="button button-small" href="'.esc_url(self::url(['edit'=>(int)$r['id']])).'">Open</a></td></tr>';}
        echo '</tbody></table></div>';
    }

    private static function render_interest(array $o): void {
        $rows=Elev8_OS_Opportunity_Service::interests((int)$o['id']);
        echo '<h2>'.sprintf(esc_html__('Customer interest: %s','elev8-os'),esc_html($o['title'])).'</h2><div class="elev8-demand-layout"><div><h3>'.esc_html__('Add interested customer','elev8-os').'</h3><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="elev8_os_interest_save"><input type="hidden" name="opportunity_id" value="'.esc_attr((string)$o['id']).'">'; wp_nonce_field('elev8_os_interest_save'); echo '<div class="elev8-demand-fields"><p><label>Name *</label><input required type="text" name="customer_name"></p><p><label>Email</label><input type="email" name="customer_email"></p><p><label>Phone</label><input type="text" name="customer_phone"></p><p><label>Seats requested</label><input type="number" min="1" max="50" name="seats_requested" value="1"></p><p><label>Preferred days</label><input type="text" name="preferred_days"></p><p><label>Preferred times</label><input type="text" name="preferred_times"></p><p class="wide"><label>Notes</label><textarea name="notes" rows="2"></textarea></p><p><label>Source</label><input type="text" name="source" value="admin"></p></div><p><button class="button button-primary">'.esc_html__('Add interest','elev8-os').'</button></p></form></div><div><h3>'.esc_html__('Interested customers','elev8-os').'</h3>';
        if(!$rows){echo '<p>'.esc_html__('No customers have registered interest yet.','elev8-os').'</p>';} else {echo '<table class="widefat striped"><thead><tr><th>Customer</th><th>Seats</th><th>Preferences</th><th></th></tr></thead><tbody>'; foreach($rows as $r){echo '<tr><td><strong>'.esc_html($r['customer_name']).'</strong><br><small>'.esc_html(trim($r['customer_email'].' '.$r['customer_phone'])).'</small></td><td>'.esc_html((string)$r['seats_requested']).'</td><td>'.esc_html(trim($r['preferred_days'].' '.$r['preferred_times'])).'</td><td><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="elev8_os_interest_delete"><input type="hidden" name="id" value="'.esc_attr((string)$r['id']).'"><input type="hidden" name="opportunity_id" value="'.esc_attr((string)$o['id']).'">';wp_nonce_field('elev8_os_interest_delete_'.$r['id']);echo '<button class="button-link-delete">Remove</button></form></td></tr>';} echo '</tbody></table>';}
        echo '</div></div>';
    }
}

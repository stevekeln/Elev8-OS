<?php
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Observation_Registry_Module {
    private const OPTION_PAGE_ID = 'elev8_os_observation_registry_page_id';
    private const SLUG = 'elev8-intelligence-review';

    public static function init(): void {
        add_shortcode('elev8_os_observation_registry', [__CLASS__, 'shortcode']);
        add_action('admin_init', [__CLASS__, 'ensure_page_for_admin']);
        add_action('admin_post_elev8_os_review_observation', [__CLASS__, 'review']);
        add_action('admin_post_elev8_os_review_pattern', [__CLASS__, 'review_pattern']);
        add_action('admin_post_elev8_os_scan_patterns', [__CLASS__, 'scan_patterns']);
        add_action('admin_post_elev8_os_promote_pattern', [__CLASS__, 'promote_pattern']);
        add_action('admin_post_elev8_os_decide_recommendation', [__CLASS__, 'decide_recommendation']);
        add_action('admin_post_elev8_os_record_recommendation_outcome', [__CLASS__, 'record_recommendation_outcome']);
        add_filter('elev8_os_application_shell_frontend', [__CLASS__, 'shell_page']);
        add_filter('elev8_os_command_palette_commands', [__CLASS__, 'command'], 10, 2);
    }
    public static function activate(): void { self::ensure_page(true); }
    public static function ensure_page_for_admin(): void { if (current_user_can('manage_options')) { self::ensure_page(true); } }
    public static function shortcode(): string {
        if (!is_user_logged_in()) { return '<p>'.esc_html__('Please sign in.', 'elev8-os').'</p>'; }
        $user = wp_get_current_user();
        if (!self::can_review($user)) { return '<p>'.esc_html__('You do not have permission to review intelligence.', 'elev8-os').'</p>'; }
        $view = sanitize_key((string)($_GET['view'] ?? 'observations'));
        if ($view === 'executive') { return self::executive_view(); }
        if ($view === 'patterns') { return self::patterns_view(); }
        if ($view === 'recommendations') { return self::recommendations_view(); }
        $filters = [
            'classification'=>sanitize_key((string)($_GET['classification'] ?? '')),
            'severity'=>sanitize_key((string)($_GET['severity'] ?? '')),
            'source_type'=>sanitize_key((string)($_GET['source_type'] ?? '')),
            'review_status'=>sanitize_key((string)($_GET['review_status'] ?? 'unreviewed')),
            'posts_per_page'=>200,
        ];
        $items = Elev8_OS_Observation_Service::query($filters);
        $summary = Elev8_OS_Observation_Service::summary();
        ob_start();
        echo '<div class="elev8-observation-registry"><header><p>'.esc_html__('INTELLIGENCE ENGINE', 'elev8-os').'</p><h1>'.esc_html__('Observation Review', 'elev8-os').'</h1><span>'.esc_html__('Confirm, correct, or dismiss verified facts before they drive higher-level recommendations.', 'elev8-os').'</span></header>';
        self::tabs('observations');
        echo '<div style="display:flex;gap:12px;flex-wrap:wrap;margin:20px 0">';
        foreach (['total'=>'Total','risk'=>'Risks','opportunity'=>'Opportunities','critical'=>'Critical'] as $key=>$label) { echo '<div style="background:#fff;border:1px solid #ddd;border-radius:12px;padding:14px 18px"><strong style="font-size:24px">'.(int)($summary[$key]??0).'</strong><div>'.esc_html($label).'</div></div>'; }
        echo '</div><form method="get" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px"><select name="review_status"><option value="">'.esc_html__('All review states','elev8-os').'</option>';
        foreach (['unreviewed'=>'Unreviewed','confirmed'=>'Confirmed','corrected'=>'Corrected','dismissed'=>'Dismissed'] as $v=>$l) { echo '<option value="'.esc_attr($v).'" '.selected($filters['review_status'],$v,false).'>'.esc_html($l).'</option>'; }
        echo '</select><select name="classification"><option value="">'.esc_html__('All classifications','elev8-os').'</option>';
        foreach (['risk','opportunity','decision','achievement','follow_up','information'] as $v) { echo '<option value="'.esc_attr($v).'" '.selected($filters['classification'],$v,false).'>'.esc_html(ucwords(str_replace('_',' ',$v))).'</option>'; }
        echo '</select><select name="severity"><option value="">'.esc_html__('All severity','elev8-os').'</option>';
        foreach (['low','normal','high','critical'] as $v) { echo '<option value="'.esc_attr($v).'" '.selected($filters['severity'],$v,false).'>'.esc_html(ucfirst($v)).'</option>'; }
        echo '</select><input name="source_type" value="'.esc_attr($filters['source_type']).'" placeholder="'.esc_attr__('Source type','elev8-os').'"><button>'.esc_html__('Filter','elev8-os').'</button></form>';
        if (!$items) { echo '<div style="background:#fff;border:1px solid #ddd;border-radius:12px;padding:24px"><strong>'.esc_html__('No observations match these filters.','elev8-os').'</strong></div>'; }
        foreach ($items as $item) { self::card($item); }
        echo '</div>';
        return (string)ob_get_clean();
    }
    private static function card(array $item): void {
        echo '<article style="background:#fff;border:1px solid #ddd;border-radius:14px;padding:18px;margin:0 0 14px"><div style="display:flex;justify-content:space-between;gap:20px"><div><div style="font-size:12px;text-transform:uppercase">'.esc_html($item['source_type'].' · '.$item['severity']).'</div><h3 style="margin:6px 0">'.esc_html($item['title']).'</h3><p>'.esc_html($item['summary']).'</p><small>'.esc_html(implode(', ',(array)$item['classifications'])).' · '.esc_html($item['occurred_at']).'</small></div><strong>'.esc_html(ucfirst($item['review_status'])).'</strong></div>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:flex;gap:8px;flex-wrap:wrap;margin-top:14px">';
        wp_nonce_field('elev8_review_observation_'.$item['id']);
        echo '<input type="hidden" name="action" value="elev8_os_review_observation"><input type="hidden" name="observation_id" value="'.(int)$item['id'].'"><select name="review_status">';
        foreach (['confirmed'=>'Confirm','corrected'=>'Mark corrected','dismissed'=>'Dismiss','unreviewed'=>'Return to unreviewed'] as $v=>$l) { echo '<option value="'.esc_attr($v).'">'.esc_html($l).'</option>'; }
        echo '</select><input name="review_notes" placeholder="'.esc_attr__('Review note (optional)','elev8-os').'" style="min-width:260px"><button>'.esc_html__('Save review','elev8-os').'</button></form></article>';
    }

    private static function tabs(string $active): void {
        $base = self::url();
        echo '<nav style="display:flex;gap:8px;flex-wrap:wrap;margin:18px 0">';
        foreach (['executive'=>__('Executive Intelligence','elev8-os'),'observations'=>__('Observations','elev8-os'),'patterns'=>__('Patterns','elev8-os'),'recommendations'=>__('Recommendations','elev8-os')] as $key=>$label) {
            $url = $key === 'observations' ? $base : add_query_arg('view',$key,$base);
            $style = $active === $key ? 'background:#5b21b6;color:#fff;' : 'background:#fff;color:#2d1b55;';
            echo '<a href="'.esc_url($url).'" style="'.esc_attr($style).'border:1px solid #d8c8ff;border-radius:999px;padding:9px 14px;text-decoration:none;font-weight:700">'.esc_html($label).'</a>';
        }
        echo '</nav>';
    }

    private static function executive_view(): string {
        if (!class_exists('Elev8_OS_Executive_Intelligence_Read_Model_Service')) { return '<p>'.esc_html__('Executive Intelligence is unavailable.','elev8-os').'</p>'; }
        $report=Elev8_OS_Executive_Intelligence_Read_Model_Service::report();
        $patterns=(array)($report['pattern_summary']??[]);
        $recommendations=(array)($report['recommendation_summary']??[]);
        $performance=(array)($report['performance']??[]);
        $confidence=(array)($report['confidence']??[]);
        ob_start();
        echo '<div class="elev8-observation-registry"><header><p>'.esc_html__('INTELLIGENCE ENGINE','elev8-os').'</p><h1>'.esc_html__('Executive Intelligence','elev8-os').'</h1><span>'.esc_html__('A governed view of the risks, opportunities, decisions, and measured outcomes that deserve executive attention.','elev8-os').'</span></header>';
        self::tabs('executive');
        echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;margin:20px 0">';
        self::metric_card(__('Active risks','elev8-os'),(int)($patterns['risks']??0),__('Confirmed recurring risks','elev8-os'));
        self::metric_card(__('Opportunities','elev8-os'),(int)($patterns['opportunities']??0),__('Confirmed recurring opportunities','elev8-os'));
        self::metric_card(__('Decisions waiting','elev8-os'),(int)($recommendations['proposed']??0),__('Proposed Recommendations','elev8-os'));
        self::metric_card(__('Outcomes waiting','elev8-os'),(int)($recommendations['awaiting_outcome']??0),__('Approved actions needing measurement','elev8-os'));
        $score=!empty($performance['available'])?(string)((int)($performance['score']??0)).'%':'—';
        self::metric_card(__('Recommendation performance','elev8-os'),$score,(string)($performance['label']??__('Awaiting outcomes','elev8-os')));
        echo '</div>';
        echo '<section style="background:#fff;border:1px solid #ddd;border-radius:14px;padding:18px;margin-bottom:18px"><div style="display:flex;justify-content:space-between;gap:20px;align-items:flex-start"><div><p style="margin:0;font-size:12px;text-transform:uppercase;font-weight:700">'.esc_html__('BEST USE OF EXECUTIVE ATTENTION','elev8-os').'</p><h2 style="margin:6px 0">'.esc_html__('What deserves attention now','elev8-os').'</h2></div><strong>'.esc_html((string)($confidence['label']??'Low')).' '.esc_html__('confidence','elev8-os').'</strong></div>';
        $attention=(array)($report['attention']??[]);
        if(!$attention){echo '<p>'.esc_html__('No governed intelligence currently requires executive attention.','elev8-os').'</p>';}
        foreach($attention as $index=>$item){$target=(string)($item['target']??'patterns');$url=add_query_arg('view',$target,self::url());echo '<article style="border-top:1px solid #eee;padding:14px 0"><div style="display:flex;gap:12px"><strong style="min-width:28px">'.(int)($index+1).'.</strong><div><a href="'.esc_url($url).'" style="font-weight:800;text-decoration:none">'.esc_html((string)($item['title']??'')).'</a><p style="margin:5px 0 0">'.esc_html((string)($item['reason']??'')).'</p><small>'.esc_html(ucwords((string)($item['kind']??'attention'))).'</small></div></div></article>';}
        echo '<p style="margin-bottom:0"><small>'.esc_html((string)($confidence['explanation']??'')).'</small></p></section>';
        echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:18px">';
        self::executive_pattern_section(__('Highest-priority risks','elev8-os'),(array)($report['top_risks']??[]),'risk');
        self::executive_pattern_section(__('Strongest opportunities','elev8-os'),(array)($report['top_opportunities']??[]),'opportunity');
        echo '</div>';
        if(!empty($performance['explanations'])){echo '<section style="background:#fff;border:1px solid #ddd;border-radius:14px;padding:18px;margin-top:18px"><h2>'.esc_html__('Why this performance score?','elev8-os').'</h2><ul>';foreach((array)$performance['explanations'] as $line){echo '<li>'.esc_html((string)$line).'</li>';}echo '</ul></section>';}
        echo '</div>';
        return (string)ob_get_clean();
    }

    private static function metric_card(string $label, $value, string $detail): void {
        echo '<div style="background:#fff;border:1px solid #ddd;border-radius:12px;padding:16px"><strong style="display:block;font-size:26px">'.esc_html((string)$value).'</strong><div style="font-weight:800">'.esc_html($label).'</div><small>'.esc_html($detail).'</small></div>';
    }

    /** @param array<int,array<string,mixed>> $items */
    private static function executive_pattern_section(string $title,array $items,string $kind): void {
        echo '<section style="background:#fff;border:1px solid #ddd;border-radius:14px;padding:18px"><h2>'.esc_html($title).'</h2>';
        if(!$items){echo '<p>'.esc_html($kind==='risk'?__('No active confirmed risk Patterns.','elev8-os'):__('No active confirmed opportunity Patterns.','elev8-os')).'</p>';}
        foreach($items as $item){echo '<article style="border-top:1px solid #eee;padding:12px 0"><a href="'.esc_url(add_query_arg('view','patterns',self::url())).'" style="font-weight:800;text-decoration:none">'.esc_html((string)($item['title']??'')).'</a><p style="margin:4px 0">'.esc_html((string)($item['summary']??'')).'</p><small>'.sprintf(esc_html__('%1$d observations · %2$s severity · %3$s trend · %4$d%% confidence','elev8-os'),(int)($item['occurrence_count']??0),(string)($item['severity']??'normal'),(string)($item['trend']??'stable'),(int)($item['confidence']??0)).'</small></article>';}
        echo '</section>';
    }

    private static function patterns_view(): string {
        if (!class_exists('Elev8_OS_Pattern_Detection_Service')) { return '<p>'.esc_html__('Pattern Detection is unavailable.','elev8-os').'</p>'; }
        $filters=['classification'=>sanitize_key((string)($_GET['classification']??'')),'severity'=>sanitize_key((string)($_GET['severity']??'')),'status'=>sanitize_key((string)($_GET['status']??'active')),'posts_per_page'=>200];
        $items=Elev8_OS_Pattern_Detection_Service::query($filters);
        ob_start();
        echo '<div class="elev8-observation-registry"><header><p>'.esc_html__('INTELLIGENCE ENGINE','elev8-os').'</p><h1>'.esc_html__('Pattern Detection','elev8-os').'</h1><span>'.esc_html__('Repeated confirmed observations are grouped into human-governed patterns. Patterns never create work automatically.','elev8-os').'</span></header>';
        self::tabs('patterns');
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="margin:0 0 18px">'; wp_nonce_field('elev8_scan_patterns'); echo '<input type="hidden" name="action" value="elev8_os_scan_patterns"><button>'.esc_html__('Run pattern scan now','elev8-os').'</button></form>';
        echo '<form method="get" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px"><input type="hidden" name="view" value="patterns"><select name="status"><option value="">'.esc_html__('All states','elev8-os').'</option>';
        foreach(['active'=>'Active','acknowledged'=>'Acknowledged','dismissed'=>'Dismissed','resolved'=>'Resolved'] as $v=>$l){echo '<option value="'.esc_attr($v).'" '.selected($filters['status'],$v,false).'>'.esc_html($l).'</option>';}
        echo '</select><select name="classification"><option value="">'.esc_html__('All classifications','elev8-os').'</option>';
        foreach(['risk','opportunity','achievement','follow_up'] as $v){echo '<option value="'.esc_attr($v).'" '.selected($filters['classification'],$v,false).'>'.esc_html(ucwords(str_replace('_',' ',$v))).'</option>';}
        echo '</select><select name="severity"><option value="">'.esc_html__('All severity','elev8-os').'</option>';
        foreach(['low','normal','high','critical'] as $v){echo '<option value="'.esc_attr($v).'" '.selected($filters['severity'],$v,false).'>'.esc_html(ucfirst($v)).'</option>';}
        echo '</select><button>'.esc_html__('Filter','elev8-os').'</button></form>';
        if(!$items){echo '<div style="background:#fff;border:1px solid #ddd;border-radius:12px;padding:24px"><strong>'.esc_html__('No patterns match these filters. Confirm at least two related observations, then run the scan.','elev8-os').'</strong></div>';}
        foreach($items as $item){self::pattern_card($item);} echo '</div>'; return (string)ob_get_clean();
    }

    private static function pattern_card(array $item): void {
        echo '<article style="background:#fff;border:1px solid #ddd;border-radius:14px;padding:18px;margin:0 0 14px"><div style="display:flex;justify-content:space-between;gap:20px"><div><div style="font-size:12px;text-transform:uppercase">'.esc_html($item['classification'].' · '.$item['severity'].' · '.$item['trend']).'</div><h3 style="margin:6px 0">'.esc_html($item['title']).'</h3><p>'.esc_html($item['summary']).'</p><small>'.sprintf(esc_html__('%1$d supporting observations · %2$d%% confidence','elev8-os'),(int)$item['occurrence_count'],(int)$item['confidence']).'</small></div><strong>'.esc_html(ucfirst($item['status'])).'</strong></div>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:flex;gap:8px;flex-wrap:wrap;margin-top:14px">'; wp_nonce_field('elev8_review_pattern_'.$item['id']);
        echo '<input type="hidden" name="action" value="elev8_os_review_pattern"><input type="hidden" name="pattern_id" value="'.(int)$item['id'].'"><select name="pattern_status">';
        foreach(['active'=>'Keep active','acknowledged'=>'Acknowledge','dismissed'=>'Dismiss','resolved'=>'Resolve'] as $v=>$l){echo '<option value="'.esc_attr($v).'">'.esc_html($l).'</option>';}
        echo '</select><input name="review_notes" placeholder="'.esc_attr__('Pattern note (optional)','elev8-os').'" style="min-width:260px"><button>'.esc_html__('Save pattern','elev8-os').'</button></form>';
        if (($item['status'] ?? '') === 'acknowledged') {
            echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="margin-top:10px">';
            wp_nonce_field('elev8_promote_pattern_'.$item['id']);
            echo '<input type="hidden" name="action" value="elev8_os_promote_pattern"><input type="hidden" name="pattern_id" value="'.(int)$item['id'].'"><button>'.esc_html__('Promote to recommendation','elev8-os').'</button></form>';
        }
        echo '</article>';
    }

    private static function recommendations_view(): string {
        if (!class_exists('Elev8_OS_Intelligence_Recommendation_Service')) { return '<p>'.esc_html__('Recommendation Promotion is unavailable.','elev8-os').'</p>'; }
        $filters=['classification'=>sanitize_key((string)($_GET['classification']??'')),'status'=>sanitize_key((string)($_GET['status']??'')),'posts_per_page'=>200];
        $items=Elev8_OS_Intelligence_Recommendation_Service::query($filters);
        ob_start();
        echo '<div class="elev8-observation-registry"><header><p>'.esc_html__('INTELLIGENCE ENGINE','elev8-os').'</p><h1>'.esc_html__('Recommendations','elev8-os').'</h1><span>'.esc_html__('Explainable next actions promoted from acknowledged Patterns. Nothing becomes operational work until a leader approves execution.','elev8-os').'</span></header>';
        self::tabs('recommendations');
        echo '<form method="get" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px"><input type="hidden" name="view" value="recommendations"><select name="status"><option value="">'.esc_html__('All states','elev8-os').'</option>';
        foreach(['proposed'=>'Proposed','approved'=>'Approved','rejected'=>'Rejected'] as $v=>$l){echo '<option value="'.esc_attr($v).'" '.selected($filters['status'],$v,false).'>'.esc_html($l).'</option>';}
        echo '</select><select name="classification"><option value="">'.esc_html__('All classifications','elev8-os').'</option>';
        foreach(['risk','opportunity','achievement','follow_up'] as $v){echo '<option value="'.esc_attr($v).'" '.selected($filters['classification'],$v,false).'>'.esc_html(ucwords(str_replace('_',' ',$v))).'</option>';}
        echo '</select><button>'.esc_html__('Filter','elev8-os').'</button></form>';
        if(!$items){echo '<div style="background:#fff;border:1px solid #ddd;border-radius:12px;padding:24px"><strong>'.esc_html__('No Recommendations match these filters. Acknowledge a Pattern, then promote it when it is ready for a decision.','elev8-os').'</strong></div>';}
        foreach($items as $item){self::recommendation_card($item);} echo '</div>'; return (string)ob_get_clean();
    }

    private static function recommendation_card(array $item): void {
        echo '<article style="background:#fff;border:1px solid #ddd;border-radius:14px;padding:18px;margin:0 0 14px"><div style="display:flex;justify-content:space-between;gap:20px"><div><div style="font-size:12px;text-transform:uppercase">'.esc_html($item['classification'].' · '.$item['severity']).'</div><h3 style="margin:6px 0">'.esc_html($item['title']).'</h3><p>'.esc_html($item['summary']).'</p><p><strong>'.esc_html__('Suggested action:','elev8-os').'</strong> '.esc_html($item['suggested_action']).'</p><p><strong>'.esc_html__('Expected benefit:','elev8-os').'</strong> '.esc_html($item['expected_benefit']).'</p><small>'.sprintf(esc_html__('%1$d supporting observations · %2$d%% confidence · Suggested owner: %3$s','elev8-os'),count((array)$item['observation_ids']),(int)$item['confidence'],$item['suggested_owner_name']).'</small></div><strong>'.esc_html(ucfirst($item['status'])).'</strong></div>';
        if ((int)$item['work_item_id'] > 0) { echo '<p><strong>'.sprintf(esc_html__('Approved Work Item #%d','elev8-os'),(int)$item['work_item_id']).'</strong></p>'; }
        $outcome=is_array($item['outcome']??null)?$item['outcome']:[];
        if($outcome){
            echo '<div style="margin-top:14px;padding:14px;border:1px solid #ddd;border-radius:10px;background:#fafafa"><strong>'.esc_html__('Measured outcome','elev8-os').': '.esc_html(ucwords(str_replace('_',' ',(string)($outcome['result']??'unknown')))).'</strong>';
            if(!empty($outcome['metric_name'])){echo '<p>'.esc_html($outcome['metric_name']).': '.esc_html($outcome['metric_before']).' → '.esc_html($outcome['metric_after']).'</p>';}
            if(!empty($outcome['notes'])){echo '<p>'.esc_html($outcome['notes']).'</p>';}
            echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:8px;margin-top:10px">'; wp_nonce_field('elev8_record_recommendation_outcome_'.$item['id']);
            echo '<input type="hidden" name="action" value="elev8_os_record_recommendation_outcome"><input type="hidden" name="recommendation_id" value="'.(int)$item['id'].'"><select name="outcome_result">';
            foreach(['unknown'=>'Unknown','successful'=>'Successful','partial'=>'Partially successful','no_change'=>'No measurable change','unsuccessful'=>'Unsuccessful'] as $v=>$l){echo '<option value="'.esc_attr($v).'" '.selected((string)($outcome['result']??'unknown'),$v,false).'>'.esc_html($l).'</option>';}
            echo '</select><input name="metric_name" value="'.esc_attr((string)($outcome['metric_name']??'')).'" placeholder="'.esc_attr__('Metric name','elev8-os').'"><input name="metric_before" value="'.esc_attr((string)($outcome['metric_before']??'')).'" placeholder="'.esc_attr__('Before','elev8-os').'"><input name="metric_after" value="'.esc_attr((string)($outcome['metric_after']??'')).'" placeholder="'.esc_attr__('After','elev8-os').'"><input name="outcome_notes" value="'.esc_attr((string)($outcome['notes']??'')).'" placeholder="'.esc_attr__('Outcome evidence or notes','elev8-os').'"><button>'.esc_html__('Save outcome','elev8-os').'</button></form></div>';
        }
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:flex;gap:8px;flex-wrap:wrap;margin-top:14px">'; wp_nonce_field('elev8_decide_recommendation_'.$item['id']);
        echo '<input type="hidden" name="action" value="elev8_os_decide_recommendation"><input type="hidden" name="recommendation_id" value="'.(int)$item['id'].'"><select name="recommendation_status">';
        foreach(['proposed'=>'Keep proposed','approved'=>'Approve execution','rejected'=>'Reject'] as $v=>$l){echo '<option value="'.esc_attr($v).'">'.esc_html($l).'</option>';}
        echo '</select><select name="owner_user_id"><option value="0">'.esc_html__('Unassigned','elev8-os').'</option>';
        foreach(get_users(['orderby'=>'display_name','order'=>'ASC','fields'=>['ID','display_name']]) as $user){echo '<option value="'.(int)$user->ID.'" '.selected((int)$item['suggested_owner_user_id'],(int)$user->ID,false).'>'.esc_html($user->display_name).'</option>';}
        echo '</select><input name="decision_notes" placeholder="'.esc_attr__('Decision note (optional)','elev8-os').'" style="min-width:260px"><button>'.esc_html__('Save decision','elev8-os').'</button></form></article>';
    }

    public static function promote_pattern(): void {
        $id=absint($_POST['pattern_id']??0); check_admin_referer('elev8_promote_pattern_'.$id); if(!self::can_review(wp_get_current_user())){wp_die(esc_html__('Permission denied.','elev8-os'));}
        $result=Elev8_OS_Intelligence_Recommendation_Service::promote_pattern($id,get_current_user_id());
        if(is_wp_error($result)){wp_die(esc_html($result->get_error_message()));}
        wp_safe_redirect(add_query_arg('view','recommendations',self::url())); exit;
    }

    public static function decide_recommendation(): void {
        $id=absint($_POST['recommendation_id']??0); check_admin_referer('elev8_decide_recommendation_'.$id); if(!self::can_review(wp_get_current_user())){wp_die(esc_html__('Permission denied.','elev8-os'));}
        $result=Elev8_OS_Intelligence_Recommendation_Service::decide($id,(string)($_POST['recommendation_status']??''),get_current_user_id(),wp_unslash((string)($_POST['decision_notes']??'')),absint($_POST['owner_user_id']??0));
        if(is_wp_error($result)){wp_die(esc_html($result->get_error_message()));}
        wp_safe_redirect(add_query_arg('view','recommendations',self::url())); exit;
    }

    public static function record_recommendation_outcome(): void {
        $id=absint($_POST['recommendation_id']??0); check_admin_referer('elev8_record_recommendation_outcome_'.$id); if(!self::can_review(wp_get_current_user())){wp_die(esc_html__('Permission denied.','elev8-os'));}
        $result=Elev8_OS_Recommendation_Outcome_Service::record($id,(string)($_POST['outcome_result']??'unknown'),get_current_user_id(),wp_unslash((string)($_POST['outcome_notes']??'')),wp_unslash((string)($_POST['metric_name']??'')),wp_unslash((string)($_POST['metric_before']??'')),wp_unslash((string)($_POST['metric_after']??'')));
        if(is_wp_error($result)){wp_die(esc_html($result->get_error_message()));}
        wp_safe_redirect(add_query_arg('view','recommendations',self::url())); exit;
    }

    public static function review_pattern(): void {
        $id=absint($_POST['pattern_id']??0); check_admin_referer('elev8_review_pattern_'.$id); if(!self::can_review(wp_get_current_user())){wp_die(esc_html__('Permission denied.','elev8-os'));}
        Elev8_OS_Pattern_Detection_Service::review($id,(string)($_POST['pattern_status']??''),get_current_user_id(),wp_unslash((string)($_POST['review_notes']??'')));
        wp_safe_redirect(add_query_arg('view','patterns',self::url())); exit;
    }

    public static function scan_patterns(): void {
        check_admin_referer('elev8_scan_patterns'); if(!self::can_review(wp_get_current_user())){wp_die(esc_html__('Permission denied.','elev8-os'));}
        Elev8_OS_Pattern_Detection_Service::scan(); wp_safe_redirect(add_query_arg('view','patterns',self::url())); exit;
    }

    public static function review(): void {
        $id=absint($_POST['observation_id']??0); check_admin_referer('elev8_review_observation_'.$id);
        if (!self::can_review(wp_get_current_user())) { wp_die(esc_html__('Permission denied.','elev8-os')); }
        Elev8_OS_Observation_Service::review($id, (string)($_POST['review_status']??''), get_current_user_id(), wp_unslash((string)($_POST['review_notes']??'')));
        wp_safe_redirect(self::url()); exit;
    }
    public static function command(array $commands, WP_User $user): array { if (self::can_review($user)) { $commands[]=['id'=>'executive-intelligence','label'=>__('Executive Intelligence','elev8-os'),'description'=>__('Review governed risks, opportunities, recommendations, and outcomes.','elev8-os'),'url'=>add_query_arg('view','executive',self::url()),'group'=>'intelligence','icon'=>'🧠','type'=>'command']; } return $commands; }
    public static function shell_page(bool $render): bool { return $render || self::is_page(); }
    public static function is_page(): bool { return is_page(self::page_id()) || is_page(self::SLUG); }
    public static function url(): string { $id=self::page_id(); return $id ? (string)get_permalink($id) : home_url('/'.self::SLUG.'/'); }
    private static function page_id(): int { return absint(get_option(self::OPTION_PAGE_ID)); }
    private static function can_review(WP_User $user): bool { return user_can($user,'manage_options') || Elev8_OS_Access_Service::user_can('view_ceo_dashboard',$user) || Elev8_OS_Access_Service::user_can('manage_operations',$user); }
    private static function ensure_page(bool $create): int { $id=self::page_id(); if($id&&get_post_status($id)){return $id;} $page=get_page_by_path(self::SLUG,OBJECT,'page'); if($page instanceof WP_Post){update_option(self::OPTION_PAGE_ID,$page->ID,false);return (int)$page->ID;} if(!$create){return 0;} $id=wp_insert_post(['post_title'=>__('Observation Review','elev8-os'),'post_name'=>self::SLUG,'post_content'=>'[elev8_os_observation_registry]','post_status'=>'publish','post_type'=>'page','comment_status'=>'closed'],true); if(!is_wp_error($id)&&$id>0){update_option(self::OPTION_PAGE_ID,(int)$id,false);return (int)$id;} return 0; }
}

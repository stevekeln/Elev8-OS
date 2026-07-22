<?php
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Observation_Registry_Module {
    private const OPTION_PAGE_ID = 'elev8_os_observation_registry_page_id';
    private const SLUG = 'elev8-intelligence-review';

    public static function init(): void {
        add_shortcode('elev8_os_observation_registry', [__CLASS__, 'shortcode']);
        add_action('admin_init', [__CLASS__, 'ensure_page_for_admin']);
        add_action('admin_post_elev8_os_review_observation', [__CLASS__, 'review']);
        add_filter('elev8_os_application_shell_frontend', [__CLASS__, 'shell_page']);
        add_filter('elev8_os_command_palette_commands', [__CLASS__, 'command'], 10, 2);
    }
    public static function activate(): void { self::ensure_page(true); }
    public static function ensure_page_for_admin(): void { if (current_user_can('manage_options')) { self::ensure_page(true); } }
    public static function shortcode(): string {
        if (!is_user_logged_in()) { return '<p>'.esc_html__('Please sign in.', 'elev8-os').'</p>'; }
        $user = wp_get_current_user();
        if (!self::can_review($user)) { return '<p>'.esc_html__('You do not have permission to review intelligence.', 'elev8-os').'</p>'; }
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
    public static function review(): void {
        $id=absint($_POST['observation_id']??0); check_admin_referer('elev8_review_observation_'.$id);
        if (!self::can_review(wp_get_current_user())) { wp_die(esc_html__('Permission denied.','elev8-os')); }
        Elev8_OS_Observation_Service::review($id, (string)($_POST['review_status']??''), get_current_user_id(), wp_unslash((string)($_POST['review_notes']??'')));
        wp_safe_redirect(self::url()); exit;
    }
    public static function command(array $commands, WP_User $user): array { if (self::can_review($user)) { $commands[]=['id'=>'observation-review','label'=>__('Observation Review','elev8-os'),'description'=>__('Review verified facts, risks, and opportunities.','elev8-os'),'url'=>self::url(),'group'=>'intelligence','icon'=>'🧠','type'=>'command']; } return $commands; }
    public static function shell_page(bool $render): bool { return $render || self::is_page(); }
    public static function is_page(): bool { return is_page(self::page_id()) || is_page(self::SLUG); }
    public static function url(): string { $id=self::page_id(); return $id ? (string)get_permalink($id) : home_url('/'.self::SLUG.'/'); }
    private static function page_id(): int { return absint(get_option(self::OPTION_PAGE_ID)); }
    private static function can_review(WP_User $user): bool { return user_can($user,'manage_options') || Elev8_OS_Access_Service::user_can('view_ceo_dashboard',$user) || Elev8_OS_Access_Service::user_can('manage_operations',$user); }
    private static function ensure_page(bool $create): int { $id=self::page_id(); if($id&&get_post_status($id)){return $id;} $page=get_page_by_path(self::SLUG,OBJECT,'page'); if($page instanceof WP_Post){update_option(self::OPTION_PAGE_ID,$page->ID,false);return (int)$page->ID;} if(!$create){return 0;} $id=wp_insert_post(['post_title'=>__('Observation Review','elev8-os'),'post_name'=>self::SLUG,'post_content'=>'[elev8_os_observation_registry]','post_status'=>'publish','post_type'=>'page','comment_status'=>'closed'],true); if(!is_wp_error($id)&&$id>0){update_option(self::OPTION_PAGE_ID,(int)$id,false);return (int)$id;} return 0; }
}

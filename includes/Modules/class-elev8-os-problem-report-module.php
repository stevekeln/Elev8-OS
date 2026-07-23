<?php
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Problem_Report_Module {
    private const PAGE_SLUG = 'report-a-problem';
    public static function init(): void {
        add_shortcode('elev8_report_problem', [__CLASS__, 'shortcode']);
        add_action('init', [__CLASS__, 'ensure_page'], 30);
        add_action('wp_enqueue_scripts', [__CLASS__, 'assets']);
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_post_elev8_submit_problem', [__CLASS__, 'submit']);
        add_action('admin_post_elev8_problem_status', [__CLASS__, 'status']);
        add_filter('elev8_os_mobile_home_cards', [__CLASS__, 'mobile_card'], 5, 2);
    }
    public static function page_url(): string { return home_url('/' . self::PAGE_SLUG . '/'); }
    public static function ensure_page(): void {
        $page = get_page_by_path(self::PAGE_SLUG);
        if (!$page) { wp_insert_post(['post_type'=>'page','post_status'=>'publish','post_title'=>__('Report a Problem','elev8-os'),'post_name'=>self::PAGE_SLUG,'post_content'=>'[elev8_report_problem]']); }
        elseif (!has_shortcode((string)$page->post_content, 'elev8_report_problem')) { wp_update_post(['ID'=>$page->ID,'post_content'=>'[elev8_report_problem]']); }
    }
    public static function assets(): void { if (is_page(self::PAGE_SLUG)) { wp_enqueue_style('elev8-problem-report', ELEV8_OS_URL . 'assets/css/problem-report.css', [], ELEV8_OS_VERSION); } }
    public static function menu(): void { add_submenu_page('elev8-os', __('Problem Reports', 'elev8-os'), __('Problem Reports', 'elev8-os'), 'manage_options', 'elev8-problem-reports', [__CLASS__, 'admin_page']); }
    public static function mobile_card(array $cards, WP_User $user): array {
        array_unshift($cards, ['title' => __('Report a Problem', 'elev8-os'), 'description' => __('Tell us what broke, what is confusing, or what would make work easier.', 'elev8-os'), 'url' => self::page_url(), 'icon' => 'sos', 'primary' => true]);
        return $cards;
    }
    public static function shortcode(): string {
        if (!is_user_logged_in()) { return '<p>' . esc_html__('Please sign in to report a problem.', 'elev8-os') . '</p>'; }
        $result = sanitize_key((string) ($_GET['problem_reported'] ?? ''));
        ob_start(); ?>
        <main class="elev8-problem-wrap">
            <section class="elev8-problem-hero"><span><?php echo esc_html__('Help us improve Elev8 OS', 'elev8-os'); ?></span><h1><?php echo esc_html__('Report a Problem', 'elev8-os'); ?></h1><p><?php echo esc_html__('One clear report helps us fix the system. Repeated reports are grouped together and rise in priority.', 'elev8-os'); ?></p></section>
            <?php if ($result): ?><div class="elev8-problem-success"><?php echo esc_html($result === 'duplicate' ? __('Thank you. Your report matched an existing issue and increased its priority.', 'elev8-os') : __('Thank you. Your report was saved for review.', 'elev8-os')); ?></div><?php endif; ?>
            <form class="elev8-problem-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="elev8_submit_problem"><?php wp_nonce_field('elev8_submit_problem'); ?>
                <label><?php echo esc_html__('What kind of problem is this?', 'elev8-os'); ?><select name="category" required><?php foreach (Elev8_OS_Problem_Report_Service::categories() as $key => $label): ?><option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
                <label><?php echo esc_html__('Where were you working?', 'elev8-os'); ?><input name="area" placeholder="Example: My Work, Artist Portal, Glass Production"></label>
                <label><?php echo esc_html__('Short summary', 'elev8-os'); ?><input name="summary" maxlength="160" required placeholder="What went wrong?"></label>
                <label><?php echo esc_html__('What happened?', 'elev8-os'); ?><textarea name="details" rows="6" required placeholder="Tell us what you were trying to do and what happened instead."></textarea></label>
                <label><?php echo esc_html__('What should have happened?', 'elev8-os'); ?><textarea name="expected" rows="3"></textarea></label>
                <div class="elev8-problem-grid"><label><?php echo esc_html__('Impact', 'elev8-os'); ?><select name="severity"><?php foreach (Elev8_OS_Problem_Report_Service::severities() as $key => $label): ?><option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label><label><?php echo esc_html__('Screenshot or file', 'elev8-os'); ?><input type="file" name="attachment" accept="image/*,.pdf,.txt"></label></div>
                <input type="hidden" name="page_url" id="elev8-problem-page-url"><input type="hidden" name="device" id="elev8-problem-device">
                <button type="submit"><?php echo esc_html__('Send Problem Report', 'elev8-os'); ?></button>
            </form>
        </main><script>document.getElementById('elev8-problem-page-url').value=document.referrer||location.href;document.getElementById('elev8-problem-device').value=navigator.userAgent;</script>
        <?php return (string) ob_get_clean();
    }
    public static function submit(): void {
        if (!is_user_logged_in()) { wp_die(esc_html__('Please sign in.', 'elev8-os')); }
        check_admin_referer('elev8_submit_problem');
        $result = Elev8_OS_Problem_Report_Service::save($_POST, $_FILES, get_current_user_id());
        if (is_wp_error($result)) { wp_die(esc_html($result->get_error_message())); }
        wp_safe_redirect(add_query_arg('problem_reported', !empty($result['duplicate']) ? 'duplicate' : 'new', self::page_url())); exit;
    }
    public static function status(): void {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('You do not have permission.', 'elev8-os')); }
        check_admin_referer('elev8_problem_status');
        Elev8_OS_Problem_Report_Service::update_status(absint($_POST['report_id'] ?? 0), sanitize_key((string) ($_POST['status'] ?? 'new')));
        wp_safe_redirect(admin_url('admin.php?page=elev8-problem-reports')); exit;
    }
    public static function admin_page(): void {
        $reports = Elev8_OS_Problem_Report_Service::reports(); ?>
        <div class="wrap"><h1><?php echo esc_html__('Problem Reports', 'elev8-os'); ?></h1><p><?php echo esc_html__('Likely duplicates are grouped. Occurrence count and impact determine what rises first.', 'elev8-os'); ?></p>
        <table class="widefat striped"><thead><tr><th><?php echo esc_html__('Issue', 'elev8-os'); ?></th><th><?php echo esc_html__('Area', 'elev8-os'); ?></th><th><?php echo esc_html__('Impact', 'elev8-os'); ?></th><th><?php echo esc_html__('Reports', 'elev8-os'); ?></th><th><?php echo esc_html__('Status', 'elev8-os'); ?></th><th><?php echo esc_html__('Details', 'elev8-os'); ?></th></tr></thead><tbody>
        <?php if (!$reports): ?><tr><td colspan="6"><?php echo esc_html__('No problem reports yet.', 'elev8-os'); ?></td></tr><?php endif; foreach ($reports as $item): $d=$item['data']; ?><tr><td><strong><?php echo esc_html($item['post']->post_title); ?></strong><br><small><?php echo esc_html((string)($d['category'] ?? '')); ?></small></td><td><?php echo esc_html((string)($d['area'] ?? '')); ?></td><td><?php echo esc_html((string)($d['severity'] ?? 'normal')); ?></td><td><strong><?php echo esc_html((string)($d['occurrences'] ?? 1)); ?></strong></td><td><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="elev8_problem_status"><input type="hidden" name="report_id" value="<?php echo esc_attr((string)$item['post']->ID); ?>"><?php wp_nonce_field('elev8_problem_status'); ?><select name="status" onchange="this.form.submit()"><?php foreach (['new','reviewing','planned','resolved','closed'] as $status): ?><option value="<?php echo esc_attr($status); ?>" <?php selected(($d['status'] ?? 'new'), $status); ?>><?php echo esc_html(ucwords(str_replace('_',' ',$status))); ?></option><?php endforeach; ?></select></form></td><td><?php echo esc_html(wp_trim_words((string)($d['details'] ?? ''), 24)); ?><?php if (!empty($d['page_url'])): ?><br><a href="<?php echo esc_url($d['page_url']); ?>" target="_blank" rel="noopener"><?php echo esc_html__('Open reported page', 'elev8-os'); ?></a><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div>
        <?php }
}

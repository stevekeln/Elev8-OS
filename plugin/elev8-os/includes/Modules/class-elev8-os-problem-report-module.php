<?php
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Problem_Report_Module {
    private const PAGE_SLUG = 'report-a-problem';
    private const NOTICE_PREFIX = 'elev8_problem_notice_';

    public static function init(): void {
        add_shortcode('elev8_report_problem', [__CLASS__, 'shortcode']);
        add_action('init', [__CLASS__, 'ensure_page'], 30);
        add_action('wp_enqueue_scripts', [__CLASS__, 'assets']);
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_post_elev8_submit_problem', [__CLASS__, 'submit']);
        add_action('admin_post_elev8_problem_status', [__CLASS__, 'status']);
        add_filter('elev8_os_mobile_home_cards', [__CLASS__, 'mobile_card'], 5, 2);
        add_action('wp_footer', [__CLASS__, 'render_return_notice'], 100);
        add_action('admin_notices', [__CLASS__, 'render_return_notice']);
    }

    /** Backward-compatible route contract used by workspace definitions. */
    public static function url(string $return_to = ''): string {
        return self::page_url($return_to);
    }

    public static function page_url(string $return_to = ''): string {
        $url = home_url('/' . self::PAGE_SLUG . '/');
        if ($return_to !== '') { $url = add_query_arg('return_to', rawurlencode($return_to), $url); }
        return $url;
    }

    public static function current_request_url(): string {
        $scheme = is_ssl() ? 'https' : 'http';
        $host = sanitize_text_field((string) ($_SERVER['HTTP_HOST'] ?? wp_parse_url(home_url(), PHP_URL_HOST)));
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        return esc_url_raw($scheme . '://' . $host . $uri);
    }

    public static function ensure_page(): void {
        $page = get_page_by_path(self::PAGE_SLUG);
        if (!$page) {
            wp_insert_post(['post_type'=>'page','post_status'=>'publish','post_title'=>__('Report a Problem','elev8-os'),'post_name'=>self::PAGE_SLUG,'post_content'=>'[elev8_report_problem]']);
        } elseif (!has_shortcode((string) $page->post_content, 'elev8_report_problem')) {
            wp_update_post(['ID'=>$page->ID,'post_content'=>'[elev8_report_problem]']);
        }
    }

    public static function assets(): void {
        if (is_page(self::PAGE_SLUG)) {
            wp_enqueue_style('elev8-problem-report', ELEV8_OS_URL . 'assets/css/problem-report.css', [], ELEV8_OS_VERSION);
        }
    }

    public static function menu(): void {
        add_submenu_page('elev8-os', __('Problem Reports', 'elev8-os'), __('Problem Reports', 'elev8-os'), 'manage_options', 'elev8-problem-reports', [__CLASS__, 'admin_page']);
    }

    public static function mobile_card(array $cards, WP_User $user): array {
        array_unshift($cards, [
            'title' => __('Report a Problem', 'elev8-os'),
            'description' => __('Tell us what broke, what is confusing, or what would make work easier.', 'elev8-os'),
            'url' => self::page_url(self::current_request_url()),
            'icon' => 'sos',
            'primary' => true,
        ]);
        return $cards;
    }

    public static function shortcode(): string {
        if (!is_user_logged_in()) { return '<p>' . esc_html__('Please sign in to report a problem.', 'elev8-os') . '</p>'; }
        $return_to = self::validated_return_url(isset($_GET['return_to']) ? rawurldecode((string) wp_unslash($_GET['return_to'])) : '');
        if ($return_to === '') { $return_to = wp_get_referer() ?: self::dashboard_fallback(); }
        $preview_target_id = 0;
        $preview_role = '';
        if (class_exists('Elev8_OS_Preview_Service') && Elev8_OS_Preview_Service::is_active()) {
            $target = Elev8_OS_Preview_Service::target_user();
            $preview_target_id = $target instanceof WP_User ? (int) $target->ID : 0;
            $preview_role = Elev8_OS_Preview_Service::selected_role();
        }
        ob_start(); ?>
        <main class="elev8-problem-wrap">
            <section class="elev8-problem-hero">
                <span><?php echo esc_html__('Help us improve Elev8 OS', 'elev8-os'); ?></span>
                <h1><?php echo esc_html__('Report a Problem', 'elev8-os'); ?></h1>
                <p><?php echo esc_html__('One clear report helps us fix the system. After submitting, you will return to exactly where you were working.', 'elev8-os'); ?></p>
            </section>
            <form class="elev8-problem-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="elev8_submit_problem"><?php wp_nonce_field('elev8_submit_problem'); ?>
                <input type="hidden" name="return_to" id="elev8-problem-return-to" value="<?php echo esc_attr($return_to); ?>">
                <input type="hidden" name="preview_target_user_id" value="<?php echo esc_attr((string) $preview_target_id); ?>">
                <input type="hidden" name="preview_role" value="<?php echo esc_attr($preview_role); ?>">
                <label><?php echo esc_html__('What kind of problem is this?', 'elev8-os'); ?><select name="category" required><?php foreach (Elev8_OS_Problem_Report_Service::categories() as $key => $label): ?><option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
                <label><?php echo esc_html__('Where were you working?', 'elev8-os'); ?><input name="area" placeholder="Example: My Work, Artist Portal, Glass Production"></label>
                <label><?php echo esc_html__('Short summary', 'elev8-os'); ?><input name="summary" maxlength="160" required placeholder="What went wrong?"></label>
                <label><?php echo esc_html__('What happened?', 'elev8-os'); ?><textarea name="details" rows="6" required placeholder="Tell us what you were trying to do and what happened instead."></textarea></label>
                <label><?php echo esc_html__('What should have happened?', 'elev8-os'); ?><textarea name="expected" rows="3"></textarea></label>
                <div class="elev8-problem-grid"><label><?php echo esc_html__('Impact', 'elev8-os'); ?><select name="severity"><?php foreach (Elev8_OS_Problem_Report_Service::severities() as $key => $label): ?><option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label><label><?php echo esc_html__('Screenshot or file', 'elev8-os'); ?><input type="file" name="attachment" accept="image/*,.pdf,.txt"></label></div>
                <input type="hidden" name="page_url" id="elev8-problem-page-url" value="<?php echo esc_attr($return_to); ?>"><input type="hidden" name="device" id="elev8-problem-device">
                <button type="submit"><?php echo esc_html__('Send Problem Report', 'elev8-os'); ?></button>
                <a class="elev8-problem-cancel" href="<?php echo esc_url($return_to); ?>"><?php esc_html_e('Cancel and return', 'elev8-os'); ?></a>
            </form>
        </main>
        <script>(function(){var d=document.getElementById('elev8-problem-device');if(d){d.value=navigator.userAgent;}var r=document.getElementById('elev8-problem-return-to');var p=document.getElementById('elev8-problem-page-url');if(r&&p&&!p.value){p.value=r.value||document.referrer||location.href;}}());</script>
        <?php return (string) ob_get_clean();
    }

    public static function submit(): void {
        if (!is_user_logged_in()) { wp_die(esc_html__('Please sign in.', 'elev8-os')); }
        check_admin_referer('elev8_submit_problem');
        $result = Elev8_OS_Problem_Report_Service::save($_POST, $_FILES, get_current_user_id());
        if (is_wp_error($result)) { wp_die(esc_html($result->get_error_message())); }
        $kind = !empty($result['duplicate']) ? 'duplicate' : 'new';
        set_transient(self::NOTICE_PREFIX . get_current_user_id(), $kind, 120);
        $return_to = self::validated_return_url((string) ($_POST['return_to'] ?? ''));
        if ($return_to === '') { $return_to = self::dashboard_fallback(); }
        wp_safe_redirect($return_to);
        exit;
    }

    public static function render_return_notice(): void {
        if (!is_user_logged_in()) { return; }
        $key = self::NOTICE_PREFIX . get_current_user_id();
        $kind = (string) get_transient($key);
        if ($kind === '') { return; }
        delete_transient($key);
        $message = $kind === 'duplicate'
            ? __('Problem reported. It matched an existing issue and increased its priority.', 'elev8-os')
            : __('Problem reported. Thank you—you are back where you were working.', 'elev8-os');
        echo '<div class="elev8-problem-return-toast" role="status" aria-live="polite"><strong>' . esc_html($message) . '</strong></div>';
        echo '<style>.elev8-problem-return-toast{position:fixed;z-index:100000;left:50%;bottom:calc(88px + env(safe-area-inset-bottom,0px));transform:translateX(-50%);width:min(92vw,620px);box-sizing:border-box;padding:16px 20px;border-radius:14px;background:#123f35;color:#fff;box-shadow:0 16px 44px rgba(0,0,0,.22);font:600 16px/1.35 system-ui,sans-serif}@media(min-width:783px){.elev8-problem-return-toast{bottom:28px}}</style>';
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
        <?php if (!$reports): ?><tr><td colspan="6"><?php echo esc_html__('No problem reports yet.', 'elev8-os'); ?></td></tr><?php endif; foreach ($reports as $item): $d=$item['data']; ?><tr><td><strong><?php echo esc_html($item['post']->post_title); ?></strong><br><small><?php echo esc_html((string)($d['category'] ?? '')); ?></small></td><td><?php echo esc_html((string)($d['area'] ?? '')); ?></td><td><?php echo esc_html((string)($d['severity'] ?? 'normal')); ?></td><td><strong><?php echo esc_html((string)($d['occurrences'] ?? 1)); ?></strong></td><td><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="elev8_problem_status"><input type="hidden" name="report_id" value="<?php echo esc_attr((string)$item['post']->ID); ?>"><?php wp_nonce_field('elev8_problem_status'); ?><select name="status" onchange="this.form.submit()"><?php foreach (['new','reviewing','planned','resolved','closed'] as $status): ?><option value="<?php echo esc_attr($status); ?>" <?php selected(($d['status'] ?? 'new'), $status); ?>><?php echo esc_html(ucwords(str_replace('_',' ',$status))); ?></option><?php endforeach; ?></select></form></td><td><?php echo esc_html(wp_trim_words((string)($d['details'] ?? ''), 24)); ?><?php if (!empty($d['preview_role'])): ?><br><small><?php echo esc_html(sprintf(__('Preview context: %s', 'elev8-os'), (string) $d['preview_role'])); ?></small><?php endif; ?><?php if (!empty($d['page_url'])): ?><br><a href="<?php echo esc_url($d['page_url']); ?>" target="_blank" rel="noopener"><?php echo esc_html__('Open reported page', 'elev8-os'); ?></a><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div>
        <?php
    }

    private static function validated_return_url(string $url): string {
        $url = esc_url_raw(wp_unslash($url));
        if ($url === '') { return ''; }
        return wp_validate_redirect($url, '');
    }

    private static function dashboard_fallback(): string {
        if (class_exists('Elev8_OS_Preview_Service') && Elev8_OS_Preview_Service::is_active()) {
            return Elev8_OS_Preview_Service::dashboard_url(Elev8_OS_Preview_Service::effective_user());
        }
        if (class_exists('Elev8_OS_Workspace_Runtime_Module')) { return Elev8_OS_Workspace_Runtime_Module::url(); }
        if (class_exists('Elev8_OS_Workspace_Resolver_Service')) { return Elev8_OS_Workspace_Resolver_Service::destination(wp_get_current_user()); }
        return home_url('/');
    }
}

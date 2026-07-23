<?php
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Business_Coaching_Module {
    private const SHORTCODE = 'elev8_business_coaching';
    private const ADMIN_SLUG = 'elev8-business-coaching';

    public static function init(): void {
        Elev8_OS_Business_Coaching_Service::init();
        add_shortcode(self::SHORTCODE, [__CLASS__, 'shortcode']);
        add_action('admin_menu', [__CLASS__, 'register_menu'], 34);
        add_action('admin_post_elev8_os_coaching_state', [__CLASS__, 'handle_state']);
    }

    public static function register_menu(): void {
        add_submenu_page('elev8-os', __('Business Coaching', 'elev8-os'), __('Business Coaching', 'elev8-os'), 'read', self::ADMIN_SLUG, [__CLASS__, 'render_admin']);
    }

    public static function render_admin(): void {
        if (!is_user_logged_in()) { wp_die(esc_html__('Please sign in to view coaching.', 'elev8-os')); }
        echo '<div class="wrap">'.self::render().'</div>';
    }

    public static function shortcode(): string {
        if (!is_user_logged_in()) { return '<p>'.esc_html__('Please sign in to view your business coaching.', 'elev8-os').'</p>'; }
        return self::render();
    }

    public static function handle_state(): void {
        if (!is_user_logged_in()) { wp_die(esc_html__('Please sign in.', 'elev8-os')); }
        check_admin_referer('elev8_coaching_state');
        $card_key = sanitize_text_field((string) wp_unslash($_POST['card_key'] ?? ''));
        $state = sanitize_key((string) ($_POST['card_state'] ?? 'read'));
        Elev8_OS_Business_Coaching_Service::set_state(get_current_user_id(), $card_key, $state);
        $redirect = wp_get_referer();
        wp_safe_redirect(is_string($redirect) && $redirect !== '' ? $redirect : self::url());
        exit;
    }

    public static function url(): string {
        return class_exists('Elev8_OS_Portal_Page_Manager') ? Elev8_OS_Portal_Page_Manager::get_url('coaching') : admin_url('admin.php?page='.self::ADMIN_SLUG);
    }

    private static function render(): string {
        $user = class_exists('Elev8_OS_Preview_Service') ? Elev8_OS_Preview_Service::effective_user() : wp_get_current_user();
        if (!$user instanceof WP_User) { return ''; }
        $cards = Elev8_OS_Business_Coaching_Service::cards($user);
        $summary = Elev8_OS_Business_Coaching_Service::summary($user);
        $role = class_exists('Elev8_OS_Workspace_Resolver_Service') ? Elev8_OS_Workspace_Resolver_Service::role_label($user) : __('Team Member', 'elev8-os');
        ob_start();
        ?>
        <div class="elev8-coaching" style="max-width:1180px;margin:0 auto;padding:24px 18px 48px">
            <header style="background:#fff;border:1px solid #ddd;border-radius:18px;padding:24px;margin-bottom:18px">
                <p style="margin:0 0 6px;font-weight:700;letter-spacing:.08em"><?php echo esc_html__('INTELLIGENCE ENGINE', 'elev8-os'); ?></p>
                <h1 style="margin:0 0 8px"><?php echo esc_html__('Business Coaching', 'elev8-os'); ?></h1>
                <p style="font-size:16px;margin:0"><?php echo esc_html(sprintf(__('Role-aware guidance for %s, grounded in governed Elev8 OS evidence.', 'elev8-os'), $role)); ?></p>
            </header>
            <section style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:20px">
                <?php self::metric(__('Active coaching', 'elev8-os'), (int) $summary['total']); ?>
                <?php self::metric(__('Unread', 'elev8-os'), (int) $summary['unread']); ?>
                <?php self::metric(__('Pinned', 'elev8-os'), (int) $summary['pinned']); ?>
                <?php self::metric(__('Needs follow-up', 'elev8-os'), (int) $summary['follow_up']); ?>
            </section>
            <?php if (!$cards): ?>
                <section style="background:#fff;border:1px solid #ddd;border-radius:16px;padding:28px">
                    <h2><?php echo esc_html__('No coaching needs your attention right now.', 'elev8-os'); ?></h2>
                    <p><?php echo esc_html__('As confirmed observations, patterns, recommendations, and assigned work develop, relevant guidance will appear here.', 'elev8-os'); ?></p>
                </section>
            <?php else: ?>
                <div style="display:grid;gap:14px">
                    <?php foreach ($cards as $card): self::card($card); endforeach; ?>
                </div>
            <?php endif; ?>
            <p style="margin-top:22px;color:#555"><?php echo esc_html__('Coaching is explainable guidance only. It cannot approve recommendations, create work, or alter authoritative business records.', 'elev8-os'); ?></p>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private static function metric(string $label, int $value): void {
        echo '<article style="background:#fff;border:1px solid #ddd;border-radius:14px;padding:16px"><strong style="display:block;font-size:28px">'.esc_html((string) $value).'</strong><span>'.esc_html($label).'</span></article>';
    }

    /** @param array<string,mixed> $card */
    private static function card(array $card): void {
        $state = (string) ($card['state'] ?? 'unread');
        echo '<article style="background:#fff;border:1px solid #d9d9d9;border-radius:16px;padding:20px">';
        echo '<div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap"><div><span style="font-size:12px;font-weight:700;text-transform:uppercase">'.esc_html(str_replace('_', ' ', (string) $card['category'])).' · '.esc_html((string) $card['severity']).'</span><h2 style="margin:6px 0 10px">'.esc_html((string) $card['title']).'</h2></div><strong>'.esc_html(ucwords(str_replace('_', ' ', $state))).'</strong></div>';
        echo '<p style="font-size:16px"><strong>'.esc_html__('Suggested next step:', 'elev8-os').'</strong> '.esc_html((string) $card['suggested_action']).'</p>';
        echo '<details style="margin:12px 0"><summary style="cursor:pointer;font-weight:700">'.esc_html__('Why am I seeing this?', 'elev8-os').'</summary><p>'.esc_html((string) $card['why']).'</p></details>';
        echo '<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">';
        if (!empty($card['url'])) { echo '<a class="button" href="'.esc_url((string) $card['url']).'">'.esc_html__('Review evidence', 'elev8-os').'</a>'; }
        foreach (['read'=>__('Mark read','elev8-os'),'pinned'=>__('Pin','elev8-os'),'follow_up'=>__('Needs follow-up','elev8-os'),'dismissed'=>__('Dismiss','elev8-os')] as $value=>$label) {
            echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:inline">';
            wp_nonce_field('elev8_coaching_state');
            echo '<input type="hidden" name="action" value="elev8_os_coaching_state"><input type="hidden" name="card_key" value="'.esc_attr((string) $card['key']).'"><input type="hidden" name="card_state" value="'.esc_attr($value).'"><button class="button">'.esc_html($label).'</button></form>';
        }
        echo '</div></article>';
    }
}

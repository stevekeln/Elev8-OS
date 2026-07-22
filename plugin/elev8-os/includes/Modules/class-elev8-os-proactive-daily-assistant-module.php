<?php
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Proactive_Daily_Assistant_Module {
    private const SHORTCODE = 'elev8_daily_assistant';
    private const ADMIN_SLUG = 'elev8-daily-assistant';

    public static function init(): void {
        Elev8_OS_Proactive_Daily_Assistant_Service::init();
        Elev8_OS_Daily_Assistant_Delivery_Service::init();
        add_action('admin_post_elev8_save_daily_assistant_preferences', [__CLASS__, 'save_preferences']);
        add_action('admin_post_elev8_send_daily_assistant_test', [__CLASS__, 'send_test']);
        add_shortcode(self::SHORTCODE, [__CLASS__, 'shortcode']);
        add_action('admin_menu', [__CLASS__, 'register_menu'], 33);
        add_filter('elev8_os_command_palette_commands', [__CLASS__, 'command'], 10, 2);
    }

    public static function register_menu(): void {
        add_submenu_page('elev8-os', __('Today', 'elev8-os'), __('Today', 'elev8-os'), 'read', self::ADMIN_SLUG, [__CLASS__, 'render_admin']);
    }

    public static function render_admin(): void {
        if (!is_user_logged_in()) { wp_die(esc_html__('Please sign in to view your daily assistant.', 'elev8-os')); }
        echo '<div class="wrap">'.self::render().'</div>';
    }

    public static function shortcode(): string {
        if (!is_user_logged_in()) { return '<p>'.esc_html__('Please sign in to view your daily assistant.', 'elev8-os').'</p>'; }
        return self::render();
    }

    public static function url(): string {
        return class_exists('Elev8_OS_Portal_Page_Manager') ? Elev8_OS_Portal_Page_Manager::get_url('today') : admin_url('admin.php?page='.self::ADMIN_SLUG);
    }

    public static function command(array $commands, WP_User $user): array {
        $commands[] = [
            'id'=>'daily-assistant',
            'label'=>__('Today','elev8-os'),
            'description'=>__('See your role-aware start-of-day priorities, work, conversations, and coaching.','elev8-os'),
            'url'=>self::url(),
            'group'=>'workspace',
            'icon'=>'☀️',
            'type'=>'command',
        ];
        return $commands;
    }

    private static function render(): string {
        $user = class_exists('Elev8_OS_Preview_Service') ? Elev8_OS_Preview_Service::effective_user() : wp_get_current_user();
        if (!$user instanceof WP_User) { return ''; }
        $settings = Elev8_OS_Daily_Assistant_Delivery_Service::settings((int) $user->ID);
        $brief = Elev8_OS_Proactive_Daily_Assistant_Service::briefing($user, (array) ($settings['categories'] ?? []));
        Elev8_OS_Proactive_Daily_Assistant_Service::mark_viewed((int) $user->ID);
        ob_start();
        ?>
        <main class="elev8-daily-assistant" style="max-width:1180px;margin:0 auto;padding:24px 18px 48px">
            <header style="background:#fff;border:1px solid #ddd;border-radius:18px;padding:24px;margin-bottom:18px">
                <p style="margin:0 0 6px;font-weight:700;letter-spacing:.08em"><?php echo esc_html__('PROACTIVE DAILY ASSISTANT', 'elev8-os'); ?></p>
                <h1 style="margin:0 0 8px"><?php echo esc_html(sprintf(__('%1$s, %2$s.', 'elev8-os'), (string) $brief['greeting'], (string) $brief['name'])); ?></h1>
                <p style="font-size:16px;margin:0"><?php echo esc_html(sprintf(__('Here is what deserves your attention today as %s.', 'elev8-os'), (string) $brief['role_label'])); ?></p>
            </header>

            <section style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:20px">
                <?php self::metric(__('Due today','elev8-os'), (int) ($brief['work']['due_today'] ?? 0)); ?>
                <?php self::metric(__('Overdue','elev8-os'), (int) ($brief['work']['overdue'] ?? 0)); ?>
                <?php self::metric(__('Unread conversations','elev8-os'), (int) ($brief['unread_conversations'] ?? 0)); ?>
                <?php self::metric(__('Coaching cards','elev8-os'), count((array) ($brief['coaching'] ?? []))); ?>
            </section>

            <section style="background:#fff;border:1px solid #ddd;border-radius:18px;padding:22px;margin-bottom:18px">
                <h2 style="margin-top:0"><?php echo esc_html__('Your focus today', 'elev8-os'); ?></h2>
                <?php if (empty($brief['focus'])): ?>
                    <p><?php echo esc_html__('Nothing urgent is competing for your attention. Use the available space to move your most important planned work forward.', 'elev8-os'); ?></p>
                <?php else: ?>
                    <div style="display:grid;gap:12px">
                        <?php foreach ((array) $brief['focus'] as $index=>$item): ?>
                            <article style="border:1px solid #e2e2e2;border-radius:14px;padding:16px">
                                <div style="display:flex;gap:12px;align-items:flex-start"><strong style="font-size:24px"><?php echo esc_html((string) ((int) $index + 1)); ?></strong><div style="flex:1"><span style="font-size:12px;font-weight:700;text-transform:uppercase"><?php echo esc_html((string) $item['source'].' · '.(string) $item['severity']); ?></span><h3 style="margin:5px 0 6px"><?php echo esc_html((string) $item['title']); ?></h3><p style="margin:0 0 10px"><?php echo esc_html((string) $item['summary']); ?></p><?php if (!empty($item['url'])): ?><a class="button" href="<?php echo esc_url((string) $item['url']); ?>"><?php echo esc_html__('Open', 'elev8-os'); ?></a><?php endif; ?></div></div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:18px;margin-bottom:18px">
                <article style="background:#fff;border:1px solid #ddd;border-radius:18px;padding:22px">
                    <h2 style="margin-top:0"><?php echo esc_html__('Work and communication', 'elev8-os'); ?></h2>
                    <p><?php echo esc_html(sprintf(__('%1$d open Work Items, %2$d due today, and %3$d overdue.', 'elev8-os'), (int) ($brief['work']['open'] ?? 0), (int) ($brief['work']['due_today'] ?? 0), (int) ($brief['work']['overdue'] ?? 0))); ?></p>
                    <p><?php echo esc_html(sprintf(_n('%d unread Conversation.', '%d unread Conversations.', (int) ($brief['unread_conversations'] ?? 0), 'elev8-os'), (int) ($brief['unread_conversations'] ?? 0))); ?></p>
                </article>
                <article style="background:#fff;border:1px solid #ddd;border-radius:18px;padding:22px">
                    <h2 style="margin-top:0"><?php echo esc_html__('Coaching snapshot', 'elev8-os'); ?></h2>
                    <?php if (empty($brief['coaching'])): ?><p><?php echo esc_html__('No active coaching cards need your attention.', 'elev8-os'); ?></p><?php else: ?><ul><?php foreach ((array) $brief['coaching'] as $card): ?><li style="margin-bottom:8px"><strong><?php echo esc_html((string) $card['title']); ?></strong></li><?php endforeach; ?></ul><?php endif; ?>
                </article>
            </section>

            <section style="background:#fff;border:1px solid #ddd;border-radius:18px;padding:22px">
                <h2 style="margin-top:0"><?php echo esc_html__('Quick access', 'elev8-os'); ?></h2>
                <div style="display:flex;gap:10px;flex-wrap:wrap"><?php foreach ((array) $brief['quick_links'] as $link): ?><a class="button" href="<?php echo esc_url((string) $link['url']); ?>"><?php echo esc_html((string) $link['icon'].' '.(string) $link['label']); ?></a><?php endforeach; ?></div>
            </section>
            <?php self::preferences($user, $settings); ?>
            <p style="margin-top:20px;color:#555"><?php echo esc_html__('The Daily Assistant is a personal read model. It does not create work, approve recommendations, or alter conversations or source records.', 'elev8-os'); ?></p>
        </main>
        <?php
        return (string) ob_get_clean();
    }

    public static function save_preferences(): void {
        if (!is_user_logged_in()) { wp_die(esc_html__('Please sign in.', 'elev8-os')); }
        check_admin_referer('elev8_daily_assistant_preferences');
        $user_id = get_current_user_id();
        Elev8_OS_Daily_Assistant_Delivery_Service::save_settings($user_id, (array) ($_POST['settings'] ?? []));
        wp_safe_redirect(add_query_arg('preferences_saved', '1', self::url()));
        exit;
    }

    public static function send_test(): void {
        if (!is_user_logged_in()) { wp_die(esc_html__('Please sign in.', 'elev8-os')); }
        check_admin_referer('elev8_daily_assistant_test');
        $user = wp_get_current_user();
        $result = Elev8_OS_Daily_Assistant_Delivery_Service::deliver($user);
        wp_safe_redirect(add_query_arg('test_sent', !empty($result['email']) || !empty($result['in_app']) ? '1' : '0', self::url()));
        exit;
    }

    private static function preferences(WP_User $user, array $settings): void {
        $status = Elev8_OS_Daily_Assistant_Delivery_Service::delivery_status((int) $user->ID);
        ?>
        <section style="background:#fff;border:1px solid #ddd;border-radius:18px;padding:22px;margin-top:18px">
            <h2 style="margin-top:0"><?php echo esc_html__('Briefing preferences', 'elev8-os'); ?></h2>
            <?php if (isset($_GET['preferences_saved'])): ?><div class="notice notice-success inline"><p><?php echo esc_html__('Daily Assistant preferences saved.', 'elev8-os'); ?></p></div><?php endif; ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="elev8_save_daily_assistant_preferences">
                <?php wp_nonce_field('elev8_daily_assistant_preferences'); ?>
                <p><label><input type="checkbox" name="settings[enabled]" value="1" <?php checked(!empty($settings['enabled'])); ?>> <?php echo esc_html__('Deliver my Daily Assistant briefing automatically', 'elev8-os'); ?></label></p>
                <p><label><?php echo esc_html__('Delivery hour', 'elev8-os'); ?> <select name="settings[hour]"><?php for ($hour=0;$hour<24;$hour++): ?><option value="<?php echo esc_attr((string)$hour); ?>" <?php selected((int)($settings['hour'] ?? 8),$hour); ?>><?php echo esc_html(wp_date('g A', mktime($hour,0))); ?></option><?php endfor; ?></select></label> <label style="margin-left:12px"><?php echo esc_html__('Days', 'elev8-os'); ?> <select name="settings[days]"><option value="weekdays" <?php selected($settings['days'] ?? '', 'weekdays'); ?>><?php echo esc_html__('Weekdays','elev8-os'); ?></option><option value="daily" <?php selected($settings['days'] ?? '', 'daily'); ?>><?php echo esc_html__('Every day','elev8-os'); ?></option></select></label></p>
                <fieldset><legend><strong><?php echo esc_html__('Channels','elev8-os'); ?></strong></legend><label><input type="checkbox" name="settings[channels][]" value="in_app" <?php checked(in_array('in_app',(array)($settings['channels'] ?? []),true)); ?>> <?php echo esc_html__('In-app','elev8-os'); ?></label> &nbsp; <label><input type="checkbox" name="settings[channels][]" value="email" <?php checked(in_array('email',(array)($settings['channels'] ?? []),true)); ?>> <?php echo esc_html__('Email','elev8-os'); ?></label></fieldset>
                <fieldset style="margin-top:12px"><legend><strong><?php echo esc_html__('Focus categories','elev8-os'); ?></strong></legend><?php foreach (['work'=>__('Work','elev8-os'),'conversations'=>__('Conversations','elev8-os'),'attention'=>__('Attention','elev8-os'),'coaching'=>__('Coaching','elev8-os')] as $key=>$label): ?><label style="margin-right:14px"><input type="checkbox" name="settings[categories][]" value="<?php echo esc_attr($key); ?>" <?php checked(in_array($key,(array)($settings['categories'] ?? []),true)); ?>> <?php echo esc_html($label); ?></label><?php endforeach; ?></fieldset>
                <p><label><?php echo esc_html__('Reminder emphasis','elev8-os'); ?> <select name="settings[reminders]"><option value="none" <?php selected($settings['reminders'] ?? '', 'none'); ?>><?php echo esc_html__('No reminder emphasis','elev8-os'); ?></option><option value="overdue" <?php selected($settings['reminders'] ?? '', 'overdue'); ?>><?php echo esc_html__('Emphasize overdue work','elev8-os'); ?></option><option value="all_focus" <?php selected($settings['reminders'] ?? '', 'all_focus'); ?>><?php echo esc_html__('Emphasize all focus items','elev8-os'); ?></option></select></label></p>
                <p><button class="button button-primary" type="submit"><?php echo esc_html__('Save preferences','elev8-os'); ?></button></p>
            </form>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:8px"><input type="hidden" name="action" value="elev8_send_daily_assistant_test"><?php wp_nonce_field('elev8_daily_assistant_test'); ?><button class="button" type="submit"><?php echo esc_html__('Send test briefing now','elev8-os'); ?></button></form>
            <p style="color:#666"><?php echo esc_html(sprintf(__('Last email: %1$s · Last in-app delivery: %2$s', 'elev8-os'), $status['last_email'] ?: __('Never','elev8-os'), $status['last_in_app'] ?: __('Never','elev8-os'))); ?></p>
        </section>
        <?php
    }

    private static function metric(string $label, int $value): void {
        echo '<article style="background:#fff;border:1px solid #ddd;border-radius:14px;padding:16px"><strong style="display:block;font-size:28px">'.esc_html((string) $value).'</strong><span>'.esc_html($label).'</span></article>';
    }
}

<?php
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Proactive_Daily_Assistant_Module {
    private const SHORTCODE = 'elev8_daily_assistant';
    private const ADMIN_SLUG = 'elev8-daily-assistant';

    public static function init(): void {
        Elev8_OS_Proactive_Daily_Assistant_Service::init();
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
        $brief = Elev8_OS_Proactive_Daily_Assistant_Service::briefing($user);
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
            <p style="margin-top:20px;color:#555"><?php echo esc_html__('The Daily Assistant is a personal read model. It does not create work, approve recommendations, or alter conversations or source records.', 'elev8-os'); ?></p>
        </main>
        <?php
        return (string) ob_get_clean();
    }

    private static function metric(string $label, int $value): void {
        echo '<article style="background:#fff;border:1px solid #ddd;border-radius:14px;padding:16px"><strong style="display:block;font-size:28px">'.esc_html((string) $value).'</strong><span>'.esc_html($label).'</span></article>';
    }
}

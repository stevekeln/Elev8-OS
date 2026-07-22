<?php
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Team_Coordination_Module {
    private const SHORTCODE = 'elev8_team_coordination';
    private const ADMIN_SLUG = 'elev8-team-coordination';

    public static function init(): void {
        add_shortcode(self::SHORTCODE, [__CLASS__, 'shortcode']);
        add_action('admin_menu', [__CLASS__, 'register_menu'], 34);
        add_action('admin_post_elev8_save_work_dependencies', [__CLASS__, 'save_dependencies']);
        add_action('admin_post_elev8_handoff_work', [__CLASS__, 'handoff']);
        add_filter('elev8_os_command_palette_commands', [__CLASS__, 'command'], 10, 2);
    }

    public static function register_menu(): void {
        add_submenu_page('elev8-os', __('Team Coordination', 'elev8-os'), __('Team Coordination', 'elev8-os'), 'read', self::ADMIN_SLUG, [__CLASS__, 'render_admin']);
    }

    public static function url(): string {
        return class_exists('Elev8_OS_Portal_Page_Manager') ? Elev8_OS_Portal_Page_Manager::get_url('team_coordination') : admin_url('admin.php?page=' . self::ADMIN_SLUG);
    }

    public static function command(array $commands, WP_User $user): array {
        $commands[] = ['id' => 'team-coordination', 'label' => __('Team Coordination', 'elev8-os'), 'description' => __('See workloads, dependencies, waiting-on relationships, bottlenecks, and handoffs.', 'elev8-os'), 'url' => self::url(), 'keywords' => ['team','dependencies','handoff','waiting','bottleneck']];
        return $commands;
    }

    public static function render_admin(): void {
        if (!is_user_logged_in()) { wp_die(esc_html__('Please sign in.', 'elev8-os')); }
        echo '<div class="wrap">' . self::render() . '</div>';
    }

    public static function shortcode(): string {
        return is_user_logged_in() ? self::render() : '<p>' . esc_html__('Please sign in to view Team Coordination.', 'elev8-os') . '</p>';
    }

    private static function render(): string {
        $user = wp_get_current_user();
        $snapshot = Elev8_OS_Team_Coordination_Service::snapshot($user);
        $users = Elev8_OS_Team_Coordination_Service::assignable_users();
        ob_start();
        ?>
        <main class="elev8-team-coordination" style="max-width:1200px;margin:0 auto;padding:24px">
            <header style="margin-bottom:20px"><p style="text-transform:uppercase;letter-spacing:.08em;color:#666;margin:0"><?php esc_html_e('Operations + Workflow', 'elev8-os'); ?></p><h1 style="margin:.2em 0"><?php esc_html_e('Team Coordination', 'elev8-os'); ?></h1><p><?php echo esc_html($snapshot['team_view'] ? __('See how work moves across the team and where it is waiting.', 'elev8-os') : __('See the work assigned to you, what it is waiting on, and where a handoff may be needed.', 'elev8-os')); ?></p></header>
            <?php if (isset($_GET['coordination_saved'])): ?><div class="notice notice-success inline"><p><?php esc_html_e('Coordination relationship saved.', 'elev8-os'); ?></p></div><?php endif; ?>
            <?php if (isset($_GET['coordination_error'])): ?><div class="notice notice-error inline"><p><?php echo esc_html(sanitize_text_field(wp_unslash((string) $_GET['coordination_error']))); ?></p></div><?php endif; ?>

            <section style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:20px">
                <?php self::metric(__('People with active work', 'elev8-os'), count($snapshot['workloads'])); ?>
                <?php self::metric(__('Active Work Items', 'elev8-os'), count($snapshot['items'])); ?>
                <?php self::metric(__('Potential bottlenecks', 'elev8-os'), count($snapshot['bottlenecks'])); ?>
                <?php self::metric(__('Recent handoffs', 'elev8-os'), count($snapshot['handoffs'])); ?>
            </section>

            <section style="background:#fff;border:1px solid #ddd;border-radius:18px;padding:20px;margin-bottom:18px"><h2 style="margin-top:0"><?php esc_html_e('Workload visibility', 'elev8-os'); ?></h2>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:12px">
                <?php foreach ($snapshot['workloads'] as $load): ?><article style="border:1px solid #ddd;border-radius:14px;padding:15px"><strong><?php echo esc_html($load['name']); ?></strong><p style="margin:.5em 0 0"><?php echo esc_html(sprintf(__('%1$d active · %2$d overdue · %3$d blocked · %4$d urgent', 'elev8-os'), $load['active'], $load['overdue'], $load['blocked'], $load['urgent'])); ?></p></article><?php endforeach; ?>
                <?php if (!$snapshot['workloads']): ?><p><?php esc_html_e('No active work is available in your scope.', 'elev8-os'); ?></p><?php endif; ?>
                </div>
            </section>

            <section style="background:#fff;border:1px solid #ddd;border-radius:18px;padding:20px;margin-bottom:18px"><h2 style="margin-top:0"><?php esc_html_e('Waiting on and bottlenecks', 'elev8-os'); ?></h2>
            <?php foreach ($snapshot['bottlenecks'] as $item): ?><article style="border-top:1px solid #eee;padding:14px 0"><h3 style="margin:0"><?php echo esc_html($item['title']); ?></h3><p><?php echo esc_html(sprintf(__('Owner: %1$s · Bottleneck score: %2$d · Waiting on: %3$d · Blocking: %4$d', 'elev8-os'), $item['owner_name'], $item['bottleneck_score'], count($item['open_dependencies']), count($item['dependent_ids']))); ?></p>
                <?php if (Elev8_OS_Team_Coordination_Service::can_change_work((int) $item['id'], $user)): ?>
                <details><summary><?php esc_html_e('Manage dependencies or hand off', 'elev8-os'); ?></summary>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:12px 0"><input type="hidden" name="action" value="elev8_save_work_dependencies"><input type="hidden" name="work_id" value="<?php echo esc_attr((string) $item['id']); ?>"><?php wp_nonce_field('elev8_save_work_dependencies_' . $item['id']); ?><label><?php esc_html_e('Waiting on Work Items', 'elev8-os'); ?><select name="dependency_ids[]" multiple size="5" style="display:block;min-width:320px;max-width:100%">
                    <?php foreach ($snapshot['items'] as $candidate): if ((int)$candidate['id'] === (int)$item['id']) continue; ?><option value="<?php echo esc_attr((string)$candidate['id']); ?>" <?php selected(in_array((int)$candidate['id'], Elev8_OS_Team_Coordination_Service::dependencies((int)$item['id']), true)); ?>><?php echo esc_html($candidate['title'].' — '.$candidate['owner_name']); ?></option><?php endforeach; ?></select></label><p><button class="button" type="submit"><?php esc_html_e('Save waiting-on relationships', 'elev8-os'); ?></button></p></form>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="elev8_handoff_work"><input type="hidden" name="work_id" value="<?php echo esc_attr((string)$item['id']); ?>"><?php wp_nonce_field('elev8_handoff_work_' . $item['id']); ?><label><?php esc_html_e('Hand off to', 'elev8-os'); ?> <select name="to_user_id"><?php foreach ($users as $assignable): ?><option value="<?php echo esc_attr((string)$assignable->ID); ?>"><?php echo esc_html($assignable->display_name); ?></option><?php endforeach; ?></select></label><label style="display:block;margin-top:8px"><?php esc_html_e('Handoff note', 'elev8-os'); ?><textarea name="note" rows="2" style="display:block;width:100%"></textarea></label><p><button class="button button-primary" type="submit"><?php esc_html_e('Confirm handoff', 'elev8-os'); ?></button></p></form>
                </details><?php endif; ?></article><?php endforeach; ?>
            <?php if (!$snapshot['bottlenecks']): ?><p><?php esc_html_e('No dependency bottlenecks are currently visible in your scope.', 'elev8-os'); ?></p><?php endif; ?></section>

            <section style="background:#fff;border:1px solid #ddd;border-radius:18px;padding:20px"><h2 style="margin-top:0"><?php esc_html_e('Recent handoffs', 'elev8-os'); ?></h2><?php foreach ($snapshot['handoffs'] as $handoff): $from=get_user_by('id',(int)$handoff['from_user_id']);$to=get_user_by('id',(int)$handoff['to_user_id']); ?><p><strong><?php echo esc_html($handoff['work_title']); ?></strong> — <?php echo esc_html(sprintf(__('%1$s to %2$s on %3$s', 'elev8-os'), $from instanceof WP_User?$from->display_name:__('Unassigned','elev8-os'), $to instanceof WP_User?$to->display_name:__('Unknown','elev8-os'), $handoff['created_at'])); ?><?php if (!empty($handoff['note'])): ?><br><span style="color:#666"><?php echo esc_html($handoff['note']); ?></span><?php endif; ?></p><?php endforeach; ?><?php if (!$snapshot['handoffs']): ?><p><?php esc_html_e('No handoffs have been recorded yet.', 'elev8-os'); ?></p><?php endif; ?></section>
            <p style="color:#666;margin-top:18px"><?php esc_html_e('Team Coordination extends Universal Work Items. It does not create a separate project-management or task system.', 'elev8-os'); ?></p>
        </main>
        <?php
        return (string) ob_get_clean();
    }

    public static function save_dependencies(): void {
        $work_id = absint($_POST['work_id'] ?? 0);
        check_admin_referer('elev8_save_work_dependencies_' . $work_id);
        $result = Elev8_OS_Team_Coordination_Service::set_dependencies($work_id, (array) ($_POST['dependency_ids'] ?? []));
        self::redirect($result);
    }

    public static function handoff(): void {
        $work_id = absint($_POST['work_id'] ?? 0);
        check_admin_referer('elev8_handoff_work_' . $work_id);
        $result = Elev8_OS_Team_Coordination_Service::handoff($work_id, absint($_POST['to_user_id'] ?? 0), sanitize_textarea_field(wp_unslash((string) ($_POST['note'] ?? ''))));
        self::redirect($result);
    }

    private static function redirect($result): void {
        $args = is_wp_error($result) ? ['coordination_error' => $result->get_error_message()] : ['coordination_saved' => '1'];
        wp_safe_redirect(add_query_arg($args, self::url()));
        exit;
    }

    private static function metric(string $label, int $value): void {
        echo '<article style="background:#fff;border:1px solid #ddd;border-radius:14px;padding:16px"><strong style="display:block;font-size:28px">'.esc_html((string)$value).'</strong><span>'.esc_html($label).'</span></article>';
    }
}

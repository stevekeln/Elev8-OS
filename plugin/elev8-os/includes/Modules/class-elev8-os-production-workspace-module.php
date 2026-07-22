<?php
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Production_Workspace_Module {
    private const SHORTCODE = 'elev8_production_workspace';
    private const ADMIN_SLUG = 'elev8-production-workspace';

    public static function init(): void {
        add_shortcode(self::SHORTCODE, [__CLASS__, 'shortcode']);
        add_action('admin_menu', [__CLASS__, 'register_menu'], 35);
        add_action('admin_post_elev8_update_production_queue_item', [__CLASS__, 'handle_update']);
        add_filter('elev8_os_command_palette_commands', [__CLASS__, 'command'], 10, 2);
    }

    public static function register_menu(): void {
        add_submenu_page('elev8-os', __('Production', 'elev8-os'), __('Production', 'elev8-os'), 'read', self::ADMIN_SLUG, [__CLASS__, 'render_admin']);
    }

    public static function url(array $args = []): string {
        $base = class_exists('Elev8_OS_Portal_Page_Manager') ? Elev8_OS_Portal_Page_Manager::get_url('production') : admin_url('admin.php?page=' . self::ADMIN_SLUG);
        return $args ? add_query_arg($args, $base) : $base;
    }

    public static function command(array $commands, WP_User $user): array {
        if (Elev8_OS_Production_Workspace_Service::can_view($user)) {
            $commands[] = ['id'=>'production-workspace','label'=>__('Production','elev8-os'),'description'=>__('See today’s production queue, late work, quality review, and assignments.','elev8-os'),'url'=>self::url(),'keywords'=>['production','queue','glass','memorial','jobs','quality']];
        }
        return $commands;
    }

    public static function render_admin(): void { echo '<div class="wrap">' . self::shortcode() . '</div>'; }

    public static function shortcode(): string {
        if (!is_user_logged_in()) { return '<p>' . esc_html__('Please sign in to view Production.', 'elev8-os') . '</p>'; }
        if (!Elev8_OS_Production_Workspace_Service::can_view()) { return '<p>' . esc_html__('You do not have access to Production.', 'elev8-os') . '</p>'; }
        $filters = [
            'status' => sanitize_key((string)($_GET['production_status'] ?? '')),
            'priority' => sanitize_key((string)($_GET['production_priority'] ?? '')),
            'assigned_user_id' => absint($_GET['production_owner'] ?? 0),
            'search' => sanitize_text_field(wp_unslash((string)($_GET['production_search'] ?? ''))),
            'overdue' => !empty($_GET['production_overdue']),
        ];
        $data = Elev8_OS_Production_Workspace_Service::snapshot($filters);
        ob_start(); ?>
        <main style="max-width:1280px;margin:0 auto;padding:24px">
            <header style="margin-bottom:20px"><p style="text-transform:uppercase;letter-spacing:.08em;color:#666;margin:0"><?php esc_html_e('Operations Workspace · Glass Configuration', 'elev8-os'); ?></p><h1 style="margin:.2em 0"><?php esc_html_e('Production', 'elev8-os'); ?></h1><p><?php esc_html_e('Run today’s production from one queue. Glass jobs remain authoritative in Glass Operations; this workspace coordinates the view.', 'elev8-os'); ?></p></header>
            <?php if (isset($_GET['production_saved'])): ?><div class="notice notice-success inline"><p><?php esc_html_e('Production item updated.', 'elev8-os'); ?></p></div><?php endif; ?>
            <?php if (isset($_GET['production_error'])): ?><div class="notice notice-error inline"><p><?php echo esc_html(sanitize_text_field(wp_unslash((string)$_GET['production_error']))); ?></p></div><?php endif; ?>
            <section style="display:grid;grid-template-columns:repeat(auto-fit,minmax(145px,1fr));gap:12px;margin-bottom:20px">
                <?php foreach ([['Ready','ready'],['Running','running'],['Waiting','waiting'],['Blocked','blocked'],['Late','late'],['Quality review','quality'],['Completed today','completed_today']] as $metric): ?>
                    <article style="background:#fff;border:1px solid #ddd;border-radius:16px;padding:16px"><strong style="font-size:28px;display:block"><?php echo esc_html((string)$data['metrics'][$metric[1]]); ?></strong><span><?php echo esc_html__($metric[0], 'elev8-os'); ?></span></article>
                <?php endforeach; ?>
            </section>
            <section style="background:#fff;border:1px solid #ddd;border-radius:18px;padding:18px;margin-bottom:18px">
                <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
                    <label><?php esc_html_e('Search','elev8-os'); ?><input type="search" name="production_search" value="<?php echo esc_attr($filters['search']); ?>"></label>
                    <label><?php esc_html_e('Status','elev8-os'); ?><select name="production_status"><option value=""><?php esc_html_e('All','elev8-os'); ?></option><?php foreach ($data['statuses'] as $key=>$label): ?><option value="<?php echo esc_attr($key); ?>" <?php selected($filters['status'],$key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
                    <label><?php esc_html_e('Owner','elev8-os'); ?><select name="production_owner"><option value="0"><?php esc_html_e('All','elev8-os'); ?></option><?php foreach ($data['workers'] as $worker): ?><option value="<?php echo esc_attr((string)$worker->ID); ?>" <?php selected($filters['assigned_user_id'],(int)$worker->ID); ?>><?php echo esc_html($worker->display_name); ?></option><?php endforeach; ?></select></label>
                    <label><input type="checkbox" name="production_overdue" value="1" <?php checked($filters['overdue']); ?>> <?php esc_html_e('Late only','elev8-os'); ?></label>
                    <button class="button"><?php esc_html_e('Filter','elev8-os'); ?></button><a class="button" href="<?php echo esc_url(self::url()); ?>"><?php esc_html_e('Reset','elev8-os'); ?></a>
                </form>
            </section>
            <section style="background:#fff;border:1px solid #ddd;border-radius:18px;padding:18px;overflow:auto"><h2 style="margin-top:0"><?php esc_html_e('Production Queue','elev8-os'); ?></h2>
                <?php if (!$data['queue']): ?><p><?php esc_html_e('No production work matches these filters.','elev8-os'); ?></p><?php else: ?>
                <table class="widefat striped"><thead><tr><th><?php esc_html_e('Job','elev8-os'); ?></th><th><?php esc_html_e('Due','elev8-os'); ?></th><th><?php esc_html_e('Owner','elev8-os'); ?></th><th><?php esc_html_e('Progress','elev8-os'); ?></th><th><?php esc_html_e('Status','elev8-os'); ?></th><th><?php esc_html_e('Update','elev8-os'); ?></th></tr></thead><tbody>
                <?php foreach ($data['queue'] as $item): ?><tr>
                    <td><strong><?php echo esc_html($item['title']); ?></strong><br><small><?php echo esc_html(ucwords(str_replace('_',' ',$item['type']))); ?><?php if ($item['customer']): ?> · <?php echo esc_html($item['customer']); ?><?php endif; ?><?php if ($item['order_number']): ?> · #<?php echo esc_html($item['order_number']); ?><?php endif; ?></small><?php if ($item['is_blocked']): ?><br><span style="color:#a00;font-weight:700"><?php esc_html_e('Blocked by missing customer or ashes information','elev8-os'); ?></span><?php endif; ?></td>
                    <td><?php echo $item['due_date'] ? esc_html($item['due_date']) : '—'; ?><?php if ($item['is_late']): ?><br><strong style="color:#a00"><?php esc_html_e('Late','elev8-os'); ?></strong><?php endif; ?></td>
                    <td><?php echo esc_html($item['assigned_name']); ?></td>
                    <td><?php echo esc_html((string)$item['completed_units'] . ' / ' . (string)$item['planned_units']); ?></td>
                    <td><?php echo esc_html($data['statuses'][$item['status']] ?? ucwords(str_replace('_',' ',$item['status']))); ?><br><small><?php echo esc_html(ucfirst($item['priority'])); ?></small></td>
                    <td><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:flex;gap:6px;flex-wrap:wrap"><input type="hidden" name="action" value="elev8_update_production_queue_item"><input type="hidden" name="job_id" value="<?php echo esc_attr((string)$item['id']); ?>"><?php wp_nonce_field('elev8_update_production_queue_item_'.$item['id']); ?><select name="status"><?php foreach ($data['statuses'] as $key=>$label): ?><option value="<?php echo esc_attr($key); ?>" <?php selected($item['status'],$key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select><select name="assigned_user_id"><option value="0"><?php esc_html_e('Unassigned','elev8-os'); ?></option><?php foreach ($data['workers'] as $worker): ?><option value="<?php echo esc_attr((string)$worker->ID); ?>" <?php selected($item['assigned_user_id'],(int)$worker->ID); ?>><?php echo esc_html($worker->display_name); ?></option><?php endforeach; ?></select><button class="button button-primary"><?php esc_html_e('Save','elev8-os'); ?></button></form></td>
                </tr><?php endforeach; ?></tbody></table><?php endif; ?>
            </section>
        </main><?php return (string)ob_get_clean();
    }

    public static function handle_update(): void {
        $job_id = absint($_POST['job_id'] ?? 0);
        check_admin_referer('elev8_update_production_queue_item_'.$job_id);
        $result = Elev8_OS_Production_Workspace_Service::update_job($job_id, sanitize_key((string)($_POST['status'] ?? '')), absint($_POST['assigned_user_id'] ?? 0));
        if (is_wp_error($result)) { wp_safe_redirect(add_query_arg('production_error', rawurlencode($result->get_error_message()), self::url())); exit; }
        wp_safe_redirect(add_query_arg('production_saved', '1', self::url())); exit;
    }
}

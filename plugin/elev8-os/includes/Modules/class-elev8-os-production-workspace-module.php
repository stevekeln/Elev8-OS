<?php
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Production_Workspace_Module {
    private const SHORTCODE = 'elev8_production_workspace';
    private const ADMIN_SLUG = 'elev8-production-workspace';

    public static function init(): void {
        add_shortcode(self::SHORTCODE, [__CLASS__, 'shortcode']);
        add_action('admin_menu', [__CLASS__, 'register_menu'], 35);
        add_action('admin_post_elev8_update_production_queue_item', [__CLASS__, 'handle_update']);
        add_action('admin_post_elev8_review_production_quality', [__CLASS__, 'handle_quality']);
        add_action('admin_post_elev8_record_production_fulfillment', [__CLASS__, 'handle_fulfillment']);
        add_action('admin_post_elev8_save_production_workstation', [__CLASS__, 'handle_workstation']);
        add_action('admin_post_elev8_save_production_cycle', [__CLASS__, 'handle_cycle']);
        add_action('admin_post_elev8_assign_production_workstation', [__CLASS__, 'handle_allocation']);
        add_action('admin_post_elev8_sync_production_compensation', [__CLASS__, 'handle_compensation']);
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
            'job_type' => sanitize_key((string)($_GET['production_type'] ?? '')),
            'source' => sanitize_key((string)($_GET['production_source'] ?? '')),
        ];
        $data = Elev8_OS_Production_Workspace_Service::snapshot($filters);
        ob_start(); ?>
        <main style="max-width:1280px;margin:0 auto;padding:24px">
            <header style="margin-bottom:20px"><p style="text-transform:uppercase;letter-spacing:.08em;color:#666;margin:0"><?php esc_html_e('Operations Workspace · Glass Configuration', 'elev8-os'); ?></p><h1 style="margin:.2em 0"><?php esc_html_e('Production', 'elev8-os'); ?></h1><p><?php esc_html_e('Run today’s production from one queue. Glass jobs remain authoritative in Glass Operations; this workspace coordinates the view.', 'elev8-os'); ?></p></header>
            <?php if (isset($_GET['production_saved'])): ?><div class="notice notice-success inline"><p><?php echo !empty($_GET['production_message']) ? esc_html(sanitize_text_field(wp_unslash((string)$_GET['production_message']))) : esc_html__('Production item updated.', 'elev8-os'); ?></p></div><?php endif; ?>
            <?php if (isset($_GET['production_error'])): ?><div class="notice notice-error inline"><p><?php echo esc_html(sanitize_text_field(wp_unslash((string)$_GET['production_error']))); ?></p></div><?php endif; ?>
            <section style="display:grid;grid-template-columns:repeat(auto-fit,minmax(145px,1fr));gap:12px;margin-bottom:20px">
                <?php foreach ([['Ready','ready'],['Running','running'],['Waiting','waiting'],['Blocked','blocked'],['Late','late'],['Quality attention','quality'],['Handoff','fulfillment'],['Completed today','completed_today']] as $metric): ?>
                    <article style="background:#fff;border:1px solid #ddd;border-radius:16px;padding:16px"><strong style="font-size:28px;display:block"><?php echo esc_html((string)$data['metrics'][$metric[1]]); ?></strong><span><?php echo esc_html__($metric[0], 'elev8-os'); ?></span></article>
                <?php endforeach; ?>
            </section>
            <section style="background:#fff;border:1px solid #ddd;border-radius:18px;padding:18px;margin-bottom:18px"><h2 style="margin-top:0"><?php esc_html_e('Manager Daily Production Brief','elev8-os'); ?></h2><ul style="margin-bottom:0"><?php foreach ($data['brief'] as $line): ?><li><?php echo esc_html($line); ?></li><?php endforeach; ?></ul></section>
            <section style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px;margin-bottom:18px">
                <article style="background:#fff;border:1px solid #ddd;border-radius:18px;padding:18px">
                    <h2 style="margin-top:0"><?php esc_html_e('Workstations','elev8-os'); ?></h2>
                    <?php if (!$data['workstations']): ?><p><?php esc_html_e('No workstations are configured yet. Add a bench, kiln, annealer, cold-working station, or other production area.','elev8-os'); ?></p><?php else: ?><ul><?php foreach ($data['workstations'] as $station): ?><li><strong><?php echo esc_html($station['name']); ?></strong> · <?php echo esc_html(Elev8_OS_Production_Coordination_Service::workstation_types()[$station['workstation_type']] ?? $station['workstation_type']); ?> · <?php echo esc_html((string)$station['capacity_units']); ?> <?php esc_html_e('capacity','elev8-os'); ?></li><?php endforeach; ?></ul><?php endif; ?>
                    <details><summary><?php esc_html_e('Add workstation','elev8-os'); ?></summary><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:grid;gap:8px;margin-top:10px"><input type="hidden" name="action" value="elev8_save_production_workstation"><?php wp_nonce_field('elev8_save_production_workstation'); ?><input name="name" required placeholder="<?php esc_attr_e('Workstation name','elev8-os'); ?>"><select name="workstation_type"><?php foreach (Elev8_OS_Production_Coordination_Service::workstation_types() as $key=>$label): ?><option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option><?php endforeach; ?></select><input type="number" min="0.01" step="0.01" name="capacity_units" value="1" placeholder="<?php esc_attr_e('Capacity','elev8-os'); ?>"><input type="number" min="0" name="asset_id" placeholder="<?php esc_attr_e('Optional Asset ID','elev8-os'); ?>"><textarea name="notes" rows="2" placeholder="<?php esc_attr_e('Safe operating or coordination notes','elev8-os'); ?>"></textarea><label><input type="checkbox" name="active" value="1" checked> <?php esc_html_e('Active','elev8-os'); ?></label><button class="button button-primary"><?php esc_html_e('Save workstation','elev8-os'); ?></button></form></details>
                </article>
                <article style="background:#fff;border:1px solid #ddd;border-radius:18px;padding:18px">
                    <h2 style="margin-top:0"><?php esc_html_e('Kiln / Annealing Coordination','elev8-os'); ?></h2>
                    <p><strong><?php echo esc_html((string)$data['capacity']['active_cycles']); ?></strong> <?php esc_html_e('active cycles','elev8-os'); ?> · <strong><?php echo esc_html((string)$data['capacity']['running_cycles']); ?></strong> <?php esc_html_e('running','elev8-os'); ?> · <strong><?php echo esc_html((string)$data['capacity']['cooling_cycles']); ?></strong> <?php esc_html_e('cooling','elev8-os'); ?></p>
                    <?php if ($data['capacity']['cycles']): ?><ul><?php foreach ($data['capacity']['cycles'] as $cycle): ?><li><strong><?php echo esc_html($cycle['workstation_name'] ?: __('Unassigned workstation','elev8-os')); ?></strong> · <?php echo esc_html(ucwords(str_replace('_',' ',$cycle['cycle_type']))); ?> · <?php echo esc_html(Elev8_OS_Production_Coordination_Service::cycle_statuses()[$cycle['status']] ?? $cycle['status']); ?><?php if ($cycle['scheduled_end']): ?> · <?php echo esc_html($cycle['scheduled_end']); ?><?php endif; ?></li><?php endforeach; ?></ul><?php endif; ?>
                    <?php if ($data['workstations']): ?><details><summary><?php esc_html_e('Schedule production cycle','elev8-os'); ?></summary><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:grid;gap:8px;margin-top:10px"><input type="hidden" name="action" value="elev8_save_production_cycle"><?php wp_nonce_field('elev8_save_production_cycle'); ?><select name="workstation_id" required><option value=""><?php esc_html_e('Choose workstation','elev8-os'); ?></option><?php foreach ($data['workstations'] as $station): ?><option value="<?php echo esc_attr((string)$station['id']); ?>"><?php echo esc_html($station['name']); ?></option><?php endforeach; ?></select><input name="cycle_type" value="annealing" placeholder="<?php esc_attr_e('Cycle type','elev8-os'); ?>"><label><?php esc_html_e('Start','elev8-os'); ?><input type="datetime-local" name="scheduled_start"></label><label><?php esc_html_e('Expected finish','elev8-os'); ?><input type="datetime-local" name="scheduled_end"></label><select name="status"><?php foreach (Elev8_OS_Production_Coordination_Service::cycle_statuses() as $key=>$label): ?><option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option><?php endforeach; ?></select><input type="number" min="0.01" step="0.01" name="capacity_units" value="1"><textarea name="notes" rows="2" placeholder="<?php esc_attr_e('Cycle, temperature, loading, or cooling notes','elev8-os'); ?>"></textarea><button class="button button-primary"><?php esc_html_e('Schedule cycle','elev8-os'); ?></button></form></details><?php endif; ?>
                </article>
            </section>
            <section style="background:#fff;border:1px solid #ddd;border-radius:18px;padding:18px;margin-bottom:18px">
                <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
                    <label><?php esc_html_e('Search','elev8-os'); ?><input type="search" name="production_search" value="<?php echo esc_attr($filters['search']); ?>"></label>
                    <label><?php esc_html_e('Status','elev8-os'); ?><select name="production_status"><option value=""><?php esc_html_e('All','elev8-os'); ?></option><?php foreach ($data['statuses'] as $key=>$label): ?><option value="<?php echo esc_attr($key); ?>" <?php selected($filters['status'],$key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
                    <label><?php esc_html_e('Type','elev8-os'); ?><select name="production_type"><option value=""><?php esc_html_e('All','elev8-os'); ?></option><?php foreach (['production'=>'Stock / production','custom'=>'Custom','cremation'=>'Cremation','memorial'=>'Memorial','repair'=>'Repair'] as $key=>$label): ?><option value="<?php echo esc_attr($key); ?>" <?php selected($filters['job_type'],$key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
                    <label><?php esc_html_e('Source','elev8-os'); ?><select name="production_source"><option value=""><?php esc_html_e('All','elev8-os'); ?></option><?php foreach (['shipping'=>'Shipping','head_shop'=>'Head Shop','cremation'=>'Cremation','website'=>'Website','wholesale'=>'Wholesale','repair'=>'Repair','internal_inventory'=>'Internal Inventory','custom'=>'Custom','manual'=>'Manual'] as $key=>$label): ?><option value="<?php echo esc_attr($key); ?>" <?php selected($filters['source'],$key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
                    <label><?php esc_html_e('Owner','elev8-os'); ?><select name="production_owner"><option value="0"><?php esc_html_e('All','elev8-os'); ?></option><?php foreach ($data['workers'] as $worker): ?><option value="<?php echo esc_attr((string)$worker->ID); ?>" <?php selected($filters['assigned_user_id'],(int)$worker->ID); ?>><?php echo esc_html($worker->display_name); ?></option><?php endforeach; ?></select></label>
                    <label><input type="checkbox" name="production_overdue" value="1" <?php checked($filters['overdue']); ?>> <?php esc_html_e('Late only','elev8-os'); ?></label>
                    <button class="button"><?php esc_html_e('Filter','elev8-os'); ?></button><a class="button" href="<?php echo esc_url(self::url()); ?>"><?php esc_html_e('Reset','elev8-os'); ?></a>
                </form>
            </section>
            <section style="background:#fff;border:1px solid #ddd;border-radius:18px;padding:18px;overflow:auto"><h2 style="margin-top:0"><?php esc_html_e('Production Queue','elev8-os'); ?></h2>
                <?php if (!$data['queue']): ?><p><?php esc_html_e('No production work matches these filters.','elev8-os'); ?></p><?php else: ?>
                <table class="widefat striped"><thead><tr><th><?php esc_html_e('Job','elev8-os'); ?></th><th><?php esc_html_e('Due','elev8-os'); ?></th><th><?php esc_html_e('Owner','elev8-os'); ?></th><th><?php esc_html_e('Progress','elev8-os'); ?></th><th><?php esc_html_e('Status','elev8-os'); ?></th><th><?php esc_html_e('Manager actions','elev8-os'); ?></th></tr></thead><tbody>
                <?php foreach ($data['queue'] as $item): ?><tr>
                    <td><strong><?php echo esc_html($item['title']); ?></strong><br><small><?php echo esc_html(ucwords(str_replace('_',' ',$item['type']))); ?><?php if ($item['customer']): ?> · <?php echo esc_html($item['customer']); ?><?php endif; ?><?php if ($item['order_number']): ?> · #<?php echo esc_html($item['order_number']); ?><?php endif; ?></small><?php if ($item['is_blocked']): ?><br><span style="color:#a00;font-weight:700"><?php esc_html_e('Blocked by missing customer or ashes information','elev8-os'); ?></span><?php endif; ?></td>
                    <td><?php echo $item['due_date'] ? esc_html($item['due_date']) : '—'; ?><?php if ($item['is_late']): ?><br><strong style="color:#a00"><?php esc_html_e('Late','elev8-os'); ?></strong><?php endif; ?></td>
                    <td><?php echo esc_html($item['assigned_name']); ?></td>
                    <td><?php echo esc_html((string)$item['completed_units'] . ' / ' . (string)$item['planned_units']); ?></td>
                    <td><?php echo esc_html($data['statuses'][$item['status']] ?? ucwords(str_replace('_',' ',$item['status']))); ?><br><small><?php echo esc_html(ucfirst($item['priority'])); ?></small><br><small><?php echo esc_html__('QC: ','elev8-os') . esc_html($data['quality_statuses'][$item['qc_status']] ?? $item['qc_status']); ?></small><br><small><?php echo esc_html__('Handoff: ','elev8-os') . esc_html($data['fulfillment_statuses'][$item['fulfillment_status']] ?? $item['fulfillment_status']); ?></small><?php if (!empty($item['allocation'])): ?><br><small><?php echo esc_html__('Station: ','elev8-os') . esc_html($item['allocation']['workstation_name'] ?: __('Unassigned','elev8-os')); ?><?php if (!empty($item['allocation']['cycle_type'])): ?> · <?php echo esc_html(ucwords(str_replace('_',' ',$item['allocation']['cycle_type']))); ?><?php endif; ?></small><?php endif; ?></td>
                    <td>
                        <details><summary><?php esc_html_e('Queue','elev8-os'); ?></summary><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:grid;gap:6px;margin-top:8px"><input type="hidden" name="action" value="elev8_update_production_queue_item"><input type="hidden" name="job_id" value="<?php echo esc_attr((string)$item['id']); ?>"><?php wp_nonce_field('elev8_update_production_queue_item_'.$item['id']); ?><select name="status"><?php foreach ($data['statuses'] as $key=>$label): ?><option value="<?php echo esc_attr($key); ?>" <?php selected($item['status'],$key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select><select name="assigned_user_id"><option value="0"><?php esc_html_e('Unassigned','elev8-os'); ?></option><?php foreach ($data['workers'] as $worker): ?><option value="<?php echo esc_attr((string)$worker->ID); ?>" <?php selected($item['assigned_user_id'],(int)$worker->ID); ?>><?php echo esc_html($worker->display_name); ?></option><?php endforeach; ?></select><button class="button button-primary"><?php esc_html_e('Save queue','elev8-os'); ?></button></form></details>
                        <details><summary><?php esc_html_e('Quality review','elev8-os'); ?></summary><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:grid;gap:6px;margin-top:8px"><input type="hidden" name="action" value="elev8_review_production_quality"><input type="hidden" name="job_id" value="<?php echo esc_attr((string)$item['id']); ?>"><?php wp_nonce_field('elev8_review_production_quality_'.$item['id']); ?><select name="qc_status"><?php foreach ($data['quality_statuses'] as $key=>$label): ?><option value="<?php echo esc_attr($key); ?>" <?php selected($item['qc_status'],$key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select><textarea name="qc_notes" rows="2" placeholder="<?php esc_attr_e('What was checked and what must change?','elev8-os'); ?>"><?php echo esc_textarea($item['qc_notes']); ?></textarea><button class="button"><?php esc_html_e('Save quality evidence','elev8-os'); ?></button></form></details>
                        <details><summary><?php esc_html_e('Pickup / shipping','elev8-os'); ?></summary><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:grid;gap:6px;margin-top:8px"><input type="hidden" name="action" value="elev8_record_production_fulfillment"><input type="hidden" name="job_id" value="<?php echo esc_attr((string)$item['id']); ?>"><?php wp_nonce_field('elev8_record_production_fulfillment_'.$item['id']); ?><select name="fulfillment_status"><?php foreach ($data['fulfillment_statuses'] as $key=>$label): ?><option value="<?php echo esc_attr($key); ?>" <?php selected($item['fulfillment_status'],$key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select><select name="fulfillment_method"><option value=""><?php esc_html_e('Choose method','elev8-os'); ?></option><option value="pickup" <?php selected($item['fulfillment_method'],'pickup'); ?>><?php esc_html_e('Pickup','elev8-os'); ?></option><option value="shipping" <?php selected($item['fulfillment_method'],'shipping'); ?>><?php esc_html_e('Shipping','elev8-os'); ?></option><option value="internal" <?php selected($item['fulfillment_method'],'internal'); ?>><?php esc_html_e('Internal handoff','elev8-os'); ?></option></select><textarea name="fulfillment_notes" rows="2" placeholder="<?php esc_attr_e('Tracking, pickup person, or handoff notes','elev8-os'); ?>"><?php echo esc_textarea($item['fulfillment_notes']); ?></textarea><button class="button"><?php esc_html_e('Save handoff','elev8-os'); ?></button></form></details>
                        <?php if ($data['workstations']): ?><details><summary><?php esc_html_e('Workstation / kiln','elev8-os'); ?></summary><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:grid;gap:6px;margin-top:8px"><input type="hidden" name="action" value="elev8_assign_production_workstation"><input type="hidden" name="job_id" value="<?php echo esc_attr((string)$item['id']); ?>"><?php wp_nonce_field('elev8_assign_production_workstation_'.$item['id']); ?><select name="workstation_id"><option value="0"><?php esc_html_e('No workstation','elev8-os'); ?></option><?php foreach ($data['workstations'] as $station): ?><option value="<?php echo esc_attr((string)$station['id']); ?>" <?php selected((int)($item['allocation']['workstation_id'] ?? 0),(int)$station['id']); ?>><?php echo esc_html($station['name']); ?></option><?php endforeach; ?></select><select name="cycle_id"><option value="0"><?php esc_html_e('No scheduled cycle','elev8-os'); ?></option><?php foreach ($data['cycles'] as $cycle): ?><option value="<?php echo esc_attr((string)$cycle['id']); ?>" <?php selected((int)($item['allocation']['cycle_id'] ?? 0),(int)$cycle['id']); ?>><?php echo esc_html(($cycle['workstation_name'] ?: __('Workstation','elev8-os')) . ' · ' . ucwords(str_replace('_',' ',$cycle['cycle_type'])) . ' · ' . ($cycle['scheduled_start'] ?: __('unscheduled','elev8-os'))); ?></option><?php endforeach; ?></select><label><?php esc_html_e('Planned start','elev8-os'); ?><input type="datetime-local" name="planned_start" value="<?php echo esc_attr(!empty($item['allocation']['planned_start']) ? str_replace(' ','T',substr($item['allocation']['planned_start'],0,16)) : ''); ?>"></label><label><?php esc_html_e('Planned finish','elev8-os'); ?><input type="datetime-local" name="planned_end" value="<?php echo esc_attr(!empty($item['allocation']['planned_end']) ? str_replace(' ','T',substr($item['allocation']['planned_end'],0,16)) : ''); ?>"></label><textarea name="notes" rows="2" placeholder="<?php esc_attr_e('Loading, workstation, or scheduling notes','elev8-os'); ?>"><?php echo esc_textarea((string)($item['allocation']['notes'] ?? '')); ?></textarea><button class="button"><?php esc_html_e('Save allocation','elev8-os'); ?></button></form></details><?php endif; ?>
                        <?php if ($item['status'] === 'completed'): ?><details><summary><?php esc_html_e('Compensation evidence','elev8-os'); ?></summary><p><small><?php esc_html_e('Creates pending payout entries only from manager-approved completed production lines. Existing entries are not duplicated.','elev8-os'); ?></small></p><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="elev8_sync_production_compensation"><input type="hidden" name="job_id" value="<?php echo esc_attr((string)$item['id']); ?>"><?php wp_nonce_field('elev8_sync_production_compensation_'.$item['id']); ?><button class="button"><?php esc_html_e('Sync compensation evidence','elev8-os'); ?></button></form></details><?php endif; ?>
                    </td>
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


    public static function handle_quality(): void {
        $job_id = absint($_POST['job_id'] ?? 0);
        check_admin_referer('elev8_review_production_quality_'.$job_id);
        $result = Elev8_OS_Production_Workspace_Service::review_quality($job_id, sanitize_key((string)($_POST['qc_status'] ?? '')), sanitize_textarea_field(wp_unslash((string)($_POST['qc_notes'] ?? ''))));
        if (is_wp_error($result)) { wp_safe_redirect(add_query_arg('production_error', rawurlencode($result->get_error_message()), self::url())); exit; }
        wp_safe_redirect(add_query_arg('production_saved', 'quality', self::url())); exit;
    }

    public static function handle_fulfillment(): void {
        $job_id = absint($_POST['job_id'] ?? 0);
        check_admin_referer('elev8_record_production_fulfillment_'.$job_id);
        $result = Elev8_OS_Production_Workspace_Service::record_fulfillment($job_id, sanitize_key((string)($_POST['fulfillment_status'] ?? '')), sanitize_key((string)($_POST['fulfillment_method'] ?? '')), sanitize_textarea_field(wp_unslash((string)($_POST['fulfillment_notes'] ?? ''))));
        if (is_wp_error($result)) { wp_safe_redirect(add_query_arg('production_error', rawurlencode($result->get_error_message()), self::url())); exit; }
        wp_safe_redirect(add_query_arg('production_saved', 'fulfillment', self::url())); exit;
    }

    public static function handle_workstation(): void {
        check_admin_referer('elev8_save_production_workstation');
        $result = Elev8_OS_Production_Workspace_Service::save_workstation(wp_unslash($_POST));
        if (is_wp_error($result)) { wp_safe_redirect(add_query_arg('production_error', rawurlencode($result->get_error_message()), self::url())); exit; }
        wp_safe_redirect(add_query_arg('production_saved', 'workstation', self::url())); exit;
    }

    public static function handle_cycle(): void {
        check_admin_referer('elev8_save_production_cycle');
        $result = Elev8_OS_Production_Workspace_Service::save_cycle(wp_unslash($_POST));
        if (is_wp_error($result)) { wp_safe_redirect(add_query_arg('production_error', rawurlencode($result->get_error_message()), self::url())); exit; }
        wp_safe_redirect(add_query_arg('production_saved', 'cycle', self::url())); exit;
    }

    public static function handle_allocation(): void {
        $job_id = absint($_POST['job_id'] ?? 0);
        check_admin_referer('elev8_assign_production_workstation_'.$job_id);
        $result = Elev8_OS_Production_Workspace_Service::assign_workstation(wp_unslash($_POST));
        if (is_wp_error($result)) { wp_safe_redirect(add_query_arg('production_error', rawurlencode($result->get_error_message()), self::url())); exit; }
        wp_safe_redirect(add_query_arg('production_saved', 'allocation', self::url())); exit;
    }

    public static function handle_compensation(): void {
        $job_id = absint($_POST['job_id'] ?? 0);
        check_admin_referer('elev8_sync_production_compensation_'.$job_id);
        $result = Elev8_OS_Production_Workspace_Service::sync_compensation($job_id);
        if (is_wp_error($result)) { wp_safe_redirect(add_query_arg('production_error', rawurlencode($result->get_error_message()), self::url())); exit; }
        $message = sprintf(__('Compensation sync complete: %1$d created, %2$d already existed, %3$d skipped.','elev8-os'), (int)$result['created'], (int)$result['existing'], (int)$result['skipped']);
        wp_safe_redirect(add_query_arg(['production_saved'=>'compensation','production_message'=>rawurlencode($message)], self::url())); exit;
    }

}

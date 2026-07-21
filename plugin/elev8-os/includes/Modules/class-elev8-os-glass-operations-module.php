<?php
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Glass_Operations_Module {
    const SLUG = 'elev8-glass-operations';

    public static function init(): void {
        Elev8_OS_Glass_Operations_Service::init();
        add_action('admin_menu', [__CLASS__, 'menu'], 18);
        add_action('admin_enqueue_scripts', [__CLASS__, 'assets']);
        add_action('admin_post_elev8_os_save_glass_job', [__CLASS__, 'save_job']);
        add_action('admin_post_elev8_os_update_glass_job', [__CLASS__, 'update_job']);
        add_action('admin_post_elev8_os_save_glass_job_line', [__CLASS__, 'save_job_line']);
        add_action('admin_post_elev8_os_update_glass_job_line', [__CLASS__, 'update_job_line']);
        add_action('admin_post_elev8_os_save_glass_entry', [__CLASS__, 'save_entry']);
        add_action('admin_post_elev8_os_approve_glass_entry', [__CLASS__, 'approve_entry']);
        add_action('admin_post_elev8_os_save_glassblower_profile', [__CLASS__, 'save_glassblower_profile']);
        add_action('admin_post_elev8_os_import_cremation_orders', [__CLASS__, 'import_orders']);
        add_action('wp_ajax_elev8_os_move_glass_job', [__CLASS__, 'ajax_move_job']);
    }

    public static function menu(): void {
        add_submenu_page('elev8-os', 'Glass Manager Dashboard', 'Glass Operations', 'read', self::SLUG, [__CLASS__, 'render']);
    }

    public static function assets(string $hook): void {
        if (strpos($hook, self::SLUG) === false) { return; }
        wp_enqueue_style('elev8-glass-operations', ELEV8_OS_URL . 'assets/css/glass-operations.css', [], ELEV8_OS_VERSION);
        if (sanitize_key($_GET['view'] ?? '') === 'board') {
            wp_enqueue_script('elev8-glass-production-board', ELEV8_OS_URL . 'assets/js/glass-production-board.js', [], ELEV8_OS_VERSION, true);
            wp_localize_script('elev8-glass-production-board', 'Elev8GlassBoard', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('elev8_glass_board'),
                'errorMessage' => 'The production job could not be updated.',
            ]);
        }
    }

    private static function can_manage(): bool { return Elev8_OS_Access_Service::user_can('view_glass_dashboard'); }
    private static function guard(): void { if (!self::can_manage()) { wp_die('You do not have access to Glass Operations.'); } }
    private static function url(array $args = []): string { return add_query_arg(array_merge(['page' => self::SLUG], $args), admin_url('admin.php')); }

    public static function render(): void {
        self::guard();
        $view = sanitize_key($_GET['view'] ?? 'dashboard');
        $summary = Elev8_OS_Glass_Operations_Service::summary();
        $workers = Elev8_OS_Glass_Operations_Service::glass_workers();
        $products = class_exists('Elev8_OS_Production_Catalog_Service') ? Elev8_OS_Production_Catalog_Service::products(['active' => 1]) : [];
        ?>
        <div class="wrap elev8-glass">
            <header class="elev8-glass__hero"><div><p class="eyebrow">Elev8 Premier</p><h1>Glass Manager Dashboard</h1><p>Production jobs, blower assignments, QC and automatic payout review in one operating view.</p></div>
                <nav>
                    <a class="button <?php echo $view === 'dashboard' ? 'button-primary' : ''; ?>" href="<?php echo esc_url(self::url()); ?>">Dashboard</a>
                    <a class="button <?php echo $view === 'board' ? 'button-primary' : ''; ?>" href="<?php echo esc_url(self::url(['view' => 'board'])); ?>">Production board</a>
                    <a class="button <?php echo $view === 'new-job' ? 'button-primary' : ''; ?>" href="<?php echo esc_url(self::url(['view' => 'new-job'])); ?>">New job</a>
                    <a class="button <?php echo $view === 'payouts' ? 'button-primary' : ''; ?>" href="<?php echo esc_url(self::url(['view' => 'payouts'])); ?>">Pay sheets</a>
                    <a class="button <?php echo $view === 'team' ? 'button-primary' : ''; ?>" href="<?php echo esc_url(self::url(['view' => 'team'])); ?>">Glassblower Team</a>
                </nav>
            </header>
            <?php if (!empty($_GET['notice'])) : ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html(wp_unslash($_GET['notice'])); ?></p></div><?php endif; ?>
            <?php
            if ($view === 'board') { self::production_board($workers); }
            elseif ($view === 'new-job') { self::job_form($workers, $products); }
            elseif ($view === 'payouts') { self::payouts($workers); }
            elseif ($view === 'team') { self::team($workers); }
            elseif ($view === 'job') { self::job_detail(absint($_GET['job_id'] ?? 0), $workers, $products); }
            else { self::dashboard($summary); }
            ?>
        </div>
        <?php
    }

    private static function dashboard(array $s): void {
        $jobs = Elev8_OS_Glass_Operations_Service::jobs(['limit' => 100]);
        ?><section class="elev8-glass__kpis"><article><strong><?php echo absint($s['open_jobs']); ?></strong><span>Open jobs</span></article><article><strong><?php echo absint($s['cremation_ready']); ?></strong><span>Cremation ready</span></article><article><strong><?php echo absint($s['overdue']); ?></strong><span>Overdue</span></article><article><strong>$<?php echo number_format_i18n($s['pending_payout'], 2); ?></strong><span>Pending approval</span></article><article><strong>$<?php echo number_format_i18n($s['approved_payout'], 2); ?></strong><span>Approved production pay</span></article></section>
        <div class="elev8-glass__grid"><section class="panel panel-wide"><div class="panel-head"><div><h2>Active production queue</h2><p>Jobs can originate from Shipping, Head Shop, Cremation, Website, Wholesale, Repair or internal stock.</p></div><a class="button button-primary" href="<?php echo esc_url(self::url(['view' => 'new-job'])); ?>">Add job</a></div><?php self::jobs_table($jobs); ?></section>
        <aside class="panel"><h2>Glassblower roster</h2><p>Only active users on the Glassblower Team appear in assignment and payout lists.</p><a class="button" href="<?php echo esc_url(self::url(['view' => 'team'])); ?>">Manage team</a><hr><h3>Cremation orders</h3><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('elev8_import_cremation_orders'); ?><input type="hidden" name="action" value="elev8_os_import_cremation_orders"><button class="button button-primary">Import new cremation orders</button></form></aside></div><?php
    }

    private static function jobs_table(array $jobs): void {
        if (!$jobs) { echo '<div class="empty">No jobs yet. Add the first production or cremation job.</div>'; return; }
        ?><div class="table-wrap"><table><thead><tr><th>Priority</th><th>Job</th><th>Source</th><th>Customer</th><th>Assigned</th><th>Due</th><th>Status</th><th></th></tr></thead><tbody><?php foreach ($jobs as $j) : $u = $j['assigned_user_id'] ? get_userdata((int)$j['assigned_user_id']) : null; ?><tr><td><span class="pill priority-<?php echo esc_attr($j['priority']); ?>"><?php echo esc_html(ucfirst($j['priority'])); ?></span></td><td><strong><?php echo esc_html($j['product_name'] ?: 'Untitled job'); ?></strong><small><?php echo esc_html(ucfirst($j['job_type']) . ($j['order_number'] ? ' · #' . $j['order_number'] : '')); ?></small></td><td><?php echo esc_html(ucwords(str_replace('_', ' ', $j['source']))); ?></td><td><?php echo esc_html($j['customer_name'] ?: 'Internal production'); ?></td><td><?php echo esc_html($u ? $u->display_name : 'Unassigned'); ?></td><td><?php echo esc_html($j['due_date'] ?: 'Unavailable'); ?></td><td><span class="pill status-<?php echo esc_attr($j['status']); ?>"><?php echo esc_html(ucwords(str_replace('_', ' ', $j['status']))); ?></span></td><td><a class="button button-small" href="<?php echo esc_url(self::url(['view' => 'job', 'job_id' => $j['id']])); ?>">Open</a></td></tr><?php endforeach; ?></tbody></table></div><?php
    }

    private static function production_board(array $workers): void {
        $filters = [
            'search' => sanitize_text_field(wp_unslash($_GET['s'] ?? '')),
            'assigned_user_id' => absint($_GET['blower'] ?? 0),
            'source' => sanitize_key($_GET['source'] ?? ''),
            'priority' => sanitize_key($_GET['priority'] ?? ''),
            'overdue' => empty($_GET['overdue']) ? 0 : 1,
        ];
        $jobs = Elev8_OS_Glass_Operations_Service::board_jobs($filters);
        $statuses = Elev8_OS_Glass_Operations_Service::workflow_statuses();
        $workload = Elev8_OS_Glass_Operations_Service::board_workload($jobs, $workers);
        $sources = ['shipping'=>'Shipping','head_shop'=>'Head Shop','cremation'=>'Cremation','website'=>'Website','wholesale'=>'Wholesale','repair'=>'Repair','internal_inventory'=>'Internal Inventory','custom'=>'Custom','manual'=>'Manual'];
        ?>
        <section class="panel elev8-production-board-controls">
            <div class="panel-head"><div><h2>Production Board</h2><p>Move work through the studio, balance blower assignments and surface late jobs.</p></div><a class="button button-primary" href="<?php echo esc_url(self::url(['view'=>'new-job'])); ?>">Create job</a></div>
            <form method="get" class="elev8-board-filters">
                <input type="hidden" name="page" value="<?php echo esc_attr(self::SLUG); ?>"><input type="hidden" name="view" value="board">
                <label>Search<input type="search" name="s" value="<?php echo esc_attr($filters['search']); ?>" placeholder="Job, customer, order..."></label>
                <label>Glassblower<select name="blower"><option value="0">All blowers</option><?php foreach ($workers as $u) : ?><option value="<?php echo absint($u->ID); ?>" <?php selected($filters['assigned_user_id'],$u->ID); ?>><?php echo esc_html($u->display_name); ?></option><?php endforeach; ?></select></label>
                <label>Source<select name="source"><option value="">All sources</option><?php foreach ($sources as $key=>$label) : ?><option value="<?php echo esc_attr($key); ?>" <?php selected($filters['source'],$key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
                <label>Priority<select name="priority"><option value="">All priorities</option><?php foreach (['urgent','high','normal','low'] as $priority) : ?><option value="<?php echo esc_attr($priority); ?>" <?php selected($filters['priority'],$priority); ?>><?php echo esc_html(ucfirst($priority)); ?></option><?php endforeach; ?></select></label>
                <label class="elev8-board-check"><input type="checkbox" name="overdue" value="1" <?php checked($filters['overdue'],1); ?>> Overdue only</label>
                <button class="button button-primary">Apply</button><a class="button" href="<?php echo esc_url(self::url(['view'=>'board'])); ?>">Clear</a>
            </form>
        </section>
        <section class="elev8-board-workload" aria-label="Glassblower workload">
            <?php foreach ($workload as $row) : ?><article><strong><?php echo esc_html($row['label']); ?></strong><span><?php echo absint($row['open']); ?> open</span><?php if ($row['overdue']) : ?><em><?php echo absint($row['overdue']); ?> overdue</em><?php elseif ($row['due_today']) : ?><em><?php echo absint($row['due_today']); ?> due today</em><?php else : ?><small>On track</small><?php endif; ?></article><?php endforeach; ?>
        </section>
        <div class="elev8-production-board" data-board>
            <?php foreach ($statuses as $status => $label) : $column_jobs = array_values(array_filter($jobs, static fn(array $j): bool => $j['status'] === $status)); ?>
                <section class="elev8-board-column" data-status="<?php echo esc_attr($status); ?>">
                    <header><div><h3><?php echo esc_html($label); ?></h3><span><?php echo count($column_jobs); ?></span></div></header>
                    <div class="elev8-board-dropzone" data-dropzone>
                        <?php if (!$column_jobs) : ?><p class="elev8-board-empty">No jobs</p><?php endif; ?>
                        <?php foreach ($column_jobs as $job) : self::production_board_card($job, $workers, $statuses); endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>
        <?php
    }

    private static function production_board_card(array $job, array $workers, array $statuses): void {
        $user = $job['assigned_user_id'] ? get_userdata((int)$job['assigned_user_id']) : null;
        $today = current_time('Y-m-d');
        $is_overdue = !empty($job['due_date']) && $job['due_date'] < $today && !in_array($job['status'], ['completed','cancelled'], true);
        $is_today = !empty($job['due_date']) && $job['due_date'] === $today;
        ?>
        <article class="elev8-board-card priority-<?php echo esc_attr($job['priority']); ?><?php echo $is_overdue ? ' is-overdue' : ''; ?>" draggable="true" data-job-id="<?php echo absint($job['id']); ?>">
            <div class="elev8-board-card-top"><span class="pill priority-<?php echo esc_attr($job['priority']); ?>"><?php echo esc_html(ucfirst($job['priority'])); ?></span><small>#<?php echo absint($job['id']); ?></small></div>
            <h4><a href="<?php echo esc_url(self::url(['view'=>'job','job_id'=>$job['id']])); ?>"><?php echo esc_html($job['product_name'] ?: 'Untitled production job'); ?></a></h4>
            <p><?php echo esc_html($job['customer_name'] ?: 'Internal production'); ?></p>
            <dl><div><dt>Source</dt><dd><?php echo esc_html(ucwords(str_replace('_',' ',$job['source']))); ?></dd></div><div><dt>Lines</dt><dd><?php echo absint($job['line_count']); ?></dd></div><div><dt>Due</dt><dd class="<?php echo $is_overdue ? 'danger' : ($is_today ? 'warning' : ''); ?>"><?php echo esc_html($job['due_date'] ?: 'Unavailable'); ?></dd></div></dl>
            <label>Glassblower<select data-assignee><?php ?><option value="0">Unassigned</option><?php foreach ($workers as $worker) : ?><option value="<?php echo absint($worker->ID); ?>" <?php selected((int)$job['assigned_user_id'],$worker->ID); ?>><?php echo esc_html($worker->display_name); ?></option><?php endforeach; ?></select></label>
            <label>Status<select data-status-select><?php foreach ($statuses as $key=>$label) : ?><option value="<?php echo esc_attr($key); ?>" <?php selected($job['status'],$key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
            <div class="elev8-board-card-actions"><button type="button" class="button button-small" data-save-card>Save</button><a class="button button-small" href="<?php echo esc_url(self::url(['view'=>'job','job_id'=>$job['id']])); ?>">Open</a><span data-card-status aria-live="polite"></span></div>
        </article>
        <?php
    }

    private static function job_form(array $workers, array $products): void {
        ?><section class="panel"><h2>Create a production job</h2><p>The selected production product supplies the compensation and cost snapshot. More lines can be added after creation.</p><form class="form-grid" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('elev8_save_glass_job'); ?><input type="hidden" name="action" value="elev8_os_save_glass_job">
        <label>Job source<select name="source"><option value="shipping">Shipping</option><option value="head_shop">Head Shop</option><option value="cremation">Cremation</option><option value="website">Website</option><option value="wholesale">Wholesale</option><option value="repair">Repair</option><option value="internal_inventory">Internal Inventory</option><option value="custom">Custom</option></select></label>
        <label>Job type<select name="job_type"><option value="production">Production</option><option value="cremation">Cremation / memorial</option></select></label><label>Order number<input name="order_number"></label>
        <label>Production product<select name="production_product_id"><option value="0">Choose catalog product</option><?php foreach ($products as $p) : ?><option value="<?php echo absint($p['id']); ?>"><?php echo esc_html($p['product_name'] . ' · ' . ucwords($p['compensation_method'])); ?></option><?php endforeach; ?></select></label>
        <label class="span-2">Product or work requested<input name="product_name" required></label><label>Quantity<input type="number" min="1" step="0.01" name="quantity" value="1"></label><label>Priority<select name="priority"><option>normal</option><option>high</option><option>urgent</option><option>low</option></select></label><label>Due date<input type="date" name="due_date"></label><label>Assign blower<select name="assigned_user_id"><option value="0">Unassigned</option><?php foreach ($workers as $u) : ?><option value="<?php echo absint($u->ID); ?>"><?php echo esc_html($u->display_name); ?></option><?php endforeach; ?></select></label><label>Customer name<input name="customer_name"></label><label>Memorial name<input name="memorial_name"></label><label>Customer email<input type="email" name="customer_email"></label><label>Customer phone<input name="customer_phone"></label><label class="span-2">Colors / design<input name="colors"></label><label class="span-2">Engraving<input name="engraving"></label><label>Ashes status<select name="ashes_status"><option value="not_applicable">Not applicable</option><option value="waiting">Waiting for ashes</option><option value="received">Received</option><option value="returned">Returned</option></select></label><label>Status<select name="status"><?php foreach (['new','waiting_customer_info','waiting_ashes','ready_for_production','assigned','in_production','quality_control','ready_for_pickup','ready_to_ship','completed'] as $st) : ?><option value="<?php echo esc_attr($st); ?>"><?php echo esc_html(ucwords(str_replace('_',' ',$st))); ?></option><?php endforeach; ?></select></label><label class="span-2">Return / shipping instructions<textarea name="return_instructions" rows="3"></textarea></label><label class="span-2">Special notes<textarea name="special_notes" rows="4"></textarea></label><div class="span-2"><button class="button button-primary button-large">Create production job</button></div></form></section><?php
    }

    private static function job_detail(int $id, array $workers, array $products): void {
        $j = Elev8_OS_Glass_Operations_Service::job($id); if (!$j) { echo '<div class="notice notice-error"><p>Job not found.</p></div>'; return; }
        $lines = Elev8_OS_Glass_Operations_Service::job_lines($id);
        ?><p><a href="<?php echo esc_url(self::url()); ?>">← Back to queue</a></p><div class="elev8-glass__grid"><section class="panel panel-wide"><p class="eyebrow"><?php echo esc_html(ucfirst($j['job_type']) . ($j['order_number'] ? ' · Order #' . $j['order_number'] : '')); ?></p><h2><?php echo esc_html($j['product_name']); ?></h2><div class="detail-grid"><?php foreach (['customer_name'=>'Customer','customer_email'=>'Email','customer_phone'=>'Phone','memorial_name'=>'Memorial name','quantity'=>'Quantity','colors'=>'Colors / design','engraving'=>'Engraving','ashes_status'=>'Ashes','return_instructions'=>'Return instructions','special_notes'=>'Special notes'] as $k=>$label) : ?><div><small><?php echo esc_html($label); ?></small><strong><?php echo $j[$k] !== '' ? nl2br(esc_html(ucwords(str_replace('_',' ',(string)$j[$k])))) : 'Unavailable'; ?></strong></div><?php endforeach; ?></div></section><aside class="panel"><h2>Manager controls</h2><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('elev8_update_glass_job_' . $id); ?><input type="hidden" name="action" value="elev8_os_update_glass_job"><input type="hidden" name="job_id" value="<?php echo absint($id); ?>"><label>Status<select name="status"><?php foreach (['new','waiting_customer_info','waiting_ashes','ready_for_production','assigned','in_production','quality_control','ready_for_pickup','ready_to_ship','completed','cancelled'] as $st) : ?><option value="<?php echo esc_attr($st); ?>" <?php selected($j['status'],$st); ?>><?php echo esc_html(ucwords(str_replace('_',' ',$st))); ?></option><?php endforeach; ?></select></label><label>Assigned blower<select name="assigned_user_id"><option value="0">Unassigned</option><?php foreach ($workers as $u) : ?><option value="<?php echo absint($u->ID); ?>" <?php selected($j['assigned_user_id'],$u->ID); ?>><?php echo esc_html($u->display_name); ?></option><?php endforeach; ?></select></label><label>Due date<input type="date" name="due_date" value="<?php echo esc_attr($j['due_date']); ?>"></label><button class="button button-primary">Update job</button></form></aside></div>
        <section class="panel"><div class="panel-head"><div><h2>Production lines</h2><p>Each line preserves the catalog version, payout rule and material-cost snapshot used when it was added.</p></div></div><?php self::lines_table($lines, $j); ?><hr><h3>Add production line</h3><form class="form-grid compact" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('elev8_save_glass_job_line_' . $id); ?><input type="hidden" name="action" value="elev8_os_save_glass_job_line"><input type="hidden" name="job_id" value="<?php echo absint($id); ?>"><label class="span-2">Production product<select name="production_product_id" required><option value="">Choose product</option><?php foreach ($products as $p) : ?><option value="<?php echo absint($p['id']); ?>"><?php echo esc_html($p['product_name'] . ' · ' . ucwords($p['compensation_method'])); ?></option><?php endforeach; ?></select></label><label>Quantity<input type="number" min="0.01" step="0.01" name="quantity" value="1"></label><div><button class="button button-primary">Add line</button></div></form></section>
        <section class="panel"><h2>Record blower work</h2><?php self::entry_form($workers, $j, $lines); ?></section><?php
    }

    private static function lines_table(array $lines, array $job): void {
        if (!$lines) { echo '<div class="empty">No production lines have been added.</div>'; return; }
        ?><div class="table-wrap"><table><thead><tr><th>Product</th><th>Pay method</th><th>Planned</th><th>Completed</th><th>Actual time</th><th>QC</th><th>Approval</th></tr></thead><tbody><?php foreach ($lines as $line) : ?><tr><td><strong><?php echo esc_html($line['item_name']); ?></strong><small>Catalog v<?php echo absint($line['product_version']); ?> · Material $<?php echo number_format_i18n((float)$line['material_cost'],2); ?></small></td><td><?php echo esc_html(ucwords($line['compensation_method'])); ?><?php if ((float)$line['piecework_rate'] > 0) : ?><small>$<?php echo number_format_i18n((float)$line['piecework_rate'],2); ?> / <?php echo esc_html($line['piecework_unit']); ?></small><?php endif; ?></td><td><?php echo esc_html($line['quantity']); ?></td><td><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('elev8_update_glass_job_line_' . $line['id']); ?><input type="hidden" name="action" value="elev8_os_update_glass_job_line"><input type="hidden" name="line_id" value="<?php echo absint($line['id']); ?>"><input type="hidden" name="job_id" value="<?php echo absint($job['id']); ?>"><input class="small-text" type="number" step="0.01" name="quantity_completed" value="<?php echo esc_attr($line['quantity_completed']); ?>"></td><td><input class="small-text" type="number" step="1" name="actual_minutes" value="<?php echo esc_attr($line['actual_minutes']); ?>"> min</td><td><select name="qc_status"><option value="not_reviewed" <?php selected($line['qc_status'],'not_reviewed'); ?>>Not reviewed</option><option value="passed" <?php selected($line['qc_status'],'passed'); ?>>Passed</option><option value="rework" <?php selected($line['qc_status'],'rework'); ?>>Rework</option><option value="rejected" <?php selected($line['qc_status'],'rejected'); ?>>Rejected</option></select></td><td><label><input type="checkbox" name="manager_approved" value="1" <?php checked($line['manager_approved'],1); ?>> Manager</label><label><input type="checkbox" name="payroll_approved" value="1" <?php checked($line['payroll_approved'],1); ?>> Payroll</label><button class="button button-small">Save</button></form></td></tr><?php endforeach; ?></tbody></table></div><?php
    }

    private static function payouts(array $workers): void {
        $entries = Elev8_OS_Glass_Operations_Service::entries();
        ?><div class="elev8-glass__grid"><section class="panel"><h2>Add blower work</h2><?php self::entry_form($workers, null, []); ?></section><section class="panel panel-wide"><h2>Automatic pay sheet review</h2><p>Approved hourly and piecework records stay linked to their source production job.</p><?php if (!$entries) { echo '<div class="empty">No blower work has been recorded.</div>'; } else { ?><div class="table-wrap"><table><thead><tr><th>Date</th><th>Blower</th><th>Work</th><th>Calculation</th><th>Total</th><th>Status</th><th></th></tr></thead><tbody><?php foreach ($entries as $e) : $u = get_userdata((int)$e['blower_user_id']); ?><tr><td><?php echo esc_html($e['work_date']); ?></td><td><?php echo esc_html($u ? $u->display_name : 'Unknown user'); ?></td><td><?php echo esc_html($e['item_name']); ?></td><td><?php echo esc_html($e['pay_method'] === 'hourly' ? $e['minutes'] . ' min @ $' . $e['rate'] . '/hr' : $e['quantity'] . ' × $' . $e['rate']); ?></td><td><strong>$<?php echo number_format_i18n((float)$e['total'],2); ?></strong></td><td><?php echo esc_html(ucfirst($e['approval_status'])); ?></td><td><?php if ($e['approval_status'] === 'pending') : ?><form class="inline" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('elev8_approve_glass_entry_' . $e['id']); ?><input type="hidden" name="action" value="elev8_os_approve_glass_entry"><input type="hidden" name="entry_id" value="<?php echo absint($e['id']); ?>"><button name="status" value="approved" class="button button-small">Approve</button><button name="status" value="rejected" class="button button-small">Reject</button></form><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div><?php } ?></section></div><?php
    }

    private static function entry_form(array $workers, ?array $job, array $lines): void {
        ?><form class="form-grid compact" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('elev8_save_glass_entry'); ?><input type="hidden" name="action" value="elev8_os_save_glass_entry"><input type="hidden" name="job_id" value="<?php echo absint($job['id'] ?? 0); ?>"><label>Blower<select name="blower_user_id" required><option value="">Choose blower</option><?php foreach ($workers as $u) : ?><option value="<?php echo absint($u->ID); ?>" <?php selected(absint($job['assigned_user_id'] ?? 0),$u->ID); ?>><?php echo esc_html($u->display_name); ?></option><?php endforeach; ?></select></label><label>Work date<input type="date" name="work_date" value="<?php echo esc_attr(current_time('Y-m-d')); ?>"></label>
        <?php if ($lines) : ?><label class="span-2">Production line<select name="job_line_id"><option value="0">Choose line</option><?php foreach ($lines as $line) : ?><option value="<?php echo absint($line['id']); ?>"><?php echo esc_html($line['item_name'] . ' · ' . ucwords($line['compensation_method'])); ?></option><?php endforeach; ?></select></label><?php endif; ?>
        <label class="span-2">Item / work completed<input name="item_name" value="<?php echo esc_attr($job['product_name'] ?? ''); ?>" required></label><label>Pay method<select name="pay_method"><option value="piece_rate">Piece rate</option><option value="hourly">Hourly</option></select></label><label>Quantity<input type="number" step="0.01" name="quantity" value="1"></label><label>Rate ($)<input type="number" step="0.01" name="rate" placeholder="Uses profile/catalog when blank"></label><label>Minutes (hourly only)<input type="number" step="1" name="minutes" value="0"></label><label>Bonus ($)<input type="number" step="0.01" name="bonus" value="0"></label><label>Adjustment ($)<input type="number" step="0.01" name="adjustment" value="0"></label><label class="span-2">Notes<textarea name="notes" rows="2"></textarea></label><div class="span-2"><button class="button button-primary">Add to payout review</button></div></form><?php
    }

    private static function team(array $workers): void {
        $all = get_users(['orderby' => 'display_name', 'order' => 'ASC']);
        ?><div class="elev8-glass__grid"><section class="panel"><h2>Add or update glassblower</h2><p>Only active compensation profiles with the Elev8 Glassblower role appear in production dropdowns.</p><form class="form-grid compact" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('elev8_save_glassblower_profile'); ?><input type="hidden" name="action" value="elev8_os_save_glassblower_profile"><label class="span-2">WordPress user<select name="user_id" required><option value="">Choose user</option><?php foreach ($all as $u) : ?><option value="<?php echo absint($u->ID); ?>"><?php echo esc_html($u->display_name . ' · ' . $u->user_email); ?></option><?php endforeach; ?></select></label><label>Hourly rate<input type="number" step="0.01" name="hourly_rate" value="18.00"></label><label>Effective date<input type="date" name="effective_date" value="<?php echo esc_attr(current_time('Y-m-d')); ?>"></label><label><input type="checkbox" name="piecework_eligible" value="1" checked> Piecework eligible</label><label><input type="checkbox" name="active" value="1" checked> Active on roster</label><label class="span-2">Notes<textarea name="notes"></textarea></label><div class="span-2"><button class="button button-primary">Save glassblower</button></div></form></section><section class="panel panel-wide"><h2>Active Glassblower Team</h2><?php if (!$workers) { echo '<div class="empty">No active glassblowers found.</div>'; } else { ?><div class="table-wrap"><table><thead><tr><th>Name</th><th>Email</th><th>Hourly rate</th><th>Piecework</th><th>Dashboard</th></tr></thead><tbody><?php foreach ($workers as $u) : $profile = Elev8_OS_Production_Catalog_Service::compensation_profile((int)$u->ID); ?><tr><td><strong><?php echo esc_html($u->display_name); ?></strong></td><td><?php echo esc_html($u->user_email); ?></td><td>$<?php echo number_format_i18n((float)($profile['hourly_rate'] ?? 0),2); ?></td><td><?php echo !empty($profile['piecework_eligible']) ? 'Eligible' : 'No'; ?></td><td><a class="button button-small" href="<?php echo esc_url(Elev8_OS_Portal_Page_Manager::get_url('dashboard')); ?>" target="_blank">Open dashboard</a></td></tr><?php endforeach; ?></tbody></table></div><?php } ?></section></div><?php
    }

    public static function save_job(): void { self::guard(); check_admin_referer('elev8_save_glass_job'); $data = wp_unslash($_POST); $r = Elev8_OS_Glass_Operations_Service::save_job($data); if (!is_wp_error($r) && !empty($data['production_product_id'])) { Elev8_OS_Glass_Operations_Service::save_job_line(['job_id' => $r, 'production_product_id' => absint($data['production_product_id']), 'quantity' => (float)($data['quantity'] ?? 1)]); } $m = is_wp_error($r) ? $r->get_error_message() : 'Production job created.'; wp_safe_redirect(self::url(['view' => is_wp_error($r) ? 'new-job' : 'job', 'job_id' => is_wp_error($r) ? 0 : $r, 'notice' => $m])); exit; }
    public static function update_job(): void { self::guard(); $id = absint($_POST['job_id'] ?? 0); check_admin_referer('elev8_update_glass_job_' . $id); Elev8_OS_Glass_Operations_Service::update_job($id, wp_unslash($_POST)); wp_safe_redirect(self::url(['view' => 'job', 'job_id' => $id, 'notice' => 'Job updated.'])); exit; }
    public static function save_job_line(): void { self::guard(); $job = absint($_POST['job_id'] ?? 0); check_admin_referer('elev8_save_glass_job_line_' . $job); $r = Elev8_OS_Glass_Operations_Service::save_job_line(wp_unslash($_POST)); wp_safe_redirect(self::url(['view' => 'job', 'job_id' => $job, 'notice' => is_wp_error($r) ? $r->get_error_message() : 'Production line added.'])); exit; }
    public static function update_job_line(): void { self::guard(); $line = absint($_POST['line_id'] ?? 0); $job = absint($_POST['job_id'] ?? 0); check_admin_referer('elev8_update_glass_job_line_' . $line); Elev8_OS_Glass_Operations_Service::update_job_line($line, wp_unslash($_POST)); wp_safe_redirect(self::url(['view' => 'job', 'job_id' => $job, 'notice' => 'Production line updated.'])); exit; }
    public static function save_entry(): void { self::guard(); check_admin_referer('elev8_save_glass_entry'); $data = wp_unslash($_POST); $r = Elev8_OS_Glass_Operations_Service::save_entry($data); $job = absint($data['job_id'] ?? 0); wp_safe_redirect(self::url(['view' => $job ? 'job' : 'payouts', 'job_id' => $job, 'notice' => is_wp_error($r) ? $r->get_error_message() : 'Blower work added for review.'])); exit; }
    public static function approve_entry(): void { self::guard(); $id = absint($_POST['entry_id'] ?? 0); check_admin_referer('elev8_approve_glass_entry_' . $id); Elev8_OS_Glass_Operations_Service::approve_entry($id, sanitize_key($_POST['status'] ?? 'pending')); wp_safe_redirect(self::url(['view' => 'payouts', 'notice' => 'Payout entry updated.'])); exit; }
    public static function save_glassblower_profile(): void { self::guard(); check_admin_referer('elev8_save_glassblower_profile'); $data = wp_unslash($_POST); $user = get_userdata(absint($data['user_id'] ?? 0)); if ($user instanceof WP_User) { $user->add_role(Elev8_OS_Access_Service::ROLE_GLASS_BLOWER); Elev8_OS_Production_Catalog_Service::save_compensation_profile($data); $notice = 'Glassblower roster updated.'; } else { $notice = 'User not found.'; } wp_safe_redirect(self::url(['view' => 'team', 'notice' => $notice])); exit; }
    public static function ajax_move_job(): void {
        self::guard();
        check_ajax_referer('elev8_glass_board', 'nonce');
        $job_id = absint($_POST['job_id'] ?? 0);
        $status = sanitize_key($_POST['status'] ?? '');
        $assigned = absint($_POST['assigned_user_id'] ?? 0);
        $result = Elev8_OS_Glass_Operations_Service::move_board_job($job_id, $status, $assigned);
        if (is_wp_error($result)) { wp_send_json_error(['message' => $result->get_error_message()], 400); }
        wp_send_json_success(['message' => 'Production job updated.']);
    }

    public static function import_orders(): void { self::guard(); check_admin_referer('elev8_import_cremation_orders'); $n = Elev8_OS_Glass_Operations_Service::import_woocommerce_cremation_orders(); wp_safe_redirect(self::url(['notice' => sprintf('%d new cremation order(s) imported.', $n)])); exit; }
}

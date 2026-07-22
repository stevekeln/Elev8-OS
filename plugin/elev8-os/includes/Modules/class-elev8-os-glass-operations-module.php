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
        add_action('admin_post_elev8_os_save_fast_pay_sheet', [__CLASS__, 'save_fast_pay_sheet']);
        add_action('admin_post_elev8_os_copy_previous_pay_day', [__CLASS__, 'copy_previous_pay_day']);
        add_action('wp_ajax_elev8_os_quick_create_pay_item', [__CLASS__, 'ajax_quick_create_pay_item']);
        add_action('wp_ajax_elev8_os_toggle_pay_favorite', [__CLASS__, 'ajax_toggle_pay_favorite']);
        add_action('admin_post_elev8_os_save_glassblower_profile', [__CLASS__, 'save_glassblower_profile']);
        add_action('admin_post_elev8_os_import_cremation_orders', [__CLASS__, 'import_orders']);
        add_action('admin_post_elev8_os_save_glass_case', [__CLASS__, 'save_case']);
        add_action('admin_post_elev8_os_add_custody_event', [__CLASS__, 'add_custody_event']);
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
        if (sanitize_key($_GET['view'] ?? '') === 'payouts') {
            wp_enqueue_script('elev8-glass-fast-pay', ELEV8_OS_URL . 'assets/js/glass-fast-pay.js', [], ELEV8_OS_VERSION, true);
            wp_localize_script('elev8-glass-fast-pay', 'Elev8GlassFastPay', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'createNonce' => wp_create_nonce('elev8_quick_create_pay_item'),
                'favoriteNonce' => wp_create_nonce('elev8_toggle_pay_favorite'),
                'products' => Elev8_OS_Production_Catalog_Service::pay_search('', 100),
                'hourlyRates' => array_reduce(Elev8_OS_Glass_Operations_Service::glass_workers(), static function($carry,$user){$p=Elev8_OS_Production_Catalog_Service::compensation_profile((int)$user->ID);$carry[$user->ID]=(float)($p['hourly_rate']??0);return $carry;}, []),
            ]);
        }
    }

    private static function can_manage(): bool { return Elev8_OS_Access_Service::user_can('view_glass_dashboard'); }
    private static function guard(): void { if (!self::can_manage()) { wp_die('You do not have access to Glass Operations.'); } }
    private static function url(array $args = []): string {
        if (class_exists('Elev8_OS_Glass_Manager_Suite_Module') && (!empty($_GET['elev8_glass_suite']) || strpos((string) wp_get_referer(), '/glass-manager/') !== false)) {
            return Elev8_OS_Glass_Manager_Suite_Module::url(array_merge(['suite_tool' => 'operations'], $args));
        }
        return add_query_arg(array_merge(['page' => self::SLUG], $args), admin_url('admin.php'));
    }

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
                    <a class="button <?php echo $view === 'repair-intake' ? 'button-primary' : ''; ?>" href="<?php echo esc_url(self::url(['view' => 'repair-intake'])); ?>">Repair Intake</a>
                    <a class="button <?php echo $view === 'memorial-intake' ? 'button-primary' : ''; ?>" href="<?php echo esc_url(self::url(['view' => 'memorial-intake'])); ?>">Memorial Intake</a>
                </nav>
            </header>
            <?php if (!empty($_GET['notice'])) : ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html(wp_unslash($_GET['notice'])); ?></p></div><?php endif; ?>
            <?php
            if ($view === 'board') { self::production_board($workers); }
            elseif ($view === 'new-job') { self::job_form($workers, $products); }
            elseif ($view === 'payouts') { self::payouts($workers); }
            elseif ($view === 'team') { self::team($workers); }
            elseif ($view === 'repair-intake') { self::case_intake('repair', $workers, $products); }
            elseif ($view === 'memorial-intake') { self::case_intake('memorial', $workers, $products); }
            elseif ($view === 'job') { self::job_detail(absint($_GET['job_id'] ?? 0), $workers, $products); }
            else { self::dashboard($summary); }
            ?>
        </div>
        <?php
    }

    private static function dashboard(array $s): void {
        $brief = class_exists('Elev8_OS_Glass_Manager_Brief_Service')
            ? Elev8_OS_Glass_Manager_Brief_Service::build()
            : ['pulse' => 'healthy', 'metrics' => $s, 'attention' => [], 'workload' => [], 'recent_jobs' => Elev8_OS_Glass_Operations_Service::jobs(['limit' => 8]), 'closeout' => []];
        $m = $brief['metrics'];
        $pulse_labels = [
            'healthy' => ['Healthy', 'Production is moving without verified critical blockers.'],
            'needs_attention' => ['Needs Attention', 'Several production items should be reviewed today.'],
            'action_required' => ['Action Required', 'Urgent, overdue, QC or assignment issues require manager action.'],
        ];
        $pulse = $pulse_labels[$brief['pulse']] ?? $pulse_labels['needs_attention'];
        ?>
        <section class="elev8-glass-brief pulse-<?php echo esc_attr($brief['pulse']); ?>">
            <div>
                <p class="eyebrow">Glass Manager Operational Home</p>
                <h2>Today's mission: keep production moving, protect quality and approve accurate pay.</h2>
                <p>Start with the attention queue, balance the blower team, then complete the studio closeout.</p>
            </div>
            <aside>
                <span>Studio pulse</span>
                <strong><?php echo esc_html($pulse[0]); ?></strong>
                <small><?php echo esc_html($pulse[1]); ?></small>
                <details><summary>Why?</summary><p>This status is rule-based from overdue and urgent jobs, unassigned ready work, QC/rework, and pending pay approvals. No production data is guessed.</p></details>
            </aside>
        </section>

        <section class="elev8-glass__kpis elev8-glass__kpis--manager">
            <article><strong><?php echo absint($m['open_jobs']); ?></strong><span>Open jobs</span><small><?php echo absint($m['in_production']); ?> currently in production</small></article>
            <article class="<?php echo $m['overdue'] ? 'is-danger' : ''; ?>"><strong><?php echo absint($m['overdue']); ?></strong><span>Overdue</span><small><?php echo absint($m['due_today']); ?> due today</small></article>
            <article class="<?php echo $m['unassigned'] ? 'is-warning' : ''; ?>"><strong><?php echo absint($m['unassigned']); ?></strong><span>Unassigned jobs</span><small>Ready work needing a blower</small></article>
            <article class="<?php echo ($m['qc_lines'] + $m['rework_lines']) ? 'is-warning' : ''; ?>"><strong><?php echo absint($m['qc_lines'] + $m['rework_lines']); ?></strong><span>QC / rework</span><small><?php echo absint($m['rework_lines']); ?> specifically require rework</small></article>
            <article class="<?php echo $m['pending_payout_count'] ? 'is-warning' : ''; ?>"><strong>$<?php echo number_format_i18n((float)$m['pending_payout_total'], 2); ?></strong><span>Pay awaiting review</span><small><?php echo absint($m['pending_payout_count']); ?> entries</small></article>
            <article><strong><?php echo absint($m['ready_to_finish']); ?></strong><span>Ready to finish</span><small>Pickup or shipping</small></article>
        </section>

        <div class="elev8-glass-manager-grid">
            <section class="panel elev8-glass-attention">
                <div class="panel-head"><div><h2>Needs Your Attention</h2><p>The highest-priority verified blockers across production, QC and pay.</p></div><a class="button button-primary" href="<?php echo esc_url(self::url(['view' => 'board'])); ?>">Open Production Board</a></div>
                <?php if (empty($brief['attention'])) : ?>
                    <div class="elev8-glass-all-clear"><strong>All clear.</strong><span>No verified production blockers need manager action right now.</span></div>
                <?php else : ?>
                    <div class="elev8-glass-attention-list">
                        <?php foreach ($brief['attention'] as $item) :
                            if ($item['kind'] === 'job') { $href = self::url(['view' => 'job', 'job_id' => $item['job_id']]); }
                            elseif ($item['kind'] === 'payouts') { $href = self::url(['view' => 'payouts']); }
                            else { $href = self::url(['view' => 'board']); }
                            ?>
                            <article class="severity-<?php echo esc_attr($item['severity']); ?>">
                                <div><span><?php echo esc_html(ucfirst($item['severity'])); ?></span><h3><?php echo esc_html($item['title']); ?></h3><p><?php echo esc_html($item['detail']); ?></p></div>
                                <a class="button" href="<?php echo esc_url($href); ?>"><?php echo esc_html($item['action']); ?></a>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <aside class="panel elev8-glass-quick-actions">
                <h2>Quick Actions</h2>
                <a class="button button-primary" href="<?php echo esc_url(self::url(['view' => 'new-job'])); ?>">Create Production Job</a>
                <a class="button" href="<?php echo esc_url(self::url(['view' => 'board'])); ?>">Assign & Move Jobs</a>
                <a class="button" href="<?php echo esc_url(self::url(['view' => 'payouts'])); ?>">Review Pay Sheets</a>
                <a class="button" href="<?php echo esc_url(self::url(['view' => 'team'])); ?>">Manage Glassblower Team</a>
                <?php if (class_exists('Elev8_OS_Production_Catalog_Module')) : ?><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=elev8-production-catalog')); ?>">Production Catalog</a><?php endif; ?>
                <hr>
                <h3>Cremation Intake</h3>
                <p>Bring new trusted cremation orders into the studio queue.</p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('elev8_import_cremation_orders'); ?><input type="hidden" name="action" value="elev8_os_import_cremation_orders"><button class="button">Import New Orders</button></form>
            </aside>
        </div>

        <div class="elev8-glass-manager-grid elev8-glass-manager-grid--equal">
            <section class="panel">
                <div class="panel-head"><div><h2>Glassblower Workload</h2><p>Open production assigned to each active roster member.</p></div><a class="button" href="<?php echo esc_url(self::url(['view' => 'team'])); ?>">Team</a></div>
                <div class="elev8-manager-workload">
                    <?php foreach ($brief['workload'] as $row) : ?>
                        <article class="<?php echo $row['overdue'] ? 'has-risk' : ''; ?>">
                            <strong><?php echo esc_html($row['label']); ?></strong>
                            <span><?php echo absint($row['open']); ?> open</span>
                            <small><?php if ($row['overdue']) { echo absint($row['overdue']) . ' overdue'; } elseif ($row['due_today']) { echo absint($row['due_today']) . ' due today'; } else { echo 'On track'; } ?></small>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="panel elev8-glass-closeout">
                <div class="panel-head"><div><h2>Before You Leave</h2><p>A live closeout checklist based on current studio data.</p></div></div>
                <ul>
                    <?php foreach ($brief['closeout'] as $item) : $href = self::url(['view' => $item['kind'] === 'payouts' ? 'payouts' : 'board']); ?>
                        <li class="<?php echo $item['complete'] ? 'is-complete' : 'is-open'; ?>"><span aria-hidden="true"><?php echo $item['complete'] ? '✓' : '!'; ?></span><a href="<?php echo esc_url($href); ?>"><?php echo esc_html($item['label']); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </section>
        </div>

        <section class="panel panel-wide elev8-glass-recent">
            <div class="panel-head"><div><h2>Recent Production Queue</h2><p>Open the source job for full instructions, production lines, QC and pay history.</p></div><a class="button" href="<?php echo esc_url(self::url(['view' => 'board'])); ?>">View All Jobs</a></div>
            <?php self::jobs_table($brief['recent_jobs']); ?>
        </section>
        <?php
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
                <?php if (!empty($_GET['elev8_glass_suite'])): ?><input type="hidden" name="suite_tool" value="operations"><input type="hidden" name="view" value="board"><?php else: ?><input type="hidden" name="page" value="<?php echo esc_attr(self::SLUG); ?>"><input type="hidden" name="view" value="board"><?php endif; ?>
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
        <label>Job type<select name="job_type"><option value="production">Production</option><option value="repair">Repair</option><option value="memorial">Memorial / cremation</option></select></label><label>Order number<input name="order_number"></label>
        <label>Production product<select name="production_product_id"><option value="0">Choose catalog product</option><?php foreach ($products as $p) : ?><option value="<?php echo absint($p['id']); ?>"><?php echo esc_html($p['product_name'] . ' · ' . ucwords($p['compensation_method'])); ?></option><?php endforeach; ?></select></label>
        <label class="span-2">Product or work requested<input name="product_name" required></label><label>Quantity<input type="number" min="1" step="0.01" name="quantity" value="1"></label><label>Priority<select name="priority"><option>normal</option><option>high</option><option>urgent</option><option>low</option></select></label><label>Due date<input type="date" name="due_date"></label><label>Assign blower<select name="assigned_user_id"><option value="0">Unassigned</option><?php foreach ($workers as $u) : ?><option value="<?php echo absint($u->ID); ?>"><?php echo esc_html($u->display_name); ?></option><?php endforeach; ?></select></label><label>Customer name<input name="customer_name"></label><label>Memorial name<input name="memorial_name"></label><label>Customer email<input type="email" name="customer_email"></label><label>Customer phone<input name="customer_phone"></label><label class="span-2">Colors / design<input name="colors"></label><label class="span-2">Engraving<input name="engraving"></label><label>Ashes status<select name="ashes_status"><option value="not_applicable">Not applicable</option><option value="waiting">Waiting for ashes</option><option value="received">Received</option><option value="returned">Returned</option></select></label><label>Status<select name="status"><?php foreach (['new','waiting_customer_info','waiting_ashes','ready_for_production','assigned','in_production','quality_control','ready_for_pickup','ready_to_ship','completed'] as $st) : ?><option value="<?php echo esc_attr($st); ?>"><?php echo esc_html(ucwords(str_replace('_',' ',$st))); ?></option><?php endforeach; ?></select></label><label class="span-2">Return / shipping instructions<textarea name="return_instructions" rows="3"></textarea></label><label class="span-2">Special notes<textarea name="special_notes" rows="4"></textarea></label><div class="span-2"><button class="button button-primary button-large">Create production job</button></div></form></section><?php
    }

    private static function job_detail(int $id, array $workers, array $products): void {
        $j = Elev8_OS_Glass_Operations_Service::job($id); if (!$j) { echo '<div class="notice notice-error"><p>Job not found.</p></div>'; return; }
        $lines = Elev8_OS_Glass_Operations_Service::job_lines($id);
        ?><p><a href="<?php echo esc_url(self::url()); ?>">← Back to queue</a></p><div class="elev8-glass__grid"><section class="panel panel-wide"><p class="eyebrow"><?php echo esc_html(ucfirst($j['job_type']) . ($j['order_number'] ? ' · Order #' . $j['order_number'] : '')); ?></p><h2><?php echo esc_html($j['product_name']); ?></h2><div class="detail-grid"><?php foreach (['customer_name'=>'Customer','customer_email'=>'Email','customer_phone'=>'Phone','memorial_name'=>'Memorial name','quantity'=>'Quantity','colors'=>'Colors / design','engraving'=>'Engraving','ashes_status'=>'Ashes','return_instructions'=>'Return instructions','special_notes'=>'Special notes'] as $k=>$label) : ?><div><small><?php echo esc_html($label); ?></small><strong><?php echo $j[$k] !== '' ? nl2br(esc_html(ucwords(str_replace('_',' ',(string)$j[$k])))) : 'Unavailable'; ?></strong></div><?php endforeach; ?></div></section><aside class="panel"><h2>Manager controls</h2><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('elev8_update_glass_job_' . $id); ?><input type="hidden" name="action" value="elev8_os_update_glass_job"><input type="hidden" name="job_id" value="<?php echo absint($id); ?>"><label>Status<select name="status"><?php foreach (['new','waiting_customer_info','waiting_ashes','ready_for_production','assigned','in_production','quality_control','ready_for_pickup','ready_to_ship','completed','cancelled'] as $st) : ?><option value="<?php echo esc_attr($st); ?>" <?php selected($j['status'],$st); ?>><?php echo esc_html(ucwords(str_replace('_',' ',$st))); ?></option><?php endforeach; ?></select></label><label>Assigned blower<select name="assigned_user_id"><option value="0">Unassigned</option><?php foreach ($workers as $u) : ?><option value="<?php echo absint($u->ID); ?>" <?php selected($j['assigned_user_id'],$u->ID); ?>><?php echo esc_html($u->display_name); ?></option><?php endforeach; ?></select></label><label>Due date<input type="date" name="due_date" value="<?php echo esc_attr($j['due_date']); ?>"></label><button class="button button-primary">Update job</button></form></aside></div>
        <section class="panel"><div class="panel-head"><div><h2>Production lines</h2><p>Each line preserves the catalog version, payout rule and material-cost snapshot used when it was added.</p></div></div><?php self::lines_table($lines, $j); ?><hr><h3>Add production line</h3><form class="form-grid compact" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('elev8_save_glass_job_line_' . $id); ?><input type="hidden" name="action" value="elev8_os_save_glass_job_line"><input type="hidden" name="job_id" value="<?php echo absint($id); ?>"><label class="span-2">Production product<select name="production_product_id" required><option value="">Choose product</option><?php foreach ($products as $p) : ?><option value="<?php echo absint($p['id']); ?>"><?php echo esc_html($p['product_name'] . ' · ' . ucwords($p['compensation_method'])); ?></option><?php endforeach; ?></select></label><label>Quantity<input type="number" min="0.01" step="0.01" name="quantity" value="1"></label><div><button class="button button-primary">Add line</button></div></form></section>
        <?php self::case_workspace($j); ?>
        <section class="panel"><h2>Record blower work</h2><?php self::entry_form($workers, $j, $lines); ?></section><?php
    }

    private static function lines_table(array $lines, array $job): void {
        if (!$lines) { echo '<div class="empty">No production lines have been added.</div>'; return; }
        ?><div class="table-wrap"><table><thead><tr><th>Product</th><th>Pay method</th><th>Planned</th><th>Completed</th><th>Actual time</th><th>QC</th><th>Approval</th></tr></thead><tbody><?php foreach ($lines as $line) : ?><tr><td><strong><?php echo esc_html($line['item_name']); ?></strong><small>Catalog v<?php echo absint($line['product_version']); ?> · Material $<?php echo number_format_i18n((float)$line['material_cost'],2); ?></small></td><td><?php echo esc_html(ucwords($line['compensation_method'])); ?><?php if ((float)$line['piecework_rate'] > 0) : ?><small>$<?php echo number_format_i18n((float)$line['piecework_rate'],2); ?> / <?php echo esc_html($line['piecework_unit']); ?></small><?php endif; ?></td><td><?php echo esc_html($line['quantity']); ?></td><td><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('elev8_update_glass_job_line_' . $line['id']); ?><input type="hidden" name="action" value="elev8_os_update_glass_job_line"><input type="hidden" name="line_id" value="<?php echo absint($line['id']); ?>"><input type="hidden" name="job_id" value="<?php echo absint($job['id']); ?>"><input class="small-text" type="number" step="0.01" name="quantity_completed" value="<?php echo esc_attr($line['quantity_completed']); ?>"></td><td><input class="small-text" type="number" step="1" name="actual_minutes" value="<?php echo esc_attr($line['actual_minutes']); ?>"> min</td><td><select name="qc_status"><option value="not_reviewed" <?php selected($line['qc_status'],'not_reviewed'); ?>>Not reviewed</option><option value="passed" <?php selected($line['qc_status'],'passed'); ?>>Passed</option><option value="rework" <?php selected($line['qc_status'],'rework'); ?>>Rework</option><option value="rejected" <?php selected($line['qc_status'],'rejected'); ?>>Rejected</option></select></td><td><label><input type="checkbox" name="manager_approved" value="1" <?php checked($line['manager_approved'],1); ?>> Manager</label><label><input type="checkbox" name="payroll_approved" value="1" <?php checked($line['payroll_approved'],1); ?>> Payroll</label><button class="button button-small">Save</button></form></td></tr><?php endforeach; ?></tbody></table></div><?php
    }

    private static function payouts(array $workers): void {
        $blower_id=absint($_GET['blower_user_id']??0);
        $work_date=sanitize_text_field($_GET['work_date']??current_time('Y-m-d'));
        $entries=Elev8_OS_Glass_Operations_Service::entries(array_filter(['blower_user_id'=>$blower_id,'work_date'=>$blower_id?$work_date:'']));
        $favorites=array_map('absint',(array)get_user_meta(get_current_user_id(),'elev8_glass_pay_favorites',true));
        $recent_ids=Elev8_OS_Glass_Operations_Service::recent_product_ids($blower_id,8);
        $lookup=[]; foreach(Elev8_OS_Production_Catalog_Service::pay_search('',100) as $p){$lookup[(int)$p['id']]=$p;}
        $day_total=0; foreach($entries as $e){$day_total+=(float)$e['total'];}
        ?>
        <section class="elev8-fast-pay" data-fast-pay>
            <div class="panel-head"><div><p class="eyebrow">Fast Glass Pay Entry</p><h2>Type the work, enter quantity or time, and keep moving.</h2><p>The Production Catalog remains the trusted payout source. New pay items can be created without leaving this screen.</p></div><button type="button" class="button" onclick="window.print()">Print Pay Sheet</button></div>
            <form class="elev8-fast-pay-filter" method="get"><?php if (!empty($_GET['elev8_glass_suite'])): ?><input type="hidden" name="suite_tool" value="operations"><input type="hidden" name="view" value="payouts"><?php else: ?><input type="hidden" name="page" value="<?php echo esc_attr(self::SLUG); ?>"><input type="hidden" name="view" value="payouts"><?php endif; ?><label>Glassblower<select name="blower_user_id" required><option value="">Choose blower</option><?php foreach($workers as $u):?><option value="<?php echo absint($u->ID);?>" <?php selected($blower_id,$u->ID);?>><?php echo esc_html($u->display_name);?></option><?php endforeach;?></select></label><label>Work date<input type="date" name="work_date" value="<?php echo esc_attr($work_date);?>"></label><button class="button button-primary">Open Daily Sheet</button></form>
            <?php if($blower_id): $user=get_userdata($blower_id); ?>
            <div class="elev8-fast-pay-shortcuts">
                <div><strong>★ Favorites</strong><div class="elev8-pay-chips"><?php foreach($favorites as $id){if(isset($lookup[$id])){echo '<button type="button" class="elev8-pay-chip" data-product-id="'.absint($id).'">'.esc_html($lookup[$id]['name']).'</button>';}} if(!$favorites)echo '<span>No favorites yet. Star an item after adding it.</span>';?></div></div>
                <div><strong>Recently used</strong><div class="elev8-pay-chips"><?php foreach($recent_ids as $id){if(isset($lookup[$id])){echo '<button type="button" class="elev8-pay-chip" data-product-id="'.absint($id).'">'.esc_html($lookup[$id]['name']).'</button>';}} if(!$recent_ids)echo '<span>No recent items yet.</span>';?></div></div>
                <details class="elev8-pay-advanced"><summary>Advanced tools</summary><p>Copy Previous Day is preserved for future repetitive production workflows. It is not the recommended glass-team entry method.</p><form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><?php wp_nonce_field('elev8_copy_previous_pay_day');?><input type="hidden" name="action" value="elev8_os_copy_previous_pay_day"><input type="hidden" name="blower_user_id" value="<?php echo absint($blower_id);?>"><input type="hidden" name="work_date" value="<?php echo esc_attr($work_date);?>"><button class="button">Copy Previous Day</button></form></details>
            </div>
            <form class="elev8-fast-pay-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><?php wp_nonce_field('elev8_save_fast_pay_sheet');?><input type="hidden" name="action" value="elev8_os_save_fast_pay_sheet"><input type="hidden" name="blower_user_id" value="<?php echo absint($blower_id);?>"><input type="hidden" name="work_date" value="<?php echo esc_attr($work_date);?>">
                <div class="elev8-pay-search-wrap"><label for="elev8-pay-search">Add work</label><input id="elev8-pay-search" type="search" autocomplete="off" placeholder="Start typing: mushroom, knob, repair..."><div class="elev8-pay-suggestions" hidden></div><button type="button" class="button" data-open-quick-item>+ Create New Pay Item</button></div>
                <div class="elev8-pay-lines" data-pay-lines><div class="empty">Start typing above to add the first item.</div></div>
                <div class="elev8-pay-total"><span>Daily tracked pay</span><strong data-sheet-total>$0.00</strong></div>
                <div class="elev8-pay-actions"><button class="button" name="sheet_action" value="draft">Save Draft</button><button class="button button-primary" name="sheet_action" value="pending">Submit for Approval</button></div>
            </form>
            <dialog class="elev8-quick-pay-dialog" data-quick-item-dialog><form method="dialog"><button class="elev8-dialog-close" aria-label="Close">×</button></form><h3>Create New Pay Item</h3><p>This item can be used immediately for pay. Material and full cost details can be completed later in Production Catalog.</p><div class="form-grid compact"><label class="span-2">Item name<input data-quick-name></label><label>Pay method<select data-quick-method><option value="piecework">Piecework</option><option value="hourly">Hourly</option><option value="either">Either</option></select></label><label>Piecework payout<input type="number" min="0" step="0.01" data-quick-rate></label><label>Paid per<select data-quick-unit><option value="piece">Piece</option><option value="pair">Pair</option><option value="set">Set</option><option value="batch">Batch</option><option value="job">Job</option></select></label><fieldset class="quick-duration"><legend>Estimated time</legend><label>Minutes<input type="number" min="0" step="1" value="0" data-quick-minutes></label><label>Seconds<input type="number" min="0" max="59" step="1" value="0" data-quick-seconds></label></fieldset><label class="span-2">Category<input data-quick-category value="Quick Pay Items"></label><div class="span-2"><button type="button" class="button button-primary" data-save-quick-item>Save and Add</button><span data-quick-message></span></div></div></dialog>
            <section class="panel elev8-daily-pay-sheet"><div class="panel-head"><div><h2><?php echo esc_html(($user?$user->display_name:'Glassblower').' — '.wp_date('l, F j, Y',strtotime($work_date)));?></h2><p>Draft, pending, approved and rejected entries for this day.</p></div><strong>$<?php echo number_format_i18n($day_total,2);?></strong></div>
                <?php if(!$entries):?><div class="empty">No work is recorded for this daily sheet yet.</div><?php else:?><div class="table-wrap"><table><thead><tr><th>Item</th><th>Qty / Time</th><th>Rate</th><th>Pay</th><th>Status</th><th></th></tr></thead><tbody><?php foreach($entries as $e):?><tr><td><?php echo esc_html($e['item_name']);?></td><td><?php echo esc_html($e['pay_method']==='hourly'?$e['minutes'].' min':$e['quantity']);?></td><td><?php echo esc_html($e['pay_method']==='hourly'?'$'.number_format_i18n((float)$e['rate'],2).'/hr':'$'.number_format_i18n((float)$e['rate'],2));?></td><td><strong>$<?php echo number_format_i18n((float)$e['total'],2);?></strong></td><td><?php echo esc_html(ucfirst($e['approval_status']));?></td><td><?php if($e['approval_status']==='pending'):?><form class="inline" method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><?php wp_nonce_field('elev8_approve_glass_entry_'.$e['id']);?><input type="hidden" name="action" value="elev8_os_approve_glass_entry"><input type="hidden" name="entry_id" value="<?php echo absint($e['id']);?>"><input type="hidden" name="return_blower_user_id" value="<?php echo absint($blower_id);?>"><input type="hidden" name="return_work_date" value="<?php echo esc_attr($work_date);?>"><button name="status" value="approved" class="button button-small">Approve</button><button name="status" value="rejected" class="button button-small">Reject</button></form><?php endif;?></td></tr><?php endforeach;?></tbody></table></div><?php endif;?>
            </section>
            <?php else:?><div class="panel empty">Choose a glassblower and date to open a fast daily pay sheet.</div><?php endif;?>
        </section><?php
    }

    private static function entry_form(array $workers, ?array $job, array $lines): void {
        ?><form class="form-grid compact" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('elev8_save_glass_entry'); ?><input type="hidden" name="action" value="elev8_os_save_glass_entry"><input type="hidden" name="job_id" value="<?php echo absint($job['id'] ?? 0); ?>"><label>Blower<select name="blower_user_id" required><option value="">Choose blower</option><?php foreach ($workers as $u) : ?><option value="<?php echo absint($u->ID); ?>" <?php selected(absint($job['assigned_user_id'] ?? 0),$u->ID); ?>><?php echo esc_html($u->display_name); ?></option><?php endforeach; ?></select></label><label>Work date<input type="date" name="work_date" value="<?php echo esc_attr(current_time('Y-m-d')); ?>"></label>
        <?php if ($lines) : ?><label class="span-2">Production line<select name="job_line_id"><option value="0">Choose line</option><?php foreach ($lines as $line) : ?><option value="<?php echo absint($line['id']); ?>"><?php echo esc_html($line['item_name'] . ' · ' . ucwords($line['compensation_method'])); ?></option><?php endforeach; ?></select></label><?php endif; ?>
        <label class="span-2">Item / work completed<input name="item_name" value="<?php echo esc_attr($job['product_name'] ?? ''); ?>" required></label><label>Pay method<select name="pay_method"><option value="piece_rate">Piece rate</option><option value="hourly">Hourly</option></select></label><label>Quantity<input type="number" step="0.01" name="quantity" value="1"></label><label>Rate ($)<input type="number" step="0.01" name="rate" placeholder="Uses profile/catalog when blank"></label><label>Minutes (hourly only)<input type="number" step="1" name="minutes" value="0"></label><label>Bonus ($)<input type="number" step="0.01" name="bonus" value="0"></label><label>Adjustment ($)<input type="number" step="0.01" name="adjustment" value="0"></label><label class="span-2">Notes<textarea name="notes" rows="2"></textarea></label><div class="span-2"><button class="button button-primary">Add to payout review</button></div></form><?php
    }

    private static function case_intake(string $type, array $workers, array $products): void {
        $is_memorial = $type === 'memorial';
        ?>
        <section class="panel elev8-case-intake">
            <p class="eyebrow"><?php echo $is_memorial ? 'High-trust memorial workflow' : 'Customer repair workflow'; ?></p>
            <h2><?php echo $is_memorial ? 'Create Memorial Intake' : 'Create Repair Intake'; ?></h2>
            <p>This creates the Glass Operations job first. After creation, complete custody, quote, photos, production lines and QC from the job workspace.</p>
            <form class="form-grid" method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('elev8_save_glass_job'); ?>
                <input type="hidden" name="action" value="elev8_os_save_glass_job">
                <input type="hidden" name="job_type" value="<?php echo esc_attr($type); ?>">
                <input type="hidden" name="source" value="<?php echo $is_memorial ? 'cremation' : 'repair'; ?>">
                <input type="hidden" name="status" value="<?php echo $is_memorial ? 'waiting_ashes' : 'new'; ?>">
                <label>Order / intake number<input name="order_number"></label>
                <label>Priority<select name="priority"><option value="normal">Normal</option><option value="high">High</option><option value="urgent">Urgent</option><option value="low">Low</option></select></label>
                <label class="span-2">Product or piece description<input name="product_name" required></label>
                <label>Customer name<input name="customer_name" required></label>
                <label><?php echo $is_memorial ? 'Loved one’s name' : 'Customer phone'; ?><input name="<?php echo $is_memorial ? 'memorial_name' : 'customer_phone'; ?>"></label>
                <label>Customer email<input type="email" name="customer_email"></label>
                <?php if ($is_memorial) : ?><label>Customer phone<input name="customer_phone"></label><?php endif; ?>
                <label>Due date<input type="date" name="due_date"></label>
                <label>Assign glassblower<select name="assigned_user_id"><option value="0">Unassigned</option><?php foreach ($workers as $u) : ?><option value="<?php echo absint($u->ID); ?>"><?php echo esc_html($u->display_name); ?></option><?php endforeach; ?></select></label>
                <label class="span-2">Initial instructions / damage / personalization<textarea name="special_notes" rows="5"></textarea></label>
                <label class="span-2">Return or shipping instructions<textarea name="return_instructions" rows="3"></textarea></label>
                <div class="span-2"><button class="button button-primary button-large">Create <?php echo $is_memorial ? 'Memorial' : 'Repair'; ?> Job</button></div>
            </form>
        </section>
        <?php
    }

    private static function case_workspace(array $job): void {
        if (!class_exists('Elev8_OS_Repair_Memorial_Service') || !in_array($job['job_type'], ['repair','memorial','cremation'], true)) { return; }
        $type = in_array($job['job_type'], ['memorial','cremation'], true) ? 'memorial' : 'repair';
        $case = Elev8_OS_Repair_Memorial_Service::case_for_job((int)$job['id']);
        $case = $case ?: ['case_type'=>$type,'case_status'=>'received','receiving_location'=>'','received_at'=>'','received_by'=>0,'piece_description'=>'','damage_description'=>'','requested_work'=>'','repairability'=>'unknown','risk_notice'=>'','quote_amount'=>0,'quote_status'=>'not_required','approval_deadline'=>'','payment_status'=>'unknown','ashes_amount_received'=>0,'ashes_amount_used'=>0,'ashes_amount_returned'=>0,'ashes_unit'=>'teaspoon','ashes_estimated'=>1,'reconciliation_confirmed'=>0,'storage_location'=>'','container_description'=>'','final_recipient'=>'','release_method'=>'','intake_photo_ids'=>[]];
        $statuses = Elev8_OS_Repair_Memorial_Service::case_statuses($type);
        ?>
        <section class="panel elev8-case-workspace">
            <div class="panel-head"><div><p class="eyebrow"><?php echo $type === 'memorial' ? 'Memorial chain of custody' : 'Repair evaluation and approval'; ?></p><h2><?php echo $type === 'memorial' ? 'Memorial Case' : 'Repair Case'; ?></h2></div><span class="pill"><?php echo esc_html($statuses[$case['case_status']] ?? 'Received'); ?></span></div>
            <form class="form-grid" method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('elev8_save_glass_case_' . $job['id']); ?>
                <input type="hidden" name="action" value="elev8_os_save_glass_case"><input type="hidden" name="job_id" value="<?php echo absint($job['id']); ?>"><input type="hidden" name="case_type" value="<?php echo esc_attr($type); ?>">
                <label>Case status<select name="case_status"><?php foreach ($statuses as $key=>$label) : ?><option value="<?php echo esc_attr($key); ?>" <?php selected($case['case_status'],$key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
                <label>Receiving location<input name="receiving_location" value="<?php echo esc_attr($case['receiving_location']); ?>"></label>
                <label>Received date/time<input type="datetime-local" name="received_at" value="<?php echo esc_attr($case['received_at'] ? str_replace(' ','T',substr($case['received_at'],0,16)) : ''); ?>"></label>
                <label>Intake photos<input type="file" name="intake_photos[]" accept="image/*" multiple></label>
                <label class="span-2">Piece / container description<textarea name="piece_description" rows="3"><?php echo esc_textarea($case['piece_description']); ?></textarea></label>
                <?php if ($type === 'repair') : ?>
                    <label class="span-2">Damage description<textarea name="damage_description" rows="3"><?php echo esc_textarea($case['damage_description']); ?></textarea></label>
                    <label class="span-2">Requested / recommended repair<textarea name="requested_work" rows="3"><?php echo esc_textarea($case['requested_work']); ?></textarea></label>
                    <label>Repairability<select name="repairability"><option value="unknown" <?php selected($case['repairability'],'unknown'); ?>>Unknown</option><option value="yes" <?php selected($case['repairability'],'yes'); ?>>Repairable</option><option value="uncertain" <?php selected($case['repairability'],'uncertain'); ?>>Uncertain</option><option value="no" <?php selected($case['repairability'],'no'); ?>>Not repairable</option></select></label>
                    <label>Quote amount ($)<input type="number" step="0.01" name="quote_amount" value="<?php echo esc_attr($case['quote_amount']); ?>"></label>
                    <label>Quote / approval<select name="quote_status"><option value="not_required" <?php selected($case['quote_status'],'not_required'); ?>>Not required</option><option value="draft" <?php selected($case['quote_status'],'draft'); ?>>Draft</option><option value="ready" <?php selected($case['quote_status'],'ready'); ?>>Ready</option><option value="waiting_customer" <?php selected($case['quote_status'],'waiting_customer'); ?>>Waiting for customer</option><option value="approved" <?php selected($case['quote_status'],'approved'); ?>>Approved</option><option value="declined" <?php selected($case['quote_status'],'declined'); ?>>Declined</option></select></label>
                    <label>Approval deadline<input type="date" name="approval_deadline" value="<?php echo esc_attr($case['approval_deadline']); ?>"></label>
                    <label class="span-2">Breakage / repair risk notice<textarea name="risk_notice" rows="3"><?php echo esc_textarea($case['risk_notice']); ?></textarea></label>
                <?php else : ?>
                    <label class="span-2">Container description<textarea name="container_description" rows="3"><?php echo esc_textarea($case['container_description']); ?></textarea></label>
                    <label>Secure storage location<input name="storage_location" value="<?php echo esc_attr($case['storage_location']); ?>"></label>
                    <label>Unit<select name="ashes_unit"><option value="teaspoon" <?php selected($case['ashes_unit'],'teaspoon'); ?>>Teaspoon</option><option value="tablespoon" <?php selected($case['ashes_unit'],'tablespoon'); ?>>Tablespoon</option><option value="gram" <?php selected($case['ashes_unit'],'gram'); ?>>Gram</option><option value="estimated_portion" <?php selected($case['ashes_unit'],'estimated_portion'); ?>>Estimated portion</option></select></label>
                    <label>Amount received<input type="number" step="0.0001" name="ashes_amount_received" value="<?php echo esc_attr($case['ashes_amount_received']); ?>"></label>
                    <label>Amount used<input type="number" step="0.0001" name="ashes_amount_used" value="<?php echo esc_attr($case['ashes_amount_used']); ?>"></label>
                    <label>Amount returned<input type="number" step="0.0001" name="ashes_amount_returned" value="<?php echo esc_attr($case['ashes_amount_returned']); ?>"></label>
                    <label>Final recipient<input name="final_recipient" value="<?php echo esc_attr($case['final_recipient']); ?>"></label>
                    <label>Release method<select name="release_method"><option value="">Choose</option><option value="pickup" <?php selected($case['release_method'],'pickup'); ?>>Pickup</option><option value="shipping" <?php selected($case['release_method'],'shipping'); ?>>Shipping</option><option value="returned_with_order" <?php selected($case['release_method'],'returned_with_order'); ?>>Returned with order</option></select></label>
                    <label><input type="checkbox" name="ashes_estimated" value="1" <?php checked(!empty($case['ashes_estimated'])); ?>> Amounts are estimated</label>
                    <label><input type="checkbox" name="reconciliation_confirmed" value="1" <?php checked(!empty($case['reconciliation_confirmed'])); ?>> Reconciliation confirmed; no remains are left in production</label>
                <?php endif; ?>
                <label>Payment status<select name="payment_status"><option value="unknown" <?php selected($case['payment_status'],'unknown'); ?>>Unknown</option><option value="not_required" <?php selected($case['payment_status'],'not_required'); ?>>Not required</option><option value="deposit_paid" <?php selected($case['payment_status'],'deposit_paid'); ?>>Deposit paid</option><option value="paid" <?php selected($case['payment_status'],'paid'); ?>>Paid</option><option value="balance_due" <?php selected($case['payment_status'],'balance_due'); ?>>Balance due</option></select></label>
                <div class="span-2"><button class="button button-primary">Save Case Details</button></div>
            </form>
            <?php if (!empty($case['intake_photo_ids'])) : ?><div class="elev8-case-photos"><?php foreach ($case['intake_photo_ids'] as $photo_id) { echo wp_get_attachment_image($photo_id,'thumbnail'); } ?></div><?php endif; ?>
        </section>
        <?php if ($type === 'memorial') : self::custody_workspace($job, $case); endif; ?>
        <?php self::customer_updates($job, $case); ?>
        <?php
    }

    private static function custody_workspace(array $job, array $case): void {
        $events = Elev8_OS_Repair_Memorial_Service::custody_events((int)$job['id']);
        ?>
        <section class="panel elev8-custody"><div class="panel-head"><div><h2>Chain of Custody</h2><p>Permanent custody events cannot be edited or silently deleted.</p></div></div>
            <form class="form-grid compact" method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('elev8_add_custody_event_' . $job['id']); ?><input type="hidden" name="action" value="elev8_os_add_custody_event"><input type="hidden" name="job_id" value="<?php echo absint($job['id']); ?>">
                <label>Event type<select name="event_type"><option value="received">Ashes received</option><option value="verified">Intake verified</option><option value="stored">Stored securely</option><option value="assigned">Assigned to glassblower</option><option value="removed_for_production">Removed for production</option><option value="returned_to_storage">Returned to storage</option><option value="qc_passed">Quality control passed</option><option value="packaged">Packaged</option><option value="released">Released / shipped</option><option value="note">Other custody note</option></select></label>
                <label>Location<input name="event_location"></label><label class="span-2">Event label<input name="event_label" required></label><label class="span-2">Notes<textarea name="notes" rows="2"></textarea></label><label>Photo / document<input type="file" name="custody_attachment" accept="image/*,.pdf"></label><div><button class="button button-primary">Record Custody Event</button></div>
            </form>
            <?php if (!$events) : ?><div class="empty">No custody events recorded yet.</div><?php else : ?><div class="elev8-custody-timeline"><?php foreach ($events as $event) : $user=get_userdata((int)$event['created_by']); ?><article><strong><?php echo esc_html($event['event_label']); ?></strong><span><?php echo esc_html($event['created_at'] . ' · ' . ($user ? $user->display_name : 'Unknown user')); ?></span><p><?php echo esc_html($event['event_location']); ?><?php echo $event['notes'] ? ' — ' . esc_html($event['notes']) : ''; ?></p><?php if ($event['attachment_id']) : ?><a href="<?php echo esc_url(wp_get_attachment_url((int)$event['attachment_id'])); ?>" target="_blank" rel="noopener">Open attachment</a><?php endif; ?></article><?php endforeach; ?></div><?php endif; ?>
        </section>
        <?php
    }

    private static function customer_updates(array $job, array $case): void {
        $templates = Elev8_OS_Repair_Memorial_Service::templates($job, $case);
        ?><section class="panel elev8-customer-updates"><h2>Customer Update Templates</h2><p>Copy an approved status message. Sending automation can be connected later through Notifications.</p><div class="elev8-template-grid"><?php foreach ($templates as $key=>$template) : ?><article><h3><?php echo esc_html($template[0]); ?></h3><textarea readonly rows="5"><?php echo esc_textarea($template[1]); ?></textarea><button type="button" class="button" onclick="navigator.clipboard && navigator.clipboard.writeText(this.previousElementSibling.value)">Copy message</button></article><?php endforeach; ?></div></section><?php
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
    public static function approve_entry(): void { self::guard(); $id = absint($_POST['entry_id'] ?? 0); check_admin_referer('elev8_approve_glass_entry_' . $id); Elev8_OS_Glass_Operations_Service::approve_entry($id, sanitize_key($_POST['status'] ?? 'pending')); wp_safe_redirect(self::url(['view' => 'payouts', 'blower_user_id'=>absint($_POST['return_blower_user_id']??0), 'work_date'=>sanitize_text_field($_POST['return_work_date']??''), 'notice' => 'Payout entry updated.'])); exit; }
    public static function save_glassblower_profile(): void { self::guard(); check_admin_referer('elev8_save_glassblower_profile'); $data = wp_unslash($_POST); $user = get_userdata(absint($data['user_id'] ?? 0)); if ($user instanceof WP_User) { $user->add_role(Elev8_OS_Access_Service::ROLE_GLASS_BLOWER); Elev8_OS_Production_Catalog_Service::save_compensation_profile($data); $notice = 'Glassblower roster updated.'; } else { $notice = 'User not found.'; } wp_safe_redirect(self::url(['view' => 'team', 'notice' => $notice])); exit; }
    public static function save_fast_pay_sheet(): void {
        self::guard(); check_admin_referer('elev8_save_fast_pay_sheet'); $data=wp_unslash($_POST);
        $result=Elev8_OS_Glass_Operations_Service::save_fast_pay_sheet($data);
        $notice=is_wp_error($result)?$result->get_error_message():count($result).' pay item(s) saved.';
        wp_safe_redirect(self::url(['view'=>'payouts','blower_user_id'=>absint($data['blower_user_id']??0),'work_date'=>sanitize_text_field($data['work_date']??''),'notice'=>$notice])); exit;
    }
    public static function copy_previous_pay_day(): void {
        self::guard(); check_admin_referer('elev8_copy_previous_pay_day'); $blower=absint($_POST['blower_user_id']??0);$date=sanitize_text_field($_POST['work_date']??'');
        $result=Elev8_OS_Glass_Operations_Service::copy_previous_day($blower,$date);
        $notice=is_wp_error($result)?$result->get_error_message():$result.' item(s) copied as drafts.';
        wp_safe_redirect(self::url(['view'=>'payouts','blower_user_id'=>$blower,'work_date'=>$date,'notice'=>$notice])); exit;
    }
    public static function ajax_quick_create_pay_item(): void {
        self::guard(); check_ajax_referer('elev8_quick_create_pay_item','nonce');
        $result=Elev8_OS_Production_Catalog_Service::quick_create_pay_item(wp_unslash($_POST));
        if(is_wp_error($result)){wp_send_json_error(['message'=>$result->get_error_message()],400);}
        $product=Elev8_OS_Production_Catalog_Service::product((int)$result);
        wp_send_json_success(['product'=>Elev8_OS_Production_Catalog_Service::pay_search($product['product_name'],10)[0]??null,'message'=>'Pay item created. Cost details are incomplete until reviewed in Production Catalog.']);
    }
    public static function ajax_toggle_pay_favorite(): void {
        self::guard(); check_ajax_referer('elev8_toggle_pay_favorite','nonce'); $id=absint($_POST['product_id']??0);
        $ids=array_map('absint',(array)get_user_meta(get_current_user_id(),'elev8_glass_pay_favorites',true));
        if(in_array($id,$ids,true)){$ids=array_values(array_diff($ids,[$id]));$favorite=false;}else{$ids[]=$id;$ids=array_values(array_unique($ids));$favorite=true;}
        update_user_meta(get_current_user_id(),'elev8_glass_pay_favorites',$ids); wp_send_json_success(['favorite'=>$favorite]);
    }

    public static function save_case(): void {
        self::guard(); $job_id=absint($_POST['job_id']??0); check_admin_referer('elev8_save_glass_case_' . $job_id);
        $result=Elev8_OS_Repair_Memorial_Service::save_case($job_id, wp_unslash($_POST), $_FILES['intake_photos']??[]);
        wp_safe_redirect(self::url(['view'=>'job','job_id'=>$job_id,'notice'=>is_wp_error($result)?$result->get_error_message():'Case details saved.'])); exit;
    }
    public static function add_custody_event(): void {
        self::guard(); $job_id=absint($_POST['job_id']??0); check_admin_referer('elev8_add_custody_event_' . $job_id);
        $result=Elev8_OS_Repair_Memorial_Service::add_custody_event($job_id, wp_unslash($_POST), $_FILES['custody_attachment']??[]);
        wp_safe_redirect(self::url(['view'=>'job','job_id'=>$job_id,'notice'=>is_wp_error($result)?$result->get_error_message():'Custody event recorded.'])); exit;
    }

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

<?php
if (!defined('ABSPATH')) { exit; }

/** Admin and role-aware user interface for Elev8 OS work items. */
final class Elev8_OS_Work_Module {
    private const PAGE_MY = 'elev8-my-work';
    private const PAGE_TEAM = 'elev8-team-work';

    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'admin_menu'], 27);
        add_action('admin_enqueue_scripts', [__CLASS__, 'assets']);
        add_action('admin_post_elev8_os_create_work_item', [__CLASS__, 'create']);
        add_action('admin_post_elev8_os_update_work_item', [__CLASS__, 'update']);
        add_action('admin_post_elev8_os_generate_takeover_workflow', [__CLASS__, 'generate_takeover']);
    }

    public static function admin_menu(): void {
        if (Elev8_OS_Access_Service::user_can('view_work')) {
            add_submenu_page('elev8-os', __('My Work', 'elev8-os'), __('My Work', 'elev8-os'), 'read', self::PAGE_MY, [__CLASS__, 'render_my']);
        }
        if (Elev8_OS_Access_Service::user_can('manage_work')) {
            add_submenu_page('elev8-os', __('Team Work', 'elev8-os'), __('Team Work', 'elev8-os'), 'read', self::PAGE_TEAM, [__CLASS__, 'render_team']);
        }
    }

    public static function assets(string $hook): void {
        if (!in_array($hook, ['elev8-os_page_' . self::PAGE_MY, 'elev8-os_page_' . self::PAGE_TEAM], true)) { return; }
        wp_enqueue_style('elev8-os-work', ELEV8_OS_URL . 'assets/css/work-management.css', [], ELEV8_OS_VERSION);
    }

    public static function render_my(): void {
        if (!Elev8_OS_Access_Service::user_can('view_work')) { wp_die(esc_html__('You do not have permission to view work.', 'elev8-os')); }
        self::render(false);
    }

    public static function render_team(): void {
        if (!Elev8_OS_Access_Service::user_can('manage_work')) { wp_die(esc_html__('You do not have permission to manage team work.', 'elev8-os')); }
        self::render(true);
    }

    private static function render(bool $team): void {
        $current = get_current_user_id();
        $status = sanitize_key((string) ($_GET['status'] ?? 'active'));
        $meta = [];
        if (!$team) { $meta[] = ['key'=>'_elev8_work_owner_user_id','value'=>$current,'type'=>'NUMERIC']; }
        if ($status === 'active') { $meta[] = ['key'=>'_elev8_work_status','value'=>['new','assigned','in_progress','waiting'],'compare'=>'IN']; }
        elseif (isset(Elev8_OS_Work_Service::statuses()[$status])) { $meta[] = ['key'=>'_elev8_work_status','value'=>$status]; }
        $items = get_posts(['post_type'=>Elev8_OS_Work_Service::POST_TYPE,'post_status'=>'publish','posts_per_page'=>200,'orderby'=>'meta_value','meta_key'=>'_elev8_work_due_date','order'=>'ASC','meta_query'=>$meta]);
        $counts = Elev8_OS_Work_Service::counts($team ? 0 : $current);
        echo '<div class="wrap elev8-work"><header class="elev8-work-header"><div><p class="elev8-work-eyebrow">' . esc_html__('Elev8 OS', 'elev8-os') . '</p><h1>' . esc_html($team ? __('Team Work', 'elev8-os') : __('My Work', 'elev8-os')) . '</h1><p>' . esc_html__('The execution layer for assignments, follow-up, and business workflows.', 'elev8-os') . '</p></div></header>';
        echo '<section class="elev8-work-metrics">';
        foreach (['overdue'=>__('Overdue','elev8-os'),'due_today'=>__('Due Today','elev8-os'),'active'=>__('Active','elev8-os'),'waiting'=>__('Waiting','elev8-os')] as $key=>$label) { echo '<div><strong>' . (int) $counts[$key] . '</strong><span>' . esc_html($label) . '</span></div>'; }
        if ($team) { echo '<div><strong>' . (int) $counts['unassigned'] . '</strong><span>' . esc_html__('Unassigned', 'elev8-os') . '</span></div>'; }
        echo '</section><nav class="elev8-work-filters"><a href="' . esc_url(self::url($team, 'active')) . '">' . esc_html__('Active', 'elev8-os') . '</a>';
        foreach (Elev8_OS_Work_Service::statuses() as $key=>$label) { echo '<a href="' . esc_url(self::url($team, $key)) . '">' . esc_html($label) . '</a>'; }
        echo '</nav>';
        if ($team) { self::render_create_form(); }
        echo '<section class="elev8-work-list">';
        if (!$items) { echo '<div class="elev8-work-empty">' . esc_html__('No work items found.', 'elev8-os') . '</div>'; }
        foreach ($items as $item) { self::render_item($item, $team); }
        echo '</section></div>';
    }

    private static function render_create_form(): void {
        echo '<details class="elev8-work-create"><summary>' . esc_html__('Create Work Item', 'elev8-os') . '</summary><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('elev8_create_work_item');
        echo '<input type="hidden" name="action" value="elev8_os_create_work_item"><label>' . esc_html__('Title', 'elev8-os') . '<input name="title" required></label><label>' . esc_html__('Description', 'elev8-os') . '<textarea name="description" rows="3"></textarea></label>';
        self::owner_select(0);
        echo '<label>' . esc_html__('Due date', 'elev8-os') . '<input type="date" name="due_date"></label><label>' . esc_html__('Priority', 'elev8-os') . '<select name="priority">';
        foreach (Elev8_OS_Work_Service::priorities() as $key=>$label) { echo '<option value="' . esc_attr($key) . '">' . esc_html($label) . '</option>'; }
        echo '</select></label><button class="button button-primary">' . esc_html__('Create Work Item', 'elev8-os') . '</button></form></details>';
    }

    private static function render_item(WP_Post $item, bool $team): void {
        $status = (string) get_post_meta($item->ID, '_elev8_work_status', true) ?: 'new';
        $priority = (string) get_post_meta($item->ID, '_elev8_work_priority', true) ?: 'normal';
        $owner = absint(get_post_meta($item->ID, '_elev8_work_owner_user_id', true));
        $due = (string) get_post_meta($item->ID, '_elev8_work_due_date', true);
        $source_type = (string) get_post_meta($item->ID, '_elev8_work_source_type', true);
        $source_id = absint(get_post_meta($item->ID, '_elev8_work_source_id', true));
        $is_overdue = $due && $due < current_time('Y-m-d') && !in_array($status, ['completed','cancelled','archived'], true);
        echo '<article class="elev8-work-card' . ($is_overdue ? ' is-overdue' : '') . '"><header><div><span class="elev8-work-priority">' . esc_html(Elev8_OS_Work_Service::priorities()[$priority] ?? ucfirst($priority)) . '</span><h2>' . esc_html(get_the_title($item)) . '</h2></div><strong>' . esc_html(Elev8_OS_Work_Service::statuses()[$status] ?? ucfirst($status)) . '</strong></header>';
        if ($item->post_content) { echo '<div class="elev8-work-description">' . wp_kses_post(wpautop($item->post_content)) . '</div>'; }
        $owner_user = $owner ? get_userdata($owner) : false;
        $owner_name = $owner_user instanceof WP_User ? $owner_user->display_name : __('Unassigned', 'elev8-os');
        echo '<div class="elev8-work-meta"><span><b>' . esc_html__('Due', 'elev8-os') . ':</b> ' . esc_html($due ?: __('Unavailable', 'elev8-os')) . '</span><span><b>' . esc_html__('Owner', 'elev8-os') . ':</b> ' . esc_html($owner_name) . '</span>';
        if ($source_type && $source_id) { echo '<span><b>' . esc_html__('Source', 'elev8-os') . ':</b> ' . esc_html($source_type . ' #' . $source_id) . '</span>'; }
        echo '</div><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="elev8-work-update">';
        wp_nonce_field('elev8_update_work_item_' . $item->ID);
        echo '<input type="hidden" name="action" value="elev8_os_update_work_item"><input type="hidden" name="work_id" value="' . (int) $item->ID . '"><label>' . esc_html__('Status', 'elev8-os') . '<select name="status">';
        foreach (Elev8_OS_Work_Service::statuses() as $key=>$label) { echo '<option value="' . esc_attr($key) . '"' . selected($status,$key,false) . '>' . esc_html($label) . '</option>'; }
        echo '</select></label>';
        if ($team) { self::owner_select($owner); }
        echo '<label>' . esc_html__('Due date', 'elev8-os') . '<input type="date" name="due_date" value="' . esc_attr($due) . '"></label><label>' . esc_html__('Priority', 'elev8-os') . '<select name="priority">';
        foreach (Elev8_OS_Work_Service::priorities() as $key=>$label) { echo '<option value="' . esc_attr($key) . '"' . selected($priority,$key,false) . '>' . esc_html($label) . '</option>'; }
        echo '</select></label><label class="elev8-work-notes">' . esc_html__('Completion or progress notes', 'elev8-os') . '<textarea name="notes" rows="2">' . esc_textarea((string) get_post_meta($item->ID, '_elev8_work_notes', true)) . '</textarea></label><button class="button button-primary">' . esc_html__('Save Work', 'elev8-os') . '</button></form></article>';
    }

    private static function owner_select(int $selected): void {
        echo '<label>' . esc_html__('Owner', 'elev8-os') . '<select name="owner_user_id"><option value="0">' . esc_html__('Unassigned', 'elev8-os') . '</option>';
        foreach (Elev8_OS_Access_Service::assignment_users_grouped() as $group=>$users) { echo '<optgroup label="' . esc_attr($group) . '">'; foreach ($users as $user) { echo '<option value="' . (int) $user->ID . '"' . selected($selected,$user->ID,false) . '>' . esc_html($user->display_name) . '</option>'; } echo '</optgroup>'; }
        echo '</select></label>';
    }

    public static function create(): void {
        if (!Elev8_OS_Access_Service::user_can('manage_work')) { wp_die(esc_html__('Permission denied.', 'elev8-os')); }
        check_admin_referer('elev8_create_work_item');
        Elev8_OS_Work_Service::create([
            'title'=>wp_unslash($_POST['title'] ?? ''),'description'=>wp_unslash($_POST['description'] ?? ''),'owner_user_id'=>absint($_POST['owner_user_id'] ?? 0),
            'due_date'=>wp_unslash($_POST['due_date'] ?? ''),'priority'=>wp_unslash($_POST['priority'] ?? 'normal'),
        ]);
        wp_safe_redirect(self::team_url()); exit;
    }

    public static function update(): void {
        $id = absint($_POST['work_id'] ?? 0);
        check_admin_referer('elev8_update_work_item_' . $id);
        $owner = absint(get_post_meta($id, '_elev8_work_owner_user_id', true));
        $can_manage = Elev8_OS_Access_Service::user_can('manage_work');
        if (!$can_manage && (!Elev8_OS_Access_Service::user_can('view_work') || $owner !== get_current_user_id())) { wp_die(esc_html__('Permission denied.', 'elev8-os')); }
        $changes = ['status'=>wp_unslash($_POST['status'] ?? 'new'),'due_date'=>wp_unslash($_POST['due_date'] ?? ''),'priority'=>wp_unslash($_POST['priority'] ?? 'normal'),'notes'=>wp_unslash($_POST['notes'] ?? '')];
        if ($can_manage) { $changes['owner_user_id'] = absint($_POST['owner_user_id'] ?? 0); }
        Elev8_OS_Work_Service::update($id, $changes);
        wp_safe_redirect($can_manage ? self::team_url() : self::my_url()); exit;
    }

    public static function generate_takeover(): void {
        if (!Elev8_OS_Access_Service::user_can('manage_work')) { wp_die(esc_html__('Permission denied.', 'elev8-os')); }
        $id = absint($_POST['application_id'] ?? 0);
        check_admin_referer('elev8_generate_takeover_workflow_' . $id);
        Elev8_OS_Work_Service::generate_takeover_workflow($id);
        wp_safe_redirect(admin_url('admin.php?page=elev8-event-applications&workflow=generated')); exit;
    }

    public static function my_url(): string { return admin_url('admin.php?page=' . self::PAGE_MY); }
    public static function team_url(): string { return admin_url('admin.php?page=' . self::PAGE_TEAM); }
    private static function url(bool $team, string $status): string { return add_query_arg('status',$status,$team?self::team_url():self::my_url()); }
}

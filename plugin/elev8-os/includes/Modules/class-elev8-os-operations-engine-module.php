<?php
if (!defined('ABSPATH')) { exit; }

/** Frontend Operations Engine and universal Work Inbox. */
final class Elev8_OS_Operations_Engine_Module {
    private const OPTION_PAGE_ID = 'elev8_os_operations_engine_page_id';
    private const SLUG = 'operations';
    private const SHORTCODE = 'elev8_operations_engine';

    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'admin_menu'], 26);
        add_action('init', [__CLASS__, 'ensure_page'], 39);
        add_shortcode(self::SHORTCODE, [__CLASS__, 'shortcode']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'assets']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'admin_assets']);
        add_action('admin_post_elev8_os_operations_create', [__CLASS__, 'create']);
        add_action('admin_post_elev8_os_operations_update', [__CLASS__, 'update']);
        add_filter('elev8_os_application_shell_frontend', [__CLASS__, 'shell']);
        add_filter('elev8_os_command_palette_commands', [__CLASS__, 'command'], 24, 2);
    }

    public static function activate(): void { self::ensure_page(true); }

    public static function admin_menu(): void {
        if (Elev8_OS_Access_Service::user_can('view_operations')) {
            add_submenu_page('elev8-os', __('Operations Engine','elev8-os'), __('Operations','elev8-os'), 'read', 'elev8-operations-engine', [__CLASS__, 'render_admin']);
        }
    }

    public static function ensure_page(bool $force = false): void {
        $id = absint(get_option(self::OPTION_PAGE_ID));
        if ($id && get_post($id) instanceof WP_Post) { return; }
        $page = get_page_by_path(self::SLUG, OBJECT, 'page');
        if ($page instanceof WP_Post && $page->post_status !== 'trash') { update_option(self::OPTION_PAGE_ID, (int) $page->ID, false); return; }
        if (!$force && !current_user_can('manage_options')) { return; }
        $id = wp_insert_post(['post_title'=>__('Operations','elev8-os'),'post_name'=>self::SLUG,'post_content'=>'['.self::SHORTCODE.']','post_status'=>'publish','post_type'=>'page','post_author'=>get_current_user_id(),'comment_status'=>'closed'], true);
        if (!is_wp_error($id) && $id > 0) { update_option(self::OPTION_PAGE_ID, (int) $id, false); }
    }

    public static function url(array $args = []): string {
        $id = absint(get_option(self::OPTION_PAGE_ID));
        $base = $id ? (string) get_permalink($id) : home_url('/'.self::SLUG.'/');
        return $args ? add_query_arg($args, $base) : $base;
    }

    public static function shell(bool $show): bool { return $show || is_page(absint(get_option(self::OPTION_PAGE_ID))) || is_page(self::SLUG); }
    public static function assets(): void { if (self::shell(false)) { wp_enqueue_style('elev8-operations-engine', ELEV8_OS_URL.'assets/css/operations-engine.css', [], ELEV8_OS_VERSION); } }
    public static function admin_assets(string $hook): void { if ($hook === 'elev8-os_page_elev8-operations-engine') { wp_enqueue_style('elev8-operations-engine', ELEV8_OS_URL.'assets/css/operations-engine.css', [], ELEV8_OS_VERSION); } }

    public static function shortcode(): string {
        if (!is_user_logged_in()) { return '<p>'.esc_html__('Please sign in.','elev8-os').'</p>'; }
        $user = class_exists('Elev8_OS_Preview_Service') ? Elev8_OS_Preview_Service::effective_user() : wp_get_current_user();
        if (!$user instanceof WP_User || !Elev8_OS_Access_Service::user_can('view_operations', $user)) { return '<p>'.esc_html__('You do not have access to Operations.','elev8-os').'</p>'; }
        return self::content($user);
    }

    public static function render_admin(): void {
        $user = wp_get_current_user();
        if (!Elev8_OS_Access_Service::user_can('view_operations', $user)) { wp_die(esc_html__('Permission denied.','elev8-os')); }
        echo '<div class="wrap">'.self::content($user).'</div>';
    }

    private static function content(WP_User $user): string {
        $manage = Elev8_OS_Access_Service::user_can('manage_operations', $user) || Elev8_OS_Access_Service::user_can('manage_work', $user);
        $view = $manage && sanitize_key((string)($_GET['view'] ?? 'mine')) === 'team' ? 'team' : 'mine';
        $status = sanitize_key((string)($_GET['status'] ?? 'active'));
        $type = sanitize_key((string)($_GET['type'] ?? ''));
        $organization = absint($_GET['organization_unit_id'] ?? 0);
        $items = Elev8_OS_Operations_Engine_Service::inbox($user, compact('view','status','type') + ['organization_unit_id'=>$organization]);
        $metrics = Elev8_OS_Operations_Engine_Service::metrics($user, $view === 'team');
        $signals = Elev8_OS_Operations_Engine_Service::source_signals();
        $organizations = self::organization_options($user);
        ob_start(); ?>
        <main class="elev8-operations-engine">
            <?php if(!empty($_GET['error'])):?><div class="elev8-operations-engine__notice is-error"><?php echo esc_html(wp_unslash((string)$_GET['error'])); ?></div><?php elseif(!empty($_GET['saved'])):?><div class="elev8-operations-engine__notice is-success"><?php esc_html_e('Work and execution evidence saved.','elev8-os'); ?></div><?php endif;?>
            <header class="elev8-operations-engine__hero">
                <div><p><?php esc_html_e('OPERATIONS ENGINE','elev8-os'); ?></p><h1><?php esc_html_e('What needs to happen next?','elev8-os'); ?></h1><span><?php esc_html_e('One Work Inbox for assignments, approvals, production, repairs, teaching, maintenance, routes, inventory, events, and future operational work.','elev8-os'); ?></span></div>
                <nav><a class="<?php echo $view==='mine'?'is-active':''; ?>" href="<?php echo esc_url(self::url()); ?>"><?php esc_html_e('My Work','elev8-os'); ?></a><?php if($manage):?><a class="<?php echo $view==='team'?'is-active':''; ?>" href="<?php echo esc_url(self::url(['view'=>'team'])); ?>"><?php esc_html_e('Team Work','elev8-os'); ?></a><?php endif;?></nav>
            </header>

            <section class="elev8-operations-engine__metrics">
                <?php foreach(['overdue'=>__('Overdue','elev8-os'),'due_today'=>__('Due Today','elev8-os'),'active'=>__('Active','elev8-os'),'waiting'=>__('Waiting','elev8-os'),'review'=>__('Review','elev8-os')] as $key=>$label): ?><article><strong><?php echo (int)($metrics[$key]??0); ?></strong><span><?php echo esc_html($label); ?></span></article><?php endforeach; ?>
                <?php if($view==='team'):?><article><strong><?php echo (int)($metrics['unassigned']??0); ?></strong><span><?php esc_html_e('Unassigned','elev8-os'); ?></span></article><?php endif;?>
            </section>

            <?php if($manage): ?>
            <details class="elev8-operations-engine__create" <?php echo isset($_GET['create'])?'open':''; ?>>
                <summary><?php esc_html_e('Create Work Item','elev8-os'); ?></summary>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('elev8_operations_create'); ?><input type="hidden" name="action" value="elev8_os_operations_create">
                    <label><?php esc_html_e('Title','elev8-os'); ?><input name="title" required></label>
                    <label><?php esc_html_e('Work type','elev8-os'); ?><select name="type"><?php foreach(Elev8_OS_Operations_Engine_Service::types() as $key=>$label):?><option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option><?php endforeach;?></select></label>
                    <label><?php esc_html_e('Organization scope','elev8-os'); ?><select name="organization_unit_id"><option value="0"><?php esc_html_e('Unscoped / shared','elev8-os'); ?></option><?php foreach($organizations as $unit):?><option value="<?php echo (int)$unit['id']; ?>"><?php echo esc_html($unit['name']); ?></option><?php endforeach;?></select></label>
                    <label><?php esc_html_e('Assign to','elev8-os'); ?><select name="owner_user_id"><option value="0"><?php esc_html_e('Unassigned','elev8-os'); ?></option><?php foreach(Elev8_OS_Access_Service::assignment_users_grouped() as $group=>$users):?><optgroup label="<?php echo esc_attr($group); ?>"><?php foreach($users as $candidate):?><option value="<?php echo (int)$candidate->ID; ?>"><?php echo esc_html($candidate->display_name); ?></option><?php endforeach;?></optgroup><?php endforeach;?></select></label>
                    <label><?php esc_html_e('Due date','elev8-os'); ?><input type="date" name="due_date"></label>
                    <label><?php esc_html_e('Priority','elev8-os'); ?><select name="priority"><?php foreach(Elev8_OS_Work_Service::priorities() as $key=>$label):?><option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option><?php endforeach;?></select></label>
                    <label class="is-wide"><?php esc_html_e('Description','elev8-os'); ?><textarea name="description" rows="3"></textarea></label>
                    <button><?php esc_html_e('Create Work','elev8-os'); ?></button>
                </form>
            </details>
            <?php endif; ?>

            <section class="elev8-operations-engine__signals">
                <header><div><h2><?php esc_html_e('Connected operational systems','elev8-os'); ?></h2><p><?php esc_html_e('These systems remain authoritative for their own records. The Operations Engine coordinates execution around them.','elev8-os'); ?></p></div></header>
                <div><?php foreach(['production'=>__('Production records','elev8-os'),'repair'=>__('Repair records','elev8-os'),'memorial'=>__('Memorial records','elev8-os'),'classes_pending'=>__('Pending class decisions','elev8-os'),'manager_logs'=>__('Operations logs','elev8-os')] as $key=>$label):?><article><strong><?php echo (int)($signals[$key]??0); ?></strong><span><?php echo esc_html($label); ?></span></article><?php endforeach;?></div>
            </section>

            <form class="elev8-operations-engine__filters" method="get" action="<?php echo esc_url(self::url()); ?>">
                <?php if($view==='team'):?><input type="hidden" name="view" value="team"><?php endif;?>
                <label><?php esc_html_e('Status','elev8-os'); ?><select name="status"><option value="active"><?php esc_html_e('Active','elev8-os'); ?></option><?php foreach(Elev8_OS_Work_Service::statuses() as $key=>$label):?><option value="<?php echo esc_attr($key); ?>" <?php selected($status,$key); ?>><?php echo esc_html($label); ?></option><?php endforeach;?></select></label>
                <label><?php esc_html_e('Type','elev8-os'); ?><select name="type"><option value=""><?php esc_html_e('All types','elev8-os'); ?></option><?php foreach(Elev8_OS_Operations_Engine_Service::types() as $key=>$label):?><option value="<?php echo esc_attr($key); ?>" <?php selected($type,$key); ?>><?php echo esc_html($label); ?></option><?php endforeach;?></select></label>
                <?php if($organizations):?><label><?php esc_html_e('Organization','elev8-os'); ?><select name="organization_unit_id"><option value="0"><?php esc_html_e('All allowed','elev8-os'); ?></option><?php foreach($organizations as $unit):?><option value="<?php echo (int)$unit['id']; ?>" <?php selected($organization,(int)$unit['id']); ?>><?php echo esc_html($unit['name']); ?></option><?php endforeach;?></select></label><?php endif;?>
                <button><?php esc_html_e('Filter','elev8-os'); ?></button>
            </form>

            <section class="elev8-operations-engine__inbox">
                <header><div><h2><?php echo esc_html($view==='team'?__('Team Work Inbox','elev8-os'):__('My Work Inbox','elev8-os')); ?></h2><p><?php esc_html_e('Every item answers one question: what should happen next?','elev8-os'); ?></p></div></header>
                <?php if(!$items):?><div class="elev8-operations-engine__empty"><h3><?php esc_html_e('Nothing is waiting','elev8-os'); ?></h3><p><?php esc_html_e('No work items match this view.','elev8-os'); ?></p></div><?php endif;?>
                <?php foreach($items as $item): self::render_item($item,$manage,$view); endforeach;?>
            </section>
        </main>
        <?php return (string)ob_get_clean();
    }

    private static function render_item(array $item, bool $manage, string $view): void {
        $status = $item['status']; $priority = $item['priority'];
        $overdue = $item['due_date'] && $item['due_date'] < current_time('Y-m-d') && !in_array($status,['completed','cancelled','archived'],true);
        ?>
        <article class="elev8-operations-engine__item <?php echo $overdue?'is-overdue':''; ?>">
            <header><div><span><?php echo esc_html($item['type_label']); ?></span><h3><?php echo esc_html($item['title']); ?></h3></div><strong><?php echo esc_html(Elev8_OS_Work_Service::statuses()[$status]??ucfirst($status)); ?></strong></header>
            <?php if($item['description']):?><p><?php echo esc_html(wp_trim_words(wp_strip_all_tags($item['description']),35)); ?></p><?php endif;?>
            <dl><div><dt><?php esc_html_e('Owner','elev8-os'); ?></dt><dd><?php echo esc_html($item['owner_name']); ?></dd></div><div><dt><?php esc_html_e('Due','elev8-os'); ?></dt><dd><?php echo esc_html($item['due_date']?:__('Unavailable','elev8-os')); ?></dd></div><div><dt><?php esc_html_e('Priority','elev8-os'); ?></dt><dd><?php echo esc_html(Elev8_OS_Work_Service::priorities()[$priority]??ucfirst($priority)); ?></dd></div><div><dt><?php esc_html_e('Organization','elev8-os'); ?></dt><dd><?php echo esc_html(Elev8_OS_Operations_Engine_Service::organization_label((int)$item['organization_unit_id'])); ?></dd></div></dl>
            <?php $execution = is_array($item['execution'] ?? null) ? $item['execution'] : ['checklist'=>[],'approvals'=>[],'ready'=>true]; ?>
            <?php if(!empty($execution['checklist']) || !empty($execution['approvals'])): ?>
            <section class="elev8-operations-engine__execution">
                <header><div><h4><?php esc_html_e('Execution evidence','elev8-os'); ?></h4><p><?php esc_html_e('Complete the required procedure and approvals before closing this work item.','elev8-os'); ?></p></div><strong class="<?php echo !empty($execution['ready'])?'is-ready':'is-pending'; ?>"><?php echo esc_html(!empty($execution['ready'])?__('Ready to complete','elev8-os'):__('Evidence required','elev8-os')); ?></strong></header>
                <?php if(!empty($execution['checklist'])):?><fieldset><legend><?php esc_html_e('Procedure checklist','elev8-os'); ?></legend><?php foreach($execution['checklist'] as $step):?><label class="elev8-execution-check"><input form="elev8-work-form-<?php echo (int)$item['id']; ?>" type="checkbox" name="execution_checklist[]" value="<?php echo esc_attr($step['id']); ?>" <?php checked(!empty($step['completed'])); ?>><span><?php echo esc_html($step['label']); ?></span></label><?php endforeach;?></fieldset><?php endif;?>
                <?php if(!empty($execution['approvals'])):?><fieldset><legend><?php esc_html_e('Required approvals','elev8-os'); ?></legend><?php foreach($execution['approvals'] as $approval):?><div class="elev8-execution-approval"><label class="elev8-execution-check"><input form="elev8-work-form-<?php echo (int)$item['id']; ?>" type="checkbox" name="execution_approvals[]" value="<?php echo esc_attr($approval['id']); ?>" <?php checked(!empty($approval['approved'])); ?>><span><?php echo esc_html($approval['label']); ?></span></label><input form="elev8-work-form-<?php echo (int)$item['id']; ?>" name="execution_approval_notes[<?php echo esc_attr($approval['id']); ?>]" value="<?php echo esc_attr((string)($approval['note']??'')); ?>" placeholder="<?php esc_attr_e('Approval note or evidence','elev8-os'); ?>"></div><?php endforeach;?></fieldset><?php endif;?>
            </section>
            <?php endif; ?>
            <form id="elev8-work-form-<?php echo (int)$item['id']; ?>" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('elev8_operations_update_'.$item['id']); ?><input type="hidden" name="action" value="elev8_os_operations_update"><input type="hidden" name="work_id" value="<?php echo (int)$item['id']; ?>"><input type="hidden" name="view" value="<?php echo esc_attr($view); ?>">
                <label><?php esc_html_e('Status','elev8-os'); ?><select name="status"><?php foreach(Elev8_OS_Work_Service::statuses() as $key=>$label):?><option value="<?php echo esc_attr($key); ?>" <?php selected($status,$key); ?>><?php echo esc_html($label); ?></option><?php endforeach;?></select></label>
                <label><?php esc_html_e('Notes','elev8-os'); ?><input name="notes" placeholder="<?php esc_attr_e('Progress, decision, or completion note','elev8-os'); ?>"></label>
                <button><?php esc_html_e('Save','elev8-os'); ?></button>
                <?php if($item['workspace_url']):?><a href="<?php echo esc_url($item['workspace_url']); ?>"><?php esc_html_e('Open Workspace','elev8-os'); ?></a><?php endif;?>
            </form>
            <?php if (class_exists('Elev8_OS_Embedded_Conversation_Service')): ?>
                <details class="elev8-work-conversation" <?php echo absint($_GET['elev8_work_conversation'] ?? 0) === (int)$item['id'] ? 'open' : ''; ?>>
                    <summary><?php esc_html_e('Conversation','elev8-os'); ?><?php $work_unread = Elev8_OS_Embedded_Conversation_Service::unread_count('work_item', (int)$item['id']); if ($work_unread > 0): ?> <span class="elev8-work-conversation__badge"><?php echo esc_html((string)$work_unread); ?></span><?php endif; ?></summary>
                    <?php echo Elev8_OS_Embedded_Conversation_Service::render('work_item', (int)$item['id'], ['title' => sprintf(__('Work: %s','elev8-os'), $item['title']), 'return_url' => self::url($view === 'team' ? ['view'=>'team','elev8_work_conversation'=>(int)$item['id']] : ['elev8_work_conversation'=>(int)$item['id']]), 'participant_user_ids' => [(int)$item['owner_user_id']]]); ?>
                </details>
            <?php endif; ?>
        </article>
        <?php
    }

    /** @return array<int,array{id:int,name:string}> */
    private static function organization_options(WP_User $user): array {
        if (!class_exists('Elev8_OS_Organization_Service')) { return []; }
        $units = [];
        if (user_can($user,'manage_options') || Elev8_OS_Access_Service::user_can('view_ceo_dashboard',$user)) {
            $units = Elev8_OS_Organization_Service::units(['status'=>'active']);
        } else {
            foreach (Elev8_OS_Organization_Service::user_scope_ids($user->ID) as $unit_id) {
                $unit = Elev8_OS_Organization_Service::get_unit((int) $unit_id);
                if ($unit) { $units[] = $unit; }
            }
        }
        $out=[]; foreach((array)$units as $unit){ if(is_array($unit)&&!empty($unit['id']))$out[]=['id'=>(int)$unit['id'],'name'=>(string)($unit['name']??('#'.$unit['id']))]; }
        return $out;
    }

    public static function create(): void {
        if (!Elev8_OS_Access_Service::user_can('manage_operations') && !Elev8_OS_Access_Service::user_can('manage_work')) { wp_die(esc_html__('Permission denied.','elev8-os')); }
        check_admin_referer('elev8_operations_create');
        $organization_id = absint($_POST['organization_unit_id']??0);
        $effective_user = class_exists('Elev8_OS_Preview_Service') ? Elev8_OS_Preview_Service::effective_user() : wp_get_current_user();
        if ($organization_id && !Elev8_OS_Access_Service::user_can_in_scope('manage_operations', $organization_id, $effective_user) && !Elev8_OS_Access_Service::user_can_in_scope('manage_work', $organization_id, $effective_user)) {
            wp_die(esc_html__('You do not have permission to create work in that organization scope.','elev8-os'));
        }
        $result = Elev8_OS_Operations_Engine_Service::create_work([
            'title'=>wp_unslash($_POST['title']??''),'description'=>wp_unslash($_POST['description']??''),'type'=>wp_unslash($_POST['type']??'general'),
            'organization_unit_id'=>$organization_id,'owner_user_id'=>absint($_POST['owner_user_id']??0),'due_date'=>wp_unslash($_POST['due_date']??''),'priority'=>wp_unslash($_POST['priority']??'normal'),
        ]);
        $url=self::url(['view'=>'team']); if(is_wp_error($result))$url=add_query_arg('error',$result->get_error_message(),$url); else $url=add_query_arg('created',1,$url); wp_safe_redirect($url); exit;
    }

    public static function update(): void {
        $id=absint($_POST['work_id']??0); check_admin_referer('elev8_operations_update_'.$id);
        $item=Elev8_OS_Operations_Engine_Service::work_item($id); if(!$item)wp_die(esc_html__('Invalid work item.','elev8-os'));
        $user=class_exists('Elev8_OS_Preview_Service')?Elev8_OS_Preview_Service::effective_user():wp_get_current_user();
        $can=Elev8_OS_Access_Service::user_can('manage_operations',$user)||Elev8_OS_Access_Service::user_can('manage_work',$user)||((int)$item['owner_user_id']===$user->ID&&Elev8_OS_Access_Service::user_can('view_operations',$user));
        if(!$can)wp_die(esc_html__('Permission denied.','elev8-os'));
        if (class_exists('Elev8_OS_SOP_Execution_Service')) {
            Elev8_OS_SOP_Execution_Service::save($id, [
                'checklist' => (array)($_POST['execution_checklist'] ?? []),
                'approvals' => (array)($_POST['execution_approvals'] ?? []),
                'approval_notes' => (array)($_POST['execution_approval_notes'] ?? []),
            ], $user->ID);
        }
        $result=Elev8_OS_Operations_Engine_Service::update_status($id,sanitize_key((string)($_POST['status']??'requested')));
        if(isset($_POST['notes']))Elev8_OS_Work_Service::update($id,['notes'=>wp_unslash($_POST['notes'])]);
        $args=!empty($_POST['view'])&&$_POST['view']==='team'?['view'=>'team']:[];
        if(is_wp_error($result)){$args['error']=$result->get_error_message();}else{$args['saved']=1;}
        wp_safe_redirect(self::url($args)); exit;
    }

    public static function command(array $commands, WP_User $user): array {
        if(Elev8_OS_Access_Service::user_can('view_operations',$user))$commands[]=['id'=>'operations_engine','label'=>__('Operations','elev8-os'),'description'=>__('Open the universal Work Inbox and operational execution view.','elev8-os'),'url'=>self::url(),'group'=>'operations','icon'=>'⚙️','type'=>'command'];
        return $commands;
    }
}

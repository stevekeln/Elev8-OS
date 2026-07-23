<?php
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Operational_Readiness_Module {
    public static function init(): void {
        add_shortcode('elev8_operational_readiness', [__CLASS__, 'shortcode']);
        add_action('admin_menu', [__CLASS__, 'admin_menu']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'assets']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'admin_assets']);
        add_action('admin_post_elev8_save_readiness_standard', [__CLASS__, 'save_standard']);
        add_action('admin_post_elev8_delete_readiness_standard', [__CLASS__, 'delete_standard']);
        add_action('admin_post_elev8_assign_readiness_standard', [__CLASS__, 'assign_standard']);
        add_action('admin_post_elev8_save_readiness_execution', [__CLASS__, 'save_execution']);
        add_filter('elev8_os_command_palette_commands', [__CLASS__, 'command'], 32, 2);
    }

    public static function admin_menu(): void {
        if (Elev8_OS_Operational_Readiness_Service::can_manage()) {
            add_submenu_page('elev8-os', __('Operational Readiness', 'elev8-os'), __('Readiness', 'elev8-os'), 'read', 'elev8-operational-readiness', [__CLASS__, 'admin_page']);
        }
    }

    public static function assets(): void {
        $readiness_page = class_exists('Elev8_OS_Portal_Page_Manager') && Elev8_OS_Portal_Page_Manager::is_current_page('readiness');
        $operations_page = class_exists('Elev8_OS_Operations_Engine_Module') && Elev8_OS_Operations_Engine_Module::shell(false);
        if ($readiness_page || $operations_page) {
            wp_enqueue_style('elev8-operational-readiness', ELEV8_OS_URL . 'assets/css/operational-readiness.css', [], ELEV8_OS_VERSION);
            wp_enqueue_script('elev8-operational-readiness', ELEV8_OS_URL . 'assets/js/operational-readiness.js', [], ELEV8_OS_VERSION, true);
        }
    }

    public static function admin_assets(string $hook): void {
        if (in_array($hook, ['elev8-os_page_elev8-operational-readiness', 'elev8-os_page_elev8-operations-engine'], true)) {
            wp_enqueue_style('elev8-operational-readiness', ELEV8_OS_URL . 'assets/css/operational-readiness.css', [], ELEV8_OS_VERSION);
            wp_enqueue_script('elev8-operational-readiness', ELEV8_OS_URL . 'assets/js/operational-readiness.js', [], ELEV8_OS_VERSION, true);
        }
    }

    public static function shortcode(): string {
        if (!is_user_logged_in()) { return '<p>' . esc_html__('Please sign in.', 'elev8-os') . '</p>'; }
        if (!Elev8_OS_Operational_Readiness_Service::can_manage()) { return '<p>' . esc_html__('You do not have access to manage Experience Standards.', 'elev8-os') . '</p>'; }
        return self::render();
    }

    public static function admin_page(): void { echo '<div class="wrap">' . self::render() . '</div>'; }

    private static function render(): string {
        $edit_id = absint($_GET['edit_standard'] ?? 0);
        $editing = $edit_id ? Elev8_OS_Operational_Readiness_Service::standard($edit_id) : null;
        $standards = Elev8_OS_Operational_Readiness_Service::standards(false);
        $cards = $editing ? (array) $editing['cards'] : [[
            'id'=>'', 'title'=>'', 'instructions'=>'', 'required'=>true, 'active'=>true,
            'verification'=>'checkbox', 'timing'=>'before_start', 'due_offset_minutes'=>0, 'sop_url'=>'', 'sort_order'=>10,
        ]];
        $units = class_exists('Elev8_OS_Organization_Service') ? Elev8_OS_Organization_Service::units(['status'=>'active']) : [];
        ob_start(); ?>
        <main class="elev8-readiness-admin">
            <header class="elev8-readiness-hero"><div><p><?php esc_html_e('OPERATIONAL READINESS', 'elev8-os'); ?></p><h1><?php esc_html_e('Build the experience before the work begins.', 'elev8-os'); ?></h1><span><?php esc_html_e('Create reusable card sets for shifts, events, classes, production jobs, and Work Items. The right cards appear where employees already work.', 'elev8-os'); ?></span></div></header>
            <?php if (!empty($_GET['saved'])): ?><div class="notice notice-success inline"><p><?php esc_html_e('Experience Standard saved.', 'elev8-os'); ?></p></div><?php endif; ?>
            <section class="elev8-readiness-layout">
                <aside class="elev8-readiness-list">
                    <div class="elev8-readiness-list__head"><h2><?php esc_html_e('Experience Standards', 'elev8-os'); ?></h2><a class="button button-primary" href="<?php echo esc_url(self::url()); ?>"><?php esc_html_e('+ New standard', 'elev8-os'); ?></a></div>
                    <?php if (!$standards): ?><p><?php esc_html_e('No standards yet. Create the first one for an event, class, shift, or job.', 'elev8-os'); ?></p><?php endif; ?>
                    <?php foreach ($standards as $standard): ?><article class="elev8-readiness-standard-card <?php echo !empty($standard['active']) ? '' : 'is-inactive'; ?>"><div><strong><?php echo esc_html($standard['title']); ?></strong><span><?php echo esc_html(sprintf(_n('%d card', '%d cards', count($standard['cards']), 'elev8-os'), count($standard['cards']))); ?></span></div><a href="<?php echo esc_url(self::url(['edit_standard'=>(int)$standard['id']])); ?>"><?php esc_html_e('Edit', 'elev8-os'); ?></a></article><?php endforeach; ?>
                </aside>
                <section class="elev8-readiness-editor">
                    <h2><?php echo esc_html($editing ? __('Edit Experience Standard', 'elev8-os') : __('Create Experience Standard', 'elev8-os')); ?></h2>
                    <p><?php esc_html_e('Think of each item as a Trello-style card that appears when the employee is doing the relevant work.', 'elev8-os'); ?></p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-readiness-standard-form>
                        <input type="hidden" name="action" value="elev8_save_readiness_standard"><input type="hidden" name="standard_id" value="<?php echo (int)($editing['id'] ?? 0); ?>"><?php wp_nonce_field('elev8_save_readiness_standard'); ?>
                        <div class="elev8-readiness-fields">
                            <label><?php esc_html_e('Standard name', 'elev8-os'); ?><input name="title" required value="<?php echo esc_attr((string)($editing['title'] ?? '')); ?>" placeholder="<?php esc_attr_e('Art Walk opening readiness', 'elev8-os'); ?>"></label>
                            <label><?php esc_html_e('Context', 'elev8-os'); ?><select name="context_type"><?php foreach (self::context_types() as $key=>$label): ?><option value="<?php echo esc_attr($key); ?>" <?php selected((string)($editing['context_type'] ?? 'work_item'), $key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
                            <label><?php esc_html_e('Organization scope', 'elev8-os'); ?><select name="organization_unit_id"><option value="0"><?php esc_html_e('Shared / any organization', 'elev8-os'); ?></option><?php foreach ((array)$units as $unit): ?><option value="<?php echo (int)$unit['id']; ?>" <?php selected((int)($editing['organization_unit_id'] ?? 0),(int)$unit['id']); ?>><?php echo esc_html((string)$unit['name']); ?></option><?php endforeach; ?></select></label>
                            <label><?php esc_html_e('Work type filter', 'elev8-os'); ?><select name="work_type"><option value=""><?php esc_html_e('Any Work Item type', 'elev8-os'); ?></option><?php foreach (Elev8_OS_Operations_Engine_Service::types() as $key=>$label): ?><option value="<?php echo esc_attr($key); ?>" <?php selected((string)($editing['work_type'] ?? ''),$key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
                            <label><?php esc_html_e('Role key (optional)', 'elev8-os'); ?><input name="role_key" value="<?php echo esc_attr((string)($editing['role_key'] ?? '')); ?>" placeholder="shop_employee"></label>
                            <label class="is-check"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" <?php checked(!isset($editing['active']) || !empty($editing['active'])); ?>> <?php esc_html_e('Active and available', 'elev8-os'); ?></label>
                            <label class="is-wide"><?php esc_html_e('Purpose', 'elev8-os'); ?><textarea name="description" rows="3" placeholder="<?php esc_attr_e('What experience should this standard protect?', 'elev8-os'); ?>"><?php echo esc_textarea((string)($editing['description'] ?? '')); ?></textarea></label>
                        </div>
                        <div class="elev8-readiness-cards-head"><div><h3><?php esc_html_e('Readiness cards', 'elev8-os'); ?></h3><p><?php esc_html_e('Add, remove, reorder, or temporarily disable cards whenever the experience changes.', 'elev8-os'); ?></p></div><button type="button" class="button button-primary" data-add-readiness-card><?php esc_html_e('+ Add card', 'elev8-os'); ?></button></div>
                        <div data-readiness-cards><?php foreach ($cards as $index=>$card) { self::card_row((int)$index, $card); } ?></div>
                        <p class="submit"><button class="button button-primary button-large"><?php esc_html_e('Save Experience Standard', 'elev8-os'); ?></button></p>
                    </form>
                    <?php if ($editing): ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js(__('Remove this Experience Standard?', 'elev8-os')); ?>')"><input type="hidden" name="action" value="elev8_delete_readiness_standard"><input type="hidden" name="standard_id" value="<?php echo (int)$editing['id']; ?>"><?php wp_nonce_field('elev8_delete_readiness_standard_'.$editing['id']); ?><button class="button-link-delete"><?php esc_html_e('Remove standard', 'elev8-os'); ?></button></form><?php endif; ?>
                </section>
            </section>
            <template id="elev8-readiness-card-template"><?php self::card_row('__INDEX__', ['id'=>'','title'=>'','instructions'=>'','required'=>true,'active'=>true,'verification'=>'checkbox','timing'=>'before_start','due_offset_minutes'=>0,'sop_url'=>'','sort_order'=>10]); ?></template>
        </main><?php return (string) ob_get_clean();
    }

    private static function card_row($index, array $card): void { ?>
        <article class="elev8-readiness-card-row" data-readiness-card>
            <input type="hidden" name="cards[<?php echo esc_attr((string)$index); ?>][id]" value="<?php echo esc_attr((string)($card['id'] ?? '')); ?>">
            <div class="elev8-readiness-card-row__bar"><button type="button" class="elev8-readiness-drag" aria-label="<?php esc_attr_e('Drag to reorder', 'elev8-os'); ?>">⋮⋮</button><strong><?php esc_html_e('Readiness card', 'elev8-os'); ?></strong><button type="button" class="button-link-delete" data-remove-readiness-card><?php esc_html_e('Remove', 'elev8-os'); ?></button></div>
            <div class="elev8-readiness-card-row__grid">
                <label class="is-wide"><?php esc_html_e('Card title', 'elev8-os'); ?><input name="cards[<?php echo esc_attr((string)$index); ?>][title]" required value="<?php echo esc_attr((string)($card['title'] ?? '')); ?>" placeholder="<?php esc_attr_e('Goodie bags assembled before doors open', 'elev8-os'); ?>"></label>
                <label class="is-wide"><?php esc_html_e('Instructions', 'elev8-os'); ?><textarea name="cards[<?php echo esc_attr((string)$index); ?>][instructions]" rows="2"><?php echo esc_textarea((string)($card['instructions'] ?? '')); ?></textarea></label>
                <label><?php esc_html_e('When', 'elev8-os'); ?><select name="cards[<?php echo esc_attr((string)$index); ?>][timing]"><?php foreach (self::timings() as $key=>$label): ?><option value="<?php echo esc_attr($key); ?>" <?php selected((string)($card['timing'] ?? 'before_start'),$key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
                <label><?php esc_html_e('Verification', 'elev8-os'); ?><select name="cards[<?php echo esc_attr((string)$index); ?>][verification]"><?php foreach (self::verification_types() as $key=>$label): ?><option value="<?php echo esc_attr($key); ?>" <?php selected((string)($card['verification'] ?? 'checkbox'),$key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
                <label><?php esc_html_e('Due offset (minutes)', 'elev8-os'); ?><input type="number" min="0" name="cards[<?php echo esc_attr((string)$index); ?>][due_offset_minutes]" value="<?php echo esc_attr((string)($card['due_offset_minutes'] ?? 0)); ?>"></label>
                <label><?php esc_html_e('SOP or guide link', 'elev8-os'); ?><input type="url" name="cards[<?php echo esc_attr((string)$index); ?>][sop_url]" value="<?php echo esc_attr((string)($card['sop_url'] ?? '')); ?>"></label>
                <label><?php esc_html_e('Order', 'elev8-os'); ?><input type="number" name="cards[<?php echo esc_attr((string)$index); ?>][sort_order]" value="<?php echo esc_attr((string)($card['sort_order'] ?? 10)); ?>"></label>
                <label class="is-check"><input type="hidden" name="cards[<?php echo esc_attr((string)$index); ?>][required]" value="0"><input type="checkbox" name="cards[<?php echo esc_attr((string)$index); ?>][required]" value="1" <?php checked(!empty($card['required'])); ?>> <?php esc_html_e('Required', 'elev8-os'); ?></label>
                <label class="is-check"><input type="hidden" name="cards[<?php echo esc_attr((string)$index); ?>][active]" value="0"><input type="checkbox" name="cards[<?php echo esc_attr((string)$index); ?>][active]" value="1" <?php checked(!isset($card['active']) || !empty($card['active'])); ?>> <?php esc_html_e('Active', 'elev8-os'); ?></label>
            </div>
        </article><?php }

    public static function render_work_readiness(array $item, bool $manage, string $view): void {
        $work_id = (int) ($item['id'] ?? 0);
        if (!$work_id) { return; }
        $standards = Elev8_OS_Operational_Readiness_Service::applicable_standards_for_work($work_id);
        $assigned_id = absint(get_post_meta($work_id, Elev8_OS_Operational_Readiness_Service::WORK_STANDARD_META, true));
        if ($manage): ?>
            <details class="elev8-work-readiness-assign"><summary><?php esc_html_e('Experience Standard', 'elev8-os'); ?><?php if ($standards): ?> <span><?php echo esc_html((string)count($standards)); ?></span><?php endif; ?></summary>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="elev8_assign_readiness_standard"><input type="hidden" name="work_id" value="<?php echo $work_id; ?>"><input type="hidden" name="view" value="<?php echo esc_attr($view); ?>"><?php wp_nonce_field('elev8_assign_readiness_standard_'.$work_id); ?><label><?php esc_html_e('Apply standard', 'elev8-os'); ?><select name="standard_id"><option value="0"><?php esc_html_e('Use automatic matching / none', 'elev8-os'); ?></option><?php foreach (Elev8_OS_Operational_Readiness_Service::standards(true) as $standard): ?><option value="<?php echo (int)$standard['id']; ?>" <?php selected($assigned_id,(int)$standard['id']); ?>><?php echo esc_html($standard['title']); ?></option><?php endforeach; ?></select></label><button><?php esc_html_e('Apply', 'elev8-os'); ?></button></form>
            </details><?php endif;
        foreach ($standards as $standard):
            $snapshot = Elev8_OS_Operational_Readiness_Service::snapshot($work_id, (int)$standard['id']); ?>
            <section class="elev8-work-readiness <?php echo !empty($snapshot['ready']) ? 'is-ready' : 'is-pending'; ?>">
                <header><div><p><?php esc_html_e('EXPERIENCE STANDARD', 'elev8-os'); ?></p><h4><?php echo esc_html($standard['title']); ?></h4><?php if (!empty($standard['description'])): ?><span><?php echo esc_html(wp_trim_words(wp_strip_all_tags($standard['description']),24)); ?></span><?php endif; ?></div><div class="elev8-readiness-score"><strong><?php echo (int)$snapshot['score']; ?>%</strong><span><?php echo esc_html(!empty($snapshot['ready']) ? __('Ready', 'elev8-os') : __('Not ready', 'elev8-os')); ?></span></div></header>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="elev8_save_readiness_execution"><input type="hidden" name="work_id" value="<?php echo $work_id; ?>"><input type="hidden" name="standard_id" value="<?php echo (int)$standard['id']; ?>"><input type="hidden" name="view" value="<?php echo esc_attr($view); ?>"><?php wp_nonce_field('elev8_save_readiness_execution_'.$work_id.'_'.$standard['id']); ?>
                    <div class="elev8-work-readiness__cards"><?php foreach ((array)$snapshot['cards'] as $card): ?><article class="elev8-work-readiness__card <?php echo !empty($card['completed']) ? 'is-complete' : ''; ?>"><label><input type="checkbox" name="completed[]" value="<?php echo esc_attr($card['id']); ?>" <?php checked(!empty($card['completed'])); ?> <?php disabled(($card['verification'] ?? '') === 'manager_approval' && !$manage); ?>><span><strong><?php echo esc_html($card['title']); ?></strong><?php if (!empty($card['instructions'])): ?><small><?php echo esc_html($card['instructions']); ?></small><?php endif; ?><em><?php echo esc_html(self::timings()[$card['timing']] ?? ucfirst((string)$card['timing'])); ?><?php if (!empty($card['required'])) echo ' · ' . esc_html__('Required','elev8-os'); ?></em></span></label><?php if (($card['verification'] ?? '') !== 'checkbox'): ?><textarea name="notes[<?php echo esc_attr($card['id']); ?>]" rows="2" placeholder="<?php echo esc_attr(($card['verification'] ?? '') === 'photo_reference' ? __('Paste photo or media reference', 'elev8-os') : __('Add verification note', 'elev8-os')); ?>"><?php echo esc_textarea((string)($card['note'] ?? '')); ?></textarea><?php endif; ?><?php if (!empty($card['sop_url'])): ?><a target="_blank" rel="noopener" href="<?php echo esc_url($card['sop_url']); ?>"><?php esc_html_e('Open SOP', 'elev8-os'); ?></a><?php endif; ?></article><?php endforeach; ?></div>
                    <button><?php esc_html_e('Save readiness', 'elev8-os'); ?></button>
                </form>
            </section>
        <?php endforeach;
    }

    public static function save_standard(): void { check_admin_referer('elev8_save_readiness_standard'); $result=Elev8_OS_Operational_Readiness_Service::save_standard(wp_unslash($_POST),absint($_POST['standard_id']??0)); if(is_wp_error($result))wp_die(esc_html($result->get_error_message())); wp_safe_redirect(add_query_arg(['saved'=>1,'edit_standard'=>(int)$result],self::url())); exit; }
    public static function delete_standard(): void { $id=absint($_POST['standard_id']??0); check_admin_referer('elev8_delete_readiness_standard_'.$id); $result=Elev8_OS_Operational_Readiness_Service::delete_standard($id); if(is_wp_error($result))wp_die(esc_html($result->get_error_message())); wp_safe_redirect(self::url()); exit; }
    public static function assign_standard(): void { $work_id=absint($_POST['work_id']??0); check_admin_referer('elev8_assign_readiness_standard_'.$work_id); $result=Elev8_OS_Operational_Readiness_Service::assign_standard($work_id,absint($_POST['standard_id']??0)); if(is_wp_error($result))wp_die(esc_html($result->get_error_message())); wp_safe_redirect(Elev8_OS_Operations_Engine_Module::url(!empty($_POST['view'])&&$_POST['view']==='team'?['view'=>'team','saved'=>1]:['saved'=>1])); exit; }
    public static function save_execution(): void { $work_id=absint($_POST['work_id']??0); $standard_id=absint($_POST['standard_id']??0); check_admin_referer('elev8_save_readiness_execution_'.$work_id.'_'.$standard_id); $user=class_exists('Elev8_OS_Preview_Service')?Elev8_OS_Preview_Service::effective_user():wp_get_current_user(); $result=Elev8_OS_Operational_Readiness_Service::save_execution($work_id,$standard_id,wp_unslash($_POST),$user); if(is_wp_error($result))wp_die(esc_html($result->get_error_message())); wp_safe_redirect(Elev8_OS_Operations_Engine_Module::url(!empty($_POST['view'])&&$_POST['view']==='team'?['view'=>'team','saved'=>1]:['saved'=>1])); exit; }

    public static function url(array $args=[]): string { $base=class_exists('Elev8_OS_Portal_Page_Manager')?Elev8_OS_Portal_Page_Manager::get_url('readiness'):home_url('/elev8-readiness/'); return $args?add_query_arg($args,$base):$base; }
    public static function command(array $commands, WP_User $user): array { if(self::can_view($user))$commands[]=['id'=>'operational_readiness','label'=>__('Operational Readiness','elev8-os'),'description'=>__('Manage Experience Standards and contextual readiness cards.','elev8-os'),'url'=>self::url(),'group'=>'operations','icon'=>'✅','type'=>'command']; return $commands; }
    private static function can_view(WP_User $user): bool { return Elev8_OS_Operational_Readiness_Service::can_manage($user); }
    private static function context_types(): array { return ['work_item'=>__('Work Item / job','elev8-os'),'shift'=>__('Shift','elev8-os'),'event'=>__('Event','elev8-os'),'class'=>__('Class','elev8-os'),'production_job'=>__('Production job','elev8-os'),'location'=>__('Location opening/closing','elev8-os')]; }
    private static function timings(): array { return ['before_start'=>__('Before start','elev8-os'),'during'=>__('During execution','elev8-os'),'before_complete'=>__('Before completion','elev8-os'),'anytime'=>__('Any time','elev8-os')]; }
    private static function verification_types(): array { return ['checkbox'=>__('Simple confirmation','elev8-os'),'note'=>__('Confirmation + note','elev8-os'),'photo_reference'=>__('Photo/media reference','elev8-os'),'manager_approval'=>__('Manager approval','elev8-os')]; }
}

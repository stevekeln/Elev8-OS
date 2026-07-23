<?php
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_IT_Support_Module {
    public static function init(): void {
        add_shortcode('elev8_it_support', [__CLASS__, 'shortcode']);
        add_action('admin_menu', [__CLASS__, 'register_admin_menu']);
        add_action('admin_post_elev8_report_it_incident', [__CLASS__, 'report']);
        add_action('admin_post_elev8_save_it_support_settings', [__CLASS__, 'save_settings']);
        add_action('admin_post_elev8_resolve_it_incident', [__CLASS__, 'resolve']);
    }

    public static function register_admin_menu(): void { add_submenu_page('elev8-os', __('IT Support', 'elev8-os'), __('IT Support', 'elev8-os'), 'read', 'elev8-it-support', [__CLASS__, 'admin_page']); }
    public static function admin_page(): void { echo self::render(); }
    public static function shortcode(): string { return self::render(); }

    private static function render(): string {
        if (!is_user_logged_in()) { return '<p>'.esc_html__('Please sign in to use IT Support.', 'elev8-os').'</p>'; }
        $user_id = get_current_user_id();
        $support = Elev8_OS_IT_Support_Service::is_support_user($user_id);
        $records = Elev8_OS_IT_Support_Service::incidents($user_id);
        $units = class_exists('Elev8_OS_Organization_Service') ? Elev8_OS_Organization_Service::units(['status' => 'active']) : [];
        ob_start(); ?>
        <main style="max-width:1120px;margin:24px auto;padding:0 16px">
            <h1><?php echo esc_html__('IT Support', 'elev8-os'); ?></h1>
            <p><?php echo esc_html__('Report technology problems and route them through the shared Asset, Maintenance, Operations, and Work Item system.', 'elev8-os'); ?></p>
            <?php if (isset($_GET['reported'])): ?><div class="notice notice-success inline"><p><?php echo esc_html__('Technology incident reported.', 'elev8-os'); ?></p></div><?php endif; ?>
            <section style="background:#fff;border:1px solid #ddd;border-radius:16px;padding:22px;margin-bottom:20px">
                <h2><?php echo esc_html__('Report a technology issue', 'elev8-os'); ?></h2>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="elev8_report_it_incident"><?php wp_nonce_field('elev8_report_it_incident'); ?>
                    <p><label><?php echo esc_html__('Issue type', 'elev8-os'); ?><br><select name="incident_type" required><?php foreach (Elev8_OS_IT_Support_Service::incident_types() as $key=>$label): ?><option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label></p>
                    <p><label><?php echo esc_html__('Computer, device, or system', 'elev8-os'); ?><br><input class="regular-text" name="asset_label" required></label></p>
                    <p><label><?php echo esc_html__('Location', 'elev8-os'); ?><br><input class="regular-text" name="location_label"></label></p>
                    <?php if ($units): ?><p><label><?php echo esc_html__('Organization location or team', 'elev8-os'); ?><br><select name="organization_unit_id"><option value="0"><?php echo esc_html__('Not selected', 'elev8-os'); ?></option><?php foreach ($units as $unit): ?><option value="<?php echo esc_attr((string)$unit['id']); ?>"><?php echo esc_html((string)$unit['name']); ?></option><?php endforeach; ?></select></label></p><?php endif; ?>
                    <p><label><?php echo esc_html__('What is happening?', 'elev8-os'); ?><br><textarea name="description" rows="5" class="large-text" required></textarea></label></p>
                    <p><label><?php echo esc_html__('Business impact', 'elev8-os'); ?><br><textarea name="business_impact" rows="3" class="large-text"></textarea></label></p>
                    <p><label><input type="checkbox" name="critical" value="1"> <?php echo esc_html__('Critical: checkout, payments, internet, security, or essential operations are blocked', 'elev8-os'); ?></label></p>
                    <button class="button button-primary" type="submit"><?php echo esc_html__('Report issue', 'elev8-os'); ?></button>
                </form>
            </section>
            <?php if ($support && current_user_can('manage_options')): self::settings(); endif; ?>
            <section style="background:#fff;border:1px solid #ddd;border-radius:16px;padding:22px">
                <h2><?php echo esc_html($support ? __('IT incident queue', 'elev8-os') : __('My reported incidents', 'elev8-os')); ?></h2>
                <?php if (!$records): ?><p><?php echo esc_html__('No technology incidents are recorded.', 'elev8-os'); ?></p><?php else: ?><div style="display:grid;gap:12px"><?php foreach ($records as $record): $context=is_array($record['context']??null)?$record['context']:[]; ?>
                    <article style="border:1px solid #ddd;border-radius:12px;padding:16px"><div style="display:flex;justify-content:space-between;gap:12px"><div><strong><?php echo esc_html((string)$record['title']); ?></strong><p><?php echo esc_html((string)$record['description']); ?></p><small><?php echo esc_html(sprintf(__('%1$s · %2$s · %3$s', 'elev8-os'), ucfirst((string)$record['priority']), ucfirst(str_replace('_',' ',(string)$record['status'])), (string)$record['location_label'])); ?></small><?php if (!empty($context['business_impact'])): ?><p><strong><?php echo esc_html__('Impact:', 'elev8-os'); ?></strong> <?php echo esc_html((string)$context['business_impact']); ?></p><?php endif; ?></div>
                    <?php if ($support && !in_array((string)$record['status'], ['resolved','cancelled'], true)): ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="elev8_resolve_it_incident"><input type="hidden" name="record_id" value="<?php echo esc_attr((string)$record['id']); ?>"><?php wp_nonce_field('elev8_resolve_it_incident_'.$record['id']); ?><textarea name="notes" placeholder="<?php echo esc_attr__('Resolution notes', 'elev8-os'); ?>"></textarea><br><button class="button" type="submit"><?php echo esc_html__('Resolve', 'elev8-os'); ?></button></form><?php endif; ?></div></article>
                <?php endforeach; ?></div><?php endif; ?>
            </section>
        </main><?php return (string)ob_get_clean();
    }

    private static function settings(): void {
        $selected = Elev8_OS_IT_Support_Service::support_user_ids();
        $groups = Elev8_OS_Access_Service::assignment_users_grouped();
        echo '<section style="background:#fff;border:1px solid #ddd;border-radius:16px;padding:22px;margin-bottom:20px"><h2>'.esc_html__('IT support assignment', 'elev8-os').'</h2><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="elev8_save_it_support_settings">'; wp_nonce_field('elev8_save_it_support_settings');
        echo '<p>'.esc_html__('Select the people who should receive technology incidents. No formal IT department is required.', 'elev8-os').'</p>';
        foreach ($groups as $label=>$users) { foreach ((array)$users as $user) { if (!$user instanceof WP_User) continue; echo '<label style="display:block;margin:6px 0"><input type="checkbox" name="support_users[]" value="'.esc_attr((string)$user->ID).'" '.checked(in_array((int)$user->ID,$selected,true),true,false).'> '.esc_html($user->display_name).' <small>('.esc_html((string)$label).')</small></label>'; } }
        echo '<p><button class="button button-primary">'.esc_html__('Save IT support assignment', 'elev8-os').'</button></p></form></section>';
    }

    public static function report(): void { if (!is_user_logged_in()) wp_die('Unauthorized'); check_admin_referer('elev8_report_it_incident'); $result=Elev8_OS_IT_Support_Service::report(wp_unslash($_POST)); if (is_wp_error($result)) wp_die(esc_html($result->get_error_message())); wp_safe_redirect(add_query_arg('reported','1',Elev8_OS_Portal_Page_Manager::get_url('it_support'))); exit; }
    public static function save_settings(): void { if (!current_user_can('manage_options')) wp_die('Unauthorized'); check_admin_referer('elev8_save_it_support_settings'); Elev8_OS_IT_Support_Service::save_support_user_ids((array)($_POST['support_users']??[])); wp_safe_redirect(Elev8_OS_Portal_Page_Manager::get_url('it_support')); exit; }
    public static function resolve(): void { $id=absint($_POST['record_id']??0); if (!Elev8_OS_IT_Support_Service::is_support_user(get_current_user_id())) wp_die('Unauthorized'); check_admin_referer('elev8_resolve_it_incident_'.$id); Elev8_OS_Maintenance_Service::resolve($id,sanitize_textarea_field(wp_unslash($_POST['notes']??''))); wp_safe_redirect(Elev8_OS_Portal_Page_Manager::get_url('it_support')); exit; }
}

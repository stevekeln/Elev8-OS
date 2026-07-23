<?php
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_UI_Framework_Module {
    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_post_elev8_save_ui_framework', [__CLASS__, 'save']);
    }

    public static function menu(): void {
        add_submenu_page('elev8-os', __('UI Framework', 'elev8-os'), __('UI Framework', 'elev8-os'), 'manage_options', 'elev8-ui-framework', [__CLASS__, 'render']);
    }

    public static function save(): void {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('Not allowed.', 'elev8-os')); }
        check_admin_referer('elev8_save_ui_framework');
        $pack = sanitize_key((string) ($_POST['theme_pack'] ?? 'business'));
        if (!in_array($pack, ['business','studio','retail','executive'], true)) { $pack = 'business'; }
        update_option(Elev8_OS_UI_Framework_Service::OPTION, [
            'enabled' => isset($_POST['enabled']) ? 1 : 0,
            'theme_pack' => $pack,
            'role_shells' => isset($_POST['role_shells']) ? 1 : 0,
            'legacy_bridge' => isset($_POST['legacy_bridge']) ? 1 : 0,
        ], false);
        wp_safe_redirect(add_query_arg(['page' => 'elev8-ui-framework', 'updated' => '1'], admin_url('admin.php')));
        exit;
    }

    public static function render(): void {
        if (!current_user_can('manage_options')) { return; }
        $s = Elev8_OS_UI_Framework_Service::settings();
        ?>
        <div class="wrap elev8-ui-admin">
            <h1><?php esc_html_e('Elev8 UI Framework', 'elev8-os'); ?></h1>
            <p><?php esc_html_e('Presentation is now governed separately from business engines. Use this screen to control the gradual migration to reusable Elev8 shells and components.', 'elev8-os'); ?></p>
            <?php if (isset($_GET['updated'])) : ?><div class="notice notice-success"><p><?php esc_html_e('UI Framework settings saved.', 'elev8-os'); ?></p></div><?php endif; ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="elev8-ui-card elev8-ui-settings-card">
                <input type="hidden" name="action" value="elev8_save_ui_framework">
                <?php wp_nonce_field('elev8_save_ui_framework'); ?>
                <label><input type="checkbox" name="enabled" value="1" <?php checked(!empty($s['enabled'])); ?>> <?php esc_html_e('Enable the Elev8 UI Framework', 'elev8-os'); ?></label>
                <label><input type="checkbox" name="role_shells" value="1" <?php checked(!empty($s['role_shells'])); ?>> <?php esc_html_e('Apply role-aware shell context', 'elev8-os'); ?></label>
                <label><input type="checkbox" name="legacy_bridge" value="1" <?php checked(!empty($s['legacy_bridge'])); ?>> <?php esc_html_e('Apply safe compatibility rules to existing screens', 'elev8-os'); ?></label>
                <label for="elev8-theme-pack"><strong><?php esc_html_e('Default theme pack', 'elev8-os'); ?></strong></label>
                <select id="elev8-theme-pack" name="theme_pack">
                    <option value="business" <?php selected($s['theme_pack'], 'business'); ?>>Elev8 Business</option>
                    <option value="studio" <?php selected($s['theme_pack'], 'studio'); ?>>Elev8 Studio</option>
                    <option value="retail" <?php selected($s['theme_pack'], 'retail'); ?>>Elev8 Retail</option>
                    <option value="executive" <?php selected($s['theme_pack'], 'executive'); ?>>Elev8 Executive</option>
                </select>
                <?php submit_button(__('Save UI Framework', 'elev8-os')); ?>
            </form>
            <div class="elev8-ui-grid elev8-ui-grid--3">
                <article class="elev8-ui-card"><span class="elev8-ui-kicker">FOUNDATION</span><h2>Design Tokens</h2><p>One source for spacing, type, borders, shadows, surfaces, and responsive sizing.</p></article>
                <article class="elev8-ui-card"><span class="elev8-ui-kicker">PRESENTATION</span><h2>Role Shells</h2><p>Executive, Studio, Retail, Artist, Event, and Business contexts without duplicating engine logic.</p></article>
                <article class="elev8-ui-card"><span class="elev8-ui-kicker">MIGRATION</span><h2>Legacy Bridge</h2><p>Safe width, wrapping, form, and card rules while existing workspaces move into shared components.</p></article>
            </div>
        </div>
        <?php
    }
}

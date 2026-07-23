<?php
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Role_Preview_Module {
    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'register_menu'], 21);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_frontend_assets']);
        add_action('admin_notices', [__CLASS__, 'render_banner']);
        add_action('wp_body_open', [__CLASS__, 'render_banner']);
        add_action('wp_footer', [__CLASS__, 'render_banner_fallback'], 0);
        add_action('admin_head', [__CLASS__, 'render_clean_preview_admin_styles']);
    }

    public static function register_menu(): void {
        add_submenu_page(
            'elev8-os',
            __('Role Preview', 'elev8-os'),
            __('Role Preview', 'elev8-os'),
            'manage_options',
            'elev8-role-preview',
            [__CLASS__, 'render_page']
        );
    }

    public static function enqueue_assets(string $hook = ''): void {
        if ($hook === 'elev8-os_page_elev8-role-preview' || Elev8_OS_Preview_Service::is_active()) {
            wp_enqueue_style('elev8-os-role-preview', ELEV8_OS_URL . 'assets/css/role-preview.css', [], ELEV8_OS_VERSION);
        }
    }

    public static function enqueue_frontend_assets(): void {
        if (Elev8_OS_Preview_Service::is_active()) {
            wp_enqueue_style('elev8-os-role-preview', ELEV8_OS_URL . 'assets/css/role-preview.css', [], ELEV8_OS_VERSION);
        }
    }

    public static function render_page(): void {
        if (!Elev8_OS_Preview_Service::can_preview()) { wp_die(esc_html__('You do not have permission to preview roles.', 'elev8-os')); }
        $role = sanitize_key((string) ($_GET['preview_role'] ?? Elev8_OS_Preview_Service::selected_role() ?: 'glass_manager'));
        $roles = Elev8_OS_Preview_Service::roles();
        if (!isset($roles[$role])) { $role = 'glass_manager'; }
        $users = Elev8_OS_Preview_Service::users_for_role($role);
        $active = Elev8_OS_Preview_Service::target_user();
        ?>
        <div class="wrap elev8-role-preview-admin">
            <header class="elev8-role-preview-hero">
                <div><span><?php esc_html_e('FOUNDER TOOL', 'elev8-os'); ?></span><h1><?php esc_html_e('Experience Elev8 OS as Any Role', 'elev8-os'); ?></h1><p><?php esc_html_e('Preview the real role-aware dashboard without logging out, opening Incognito, or knowing another user’s password.', 'elev8-os'); ?></p></div>
                <?php if ($active instanceof WP_User) : ?><a class="button button-secondary" href="<?php echo esc_url(Elev8_OS_Preview_Service::stop_url(Elev8_OS_Preview_Service::preview_page_url())); ?>"><?php esc_html_e('Exit Current Preview', 'elev8-os'); ?></a><?php endif; ?>
            </header>

            <section class="elev8-role-preview-card">
                <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
                    <input type="hidden" name="page" value="elev8-role-preview">
                    <label for="elev8-preview-role"><?php esc_html_e('1. Choose a role', 'elev8-os'); ?></label>
                    <select id="elev8-preview-role" name="preview_role" onchange="this.form.submit()">
                        <?php foreach ($roles as $key => $label) : ?><option value="<?php echo esc_attr($key); ?>" <?php selected($role, $key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?>
                    </select>
                </form>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" target="_blank">
                    <?php wp_nonce_field('elev8_os_start_preview'); ?>
                    <input type="hidden" name="action" value="elev8_os_start_preview">
                    <input type="hidden" name="preview_role" value="<?php echo esc_attr($role); ?>">
                    <label for="elev8-preview-user"><?php esc_html_e('2. Choose the person', 'elev8-os'); ?></label>
                    <select id="elev8-preview-user" name="target_user_id" required>
                        <option value=""><?php esc_html_e('Choose a user', 'elev8-os'); ?></option>
                        <?php foreach ($users as $user) : ?>
                            <option value="<?php echo esc_attr((string) $user->ID); ?>"><?php echo esc_html($user->display_name . ' — ' . $user->user_email); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!$users) : ?><p class="description"><?php esc_html_e('No WordPress users currently match this role. Assign the role or capability first.', 'elev8-os'); ?></p><?php endif; ?>
                    <label for="elev8-preview-destination"><?php esc_html_e('3. Start here', 'elev8-os'); ?></label>
                    <select id="elev8-preview-destination" name="preview_destination">
                        <option value="dashboard"><?php esc_html_e('Role Dashboard', 'elev8-os'); ?></option>
                        <option value="profile"><?php esc_html_e('Public Profile Editor', 'elev8-os'); ?></option>
                    </select>
                    <button class="button button-primary button-hero" type="submit" <?php disabled(!$users); ?>><?php esc_html_e('Open Preview in New Window', 'elev8-os'); ?></button>
                    <p class="description"><?php esc_html_e('The new window hides the WordPress administrator chrome so you see the same Elev8 OS application experience as the selected person.', 'elev8-os'); ?></p>
                </form>
            </section>

            <section class="elev8-role-preview-note"><h2><?php esc_html_e('How Preview Mode Works', 'elev8-os'); ?></h2><p><?php esc_html_e('Elev8 OS uses the selected person for dashboard data and centralized capability checks while you remain securely logged in as the owner. Email and notification delivery is suppressed during preview requests. A persistent banner identifies the selected person and provides one-click exit and workspace shortcuts.', 'elev8-os'); ?></p></section>
        </div>
        <?php
    }

    public static function render_banner(): void {
        static $rendered = false;
        if ($rendered || !Elev8_OS_Preview_Service::is_active()) { return; }
        $target = Elev8_OS_Preview_Service::target_user();
        if (!$target instanceof WP_User) { return; }
        $rendered = true;
        $role = Elev8_OS_Preview_Service::roles()[Elev8_OS_Preview_Service::selected_role()] ?? __('Preview User', 'elev8-os');
        $links = Elev8_OS_Preview_Service::jump_links($target);
        ?>
        <aside class="elev8-preview-banner" data-elev8-preview-banner>
            <div><strong><?php esc_html_e('PREVIEW MODE', 'elev8-os'); ?></strong><span><?php echo esc_html(sprintf(__('Viewing as %1$s · %2$s', 'elev8-os'), $target->display_name, $role)); ?></span><small><?php esc_html_e('Email and notification delivery is suppressed for this preview request.', 'elev8-os'); ?></small></div>
            <nav><?php foreach ($links as $link) : ?><a href="<?php echo esc_url($link['url']); ?>"><?php echo esc_html($link['label']); ?></a><?php endforeach; ?></nav>
            <a class="elev8-preview-exit" href="<?php echo esc_url(Elev8_OS_Preview_Service::stop_url()); ?>"><?php esc_html_e('Exit Preview', 'elev8-os'); ?></a>
        </aside>
        <?php
    }

    public static function render_banner_fallback(): void { self::render_banner(); }

    public static function render_clean_preview_admin_styles(): void {
        if (!Elev8_OS_Preview_Service::is_clean_request()) { return; }
        ?>
        <style id="elev8-clean-preview-admin">
            #wpadminbar,#adminmenumain,#screen-meta-links,.update-nag,.notice:not(.elev8-preview-keep),.wrap>h1.wp-heading-inline+.page-title-action{display:none!important}
            html.wp-toolbar{padding-top:0!important}
            #wpcontent,#wpfooter{margin-left:0!important}
            #wpbody-content{padding-bottom:20px!important}
            body.wp-admin{background:#f4f3f7!important}
            .elev8-preview-banner{position:sticky!important;top:0!important}
        </style>
        <?php
    }
}

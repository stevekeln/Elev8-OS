<?php
/**
 * Universal Elev8 OS application shell.
 *
 * @package Elev8OS
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Elev8_OS_Application_Shell_Module {

    private static bool $frontend_rendered = false;

    public static function init(): void {
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_frontend']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin']);
        add_action('wp_body_open', [__CLASS__, 'render_frontend']);
        add_action('wp_footer', [__CLASS__, 'render_frontend_fallback'], 1);
        add_action('admin_notices', [__CLASS__, 'render_admin']);
        add_filter('body_class', [__CLASS__, 'frontend_body_class']);
        add_filter('admin_body_class', [__CLASS__, 'admin_body_class']);
    }

    public static function enqueue_frontend(): void {
        if (!self::should_render_frontend()) {
            return;
        }
        self::enqueue_assets();
    }

    public static function enqueue_admin(string $hook): void {
        if (!self::should_render_admin()) {
            return;
        }
        self::enqueue_assets();
    }

    private static function enqueue_assets(): void {
        wp_enqueue_style(
            'elev8-os-application-shell',
            ELEV8_OS_URL . 'assets/css/application-shell.css',
            [],
            ELEV8_OS_VERSION
        );
        wp_enqueue_script(
            'elev8-os-application-shell',
            ELEV8_OS_URL . 'assets/js/application-shell.js',
            [],
            ELEV8_OS_VERSION,
            true
        );
        wp_localize_script('elev8-os-application-shell', 'Elev8OSCommandPalette', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('elev8_os_command_palette'),
            'emptyMessage' => __('No matching Elev8 OS results.', 'elev8-os'),
            'errorMessage' => __('Search is temporarily unavailable.', 'elev8-os'),
        ]);
    }

    public static function render_frontend(): void {
        if (self::$frontend_rendered || !self::should_render_frontend()) {
            return;
        }

        self::$frontend_rendered = true;
        self::render('frontend');
    }

    /**
     * Some WordPress themes do not call wp_body_open(). Render once from the
     * footer as a compatibility fallback; the application-shell script moves
     * the shell to the top of the document before initializing it.
     */
    public static function render_frontend_fallback(): void {
        self::render_frontend();
    }

    public static function render_admin(): void {
        if (self::should_render_admin()) {
            self::render('admin');
        }
    }

    /** @param array<int,string> $classes */
    public static function frontend_body_class(array $classes): array {
        if (self::should_render_frontend()) {
            $classes[] = 'elev8-os-app-shell-active';
        }
        return $classes;
    }

    public static function admin_body_class(string $classes): string {
        return self::should_render_admin() ? $classes . ' elev8-os-app-shell-active' : $classes;
    }

    private static function should_render_frontend(): bool {
        if (is_admin() || !is_user_logged_in()) {
            return false;
        }

        $path = trim((string) wp_parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');

        if (class_exists('Elev8_OS_Portal_Page_Manager')) {
            foreach (Elev8_OS_Portal_Page_Manager::definitions() as $key => $definition) {
                if (Elev8_OS_Portal_Page_Manager::is_current_page((string) $key)) {
                    return true;
                }

                $slug = trim((string) ($definition['slug'] ?? ''), '/');
                if ($slug !== '' && ($path === $slug || substr($path, -strlen('/' . $slug)) === '/' . $slug)) {
                    return true;
                }
            }
        }

        $known = ['checkin', 'elev8-mobile-home', 'artist-dashboard'];
        foreach ($known as $slug) {
            if ($path === $slug || substr($path, -strlen('/' . $slug)) === '/' . $slug) {
                return true;
            }
        }

        return (bool) apply_filters('elev8_os_application_shell_frontend', false);
    }

    private static function should_render_admin(): bool {
        if (!is_admin() || !is_user_logged_in()) {
            return false;
        }
        $page = sanitize_key((string) ($_GET['page'] ?? ''));
        return $page !== '' && (strpos($page, 'elev8-') === 0 || strpos($page, 'elev8_os') === 0);
    }

    private static function render(string $context = 'frontend'): void {
        $user = class_exists('Elev8_OS_Preview_Service') ? Elev8_OS_Preview_Service::effective_user() : wp_get_current_user();
        if (!$user instanceof WP_User || $user->ID <= 0) {
            return;
        }

        $dashboard_url = self::dashboard_url($user);
        $attention = class_exists('Elev8_OS_Attention_Service')
            ? Elev8_OS_Attention_Service::summary($user)
            : ['total' => 0];
        $attention_count = max(0, (int) ($attention['total'] ?? 0));
        $role_label = self::role_label($user);
        $profile_url = class_exists('Elev8_OS_Public_Profile_Service') ? Elev8_OS_Public_Profile_Service::editor_url() : get_edit_profile_url($user->ID);
        $settings_url = get_edit_profile_url($user->ID);
        $notifications_url = $dashboard_url !== '' ? add_query_arg('elev8_view', 'attention', $dashboard_url) : $dashboard_url;
        $home_url = home_url('/');
        $help_url = (string) apply_filters('elev8_os_help_url', home_url('/contact/'));
        $resources_url = class_exists('Elev8_OS_Knowledge_Base_Module') ? Elev8_OS_Knowledge_Base_Module::url() : home_url('/elev8-resources/');
        $blueprint_url = class_exists('Elev8_OS_Business_Blueprint_Module') ? Elev8_OS_Business_Blueprint_Module::url() : home_url('/business-blueprint/');
        $logout_url = wp_logout_url($home_url);
        $display_name = trim((string) $user->display_name) ?: (string) $user->user_login;
        $initial = function_exists('mb_substr') ? mb_substr($display_name, 0, 1) : substr($display_name, 0, 1);
        ?>
        <div class="elev8-app-shell" data-elev8-app-shell data-elev8-shell-context="<?php echo esc_attr($context); ?>">
            <div class="elev8-app-shell__inner">
                <a class="elev8-app-shell__brand" href="<?php echo esc_url($dashboard_url); ?>" aria-label="<?php esc_attr_e('Open my Elev8 OS dashboard', 'elev8-os'); ?>">
                    <span class="elev8-app-shell__brand-mark" aria-hidden="true">8</span>
                    <span class="elev8-app-shell__brand-text">ELEV8 OS</span>
                </a>

                <nav class="elev8-app-shell__nav" aria-label="<?php esc_attr_e('Elev8 OS navigation', 'elev8-os'); ?>">
                    <a href="<?php echo esc_url($home_url); ?>"><?php esc_html_e('Elev8 Arts Home', 'elev8-os'); ?></a>
                    <a href="<?php echo esc_url($dashboard_url); ?>"><?php esc_html_e('My Dashboard', 'elev8-os'); ?></a>
                    <?php if (Elev8_OS_Access_Service::user_can('view_organization', $user) && class_exists('Elev8_OS_Organization_Module')) : ?>
                        <a href="<?php echo esc_url(Elev8_OS_Organization_Module::url()); ?>"><?php esc_html_e('Organization', 'elev8-os'); ?></a>
                    <?php endif; ?>
                    <?php if (Elev8_OS_Access_Service::user_can('view_work', $user)) : ?>
                        <a href="<?php echo esc_url(class_exists('Elev8_OS_Action_Center_Module') ? Elev8_OS_Action_Center_Module::url() : $dashboard_url); ?>"><?php esc_html_e('My Actions', 'elev8-os'); ?></a>
                    <?php endif; ?>
                    <?php if (Elev8_OS_Access_Service::user_can('manage_daily_operations', $user)) : ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=elev8-daily-operations&view=brief')); ?>"><?php esc_html_e('Manager Logs', 'elev8-os'); ?></a>
                    <?php endif; ?>
                    <?php if (Elev8_OS_Access_Service::user_can('view_business_memory', $user)) : ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=elev8-business-memory')); ?>"><?php esc_html_e('Business Memory', 'elev8-os'); ?></a>
                    <?php endif; ?>
                    <?php if (Elev8_OS_Access_Service::user_can('view_conversations', $user) && class_exists('Elev8_OS_Conversations_Module')) : ?>
                        <a href="<?php echo esc_url(Elev8_OS_Conversations_Module::url()); ?>"><?php esc_html_e('Conversations', 'elev8-os'); ?><?php $conversation_count = class_exists('Elev8_OS_Conversation_Service') ? Elev8_OS_Conversation_Service::unread_count($user->ID) : 0; if ($conversation_count > 0) : ?> <strong>(<?php echo esc_html((string) $conversation_count); ?>)</strong><?php endif; ?></a>
                    <?php endif; ?>
                    <a href="<?php echo esc_url($resources_url); ?>"><?php esc_html_e('Resources', 'elev8-os'); ?></a>
                    <?php if (user_can($user, 'manage_options')) : ?><a href="<?php echo esc_url($blueprint_url); ?>"><?php esc_html_e('Blueprint', 'elev8-os'); ?></a><?php endif; ?>
                </nav>

                <div class="elev8-app-shell__actions">
                    <?php if (class_exists('Elev8_OS_Preview_Service') && Elev8_OS_Preview_Service::can_preview()) : ?>
                        <a class="elev8-app-shell__preview-button" href="<?php echo esc_url(Elev8_OS_Preview_Service::preview_page_url()); ?>">👁 <span><?php esc_html_e('Preview', 'elev8-os'); ?></span></a>
                    <?php endif; ?>
                    <button class="elev8-app-shell__search-button" type="button" data-elev8-command-open aria-haspopup="dialog" aria-controls="elev8-command-palette">
                        <span aria-hidden="true">⌕</span>
                        <span><?php esc_html_e('Search', 'elev8-os'); ?></span>
                        <kbd>Ctrl K</kbd>
                    </button>
                    <a class="elev8-app-shell__attention" href="<?php echo esc_url($notifications_url); ?>" aria-label="<?php echo esc_attr(sprintf(__('Notifications: %d items', 'elev8-os'), $attention_count)); ?>">
                        <span aria-hidden="true">🔔</span>
                        <?php if ($attention_count > 0) : ?><span class="elev8-app-shell__badge"><?php echo esc_html((string) min(99, $attention_count)); ?></span><?php endif; ?>
                    </a>
                    <button class="elev8-app-shell__user-button" type="button" aria-expanded="false" aria-controls="elev8-app-user-menu">
                        <span class="elev8-app-shell__avatar" aria-hidden="true"><?php echo esc_html(strtoupper($initial)); ?></span>
                        <span class="elev8-app-shell__identity">
                            <strong><?php echo esc_html($display_name); ?></strong>
                            <small><?php echo esc_html($role_label); ?></small>
                        </span>
                        <span aria-hidden="true">▾</span>
                    </button>
                </div>
            </div>

            <div class="elev8-app-shell__menu" id="elev8-app-user-menu" hidden>
                <div class="elev8-app-shell__menu-head">
                    <strong><?php echo esc_html($display_name); ?></strong>
                    <span><?php echo esc_html($role_label); ?></span>
                </div>
                <a href="<?php echo esc_url($dashboard_url); ?>">🏠 <span><?php esc_html_e('My Dashboard', 'elev8-os'); ?></span></a>
                <a href="<?php echo esc_url($profile_url); ?>">👤 <span><?php esc_html_e('My Profile', 'elev8-os'); ?></span></a>
                <a href="<?php echo esc_url($notifications_url); ?>">🔔 <span><?php esc_html_e('Notifications', 'elev8-os'); ?></span><?php if ($attention_count > 0) : ?><b><?php echo esc_html((string) $attention_count); ?></b><?php endif; ?></a>
                <?php if (Elev8_OS_Access_Service::user_can('view_conversations', $user) && class_exists('Elev8_OS_Conversations_Module')) : ?><a href="<?php echo esc_url(Elev8_OS_Conversations_Module::url()); ?>">💬 <span><?php esc_html_e('Conversations', 'elev8-os'); ?></span><?php $conversation_count = class_exists('Elev8_OS_Conversation_Service') ? Elev8_OS_Conversation_Service::unread_count($user->ID) : 0; if ($conversation_count > 0) : ?><b><?php echo esc_html((string) $conversation_count); ?></b><?php endif; ?></a><?php endif; ?>
                <a href="<?php echo esc_url($resources_url); ?>">📚 <span><?php esc_html_e('Employee Guides', 'elev8-os'); ?></span></a>
                <?php if (user_can($user, 'manage_options')) : ?><a href="<?php echo esc_url($blueprint_url); ?>">🧭 <span><?php esc_html_e('Business Blueprint', 'elev8-os'); ?></span></a><?php endif; ?>
                <a href="<?php echo esc_url($settings_url); ?>">⚙️ <span><?php esc_html_e('Settings', 'elev8-os'); ?></span></a>
                <?php if (class_exists('Elev8_OS_Preview_Service') && Elev8_OS_Preview_Service::can_preview()) : ?><a href="<?php echo esc_url(Elev8_OS_Preview_Service::preview_page_url()); ?>">👁 <span><?php esc_html_e('Role Preview', 'elev8-os'); ?></span></a><?php endif; ?>
                <a href="<?php echo esc_url($help_url); ?>">❓ <span><?php esc_html_e('Help', 'elev8-os'); ?></span></a>
                <a href="<?php echo esc_url($home_url); ?>">🌐 <span><?php esc_html_e('Return to Elev8Arts.com', 'elev8-os'); ?></span></a>
                <a class="elev8-app-shell__logout" href="<?php echo esc_url($logout_url); ?>">🚪 <span><?php esc_html_e('Log Out', 'elev8-os'); ?></span></a>
            </div>
        </div>

            <div class="elev8-command-palette" id="elev8-command-palette" data-elev8-command-palette hidden>
                <button class="elev8-command-palette__backdrop" type="button" data-elev8-command-close aria-label="<?php esc_attr_e('Close search', 'elev8-os'); ?>"></button>
                <section class="elev8-command-palette__dialog" role="dialog" aria-modal="true" aria-labelledby="elev8-command-title">
                    <header>
                        <div>
                            <span aria-hidden="true">⌕</span>
                            <input id="elev8-command-input" data-elev8-command-input type="search" autocomplete="off" placeholder="<?php esc_attr_e('Search Elev8 OS or type a command…', 'elev8-os'); ?>" aria-label="<?php esc_attr_e('Search Elev8 OS', 'elev8-os'); ?>">
                        </div>
                        <button type="button" data-elev8-command-close><?php esc_html_e('Esc', 'elev8-os'); ?></button>
                    </header>
                    <h2 id="elev8-command-title" class="screen-reader-text"><?php esc_html_e('Elev8 OS Search and Command Palette', 'elev8-os'); ?></h2>
                    <div class="elev8-command-palette__status" data-elev8-command-status><?php esc_html_e('Quick actions', 'elev8-os'); ?></div>
                    <div class="elev8-command-palette__results" data-elev8-command-results role="listbox"></div>
                    <footer><span>↑↓ <?php esc_html_e('Navigate', 'elev8-os'); ?></span><span>Enter <?php esc_html_e('Open', 'elev8-os'); ?></span><span>Esc <?php esc_html_e('Close', 'elev8-os'); ?></span></footer>
                </section>
            </div>
        <?php
    }

    private static function dashboard_url(WP_User $user): string {
        if (class_exists('Elev8_OS_Workspace_Resolver_Service')) {
            return Elev8_OS_Workspace_Resolver_Service::destination($user, true);
        }
        return home_url('/artist-dashboard/');
    }

    private static function role_label(WP_User $user): string {
        if (class_exists('Elev8_OS_Workspace_Resolver_Service')) {
            return Elev8_OS_Workspace_Resolver_Service::role_label($user);
        }
        return __('Elev8 Team', 'elev8-os');
    }

}

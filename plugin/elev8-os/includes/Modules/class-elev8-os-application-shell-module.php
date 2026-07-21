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

    public static function init(): void {
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_frontend']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin']);
        add_action('wp_body_open', [__CLASS__, 'render_frontend']);
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
    }

    public static function render_frontend(): void {
        if (self::should_render_frontend()) {
            self::render();
        }
    }

    public static function render_admin(): void {
        if (self::should_render_admin()) {
            self::render();
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

        if (class_exists('Elev8_OS_Portal_Page_Manager')) {
            foreach (array_keys(Elev8_OS_Portal_Page_Manager::definitions()) as $key) {
                if (Elev8_OS_Portal_Page_Manager::is_current_page($key)) {
                    return true;
                }
            }
        }

        $path = trim((string) wp_parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
        $known = ['checkin', 'elev8-mobile-home'];
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

    private static function render(): void {
        $user = wp_get_current_user();
        if (!$user instanceof WP_User || $user->ID <= 0) {
            return;
        }

        $dashboard_url = self::dashboard_url($user);
        $attention = class_exists('Elev8_OS_Attention_Service')
            ? Elev8_OS_Attention_Service::summary($user)
            : ['total' => 0];
        $attention_count = max(0, (int) ($attention['total'] ?? 0));
        $role_label = self::role_label($user);
        $profile_url = get_edit_profile_url($user->ID);
        $settings_url = $profile_url;
        $notifications_url = $dashboard_url !== '' ? add_query_arg('elev8_view', 'attention', $dashboard_url) : $dashboard_url;
        $home_url = home_url('/');
        $help_url = (string) apply_filters('elev8_os_help_url', home_url('/contact/'));
        $logout_url = wp_logout_url($home_url);
        $display_name = trim((string) $user->display_name) ?: (string) $user->user_login;
        $initial = function_exists('mb_substr') ? mb_substr($display_name, 0, 1) : substr($display_name, 0, 1);
        ?>
        <div class="elev8-app-shell" data-elev8-app-shell>
            <div class="elev8-app-shell__inner">
                <a class="elev8-app-shell__brand" href="<?php echo esc_url($dashboard_url); ?>" aria-label="<?php esc_attr_e('Open my Elev8 OS dashboard', 'elev8-os'); ?>">
                    <span class="elev8-app-shell__brand-mark" aria-hidden="true">8</span>
                    <span class="elev8-app-shell__brand-text">ELEV8 OS</span>
                </a>

                <nav class="elev8-app-shell__nav" aria-label="<?php esc_attr_e('Elev8 OS navigation', 'elev8-os'); ?>">
                    <a href="<?php echo esc_url($home_url); ?>"><?php esc_html_e('Elev8 Arts Home', 'elev8-os'); ?></a>
                    <a href="<?php echo esc_url($dashboard_url); ?>"><?php esc_html_e('My Dashboard', 'elev8-os'); ?></a>
                    <?php if (Elev8_OS_Access_Service::user_can('view_work', $user)) : ?>
                        <a href="<?php echo esc_url(class_exists('Elev8_OS_Work_Module') ? Elev8_OS_Work_Module::my_url() : $dashboard_url); ?>"><?php esc_html_e('Work', 'elev8-os'); ?></a>
                    <?php endif; ?>
                    <?php if (Elev8_OS_Access_Service::user_can('view_business_memory', $user)) : ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=elev8-business-memory')); ?>"><?php esc_html_e('Business Memory', 'elev8-os'); ?></a>
                    <?php endif; ?>
                </nav>

                <div class="elev8-app-shell__actions">
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
                <a href="<?php echo esc_url($settings_url); ?>">⚙️ <span><?php esc_html_e('Settings', 'elev8-os'); ?></span></a>
                <a href="<?php echo esc_url($help_url); ?>">❓ <span><?php esc_html_e('Help', 'elev8-os'); ?></span></a>
                <a href="<?php echo esc_url($home_url); ?>">🌐 <span><?php esc_html_e('Return to Elev8Arts.com', 'elev8-os'); ?></span></a>
                <a class="elev8-app-shell__logout" href="<?php echo esc_url($logout_url); ?>">🚪 <span><?php esc_html_e('Log Out', 'elev8-os'); ?></span></a>
            </div>
        </div>
        <?php
    }

    private static function dashboard_url(WP_User $user): string {
        if (Elev8_OS_Access_Service::user_can('view_ceo_dashboard', $user)) {
            return admin_url('admin.php?page=elev8-ceo-dashboard');
        }
        if (class_exists('Elev8_OS_Portal_Page_Manager')) {
            return Elev8_OS_Portal_Page_Manager::get_url('dashboard');
        }
        return home_url('/artist-dashboard/');
    }

    private static function role_label(WP_User $user): string {
        if (Elev8_OS_Access_Service::user_can('view_ceo_dashboard', $user)) {
            return __('Owner', 'elev8-os');
        }
        if (Elev8_OS_Access_Service::user_can('view_manager_dashboard', $user)) {
            return Elev8_OS_Access_Service::user_can('view_glass_dashboard', $user)
                ? __('Glass Manager', 'elev8-os')
                : __('Shop Manager', 'elev8-os');
        }
        if (Elev8_OS_Access_Service::is_dj($user) && !Elev8_OS_Access_Service::user_can('view_manager_dashboard', $user)) {
            return __('Event Host', 'elev8-os');
        }
        if (Elev8_OS_Access_Service::user_can('view_volunteer_portal', $user)) {
            return __('Volunteer', 'elev8-os');
        }
        if (Elev8_OS_Access_Service::is_teacher($user)) {
            return __('Teacher', 'elev8-os');
        }
        if (Elev8_OS_Access_Service::user_can('view_artist_dashboard', $user)) {
            return __('Artist', 'elev8-os');
        }
        return __('Elev8 Team', 'elev8-os');
    }
}

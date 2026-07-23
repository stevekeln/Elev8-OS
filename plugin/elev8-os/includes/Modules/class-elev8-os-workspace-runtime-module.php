<?php
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Workspace_Runtime_Module {
    private const PAGE_SLUG = 'elev8-workspace';
    private const SHORTCODE = 'elev8_workspace_runtime';

    public static function init(): void {
        add_shortcode(self::SHORTCODE, [__CLASS__, 'shortcode']);
        add_action('init', [__CLASS__, 'ensure_page'], 30);
        add_action('wp_enqueue_scripts', [__CLASS__, 'assets']);
        add_action('wp_head', [__CLASS__, 'viewport'], 1);
        add_filter('body_class', [__CLASS__, 'body_class']);
    }

    public static function ensure_page(): void {
        $page = get_page_by_path(self::PAGE_SLUG);
        if (!$page && current_user_can('manage_options')) {
            wp_insert_post([
                'post_type' => 'page',
                'post_status' => 'publish',
                'post_title' => __('My Elev8 Workspace', 'elev8-os'),
                'post_name' => self::PAGE_SLUG,
                'post_content' => '[' . self::SHORTCODE . ']',
            ]);
        } elseif ($page && !has_shortcode((string) $page->post_content, self::SHORTCODE)) {
            wp_update_post(['ID' => $page->ID, 'post_content' => '[' . self::SHORTCODE . ']']);
        }
    }

    public static function url(): string {
        return home_url('/' . self::PAGE_SLUG . '/');
    }

    public static function is_current(): bool {
        return is_page(self::PAGE_SLUG);
    }

    /** @param array<int,string> $classes */
    public static function body_class(array $classes): array {
        if (self::is_current()) {
            $classes[] = 'elev8-workspace-active';
        }
        return $classes;
    }

    public static function viewport(): void {
        if (self::is_current()) {
            echo '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">' . "\n";
        }
    }

    public static function assets(): void {
        if (!self::is_current()) { return; }
        wp_enqueue_style(
            'elev8-os-workspace-platform',
            ELEV8_OS_URL . 'assets/css/workspace-platform.css',
            ['elev8-os-ui-components'],
            ELEV8_OS_VERSION
        );
    }

    public static function shortcode(): string {
        if (!is_user_logged_in()) {
            return '<p>' . esc_html__('Please sign in to open your Elev8 workspace.', 'elev8-os') . '</p>';
        }

        $user = class_exists('Elev8_OS_Preview_Service')
            ? Elev8_OS_Preview_Service::effective_user()
            : wp_get_current_user();
        $workspace = Elev8_OS_Workspace_Definition_Service::resolve($user);
        $actions_url = class_exists('Elev8_OS_Action_Center_Module')
            ? Elev8_OS_Action_Center_Module::url()
            : self::url();
        $messages_url = class_exists('Elev8_OS_Conversations_Module')
            ? Elev8_OS_Conversations_Module::url()
            : self::url();
        $today_url = class_exists('Elev8_OS_Proactive_Daily_Assistant_Module')
            ? Elev8_OS_Proactive_Daily_Assistant_Module::url()
            : self::url();
        $role_label = class_exists('Elev8_OS_Workspace_Resolver_Service')
            ? Elev8_OS_Workspace_Resolver_Service::role_label($user)
            : __('Elev8 Team', 'elev8-os');

        ob_start(); ?>
        <main class="elev8-workspace-runtime elev8-workspace-runtime--<?php echo esc_attr((string) ($workspace['shell'] ?? 'business')); ?>">
            <header class="elev8-workspace-runtime__header">
                <div class="elev8-workspace-runtime__heading">
                    <span><?php esc_html_e('ELEV8 OS', 'elev8-os'); ?></span>
                    <h1><?php echo esc_html((string) ($workspace['label'] ?? __('My Workspace', 'elev8-os'))); ?></h1>
                    <p><?php echo esc_html((string) ($workspace['description'] ?? '')); ?></p>
                </div>
                <div class="elev8-workspace-runtime__context" aria-label="<?php esc_attr_e('Current workspace context', 'elev8-os'); ?>">
                    <strong><?php echo esc_html($user instanceof WP_User ? $user->display_name : ''); ?></strong>
                    <span><?php echo esc_html($role_label); ?></span>
                </div>
            </header>

            <?php echo Elev8_OS_Responsive_Grid_Service::render($workspace, ['user' => $user]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

            <nav class="elev8-workspace-mobile-nav" aria-label="<?php esc_attr_e('Workspace navigation', 'elev8-os'); ?>">
                <a class="is-current" href="<?php echo esc_url(self::url()); ?>"><span aria-hidden="true">⌂</span><strong><?php esc_html_e('Home', 'elev8-os'); ?></strong></a>
                <a href="<?php echo esc_url($today_url); ?>"><span aria-hidden="true">☀</span><strong><?php esc_html_e('Today', 'elev8-os'); ?></strong></a>
                <a href="<?php echo esc_url($actions_url); ?>"><span aria-hidden="true">✓</span><strong><?php esc_html_e('Actions', 'elev8-os'); ?></strong></a>
                <a href="<?php echo esc_url($messages_url); ?>"><span aria-hidden="true">●</span><strong><?php esc_html_e('Messages', 'elev8-os'); ?></strong></a>
            </nav>
        </main>
        <?php
        return (string) ob_get_clean();
    }
}

<?php
/** Read-only Platform Kernel status for administrators. */
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Platform_Kernel_Module {
    private const PAGE_SLUG = 'elev8-platform-kernel';

    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'register_page'], 88);
    }

    public static function register_page(): void {
        add_submenu_page(
            'elev8-os',
            __('Platform Kernel', 'elev8-os'),
            __('Platform Kernel', 'elev8-os'),
            'manage_options',
            self::PAGE_SLUG,
            [__CLASS__, 'render_page']
        );
    }

    public static function render_page(): void {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('You do not have permission to view this page.', 'elev8-os')); }
        $snapshot = Elev8_OS_Platform_Kernel::snapshot();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Elev8 OS Platform Kernel', 'elev8-os'); ?></h1>
            <p><?php esc_html_e('Read-only view of the governed platform bootstrap. Business engines remain independent of presentation.', 'elev8-os'); ?></p>
            <div class="notice <?php echo $snapshot['healthy'] ? 'notice-success' : 'notice-error'; ?> inline"><p>
                <strong><?php echo esc_html($snapshot['healthy'] ? __('Kernel healthy', 'elev8-os') : __('Kernel needs attention', 'elev8-os')); ?></strong>
                <?php echo esc_html(sprintf(__(' — %1$d registered, %2$d booted, %3$d failed.', 'elev8-os'), $snapshot['registered'], $snapshot['booted'], $snapshot['failed'])); ?>
            </p></div>
            <table class="widefat striped"><thead><tr><th><?php esc_html_e('Component', 'elev8-os'); ?></th><th><?php esc_html_e('Type', 'elev8-os'); ?></th><th><?php esc_html_e('Contract', 'elev8-os'); ?></th><th><?php esc_html_e('Status', 'elev8-os'); ?></th></tr></thead><tbody>
            <?php foreach ($snapshot['components'] as $component) : ?>
                <tr><td><?php echo esc_html($component['id']); ?></td><td><?php echo esc_html($component['type']); ?></td><td><code><?php echo esc_html($component['class'] . '::' . $component['method']); ?></code></td><td><?php echo esc_html($component['status']); ?></td></tr>
            <?php endforeach; ?>
            </tbody></table>
        </div>
        <?php
    }
}

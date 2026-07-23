<?php
/** Shared responsive grid renderer for workspace widgets. */
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Responsive_Grid_Service {
    public static function render(array $workspace, array $context = []): string {
        $context['workspace'] = $workspace;
        $context['user'] = $context['user'] ?? wp_get_current_user();
        $widgets = array_values(array_filter(array_map('sanitize_key', (array) ($workspace['widgets'] ?? []))));
        ob_start();
        ?>
        <section class="elev8-workspace-grid" data-elev8-workspace="<?php echo esc_attr((string) ($workspace['id'] ?? 'business')); ?>">
            <?php foreach ($widgets as $widget_id) :
                $definition = Elev8_OS_Widget_Registry_Service::get($widget_id);
                if (!$definition) { continue; }
                $size = sanitize_key((string) ($definition['size'] ?? 'medium'));
                $html = Elev8_OS_Widget_Registry_Service::render($widget_id, $context);
                if ($html === '') { continue; }
                ?>
                <div class="elev8-workspace-grid__item elev8-workspace-grid__item--<?php echo esc_attr($size); ?>"><?php echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
            <?php endforeach; ?>
        </section>
        <?php
        return (string) ob_get_clean();
    }
}

<?php
if (!defined('ABSPATH')) { exit; }

/** Artist Success presentation layer for motivation, progress, journey, and focused action. */
final class Elev8_OS_Artist_Success_Module {
    public static function init(): void {}

    /** @param array<string,mixed> $snapshot */
    public static function render_artist(WP_User $user, array $snapshot): void {
        $success = Elev8_OS_Artist_Success_Service::build($user, $snapshot);
        $goal = (array) $success['weekly_goal'];
        $journey = (array) $success['journey'];
        ?>
        <section class="elev8-success-welcome">
            <div><p class="elev8-eyebrow"><?php esc_html_e('Artist Success', 'elev8-os'); ?></p><h2><?php echo esc_html($success['greeting'] . ', ' . $success['first_name']); ?></h2><p><?php echo esc_html((string) $success['headline']); ?></p></div>
            <div class="elev8-momentum elev8-momentum-<?php echo esc_attr((string) $success['momentum']['key']); ?>"><span class="dashicons dashicons-chart-line"></span><div><small><?php esc_html_e('Momentum', 'elev8-os'); ?></small><strong><?php echo esc_html((string) $success['momentum']['label']); ?></strong><p><?php echo esc_html((string) $success['momentum']['message']); ?></p></div></div>
        </section>

        <div class="elev8-success-grid">
            <section class="elev8-dashboard-panel elev8-weekly-goal"><p class="elev8-eyebrow"><?php esc_html_e("This week's goal", 'elev8-os'); ?></p><h2><?php echo esc_html(sprintf(__('%1$d of %2$d meaningful actions', 'elev8-os'), (int) $goal['current'], (int) $goal['target'])); ?></h2><progress max="100" value="<?php echo esc_attr((string) $goal['percent']); ?>"></progress><p><?php echo (int) $goal['remaining'] > 0 ? esc_html(sprintf(_n('Complete %d more verified action to reach your weekly goal.', 'Complete %d more verified actions to reach your weekly goal.', (int) $goal['remaining'], 'elev8-os'), (int) $goal['remaining'])) : esc_html__('Weekly goal complete. Keep building on the momentum.', 'elev8-os'); ?></p></section>
            <section class="elev8-dashboard-panel elev8-artist-journey"><p class="elev8-eyebrow"><?php esc_html_e('Artist Journey', 'elev8-os'); ?></p><h2><?php echo esc_html((string) $journey['current']); ?></h2><progress max="100" value="<?php echo esc_attr((string) $journey['progress']); ?>"></progress><p><?php echo !empty($journey['next']) ? esc_html(sprintf(__('Next level: %s', 'elev8-os'), $journey['next'])) : esc_html__('You have reached the highest current journey level.', 'elev8-os'); ?></p></section>
        </div>

        <section class="elev8-dashboard-panel elev8-thirty-plan"><div class="elev8-panel-heading"><div><p class="elev8-eyebrow"><?php esc_html_e('If you only have 30 minutes', 'elev8-os'); ?></p><h2><?php esc_html_e('Do these three things first', 'elev8-os'); ?></h2></div><strong><?php echo is_numeric($success['estimated_impact']) ? esc_html(sprintf(__('Estimated impact: $%s', 'elev8-os'), number_format_i18n((float) $success['estimated_impact'], 0))) : esc_html__('Dollar impact unavailable', 'elev8-os'); ?></strong></div><div class="elev8-thirty-list">
        <?php if (empty($success['thirty_minute_plan'])): ?><p><?php esc_html_e('No urgent verified actions were found. Review your Content Calendar for the next growth step.', 'elev8-os'); ?></p><?php else: foreach ($success['thirty_minute_plan'] as $index => $item): ?><article><span><?php echo esc_html((string) ($index + 1)); ?></span><div><h3><?php echo esc_html((string) $item['title']); ?></h3><p><?php echo esc_html((string) $item['reason']); ?></p></div><div><strong><?php echo is_numeric($item['estimated_value'] ?? null) ? esc_html('$' . number_format_i18n((float) $item['estimated_value'], 0)) : esc_html__('Impact available', 'elev8-os'); ?></strong><a class="button" href="<?php echo esc_url(Elev8_OS_Marketing_Launcher::url((string) $item['action'])); ?>"><?php esc_html_e('Start', 'elev8-os'); ?></a></div></article><?php endforeach; endif; ?></div></section>

        <?php if (!empty($success['wins'])): ?><section class="elev8-dashboard-panel elev8-celebrate-wins"><div class="elev8-panel-heading"><div><p class="elev8-eyebrow"><?php esc_html_e('Celebrate Wins', 'elev8-os'); ?></p><h2><?php esc_html_e('Progress worth noticing', 'elev8-os'); ?></h2></div></div><div><?php foreach ($success['wins'] as $win): ?><article><span class="dashicons dashicons-awards"></span><div><strong><?php echo esc_html((string) $win['title']); ?></strong><p><?php echo esc_html((string) $win['detail']); ?></p></div></article><?php endforeach; ?></div></section><?php endif;
    }
}

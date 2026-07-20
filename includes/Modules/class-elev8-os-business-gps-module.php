<?php
if (!defined('ABSPATH')) { exit; }

/** Presentation layer for Business GPS, timeline, opportunity engine, content calendar, and scheduling guidance. */
final class Elev8_OS_Business_GPS_Module {
    public static function init(): void {}

    /** @param array<string,mixed> $snapshot */
    public static function render_artist(WP_User $user,array $snapshot): void {
        $gps=Elev8_OS_Business_GPS_Service::build($user,$snapshot);
        $best=is_array($gps['highest_opportunity']??null)?$gps['highest_opportunity']:null;
        ?>
        <section class="elev8-gps-hero" aria-label="<?php esc_attr_e('Business GPS','elev8-os'); ?>">
            <div class="elev8-gps-heading"><div><p class="elev8-eyebrow"><?php esc_html_e('Business GPS','elev8-os'); ?></p><h2><?php esc_html_e('Your business today','elev8-os'); ?></h2><p><?php esc_html_e('One clear view of business health, opportunity, risk, and the best next step.','elev8-os'); ?></p></div><span class="elev8-gps-health"><strong><?php echo $gps['score']===null?esc_html__('Unavailable','elev8-os'):esc_html((string)$gps['score'].'%'); ?></strong><small><?php esc_html_e('Business health','elev8-os'); ?></small></span></div>
            <div class="elev8-gps-grid">
                <article><span><?php esc_html_e('Estimated revenue this month','elev8-os'); ?></span><strong><?php echo self::money($gps['revenue_month']); ?></strong><small><?php esc_html_e('Verified sales plus scheduled booking value when available.','elev8-os'); ?></small></article>
                <article><span><?php esc_html_e('Highest opportunity','elev8-os'); ?></span><strong><?php echo $best?esc_html((string)$best['title']):esc_html__('No verified opportunity','elev8-os'); ?></strong><small><?php echo $best?esc_html(self::value_label($best['estimated_value']??null)):esc_html__('You are caught up.','elev8-os'); ?></small></article>
                <article><span><?php esc_html_e('Biggest risk','elev8-os'); ?></span><strong><?php echo esc_html((string)($gps['biggest_risk']['title']??__('Unavailable','elev8-os'))); ?></strong><small><?php echo esc_html((string)($gps['biggest_risk']['detail']??'')); ?></small></article>
                <article class="is-action"><span><?php esc_html_e('Recommended first step','elev8-os'); ?></span><strong><?php echo $best?esc_html((string)$best['title']):esc_html__('Review your dashboard','elev8-os'); ?></strong><?php if($best): ?><a class="button elev8-dashboard-primary-action" href="<?php echo esc_url(Elev8_OS_Marketing_Launcher::url((string)$best['action'])); ?>"><?php esc_html_e('Do this now','elev8-os'); ?></a><?php endif; ?></article>
            </div>
        </section>

        <div class="elev8-gps-two-column">
            <section class="elev8-dashboard-panel"><div class="elev8-panel-heading"><div><p class="elev8-eyebrow"><?php esc_html_e('Opportunity Engine','elev8-os'); ?></p><h2><?php esc_html_e('Potential business value','elev8-os'); ?></h2></div><strong class="elev8-gps-total"><?php echo self::money($gps['opportunities']['known_total']??null); ?></strong></div>
                <div class="elev8-gps-opportunities"><?php foreach((array)($gps['opportunities']['items']??[]) as $item): ?><article><div><h3><?php echo esc_html((string)$item['title']); ?></h3><p><?php echo esc_html((string)$item['reason']); ?></p></div><div><strong><?php echo esc_html(self::value_label($item['estimated_value']??null)); ?></strong><a href="<?php echo esc_url(Elev8_OS_Marketing_Launcher::url((string)$item['action'])); ?>"><?php esc_html_e('Take action','elev8-os'); ?></a></div></article><?php endforeach; ?></div>
                <p class="description"><?php echo esc_html((string)($gps['opportunities']['estimate_note']??'')); ?></p>
            </section>
            <section class="elev8-dashboard-panel"><div class="elev8-panel-heading"><div><p class="elev8-eyebrow"><?php esc_html_e('Predictive Scheduling','elev8-os'); ?></p><h2><?php echo esc_html((string)($gps['scheduling']['title']??__('Unavailable','elev8-os'))); ?></h2></div><span class="elev8-confidence"><?php echo esc_html(ucfirst((string)($gps['scheduling']['confidence']??'unavailable'))); ?></span></div><p><?php echo esc_html((string)($gps['scheduling']['message']??'')); ?></p><p class="description"><?php esc_html_e('Elev8 OS will not invent a preferred day or time. Stronger recommendations appear only after enough verified Amelia history exists.','elev8-os'); ?></p></section>
        </div>

        <section class="elev8-dashboard-panel"><div class="elev8-panel-heading"><div><p class="elev8-eyebrow"><?php esc_html_e('Content Calendar','elev8-os'); ?></p><h2><?php esc_html_e('Your next seven days','elev8-os'); ?></h2><p class="elev8-panel-intro"><?php esc_html_e('A simple plan connected directly to Content Studio.','elev8-os'); ?></p></div></div><div class="elev8-content-week"><?php foreach((array)$gps['calendar'] as $day): ?><a href="<?php echo esc_url((string)$day['url']); ?>"><span><?php echo esc_html((string)$day['day']); ?></span><strong><?php echo esc_html((string)$day['title']); ?></strong><small><?php echo esc_html(ucwords(str_replace('_',' ',(string)$day['type']))); ?></small></a><?php endforeach; ?></div></section>

        <section class="elev8-dashboard-panel"><div class="elev8-panel-heading"><div><p class="elev8-eyebrow"><?php esc_html_e('Business Timeline','elev8-os'); ?></p><h2><?php esc_html_e('What is happening','elev8-os'); ?></h2></div></div><?php if(empty($gps['timeline'])): ?><div class="elev8-dashboard-empty"><span class="dashicons dashicons-backup"></span><h3><?php esc_html_e('No verified events yet','elev8-os'); ?></h3><p><?php esc_html_e('Sales, classes, artwork engagement, and achievements will appear here automatically.','elev8-os'); ?></p></div><?php else: ?><div class="elev8-business-timeline"><?php foreach((array)$gps['timeline'] as $event): ?><article><span class="dashicons dashicons-<?php echo esc_attr((string)$event['icon']); ?>"></span><div><h3><?php echo esc_html((string)$event['title']); ?></h3><p><?php echo esc_html((string)$event['detail']); ?></p><small><?php echo esc_html((string)$event['source']); ?><?php if(!empty($event['timestamp'])) echo ' · '.esc_html(wp_date(get_option('date_format'),(int)$event['timestamp'])); ?></small></div></article><?php endforeach; ?></div><?php endif; ?></section>
        <?php
    }

    private static function money($value): string { if(!is_numeric($value)) return __('Unavailable','elev8-os'); if(function_exists('wc_price')) return wp_strip_all_tags((string)wc_price((float)$value)); return '$'.number_format_i18n((float)$value,2); }
    private static function value_label($value): string { return is_numeric($value)?sprintf(__('Estimated %s','elev8-os'),self::money($value)):__('Impact available; dollar estimate unavailable','elev8-os'); }
}

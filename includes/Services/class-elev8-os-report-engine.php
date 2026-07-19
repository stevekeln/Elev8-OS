<?php
if (!defined('ABSPATH')) { exit; }

/** HTML-first reporting service. PDF can later consume the same report model. */
final class Elev8_OS_Report_Engine {
    public static function init(): void { add_action('admin_post_elev8_os_artist_report',[__CLASS__,'download']); }
    public static function report_url(): string { return wp_nonce_url(admin_url('admin-post.php?action=elev8_os_artist_report'),'elev8_os_artist_report'); }
    public static function download(): void {
        if(!is_user_logged_in() || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce']??'')),'elev8_os_artist_report')) wp_die(esc_html__('Invalid report request.','elev8-os'));
        $s=Elev8_OS_Artist_Business_Service::get_snapshot(wp_get_current_user());
        nocache_headers(); header('Content-Type: text/html; charset=UTF-8'); header('Content-Disposition: attachment; filename="elev8-artist-report-'.wp_date('Y-m').'.html"');
        echo self::render($s); exit;
    }
    /** @param array<string,mixed> $s */
    public static function render(array $s): string {
        $a=(array)$s['assets'];$c=(array)$s['classes'];$sales=(array)$s['sales'];$score=(array)$s['score'];$rec=(array)$s['recommendations'];
        ob_start();?><!doctype html><html><head><meta charset="utf-8"><title><?php esc_html_e('Monthly Artist Report','elev8-os');?></title><style>body{font:16px Arial,sans-serif;max-width:900px;margin:40px auto;color:#1d2327}h1{margin-bottom:4px}.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}.card{border:1px solid #ddd;border-radius:10px;padding:16px}.value{font-size:28px;font-weight:700}.muted{color:#666}li{margin:10px 0}@media print{body{margin:20px}}</style></head><body><h1><?php esc_html_e('Monthly Artist Report','elev8-os');?></h1><p class="muted"><?php echo esc_html(wp_date('F Y'));?> · Elev8 OS</p><div class="grid"><div class="card"><div class="value"><?php echo esc_html((string)($score['score']??0));?>/100</div><?php esc_html_e('Business Score','elev8-os');?></div><div class="card"><div class="value"><?php echo esc_html(number_format_i18n((int)($a['sold']??0)));?></div><?php esc_html_e('Artwork Sold','elev8-os');?></div><div class="card"><div class="value"><?php echo esc_html(number_format_i18n((int)($c['upcoming_count']??0)));?></div><?php esc_html_e('Upcoming Classes','elev8-os');?></div><div class="card"><div class="value"><?php echo isset($sales['revenue_month'])&&is_numeric($sales['revenue_month'])?esc_html('$'.number_format_i18n((float)$sales['revenue_month'],2)):esc_html__('Unavailable','elev8-os');?></div><?php esc_html_e('Monthly Revenue','elev8-os');?></div><div class="card"><div class="value"><?php echo esc_html(number_format_i18n((int)($a['qr_scans']??0)));?></div><?php esc_html_e('QR Scans','elev8-os');?></div><div class="card"><div class="value"><?php echo esc_html(number_format_i18n((int)($a['views']??0)));?></div><?php esc_html_e('Artwork Views','elev8-os');?></div></div><h2><?php esc_html_e('Recommended Next Steps','elev8-os');?></h2><ol><?php foreach($rec as $r):?><li><strong><?php echo esc_html((string)$r['title']);?></strong> — <?php echo esc_html((string)$r['message']);?></li><?php endforeach;?></ol></body></html><?php return (string)ob_get_clean();
    }
}

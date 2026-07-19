<?php
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Print_Service {
    const OPTION_SETTINGS = 'elev8_os_print_identity_settings';

    public static function get_settings(): array {
        $settings = get_option(self::OPTION_SETTINGS, []);
        $settings = is_array($settings) ? $settings : [];
        return wp_parse_args($settings, [
            'background_url' => '',
            'logo_url' => '',
            'theme' => 'lavender',
            'instruction' => 'Scan to meet the artist',
        ]);
    }

    public static function save_settings(array $input): void {
        $theme = sanitize_key((string)($input['theme'] ?? 'lavender'));
        if (!in_array($theme, ['lavender', 'minimal', 'ink'], true)) { $theme = 'lavender'; }
        update_option(self::OPTION_SETTINGS, [
            'background_url' => esc_url_raw((string)($input['background_url'] ?? '')),
            'logo_url' => esc_url_raw((string)($input['logo_url'] ?? '')),
            'theme' => $theme,
            'instruction' => sanitize_text_field((string)($input['instruction'] ?? 'Scan to meet the artist')),
        ], false);
    }

    public static function artist_card_url(string $artist_url, bool $two_up = false): string {
        return add_query_arg(['elev8_print' => 'artist-card', 'elev8_two_up' => $two_up ? '1' : '0'], $artist_url);
    }

    public static function qr_url(string $artist_url): string { return add_query_arg('elev8_print', 'qr', $artist_url); }

    public static function qr_image_url(string $target, int $size = 700): string {
        $size = max(160, min(1200, $size));
        return 'https://api.qrserver.com/v1/create-qr-code/?format=png&margin=20&size=' . $size . 'x' . $size . '&data=' . rawurlencode($target);
    }

    public static function render(array $artist, string $format, bool $legacy_two_up = false): void {
        $allowed = ['artist-card', 'artist-card-two', 'artist-card-5x7', 'artist-label-3x1', 'artist-label-3x1-sheet', 'qr'];
        if ($legacy_two_up && $format === 'artist-card') { $format = 'artist-card-two'; }
        if (!in_array($format, $allowed, true)) { $format = 'artist-card'; }

        $settings = self::get_settings();
        $profile_url = (string)$artist['profile_url'];
        $qr = self::qr_image_url($profile_url, $format === 'qr' ? 900 : 620);
        $bio = wp_trim_words(wp_strip_all_tags((string)$artist['bio']), $format === 'artist-card-5x7' ? 42 : 62, '…');
        $medium = trim((string)$artist['medium']);
        $instruction = trim((string)$settings['instruction']) ?: 'Scan to meet the artist';
        $logo = trim((string)$settings['logo_url']);
        $theme = (string)$settings['theme'];
        $title = $format === 'qr' ? 'Artist QR Code' : 'Artist Identity Display';
        $copies = $format === 'artist-card-two' ? 2 : ($format === 'artist-label-3x1-sheet' ? 16 : 1);

        status_header(200); nocache_headers(); header('X-Robots-Tag: noindex, nofollow', true);
        ?><!doctype html><html <?php language_attributes(); ?>><head>
<meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo esc_html($title . ' — ' . $artist['name']); ?></title>
<style>
:root{--ink:#1d1930;--purple:#68439a;--purple-dark:#422568;--lavender:#f2ebfb;--lavender-2:#e5d7f6;--paper:#fff}*{box-sizing:border-box}body{margin:0;background:#ececf1;color:var(--ink);font-family:Arial,Helvetica,sans-serif}.toolbar{position:sticky;top:0;z-index:10;display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:10px;padding:12px;background:#21172f;color:#fff}.toolbar button,.toolbar a{border:1px solid rgba(255,255,255,.45);border-radius:999px;background:#fff;color:#21172f;padding:10px 16px;font:700 14px Arial;text-decoration:none;cursor:pointer}.toolbar a{background:transparent;color:#fff}.toolbar .hint{font-size:12px;opacity:.8}.sheet{width:8.5in;min-height:11in;margin:24px auto;background:#fff;box-shadow:0 8px 30px rgba(0,0,0,.16);display:flex;flex-wrap:wrap;align-content:flex-start;justify-content:center}.display{position:relative;overflow:hidden;background:#fff;page-break-inside:avoid}.display:before{content:"";position:absolute;inset:0;background:linear-gradient(135deg,#fff 0 48%,var(--lavender) 100%)}.display:after{content:"";position:absolute;width:3.2in;height:3.2in;border-radius:50%;right:-1.3in;bottom:-1.6in;background:var(--lavender-2);opacity:.8}.display-inner{position:relative;z-index:1;height:100%;display:grid;grid-template-columns:1fr 2.08in;gap:.38in;padding:.42in .5in}.copy{display:flex;flex-direction:column;min-width:0}.brand{display:flex;align-items:center;gap:.12in;font-size:11px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:var(--purple-dark)}.brand img{max-width:1.35in;max-height:.48in;object-fit:contain}.eyebrow{margin:.16in 0 .05in;color:var(--purple);font-size:11px;font-weight:800;letter-spacing:.15em;text-transform:uppercase}.name{margin:0;font-size:29px;line-height:1.03;letter-spacing:.055em;text-transform:uppercase}.medium{margin:.07in 0 0;font-size:13px;font-weight:700;color:var(--purple)}.bio{margin:.25in 0 0;font-size:15.5px;line-height:1.48}.qrbox{align-self:center;padding:.16in;border-radius:.24in;background:linear-gradient(155deg,var(--purple-dark),#9b62b8);color:#fff;text-align:center;box-shadow:0 10px 25px rgba(66,37,104,.22)}.qrbox .top,.qrbox .bottom{margin:0;font-size:11px;font-weight:800;letter-spacing:.1em;text-transform:uppercase}.qrbox img{display:block;width:1.55in;height:1.55in;margin:.1in auto;padding:5px;background:#fff}.qrbox .bottom{font-size:10px}.artist-card{width:8.5in;height:5.5in;border-bottom:1px dashed #aaa}.artist-card:last-child{border-bottom:0}.artist-5x7{width:5in;height:7in;margin:.5in 0}.artist-5x7 .display-inner{grid-template-columns:1fr;padding:.42in}.artist-5x7 .qrbox{width:2.15in;justify-self:end;margin-top:auto}.artist-5x7 .name{font-size:27px}.artist-5x7 .bio{font-size:14px}.small-sheet{padding:.45in .65in;gap:.22in .25in;justify-content:flex-start}.small-label{width:3in;height:1in;border:1px solid #c8bfd6;border-radius:.08in}.small-label:after{width:1.2in;height:1.2in;right:-.55in;bottom:-.65in}.small-label .display-inner{grid-template-columns:1fr .76in;gap:.08in;padding:.1in .12in}.small-label .brand{font-size:5.8pt;letter-spacing:.06em}.small-label .brand img{max-width:.6in;max-height:.18in}.small-label .name{font-size:11pt;letter-spacing:.02em}.small-label .medium{font-size:6.7pt;margin:.025in 0 0}.small-label .qrbox{padding:.035in;border-radius:.06in;box-shadow:none}.small-label .qrbox .top{display:none}.small-label .qrbox img{width:.57in;height:.57in;margin:0 auto;padding:2px}.small-label .qrbox .bottom{font-size:4.8pt;letter-spacing:.02em}.qr-sheet{align-items:center;justify-content:center}.qr-only{width:5.5in;padding:.4in;border-radius:.3in;background:linear-gradient(145deg,#fff,var(--lavender));text-align:center}.qr-only h1{margin:0;font-size:27px;text-transform:uppercase;letter-spacing:.07em}.qr-only img{width:4in;height:4in;margin:.2in 0;padding:.12in;background:#fff}.qr-only p{font-weight:800;color:var(--purple-dark)}.theme-minimal .display:before,.theme-minimal .display:after{display:none}.theme-minimal .qrbox{background:#fff;color:var(--ink);border:2px solid var(--purple);box-shadow:none}.theme-ink{--purple:#000;--purple-dark:#000;--lavender:#fff;--lavender-2:#fff}.theme-ink .display:before,.theme-ink .display:after{display:none}.theme-ink .qrbox{background:#fff;color:#000;border:2px solid #000;box-shadow:none}@page{size:letter portrait;margin:0}@media print{*{-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important}body{background:#fff}.toolbar{display:none!important}.sheet{margin:0;box-shadow:none}.display{break-inside:avoid}.artist-5x7{margin:.5in auto}}@media(max-width:900px){.sheet{transform-origin:top left;transform:scale(calc((100vw - 20px)/816));margin:10px}.toolbar .hint{width:100%;text-align:center}}
</style></head><body class="theme-<?php echo esc_attr($theme); ?>">
<div class="toolbar"><button onclick="window.print()">Print</button><button onclick="window.print()">Download / Save PDF</button><a href="<?php echo esc_url($artist['canonical_url']); ?>">Back to Print Center</a><span class="hint">Choose “Save as PDF” in the print window to download.</span></div>
<?php if ($format === 'qr'): ?>
<div class="sheet qr-sheet"><section class="qr-only"><p class="eyebrow">Elev8 Artist</p><h1><?php echo esc_html($artist['name']); ?></h1><img src="<?php echo esc_url($qr); ?>" alt="QR code"><p><?php echo esc_html($instruction); ?></p></section></div>
<?php elseif (in_array($format, ['artist-label-3x1','artist-label-3x1-sheet'], true)): ?>
<div class="sheet small-sheet"><?php for($i=0;$i<$copies;$i++): ?><section class="display small-label"><div class="display-inner"><div class="copy"><div class="brand"><?php if($logo!==''): ?><img src="<?php echo esc_url($logo); ?>" alt="Elev8 Arts"><?php else: ?>Elev8 Artist<?php endif; ?></div><h1 class="name"><?php echo esc_html($artist['name']); ?></h1><?php if($medium!==''): ?><p class="medium"><?php echo esc_html($medium); ?></p><?php endif; ?></div><div class="qrbox"><p class="top">Scan</p><img src="<?php echo esc_url($qr); ?>" alt="QR code"><p class="bottom">Meet me</p></div></div></section><?php endfor; ?></div>
<?php else: ?><div class="sheet"><?php for($i=0;$i<$copies;$i++): ?><section class="display <?php echo $format === 'artist-card-5x7' ? 'artist-5x7' : 'artist-card'; ?>"><div class="display-inner"><div class="copy"><div class="brand"><?php if($logo!==''): ?><img src="<?php echo esc_url($logo); ?>" alt="Elev8 Arts"><?php else: ?>Elev8 Artist<?php endif; ?></div><p class="eyebrow">Meet the artist</p><h1 class="name"><?php echo esc_html($artist['name']); ?></h1><?php if($medium!==''): ?><p class="medium"><?php echo esc_html($medium); ?></p><?php endif; ?><?php if($bio!==''): ?><p class="bio"><?php echo esc_html($bio); ?></p><?php endif; ?></div><div class="qrbox"><p class="top">Scan to learn</p><img src="<?php echo esc_url($qr); ?>" alt="QR code"><p class="bottom">More about me</p></div></div></section><?php endfor; ?></div><?php endif; ?>
</body></html><?php exit;
    }

    public static function render_artwork(array $asset, string $artist_name, string $format, string $back_url): void {
        $allowed = ['artwork-label','artwork-label-two','artwork-label-small','artwork-label-small-sheet'];
        if (!in_array($format, $allowed, true)) { $format = 'artwork-label'; }
        $target = Elev8_OS_Asset_Service::get_public_url($asset, true);
        $qr = self::qr_image_url($target, 700);
        $title = trim((string)($asset['title'] ?? 'Artwork'));
        $price = $asset['price'] === null ? '' : ('$' . number_format_i18n((float)$asset['price'], 2));
        $small = in_array($format, ['artwork-label-small','artwork-label-small-sheet'], true);
        $copies = $format === 'artwork-label-two' ? 2 : ($format === 'artwork-label-small-sheet' ? 16 : 1);
        status_header(200); nocache_headers(); header('X-Robots-Tag: noindex, nofollow', true);
        ?><!doctype html><html <?php language_attributes(); ?>><head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?php echo esc_html('Artwork Label — '.$title); ?></title><style>
*{box-sizing:border-box}body{margin:0;background:#ececf1;color:#1d1930;font-family:Arial,Helvetica,sans-serif}.toolbar{position:sticky;top:0;z-index:5;display:flex;justify-content:center;gap:10px;padding:12px;background:#21172f}.toolbar button,.toolbar a{border:1px solid #fff;border-radius:999px;background:#fff;color:#21172f;padding:10px 15px;font:700 14px Arial;text-decoration:none}.toolbar a{background:transparent;color:#fff}.sheet{width:8.5in;min-height:11in;margin:24px auto;padding:.5in;display:flex;flex-wrap:wrap;align-content:flex-start;justify-content:center;gap:.3in;background:#fff;box-shadow:0 8px 28px rgba(0,0,0,.16)}.label{position:relative;width:3in;height:3in;overflow:hidden;border:1px solid #c8bfd6;background:linear-gradient(145deg,#fff,#f2ebfb);page-break-inside:avoid}.inner{height:100%;display:grid;grid-template-columns:1fr 1.08in;gap:.12in;padding:.2in}.copy{display:flex;flex-direction:column;min-width:0}.eyebrow{margin:0 0 .06in;color:#68439a;font-size:7.5pt;font-weight:800;letter-spacing:.11em;text-transform:uppercase}.title{margin:0;font-size:15pt;line-height:1.04;overflow-wrap:anywhere}.artist{margin:.07in 0 0;font-size:9pt}.price{margin:.1in 0 0;font-size:13pt;font-weight:800}.qr{align-self:center;text-align:center;padding:.06in;border-radius:.1in;background:#68439a;color:#fff}.qr img{display:block;width:.92in;height:.92in;padding:3px;background:#fff}.scan{margin:4px 0 0;font-size:6.5pt;font-weight:800}.label.small{width:3in;height:1in}.small .inner{grid-template-columns:1fr .7in;padding:.09in .11in;gap:.06in}.small .eyebrow{font-size:5.5pt;margin:0 0 .02in}.small .title{font-size:9.5pt}.small .artist{font-size:6.3pt;margin:.02in 0 0}.small .price{font-size:8pt;margin:.025in 0 0}.small .qr{padding:.025in}.small .qr img{width:.55in;height:.55in;padding:2px}.small .scan{font-size:4.7pt;margin:1px 0 0}@page{size:letter portrait;margin:0}@media print{*{-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important}body{background:#fff}.toolbar{display:none}.sheet{margin:0;box-shadow:none}.label{break-inside:avoid}}@media(max-width:900px){.sheet{transform-origin:top left;transform:scale(calc((100vw - 20px)/816));margin:10px}}
</style></head><body><div class="toolbar"><button onclick="window.print()">Print</button><button onclick="window.print()">Download / Save PDF</button><a href="<?php echo esc_url($back_url); ?>">Back to Print Center</a></div><div class="sheet"><?php for($i=0;$i<$copies;$i++): ?><section class="label<?php echo $small?' small':''; ?>"><div class="inner"><div class="copy"><p class="eyebrow">Elev8 Arts</p><h1 class="title"><?php echo esc_html($title); ?></h1><p class="artist">by <strong><?php echo esc_html($artist_name); ?></strong></p><?php if($price!==''): ?><p class="price"><?php echo esc_html($price); ?></p><?php endif; ?></div><div class="qr"><img src="<?php echo esc_url($qr); ?>" alt="QR code"><p class="scan">Scan for story</p></div></div></section><?php endfor; ?></div></body></html><?php exit;
    }
}

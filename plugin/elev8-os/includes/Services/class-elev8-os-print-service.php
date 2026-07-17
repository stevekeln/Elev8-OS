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
            'theme' => 'classic',
            'instruction' => 'Scan to learn more about this artist',
        ]);
    }

    public static function save_settings(array $input): void {
        $theme = sanitize_key((string)($input['theme'] ?? 'classic'));
        if (!in_array($theme, ['classic', 'minimal', 'ink'], true)) { $theme = 'classic'; }
        update_option(self::OPTION_SETTINGS, [
            'background_url' => esc_url_raw((string)($input['background_url'] ?? '')),
            'logo_url' => esc_url_raw((string)($input['logo_url'] ?? '')),
            'theme' => $theme,
            'instruction' => sanitize_text_field((string)($input['instruction'] ?? 'Scan to learn more about this artist')),
        ], false);
    }

    public static function artist_card_url(string $artist_url, bool $two_up = false): string {
        return add_query_arg(['elev8_print' => 'artist-card', 'elev8_two_up' => $two_up ? '1' : '0'], $artist_url);
    }

    public static function qr_url(string $artist_url): string {
        return add_query_arg('elev8_print', 'qr', $artist_url);
    }

    public static function qr_image_url(string $target, int $size = 700): string {
        $size = max(160, min(1200, $size));
        return 'https://api.qrserver.com/v1/create-qr-code/?format=png&margin=20&size=' . $size . 'x' . $size . '&data=' . rawurlencode($target);
    }

    public static function render(array $artist, string $mode, bool $two_up = false): void {
        $settings = self::get_settings();
        $mode = $mode === 'qr' ? 'qr' : 'artist-card';
        $title = $mode === 'qr' ? 'Artist QR Code' : 'Artist Display Card';
        $copies = ($mode === 'artist-card' && $two_up) ? 2 : 1;
        $background = trim((string)$settings['background_url']);
        $logo = trim((string)$settings['logo_url']);
        $theme = (string)$settings['theme'];
        $profile_url = (string)$artist['profile_url'];
        $qr = self::qr_image_url($profile_url, $mode === 'qr' ? 900 : 620);
        $bio = wp_trim_words(wp_strip_all_tags((string)$artist['bio']), 62, '…');
        $medium = trim((string)$artist['medium']);
        $instruction = trim((string)$settings['instruction']);
        if ($instruction === '') { $instruction = 'Scan to learn more about this artist'; }

        status_header(200);
        nocache_headers();
        header('X-Robots-Tag: noindex, nofollow', true);
        ?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo esc_html($title . ' — ' . $artist['name']); ?></title>
<style>
:root{--ink:#17212b;--accent:#16789a;--paper:#fff}*{box-sizing:border-box}body{margin:0;background:#eef1f4;color:var(--ink);font-family:Arial,Helvetica,sans-serif}.toolbar{position:sticky;top:0;z-index:5;display:flex;align-items:center;justify-content:center;gap:10px;padding:12px;background:#17212b;color:#fff}.toolbar button,.toolbar a{border:1px solid rgba(255,255,255,.45);border-radius:7px;background:#fff;color:#17212b;padding:10px 15px;font:600 14px Arial;text-decoration:none;cursor:pointer}.toolbar .secondary{background:transparent;color:#fff}.hint{font-size:12px;opacity:.8}.sheet{width:8.5in;min-height:11in;margin:24px auto;padding:0;background:#fff;box-shadow:0 8px 28px rgba(0,0,0,.16);display:flex;flex-direction:column;justify-content:flex-start}.card{position:relative;width:8.5in;height:5.5in;overflow:hidden;background:var(--paper);page-break-inside:avoid;border-bottom:1px dashed #b9c0c7}.card:last-child{border-bottom:0}.card-bg{position:absolute;inset:0;opacity:.13;background-image:radial-gradient(circle at 15% 25%,#16789a 0 7px,transparent 8px),radial-gradient(circle at 28% 40%,#7b3fa0 0 5px,transparent 6px),radial-gradient(circle at 44% 18%,#16789a 0 9px,transparent 10px);background-size:95px 95px}.has-image .card-bg{opacity:.18;background-size:cover;background-position:center}.card-inner{position:relative;z-index:1;height:100%;display:grid;grid-template-columns:1fr 2.1in;gap:.35in;padding:.45in .5in;background:linear-gradient(90deg,rgba(255,255,255,.96),rgba(255,255,255,.87))}.identity{display:grid;grid-template-columns:1.15in 1fr;gap:.25in;align-items:center}.portrait{width:1.15in;height:1.15in;border-radius:50%;object-fit:cover;border:4px solid #fff;box-shadow:0 2px 12px rgba(0,0,0,.18)}.portrait-placeholder{display:flex;align-items:center;justify-content:center;background:#e8edf1;font-size:10px;text-align:center}.name{margin:0;font-size:28px;line-height:1.05;letter-spacing:.08em;text-transform:uppercase}.medium{margin:7px 0 0;color:var(--accent);font-size:14px;font-weight:700;letter-spacing:.09em;text-transform:uppercase}.bio{margin:.28in 0 0;font-size:16px;line-height:1.48}.brand{display:flex;align-items:center;gap:10px;margin-top:auto;padding-top:.15in;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase}.brand img{max-width:1.45in;max-height:.42in;object-fit:contain}.qr-column{display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center}.qr-column img{width:1.85in;height:1.85in;background:#fff;padding:6px}.scan{margin:12px 0 0;font-size:13px;font-weight:700;line-height:1.35}.url{margin:8px 0 0;max-width:2in;font-size:9px;line-height:1.25;word-break:break-word;color:#5a6570}.theme-minimal .card-bg,.theme-ink .card-bg{display:none}.theme-minimal .card-inner{background:#fff}.theme-ink{--ink:#000;--accent:#000}.theme-ink .card-inner{background:#fff}.qr-sheet{align-items:center;justify-content:center}.qr-only{width:5.5in;min-height:5.5in;padding:.45in;text-align:center;background:#fff}.qr-only h1{margin:0 0 .12in;font-size:25px;letter-spacing:.08em;text-transform:uppercase}.qr-only .qr-main{width:4in;height:4in}.qr-only p{margin:.1in 0 0}.cut-note{display:none}
@page{size:letter portrait;margin:0}@media print{body{background:#fff}.toolbar{display:none!important}.sheet{margin:0;width:8.5in;min-height:11in;box-shadow:none}.card{break-inside:avoid}.cut-note{display:block;position:absolute;right:.12in;bottom:.05in;font-size:7pt;color:#888}.qr-sheet{justify-content:flex-start;padding-top:2.5in}}
@media(max-width:900px){.sheet{transform-origin:top left;transform:scale(calc((100vw - 20px) / 816));margin:10px;width:8.5in}.toolbar{flex-wrap:wrap}.hint{width:100%;text-align:center}}
</style>
</head>
<body class="theme-<?php echo esc_attr($theme); ?>">
<div class="toolbar"><button type="button" onclick="window.print()">Print</button><button type="button" onclick="window.print()">Download / Save PDF</button><?php if($mode==='artist-card'): ?><a class="secondary" href="<?php echo esc_url(self::artist_card_url((string)$artist['canonical_url'], !$two_up)); ?>"><?php echo $two_up ? 'Single card' : 'Two per sheet'; ?></a><?php endif; ?><a class="secondary" href="<?php echo esc_url($artist['canonical_url']); ?>">Back to profile</a><span class="hint">For PDF, choose “Save as PDF” in the print window.</span></div>
<?php if($mode==='qr'): ?>
<div class="sheet qr-sheet"><section class="qr-only"><h1><?php echo esc_html($artist['name']); ?></h1><img class="qr-main" src="<?php echo esc_url($qr); ?>" alt="QR code"><p><strong><?php echo esc_html($instruction); ?></strong></p><p class="url"><?php echo esc_html($profile_url); ?></p></section></div>
<?php else: ?>
<div class="sheet<?php echo $background !== '' ? ' has-image' : ''; ?>">
<?php for($copy=0;$copy<$copies;$copy++): ?>
<section class="card"><div class="card-bg"<?php echo $background !== '' ? ' style="background-image:url(' . esc_url($background) . ')"' : ''; ?>></div><div class="card-inner"><div style="display:flex;flex-direction:column"><div class="identity"><?php if($artist['photo']!==''): ?><img class="portrait" src="<?php echo esc_url($artist['photo']); ?>" alt=""><?php else: ?><div class="portrait portrait-placeholder">Artist photo</div><?php endif; ?><div><h1 class="name"><?php echo esc_html($artist['name']); ?></h1><?php if($medium!==''): ?><p class="medium"><?php echo esc_html($medium); ?></p><?php endif; ?></div></div><?php if($bio!==''): ?><p class="bio"><?php echo esc_html($bio); ?></p><?php endif; ?><div class="brand"><?php if($logo!==''): ?><img src="<?php echo esc_url($logo); ?>" alt="Elev8 Arts"><?php else: ?><span>Elev8 Arts</span><?php endif; ?></div></div><div class="qr-column"><img src="<?php echo esc_url($qr); ?>" alt="QR code"><p class="scan"><?php echo esc_html($instruction); ?></p><p class="url"><?php echo esc_html($profile_url); ?></p></div></div><?php if($two_up): ?><span class="cut-note">Cut on center line</span><?php endif; ?></section>
<?php endfor; ?>
</div>
<?php endif; ?>
</body></html><?php
        exit;
    }
}

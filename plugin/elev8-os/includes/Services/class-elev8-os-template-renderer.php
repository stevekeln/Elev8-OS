<?php
if (!defined('ABSPATH')) { exit; }

/** Channel-neutral template resolver plus the universal branded email layout. */
final class Elev8_OS_Template_Renderer {
    /** @param array<string,mixed> $template @param array<string,string> $variables */
    public static function resolve(array $template, array $variables): array {
        $resolved = $template;
        foreach (['subject','headline','body','cta_label','cta_url'] as $field) {
            $resolved[$field] = self::replace((string) ($template[$field] ?? ''), $variables);
        }
        return $resolved;
    }

    /** @param array<string,mixed> $template @param array<string,string> $variables */
    public static function email_html(array $template, array $variables = []): string {
        $content = self::resolve($template, $variables);
        $brand = Elev8_OS_Brand_Service::get();
        $logo = Elev8_OS_Brand_Service::logo_url();
        $headline = (string) ($content['headline'] ?? '');
        $body = wpautop(wp_kses_post((string) ($content['body'] ?? '')));
        $cta_label = (string) ($content['cta_label'] ?? '');
        $cta_url = esc_url((string) ($content['cta_url'] ?? ''));
        ob_start(); ?>
<!doctype html><html><body style="margin:0;background:<?php echo esc_attr((string)$brand['background_color']); ?>;font-family:Arial,Helvetica,sans-serif;color:<?php echo esc_attr((string)$brand['text_color']); ?>">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr><td align="center" style="padding:28px 14px"><table role="presentation" width="100%" style="max-width:640px;background:#fff;border-radius:14px;overflow:hidden"><tr><td style="padding:24px 30px;background:<?php echo esc_attr((string)$brand['secondary_color']); ?>;color:#fff"><?php if($logo!==''): ?><img src="<?php echo esc_url($logo); ?>" alt="<?php echo esc_attr((string)$brand['brand_name']); ?>" style="max-width:180px;max-height:64px"><?php else: ?><strong style="font-size:22px"><?php echo esc_html((string)$brand['brand_name']); ?></strong><?php endif; ?></td></tr><tr><td style="padding:34px 30px"><?php if($headline!==''): ?><h1 style="margin:0 0 18px;font-size:30px;line-height:1.15"><?php echo esc_html($headline); ?></h1><?php endif; ?><div style="font-size:16px;line-height:1.65"><?php echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div><?php if($cta_label!==''&&$cta_url!==''): ?><p style="margin:28px 0 0"><a href="<?php echo esc_url($cta_url); ?>" style="display:inline-block;padding:13px 22px;border-radius:7px;background:<?php echo esc_attr((string)$brand['primary_color']); ?>;color:<?php echo esc_attr((string)$brand['button_text_color']); ?>;text-decoration:none;font-weight:700"><?php echo esc_html($cta_label); ?></a></p><?php endif; ?></td></tr><tr><td style="padding:18px 30px;background:#f6f7f7;color:#646970;font-size:12px;text-align:center"><?php echo esc_html((string)$brand['footer_text']); ?></td></tr></table></td></tr></table></body></html>
        <?php return (string) ob_get_clean();
    }

    /** @param array<string,string> $variables */
    private static function replace(string $value, array $variables): string {
        if ($value === '') { return ''; }
        $pairs = [];
        foreach ($variables as $key => $replacement) { $pairs['{{' . sanitize_key($key) . '}}'] = (string) $replacement; }
        return strtr($value, $pairs);
    }
}

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
        $goal = sanitize_key((string) ($content['goal'] ?? 'custom'));
        $include_classes = !isset($content['include_upcoming_classes']) || !empty($content['include_upcoming_classes']);
        $include_events = !isset($content['include_events']) || !empty($content['include_events']);
        $include_artist = !isset($content['include_artist_profile']) || !empty($content['include_artist_profile']);
        $context = self::contextual_section($goal, $brand, $include_classes, $include_events, $include_artist);
        $socials = array_filter([
            'Facebook' => esc_url((string) $brand['facebook_url']),
            'Instagram' => esc_url((string) $brand['instagram_url']),
            'YouTube' => esc_url((string) $brand['youtube_url']),
        ]);
        ob_start(); ?>
<!doctype html>
<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:<?php echo esc_attr((string)$brand['background_color']); ?>;font-family:Arial,Helvetica,sans-serif;color:<?php echo esc_attr((string)$brand['text_color']); ?>;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:<?php echo esc_attr((string)$brand['background_color']); ?>;"><tr><td align="center" style="padding:30px 12px;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:660px;background:#ffffff;border-radius:18px;overflow:hidden;box-shadow:0 12px 34px rgba(23,33,43,.10);">
<tr><td align="center" style="padding:28px 30px 24px;background:<?php echo esc_attr((string)$brand['secondary_color']); ?>;color:#ffffff;border-bottom:5px solid <?php echo esc_attr((string)$brand['primary_color']); ?>;">
<?php if($logo!==''): ?><a href="<?php echo esc_url((string)$brand['website_url']); ?>" style="text-decoration:none;"><img src="<?php echo esc_url($logo); ?>" alt="<?php echo esc_attr((string)$brand['brand_name']); ?>" style="display:block;max-width:230px;max-height:90px;width:auto;height:auto;border:0;"></a><?php else: ?><a href="<?php echo esc_url((string)$brand['website_url']); ?>" style="color:#ffffff;text-decoration:none;font-size:26px;font-weight:800;letter-spacing:.5px;"><?php echo esc_html((string)$brand['brand_name']); ?></a><?php endif; ?>
<?php if((string)$brand['tagline']!==''): ?><div style="margin-top:10px;color:#dce3e8;font-size:12px;letter-spacing:1.8px;text-transform:uppercase;"><?php echo esc_html((string)$brand['tagline']); ?></div><?php endif; ?>
</td></tr>
<tr><td style="padding:40px 38px 34px;">
<div style="width:34px;height:5px;background:<?php echo esc_attr((string)$brand['primary_color']); ?>;border-radius:99px;margin:0 0 16px;"></div>
<?php if($headline!==''): ?><h1 style="margin:0 0 20px;color:<?php echo esc_attr((string)$brand['text_color']); ?>;font-size:32px;line-height:1.17;letter-spacing:-.4px;"><?php echo esc_html($headline); ?></h1><?php endif; ?>
<div style="font-size:16px;line-height:1.72;color:#263746;"><?php echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
<?php if($cta_label!==''&&$cta_url!==''): ?><table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:28px 0 4px;"><tr><td style="border-radius:9px;background:<?php echo esc_attr((string)$brand['primary_color']); ?>;"><a href="<?php echo esc_url($cta_url); ?>" style="display:inline-block;padding:15px 25px;color:<?php echo esc_attr((string)$brand['button_text_color']); ?>;text-decoration:none;font-size:16px;font-weight:800;line-height:1;"> <?php echo esc_html($cta_label); ?> </a></td></tr></table><?php endif; ?>
</td></tr>
<?php if($context): ?><tr><td style="padding:0 30px 30px;"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f7f9fa;border:1px solid #e5e9ec;border-radius:13px;"><tr><td style="padding:24px 25px;"><div style="font-size:19px;font-weight:800;margin-bottom:8px;color:<?php echo esc_attr((string)$brand['secondary_color']); ?>;"><?php echo esc_html($context['heading']); ?></div><div style="font-size:14px;line-height:1.6;color:#536474;margin-bottom:18px;"><?php echo esc_html($context['text']); ?></div><a href="<?php echo esc_url($context['url']); ?>" style="color:<?php echo esc_attr((string)$brand['primary_color']); ?>;font-weight:800;text-decoration:none;"><?php echo esc_html($context['label']); ?> &rarr;</a></td></tr></table></td></tr><?php endif; ?>
<?php if($include_classes || $include_events): ?><tr><td style="padding:0 30px 30px;"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"><tr>
<?php if($include_classes): ?><td width="50%" valign="top" style="padding:0 7px 0 0;"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border:1px solid #e5e9ec;border-radius:12px;"><tr><td style="padding:21px;"><div style="font-size:12px;font-weight:800;letter-spacing:1px;text-transform:uppercase;color:<?php echo esc_attr((string)$brand['primary_color']); ?>;">Create</div><div style="font-size:18px;font-weight:800;margin:7px 0;color:<?php echo esc_attr((string)$brand['secondary_color']); ?>;">Book a Class</div><div style="font-size:13px;line-height:1.5;color:#63727f;margin-bottom:14px;">Make something memorable with an Elev8 artist.</div><a href="<?php echo esc_url((string)$brand['class_booking_url']); ?>" style="font-size:14px;font-weight:800;color:<?php echo esc_attr((string)$brand['primary_color']); ?>;text-decoration:none;">Explore classes &rarr;</a></td></tr></table></td><?php endif; ?>
<?php if($include_events): ?><td width="50%" valign="top" style="padding:0 0 0 7px;"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border:1px solid #e5e9ec;border-radius:12px;"><tr><td style="padding:21px;"><div style="font-size:12px;font-weight:800;letter-spacing:1px;text-transform:uppercase;color:<?php echo esc_attr((string)$brand['primary_color']); ?>;">Connect</div><div style="font-size:18px;font-weight:800;margin:7px 0;color:<?php echo esc_attr((string)$brand['secondary_color']); ?>;">Upcoming Events</div><div style="font-size:13px;line-height:1.5;color:#63727f;margin-bottom:14px;">See Art Walks, live events, workshops, and more.</div><a href="<?php echo esc_url((string)$brand['events_url']); ?>" style="font-size:14px;font-weight:800;color:<?php echo esc_attr((string)$brand['primary_color']); ?>;text-decoration:none;">View all events &rarr;</a></td></tr></table></td><?php endif; ?>
</tr></table></td></tr><?php endif; ?>
<tr><td style="padding:25px 34px;background:#fff7ef;border-top:1px solid #f3dfcc;"><div style="font-size:17px;font-weight:800;color:<?php echo esc_attr((string)$brand['secondary_color']); ?>;margin-bottom:6px;">&#10084; <?php echo esc_html((string)$brand['mission_heading']); ?></div><div style="font-size:13px;line-height:1.6;color:#67594f;"><?php echo esc_html((string)$brand['mission_text']); ?></div></td></tr>
<tr><td align="center" style="padding:26px 30px;background:<?php echo esc_attr((string)$brand['secondary_color']); ?>;color:#dce3e8;">
<div style="font-size:18px;font-weight:800;color:#ffffff;margin-bottom:6px;"><?php echo esc_html((string)$brand['tagline']); ?></div>
<div style="font-size:13px;line-height:1.6;margin-bottom:11px;"><?php echo esc_html((string)$brand['brand_name']); ?><?php if((string)$brand['address_text']!==''): ?> &bull; <?php echo esc_html((string)$brand['address_text']); ?><?php endif; ?></div>
<?php if($socials): ?><div style="font-size:13px;margin-bottom:13px;"><?php $i=0; foreach($socials as $label=>$url): if($i++): ?> &nbsp;&bull;&nbsp; <?php endif; ?><a href="<?php echo esc_url($url); ?>" style="color:#ffffff;text-decoration:none;font-weight:700;"><?php echo esc_html($label); ?></a><?php endforeach; ?></div><?php endif; ?>
<div style="font-size:11px;color:#9eabb5;"><?php echo esc_html((string)$brand['footer_text']); ?></div>
</td></tr>
</table></td></tr></table></body></html>
        <?php return (string) ob_get_clean();
    }

    /** @param array<string,mixed> $brand @return array<string,string>|null */
    private static function contextual_section(string $goal, array $brand, bool $include_classes, bool $include_events, bool $include_artist): ?array {
        if ($goal === 'sell_artwork' && $include_classes) {
            return ['heading'=>__('Want to learn how it was made?', 'elev8-os'),'text'=>__('Explore upcoming classes and create something of your own.', 'elev8-os'),'label'=>__('Book a class', 'elev8-os'),'url'=>(string)$brand['class_booking_url']];
        }
        if ($goal === 'fill_class' && $include_events) {
            return ['heading'=>__('Cannot make this class?', 'elev8-os'),'text'=>__('There are more workshops, Art Walks, and community experiences waiting for you.', 'elev8-os'),'label'=>__('See all upcoming events', 'elev8-os'),'url'=>(string)$brand['events_url']];
        }
        if ($goal === 'announce_event' && $include_artist) {
            return ['heading'=>__('Love local art?', 'elev8-os'),'text'=>__('Meet the artists who make Elev8 Arts a creative community.', 'elev8-os'),'label'=>__('Meet our artists', 'elev8-os'),'url'=>(string)$brand['artist_directory_url']];
        }
        return null;
    }

    /** @param array<string,string> $variables */
    private static function replace(string $value, array $variables): string {
        if ($value === '') { return ''; }
        $pairs = [];
        foreach ($variables as $key => $replacement) { $pairs['{{' . sanitize_key($key) . '}}'] = (string) $replacement; }
        return strtr($value, $pairs);
    }
}

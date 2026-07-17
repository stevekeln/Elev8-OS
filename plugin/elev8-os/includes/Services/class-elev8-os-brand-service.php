<?php
if (!defined('ABSPATH')) { exit; }

/** WordPress-owned brand presentation settings shared by all Content Studio channels. */
final class Elev8_OS_Brand_Service {
    private const OPTION = 'elev8_os_brand_settings';

    /** @return array<string,mixed> */
    public static function defaults(): array {
        return [
            'brand_name' => 'Elev8 Arts',
            'logo_id' => 0,
            'primary_color' => '#ff7a00',
            'secondary_color' => '#17212b',
            'background_color' => '#f6f7f7',
            'text_color' => '#17212b',
            'button_text_color' => '#ffffff',
            'footer_text' => __('Created with Elev8 OS', 'elev8-os'),
            'website_url' => home_url('/'),
            'default_cta_label' => __('Learn More', 'elev8-os'),
        ];
    }

    /** @return array<string,mixed> */
    public static function get(): array {
        $stored = get_option(self::OPTION, []);
        return wp_parse_args(is_array($stored) ? $stored : [], self::defaults());
    }

    /** @param array<string,mixed> $input */
    public static function save(array $input): bool {
        $current = self::get();
        $record = [
            'brand_name' => sanitize_text_field((string) ($input['brand_name'] ?? $current['brand_name'])),
            'logo_id' => absint($input['logo_id'] ?? $current['logo_id']),
            'primary_color' => self::color($input['primary_color'] ?? $current['primary_color'], '#ff7a00'),
            'secondary_color' => self::color($input['secondary_color'] ?? $current['secondary_color'], '#17212b'),
            'background_color' => self::color($input['background_color'] ?? $current['background_color'], '#f6f7f7'),
            'text_color' => self::color($input['text_color'] ?? $current['text_color'], '#17212b'),
            'button_text_color' => self::color($input['button_text_color'] ?? $current['button_text_color'], '#ffffff'),
            'footer_text' => sanitize_text_field((string) ($input['footer_text'] ?? $current['footer_text'])),
            'website_url' => esc_url_raw((string) ($input['website_url'] ?? $current['website_url'])),
            'default_cta_label' => sanitize_text_field((string) ($input['default_cta_label'] ?? $current['default_cta_label'])),
        ];
        return update_option(self::OPTION, $record, false);
    }

    public static function logo_url(): string {
        $settings = self::get();
        $url = $settings['logo_id'] ? wp_get_attachment_image_url((int) $settings['logo_id'], 'medium') : '';
        return is_string($url) ? $url : '';
    }

    private static function color($value, string $fallback): string {
        $color = sanitize_hex_color((string) $value);
        return $color ?: $fallback;
    }
}

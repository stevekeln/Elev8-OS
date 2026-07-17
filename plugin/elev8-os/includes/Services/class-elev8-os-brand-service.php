<?php
if (!defined('ABSPATH')) { exit; }

/** WordPress-owned brand presentation settings shared by all Content Studio channels. */
final class Elev8_OS_Brand_Service {
    private const OPTION = 'elev8_os_brand_settings';

    /** @return array<string,mixed> */
    public static function defaults(): array {
        return [
            'brand_name' => 'Elev8 Arts',
            'tagline' => __('Create • Learn • Connect • Inspire', 'elev8-os'),
            'logo_id' => 0,
            'primary_color' => '#ff7a00',
            'secondary_color' => '#17212b',
            'background_color' => '#f3f5f7',
            'text_color' => '#17212b',
            'button_text_color' => '#ffffff',
            'footer_text' => __('Powered by Elev8 OS', 'elev8-os'),
            'website_url' => home_url('/'),
            'class_booking_url' => 'https://elev8arts.com/book-appointment/',
            'events_url' => 'https://elev8arts.com/elev8-events/',
            'artist_directory_url' => home_url('/artists/'),
            'default_cta_label' => __('Learn More', 'elev8-os'),
            'mission_heading' => __('Every class helps.', 'elev8-os'),
            'mission_text' => __('When you support Elev8 Arts, you help create opportunities for local artists and build a stronger creative community.', 'elev8-os'),
            'address_text' => __('2438 E Platte Ave, Colorado Springs, CO', 'elev8-os'),
            'facebook_url' => '',
            'instagram_url' => '',
            'youtube_url' => '',
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
            'tagline' => sanitize_text_field((string) ($input['tagline'] ?? $current['tagline'])),
            'logo_id' => absint($input['logo_id'] ?? $current['logo_id']),
            'primary_color' => self::color($input['primary_color'] ?? $current['primary_color'], '#ff7a00'),
            'secondary_color' => self::color($input['secondary_color'] ?? $current['secondary_color'], '#17212b'),
            'background_color' => self::color($input['background_color'] ?? $current['background_color'], '#f3f5f7'),
            'text_color' => self::color($input['text_color'] ?? $current['text_color'], '#17212b'),
            'button_text_color' => self::color($input['button_text_color'] ?? $current['button_text_color'], '#ffffff'),
            'footer_text' => sanitize_text_field((string) ($input['footer_text'] ?? $current['footer_text'])),
            'website_url' => esc_url_raw((string) ($input['website_url'] ?? $current['website_url'])),
            'class_booking_url' => esc_url_raw((string) ($input['class_booking_url'] ?? $current['class_booking_url'])),
            'events_url' => esc_url_raw((string) ($input['events_url'] ?? $current['events_url'])),
            'artist_directory_url' => esc_url_raw((string) ($input['artist_directory_url'] ?? $current['artist_directory_url'])),
            'default_cta_label' => sanitize_text_field((string) ($input['default_cta_label'] ?? $current['default_cta_label'])),
            'mission_heading' => sanitize_text_field((string) ($input['mission_heading'] ?? $current['mission_heading'])),
            'mission_text' => sanitize_textarea_field((string) ($input['mission_text'] ?? $current['mission_text'])),
            'address_text' => sanitize_text_field((string) ($input['address_text'] ?? $current['address_text'])),
            'facebook_url' => esc_url_raw((string) ($input['facebook_url'] ?? $current['facebook_url'])),
            'instagram_url' => esc_url_raw((string) ($input['instagram_url'] ?? $current['instagram_url'])),
            'youtube_url' => esc_url_raw((string) ($input['youtube_url'] ?? $current['youtube_url'])),
        ];
        return update_option(self::OPTION, $record, false);
    }

    public static function logo_url(): string {
        $settings = self::get();
        $url = $settings['logo_id'] ? wp_get_attachment_image_url((int) $settings['logo_id'], 'large') : '';
        if (!is_string($url) || $url === '') {
            $custom_logo_id = (int) get_theme_mod('custom_logo', 0);
            $url = $custom_logo_id ? wp_get_attachment_image_url($custom_logo_id, 'large') : '';
        }
        return is_string($url) ? $url : '';
    }

    private static function color($value, string $fallback): string {
        $color = sanitize_hex_color((string) $value);
        return $color ?: $fallback;
    }
}

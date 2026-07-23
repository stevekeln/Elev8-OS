<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Trusted-device registry for persistent mobile reliability.
 *
 * WordPress remains the authentication authority. Elev8 OS records device
 * identity and last-seen evidence, and can revoke an individual registered
 * device without storing passwords or WordPress session tokens.
 */
final class Elev8_OS_Device_Session_Service {
    private const META_KEY = 'elev8_os_trusted_devices_v1';
    private const COOKIE = 'elev8_os_device';
    private const MAX_DEVICES = 20;

    public static function init(): void {
        add_action('init', [__CLASS__, 'enforce_revocation'], 2);
    }

    public static function cookie_name(): string { return self::COOKIE; }

    public static function current_device_id(): string {
        return self::sanitize_device_id((string) ($_COOKIE[self::COOKIE] ?? ''));
    }

    public static function devices(int $user_id): array {
        $devices = get_user_meta($user_id, self::META_KEY, true);
        if (!is_array($devices)) { return []; }
        uasort($devices, static function (array $a, array $b): int {
            return strcmp((string) ($b['last_seen_gmt'] ?? ''), (string) ($a['last_seen_gmt'] ?? ''));
        });
        return $devices;
    }

    public static function register(int $user_id, array $input): array {
        $device_id = self::sanitize_device_id((string) ($input['device_id'] ?? ''));
        if (!$device_id) { $device_id = wp_generate_uuid4(); }

        $devices = self::devices($user_id);
        $existing = isset($devices[$device_id]) && is_array($devices[$device_id]) ? $devices[$device_id] : [];
        $now = current_time('mysql', true);
        $devices[$device_id] = [
            'id' => $device_id,
            'name' => self::device_name((string) ($input['user_agent'] ?? ''), (string) ($input['platform'] ?? '')),
            'platform' => sanitize_text_field((string) ($input['platform'] ?? '')),
            'user_agent' => sanitize_text_field(wp_trim_words((string) ($input['user_agent'] ?? ''), 30, '')),
            'first_seen_gmt' => (string) ($existing['first_seen_gmt'] ?? $now),
            'last_seen_gmt' => $now,
            'last_ip_hash' => self::ip_hash(),
            'revoked' => false,
            'revoked_gmt' => '',
        ];

        if (count($devices) > self::MAX_DEVICES) {
            uasort($devices, static fn(array $a, array $b): int => strcmp((string)($b['last_seen_gmt'] ?? ''), (string)($a['last_seen_gmt'] ?? '')));
            $devices = array_slice($devices, 0, self::MAX_DEVICES, true);
        }
        update_user_meta($user_id, self::META_KEY, $devices);
        self::set_device_cookie($device_id);
        return $devices[$device_id];
    }

    public static function revoke(int $user_id, string $device_id): bool {
        $device_id = self::sanitize_device_id($device_id);
        $devices = self::devices($user_id);
        if (!$device_id || empty($devices[$device_id])) { return false; }
        $devices[$device_id]['revoked'] = true;
        $devices[$device_id]['revoked_gmt'] = current_time('mysql', true);
        update_user_meta($user_id, self::META_KEY, $devices);
        return true;
    }

    public static function forget(int $user_id, string $device_id): bool {
        $device_id = self::sanitize_device_id($device_id);
        $devices = self::devices($user_id);
        if (!$device_id || !isset($devices[$device_id])) { return false; }
        unset($devices[$device_id]);
        update_user_meta($user_id, self::META_KEY, $devices);
        return true;
    }

    public static function enforce_revocation(): void {
        if (!is_user_logged_in() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) { return; }
        $device_id = self::current_device_id();
        if (!$device_id) { return; }
        $devices = self::devices(get_current_user_id());
        if (!empty($devices[$device_id]['revoked'])) {
            wp_logout();
            self::clear_device_cookie();
            wp_safe_redirect(wp_login_url(home_url('/elev8-app/')));
            exit;
        }
    }

    public static function is_current(array $device): bool {
        return !empty($device['id']) && hash_equals((string)$device['id'], self::current_device_id());
    }

    private static function sanitize_device_id(string $value): string {
        $value = strtolower(preg_replace('/[^a-zA-Z0-9-]/', '', $value));
        return strlen($value) >= 16 && strlen($value) <= 64 ? $value : '';
    }

    private static function set_device_cookie(string $device_id): void {
        if (headers_sent()) { return; }
        setcookie(self::COOKIE, $device_id, [
            'expires' => time() + YEAR_IN_SECONDS,
            'path' => COOKIEPATH ?: '/',
            'domain' => COOKIE_DOMAIN ?: '',
            'secure' => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[self::COOKIE] = $device_id;
    }

    private static function clear_device_cookie(): void {
        if (!headers_sent()) {
            setcookie(self::COOKIE, '', ['expires'=>time()-3600,'path'=>COOKIEPATH ?: '/','domain'=>COOKIE_DOMAIN ?: '','secure'=>is_ssl(),'httponly'=>true,'samesite'=>'Lax']);
        }
        unset($_COOKIE[self::COOKIE]);
    }

    private static function ip_hash(): string {
        $ip = sanitize_text_field((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        return $ip ? hash_hmac('sha256', $ip, wp_salt('auth')) : '';
    }

    private static function device_name(string $ua, string $platform): string {
        $ua_lower = strtolower($ua);
        if (strpos($ua_lower, 'iphone') !== false) { $device = 'iPhone'; }
        elseif (strpos($ua_lower, 'ipad') !== false) { $device = 'iPad'; }
        elseif (strpos($ua_lower, 'android') !== false) { $device = 'Android device'; }
        elseif (strpos($ua_lower, 'windows') !== false) { $device = 'Windows computer'; }
        elseif (strpos($ua_lower, 'macintosh') !== false) { $device = 'Mac'; }
        else { $device = $platform ?: 'Browser device'; }

        if (strpos($ua_lower, 'edg/') !== false) { $browser = 'Edge'; }
        elseif (strpos($ua_lower, 'chrome/') !== false) { $browser = 'Chrome'; }
        elseif (strpos($ua_lower, 'firefox/') !== false) { $browser = 'Firefox'; }
        elseif (strpos($ua_lower, 'safari/') !== false) { $browser = 'Safari'; }
        else { $browser = 'Browser'; }
        return sanitize_text_field($device . ' · ' . $browser);
    }
}

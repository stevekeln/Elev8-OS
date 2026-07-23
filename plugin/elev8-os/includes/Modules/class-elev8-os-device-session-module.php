<?php
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Device_Session_Module {
    private const PAGE_SLUG = 'my-devices';

    public static function init(): void {
        add_shortcode('elev8_my_devices', [__CLASS__, 'shortcode']);
        add_action('init', [__CLASS__, 'ensure_page'], 30);
        add_action('wp_enqueue_scripts', [__CLASS__, 'assets']);
        add_action('rest_api_init', [__CLASS__, 'routes']);
        add_action('admin_post_elev8_revoke_device', [__CLASS__, 'revoke']);
        add_action('admin_post_elev8_forget_device', [__CLASS__, 'forget']);
        add_filter('elev8_os_mobile_home_cards', [__CLASS__, 'mobile_card'], 25, 2);
    }

    public static function page_url(): string { return home_url('/' . self::PAGE_SLUG . '/'); }

    public static function ensure_page(): void {
        $page = get_page_by_path(self::PAGE_SLUG);
        if (!$page) {
            wp_insert_post(['post_type'=>'page','post_status'=>'publish','post_title'=>__('My Devices','elev8-os'),'post_name'=>self::PAGE_SLUG,'post_content'=>'[elev8_my_devices]']);
        } elseif (!has_shortcode((string)$page->post_content, 'elev8_my_devices')) {
            wp_update_post(['ID'=>$page->ID,'post_content'=>'[elev8_my_devices]']);
        }
    }

    public static function assets(): void {
        if (!is_user_logged_in()) { return; }
        wp_enqueue_script('elev8-device-session', ELEV8_OS_URL . 'assets/js/device-session.js', [], ELEV8_OS_VERSION, true);
        wp_localize_script('elev8-device-session', 'elev8DeviceSession', [
            'endpoint' => esc_url_raw(rest_url('elev8-os/v1/device/register')),
            'nonce' => wp_create_nonce('wp_rest'),
        ]);
        if (is_page(self::PAGE_SLUG)) {
            wp_enqueue_style('elev8-device-session', ELEV8_OS_URL . 'assets/css/device-session.css', [], ELEV8_OS_VERSION);
        }
    }

    public static function routes(): void {
        register_rest_route('elev8-os/v1', '/device/register', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'register'],
            'permission_callback' => static fn(): bool => is_user_logged_in(),
        ]);
    }

    public static function register(WP_REST_Request $request): WP_REST_Response {
        $record = Elev8_OS_Device_Session_Service::register(get_current_user_id(), [
            'device_id' => sanitize_text_field((string)$request->get_param('device_id')),
            'user_agent' => sanitize_text_field((string)$request->get_param('user_agent')),
            'platform' => sanitize_text_field((string)$request->get_param('platform')),
        ]);
        return new WP_REST_Response(['ok'=>true,'device'=>$record], 200);
    }

    public static function mobile_card(array $cards, WP_User $user): array {
        $cards[] = ['title'=>__('My Devices','elev8-os'),'description'=>__('See where you are signed in and remotely sign out a lost or old device.','elev8-os'),'url'=>self::page_url(),'icon'=>'smartphone'];
        return $cards;
    }

    public static function shortcode(): string {
        if (!is_user_logged_in()) { return '<p>' . esc_html__('Please sign in to manage devices.', 'elev8-os') . '</p>'; }
        $devices = Elev8_OS_Device_Session_Service::devices(get_current_user_id());
        $message = sanitize_key((string)($_GET['device_action'] ?? ''));
        ob_start(); ?>
        <main class="elev8-device-wrap">
            <section class="elev8-device-hero"><span><?php echo esc_html__('Mobile Reliability','elev8-os'); ?></span><h1><?php echo esc_html__('My Devices','elev8-os'); ?></h1><p><?php echo esc_html__('Elev8 OS remembers trusted browsers so you can see where your account is active. Revoke a device to sign it out the next time it connects.','elev8-os'); ?></p></section>
            <?php if ($message): ?><div class="elev8-device-notice"><?php echo esc_html($message === 'revoked' ? __('Device access revoked.','elev8-os') : __('Device removed from the list.','elev8-os')); ?></div><?php endif; ?>
            <section class="elev8-device-list">
            <?php if (!$devices): ?><article class="elev8-device-empty"><h2><?php echo esc_html__('Registering this device…','elev8-os'); ?></h2><p><?php echo esc_html__('Refresh this page in a moment to see it listed.','elev8-os'); ?></p></article><?php endif; ?>
            <?php foreach ($devices as $device): $current=Elev8_OS_Device_Session_Service::is_current($device); $revoked=!empty($device['revoked']); ?>
                <article class="elev8-device-card <?php echo $revoked ? 'is-revoked' : ''; ?>">
                    <div><span class="elev8-device-status"><?php echo esc_html($revoked ? __('Access revoked','elev8-os') : ($current ? __('This device','elev8-os') : __('Active device','elev8-os'))); ?></span><h2><?php echo esc_html((string)($device['name'] ?? __('Browser device','elev8-os'))); ?></h2><p><?php printf(esc_html__('Last active: %s','elev8-os'), esc_html(self::local_time((string)($device['last_seen_gmt'] ?? '')))); ?></p></div>
                    <div class="elev8-device-actions">
                    <?php if (!$revoked): ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="elev8_revoke_device"><input type="hidden" name="device_id" value="<?php echo esc_attr((string)$device['id']); ?>"><?php wp_nonce_field('elev8_revoke_device'); ?><button type="submit" class="danger"><?php echo esc_html($current ? __('Sign out this device','elev8-os') : __('Revoke access','elev8-os')); ?></button></form><?php else: ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="elev8_forget_device"><input type="hidden" name="device_id" value="<?php echo esc_attr((string)$device['id']); ?>"><?php wp_nonce_field('elev8_forget_device'); ?><button type="submit"><?php echo esc_html__('Remove from list','elev8-os'); ?></button></form><?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
            </section>
            <section class="elev8-device-security"><h2><?php echo esc_html__('What this protects','elev8-os'); ?></h2><p><?php echo esc_html__('Elev8 OS stores device identity and activity—not your password, credit-card information, or raw WordPress login token. WordPress remains responsible for authentication.','elev8-os'); ?></p></section>
        </main><?php return (string)ob_get_clean();
    }

    public static function revoke(): void {
        if (!is_user_logged_in()) { wp_die(esc_html__('Please sign in.','elev8-os')); }
        check_admin_referer('elev8_revoke_device');
        $device_id = sanitize_text_field((string)($_POST['device_id'] ?? ''));
        $current = hash_equals(Elev8_OS_Device_Session_Service::current_device_id(), $device_id);
        Elev8_OS_Device_Session_Service::revoke(get_current_user_id(), $device_id);
        if ($current) { wp_logout(); wp_safe_redirect(wp_login_url(self::page_url())); exit; }
        wp_safe_redirect(add_query_arg('device_action','revoked',self::page_url())); exit;
    }

    public static function forget(): void {
        if (!is_user_logged_in()) { wp_die(esc_html__('Please sign in.','elev8-os')); }
        check_admin_referer('elev8_forget_device');
        Elev8_OS_Device_Session_Service::forget(get_current_user_id(), sanitize_text_field((string)($_POST['device_id'] ?? '')));
        wp_safe_redirect(add_query_arg('device_action','forgotten',self::page_url())); exit;
    }

    private static function local_time(string $gmt): string {
        if (!$gmt) { return __('Unknown','elev8-os'); }
        return wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($gmt . ' UTC'));
    }
}

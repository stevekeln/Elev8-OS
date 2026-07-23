<?php
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Order_Capture_Module {
    private const SLUG = 'elev8-order-capture';
    private const SHORTCODE = 'elev8_order_capture';

    public static function init(): void {
        add_shortcode(self::SHORTCODE, [__CLASS__, 'shortcode']);
        add_action('init', [__CLASS__, 'ensure_page'], 31);
        add_action('wp_enqueue_scripts', [__CLASS__, 'assets']);
        add_action('wp_ajax_elev8_lookup_order', [__CLASS__, 'ajax_lookup']);
        add_filter('elev8_os_application_shell_frontend', [__CLASS__, 'shell_filter'], 10, 2);
    }

    public static function ensure_page(): void {
        $page = get_page_by_path(self::SLUG);
        if (!$page && current_user_can('manage_options')) {
            wp_insert_post(['post_type'=>'page','post_status'=>'publish','post_title'=>__('Order Capture','elev8-os'),'post_name'=>self::SLUG,'post_content'=>'['.self::SHORTCODE.']']);
        } elseif ($page && !has_shortcode((string)$page->post_content, self::SHORTCODE)) {
            wp_update_post(['ID'=>$page->ID,'post_content'=>'['.self::SHORTCODE.']']);
        }
    }

    public static function url(string $mode = 'shipping'): string {
        return add_query_arg('mode', sanitize_key($mode), home_url('/'.self::SLUG.'/'));
    }

    public static function is_current(): bool { return is_page(self::SLUG); }
    public static function shell_filter(bool $render, string $path): bool { return $render || self::is_current() || trim($path,'/') === self::SLUG; }

    public static function assets(): void {
        if (!self::is_current()) { return; }
        wp_enqueue_style('elev8-os-order-capture', ELEV8_OS_URL.'assets/css/order-capture.css', [], ELEV8_OS_VERSION);
        wp_enqueue_script('elev8-os-order-capture', ELEV8_OS_URL.'assets/js/order-capture.js', [], ELEV8_OS_VERSION, true);
        wp_localize_script('elev8-os-order-capture','Elev8OrderCapture',[
            'ajaxUrl'=>admin_url('admin-ajax.php'),
            'nonce'=>wp_create_nonce('elev8_lookup_order'),
            'cameraUnsupported'=>__('Camera scanning is not supported in this browser. Type the order number instead.','elev8-os'),
            'cameraError'=>__('The camera could not be opened. Check browser permission or type the order number.','elev8-os'),
        ]);
    }

    private static function allowed(string $mode): bool {
        $permission = $mode === 'customer-service' ? 'search_customer_orders' : 'scan_shipping_orders';
        return class_exists('Elev8_OS_Access_Service') && Elev8_OS_Access_Service::user_can($permission);
    }

    public static function shortcode(): string {
        if (!is_user_logged_in()) { return '<p>'.esc_html__('Please sign in.','elev8-os').'</p>'; }
        $mode = sanitize_key($_GET['mode'] ?? 'shipping');
        if (!in_array($mode,['shipping','customer-service'],true)) { $mode='shipping'; }
        if (!self::allowed($mode)) { return '<p>'.esc_html__('You do not have access to this workspace.','elev8-os').'</p>'; }
        $shipping = $mode === 'shipping';
        ob_start(); ?>
        <main class="elev8-order-capture" data-mode="<?php echo esc_attr($mode); ?>">
            <header><span><?php esc_html_e('ELEV8 OS','elev8-os'); ?></span><h1><?php echo esc_html($shipping ? __('Shipping Order Scanner','elev8-os') : __('Customer Order Lookup','elev8-os')); ?></h1><p><?php echo esc_html($shipping ? __('Scan the order from the pick list, then verify the customer and every item before packing.','elev8-os') : __('Find the order before responding so the conversation stays grounded in verified business data.','elev8-os')); ?></p></header>
            <section class="elev8-order-capture__panel">
                <form data-elev8-order-form>
                    <label for="elev8-order-reference"><?php esc_html_e('Order number','elev8-os'); ?></label>
                    <div class="elev8-order-capture__entry"><input id="elev8-order-reference" name="reference" inputmode="numeric" autocomplete="off" placeholder="12345" required><button class="elev8-ui-button elev8-ui-button--primary" type="submit"><?php esc_html_e('Find Order','elev8-os'); ?></button></div>
                </form>
                <?php if ($shipping): ?><button class="elev8-ui-button elev8-order-capture__scan" type="button" data-elev8-start-scan><?php esc_html_e('Scan with Camera','elev8-os'); ?></button><div class="elev8-order-capture__camera" data-elev8-camera hidden><video playsinline muted></video><button type="button" data-elev8-stop-scan><?php esc_html_e('Stop Camera','elev8-os'); ?></button></div><?php endif; ?>
                <div class="elev8-order-capture__status" data-elev8-order-status aria-live="polite"></div>
                <div data-elev8-order-result></div>
            </section>
        </main>
        <?php return (string)ob_get_clean();
    }

    public static function ajax_lookup(): void {
        check_ajax_referer('elev8_lookup_order','nonce');
        $mode = sanitize_key($_POST['mode'] ?? 'shipping');
        if (!self::allowed($mode)) { wp_send_json_error(['message'=>__('You do not have permission to view this order.','elev8-os')],403); }
        $order = Elev8_OS_Order_Capture_Service::find((string)($_POST['reference'] ?? ''));
        if (is_wp_error($order)) { wp_send_json_error(['message'=>$order->get_error_message()],404); }
        wp_send_json_success(Elev8_OS_Order_Capture_Service::read_model($order));
    }
}

<?php
if (!defined('ABSPATH')) { exit; }

/** Shared order capture/read model used by shipping and customer service. */
final class Elev8_OS_Order_Capture_Service {
    public static function normalize_reference(string $raw): string {
        $raw = trim(wp_strip_all_tags($raw));
        if ($raw === '') { return ''; }
        if (preg_match('/(?:order[\s#:-]*)?(\d{1,12})/i', $raw, $m)) { return ltrim($m[1], '0') ?: '0'; }
        return preg_replace('/[^A-Za-z0-9_-]/', '', $raw);
    }

    public static function find(string $reference) {
        $reference = self::normalize_reference($reference);
        if ($reference === '') { return new WP_Error('elev8_order_reference', __('Enter or scan an order number.', 'elev8-os')); }
        if (!function_exists('wc_get_order')) { return new WP_Error('elev8_commerce_unavailable', __('WooCommerce is not available on this site.', 'elev8-os')); }
        $order = wc_get_order(absint($reference));
        if (!$order) { return new WP_Error('elev8_order_not_found', sprintf(__('Order #%s was not found.', 'elev8-os'), $reference)); }
        return $order;
    }

    public static function read_model($order): array {
        if (!is_a($order, 'WC_Order')) { return []; }
        $items = [];
        foreach ($order->get_items() as $item) {
            $items[] = [
                'name' => $item->get_name(),
                'quantity' => (float) $item->get_quantity(),
                'sku' => ($product = $item->get_product()) ? (string) $product->get_sku() : '',
            ];
        }
        return [
            'id' => (int) $order->get_id(),
            'number' => (string) $order->get_order_number(),
            'status' => wc_get_order_status_name($order->get_status()),
            'customer' => trim($order->get_formatted_billing_full_name()) ?: __('Guest customer', 'elev8-os'),
            'email' => (string) $order->get_billing_email(),
            'shipping_method' => (string) $order->get_shipping_method(),
            'total' => (string) $order->get_formatted_order_total(),
            'items' => $items,
        ];
    }
}

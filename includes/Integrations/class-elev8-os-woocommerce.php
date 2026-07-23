<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WooCommerce is the checkout and payment system for sellable Elev8 OS assets.
 * Elev8 OS remains the owner of asset identity, ownership, inventory state, and BI data.
 */
final class Elev8_OS_WooCommerce {

    private const SYNC_VERSION = '7.2.0';
    private const SYNC_OPTION = 'elev8_os_asset_woo_sync_version';
    private static bool $syncing = false;

    public static function init(): void {
        add_action('init', [__CLASS__, 'maybe_sync_existing_assets'], 30);
        add_action('elev8_os_asset_saved', [__CLASS__, 'sync_saved_asset'], 10, 2);
        add_action('elev8_os_asset_deleted', [__CLASS__, 'trash_deleted_asset_product'], 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', [__CLASS__, 'add_asset_order_item_meta'], 10, 4);
        add_action('woocommerce_checkout_order_processed', [__CLASS__, 'reserve_order_assets'], 10, 3);
        add_action('woocommerce_order_status_processing', [__CLASS__, 'sell_order_assets']);
        add_action('woocommerce_order_status_completed', [__CLASS__, 'sell_order_assets']);
        add_action('woocommerce_order_status_cancelled', [__CLASS__, 'release_order_assets']);
        add_action('woocommerce_order_status_failed', [__CLASS__, 'release_order_assets']);
        add_action('woocommerce_order_status_refunded', [__CLASS__, 'release_order_assets']);
    }

    public static function is_available(): bool {
        return class_exists('WooCommerce') && class_exists('WC_Product_Simple');
    }

    public static function maybe_sync_existing_assets(): void {
        if (!self::is_available() || (string) get_option(self::SYNC_OPTION, '') === self::SYNC_VERSION) {
            return;
        }
        foreach (Elev8_OS_Asset_Service::get_all() as $asset) {
            self::sync_asset($asset);
        }
        update_option(self::SYNC_OPTION, self::SYNC_VERSION, false);
    }

    /** @param array<string,mixed>|null $asset */
    public static function sync_saved_asset(int $asset_id, ?array $asset = null): void {
        if (self::$syncing || !self::is_available()) {
            return;
        }
        if (!$asset) {
            $asset = Elev8_OS_Asset_Service::get($asset_id);
        }
        if ($asset) {
            self::sync_asset($asset);
        }
    }

    /** @param array<string,mixed> $asset */
    public static function sync_asset(array $asset): int {
        if (!self::is_available()) {
            return 0;
        }

        self::$syncing = true;
        try {
            $product_id = absint($asset['wc_product_id'] ?? 0);
            $product = $product_id > 0 ? wc_get_product($product_id) : false;
            if (!$product || !is_a($product, 'WC_Product_Simple')) {
                $product = new WC_Product_Simple();
            }

            $status = (string) ($asset['status'] ?? 'draft');
            $public = !empty($asset['public_visibility']);
            $sell_online = !empty($asset['sell_online']);
            $price_available = $asset['price'] !== null && (float) $asset['price'] >= 0;
            $can_publish = $public && $sell_online && $price_available && !in_array($status, ['draft', 'archived'], true);
            $quantity = max(0, absint($asset['quantity'] ?? 0));
            $is_purchasable = $status === 'available' && $quantity > 0;

            $product->set_name((string) $asset['title']);
            $product->set_slug(sanitize_title((string) $asset['title'] . '-' . (string) $asset['asset_number']));
            $product->set_description((string) $asset['description']);
            $product->set_short_description(implode(' · ', array_filter([(string) $asset['medium'], (string) $asset['dimensions']])));
            $product->set_status($can_publish ? 'publish' : 'draft');
            $product->set_catalog_visibility($can_publish ? 'visible' : 'hidden');
            $product->set_regular_price($price_available ? wc_format_decimal((string) $asset['price']) : '');
            $product->set_price($price_available ? wc_format_decimal((string) $asset['price']) : '');
            $product->set_manage_stock(true);
            $product->set_stock_quantity($is_purchasable ? $quantity : 0);
            $product->set_stock_status($is_purchasable ? 'instock' : 'outofstock');
            $product->set_sold_individually(true);
            $product->set_virtual(false);
            $product->set_downloadable(false);
            if (!empty($asset['image_attachment_id'])) {
                $product->set_image_id(absint($asset['image_attachment_id']));
            }
            if (!empty($asset['asset_number'])) {
                try {
                    $product->set_sku((string) $asset['asset_number']);
                } catch (WC_Data_Exception $exception) {
                    // Preserve the product if another legacy product already owns the SKU.
                }
            }

            $product_id = $product->save();
            update_post_meta($product_id, '_elev8_os_asset_id', absint($asset['id']));
            update_post_meta($product_id, '_elev8_os_owner_user_id', absint($asset['owner_user_id']));
            update_post_meta($product_id, '_elev8_os_asset_number', sanitize_text_field((string) $asset['asset_number']));
            update_post_meta($product_id, '_elev8_os_asset_location', sanitize_key((string) $asset['location']));
            update_post_meta($product_id, '_elev8_os_managed_product', 'yes');

            if ((int) ($asset['wc_product_id'] ?? 0) !== $product_id) {
                Elev8_OS_Asset_Service::update_operational_fields((int) $asset['id'], ['wc_product_id' => $product_id]);
            }
            return $product_id;
        } finally {
            self::$syncing = false;
        }
    }

    /** @param array<string,mixed> $asset */
    public static function trash_deleted_asset_product(int $asset_id, array $asset): void {
        $product_id = absint($asset['wc_product_id'] ?? 0);
        if ($product_id > 0 && get_post($product_id)) {
            wp_trash_post($product_id);
        }
    }

    public static function add_asset_order_item_meta($item, string $cart_item_key, array $values, $order): void {
        $product_id = absint($values['product_id'] ?? 0);
        $asset_id = absint(get_post_meta($product_id, '_elev8_os_asset_id', true));
        if ($asset_id > 0) {
            $item->add_meta_data('_elev8_os_asset_id', $asset_id, true);
            $asset_number = (string) get_post_meta($product_id, '_elev8_os_asset_number', true);
            if ($asset_number !== '') {
                $item->add_meta_data(__('Asset number', 'elev8-os'), $asset_number, true);
            }
        }
    }

    public static function reserve_order_assets(int $order_id, array $posted_data = [], $order = null): void {
        self::update_assets_from_order($order_id, 'reserved');
    }

    public static function sell_order_assets(int $order_id): void {
        self::update_assets_from_order($order_id, 'sold');
    }

    public static function release_order_assets(int $order_id): void {
        self::update_assets_from_order($order_id, 'available');
    }

    private static function update_assets_from_order(int $order_id, string $target_status): void {
        if (!self::is_available()) {
            return;
        }
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        foreach ($order->get_items() as $item) {
            $asset_id = absint($item->get_meta('_elev8_os_asset_id', true));
            if ($asset_id <= 0) {
                $asset_id = absint(get_post_meta($item->get_product_id(), '_elev8_os_asset_id', true));
            }
            $asset = Elev8_OS_Asset_Service::get($asset_id);
            if (!$asset) {
                continue;
            }
            $was_sold = (string) $asset['status'] === 'sold';
            if ($target_status === 'sold') {
                Elev8_OS_Asset_Service::update_operational_fields($asset_id, ['status' => 'sold', 'location' => 'sold', 'quantity' => 0]);
            } elseif ($target_status === 'reserved' && (string) $asset['status'] === 'available') {
                Elev8_OS_Asset_Service::update_operational_fields($asset_id, ['status' => 'reserved', 'quantity' => 0]);
            } elseif ($target_status === 'available' && (string) $asset['status'] === 'reserved') {
                Elev8_OS_Asset_Service::update_operational_fields($asset_id, ['status' => 'available', 'location' => 'at_elev8', 'quantity' => 1]);
            }
            $updated = Elev8_OS_Asset_Service::get($asset_id);
            if ($updated) {
                self::sync_asset($updated);
                if ($target_status === 'sold' && !$was_sold) {
                    do_action('elev8_os_asset_sold', $updated, $order_id, $item, $order);
                }
            }
        }
    }

    public static function get_cart_url(): string {
        if (!self::is_available() || !function_exists('wc_get_cart_url')) {
            return '';
        }

        return (string) wc_get_cart_url();
    }

    public static function get_checkout_url(): string {
        if (!self::is_available() || !function_exists('wc_get_checkout_url')) {
            return '';
        }

        return (string) wc_get_checkout_url();
    }

    /** @param array<string,mixed> $asset */
    public static function get_purchase_data(array $asset): array {
        $product_id = absint($asset['wc_product_id'] ?? 0);
        $product = self::is_available() && $product_id > 0 ? wc_get_product($product_id) : false;
        if (!$product || $product->get_status() !== 'publish') {
            return [
                'url'         => '',
                'add_to_cart' => '',
                'cart_url'    => self::get_cart_url(),
                'purchasable' => false,
            ];
        }

        $purchasable = $product->is_purchasable() && $product->is_in_stock();
        $cart_url = self::get_cart_url();
        $add_to_cart_url = '';

        if ($purchasable && $cart_url !== '') {
            // Load the actual Cart page after adding the item. This avoids a dead-end
            // storefront state and works independently of WooCommerce AJAX settings.
            $add_to_cart_url = add_query_arg(
                [
                    'add-to-cart' => $product_id,
                    'quantity'    => 1,
                ],
                $cart_url
            );
        }

        return [
            'url'         => (string) get_permalink($product_id),
            'add_to_cart' => (string) $add_to_cart_url,
            'cart_url'    => (string) $cart_url,
            'purchasable' => $purchasable,
        ];
    }
}

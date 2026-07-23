<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Canonical exception records for inventory work.
 *
 * WooCommerce remains authoritative for product identity and stock quantity.
 * Elev8 OS owns only the operational signal that requires human execution.
 */
final class Elev8_OS_Inventory_Signal_Service {
    public const POST_TYPE = 'elev8_inventory_signal';
    public const CRON_HOOK = 'elev8_os_inventory_exception_scan';
    private const META_PREFIX = '_elev8_inventory_signal_';

    public static function init(): void {
        add_action('init', [__CLASS__, 'register_post_type']);
        add_action('init', [__CLASS__, 'ensure_scan_scheduled'], 30);
        add_action(self::CRON_HOOK, [__CLASS__, 'scan_low_stock_products']);
        add_action('woocommerce_product_set_stock', [__CLASS__, 'stock_changed'], 20, 1);
        add_action('woocommerce_variation_set_stock', [__CLASS__, 'stock_changed'], 20, 1);
        add_action('woocommerce_low_stock', [__CLASS__, 'stock_changed'], 20, 1);
        add_action('woocommerce_no_stock', [__CLASS__, 'stock_changed'], 20, 1);
        add_action('woocommerce_product_set_stock_status', [__CLASS__, 'stock_status_changed'], 20, 3);
        add_filter('elev8_os_business_graph_objects', [__CLASS__, 'register_graph_objects']);
    }

    public static function register_post_type(): void {
        register_post_type(self::POST_TYPE, [
            'labels' => ['name' => __('Inventory Signals', 'elev8-os'), 'singular_name' => __('Inventory Signal', 'elev8-os')],
            'public' => false,
            'show_ui' => false,
            'show_in_rest' => false,
            'supports' => ['title'],
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ]);
    }

    public static function ensure_scan_scheduled(): void {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK);
        }
    }

    /** @param mixed $product */
    public static function stock_changed($product): void {
        if (is_numeric($product) && function_exists('wc_get_product')) { $product = wc_get_product(absint($product)); }
        if (!is_object($product) || !is_a($product, 'WC_Product')) { return; }
        self::synchronize_low_stock_signal($product);
    }

    /** @param mixed $product */
    public static function stock_status_changed(int $product_id, string $stock_status, $product = null): void {
        if (!$product && function_exists('wc_get_product')) { $product = wc_get_product($product_id); }
        self::stock_changed($product);
    }

    public static function scan_low_stock_products(): int {
        if (!function_exists('wc_get_products')) { return 0; }
        $page = 1;
        $count = 0;
        do {
            $products = wc_get_products([
                'status' => ['publish','private','draft'],
                'manage_stock' => true,
                'limit' => 100,
                'page' => $page,
                'return' => 'objects',
            ]);
            foreach ($products as $product) {
                self::synchronize_low_stock_signal($product);
                $count++;
            }
            $page++;
        } while (count($products) === 100);
        return $count;
    }

    /** @param WC_Product $product */
    public static function synchronize_low_stock_signal($product): int {
        $product_id = absint($product->get_id());
        if ($product_id < 1 || !$product->managing_stock()) { return 0; }
        $quantity = $product->get_stock_quantity();
        $threshold = $product->get_low_stock_amount();
        if ($threshold === '' || $threshold === null) {
            $threshold = get_option('woocommerce_notify_low_stock_amount', 2);
        }
        $quantity = is_numeric($quantity) ? (float)$quantity : 0.0;
        $threshold = is_numeric($threshold) ? (float)$threshold : 2.0;
        $existing = self::find_open_signal('low_stock', 'woocommerce_product', $product_id, 0);

        if ($quantity > $threshold && $product->is_in_stock()) {
            if ($existing) { self::resolve($existing, __('Stock recovered above the configured low-stock threshold.', 'elev8-os')); }
            return $existing;
        }

        $priority = $quantity <= 0 || !$product->is_in_stock() ? 'urgent' : 'high';
        return self::upsert([
            'signal_type' => 'low_stock',
            'source_type' => 'woocommerce_product',
            'source_id' => $product_id,
            'status' => 'open',
            'priority' => $priority,
            'quantity' => $quantity,
            'threshold' => $threshold,
            'due_date' => wp_date('Y-m-d', current_time('timestamp') + DAY_IN_SECONDS),
            'organization_unit_id' => absint(apply_filters('elev8_os_inventory_product_organization_unit_id', 0, $product_id, $product)),
            'owner_user_id' => absint(apply_filters('elev8_os_inventory_signal_owner_user_id', 0, 'low_stock', $product_id)),
            'context' => ['stock_status' => sanitize_key((string)$product->get_stock_status())],
        ]);
    }

    /**
     * Create or update a canonical inventory exception.
     *
     * Supported signal types: low_stock, receiving, cycle_count,
     * discrepancy, event_reservation.
     *
     * @param array<string,mixed> $args
     */
    public static function upsert(array $args) {
        $type = sanitize_key((string)($args['signal_type'] ?? ''));
        $allowed = ['low_stock','receiving','cycle_count','discrepancy','event_reservation'];
        if (!in_array($type, $allowed, true)) {
            return new WP_Error('invalid_inventory_signal_type', __('The inventory signal type is invalid.', 'elev8-os'));
        }
        $source_type = sanitize_key((string)($args['source_type'] ?? 'inventory'));
        $source_id = absint($args['source_id'] ?? 0);
        $organization_unit_id = absint($args['organization_unit_id'] ?? 0);
        $existing = self::find_open_signal($type, $source_type, $source_id, $organization_unit_id);
        $signal_id = $existing ?: wp_insert_post([
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'post_title' => self::signal_title($type, $source_type, $source_id),
        ], true);
        if (is_wp_error($signal_id)) { return $signal_id; }
        $signal_id = absint($signal_id);

        $fields = [
            'signal_type' => $type,
            'source_type' => $source_type,
            'source_id' => $source_id,
            'status' => sanitize_key((string)($args['status'] ?? 'open')) ?: 'open',
            'priority' => sanitize_key((string)($args['priority'] ?? 'normal')) ?: 'normal',
            'owner_user_id' => absint($args['owner_user_id'] ?? 0),
            'organization_unit_id' => $organization_unit_id,
            'due_date' => sanitize_text_field((string)($args['due_date'] ?? '')),
            'quantity' => is_numeric($args['quantity'] ?? null) ? (float)$args['quantity'] : '',
            'threshold' => is_numeric($args['threshold'] ?? null) ? (float)$args['threshold'] : '',
            'expected_quantity' => is_numeric($args['expected_quantity'] ?? null) ? (float)$args['expected_quantity'] : '',
            'actual_quantity' => is_numeric($args['actual_quantity'] ?? null) ? (float)$args['actual_quantity'] : '',
            'event_id' => absint($args['event_id'] ?? 0),
            'notes' => sanitize_textarea_field((string)($args['notes'] ?? '')),
            'context' => is_array($args['context'] ?? null) ? self::clean_context($args['context']) : [],
            'updated_at' => current_time('mysql'),
        ];
        if (!$existing) { $fields['created_at'] = current_time('mysql'); }
        foreach ($fields as $key => $value) { update_post_meta($signal_id, self::META_PREFIX . $key, $value); }
        do_action('elev8_os_inventory_signal_changed', $signal_id, self::get($signal_id));
        return $signal_id;
    }

    public static function resolve(int $signal_id, string $notes = ''): bool {
        if (get_post_type($signal_id) !== self::POST_TYPE) { return false; }
        update_post_meta($signal_id, self::META_PREFIX . 'status', 'resolved');
        update_post_meta($signal_id, self::META_PREFIX . 'resolved_at', current_time('mysql'));
        update_post_meta($signal_id, self::META_PREFIX . 'resolved_by_user_id', get_current_user_id());
        if ($notes !== '') { update_post_meta($signal_id, self::META_PREFIX . 'resolution_notes', sanitize_textarea_field($notes)); }
        do_action('elev8_os_inventory_signal_changed', $signal_id, self::get($signal_id));
        return true;
    }

    /** @return array<string,mixed> */
    public static function get(int $signal_id): array {
        if (get_post_type($signal_id) !== self::POST_TYPE) { return []; }
        $keys = ['signal_type','source_type','source_id','status','priority','owner_user_id','organization_unit_id','due_date','quantity','threshold','expected_quantity','actual_quantity','event_id','notes','context','created_at','updated_at','resolved_at','resolved_by_user_id','resolution_notes'];
        $data = ['id' => $signal_id];
        foreach ($keys as $key) { $data[$key] = get_post_meta($signal_id, self::META_PREFIX . $key, true); }
        return $data;
    }

    private static function find_open_signal(string $type, string $source_type, int $source_id, int $organization_unit_id): int {
        $query = [
            ['key' => self::META_PREFIX . 'signal_type', 'value' => $type],
            ['key' => self::META_PREFIX . 'source_type', 'value' => $source_type],
            ['key' => self::META_PREFIX . 'source_id', 'value' => $source_id, 'type' => 'NUMERIC'],
            ['key' => self::META_PREFIX . 'status', 'value' => ['open','in_progress','waiting'], 'compare' => 'IN'],
        ];
        if ($organization_unit_id > 0) { $query[] = ['key' => self::META_PREFIX . 'organization_unit_id', 'value' => $organization_unit_id, 'type' => 'NUMERIC']; }
        $ids = get_posts(['post_type'=>self::POST_TYPE, 'post_status'=>'publish', 'posts_per_page'=>1, 'fields'=>'ids', 'meta_query'=>$query]);
        return $ids ? absint($ids[0]) : 0;
    }

    private static function signal_title(string $type, string $source_type, int $source_id): string {
        $labels = ['low_stock'=>'Low stock','receiving'=>'Receiving','cycle_count'=>'Cycle count','discrepancy'=>'Inventory discrepancy','event_reservation'=>'Event inventory reservation'];
        return sprintf('%s — %s #%d', $labels[$type] ?? ucfirst(str_replace('_', ' ', $type)), $source_type, $source_id);
    }

    /** @param array<string,mixed> $context @return array<string,mixed> */
    private static function clean_context(array $context): array {
        $clean = [];
        foreach ($context as $key => $value) {
            $key = sanitize_key((string)$key);
            if ($key === '' || is_array($value) || is_object($value)) { continue; }
            $clean[$key] = sanitize_text_field((string)$value);
        }
        return $clean;
    }

    public static function register_graph_objects(array $objects): array {
        $objects['inventory_signal'] = [
            'label' => __('Inventory Signal', 'elev8-os'),
            'engine' => 'inventory',
            'authority' => 'elev8_os',
            'scope' => 'organization',
        ];
        return $objects;
    }
}

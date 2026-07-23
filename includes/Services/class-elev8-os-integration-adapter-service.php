<?php
/**
 * Read-only adapters that expose authoritative WooCommerce and Amelia records
 * to the Elev8 OS Business Graph without copying source data.
 */
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Integration_Adapter_Service {
    public static function init(): void {
        add_filter('elev8_os_workspace_can_view', [__CLASS__, 'workspace_can_view'], 10, 4);
        add_filter('elev8_os_workspace_summary', [__CLASS__, 'workspace_summary'], 10, 3);
        add_filter('elev8_os_workspace_source_details', [__CLASS__, 'workspace_source_details'], 10, 3);
        add_filter('elev8_os_business_graph_organization_scope', [__CLASS__, 'organization_scope'], 10, 3);
    }

    public static function diagnostics(): array {
        global $wpdb;
        $woo_available = function_exists('wc_get_product') && function_exists('wc_get_order');
        $product_count = $woo_available ? (int) wp_count_posts('product')->publish : 0;
        $order_count = 0;
        if ($woo_available && function_exists('wc_get_orders')) {
            $result = wc_get_orders(['limit' => 1, 'return' => 'ids', 'paginate' => true]);
            $order_count = is_object($result) && isset($result->total) ? (int) $result->total : 0;
        }
        $amelia_tables = [
            'appointments' => $wpdb->prefix . 'amelia_appointments',
            'bookings' => $wpdb->prefix . 'amelia_customer_bookings',
            'services' => $wpdb->prefix . 'amelia_services',
        ];
        $amelia = [];
        foreach ($amelia_tables as $key => $table) {
            $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
            $amelia[$key] = ['available' => $exists, 'count' => $exists ? (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$table}`") : 0];
        }
        return [
            'woocommerce' => ['available' => $woo_available, 'products' => $product_count, 'orders' => $order_count],
            'amelia' => $amelia,
        ];
    }

    public static function workspace_can_view(bool $allowed, string $type, int $id, WP_User $user): bool {
        if ($allowed) { return true; }
        $type = Elev8_OS_Workspace_Service::normalize_type($type);
        if ($type === 'product') { return get_post_type($id) === 'product' && (user_can($user, 'manage_woocommerce') || user_can($user, 'edit_products')); }
        if ($type === 'order') { return function_exists('wc_get_order') && (bool) wc_get_order($id) && user_can($user, 'manage_woocommerce'); }
        if (in_array($type, ['booking','class'], true)) {
            return class_exists('Elev8_OS_Access_Service') && (Elev8_OS_Access_Service::user_can('view_glass_dashboard', $user) || Elev8_OS_Access_Service::user_can('view_classes', $user) || user_can($user, 'manage_options'));
        }
        return false;
    }

    public static function workspace_summary(array $summary, string $type, int $id): array {
        $type = Elev8_OS_Workspace_Service::normalize_type($type);
        if ($type === 'product' && function_exists('wc_get_product')) {
            $product = wc_get_product($id);
            if ($product) {
                $summary['label'] = __('WooCommerce Product', 'elev8-os');
                $summary['title'] = $product->get_name();
                $summary['description'] = wp_trim_words(wp_strip_all_tags($product->get_short_description() ?: $product->get_description()), 45);
                $summary['status'] = ucfirst((string) $product->get_status());
                $summary['source_url'] = get_edit_post_link($id, '');
            }
        } elseif ($type === 'order' && function_exists('wc_get_order')) {
            $order = wc_get_order($id);
            if ($order) {
                $summary['label'] = __('WooCommerce Order', 'elev8-os');
                $summary['title'] = sprintf(__('Order #%s', 'elev8-os'), $order->get_order_number());
                $summary['description'] = trim($order->get_formatted_billing_full_name() . ' · ' . wp_strip_all_tags($order->get_formatted_order_total()));
                $summary['status'] = wc_get_order_status_name($order->get_status());
                $summary['source_url'] = method_exists($order, 'get_edit_order_url') ? $order->get_edit_order_url() : '';
            }
        } elseif ($type === 'booking') {
            $record = self::amelia_booking($id);
            if ($record) {
                $summary['label'] = __('Amelia Booking', 'elev8-os');
                $summary['title'] = sprintf('%s — %s', $record['service'], $record['customer']);
                $summary['description'] = wp_date('l, F j, Y · ' . get_option('time_format'), strtotime($record['start']));
                $summary['status'] = ucfirst((string) $record['status']);
                $summary['source_url'] = class_exists('Elev8_OS_Class_Approval_Module') ? Elev8_OS_Class_Approval_Module::url() : '';
            }
        } elseif ($type === 'class') {
            $record = self::amelia_appointment($id);
            if ($record) {
                $summary['label'] = __('Amelia Class', 'elev8-os');
                $summary['title'] = $record['service'];
                $summary['description'] = wp_date('l, F j, Y · ' . get_option('time_format'), strtotime($record['start']));
                $summary['status'] = __('Scheduled', 'elev8-os');
                $summary['source_url'] = class_exists('Elev8_OS_My_Classes_Module') ? Elev8_OS_My_Classes_Module::url() : '';
            }
        }
        return $summary;
    }

    public static function workspace_source_details(string $details, string $type, int $id): string {
        $type = Elev8_OS_Workspace_Service::normalize_type($type);
        if ($type === 'product' && function_exists('wc_get_product')) {
            $product = wc_get_product($id); return $product ? wp_kses_post($product->get_description()) : $details;
        }
        if ($type === 'order' && function_exists('wc_get_order')) {
            $order = wc_get_order($id); return $order ? esc_html(sprintf(__('Customer: %s | Total: %s', 'elev8-os'), $order->get_formatted_billing_full_name(), wp_strip_all_tags($order->get_formatted_order_total()))) : $details;
        }
        return $details;
    }

    public static function organization_scope(int $scope, string $type, int $id): int {
        if ($scope > 0) { return $scope; }
        $type = Elev8_OS_Workspace_Service::normalize_type($type);
        if ($type === 'product' || $type === 'order') {
            foreach (['_elev8_org_unit_id','_elev8_organization_unit_id','_elev8_location_id'] as $key) {
                $found = absint(get_post_meta($id, $key, true)); if ($found) { return $found; }
            }
        }
        return 0;
    }

    private static function amelia_booking(int $booking_id): array {
        global $wpdb;
        $b = $wpdb->prefix . 'amelia_customer_bookings'; $a = $wpdb->prefix . 'amelia_appointments';
        if (!self::table_exists($b) || !self::table_exists($a)) { return []; }
        $row = $wpdb->get_row($wpdb->prepare("SELECT b.id booking_id,b.status,a.id appointment_id,a.bookingStart start,a.serviceId service_id,b.customerId customer_id FROM `{$b}` b LEFT JOIN `{$a}` a ON a.id=b.appointmentId WHERE b.id=%d LIMIT 1", $booking_id), ARRAY_A);
        if (!$row) { return []; }
        $row['service'] = self::amelia_label('amelia_services', (int) $row['service_id'], 'name', __('Class', 'elev8-os'));
        $row['customer'] = self::amelia_user_name((int) $row['customer_id']);
        return $row;
    }

    private static function amelia_appointment(int $appointment_id): array {
        global $wpdb; $a = $wpdb->prefix . 'amelia_appointments';
        if (!self::table_exists($a)) { return []; }
        $row = $wpdb->get_row($wpdb->prepare("SELECT id,bookingStart start,bookingEnd end,serviceId service_id,providerId provider_id FROM `{$a}` WHERE id=%d LIMIT 1", $appointment_id), ARRAY_A);
        if (!$row) { return []; }
        $row['service'] = self::amelia_label('amelia_services', (int) $row['service_id'], 'name', __('Class', 'elev8-os'));
        return $row;
    }

    private static function amelia_label(string $suffix, int $id, string $column, string $fallback): string {
        global $wpdb; $table = $wpdb->prefix . $suffix;
        if (!$id || !self::table_exists($table)) { return $fallback; }
        $value = $wpdb->get_var($wpdb->prepare("SELECT `{$column}` FROM `{$table}` WHERE id=%d LIMIT 1", $id));
        return is_string($value) && $value !== '' ? $value : $fallback;
    }

    private static function amelia_user_name(int $id): string {
        global $wpdb; $table = $wpdb->prefix . 'amelia_users';
        if (!$id || !self::table_exists($table)) { return __('Customer', 'elev8-os'); }
        $row = $wpdb->get_row($wpdb->prepare("SELECT firstName,lastName,email FROM `{$table}` WHERE id=%d LIMIT 1", $id), ARRAY_A);
        if (!$row) { return __('Customer', 'elev8-os'); }
        $name = trim((string) $row['firstName'] . ' ' . (string) $row['lastName']);
        return $name !== '' ? $name : ((string) $row['email'] ?: __('Customer', 'elev8-os'));
    }

    private static function table_exists(string $table): bool { global $wpdb; return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table; }
}

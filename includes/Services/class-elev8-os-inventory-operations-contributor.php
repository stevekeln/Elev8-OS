<?php
if (!defined('ABSPATH')) { exit; }

/** Inventory exception adapter for the shared Operations Contributor framework. */
final class Elev8_OS_Inventory_Operations_Contributor {
    public const KEY = 'inventory_operations';

    public static function init(): void {
        add_filter('elev8_os_operations_contributors', [__CLASS__, 'register']);
        add_action('elev8_os_inventory_signal_changed', [__CLASS__, 'sync'], 10, 2);
    }

    public static function register(array $contributors): array {
        $contributors[self::KEY] = [
            'label' => __('Inventory Operations', 'elev8-os'),
            'source_type' => 'inventory_signal',
            'work_type' => 'inventory',
            'resolve' => [__CLASS__, 'resolve'],
            'steps' => [__CLASS__, 'steps'],
        ];
        return $contributors;
    }

    /** @param array<string,mixed> $signal @return array<int,int> */
    public static function sync(int $signal_id, array $signal = []): array {
        if ($signal_id < 1) { return []; }
        $result = Elev8_OS_Operations_Contributor_Service::sync_source(self::KEY, $signal_id, ['source' => $signal]);
        return is_wp_error($result) ? [] : array_map('absint', $result);
    }

    /** @return array<string,mixed> */
    public static function resolve(int $signal_id, array $context = []): array {
        $signal = is_array($context['source'] ?? null) && !empty($context['source']['id']) ? $context['source'] : Elev8_OS_Inventory_Signal_Service::get($signal_id);
        if (!$signal) { return []; }
        $source_type = sanitize_key((string)($signal['source_type'] ?? 'inventory'));
        $source_id = absint($signal['source_id'] ?? 0);
        $label = self::source_label($source_type, $source_id);
        return [
            'id' => $signal_id,
            'status' => sanitize_key((string)($signal['status'] ?? 'open')),
            'owner_user_id' => absint($signal['owner_user_id'] ?? 0),
            'due_date' => sanitize_text_field((string)($signal['due_date'] ?? '')),
            'priority' => sanitize_key((string)($signal['priority'] ?? 'normal')),
            'organization_unit_id' => absint($signal['organization_unit_id'] ?? 0),
            'signal_type' => sanitize_key((string)($signal['signal_type'] ?? '')),
            'source_type' => $source_type,
            'source_id' => $source_id,
            'source_label' => $label,
            'quantity' => $signal['quantity'] ?? '',
            'threshold' => $signal['threshold'] ?? '',
            'expected_quantity' => $signal['expected_quantity'] ?? '',
            'actual_quantity' => $signal['actual_quantity'] ?? '',
            'event_id' => absint($signal['event_id'] ?? 0),
            'notes' => sanitize_textarea_field((string)($signal['notes'] ?? '')),
        ];
    }

    /** @return array<string,array<string,mixed>> */
    public static function steps(array $source): array {
        $type = sanitize_key((string)($source['signal_type'] ?? ''));
        $status = sanitize_key((string)($source['status'] ?? 'open'));
        $active = !in_array($status, ['resolved','cancelled','archived'], true);
        $label = (string)($source['source_label'] ?? '') ?: sprintf(__('Inventory signal #%d', 'elev8-os'), absint($source['id'] ?? 0));
        $base = [
            'active' => $active,
            'owner_user_id' => absint($source['owner_user_id'] ?? 0),
            'due_date' => (string)($source['due_date'] ?? ''),
            'priority' => (string)($source['priority'] ?? 'normal'),
            'status' => $status === 'in_progress' ? 'in_progress' : ($status === 'waiting' ? 'waiting' : 'requested'),
            'type' => 'inventory',
        ];
        $definitions = [
            'low_stock' => [
                'title' => sprintf(__('Resolve low stock — %s', 'elev8-os'), $label),
                'description' => self::quantity_description($source),
                'checklist' => [__('Verify the current physical quantity', 'elev8-os'), __('Review open orders, transfers, and reserved quantity', 'elev8-os'), __('Choose reorder, transfer, production, or discontinue action', 'elev8-os'), __('Record the action and expected resolution date', 'elev8-os')],
                'required_approvals' => [__('Inventory action confirmed by responsible lead', 'elev8-os')],
                'completion_rules' => [__('WooCommerce stock recovers above its configured threshold or the signal is intentionally resolved', 'elev8-os')],
                'escalation' => ['after_days'=>1, 'priority'=>'urgent', 'notify_capability'=>'manage_inventory'],
            ],
            'receiving' => [
                'title' => sprintf(__('Receive inventory — %s', 'elev8-os'), $label),
                'description' => __('Receive and reconcile incoming inventory against the authoritative product record.', 'elev8-os'),
                'checklist' => [__('Verify shipment and source documentation', 'elev8-os'), __('Count and inspect received items', 'elev8-os'), __('Record damage or quantity variance', 'elev8-os'), __('Update the authoritative inventory quantity', 'elev8-os'), __('Place inventory in its assigned location', 'elev8-os')],
                'required_approvals' => [__('Receiving reconciliation approved', 'elev8-os')],
                'completion_rules' => [__('Received quantity and any variance are recorded', 'elev8-os')],
                'escalation' => ['after_days'=>2, 'priority'=>'high', 'notify_capability'=>'manage_inventory'],
            ],
            'cycle_count' => [
                'title' => sprintf(__('Complete cycle count — %s', 'elev8-os'), $label),
                'description' => __('Count physical inventory and reconcile it with the configured authoritative inventory provider.', 'elev8-os'),
                'checklist' => [__('Freeze or note active inventory movement', 'elev8-os'), __('Count the physical quantity independently', 'elev8-os'), __('Compare physical and authoritative quantities', 'elev8-os'), __('Record and investigate any variance', 'elev8-os'), __('Confirm the reconciled quantity', 'elev8-os')],
                'required_approvals' => [__('Cycle-count reconciliation approved', 'elev8-os')],
                'completion_rules' => [__('Expected and actual quantities are documented and reconciled', 'elev8-os')],
                'escalation' => ['after_days'=>3, 'priority'=>'high', 'notify_capability'=>'manage_inventory'],
            ],
            'discrepancy' => [
                'title' => sprintf(__('Investigate inventory discrepancy — %s', 'elev8-os'), $label),
                'description' => __('Determine why physical inventory differs from the authoritative quantity and record the correction.', 'elev8-os'),
                'checklist' => [__('Recount the physical inventory', 'elev8-os'), __('Review recent sales, returns, transfers, damage, and adjustments', 'elev8-os'), __('Identify the likely cause', 'elev8-os'), __('Correct the authoritative quantity with an audit note', 'elev8-os'), __('Record prevention or follow-up action', 'elev8-os')],
                'required_approvals' => [__('Inventory correction approved', 'elev8-os')],
                'completion_rules' => [__('Variance is reconciled and cause is documented', 'elev8-os')],
                'escalation' => ['after_days'=>1, 'priority'=>'urgent', 'notify_capability'=>'manage_inventory'],
            ],
            'event_reservation' => [
                'title' => sprintf(__('Prepare event inventory — %s', 'elev8-os'), $label),
                'description' => __('Reserve, stage, deliver, and reconcile inventory for the linked event without changing event ownership.', 'elev8-os'),
                'checklist' => [__('Confirm requested products and quantities', 'elev8-os'), __('Verify availability and reserve inventory', 'elev8-os'), __('Stage and label event inventory', 'elev8-os'), __('Confirm custody at event handoff', 'elev8-os'), __('Reconcile sold, returned, damaged, and missing inventory after the event', 'elev8-os')],
                'required_approvals' => [__('Event inventory reconciliation approved', 'elev8-os')],
                'completion_rules' => [__('All reserved event inventory is reconciled after return', 'elev8-os')],
                'escalation' => ['after_days'=>2, 'priority'=>'high', 'notify_capability'=>'manage_inventory'],
            ],
        ];
        $definition = $definitions[$type] ?? null;
        return $definition ? ['execution' => array_merge($base, $definition)] : [];
    }

    private static function source_label(string $source_type, int $source_id): string {
        if ($source_type === 'woocommerce_product' && $source_id > 0 && function_exists('wc_get_product')) {
            $product = wc_get_product($source_id);
            if ($product) { return (string)$product->get_name(); }
        }
        return apply_filters('elev8_os_inventory_source_label', sprintf('%s #%d', ucwords(str_replace('_', ' ', $source_type)), $source_id), $source_type, $source_id);
    }

    private static function quantity_description(array $source): string {
        $quantity = $source['quantity'] ?? '';
        $threshold = $source['threshold'] ?? '';
        if ($quantity !== '' && $threshold !== '') {
            return sprintf(__('Authoritative stock is %1$s; the configured low-stock threshold is %2$s. Verify physical inventory and resolve the exception.', 'elev8-os'), (string)$quantity, (string)$threshold);
        }
        return __('Verify physical inventory and resolve the low-stock exception in the authoritative inventory system.', 'elev8-os');
    }
}

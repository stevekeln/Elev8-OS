<?php
if (!defined('ABSPATH')) { exit; }

/** Converts authoritative cross-engine state changes into verified observations. */
final class Elev8_OS_Cross_Engine_Observation_Contributor {
    public static function init(): void {
        add_action('elev8_os_inventory_signal_changed', [__CLASS__, 'inventory'], 20, 2);
        add_action('elev8_os_maintenance_record_changed', [__CLASS__, 'maintenance'], 20, 2);
        add_action('elev8_os_event_application_changed', [__CLASS__, 'event_application'], 20, 2);
        add_action('elev8_os_class_booking_changed', [__CLASS__, 'booking'], 20, 2);
        add_action('elev8_os_conversation_created', [__CLASS__, 'conversation'], 20, 2);
    }

    public static function inventory(int $id, array $signal): void {
        $type = sanitize_key((string)($signal['signal_type'] ?? 'inventory'));
        $status = sanitize_key((string)($signal['status'] ?? 'open'));
        $priority = sanitize_key((string)($signal['priority'] ?? 'normal'));
        $summary = sprintf(__('Inventory signal %1$s is %2$s.', 'elev8-os'), str_replace('_', ' ', $type), str_replace('_', ' ', $status));
        if (isset($signal['quantity'], $signal['threshold'])) {
            $summary .= ' ' . sprintf(__('Authoritative quantity: %1$s; threshold: %2$s.', 'elev8-os'), (string)$signal['quantity'], (string)$signal['threshold']);
        }
        self::save('inventory_signal', $id, 'state', $summary, [$status === 'resolved' ? 'information' : 'risk'], self::severity($priority), absint($signal['organization_unit_id'] ?? 0), $status !== 'resolved', [['type'=>'inventory_signal','id'=>$id]]);
    }

    public static function maintenance(int $id, array $record): void {
        $status = sanitize_key((string)($record['status'] ?? 'open'));
        $priority = sanitize_key((string)($record['priority'] ?? 'normal'));
        $label = sanitize_text_field((string)($record['title'] ?? get_the_title($id)));
        $summary = sprintf(__('Maintenance record “%1$s” is %2$s.', 'elev8-os'), $label, str_replace('_', ' ', $status));
        $description = sanitize_textarea_field((string)($record['description'] ?? ''));
        if ($description !== '') { $summary .= ' ' . $description; }
        self::save('maintenance_record', $id, 'state', $summary, [$status === 'resolved' ? 'information' : 'risk'], self::severity($priority), absint($record['organization_unit_id'] ?? 0), !in_array($status, ['resolved','cancelled'], true), [['type'=>'maintenance_record','id'=>$id],['type'=>'asset','id'=>absint($record['asset_id'] ?? 0)]]);
    }

    public static function event_application(int $id, array $data): void {
        $status = sanitize_key((string)($data['status'] ?? get_post_meta($id, '_elev8_event_app_status', true) ?: 'new'));
        $summary = sprintf(__('Event application “%1$s” is %2$s.', 'elev8-os'), get_the_title($id), str_replace('_', ' ', $status));
        $classifications = in_array($status, ['approved','scheduled'], true) ? ['opportunity'] : (in_array($status, ['declined','cancelled'], true) ? ['decision'] : ['follow_up']);
        self::save('event_application', $id, 'status', $summary, $classifications, 'normal', 0, !in_array($status, ['approved','declined','cancelled','completed'], true), [['type'=>'application','id'=>$id]]);
    }

    public static function booking(int $id, array $booking): void {
        $status = sanitize_key((string)($booking['status'] ?? 'pending'));
        $title = sanitize_text_field((string)($booking['service_name'] ?? $booking['name'] ?? __('Class booking', 'elev8-os')));
        $summary = sprintf(__('Booking %1$d for “%2$s” is %3$s in Amelia.', 'elev8-os'), $id, $title, str_replace('_', ' ', $status));
        self::save('amelia_booking', $id, 'status', $summary, [in_array($status, ['approved','rejected','cancelled'], true) ? 'decision' : 'follow_up'], 'normal', absint($booking['organization_unit_id'] ?? 0), !in_array($status, ['approved','rejected','cancelled'], true), [['type'=>'booking','id'=>$id]]);
    }

    public static function conversation(int $id, array $data): void {
        $subject = sanitize_text_field((string)($data['subject'] ?? get_the_title($id)));
        $summary = sprintf(__('Conversation started: %s', 'elev8-os'), $subject);
        self::save('conversation', $id, 'created', $summary, ['information'], 'normal', 0, false, [['type'=>'communication','id'=>$id]]);
    }

    private static function save(string $source_type, int $source_id, string $key, string $summary, array $classifications, string $severity, int $organization_id, bool $requires_action, array $related): void {
        if (!class_exists('Elev8_OS_Observation_Service')) { return; }
        Elev8_OS_Observation_Service::upsert([
            'source_type'=>$source_type, 'source_id'=>$source_id, 'source_key'=>$key,
            'summary'=>$summary, 'classifications'=>$classifications, 'severity'=>$severity,
            'confidence'=>100, 'organization_unit_id'=>$organization_id,
            'requires_action'=>$requires_action, 'related_objects'=>array_values(array_filter($related, static fn($r)=>!empty($r['id']))),
        ]);
    }

    private static function severity(string $priority): string {
        return ['urgent'=>'critical','high'=>'high','low'=>'low'][$priority] ?? 'normal';
    }
}

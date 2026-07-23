<?php
if (!defined('ABSPATH')) { exit; }

/** Technology-support adapter over Maintenance, Assets, Operations, and Communication. */
final class Elev8_OS_IT_Support_Service {
    public const OPTION_SUPPORT_USERS = 'elev8_os_it_support_users';
    public const SOURCE_TYPE = 'it_support_incident';

    public static function init(): void {
        add_filter('elev8_os_business_graph_objects', [__CLASS__, 'register_graph_objects']);
    }

    /** @return array<string,string> */
    public static function incident_types(): array {
        return [
            'computer_down' => __('Computer down', 'elev8-os'),
            'internet_wifi' => __('Internet or Wi-Fi', 'elev8-os'),
            'printer' => __('Printer', 'elev8-os'),
            'phone_tablet' => __('Phone or tablet', 'elev8-os'),
            'login_access' => __('Login or account access', 'elev8-os'),
            'software' => __('Software issue', 'elev8-os'),
            'security' => __('Security concern', 'elev8-os'),
            'employee_setup' => __('New employee setup', 'elev8-os'),
            'installation' => __('Equipment installation', 'elev8-os'),
            'website_email' => __('Website or email', 'elev8-os'),
            'recurring_maintenance' => __('Recurring technology maintenance', 'elev8-os'),
            'other' => __('Other technology issue', 'elev8-os'),
        ];
    }

    /** @return int[] */
    public static function support_user_ids(): array {
        $ids = array_values(array_unique(array_filter(array_map('absint', (array) get_option(self::OPTION_SUPPORT_USERS, [])))));
        return array_values(array_filter($ids, static function(int $id): bool {
            $user = get_user_by('id', $id);
            return $user instanceof WP_User && Elev8_OS_Access_Service::can_receive_assignment($user);
        }));
    }

    /** @param int[] $ids */
    public static function save_support_user_ids(array $ids): void {
        update_option(self::OPTION_SUPPORT_USERS, array_values(array_unique(array_filter(array_map('absint', $ids)))), false);
    }

    public static function is_support_user(int $user_id): bool {
        return in_array($user_id, self::support_user_ids(), true) || user_can($user_id, 'elev8_manage_operations') || user_can($user_id, 'manage_options');
    }

    /** @param array<string,mixed> $args @return int|WP_Error */
    public static function report(array $args) {
        $type = sanitize_key((string) ($args['incident_type'] ?? 'other'));
        if (!isset(self::incident_types()[$type])) { $type = 'other'; }
        $critical = !empty($args['critical']);
        $owner = absint($args['owner_user_id'] ?? 0);
        if ($owner < 1) { $owner = absint(self::support_user_ids()[0] ?? 0); }
        $due = sanitize_text_field((string) ($args['due_date'] ?? ''));
        if ($due === '') { $due = current_time('Y-m-d'); }
        $record_id = Elev8_OS_Maintenance_Service::upsert([
            'maintenance_type' => $type === 'recurring_maintenance' ? 'preventive_maintenance' : 'maintenance_request',
            'source_type' => self::SOURCE_TYPE,
            'source_id' => absint($args['source_id'] ?? 0),
            'status' => $owner > 0 ? 'assigned' : 'open',
            'priority' => $critical ? 'urgent' : sanitize_key((string) ($args['priority'] ?? 'normal')),
            'owner_user_id' => $owner,
            'requested_by_user_id' => absint($args['requested_by_user_id'] ?? get_current_user_id()),
            'organization_unit_id' => absint($args['organization_unit_id'] ?? 0),
            'asset_id' => absint($args['asset_id'] ?? 0),
            'asset_type' => 'technology',
            'asset_label' => sanitize_text_field((string) ($args['asset_label'] ?? '')),
            'location_label' => sanitize_text_field((string) ($args['location_label'] ?? '')),
            'description' => sanitize_textarea_field((string) ($args['description'] ?? '')),
            'due_date' => $due,
            'recurrence_days' => $type === 'recurring_maintenance' ? max(1, absint($args['recurrence_days'] ?? 30)) : 0,
            'context' => [
                'capability' => 'it_support',
                'it_incident_type' => $type,
                'critical_operations' => $critical ? 1 : 0,
                'business_impact' => sanitize_textarea_field((string) ($args['business_impact'] ?? '')),
            ],
        ]);
        if (!is_wp_error($record_id)) { self::notify_assignment((int) $record_id); }
        return $record_id;
    }

    /** @return array<int,array<string,mixed>> */
    public static function incidents(int $user_id = 0, int $limit = 100): array {
        $query = [
            'post_type' => Elev8_OS_Maintenance_Service::POST_TYPE,
            'post_status' => 'publish', 'posts_per_page' => max(1, min(500, $limit)),
            'fields' => 'ids', 'orderby' => 'date', 'order' => 'DESC',
            'meta_query' => [['key' => '_elev8_maintenance_source_type', 'value' => self::SOURCE_TYPE]],
        ];
        $records = array_map([Elev8_OS_Maintenance_Service::class, 'get'], array_map('absint', get_posts($query)));
        if ($user_id > 0 && !self::is_support_user($user_id)) {
            $records = array_values(array_filter($records, static fn(array $r): bool => absint($r['requested_by_user_id'] ?? 0) === $user_id));
        }
        return array_values(array_filter($records));
    }

    public static function notify_assignment(int $record_id): void {
        $record = Elev8_OS_Maintenance_Service::get($record_id);
        $owner = get_user_by('id', absint($record['owner_user_id'] ?? 0));
        if (!$owner instanceof WP_User || !$owner->user_email) { return; }
        $subject = sprintf(__('IT support assigned: %s', 'elev8-os'), (string) ($record['title'] ?? __('Technology incident', 'elev8-os')));
        $message = sprintf("%s\n\n%s\n%s", (string) ($record['description'] ?? ''), __('Open IT Support:', 'elev8-os'), Elev8_OS_Portal_Page_Manager::get_url('it_support'));
        Elev8_OS_Notification_Service::send_email($owner->user_email, $subject, $message);
    }

    public static function register_graph_objects(array $objects): array {
        $objects['it_incident'] = ['label' => __('IT Incident', 'elev8-os'), 'owner_engine' => 'operations', 'authoritative_source' => Elev8_OS_Maintenance_Service::POST_TYPE];
        return $objects;
    }
}

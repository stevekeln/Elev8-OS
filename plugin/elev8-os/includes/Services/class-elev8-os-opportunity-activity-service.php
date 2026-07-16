<?php
/**
 * Elev8 OS Opportunity Activity service.
 *
 * Owns the permanent audit timeline for opportunity and customer-interest actions.
 */
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Opportunity_Activity_Service {
    private const DB_VERSION = '1.0.0';
    private const DB_OPTION = 'elev8_os_opportunity_activity_db_version';

    public static function maybe_upgrade(): void {
        if (get_option(self::DB_OPTION) !== self::DB_VERSION) {
            self::create_table();
            self::backfill_created_events();
            update_option(self::DB_OPTION, self::DB_VERSION, false);
        }
    }

    private static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'elev8_opportunity_activity';
    }

    private static function create_table(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $table = self::table();
        dbDelta("CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            opportunity_id bigint(20) unsigned NOT NULL,
            interest_id bigint(20) unsigned NOT NULL DEFAULT 0,
            event_type varchar(80) NOT NULL,
            event_label varchar(190) NOT NULL,
            event_details text NULL,
            actor_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY opportunity_created (opportunity_id,created_at),
            KEY interest_id (interest_id),
            KEY event_type (event_type)
        ) {$charset};");
    }

    private static function backfill_created_events(): void {
        global $wpdb;
        $opportunities = $wpdb->prefix . 'elev8_opportunities';
        $activity = self::table();
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $opportunities)) !== $opportunities) { return; }
        $wpdb->query("INSERT INTO {$activity} (opportunity_id,interest_id,event_type,event_label,event_details,actor_user_id,created_at)
            SELECT o.id,0,'opportunity_created','Opportunity created','Imported from the existing opportunity record.',0,o.created_at
            FROM {$opportunities} o
            LEFT JOIN {$activity} a ON a.opportunity_id=o.id AND a.event_type='opportunity_created'
            WHERE a.id IS NULL");
    }

    public static function record(int $opportunity_id, string $event_type, string $label, string $details = '', int $interest_id = 0): int {
        global $wpdb;
        if ($opportunity_id <= 0 || $event_type === '' || $label === '') { return 0; }
        $wpdb->insert(self::table(), [
            'opportunity_id' => $opportunity_id,
            'interest_id' => max(0, $interest_id),
            'event_type' => sanitize_key($event_type),
            'event_label' => sanitize_text_field($label),
            'event_details' => sanitize_textarea_field($details),
            'actor_user_id' => get_current_user_id(),
            'created_at' => current_time('mysql'),
        ]);
        return (int) $wpdb->insert_id;
    }

    public static function for_opportunity(int $opportunity_id, int $limit = 100): array {
        global $wpdb;
        if ($opportunity_id <= 0) { return []; }
        $limit = min(250, max(1, $limit));
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE opportunity_id=%d ORDER BY created_at DESC, id DESC LIMIT %d',
            $opportunity_id,
            $limit
        ), ARRAY_A);
        return is_array($rows) ? $rows : [];
    }
}

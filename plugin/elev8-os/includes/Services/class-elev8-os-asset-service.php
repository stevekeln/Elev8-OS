<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WordPress-owned source of truth for artist artwork and creative assets.
 */
final class Elev8_OS_Asset_Service {

    private const DB_VERSION = '1.0.0';
    private const DB_VERSION_OPTION = 'elev8_os_asset_db_version';

    public static function init(): void {
        add_action('init', [__CLASS__, 'maybe_upgrade'], 5);
    }

    public static function activate(): void {
        self::install();
    }

    public static function maybe_upgrade(): void {
        if ((string) get_option(self::DB_VERSION_OPTION, '') !== self::DB_VERSION) {
            self::install();
        }
    }

    private static function install(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            owner_user_id bigint(20) unsigned NOT NULL,
            title varchar(200) NOT NULL,
            description longtext NOT NULL,
            medium varchar(160) NOT NULL DEFAULT '',
            dimensions varchar(160) NOT NULL DEFAULT '',
            price decimal(12,2) NULL,
            status varchar(30) NOT NULL DEFAULT 'draft',
            image_attachment_id bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY owner_user_id (owner_user_id),
            KEY owner_status (owner_user_id,status),
            KEY updated_at (updated_at)
        ) {$charset_collate};";

        dbDelta($sql);
        update_option(self::DB_VERSION_OPTION, self::DB_VERSION, false);
    }

    public static function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . 'elev8_os_assets';
    }

    /** @return array<int,array<string,mixed>> */
    public static function get_for_owner(int $owner_user_id): array {
        global $wpdb;
        if ($owner_user_id <= 0) {
            return [];
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . self::table_name() . ' WHERE owner_user_id = %d ORDER BY updated_at DESC, id DESC',
                $owner_user_id
            ),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    /** @return array<string,mixed>|null */
    public static function get(int $asset_id): ?array {
        global $wpdb;
        if ($asset_id <= 0) {
            return null;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . self::table_name() . ' WHERE id = %d', $asset_id),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string,mixed> $data
     * @return int|WP_Error
     */
    public static function save(array $data) {
        global $wpdb;

        $asset_id = absint($data['id'] ?? 0);
        $owner_user_id = absint($data['owner_user_id'] ?? 0);
        $title = sanitize_text_field((string) ($data['title'] ?? ''));
        if ($owner_user_id <= 0 || $title === '') {
            return new WP_Error('elev8_asset_invalid', __('Artwork owner and title are required.', 'elev8-os'));
        }

        $status = sanitize_key((string) ($data['status'] ?? 'draft'));
        if (!in_array($status, self::statuses(), true)) {
            $status = 'draft';
        }

        $price = null;
        $raw_price = trim((string) ($data['price'] ?? ''));
        if ($raw_price !== '') {
            $price = round((float) $raw_price, 2);
            if ($price < 0) {
                return new WP_Error('elev8_asset_price', __('Price cannot be negative.', 'elev8-os'));
            }
        }

        $now = current_time('mysql');
        $record = [
            'owner_user_id' => $owner_user_id,
            'title' => $title,
            'description' => sanitize_textarea_field((string) ($data['description'] ?? '')),
            'medium' => sanitize_text_field((string) ($data['medium'] ?? '')),
            'dimensions' => sanitize_text_field((string) ($data['dimensions'] ?? '')),
            'price' => $price,
            'status' => $status,
            'image_attachment_id' => absint($data['image_attachment_id'] ?? 0) ?: null,
            'updated_at' => $now,
        ];

        if ($asset_id > 0) {
            $existing = self::get($asset_id);
            if (!$existing || (int) $existing['owner_user_id'] !== $owner_user_id) {
                return new WP_Error('elev8_asset_not_found', __('Artwork record was not found.', 'elev8-os'));
            }

            $updated = $wpdb->update(self::table_name(), $record, ['id' => $asset_id], null, ['%d']);
            return $updated === false
                ? new WP_Error('elev8_asset_save_failed', __('Artwork could not be updated.', 'elev8-os'))
                : $asset_id;
        }

        $record['created_at'] = $now;
        $inserted = $wpdb->insert(self::table_name(), $record);
        return $inserted === false
            ? new WP_Error('elev8_asset_save_failed', __('Artwork could not be created.', 'elev8-os'))
            : (int) $wpdb->insert_id;
    }

    public static function delete(int $asset_id, int $owner_user_id): bool {
        global $wpdb;
        if ($asset_id <= 0 || $owner_user_id <= 0) {
            return false;
        }

        return $wpdb->delete(
            self::table_name(),
            ['id' => $asset_id, 'owner_user_id' => $owner_user_id],
            ['%d', '%d']
        ) === 1;
    }

    /** @return string[] */
    public static function statuses(): array {
        return ['draft', 'available', 'sold', 'archived'];
    }
}

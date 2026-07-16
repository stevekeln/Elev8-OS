<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WordPress-owned source of truth for artwork, products, and future creative assets.
 */
final class Elev8_OS_Asset_Service {

    private const DB_VERSION = '3.0.0';
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
            asset_type varchar(40) NOT NULL DEFAULT 'artwork',
            asset_number varchar(80) NOT NULL DEFAULT '',
            title varchar(200) NOT NULL,
            description longtext NOT NULL,
            medium varchar(160) NOT NULL DEFAULT '',
            dimensions varchar(160) NOT NULL DEFAULT '',
            price decimal(12,2) NULL,
            status varchar(30) NOT NULL DEFAULT 'draft',
            location varchar(40) NOT NULL DEFAULT 'at_elev8',
            quantity int(10) unsigned NOT NULL DEFAULT 1,
            received_date date NULL,
            public_visibility tinyint(1) unsigned NOT NULL DEFAULT 1,
            sell_online tinyint(1) unsigned NOT NULL DEFAULT 1,
            internal_notes longtext NOT NULL,
            image_attachment_id bigint(20) unsigned NULL,
            wc_product_id bigint(20) unsigned NULL,
            public_view_count bigint(20) unsigned NOT NULL DEFAULT 0,
            qr_scan_count bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY asset_number (asset_number),
            KEY owner_user_id (owner_user_id),
            KEY owner_status (owner_user_id,status),
            KEY wc_product_id (wc_product_id),
            KEY updated_at (updated_at)
        ) {$charset_collate};";

        dbDelta($sql);

        // Backfill stable asset numbers for records created before Inventory Foundation.
        $missing_ids = $wpdb->get_col("SELECT id FROM {$table} WHERE asset_number = '' OR asset_number IS NULL");
        if (is_array($missing_ids)) {
            foreach ($missing_ids as $missing_id) {
                $id = absint($missing_id);
                if ($id > 0) {
                    $wpdb->update($table, ['asset_number' => self::generate_asset_number($id)], ['id' => $id], ['%s'], ['%d']);
                }
            }
        }

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

    /** @return array<int,array<string,mixed>> */
    public static function get_all(int $limit = 1000): array {
        global $wpdb;
        $limit = max(1, min(5000, $limit));
        $rows = $wpdb->get_results(
            $wpdb->prepare('SELECT * FROM ' . self::table_name() . ' ORDER BY id ASC LIMIT %d', $limit),
            ARRAY_A
        );
        return is_array($rows) ? $rows : [];
    }

    /**
     * Return inventory records that are safe to publish on an artist storefront.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function get_public_for_owner(int $owner_user_id): array {
        global $wpdb;
        if ($owner_user_id <= 0) {
            return [];
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . self::table_name() . " WHERE owner_user_id = %d AND public_visibility = 1 AND status IN ('available','reserved','sold') ORDER BY CASE status WHEN 'available' THEN 0 WHEN 'reserved' THEN 1 ELSE 2 END, updated_at DESC, id DESC",
                $owner_user_id
            ),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }


    /** Return the canonical public page for one asset. */
    public static function get_public_url(array $asset, bool $qr_scan = false): string {
        $asset_id = absint($asset['id'] ?? 0);
        if ($asset_id <= 0) {
            return '';
        }
        $slug = sanitize_title((string) ($asset['title'] ?? 'artwork'));
        $url = home_url('/artwork/' . $asset_id . '/' . ($slug !== '' ? $slug : 'item') . '/');
        return $qr_scan ? add_query_arg('elev8_qr', '1', $url) : $url;
    }

    /** Record a public view or physical QR scan without changing asset content. */
    public static function record_public_view(int $asset_id, bool $qr_scan = false): void {
        global $wpdb;
        if ($asset_id <= 0) {
            return;
        }
        $column = $qr_scan ? 'qr_scan_count' : 'public_view_count';
        $wpdb->query(
            $wpdb->prepare(
                'UPDATE ' . self::table_name() . " SET {$column} = {$column} + 1 WHERE id = %d",
                $asset_id
            )
        );
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

    /** @return array<string,mixed>|null */
    public static function get_by_product_id(int $product_id): ?array {
        global $wpdb;
        if ($product_id <= 0) {
            return null;
        }
        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . self::table_name() . ' WHERE wc_product_id = %d', $product_id),
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

        $status = sanitize_key((string) ($data['status'] ?? 'available'));
        if (!in_array($status, self::statuses(), true)) {
            $status = 'available';
        }

        $location = sanitize_key((string) ($data['location'] ?? 'at_elev8'));
        if (!array_key_exists($location, self::locations())) {
            $location = 'at_elev8';
        }

        $price = null;
        $raw_price = trim((string) ($data['price'] ?? ''));
        if ($raw_price !== '') {
            $price = round((float) $raw_price, 2);
            if ($price < 0) {
                return new WP_Error('elev8_asset_price', __('Price cannot be negative.', 'elev8-os'));
            }
        }

        $quantity = max(0, absint($data['quantity'] ?? 1));
        if ($status === 'sold') {
            $quantity = 0;
            $location = 'sold';
        }

        $received_date = sanitize_text_field((string) ($data['received_date'] ?? ''));
        if ($received_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $received_date)) {
            $received_date = '';
        }

        $existing = $asset_id > 0 ? self::get($asset_id) : null;
        if ($asset_id > 0 && (!$existing || (int) $existing['owner_user_id'] !== $owner_user_id)) {
            return new WP_Error('elev8_asset_not_found', __('Artwork record was not found.', 'elev8-os'));
        }

        $now = current_time('mysql');
        $record = [
            'owner_user_id' => $owner_user_id,
            'asset_type' => sanitize_key((string) ($data['asset_type'] ?? 'artwork')) ?: 'artwork',
            'asset_number' => $existing ? (string) $existing['asset_number'] : '',
            'title' => $title,
            'description' => sanitize_textarea_field((string) ($data['description'] ?? '')),
            'medium' => sanitize_text_field((string) ($data['medium'] ?? '')),
            'dimensions' => sanitize_text_field((string) ($data['dimensions'] ?? '')),
            'price' => $price,
            'status' => $status,
            'location' => $location,
            'quantity' => $quantity,
            'received_date' => $received_date !== '' ? $received_date : null,
            'public_visibility' => empty($data['public_visibility']) ? 0 : 1,
            'sell_online' => empty($data['sell_online']) ? 0 : 1,
            'internal_notes' => sanitize_textarea_field((string) ($data['internal_notes'] ?? '')),
            'image_attachment_id' => absint($data['image_attachment_id'] ?? 0) ?: null,
            'wc_product_id' => $existing ? (absint($existing['wc_product_id'] ?? 0) ?: null) : null,
            'updated_at' => $now,
        ];

        if ($asset_id > 0) {
            $updated = $wpdb->update(self::table_name(), $record, ['id' => $asset_id], null, ['%d']);
            if ($updated === false) {
                return new WP_Error('elev8_asset_save_failed', __('Artwork could not be updated.', 'elev8-os'));
            }
        } else {
            $record['created_at'] = $now;
            $inserted = $wpdb->insert(self::table_name(), $record);
            if ($inserted === false) {
                return new WP_Error('elev8_asset_save_failed', __('Artwork could not be created.', 'elev8-os'));
            }
            $asset_id = (int) $wpdb->insert_id;
            $asset_number = self::generate_asset_number($asset_id);
            $wpdb->update(self::table_name(), ['asset_number' => $asset_number], ['id' => $asset_id], ['%s'], ['%d']);
        }

        do_action('elev8_os_asset_saved', $asset_id, self::get($asset_id), $existing);
        return $asset_id;
    }

    /**
     * Update operational fields without changing ownership or descriptive data.
     *
     * @param array<string,mixed> $fields
     */
    public static function update_operational_fields(int $asset_id, array $fields): bool {
        global $wpdb;
        $allowed = ['status', 'location', 'quantity', 'wc_product_id'];
        $record = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $fields)) {
                $record[$key] = $fields[$key];
            }
        }
        if (!$record) {
            return false;
        }
        $record['updated_at'] = current_time('mysql');
        return $wpdb->update(self::table_name(), $record, ['id' => $asset_id], null, ['%d']) !== false;
    }

    public static function delete(int $asset_id, int $owner_user_id): bool {
        global $wpdb;
        if ($asset_id <= 0 || $owner_user_id <= 0) {
            return false;
        }

        $asset = self::get($asset_id);
        if (!$asset || (int) $asset['owner_user_id'] !== $owner_user_id) {
            return false;
        }

        $deleted = $wpdb->delete(
            self::table_name(),
            ['id' => $asset_id, 'owner_user_id' => $owner_user_id],
            ['%d', '%d']
        ) === 1;

        if ($deleted) {
            do_action('elev8_os_asset_deleted', $asset_id, $asset);
        }
        return $deleted;
    }

    public static function get_product_url(array $asset): string {
        $product_id = absint($asset['wc_product_id'] ?? 0);
        if ($product_id <= 0 || get_post_status($product_id) !== 'publish') {
            return '';
        }
        $url = get_permalink($product_id);
        return is_string($url) ? $url : '';
    }

    public static function generate_asset_number(int $asset_id): string {
        return 'E8-A' . str_pad((string) $asset_id, 6, '0', STR_PAD_LEFT);
    }

    /** @return string[] */
    public static function statuses(): array {
        return ['draft', 'available', 'reserved', 'sold', 'archived'];
    }

    /** @return array<string,string> */
    public static function locations(): array {
        return [
            'at_elev8' => __('At Elev8 Arts', 'elev8-os'),
            'with_artist' => __('With artist', 'elev8-os'),
            'sold' => __('Sold', 'elev8-os'),
            'removed' => __('Removed', 'elev8-os'),
            'other' => __('Other', 'elev8-os'),
        ];
    }
}

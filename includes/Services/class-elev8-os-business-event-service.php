<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Normalizes verified activity from trusted systems into a common event shape.
 * It does not copy Amelia or WooCommerce records into Elev8 OS tables.
 */
final class Elev8_OS_Business_Event_Service {
    /** @param array<string,mixed> $snapshot @return array<int,array<string,mixed>> */
    public static function timeline(array $snapshot, int $limit = 12): array {
        $events = [];
        $command = (array) ($snapshot['command_center'] ?? []);
        foreach ((array) ($command['activity'] ?? []) as $item) {
            $events[] = [
                'type' => sanitize_key((string) ($item['type'] ?? 'activity')),
                'icon' => sanitize_key((string) ($item['icon'] ?? 'marker')),
                'title' => (string) ($item['title'] ?? __('Business activity', 'elev8-os')),
                'detail' => (string) ($item['detail'] ?? ''),
                'timestamp' => (int) ($item['timestamp'] ?? 0),
                'source' => self::source_for((string) ($item['type'] ?? '')),
            ];
        }
        return array_slice($events, 0, max(1, $limit));
    }

    private static function source_for(string $type): string {
        if ($type === 'sale') { return 'WooCommerce'; }
        if ($type === 'class') { return 'Amelia'; }
        return 'Elev8 OS';
    }
}

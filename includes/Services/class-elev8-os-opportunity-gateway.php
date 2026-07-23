<?php
/**
 * Stable application-facing gateway for the existing Opportunity Engine.
 * It centralizes access without replacing the verified persistence service.
 */
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Opportunity_Gateway {
    private static function available(): bool { return class_exists('Elev8_OS_Opportunity_Service'); }

    public static function all(): array { return self::available() ? Elev8_OS_Opportunity_Service::all() : []; }
    public static function get(int $id): ?array { return self::available() ? Elev8_OS_Opportunity_Service::get($id) : null; }
    public static function save(array $data): int { return self::available() ? (int) Elev8_OS_Opportunity_Service::save_opportunity($data) : 0; }
    public static function interests(int $opportunity_id): array { return self::available() ? Elev8_OS_Opportunity_Service::interests($opportunity_id) : []; }
    public static function get_interest(int $interest_id): ?array { return self::available() ? Elev8_OS_Opportunity_Service::get_interest($interest_id) : null; }
    public static function add_interest(array $data): int { return self::available() ? (int) Elev8_OS_Opportunity_Service::add_interest($data) : 0; }
    public static function update_interest(array $data): bool { return self::available() && (bool) Elev8_OS_Opportunity_Service::update_interest($data); }
    public static function delete_interest(int $interest_id): bool { return self::available() && (bool) Elev8_OS_Opportunity_Service::delete_interest($interest_id); }
    public static function interest_statuses(): array { return self::available() ? Elev8_OS_Opportunity_Service::interest_statuses() : []; }
}

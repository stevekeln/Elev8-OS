<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Central role/capability resolver for role-aware Elev8 OS experiences.
 * WordPress remains the source of truth for authentication and roles; this
 * service translates those roles into Elev8 OS work capabilities.
 */
final class Elev8_OS_Access_Service {
    public const ROLE_TEACHER = 'elev8_teacher';
    public const ROLE_DJ = 'elev8_open_mic_dj';
    public const ROLE_RETAIL = 'elev8_retail_employee';

    public static function init(): void {
        add_action('init', [__CLASS__, 'ensure_roles'], 5);
    }

    public static function activate(): void {
        self::ensure_roles();
    }

    public static function ensure_roles(): void {
        add_role(self::ROLE_TEACHER, __('Elev8 Teacher', 'elev8-os'), ['read' => true]);
        add_role(self::ROLE_DJ, __('Elev8 Open Mic DJ', 'elev8-os'), ['read' => true]);
        add_role(self::ROLE_RETAIL, __('Elev8 Retail Employee', 'elev8-os'), ['read' => true]);
    }

    public static function is_owner(WP_User $user): bool {
        return user_can($user, 'manage_options');
    }

    public static function is_manager(WP_User $user): bool {
        return self::is_owner($user) || self::has_any_role($user, ['shop_manager', 'editor']);
    }

    public static function is_retail_employee(WP_User $user): bool {
        if (self::is_manager($user)) { return false; }
        return self::has_any_role($user, [self::ROLE_RETAIL, 'author', 'contributor', 'subscriber']);
    }

    public static function is_teacher(WP_User $user): bool {
        return self::has_any_role($user, [self::ROLE_TEACHER]);
    }

    public static function is_dj(WP_User $user): bool {
        return self::has_any_role($user, [self::ROLE_DJ]);
    }

    public static function is_artist(WP_User $user): bool {
        return class_exists('Elev8_OS_Identity_Service') && Elev8_OS_Identity_Service::artist_for_user($user) !== null;
    }

    public static function can_distribute_flyers(WP_User $user): bool {
        return self::is_owner($user) || self::is_manager($user) || self::is_retail_employee($user) || self::is_dj($user);
    }

    public static function allowed_operations_templates(WP_User $user): array {
        if (self::is_owner($user)) { return array_keys(Elev8_OS_Daily_Operations_Service::templates()); }
        $allowed = [];
        if (self::is_manager($user)) { $allowed[] = 'manager'; }
        if (self::is_retail_employee($user)) { $allowed[] = 'retail'; }
        if (self::is_artist($user) || self::is_teacher($user)) { $allowed[] = 'artist'; }
        if (self::is_dj($user)) { $allowed[] = 'event'; }
        return array_values(array_unique($allowed));
    }

    private static function has_any_role(WP_User $user, array $roles): bool {
        return (bool) array_intersect((array) $user->roles, $roles);
    }
}

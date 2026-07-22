<?php
/**
 * Organization Engine foundation.
 *
 * WordPress remains authoritative for people/authentication. This service owns
 * the configurable organization structure and scoped person assignments that
 * connect those people to businesses, brands, locations, departments, and teams.
 */
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Organization_Service {
    public const UNIT_POST_TYPE = 'elev8_org_unit';
    public const ASSIGNMENT_POST_TYPE = 'elev8_org_assign';

    public static function init(): void {
        add_action('init', [__CLASS__, 'register_post_types'], 8);
        add_filter('elev8_os_workspace_summary', [__CLASS__, 'workspace_summary'], 10, 3);
        add_filter('elev8_os_workspace_can_view', [__CLASS__, 'workspace_can_view'], 10, 4);
    }

    public static function activate(): void {
        self::register_post_types();
    }

    public static function register_post_types(): void {
        register_post_type(self::UNIT_POST_TYPE, [
            'labels' => ['name' => __('Organization Units', 'elev8-os'), 'singular_name' => __('Organization Unit', 'elev8-os')],
            'public' => false,
            'show_ui' => false,
            'show_in_menu' => false,
            'supports' => ['title', 'editor', 'author'],
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ]);
        register_post_type(self::ASSIGNMENT_POST_TYPE, [
            'labels' => ['name' => __('Organization Assignments', 'elev8-os'), 'singular_name' => __('Organization Assignment', 'elev8-os')],
            'public' => false,
            'show_ui' => false,
            'show_in_menu' => false,
            'supports' => ['title', 'author'],
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ]);
    }

    /** @return array<string,string> */
    public static function unit_types(): array {
        return (array) apply_filters('elev8_os_organization_unit_types', [
            'business' => __('Business', 'elev8-os'),
            'brand' => __('Brand', 'elev8-os'),
            'location' => __('Location', 'elev8-os'),
            'department' => __('Department', 'elev8-os'),
            'team' => __('Team', 'elev8-os'),
        ]);
    }

    /** @return array<string,string> */
    public static function assignment_types(): array {
        return (array) apply_filters('elev8_os_organization_assignment_types', [
            'owner' => __('Owner', 'elev8-os'),
            'executive' => __('Executive', 'elev8-os'),
            'manager' => __('Manager', 'elev8-os'),
            'lead' => __('Team Lead', 'elev8-os'),
            'member' => __('Team Member', 'elev8-os'),
            'contractor' => __('Contractor', 'elev8-os'),
            'volunteer' => __('Volunteer', 'elev8-os'),
        ]);
    }

    public static function can_manage(?WP_User $user = null): bool {
        $user = $user instanceof WP_User ? $user : wp_get_current_user();
        return $user instanceof WP_User && Elev8_OS_Access_Service::user_can('manage_organization', $user);
    }

    public static function can_view(?WP_User $user = null): bool {
        $user = $user instanceof WP_User ? $user : wp_get_current_user();
        return $user instanceof WP_User && Elev8_OS_Access_Service::user_can('view_organization', $user);
    }

    public static function save_unit(array $data, int $unit_id = 0): int {
        if (!self::can_manage()) { return 0; }
        $name = sanitize_text_field((string) ($data['name'] ?? ''));
        $type = sanitize_key((string) ($data['type'] ?? ''));
        if ($name === '' || !isset(self::unit_types()[$type])) { return 0; }

        $parent_id = absint($data['parent_id'] ?? 0);
        if ($parent_id && get_post_type($parent_id) !== self::UNIT_POST_TYPE) { $parent_id = 0; }
        if ($unit_id && $parent_id === $unit_id) { $parent_id = 0; }

        $post = [
            'post_type' => self::UNIT_POST_TYPE,
            'post_status' => 'publish',
            'post_title' => $name,
            'post_content' => wp_kses_post((string) ($data['description'] ?? '')),
            'post_author' => get_current_user_id(),
        ];
        if ($unit_id > 0) { $post['ID'] = $unit_id; $result = wp_update_post($post, true); }
        else { $result = wp_insert_post($post, true); }
        if (is_wp_error($result) || !$result) { return 0; }
        $unit_id = (int) $result;

        $status = sanitize_key((string) ($data['status'] ?? 'active'));
        if (!in_array($status, ['active', 'inactive', 'archived'], true)) { $status = 'active'; }
        $meta = [
            '_elev8_org_type' => $type,
            '_elev8_org_parent_id' => $parent_id,
            '_elev8_org_status' => $status,
            '_elev8_org_code' => sanitize_text_field((string) ($data['code'] ?? '')),
            '_elev8_org_address' => sanitize_textarea_field((string) ($data['address'] ?? '')),
            '_elev8_org_timezone' => sanitize_text_field((string) ($data['timezone'] ?? wp_timezone_string())),
        ];
        foreach ($meta as $key => $value) { update_post_meta($unit_id, $key, $value); }

        self::record_activity($unit_id, $unit_id === absint($data['existing_id'] ?? 0) ? 'organization_unit_updated' : 'organization_unit_saved', sprintf(__('Organization unit “%s” was saved.', 'elev8-os'), $name));
        do_action('elev8_os_organization_unit_saved', $unit_id, self::get_unit($unit_id));
        return $unit_id;
    }

    public static function set_status(int $unit_id, string $status): bool {
        if (!self::can_manage() || get_post_type($unit_id) !== self::UNIT_POST_TYPE) { return false; }
        $status = sanitize_key($status);
        if (!in_array($status, ['active', 'inactive', 'archived'], true)) { return false; }
        update_post_meta($unit_id, '_elev8_org_status', $status);
        self::record_activity($unit_id, 'organization_unit_status_changed', sprintf(__('Organization unit status changed to %s.', 'elev8-os'), $status));
        return true;
    }

    public static function save_assignment(array $data, int $assignment_id = 0): int {
        if (!self::can_manage()) { return 0; }
        $user_id = absint($data['user_id'] ?? 0);
        $unit_id = absint($data['unit_id'] ?? 0);
        $assignment_type = sanitize_key((string) ($data['assignment_type'] ?? 'member'));
        $responsibility = sanitize_text_field((string) ($data['responsibility'] ?? ''));
        if (!$user_id || !get_userdata($user_id) || get_post_type($unit_id) !== self::UNIT_POST_TYPE || !isset(self::assignment_types()[$assignment_type])) { return 0; }

        if (!$assignment_id) {
            $existing = get_posts([
                'post_type' => self::ASSIGNMENT_POST_TYPE,
                'post_status' => 'publish',
                'posts_per_page' => 1,
                'fields' => 'ids',
                'meta_query' => [
                    ['key' => '_elev8_org_assignment_user_id', 'value' => $user_id, 'type' => 'NUMERIC'],
                    ['key' => '_elev8_org_assignment_unit_id', 'value' => $unit_id, 'type' => 'NUMERIC'],
                    ['key' => '_elev8_org_assignment_type', 'value' => $assignment_type],
                ],
            ]);
            if ($existing) { $assignment_id = (int) $existing[0]; }
        }
        $user = get_userdata($user_id);
        $unit = self::get_unit($unit_id);
        $title = sprintf('%s — %s', $user instanceof WP_User ? $user->display_name : __('Person', 'elev8-os'), $unit['name'] ?? __('Organization', 'elev8-os'));
        $post = ['post_type' => self::ASSIGNMENT_POST_TYPE, 'post_status' => 'publish', 'post_title' => $title, 'post_author' => get_current_user_id()];
        if ($assignment_id) { $post['ID'] = $assignment_id; $result = wp_update_post($post, true); }
        else { $result = wp_insert_post($post, true); }
        if (is_wp_error($result) || !$result) { return 0; }
        $assignment_id = (int) $result;
        $meta = [
            '_elev8_org_assignment_user_id' => $user_id,
            '_elev8_org_assignment_unit_id' => $unit_id,
            '_elev8_org_assignment_type' => $assignment_type,
            '_elev8_org_assignment_responsibility' => $responsibility,
            '_elev8_org_assignment_primary' => !empty($data['is_primary']) ? '1' : '0',
            '_elev8_org_assignment_active' => isset($data['active']) && !$data['active'] ? '0' : '1',
            '_elev8_org_assignment_start' => sanitize_text_field((string) ($data['start_date'] ?? '')),
            '_elev8_org_assignment_end' => sanitize_text_field((string) ($data['end_date'] ?? '')),
        ];
        foreach ($meta as $key => $value) { update_post_meta($assignment_id, $key, $value); }
        self::record_activity($unit_id, 'organization_assignment_saved', sprintf(__('%1$s was assigned to %2$s.', 'elev8-os'), $user instanceof WP_User ? $user->display_name : __('A person', 'elev8-os'), (string) ($unit['name'] ?? '')));
        do_action('elev8_os_organization_assignment_saved', $assignment_id, self::get_assignment($assignment_id));
        return $assignment_id;
    }

    public static function remove_assignment(int $assignment_id): bool {
        if (!self::can_manage() || get_post_type($assignment_id) !== self::ASSIGNMENT_POST_TYPE) { return false; }
        $assignment = self::get_assignment($assignment_id);
        $deleted = (bool) wp_delete_post($assignment_id, true);
        if ($deleted && $assignment) { self::record_activity((int) $assignment['unit_id'], 'organization_assignment_removed', __('An organization assignment was removed.', 'elev8-os')); }
        return $deleted;
    }

    public static function get_unit(int $unit_id): array {
        $post = get_post($unit_id);
        if (!$post instanceof WP_Post || $post->post_type !== self::UNIT_POST_TYPE) { return []; }
        $type = sanitize_key((string) get_post_meta($unit_id, '_elev8_org_type', true));
        return [
            'id' => $unit_id,
            'name' => get_the_title($post),
            'description' => (string) $post->post_content,
            'type' => $type,
            'type_label' => self::unit_types()[$type] ?? ucfirst($type),
            'parent_id' => absint(get_post_meta($unit_id, '_elev8_org_parent_id', true)),
            'status' => sanitize_key((string) get_post_meta($unit_id, '_elev8_org_status', true)) ?: 'active',
            'code' => (string) get_post_meta($unit_id, '_elev8_org_code', true),
            'address' => (string) get_post_meta($unit_id, '_elev8_org_address', true),
            'timezone' => (string) get_post_meta($unit_id, '_elev8_org_timezone', true),
        ];
    }

    public static function units(array $args = []): array {
        $status = sanitize_key((string) ($args['status'] ?? ''));
        $type = sanitize_key((string) ($args['type'] ?? ''));
        $parent_id = isset($args['parent_id']) ? absint($args['parent_id']) : null;
        $meta = [];
        if ($status !== '' && $status !== 'all') { $meta[] = ['key' => '_elev8_org_status', 'value' => $status]; }
        if ($type !== '' && isset(self::unit_types()[$type])) { $meta[] = ['key' => '_elev8_org_type', 'value' => $type]; }
        if ($parent_id !== null) { $meta[] = ['key' => '_elev8_org_parent_id', 'value' => $parent_id, 'type' => 'NUMERIC']; }
        $posts = get_posts(['post_type' => self::UNIT_POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => 500, 'orderby' => 'title', 'order' => 'ASC', 'meta_query' => $meta]);
        return array_values(array_filter(array_map(static fn(WP_Post $post): array => self::get_unit((int) $post->ID), $posts)));
    }

    public static function hierarchy(string $status = 'active'): array {
        $units = self::units(['status' => $status]);
        $by_parent = [];
        foreach ($units as $unit) { $by_parent[(int) $unit['parent_id']][] = $unit; }
        $build = static function(int $parent) use (&$build, $by_parent): array {
            $nodes = [];
            foreach ($by_parent[$parent] ?? [] as $unit) { $unit['children'] = $build((int) $unit['id']); $nodes[] = $unit; }
            return $nodes;
        };
        return $build(0);
    }

    /** Hierarchy limited to the units a non-owner is assigned to manage or participate in. */
    public static function hierarchy_for_user(WP_User $user, string $status = 'active'): array {
        if (self::can_manage($user)) { return self::hierarchy($status); }
        $visible = array_flip(self::user_scope_ids((int) $user->ID));
        if (!$visible) { return []; }
        $units = array_values(array_filter(self::units(['status' => $status]), static fn(array $unit): bool => isset($visible[(int) $unit['id']])));
        $by_parent = [];
        foreach ($units as $unit) {
            $parent = isset($visible[(int) $unit['parent_id']]) ? (int) $unit['parent_id'] : 0;
            $unit['parent_id'] = $parent;
            $by_parent[$parent][] = $unit;
        }
        $build = static function(int $parent) use (&$build, $by_parent): array {
            $nodes = [];
            foreach ($by_parent[$parent] ?? [] as $unit) { $unit['children'] = $build((int) $unit['id']); $nodes[] = $unit; }
            return $nodes;
        };
        return $build(0);
    }

    public static function assignments_for_unit(int $unit_id, bool $active_only = true): array {
        $meta = [['key' => '_elev8_org_assignment_unit_id', 'value' => $unit_id, 'type' => 'NUMERIC']];
        if ($active_only) { $meta[] = ['key' => '_elev8_org_assignment_active', 'value' => '1']; }
        $posts = get_posts(['post_type' => self::ASSIGNMENT_POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => 500, 'orderby' => 'title', 'order' => 'ASC', 'meta_query' => $meta]);
        return array_values(array_filter(array_map(static fn(WP_Post $post): array => self::get_assignment((int) $post->ID), $posts)));
    }

    public static function assignments_for_user(int $user_id, bool $active_only = true): array {
        $meta = [['key' => '_elev8_org_assignment_user_id', 'value' => $user_id, 'type' => 'NUMERIC']];
        if ($active_only) { $meta[] = ['key' => '_elev8_org_assignment_active', 'value' => '1']; }
        $posts = get_posts(['post_type' => self::ASSIGNMENT_POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => 500, 'orderby' => 'date', 'order' => 'DESC', 'meta_query' => $meta]);
        return array_values(array_filter(array_map(static fn(WP_Post $post): array => self::get_assignment((int) $post->ID), $posts)));
    }

    public static function get_assignment(int $assignment_id): array {
        if (get_post_type($assignment_id) !== self::ASSIGNMENT_POST_TYPE) { return []; }
        $user_id = absint(get_post_meta($assignment_id, '_elev8_org_assignment_user_id', true));
        $unit_id = absint(get_post_meta($assignment_id, '_elev8_org_assignment_unit_id', true));
        $type = sanitize_key((string) get_post_meta($assignment_id, '_elev8_org_assignment_type', true));
        $user = get_userdata($user_id);
        $unit = self::get_unit($unit_id);
        return [
            'id' => $assignment_id,
            'user_id' => $user_id,
            'user_name' => $user instanceof WP_User ? $user->display_name : __('Unavailable', 'elev8-os'),
            'user_email' => $user instanceof WP_User ? $user->user_email : '',
            'unit_id' => $unit_id,
            'unit_name' => (string) ($unit['name'] ?? __('Unavailable', 'elev8-os')),
            'assignment_type' => $type,
            'assignment_type_label' => self::assignment_types()[$type] ?? ucfirst($type),
            'responsibility' => (string) get_post_meta($assignment_id, '_elev8_org_assignment_responsibility', true),
            'is_primary' => get_post_meta($assignment_id, '_elev8_org_assignment_primary', true) === '1',
            'active' => get_post_meta($assignment_id, '_elev8_org_assignment_active', true) !== '0',
            'start_date' => (string) get_post_meta($assignment_id, '_elev8_org_assignment_start', true),
            'end_date' => (string) get_post_meta($assignment_id, '_elev8_org_assignment_end', true),
        ];
    }

    /** Unit IDs directly or hierarchically available to a person. */
    public static function user_scope_ids(int $user_id): array {
        $ids = [];
        foreach (self::assignments_for_user($user_id) as $assignment) { $ids[] = (int) $assignment['unit_id']; }
        $expanded = $ids;
        foreach ($ids as $id) { $expanded = array_merge($expanded, self::descendant_ids($id)); }
        return array_values(array_unique(array_filter(array_map('absint', $expanded))));
    }

    public static function descendant_ids(int $unit_id): array {
        $result = [];
        foreach (self::units(['parent_id' => $unit_id, 'status' => 'active']) as $child) {
            $result[] = (int) $child['id'];
            $result = array_merge($result, self::descendant_ids((int) $child['id']));
        }
        return array_values(array_unique($result));
    }

    public static function user_in_scope(int $user_id, int $unit_id): bool {
        if ($user_id < 1 || $unit_id < 1) { return false; }
        return in_array($unit_id, self::user_scope_ids($user_id), true);
    }

    public static function stats(): array {
        $counts = array_fill_keys(array_keys(self::unit_types()), 0);
        $active = self::units(['status' => 'active']);
        foreach ($active as $unit) { if (isset($counts[$unit['type']])) { $counts[$unit['type']]++; } }
        $assignment_posts = get_posts(['post_type' => self::ASSIGNMENT_POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'meta_key' => '_elev8_org_assignment_active', 'meta_value' => '1']);
        return ['total_units' => count($active), 'active_assignments' => count($assignment_posts), 'counts' => $counts];
    }

    public static function workspace_summary(array $summary, string $type, int $id): array {
        if ($type !== 'organization') { return $summary; }
        $unit = self::get_unit($id);
        if (!$unit) { return $summary; }
        $summary['label'] = $unit['type_label'];
        $summary['title'] = $unit['name'];
        $summary['description'] = wp_trim_words(wp_strip_all_tags($unit['description']), 40);
        $summary['status'] = ucfirst($unit['status']);
        $summary['source_url'] = class_exists('Elev8_OS_Organization_Module') ? Elev8_OS_Organization_Module::url(['unit_id' => $id]) : '';
        return $summary;
    }

    public static function workspace_can_view(bool $allowed, string $type, int $id, WP_User $user): bool {
        if ($type !== 'organization') { return $allowed; }
        return self::can_manage($user) || (self::can_view($user) && self::user_in_scope((int) $user->ID, $id));
    }

    private static function record_activity(int $unit_id, string $type, string $details): void {
        if (!class_exists('Elev8_OS_Activity_Service')) { return; }
        Elev8_OS_Activity_Service::record([
            'type' => $type,
            'label' => __('Organization updated', 'elev8-os'),
            'details' => $details,
            'object_id' => $unit_id,
            'object_type' => 'organization',
            'source' => 'organization-engine',
            'actor_user_id' => get_current_user_id(),
        ]);
    }
}

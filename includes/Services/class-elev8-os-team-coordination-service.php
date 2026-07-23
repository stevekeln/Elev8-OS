<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Shared coordination read/write boundary for Universal Work Items.
 *
 * Dependencies and handoff evidence extend the canonical Work Item. They do
 * not create a second project, ticket or task record.
 */
final class Elev8_OS_Team_Coordination_Service {
    public const META_DEPENDENCIES = '_elev8_work_dependencies';
    public const META_HANDOFFS = '_elev8_work_handoff_history';

    public static function init(): void {
        add_filter('elev8_os_business_graph_objects', [__CLASS__, 'register_graph_objects']);
    }

    public static function can_coordinate(?WP_User $user = null): bool {
        $user = $user ?: wp_get_current_user();
        return $user instanceof WP_User && (
            user_can($user, 'manage_options') ||
            Elev8_OS_Access_Service::user_can('manage_operations', $user) ||
            Elev8_OS_Access_Service::user_can('manage_work', $user) ||
            Elev8_OS_Access_Service::user_can('view_ceo_dashboard', $user)
        );
    }

    public static function can_change_work(int $work_id, ?WP_User $user = null): bool {
        $user = $user ?: wp_get_current_user();
        if (!$user instanceof WP_User || get_post_type($work_id) !== Elev8_OS_Work_Service::POST_TYPE) { return false; }
        return self::can_coordinate($user) || absint(get_post_meta($work_id, '_elev8_work_owner_user_id', true)) === (int) $user->ID;
    }

    /** @return int[] */
    public static function dependencies(int $work_id): array {
        $ids = array_map('absint', (array) get_post_meta($work_id, self::META_DEPENDENCIES, true));
        return array_values(array_unique(array_filter($ids, static function(int $id) use ($work_id): bool {
            return $id !== $work_id && get_post_type($id) === Elev8_OS_Work_Service::POST_TYPE;
        })));
    }

    /** @return int[] */
    public static function dependents(int $work_id): array {
        $ids = get_posts([
            'post_type' => Elev8_OS_Work_Service::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => 250,
            'fields' => 'ids',
            'meta_query' => [[
                'key' => self::META_DEPENDENCIES,
                'value' => 'i:' . $work_id . ';',
                'compare' => 'LIKE',
            ]],
        ]);
        return array_values(array_map('intval', $ids));
    }

    /** @return int[] */
    public static function open_dependencies(int $work_id): array {
        return array_values(array_filter(self::dependencies($work_id), static function(int $dependency_id): bool {
            return !in_array((string) get_post_meta($dependency_id, '_elev8_work_status', true), ['completed', 'cancelled', 'archived'], true);
        }));
    }

    public static function set_dependencies(int $work_id, array $dependency_ids) {
        if (!self::can_change_work($work_id)) { return new WP_Error('forbidden', __('You cannot change coordination relationships for this Work Item.', 'elev8-os')); }
        $clean = [];
        foreach ($dependency_ids as $dependency_id) {
            $dependency_id = absint($dependency_id);
            if (!$dependency_id || $dependency_id === $work_id || get_post_type($dependency_id) !== Elev8_OS_Work_Service::POST_TYPE) { continue; }
            if (self::would_create_cycle($work_id, $dependency_id)) {
                return new WP_Error('dependency_cycle', __('That dependency would create a circular waiting relationship.', 'elev8-os'));
            }
            $clean[] = $dependency_id;
        }
        $clean = array_values(array_unique($clean));
        update_post_meta($work_id, self::META_DEPENDENCIES, $clean);
        do_action('elev8_os_work_dependencies_changed', $work_id, $clean);
        return true;
    }

    public static function handoff(int $work_id, int $to_user_id, string $note = '') {
        if (!self::can_change_work($work_id)) { return new WP_Error('forbidden', __('You cannot hand off this Work Item.', 'elev8-os')); }
        $target = get_user_by('id', $to_user_id);
        if (!$target instanceof WP_User || !Elev8_OS_Access_Service::can_receive_assignment($target)) {
            return new WP_Error('invalid_owner', __('The selected person cannot receive operational assignments.', 'elev8-os'));
        }
        $from_user_id = absint(get_post_meta($work_id, '_elev8_work_owner_user_id', true));
        if ($from_user_id === $to_user_id) { return new WP_Error('same_owner', __('This Work Item is already assigned to that person.', 'elev8-os')); }

        $history = (array) get_post_meta($work_id, self::META_HANDOFFS, true);
        $history[] = [
            'from_user_id' => $from_user_id,
            'to_user_id' => $to_user_id,
            'actor_user_id' => get_current_user_id(),
            'note' => sanitize_textarea_field($note),
            'created_at' => current_time('mysql'),
        ];
        update_post_meta($work_id, self::META_HANDOFFS, array_slice($history, -100));
        $status = (string) get_post_meta($work_id, '_elev8_work_status', true);
        $changes = ['owner_user_id' => $to_user_id];
        if (in_array($status, ['new', 'requested', 'queued'], true)) { $changes['status'] = 'assigned'; }
        $result = Elev8_OS_Work_Service::update($work_id, $changes);
        if (is_wp_error($result)) { return $result; }
        do_action('elev8_os_work_handed_off', $work_id, $from_user_id, $to_user_id, $note);
        return true;
    }

    /** @return array<int,array<string,mixed>> */
    public static function handoff_history(int $work_id): array {
        return array_values(array_filter((array) get_post_meta($work_id, self::META_HANDOFFS, true), 'is_array'));
    }

    /** @return array<string,mixed> */
    public static function snapshot(WP_User $user): array {
        $team = self::can_coordinate($user);
        $items = Elev8_OS_Operations_Engine_Service::inbox($user, ['view' => $team ? 'team' : 'mine', 'status' => 'active']);
        $workloads = [];
        $bottlenecks = [];
        $handoffs = [];
        foreach ($items as $item) {
            $owner_id = (int) ($item['owner_user_id'] ?? 0);
            $owner_key = $owner_id ?: 0;
            if (!isset($workloads[$owner_key])) {
                $workloads[$owner_key] = ['user_id' => $owner_id, 'name' => (string) ($item['owner_name'] ?? __('Unassigned', 'elev8-os')), 'active' => 0, 'overdue' => 0, 'blocked' => 0, 'urgent' => 0];
            }
            $workloads[$owner_key]['active']++;
            if (($item['due_date'] ?? '') && $item['due_date'] < current_time('Y-m-d')) { $workloads[$owner_key]['overdue']++; }
            if (($item['priority'] ?? '') === 'urgent') { $workloads[$owner_key]['urgent']++; }

            $open = self::open_dependencies((int) $item['id']);
            $dependents = self::dependents((int) $item['id']);
            if ($open) { $workloads[$owner_key]['blocked']++; }
            $score = count($open) * 20 + count($dependents) * 10;
            if (($item['priority'] ?? '') === 'urgent') { $score += 25; }
            if (($item['due_date'] ?? '') && $item['due_date'] < current_time('Y-m-d')) { $score += 20; }
            if ($score > 0) {
                $item['open_dependencies'] = $open;
                $item['dependent_ids'] = $dependents;
                $item['bottleneck_score'] = $score;
                $bottlenecks[] = $item;
            }
            foreach (array_reverse(self::handoff_history((int) $item['id'])) as $handoff) {
                $handoff['work_id'] = (int) $item['id'];
                $handoff['work_title'] = (string) $item['title'];
                $handoffs[] = $handoff;
            }
        }
        foreach ($workloads as &$load) {
            if (class_exists('Elev8_OS_Team_Capacity_Service')) {
                $load['capacity'] = Elev8_OS_Team_Capacity_Service::capacity_projection($load);
            }
        }
        unset($load);
        usort($bottlenecks, static fn(array $a, array $b): int => ((int) $b['bottleneck_score']) <=> ((int) $a['bottleneck_score']));
        usort($workloads, static fn(array $a, array $b): int => ((int) ($b['capacity']['percent'] ?? $b['active'])) <=> ((int) ($a['capacity']['percent'] ?? $a['active'])));
        usort($handoffs, static fn(array $a, array $b): int => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));
        $snapshot = ['team_view' => $team, 'items' => $items, 'workloads' => array_values($workloads), 'bottlenecks' => array_slice($bottlenecks, 0, 25), 'handoffs' => array_slice($handoffs, 0, 25)];
        $snapshot['reassignment_suggestions'] = class_exists('Elev8_OS_Team_Capacity_Service') ? Elev8_OS_Team_Capacity_Service::reassignment_suggestions($snapshot) : [];
        return $snapshot;
    }

    /** @return WP_User[] */
    public static function assignable_users(): array {
        return array_values(array_filter(get_users(['orderby' => 'display_name', 'order' => 'ASC']), static function($user): bool {
            return $user instanceof WP_User && Elev8_OS_Access_Service::can_receive_assignment($user);
        }));
    }

    private static function would_create_cycle(int $work_id, int $candidate_id, array $visited = []): bool {
        if ($candidate_id === $work_id) { return true; }
        if (in_array($candidate_id, $visited, true)) { return false; }
        $visited[] = $candidate_id;
        foreach (self::dependencies($candidate_id) as $next_id) {
            if (self::would_create_cycle($work_id, $next_id, $visited)) { return true; }
        }
        return false;
    }

    public static function register_graph_objects(array $objects): array {
        $objects['work_dependency'] = [
            'label' => __('Work Dependency', 'elev8-os'),
            'engine' => 'Workflow',
            'organization_scoped' => true,
            'notes' => 'A governed waiting-on relationship between canonical Work Items; it is not a duplicate task.',
        ];
        $objects['work_handoff'] = [
            'label' => __('Work Handoff', 'elev8-os'),
            'engine' => 'Operations',
            'organization_scoped' => true,
            'notes' => 'Assignment-transfer evidence retained on the canonical Work Item.',
        ];
        return $objects;
    }
}

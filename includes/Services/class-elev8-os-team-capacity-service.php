<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Capacity policy and governed handoff acknowledgement for Team Coordination.
 *
 * Capacity is a planning target, not an employment limit or automatic
 * assignment rule. Handoff requests do not change ownership until accepted.
 */
final class Elev8_OS_Team_Capacity_Service {
    public const USER_META_CAPACITY = '_elev8_work_capacity_target';
    public const META_HANDOFF_REQUESTS = '_elev8_work_handoff_requests';
    private const DEFAULT_CAPACITY = 8;

    public static function init(): void {
        add_action('elev8_os_work_dependencies_changed', [__CLASS__, 'notify_dependency_change'], 10, 2);
        add_filter('elev8_os_business_graph_objects', [__CLASS__, 'register_graph_objects']);
    }

    public static function target(int $user_id): int {
        $value = absint(get_user_meta($user_id, self::USER_META_CAPACITY, true));
        return $value > 0 ? min(100, $value) : self::DEFAULT_CAPACITY;
    }

    public static function set_target(int $user_id, int $target) {
        if (!Elev8_OS_Team_Coordination_Service::can_coordinate()) {
            return new WP_Error('forbidden', __('You cannot manage workload capacity targets.', 'elev8-os'));
        }
        $user = get_user_by('id', $user_id);
        if (!$user instanceof WP_User || !Elev8_OS_Access_Service::can_receive_assignment($user)) {
            return new WP_Error('invalid_user', __('The selected person cannot receive operational assignments.', 'elev8-os'));
        }
        update_user_meta($user_id, self::USER_META_CAPACITY, max(1, min(100, $target)));
        return true;
    }

    /** @param array<string,mixed> $load */
    public static function capacity_projection(array $load): array {
        $target = self::target((int) ($load['user_id'] ?? 0));
        $points = (int) ($load['active'] ?? 0)
            + ((int) ($load['urgent'] ?? 0) * 2)
            + ((int) ($load['overdue'] ?? 0) * 2)
            + (int) ($load['blocked'] ?? 0);
        $percent = $target > 0 ? (int) round(($points / $target) * 100) : 0;
        return [
            'target' => $target,
            'points' => $points,
            'percent' => $percent,
            'state' => $percent > 120 ? 'over_capacity' : ($percent >= 80 ? 'near_capacity' : 'available'),
            'explanation' => sprintf(
                __('%1$d active + %2$d urgent×2 + %3$d overdue×2 + %4$d blocked = %5$d capacity points against a target of %6$d.', 'elev8-os'),
                (int) ($load['active'] ?? 0),
                (int) ($load['urgent'] ?? 0),
                (int) ($load['overdue'] ?? 0),
                (int) ($load['blocked'] ?? 0),
                $points,
                $target
            ),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public static function requests(int $work_id): array {
        return array_values(array_filter((array) get_post_meta($work_id, self::META_HANDOFF_REQUESTS, true), 'is_array'));
    }

    public static function pending_request(int $work_id): ?array {
        foreach (array_reverse(self::requests($work_id)) as $request) {
            if (($request['status'] ?? '') === 'pending') { return $request; }
        }
        return null;
    }

    public static function request_handoff(int $work_id, int $to_user_id, string $note = '') {
        if (!Elev8_OS_Team_Coordination_Service::can_change_work($work_id)) {
            return new WP_Error('forbidden', __('You cannot request a handoff for this Work Item.', 'elev8-os'));
        }
        if (self::pending_request($work_id)) {
            return new WP_Error('pending_handoff', __('This Work Item already has a handoff awaiting acknowledgement.', 'elev8-os'));
        }
        $target = get_user_by('id', $to_user_id);
        if (!$target instanceof WP_User || !Elev8_OS_Access_Service::can_receive_assignment($target)) {
            return new WP_Error('invalid_owner', __('The selected person cannot receive operational assignments.', 'elev8-os'));
        }
        $from_user_id = absint(get_post_meta($work_id, '_elev8_work_owner_user_id', true));
        if ($from_user_id === $to_user_id) {
            return new WP_Error('same_owner', __('This Work Item is already assigned to that person.', 'elev8-os'));
        }
        $requests = self::requests($work_id);
        $request_id = wp_generate_uuid4();
        $requests[] = [
            'request_id' => $request_id,
            'from_user_id' => $from_user_id,
            'to_user_id' => $to_user_id,
            'actor_user_id' => get_current_user_id(),
            'note' => sanitize_textarea_field($note),
            'status' => 'pending',
            'created_at' => current_time('mysql'),
            'decided_at' => '',
            'decision_note' => '',
        ];
        update_post_meta($work_id, self::META_HANDOFF_REQUESTS, array_slice($requests, -100));
        self::notify_handoff_request($work_id, $target, $note);
        do_action('elev8_os_work_handoff_requested', $work_id, $from_user_id, $to_user_id, $request_id);
        return true;
    }

    public static function decide_handoff(int $work_id, string $request_id, string $decision, string $note = '') {
        $user = wp_get_current_user();
        $requests = self::requests($work_id);
        $matched = false;
        foreach ($requests as &$request) {
            if (($request['request_id'] ?? '') !== $request_id || ($request['status'] ?? '') !== 'pending') { continue; }
            if ((int) ($request['to_user_id'] ?? 0) !== (int) $user->ID && !Elev8_OS_Team_Coordination_Service::can_coordinate($user)) {
                return new WP_Error('forbidden', __('Only the proposed recipient or an operational leader can decide this handoff.', 'elev8-os'));
            }
            if (!in_array($decision, ['accepted', 'declined'], true)) {
                return new WP_Error('invalid_decision', __('Choose whether to accept or decline the handoff.', 'elev8-os'));
            }
            $request['status'] = $decision;
            $request['decided_at'] = current_time('mysql');
            $request['decision_note'] = sanitize_textarea_field($note);
            $request['decided_by_user_id'] = (int) $user->ID;
            $matched = true;
            if ($decision === 'accepted') {
                $result = Elev8_OS_Team_Coordination_Service::handoff($work_id, (int) $request['to_user_id'], trim((string) $request['note'] . "\n" . $note));
                if (is_wp_error($result)) { return $result; }
            }
            break;
        }
        unset($request);
        if (!$matched) { return new WP_Error('missing_request', __('The pending handoff request could not be found.', 'elev8-os')); }
        update_post_meta($work_id, self::META_HANDOFF_REQUESTS, array_slice($requests, -100));
        do_action('elev8_os_work_handoff_decided', $work_id, $request_id, $decision, (int) $user->ID);
        return true;
    }

    /** @return array<int,array<string,mixed>> */
    public static function reassignment_suggestions(array $snapshot): array {
        $loads = [];
        foreach ((array) ($snapshot['workloads'] ?? []) as $load) {
            $load['capacity'] = self::capacity_projection($load);
            $loads[(int) ($load['user_id'] ?? 0)] = $load;
        }
        $available = array_values(array_filter($loads, static function(array $load): bool {
            if (($load['capacity']['state'] ?? '') !== 'available' || empty($load['user_id'])) { return false; }
            if (!class_exists('Elev8_OS_Team_Availability_Skill_Service')) { return true; }
            return Elev8_OS_Team_Availability_Skill_Service::availability((int) $load['user_id'])['state'] !== 'unavailable';
        }));
        $suggestions = [];
        foreach ((array) ($snapshot['items'] ?? []) as $item) {
            $owner_id = (int) ($item['owner_user_id'] ?? 0);
            if (!$owner_id || empty($loads[$owner_id]) || ($loads[$owner_id]['capacity']['state'] ?? '') !== 'over_capacity') { continue; }
            $ranked = [];
            foreach ($available as $candidate) {
                if ((int) $candidate['user_id'] === $owner_id) { continue; }
                $match = class_exists('Elev8_OS_Team_Availability_Skill_Service')
                    ? Elev8_OS_Team_Availability_Skill_Service::match((int) $item['id'], (int) $candidate['user_id'], $owner_id)
                    : ['score' => 0, 'eligible' => true, 'explanation' => __('No availability or skill profile is available.', 'elev8-os')];
                if (empty($match['eligible'])) { continue; }
                $ranked[] = ['candidate' => $candidate, 'match' => $match];
            }
            usort($ranked, static function(array $a, array $b): int {
                $match = ((int) $b['match']['score']) <=> ((int) $a['match']['score']);
                return $match !== 0 ? $match : ((int) $a['candidate']['capacity']['percent']) <=> ((int) $b['candidate']['capacity']['percent']);
            });
            if (!$ranked) { continue; }
            $best = $ranked[0]; $candidate = $best['candidate']; $match = $best['match'];
            $suggestions[] = [
                'work_id' => (int) $item['id'],
                'work_title' => (string) $item['title'],
                'from_user_id' => $owner_id,
                'from_name' => (string) $loads[$owner_id]['name'],
                'to_user_id' => (int) $candidate['user_id'],
                'to_name' => (string) $candidate['name'],
                'match_score' => (int) $match['score'],
                'reason' => sprintf(
                    __('%1$s is at %2$d%% capacity while %3$s is at %4$d%%. Fit evidence: %5$s Ownership will not change without a governed handoff.', 'elev8-os'),
                    (string) $loads[$owner_id]['name'],
                    (int) $loads[$owner_id]['capacity']['percent'],
                    (string) $candidate['name'],
                    (int) $candidate['capacity']['percent'],
                    (string) $match['explanation']
                ),
            ];
            if (count($suggestions) >= 10) { break; }
        }
        return $suggestions;
    }

    public static function notify_dependency_change(int $work_id, array $dependency_ids): void {
        $recipients = [];
        $owner_id = absint(get_post_meta($work_id, '_elev8_work_owner_user_id', true));
        if ($owner_id) { $recipients[] = $owner_id; }
        foreach ($dependency_ids as $dependency_id) {
            $dependency_owner = absint(get_post_meta((int) $dependency_id, '_elev8_work_owner_user_id', true));
            if ($dependency_owner) { $recipients[] = $dependency_owner; }
        }
        $recipients = array_unique($recipients);
        foreach ($recipients as $user_id) {
            $user = get_user_by('id', $user_id);
            if (!$user instanceof WP_User || !$user->user_email) { continue; }
            Elev8_OS_Notification_Service::send_email(
                $user->user_email,
                sprintf(__('Work dependency updated: %s', 'elev8-os'), get_the_title($work_id)),
                sprintf(
                    __("A waiting-on relationship changed for %1$s.\n\nOpen Team Coordination: %2$s", 'elev8-os'),
                    get_the_title($work_id),
                    Elev8_OS_Team_Coordination_Module::url()
                )
            );
        }
    }

    private static function notify_handoff_request(int $work_id, WP_User $target, string $note): void {
        if (!$target->user_email) { return; }
        Elev8_OS_Notification_Service::send_email(
            $target->user_email,
            sprintf(__('Handoff acknowledgement requested: %s', 'elev8-os'), get_the_title($work_id)),
            sprintf(
                __("A Work Item has been proposed for handoff to you. Ownership will not change until you accept it.\n\nWork: %1$s\nNote: %2$s\n\nReview: %3$s", 'elev8-os'),
                get_the_title($work_id),
                $note ?: __('No note provided.', 'elev8-os'),
                Elev8_OS_Team_Coordination_Module::url()
            )
        );
    }

    public static function register_graph_objects(array $objects): array {
        $objects['work_capacity_policy'] = [
            'label' => __('Work Capacity Policy', 'elev8-os'),
            'engine' => 'Organization',
            'organization_scoped' => true,
            'notes' => 'A configurable planning target used by Team Coordination; it is not an employment limit or automatic assignment rule.',
        ];
        $objects['work_handoff_request'] = [
            'label' => __('Work Handoff Request', 'elev8-os'),
            'engine' => 'Operations',
            'organization_scoped' => true,
            'notes' => 'Governed acknowledgement evidence before a Universal Work Item changes owner.',
        ];
        return $objects;
    }
}

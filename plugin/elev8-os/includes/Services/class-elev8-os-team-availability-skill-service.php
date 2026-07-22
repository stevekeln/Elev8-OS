<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Availability and skill evidence for Team Coordination.
 *
 * Availability and skills improve suggestions only. They never assign work,
 * change WordPress roles, or replace Organization assignments.
 */
final class Elev8_OS_Team_Availability_Skill_Service {
    public const USER_META_AVAILABILITY = '_elev8_work_availability';
    public const USER_META_SKILLS = '_elev8_work_skills';
    public const META_REQUIRED_SKILLS = '_elev8_work_required_skills';

    public static function init(): void {
        add_filter('elev8_os_business_graph_objects', [__CLASS__, 'register_graph_objects']);
    }

    public static function availability(int $user_id): array {
        $saved = (array) get_user_meta($user_id, self::USER_META_AVAILABILITY, true);
        $state = sanitize_key((string) ($saved['state'] ?? 'available'));
        if (!in_array($state, ['available', 'limited', 'unavailable'], true)) { $state = 'available'; }
        $until = sanitize_text_field((string) ($saved['until'] ?? ''));
        if ($state !== 'available' && $until && $until < current_time('Y-m-d')) {
            $state = 'available'; $until = '';
        }
        return [
            'state' => $state,
            'until' => $until,
            'note' => sanitize_textarea_field((string) ($saved['note'] ?? '')),
            'updated_at' => sanitize_text_field((string) ($saved['updated_at'] ?? '')),
        ];
    }

    /** @return string[] */
    public static function skills(int $user_id): array {
        return self::clean_terms((array) get_user_meta($user_id, self::USER_META_SKILLS, true));
    }

    public static function save_profile(int $user_id, string $state, string $until, string $note, array $skills) {
        $actor = wp_get_current_user();
        if ((int) $actor->ID !== $user_id && !Elev8_OS_Team_Coordination_Service::can_coordinate($actor)) {
            return new WP_Error('forbidden', __('You cannot change this person’s coordination profile.', 'elev8-os'));
        }
        $user = get_user_by('id', $user_id);
        if (!$user instanceof WP_User || !Elev8_OS_Access_Service::can_receive_assignment($user)) {
            return new WP_Error('invalid_user', __('The selected person cannot receive operational assignments.', 'elev8-os'));
        }
        $state = sanitize_key($state);
        if (!in_array($state, ['available', 'limited', 'unavailable'], true)) { $state = 'available'; }
        update_user_meta($user_id, self::USER_META_AVAILABILITY, [
            'state' => $state,
            'until' => sanitize_text_field($until),
            'note' => sanitize_textarea_field($note),
            'updated_at' => current_time('mysql'),
        ]);
        update_user_meta($user_id, self::USER_META_SKILLS, self::clean_terms($skills));
        do_action('elev8_os_team_coordination_profile_saved', $user_id, $state);
        return true;
    }

    /** @return string[] */
    public static function required_skills(int $work_id): array {
        return self::clean_terms((array) get_post_meta($work_id, self::META_REQUIRED_SKILLS, true));
    }

    public static function save_required_skills(int $work_id, array $skills) {
        if (!Elev8_OS_Team_Coordination_Service::can_change_work($work_id)) {
            return new WP_Error('forbidden', __('You cannot change skill requirements for this Work Item.', 'elev8-os'));
        }
        update_post_meta($work_id, self::META_REQUIRED_SKILLS, self::clean_terms($skills));
        do_action('elev8_os_work_skill_requirements_changed', $work_id, self::required_skills($work_id));
        return true;
    }

    /** @return int[] */
    public static function organization_units(int $user_id): array {
        if (!class_exists('Elev8_OS_Organization_Service')) { return []; }
        $units = [];
        foreach (Elev8_OS_Organization_Service::assignments_for_user($user_id, true) as $assignment) {
            $unit_id = absint($assignment['unit_id'] ?? 0);
            if ($unit_id) { $units[] = $unit_id; }
        }
        return array_values(array_unique($units));
    }

    public static function match(int $work_id, int $candidate_user_id, int $current_owner_id = 0): array {
        $required = self::required_skills($work_id);
        $candidate_skills = self::skills($candidate_user_id);
        $matched = array_values(array_intersect($required, $candidate_skills));
        $missing = array_values(array_diff($required, $candidate_skills));
        $availability = self::availability($candidate_user_id);
        $candidate_units = self::organization_units($candidate_user_id);
        $owner_units = $current_owner_id ? self::organization_units($current_owner_id) : [];
        $shared_units = array_values(array_intersect($candidate_units, $owner_units));

        $score = 0;
        $reasons = [];
        if ($availability['state'] === 'available') { $score += 30; $reasons[] = __('currently available', 'elev8-os'); }
        elseif ($availability['state'] === 'limited') { $score += 10; $reasons[] = __('available with limits', 'elev8-os'); }
        else { $score -= 100; $reasons[] = __('marked unavailable', 'elev8-os'); }

        if ($required) {
            $skill_score = (int) round((count($matched) / count($required)) * 50);
            $score += $skill_score;
            $reasons[] = sprintf(__('%1$d of %2$d required skills matched', 'elev8-os'), count($matched), count($required));
        } else {
            $score += 20;
            $reasons[] = __('no explicit skill requirement is recorded', 'elev8-os');
        }
        if ($shared_units) { $score += 20; $reasons[] = __('shares an active Organization assignment with the current owner', 'elev8-os'); }

        return [
            'score' => $score,
            'eligible' => $availability['state'] !== 'unavailable' && !$missing,
            'availability' => $availability,
            'required_skills' => $required,
            'matched_skills' => $matched,
            'missing_skills' => $missing,
            'shared_unit_ids' => $shared_units,
            'explanation' => ucfirst(implode('; ', $reasons)) . '.',
        ];
    }

    /** @return string[] */
    private static function clean_terms(array $terms): array {
        $clean = [];
        foreach ($terms as $term) {
            foreach (preg_split('/[,\n]+/', (string) $term) ?: [] as $piece) {
                $piece = sanitize_text_field(trim($piece));
                if ($piece !== '') { $clean[] = strtolower($piece); }
            }
        }
        return array_values(array_unique($clean));
    }

    public static function register_graph_objects(array $objects): array {
        $objects['work_availability'] = [
            'label' => __('Work Availability', 'elev8-os'),
            'engine' => 'Organization',
            'organization_scoped' => true,
            'notes' => 'Personal coordination availability used for governed suggestions; it is not attendance, scheduling, or employment status.',
        ];
        $objects['person_skill_relationship'] = [
            'label' => __('Person Skill Relationship', 'elev8-os'),
            'engine' => 'Organization',
            'organization_scoped' => true,
            'notes' => 'A configurable capability relationship used to explain Work Item handoff fit; it does not grant access or certification.',
        ];
        return $objects;
    }
}

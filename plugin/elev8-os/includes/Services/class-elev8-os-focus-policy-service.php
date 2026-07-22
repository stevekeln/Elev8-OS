<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Explainable focus-ranking policy and personal usefulness feedback.
 *
 * This service owns presentation policy only. It never changes source priority,
 * Work Items, Recommendations, Conversations, or authoritative business data.
 */
final class Elev8_OS_Focus_Policy_Service {
    private const OPTION_POLICIES = 'elev8_os_focus_policies';
    private const META_FEEDBACK = '_elev8_os_focus_feedback';

    public static function init(): void {
        add_filter('elev8_os_business_graph_relationships', [__CLASS__, 'register_graph_relationships']);
    }

    /** @return array<string,int> */
    public static function default_weights(): array {
        return ['work'=>10, 'attention'=>20, 'coaching'=>8, 'conversations'=>6, 'critical'=>35, 'high'=>20, 'overdue'=>25, 'due_today'=>15, 'executive'=>12, 'pattern'=>10];
    }

    /** @return array<string,int> */
    public static function weights_for_user(WP_User $user): array {
        $weights = self::default_weights();
        $policies = get_option(self::OPTION_POLICIES, []);
        if (!is_array($policies)) { $policies = []; }
        foreach (self::organization_ids($user) as $unit_id) {
            $policy = isset($policies[$unit_id]) && is_array($policies[$unit_id]) ? $policies[$unit_id] : [];
            foreach ($weights as $key=>$value) {
                if (isset($policy[$key])) { $weights[$key] = max(-50, min(50, (int) $policy[$key])); }
            }
        }
        return (array) apply_filters('elev8_os_focus_policy_weights', $weights, $user);
    }

    /** @return array<int,int> */
    public static function organization_ids(WP_User $user): array {
        if (!class_exists('Elev8_OS_Organization_Service')) { return []; }
        $assignments = Elev8_OS_Organization_Service::assignments_for_user((int) $user->ID);
        $primary = [];
        $others = [];
        foreach ($assignments as $assignment) {
            $id = absint($assignment['unit_id'] ?? 0);
            if (!$id) { continue; }
            if (!empty($assignment['is_primary'])) { $primary[] = $id; } else { $others[] = $id; }
        }
        return array_values(array_unique(array_merge($primary, $others)));
    }

    /** @return array<string,mixed> */
    public static function score(array $item, WP_User $user): array {
        $weights = self::weights_for_user($user);
        $source = sanitize_key((string) ($item['source'] ?? ''));
        $severity = sanitize_key((string) ($item['severity'] ?? 'normal'));
        $base = (int) ($item['base_score'] ?? $item['score'] ?? 0);
        $reasons = [['label'=>__('Source evidence', 'elev8-os'), 'points'=>$base]];
        $score = $base;

        foreach ([[$source, $source], [$severity, $severity]] as $pair) {
            $key = $pair[0];
            if ($key !== '' && isset($weights[$key]) && (int) $weights[$key] !== 0) {
                $score += (int) $weights[$key];
                $reasons[] = ['label'=>ucwords(str_replace('_',' ', $pair[1])).__(' policy', 'elev8-os'), 'points'=>(int) $weights[$key]];
            }
        }
        foreach (['overdue','due_today','executive','pattern'] as $flag) {
            if (!empty($item[$flag]) && !empty($weights[$flag])) {
                $score += (int) $weights[$flag];
                $reasons[] = ['label'=>ucwords(str_replace('_',' ', $flag)), 'points'=>(int) $weights[$flag]];
            }
        }

        $feedback = self::feedback_for_user((int) $user->ID);
        $key = sanitize_text_field((string) ($item['key'] ?? ''));
        $state = sanitize_key((string) ($feedback[$key]['state'] ?? ''));
        if ($state === 'helpful') { $score += 5; $reasons[] = ['label'=>__('Previously marked helpful', 'elev8-os'), 'points'=>5]; }
        elseif ($state === 'not_relevant') { $score -= 20; $reasons[] = ['label'=>__('Previously marked not relevant', 'elev8-os'), 'points'=>-20]; }
        elseif ($state === 'handled') { $score -= 30; $reasons[] = ['label'=>__('Previously marked handled', 'elev8-os'), 'points'=>-30]; }

        $item['score'] = max(0, min(200, $score));
        $item['focus_reasons'] = $reasons;
        $item['feedback_state'] = $state;
        return $item;
    }

    /** @return array<string,array<string,string>> */
    public static function feedback_for_user(int $user_id): array {
        $value = get_user_meta($user_id, self::META_FEEDBACK, true);
        return is_array($value) ? $value : [];
    }

    public static function save_feedback(int $user_id, string $item_key, string $state): bool {
        $state = sanitize_key($state);
        if ($user_id < 1 || $item_key === '' || !in_array($state, ['helpful','handled','not_relevant','clear'], true)) { return false; }
        $all = self::feedback_for_user($user_id);
        if ($state === 'clear') { unset($all[$item_key]); }
        else { $all[$item_key] = ['state'=>$state, 'updated_at'=>current_time('mysql')]; }
        return update_user_meta($user_id, self::META_FEEDBACK, $all) !== false;
    }

    /** @param array<string,mixed> $weights */
    public static function save_policy(int $unit_id, array $weights): bool {
        if ($unit_id < 1 || !class_exists('Elev8_OS_Organization_Service') || get_post_type($unit_id) !== Elev8_OS_Organization_Service::UNIT_POST_TYPE) { return false; }
        $allowed = array_keys(self::default_weights());
        $clean = [];
        foreach ($allowed as $key) { $clean[$key] = max(-50, min(50, (int) ($weights[$key] ?? self::default_weights()[$key]))); }
        $policies = get_option(self::OPTION_POLICIES, []);
        if (!is_array($policies)) { $policies = []; }
        $policies[$unit_id] = $clean;
        return update_option(self::OPTION_POLICIES, $policies, false);
    }

    /** @return array<string,int> */
    public static function policy_for_unit(int $unit_id): array {
        $policies = get_option(self::OPTION_POLICIES, []);
        $policy = is_array($policies) && isset($policies[$unit_id]) && is_array($policies[$unit_id]) ? $policies[$unit_id] : [];
        return array_merge(self::default_weights(), $policy);
    }

    public static function register_graph_relationships(array $relationships): array {
        $relationships['governed_evidence_projects_focus'] = [
            'label'=>__('Projects explainable focus ranking', 'elev8-os'),
            'from'=>['work','conversation','attention_projection','coaching_projection','organization_policy'],
            'to'=>['focus_projection'],
            'directional'=>true,
            'notes'=>__('Focus ranking is a non-authoritative projection. Policy and personal usefulness feedback may influence presentation but never change source records.', 'elev8-os'),
        ];
        return $relationships;
    }
}

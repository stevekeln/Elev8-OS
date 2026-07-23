<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Explainable, role-aware coaching read model.
 *
 * Coaching projects governed evidence into personal guidance. It never creates
 * Observations, Patterns, Recommendations, Outcomes, or Work Items.
 */
final class Elev8_OS_Business_Coaching_Service {
    private const META_STATES = '_elev8_os_coaching_card_states';

    public static function init(): void {
        add_filter('elev8_os_business_graph_relationships', [__CLASS__, 'register_graph_relationships']);
    }

    /** @return array<int,array<string,mixed>> */
    public static function cards(?WP_User $user = null, int $organization_unit_id = 0): array {
        $user = $user ?: (class_exists('Elev8_OS_Preview_Service') ? Elev8_OS_Preview_Service::effective_user() : wp_get_current_user());
        if (!$user instanceof WP_User || !$user->ID) { return []; }

        $role = class_exists('Elev8_OS_Workspace_Resolver_Service') ? Elev8_OS_Workspace_Resolver_Service::role_key($user) : 'team';
        $cards = [];
        $cards = array_merge($cards, self::work_cards($user, $role));
        $cards = array_merge($cards, self::pattern_cards($role, $organization_unit_id));
        $cards = array_merge($cards, self::recommendation_cards($role, $organization_unit_id));
        $cards = self::decorate_states($cards, $user->ID);

        usort($cards, static function(array $a, array $b): int {
            $pin = ((int) !empty($b['pinned'])) <=> ((int) !empty($a['pinned']));
            return $pin !== 0 ? $pin : ((int) ($b['priority_score'] ?? 0) <=> (int) ($a['priority_score'] ?? 0));
        });
        return array_values(array_filter($cards, static fn(array $card): bool => ($card['state'] ?? '') !== 'dismissed'));
    }

    /** @return array<string,int> */
    public static function summary(?WP_User $user = null): array {
        $cards = self::cards($user);
        $summary = ['total'=>count($cards),'unread'=>0,'pinned'=>0,'follow_up'=>0,'critical'=>0];
        foreach ($cards as $card) {
            if (($card['state'] ?? 'unread') === 'unread') { $summary['unread']++; }
            if (!empty($card['pinned'])) { $summary['pinned']++; }
            if (($card['state'] ?? '') === 'follow_up') { $summary['follow_up']++; }
            if (($card['severity'] ?? '') === 'critical') { $summary['critical']++; }
        }
        return $summary;
    }

    public static function set_state(int $user_id, string $card_key, string $state): bool {
        $allowed = ['unread','read','dismissed','pinned','follow_up'];
        $state = sanitize_key($state);
        $card_key = sanitize_text_field($card_key);
        if (!$user_id || $card_key === '' || !in_array($state, $allowed, true)) { return false; }
        $states = get_user_meta($user_id, self::META_STATES, true);
        $states = is_array($states) ? $states : [];
        $states[$card_key] = ['state'=>$state,'updated_at'=>current_time('mysql')];
        return (bool) update_user_meta($user_id, self::META_STATES, $states);
    }

    /** @return array<int,array<string,mixed>> */
    private static function work_cards(WP_User $user, string $role): array {
        if (!class_exists('Elev8_OS_Operations_Engine_Service')) { return []; }
        $metrics = Elev8_OS_Operations_Engine_Service::metrics($user, in_array($role, ['owner','shop_manager','glass_manager'], true));
        $cards = [];
        if ((int) ($metrics['overdue'] ?? 0) > 0) {
            $count = (int) $metrics['overdue'];
            $cards[] = self::card('work:overdue:'.$role, 'operations', 'risk', $count >= 3 ? 'high' : 'normal',
                sprintf(_n('%d overdue Work Item needs attention.', '%d overdue Work Items need attention.', $count, 'elev8-os'), $count),
                __('Review the oldest overdue work first, confirm ownership, and reset only dates that reflect a real plan.', 'elev8-os'),
                sprintf(__('This guidance is based on %d active Work Items whose due date has passed.', 'elev8-os'), $count),
                class_exists('Elev8_OS_Portal_Page_Manager') ? Elev8_OS_Portal_Page_Manager::get_url('actions') : '',
                80 + min(15, $count * 3));
        }
        if ((int) ($metrics['due_today'] ?? 0) > 0) {
            $count = (int) $metrics['due_today'];
            $cards[] = self::card('work:today:'.$role, 'operations', 'efficiency', 'normal',
                sprintf(_n('%d Work Item is due today.', '%d Work Items are due today.', $count, 'elev8-os'), $count),
                __('Protect time for today’s committed work before taking on lower-priority requests.', 'elev8-os'),
                sprintf(__('This guidance is based on %d active Work Items due today.', 'elev8-os'), $count),
                class_exists('Elev8_OS_Portal_Page_Manager') ? Elev8_OS_Portal_Page_Manager::get_url('actions') : '', 60);
        }
        return $cards;
    }

    /** @return array<int,array<string,mixed>> */
    private static function pattern_cards(string $role, int $organization_unit_id): array {
        if (!class_exists('Elev8_OS_Pattern_Detection_Service')) { return []; }
        $patterns = Elev8_OS_Pattern_Detection_Service::query(['status'=>'','organization_unit_id'=>$organization_unit_id,'posts_per_page'=>100]);
        $patterns = array_values(array_filter($patterns, static fn(array $p): bool => in_array((string) ($p['status'] ?? ''), ['active','acknowledged'], true)));
        $cards = [];
        foreach ($patterns as $pattern) {
            $classification = sanitize_key((string) ($pattern['classification'] ?? 'information'));
            $category = self::category_for_pattern($pattern);
            if (!self::role_accepts($role, $classification, $category)) { continue; }
            $count = max(1, (int) ($pattern['occurrence_count'] ?? 1));
            $severity = sanitize_key((string) ($pattern['severity'] ?? 'normal'));
            $action = self::pattern_action($classification, $category);
            $cards[] = self::card(
                'pattern:'.(int) ($pattern['id'] ?? 0).':'.$role,
                'pattern', $category, $severity,
                (string) ($pattern['title'] ?? __('Business pattern detected', 'elev8-os')),
                $action,
                sprintf(__('%1$d confirmed observations support this %2$s pattern. Trend: %3$s. Confidence: %4$d%%.', 'elev8-os'), $count, $classification, (string) ($pattern['trend'] ?? 'stable'), (int) ($pattern['confidence'] ?? 0)),
                self::intelligence_url('patterns'),
                self::severity_score($severity) + min(20, $count * 3)
            );
        }
        return array_slice($cards, 0, 8);
    }

    /** @return array<int,array<string,mixed>> */
    private static function recommendation_cards(string $role, int $organization_unit_id): array {
        if (!class_exists('Elev8_OS_Intelligence_Recommendation_Service')) { return []; }
        $items = Elev8_OS_Intelligence_Recommendation_Service::query(['organization_unit_id'=>$organization_unit_id,'posts_per_page'=>100]);
        $cards = [];
        foreach ($items as $item) {
            $status = sanitize_key((string) ($item['status'] ?? 'proposed'));
            $classification = sanitize_key((string) ($item['classification'] ?? 'information'));
            if ($status === 'rejected' || !self::role_accepts($role, $classification, $classification)) { continue; }
            if ($status === 'proposed' && !in_array($role, ['owner','shop_manager','glass_manager'], true)) { continue; }
            $severity = sanitize_key((string) ($item['severity'] ?? 'normal'));
            $title = (string) ($item['title'] ?? __('Recommendation ready for review', 'elev8-os'));
            $next = $status === 'proposed'
                ? __('Review the evidence and decide whether this recommendation should move into execution.', 'elev8-os')
                : __('Keep the approved action moving and record its outcome when the work is complete.', 'elev8-os');
            $confidence = (int) ($item['calibrated_confidence'] ?? $item['confidence'] ?? 0);
            $cards[] = self::card('recommendation:'.(int) ($item['id'] ?? 0).':'.$role, 'recommendation', $classification, $severity,
                $title, $next,
                sprintf(__('This %1$s recommendation is %2$s with %3$d%% confidence and remains governed by the existing approval boundary.', 'elev8-os'), $classification, $status, $confidence),
                self::intelligence_url('recommendations'), self::severity_score($severity) + ($status === 'proposed' ? 18 : 8));
        }
        return array_slice($cards, 0, 6);
    }

    /** @param array<int,array<string,mixed>> $cards @return array<int,array<string,mixed>> */
    private static function decorate_states(array $cards, int $user_id): array {
        $states = get_user_meta($user_id, self::META_STATES, true);
        $states = is_array($states) ? $states : [];
        foreach ($cards as &$card) {
            $state = (string) ($states[$card['key']]['state'] ?? 'unread');
            $card['state'] = $state;
            $card['pinned'] = $state === 'pinned';
        }
        unset($card);
        return $cards;
    }

    /** @return array<string,mixed> */
    private static function card(string $key, string $source_type, string $category, string $severity, string $title, string $action, string $why, string $url, int $score): array {
        return ['key'=>$key,'source_type'=>$source_type,'category'=>$category,'severity'=>$severity,'title'=>$title,'suggested_action'=>$action,'why'=>$why,'url'=>$url,'priority_score'=>min(100,max(0,$score))];
    }

    private static function category_for_pattern(array $pattern): string {
        $text = strtolower((string) ($pattern['title'] ?? '').' '.implode(' ', (array) ($pattern['tags'] ?? [])));
        if (preg_match('/inventory|stock|supply|product/', $text)) { return 'inventory'; }
        if (preg_match('/maintenance|repair|equipment|asset|safety/', $text)) { return 'maintenance'; }
        if (preg_match('/customer|client|complaint|experience/', $text)) { return 'customer_experience'; }
        if (preg_match('/class|demand|sales|revenue|booking/', $text)) { return 'sales'; }
        return sanitize_key((string) ($pattern['classification'] ?? 'operations')) ?: 'operations';
    }

    private static function role_accepts(string $role, string $classification, string $category): bool {
        if ($role === 'owner') { return true; }
        $allowed = [
            'shop_manager'=>['risk','follow_up','operations','inventory','customer_experience','sales','achievement','efficiency'],
            'glass_manager'=>['risk','follow_up','operations','maintenance','inventory','achievement','efficiency'],
            'glassblower'=>['operations','maintenance','risk','achievement','efficiency'],
            'artist'=>['opportunity','achievement','sales','marketing','customer_experience','efficiency'],
            'teacher'=>['opportunity','achievement','sales','customer_experience','efficiency','follow_up'],
            'event_host'=>['opportunity','follow_up','operations','customer_experience','achievement'],
            'retail'=>['inventory','customer_experience','operations','achievement','efficiency','risk'],
            'volunteer'=>['operations','achievement','follow_up'],
            'team'=>['operations','achievement','efficiency'],
        ];
        $values = $allowed[$role] ?? $allowed['team'];
        return in_array($classification, $values, true) || in_array($category, $values, true);
    }

    private static function pattern_action(string $classification, string $category): string {
        if ($category === 'maintenance') { return __('Review the affected asset or facility condition and confirm whether preventive or corrective work is already assigned.', 'elev8-os'); }
        if ($category === 'inventory') { return __('Verify the authoritative quantity and confirm reorder, transfer, production, or reconciliation ownership.', 'elev8-os'); }
        if ($classification === 'opportunity' || $category === 'sales') { return __('Review the supporting demand and choose one small, measurable next step to test the opportunity.', 'elev8-os'); }
        if ($classification === 'achievement') { return __('Recognize the people involved and identify which repeatable behavior should become standard practice.', 'elev8-os'); }
        return __('Review the supporting evidence, confirm ownership, and decide whether the condition needs a governed Recommendation or operational follow-up.', 'elev8-os');
    }

    private static function severity_score(string $severity): int {
        return ['low'=>25,'normal'=>45,'high'=>70,'critical'=>90][$severity] ?? 45;
    }

    private static function intelligence_url(string $view): string {
        return add_query_arg('view', $view, home_url('/elev8-intelligence-review/'));
    }

    public static function register_graph_relationships(array $relationships): array {
        $relationships['governed_intelligence_projects_coaching'] = [
            'label' => __('Projects role-specific coaching', 'elev8-os'),
            'from' => ['pattern','recommendation','work'],
            'to' => ['coaching_projection'],
            'directional' => true,
            'notes' => __('Coaching is a personal, explainable read model. It owns only user presentation state and never creates or changes operational or intelligence records.', 'elev8-os'),
        ];
        return $relationships;
    }
}

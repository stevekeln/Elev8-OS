<?php
/**
 * Explainable, rule-based intelligence for Universal Workspaces.
 *
 * Uses verified workspace data only. It does not modify source records or run
 * autonomous actions.
 */
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Workspace_Intelligence_Service {
    /** @return array<string,mixed> */
    public static function analyze(string $type, int $id, ?WP_User $user = null): array {
        $type = Elev8_OS_Workspace_Service::normalize_type($type);
        $user = $user instanceof WP_User ? $user : wp_get_current_user();
        if (!Elev8_OS_Workspace_Service::can_view($type, $id, $user)) { return []; }

        $summary = Elev8_OS_Workspace_Service::summary($type, $id);
        $work = Elev8_OS_Workspace_Service::work_items($type, $id);
        $conversations = Elev8_OS_Workspace_Service::conversations($type, $id);
        $activities = Elev8_OS_Workspace_Service::activities($type, $id, 100);
        $impact = Elev8_OS_Workspace_Service::relationship_impact($type, $id);
        $suggestions = class_exists('Elev8_OS_Automation_Service') ? Elev8_OS_Automation_Service::suggestions($type, $id, $user) : [];

        $signals = self::signals($work, $conversations, $activities, $impact, $suggestions);
        $score = self::score($signals);
        $status = $score >= 80 ? 'healthy' : ($score >= 55 ? 'attention' : 'action_required');
        $label = $status === 'healthy' ? __('Healthy', 'elev8-os') : ($status === 'attention' ? __('Needs Attention', 'elev8-os') : __('Action Required', 'elev8-os'));

        return [
            'generated_at' => current_time('mysql'),
            'score' => $score,
            'status' => $status,
            'label' => $label,
            'headline' => self::headline($status, $summary),
            'signals' => $signals,
            'risks' => self::risks($signals),
            'opportunities' => self::opportunities($signals, $summary),
            'next_step' => self::next_step($signals, $suggestions, $summary),
            'confidence' => self::confidence($signals),
            'why' => self::why($signals, $score),
        ];
    }

    /** @return array<string,int|bool|string> */
    private static function signals(array $work, array $conversations, array $activities, array $impact, array $suggestions): array {
        $now = current_time('timestamp');
        $open = 0; $overdue = 0; $waiting = 0; $completed = 0;
        foreach ($work as $item) {
            if (!$item instanceof WP_Post) { continue; }
            $status = sanitize_key((string) get_post_meta($item->ID, '_elev8_work_status', true));
            $due = (string) get_post_meta($item->ID, '_elev8_work_due_date', true);
            if ($status === 'completed') { $completed++; continue; }
            $open++;
            if ($status === 'waiting') { $waiting++; }
            if ($due !== '') {
                $due_ts = strtotime($due . ' 23:59:59');
                if ($due_ts && $due_ts < $now) { $overdue++; }
            }
        }
        $open_conversations = 0;
        foreach ($conversations as $thread) {
            if (!$thread instanceof WP_Post) { continue; }
            $status = sanitize_key((string) get_post_meta($thread->ID, '_elev8_conversation_status', true));
            if ($status !== 'closed') { $open_conversations++; }
        }
        $latest_activity = '';
        if (!empty($activities[0]) && $activities[0] instanceof WP_Post) { $latest_activity = (string) $activities[0]->post_date; }
        $stale_days = 0;
        if ($latest_activity !== '') {
            $latest_ts = strtotime($latest_activity);
            if ($latest_ts) { $stale_days = max(0, (int) floor(($now - $latest_ts) / DAY_IN_SECONDS)); }
        }
        return [
            'open_actions' => $open,
            'overdue_actions' => $overdue,
            'waiting_actions' => $waiting,
            'completed_actions' => $completed,
            'open_conversations' => $open_conversations,
            'activity_count' => count($activities),
            'stale_days' => $stale_days,
            'dependencies' => absint($impact['depends_on'] ?? 0),
            'blocked_records' => absint($impact['blocks'] ?? 0),
            'connected_people' => absint($impact['people'] ?? 0),
            'explicit_links' => absint($impact['total'] ?? 0),
            'suggested_actions' => count($suggestions),
            'latest_activity' => $latest_activity,
        ];
    }

    private static function score(array $s): int {
        $score = 100;
        $score -= min(40, ((int) $s['overdue_actions']) * 20);
        $score -= min(20, ((int) $s['blocked_records']) * 10);
        $score -= min(12, ((int) $s['waiting_actions']) * 6);
        $score -= min(12, ((int) $s['suggested_actions']) * 4);
        if ((int) $s['stale_days'] >= 14) { $score -= 15; }
        elseif ((int) $s['stale_days'] >= 7) { $score -= 8; }
        if ((int) $s['open_actions'] > 0 && (int) $s['connected_people'] === 0) { $score -= 5; }
        return max(0, min(100, $score));
    }

    private static function headline(string $status, array $summary): string {
        $title = (string) ($summary['title'] ?? __('This workspace', 'elev8-os'));
        if ($status === 'action_required') { return sprintf(__('%s has verified blockers or overdue follow-up.', 'elev8-os'), $title); }
        if ($status === 'attention') { return sprintf(__('%s is moving, but follow-up is still needed.', 'elev8-os'), $title); }
        return sprintf(__('%s has no verified urgent blockers.', 'elev8-os'), $title);
    }

    /** @return array<int,string> */
    private static function risks(array $s): array {
        $items = [];
        if ((int) $s['overdue_actions'] > 0) { $items[] = sprintf(_n('%d connected action is overdue.', '%d connected actions are overdue.', (int) $s['overdue_actions'], 'elev8-os'), (int) $s['overdue_actions']); }
        if ((int) $s['blocked_records'] > 0) { $items[] = sprintf(_n('%d related record is blocked.', '%d related records are blocked.', (int) $s['blocked_records'], 'elev8-os'), (int) $s['blocked_records']); }
        if ((int) $s['waiting_actions'] > 0) { $items[] = sprintf(_n('%d action is waiting.', '%d actions are waiting.', (int) $s['waiting_actions'], 'elev8-os'), (int) $s['waiting_actions']); }
        if ((int) $s['stale_days'] >= 7) { $items[] = sprintf(__('No verified activity has been recorded for %d days.', 'elev8-os'), (int) $s['stale_days']); }
        return array_slice($items, 0, 4);
    }

    /** @return array<int,string> */
    private static function opportunities(array $s, array $summary): array {
        $items = [];
        if ((int) $s['suggested_actions'] > 0) { $items[] = __('A confirmed next action is available in this workspace.', 'elev8-os'); }
        if ((int) $s['open_conversations'] > 0 && (int) $s['open_actions'] === 0) { $items[] = __('Turn an open conversation into an assigned action so follow-up has an owner.', 'elev8-os'); }
        if ((int) $s['explicit_links'] === 0) { $items[] = __('Connect related records to improve context, impact analysis, and future automation.', 'elev8-os'); }
        if ((int) $s['connected_people'] === 0) { $items[] = __('Connect the responsible people so ownership is visible.', 'elev8-os'); }
        if (!$items) { $items[] = sprintf(__('Continue monitoring %s through its verified timeline.', 'elev8-os'), (string) ($summary['label'] ?? __('this workspace', 'elev8-os'))); }
        return array_slice($items, 0, 3);
    }

    /** @return array<string,string> */
    private static function next_step(array $s, array $suggestions, array $summary): array {
        if (!empty($suggestions[0]) && is_array($suggestions[0])) {
            return ['title' => (string) ($suggestions[0]['title'] ?? __('Review suggested action', 'elev8-os')), 'reason' => (string) ($suggestions[0]['reason'] ?? __('A verified condition produced this recommendation.', 'elev8-os'))];
        }
        if ((int) $s['overdue_actions'] > 0) { return ['title' => __('Resolve the oldest overdue action', 'elev8-os'), 'reason' => __('Overdue work is the strongest verified risk in this workspace.', 'elev8-os')]; }
        if ((int) $s['open_conversations'] > 0) { return ['title' => __('Review the open conversation', 'elev8-os'), 'reason' => __('A connected conversation remains open and may contain a decision or follow-up.', 'elev8-os')]; }
        return ['title' => __('Keep the workspace current', 'elev8-os'), 'reason' => sprintf(__('No urgent next action is verified for %s.', 'elev8-os'), (string) ($summary['title'] ?? __('this record', 'elev8-os')))];
    }

    /** @return array<string,mixed> */
    private static function confidence(array $s): array {
        $sources = 2; // source record + workspace summary
        if ((int) $s['activity_count'] > 0) { $sources++; }
        if ((int) $s['explicit_links'] > 0) { $sources++; }
        if ((int) $s['open_actions'] + (int) $s['completed_actions'] > 0) { $sources++; }
        if ((int) $s['open_conversations'] > 0) { $sources++; }
        $percent = min(100, 40 + ($sources * 10));
        return ['percent' => $percent, 'label' => $percent >= 80 ? __('High', 'elev8-os') : ($percent >= 60 ? __('Medium', 'elev8-os') : __('Low', 'elev8-os')), 'sources' => $sources];
    }

    /** @return array<int,string> */
    private static function why(array $s, int $score): array {
        return [
            sprintf(__('Health score: %d out of 100.', 'elev8-os'), $score),
            sprintf(__('Verified actions: %1$d open, %2$d overdue, %3$d waiting, %4$d completed.', 'elev8-os'), (int) $s['open_actions'], (int) $s['overdue_actions'], (int) $s['waiting_actions'], (int) $s['completed_actions']),
            sprintf(__('Business Graph: %1$d explicit links, %2$d dependencies, %3$d blocked records.', 'elev8-os'), (int) $s['explicit_links'], (int) $s['dependencies'], (int) $s['blocked_records']),
            sprintf(__('Connected context: %1$d open conversations, %2$d people, %3$d timeline entries.', 'elev8-os'), (int) $s['open_conversations'], (int) $s['connected_people'], (int) $s['activity_count']),
            __('The score is rule-based and does not use generative AI or guessed business data.', 'elev8-os'),
        ];
    }
}

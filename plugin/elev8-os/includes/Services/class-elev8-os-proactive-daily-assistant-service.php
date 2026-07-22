<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Personal start-of-day read model.
 *
 * Combines governed Coaching, Operations, Attention, and Conversation evidence
 * without creating or changing any authoritative record.
 */
final class Elev8_OS_Proactive_Daily_Assistant_Service {
    private const META_LAST_VIEWED = '_elev8_os_daily_assistant_last_viewed';

    public static function init(): void {
        add_filter('elev8_os_business_graph_relationships', [__CLASS__, 'register_graph_relationships']);
    }

    /** @return array<string,mixed> */
    public static function briefing(?WP_User $user = null): array {
        $user = $user ?: (class_exists('Elev8_OS_Preview_Service') ? Elev8_OS_Preview_Service::effective_user() : wp_get_current_user());
        if (!$user instanceof WP_User || !$user->ID) { return []; }

        $role_key = class_exists('Elev8_OS_Workspace_Resolver_Service') ? Elev8_OS_Workspace_Resolver_Service::role_key($user) : 'team';
        $role_label = class_exists('Elev8_OS_Workspace_Resolver_Service') ? Elev8_OS_Workspace_Resolver_Service::role_label($user) : __('Team Member', 'elev8-os');
        $attention = class_exists('Elev8_OS_Attention_Service') ? Elev8_OS_Attention_Service::items($user, 12) : [];
        $coaching = class_exists('Elev8_OS_Business_Coaching_Service') ? Elev8_OS_Business_Coaching_Service::cards($user) : [];
        $work = self::work_summary($user, $role_key);
        $unread_conversations = class_exists('Elev8_OS_Conversation_Service') && Elev8_OS_Access_Service::user_can('view_conversations', $user)
            ? Elev8_OS_Conversation_Service::unread_count((int) $user->ID)
            : 0;

        return [
            'user_id' => (int) $user->ID,
            'name' => self::first_name($user),
            'role_key' => $role_key,
            'role_label' => $role_label,
            'generated_at' => current_time('mysql'),
            'greeting' => self::greeting(),
            'focus' => self::focus_items($attention, $coaching),
            'attention' => array_slice($attention, 0, 6),
            'coaching' => array_slice($coaching, 0, 5),
            'work' => $work,
            'unread_conversations' => $unread_conversations,
            'quick_links' => self::quick_links($user),
            'last_viewed' => (string) get_user_meta($user->ID, self::META_LAST_VIEWED, true),
        ];
    }

    public static function mark_viewed(int $user_id): void {
        if ($user_id > 0) { update_user_meta($user_id, self::META_LAST_VIEWED, current_time('mysql')); }
    }

    /** @return array<string,int> */
    private static function work_summary(WP_User $user, string $role_key): array {
        $summary = ['open'=>0,'due_today'=>0,'overdue'=>0,'unassigned'=>0];
        if (!class_exists('Elev8_OS_Operations_Engine_Service')) { return $summary; }
        $can_manage = in_array($role_key, ['owner','shop_manager','glass_manager'], true) || Elev8_OS_Access_Service::user_can('manage_work', $user);
        $metrics = Elev8_OS_Operations_Engine_Service::metrics($user, $can_manage);
        $summary['open'] = (int) ($metrics['active'] ?? 0);
        $summary['due_today'] = (int) ($metrics['due_today'] ?? 0);
        $summary['overdue'] = (int) ($metrics['overdue'] ?? 0);
        $summary['unassigned'] = (int) ($metrics['unassigned'] ?? 0);
        return $summary;
    }

    /** @param array<int,array<string,mixed>> $attention @param array<int,array<string,mixed>> $coaching @return array<int,array<string,mixed>> */
    private static function focus_items(array $attention, array $coaching): array {
        $items = [];
        foreach ($attention as $item) {
            $severity = sanitize_key((string) ($item['severity'] ?? 'normal'));
            $items[] = [
                'key' => 'attention:' . sanitize_key((string) ($item['id'] ?? md5(wp_json_encode($item)))),
                'source' => 'attention',
                'title' => (string) ($item['title'] ?? __('Attention item', 'elev8-os')),
                'summary' => (string) ($item['summary'] ?? ''),
                'severity' => $severity,
                'url' => (string) ($item['url'] ?? ''),
                'score' => self::severity_score($severity) + 20,
            ];
        }
        foreach ($coaching as $card) {
            $severity = sanitize_key((string) ($card['severity'] ?? 'normal'));
            $state = sanitize_key((string) ($card['state'] ?? 'unread'));
            $items[] = [
                'key' => 'coaching:' . sanitize_key((string) ($card['key'] ?? md5(wp_json_encode($card)))),
                'source' => 'coaching',
                'title' => (string) ($card['title'] ?? __('Coaching guidance', 'elev8-os')),
                'summary' => (string) ($card['suggested_action'] ?? ''),
                'severity' => $severity,
                'url' => (string) ($card['url'] ?? ''),
                'score' => (int) ($card['priority_score'] ?? self::severity_score($severity)) + ($state === 'follow_up' ? 25 : ($state === 'pinned' ? 18 : 0)),
            ];
        }
        $seen = [];
        $unique = [];
        usort($items, static fn(array $a, array $b): int => (int) ($b['score'] ?? 0) <=> (int) ($a['score'] ?? 0));
        foreach ($items as $item) {
            $fingerprint = strtolower(trim((string) ($item['title'] ?? '')));
            if ($fingerprint === '' || isset($seen[$fingerprint])) { continue; }
            $seen[$fingerprint] = true;
            $unique[] = $item;
            if (count($unique) >= 5) { break; }
        }
        return $unique;
    }

    /** @return array<int,array<string,string>> */
    private static function quick_links(WP_User $user): array {
        $links = [];
        if (class_exists('Elev8_OS_Workspace_Resolver_Service')) {
            $links[] = ['label'=>__('My Dashboard','elev8-os'),'url'=>Elev8_OS_Workspace_Resolver_Service::destination($user),'icon'=>'🏠'];
        }
        if (Elev8_OS_Access_Service::user_can('view_work', $user) && class_exists('Elev8_OS_Work_Module')) {
            $links[] = ['label'=>__('My Work','elev8-os'),'url'=>Elev8_OS_Work_Module::my_url(),'icon'=>'☑'];
        }
        if (Elev8_OS_Access_Service::user_can('view_conversations', $user) && class_exists('Elev8_OS_Conversations_Module')) {
            $links[] = ['label'=>__('Conversations','elev8-os'),'url'=>Elev8_OS_Conversations_Module::url(),'icon'=>'💬'];
        }
        if (class_exists('Elev8_OS_Business_Coaching_Module')) {
            $links[] = ['label'=>__('Business Coaching','elev8-os'),'url'=>Elev8_OS_Business_Coaching_Module::url(),'icon'=>'🧭'];
        }
        if (Elev8_OS_Access_Service::user_can('view_business_memory', $user)) {
            $links[] = ['label'=>__('Business Memory','elev8-os'),'url'=>admin_url('admin.php?page=elev8-business-memory'),'icon'=>'📝'];
        }
        return $links;
    }

    private static function first_name(WP_User $user): string {
        $name = trim((string) $user->first_name);
        if ($name === '') { $name = trim((string) $user->display_name); }
        if ($name === '') { return __('there', 'elev8-os'); }
        $parts = preg_split('/\s+/', $name);
        return (string) ($parts[0] ?? $name);
    }

    private static function greeting(): string {
        $hour = (int) current_time('G');
        if ($hour < 12) { return __('Good morning', 'elev8-os'); }
        if ($hour < 18) { return __('Good afternoon', 'elev8-os'); }
        return __('Good evening', 'elev8-os');
    }

    private static function severity_score(string $severity): int {
        return ['low'=>20,'normal'=>45,'high'=>75,'critical'=>100][$severity] ?? 45;
    }

    public static function register_graph_relationships(array $relationships): array {
        $relationships['governed_evidence_projects_daily_assistant'] = [
            'label' => __('Projects a personal daily briefing', 'elev8-os'),
            'from' => ['work','conversation','attention_projection','coaching_projection'],
            'to' => ['daily_assistant_projection'],
            'directional' => true,
            'notes' => __('The Daily Assistant is a personal read model. It combines permitted evidence but owns no business fact, decision, conversation, or Work Item.', 'elev8-os'),
        ];
        return $relationships;
    }
}

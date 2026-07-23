<?php
/**
 * Shared Elev8 OS Attention Center.
 *
 * Connected engines publish verified items here so dashboards can answer
 * "What needs me right now?" without duplicating module-specific logic.
 *
 * @package Elev8OS
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Elev8_OS_Attention_Service {

    /**
     * Return prioritized attention items visible to the supplied user.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function items(?WP_User $user = null, int $limit = 20): array {
        $user = $user ?: wp_get_current_user();
        $items = [];

        if (Elev8_OS_Access_Service::user_can('view_ceo_dashboard', $user)) {
            $items = array_merge($items, self::manager_attention_items($limit));
        }

        $items = array_merge($items, self::work_items($user));
        $items = array_merge($items, self::reservation_items($user));
        $items = array_merge($items, self::event_application_items($user));
        $items = array_merge($items, self::conversation_items($user));

        usort($items, [__CLASS__, 'sort_items']);

        return array_slice($items, 0, max(1, $limit));
    }

    /**
     * Return a compact summary for role-aware dashboards.
     *
     * @return array<string,mixed>
     */
    public static function summary(?WP_User $user = null): array {
        $items = self::items($user, 50);
        $summary = [
            'available' => true,
            'total' => count($items),
            'critical' => 0,
            'high' => 0,
            'normal' => 0,
            'items' => $items,
            'generated_at' => current_time('mysql'),
        ];

        foreach ($items as $item) {
            $severity = (string) ($item['severity'] ?? 'normal');
            if (!isset($summary[$severity])) {
                $severity = 'normal';
            }
            $summary[$severity]++;
        }

        return $summary;
    }

    /** @return array<int,array<string,mixed>> */
    private static function manager_attention_items(int $limit): array {
        if (!class_exists('Elev8_OS_Daily_Operations_Service')) {
            return [];
        }

        $query = new WP_Query([
            'post_type' => Elev8_OS_Daily_Operations_Service::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => max(1, min(10, $limit)),
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => [
                [
                    'key' => Elev8_OS_Daily_Operations_Service::META_ATTENTION,
                    'value' => 1,
                    'compare' => '=',
                    'type' => 'NUMERIC',
                ],
                [
                    'key' => Elev8_OS_Daily_Operations_Service::META_STATUS,
                    'value' => ['reviewed', 'completed'],
                    'compare' => 'NOT IN',
                ],
            ],
        ]);

        $items = [];
        foreach ($query->posts as $post) {
            $entry = Elev8_OS_Daily_Operations_Service::entry((int) $post->ID);
            if (!is_array($entry)) {
                continue;
            }

            $fields = is_array($entry['fields'] ?? null) ? $entry['fields'] : [];
            $message = trim((string) ($fields['owner_attention_items'] ?? ''));
            $author = get_the_author_meta('display_name', (int) $post->post_author);
            $location = trim((string) ($entry['location'] ?? ''));
            $summary = $message !== ''
                ? wp_trim_words($message, 24, '…')
                : __('An operating log was marked for owner attention.', 'elev8-os');

            if ($location !== '') {
                $summary .= ' · ' . $location;
            }

            $items[] = self::item(
                'operations:' . (int) $post->ID,
                'high',
                __('Manager note for Steve', 'elev8-os'),
                $summary,
                $author !== '' ? $author : __('Manager Operations Log', 'elev8-os'),
                add_query_arg([
                    'page' => 'elev8-daily-operations',
                    'view' => 'entry',
                    'entry_id' => (int) $post->ID,
                ], admin_url('admin.php')),
                (string) $post->post_date,
                'clipboard'
            );
        }

        return $items;
    }

    /** @return array<int,array<string,mixed>> */
    private static function work_items(WP_User $user): array {
        if (!class_exists('Elev8_OS_Work_Service') || !class_exists('Elev8_OS_Work_Module')) {
            return [];
        }

        $items = [];
        $my = Elev8_OS_Work_Service::counts((int) $user->ID);
        if ((int) ($my['overdue'] ?? 0) > 0) {
            $count = (int) $my['overdue'];
            $items[] = self::aggregate_item('work:my-overdue', 'critical', __('My overdue work', 'elev8-os'), $count, Elev8_OS_Work_Module::my_url(), 'warning');
        }
        if ((int) ($my['due_today'] ?? 0) > 0) {
            $count = (int) $my['due_today'];
            $items[] = self::aggregate_item('work:my-today', 'high', __('My work due today', 'elev8-os'), $count, Elev8_OS_Work_Module::my_url(), 'clock');
        }

        if (Elev8_OS_Access_Service::user_can('manage_work', $user)) {
            $team = Elev8_OS_Work_Service::counts();
            if ((int) ($team['unassigned'] ?? 0) > 0) {
                $count = (int) $team['unassigned'];
                $items[] = self::aggregate_item('work:unassigned', 'high', __('Unassigned team work', 'elev8-os'), $count, Elev8_OS_Work_Module::team_url(), 'groups');
            }
        }

        return $items;
    }

    /** @return array<int,array<string,mixed>> */
    private static function reservation_items(WP_User $user): array {
        if (!class_exists('Elev8_OS_Bingo_Reservations_Module')) {
            return [];
        }

        $can_manage = Elev8_OS_Access_Service::user_can('manage_reservations', $user)
            || Elev8_OS_Access_Service::user_can('manage_bingo', $user);
        $owner_id = $can_manage ? 0 : (int) $user->ID;
        $count = Elev8_OS_Bingo_Reservations_Module::attention_count($owner_id);

        return $count > 0
            ? [self::aggregate_item('reservations:attention', 'normal', __('Reservations needing attention', 'elev8-os'), $count, Elev8_OS_Bingo_Reservations_Module::admin_url(), 'tickets-alt')]
            : [];
    }

    /** @return array<int,array<string,mixed>> */
    private static function conversation_items(WP_User $user): array {
        if (!class_exists('Elev8_OS_Conversation_Service') || !class_exists('Elev8_OS_Conversations_Module') || !Elev8_OS_Access_Service::user_can('view_conversations', $user)) { return []; }
        $count = Elev8_OS_Conversation_Service::unread_count((int) $user->ID);
        return $count > 0
            ? [self::aggregate_item('conversations:unread', 'high', __('Unread conversations', 'elev8-os'), $count, Elev8_OS_Conversations_Module::url(), 'format-chat')]
            : [];
    }

    /** @return array<int,array<string,mixed>> */
    private static function event_application_items(WP_User $user): array {
        if (!class_exists('Elev8_OS_Event_Applications_Module') || !Elev8_OS_Access_Service::user_can('manage_events', $user)) {
            return [];
        }

        $count = Elev8_OS_Event_Applications_Module::attention_count();
        return $count > 0
            ? [self::aggregate_item('event-applications:attention', 'normal', __('Event applications waiting for review', 'elev8-os'), $count, Elev8_OS_Event_Applications_Module::admin_url(), 'forms')]
            : [];
    }

    /** @return array<string,mixed> */
    private static function aggregate_item(string $id, string $severity, string $title, int $count, string $url, string $icon): array {
        return self::item(
            $id,
            $severity,
            $title,
            sprintf(_n('%d item is waiting.', '%d items are waiting.', $count, 'elev8-os'), $count),
            __('Elev8 OS', 'elev8-os'),
            $url,
            current_time('mysql'),
            $icon,
            $count
        );
    }

    /** @return array<string,mixed> */
    private static function item(string $id, string $severity, string $title, string $summary, string $source, string $url, string $created_at, string $icon, int $count = 1): array {
        return compact('id', 'severity', 'title', 'summary', 'source', 'url', 'created_at', 'icon', 'count');
    }

    /** @param array<string,mixed> $a @param array<string,mixed> $b */
    private static function sort_items(array $a, array $b): int {
        $weights = ['critical' => 30, 'high' => 20, 'normal' => 10];
        $severity_compare = ($weights[(string) ($b['severity'] ?? 'normal')] ?? 0)
            <=> ($weights[(string) ($a['severity'] ?? 'normal')] ?? 0);
        if ($severity_compare !== 0) {
            return $severity_compare;
        }

        return strtotime((string) ($b['created_at'] ?? '')) <=> strtotime((string) ($a['created_at'] ?? ''));
    }
}

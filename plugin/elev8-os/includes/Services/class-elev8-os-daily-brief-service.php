<?php
/**
 * Shared, rule-based Daily Brief Engine for Elev8 OS.
 *
 * Produces an explainable briefing from verified operational services and
 * immutable activity records. Missing sources remain explicitly unavailable.
 *
 * @package Elev8OS
 */
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Daily_Brief_Service {

    /** @return array<string,mixed> */
    public static function build(array $summary, array $metrics, ?WP_User $user = null): array {
        $user = $user ?: wp_get_current_user();
        $yesterday = self::yesterday_range();
        $activities = self::activities_between($yesterday['start'], $yesterday['end'], 12);
        $operations = self::operations_between($yesterday['start'], $yesterday['end']);
        $work = self::work_activity_summary($activities);
        $attention = is_array($summary['attention'] ?? null) ? $summary['attention'] : [];
        $pulse = self::pulse($attention);

        return [
            'generated_at' => current_time('mysql'),
            'greeting' => self::greeting($user),
            'date_label' => wp_date(get_option('date_format'), current_time('timestamp')),
            'pulse' => $pulse,
            'yesterday' => self::yesterday_items($operations, $work, $activities),
            'attention' => self::attention_items($attention),
            'wins' => self::wins($operations, $work, $activities, $attention, $metrics),
            'risks' => self::risks($summary),
            'opportunities' => self::opportunities($summary, $metrics),
            'focus' => self::focus($attention),
            'timeline' => self::timeline($activities),
            'confidence' => self::confidence($activities, $operations, $summary),
            'explanations' => self::explanations($yesterday, $activities, $operations, $attention),
        ];
    }

    /** @return array{start:string,end:string,label:string} */
    private static function yesterday_range(): array {
        $timestamp = current_time('timestamp');
        $start = wp_date('Y-m-d 00:00:00', strtotime('-1 day', $timestamp));
        $end = wp_date('Y-m-d 23:59:59', strtotime('-1 day', $timestamp));
        return ['start' => $start, 'end' => $end, 'label' => wp_date(get_option('date_format'), strtotime($start))];
    }

    /** @return array<int,WP_Post> */
    private static function activities_between(string $start, string $end, int $limit): array {
        if (!class_exists('Elev8_OS_Activity_Service')) { return []; }
        return get_posts([
            'post_type' => Elev8_OS_Activity_Service::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => max(1, min(50, $limit)),
            'orderby' => 'date',
            'order' => 'DESC',
            'date_query' => [['after' => $start, 'before' => $end, 'inclusive' => true]],
        ]);
    }

    /** @return array<string,int|bool> */
    private static function operations_between(string $start, string $end): array {
        if (!class_exists('Elev8_OS_Daily_Operations_Service')) {
            return ['available' => false, 'total' => 0, 'manager' => 0, 'attention' => 0];
        }
        $base = [
            'post_type' => Elev8_OS_Daily_Operations_Service::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'date_query' => [['after' => $start, 'before' => $end, 'inclusive' => true]],
        ];
        $count = static function(array $extra) use ($base): int {
            $query = new WP_Query(array_merge($base, $extra));
            return (int) $query->found_posts;
        };
        return [
            'available' => true,
            'total' => $count([]),
            'manager' => $count(['meta_query' => [[
                'key' => Elev8_OS_Daily_Operations_Service::META_TEMPLATE,
                'value' => 'manager',
            ]]]),
            'attention' => $count(['meta_query' => [[
                'key' => Elev8_OS_Daily_Operations_Service::META_ATTENTION,
                'value' => 1,
                'type' => 'NUMERIC',
            ]]]),
        ];
    }

    /** @param array<int,WP_Post> $activities @return array<string,int> */
    private static function work_activity_summary(array $activities): array {
        $created = 0; $completed = 0; $updated = 0;
        foreach ($activities as $activity) {
            $type = (string) get_post_meta($activity->ID, '_elev8_activity_type', true);
            if ($type === 'work_created') { $created++; }
            if ($type === 'work_updated') {
                $updated++;
                if (stripos((string) $activity->post_content, 'completed') !== false) { $completed++; }
            }
        }
        return ['created' => $created, 'completed' => $completed, 'updated' => $updated];
    }

    /** @return array<string,string|int> */
    private static function pulse(array $attention): array {
        $critical = (int) ($attention['critical'] ?? 0);
        $high = (int) ($attention['high'] ?? 0);
        $total = (int) ($attention['total'] ?? 0);
        if ($critical > 0) {
            return ['status' => 'critical', 'label' => __('Action Required', 'elev8-os'), 'reason' => sprintf(_n('%d critical item is waiting.', '%d critical items are waiting.', $critical, 'elev8-os'), $critical)];
        }
        if ($high > 0 || $total > 0) {
            return ['status' => 'busy', 'label' => __('Needs Attention', 'elev8-os'), 'reason' => sprintf(_n('%d verified item is waiting.', '%d verified items are waiting.', $total, 'elev8-os'), $total)];
        }
        return ['status' => 'healthy', 'label' => __('Healthy', 'elev8-os'), 'reason' => __('No verified urgent items are waiting.', 'elev8-os')];
    }

    /** @return array<int,string> */
    private static function yesterday_items(array $operations, array $work, array $activities): array {
        $items = [];
        if (!empty($operations['available'])) {
            $manager = (int) ($operations['manager'] ?? 0);
            $total = (int) ($operations['total'] ?? 0);
            if ($manager > 0) { $items[] = sprintf(_n('%d manager operating log was submitted.', '%d manager operating logs were submitted.', $manager, 'elev8-os'), $manager); }
            if ($total > $manager) { $items[] = sprintf(_n('%d additional operating log was submitted.', '%d additional operating logs were submitted.', $total - $manager, 'elev8-os'), $total - $manager); }
        }
        if ((int) ($work['completed'] ?? 0) > 0) { $items[] = sprintf(_n('%d work item was completed.', '%d work items were completed.', (int) $work['completed'], 'elev8-os'), (int) $work['completed']); }
        if ((int) ($work['created'] ?? 0) > 0) { $items[] = sprintf(_n('%d new work item was created.', '%d new work items were created.', (int) $work['created'], 'elev8-os'), (int) $work['created']); }
        if (!$items && $activities) { $items[] = sprintf(_n('%d verified business activity was recorded.', '%d verified business activities were recorded.', count($activities), 'elev8-os'), count($activities)); }
        if (!$items) { $items[] = __('No verified activity summary is available for yesterday.', 'elev8-os'); }
        return array_slice($items, 0, 5);
    }

    /** @return array<int,array<string,string>> */
    private static function attention_items(array $attention): array {
        $result = [];
        foreach ((array) ($attention['items'] ?? []) as $item) {
            if (!is_array($item)) { continue; }
            $result[] = [
                'title' => (string) ($item['title'] ?? __('Attention item', 'elev8-os')),
                'summary' => (string) ($item['summary'] ?? ''),
                'severity' => (string) ($item['severity'] ?? 'normal'),
                'url' => (string) ($item['url'] ?? ''),
            ];
        }
        return array_slice($result, 0, 5);
    }

    /** @return array<int,string> */
    private static function wins(array $operations, array $work, array $activities, array $attention, array $metrics): array {
        $wins = [];
        if ((int) ($attention['critical'] ?? 0) === 0) { $wins[] = __('No critical operating issues are waiting.', 'elev8-os'); }
        if ((int) ($operations['total'] ?? 0) > 0) { $wins[] = sprintf(_n('%d operating log preserved yesterday’s business memory.', '%d operating logs preserved yesterday’s business memory.', (int) $operations['total'], 'elev8-os'), (int) $operations['total']); }
        if ((int) ($work['completed'] ?? 0) > 0) { $wins[] = sprintf(_n('%d work item moved to completion.', '%d work items moved to completion.', (int) $work['completed'], 'elev8-os'), (int) $work['completed']); }
        $change = is_array($metrics['booked_value_change'] ?? null) ? $metrics['booked_value_change'] : [];
        if (!empty($change['available']) && is_numeric($change['value'] ?? null) && (float) $change['value'] > 0) { $wins[] = sprintf(__('Booked value is up %s versus last month.', 'elev8-os'), number_format_i18n((float) $change['value'], 1) . '%'); }
        return array_slice($wins, 0, 4);
    }

    /** @return array<int,string> */
    private static function risks(array $summary): array {
        $risks = [];
        $team = is_array($summary['team_work'] ?? null) ? $summary['team_work'] : [];
        if (!empty($team['available']) && (int) ($team['overdue'] ?? 0) > 0) { $risks[] = sprintf(_n('%d team work item is overdue.', '%d team work items are overdue.', (int) $team['overdue'], 'elev8-os'), (int) $team['overdue']); }
        $reservations = is_array($summary['reservations'] ?? null) ? $summary['reservations'] : [];
        if (!empty($reservations['available']) && (int) ($reservations['attention'] ?? 0) > 0) { $risks[] = sprintf(_n('%d reservation needs follow-up.', '%d reservations need follow-up.', (int) $reservations['attention'], 'elev8-os'), (int) $reservations['attention']); }
        $applications = is_array($summary['applications'] ?? null) ? $summary['applications'] : [];
        if (!empty($applications['available']) && (int) ($applications['awaiting_agreement'] ?? 0) > 0) { $risks[] = sprintf(_n('%d event application is waiting on an agreement.', '%d event applications are waiting on agreements.', (int) $applications['awaiting_agreement'], 'elev8-os'), (int) $applications['awaiting_agreement']); }
        if (!$risks) { $risks[] = __('No verified operational risks are currently identified.', 'elev8-os'); }
        return array_slice($risks, 0, 4);
    }

    /** @return array<int,array<string,string>> */
    private static function opportunities(array $summary, array $metrics): array {
        $items = [];
        $applications = is_array($summary['applications'] ?? null) ? $summary['applications'] : [];
        if (!empty($applications['available']) && (int) ($applications['attention'] ?? 0) > 0) { $items[] = ['title' => __('Move event applications forward', 'elev8-os'), 'url' => class_exists('Elev8_OS_Event_Applications_Module') ? Elev8_OS_Event_Applications_Module::admin_url() : '']; }
        $change = is_array($metrics['booked_value_change'] ?? null) ? $metrics['booked_value_change'] : [];
        if (!empty($change['available']) && is_numeric($change['value'] ?? null) && (float) $change['value'] > 0) { $items[] = ['title' => __('Review what is driving booking growth', 'elev8-os'), 'url' => admin_url('admin.php?page=elev8-business-intelligence')]; }
        if (!$items) { $items[] = ['title' => __('Review captured growth opportunities', 'elev8-os'), 'url' => admin_url('admin.php?page=elev8-ceo-dashboard&view=opportunities')]; }
        return array_slice($items, 0, 3);
    }

    /** @return array<int,string> */
    private static function focus(array $attention): array {
        $items = [];
        foreach ((array) ($attention['items'] ?? []) as $item) {
            if (!is_array($item)) { continue; }
            $items[] = (string) ($item['title'] ?? __('Review attention item', 'elev8-os'));
            if (count($items) >= 3) { break; }
        }
        if (!$items) { $items[] = __('Protect today’s momentum and review the next best opportunity.', 'elev8-os'); }
        return $items;
    }

    /** @param array<int,WP_Post> $activities @return array<int,array<string,string>> */
    private static function timeline(array $activities): array {
        $timeline = [];
        foreach ($activities as $activity) {
            $timeline[] = [
                'time' => wp_date(get_option('time_format'), strtotime($activity->post_date)),
                'title' => get_the_title($activity),
                'detail' => wp_trim_words(wp_strip_all_tags((string) $activity->post_content), 16),
            ];
        }
        return array_slice($timeline, 0, 8);
    }

    /** @return array<string,string|int> */
    private static function confidence(array $activities, array $operations, array $summary): array {
        $sources = 0;
        if (class_exists('Elev8_OS_Activity_Service')) { $sources++; }
        if (!empty($operations['available'])) { $sources++; }
        if (!empty($summary['attention']['available'])) { $sources++; }
        if (!empty($summary['team_work']['available'])) { $sources++; }
        $level = $sources >= 4 ? 'high' : ($sources >= 2 ? 'medium' : 'low');
        return ['level' => $level, 'sources' => $sources, 'label' => ucfirst($level)];
    }

    /** @return array<string,array<string,string>> */
    private static function explanations(array $range, array $activities, array $operations, array $attention): array {
        return [
            'yesterday' => ['title' => __('Why this yesterday summary?', 'elev8-os'), 'body' => sprintf(__('This section uses verified records created between %1$s and %2$s in the site timezone. It found %3$d immutable activities and %4$d operating logs.', 'elev8-os'), $range['start'], $range['end'], count($activities), (int) ($operations['total'] ?? 0))],
            'pulse' => ['title' => __('Why this Business Pulse?', 'elev8-os'), 'body' => sprintf(__('The pulse is based on the shared Attention Service: %1$d total, %2$d high-priority, and %3$d critical items. It does not infer unconnected data.', 'elev8-os'), (int) ($attention['total'] ?? 0), (int) ($attention['high'] ?? 0), (int) ($attention['critical'] ?? 0))],
            'confidence' => ['title' => __('Why this confidence?', 'elev8-os'), 'body' => __('Confidence reflects how many trusted Elev8 OS sources were available to build the brief. Missing integrations lower confidence and remain marked Unavailable.', 'elev8-os')],
        ];
    }

    private static function greeting(WP_User $user): string {
        $hour = (int) current_time('G');
        $part = $hour < 12 ? __('morning', 'elev8-os') : ($hour < 18 ? __('afternoon', 'elev8-os') : __('evening', 'elev8-os'));
        $name = trim((string) $user->display_name);
        return sprintf(__('Good %1$s, %2$s.', 'elev8-os'), $part, $name !== '' ? $name : __('Steve', 'elev8-os'));
    }
}

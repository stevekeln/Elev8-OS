<?php
/**
 * Verified data provider for the Manager Operational Home.
 *
 * @package Elev8OS
 */

if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Manager_Dashboard_Service {

    /** @return array<string,mixed> */
    public static function snapshot(?WP_User $user = null): array {
        $user = $user ?: wp_get_current_user();
        $summary = class_exists('Elev8_OS_Dashboard_Service')
            ? Elev8_OS_Dashboard_Service::summary($user)
            : [];

        $my_work = is_array($summary['my_work'] ?? null) ? $summary['my_work'] : self::unavailable_counts();
        $team_work = is_array($summary['team_work'] ?? null) ? $summary['team_work'] : self::unavailable_counts();
        $attention = is_array($summary['attention'] ?? null) ? $summary['attention'] : ['available'=>false,'total'=>0,'items'=>[]];

        return [
            'generated_at' => current_time('mysql'),
            'attention' => $attention,
            'my_work' => $my_work,
            'team_work' => $team_work,
            'reservations' => is_array($summary['reservations'] ?? null) ? $summary['reservations'] : ['available'=>false,'attention'=>null,'upcoming'=>null],
            'applications' => is_array($summary['applications'] ?? null) ? $summary['applications'] : ['available'=>false,'attention'=>null,'awaiting_agreement'=>null],
            'operations' => self::operations_snapshot($user),
            'team' => self::team_snapshot(),
            'pulse' => self::pulse($attention, $team_work),
            'wins' => self::wins($attention, $my_work, $team_work),
        ];
    }

    /** @return array<string,mixed> */
    private static function operations_snapshot(WP_User $user): array {
        if (!class_exists('Elev8_OS_Daily_Operations_Service')) {
            return ['available'=>false,'submitted_today'=>null,'team_logs_today'=>null,'needs_review'=>null,'latest'=>[]];
        }

        $today = current_time('Y-m-d');
        $base = [
            'post_type' => Elev8_OS_Daily_Operations_Service::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'date_query' => [['after'=>$today . ' 00:00:00','before'=>$today . ' 23:59:59','inclusive'=>true]],
        ];
        $count = static function(array $args) use ($base): int {
            $query = new WP_Query(array_merge($base, $args));
            return (int) $query->found_posts;
        };

        $submitted_today = $count([
            'author' => (int) $user->ID,
            'meta_query' => [['key'=>Elev8_OS_Daily_Operations_Service::META_TEMPLATE,'value'=>'manager']],
        ]);
        $team_logs_today = $count([]);

        $review_query = new WP_Query([
            'post_type'=>Elev8_OS_Daily_Operations_Service::POST_TYPE,
            'post_status'=>'publish',
            'posts_per_page'=>1,
            'fields'=>'ids',
            'meta_query'=>[
                ['key'=>Elev8_OS_Daily_Operations_Service::META_STATUS,'value'=>['reviewed','completed'],'compare'=>'NOT IN'],
            ],
        ]);

        $latest_query = new WP_Query([
            'post_type'=>Elev8_OS_Daily_Operations_Service::POST_TYPE,
            'post_status'=>'publish',
            'posts_per_page'=>4,
            'orderby'=>'date',
            'order'=>'DESC',
        ]);
        $latest = [];
        foreach ($latest_query->posts as $post) {
            $entry = Elev8_OS_Daily_Operations_Service::entry((int) $post->ID);
            if (!is_array($entry)) { continue; }
            $latest[] = [
                'id'=>(int)$post->ID,
                'title'=>(string)$post->post_title,
                'author'=>(string)get_the_author_meta('display_name', (int)$post->post_author),
                'status'=>(string)($entry['status'] ?? 'new'),
                'location'=>(string)($entry['location'] ?? ''),
                'created_at'=>(string)$post->post_date,
                'url'=>add_query_arg(['page'=>'elev8-daily-operations','view'=>'entry','entry_id'=>(int)$post->ID], admin_url('admin.php')),
            ];
        }

        return [
            'available'=>true,
            'submitted_today'=>$submitted_today,
            'team_logs_today'=>$team_logs_today,
            'needs_review'=>(int)$review_query->found_posts,
            'latest'=>$latest,
        ];
    }

    /** @return array<string,mixed> */
    private static function team_snapshot(): array {
        if (!class_exists('Elev8_OS_Access_Service')) {
            return ['available'=>false,'assignable'=>null,'management'=>null,'event_staff'=>null,'artists'=>null,'shop_employees'=>null];
        }
        $groups = Elev8_OS_Access_Service::assignment_users_grouped();
        $count = static fn(string $key): int => isset($groups[$key]) && is_array($groups[$key]) ? count($groups[$key]) : 0;
        return [
            'available'=>true,
            'assignable'=>array_sum(array_map('count', $groups)),
            'management'=>$count('Management'),
            'event_staff'=>$count('Event Staff'),
            'artists'=>$count('Artists') + $count('Teachers'),
            'shop_employees'=>$count('Shop Employees'),
        ];
    }

    /** @param array<string,mixed> $attention @param array<string,mixed> $team_work @return array<string,string|int> */
    private static function pulse(array $attention, array $team_work): array {
        $critical = (int)($attention['critical'] ?? 0);
        $high = (int)($attention['high'] ?? 0);
        $overdue = (int)($team_work['overdue'] ?? 0);
        if ($critical > 0 || $overdue > 0) {
            return ['status'=>'action_required','label'=>__('Action Required','elev8-os'),'message'=>__('Overdue or critical operational items need action.','elev8-os')];
        }
        if ($high > 0 || (int)($attention['total'] ?? 0) > 0) {
            return ['status'=>'needs_attention','label'=>__('Needs Attention','elev8-os'),'message'=>__('The operation is moving, with follow-ups waiting.','elev8-os')];
        }
        return ['status'=>'healthy','label'=>__('Healthy','elev8-os'),'message'=>__('No verified urgent operating issues are waiting.','elev8-os')];
    }

    /** @param array<string,mixed> $attention @param array<string,mixed> $my_work @param array<string,mixed> $team_work @return array<int,string> */
    private static function wins(array $attention, array $my_work, array $team_work): array {
        $wins = [];
        if ((int)($attention['critical'] ?? 0) === 0) { $wins[] = __('No critical attention items are open.','elev8-os'); }
        if ((int)($my_work['overdue'] ?? 0) === 0) { $wins[] = __('Your assigned work has no overdue items.','elev8-os'); }
        if (!empty($team_work['available']) && (int)($team_work['unassigned'] ?? 0) === 0) { $wins[] = __('No verified team work is waiting without an owner.','elev8-os'); }
        return array_slice($wins, 0, 3);
    }

    /** @return array<string,int|bool> */
    private static function unavailable_counts(): array {
        return ['available'=>false,'active'=>0,'overdue'=>0,'due_today'=>0,'unassigned'=>0,'waiting'=>0];
    }
}

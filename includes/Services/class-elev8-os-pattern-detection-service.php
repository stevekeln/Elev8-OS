<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Detects repeated, human-governed intelligence across confirmed Observations.
 *
 * Patterns summarize repetition. They never rewrite source records and they do
 * not create Work Items automatically.
 */
final class Elev8_OS_Pattern_Detection_Service {
    public const POST_TYPE = 'elev8_pattern';
    public const META_FINGERPRINT = '_elev8_pattern_fingerprint';
    public const META_CLASSIFICATION = '_elev8_pattern_classification';
    public const META_GROUP_TYPE = '_elev8_pattern_group_type';
    public const META_GROUP_KEY = '_elev8_pattern_group_key';
    public const META_ORGANIZATION = '_elev8_pattern_organization_unit_id';
    public const META_OBSERVATION_IDS = '_elev8_pattern_observation_ids';
    public const META_OCCURRENCE_COUNT = '_elev8_pattern_occurrence_count';
    public const META_FIRST_SEEN = '_elev8_pattern_first_seen';
    public const META_LAST_SEEN = '_elev8_pattern_last_seen';
    public const META_SEVERITY = '_elev8_pattern_severity';
    public const META_CONFIDENCE = '_elev8_pattern_confidence';
    public const META_TREND = '_elev8_pattern_trend';
    public const META_STATUS = '_elev8_pattern_status';
    public const META_REVIEWED_BY = '_elev8_pattern_reviewed_by_user_id';
    public const META_REVIEWED_AT = '_elev8_pattern_reviewed_at';
    public const META_REVIEW_NOTES = '_elev8_pattern_review_notes';
    private const CRON_HOOK = 'elev8_os_detect_intelligence_patterns';
    private const SINGLE_HOOK = 'elev8_os_detect_intelligence_patterns_once';

    public static function init(): void {
        add_action('init', [__CLASS__, 'register_post_type']);
        add_action(self::CRON_HOOK, [__CLASS__, 'scan']);
        add_action(self::SINGLE_HOOK, [__CLASS__, 'scan']);
        add_action('elev8_os_observation_reviewed', [__CLASS__, 'schedule_scan'], 20);
        add_action('elev8_os_observation_saved', [__CLASS__, 'schedule_scan'], 20);
        add_action('init', [__CLASS__, 'ensure_schedule']);
        add_filter('elev8_os_business_graph_objects', [__CLASS__, 'register_graph_objects']);
        add_filter('elev8_os_business_graph_relationships', [__CLASS__, 'register_graph_relationships']);
    }

    public static function register_post_type(): void {
        register_post_type(self::POST_TYPE, [
            'labels' => ['name' => __('Patterns', 'elev8-os'), 'singular_name' => __('Pattern', 'elev8-os')],
            'public' => false,
            'show_ui' => false,
            'show_in_rest' => false,
            'supports' => ['title', 'editor'],
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ]);
    }

    public static function ensure_schedule(): void {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK);
        }
    }

    public static function schedule_scan(): void {
        if (!wp_next_scheduled(self::SINGLE_HOOK)) {
            wp_schedule_single_event(time() + 60, self::SINGLE_HOOK);
        }
    }

    /** @return array<string,int> */
    public static function scan(int $window_days = 90, int $minimum_occurrences = 2): array {
        if (!class_exists('Elev8_OS_Observation_Service')) { return ['observations'=>0,'patterns'=>0]; }
        $window_days = max(7, min(365, $window_days));
        $minimum_occurrences = max(2, min(20, $minimum_occurrences));
        $observations = array_merge(
            Elev8_OS_Observation_Service::query(['review_status'=>'confirmed','posts_per_page'=>500,'date_from'=>gmdate('Y-m-d', time() - DAY_IN_SECONDS * $window_days)]),
            Elev8_OS_Observation_Service::query(['review_status'=>'corrected','posts_per_page'=>500,'date_from'=>gmdate('Y-m-d', time() - DAY_IN_SECONDS * $window_days)])
        );
        $groups = [];
        foreach ($observations as $observation) {
            foreach (self::candidates($observation) as $candidate) {
                $fingerprint = $candidate['fingerprint'];
                if (!isset($groups[$fingerprint])) {
                    $groups[$fingerprint] = $candidate + ['observations'=>[]];
                }
                $groups[$fingerprint]['observations'][(int)$observation['id']] = $observation;
            }
        }

        $active_fingerprints = [];
        $created = 0;
        foreach ($groups as $fingerprint => $group) {
            if (count($group['observations']) < $minimum_occurrences) { continue; }
            self::upsert_pattern($group);
            $active_fingerprints[] = $fingerprint;
            $created++;
        }
        self::resolve_missing($active_fingerprints);
        return ['observations'=>count($observations),'patterns'=>$created];
    }

    /** @return array<int,array<string,mixed>> */
    private static function candidates(array $observation): array {
        $result = [];
        $organization = absint($observation['organization_unit_id'] ?? 0);
        $classes = array_values(array_intersect((array)($observation['classifications'] ?? []), ['risk','opportunity','achievement','follow_up']));
        if (!$classes) { return []; }

        foreach ($classes as $classification) {
            foreach ((array)($observation['related_objects'] ?? []) as $related) {
                $type = sanitize_key((string)($related['type'] ?? ''));
                $id = absint($related['id'] ?? 0);
                if ($type === '' || $id < 1 || in_array($type, ['manager_log','communication'], true)) { continue; }
                $result[] = self::candidate($classification, 'related_object', $type.':'.$id, $organization, $type.' #'.$id);
            }
            foreach ((array)($observation['tags'] ?? []) as $tag) {
                $tag = sanitize_key((string)$tag);
                if ($tag === '' || in_array($tag, ['daily-operations','information','risk','opportunity','achievement','follow_up','operational_action'], true)) { continue; }
                $result[] = self::candidate($classification, 'tag', $tag, $organization, str_replace('-', ' ', $tag));
            }
            $source_type = sanitize_key((string)($observation['source_type'] ?? ''));
            if ($source_type !== '') {
                $result[] = self::candidate($classification, 'source_type', $source_type, $organization, str_replace('_', ' ', $source_type));
            }
        }
        $unique = [];
        foreach ($result as $candidate) { $unique[$candidate['fingerprint']] = $candidate; }
        return array_values($unique);
    }

    /** @return array<string,mixed> */
    private static function candidate(string $classification, string $group_type, string $group_key, int $organization, string $label): array {
        $fingerprint = hash('sha256', implode('|', [$organization, $classification, $group_type, $group_key]));
        return compact('fingerprint','classification','group_type','group_key','organization','label');
    }

    /** @param array<string,mixed> $group */
    private static function upsert_pattern(array $group): int {
        $observations = array_values($group['observations']);
        usort($observations, static function(array $a, array $b): int { return strcmp((string)$a['occurred_at'], (string)$b['occurred_at']); });
        $ids = array_map(static fn(array $item): int => (int)$item['id'], $observations);
        $count = count($ids);
        $first = (string)($observations[0]['occurred_at'] ?? '');
        $last = (string)($observations[$count - 1]['occurred_at'] ?? '');
        $severity = self::highest_severity($observations);
        $trend = self::trend($observations);
        $confidence = min(100, 60 + ($count * 8));
        $classification = sanitize_key((string)$group['classification']);
        $label = sanitize_text_field((string)$group['label']);
        $title = sprintf(__('%1$s pattern: %2$s', 'elev8-os'), ucfirst(str_replace('_',' ', $classification)), $label);
        $summary = sprintf(
            _n('%1$d confirmed observation involving %2$s was detected between %3$s and %4$s.', '%1$d confirmed observations involving %2$s were detected between %3$s and %4$s.', $count, 'elev8-os'),
            $count,
            $label,
            self::display_date($first),
            self::display_date($last)
        );
        $existing = self::find((string)$group['fingerprint']);
        $post = ['post_type'=>self::POST_TYPE,'post_status'=>'publish','post_title'=>$title,'post_content'=>$summary];
        if ($existing) { $post['ID'] = $existing; }
        $id = wp_insert_post($post, true);
        if (is_wp_error($id)) { return 0; }
        $id = (int)$id;
        update_post_meta($id, self::META_FINGERPRINT, (string)$group['fingerprint']);
        update_post_meta($id, self::META_CLASSIFICATION, $classification);
        update_post_meta($id, self::META_GROUP_TYPE, sanitize_key((string)$group['group_type']));
        update_post_meta($id, self::META_GROUP_KEY, sanitize_text_field((string)$group['group_key']));
        update_post_meta($id, self::META_ORGANIZATION, absint($group['organization']));
        update_post_meta($id, self::META_OBSERVATION_IDS, $ids);
        update_post_meta($id, self::META_OCCURRENCE_COUNT, $count);
        update_post_meta($id, self::META_FIRST_SEEN, $first);
        update_post_meta($id, self::META_LAST_SEEN, $last);
        update_post_meta($id, self::META_SEVERITY, $severity);
        update_post_meta($id, self::META_CONFIDENCE, $confidence);
        update_post_meta($id, self::META_TREND, $trend);
        $status = (string)get_post_meta($id, self::META_STATUS, true);
        if ($status === '' || $status === 'resolved') { update_post_meta($id, self::META_STATUS, 'active'); }
        do_action('elev8_os_pattern_detected', $id, self::get($id));
        return $id;
    }

    private static function resolve_missing(array $active_fingerprints): void {
        $ids = get_posts(['post_type'=>self::POST_TYPE,'post_status'=>'publish','posts_per_page'=>-1,'fields'=>'ids','meta_query'=>[['key'=>self::META_STATUS,'value'=>'active']]]);
        foreach ($ids as $id) {
            $fingerprint = (string)get_post_meta((int)$id, self::META_FINGERPRINT, true);
            if (!in_array($fingerprint, $active_fingerprints, true)) { update_post_meta((int)$id, self::META_STATUS, 'resolved'); }
        }
    }

    public static function find(string $fingerprint): int {
        $ids = get_posts(['post_type'=>self::POST_TYPE,'post_status'=>'publish','posts_per_page'=>1,'fields'=>'ids','meta_query'=>[['key'=>self::META_FINGERPRINT,'value'=>$fingerprint]]]);
        return $ids ? (int)$ids[0] : 0;
    }

    /** @return array<string,mixed> */
    public static function get(int $id): array {
        $post = get_post($id);
        if (!$post instanceof WP_Post || $post->post_type !== self::POST_TYPE) { return []; }
        return [
            'id'=>$id,'title'=>get_the_title($id),'summary'=>(string)$post->post_content,
            'classification'=>(string)get_post_meta($id,self::META_CLASSIFICATION,true),
            'group_type'=>(string)get_post_meta($id,self::META_GROUP_TYPE,true),
            'group_key'=>(string)get_post_meta($id,self::META_GROUP_KEY,true),
            'organization_unit_id'=>absint(get_post_meta($id,self::META_ORGANIZATION,true)),
            'observation_ids'=>(array)get_post_meta($id,self::META_OBSERVATION_IDS,true),
            'occurrence_count'=>absint(get_post_meta($id,self::META_OCCURRENCE_COUNT,true)),
            'first_seen'=>(string)get_post_meta($id,self::META_FIRST_SEEN,true),
            'last_seen'=>(string)get_post_meta($id,self::META_LAST_SEEN,true),
            'severity'=>(string)get_post_meta($id,self::META_SEVERITY,true) ?: 'normal',
            'confidence'=>absint(get_post_meta($id,self::META_CONFIDENCE,true)),
            'trend'=>(string)get_post_meta($id,self::META_TREND,true) ?: 'stable',
            'status'=>(string)get_post_meta($id,self::META_STATUS,true) ?: 'active',
            'reviewed_by_user_id'=>absint(get_post_meta($id,self::META_REVIEWED_BY,true)),
            'reviewed_at'=>(string)get_post_meta($id,self::META_REVIEWED_AT,true),
            'review_notes'=>(string)get_post_meta($id,self::META_REVIEW_NOTES,true),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public static function query(array $filters = []): array {
        $filters = wp_parse_args($filters, ['classification'=>'','severity'=>'','status'=>'active','organization_unit_id'=>0,'posts_per_page'=>100]);
        $meta = ['relation'=>'AND'];
        foreach (['classification'=>self::META_CLASSIFICATION,'severity'=>self::META_SEVERITY,'status'=>self::META_STATUS] as $key=>$meta_key) {
            if ($filters[$key] !== '') { $meta[] = ['key'=>$meta_key,'value'=>sanitize_key((string)$filters[$key])]; }
        }
        if ($filters['organization_unit_id']) { $meta[] = ['key'=>self::META_ORGANIZATION,'value'=>absint($filters['organization_unit_id']),'type'=>'NUMERIC']; }
        $posts = get_posts(['post_type'=>self::POST_TYPE,'post_status'=>'publish','posts_per_page'=>max(1,min(500,(int)$filters['posts_per_page'])),'orderby'=>'meta_value_num','meta_key'=>self::META_OCCURRENCE_COUNT,'order'=>'DESC','meta_query'=>$meta]);
        return array_values(array_filter(array_map(static fn(WP_Post $post): array => self::get((int)$post->ID), $posts)));
    }

    public static function review(int $id, string $status, int $user_id, string $notes = '') {
        if (get_post_type($id) !== self::POST_TYPE) { return new WP_Error('invalid_pattern', __('The pattern could not be found.', 'elev8-os')); }
        $status = sanitize_key($status);
        if (!in_array($status, ['active','acknowledged','dismissed','resolved'], true)) { return new WP_Error('invalid_pattern_status', __('The pattern status is invalid.', 'elev8-os')); }
        update_post_meta($id, self::META_STATUS, $status);
        update_post_meta($id, self::META_REVIEWED_BY, absint($user_id));
        update_post_meta($id, self::META_REVIEWED_AT, current_time('mysql'));
        update_post_meta($id, self::META_REVIEW_NOTES, sanitize_textarea_field($notes));
        do_action('elev8_os_pattern_reviewed', $id, $status, $user_id);
        return true;
    }

    private static function highest_severity(array $observations): string {
        $rank = ['low'=>1,'normal'=>2,'high'=>3,'critical'=>4]; $winner='low';
        foreach ($observations as $item) { $value=(string)($item['severity']??'normal'); if (($rank[$value]??2)>($rank[$winner]??1)) { $winner=$value; } }
        return $winner;
    }

    private static function trend(array $observations): string {
        if (count($observations) < 3) { return 'stable'; }
        $mid = time() - (15 * DAY_IN_SECONDS); $recent=0; $older=0;
        foreach ($observations as $item) { $ts=strtotime((string)($item['occurred_at']??'')); if ($ts && $ts >= $mid) { $recent++; } else { $older++; } }
        if ($recent > $older) { return 'increasing'; }
        if ($recent < $older) { return 'decreasing'; }
        return 'stable';
    }

    private static function display_date(string $date): string {
        $ts = strtotime($date); return $ts ? wp_date(get_option('date_format'), $ts) : __('unknown date', 'elev8-os');
    }

    public static function register_graph_objects(array $objects): array {
        $objects['pattern'] = ['label'=>__('Pattern','elev8-os'),'engine'=>'Intelligence','authoritative_system'=>'elev8_os','source_type'=>self::POST_TYPE,'organization_scoped'=>true,'notes'=>__('Human-governed summary of repeated confirmed Observations.','elev8-os')];
        return $objects;
    }

    public static function register_graph_relationships(array $relationships): array {
        $relationships['pattern_supported_by'] = ['label'=>__('Supported by','elev8-os'),'from'=>['pattern'],'to'=>['observation'],'directional'=>true,'notes'=>__('Connects a detected pattern to the confirmed Observations that support it.','elev8-os')];
        return $relationships;
    }
}

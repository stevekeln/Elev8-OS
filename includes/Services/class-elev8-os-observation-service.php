<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Canonical Intelligence Engine fact record.
 *
 * Observations preserve verified facts and source relationships. They are not
 * tasks. Operational action is contributed separately only when a source
 * explicitly declares that follow-up is required.
 */
final class Elev8_OS_Observation_Service {
    public const POST_TYPE = 'elev8_observation';
    public const META_SOURCE_TYPE = '_elev8_observation_source_type';
    public const META_SOURCE_ID = '_elev8_observation_source_id';
    public const META_SOURCE_KEY = '_elev8_observation_source_key';
    public const META_CLASSIFICATIONS = '_elev8_observation_classifications';
    public const META_SEVERITY = '_elev8_observation_severity';
    public const META_CONFIDENCE = '_elev8_observation_confidence';
    public const META_TAGS = '_elev8_observation_tags';
    public const META_ORGANIZATION = '_elev8_observation_organization_unit_id';
    public const META_AUTHOR_USER = '_elev8_observation_author_user_id';
    public const META_OCCURRED_AT = '_elev8_observation_occurred_at';
    public const META_REQUIRES_ACTION = '_elev8_observation_requires_action';
    public const META_WORK_ID = '_elev8_observation_work_id';
    public const META_RELATED = '_elev8_observation_related_objects';
    public const META_REVIEW_STATUS = '_elev8_observation_review_status';
    public const META_REVIEWED_BY = '_elev8_observation_reviewed_by_user_id';
    public const META_REVIEWED_AT = '_elev8_observation_reviewed_at';
    public const META_REVIEW_NOTES = '_elev8_observation_review_notes';

    public static function init(): void {
        add_action('init', [__CLASS__, 'register_post_type']);
        add_filter('elev8_os_business_graph_objects', [__CLASS__, 'register_graph_objects']);
        add_filter('elev8_os_business_graph_relationships', [__CLASS__, 'register_graph_relationships']);
    }

    public static function register_post_type(): void {
        register_post_type(self::POST_TYPE, [
            'labels' => ['name' => __('Observations', 'elev8-os'), 'singular_name' => __('Observation', 'elev8-os')],
            'public' => false,
            'show_ui' => false,
            'show_in_rest' => false,
            'supports' => ['title', 'editor', 'author'],
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ]);
    }

    /** @param array<string,mixed> $args */
    public static function upsert(array $args) {
        $source_type = sanitize_key((string)($args['source_type'] ?? ''));
        $source_id = absint($args['source_id'] ?? 0);
        $source_key = sanitize_key((string)($args['source_key'] ?? 'summary'));
        $summary = sanitize_textarea_field((string)($args['summary'] ?? ''));
        if ($source_type === '' || $source_id < 1 || $summary === '') {
            return new WP_Error('invalid_observation', __('A verified source and summary are required.', 'elev8-os'));
        }

        $existing = self::find($source_type, $source_id, $source_key);
        $title = sanitize_text_field((string)($args['title'] ?? ''));
        if ($title === '') { $title = wp_trim_words($summary, 12, '…'); }
        $author_user_id = absint($args['author_user_id'] ?? get_current_user_id());
        $post = [
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'post_title' => $title,
            'post_content' => $summary,
            'post_author' => $author_user_id,
        ];
        if ($existing) { $post['ID'] = $existing; }
        $observation_id = wp_insert_post($post, true);
        if (is_wp_error($observation_id)) { return $observation_id; }
        $observation_id = (int)$observation_id;

        $classifications = self::clean_classifications((array)($args['classifications'] ?? []));
        $severity = sanitize_key((string)($args['severity'] ?? 'normal'));
        if (!in_array($severity, ['low','normal','high','critical'], true)) { $severity = 'normal'; }
        $confidence = max(0, min(100, absint($args['confidence'] ?? 100)));
        $occurred_at = sanitize_text_field((string)($args['occurred_at'] ?? current_time('mysql')));
        $tags = array_values(array_unique(array_filter(array_map('sanitize_text_field', (array)($args['tags'] ?? [])))));
        $related = self::clean_related((array)($args['related_objects'] ?? []));

        update_post_meta($observation_id, self::META_SOURCE_TYPE, $source_type);
        update_post_meta($observation_id, self::META_SOURCE_ID, $source_id);
        update_post_meta($observation_id, self::META_SOURCE_KEY, $source_key);
        update_post_meta($observation_id, self::META_CLASSIFICATIONS, $classifications);
        update_post_meta($observation_id, self::META_SEVERITY, $severity);
        update_post_meta($observation_id, self::META_CONFIDENCE, $confidence);
        update_post_meta($observation_id, self::META_TAGS, $tags);
        update_post_meta($observation_id, self::META_ORGANIZATION, absint($args['organization_unit_id'] ?? 0));
        update_post_meta($observation_id, self::META_AUTHOR_USER, $author_user_id);
        update_post_meta($observation_id, self::META_OCCURRED_AT, $occurred_at);
        update_post_meta($observation_id, self::META_REQUIRES_ACTION, !empty($args['requires_action']) ? 1 : 0);
        update_post_meta($observation_id, self::META_RELATED, $related);
        if (!get_post_meta($observation_id, self::META_REVIEW_STATUS, true)) {
            update_post_meta($observation_id, self::META_REVIEW_STATUS, 'unreviewed');
        }
        if (!empty($args['work_id'])) { update_post_meta($observation_id, self::META_WORK_ID, absint($args['work_id'])); }

        do_action('elev8_os_observation_saved', $observation_id, $args);
        return $observation_id;
    }

    public static function find(string $source_type, int $source_id, string $source_key = 'summary'): int {
        $ids = get_posts([
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_query' => [
                ['key' => self::META_SOURCE_TYPE, 'value' => sanitize_key($source_type)],
                ['key' => self::META_SOURCE_ID, 'value' => absint($source_id), 'type' => 'NUMERIC'],
                ['key' => self::META_SOURCE_KEY, 'value' => sanitize_key($source_key)],
            ],
        ]);
        return $ids ? (int)$ids[0] : 0;
    }

    /** @return array<string,mixed> */
    public static function get(int $id): array {
        $post = get_post($id);
        if (!$post instanceof WP_Post || $post->post_type !== self::POST_TYPE) { return []; }
        return [
            'id' => $id,
            'title' => get_the_title($id),
            'summary' => (string)$post->post_content,
            'source_type' => (string)get_post_meta($id, self::META_SOURCE_TYPE, true),
            'source_id' => absint(get_post_meta($id, self::META_SOURCE_ID, true)),
            'source_key' => (string)get_post_meta($id, self::META_SOURCE_KEY, true),
            'classifications' => (array)get_post_meta($id, self::META_CLASSIFICATIONS, true),
            'severity' => (string)get_post_meta($id, self::META_SEVERITY, true) ?: 'normal',
            'confidence' => absint(get_post_meta($id, self::META_CONFIDENCE, true)),
            'tags' => (array)get_post_meta($id, self::META_TAGS, true),
            'organization_unit_id' => absint(get_post_meta($id, self::META_ORGANIZATION, true)),
            'author_user_id' => absint(get_post_meta($id, self::META_AUTHOR_USER, true)),
            'occurred_at' => (string)get_post_meta($id, self::META_OCCURRED_AT, true),
            'requires_action' => (bool)get_post_meta($id, self::META_REQUIRES_ACTION, true),
            'work_id' => absint(get_post_meta($id, self::META_WORK_ID, true)),
            'related_objects' => (array)get_post_meta($id, self::META_RELATED, true),
            'review_status' => (string)get_post_meta($id, self::META_REVIEW_STATUS, true) ?: 'unreviewed',
            'reviewed_by_user_id' => absint(get_post_meta($id, self::META_REVIEWED_BY, true)),
            'reviewed_at' => (string)get_post_meta($id, self::META_REVIEWED_AT, true),
            'review_notes' => (string)get_post_meta($id, self::META_REVIEW_NOTES, true),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public static function query(array $filters = []): array {
        $filters = wp_parse_args($filters, ['classification'=>'','severity'=>'','source_type'=>'','review_status'=>'','organization_unit_id'=>0,'requires_action'=>'','date_from'=>'','date_to'=>'','posts_per_page'=>100]);
        $meta = ['relation' => 'AND'];
        if ($filters['classification']) { $meta[] = ['key'=>self::META_CLASSIFICATIONS,'value'=>'"'.sanitize_key((string)$filters['classification']).'"','compare'=>'LIKE']; }
        if ($filters['severity']) { $meta[] = ['key'=>self::META_SEVERITY,'value'=>sanitize_key((string)$filters['severity'])]; }
        if ($filters['source_type']) { $meta[] = ['key'=>self::META_SOURCE_TYPE,'value'=>sanitize_key((string)$filters['source_type'])]; }
        if ($filters['review_status']) { $meta[] = ['key'=>self::META_REVIEW_STATUS,'value'=>sanitize_key((string)$filters['review_status'])]; }
        if ($filters['organization_unit_id']) { $meta[] = ['key'=>self::META_ORGANIZATION,'value'=>absint($filters['organization_unit_id']),'type'=>'NUMERIC']; }
        if ($filters['requires_action'] !== '') { $meta[] = ['key'=>self::META_REQUIRES_ACTION,'value'=>!empty($filters['requires_action']) ? 1 : 0,'type'=>'NUMERIC']; }
        $date_query = [];
        if ($filters['date_from'] || $filters['date_to']) { $date_query[] = ['after'=>$filters['date_from'] ?: null,'before'=>$filters['date_to'] ?: null,'inclusive'=>true]; }
        $posts = get_posts([
            'post_type'=>self::POST_TYPE,'post_status'=>'publish','posts_per_page'=>max(1,min(500,(int)$filters['posts_per_page'])),
            'orderby'=>'date','order'=>'DESC','meta_query'=>$meta,'date_query'=>$date_query,
        ]);
        return array_values(array_filter(array_map(static function($post) { return self::get((int)$post->ID); }, $posts)));
    }

    /** @return array<string,int> */
    public static function summary(string $date_from = '', string $date_to = ''): array {
        $items = self::query(['date_from'=>$date_from,'date_to'=>$date_to,'posts_per_page'=>500]);
        $summary = ['total'=>count($items),'risk'=>0,'opportunity'=>0,'decision'=>0,'achievement'=>0,'follow_up'=>0,'critical'=>0,'high'=>0];
        foreach ($items as $item) {
            foreach ((array)$item['classifications'] as $classification) {
                if (isset($summary[$classification])) { $summary[$classification]++; }
            }
            if (isset($summary[$item['severity']])) { $summary[$item['severity']]++; }
        }
        return $summary;
    }


    public static function review(int $id, string $status, int $user_id, string $notes = '') {
        if (get_post_type($id) !== self::POST_TYPE) {
            return new WP_Error('invalid_observation', __('The observation could not be found.', 'elev8-os'));
        }
        $status = sanitize_key($status);
        if (!in_array($status, ['unreviewed','confirmed','corrected','dismissed'], true)) {
            return new WP_Error('invalid_review_status', __('The review status is invalid.', 'elev8-os'));
        }
        update_post_meta($id, self::META_REVIEW_STATUS, $status);
        update_post_meta($id, self::META_REVIEWED_BY, absint($user_id));
        update_post_meta($id, self::META_REVIEWED_AT, current_time('mysql'));
        update_post_meta($id, self::META_REVIEW_NOTES, sanitize_textarea_field($notes));
        do_action('elev8_os_observation_reviewed', $id, $status, $user_id);
        return true;
    }

    public static function register_graph_objects(array $objects): array {
        $objects['observation'] = [
            'label' => __('Observation', 'elev8-os'),
            'engine' => 'Intelligence',
            'authoritative_system' => 'elev8_os',
            'source_type' => self::POST_TYPE,
            'organization_scoped' => true,
            'notes' => __('Verified fact derived from an authoritative source. Observations are not tasks.', 'elev8-os'),
        ];
        return $objects;
    }

    public static function register_graph_relationships(array $relationships): array {
        $relationships['observed_from'] = ['label'=>__('Observed from','elev8-os'),'from'=>['observation'],'to'=>['*'],'directional'=>true,'notes'=>__('Connects a verified observation to its authoritative source.','elev8-os')];
        $relationships['evidences'] = ['label'=>__('Evidences','elev8-os'),'from'=>['observation'],'to'=>['work','conversation','knowledge'],'directional'=>true,'notes'=>__('Observation provides evidence or context without owning the target record.','elev8-os')];
        return $relationships;
    }

    /** @return array<int,string> */
    private static function clean_classifications(array $items): array {
        $allowed = ['information','risk','opportunity','decision','achievement','follow_up','operational_action'];
        $clean = [];
        foreach ($items as $item) { $key = sanitize_key((string)$item); if (in_array($key, $allowed, true)) { $clean[] = $key; } }
        return $clean ? array_values(array_unique($clean)) : ['information'];
    }

    /** @return array<int,array<string,mixed>> */
    private static function clean_related(array $items): array {
        $clean = [];
        foreach ($items as $item) {
            if (!is_array($item)) { continue; }
            $type = sanitize_key((string)($item['type'] ?? ''));
            $id = absint($item['id'] ?? 0);
            if ($type !== '' && $id > 0) { $clean[] = ['type'=>$type,'id'=>$id]; }
        }
        return $clean;
    }
}

<?php
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Problem_Report_Service {
    public const POST_TYPE = 'elev8_problem';
    private const META = '_elev8_problem_data';

    public static function init(): void {
        add_action('init', [__CLASS__, 'register_post_type']);
    }

    public static function register_post_type(): void {
        register_post_type(self::POST_TYPE, [
            'labels' => ['name' => __('Problem Reports', 'elev8-os'), 'singular_name' => __('Problem Report', 'elev8-os')],
            'public' => false, 'show_ui' => false, 'show_in_rest' => false,
            'supports' => ['title', 'editor', 'author'], 'map_meta_cap' => true,
        ]);
    }

    public static function categories(): array {
        return [
            'something_broken' => __('Something is broken', 'elev8-os'),
            'hard_to_use' => __('Hard or confusing to use', 'elev8-os'),
            'missing_feature' => __('Something important is missing', 'elev8-os'),
            'data_problem' => __('Wrong or missing information', 'elev8-os'),
            'performance' => __('Slow or unreliable', 'elev8-os'),
            'idea' => __('Improvement idea', 'elev8-os'),
            'other' => __('Other', 'elev8-os'),
        ];
    }

    public static function severities(): array {
        return ['low' => __('Low', 'elev8-os'), 'normal' => __('Normal', 'elev8-os'), 'high' => __('High', 'elev8-os'), 'critical' => __('Stops work', 'elev8-os')];
    }

    public static function save(array $raw, array $files, int $user_id) {
        $summary = sanitize_text_field((string) ($raw['summary'] ?? ''));
        $details = sanitize_textarea_field((string) ($raw['details'] ?? ''));
        if ($summary === '' || $details === '') { return new WP_Error('required', __('A short summary and details are required.', 'elev8-os')); }

        $category = sanitize_key((string) ($raw['category'] ?? 'other'));
        if (!isset(self::categories()[$category])) { $category = 'other'; }
        $severity = sanitize_key((string) ($raw['severity'] ?? 'normal'));
        if (!isset(self::severities()[$severity])) { $severity = 'normal'; }
        $area = sanitize_text_field((string) ($raw['area'] ?? ''));
        $expected = sanitize_textarea_field((string) ($raw['expected'] ?? ''));
        $page_url = esc_url_raw((string) ($raw['page_url'] ?? ''));
        $device = sanitize_text_field((string) ($raw['device'] ?? ''));
        $preview_target_user_id = absint($raw['preview_target_user_id'] ?? 0);
        $preview_role = sanitize_key((string) ($raw['preview_role'] ?? ''));
        $fingerprint = self::fingerprint($category, $area, $summary);
        $existing = self::find_open_duplicate($fingerprint);

        if ($existing > 0) {
            $data = self::get_data($existing);
            $data['occurrences'] = max(1, (int) ($data['occurrences'] ?? 1)) + 1;
            $data['last_reported_at'] = current_time('mysql');
            $data['reporters'] = array_values(array_unique(array_merge((array) ($data['reporters'] ?? []), [$user_id])));
            $data['recent_context'][] = ['user_id' => $user_id, 'details' => $details, 'page_url' => $page_url, 'device' => $device, 'preview_target_user_id' => $preview_target_user_id, 'preview_role' => $preview_role, 'reported_at' => current_time('mysql')];
            $data['recent_context'] = array_slice($data['recent_context'], -10);
            if (self::severity_rank($severity) > self::severity_rank((string) ($data['severity'] ?? 'normal'))) { $data['severity'] = $severity; }
            update_post_meta($existing, self::META, $data);
            return ['id' => $existing, 'duplicate' => true, 'occurrences' => $data['occurrences']];
        }

        $post_id = wp_insert_post([
            'post_type' => self::POST_TYPE, 'post_status' => 'publish', 'post_title' => $summary,
            'post_content' => $details, 'post_author' => $user_id,
        ], true);
        if (is_wp_error($post_id)) { return $post_id; }

        $data = [
            'category' => $category, 'severity' => $severity, 'area' => $area, 'summary' => $summary,
            'details' => $details, 'expected' => $expected, 'page_url' => $page_url, 'device' => $device,
            'preview_target_user_id' => $preview_target_user_id, 'preview_role' => $preview_role,
            'fingerprint' => $fingerprint, 'status' => 'new', 'occurrences' => 1, 'reporters' => [$user_id],
            'created_at' => current_time('mysql'), 'last_reported_at' => current_time('mysql'),
            'recent_context' => [], 'attachment_ids' => self::attachments($files, (int) $post_id),
        ];
        update_post_meta((int) $post_id, self::META, $data);
        return ['id' => (int) $post_id, 'duplicate' => false, 'occurrences' => 1];
    }

    public static function reports(array $args = []): array {
        $args = wp_parse_args($args, ['status' => '', 'posts_per_page' => 100]);
        $query = new WP_Query(['post_type' => self::POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => (int) $args['posts_per_page'], 'orderby' => 'modified', 'order' => 'DESC']);
        $items = [];
        foreach ($query->posts as $post) {
            $data = self::get_data((int) $post->ID);
            if ($args['status'] && ($data['status'] ?? 'new') !== $args['status']) { continue; }
            $items[] = ['post' => $post, 'data' => $data];
        }
        usort($items, static function(array $a, array $b): int {
            $score = static function(array $d): int { return self::severity_rank((string) ($d['severity'] ?? 'normal')) * 1000 + (int) ($d['occurrences'] ?? 1); };
            return $score($b['data']) <=> $score($a['data']);
        });
        return $items;
    }

    public static function update_status(int $id, string $status): bool {
        if (!in_array($status, ['new', 'reviewing', 'planned', 'resolved', 'closed'], true)) { return false; }
        $data = self::get_data($id); if (!$data) { return false; }
        $data['status'] = $status; $data['updated_at'] = current_time('mysql');
        return update_post_meta($id, self::META, $data) !== false;
    }

    public static function get_data(int $id): array { return (array) get_post_meta($id, self::META, true); }

    private static function find_open_duplicate(string $fingerprint): int {
        $query = new WP_Query(['post_type' => self::POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => 1, 'meta_query' => [['key' => self::META, 'value' => $fingerprint, 'compare' => 'LIKE']]]);
        foreach ($query->posts as $post) {
            $data = self::get_data((int) $post->ID);
            if (($data['fingerprint'] ?? '') === $fingerprint && !in_array(($data['status'] ?? 'new'), ['resolved', 'closed'], true)) { return (int) $post->ID; }
        }
        return 0;
    }

    private static function fingerprint(string $category, string $area, string $summary): string {
        $normalized = strtolower(remove_accents($category . '|' . $area . '|' . $summary));
        $normalized = preg_replace('/[^a-z0-9]+/', ' ', $normalized);
        $words = array_values(array_filter(explode(' ', trim((string) $normalized)), static fn(string $word): bool => strlen($word) > 2));
        sort($words); return hash('sha256', implode('|', array_unique($words)));
    }

    private static function severity_rank(string $severity): int { return ['low' => 1, 'normal' => 2, 'high' => 3, 'critical' => 4][$severity] ?? 2; }

    private static function attachments(array $files, int $post_id): array {
        if (empty($files['attachment']['name']) || (int) ($files['attachment']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) { return []; }
        require_once ABSPATH . 'wp-admin/includes/file.php'; require_once ABSPATH . 'wp-admin/includes/media.php'; require_once ABSPATH . 'wp-admin/includes/image.php';
        $id = media_handle_upload('attachment', $post_id, [], ['test_form' => false]);
        return is_wp_error($id) ? [] : [(int) $id];
    }
}

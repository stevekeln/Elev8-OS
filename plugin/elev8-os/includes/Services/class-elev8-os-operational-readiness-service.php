<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Configurable Experience Standards and contextual readiness execution.
 *
 * Standards describe what the promised experience requires. Work Items remain
 * authoritative for operational ownership and status; this service stores the
 * reusable card definitions and attributed execution evidence only.
 */
final class Elev8_OS_Operational_Readiness_Service {
    public const POST_TYPE = 'elev8_ready_std';
    public const META_CARDS = '_elev8_readiness_cards';
    public const META_CONTEXT = '_elev8_readiness_context';
    public const META_ORG = '_elev8_readiness_org';
    public const META_ROLE = '_elev8_readiness_role';
    public const META_WORK_TYPE = '_elev8_readiness_work_type';
    public const META_ACTIVE = '_elev8_readiness_active';
    public const WORK_STANDARD_META = '_elev8_readiness_standard_id';
    public const WORK_STATE_META = '_elev8_readiness_execution';

    public static function init(): void {
        add_action('init', [__CLASS__, 'register_post_type'], 18);
        add_filter('elev8_os_business_graph_objects', [__CLASS__, 'register_graph_objects']);
    }

    public static function register_post_type(): void {
        register_post_type(self::POST_TYPE, [
            'labels' => ['name' => __('Experience Standards', 'elev8-os'), 'singular_name' => __('Experience Standard', 'elev8-os')],
            'public' => false,
            'show_ui' => false,
            'show_in_menu' => false,
            'supports' => ['title', 'editor'],
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ]);
    }

    public static function can_manage(?WP_User $user = null): bool {
        $user = $user ?: wp_get_current_user();
        return $user instanceof WP_User && (
            user_can($user, 'manage_options') ||
            Elev8_OS_Access_Service::user_can('manage_operations', $user) ||
            Elev8_OS_Access_Service::user_can('manage_work', $user)
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function standards(bool $active_only = false): array {
        $query = [
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => 250,
            'orderby' => 'title',
            'order' => 'ASC',
        ];
        if ($active_only) {
            $query['meta_query'] = [[
                'key' => self::META_ACTIVE,
                'value' => '1',
                'compare' => '=',
            ]];
        }
        $posts = get_posts($query);
        $out = [];
        foreach ($posts as $post) {
            if ($post instanceof WP_Post) { $out[] = self::standard((int) $post->ID); }
        }
        return array_values(array_filter($out));
    }

    /** @return array<string,mixed>|null */
    public static function standard(int $standard_id): ?array {
        $post = get_post($standard_id);
        if (!$post instanceof WP_Post || $post->post_type !== self::POST_TYPE) { return null; }
        $cards = self::normalize_cards((array) get_post_meta($standard_id, self::META_CARDS, true));
        return [
            'id' => (int) $post->ID,
            'title' => (string) $post->post_title,
            'description' => (string) $post->post_content,
            'context_type' => (string) (get_post_meta($standard_id, self::META_CONTEXT, true) ?: 'work_item'),
            'organization_unit_id' => absint(get_post_meta($standard_id, self::META_ORG, true)),
            'role_key' => sanitize_key((string) get_post_meta($standard_id, self::META_ROLE, true)),
            'work_type' => sanitize_key((string) get_post_meta($standard_id, self::META_WORK_TYPE, true)),
            'active' => get_post_meta($standard_id, self::META_ACTIVE, true) !== '0',
            'cards' => $cards,
        ];
    }

    /** @return int|WP_Error */
    public static function save_standard(array $input, int $standard_id = 0) {
        if (!self::can_manage()) { return new WP_Error('forbidden', __('You cannot manage Experience Standards.', 'elev8-os')); }
        $title = sanitize_text_field((string) ($input['title'] ?? ''));
        if ($title === '') { return new WP_Error('missing_title', __('A standard name is required.', 'elev8-os')); }
        $postarr = [
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'post_title' => $title,
            'post_content' => wp_kses_post((string) ($input['description'] ?? '')),
        ];
        if ($standard_id > 0) { $postarr['ID'] = $standard_id; }
        $result = wp_insert_post($postarr, true);
        if (is_wp_error($result)) { return $result; }
        $standard_id = (int) $result;
        update_post_meta($standard_id, self::META_CONTEXT, sanitize_key((string) ($input['context_type'] ?? 'work_item')) ?: 'work_item');
        update_post_meta($standard_id, self::META_ORG, absint($input['organization_unit_id'] ?? 0));
        update_post_meta($standard_id, self::META_ROLE, sanitize_key((string) ($input['role_key'] ?? '')));
        update_post_meta($standard_id, self::META_WORK_TYPE, sanitize_key((string) ($input['work_type'] ?? '')));
        update_post_meta($standard_id, self::META_ACTIVE, empty($input['active']) ? '0' : '1');
        update_post_meta($standard_id, self::META_CARDS, self::sanitize_cards((array) ($input['cards'] ?? [])));
        return $standard_id;
    }

    public static function delete_standard(int $standard_id) {
        if (!self::can_manage()) { return new WP_Error('forbidden', __('You cannot remove Experience Standards.', 'elev8-os')); }
        $post = get_post($standard_id);
        if (!$post instanceof WP_Post || $post->post_type !== self::POST_TYPE) { return new WP_Error('not_found', __('Experience Standard not found.', 'elev8-os')); }
        return wp_trash_post($standard_id) ? true : new WP_Error('delete_failed', __('The Experience Standard could not be removed.', 'elev8-os'));
    }

    /** @return array<int,array<string,mixed>> */
    private static function sanitize_cards(array $rows): array {
        $cards = [];
        foreach ($rows as $row) {
            if (!is_array($row)) { continue; }
            $title = sanitize_text_field((string) ($row['title'] ?? ''));
            if ($title === '') { continue; }
            $id = sanitize_key((string) ($row['id'] ?? ''));
            if ($id === '') { $id = 'card_' . wp_generate_password(10, false, false); }
            $verification = sanitize_key((string) ($row['verification'] ?? 'checkbox'));
            if (!in_array($verification, ['checkbox', 'note', 'photo_reference', 'manager_approval'], true)) { $verification = 'checkbox'; }
            $timing = sanitize_key((string) ($row['timing'] ?? 'before_start'));
            if (!in_array($timing, ['before_start', 'during', 'before_complete', 'anytime'], true)) { $timing = 'before_start'; }
            $cards[] = [
                'id' => $id,
                'title' => $title,
                'instructions' => sanitize_textarea_field((string) ($row['instructions'] ?? '')),
                'required' => !empty($row['required']),
                'active' => !empty($row['active']),
                'verification' => $verification,
                'timing' => $timing,
                'due_offset_minutes' => max(0, absint($row['due_offset_minutes'] ?? 0)),
                'sop_url' => esc_url_raw((string) ($row['sop_url'] ?? '')),
                'sort_order' => intval($row['sort_order'] ?? count($cards) * 10),
            ];
        }
        usort($cards, static function(array $a, array $b): int { return ((int) $a['sort_order']) <=> ((int) $b['sort_order']); });
        return array_values($cards);
    }

    /** @return array<int,array<string,mixed>> */
    private static function normalize_cards(array $cards): array {
        return self::sanitize_cards($cards);
    }

    /** @return array<int,array<string,mixed>> */
    public static function applicable_standards_for_work(int $work_id): array {
        $work = Elev8_OS_Operations_Engine_Service::work_item($work_id);
        if (!$work) { return []; }
        $assigned = absint(get_post_meta($work_id, self::WORK_STANDARD_META, true));
        if ($assigned) {
            $standard = self::standard($assigned);
            return $standard && !empty($standard['active']) ? [$standard] : [];
        }
        $out = [];
        foreach (self::standards(true) as $standard) {
            if (($standard['context_type'] ?? '') !== 'work_item') { continue; }
            if (!empty($standard['organization_unit_id']) && (int) $standard['organization_unit_id'] !== (int) ($work['organization_unit_id'] ?? 0)) { continue; }
            if (!empty($standard['work_type']) && (string) $standard['work_type'] !== (string) ($work['type'] ?? '')) { continue; }
            if (!empty($standard['role_key'])) {
                $owner = get_user_by('id', (int) ($work['owner_user_id'] ?? 0));
                if (!$owner instanceof WP_User || !in_array((string) $standard['role_key'], (array) $owner->roles, true)) { continue; }
            }
            $out[] = $standard;
        }
        return $out;
    }

    public static function assign_standard(int $work_id, int $standard_id) {
        if (!self::can_manage()) { return new WP_Error('forbidden', __('You cannot assign Experience Standards.', 'elev8-os')); }
        if (get_post_type($work_id) !== Elev8_OS_Work_Service::POST_TYPE) { return new WP_Error('invalid_work', __('Invalid Work Item.', 'elev8-os')); }
        if ($standard_id > 0 && !self::standard($standard_id)) { return new WP_Error('invalid_standard', __('Invalid Experience Standard.', 'elev8-os')); }
        if ($standard_id > 0) { update_post_meta($work_id, self::WORK_STANDARD_META, $standard_id); }
        else { delete_post_meta($work_id, self::WORK_STANDARD_META); }
        return true;
    }

    /** @return array<string,array<string,mixed>> */
    public static function execution_state(int $work_id): array {
        $state = get_post_meta($work_id, self::WORK_STATE_META, true);
        return is_array($state) ? $state : [];
    }

    public static function save_execution(int $work_id, int $standard_id, array $input, WP_User $user) {
        $work = Elev8_OS_Operations_Engine_Service::work_item($work_id);
        $standard = self::standard($standard_id);
        if (!$work || !$standard) { return new WP_Error('invalid_context', __('Readiness context is unavailable.', 'elev8-os')); }
        $can_manage = self::can_manage($user);
        $is_owner = (int) ($work['owner_user_id'] ?? 0) === (int) $user->ID;
        if (!$can_manage && !$is_owner) { return new WP_Error('forbidden', __('You cannot update readiness for this work.', 'elev8-os')); }
        $state = self::execution_state($work_id);
        $standard_key = (string) $standard_id;
        $current = is_array($state[$standard_key] ?? null) ? $state[$standard_key] : [];
        $completed_ids = array_map('sanitize_key', (array) ($input['completed'] ?? []));
        $notes = (array) ($input['notes'] ?? []);
        foreach ((array) $standard['cards'] as $card) {
            if (empty($card['active'])) { continue; }
            $card_id = (string) $card['id'];
            $was = is_array($current[$card_id] ?? null) ? $current[$card_id] : [];
            $complete = in_array($card_id, $completed_ids, true);
            if (($card['verification'] ?? '') === 'manager_approval' && !$can_manage) {
                $complete = !empty($was['completed']);
            }
            $note = sanitize_textarea_field((string) ($notes[$card_id] ?? ($was['note'] ?? '')));
            if (in_array((string) ($card['verification'] ?? ''), ['note', 'photo_reference'], true) && $complete && $note === '') {
                $complete = false;
            }
            $current[$card_id] = [
                'completed' => $complete,
                'note' => $note,
                'completed_by' => $complete ? (int) $user->ID : 0,
                'completed_at' => $complete ? current_time('mysql') : '',
                'updated_by' => (int) $user->ID,
                'updated_at' => current_time('mysql'),
            ];
        }
        $state[$standard_key] = $current;
        update_post_meta($work_id, self::WORK_STATE_META, $state);
        do_action('elev8_os_readiness_execution_saved', $work_id, $standard_id, $user->ID, self::snapshot($work_id, $standard_id));
        return true;
    }

    /** @return array<string,mixed> */
    public static function snapshot(int $work_id, int $standard_id): array {
        $standard = self::standard($standard_id);
        if (!$standard) { return ['required_total'=>0,'required_completed'=>0,'score'=>100,'ready'=>true,'cards'=>[]]; }
        $state = self::execution_state($work_id);
        $current = is_array($state[(string) $standard_id] ?? null) ? $state[(string) $standard_id] : [];
        $required_total = 0; $required_completed = 0; $cards = [];
        foreach ((array) $standard['cards'] as $card) {
            if (empty($card['active'])) { continue; }
            $evidence = is_array($current[$card['id']] ?? null) ? $current[$card['id']] : [];
            $card['completed'] = !empty($evidence['completed']);
            $card['note'] = (string) ($evidence['note'] ?? '');
            $card['completed_by'] = absint($evidence['completed_by'] ?? 0);
            $card['completed_at'] = (string) ($evidence['completed_at'] ?? '');
            if (!empty($card['required'])) { $required_total++; if ($card['completed']) { $required_completed++; } }
            $cards[] = $card;
        }
        $score = $required_total > 0 ? (int) round(($required_completed / $required_total) * 100) : 100;
        return [
            'standard' => $standard,
            'required_total' => $required_total,
            'required_completed' => $required_completed,
            'score' => $score,
            'ready' => $required_completed >= $required_total,
            'cards' => $cards,
        ];
    }

    public static function register_graph_objects(array $objects): array {
        $objects['experience_standard'] = [
            'label' => __('Experience Standard', 'elev8-os'),
            'engine' => 'Workflow',
            'organization_scoped' => true,
            'notes' => 'A reusable definition of the experience a role, event, class, shift, or Work Item must deliver.',
        ];
        $objects['readiness_execution'] = [
            'label' => __('Readiness Execution Evidence', 'elev8-os'),
            'engine' => 'Workflow',
            'organization_scoped' => true,
            'notes' => 'Attributed completion evidence for contextual readiness cards; it does not duplicate the Work Item.',
        ];
        return $objects;
    }
}

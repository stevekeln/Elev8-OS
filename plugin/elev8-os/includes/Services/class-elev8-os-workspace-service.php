<?php
/**
 * Universal workspace boundary for Elev8 OS records.
 *
 * Workspaces are virtual views over authoritative source records. This service
 * never duplicates business records; it gathers the related timeline, work,
 * conversations, people, files, and links around one source object.
 */
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Workspace_Service {
    public static function types(): array {
        $types = [
            'work' => __('Work Item', 'elev8-os'),
            'conversation' => __('Conversation', 'elev8-os'),
            'manager_log' => __('Manager Log', 'elev8-os'),
            'event_application' => __('Event Application', 'elev8-os'),
            'reservation' => __('Reservation', 'elev8-os'),
            'person' => __('Person', 'elev8-os'),
            'organization' => __('Organization Unit', 'elev8-os'),
        ];
        if (class_exists('Elev8_OS_Business_Graph_Registry_Service')) {
            foreach (Elev8_OS_Business_Graph_Registry_Service::objects() as $type => $definition) {
                $types[$type] = (string) ($definition['label'] ?? ucfirst(str_replace('_', ' ', $type)));
            }
        }
        return (array) apply_filters('elev8_os_workspace_types', $types);
    }

    public static function normalize_type(string $type): string {
        $type = sanitize_key($type);
        $aliases = [
            'elev8_work_item' => 'work',
            'elev8_conversation' => 'conversation',
            'elev8_ops_log' => 'manager_log',
            'elev8_event_app' => 'event_application',
            'user' => 'person',
            'org_unit' => 'organization',
            'elev8_org_unit' => 'organization',
        ];
        $type = $aliases[$type] ?? $type;
        return class_exists('Elev8_OS_Business_Graph_Registry_Service') ? Elev8_OS_Business_Graph_Registry_Service::normalize_object_type($type) : $type;
    }

    public static function url(string $type, int $id): string {
        $base = class_exists('Elev8_OS_Workspace_Module') ? Elev8_OS_Workspace_Module::url() : home_url('/elev8-workspace/');
        return add_query_arg(['workspace_type' => self::normalize_type($type), 'workspace_id' => absint($id)], $base);
    }

    public static function can_view(string $type, int $id, ?WP_User $user = null): bool {
        $type = self::normalize_type($type);
        $user = $user instanceof WP_User ? $user : wp_get_current_user();
        if (!$user instanceof WP_User || $user->ID < 1 || $id < 1) { return false; }
        if (current_user_can('manage_options') || Elev8_OS_Access_Service::user_can('manage_business_memory', $user)) { return true; }

        switch ($type) {
            case 'work':
                if (get_post_type($id) !== Elev8_OS_Work_Service::POST_TYPE) { return false; }
                $owner = absint(get_post_meta($id, '_elev8_work_owner_user_id', true));
                return Elev8_OS_Access_Service::user_can('manage_work', $user) || (Elev8_OS_Access_Service::user_can('view_work', $user) && $owner === $user->ID);
            case 'conversation':
                return class_exists('Elev8_OS_Conversation_Service') && Elev8_OS_Conversation_Service::can_view($id, $user);
            case 'manager_log':
                return get_post_type($id) === Elev8_OS_Daily_Operations_Service::POST_TYPE && (Elev8_OS_Access_Service::user_can('view_business_memory', $user) || Elev8_OS_Access_Service::user_can('submit_manager_log', $user));
            case 'event_application':
                return get_post_type($id) === 'elev8_event_app' && Elev8_OS_Access_Service::user_can('manage_event_applications', $user);
            case 'reservation':
                return Elev8_OS_Access_Service::user_can('manage_reservations', $user) || Elev8_OS_Access_Service::user_can('view_assigned_reservations', $user);
            case 'person':
                return $id === $user->ID || Elev8_OS_Access_Service::user_can('view_ceo_dashboard', $user) || Elev8_OS_Access_Service::user_can('manage_work', $user);
            case 'organization':
                return class_exists('Elev8_OS_Organization_Service') && (Elev8_OS_Organization_Service::can_manage($user) || (Elev8_OS_Organization_Service::can_view($user) && Elev8_OS_Organization_Service::user_in_scope((int) $user->ID, $id)));
        }
        return (bool) apply_filters('elev8_os_workspace_can_view', false, $type, $id, $user);
    }

    public static function summary(string $type, int $id): array {
        $type = self::normalize_type($type);
        $summary = ['type' => $type, 'id' => $id, 'label' => self::types()[$type] ?? ucfirst(str_replace('_', ' ', $type)), 'title' => __('Unavailable', 'elev8-os'), 'description' => '', 'status' => __('Unavailable', 'elev8-os'), 'source_url' => ''];
        switch ($type) {
            case 'work':
                $post = get_post($id); if (!$post instanceof WP_Post) { break; }
                $status = (string) get_post_meta($id, '_elev8_work_status', true) ?: 'new';
                $summary['title'] = get_the_title($post);
                $summary['description'] = wp_trim_words(wp_strip_all_tags($post->post_content), 45);
                $summary['status'] = Elev8_OS_Work_Service::statuses()[$status] ?? ucfirst($status);
                $summary['source_url'] = class_exists('Elev8_OS_Action_Center_Module') ? Elev8_OS_Action_Center_Module::url() : '';
                break;
            case 'conversation':
                $post = get_post($id); if (!$post instanceof WP_Post) { break; }
                $summary['title'] = get_the_title($post);
                $summary['description'] = __('Questions, decisions, and follow-up connected to this conversation.', 'elev8-os');
                $summary['status'] = ucfirst((string) get_post_meta($id, '_elev8_conversation_status', true) ?: 'open');
                $summary['source_url'] = class_exists('Elev8_OS_Conversations_Module') ? add_query_arg('conversation', $id, Elev8_OS_Conversations_Module::url()) : '';
                break;
            case 'manager_log':
                $post = get_post($id); if (!$post instanceof WP_Post) { break; }
                $author = get_userdata((int) $post->post_author);
                $summary['title'] = get_the_title($post) ?: sprintf(__('Manager Log #%d', 'elev8-os'), $id);
                $summary['description'] = $author instanceof WP_User ? sprintf(__('Submitted by %s.', 'elev8-os'), $author->display_name) : __('Manager operating log.', 'elev8-os');
                $summary['status'] = ucfirst((string) get_post_meta($id, '_elev8_ops_review_status', true) ?: 'submitted');
                $summary['source_url'] = class_exists('Elev8_OS_Action_Service') ? Elev8_OS_Action_Service::manager_log_url($id) : '';
                break;
            case 'event_application':
                $post = get_post($id); if (!$post instanceof WP_Post) { break; }
                $organization = (string) get_post_meta($id, '_elev8_event_app_organization_name', true);
                $summary['title'] = $organization ?: (get_the_title($post) ?: sprintf(__('Event Application #%d', 'elev8-os'), $id));
                $summary['description'] = __('Application, approvals, work, and follow-up in one operational context.', 'elev8-os');
                $summary['status'] = ucfirst(str_replace('_', ' ', (string) get_post_meta($id, '_elev8_event_app_status', true) ?: 'new'));
                $summary['source_url'] = add_query_arg(['page' => 'elev8-event-applications', 'view' => 'detail', 'application_id' => $id], admin_url('admin.php'));
                break;
            case 'organization':
                if (class_exists('Elev8_OS_Organization_Service')) {
                    $unit = Elev8_OS_Organization_Service::get_unit($id);
                    if ($unit) {
                        $summary['label'] = (string) $unit['type_label'];
                        $summary['title'] = (string) $unit['name'];
                        $summary['description'] = wp_trim_words(wp_strip_all_tags((string) $unit['description']), 45);
                        $summary['status'] = ucfirst((string) $unit['status']);
                        $summary['source_url'] = class_exists('Elev8_OS_Organization_Module') ? Elev8_OS_Organization_Module::url(['unit_id' => $id]) : '';
                    }
                }
                break;
            case 'person':
                $person = get_userdata($id); if (!$person instanceof WP_User) { break; }
                $summary['title'] = $person->display_name;
                $summary['description'] = $person->user_email;
                $summary['status'] = __('Active account', 'elev8-os');
                $summary['source_url'] = current_user_can('manage_options') && class_exists('Elev8_OS_Public_Profile_Service') ? Elev8_OS_Public_Profile_Service::admin_url(['user_id' => $id]) : ($id === get_current_user_id() ? home_url('/elev8-profile/') : '');
                break;
        }
        return (array) apply_filters('elev8_os_workspace_summary', $summary, $type, $id);
    }


    public static function source_details(string $type, int $id): string {
        $type = self::normalize_type($type);
        if ($type === 'organization' && class_exists('Elev8_OS_Organization_Service')) {
            $unit = Elev8_OS_Organization_Service::get_unit($id);
            return wp_kses_post((string) ($unit['description'] ?? ''));
        }
        if ($type === 'person') {
            $profile = class_exists('Elev8_OS_Public_Profile_Service') ? Elev8_OS_Public_Profile_Service::get($id) : [];
            return sanitize_textarea_field((string) ($profile['bio'] ?? ''));
        }
        $post = get_post($id);
        $details = $post instanceof WP_Post ? wp_kses_post((string) $post->post_content) : '';
        return (string) apply_filters('elev8_os_workspace_source_details', $details, $type, $id);
    }

    public static function activities(string $type, int $id, int $limit = 50): array {
        $type = self::normalize_type($type);
        if (!class_exists('Elev8_OS_Activity_Service')) { return []; }
        $object_type = self::activity_object_type($type);
        $items = Elev8_OS_Activity_Service::for_object($id, $object_type, $limit);
        if ($type === 'person') {
            $items = array_merge($items, Elev8_OS_Activity_Service::for_person($id, $limit));
            usort($items, static function($a, $b): int { return strcmp((string) $b->post_date, (string) $a->post_date); });
            $items = array_slice($items, 0, $limit);
        }
        return $items;
    }

    public static function work_items(string $type, int $id): array {
        $type = self::normalize_type($type);
        if (!class_exists('Elev8_OS_Work_Service')) { return []; }
        if ($type === 'work') { $post = get_post($id); return $post instanceof WP_Post ? [$post] : []; }
        if ($type === 'person') {
            return get_posts(['post_type' => Elev8_OS_Work_Service::POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => 100, 'orderby' => 'date', 'order' => 'DESC', 'meta_key' => '_elev8_work_owner_user_id', 'meta_value' => $id]);
        }
        return Elev8_OS_Work_Service::source_items($type, $id);
    }

    public static function conversations(string $type, int $id): array {
        if (!class_exists('Elev8_OS_Conversation_Service')) { return []; }
        $type = self::normalize_type($type);
        if ($type === 'conversation') { $thread = get_post($id); return $thread instanceof WP_Post ? [$thread] : []; }
        return get_posts([
            'post_type' => Elev8_OS_Conversation_Service::THREAD_POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => 100,
            'orderby' => 'meta_value',
            'meta_key' => '_elev8_conversation_last_activity',
            'order' => 'DESC',
            'meta_query' => [
                ['key' => '_elev8_conversation_context_type', 'value' => $type],
                ['key' => '_elev8_conversation_context_id', 'value' => $id, 'type' => 'NUMERIC'],
            ],
        ]);
    }

    public static function related_records(string $type, int $id): array {
        $type = self::normalize_type($type);
        $records = [];
        if (class_exists('Elev8_OS_Relationship_Service')) {
            foreach (Elev8_OS_Relationship_Service::for_record($type, $id) as $relationship) {
                $records[] = $relationship;
            }
        }
        if ($type === 'work') {
            $source_type = self::normalize_type((string) get_post_meta($id, '_elev8_work_source_type', true));
            $source_id = absint(get_post_meta($id, '_elev8_work_source_id', true));
            if ($source_type && $source_id) { $records[] = self::record_link($source_type, $source_id); }
            $person_id = absint(get_post_meta($id, '_elev8_work_person_id', true));
            if ($person_id) { $records[] = self::record_link('person', $person_id); }
        }
        foreach (self::work_items($type, $id) as $work) {
            if ($type !== 'work') { $records[] = self::record_link('work', (int) $work->ID); }
        }
        foreach (self::conversations($type, $id) as $thread) {
            if ($type !== 'conversation') { $records[] = self::record_link('conversation', (int) $thread->ID); }
        }
        $unique = [];
        foreach (array_filter($records) as $record) {
            $key = $record['type'] . ':' . $record['id'];
            if (!isset($unique[$key]) || !empty($record['relationship_id'])) { $unique[$key] = $record; }
        }
        return array_values($unique);
    }

    public static function relationship_impact(string $type, int $id): array {
        if (!class_exists('Elev8_OS_Relationship_Service')) {
            return ['total' => 0, 'blocks' => 0, 'depends_on' => 0, 'people' => 0, 'work' => 0, 'conversations' => 0];
        }
        return Elev8_OS_Relationship_Service::impact_summary(self::normalize_type($type), $id);
    }

    public static function people(string $type, int $id): array {
        $type = self::normalize_type($type); $ids = [];
        if ($type === 'person') { $ids[] = $id; }
        if ($type === 'work') {
            $ids[] = absint(get_post_meta($id, '_elev8_work_owner_user_id', true));
            $ids[] = absint(get_post_meta($id, '_elev8_work_created_by', true));
        }
        if ($type === 'conversation' && class_exists('Elev8_OS_Conversation_Service')) { $ids = array_merge($ids, Elev8_OS_Conversation_Service::participants($id)); }
        if ($type === 'organization' && class_exists('Elev8_OS_Organization_Service')) { foreach (Elev8_OS_Organization_Service::assignments_for_unit($id) as $assignment) { $ids[] = (int) $assignment['user_id']; } }
        $post = get_post($id); if ($post instanceof WP_Post && $post->post_author) { $ids[] = (int) $post->post_author; }
        $users = [];
        foreach (array_unique(array_filter(array_map('absint', $ids))) as $user_id) { $user = get_userdata($user_id); if ($user instanceof WP_User) { $users[] = $user; } }
        return $users;
    }

    public static function files(string $type, int $id): array {
        $type = self::normalize_type($type);
        if ($type === 'organization' && class_exists('Elev8_OS_Organization_Service')) {
            $unit = Elev8_OS_Organization_Service::get_unit($id);
            return [];
        }
        if ($type === 'person') {
            $profile = class_exists('Elev8_OS_Public_Profile_Service') ? Elev8_OS_Public_Profile_Service::get($id) : [];
            $ids = array_filter([absint($profile['profile_photo_id'] ?? 0), absint($profile['cover_image_id'] ?? 0)]);
            return array_values(array_filter(array_map('get_post', $ids)));
        }
        return get_attached_media('', $id) ?: [];
    }

    private static function record_link(string $type, int $id): array {
        if ($id < 1) { return []; }
        $summary = self::summary($type, $id);
        return ['type' => self::normalize_type($type), 'id' => $id, 'label' => $summary['label'], 'title' => $summary['title'], 'status' => $summary['status'], 'url' => self::url($type, $id)];
    }

    private static function activity_object_type(string $type): string {
        $map = ['work' => Elev8_OS_Work_Service::POST_TYPE, 'conversation' => Elev8_OS_Conversation_Service::THREAD_POST_TYPE, 'manager_log' => Elev8_OS_Daily_Operations_Service::POST_TYPE, 'event_application' => 'elev8_event_app', 'person' => 'person'];
        return $map[$type] ?? $type;
    }
}

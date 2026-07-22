<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Canonical Operations Engine service.
 *
 * Work remains stored in the existing Elev8 OS Work Item post type. This
 * service adds reusable operational semantics, organization scope and a
 * unified inbox without copying production, booking, commerce or identity
 * source records.
 */
final class Elev8_OS_Operations_Engine_Service {
    public const META_TYPE = '_elev8_work_type';
    public const META_ORGANIZATION = '_elev8_work_organization_unit_id';
    public const META_REQUESTED_BY = '_elev8_work_requested_by_user_id';
    public const META_CUSTOMER_PERSON = '_elev8_work_customer_person_id';
    public const META_STARTED_AT = '_elev8_work_started_at';
    public const META_COMPLETED_AT = '_elev8_work_completed_at';

    public static function init(): void {
        add_filter('elev8_os_business_graph_objects', [__CLASS__, 'register_graph_objects']);
    }

    /** @return array<string,string> */
    public static function types(): array {
        $types = [
            'general' => __('General Work', 'elev8-os'),
            'production' => __('Production', 'elev8-os'),
            'repair' => __('Repair', 'elev8-os'),
            'memorial' => __('Memorial', 'elev8-os'),
            'teaching' => __('Teaching', 'elev8-os'),
            'inventory' => __('Inventory', 'elev8-os'),
            'route' => __('Route', 'elev8-os'),
            'maintenance' => __('Maintenance', 'elev8-os'),
            'event' => __('Event', 'elev8-os'),
            'approval' => __('Approval', 'elev8-os'),
        ];
        return apply_filters('elev8_os_operations_work_types', $types);
    }

    /** @return array<string,string> */
    public static function statuses(): array {
        return Elev8_OS_Work_Service::statuses();
    }

    /** @param array<string,mixed> $args */
    public static function create_work(array $args) {
        $type = sanitize_key((string) ($args['type'] ?? 'general'));
        if (!isset(self::types()[$type])) { $type = 'general'; }
        $organization = absint($args['organization_unit_id'] ?? 0);
        if ($organization && class_exists('Elev8_OS_Organization_Service') && !Elev8_OS_Organization_Service::get_unit($organization)) {
            return new WP_Error('invalid_organization_scope', __('The selected organization unit could not be verified.', 'elev8-os'));
        }

        $work_id = Elev8_OS_Work_Service::create([
            'title' => $args['title'] ?? '',
            'description' => $args['description'] ?? '',
            'owner_user_id' => absint($args['owner_user_id'] ?? 0),
            'due_date' => $args['due_date'] ?? '',
            'priority' => $args['priority'] ?? 'normal',
            'status' => $args['status'] ?? 'requested',
            'source_type' => $args['source_type'] ?? '',
            'source_id' => absint($args['source_id'] ?? 0),
            'workflow_key' => $args['workflow_key'] ?? '',
            'step_key' => $args['step_key'] ?? '',
            'person_id' => absint($args['person_id'] ?? 0),
            'relationship_id' => absint($args['relationship_id'] ?? 0),
            'work_type' => $type,
            'organization_unit_id' => $organization,
            'requested_by_user_id' => absint($args['requested_by_user_id'] ?? get_current_user_id()),
            'customer_person_id' => absint($args['customer_person_id'] ?? 0),
        ]);
        return $work_id;
    }

    /** @return array<string,mixed> */
    public static function work_item(int $id): array {
        $post = get_post($id);
        if (!$post instanceof WP_Post || $post->post_type !== Elev8_OS_Work_Service::POST_TYPE) { return []; }
        $owner_id = absint(get_post_meta($id, '_elev8_work_owner_user_id', true));
        $owner = $owner_id ? get_user_by('id', $owner_id) : false;
        $type = sanitize_key((string) get_post_meta($id, self::META_TYPE, true));
        if (!isset(self::types()[$type])) { $type = self::infer_type($id); }
        return [
            'id' => $id,
            'title' => get_the_title($id),
            'description' => (string) $post->post_content,
            'type' => $type,
            'type_label' => self::types()[$type] ?? ucfirst($type),
            'status' => (string) get_post_meta($id, '_elev8_work_status', true) ?: 'requested',
            'priority' => (string) get_post_meta($id, '_elev8_work_priority', true) ?: 'normal',
            'owner_user_id' => $owner_id,
            'owner_name' => $owner instanceof WP_User ? $owner->display_name : __('Unassigned', 'elev8-os'),
            'due_date' => (string) get_post_meta($id, '_elev8_work_due_date', true),
            'organization_unit_id' => absint(get_post_meta($id, self::META_ORGANIZATION, true)),
            'requested_by_user_id' => absint(get_post_meta($id, self::META_REQUESTED_BY, true)),
            'customer_person_id' => absint(get_post_meta($id, self::META_CUSTOMER_PERSON, true)),
            'source_type' => (string) get_post_meta($id, '_elev8_work_source_type', true),
            'source_id' => absint(get_post_meta($id, '_elev8_work_source_id', true)),
            'started_at' => (string) get_post_meta($id, self::META_STARTED_AT, true),
            'completed_at' => (string) get_post_meta($id, self::META_COMPLETED_AT, true),
            'contributor_key' => (string) get_post_meta($id, '_elev8_work_contributor_key', true),
            'checklist' => (array) get_post_meta($id, '_elev8_work_checklist', true),
            'required_approvals' => (array) get_post_meta($id, '_elev8_work_required_approvals', true),
            'completion_rules' => (array) get_post_meta($id, '_elev8_work_completion_rules', true),
            'escalation' => (array) get_post_meta($id, '_elev8_work_escalation', true),
            'source_status' => (string) get_post_meta($id, '_elev8_work_source_status', true),
            'last_synced_at' => (string) get_post_meta($id, '_elev8_work_last_synced_at', true),
            'execution' => class_exists('Elev8_OS_SOP_Execution_Service') ? Elev8_OS_SOP_Execution_Service::execution($id) : ['checklist'=>[],'approvals'=>[],'timeline'=>[],'ready'=>true],
            'workspace_url' => class_exists('Elev8_OS_Workspace_Service') ? Elev8_OS_Workspace_Service::url('work', $id) : '',
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public static function inbox(WP_User $user, array $filters = []): array {
        $manage = Elev8_OS_Access_Service::user_can('manage_operations', $user) || Elev8_OS_Access_Service::user_can('manage_work', $user);
        $view = sanitize_key((string) ($filters['view'] ?? 'mine'));
        $meta = ['relation' => 'AND'];
        if (!$manage || $view !== 'team') {
            $meta[] = ['key' => '_elev8_work_owner_user_id', 'value' => $user->ID, 'type' => 'NUMERIC'];
        }
        $status = sanitize_key((string) ($filters['status'] ?? 'active'));
        if ($status === 'active') {
            $meta[] = ['key' => '_elev8_work_status', 'value' => ['new','requested','queued','assigned','in_progress','waiting','review','approved'], 'compare' => 'IN'];
        } elseif (isset(Elev8_OS_Work_Service::statuses()[$status])) {
            $meta[] = ['key' => '_elev8_work_status', 'value' => $status];
        }
        $type = sanitize_key((string) ($filters['type'] ?? ''));
        if ($type && isset(self::types()[$type])) { $meta[] = ['key' => self::META_TYPE, 'value' => $type]; }
        $organization = absint($filters['organization_unit_id'] ?? 0);
        if ($organization) { $meta[] = ['key' => self::META_ORGANIZATION, 'value' => $organization, 'type' => 'NUMERIC']; }

        $posts = get_posts([
            'post_type' => Elev8_OS_Work_Service::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => 250,
            'orderby' => ['meta_value' => 'ASC', 'date' => 'DESC'],
            'meta_key' => '_elev8_work_due_date',
            'meta_query' => $meta,
        ]);
        $items = [];
        foreach ($posts as $post) {
            $item = self::work_item((int) $post->ID);
            if (!$item) { continue; }
            if ($organization && !self::user_can_access_scope($user, $organization)) { continue; }
            if (!$organization && $manage && $view === 'team' && $item['organization_unit_id'] && !self::user_can_access_scope($user, (int) $item['organization_unit_id'])) { continue; }
            $items[] = $item;
        }
        return $items;
    }

    /** @return array<string,int> */
    public static function metrics(WP_User $user, bool $team = false): array {
        $items = self::inbox($user, ['view' => $team ? 'team' : 'mine', 'status' => 'active']);
        $today = current_time('Y-m-d');
        $metrics = ['active'=>0,'overdue'=>0,'due_today'=>0,'waiting'=>0,'review'=>0,'unassigned'=>0];
        foreach ($items as $item) {
            $metrics['active']++;
            if ($item['due_date'] && $item['due_date'] < $today) { $metrics['overdue']++; }
            if ($item['due_date'] === $today) { $metrics['due_today']++; }
            if ($item['status'] === 'waiting') { $metrics['waiting']++; }
            if (in_array($item['status'], ['review','approved'], true)) { $metrics['review']++; }
            if (!(int) $item['owner_user_id']) { $metrics['unassigned']++; }
        }
        return $metrics;
    }

    /** @return array<string,int> */
    public static function source_signals(): array {
        $signals = ['production'=>0,'repair'=>0,'memorial'=>0,'classes_pending'=>0,'manager_logs'=>0];
        if (class_exists('Elev8_OS_Glass_Operations_Service')) {
            foreach (['production','repair','memorial'] as $kind) {
                if (method_exists('Elev8_OS_Glass_Operations_Service', 'jobs')) {
                    $jobs = Elev8_OS_Glass_Operations_Service::jobs(['job_type' => $kind, 'posts_per_page' => 200]);
                    $signals[$kind] = is_array($jobs) ? count($jobs) : 0;
                }
            }
        }
        if (class_exists('Elev8_OS_Class_Approval_Service') && method_exists('Elev8_OS_Class_Approval_Service', 'pending_bookings')) {
            $pending = Elev8_OS_Class_Approval_Service::pending_bookings();
            $signals['classes_pending'] = is_array($pending) ? count($pending) : 0;
        }
        if (class_exists('Elev8_OS_Daily_Operations_Service')) {
            $logs = get_posts(['post_type'=>Elev8_OS_Daily_Operations_Service::POST_TYPE,'post_status'=>'publish','posts_per_page'=>200,'fields'=>'ids']);
            $signals['manager_logs'] = count($logs);
        }
        return $signals;
    }

    public static function update_status(int $id, string $status) {
        $status = sanitize_key($status);
        if ($status === 'completed' && class_exists('Elev8_OS_SOP_Execution_Service')) {
            $ready = Elev8_OS_SOP_Execution_Service::validate_completion($id);
            if (is_wp_error($ready)) { return $ready; }
        }
        $before = (string) get_post_meta($id, '_elev8_work_status', true);
        $result = Elev8_OS_Work_Service::update($id, ['status' => $status]);
        if (is_wp_error($result)) { return $result; }
        if ($status === 'in_progress' && $before !== 'in_progress' && !get_post_meta($id, self::META_STARTED_AT, true)) {
            update_post_meta($id, self::META_STARTED_AT, current_time('mysql'));
        }
        if ($status === 'completed' && $before !== 'completed') { update_post_meta($id, self::META_COMPLETED_AT, current_time('mysql')); }
        do_action('elev8_os_operations_work_status_changed', $id, $status, $before);
        return true;
    }

    public static function organization_label(int $id): string {
        if (!$id || !class_exists('Elev8_OS_Organization_Service')) { return __('Unscoped', 'elev8-os'); }
        $unit = Elev8_OS_Organization_Service::get_unit($id);
        return is_array($unit) ? (string) ($unit['name'] ?? __('Unavailable', 'elev8-os')) : __('Unavailable', 'elev8-os');
    }

    private static function user_can_access_scope(WP_User $user, int $organization): bool {
        if (!$organization || user_can($user, 'manage_options') || Elev8_OS_Access_Service::user_can('view_ceo_dashboard', $user)) { return true; }
        return class_exists('Elev8_OS_Organization_Service') && Elev8_OS_Organization_Service::user_in_scope($user->ID, $organization);
    }

    private static function infer_type(int $id): string {
        $source = sanitize_key((string) get_post_meta($id, '_elev8_work_source_type', true));
        $map = ['production_job'=>'production','repair'=>'repair','memorial_case'=>'memorial','booking'=>'teaching','class'=>'teaching','event'=>'event','event_application'=>'event','manager_log'=>'general'];
        return $map[$source] ?? 'general';
    }

    public static function register_graph_objects(array $objects): array {
        if (isset($objects['work'])) {
            $objects['work']['engine'] = 'Operations';
            $objects['work']['organization_scoped'] = true;
            $objects['work']['notes'] = 'Canonical operational execution record. Workflow supplies reusable states and approvals.';
        }
        return $objects;
    }
}

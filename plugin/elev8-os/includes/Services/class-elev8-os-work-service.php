<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Shared work-management service for tasks created by any Elev8 OS module.
 */
final class Elev8_OS_Work_Service {
    public const POST_TYPE = 'elev8_work_item';

    public static function init(): void {
        add_action('init', [__CLASS__, 'register_post_type']);
    }

    public static function activate(): void {
        self::register_post_type();
    }

    public static function register_post_type(): void {
        register_post_type(self::POST_TYPE, [
            'labels' => ['name' => __('Work Items', 'elev8-os'), 'singular_name' => __('Work Item', 'elev8-os')],
            'public' => false,
            'show_ui' => false,
            'supports' => ['title', 'editor'],
            'map_meta_cap' => true,
        ]);
    }

    public static function statuses(): array {
        return [
            'new' => __('New', 'elev8-os'),
            'assigned' => __('Assigned', 'elev8-os'),
            'in_progress' => __('In Progress', 'elev8-os'),
            'waiting' => __('Waiting', 'elev8-os'),
            'completed' => __('Completed', 'elev8-os'),
            'cancelled' => __('Cancelled', 'elev8-os'),
            'archived' => __('Archived', 'elev8-os'),
        ];
    }

    public static function priorities(): array {
        return ['low' => __('Low', 'elev8-os'), 'normal' => __('Normal', 'elev8-os'), 'high' => __('High', 'elev8-os'), 'urgent' => __('Urgent', 'elev8-os')];
    }

    /** @param array<string,mixed> $args */
    public static function create(array $args) {
        $defaults = [
            'title' => '', 'description' => '', 'owner_user_id' => 0, 'due_date' => '', 'priority' => 'normal', 'status' => 'new',
            'source_type' => '', 'source_id' => 0, 'workflow_key' => '', 'step_key' => '', 'person_id' => 0, 'relationship_id' => 0,
        ];
        $args = wp_parse_args($args, $defaults);
        $title = sanitize_text_field((string) $args['title']);
        if ($title === '') { return new WP_Error('missing_title', __('Work item title is required.', 'elev8-os')); }

        $source_type = sanitize_key((string) $args['source_type']);
        $source_id = absint($args['source_id']);
        $workflow_key = sanitize_key((string) $args['workflow_key']);
        $step_key = sanitize_key((string) $args['step_key']);
        if ($source_type && $source_id && $workflow_key && $step_key) {
            $existing = self::find_existing($source_type, $source_id, $workflow_key, $step_key);
            if ($existing) { return $existing; }
        }

        $id = wp_insert_post([
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'post_title' => $title,
            'post_content' => wp_kses_post((string) $args['description']),
        ], true);
        if (is_wp_error($id)) { return $id; }

        $status = sanitize_key((string) $args['status']);
        if (!isset(self::statuses()[$status])) { $status = 'new'; }
        $priority = sanitize_key((string) $args['priority']);
        if (!isset(self::priorities()[$priority])) { $priority = 'normal'; }
        $owner = absint($args['owner_user_id']);
        if ($owner && !Elev8_OS_Access_Service::user_can('receive_assignments', get_user_by('id', $owner) ?: null)) { $owner = 0; }
        if ($owner && $status === 'new') { $status = 'assigned'; }

        $meta = [
            '_elev8_work_status' => $status,
            '_elev8_work_priority' => $priority,
            '_elev8_work_owner_user_id' => $owner,
            '_elev8_work_due_date' => self::clean_date((string) $args['due_date']),
            '_elev8_work_source_type' => $source_type,
            '_elev8_work_source_id' => $source_id,
            '_elev8_work_workflow_key' => $workflow_key,
            '_elev8_work_step_key' => $step_key,
            '_elev8_work_person_id' => absint($args['person_id']),
            '_elev8_work_relationship_id' => absint($args['relationship_id']),
            '_elev8_work_created_by' => get_current_user_id(),
        ];
        foreach ($meta as $key => $value) { update_post_meta((int) $id, $key, $value); }
        self::record_activity((int) $id, 'work_created', __('Work item created', 'elev8-os'), $title);
        return (int) $id;
    }

    public static function update(int $id, array $changes) {
        if (get_post_type($id) !== self::POST_TYPE) { return new WP_Error('invalid_work_item', __('Invalid work item.', 'elev8-os')); }
        $before_status = (string) get_post_meta($id, '_elev8_work_status', true);
        $before_owner = absint(get_post_meta($id, '_elev8_work_owner_user_id', true));

        if (array_key_exists('title', $changes)) { wp_update_post(['ID' => $id, 'post_title' => sanitize_text_field((string) $changes['title'])]); }
        if (array_key_exists('description', $changes)) { wp_update_post(['ID' => $id, 'post_content' => wp_kses_post((string) $changes['description'])]); }
        if (array_key_exists('status', $changes)) {
            $status = sanitize_key((string) $changes['status']);
            if (isset(self::statuses()[$status])) { update_post_meta($id, '_elev8_work_status', $status); }
        }
        if (array_key_exists('priority', $changes)) {
            $priority = sanitize_key((string) $changes['priority']);
            if (isset(self::priorities()[$priority])) { update_post_meta($id, '_elev8_work_priority', $priority); }
        }
        if (array_key_exists('due_date', $changes)) { update_post_meta($id, '_elev8_work_due_date', self::clean_date((string) $changes['due_date'])); }
        if (array_key_exists('owner_user_id', $changes)) {
            $owner = absint($changes['owner_user_id']);
            $user = $owner ? get_user_by('id', $owner) : false;
            if (!$owner || ($user instanceof WP_User && Elev8_OS_Access_Service::user_can('receive_assignments', $user))) { update_post_meta($id, '_elev8_work_owner_user_id', $owner); }
        }
        if (array_key_exists('notes', $changes)) { update_post_meta($id, '_elev8_work_notes', sanitize_textarea_field((string) $changes['notes'])); }

        $after_status = (string) get_post_meta($id, '_elev8_work_status', true);
        $after_owner = absint(get_post_meta($id, '_elev8_work_owner_user_id', true));
        $details = [];
        if ($before_status !== $after_status) { $details[] = sprintf(__('Status: %1$s → %2$s', 'elev8-os'), $before_status ?: 'new', $after_status); }
        if ($before_owner !== $after_owner) { $details[] = sprintf(__('Owner changed from user #%1$d to user #%2$d', 'elev8-os'), $before_owner, $after_owner); }
        self::record_activity($id, 'work_updated', __('Work item updated', 'elev8-os'), $details ? implode('; ', $details) : __('Work item details updated.', 'elev8-os'));
        return true;
    }

    public static function generate_takeover_workflow(int $application_id): array {
        if (get_post_type($application_id) !== 'elev8_event_app') { return []; }
        $organization = (string) get_post_meta($application_id, '_elev8_event_app_organization_name', true);
        $date = (string) get_post_meta($application_id, '_elev8_event_app_preferred_date_1', true);
        $assigned = absint(get_post_meta($application_id, '_elev8_event_app_assigned_user', true));
        $relationship = absint(get_post_meta($application_id, '_elev8_event_app_relationship_id', true));
        $person = absint(get_post_meta($application_id, '_elev8_event_app_person_id', true));
        $label = $organization ?: sprintf(__('Application #%d', 'elev8-os'), $application_id);
        $base = current_time('Y-m-d');
        $steps = [
            ['review', sprintf(__('Review Takeover application — %s', 'elev8-os'), $label), $base, 'high'],
            ['contact', sprintf(__('Contact dispensary — %s', 'elev8-os'), $label), self::date_plus($base, 1), 'high'],
            ['decision', sprintf(__('Approve or decline Takeover — %s', 'elev8-os'), $label), self::date_plus($base, 3), 'high'],
            ['reserve_date', sprintf(__('Confirm and reserve event date — %s', 'elev8-os'), $label), self::date_plus($base, 5), 'normal'],
            ['assign_lead', sprintf(__('Assign event lead — %s', 'elev8-os'), $label), self::date_plus($base, 7), 'normal'],
            ['marketing', sprintf(__('Create Takeover marketing package — %s', 'elev8-os'), $label), self::date_plus($base, 10), 'normal'],
            ['staffing', sprintf(__('Schedule Takeover staff — %s', 'elev8-os'), $label), self::date_plus($base, 14), 'normal'],
            ['confirm_week', sprintf(__('Confirm Takeover details one week before — %s', 'elev8-os'), $label), $date ? self::date_plus($date, -7) : '', 'high'],
            ['confirm_day', sprintf(__('Confirm Takeover details one day before — %s', 'elev8-os'), $label), $date ? self::date_plus($date, -1) : '', 'high'],
            ['follow_up', sprintf(__('Complete Takeover follow-up — %s', 'elev8-os'), $label), $date ? self::date_plus($date, 1) : '', 'normal'],
        ];
        $ids = [];
        foreach ($steps as [$key, $title, $due, $priority]) {
            $result = self::create([
                'title' => $title,
                'description' => sprintf(__('Generated from Elev8 Takeover application #%d.', 'elev8-os'), $application_id),
                'owner_user_id' => in_array($key, ['review','contact','decision'], true) ? $assigned : 0,
                'due_date' => $due,
                'priority' => $priority,
                'source_type' => 'event_application',
                'source_id' => $application_id,
                'workflow_key' => 'elev8_takeover',
                'step_key' => $key,
                'relationship_id' => $relationship,
                'person_id' => $person,
            ]);
            if (!is_wp_error($result)) { $ids[] = (int) $result; }
        }
        update_post_meta($application_id, '_elev8_event_app_workflow_generated', current_time('mysql'));
        return array_values(array_unique($ids));
    }

    public static function counts(int $owner_user_id = 0): array {
        $today = current_time('Y-m-d');
        $base = ['post_type' => self::POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids'];
        $owner_query = $owner_user_id ? [['key' => '_elev8_work_owner_user_id', 'value' => $owner_user_id, 'type' => 'NUMERIC']] : [];
        $active_statuses = ['new','assigned','in_progress','waiting'];
        $count = static function(array $meta) use ($base): int { $q = new WP_Query(array_merge($base, ['meta_query' => $meta])); return (int) $q->found_posts; };
        return [
            'active' => $count(array_merge($owner_query, [['key'=>'_elev8_work_status','value'=>$active_statuses,'compare'=>'IN']])),
            'overdue' => $count(array_merge($owner_query, [['key'=>'_elev8_work_status','value'=>$active_statuses,'compare'=>'IN'], ['key'=>'_elev8_work_due_date','value'=>$today,'compare'=>'<','type'=>'DATE']])),
            'due_today' => $count(array_merge($owner_query, [['key'=>'_elev8_work_status','value'=>$active_statuses,'compare'=>'IN'], ['key'=>'_elev8_work_due_date','value'=>$today,'compare'=>'=','type'=>'DATE']])),
            'unassigned' => $count([['key'=>'_elev8_work_status','value'=>$active_statuses,'compare'=>'IN'], ['key'=>'_elev8_work_owner_user_id','value'=>0,'type'=>'NUMERIC']]),
            'waiting' => $count(array_merge($owner_query, [['key'=>'_elev8_work_status','value'=>'waiting']])),
        ];
    }

    public static function source_items(string $source_type, int $source_id): array {
        return get_posts(['post_type'=>self::POST_TYPE,'post_status'=>'publish','posts_per_page'=>100,'orderby'=>'date','order'=>'ASC','meta_query'=>[
            ['key'=>'_elev8_work_source_type','value'=>sanitize_key($source_type)], ['key'=>'_elev8_work_source_id','value'=>$source_id,'type'=>'NUMERIC']
        ]]);
    }

    private static function find_existing(string $source_type, int $source_id, string $workflow_key, string $step_key): int {
        $ids = get_posts(['post_type'=>self::POST_TYPE,'post_status'=>'publish','posts_per_page'=>1,'fields'=>'ids','meta_query'=>[
            ['key'=>'_elev8_work_source_type','value'=>$source_type], ['key'=>'_elev8_work_source_id','value'=>$source_id,'type'=>'NUMERIC'],
            ['key'=>'_elev8_work_workflow_key','value'=>$workflow_key], ['key'=>'_elev8_work_step_key','value'=>$step_key],
        ]]);
        return $ids ? (int) $ids[0] : 0;
    }

    private static function record_activity(int $id, string $type, string $label, string $details): void {
        if (!class_exists('Elev8_OS_Activity_Service')) { return; }
        Elev8_OS_Activity_Service::record(['type'=>$type,'label'=>$label,'details'=>$details,'object_id'=>$id,'object_type'=>self::POST_TYPE,'source'=>'work','actor_user_id'=>get_current_user_id()]);
    }

    private static function clean_date(string $date): string {
        $date = sanitize_text_field($date);
        if ($date === '') { return ''; }
        $parsed = DateTime::createFromFormat('Y-m-d', $date);
        return $parsed && $parsed->format('Y-m-d') === $date ? $date : '';
    }

    private static function date_plus(string $date, int $days): string {
        try { $d = new DateTime($date); $d->modify(($days >= 0 ? '+' : '') . $days . ' days'); return $d->format('Y-m-d'); } catch (Exception $e) { return ''; }
    }
}

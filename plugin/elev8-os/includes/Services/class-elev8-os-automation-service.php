<?php
/** Rule-based workspace automation and suggested-action engine. */
if (!defined('ABSPATH')) { exit; }
final class Elev8_OS_Automation_Service {
    public static function init(): void {
        add_action('elev8_os_relationship_created', [__CLASS__, 'relationship_created'], 20, 2);
    }

    public static function can_execute(?WP_User $user = null): bool {
        $user = $user instanceof WP_User ? $user : wp_get_current_user();
        return $user instanceof WP_User && $user->ID > 0 && (
            current_user_can('manage_options') ||
            Elev8_OS_Access_Service::user_can('manage_work', $user) ||
            Elev8_OS_Access_Service::user_can('manage_business_memory', $user)
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function suggestions(string $type, int $id, ?WP_User $user = null): array {
        $type = Elev8_OS_Workspace_Service::normalize_type($type);
        $id = absint($id);
        $user = $user instanceof WP_User ? $user : wp_get_current_user();
        if (!$id || !Elev8_OS_Workspace_Service::can_view($type, $id, $user)) { return []; }
        $items = [];
        $work = Elev8_OS_Workspace_Service::work_items($type, $id);
        $open_work = array_filter($work, static function($post): bool {
            $status = (string) get_post_meta((int)$post->ID, '_elev8_work_status', true);
            return !in_array($status, ['completed','cancelled','archived'], true);
        });

        if ($type === 'manager_log') {
            $fields = (array) get_post_meta($id, Elev8_OS_Daily_Operations_Service::META_FIELDS, true);
            $message = trim((string)($fields['owner_attention_items'] ?? ''));
            if ($message !== '' && !$open_work) {
                $items[] = self::suggestion('manager_owner_followup', __('Create owner follow-up action', 'elev8-os'), __('This manager log includes a message for Steve but has no open action connected to it.', 'elev8-os'), 'high', __('Creates one tracked action connected to this manager log. Nothing is sent or changed until you confirm.', 'elev8-os'));
            }
        }
        if ($type === 'event_application') {
            $status = (string) get_post_meta($id, '_elev8_event_app_status', true) ?: 'new';
            if (in_array($status, ['new','review','contacted','agreement_needed'], true) && !$open_work) {
                $items[] = self::suggestion('event_application_followup', __('Create application review action', 'elev8-os'), __('This event application is still waiting in the review process and has no open action.', 'elev8-os'), 'high', __('Creates a review action tied to this application so ownership and follow-up are visible.', 'elev8-os'));
            }
        }
        if ($type === 'reservation' && !$open_work) {
            $items[] = self::suggestion('reservation_followup', __('Create reservation follow-up', 'elev8-os'), __('No open follow-up action is connected to this reservation.', 'elev8-os'), 'normal', __('Creates one action connected to this reservation. The reservation itself is not changed.', 'elev8-os'));
        }
        if ($type === 'conversation' && !$open_work) {
            $status = (string) get_post_meta($id, '_elev8_conversation_status', true) ?: 'open';
            if ($status === 'open') {
                $items[] = self::suggestion('conversation_followup', __('Turn this conversation into an action', 'elev8-os'), __('This conversation is open and does not yet have a connected action.', 'elev8-os'), 'normal', __('Creates a tracked action while keeping the conversation as the source of truth.', 'elev8-os'));
            }
        }
        if ($type === 'work') {
            $status = (string) get_post_meta($id, '_elev8_work_status', true) ?: 'new';
            if (!in_array($status, ['completed','cancelled','archived'], true)) {
                $items[] = self::suggestion('complete_work', __('Mark this action complete', 'elev8-os'), __('This action is still active.', 'elev8-os'), 'normal', __('Updates only this action status and records the change in the activity timeline.', 'elev8-os'));
            }
        }
        return (array) apply_filters('elev8_os_workspace_automation_suggestions', $items, $type, $id, $user);
    }

    private static function suggestion(string $key, string $title, string $reason, string $priority, string $why): array {
        return compact('key','title','reason','priority','why');
    }

    public static function execute(string $key, string $type, int $id, ?WP_User $user = null) {
        $user = $user instanceof WP_User ? $user : wp_get_current_user();
        $key = sanitize_key($key); $type = Elev8_OS_Workspace_Service::normalize_type($type); $id = absint($id);
        if (!self::can_execute($user) || !Elev8_OS_Workspace_Service::can_view($type, $id, $user)) {
            return new WP_Error('forbidden', __('You do not have permission to run this automation.', 'elev8-os'));
        }
        $summary = Elev8_OS_Workspace_Service::summary($type, $id);
        if ($key === 'complete_work' && $type === 'work') {
            $result = Elev8_OS_Work_Service::update($id, ['status'=>'completed']);
            if (is_wp_error($result)) { return $result; }
            self::record($type, $id, __('Action marked complete from Workspace Automation.', 'elev8-os'), $key);
            return $id;
        }
        $titles = [
            'manager_owner_followup' => sprintf(__('Follow up on manager note: %s', 'elev8-os'), $summary['title']),
            'event_application_followup' => sprintf(__('Review event application: %s', 'elev8-os'), $summary['title']),
            'reservation_followup' => sprintf(__('Follow up on reservation: %s', 'elev8-os'), $summary['title']),
            'conversation_followup' => sprintf(__('Follow up on conversation: %s', 'elev8-os'), $summary['title']),
        ];
        if (!isset($titles[$key])) { return new WP_Error('invalid_automation', __('Unknown automation.', 'elev8-os')); }
        $work_id = Elev8_OS_Work_Service::create([
            'title'=>$titles[$key],
            'description'=>sprintf(__('Created from the %s workspace by the Elev8 OS Automation Engine.', 'elev8-os'), $summary['label']),
            'owner_user_id'=>$user->ID,
            'priority'=>in_array($key, ['manager_owner_followup','event_application_followup'], true) ? 'high' : 'normal',
            'source_type'=>$type,
            'source_id'=>$id,
            'workflow_key'=>'workspace_automation',
            'step_key'=>$key,
        ]);
        if (is_wp_error($work_id)) { return $work_id; }
        if (class_exists('Elev8_OS_Relationship_Service')) {
            Elev8_OS_Relationship_Service::connect($type, $id, 'work', (int)$work_id, 'follow_up_for', __('Created by Workspace Automation.', 'elev8-os'));
        }
        self::record($type, $id, __('Suggested automation created a connected action.', 'elev8-os'), $key);
        return (int)$work_id;
    }

    private static function record(string $type, int $id, string $details, string $key): void {
        if (!class_exists('Elev8_OS_Activity_Service')) { return; }
        Elev8_OS_Activity_Service::record([
            'type'=>'automation_executed','label'=>__('Workspace automation completed', 'elev8-os'),'details'=>$details,
            'object_id'=>$id,'object_type'=>$type,'source'=>'automation-engine','actor_user_id'=>get_current_user_id(),
            'metadata'=>['automation_key'=>$key],
        ]);
    }

    public static function relationship_created(int $relationship_id, array $meta): void {
        if (!class_exists('Elev8_OS_Activity_Service')) { return; }
        $kind = sanitize_key((string)($meta['_elev8_relation_kind'] ?? 'related_to'));
        if (!in_array($kind, ['blocks','depends_on'], true)) { return; }
        Elev8_OS_Activity_Service::record([
            'type'=>'automation_signal','label'=>__('Dependency signal recorded', 'elev8-os'),
            'details'=>__('A blocking or dependency relationship is now available to workspace automation.', 'elev8-os'),
            'object_id'=>absint($meta['_elev8_relation_from_id'] ?? 0),'object_type'=>sanitize_key((string)($meta['_elev8_relation_from_type'] ?? '')),
            'source'=>'automation-engine','actor_user_id'=>get_current_user_id(),'metadata'=>['relationship_id'=>$relationship_id,'kind'=>$kind],
        ]);
    }
}

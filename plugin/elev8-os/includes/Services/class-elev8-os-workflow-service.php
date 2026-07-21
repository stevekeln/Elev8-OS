<?php
/**
 * Shared Workflow Engine foundation for Elev8 OS.
 *
 * Modules publish trusted events. This service evaluates configurable workflow
 * definitions, executes registered actions, and preserves an auditable run log.
 */
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Workflow_Service {
    public const POST_TYPE = 'elev8_workflow_run';

    public static function init(): void {
        add_action('init', [__CLASS__, 'register_post_type']);
        add_action('elev8_os_operations_entry_created', [__CLASS__, 'operations_entry_created'], 20, 3);
        add_action('elev8_os_intake_created', [__CLASS__, 'intake_created'], 20, 2);
        add_action('elev8_os_bingo_reservation_created', [__CLASS__, 'reservation_created'], 20, 2);
    }

    public static function activate(): void { self::register_post_type(); }

    public static function register_post_type(): void {
        register_post_type(self::POST_TYPE, [
            'labels' => ['name' => __('Workflow Runs', 'elev8-os'), 'singular_name' => __('Workflow Run', 'elev8-os')],
            'public' => false,
            'show_ui' => false,
            'show_in_rest' => false,
            'supports' => ['title', 'editor', 'author'],
            'map_meta_cap' => true,
        ]);
    }

    /** @return array<string,array<string,mixed>> */
    public static function definitions(): array {
        $definitions = [
            'manager_owner_attention' => [
                'label' => __('Route manager owner note', 'elev8-os'),
                'trigger' => 'operations.entry.created',
                'condition' => static function(array $context): bool {
                    return ($context['template_key'] ?? '') === 'manager'
                        && trim((string)($context['values']['owner_attention_items'] ?? '')) !== '';
                },
                'actions' => [['type' => 'record_activity', 'label' => __('Manager note routed to CEO Attention', 'elev8-os')]],
            ],
            'unified_intake_routing' => [
                'label' => __('Record new intake routing', 'elev8-os'),
                'trigger' => 'intake.created',
                'actions' => [['type' => 'record_activity', 'label' => __('New intake entered the operating queue', 'elev8-os')]],
            ],
            'bingo_reservation_routing' => [
                'label' => __('Record Bingo reservation routing', 'elev8-os'),
                'trigger' => 'reservation.created',
                'actions' => [['type' => 'record_activity', 'label' => __('Reservation entered the event workflow', 'elev8-os')]],
            ],
        ];
        $filtered = apply_filters('elev8_os_workflow_definitions', $definitions);
        return is_array($filtered) ? $filtered : $definitions;
    }

    /** @return array<string,callable> */
    public static function actions(): array {
        $actions = [
            'record_activity' => [__CLASS__, 'action_record_activity'],
            'create_work' => [__CLASS__, 'action_create_work'],
        ];
        $filtered = apply_filters('elev8_os_workflow_actions', $actions);
        return is_array($filtered) ? $filtered : $actions;
    }

    /** @return array<int,int> */
    public static function trigger(string $trigger, array $context): array {
        $trigger = sanitize_key(str_replace('.', '_', $trigger));
        $trigger = str_replace('_', '.', $trigger);
        $run_ids = [];
        foreach (self::definitions() as $workflow_key => $definition) {
            if (!is_array($definition) || (string)($definition['trigger'] ?? '') !== $trigger) { continue; }
            $condition = $definition['condition'] ?? null;
            if (is_callable($condition) && !$condition($context)) { continue; }
            $source_id = absint($context['source_id'] ?? 0);
            if ($source_id && self::existing_run($workflow_key, $trigger, $source_id)) { continue; }
            $run_ids[] = self::execute(sanitize_key((string)$workflow_key), $definition, $trigger, $context);
        }
        return array_values(array_filter(array_map('absint', $run_ids)));
    }

    public static function operations_entry_created(int $post_id, string $template_key, array $values): void {
        self::trigger('operations.entry.created', ['source_id'=>$post_id,'source_type'=>'operations_entry','template_key'=>$template_key,'values'=>$values,'actor_user_id'=>get_current_user_id()]);
    }

    public static function intake_created(int $post_id, array $data): void {
        self::trigger('intake.created', ['source_id'=>$post_id,'source_type'=>'intake','data'=>$data,'actor_user_id'=>get_current_user_id()]);
    }

    public static function reservation_created(int $post_id, array $meta): void {
        self::trigger('reservation.created', ['source_id'=>$post_id,'source_type'=>'bingo_reservation','data'=>$meta,'actor_user_id'=>get_current_user_id()]);
    }

    /** @return array<string,mixed> */
    public static function summary(int $days = 7): array {
        $after = gmdate('Y-m-d H:i:s', time() - max(1, $days) * DAY_IN_SECONDS);
        $ids = get_posts(['post_type'=>self::POST_TYPE,'post_status'=>'publish','posts_per_page'=>200,'fields'=>'ids','date_query'=>[['after'=>$after,'inclusive'=>true]]]);
        $success = 0; $failed = 0;
        foreach ($ids as $id) {
            $status = (string)get_post_meta((int)$id, '_elev8_workflow_status', true);
            if ($status === 'completed') { $success++; }
            elseif ($status === 'failed') { $failed++; }
        }
        return [
            'available' => true,
            'active_workflows' => count(self::definitions()),
            'runs' => count($ids),
            'completed' => $success,
            'failed' => $failed,
            'period_days' => max(1, $days),
            'why' => __('Workflow Health is based on registered workflow definitions and auditable workflow runs stored by Elev8 OS. It does not count actions from systems that have not published a workflow event.', 'elev8-os'),
        ];
    }

    private static function execute(string $workflow_key, array $definition, string $trigger, array $context): int {
        $label = sanitize_text_field((string)($definition['label'] ?? $workflow_key));
        $run_id = wp_insert_post(['post_type'=>self::POST_TYPE,'post_status'=>'publish','post_title'=>$label,'post_content'=>'','post_author'=>absint($context['actor_user_id'] ?? get_current_user_id())], true);
        if (is_wp_error($run_id)) { return 0; }
        update_post_meta($run_id, '_elev8_workflow_key', $workflow_key);
        update_post_meta($run_id, '_elev8_workflow_trigger', $trigger);
        update_post_meta($run_id, '_elev8_workflow_source_id', absint($context['source_id'] ?? 0));
        update_post_meta($run_id, '_elev8_workflow_source_type', sanitize_key((string)($context['source_type'] ?? '')));
        update_post_meta($run_id, '_elev8_workflow_started_at', current_time('mysql'));
        update_post_meta($run_id, '_elev8_workflow_status', 'running');

        $results = []; $failed = false; $registry = self::actions();
        foreach ((array)($definition['actions'] ?? []) as $index => $action) {
            $type = sanitize_key((string)($action['type'] ?? ''));
            if ($type === '' || empty($registry[$type]) || !is_callable($registry[$type])) {
                $results[] = ['action'=>$type ?: 'unknown','status'=>'failed','message'=>__('Action is not registered.', 'elev8-os')];
                $failed = true; continue;
            }
            try {
                $result = call_user_func($registry[$type], $action, $context, $workflow_key, (int)$run_id, (int)$index);
                $ok = !is_wp_error($result) && $result !== false;
                $results[] = ['action'=>$type,'status'=>$ok ? 'completed' : 'failed','message'=>is_wp_error($result) ? $result->get_error_message() : ''];
                if (!$ok) { $failed = true; }
            } catch (Throwable $e) {
                $results[] = ['action'=>$type,'status'=>'failed','message'=>sanitize_text_field($e->getMessage())];
                $failed = true;
            }
        }
        update_post_meta($run_id, '_elev8_workflow_results', $results);
        update_post_meta($run_id, '_elev8_workflow_status', $failed ? 'failed' : 'completed');
        update_post_meta($run_id, '_elev8_workflow_finished_at', current_time('mysql'));
        do_action('elev8_os_workflow_completed', (int)$run_id, $workflow_key, $failed ? 'failed' : 'completed', $context, $results);
        return (int)$run_id;
    }

    public static function action_record_activity(array $action, array $context, string $workflow_key, int $run_id, int $index) {
        if (!class_exists('Elev8_OS_Activity_Service')) { return new WP_Error('activity_unavailable', __('Activity Service is unavailable.', 'elev8-os')); }
        $label = sanitize_text_field((string)($action['label'] ?? __('Workflow action completed', 'elev8-os')));
        return Elev8_OS_Activity_Service::record([
            'type'=>'workflow_action','label'=>$label,'details'=>sprintf(__('Workflow: %s', 'elev8-os'), $workflow_key),
            'object_id'=>absint($context['source_id'] ?? 0),'object_type'=>sanitize_key((string)($context['source_type'] ?? '')),
            'source'=>'workflow','actor_user_id'=>absint($context['actor_user_id'] ?? get_current_user_id()),
            'metadata'=>['workflow_run_id'=>$run_id,'action_index'=>$index],
        ]);
    }

    public static function action_create_work(array $action, array $context, string $workflow_key, int $run_id, int $index) {
        if (!class_exists('Elev8_OS_Work_Service')) { return new WP_Error('work_unavailable', __('Work Service is unavailable.', 'elev8-os')); }
        return Elev8_OS_Work_Service::create([
            'title'=>(string)($action['title'] ?? __('Workflow follow-up', 'elev8-os')),
            'description'=>(string)($action['description'] ?? ''),
            'owner_user_id'=>absint($action['owner_user_id'] ?? 0),
            'priority'=>(string)($action['priority'] ?? 'normal'),
            'due_date'=>(string)($action['due_date'] ?? ''),
            'source_type'=>sanitize_key((string)($context['source_type'] ?? 'workflow')),
            'source_id'=>absint($context['source_id'] ?? $run_id),
            'workflow_key'=>$workflow_key,
            'step_key'=>sanitize_key((string)($action['step_key'] ?? 'action_' . $index)),
        ]);
    }

    private static function existing_run(string $workflow_key, string $trigger, int $source_id): int {
        $ids = get_posts(['post_type'=>self::POST_TYPE,'post_status'=>'publish','posts_per_page'=>1,'fields'=>'ids','meta_query'=>[
            ['key'=>'_elev8_workflow_key','value'=>sanitize_key($workflow_key)],
            ['key'=>'_elev8_workflow_trigger','value'=>$trigger],
            ['key'=>'_elev8_workflow_source_id','value'=>$source_id,'type'=>'NUMERIC'],
        ]]);
        return $ids ? (int)$ids[0] : 0;
    }
}

<?php
if (!defined('ABSPATH')) { exit; }

/** Converts structured Daily Operations Logs into verified Observations. */
final class Elev8_OS_Daily_Operations_Observation_Contributor {
    public static function init(): void {
        add_action('elev8_os_operations_entry_created', [__CLASS__, 'capture'], 30, 3);
    }

    public static function capture(int $entry_id, string $template_key, array $values): void {
        if (!class_exists('Elev8_OS_Observation_Service')) { return; }
        $entry = get_post($entry_id);
        if (!$entry instanceof WP_Post) { return; }
        $meaningful = self::meaningful_fields($values);
        if (!$meaningful) { return; }

        $classification = self::classify($template_key, $meaningful);
        $requires_action = self::requires_action($template_key, $meaningful);
        $severity = self::severity($template_key, $meaningful, $requires_action);
        $organization = class_exists('Elev8_OS_Business_Graph_Registry_Service')
            ? Elev8_OS_Business_Graph_Registry_Service::organization_scope_for('manager_log', $entry_id)
            : 0;
        $summary = self::summary($meaningful);
        $observation_id = Elev8_OS_Observation_Service::upsert([
            'source_type' => Elev8_OS_Daily_Operations_Service::POST_TYPE,
            'source_id' => $entry_id,
            'source_key' => 'daily_operations_summary',
            'title' => sprintf(__('%1$s observation — %2$s', 'elev8-os'), ucfirst(str_replace('_',' ', $template_key)), get_the_date('', $entry_id)),
            'summary' => $summary,
            'classifications' => $classification,
            'severity' => $severity,
            'confidence' => 100,
            'tags' => array_values(array_unique(array_merge([$template_key, 'daily-operations'], $classification))),
            'organization_unit_id' => $organization,
            'author_user_id' => (int)$entry->post_author,
            'occurred_at' => (string)get_post_meta($entry_id, Elev8_OS_Daily_Operations_Service::META_ENTRY_DATE, true) ?: $entry->post_date,
            'requires_action' => $requires_action,
            'related_objects' => [['type'=>'manager_log','id'=>$entry_id]],
        ]);
        if (is_wp_error($observation_id) || !$requires_action) { return; }

        $work_id = self::ensure_follow_up_work($entry_id, $entry, $template_key, $summary, $severity, $organization);
        if ($work_id && !is_wp_error($work_id)) {
            update_post_meta((int)$observation_id, Elev8_OS_Observation_Service::META_WORK_ID, (int)$work_id);
        }
    }

    /** @return array<string,string> */
    private static function meaningful_fields(array $values): array {
        $ignored = ['start_time','end_time','shift','attendance','sales','vendor_count','hemp_sales','elev8_glass_gallery_sales','store_worked','class_taught','event_name'];
        $result = [];
        foreach ($values as $key => $value) {
            $text = trim((string)$value);
            if ($text === '' || $text === 'No' || in_array((string)$key, $ignored, true)) { continue; }
            $result[sanitize_key((string)$key)] = $text;
        }
        return $result;
    }

    /** @return array<int,string> */
    private static function classify(string $template, array $values): array {
        $keys = array_keys($values);
        $classes = ['information'];
        if (array_intersect($keys, ['problems_discovered','customer_issues','complaints','concern','inventory_low','supplies_low','equipment_issues','backorders','problems'])) { $classes[] = 'risk'; }
        if (array_intersect($keys, ['business_improvements','ideas','future_class_ideas','products_requested','customer_requests','request','new_products','suggestions','lessons_learned'])) { $classes[] = 'opportunity'; }
        if (array_intersect($keys, ['compliments','sales_wins','problems_solved'])) { $classes[] = 'achievement'; }
        if (array_intersect($keys, ['follow_up_needed','owner_attention_items','manager_assistance','equipment_issues','inventory_low','supplies_low','backorders'])) { $classes[] = 'follow_up'; $classes[] = 'operational_action'; }
        if ($template === 'maintenance') { $classes[] = 'risk'; $classes[] = 'operational_action'; }
        return array_values(array_unique($classes));
    }

    private static function requires_action(string $template, array $values): bool {
        if ($template === 'maintenance') { return true; }
        return (bool)array_intersect(array_keys($values), ['follow_up_needed','owner_attention_items','manager_assistance','equipment_issues','inventory_low','supplies_low','backorders']);
    }

    private static function severity(string $template, array $values, bool $requires_action): string {
        $priority = strtolower((string)($values['priority'] ?? ''));
        if (in_array($priority, ['urgent','critical'], true)) { return 'critical'; }
        if ($priority === 'high' || !empty($values['owner_attention_items']) || !empty($values['equipment_issues'])) { return 'high'; }
        return $requires_action ? 'normal' : 'low';
    }

    private static function summary(array $values): string {
        $parts = [];
        foreach ($values as $key => $value) {
            $label = ucwords(str_replace('_', ' ', $key));
            $parts[] = $label . ': ' . $value;
        }
        return implode("\n", $parts);
    }

    private static function ensure_follow_up_work(int $entry_id, WP_Post $entry, string $template, string $summary, string $severity, int $organization) {
        if (!class_exists('Elev8_OS_Operations_Engine_Service') || !class_exists('Elev8_OS_Work_Service')) { return 0; }
        $existing = get_posts([
            'post_type'=>Elev8_OS_Work_Service::POST_TYPE,'post_status'=>'publish','posts_per_page'=>1,'fields'=>'ids',
            'meta_query'=>[
                ['key'=>'_elev8_work_source_type','value'=>Elev8_OS_Daily_Operations_Service::POST_TYPE],
                ['key'=>'_elev8_work_source_id','value'=>$entry_id,'type'=>'NUMERIC'],
                ['key'=>'_elev8_work_step_key','value'=>'explicit_follow_up'],
            ],
        ]);
        if ($existing) { return (int)$existing[0]; }
        $priority = $severity === 'critical' ? 'urgent' : ($severity === 'high' ? 'high' : 'normal');
        return Elev8_OS_Operations_Engine_Service::create_work([
            'title' => sprintf(__('%s log follow-up', 'elev8-os'), ucfirst(str_replace('_',' ', $template))),
            'description' => $summary,
            'type' => $template === 'maintenance' ? 'maintenance' : 'general',
            'owner_user_id' => 0,
            'due_date' => wp_date('Y-m-d', strtotime('+1 day', current_time('timestamp'))),
            'priority' => $priority,
            'status' => 'requested',
            'source_type' => Elev8_OS_Daily_Operations_Service::POST_TYPE,
            'source_id' => $entry_id,
            'workflow_key' => 'daily_operations_observation',
            'step_key' => 'explicit_follow_up',
            'organization_unit_id' => $organization,
            'requested_by_user_id' => (int)$entry->post_author,
        ]);
    }
}

<?php
/**
 * Administrator-confirmed plugin ownership and migration plans.
 *
 * @package Elev8OS
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Elev8_OS_Plugin_Migration_Plan_Service {

    public const POST_TYPE = 'elev8_plugin_plan';

    public static function init(): void {
        add_action('init', [__CLASS__, 'register_post_type']);
    }

    public static function register_post_type(): void {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name' => __('Plugin Migration Plans', 'elev8-os'),
                'singular_name' => __('Plugin Migration Plan', 'elev8-os'),
            ],
            'public' => false,
            'show_ui' => false,
            'show_in_menu' => false,
            'supports' => ['title', 'author'],
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ]);
    }

    /** @return array<string,string> */
    public static function ownership_statuses(): array {
        return [
            'unconfirmed' => __('Unconfirmed', 'elev8-os'),
            'confirmed_external' => __('Confirmed external authority', 'elev8-os'),
            'shared_boundary' => __('Shared boundary', 'elev8-os'),
            'elev8_replacement' => __('Elev8 OS replacement planned', 'elev8-os'),
            'retirement_candidate' => __('Retirement candidate', 'elev8-os'),
        ];
    }

    /** @return array<string,string> */
    public static function stages(): array {
        return [
            'discovery' => __('Discovery', 'elev8-os'),
            'ownership_confirmed' => __('Ownership confirmed', 'elev8-os'),
            'migration_planned' => __('Migration planned', 'elev8-os'),
            'local_rehearsal' => __('Local rehearsal', 'elev8-os'),
            'blocked' => __('Blocked', 'elev8-os'),
            'ready_for_approval' => __('Ready for final approval', 'elev8-os'),
            'approved_for_retirement' => __('Approved for retirement', 'elev8-os'),
            'retired' => __('Retired', 'elev8-os'),
        ];
    }

    /** @return array<string,string> */
    public static function engines(): array {
        return [
            '' => __('Not selected', 'elev8-os'),
            'organization' => __('Organization', 'elev8-os'),
            'commerce' => __('Commerce', 'elev8-os'),
            'sales' => __('Sales', 'elev8-os'),
            'marketing' => __('Marketing', 'elev8-os'),
            'operations' => __('Operations', 'elev8-os'),
            'communication' => __('Communication', 'elev8-os'),
            'booking' => __('Booking', 'elev8-os'),
            'financial' => __('Financial', 'elev8-os'),
            'intelligence' => __('Intelligence', 'elev8-os'),
            'identity' => __('Identity', 'elev8-os'),
            'crm' => __('CRM', 'elev8-os'),
            'membership_benefits' => __('Membership & Benefits', 'elev8-os'),
            'knowledge' => __('Knowledge', 'elev8-os'),
            'inventory' => __('Inventory', 'elev8-os'),
            'assets' => __('Assets', 'elev8-os'),
            'workflow' => __('Workflow', 'elev8-os'),
            'automation' => __('Automation', 'elev8-os'),
            'analytics' => __('Analytics', 'elev8-os'),
            'integrations' => __('Integrations', 'elev8-os'),
            'events' => __('Events', 'elev8-os'),
        ];
    }

    /** @return array<string,mixed>|null */
    public static function get_by_plugin(string $plugin_file): ?array {
        $posts = get_posts([
            'post_type' => self::POST_TYPE,
            'post_status' => 'any',
            'numberposts' => 1,
            'meta_key' => '_elev8_plugin_file',
            'meta_value' => $plugin_file,
            'orderby' => 'ID',
            'order' => 'ASC',
        ]);
        if (!$posts) {
            return null;
        }
        return self::hydrate($posts[0]);
    }

    /** @return array<int,array<string,mixed>> */
    public static function all(): array {
        $posts = get_posts([
            'post_type' => self::POST_TYPE,
            'post_status' => 'any',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
        return array_map([__CLASS__, 'hydrate'], $posts);
    }

    /**
     * @param array<string,mixed> $data
     * @return int|WP_Error
     */
    public static function save(array $data) {
        $plugin_file = isset($data['plugin_file']) ? sanitize_text_field((string) $data['plugin_file']) : '';
        if ($plugin_file === '') {
            return new WP_Error('elev8_missing_plugin', __('A plugin must be selected.', 'elev8-os'));
        }

        $existing = self::get_by_plugin($plugin_file);
        $post_id = $existing ? (int) $existing['id'] : 0;
        $title = isset($data['plugin_name']) && $data['plugin_name'] !== ''
            ? sanitize_text_field((string) $data['plugin_name'])
            : $plugin_file;

        $post = [
            'ID' => $post_id,
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'post_title' => $title,
            'post_author' => get_current_user_id(),
        ];
        $post_id = $post_id ? wp_update_post($post, true) : wp_insert_post($post, true);
        if (is_wp_error($post_id)) {
            return $post_id;
        }

        $allowed_ownership = array_keys(self::ownership_statuses());
        $allowed_stages = array_keys(self::stages());
        $allowed_engines = array_keys(self::engines());
        $ownership = sanitize_key((string) ($data['ownership_status'] ?? 'unconfirmed'));
        $stage = sanitize_key((string) ($data['stage'] ?? 'discovery'));
        $engine = sanitize_key((string) ($data['replacement_engine'] ?? ''));

        update_post_meta($post_id, '_elev8_plugin_file', $plugin_file);
        update_post_meta($post_id, '_elev8_plugin_name', $title);
        update_post_meta($post_id, '_elev8_ownership_status', in_array($ownership, $allowed_ownership, true) ? $ownership : 'unconfirmed');
        update_post_meta($post_id, '_elev8_migration_stage', in_array($stage, $allowed_stages, true) ? $stage : 'discovery');
        update_post_meta($post_id, '_elev8_current_owner', sanitize_textarea_field((string) ($data['current_owner'] ?? '')));
        update_post_meta($post_id, '_elev8_replacement_engine', in_array($engine, $allowed_engines, true) ? $engine : '');
        update_post_meta($post_id, '_elev8_capabilities_owned', sanitize_textarea_field((string) ($data['capabilities_owned'] ?? '')));
        update_post_meta($post_id, '_elev8_authoritative_data', sanitize_textarea_field((string) ($data['authoritative_data'] ?? '')));
        update_post_meta($post_id, '_elev8_data_migration', sanitize_textarea_field((string) ($data['data_migration'] ?? '')));
        update_post_meta($post_id, '_elev8_pages_workflows', sanitize_textarea_field((string) ($data['pages_workflows'] ?? '')));
        update_post_meta($post_id, '_elev8_external_dependencies', sanitize_textarea_field((string) ($data['external_dependencies'] ?? '')));
        update_post_meta($post_id, '_elev8_retirement_blockers', sanitize_textarea_field((string) ($data['retirement_blockers'] ?? '')));
        update_post_meta($post_id, '_elev8_local_rehearsal', sanitize_textarea_field((string) ($data['local_rehearsal'] ?? '')));
        update_post_meta($post_id, '_elev8_rollback_plan', sanitize_textarea_field((string) ($data['rollback_plan'] ?? '')));
        update_post_meta($post_id, '_elev8_validation_results', sanitize_textarea_field((string) ($data['validation_results'] ?? '')));
        update_post_meta($post_id, '_elev8_final_approval_notes', sanitize_textarea_field((string) ($data['final_approval_notes'] ?? '')));
        update_post_meta($post_id, '_elev8_updated_by', get_current_user_id());
        update_post_meta($post_id, '_elev8_updated_at_utc', gmdate('c'));

        return (int) $post_id;
    }

    /** @return array<string,mixed> */
    public static function readiness(array $plan): array {
        $required = [
            'ownership_status' => __('Ownership status', 'elev8-os'),
            'current_owner' => __('Current capability owner', 'elev8-os'),
            'capabilities_owned' => __('Capabilities owned', 'elev8-os'),
            'authoritative_data' => __('Authoritative data', 'elev8-os'),
            'pages_workflows' => __('Pages and workflows', 'elev8-os'),
            'retirement_blockers' => __('Retirement blockers', 'elev8-os'),
            'rollback_plan' => __('Rollback plan', 'elev8-os'),
        ];
        $missing = [];
        foreach ($required as $key => $label) {
            if (empty($plan[$key]) || ($key === 'ownership_status' && $plan[$key] === 'unconfirmed')) {
                $missing[] = $label;
            }
        }
        if (($plan['ownership_status'] ?? '') === 'elev8_replacement' || ($plan['ownership_status'] ?? '') === 'retirement_candidate') {
            foreach (['replacement_engine' => __('Replacement Engine', 'elev8-os'), 'data_migration' => __('Data migration', 'elev8-os')] as $key => $label) {
                if (empty($plan[$key])) {
                    $missing[] = $label;
                }
            }
        }
        return [
            'complete' => !$missing,
            'missing' => $missing,
            'percent' => (int) round((1 - (count($missing) / max(1, count($required) + 2))) * 100),
        ];
    }

    /** @return array<string,mixed> */
    private static function hydrate(WP_Post $post): array {
        $map = [
            'plugin_file' => '_elev8_plugin_file',
            'plugin_name' => '_elev8_plugin_name',
            'ownership_status' => '_elev8_ownership_status',
            'stage' => '_elev8_migration_stage',
            'current_owner' => '_elev8_current_owner',
            'replacement_engine' => '_elev8_replacement_engine',
            'capabilities_owned' => '_elev8_capabilities_owned',
            'authoritative_data' => '_elev8_authoritative_data',
            'data_migration' => '_elev8_data_migration',
            'pages_workflows' => '_elev8_pages_workflows',
            'external_dependencies' => '_elev8_external_dependencies',
            'retirement_blockers' => '_elev8_retirement_blockers',
            'local_rehearsal' => '_elev8_local_rehearsal',
            'rollback_plan' => '_elev8_rollback_plan',
            'validation_results' => '_elev8_validation_results',
            'final_approval_notes' => '_elev8_final_approval_notes',
            'updated_by' => '_elev8_updated_by',
            'updated_at_utc' => '_elev8_updated_at_utc',
        ];
        $result = ['id' => $post->ID, 'title' => $post->post_title];
        foreach ($map as $key => $meta_key) {
            $result[$key] = get_post_meta($post->ID, $meta_key, true);
        }
        return $result;
    }
}

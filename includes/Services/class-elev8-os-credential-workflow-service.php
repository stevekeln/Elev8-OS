<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Credential requirements on canonical Work Items and governed renewal work.
 *
 * Requirements are coordination evidence only. They never grant access,
 * certify legal competence, or automatically assign a Work Item.
 */
final class Elev8_OS_Credential_Workflow_Service {
    public const META_REQUIREMENTS = '_elev8_work_credential_requirements';
    public const META_RENEWAL_WORK_ID = '_elev8_credential_renewal_work_id';
    private const CRON_HOOK = 'elev8_os_credential_workflow_scan';

    public static function init(): void {
        add_filter('elev8_os_business_graph_objects', [__CLASS__, 'register_graph_objects']);
        add_action('init', [__CLASS__, 'schedule_scan']);
        add_action(self::CRON_HOOK, [__CLASS__, 'run_renewal_workflow_scan']);
    }

    public static function schedule_scan(): void {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + (2 * HOUR_IN_SECONDS), 'daily', self::CRON_HOOK);
        }
    }

    /** @return string[] */
    public static function requirements(int $work_id): array {
        $saved = (array) get_post_meta($work_id, self::META_REQUIREMENTS, true);
        $clean = [];
        foreach ($saved as $value) {
            $value = sanitize_text_field(strtolower(trim((string) $value)));
            if ($value !== '') { $clean[] = $value; }
        }
        return array_values(array_unique($clean));
    }

    public static function save_requirements(int $work_id, array $requirements) {
        if (!Elev8_OS_Team_Coordination_Service::can_change_work($work_id)) {
            return new WP_Error('forbidden', __('You cannot change credential requirements for this Work Item.', 'elev8-os'));
        }
        $clean = [];
        foreach ($requirements as $value) {
            foreach (preg_split('/[\r\n,]+/', (string) $value) ?: [] as $part) {
                $part = sanitize_text_field(strtolower(trim($part)));
                if ($part !== '') { $clean[] = $part; }
            }
        }
        $clean = array_values(array_unique($clean));
        update_post_meta($work_id, self::META_REQUIREMENTS, $clean);
        do_action('elev8_os_work_credential_requirements_changed', $work_id, $clean);
        return true;
    }

    /** @return array<string,mixed> */
    public static function readiness(int $work_id, int $user_id): array {
        $required = self::requirements($work_id);
        $credentials = Elev8_OS_Team_Coordination_Evidence_Service::credentials($user_id);
        $matched = [];
        $missing = [];
        $expired = [];
        foreach ($required as $requirement) {
            $found = false;
            $found_expired = false;
            foreach ($credentials as $credential) {
                $haystack = strtolower(trim(implode(' ', [
                    (string) ($credential['title'] ?? ''),
                    (string) ($credential['skill'] ?? ''),
                    (string) ($credential['issuer'] ?? ''),
                ])));
                if ($haystack === '' || strpos($haystack, $requirement) === false) { continue; }
                if (($credential['status'] ?? '') === 'active') {
                    $matched[] = $requirement;
                    $found = true;
                    break;
                }
                $found_expired = true;
            }
            if (!$found) {
                if ($found_expired) { $expired[] = $requirement; }
                else { $missing[] = $requirement; }
            }
        }
        return [
            'required' => $required,
            'matched' => array_values(array_unique($matched)),
            'missing' => array_values(array_unique($missing)),
            'expired' => array_values(array_unique($expired)),
            'ready' => !$missing && !$expired,
            'explanation' => !$required
                ? __('No credential evidence is declared for this Work Item.', 'elev8-os')
                : sprintf(__('Matched %1$d of %2$d declared credential requirements. This is coordination evidence only.', 'elev8-os'), count(array_unique($matched)), count($required)),
        ];
    }

    public static function run_renewal_workflow_scan(): void {
        if (!class_exists('Elev8_OS_Team_Coordination_Evidence_Service') || !class_exists('Elev8_OS_Work_Service')) { return; }
        $today = current_time('Y-m-d');
        foreach (Elev8_OS_Team_Coordination_Service::assignable_users() as $user) {
            foreach (Elev8_OS_Team_Coordination_Evidence_Service::credentials((int) $user->ID) as $credential) {
                if (empty($credential['expires_on'])) { continue; }
                $days = (int) floor((strtotime((string) $credential['expires_on']) - strtotime($today)) / DAY_IN_SECONDS);
                if ($days > (int) ($credential['renewal_days'] ?? 30)) { continue; }
                self::ensure_renewal_work((int) $user->ID, $credential);
            }
        }
    }

    private static function ensure_renewal_work(int $user_id, array $credential): void {
        $credential_id = sanitize_key((string) ($credential['id'] ?? ''));
        if (!$credential_id) { return; }
        $source_id = absint(sprintf('%u', crc32($user_id . ':' . $credential_id)));
        $due_date = sanitize_text_field((string) ($credential['expires_on'] ?? ''));
        $priority = (($credential['status'] ?? '') === 'expired') ? 'urgent' : 'high';
        $work_id = Elev8_OS_Work_Service::create([
            'title' => sprintf(__('Renew credential: %s', 'elev8-os'), (string) ($credential['title'] ?? __('Credential', 'elev8-os'))),
            'description' => sprintf(__('Review and renew the credential evidence before %1$s. Use only safe evidence references; never store passwords, secret keys, access codes, license keys, or full credential numbers.', 'elev8-os'), $due_date ?: __('the renewal deadline', 'elev8-os')),
            'owner_user_id' => $user_id,
            'due_date' => $due_date,
            'priority' => $priority,
            'status' => 'assigned',
            'source_type' => 'credential_evidence',
            'source_id' => $source_id,
            'workflow_key' => 'credential_renewal',
            'step_key' => 'renew',
            'work_type' => 'general',
            'requested_by_user_id' => $user_id,
        ]);
        if (!is_wp_error($work_id)) {
            update_post_meta((int) $work_id, '_elev8_credential_user_id', $user_id);
            update_post_meta((int) $work_id, '_elev8_credential_evidence_id', $credential_id);
        }
    }

    public static function register_graph_objects(array $objects): array {
        $objects['work_credential_requirement'] = [
            'label' => __('Work Credential Requirement', 'elev8-os'),
            'engine' => 'Workflow',
            'organization_scoped' => true,
            'notes' => 'A declared evidence requirement on a canonical Work Item; it is not access control or professional licensing authority.',
        ];
        $objects['credential_renewal_workflow'] = [
            'label' => __('Credential Renewal Workflow', 'elev8-os'),
            'engine' => 'Operations',
            'organization_scoped' => true,
            'notes' => 'A governed renewal follow-up contributed to Universal Work from expiring credential evidence.',
        ];
        return $objects;
    }
}

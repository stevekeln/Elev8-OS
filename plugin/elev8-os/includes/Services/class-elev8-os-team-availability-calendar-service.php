<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Recurring coordination availability and manager-confirmed skill evidence.
 *
 * This is not attendance, payroll, leave, scheduling, or certification. It is
 * bounded coordination evidence used to explain handoff fit.
 */
final class Elev8_OS_Team_Availability_Calendar_Service {
    public const USER_META_WINDOWS = '_elev8_work_availability_windows';
    public const USER_META_SKILL_VERIFICATION = '_elev8_work_skill_verification';

    private const DAYS = ['mon','tue','wed','thu','fri','sat','sun'];

    public static function init(): void {
        add_filter('elev8_os_business_graph_objects', [__CLASS__, 'register_graph_objects']);
    }

    /** @return array<string,array<int,array{start:string,end:string}>> */
    public static function windows(int $user_id): array {
        $saved = (array) get_user_meta($user_id, self::USER_META_WINDOWS, true);
        $result = [];
        foreach (self::DAYS as $day) {
            $result[$day] = [];
            foreach ((array) ($saved[$day] ?? []) as $window) {
                if (!is_array($window)) { continue; }
                $start = self::clean_time((string) ($window['start'] ?? ''));
                $end = self::clean_time((string) ($window['end'] ?? ''));
                if ($start && $end && $start < $end) { $result[$day][] = ['start' => $start, 'end' => $end]; }
            }
        }
        return $result;
    }

    public static function save_windows(int $user_id, string $definition) {
        $actor = wp_get_current_user();
        if ((int) $actor->ID !== $user_id && !Elev8_OS_Team_Coordination_Service::can_coordinate($actor)) {
            return new WP_Error('forbidden', __('You cannot change this person’s recurring coordination availability.', 'elev8-os'));
        }
        $user = get_user_by('id', $user_id);
        if (!$user instanceof WP_User || !Elev8_OS_Access_Service::can_receive_assignment($user)) {
            return new WP_Error('invalid_user', __('The selected person cannot receive operational assignments.', 'elev8-os'));
        }
        $parsed = array_fill_keys(self::DAYS, []);
        foreach (preg_split('/\r?\n/', $definition) ?: [] as $line) {
            $line = strtolower(trim($line));
            if ($line === '') { continue; }
            if (!preg_match('/^(mon|tue|wed|thu|fri|sat|sun)\s+(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})$/', $line, $matches)) {
                return new WP_Error('invalid_window', sprintf(__('Invalid availability line: %s. Use “mon 09:00-17:00”.', 'elev8-os'), $line));
            }
            $start = self::clean_time($matches[2]); $end = self::clean_time($matches[3]);
            if (!$start || !$end || $start >= $end) {
                return new WP_Error('invalid_window', sprintf(__('Invalid availability window: %s.', 'elev8-os'), $line));
            }
            $parsed[$matches[1]][] = ['start' => $start, 'end' => $end];
        }
        update_user_meta($user_id, self::USER_META_WINDOWS, $parsed);
        do_action('elev8_os_team_availability_calendar_saved', $user_id, $parsed);
        return true;
    }

    public static function windows_text(int $user_id): string {
        $lines = [];
        foreach (self::windows($user_id) as $day => $windows) {
            foreach ($windows as $window) { $lines[] = $day . ' ' . $window['start'] . '-' . $window['end']; }
        }
        return implode("\n", $lines);
    }

    /** @return array<string,array<string,mixed>> */
    public static function skill_verifications(int $user_id): array {
        $saved = (array) get_user_meta($user_id, self::USER_META_SKILL_VERIFICATION, true);
        $clean = [];
        foreach ($saved as $skill => $evidence) {
            $key = sanitize_text_field(strtolower((string) $skill));
            if ($key === '' || !is_array($evidence)) { continue; }
            $clean[$key] = [
                'status' => in_array(($evidence['status'] ?? ''), ['verified','not_verified'], true) ? $evidence['status'] : 'unreviewed',
                'verified_by_user_id' => absint($evidence['verified_by_user_id'] ?? 0),
                'verified_at' => sanitize_text_field((string) ($evidence['verified_at'] ?? '')),
                'note' => sanitize_textarea_field((string) ($evidence['note'] ?? '')),
                'expires_on' => sanitize_text_field((string) ($evidence['expires_on'] ?? '')),
            ];
        }
        return $clean;
    }

    public static function verify_skills(int $user_id, array $verified_skills, string $note = '', string $expires_on = '') {
        if (!Elev8_OS_Team_Coordination_Service::can_coordinate()) {
            return new WP_Error('forbidden', __('Only an operational leader can verify coordination skills.', 'elev8-os'));
        }
        $declared = Elev8_OS_Team_Availability_Skill_Service::skills($user_id);
        $selected = array_map('strtolower', array_map('sanitize_text_field', $verified_skills));
        $evidence = self::skill_verifications($user_id);
        foreach ($declared as $skill) {
            $evidence[$skill] = [
                'status' => in_array($skill, $selected, true) ? 'verified' : 'unreviewed',
                'verified_by_user_id' => in_array($skill, $selected, true) ? get_current_user_id() : 0,
                'verified_at' => in_array($skill, $selected, true) ? current_time('mysql') : '',
                'note' => sanitize_textarea_field($note),
                'expires_on' => sanitize_text_field($expires_on),
            ];
        }
        update_user_meta($user_id, self::USER_META_SKILL_VERIFICATION, $evidence);
        do_action('elev8_os_team_skills_verified', $user_id, $evidence);
        return true;
    }

    /** @return string[] */
    public static function verified_skills(int $user_id): array {
        $verified = [];
        foreach (self::skill_verifications($user_id) as $skill => $evidence) {
            if (($evidence['status'] ?? '') === 'verified' && (empty($evidence['expires_on']) || $evidence['expires_on'] >= current_time('Y-m-d'))) { $verified[] = $skill; }
        }
        return $verified;
    }

    public static function work_availability(int $user_id, int $work_id): array {
        $base = Elev8_OS_Team_Availability_Skill_Service::availability($user_id);
        $due = sanitize_text_field((string) get_post_meta($work_id, '_elev8_work_due_date', true));
        $windows = self::windows($user_id);
        $has_schedule = count(array_filter($windows)) > 0;
        if ($base['state'] === 'unavailable') {
            return ['eligible' => false, 'state' => 'unavailable', 'due_date' => $due, 'explanation' => __('The person is explicitly marked unavailable.', 'elev8-os')];
        }
        if (!$due || !$has_schedule) {
            return ['eligible' => true, 'state' => $base['state'], 'due_date' => $due, 'explanation' => $has_schedule ? __('The Work Item has no due date to compare with recurring availability.', 'elev8-os') : __('No recurring availability calendar is recorded.', 'elev8-os')];
        }
        if (class_exists('Elev8_OS_Team_Coordination_Evidence_Service')) {
            $exception = Elev8_OS_Team_Coordination_Evidence_Service::exception_for($user_id, $due);
            if ($exception) {
                $eligible = $exception['state'] !== 'unavailable';
                return [
                    'eligible' => $eligible,
                    'state' => $exception['state'],
                    'due_date' => $due,
                    'exception' => $exception,
                    'explanation' => sprintf(__('A date-specific availability exception marks this person %1$s on %2$s%3$s.', 'elev8-os'), $exception['state'], $due, !empty($exception['note']) ? ': ' . $exception['note'] : ''),
                ];
            }
        }
        $timestamp = strtotime($due . ' 12:00:00');
        $day = $timestamp ? strtolower(substr(date('D', $timestamp), 0, 3)) : '';
        $day_windows = (array) ($windows[$day] ?? []);
        return [
            'eligible' => !empty($day_windows),
            'state' => !empty($day_windows) ? $base['state'] : 'schedule_conflict',
            'due_date' => $due,
            'day' => $day,
            'windows' => $day_windows,
            'explanation' => !empty($day_windows)
                ? sprintf(__('Recurring availability exists on %1$s for the Work Item due date %2$s.', 'elev8-os'), strtoupper($day), $due)
                : sprintf(__('No recurring availability is recorded on %1$s, the Work Item due date.', 'elev8-os'), strtoupper($day)),
        ];
    }

    private static function clean_time(string $time): string {
        if (!preg_match('/^(\d{1,2}):(\d{2})$/', trim($time), $matches)) { return ''; }
        $hour = (int) $matches[1]; $minute = (int) $matches[2];
        if ($hour > 23 || $minute > 59) { return ''; }
        return sprintf('%02d:%02d', $hour, $minute);
    }

    public static function register_graph_objects(array $objects): array {
        $objects['work_availability_window'] = [
            'label' => __('Work Availability Window', 'elev8-os'),
            'engine' => 'Organization',
            'organization_scoped' => true,
            'notes' => 'Recurring coordination availability used for conflict-aware suggestions; not attendance, payroll, leave, or booking availability.',
        ];
        $objects['skill_verification_evidence'] = [
            'label' => __('Skill Verification Evidence', 'elev8-os'),
            'engine' => 'Organization',
            'organization_scoped' => true,
            'notes' => 'Manager confirmation that a declared coordination skill has been reviewed; not a professional license or access grant.',
        ];
        return $objects;
    }
}

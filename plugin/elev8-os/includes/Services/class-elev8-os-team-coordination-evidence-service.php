<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Date-specific availability exceptions and bounded credential evidence.
 *
 * This service never stores passwords, secret values, credential numbers, or
 * document contents. It stores coordination-safe references and renewal dates.
 */
final class Elev8_OS_Team_Coordination_Evidence_Service {
    public const USER_META_EXCEPTIONS = '_elev8_work_availability_exceptions';
    public const USER_META_CREDENTIALS = '_elev8_work_credential_evidence';
    private const CRON_HOOK = 'elev8_os_coordination_credential_renewal_scan';

    public static function init(): void {
        add_filter('elev8_os_business_graph_objects', [__CLASS__, 'register_graph_objects']);
        add_action('init', [__CLASS__, 'schedule_scan']);
        add_action(self::CRON_HOOK, [__CLASS__, 'run_renewal_scan']);
    }

    public static function schedule_scan(): void {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK);
        }
    }

    /** @return array<int,array<string,string>> */
    public static function exceptions(int $user_id): array {
        $saved = (array) get_user_meta($user_id, self::USER_META_EXCEPTIONS, true);
        $clean = [];
        foreach ($saved as $item) {
            if (!is_array($item)) { continue; }
            $date = self::clean_date((string) ($item['date'] ?? ''));
            $state = sanitize_key((string) ($item['state'] ?? 'unavailable'));
            if (!$date || !in_array($state, ['available','limited','unavailable'], true)) { continue; }
            $clean[] = [
                'date' => $date,
                'state' => $state,
                'note' => sanitize_textarea_field((string) ($item['note'] ?? '')),
                'updated_at' => sanitize_text_field((string) ($item['updated_at'] ?? '')),
            ];
        }
        usort($clean, static fn(array $a, array $b): int => strcmp($a['date'], $b['date']));
        return $clean;
    }

    public static function save_exception(int $user_id, string $date, string $state, string $note = '') {
        $actor = wp_get_current_user();
        if ((int) $actor->ID !== $user_id && !Elev8_OS_Team_Coordination_Service::can_coordinate($actor)) {
            return new WP_Error('forbidden', __('You cannot change this person’s availability exceptions.', 'elev8-os'));
        }
        $date = self::clean_date($date);
        $state = sanitize_key($state);
        if (!$date || !in_array($state, ['available','limited','unavailable'], true)) {
            return new WP_Error('invalid_exception', __('Enter a valid date and availability state.', 'elev8-os'));
        }
        $items = array_values(array_filter(self::exceptions($user_id), static fn(array $item): bool => $item['date'] !== $date));
        $items[] = ['date' => $date, 'state' => $state, 'note' => sanitize_textarea_field($note), 'updated_at' => current_time('mysql')];
        update_user_meta($user_id, self::USER_META_EXCEPTIONS, $items);
        do_action('elev8_os_team_availability_exception_saved', $user_id, $date, $state);
        return true;
    }

    public static function delete_exception(int $user_id, string $date) {
        $actor = wp_get_current_user();
        if ((int) $actor->ID !== $user_id && !Elev8_OS_Team_Coordination_Service::can_coordinate($actor)) {
            return new WP_Error('forbidden', __('You cannot change this person’s availability exceptions.', 'elev8-os'));
        }
        $date = self::clean_date($date);
        update_user_meta($user_id, self::USER_META_EXCEPTIONS, array_values(array_filter(self::exceptions($user_id), static fn(array $item): bool => $item['date'] !== $date)));
        return true;
    }

    public static function exception_for(int $user_id, string $date): ?array {
        $date = self::clean_date($date);
        foreach (self::exceptions($user_id) as $item) {
            if ($item['date'] === $date) { return $item; }
        }
        return null;
    }

    /** @return array<int,array<string,mixed>> */
    public static function credentials(int $user_id): array {
        $saved = (array) get_user_meta($user_id, self::USER_META_CREDENTIALS, true);
        $clean = [];
        foreach ($saved as $item) {
            if (!is_array($item)) { continue; }
            $id = sanitize_key((string) ($item['id'] ?? ''));
            $title = sanitize_text_field((string) ($item['title'] ?? ''));
            if (!$id || !$title) { continue; }
            $expires_on = self::clean_date((string) ($item['expires_on'] ?? ''));
            $clean[] = [
                'id' => $id,
                'title' => $title,
                'skill' => sanitize_text_field(strtolower((string) ($item['skill'] ?? ''))),
                'issuer' => sanitize_text_field((string) ($item['issuer'] ?? '')),
                'expires_on' => $expires_on,
                'reference' => sanitize_text_field((string) ($item['reference'] ?? '')),
                'attachment_id' => absint($item['attachment_id'] ?? 0),
                'renewal_days' => max(1, min(365, absint($item['renewal_days'] ?? 30))),
                'status' => self::credential_status($expires_on),
                'recorded_by_user_id' => absint($item['recorded_by_user_id'] ?? 0),
                'recorded_at' => sanitize_text_field((string) ($item['recorded_at'] ?? '')),
                'last_reminded_on' => self::clean_date((string) ($item['last_reminded_on'] ?? '')),
            ];
        }
        return $clean;
    }

    public static function save_credential(int $user_id, array $data) {
        if (!Elev8_OS_Team_Coordination_Service::can_coordinate()) {
            return new WP_Error('forbidden', __('Only an operational leader can record credential evidence.', 'elev8-os'));
        }
        $user = get_user_by('id', $user_id);
        if (!$user instanceof WP_User || !Elev8_OS_Access_Service::can_receive_assignment($user)) {
            return new WP_Error('invalid_user', __('The selected person cannot receive operational assignments.', 'elev8-os'));
        }
        $title = sanitize_text_field((string) ($data['title'] ?? ''));
        if ($title === '') { return new WP_Error('missing_title', __('Enter a credential or training title.', 'elev8-os')); }
        $id = sanitize_key((string) ($data['id'] ?? '')) ?: wp_generate_uuid4();
        $items = array_values(array_filter(self::credentials($user_id), static fn(array $item): bool => $item['id'] !== $id));
        $items[] = [
            'id' => $id,
            'title' => $title,
            'skill' => sanitize_text_field(strtolower((string) ($data['skill'] ?? ''))),
            'issuer' => sanitize_text_field((string) ($data['issuer'] ?? '')),
            'expires_on' => self::clean_date((string) ($data['expires_on'] ?? '')),
            'reference' => sanitize_text_field((string) ($data['reference'] ?? '')),
            'attachment_id' => absint($data['attachment_id'] ?? 0),
            'renewal_days' => max(1, min(365, absint($data['renewal_days'] ?? 30))),
            'recorded_by_user_id' => get_current_user_id(),
            'recorded_at' => current_time('mysql'),
            'last_reminded_on' => '',
        ];
        update_user_meta($user_id, self::USER_META_CREDENTIALS, $items);
        do_action('elev8_os_team_credential_evidence_saved', $user_id, $id);
        return true;
    }

    public static function delete_credential(int $user_id, string $credential_id) {
        if (!Elev8_OS_Team_Coordination_Service::can_coordinate()) {
            return new WP_Error('forbidden', __('Only an operational leader can remove credential evidence.', 'elev8-os'));
        }
        $credential_id = sanitize_key($credential_id);
        update_user_meta($user_id, self::USER_META_CREDENTIALS, array_values(array_filter(self::credentials($user_id), static fn(array $item): bool => $item['id'] !== $credential_id)));
        return true;
    }

    public static function active_credential_skills(int $user_id): array {
        $skills = [];
        foreach (self::credentials($user_id) as $credential) {
            if (($credential['status'] ?? '') === 'active' && !empty($credential['skill'])) { $skills[] = (string) $credential['skill']; }
        }
        return array_values(array_unique($skills));
    }

    public static function run_renewal_scan(): void {
        if (!class_exists('Elev8_OS_Team_Coordination_Service') || !class_exists('Elev8_OS_Notification_Service')) { return; }
        $today = current_time('Y-m-d');
        foreach (Elev8_OS_Team_Coordination_Service::assignable_users() as $user) {
            $changed = false;
            $credentials = self::credentials((int) $user->ID);
            foreach ($credentials as &$credential) {
                if (empty($credential['expires_on'])) { continue; }
                $days = (int) floor((strtotime($credential['expires_on']) - strtotime($today)) / DAY_IN_SECONDS);
                if ($days > (int) $credential['renewal_days'] || ($credential['last_reminded_on'] ?? '') === $today) { continue; }
                $subject = sprintf(__('Elev8 OS credential renewal: %s', 'elev8-os'), $credential['title']);
                $message = sprintf(__('%1$s is due to expire on %2$s. Review the secure reference in Elev8 OS Team Coordination. Do not send passwords or secret credential values by email.', 'elev8-os'), $credential['title'], $credential['expires_on']);
                if ($user->user_email && Elev8_OS_Notification_Service::send_email($user->user_email, $subject, $message)) {
                    $credential['last_reminded_on'] = $today; $changed = true;
                }
            }
            unset($credential);
            if ($changed) { update_user_meta((int) $user->ID, self::USER_META_CREDENTIALS, $credentials); }
        }
    }

    private static function credential_status(string $expires_on): string {
        if (!$expires_on) { return 'active'; }
        return $expires_on < current_time('Y-m-d') ? 'expired' : 'active';
    }

    private static function clean_date(string $date): string {
        $date = sanitize_text_field($date);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { return ''; }
        [$year,$month,$day] = array_map('intval', explode('-', $date));
        return checkdate($month,$day,$year) ? $date : '';
    }

    public static function register_graph_objects(array $objects): array {
        $objects['work_availability_exception'] = [
            'label' => __('Work Availability Exception', 'elev8-os'),
            'engine' => 'Organization',
            'organization_scoped' => true,
            'notes' => 'A date-specific coordination exception that overrides a recurring window; not attendance, leave approval, or employment scheduling.',
        ];
        $objects['credential_evidence_reference'] = [
            'label' => __('Credential Evidence Reference', 'elev8-os'),
            'engine' => 'Knowledge',
            'organization_scoped' => true,
            'notes' => 'A bounded reference to training, authorization, or certification evidence; never stores passwords, secrets, or credential values.',
        ];
        return $objects;
    }
}

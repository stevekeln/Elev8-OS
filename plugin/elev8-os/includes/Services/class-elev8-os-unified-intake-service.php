<?php
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Unified_Intake_Service {
    const POST_TYPE = 'elev8_intake';

    public static function init(): void {
        add_action('init', [__CLASS__, 'register_post_type']);
        add_action('elev8_os_operations_entry_created', [__CLASS__, 'capture_operations_entry'], 10, 3);
        add_action('elev8_os_bingo_reservation_created', [__CLASS__, 'capture_bingo_reservation'], 10, 2);
        add_action('elev8_os_unified_intake_submit', [__CLASS__, 'capture_submission'], 10, 1);
    }

    public static function activate(): void {
        self::register_post_type();
    }

    public static function register_post_type(): void {
        register_post_type(self::POST_TYPE, [
            'labels' => ['name' => __('Intake Items', 'elev8-os'), 'singular_name' => __('Intake Item', 'elev8-os')],
            'public' => false,
            'show_ui' => false,
            'show_in_rest' => false,
            'supports' => ['title', 'editor', 'author'],
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ]);
    }

    public static function statuses(): array {
        return [
            'new' => __('New', 'elev8-os'),
            'reviewed' => __('Reviewed', 'elev8-os'),
            'contacted' => __('Contacted', 'elev8-os'),
            'in_progress' => __('In Progress', 'elev8-os'),
            'completed' => __('Completed', 'elev8-os'),
            'archived' => __('Archived', 'elev8-os'),
        ];
    }


    /**
     * Reusable intake boundary for future public forms and integrations.
     *
     * Required data is intentionally small. The originating feature keeps its
     * own authoritative record and passes origin_post_id/origin_post_type when
     * available so workflow cards can be linked without duplicating logic.
     */
    public static function capture_submission(array $data): int {
        $name = sanitize_text_field((string) ($data['name'] ?? ''));
        $email = sanitize_email((string) ($data['email'] ?? ''));
        $phone = sanitize_text_field((string) ($data['phone'] ?? ''));
        $person_id = absint($data['person_id'] ?? 0);
        if ($person_id < 1 && $email !== '') {
            $person_id = Elev8_OS_Person_Service::find_or_create($email, $name, $phone);
        }

        $intake_id = self::create([
            'title' => (string) ($data['title'] ?? __('New intake item', 'elev8-os')),
            'type' => (string) ($data['type'] ?? 'general'),
            'source' => (string) ($data['source'] ?? 'unknown'),
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'summary' => (string) ($data['summary'] ?? ''),
            'origin_post_id' => absint($data['origin_post_id'] ?? 0),
            'origin_post_type' => (string) ($data['origin_post_type'] ?? ''),
            'person_id' => $person_id,
        ]);

        if ($intake_id > 0 && $person_id > 0) {
            Elev8_OS_Person_Service::add_activity(
                $person_id,
                (string) ($data['type'] ?? 'general'),
                $intake_id,
                (string) ($data['activity_label'] ?? $data['title'] ?? __('Intake submitted', 'elev8-os')),
                (string) ($data['source'] ?? 'unknown'),
                (string) ($data['summary'] ?? '')
            );
        }

        if ($intake_id > 0 && !empty($data['notify'])) {
            self::notify(
                $intake_id,
                (string) ($data['type_label'] ?? $data['type'] ?? __('submission', 'elev8-os')),
                $name,
                (string) ($data['summary'] ?? '')
            );
        }

        return $intake_id;
    }

    public static function capture_operations_entry(int $entry_id, string $template_key, array $values): void {
        $template = Elev8_OS_Daily_Operations_Service::template($template_key);
        if (!$template || empty($template['public'])) { return; }
        $name = (string) get_post_meta($entry_id, Elev8_OS_Daily_Operations_Service::META_GUEST_NAME, true);
        $email = (string) get_post_meta($entry_id, Elev8_OS_Daily_Operations_Service::META_GUEST_EMAIL, true);
        $phone = isset($values['phone']) ? (string) $values['phone'] : '';
        $source = sanitize_key((string) ($_SERVER['HTTP_REFERER'] ?? 'checkin-center'));
        $person_id = Elev8_OS_Person_Service::find_or_create($email, $name, $phone);
        $summary = self::summarize_values($template, $values);
        $intake_id = self::create([
            'title' => sprintf('%s — %s', (string) $template['name'], $name !== '' ? $name : __('Guest', 'elev8-os')),
            'type' => $template_key,
            'source' => 'checkin:' . $template_key,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'summary' => $summary,
            'origin_post_id' => $entry_id,
            'origin_post_type' => Elev8_OS_Daily_Operations_Service::POST_TYPE,
            'person_id' => $person_id,
        ]);
        Elev8_OS_Person_Service::add_activity($person_id, $template_key, $intake_id, (string) $template['name'], $source);
        self::notify($intake_id, (string) $template['name'], $name, $summary);
    }

    public static function capture_bingo_reservation(int $reservation_id, array $meta): void {
        $name = (string) ($meta['_elev8_bingo_name'] ?? '');
        $email = (string) ($meta['_elev8_bingo_email'] ?? '');
        $phone = (string) ($meta['_elev8_bingo_phone'] ?? '');
        $person_id = Elev8_OS_Person_Service::find_or_create($email, $name, $phone);
        $summary = sprintf(
            __('%1$d people for %2$s. %3$s', 'elev8-os'),
            (int) ($meta['_elev8_bingo_guest_count'] ?? 0),
            sanitize_text_field((string) ($meta['_elev8_bingo_event_date'] ?? '')),
            sanitize_textarea_field((string) ($meta['_elev8_bingo_notes'] ?? ''))
        );
        $intake_id = self::create([
            'title' => sprintf(__('Bingo Reservation — %s', 'elev8-os'), $name),
            'type' => 'bingo_reservation',
            'source' => 'bingo-reservations-page',
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'summary' => trim($summary),
            'origin_post_id' => $reservation_id,
            'origin_post_type' => 'elev8_bingo_res',
            'person_id' => $person_id,
        ]);
        Elev8_OS_Person_Service::add_activity($person_id, 'bingo_reservation', $intake_id, __('Bingo reservation', 'elev8-os'), 'bingo-reservations-page');
        self::notify($intake_id, __('Bingo reservation', 'elev8-os'), $name, $summary);
    }

    private static function summarize_values(array $template, array $values): string {
        $parts = [];
        foreach ((array) ($template['fields'] ?? []) as $field) {
            $key = (string) ($field['key'] ?? '');
            $value = trim((string) ($values[$key] ?? ''));
            if ($value !== '') { $parts[] = (string) ($field['label'] ?? $key) . ': ' . $value; }
        }
        return implode("\n", $parts);
    }

    public static function create(array $data): int {
        $origin_post_id = absint($data['origin_post_id'] ?? 0);
        $origin_post_type = sanitize_key((string) ($data['origin_post_type'] ?? ''));
        if ($origin_post_id > 0 && $origin_post_type !== '') {
            $existing = get_posts([
                'post_type' => self::POST_TYPE,
                'post_status' => 'publish',
                'posts_per_page' => 1,
                'fields' => 'ids',
                'meta_query' => [
                    ['key' => '_elev8_intake_origin_post_id', 'value' => $origin_post_id, 'compare' => '=', 'type' => 'NUMERIC'],
                    ['key' => '_elev8_intake_origin_post_type', 'value' => $origin_post_type, 'compare' => '='],
                ],
            ]);
            if ($existing) { return (int) $existing[0]; }
        }

        $id = wp_insert_post([
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'post_title' => sanitize_text_field((string) ($data['title'] ?? __('New intake item', 'elev8-os'))),
            'post_content' => sanitize_textarea_field((string) ($data['summary'] ?? '')),
        ], true);
        if (is_wp_error($id)) { return 0; }
        $meta = [
            '_elev8_intake_type' => sanitize_key((string) ($data['type'] ?? 'general')),
            '_elev8_intake_source' => sanitize_text_field((string) ($data['source'] ?? '')),
            '_elev8_intake_name' => sanitize_text_field((string) ($data['name'] ?? '')),
            '_elev8_intake_email' => sanitize_email((string) ($data['email'] ?? '')),
            '_elev8_intake_phone' => sanitize_text_field((string) ($data['phone'] ?? '')),
            '_elev8_intake_status' => 'new',
            '_elev8_intake_assigned_user' => 0,
            '_elev8_intake_follow_up' => '',
            '_elev8_intake_notes' => '',
            '_elev8_intake_origin_post_id' => $origin_post_id,
            '_elev8_intake_origin_post_type' => $origin_post_type,
            '_elev8_intake_person_id' => absint($data['person_id'] ?? 0),
        ];
        foreach ($meta as $key => $value) { update_post_meta((int) $id, $key, $value); }
        if (class_exists('Elev8_OS_Activity_Service')) {
            Elev8_OS_Activity_Service::record([
                'type' => 'intake_created',
                'label' => __('Intake item created', 'elev8-os'),
                'details' => (string) ($data['summary'] ?? ''),
                'person_id' => absint($data['person_id'] ?? 0),
                'object_id' => (int) $id,
                'object_type' => self::POST_TYPE,
                'source' => (string) ($data['source'] ?? ''),
            ]);
        }
        do_action('elev8_os_intake_created', (int) $id, $data);
        return (int) $id;
    }

    private static function notify(int $intake_id, string $type_label, string $name, string $summary): void {
        $to = (string) get_option('admin_email');
        if ($to === '' || !is_email($to)) { return; }
        $subject = sprintf('[Elev8 OS] New %s', $type_label);
        $message = "A new submission was added to the Owner Intake Dashboard.\n\n";
        $message .= 'From: ' . ($name !== '' ? $name : 'Guest') . "\n";
        $message .= 'Details: ' . wp_strip_all_tags($summary) . "\n\n";
        $message .= admin_url('admin.php?page=elev8-unified-intake&focus=' . $intake_id);
        Elev8_OS_Notification_Service::send_email($to, $subject, $message);
    }
}

<?php
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Person_Service {
    const POST_TYPE = 'elev8_person';

    public static function init(): void {
        add_action('init', [__CLASS__, 'register_post_type']);
    }

    public static function activate(): void {
        self::register_post_type();
    }

    public static function register_post_type(): void {
        register_post_type(self::POST_TYPE, [
            'labels' => ['name' => __('People', 'elev8-os'), 'singular_name' => __('Person', 'elev8-os')],
            'public' => false,
            'show_ui' => false,
            'show_in_rest' => false,
            'supports' => ['title'],
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ]);
    }

    public static function find_or_create(string $email, string $name = '', string $phone = ''): int {
        $email = sanitize_email($email);
        $name = sanitize_text_field($name);
        $phone = sanitize_text_field($phone);
        if ($email === '') { return 0; }

        $existing = get_posts([
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_key' => '_elev8_person_email',
            'meta_value' => strtolower($email),
        ]);

        if ($existing) {
            $id = (int) $existing[0];
            if ($name !== '') { update_post_meta($id, '_elev8_person_name', $name); }
            if ($phone !== '') { update_post_meta($id, '_elev8_person_phone', $phone); }
            return $id;
        }

        $title = $name !== '' ? $name : $email;
        $id = wp_insert_post([
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'post_title' => $title,
        ], true);
        if (is_wp_error($id)) { return 0; }

        update_post_meta((int) $id, '_elev8_person_email', strtolower($email));
        update_post_meta((int) $id, '_elev8_person_name', $name);
        update_post_meta((int) $id, '_elev8_person_phone', $phone);
        update_post_meta((int) $id, '_elev8_person_first_seen', current_time('mysql'));
        return (int) $id;
    }

    public static function add_activity(int $person_id, string $type, int $record_id, string $label, string $source = ''): void {
        if ($person_id < 1) { return; }
        $activity = get_post_meta($person_id, '_elev8_person_activity', true);
        if (!is_array($activity)) { $activity = []; }
        $activity[] = [
            'type' => sanitize_key($type),
            'record_id' => $record_id,
            'label' => sanitize_text_field($label),
            'source' => sanitize_text_field($source),
            'created_at' => current_time('mysql'),
        ];
        if (count($activity) > 100) { $activity = array_slice($activity, -100); }
        update_post_meta($person_id, '_elev8_person_activity', $activity);
        update_post_meta($person_id, '_elev8_person_last_seen', current_time('mysql'));
    }
}

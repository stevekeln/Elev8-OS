<?php
/**
 * Shared public identity profiles for Elev8 OS users.
 *
 * @package Elev8OS
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Elev8_OS_Public_Profile_Service {

    public const META_PREFIX = '_elev8_public_profile_';

    /** @return array<string,mixed> */
    public static function get(int $user_id): array {
        $user = get_user_by('id', $user_id);
        if (!$user instanceof WP_User) {
            return [];
        }

        $display_name = (string) get_user_meta($user_id, self::META_PREFIX . 'display_name', true);
        $slug = (string) get_user_meta($user_id, self::META_PREFIX . 'slug', true);

        return [
            'user_id' => $user_id,
            'display_name' => $display_name !== '' ? $display_name : (string) $user->display_name,
            'slug' => $slug !== '' ? $slug : self::default_slug($user),
            'headline' => (string) get_user_meta($user_id, self::META_PREFIX . 'headline', true),
            'bio' => (string) get_user_meta($user_id, self::META_PREFIX . 'bio', true),
            'photo_url' => (string) get_user_meta($user_id, self::META_PREFIX . 'photo_url', true),
            'website_url' => (string) get_user_meta($user_id, self::META_PREFIX . 'website_url', true),
            'instagram_url' => (string) get_user_meta($user_id, self::META_PREFIX . 'instagram_url', true),
            'facebook_url' => (string) get_user_meta($user_id, self::META_PREFIX . 'facebook_url', true),
            'contact_email' => (string) get_user_meta($user_id, self::META_PREFIX . 'contact_email', true),
            'published' => get_user_meta($user_id, self::META_PREFIX . 'status', true) === 'published',
            'profile_type' => self::profile_type($user),
            'role_label' => self::role_label($user),
        ];
    }

    public static function is_published(int $user_id): bool {
        return get_user_meta($user_id, self::META_PREFIX . 'status', true) === 'published';
    }

    public static function editor_url(): string {
        return home_url('/elev8-profile/');
    }

    public static function public_url(int $user_id): string {
        $profile = self::get($user_id);
        $slug = sanitize_title((string) ($profile['slug'] ?? ''));
        return $slug !== '' ? home_url('/people/' . $slug . '/') : '';
    }

    public static function user_id_from_slug(string $slug): int {
        $slug = sanitize_title($slug);
        if ($slug === '') {
            return 0;
        }

        $users = get_users([
            'number' => 1,
            'fields' => 'ids',
            'meta_key' => self::META_PREFIX . 'slug',
            'meta_value' => $slug,
        ]);
        if ($users) {
            return (int) $users[0];
        }

        $user = get_user_by('slug', $slug);
        return $user instanceof WP_User ? (int) $user->ID : 0;
    }

    /** @param array<string,mixed> $input */
    public static function save(int $user_id, array $input): array {
        $user = get_user_by('id', $user_id);
        if (!$user instanceof WP_User) {
            return ['success' => false, 'message' => __('Profile account is unavailable.', 'elev8-os')];
        }

        $display_name = sanitize_text_field((string) ($input['display_name'] ?? ''));
        $headline = sanitize_text_field((string) ($input['headline'] ?? ''));
        $bio = sanitize_textarea_field((string) ($input['bio'] ?? ''));
        $slug = sanitize_title((string) ($input['slug'] ?? ''));
        $photo_url = esc_url_raw((string) ($input['photo_url'] ?? ''));
        $website_url = esc_url_raw((string) ($input['website_url'] ?? ''));
        $instagram_url = esc_url_raw((string) ($input['instagram_url'] ?? ''));
        $facebook_url = esc_url_raw((string) ($input['facebook_url'] ?? ''));
        $contact_email = sanitize_email((string) ($input['contact_email'] ?? ''));
        $publish = !empty($input['publish']);

        if ($display_name === '') {
            $display_name = (string) $user->display_name;
        }
        if ($slug === '') {
            $slug = self::default_slug($user);
        }
        $slug = self::unique_slug($slug, $user_id);

        if ($publish && trim($bio) === '') {
            return ['success' => false, 'message' => __('Add a short biography before publishing your public profile.', 'elev8-os')];
        }

        $values = compact('display_name', 'headline', 'bio', 'slug', 'photo_url', 'website_url', 'instagram_url', 'facebook_url', 'contact_email');
        foreach ($values as $key => $value) {
            update_user_meta($user_id, self::META_PREFIX . $key, $value);
        }
        update_user_meta($user_id, self::META_PREFIX . 'status', $publish ? 'published' : 'draft');

        // Backward-compatible event-host status used by existing dashboard snapshots.
        update_user_meta($user_id, '_elev8_public_host_profile_status', $publish ? 'published' : 'draft');

        if (class_exists('Elev8_OS_Activity_Service')) {
            Elev8_OS_Activity_Service::record([
                'actor_user_id' => $user_id,
                'type' => $publish ? 'public_profile_published' : 'public_profile_updated',
                'label' => $publish
                    ? sprintf(__('%s published a public profile.', 'elev8-os'), $display_name)
                    : sprintf(__('%s updated a public profile draft.', 'elev8-os'), $display_name),
                'details' => __('Public identity profile activity.', 'elev8-os'),
                'object_type' => 'user',
                'object_id' => $user_id,
                'source' => 'public_profiles',
            ]);
        }

        return ['success' => true, 'message' => $publish ? __('Your public profile is published.', 'elev8-os') : __('Your profile draft was saved.', 'elev8-os')];
    }

    private static function default_slug(WP_User $user): string {
        $preferred = sanitize_title((string) $user->display_name);
        return $preferred !== '' ? $preferred : sanitize_title((string) $user->user_nicename);
    }

    private static function unique_slug(string $slug, int $user_id): string {
        $base = $slug;
        $suffix = 2;
        while (($existing = self::user_id_from_slug($slug)) > 0 && $existing !== $user_id) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }
        return $slug;
    }

    private static function profile_type(WP_User $user): string {
        if (class_exists('Elev8_OS_Access_Service')) {
            if (Elev8_OS_Access_Service::uses_event_host_home($user)) return 'event_host';
            if (Elev8_OS_Access_Service::is_manager($user)) return 'manager';
            if (Elev8_OS_Access_Service::user_can('view_artist_dashboard', $user)) return 'artist';
        }
        return 'team_member';
    }

    private static function role_label(WP_User $user): string {
        $type = self::profile_type($user);
        $labels = [
            'event_host' => __('Event Host', 'elev8-os'),
            'manager' => __('Manager', 'elev8-os'),
            'artist' => __('Artist', 'elev8-os'),
            'team_member' => __('Elev8 Team', 'elev8-os'),
        ];
        return (string) ($labels[$type] ?? $labels['team_member']);
    }
}

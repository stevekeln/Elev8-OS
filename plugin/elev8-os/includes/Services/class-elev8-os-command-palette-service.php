<?php
/**
 * Universal command palette and role-aware search for Elev8 OS.
 *
 * @package Elev8OS
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Elev8_OS_Command_Palette_Service {

    public static function init(): void {
        add_action('wp_ajax_elev8_os_command_search', [__CLASS__, 'ajax_search']);
    }

    /** @return array<int,array<string,string>> */
    public static function commands(?WP_User $user = null): array {
        $user = $user instanceof WP_User ? $user : wp_get_current_user();
        if (!$user instanceof WP_User || $user->ID <= 0) {
            return [];
        }

        $dashboard = self::dashboard_url($user);
        $commands = [
            self::command('dashboard', __('My Dashboard', 'elev8-os'), __('Open your role-based Operational Home.', 'elev8-os'), $dashboard, 'dashboard', '🏠'),
            self::command('public_home', __('Elev8 Arts Home', 'elev8-os'), __('Return to the public Elev8 Arts website.', 'elev8-os'), home_url('/'), 'navigation', '🌐'),
            self::command('profile', __('My Profile', 'elev8-os'), __('Update your WordPress account and profile information.', 'elev8-os'), get_edit_profile_url($user->ID), 'account', '👤'),
        ];

        if (Elev8_OS_Access_Service::user_can('view_work', $user) && class_exists('Elev8_OS_Work_Module')) {
            $commands[] = self::command('my_work', __('My Work', 'elev8-os'), __('View assignments, due dates, and follow-up.', 'elev8-os'), Elev8_OS_Work_Module::my_url(), 'work', '☑');
        }
        if (Elev8_OS_Access_Service::user_can('manage_work', $user) && class_exists('Elev8_OS_Work_Module')) {
            $commands[] = self::command('team_work', __('Team Work', 'elev8-os'), __('Assign and manage work across the team.', 'elev8-os'), Elev8_OS_Work_Module::team_url(), 'work', '👥');
        }
        if (Elev8_OS_Access_Service::user_can('view_business_memory', $user)) {
            $commands[] = self::command('business_memory', __('Business Memory', 'elev8-os'), __('Open operating logs, owner notes, issues, and follow-up.', 'elev8-os'), admin_url('admin.php?page=elev8-business-memory'), 'workspace', '📝');
        }
        if (Elev8_OS_Access_Service::user_can('view_ceo_dashboard', $user)) {
            $commands[] = self::command('business_intelligence', __('Business Intelligence', 'elev8-os'), __('Open verified KPIs, confidence, trends, and decision support.', 'elev8-os'), admin_url('admin.php?page=elev8-business-intelligence'), 'workspace', '📈');
            $commands[] = self::command('event_applications', __('Event Applications', 'elev8-os'), __('Review event applications and agreements.', 'elev8-os'), admin_url('admin.php?page=elev8-event-applications'), 'workspace', '🗂');
            $commands[] = self::command('opportunities', __('Opportunities', 'elev8-os'), __('Open business and artist growth opportunities.', 'elev8-os'), admin_url('admin.php?page=elev8-opportunities'), 'workspace', '📣');
        }
        if (Elev8_OS_Access_Service::user_can('view_assigned_reservations', $user) || Elev8_OS_Access_Service::user_can('view_ceo_dashboard', $user)) {
            $commands[] = self::command('reservations', __('Bingo Reservations', 'elev8-os'), __('Review reservation groups and guest details.', 'elev8-os'), add_query_arg('elev8_view', 'bingo_reservations', $dashboard), 'workspace', '🎟');
        }
        if (Elev8_OS_Access_Service::is_dj($user)) {
            $commands[] = self::command('open_mic', __('Open Mic Check-In', 'elev8-os'), __('Open performer check-ins and event entries.', 'elev8-os'), home_url('/checkin/?type=open_mic'), 'event', '🎤');
            $commands[] = self::command('event_log', __('Complete Event Log', 'elev8-os'), __('Record the event closeout in Business Memory.', 'elev8-os'), home_url('/checkin/?type=event_log'), 'event', '📝');
        }
        if (Elev8_OS_Access_Service::user_can('view_artist_dashboard', $user) && class_exists('Elev8_OS_Portal_Page_Manager')) {
            foreach (['classes' => 'My Classes', 'artwork' => 'My Artwork', 'growth_studio' => 'Growth Studio', 'marketing' => 'Marketing Center', 'content_studio' => 'Content Studio'] as $key => $label) {
                $commands[] = self::command('artist_' . $key, __($label, 'elev8-os'), sprintf(__('Open %s.', 'elev8-os'), $label), Elev8_OS_Portal_Page_Manager::get_url($key), 'artist', '✦');
            }
        }

        /** @var array<int,array<string,string>> $commands */
        $commands = apply_filters('elev8_os_command_palette_commands', $commands, $user);
        return array_values(array_filter($commands, static function (array $command): bool {
            return !empty($command['label']) && !empty($command['url']);
        }));
    }

    public static function ajax_search(): void {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('Please sign in again.', 'elev8-os')], 401);
        }
        check_ajax_referer('elev8_os_command_palette', 'nonce');

        $user = wp_get_current_user();
        $query = sanitize_text_field(wp_unslash($_GET['q'] ?? ''));
        $results = self::matching_commands($query, $user);

        if (strlen($query) >= 2) {
            $results = array_merge($results, self::search_work($query, $user));
            $results = array_merge($results, self::search_people($query, $user));
        }

        $seen = [];
        $unique = [];
        foreach ($results as $result) {
            $key = ($result['type'] ?? '') . '|' . ($result['url'] ?? '') . '|' . ($result['label'] ?? '');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $result;
            if (count($unique) >= 20) {
                break;
            }
        }

        wp_send_json_success(['results' => $unique]);
    }

    /** @return array<int,array<string,string>> */
    private static function matching_commands(string $query, WP_User $user): array {
        $commands = self::commands($user);
        if ($query === '') {
            return array_slice($commands, 0, 12);
        }
        $needle = strtolower($query);
        return array_values(array_filter($commands, static function (array $command) use ($needle): bool {
            $haystack = strtolower(($command['label'] ?? '') . ' ' . ($command['description'] ?? '') . ' ' . ($command['group'] ?? ''));
            return strpos($haystack, $needle) !== false;
        }));
    }

    /** @return array<int,array<string,string>> */
    private static function search_work(string $query, WP_User $user): array {
        if (!class_exists('Elev8_OS_Work_Service') || !Elev8_OS_Access_Service::user_can('view_work', $user)) {
            return [];
        }

        $can_manage = Elev8_OS_Access_Service::user_can('manage_work', $user);
        $args = [
            'post_type' => Elev8_OS_Work_Service::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => 8,
            's' => $query,
            'orderby' => 'date',
            'order' => 'DESC',
        ];
        if (!$can_manage) {
            $args['meta_query'] = [[
                'key' => '_elev8_work_owner_user_id',
                'value' => $user->ID,
                'type' => 'NUMERIC',
            ]];
        }

        $items = get_posts($args);
        $url = $can_manage && class_exists('Elev8_OS_Work_Module') ? Elev8_OS_Work_Module::team_url() : Elev8_OS_Work_Module::my_url();
        $results = [];
        foreach ($items as $item) {
            $status = (string) get_post_meta($item->ID, '_elev8_work_status', true) ?: 'new';
            $due = (string) get_post_meta($item->ID, '_elev8_work_due_date', true);
            $description = sprintf(__('Work · %1$s%2$s', 'elev8-os'), ucwords(str_replace('_', ' ', $status)), $due ? ' · ' . $due : '');
            $results[] = self::command('work_' . $item->ID, get_the_title($item), $description, add_query_arg('highlight', $item->ID, $url), 'search', '☑', 'work');
        }
        return $results;
    }

    /** @return array<int,array<string,string>> */
    private static function search_people(string $query, WP_User $user): array {
        if (!Elev8_OS_Access_Service::user_can('view_ceo_dashboard', $user)) {
            return [];
        }

        $users = get_users([
            'number' => 8,
            'search' => '*' . $query . '*',
            'search_columns' => ['display_name', 'user_login', 'user_email'],
            'orderby' => 'display_name',
            'order' => 'ASC',
        ]);
        $results = [];
        foreach ($users as $found) {
            if (!current_user_can('edit_user', $found->ID)) {
                continue;
            }
            $results[] = self::command(
                'person_' . $found->ID,
                $found->display_name ?: $found->user_login,
                __('Team member profile', 'elev8-os'),
                get_edit_user_link($found->ID) ?: admin_url('user-edit.php?user_id=' . $found->ID),
                'search',
                '👤',
                'person'
            );
        }
        return $results;
    }

    /** @return array<string,string> */
    private static function command(string $id, string $label, string $description, string $url, string $group, string $icon, string $type = 'command'): array {
        return [
            'id' => sanitize_key($id),
            'label' => wp_strip_all_tags($label),
            'description' => wp_strip_all_tags($description),
            'url' => esc_url_raw($url),
            'group' => sanitize_key($group),
            'icon' => $icon,
            'type' => sanitize_key($type),
        ];
    }

    private static function dashboard_url(WP_User $user): string {
        if (Elev8_OS_Access_Service::user_can('view_ceo_dashboard', $user)) {
            return admin_url('admin.php?page=elev8-ceo-dashboard');
        }
        if (class_exists('Elev8_OS_Portal_Page_Manager')) {
            return Elev8_OS_Portal_Page_Manager::get_url('dashboard');
        }
        return home_url('/artist-dashboard/');
    }
}

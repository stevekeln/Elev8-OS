<?php
/**
 * Shared threaded conversation boundary for Elev8 OS records and teams.
 */
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Conversation_Service {
    public const THREAD_POST_TYPE = 'elev8_conversation';
    public const MESSAGE_POST_TYPE = 'elev8_message';
    public const META_PINNED_MEMORY_ID = '_elev8_conversation_memory_id';
    public const META_ATTACHMENTS = '_elev8_conversation_attachments';

    public static function init(): void {
        add_action('init', [__CLASS__, 'register_post_types']);
    }

    public static function activate(): void {
        self::register_post_types();
    }

    public static function register_post_types(): void {
        register_post_type(self::THREAD_POST_TYPE, [
            'labels' => ['name' => __('Conversations', 'elev8-os'), 'singular_name' => __('Conversation', 'elev8-os')],
            'public' => false,
            'show_ui' => false,
            'show_in_rest' => false,
            'supports' => ['title', 'author'],
            'map_meta_cap' => true,
        ]);
        register_post_type(self::MESSAGE_POST_TYPE, [
            'labels' => ['name' => __('Conversation Messages', 'elev8-os'), 'singular_name' => __('Conversation Message', 'elev8-os')],
            'public' => false,
            'show_ui' => false,
            'show_in_rest' => false,
            'supports' => ['editor', 'author'],
            'map_meta_cap' => true,
        ]);
    }

    public static function create_thread(array $data): int {
        $creator = absint($data['creator_user_id'] ?? get_current_user_id());
        $subject = sanitize_text_field((string) ($data['subject'] ?? ''));
        if ($creator < 1 || $subject === '') { return 0; }

        $participants = [];
        foreach (array_values(array_unique(array_filter(array_map('absint', (array) ($data['participant_user_ids'] ?? []))))) as $participant_id) {
            $participant = get_userdata($participant_id);
            if ($participant instanceof WP_User && Elev8_OS_Access_Service::user_can('view_conversations', $participant)) {
                $participants[] = $participant_id;
            }
        }
        if (!in_array($creator, $participants, true)) { $participants[] = $creator; }

        $thread_id = wp_insert_post([
            'post_type' => self::THREAD_POST_TYPE,
            'post_status' => 'publish',
            'post_title' => $subject,
            'post_author' => $creator,
        ], true);
        if (is_wp_error($thread_id) || $thread_id < 1) { return 0; }

        update_post_meta($thread_id, '_elev8_conversation_status', 'open');
        update_post_meta($thread_id, '_elev8_conversation_context_type', sanitize_key((string) ($data['context_type'] ?? 'general')));
        update_post_meta($thread_id, '_elev8_conversation_context_id', absint($data['context_id'] ?? 0));
        update_post_meta($thread_id, '_elev8_conversation_participants', $participants);
        update_post_meta($thread_id, '_elev8_conversation_last_activity', current_time('mysql'));
        foreach ($participants as $user_id) {
            update_post_meta($thread_id, '_elev8_conversation_participant_' . $user_id, 1);
        }

        $message = sanitize_textarea_field((string) ($data['message'] ?? ''));
        if ($message !== '') { self::add_message((int) $thread_id, $message, $creator); }

        if (class_exists('Elev8_OS_Activity_Service')) {
            Elev8_OS_Activity_Service::record([
                'type' => 'conversation_created',
                'label' => sprintf(__('Conversation started: %s', 'elev8-os'), $subject),
                'object_id' => (int) $thread_id,
                'object_type' => 'conversation',
                'source' => 'conversation_engine',
                'actor_user_id' => $creator,
                'metadata' => ['participants' => $participants],
            ]);
        }
        do_action('elev8_os_conversation_created', (int) $thread_id, $data);
        return (int) $thread_id;
    }

    public static function add_message(int $thread_id, string $message, int $author_user_id = 0, array $attachment_ids = []): int {
        $author_user_id = $author_user_id > 0 ? $author_user_id : get_current_user_id();
        $message = sanitize_textarea_field($message);
        if ($thread_id < 1 || $author_user_id < 1 || $message === '' || !self::can_view($thread_id, get_userdata($author_user_id) ?: null)) { return 0; }

        $message_id = wp_insert_post([
            'post_type' => self::MESSAGE_POST_TYPE,
            'post_status' => 'publish',
            'post_content' => $message,
            'post_author' => $author_user_id,
            'post_parent' => $thread_id,
        ], true);
        if (is_wp_error($message_id) || $message_id < 1) { return 0; }

        update_post_meta($message_id, '_elev8_conversation_thread_id', $thread_id);
        $attachment_ids = array_values(array_filter(array_map('absint', $attachment_ids)));
        if ($attachment_ids) { update_post_meta($message_id, self::META_ATTACHMENTS, $attachment_ids); }
        update_post_meta($thread_id, '_elev8_conversation_last_activity', current_time('mysql'));
        self::add_mentions_as_participants($thread_id, $message);
        self::mark_read($thread_id, $author_user_id);

        if (class_exists('Elev8_OS_Activity_Service')) {
            Elev8_OS_Activity_Service::record([
                'type' => 'conversation_message',
                'label' => sprintf(__('Reply added to %s', 'elev8-os'), get_the_title($thread_id)),
                'details' => wp_trim_words($message, 30),
                'object_id' => $thread_id,
                'object_type' => 'conversation',
                'source' => 'conversation_engine',
                'actor_user_id' => $author_user_id,
                'metadata' => ['message_id' => (int) $message_id, 'attachments' => $attachment_ids],
            ]);
        }
        do_action('elev8_os_conversation_message_added', (int) $message_id, $thread_id, $author_user_id);
        return (int) $message_id;
    }

    public static function threads_for_user(int $user_id, int $limit = 100): array {
        if ($user_id < 1) { return []; }
        return get_posts([
            'post_type' => self::THREAD_POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => max(1, min(200, $limit)),
            'meta_key' => '_elev8_conversation_last_activity',
            'orderby' => 'meta_value',
            'order' => 'DESC',
            'meta_query' => [[
                'key' => '_elev8_conversation_participant_' . $user_id,
                'value' => 1,
                'type' => 'NUMERIC',
            ]],
        ]);
    }

    public static function messages(int $thread_id): array {
        return get_posts([
            'post_type' => self::MESSAGE_POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => 200,
            'post_parent' => $thread_id,
            'orderby' => 'date',
            'order' => 'ASC',
        ]);
    }

    public static function participants(int $thread_id): array {
        return array_values(array_filter(array_map('absint', (array) get_post_meta($thread_id, '_elev8_conversation_participants', true))));
    }

    public static function can_view(int $thread_id, ?WP_User $user = null): bool {
        $user = $user instanceof WP_User ? $user : wp_get_current_user();
        if (!$user instanceof WP_User || $user->ID < 1) { return false; }
        if (Elev8_OS_Access_Service::user_can('manage_conversations', $user)) { return true; }
        return (int) get_post_meta($thread_id, '_elev8_conversation_participant_' . $user->ID, true) === 1;
    }

    public static function mark_read(int $thread_id, int $user_id): void {
        if ($thread_id > 0 && $user_id > 0) {
            update_post_meta($thread_id, '_elev8_conversation_read_' . $user_id, current_time('mysql'));
        }
    }

    public static function unread_count(int $user_id): int {
        $count = 0;
        foreach (self::threads_for_user($user_id, 200) as $thread) {
            $last = (string) get_post_meta($thread->ID, '_elev8_conversation_last_activity', true);
            $read = (string) get_post_meta($thread->ID, '_elev8_conversation_read_' . $user_id, true);
            if ($last !== '' && ($read === '' || $last > $read)) { $count++; }
        }
        return $count;
    }

    public static function close(int $thread_id, WP_User $user): bool {
        if (!self::can_view($thread_id, $user)) { return false; }
        $creator = (int) get_post_field('post_author', $thread_id);
        if ($creator !== $user->ID && !Elev8_OS_Access_Service::user_can('manage_conversations', $user)) { return false; }
        update_post_meta($thread_id, '_elev8_conversation_status', 'closed');
        return true;
    }


    /** @return array<int,WP_User> */
    public static function recipient_users(): array {
        $found = [];
        foreach (Elev8_OS_Access_Service::assignment_users_grouped() as $users) {
            foreach ($users as $user) { if ($user instanceof WP_User) { $found[$user->ID] = $user; } }
        }
        foreach (get_users(['orderby' => 'display_name', 'order' => 'ASC']) as $user) {
            if (!$user instanceof WP_User) { continue; }
            if (Elev8_OS_Access_Service::user_can('view_ceo_dashboard', $user) || Elev8_OS_Access_Service::user_can('manage_conversations', $user)) {
                $found[$user->ID] = $user;
            }
        }
        uasort($found, static fn(WP_User $a, WP_User $b): int => strcasecmp($a->display_name, $b->display_name));
        return array_values($found);
    }

    /** @return array<string,array<int,WP_User>> */
    public static function recipient_groups(): array {
        $groups = Elev8_OS_Access_Service::assignment_users_grouped();
        $known = []; foreach ($groups as $users) { foreach ($users as $u) { $known[$u->ID] = true; } }
        foreach (self::recipient_users() as $user) {
            if (!isset($known[$user->ID])) { $groups[__('Leadership', 'elev8-os')][] = $user; }
        }
        return array_filter($groups);
    }

    /** @return array<int,int> */
    public static function message_attachments(int $message_id): array {
        return array_values(array_filter(array_map('absint', (array) get_post_meta($message_id, self::META_ATTACHMENTS, true))));
    }

    public static function pin_to_business_memory(int $thread_id, WP_User $user): int {
        if (!self::can_view($thread_id, $user) || !Elev8_OS_Access_Service::user_can('manage_business_memory', $user) || !class_exists('Elev8_OS_Business_Memory_Service')) { return 0; }
        $existing = absint(get_post_meta($thread_id, self::META_PINNED_MEMORY_ID, true));
        if ($existing && get_post_status($existing)) { return $existing; }
        $names = []; foreach (self::participants($thread_id) as $id) { $u = get_userdata($id); if ($u instanceof WP_User) { $names[] = $u->display_name; } }
        $lines = [];
        foreach (self::messages($thread_id) as $message) {
            $author = get_userdata((int) $message->post_author);
            $lines[] = sprintf('%s (%s): %s', $author instanceof WP_User ? $author->display_name : __('Former user', 'elev8-os'), get_the_date('M j, Y g:i a', $message), wp_strip_all_tags($message->post_content));
        }
        $result = Elev8_OS_Business_Memory_Service::save([
            'event_date' => current_time('Y-m-d'), 'event_time' => current_time('H:i'),
            'record_type' => 'meeting', 'priority' => 'normal',
            'participants' => implode(', ', $names),
            'topic' => sprintf(__('Conversation: %s', 'elev8-os'), get_the_title($thread_id)),
            'summary' => implode("\n\n", $lines),
            'decisions' => __('Pinned from the Elev8 OS Conversation Center for long-term visibility.', 'elev8-os'),
            'tags' => 'conversation, pinned',
        ], [], $user->ID);
        if (is_wp_error($result) || (int) $result < 1) { return 0; }
        update_post_meta($thread_id, self::META_PINNED_MEMORY_ID, (int) $result);
        if (class_exists('Elev8_OS_Activity_Service')) { Elev8_OS_Activity_Service::record(['type'=>'conversation_pinned','label'=>sprintf(__('Conversation pinned to Business Memory: %s','elev8-os'), get_the_title($thread_id)),'object_id'=>$thread_id,'object_type'=>'conversation','source'=>'conversation_engine','actor_user_id'=>$user->ID,'metadata'=>['memory_id'=>(int)$result]]); }
        return (int) $result;
    }

    public static function pinned_memory_id(int $thread_id): int { return absint(get_post_meta($thread_id, self::META_PINNED_MEMORY_ID, true)); }

    private static function add_mentions_as_participants(int $thread_id, string $message): void {
        if (!preg_match_all('/@([A-Za-z0-9._-]+)/', $message, $matches)) { return; }
        $participants = self::participants($thread_id);
        foreach (array_unique($matches[1]) as $login) {
            $user = get_user_by('login', sanitize_user($login));
            if (!$user instanceof WP_User) { continue; }
            if (!in_array($user->ID, $participants, true)) {
                $participants[] = $user->ID;
                update_post_meta($thread_id, '_elev8_conversation_participant_' . $user->ID, 1);
            }
        }
        update_post_meta($thread_id, '_elev8_conversation_participants', array_values(array_unique($participants)));
    }
}

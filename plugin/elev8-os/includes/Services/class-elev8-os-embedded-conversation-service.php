<?php
/**
 * Reusable embedded Conversation panels for Business Graph workspaces.
 */
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Embedded_Conversation_Service {
    public static function init(): void {
        add_action('admin_post_elev8_create_context_conversation', [__CLASS__, 'handle_create']);
        add_action('admin_post_elev8_reply_context_conversation', [__CLASS__, 'handle_reply']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    public static function enqueue_assets(): void {
        wp_enqueue_style('elev8-os-conversations', ELEV8_OS_URL . 'assets/css/conversations.css', [], ELEV8_OS_VERSION);
        wp_enqueue_script('elev8-os-conversations', ELEV8_OS_URL . 'assets/js/conversations.js', [], ELEV8_OS_VERSION, true);
    }

    /** @return array<int,WP_Post> */
    public static function threads(string $context_type, int $context_id, ?WP_User $user = null): array {
        $context_type = sanitize_key($context_type);
        $context_id = absint($context_id);
        $user = $user instanceof WP_User ? $user : self::effective_user();
        if ($context_type === '' || $context_id < 1 || $user->ID < 1) { return []; }

        $threads = get_posts([
            'post_type' => Elev8_OS_Conversation_Service::THREAD_POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => 50,
            'meta_key' => '_elev8_conversation_last_activity',
            'orderby' => 'meta_value',
            'order' => 'DESC',
            'meta_query' => [
                'relation' => 'AND',
                ['key' => '_elev8_conversation_context_type', 'value' => $context_type],
                ['key' => '_elev8_conversation_context_id', 'value' => $context_id, 'type' => 'NUMERIC'],
            ],
        ]);
        return array_values(array_filter($threads, static fn($thread): bool => $thread instanceof WP_Post && Elev8_OS_Conversation_Service::can_view((int)$thread->ID, $user)));
    }

    public static function unread_count(string $context_type, int $context_id, ?WP_User $user = null): int {
        $user = $user instanceof WP_User ? $user : self::effective_user();
        $count = 0;
        foreach (self::threads($context_type, $context_id, $user) as $thread) {
            $count += Elev8_OS_Conversation_Service::unread_messages_count((int)$thread->ID, (int)$user->ID);
        }
        return $count;
    }

    /**
     * Render an object-attached panel without leaving the current workspace.
     *
     * @param array<string,mixed> $args
     */
    public static function render(string $context_type, int $context_id, array $args = []): string {
        $user = self::effective_user();
        if ($user->ID < 1 || !Elev8_OS_Access_Service::user_can('view_conversations', $user)) { return ''; }

        $context_type = sanitize_key($context_type);
        $context_id = absint($context_id);
        if ($context_type === '' || $context_id < 1) { return ''; }

        $title = sanitize_text_field((string)($args['title'] ?? __('Conversation', 'elev8-os')));
        $return_url = esc_url_raw((string)($args['return_url'] ?? wp_get_referer() ?: home_url('/')));
        $default_participants = array_values(array_unique(array_filter(array_map('absint', (array)($args['participant_user_ids'] ?? [])))));
        $threads = self::threads($context_type, $context_id, $user);
        $selected_id = absint($_GET['elev8_context_conversation'] ?? 0);
        $selected = null;
        foreach ($threads as $thread) { if ((int)$thread->ID === $selected_id) { $selected = $thread; break; } }
        if (!$selected && count($threads) === 1 && !empty($_GET['elev8_open_context'])) { $selected = reset($threads); }
        $unread = self::unread_count($context_type, $context_id, $user);

        ob_start();
        echo '<div class="elev8-embedded-conversation"' . ($selected instanceof WP_Post ? ' data-elev8-conversation-thread' : '') . ' id="elev8-conversation-' . esc_attr($context_type . '-' . $context_id) . '">';
        echo '<div class="elev8-embedded-conversation__head"><strong>' . esc_html($title) . '</strong>';
        if ($unread > 0) { echo '<span class="elev8-embedded-conversation__badge">' . esc_html(sprintf(_n('%d unread', '%d unread', $unread, 'elev8-os'), $unread)) . '</span>'; }
        echo '</div>';

        if ($selected instanceof WP_Post && Elev8_OS_Conversation_Service::can_view((int)$selected->ID, $user)) {
            self::render_thread($selected, $return_url, $context_type, $context_id, $user);
        } else {
            if ($threads) {
                echo '<div class="elev8-embedded-conversation__threads">';
                foreach ($threads as $thread) {
                    $thread_unread = Elev8_OS_Conversation_Service::unread_messages_count((int)$thread->ID, (int)$user->ID);
                    $url = add_query_arg(['elev8_context_conversation' => (int)$thread->ID, 'elev8_open_context' => 1], $return_url) . '#elev8-conversation-' . rawurlencode($context_type . '-' . $context_id);
                    echo '<a class="elev8-embedded-conversation__thread' . ($thread_unread ? ' is-unread' : '') . '" href="' . esc_url($url) . '"><span><strong>' . esc_html(get_the_title($thread)) . '</strong><small>' . esc_html(human_time_diff(strtotime((string)get_post_meta($thread->ID, '_elev8_conversation_last_activity', true)), current_time('timestamp'))) . ' ' . esc_html__('ago', 'elev8-os') . '</small></span><b>' . ($thread_unread ? esc_html((string)$thread_unread) : '✓') . '</b></a>';
                }
                echo '</div>';
            } else {
                echo '<p class="elev8-embedded-conversation__empty">' . esc_html__('No conversation is attached to this record yet.', 'elev8-os') . '</p>';
            }
            self::render_create_form($context_type, $context_id, $title, $return_url, $default_participants, $user);
        }
        echo '</div>';
        return (string)ob_get_clean();
    }

    private static function render_thread(WP_Post $thread, string $return_url, string $context_type, int $context_id, WP_User $user): void {
        $thread_id = (int)$thread->ID;
        $back = remove_query_arg(['elev8_context_conversation', 'elev8_open_context'], $return_url) . '#elev8-conversation-' . rawurlencode($context_type . '-' . $context_id);
        $read_at = Elev8_OS_Conversation_Service::last_read_at($thread_id, (int)$user->ID);
        $first_unread_printed = false;
        echo '<div class="elev8-embedded-conversation__toolbar"><a href="' . esc_url($back) . '">← ' . esc_html__('Threads', 'elev8-os') . '</a><a href="' . esc_url(add_query_arg('conversation', $thread_id, Elev8_OS_Conversations_Module::url())) . '">' . esc_html__('Open full conversation', 'elev8-os') . ' ↗</a></div>';
        echo '<h4>' . esc_html(get_the_title($thread)) . '</h4><div class="elev8-embedded-conversation__messages" data-elev8-message-list>';
        foreach (Elev8_OS_Conversation_Service::messages($thread_id) as $message) {
            $is_unread = (int)$message->post_author !== (int)$user->ID && ($read_at === '' || strtotime((string)$message->post_date) > strtotime($read_at));
            if ($is_unread && !$first_unread_printed) { echo '<div class="elev8-conversation-new-divider" data-elev8-first-unread>' . esc_html__('New messages', 'elev8-os') . '</div>'; $first_unread_printed = true; }
            $author = get_userdata((int)$message->post_author);
            echo '<article class="elev8-conversation-message' . ((int)$message->post_author === (int)$user->ID ? ' is-mine' : '') . ($is_unread ? ' is-new' : '') . '"><div class="elev8-conversation-message__head"><strong>' . esc_html($author instanceof WP_User ? $author->display_name : __('Former user', 'elev8-os')) . '</strong><time>' . esc_html(get_the_date('M j, g:i a', $message)) . '</time></div><p>' . nl2br(esc_html((string)$message->post_content)) . '</p></article>';
        }
        echo '<div data-elev8-latest-message></div></div>';
        Elev8_OS_Conversation_Service::mark_read($thread_id, (int)$user->ID);
        echo '<form class="elev8-embedded-conversation__reply" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('elev8_reply_context_conversation_' . $thread_id);
        echo '<input type="hidden" name="action" value="elev8_reply_context_conversation"><input type="hidden" name="thread_id" value="' . esc_attr((string)$thread_id) . '"><input type="hidden" name="return_url" value="' . esc_attr($return_url) . '"><input type="hidden" name="context_type" value="' . esc_attr($context_type) . '"><input type="hidden" name="context_id" value="' . esc_attr((string)$context_id) . '"><textarea name="message" rows="3" required placeholder="' . esc_attr__('Write a reply…', 'elev8-os') . '"></textarea><button class="button button-primary">' . esc_html__('Send reply', 'elev8-os') . '</button></form>';
    }

    /** @param array<int,int> $default_participants */
    private static function render_create_form(string $context_type, int $context_id, string $title, string $return_url, array $default_participants, WP_User $user): void {
        echo '<details class="elev8-embedded-conversation__create"><summary>' . esc_html__('Start a conversation', 'elev8-os') . '</summary><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('elev8_create_context_conversation_' . $context_type . '_' . $context_id);
        echo '<input type="hidden" name="action" value="elev8_create_context_conversation"><input type="hidden" name="context_type" value="' . esc_attr($context_type) . '"><input type="hidden" name="context_id" value="' . esc_attr((string)$context_id) . '"><input type="hidden" name="return_url" value="' . esc_attr($return_url) . '"><label>' . esc_html__('Subject', 'elev8-os') . '<input name="subject" required value="' . esc_attr($title) . '"></label><label>' . esc_html__('Message', 'elev8-os') . '<textarea name="message" rows="3" required></textarea></label>';
        $people = Elev8_OS_Conversation_Service::recipient_users();
        if ($people) {
            echo '<fieldset><legend>' . esc_html__('Include people', 'elev8-os') . '</legend><div class="elev8-embedded-conversation__people">';
            foreach ($people as $person) {
                if (!$person instanceof WP_User || (int)$person->ID === (int)$user->ID) { continue; }
                echo '<label><input type="checkbox" name="participant_user_ids[]" value="' . esc_attr((string)$person->ID) . '" ' . checked(in_array((int)$person->ID, $default_participants, true), true, false) . '> ' . esc_html($person->display_name) . '</label>';
            }
            echo '</div></fieldset>';
        }
        echo '<button class="button button-primary">' . esc_html__('Start conversation', 'elev8-os') . '</button></form></details>';
    }

    public static function handle_create(): void {
        $context_type = sanitize_key((string)($_POST['context_type'] ?? ''));
        $context_id = absint($_POST['context_id'] ?? 0);
        check_admin_referer('elev8_create_context_conversation_' . $context_type . '_' . $context_id);
        $user = self::effective_user();
        if (!Elev8_OS_Access_Service::user_can('view_conversations', $user)) { wp_die(esc_html__('Permission denied.', 'elev8-os')); }
        $thread_id = Elev8_OS_Conversation_Service::create_thread([
            'subject' => wp_unslash((string)($_POST['subject'] ?? '')),
            'message' => wp_unslash((string)($_POST['message'] ?? '')),
            'participant_user_ids' => (array)($_POST['participant_user_ids'] ?? []),
            'creator_user_id' => (int)$user->ID,
            'context_type' => $context_type,
            'context_id' => $context_id,
        ]);
        $return = self::safe_return_url((string)($_POST['return_url'] ?? ''));
        wp_safe_redirect(add_query_arg(['elev8_context_conversation' => $thread_id, 'elev8_open_context' => 1], $return) . '#elev8-conversation-' . rawurlencode($context_type . '-' . $context_id));
        exit;
    }

    public static function handle_reply(): void {
        $thread_id = absint($_POST['thread_id'] ?? 0);
        check_admin_referer('elev8_reply_context_conversation_' . $thread_id);
        $user = self::effective_user();
        if (!Elev8_OS_Conversation_Service::can_view($thread_id, $user)) { wp_die(esc_html__('Permission denied.', 'elev8-os')); }
        Elev8_OS_Conversation_Service::add_message($thread_id, wp_unslash((string)($_POST['message'] ?? '')), (int)$user->ID);
        $context_type = sanitize_key((string)($_POST['context_type'] ?? ''));
        $context_id = absint($_POST['context_id'] ?? 0);
        $return = self::safe_return_url((string)($_POST['return_url'] ?? ''));
        wp_safe_redirect(add_query_arg(['elev8_context_conversation' => $thread_id, 'elev8_open_context' => 1], $return) . '#elev8-conversation-' . rawurlencode($context_type . '-' . $context_id));
        exit;
    }

    private static function safe_return_url(string $url): string {
        $url = esc_url_raw($url);
        return $url !== '' ? $url : home_url('/');
    }

    private static function effective_user(): WP_User {
        if (class_exists('Elev8_OS_Preview_Service')) { $user = Elev8_OS_Preview_Service::effective_user(); if ($user instanceof WP_User && $user->ID > 0) { return $user; } }
        return wp_get_current_user();
    }
}

<?php
/** Frontend Conversation Center for Elev8 OS. */
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Conversations_Module {
    private const OPTION_PAGE_ID = 'elev8_os_conversations_page_id';
    private const SLUG = 'elev8-conversations';

    public static function init(): void {
        add_shortcode('elev8_os_conversations', [__CLASS__, 'shortcode']);
        add_action('admin_init', [__CLASS__, 'ensure_page_for_admin']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue']);
        add_action('admin_post_elev8_os_create_conversation', [__CLASS__, 'create']);
        add_action('admin_post_elev8_os_reply_conversation', [__CLASS__, 'reply']);
        add_action('admin_post_elev8_os_close_conversation', [__CLASS__, 'close']);
        add_filter('elev8_os_application_shell_frontend', [__CLASS__, 'shell_page']);
        add_filter('elev8_os_command_palette_commands', [__CLASS__, 'command'], 10, 2);
    }

    public static function activate(): void {
        self::ensure_page(true);
    }

    public static function ensure_page_for_admin(): void {
        if (current_user_can('manage_options')) { self::ensure_page(true); }
    }

    public static function enqueue(): void {
        if (!self::is_page()) { return; }
        wp_enqueue_style('elev8-os-conversations', ELEV8_OS_URL . 'assets/css/conversations.css', [], ELEV8_OS_VERSION);
    }

    public static function shortcode(): string {
        if (!is_user_logged_in()) { return '<p>' . esc_html__('Please sign in to view conversations.', 'elev8-os') . '</p>'; }
        $user = wp_get_current_user();
        if (!Elev8_OS_Access_Service::user_can('view_conversations', $user)) { return '<p>' . esc_html__('You do not have permission to view conversations.', 'elev8-os') . '</p>'; }

        ob_start();
        $thread_id = absint($_GET['conversation'] ?? 0);
        echo '<div class="elev8-conversations">';
        echo '<header class="elev8-conversations__hero"><div><p>' . esc_html__('Elev8 OS', 'elev8-os') . '</p><h1>' . esc_html__('Conversations', 'elev8-os') . '</h1><span>' . esc_html__('Keep questions, decisions, and follow-up connected to the work.', 'elev8-os') . '</span></div><a class="elev8-conversations__new" href="#new-conversation">' . esc_html__('New Conversation', 'elev8-os') . '</a></header>';
        if ($thread_id > 0 && Elev8_OS_Conversation_Service::can_view($thread_id, $user)) {
            self::render_thread($thread_id, $user);
        } else {
            self::render_inbox($user);
        }
        self::render_create_form($user);
        echo '</div>';
        return (string) ob_get_clean();
    }

    private static function render_inbox(WP_User $user): void {
        $threads = Elev8_OS_Conversation_Service::threads_for_user($user->ID);
        echo '<section class="elev8-conversations__inbox"><div class="elev8-conversations__section-head"><h2>' . esc_html__('Your Conversations', 'elev8-os') . '</h2><span>' . esc_html(sprintf(_n('%d conversation', '%d conversations', count($threads), 'elev8-os'), count($threads))) . '</span></div>';
        if (!$threads) { echo '<div class="elev8-conversations__empty"><strong>' . esc_html__('No conversations yet.', 'elev8-os') . '</strong><p>' . esc_html__('Start one when a question, decision, or follow-up should stay connected and visible.', 'elev8-os') . '</p></div>'; }
        foreach ($threads as $thread) {
            $participants = self::participant_names($thread->ID, $user->ID);
            $messages = Elev8_OS_Conversation_Service::messages($thread->ID);
            $status = (string) get_post_meta($thread->ID, '_elev8_conversation_status', true) ?: 'open';
            $last = (string) get_post_meta($thread->ID, '_elev8_conversation_last_activity', true);
            $read = (string) get_post_meta($thread->ID, '_elev8_conversation_read_' . $user->ID, true);
            $unread = $last !== '' && ($read === '' || $last > $read);
            echo '<a class="elev8-conversation-card' . ($unread ? ' is-unread' : '') . '" href="' . esc_url(add_query_arg('conversation', $thread->ID, self::url())) . '">';
            echo '<div><span class="elev8-conversation-card__status">' . esc_html(ucfirst($status)) . '</span><h3>' . esc_html(get_the_title($thread)) . '</h3><p>' . esc_html($participants ?: __('Only you', 'elev8-os')) . '</p></div>';
            echo '<div class="elev8-conversation-card__meta"><strong>' . (int) count($messages) . '</strong><span>' . esc_html__('messages', 'elev8-os') . '</span><small>' . esc_html($last ? human_time_diff(strtotime($last), current_time('timestamp')) . ' ago' : __('Unavailable', 'elev8-os')) . '</small></div></a>';
        }
        echo '</section>';
    }

    private static function render_thread(int $thread_id, WP_User $user): void {
        Elev8_OS_Conversation_Service::mark_read($thread_id, $user->ID);
        $thread = get_post($thread_id);
        if (!$thread instanceof WP_Post) { return; }
        $status = (string) get_post_meta($thread_id, '_elev8_conversation_status', true) ?: 'open';
        echo '<section class="elev8-conversation-thread"><a class="elev8-conversation-thread__back" href="' . esc_url(self::url()) . '">← ' . esc_html__('All conversations', 'elev8-os') . '</a><header><div><span>' . esc_html(ucfirst($status)) . '</span><h2>' . esc_html(get_the_title($thread)) . '</h2><p>' . esc_html(self::participant_names($thread_id, 0)) . '</p></div>';
        if ($status === 'open' && ((int) $thread->post_author === $user->ID || Elev8_OS_Access_Service::user_can('manage_conversations', $user))) {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">'; wp_nonce_field('elev8_close_conversation_' . $thread_id); echo '<input type="hidden" name="action" value="elev8_os_close_conversation"><input type="hidden" name="thread_id" value="' . $thread_id . '"><button>' . esc_html__('Close Conversation', 'elev8-os') . '</button></form>';
        }
        echo '</header><div class="elev8-conversation-thread__messages">';
        foreach (Elev8_OS_Conversation_Service::messages($thread_id) as $message) {
            $author = get_userdata((int) $message->post_author);
            echo '<article class="elev8-conversation-message' . ((int) $message->post_author === $user->ID ? ' is-mine' : '') . '"><div class="elev8-conversation-message__head"><strong>' . esc_html($author instanceof WP_User ? $author->display_name : __('Former user', 'elev8-os')) . '</strong><time>' . esc_html(get_the_date('M j, Y g:i a', $message)) . '</time></div><div>' . wp_kses_post(wpautop($message->post_content)) . '</div></article>';
        }
        echo '</div>';
        if ($status === 'open') {
            echo '<form class="elev8-conversation-reply" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">'; wp_nonce_field('elev8_reply_conversation_' . $thread_id); echo '<input type="hidden" name="action" value="elev8_os_reply_conversation"><input type="hidden" name="thread_id" value="' . $thread_id . '"><label>' . esc_html__('Reply', 'elev8-os') . '<textarea name="message" rows="4" required placeholder="' . esc_attr__('Write a reply. Use @username to include another Elev8 OS user.', 'elev8-os') . '"></textarea></label><button>' . esc_html__('Send Reply', 'elev8-os') . '</button></form>';
        }
        echo '</section>';
    }

    private static function render_create_form(WP_User $user): void {
        echo '<section class="elev8-conversations__create" id="new-conversation"><h2>' . esc_html__('Start a Conversation', 'elev8-os') . '</h2><p>' . esc_html__('Use a conversation for a question or decision that should remain visible and searchable.', 'elev8-os') . '</p><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('elev8_create_conversation'); echo '<input type="hidden" name="action" value="elev8_os_create_conversation"><label>' . esc_html__('Subject', 'elev8-os') . '<input name="subject" required maxlength="160"></label><label>' . esc_html__('People', 'elev8-os') . '<select name="participant_user_ids[]" multiple size="6">';
        foreach (Elev8_OS_Access_Service::assignment_users_grouped() as $group => $users) { echo '<optgroup label="' . esc_attr($group) . '">'; foreach ($users as $person) { if ($person->ID === $user->ID) { continue; } echo '<option value="' . (int) $person->ID . '">' . esc_html($person->display_name) . '</option>'; } echo '</optgroup>'; }
        echo '</select><small>' . esc_html__('Hold Ctrl or Command to select more than one person.', 'elev8-os') . '</small></label><label>' . esc_html__('Message', 'elev8-os') . '<textarea name="message" rows="5" required></textarea></label><button>' . esc_html__('Start Conversation', 'elev8-os') . '</button></form></section>';
    }

    public static function create(): void {
        $user = wp_get_current_user();
        if (!Elev8_OS_Access_Service::user_can('view_conversations', $user)) { wp_die(esc_html__('Permission denied.', 'elev8-os')); }
        check_admin_referer('elev8_create_conversation');
        $thread_id = Elev8_OS_Conversation_Service::create_thread(['subject' => wp_unslash($_POST['subject'] ?? ''), 'message' => wp_unslash($_POST['message'] ?? ''), 'participant_user_ids' => (array) ($_POST['participant_user_ids'] ?? []), 'creator_user_id' => $user->ID]);
        wp_safe_redirect($thread_id ? add_query_arg('conversation', $thread_id, self::url()) : self::url()); exit;
    }

    public static function reply(): void {
        $thread_id = absint($_POST['thread_id'] ?? 0); check_admin_referer('elev8_reply_conversation_' . $thread_id);
        Elev8_OS_Conversation_Service::add_message($thread_id, wp_unslash($_POST['message'] ?? ''), get_current_user_id());
        wp_safe_redirect(add_query_arg('conversation', $thread_id, self::url())); exit;
    }

    public static function close(): void {
        $thread_id = absint($_POST['thread_id'] ?? 0); check_admin_referer('elev8_close_conversation_' . $thread_id);
        Elev8_OS_Conversation_Service::close($thread_id, wp_get_current_user());
        wp_safe_redirect(add_query_arg('conversation', $thread_id, self::url())); exit;
    }

    public static function command(array $commands, WP_User $user): array {
        if (Elev8_OS_Access_Service::user_can('view_conversations', $user)) {
            $commands[] = ['id' => 'conversations', 'label' => __('Conversations', 'elev8-os'), 'description' => __('Open questions, decisions, replies, and team follow-up.', 'elev8-os'), 'url' => self::url(), 'group' => 'communication', 'icon' => '💬', 'type' => 'command'];
        }
        return $commands;
    }

    public static function shell_page(bool $render): bool { return $render || self::is_page(); }
    public static function is_page(): bool { return is_page(self::page_id()) || is_page(self::SLUG); }
    public static function url(): string { $id = self::page_id(); return $id ? (string) get_permalink($id) : home_url('/' . self::SLUG . '/'); }

    private static function page_id(): int { return absint(get_option(self::OPTION_PAGE_ID)); }
    private static function ensure_page(bool $create): int {
        $id = self::page_id(); if ($id && get_post_status($id)) { return $id; }
        $page = get_page_by_path(self::SLUG, OBJECT, 'page');
        if ($page instanceof WP_Post) { update_option(self::OPTION_PAGE_ID, $page->ID, false); return (int) $page->ID; }
        if (!$create) { return 0; }
        $id = wp_insert_post(['post_title' => __('Conversations', 'elev8-os'), 'post_name' => self::SLUG, 'post_content' => '[elev8_os_conversations]', 'post_status' => 'publish', 'post_type' => 'page', 'comment_status' => 'closed'], true);
        if (!is_wp_error($id) && $id > 0) { update_option(self::OPTION_PAGE_ID, (int) $id, false); return (int) $id; }
        return 0;
    }

    private static function participant_names(int $thread_id, int $exclude): string {
        $names = []; foreach (Elev8_OS_Conversation_Service::participants($thread_id) as $id) { if ($id === $exclude) { continue; } $u = get_userdata($id); if ($u instanceof WP_User) { $names[] = $u->display_name; } }
        return implode(', ', $names);
    }
}

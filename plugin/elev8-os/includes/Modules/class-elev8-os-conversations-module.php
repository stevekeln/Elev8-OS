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
        add_action('admin_post_elev8_os_pin_conversation', [__CLASS__, 'pin']);
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
        wp_enqueue_style('elev8-workspace-button', ELEV8_OS_URL . 'assets/css/workspace.css', [], ELEV8_OS_VERSION);
        wp_enqueue_script('elev8-os-conversations', ELEV8_OS_URL . 'assets/js/conversations.js', [], ELEV8_OS_VERSION, true);
        wp_localize_script('elev8-os-conversations', 'Elev8OSConversations', ['dashboardUrl' => self::dashboard_url()]);
    }

    public static function shortcode(): string {
        if (!is_user_logged_in()) { return '<p>' . esc_html__('Please sign in to view conversations.', 'elev8-os') . '</p>'; }
        $user = self::effective_user();
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
            $status = (string) get_post_meta($thread->ID, '_elev8_conversation_status', true) ?: 'open';
            $last = (string) get_post_meta($thread->ID, '_elev8_conversation_last_activity', true);
            $unread_count = Elev8_OS_Conversation_Service::unread_messages_count((int) $thread->ID, $user->ID);
            echo '<a class="elev8-conversation-card' . ($unread_count > 0 ? ' is-unread' : '') . '" href="' . esc_url(add_query_arg('conversation', $thread->ID, self::url())) . '">';
            echo '<div><span class="elev8-conversation-card__status">' . esc_html(ucfirst($status)) . '</span><h3>' . esc_html(get_the_title($thread)) . '</h3><p>' . esc_html($participants ?: __('Only you', 'elev8-os')) . '</p></div>';
            echo '<div class="elev8-conversation-card__meta">';
            if ($unread_count > 0) { echo '<strong>' . (int) $unread_count . '</strong><span>' . esc_html(_n('unread message', 'unread messages', $unread_count, 'elev8-os')) . '</span>'; }
            else { echo '<strong aria-hidden="true">✓</strong><span>' . esc_html__('All read', 'elev8-os') . '</span>'; }
            echo '<small>' . esc_html($last ? human_time_diff(strtotime($last), current_time('timestamp')) . ' ago' : __('Unavailable', 'elev8-os')) . '</small></div></a>';
        }
        echo '</section>';
    }

    private static function render_thread(int $thread_id, WP_User $user): void {
        $last_read = Elev8_OS_Conversation_Service::last_read_at($thread_id, $user->ID);
        $thread = get_post($thread_id);
        if (!$thread instanceof WP_Post) { return; }
        $status = (string) get_post_meta($thread_id, '_elev8_conversation_status', true) ?: 'open';
        echo '<section class="elev8-conversation-thread" data-elev8-conversation-thread><div class="elev8-conversation-thread__nav"><a class="elev8-conversation-thread__dashboard" href="' . esc_url(self::dashboard_url()) . '">⌂ ' . esc_html__('My Dashboard', 'elev8-os') . '</a><a class="elev8-conversation-thread__back" href="' . esc_url(self::url()) . '">← ' . esc_html__('All conversations', 'elev8-os') . '</a>' . (class_exists('Elev8_OS_Workspace_Service') ? '<a class="elev8-open-workspace" href="' . esc_url(Elev8_OS_Workspace_Service::url('conversation', $thread_id)) . '">' . esc_html__('Open Workspace', 'elev8-os') . '</a>' : '') . '</div><header><div><span>' . esc_html(ucfirst($status)) . '</span><h2>' . esc_html(get_the_title($thread)) . '</h2><p>' . esc_html(self::participant_names($thread_id, 0)) . '</p></div>';
        echo '<div class="elev8-conversation-thread__actions">';
        if (Elev8_OS_Access_Service::user_can('manage_business_memory', $user)) {
            $memory_id = Elev8_OS_Conversation_Service::pinned_memory_id($thread_id);
            if ($memory_id > 0) {
                echo '<span class="elev8-conversation-pinned">📌 ' . esc_html__('Pinned to Business Memory', 'elev8-os') . '</span>';
            } else {
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">'; wp_nonce_field('elev8_pin_conversation_' . $thread_id); echo '<input type="hidden" name="action" value="elev8_os_pin_conversation"><input type="hidden" name="thread_id" value="' . $thread_id . '"><button class="is-secondary">📌 ' . esc_html__('Pin to Business Memory', 'elev8-os') . '</button></form>';
            }
        }
        if ($status === 'open' && ((int) $thread->post_author === $user->ID || Elev8_OS_Access_Service::user_can('manage_conversations', $user))) {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">'; wp_nonce_field('elev8_close_conversation_' . $thread_id); echo '<input type="hidden" name="action" value="elev8_os_close_conversation"><input type="hidden" name="thread_id" value="' . $thread_id . '"><button>' . esc_html__('Close Conversation', 'elev8-os') . '</button></form>';
        }
        echo '</div>';
        echo '</header><div class="elev8-conversation-thread__jump"><button type="button" data-elev8-jump-new>' . esc_html__('Jump to new messages', 'elev8-os') . '</button><button type="button" data-elev8-jump-latest>' . esc_html__('Jump to newest', 'elev8-os') . '</button></div><div class="elev8-conversation-thread__messages" data-elev8-message-list>';
        $new_divider_added = false;
        foreach (Elev8_OS_Conversation_Service::messages($thread_id) as $message) {
            $is_unread = (int) $message->post_author !== $user->ID && ($last_read === '' || strtotime((string) $message->post_date) > strtotime($last_read));
            if ($is_unread && !$new_divider_added) { echo '<div class="elev8-conversation-new-divider" data-elev8-first-unread><span>' . esc_html__('New messages', 'elev8-os') . '</span></div>'; $new_divider_added = true; }
            $author = get_userdata((int) $message->post_author);
            echo '<article class="elev8-conversation-message' . ((int) $message->post_author === $user->ID ? ' is-mine' : '') . ($is_unread ? ' is-new' : '') . '"><div class="elev8-conversation-message__head"><strong>' . esc_html($author instanceof WP_User ? $author->display_name : __('Former user', 'elev8-os')) . '</strong><time>' . esc_html(get_the_date('M j, Y g:i a', $message)) . '</time></div><div>' . wp_kses_post(wpautop($message->post_content)) . '</div>';
            $attachments = Elev8_OS_Conversation_Service::message_attachments((int) $message->ID);
            if ($attachments) { echo '<div class="elev8-conversation-attachments">'; foreach ($attachments as $attachment_id) { $url = wp_get_attachment_url($attachment_id); if (!$url) { continue; } echo '<a href="' . esc_url($url) . '" target="_blank" rel="noopener">📎 ' . esc_html(get_the_title($attachment_id) ?: __('Attachment', 'elev8-os')) . '</a>'; } echo '</div>'; }
            echo '</article>';
        }
        echo '<div data-elev8-latest-message></div></div>';
        Elev8_OS_Conversation_Service::mark_read($thread_id, $user->ID);
        if ($status === 'open') {
            echo '<form class="elev8-conversation-reply" method="post" enctype="multipart/form-data" action="' . esc_url(admin_url('admin-post.php')) . '">'; wp_nonce_field('elev8_reply_conversation_' . $thread_id); echo '<input type="hidden" name="action" value="elev8_os_reply_conversation"><input type="hidden" name="thread_id" value="' . $thread_id . '"><label>' . esc_html__('Reply', 'elev8-os') . '<textarea name="message" rows="4" required placeholder="' . esc_attr__('Write a reply. Use @username to include another Elev8 OS user.', 'elev8-os') . '"></textarea></label><label>' . esc_html__('Attachments', 'elev8-os') . '<input type="file" name="attachments[]" multiple accept="image/*,.pdf,.doc,.docx,.txt"></label><button>' . esc_html__('Send Reply', 'elev8-os') . '</button></form>';
        }
        echo '</section>';
    }

    private static function render_create_form(WP_User $user): void {
        $groups = Elev8_OS_Conversation_Service::recipient_groups();
        echo '<section class="elev8-conversations__create" id="new-conversation"><h2>' . esc_html__('Start a Conversation', 'elev8-os') . '</h2><p>' . esc_html__('Search for one person, several people, or select an entire team.', 'elev8-os') . '</p><form method="post" enctype="multipart/form-data" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('elev8_create_conversation');
        echo '<input type="hidden" name="action" value="elev8_os_create_conversation"><label>' . esc_html__('Subject', 'elev8-os') . '<input name="subject" required maxlength="160"></label>';
        echo '<div class="elev8-recipient-picker" data-elev8-recipient-picker><label>' . esc_html__('People and teams', 'elev8-os') . '<input type="search" class="elev8-recipient-search" placeholder="' . esc_attr__('Search Steve, Nick, Glass Team…', 'elev8-os') . '"></label><div class="elev8-recipient-selected" aria-live="polite"></div>';
        foreach ($groups as $group => $people) {
            echo '<section class="elev8-recipient-group" data-group="' . esc_attr(strtolower((string) $group)) . '"><header><strong>' . esc_html($group) . '</strong><button type="button" class="elev8-select-team">' . esc_html__('Select team', 'elev8-os') . '</button></header><div class="elev8-recipient-options">';
            foreach ($people as $person) {
                if (!$person instanceof WP_User || $person->ID === $user->ID) { continue; }
                $label = trim($person->display_name . ' ' . $person->user_email . ' ' . $person->user_login);
                echo '<label class="elev8-recipient-option" data-search="' . esc_attr(strtolower($label . ' ' . $group)) . '"><input type="checkbox" name="participant_user_ids[]" value="' . (int) $person->ID . '"><span><strong>' . esc_html($person->display_name) . '</strong><small>' . esc_html($person->user_email) . '</small></span></label>';
            }
            echo '</div></section>';
        }
        echo '</div><label>' . esc_html__('Message', 'elev8-os') . '<textarea name="message" rows="5" required></textarea></label><label>' . esc_html__('Attachments', 'elev8-os') . '<input type="file" name="attachments[]" multiple accept="image/*,.pdf,.doc,.docx,.txt"></label><button>' . esc_html__('Start Conversation', 'elev8-os') . '</button></form></section>';
    }

    public static function create(): void {
        $user = self::effective_user();
        if (!Elev8_OS_Access_Service::user_can('view_conversations', $user)) { wp_die(esc_html__('Permission denied.', 'elev8-os')); }
        check_admin_referer('elev8_create_conversation');
        $thread_id = Elev8_OS_Conversation_Service::create_thread(['subject' => wp_unslash($_POST['subject'] ?? ''), 'message' => wp_unslash($_POST['message'] ?? ''), 'participant_user_ids' => (array) ($_POST['participant_user_ids'] ?? []), 'creator_user_id' => $user->ID]);
        if ($thread_id && !empty($_FILES['attachments'])) { $ids = self::upload_attachments((array) $_FILES['attachments'], $thread_id); if ($ids) { $messages = Elev8_OS_Conversation_Service::messages($thread_id); $first = $messages ? reset($messages) : null; if ($first instanceof WP_Post) { update_post_meta($first->ID, Elev8_OS_Conversation_Service::META_ATTACHMENTS, $ids); } } }
        wp_safe_redirect($thread_id ? add_query_arg('conversation', $thread_id, self::url()) : self::url()); exit;
    }

    public static function reply(): void {
        $thread_id = absint($_POST['thread_id'] ?? 0); check_admin_referer('elev8_reply_conversation_' . $thread_id);
        $user = self::effective_user();
        $attachments = !empty($_FILES['attachments']) ? self::upload_attachments((array) $_FILES['attachments'], $thread_id) : [];
        Elev8_OS_Conversation_Service::add_message($thread_id, wp_unslash($_POST['message'] ?? ''), $user->ID, $attachments);
        wp_safe_redirect(add_query_arg('conversation', $thread_id, self::url())); exit;
    }

    public static function close(): void {
        $thread_id = absint($_POST['thread_id'] ?? 0); check_admin_referer('elev8_close_conversation_' . $thread_id);
        Elev8_OS_Conversation_Service::close($thread_id, self::effective_user());
        wp_safe_redirect(add_query_arg('conversation', $thread_id, self::url())); exit;
    }

    public static function pin(): void {
        $thread_id = absint($_POST['thread_id'] ?? 0);
        check_admin_referer('elev8_pin_conversation_' . $thread_id);
        Elev8_OS_Conversation_Service::pin_to_business_memory($thread_id, self::effective_user());
        wp_safe_redirect(add_query_arg('conversation', $thread_id, self::url())); exit;
    }

    /** @return array<int,int> */
    private static function upload_attachments(array $files, int $parent_id): array {
        if (empty($files['name']) || !is_array($files['name'])) { return []; }
        require_once ABSPATH . 'wp-admin/includes/file.php'; require_once ABSPATH . 'wp-admin/includes/media.php'; require_once ABSPATH . 'wp-admin/includes/image.php';
        $ids = []; $count = min(8, count($files['name']));
        for ($i=0; $i<$count; $i++) {
            if ((int) ($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) { continue; }
            $_FILES['elev8_conversation_attachment'] = ['name'=>$files['name'][$i],'type'=>$files['type'][$i],'tmp_name'=>$files['tmp_name'][$i],'error'=>$files['error'][$i],'size'=>$files['size'][$i]];
            $id = media_handle_upload('elev8_conversation_attachment', $parent_id, [], ['test_form'=>false]); if (!is_wp_error($id)) { $ids[]=(int)$id; }
        }
        unset($_FILES['elev8_conversation_attachment']); return $ids;
    }

    private static function effective_user(): WP_User {
        if (class_exists('Elev8_OS_Preview_Service')) { $user = Elev8_OS_Preview_Service::effective_user(); if ($user instanceof WP_User && $user->ID > 0) { return $user; } }
        return wp_get_current_user();
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

    private static function dashboard_url(): string {
        $user = self::effective_user();
        if (class_exists('Elev8_OS_Portal_Page_Manager')) {
            $url = class_exists('Elev8_OS_Workspace_Resolver_Service') ? Elev8_OS_Workspace_Resolver_Service::primary_destination_for($user) : Elev8_OS_Portal_Page_Manager::get_url('dashboard');
            if (is_string($url) && $url !== '') { return $url; }
        }
        return admin_url('admin.php?page=elev8-os');
    }

    private static function participant_names(int $thread_id, int $exclude): string {
        $names = []; foreach (Elev8_OS_Conversation_Service::participants($thread_id) as $id) { if ($id === $exclude) { continue; } $u = get_userdata($id); if ($u instanceof WP_User) { $names[] = $u->display_name; } }
        return implode(', ', $names);
    }
}

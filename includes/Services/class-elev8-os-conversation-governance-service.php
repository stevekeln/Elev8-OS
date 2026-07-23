<?php
/**
 * Governed summaries and explicit follow-up promotion for Conversations.
 */
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Conversation_Governance_Service {
    private const META_SUMMARY = '_elev8_conversation_governed_summary';
    private const META_SUMMARY_BY = '_elev8_conversation_summary_user_id';
    private const META_SUMMARY_AT = '_elev8_conversation_summary_at';

    public static function init(): void {
        add_action('admin_post_elev8_save_conversation_summary', [__CLASS__, 'handle_save_summary']);
        add_action('admin_post_elev8_create_conversation_followup', [__CLASS__, 'handle_create_followup']);
    }

    public static function summary(int $thread_id): string {
        return $thread_id > 0 ? (string) get_post_meta($thread_id, self::META_SUMMARY, true) : '';
    }

    public static function render(int $thread_id, string $return_url, string $context_type, int $context_id, WP_User $user): string {
        if ($thread_id < 1 || !Elev8_OS_Conversation_Service::can_view($thread_id, $user)) { return ''; }
        $summary = self::summary($thread_id);
        $manage = Elev8_OS_Access_Service::user_can('manage_conversations', $user)
            || Elev8_OS_Access_Service::user_can('manage_operations', $user)
            || Elev8_OS_Access_Service::user_can('manage_work', $user);
        $summary_by = absint(get_post_meta($thread_id, self::META_SUMMARY_BY, true));
        $summary_user = $summary_by ? get_userdata($summary_by) : false;
        $summary_at = (string) get_post_meta($thread_id, self::META_SUMMARY_AT, true);

        ob_start();
        echo '<section class="elev8-conversation-governance">';
        echo '<details' . ($summary === '' ? '' : ' open') . '><summary>' . esc_html__('Conversation summary', 'elev8-os') . '</summary>';
        if ($summary !== '') {
            echo '<div class="elev8-conversation-governance__summary"><p>' . nl2br(esc_html($summary)) . '</p>';
            if ($summary_user instanceof WP_User || $summary_at !== '') {
                echo '<small>' . esc_html(sprintf(__('Last updated by %1$s on %2$s', 'elev8-os'), $summary_user instanceof WP_User ? $summary_user->display_name : __('a team member', 'elev8-os'), $summary_at !== '' ? mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $summary_at) : __('an unknown date', 'elev8-os'))) . '</small>';
            }
            echo '</div>';
        } else {
            echo '<p class="elev8-conversation-governance__empty">' . esc_html__('No governed summary has been recorded yet.', 'elev8-os') . '</p>';
        }
        if ($manage) {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('elev8_save_conversation_summary_' . $thread_id);
            echo '<input type="hidden" name="action" value="elev8_save_conversation_summary"><input type="hidden" name="thread_id" value="' . esc_attr((string)$thread_id) . '"><input type="hidden" name="return_url" value="' . esc_attr($return_url) . '"><input type="hidden" name="context_type" value="' . esc_attr($context_type) . '"><input type="hidden" name="context_id" value="' . esc_attr((string)$context_id) . '"><textarea name="summary" rows="3" placeholder="' . esc_attr__('Record the objective decisions, risks, and next steps from this conversation.', 'elev8-os') . '">' . esc_textarea($summary) . '</textarea><button class="button">' . esc_html__('Save summary', 'elev8-os') . '</button></form>';
        }
        echo '</details>';

        echo '<details><summary>' . esc_html__('Create explicit follow-up', 'elev8-os') . '</summary>';
        echo '<p><small>' . esc_html__('This intentionally creates one Universal Work Item. Nothing is extracted or assigned silently.', 'elev8-os') . '</small></p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="elev8-conversation-governance__followup">';
        wp_nonce_field('elev8_create_conversation_followup_' . $thread_id);
        echo '<input type="hidden" name="action" value="elev8_create_conversation_followup"><input type="hidden" name="thread_id" value="' . esc_attr((string)$thread_id) . '"><input type="hidden" name="return_url" value="' . esc_attr($return_url) . '"><input type="hidden" name="context_type" value="' . esc_attr($context_type) . '"><input type="hidden" name="context_id" value="' . esc_attr((string)$context_id) . '">';
        echo '<label>' . esc_html__('Follow-up title', 'elev8-os') . '<input name="title" required value="' . esc_attr(sprintf(__('Follow up: %s', 'elev8-os'), get_the_title($thread_id))) . '"></label>';
        echo '<label>' . esc_html__('Assign to', 'elev8-os') . '<select name="owner_user_id"><option value="0">' . esc_html__('Unassigned', 'elev8-os') . '</option>';
        foreach (Elev8_OS_Access_Service::assignment_users_grouped() as $group => $users) {
            echo '<optgroup label="' . esc_attr($group) . '">';
            foreach ($users as $candidate) { if ($candidate instanceof WP_User) { echo '<option value="' . esc_attr((string)$candidate->ID) . '">' . esc_html($candidate->display_name) . '</option>'; } }
            echo '</optgroup>';
        }
        echo '</select></label>';
        echo '<label>' . esc_html__('Due date', 'elev8-os') . '<input type="date" name="due_date"></label>';
        echo '<label>' . esc_html__('Priority', 'elev8-os') . '<select name="priority">';
        foreach (Elev8_OS_Work_Service::priorities() as $key => $label) { echo '<option value="' . esc_attr($key) . '">' . esc_html($label) . '</option>'; }
        echo '</select></label>';
        echo '<label class="is-wide">' . esc_html__('Instructions', 'elev8-os') . '<textarea name="description" rows="3" required placeholder="' . esc_attr__('Describe the explicit follow-up and the expected result.', 'elev8-os') . '"></textarea></label>';
        echo '<button class="button button-primary">' . esc_html__('Create follow-up Work Item', 'elev8-os') . '</button></form></details>';
        echo '</section>';
        return (string) ob_get_clean();
    }

    public static function handle_save_summary(): void {
        $thread_id = absint($_POST['thread_id'] ?? 0);
        check_admin_referer('elev8_save_conversation_summary_' . $thread_id);
        $user = self::effective_user();
        if (!Elev8_OS_Conversation_Service::can_view($thread_id, $user)) { wp_die(esc_html__('Permission denied.', 'elev8-os')); }
        if (!Elev8_OS_Access_Service::user_can('manage_conversations', $user) && !Elev8_OS_Access_Service::user_can('manage_operations', $user) && !Elev8_OS_Access_Service::user_can('manage_work', $user)) { wp_die(esc_html__('Permission denied.', 'elev8-os')); }
        update_post_meta($thread_id, self::META_SUMMARY, sanitize_textarea_field(wp_unslash((string)($_POST['summary'] ?? ''))));
        update_post_meta($thread_id, self::META_SUMMARY_BY, (int)$user->ID);
        update_post_meta($thread_id, self::META_SUMMARY_AT, current_time('mysql'));
        self::redirect_back($thread_id);
    }

    public static function handle_create_followup(): void {
        $thread_id = absint($_POST['thread_id'] ?? 0);
        check_admin_referer('elev8_create_conversation_followup_' . $thread_id);
        $user = self::effective_user();
        if (!Elev8_OS_Conversation_Service::can_view($thread_id, $user) || !Elev8_OS_Access_Service::user_can('view_operations', $user)) { wp_die(esc_html__('Permission denied.', 'elev8-os')); }
        $title = sanitize_text_field(wp_unslash((string)($_POST['title'] ?? '')));
        $description = sanitize_textarea_field(wp_unslash((string)($_POST['description'] ?? '')));
        if ($title === '' || $description === '') { wp_die(esc_html__('A title and instructions are required.', 'elev8-os')); }
        $work_id = Elev8_OS_Operations_Engine_Service::create_work([
            'title' => $title,
            'description' => $description,
            'type' => 'general',
            'owner_user_id' => absint($_POST['owner_user_id'] ?? 0),
            'due_date' => sanitize_text_field((string)($_POST['due_date'] ?? '')),
            'priority' => sanitize_key((string)($_POST['priority'] ?? 'normal')),
            'status' => 'requested',
            'source_type' => 'conversation_followup',
            'source_id' => $thread_id,
            'requested_by_user_id' => (int)$user->ID,
        ]);
        if (is_wp_error($work_id) || !$work_id) { wp_die(esc_html__('The follow-up Work Item could not be created.', 'elev8-os')); }
        update_post_meta((int)$work_id, '_elev8_work_conversation_thread_id', $thread_id);
        update_post_meta((int)$work_id, '_elev8_work_context_type', sanitize_key((string)($_POST['context_type'] ?? '')));
        update_post_meta((int)$work_id, '_elev8_work_context_id', absint($_POST['context_id'] ?? 0));
        Elev8_OS_Conversation_Service::add_message($thread_id, sprintf(__('Follow-up Work Item created: %1$s (#%2$d).', 'elev8-os'), $title, (int)$work_id), (int)$user->ID);
        self::redirect_back($thread_id, ['elev8_followup_created' => (int)$work_id]);
    }

    private static function redirect_back(int $thread_id, array $extra = []): void {
        $return = esc_url_raw((string)($_POST['return_url'] ?? '')) ?: home_url('/');
        $context_type = sanitize_key((string)($_POST['context_type'] ?? ''));
        $context_id = absint($_POST['context_id'] ?? 0);
        $args = array_merge(['elev8_context_conversation' => $thread_id, 'elev8_open_context' => 1], $extra);
        wp_safe_redirect(add_query_arg($args, $return) . '#elev8-conversation-' . rawurlencode($context_type . '-' . $context_id));
        exit;
    }

    private static function effective_user(): WP_User {
        if (class_exists('Elev8_OS_Preview_Service')) { $user = Elev8_OS_Preview_Service::effective_user(); if ($user instanceof WP_User && $user->ID > 0) { return $user; } }
        return wp_get_current_user();
    }
}

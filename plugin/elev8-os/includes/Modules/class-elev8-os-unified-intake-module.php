<?php
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Unified_Intake_Module {
    const ADMIN_SLUG = 'elev8-unified-intake';

    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'admin_menu'], 18);
        add_action('admin_enqueue_scripts', [__CLASS__, 'assets']);
        add_action('admin_post_elev8_os_update_intake', [__CLASS__, 'update_item']);
    }

    public static function admin_menu(): void {
        add_submenu_page('elev8-os', __('Owner Intake Dashboard', 'elev8-os'), __('Intake Dashboard', 'elev8-os'), 'manage_options', self::ADMIN_SLUG, [__CLASS__, 'render']);
    }

    public static function assets(string $hook): void {
        if ($hook !== 'elev8-os_page_' . self::ADMIN_SLUG) { return; }
        wp_enqueue_style('elev8-unified-intake', ELEV8_OS_URL . 'assets/css/unified-intake.css', [], ELEV8_OS_VERSION);
    }

    public static function update_item(): void {
        if (!current_user_can('manage_options')) { wp_die(__('You do not have permission.', 'elev8-os')); }
        $id = absint($_POST['intake_id'] ?? 0);
        check_admin_referer('elev8_intake_update_' . $id, 'elev8_intake_nonce');
        if (get_post_type($id) !== Elev8_OS_Unified_Intake_Service::POST_TYPE) { wp_die(__('Invalid intake item.', 'elev8-os')); }
        $status = sanitize_key((string) ($_POST['status'] ?? 'new'));
        if (!array_key_exists($status, Elev8_OS_Unified_Intake_Service::statuses())) { $status = 'new'; }
        update_post_meta($id, '_elev8_intake_status', $status);
        update_post_meta($id, '_elev8_intake_assigned_user', absint($_POST['assigned_user'] ?? 0));
        update_post_meta($id, '_elev8_intake_follow_up', sanitize_text_field(wp_unslash($_POST['follow_up'] ?? '')));
        update_post_meta($id, '_elev8_intake_notes', sanitize_textarea_field(wp_unslash($_POST['internal_notes'] ?? '')));
        wp_safe_redirect(admin_url('admin.php?page=' . self::ADMIN_SLUG . '&updated=1#intake-' . $id));
        exit;
    }

    public static function render(): void {
        if (!current_user_can('manage_options')) { wp_die(__('You do not have permission.', 'elev8-os')); }
        $type_filter = sanitize_key((string) ($_GET['type'] ?? ''));
        $args = [
            'post_type' => Elev8_OS_Unified_Intake_Service::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => 200,
            'orderby' => 'date',
            'order' => 'DESC',
        ];
        if ($type_filter !== '') {
            $args['meta_query'] = [['key' => '_elev8_intake_type', 'value' => $type_filter]];
        }
        $items = get_posts($args);
        $grouped = array_fill_keys(array_keys(Elev8_OS_Unified_Intake_Service::statuses()), []);
        $types = [];
        foreach ($items as $item) {
            $status = (string) get_post_meta($item->ID, '_elev8_intake_status', true);
            if (!isset($grouped[$status])) { $status = 'new'; }
            $grouped[$status][] = $item;
            $type = (string) get_post_meta($item->ID, '_elev8_intake_type', true);
            if ($type !== '') { $types[$type] = self::type_label($type); }
        }
        asort($types);
        $users = get_users(['orderby' => 'display_name', 'order' => 'ASC']);
        echo '<div class="wrap elev8-intake-admin">';
        echo '<header class="elev8-intake-hero"><div><p class="elev8-intake-eyebrow">ELEV8 OS ' . esc_html(ELEV8_OS_VERSION) . '</p><h1>Owner Intake Dashboard</h1><p>One place for reservations, volunteers, class requests, feedback, sponsors, ideas, and future communication.</p></div></header>';
        if (!empty($_GET['updated'])) { echo '<div class="notice notice-success is-dismissible"><p>Intake item updated.</p></div>'; }
        echo '<form class="elev8-intake-filter" method="get"><input type="hidden" name="page" value="' . esc_attr(self::ADMIN_SLUG) . '"><label>Submission type<select name="type"><option value="">All types</option>';
        foreach ($types as $key => $label) { echo '<option value="' . esc_attr($key) . '" ' . selected($type_filter, $key, false) . '>' . esc_html($label) . '</option>'; }
        echo '</select></label><button class="button button-primary">Filter</button></form>';
        echo '<div class="elev8-intake-board">';
        foreach (Elev8_OS_Unified_Intake_Service::statuses() as $status_key => $status_label) {
            echo '<section class="elev8-intake-column"><header><h2>' . esc_html($status_label) . '</h2><span>' . count($grouped[$status_key]) . '</span></header><div class="elev8-intake-stack">';
            if (!$grouped[$status_key]) { echo '<p class="elev8-intake-empty">Nothing here.</p>'; }
            foreach ($grouped[$status_key] as $item) { self::render_card($item, $users); }
            echo '</div></section>';
        }
        echo '</div></div>';
    }

    private static function render_card(WP_Post $item, array $users): void {
        $id = (int) $item->ID;
        $type = (string) get_post_meta($id, '_elev8_intake_type', true);
        $source = (string) get_post_meta($id, '_elev8_intake_source', true);
        $name = (string) get_post_meta($id, '_elev8_intake_name', true);
        $email = (string) get_post_meta($id, '_elev8_intake_email', true);
        $phone = (string) get_post_meta($id, '_elev8_intake_phone', true);
        $status = (string) get_post_meta($id, '_elev8_intake_status', true);
        $assigned = (int) get_post_meta($id, '_elev8_intake_assigned_user', true);
        $follow_up = (string) get_post_meta($id, '_elev8_intake_follow_up', true);
        $notes = (string) get_post_meta($id, '_elev8_intake_notes', true);
        $person_id = (int) get_post_meta($id, '_elev8_intake_person_id', true);
        echo '<article id="intake-' . $id . '" class="elev8-intake-card">';
        echo '<div class="elev8-intake-card__top"><span class="elev8-intake-type">' . esc_html(self::type_label($type)) . '</span><small>' . esc_html(get_the_date('M j, Y g:i a', $id)) . '</small></div>';
        echo '<h3>' . esc_html($item->post_title) . '</h3>';
        if ($name !== '' || $email !== '' || $phone !== '') { echo '<p class="elev8-intake-contact">' . esc_html($name) . ($email !== '' ? '<br><a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>' : '') . ($phone !== '' ? '<br><a href="tel:' . esc_attr(preg_replace('/[^0-9+]/', '', $phone)) . '">' . esc_html($phone) . '</a>' : '') . '</p>'; }
        if (trim((string) $item->post_content) !== '') { echo '<details><summary>Submission details</summary><div class="elev8-intake-details">' . nl2br(esc_html($item->post_content)) . '</div></details>'; }
        echo '<p class="elev8-intake-source">Source: ' . esc_html($source !== '' ? $source : 'Unavailable') . ($person_id > 0 ? ' · Person #' . $person_id : '') . '</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('elev8_intake_update_' . $id, 'elev8_intake_nonce');
        echo '<input type="hidden" name="action" value="elev8_os_update_intake"><input type="hidden" name="intake_id" value="' . $id . '">';
        echo '<label>Status<select name="status">';
        foreach (Elev8_OS_Unified_Intake_Service::statuses() as $key => $label) { echo '<option value="' . esc_attr($key) . '" ' . selected($status ?: 'new', $key, false) . '>' . esc_html($label) . '</option>'; }
        echo '</select></label><label>Assigned to<select name="assigned_user"><option value="0">Unassigned</option>';
        foreach ($users as $user) { echo '<option value="' . (int) $user->ID . '" ' . selected($assigned, (int) $user->ID, false) . '>' . esc_html($user->display_name) . '</option>'; }
        echo '</select></label><label>Follow-up date<input type="date" name="follow_up" value="' . esc_attr($follow_up) . '"></label><label>Internal notes<textarea name="internal_notes" rows="3">' . esc_textarea($notes) . '</textarea></label><button class="button button-primary" type="submit">Save</button></form></article>';
    }

    private static function type_label(string $type): string {
        $labels = [
            'bingo_reservation' => 'Bingo reservation', 'volunteer' => 'Volunteer', 'class_request' => 'Class request',
            'customer_feedback' => 'Customer feedback', 'class_feedback' => 'Class feedback', 'suggest_idea' => 'Idea',
            'art_walk' => 'Art Walk check-in', 'open_mic' => 'Open Mic check-in', 'sponsor' => 'Sponsor request',
        ];
        return $labels[$type] ?? ucwords(str_replace('_', ' ', $type));
    }
}

<?php
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Unified_Intake_Module {
    const ADMIN_SLUG = 'elev8-unified-intake';

    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'admin_menu'], 18);
        add_action('admin_enqueue_scripts', [__CLASS__, 'assets']);
        add_action('admin_post_elev8_os_update_intake', [__CLASS__, 'update_item']);
        add_action('admin_post_elev8_os_backfill_intake', [__CLASS__, 'backfill']);
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
        $assigned = absint($_POST['assigned_user'] ?? 0);
        if ($assigned > 0) {
            $assigned_user = get_userdata($assigned);
            if (!$assigned_user || !Elev8_OS_Access_Service::can_receive_assignments($assigned_user)) { $assigned = 0; }
        }
        $follow_up = sanitize_text_field(wp_unslash($_POST['follow_up'] ?? ''));
        $notes = sanitize_textarea_field(wp_unslash($_POST['internal_notes'] ?? ''));
        $previous_status = (string) get_post_meta($id, '_elev8_intake_status', true);
        $previous_assigned = (int) get_post_meta($id, '_elev8_intake_assigned_user', true);
        $previous_follow_up = (string) get_post_meta($id, '_elev8_intake_follow_up', true);
        $previous_notes = (string) get_post_meta($id, '_elev8_intake_notes', true);

        update_post_meta($id, '_elev8_intake_status', $status);
        update_post_meta($id, '_elev8_intake_assigned_user', $assigned);
        update_post_meta($id, '_elev8_intake_follow_up', $follow_up);
        update_post_meta($id, '_elev8_intake_notes', $notes);

        if (class_exists('Elev8_OS_Activity_Service')) {
            $changes = [];
            if ($previous_status !== $status) {
                $labels = Elev8_OS_Unified_Intake_Service::statuses();
                $changes[] = sprintf('Status: %s → %s', $labels[$previous_status] ?? 'Unavailable', $labels[$status] ?? 'Unavailable');
            }
            if ($previous_assigned !== $assigned) {
                $old_user = $previous_assigned > 0 ? get_userdata($previous_assigned) : null;
                $new_user = $assigned > 0 ? get_userdata($assigned) : null;
                $changes[] = sprintf('Assigned: %s → %s', $old_user ? $old_user->display_name : 'Unassigned', $new_user ? $new_user->display_name : 'Unassigned');
            }
            if ($previous_follow_up !== $follow_up) { $changes[] = 'Follow-up: ' . ($follow_up !== '' ? $follow_up : 'Removed'); }
            if ($previous_notes !== $notes) { $changes[] = 'Internal notes updated'; }
            if ($changes) {
                Elev8_OS_Activity_Service::record([
                    'type' => 'intake_updated',
                    'label' => __('Intake workflow updated', 'elev8-os'),
                    'details' => implode("
", $changes),
                    'person_id' => (int) get_post_meta($id, '_elev8_intake_person_id', true),
                    'object_id' => $id,
                    'object_type' => Elev8_OS_Unified_Intake_Service::POST_TYPE,
                    'source' => 'owner-intake-dashboard',
                    'actor_user_id' => get_current_user_id(),
                ]);
            }
        }
        wp_safe_redirect(admin_url('admin.php?page=' . self::ADMIN_SLUG . '&updated=1#intake-' . $id));
        exit;
    }


    public static function backfill(): void {
        if (!current_user_can('manage_options')) { wp_die(__('You do not have permission.', 'elev8-os')); }
        check_admin_referer('elev8_os_backfill_intake');
        $result = Elev8_OS_Unified_Intake_Service::backfill_existing();
        $args = ['page' => self::ADMIN_SLUG, 'backfilled' => (int) ($result['created'] ?? 0), 'skipped' => (int) ($result['skipped'] ?? 0), 'failed' => (int) ($result['failed'] ?? 0)];
        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
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
        $users = Elev8_OS_Access_Service::assignment_users_grouped();
        $diagnostics = Elev8_OS_Unified_Intake_Service::diagnostics();
        echo '<div class="wrap elev8-intake-admin">';
        echo '<header class="elev8-intake-hero"><div><p class="elev8-intake-eyebrow">ELEV8 OS ' . esc_html(ELEV8_OS_VERSION) . '</p><h1>Owner Intake Dashboard</h1><p>One place for reservations, volunteers, class requests, feedback, sponsors, ideas, and future communication.</p></div></header>';
        if (!empty($_GET['updated'])) { echo '<div class="notice notice-success is-dismissible"><p>Intake item updated.</p></div>'; }
        if (isset($_GET['backfilled'])) { echo '<div class="notice notice-success is-dismissible"><p>Historical intake scan complete: ' . absint($_GET['backfilled']) . ' created, ' . absint($_GET['skipped'] ?? 0) . ' already connected, ' . absint($_GET['failed'] ?? 0) . ' failed.</p></div>'; }
        self::render_diagnostics($diagnostics);
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


    private static function render_diagnostics(array $diagnostics): void {
        echo '<section class="elev8-intake-diagnostics"><div><h2>Intake Connections</h2><p>Trusted source records stay unchanged. This tool creates only missing workflow cards and can be run more than once safely.</p></div><div class="elev8-intake-diagnostic-stats">';
        foreach ([['Source records', $diagnostics['source_records'] ?? 0], ['Connected', $diagnostics['connected'] ?? 0], ['Ready to import', $diagnostics['ready'] ?? 0]] as $stat) { echo '<span><strong>' . absint($stat[1]) . '</strong>' . esc_html($stat[0]) . '</span>'; }
        echo '</div><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="elev8_os_backfill_intake">';
        wp_nonce_field('elev8_os_backfill_intake');
        echo '<button class="button button-secondary" type="submit">Import Existing Submissions</button></form></section>';
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
        self::render_timeline($id);
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('elev8_intake_update_' . $id, 'elev8_intake_nonce');
        echo '<input type="hidden" name="action" value="elev8_os_update_intake"><input type="hidden" name="intake_id" value="' . $id . '">';
        echo '<label>Status<select name="status">';
        foreach (Elev8_OS_Unified_Intake_Service::statuses() as $key => $label) { echo '<option value="' . esc_attr($key) . '" ' . selected($status ?: 'new', $key, false) . '>' . esc_html($label) . '</option>'; }
        echo '</select></label><label>Assigned to<select name="assigned_user"><option value="0">Unassigned</option>';
        $group_labels = Elev8_OS_Access_Service::assignment_group_labels();
        foreach ($users as $group_key => $group_users) {
            echo '<optgroup label="' . esc_attr($group_labels[$group_key] ?? ucwords(str_replace('_', ' ', $group_key))) . '">';
            foreach ($group_users as $user) { echo '<option value="' . (int) $user->ID . '" ' . selected($assigned, (int) $user->ID, false) . '>' . esc_html($user->display_name) . '</option>'; }
            echo '</optgroup>';
        }
        echo '</select></label><label>Follow-up date<input type="date" name="follow_up" value="' . esc_attr($follow_up) . '"></label><label>Internal notes<textarea name="internal_notes" rows="3">' . esc_textarea($notes) . '</textarea></label><button class="button button-primary" type="submit">Save</button></form></article>';
    }

    private static function render_timeline(int $intake_id): void {
        if (!class_exists('Elev8_OS_Activity_Service')) { return; }
        $activities = Elev8_OS_Activity_Service::for_object($intake_id, Elev8_OS_Unified_Intake_Service::POST_TYPE, 20);
        if (!$activities) { return; }
        echo '<details class="elev8-intake-timeline"><summary>Activity timeline (' . count($activities) . ')</summary><ol>';
        foreach ($activities as $activity) {
            $actor_id = (int) get_post_meta($activity->ID, '_elev8_activity_actor_user_id', true);
            $actor = $actor_id > 0 ? get_userdata($actor_id) : null;
            echo '<li><strong>' . esc_html($activity->post_title) . '</strong><time>' . esc_html(get_the_date('M j, Y g:i a', $activity->ID)) . '</time>';
            if ($actor) { echo '<span>By ' . esc_html($actor->display_name) . '</span>'; }
            if (trim((string) $activity->post_content) !== '') { echo '<p>' . nl2br(esc_html($activity->post_content)) . '</p>'; }
            echo '</li>';
        }
        echo '</ol></details>';
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

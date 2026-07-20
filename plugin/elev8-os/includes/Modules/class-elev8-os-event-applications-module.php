<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Reusable public event-application workflow.
 * Elev8 Takeover is the first configured application type.
 */
final class Elev8_OS_Event_Applications_Module {
    private const POST_TYPE = 'elev8_event_app';
    private const PAGE_SLUG = 'elev8-event-applications';
    private const SHORTCODE = 'elev8_event_application';

    public static function init(): void {
        add_action('init', [__CLASS__, 'register_post_type']);
        add_shortcode(self::SHORTCODE, [__CLASS__, 'shortcode']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'frontend_assets']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'admin_assets']);
        add_action('admin_menu', [__CLASS__, 'admin_menu'], 28);
        add_action('admin_post_nopriv_elev8_os_submit_event_application', [__CLASS__, 'submit']);
        add_action('admin_post_elev8_os_submit_event_application', [__CLASS__, 'submit']);
        add_action('admin_post_elev8_os_update_event_application', [__CLASS__, 'update']);
    }

    public static function register_post_type(): void {
        register_post_type(self::POST_TYPE, [
            'labels' => ['name' => __('Event Applications', 'elev8-os'), 'singular_name' => __('Event Application', 'elev8-os')],
            'public' => false,
            'show_ui' => false,
            'supports' => ['title'],
            'map_meta_cap' => true,
        ]);
    }

    public static function admin_menu(): void {
        add_submenu_page('elev8-os', __('Event Applications', 'elev8-os'), __('Event Applications', 'elev8-os'), 'read', self::PAGE_SLUG, [__CLASS__, 'render_admin']);
    }

    public static function frontend_assets(): void {
        if (!is_singular()) { return; }
        global $post;
        if (!$post || !has_shortcode((string) $post->post_content, self::SHORTCODE)) { return; }
        wp_enqueue_style('elev8-os-event-applications', ELEV8_OS_URL . 'assets/css/event-applications.css', [], ELEV8_OS_VERSION);
    }

    public static function admin_assets(string $hook): void {
        if ($hook !== 'elev8-os_page_' . self::PAGE_SLUG) { return; }
        wp_enqueue_style('elev8-os-event-applications', ELEV8_OS_URL . 'assets/css/event-applications.css', [], ELEV8_OS_VERSION);
    }

    public static function shortcode(array $atts = []): string {
        $atts = shortcode_atts(['type' => 'elev8_takeover'], $atts, self::SHORTCODE);
        $type = sanitize_key((string) $atts['type']);
        if ($type !== 'elev8_takeover') { return '<p>' . esc_html__('This application type is unavailable.', 'elev8-os') . '</p>'; }
        $state = sanitize_key((string) ($_GET['event_application'] ?? ''));
        ob_start();
        ?>
        <div class="elev8-event-application-wrap">
            <?php if ($state === 'thanks') : ?>
                <div class="elev8-event-application-notice is-success"><h2><?php esc_html_e('Application received!', 'elev8-os'); ?></h2><p><?php esc_html_e('Your Elev8 Takeover request is now in Elev8 OS. Our team will review it and contact you.', 'elev8-os'); ?></p></div>
            <?php elseif ($state === 'error') : ?>
                <div class="elev8-event-application-notice is-error"><h2><?php esc_html_e('We could not save your application.', 'elev8-os'); ?></h2><p><?php esc_html_e('Please review the required fields and try again.', 'elev8-os'); ?></p></div>
            <?php endif; ?>
            <form class="elev8-event-application-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('elev8_event_application_submit', 'elev8_event_application_nonce'); ?>
                <input type="hidden" name="action" value="elev8_os_submit_event_application">
                <input type="hidden" name="application_type" value="elev8_takeover">
                <input class="elev8-event-honeypot" type="text" name="website_confirm" value="" tabindex="-1" autocomplete="off" aria-hidden="true">

                <section><h2><?php esc_html_e('Business and Contact', 'elev8-os'); ?></h2>
                    <label><?php esc_html_e('Dispensary or shop name', 'elev8-os'); ?> <b>*</b><input type="text" name="organization_name" required></label>
                    <div class="elev8-event-two"><label><?php esc_html_e('First name', 'elev8-os'); ?> <b>*</b><input type="text" name="first_name" autocomplete="given-name" required></label><label><?php esc_html_e('Last name', 'elev8-os'); ?> <b>*</b><input type="text" name="last_name" autocomplete="family-name" required></label></div>
                    <div class="elev8-event-two"><label><?php esc_html_e('Email', 'elev8-os'); ?> <b>*</b><input type="email" name="email" autocomplete="email" required></label><label><?php esc_html_e('Phone', 'elev8-os'); ?> <b>*</b><input type="tel" name="phone" autocomplete="tel" required></label></div>
                    <label><?php esc_html_e('Website or Instagram', 'elev8-os'); ?><input type="text" name="website_social"></label>
                </section>

                <section><h2><?php esc_html_e('Takeover Idea', 'elev8-os'); ?></h2>
                    <label><?php esc_html_e('What kind of event or party do you want to host?', 'elev8-os'); ?> <b>*</b><textarea name="event_idea" rows="4" required></textarea></label>
                    <label><?php esc_html_e('Why do you want to host an Elev8 Takeover?', 'elev8-os'); ?> <b>*</b><textarea name="why_host" rows="4" required></textarea></label>
                    <div class="elev8-event-two"><label><?php esc_html_e('First preferred date', 'elev8-os'); ?> <b>*</b><input type="date" name="preferred_date_1" required></label><label><?php esc_html_e('Second preferred date', 'elev8-os'); ?><input type="date" name="preferred_date_2"></label></div>
                    <label><?php esc_html_e('Expected attendance', 'elev8-os'); ?><input type="number" name="expected_attendance" min="1" max="500"></label>
                </section>

                <section><h2><?php esc_html_e('Setup and Promotion', 'elev8-os'); ?></h2>
                    <label><?php esc_html_e('Will staff, vendors, or brand representatives attend?', 'elev8-os'); ?><textarea name="attending_team" rows="3"></textarea></label>
                    <label><?php esc_html_e('Tables, tents, music, power, or other setup needs', 'elev8-os'); ?><textarea name="setup_needs" rows="3"></textarea></label>
                    <label><?php esc_html_e('Giveaways, specials, or promotions', 'elev8-os'); ?><textarea name="giveaways" rows="3"></textarea></label>
                    <label><?php esc_html_e('How will you promote the event?', 'elev8-os'); ?> <b>*</b><textarea name="promotion_plan" rows="4" required></textarea></label>
                    <label><?php esc_html_e('Anything else we should know?', 'elev8-os'); ?><textarea name="additional_notes" rows="4"></textarea></label>
                </section>

                <section class="elev8-event-agreements"><h2><?php esc_html_e('Agreements', 'elev8-os'); ?></h2>
                    <label><input type="checkbox" name="cleanup_agreement" value="1" required> <?php esc_html_e('I agree to leave the event area clean and remove everything brought for the event.', 'elev8-os'); ?> <b>*</b></label>
                    <label><input type="checkbox" name="facility_agreement" value="1" required> <?php esc_html_e('I understand restroom or portable-toilet arrangements may be required based on the event.', 'elev8-os'); ?> <b>*</b></label>
                    <label><input type="checkbox" name="fee_agreement" value="1" required> <?php esc_html_e('I understand a cleaning or damage fee may apply if the space is not left in acceptable condition.', 'elev8-os'); ?> <b>*</b></label>
                    <label><input type="checkbox" name="event_agreement" value="1" required> <?php esc_html_e('I understand approval is required and additional event terms may be provided before scheduling.', 'elev8-os'); ?> <b>*</b></label>
                </section>
                <button type="submit" class="elev8-event-submit"><?php esc_html_e('Submit Takeover Application', 'elev8-os'); ?></button>
            </form>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    public static function submit(): void {
        $redirect = wp_get_referer() ?: home_url('/elev8-takeover-application/');
        if (!isset($_POST['elev8_event_application_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['elev8_event_application_nonce'])), 'elev8_event_application_submit')) { wp_die(esc_html__('Security check failed.', 'elev8-os')); }
        if (!empty($_POST['website_confirm']) || !self::rate_limit()) { self::redirect($redirect, 'error'); }

        $organization = sanitize_text_field(wp_unslash($_POST['organization_name'] ?? ''));
        $first = sanitize_text_field(wp_unslash($_POST['first_name'] ?? ''));
        $last = sanitize_text_field(wp_unslash($_POST['last_name'] ?? ''));
        $name = trim($first . ' ' . $last);
        $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
        $phone = sanitize_text_field(wp_unslash($_POST['phone'] ?? ''));
        $idea = sanitize_textarea_field(wp_unslash($_POST['event_idea'] ?? ''));
        $why = sanitize_textarea_field(wp_unslash($_POST['why_host'] ?? ''));
        $date1 = sanitize_text_field(wp_unslash($_POST['preferred_date_1'] ?? ''));
        $promotion = sanitize_textarea_field(wp_unslash($_POST['promotion_plan'] ?? ''));
        $agreements = !empty($_POST['cleanup_agreement']) && !empty($_POST['facility_agreement']) && !empty($_POST['fee_agreement']) && !empty($_POST['event_agreement']);
        if ($organization === '' || $name === '' || !is_email($email) || $phone === '' || $idea === '' || $why === '' || !self::valid_date($date1) || $promotion === '' || !$agreements) { self::redirect($redirect, 'error'); }

        $id = wp_insert_post(['post_type' => self::POST_TYPE, 'post_status' => 'publish', 'post_title' => sprintf('%s — %s', $organization, $name)], true);
        if (is_wp_error($id)) { self::redirect($redirect, 'error'); }

        $fields = [
            'application_type' => 'elev8_takeover', 'organization_name' => $organization, 'contact_name' => $name, 'email' => $email, 'phone' => $phone,
            'website_social' => sanitize_text_field(wp_unslash($_POST['website_social'] ?? '')), 'event_idea' => $idea, 'why_host' => $why,
            'preferred_date_1' => $date1, 'preferred_date_2' => sanitize_text_field(wp_unslash($_POST['preferred_date_2'] ?? '')),
            'expected_attendance' => absint($_POST['expected_attendance'] ?? 0), 'attending_team' => sanitize_textarea_field(wp_unslash($_POST['attending_team'] ?? '')),
            'setup_needs' => sanitize_textarea_field(wp_unslash($_POST['setup_needs'] ?? '')), 'giveaways' => sanitize_textarea_field(wp_unslash($_POST['giveaways'] ?? '')),
            'promotion_plan' => $promotion, 'additional_notes' => sanitize_textarea_field(wp_unslash($_POST['additional_notes'] ?? '')), 'status' => 'new',
        ];
        foreach ($fields as $key => $value) { update_post_meta((int) $id, '_elev8_event_app_' . $key, $value); }
        update_post_meta((int) $id, '_elev8_event_app_submitted_at', current_time('mysql'));

        $person_id = class_exists('Elev8_OS_Person_Service') ? Elev8_OS_Person_Service::find_or_create($email, $name, $phone) : 0;
        $relationship_id = self::find_or_create_relationship($organization, $name, $email, $phone, $fields['website_social']);
        update_post_meta((int) $id, '_elev8_event_app_person_id', $person_id);
        update_post_meta((int) $id, '_elev8_event_app_relationship_id', $relationship_id);

        $summary = "Organization: {$organization}\nPreferred date: {$date1}\nExpected attendance: " . ($fields['expected_attendance'] ?: __('Unavailable', 'elev8-os')) . "\nEvent idea: {$idea}\nPromotion: {$promotion}";
        $intake_id = class_exists('Elev8_OS_Unified_Intake_Service') ? Elev8_OS_Unified_Intake_Service::create([
            'title' => sprintf(__('Elev8 Takeover Application — %s', 'elev8-os'), $organization), 'type' => 'event_application', 'source' => 'elev8-takeover-application',
            'name' => $name, 'email' => $email, 'phone' => $phone, 'summary' => $summary, 'origin_post_id' => (int) $id, 'origin_post_type' => self::POST_TYPE, 'person_id' => $person_id,
        ]) : 0;
        update_post_meta((int) $id, '_elev8_event_app_intake_id', $intake_id);
        if ($person_id && class_exists('Elev8_OS_Person_Service')) { Elev8_OS_Person_Service::add_activity($person_id, 'event_application', (int) $id, __('Elev8 Takeover application', 'elev8-os'), 'elev8-takeover-application', $summary); }
        self::send_notifications((int) $id, $organization, $name, $email, $summary);
        self::redirect($redirect, 'thanks');
    }

    public static function render_admin(): void {
        if (!Elev8_OS_Access_Service::user_can('manage_event_applications')) { wp_die(esc_html__('You do not have permission to manage event applications.', 'elev8-os')); }
        $status = sanitize_key((string) ($_GET['status'] ?? ''));
        $args = ['post_type' => self::POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => 100, 'orderby' => 'date', 'order' => 'DESC'];
        if ($status !== '') { $args['meta_query'] = [['key' => '_elev8_event_app_status', 'value' => $status]]; }
        $apps = get_posts($args);
        echo '<div class="wrap elev8-event-app-admin"><h1>' . esc_html__('Event Applications', 'elev8-os') . '</h1><p>' . esc_html__('Review, assign, and move public event requests into planning.', 'elev8-os') . '</p>';
        echo '<nav class="elev8-event-filters"><a href="' . esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG)) . '">' . esc_html__('All', 'elev8-os') . '</a>';
        foreach (self::statuses() as $key => $label) { echo '<a href="' . esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG . '&status=' . $key)) . '">' . esc_html($label) . '</a>'; }
        echo '</nav><div class="elev8-event-app-list">';
        if (!$apps) { echo '<div class="elev8-event-empty">' . esc_html__('No applications found.', 'elev8-os') . '</div>'; }
        foreach ($apps as $app) { self::render_card($app); }
        echo '</div></div>';
    }

    private static function render_card(WP_Post $app): void {
        $m = static fn(string $key): string => (string) get_post_meta($app->ID, '_elev8_event_app_' . $key, true);
        $status = $m('status') ?: 'new';
        echo '<article class="elev8-event-app-card"><header><div><span class="elev8-event-type">' . esc_html__('Elev8 Takeover', 'elev8-os') . '</span><h2>' . esc_html($m('organization_name')) . '</h2><p>' . esc_html($m('contact_name')) . ' · <a href="mailto:' . esc_attr($m('email')) . '">' . esc_html($m('email')) . '</a> · ' . esc_html($m('phone')) . '</p></div><strong class="elev8-event-status">' . esc_html(self::statuses()[$status] ?? ucfirst($status)) . '</strong></header>';
        echo '<div class="elev8-event-app-grid"><div><b>' . esc_html__('Preferred date', 'elev8-os') . '</b><span>' . esc_html($m('preferred_date_1') ?: __('Unavailable', 'elev8-os')) . '</span></div><div><b>' . esc_html__('Expected attendance', 'elev8-os') . '</b><span>' . esc_html($m('expected_attendance') ?: __('Unavailable', 'elev8-os')) . '</span></div><div><b>' . esc_html__('Website / social', 'elev8-os') . '</b><span>' . esc_html($m('website_social') ?: __('Unavailable', 'elev8-os')) . '</span></div></div>';
        foreach (['event_idea' => __('Event idea', 'elev8-os'), 'why_host' => __('Why they want to host', 'elev8-os'), 'promotion_plan' => __('Promotion plan', 'elev8-os'), 'setup_needs' => __('Setup needs', 'elev8-os'), 'giveaways' => __('Giveaways', 'elev8-os'), 'additional_notes' => __('Additional notes', 'elev8-os')] as $key => $label) { if ($m($key) !== '') { echo '<details><summary>' . esc_html($label) . '</summary><p>' . nl2br(esc_html($m($key))) . '</p></details>'; } }
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="elev8-event-update">';
        wp_nonce_field('elev8_update_event_application_' . $app->ID);
        echo '<input type="hidden" name="action" value="elev8_os_update_event_application"><input type="hidden" name="application_id" value="' . (int) $app->ID . '"><label>' . esc_html__('Status', 'elev8-os') . '<select name="status">';
        foreach (self::statuses() as $key => $label) { echo '<option value="' . esc_attr($key) . '"' . selected($status, $key, false) . '>' . esc_html($label) . '</option>'; }
        echo '</select></label><label>' . esc_html__('Assigned to', 'elev8-os') . '<select name="assigned_user"><option value="0">' . esc_html__('Unassigned', 'elev8-os') . '</option>';
        $assigned = absint(get_post_meta($app->ID, '_elev8_event_app_assigned_user', true));
        foreach (Elev8_OS_Access_Service::assignment_users_grouped() as $group => $users) { echo '<optgroup label="' . esc_attr($group) . '">'; foreach ($users as $user) { echo '<option value="' . (int) $user->ID . '"' . selected($assigned, $user->ID, false) . '>' . esc_html($user->display_name) . '</option>'; } echo '</optgroup>'; }
        echo '</select></label><label>' . esc_html__('Follow-up date', 'elev8-os') . '<input type="date" name="follow_up" value="' . esc_attr((string) get_post_meta($app->ID, '_elev8_event_app_follow_up', true)) . '"></label><label class="elev8-event-notes">' . esc_html__('Internal notes', 'elev8-os') . '<textarea name="internal_notes" rows="3">' . esc_textarea((string) get_post_meta($app->ID, '_elev8_event_app_internal_notes', true)) . '</textarea></label><button class="button button-primary">' . esc_html__('Save Application', 'elev8-os') . '</button></form></article>';
    }

    public static function update(): void {
        if (!Elev8_OS_Access_Service::user_can('manage_event_applications')) { wp_die(esc_html__('Permission denied.', 'elev8-os')); }
        $id = absint($_POST['application_id'] ?? 0);
        check_admin_referer('elev8_update_event_application_' . $id);
        if (get_post_type($id) !== self::POST_TYPE) { wp_die(esc_html__('Invalid application.', 'elev8-os')); }
        $old = (string) get_post_meta($id, '_elev8_event_app_status', true);
        $status = sanitize_key((string) ($_POST['status'] ?? 'new'));
        if (!isset(self::statuses()[$status])) { $status = 'new'; }
        update_post_meta($id, '_elev8_event_app_status', $status);
        update_post_meta($id, '_elev8_event_app_assigned_user', absint($_POST['assigned_user'] ?? 0));
        update_post_meta($id, '_elev8_event_app_follow_up', sanitize_text_field(wp_unslash($_POST['follow_up'] ?? '')));
        update_post_meta($id, '_elev8_event_app_internal_notes', sanitize_textarea_field(wp_unslash($_POST['internal_notes'] ?? '')));
        if (class_exists('Elev8_OS_Activity_Service')) { Elev8_OS_Activity_Service::record(['type' => 'event_application_updated', 'label' => __('Event application updated', 'elev8-os'), 'details' => sprintf(__('Status changed from %1$s to %2$s.', 'elev8-os'), $old ?: 'new', $status), 'object_id' => $id, 'object_type' => self::POST_TYPE, 'source' => 'event-applications', 'actor_user_id' => get_current_user_id()]); }
        wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE_SLUG . '&updated=1')); exit;
    }

    public static function attention_count(): int { return self::count_by_status(['new', 'review', 'contacted']); }
    public static function awaiting_agreement_count(): int { return self::count_by_status(['agreement_needed']); }
    public static function admin_url(): string { return admin_url('admin.php?page=' . self::PAGE_SLUG); }

    private static function count_by_status(array $statuses): int {
        $q = new WP_Query(['post_type' => self::POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_query' => [['key' => '_elev8_event_app_status', 'value' => $statuses, 'compare' => 'IN']]]);
        return (int) $q->found_posts;
    }

    private static function statuses(): array { return ['new' => __('New', 'elev8-os'), 'review' => __('Under Review', 'elev8-os'), 'contacted' => __('Contacted', 'elev8-os'), 'approved' => __('Approved', 'elev8-os'), 'agreement_needed' => __('Agreement Needed', 'elev8-os'), 'planning' => __('Planning', 'elev8-os'), 'scheduled' => __('Scheduled', 'elev8-os'), 'completed' => __('Completed', 'elev8-os'), 'declined' => __('Declined', 'elev8-os'), 'archived' => __('Archived', 'elev8-os')]; }

    private static function find_or_create_relationship(string $organization, string $contact, string $email, string $phone, string $social): int {
        $existing = get_page_by_title($organization, OBJECT, 'elev8_relationship');
        $id = $existing ? (int) $existing->ID : 0;
        if (!$id) { $id = wp_insert_post(['post_type' => 'elev8_relationship', 'post_status' => 'publish', 'post_title' => $organization], true); if (is_wp_error($id)) { return 0; } }
        update_post_meta((int) $id, '_elev8_type', 'Dispensary');
        if ($contact !== '') { update_post_meta((int) $id, '_elev8_contact', $contact); }
        if ($email !== '') { update_post_meta((int) $id, '_elev8_email', strtolower($email)); }
        if ($phone !== '') { update_post_meta((int) $id, '_elev8_phone', $phone); }
        if ($social !== '') { update_post_meta((int) $id, '_elev8_social', $social); }
        update_post_meta((int) $id, '_elev8_relationship_level', 'future_opportunity');
        return (int) $id;
    }

    private static function send_notifications(int $id, string $organization, string $name, string $email, string $summary): void {
        $admin = (string) get_option('admin_email');
        if (is_email($admin) && class_exists('Elev8_OS_Notification_Service')) { Elev8_OS_Notification_Service::send_email($admin, sprintf('[Elev8 OS] New Takeover Application — %s', $organization), "A new Elev8 Takeover application was submitted.\n\nContact: {$name}\nEmail: {$email}\n\n{$summary}\n\n" . self::admin_url()); }
        wp_mail($email, __('We received your Elev8 Takeover application', 'elev8-os'), "Thanks for applying to host an Elev8 Takeover. Our team will review your request and contact you.\n\nReference: #{$id}");
    }

    private static function rate_limit(): bool {
        $ip = sanitize_text_field((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        $key = 'elev8_event_app_' . md5($ip);
        if (get_transient($key)) { return false; }
        set_transient($key, 1, 30);
        return true;
    }

    private static function valid_date(string $date): bool { $d = DateTime::createFromFormat('Y-m-d', $date); return $d && $d->format('Y-m-d') === $date; }
    private static function redirect(string $url, string $state): void { wp_safe_redirect(add_query_arg('event_application', $state, remove_query_arg('event_application', $url))); exit; }
}

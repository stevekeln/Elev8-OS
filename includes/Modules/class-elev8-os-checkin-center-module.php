<?php
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Checkin_Center_Module {
    const PAGE_SLUG = 'checkin';
    const SHORTCODE = 'elev8_checkin_center';
    const ADMIN_SLUG = 'elev8-checkin-center';

    public static function init(): void {
        add_shortcode(self::SHORTCODE, [__CLASS__, 'shortcode']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'frontend_assets']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'admin_assets']);
        add_action('admin_menu', [__CLASS__, 'admin_menu'], 26);
        add_action('admin_post_nopriv_elev8_os_submit_checkin', [__CLASS__, 'submit']);
        add_action('admin_post_elev8_os_submit_checkin', [__CLASS__, 'submit']);
    }

    public static function activate(): void {
        self::ensure_page();
    }

    public static function ensure_page(): int {
        $existing = get_page_by_path(self::PAGE_SLUG);
        if ($existing instanceof WP_Post) {
            if (strpos((string)$existing->post_content, '[' . self::SHORTCODE) === false) {
                wp_update_post(['ID' => $existing->ID, 'post_content' => '[' . self::SHORTCODE . ']']);
            }
            return (int)$existing->ID;
        }
        $id = wp_insert_post([
            'post_type' => 'page',
            'post_status' => 'publish',
            'post_title' => __('Elev8 Check-In', 'elev8-os'),
            'post_name' => self::PAGE_SLUG,
            'post_content' => '[' . self::SHORTCODE . ']',
        ], true);
        return is_wp_error($id) ? 0 : (int)$id;
    }

    public static function frontend_assets(): void {
        if (!is_singular()) { return; }
        global $post;
        if (!$post || !has_shortcode((string)$post->post_content, self::SHORTCODE)) { return; }
        wp_enqueue_style('elev8-checkin-center', ELEV8_OS_URL . 'assets/css/checkin-center.css', [], ELEV8_OS_VERSION);
        wp_enqueue_script('elev8-checkin-center', ELEV8_OS_URL . 'assets/js/checkin-center.js', [], ELEV8_OS_VERSION, true);
    }

    public static function admin_assets(string $hook): void {
        if ($hook !== 'elev8-os_page_' . self::ADMIN_SLUG) { return; }
        wp_enqueue_style('elev8-checkin-center', ELEV8_OS_URL . 'assets/css/checkin-center.css', [], ELEV8_OS_VERSION);
        wp_enqueue_script('elev8-checkin-center', ELEV8_OS_URL . 'assets/js/checkin-center.js', [], ELEV8_OS_VERSION, true);
    }

    public static function admin_menu(): void {
        add_submenu_page('elev8-os', __('Check-In Center', 'elev8-os'), __('Check-In Center', 'elev8-os'), 'manage_options', self::ADMIN_SLUG, [__CLASS__, 'render_admin']);
    }

    public static function render_admin(): void {
        if (!current_user_can('manage_options')) { wp_die(__('You do not have permission to view this page.', 'elev8-os')); }
        $base = self::page_url();
        $templates = Elev8_OS_Daily_Operations_Service::templates();
        echo '<div class="wrap elev8-checkin-admin">';
        if (class_exists('Elev8_OS_CEO_Dashboard_Module')) { Elev8_OS_CEO_Dashboard_Module::render_workspace_navigation('operations'); }
        echo '<header class="elev8-checkin-hero"><div><p class="elev8-checkin-eyebrow">ELEV8 OS ' . esc_html(ELEV8_OS_VERSION) . '</p><h1>Check-In Center</h1><p>One public front door for events, customers, artists, employees, and managers.</p></div><a class="button button-primary button-hero" target="_blank" href="' . esc_url($base) . '">Open public page</a></header>';
        echo '<div class="elev8-checkin-admin-grid">';
        foreach ($templates as $key => $template) {
            $is_public = !empty($template['public']);
            $url = add_query_arg('type', $key, $base);
            $qr = 'https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=' . rawurlencode($url);
            echo '<article class="elev8-checkin-link-card">';
            echo '<div class="elev8-checkin-link-card__head"><div><span class="elev8-access ' . ($is_public ? 'is-public' : 'is-private') . '">' . ($is_public ? 'Public' : 'Login required') . '</span><h2>' . esc_html($template['name']) . '</h2><p>' . esc_html($template['description']) . '</p></div></div>';
            echo '<img class="elev8-qr" src="' . esc_url($qr) . '" alt="QR code for ' . esc_attr($template['name']) . '">';
            echo '<label>Direct link<input class="elev8-copy-source" readonly value="' . esc_attr($url) . '"></label>';
            echo '<div class="elev8-checkin-actions"><button type="button" class="button elev8-copy-button">Copy link</button><a class="button" target="_blank" href="' . esc_url($url) . '">Open form</a><a class="button" download href="' . esc_url($qr) . '">QR image</a></div>';
            echo '</article>';
        }
        echo '</div></div>';
    }

    public static function shortcode(array $atts = []): string {
        $team_view = !empty($_GET['team']);
        $all_allowed = is_user_logged_in() ? Elev8_OS_Daily_Operations_Service::templates_for_user(get_current_user_id(), true) : [];
        $templates = $team_view && is_user_logged_in()
            ? array_filter($all_allowed, static function (array $template): bool { return empty($template['public']); })
            : Elev8_OS_Daily_Operations_Service::public_templates();
        $selected = sanitize_key((string)($_GET['type'] ?? ''));
        $message = sanitize_key((string)($_GET['checkin'] ?? ''));
        ob_start();
        echo '<div class="elev8-checkin">';
        echo '<div class="elev8-checkin-intro"><p>Choose the check-in or form that fits what you are here to share.</p></div>';
        if ($message === 'thanks') {
            echo '<div class="elev8-checkin-notice is-success"><h2>Thank you!</h2><p>Your check-in was received and added to Elev8’s business memory.</p></div>';
        } elseif ($message === 'error') {
            echo '<div class="elev8-checkin-notice is-error"><h2>We could not save that.</h2><p>Please review the form and try again.</p></div>';
        }
        if ($selected !== '') {
            $template = Elev8_OS_Daily_Operations_Service::template($selected);
            if (!$template) {
                echo '<div class="elev8-checkin-notice is-error"><p>That check-in form is unavailable.</p></div>';
            } elseif (empty($template['public']) && !is_user_logged_in()) {
                $login = wp_login_url(add_query_arg('type', $selected, self::page_url()));
                echo '<section class="elev8-checkin-login"><h2>Sign in required</h2><p>This check-in is for authorized Elev8 team members.</p><a class="elev8-checkin-primary" href="' . esc_url($login) . '">Sign in to continue</a></section>';
            } elseif (!isset($templates[$selected])) {
                echo '<div class="elev8-checkin-notice is-error"><p>You do not have permission to use this check-in.</p></div>';
            } else {
                self::render_form($selected, $template);
            }
            echo '<p class="elev8-checkin-back"><a href="' . esc_url(self::page_url()) . '">← View all check-ins</a></p>';
        } else {
            self::render_choices($templates, $team_view);
        }
        echo '</div>';
        return (string)ob_get_clean();
    }

    private static function render_choices(array $templates, bool $team_view = false): void {
        $heading = $team_view ? __('Elev8 Team', 'elev8-os') : __('What are you checking in for?', 'elev8-os');
        echo '<section class="elev8-checkin-choices"><h2>' . esc_html($heading) . '</h2><div class="elev8-checkin-grid">';
        foreach ($templates as $key => $template) {
            $url = add_query_arg('type', $key, self::page_url());
            echo '<a class="elev8-checkin-choice" href="' . esc_url($url) . '"><span>' . esc_html(self::icon_for($key)) . '</span><strong>' . esc_html($template['name']) . '</strong><small>' . esc_html($template['description']) . '</small></a>';
        }
        echo '</div></section>';

        if ($team_view) {
            echo '<p class="elev8-checkin-team-login"><a class="elev8-team-button is-secondary" href="' . esc_url(self::page_url()) . '">← Back to public check-ins</a></p>';
            return;
        }

        $team_url = add_query_arg('team', '1', self::page_url());
        if (!is_user_logged_in()) {
            $team_url = wp_login_url($team_url);
        }
        echo '<div class="elev8-team-entry"><a class="elev8-team-button" href="' . esc_url($team_url) . '">Elev8 Team</a></div>';
    }

    private static function render_form(string $key, array $template): void {
        $public = !empty($template['public']);
        echo '<section class="elev8-checkin-form-panel"><div class="elev8-checkin-form-heading"><span class="elev8-access ' . ($public ? 'is-public' : 'is-private') . '">' . ($public ? 'Public check-in' : 'Team check-in') . '</span><h2>' . esc_html($template['name']) . '</h2><p>' . esc_html($template['description']) . '</p></div>';
        echo '<form class="elev8-checkin-form" method="post" enctype="multipart/form-data" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('elev8_checkin_submit', 'elev8_checkin_nonce');
        echo '<input type="hidden" name="action" value="elev8_os_submit_checkin"><input type="hidden" name="template" value="' . esc_attr($key) . '"><input type="text" name="website" value="" class="elev8-honeypot" tabindex="-1" autocomplete="off">';
        if ($public) {
            echo '<div class="elev8-checkin-two"><label>Your name<input type="text" name="guest_name" autocomplete="name"></label><label>Email <span>optional</span><input type="email" name="guest_email" autocomplete="email"></label></div>';
        }
        $location_required = !empty($template['location_required']);
        $location_label = (string)($template['location_label'] ?? __('Location', 'elev8-os'));
        $location_options = (array)($template['location_options'] ?? []);
        echo '<div class="elev8-checkin-two"><label>Date<input type="date" name="entry_date" value="' . esc_attr(current_time('Y-m-d')) . '" required></label><label>' . esc_html($location_label) . ($location_required ? ' <b>*</b>' : ' <span>optional</span>');
        if ($location_options) {
            echo '<select name="location"' . ($location_required ? ' required' : '') . '><option value="">Select location</option>';
            foreach ($location_options as $option) { echo '<option value="' . esc_attr((string)$option) . '">' . esc_html((string)$option) . '</option>'; }
            echo '</select>';
        } else {
            echo '<input type="text" name="location"' . ($location_required ? ' required' : '') . '>';
        }
        echo '</label></div>';
        foreach ((array)$template['fields'] as $field) { self::render_field($field); }
        if ($public) {
            echo '<label class="elev8-checkin-consent"><input type="checkbox" name="invite_consent" value="1"> Email me a thank-you and invitations to future Elev8 events.</label>';
        } else {
            echo '<label class="elev8-checkin-consent"><input type="checkbox" name="owner_attention" value="1"> This needs Steve’s attention.</label>';
        }
        echo '<label>Photo or attachment <span>optional</span><input type="file" name="attachments[]" accept="image/*,.pdf"></label>';
        echo '<button class="elev8-checkin-primary" type="submit">Submit check-in</button></form></section>';
    }

    private static function render_field(array $field): void {
        $key = sanitize_key((string)$field['key']);
        $required = !empty($field['required']);
        echo '<label>' . esc_html((string)$field['label']) . ($required ? ' <b>*</b>' : '') . ' ';
        $name = 'fields[' . esc_attr($key) . ']';
        if (($field['type'] ?? '') === 'textarea') {
            echo '<textarea name="' . $name . '" rows="3"' . ($required ? ' required' : '') . '></textarea>';
        } elseif (($field['type'] ?? '') === 'select') {
            echo '<select name="' . $name . '"' . ($required ? ' required' : '') . '><option value="">Select</option>';
            foreach ((array)($field['options'] ?? []) as $option) { echo '<option value="' . esc_attr((string)$option) . '">' . esc_html((string)$option) . '</option>'; }
            echo '</select>';
        } elseif (($field['type'] ?? '') === 'checkbox') {
            echo '<input type="checkbox" name="' . $name . '" value="1">';
        } elseif (($field['type'] ?? '') === 'checkbox_group') {
            echo '<span class="elev8-checkin-checkbox-grid">';
            foreach ((array)($field['options'] ?? []) as $option) {
                echo '<label class="elev8-checkin-checkbox-option"><input type="checkbox" name="fields[' . esc_attr($key) . '][]" value="' . esc_attr((string)$option) . '"> ' . esc_html((string)$option) . '</label>';
            }
            echo '</span>';
        } else {
            $type = in_array(($field['type'] ?? ''), ['number','date','email','time'], true) ? $field['type'] : 'text';
            echo '<input type="' . esc_attr($type) . '" name="' . $name . '"' . ($required ? ' required' : '') . '>';
        }
        echo '</label>';
    }

    public static function submit(): void {
        if (!isset($_POST['elev8_checkin_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['elev8_checkin_nonce'])), 'elev8_checkin_submit')) {
            wp_die(__('Security check failed.', 'elev8-os'));
        }
        $template_key = sanitize_key((string)($_POST['template'] ?? ''));
        $template = Elev8_OS_Daily_Operations_Service::template($template_key);
        $redirect = add_query_arg('type', $template_key, self::page_url());
        if (!$template || !empty($_POST['website'])) { wp_safe_redirect(add_query_arg('checkin', 'error', $redirect)); exit; }
        if (empty($template['public']) && !is_user_logged_in()) { auth_redirect(); }
        if (!self::rate_limit()) { wp_safe_redirect(add_query_arg('checkin', 'error', $redirect)); exit; }

        if (!empty($template['location_required']) && trim(sanitize_text_field((string)($_POST['location'] ?? ''))) === '') {
            wp_safe_redirect(add_query_arg('checkin', 'error', $redirect)); exit;
        }
        $user_id = is_user_logged_in() ? get_current_user_id() : 0;
        $result = Elev8_OS_Daily_Operations_Service::save_entry(wp_unslash($_POST), $_FILES, $user_id, !empty($template['public']));
        if (is_wp_error($result)) { wp_safe_redirect(add_query_arg('checkin', 'error', $redirect)); exit; }
        wp_safe_redirect(add_query_arg('checkin', 'thanks', $redirect));
        exit;
    }

    private static function rate_limit(): bool {
        $ip = sanitize_text_field((string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        $key = 'elev8_checkin_' . md5($ip);
        if (get_transient($key)) { return false; }
        set_transient($key, 1, 10);
        return true;
    }

    private static function icon_for(string $key): string {
        $icons = ['art_walk'=>'🎨','open_mic'=>'🎤','customer_feedback'=>'💬','class_feedback'=>'🧑‍🎨','suggest_idea'=>'💡','class_request'=>'🎓','volunteer'=>'🤝','manager'=>'📋','retail'=>'🏪','artist'=>'🖌️','maintenance'=>'🛠️','vendor'=>'📦','event'=>'📅'];
        return $icons[$key] ?? '✓';
    }

    public static function page_url(): string {
        $page = get_page_by_path(self::PAGE_SLUG);
        return $page ? get_permalink($page) : home_url('/' . self::PAGE_SLUG . '/');
    }
}

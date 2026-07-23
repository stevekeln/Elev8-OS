<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Public Class Idea Center.
 *
 * Captures customer demand and artist proposals before a class is scheduled.
 * Elev8 OS owns the idea/demand workflow; Amelia continues to own published
 * schedules and bookings.
 */
final class Elev8_OS_Class_Idea_Center_Module {
    private const SHORTCODE = 'elev8_class_idea_center';
    private const PAGE_SLUG = 'teach-or-suggest-a-class';

    public static function init(): void {
        add_shortcode(self::SHORTCODE, [__CLASS__, 'shortcode']);
        add_filter('the_content', [__CLASS__, 'replace_public_page_content'], 20);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);

        add_action('admin_post_elev8_os_public_class_request', [__CLASS__, 'handle_customer_request']);
        add_action('admin_post_nopriv_elev8_os_public_class_request', [__CLASS__, 'handle_customer_request']);
        add_action('admin_post_elev8_os_public_artist_proposal', [__CLASS__, 'handle_artist_proposal']);
    }

    public static function status(): string { return 'active'; }
    public static function activate(): void {}
    public static function maybe_upgrade(): void {}

    public static function replace_public_page_content(string $content): string {
        if (!is_singular('page') || !is_main_query() || !in_the_loop()) { return $content; }
        $post = get_post();
        if (!$post || $post->post_name !== self::PAGE_SLUG) { return $content; }
        return self::shortcode();
    }

    public static function enqueue_assets(): void {
        if (!self::is_public_page()) { return; }
        wp_enqueue_style('elev8-os-class-idea-center', ELEV8_OS_URL . 'assets/css/class-idea-center.css', [], ELEV8_OS_VERSION);
    }

    public static function shortcode(): string {
        if (!class_exists('Elev8_OS_Opportunity_Gateway')) {
            return '<div class="elev8-class-idea-center"><div class="elev8-cic-notice is-error">' . esc_html__('The Class Idea Center is temporarily unavailable.', 'elev8-os') . '</div></div>';
        }

        $ideas = self::public_ideas();
        $artist = self::current_artist();
        $active = sanitize_key((string) ($_GET['idea_view'] ?? 'customer'));
        if ($active === 'artist' && !$artist) { $active = 'customer'; }

        ob_start();
        ?>
        <div class="elev8-class-idea-center">
            <?php self::render_notice(); ?>

            <header class="elev8-cic-hero">
                <div>
                    <p class="elev8-cic-eyebrow"><?php esc_html_e('Elev8 Arts Class Idea Center', 'elev8-os'); ?></p>
                    <h1><?php esc_html_e('What would you love to learn or teach?', 'elev8-os'); ?></h1>
                    <p><?php esc_html_e('Tell us what should happen next. Customer requests and artist proposals stay together inside Elev8 OS so strong ideas can become real classes.', 'elev8-os'); ?></p>
                </div>
                <span><?php esc_html_e('Suggest • Measure Demand • Build', 'elev8-os'); ?></span>
            </header>

            <section class="elev8-cic-how" aria-label="<?php esc_attr_e('How it works', 'elev8-os'); ?>">
                <article><strong>1</strong><div><h2><?php esc_html_e('Share the idea', 'elev8-os'); ?></h2><p><?php esc_html_e('Request a class you want to take or propose one you want to teach.', 'elev8-os'); ?></p></div></article>
                <article><strong>2</strong><div><h2><?php esc_html_e('We measure demand', 'elev8-os'); ?></h2><p><?php esc_html_e('Elev8 tracks people, requested seats, timing, and interest in one place.', 'elev8-os'); ?></p></div></article>
                <article><strong>3</strong><div><h2><?php esc_html_e('The best ideas move forward', 'elev8-os'); ?></h2><p><?php esc_html_e('Elev8 reviews the opportunity, finds an artist, and builds the class when it is ready.', 'elev8-os'); ?></p></div></article>
            </section>

            <nav class="elev8-cic-tabs" aria-label="<?php esc_attr_e('Class idea form choices', 'elev8-os'); ?>">
                <a class="<?php echo $active === 'customer' ? 'is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg('idea_view', 'customer', self::page_url())); ?>#class-idea-form"><?php esc_html_e('I want to take a class', 'elev8-os'); ?></a>
                <?php if ($artist) : ?>
                    <a class="<?php echo $active === 'artist' ? 'is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg('idea_view', 'artist', self::page_url())); ?>#class-idea-form"><?php esc_html_e('I want to teach a class', 'elev8-os'); ?></a>
                <?php else : ?>
                    <a href="<?php echo esc_url(wp_login_url(add_query_arg('idea_view', 'artist', self::page_url()) . '#class-idea-form')); ?>"><?php esc_html_e('Artist login to propose a class', 'elev8-os'); ?></a>
                <?php endif; ?>
            </nav>

            <section id="class-idea-form" class="elev8-cic-workspace">
                <?php if ($active === 'artist' && $artist) : ?>
                    <?php self::render_artist_form($artist); ?>
                <?php else : ?>
                    <?php self::render_customer_form($ideas); ?>
                <?php endif; ?>
            </section>

            <section class="elev8-cic-next">
                <p class="elev8-cic-eyebrow"><?php esc_html_e('What happens after you submit?', 'elev8-os'); ?></p>
                <h2><?php esc_html_e('Your idea becomes an organized opportunity—not a forgotten form response.', 'elev8-os'); ?></h2>
                <div>
                    <span><?php esc_html_e('Needs review', 'elev8-os'); ?></span><b>→</b>
                    <span><?php esc_html_e('Demand building', 'elev8-os'); ?></span><b>→</b>
                    <span><?php esc_html_e('Artist assigned', 'elev8-os'); ?></span><b>→</b>
                    <span><?php esc_html_e('Ready to schedule', 'elev8-os'); ?></span><b>→</b>
                    <span><?php esc_html_e('Published', 'elev8-os'); ?></span>
                </div>
                <p><?php esc_html_e('Submitting an idea does not guarantee that a class will be scheduled, but it gives Elev8 verified demand to make a better decision.', 'elev8-os'); ?></p>
            </section>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private static function render_customer_form(array $ideas): void {
        ?>
        <div class="elev8-cic-form-intro">
            <p class="elev8-cic-eyebrow"><?php esc_html_e('Customer request', 'elev8-os'); ?></p>
            <h2><?php esc_html_e('Join interest for an idea—or suggest something new', 'elev8-os'); ?></h2>
            <p><?php esc_html_e('Choose an existing idea when possible. Every request helps Elev8 see which classes have enough demand to build.', 'elev8-os'); ?></p>
        </div>
        <form class="elev8-cic-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="elev8_os_public_class_request">
            <input type="hidden" name="redirect_to" value="<?php echo esc_url(self::page_url()); ?>">
            <?php wp_nonce_field('elev8_os_public_class_request'); ?>
            <label class="elev8-cic-wide"><span><?php esc_html_e('Choose an existing class idea', 'elev8-os'); ?></span><select name="opportunity_id"><option value="0"><?php esc_html_e('I want to suggest a new idea', 'elev8-os'); ?></option><?php foreach ($ideas as $idea) : ?><option value="<?php echo esc_attr((string) $idea['id']); ?>"><?php echo esc_html((string) $idea['title']); ?><?php echo !empty($idea['category']) ? ' — ' . esc_html((string) $idea['category']) : ''; ?></option><?php endforeach; ?></select></label>
            <div class="elev8-cic-or"><span><?php esc_html_e('OR SUGGEST SOMETHING NEW', 'elev8-os'); ?></span></div>
            <label class="elev8-cic-wide"><span><?php esc_html_e('What class would you love to take?', 'elev8-os'); ?></span><input type="text" name="new_class_title" placeholder="<?php esc_attr_e('Example: Beginner pottery wheel', 'elev8-os'); ?>"></label>
            <label><span><?php esc_html_e('Category', 'elev8-os'); ?></span><input type="text" name="new_class_category" placeholder="<?php esc_attr_e('Painting, glass, pottery, wellness…', 'elev8-os'); ?>"></label>
            <label><span><?php esc_html_e('How many people may attend?', 'elev8-os'); ?></span><input type="number" min="1" max="50" name="seats_requested" value="1"></label>
            <label><span><?php esc_html_e('Your name', 'elev8-os'); ?> *</span><input type="text" name="customer_name" required autocomplete="name"></label>
            <label><span><?php esc_html_e('Email', 'elev8-os'); ?> *</span><input type="email" name="customer_email" required autocomplete="email"></label>
            <label><span><?php esc_html_e('Phone (optional)', 'elev8-os'); ?></span><input type="tel" name="customer_phone" autocomplete="tel"></label>
            <label><span><?php esc_html_e('Best days', 'elev8-os'); ?></span><input type="text" name="preferred_days" placeholder="<?php esc_attr_e('Saturday, weekday evenings…', 'elev8-os'); ?>"></label>
            <label><span><?php esc_html_e('Best times', 'elev8-os'); ?></span><input type="text" name="preferred_times" placeholder="<?php esc_attr_e('Morning, after 5 PM…', 'elev8-os'); ?>"></label>
            <label class="elev8-cic-wide"><span><?php esc_html_e('What would make this class great for you?', 'elev8-os'); ?></span><textarea name="notes" rows="4"></textarea></label>
            <label class="elev8-cic-consent elev8-cic-wide"><input type="checkbox" name="contact_permission" value="1" required><span><?php esc_html_e('Elev8 Arts may contact me about this class idea or a related class opportunity.', 'elev8-os'); ?></span></label>
            <label class="elev8-cic-hp" aria-hidden="true"><span>Website</span><input type="text" name="website" tabindex="-1" autocomplete="off"></label>
            <button class="elev8-cic-primary elev8-cic-wide" type="submit"><?php esc_html_e('Join the Class Interest List', 'elev8-os'); ?></button>
        </form>
        <?php
    }

    private static function render_artist_form(array $artist): void {
        ?>
        <div class="elev8-cic-form-intro">
            <p class="elev8-cic-eyebrow"><?php esc_html_e('Artist proposal', 'elev8-os'); ?></p>
            <h2><?php echo esc_html(sprintf(__('Propose a class as %s', 'elev8-os'), (string) ($artist['name'] ?? __('an Elev8 artist', 'elev8-os')))); ?></h2>
            <p><?php esc_html_e('This creates an Elev8 OS opportunity for review. It does not publish a booking in Amelia until the class is approved and ready.', 'elev8-os'); ?></p>
        </div>
        <form class="elev8-cic-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="elev8_os_public_artist_proposal">
            <input type="hidden" name="redirect_to" value="<?php echo esc_url(add_query_arg('idea_view', 'artist', self::page_url())); ?>">
            <?php wp_nonce_field('elev8_os_public_artist_proposal'); ?>
            <label class="elev8-cic-wide"><span><?php esc_html_e('Class title', 'elev8-os'); ?> *</span><input type="text" name="title" required placeholder="<?php esc_attr_e('Example: Paint Your Pet Portrait', 'elev8-os'); ?>"></label>
            <label><span><?php esc_html_e('Category', 'elev8-os'); ?></span><input type="text" name="category"></label>
            <label><span><?php esc_html_e('Experience level', 'elev8-os'); ?></span><select name="difficulty"><option value=""><?php esc_html_e('Choose a level', 'elev8-os'); ?></option><option value="beginner"><?php esc_html_e('Beginner', 'elev8-os'); ?></option><option value="intermediate"><?php esc_html_e('Intermediate', 'elev8-os'); ?></option><option value="advanced"><?php esc_html_e('Advanced', 'elev8-os'); ?></option><option value="all-levels"><?php esc_html_e('All levels', 'elev8-os'); ?></option></select></label>
            <label><span><?php esc_html_e('Estimated price per student', 'elev8-os'); ?></span><input type="number" min="0" step="0.01" name="estimated_price"></label>
            <label><span><?php esc_html_e('Estimated duration in hours', 'elev8-os'); ?></span><input type="number" min="0.5" step="0.25" name="estimated_duration"></label>
            <label><span><?php esc_html_e('Maximum students', 'elev8-os'); ?></span><input type="number" min="1" max="100" name="maximum_students"></label>
            <label><span><?php esc_html_e('Preferred day', 'elev8-os'); ?></span><input type="text" name="preferred_day"></label>
            <label><span><?php esc_html_e('Preferred time', 'elev8-os'); ?></span><input type="text" name="preferred_time"></label>
            <label class="elev8-cic-wide"><span><?php esc_html_e('Class description', 'elev8-os'); ?> *</span><textarea name="description" rows="5" required></textarea></label>
            <label class="elev8-cic-wide"><span><?php esc_html_e('Materials, equipment, or room needs', 'elev8-os'); ?></span><textarea name="supplies_needed" rows="3"></textarea></label>
            <label class="elev8-cic-wide"><span><?php esc_html_e('Private notes for Elev8', 'elev8-os'); ?></span><textarea name="internal_notes" rows="3"></textarea></label>
            <button class="elev8-cic-primary elev8-cic-wide" type="submit"><?php esc_html_e('Submit Class Proposal', 'elev8-os'); ?></button>
        </form>
        <?php
    }

    public static function handle_customer_request(): void {
        check_admin_referer('elev8_os_public_class_request');
        if (!empty($_POST['website']) || self::rate_limited()) { self::redirect('idea_error'); }

        $name = sanitize_text_field(wp_unslash((string) ($_POST['customer_name'] ?? '')));
        $email = sanitize_email(wp_unslash((string) ($_POST['customer_email'] ?? '')));
        $opportunity_id = absint($_POST['opportunity_id'] ?? 0);
        $new_title = sanitize_text_field(wp_unslash((string) ($_POST['new_class_title'] ?? '')));
        if ($name === '' || $email === '' || empty($_POST['contact_permission']) || ($opportunity_id <= 0 && $new_title === '')) {
            self::redirect('idea_error');
        }

        if ($opportunity_id > 0) {
            $opportunity = Elev8_OS_Opportunity_Gateway::get($opportunity_id);
            if (!$opportunity || (string) ($opportunity['type'] ?? '') !== 'class') { self::redirect('idea_error'); }
        } else {
            $opportunity_id = Elev8_OS_Opportunity_Gateway::save([
                'type' => 'class',
                'title' => $new_title,
                'category' => wp_unslash((string) ($_POST['new_class_category'] ?? '')),
                'description' => wp_unslash((string) ($_POST['notes'] ?? '')),
                'status' => 'idea',
                'teacher_needed' => 1,
                'teacher_id' => 0,
                'preferred_day' => wp_unslash((string) ($_POST['preferred_days'] ?? '')),
                'preferred_time' => wp_unslash((string) ($_POST['preferred_times'] ?? '')),
                'internal_notes' => __('Created from the public Class Idea Center.', 'elev8-os'),
            ]);
        }
        if ($opportunity_id <= 0 || self::duplicate_exists($opportunity_id, $email)) { self::redirect($opportunity_id > 0 ? 'idea_duplicate' : 'idea_error'); }

        $interest_id = Elev8_OS_Opportunity_Gateway::add_interest([
            'opportunity_id' => $opportunity_id,
            'customer_name' => $name,
            'customer_email' => $email,
            'customer_phone' => wp_unslash((string) ($_POST['customer_phone'] ?? '')),
            'seats_requested' => min(50, max(1, absint($_POST['seats_requested'] ?? 1))),
            'preferred_days' => wp_unslash((string) ($_POST['preferred_days'] ?? '')),
            'preferred_times' => wp_unslash((string) ($_POST['preferred_times'] ?? '')),
            'notes' => wp_unslash((string) ($_POST['notes'] ?? '')),
            'source' => 'public_class_idea_center',
            'crm_status' => 'new',
        ]);
        if ($interest_id > 0 && class_exists('Elev8_OS_Activity_Service')) {
            Elev8_OS_Activity_Service::record_opportunity($opportunity_id, 'public_class_interest_added', __('Public class interest added', 'elev8-os'), $name, $interest_id);
        }
        self::redirect($interest_id > 0 ? 'idea_saved' : 'idea_error');
    }

    public static function handle_artist_proposal(): void {
        if (!is_user_logged_in()) { auth_redirect(); }
        check_admin_referer('elev8_os_public_artist_proposal');
        $artist = self::current_artist();
        if (!$artist) { self::redirect('artist_error'); }

        $title = sanitize_text_field(wp_unslash((string) ($_POST['title'] ?? '')));
        $description = sanitize_textarea_field(wp_unslash((string) ($_POST['description'] ?? '')));
        if ($title === '' || $description === '') { self::redirect('artist_error'); }
        $maximum_students = absint($_POST['maximum_students'] ?? 0);
        $private_notes = sanitize_textarea_field(wp_unslash((string) ($_POST['internal_notes'] ?? '')));
        if ($maximum_students > 0) {
            $private_notes = trim($private_notes . "\n" . sprintf(__('Proposed maximum students: %d', 'elev8-os'), $maximum_students));
        }

        $id = Elev8_OS_Opportunity_Gateway::save([
            'type' => 'class',
            'title' => $title,
            'category' => wp_unslash((string) ($_POST['category'] ?? '')),
            'description' => $description,
            'status' => 'idea',
            'teacher_needed' => 0,
            'teacher_id' => absint($artist['id'] ?? 0),
            'teacher_contact' => (string) ($artist['email'] ?? ''),
            'preferred_day' => wp_unslash((string) ($_POST['preferred_day'] ?? '')),
            'preferred_time' => wp_unslash((string) ($_POST['preferred_time'] ?? '')),
            'difficulty' => wp_unslash((string) ($_POST['difficulty'] ?? '')),
            'supplies_needed' => wp_unslash((string) ($_POST['supplies_needed'] ?? '')),
            'estimated_price' => $_POST['estimated_price'] ?? '',
            'estimated_duration' => $_POST['estimated_duration'] ?? '',
            'internal_notes' => $private_notes,
        ]);
        if ($id > 0 && class_exists('Elev8_OS_Activity_Service')) {
            Elev8_OS_Activity_Service::record_opportunity($id, 'public_artist_class_proposal', __('Artist proposed a class', 'elev8-os'), __('Submitted through the public Class Idea Center.', 'elev8-os'));
        }
        self::redirect($id > 0 ? 'artist_saved' : 'artist_error');
    }

    private static function current_artist(): ?array {
        if (!is_user_logged_in() || !class_exists('Elev8_OS_Identity_Service')) { return null; }
        $artist = Elev8_OS_Identity_Service::artist_for_user(wp_get_current_user());
        if (!is_array($artist) || absint($artist['id'] ?? 0) <= 0) { return null; }
        $artist['name'] = trim((string) (($artist['first_name'] ?? '') . ' ' . ($artist['last_name'] ?? '')));
        if ($artist['name'] === '') { $artist['name'] = wp_get_current_user()->display_name; }
        if (empty($artist['email'])) { $artist['email'] = wp_get_current_user()->user_email; }
        return $artist;
    }

    private static function public_ideas(): array {
        $ideas = array_values(array_filter(Elev8_OS_Opportunity_Gateway::all(), static function(array $idea): bool {
            return (string) ($idea['type'] ?? '') === 'class' && !in_array((string) ($idea['status'] ?? ''), ['completed', 'archived', 'cancelled'], true);
        }));
        usort($ideas, static fn(array $a, array $b): int => strcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? '')));
        return $ideas;
    }

    private static function duplicate_exists(int $opportunity_id, string $email): bool {
        foreach (Elev8_OS_Opportunity_Gateway::interests($opportunity_id) as $interest) {
            if ($email !== '' && strtolower($email) === strtolower(sanitize_email((string) ($interest['customer_email'] ?? '')))) { return true; }
        }
        return false;
    }

    private static function rate_limited(): bool {
        $ip = sanitize_text_field((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        $key = 'elev8_cic_' . md5($ip);
        if (get_transient($key)) { return true; }
        set_transient($key, 1, MINUTE_IN_SECONDS);
        return false;
    }

    private static function render_notice(): void {
        if (isset($_GET['idea_saved'])) { echo '<div class="elev8-cic-notice">' . esc_html__('Thank you. Your class interest is now inside Elev8 OS, and the team can measure demand for it.', 'elev8-os') . '</div>'; }
        elseif (isset($_GET['artist_saved'])) { echo '<div class="elev8-cic-notice">' . esc_html__('Your class proposal was submitted to Elev8 OS for review.', 'elev8-os') . '</div>'; }
        elseif (isset($_GET['idea_duplicate'])) { echo '<div class="elev8-cic-notice is-warning">' . esc_html__('You are already on the interest list for that class idea.', 'elev8-os') . '</div>'; }
        elseif (isset($_GET['idea_error']) || isset($_GET['artist_error'])) { echo '<div class="elev8-cic-notice is-error">' . esc_html__('We could not save the submission. Please check the required fields and try again.', 'elev8-os') . '</div>'; }
    }

    private static function redirect(string $flag): void {
        $url = esc_url_raw(wp_unslash((string) ($_POST['redirect_to'] ?? self::page_url())));
        if (!$url || !wp_http_validate_url($url)) { $url = self::page_url(); }
        wp_safe_redirect(add_query_arg($flag, 1, $url) . '#class-idea-form');
        exit;
    }

    private static function page_url(): string { return home_url('/' . self::PAGE_SLUG . '/'); }
    private static function is_public_page(): bool {
        if (is_page(self::PAGE_SLUG)) { return true; }
        global $post;
        return $post instanceof WP_Post && has_shortcode((string) $post->post_content, self::SHORTCODE);
    }
}

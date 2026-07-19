<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Logged-in Artist Dashboard.
 *
 * The artist experience lives on the public-facing website rather than inside
 * wp-admin because Amelia Employee accounts may be redirected away from the
 * WordPress administration area.
 */
final class Elev8_OS_Dashboard_Module {

    private const ADMIN_PAGE_SLUG = 'elev8-artist-dashboard';
    private const FRONTEND_PAGE_SLUG = 'artist-dashboard';
    private const FRONTEND_SHORTCODE = 'elev8_artist_dashboard';
    private const PAGE_OPTION = 'elev8_os_artist_dashboard_page_id';

    public static function init(): void {
        add_action('init', [__CLASS__, 'register_shortcode']);
        add_action('admin_menu', [__CLASS__, 'register_admin_menu'], 20);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_frontend_assets']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_assets']);
        add_filter('login_redirect', [__CLASS__, 'login_redirect'], 999, 3);
        add_filter('um_login_redirect_url', [__CLASS__, 'ultimate_member_login_redirect'], 999, 2);
        add_action('um_on_login_before_redirect', [__CLASS__, 'ultimate_member_before_redirect'], 1, 1);
        add_action('template_redirect', [__CLASS__, 'capture_public_browsing_intent'], -999);
        add_action('template_redirect', [__CLASS__, 'redirect_artist_profile_to_dashboard'], 1);
        add_filter('home_url', [__CLASS__, 'artist_public_home_url'], 999, 4);
    }

    public static function status(): string {
        return 'active';
    }

    public static function register_shortcode(): void {
        add_shortcode(self::FRONTEND_SHORTCODE, [__CLASS__, 'shortcode']);
    }

    public static function ensure_frontend_page(): void {
        $page_id = (int) get_option(self::PAGE_OPTION);

        if ($page_id > 0 && get_post_status($page_id)) {
            return;
        }

        $existing = get_page_by_path(self::FRONTEND_PAGE_SLUG);
        if ($existing instanceof WP_Post) {
            update_option(self::PAGE_OPTION, (int) $existing->ID, false);
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        $page_id = wp_insert_post(
            [
                'post_title'   => __('Artist Dashboard', 'elev8-os'),
                'post_name'    => self::FRONTEND_PAGE_SLUG,
                'post_content' => '[' . self::FRONTEND_SHORTCODE . ']',
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_author'  => get_current_user_id(),
            ],
            true
        );

        if (!is_wp_error($page_id) && $page_id > 0) {
            update_option(self::PAGE_OPTION, (int) $page_id, false);
        }
    }

    public static function register_admin_menu(): void {
        add_submenu_page(
            'elev8-os',
            __('Artist Dashboard', 'elev8-os'),
            __('Artist Dashboard', 'elev8-os'),
            'manage_options',
            self::ADMIN_PAGE_SLUG,
            [__CLASS__, 'render_admin_preview']
        );
    }

    public static function enqueue_frontend_assets(): void {
        if (!self::is_dashboard_page()) {
            return;
        }

        wp_enqueue_style('dashicons');
        wp_enqueue_style(
            'elev8-os-artist-dashboard',
            ELEV8_OS_URL . 'assets/css/artist-dashboard.css',
            [],
            ELEV8_OS_VERSION
        );
    }

    public static function enqueue_admin_assets(string $hook): void {
        if ($hook !== 'elev8-os_page_' . self::ADMIN_PAGE_SLUG) {
            return;
        }

        wp_enqueue_style(
            'elev8-os-artist-dashboard',
            ELEV8_OS_URL . 'assets/css/artist-dashboard.css',
            [],
            ELEV8_OS_VERSION
        );
    }

    public static function login_redirect(string $redirect_to, string $requested_redirect_to, $user): string {
        if (!($user instanceof WP_User) || is_wp_error($user)) {
            return $redirect_to;
        }

        if ($user->has_cap('manage_options')) {
            return $redirect_to;
        }

        if (!self::is_artist_user($user)) {
            return $redirect_to;
        }

        return self::dashboard_url();
    }


    /**
     * Ultimate Member does not always run WordPress' login_redirect filter.
     * This filter covers UM roles configured to redirect to a custom URL.
     */
    public static function ultimate_member_login_redirect(string $redirect_url, int $user_id): string {
        $user = get_user_by('id', $user_id);

        if (!($user instanceof WP_User) || $user->has_cap('manage_options') || !self::is_artist_user($user)) {
            return $redirect_url;
        }

        return self::dashboard_url();
    }

    /**
     * Ultimate Member's default role behavior may redirect directly to the UM
     * profile without applying um_login_redirect_url. Redirect linked artists
     * before UM selects that destination.
     */
    public static function ultimate_member_before_redirect(int $user_id): void {
        $user = get_user_by('id', $user_id);

        if (!($user instanceof WP_User) || $user->has_cap('manage_options') || !self::is_artist_user($user)) {
            return;
        }

        wp_safe_redirect(self::dashboard_url());
        exit;
    }

    /**
     * Existing bookmarks and older Ultimate Member redirects may still point
     * to /user/... profile screens. Treat that screen as a bridge into Elev8 OS
     * for linked artists instead of leaving them on an empty social profile.
     */
    /**
     * Keep the theme logo pointed at the real public website while a linked
     * artist is viewing a public artist page. Some membership plugins replace
     * the site-home destination with the member dashboard for logged-in users.
     * The explicit query flag lets Elev8 OS distinguish an intentional public
     * website visit from the normal post-login dashboard redirect.
     */
    public static function artist_public_home_url(string $url, string $path, ?string $scheme, ?int $blog_id): string {
        if (is_admin() || wp_doing_ajax() || !is_user_logged_in()) {
            return $url;
        }

        $user = wp_get_current_user();
        if (!($user instanceof WP_User) || $user->has_cap('manage_options') || !self::is_artist_user($user)) {
            return $url;
        }

        if (!in_array($path, ['', '/'], true)) {
            return $url;
        }

        $public_home = trailingslashit((string) get_option('home'));
        return add_query_arg('elev8_public_home', '1', $public_home);
    }

    /**
     * Render the real public homepage for a linked artist without allowing a
     * membership plugin to replace that request with its member-profile
     * redirect. Cancelling a redirect through the wp_redirect filter leaves a
     * blank response when the originating plugin immediately calls exit, so
     * the public-home request is now handled before those redirect callbacks
     * run.
     */
    public static function capture_public_browsing_intent(): void {
        if (!is_user_logged_in()) {
            return;
        }

        $user = wp_get_current_user();
        if (!($user instanceof WP_User) || $user->has_cap('manage_options') || !self::is_artist_user($user)) {
            return;
        }

        $starting_public_browse = !empty($_GET['elev8_public_home']);
        $continuing_public_browse = !empty($_COOKIE['elev8_public_browse']);

        if (!$starting_public_browse && !$continuing_public_browse) {
            return;
        }

        if ($starting_public_browse && !headers_sent()) {
            setcookie(
                'elev8_public_browse',
                '1',
                [
                    'expires'  => time() + (30 * MINUTE_IN_SECONDS),
                    'path'     => COOKIEPATH ?: '/',
                    'domain'   => defined('COOKIE_DOMAIN') ? (string) COOKIE_DOMAIN : '',
                    'secure'   => is_ssl(),
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]
            );
            $_COOKIE['elev8_public_browse'] = '1';
        }

        // Do not alter the private artist portal itself. The public browsing
        // flag is only meant to stop Ultimate Member from replacing public
        // website pages with its profile/login redirect.
        if (self::is_dashboard_page()) {
            return;
        }

        self::disable_ultimate_member_access_redirect();

        if ($starting_public_browse) {
            add_action('wp_head', [__CLASS__, 'clean_public_home_browser_url'], 1);
        }
    }

    /**
     * Disable only Ultimate Member's global access redirect for the current
     * request. Never remove every template_redirect callback because themes,
     * WooCommerce, WordPress canonical handling, and Elev8 OS routes rely on
     * that hook to render pages correctly.
     */
    private static function disable_ultimate_member_access_redirect(): void {
        if (!function_exists('UM')) {
            return;
        }

        $um = UM();
        if (!is_object($um) || !method_exists($um, 'access')) {
            return;
        }

        $access = $um->access();
        if (!is_object($access)) {
            return;
        }

        remove_action('template_redirect', [$access, 'template_redirect'], 1000);
    }

    public static function clean_public_home_browser_url(): void {
        $home = trailingslashit((string) get_option('home'));
        ?>
        <script>
        (function () {
            if (window.history && window.history.replaceState) {
                window.history.replaceState({}, document.title, <?php echo wp_json_encode($home); ?>);
            }
        }());
        </script>
        <?php
    }

    public static function redirect_artist_profile_to_dashboard(): void {
        if (is_admin() || wp_doing_ajax() || !is_user_logged_in() || self::is_dashboard_page() || !empty($_GET['elev8_public_home']) || !empty($_COOKIE['elev8_public_browse'])) {
            return;
        }

        $user = wp_get_current_user();
        if (!($user instanceof WP_User) || $user->has_cap('manage_options') || !self::is_artist_user($user)) {
            return;
        }

        $is_um_profile = function_exists('um_is_core_page') && um_is_core_page('user');

        if (!$is_um_profile) {
            $request_path = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
            $request_path = (string) wp_parse_url($request_path, PHP_URL_PATH);
            $is_um_profile = (bool) preg_match('#/user/[^/]+/?$#i', $request_path);
        }

        if (!$is_um_profile) {
            return;
        }

        wp_safe_redirect(self::dashboard_url());
        exit;
    }

    public static function shortcode(): string {
        if (!is_user_logged_in()) {
            $login_url = wp_login_url(self::dashboard_url());

            return sprintf(
                '<div class="elev8-dashboard-login"><p>%1$s</p><p><a class="button" href="%2$s">%3$s</a></p></div>',
                esc_html__('Please log in to view your Elev8 OS dashboard.', 'elev8-os'),
                esc_url($login_url),
                esc_html__('Log In', 'elev8-os')
            );
        }

        ob_start();
        self::render_dashboard();
        return (string) ob_get_clean();
    }

    public static function render_admin_preview(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to view this page.', 'elev8-os'));
        }

        echo '<div class="wrap">';
        self::render_dashboard();
        echo '</div>';
    }

    private static function render_dashboard(): void {
        $user = wp_get_current_user();
        $snapshot = class_exists('Elev8_OS_My_Classes_Module')
            ? Elev8_OS_My_Classes_Module::get_dashboard_snapshot($user)
            : [
                'available' => false,
                'reason' => __('The class schedule service is unavailable.', 'elev8-os'),
                'summary' => [],
                'upcoming' => [],
                'artist' => null,
            ];
        $artist = isset($snapshot['artist']) && is_array($snapshot['artist']) ? $snapshot['artist'] : self::find_artist_for_user($user);
        $first_name = $artist ? trim((string) ($artist['firstName'] ?? '')) : trim((string) $user->first_name);
        if ($first_name === '') { $first_name = $user->display_name ?: __('Artist', 'elev8-os'); }
        $summary = isset($snapshot['summary']) && is_array($snapshot['summary']) ? $snapshot['summary'] : [];
        $upcoming = isset($snapshot['upcoming']) && is_array($snapshot['upcoming']) ? $snapshot['upcoming'] : [];
        $next_class = $upcoming[0] ?? null;
        $recommendations = class_exists('Elev8_OS_Recommendation_Service')
            ? Elev8_OS_Recommendation_Service::get_recommendations($user, 25)
            : [];
        if (class_exists('Elev8_OS_Recommendation_State_Service')) {
            $recommendations = Elev8_OS_Recommendation_State_Service::apply_states((int) $user->ID, $recommendations);
        }
        $today_focus = $recommendations[0] ?? null;
        $recommendation_history = class_exists('Elev8_OS_Recommendation_State_Service')
            ? Elev8_OS_Recommendation_State_Service::get_history((int) $user->ID, 6)
            : [];
        $classes_url = Elev8_OS_Portal_Page_Manager::get_url('classes');
        $students_url = Elev8_OS_Portal_Page_Manager::get_url('students');
        $marketing_url = Elev8_OS_Portal_Page_Manager::get_url('marketing');
        $artwork_url = Elev8_OS_Portal_Page_Manager::get_url('artwork');
        $website_url = Elev8_OS_Portal_Page_Manager::get_url('website');
        $edit_website_url = Elev8_OS_Portal_Page_Manager::get_url('edit_website');
        $artist_print_center_url = Elev8_OS_Portal_Page_Manager::get_url('print_center');
        $public_artist_url = '';
        if (is_array($artist)) {
            $artist_name_for_slug = trim((string) ($artist['firstName'] ?? '') . ' ' . (string) ($artist['lastName'] ?? ''));
            if ($artist_name_for_slug === '') {
                $artist_name_for_slug = 'artist-' . absint($artist['id'] ?? 0);
            }
            $public_artist_url = home_url('/artists/' . sanitize_title($artist_name_for_slug) . '/');
        }
        $relationship_snapshot = class_exists('Elev8_OS_Student_Relationship_Service') ? Elev8_OS_Student_Relationship_Service::get_snapshot($user) : ['available'=>false];
        $business = class_exists('Elev8_OS_Artist_Business_Service') ? Elev8_OS_Artist_Business_Service::get_snapshot($user) : [];
        $business_score = is_array($business['score'] ?? null) ? $business['score'] : ['score'=>0,'label'=>__('Unavailable','elev8-os'),'components'=>[]];
        $growth_recommendations = is_array($business['recommendations'] ?? null) ? $business['recommendations'] : [];
        $achievements = is_array($business['achievements'] ?? null) ? $business['achievements'] : [];
        ?>
        <div class="elev8-artist-dashboard elev8-dashboard-v2">
            <?php Elev8_OS_Artist_Portal_Module::render_navigation('dashboard'); ?>
            <?php Elev8_OS_Artist_Portal_Module::render_shortcut_launcher('dashboard'); ?>
            <header class="elev8-dashboard-header elev8-dashboard-hero">
                <div>
                    <p class="elev8-eyebrow"><?php esc_html_e('Artist Portal', 'elev8-os'); ?></p>
                    <h1><?php echo esc_html(sprintf(__('Welcome back, %s!', 'elev8-os'), $first_name)); ?></h1>
                    <p><?php echo esc_html(wp_date('l, F j')); ?> · <?php esc_html_e('Here is what needs your attention.', 'elev8-os'); ?></p>
                    <?php if ($public_artist_url !== '') : ?>
                        <p class="elev8-dashboard-public-link"><span class="dashicons dashicons-admin-site-alt3" aria-hidden="true"></span><a href="<?php echo esc_url($public_artist_url); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('View your public artist page', 'elev8-os'); ?></a></p>
                    <?php endif; ?>
                </div>
                <span class="elev8-dashboard-badge"><?php esc_html_e('Founders Edition', 'elev8-os'); ?></span>
            </header>

            <?php if (class_exists('Elev8_OS_Artist_Success_Module')) { Elev8_OS_Artist_Success_Module::render_artist($user, $business); } ?>

            <?php if (class_exists('Elev8_OS_Business_GPS_Module')) { Elev8_OS_Business_GPS_Module::render_artist($user, $business); } ?>

            <?php if (empty($snapshot['available'])) : ?>
                <div class="elev8-dashboard-warning"><p><strong><?php esc_html_e('Your verified class information is unavailable.', 'elev8-os'); ?></strong><br><?php echo esc_html((string) ($snapshot['reason'] ?? __('No diagnostic was supplied.', 'elev8-os'))); ?></p></div>
            <?php endif; ?>

            <section class="elev8-growth-score-panel" aria-label="<?php esc_attr_e('Business score', 'elev8-os'); ?>">
                <div class="elev8-growth-score-ring"><strong><?php echo esc_html((string)($business_score['score'] ?? 0)); ?></strong><span>/ 100</span></div>
                <div class="elev8-growth-score-copy"><p class="elev8-eyebrow"><?php esc_html_e('Artist Growth Center', 'elev8-os'); ?></p><h2><?php esc_html_e('Your Business Score', 'elev8-os'); ?></h2><p><?php echo esc_html(sprintf(__('Your business health is %s. Improve the areas below to create more opportunities to earn.', 'elev8-os'), strtolower((string)($business_score['label'] ?? __('unavailable','elev8-os'))))); ?></p>
                    <div class="elev8-score-components"><?php foreach ((array)($business_score['components'] ?? []) as $key=>$component): ?><div><span><?php echo esc_html(ucwords(str_replace('_',' ',(string)$key))); ?></span><progress max="100" value="<?php echo esc_attr((string)($component['score'] ?? 0)); ?>"></progress><strong><?php echo isset($component['score']) ? esc_html((string)$component['score'].'%') : esc_html__('Unavailable','elev8-os'); ?></strong></div><?php endforeach; ?></div>
                </div>
            </section>

            <section class="elev8-dashboard-panel elev8-growth-opportunities" aria-label="<?php esc_attr_e('Today’s opportunities', 'elev8-os'); ?>">
                <div class="elev8-panel-heading"><div><p class="elev8-eyebrow"><?php esc_html_e('What should I do today?', 'elev8-os'); ?></p><h2><?php esc_html_e("Today's Opportunities", 'elev8-os'); ?></h2><p class="elev8-panel-intro"><?php esc_html_e('These actions are generated from verified business data—not guesses.', 'elev8-os'); ?></p></div></div>
                <?php if (!$growth_recommendations): ?><div class="elev8-dashboard-empty"><span class="dashicons dashicons-yes-alt"></span><h3><?php esc_html_e('You are caught up', 'elev8-os'); ?></h3><p><?php esc_html_e('No supported growth opportunity needs attention right now.', 'elev8-os'); ?></p></div><?php else: ?>
                <div class="elev8-growth-opportunity-grid"><?php foreach ($growth_recommendations as $index=>$item): $action=(string)($item['action']??''); $url=Elev8_OS_Marketing_Launcher::url($action); ?><article class="priority-<?php echo esc_attr((string)($item['priority']??'medium')); ?>"><span class="dashicons dashicons-<?php echo $index===0?'star-filled':'lightbulb'; ?>"></span><div><small><?php echo $index===0?esc_html__('Best next action','elev8-os'):esc_html__('Growth opportunity','elev8-os'); ?></small><h3><?php echo esc_html((string)($item['title']??'')); ?></h3><p><?php echo esc_html((string)($item['message']??'')); ?></p><a class="button elev8-opportunity-action" href="<?php echo esc_url($url); ?>"><?php echo in_array($action,['classes','artwork','website'],true)?esc_html__('Promote This','elev8-os'):esc_html__('Take Action','elev8-os'); ?></a></div></article><?php endforeach; ?></div>
                <?php endif; ?>
            </section>

            <section class="elev8-dashboard-grid elev8-dashboard-summary elev8-dashboard-summary-v2" aria-label="<?php esc_attr_e('Artist summary', 'elev8-os'); ?>">
                <?php self::render_value_card('calendar-alt', __('Upcoming Classes', 'elev8-os'), $summary['upcoming_count'] ?? null, __('Future class dates assigned to you', 'elev8-os')); ?>
                <?php self::render_value_card('groups', __('Students Enrolled', 'elev8-os'), $summary['student_count'] ?? null, __('Across your upcoming classes', 'elev8-os')); ?>
                <?php self::render_value_card('tickets-alt', __('Seats Available', 'elev8-os'), $summary['seats_available'] ?? null, __('Across classes with verified capacity', 'elev8-os')); ?>
                <?php self::render_value_card('money-alt', __('Booked Value', 'elev8-os'), $summary['booked_value'] ?? null, __('Scheduled booking value, not payout', 'elev8-os'), true); ?>
            </section>

            <?php if (!empty($relationship_snapshot['available'])) : ?>
                <section class="elev8-dashboard-panel elev8-relationship-tickler" aria-label="<?php esc_attr_e('Student relationship tickler', 'elev8-os'); ?>">
                    <div class="elev8-panel-heading"><div><p class="elev8-eyebrow"><?php esc_html_e('Relationship Tickler', 'elev8-os'); ?></p><h2><?php esc_html_e('Your Students', 'elev8-os'); ?></h2><p class="elev8-panel-intro"><?php esc_html_e('The people who already know your work are your strongest path to the next filled class.', 'elev8-os'); ?></p></div><a href="<?php echo esc_url($students_url); ?>"><?php esc_html_e('Open Student Relationships', 'elev8-os'); ?></a></div>
                    <div class="elev8-tickler-grid">
                        <a href="<?php echo esc_url(add_query_arg('segment','all',$students_url)); ?>"><strong><?php echo esc_html(number_format_i18n((int)$relationship_snapshot['total'])); ?></strong><span><?php esc_html_e('All students', 'elev8-os'); ?></span></a>
                        <a href="<?php echo esc_url(add_query_arg('segment','upcoming',$students_url)); ?>"><strong><?php echo esc_html(number_format_i18n((int)$relationship_snapshot['upcoming'])); ?></strong><span><?php esc_html_e('Coming back', 'elev8-os'); ?></span></a>
                        <a href="<?php echo esc_url(add_query_arg('segment','repeat',$students_url)); ?>"><strong><?php echo esc_html(number_format_i18n((int)$relationship_snapshot['repeat'])); ?></strong><span><?php esc_html_e('Repeat students', 'elev8-os'); ?></span></a>
                        <a href="<?php echo esc_url(add_query_arg('segment','inactive',$students_url)); ?>"><strong><?php echo esc_html(number_format_i18n((int)$relationship_snapshot['inactive'])); ?></strong><span><?php esc_html_e('Need follow-up', 'elev8-os'); ?></span></a>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($achievements): ?>
            <section class="elev8-dashboard-panel elev8-achievement-panel"><div class="elev8-panel-heading"><div><p class="elev8-eyebrow"><?php esc_html_e('Progress', 'elev8-os'); ?></p><h2><?php esc_html_e('Achievements', 'elev8-os'); ?></h2></div></div><div class="elev8-achievement-grid"><?php foreach ($achievements as $achievement): $earned=!empty($achievement['earned']); $current=(float)($achievement['current']??0); $target=max(1,(float)($achievement['target']??1)); ?><article class="<?php echo $earned?'is-earned':'is-locked'; ?>"><span class="dashicons dashicons-<?php echo $earned?'awards':'lock'; ?>"></span><strong><?php echo esc_html((string)($achievement['title']??'')); ?></strong><small><?php echo $earned?esc_html__('Earned','elev8-os'):esc_html(sprintf(__('%s of %s','elev8-os'),number_format_i18n($current),number_format_i18n($target))); ?></small><progress max="100" value="<?php echo esc_attr((string)min(100,round(($current/$target)*100))); ?>"></progress></article><?php endforeach; ?></div></section>
            <?php endif; ?>

            <?php if (class_exists('Elev8_OS_Marketing_Module')) { Elev8_OS_Marketing_Module::render_art_walk_tickler($user); } ?>

            <div class="elev8-dashboard-main-grid">
                <section class="elev8-dashboard-panel elev8-next-class-panel">
                    <div class="elev8-panel-heading"><div><p class="elev8-eyebrow"><?php esc_html_e('Up next', 'elev8-os'); ?></p><h2><?php esc_html_e('Next Class', 'elev8-os'); ?></h2></div><a href="<?php echo esc_url($classes_url); ?>"><?php esc_html_e('View all classes', 'elev8-os'); ?></a></div>
                    <?php if (!$next_class) : ?>
                        <div class="elev8-dashboard-empty"><span class="dashicons dashicons-calendar-alt"></span><h3><?php esc_html_e('No upcoming class found', 'elev8-os'); ?></h3><p><?php esc_html_e('Your next verified Amelia class will appear here.', 'elev8-os'); ?></p></div>
                    <?php else : ?>
                        <?php $ts = strtotime((string) $next_class['start']); ?>
                        <article class="elev8-next-class">
                            <div class="elev8-next-class-date"><span><?php echo esc_html($ts ? wp_date('M', $ts) : ''); ?></span><strong><?php echo esc_html($ts ? wp_date('j', $ts) : ''); ?></strong></div>
                            <div class="elev8-next-class-body"><h3><?php echo esc_html((string) $next_class['name']); ?></h3><p><span class="dashicons dashicons-clock"></span><?php echo esc_html($ts ? wp_date(get_option('date_format').' '.get_option('time_format'), $ts) : (string) $next_class['start']); ?></p><?php if ((string)$next_class['location'] !== '') : ?><p><span class="dashicons dashicons-location"></span><?php echo esc_html((string)$next_class['location']); ?></p><?php endif; ?><div class="elev8-next-class-facts"><span><strong><?php echo esc_html(number_format_i18n((int)$next_class['students'])); ?></strong> <?php esc_html_e('students', 'elev8-os'); ?></span><span><strong><?php echo $next_class['seats_left'] === null ? esc_html__('Unavailable','elev8-os') : esc_html(number_format_i18n((int)$next_class['seats_left'])); ?></strong> <?php esc_html_e('seats left', 'elev8-os'); ?></span></div></div>
                        </article>
                    <?php endif; ?>
                </section>

                <section class="elev8-dashboard-panel">
                    <div class="elev8-panel-heading"><div><p class="elev8-eyebrow"><?php esc_html_e('Shortcuts', 'elev8-os'); ?></p><h2><?php esc_html_e('Quick Actions', 'elev8-os'); ?></h2></div></div>
                    <div class="elev8-quick-actions">
                        <?php self::render_action_link('art', __('Add Artwork', 'elev8-os'), __('Create a product, QR page, and inventory record', 'elev8-os'), $artwork_url . '#elev8-artwork-editor'); ?>
                        <?php self::render_action_link('calendar-alt', __('Manage My Classes', 'elev8-os'), __('Schedule, enrollment, and booking links', 'elev8-os'), $classes_url); ?>
                        <?php self::render_action_link('groups', __('View My Students', 'elev8-os'), __('Open class rosters and contact details', 'elev8-os'), $students_url); ?>
                        <?php self::render_action_link('email-alt', __('Email My Students', 'elev8-os'), __('Create a safe campaign for a verified student segment', 'elev8-os'), $marketing_url); ?>
                        <?php self::render_action_link('admin-home', __('View My Website', 'elev8-os'), __('See what customers see', 'elev8-os'), $website_url); ?>
                        <?php self::render_action_link('edit', __('Edit My Website', 'elev8-os'), __('Update your bio, links, and profile', 'elev8-os'), $edit_website_url); ?>
                        <?php if ($artist && !empty($artist['id'])) : ?>
                            <?php self::render_action_link('media-document', __('My Print Center', 'elev8-os'), __('Print your artist card, profile QR, and artwork labels', 'elev8-os'), $artist_print_center_url); ?>
                        <?php endif; ?>
                    </div>
                </section>
            </div>

            <?php if (isset($_GET['elev8_recommendation']) && sanitize_key((string) $_GET['elev8_recommendation']) === 'completed') : ?>
                <div class="elev8-momentum-success" role="status"><span class="dashicons dashicons-yes-alt"></span><div><strong><?php esc_html_e('Nice work!', 'elev8-os'); ?></strong><p><?php esc_html_e('Recommendation completed. Your next recommendation is now ready.', 'elev8-os'); ?></p></div></div>
            <?php endif; ?>

            <section class="elev8-dashboard-panel elev8-recommendations-panel" aria-label="<?php esc_attr_e('Today’s focus', 'elev8-os'); ?>">
                <div class="elev8-panel-heading">
                    <div><p class="elev8-eyebrow"><?php esc_html_e('Business Coach', 'elev8-os'); ?></p><h2><?php esc_html_e("Today's Focus", 'elev8-os'); ?> ⭐</h2><p class="elev8-panel-intro"><?php esc_html_e('The single most valuable verified action you can take today.', 'elev8-os'); ?></p></div>
                </div>
                <?php if (!$today_focus) : ?>
                    <div class="elev8-dashboard-empty"><span class="dashicons dashicons-yes-alt"></span><h3><?php esc_html_e('You are caught up', 'elev8-os'); ?></h3><p><?php esc_html_e('No high-value next step is currently supported by the available data.', 'elev8-os'); ?></p></div>
                <?php else : ?>
                    <?php $focus_state = sanitize_key((string) ($today_focus['state'] ?? 'not_started')); ?>
                    <article class="elev8-focus-card priority-<?php echo esc_attr((string) $today_focus['priority']); ?>">
                        <div class="elev8-focus-topline"><span class="elev8-focus-category"><?php echo esc_html(ucfirst((string) $today_focus['category'])); ?></span><span class="elev8-focus-status"><?php echo esc_html($focus_state === 'in_progress' ? __('In Progress', 'elev8-os') : __('Not Started', 'elev8-os')); ?></span></div>
                        <h3><?php echo esc_html((string) $today_focus['title']); ?></h3>
                        <p class="elev8-focus-description"><?php echo esc_html((string) $today_focus['description']); ?></p>
                        <div class="elev8-focus-facts">
                            <div><span><?php esc_html_e('Impact', 'elev8-os'); ?></span><strong aria-label="<?php echo esc_attr(sprintf(__('%d out of 5 stars', 'elev8-os'), (int) $today_focus['impact_stars'])); ?>"><?php echo esc_html(str_repeat('★', (int) $today_focus['impact_stars']) . str_repeat('☆', 5 - (int) $today_focus['impact_stars'])); ?></strong></div>
                            <div><span><?php esc_html_e('Estimated Time', 'elev8-os'); ?></span><strong><?php echo esc_html((string) $today_focus['estimated_time']); ?></strong></div>
                            <div><span><?php esc_html_e('Expected Result', 'elev8-os'); ?></span><strong><?php echo esc_html((string) $today_focus['estimated_impact']); ?></strong></div>
                        </div>
                        <div class="elev8-focus-reason"><strong><?php esc_html_e('Why this matters:', 'elev8-os'); ?></strong> <?php echo esc_html((string) $today_focus['reason']); ?></div>
                        <div class="elev8-focus-actions">
                            <?php if ($focus_state !== 'in_progress') : ?>
                                <?php self::render_recommendation_state_form($today_focus, 'in_progress', __('Start This Focus', 'elev8-os'), 'primary'); ?>
                            <?php endif; ?>
                            <?php if ($focus_state === 'in_progress' && (string) $today_focus['action_url'] !== '') : ?><a class="elev8-recommendation-action" href="<?php echo esc_url((string) $today_focus['action_url']); ?>"><?php echo esc_html((string) $today_focus['action_label']); ?><span class="dashicons dashicons-arrow-right-alt2"></span></a><?php endif; ?>
                            <?php if ($focus_state === 'in_progress') : ?><?php self::render_recommendation_state_form($today_focus, 'completed', __('Mark Complete', 'elev8-os'), 'complete'); ?><?php endif; ?>
                            <?php if (!empty($today_focus['dismissable'])) : ?><?php self::render_recommendation_state_form($today_focus, 'dismissed', __('Not Relevant', 'elev8-os'), 'quiet'); ?><?php endif; ?>
                        </div>
                    </article>
                <?php endif; ?>
                <p class="elev8-recommendation-source"><?php esc_html_e('Powered by verified Elev8 OS business data and rules.', 'elev8-os'); ?></p>
            </section>


            <section class="elev8-dashboard-panel elev8-dashboard-status-panel">
                <div class="elev8-panel-heading"><div><p class="elev8-eyebrow"><?php esc_html_e('Today', 'elev8-os'); ?></p><h2><?php esc_html_e('Your Artist Checklist', 'elev8-os'); ?></h2></div></div>
                <div class="elev8-artist-checklist">
                    <div class="is-complete"><span class="dashicons dashicons-yes-alt"></span><div><strong><?php esc_html_e('Artist account connected', 'elev8-os'); ?></strong><p><?php echo $artist ? esc_html__('Your WordPress account is mapped to Amelia.', 'elev8-os') : esc_html__('Connection unavailable.', 'elev8-os'); ?></p></div></div>
                    <div class="<?php echo $next_class ? 'is-complete' : 'is-pending'; ?>"><span class="dashicons dashicons-<?php echo $next_class ? 'yes-alt' : 'warning'; ?>"></span><div><strong><?php esc_html_e('Upcoming schedule', 'elev8-os'); ?></strong><p><?php echo $next_class ? esc_html__('Your next class is ready to review.', 'elev8-os') : esc_html__('No upcoming class is currently verified.', 'elev8-os'); ?></p></div></div>
                    <div class="is-ready"><span class="dashicons dashicons-admin-page"></span><div><strong><?php esc_html_e('Keep your public page current', 'elev8-os'); ?></strong><p><?php esc_html_e('Review your bio, links, and class information regularly.', 'elev8-os'); ?></p></div><a href="<?php echo esc_url($edit_website_url); ?>"><?php esc_html_e('Review website', 'elev8-os'); ?></a></div>
                </div>
            </section>

            <section class="elev8-dashboard-panel elev8-momentum-activity-panel">
                <div class="elev8-panel-heading"><div><p class="elev8-eyebrow"><?php esc_html_e('Momentum', 'elev8-os'); ?></p><h2><?php esc_html_e('Recent Activity', 'elev8-os'); ?></h2></div></div>
                <?php if (!$recommendation_history) : ?><div class="elev8-dashboard-empty"><p><?php esc_html_e('Your recommendation activity will appear here as you build momentum.', 'elev8-os'); ?></p></div><?php else : ?>
                    <div class="elev8-momentum-activity-list"><?php foreach ($recommendation_history as $activity) : ?><div><span class="dashicons dashicons-<?php echo esc_attr((string) $activity['type'] === 'recommendation_completed' ? 'yes-alt' : ((string) $activity['type'] === 'recommendation_dismissed' ? 'dismiss' : 'controls-play')); ?>"></span><div><strong><?php echo esc_html((string) $activity['label']); ?></strong><p><?php echo esc_html(self::activity_label((string) $activity['type'])); ?> · <?php echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), (string) $activity['occurred_at'])); ?></p></div></div><?php endforeach; ?></div>
                <?php endif; ?>
            </section>

            <?php if (class_exists('Elev8_OS_Gallery_Operations_Module')) : ?>
                <section class="elev8-dashboard-panel elev8-gallery-location-panel">
                    <div class="elev8-panel-heading"><div><p class="elev8-eyebrow"><?php esc_html_e('Gallery', 'elev8-os'); ?></p><h2><?php esc_html_e('Where is my artwork?', 'elev8-os'); ?></h2><p class="elev8-panel-intro"><?php esc_html_e('See what is currently displayed, where it is located, and how long it has been on the floor.', 'elev8-os'); ?></p></div></div>
                    <?php echo do_shortcode('[elev8_artist_gallery_status]'); ?>
                </section>
            <?php endif; ?>

            <section class="elev8-dashboard-panel elev8-future-reports"><p class="elev8-eyebrow"><?php esc_html_e('Coming Later', 'elev8-os'); ?></p><h2><?php esc_html_e('Future Reports', 'elev8-os'); ?></h2><p><?php esc_html_e('Your completed actions are building the history future coaching and reports will use.', 'elev8-os'); ?></p></section>
        </div>
        <?php
    }

    /** @param array<string,mixed> $recommendation */
    private static function render_recommendation_state_form(array $recommendation, string $status, string $label, string $style): void {
        ?>
        <form class="elev8-focus-state-form style-<?php echo esc_attr($style); ?>" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="elev8_recommendation_state">
            <input type="hidden" name="recommendation_id" value="<?php echo esc_attr((string) $recommendation['id']); ?>">
            <input type="hidden" name="recommendation_title" value="<?php echo esc_attr((string) $recommendation['title']); ?>">
            <input type="hidden" name="recommendation_priority" value="<?php echo esc_attr((string) $recommendation['priority']); ?>">
            <input type="hidden" name="recommendation_status" value="<?php echo esc_attr($status); ?>">
            <input type="hidden" name="redirect_to" value="<?php echo esc_url(self::dashboard_url()); ?>">
            <?php wp_nonce_field('elev8_recommendation_state_' . get_current_user_id()); ?>
            <button type="submit"><?php echo esc_html($label); ?></button>
        </form>
        <?php
    }

    private static function activity_label(string $type): string {
        return [
            'recommendation_started' => __('Recommendation Started', 'elev8-os'),
            'recommendation_completed' => __('Recommendation Completed', 'elev8-os'),
            'recommendation_dismissed' => __('Recommendation Dismissed', 'elev8-os'),
        ][$type] ?? __('Recommendation Updated', 'elev8-os');
    }

    /** @param int|float|null $value */
    private static function render_value_card(string $icon, string $title, $value, string $description, bool $money = false): void {
        $available = is_numeric($value);
        $display = __('Unavailable', 'elev8-os');
        if ($available) { $display = $money ? self::format_money((float)$value) : number_format_i18n((int)$value); }
        ?>
        <article class="elev8-welcome-card <?php echo $available ? '' : 'is-unavailable'; ?>"><div><span class="dashicons dashicons-<?php echo esc_attr($icon); ?>"></span><div><p class="elev8-card-label"><?php echo esc_html($title); ?></p><strong><?php echo esc_html($display); ?></strong><p><?php echo esc_html($description); ?></p></div></div></article>
        <?php
    }

    private static function render_action_link(string $icon, string $title, string $description, string $url): void {
        ?><a class="elev8-quick-action" href="<?php echo esc_url($url); ?>"><span class="dashicons dashicons-<?php echo esc_attr($icon); ?>"></span><span><strong><?php echo esc_html($title); ?></strong><small><?php echo esc_html($description); ?></small></span><span class="dashicons dashicons-arrow-right-alt2"></span></a><?php
    }

    private static function format_money(float $value): string {
        if (function_exists('wc_price')) { return wp_strip_all_tags((string) wc_price($value)); }
        return '$' . number_format_i18n($value, 2);
    }

    private static function is_artist_user(WP_User $user): bool {
        if (self::find_artist_for_user($user)) {
            return true;
        }

        foreach ((array) $user->roles as $role) {
            $normalized = strtolower(str_replace(['_', '-'], ' ', (string) $role));

            if (strpos($normalized, 'amelia') !== false && (
                strpos($normalized, 'employee') !== false ||
                strpos($normalized, 'provider') !== false
            )) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function find_artist_for_user(WP_User $user): ?array {
        global $wpdb;
        $table = $wpdb->prefix . 'amelia_users';
        if (!self::table_exists($table)) { return null; }
        $columns = self::table_columns($table);
        if (!in_array('id', $columns, true)) { return null; }
        $select = ['id'];
        foreach (['firstName','lastName','email'] as $column) { if (in_array($column,$columns,true)) { $select[]=$column; } }
        $select_sql = implode(', ', array_map(static fn(string $column): string => "`{$column}`", $select));
        $type_sql = in_array('type',$columns,true) ? " AND LOWER(COALESCE(`type`,'')) IN ('provider','employee')" : '';
        $mapped_id = max(0,(int)get_user_meta($user->ID,'elev8_os_amelia_employee_id',true));
        if ($mapped_id > 0) {
            $mapped = $wpdb->get_row($wpdb->prepare("SELECT {$select_sql} FROM `{$table}` WHERE `id`=%d{$type_sql} LIMIT 1",$mapped_id),ARRAY_A);
            if (is_array($mapped)) { return $mapped; }
        }
        $email=sanitize_email((string)$user->user_email);
        if ($email==='' || !in_array('email',$columns,true)) { return null; }
        $artist=$wpdb->get_row($wpdb->prepare("SELECT {$select_sql} FROM `{$table}` WHERE LOWER(`email`)=LOWER(%s){$type_sql} LIMIT 1",$email),ARRAY_A);
        return is_array($artist)?$artist:null;
    }

    /**
     * Count scheduled service/date assignments for an Amelia provider.
     *
     * This restores the behavior verified on Elev8 Arts, where Heather's
     * three scheduled dates appear as three provider-to-service assignments.
     */
    private static function get_active_service_count(int $artist_id): int {
        global $wpdb;

        $relation_tables = [
            $wpdb->prefix . 'amelia_providers_to_services',
            $wpdb->prefix . 'amelia_services_providers',
            $wpdb->prefix . 'amelia_providers_services',
        ];

        $relation_table = '';

        foreach ($relation_tables as $candidate) {
            if (self::table_exists($candidate)) {
                $relation_table = $candidate;
                break;
            }
        }

        if ($relation_table === '') {
            return 0;
        }

        $columns = self::table_columns($relation_table);
        $provider_column = self::first_existing_column(
            $columns,
            ['userId', 'providerId', 'employeeId', 'provider_id', 'user_id']
        );

        if (!$provider_column) {
            return 0;
        }

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*)
                 FROM `{$relation_table}`
                 WHERE `{$provider_column}` = %d",
                $artist_id
            )
        );

        return max(0, (int) $count);
    }

    /**
     * Count future Amelia appointments that have actually been booked.
     */
    private static function get_upcoming_booking_count(int $artist_id): int {
        global $wpdb;

        $appointments = $wpdb->prefix . 'amelia_appointments';

        if (!self::table_exists($appointments)) {
            return 0;
        }

        $columns = self::table_columns($appointments);
        $provider_id = self::first_existing_column(
            $columns,
            ['providerId', 'provider_id', 'employeeId']
        );
        $booking_start = self::first_existing_column(
            $columns,
            ['bookingStart', 'booking_start', 'start']
        );

        if (!$provider_id || !$booking_start) {
            return 0;
        }

        $status_sql = '';

        if (in_array('status', $columns, true)) {
            $status_sql = " AND LOWER(COALESCE(`status`, '')) NOT IN ('canceled', 'cancelled', 'rejected')";
        }

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT `id`)
                 FROM `{$appointments}`
                 WHERE `{$provider_id}` = %d
                   AND `{$booking_start}` >= %s
                   {$status_sql}",
                $artist_id,
                current_time('mysql')
            )
        );

        return max(0, (int) $count);
    }

    private static function dashboard_url(): string {
        return Elev8_OS_Portal_Page_Manager::get_url('dashboard');
    }

    private static function is_dashboard_page(): bool {
        return Elev8_OS_Portal_Page_Manager::is_current_page('dashboard');
    }

    /**
     * @return array<int,string>
     */
    private static function table_columns(string $table): array {
        global $wpdb;

        $columns = $wpdb->get_col("DESCRIBE `{$table}`", 0);

        return is_array($columns) ? array_map('strval', $columns) : [];
    }

    /**
     * @param array<int,string> $available
     * @param array<int,string> $candidates
     */
    private static function first_existing_column(array $available, array $candidates): ?string {
        foreach ($candidates as $candidate) {
            if (in_array($candidate, $available, true)) {
                return $candidate;
            }
        }

        return null;
    }

    private static function table_exists(string $table): bool {
        global $wpdb;

        $found = $wpdb->get_var(
            $wpdb->prepare('SHOW TABLES LIKE %s', $table)
        );

        return $found === $table;
    }
}

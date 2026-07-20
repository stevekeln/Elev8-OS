<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Role-aware, mobile-first launch page for Elev8 OS.
 */
final class Elev8_OS_Mobile_Home_Module {
    private const PAGE_OPTION = 'elev8_os_mobile_home_page_id';
    private const PAGE_SLUG = 'elev8-app';
    private const SHORTCODE = 'elev8_os_mobile_home';

    public static function init(): void {
        add_shortcode(self::SHORTCODE, [__CLASS__, 'shortcode']);
        add_action('admin_init', [__CLASS__, 'ensure_page_for_admin'], 35);
        add_action('wp_enqueue_scripts', [__CLASS__, 'assets']);
        add_action('wp_head', [__CLASS__, 'app_meta']);
        add_filter('body_class', [__CLASS__, 'body_class']);
    }

    public static function ensure_page_for_admin(): void {
        if (Elev8_OS_Access_Service::user_can('platform_admin')) {
            self::ensure_page(true);
        }
    }

    public static function ensure_page(bool $allow_create = true): int {
        $stored = absint(get_option(self::PAGE_OPTION));
        if ($stored > 0 && get_post_status($stored) && get_post_status($stored) !== 'trash') {
            self::ensure_shortcode($stored);
            return $stored;
        }

        $page = get_page_by_path(self::PAGE_SLUG, OBJECT, 'page');
        if ($page instanceof WP_Post && $page->post_status !== 'trash') {
            if ($page->post_status !== 'publish') {
                wp_update_post(['ID' => $page->ID, 'post_status' => 'publish']);
            }
            self::ensure_shortcode((int) $page->ID);
            update_option(self::PAGE_OPTION, (int) $page->ID, false);
            return (int) $page->ID;
        }

        if (!$allow_create || !Elev8_OS_Access_Service::user_can('platform_admin')) {
            return 0;
        }

        $page_id = wp_insert_post([
            'post_title' => __('Elev8 OS Home', 'elev8-os'),
            'post_name' => self::PAGE_SLUG,
            'post_content' => '[' . self::SHORTCODE . ']',
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_author' => get_current_user_id(),
            'comment_status' => 'closed',
        ], true);

        if (is_wp_error($page_id) || $page_id <= 0) {
            return 0;
        }

        update_option(self::PAGE_OPTION, (int) $page_id, false);
        return (int) $page_id;
    }

    public static function get_url(): string {
        $id = self::ensure_page(false);
        if ($id > 0) {
            $url = get_permalink($id);
            if (is_string($url) && $url !== '') { return $url; }
        }
        return home_url('/' . self::PAGE_SLUG . '/');
    }

    public static function assets(): void {
        if (!self::is_current_page()) { return; }
        wp_enqueue_style('elev8-os-mobile-home', ELEV8_OS_URL . 'assets/css/mobile-home.css', [], ELEV8_OS_VERSION);
    }

    public static function app_meta(): void {
        if (!self::is_current_page()) { return; }
        $icon = get_site_icon_url(192);
        echo '<meta name="theme-color" content="#6f2dbd">' . "\n";
        echo '<meta name="mobile-web-app-capable" content="yes">' . "\n";
        echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
        echo '<meta name="apple-mobile-web-app-status-bar-style" content="default">' . "\n";
        echo '<meta name="apple-mobile-web-app-title" content="Elev8 OS">' . "\n";
        if ($icon) {
            echo '<link rel="apple-touch-icon" href="' . esc_url($icon) . '">' . "\n";
        }
    }

    public static function body_class(array $classes): array {
        if (self::is_current_page()) { $classes[] = 'elev8-os-mobile-home-page'; }
        return $classes;
    }

    public static function shortcode(): string {
        if (!is_user_logged_in()) {
            $login = wp_login_url(self::get_url());
            return '<div class="elev8-mobile-home elev8-mobile-login"><div class="elev8-mobile-hero"><p class="eyebrow">ELEV8 OS</p><h1>Business Home</h1><p>Sign in to see the tools available for your role.</p><a class="elev8-mobile-primary" href="' . esc_url($login) . '">Sign In to Elev8 OS</a></div></div>';
        }

        $user = wp_get_current_user();
        $first = trim((string) $user->first_name);
        $name = $first !== '' ? $first : ($user->display_name ?: __('Team Member', 'elev8-os'));
        $cards = self::cards_for_user($user);

        ob_start();
        ?>
        <main class="elev8-mobile-home">
            <header class="elev8-mobile-hero">
                <p class="eyebrow"><?php esc_html_e('ELEV8 OS', 'elev8-os'); ?></p>
                <h1><?php echo esc_html(sprintf(__('Hello, %s', 'elev8-os'), $name)); ?></h1>
                <p><?php esc_html_e('Your most-used business tools in one place. You only see tools available to your account.', 'elev8-os'); ?></p>
            </header>

            <section class="elev8-mobile-grid" aria-label="<?php esc_attr_e('Elev8 OS tools', 'elev8-os'); ?>">
                <?php foreach ($cards as $card) : ?>
                    <a class="elev8-mobile-card <?php echo !empty($card['primary']) ? 'is-primary' : ''; ?>" href="<?php echo esc_url($card['url']); ?>">
                        <span class="dashicons dashicons-<?php echo esc_attr($card['icon']); ?>" aria-hidden="true"></span>
                        <strong><?php echo esc_html($card['title']); ?></strong>
                        <small><?php echo esc_html($card['description']); ?></small>
                    </a>
                <?php endforeach; ?>
            </section>

            <aside class="elev8-mobile-install-tip">
                <strong><?php esc_html_e('Put Elev8 OS on your phone', 'elev8-os'); ?></strong>
                <span><?php esc_html_e('Use your browser menu and choose “Add to Home Screen.” This page will open like an app button.', 'elev8-os'); ?></span>
            </aside>
        </main>
        <?php
        return (string) ob_get_clean();
    }

    /** @return array<int,array<string,string|bool>> */
    private static function cards_for_user(WP_User $user): array {
        $cards = [];
        $can = static fn(string $permission): bool => Elev8_OS_Access_Service::user_can($permission, $user);

        if ($can('view_ceo_dashboard')) {
            $cards[] = self::card(__('CEO Dashboard', 'elev8-os'), __('See the owner view, priorities, and business intelligence.', 'elev8-os'), admin_url('admin.php?page=elev8-ceo-dashboard'), 'chart-area', true);
            $cards[] = self::card(__('Record Business Memory', 'elev8-os'), __('Quickly document a conversation, event, decision, or incident.', 'elev8-os'), admin_url('admin.php?page=elev8-business-memory&view=new'), 'edit-page', true);
        }
        if ($can('submit_manager_log')) {
            $cards[] = self::card(__('Manager Operations Log', 'elev8-os'), __('Record each management work period or location visit.', 'elev8-os'), add_query_arg(['type'=>'manager','team'=>'1'], Elev8_OS_Checkin_Center_Module::page_url()), 'clipboard', true);
        } elseif ($can('submit_retail_log')) {
            $cards[] = self::card(__('Retail Employee Log', 'elev8-os'), __('Record shift activity, customer requests, inventory signals, and store needs.', 'elev8-os'), add_query_arg(['type'=>'retail','team'=>'1'], Elev8_OS_Checkin_Center_Module::page_url()), 'store', true);
        }
        if ($can('manage_daily_operations')) {
            $cards[] = self::card(__('Daily Operations', 'elev8-os'), __('Review submitted work logs and operational signals.', 'elev8-os'), admin_url('admin.php?page=elev8-daily-operations&view=brief'), 'clipboard');
        }
        if ($can('view_artist_dashboard')) {
            $cards[] = self::card(__('Artist Dashboard', 'elev8-os'), __('View your profile, classes, business tools, and opportunities.', 'elev8-os'), Elev8_OS_Portal_Page_Manager::get_url('dashboard'), 'art', true);
        }
        if ($can('view_artist_classes')) {
            $cards[] = self::card(__('My Classes', 'elev8-os'), __('See scheduled classes, bookings, and teaching details.', 'elev8-os'), Elev8_OS_Portal_Page_Manager::get_url('classes'), 'calendar-alt');
        }
        if ($can('submit_artist_log')) {
            $cards[] = self::card(__('Artist Operating Log', 'elev8-os'), __('Preserve class results, student feedback, supply needs, and ideas.', 'elev8-os'), add_query_arg(['type'=>'artist','team'=>'1'], Elev8_OS_Checkin_Center_Module::page_url()), 'welcome-write-blog');
        }
        if ($can('submit_event_log')) {
            $cards[] = self::card(__('Event Operations Log', 'elev8-os'), __('Record attendance, performers, event needs, problems, and lessons learned.', 'elev8-os'), add_query_arg(['type'=>'event','team'=>'1'], Elev8_OS_Checkin_Center_Module::page_url()), 'microphone', true);
        }
        if ($can('view_glass_dashboard')) {
            $cards[] = self::card(__('Glass Manager Dashboard', 'elev8-os'), __('Manage production, cremation orders, blower work, and payouts.', 'elev8-os'), admin_url('admin.php?page=elev8-glass-operations'), 'hammer', true);
        }
        if ($can('manage_relationships')) {
            $cards[] = self::card(__('Relationships & Outreach', 'elev8-os'), __('Build community relationships, run flyer campaigns, and record visits.', 'elev8-os'), admin_url('admin.php?page=elev8-community-outreach'), 'location-alt');
        }
        if ($can('view_business_memory')) {
            $cards[] = self::card(__('Business Memory', 'elev8-os'), __('Search records, follow-ups, risks, and recurring patterns.', 'elev8-os'), admin_url('admin.php?page=elev8-business-memory'), 'database-view');
        }
        if (($can('manage_reservations') || $can('manage_bingo') || $can('view_assigned_reservations')) && class_exists('Elev8_OS_Bingo_Reservations_Module')) {
            $assigned_user_id = ($can('manage_reservations') || $can('manage_bingo')) ? 0 : (int) $user->ID;
            $attention = Elev8_OS_Bingo_Reservations_Module::attention_count($assigned_user_id);
            $upcoming = Elev8_OS_Bingo_Reservations_Module::upcoming_count(7, $assigned_user_id);
            $cards[] = self::card(
                __('Reservations', 'elev8-os'),
                sprintf(__('%1$d need attention · %2$d upcoming this week.', 'elev8-os'), $attention, $upcoming),
                Elev8_OS_Bingo_Reservations_Module::admin_url(),
                'tickets-alt',
                $attention > 0
            );
        }
        if ($can('manage_checkins')) {
            $cards[] = self::card(__('Check-In Center', 'elev8-os'), __('Open public and internal check-ins, links, and QR tools.', 'elev8-os'), home_url('/checkin/?team=1'), 'forms');
        }
        return apply_filters('elev8_os_mobile_home_cards', $cards, $user);
    }

    /** @return array<string,string|bool> */
    private static function card(string $title, string $description, string $url, string $icon, bool $primary = false): array {
        return compact('title', 'description', 'url', 'icon', 'primary');
    }

    private static function is_current_page(): bool {
        $id = absint(get_option(self::PAGE_OPTION));
        return ($id > 0 && is_page($id)) || is_page(self::PAGE_SLUG);
    }

    private static function ensure_shortcode(int $page_id): void {
        $post = get_post($page_id);
        if (!$post instanceof WP_Post) { return; }
        $shortcode = '[' . self::SHORTCODE . ']';
        if (!has_shortcode((string) $post->post_content, self::SHORTCODE)) {
            wp_update_post(['ID' => $page_id, 'post_content' => $shortcode]);
        }
    }
}

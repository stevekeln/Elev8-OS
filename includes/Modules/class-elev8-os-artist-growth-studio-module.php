<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Artist-facing home for content, promotion, print, QR, and campaign tools.
 * Existing Marketing and Content Studio modules remain the source of truth.
 */
final class Elev8_OS_Artist_Growth_Studio_Module {
    public static function init(): void {
        add_shortcode('elev8_artist_growth_studio', [__CLASS__, 'shortcode']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'assets']);
        add_filter('body_class', [__CLASS__, 'body_classes']);
    }

    public static function body_classes(array $classes): array {
        if (Elev8_OS_Portal_Page_Manager::is_current_page('growth_studio')) {
            $classes[] = 'elev8-growth-studio-page';
        }
        return $classes;
    }

    public static function assets(): void {
        if (!Elev8_OS_Portal_Page_Manager::is_current_page('growth_studio')) { return; }
        wp_enqueue_style('dashicons');
        wp_enqueue_style('elev8-os-growth-studio', ELEV8_OS_URL . 'assets/css/artist-growth-studio.css', [], ELEV8_OS_VERSION);
    }

    public static function shortcode(): string {
        if (!is_user_logged_in()) {
            return '<div class="elev8-dashboard-login"><p>' . esc_html__('Please log in to use Growth Studio.', 'elev8-os') . '</p></div>';
        }

        $marketing = Elev8_OS_Portal_Page_Manager::get_url('marketing');
        $content = Elev8_OS_Portal_Page_Manager::get_url('content_studio');
        $print = Elev8_OS_Portal_Page_Manager::get_url('print_center');
        $artwork = Elev8_OS_Portal_Page_Manager::get_url('artwork');
        $website = Elev8_OS_Portal_Page_Manager::get_url('website');
        $events = home_url('/events/');

        ob_start(); ?>
        <div class="elev8-growth-studio">
            <?php Elev8_OS_Artist_Portal_Module::render_navigation('growth_studio'); ?>

            <main class="elev8-growth-studio-main">
                <header class="elev8-growth-hero">
                    <div>
                        <p class="elev8-growth-kicker"><?php esc_html_e('Your artist growth workspace', 'elev8-os'); ?></p>
                        <h1><?php esc_html_e('Growth Studio', 'elev8-os'); ?></h1>
                        <p><?php esc_html_e('Create your message, promote what matters, and put your art in front of more people—all from one place.', 'elev8-os'); ?></p>
                    </div>
                    <span class="elev8-growth-badge"><?php esc_html_e('Create • Promote • Print', 'elev8-os'); ?></span>
                </header>

                <section class="elev8-growth-question" aria-labelledby="elev8-growth-question-title">
                    <div class="elev8-growth-section-heading">
                        <p><?php esc_html_e('Start here', 'elev8-os'); ?></p>
                        <h2 id="elev8-growth-question-title"><?php esc_html_e('What do you want to promote today?', 'elev8-os'); ?></h2>
                    </div>
                    <div class="elev8-growth-goals">
                        <a href="<?php echo esc_url(add_query_arg('campaign_goal', 'sell_artwork', $content)); ?>#elev8-campaign-builder"><span class="dashicons dashicons-art"></span><strong><?php esc_html_e('My artwork', 'elev8-os'); ?></strong><small><?php esc_html_e('Tell the story and help collectors discover it.', 'elev8-os'); ?></small></a>
                        <a href="<?php echo esc_url(add_query_arg('campaign_goal', 'fill_class', $content)); ?>#elev8-campaign-builder"><span class="dashicons dashicons-tickets-alt"></span><strong><?php esc_html_e('A class', 'elev8-os'); ?></strong><small><?php esc_html_e('Create content that helps fill available seats.', 'elev8-os'); ?></small></a>
                        <a href="<?php echo esc_url(add_query_arg('campaign_goal', 'announce_event', $content)); ?>#elev8-campaign-builder"><span class="dashicons dashicons-megaphone"></span><strong><?php esc_html_e('An event', 'elev8-os'); ?></strong><small><?php esc_html_e('Build excitement around an upcoming experience.', 'elev8-os'); ?></small></a>
                        <a href="<?php echo esc_url(add_query_arg('campaign_goal', 'introduce_artist', $content)); ?>#elev8-campaign-builder"><span class="dashicons dashicons-admin-users"></span><strong><?php esc_html_e('My artist profile', 'elev8-os'); ?></strong><small><?php esc_html_e('Introduce yourself and give people a reason to follow.', 'elev8-os'); ?></small></a>
                    </div>
                </section>

                <section class="elev8-growth-tools" aria-labelledby="elev8-growth-tools-title">
                    <div class="elev8-growth-section-heading">
                        <p><?php esc_html_e('Everything in one place', 'elev8-os'); ?></p>
                        <h2 id="elev8-growth-tools-title"><?php esc_html_e('Choose how you want to grow', 'elev8-os'); ?></h2>
                    </div>
                    <div class="elev8-growth-tool-grid">
                        <article class="elev8-growth-tool is-purple">
                            <span class="elev8-growth-tool-icon dashicons dashicons-layout"></span>
                            <div><p><?php esc_html_e('Create', 'elev8-os'); ?></p><h3><?php esc_html_e('Campaign & Content Builder', 'elev8-os'); ?></h3><p><?php esc_html_e('Write reusable messages, start from templates, and build campaigns around a clear goal.', 'elev8-os'); ?></p></div>
                            <a class="elev8-growth-button is-purple" href="<?php echo esc_url($content); ?>"><?php esc_html_e('Create Content', 'elev8-os'); ?></a>
                        </article>
                        <article class="elev8-growth-tool is-teal">
                            <span class="elev8-growth-tool-icon dashicons dashicons-email-alt"></span>
                            <div><p><?php esc_html_e('Reach people', 'elev8-os'); ?></p><h3><?php esc_html_e('Email Marketing', 'elev8-os'); ?></h3><p><?php esc_html_e('Reconnect with verified students, choose an audience, and send a safe campaign.', 'elev8-os'); ?></p></div>
                            <a class="elev8-growth-button is-teal" href="<?php echo esc_url($marketing); ?>"><?php esc_html_e('Send a Campaign', 'elev8-os'); ?></a>
                        </article>
                        <article class="elev8-growth-tool is-lavender">
                            <span class="elev8-growth-tool-icon dashicons dashicons-media-document"></span>
                            <div><p><?php esc_html_e('Print & QR', 'elev8-os'); ?></p><h3><?php esc_html_e('Artist Identity & Displays', 'elev8-os'); ?></h3><p><?php esc_html_e('Print artist cards, artwork labels, QR displays, and professional materials for your table or gallery wall.', 'elev8-os'); ?></p></div>
                            <a class="elev8-growth-button is-outline" href="<?php echo esc_url($print); ?>"><?php esc_html_e('Open Print Center', 'elev8-os'); ?></a>
                        </article>
                    </div>
                </section>

                <section class="elev8-growth-quick-links">
                    <a href="<?php echo esc_url($artwork); ?>"><span class="dashicons dashicons-format-image"></span><strong><?php esc_html_e('Manage Artwork', 'elev8-os'); ?></strong><small><?php esc_html_e('Add the pieces you want to promote or print.', 'elev8-os'); ?></small></a>
                    <a href="<?php echo esc_url($website); ?>"><span class="dashicons dashicons-admin-site-alt3"></span><strong><?php esc_html_e('View My Website', 'elev8-os'); ?></strong><small><?php esc_html_e('See the public experience collectors receive.', 'elev8-os'); ?></small></a>
                    <a href="<?php echo esc_url($events); ?>" target="_blank" rel="noopener noreferrer"><span class="dashicons dashicons-calendar-alt"></span><strong><?php esc_html_e('Explore Events', 'elev8-os'); ?></strong><small><?php esc_html_e('Find something worth sharing with your audience.', 'elev8-os'); ?></small></a>
                </section>
            </main>
        </div>
        <?php return (string) ob_get_clean();
    }
}

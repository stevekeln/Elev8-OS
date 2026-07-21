<?php
if (!defined('ABSPATH')) { exit; }

/** Shared Employee Guides and Knowledge Base foundation. */
final class Elev8_OS_Knowledge_Base_Module {
    private const POST_TYPE = 'elev8_guide';

    public static function init(): void {
        add_action('init', [__CLASS__, 'register']);
        add_shortcode('elev8_knowledge_base', [__CLASS__, 'shortcode']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'assets']);
        add_filter('elev8_os_command_palette_commands', [__CLASS__, 'command'], 20, 2);
    }

    public static function register(): void {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name' => __('Employee Guides', 'elev8-os'),
                'singular_name' => __('Employee Guide', 'elev8-os'),
                'add_new_item' => __('Add Employee Guide', 'elev8-os'),
                'edit_item' => __('Edit Employee Guide', 'elev8-os'),
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'elev8-os',
            'supports' => ['title', 'editor', 'excerpt', 'thumbnail'],
            'capability_type' => 'post',
            'map_meta_cap' => true,
            'menu_icon' => 'dashicons-welcome-learn-more',
        ]);
    }

    public static function url(): string {
        return class_exists('Elev8_OS_Portal_Page_Manager')
            ? Elev8_OS_Portal_Page_Manager::get_url('resources')
            : home_url('/elev8-resources/');
    }

    public static function assets(): void {
        if (!is_user_logged_in()) { return; }
        if ((class_exists('Elev8_OS_Portal_Page_Manager') && Elev8_OS_Portal_Page_Manager::is_current_page('resources')) || has_shortcode((string) get_post_field('post_content', get_queried_object_id()), 'elev8_knowledge_base')) {
            wp_enqueue_style('elev8-os-knowledge-base', ELEV8_OS_URL . 'assets/css/knowledge-base.css', [], ELEV8_OS_VERSION);
        }
    }

    public static function shortcode(): string {
        if (!is_user_logged_in()) {
            return '<p>' . esc_html__('Please sign in to open Employee Guides.', 'elev8-os') . '</p>';
        }
        $guide_url = ELEV8_OS_URL . 'assets/docs/elev8-os-staff-quick-start.pdf';
        $external = 'https://www.elev8glass.com/';
        $posts = get_posts(['post_type' => self::POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => 50, 'orderby' => ['menu_order' => 'ASC', 'title' => 'ASC']]);
        ob_start(); ?>
        <main class="elev8-kb">
            <header class="elev8-kb__hero">
                <p class="elev8-kb__eyebrow"><?php esc_html_e('Elev8 OS Resources', 'elev8-os'); ?></p>
                <h1><?php esc_html_e('Employee Guides & Knowledge Base', 'elev8-os'); ?></h1>
                <p><?php esc_html_e('Find quick-start instructions, procedures, training, and company knowledge in one place.', 'elev8-os'); ?></p>
            </header>
            <section class="elev8-kb__grid">
                <article class="elev8-kb__card is-featured">
                    <span aria-hidden="true">🚀</span>
                    <h2><?php esc_html_e('Welcome to Elev8 OS', 'elev8-os'); ?></h2>
                    <p><?php esc_html_e('A one-page staff quick-start guide with a clickable access link, QR code, mobile navigation, messages, and support steps.', 'elev8-os'); ?></p>
                    <a href="<?php echo esc_url($guide_url); ?>" target="_blank" rel="noopener"><?php esc_html_e('Open Quick-Start PDF', 'elev8-os'); ?></a>
                </article>
                <article class="elev8-kb__card">
                    <span aria-hidden="true">📚</span>
                    <h2><?php esc_html_e('Existing Elev8 Knowledge Base', 'elev8-os'); ?></h2>
                    <p><?php esc_html_e('Use the current Elev8Glass.com knowledge base while guides and procedures are moved into Elev8 OS.', 'elev8-os'); ?></p>
                    <a href="<?php echo esc_url($external); ?>" target="_blank" rel="noopener"><?php esc_html_e('Open Elev8Glass.com', 'elev8-os'); ?></a>
                </article>
                <?php foreach ($posts as $post) : ?>
                    <article class="elev8-kb__card">
                        <span aria-hidden="true">📄</span>
                        <h2><?php echo esc_html(get_the_title($post)); ?></h2>
                        <p><?php echo esc_html(has_excerpt($post) ? get_the_excerpt($post) : wp_trim_words(wp_strip_all_tags($post->post_content), 28)); ?></p>
                        <details><summary><?php esc_html_e('Read guide', 'elev8-os'); ?></summary><div><?php echo wp_kses_post(apply_filters('the_content', $post->post_content)); ?></div></details>
                    </article>
                <?php endforeach; ?>
            </section>
        </main>
        <?php return (string) ob_get_clean();
    }

    public static function command(array $commands, WP_User $user): array {
        $commands[] = [
            'id' => 'employee_guides',
            'label' => __('Employee Guides', 'elev8-os'),
            'description' => __('Open quick-start guides, procedures, training, and company knowledge.', 'elev8-os'),
            'url' => self::url(),
            'group' => 'resources',
            'icon' => '📚',
            'type' => 'command',
        ];
        return $commands;
    }
}

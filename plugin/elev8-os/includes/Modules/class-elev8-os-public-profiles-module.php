<?php
/**
 * Frontend public-profile editor and public profile renderer.
 *
 * @package Elev8OS
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Elev8_OS_Public_Profiles_Module {

    private const PAGE_OPTION = 'elev8_os_public_profile_editor_page_id';
    private const REWRITE_VERSION_OPTION = 'elev8_os_public_profile_rewrite_version';

    public static function init(): void {
        add_action('init', [__CLASS__, 'register_rewrite']);
        add_action('init', [__CLASS__, 'ensure_editor_page'], 30);
        add_action('template_redirect', [__CLASS__, 'render_public_profile']);
        add_shortcode('elev8_public_profile_editor', [__CLASS__, 'editor_shortcode']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_filter('query_vars', [__CLASS__, 'query_vars']);
        add_filter('elev8_os_application_shell_frontend', [__CLASS__, 'shell_support']);
    }

    public static function register_rewrite(): void {
        add_rewrite_rule('^people/([^/]+)/?$', 'index.php?elev8_public_profile=$matches[1]', 'top');
        $version = (string) get_option(self::REWRITE_VERSION_OPTION, '');
        if ($version !== ELEV8_OS_VERSION) {
            flush_rewrite_rules(false);
            update_option(self::REWRITE_VERSION_OPTION, ELEV8_OS_VERSION, false);
        }
    }

    /** @param array<int,string> $vars */
    public static function query_vars(array $vars): array {
        $vars[] = 'elev8_public_profile';
        return $vars;
    }

    public static function shell_support(bool $supported): bool {
        if ($supported) return true;
        $path = trim((string) wp_parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
        return $path === 'elev8-profile';
    }

    public static function ensure_editor_page(): void {
        $page_id = (int) get_option(self::PAGE_OPTION, 0);
        if ($page_id > 0 && get_post_status($page_id)) return;

        $existing = get_page_by_path('elev8-profile');
        if ($existing instanceof WP_Post) {
            update_option(self::PAGE_OPTION, (int) $existing->ID, false);
            return;
        }
        if (!is_user_logged_in()) return;

        $page_id = wp_insert_post([
            'post_title' => __('My Public Profile', 'elev8-os'),
            'post_name' => 'elev8-profile',
            'post_content' => '[elev8_public_profile_editor]',
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_author' => get_current_user_id(),
        ], true);
        if (!is_wp_error($page_id) && $page_id > 0) {
            update_option(self::PAGE_OPTION, (int) $page_id, false);
        }
    }

    public static function enqueue_assets(): void {
        $slug = get_query_var('elev8_public_profile');
        $path = trim((string) wp_parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
        if ($slug === '' && $path !== 'elev8-profile') return;
        wp_enqueue_style('elev8-os-public-profiles', ELEV8_OS_URL . 'assets/css/public-profiles.css', [], ELEV8_OS_VERSION);
    }

    public static function editor_shortcode(): string {
        if (!is_user_logged_in()) {
            return '<div class="elev8-profile-message">' . esc_html__('Please log in to manage your public profile.', 'elev8-os') . '</div>';
        }

        $user_id = get_current_user_id();
        $notice = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['elev8_public_profile_save'])) {
            check_admin_referer('elev8_public_profile_save', 'elev8_public_profile_nonce');
            $result = Elev8_OS_Public_Profile_Service::save($user_id, wp_unslash($_POST));
            $notice = sprintf('<div class="elev8-profile-message %s">%s</div>', !empty($result['success']) ? 'is-success' : 'is-error', esc_html((string) ($result['message'] ?? '')));
        }

        $profile = Elev8_OS_Public_Profile_Service::get($user_id);
        $public_url = Elev8_OS_Public_Profile_Service::public_url($user_id);
        ob_start();
        ?>
        <main class="elev8-profile-editor">
            <header class="elev8-profile-editor__header">
                <div><p class="elev8-profile-eyebrow"><?php esc_html_e('Public Identity', 'elev8-os'); ?></p><h1><?php esc_html_e('My Public Profile', 'elev8-os'); ?></h1><p><?php esc_html_e('Control what customers and event guests can see about you.', 'elev8-os'); ?></p></div>
                <span class="elev8-profile-status <?php echo !empty($profile['published']) ? 'is-published' : 'is-draft'; ?>"><?php echo !empty($profile['published']) ? esc_html__('Published', 'elev8-os') : esc_html__('Draft', 'elev8-os'); ?></span>
            </header>
            <?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <form method="post" class="elev8-profile-form">
                <?php wp_nonce_field('elev8_public_profile_save', 'elev8_public_profile_nonce'); ?>
                <section class="elev8-profile-card">
                    <h2><?php esc_html_e('Public introduction', 'elev8-os'); ?></h2>
                    <div class="elev8-profile-grid">
                        <label><span><?php esc_html_e('Public display name', 'elev8-os'); ?></span><input type="text" name="display_name" value="<?php echo esc_attr((string) ($profile['display_name'] ?? '')); ?>" required></label>
                        <label><span><?php esc_html_e('Public page address', 'elev8-os'); ?></span><div class="elev8-profile-slug"><small><?php echo esc_html(home_url('/people/')); ?></small><input type="text" name="slug" value="<?php echo esc_attr((string) ($profile['slug'] ?? '')); ?>"></div></label>
                        <label class="is-wide"><span><?php esc_html_e('Headline', 'elev8-os'); ?></span><input type="text" name="headline" value="<?php echo esc_attr((string) ($profile['headline'] ?? '')); ?>" placeholder="<?php esc_attr_e('Open Mic host, community builder, and event MC', 'elev8-os'); ?>"></label>
                        <label class="is-wide"><span><?php esc_html_e('Biography', 'elev8-os'); ?></span><textarea name="bio" rows="7" placeholder="<?php esc_attr_e('Tell guests who you are and what you bring to the experience.', 'elev8-os'); ?>"><?php echo esc_textarea((string) ($profile['bio'] ?? '')); ?></textarea></label>
                        <label class="is-wide"><span><?php esc_html_e('Profile photo URL', 'elev8-os'); ?></span><input type="url" name="photo_url" value="<?php echo esc_attr((string) ($profile['photo_url'] ?? '')); ?>" placeholder="https://..."></label>
                    </div>
                </section>
                <section class="elev8-profile-card">
                    <h2><?php esc_html_e('Links and contact', 'elev8-os'); ?></h2>
                    <div class="elev8-profile-grid">
                        <label><span><?php esc_html_e('Website', 'elev8-os'); ?></span><input type="url" name="website_url" value="<?php echo esc_attr((string) ($profile['website_url'] ?? '')); ?>"></label>
                        <label><span><?php esc_html_e('Public email', 'elev8-os'); ?></span><input type="email" name="contact_email" value="<?php echo esc_attr((string) ($profile['contact_email'] ?? '')); ?>"></label>
                        <label><span><?php esc_html_e('Instagram', 'elev8-os'); ?></span><input type="url" name="instagram_url" value="<?php echo esc_attr((string) ($profile['instagram_url'] ?? '')); ?>"></label>
                        <label><span><?php esc_html_e('Facebook', 'elev8-os'); ?></span><input type="url" name="facebook_url" value="<?php echo esc_attr((string) ($profile['facebook_url'] ?? '')); ?>"></label>
                    </div>
                </section>
                <section class="elev8-profile-publish">
                    <label><input type="checkbox" name="publish" value="1" <?php checked(!empty($profile['published'])); ?>> <span><?php esc_html_e('Publish this profile for customers and guests', 'elev8-os'); ?></span></label>
                    <div>
                        <?php if (!empty($profile['published']) && $public_url !== '') : ?><a class="elev8-profile-secondary" href="<?php echo esc_url($public_url); ?>" target="_blank" rel="noopener"><?php esc_html_e('Preview Public Profile', 'elev8-os'); ?></a><?php endif; ?>
                        <button class="elev8-profile-primary" type="submit" name="elev8_public_profile_save" value="1"><?php esc_html_e('Save Profile', 'elev8-os'); ?></button>
                    </div>
                </section>
            </form>
        </main>
        <?php
        return (string) ob_get_clean();
    }

    public static function render_public_profile(): void {
        $slug = sanitize_title((string) get_query_var('elev8_public_profile'));
        if ($slug === '') return;

        $user_id = Elev8_OS_Public_Profile_Service::user_id_from_slug($slug);
        if ($user_id <= 0 || !Elev8_OS_Public_Profile_Service::is_published($user_id)) {
            status_header(404);
            nocache_headers();
            include get_404_template();
            exit;
        }

        $profile = Elev8_OS_Public_Profile_Service::get($user_id);
        status_header(200);
        nocache_headers();
        ?><!doctype html><html <?php language_attributes(); ?>><head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?php echo esc_html((string) $profile['display_name']); ?> | Elev8 Arts</title><?php wp_head(); ?></head><body <?php body_class('elev8-public-profile-page'); ?>><?php wp_body_open(); ?>
        <main class="elev8-public-person">
            <a class="elev8-public-person__home" href="<?php echo esc_url(home_url('/')); ?>">← <?php esc_html_e('Elev8 Arts', 'elev8-os'); ?></a>
            <article class="elev8-public-person__card">
                <?php if (!empty($profile['photo_url'])) : ?><img class="elev8-public-person__photo" src="<?php echo esc_url((string) $profile['photo_url']); ?>" alt="<?php echo esc_attr((string) $profile['display_name']); ?>"><?php else : ?><div class="elev8-public-person__photo is-placeholder" aria-hidden="true"><?php echo esc_html(strtoupper(substr((string) $profile['display_name'], 0, 1))); ?></div><?php endif; ?>
                <p class="elev8-profile-eyebrow"><?php echo esc_html((string) $profile['role_label']); ?></p>
                <h1><?php echo esc_html((string) $profile['display_name']); ?></h1>
                <?php if (!empty($profile['headline'])) : ?><p class="elev8-public-person__headline"><?php echo esc_html((string) $profile['headline']); ?></p><?php endif; ?>
                <div class="elev8-public-person__bio"><?php echo wpautop(esc_html((string) $profile['bio'])); ?></div>
                <div class="elev8-public-person__links">
                    <?php if (!empty($profile['contact_email'])) : ?><a href="mailto:<?php echo esc_attr((string) $profile['contact_email']); ?>"><?php esc_html_e('Email', 'elev8-os'); ?></a><?php endif; ?>
                    <?php foreach (['website_url' => __('Website', 'elev8-os'), 'instagram_url' => __('Instagram', 'elev8-os'), 'facebook_url' => __('Facebook', 'elev8-os')] as $key => $label) : if (empty($profile[$key])) continue; ?><a href="<?php echo esc_url((string) $profile[$key]); ?>" target="_blank" rel="noopener"><?php echo esc_html($label); ?></a><?php endforeach; ?>
                </div>
            </article>
        </main><?php wp_footer(); ?></body></html><?php
        exit;
    }
}

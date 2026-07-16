<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once ELEV8_OS_DIR . 'includes/Services/class-elev8-os-asset-service.php';

/**
 * Artist-facing Asset Engine workspace.
 */
final class Elev8_OS_My_Artwork_Module {

    private const SHORTCODE = 'elev8_artist_artwork';

    public static function init(): void {
        Elev8_OS_Asset_Service::init();
        add_action('init', [__CLASS__, 'register_shortcode']);
        add_action('admin_post_elev8_os_save_artwork', [__CLASS__, 'handle_save']);
        add_action('admin_post_elev8_os_delete_artwork', [__CLASS__, 'handle_delete']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    public static function activate(): void {
        Elev8_OS_Asset_Service::activate();
    }

    public static function register_shortcode(): void {
        add_shortcode(self::SHORTCODE, [__CLASS__, 'shortcode']);
    }

    public static function enqueue_assets(): void {
        if (!is_user_logged_in() || !Elev8_OS_Portal_Page_Manager::is_current_page('artwork')) {
            return;
        }

        wp_enqueue_style(
            'elev8-os-my-artwork',
            ELEV8_OS_URL . 'assets/css/artist-artwork.css',
            ['elev8-os-artist-portal'],
            ELEV8_OS_VERSION
        );
    }

    public static function shortcode(): string {
        if (!is_user_logged_in()) {
            return '<div class="elev8-dashboard-login"><p>' . esc_html__('Please log in to manage your artwork.', 'elev8-os') . '</p></div>';
        }

        $user_id = get_current_user_id();
        $edit_id = isset($_GET['edit_artwork']) ? absint($_GET['edit_artwork']) : 0;
        $editing = $edit_id > 0 ? Elev8_OS_Asset_Service::get($edit_id) : null;
        if ($editing && (int) $editing['owner_user_id'] !== $user_id) {
            $editing = null;
        }

        $assets = Elev8_OS_Asset_Service::get_for_owner($user_id);
        $counts = ['all' => count($assets), 'available' => 0, 'sold' => 0, 'draft' => 0];
        foreach ($assets as $asset) {
            $key = (string) $asset['status'];
            if (isset($counts[$key])) {
                $counts[$key]++;
            }
        }

        ob_start();
        ?>
        <div class="elev8-artwork-workspace">
            <?php Elev8_OS_Artist_Portal_Module::render_navigation('artwork'); ?>

            <header class="elev8-artwork-header">
                <div>
                    <p class="elev8-eyebrow"><?php esc_html_e('Asset Engine', 'elev8-os'); ?></p>
                    <h1><?php esc_html_e('My Artwork', 'elev8-os'); ?></h1>
                    <p><?php esc_html_e('Create one trusted record for each piece. These assets can later power your website, inventory, sales, and reporting.', 'elev8-os'); ?></p>
                </div>
                <a class="elev8-primary-button" href="#elev8-artwork-editor"><?php esc_html_e('Add Artwork', 'elev8-os'); ?></a>
            </header>

            <?php self::render_notice(); ?>

            <section class="elev8-artwork-summary" aria-label="<?php esc_attr_e('Artwork summary', 'elev8-os'); ?>">
                <?php self::summary_card(__('Total pieces', 'elev8-os'), $counts['all']); ?>
                <?php self::summary_card(__('Available', 'elev8-os'), $counts['available']); ?>
                <?php self::summary_card(__('Sold', 'elev8-os'), $counts['sold']); ?>
                <?php self::summary_card(__('Drafts', 'elev8-os'), $counts['draft']); ?>
            </section>

            <section id="elev8-artwork-editor" class="elev8-artwork-editor">
                <div class="elev8-section-heading">
                    <div>
                        <p class="elev8-eyebrow"><?php echo $editing ? esc_html__('Edit asset', 'elev8-os') : esc_html__('New asset', 'elev8-os'); ?></p>
                        <h2><?php echo $editing ? esc_html__('Update Artwork', 'elev8-os') : esc_html__('Add Artwork', 'elev8-os'); ?></h2>
                    </div>
                    <?php if ($editing) : ?><a href="<?php echo esc_url(Elev8_OS_Portal_Page_Manager::get_url('artwork')); ?>"><?php esc_html_e('Cancel edit', 'elev8-os'); ?></a><?php endif; ?>
                </div>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="elev8_os_save_artwork">
                    <input type="hidden" name="asset_id" value="<?php echo esc_attr((string) ($editing['id'] ?? 0)); ?>">
                    <input type="hidden" name="existing_attachment_id" value="<?php echo esc_attr((string) ($editing['image_attachment_id'] ?? 0)); ?>">
                    <?php wp_nonce_field('elev8_os_save_artwork'); ?>

                    <div class="elev8-artwork-form-grid">
                        <label class="elev8-field"><span><?php esc_html_e('Artwork title', 'elev8-os'); ?> *</span><input required name="title" value="<?php echo esc_attr((string) ($editing['title'] ?? '')); ?>"></label>
                        <label class="elev8-field"><span><?php esc_html_e('Status', 'elev8-os'); ?></span><select name="status"><?php foreach (Elev8_OS_Asset_Service::statuses() as $status) : ?><option value="<?php echo esc_attr($status); ?>" <?php selected((string) ($editing['status'] ?? 'draft'), $status); ?>><?php echo esc_html(ucfirst($status)); ?></option><?php endforeach; ?></select></label>
                        <label class="elev8-field"><span><?php esc_html_e('Medium', 'elev8-os'); ?></span><input name="medium" placeholder="Oil, glass, watercolor…" value="<?php echo esc_attr((string) ($editing['medium'] ?? '')); ?>"></label>
                        <label class="elev8-field"><span><?php esc_html_e('Dimensions', 'elev8-os'); ?></span><input name="dimensions" placeholder="12 × 18 in" value="<?php echo esc_attr((string) ($editing['dimensions'] ?? '')); ?>"></label>
                        <label class="elev8-field"><span><?php esc_html_e('Price', 'elev8-os'); ?></span><input type="number" min="0" step="0.01" name="price" placeholder="Leave blank when unavailable" value="<?php echo esc_attr(isset($editing['price']) && $editing['price'] !== null ? (string) $editing['price'] : ''); ?>"></label>
                        <label class="elev8-field"><span><?php esc_html_e('Artwork image', 'elev8-os'); ?></span><input type="file" name="artwork_image" accept="image/*"><small><?php esc_html_e('Uploading a new image replaces the current image for this record.', 'elev8-os'); ?></small></label>
                    </div>
                    <label class="elev8-field elev8-field-full"><span><?php esc_html_e('Description', 'elev8-os'); ?></span><textarea name="description" rows="5"><?php echo esc_textarea((string) ($editing['description'] ?? '')); ?></textarea></label>
                    <div class="elev8-artwork-form-actions"><button class="elev8-primary-button" type="submit"><?php echo $editing ? esc_html__('Save Changes', 'elev8-os') : esc_html__('Add Artwork', 'elev8-os'); ?></button></div>
                </form>
            </section>

            <section class="elev8-artwork-library">
                <div class="elev8-section-heading"><div><p class="elev8-eyebrow"><?php esc_html_e('Asset library', 'elev8-os'); ?></p><h2><?php esc_html_e('Your Artwork', 'elev8-os'); ?></h2></div></div>
                <?php if (!$assets) : ?>
                    <div class="elev8-artwork-empty"><span class="dashicons dashicons-format-image"></span><h3><?php esc_html_e('No artwork added yet', 'elev8-os'); ?></h3><p><?php esc_html_e('Add your first piece above to begin building your Asset Engine.', 'elev8-os'); ?></p></div>
                <?php else : ?>
                    <div class="elev8-artwork-grid">
                        <?php foreach ($assets as $asset) : self::render_asset_card($asset); endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    public static function handle_save(): void {
        if (!is_user_logged_in()) {
            wp_die(esc_html__('You must be logged in.', 'elev8-os'));
        }
        check_admin_referer('elev8_os_save_artwork');

        $user_id = get_current_user_id();
        $asset_id = absint($_POST['asset_id'] ?? 0);
        $existing_attachment_id = absint($_POST['existing_attachment_id'] ?? 0);

        if ($asset_id > 0) {
            $existing = Elev8_OS_Asset_Service::get($asset_id);
            if (!$existing || (int) $existing['owner_user_id'] !== $user_id) {
                wp_die(esc_html__('You cannot edit this artwork.', 'elev8-os'));
            }
            $existing_attachment_id = (int) $existing['image_attachment_id'];
        }

        $attachment_id = $existing_attachment_id;
        if (!empty($_FILES['artwork_image']['name'])) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
            $uploaded = media_handle_upload('artwork_image', 0);
            if (is_wp_error($uploaded)) {
                self::redirect('error', $uploaded->get_error_message());
            }
            $attachment_id = (int) $uploaded;
        }

        $result = Elev8_OS_Asset_Service::save([
            'id' => $asset_id,
            'owner_user_id' => $user_id,
            'title' => wp_unslash($_POST['title'] ?? ''),
            'description' => wp_unslash($_POST['description'] ?? ''),
            'medium' => wp_unslash($_POST['medium'] ?? ''),
            'dimensions' => wp_unslash($_POST['dimensions'] ?? ''),
            'price' => wp_unslash($_POST['price'] ?? ''),
            'status' => wp_unslash($_POST['status'] ?? 'draft'),
            'image_attachment_id' => $attachment_id,
        ]);

        if (is_wp_error($result)) {
            self::redirect('error', $result->get_error_message());
        }
        self::redirect('saved', $asset_id > 0 ? __('Artwork updated.', 'elev8-os') : __('Artwork added.', 'elev8-os'));
    }

    public static function handle_delete(): void {
        if (!is_user_logged_in()) {
            wp_die(esc_html__('You must be logged in.', 'elev8-os'));
        }
        $asset_id = absint($_POST['asset_id'] ?? 0);
        check_admin_referer('elev8_os_delete_artwork_' . $asset_id);
        $deleted = Elev8_OS_Asset_Service::delete($asset_id, get_current_user_id());
        self::redirect($deleted ? 'deleted' : 'error', $deleted ? __('Artwork removed.', 'elev8-os') : __('Artwork could not be removed.', 'elev8-os'));
    }

    /** @param array<string,mixed> $asset */
    private static function render_asset_card(array $asset): void {
        $image = wp_get_attachment_image_url((int) $asset['image_attachment_id'], 'medium_large');
        $edit_url = add_query_arg('edit_artwork', (int) $asset['id'], Elev8_OS_Portal_Page_Manager::get_url('artwork'));
        ?>
        <article class="elev8-artwork-card">
            <div class="elev8-artwork-image"><?php if ($image) : ?><img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr((string) $asset['title']); ?>"><?php else : ?><span class="dashicons dashicons-format-image"></span><small><?php esc_html_e('Image unavailable', 'elev8-os'); ?></small><?php endif; ?></div>
            <div class="elev8-artwork-card-body">
                <div class="elev8-artwork-card-top"><span class="elev8-artwork-status is-<?php echo esc_attr((string) $asset['status']); ?>"><?php echo esc_html(ucfirst((string) $asset['status'])); ?></span><span class="elev8-artwork-price"><?php echo $asset['price'] === null ? esc_html__('Price unavailable', 'elev8-os') : esc_html('$' . number_format_i18n((float) $asset['price'], 2)); ?></span></div>
                <h3><?php echo esc_html((string) $asset['title']); ?></h3>
                <?php if ((string) $asset['medium'] !== '' || (string) $asset['dimensions'] !== '') : ?><p class="elev8-artwork-meta"><?php echo esc_html(implode(' · ', array_filter([(string) $asset['medium'], (string) $asset['dimensions']]))); ?></p><?php endif; ?>
                <?php if ((string) $asset['description'] !== '') : ?><p><?php echo esc_html(wp_trim_words((string) $asset['description'], 24)); ?></p><?php endif; ?>
                <div class="elev8-artwork-actions"><a class="elev8-secondary-button" href="<?php echo esc_url($edit_url); ?>#elev8-artwork-editor"><?php esc_html_e('Edit', 'elev8-os'); ?></a><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js(__('Remove this artwork record?', 'elev8-os')); ?>');"><input type="hidden" name="action" value="elev8_os_delete_artwork"><input type="hidden" name="asset_id" value="<?php echo esc_attr((string) $asset['id']); ?>"><?php wp_nonce_field('elev8_os_delete_artwork_' . (int) $asset['id']); ?><button type="submit" class="elev8-text-danger"><?php esc_html_e('Remove', 'elev8-os'); ?></button></form></div>
            </div>
        </article>
        <?php
    }

    private static function summary_card(string $label, int $value): void {
        ?><article><span><?php echo esc_html($label); ?></span><strong><?php echo esc_html(number_format_i18n($value)); ?></strong></article><?php
    }

    private static function render_notice(): void {
        $state = sanitize_key((string) ($_GET['artwork_notice'] ?? ''));
        $message = sanitize_text_field(wp_unslash((string) ($_GET['artwork_message'] ?? '')));
        if ($state === '' || $message === '') {
            return;
        }
        ?><div class="elev8-artwork-notice is-<?php echo esc_attr($state); ?>"><p><?php echo esc_html($message); ?></p></div><?php
    }

    private static function redirect(string $state, string $message): void {
        wp_safe_redirect(add_query_arg([
            'artwork_notice' => $state,
            'artwork_message' => $message,
        ], Elev8_OS_Portal_Page_Manager::get_url('artwork')));
        exit;
    }
}

<?php
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Artist_Print_Center_Module {
    private const SHORTCODE = 'elev8_artist_print_center';

    public static function init(): void {
        add_shortcode(self::SHORTCODE, [__CLASS__, 'shortcode']);
        add_action('admin_post_elev8_os_artist_print_profile', [__CLASS__, 'print_profile']);
        add_action('admin_post_elev8_os_artist_print_artwork', [__CLASS__, 'print_artwork']);
    }

    public static function shortcode(): string {
        if (!is_user_logged_in()) {
            return '<div class="elev8-portal-notice"><p>' . esc_html__('Please sign in to open your Print Center.', 'elev8-os') . '</p></div>';
        }

        $artist = Elev8_OS_Identity_Service::current_artist();
        $artist_id = is_array($artist) ? absint($artist['id'] ?? 0) : 0;
        if ($artist_id <= 0) {
            return '<div class="elev8-portal-notice"><p>' . esc_html__('Your account is not connected to an approved artist profile.', 'elev8-os') . '</p></div>';
        }

        $user_id = get_current_user_id();
        $assets = array_values(array_filter(Elev8_OS_Asset_Service::get_all(), static function(array $asset) use ($user_id): bool {
            return absint($asset['owner_user_id'] ?? 0) === $user_id;
        }));
        usort($assets, static fn(array $a, array $b): int => strcasecmp((string)($a['title'] ?? ''), (string)($b['title'] ?? '')));

        ob_start();
        ?>
        <div class="elev8-artist-dashboard elev8-dashboard-v2 elev8-artist-print-center">
            <?php Elev8_OS_Artist_Portal_Module::render_navigation('print_center'); ?>
            <header class="elev8-dashboard-header elev8-dashboard-hero">
                <div><p class="elev8-eyebrow"><?php esc_html_e('Artist Portal', 'elev8-os'); ?></p><h1><?php esc_html_e('My Print Center', 'elev8-os'); ?></h1><p><?php esc_html_e('Preview and print your approved Elev8 Arts profile card, QR code, and artwork labels.', 'elev8-os'); ?></p></div>
                <span class="elev8-dashboard-badge"><?php esc_html_e('Elev8 Print Standard', 'elev8-os'); ?></span>
            </header>

            <div class="elev8-print-center-grid">
                <section class="elev8-dashboard-panel">
                    <div class="elev8-panel-heading"><div><p class="elev8-eyebrow"><?php esc_html_e('Profile', 'elev8-os'); ?></p><h2><?php esc_html_e('Artist Materials', 'elev8-os'); ?></h2></div></div>
                    <p><?php esc_html_e('Print your artist introduction card or a standalone QR code. These always use the approved Elev8 Arts template.', 'elev8-os'); ?></p>
                    <form method="get" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" target="_blank">
                        <input type="hidden" name="action" value="elev8_os_artist_print_profile">
                        <?php wp_nonce_field('elev8_os_artist_print_profile', '_wpnonce', false); ?>
                        <label><strong><?php esc_html_e('Print format', 'elev8-os'); ?></strong><select name="print_format"><option value="artist-card"><?php esc_html_e('Artist display card — 8.5 × 5.5', 'elev8-os'); ?></option><option value="artist-card-two"><?php esc_html_e('Two cards — letter sheet', 'elev8-os'); ?></option><option value="artist-qr"><?php esc_html_e('Artist QR code only', 'elev8-os'); ?></option></select></label>
                        <button class="elev8-button elev8-button-primary" type="submit"><?php esc_html_e('Preview Artist Print', 'elev8-os'); ?></button>
                    </form>
                </section>

                <section class="elev8-dashboard-panel">
                    <div class="elev8-panel-heading"><div><p class="elev8-eyebrow"><?php esc_html_e('Artwork', 'elev8-os'); ?></p><h2><?php esc_html_e('My Artwork Labels', 'elev8-os'); ?></h2></div></div>
                    <p><?php esc_html_e('Choose one of your artwork records and print its 3 × 3 QR label.', 'elev8-os'); ?></p>
                    <?php if (!$assets): ?>
                        <div class="elev8-dashboard-empty"><span class="dashicons dashicons-art"></span><h3><?php esc_html_e('No artwork ready to print', 'elev8-os'); ?></h3><p><?php esc_html_e('Add artwork first, then return here to print its gallery label.', 'elev8-os'); ?></p></div>
                    <?php else: ?>
                        <form method="get" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" target="_blank">
                            <input type="hidden" name="action" value="elev8_os_artist_print_artwork">
                            <?php wp_nonce_field('elev8_os_artist_print_artwork', '_wpnonce', false); ?>
                            <label><strong><?php esc_html_e('Choose artwork', 'elev8-os'); ?></strong><select name="asset_id" required><option value=""><?php esc_html_e('Select artwork…', 'elev8-os'); ?></option><?php foreach ($assets as $asset): ?><option value="<?php echo esc_attr((string)absint($asset['id'] ?? 0)); ?>"><?php echo esc_html((string)($asset['title'] ?? __('Untitled', 'elev8-os')) . ' — ' . ucfirst((string)($asset['status'] ?? 'available'))); ?></option><?php endforeach; ?></select></label>
                            <label><strong><?php esc_html_e('Print format', 'elev8-os'); ?></strong><select name="print_format"><option value="artwork-label"><?php esc_html_e('Artwork QR label — 3 × 3', 'elev8-os'); ?></option><option value="artwork-label-two"><?php esc_html_e('Two labels — letter sheet', 'elev8-os'); ?></option></select></label>
                            <button class="elev8-button elev8-button-primary" type="submit"><?php esc_html_e('Preview Artwork Print', 'elev8-os'); ?></button>
                        </form>
                    <?php endif; ?>
                </section>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    public static function print_profile(): void {
        self::require_artist();
        check_admin_referer('elev8_os_artist_print_profile');
        $artist = Elev8_OS_Identity_Service::current_artist();
        $artist_id = absint($artist['id'] ?? 0);
        $profiles = get_option(Elev8_OS::OPTION_PROFILES, []);
        $profiles = is_array($profiles) ? $profiles : [];
        $profile = is_array($profiles[$artist_id] ?? null) ? $profiles[$artist_id] : [];
        $bio = trim((string)($profile['bio'] ?? $profile['short_description'] ?? $profile['description'] ?? ''));
        if ($bio === '') { $bio = trim((string)wp_get_current_user()->description); }
        $name = trim((string)($artist['firstName'] ?? '') . ' ' . (string)($artist['lastName'] ?? ''));
        if ($name === '') { $name = wp_get_current_user()->display_name; }
        $format = sanitize_key((string)($_GET['print_format'] ?? 'artist-card'));
        $profile_url = home_url('/artists/' . sanitize_title($name) . '/');
        Elev8_OS_Print_Service::render([
            'name' => $name,
            'bio' => $bio,
            'medium' => (string)($profile['medium'] ?? ''),
            'photo' => (string)($profile['profile_photo'] ?? ''),
            'profile_url' => $profile_url,
            'canonical_url' => Elev8_OS_Portal_Page_Manager::get_url('print_center'),
        ], $format === 'artist-qr' ? 'qr' : 'artist-card', $format === 'artist-card-two');
    }

    public static function print_artwork(): void {
        self::require_artist();
        check_admin_referer('elev8_os_artist_print_artwork');
        $asset = Elev8_OS_Asset_Service::get(absint($_GET['asset_id'] ?? 0));
        if (!$asset || absint($asset['owner_user_id'] ?? 0) !== get_current_user_id()) {
            wp_die(esc_html__('You may only print labels for your own artwork.', 'elev8-os'), 403);
        }
        $artist = Elev8_OS_Identity_Service::current_artist();
        $name = trim((string)($artist['firstName'] ?? '') . ' ' . (string)($artist['lastName'] ?? ''));
        if ($name === '') { $name = wp_get_current_user()->display_name; }
        $format = sanitize_key((string)($_GET['print_format'] ?? 'artwork-label'));
        Elev8_OS_Print_Service::render_artwork($asset, $name, $format, Elev8_OS_Portal_Page_Manager::get_url('print_center'));
    }

    private static function require_artist(): void {
        if (!is_user_logged_in()) { auth_redirect(); exit; }
        if (Elev8_OS_Identity_Service::current_artist_id() <= 0) {
            wp_die(esc_html__('Your account is not connected to an approved artist.', 'elev8-os'), 403);
        }
    }
}

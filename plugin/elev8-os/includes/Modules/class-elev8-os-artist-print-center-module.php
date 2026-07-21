<?php
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Artist_Print_Center_Module {
    private const SHORTCODE = 'elev8_artist_print_center';

    public static function init(): void {
        add_shortcode(self::SHORTCODE, [__CLASS__, 'shortcode']);
        add_action('admin_post_elev8_os_artist_print_profile', [__CLASS__, 'print_profile']);
        add_action('admin_post_elev8_os_artist_print_artwork', [__CLASS__, 'print_artwork']);
    }

    public static function render_growth_entry(string $context = 'marketing'): string {
        if (!is_user_logged_in()) { return ''; }

        $print_url = Elev8_OS_Portal_Page_Manager::get_url('print_center');
        $artwork_url = Elev8_OS_Portal_Page_Manager::get_url('artwork');
        $title = $context === 'content_studio'
            ? __('Turn your content into something customers can scan', 'elev8-os')
            : __('Put your art in front of people', 'elev8-os');
        $description = $context === 'content_studio'
            ? __('Create artist displays, profile QR cards, and artwork labels using the same identity customers see online.', 'elev8-os')
            : __('Print professional artist displays, QR cards, and small artwork labels for your booth, gallery wall, or Art Walk table.', 'elev8-os');

        ob_start();
        ?>
        <section class="elev8-growth-tool-card elev8-growth-tool-print">
            <div class="elev8-growth-tool-icon"><span class="dashicons dashicons-media-document" aria-hidden="true"></span></div>
            <div class="elev8-growth-tool-copy">
                <p class="elev8-eyebrow"><?php esc_html_e('Print & QR', 'elev8-os'); ?></p>
                <h2><?php echo esc_html($title); ?></h2>
                <p><?php echo esc_html($description); ?></p>
            </div>
            <div class="elev8-growth-tool-actions">
                <a class="button button-primary" href="<?php echo esc_url($print_url); ?>"><?php esc_html_e('Open My Print Center', 'elev8-os'); ?></a>
                <a class="button" href="<?php echo esc_url($artwork_url); ?>"><?php esc_html_e('Manage My Artwork', 'elev8-os'); ?></a>
            </div>
        </section>
        <?php
        return (string) ob_get_clean();
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

        $dashboard_url = Elev8_OS_Portal_Page_Manager::get_url('dashboard');
        $artwork_url = Elev8_OS_Portal_Page_Manager::get_url('artwork');

        ob_start();
        ?>
        <div class="elev8-artist-dashboard elev8-dashboard-v2 elev8-artist-print-center">
            <?php Elev8_OS_Artist_Portal_Module::render_navigation('print_center'); ?>

            <header class="elev8-print-hero">
                <div>
                    <p class="elev8-eyebrow"><?php esc_html_e('Artist Portal', 'elev8-os'); ?></p>
                    <h1><?php esc_html_e('My Print Center', 'elev8-os'); ?></h1>
                    <p><?php esc_html_e('Preview and print your approved artist card, profile QR code, and artwork labels.', 'elev8-os'); ?></p>
                </div>
                <a class="elev8-button elev8-button-secondary" href="<?php echo esc_url($dashboard_url); ?>">
                    <?php esc_html_e('Back to Dashboard', 'elev8-os'); ?>
                </a>
            </header>

            <div class="elev8-print-center-grid">
                <section class="elev8-print-card">
                    <div class="elev8-print-card-icon"><span class="dashicons dashicons-id" aria-hidden="true"></span></div>
                    <div class="elev8-print-card-heading">
                        <p class="elev8-eyebrow"><?php esc_html_e('Artist materials', 'elev8-os'); ?></p>
                        <h2><?php esc_html_e('Artist Display Card', 'elev8-os'); ?></h2>
                        <p><?php esc_html_e('Print your approved introduction card or a standalone profile QR code using the Elev8 Arts print standard.', 'elev8-os'); ?></p>
                    </div>
                    <form class="elev8-print-form" method="get" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" target="_blank">
                        <input type="hidden" name="action" value="elev8_os_artist_print_profile">
                        <?php wp_nonce_field('elev8_os_artist_print_profile', '_wpnonce', false); ?>
                        <label class="elev8-print-field">
                            <span><?php esc_html_e('Print format', 'elev8-os'); ?></span>
                            <select name="print_format">
                                <option value="artist-card"><?php esc_html_e('Feature display — 8.5 × 5.5', 'elev8-os'); ?></option>
                                <option value="artist-card-two"><?php esc_html_e('Two feature displays — letter sheet', 'elev8-os'); ?></option>
                                <option value="artist-card-5x7"><?php esc_html_e('Table display — 5 × 7', 'elev8-os'); ?></option>
                                <option value="artist-label-3x1"><?php esc_html_e('Small artist label — 3 × 1', 'elev8-os'); ?></option>
                                <option value="artist-label-3x1-sheet"><?php esc_html_e('Small artist labels — 16 per sheet', 'elev8-os'); ?></option>
                                <option value="artist-qr"><?php esc_html_e('Profile QR display', 'elev8-os'); ?></option>
                            </select>
                        </label>
                        <button class="elev8-button elev8-button-primary" type="submit"><?php esc_html_e('Preview Artist Card', 'elev8-os'); ?></button>
                    </form>
                </section>

                <section class="elev8-print-card elev8-print-card-wide">
                    <div class="elev8-print-card-icon"><span class="dashicons dashicons-grid-view" aria-hidden="true"></span></div>
                    <div class="elev8-print-card-heading">
                        <p class="elev8-eyebrow"><?php esc_html_e('Artwork label sheets', 'elev8-os'); ?></p>
                        <h2><?php esc_html_e('Choose Some or Print Them All', 'elev8-os'); ?></h2>
                        <p><?php esc_html_e('Select any combination of artwork QR labels. Elev8 OS lays them out on full letter-size sheets so one 3 × 3 label no longer wastes an entire page.', 'elev8-os'); ?></p>
                    </div>
                    <?php if (!$assets): ?>
                        <div class="elev8-print-empty"><span class="dashicons dashicons-art" aria-hidden="true"></span><div><strong><?php esc_html_e('No artwork is currently available to print.', 'elev8-os'); ?></strong><p><?php esc_html_e('Add artwork from My Artwork, then return here to build a label sheet.', 'elev8-os'); ?></p></div></div>
                        <a class="elev8-button elev8-button-secondary" href="<?php echo esc_url($artwork_url); ?>"><?php esc_html_e('Go to My Artwork', 'elev8-os'); ?></a>
                    <?php else: ?>
                        <form class="elev8-print-form elev8-label-sheet-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" target="_blank" data-elev8-label-sheet>
                            <input type="hidden" name="action" value="elev8_os_artist_print_artwork">
                            <?php wp_nonce_field('elev8_os_artist_print_artwork'); ?>
                            <div class="elev8-label-selection-toolbar">
                                <button class="elev8-button elev8-button-secondary" type="button" data-elev8-select-all><?php esc_html_e('Select All', 'elev8-os'); ?></button>
                                <button class="elev8-button elev8-button-secondary" type="button" data-elev8-clear-all><?php esc_html_e('Clear All', 'elev8-os'); ?></button>
                                <strong data-elev8-selection-count><?php esc_html_e('0 selected', 'elev8-os'); ?></strong>
                            </div>
                            <div class="elev8-artwork-selection-grid">
                                <?php foreach ($assets as $asset): $asset_id = absint($asset['id'] ?? 0); ?>
                                    <label class="elev8-artwork-selection-item">
                                        <input type="checkbox" name="asset_ids[]" value="<?php echo esc_attr((string)$asset_id); ?>">
                                        <span><strong><?php echo esc_html((string)($asset['title'] ?? __('Untitled', 'elev8-os'))); ?></strong><small><?php echo esc_html(ucfirst((string)($asset['status'] ?? 'available'))); ?></small></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <div class="elev8-label-sheet-options">
                                <label class="elev8-print-field"><span><?php esc_html_e('Sheet format', 'elev8-os'); ?></span><select name="print_format"><option value="artwork-sheet-3x3"><?php esc_html_e('3 × 3 artwork labels — 6 per letter sheet', 'elev8-os'); ?></option><option value="artwork-sheet-3x1"><?php esc_html_e('3 × 1 compact labels — 16 per letter sheet', 'elev8-os'); ?></option></select></label>
                                <label class="elev8-print-field"><span><?php esc_html_e('Copies of each selected label', 'elev8-os'); ?></span><input type="number" name="copies_each" min="1" max="24" value="1"></label>
                            </div>
                            <p class="elev8-print-help"><?php esc_html_e('Example: select one artwork and choose 6 copies to fill a complete 3 × 3 sheet, or select six different artworks and leave copies at 1.', 'elev8-os'); ?></p>
                            <button class="elev8-button elev8-button-primary" type="submit" disabled data-elev8-print-selected><?php esc_html_e('Preview Selected Label Sheet', 'elev8-os'); ?></button>
                        </form>
                        <script>(function(){var form=document.querySelector('[data-elev8-label-sheet]');if(!form)return;var boxes=[].slice.call(form.querySelectorAll('input[name="asset_ids[]"]'));var count=form.querySelector('[data-elev8-selection-count]');var submit=form.querySelector('[data-elev8-print-selected]');function sync(){var n=boxes.filter(function(b){return b.checked;}).length;count.textContent=n+' selected';submit.disabled=n===0;}form.querySelector('[data-elev8-select-all]').addEventListener('click',function(){boxes.forEach(function(b){b.checked=true;});sync();});form.querySelector('[data-elev8-clear-all]').addEventListener('click',function(){boxes.forEach(function(b){b.checked=false;});sync();});boxes.forEach(function(b){b.addEventListener('change',sync);});sync();})();</script>
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
        ], $format === 'artist-qr' ? 'qr' : $format);
    }

    public static function print_artwork(): void {
        self::require_artist();
        check_admin_referer('elev8_os_artist_print_artwork');
        $requested_ids = isset($_REQUEST['asset_ids']) ? (array)wp_unslash($_REQUEST['asset_ids']) : [];
        $asset_ids = array_values(array_unique(array_filter(array_map('absint', $requested_ids))));
        if (!$asset_ids) {
            $single_id = absint($_REQUEST['asset_id'] ?? 0);
            if ($single_id > 0) { $asset_ids[] = $single_id; }
        }
        $assets = [];
        foreach ($asset_ids as $asset_id) {
            $asset = Elev8_OS_Asset_Service::get($asset_id);
            if (!$asset || absint($asset['owner_user_id'] ?? 0) !== get_current_user_id()) {
                wp_die(esc_html__('You may only print labels for your own artwork.', 'elev8-os'), 403);
            }
            $assets[] = $asset;
        }
        if (!$assets) { wp_die(esc_html__('Choose at least one artwork label to print.', 'elev8-os')); }
        $artist = Elev8_OS_Identity_Service::current_artist();
        $name = trim((string)($artist['firstName'] ?? '') . ' ' . (string)($artist['lastName'] ?? ''));
        if ($name === '') { $name = wp_get_current_user()->display_name; }
        $format = sanitize_key((string)($_REQUEST['print_format'] ?? 'artwork-sheet-3x3'));
        if (in_array($format, ['artwork-sheet-3x3', 'artwork-sheet-3x1'], true) || count($assets) > 1) {
            $copies_each = max(1, min(24, absint($_REQUEST['copies_each'] ?? 1)));
            $print_assets = [];
            foreach ($assets as $asset) { for ($i = 0; $i < $copies_each; $i++) { $print_assets[] = $asset; } }
            Elev8_OS_Print_Service::render_artwork_sheet($print_assets, $name, $format, Elev8_OS_Portal_Page_Manager::get_url('print_center'));
        }
        Elev8_OS_Print_Service::render_artwork($assets[0], $name, $format, Elev8_OS_Portal_Page_Manager::get_url('print_center'));
    }

    private static function require_artist(): void {
        if (!is_user_logged_in()) { auth_redirect(); exit; }
        if (Elev8_OS_Identity_Service::current_artist_id() <= 0) {
            wp_die(esc_html__('Your account is not connected to an approved artist.', 'elev8-os'), 403);
        }
    }
}

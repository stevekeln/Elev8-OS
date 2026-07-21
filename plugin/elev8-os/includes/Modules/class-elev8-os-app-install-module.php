<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Reusable Progressive Web App install experience for Elev8 OS.
 *
 * This module owns the manifest, service worker, install prompt, and
 * platform-specific fallback instructions. It does not hardcode roles.
 */
final class Elev8_OS_App_Install_Module {
    private static bool $rendered = false;

    private const MANIFEST_QUERY = 'elev8_os_manifest';
    private const SERVICE_WORKER_QUERY = 'elev8_os_service_worker';

    public static function init(): void {
        add_action('template_redirect', [__CLASS__, 'serve_runtime_assets'], 0);
        add_action('wp_head', [__CLASS__, 'head_meta'], 4);
        add_action('wp_enqueue_scripts', [__CLASS__, 'assets']);
        add_action('wp_footer', [__CLASS__, 'render_install_control'], 40);
    }

    public static function serve_runtime_assets(): void {
        if (isset($_GET[self::MANIFEST_QUERY])) {
            self::serve_manifest();
        }
        if (isset($_GET[self::SERVICE_WORKER_QUERY])) {
            self::serve_service_worker();
        }
    }

    public static function head_meta(): void {
        if (!self::should_load()) { return; }
        echo '<link rel="manifest" href="' . esc_url(self::manifest_url()) . '">' . "\n";
        echo '<meta name="theme-color" content="#6f2dbd">' . "\n";
        echo '<meta name="mobile-web-app-capable" content="yes">' . "\n";
        echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
        echo '<meta name="apple-mobile-web-app-status-bar-style" content="default">' . "\n";
        echo '<meta name="apple-mobile-web-app-title" content="Elev8 OS">' . "\n";
        $icon = get_site_icon_url(180);
        if ($icon) {
            echo '<link rel="apple-touch-icon" href="' . esc_url($icon) . '">' . "\n";
        }
    }

    public static function assets(): void {
        if (!self::should_load()) { return; }
        wp_enqueue_style('dashicons');
        wp_enqueue_style('elev8-os-app-install', ELEV8_OS_URL . 'assets/css/app-install.css', [], ELEV8_OS_VERSION);
        wp_enqueue_script('elev8-os-app-install', ELEV8_OS_URL . 'assets/js/app-install.js', [], ELEV8_OS_VERSION, true);
        wp_localize_script('elev8-os-app-install', 'Elev8OSInstall', [
            'serviceWorkerUrl' => self::service_worker_url(),
            'serviceWorkerScope' => self::service_worker_scope(),
            'homeUrl' => class_exists('Elev8_OS_Mobile_Home_Module') ? Elev8_OS_Mobile_Home_Module::get_url() : home_url('/elev8-app/'),
            'storageKey' => 'elev8_os_install_dismissed_v1',
            'installedKey' => 'elev8_os_installed_v1',
        ]);
    }

    public static function render_install_control(): void {
        self::render_control(false);
    }

    public static function render_dashboard_control(): void {
        self::render_control(true);
    }

    private static function render_control(bool $inline): void {
        if (!self::should_load() || self::$rendered) { return; }
        self::$rendered = true;
        ?>
        <aside class="elev8-app-install<?php echo $inline ? ' is-inline' : ''; ?>" data-elev8-app-install aria-live="polite">
            <button type="button" class="elev8-app-install__compact" data-elev8-install-open aria-label="<?php esc_attr_e('Install Elev8 OS on this phone', 'elev8-os'); ?>">
                <span class="dashicons dashicons-smartphone" aria-hidden="true"></span>
                <span><?php esc_html_e('Install App', 'elev8-os'); ?></span>
            </button>
            <div class="elev8-app-install__panel" data-elev8-install-panel hidden>
                <button type="button" class="elev8-app-install__close" data-elev8-install-close aria-label="<?php esc_attr_e('Close install instructions', 'elev8-os'); ?>">×</button>
                <span class="dashicons dashicons-smartphone" aria-hidden="true"></span>
                <div>
                    <p class="elev8-app-install__eyebrow"><?php esc_html_e('Elev8 OS on your phone', 'elev8-os'); ?></p>
                    <h2><?php esc_html_e('Install this app', 'elev8-os'); ?></h2>
                    <p data-elev8-install-message><?php esc_html_e('Add Elev8 OS to your phone for one-tap access to your Operational Home.', 'elev8-os'); ?></p>
                    <div class="elev8-app-install__actions">
                        <button type="button" class="elev8-app-install__primary" data-elev8-install-button><?php esc_html_e('Install Elev8 OS', 'elev8-os'); ?></button>
                        <a href="<?php echo esc_url(class_exists('Elev8_OS_Mobile_Home_Module') ? Elev8_OS_Mobile_Home_Module::get_url() : home_url('/elev8-app/')); ?>"><?php esc_html_e('Open App Home', 'elev8-os'); ?></a>
                    </div>
                    <ol class="elev8-app-install__steps" data-elev8-install-steps hidden></ol>
                </div>
            </div>
        </aside>
        <?php
    }

    private static function should_load(): bool {
        return !is_admin() && is_user_logged_in();
    }

    private static function manifest_url(): string {
        return add_query_arg(self::MANIFEST_QUERY, '1', home_url('/'));
    }

    private static function service_worker_url(): string {
        return add_query_arg(self::SERVICE_WORKER_QUERY, '1', home_url('/'));
    }

    private static function service_worker_scope(): string {
        $path = (string) wp_parse_url(home_url('/'), PHP_URL_PATH);
        return $path !== '' ? trailingslashit($path) : '/';
    }

    private static function serve_manifest(): void {
        $home = class_exists('Elev8_OS_Mobile_Home_Module') ? Elev8_OS_Mobile_Home_Module::get_url() : home_url('/elev8-app/');
        $icons = [];
        foreach ([192, 512] as $size) {
            $icon = get_site_icon_url($size);
            if ($icon) {
                $icons[] = ['src' => $icon, 'sizes' => $size . 'x' . $size, 'type' => 'image/png', 'purpose' => 'any maskable'];
            }
        }
        $manifest = [
            'id' => self::service_worker_scope() . 'elev8-app/',
            'name' => 'Elev8 OS',
            'short_name' => 'Elev8 OS',
            'description' => 'Your role-based operational home for Elev8.',
            'start_url' => $home,
            'scope' => self::service_worker_scope(),
            'display' => 'standalone',
            'background_color' => '#ffffff',
            'theme_color' => '#6f2dbd',
            'orientation' => 'portrait-primary',
            'icons' => $icons,
        ];
        nocache_headers();
        header('Content-Type: application/manifest+json; charset=' . get_option('blog_charset'));
        echo wp_json_encode($manifest, JSON_UNESCAPED_SLASHES);
        exit;
    }

    private static function serve_service_worker(): void {
        nocache_headers();
        header('Content-Type: application/javascript; charset=UTF-8');
        header('Service-Worker-Allowed: ' . self::service_worker_scope());
        echo "const CACHE='elev8-os-shell-" . esc_js(ELEV8_OS_VERSION) . "';\n";
        echo "self.addEventListener('install',event=>{self.skipWaiting();});\n";
        echo "self.addEventListener('activate',event=>{event.waitUntil(caches.keys().then(keys=>Promise.all(keys.filter(key=>key.startsWith('elev8-os-shell-')&&key!==CACHE).map(key=>caches.delete(key)))).then(()=>self.clients.claim()));});\n";
        echo "self.addEventListener('fetch',event=>{if(event.request.method!=='GET'||event.request.mode==='navigate'){return;}event.respondWith(fetch(event.request).catch(()=>caches.match(event.request)));});\n";
        exit;
    }
}

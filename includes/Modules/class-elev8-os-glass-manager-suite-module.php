<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Frontend application suite for Glass Managers.
 * Reuses the existing Glass Operations and Production Catalog modules/services.
 */
final class Elev8_OS_Glass_Manager_Suite_Module {
    const OPTION_PAGE_ID = 'elev8_os_glass_manager_suite_page_id';
    const SLUG = 'glass-manager';
    const SHORTCODE = 'elev8_glass_manager_suite';

    public static function init(): void {
        add_shortcode(self::SHORTCODE, [__CLASS__, 'shortcode']);
        add_action('init', [__CLASS__, 'ensure_page'], 30);
        add_action('wp_enqueue_scripts', [__CLASS__, 'assets']);
        add_action('wp_head', [__CLASS__, 'viewport_meta'], 1);
    }


    /**
     * The application workspace must use the real device viewport even when the
     * active public theme omits a mobile viewport declaration.
     */
    public static function viewport_meta(): void {
        if (!self::is_current()) { return; }
        echo '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">' . "\n";
    }

    public static function ensure_page(): void {
        $id = absint(get_option(self::OPTION_PAGE_ID));
        if ($id && get_post($id) instanceof WP_Post) { return; }
        $page = get_page_by_path(self::SLUG, OBJECT, 'page');
        if ($page instanceof WP_Post && $page->post_status !== 'trash') {
            update_option(self::OPTION_PAGE_ID, (int) $page->ID, false);
            return;
        }
        if (!current_user_can('manage_options')) { return; }
        $id = wp_insert_post([
            'post_title' => __('Glass Manager', 'elev8-os'),
            'post_name' => self::SLUG,
            'post_content' => '[' . self::SHORTCODE . ']',
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_author' => get_current_user_id(),
            'comment_status' => 'closed',
        ], true);
        if (!is_wp_error($id) && $id > 0) { update_option(self::OPTION_PAGE_ID, (int) $id, false); }
    }

    public static function url(array $args = []): string {
        $id = absint(get_option(self::OPTION_PAGE_ID));
        $base = $id ? get_permalink($id) : home_url('/' . self::SLUG . '/');
        return $args ? add_query_arg($args, $base) : $base;
    }

    public static function is_current(): bool {
        return is_page(absint(get_option(self::OPTION_PAGE_ID))) || is_page(self::SLUG);
    }

    public static function assets(): void {
        if (!self::is_current()) { return; }
        wp_enqueue_style('elev8-glass-manager-suite', ELEV8_OS_URL . 'assets/css/glass-manager-suite.css', [], ELEV8_OS_VERSION);
        wp_enqueue_style('elev8-glass-operations', ELEV8_OS_URL . 'assets/css/glass-operations.css', [], ELEV8_OS_VERSION);
        wp_enqueue_style('elev8-production-catalog', ELEV8_OS_URL . 'assets/css/production-catalog.css', [], ELEV8_OS_VERSION);
        wp_enqueue_style('elev8-class-approvals', ELEV8_OS_URL . 'assets/css/class-approvals.css', [], ELEV8_OS_VERSION);
        $tool = sanitize_key($_GET['suite_tool'] ?? 'operations');
        $view = sanitize_key($_GET['view'] ?? 'dashboard');
        if ($tool === 'operations' && $view === 'board') {
            wp_enqueue_script('elev8-glass-production-board', ELEV8_OS_URL . 'assets/js/glass-production-board.js', [], ELEV8_OS_VERSION, true);
            wp_localize_script('elev8-glass-production-board', 'Elev8GlassBoard', [
                'ajaxUrl' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('elev8_glass_board'),
                'errorMessage' => __('The production job could not be updated.', 'elev8-os'),
            ]);
        }
        wp_enqueue_script('elev8-class-approvals', ELEV8_OS_URL . 'assets/js/class-approvals.js', [], ELEV8_OS_VERSION, true);
        wp_localize_script('elev8-class-approvals', 'Elev8ClassAlerts', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('elev8_class_alerts'),
            'url' => self::url(['suite_tool'=>'approvals']),
            'icon' => get_site_icon_url(192),
            'initialCount' => count(Elev8_OS_Class_Approval_Service::pending_for_current_user()),
        ]);
        if ($tool === 'operations' && $view === 'payouts') {
            wp_enqueue_script('elev8-glass-fast-pay', ELEV8_OS_URL . 'assets/js/glass-fast-pay.js', [], ELEV8_OS_VERSION, true);
            wp_localize_script('elev8-glass-fast-pay', 'Elev8GlassFastPay', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'createNonce' => wp_create_nonce('elev8_quick_create_pay_item'),
                'favoriteNonce' => wp_create_nonce('elev8_toggle_pay_favorite'),
                'products' => Elev8_OS_Production_Catalog_Service::pay_search('', 100),
                'hourlyRates' => array_reduce(Elev8_OS_Glass_Operations_Service::glass_workers(), static function($carry,$user){$p=Elev8_OS_Production_Catalog_Service::compensation_profile((int)$user->ID);$carry[$user->ID]=(float)($p['hourly_rate']??0);return $carry;}, []),
            ]);
        }
    }

    public static function shortcode(): string {
        if (!is_user_logged_in()) { return '<div class="elev8-suite-message">Please sign in to use Glass Operations.</div>'; }
        if (!Elev8_OS_Access_Service::user_can('view_glass_dashboard')) { return '<div class="elev8-suite-message">You do not have access to Glass Operations.</div>'; }

        $tool = sanitize_key($_GET['suite_tool'] ?? 'operations');
        if (!in_array($tool, ['operations','catalog','approvals'], true)) { $tool = 'operations'; }

        ob_start();
        ?>
        <div class="elev8-glass-suite">
            <aside class="elev8-glass-suite__nav" aria-label="Glass Manager tools">
                <div class="elev8-glass-suite__brand"><span>Elev8 Premier</span><strong>Glass Manager</strong></div>
                <?php self::nav_link('Dashboard', ['suite_tool'=>'operations']); ?>
                <?php self::nav_link('Production Board', ['suite_tool'=>'operations','view'=>'board']); ?>
                <?php self::nav_link('New Production Job', ['suite_tool'=>'operations','view'=>'new-job']); ?>
                <?php self::nav_link('Fast Pay & Pay Sheets', ['suite_tool'=>'operations','view'=>'payouts']); ?>
                <?php self::nav_link('Glassblower Team', ['suite_tool'=>'operations','view'=>'team']); ?>
                <?php self::nav_link('Class Approvals', ['suite_tool'=>'approvals']); ?>
                <?php self::nav_link('Repair Intake', ['suite_tool'=>'operations','view'=>'repair-intake']); ?>
                <?php self::nav_link('Memorial Intake', ['suite_tool'=>'operations','view'=>'memorial-intake']); ?>
                <div class="elev8-glass-suite__divider">Catalog</div>
                <?php self::nav_link('Production Products', ['suite_tool'=>'catalog']); ?>
                <?php self::nav_link('New Product', ['suite_tool'=>'catalog','view'=>'new-product']); ?>
                <?php self::nav_link('Materials', ['suite_tool'=>'catalog','view'=>'materials']); ?>
                <?php self::nav_link('Compensation Profiles', ['suite_tool'=>'catalog','view'=>'compensation']); ?>
                <?php self::nav_link('Catalog Manager', ['suite_tool'=>'catalog','view'=>'manager']); ?>
                <?php self::nav_link('Import Wizard', ['suite_tool'=>'catalog','view'=>'wizard']); ?>
                <div class="elev8-glass-suite__divider">Team</div>
                <a href="<?php echo esc_url(Elev8_OS_Portal_Page_Manager::get_url('classes')); ?>">Glass Classes</a>
                <a href="<?php echo esc_url(home_url('/elev8-conversations/')); ?>">Conversations</a>
                <a href="<?php echo esc_url(Elev8_OS_Portal_Page_Manager::get_url('actions')); ?>">My Actions</a>
            </aside>
            <main class="elev8-glass-suite__content">
                <?php $pending_class_count = count(Elev8_OS_Class_Approval_Service::pending_for_current_user()); ?>
                <?php if ($tool !== 'approvals' && $pending_class_count > 0) : ?>
                    <a class="elev8-suite-class-alert" data-elev8-class-alert-count="<?php echo esc_attr((string) $pending_class_count); ?>" href="<?php echo esc_url(self::url(['suite_tool'=>'approvals'])); ?>">
                        <strong><?php echo esc_html(sprintf(_n('%d class booking needs a decision', '%d class bookings need a decision', $pending_class_count, 'elev8-os'), $pending_class_count)); ?></strong>
                        <span><?php esc_html_e('Approve, move the date, or cancel now.', 'elev8-os'); ?></span>
                    </a>
                <?php endif; ?>
                <?php
                // Existing modules use this flag to create frontend-safe navigation URLs.
                $_GET['elev8_glass_suite'] = '1';
                if ($tool === 'catalog') { Elev8_OS_Production_Catalog_Module::render(); }
                elseif ($tool === 'approvals') { Elev8_OS_Class_Approval_Module::render(); }
                else { Elev8_OS_Glass_Operations_Module::render(); }
                ?>
            </main>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private static function nav_link(string $label, array $args): void {
        $current_tool = sanitize_key($_GET['suite_tool'] ?? 'operations');
        $current_view = sanitize_key($_GET['view'] ?? ($current_tool === 'operations' ? 'dashboard' : 'products'));
        $target_tool = $args['suite_tool'];
        $target_view = $args['view'] ?? ($target_tool === 'operations' ? 'dashboard' : ($target_tool === 'catalog' ? 'products' : '')); 
        $active = $current_tool === $target_tool && $current_view === $target_view;
        echo '<a class="' . ($active ? 'is-active' : '') . '" href="' . esc_url(self::url($args)) . '">' . esc_html($label) . '</a>';
    }
}

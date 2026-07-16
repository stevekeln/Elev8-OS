<?php
if (!defined('ABSPATH')) { exit; }

require_once ELEV8_OS_DIR . 'includes/Services/class-elev8-os-identity-service.php';
require_once ELEV8_OS_DIR . 'includes/Services/class-elev8-os-settings-service.php';
require_once ELEV8_OS_DIR . 'includes/Services/class-elev8-os-notification-service.php';
require_once ELEV8_OS_DIR . 'includes/Services/class-elev8-os-activity-service.php';
require_once ELEV8_OS_DIR . 'includes/Services/class-elev8-os-opportunity-gateway.php';

/**
 * Elev8-owned Class Requests / Waitlist Engine.
 *
 * This engine owns demand before scheduling. It intentionally does not read or
 * write Amelia classes. Amelia remains the scheduling and booking integration.
 */
final class Elev8_OS_Waitlist_Module {
    private const SHORTCODE = 'elev8_artist_waitlist';
    private const ADMIN_SLUG = 'elev8-waitlists';
    private const EMPLOYEE_META = 'elev8_os_amelia_employee_id';

    public static function init(): void {
        add_shortcode(self::SHORTCODE, [__CLASS__, 'shortcode']);
        add_action('admin_menu', [__CLASS__, 'admin_menu'], 40);
        add_action('admin_post_elev8_os_class_request_add', [__CLASS__, 'handle_add_request']);
        add_action('admin_post_elev8_os_class_idea_suggest', [__CLASS__, 'handle_suggest_idea']);
        add_action('admin_post_elev8_os_class_request_update', [__CLASS__, 'handle_update_request']);
        add_action('admin_post_elev8_os_class_request_delete', [__CLASS__, 'handle_delete_request']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    public static function status(): string { return 'active'; }
    public static function activate(): void {}
    public static function maybe_upgrade(): void {}

    public static function admin_menu(): void {
        add_submenu_page(
            'elev8-os',
            __('Class Requests', 'elev8-os'),
            __('Class Requests', 'elev8-os'),
            'manage_options',
            self::ADMIN_SLUG,
            [__CLASS__, 'render_admin']
        );
    }

    public static function enqueue_assets(): void {
        if (!is_user_logged_in() || !class_exists('Elev8_OS_Portal_Page_Manager') || !Elev8_OS_Portal_Page_Manager::is_current_page('waitlist')) {
            return;
        }
        wp_enqueue_style('elev8-os-artist-portal', ELEV8_OS_URL . 'assets/css/artist-portal.css', [], ELEV8_OS_VERSION);
        wp_enqueue_style('elev8-os-waitlist', ELEV8_OS_URL . 'assets/css/artist-waitlist.css', ['elev8-os-artist-portal'], ELEV8_OS_VERSION);
    }

    public static function shortcode(): string {
        if (!is_user_logged_in()) {
            return '<div class="elev8-dashboard-login"><p>' . esc_html__('Please log in to view class requests.', 'elev8-os') . '</p></div>';
        }
        if (!class_exists('Elev8_OS_Opportunity_Service') || !class_exists('Elev8_OS_Opportunity_Gateway')) {
            return '<div class="elev8-dashboard-warning"><p><strong>' . esc_html__('Class Requests are temporarily unavailable.', 'elev8-os') . '</strong></p></div>';
        }

        $user = wp_get_current_user();
        $teacher_id = self::resolve_teacher_id($user);
        $admin_preview = current_user_can('manage_options');
        if ($admin_preview && isset($_GET['employee_id'])) {
            $teacher_id = absint($_GET['employee_id']);
        }
        if ($teacher_id <= 0 && !$admin_preview) {
            return '<div class="elev8-dashboard-warning"><p><strong>' . esc_html__('Your account is not connected to an Elev8 Member Artist record.', 'elev8-os') . '</strong></p></div>';
        }

        ob_start();
        echo '<div class="elev8-artist-dashboard elev8-waitlist elev8-class-requests">';
        if (class_exists('Elev8_OS_Artist_Portal_Module')) {
            Elev8_OS_Artist_Portal_Module::render_navigation('waitlist');
        }
        self::render_content($teacher_id, false, $admin_preview);
        echo '</div>';
        return (string) ob_get_clean();
    }

    public static function render_admin(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to view this page.', 'elev8-os'));
        }
        $teacher_id = absint($_GET['employee_id'] ?? 0);
        echo '<div class="wrap"><h1>' . esc_html__('Class Requests', 'elev8-os') . '</h1><p>' . esc_html__('Manage Elev8-owned demand before scheduling. This page does not use Amelia class dates.', 'elev8-os') . '</p>';
        self::render_content($teacher_id, true, false);
        echo '</div>';
    }

    private static function render_content(int $teacher_id, bool $admin, bool $admin_preview): void {
        $opportunities = self::available_opportunities($teacher_id, $admin);
        $all_requests = self::request_rows($teacher_id, $admin);
        $filters = self::filters();
        $requests = self::filter_rows($all_requests, $filters);
        $groups = self::group_requests($all_requests);

        $people = count($all_requests);
        $seats = array_sum(array_map(static fn(array $r): int => (int) ($r['seats_requested'] ?? 0), $all_requests));
        $idea_count = count($opportunities);
        $follow_up_count = self::follow_up_count($all_requests);
        $revenue = self::potential_revenue($all_requests);
        $redirect = $admin
            ? add_query_arg(['page' => self::ADMIN_SLUG, 'employee_id' => $teacher_id], admin_url('admin.php'))
            : Elev8_OS_Portal_Page_Manager::get_url('waitlist');

        self::render_notices();
        ?>
        <header class="elev8-dashboard-header elev8-waitlist-header">
            <div>
                <p class="elev8-eyebrow"><?php esc_html_e('Artist Portal', 'elev8-os'); ?></p>
                <h1><?php esc_html_e('Class Requests', 'elev8-os'); ?></h1>
                <p><?php esc_html_e('Capture demand, organize follow-up, and decide which classes are ready to plan—before anything is scheduled in Amelia.', 'elev8-os'); ?></p>
            </div>
            <span class="elev8-dashboard-badge"><?php esc_html_e('Elev8-owned Waitlist Engine', 'elev8-os'); ?></span>
        </header>

        <section class="elev8-waitlist-metrics" aria-label="<?php esc_attr_e('Class request summary', 'elev8-os'); ?>">
            <?php self::metric(__('People interested', 'elev8-os'), (string) $people, __('Unique request records in this view.', 'elev8-os')); ?>
            <?php self::metric(__('Requested seats', 'elev8-os'), (string) $seats, __('Total seats customers may purchase.', 'elev8-os')); ?>
            <?php self::metric(__('Class ideas', 'elev8-os'), (string) $idea_count, __('Active Elev8 OS opportunities.', 'elev8-os')); ?>
            <?php self::metric(__('Follow-up needed', 'elev8-os'), (string) $follow_up_count, __('New or due customer requests.', 'elev8-os')); ?>
            <?php self::metric(__('Potential value', 'elev8-os'), $revenue['available'] ? self::money((float) $revenue['value']) : __('Unavailable', 'elev8-os'), $revenue['diagnostic']); ?>
        </section>

        <?php self::render_demand_groups($groups); ?>

        <div class="elev8-waitlist-workspace">
            <?php self::render_add_request_form($opportunities, $teacher_id, $redirect); ?>
            <?php self::render_idea_form($teacher_id, $redirect); ?>
        </div>

        <section class="elev8-waitlist-panel elev8-request-list-panel">
            <div class="elev8-waitlist-section-heading">
                <div>
                    <p class="elev8-eyebrow"><?php esc_html_e('Customer queue', 'elev8-os'); ?></p>
                    <h2><?php esc_html_e('Follow-up workspace', 'elev8-os'); ?></h2>
                </div>
                <span class="elev8-waitlist-count"><?php echo esc_html((string) count($requests)); ?></span>
            </div>

            <?php self::render_filters($opportunities, $filters, $redirect); ?>

            <?php if (!$requests) : ?>
                <div class="elev8-waitlist-empty">
                    <strong><?php esc_html_e('No requests match these filters', 'elev8-os'); ?></strong>
                    <p><?php esc_html_e('Clear the filters or add a new customer request.', 'elev8-os'); ?></p>
                </div>
            <?php else : ?>
                <div class="elev8-waitlist-cards">
                    <?php foreach ($requests as $request) { self::request_card($request, $redirect); } ?>
                </div>
            <?php endif; ?>
        </section>
        <?php
    }

    private static function render_add_request_form(array $opportunities, int $teacher_id, string $redirect): void {
        ?>
        <section class="elev8-waitlist-panel">
            <div class="elev8-waitlist-section-heading"><div><p class="elev8-eyebrow"><?php esc_html_e('Customer demand', 'elev8-os'); ?></p><h2><?php esc_html_e('Add a class request', 'elev8-os'); ?></h2></div></div>
            <p class="elev8-request-help"><?php esc_html_e('Choose an existing class idea or create a new one while recording the customer.', 'elev8-os'); ?></p>
            <form class="elev8-waitlist-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="elev8_os_class_request_add">
                <input type="hidden" name="teacher_id" value="<?php echo esc_attr((string) $teacher_id); ?>">
                <input type="hidden" name="redirect_to" value="<?php echo esc_url($redirect); ?>">
                <?php wp_nonce_field('elev8_os_class_request_add'); ?>
                <label class="elev8-waitlist-class-select"><span><?php esc_html_e('Existing class idea', 'elev8-os'); ?></span><select name="opportunity_id"><option value="0"><?php esc_html_e('Choose an idea, or suggest a new class below', 'elev8-os'); ?></option><?php foreach ($opportunities as $opportunity) : ?><option value="<?php echo esc_attr((string) $opportunity['id']); ?>"><?php echo esc_html((string) $opportunity['title']); ?><?php echo !empty($opportunity['category']) ? ' — ' . esc_html((string) $opportunity['category']) : ''; ?></option><?php endforeach; ?></select></label>
                <div class="elev8-request-or"><span><?php esc_html_e('OR', 'elev8-os'); ?></span></div>
                <label class="elev8-waitlist-class-select"><span><?php esc_html_e('Suggest a new class', 'elev8-os'); ?></span><input type="text" name="new_class_title" placeholder="<?php esc_attr_e('Example: Beginner pottery wheel', 'elev8-os'); ?>"></label>
                <label><span><?php esc_html_e('Category', 'elev8-os'); ?></span><input type="text" name="new_class_category" placeholder="<?php esc_attr_e('Pottery, stained glass, wellness…', 'elev8-os'); ?>"></label>
                <label><span><?php esc_html_e('Customer name', 'elev8-os'); ?> *</span><input type="text" name="customer_name" required></label>
                <label><span><?php esc_html_e('Email', 'elev8-os'); ?></span><input type="email" name="customer_email"></label>
                <label><span><?php esc_html_e('Phone', 'elev8-os'); ?></span><input type="text" name="customer_phone"></label>
                <label><span><?php esc_html_e('Seats requested', 'elev8-os'); ?></span><input type="number" min="1" name="seats_requested" value="1"></label>
                <label><span><?php esc_html_e('Preferred days', 'elev8-os'); ?></span><input type="text" name="preferred_days" placeholder="<?php esc_attr_e('Saturday, weekday evenings…', 'elev8-os'); ?>"></label>
                <label><span><?php esc_html_e('Preferred times', 'elev8-os'); ?></span><input type="text" name="preferred_times" placeholder="<?php esc_attr_e('Morning, after 5 PM…', 'elev8-os'); ?>"></label>
                <label class="elev8-waitlist-notes"><span><?php esc_html_e('Notes', 'elev8-os'); ?></span><textarea name="notes" rows="3"></textarea></label>
                <div class="elev8-waitlist-submit"><button class="elev8-waitlist-primary" type="submit"><?php esc_html_e('Save Class Request', 'elev8-os'); ?></button></div>
            </form>
        </section>
        <?php
    }

    private static function render_idea_form(int $teacher_id, string $redirect): void {
        ?>
        <section class="elev8-waitlist-panel">
            <div class="elev8-waitlist-section-heading"><div><p class="elev8-eyebrow"><?php esc_html_e('Teacher idea', 'elev8-os'); ?></p><h2><?php esc_html_e('Suggest a class without a customer', 'elev8-os'); ?></h2></div></div>
            <p class="elev8-request-help"><?php esc_html_e('Submit something you want to teach. It enters the Opportunity Engine for planning and approval.', 'elev8-os'); ?></p>
            <form class="elev8-waitlist-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="elev8_os_class_idea_suggest"><input type="hidden" name="teacher_id" value="<?php echo esc_attr((string) $teacher_id); ?>"><input type="hidden" name="redirect_to" value="<?php echo esc_url($redirect); ?>"><?php wp_nonce_field('elev8_os_class_idea_suggest'); ?>
                <label class="elev8-waitlist-class-select"><span><?php esc_html_e('Class name', 'elev8-os'); ?> *</span><input type="text" name="title" required></label>
                <label><span><?php esc_html_e('Category', 'elev8-os'); ?></span><input type="text" name="category"></label>
                <label><span><?php esc_html_e('Estimated price per seat', 'elev8-os'); ?></span><input type="number" min="0" step="0.01" name="estimated_price"></label>
                <label><span><?php esc_html_e('Estimated duration in hours', 'elev8-os'); ?></span><input type="number" min="0" step="0.25" name="estimated_duration"></label>
                <label><span><?php esc_html_e('Preferred day', 'elev8-os'); ?></span><input type="text" name="preferred_day"></label>
                <label><span><?php esc_html_e('Preferred time', 'elev8-os'); ?></span><input type="text" name="preferred_time"></label>
                <label class="elev8-waitlist-notes"><span><?php esc_html_e('Description', 'elev8-os'); ?></span><textarea name="description" rows="4"></textarea></label>
                <div class="elev8-waitlist-submit"><button class="elev8-waitlist-primary" type="submit"><?php esc_html_e('Suggest Class Idea', 'elev8-os'); ?></button></div>
            </form>
        </section>
        <?php
    }

    private static function render_demand_groups(array $groups): void {
        ?>
        <section class="elev8-waitlist-panel elev8-demand-overview">
            <div class="elev8-waitlist-section-heading"><div><p class="elev8-eyebrow"><?php esc_html_e('Demand by class', 'elev8-os'); ?></p><h2><?php esc_html_e('What customers are asking for', 'elev8-os'); ?></h2></div></div>
            <?php if (!$groups) : ?>
                <div class="elev8-waitlist-empty"><strong><?php esc_html_e('No demand recorded yet', 'elev8-os'); ?></strong></div>
            <?php else : ?>
                <div class="elev8-demand-grid">
                    <?php foreach ($groups as $group) : ?>
                        <article class="elev8-demand-card">
                            <div><span class="elev8-demand-category"><?php echo esc_html($group['category'] !== '' ? $group['category'] : __('Uncategorized', 'elev8-os')); ?></span><h3><?php echo esc_html($group['title']); ?></h3></div>
                            <div class="elev8-demand-numbers"><span><strong><?php echo esc_html((string) $group['people']); ?></strong><?php esc_html_e('people', 'elev8-os'); ?></span><span><strong><?php echo esc_html((string) $group['seats']); ?></strong><?php esc_html_e('seats', 'elev8-os'); ?></span></div>
                            <p><?php echo esc_html($group['price_available'] ? sprintf(__('Potential value: %s', 'elev8-os'), self::money((float) $group['potential'])) : __('Potential value: Unavailable until a price is entered.', 'elev8-os')); ?></p>
                            <small><?php echo esc_html(sprintf(__('Last request: %s', 'elev8-os'), self::date_label($group['last_request']))); ?></small>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        <?php
    }

    private static function render_filters(array $opportunities, array $filters, string $redirect): void {
        $statuses = class_exists('Elev8_OS_Opportunity_Service') ? Elev8_OS_Opportunity_Gateway::interest_statuses() : [];
        ?>
        <form class="elev8-request-filters" method="get" action="<?php echo esc_url($redirect); ?>">
            <?php if (isset($_GET['page'])) : ?><input type="hidden" name="page" value="<?php echo esc_attr(sanitize_key((string) $_GET['page'])); ?>"><?php endif; ?>
            <?php if (isset($_GET['employee_id'])) : ?><input type="hidden" name="employee_id" value="<?php echo esc_attr((string) absint($_GET['employee_id'])); ?>"><?php endif; ?>
            <label><span><?php esc_html_e('Class', 'elev8-os'); ?></span><select name="request_class"><option value="0"><?php esc_html_e('All classes', 'elev8-os'); ?></option><?php foreach ($opportunities as $opportunity) : ?><option value="<?php echo esc_attr((string) $opportunity['id']); ?>" <?php selected($filters['class_id'], (int) $opportunity['id']); ?>><?php echo esc_html((string) $opportunity['title']); ?></option><?php endforeach; ?></select></label>
            <label><span><?php esc_html_e('Status', 'elev8-os'); ?></span><select name="request_status"><option value=""><?php esc_html_e('All statuses', 'elev8-os'); ?></option><?php foreach ($statuses as $status) : ?><option value="<?php echo esc_attr($status); ?>" <?php selected($filters['status'], $status); ?>><?php echo esc_html(ucwords(str_replace('_', ' ', $status))); ?></option><?php endforeach; ?></select></label>
            <label class="elev8-filter-search"><span><?php esc_html_e('Search', 'elev8-os'); ?></span><input type="search" name="request_search" value="<?php echo esc_attr($filters['search']); ?>" placeholder="<?php esc_attr_e('Name, email, phone, class…', 'elev8-os'); ?>"></label>
            <button type="submit"><?php esc_html_e('Filter', 'elev8-os'); ?></button>
            <a href="<?php echo esc_url(remove_query_arg(['request_class', 'request_status', 'request_search'], $redirect)); ?>"><?php esc_html_e('Clear', 'elev8-os'); ?></a>
        </form>
        <?php
    }

    public static function handle_add_request(): void {
        self::authorize('elev8_os_class_request_add');
        $teacher_id = absint($_POST['teacher_id'] ?? 0);
        self::verify_teacher_scope($teacher_id);
        $opportunity_id = absint($_POST['opportunity_id'] ?? 0);
        $new_title = sanitize_text_field(wp_unslash((string) ($_POST['new_class_title'] ?? '')));

        if ($opportunity_id <= 0 && $new_title !== '') {
            $opportunity_id = Elev8_OS_Opportunity_Gateway::save([
                'type' => 'class',
                'title' => $new_title,
                'category' => wp_unslash($_POST['new_class_category'] ?? ''),
                'status' => 'idea',
                'teacher_id' => $teacher_id,
                'teacher_needed' => 0,
            ]);
            self::record($opportunity_id, 'teacher_class_suggested', __('Class idea suggested', 'elev8-os'), __('Created while adding a customer class request.', 'elev8-os'));
        }

        if ($opportunity_id <= 0) {
            self::redirect('class_request_error');
        }
        self::verify_opportunity_access($opportunity_id);

        $email = sanitize_email(wp_unslash((string) ($_POST['customer_email'] ?? '')));
        $phone = sanitize_text_field(wp_unslash((string) ($_POST['customer_phone'] ?? '')));
        if (self::duplicate_exists($opportunity_id, $email, $phone)) {
            self::redirect('class_request_duplicate');
        }

        $interest_id = Elev8_OS_Opportunity_Gateway::add_interest([
            'opportunity_id' => $opportunity_id,
            'customer_name' => wp_unslash($_POST['customer_name'] ?? ''),
            'customer_email' => $email,
            'customer_phone' => $phone,
            'seats_requested' => $_POST['seats_requested'] ?? 1,
            'preferred_days' => wp_unslash($_POST['preferred_days'] ?? ''),
            'preferred_times' => wp_unslash($_POST['preferred_times'] ?? ''),
            'notes' => wp_unslash($_POST['notes'] ?? ''),
            'source' => 'artist_portal',
            'crm_status' => 'new',
        ]);

        if ($interest_id > 0) {
            self::record($opportunity_id, 'customer_class_request_added', __('Customer class request added', 'elev8-os'), sanitize_text_field(wp_unslash((string) ($_POST['customer_name'] ?? ''))), $interest_id);
        }
        self::redirect($interest_id > 0 ? 'class_request_saved' : 'class_request_error');
    }

    public static function handle_suggest_idea(): void {
        self::authorize('elev8_os_class_idea_suggest');
        $teacher_id = absint($_POST['teacher_id'] ?? 0);
        self::verify_teacher_scope($teacher_id);
        $id = Elev8_OS_Opportunity_Gateway::save([
            'type' => 'class',
            'title' => wp_unslash($_POST['title'] ?? ''),
            'category' => wp_unslash($_POST['category'] ?? ''),
            'description' => wp_unslash($_POST['description'] ?? ''),
            'status' => 'idea',
            'teacher_id' => $teacher_id,
            'teacher_needed' => 0,
            'estimated_price' => $_POST['estimated_price'] ?? '',
            'estimated_duration' => $_POST['estimated_duration'] ?? '',
            'preferred_day' => wp_unslash($_POST['preferred_day'] ?? ''),
            'preferred_time' => wp_unslash($_POST['preferred_time'] ?? ''),
        ]);
        if ($id > 0) {
            self::record($id, 'teacher_class_suggested', __('Teacher suggested a class', 'elev8-os'), __('Submitted from the Artist Portal Class Requests page.', 'elev8-os'));
        }
        self::redirect($id > 0 ? 'class_idea_saved' : 'class_request_error');
    }

    public static function handle_update_request(): void {
        self::authorize('elev8_os_class_request_update');
        $interest_id = absint($_POST['interest_id'] ?? 0);
        $before = Elev8_OS_Opportunity_Gateway::get_interest($interest_id);
        if (!$before) { self::redirect('class_request_error'); }
        self::verify_opportunity_access((int) $before['opportunity_id']);
        $ok = Elev8_OS_Opportunity_Gateway::update_interest(wp_unslash($_POST));
        if ($ok) {
            self::record((int) $before['opportunity_id'], 'customer_class_request_updated', __('Customer class request updated', 'elev8-os'), '', $interest_id);
        }
        self::redirect($ok ? 'class_request_updated' : 'class_request_error');
    }

    public static function handle_delete_request(): void {
        self::authorize('elev8_os_class_request_delete');
        $interest_id = absint($_POST['interest_id'] ?? 0);
        $before = Elev8_OS_Opportunity_Gateway::get_interest($interest_id);
        if (!$before) { self::redirect('class_request_error'); }
        self::verify_opportunity_access((int) $before['opportunity_id']);
        $ok = Elev8_OS_Opportunity_Gateway::delete_interest($interest_id);
        if ($ok) {
            self::record((int) $before['opportunity_id'], 'customer_class_request_deleted', __('Customer class request deleted', 'elev8-os'), (string) $before['customer_name'], $interest_id);
        }
        self::redirect($ok ? 'class_request_deleted' : 'class_request_error');
    }

    private static function available_opportunities(int $teacher_id, bool $admin): array {
        $all = Elev8_OS_Opportunity_Gateway::all();
        return array_values(array_filter($all, static function(array $opportunity) use ($teacher_id, $admin): bool {
            if ((string) ($opportunity['type'] ?? '') !== 'class' || in_array((string) ($opportunity['status'] ?? ''), ['completed', 'archived', 'cancelled'], true)) {
                return false;
            }
            if ($admin || $teacher_id <= 0) { return true; }
            return (int) ($opportunity['teacher_id'] ?? 0) === $teacher_id;
        }));
    }

    private static function request_rows(int $teacher_id, bool $admin): array {
        $rows = [];
        foreach (Elev8_OS_Opportunity_Gateway::all() as $opportunity) {
            if ((string) ($opportunity['type'] ?? '') !== 'class') { continue; }
            if (!$admin && $teacher_id > 0 && (int) ($opportunity['teacher_id'] ?? 0) !== $teacher_id) { continue; }
            foreach (Elev8_OS_Opportunity_Gateway::interests((int) $opportunity['id']) as $interest) {
                $interest['opportunity_title'] = $opportunity['title'] ?? '';
                $interest['category'] = $opportunity['category'] ?? '';
                $interest['estimated_price'] = $opportunity['estimated_price'] ?? '';
                $interest['opportunity_status'] = $opportunity['status'] ?? '';
                $rows[] = $interest;
            }
        }
        usort($rows, static fn(array $a, array $b): int => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));
        return $rows;
    }

    private static function request_card(array $request, string $redirect): void {
        $email = sanitize_email((string) ($request['customer_email'] ?? ''));
        $phone = sanitize_text_field((string) ($request['customer_phone'] ?? ''));
        $status = (string) ($request['crm_status'] ?? 'new');
        $follow_up = (string) ($request['follow_up_date'] ?? '');
        $notes = (string) ($request['notes'] ?? '');
        $contacted_at = (string) ($request['contacted_at'] ?? '');
        ?>
        <article class="elev8-waitlist-customer-card <?php echo self::is_follow_up_due($request) ? 'is-follow-up-due' : ''; ?>">
            <div class="elev8-waitlist-customer-main">
                <span class="elev8-waitlist-avatar"><?php echo esc_html(strtoupper(substr((string) ($request['customer_name'] ?? '?'), 0, 1))); ?></span>
                <div>
                    <h3><?php echo esc_html((string) ($request['customer_name'] ?? __('Customer', 'elev8-os'))); ?></h3>
                    <div class="elev8-waitlist-contact-links">
                        <?php if ($email) : ?><a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a><?php endif; ?>
                        <?php if ($phone) : ?><a href="tel:<?php echo esc_attr((string) preg_replace('/[^0-9+]/', '', $phone)); ?>"><?php echo esc_html($phone); ?></a><?php endif; ?>
                    </div>
                    <small><?php echo esc_html(sprintf(__('Added %s', 'elev8-os'), self::date_label((string) ($request['created_at'] ?? '')))); ?></small>
                </div>
            </div>
            <div class="elev8-waitlist-class-info"><span><?php esc_html_e('Requested class', 'elev8-os'); ?></span><strong><?php echo esc_html((string) ($request['opportunity_title'] ?? '')); ?></strong><?php if (!empty($request['category'])) : ?><small><?php echo esc_html((string) $request['category']); ?></small><?php endif; ?></div>
            <div class="elev8-waitlist-seat-info"><span><?php esc_html_e('Seats', 'elev8-os'); ?></span><strong><?php echo esc_html((string) ($request['seats_requested'] ?? 1)); ?></strong></div>

            <form class="elev8-request-editor" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="elev8_os_class_request_update">
                <input type="hidden" name="interest_id" value="<?php echo esc_attr((string) ($request['id'] ?? 0)); ?>">
                <input type="hidden" name="redirect_to" value="<?php echo esc_url($redirect); ?>">
                <?php wp_nonce_field('elev8_os_class_request_update'); ?>
                <label><span><?php esc_html_e('Status', 'elev8-os'); ?></span><select name="crm_status"><?php foreach (Elev8_OS_Opportunity_Gateway::interest_statuses() as $available_status) : ?><option value="<?php echo esc_attr($available_status); ?>" <?php selected($status, $available_status); ?>><?php echo esc_html(ucwords(str_replace('_', ' ', $available_status))); ?></option><?php endforeach; ?></select></label>
                <label><span><?php esc_html_e('Follow-up date', 'elev8-os'); ?></span><input type="date" name="follow_up_date" value="<?php echo esc_attr($follow_up); ?>"></label>
                <label class="elev8-request-notes"><span><?php esc_html_e('Notes', 'elev8-os'); ?></span><textarea name="notes" rows="3"><?php echo esc_textarea($notes); ?></textarea></label>
                <label class="elev8-contact-check"><input type="checkbox" name="mark_contacted_now" value="1"><span><?php esc_html_e('Mark contacted now', 'elev8-os'); ?></span></label>
                <?php if ($contacted_at !== '') : ?><small class="elev8-contacted-at"><?php echo esc_html(sprintf(__('Last contacted: %s', 'elev8-os'), self::date_label($contacted_at))); ?></small><?php endif; ?>
                <button class="elev8-waitlist-primary" type="submit"><?php esc_html_e('Save Request', 'elev8-os'); ?></button>
            </form>

            <form class="elev8-request-delete" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js(__('Remove this customer request?', 'elev8-os')); ?>');">
                <input type="hidden" name="action" value="elev8_os_class_request_delete"><input type="hidden" name="interest_id" value="<?php echo esc_attr((string) ($request['id'] ?? 0)); ?>"><input type="hidden" name="redirect_to" value="<?php echo esc_url($redirect); ?>"><?php wp_nonce_field('elev8_os_class_request_delete'); ?>
                <button class="elev8-waitlist-remove" type="submit"><?php esc_html_e('Remove', 'elev8-os'); ?></button>
            </form>
        </article>
        <?php
    }

    private static function filters(): array {
        return [
            'class_id' => absint($_GET['request_class'] ?? 0),
            'status' => sanitize_key((string) ($_GET['request_status'] ?? '')),
            'search' => sanitize_text_field(wp_unslash((string) ($_GET['request_search'] ?? ''))),
        ];
    }

    private static function filter_rows(array $rows, array $filters): array {
        return array_values(array_filter($rows, static function(array $row) use ($filters): bool {
            if ($filters['class_id'] > 0 && (int) ($row['opportunity_id'] ?? 0) !== $filters['class_id']) { return false; }
            if ($filters['status'] !== '' && (string) ($row['crm_status'] ?? '') !== $filters['status']) { return false; }
            if ($filters['search'] !== '') {
                $haystack = strtolower(implode(' ', [
                    (string) ($row['customer_name'] ?? ''),
                    (string) ($row['customer_email'] ?? ''),
                    (string) ($row['customer_phone'] ?? ''),
                    (string) ($row['opportunity_title'] ?? ''),
                    (string) ($row['notes'] ?? ''),
                ]));
                if (strpos($haystack, strtolower($filters['search'])) === false) { return false; }
            }
            return true;
        }));
    }

    private static function group_requests(array $rows): array {
        $groups = [];
        foreach ($rows as $row) {
            $id = (int) ($row['opportunity_id'] ?? 0);
            if ($id <= 0) { continue; }
            if (!isset($groups[$id])) {
                $price = $row['estimated_price'] ?? '';
                $groups[$id] = [
                    'id' => $id,
                    'title' => (string) ($row['opportunity_title'] ?? ''),
                    'category' => (string) ($row['category'] ?? ''),
                    'people' => 0,
                    'seats' => 0,
                    'potential' => 0.0,
                    'price_available' => is_numeric($price) && (float) $price >= 0,
                    'price' => is_numeric($price) ? (float) $price : 0.0,
                    'last_request' => '',
                ];
            }
            $groups[$id]['people']++;
            $group_seats = max(1, (int) ($row['seats_requested'] ?? 1));
            $groups[$id]['seats'] += $group_seats;
            if ($groups[$id]['price_available']) {
                $groups[$id]['potential'] += $groups[$id]['price'] * $group_seats;
            }
            $created = (string) ($row['created_at'] ?? '');
            if ($created > $groups[$id]['last_request']) { $groups[$id]['last_request'] = $created; }
        }
        usort($groups, static function(array $a, array $b): int {
            if ($a['seats'] === $b['seats']) { return strcmp($b['last_request'], $a['last_request']); }
            return $b['seats'] <=> $a['seats'];
        });
        return $groups;
    }

    private static function potential_revenue(array $rows): array {
        if (!$rows) {
            return ['available' => true, 'value' => 0.0, 'diagnostic' => __('No customer requests have been recorded.', 'elev8-os')];
        }
        $total = 0.0;
        foreach ($rows as $row) {
            $price = $row['estimated_price'] ?? '';
            if (!is_numeric($price)) {
                return ['available' => false, 'value' => null, 'diagnostic' => __('Enter an estimated price on every requested class to calculate potential value.', 'elev8-os')];
            }
            $total += (float) $price * max(1, (int) ($row['seats_requested'] ?? 1));
        }
        return ['available' => true, 'value' => $total, 'diagnostic' => __('Estimated price multiplied by requested seats.', 'elev8-os')];
    }

    private static function follow_up_count(array $rows): int {
        return count(array_filter($rows, static fn(array $row): bool => self::is_follow_up_due($row)));
    }

    private static function is_follow_up_due(array $row): bool {
        $status = (string) ($row['crm_status'] ?? 'new');
        if (in_array($status, ['converted', 'enrolled', 'closed', 'cancelled'], true)) { return false; }
        $date = (string) ($row['follow_up_date'] ?? '');
        if ($date !== '') { return $date <= current_time('Y-m-d'); }
        return in_array($status, ['new', 'waiting'], true);
    }

    private static function duplicate_exists(int $opportunity_id, string $email, string $phone): bool {
        if ($email === '' && $phone === '') { return false; }
        $normalized_phone = preg_replace('/\D+/', '', $phone);
        foreach (Elev8_OS_Opportunity_Gateway::interests($opportunity_id) as $interest) {
            $existing_email = strtolower(sanitize_email((string) ($interest['customer_email'] ?? '')));
            $existing_phone = preg_replace('/\D+/', '', (string) ($interest['customer_phone'] ?? ''));
            if ($email !== '' && $existing_email !== '' && strtolower($email) === $existing_email) { return true; }
            if ($normalized_phone !== '' && $existing_phone !== '' && $normalized_phone === $existing_phone) { return true; }
        }
        return false;
    }

    private static function render_notices(): void {
        if (isset($_GET['class_request_saved'])) { self::notice(__('Class request saved.', 'elev8-os')); }
        if (isset($_GET['class_idea_saved'])) { self::notice(__('Class idea suggested.', 'elev8-os')); }
        if (isset($_GET['class_request_updated'])) { self::notice(__('Customer request updated.', 'elev8-os')); }
        if (isset($_GET['class_request_deleted'])) { self::notice(__('Customer request removed.', 'elev8-os')); }
        if (isset($_GET['class_request_duplicate'])) { self::notice(__('That email or phone number is already attached to this class request.', 'elev8-os'), true); }
        if (isset($_GET['class_request_error'])) { self::notice(__('The class request could not be saved. Please verify the required fields and try again.', 'elev8-os'), true); }
    }

    private static function metric(string $label, string $value, string $description): void {
        echo '<article class="elev8-waitlist-metric"><span>' . esc_html($label) . '</span><strong>' . esc_html($value) . '</strong><p>' . esc_html($description) . '</p></article>';
    }

    private static function money(float $value): string {
        if (function_exists('wp_currency_format')) {
            return wp_currency_format($value);
        }
        return '$' . number_format_i18n($value, 2);
    }

    private static function date_label(string $value): string {
        if ($value === '') { return __('Unavailable', 'elev8-os'); }
        $timestamp = strtotime($value);
        return $timestamp ? wp_date(get_option('date_format'), $timestamp) : $value;
    }

    private static function notice(string $message, bool $error = false): void {
        echo '<div class="elev8-waitlist-notice' . ($error ? ' is-error' : '') . '" role="status">' . esc_html($message) . '</div>';
    }

    private static function authorize(string $action): void {
        if (!is_user_logged_in()) { auth_redirect(); }
        check_admin_referer($action);
    }

    /** Resolve the current teacher through the central identity service. */
    private static function resolve_teacher_id(WP_User $user): int {
        $artist = Elev8_OS_Identity_Service::artist_for_user($user);
        return is_array($artist) ? absint($artist['id'] ?? 0) : 0;
    }

    private static function verify_teacher_scope(int $teacher_id): void {
        if (current_user_can('manage_options')) { return; }
        $mapped = self::resolve_teacher_id(wp_get_current_user());
        if ($teacher_id <= 0 || $mapped !== $teacher_id) {
            wp_die(esc_html__('You do not have permission to manage this teacher record.', 'elev8-os'));
        }
    }

    private static function verify_opportunity_access(int $opportunity_id): void {
        if (current_user_can('manage_options')) { return; }
        $opportunity = Elev8_OS_Opportunity_Gateway::get($opportunity_id);
        $mapped = self::resolve_teacher_id(wp_get_current_user());
        if (!$opportunity || (int) ($opportunity['teacher_id'] ?? 0) !== $mapped) {
            wp_die(esc_html__('You do not have permission to manage this class request.', 'elev8-os'));
        }
    }

    private static function redirect(string $flag): void {
        $url = esc_url_raw(wp_unslash((string) ($_POST['redirect_to'] ?? '')));
        if (!$url) {
            $url = class_exists('Elev8_OS_Portal_Page_Manager') ? Elev8_OS_Portal_Page_Manager::get_url('waitlist') : home_url('/');
        }
        wp_safe_redirect(add_query_arg($flag, 1, $url));
        exit;
    }

    private static function record(int $opportunity_id, string $type, string $label, string $details = '', int $interest_id = 0): void {
        Elev8_OS_Activity_Service::record_opportunity($opportunity_id, $type, $label, $details, $interest_id);
    }
}

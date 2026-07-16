<?php
/** Class Demand Manager UI built on the Opportunity Engine. */
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Class_Demand_Manager_Module {
    private const SLUG = 'elev8-class-demand';

    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'admin_menu'], 35);
        add_action('admin_init', ['Elev8_OS_Opportunity_Service', 'maybe_upgrade']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'assets']);
        add_action('admin_post_elev8_os_save_opportunity', [__CLASS__, 'save_opportunity']);
        add_action('admin_post_elev8_os_add_interest', [__CLASS__, 'add_interest']);
        add_action('admin_post_elev8_os_delete_opportunity', [__CLASS__, 'delete_opportunity']);
        add_action('admin_post_elev8_os_delete_interest', [__CLASS__, 'delete_interest']);
        add_action('admin_post_elev8_os_update_interest', [__CLASS__, 'update_interest']);
    }

    public static function admin_menu(): void {
        add_submenu_page(
            'elev8-os',
            __('Class Demand Manager', 'elev8-os'),
            __('Class Demand', 'elev8-os'),
            'manage_options',
            self::SLUG,
            [__CLASS__, 'render']
        );
    }

    public static function assets(string $hook): void {
        if ($hook !== 'elev8-os_page_' . self::SLUG) { return; }
        wp_enqueue_style(
            'elev8-os-class-demand',
            ELEV8_OS_URL . 'assets/css/class-demand-manager.css',
            [],
            ELEV8_OS_VERSION
        );
    }

    public static function save_opportunity(): void {
        self::authorize('elev8_os_save_opportunity');
        $id = Elev8_OS_Opportunity_Service::save_opportunity(wp_unslash($_POST));
        $args = ['page' => self::SLUG, 'saved' => 1];
        if ($id > 0) { $args['opportunity_id'] = $id; }
        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    public static function add_interest(): void {
        self::authorize('elev8_os_add_interest');
        $opportunity_id = absint($_POST['opportunity_id'] ?? 0);
        $interest_id = Elev8_OS_Opportunity_Service::add_interest(wp_unslash($_POST));
        $args = [
            'page' => self::SLUG,
            'opportunity_id' => $opportunity_id,
        ];
        if ($interest_id > 0) {
            $args['interest_saved'] = 1;
        } else {
            $args['interest_error'] = 1;
        }
        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    public static function delete_opportunity(): void {
        self::authorize('elev8_os_delete_opportunity');
        Elev8_OS_Opportunity_Service::delete_opportunity(absint($_POST['opportunity_id'] ?? 0));
        wp_safe_redirect(add_query_arg(['page' => self::SLUG, 'deleted' => 1], admin_url('admin.php')));
        exit;
    }

    public static function delete_interest(): void {
        self::authorize('elev8_os_delete_interest');
        $opportunity_id = absint($_POST['opportunity_id'] ?? 0);
        Elev8_OS_Opportunity_Service::delete_interest(absint($_POST['interest_id'] ?? 0));
        wp_safe_redirect(add_query_arg([
            'page' => self::SLUG,
            'opportunity_id' => $opportunity_id,
            'interest_deleted' => 1,
        ], admin_url('admin.php')));
        exit;
    }


    public static function update_interest(): void {
        self::authorize('elev8_os_update_interest');
        $opportunity_id = absint($_POST['opportunity_id'] ?? 0);
        Elev8_OS_Opportunity_Service::update_interest(wp_unslash($_POST));
        wp_safe_redirect(add_query_arg([
            'page' => self::SLUG,
            'opportunity_id' => $opportunity_id,
            'interest_updated' => 1,
        ], admin_url('admin.php')));
        exit;
    }

    private static function authorize(string $action): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'elev8-os'));
        }
        check_admin_referer($action);
    }

    public static function render(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to view this page.', 'elev8-os'));
        }

        $id = absint($_GET['opportunity_id'] ?? 0);
        $current = $id > 0 ? Elev8_OS_Opportunity_Service::get($id) : null;
        $report = Elev8_OS_Business_Intelligence::get_opportunity_report();

        if ($id > 0 && !$current) {
            self::notice(__('That opportunity is unavailable or may have been deleted.', 'elev8-os'), 'error');
        }

        if ($current) {
            self::render_detail($current, Elev8_OS_Opportunity_Service::interests($id));
            return;
        }

        self::render_dashboard($report);
    }

    private static function render_dashboard(array $report): void {
        ?>
        <div class="wrap elev8-demand">
            <?php self::header(__('Class Demand Manager', 'elev8-os'), __('Capture demand before a class exists. All totals come from the Opportunity Engine.', 'elev8-os')); ?>
            <?php self::render_notices(); ?>

            <section class="elev8-demand__metrics">
                <?php self::metric(__('Class Ideas', 'elev8-os'), $report['metrics']['opportunity_count']); ?>
                <?php self::metric(__('People Interested', 'elev8-os'), $report['metrics']['people_waiting']); ?>
                <?php self::metric(__('Seats Requested', 'elev8-os'), $report['metrics']['seats_waiting']); ?>
                <?php self::metric(__('Potential Revenue', 'elev8-os'), $report['metrics']['potential_revenue']); ?>
                <?php self::metric(__('Need Teachers', 'elev8-os'), $report['metrics']['classes_without_teacher']); ?>
            </section>

            <div class="elev8-demand__layout">
                <section class="elev8-demand__panel">
                    <h2><?php esc_html_e('Add a New Class Idea', 'elev8-os'); ?></h2>
                    <p class="description"><?php esc_html_e('Create the opportunity first. Customer interest, planning, and teacher management are handled from its detail page.', 'elev8-os'); ?></p>
                    <?php self::opportunity_form(null); ?>
                </section>
                <section class="elev8-demand__panel">
                    <h2><?php esc_html_e('Demand Pipeline', 'elev8-os'); ?></h2>
                    <p class="description"><?php esc_html_e('Open any class idea to manage the full opportunity record.', 'elev8-os'); ?></p>
                    <?php self::pipeline($report['opportunities']); ?>
                </section>
            </div>
        </div>
        <?php
    }

    private static function render_detail(array $opportunity, array $interests): void {
        $people = count($interests);
        $seats = 0;
        foreach ($interests as $interest) { $seats += (int) ($interest['seats_requested'] ?? 0); }

        $has_price = $opportunity['estimated_price'] !== null && $opportunity['estimated_price'] !== '';
        $potential = $has_price ? ((float) $opportunity['estimated_price'] * $seats) : null;
        $teacher_ready = empty($opportunity['teacher_needed']) || !empty($opportunity['teacher_id']) || trim((string) $opportunity['teacher_contact']) !== '';
        $list_url = add_query_arg(['page' => self::SLUG], admin_url('admin.php'));
        ?>
        <div class="wrap elev8-demand elev8-opportunity-detail">
            <p class="elev8-demand__back"><a href="<?php echo esc_url($list_url); ?>">&larr; <?php esc_html_e('Back to Class Demand', 'elev8-os'); ?></a></p>
            <header class="elev8-demand__header elev8-demand__header--detail">
                <div>
                    <p class="elev8-demand__eyebrow"><?php echo esc_html(sprintf(__('Opportunity #%d', 'elev8-os'), (int) $opportunity['id'])); ?></p>
                    <h1><?php echo esc_html((string) $opportunity['title']); ?></h1>
                    <p><?php echo esc_html(self::status_label((string) $opportunity['status'])); ?><?php if (!empty($opportunity['category'])) : ?> &middot; <?php echo esc_html((string) $opportunity['category']); ?><?php endif; ?></p>
                </div>
                <div class="elev8-demand__header-actions">
                    <span class="elev8-demand__status"><?php echo esc_html(self::status_label((string) $opportunity['status'])); ?></span>
                    <a class="button" href="#elev8-opportunity-edit"><?php esc_html_e('Edit Opportunity', 'elev8-os'); ?></a>
                </div>
            </header>
            <?php self::render_notices(); ?>

            <section class="elev8-demand__metrics elev8-demand__metrics--detail">
                <?php self::simple_metric(__('People Interested', 'elev8-os'), (string) $people, __('Customer interest records attached to this opportunity.', 'elev8-os')); ?>
                <?php self::simple_metric(__('Seats Requested', 'elev8-os'), (string) $seats, __('Total requested seats for this opportunity.', 'elev8-os')); ?>
                <?php self::simple_metric(__('Potential Revenue', 'elev8-os'), $potential === null ? __('Unavailable', 'elev8-os') : self::money($potential), $potential === null ? __('Add an estimated price to calculate potential revenue.', 'elev8-os') : __('Estimated price multiplied by requested seats.', 'elev8-os')); ?>
                <?php self::simple_metric(__('Teacher', 'elev8-os'), $teacher_ready ? __('Ready', 'elev8-os') : __('Needed', 'elev8-os'), $teacher_ready ? __('A teacher is assigned, identified, or not required.', 'elev8-os') : __('This opportunity is marked as needing a teacher.', 'elev8-os')); ?>
            </section>

            <div class="elev8-demand__detail-grid">
                <section class="elev8-demand__panel elev8-demand__panel--summary">
                    <h2><?php esc_html_e('Opportunity Summary', 'elev8-os'); ?></h2>
                    <?php self::summary($opportunity, $potential); ?>
                </section>
                <section class="elev8-demand__panel elev8-demand__panel--action">
                    <h2><?php esc_html_e('Next Action', 'elev8-os'); ?></h2>
                    <?php self::next_action($opportunity, $people, $has_price, $teacher_ready); ?>
                    <button class="button button-primary" type="button" disabled aria-disabled="true"><?php esc_html_e('Convert to Amelia — Planned', 'elev8-os'); ?></button>
                    <p class="description"><?php esc_html_e('Conversion remains disabled until Amelia class creation, instructor validation, notifications, and rollback are implemented safely.', 'elev8-os'); ?></p>
                </section>
            </div>

            <div class="elev8-demand__detail-grid elev8-demand__detail-grid--customers">
                <section class="elev8-demand__panel">
                    <h2><?php esc_html_e('Add Customer Interest', 'elev8-os'); ?></h2>
                    <?php self::interest_form($opportunity); ?>
                </section>
                <section class="elev8-demand__panel">
                    <h2><?php esc_html_e('Interested Customers', 'elev8-os'); ?></h2>
                    <?php self::interest_table($interests); ?>
                </section>
            </div>

            <section id="elev8-opportunity-edit" class="elev8-demand__panel elev8-demand__panel--edit">
                <h2><?php esc_html_e('Edit Opportunity', 'elev8-os'); ?></h2>
                <p class="description"><?php esc_html_e('Update planning, teacher, pricing, supplies, and internal notes from one trusted record.', 'elev8-os'); ?></p>
                <?php self::opportunity_form($opportunity); ?>
            </section>
        </div>
        <?php
    }

    private static function header(string $title, string $description): void {
        ?>
        <header class="elev8-demand__header">
            <div>
                <p class="elev8-demand__eyebrow"><?php echo esc_html('Elev8 OS ' . ELEV8_OS_VERSION); ?></p>
                <h1><?php echo esc_html($title); ?></h1>
                <p><?php echo esc_html($description); ?></p>
            </div>
        </header>
        <?php
    }

    private static function render_notices(): void {
        if (isset($_GET['saved'])) { self::notice(__('Opportunity saved.', 'elev8-os')); }
        if (isset($_GET['interest_saved'])) { self::notice(__('Customer interest saved.', 'elev8-os')); }
        if (isset($_GET['interest_error'])) { self::notice(__('Customer interest could not be saved. Reload this page once and try again. If it still fails, check Elev8 OS System Status.', 'elev8-os'), 'error'); }
        if (isset($_GET['deleted'])) { self::notice(__('Class idea and its customer interest records were deleted.', 'elev8-os')); }
        if (isset($_GET['interest_deleted'])) { self::notice(__('Customer interest record deleted.', 'elev8-os')); }
        if (isset($_GET['interest_updated'])) { self::notice(__('Customer follow-up updated.', 'elev8-os')); }
    }

    private static function notice(string $message, string $type = 'success'): void {
        echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
    }

    private static function metric(string $label, array $metric): void {
        $available = !empty($metric['available']);
        $value = __('Unavailable', 'elev8-os');
        if ($available) {
            $value = $metric['format'] === 'currency'
                ? self::money((float) $metric['value'])
                : number_format_i18n((float) $metric['value']);
        }
        self::simple_metric($label, $value, (string) ($metric['diagnostic'] ?? ''));
    }

    private static function simple_metric(string $label, string $value, string $diagnostic): void {
        echo '<article class="elev8-demand__metric"><span>' . esc_html($label) . '</span><strong>' . esc_html($value) . '</strong><small>' . esc_html($diagnostic) . '</small></article>';
    }

    private static function opportunity_form(?array $o): void {
        $o = $o ?: [];
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="elev8-demand__form">
            <input type="hidden" name="action" value="elev8_os_save_opportunity">
            <input type="hidden" name="id" value="<?php echo esc_attr((string) ($o['id'] ?? 0)); ?>">
            <?php wp_nonce_field('elev8_os_save_opportunity'); ?>

            <label class="wide"><?php esc_html_e('Class name', 'elev8-os'); ?><input required maxlength="190" name="title" value="<?php echo esc_attr((string) ($o['title'] ?? '')); ?>"></label>
            <label><?php esc_html_e('Category', 'elev8-os'); ?><input name="category" value="<?php echo esc_attr((string) ($o['category'] ?? '')); ?>"></label>
            <label><?php esc_html_e('Status', 'elev8-os'); ?><select name="status"><?php foreach (Elev8_OS_Opportunity_Service::statuses() as $status) : ?><option value="<?php echo esc_attr($status); ?>" <?php selected($o['status'] ?? 'idea', $status); ?>><?php echo esc_html(self::status_label($status)); ?></option><?php endforeach; ?></select></label>
            <label><?php esc_html_e('Estimated price per seat', 'elev8-os'); ?><input type="number" min="0" step="0.01" name="estimated_price" value="<?php echo esc_attr((string) ($o['estimated_price'] ?? '')); ?>"></label>
            <label><?php esc_html_e('Estimated duration in hours', 'elev8-os'); ?><input type="number" min="0" step="0.25" name="estimated_duration" value="<?php echo esc_attr((string) ($o['estimated_duration'] ?? '')); ?>"></label>
            <label><?php esc_html_e('Preferred day', 'elev8-os'); ?><input name="preferred_day" value="<?php echo esc_attr((string) ($o['preferred_day'] ?? '')); ?>"></label>
            <label><?php esc_html_e('Preferred time', 'elev8-os'); ?><input name="preferred_time" value="<?php echo esc_attr((string) ($o['preferred_time'] ?? '')); ?>"></label>
            <label><?php esc_html_e('Difficulty', 'elev8-os'); ?><input name="difficulty" value="<?php echo esc_attr((string) ($o['difficulty'] ?? '')); ?>"></label>
            <label><?php esc_html_e('Interview status', 'elev8-os'); ?><input name="interview_status" value="<?php echo esc_attr((string) ($o['interview_status'] ?? '')); ?>"></label>
            <label class="wide check"><input type="checkbox" name="teacher_needed" value="1" <?php checked(!empty($o['teacher_needed'])); ?>> <?php esc_html_e('Teacher needed', 'elev8-os'); ?></label>
            <label><?php esc_html_e('Assigned teacher ID', 'elev8-os'); ?><input type="number" min="0" name="teacher_id" value="<?php echo esc_attr((string) ($o['teacher_id'] ?? 0)); ?>"></label>
            <label><?php esc_html_e('Teacher contact', 'elev8-os'); ?><input name="teacher_contact" value="<?php echo esc_attr((string) ($o['teacher_contact'] ?? '')); ?>"></label>
            <label class="wide"><?php esc_html_e('Description', 'elev8-os'); ?><textarea name="description" rows="3"><?php echo esc_textarea((string) ($o['description'] ?? '')); ?></textarea></label>
            <label class="wide"><?php esc_html_e('Supplies needed', 'elev8-os'); ?><textarea name="supplies_needed" rows="2"><?php echo esc_textarea((string) ($o['supplies_needed'] ?? '')); ?></textarea></label>
            <label class="wide"><?php esc_html_e('Internal notes', 'elev8-os'); ?><textarea name="internal_notes" rows="4"><?php echo esc_textarea((string) ($o['internal_notes'] ?? '')); ?></textarea></label>
            <p class="wide elev8-demand__form-actions"><button class="button button-primary" type="submit"><?php echo empty($o['id']) ? esc_html__('Create Opportunity', 'elev8-os') : esc_html__('Save Opportunity', 'elev8-os'); ?></button></p>
        </form>

        <?php if (!empty($o['id'])) : ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="elev8-demand__delete-form" onsubmit="return confirm('<?php echo esc_js(__('Delete this opportunity and all customer interest records? This cannot be undone.', 'elev8-os')); ?>');">
                <input type="hidden" name="action" value="elev8_os_delete_opportunity">
                <input type="hidden" name="opportunity_id" value="<?php echo esc_attr((string) $o['id']); ?>">
                <?php wp_nonce_field('elev8_os_delete_opportunity'); ?>
                <button class="button button-link-delete" type="submit"><?php esc_html_e('Delete Opportunity', 'elev8-os'); ?></button>
            </form>
        <?php endif;
    }

    private static function pipeline(array $items): void {
        if (!$items) {
            echo '<p>' . esc_html__('No class ideas yet. Add the first opportunity.', 'elev8-os') . '</p>';
            return;
        }

        echo '<div class="elev8-demand__table-wrap"><table class="widefat striped"><thead><tr><th>' . esc_html__('Class', 'elev8-os') . '</th><th>' . esc_html__('Status', 'elev8-os') . '</th><th>' . esc_html__('People', 'elev8-os') . '</th><th>' . esc_html__('Seats', 'elev8-os') . '</th><th>' . esc_html__('Potential', 'elev8-os') . '</th><th>' . esc_html__('Action', 'elev8-os') . '</th></tr></thead><tbody>';
        foreach ($items as $item) {
            $url = add_query_arg(['page' => self::SLUG, 'opportunity_id' => $item['id']], admin_url('admin.php'));
            $potential = $item['potential_revenue'] === null ? __('Unavailable', 'elev8-os') : self::money((float) $item['potential_revenue']);
            echo '<tr><td><a href="' . esc_url($url) . '"><strong>' . esc_html((string) $item['title']) . '</strong></a><br><small>' . esc_html((string) $item['category']) . '</small></td><td>' . esc_html(self::status_label((string) $item['status'])) . '</td><td>' . esc_html((string) $item['people_waiting']) . '</td><td>' . esc_html((string) $item['seats_waiting']) . '</td><td>' . esc_html($potential) . '</td><td><a class="button button-small" href="' . esc_url($url) . '">' . esc_html__('Open', 'elev8-os') . '</a></td></tr>';
        }
        echo '</tbody></table></div>';
    }

    private static function interest_form(array $o): void {
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="elev8-demand__form">
            <input type="hidden" name="action" value="elev8_os_add_interest">
            <input type="hidden" name="opportunity_id" value="<?php echo esc_attr((string) $o['id']); ?>">
            <?php wp_nonce_field('elev8_os_add_interest'); ?>
            <label class="wide"><?php esc_html_e('Customer name', 'elev8-os'); ?><input required name="customer_name"></label>
            <label><?php esc_html_e('Email', 'elev8-os'); ?><input type="email" name="customer_email"></label>
            <label><?php esc_html_e('Phone', 'elev8-os'); ?><input name="customer_phone"></label>
            <label><?php esc_html_e('Seats requested', 'elev8-os'); ?><input type="number" min="1" value="1" name="seats_requested"></label>
            <label><?php esc_html_e('Source', 'elev8-os'); ?><input name="source" value="admin"></label>
            <label><?php esc_html_e('Preferred days', 'elev8-os'); ?><input name="preferred_days"></label>
            <label><?php esc_html_e('Preferred times', 'elev8-os'); ?><input name="preferred_times"></label>
            <label class="wide"><?php esc_html_e('Notes', 'elev8-os'); ?><textarea name="notes" rows="3"></textarea></label>
            <p class="wide"><button class="button button-primary" type="submit"><?php esc_html_e('Add Interest', 'elev8-os'); ?></button></p>
        </form>
        <?php
    }

    private static function interest_table(array $items): void {
        if (!$items) {
            echo '<p>' . esc_html__('No customer interest has been recorded for this opportunity.', 'elev8-os') . '</p>';
            return;
        }

        echo '<div class="elev8-demand__table-wrap"><table class="widefat striped"><thead><tr><th>' . esc_html__('Customer', 'elev8-os') . '</th><th>' . esc_html__('Seats', 'elev8-os') . '</th><th>' . esc_html__('CRM Status', 'elev8-os') . '</th><th>' . esc_html__('Follow Up', 'elev8-os') . '</th><th>' . esc_html__('Notes', 'elev8-os') . '</th><th>' . esc_html__('Actions', 'elev8-os') . '</th></tr></thead><tbody>';
        foreach ($items as $item) {
            $email = trim((string) $item['customer_email']);
            $phone = trim((string) $item['customer_phone']);
            echo '<tr><td><strong>' . esc_html((string) $item['customer_name']) . '</strong><br>';
            if ($email !== '') { echo '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a><br>'; }
            if ($phone !== '') { echo '<a href="tel:' . esc_attr(preg_replace('/[^0-9+]/', '', $phone)) . '">' . esc_html($phone) . '</a>'; }
            echo '</td><td>' . esc_html((string) $item['seats_requested']) . '</td><td colspan="3">';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="elev8-interest-crm-form">';
            echo '<input type="hidden" name="action" value="elev8_os_update_interest"><input type="hidden" name="interest_id" value="' . esc_attr((string) $item['id']) . '"><input type="hidden" name="opportunity_id" value="' . esc_attr((string) $item['opportunity_id']) . '">';
            wp_nonce_field('elev8_os_update_interest');
            echo '<label><span class="screen-reader-text">' . esc_html__('CRM status', 'elev8-os') . '</span><select name="crm_status">';
            foreach (Elev8_OS_Opportunity_Service::interest_statuses() as $status) {
                echo '<option value="' . esc_attr($status) . '" ' . selected((string) ($item['crm_status'] ?? 'new'), $status, false) . '>' . esc_html(self::status_label($status)) . '</option>';
            }
            echo '</select></label> ';
            echo '<label><span class="screen-reader-text">' . esc_html__('Follow-up date', 'elev8-os') . '</span><input type="date" name="follow_up_date" value="' . esc_attr((string) ($item['follow_up_date'] ?? '')) . '"></label> ';
            echo '<label class="elev8-interest-notes"><span class="screen-reader-text">' . esc_html__('Notes', 'elev8-os') . '</span><textarea name="notes" rows="2">' . esc_textarea((string) $item['notes']) . '</textarea></label> ';
            echo '<label><input type="checkbox" name="mark_contacted" value="1"> ' . esc_html__('Mark contacted now', 'elev8-os') . '</label> ';
            echo '<button class="button button-small" type="submit">' . esc_html__('Save Follow-up', 'elev8-os') . '</button>';
            if (!empty($item['last_contacted_at'])) { echo '<small class="elev8-interest-last-contact">' . esc_html(sprintf(__('Last contacted: %s', 'elev8-os'), mysql2date(get_option('date_format') . ' ' . get_option('time_format'), (string) $item['last_contacted_at']))) . '</small>'; }
            echo '</form></td><td>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" onsubmit="return confirm(&quot;' . esc_js(__('Delete this customer interest record?', 'elev8-os')) . '&quot;);">';
            echo '<input type="hidden" name="action" value="elev8_os_delete_interest"><input type="hidden" name="interest_id" value="' . esc_attr((string) $item['id']) . '"><input type="hidden" name="opportunity_id" value="' . esc_attr((string) $item['opportunity_id']) . '">';
            wp_nonce_field('elev8_os_delete_interest');
            echo '<button class="button button-small button-link-delete" type="submit">' . esc_html__('Delete', 'elev8-os') . '</button></form></td></tr>';
        }
        echo '</tbody></table></div>';
    }

    private static function summary(array $o, ?float $potential): void {
        $rows = [
            __('Status', 'elev8-os') => self::status_label((string) $o['status']),
            __('Estimated price', 'elev8-os') => ($o['estimated_price'] === null || $o['estimated_price'] === '') ? __('Unavailable', 'elev8-os') : self::money((float) $o['estimated_price']),
            __('Potential revenue', 'elev8-os') => $potential === null ? __('Unavailable', 'elev8-os') : self::money($potential),
            __('Duration', 'elev8-os') => ($o['estimated_duration'] === null || $o['estimated_duration'] === '') ? __('Unavailable', 'elev8-os') : sprintf(__('%s hours', 'elev8-os'), number_format_i18n((float) $o['estimated_duration'], 2)),
            __('Preferred schedule', 'elev8-os') => trim((string) $o['preferred_day'] . ' ' . (string) $o['preferred_time']) ?: __('Unavailable', 'elev8-os'),
            __('Difficulty', 'elev8-os') => trim((string) $o['difficulty']) ?: __('Unavailable', 'elev8-os'),
            __('Teacher', 'elev8-os') => self::teacher_label($o),
            __('Last updated', 'elev8-os') => mysql2date(get_option('date_format') . ' ' . get_option('time_format'), (string) $o['updated_at']),
        ];
        echo '<dl class="elev8-demand__summary">';
        foreach ($rows as $label => $value) { echo '<div><dt>' . esc_html($label) . '</dt><dd>' . esc_html($value) . '</dd></div>'; }
        echo '</dl>';
        if (!empty($o['description'])) { echo '<h3>' . esc_html__('Description', 'elev8-os') . '</h3><p>' . nl2br(esc_html((string) $o['description'])) . '</p>'; }
        if (!empty($o['supplies_needed'])) { echo '<h3>' . esc_html__('Supplies Needed', 'elev8-os') . '</h3><p>' . nl2br(esc_html((string) $o['supplies_needed'])) . '</p>'; }
        if (!empty($o['internal_notes'])) { echo '<h3>' . esc_html__('Internal Notes', 'elev8-os') . '</h3><p>' . nl2br(esc_html((string) $o['internal_notes'])) . '</p>'; }
    }

    private static function next_action(array $o, int $people, bool $has_price, bool $teacher_ready): void {
        $message = __('Continue collecting customer interest.', 'elev8-os');
        if (!$has_price) {
            $message = __('Add an estimated price so Business Intelligence can calculate potential revenue.', 'elev8-os');
        } elseif (!$teacher_ready) {
            $message = __('Recruit or identify a teacher before scheduling this opportunity.', 'elev8-os');
        } elseif ($people === 0) {
            $message = __('Add or collect the first customer interest record to verify demand.', 'elev8-os');
        } elseif (in_array((string) $o['status'], ['idea', 'research'], true)) {
            $message = __('Review demand and move this opportunity into planning when the business is ready.', 'elev8-os');
        } elseif ((string) $o['status'] === 'planning') {
            $message = __('Confirm the teacher, price, duration, and schedule before creating the Amelia class.', 'elev8-os');
        }
        echo '<p class="elev8-demand__recommendation">' . esc_html($message) . '</p>';
    }

    private static function teacher_label(array $o): string {
        if (!empty($o['teacher_id'])) { return sprintf(__('Assigned ID %d', 'elev8-os'), (int) $o['teacher_id']); }
        if (trim((string) $o['teacher_contact']) !== '') { return (string) $o['teacher_contact']; }
        return !empty($o['teacher_needed']) ? __('Needed', 'elev8-os') : __('Not required', 'elev8-os');
    }

    private static function status_label(string $status): string {
        return ucwords(str_replace('_', ' ', $status));
    }

    private static function money(float $value): string {
        return function_exists('wc_price')
            ? wp_strip_all_tags(wc_price($value))
            : '$' . number_format_i18n($value, 2);
    }
}

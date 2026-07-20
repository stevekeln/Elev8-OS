<?php
/**
 * Elev8 OS Bingo Reservations.
 *
 * Public reservation form plus an owner-facing reservation queue.
 *
 * @package Elev8OS
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Elev8_OS_Bingo_Reservations_Module {
    private const POST_TYPE = 'elev8_bingo_res';
    private const SHORTCODE = 'elev8_bingo_reservation_form';
    private const ADMIN_SLUG = 'elev8-bingo-reservations';

    public static function init(): void {
        add_action('init', [__CLASS__, 'register_post_type']);
        add_shortcode(self::SHORTCODE, [__CLASS__, 'shortcode']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'frontend_assets']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'admin_assets']);
        add_action('admin_menu', [__CLASS__, 'admin_menu'], 27);
        add_action('admin_post_nopriv_elev8_os_submit_bingo_reservation', [__CLASS__, 'submit']);
        add_action('admin_post_elev8_os_submit_bingo_reservation', [__CLASS__, 'submit']);
        add_action('admin_post_elev8_os_update_bingo_reservation', [__CLASS__, 'update_status']);
    }

    public static function register_post_type(): void {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name' => __('Bingo Reservations', 'elev8-os'),
                'singular_name' => __('Bingo Reservation', 'elev8-os'),
            ],
            'public' => false,
            'show_ui' => false,
            'show_in_menu' => false,
            'supports' => ['title'],
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ]);
    }

    public static function frontend_assets(): void {
        if (!is_singular()) {
            return;
        }

        global $post;
        if (!$post || !has_shortcode((string) $post->post_content, self::SHORTCODE)) {
            return;
        }

        wp_enqueue_style(
            'elev8-os-bingo-reservations',
            ELEV8_OS_URL . 'assets/css/bingo-reservations.css',
            [],
            ELEV8_OS_VERSION
        );
    }

    public static function admin_assets(string $hook): void {
        if ($hook !== 'elev8-os_page_' . self::ADMIN_SLUG) {
            return;
        }

        wp_enqueue_style(
            'elev8-os-bingo-reservations',
            ELEV8_OS_URL . 'assets/css/bingo-reservations.css',
            [],
            ELEV8_OS_VERSION
        );
    }

    public static function admin_menu(): void {
        add_submenu_page(
            'elev8-os',
            __('Bingo Reservations', 'elev8-os'),
            __('Bingo Reservations', 'elev8-os'),
            Elev8_OS_Access_Service::capability('manage_bingo'),
            self::ADMIN_SLUG,
            [__CLASS__, 'render_admin']
        );
    }

    public static function shortcode(): string {
        $state = sanitize_key((string) ($_GET['bingo_reservation'] ?? ''));
        $dates = self::upcoming_bingo_dates(8);

        ob_start();
        ?>
        <div class="elev8-bingo-form-wrap">
            <?php if ($state === 'thanks') : ?>
                <div class="elev8-bingo-notice is-success" role="status">
                    <h2><?php esc_html_e('Your seats are reserved!', 'elev8-os'); ?></h2>
                    <p><?php esc_html_e('We added your reservation to Elev8 OS. Please arrive by 6:00 pm so the event team can prepare your table.', 'elev8-os'); ?></p>
                </div>
            <?php elseif ($state === 'error') : ?>
                <div class="elev8-bingo-notice is-error" role="alert">
                    <h2><?php esc_html_e('We could not save your reservation.', 'elev8-os'); ?></h2>
                    <p><?php esc_html_e('Please review the required information and try again.', 'elev8-os'); ?></p>
                </div>
            <?php endif; ?>

            <form class="elev8-bingo-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('elev8_bingo_reservation_submit', 'elev8_bingo_reservation_nonce'); ?>
                <input type="hidden" name="action" value="elev8_os_submit_bingo_reservation">
                <input class="elev8-bingo-honeypot" type="text" name="website" value="" tabindex="-1" autocomplete="off" aria-hidden="true">

                <div class="elev8-bingo-two">
                    <label>
                        <?php esc_html_e('Your name', 'elev8-os'); ?> <b>*</b>
                        <input type="text" name="guest_name" autocomplete="name" required>
                    </label>
                    <label>
                        <?php esc_html_e('Email', 'elev8-os'); ?> <b>*</b>
                        <input type="email" name="guest_email" autocomplete="email" required>
                    </label>
                </div>

                <div class="elev8-bingo-two">
                    <label>
                        <?php esc_html_e('Phone', 'elev8-os'); ?> <b>*</b>
                        <input type="tel" name="guest_phone" autocomplete="tel" required>
                    </label>
                    <label>
                        <?php esc_html_e('Number of people', 'elev8-os'); ?> <b>*</b>
                        <input type="number" name="guest_count" min="1" max="30" value="2" required>
                    </label>
                </div>

                <label>
                    <?php esc_html_e('Bingo night', 'elev8-os'); ?> <b>*</b>
                    <select name="event_date" required>
                        <option value=""><?php esc_html_e('Choose a date', 'elev8-os'); ?></option>
                        <?php foreach ($dates as $date_value => $date_label) : ?>
                            <option value="<?php echo esc_attr($date_value); ?>"><?php echo esc_html($date_label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    <?php esc_html_e('Accessibility needs', 'elev8-os'); ?> <span><?php esc_html_e('optional', 'elev8-os'); ?></span>
                    <textarea name="accessibility" rows="3" placeholder="<?php esc_attr_e('Wheelchair seating, mobility needs, hearing needs, or anything else we should prepare for.', 'elev8-os'); ?>"></textarea>
                </label>

                <label>
                    <?php esc_html_e('Special notes', 'elev8-os'); ?> <span><?php esc_html_e('optional', 'elev8-os'); ?></span>
                    <textarea name="notes" rows="3" placeholder="<?php esc_attr_e('Tell us anything helpful about your group.', 'elev8-os'); ?>"></textarea>
                </label>

                <label class="elev8-bingo-consent">
                    <input type="checkbox" name="reminder_consent" value="1" checked>
                    <?php esc_html_e('Keep me updated about upcoming Bingo Nights and other Elev8 Arts events.', 'elev8-os'); ?>
                </label>

                <button class="elev8-bingo-submit" type="submit"><?php esc_html_e('Reserve My Seats', 'elev8-os'); ?></button>
            </form>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    public static function submit(): void {
        $redirect = wp_get_referer() ?: home_url('/bingo-reservations/');

        if (
            !isset($_POST['elev8_bingo_reservation_nonce']) ||
            !wp_verify_nonce(
                sanitize_text_field(wp_unslash($_POST['elev8_bingo_reservation_nonce'])),
                'elev8_bingo_reservation_submit'
            )
        ) {
            wp_die(esc_html__('Security check failed.', 'elev8-os'));
        }

        if (!empty($_POST['website']) || !self::rate_limit()) {
            self::redirect($redirect, 'error');
        }

        $name = sanitize_text_field(wp_unslash($_POST['guest_name'] ?? ''));
        $email = sanitize_email(wp_unslash($_POST['guest_email'] ?? ''));
        $phone = sanitize_text_field(wp_unslash($_POST['guest_phone'] ?? ''));
        $guest_count = absint($_POST['guest_count'] ?? 0);
        $event_date = sanitize_text_field(wp_unslash($_POST['event_date'] ?? ''));
        $accessibility = sanitize_textarea_field(wp_unslash($_POST['accessibility'] ?? ''));
        $notes = sanitize_textarea_field(wp_unslash($_POST['notes'] ?? ''));
        $reminder_consent = !empty($_POST['reminder_consent']) ? '1' : '0';

        if (
            $name === '' ||
            !is_email($email) ||
            $phone === '' ||
            $guest_count < 1 ||
            $guest_count > 30 ||
            !array_key_exists($event_date, self::upcoming_bingo_dates(12))
        ) {
            self::redirect($redirect, 'error');
        }

        $post_id = wp_insert_post([
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'post_title' => sprintf('%s — %s', $name, $event_date),
        ], true);

        if (is_wp_error($post_id)) {
            self::redirect($redirect, 'error');
        }

        $meta = [
            '_elev8_bingo_name' => $name,
            '_elev8_bingo_email' => $email,
            '_elev8_bingo_phone' => $phone,
            '_elev8_bingo_guest_count' => $guest_count,
            '_elev8_bingo_event_date' => $event_date,
            '_elev8_bingo_accessibility' => $accessibility,
            '_elev8_bingo_notes' => $notes,
            '_elev8_bingo_reminder_consent' => $reminder_consent,
            '_elev8_bingo_status' => 'reserved',
            '_elev8_bingo_source' => 'bingo-reservations-page',
        ];

        foreach ($meta as $key => $value) {
            update_post_meta((int) $post_id, $key, $value);
        }

        do_action('elev8_os_bingo_reservation_created', (int) $post_id, $meta);
        self::redirect($redirect, 'thanks');
    }

    public static function render_admin(): void {
        if (!Elev8_OS_Access_Service::user_can('manage_bingo')) {
            wp_die(esc_html__('You do not have permission to view this page.', 'elev8-os'));
        }

        $status_filter = sanitize_key((string) ($_GET['status'] ?? ''));
        $date_filter = sanitize_text_field((string) ($_GET['event_date'] ?? ''));

        $meta_query = [];
        if ($status_filter !== '') {
            $meta_query[] = [
                'key' => '_elev8_bingo_status',
                'value' => $status_filter,
            ];
        }
        if ($date_filter !== '') {
            $meta_query[] = [
                'key' => '_elev8_bingo_event_date',
                'value' => $date_filter,
            ];
        }

        $query = new WP_Query([
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => 100,
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => $meta_query,
        ]);

        $total_people = 0;
        foreach ($query->posts as $reservation) {
            $total_people += (int) get_post_meta($reservation->ID, '_elev8_bingo_guest_count', true);
        }

        ?>
        <div class="wrap elev8-bingo-admin">
            <header class="elev8-bingo-admin-hero">
                <div>
                    <p class="elev8-bingo-eyebrow">ELEV8 OS <?php echo esc_html(ELEV8_OS_VERSION); ?></p>
                    <h1><?php esc_html_e('Bingo Reservations', 'elev8-os'); ?></h1>
                    <p><?php esc_html_e('See every Bingo reservation, expected guest count, contact details, and check-in status.', 'elev8-os'); ?></p>
                </div>
                <a class="button button-primary button-hero" target="_blank" href="<?php echo esc_url(home_url('/bingo-reservations/')); ?>"><?php esc_html_e('Open reservation page', 'elev8-os'); ?></a>
            </header>

            <div class="elev8-bingo-summary">
                <article><strong><?php echo esc_html((string) $query->post_count); ?></strong><span><?php esc_html_e('Reservations shown', 'elev8-os'); ?></span></article>
                <article><strong><?php echo esc_html((string) $total_people); ?></strong><span><?php esc_html_e('Expected guests', 'elev8-os'); ?></span></article>
            </div>

            <form class="elev8-bingo-filters" method="get">
                <input type="hidden" name="page" value="<?php echo esc_attr(self::ADMIN_SLUG); ?>">
                <label><?php esc_html_e('Event date', 'elev8-os'); ?><input type="date" name="event_date" value="<?php echo esc_attr($date_filter); ?>"></label>
                <label><?php esc_html_e('Status', 'elev8-os'); ?>
                    <select name="status">
                        <option value=""><?php esc_html_e('All statuses', 'elev8-os'); ?></option>
                        <?php foreach (self::statuses() as $key => $label) : ?>
                            <option value="<?php echo esc_attr($key); ?>" <?php selected($status_filter, $key); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button class="button button-primary" type="submit"><?php esc_html_e('Filter', 'elev8-os'); ?></button>
            </form>

            <div class="elev8-bingo-table-wrap">
                <table class="widefat striped elev8-bingo-table">
                    <thead><tr>
                        <th><?php esc_html_e('Event', 'elev8-os'); ?></th>
                        <th><?php esc_html_e('Guest', 'elev8-os'); ?></th>
                        <th><?php esc_html_e('People', 'elev8-os'); ?></th>
                        <th><?php esc_html_e('Contact', 'elev8-os'); ?></th>
                        <th><?php esc_html_e('Notes', 'elev8-os'); ?></th>
                        <th><?php esc_html_e('Status', 'elev8-os'); ?></th>
                    </tr></thead>
                    <tbody>
                    <?php if (!$query->have_posts()) : ?>
                        <tr><td colspan="6"><?php esc_html_e('No reservations match these filters.', 'elev8-os'); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ($query->posts as $reservation) :
                            $id = (int) $reservation->ID;
                            $date = (string) get_post_meta($id, '_elev8_bingo_event_date', true);
                            $name = (string) get_post_meta($id, '_elev8_bingo_name', true);
                            $email = (string) get_post_meta($id, '_elev8_bingo_email', true);
                            $phone = (string) get_post_meta($id, '_elev8_bingo_phone', true);
                            $count = (int) get_post_meta($id, '_elev8_bingo_guest_count', true);
                            $accessibility = (string) get_post_meta($id, '_elev8_bingo_accessibility', true);
                            $notes = (string) get_post_meta($id, '_elev8_bingo_notes', true);
                            $status = (string) get_post_meta($id, '_elev8_bingo_status', true);
                            ?>
                            <tr>
                                <td><strong><?php echo esc_html(self::format_date($date)); ?></strong><br><small><?php echo esc_html(get_the_date('', $id)); ?></small></td>
                                <td><strong><?php echo esc_html($name); ?></strong></td>
                                <td><?php echo esc_html((string) $count); ?></td>
                                <td><a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a><br><a href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', $phone)); ?>"><?php echo esc_html($phone); ?></a></td>
                                <td><?php echo $accessibility !== '' ? '<strong>Accessibility:</strong> ' . esc_html($accessibility) . '<br>' : ''; ?><?php echo $notes !== '' ? esc_html($notes) : '<span class="elev8-muted">—</span>'; ?></td>
                                <td>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                        <?php wp_nonce_field('elev8_bingo_update_' . $id, 'elev8_bingo_update_nonce'); ?>
                                        <input type="hidden" name="action" value="elev8_os_update_bingo_reservation">
                                        <input type="hidden" name="reservation_id" value="<?php echo esc_attr((string) $id); ?>">
                                        <select name="reservation_status">
                                            <?php foreach (self::statuses() as $key => $label) : ?>
                                                <option value="<?php echo esc_attr($key); ?>" <?php selected($status ?: 'reserved', $key); ?>><?php echo esc_html($label); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button class="button" type="submit"><?php esc_html_e('Save', 'elev8-os'); ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    public static function update_status(): void {
        if (!Elev8_OS_Access_Service::user_can('manage_bingo')) {
            wp_die(esc_html__('You do not have permission to update reservations.', 'elev8-os'));
        }

        $id = absint($_POST['reservation_id'] ?? 0);
        $nonce = sanitize_text_field(wp_unslash($_POST['elev8_bingo_update_nonce'] ?? ''));
        if (!$id || !wp_verify_nonce($nonce, 'elev8_bingo_update_' . $id)) {
            wp_die(esc_html__('Security check failed.', 'elev8-os'));
        }

        $status = sanitize_key((string) ($_POST['reservation_status'] ?? ''));
        if (!array_key_exists($status, self::statuses())) {
            $status = 'reserved';
        }

        update_post_meta($id, '_elev8_bingo_status', $status);
        wp_safe_redirect(admin_url('admin.php?page=' . self::ADMIN_SLUG));
        exit;
    }

    private static function statuses(): array {
        return [
            'reserved' => __('Reserved', 'elev8-os'),
            'confirmed' => __('Confirmed', 'elev8-os'),
            'checked_in' => __('Checked in', 'elev8-os'),
            'completed' => __('Completed', 'elev8-os'),
            'cancelled' => __('Cancelled', 'elev8-os'),
            'no_show' => __('No show', 'elev8-os'),
        ];
    }

    private static function upcoming_bingo_dates(int $count): array {
        $dates = [];
        $timezone = wp_timezone();
        $today = new DateTimeImmutable('today', $timezone);
        $cursor = $today;

        while (count($dates) < $count) {
            $weekday = (int) $cursor->format('N');
            $day = (int) $cursor->format('j');
            if ($weekday === 5 && (($day >= 1 && $day <= 7) || ($day >= 15 && $day <= 21))) {
                $value = $cursor->format('Y-m-d');
                $dates[$value] = wp_date('l, F j, Y', $cursor->getTimestamp(), $timezone) . ' — 6:00 pm';
            }
            $cursor = $cursor->modify('+1 day');
        }

        return $dates;
    }

    private static function format_date(string $date): string {
        $timestamp = strtotime($date . ' 12:00:00');
        return $timestamp ? wp_date('M j, Y', $timestamp, wp_timezone()) : $date;
    }

    private static function rate_limit(): bool {
        $ip = sanitize_text_field((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        $key = 'elev8_bingo_res_' . md5($ip);
        if (get_transient($key)) {
            return false;
        }
        set_transient($key, 1, 8);
        return true;
    }

    private static function redirect(string $url, string $state): void {
        wp_safe_redirect(add_query_arg('bingo_reservation', $state, $url));
        exit;
    }
}

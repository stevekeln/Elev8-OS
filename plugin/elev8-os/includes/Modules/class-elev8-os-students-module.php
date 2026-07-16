<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Artist-facing student rosters backed by verified Amelia booking data.
 */
final class Elev8_OS_Students_Module {

    private const SHORTCODE = 'elev8_artist_students';
    private const EMPLOYEE_META_KEY = 'elev8_os_amelia_employee_id';

    public static function init(): void {
        add_action('init', [__CLASS__, 'register_shortcode']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    public static function register_shortcode(): void {
        add_shortcode(self::SHORTCODE, [__CLASS__, 'shortcode']);
    }

    public static function enqueue_assets(): void {
        if (!Elev8_OS_Portal_Page_Manager::is_current_page('students')) {
            return;
        }

        wp_enqueue_style('dashicons');
        wp_enqueue_style(
            'elev8-os-students',
            ELEV8_OS_URL . 'assets/css/artist-students.css',
            ['elev8-os-artist-portal'],
            ELEV8_OS_VERSION
        );
    }

    public static function shortcode(): string {
        if (!is_user_logged_in()) {
            return sprintf(
                '<div class="elev8-dashboard-login"><p>%1$s</p><p><a class="button" href="%2$s">%3$s</a></p></div>',
                esc_html__('Please log in to view your students.', 'elev8-os'),
                esc_url(wp_login_url(Elev8_OS_Portal_Page_Manager::get_url('students'))),
                esc_html__('Log In', 'elev8-os')
            );
        }

        $artist = self::find_artist_for_user(wp_get_current_user());
        $appointment_id = isset($_GET['appointment_id']) ? absint($_GET['appointment_id']) : 0;
        $result = $artist
            ? self::load_roster((int) $artist['id'], $appointment_id)
            : self::unavailable_result(__('Your WordPress account is not connected to an Amelia artist.', 'elev8-os'));

        ob_start();
        ?>
        <div class="elev8-artist-dashboard elev8-students-page">
            <?php Elev8_OS_Artist_Portal_Module::render_navigation('students'); ?>

            <header class="elev8-dashboard-header">
                <div>
                    <p class="elev8-eyebrow"><?php esc_html_e('Artist Portal', 'elev8-os'); ?></p>
                    <h1><?php esc_html_e('Students', 'elev8-os'); ?></h1>
                    <p><?php esc_html_e('View verified rosters for classes assigned to you in Amelia.', 'elev8-os'); ?></p>
                </div>
                <span class="elev8-dashboard-badge"><?php esc_html_e('Private roster', 'elev8-os'); ?></span>
            </header>

            <?php if (!$result['available']) : ?>
                <div class="elev8-dashboard-warning"><p><strong><?php esc_html_e('Student information is unavailable.', 'elev8-os'); ?></strong><br><?php echo esc_html($result['reason']); ?></p></div>
            <?php else : ?>
                <section class="elev8-student-class-picker">
                    <label for="elev8-student-class-select"><?php esc_html_e('Choose a class', 'elev8-os'); ?></label>
                    <select id="elev8-student-class-select" onchange="if(this.value){window.location.href=this.value;}">
                        <?php foreach ($result['appointments'] as $appointment) : ?>
                            <option value="<?php echo esc_url(add_query_arg('appointment_id', (int) $appointment['id'], Elev8_OS_Portal_Page_Manager::get_url('students'))); ?>" <?php selected((int) $appointment['id'], (int) $result['selected_id']); ?>>
                                <?php echo esc_html((string) $appointment['label']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </section>

                <?php if ($result['selected_id'] <= 0) : ?>
                    <div class="elev8-student-empty"><span class="dashicons dashicons-groups"></span><h2><?php esc_html_e('No classes were found', 'elev8-os'); ?></h2><p><?php esc_html_e('A roster will appear after Amelia assigns a class to this artist.', 'elev8-os'); ?></p></div>
                <?php else : ?>
                    <section class="elev8-student-summary" aria-label="<?php esc_attr_e('Roster summary', 'elev8-os'); ?>">
                        <?php self::summary_card(__('Students', 'elev8-os'), count($result['students'])); ?>
                        <?php self::summary_card(__('Seats booked', 'elev8-os'), $result['seats_booked']); ?>
                        <?php self::summary_card(__('Confirmed', 'elev8-os'), $result['confirmed']); ?>
                        <?php self::summary_card(__('Pending', 'elev8-os'), $result['pending']); ?>
                    </section>

                    <section class="elev8-student-roster">
                        <div class="elev8-student-roster-heading">
                            <div><p class="elev8-eyebrow"><?php esc_html_e('Class roster', 'elev8-os'); ?></p><h2><?php echo esc_html((string) $result['selected_label']); ?></h2></div>
                            <input type="search" id="elev8-student-search" placeholder="<?php esc_attr_e('Search name, email, or phone', 'elev8-os'); ?>" oninput="var q=this.value.toLowerCase();document.querySelectorAll('.elev8-student-row').forEach(function(r){r.hidden=r.dataset.search.indexOf(q)===-1;});">
                        </div>

                        <?php if (!$result['students']) : ?>
                            <div class="elev8-student-empty"><span class="dashicons dashicons-groups"></span><h3><?php esc_html_e('No active students found', 'elev8-os'); ?></h3><p><?php esc_html_e('Cancelled and rejected bookings are excluded from this roster.', 'elev8-os'); ?></p></div>
                        <?php else : ?>
                            <div class="elev8-student-table-wrap">
                                <table class="elev8-student-table">
                                    <thead><tr><th><?php esc_html_e('Student', 'elev8-os'); ?></th><th><?php esc_html_e('Contact', 'elev8-os'); ?></th><th><?php esc_html_e('Seats', 'elev8-os'); ?></th><th><?php esc_html_e('Status', 'elev8-os'); ?></th><th><?php esc_html_e('Booked', 'elev8-os'); ?></th></tr></thead>
                                    <tbody>
                                    <?php foreach ($result['students'] as $student) : ?>
                                        <?php $search = strtolower(trim($student['name'] . ' ' . $student['email'] . ' ' . $student['phone'])); ?>
                                        <tr class="elev8-student-row" data-search="<?php echo esc_attr($search); ?>">
                                            <td><strong><?php echo esc_html($student['name'] !== '' ? $student['name'] : __('Name unavailable', 'elev8-os')); ?></strong></td>
                                            <td>
                                                <?php if ($student['email'] !== '') : ?><a href="mailto:<?php echo esc_attr($student['email']); ?>"><?php echo esc_html($student['email']); ?></a><?php endif; ?>
                                                <?php if ($student['phone'] !== '') : ?><a href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', $student['phone'])); ?>"><?php echo esc_html($student['phone']); ?></a><?php endif; ?>
                                                <?php if ($student['email'] === '' && $student['phone'] === '') : ?><span><?php esc_html_e('Unavailable', 'elev8-os'); ?></span><?php endif; ?>
                                            </td>
                                            <td><?php echo esc_html(number_format_i18n((int) $student['seats'])); ?></td>
                                            <td><span class="elev8-student-status status-<?php echo esc_attr(sanitize_html_class($student['status'])); ?>"><?php echo esc_html($student['status_label']); ?></span></td>
                                            <td><?php echo $student['booked_at'] !== '' ? esc_html(self::format_date($student['booked_at'])) : esc_html__('Unavailable', 'elev8-os'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private static function summary_card(string $label, int $value): void {
        echo '<article><span>' . esc_html($label) . '</span><strong>' . esc_html(number_format_i18n($value)) . '</strong></article>';
    }

    /** @return array<string,mixed> */
    private static function load_roster(int $artist_id, int $requested_id): array {
        global $wpdb;
        $appointments_table = $wpdb->prefix . 'amelia_appointments';
        $bookings_table = $wpdb->prefix . 'amelia_customer_bookings';
        $users_table = $wpdb->prefix . 'amelia_users';

        if (!self::table_exists($appointments_table) || !self::table_exists($bookings_table)) {
            return self::unavailable_result(__('Required Amelia appointment or booking tables were not found.', 'elev8-os'));
        }

        $ac = self::table_columns($appointments_table);
        $appointment_id_col = self::first_existing_column($ac, ['id']);
        $provider_col = self::first_existing_column($ac, ['providerId', 'provider_id', 'employeeId']);
        $start_col = self::first_existing_column($ac, ['bookingStart', 'booking_start', 'start']);
        $service_col = self::first_existing_column($ac, ['serviceId', 'service_id']);
        if (!$appointment_id_col || !$provider_col || !$start_col) {
            return self::unavailable_result(__('Required Amelia appointment columns could not be verified.', 'elev8-os'));
        }

        $appointments = $wpdb->get_results($wpdb->prepare(
            "SELECT `{$appointment_id_col}` AS id, `{$start_col}` AS starts" . ($service_col ? ", `{$service_col}` AS service_id" : ', NULL AS service_id') . " FROM `{$appointments_table}` WHERE `{$provider_col}`=%d ORDER BY `{$start_col}` DESC LIMIT 100",
            $artist_id
        ), ARRAY_A);
        if (!is_array($appointments)) {
            return self::unavailable_result(__('Amelia classes could not be read.', 'elev8-os'));
        }

        $service_names = self::simple_id_label_map('amelia_services', ['name', 'title']);
        $choices = [];
        foreach ($appointments as $appointment) {
            $id = (int) $appointment['id'];
            $name = $service_names[(int) ($appointment['service_id'] ?? 0)] ?? __('Class', 'elev8-os');
            $choices[] = ['id' => $id, 'label' => $name . ' — ' . self::format_date((string) $appointment['starts'])];
        }

        if (!$choices) {
            return ['available' => true, 'reason' => '', 'appointments' => [], 'selected_id' => 0, 'selected_label' => '', 'students' => [], 'seats_booked' => 0, 'confirmed' => 0, 'pending' => 0];
        }

        $allowed_ids = array_map(static fn(array $row): int => (int) $row['id'], $choices);
        $selected_id = in_array($requested_id, $allowed_ids, true) ? $requested_id : (int) $choices[0]['id'];
        $selected_label = '';
        foreach ($choices as $choice) { if ((int) $choice['id'] === $selected_id) { $selected_label = (string) $choice['label']; break; } }

        $bc = self::table_columns($bookings_table);
        $booking_appointment_col = self::first_existing_column($bc, ['appointmentId', 'appointment_id']);
        $customer_col = self::first_existing_column($bc, ['customerId', 'customer_id']);
        $persons_col = self::first_existing_column($bc, ['persons', 'personsCount', 'persons_count']);
        $status_col = self::first_existing_column($bc, ['status']);
        $created_col = self::first_existing_column($bc, ['created', 'createdAt', 'created_at']);
        if (!$booking_appointment_col) {
            return self::unavailable_result(__('The Amelia booking-to-class relationship could not be verified.', 'elev8-os'));
        }

        $select = ['b.*'];
        $join = '';
        $user_columns = self::table_exists($users_table) ? self::table_columns($users_table) : [];
        if ($customer_col && in_array('id', $user_columns, true)) {
            $join = " LEFT JOIN `{$users_table}` u ON u.`id`=b.`{$customer_col}`";
            foreach (['firstName', 'lastName', 'email', 'phone'] as $col) {
                if (in_array($col, $user_columns, true)) { $select[] = "u.`{$col}` AS customer_{$col}"; }
            }
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT ' . implode(', ', $select) . " FROM `{$bookings_table}` b{$join} WHERE b.`{$booking_appointment_col}`=%d ORDER BY b.`" . ($created_col ?: $booking_appointment_col) . '` ASC',
            $selected_id
        ), ARRAY_A);
        if (!is_array($rows)) {
            return self::unavailable_result(__('The class roster could not be read.', 'elev8-os'));
        }

        $students = [];
        $seats_booked = 0;
        $confirmed = 0;
        $pending = 0;
        foreach ($rows as $row) {
            $status = $status_col ? strtolower((string) ($row[$status_col] ?? '')) : '';
            if (in_array($status, ['canceled', 'cancelled', 'rejected'], true)) { continue; }
            $seats = $persons_col ? max(1, (int) ($row[$persons_col] ?? 1)) : 1;
            $status_key = in_array($status, ['approved', 'confirmed'], true) ? 'confirmed' : ($status === 'pending' ? 'pending' : ($status !== '' ? $status : 'unknown'));
            if ($status_key === 'confirmed') { $confirmed += $seats; }
            if ($status_key === 'pending') { $pending += $seats; }
            $seats_booked += $seats;
            $first = trim((string) ($row['customer_firstName'] ?? ''));
            $last = trim((string) ($row['customer_lastName'] ?? ''));
            $students[] = [
                'name' => trim($first . ' ' . $last),
                'email' => sanitize_email((string) ($row['customer_email'] ?? '')),
                'phone' => sanitize_text_field((string) ($row['customer_phone'] ?? '')),
                'seats' => $seats,
                'status' => $status_key,
                'status_label' => $status_key === 'confirmed' ? __('Confirmed', 'elev8-os') : ($status_key === 'pending' ? __('Pending', 'elev8-os') : ($status !== '' ? ucfirst($status) : __('Unavailable', 'elev8-os'))),
                'booked_at' => $created_col ? (string) ($row[$created_col] ?? '') : '',
            ];
        }

        return ['available' => true, 'reason' => '', 'appointments' => $choices, 'selected_id' => $selected_id, 'selected_label' => $selected_label, 'students' => $students, 'seats_booked' => $seats_booked, 'confirmed' => $confirmed, 'pending' => $pending];
    }

    /** @return array<string,mixed>|null */
    private static function find_artist_for_user(WP_User $user): ?array {
        global $wpdb;
        $table = $wpdb->prefix . 'amelia_users';
        if (!self::table_exists($table)) { return null; }
        $columns = self::table_columns($table);
        if (!in_array('id', $columns, true)) { return null; }
        $mapped_id = max(0, (int) get_user_meta($user->ID, self::EMPLOYEE_META_KEY, true));
        if ($mapped_id > 0) {
            $row = $wpdb->get_row($wpdb->prepare("SELECT `id` FROM `{$table}` WHERE `id`=%d LIMIT 1", $mapped_id), ARRAY_A);
            if (is_array($row)) { return $row; }
        }
        if (!in_array('email', $columns, true)) { return null; }
        $row = $wpdb->get_row($wpdb->prepare("SELECT `id` FROM `{$table}` WHERE LOWER(`email`)=LOWER(%s) LIMIT 1", sanitize_email($user->user_email)), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    /** @return array<int,string> */
    private static function simple_id_label_map(string $suffix, array $label_candidates): array {
        global $wpdb;
        $table = $wpdb->prefix . $suffix;
        if (!self::table_exists($table)) { return []; }
        $columns = self::table_columns($table);
        $label_col = self::first_existing_column($columns, $label_candidates);
        if (!$label_col || !in_array('id', $columns, true)) { return []; }
        $rows = $wpdb->get_results("SELECT `id`, `{$label_col}` AS label FROM `{$table}`", ARRAY_A);
        $map = [];
        foreach ((array) $rows as $row) { $map[(int) $row['id']] = (string) $row['label']; }
        return $map;
    }

    /** @return array<string,mixed> */
    private static function unavailable_result(string $reason): array {
        return ['available' => false, 'reason' => $reason, 'appointments' => [], 'selected_id' => 0, 'selected_label' => '', 'students' => [], 'seats_booked' => 0, 'confirmed' => 0, 'pending' => 0];
    }

    /** @return string[] */
    private static function table_columns(string $table): array {
        global $wpdb;
        $columns = $wpdb->get_col("DESCRIBE `{$table}`", 0);
        return is_array($columns) ? array_map('strval', $columns) : [];
    }

    private static function first_existing_column(array $available, array $candidates): ?string {
        foreach ($candidates as $candidate) { if (in_array($candidate, $available, true)) { return $candidate; } }
        return null;
    }

    private static function table_exists(string $table): bool {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    private static function format_date(string $value): string {
        $timestamp = strtotime($value);
        return $timestamp ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), $timestamp) : $value;
    }
}

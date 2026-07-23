<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Artist-facing class schedule backed by verified Amelia data.
 */
final class Elev8_OS_My_Classes_Module {

    private const SHORTCODE = 'elev8_artist_classes';
    private const EMPLOYEE_META_KEY = 'elev8_os_amelia_employee_id';

    public static function init(): void {
        add_action('init', [__CLASS__, 'register_shortcode']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    public static function register_shortcode(): void {
        add_shortcode(self::SHORTCODE, [__CLASS__, 'shortcode']);
    }

    /**
     * Verified, reusable artist schedule snapshot for portal dashboards.
     *
     * @return array<string,mixed>
     */
    public static function get_dashboard_snapshot(WP_User $user): array {
        if (self::uses_glass_manager_scope($user)) {
            $result = self::load_glass_manager_classes();
            $result['scope'] = 'glass_manager';
            return $result;
        }

        $artist = self::find_artist_for_user($user);

        if (!$artist) {
            return self::unavailable_result(__('Your WordPress account is not connected to an Amelia teacher.', 'elev8-os'));
        }

        $result = self::load_classes((int) $artist['id']);
        $result['artist'] = $artist;
        $result['scope'] = 'teacher';

        return $result;
    }

    public static function enqueue_assets(): void {
        if (!Elev8_OS_Portal_Page_Manager::is_current_page('classes')) {
            return;
        }

        wp_enqueue_style('dashicons');
        wp_enqueue_style(
            'elev8-os-my-classes',
            ELEV8_OS_URL . 'assets/css/artist-classes.css',
            ['elev8-os-artist-portal'],
            ELEV8_OS_VERSION
        );
        wp_enqueue_script(
            'elev8-os-artist-classes',
            ELEV8_OS_URL . 'assets/js/artist-classes.js',
            [],
            ELEV8_OS_VERSION,
            true
        );
    }

    public static function shortcode(): string {
        if (!is_user_logged_in()) {
            return sprintf(
                '<div class="elev8-dashboard-login"><p>%1$s</p><p><a class="button" href="%2$s">%3$s</a></p></div>',
                esc_html__('Please log in to view your classes.', 'elev8-os'),
                esc_url(wp_login_url(Elev8_OS_Portal_Page_Manager::get_url('classes'))),
                esc_html__('Log In', 'elev8-os')
            );
        }

        $effective_user = class_exists('Elev8_OS_Preview_Service')
            ? Elev8_OS_Preview_Service::effective_user()
            : wp_get_current_user();
        $glass_manager_scope = self::uses_glass_manager_scope($effective_user);
        $artist = $glass_manager_scope ? null : self::find_artist_for_user($effective_user);
        $result = $glass_manager_scope
            ? self::load_glass_manager_classes()
            : ($artist ? self::load_classes((int) $artist['id']) : self::unavailable_result(__('This account is not connected to an Amelia teacher.', 'elev8-os')));

        if (!empty($result['available'])) {
            $result = self::apply_class_filters($result);
        }

        ob_start();
        ?>
        <div class="elev8-artist-dashboard elev8-my-classes">
            <?php if (!$glass_manager_scope) : ?>
                <?php Elev8_OS_Artist_Portal_Module::render_navigation('classes'); ?>
            <?php else : ?>
                <nav class="elev8-glass-class-return"><a href="<?php echo esc_url(class_exists('Elev8_OS_Preview_Service') ? Elev8_OS_Preview_Service::dashboard_url($effective_user) : admin_url('admin.php?page=elev8-glass-operations')); ?>">&larr; <?php esc_html_e('Back to Glass Operations', 'elev8-os'); ?></a></nav>
            <?php endif; ?>

            <header class="elev8-dashboard-header">
                <div>
                    <p class="elev8-eyebrow"><?php echo esc_html($glass_manager_scope ? __('Glass Operations', 'elev8-os') : __('Teaching Portal', 'elev8-os')); ?></p>
                    <h1><?php echo esc_html($glass_manager_scope ? __('Glass Classes', 'elev8-os') : __('My Classes', 'elev8-os')); ?></h1>
                    <p><?php echo esc_html($glass_manager_scope
                        ? __('All verified Amelia glassblowing classes, teachers, enrollment, and open seats in one place.', 'elev8-os')
                        : __('Your verified Amelia schedule, enrollment, and available seats in one place.', 'elev8-os')); ?></p>
                </div>
                <span class="elev8-dashboard-badge"><?php esc_html_e('Amelia connected', 'elev8-os'); ?></span>
            </header>

            <?php if (!$result['available']) : ?>
                <div class="elev8-dashboard-warning">
                    <p><strong><?php esc_html_e('Class information is unavailable.', 'elev8-os'); ?></strong><br><?php echo esc_html($result['reason']); ?></p>
                </div>
            <?php else : ?>
                <?php $summary = $result['summary']; ?>
                <section class="elev8-classes-summary" aria-label="<?php esc_attr_e('Class summary', 'elev8-os'); ?>">
                    <?php self::render_summary_card(__('Upcoming class dates', 'elev8-os'), $summary['upcoming_count'], __('Verified future Amelia appointments', 'elev8-os')); ?>
                    <?php self::render_summary_card(__('Students enrolled', 'elev8-os'), $summary['student_count'], __('Across upcoming classes', 'elev8-os')); ?>
                    <?php self::render_summary_card(__('Available seats', 'elev8-os'), $summary['seats_available'], $summary['seats_available'] === null ? __('Unavailable because capacity was not detected', 'elev8-os') : __('Across classes with verified capacity', 'elev8-os')); ?>
                    <?php self::render_summary_card(__('Booked value', 'elev8-os'), $summary['booked_value'], $summary['booked_value'] === null ? __('Unavailable because booking amounts were not detected', 'elev8-os') : __('Booked value, not recognized revenue', 'elev8-os'), true); ?>
                </section>

                <section class="elev8-classes-section elev8-teaching-calendar-section">
                    <div class="elev8-panel-heading elev8-calendar-heading">
                        <div>
                            <p class="elev8-eyebrow"><?php esc_html_e('Teaching Calendar', 'elev8-os'); ?></p>
                            <h2><?php echo esc_html($glass_manager_scope ? __('Glass Teaching Schedule', 'elev8-os') : __('My Teaching Schedule', 'elev8-os')); ?></h2>
                            <p><?php echo esc_html($glass_manager_scope
                                ? __('Review all glassblowing classes by date or teacher. Amelia remains the verified scheduling and booking source.', 'elev8-os')
                                : __('Switch between agenda, week, and month views. Amelia remains the verified scheduling and booking source.', 'elev8-os')); ?></p>
                        </div>
                    </div>
                    <?php if ($glass_manager_scope) { self::render_glass_manager_filters($result); } ?>
                    <?php self::render_teaching_calendar($result['upcoming']); ?>
                </section>

                <section class="elev8-classes-section">
                    <div class="elev8-panel-heading">
                        <div>
                            <p class="elev8-eyebrow"><?php esc_html_e('Upcoming details', 'elev8-os'); ?></p>
                            <h2><?php esc_html_e('Upcoming Class Details', 'elev8-os'); ?></h2>
                        </div>
                    </div>
                    <?php self::render_class_list($result['upcoming'], true); ?>
                </section>

                <section class="elev8-classes-section">
                    <div class="elev8-panel-heading">
                        <div>
                            <p class="elev8-eyebrow"><?php esc_html_e('History', 'elev8-os'); ?></p>
                            <h2><?php esc_html_e('Recent Past Classes', 'elev8-os'); ?></h2>
                        </div>
                    </div>
                    <?php self::render_class_list($result['past'], false); ?>
                </section>
            <?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /** @param mixed $value */
    private static function render_summary_card(string $label, $value, string $description, bool $money = false): void {
        $display = __('Unavailable', 'elev8-os');
        if ($value !== null) {
            $display = $money ? self::format_money((float) $value) : number_format_i18n((int) $value);
        }
        ?>
        <article class="elev8-class-summary-card">
            <span><?php echo esc_html($label); ?></span>
            <strong><?php echo esc_html($display); ?></strong>
            <p><?php echo esc_html($description); ?></p>
        </article>
        <?php
    }

    /** @param array<int,array<string,mixed>> $classes */
    private static function render_class_list(array $classes, bool $upcoming): void {
        if (!$classes) {
            ?>
            <div class="elev8-class-empty">
                <span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>
                <h3><?php echo esc_html($upcoming ? __('No upcoming classes found', 'elev8-os') : __('No recent past classes found', 'elev8-os')); ?></h3>
                <p><?php echo esc_html($upcoming ? __('When Amelia assigns a future class to you, it will appear here.', 'elev8-os') : __('Completed classes will appear here after their scheduled date.', 'elev8-os')); ?></p>
            </div>
            <?php
            return;
        }

        echo '<div class="elev8-class-list">';
        foreach ($classes as $class) {
            self::render_class_card($class, $upcoming);
        }
        echo '</div>';
    }

    /** @param array<string,mixed> $class */
    private static function render_class_card(array $class, bool $upcoming): void {
        $timestamp = strtotime((string) $class['start']);
        $month = $timestamp ? wp_date('M', $timestamp) : '';
        $day = $timestamp ? wp_date('j', $timestamp) : '';
        $weekday = $timestamp ? wp_date('D', $timestamp) : '';
        $date_only = !empty($class['date_only']);
        $date_time = self::format_schedule_date((string) $class['start'], $date_only, false);
        ?>
        <article class="elev8-class-card" id="elev8-class-<?php echo esc_attr((string) max(0, (int) $class['id'])); ?>-<?php echo esc_attr(sanitize_title((string) $class['start'])); ?>">
            <div class="elev8-class-date"><small><?php echo esc_html($weekday); ?></small><span><?php echo esc_html($month); ?></span><strong><?php echo esc_html($day); ?></strong></div>
            <div class="elev8-class-main">
                <div class="elev8-class-title-row">
                    <div>
                        <h3><?php echo esc_html((string) $class['name']); ?></h3>
                        <p><span class="dashicons dashicons-clock" aria-hidden="true"></span> <?php echo esc_html($date_time); ?></p>
                        <?php if (!empty($class['teacher'])) : ?><p><span class="dashicons dashicons-businessperson" aria-hidden="true"></span> <?php echo esc_html((string) $class['teacher']); ?></p><?php endif; ?>
                        <?php if ($class['location'] !== '') : ?><p><span class="dashicons dashicons-location" aria-hidden="true"></span> <?php echo esc_html((string) $class['location']); ?></p><?php endif; ?>
                    </div>
                    <span class="elev8-class-status <?php echo $upcoming ? 'is-upcoming' : 'is-complete'; ?>"><?php echo esc_html($upcoming ? __('Upcoming', 'elev8-os') : __('Completed', 'elev8-os')); ?></span>
                </div>

                <div class="elev8-class-facts">
                    <div><span><?php esc_html_e('Students', 'elev8-os'); ?></span><strong><?php echo esc_html(number_format_i18n((int) $class['students'])); ?></strong></div>
                    <div><span><?php esc_html_e('Capacity', 'elev8-os'); ?></span><strong><?php echo $class['capacity'] === null ? esc_html__('Unavailable', 'elev8-os') : esc_html(number_format_i18n((int) $class['capacity'])); ?></strong></div>
                    <div><span><?php esc_html_e('Seats left', 'elev8-os'); ?></span><strong><?php echo $class['seats_left'] === null ? esc_html__('Unavailable', 'elev8-os') : esc_html(number_format_i18n((int) $class['seats_left'])); ?></strong></div>
                    <div><span><?php esc_html_e('Booked value', 'elev8-os'); ?></span><strong><?php echo $class['booked_value'] === null ? esc_html__('Unavailable', 'elev8-os') : esc_html(self::format_money((float) $class['booked_value'])); ?></strong></div>
                </div>

                <div class="elev8-class-actions">
                    <?php if ($class['booking_url'] !== '') : ?>
                        <button type="button" class="elev8-copy-class-link" data-link="<?php echo esc_attr((string) $class['booking_url']); ?>"><?php esc_html_e('Copy Booking Link', 'elev8-os'); ?></button>
                        <a href="<?php echo esc_url((string) $class['booking_url']); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Open Booking Page', 'elev8-os'); ?></a>
                    <?php else : ?>
                        <span class="elev8-action-unavailable"><?php esc_html_e('Booking link unavailable', 'elev8-os'); ?></span>
                    <?php endif; ?>
                    <?php if ((int) $class['id'] > 0) : ?>
                        <a href="<?php echo esc_url(add_query_arg('appointment_id', (int) $class['id'], Elev8_OS_Portal_Page_Manager::get_url('students'))); ?>"><?php esc_html_e('View Students', 'elev8-os'); ?></a>
                    <?php else : ?>
                        <span class="elev8-action-unavailable"><?php esc_html_e('Roster available after Amelia creates this class date', 'elev8-os'); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </article>
        <?php
    }

    /**
     * Render the shared teaching calendar using verified Amelia-backed occurrences.
     *
     * @param array<int,array<string,mixed>> $classes
     */
    private static function render_teaching_calendar(array $classes): void {
        $view = sanitize_key((string) ($_GET['elev8_calendar_view'] ?? 'agenda'));
        if (!in_array($view, ['agenda', 'week', 'month'], true)) {
            $view = 'agenda';
        }

        $anchor_raw = sanitize_text_field((string) ($_GET['elev8_calendar_date'] ?? wp_date('Y-m-d')));
        $anchor = strtotime($anchor_raw . ' 12:00:00');
        if (!$anchor) {
            $anchor = current_time('timestamp');
        }

        self::render_calendar_toolbar($view, $anchor);

        if (!$classes) {
            echo '<div class="elev8-class-empty"><span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span><h3>' . esc_html__('No upcoming classes found', 'elev8-os') . '</h3><p>' . esc_html__('When Amelia assigns a future class to you, it will appear on this calendar.', 'elev8-os') . '</p></div>';
            return;
        }

        if ($view === 'month') {
            self::render_month_view($classes, $anchor);
        } elseif ($view === 'week') {
            self::render_week_view($classes, $anchor);
        } else {
            self::render_agenda_view($classes);
        }
    }

    private static function render_calendar_toolbar(string $view, int $anchor): void {
        $base = Elev8_OS_Portal_Page_Manager::get_url('classes');
        $teacher_id = absint($_GET['elev8_teacher'] ?? 0);
        $views = [
            'agenda' => __('Agenda', 'elev8-os'),
            'week' => __('Week', 'elev8-os'),
            'month' => __('Month', 'elev8-os'),
        ];
        ?>
        <div class="elev8-calendar-toolbar">
            <div class="elev8-calendar-view-tabs" role="navigation" aria-label="<?php esc_attr_e('Calendar views', 'elev8-os'); ?>">
                <?php foreach ($views as $key => $label) : ?>
                    <a class="<?php echo $view === $key ? 'is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg(array_filter(['elev8_calendar_view' => $key, 'elev8_calendar_date' => wp_date('Y-m-d', $anchor), 'elev8_teacher' => $teacher_id]), $base)); ?>"><?php echo esc_html($label); ?></a>
                <?php endforeach; ?>
            </div>
            <?php if ($view !== 'agenda') :
                $step = $view === 'month' ? 'month' : 'week';
                $previous = strtotime('-1 ' . $step, $anchor);
                $next = strtotime('+1 ' . $step, $anchor);
                $label = $view === 'month'
                    ? wp_date('F Y', $anchor)
                    : sprintf(
                        __('%1$s – %2$s', 'elev8-os'),
                        wp_date('M j', self::week_start($anchor)),
                        wp_date('M j, Y', self::week_start($anchor) + (6 * DAY_IN_SECONDS))
                    );
                ?>
                <div class="elev8-calendar-period-nav">
                    <a aria-label="<?php esc_attr_e('Previous period', 'elev8-os'); ?>" href="<?php echo esc_url(add_query_arg(array_filter(['elev8_calendar_view' => $view, 'elev8_calendar_date' => wp_date('Y-m-d', $previous), 'elev8_teacher' => $teacher_id]), $base)); ?>">&larr;</a>
                    <strong><?php echo esc_html($label); ?></strong>
                    <a aria-label="<?php esc_attr_e('Next period', 'elev8-os'); ?>" href="<?php echo esc_url(add_query_arg(array_filter(['elev8_calendar_view' => $view, 'elev8_calendar_date' => wp_date('Y-m-d', $next), 'elev8_teacher' => $teacher_id]), $base)); ?>">&rarr;</a>
                    <a class="elev8-calendar-today" href="<?php echo esc_url(add_query_arg(array_filter(['elev8_calendar_view' => $view, 'elev8_calendar_date' => wp_date('Y-m-d'), 'elev8_teacher' => $teacher_id]), $base)); ?>"><?php esc_html_e('Today', 'elev8-os'); ?></a>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /** @param array<int,array<string,mixed>> $classes */
    private static function render_agenda_view(array $classes): void {
        $groups = [];
        foreach ($classes as $class) {
            $timestamp = strtotime((string) ($class['start'] ?? ''));
            if (!$timestamp) { continue; }
            $groups[wp_date('Y-m-d', $timestamp)][] = $class;
        }
        echo '<div class="elev8-calendar-agenda">';
        foreach ($groups as $date => $items) {
            $timestamp = strtotime($date . ' 12:00:00');
            echo '<section class="elev8-agenda-day">';
            echo '<header><span>' . esc_html(wp_date('D', $timestamp)) . '</span><strong>' . esc_html(wp_date('l, F j, Y', $timestamp)) . '</strong><small>' . esc_html(sprintf(_n('%d class', '%d classes', count($items), 'elev8-os'), count($items))) . '</small></header>';
            echo '<div class="elev8-agenda-items">';
            foreach ($items as $class) {
                self::render_calendar_event($class, 'agenda');
            }
            echo '</div></section>';
        }
        echo '</div>';
    }

    /** @param array<int,array<string,mixed>> $classes */
    private static function render_week_view(array $classes, int $anchor): void {
        $start = self::week_start($anchor);
        $map = self::classes_by_date($classes);
        echo '<div class="elev8-calendar-week">';
        for ($i = 0; $i < 7; $i++) {
            $day_ts = $start + ($i * DAY_IN_SECONDS);
            $key = wp_date('Y-m-d', $day_ts);
            $is_today = $key === wp_date('Y-m-d');
            echo '<section class="elev8-calendar-day-column' . ($is_today ? ' is-today' : '') . '">';
            echo '<header><span>' . esc_html(wp_date('D', $day_ts)) . '</span><strong>' . esc_html(wp_date('j', $day_ts)) . '</strong><small>' . esc_html(wp_date('M', $day_ts)) . '</small></header>';
            echo '<div class="elev8-calendar-day-events">';
            foreach (($map[$key] ?? []) as $class) {
                self::render_calendar_event($class, 'week');
            }
            if (empty($map[$key])) {
                echo '<span class="elev8-calendar-no-class">' . esc_html__('No class', 'elev8-os') . '</span>';
            }
            echo '</div></section>';
        }
        echo '</div>';
    }

    /** @param array<int,array<string,mixed>> $classes */
    private static function render_month_view(array $classes, int $anchor): void {
        $year = (int) wp_date('Y', $anchor);
        $month = (int) wp_date('n', $anchor);
        $first = strtotime(sprintf('%04d-%02d-01 12:00:00', $year, $month));
        $days = (int) wp_date('t', $first);
        $offset = (int) wp_date('N', $first) - 1;
        $map = self::classes_by_date($classes);
        $weekdays = [__('Mon', 'elev8-os'), __('Tue', 'elev8-os'), __('Wed', 'elev8-os'), __('Thu', 'elev8-os'), __('Fri', 'elev8-os'), __('Sat', 'elev8-os'), __('Sun', 'elev8-os')];
        echo '<div class="elev8-calendar-month">';
        foreach ($weekdays as $weekday) {
            echo '<div class="elev8-calendar-weekday">' . esc_html($weekday) . '</div>';
        }
        for ($i = 0; $i < $offset; $i++) {
            echo '<div class="elev8-calendar-month-day is-empty" aria-hidden="true"></div>';
        }
        for ($day = 1; $day <= $days; $day++) {
            $day_ts = strtotime(sprintf('%04d-%02d-%02d 12:00:00', $year, $month, $day));
            $key = wp_date('Y-m-d', $day_ts);
            $items = $map[$key] ?? [];
            $is_today = $key === wp_date('Y-m-d');
            echo '<section class="elev8-calendar-month-day' . ($is_today ? ' is-today' : '') . '"><header><span>' . esc_html((string) $day) . '</span><small>' . esc_html(wp_date('D', $day_ts)) . '</small></header><div>';
            foreach (array_slice($items, 0, 3) as $class) {
                self::render_calendar_event($class, 'month');
            }
            if (count($items) > 3) {
                echo '<span class="elev8-calendar-more">' . esc_html(sprintf(__('+%d more', 'elev8-os'), count($items) - 3)) . '</span>';
            }
            echo '</div></section>';
        }
        echo '</div>';
    }

    /** @param array<string,mixed> $class */
    private static function render_calendar_event(array $class, string $context): void {
        $timestamp = strtotime((string) ($class['start'] ?? ''));
        $date_only = !empty($class['date_only']);
        $time = $date_only ? __('Time unavailable', 'elev8-os') : ($timestamp ? wp_date(get_option('time_format'), $timestamp) : __('Unavailable', 'elev8-os'));
        $students = max(0, (int) ($class['students'] ?? 0));
        $capacity = isset($class['capacity']) && $class['capacity'] !== null ? (int) $class['capacity'] : null;
        $seats = isset($class['seats_left']) && $class['seats_left'] !== null ? (int) $class['seats_left'] : null;
        $anchor = '#elev8-class-' . max(0, (int) ($class['id'] ?? 0)) . '-' . sanitize_title((string) ($class['start'] ?? ''));
        ?>
        <article class="elev8-calendar-event is-<?php echo esc_attr($context); ?>">
            <a href="<?php echo esc_url($anchor); ?>">
                <span class="elev8-calendar-event-time"><?php echo esc_html($time); ?></span>
                <strong><?php echo esc_html((string) ($class['name'] ?? __('Class', 'elev8-os'))); ?></strong>
                <?php if ($context !== 'month') : ?>
                    <?php if (!empty($class['teacher'])) : ?><small><?php echo esc_html((string) $class['teacher']); ?></small><?php endif; ?>
                    <small><?php echo esc_html(sprintf(__('%1$d booked%2$s', 'elev8-os'), $students, $capacity === null ? '' : sprintf(__(' · %d capacity', 'elev8-os'), $capacity))); ?></small>
                    <small><?php echo $seats === null ? esc_html__('Seats unavailable', 'elev8-os') : esc_html(sprintf(__('%d seats left', 'elev8-os'), $seats)); ?></small>
                <?php endif; ?>
            </a>
        </article>
        <?php
    }

    /** @param array<int,array<string,mixed>> $classes @return array<string,array<int,array<string,mixed>>> */
    private static function classes_by_date(array $classes): array {
        $map = [];
        foreach ($classes as $class) {
            $timestamp = strtotime((string) ($class['start'] ?? ''));
            if (!$timestamp) { continue; }
            $map[wp_date('Y-m-d', $timestamp)][] = $class;
        }
        return $map;
    }

    private static function week_start(int $timestamp): int {
        $weekday = (int) wp_date('N', $timestamp);
        return strtotime('-' . ($weekday - 1) . ' days', strtotime(wp_date('Y-m-d', $timestamp) . ' 12:00:00'));
    }

    public static function format_schedule_date(string $start, bool $date_only = false, bool $compact = false): string {
        $timestamp = strtotime($start);
        if (!$timestamp) { return $start !== '' ? $start : __('Unavailable', 'elev8-os'); }
        if ($compact) {
            return $date_only ? wp_date('D, M j', $timestamp) : wp_date('D, M j · ' . get_option('time_format'), $timestamp);
        }
        return $date_only ? wp_date('l, F j, Y', $timestamp) : wp_date('l, F j, Y · ' . get_option('time_format'), $timestamp);
    }

    /** @return array<string,mixed> */
    private static function load_classes(int $artist_id): array {
        global $wpdb;
        $appointments = $wpdb->prefix . 'amelia_appointments';
        if (!self::table_exists($appointments)) {
            return self::unavailable_result(__('The Amelia appointments table was not found.', 'elev8-os'));
        }

        $appointment_columns = self::table_columns($appointments);
        $id_col = self::first_existing_column($appointment_columns, ['id']);
        $provider_col = self::first_existing_column($appointment_columns, ['providerId', 'provider_id', 'employeeId']);
        $start_col = self::first_existing_column($appointment_columns, ['bookingStart', 'booking_start', 'start']);
        if (!$id_col || !$provider_col || !$start_col) {
            return self::unavailable_result(__('Required Amelia appointment columns could not be verified.', 'elev8-os'));
        }

        $service_col = self::first_existing_column($appointment_columns, ['serviceId', 'service_id']);
        $location_col = self::first_existing_column($appointment_columns, ['locationId', 'location_id']);
        $capacity_col = self::first_existing_column($appointment_columns, ['maxCapacity', 'max_capacity', 'capacity']);
        $status_col = self::first_existing_column($appointment_columns, ['status']);

        $select = ["a.`{$id_col}` AS appointment_id", "a.`{$start_col}` AS booking_start"];
        $select[] = $service_col ? "a.`{$service_col}` AS service_id" : 'NULL AS service_id';
        $select[] = $location_col ? "a.`{$location_col}` AS location_id" : 'NULL AS location_id';
        $select[] = $capacity_col ? "a.`{$capacity_col}` AS appointment_capacity" : 'NULL AS appointment_capacity';
        $select[] = $status_col ? "a.`{$status_col}` AS appointment_status" : "'' AS appointment_status";

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT ' . implode(', ', $select) . " FROM `{$appointments}` a WHERE a.`{$provider_col}` = %d ORDER BY a.`{$start_col}` DESC LIMIT 250",
                $artist_id
            ),
            ARRAY_A
        );
        if (!is_array($rows)) {
            return self::unavailable_result(__('Amelia appointments could not be read.', 'elev8-os'));
        }

        $service_names = self::service_map();
        $service_capacities = self::service_capacity_map();
        $location_names = self::location_map();
        $bookings = self::booking_aggregates();
        $booking_base = self::artist_booking_url();
        $now = current_time('timestamp');
        $upcoming = [];
        $past = [];

        foreach ($rows as $row) {
            $status = strtolower((string) ($row['appointment_status'] ?? ''));
            if (in_array($status, ['canceled', 'cancelled', 'rejected'], true)) {
                continue;
            }
            $appointment_id = (int) ($row['appointment_id'] ?? 0);
            $service_id = (int) ($row['service_id'] ?? 0);
            $capacity = self::nullable_positive_int($row['appointment_capacity'] ?? null);
            if ($capacity === null && isset($service_capacities[$service_id])) {
                $capacity = $service_capacities[$service_id];
            }
            $aggregate = $bookings[$appointment_id] ?? ['students' => 0, 'booked_value' => null];
            $students = (int) $aggregate['students'];
            $start = (string) ($row['booking_start'] ?? '');
            $timestamp = strtotime($start);
            $class = [
                'id' => $appointment_id,
                'name' => $service_names[$service_id] ?? __('Class', 'elev8-os'),
                'start' => $start,
                'location' => $location_names[(int) ($row['location_id'] ?? 0)] ?? '',
                'students' => $students,
                'capacity' => $capacity,
                'seats_left' => $capacity === null ? null : max(0, $capacity - $students),
                'booked_value' => $aggregate['booked_value'],
                'booking_url' => $booking_base,
                'teacher_id' => $artist_id,
                'teacher' => '',
            ];
            // Upcoming classes are normalized through the shared Class Discovery
            // service below. Keep this direct appointment query for class history only.
            if ($timestamp === false || $timestamp < $now) {
                $past[] = $class;
            }
        }

        // Appointment-first discovery with verified service-date fallback. This is
        // required for recurring Amelia services whose future dates exist in the
        // assigned service record before Amelia creates appointment rows.
        if (class_exists('Elev8_OS_Class_Discovery')) {
            foreach (Elev8_OS_Class_Discovery::upcoming_for_employee($artist_id) as $occurrence) {
                $appointment_id = max(0, (int) ($occurrence['appointment_id'] ?? 0));
                $aggregate = $appointment_id > 0
                    ? ($bookings[$appointment_id] ?? ['students' => 0, 'booked_value' => null])
                    : ['students' => 0, 'booked_value' => null];
                $start = (string) ($occurrence['sort_start'] ?? '');
                if ($start === '') { continue; }

                $upcoming[] = [
                    'id' => $appointment_id,
                    'name' => (string) ($occurrence['name'] ?? __('Class', 'elev8-os')),
                    'start' => $start,
                    'date_only' => !empty($occurrence['date_only']),
                    'location' => '',
                    'students' => max(0, (int) ($occurrence['booked'] ?? $aggregate['students'] ?? 0)),
                    'capacity' => isset($occurrence['capacity']) && $occurrence['capacity'] !== null ? (int) $occurrence['capacity'] : null,
                    'seats_left' => isset($occurrence['seats_left']) && $occurrence['seats_left'] !== null ? (int) $occurrence['seats_left'] : null,
                    'booked_value' => $aggregate['booked_value'] ?? null,
                    'booking_url' => $booking_base,
                    'source' => (string) ($occurrence['source'] ?? 'unknown'),
                    'teacher_id' => $artist_id,
                    'teacher' => '',
                ];
            }
        }

        usort($upcoming, static fn(array $a, array $b): int => strcmp((string) $a['start'], (string) $b['start']));
        usort($past, static fn(array $a, array $b): int => strcmp((string) $b['start'], (string) $a['start']));
        $past = array_slice($past, 0, 12);

        $student_count = 0;
        $seats_available = 0;
        $capacity_available = false;
        $booked_value = 0.0;
        $value_available = false;
        foreach ($upcoming as $class) {
            $student_count += (int) $class['students'];
            if ($class['seats_left'] !== null) {
                $seats_available += (int) $class['seats_left'];
                $capacity_available = true;
            }
            if ($class['booked_value'] !== null) {
                $booked_value += (float) $class['booked_value'];
                $value_available = true;
            }
        }

        return [
            'available' => true,
            'reason' => '',
            'summary' => [
                'upcoming_count' => count($upcoming),
                'student_count' => $student_count,
                'seats_available' => $capacity_available ? $seats_available : null,
                'booked_value' => $value_available ? $booked_value : null,
            ],
            'upcoming' => $upcoming,
            'past' => $past,
        ];
    }


    private static function uses_glass_manager_scope(WP_User $user): bool {
        if (!class_exists('Elev8_OS_Access_Service')) { return false; }
        if (class_exists('Elev8_OS_Preview_Service') && Elev8_OS_Preview_Service::is_active()) {
            return Elev8_OS_Preview_Service::selected_role() === 'glass_manager';
        }
        return Elev8_OS_Access_Service::user_can('view_glass_dashboard', $user)
            && !Elev8_OS_Access_Service::is_teacher($user);
    }

    /** @param array<string,mixed> $result @return array<string,mixed> */
    private static function apply_class_filters(array $result): array {
        $teacher_id = absint($_GET['elev8_teacher'] ?? 0);
        if ($teacher_id <= 0) { return $result; }

        foreach (['upcoming', 'past'] as $bucket) {
            $result[$bucket] = array_values(array_filter((array) ($result[$bucket] ?? []), static function (array $class) use ($teacher_id): bool {
                return (int) ($class['teacher_id'] ?? 0) === $teacher_id;
            }));
        }
        $result['summary'] = self::summarize_classes((array) $result['upcoming']);
        return $result;
    }

    /** @param array<string,mixed> $result */
    private static function render_glass_manager_filters(array $result): void {
        $teachers = [];
        foreach (array_merge((array) ($result['upcoming'] ?? []), (array) ($result['past'] ?? [])) as $class) {
            $id = (int) ($class['teacher_id'] ?? 0);
            $name = trim((string) ($class['teacher'] ?? ''));
            if ($id > 0 && $name !== '') { $teachers[$id] = $name; }
        }
        asort($teachers, SORT_NATURAL | SORT_FLAG_CASE);
        $selected = absint($_GET['elev8_teacher'] ?? 0);
        ?>
        <form class="elev8-class-manager-filters" method="get" action="<?php echo esc_url(Elev8_OS_Portal_Page_Manager::get_url('classes')); ?>">
            <?php foreach (['elev8_calendar_view', 'elev8_calendar_date'] as $key) : if (!empty($_GET[$key])) : ?>
                <input type="hidden" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr(sanitize_text_field((string) $_GET[$key])); ?>">
            <?php endif; endforeach; ?>
            <label>
                <span><?php esc_html_e('Teacher', 'elev8-os'); ?></span>
                <select name="elev8_teacher">
                    <option value="0"><?php esc_html_e('All glass teachers', 'elev8-os'); ?></option>
                    <?php foreach ($teachers as $id => $name) : ?>
                        <option value="<?php echo esc_attr((string) $id); ?>" <?php selected($selected, $id); ?>><?php echo esc_html($name); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit"><?php esc_html_e('Apply', 'elev8-os'); ?></button>
            <?php if ($selected > 0) : ?><a href="<?php echo esc_url(Elev8_OS_Portal_Page_Manager::get_url('classes')); ?>"><?php esc_html_e('Clear', 'elev8-os'); ?></a><?php endif; ?>
        </form>
        <?php
    }

    /** @return array<string,mixed> */
    private static function load_glass_manager_classes(): array {
        $service_ids = self::glass_class_service_ids();
        if (!$service_ids) {
            return self::unavailable_result(__('No Amelia glassblowing services could be verified. Assign them to a glassblowing category or include a glassblowing keyword in the service name.', 'elev8-os'));
        }

        $teachers = self::amelia_teacher_map();
        $bookings = self::booking_aggregates();
        $service_names = self::service_map();
        $service_capacities = self::service_capacity_map();
        $location_names = self::location_map();
        $upcoming = [];
        $past = [];
        $now = current_time('timestamp');

        global $wpdb;
        $appointments = $wpdb->prefix . 'amelia_appointments';
        if (!self::table_exists($appointments)) {
            return self::unavailable_result(__('The Amelia appointments table was not found.', 'elev8-os'));
        }
        $columns = self::table_columns($appointments);
        $id_col = self::first_existing_column($columns, ['id']);
        $provider_col = self::first_existing_column($columns, ['providerId', 'provider_id', 'employeeId']);
        $service_col = self::first_existing_column($columns, ['serviceId', 'service_id']);
        $start_col = self::first_existing_column($columns, ['bookingStart', 'booking_start', 'start']);
        $location_col = self::first_existing_column($columns, ['locationId', 'location_id']);
        $capacity_col = self::first_existing_column($columns, ['maxCapacity', 'max_capacity', 'capacity']);
        $status_col = self::first_existing_column($columns, ['status']);
        if (!$id_col || !$provider_col || !$service_col || !$start_col) {
            return self::unavailable_result(__('Required Amelia appointment columns could not be verified.', 'elev8-os'));
        }

        $select = ["`{$id_col}` AS appointment_id", "`{$provider_col}` AS provider_id", "`{$service_col}` AS service_id", "`{$start_col}` AS booking_start"];
        $select[] = $location_col ? "`{$location_col}` AS location_id" : 'NULL AS location_id';
        $select[] = $capacity_col ? "`{$capacity_col}` AS appointment_capacity" : 'NULL AS appointment_capacity';
        $select[] = $status_col ? "`{$status_col}` AS appointment_status" : "'' AS appointment_status";
        $placeholders = implode(',', array_fill(0, count($service_ids), '%d'));
        $query = $wpdb->prepare(
            'SELECT ' . implode(', ', $select) . " FROM `{$appointments}` WHERE `{$service_col}` IN ({$placeholders}) ORDER BY `{$start_col}` DESC LIMIT 600",
            ...$service_ids
        );
        $rows = $wpdb->get_results($query, ARRAY_A);
        if (!is_array($rows)) {
            return self::unavailable_result(__('Amelia glass class appointments could not be read.', 'elev8-os'));
        }

        foreach ($rows as $row) {
            $status = strtolower((string) ($row['appointment_status'] ?? ''));
            if (in_array($status, ['canceled', 'cancelled', 'rejected'], true)) { continue; }
            $appointment_id = (int) ($row['appointment_id'] ?? 0);
            $provider_id = (int) ($row['provider_id'] ?? 0);
            $service_id = (int) ($row['service_id'] ?? 0);
            $start = (string) ($row['booking_start'] ?? '');
            $timestamp = strtotime($start);
            $capacity = self::nullable_positive_int($row['appointment_capacity'] ?? null);
            if ($capacity === null && isset($service_capacities[$service_id])) { $capacity = $service_capacities[$service_id]; }
            $aggregate = $bookings[$appointment_id] ?? ['students' => 0, 'booked_value' => null];
            $class = [
                'id' => $appointment_id,
                'name' => $service_names[$service_id] ?? __('Glass Class', 'elev8-os'),
                'start' => $start,
                'date_only' => false,
                'location' => $location_names[(int) ($row['location_id'] ?? 0)] ?? '',
                'students' => max(0, (int) ($aggregate['students'] ?? 0)),
                'capacity' => $capacity,
                'seats_left' => $capacity === null ? null : max(0, $capacity - (int) ($aggregate['students'] ?? 0)),
                'booked_value' => $aggregate['booked_value'] ?? null,
                'booking_url' => '',
                'teacher_id' => $provider_id,
                'teacher' => $teachers[$provider_id] ?? __('Unassigned teacher', 'elev8-os'),
                'source' => 'appointment',
            ];
            if ($timestamp !== false && $timestamp >= $now) { $upcoming[] = $class; }
            else { $past[] = $class; }
        }

        if (class_exists('Elev8_OS_Class_Discovery')) {
            foreach (array_keys($teachers) as $provider_id) {
                foreach (Elev8_OS_Class_Discovery::upcoming_for_employee((int) $provider_id) as $occurrence) {
                    $service_id = (int) ($occurrence['service_id'] ?? 0);
                    if (!in_array($service_id, $service_ids, true)) { continue; }
                    $appointment_id = max(0, (int) ($occurrence['appointment_id'] ?? 0));
                    $aggregate = $appointment_id > 0 ? ($bookings[$appointment_id] ?? ['students' => 0, 'booked_value' => null]) : ['students' => 0, 'booked_value' => null];
                    $start = (string) ($occurrence['sort_start'] ?? '');
                    if ($start === '') { continue; }
                    $upcoming[] = [
                        'id' => $appointment_id,
                        'name' => (string) ($occurrence['name'] ?? ($service_names[$service_id] ?? __('Glass Class', 'elev8-os'))),
                        'start' => $start,
                        'date_only' => !empty($occurrence['date_only']),
                        'location' => '',
                        'students' => max(0, (int) ($occurrence['booked'] ?? $aggregate['students'] ?? 0)),
                        'capacity' => isset($occurrence['capacity']) && $occurrence['capacity'] !== null ? (int) $occurrence['capacity'] : null,
                        'seats_left' => isset($occurrence['seats_left']) && $occurrence['seats_left'] !== null ? (int) $occurrence['seats_left'] : null,
                        'booked_value' => $aggregate['booked_value'] ?? null,
                        'booking_url' => '',
                        'teacher_id' => (int) $provider_id,
                        'teacher' => $teachers[(int) $provider_id] ?? __('Unassigned teacher', 'elev8-os'),
                        'source' => (string) ($occurrence['source'] ?? 'unknown'),
                    ];
                }
            }
        }

        $upcoming = self::dedupe_classes($upcoming);
        usort($upcoming, static fn(array $a, array $b): int => strcmp((string) $a['start'], (string) $b['start']));
        usort($past, static fn(array $a, array $b): int => strcmp((string) $b['start'], (string) $a['start']));
        $past = array_slice(self::dedupe_classes($past), 0, 30);

        return [
            'available' => true,
            'reason' => '',
            'summary' => self::summarize_classes($upcoming),
            'upcoming' => $upcoming,
            'past' => $past,
        ];
    }

    /** @param array<int,array<string,mixed>> $classes @return array<string,mixed> */
    private static function summarize_classes(array $classes): array {
        $student_count = 0;
        $seats_available = 0;
        $capacity_available = false;
        $booked_value = 0.0;
        $value_available = false;
        foreach ($classes as $class) {
            $student_count += (int) ($class['students'] ?? 0);
            if (isset($class['seats_left']) && $class['seats_left'] !== null) {
                $seats_available += (int) $class['seats_left'];
                $capacity_available = true;
            }
            if (isset($class['booked_value']) && $class['booked_value'] !== null) {
                $booked_value += (float) $class['booked_value'];
                $value_available = true;
            }
        }
        return [
            'upcoming_count' => count($classes),
            'student_count' => $student_count,
            'seats_available' => $capacity_available ? $seats_available : null,
            'booked_value' => $value_available ? $booked_value : null,
        ];
    }

    /** @param array<int,array<string,mixed>> $classes @return array<int,array<string,mixed>> */
    private static function dedupe_classes(array $classes): array {
        $seen = [];
        $out = [];
        foreach ($classes as $class) {
            $key = (int) ($class['id'] ?? 0) > 0
                ? 'appointment:' . (int) $class['id']
                : (int) ($class['teacher_id'] ?? 0) . '|' . (string) ($class['name'] ?? '') . '|' . (string) ($class['start'] ?? '');
            if (isset($seen[$key])) { continue; }
            $seen[$key] = true;
            $out[] = $class;
        }
        return $out;
    }

    /** @return array<int,string> */
    private static function amelia_teacher_map(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'amelia_users';
        if (!self::table_exists($table)) { return []; }
        $columns = self::table_columns($table);
        if (!in_array('id', $columns, true)) { return []; }
        $first = self::first_existing_column($columns, ['firstName', 'first_name']);
        $last = self::first_existing_column($columns, ['lastName', 'last_name']);
        $email = self::first_existing_column($columns, ['email']);
        $type = self::first_existing_column($columns, ['type']);
        $select = ['`id`'];
        $select[] = $first ? "`{$first}` AS first_name" : "'' AS first_name";
        $select[] = $last ? "`{$last}` AS last_name" : "'' AS last_name";
        $select[] = $email ? "`{$email}` AS email" : "'' AS email";
        $where = $type ? " WHERE LOWER(COALESCE(`{$type}`,'')) IN ('provider','employee')" : '';
        $rows = $wpdb->get_results('SELECT ' . implode(',', $select) . " FROM `{$table}`{$where} ORDER BY `id` ASC", ARRAY_A) ?: [];
        $map = [];
        foreach ($rows as $row) {
            $name = trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''));
            if ($name === '') { $name = (string) ($row['email'] ?? ''); }
            if ($name !== '') { $map[(int) $row['id']] = $name; }
        }
        return $map;
    }

    /** @return int[] */
    private static function glass_class_service_ids(): array {
        $configured = array_map('absint', (array) get_option('elev8_os_glass_class_service_ids', []));
        $configured = array_values(array_filter($configured));
        $configured = (array) apply_filters('elev8_os_glass_class_service_ids', $configured);
        if ($configured) { return array_values(array_unique(array_map('absint', $configured))); }

        global $wpdb;
        $services = $wpdb->prefix . 'amelia_services';
        if (!self::table_exists($services)) { return []; }
        $columns = self::table_columns($services);
        if (!in_array('id', $columns, true)) { return []; }
        $name_col = self::first_existing_column($columns, ['name', 'title']);
        $description_col = self::first_existing_column($columns, ['description', 'details', 'content']);
        $category_col = self::first_existing_column($columns, ['categoryId', 'category_id']);
        $category_names = [];
        if ($category_col) {
            foreach (['amelia_categories', 'amelia_service_categories'] as $suffix) {
                $table = $wpdb->prefix . $suffix;
                if (!self::table_exists($table)) { continue; }
                $cat_columns = self::table_columns($table);
                $cat_name_col = self::first_existing_column($cat_columns, ['name', 'title']);
                if (!$cat_name_col || !in_array('id', $cat_columns, true)) { continue; }
                foreach ($wpdb->get_results("SELECT `id`, `{$cat_name_col}` AS label FROM `{$table}`", ARRAY_A) ?: [] as $row) {
                    $category_names[(int) $row['id']] = (string) $row['label'];
                }
                break;
            }
        }

        $keywords = (array) apply_filters('elev8_os_glass_class_keywords', [
            'glassblowing', 'glass blowing', 'liquid arts', 'lampwork', 'flamework', 'torch class', 'glass 101',
        ]);
        $select = ['`id`'];
        $select[] = $name_col ? "`{$name_col}` AS service_name" : "'' AS service_name";
        $select[] = $description_col ? "`{$description_col}` AS service_description" : "'' AS service_description";
        $select[] = $category_col ? "`{$category_col}` AS category_id" : '0 AS category_id';
        $rows = $wpdb->get_results('SELECT ' . implode(',', $select) . " FROM `{$services}` ORDER BY `id` ASC", ARRAY_A) ?: [];
        $ids = [];
        foreach ($rows as $row) {
            $haystack = strtolower(wp_strip_all_tags(
                (string) ($row['service_name'] ?? '') . ' ' .
                (string) ($row['service_description'] ?? '') . ' ' .
                (string) ($category_names[(int) ($row['category_id'] ?? 0)] ?? '')
            ));
            foreach ($keywords as $keyword) {
                $keyword = strtolower(trim((string) $keyword));
                if ($keyword !== '' && strpos($haystack, $keyword) !== false) {
                    $ids[] = (int) $row['id'];
                    break;
                }
            }
        }
        return array_values(array_unique(array_filter($ids)));
    }

    /** @return array<int,array{students:int,booked_value:?float}> */
    private static function booking_aggregates(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'amelia_customer_bookings';
        if (!self::table_exists($table)) {
            return [];
        }
        $columns = self::table_columns($table);
        $appointment_col = self::first_existing_column($columns, ['appointmentId', 'appointment_id']);
        if (!$appointment_col) {
            return [];
        }
        $persons_col = self::first_existing_column($columns, ['persons', 'personsCount', 'persons_count']);
        $price_col = self::first_existing_column($columns, ['price', 'amount', 'paymentAmount', 'payment_amount']);
        $status_col = self::first_existing_column($columns, ['status']);
        $status_sql = $status_col ? " WHERE LOWER(COALESCE(`{$status_col}`,'')) NOT IN ('canceled','cancelled','rejected')" : '';
        $select_students = $persons_col ? "SUM(COALESCE(`{$persons_col}`,1))" : 'COUNT(*)';
        $select_value = $price_col ? ", SUM(COALESCE(`{$price_col}`,0)) AS booked_value" : ', NULL AS booked_value';
        $rows = $wpdb->get_results("SELECT `{$appointment_col}` AS appointment_id, {$select_students} AS students{$select_value} FROM `{$table}`{$status_sql} GROUP BY `{$appointment_col}`", ARRAY_A);
        $map = [];
        foreach ((array) $rows as $row) {
            $map[(int) $row['appointment_id']] = [
                'students' => max(0, (int) $row['students']),
                'booked_value' => $price_col ? (float) $row['booked_value'] : null,
            ];
        }
        return $map;
    }

    /** @return array<int,string> */
    private static function service_map(): array {
        return self::simple_id_label_map('amelia_services', ['name', 'title']);
    }

    /** @return array<int,int> */
    private static function service_capacity_map(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'amelia_services';
        if (!self::table_exists($table)) { return []; }
        $columns = self::table_columns($table);
        $capacity_col = self::first_existing_column($columns, ['maxCapacity', 'max_capacity', 'capacity']);
        if (!$capacity_col || !in_array('id', $columns, true)) { return []; }
        $rows = $wpdb->get_results("SELECT `id`, `{$capacity_col}` AS capacity FROM `{$table}`", ARRAY_A);
        $map = [];
        foreach ((array) $rows as $row) {
            $capacity = self::nullable_positive_int($row['capacity'] ?? null);
            if ($capacity !== null) { $map[(int) $row['id']] = $capacity; }
        }
        return $map;
    }

    /** @return array<int,string> */
    private static function location_map(): array {
        return self::simple_id_label_map('amelia_locations', ['name', 'address']);
    }

    /** @param string[] $label_candidates @return array<int,string> */
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

    private static function artist_booking_url(): string {
        $user = wp_get_current_user();
        $url = esc_url_raw((string) get_user_meta($user->ID, 'elev8_os_artist_booking_url', true));
        return $url;
    }

    /** @return array<string,mixed>|null */
    private static function find_artist_for_user(WP_User $user): ?array {
        global $wpdb;
        $table = $wpdb->prefix . 'amelia_users';
        if (!self::table_exists($table)) { return null; }
        $columns = self::table_columns($table);
        if (!in_array('id', $columns, true)) { return null; }
        $select = ['id'];
        foreach (['firstName', 'lastName', 'email'] as $column) {
            if (in_array($column, $columns, true)) { $select[] = $column; }
        }
        $select_sql = implode(', ', array_map(static fn(string $column): string => "`{$column}`", $select));
        $type_sql = in_array('type', $columns, true) ? " AND LOWER(COALESCE(`type`,'')) IN ('provider','employee')" : '';
        $mapped_id = max(0, (int) get_user_meta($user->ID, self::EMPLOYEE_META_KEY, true));
        if ($mapped_id > 0) {
            $row = $wpdb->get_row($wpdb->prepare("SELECT {$select_sql} FROM `{$table}` WHERE `id`=%d{$type_sql} LIMIT 1", $mapped_id), ARRAY_A);
            if (is_array($row)) { return $row; }
        }
        $email = sanitize_email((string) $user->user_email);
        if ($email === '' || !in_array('email', $columns, true)) { return null; }
        $row = $wpdb->get_row($wpdb->prepare("SELECT {$select_sql} FROM `{$table}` WHERE LOWER(`email`)=LOWER(%s){$type_sql} LIMIT 1", $email), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    /** @return array<string,mixed> */
    private static function unavailable_result(string $reason): array {
        return ['available' => false, 'reason' => $reason, 'summary' => [], 'upcoming' => [], 'past' => []];
    }

    /** @return string[] */
    private static function table_columns(string $table): array {
        global $wpdb;
        $columns = $wpdb->get_col("DESCRIBE `{$table}`", 0);
        return is_array($columns) ? array_map('strval', $columns) : [];
    }

    /** @param string[] $available @param string[] $candidates */
    private static function first_existing_column(array $available, array $candidates): ?string {
        foreach ($candidates as $candidate) {
            if (in_array($candidate, $available, true)) { return $candidate; }
        }
        return null;
    }

    private static function table_exists(string $table): bool {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    /** @param mixed $value */
    private static function nullable_positive_int($value): ?int {
        if ($value === null || $value === '') { return null; }
        $number = (int) $value;
        return $number > 0 ? $number : null;
    }

    private static function format_money(float $value): string {
        if (function_exists('wc_price')) {
            return wp_strip_all_tags((string) wc_price($value));
        }
        return '$' . number_format_i18n($value, 2);
    }
}

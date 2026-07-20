<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Central role/capability resolver for role-aware Elev8 OS experiences.
 * WordPress remains the source of truth for accounts and authentication.
 */
final class Elev8_OS_Access_Service {
    public const ROLE_TEACHER = 'elev8_teacher';
    public const ROLE_EVENT_STAFF = 'elev8_event_staff';
    public const ROLE_DJ = 'elev8_open_mic_dj'; // Legacy role retained for compatibility.
    public const ROLE_RETAIL = 'elev8_retail_employee';
    public const ROLE_GLASS_MANAGER = 'elev8_glass_manager';
    public const ROLE_GLASS_BLOWER = 'elev8_glass_blower';
    public const ROLE_SHOP_MANAGER = 'shop_manager';
    public const CAP_RECEIVE_ASSIGNMENTS = 'elev8_receive_assignments';
    public const META_ASSIGNMENT_OVERRIDE = 'elev8_os_assignment_eligible';
    public const META_ACTIVE = 'elev8_os_operational_active';
    public const META_DEPARTMENT = 'elev8_os_department';

    public static function init(): void {
        add_action('init', [__CLASS__, 'ensure_roles'], 5);
        add_action('show_user_profile', [__CLASS__, 'render_operational_access']);
        add_action('edit_user_profile', [__CLASS__, 'render_operational_access']);
        add_action('personal_options_update', [__CLASS__, 'save_operational_access']);
        add_action('edit_user_profile_update', [__CLASS__, 'save_operational_access']);
        add_filter('manage_users_columns', [__CLASS__, 'user_columns']);
        add_filter('manage_users_custom_column', [__CLASS__, 'user_column_value'], 10, 3);
        add_action('restrict_manage_users', [__CLASS__, 'user_filters']);
        add_action('pre_get_users', [__CLASS__, 'apply_user_filters']);
    }

    public static function activate(): void { self::ensure_roles(); }

    public static function ensure_roles(): void {
        self::ensure_role(self::ROLE_TEACHER, __('Elev8 Teacher', 'elev8-os'));
        self::ensure_role(self::ROLE_EVENT_STAFF, __('Elev8 Event Staff', 'elev8-os'));
        self::ensure_role(self::ROLE_DJ, __('Elev8 Open Mic DJ', 'elev8-os'));
        self::ensure_role(self::ROLE_RETAIL, __('Elev8 Shop Employee', 'elev8-os'));
        self::ensure_role(self::ROLE_SHOP_MANAGER, __('Elev8 Shop Manager', 'elev8-os'));
        self::ensure_role(self::ROLE_GLASS_MANAGER, __('Elev8 Glass Manager', 'elev8-os'), ['elev8_manage_glass' => true, 'elev8_glass_work' => true]);
        self::ensure_role(self::ROLE_GLASS_BLOWER, __('Elev8 Glass Blower', 'elev8-os'), ['elev8_glass_work' => true]);

        foreach ([
            'administrator', self::ROLE_TEACHER, self::ROLE_EVENT_STAFF, self::ROLE_DJ,
            self::ROLE_RETAIL, self::ROLE_SHOP_MANAGER, self::ROLE_GLASS_MANAGER, self::ROLE_GLASS_BLOWER,
        ] as $role_key) {
            $role = get_role($role_key);
            if ($role) { $role->add_cap(self::CAP_RECEIVE_ASSIGNMENTS); }
        }

        $admin = get_role('administrator');
        if ($admin) { $admin->add_cap('elev8_manage_glass'); $admin->add_cap('elev8_glass_work'); }
        self::assign_foundation_glass_manager();
    }

    private static function ensure_role(string $key, string $label, array $extra_caps = []): void {
        $caps = array_merge(['read' => true, self::CAP_RECEIVE_ASSIGNMENTS => true], $extra_caps);
        if (!get_role($key)) { add_role($key, $label, $caps); }
        $role = get_role($key);
        if ($role) { foreach ($caps as $cap => $grant) { if ($grant) { $role->add_cap($cap); } } }
    }

    private static function assign_foundation_glass_manager(): void {
        $email = (string) apply_filters('elev8_os_foundation_glass_manager_email', 'glass@elev8premier.com');
        $user = $email ? get_user_by('email', $email) : false;
        if (!$user instanceof WP_User || user_can($user, 'elev8_manage_glass')) { return; }
        $user->add_role(self::ROLE_GLASS_MANAGER);
    }

    public static function is_owner(WP_User $user): bool { return user_can($user, 'manage_options'); }
    public static function is_manager(WP_User $user): bool { return self::is_owner($user) || self::has_any_role($user, [self::ROLE_SHOP_MANAGER, self::ROLE_GLASS_MANAGER, 'editor']); }
    public static function is_retail_employee(WP_User $user): bool { return self::has_any_role($user, [self::ROLE_RETAIL]); }
    public static function is_teacher(WP_User $user): bool { return self::has_any_role($user, [self::ROLE_TEACHER]); }
    public static function is_event_staff(WP_User $user): bool { return self::has_any_role($user, [self::ROLE_EVENT_STAFF, self::ROLE_DJ]); }
    public static function is_dj(WP_User $user): bool { return self::is_event_staff($user); }
    public static function is_artist(WP_User $user): bool { return class_exists('Elev8_OS_Identity_Service') && Elev8_OS_Identity_Service::artist_for_user($user) !== null; }

    public static function is_operationally_active(WP_User $user): bool {
        return get_user_meta($user->ID, self::META_ACTIVE, true) !== '0';
    }

    public static function can_receive_assignments(WP_User $user): bool {
        if (!$user->exists() || !self::is_operationally_active($user)) { return false; }
        $override = get_user_meta($user->ID, self::META_ASSIGNMENT_OVERRIDE, true);
        if ($override === 'yes') { return true; }
        if ($override === 'no') { return false; }
        return self::is_owner($user) || user_can($user, self::CAP_RECEIVE_ASSIGNMENTS) || self::is_artist($user);
    }

    /** @return array<string,array<int,WP_User>> */
    public static function assignment_users_grouped(): array {
        $groups = [
            'management' => [], 'event_staff' => [], 'teachers' => [], 'artists' => [], 'shop_employees' => [], 'glass_team' => [],
        ];
        foreach (get_users(['orderby' => 'display_name', 'order' => 'ASC']) as $user) {
            if (!$user instanceof WP_User || !self::can_receive_assignments($user)) { continue; }
            $group = self::assignment_group($user);
            $groups[$group][] = $user;
        }
        return array_filter($groups);
    }

    public static function assignment_group(WP_User $user): string {
        if (self::is_owner($user) || self::has_any_role($user, [self::ROLE_SHOP_MANAGER, self::ROLE_GLASS_MANAGER, 'editor'])) { return 'management'; }
        if (self::is_event_staff($user)) { return 'event_staff'; }
        if (self::is_teacher($user)) { return 'teachers'; }
        if (self::is_artist($user)) { return 'artists'; }
        if (self::is_retail_employee($user)) { return 'shop_employees'; }
        if (self::has_any_role($user, [self::ROLE_GLASS_BLOWER])) { return 'glass_team'; }
        return 'shop_employees';
    }

    public static function assignment_group_labels(): array {
        return [
            'management' => __('Management', 'elev8-os'),
            'event_staff' => __('Event Staff', 'elev8-os'),
            'teachers' => __('Teachers', 'elev8-os'),
            'artists' => __('Artists', 'elev8-os'),
            'shop_employees' => __('Shop Employees', 'elev8-os'),
            'glass_team' => __('Glass Team', 'elev8-os'),
        ];
    }

    public static function can_distribute_flyers(WP_User $user): bool { return self::is_owner($user) || self::is_manager($user) || self::is_retail_employee($user) || self::is_event_staff($user); }

    public static function allowed_operations_templates(WP_User $user): array {
        if (self::is_owner($user)) { return array_keys(Elev8_OS_Daily_Operations_Service::templates()); }
        $allowed = [];
        if (self::is_manager($user)) { $allowed[] = 'manager'; }
        if (self::is_retail_employee($user)) { $allowed[] = 'retail'; }
        if (self::is_artist($user) || self::is_teacher($user)) { $allowed[] = 'artist'; }
        if (self::is_event_staff($user)) { $allowed[] = 'event'; }
        return array_values(array_unique($allowed));
    }

    public static function render_operational_access(WP_User $user): void {
        if (!current_user_can('edit_users')) { return; }
        $override = (string) get_user_meta($user->ID, self::META_ASSIGNMENT_OVERRIDE, true);
        $active = get_user_meta($user->ID, self::META_ACTIVE, true) !== '0';
        $department = (string) get_user_meta($user->ID, self::META_DEPARTMENT, true);
        ?>
        <h2><?php esc_html_e('Elev8 OS Operational Access', 'elev8-os'); ?></h2>
        <?php wp_nonce_field('elev8_os_operational_access_' . $user->ID, 'elev8_os_operational_access_nonce'); ?>
        <table class="form-table" role="presentation">
            <tr><th><label for="elev8_os_operational_active"><?php esc_html_e('Operational status', 'elev8-os'); ?></label></th><td><label><input type="checkbox" id="elev8_os_operational_active" name="elev8_os_operational_active" value="1" <?php checked($active); ?>> <?php esc_html_e('Active in Elev8 OS', 'elev8-os'); ?></label><p class="description"><?php esc_html_e('Inactive users are removed from work-assignment lists without deleting their WordPress account.', 'elev8-os'); ?></p></td></tr>
            <tr><th><label for="elev8_os_assignment_eligible"><?php esc_html_e('Work assignments', 'elev8-os'); ?></label></th><td><select id="elev8_os_assignment_eligible" name="elev8_os_assignment_eligible"><option value="" <?php selected($override, ''); ?>><?php esc_html_e('Automatic from role', 'elev8-os'); ?></option><option value="yes" <?php selected($override, 'yes'); ?>><?php esc_html_e('Allow assignments', 'elev8-os'); ?></option><option value="no" <?php selected($override, 'no'); ?>><?php esc_html_e('Do not allow assignments', 'elev8-os'); ?></option></select><p class="description"><?php esc_html_e('Automatic includes administrators, artists, teachers, managers, shop employees, glass staff, and Elev8 Event Staff.', 'elev8-os'); ?></p></td></tr>
            <tr><th><label for="elev8_os_department"><?php esc_html_e('Department', 'elev8-os'); ?></label></th><td><input type="text" class="regular-text" id="elev8_os_department" name="elev8_os_department" value="<?php echo esc_attr($department); ?>" placeholder="<?php esc_attr_e('Events, Retail, Glass, Arts…', 'elev8-os'); ?>"></td></tr>
        </table>
        <?php
    }

    public static function save_operational_access(int $user_id): void {
        if (!current_user_can('edit_user', $user_id)) { return; }
        if (!isset($_POST['elev8_os_operational_access_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['elev8_os_operational_access_nonce'])), 'elev8_os_operational_access_' . $user_id)) { return; }
        update_user_meta($user_id, self::META_ACTIVE, !empty($_POST['elev8_os_operational_active']) ? '1' : '0');
        $override = sanitize_key((string) ($_POST['elev8_os_assignment_eligible'] ?? ''));
        if (!in_array($override, ['', 'yes', 'no'], true)) { $override = ''; }
        if ($override === '') { delete_user_meta($user_id, self::META_ASSIGNMENT_OVERRIDE); } else { update_user_meta($user_id, self::META_ASSIGNMENT_OVERRIDE, $override); }
        update_user_meta($user_id, self::META_DEPARTMENT, sanitize_text_field(wp_unslash($_POST['elev8_os_department'] ?? '')));
    }

    public static function user_columns(array $columns): array {
        $columns['elev8_role'] = __('Elev8 Role', 'elev8-os');
        $columns['elev8_assignment'] = __('Assignment Eligible', 'elev8-os');
        $columns['elev8_department'] = __('Department', 'elev8-os');
        return $columns;
    }

    public static function user_column_value(string $value, string $column, int $user_id): string {
        $user = get_userdata($user_id); if (!$user) { return $value; }
        if ($column === 'elev8_role') { return esc_html(implode(', ', array_map(static fn($r) => wp_roles()->roles[$r]['name'] ?? $r, (array) $user->roles))); }
        if ($column === 'elev8_assignment') { return self::can_receive_assignments($user) ? __('Yes', 'elev8-os') : __('No', 'elev8-os'); }
        if ($column === 'elev8_department') { $d = (string) get_user_meta($user_id, self::META_DEPARTMENT, true); return $d !== '' ? esc_html($d) : __('Unavailable', 'elev8-os'); }
        return $value;
    }

    public static function user_filters(string $which): void {
        if ($which !== 'top') { return; }
        $current = sanitize_key((string) ($_GET['elev8_assignment_filter'] ?? ''));
        echo '<select name="elev8_assignment_filter"><option value="">' . esc_html__('All assignment access', 'elev8-os') . '</option><option value="eligible" ' . selected($current, 'eligible', false) . '>' . esc_html__('Assignment eligible', 'elev8-os') . '</option><option value="ineligible" ' . selected($current, 'ineligible', false) . '>' . esc_html__('Not assignment eligible', 'elev8-os') . '</option></select>';
    }

    public static function apply_user_filters(WP_User_Query $query): void {
        if (!is_admin() || !current_user_can('list_users')) { return; }
        $filter = sanitize_key((string) ($_GET['elev8_assignment_filter'] ?? ''));
        if ($filter === '') { return; }
        $ids = [];
        foreach (get_users(['fields' => 'all']) as $user) {
            $eligible = self::can_receive_assignments($user);
            if (($filter === 'eligible' && $eligible) || ($filter === 'ineligible' && !$eligible)) { $ids[] = $user->ID; }
        }
        $query->set('include', $ids ?: [0]);
    }

    private static function has_any_role(WP_User $user, array $roles): bool { return (bool) array_intersect((array) $user->roles, $roles); }
}

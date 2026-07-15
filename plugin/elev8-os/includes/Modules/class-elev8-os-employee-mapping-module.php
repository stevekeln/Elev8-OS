<?php
if (!defined('ABSPATH')) { exit; }

/** Read-only Amelia employee discovery plus WordPress-owned artist mapping. */
final class Elev8_OS_Employee_Mapping_Module {
    private const PAGE_SLUG = 'elev8-employee-mapping';
    private const META_KEY = 'elev8_os_amelia_employee_id';
    private const NONCE_ACTION = 'elev8_os_save_employee_mapping';
    private const NONCE_NAME = 'elev8_os_employee_mapping_nonce';

    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'register_menu'], 25);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    public static function status(): string { return 'active'; }

    public static function register_menu(): void {
        add_submenu_page('elev8-os', __('Artist Mapping','elev8-os'), __('Artist Mapping','elev8-os'), 'manage_options', self::PAGE_SLUG, [__CLASS__, 'render']);
    }

    public static function enqueue_assets(string $hook): void {
        if ($hook !== 'elev8-os_page_' . self::PAGE_SLUG) { return; }
        wp_enqueue_style('elev8-os-employee-mapping', ELEV8_OS_URL . 'assets/css/employee-mapping.css', [], ELEV8_OS_VERSION);
    }

    public static function mapped_employee_id(WP_User $user): int {
        return max(0, (int) get_user_meta($user->ID, self::META_KEY, true));
    }

    public static function render(): void {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('You do not have permission to manage artist mappings.','elev8-os')); }
        $notice = self::handle_post();
        $employees = self::employees();
        $users = self::candidate_users($employees);
        $mapped = [];
        foreach ($users as $user) { $id = self::mapped_employee_id($user); if ($id > 0) { $mapped[$id] = true; } }
        ?>
        <div class="wrap elev8-mapping-wrap">
            <h1><?php esc_html_e('Elev8 OS Artist Mapping','elev8-os'); ?></h1>
            <p class="description"><?php esc_html_e('Connect each WordPress artist account to one Amelia employee. Elev8 OS stores the connection without modifying Amelia.','elev8-os'); ?></p>
            <?php if ($notice['text'] !== ''): ?><div class="notice <?php echo $notice['success'] ? 'notice-success' : 'notice-error'; ?> is-dismissible"><p><?php echo esc_html($notice['text']); ?></p></div><?php endif; ?>
            <?php if (!$employees): ?>
                <div class="notice notice-error"><p><?php esc_html_e('No Amelia employee/provider records were detected. Verify Amelia Database Discovery first.','elev8-os'); ?></p></div>
            <?php else: ?>
                <div class="elev8-mapping-summary"><div><strong><?php echo esc_html(number_format_i18n(count($employees))); ?></strong><span><?php esc_html_e('Amelia employees detected','elev8-os'); ?></span></div><div><strong><?php echo esc_html(number_format_i18n(count($mapped))); ?></strong><span><?php esc_html_e('employees mapped','elev8-os'); ?></span></div></div>
                <form method="post">
                    <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME); ?>
                    <div class="elev8-mapping-actions"><button class="button button-primary" name="elev8_mapping_action" value="save"><?php esc_html_e('Save Artist Mappings','elev8-os'); ?></button><button class="button" name="elev8_mapping_action" value="auto_match"><?php esc_html_e('Auto-match Identical Emails','elev8-os'); ?></button></div>
                    <table class="widefat striped elev8-mapping-table"><thead><tr><th><?php esc_html_e('WordPress Account','elev8-os'); ?></th><th><?php esc_html_e('Role','elev8-os'); ?></th><th><?php esc_html_e('Amelia Employee','elev8-os'); ?></th><th><?php esc_html_e('Status','elev8-os'); ?></th></tr></thead><tbody>
                    <?php if (!$users): ?><tr><td colspan="4"><?php esc_html_e('No candidate artist accounts were found.','elev8-os'); ?></td></tr><?php endif; ?>
                    <?php foreach ($users as $user): $selected = self::mapped_employee_id($user); $suggested = self::employee_id_by_email($user->user_email, $employees); ?>
                        <tr><td><strong><?php echo esc_html($user->display_name); ?></strong><span class="elev8-mapping-email"><?php echo esc_html($user->user_email); ?></span></td><td><?php echo esc_html(implode(', ', (array) $user->roles)); ?></td><td>
                            <select name="employee_mapping[<?php echo esc_attr((string)$user->ID); ?>]"><option value="0"><?php esc_html_e('Not connected','elev8-os'); ?></option><?php foreach ($employees as $employee): $id=(int)$employee['id']; ?><option value="<?php echo esc_attr((string)$id); ?>" <?php selected($selected,$id); ?>><?php echo esc_html(self::employee_label($employee)); ?></option><?php endforeach; ?></select>
                            <?php if ($selected <= 0 && $suggested > 0): ?><p class="elev8-mapping-suggestion"><?php esc_html_e('Exact email match available.','elev8-os'); ?></p><?php endif; ?>
                        </td><td><?php if ($selected > 0 && self::employee_by_id($selected)): ?><span class="elev8-mapping-status is-connected"><?php esc_html_e('Connected','elev8-os'); ?></span><?php else: ?><span class="elev8-mapping-status is-unconnected"><?php esc_html_e('Needs mapping','elev8-os'); ?></span><?php endif; ?></td></tr>
                    <?php endforeach; ?>
                    </tbody></table><p class="submit"><button class="button button-primary" name="elev8_mapping_action" value="save"><?php esc_html_e('Save Artist Mappings','elev8-os'); ?></button></p>
                </form>
                <?php self::render_unmapped($employees, $mapped); ?>
            <?php endif; ?>
        </div><?php
    }

    private static function handle_post(): array {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { return ['success'=>false,'text'=>'']; }
        if (empty($_POST[self::NONCE_NAME]) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE_NAME])), self::NONCE_ACTION)) { return ['success'=>false,'text'=>__('Security check failed. Refresh and try again.','elev8-os')]; }
        $action = sanitize_key(wp_unslash($_POST['elev8_mapping_action'] ?? 'save'));
        if ($action === 'auto_match') { $count=self::auto_match(); return ['success'=>true,'text'=>sprintf(_n('%d artist account matched by email.','%d artist accounts matched by email.',$count,'elev8-os'),$count)]; }
        $submitted = isset($_POST['employee_mapping']) ? (array) wp_unslash($_POST['employee_mapping']) : [];
        $valid=[]; foreach (self::employees() as $employee) { $valid[(int)$employee['id']] = true; }
        $saved=0;
        foreach ($submitted as $user_id=>$employee_id) { $user_id=absint($user_id); $employee_id=absint($employee_id); if (!$user_id || !get_user_by('id',$user_id)) { continue; } if (!$employee_id) { delete_user_meta($user_id,self::META_KEY); $saved++; continue; } if (!isset($valid[$employee_id])) { continue; } update_user_meta($user_id,self::META_KEY,$employee_id); $saved++; }
        return ['success'=>true,'text'=>sprintf(_n('%d artist mapping saved.','%d artist mappings saved.',$saved,'elev8-os'),$saved)];
    }

    private static function auto_match(): int {
        $employees=self::employees(); $count=0;
        foreach (self::candidate_users($employees) as $user) { if (self::mapped_employee_id($user)>0) { continue; } $id=self::employee_id_by_email($user->user_email,$employees); if ($id>0) { update_user_meta($user->ID,self::META_KEY,$id); $count++; } }
        return $count;
    }

    private static function candidate_users(array $employees): array {
        $emails=[]; foreach ($employees as $employee) { $email=strtolower(sanitize_email($employee['email'] ?? '')); if ($email!=='') { $emails[$email]=true; } }
        $users=get_users(['orderby'=>'display_name','order'=>'ASC','number'=>500]);
        return array_values(array_filter($users, static function(WP_User $user) use ($emails): bool { if ((int)get_user_meta($user->ID,self::META_KEY,true)>0) { return true; } $email=strtolower(sanitize_email($user->user_email)); if ($email!=='' && isset($emails[$email])) { return true; } foreach ((array)$user->roles as $role) { $role=strtolower(str_replace(['_','-'],' ',$role)); if (strpos($role,'amelia')!==false || strpos($role,'artist')!==false || strpos($role,'instructor')!==false || strpos($role,'teacher')!==false) { return true; } } return false; }));
    }

    private static function employees(): array {
        global $wpdb; $table=$wpdb->prefix.'amelia_users'; if (!self::table_exists($table)) { return []; }
        $columns=self::table_columns($table); if (!in_array('id',$columns,true)) { return []; }
        $select=['`id`']; foreach (['firstName','lastName','email','type','status'] as $column) { if (in_array($column,$columns,true)) { $select[]="`{$column}`"; } }
        $where=in_array('type',$columns,true) ? " WHERE LOWER(COALESCE(`type`,'')) IN ('provider','employee')" : '';
        $order=[]; foreach (['lastName','firstName','email','id'] as $column) { if (in_array($column,$columns,true)) { $order[]="`{$column}` ASC"; } }
        $rows=$wpdb->get_results('SELECT '.implode(', ',$select)." FROM `{$table}`{$where}".($order?' ORDER BY '.implode(', ',$order):''),ARRAY_A);
        return is_array($rows)?$rows:[];
    }

    private static function employee_by_id(int $id): ?array {
        global $wpdb; if ($id<=0) { return null; } $table=$wpdb->prefix.'amelia_users'; if (!self::table_exists($table)) { return null; }
        $columns=self::table_columns($table); if (!in_array('id',$columns,true)) { return null; }
        $select=['`id`']; foreach (['firstName','lastName','email','type','status'] as $column) { if (in_array($column,$columns,true)) { $select[]="`{$column}`"; } }
        $type=in_array('type',$columns,true) ? " AND LOWER(COALESCE(`type`,'')) IN ('provider','employee')" : '';
        $row=$wpdb->get_row($wpdb->prepare('SELECT '.implode(', ',$select)." FROM `{$table}` WHERE `id`=%d{$type} LIMIT 1",$id),ARRAY_A);
        return is_array($row)?$row:null;
    }

    private static function employee_id_by_email(string $email,array $employees): int {
        $email=strtolower(sanitize_email($email)); if ($email==='') { return 0; }
        foreach ($employees as $employee) { $candidate=strtolower(sanitize_email($employee['email']??'')); if ($candidate!=='' && $candidate===$email) { return (int)$employee['id']; } }
        return 0;
    }

    private static function employee_label(array $employee): string {
        $name=trim(($employee['firstName']??'').' '.($employee['lastName']??'')); $email=sanitize_email($employee['email']??''); $id=(int)($employee['id']??0);
        if ($name==='') { $name=$email!==''?$email:sprintf(__('Amelia employee #%d','elev8-os'),$id); }
        return $email!=='' && strcasecmp($name,$email)!==0 ? $name.' — '.$email : $name;
    }

    private static function render_unmapped(array $employees,array $mapped): void {
        $unmapped=array_values(array_filter($employees,static fn(array $employee):bool=>!isset($mapped[(int)$employee['id']]))); if (!$unmapped) { return; }
        ?><section class="elev8-unmapped-employees"><h2><?php esc_html_e('Unmapped Amelia Employees','elev8-os'); ?></h2><p><?php esc_html_e('These Amelia employees are not connected to a WordPress artist account.','elev8-os'); ?></p><ul><?php foreach ($unmapped as $employee): ?><li><?php echo esc_html(self::employee_label($employee)); ?></li><?php endforeach; ?></ul></section><?php
    }

    private static function table_columns(string $table): array { global $wpdb; $columns=$wpdb->get_col("DESCRIBE `{$table}`",0); return is_array($columns)?array_map('strval',$columns):[]; }
    private static function table_exists(string $table): bool { global $wpdb; return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($table)))===$table; }
}

<?php
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Daily_Operations_Service {
    const OPTION_TEMPLATES = 'elev8_os_operations_templates_v1';
    const POST_TYPE = 'elev8_ops_log';
    const META_TEMPLATE = '_elev8_ops_template';
    const META_FIELDS = '_elev8_ops_fields';
    const META_STATUS = '_elev8_ops_status';
    const META_ATTENTION = '_elev8_ops_owner_attention';
    const META_LOCATION = '_elev8_ops_location';
    const META_ENTRY_DATE = '_elev8_ops_entry_date';
    const META_ATTACHMENTS = '_elev8_ops_attachments';

    public static function init(): void {
        add_action('init', [__CLASS__, 'register_post_type']);
        add_action('init', [__CLASS__, 'ensure_defaults'], 15);
    }

    public static function activate(): void {
        self::register_post_type();
        self::ensure_defaults();
    }

    public static function register_post_type(): void {
        register_post_type(self::POST_TYPE, [
            'labels' => ['name' => __('Operations Logs', 'elev8-os'), 'singular_name' => __('Operations Log', 'elev8-os')],
            'public' => false,
            'show_ui' => false,
            'show_in_rest' => false,
            'supports' => ['title', 'editor', 'author'],
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ]);
    }

    public static function ensure_defaults(): void {
        $templates = get_option(self::OPTION_TEMPLATES, []);
        if (!is_array($templates) || !$templates) {
            update_option(self::OPTION_TEMPLATES, self::default_templates(), false);
        }
    }

    public static function templates(): array {
        $templates = get_option(self::OPTION_TEMPLATES, []);
        if (!is_array($templates) || !$templates) { $templates = self::default_templates(); }
        return apply_filters('elev8_os_operations_templates', $templates);
    }

    public static function template(string $key): ?array {
        $templates = self::templates();
        return isset($templates[$key]) && is_array($templates[$key]) ? $templates[$key] : null;
    }

    public static function save_template(array $input): string {
        $templates = self::templates();
        $key = sanitize_key((string)($input['key'] ?? ''));
        if ($key === '') { $key = 'custom_' . wp_generate_password(8, false, false); }
        $fields = [];
        foreach ((array)($input['fields'] ?? []) as $field) {
            $label = sanitize_text_field((string)($field['label'] ?? ''));
            if ($label === '') { continue; }
            $field_key = sanitize_key((string)($field['key'] ?? $label));
            $type = sanitize_key((string)($field['type'] ?? 'textarea'));
            if (!in_array($type, ['text','textarea','number','select','checkbox','date'], true)) { $type = 'textarea'; }
            $fields[] = [
                'key' => $field_key,
                'label' => $label,
                'type' => $type,
                'required' => !empty($field['required']),
                'options' => array_values(array_filter(array_map('sanitize_text_field', (array)($field['options'] ?? []))))
            ];
        }
        $templates[$key] = [
            'key' => $key,
            'name' => sanitize_text_field((string)($input['name'] ?? __('Custom Operating Log', 'elev8-os'))),
            'description' => sanitize_textarea_field((string)($input['description'] ?? '')),
            'roles' => array_values(array_filter(array_map('sanitize_key', (array)($input['roles'] ?? [])))) ,
            'fields' => $fields,
            'custom' => true,
        ];
        update_option(self::OPTION_TEMPLATES, $templates, false);
        return $key;
    }

    public static function delete_template(string $key): bool {
        $templates = self::templates();
        if (empty($templates[$key]['custom'])) { return false; }
        unset($templates[$key]);
        update_option(self::OPTION_TEMPLATES, $templates, false);
        return true;
    }

    public static function templates_for_user(int $user_id): array {
        if (user_can($user_id, 'manage_options')) { return self::templates(); }
        $user = get_userdata($user_id);
        if (!$user) { return []; }
        $roles = (array)$user->roles;
        $available = [];
        foreach (self::templates() as $key => $template) {
            $allowed = (array)($template['roles'] ?? []);
            if (!$allowed || array_intersect($roles, $allowed)) { $available[$key] = $template; }
        }
        return $available;
    }

    public static function save_entry(array $posted, array $files, int $user_id) {
        $template_key = sanitize_key((string)($posted['template'] ?? ''));
        $template = self::template($template_key);
        if (!$template) { return new WP_Error('invalid_template', __('That report template is unavailable.', 'elev8-os')); }
        if (!isset(self::templates_for_user($user_id)[$template_key])) { return new WP_Error('forbidden_template', __('You cannot submit this report type.', 'elev8-os')); }

        $values = [];
        foreach ((array)($template['fields'] ?? []) as $field) {
            $key = (string)$field['key'];
            $raw = $posted['fields'][$key] ?? '';
            if (($field['type'] ?? '') === 'checkbox') { $value = !empty($raw) ? 'Yes' : 'No'; }
            elseif (($field['type'] ?? '') === 'textarea') { $value = sanitize_textarea_field((string)$raw); }
            elseif (($field['type'] ?? '') === 'number') { $value = is_numeric($raw) ? (string)(float)$raw : ''; }
            else { $value = sanitize_text_field((string)$raw); }
            if (!empty($field['required']) && trim($value) === '') {
                return new WP_Error('required_field', sprintf(__('%s is required.', 'elev8-os'), $field['label']));
            }
            $values[$key] = $value;
        }
        $entry_date = sanitize_text_field((string)($posted['entry_date'] ?? current_time('Y-m-d')));
        $location = sanitize_text_field((string)($posted['location'] ?? ''));
        $title = sprintf('%s — %s — %s', $template['name'], get_the_author_meta('display_name', $user_id), $entry_date);
        $search_parts = [$title, $location];
        foreach ($template['fields'] as $field) {
            $key = $field['key'];
            if (!empty($values[$key])) { $search_parts[] = $field['label'] . ': ' . $values[$key]; }
        }
        $post_id = wp_insert_post([
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'post_title' => $title,
            'post_content' => implode("\n", $search_parts),
            'post_author' => $user_id,
        ], true);
        if (is_wp_error($post_id)) { return $post_id; }
        update_post_meta($post_id, self::META_TEMPLATE, $template_key);
        update_post_meta($post_id, self::META_FIELDS, $values);
        update_post_meta($post_id, self::META_STATUS, 'new');
        update_post_meta($post_id, self::META_ATTENTION, !empty($posted['owner_attention']) ? 1 : 0);
        update_post_meta($post_id, self::META_LOCATION, $location);
        update_post_meta($post_id, self::META_ENTRY_DATE, $entry_date);
        $attachment_ids = self::handle_attachments($post_id, $files);
        update_post_meta($post_id, self::META_ATTACHMENTS, $attachment_ids);
        do_action('elev8_os_operations_entry_created', $post_id, $template_key, $values);
        return (int)$post_id;
    }

    private static function handle_attachments(int $post_id, array $files): array {
        if (empty($files['attachments']['name']) || !is_array($files['attachments']['name'])) { return []; }
        if (!function_exists('media_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }
        $ids = [];
        $count = min(5, count($files['attachments']['name']));
        for ($i=0; $i<$count; $i++) {
            if ((int)$files['attachments']['error'][$i] !== UPLOAD_ERR_OK) { continue; }
            $_FILES['elev8_ops_single_attachment'] = [
                'name' => $files['attachments']['name'][$i],
                'type' => $files['attachments']['type'][$i],
                'tmp_name' => $files['attachments']['tmp_name'][$i],
                'error' => $files['attachments']['error'][$i],
                'size' => $files['attachments']['size'][$i],
            ];
            $id = media_handle_upload('elev8_ops_single_attachment', $post_id, [], ['test_form' => false]);
            if (!is_wp_error($id)) { $ids[] = (int)$id; }
        }
        unset($_FILES['elev8_ops_single_attachment']);
        return $ids;
    }

    public static function update_status(int $entry_id, string $status): bool {
        if (!in_array($status, ['new','reviewed','waiting','completed'], true)) { return false; }
        return (bool)update_post_meta($entry_id, self::META_STATUS, $status);
    }

    public static function entries(array $args = []): array {
        $defaults = ['posts_per_page'=>30, 'paged'=>1, 's'=>'', 'status'=>'', 'template'=>'', 'author'=>0, 'attention'=>''];
        $args = wp_parse_args($args, $defaults);
        $meta = [];
        if ($args['status']) { $meta[] = ['key'=>self::META_STATUS, 'value'=>sanitize_key($args['status'])]; }
        if ($args['template']) { $meta[] = ['key'=>self::META_TEMPLATE, 'value'=>sanitize_key($args['template'])]; }
        if ($args['attention'] !== '') { $meta[] = ['key'=>self::META_ATTENTION, 'value'=>(int)$args['attention']]; }
        $query = new WP_Query([
            'post_type'=>self::POST_TYPE, 'post_status'=>'publish', 'posts_per_page'=>(int)$args['posts_per_page'],
            'paged'=>(int)$args['paged'], 's'=>sanitize_text_field((string)$args['s']), 'author'=>(int)$args['author'],
            'meta_query'=>$meta, 'orderby'=>'date', 'order'=>'DESC'
        ]);
        return ['items'=>$query->posts, 'total'=>(int)$query->found_posts, 'pages'=>(int)$query->max_num_pages];
    }

    public static function entry(int $id): ?array {
        $post = get_post($id);
        if (!$post || $post->post_type !== self::POST_TYPE) { return null; }
        return [
            'post'=>$post,
            'template_key'=>(string)get_post_meta($id,self::META_TEMPLATE,true),
            'fields'=>(array)get_post_meta($id,self::META_FIELDS,true),
            'status'=>(string)get_post_meta($id,self::META_STATUS,true),
            'attention'=>(bool)get_post_meta($id,self::META_ATTENTION,true),
            'location'=>(string)get_post_meta($id,self::META_LOCATION,true),
            'entry_date'=>(string)get_post_meta($id,self::META_ENTRY_DATE,true),
            'attachments'=>(array)get_post_meta($id,self::META_ATTACHMENTS,true),
        ];
    }

    public static function default_templates(): array {
        $t = [];
        $make = static function($key,$name,$description,$roles,$fields) use (&$t){$t[$key]=compact('key','name','description','roles','fields')+['custom'=>false];};
        $f = static function($key,$label,$type='textarea',$required=false,$options=[]){return compact('key','label','type','required','options');};
        $make('manager','Manager Operating Log','Record what happened across the business and what needs owner attention.', ['administrator','editor','shop_manager'], [
            $f('locations_worked','Locations worked','text',true),$f('hours_worked','Hours worked','number'),$f('accomplishments','Accomplishments'),$f('problems_discovered','Problems discovered'),$f('problems_solved','Problems solved'),$f('employee_coaching','Employee coaching'),$f('customer_issues','Customer issues'),$f('maintenance_issues','Maintenance issues'),$f('business_improvements','Business improvements'),$f('owner_attention_items','Items requiring owner attention'),$f('general_notes','General notes')]);
        $make('retail','Retail Employee Log','Capture shift activity, customer demand, inventory signals, and store needs.', ['administrator','editor','shop_manager','author','contributor','subscriber'], [
            $f('store_worked','Store worked','text',true),$f('shift','Shift','text'),$f('cleaning_completed','Cleaning completed'),$f('displays_updated','Displays updated'),$f('customer_requests','Customer requests'),$f('inventory_low','Inventory running low'),$f('products_requested','Products customers asked for'),$f('sales_wins','Sales wins'),$f('returns','Returns'),$f('compliments','Customer compliments'),$f('complaints','Customer complaints'),$f('ideas','Ideas'),$f('manager_assistance','Need manager assistance')]);
        $make('artist','Artist Operating Log','Preserve class results, student feedback, supply needs, and future ideas.', ['administrator','editor','author','contributor','subscriber'], [
            $f('class_taught','Class taught','text',true),$f('attendance','Attendance','number'),$f('student_engagement','Student engagement'),$f('student_feedback','Student feedback'),$f('supplies_low','Supplies running low'),$f('equipment_issues','Equipment issues'),$f('future_class_ideas','Future class ideas'),$f('teach_again','Would teach again','select',false,['Yes','Maybe','No']),$f('general_notes','General notes')]);
        $make('maintenance','Maintenance Log','Create an operational record for equipment and facility issues.', [], [
            $f('equipment','Equipment','text',true),$f('problem_location','Location','text',true),$f('problem','Problem','textarea',true),$f('priority','Priority','select',true,['Low','Normal','High','Urgent']),$f('assigned_to','Assigned to','text'),$f('work_status','Status','select',false,['Reported','Assigned','In progress','Waiting','Completed']),$f('completed_date','Completed date','date')]);
        $make('vendor','Vendor Log','Track deliveries, backorders, price changes, new products, and vendor recommendations.', ['administrator','editor','shop_manager'], [
            $f('vendor','Vendor','text',true),$f('products_delivered','Products delivered'),$f('backorders','Backorders'),$f('price_changes','Price changes'),$f('new_products','New products'),$f('suggestions','Suggestions')]);
        $make('event','Event Log','Record attendance, sales, partners, customer feedback, and lessons learned.', ['administrator','editor','shop_manager','author'], [
            $f('event_name','Event','text',true),$f('attendance','Attendance','number'),$f('sales','Sales','number'),$f('vendor_count','Vendor count','number'),$f('music','Music'),$f('food_vendors','Food vendors'),$f('customer_comments','Customer comments'),$f('problems','Problems'),$f('lessons_learned','Lessons learned')]);
        return $t;
    }
}

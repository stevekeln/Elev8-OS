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
    const META_GUEST_NAME = '_elev8_ops_guest_name';
    const META_GUEST_EMAIL = '_elev8_ops_guest_email';
    const META_INVITE_CONSENT = '_elev8_ops_invite_consent';

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
        if (!is_array($templates)) { $templates = []; }
        $merged = $templates;
        foreach (self::default_templates() as $key => $template) {
            if (!isset($merged[$key])) { $merged[$key] = $template; }
        }
        if ($merged !== $templates) { update_option(self::OPTION_TEMPLATES, $merged, false); }
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

    public static function public_templates(): array {
        return array_filter(self::templates(), static function(array $template): bool { return !empty($template['public']); });
    }

    public static function templates_for_user(int $user_id, bool $include_public = false): array {
        if (user_can($user_id, 'manage_options')) { return self::templates(); }
        $user = get_userdata($user_id);
        if (!$user) { return []; }
        $roles = (array)$user->roles;
        $available = [];
        foreach (self::templates() as $key => $template) {
            if (!empty($template['public'])) {
                if ($include_public) { $available[$key] = $template; }
                continue;
            }
            $allowed = (array)($template['roles'] ?? []);
            if (!$allowed || array_intersect($roles, $allowed)) { $available[$key] = $template; }
        }
        return $available;
    }

    public static function save_entry(array $posted, array $files, int $user_id, bool $allow_public = false) {
        $template_key = sanitize_key((string)($posted['template'] ?? ''));
        $template = self::template($template_key);
        if (!$template) { return new WP_Error('invalid_template', __('That report template is unavailable.', 'elev8-os')); }
        $is_public = !empty($template['public']);
        if ($is_public) {
            if (!$allow_public) { return new WP_Error('forbidden_template', __('This public check-in must be submitted through the Check-In Center.', 'elev8-os')); }
        } elseif (!isset(self::templates_for_user($user_id)[$template_key])) {
            return new WP_Error('forbidden_template', __('You cannot submit this report type.', 'elev8-os'));
        }

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
        $guest_name = sanitize_text_field((string)($posted['guest_name'] ?? ''));
        $guest_email = sanitize_email((string)($posted['guest_email'] ?? ''));
        $person = $user_id > 0 ? get_the_author_meta('display_name', $user_id) : ($guest_name !== '' ? $guest_name : __('Guest', 'elev8-os'));
        $title = sprintf('%s — %s — %s', $template['name'], $person, $entry_date);
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
        update_post_meta($post_id, self::META_GUEST_NAME, $guest_name);
        update_post_meta($post_id, self::META_GUEST_EMAIL, $guest_email);
        update_post_meta($post_id, self::META_INVITE_CONSENT, !empty($posted['invite_consent']) ? 1 : 0);
        if ($is_public && $guest_email !== '' && !empty($posted['invite_consent'])) {
            self::send_thank_you_email($guest_email, $guest_name, $template);
        }
        do_action('elev8_os_operations_entry_created', $post_id, $template_key, $values);
        return (int)$post_id;
    }

    private static function send_thank_you_email(string $email, string $name, array $template): void {
        $event_name = (string)($template['name'] ?? __('Elev8 event', 'elev8-os'));
        $subject = sprintf(__('Thank you for checking in at %s', 'elev8-os'), $event_name);
        $greeting = $name !== '' ? sprintf(__('Hi %s,', 'elev8-os'), $name) : __('Hello,', 'elev8-os');
        $message = $greeting . "\n\n";
        $message .= sprintf(__('Thank you for being part of %s. Your feedback helps Elev8 Arts create better events and experiences.', 'elev8-os'), $event_name) . "\n\n";
        $message .= __('We would love to see you again at an upcoming Elev8 event.', 'elev8-os') . "\n\n";
        $message .= home_url('/') . "\n\n" . __('— Elev8 Arts', 'elev8-os');
        wp_mail($email, $subject, $message);
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
        $make = static function($key,$name,$description,$roles,$fields,$public=false) use (&$t){$t[$key]=compact('key','name','description','roles','fields','public')+['custom'=>false];};
        $f = static function($key,$label,$type='textarea',$required=false,$options=[]){return compact('key','label','type','required','options');};

        $make('art_walk','Art Walk Check-In','Tell us about your Art Walk experience and help us improve the next one.', [], [
            $f('attendee_type','I attended as','select',true,['Guest','Vendor','Artist','Performer','Volunteer','Food vendor']),$f('first_time','Was this your first Art Walk?','select',true,['Yes','No']),$f('experience_rating','Overall experience','select',true,['Excellent','Good','Okay','Needs improvement']),$f('favorite_part','Favorite part'),$f('sales','Vendor or artist sales','number'),$f('what_worked','What worked well?'),$f('what_improve','What should we improve?'),$f('return_next_time','Would you attend the next Art Walk?','select',true,['Yes','Maybe','No'])], true);
        $make('open_mic','Open Mic Check-In','Let us know how Open Mic went and whether you want to join us again.', [], [
            $f('attendee_type','I attended as','select',true,['Audience member','Performer','Volunteer','Vendor']),$f('performed','Did you perform?','select',false,['Yes','No']),$f('experience_rating','Overall experience','select',true,['Excellent','Good','Okay','Needs improvement']),$f('favorite_part','Favorite performer or moment'),$f('sound_quality','Sound quality','select',false,['Excellent','Good','Okay','Needs improvement']),$f('what_improve','What should we improve?'),$f('perform_next_time','Interested in performing next time?','select',false,['Yes','Maybe','No'])], true);
        $make('customer_feedback','Customer Feedback','Share a compliment, concern, request, or idea with Elev8.', [], [
            $f('visit_type','What brought you in?','select',false,['Shopping','Class','Event','Gallery visit','Other']),$f('experience_rating','Overall experience','select',true,['Excellent','Good','Okay','Needs improvement']),$f('compliment','What did we do well?'),$f('concern','Was there a problem?'),$f('request','What would you like us to offer?')], true);
        $make('class_feedback','Class Feedback','Help the artist and Elev8 improve future classes.', [], [
            $f('class_name','Class name','text',true),$f('artist_name','Teaching artist','text'),$f('experience_rating','Overall experience','select',true,['Excellent','Good','Okay','Needs improvement']),$f('learned','What did you learn or make?'),$f('artist_feedback','Feedback for the artist'),$f('future_classes','Classes you would like to take next')], true);
        $make('suggest_idea','Suggest an Idea','Tell us about an event, class, artist, product, or improvement you would like to see.', [], [
            $f('idea_type','Type of idea','select',true,['Class','Event','Artist','Product','Community program','Business improvement','Other']),$f('idea','Describe your idea','textarea',true),$f('why','Why would this help?'),$f('help_create','Would you like to help make it happen?','select',false,['Yes','Maybe','No'])], true);
        $make('class_request','Suggest a Class','Tell us what class you would like Elev8 Arts to offer.', [], [
            $f('class_topic','What class would you like to take?','text',true),$f('class_description','Tell us more about what you would like to learn or make','textarea',true),$f('experience_level','Your experience level','select',false,['Brand new','Beginner','Intermediate','Advanced','Not sure']),$f('preferred_schedule','Best days or times','text'),$f('group_interest','How many people may be interested?','select',false,['Just me','2–3 people','4–6 people','7 or more','Not sure']),$f('suggested_artist','Is there an artist or teacher you would like?','text'),$f('teach_or_connect','Could you teach this class or connect us with someone who can?','select',false,['Yes, I could teach it','I know someone','Maybe','No']),$f('extra_notes','Anything else we should know?')], true);
        $make('volunteer','Volunteer or Get Involved','Tell us how you would like to help Elev8 Arts and our community.', [], [
            $f('phone','Phone number','text'),$f('preferred_contact','Preferred way to contact you','select',true,['Email','Phone call','Text message']),$f('help_area','How would you like to help?','select',true,['Art Walk and events','Setup and cleanup','Greeting guests','Classes and workshops','Youth or senior programs','Community outreach','Promotions and social media','Fundraising','Administrative help','Maintenance or building projects','Other']),$f('availability','Availability','select',true,['One-time opportunity','Occasionally','Monthly','Weekly','Not sure yet']),$f('days_times','Best days and times','text'),$f('skills','Skills, interests, or experience','textarea'),$f('why_volunteer','Why would you like to get involved?','textarea'),$f('other_notes','Anything else we should know?')], true);
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

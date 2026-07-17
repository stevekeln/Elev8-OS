<?php
if (!defined('ABSPATH')) { exit; }

/** Artist Growth Center: verified Student CRM, notes, tags, and relationship timeline. */
final class Elev8_OS_Students_Module {
    private const SHORTCODE='elev8_artist_students';

    public static function init(): void {
        add_action('init',[__CLASS__,'register_shortcode']);
        add_action('wp_enqueue_scripts',[__CLASS__,'enqueue_assets']);
        add_action('admin_post_elev8_os_student_add_note',[__CLASS__,'handle_add_note']);
        add_action('admin_post_elev8_os_student_save_tags',[__CLASS__,'handle_save_tags']);
    }
    public static function activate(): void { if(class_exists('Elev8_OS_Student_Relationship_Service')){Elev8_OS_Student_Relationship_Service::activate();} }
    public static function register_shortcode(): void { add_shortcode(self::SHORTCODE,[__CLASS__,'shortcode']); }
    public static function enqueue_assets(): void {
        if(!Elev8_OS_Portal_Page_Manager::is_current_page('students')){return;}
        wp_enqueue_style('dashicons');
        wp_enqueue_style('elev8-os-students',ELEV8_OS_URL.'assets/css/artist-students.css',['elev8-os-artist-portal'],ELEV8_OS_VERSION);
    }

    public static function shortcode(): string {
        if(!is_user_logged_in()){
            return '<div class="elev8-dashboard-login"><p>'.esc_html__('Please log in to view your Student Relationship Center.','elev8-os').'</p><p><a class="button" href="'.esc_url(wp_login_url(Elev8_OS_Portal_Page_Manager::get_url('students'))).'">'.esc_html__('Log In','elev8-os').'</a></p></div>';
        }
        $user=wp_get_current_user();
        $snapshot=Elev8_OS_Student_Relationship_Service::get_snapshot($user);
        $selected_key=isset($_GET['student'])?sanitize_text_field(wp_unslash($_GET['student'])):'';
        $selected=$selected_key!==''?Elev8_OS_Student_Relationship_Service::get_student($user,$selected_key):null;
        $segment=isset($_GET['segment'])?sanitize_key((string)$_GET['segment']):'all';
        $query=isset($_GET['q'])?sanitize_text_field(wp_unslash($_GET['q'])):'';
        $students=$snapshot['students']??[];
        $students=self::filter_students($students,$segment,$query);
        ob_start(); ?>
        <div class="elev8-artist-dashboard elev8-students-page elev8-growth-center">
            <?php Elev8_OS_Artist_Portal_Module::render_navigation('students'); ?>
            <header class="elev8-dashboard-header elev8-growth-header">
                <div><p class="elev8-eyebrow"><?php esc_html_e('Artist Growth Center','elev8-os'); ?></p><h1><?php esc_html_e('Student Relationships','elev8-os'); ?></h1><p><?php esc_html_e('See every verified student relationship, remember important details, and know who needs follow-up.','elev8-os'); ?></p></div>
                <span class="elev8-dashboard-badge"><?php esc_html_e('Private CRM','elev8-os'); ?></span>
            </header>
            <?php if(isset($_GET['elev8_saved'])):?><div class="elev8-momentum-success"><span class="dashicons dashicons-yes-alt"></span><div><strong><?php esc_html_e('Saved','elev8-os'); ?></strong><p><?php esc_html_e('The student relationship was updated.','elev8-os'); ?></p></div></div><?php endif; ?>
            <?php if(empty($snapshot['available'])):?><div class="elev8-dashboard-warning"><p><strong><?php esc_html_e('Student relationships are unavailable.','elev8-os'); ?></strong><br><?php echo esc_html((string)$snapshot['reason']); ?></p></div><?php else: ?>
                <section class="elev8-student-summary elev8-growth-summary">
                    <?php self::summary_card(__('All Students','elev8-os'),(int)$snapshot['total'],'all'); ?>
                    <?php self::summary_card(__('Repeat Students','elev8-os'),(int)$snapshot['repeat'],'repeat'); ?>
                    <?php self::summary_card(__('New This Month','elev8-os'),(int)$snapshot['new_this_month'],'new'); ?>
                    <?php self::summary_card(__('Need Follow-up','elev8-os'),(int)$snapshot['inactive'],'inactive'); ?>
                    <?php self::summary_card(__('Upcoming','elev8-os'),(int)$snapshot['upcoming'],'upcoming'); ?>
                </section>
                <section class="elev8-student-toolbar">
                    <form method="get" action="<?php echo esc_url(Elev8_OS_Portal_Page_Manager::get_url('students')); ?>">
                        <input type="hidden" name="segment" value="<?php echo esc_attr($segment); ?>">
                        <label class="screen-reader-text" for="elev8-student-search"><?php esc_html_e('Search students','elev8-os'); ?></label>
                        <input id="elev8-student-search" type="search" name="q" value="<?php echo esc_attr($query); ?>" placeholder="<?php esc_attr_e('Search name, email, phone, or tag','elev8-os'); ?>">
                        <button class="button button-primary" type="submit"><?php esc_html_e('Search','elev8-os'); ?></button>
                        <?php if($query!==''):?><a class="button" href="<?php echo esc_url(add_query_arg('segment',$segment,Elev8_OS_Portal_Page_Manager::get_url('students'))); ?>"><?php esc_html_e('Clear','elev8-os'); ?></a><?php endif; ?>
                    </form>
                    <p><?php echo esc_html(sprintf(_n('%d relationship','%d relationships',count($students),'elev8-os'),count($students))); ?></p>
                </section>
                <div class="elev8-growth-layout">
                    <section class="elev8-student-directory">
                        <?php if(!$students):?><div class="elev8-student-empty"><span class="dashicons dashicons-groups"></span><h2><?php esc_html_e('No students match this view','elev8-os'); ?></h2><p><?php esc_html_e('Try another segment or clear the search.','elev8-os'); ?></p></div>
                        <?php else:?><div class="elev8-student-card-grid">
                            <?php foreach($students as $student): $url=add_query_arg(['student'=>(string)$student['customer_key'],'segment'=>$segment,'q'=>$query],Elev8_OS_Portal_Page_Manager::get_url('students')); ?>
                                <article class="elev8-student-card<?php echo $selected_key===$student['customer_key']?' is-selected':''; ?>">
                                    <div class="elev8-student-avatar"><?php echo esc_html(self::initials((string)$student['name'])); ?></div>
                                    <div class="elev8-student-card-body"><h2><a href="<?php echo esc_url($url); ?>"><?php echo esc_html($student['name']!==''?$student['name']:__('Name unavailable','elev8-os')); ?></a></h2>
                                        <p><?php echo esc_html((string)$student['email']); ?></p>
                                        <div class="elev8-student-metrics"><span><strong><?php echo esc_html(number_format_i18n((int)$student['classes_attended'])); ?></strong> <?php esc_html_e('classes','elev8-os'); ?></span><span><strong><?php echo esc_html(number_format_i18n((int)$student['upcoming_bookings'])); ?></strong> <?php esc_html_e('upcoming','elev8-os'); ?></span></div>
                                        <?php if($student['last_class_at']!==''):?><p class="elev8-student-last"><?php esc_html_e('Last class:','elev8-os'); ?> <?php echo esc_html((string)$student['last_class_name']); ?> · <?php echo esc_html(self::date((string)$student['last_class_at'])); ?></p><?php endif; ?>
                                        <div class="elev8-tag-list"><?php foreach(array_slice((array)$student['tags'],0,4) as $tag):?><span><?php echo esc_html((string)$tag); ?></span><?php endforeach; ?></div>
                                        <div class="elev8-student-card-actions"><a class="button" href="<?php echo esc_url($url); ?>"><?php esc_html_e('View Relationship','elev8-os'); ?></a><?php if($student['email']!==''):?><a class="button" href="mailto:<?php echo esc_attr((string)$student['email']); ?>"><?php esc_html_e('Email','elev8-os'); ?></a><?php endif; ?></div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div><?php endif; ?>
                    </section>
                    <?php if($selected): self::render_detail($selected,$segment,$query); else: ?>
                        <aside class="elev8-student-detail elev8-student-detail-empty"><span class="dashicons dashicons-id"></span><h2><?php esc_html_e('Choose a student','elev8-os'); ?></h2><p><?php esc_html_e('Open a relationship to view contact details, tags, notes, and timeline.','elev8-os'); ?></p></aside>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div><?php return (string)ob_get_clean();
    }

    /** @param array<string,mixed> $student */
    private static function render_detail(array $student,string $segment,string $query): void { $return=add_query_arg(array_filter(['student'=>(string)$student['customer_key'],'segment'=>$segment,'q'=>$query]),Elev8_OS_Portal_Page_Manager::get_url('students')); ?>
        <aside class="elev8-student-detail">
            <div class="elev8-detail-heading"><div class="elev8-student-avatar large"><?php echo esc_html(self::initials((string)$student['name'])); ?></div><div><p class="elev8-eyebrow"><?php esc_html_e('Relationship Profile','elev8-os'); ?></p><h2><?php echo esc_html((string)$student['name']); ?></h2><p><?php echo esc_html(sprintf(__('Customer since %s','elev8-os'),self::date((string)$student['first_class_at']))); ?></p></div></div>
            <div class="elev8-contact-actions"><?php if($student['email']!==''):?><a class="button button-primary" href="mailto:<?php echo esc_attr((string)$student['email']); ?>"><?php esc_html_e('Send Email','elev8-os'); ?></a><?php endif; ?><?php if($student['phone']!==''):?><a class="button" href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/','',(string)$student['phone'])); ?>"><?php esc_html_e('Call','elev8-os'); ?></a><?php endif; ?></div>
            <dl class="elev8-relationship-facts"><div><dt><?php esc_html_e('Classes attended','elev8-os'); ?></dt><dd><?php echo esc_html(number_format_i18n((int)$student['classes_attended'])); ?></dd></div><div><dt><?php esc_html_e('Upcoming bookings','elev8-os'); ?></dt><dd><?php echo esc_html(number_format_i18n((int)$student['upcoming_bookings'])); ?></dd></div><div><dt><?php esc_html_e('Seats booked','elev8-os'); ?></dt><dd><?php echo esc_html(number_format_i18n((int)$student['seats_total'])); ?></dd></div><div><dt><?php esc_html_e('Verified booked value','elev8-os'); ?></dt><dd><?php echo $student['lifetime_value']>0?esc_html(function_exists('wc_price')?wp_strip_all_tags(wc_price((float)$student['lifetime_value'])):'$'.number_format_i18n((float)$student['lifetime_value'],2)):esc_html__('Unavailable','elev8-os'); ?></dd></div></dl>
            <section class="elev8-detail-section"><h3><?php esc_html_e('Tags','elev8-os'); ?></h3><p><?php esc_html_e('Use commas to create useful groups such as Painting, Beginner, VIP, or Private Lesson.','elev8-os'); ?></p><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="elev8_os_student_save_tags"><input type="hidden" name="customer_key" value="<?php echo esc_attr((string)$student['customer_key']); ?>"><input type="hidden" name="return_url" value="<?php echo esc_url($return); ?>"><?php wp_nonce_field('elev8_os_student_save_tags'); ?><input type="text" name="tags" value="<?php echo esc_attr(implode(', ',(array)$student['tags'])); ?>"><button class="button" type="submit"><?php esc_html_e('Save Tags','elev8-os'); ?></button></form></section>
            <section class="elev8-detail-section"><h3><?php esc_html_e('Private Notes','elev8-os'); ?></h3><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="elev8_os_student_add_note"><input type="hidden" name="customer_key" value="<?php echo esc_attr((string)$student['customer_key']); ?>"><input type="hidden" name="return_url" value="<?php echo esc_url($return); ?>"><?php wp_nonce_field('elev8_os_student_add_note'); ?><textarea name="note" rows="3" placeholder="<?php esc_attr_e('Add something useful to remember…','elev8-os'); ?>" required></textarea><button class="button button-primary" type="submit"><?php esc_html_e('Add Note','elev8-os'); ?></button></form><?php foreach((array)$student['notes'] as $note):?><article class="elev8-note"><p><?php echo esc_html((string)$note['note_text']); ?></p><time><?php echo esc_html(self::date((string)$note['created_at'],true)); ?></time></article><?php endforeach; ?></section>
            <section class="elev8-detail-section"><h3><?php esc_html_e('Relationship Timeline','elev8-os'); ?></h3><div class="elev8-relationship-timeline"><?php foreach((array)$student['timeline'] as $event):?><article><span class="dashicons dashicons-marker"></span><div><strong><?php echo esc_html((string)$event['event_title']); ?></strong><?php if(!empty($event['event_detail'])):?><p><?php echo esc_html((string)$event['event_detail']); ?></p><?php endif; ?><time><?php echo esc_html(self::date((string)$event['event_date'],true)); ?></time></div></article><?php endforeach; ?></div></section>
        </aside><?php }

    public static function handle_add_note(): void { self::authorize('elev8_os_student_add_note'); $user=wp_get_current_user(); $key=sanitize_text_field(wp_unslash($_POST['customer_key']??'')); $note=sanitize_textarea_field(wp_unslash($_POST['note']??'')); if(Elev8_OS_Student_Relationship_Service::get_student($user,$key)){Elev8_OS_Student_Relationship_Service::add_note((int)$user->ID,$key,$note);} self::redirect(); }
    public static function handle_save_tags(): void { self::authorize('elev8_os_student_save_tags'); $user=wp_get_current_user(); $key=sanitize_text_field(wp_unslash($_POST['customer_key']??'')); $raw=sanitize_text_field(wp_unslash($_POST['tags']??'')); if(Elev8_OS_Student_Relationship_Service::get_student($user,$key)){Elev8_OS_Student_Relationship_Service::replace_tags((int)$user->ID,$key,array_map('trim',explode(',',$raw)));} self::redirect(); }
    private static function authorize(string $nonce): void { if(!is_user_logged_in()){wp_die(esc_html__('Please log in.','elev8-os'));} check_admin_referer($nonce); }
    private static function redirect(): void { $url=isset($_POST['return_url'])?esc_url_raw(wp_unslash($_POST['return_url'])):Elev8_OS_Portal_Page_Manager::get_url('students'); wp_safe_redirect(add_query_arg('elev8_saved','1',$url)); exit; }
    /** @param array<int,array<string,mixed>> $students @return array<int,array<string,mixed>> */
    private static function filter_students(array $students,string $segment,string $query): array { $now=current_time('timestamp'); $month=strtotime(wp_date('Y-m-01 00:00:00',$now)); return array_values(array_filter($students,static function($s)use($segment,$query,$now,$month){$match=true;if($segment==='repeat'){$match=(int)$s['classes_attended']>=2;}elseif($segment==='new'){$match=$s['first_class_at']!==''&&strtotime((string)$s['first_class_at'])>=$month;}elseif($segment==='inactive'){$match=$s['last_class_at']!==''&&strtotime((string)$s['last_class_at'])<strtotime('-90 days',$now);}elseif($segment==='upcoming'){$match=(int)$s['upcoming_bookings']>0;}if(!$match){return false;}if($query===''){return true;}$hay=strtolower(implode(' ',[(string)$s['name'],(string)$s['email'],(string)$s['phone'],implode(' ',(array)$s['tags'])]));return strpos($hay,strtolower($query))!==false;})); }
    private static function summary_card(string $label,int $value,string $segment): void { $url=add_query_arg('segment',$segment,Elev8_OS_Portal_Page_Manager::get_url('students')); echo '<a class="elev8-growth-stat" href="'.esc_url($url).'"><strong>'.esc_html(number_format_i18n($value)).'</strong><span>'.esc_html($label).'</span></a>'; }
    private static function initials(string $name): string { $parts=preg_split('/\s+/',trim($name));$out='';foreach(array_slice((array)$parts,0,2) as $part){$out.=strtoupper(substr((string)$part,0,1));}return $out!==''?$out:'?'; }
    private static function date(string $value,bool $time=false): string { $ts=strtotime($value); if(!$ts){return __('Unavailable','elev8-os');} return wp_date(get_option('date_format').($time?' '.get_option('time_format'):''),$ts); }
}

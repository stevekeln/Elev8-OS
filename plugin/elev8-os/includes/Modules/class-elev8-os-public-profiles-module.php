<?php
/**
 * Frontend public-profile editor, CEO directory, and public renderer.
 *
 * @package Elev8OS
 */
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Public_Profiles_Module {
    private const PAGE_OPTION='elev8_os_public_profile_editor_page_id';
    private const REWRITE_VERSION_OPTION='elev8_os_public_profile_rewrite_version';
    private const ADMIN_SLUG='elev8-public-profiles';

    public static function init(): void {
        add_action('init',[__CLASS__,'register_rewrite']);
        add_action('init',[__CLASS__,'ensure_editor_page'],30);
        add_action('template_redirect',[__CLASS__,'render_public_profile']);
        add_shortcode('elev8_public_profile_editor',[__CLASS__,'editor_shortcode']);
        add_action('wp_enqueue_scripts',[__CLASS__,'enqueue_assets']);
        add_action('admin_enqueue_scripts',[__CLASS__,'admin_assets']);
        add_action('admin_menu',[__CLASS__,'register_admin_page'],42);
        add_filter('query_vars',[__CLASS__,'query_vars']);
        add_filter('elev8_os_application_shell_frontend',[__CLASS__,'shell_support']);
    }

    public static function register_admin_page(): void {
        add_submenu_page('elev8-os',__('Public Profiles','elev8-os'),__('Public Profiles','elev8-os'),'manage_options',self::ADMIN_SLUG,[__CLASS__,'render_admin_page']);
    }
    public static function register_rewrite(): void { add_rewrite_rule('^people/([^/]+)/?$','index.php?elev8_public_profile=$matches[1]','top');$v=(string)get_option(self::REWRITE_VERSION_OPTION,'');if($v!==ELEV8_OS_VERSION){flush_rewrite_rules(false);update_option(self::REWRITE_VERSION_OPTION,ELEV8_OS_VERSION,false);} }
    public static function query_vars(array $vars): array { $vars[]='elev8_public_profile';return $vars; }
    public static function shell_support(bool $supported): bool { if($supported)return true;$path=trim((string)wp_parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH),'/');return $path==='elev8-profile'; }
    public static function ensure_editor_page(): void { $id=(int)get_option(self::PAGE_OPTION,0);if($id>0&&get_post_status($id))return;$existing=get_page_by_path('elev8-profile');if($existing instanceof WP_Post){update_option(self::PAGE_OPTION,(int)$existing->ID,false);return;}if(!is_user_logged_in())return;$id=wp_insert_post(['post_title'=>__('My Public Profile','elev8-os'),'post_name'=>'elev8-profile','post_content'=>'[elev8_public_profile_editor]','post_status'=>'publish','post_type'=>'page','post_author'=>get_current_user_id()],true);if(!is_wp_error($id)&&$id>0)update_option(self::PAGE_OPTION,(int)$id,false); }
    public static function enqueue_assets(): void { $slug=get_query_var('elev8_public_profile');$path=trim((string)wp_parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH),'/');if($slug===''&&$path!=='elev8-profile')return;wp_enqueue_style('elev8-os-public-profiles',ELEV8_OS_URL.'assets/css/public-profiles.css',[],ELEV8_OS_VERSION);if($path==='elev8-profile'&&is_user_logged_in()){wp_enqueue_media();wp_enqueue_script('elev8-os-public-profiles',ELEV8_OS_URL.'assets/js/public-profiles.js',['jquery'],ELEV8_OS_VERSION,true);} }
    public static function admin_assets(string $hook): void { if($hook!=='elev8-os_page_'.self::ADMIN_SLUG)return;wp_enqueue_style('elev8-os-public-profiles',ELEV8_OS_URL.'assets/css/public-profiles.css',[],ELEV8_OS_VERSION);wp_enqueue_style('dashicons');wp_enqueue_media();wp_enqueue_script('elev8-os-public-profiles',ELEV8_OS_URL.'assets/js/public-profiles.js',['jquery'],ELEV8_OS_VERSION,true); }

    public static function editor_shortcode(): string {
        if(!is_user_logged_in())return '<div class="elev8-profile-message">'.esc_html__('Please log in to manage your public profile.','elev8-os').'</div>';
        return self::render_editor(get_current_user_id(),false);
    }

    private static function render_editor(int $user_id,bool $admin): string {
        $notice='';
        if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['elev8_public_profile_save'])){check_admin_referer('elev8_public_profile_save_'.$user_id,'elev8_public_profile_nonce');$result=Elev8_OS_Public_Profile_Service::save($user_id,wp_unslash($_POST));$notice=sprintf('<div class="elev8-profile-message %s">%s</div>',!empty($result['success'])?'is-success':'is-error',esc_html((string)($result['message']??'')));}
        $profile=Elev8_OS_Public_Profile_Service::get($user_id);$public_url=Elev8_OS_Public_Profile_Service::public_url($user_id);$types=Elev8_OS_Public_Profile_Service::available_types();
        ob_start(); ?>
        <main class="elev8-profile-editor <?php echo $admin?'is-admin':''; ?>">
            <header class="elev8-profile-editor__header"><div><p class="elev8-profile-eyebrow"><?php esc_html_e('Public Identity','elev8-os'); ?></p><h1><?php echo esc_html($admin?sprintf(__('Edit %s','elev8-os'),$profile['display_name']):__('My Public Profile','elev8-os')); ?></h1><p><?php esc_html_e('One shared public identity can represent an artist, teacher, event host, manager, volunteer, or staff member.','elev8-os'); ?></p></div><span class="elev8-profile-status <?php echo !empty($profile['published'])?'is-published':'is-draft'; ?>"><?php echo !empty($profile['published'])?esc_html__('Published','elev8-os'):esc_html__('Not Published','elev8-os'); ?></span></header>
            <?php echo $notice; ?>
            <form method="post" class="elev8-profile-form">
                <?php wp_nonce_field('elev8_public_profile_save_'.$user_id,'elev8_public_profile_nonce'); ?>
                <section class="elev8-profile-card"><h2><?php esc_html_e('Public profile types','elev8-os'); ?></h2><p><?php esc_html_e('Choose every public identity this person should have. Private WordPress roles are not changed.','elev8-os'); ?></p><div class="elev8-profile-type-grid"><?php foreach($types as $key=>$label): ?><label><input type="checkbox" name="profile_types[]" value="<?php echo esc_attr($key); ?>" <?php checked(in_array($key,(array)$profile['profile_types'],true)); ?>><span><?php echo esc_html($label); ?></span></label><?php endforeach; ?></div></section>
                <section class="elev8-profile-card"><h2><?php esc_html_e('Public introduction','elev8-os'); ?></h2><div class="elev8-profile-grid">
                    <label><span><?php esc_html_e('Public display name','elev8-os'); ?></span><input type="text" name="display_name" value="<?php echo esc_attr((string)$profile['display_name']); ?>" required></label>
                    <label><span><?php esc_html_e('Public page address','elev8-os'); ?></span><div class="elev8-profile-slug"><small><?php echo esc_html(home_url('/people/')); ?></small><input type="text" name="slug" value="<?php echo esc_attr((string)$profile['slug']); ?>"></div></label>
                    <label class="is-wide"><span><?php esc_html_e('Headline','elev8-os'); ?></span><input type="text" name="headline" value="<?php echo esc_attr((string)$profile['headline']); ?>"></label>
                    <label class="is-wide"><span><?php esc_html_e('Biography','elev8-os'); ?></span><textarea name="bio" rows="7"><?php echo esc_textarea((string)$profile['bio']); ?></textarea></label>
                </div></section>
                <section class="elev8-profile-card"><h2><?php esc_html_e('Personal branding','elev8-os'); ?></h2><p><?php esc_html_e('Upload images directly to the WordPress Media Library. Elev8 OS stores the media attachment so the profile remains portable if the site address changes.','elev8-os'); ?></p><div class="elev8-profile-media-grid">
                    <?php echo self::render_media_control('photo',__('Profile photo','elev8-os'),__('Choose a clear square or portrait image.','elev8-os'),(int)$profile['photo_id'],(string)$profile['photo_url']); ?>
                    <?php echo self::render_media_control('cover',__('Cover image','elev8-os'),__('Choose a wide image that represents this person or their work.','elev8-os'),(int)$profile['cover_id'],(string)$profile['cover_url']); ?>
                </div></section>
                <section class="elev8-profile-card"><h2><?php esc_html_e('Links and contact','elev8-os'); ?></h2><div class="elev8-profile-grid">
                    <label><span><?php esc_html_e('Website','elev8-os'); ?></span><input type="url" name="website_url" value="<?php echo esc_attr((string)$profile['website_url']); ?>"></label><label><span><?php esc_html_e('Public email','elev8-os'); ?></span><input type="email" name="contact_email" value="<?php echo esc_attr((string)$profile['contact_email']); ?>"></label><label><span><?php esc_html_e('Instagram','elev8-os'); ?></span><input type="url" name="instagram_url" value="<?php echo esc_attr((string)$profile['instagram_url']); ?>"></label><label><span><?php esc_html_e('Facebook','elev8-os'); ?></span><input type="url" name="facebook_url" value="<?php echo esc_attr((string)$profile['facebook_url']); ?>"></label>
                </div></section>
                <section class="elev8-profile-publish"><label class="elev8-profile-publish__control"><input type="checkbox" name="publish" value="1" <?php checked(!empty($profile['published'])); ?>><span><strong><?php esc_html_e('Make this public profile active','elev8-os'); ?></strong><small><?php esc_html_e('When active, customers and guests can view this person on Elev8 Arts.','elev8-os'); ?></small></span></label><div><?php if(!empty($profile['published'])&&$public_url!==''): ?><a class="elev8-profile-secondary" href="<?php echo esc_url($public_url); ?>" target="_blank" rel="noopener"><?php esc_html_e('Preview','elev8-os'); ?></a><?php endif; ?><button class="elev8-profile-primary" type="submit" name="elev8_public_profile_save" value="1"><?php esc_html_e('Save Profile','elev8-os'); ?></button></div></section>
            </form>
        </main><?php return (string)ob_get_clean();
    }

    private static function render_media_control(string $key,string $label,string $help,int $attachment_id,string $url): string {
        $has_image=$url!=='';
        ob_start(); ?>
        <div class="elev8-profile-media" data-elev8-media-control data-media-title="<?php echo esc_attr($label); ?>">
            <div class="elev8-profile-media__heading"><strong><?php echo esc_html($label); ?></strong><small><?php echo esc_html($help); ?></small></div>
            <div class="elev8-profile-media__preview <?php echo $has_image?'has-image':'is-empty'; ?>" data-media-preview>
                <?php if($has_image): ?><img src="<?php echo esc_url($url); ?>" alt=""><?php else: ?><span class="dashicons dashicons-format-image" aria-hidden="true"></span><em><?php esc_html_e('No image uploaded','elev8-os'); ?></em><?php endif; ?>
            </div>
            <input type="hidden" name="<?php echo esc_attr($key); ?>_attachment_id" value="<?php echo esc_attr((string)$attachment_id); ?>" data-media-id>
            <input type="hidden" name="<?php echo esc_attr($key); ?>_legacy_url" value="<?php echo esc_attr($attachment_id>0?'':$url); ?>" data-media-legacy-url>
            <div class="elev8-profile-media__actions">
                <button type="button" class="button button-primary" data-media-select><?php echo esc_html($has_image?__('Replace image','elev8-os'):__('Upload image','elev8-os')); ?></button>
                <button type="button" class="button <?php echo $has_image?'':'is-hidden'; ?>" data-media-remove><?php esc_html_e('Remove','elev8-os'); ?></button>
            </div>
        </div><?php return (string)ob_get_clean();
    }

    public static function render_admin_page(): void {
        if(!current_user_can('manage_options'))wp_die(esc_html__('You do not have permission to manage public profiles.','elev8-os'));
        $user_id=absint($_GET['user_id']??0);echo '<div class="wrap elev8-public-profiles-admin">';
        if($user_id>0){echo '<p><a href="'.esc_url(Elev8_OS_Public_Profile_Service::admin_url()).'">← '.esc_html__('Back to Public Profiles','elev8-os').'</a></p>';echo self::render_editor($user_id,true);echo '</div>';return;}
        $type=sanitize_key((string)($_GET['profile_type']??''));$status=sanitize_key((string)($_GET['profile_status']??''));$search=sanitize_text_field((string)($_GET['s']??''));$rows=Elev8_OS_Public_Profile_Service::directory(['type'=>$type,'status'=>$status,'search'=>$search]);$summary=Elev8_OS_Public_Profile_Service::summary(); ?>
        <div class="elev8-profile-admin-header"><div><p class="elev8-profile-eyebrow"><?php esc_html_e('Public Identity','elev8-os'); ?></p><h1><?php esc_html_e('Public Profiles','elev8-os'); ?></h1><p><?php esc_html_e('Manage artists, teachers, event hosts, managers, volunteers, and staff from one place.','elev8-os'); ?></p></div></div>
        <div class="elev8-profile-summary"><?php foreach(['published'=>__('Published','elev8-os'),'draft'=>__('Draft / Unpublished','elev8-os'),'missing'=>__('Missing Profile','elev8-os'),'incomplete'=>__('Incomplete','elev8-os')] as $key=>$label): ?><div><strong><?php echo esc_html((string)$summary[$key]); ?></strong><span><?php echo esc_html($label); ?></span></div><?php endforeach; ?></div>
        <form method="get" class="elev8-profile-filters"><input type="hidden" name="page" value="<?php echo esc_attr(self::ADMIN_SLUG); ?>"><input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search people','elev8-os'); ?>"><select name="profile_type"><option value=""><?php esc_html_e('All profile types','elev8-os'); ?></option><?php foreach(Elev8_OS_Public_Profile_Service::available_types() as $k=>$label): ?><option value="<?php echo esc_attr($k); ?>" <?php selected($type,$k); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select><select name="profile_status"><option value=""><?php esc_html_e('All statuses','elev8-os'); ?></option><option value="published" <?php selected($status,'published'); ?>><?php esc_html_e('Published','elev8-os'); ?></option><option value="draft" <?php selected($status,'draft'); ?>><?php esc_html_e('Draft / Unpublished','elev8-os'); ?></option><option value="missing" <?php selected($status,'missing'); ?>><?php esc_html_e('Missing Profile','elev8-os'); ?></option></select><button class="button button-primary"><?php esc_html_e('Filter','elev8-os'); ?></button></form>
        <div class="elev8-profile-directory"><?php if(!$rows): ?><div class="elev8-profile-card"><p><?php esc_html_e('No profiles match these filters.','elev8-os'); ?></p></div><?php endif; foreach($rows as $row): ?><article class="elev8-profile-person-row"><div class="elev8-profile-person-avatar"><?php echo esc_html(strtoupper(substr((string)$row['display_name'],0,1))); ?></div><div class="elev8-profile-person-main"><h2><?php echo esc_html((string)$row['display_name']); ?></h2><p><?php echo esc_html((string)$row['role_label']); ?></p><div class="elev8-profile-person-meta"><span class="elev8-profile-status <?php echo $row['status']==='published'?'is-published':'is-draft'; ?>"><?php echo esc_html($row['status']==='published'?__('Published','elev8-os'):($row['status']==='missing'?__('Missing Profile','elev8-os'):__('Not Published','elev8-os'))); ?></span><span><?php echo esc_html(sprintf(__('%d%% complete','elev8-os'),(int)$row['completeness'])); ?></span></div></div><div class="elev8-profile-person-actions"><a class="button button-primary" href="<?php echo esc_url(Elev8_OS_Public_Profile_Service::admin_url(['user_id'=>(int)$row['user_id']])); ?>"><?php echo esc_html($row['status']==='missing'?__('Create Profile','elev8-os'):__('Edit Profile','elev8-os')); ?></a><?php if($row['status']==='published'): ?><a class="button" href="<?php echo esc_url(Elev8_OS_Public_Profile_Service::public_url((int)$row['user_id'])); ?>" target="_blank" rel="noopener"><?php esc_html_e('Preview','elev8-os'); ?></a><?php endif; ?></div></article><?php endforeach; ?></div></div><?php
    }

    public static function render_public_profile(): void {
        $slug=sanitize_title((string)get_query_var('elev8_public_profile'));if($slug==='')return;$user_id=Elev8_OS_Public_Profile_Service::user_id_from_slug($slug);if($user_id<=0||!Elev8_OS_Public_Profile_Service::is_published($user_id)){status_header(404);nocache_headers();include get_404_template();exit;}$p=Elev8_OS_Public_Profile_Service::get($user_id);status_header(200);nocache_headers();?><!doctype html><html <?php language_attributes(); ?>><head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?php echo esc_html((string)$p['display_name']); ?> | Elev8 Arts</title><?php wp_head(); ?></head><body <?php body_class('elev8-public-profile-page'); ?>><?php wp_body_open(); ?><main class="elev8-public-person"><a class="elev8-public-person__home" href="<?php echo esc_url(home_url('/')); ?>">← <?php esc_html_e('Elev8 Arts','elev8-os'); ?></a><article class="elev8-public-person__card"><?php if(!empty($p['cover_url'])): ?><div class="elev8-public-person__cover" style="background-image:url('<?php echo esc_url((string)$p['cover_url']); ?>')"></div><?php endif; ?><div class="elev8-public-person__content"><?php if(!empty($p['photo_url'])): ?><img class="elev8-public-person__photo" src="<?php echo esc_url((string)$p['photo_url']); ?>" alt="<?php echo esc_attr((string)$p['display_name']); ?>"><?php else: ?><div class="elev8-public-person__photo is-placeholder"><?php echo esc_html(strtoupper(substr((string)$p['display_name'],0,1))); ?></div><?php endif; ?><p class="elev8-profile-eyebrow"><?php echo esc_html((string)$p['role_label']); ?></p><h1><?php echo esc_html((string)$p['display_name']); ?></h1><?php if(!empty($p['headline'])): ?><p class="elev8-public-person__headline"><?php echo esc_html((string)$p['headline']); ?></p><?php endif; ?><div class="elev8-public-person__bio"><?php echo wpautop(esc_html((string)$p['bio'])); ?></div><div class="elev8-public-person__links"><?php if(!empty($p['contact_email'])): ?><a href="mailto:<?php echo esc_attr((string)$p['contact_email']); ?>"><?php esc_html_e('Email','elev8-os'); ?></a><?php endif; ?><?php foreach(['website_url'=>__('Website','elev8-os'),'instagram_url'=>__('Instagram','elev8-os'),'facebook_url'=>__('Facebook','elev8-os')] as $k=>$label)if(!empty($p[$k]))echo '<a href="'.esc_url((string)$p[$k]).'" target="_blank" rel="noopener">'.esc_html($label).'</a>'; ?></div></div></article></main><?php wp_footer(); ?></body></html><?php exit;
    }
}

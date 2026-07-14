<?php
if (!defined('ABSPATH')) { exit; }
final class Elev8_OS_Artist_Website_Editor_Module {
    private const PAGE_SLUG='artist-website';
    private const SHORTCODE='elev8_artist_website_editor';
    private const PAGE_OPTION='elev8_os_artist_website_editor_page_id';
    private const META_BUSINESS='elev8_os_artist_business_name';
    private const META_TAGLINE='elev8_os_artist_tagline';
    private const META_BIO='elev8_os_artist_bio';
    private const META_PHONE='elev8_os_artist_phone';
    private const META_EMAIL='elev8_os_artist_public_email';
    private const META_PROFILE='elev8_os_artist_profile_image_id';
    private const META_BANNER='elev8_os_artist_banner_image_id';
    private const META_LOGO='elev8_os_artist_logo_image_id';
    private const META_SOCIAL='elev8_os_artist_social_links';
    private const META_PAYMENT='elev8_os_artist_payment_links';
    public static function init():void{add_action('init',[__CLASS__,'register_shortcode']);add_action('init',[__CLASS__,'ensure_page'],35);add_action('wp_enqueue_scripts',[__CLASS__,'enqueue_assets']);}
    public static function status():string{return 'active';}
    public static function register_shortcode():void{add_shortcode(self::SHORTCODE,[__CLASS__,'shortcode']);}
    public static function ensure_page():void{
        $id=(int)get_option(self::PAGE_OPTION); if($id&&get_post_status($id))return;
        $existing=get_page_by_path(self::PAGE_SLUG); if($existing instanceof WP_Post){update_option(self::PAGE_OPTION,(int)$existing->ID,false);return;}
        if(!current_user_can('manage_options'))return;
        $id=wp_insert_post(['post_title'=>__('Edit My Website','elev8-os'),'post_name'=>self::PAGE_SLUG,'post_content'=>'['.self::SHORTCODE.']','post_status'=>'publish','post_type'=>'page','post_author'=>get_current_user_id()],true);
        if(!is_wp_error($id)&&$id>0)update_option(self::PAGE_OPTION,(int)$id,false);
    }
    public static function enqueue_assets():void{if(!self::is_page())return;wp_enqueue_style('dashicons');wp_enqueue_style('elev8-os-artist-portal',ELEV8_OS_URL.'assets/css/artist-portal.css',[],ELEV8_OS_VERSION);wp_enqueue_style('elev8-os-artist-website-editor',ELEV8_OS_URL.'assets/css/artist-website-editor.css',['elev8-os-artist-portal'],ELEV8_OS_VERSION);}
    public static function shortcode():string{
        if(!is_user_logged_in())return '<div class="elev8-editor-message is-error">'.esc_html__('Please log in to edit your artist website.','elev8-os').'</div>';
        $user=wp_get_current_user(); if(!self::is_artist($user)&&!current_user_can('manage_options'))return '<div class="elev8-editor-message is-error">'.esc_html__('This account is not connected to an Elev8 Member Artist.','elev8-os').'</div>';
        $message=self::save($user);ob_start();self::render($user,$message);return (string)ob_get_clean();
    }
    private static function render(WP_User $user,array $message):void{
        $social=self::links(get_user_meta($user->ID,self::META_SOCIAL,true));$payment=self::links(get_user_meta($user->ID,self::META_PAYMENT,true));
        $public=(string)get_user_meta($user->ID,'elev8_os_public_artist_page_url',true);
        ?>
        <div class="elev8-artist-editor">
        <?php Elev8_OS_Artist_Portal_Module::render_navigation('edit_website'); ?>
        <header class="elev8-editor-header"><div><p class="elev8-editor-eyebrow">Artist Portal</p><h1>Edit My Website</h1><p>Update what visitors see on your public artist page.</p></div><?php if($public!==''):?><a class="elev8-editor-secondary-button" href="<?php echo esc_url($public);?>" target="_blank" rel="noopener">View My Website</a><?php endif;?></header>
        <?php if($message['text']!==''):?><div class="elev8-editor-message <?php echo $message['success']?'is-success':'is-error';?>"><?php echo esc_html($message['text']);?></div><?php endif;?>
        <form class="elev8-editor-form" method="post" enctype="multipart/form-data"><?php wp_nonce_field('elev8_os_save_artist_website','elev8_os_artist_website_nonce');?><input type="hidden" name="elev8_os_artist_website_action" value="save">
        <section class="elev8-editor-section"><h2>Basic Information</h2><div class="elev8-editor-fields">
        <?php self::field('first_name','First Name',$user->first_name,true);self::field('last_name','Last Name',$user->last_name,true);self::field('business_name','Artist or Business Name',(string)get_user_meta($user->ID,self::META_BUSINESS,true));self::field('tagline','Short Tagline',(string)get_user_meta($user->ID,self::META_TAGLINE,true));self::field('phone','Phone',(string)get_user_meta($user->ID,self::META_PHONE,true),false,'tel');self::field('public_email','Public Email',(string)get_user_meta($user->ID,self::META_EMAIL,true),false,'email');?>
        </div><label class="elev8-editor-full-field"><span>Biography</span><textarea name="bio" rows="8"><?php echo esc_textarea((string)get_user_meta($user->ID,self::META_BIO,true));?></textarea></label></section>
        <section class="elev8-editor-section"><h2>Images</h2><p class="elev8-editor-help">Accepted formats: JPG, PNG, and WebP.</p><div class="elev8-image-grid"><?php self::image('profile_image','Profile Photo',(int)get_user_meta($user->ID,self::META_PROFILE,true));self::image('banner_image','Banner Image',(int)get_user_meta($user->ID,self::META_BANNER,true));self::image('logo_image','Logo',(int)get_user_meta($user->ID,self::META_LOGO,true));?></div></section>
        <section class="elev8-editor-section"><h2>Social Links</h2><?php self::link_rows('social',$social);?></section>
        <section class="elev8-editor-section"><h2>Payment and Contact Links</h2><?php self::link_rows('payment',$payment);?></section>
        <div class="elev8-editor-actions"><button class="elev8-editor-primary-button" type="submit">Save Changes</button><?php if($public!==''):?><button class="elev8-editor-secondary-button" type="submit" name="save_and_view" value="1">Save and View Website</button><?php endif;?></div>
        </form></div><?php
    }
    private static function save(WP_User $user):array{
        if(($_POST['elev8_os_artist_website_action']??'')!=='save')return ['success'=>false,'text'=>''];
        if(empty($_POST['elev8_os_artist_website_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['elev8_os_artist_website_nonce'])),'elev8_os_save_artist_website'))return ['success'=>false,'text'=>'Security check failed. Please refresh and try again.'];
        $first=sanitize_text_field(wp_unslash($_POST['first_name']??''));$last=sanitize_text_field(wp_unslash($_POST['last_name']??''));if($first===''||$last==='')return ['success'=>false,'text'=>'First and last name are required.'];
        wp_update_user(['ID'=>$user->ID,'first_name'=>$first,'last_name'=>$last,'display_name'=>trim($first.' '.$last)]);
        self::meta($user->ID,self::META_BUSINESS,sanitize_text_field(wp_unslash($_POST['business_name']??'')));self::meta($user->ID,self::META_TAGLINE,sanitize_text_field(wp_unslash($_POST['tagline']??'')));self::meta($user->ID,self::META_BIO,wp_kses_post(wp_unslash($_POST['bio']??'')));self::meta($user->ID,self::META_PHONE,sanitize_text_field(wp_unslash($_POST['phone']??'')));self::meta($user->ID,self::META_EMAIL,sanitize_email(wp_unslash($_POST['public_email']??'')));
        self::save_links($user->ID,'social',self::META_SOCIAL);self::save_links($user->ID,'payment',self::META_PAYMENT);$u=self::uploads($user->ID);if(!$u['success'])return $u;
        $public=(string)get_user_meta($user->ID,'elev8_os_public_artist_page_url',true);if(!empty($_POST['save_and_view'])&&$public!==''){wp_safe_redirect($public);exit;}
        return ['success'=>true,'text'=>'Your website information has been saved.'];
    }
    private static function uploads(int $uid):array{
        if(!function_exists('media_handle_upload')){require_once ABSPATH.'wp-admin/includes/file.php';require_once ABSPATH.'wp-admin/includes/media.php';require_once ABSPATH.'wp-admin/includes/image.php';}
        $map=['profile_image'=>[self::META_PROFILE,5*MB_IN_BYTES],'banner_image'=>[self::META_BANNER,8*MB_IN_BYTES],'logo_image'=>[self::META_LOGO,3*MB_IN_BYTES]];
        foreach($map as $field=>[$meta,$max]){if(!empty($_POST['remove_'.$field]))delete_user_meta($uid,$meta);if(empty($_FILES[$field]['name'])||($_FILES[$field]['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)continue;if((int)$_FILES[$field]['size']>$max)return ['success'=>false,'text'=>'One image is larger than allowed.'];$ft=wp_check_filetype_and_ext($_FILES[$field]['tmp_name'],$_FILES[$field]['name']);if(empty($ft['type'])||!in_array($ft['type'],['image/jpeg','image/png','image/webp'],true))return ['success'=>false,'text'=>'Only JPG, PNG, and WebP images are allowed.'];$id=media_handle_upload($field,0);if(is_wp_error($id))return ['success'=>false,'text'=>$id->get_error_message()];update_user_meta($uid,$meta,(int)$id);}return ['success'=>true,'text'=>''];
    }
    private static function field(
        string $name,
        string $label,
        string $value,
        bool $required = false,
        string $type = 'text'
    ): void {
        ?>
        <label>
            <span><?php echo esc_html($label); ?></span>
            <input
                type="<?php echo esc_attr($type); ?>"
                name="<?php echo esc_attr($name); ?>"
                value="<?php echo esc_attr($value); ?>"
                <?php echo $required ? 'required' : ''; ?>
            >
        </label>
        <?php
    }

    private static function image(string $name, string $label, int $id): void {
        $img = $id ? wp_get_attachment_image($id, 'medium') : '';
        ?>
        <div class="elev8-image-field">
            <h3><?php echo esc_html($label); ?></h3>
            <div class="elev8-image-preview">
                <?php if ($img !== '') : ?>
                    <?php echo wp_kses_post($img); ?>
                <?php else : ?>
                    <span class="dashicons dashicons-format-image"></span>
                    <p><?php esc_html_e('No image uploaded', 'elev8-os'); ?></p>
                <?php endif; ?>
            </div>

            <input
                type="file"
                name="<?php echo esc_attr($name); ?>"
                accept="image/jpeg,image/png,image/webp"
            >

            <?php if ($id) : ?>
                <label class="elev8-remove-image">
                    <input
                        type="checkbox"
                        name="remove_<?php echo esc_attr($name); ?>"
                        value="1"
                    >
                    <?php esc_html_e('Remove current image', 'elev8-os'); ?>
                </label>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function link_rows(string $prefix, array $links): void {
        for ($i = 0; $i < 4; $i++) {
            $link = $links[$i] ?? ['label' => '', 'url' => ''];
            ?>
            <div class="elev8-link-row">
                <label>
                    <span><?php esc_html_e('Label', 'elev8-os'); ?></span>
                    <input
                        type="text"
                        name="<?php echo esc_attr($prefix); ?>_label[]"
                        value="<?php echo esc_attr($link['label']); ?>"
                    >
                </label>

                <label>
                    <span><?php esc_html_e('Link, email, or phone', 'elev8-os'); ?></span>
                    <input
                        type="text"
                        name="<?php echo esc_attr($prefix); ?>_url[]"
                        value="<?php echo esc_attr($link['url']); ?>"
                    >
                </label>
            </div>
            <?php
        }
    }

    private static function save_links(int $uid,string $prefix,string $meta):void{$labels=(array)wp_unslash($_POST[$prefix.'_label']??[]);$urls=(array)wp_unslash($_POST[$prefix.'_url']??[]);$out=[];for($i=0;$i<4;$i++){$label=sanitize_text_field($labels[$i]??'');$url=self::normalize(sanitize_text_field($urls[$i]??''));if($label!==''||$url!=='')$out[]=['label'=>$label,'url'=>$url];}update_user_meta($uid,$meta,$out);}
    private static function normalize(string $v):string{$v=trim($v);if($v==='')return '';if(is_email($v))return 'mailto:'.sanitize_email($v);$digits=preg_replace('/\D/','',$v);if(strlen($digits)>=7)return 'tel:'.preg_replace('/[^0-9+]/','',$v);if(preg_match('#^(https?://|mailto:|tel:)#i',$v))return esc_url_raw($v,['http','https','mailto','tel']);return esc_url_raw('https://'.ltrim($v,'/'));}
    private static function links($v):array{return is_array($v)?array_values(array_slice($v,0,4)):[];}
    private static function meta(int $uid,string $key,string $v):void{$v===''?delete_user_meta($uid,$key):update_user_meta($uid,$key,$v);}
    private static function is_artist(WP_User $u):bool{foreach((array)$u->roles as $r){$n=strtolower(str_replace(['_','-'],' ',$r));if(strpos($n,'amelia')!==false&&(strpos($n,'employee')!==false||strpos($n,'provider')!==false))return true;}return false;}
    private static function is_page():bool{$id=(int)get_option(self::PAGE_OPTION);return ($id&&is_page($id))||is_page(self::PAGE_SLUG);}
}
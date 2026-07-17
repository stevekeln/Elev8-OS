<?php
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Content_Studio_Module {
    public static function init(): void {
        add_shortcode('elev8_artist_content_studio', [__CLASS__, 'shortcode']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'assets']);
        add_filter('body_class', [__CLASS__, 'body_classes']);
        add_action('admin_menu', [__CLASS__, 'admin_menu'], 45);
        add_action('admin_enqueue_scripts', [__CLASS__, 'admin_assets']);
        add_action('admin_post_elev8_os_content_save_template', [__CLASS__, 'save_template']);
        add_action('admin_post_elev8_os_content_duplicate_template', [__CLASS__, 'duplicate_template']);
        add_action('admin_post_elev8_os_content_status_template', [__CLASS__, 'status_template']);
        add_action('admin_post_elev8_os_content_delete_template', [__CLASS__, 'delete_template']);
        add_action('admin_post_elev8_os_content_save_category', [__CLASS__, 'save_category']);
        add_action('admin_post_elev8_os_content_save_campaign', [__CLASS__, 'save_campaign']);
        add_action('admin_post_elev8_os_content_save_brand', [__CLASS__, 'save_brand']);
    }

    public static function body_classes(array $classes): array {
        if (Elev8_OS_Portal_Page_Manager::is_current_page('content_studio')) {
            $classes[] = 'elev8-content-studio-page';
        }
        return $classes;
    }

    public static function assets(): void {
        if (Elev8_OS_Portal_Page_Manager::is_current_page('content_studio')) {
            wp_enqueue_style('dashicons');
            wp_enqueue_style('elev8-os-content-studio', ELEV8_OS_URL . 'assets/css/content-studio.css', [], ELEV8_OS_VERSION);
            wp_enqueue_script('elev8-os-content-studio', ELEV8_OS_URL . 'assets/js/content-studio.js', [], ELEV8_OS_VERSION, true);
        }
    }

    public static function admin_assets(string $hook): void {
        if ($hook !== 'elev8-os_page_elev8-content-studio') { return; }
        wp_enqueue_style('elev8-os-content-studio', ELEV8_OS_URL . 'assets/css/content-studio.css', [], ELEV8_OS_VERSION);
        wp_enqueue_media();
        wp_enqueue_script('elev8-os-content-studio', ELEV8_OS_URL . 'assets/js/content-studio.js', [], ELEV8_OS_VERSION, true);
    }

    public static function admin_menu(): void {
        add_submenu_page('elev8-os', __('Content Studio', 'elev8-os'), __('Content Studio', 'elev8-os'), 'manage_options', 'elev8-content-studio', [__CLASS__, 'render_admin']);
    }

    public static function shortcode(): string {
        if (!is_user_logged_in()) {
            return '<div class="elev8-dashboard-login"><p>' . esc_html__('Please log in to use Content Studio.', 'elev8-os') . '</p></div>';
        }
        return self::render_library((int) get_current_user_id(), false);
    }

    public static function render_admin(): void {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('You do not have permission.', 'elev8-os')); }
        echo '<div class="wrap elev8-content-admin">';
        echo '<h1>' . esc_html__('Content Studio — Shared Library', 'elev8-os') . '</h1>';
        echo '<p>' . esc_html__('Create gallery-wide templates. Artists can duplicate these into their personal libraries without changing the shared original.', 'elev8-os') . '</p>';
        echo self::render_library(0, true); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '</div>';
    }

    private static function render_library(int $owner_user_id, bool $admin): string {
        $edit_id = absint($_GET['template_id'] ?? 0);
        $search = sanitize_text_field(wp_unslash($_GET['content_search'] ?? ''));
        $status = sanitize_key(wp_unslash($_GET['content_status'] ?? ''));
        $category_id = absint($_GET['content_category'] ?? 0);
        $templates = Elev8_OS_Content_Studio_Service::templates($owner_user_id, ['search'=>$search,'status'=>$status,'category_id'=>$category_id], !$admin);
        $categories = Elev8_OS_Content_Studio_Service::categories($owner_user_id, !$admin);
        $editing = $edit_id > 0 ? Elev8_OS_Content_Studio_Service::get_template($edit_id, $owner_user_id, !$admin) : null;
        if ($editing && !$admin && (int) $editing['owner_user_id'] === 0) { $editing = null; }
        $base_url = $admin ? admin_url('admin.php?page=elev8-content-studio') : Elev8_OS_Portal_Page_Manager::get_url('content_studio');
        ob_start();
        if (!$admin) {
            echo '<div class="elev8-portal-layout elev8-content-studio-shell">'; Elev8_OS_Artist_Portal_Module::render_navigation('content_studio'); echo '<main class="elev8-content-main">';
        }
        ?>
        <div class="elev8-content-studio">
            <header class="elev8-content-header">
                <div><p class="elev8-eyebrow"><?php esc_html_e('Create once. Publish everywhere.', 'elev8-os'); ?></p><h1><?php esc_html_e('Content Studio', 'elev8-os'); ?></h1><p><?php esc_html_e('Build reusable content assets for classes, artwork, events, follow-up, referrals, and future publishing channels.', 'elev8-os'); ?></p></div>
                <a class="button button-primary" href="<?php echo esc_url($base_url); ?>#elev8-template-editor"><?php esc_html_e('Create Template', 'elev8-os'); ?></a>
            </header>
            <?php self::notice(); self::campaign_wizard($owner_user_id,$admin,$templates); ?>
            <section class="elev8-content-toolbar">
                <form method="get" action="<?php echo esc_url($base_url); ?>">
                    <?php if ($admin): ?><input type="hidden" name="page" value="elev8-content-studio"><?php endif; ?>
                    <label><span class="screen-reader-text"><?php esc_html_e('Search templates', 'elev8-os'); ?></span><input type="search" name="content_search" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search templates…', 'elev8-os'); ?>"></label>
                    <select name="content_category"><option value="0"><?php esc_html_e('All categories', 'elev8-os'); ?></option><?php foreach($categories as $category): ?><option value="<?php echo esc_attr((string)$category['id']); ?>" <?php selected($category_id,(int)$category['id']); ?>><?php echo esc_html((string)$category['name']); ?></option><?php endforeach; ?></select>
                    <select name="content_status"><option value=""><?php esc_html_e('All statuses', 'elev8-os'); ?></option><option value="active" <?php selected($status,'active'); ?>><?php esc_html_e('Active', 'elev8-os'); ?></option><option value="draft" <?php selected($status,'draft'); ?>><?php esc_html_e('Draft', 'elev8-os'); ?></option><option value="archived" <?php selected($status,'archived'); ?>><?php esc_html_e('Archived', 'elev8-os'); ?></option></select>
                    <button class="button" type="submit"><?php esc_html_e('Filter', 'elev8-os'); ?></button>
                    <?php if($search!==''||$status!==''||$category_id>0): ?><a class="button" href="<?php echo esc_url($base_url); ?>"><?php esc_html_e('Clear', 'elev8-os'); ?></a><?php endif; ?>
                </form>
            </section>
            <section class="elev8-template-library">
                <div class="elev8-section-heading"><h2><?php esc_html_e('Template Library', 'elev8-os'); ?></h2><span><?php echo esc_html(sprintf(_n('%d template','%d templates',count($templates),'elev8-os'),count($templates))); ?></span></div>
                <?php if(empty($templates)): ?><div class="elev8-content-empty"><span class="dashicons dashicons-layout"></span><h3><?php esc_html_e('No templates found', 'elev8-os'); ?></h3><p><?php esc_html_e('Create your first reusable template or clear the current filters.', 'elev8-os'); ?></p></div><?php else: ?>
                <div class="elev8-template-grid">
                    <?php foreach($templates as $template): $shared=(int)$template['owner_user_id']===0; ?>
                    <article class="elev8-template-card <?php echo $shared?'is-shared':'is-personal'; ?>">
                        <div class="elev8-template-card-top"><span class="elev8-template-category"><?php echo esc_html((string)($template['category_name']?:__('Uncategorized','elev8-os'))); ?></span><span class="elev8-template-status status-<?php echo esc_attr((string)$template['status']); ?>"><?php echo esc_html(ucfirst((string)$template['status'])); ?></span></div>
                        <h3><?php echo esc_html((string)$template['name']); ?></h3>
                        <?php if((string)$template['description']!==''): ?><p><?php echo esc_html((string)$template['description']); ?></p><?php endif; ?>
                        <?php if((string)$template['subject']!==''): ?><div class="elev8-template-subject"><strong><?php esc_html_e('Subject:', 'elev8-os'); ?></strong> <?php echo esc_html((string)$template['subject']); ?></div><?php endif; ?>
                        <div class="elev8-template-meta"><span><?php echo $shared?esc_html__('Shared by Elev8','elev8-os'):esc_html__('My template','elev8-os'); ?></span><span><?php echo esc_html(wp_date(get_option('date_format'),strtotime((string)$template['updated_at']))); ?></span></div>
                        <div class="elev8-template-actions">
                            <?php if(!$shared || $admin): ?><a class="button" href="<?php echo esc_url(add_query_arg('template_id',(int)$template['id'],$base_url).'#elev8-template-editor'); ?>"><?php esc_html_e('Edit', 'elev8-os'); ?></a><?php endif; ?>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="elev8_os_content_duplicate_template"><input type="hidden" name="template_id" value="<?php echo esc_attr((string)$template['id']); ?>"><input type="hidden" name="context" value="<?php echo $admin?'admin':'artist'; ?>"><?php wp_nonce_field('elev8_os_content_duplicate_template'); ?><button class="button" type="submit"><?php echo $shared&&!$admin?esc_html__('Use Template','elev8-os'):esc_html__('Duplicate','elev8-os'); ?></button></form>
                            <?php if(!$shared || $admin): ?>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="elev8_os_content_status_template"><input type="hidden" name="template_id" value="<?php echo esc_attr((string)$template['id']); ?>"><input type="hidden" name="status" value="<?php echo $template['status']==='archived'?'active':'archived'; ?>"><input type="hidden" name="context" value="<?php echo $admin?'admin':'artist'; ?>"><?php wp_nonce_field('elev8_os_content_status_template'); ?><button class="button" type="submit"><?php echo $template['status']==='archived'?esc_html__('Restore','elev8-os'):esc_html__('Archive','elev8-os'); ?></button></form>
                            <?php endif; ?>
                        </div>
                    </article><?php endforeach; ?>
                </div><?php endif; ?>
            </section>
            <section id="elev8-template-editor" class="elev8-template-editor">
                <div class="elev8-section-heading"><div><p class="elev8-eyebrow"><?php echo $editing?esc_html__('Update reusable content','elev8-os'):esc_html__('Add to your library','elev8-os'); ?></p><h2><?php echo $editing?esc_html__('Edit Template','elev8-os'):esc_html__('Create Template','elev8-os'); ?></h2></div><?php if($editing): ?><a class="button" href="<?php echo esc_url($base_url.'#elev8-template-editor'); ?>"><?php esc_html_e('Create New', 'elev8-os'); ?></a><?php endif; ?></div>
                <?php self::editor_form($owner_user_id,$editing,$categories,$admin); ?>
            </section>
            <?php if($admin): self::brand_form(); self::category_form(); endif; ?>
        </div>
        <?php
        if (!$admin) { echo '</main></div>'; }
        return (string) ob_get_clean();
    }

    /** @param array<string,mixed>|null $template @param array<int,array<string,mixed>> $categories */
    private static function editor_form(int $owner_user_id, ?array $template, array $categories, bool $admin): void {
        $template = wp_parse_args($template ?: [], ['id'=>0,'name'=>'','description'=>'','category_id'=>0,'status'=>'active','subject'=>'','headline'=>'','body'=>'','cta_label'=>'','cta_url'=>'']); ?>
        <form class="elev8-content-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="elev8_os_content_save_template"><input type="hidden" name="template_id" value="<?php echo esc_attr((string)$template['id']); ?>"><input type="hidden" name="context" value="<?php echo $admin?'admin':'artist'; ?>"><?php wp_nonce_field('elev8_os_content_save_template'); ?>
            <div class="elev8-form-grid"><label><span><?php esc_html_e('Template name', 'elev8-os'); ?></span><input type="text" name="name" value="<?php echo esc_attr((string)$template['name']); ?>" required></label>
            <label><span><?php esc_html_e('Category', 'elev8-os'); ?></span><select name="category_id"><option value="0"><?php esc_html_e('Uncategorized','elev8-os'); ?></option><?php foreach($categories as $category): ?><option value="<?php echo esc_attr((string)$category['id']); ?>" <?php selected((int)$template['category_id'],(int)$category['id']); ?>><?php echo esc_html((string)$category['name']); ?></option><?php endforeach; ?></select></label>
            <label><span><?php esc_html_e('Status', 'elev8-os'); ?></span><select name="status"><option value="active" <?php selected($template['status'],'active'); ?>><?php esc_html_e('Active','elev8-os'); ?></option><option value="draft" <?php selected($template['status'],'draft'); ?>><?php esc_html_e('Draft','elev8-os'); ?></option><option value="archived" <?php selected($template['status'],'archived'); ?>><?php esc_html_e('Archived','elev8-os'); ?></option></select></label></div>
            <label><span><?php esc_html_e('Description', 'elev8-os'); ?></span><textarea name="description" rows="2" placeholder="<?php esc_attr_e('What is this template best used for?', 'elev8-os'); ?>"><?php echo esc_textarea((string)$template['description']); ?></textarea></label>
            <label><span><?php esc_html_e('Subject line', 'elev8-os'); ?></span><input type="text" name="subject" value="<?php echo esc_attr((string)$template['subject']); ?>"></label>
            <label><span><?php esc_html_e('Headline', 'elev8-os'); ?></span><input type="text" name="headline" value="<?php echo esc_attr((string)$template['headline']); ?>"></label>
            <label><span><?php esc_html_e('Body content', 'elev8-os'); ?></span><textarea name="body" rows="10"><?php echo esc_textarea((string)$template['body']); ?></textarea></label>
            <div class="elev8-form-grid"><label><span><?php esc_html_e('Button label', 'elev8-os'); ?></span><input type="text" name="cta_label" value="<?php echo esc_attr((string)$template['cta_label']); ?>"></label><label><span><?php esc_html_e('Button URL', 'elev8-os'); ?></span><input type="url" name="cta_url" value="<?php echo esc_attr((string)$template['cta_url']); ?>" placeholder="https://"></label></div>
            <div class="elev8-variable-help"><strong><?php esc_html_e('Available variables:', 'elev8-os'); ?></strong> <code>{{student_first_name}}</code> <code>{{artist_name}}</code> <code>{{class_name}}</code> <code>{{class_date}}</code> <code>{{art_walk_date}}</code> <code>{{promotion_link}}</code></div>
            <div class="elev8-form-actions"><button class="button button-primary" type="submit"><?php echo $template['id']?esc_html__('Update Template','elev8-os'):esc_html__('Save Template','elev8-os'); ?></button><?php if($template['id']): ?><button class="button elev8-delete-button" type="submit" form="elev8-delete-template-form" onclick="return confirm('<?php echo esc_js(__('Permanently delete this template?', 'elev8-os')); ?>')"><?php esc_html_e('Delete Permanently','elev8-os'); ?></button><?php endif; ?></div>
        </form>
        <?php if($template['id']): ?><form id="elev8-delete-template-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="elev8_os_content_delete_template"><input type="hidden" name="template_id" value="<?php echo esc_attr((string)$template['id']); ?>"><input type="hidden" name="context" value="<?php echo $admin?'admin':'artist'; ?>"><?php wp_nonce_field('elev8_os_content_delete_template'); ?></form><?php endif;
    }

    private static function category_form(): void { ?>
        <section class="elev8-category-manager"><div class="elev8-section-heading"><div><p class="elev8-eyebrow"><?php esc_html_e('Organization', 'elev8-os'); ?></p><h2><?php esc_html_e('Add Shared Category', 'elev8-os'); ?></h2></div></div><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="elev8_os_content_save_category"><?php wp_nonce_field('elev8_os_content_save_category'); ?><input type="text" name="name" required placeholder="<?php esc_attr_e('Category name', 'elev8-os'); ?>"><input type="text" name="description" placeholder="<?php esc_attr_e('Optional description', 'elev8-os'); ?>"><button class="button button-primary" type="submit"><?php esc_html_e('Add Category','elev8-os'); ?></button></form></section><?php
    }


    /** @param array<int,array<string,mixed>> $templates */
    private static function campaign_wizard(int $owner_user_id, bool $admin, array $templates): void {
        $goals=Elev8_OS_Content_Studio_Service::campaign_goals();
        $audiences=Elev8_OS_Content_Studio_Service::audiences();
        $goal=sanitize_key(wp_unslash($_GET['campaign_goal']??''));
        $campaign_id=absint($_GET['campaign_id']??0);
        $campaign=$campaign_id?Elev8_OS_Content_Studio_Service::get_campaign($campaign_id,$owner_user_id):null;
        if($campaign){$goal=(string)$campaign['goal'];}
        $goal=isset($goals[$goal])?$goal:'';
        $drafts=Elev8_OS_Content_Studio_Service::campaigns($owner_user_id,8);
        ?>
        <section class="elev8-campaign-wizard">
            <div class="elev8-section-heading"><div><p class="elev8-eyebrow"><?php esc_html_e('Brand & Campaign Wizard','elev8-os'); ?></p><h2><?php esc_html_e('What are you trying to accomplish today?','elev8-os'); ?></h2></div><span class="elev8-wizard-step"><?php echo $goal?esc_html__('Step 2 of 2','elev8-os'):esc_html__('Step 1 of 2','elev8-os'); ?></span></div>
            <?php if(!$goal): ?>
                <div class="elev8-goal-grid"><?php foreach($goals as $key=>$item): ?><a class="elev8-goal-card" href="<?php echo esc_url(add_query_arg('campaign_goal',$key,$admin?admin_url('admin.php?page=elev8-content-studio'):Elev8_OS_Portal_Page_Manager::get_url('content_studio'))); ?>#elev8-campaign-builder"><span class="dashicons dashicons-<?php echo esc_attr(self::goal_icon($key)); ?>"></span><strong><?php echo esc_html($item['label']); ?></strong><small><?php echo esc_html($item['description']); ?></small></a><?php endforeach; ?></div>
            <?php else:
                $suggested=self::suggested_template($templates,(string)$goals[$goal]['category']);
                $values=wp_parse_args($campaign?:[],['template_id'=>$suggested['id']??0,'title'=>$goals[$goal]['label'],'audience_key'=>'all_students','subject'=>$suggested['subject']??'','headline'=>$suggested['headline']??'','body'=>$suggested['body']??'','cta_label'=>$suggested['cta_label']??Elev8_OS_Brand_Service::get()['default_cta_label'],'cta_url'=>$suggested['cta_url']??'','include_artist_profile'=>1,'include_upcoming_classes'=>1,'include_events'=>1,'include_referral'=>1]);
                $values['goal']=$goal; $preview=Elev8_OS_Template_Renderer::email_html($values,self::preview_variables($owner_user_id)); ?>
                <div id="elev8-campaign-builder" class="elev8-campaign-builder">
                    <form class="elev8-campaign-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="elev8_os_content_save_campaign"><input type="hidden" name="context" value="<?php echo $admin?'admin':'artist'; ?>"><input type="hidden" name="campaign_id" value="<?php echo esc_attr((string)$campaign_id); ?>"><input type="hidden" name="goal" value="<?php echo esc_attr($goal); ?>"><?php wp_nonce_field('elev8_os_content_save_campaign'); ?>
                        <div class="elev8-campaign-form-head"><h3><?php echo esc_html($goals[$goal]['label']); ?></h3><a href="<?php echo esc_url($admin?admin_url('admin.php?page=elev8-content-studio'):Elev8_OS_Portal_Page_Manager::get_url('content_studio')); ?>#elev8-campaign-builder"><?php esc_html_e('Choose a different goal','elev8-os'); ?></a></div>
                        <label><span><?php esc_html_e('Campaign name','elev8-os'); ?></span><input type="text" name="title" value="<?php echo esc_attr((string)$values['title']); ?>" required></label>
                        <label><span><?php esc_html_e('Who should receive this?','elev8-os'); ?></span><select name="audience_key"><?php foreach($audiences as $key=>$label): ?><option value="<?php echo esc_attr($key); ?>" <?php selected($values['audience_key'],$key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select><small><?php esc_html_e('Elev8 OS handles the technical audience rules behind these plain-language choices.','elev8-os'); ?></small></label>
                        <label><span><?php esc_html_e('Starting template','elev8-os'); ?></span><select name="template_id" data-elev8-template-select><option value="0"><?php esc_html_e('Start without a template','elev8-os'); ?></option><?php foreach($templates as $template): ?><option value="<?php echo esc_attr((string)$template['id']); ?>" <?php selected((int)$values['template_id'],(int)$template['id']); ?> data-subject="<?php echo esc_attr((string)$template['subject']); ?>" data-headline="<?php echo esc_attr((string)$template['headline']); ?>" data-body="<?php echo esc_attr((string)$template['body']); ?>" data-cta="<?php echo esc_attr((string)$template['cta_label']); ?>" data-url="<?php echo esc_attr((string)$template['cta_url']); ?>"><?php echo esc_html((string)$template['name']); ?></option><?php endforeach; ?></select></label>
                        <label><span><?php esc_html_e('Subject line','elev8-os'); ?></span><input data-elev8-field="subject" type="text" name="subject" value="<?php echo esc_attr((string)$values['subject']); ?>"></label>
                        <label><span><?php esc_html_e('Headline','elev8-os'); ?></span><input data-elev8-field="headline" type="text" name="headline" value="<?php echo esc_attr((string)$values['headline']); ?>"></label>
                        <label><span><?php esc_html_e('Message','elev8-os'); ?></span><textarea data-elev8-field="body" name="body" rows="9"><?php echo esc_textarea((string)$values['body']); ?></textarea></label>
                        <div class="elev8-form-grid"><label><span><?php esc_html_e('Button text','elev8-os'); ?></span><input data-elev8-field="cta" type="text" name="cta_label" value="<?php echo esc_attr((string)$values['cta_label']); ?>"></label><label><span><?php esc_html_e('Button destination','elev8-os'); ?></span><input data-elev8-field="url" type="url" name="cta_url" value="<?php echo esc_attr((string)$values['cta_url']); ?>" placeholder="https://"></label></div>
                        <fieldset class="elev8-smart-sections"><legend><?php esc_html_e('Elev8 OS automatically includes','elev8-os'); ?></legend><?php foreach(['include_artist_profile'=>__('Artist profile','elev8-os'),'include_upcoming_classes'=>__('Upcoming classes','elev8-os'),'include_events'=>__('Suggested Elev8 events','elev8-os'),'include_referral'=>__('Referral tracking when applicable','elev8-os')] as $key=>$label): ?><label><input type="checkbox" name="<?php echo esc_attr($key); ?>" value="1" <?php checked(!empty($values[$key])); ?>> <?php echo esc_html($label); ?></label><?php endforeach; ?></fieldset>
                        <div class="elev8-form-actions"><button class="button button-primary" type="submit"><?php esc_html_e('Save Campaign Draft','elev8-os'); ?></button></div>
                    </form>
                    <div class="elev8-campaign-preview"><div class="elev8-preview-label"><?php esc_html_e('Universal branded email preview','elev8-os'); ?></div><iframe title="<?php esc_attr_e('Campaign preview','elev8-os'); ?>" srcdoc="<?php echo esc_attr($preview); ?>"></iframe></div>
                </div>
            <?php endif; ?>
            <?php if($drafts): ?><div class="elev8-recent-campaigns"><h3><?php esc_html_e('Recent Campaign Drafts','elev8-os'); ?></h3><div><?php foreach($drafts as $draft): ?><a href="<?php echo esc_url(add_query_arg(['campaign_goal'=>$draft['goal'],'campaign_id'=>$draft['id']],$admin?admin_url('admin.php?page=elev8-content-studio'):Elev8_OS_Portal_Page_Manager::get_url('content_studio'))); ?>#elev8-campaign-builder"><strong><?php echo esc_html((string)$draft['title']); ?></strong><span><?php echo esc_html(wp_date(get_option('date_format'),strtotime((string)$draft['updated_at']))); ?></span></a><?php endforeach; ?></div></div><?php endif; ?>
        </section><?php
    }

    private static function brand_form(): void { $b=Elev8_OS_Brand_Service::get(); $logo=Elev8_OS_Brand_Service::logo_url(); ?>
        <section class="elev8-brand-manager"><div class="elev8-section-heading"><div><p class="elev8-eyebrow"><?php esc_html_e('Brand Experience','elev8-os'); ?></p><h2><?php esc_html_e('Universal Email Brand System','elev8-os'); ?></h2><p><?php esc_html_e('These settings automatically polish every campaign with your logo, calls to action, mission, social links, and footer.','elev8-os'); ?></p></div></div>
        <form class="elev8-content-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="elev8_os_content_save_brand"><?php wp_nonce_field('elev8_os_content_save_brand'); ?>
            <div class="elev8-brand-logo-field"><div class="elev8-brand-logo-preview" data-elev8-logo-preview><?php if($logo): ?><img src="<?php echo esc_url($logo); ?>" alt=""><?php else: ?><span><?php esc_html_e('No logo selected','elev8-os'); ?></span><?php endif; ?></div><div><input type="hidden" name="logo_id" value="<?php echo esc_attr((string)$b['logo_id']); ?>" data-elev8-logo-id><button type="button" class="button" data-elev8-logo-select><?php esc_html_e('Choose Logo','elev8-os'); ?></button> <button type="button" class="button" data-elev8-logo-remove><?php esc_html_e('Remove','elev8-os'); ?></button><p class="description"><?php esc_html_e('Select the Elev8 Arts logo from the WordPress Media Library. If blank, the active theme logo is used.','elev8-os'); ?></p></div></div>
            <div class="elev8-form-grid"><label><span><?php esc_html_e('Brand name','elev8-os'); ?></span><input type="text" name="brand_name" value="<?php echo esc_attr((string)$b['brand_name']); ?>"></label><label><span><?php esc_html_e('Tagline','elev8-os'); ?></span><input type="text" name="tagline" value="<?php echo esc_attr((string)$b['tagline']); ?>"></label><label><span><?php esc_html_e('Website URL','elev8-os'); ?></span><input type="url" name="website_url" value="<?php echo esc_attr((string)$b['website_url']); ?>"></label></div>
            <div class="elev8-form-grid"><label><span><?php esc_html_e('Book a Class URL','elev8-os'); ?></span><input type="url" name="class_booking_url" value="<?php echo esc_attr((string)$b['class_booking_url']); ?>"></label><label><span><?php esc_html_e('Events URL','elev8-os'); ?></span><input type="url" name="events_url" value="<?php echo esc_attr((string)$b['events_url']); ?>"></label><label><span><?php esc_html_e('Artist Directory URL','elev8-os'); ?></span><input type="url" name="artist_directory_url" value="<?php echo esc_attr((string)$b['artist_directory_url']); ?>"></label></div>
            <div class="elev8-form-grid"><label><span><?php esc_html_e('Primary color','elev8-os'); ?></span><input type="color" name="primary_color" value="<?php echo esc_attr((string)$b['primary_color']); ?>"></label><label><span><?php esc_html_e('Header color','elev8-os'); ?></span><input type="color" name="secondary_color" value="<?php echo esc_attr((string)$b['secondary_color']); ?>"></label><label><span><?php esc_html_e('Email background','elev8-os'); ?></span><input type="color" name="background_color" value="<?php echo esc_attr((string)$b['background_color']); ?>"></label></div>
            <div class="elev8-form-grid"><label><span><?php esc_html_e('Default button text','elev8-os'); ?></span><input type="text" name="default_cta_label" value="<?php echo esc_attr((string)$b['default_cta_label']); ?>"></label><label><span><?php esc_html_e('Mission heading','elev8-os'); ?></span><input type="text" name="mission_heading" value="<?php echo esc_attr((string)$b['mission_heading']); ?>"></label><label><span><?php esc_html_e('Address','elev8-os'); ?></span><input type="text" name="address_text" value="<?php echo esc_attr((string)$b['address_text']); ?>"></label></div>
            <label><span><?php esc_html_e('Mission message','elev8-os'); ?></span><textarea name="mission_text" rows="3"><?php echo esc_textarea((string)$b['mission_text']); ?></textarea></label>
            <div class="elev8-form-grid"><label><span><?php esc_html_e('Facebook URL','elev8-os'); ?></span><input type="url" name="facebook_url" value="<?php echo esc_attr((string)$b['facebook_url']); ?>"></label><label><span><?php esc_html_e('Instagram URL','elev8-os'); ?></span><input type="url" name="instagram_url" value="<?php echo esc_attr((string)$b['instagram_url']); ?>"></label><label><span><?php esc_html_e('YouTube URL','elev8-os'); ?></span><input type="url" name="youtube_url" value="<?php echo esc_attr((string)$b['youtube_url']); ?>"></label></div>
            <label><span><?php esc_html_e('Small footer credit','elev8-os'); ?></span><input type="text" name="footer_text" value="<?php echo esc_attr((string)$b['footer_text']); ?>"></label>
            <button class="button button-primary" type="submit"><?php esc_html_e('Save Brand Experience','elev8-os'); ?></button>
        </form></section><?php
    }

    /** @param array<int,array<string,mixed>> $templates @return array<string,mixed> */
    private static function suggested_template(array $templates,string $category_slug): array { foreach($templates as $template){if(sanitize_title((string)($template['category_name']??''))===$category_slug&&$template['status']==='active'){return $template;}} return $templates[0]??[]; }
    /** @return array<string,string> */
    private static function preview_variables(int $owner_user_id): array { $user=$owner_user_id?get_userdata($owner_user_id):wp_get_current_user(); return ['student_first_name'=>__('Friend','elev8-os'),'artist_name'=>$user instanceof WP_User?$user->display_name:__('Elev8 Artist','elev8-os'),'class_name'=>__('Your Upcoming Class','elev8-os'),'class_date'=>wp_date(get_option('date_format'),strtotime('+2 weeks')),'art_walk_date'=>__('Third Saturday, 11 AM–4 PM','elev8-os'),'promotion_link'=>home_url('/')]; }
    private static function goal_icon(string $goal): string { return ['fill_class'=>'tickets-alt','sell_artwork'=>'art','announce_event'=>'megaphone','bring_back'=>'update','introduce_artist'=>'admin-users','referral'=>'share','custom'=>'edit-page'][$goal]??'edit'; }

    private static function notice(): void {
        $message = sanitize_key(wp_unslash($_GET['content_message'] ?? ''));
        $messages = ['saved'=>__('Template saved.','elev8-os'),'duplicated'=>__('Template duplicated into your library.','elev8-os'),'status'=>__('Template status updated.','elev8-os'),'deleted'=>__('Template deleted.','elev8-os'),'category'=>__('Category added.','elev8-os'),'campaign'=>__('Campaign draft saved.','elev8-os'),'brand'=>__('Brand settings saved.','elev8-os'),'invalid'=>__('That action could not be completed.','elev8-os')];
        if(isset($messages[$message])) { echo '<div class="elev8-content-notice ' . ($message==='invalid'?'is-error':'is-success') . '"><p>' . esc_html($messages[$message]) . '</p></div>'; }
    }

    public static function save_template(): void {
        self::authorize('elev8_os_content_save_template'); $context=self::context(); $owner=self::owner($context); $id=absint($_POST['template_id']??0);
        if($id>0&&!Elev8_OS_Content_Studio_Service::get_template($id,$owner,false)){self::redirect($context,'invalid');}
        $saved=Elev8_OS_Content_Studio_Service::save_template($owner,wp_unslash($_POST),$id); self::redirect($context,$saved?'saved':'invalid');
    }
    public static function duplicate_template(): void {
        self::authorize('elev8_os_content_duplicate_template'); $context=self::context(); $target=self::owner($context); $id=absint($_POST['template_id']??0); $source=Elev8_OS_Content_Studio_Service::get_template($id,$target,true);
        if(!$source){self::redirect($context,'invalid');} $new=Elev8_OS_Content_Studio_Service::duplicate_template($id,(int)$source['owner_user_id'],$target); self::redirect($context,$new?'duplicated':'invalid');
    }
    public static function status_template(): void {
        self::authorize('elev8_os_content_status_template'); $context=self::context(); $owner=self::owner($context); $ok=Elev8_OS_Content_Studio_Service::set_status(absint($_POST['template_id']??0),$owner,sanitize_key(wp_unslash($_POST['status']??''))); self::redirect($context,$ok?'status':'invalid');
    }
    public static function delete_template(): void {
        self::authorize('elev8_os_content_delete_template'); $context=self::context(); $owner=self::owner($context); $ok=Elev8_OS_Content_Studio_Service::delete_template(absint($_POST['template_id']??0),$owner); self::redirect($context,$ok?'deleted':'invalid');
    }

    public static function save_campaign(): void {
        self::authorize('elev8_os_content_save_campaign'); $context=self::context(); $owner=self::owner($context); $id=absint($_POST['campaign_id']??0);
        if($id>0&&!Elev8_OS_Content_Studio_Service::get_campaign($id,$owner)){self::redirect($context,'invalid');}
        $saved=Elev8_OS_Content_Studio_Service::save_campaign($owner,wp_unslash($_POST),$id); self::redirect($context,$saved?'campaign':'invalid');
    }
    public static function save_brand(): void {
        if(!current_user_can('manage_options')){wp_die(esc_html__('You do not have permission.','elev8-os'));} check_admin_referer('elev8_os_content_save_brand'); Elev8_OS_Brand_Service::save(wp_unslash($_POST)); self::redirect('admin','brand');
    }
    public static function save_category(): void {
        if(!current_user_can('manage_options')){wp_die(esc_html__('You do not have permission.','elev8-os'));} check_admin_referer('elev8_os_content_save_category'); $id=Elev8_OS_Content_Studio_Service::ensure_category(0,sanitize_text_field(wp_unslash($_POST['name']??'')),sanitize_textarea_field(wp_unslash($_POST['description']??''))); self::redirect('admin',$id?'category':'invalid');
    }
    private static function authorize(string $action): void { if(!is_user_logged_in()){wp_die(esc_html__('Please log in.','elev8-os'));} check_admin_referer($action); }
    private static function context(): string { $context=sanitize_key(wp_unslash($_POST['context']??'artist')); if($context==='admin'&&!current_user_can('manage_options')){wp_die(esc_html__('You do not have permission.','elev8-os'));} return $context==='admin'?'admin':'artist'; }
    private static function owner(string $context): int { return $context==='admin'?0:(int)get_current_user_id(); }
    private static function redirect(string $context,string $message): void { $url=$context==='admin'?admin_url('admin.php?page=elev8-content-studio'):Elev8_OS_Portal_Page_Manager::get_url('content_studio'); wp_safe_redirect(add_query_arg('content_message',$message,$url)); exit; }
}

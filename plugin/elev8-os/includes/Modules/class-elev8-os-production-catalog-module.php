<?php
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Production_Catalog_Module {
    const SLUG='elev8-production-catalog';

    public static function init(): void {
        Elev8_OS_Production_Catalog_Service::init();
        add_action('admin_menu',[__CLASS__,'menu'],19);
        add_action('admin_enqueue_scripts',[__CLASS__,'assets']);
        add_action('admin_post_elev8_os_save_production_product',[__CLASS__,'save_product']);
        add_action('admin_post_elev8_os_save_production_material',[__CLASS__,'save_material']);
        add_action('admin_post_elev8_os_save_compensation_profile',[__CLASS__,'save_profile']);
        add_action('admin_post_elev8_os_import_production_migration',[__CLASS__,'import_migration']);
        add_action('admin_post_elev8_os_glass_catalog_wizard_upload',[__CLASS__,'wizard_upload']);
        add_action('admin_post_elev8_os_glass_catalog_wizard_import',[__CLASS__,'wizard_import']);
        add_action('admin_post_elev8_os_glass_catalog_wizard_clear',[__CLASS__,'wizard_clear']);
        add_action('admin_post_elev8_os_production_catalog_bulk',[__CLASS__,'bulk_products']);
        add_action('admin_post_elev8_os_production_catalog_merge',[__CLASS__,'merge_products']);
    }

    public static function menu(): void {
        add_submenu_page('elev8-os','Production Catalog','Production Catalog','read',self::SLUG,[__CLASS__,'render']);
    }

    public static function assets(string $hook): void {
        if(strpos($hook,self::SLUG)===false)return;
        wp_enqueue_style('elev8-production-catalog',ELEV8_OS_URL.'assets/css/production-catalog.css',[],ELEV8_OS_VERSION);
    }

    private static function can_manage(): bool {
        return Elev8_OS_Access_Service::user_can('manage_glass_production') || current_user_can('manage_options');
    }
    private static function guard(): void { if(!self::can_manage())wp_die('You do not have permission to manage the Production Catalog.'); }
    private static function url(array $args=[]): string { return add_query_arg(array_merge(['page'=>self::SLUG],$args),admin_url('admin.php')); }

    public static function render(): void {
        self::guard();
        $view=sanitize_key($_GET['view']??'products');
        ?><div class="wrap elev8-production"><header class="elev8-production__hero"><div><p class="eyebrow">Production Engine</p><h1>Glass Production Catalog</h1><p>Define what can be made, what it costs, and whether each operation is hourly, piecework, either, or included.</p></div><nav><a class="button <?php echo $view==='products'?'button-primary':'';?>" href="<?php echo esc_url(self::url());?>">Products</a><a class="button <?php echo $view==='new-product'?'button-primary':'';?>" href="<?php echo esc_url(self::url(['view'=>'new-product']));?>">New product</a><a class="button <?php echo $view==='materials'?'button-primary':'';?>" href="<?php echo esc_url(self::url(['view'=>'materials']));?>">Materials</a><a class="button <?php echo $view==='compensation'?'button-primary':'';?>" href="<?php echo esc_url(self::url(['view'=>'compensation']));?>">Compensation profiles</a><a class="button <?php echo $view==='manager'?'button-primary':'';?>" href="<?php echo esc_url(self::url(['view'=>'manager']));?>">Catalog Manager</a><a class="button <?php echo $view==='wizard'?'button-primary':'';?>" href="<?php echo esc_url(self::url(['view'=>'wizard']));?>">Import Wizard</a><a class="button <?php echo $view==='migration'?'button-primary':'';?>" href="<?php echo esc_url(self::url(['view'=>'migration']));?>">Legacy Migration</a></nav></header><?php
        if(!empty($_GET['notice'])){$notice_type=sanitize_key($_GET['notice_type']??'success');$notice_class=$notice_type==='error'?'notice-error':'notice-success';echo '<div class="notice '.esc_attr($notice_class).' is-dismissible"><p>'.esc_html(wp_unslash($_GET['notice'])).'</p></div>';}
        if($view==='new-product'||$view==='edit-product')self::product_form(absint($_GET['product_id']??0));
        elseif($view==='materials')self::materials();
        elseif($view==='compensation')self::compensation();
        elseif($view==='manager')self::manager();
        elseif($view==='wizard')self::wizard();
        elseif($view==='migration')self::migration();
        else self::products();
        ?></div><?php
    }

    private static function products(): void {
        $status=sanitize_key($_GET['status']??'');
        $category=sanitize_text_field($_GET['category']??'');
        $products=Elev8_OS_Production_Catalog_Service::products([
            'search'=>sanitize_text_field($_GET['s']??''),
            'lifecycle_status'=>$status,
            'category'=>$category,
        ]);
        $categories=Elev8_OS_Production_Catalog_Service::categories();
        ?><section class="panel"><div class="panel-head"><div><h2>Production products</h2><p>The Production Catalog is the trusted source for Fast Glass Pay, job snapshots, costing and financial analysis. Archive products instead of deleting history.</p></div><div class="catalog-head-actions"><a class="button" href="<?php echo esc_url(self::url(['view'=>'manager']));?>">Manage families & duplicates</a><a class="button button-primary" href="<?php echo esc_url(self::url(['view'=>'new-product']));?>">Create production product</a></div></div>
        <form class="catalog-filters"><input type="hidden" name="page" value="<?php echo esc_attr(self::SLUG);?>"><input type="search" name="s" value="<?php echo esc_attr($_GET['s']??'');?>" placeholder="Search name, code, family or alias"><select name="status"><option value="">All lifecycle statuses</option><?php foreach(Elev8_OS_Production_Catalog_Service::lifecycle_statuses() as $value=>$label):?><option value="<?php echo esc_attr($value);?>" <?php selected($status,$value);?>><?php echo esc_html($label);?></option><?php endforeach;?></select><select name="category"><option value="">All families</option><?php foreach($categories as $cat):?><option value="<?php echo esc_attr($cat);?>" <?php selected($category,$cat);?>><?php echo esc_html($cat);?></option><?php endforeach;?></select><button class="button">Filter</button></form>
        <?php if(!$products){echo '<div class="empty">No production products match these filters.</div>';}else{?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>" class="catalog-bulk-form"><?php wp_nonce_field('elev8_production_catalog_bulk');?><input type="hidden" name="action" value="elev8_os_production_catalog_bulk">
            <div class="catalog-bulk-bar"><label>Bulk action <select name="bulk_action"><option value="">Choose action</option><option value="status">Change lifecycle status</option><option value="category">Move to family</option></select></label><label>Value <input name="bulk_value" placeholder="active, draft, archived, or family name"></label><button class="button">Apply to selected</button><small>Historical jobs and pay records remain unchanged.</small></div>
            <div class="table-wrap"><table><thead><tr><th><input type="checkbox" onclick="this.closest('table').querySelectorAll('tbody input[type=checkbox]').forEach(x=>x.checked=this.checked)"></th><th>Product</th><th>Family</th><th>Compensation</th><th>Retail / Wholesale</th><th>Estimated cost</th><th>Version</th><th>Lifecycle</th><th></th></tr></thead><tbody>
            <?php foreach($products as $p):$p['materials']=Elev8_OS_Production_Catalog_Service::product_materials((int)$p['id']);$cost=Elev8_OS_Production_Catalog_Service::cost_summary($p);$life=$p['lifecycle_status']??($p['active']?'active':'archived');?>
            <tr class="lifecycle-<?php echo esc_attr($life);?>"><td><input type="checkbox" name="product_ids[]" value="<?php echo absint($p['id']);?>"></td><td><strong><?php echo esc_html($p['product_name']);?></strong><small><?php echo esc_html($p['product_code']);?></small><?php if(!empty($p['merged_into_product_id'])):?><small class="warning">Merged into product #<?php echo absint($p['merged_into_product_id']);?></small><?php endif;?></td><td><?php echo esc_html($p['category']?:'Uncategorized');?></td><td><span class="pill"><?php echo esc_html(ucfirst($p['compensation_method']));?></span><?php if(in_array($p['compensation_method'],['piecework','either'],true)):?><small>$<?php echo number_format_i18n((float)$p['piecework_rate'],2);?> / <?php echo esc_html($p['piecework_unit']);?></small><?php endif;?></td><td><strong><?php echo (float)($p['actual_retail']??0)>0?'$'.number_format_i18n((float)$p['actual_retail'],2):'Unavailable';?></strong><small>Wholesale: <?php echo (float)($p['actual_wholesale']??0)>0?'$'.number_format_i18n((float)$p['actual_wholesale'],2):'Unavailable';?></small></td><td><strong>$<?php echo number_format_i18n($cost['total'],2);?></strong></td><td>v<?php echo absint($p['version_number']);?></td><td><span class="lifecycle-badge is-<?php echo esc_attr($life);?>"><?php echo esc_html(Elev8_OS_Production_Catalog_Service::lifecycle_statuses()[$life]??ucfirst($life));?></span></td><td><a class="button button-small" href="<?php echo esc_url(self::url(['view'=>'edit-product','product_id'=>$p['id']]));?>">Edit</a></td></tr>
            <?php endforeach;?></tbody></table></div>
        </form><?php }?></section><?php
    }

    private static function product_form(int $id): void {
        $p=$id?Elev8_OS_Production_Catalog_Service::product($id):null;
        $materials=Elev8_OS_Production_Catalog_Service::materials();
        $rows=$p['materials']??[]; while(count($rows)<6)$rows[]=[];
        ?><p><a href="<?php echo esc_url(self::url());?>">← Back to Production Catalog</a></p><form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><?php wp_nonce_field('elev8_save_production_product');?><input type="hidden" name="action" value="elev8_os_save_production_product"><input type="hidden" name="product_id" value="<?php echo absint($id);?>"><section class="panel"><div class="panel-head"><div><h2><?php echo $id?'Edit production product':'Create production product';?></h2><p>Changes create a new version so future jobs can preserve historical payout and cost rules.</p></div><?php if($p):?><span class="version-badge">Version <?php echo absint($p['version_number']);?></span><?php endif;?></div><div class="form-grid"><label>Product name<input name="product_name" required value="<?php echo esc_attr($p['product_name']??'');?>"></label><label>Production code<input name="product_code" value="<?php echo esc_attr($p['product_code']??'');?>" placeholder="KNOB-BLACK"></label><label>Category<input name="category" value="<?php echo esc_attr($p['category']??'');?>" placeholder="Knobs, pulls, cremation, repairs"></label><label>Department<input name="department" value="<?php echo esc_attr($p['department']??'');?>" placeholder="Hot shop, cold work, shipping"></label><label>Skill level<select name="skill_level"><?php foreach(['entry'=>'Entry','standard'=>'Standard','advanced'=>'Advanced','master'=>'Master'] as $v=>$l):?><option value="<?php echo esc_attr($v);?>" <?php selected($p['skill_level']??'standard',$v);?>><?php echo esc_html($l);?></option><?php endforeach;?></select></label><label>Effective date<input type="date" name="effective_date" value="<?php echo esc_attr($p['effective_date']??current_time('Y-m-d'));?>"></label><label class="span-2">Description<textarea name="description" rows="3"><?php echo esc_textarea($p['description']??'');?></textarea></label><label class="span-2">Search aliases<textarea name="search_aliases" rows="2" placeholder="knob, color knob, custom knob, SSV wand, CON wand"><?php echo esc_textarea($p['search_aliases']??'');?></textarea><small>These words make Fast Glass Pay type-ahead easier without changing the official product name.</small></label><label>Source family<input name="source_family" value="<?php echo esc_attr($p['source_family']??'');?>"></label><label>Source subtype<input name="source_subtype" value="<?php echo esc_attr($p['source_subtype']??'');?>"></label><label>Source variant<input name="source_variant" value="<?php echo esc_attr($p['source_variant']??'');?>"></label><label>Lifecycle status<select name="lifecycle_status"><?php foreach(Elev8_OS_Production_Catalog_Service::lifecycle_statuses() as $value=>$label):?><option value="<?php echo esc_attr($value);?>" <?php selected($p['lifecycle_status']??(($p['active']??1)?'active':'archived'),$value);?>><?php echo esc_html($label);?></option><?php endforeach;?></select><small>Active appears in Fast Pay and new jobs. Draft is hidden while being completed. Archived preserves history but cannot be selected for new work.</small></label></div></section>
        <section class="panel"><h2>Compensation rule</h2><p>The production product—not the employee tab—determines whether this specific work is hourly or piecework.</p><div class="comp-grid"><?php foreach(['hourly'=>'Hourly','piecework'=>'Piecework','either'=>'Either — manager chooses on job','included'=>'Included in another production item'] as $v=>$l):?><label class="choice"><input type="radio" name="compensation_method" value="<?php echo esc_attr($v);?>" <?php checked($p['compensation_method']??'hourly',$v);?>><strong><?php echo esc_html($l);?></strong></label><?php endforeach;?></div><div class="form-grid"><label>Piecework payout ($)<input type="number" step="0.01" min="0" name="piecework_rate" value="<?php echo esc_attr($p['piecework_rate']??0);?>"></label><label>Paid per<select name="piecework_unit"><?php foreach(['piece','pair','set','batch','job'] as $u):?><option value="<?php echo esc_attr($u);?>" <?php selected($p['piecework_unit']??'piece',$u);?>><?php echo esc_html(ucfirst($u));?></option><?php endforeach;?></select></label><label>Estimated production minutes<input type="number" step="0.1" min="0" name="estimated_minutes" value="<?php echo esc_attr($p['estimated_minutes']??0);?>"></label><label>Hourly costing rate ($)<input type="number" step="0.01" min="0" name="costing_hourly_rate" value="<?php echo esc_attr($p['costing_hourly_rate']??18);?>"><small>Used only for estimated costing. Actual future job pay will use the blower’s compensation profile.</small></label><label class="check"><input type="checkbox" name="manager_approval_required" value="1" <?php checked((int)($p['manager_approval_required']??1),1);?>> Manager approval required before payroll</label></div></section>
        <section class="panel"><h2>Product financial model</h2><p>These values preserve the complete financial logic from the production information sheet. Blower pay remains the piecework payout above.</p><div class="form-grid"><label>Actual retail ($)<input type="number" step="0.01" name="actual_retail" value="<?php echo esc_attr($p['actual_retail']??0);?>"></label><label>Suggested retail ($)<input type="number" step="0.01" name="suggested_retail" value="<?php echo esc_attr($p['suggested_retail']??0);?>"></label><label>Actual wholesale ($)<input type="number" step="0.01" name="actual_wholesale" value="<?php echo esc_attr($p['actual_wholesale']??0);?>"></label><label>Suggested wholesale ($)<input type="number" step="0.01" name="suggested_wholesale" value="<?php echo esc_attr($p['suggested_wholesale']??0);?>"></label><label>Sold to distributor @ ($)<input type="number" step="0.01" name="sold_to_distributor_at" value="<?php echo esc_attr($p['sold_to_distributor_at']??0);?>"></label><label>Dist profit @ retail ($)<input type="number" step="0.01" name="dist_profit_at_retail" value="<?php echo esc_attr($p['dist_profit_at_retail']??0);?>"></label><label>Dist additional cost ($)<input type="number" step="0.01" name="dist_additional_cost" value="<?php echo esc_attr($p['dist_additional_cost']??0);?>"></label><label>Dist profit wholesale ($)<input type="number" step="0.01" name="dist_profit_wholesale" value="<?php echo esc_attr($p['dist_profit_wholesale']??0);?>"></label><label>Premier profit ($)<input type="number" step="0.01" name="premier_profit" value="<?php echo esc_attr($p['premier_profit']??0);?>"></label><label>Source material cost ($)<input type="number" step="0.01" name="source_material_cost" value="<?php echo esc_attr($p['source_material_cost']??0);?>"></label><label>Source total cost ($)<input type="number" step="0.01" name="source_total_cost" value="<?php echo esc_attr($p['source_total_cost']??0);?>"></label><label>Training video URL<input type="url" name="training_video_url" value="<?php echo esc_attr($p['training_video_url']??'');?>"></label><label class="span-2">Production instructions<textarea name="instructions" rows="5"><?php echo esc_textarea($p['instructions']??'');?></textarea></label></div></section>
        <section class="panel"><h2>Materials and direct costs</h2><p>Material costs are definitions for future job snapshots. Update costs here without changing completed-job history.</p><div class="bom-table"><div class="bom-row bom-head"><span>Material</span><span>Quantity</span><span>Waste %</span><span>Notes</span></div><?php foreach($rows as $i=>$r):?><div class="bom-row"><select name="materials[<?php echo $i;?>][material_id]"><option value="0">Choose material</option><?php foreach($materials as $m):?><option value="<?php echo absint($m['id']);?>" <?php selected(absint($r['material_id']??0),$m['id']);?>><?php echo esc_html($m['material_name'].' — $'.number_format_i18n((float)$m['unit_cost'],4).'/'.$m['unit']);?></option><?php endforeach;?></select><input type="number" step="0.0001" min="0" name="materials[<?php echo $i;?>][quantity]" value="<?php echo esc_attr($r['quantity']??'');?>"><input type="number" step="0.01" min="0" name="materials[<?php echo $i;?>][waste_percent]" value="<?php echo esc_attr($r['waste_percent']??0);?>"><input name="materials[<?php echo $i;?>][notes]" value="<?php echo esc_attr($r['notes']??'');?>"></div><?php endforeach;?></div><div class="form-grid cost-extras"><label>Consumables ($)<input type="number" step="0.01" min="0" name="consumable_cost" value="<?php echo esc_attr($p['consumable_cost']??0);?>"></label><label>Packaging ($)<input type="number" step="0.01" min="0" name="packaging_cost" value="<?php echo esc_attr($p['packaging_cost']??0);?>"></label><label>Other direct cost ($)<input type="number" step="0.01" min="0" name="other_cost" value="<?php echo esc_attr($p['other_cost']??0);?>"></label></div></section><p><button class="button button-primary button-hero">Save production product and create version</button></p></form>
        <?php if($id):$history=Elev8_OS_Production_Catalog_Service::version_history($id);?><section class="panel revision-history"><h2>Revision history</h2><p>Every saved change is preserved so completed jobs and pay snapshots remain historically accurate.</p><?php if(!$history):?><div class="empty">No saved revisions are available.</div><?php else:?><div class="revision-list"><?php foreach($history as $revision):$author=get_userdata((int)$revision['created_by']);?><div><strong>Version <?php echo absint($revision['version_number']);?></strong><span><?php echo esc_html(wp_date('F j, Y g:i a',strtotime($revision['effective_from'])));?></span><small><?php echo esc_html($author?$author->display_name:'System');?></small></div><?php endforeach;?></div><?php endif;?></section><?php endif;?>
        <?php
    }

    private static function materials(): void {
        $materials=Elev8_OS_Production_Catalog_Service::materials();
        ?><div class="elev8-production__columns"><section class="panel"><h2>Add or update material</h2><form class="stack" method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><?php wp_nonce_field('elev8_save_production_material');?><input type="hidden" name="action" value="elev8_os_save_production_material"><label>Material name<input name="material_name" required></label><label>Material code<input name="material_code" placeholder="GLASS-CLEAR"></label><label>Unit<input name="unit" value="gram" placeholder="gram, rod, foot, each"></label><label>Unit cost ($)<input type="number" step="0.0001" min="0" name="unit_cost" value="0"></label><label>Notes<textarea name="notes" rows="3"></textarea></label><label class="check"><input type="checkbox" name="active" value="1" checked> Active</label><button class="button button-primary">Save material</button></form></section><section class="panel panel-wide"><h2>Material catalog</h2><?php if(!$materials){echo '<div class="empty">No materials yet.</div>';}else{?><div class="table-wrap"><table><thead><tr><th>Material</th><th>Code</th><th>Unit</th><th>Unit cost</th><th>Status</th></tr></thead><tbody><?php foreach($materials as $m):?><tr><td><strong><?php echo esc_html($m['material_name']);?></strong></td><td><?php echo esc_html($m['material_code']);?></td><td><?php echo esc_html($m['unit']);?></td><td>$<?php echo number_format_i18n((float)$m['unit_cost'],4);?></td><td><?php echo $m['active']?'Active':'Inactive';?></td></tr><?php endforeach;?></tbody></table></div><?php }?></section></div><?php
    }

    private static function compensation(): void {
        $profiles=Elev8_OS_Production_Catalog_Service::compensation_profiles();$workers=Elev8_OS_Production_Catalog_Service::glass_workers();
        ?><div class="elev8-production__columns"><section class="panel"><h2>Glassblower compensation profile</h2><p>A blower may be hourly and still earn piecework for products explicitly marked piecework.</p><form class="stack" method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><?php wp_nonce_field('elev8_save_compensation_profile');?><input type="hidden" name="action" value="elev8_os_save_compensation_profile"><label>Glassblower<select name="user_id" required><option value="">Choose person</option><?php foreach($workers as $u):?><option value="<?php echo absint($u->ID);?>"><?php echo esc_html($u->display_name);?></option><?php endforeach;?></select></label><label>Hourly rate ($)<input type="number" step="0.01" min="0" name="hourly_rate" value="18"></label><label>Effective date<input type="date" name="effective_date" value="<?php echo esc_attr(current_time('Y-m-d'));?>"></label><label class="check"><input type="checkbox" name="piecework_eligible" value="1" checked> Eligible for product-specific piecework</label><label class="check"><input type="checkbox" name="active" value="1" checked> Active compensation profile</label><label>Notes<textarea name="notes" rows="3"></textarea></label><button class="button button-primary">Save compensation profile</button></form></section><section class="panel panel-wide"><h2>Current compensation profiles</h2><?php if(!$profiles){echo '<div class="empty">No compensation profiles yet. Add Nick and Adam at $18/hour if they were not matched automatically.</div>';}else{?><div class="table-wrap"><table><thead><tr><th>Glassblower</th><th>Hourly rate</th><th>Piecework eligible</th><th>Effective</th><th>Status</th></tr></thead><tbody><?php foreach($profiles as $r):$u=get_userdata((int)$r['user_id']);?><tr><td><strong><?php echo esc_html($u?$u->display_name:'Unknown user');?></strong></td><td>$<?php echo number_format_i18n((float)$r['hourly_rate'],2);?> / hour</td><td><?php echo $r['piecework_eligible']?'Yes':'No';?></td><td><?php echo esc_html($r['effective_date']?:'Unavailable');?></td><td><?php echo $r['active']?'Active':'Inactive';?></td></tr><?php endforeach;?></tbody></table></div><?php }?></section></div><?php
    }



    private static function wizard(): void {
        $session = Elev8_OS_Glass_Catalog_Import_Service::session();
        $family = sanitize_text_field($_GET['family'] ?? '');
        ?>
        <section class="panel elev8-wizard">
            <div class="panel-head">
                <div>
                    <h2>Glass Catalog Import Wizard</h2>
                    <p>Upload the original workbook, review one production family at a time, then import approved items into the Production Catalog.</p>
                </div>
                <?php if ($session): ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('elev8_wizard_clear'); ?>
                        <input type="hidden" name="action" value="elev8_os_glass_catalog_wizard_clear">
                        <button class="button">Start over</button>
                    </form>
                <?php endif; ?>
            </div>

            <?php if (!$session): ?>
                <div class="wizard-step">
                    <span class="step-number">1</span>
                    <div class="grow">
                        <h3>Upload the production workbook</h3>
                        <p>Elev8 OS reads the <strong>Production Information</strong> sheet and never modifies the uploaded file.</p>
                        <form class="wizard-upload-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <?php wp_nonce_field('elev8_wizard_upload'); ?>
                            <input type="hidden" name="action" value="elev8_os_glass_catalog_wizard_upload">
                            <label>Production workbook
                                <input type="file" name="workbook" accept=".xlsx,.xlsm" required>
                            </label>
                            <p class="description">Accepted: .xlsx or .xlsm. Current site upload limit: <strong><?php echo esc_html(Elev8_OS_Glass_Catalog_Import_Service::max_upload_label()); ?></strong>.</p>
                            <button class="button button-primary">Analyze workbook</button>
                        </form>
                        <div class="wizard-help">
                            <strong>What should happen next?</strong>
                            <span>After analysis, this page will show the number of product families and detected items. If the server cannot read the file, Elev8 OS will display the exact reason here.</span>
                        </div>
                    </div>
                </div>
                <?php return; ?>
            <?php endif;

            $families = $session['families'] ?? [];
            $items = $session['items'] ?? [];
            $diagnostics = $session['diagnostics'] ?? [];
            ?>
            <div class="wizard-success">
                <strong>Workbook analyzed successfully.</strong>
                <span><?php echo esc_html($session['file_name'] ?? 'Workbook'); ?> is ready for family-by-family review.</span>
            </div>
            <div class="wizard-summary">
                <div><strong><?php echo absint($session['family_count'] ?? count($families)); ?></strong><span>Product families</span></div>
                <div><strong><?php echo absint($session['item_count'] ?? count($items)); ?></strong><span>Detected items</span></div>
                <div><strong><?php echo esc_html($session['file_name'] ?? 'Workbook'); ?></strong><span>Uploaded source</span></div>
            </div>

            <?php if ($diagnostics): ?>
                <details class="wizard-diagnostics">
                    <summary>Workbook diagnostics</summary>
                    <div class="diagnostic-grid">
                        <div><strong><?php echo esc_html($diagnostics['sheet_path'] ?? 'Unavailable'); ?></strong><span>Detected worksheet file</span></div>
                        <div><strong><?php echo absint($diagnostics['direct_cells'] ?? 0); ?></strong><span>Direct cells read</span></div>
                        <div><strong><?php echo absint($diagnostics['merged_ranges'] ?? 0); ?></strong><span>Merged ranges found</span></div>
                        <div><strong><?php echo esc_html($diagnostics['max_column'] ?? 'Unavailable'); ?></strong><span>Last used column</span></div>
                        <div><strong><?php echo absint($diagnostics['family_blocks'] ?? 0); ?></strong><span>Family blocks detected</span></div>
                        <div><strong><?php echo absint($diagnostics['skipped_columns'] ?? 0); ?></strong><span>Empty columns skipped</span></div>
                    </div>
                    <?php if (!empty($diagnostics['detected_rows'])): ?>
                        <p><strong>Detected source rows:</strong>
                            <?php
                            $row_labels = [];
                            foreach ($diagnostics['detected_rows'] as $key => $row) {
                                $row_labels[] = ucwords(str_replace('_', ' ', $key)) . ': ' . absint($row);
                            }
                            echo esc_html(implode(' · ', $row_labels));
                            ?>
                        </p>
                    <?php endif; ?>
                    <?php if (!empty($diagnostics['warnings'])): ?>
                        <ul><?php foreach ($diagnostics['warnings'] as $warning): ?><li><?php echo esc_html($warning); ?></li><?php endforeach; ?></ul>
                    <?php endif; ?>
                </details>
            <?php endif; ?>

            <?php if (!$family || empty($families[$family])): ?>
                <div class="wizard-step">
                    <span class="step-number">2</span>
                    <div class="grow">
                        <h3>Choose a product family</h3>
                        <p>Review the workbook in the same groups the glass team already understands.</p>
                        <div class="family-grid">
                            <?php foreach ($families as $name => $cols): ?>
                                <a class="family-card" href="<?php echo esc_url(self::url(['view' => 'wizard', 'family' => $name])); ?>">
                                    <strong><?php echo esc_html($name); ?></strong>
                                    <span><?php echo count($cols); ?> detected item<?php echo count($cols) === 1 ? '' : 's'; ?></span>
                                    <em>Review family →</em>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php return; ?>
            <?php endif;

            $cols = $families[$family];
            ?>
            <p><a href="<?php echo esc_url(self::url(['view' => 'wizard'])); ?>">← All product families</a></p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('elev8_wizard_import'); ?>
                <input type="hidden" name="action" value="elev8_os_glass_catalog_wizard_import">
                <input type="hidden" name="family" value="<?php echo esc_attr($family); ?>">
                <section class="wizard-family">
                    <div class="panel-head">
                        <div>
                            <p class="eyebrow">Product family</p>
                            <h3><?php echo esc_html($family); ?></h3>
                            <p>Edit names and aliases into exactly what the Glass Manager should see while typing in Fast Glass Pay.</p>
                        </div>
                        <label class="check"><input type="checkbox" name="update_existing" value="1"> Update matching workbook items already imported</label>
                    </div>
                    <div class="wizard-items">
                        <?php foreach ($cols as $col): $r = $items[$col] ?? []; ?>
                            <article class="wizard-item">
                                <div class="wizard-item__select">
                                    <?php $source_code=(string)($r['product_code']??('WB-'.$col));$ignored=Elev8_OS_Production_Catalog_Service::is_source_ignored($source_code);?>
                                    <select name="items[<?php echo esc_attr($col); ?>][decision]" aria-label="Import decision">
                                        <option value="import" <?php selected(!$ignored,true);?>>Import / Update</option>
                                        <option value="skip">Skip this time</option>
                                        <option value="ignore" <?php selected($ignored,true);?>>Ignore forever</option>
                                        <?php if($ignored):?><option value="restore">Restore from ignored</option><?php endif;?>
                                    </select>
                                    <span><?php echo esc_html($col); ?></span>
                                </div>
                                <div class="wizard-item__fields">
                                    <label>Catalog name
                                        <input name="items[<?php echo esc_attr($col); ?>][catalog_name]" value="<?php echo esc_attr($r['catalog_name'] ?? ''); ?>">
                                    </label>
                                    <label>Search aliases
                                        <textarea name="items[<?php echo esc_attr($col); ?>][search_aliases]" rows="2"><?php echo esc_textarea($r['search_aliases'] ?? ''); ?></textarea>
                                    </label>
                                    <div class="wizard-item__row">
                                        <label>Pay method
                                            <select name="items[<?php echo esc_attr($col); ?>][compensation_method]">
                                                <?php foreach (['piecework' => 'Piecework', 'hourly' => 'Hourly', 'either' => 'Either'] as $value => $label): ?>
                                                    <option value="<?php echo esc_attr($value); ?>" <?php selected($r['compensation_method'] ?? 'piecework', $value); ?>><?php echo esc_html($label); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </label>
                                        <label>Blower pay
                                            <input type="number" step="0.0001" min="0" name="items[<?php echo esc_attr($col); ?>][blower_pay]" value="<?php echo esc_attr($r['blower_pay'] ?? 0); ?>">
                                        </label>
                                    </div>
                                    <div class="financial-strip">
                                        <span>Retail <strong>$<?php echo number_format_i18n((float) ($r['actual_retail'] ?? 0), 2); ?></strong></span>
                                        <span>Wholesale <strong>$<?php echo number_format_i18n((float) ($r['actual_wholesale'] ?? 0), 2); ?></strong></span>
                                        <span>Material <strong>$<?php echo number_format_i18n((float) ($r['material_cost'] ?? 0), 2); ?></strong></span>
                                        <span>Total cost <strong>$<?php echo number_format_i18n((float) ($r['total_cost'] ?? 0), 2); ?></strong></span>
                                        <span>Time <strong><?php echo number_format_i18n((float) ($r['estimated_minutes'] ?? 0), 2); ?> min</strong></span>
                                    </div>
                                    <small class="source-trace">Source: Production Information!<?php echo esc_html($r['source_column'] ?? $col); ?><?php if (!empty($r['alternate_source_columns'])): ?> · Alternate columns: <?php echo esc_html($r['alternate_source_columns']); ?><?php endif; ?></small>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                    <div class="wizard-actions">
                        <button type="button" class="button" onclick="this.closest('form').querySelectorAll('.wizard-item select[name*=decision]').forEach(function(x){x.value='import'})">Import all</button>
                        <button type="button" class="button" onclick="this.closest('form').querySelectorAll('.wizard-item select[name*=decision]').forEach(function(x){x.value='skip'})">Skip all this time</button>
                        <button class="button button-primary">Import selected family</button>
                    </div>
                </section>
            </form>
        </section>
        <?php
    }

    public static function wizard_upload(): void {
        self::guard();
        check_admin_referer('elev8_wizard_upload');
        $result = Elev8_OS_Glass_Catalog_Import_Service::upload_and_parse($_FILES['workbook'] ?? []);
        if (is_wp_error($result)) {
            wp_safe_redirect(self::url([
                'view' => 'wizard',
                'notice_type' => 'error',
                'notice' => $result->get_error_message(),
            ]));
            exit;
        }
        wp_safe_redirect(self::url([
            'view' => 'wizard',
            'notice_type' => 'success',
            'notice' => sprintf(
                'Workbook analyzed: %d product families and %d items detected.',
                (int) $result['family_count'],
                (int) $result['item_count']
            ),
        ]));
        exit;
    }

    public static function wizard_import(): void {
        self::guard();
        check_admin_referer('elev8_wizard_import');
        $family = sanitize_text_field(wp_unslash($_POST['family'] ?? ''));
        $summary = Elev8_OS_Glass_Catalog_Import_Service::import_family(
            $family,
            (array) wp_unslash($_POST['items'] ?? []),
            !empty($_POST['update_existing'])
        );
        $notice = sprintf(
            '%s import complete: %d created, %d updated, %d skipped.',
            $family,
            (int) $summary['created'],
            (int) $summary['updated'],
            (int) $summary['skipped']
        );
        if ($summary['errors']) {
            $notice .= ' Errors: ' . implode(' | ', array_slice($summary['errors'], 0, 3));
        }
        wp_safe_redirect(self::url(['view' => 'wizard', 'family' => $family, 'notice' => $notice]));
        exit;
    }

    public static function wizard_clear(): void {
        self::guard();
        check_admin_referer('elev8_wizard_clear');
        Elev8_OS_Glass_Catalog_Import_Service::clear();
        wp_safe_redirect(self::url(['view' => 'wizard', 'notice' => 'Import wizard reset.']));
        exit;
    }

    private static function migration(): void {
        $records = Elev8_OS_Production_Catalog_Service::migration_records();
        $ready = array_values(array_filter($records, static fn($r) => ($r['import_status'] ?? '') === 'Ready'));
        $review = count($records) - count($ready);
        ?><section class="panel elev8-migration"><div class="panel-head"><div><h2>Glass Production Catalog Migration</h2><p>Step 1 normalized the original Production Information tab into one reviewable row per production item. Import only the records you approve.</p></div><a class="button" href="<?php echo esc_url(ELEV8_OS_URL.'assets/data/glass-production-catalog-migration.json');?>">Download source JSON</a></div><div class="migration-summary"><div><strong><?php echo count($records);?></strong><span>Normalized items</span></div><div><strong><?php echo count($ready);?></strong><span>Ready</span></div><div><strong><?php echo absint($review);?></strong><span>Needs review</span></div></div>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><?php wp_nonce_field('elev8_import_production_migration');?><input type="hidden" name="action" value="elev8_os_import_production_migration"><div class="migration-actions"><button type="button" class="button" onclick="document.querySelectorAll('.elev8-migration input[type=checkbox][data-ready]').forEach(function(el){el.checked=true;});">Select all ready</button><button type="button" class="button" onclick="document.querySelectorAll('.elev8-migration input[type=checkbox][name*=source_columns]').forEach(function(el){el.checked=false;});">Clear selection</button><label class="check"><input type="checkbox" name="update_existing" value="1"> Update products previously imported with the same migration code</label><button class="button button-primary">Import selected items</button></div><div class="table-wrap"><table class="migration-table"><thead><tr><th></th><th>Status</th><th>Catalog name</th><th>Aliases</th><th>Blower pay</th><th>Retail</th><th>Wholesale</th><th>Material</th><th>Total cost</th><th>Source</th></tr></thead><tbody><?php foreach($records as $r):$is_ready=($r['import_status']??'')==='Ready';?><tr class="<?php echo $is_ready?'is-ready':'needs-review';?>"><td><input type="checkbox" name="source_columns[]" value="<?php echo esc_attr($r['source_column']);?>" <?php checked($is_ready);?> <?php echo $is_ready?'data-ready="1"':'';?>></td><td><span class="pill"><?php echo esc_html($r['import_status']??'Review');?></span></td><td><strong><?php echo esc_html($r['catalog_name']);?></strong><small><?php echo esc_html(trim(($r['family']??'').' · '.($r['subtype']??''),' ·'));?></small><?php if(!empty($r['review_notes'])):?><small class="warning"><?php echo esc_html($r['review_notes']);?></small><?php endif;?></td><td><?php echo esc_html($r['search_aliases']??'');?></td><td><?php echo isset($r['blower_pay'])&&$r['blower_pay']!==null?'$'.number_format_i18n((float)$r['blower_pay'],4):'Unavailable';?></td><td><?php echo isset($r['actual_retail'])&&$r['actual_retail']!==null?'$'.number_format_i18n((float)$r['actual_retail'],2):'Unavailable';?></td><td><?php echo isset($r['actual_wholesale'])&&$r['actual_wholesale']!==null?'$'.number_format_i18n((float)$r['actual_wholesale'],2):'Unavailable';?></td><td><?php echo isset($r['material_cost'])&&$r['material_cost']!==null?'$'.number_format_i18n((float)$r['material_cost'],2):'Unavailable';?></td><td><?php echo isset($r['total_cost'])&&$r['total_cost']!==null?'$'.number_format_i18n((float)$r['total_cost'],2):'Unavailable';?></td><td><?php echo esc_html(($r['source_sheet']??'').'!'.($r['source_column']??''));?></td></tr><?php endforeach;?></tbody></table></div></form></section><?php
    }

    public static function import_migration(): void {
        self::guard();
        check_admin_referer('elev8_import_production_migration');
        $summary = Elev8_OS_Production_Catalog_Service::import_migration_records((array)($_POST['source_columns'] ?? []), !empty($_POST['update_existing']));
        $notice = sprintf('Migration complete: %d created, %d updated, %d skipped.', (int)$summary['created'], (int)$summary['updated'], (int)$summary['skipped']);
        if (!empty($summary['errors'])) { $notice .= ' Errors: ' . implode(' | ', array_slice($summary['errors'],0,5)); }
        wp_safe_redirect(self::url(['view'=>'migration','notice'=>$notice]));
        exit;
    }


    private static function manager(): void {
        $categories=Elev8_OS_Production_Catalog_Service::categories();
        $duplicates=Elev8_OS_Production_Catalog_Service::duplicate_candidates();
        $ignored=Elev8_OS_Production_Catalog_Service::ignored_sources();
        ?><section class="panel catalog-manager"><div class="panel-head"><div><h2>Production Catalog Manager</h2><p>Manage lifecycle, families, duplicates and imported workbook rows without deleting historical production records.</p></div><a class="button" href="<?php echo esc_url(self::url());?>">Back to products</a></div>
        <div class="manager-summary"><div><strong><?php echo count($categories);?></strong><span>Families</span></div><div><strong><?php echo count($duplicates);?></strong><span>Possible duplicate names</span></div><div><strong><?php echo count($ignored);?></strong><span>Workbook rows ignored</span></div></div>
        <div class="manager-grid"><section><h3>Family management</h3><p>Use the Products page bulk controls to move selected records into a new or existing family. Current families:</p><div class="family-pills"><?php foreach($categories as $category):?><a href="<?php echo esc_url(self::url(['category'=>$category]));?>"><?php echo esc_html($category);?></a><?php endforeach;?></div></section>
        <section><h3>Ignored workbook rows</h3><?php if(!$ignored):?><p>No workbook rows are ignored.</p><?php else:?><p>Ignored rows remain excluded from future imports until restored from the Import Wizard.</p><ul class="ignored-list"><?php foreach($ignored as $code):?><li><code><?php echo esc_html($code);?></code></li><?php endforeach;?></ul><?php endif;?></section></div>
        <section class="duplicates"><h3>Possible duplicates</h3><p>Merging archives the duplicate records and points them to one surviving target. Historical jobs and pay records remain attached to their original snapshots.</p>
        <?php if(!$duplicates):?><div class="empty">No exact duplicate product names were detected.</div><?php else:foreach($duplicates as $dup):$ids=array_map('absint',explode(',',(string)$dup['product_ids']));?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>" class="duplicate-card"><?php wp_nonce_field('elev8_production_catalog_merge');?><input type="hidden" name="action" value="elev8_os_production_catalog_merge"><strong><?php echo esc_html($dup['product_name']);?></strong><span><?php echo absint($dup['duplicate_count']);?> matching records</span><label>Keep target <select name="target_id"><?php foreach($ids as $id):?><option value="<?php echo $id;?>">Product #<?php echo $id;?></option><?php endforeach;?></select></label><div class="merge-sources"><?php foreach($ids as $id):?><label><input type="checkbox" name="source_ids[]" value="<?php echo $id;?>"> Archive #<?php echo $id;?></label><?php endforeach;?></div><button class="button">Merge selected duplicates</button></form>
        <?php endforeach;endif;?></section></section><?php
    }

    public static function bulk_products(): void {
        self::guard();check_admin_referer('elev8_production_catalog_bulk');
        $result=Elev8_OS_Production_Catalog_Service::bulk_update_products((array)($_POST['product_ids']??[]),sanitize_key($_POST['bulk_action']??''),sanitize_text_field($_POST['bulk_value']??''));
        $notice=is_wp_error($result)?$result->get_error_message():sprintf('%d production products updated.',(int)$result['updated']);
        wp_safe_redirect(self::url(['notice'=>$notice,'notice_type'=>is_wp_error($result)?'error':'success']));exit;
    }

    public static function merge_products(): void {
        self::guard();check_admin_referer('elev8_production_catalog_merge');
        $target=absint($_POST['target_id']??0);$sources=(array)($_POST['source_ids']??[]);
        $result=Elev8_OS_Production_Catalog_Service::merge_products($target,$sources);
        $notice=is_wp_error($result)?$result->get_error_message():sprintf('%d duplicate products archived and merged into product #%d.',(int)$result['merged'],(int)$result['target_id']);
        wp_safe_redirect(self::url(['view'=>'manager','notice'=>$notice,'notice_type'=>is_wp_error($result)?'error':'success']));exit;
    }

    public static function save_product(): void { self::guard();check_admin_referer('elev8_save_production_product');$r=Elev8_OS_Production_Catalog_Service::save_product(wp_unslash($_POST));wp_safe_redirect(self::url(['view'=>is_wp_error($r)?'new-product':'edit-product','product_id'=>is_wp_error($r)?0:$r,'notice'=>is_wp_error($r)?$r->get_error_message():'Production product saved and versioned.']));exit; }
    public static function save_material(): void { self::guard();check_admin_referer('elev8_save_production_material');$r=Elev8_OS_Production_Catalog_Service::save_material(wp_unslash($_POST));wp_safe_redirect(self::url(['view'=>'materials','notice'=>is_wp_error($r)?$r->get_error_message():'Material saved.']));exit; }
    public static function save_profile(): void { self::guard();check_admin_referer('elev8_save_compensation_profile');$r=Elev8_OS_Production_Catalog_Service::save_compensation_profile(wp_unslash($_POST));wp_safe_redirect(self::url(['view'=>'compensation','notice'=>is_wp_error($r)?$r->get_error_message():'Compensation profile saved.']));exit; }
}

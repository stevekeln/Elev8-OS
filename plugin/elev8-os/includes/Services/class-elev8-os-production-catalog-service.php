<?php
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Production_Catalog_Service {
    const DB_VERSION = '1.2.0';
    const OPTION_IGNORED_SOURCES = 'elev8_os_glass_catalog_ignored_sources';
    const OPTION_DB_VERSION = 'elev8_os_production_catalog_db_version';

    public static function init(): void {
        add_action('init', [__CLASS__, 'maybe_install'], 8);
    }

    public static function activate(): void {
        self::install();
        self::seed_initial_compensation_profiles();
    }

    public static function tables(): array {
        global $wpdb;
        return [
            'products' => $wpdb->prefix . 'elev8_production_products',
            'materials' => $wpdb->prefix . 'elev8_production_materials',
            'product_materials' => $wpdb->prefix . 'elev8_production_product_materials',
            'compensation_profiles' => $wpdb->prefix . 'elev8_production_compensation_profiles',
            'versions' => $wpdb->prefix . 'elev8_production_product_versions',
        ];
    }

    public static function maybe_install(): void {
        if (get_option(self::OPTION_DB_VERSION) !== self::DB_VERSION) {
            self::install();
            self::seed_initial_compensation_profiles();
        }
    }

    private static function install(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $t = self::tables();
        $c = $wpdb->get_charset_collate();

        dbDelta("CREATE TABLE {$t['products']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            product_code varchar(80) NOT NULL DEFAULT '',
            product_name varchar(190) NOT NULL DEFAULT '',
            category varchar(100) NOT NULL DEFAULT '',
            description text NOT NULL,
            search_aliases text NOT NULL,
            source_family varchar(190) NOT NULL DEFAULT '',
            source_subtype varchar(190) NOT NULL DEFAULT '',
            source_variant varchar(190) NOT NULL DEFAULT '',
            actual_retail decimal(14,2) NOT NULL DEFAULT 0.00,
            dist_profit_at_retail decimal(14,2) NOT NULL DEFAULT 0.00,
            dist_additional_cost decimal(14,2) NOT NULL DEFAULT 0.00,
            suggested_retail decimal(14,2) NOT NULL DEFAULT 0.00,
            dist_profit_wholesale decimal(14,2) NOT NULL DEFAULT 0.00,
            premier_profit decimal(14,2) NOT NULL DEFAULT 0.00,
            actual_wholesale decimal(14,2) NOT NULL DEFAULT 0.00,
            suggested_wholesale decimal(14,2) NOT NULL DEFAULT 0.00,
            sold_to_distributor_at decimal(14,2) NOT NULL DEFAULT 0.00,
            source_material_cost decimal(14,2) NOT NULL DEFAULT 0.00,
            source_total_cost decimal(14,2) NOT NULL DEFAULT 0.00,
            instructions longtext NOT NULL,
            training_video_url text NOT NULL,
            source_sheet varchar(190) NOT NULL DEFAULT '',
            source_column varchar(20) NOT NULL DEFAULT '',
            alternate_source_columns text NOT NULL,
            alternate_pay_tiers_json longtext NOT NULL,
            active tinyint(1) NOT NULL DEFAULT 1,
            lifecycle_status varchar(20) NOT NULL DEFAULT 'active',
            merged_into_product_id bigint(20) unsigned NOT NULL DEFAULT 0,
            skill_level varchar(40) NOT NULL DEFAULT 'standard',
            department varchar(100) NOT NULL DEFAULT '',
            compensation_method varchar(30) NOT NULL DEFAULT 'hourly',
            piecework_rate decimal(12,2) NOT NULL DEFAULT 0.00,
            piecework_unit varchar(30) NOT NULL DEFAULT 'piece',
            effective_date date NULL,
            manager_approval_required tinyint(1) NOT NULL DEFAULT 1,
            estimated_minutes decimal(10,2) NOT NULL DEFAULT 0.00,
            costing_hourly_rate decimal(12,2) NOT NULL DEFAULT 0.00,
            consumable_cost decimal(12,2) NOT NULL DEFAULT 0.00,
            packaging_cost decimal(12,2) NOT NULL DEFAULT 0.00,
            other_cost decimal(12,2) NOT NULL DEFAULT 0.00,
            included_parent_product_id bigint(20) unsigned NOT NULL DEFAULT 0,
            version_number int(10) unsigned NOT NULL DEFAULT 1,
            created_by bigint(20) unsigned NOT NULL DEFAULT 0,
            updated_by bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY product_code (product_code),
            KEY active_category (active,category),
            KEY lifecycle_status (lifecycle_status),
            KEY merged_into_product_id (merged_into_product_id),
            KEY compensation_method (compensation_method)
        ) {$c};");

        dbDelta("CREATE TABLE {$t['materials']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            material_name varchar(190) NOT NULL DEFAULT '',
            material_code varchar(80) NOT NULL DEFAULT '',
            unit varchar(40) NOT NULL DEFAULT 'unit',
            unit_cost decimal(14,4) NOT NULL DEFAULT 0.0000,
            active tinyint(1) NOT NULL DEFAULT 1,
            notes text NOT NULL,
            created_by bigint(20) unsigned NOT NULL DEFAULT 0,
            updated_by bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY material_code (material_code),
            KEY active_name (active,material_name)
        ) {$c};");

        dbDelta("CREATE TABLE {$t['product_materials']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            product_id bigint(20) unsigned NOT NULL DEFAULT 0,
            material_id bigint(20) unsigned NOT NULL DEFAULT 0,
            quantity decimal(14,4) NOT NULL DEFAULT 0.0000,
            waste_percent decimal(8,2) NOT NULL DEFAULT 0.00,
            unit_cost_snapshot decimal(14,4) NOT NULL DEFAULT 0.0000,
            calculated_cost decimal(14,4) NOT NULL DEFAULT 0.0000,
            notes varchar(255) NOT NULL DEFAULT '',
            sort_order int(10) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY product_id (product_id),
            KEY material_id (material_id)
        ) {$c};");

        dbDelta("CREATE TABLE {$t['compensation_profiles']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            hourly_rate decimal(12,2) NOT NULL DEFAULT 0.00,
            piecework_eligible tinyint(1) NOT NULL DEFAULT 1,
            active tinyint(1) NOT NULL DEFAULT 1,
            effective_date date NULL,
            notes text NOT NULL,
            created_by bigint(20) unsigned NOT NULL DEFAULT 0,
            updated_by bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id),
            KEY active (active)
        ) {$c};");

        dbDelta("CREATE TABLE {$t['versions']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            product_id bigint(20) unsigned NOT NULL DEFAULT 0,
            version_number int(10) unsigned NOT NULL DEFAULT 1,
            snapshot_json longtext NOT NULL,
            effective_from datetime NOT NULL,
            created_by bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY product_version (product_id,version_number),
            KEY product_id (product_id)
        ) {$c};");

        // Preserve legacy inactive records as archived when the lifecycle column is first introduced.
        $wpdb->query("UPDATE {$t['products']} SET lifecycle_status='archived' WHERE active=0 AND lifecycle_status='active'");
        update_option(self::OPTION_DB_VERSION, self::DB_VERSION, false);
    }

    public static function products(array $args = []): array {
        global $wpdb;
        $t = self::tables();
        $where = ['1=1'];
        $params = [];
        if (isset($args['active']) && $args['active'] !== '') { $where[] = 'active=%d'; $params[] = absint($args['active']); }
        if (!empty($args['lifecycle_status'])) { $where[] = 'lifecycle_status=%s'; $params[] = sanitize_key($args['lifecycle_status']); }
        if (!empty($args['category'])) { $where[] = 'category=%s'; $params[] = sanitize_text_field($args['category']); }
        if (!empty($args['search'])) {
            $like = '%' . $wpdb->esc_like((string)$args['search']) . '%';
            $where[] = '(product_name LIKE %s OR product_code LIKE %s OR category LIKE %s OR search_aliases LIKE %s OR source_family LIKE %s OR source_subtype LIKE %s OR source_variant LIKE %s)';
            array_push($params, $like, $like, $like, $like, $like, $like, $like);
        }
        $sql = "SELECT * FROM {$t['products']} WHERE " . implode(' AND ', $where) . " ORDER BY FIELD(lifecycle_status,'active','draft','archived') ASC, category ASC, product_name ASC";
        if ($params) { $sql = $wpdb->prepare($sql, $params); }
        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    public static function pay_search(string $term = '', int $limit = 30): array {
        $products = self::products(['active'=>1, 'search'=>sanitize_text_field($term)]);
        $out=[];
        foreach(array_slice($products,0,max(1,min(100,$limit))) as $product){
            $method=(string)$product['compensation_method'];
            if($method==='included'){continue;}
            $out[]=[
                'id'=>(int)$product['id'],'name'=>(string)$product['product_name'],'code'=>(string)$product['product_code'],
                'category'=>(string)$product['category'],'method'=>$method,'piecework_rate'=>(float)$product['piecework_rate'],
                'piecework_unit'=>(string)$product['piecework_unit'],'estimated_minutes'=>(float)$product['estimated_minutes'],
                'aliases'=>(string)($product['search_aliases']??''),'actual_retail'=>(float)($product['actual_retail']??0),
                'actual_wholesale'=>(float)($product['actual_wholesale']??0),'source_family'=>(string)($product['source_family']??''),
                'source_subtype'=>(string)($product['source_subtype']??''),'source_variant'=>(string)($product['source_variant']??''),
                'cost_complete'=>((float)$product['consumable_cost']+(float)$product['packaging_cost']+(float)$product['other_cost'])>0 || !empty(self::product_materials((int)$product['id']))
            ];
        }
        return $out;
    }

    public static function quick_create_pay_item(array $data): int|WP_Error {
        $method=sanitize_key($data['compensation_method']??'piecework');
        if(!in_array($method,['hourly','piecework','either'],true)){$method='piecework';}
        $name=sanitize_text_field($data['product_name']??'');
        if($name===''){return new WP_Error('quick_pay_name','Enter a name for the new pay item.');}
        if($method==='piecework' && (float)($data['piecework_rate']??0)<=0){return new WP_Error('quick_pay_rate','Enter a piecework payout greater than zero.');}
        return self::save_product([
            'product_name'=>$name,'product_code'=>$data['product_code']??'','category'=>$data['category']??'Quick Pay Items',
            'description'=>'Created from Glass Manager Fast Pay Entry. Material and full costing details may still need completion.',
            'active'=>1,'skill_level'=>'standard','department'=>'Glass Operations','compensation_method'=>$method,
            'piecework_rate'=>$data['piecework_rate']??0,'piecework_unit'=>$data['piecework_unit']??'piece',
            'effective_date'=>$data['effective_date']??current_time('Y-m-d'),'manager_approval_required'=>1,
            'estimated_minutes'=>$data['estimated_minutes']??0,'costing_hourly_rate'=>0,'consumable_cost'=>0,'packaging_cost'=>0,'other_cost'=>0,
        ]);
    }

    public static function product(int $id): ?array {
        global $wpdb; $t = self::tables();
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['products']} WHERE id=%d", $id), ARRAY_A);
        if (!$row) { return null; }
        $row['materials'] = self::product_materials($id);
        return $row;
    }

    public static function save_product(array $data): int|WP_Error {
        global $wpdb; $t = self::tables(); $now = current_time('mysql');
        $id = absint($data['product_id'] ?? 0);
        $method = sanitize_key($data['compensation_method'] ?? 'hourly');
        if (!in_array($method, ['hourly','piecework','either','included'], true)) { $method = 'hourly'; }
        $unit = sanitize_key($data['piecework_unit'] ?? 'piece');
        if (!in_array($unit, ['piece','pair','set','batch','job'], true)) { $unit = 'piece'; }
        $code = strtoupper(sanitize_key(str_replace(' ', '-', (string)($data['product_code'] ?? ''))));
        $name = sanitize_text_field($data['product_name'] ?? '');
        if ($name === '') { return new WP_Error('production_product_name', 'Enter a production product name.'); }
        if ($code === '') { $code = strtoupper(sanitize_key(wp_unique_id('PROD-'))); }
        $status = sanitize_key($data['lifecycle_status'] ?? '');
        if ($status === '') {
            $status = empty($data['active']) ? 'archived' : 'active';
        }
        if (!in_array($status, ['active','draft','archived'], true)) { $status = 'draft'; }
        $row = [
            'product_code' => $code,
            'product_name' => $name,
            'category' => sanitize_text_field($data['category'] ?? ''),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'search_aliases' => sanitize_textarea_field($data['search_aliases'] ?? ''),
            'source_family' => sanitize_text_field($data['source_family'] ?? ''),
            'source_subtype' => sanitize_text_field($data['source_subtype'] ?? ''),
            'source_variant' => sanitize_text_field($data['source_variant'] ?? ''),
            'actual_retail' => max(0, (float)($data['actual_retail'] ?? 0)),
            'dist_profit_at_retail' => (float)($data['dist_profit_at_retail'] ?? 0),
            'dist_additional_cost' => (float)($data['dist_additional_cost'] ?? 0),
            'suggested_retail' => max(0, (float)($data['suggested_retail'] ?? 0)),
            'dist_profit_wholesale' => (float)($data['dist_profit_wholesale'] ?? 0),
            'premier_profit' => (float)($data['premier_profit'] ?? 0),
            'actual_wholesale' => max(0, (float)($data['actual_wholesale'] ?? 0)),
            'suggested_wholesale' => max(0, (float)($data['suggested_wholesale'] ?? 0)),
            'sold_to_distributor_at' => max(0, (float)($data['sold_to_distributor_at'] ?? 0)),
            'source_material_cost' => max(0, (float)($data['source_material_cost'] ?? ($data['material_cost'] ?? 0))),
            'source_total_cost' => max(0, (float)($data['source_total_cost'] ?? ($data['total_cost'] ?? 0))),
            'instructions' => sanitize_textarea_field($data['instructions'] ?? ''),
            'training_video_url' => esc_url_raw($data['training_video_url'] ?? ($data['video_url'] ?? '')),
            'source_sheet' => sanitize_text_field($data['source_sheet'] ?? ''),
            'source_column' => sanitize_text_field($data['source_column'] ?? ''),
            'alternate_source_columns' => sanitize_text_field($data['alternate_source_columns'] ?? ''),
            'alternate_pay_tiers_json' => wp_json_encode(json_decode((string)($data['alternate_pay_tiers_json'] ?? '[]'), true) ?: []),
            'active' => $status === 'active' ? 1 : 0,
            'lifecycle_status' => $status,
            'merged_into_product_id' => absint($data['merged_into_product_id'] ?? 0),
            'skill_level' => sanitize_key($data['skill_level'] ?? 'standard'),
            'department' => sanitize_text_field($data['department'] ?? ''),
            'compensation_method' => $method,
            'piecework_rate' => max(0, (float)($data['piecework_rate'] ?? 0)),
            'piecework_unit' => $unit,
            'effective_date' => self::date_or_null($data['effective_date'] ?? ''),
            'manager_approval_required' => empty($data['manager_approval_required']) ? 0 : 1,
            'estimated_minutes' => max(0, (float)($data['estimated_minutes'] ?? 0)),
            'costing_hourly_rate' => max(0, (float)($data['costing_hourly_rate'] ?? 0)),
            'consumable_cost' => max(0, (float)($data['consumable_cost'] ?? 0)),
            'packaging_cost' => max(0, (float)($data['packaging_cost'] ?? 0)),
            'other_cost' => max(0, (float)($data['other_cost'] ?? 0)),
            'included_parent_product_id' => absint($data['included_parent_product_id'] ?? 0),
            'updated_by' => get_current_user_id(),
            'updated_at' => $now,
        ];
        if ($id) {
            $existing = self::product($id);
            if (!$existing) { return new WP_Error('production_product_missing', 'Production product not found.'); }
            $row['version_number'] = ((int)$existing['version_number']) + 1;
            $ok = $wpdb->update($t['products'], $row, ['id' => $id]);
            if ($ok === false) { return new WP_Error('production_product_save', 'The production product could not be updated.'); }
        } else {
            $row['version_number'] = 1;
            $row['created_by'] = get_current_user_id();
            $row['created_at'] = $now;
            $ok = $wpdb->insert($t['products'], $row);
            if (!$ok) { return new WP_Error('production_product_save', 'The production product could not be created.'); }
            $id = (int)$wpdb->insert_id;
        }
        self::save_product_materials($id, $data['materials'] ?? []);
        self::record_version($id);
        return $id;
    }

    public static function materials(): array {
        global $wpdb; $t = self::tables();
        return $wpdb->get_results("SELECT * FROM {$t['materials']} ORDER BY active DESC, material_name ASC", ARRAY_A) ?: [];
    }

    public static function save_material(array $data): int|WP_Error {
        global $wpdb; $t = self::tables(); $now = current_time('mysql');
        $id = absint($data['material_id'] ?? 0);
        $name = sanitize_text_field($data['material_name'] ?? '');
        if ($name === '') { return new WP_Error('production_material_name', 'Enter a material name.'); }
        $code = strtoupper(sanitize_key(str_replace(' ', '-', (string)($data['material_code'] ?? ''))));
        if ($code === '') { $code = strtoupper(sanitize_key(wp_unique_id('MAT-'))); }
        $row = [
            'material_name' => $name,
            'material_code' => $code,
            'unit' => sanitize_text_field($data['unit'] ?? 'unit'),
            'unit_cost' => max(0, (float)($data['unit_cost'] ?? 0)),
            'active' => empty($data['active']) ? 0 : 1,
            'notes' => sanitize_textarea_field($data['notes'] ?? ''),
            'updated_by' => get_current_user_id(), 'updated_at' => $now,
        ];
        if ($id) { $ok = $wpdb->update($t['materials'], $row, ['id'=>$id]); }
        else { $row['created_by']=get_current_user_id(); $row['created_at']=$now; $ok=$wpdb->insert($t['materials'],$row); $id=(int)$wpdb->insert_id; }
        return $ok === false ? new WP_Error('production_material_save','The material could not be saved.') : $id;
    }

    public static function compensation_profiles(): array {
        global $wpdb; $t = self::tables();
        return $wpdb->get_results("SELECT * FROM {$t['compensation_profiles']} ORDER BY active DESC, user_id ASC", ARRAY_A) ?: [];
    }

    public static function compensation_profile(int $user_id): ?array {
        global $wpdb; $t=self::tables();
        $r=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['compensation_profiles']} WHERE user_id=%d",$user_id),ARRAY_A);
        return $r ?: null;
    }

    public static function save_compensation_profile(array $data): int|WP_Error {
        global $wpdb; $t=self::tables(); $now=current_time('mysql');
        $user_id=absint($data['user_id']??0);
        if (!$user_id || !get_userdata($user_id)) { return new WP_Error('production_profile_user','Choose a valid glassblower.'); }
        $existing=self::compensation_profile($user_id);
        $row=[
            'user_id'=>$user_id,
            'hourly_rate'=>max(0,(float)($data['hourly_rate']??0)),
            'piecework_eligible'=>empty($data['piecework_eligible'])?0:1,
            'active'=>empty($data['active'])?0:1,
            'effective_date'=>self::date_or_null($data['effective_date']??''),
            'notes'=>sanitize_textarea_field($data['notes']??''),
            'updated_by'=>get_current_user_id(),'updated_at'=>$now,
        ];
        if($existing){$ok=$wpdb->update($t['compensation_profiles'],$row,['id'=>(int)$existing['id']]);$id=(int)$existing['id'];}
        else{$row['created_by']=get_current_user_id();$row['created_at']=$now;$ok=$wpdb->insert($t['compensation_profiles'],$row);$id=(int)$wpdb->insert_id;}
        return $ok===false?new WP_Error('production_profile_save','The compensation profile could not be saved.'):$id;
    }

    public static function product_materials(int $product_id): array {
        global $wpdb; $t=self::tables();
        return $wpdb->get_results($wpdb->prepare("SELECT pm.*,m.material_name,m.material_code,m.unit FROM {$t['product_materials']} pm LEFT JOIN {$t['materials']} m ON m.id=pm.material_id WHERE pm.product_id=%d ORDER BY pm.sort_order,pm.id",$product_id),ARRAY_A)?:[];
    }

    private static function save_product_materials(int $product_id, $rows): void {
        global $wpdb; $t=self::tables();
        $wpdb->delete($t['product_materials'],['product_id'=>$product_id]);
        if (!is_array($rows)) { return; }
        $order=0;
        foreach($rows as $row){
            $material_id=absint($row['material_id']??0); if(!$material_id)continue;
            $material=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['materials']} WHERE id=%d",$material_id),ARRAY_A); if(!$material)continue;
            $qty=max(0,(float)($row['quantity']??0)); $waste=max(0,(float)($row['waste_percent']??0));
            $unit_cost=(float)$material['unit_cost']; $calc=round($qty*$unit_cost*(1+($waste/100)),4);
            $wpdb->insert($t['product_materials'],[
                'product_id'=>$product_id,'material_id'=>$material_id,'quantity'=>$qty,'waste_percent'=>$waste,
                'unit_cost_snapshot'=>$unit_cost,'calculated_cost'=>$calc,'notes'=>sanitize_text_field($row['notes']??''),'sort_order'=>$order++,
            ]);
        }
    }

    public static function cost_summary(array $product): array {
        $materials=0.0;
        foreach(($product['materials']??[]) as $m){$materials+=(float)$m['calculated_cost'];}
        $labor=0.0;
        if(in_array($product['compensation_method'],['hourly','either'],true)){$labor=((float)$product['estimated_minutes']/60)*(float)$product['costing_hourly_rate'];}
        elseif($product['compensation_method']==='piecework'){$labor=(float)$product['piecework_rate'];}
        return [
            'materials'=>round($materials,2),'labor'=>round($labor,2),'consumables'=>(float)$product['consumable_cost'],
            'packaging'=>(float)$product['packaging_cost'],'other'=>(float)$product['other_cost'],
            'total'=>round($materials+$labor+(float)$product['consumable_cost']+(float)$product['packaging_cost']+(float)$product['other_cost'],2),
        ];
    }

    public static function snapshot(int $product_id): array {
        $p=self::product($product_id); if(!$p)return [];
        $p['cost_summary']=self::cost_summary($p);
        return $p;
    }

    private static function record_version(int $product_id): void {
        global $wpdb; $t=self::tables(); $snapshot=self::snapshot($product_id); if(!$snapshot)return;
        $wpdb->replace($t['versions'],[
            'product_id'=>$product_id,'version_number'=>(int)$snapshot['version_number'],
            'snapshot_json'=>wp_json_encode($snapshot),'effective_from'=>current_time('mysql'),
            'created_by'=>get_current_user_id(),'created_at'=>current_time('mysql'),
        ]);
    }

    public static function categories(): array {
        global $wpdb; $t=self::tables();
        return array_values(array_filter(array_map('strval',$wpdb->get_col("SELECT DISTINCT category FROM {$t['products']} WHERE category<>'' ORDER BY category"))));
    }

    public static function glass_workers(): array {
        return get_users(['orderby'=>'display_name','order'=>'ASC','fields'=>'all','role__in'=>[Elev8_OS_Access_Service::ROLE_GLASS_MANAGER,Elev8_OS_Access_Service::ROLE_GLASS_BLOWER,'administrator']]);
    }

    private static function seed_initial_compensation_profiles(): void {
        $names=(array)apply_filters('elev8_os_initial_glassblower_compensation_names',['Nick','Adam']);
        foreach($names as $name){
            $matches=get_users(['search'=>'*'.sanitize_text_field($name).'*','search_columns'=>['display_name','user_login'],'number'=>5]);
            $exact=[];
            foreach($matches as $u){if(strcasecmp(trim((string)$u->display_name),trim((string)$name))===0||strcasecmp(trim((string)$u->first_name),trim((string)$name))===0)$exact[]=$u;}
            if(count($exact)!==1)continue;
            $user=$exact[0]; if(self::compensation_profile((int)$user->ID))continue;
            self::save_compensation_profile(['user_id'=>$user->ID,'hourly_rate'=>18,'piecework_eligible'=>1,'active'=>1,'effective_date'=>current_time('Y-m-d'),'notes'=>'Initial configurable rate created during Production Catalog setup.']);
        }
    }


    public static function migration_records(): array {
        $path = ELEV8_OS_DIR . 'assets/data/glass-production-catalog-migration.json';
        if (!is_readable($path)) { return []; }
        $payload = json_decode((string)file_get_contents($path), true);
        return is_array($payload['records'] ?? null) ? $payload['records'] : [];
    }

    public static function import_migration_records(array $source_columns, bool $update_existing = false): array {
        global $wpdb;
        $selected = array_values(array_unique(array_filter(array_map('sanitize_text_field', $source_columns))));
        $records = self::migration_records();
        $summary = ['created'=>0,'updated'=>0,'skipped'=>0,'errors'=>[]];
        $t = self::tables();
        foreach ($records as $record) {
            $source_column = sanitize_text_field($record['source_column'] ?? '');
            if (!$source_column || !in_array($source_column, $selected, true)) { continue; }
            if (($record['import_status'] ?? '') === 'Skip') { $summary['skipped']++; continue; }
            $code = sanitize_text_field($record['product_code'] ?? '');
            $existing_id = $code ? absint($wpdb->get_var($wpdb->prepare("SELECT id FROM {$t['products']} WHERE product_code=%s", strtoupper(sanitize_key(str_replace(' ','-',$code)))))) : 0;
            if ($existing_id && !$update_existing) { $summary['skipped']++; continue; }
            $payload = [
                'product_id'=>$existing_id,
                'product_name'=>$record['catalog_name'] ?? '',
                'product_code'=>$code,
                'category'=>$record['family'] ?? '',
                'department'=>'Glass Operations',
                'description'=>$record['instructions'] ?? '',
                'search_aliases'=>$record['search_aliases'] ?? '',
                'source_family'=>$record['family'] ?? '',
                'source_subtype'=>$record['subtype'] ?? '',
                'source_variant'=>$record['variant'] ?? '',
                'compensation_method'=>$record['compensation_method'] ?? 'piecework',
                'piecework_rate'=>$record['blower_pay'] ?? 0,
                'piecework_unit'=>$record['piecework_unit'] ?? 'piece',
                'estimated_minutes'=>$record['estimated_minutes'] ?? 0,
                'actual_retail'=>$record['actual_retail'] ?? 0,
                'dist_profit_at_retail'=>$record['dist_profit_at_retail'] ?? 0,
                'dist_additional_cost'=>$record['dist_additional_cost'] ?? 0,
                'suggested_retail'=>$record['suggested_retail'] ?? 0,
                'dist_profit_wholesale'=>$record['dist_profit_wholesale'] ?? 0,
                'premier_profit'=>$record['premier_profit'] ?? 0,
                'actual_wholesale'=>$record['actual_wholesale'] ?? 0,
                'suggested_wholesale'=>$record['suggested_wholesale'] ?? 0,
                'sold_to_distributor_at'=>$record['sold_to_distributor_at'] ?? 0,
                'source_material_cost'=>$record['material_cost'] ?? 0,
                'source_total_cost'=>$record['total_cost'] ?? 0,
                'instructions'=>$record['instructions'] ?? '',
                'training_video_url'=>$record['video_url'] ?? '',
                'source_sheet'=>$record['source_sheet'] ?? 'Production Information',
                'source_column'=>$source_column,
                'alternate_source_columns'=>$record['alternate_source_columns'] ?? '',
                'alternate_pay_tiers_json'=>$record['alternate_pay_tiers_json'] ?? '[]',
                'effective_date'=>current_time('Y-m-d'),
                'manager_approval_required'=>1,
                'costing_hourly_rate'=>18,
                'active'=>1,
            ];
            $result = self::save_product($payload);
            if (is_wp_error($result)) { $summary['errors'][] = ($record['catalog_name'] ?? $source_column) . ': ' . $result->get_error_message(); }
            elseif ($existing_id) { $summary['updated']++; }
            else { $summary['created']++; }
        }
        return $summary;
    }


    /**
     * Return normalized workbook product codes already present in the authoritative catalog.
     *
     * @param array<int,string> $codes Workbook product codes.
     * @return array<string,bool> Normalized code lookup.
     */
    public static function imported_product_codes(array $codes): array {
        global $wpdb;
        $table = self::tables()['products'];
        $normalized = [];
        foreach ($codes as $code) {
            $value = strtoupper(sanitize_key(str_replace(' ', '-', (string) $code)));
            if ($value !== '') {
                $normalized[$value] = true;
            }
        }
        if (!$normalized) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($normalized), '%s'));
        $sql = $wpdb->prepare(
            "SELECT product_code FROM {$table} WHERE product_code IN ({$placeholders})",
            array_keys($normalized)
        );
        $found = $wpdb->get_col($sql);
        $lookup = [];
        foreach ($found as $code) {
            $lookup[(string) $code] = true;
        }
        return $lookup;
    }

    public static function import_wizard_item(array $record, bool $update_existing = false) {
        global $wpdb; $t=self::tables();
        $code=sanitize_text_field($record['product_code']??'');
        $normalized=strtoupper(sanitize_key(str_replace(' ','-',$code)));
        $existing_id=$normalized?absint($wpdb->get_var($wpdb->prepare("SELECT id FROM {$t['products']} WHERE product_code=%s",$normalized))):0;
        if($existing_id && !$update_existing)return 'skipped';
        $payload=[
            'product_id'=>$existing_id,'product_name'=>$record['catalog_name']??'','product_code'=>$code,
            'category'=>$record['family']??'','department'=>'Glass Operations','description'=>$record['instructions']??'',
            'search_aliases'=>$record['search_aliases']??'','source_family'=>$record['family']??'',
            'source_subtype'=>$record['subtype']??'','source_variant'=>$record['variant']??'',
            'compensation_method'=>$record['compensation_method']??'piecework','piecework_rate'=>$record['blower_pay']??0,
            'piecework_unit'=>$record['piecework_unit']??'piece','estimated_minutes'=>$record['estimated_minutes']??0,
            'actual_retail'=>$record['actual_retail']??0,'dist_profit_at_retail'=>$record['dist_profit_at_retail']??0,
            'dist_additional_cost'=>$record['dist_additional_cost']??0,'suggested_retail'=>$record['suggested_retail']??0,
            'dist_profit_wholesale'=>$record['dist_profit_wholesale']??0,'premier_profit'=>$record['premier_profit']??0,
            'actual_wholesale'=>$record['actual_wholesale']??0,'suggested_wholesale'=>$record['suggested_wholesale']??0,
            'sold_to_distributor_at'=>$record['sold_to_distributor_at']??0,'source_material_cost'=>$record['material_cost']??0,
            'source_total_cost'=>$record['total_cost']??0,'instructions'=>$record['instructions']??'',
            'source_sheet'=>$record['source_sheet']??'Production Information','source_column'=>$record['source_column']??'',
            'effective_date'=>current_time('Y-m-d'),'manager_approval_required'=>1,'costing_hourly_rate'=>18,'active'=>1,
        ];
        $result=self::save_product($payload);
        if(is_wp_error($result))return $result;
        return $existing_id?'updated':'created';
    }


    public static function lifecycle_statuses(): array {
        return ['active'=>'Active','draft'=>'Draft','archived'=>'Archived'];
    }

    public static function ignored_sources(): array {
        $value = get_option(self::OPTION_IGNORED_SOURCES, []);
        return is_array($value) ? array_values(array_unique(array_filter(array_map('sanitize_text_field', $value)))) : [];
    }

    public static function is_source_ignored(string $source_code): bool {
        return in_array(sanitize_text_field($source_code), self::ignored_sources(), true);
    }

    public static function set_source_ignored(string $source_code, bool $ignored = true): void {
        $source_code = sanitize_text_field($source_code);
        if ($source_code === '') { return; }
        $items = self::ignored_sources();
        if ($ignored && !in_array($source_code, $items, true)) { $items[] = $source_code; }
        if (!$ignored) { $items = array_values(array_diff($items, [$source_code])); }
        update_option(self::OPTION_IGNORED_SOURCES, $items, false);
    }

    public static function bulk_update_products(array $ids, string $action, string $value = ''): array|WP_Error {
        global $wpdb; $t=self::tables();
        $ids=array_values(array_unique(array_filter(array_map('absint',$ids))));
        if(!$ids){return new WP_Error('production_bulk_empty','Select at least one production product.');}
        $placeholders=implode(',',array_fill(0,count($ids),'%d'));
        $now=current_time('mysql'); $user=get_current_user_id();
        if($action==='status'){
            $status=sanitize_key($value);
            if(!in_array($status,['active','draft','archived'],true)){return new WP_Error('production_bulk_status','Choose a valid lifecycle status.');}
            $sql=$wpdb->prepare("UPDATE {$t['products']} SET lifecycle_status=%s,active=%d,updated_by=%d,updated_at=%s WHERE id IN ($placeholders)",array_merge([$status,$status==='active'?1:0,$user,$now],$ids));
        }elseif($action==='category'){
            $category=sanitize_text_field($value);
            if($category===''){return new WP_Error('production_bulk_category','Enter a destination family/category.');}
            $sql=$wpdb->prepare("UPDATE {$t['products']} SET category=%s,source_family=%s,updated_by=%d,updated_at=%s WHERE id IN ($placeholders)",array_merge([$category,$category,$user,$now],$ids));
        }else{return new WP_Error('production_bulk_action','Choose a valid bulk action.');}
        $result=$wpdb->query($sql);
        if($result===false){return new WP_Error('production_bulk_save','The selected production products could not be updated.');}
        foreach($ids as $id){self::record_version($id);}
        return ['updated'=>(int)$result];
    }

    public static function merge_products(int $target_id, array $source_ids): array|WP_Error {
        global $wpdb; $t=self::tables();
        $target=self::product($target_id);
        if(!$target){return new WP_Error('production_merge_target','Choose a valid target product.');}
        $source_ids=array_values(array_unique(array_filter(array_map('absint',$source_ids))));
        $source_ids=array_values(array_diff($source_ids,[$target_id]));
        if(!$source_ids){return new WP_Error('production_merge_sources','Choose at least one duplicate product to merge.');}
        $now=current_time('mysql');$user=get_current_user_id();$updated=0;
        foreach($source_ids as $id){
            if(!self::product($id)){continue;}
            $ok=$wpdb->update($t['products'],[
                'lifecycle_status'=>'archived','active'=>0,'merged_into_product_id'=>$target_id,
                'updated_by'=>$user,'updated_at'=>$now
            ],['id'=>$id]);
            if($ok!==false){$updated++;self::record_version($id);}
        }
        return ['merged'=>$updated,'target_id'=>$target_id];
    }

    public static function version_history(int $product_id): array {
        global $wpdb; $t=self::tables();
        return $wpdb->get_results($wpdb->prepare("SELECT version_number,effective_from,created_by,created_at FROM {$t['versions']} WHERE product_id=%d ORDER BY version_number DESC",$product_id),ARRAY_A) ?: [];
    }

    public static function duplicate_candidates(): array {
        global $wpdb; $t=self::tables();
        $rows=$wpdb->get_results("SELECT LOWER(TRIM(product_name)) normalized_name,COUNT(*) duplicate_count,GROUP_CONCAT(id ORDER BY id) product_ids,MIN(product_name) product_name FROM {$t['products']} WHERE lifecycle_status<>'archived' GROUP BY LOWER(TRIM(product_name)) HAVING COUNT(*)>1 ORDER BY duplicate_count DESC,product_name ASC",ARRAY_A);
        return $rows ?: [];
    }

    private static function date_or_null($value): ?string {
        $value=sanitize_text_field((string)$value); if($value==='')return null;
        $dt=DateTime::createFromFormat('Y-m-d',$value); return $dt&&$dt->format('Y-m-d')===$value?$value:null;
    }
}

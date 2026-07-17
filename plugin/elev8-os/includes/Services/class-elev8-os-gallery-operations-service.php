<?php
if (!defined('ABSPATH')) { exit; }

/** Canonical gallery location, placement, rack, storage, and movement history service. */
final class Elev8_OS_Gallery_Operations_Service {
    private const DB_VERSION = '1.7.0';
    private const DB_OPTION = 'elev8_os_gallery_operations_db_version';

    public static function init(): void {
        add_action('init', [__CLASS__, 'maybe_upgrade'], 6);
        add_action('elev8_os_asset_saved', [__CLASS__, 'sync_asset'], 10, 3);
        add_action('elev8_os_asset_deleted', [__CLASS__, 'remove_asset'], 10, 2);
    }
    public static function activate(): void { self::install(); }
    public static function maybe_upgrade(): void { if ((string)get_option(self::DB_OPTION, '') !== self::DB_VERSION) self::install(); }

    private static function install(): void {
        global $wpdb; require_once ABSPATH.'wp-admin/includes/upgrade.php';
        $c=$wpdb->get_charset_collate(); $z=self::zones_table(); $p=self::placements_table(); $h=self::history_table();
        dbDelta("CREATE TABLE {$z} (id bigint(20) unsigned NOT NULL AUTO_INCREMENT, name varchar(160) NOT NULL, slug varchar(160) NOT NULL, zone_type varchar(40) NOT NULL DEFAULT 'wall', capacity int(10) unsigned NOT NULL DEFAULT 0, sort_order int(10) unsigned NOT NULL DEFAULT 0, active tinyint(1) unsigned NOT NULL DEFAULT 1, notes text NOT NULL, rack_number int(10) unsigned NOT NULL DEFAULT 0, board_number int(10) unsigned NOT NULL DEFAULT 0, side_label varchar(10) NOT NULL DEFAULT '', assigned_artist_user_id bigint(20) unsigned NOT NULL DEFAULT 0, board_status varchar(30) NOT NULL DEFAULT 'available', created_at datetime NOT NULL, updated_at datetime NOT NULL, PRIMARY KEY(id), UNIQUE KEY slug(slug), KEY active_sort(active,sort_order), KEY rack_board(rack_number,board_number), KEY assigned_artist(assigned_artist_user_id)) {$c};");
        dbDelta("CREATE TABLE {$p} (id bigint(20) unsigned NOT NULL AUTO_INCREMENT, asset_id bigint(20) unsigned NOT NULL, zone_id bigint(20) unsigned NULL, placement_status varchar(30) NOT NULL DEFAULT 'displayed', position_label varchar(120) NOT NULL DEFAULT '', placed_at datetime NULL, removed_at datetime NULL, updated_at datetime NOT NULL, PRIMARY KEY(id), UNIQUE KEY asset_id(asset_id), KEY zone_id(zone_id), KEY placement_status(placement_status)) {$c};");
        dbDelta("CREATE TABLE {$h} (id bigint(20) unsigned NOT NULL AUTO_INCREMENT, asset_id bigint(20) unsigned NOT NULL, event_type varchar(40) NOT NULL, from_zone_id bigint(20) unsigned NULL, to_zone_id bigint(20) unsigned NULL, actor_user_id bigint(20) unsigned NOT NULL DEFAULT 0, note text NOT NULL, created_at datetime NOT NULL, PRIMARY KEY(id), KEY asset_created(asset_id,created_at), KEY event_type(event_type)) {$c};");
        if ((int)$wpdb->get_var("SELECT COUNT(*) FROM {$z}")===0) {
            foreach ([['Front Window','window'],['West Wall','wall'],['East Wall','wall'],['Center Display','display'],['Glass Case 1','case'],['Glass Case 2','case'],['Jewelry','display'],['Classroom','room'],['Storage','storage']] as $i=>$v) self::save_zone(['name'=>$v[0],'zone_type'=>$v[1],'sort_order'=>$i+1,'active'=>1]);
        }
        // Repair canonical Artist Portal board locations created by earlier releases.
        // Old A/B side records stay retired, while numbered board records are always active and selectable.
        $wpdb->query("UPDATE {$z} SET active=1, side_label='', board_status=CASE WHEN board_status='' THEN 'available' ELSE board_status END, capacity=0, updated_at='".esc_sql(current_time('mysql'))."' WHERE zone_type='artist_portal_rack' AND rack_number>0 AND board_number>0 AND (side_label='' OR side_label IS NULL)");
        update_option(self::DB_OPTION,self::DB_VERSION,false);
    }
    public static function zones_table(): string { global $wpdb; return $wpdb->prefix.'elev8_os_gallery_zones'; }
    public static function placements_table(): string { global $wpdb; return $wpdb->prefix.'elev8_os_gallery_placements'; }
    public static function history_table(): string { global $wpdb; return $wpdb->prefix.'elev8_os_gallery_history'; }

    public static function save_zone(array $data) {
        global $wpdb; $id=absint($data['id']??0); $name=sanitize_text_field((string)($data['name']??'')); if($name==='') return new WP_Error('zone_name','Zone name is required.');
        $slug=sanitize_title((string)($data['slug']??$name)); $now=current_time('mysql');
        $status=sanitize_key((string)($data['board_status']??'available')); if(!in_array($status,['available','full','reserved','maintenance'],true)) $status='available';
        $allowed_types=['wall','window','case','shelf','table','display','room','storage','artist_portal_rack','other'];
        $zone_type=sanitize_key((string)($data['zone_type']??'wall')); if(!in_array($zone_type,$allowed_types,true)) $zone_type='other';
        $row=['name'=>$name,'slug'=>$slug,'zone_type'=>$zone_type,'capacity'=>0,'sort_order'=>absint($data['sort_order']??0),'active'=>array_key_exists('active',$data)?(empty($data['active'])?0:1):1,'notes'=>sanitize_textarea_field((string)($data['notes']??'')),'rack_number'=>absint($data['rack_number']??0),'board_number'=>absint($data['board_number']??0),'side_label'=>'','assigned_artist_user_id'=>absint($data['assigned_artist_user_id']??0),'board_status'=>$status,'updated_at'=>$now];
        // A canonical slug identifies one physical location. Re-saving the same rack/board updates and reactivates it.
        if(!$id) $id=(int)$wpdb->get_var($wpdb->prepare('SELECT id FROM '.self::zones_table().' WHERE slug=%s',$slug));
        if($id){
            $ok=$wpdb->update(self::zones_table(),$row,['id'=>$id]);
            if($ok===false) return new WP_Error('zone_save','Zone could not be updated: '.$wpdb->last_error);
        } else {
            $row['created_at']=$now;
            $ok=$wpdb->insert(self::zones_table(),$row);
            if($ok===false) return new WP_Error('zone_save','Zone could not be created: '.$wpdb->last_error);
            $id=(int)$wpdb->insert_id;
        }
        // A numbered Artist Portal board is a normal active gallery location. Force canonical values and verify persistence.
        if($zone_type==='artist_portal_rack'){
            $wpdb->update(self::zones_table(),[
                'active'=>1,'capacity'=>0,'side_label'=>'','board_status'=>'available',
                'rack_number'=>absint($data['rack_number']??0),'board_number'=>absint($data['board_number']??0),
                'updated_at'=>$now,
            ],['id'=>$id]);
        }
        $saved=$wpdb->get_row($wpdb->prepare('SELECT id,active,zone_type,rack_number,board_number FROM '.self::zones_table().' WHERE id=%d',$id),ARRAY_A);
        if(!$saved || empty($saved['active'])) return new WP_Error('zone_verify','The location was not saved as active. Please try again.');
        return $id;
    }

    /** Creates one numbered position per board. Existing board positions are updated, not duplicated. */
    public static function create_artist_portal_rack(int $rack_number,int $board_count=40,int $capacity_per_board=1) {
        global $wpdb; if($rack_number<1) return new WP_Error('rack','Rack number is required.');
        $board_count=max(1,min(100,$board_count)); $capacity_per_board=max(1,min(20,$capacity_per_board)); $ready=0;
        // Retire the earlier Side A / Side B rack positions without deleting history.
        $wpdb->query($wpdb->prepare("UPDATE ".self::zones_table()." SET active=0, updated_at=%s WHERE zone_type='artist_portal_rack' AND rack_number=%d AND side_label<>''",current_time('mysql'),$rack_number));
        foreach(range(1,$board_count) as $board){
            $slug=sprintf('artist-portal-rack-%d-board-%d',$rack_number,$board);
            $existing=(int)$wpdb->get_var($wpdb->prepare('SELECT id FROM '.self::zones_table().' WHERE slug=%s',$slug));
            $result=self::save_zone(['id'=>$existing,'name'=>sprintf('Artist Portal Rack %d — Board %d',$rack_number,$board),'slug'=>$slug,'zone_type'=>'artist_portal_rack','capacity'=>$capacity_per_board,'sort_order'=>9000+($rack_number*1000)+$board,'active'=>1,'rack_number'=>$rack_number,'board_number'=>$board,'board_status'=>'available']);
            if(is_wp_error($result)) return $result; $ready++;
        }
        return $ready;
    }


    /** Save one Artist Portal board directly by physical rack and board number. */
    public static function save_artist_portal_board(int $rack_number, int $board_number) {
        global $wpdb;
        if ($rack_number < 1 || $board_number < 1) {
            return new WP_Error('portal_board_required', 'Rack number and board number are required.');
        }
        $table = self::zones_table();
        $name = sprintf('Artist Portal Rack %d — Board %d', $rack_number, $board_number);
        $slug = sprintf('artist-portal-rack-%d-board-%d', $rack_number, $board_number);
        $now = current_time('mysql');
        $id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE (rack_number=%d AND board_number=%d AND zone_type='artist_portal_rack') OR slug=%s ORDER BY id ASC LIMIT 1",
            $rack_number, $board_number, $slug
        ));
        $row = [
            'name' => $name, 'slug' => $slug, 'zone_type' => 'artist_portal_rack',
            'capacity' => 0, 'sort_order' => 9000 + ($rack_number * 1000) + $board_number,
            'active' => 1, 'notes' => '', 'rack_number' => $rack_number,
            'board_number' => $board_number, 'side_label' => '',
            'assigned_artist_user_id' => 0, 'board_status' => 'available',
            'updated_at' => $now,
        ];
        if ($id) {
            $ok = $wpdb->update($table, $row, ['id' => $id]);
        } else {
            $row['created_at'] = $now;
            $ok = $wpdb->insert($table, $row);
            $id = (int) $wpdb->insert_id;
        }
        if ($ok === false || !$id) {
            return new WP_Error('portal_board_save', 'Artist Portal board could not be saved: ' . $wpdb->last_error);
        }
        $saved = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d", $id), ARRAY_A);
        if (!$saved || (int)$saved['active'] !== 1 || $saved['zone_type'] !== 'artist_portal_rack') {
            return new WP_Error('portal_board_verify', 'Artist Portal board was written but could not be verified as an active location.');
        }
        return $id;
    }

    /** All location rows, including inactive records, for owner diagnostics. */
    public static function get_location_diagnostics(): array {
        global $wpdb;
        $rows = $wpdb->get_results('SELECT id,name,slug,zone_type,active,rack_number,board_number,board_status FROM '.self::zones_table().' ORDER BY id DESC LIMIT 100', ARRAY_A);
        return is_array($rows) ? $rows : [];
    }

    /** Canonical physical locations. Retired A/B rack records are the only rows excluded. */
    public static function get_zones(bool $active_only=true): array {
        global $wpdb;
        $table=self::zones_table();
        $where="WHERE NOT (zone_type='artist_portal_rack' AND COALESCE(side_label,'')<>'')";
        $rows=$wpdb->get_results("SELECT * FROM {$table} {$where} ORDER BY CASE WHEN zone_type='storage' THEN 2 ELSE 1 END, sort_order, name",ARRAY_A);
        return is_array($rows)?$rows:[];
    }
    /** Every canonical physical location is selectable, regardless of a stale legacy active flag. */
    public static function get_selectable_zones(): array {
        global $wpdb;
        $table=self::zones_table();
        // Repair all canonical rows before reading. This covers standard zones and numbered rack boards.
        $wpdb->query("UPDATE {$table} SET active=1 WHERE NOT (zone_type='artist_portal_rack' AND COALESCE(side_label,'')<>'')");
        $wpdb->query("UPDATE {$table} SET capacity=0, side_label='', board_status=CASE WHEN board_status='' THEN 'available' ELSE board_status END WHERE zone_type='artist_portal_rack' AND rack_number>0 AND board_number>0 AND COALESCE(side_label,'')=''");
        $rows=$wpdb->get_results("SELECT * FROM {$table} WHERE NOT (zone_type='artist_portal_rack' AND COALESCE(side_label,'')<>'') ORDER BY CASE WHEN zone_type='storage' THEN 2 ELSE 1 END, sort_order, name",ARRAY_A);
        return is_array($rows)?$rows:[];
    }
    public static function get_zone(int $id): ?array { global $wpdb; $r=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::zones_table().' WHERE id=%d',$id),ARRAY_A); return is_array($r)?$r:null; }
    public static function get_zone_artwork(int $zone_id,int $artist_filter=0): array { global $wpdb; $a=Elev8_OS_Asset_Service::table_name(); $p=self::placements_table(); $where=$artist_filter?$wpdb->prepare(' AND a.owner_user_id=%d',$artist_filter):''; $rows=$wpdb->get_results($wpdb->prepare("SELECT a.*,p.zone_id,p.position_label,p.placement_status,p.placed_at,u.display_name artist_name,u.user_email artist_email FROM {$p} p JOIN {$a} a ON a.id=p.asset_id LEFT JOIN {$wpdb->users} u ON u.ID=a.owner_user_id WHERE p.zone_id=%d AND p.placement_status IN ('displayed','storage') {$where} ORDER BY u.display_name,a.title",$zone_id),ARRAY_A); return is_array($rows)?$rows:[]; }
    public static function get_placement(int $asset_id): ?array { global $wpdb; $r=$wpdb->get_row($wpdb->prepare('SELECT p.*,z.name zone_name,z.zone_type,z.rack_number,z.board_number FROM '.self::placements_table().' p LEFT JOIN '.self::zones_table().' z ON z.id=p.zone_id WHERE p.asset_id=%d',$asset_id),ARRAY_A); return is_array($r)?$r:null; }

    public static function place_asset(int $asset_id,int $zone_id,string $position='',string $note='') {
        global $wpdb; $asset=Elev8_OS_Asset_Service::get($asset_id); if(!$asset) return new WP_Error('asset','Artwork not found.'); $zone=self::get_zone($zone_id); if(!$zone || ((string)($zone['zone_type']??'')==='artist_portal_rack' && (string)($zone['side_label']??'')!=='')) return new WP_Error('zone','Gallery zone not found.');
        if((string)$zone['board_status']==='maintenance') return new WP_Error('zone_status','That board is in maintenance.');
        $assigned=absint($zone['assigned_artist_user_id']??0); if($assigned && $assigned!==absint($asset['owner_user_id']??0)) return new WP_Error('artist_assignment','That board is reserved for another artist.');
        return self::save_placement($asset_id,$zone_id,'displayed',$position,$note);
    }

    private static function save_placement(int $asset_id,int $zone_id,string $status,string $position='',string $note='') {
        global $wpdb; $old=self::get_placement($asset_id); $now=current_time('mysql');
        $row=['asset_id'=>$asset_id,'zone_id'=>$zone_id?:null,'placement_status'=>$status,'position_label'=>sanitize_text_field($position),'placed_at'=>$status==='displayed'?$now:($old['placed_at']??$now),'removed_at'=>$status==='displayed'?null:$now,'updated_at'=>$now];
        $ok=$old?$wpdb->update(self::placements_table(),$row,['asset_id'=>$asset_id]):$wpdb->insert(self::placements_table(),$row);
        if($ok===false) return new WP_Error('placement','Artwork location could not be saved.');
        $event=$status==='displayed'?($old?'moved':'displayed'):$status; self::record($asset_id,$event,absint($old['zone_id']??0),$zone_id,$note); return true;
    }

    public static function remove_from_display(int $asset_id,string $status='removed',string $note='') {
        global $wpdb; $old=self::get_placement($asset_id); if(!$old) return new WP_Error('placement','Artwork does not have a saved location.');
        $status=in_array($status,['removed','sold','archived','reserved','pickup','storage'],true)?$status:'removed';
        if($status==='storage'){
            $storage=(int)$wpdb->get_var("SELECT id FROM ".self::zones_table()." WHERE zone_type='storage' AND NOT (zone_type='artist_portal_rack' AND COALESCE(side_label,'')<>'') ORDER BY sort_order,id LIMIT 1");
            if(!$storage) return new WP_Error('storage','Create an active Storage zone first.');
            return self::save_placement($asset_id,$storage,'storage','',$note?:'Moved to storage.');
        }
        return self::save_placement($asset_id,0,$status,'',$note);
    }

    private static function record(int $asset_id,string $event,int $from,int $to,string $note=''): void { global $wpdb; $wpdb->insert(self::history_table(),['asset_id'=>$asset_id,'event_type'=>sanitize_key($event),'from_zone_id'=>$from?:null,'to_zone_id'=>$to?:null,'actor_user_id'=>get_current_user_id(),'note'=>sanitize_textarea_field($note),'created_at'=>current_time('mysql')]); }
    /** Permanently delete an empty gallery location. Locations containing artwork must be cleared first. */
    public static function delete_zone(int $zone_id) {
        global $wpdb;
        $zone=self::get_zone($zone_id);
        if(!$zone) return new WP_Error('zone_missing','Gallery location not found.');
        $count=(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.self::placements_table().' WHERE zone_id=%d',$zone_id));
        if($count>0) return new WP_Error('zone_in_use','Move or remove the artwork in this location before deleting it.');
        $ok=$wpdb->delete(self::zones_table(),['id'=>$zone_id],['%d']);
        if($ok===false) return new WP_Error('zone_delete','Location could not be deleted: '.$wpdb->last_error);
        return true;
    }

    public static function get_history(int $asset_id,int $limit=30): array { global $wpdb; $r=$wpdb->get_results($wpdb->prepare('SELECT h.*,fz.name from_zone,tz.name to_zone,u.display_name actor FROM '.self::history_table().' h LEFT JOIN '.self::zones_table().' fz ON fz.id=h.from_zone_id LEFT JOIN '.self::zones_table().' tz ON tz.id=h.to_zone_id LEFT JOIN '.$wpdb->users.' u ON u.ID=h.actor_user_id WHERE h.asset_id=%d ORDER BY h.created_at DESC LIMIT %d',$asset_id,max(1,min(100,$limit))),ARRAY_A); return is_array($r)?$r:[]; }

    public static function dashboard(int $artist_filter=0,string $search=''): array {
        global $wpdb; $a=Elev8_OS_Asset_Service::table_name(); $p=self::placements_table(); $z=self::zones_table();
        $summary=$wpdb->get_row("SELECT COUNT(*) total, SUM(status='available') available, SUM(status='reserved') reserved, SUM(status='sold') sold, SUM(status='available' AND location='at_elev8') on_site, COALESCE(SUM(CASE WHEN status='available' THEN price*quantity ELSE 0 END),0) available_value FROM {$a}",ARRAY_A)?:[];
        $zones=$wpdb->get_results("SELECT z.*,COUNT(CASE WHEN p.placement_status='displayed' THEN 1 END) piece_count,COALESCE(SUM(CASE WHEN p.placement_status='displayed' AND a.status='available' THEN a.price ELSE 0 END),0) display_value FROM {$z} z LEFT JOIN {$p} p ON p.zone_id=z.id LEFT JOIN {$a} a ON a.id=p.asset_id WHERE NOT (z.zone_type='artist_portal_rack' AND COALESCE(z.side_label,'')<>'') GROUP BY z.id ORDER BY CASE WHEN z.zone_type='storage' THEN 2 ELSE 1 END,z.sort_order,z.name",ARRAY_A);
        $where=$artist_filter?$wpdb->prepare(' AND a.owner_user_id=%d',$artist_filter):'';
        $unplaced=$wpdb->get_results("SELECT a.*,u.display_name artist_name FROM {$a} a LEFT JOIN {$p} p ON p.asset_id=a.id AND p.placement_status IN ('displayed','storage') LEFT JOIN {$wpdb->users} u ON u.ID=a.owner_user_id WHERE a.status IN ('available','reserved') AND a.location='at_elev8' AND p.id IS NULL {$where} ORDER BY u.display_name,a.title LIMIT 200",ARRAY_A);
        $placed=$wpdb->get_results("SELECT a.*,p.zone_id,p.position_label,p.placement_status,p.placed_at,z.name zone_name,u.display_name artist_name FROM {$p} p JOIN {$a} a ON a.id=p.asset_id LEFT JOIN {$z} z ON z.id=p.zone_id LEFT JOIN {$wpdb->users} u ON u.ID=a.owner_user_id WHERE p.placement_status='displayed' {$where} ORDER BY z.sort_order,u.display_name,a.title",ARRAY_A);
        $all_where='1=1'; $params=[]; if($artist_filter){$all_where.=' AND a.owner_user_id=%d';$params[]=$artist_filter;} if($search!==''){$like='%'.$wpdb->esc_like($search).'%';$all_where.=' AND (a.title LIKE %s OR u.display_name LIKE %s OR u.user_email LIKE %s)';array_push($params,$like,$like,$like);} 
        $all_sql="SELECT a.*,p.zone_id,p.position_label,p.placement_status,p.placed_at,z.name zone_name,z.zone_type,u.display_name artist_name,u.user_email artist_email FROM {$a} a LEFT JOIN {$p} p ON p.asset_id=a.id LEFT JOIN {$z} z ON z.id=p.zone_id LEFT JOIN {$wpdb->users} u ON u.ID=a.owner_user_id WHERE {$all_where} ORDER BY u.display_name,a.title LIMIT 500";
        $all=$wpdb->get_results($params?$wpdb->prepare($all_sql,$params):$all_sql,ARRAY_A);
        $artists=$wpdb->get_results("SELECT DISTINCT u.ID,u.display_name,u.user_email FROM {$a} a JOIN {$wpdb->users} u ON u.ID=a.owner_user_id ORDER BY u.display_name,u.user_email",ARRAY_A);
        return ['summary'=>$summary,'zones'=>is_array($zones)?$zones:[],'unplaced'=>is_array($unplaced)?$unplaced:[],'placed'=>is_array($placed)?$placed:[],'all'=>is_array($all)?$all:[],'artists'=>is_array($artists)?$artists:[]];
    }

    public static function owner_snapshot(int $owner): array { global $wpdb; $a=Elev8_OS_Asset_Service::table_name(); $p=self::placements_table(); $z=self::zones_table(); $rows=$wpdb->get_results($wpdb->prepare("SELECT a.id,a.title,a.status,a.price,p.placement_status,p.placed_at,z.name zone_name,DATEDIFF(CURDATE(),p.placed_at) days_displayed FROM {$a} a LEFT JOIN {$p} p ON p.asset_id=a.id LEFT JOIN {$z} z ON z.id=p.zone_id WHERE a.owner_user_id=%d ORDER BY a.updated_at DESC",$owner),ARRAY_A); return ['rows'=>is_array($rows)?$rows:[]]; }
    public static function sync_asset(int $asset_id,$asset,$previous): void { if(!is_array($asset)) return; $new=(string)($asset['status']??''); $old=is_array($previous)?(string)($previous['status']??''):''; if($new!==$old && in_array($new,['sold','archived'],true)) self::remove_from_display($asset_id,$new,'Status changed to '.$new.'.'); elseif(!$previous) self::record($asset_id,'created',0,0,'Artwork added to Elev8 OS.'); }
    public static function remove_asset(int $asset_id,$asset): void { global $wpdb; $wpdb->delete(self::placements_table(),['asset_id'=>$asset_id]); }
}

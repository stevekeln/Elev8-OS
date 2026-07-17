<?php
if (!defined('ABSPATH')) { exit; }

/** Canonical gallery location, placement, and movement history service. */
final class Elev8_OS_Gallery_Operations_Service {
    private const DB_VERSION = '1.0.0';
    private const DB_OPTION = 'elev8_os_gallery_operations_db_version';

    public static function init(): void {
        add_action('init', [__CLASS__, 'maybe_upgrade'], 6);
        add_action('elev8_os_asset_saved', [__CLASS__, 'sync_asset'], 10, 3);
        add_action('elev8_os_asset_deleted', [__CLASS__, 'remove_asset'], 10, 2);
    }
    public static function activate(): void { self::install(); }
    public static function maybe_upgrade(): void {
        if ((string)get_option(self::DB_OPTION, '') !== self::DB_VERSION) self::install();
    }
    private static function install(): void {
        global $wpdb; require_once ABSPATH.'wp-admin/includes/upgrade.php';
        $c=$wpdb->get_charset_collate(); $z=self::zones_table(); $p=self::placements_table(); $h=self::history_table();
        dbDelta("CREATE TABLE {$z} (id bigint(20) unsigned NOT NULL AUTO_INCREMENT, name varchar(160) NOT NULL, slug varchar(160) NOT NULL, zone_type varchar(40) NOT NULL DEFAULT 'wall', capacity int(10) unsigned NOT NULL DEFAULT 0, sort_order int(10) unsigned NOT NULL DEFAULT 0, active tinyint(1) unsigned NOT NULL DEFAULT 1, notes text NOT NULL, created_at datetime NOT NULL, updated_at datetime NOT NULL, PRIMARY KEY(id), UNIQUE KEY slug(slug), KEY active_sort(active,sort_order)) {$c};");
        dbDelta("CREATE TABLE {$p} (id bigint(20) unsigned NOT NULL AUTO_INCREMENT, asset_id bigint(20) unsigned NOT NULL, zone_id bigint(20) unsigned NULL, placement_status varchar(30) NOT NULL DEFAULT 'displayed', position_label varchar(120) NOT NULL DEFAULT '', placed_at datetime NULL, removed_at datetime NULL, updated_at datetime NOT NULL, PRIMARY KEY(id), UNIQUE KEY asset_id(asset_id), KEY zone_id(zone_id), KEY placement_status(placement_status)) {$c};");
        dbDelta("CREATE TABLE {$h} (id bigint(20) unsigned NOT NULL AUTO_INCREMENT, asset_id bigint(20) unsigned NOT NULL, event_type varchar(40) NOT NULL, from_zone_id bigint(20) unsigned NULL, to_zone_id bigint(20) unsigned NULL, actor_user_id bigint(20) unsigned NOT NULL DEFAULT 0, note text NOT NULL, created_at datetime NOT NULL, PRIMARY KEY(id), KEY asset_created(asset_id,created_at), KEY event_type(event_type)) {$c};");
        if ((int)$wpdb->get_var("SELECT COUNT(*) FROM {$z}")===0) {
            foreach ([['Front Window','window'],['West Wall','wall'],['East Wall','wall'],['Center Display','display'],['Glass Case 1','case'],['Glass Case 2','case'],['Jewelry','display'],['Classroom','room'],['Storage','storage']] as $i=>$v) self::save_zone(['name'=>$v[0],'zone_type'=>$v[1],'sort_order'=>$i+1]);
        }
        update_option(self::DB_OPTION,self::DB_VERSION,false);
    }
    public static function zones_table(): string { global $wpdb; return $wpdb->prefix.'elev8_os_gallery_zones'; }
    public static function placements_table(): string { global $wpdb; return $wpdb->prefix.'elev8_os_gallery_placements'; }
    public static function history_table(): string { global $wpdb; return $wpdb->prefix.'elev8_os_gallery_history'; }
    public static function save_zone(array $data) {
        global $wpdb; $id=absint($data['id']??0); $name=sanitize_text_field((string)($data['name']??'')); if($name==='') return new WP_Error('zone_name','Zone name is required.');
        $slug=sanitize_title((string)($data['slug']??$name)); $now=current_time('mysql');
        $row=['name'=>$name,'slug'=>$slug,'zone_type'=>sanitize_key((string)($data['zone_type']??'wall')),'capacity'=>absint($data['capacity']??0),'sort_order'=>absint($data['sort_order']??0),'active'=>empty($data['active'])?0:1,'notes'=>sanitize_textarea_field((string)($data['notes']??'')),'updated_at'=>$now];
        if($id){ $ok=$wpdb->update(self::zones_table(),$row,['id'=>$id]); return $ok===false?new WP_Error('zone_save','Zone could not be updated.'):$id; }
        $row['created_at']=$now; $ok=$wpdb->insert(self::zones_table(),$row); return $ok===false?new WP_Error('zone_save','Zone could not be created.'):(int)$wpdb->insert_id;
    }
    public static function get_zones(bool $active_only=true): array { global $wpdb; $where=$active_only?'WHERE active=1':''; $r=$wpdb->get_results("SELECT * FROM ".self::zones_table()." {$where} ORDER BY sort_order,name",ARRAY_A); return is_array($r)?$r:[]; }
    public static function get_zone(int $id): ?array { global $wpdb; $r=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::zones_table().' WHERE id=%d',$id),ARRAY_A); return is_array($r)?$r:null; }
    public static function get_placement(int $asset_id): ?array { global $wpdb; $r=$wpdb->get_row($wpdb->prepare('SELECT p.*,z.name zone_name FROM '.self::placements_table().' p LEFT JOIN '.self::zones_table().' z ON z.id=p.zone_id WHERE p.asset_id=%d',$asset_id),ARRAY_A); return is_array($r)?$r:null; }
    public static function place_asset(int $asset_id, int $zone_id, string $position='', string $note='') {
        global $wpdb; $asset=Elev8_OS_Asset_Service::get($asset_id); if(!$asset) return new WP_Error('asset','Artwork not found.'); $zone=self::get_zone($zone_id); if(!$zone||empty($zone['active'])) return new WP_Error('zone','Gallery zone not found.');
        $old=self::get_placement($asset_id); $now=current_time('mysql'); $row=['asset_id'=>$asset_id,'zone_id'=>$zone_id,'placement_status'=>'displayed','position_label'=>sanitize_text_field($position),'placed_at'=>$now,'removed_at'=>null,'updated_at'=>$now];
        if($old) $ok=$wpdb->update(self::placements_table(),$row,['asset_id'=>$asset_id]); else $ok=$wpdb->insert(self::placements_table(),$row);
        if($ok===false) return new WP_Error('placement','Artwork placement could not be saved.');
        self::record($asset_id,$old?'moved':'displayed',absint($old['zone_id']??0),$zone_id,$note);
        return true;
    }
    public static function remove_from_display(int $asset_id,string $status='removed',string $note=''): bool {
        global $wpdb; $old=self::get_placement($asset_id); if(!$old) return false; $status=in_array($status,['removed','sold','reserved','pickup','storage'],true)?$status:'removed';
        $ok=$wpdb->update(self::placements_table(),['placement_status'=>$status,'removed_at'=>current_time('mysql'),'updated_at'=>current_time('mysql')],['asset_id'=>$asset_id]);
        if($ok!==false) self::record($asset_id,$status,absint($old['zone_id']??0),0,$note); return $ok!==false;
    }
    private static function record(int $asset_id,string $event,int $from,int $to,string $note=''): void { global $wpdb; $wpdb->insert(self::history_table(),['asset_id'=>$asset_id,'event_type'=>sanitize_key($event),'from_zone_id'=>$from?:null,'to_zone_id'=>$to?:null,'actor_user_id'=>get_current_user_id(),'note'=>sanitize_textarea_field($note),'created_at'=>current_time('mysql')]); }
    public static function get_history(int $asset_id,int $limit=30): array { global $wpdb; $r=$wpdb->get_results($wpdb->prepare('SELECT h.*,fz.name from_zone,tz.name to_zone,u.display_name actor FROM '.self::history_table().' h LEFT JOIN '.self::zones_table().' fz ON fz.id=h.from_zone_id LEFT JOIN '.self::zones_table().' tz ON tz.id=h.to_zone_id LEFT JOIN '.$wpdb->users.' u ON u.ID=h.actor_user_id WHERE h.asset_id=%d ORDER BY h.created_at DESC LIMIT %d',$asset_id,max(1,min(100,$limit))),ARRAY_A); return is_array($r)?$r:[]; }
    public static function dashboard(): array {
        global $wpdb; $a=Elev8_OS_Asset_Service::table_name(); $p=self::placements_table(); $z=self::zones_table();
        $summary=$wpdb->get_row("SELECT COUNT(*) total, SUM(status='available') available, SUM(status='reserved') reserved, SUM(status='sold') sold, SUM(status='available' AND location='at_elev8') on_site, COALESCE(SUM(CASE WHEN status='available' THEN price*quantity ELSE 0 END),0) available_value FROM {$a}",ARRAY_A)?:[];
        $zones=$wpdb->get_results("SELECT z.*,COUNT(CASE WHEN p.placement_status='displayed' THEN 1 END) piece_count,COALESCE(SUM(CASE WHEN p.placement_status='displayed' AND a.status='available' THEN a.price ELSE 0 END),0) display_value FROM {$z} z LEFT JOIN {$p} p ON p.zone_id=z.id LEFT JOIN {$a} a ON a.id=p.asset_id WHERE z.active=1 GROUP BY z.id ORDER BY z.sort_order,z.name",ARRAY_A);
        $unplaced=$wpdb->get_results("SELECT a.* FROM {$a} a LEFT JOIN {$p} p ON p.asset_id=a.id AND p.placement_status='displayed' WHERE a.status IN ('available','reserved') AND a.location='at_elev8' AND p.id IS NULL ORDER BY a.updated_at DESC LIMIT 100",ARRAY_A);
        $placed=$wpdb->get_results("SELECT a.*,p.zone_id,p.position_label,p.placed_at,z.name zone_name,u.display_name artist_name FROM {$p} p JOIN {$a} a ON a.id=p.asset_id LEFT JOIN {$z} z ON z.id=p.zone_id LEFT JOIN {$wpdb->users} u ON u.ID=a.owner_user_id WHERE p.placement_status='displayed' ORDER BY z.sort_order,a.title",ARRAY_A);
        return ['summary'=>$summary,'zones'=>is_array($zones)?$zones:[],'unplaced'=>is_array($unplaced)?$unplaced:[],'placed'=>is_array($placed)?$placed:[]];
    }
    public static function owner_snapshot(int $owner): array {
        global $wpdb; $a=Elev8_OS_Asset_Service::table_name(); $p=self::placements_table(); $z=self::zones_table();
        $rows=$wpdb->get_results($wpdb->prepare("SELECT a.id,a.title,a.status,a.price,p.placement_status,p.placed_at,z.name zone_name,DATEDIFF(CURDATE(),p.placed_at) days_displayed FROM {$a} a LEFT JOIN {$p} p ON p.asset_id=a.id LEFT JOIN {$z} z ON z.id=p.zone_id WHERE a.owner_user_id=%d ORDER BY a.updated_at DESC",$owner),ARRAY_A);
        return ['rows'=>is_array($rows)?$rows:[]];
    }
    public static function sync_asset(int $asset_id,$asset,$previous): void {
        if(!is_array($asset)) return; $new=(string)($asset['status']??''); $old=is_array($previous)?(string)($previous['status']??''):'';
        if($new!==$old && in_array($new,['sold','archived'],true)) self::remove_from_display($asset_id,$new,'Status changed to '.$new.'.');
        elseif(!$previous) self::record($asset_id,'created',0,0,'Artwork added to Elev8 OS.');
    }
    public static function remove_asset(int $asset_id,$asset): void { global $wpdb; $wpdb->delete(self::placements_table(),['asset_id'=>$asset_id]); }
}

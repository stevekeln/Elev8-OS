<?php
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Business_Memory_Service {
    const POST_TYPE = 'elev8_memory';
    const META = '_elev8_memory_data';
    const META_STATUS = '_elev8_memory_status';

    public static function init(): void {
        add_action('init', [__CLASS__, 'register_post_type']);
    }

    public static function register_post_type(): void {
        register_post_type(self::POST_TYPE, [
            'labels' => ['name'=>'Business Memory','singular_name'=>'Memory Record'],
            'public'=>false,'show_ui'=>false,'show_in_rest'=>false,'supports'=>['title','editor','author'],
            'capability_type'=>'post','map_meta_cap'=>true,
        ]);
    }

    public static function save(array $raw, array $files, int $user_id) {
        $topic = sanitize_text_field((string)($raw['topic'] ?? ''));
        $summary = sanitize_textarea_field((string)($raw['summary'] ?? ''));
        if ($topic === '' || $summary === '') return new WP_Error('required','Topic and objective summary are required.');
        $date = sanitize_text_field((string)($raw['event_date'] ?? current_time('Y-m-d')));
        $time = sanitize_text_field((string)($raw['event_time'] ?? current_time('H:i')));
        $participants = sanitize_text_field((string)($raw['participants'] ?? ''));
        $location = sanitize_text_field((string)($raw['location'] ?? ''));
        $decisions = sanitize_textarea_field((string)($raw['decisions'] ?? ''));
        $follow_up = sanitize_text_field((string)($raw['follow_up_date'] ?? ''));
        $priority = sanitize_key((string)($raw['priority'] ?? 'normal'));
        if (!in_array($priority,['low','normal','high','critical'],true)) $priority='normal';
        $record_type = sanitize_key((string)($raw['record_type'] ?? 'general'));
        $tags = array_values(array_filter(array_map('sanitize_text_field', preg_split('/\s*,\s*/', (string)($raw['tags'] ?? '')))));
        $actions=[];
        foreach ((array)($raw['action_items'] ?? []) as $row) {
            $task=sanitize_text_field((string)($row['task']??'')); if($task==='') continue;
            $actions[]=['task'=>$task,'owner'=>sanitize_text_field((string)($row['owner']??'')),'due'=>sanitize_text_field((string)($row['due']??'')),'status'=>'open','completed'=>''];
        }
        $title = $date.' — '.$topic;
        $post_id = wp_insert_post(['post_type'=>self::POST_TYPE,'post_status'=>'publish','post_title'=>$title,'post_content'=>$summary,'post_author'=>$user_id], true);
        if (is_wp_error($post_id)) return $post_id;
        $data = compact('date','time','participants','location','topic','summary','decisions','follow_up','priority','record_type','tags','actions');
        $data['created_at']=current_time('mysql');
        $data['attachments']=self::attachments($files,$post_id);
        update_post_meta($post_id,self::META,$data);
        update_post_meta($post_id,self::META_STATUS,'open');
        return $post_id;
    }

    private static function attachments(array $files, int $post_id): array {
        if (empty($files['attachments']['name']) || !is_array($files['attachments']['name'])) return [];
        require_once ABSPATH.'wp-admin/includes/file.php'; require_once ABSPATH.'wp-admin/includes/media.php'; require_once ABSPATH.'wp-admin/includes/image.php';
        $ids=[]; $count=min(8,count($files['attachments']['name']));
        for($i=0;$i<$count;$i++){
            if((int)$files['attachments']['error'][$i]!==UPLOAD_ERR_OK) continue;
            $_FILES['elev8_memory_attachment']=['name'=>$files['attachments']['name'][$i],'type'=>$files['attachments']['type'][$i],'tmp_name'=>$files['attachments']['tmp_name'][$i],'error'=>$files['attachments']['error'][$i],'size'=>$files['attachments']['size'][$i]];
            $id=media_handle_upload('elev8_memory_attachment',$post_id,[],['test_form'=>false]); if(!is_wp_error($id))$ids[]=(int)$id;
        }
        unset($_FILES['elev8_memory_attachment']); return $ids;
    }

    public static function get(int $id): ?array {
        $p=get_post($id); if(!$p||$p->post_type!==self::POST_TYPE)return null;
        return ['post'=>$p,'data'=>(array)get_post_meta($id,self::META,true),'status'=>(string)get_post_meta($id,self::META_STATUS,true)];
    }

    public static function query(array $filters=[]): array {
        $filters=wp_parse_args($filters,['s'=>'','priority'=>'','status'=>'','tag'=>'','participant'=>'','date_from'=>'','date_to'=>'','posts_per_page'=>50]);
        $meta=[];
        if($filters['status'])$meta[]=['key'=>self::META_STATUS,'value'=>sanitize_key($filters['status'])];
        if($filters['priority'])$meta[]=['key'=>self::META,'value'=>'s:8:"priority";s:'.strlen($filters['priority']).':"'.sanitize_key($filters['priority']).'"','compare'=>'LIKE'];
        $date_query=[]; if($filters['date_from']||$filters['date_to'])$date_query[]=['after'=>$filters['date_from']?:null,'before'=>$filters['date_to']?:null,'inclusive'=>true];
        $q=new WP_Query(['post_type'=>self::POST_TYPE,'post_status'=>'publish','posts_per_page'=>(int)$filters['posts_per_page'],'s'=>sanitize_text_field((string)$filters['s']),'meta_query'=>$meta,'date_query'=>$date_query,'orderby'=>'date','order'=>'DESC']);
        $items=[];
        foreach($q->posts as $p){$r=self::get($p->ID);$d=$r['data'];
            if($filters['tag']&&!in_array(sanitize_text_field($filters['tag']),(array)($d['tags']??[]),true))continue;
            if($filters['participant']&&stripos((string)($d['participants']??''),(string)$filters['participant'])===false)continue;
            $items[]=$r;
        }
        return $items;
    }

    public static function set_status(int $id,string $status): bool {
        if(!in_array($status,['open','monitoring','resolved','archived'],true))return false;
        return (bool)update_post_meta($id,self::META_STATUS,$status);
    }

    public static function intelligence(): array {
        $items=self::query(['posts_per_page'=>200]); $today=current_time('Y-m-d'); $open_actions=[];$terms=[];$risks=[];$opps=[];
        foreach($items as $r){$d=$r['data'];
            foreach((array)($d['actions']??[]) as $a){if(($a['status']??'open')!=='completed')$open_actions[]=['record'=>$r['post']->ID]+$a;}
            foreach((array)($d['tags']??[]) as $tag){$k=strtolower($tag);$terms[$k]=($terms[$k]??0)+1;}
            if(in_array($d['priority']??'normal',['high','critical'],true)&&$r['status']!=='resolved')$risks[]=$r;
            $text=strtolower(($d['summary']??'').' '.($d['decisions']??'')); if(strpos($text,'opportun')!==false||strpos($text,'request')!==false||strpos($text,'demand')!==false)$opps[]=$r;
        }
        arsort($terms);
        $overdue=array_values(array_filter($open_actions,fn($a)=>!empty($a['due'])&&$a['due']<$today));
        return ['records'=>count($items),'open_actions'=>$open_actions,'overdue'=>$overdue,'recurring'=>array_slice(array_filter($terms,fn($n)=>$n>1),0,10,true),'risks'=>$risks,'opportunities'=>$opps];
    }
}

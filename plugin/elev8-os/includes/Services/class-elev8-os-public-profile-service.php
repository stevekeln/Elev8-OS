<?php
/**
 * Shared public identity profiles for Elev8 OS users.
 *
 * @package Elev8OS
 */

if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Public_Profile_Service {
    public const META_PREFIX = '_elev8_public_profile_';

    /** @return array<string,string> */
    public static function available_types(): array {
        return apply_filters('elev8_os_public_profile_types', [
            'artist' => __('Artist', 'elev8-os'),
            'teacher' => __('Teacher', 'elev8-os'),
            'event_host' => __('Event Host / DJ', 'elev8-os'),
            'shop_manager' => __('Shop Manager', 'elev8-os'),
            'glass_manager' => __('Glass Manager', 'elev8-os'),
            'volunteer' => __('Volunteer', 'elev8-os'),
            'staff' => __('Staff', 'elev8-os'),
        ]);
    }

    /** @return array<string,mixed> */
    public static function get(int $user_id): array {
        $user = get_user_by('id', $user_id);
        if (!$user instanceof WP_User) return [];

        $display_name = (string) get_user_meta($user_id, self::META_PREFIX . 'display_name', true);
        $slug = (string) get_user_meta($user_id, self::META_PREFIX . 'slug', true);
        $types = get_user_meta($user_id, self::META_PREFIX . 'types', true);
        if (!is_array($types) || !$types) $types = self::inferred_types($user);
        $types = array_values(array_intersect(array_keys(self::available_types()), array_map('sanitize_key', $types)));
        $legacy = self::legacy_artist_profile($user_id);

        $bio = (string) get_user_meta($user_id, self::META_PREFIX . 'bio', true);
        $photo = (string) get_user_meta($user_id, self::META_PREFIX . 'photo_url', true);
        $website = (string) get_user_meta($user_id, self::META_PREFIX . 'website_url', true);
        $published = get_user_meta($user_id, self::META_PREFIX . 'status', true) === 'published';
        if ($legacy) {
            if ($bio === '') $bio = (string) ($legacy['bio'] ?? '');
            if ($photo === '') $photo = (string) ($legacy['profile_photo'] ?? '');
            if ($website === '') $website = (string) ($legacy['website'] ?? '');
            if (get_user_meta($user_id, self::META_PREFIX . 'status', true) === '') $published = !empty($legacy['public_enabled']);
            if (!in_array('artist', $types, true)) $types[] = 'artist';
        }

        return [
            'user_id' => $user_id,
            'display_name' => $display_name !== '' ? $display_name : (string) $user->display_name,
            'slug' => $slug !== '' ? $slug : self::default_slug($user),
            'headline' => (string) get_user_meta($user_id, self::META_PREFIX . 'headline', true),
            'bio' => $bio,
            'photo_url' => $photo,
            'website_url' => $website,
            'instagram_url' => (string) get_user_meta($user_id, self::META_PREFIX . 'instagram_url', true),
            'facebook_url' => (string) get_user_meta($user_id, self::META_PREFIX . 'facebook_url', true),
            'contact_email' => (string) get_user_meta($user_id, self::META_PREFIX . 'contact_email', true),
            'published' => $published,
            'profile_types' => $types,
            'profile_type' => $types[0] ?? 'staff',
            'role_label' => self::type_labels($types),
            'completeness' => self::completeness($bio, $photo, (string) ($display_name !== '' ? $display_name : $user->display_name)),
            'legacy_artist' => !empty($legacy),
            'updated_at' => (string) get_user_meta($user_id, self::META_PREFIX . 'updated_at', true),
        ];
    }

    public static function is_published(int $user_id): bool { return !empty(self::get($user_id)['published']); }
    public static function editor_url(int $user_id = 0): string { return $user_id > 0 && current_user_can('manage_options') ? admin_url('admin.php?page=elev8-public-profiles&user_id=' . $user_id) : home_url('/elev8-profile/'); }
    public static function admin_url(array $args = []): string { return add_query_arg($args, admin_url('admin.php?page=elev8-public-profiles')); }
    public static function public_url(int $user_id): string { $p=self::get($user_id); $s=sanitize_title((string)($p['slug']??'')); return $s!==''?home_url('/people/'.$s.'/'):''; }

    public static function user_id_from_slug(string $slug): int {
        $slug=sanitize_title($slug); if($slug==='') return 0;
        $users=get_users(['number'=>1,'fields'=>'ids','meta_key'=>self::META_PREFIX.'slug','meta_value'=>$slug]);
        if($users) return (int)$users[0];
        $user=get_user_by('slug',$slug); return $user instanceof WP_User?(int)$user->ID:0;
    }

    /** @param array<string,mixed> $input */
    public static function save(int $user_id, array $input): array {
        $user=get_user_by('id',$user_id); if(!$user instanceof WP_User) return ['success'=>false,'message'=>__('Profile account is unavailable.','elev8-os')];
        $display_name=sanitize_text_field((string)($input['display_name']??''));
        $headline=sanitize_text_field((string)($input['headline']??''));
        $bio=sanitize_textarea_field((string)($input['bio']??''));
        $slug=sanitize_title((string)($input['slug']??''));
        $photo_url=esc_url_raw((string)($input['photo_url']??''));
        $website_url=esc_url_raw((string)($input['website_url']??''));
        $instagram_url=esc_url_raw((string)($input['instagram_url']??''));
        $facebook_url=esc_url_raw((string)($input['facebook_url']??''));
        $contact_email=sanitize_email((string)($input['contact_email']??''));
        $publish=!empty($input['publish']);
        $types=array_values(array_intersect(array_keys(self::available_types()), array_map('sanitize_key',(array)($input['profile_types']??[]))));
        if(!$types) $types=self::inferred_types($user);
        if($display_name==='') $display_name=(string)$user->display_name;
        if($slug==='') $slug=self::default_slug($user);
        $slug=self::unique_slug($slug,$user_id);
        if($publish && trim($bio)==='') return ['success'=>false,'message'=>__('Add a short biography before publishing this public profile.','elev8-os')];
        foreach(compact('display_name','headline','bio','slug','photo_url','website_url','instagram_url','facebook_url','contact_email') as $key=>$value) update_user_meta($user_id,self::META_PREFIX.$key,$value);
        update_user_meta($user_id,self::META_PREFIX.'types',$types);
        update_user_meta($user_id,self::META_PREFIX.'status',$publish?'published':'draft');
        update_user_meta($user_id,self::META_PREFIX.'updated_at',current_time('mysql'));
        update_user_meta($user_id,'_elev8_public_host_profile_status',$publish?'published':'draft');
        self::sync_legacy_artist_publication($user_id,$publish,$bio,$photo_url,$website_url);
        if(class_exists('Elev8_OS_Activity_Service')) Elev8_OS_Activity_Service::record(['actor_user_id'=>get_current_user_id()?:$user_id,'type'=>$publish?'public_profile_published':'public_profile_updated','label'=>$publish?sprintf(__('%s published a public profile.','elev8-os'),$display_name):sprintf(__('%s updated a public profile draft.','elev8-os'),$display_name),'details'=>__('Public identity profile activity.','elev8-os'),'object_type'=>'user','object_id'=>$user_id,'source'=>'public_profiles']);
        return ['success'=>true,'message'=>$publish?__('The public profile is published.','elev8-os'):__('The profile draft was saved.','elev8-os')];
    }

    /** @return array<int,array<string,mixed>> */
    public static function directory(array $filters=[]): array {
        $users=get_users(['orderby'=>'display_name','order'=>'ASC','fields'=>'all']); $rows=[];
        foreach($users as $user){ if(!$user instanceof WP_User) continue; $p=self::get((int)$user->ID); if(!$p) continue;
            $status=!empty($p['published'])?'published':(((string)get_user_meta($user->ID,self::META_PREFIX.'status',true)==='draft'||$p['bio']!==''||$p['legacy_artist'])?'draft':'missing');
            if(!empty($filters['type']) && !in_array($filters['type'],$p['profile_types'],true)) continue;
            if(!empty($filters['status']) && $filters['status']!==$status) continue;
            $search=strtolower(trim((string)($filters['search']??''))); if($search!=='' && strpos(strtolower($p['display_name'].' '.$user->user_email),$search)===false) continue;
            $p['status']=$status; $p['email']=$user->user_email; $rows[]=$p;
        }
        return $rows;
    }

    /** @return array<string,int> */
    public static function summary(): array { $s=['total'=>0,'published'=>0,'draft'=>0,'missing'=>0,'incomplete'=>0]; foreach(self::directory() as $p){$s['total']++;$s[$p['status']]++;if((int)$p['completeness']<100)$s['incomplete']++;} return $s; }

    private static function completeness(string $bio,string $photo,string $name): int { $score=0; if(trim($name)!=='')$score+=25;if(trim($bio)!=='')$score+=40;if(trim($photo)!=='')$score+=35;return $score; }
    private static function default_slug(WP_User $user): string { $p=sanitize_title((string)$user->display_name); return $p!==''?$p:sanitize_title((string)$user->user_nicename); }
    private static function unique_slug(string $slug,int $user_id): string { $base=$slug;$n=2;while(($e=self::user_id_from_slug($slug))>0&&$e!==$user_id){$slug=$base.'-'.$n;$n++;}return $slug; }
    /** @return array<int,string> */
    private static function inferred_types(WP_User $user): array { $types=[]; if(class_exists('Elev8_OS_Access_Service')){ if(Elev8_OS_Access_Service::uses_event_host_home($user))$types[]='event_host'; if(Elev8_OS_Access_Service::is_manager($user))$types[]='shop_manager'; if(Elev8_OS_Access_Service::user_can('view_artist_dashboard',$user))$types[]='artist'; } return $types?:['staff']; }
    private static function type_labels(array $types): string { $available=self::available_types();$labels=[];foreach($types as $t)if(isset($available[$t]))$labels[]=$available[$t];return $labels?implode(' · ',$labels):__('Elev8 Team','elev8-os'); }
    /** @return array<string,mixed> */
    private static function legacy_artist_profile(int $user_id): array { $profiles=get_option('elev8_os_artist_profiles',[]); if(!is_array($profiles))return[];foreach($profiles as $p)if(is_array($p)&&(int)($p['wp_user_id']??0)===$user_id)return $p;return[]; }
    private static function sync_legacy_artist_publication(int $user_id,bool $publish,string $bio,string $photo,string $website): void { $profiles=get_option('elev8_os_artist_profiles',[]);if(!is_array($profiles))return;$changed=false;foreach($profiles as $id=>$p){if(!is_array($p)||(int)($p['wp_user_id']??0)!==$user_id)continue;$profiles[$id]['public_enabled']=$publish?1:0;if($bio!==''&&empty($p['bio']))$profiles[$id]['bio']=$bio;if($photo!==''&&empty($p['profile_photo']))$profiles[$id]['profile_photo']=$photo;if($website!==''&&empty($p['website']))$profiles[$id]['website']=$website;$changed=true;}if($changed)update_option('elev8_os_artist_profiles',$profiles,false); }
}

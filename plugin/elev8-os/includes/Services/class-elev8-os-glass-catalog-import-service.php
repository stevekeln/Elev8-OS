<?php
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Glass_Catalog_Import_Service {
    private const TRANSIENT_PREFIX = 'elev8_glass_catalog_wizard_';

    public static function session_key(): string { return self::TRANSIENT_PREFIX . get_current_user_id(); }
    public static function session(): array { $v=get_transient(self::session_key()); return is_array($v)?$v:[]; }
    public static function clear(): void { delete_transient(self::session_key()); }

    public static function upload_and_parse(array $file): array|WP_Error {
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) return new WP_Error('elev8_wizard_upload','Choose an Excel workbook.');
        $name=sanitize_file_name($file['name']??'glass-production.xlsx');
        $ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));
        if(!in_array($ext,['xlsx','xlsm'],true)) return new WP_Error('elev8_wizard_type','Upload an .xlsx or .xlsm workbook.');
        require_once ABSPATH.'wp-admin/includes/file.php';
        $handled=wp_handle_upload($file,['test_form'=>false,'mimes'=>['xlsx'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','xlsm'=>'application/vnd.ms-excel.sheet.macroEnabled.12']]);
        if(!empty($handled['error'])) return new WP_Error('elev8_wizard_store',$handled['error']);
        $parsed=self::parse_workbook($handled['file']);
        if(is_wp_error($parsed)) return $parsed;
        $parsed['file_url']=$handled['url']; $parsed['file_path']=$handled['file']; $parsed['file_name']=$name; $parsed['uploaded_at']=current_time('mysql');
        set_transient(self::session_key(),$parsed,DAY_IN_SECONDS*7);
        return $parsed;
    }

    private static function parse_workbook(string $path): array|WP_Error {
        $zip=null;
        if(class_exists('ZipArchive')){
            $zip=new ZipArchive();if($zip->open($path)!==true)return new WP_Error('elev8_wizard_open','Elev8 OS could not open the workbook.');
            $read=static function(string $name)use($zip){$v=$zip->getFromName($name);return $v===false?'':(string)$v;};$cleanup=static function()use($zip){$zip->close();};
        }else{
            require_once ABSPATH.'wp-admin/includes/file.php';WP_Filesystem();$uploads=wp_upload_dir();$temp=trailingslashit($uploads['basedir']).'elev8-catalog-wizard-'.wp_generate_uuid4();wp_mkdir_p($temp);$unzipped=unzip_file($path,$temp);
            if(is_wp_error($unzipped))return new WP_Error('elev8_wizard_zip','The server could not read the Excel workbook: '.$unzipped->get_error_message());
            $read=static function(string $name)use($temp){$file=trailingslashit($temp).$name;return is_readable($file)?(string)file_get_contents($file):'';};$cleanup=static function()use($temp){self::delete_tree($temp);};
        }
        $shared=[];$shared_xml=$read('xl/sharedStrings.xml');
        if($shared_xml&&preg_match_all('/<si\b[^>]*>(.*?)<\/si>/si',$shared_xml,$sis)){foreach($sis[1] as $si){$text='';if(preg_match_all('/<t\b[^>]*>(.*?)<\/t>/si',$si,$ts))foreach($ts[1] as $t)$text.=self::xml_text($t);$shared[]=$text;}}
        $workbook=$read('xl/workbook.xml');$rels=$read('xl/_rels/workbook.xml.rels');if(!$workbook||!$rels){$cleanup();return new WP_Error('elev8_wizard_structure','Workbook structure is unavailable.');}
        $relmap=[];if(preg_match_all('/<Relationship\b([^>]*)\/?\s*>/si',$rels,$rms))foreach($rms[1] as $attrs){$id=self::xml_attr($attrs,'Id');$target=self::xml_attr($attrs,'Target');if($id)$relmap[$id]=$target;}
        $sheet_path='';if(preg_match_all('/<sheet\b([^>]*)\/?\s*>/si',$workbook,$sms))foreach($sms[1] as $attrs){if(strcasecmp(self::xml_attr($attrs,'name'),'Production Information')===0){$rid=self::xml_attr($attrs,'r:id');$target=(string)($relmap[$rid]??'');$sheet_path=strpos($target,'xl/')===0?$target:'xl/'.ltrim($target,'/');break;}}
        if(!$sheet_path){$cleanup();return new WP_Error('elev8_wizard_sheet','The workbook does not contain a Production Information sheet.');}
        $sheet=$read($sheet_path);if(!$sheet){$cleanup();return new WP_Error('elev8_wizard_sheet_read','The Production Information sheet could not be read.');}
        $cells=[];if(preg_match_all('/<c\b([^>]*)>(.*?)<\/c>/si',$sheet,$cms,PREG_SET_ORDER)){foreach($cms as $cm){$ref=self::xml_attr($cm[1],'r');if(!$ref)continue;$type=self::xml_attr($cm[1],'t');$body=$cm[2];$value='';if($type==='inlineStr'){if(preg_match_all('/<t\b[^>]*>(.*?)<\/t>/si',$body,$ts))foreach($ts[1] as $t)$value.=self::xml_text($t);}elseif(preg_match('/<v\b[^>]*>(.*?)<\/v>/si',$body,$vm)){$raw=self::xml_text($vm[1]);$value=$type==='s'?(string)($shared[(int)$raw]??''):$raw;}$cells[$ref]=$value;}}
        if(preg_match_all('/<mergeCell\b[^>]*ref="([^"]+)"[^>]*\/?\s*>/si',$sheet,$mms))foreach($mms[1] as $range){[$start,$end]=array_pad(explode(':',$range),2,$range);[$sc,$sr]=self::split_ref($start);[$ec,$er]=self::split_ref($end);$value=$cells[$start]??'';if($value==='')continue;for($r=$sr;$r<=$er;$r++)for($n=self::col_num($sc);$n<=self::col_num($ec);$n++)$cells[self::num_col($n).$r]=$value;}
        $families=[];$items=[];$max=0;foreach(array_keys($cells) as $ref){[$c,$r]=self::split_ref($ref);$max=max($max,self::col_num($c));}
        for($n=self::col_num('J');$n<=$max;$n++){
            $col=self::num_col($n);$family=trim((string)($cells[$col.'4']??''));$sub=trim((string)($cells[$col.'5']??''));$ret=self::number($cells[$col.'6']??null);$pay=self::number($cells[$col.'15']??null);$time=self::number($cells[$col.'25']??null);
            if($family===''||($ret===null&&$pay===null&&$time===null))continue;$name=$family;if($sub!==''&&strcasecmp($sub,$family)!==0)$name.=' — '.$sub;$method=self::method_from_code(self::number($cells[$col.'3']??null));$aliases=array_values(array_unique(array_filter([strtolower($family),strtolower($sub),strtolower($name)])));
            $items[$col]=['source_column'=>$col,'family'=>$family,'subtype'=>$sub,'variant'=>'','catalog_name'=>$name,'search_aliases'=>implode(', ',$aliases),'product_code'=>'WB-'.$col,'compensation_method'=>$method,'piecework_unit'=>'piece','blower_pay'=>$pay??0,'estimated_minutes'=>$time??0,'actual_retail'=>$ret??0,'dist_profit_at_retail'=>self::number($cells[$col.'7']??null)??0,'dist_additional_cost'=>self::number($cells[$col.'8']??null)??0,'suggested_retail'=>self::number($cells[$col.'9']??null)??0,'dist_profit_wholesale'=>self::number($cells[$col.'10']??null)??0,'premier_profit'=>self::number($cells[$col.'11']??null)??0,'actual_wholesale'=>self::number($cells[$col.'12']??null)??0,'suggested_wholesale'=>self::number($cells[$col.'13']??null)??0,'sold_to_distributor_at'=>self::number($cells[$col.'14']??null)??0,'material_cost'=>self::number($cells[$col.'16']??null)??0,'total_cost'=>self::number($cells[$col.'17']??null)??0,'instructions'=>trim((string)($cells[$col.'18']??'')),'source_sheet'=>'Production Information','review_status'=>($name===$family&&$sub==='')?'review':'ready'];$families[$family][]=$col;
        }
        $cleanup();ksort($families,SORT_NATURAL|SORT_FLAG_CASE);return ['sheet'=>'Production Information','families'=>$families,'items'=>$items,'family_count'=>count($families),'item_count'=>count($items)];
    }

    private static function xml_attr(string $attrs,string $name): string {$pattern='/\\b'.preg_quote($name,'/').'="([^"]*)"/i';return preg_match($pattern,$attrs,$m)?self::xml_text($m[1]):'';}
    private static function xml_text(string $text): string {return html_entity_decode(strip_tags($text),ENT_QUOTES|ENT_XML1,'UTF-8');}

    private static function delete_tree(string $dir): void {if(!is_dir($dir))return;$items=scandir($dir)?:[];foreach($items as $item){if($item==='.'||$item==='..')continue;$path=$dir.DIRECTORY_SEPARATOR.$item;if(is_dir($path))self::delete_tree($path);else@unlink($path);}@rmdir($dir);}

    public static function import_family(string $family,array $posted,bool $update=false): array {
        $session=self::session();$columns=$session['families'][$family]??[];$summary=['created'=>0,'updated'=>0,'skipped'=>0,'errors'=>[]];
        foreach($columns as $col){if(empty($posted[$col]['selected'])){$summary['skipped']++;continue;}$base=$session['items'][$col]??null;if(!$base){$summary['skipped']++;continue;}
            $row=array_merge($base,[
                'catalog_name'=>sanitize_text_field($posted[$col]['catalog_name']??$base['catalog_name']),
                'search_aliases'=>sanitize_textarea_field($posted[$col]['search_aliases']??$base['search_aliases']),
                'compensation_method'=>sanitize_key($posted[$col]['compensation_method']??$base['compensation_method']),
                'blower_pay'=>(float)($posted[$col]['blower_pay']??$base['blower_pay']),
            ]);
            $result=Elev8_OS_Production_Catalog_Service::import_wizard_item($row,$update);
            if(is_wp_error($result))$summary['errors'][]=$row['catalog_name'].': '.$result->get_error_message();elseif($result==='updated')$summary['updated']++;elseif($result==='skipped')$summary['skipped']++;else$summary['created']++;
        }
        return $summary;
    }

    private static function method_from_code(?float $v): string {if($v===1.0)return 'piecework';if($v===3.0)return 'hourly';if($v===2.0||$v===4.0)return 'either';return 'piecework';}
    private static function number($v): ?float {if($v===null||$v===''||is_array($v))return null;if(!is_numeric($v))return null;return (float)$v;}
    private static function split_ref(string $ref): array {preg_match('/^([A-Z]+)(\d+)$/',$ref,$m);return [$m[1]??'A',(int)($m[2]??1)];}
    private static function col_num(string $col): int {$n=0;foreach(str_split($col) as $c)$n=$n*26+(ord($c)-64);return $n;}
    private static function num_col(int $n): string {$s='';while($n>0){$n--; $s=chr(65+$n%26).$s;$n=intdiv($n,26);}return $s;}
}

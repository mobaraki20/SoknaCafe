<?php
declare(strict_types=1);

if(session_status()!==PHP_SESSION_ACTIVE){
    $secure=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')||((int)($_SERVER['SERVER_PORT']??80)===443);
    session_name('sokna_public_guest');
    session_set_cookie_params(['httponly'=>true,'secure'=>$secure,'samesite'=>'Lax','path'=>'/']);
    session_start();
}

function public_guest_context(): array
{
    $ctx=$GLOBALS['soknaPublicGuestContext']??[];
    return is_array($ctx)?$ctx:[];
}
function e(mixed $value): string{return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function fa_digits(string|int|float $value): string{return strtr((string)$value,['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']);}
function en_digits(string|int $value): string{return strtr((string)$value,['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']);}
function json_script(mixed $value): string{return (string)json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);}
function valid_hex_color(string $value,string $default='#6f4e37'):string{return preg_match('/^#[0-9a-fA-F]{6}$/',trim($value))?strtolower(trim($value)):$default;}
function contrast_text_color(string $background,string $dark='#202522',string $light='#ffffff'):string{
    $hex=ltrim(valid_hex_color($background,'#365b4c'),'#');
    $rgb=[hexdec(substr($hex,0,2)),hexdec(substr($hex,2,2)),hexdec(substr($hex,4,2))];
    $lin=array_map(static function(int $c):float{$v=$c/255;return $v<=.04045?$v/12.92:(($v+.055)/1.055)**2.4;},$rgb);
    $lum=.2126*$lin[0]+.7152*$lin[1]+.0722*$lin[2];
    return 1.05/($lum+.05)>=($lum+.05)/.05?valid_hex_color($light,'#ffffff'):valid_hex_color($dark,'#202522');
}
function safe_external_url(string $url):string{
    $url=trim($url);if($url===''||!filter_var($url,FILTER_VALIDATE_URL))return '';
    return in_array(strtolower((string)parse_url($url,PHP_URL_SCHEME)),['http','https'],true)?$url:'';
}
function setting(string $key,string $default=''):string{
    $ctx=public_guest_context();$theme=is_array($ctx['snapshot']['theme']??null)?$ctx['snapshot']['theme']:[];
    $features=is_array($ctx['snapshot']['features']??null)?$ctx['snapshot']['features']:[];
    if(array_key_exists($key,$theme))return is_bool($theme[$key])?($theme[$key]?'1':'0'):(string)$theme[$key];
    if(array_key_exists($key,$features))return is_bool($features[$key])?($features[$key]?'1':'0'):(string)$features[$key];
    return $default;
}
function setting_bool(string $key,bool $default=false):bool{
    $value=setting($key,$default?'1':'0');
    return in_array(strtolower($value),['1','true','yes','on'],true);
}
function menu_theme():string{$v=setting('menu_theme','courtyard');return in_array($v,['courtyard','night-courtyard','kilim-wood'],true)?$v:'courtyard';}
function menu_font():string{return 'vazirmatn';}
function menu_density():string{$v=setting('menu_density','balanced');return in_array($v,['balanced','compact'],true)?$v:'balanced';}
function menu_layout():string{$v=setting('menu_layout','editorial');return in_array($v,['editorial','catalog'],true)?$v:'editorial';}
function ui_font_family(string $font=''):string{return '"Vazirmatn", Tahoma, "Segoe UI", Arial, sans-serif';}
function ui_font_head(string $font=''):string{
    $url=asset('assets/fonts/Vazirmatn-Variable.woff2');
    return '<link rel="preload" href="'.e($url).'" as="font" type="font/woff2" crossorigin><style>@font-face{font-family:"Vazirmatn";src:url("'.e($url).'") format("woff2");font-style:normal;font-weight:100 900;font-display:swap}</style>';
}
function public_guest_installation_id():string{return (string)(public_guest_context()['installation_id']??'');}
function public_guest_base_path():string{return '/guest/?installation_id='.rawurlencode(public_guest_installation_id());}
function public_guest_static_url(string $path):string{
    $root=realpath(dirname(__DIR__,2));$file=$root!==false?realpath($root.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$path)):false;
    $rev='dev';if($file!==false&&is_file($file)){$hash=hash_file('sha256',$file);if(is_string($hash))$rev=substr($hash,0,16);}
    return '/static.php?path='.rawurlencode($path).'&v='.rawurlencode($rev);
}
function asset(string $path):string{
    $clean=ltrim($path,'/');$ctx=public_guest_context();$manifest=is_array($ctx['media_manifest']??null)?$ctx['media_manifest']:[];
    if(isset($manifest[$clean])&&is_array($manifest[$clean]))return public_guest_media_url(public_guest_installation_id(),$manifest,$clean);
    if(str_starts_with($clean,'assets/'))return public_guest_static_url($clean);
    $map=[
        'api/create_order.php'=>'/api/v1/guest/compat/create_order.php',
        'api/order_status.php'=>'/api/v1/guest/compat/order_status.php',
        'api/guest_orders.php'=>'/api/v1/guest/compat/guest_orders.php',
        'api/table_context.php'=>'/api/v1/guest/compat/table_context.php',
        'api/waiter_call.php'=>'/api/v1/guest/compat/waiter_call.php',
        'api/metric.php'=>'/api/v1/guest/compat/metric.php',
        'about.php'=>'/guest/about.php',
    ];
    if(isset($map[$clean]))return $map[$clean].'?installation_id='.rawurlencode(public_guest_installation_id());
    if(rtrim($clean,'/')==='menu')return public_guest_base_path();
    return '/'.ltrim($clean,'/');
}
function canonical_asset(string $path):string{return asset($path);}
function csrf_token():string{
    if(empty($_SESSION['csrf_token']))$_SESSION['csrf_token']=bin2hex(random_bytes(32));
    return (string)$_SESSION['csrf_token'];
}
function public_guest_csrf_valid(?string $token):bool{return is_string($token)&&$token!==''&&hash_equals((string)($_SESSION['csrf_token']??''),$token);}
function favicon_head_tags():string{return '';}
function ui_icon(string $name,string $class='',string $label=''):string{
    $safeName=preg_replace('/[^a-z0-9_-]+/i','',$name)?:'info';$safeClass=trim(preg_replace('/[^a-z0-9 _-]+/i','',$class)??'');
    $href=asset('assets/icons/ui-sprite.svg').'#icon-'.$safeName;
    $aria=$label!==''?' role="img" aria-label="'.e($label).'"':' aria-hidden="true" focusable="false"';
    return '<svg class="ui-icon'.($safeClass!==''?' '.e($safeClass):'').'"'.$aria.'><use href="'.e($href).'"></use></svg>';
}
function responsive_image_data(?string $path):array{
    $path=trim((string)$path);$ctx=public_guest_context();$manifest=is_array($ctx['media_manifest']??null)?$ctx['media_manifest']:[];
    $meta=is_array($manifest[$path]??null)?$manifest[$path]:[];
    $src=$path!==''&&$meta?public_guest_media_url(public_guest_installation_id(),$manifest,$path):'';
    return ['src'=>$src,'srcset'=>'','width'=>max(1,(int)($meta['width']??1)),'height'=>max(1,(int)($meta['height']??1))];
}
function customer_message(string $key,array $vars=[]):string{
    $messages=public_guest_context()['snapshot']['messages']??[];$text=(string)($messages[$key]??$key);
    foreach($vars as $name=>$value)$text=str_replace('{'.$name.'}',(string)$value,$text);
    return $text;
}
function customer_message_enabled(string $key):bool{
    $messages=public_guest_context()['snapshot']['messages']??[];
    return !array_key_exists($key.'_enabled',$messages)||(bool)$messages[$key.'_enabled'];
}
function public_customer_messages():array{$m=public_guest_context()['snapshot']['messages']??[];return is_array($m)?$m:[];}
function sokna_module_enabled(string $key):bool{
    $features=public_guest_context()['snapshot']['features']??[];
    if($key==='marketing')return !empty($features['marketing_module']);
    if($key==='reporting')return !empty($features['reporting_module']);
    return false;
}
function category_visual_icon(?string $iconKey,string $name=''):string{
    $key=preg_replace('/[^a-z0-9_-]+/i','',trim((string)$iconKey));
    return $key!==''?$key:'sparkles';
}
function parse_toman_amount_text(string|int|null $value):?int{
    $raw=trim(en_digits((string)($value??'')));if($raw==='')return null;
    $raw=str_replace(['تومان',',','،','٬',' '],'',$raw);
    return $raw!==''&&ctype_digit($raw)?(int)$raw:null;
}
function toman_number(int|float|string $amount):string{return fa_digits(number_format((float)$amount,0,'.',','));}
function toman(int|float|string $amount):string{return toman_number($amount).' تومان';}
function event_display_fee_amount(array $event):?int{
    if(array_key_exists('fee_amount',$event)&&$event['fee_amount']!==null&&$event['fee_amount']!==''){
        $v=parse_toman_amount_text($event['fee_amount']);if($v!==null)return $v;
    }
    return parse_toman_amount_text($event['admission_text']??null);
}
function event_display_admission_text(array $event):string{
    $text=trim((string)($event['admission_text']??''));return $text!==''&&parse_toman_amount_text($text)===null?$text:'';
}
function whatsapp_number(string $value):string{
    $digits=preg_replace('/\D+/','',en_digits(trim($value)))??'';
    if(str_starts_with($digits,'00'))$digits=substr($digits,2);
    if(str_starts_with($digits,'0')&&strlen($digits)===11)$digits='98'.substr($digits,1);
    return preg_match('/^[1-9][0-9]{7,14}$/',$digits)?$digits:'';
}
function event_whatsapp_url(array $event):string{
    $number=whatsapp_number(setting('whatsapp_number',''));if($number==='')return '';
    $title=trim((string)($event['title']??'رویداد'));$custom=trim((string)($event['registration_value']??''));
    $message=$custom!==''?$custom:'سلام، برای رویداد «'.$title.'» اطلاعات و ثبت‌نام می‌خواهم.';
    return 'https://wa.me/'.$number.'?text='.rawurlencode($message);
}
function campaign_url(array $campaign):string{
    $type=(string)($campaign['action_type']??'none');$value=trim((string)($campaign['action_value']??''));
    if($type==='external')return safe_external_url($value);
    if($type==='category'&&ctype_digit($value))return '#category-'.$value;
    if($type==='events')return sokna_module_enabled('marketing')&&setting_bool('events_enabled',true)?'#events':'';
    if($type==='about')return asset('about.php');
    return '';
}
function normalize_preparation_station(string $station):string{return in_array($station,['kitchen','cold_bar','hot_bar'],true)?$station:'cold_bar';}
function preparation_area_for_station(string $station):string{return normalize_preparation_station($station)==='kitchen'?'kitchen':'bar';}
function public_guest_availability():array{$a=public_guest_context()['availability']??[];return is_array($a)?$a:[];}
function order_acceptance_states():array{
    $a=public_guest_availability()['order_acceptance']??[];
    return ['cafe'=>(bool)($a['cafe']??false),'kitchen'=>(bool)($a['kitchen']??false),'bar'=>(bool)($a['bar']??false)];
}
function order_acceptance_message(string $scope):string{
    $m=public_guest_availability()['order_acceptance_messages']??[];
    return (string)($m[$scope]??'سفارش آنلاین این بخش فعلاً متوقف است.');
}
function order_acceptance_blocked_scope_for_station(string $station):?string{
    $states=order_acceptance_states();if(!$states['cafe'])return 'cafe';
    $area=preparation_area_for_station($station);return !($states[$area]??false)?$area:null;
}
function station_busy_states():array{$v=public_guest_availability()['station_states']??[];return is_array($v)?$v:[];}
function station_is_busy(string $station):bool{$states=station_busy_states();return !empty($states[normalize_preparation_station($station)]);}
function station_state_hash():string{return (string)(public_guest_availability()['station_state_hash']??'');}

<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
require_login(['admin']);
require dirname(__DIR__) . '/includes/panel_layout.php';

function save_settings(array $values): void {
    $stmt=db()->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
    foreach($values as $key=>$value)$stmt->execute([$key,(string)$value]);
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf($_POST['csrf_token']??null);$action=(string)($_POST['action']??'');$uploadedAsset=null;$createdFaviconPaths=[];
    $returnSection=match($action){'identity','favicon','favicon_reset'=>'settingsBrand','theme','public','guest_features'=>'settingsGuest','operations'=>'settingsOperations','domain'=>'settingsIntegrations','system_options','password'=>'settingsSystem',default=>'settingsBrand'};
    try{
        if($action==='identity'){
            $name=text_substr(trim((string)($_POST['cafe_name']??'')),0,120);if($name==='')throw new RuntimeException('اسم کافه رو وارد کن.');
            $colors=[];foreach(['primary_color'=>'#365b4c','accent_color'=>'#b85c38','background_color'=>'#f7f3ec'] as $key=>$fallback){$value=trim((string)($_POST[$key]??$fallback));if(!preg_match('/^#[0-9a-fA-F]{6}$/',$value))throw new RuntimeException('یکی از رنگ‌ها معتبر نیست.');$colors[$key]=strtolower($value);}            
            $oldLogo=setting('logo_path')?:null;$imageChoice=resolve_image_input($_FILES['image']??[],$oldLogo,$_POST);$logo=$imageChoice['path']??'';$uploadedAsset=$imageChoice['uploaded'];$logoChanged=$imageChoice['changed'];
            save_settings(array_merge($colors,['cafe_name'=>$name,'logo_path'=>$logo]));if(($logoChanged??false)&&$oldLogo)delete_upload_path($oldLogo);
            flash('success','هویت و ظاهر ذخیره شد.');
        }elseif($action==='favicon'){
            $oldCustom=[];foreach([32,180,192,512] as $size){$value=trim(setting('favicon_'.$size.'_path'));if(str_starts_with($value,'uploads/site-icon-'))$oldCustom[$size]=$value;}
            $createdFaviconPaths=favicon_save_payload((string)($_POST['favicon_payload']??''));
            save_settings([
                'favicon_32_path'=>$createdFaviconPaths[32],
                'favicon_180_path'=>$createdFaviconPaths[180],
                'favicon_192_path'=>$createdFaviconPaths[192],
                'favicon_512_path'=>$createdFaviconPaths[512],
                'favicon_updated_at'=>date(DATE_ATOM),
            ]);
            favicon_delete_paths($oldCustom);
            $createdFaviconPaths=[];
            flash('success','فاوآیکن مرورگر، موبایل و وب‌اپ به‌روزرسانی شد.');
        }elseif($action==='favicon_reset'){
            $oldCustom=[];foreach([32,180,192,512] as $size){$value=trim(setting('favicon_'.$size.'_path'));if(str_starts_with($value,'uploads/site-icon-'))$oldCustom[$size]=$value;}
            save_settings(['favicon_32_path'=>'','favicon_180_path'=>'','favicon_192_path'=>'','favicon_512_path'=>'','favicon_updated_at'=>date(DATE_ATOM)]);
            favicon_delete_paths($oldCustom);
            flash('success','فاوآیکن پیش‌فرض Sokna فعال شد.');
        }elseif($action==='theme'){
            $theme=in_array(($_POST['menu_theme']??''),['courtyard','night-courtyard','kilim-wood'],true)?(string)$_POST['menu_theme']:'courtyard';
            $density=in_array(($_POST['menu_density']??''),['balanced','compact'],true)?(string)$_POST['menu_density']:'balanced';
            $layout=in_array(($_POST['menu_layout']??''),['editorial','catalog'],true)?(string)$_POST['menu_layout']:'editorial';
            save_settings(['menu_theme'=>$theme,'menu_density'=>$density,'menu_layout'=>$layout]);
            flash(font_available('vazirmatn')?'success':'warning',font_available('vazirmatn')?'قالب ذخیره شد و وزیرمتن محلی فعاله.':'قالب ذخیره شد؛ سامانه نصب محلی وزیرمتن را دوباره تلاش می‌کند.');
        }elseif($action==='guest_features'){
            $guestFeatures = [
                'public_waiter_call_enabled'=>isset($_POST['public_waiter_call_enabled'])?'1':'0',
            ];
            // When Marketing is disabled these controls are intentionally absent from the form.
            // Preserve their previous preferences so re-enabling the module restores the operator's
            // last explicit guest-display choices instead of silently turning both features off.
            if (sokna_module_enabled('marketing')) {
                $guestFeatures['events_enabled'] = isset($_POST['events_enabled'])?'1':'0';
                $guestFeatures['campaigns_enabled'] = isset($_POST['campaigns_enabled'])?'1':'0';
            }
            save_settings($guestFeatures);flash('success','امکانات مهمان ذخیره شد.');
        }elseif($action==='operations'){
            $cutoff=business_clock_normalize((string)($_POST['business_day_cutoff']??'04:00'));
            $keys=(array)($_POST['shift_key']??[]);
            $labels=(array)($_POST['shift_label']??[]);
            $starts=(array)($_POST['shift_start']??[]);
            $ends=(array)($_POST['shift_end']??[]);
            $count=max(count($keys),count($labels),count($starts),count($ends));
            if($count<1||$count>3)throw new RuntimeException('بین یک تا سه شیفت عملیاتی تعریف کن.');
            $postedShifts=[];$seen=[];
            $currentShiftKeys=array_fill_keys(array_map(static fn(array $shift):string=>(string)$shift['key'],business_shifts()),true);
            $historicalShiftKeys=array_fill_keys(business_used_shift_keys(),true);
            for($i=0;$i<$count;$i++){
                $key=preg_replace('/[^a-z0-9_\-]/i','',trim((string)($keys[$i]??'')));
                if($key==='')$key=business_new_shift_key([...array_keys($currentShiftKeys),...array_keys($historicalShiftKeys),...array_keys($seen)]);
                if(isset($seen[$key]))throw new RuntimeException('شناسه دو شیفت تکراری است؛ صفحه را تازه‌سازی و دوباره تلاش کن.');
                if(!isset($currentShiftKeys[$key])&&isset($historicalShiftKeys[$key]))throw new RuntimeException('این شیفت با یک شیفت قدیمی تداخل دارد؛ صفحه را تازه‌سازی و دوباره تلاش کن.');
                $seen[$key]=true;
                $postedShifts[]=[
                    'key'=>$key,
                    'label'=>text_substr(trim((string)($labels[$i]??'')),0,60),
                    'start'=>(string)($starts[$i]??''),
                    'end'=>(string)($ends[$i]??''),
                    'active'=>true,
                ];
            }
            $validated=business_validate_configuration($cutoff,$postedShifts);
            $storedShifts=array_map(static fn(array $shift):array=>[
                'key'=>(string)$shift['key'],'label'=>(string)$shift['label'],'start'=>(string)$shift['start'],'end'=>(string)$shift['end'],'active'=>true,
            ],$validated['shifts']);
            $previous=['cutoff'=>business_day_cutoff(),'shifts'=>array_map(static fn(array $shift):array=>['key'=>$shift['key'],'label'=>$shift['label'],'start'=>$shift['start'],'end'=>$shift['end']],business_shifts())];
            save_settings([
                'show_visit_duration'=>isset($_POST['show_visit_duration'])?'1':'0',
                'new_device_alert_minutes'=>(string)max(5,min(180,(int)en_digits((string)($_POST['new_device_alert_minutes']??20)))),
                'business_day_cutoff'=>(string)$validated['cutoff'],
                'business_shifts_json'=>json_encode($storedShifts,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),
            ]);
            clear_setting_cache();
            audit_log_write('operations.business_time_changed','settings','business_time',['previous'=>$previous,'current'=>['cutoff'=>$validated['cutoff'],'shifts'=>$storedShifts]],(int)current_user()['id']);
            flash('success','تنظیمات عملیات و شیفت‌ها ذخیره شد.');
        }elseif($action==='system_options'){
            $systemOptions = ['pwa_enabled'=>isset($_POST['pwa_enabled'])?'1':'0'];
            // Reporting owns guest-menu metric collection. When the whole module is disabled the
            // checkbox is intentionally absent; preserve the owner's previous preference for re-enable.
            if (sokna_module_enabled('reporting')) {
                $systemOptions['analytics_enabled'] = isset($_POST['analytics_enabled'])?'1':'0';
            }
            save_settings($systemOptions);flash('success','تنظیمات سامانه ذخیره شد.');
        }elseif($action==='public'){
            foreach(['instagram_cafe_url','instagram_house_url','accommodation_site_url','accommodation_rooms_url','accommodation_tours_url'] as $key){$value=trim((string)($_POST[$key]??''));if($value!==''&&!safe_external_url($value))throw new RuntimeException('یکی از آدرس‌های اینترنتی معتبر نیست.');}
            $wa=trim((string)($_POST['whatsapp_number']??''));if(isset($_POST['whatsapp_enabled'])&&whatsapp_number($wa)==='')throw new RuntimeException('شماره واتس‌اپ رو با کد کشور وارد کن؛ مثلاً 98912...');
            save_settings([
                'public_about_enabled'=>isset($_POST['public_about_enabled'])?'1':'0','about_title'=>text_substr(trim((string)($_POST['about_title']??'')),0,120)?:'درباره سکنا','about_intro'=>text_substr(trim((string)($_POST['about_intro']??'')),0,1500),'seo_description'=>text_substr(trim((string)($_POST['seo_description']??'')),0,300),'public_address'=>text_substr(trim((string)($_POST['public_address']??'')),0,500),'public_phone'=>text_substr(trim((string)($_POST['public_phone']??'')),0,50),
                'instagram_cafe_url'=>trim((string)($_POST['instagram_cafe_url']??'')),'instagram_house_url'=>trim((string)($_POST['instagram_house_url']??'')),'whatsapp_enabled'=>isset($_POST['whatsapp_enabled'])?'1':'0','whatsapp_number'=>$wa,'whatsapp_message'=>text_substr(trim((string)($_POST['whatsapp_message']??'')),0,500),'social_footer_enabled'=>isset($_POST['social_footer_enabled'])?'1':'0','post_order_instagram_enabled'=>isset($_POST['post_order_instagram_enabled'])?'1':'0',
                'accommodation_enabled'=>isset($_POST['accommodation_enabled'])?'1':'0','accommodation_site_url'=>trim((string)($_POST['accommodation_site_url']??'')),'accommodation_rooms_url'=>trim((string)($_POST['accommodation_rooms_url']??'')),'accommodation_tours_url'=>trim((string)($_POST['accommodation_tours_url']??'')),'accommodation_card_title'=>text_substr(trim((string)($_POST['accommodation_card_title']??'')),0,140),'accommodation_card_text'=>text_substr(trim((string)($_POST['accommodation_card_text']??'')),0,300)
            ]);flash('success','اطلاعات عمومی و راه‌های ارتباط ذخیره شد.');
        }elseif($action==='domain'){
            if(!isset($_POST['confirm_domain_change']))throw new RuntimeException('برای ذخیره آدرس اصلی، تیک تأیید را فعال کن.');
            $url=normalize_app_url((string)($_POST['app_canonical_url']??''));
            if($url==='')throw new RuntimeException('آدرس اصلی معتبر نیست. آدرس کامل را با http یا https و بدون بخش اضافه بعد از نشانی وارد کن.');
            $previous=app_canonical_url();
            save_settings(['app_canonical_url'=>$url,'app_canonical_url_previous'=>$previous,'app_canonical_url_changed_at'=>date(DATE_ATOM)]);
            flash('success','آدرس اصلی سامانه ذخیره شد. QRها، نسخه نصب‌شده وب‌اپ و اعلان‌ها را بررسی کن.');
        }elseif($action==='password'){
            $current=(string)($_POST['current_password']??'');$new=(string)($_POST['new_password']??'');$confirm=(string)($_POST['confirm_password']??'');$stmt=db()->prepare('SELECT password_hash FROM users WHERE id=?');$stmt->execute([current_user()['id']]);$hash=$stmt->fetchColumn();if(!$hash||!password_verify($current,$hash))throw new RuntimeException('رمز فعلی درست نیست.');if(text_length($new)<8)throw new RuntimeException('رمز تازه باید حداقل ۸ کاراکتر باشه.');if($new!==$confirm)throw new RuntimeException('تکرار رمز با رمز تازه یکی نیست.');db()->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($new,PASSWORD_DEFAULT),current_user()['id']]);flash('success','رمزت تغییر کرد.');
        }
    }catch(Throwable $e){if($uploadedAsset)delete_upload_path($uploadedAsset);if($createdFaviconPaths)favicon_delete_paths($createdFaviconPaths);error_log('settings save: '.$e->getMessage());flash('error',safe_business_error_message($e,'تنظیمات ذخیره نشد. دوباره تلاش کن.'));}
    redirect('settings.php#'.$returnSection);
}
$imageHealth=image_system_health();
$detectedUrl=detected_app_base_url();
$canonicalUrl=app_canonical_url();
$businessCutoff=business_day_cutoff();
$businessShifts=business_shifts();
$marketingModuleEnabled=sokna_module_enabled('marketing');
$reportingModuleEnabled=sokna_module_enabled('reporting');
panel_header('تنظیمات سامانه','settings');
?>
<section class="settings-section-index"><div><strong>بخش تنظیمات</strong><span>فقط بخش انتخاب‌شده نمایش داده می‌شود؛ تغییرات هر بخش جداگانه ذخیره می‌شوند.</span></div><nav class="panel-primary-tabs settings-section-nav" aria-label="بخش‌های تنظیمات" data-settings-nav>
<a href="#settingsBrand">هویت و برند</a><a href="#settingsGuest">منوی مهمان</a><a href="#settingsOperations">امکانات و عملیات</a><a href="#settingsIntegrations">دامنه و اتصال‌ها</a><a href="#settingsSystem">امنیت و سلامت</a>
</nav></section>
<div class="settings-page-grid">
<section class="card settings-section" id="settingsIdentity"><div class="card-head"><h2>هویت و ظاهر</h2></div><div class="card-body"><form method="post" enctype="multipart/form-data" class="form-grid"><?= csrf_field() ?>
<div class="form-group full"><label>نام کافه</label><input class="form-control" name="cafe_name" value="<?= e(setting('cafe_name','کافه من')) ?>" maxlength="120" required></div>
<div class="panel-color-row full"><label class="panel-color-field"><input type="color" name="primary_color" value="<?= e(valid_hex_color(setting('primary_color','#365b4c'),'#365b4c')) ?>"><span><strong>رنگ اصلی پنل</strong><small>اقدام اصلی در پنل و عملیات</small></span></label><label class="panel-color-field"><input type="color" name="accent_color" value="<?= e(valid_hex_color(setting('accent_color','#b85c38'),'#b85c38')) ?>"><span><strong>رنگ تأکیدی پنل</strong><small>تأکید محدود در پنل مدیریت</small></span></label><label class="panel-color-field"><input type="color" name="background_color" value="<?= e(valid_hex_color(setting('background_color','#f7f3ec'),'#f7f3ec')) ?>"><span><strong>پس‌زمینه پنل</strong><small>سطح اصلی صفحات مدیریتی</small></span></label></div>
<?= image_picker_html(setting('logo_path') ?: null, 'لوگوی کافه', 'لوگوی مربع با حاشیه امن پیشنهاد می‌شود. از آلبوم سایت یا دستگاه انتخاب کن.') ?>
<div class="form-group full panel-action-bar"><button class="btn btn-primary" name="action" value="identity">ذخیره هویت</button></div></form></div></section>
<section class="card settings-section" id="settingsFavicon"><div class="card-head"><div><h2>فاوآیکن و آیکن نصب</h2><small>این تصویر در تب مرورگر، میانبر موبایل و نسخه نصب‌شده وب‌اپ استفاده می‌شود.</small></div></div><div class="card-body">
<form method="post" class="form-grid" id="faviconForm"><?= csrf_field() ?>
<div class="form-group full"><div class="favicon-preview-row"><img src="<?= e(favicon_url(192)) ?>" width="96" height="96" alt="فاوآیکن فعلی"><div><strong>آیکن فعلی</strong><p class="muted">یک تصویر ساده، خوانا و ترجیحاً مربع انتخاب کن؛ سامانه اندازه‌های لازم را برای مرورگر و نصب وب‌اپ آماده می‌کند.</p></div></div></div>
<div class="form-group full"><label for="faviconSource">تصویر تازه</label><input class="form-control" id="faviconSource" type="file" accept="image/png,image/jpeg,image/webp"><small class="muted">حداکثر ۴ مگابایت. لوگو با حاشیه امن نتیجه بهتری روی موبایل می‌دهد.</small></div>
<input type="hidden" name="favicon_payload" id="faviconPayload">
<div class="form-group full"><div class="panel-action-bar"><button class="btn btn-primary" type="submit" name="action" value="favicon" id="faviconSave">ساخت و ذخیره فاوآیکن</button><button class="btn btn-light" type="submit" name="action" value="favicon_reset" formnovalidate>بازگشت به آیکن پیش‌فرض</button></div><p class="muted" id="faviconStatus" role="status" aria-live="polite"></p></div>
</form></div></section>
<section class="card settings-section" id="settingsTheme"><div class="card-head"><h2>ظاهر منوی مهمان</h2><small>پالت، تراکم و سبک نمایش منوی عمومی؛ فونت کل سامانه ثابت و یکپارچه است.</small></div><div class="card-body"><form method="post" class="form-grid" id="guestThemeForm"><?= csrf_field() ?>
<div class="form-group full"><label>پالت رنگ</label><div class="theme-choice-grid" role="radiogroup" aria-label="انتخاب پالت رنگ">
<label class="theme-choice courtyard"><input type="radio" name="menu_theme" value="courtyard" <?= menu_theme()==='courtyard'?'checked':'' ?>><span class="theme-choice-swatch"><i></i><i></i><i></i><i></i></span><strong>حیاط سکنا</strong><small>کرم گچی، فیروزه‌ای، چوب و نور کهربایی</small></label>
<label class="theme-choice night-courtyard"><input type="radio" name="menu_theme" value="night-courtyard" <?= menu_theme()==='night-courtyard'?'checked':'' ?>><span class="theme-choice-swatch"><i></i><i></i><i></i><i></i></span><strong>شب حیاط</strong><small>آبی شب، سبز تیره و طلایی گرم</small></label>
<label class="theme-choice kilim-wood"><input type="radio" name="menu_theme" value="kilim-wood" <?= menu_theme()==='kilim-wood'?'checked':'' ?>><span class="theme-choice-swatch"><i></i><i></i><i></i><i></i></span><strong>گلیم و چوب</strong><small>خاک، چوب، لاکی و زیتونی محدود</small></label>
</div></div>
<div class="form-group"><label>تراکم کارت‌ها</label><select class="form-control" name="menu_density" data-choice-mode="compact"><option value="balanced" <?= menu_density()==='balanced'?'selected':'' ?>>متعادل</option><option value="compact" <?= menu_density()==='compact'?'selected':'' ?>>جمع‌وجور</option></select></div><div class="form-group"><label>سبک نمایش منوی مهمان</label><select class="form-control" name="menu_layout" data-choice-mode="compact"><option value="editorial" <?= menu_layout()==='editorial'?'selected':'' ?>>تصویری و پیشنهاد‌محور</option><option value="catalog" <?= menu_layout()==='catalog'?'selected':'' ?>>فهرستی و جمع‌وجور</option></select></div>
<div class="form-group full"><label>پیش‌نمایش منوی عمومی</label><div class="guest-theme-live-preview theme-<?= e(menu_theme()) ?> font-<?= e(menu_font()) ?> preview-layout-<?= e(menu_layout()) ?>" id="guestThemePreview"><div class="preview-arch"><span class="preview-logo"><?= ui_icon('coffee') ?></span><div><small>پیش‌نمایش عمومی</small><strong><?= e(setting('cafe_name','سکنا')) ?></strong></div><span class="preview-event"><?= ui_icon('calendar') ?> رویدادها</span></div><div class="preview-search"><?= ui_icon('search') ?><span>جست‌وجو در نام یا دسته‌بندی</span></div><div class="preview-category-orbit"><span class="active"><?= ui_icon('coffee') ?><small>قهوه گرم</small></span><span><?= ui_icon('snowflake') ?><small>نوشیدنی سرد</small></span><span><?= ui_icon('cake') ?><small>دسر</small></span></div><article><div class="preview-photo"><?= ui_icon('coffee') ?></div><div><strong>لاته زعفرانی</strong><small>اسپرسو، شیر تازه و عطر زعفران</small><b>۱۸۵٬۰۰۰ تومان</b></div></article><?php if(setting_bool('public_waiter_call_enabled',false)): ?><div class="preview-service-note"><?= ui_icon('bell') ?><span>فراخوان گارسون در منوی عمومی فعال است.</span></div><?php endif; ?></div></div>
<div class="form-group full panel-action-bar"><button class="btn btn-primary" name="action" value="theme"><?= ui_icon('check') ?> ذخیره و انتشار قالب</button></div></form></div></section>
<script>
(() => {
 const form=document.getElementById('guestThemeForm');const preview=document.getElementById('guestThemePreview');if(!form||!preview)return;
 const sync=()=>{const theme=form.querySelector('[name="menu_theme"]:checked')?.value||'courtyard';const layout=form.querySelector('[name="menu_layout"]')?.value||'editorial';preview.className=`guest-theme-live-preview theme-${theme} font-vazirmatn preview-layout-${layout}`;};
 form.addEventListener('change',sync);sync();
})();
</script>
<script>
(() => {
 const form=document.getElementById('faviconForm');const input=document.getElementById('faviconSource');const payload=document.getElementById('faviconPayload');const status=document.getElementById('faviconStatus');const save=document.getElementById('faviconSave');if(!form||!input||!payload||!save)return;
 let prepared=false;
 const loadImage=async(file)=>{if('createImageBitmap'in window)return await createImageBitmap(file);return await new Promise((resolve,reject)=>{const img=new Image();const url=URL.createObjectURL(file);img.onload=()=>{URL.revokeObjectURL(url);resolve(img)};img.onerror=()=>{URL.revokeObjectURL(url);reject(new Error('تصویر قابل خواندن نیست.'))};img.src=url;});};
 form.addEventListener('submit',async(event)=>{
   const submitter=event.submitter;if(submitter?.value!=='favicon'||prepared)return;
   event.preventDefault();const file=input.files?.[0];
   if(!file){status.textContent='ابتدا یک تصویر انتخاب کن.';input.focus();return;}
   if(file.size>4*1024*1024){status.textContent='حجم تصویر بیشتر از ۴ مگابایت است.';return;}
   if(!['image/png','image/jpeg','image/webp'].includes(file.type)){status.textContent='فرمت تصویر باید PNG، JPEG یا WebP باشد.';return;}
   save.disabled=true;status.textContent='در حال ساخت اندازه‌های استاندارد…';
   try{
     const source=await loadImage(file);const sw=source.width||source.naturalWidth;const sh=source.height||source.naturalHeight;if(!sw||!sh)throw new Error('ابعاد تصویر معتبر نیست.');
     const output={};for(const size of [32,180,192,512]){const canvas=document.createElement('canvas');canvas.width=size;canvas.height=size;const ctx=canvas.getContext('2d',{alpha:true});ctx.clearRect(0,0,size,size);const max=size*.76;const scale=Math.min(max/sw,max/sh);const w=sw*scale,h=sh*scale;ctx.imageSmoothingEnabled=true;ctx.imageSmoothingQuality='high';ctx.drawImage(source,(size-w)/2,(size-h)/2,w,h);output[size]=canvas.toDataURL('image/png');}
     if(source.close)source.close();payload.value=JSON.stringify(output);prepared=true;save.disabled=false;status.textContent='اندازه‌ها آماده شد؛ در حال ذخیره…';form.requestSubmit(save);
   }catch(error){status.textContent=window.CafeUI?.requestErrorMessage?.(error,'ساخت فاوآیکن ناموفق بود.')||'ساخت فاوآیکن ناموفق بود.';save.disabled=false;}
 });
 input.addEventListener('change',()=>{prepared=false;payload.value='';status.textContent='';save.disabled=false;});
})();
</script>
</div>

<section class="card settings-section" id="settingsPublic" style="margin-top:18px"><div class="card-head"><h2>صفحه عمومی، اقامتگاه و شبکه‌های اجتماعی</h2><small>این بخش در پایین منو و صفحه عمومی نمایش داده می‌شود و مسیر سفارش را نمی‌پوشاند.</small></div><div class="card-body"><form method="post" class="form-grid"><?= csrf_field() ?>
<div class="form-group full feature-switch"><label><input type="checkbox" name="public_about_enabled" <?= setting_bool('public_about_enabled',true)?'checked':'' ?>> صفحه عمومی «درباره سکنا» فعال باشد</label></div>
<div class="form-group"><label>عنوان صفحه</label><input class="form-control" name="about_title" value="<?= e(setting('about_title','درباره سکنا')) ?>"></div><div class="form-group"><label>توضیح نتایج جست‌وجو</label><input class="form-control" name="seo_description" value="<?= e(setting('seo_description')) ?>" maxlength="300"></div>
<div class="form-group full"><label>معرفی کوتاه</label><textarea class="form-control" name="about_intro" maxlength="1500"><?= e(setting('about_intro')) ?></textarea></div><div class="form-group full"><label>آدرس مجموعه</label><textarea class="form-control" name="public_address" maxlength="500"><?= e(setting('public_address')) ?></textarea></div><div class="form-group"><label>شماره تماس عمومی</label><input class="form-control ltr-input" dir="ltr" type="tel" inputmode="tel" enterkeyhint="next" autocomplete="tel" name="public_phone" value="<?= e(setting('public_phone')) ?>"></div>
<div class="form-group"><label>اینستاگرام کافه</label><input class="form-control ltr-input" dir="ltr" type="url" name="instagram_cafe_url" value="<?= e(setting('instagram_cafe_url')) ?>" placeholder="https://instagram.com/..."></div><div class="form-group"><label>اینستاگرام اقامتگاه</label><input class="form-control ltr-input" dir="ltr" type="url" name="instagram_house_url" value="<?= e(setting('instagram_house_url')) ?>"></div>
<div class="form-group full feature-switch"><label><input type="checkbox" name="whatsapp_enabled" <?= setting_bool('whatsapp_enabled',false)?'checked':'' ?>> دکمه گفت‌وگوی مستقیم واتس‌اپ نمایش داده شود</label><small>شماره در لینک عمومی دیده می‌شود؛ شماره کاری مجموعه استفاده شود.</small></div><div class="form-group"><label>شماره واتس‌اپ با کد کشور</label><input class="form-control ltr-input" dir="ltr" type="tel" inputmode="tel" enterkeyhint="next" autocomplete="tel" name="whatsapp_number" value="<?= e(setting('whatsapp_number')) ?>" placeholder="98912..."></div><div class="form-group"><label>پیام آماده واتس‌اپ</label><input class="form-control" name="whatsapp_message" value="<?= e(setting('whatsapp_message')) ?>" maxlength="500"></div>
<div class="form-group full feature-switch"><label><input type="checkbox" name="accommodation_enabled" <?= setting_bool('accommodation_enabled',true)?'checked':'' ?>> معرفی کوتاه اقامتگاه در پایین منو نمایش داده شود</label></div><div class="form-group full"><label>آدرس اصلی سایت اقامتگاه</label><input class="form-control ltr-input" dir="ltr" type="url" name="accommodation_site_url" value="<?= e(setting('accommodation_site_url','https://www.soknahouse.ir/')) ?>"></div><div class="form-group"><label>لینک اتاق‌ها، اختیاری</label><input class="form-control ltr-input" dir="ltr" type="url" name="accommodation_rooms_url" value="<?= e(setting('accommodation_rooms_url')) ?>"></div><div class="form-group"><label>لینک گشت‌ها، اختیاری</label><input class="form-control ltr-input" dir="ltr" type="url" name="accommodation_tours_url" value="<?= e(setting('accommodation_tours_url')) ?>"></div><div class="form-group"><label>عنوان کارت اقامتگاه</label><input class="form-control" name="accommodation_card_title" value="<?= e(setting('accommodation_card_title')) ?>"></div><div class="form-group"><label>متن کارت اقامتگاه</label><input class="form-control" name="accommodation_card_text" value="<?= e(setting('accommodation_card_text')) ?>"></div>
<div class="form-group full check-row"><label><input type="checkbox" name="social_footer_enabled" <?= setting_bool('social_footer_enabled',true)?'checked':'' ?>> لینک‌ها پایین منو دیده شوند</label><label><input type="checkbox" name="post_order_instagram_enabled" <?= setting_bool('post_order_instagram_enabled',true)?'checked':'' ?>> بعد از سفارش، دعوت ملایم به منشن اینستاگرام نمایش داده شود</label></div>
<div class="form-group full panel-action-bar"><button class="btn btn-primary" name="action" value="public">ذخیره صفحه و لینک‌ها</button></div></form></div></section>


<div class="page-grid" style="margin-top:18px">
<section class="card settings-section" id="settingsGuestFeatures"><div class="card-head"><div><h2>امکانات مهمان</h2><small>فقط قابلیت‌هایی که مهمان در منوی عمومی می‌بیند یا استفاده می‌کند.</small></div></div><div class="card-body"><form method="post" class="form-grid"><?= csrf_field() ?>
<div class="form-group full feature-switch"><label><input type="checkbox" name="public_waiter_call_enabled" <?= setting_bool('public_waiter_call_enabled',false)?'checked':'' ?>> فراخوان گارسون از منوی عمومی با انتخاب میز</label><small>برای مهمانی که بدون QR میز وارد منوی عمومی شده است.</small></div>
<?php if($marketingModuleEnabled): ?>
<div class="form-group full feature-switch"><label><input type="checkbox" name="events_enabled" <?= setting_bool('events_enabled',true)?'checked':'' ?>> نمایش رویدادها در منوی مهمان</label></div>
<div class="form-group full feature-switch"><label><input type="checkbox" name="campaigns_enabled" <?= setting_bool('campaigns_enabled',true)?'checked':'' ?>> نمایش کمپین‌ها در منوی مهمان</label></div>
<?php else: ?>
<div class="form-group full"><div class="settings-inline-note">کمپین‌ها و رویدادها غیرفعال است؛ تنظیمات قبلی نمایش حفظ شده‌اند. برای استفاده دوباره، از <a href="modules.php">امکانات سامانه</a> آن را فعال کنید.</div></div>
<?php endif; ?>
<div class="form-group full panel-action-bar"><button class="btn btn-primary" name="action" value="guest_features">ذخیره امکانات مهمان</button></div></form></div></section>
<section class="card settings-section" id="settingsGuestMessages"><div class="card-head"><div><h2>متن‌ها و تجربه مهمان</h2><small>متن‌های منو، سبد، وضعیت سفارش، فراخوان و رویدادها در یک صفحه تخصصی مدیریت می‌شوند.</small></div></div><div class="card-body"><a class="btn btn-light" href="messages.php">مدیریت متن‌های مهمان</a></div></section>
</div>

<div class="page-grid" style="margin-top:18px">
<section class="card settings-section" id="settingsOperationalPreferences"><div class="card-head"><div><h2>نمایش و رفتار عملیات</h2><small>نمایش اطلاعات و گزینه‌های روزمره صفحه «کار روزانه».</small></div></div><div class="card-body"><div class="settings-inline-note">فعال یا غیرفعال‌کردن فراخوان مهمان از داخل صفحه «کار روزانه» انجام می‌شود.</div><form method="post" class="form-grid"><?= csrf_field() ?>
<div class="form-group full feature-switch"><label><input type="checkbox" name="show_visit_duration" <?= setting_bool('show_visit_duration',true)?'checked':'' ?>> نمایش مدت حضور میز در پنل کارکنان</label></div>
<div class="form-group"><label>هشدار دستگاه تازه بعد از چند دقیقه</label><input class="form-control" type="text" inputmode="numeric" enterkeyhint="done" name="new_device_alert_minutes" value="<?= e(fa_digits(setting('new_device_alert_minutes','20'))) ?>"><small class="muted">برای تشخیص ورود دستگاه تازه به نشست فعال میز استفاده می‌شود.</small></div>
<div class="form-group full"><div class="settings-inline-note"><strong>روز عملیاتی و شیفت‌ها</strong><br>فاکتورهای بعد از نیمه‌شب تا مرز روز عملیاتی، متعلق به روز کاری قبل می‌مانند. تغییر این تنظیم فقط روی رخدادهای بعدی اثر می‌گذارد؛ سفارش‌ها، نشست‌ها، فراخوان‌ها و تسویه‌های قبلی روز و شیفت ثبت‌شده خودشان را حفظ می‌کنند.</div></div>
<div class="form-group full"><label for="businessDayCutoff">مرز تغییر روز عملیاتی</label><input class="form-control panel-time-field" id="businessDayCutoff" type="time" name="business_day_cutoff" value="<?= e($businessCutoff) ?>" data-panel-time data-minute-step="15" step="900" aria-label="مرز تغییر روز عملیاتی" required><small class="muted">پیشنهاد فعلی برای سکنا: ۰۴:۰۰؛ چون بعد از تعطیلی شب و قبل از شروع روز بعد است.</small></div>
<div class="form-group full"><div class="business-shifts-heading"><div><label>شیفت‌های عملیاتی</label><small class="muted">حداکثر سه شیفت؛ فقط شیفت‌های فعال نمایش داده می‌شوند.</small></div><button class="btn btn-light btn-sm" type="button" id="addBusinessShift"><?= ui_icon('plus') ?> افزودن شیفت</button></div><div class="business-shift-settings" id="businessShiftSettings">
<?php foreach($businessShifts as $index=>$shift): ?><article class="business-shift-row" data-business-shift>
<input type="hidden" name="shift_key[]" value="<?= e((string)$shift['key']) ?>" data-shift-key>
<div class="business-shift-number" aria-hidden="true"><?= fa_digits($index+1) ?></div>
<label class="business-shift-name"><span>نام شیفت</span><input class="form-control" name="shift_label[]" maxlength="60" value="<?= e((string)$shift['label']) ?>" required></label>
<label><span>شروع</span><input class="form-control panel-time-field" type="time" name="shift_start[]" value="<?= e((string)$shift['start']) ?>" data-panel-time data-minute-step="15" step="900" aria-label="ساعت شروع شیفت <?= e((string)$shift['label']) ?>" required></label>
<label><span>پایان</span><input class="form-control panel-time-field" type="time" name="shift_end[]" value="<?= e((string)$shift['end']) ?>" data-panel-time data-minute-step="15" step="900" aria-label="ساعت پایان شیفت <?= e((string)$shift['label']) ?>" required></label>
<button class="icon-btn business-shift-remove" type="button" data-remove-business-shift aria-label="حذف این شیفت" title="حذف شیفت"><?= ui_icon('trash') ?></button>
</article><?php endforeach; ?>
</div><small class="muted">هم‌پوشانی یا عبور یک شیفت از مرز روز عملیاتی ذخیره نمی‌شود. حذف یک شیفت، سابقه قبلی آن را تغییر نمی‌دهد.</small></div>
<template id="businessShiftTemplate"><article class="business-shift-row" data-business-shift>
<input type="hidden" name="shift_key[]" value="" data-shift-key>
<div class="business-shift-number" aria-hidden="true"></div>
<label class="business-shift-name"><span>نام شیفت</span><input class="form-control" name="shift_label[]" maxlength="60" value="" required></label>
<label><span>شروع</span><input class="form-control panel-time-field" type="time" name="shift_start[]" value="08:00" data-panel-time data-minute-step="15" step="900" aria-label="ساعت شروع شیفت" required></label>
<label><span>پایان</span><input class="form-control panel-time-field" type="time" name="shift_end[]" value="16:00" data-panel-time data-minute-step="15" step="900" aria-label="ساعت پایان شیفت" required></label>
<button class="icon-btn business-shift-remove" type="button" data-remove-business-shift aria-label="حذف این شیفت" title="حذف شیفت"><?= ui_icon('trash') ?></button>
</article></template>
<script>
(()=>{
 const root=document.getElementById('businessShiftSettings'),add=document.getElementById('addBusinessShift'),tpl=document.getElementById('businessShiftTemplate');if(!root||!add||!tpl)return;
 const makeKey=()=>{try{return 'shift_'+crypto.randomUUID().replace(/-/g,'').slice(0,12);}catch(_e){return 'shift_'+Date.now().toString(36)+Math.random().toString(36).slice(2,8);}};
 const refresh=()=>{const rows=[...root.querySelectorAll('[data-business-shift]')];rows.forEach((row,index)=>{row.querySelector('.business-shift-number').textContent=String(index+1).replace(/[0-9]/g,d=>'۰۱۲۳۴۵۶۷۸۹'[d]);const remove=row.querySelector('[data-remove-business-shift]');if(remove)remove.disabled=rows.length<=1;});add.classList.toggle('hidden',rows.length>=3);};
 add.addEventListener('click',()=>{if(root.querySelectorAll('[data-business-shift]').length>=3)return;const fragment=tpl.content.cloneNode(true);fragment.querySelector('[data-shift-key]').value=makeKey();root.appendChild(fragment);document.dispatchEvent(new CustomEvent('panel:enhance-time',{detail:{root}}));refresh();root.lastElementChild?.querySelector('input[name="shift_label[]"]')?.focus();});
 root.addEventListener('click',event=>{const button=event.target.closest('[data-remove-business-shift]');if(!button)return;const rows=root.querySelectorAll('[data-business-shift]');if(rows.length<=1)return;button.closest('[data-business-shift]')?.remove();refresh();});
 refresh();
})();
</script>
<div class="form-group full panel-action-bar"><button class="btn btn-primary" name="action" value="operations">ذخیره تنظیمات عملیات</button></div></form></div></section>
</div>

<div class="page-grid" style="margin-top:18px">
<section class="card settings-section" id="settingsDomain"><div class="card-head"><div><h2>آدرس اصلی سامانه</h2><small>این آدرس برای QR میزها و لینک‌های خارج از سامانه استفاده می‌شود. فایل‌های داخلی از دامنه‌ای که صفحه را باز کرده بارگیری می‌شوند.</small></div></div><div class="card-body"><form method="post" class="form-grid" id="domainSettingsForm"><?= csrf_field() ?>
<div class="form-group full"><label>آدرس فعلی تشخیص‌داده‌شده</label><div class="form-control" dir="ltr" style="display:flex;align-items:center;overflow:auto"><?= e($detectedUrl?:'در محیط خط فرمان قابل تشخیص نیست') ?></div></div>
<div class="form-group full"><label>آدرس اصلی ذخیره‌شده</label><input class="form-control ltr-input" dir="ltr" type="url" name="app_canonical_url" id="appCanonicalUrl" value="<?= e($canonicalUrl) ?>" placeholder="https://example.com/Menu" required><small class="muted">آدرس کامل با http یا https؛ بدون اسلش انتهایی یا بخش اضافه بعد از نشانی.</small></div>
<div class="form-group full"><button class="btn btn-light" type="button" id="useDetectedUrl" data-url="<?= e($detectedUrl) ?>">استفاده از آدرس فعلی</button></div>
<div class="form-group full feature-switch"><label><input type="checkbox" name="confirm_domain_change" required> می‌دانم بعد از تغییر دامنه باید QRها، نصب وب‌اپ و اعلان‌ها را بررسی کنم.</label></div>
<div class="form-group full panel-action-bar"><button class="btn btn-primary" name="action" value="domain">ذخیره آدرس اصلی</button></div>
</form></div></section>
<section class="card settings-section" id="settingsIntegrationLinks"><div class="card-head"><div><h2>اتصال‌های تخصصی</h2><small>تنظیمات فنی هر سرویس در صفحه خودش نگه داشته می‌شود.</small></div></div><div class="card-body"><div class="actions"><a class="btn btn-light" href="accommodation_settings.php">تنظیم اتصال اقامتگاه</a><?php if(sokna_module_enabled('personnel')): ?><a class="btn btn-light" href="center_settings.php">تنظیم اتصال مرکز سکنا</a><?php endif; ?><a class="btn btn-light" href="printing.php">چاپ و پرینترها</a></div></div></section>
</div>
<div class="page-grid" style="margin-top:18px">
<?php $imageHealthOk=$imageHealth['gd']&&$imageHealth['webp']&&$imageHealth['jpeg']&&$imageHealth['png']&&$imageHealth['uploads_writable']; ?>
<section class="card settings-section" id="settingsHealth"><div class="card-head"><div><h2>سلامت تصاویر و سرور</h2><small>حالت سالم خلاصه نمایش داده می‌شود؛ فقط مشکل‌های نیازمند اقدام برجسته می‌شوند.</small></div></div><div class="card-body settings-health-body-v1306">
<div class="settings-health-summary-v1306 <?= $imageHealthOk?'is-ok':'has-warning' ?>"><span><?= ui_icon($imageHealthOk?'check':'warning') ?></span><div><strong><?= $imageHealthOk?'همه بررسی‌های اصلی سالم هستند':'یک یا چند مورد نیازمند بررسی است' ?></strong><small><?= $imageHealthOk?'بارگذاری و بهینه‌سازی تصویر آماده استفاده است.':'موارد مشکل‌دار در فهرست زیر مشخص شده‌اند.' ?></small></div></div>
<dl class="settings-health-list-v1306">
<div><dt>پردازش تصاویر</dt><dd class="<?= $imageHealth['gd']?'text-success':'text-danger' ?>"><?= $imageHealth['gd']?'فعال':'غیرفعال' ?></dd></div>
<div><dt>تولید WebP</dt><dd class="<?= $imageHealth['webp']?'text-success':'text-danger' ?>"><?= $imageHealth['webp']?'فعال':'غیرفعال' ?></dd></div>
<div><dt>JPEG / PNG</dt><dd class="<?= ($imageHealth['jpeg']&&$imageHealth['png'])?'text-success':'text-danger' ?>"><?= ($imageHealth['jpeg']&&$imageHealth['png'])?'پشتیبانی می‌شود':'پشتیبانی ناقص' ?></dd></div>
<div><dt>پوشه تصاویر</dt><dd class="<?= $imageHealth['uploads_writable']?'text-success':'text-danger' ?>"><?= $imageHealth['uploads_writable']?'قابل نوشتن':'غیرقابل نوشتن' ?></dd></div>
<div><dt>حداکثر حجم هر فایل</dt><dd><?= e(fa_digits((string)$imageHealth['upload_max_filesize'])) ?></dd></div>
<div><dt>حداکثر حجم کل بارگذاری</dt><dd><?= e(fa_digits((string)$imageHealth['post_max_size'])) ?></dd></div>
</dl>
<?php if(!$imageHealthOk): ?><div class="alert alert-warning">سامانه تصویر اصلی را حفظ می‌کند، اما تا رفع موارد بالا ممکن است بهینه‌سازی یا بارگذاری کامل انجام نشود.</div><?php endif; ?>
<details class="settings-health-details-v1306"><summary>جزئیات فنی</summary><dl><div><dt>GD</dt><dd><?= $imageHealth['gd']?'enabled':'disabled' ?></dd></div><div><dt>PHP memory_limit</dt><dd><?= e((string)$imageHealth['memory_limit']) ?></dd></div><div><dt>محدودیت خود سامانه</dt><dd>۴ مگابایت · حداکثر ۲۰ مگاپیکسل</dd></div></dl></details>
</div></section>
<section class="card settings-section" id="settingsSystemOptions"><div class="card-head"><div><h2>وب‌اپ و آمار منو</h2><small>تنظیمات نصب وب‌اپ و آمار کلی استفاده از منوی مهمان.</small></div></div><div class="card-body"><form method="post" class="form-grid"><?= csrf_field() ?>
<div class="form-group full feature-switch"><label><input type="checkbox" name="pwa_enabled" <?= setting_bool('pwa_enabled',true)?'checked':'' ?>> نصب و به‌روزرسانی خودکار وب‌اپ فعال باشد</label></div>
<?php if($reportingModuleEnabled): ?>
<div class="form-group full feature-switch"><label><input type="checkbox" name="analytics_enabled" <?= setting_bool('analytics_enabled',true)?'checked':'' ?>> آمار تجمیعی تعامل با منوی مهمان ثبت شود</label><small>برای گزارش‌های کلی منو؛ اطلاعات هویتی مهمان در این آمار ذخیره نمی‌شود.</small></div>
<?php else: ?>
<div class="form-group full settings-inline-note"><strong>آمار منوی مهمان متوقف است.</strong><small class="muted">«گزارش‌ها و تحلیل» خاموش است. تنظیم قبلی آمار حفظ شده و از <a href="modules.php">امکانات سامانه</a> قابل فعال‌کردن است.</small></div>
<?php endif; ?>
<div class="form-group full panel-action-bar"><button class="btn btn-primary" name="action" value="system_options">ذخیره تنظیمات سامانه</button></div></form></div></section>
<section class="card settings-section" id="settingsDeviceTools"><div class="card-head"><div><h2>دستگاه‌ها و اعلان‌ها</h2><small>مدیریت دستگاه‌های متصل و بررسی خطاهای اعلان در صفحه تخصصی انجام می‌شود.</small></div></div><div class="card-body"><a class="btn btn-light" href="push_devices.php">بازکردن دستگاه‌ها و اعلان‌ها</a></div></section>
</div>
<script>(()=>{const button=document.getElementById('useDetectedUrl');const input=document.getElementById('appCanonicalUrl');button?.addEventListener('click',()=>{if(button.dataset.url&&input){input.value=button.dataset.url;input.focus();}});})();</script>

<div class="page-grid" style="margin-top:18px"><section class="card settings-section" id="settingsSecurity"><div class="card-head"><div><h3>امنیت حساب من</h3><small>رمز حساب کاربری‌ای که الان با آن وارد شده‌ای تغییر می‌کند.</small></div></div><div class="card-body"><form method="post" class="form-grid"><?= csrf_field() ?><div class="form-group full"><label>رمز فعلی</label><input class="form-control" type="password" name="current_password" required></div><div class="form-group full"><label>رمز تازه</label><input class="form-control" type="password" name="new_password" minlength="8" required></div><div class="form-group full"><label>تکرار رمز</label><input class="form-control" type="password" name="confirm_password" minlength="8" required></div><div class="form-group full panel-action-bar"><button class="btn btn-primary" name="action" value="password">تغییر رمز</button></div></form></div></section></div>
<script src="<?= e(asset('assets/js/settings-sections.js')) ?>" defer></script>
<?php panel_footer(); ?>

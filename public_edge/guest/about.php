<?php
declare(strict_types=1);

require dirname(__DIR__).'/bootstrap.php';
require __DIR__.'/compat.php';

$installationId=trim((string)($_GET['installation_id']??public_guest_installation_id()));
if($installationId===''){http_response_code(404);exit('Not found');}
public_guest_bind_installation($installationId);
$bundle=public_guest_bundle($installationId);
$snapshot=is_array($bundle['snapshot']??null)?$bundle['snapshot']:[];
if(!$snapshot){http_response_code(404);exit('Not found');}
$GLOBALS['soknaPublicGuestContext']=[
    'installation_id'=>$installationId,'snapshot'=>$snapshot,
    'media_manifest'=>is_array($bundle['media_manifest']??null)?$bundle['media_manifest']:[],
    'availability'=>is_array($bundle['availability']??null)?$bundle['availability']:[],
    'bundle'=>$bundle,
];

if(!setting_bool('public_about_enabled',true)){http_response_code(404);exit('Not found');}
$tableToken=trim((string)($_GET['table']??''));
$menuKey=trim((string)($_GET['menu']??''));
$tableContext=$tableToken!==''?public_guest_table_from_token($snapshot,$tableToken):null;
$invalidTableContext=$tableToken!==''&&!$tableContext;
$params=[];if($tableContext)$params['table']=(string)$tableContext['token'];
if($menuKey!==''&&preg_match('/^[A-Za-z0-9._-]{1,80}$/',$menuKey))$params['menu']=$menuKey;
$menuReturnUrl=guest_page_url('menu/',$params);

$font=menu_font();$name=setting('cafe_name','سکنا');$title=setting('about_title','درباره سکنا');
$description=setting('seo_description','منوی کافه، رویدادها و راه‌های ارتباط با سکنا.');
$logo=setting('logo_path');$primary=valid_hex_color(setting('primary_color','#365b4c'),'#365b4c');
$accent=valid_hex_color(setting('accent_color','#b85c38'),'#b85c38');
$background=valid_hex_color(setting('background_color','#f7f3ec'),'#f7f3ec');
$themeData=is_array($snapshot['theme']??null)?$snapshot['theme']:[];
$social=setting_bool('social_footer_enabled',true)&&is_array($themeData['social_links']??null)?$themeData['social_links']:[];
$site=safe_external_url(setting('accommodation_site_url'));
?>
<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="description" content="<?= e($description) ?>"><meta name="theme-color" content="<?= e($primary) ?>"><link rel="canonical" href="<?= e(guest_page_url('about.php')) ?>"><title><?= e($title.' | '.$name) ?></title><?= ui_font_head($font) ?><link rel="stylesheet" href="<?= e(asset('assets/css/tokens.css')) ?>"><link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>"><link rel="stylesheet" href="<?= e(asset('assets/css/responsive.css')) ?>"><link rel="stylesheet" href="<?= e(asset('assets/css/public-page.css')) ?>"><style>:root{--font-ui:<?= ui_font_family($font) ?>;--primary:<?= e($primary) ?>;--accent:<?= e($accent) ?>;--app-bg:<?= e($background) ?>;--on-primary:<?= e(contrast_text_color($primary)) ?>;--on-accent:<?= e(contrast_text_color($accent)) ?>}</style></head><body class="public-page theme-<?= e(menu_theme()) ?> font-<?= e($font) ?>"><main class="public-shell"><?php if($invalidTableContext): ?><div class="alert alert-warning" role="alert"><?= ui_icon('warning') ?><div><strong>لینک میز معتبر نیست</strong><p>برای ادامه سفارش، QR میز فعلی را دوباره اسکن کن.</p></div></div><?php endif; ?><header class="public-hero"><a class="public-brand" href="<?= e($menuReturnUrl) ?>"><?php if($logo): $logoData=responsive_image_data($logo); ?><img src="<?= e($logoData['src']) ?>" width="<?= (int)$logoData['width'] ?>" height="<?= (int)$logoData['height'] ?>" alt="لوگوی <?= e($name) ?>"><?php else: ?><span><?= ui_icon('coffee') ?></span><?php endif; ?><strong><?= e($name) ?></strong></a><div><span>آشنایی بیشتر</span><h1><?= e($title) ?></h1><p><?= nl2br(e(setting('about_intro','سکنا جایی برای قهوه، گفت‌وگو و تجربه‌های نزدیکه.'))) ?></p></div></header><section class="public-info-grid"><?php if(setting('public_address')): ?><article><span>نشانی</span><p><?= nl2br(e(setting('public_address'))) ?></p></article><?php endif; ?><?php if(setting('public_phone')): ?><article><span>تماس</span><a href="tel:<?= e(preg_replace('/[^0-9+]/','',en_digits(setting('public_phone')))) ?>" dir="ltr"><?= e(fa_digits(setting('public_phone'))) ?></a></article><?php endif; ?><article><span><?= $tableContext?'منوی میز':'منوی کافه' ?></span><a href="<?= e($menuReturnUrl) ?>"><?= $tableContext?'بازگشت به میز':'دیدن منوی عمومی' ?></a></article></section><?php if(setting_bool('accommodation_enabled',true)&&$site): ?><section class="public-accommodation"><div><span>بیشتر از سکنا</span><h2><?= e(setting('accommodation_card_title','خانه سکنا رو هم می‌شناسی؟')) ?></h2><p><?= e(setting('accommodation_card_text','اقامت، گشت‌وگذار و تجربه غرب هرمزگان')) ?></p></div><div class="actions"><a class="btn btn-primary" target="_blank" rel="noopener" href="<?= e($site) ?>">سایت خانه سکنا</a><?php if($rooms=safe_external_url(setting('accommodation_rooms_url'))): ?><a class="btn btn-light" target="_blank" rel="noopener" href="<?= e($rooms) ?>">اتاق‌ها</a><?php endif; ?><?php if($tours=safe_external_url(setting('accommodation_tours_url'))): ?><a class="btn btn-light" target="_blank" rel="noopener" href="<?= e($tours) ?>">گشت‌ها</a><?php endif; ?></div></section><?php endif; ?><?php if($social): ?><section class="public-social"><span>راه‌های ارتباط</span><div class="social-link-grid"><?php foreach($social as $link): ?><a target="_blank" rel="noopener" href="<?= e((string)$link['url']) ?>"><span><?= ui_icon((string)$link['icon']) ?></span><strong><?= e((string)$link['label']) ?></strong></a><?php endforeach; ?></div></section><?php endif; ?><footer><a href="<?= e($menuReturnUrl) ?>">بازگشت به منو</a></footer></main></body></html>
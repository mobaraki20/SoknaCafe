<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';

$h=static fn(mixed $v):string=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$installationId=trim((string)($_GET['installation_id']??''));
$tableToken=trim((string)($_GET['table']??''));
$requestedMenu=trim((string)($_GET['menu']??''));
if($installationId===''){http_response_code(404);exit('Not found');}

$bundle=public_guest_bundle($installationId);
$snapshot=is_array($bundle['snapshot']??null)?$bundle['snapshot']:[];
if(!$snapshot){
    http_response_code(503);
    ?><!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>منوی سکنا</title><style>body{font-family:Tahoma,Arial,sans-serif;background:#f7f3ec;display:grid;place-items:center;min-height:100vh;margin:0;color:#26312b}.box{background:#fff;padding:28px;border-radius:20px;max-width:480px;text-align:center;border:1px solid #e7ded3}</style><body><div class="box"><h1>منوی عمومی هنوز منتشر نشده</h1><p>لطفاً کمی بعد دوباره امتحان کنید.</p></div></body></html><?php
    exit;
}
$table=$tableToken!==''?public_guest_table_from_token($snapshot,$tableToken):null;
if($tableToken!==''&&!$table){
    http_response_code(404);
    ?><!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>QR نامعتبر</title><body style="font-family:Tahoma,Arial,sans-serif;background:#f7f3ec;display:grid;place-items:center;min-height:100vh;margin:0"><main style="background:white;padding:28px;border-radius:20px;text-align:center"><h1>این QR معتبر نیست</h1><p>QR موجود روی میز را دوباره اسکن کنید.</p></main></body></html><?php
    exit;
}

$menus=is_array($snapshot['menus']??null)?$snapshot['menus']:[];
$catalogs=is_array($snapshot['catalogs']??null)?$snapshot['catalogs']:[];
$selectedKey=$requestedMenu!==''&&isset($catalogs[$requestedMenu])?$requestedMenu:(string)($menus[0]['menu_key']??'');
$catalog=is_array($catalogs[$selectedKey]??null)?$catalogs[$selectedKey]:['categories'=>[],'items'=>[]];
$categories=is_array($catalog['categories']??null)?$catalog['categories']:[];
$items=is_array($catalog['items']??null)?$catalog['items']:[];
$manifest=is_array($bundle['media_manifest']??null)?$bundle['media_manifest']:[];
$availability=is_array($bundle['availability']??null)?$bundle['availability']:[];
$availabilityItems=is_array($availability['items']??null)?$availability['items']:[];
$theme=is_array($snapshot['theme']??null)?$snapshot['theme']:[];
$features=is_array($snapshot['features']??null)?$snapshot['features']:[];
$action=public_guest_action_state($bundle);
$orderAcceptance=is_array($availability['order_acceptance']??null)?$availability['order_acceptance']:[];
$canOrder=(bool)$table&&$action['enabled']&&!empty($orderAcceptance['cafe']);
$waiterEnabled=$action['enabled']&&($table?!empty($availability['waiter_enabled_table']):!empty($availability['waiter_enabled_public']));

$primary=preg_match('/^#[0-9a-fA-F]{6}$/',(string)($theme['primary_color']??''))?(string)$theme['primary_color']:'#365b4c';
$accent=preg_match('/^#[0-9a-fA-F]{6}$/',(string)($theme['accent_color']??''))?(string)$theme['accent_color']:'#b85c38';
$background=preg_match('/^#[0-9a-fA-F]{6}$/',(string)($theme['background_color']??''))?(string)$theme['background_color']:'#f7f3ec';
$cafeName=(string)($theme['cafe_name']??'سکنا');
$logoPath=(string)($theme['logo_path']??'');
$logoUrl=$logoPath!==''?public_guest_media_url($installationId,$manifest,$logoPath):'';
$mediaUrl=static function(string $path)use($installationId,$manifest):string{return $path!==''?public_guest_media_url($installationId,$manifest,$path):'';};
$byCategory=[];foreach($categories as $cat)$byCategory[(int)$cat['id']]=[];
foreach($items as $item)$byCategory[(int)($item['category_id']??0)][]=$item;
$publicTables=[];
foreach(($snapshot['tables']??[]) as $row){
    if(!is_array($row))continue;
    $publicTables[]=['ref'=>(string)($row['public_ref']??''),'name'=>(string)($row['name']??''),'number'=>(int)($row['table_number']??0)];
}
$degraded=!$action['enabled'];
?><!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<?php if($tableToken!==''): ?><meta name="robots" content="noindex,nofollow,noarchive"><?php endif; ?>
<meta name="theme-color" content="<?= $h($primary) ?>">
<meta name="description" content="<?= $h((string)($theme['seo_description']??'منوی کافه')) ?>">
<title><?= $h($cafeName) ?> | منو</title>
<style>
:root{--p:<?= $h($primary) ?>;--a:<?= $h($accent) ?>;--bg:<?= $h($background) ?>;--ink:#202522;--muted:#69716d;--card:#fff;--line:#e5dfd6}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font-family:Vazirmatn,Tahoma,Arial,sans-serif;min-height:100vh}.wrap{width:min(920px,100%);margin:auto;padding:16px 16px 110px}.head{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:14px 0 18px}.brand{display:flex;align-items:center;gap:12px}.logo{width:56px;height:56px;border-radius:18px;background:#fff;border:1px solid var(--line);display:grid;place-items:center;overflow:hidden;font-size:26px}.logo img{width:100%;height:100%;object-fit:cover}.brand strong{display:block;font-size:1.18rem}.brand small{color:var(--muted)}.table{background:#fff;border:1px solid var(--line);padding:8px 12px;border-radius:999px;font-weight:800}.notice{padding:11px 14px;border-radius:14px;background:#fff8e6;border:1px solid #eed8a3;color:#725a20;margin-bottom:14px;line-height:1.8}.status{position:sticky;top:8px;z-index:8;padding:10px 13px;border-radius:13px;background:#edf8f1;border:1px solid #cfe7d7;margin-bottom:12px}.status.bad{background:#fff0ee;border-color:#f2c9c3}.hidden{display:none!important}.menus{display:flex;gap:8px;overflow:auto;padding-bottom:8px}.menus a{white-space:nowrap;text-decoration:none;color:var(--ink);background:#fff;border:1px solid var(--line);border-radius:999px;padding:9px 14px}.menus a.active{background:var(--p);color:#fff;border-color:var(--p)}.category{margin:24px 0}.category h2{font-size:1.08rem;margin:0 0 12px}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.item{background:var(--card);border:1px solid var(--line);border-radius:18px;overflow:hidden;display:flex;flex-direction:column;min-width:0}.item img{width:100%;aspect-ratio:4/3;object-fit:cover;background:#eee}.item-body{padding:12px;display:flex;flex-direction:column;gap:7px;flex:1}.item h3{font-size:1rem;margin:0}.item p{font-size:.85rem;color:var(--muted);line-height:1.7;margin:0}.price{margin-top:auto;font-weight:900}.tags{display:flex;gap:5px;flex-wrap:wrap}.tag{font-size:.72rem;background:#f3f0eb;border-radius:999px;padding:3px 7px}.add{margin-top:5px;border:0;background:var(--p);color:#fff;border-radius:12px;min-height:42px;font-weight:800}.add:disabled{background:#aaa}.unavailable{font-size:.75rem;color:#9a3d35}.waiter{margin:22px 0;background:#fff;border:1px solid var(--line);border-radius:18px;padding:14px}.waiter-row{display:flex;gap:8px;align-items:center}.waiter select{flex:1;padding:11px;border:1px solid var(--line);border-radius:12px;background:#fff}.waiter button{border:0;background:var(--a);color:#fff;padding:11px 15px;border-radius:12px;font-weight:800}.cart-bar{position:fixed;bottom:12px;left:50%;transform:translateX(-50%);width:min(620px,calc(100% - 24px));background:#17251e;color:#fff;border-radius:18px;padding:8px;display:flex;align-items:center;justify-content:space-between;box-shadow:0 16px 45px #0004;z-index:20}.cart-bar button{border:0;background:var(--p);color:#fff;padding:12px 16px;border-radius:13px;font-weight:900}.cart-panel{position:fixed;inset:0;background:#0006;z-index:30;display:grid;place-items:end center}.cart-box{width:min(620px,100%);max-height:85vh;overflow:auto;background:#fff;border-radius:24px 24px 0 0;padding:18px}.cart-head{display:flex;justify-content:space-between;align-items:center}.cart-head button{border:0;background:#eee;width:42px;height:42px;border-radius:50%}.cart-row{display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid var(--line);gap:12px}.cart-row span{display:flex;align-items:center;gap:8px}.cart-row span:first-child{display:block}.cart-row small{display:block;color:var(--muted)}.cart-row button{width:34px;height:34px;border:0;border-radius:10px}.total{display:flex;justify-content:space-between;padding:16px 0;font-weight:900}.submit{width:100%;border:0;background:var(--p);color:#fff;border-radius:14px;min-height:50px;font-weight:900;font-size:1rem}.submit:disabled{background:#aaa}@media(max-width:520px){.grid{grid-template-columns:1fr}.wrap{padding-inline:12px}.head{align-items:flex-start}.item{flex-direction:row}.item img{width:108px;aspect-ratio:1/1}.item-body{padding:10px}}
</style>
</head>
<body>
<main class="wrap">
<header class="head"><div class="brand"><div class="logo"><?php if($logoUrl!==''): ?><img src="<?= $h($logoUrl) ?>" alt="لوگوی <?= $h($cafeName) ?>"><?php else: ?>☕<?php endif; ?></div><div><strong><?= $h($cafeName) ?></strong><small>منوی مهمان</small></div></div><?php if($table): ?><span class="table"><?= $h((string)$table['name']) ?></span><?php endif; ?></header>
<div id="guestStatus" class="status hidden" role="status"></div>
<?php if($degraded): ?><div class="notice">منو قابل مشاهده است، اما ارتباط زنده با کافه موقتاً در دسترس نیست؛ سفارش و فراخوان تا برقراری دوباره اتصال غیرفعال است.</div><?php endif; ?>
<?php if(count($menus)>1): ?><nav class="menus"><?php foreach($menus as $m): $key=(string)$m['menu_key']; ?><a class="<?= $key===$selectedKey?'active':'' ?>" href="?installation_id=<?= rawurlencode($installationId) ?><?= $tableToken!==''?'&table='.rawurlencode($tableToken):'' ?>&menu=<?= rawurlencode($key) ?>"><?= $h((string)$m['name']) ?></a><?php endforeach; ?></nav><?php endif; ?>

<?php foreach($categories as $cat): $cid=(int)$cat['id']; $rows=$byCategory[$cid]??[]; if(!$rows)continue; ?>
<section class="category"><h2><?= $h((string)$cat['name']) ?></h2><div class="grid">
<?php foreach($rows as $item):
$id=(int)$item['id'];$live=is_array($availabilityItems[(string)$id]??null)?$availabilityItems[(string)$id]:[];
$available=array_key_exists('available',$live)?(bool)$live['available']:(bool)($item['available']??false);
$orderable=$canOrder&&$available&&!empty($live['order_available']);
$img=$mediaUrl((string)($item['image_path']??''));
?>
<article class="item"><?php if($img!==''): ?><img loading="lazy" decoding="async" src="<?= $h($img) ?>" alt="<?= $h((string)$item['name']) ?>"><?php endif; ?><div class="item-body"><h3><?= $h((string)$item['name']) ?></h3><?php if(trim((string)($item['description']??''))!==''): ?><p><?= $h((string)$item['description']) ?></p><?php endif; ?><?php if(!empty($item['tags'])): ?><div class="tags"><?php foreach($item['tags'] as $tag): ?><span class="tag"><?= $h((string)($tag['title']??'')) ?></span><?php endforeach; ?></div><?php endif; ?><span class="price"><?= $h(number_format((int)$item['price'])) ?> تومان</span><?php if(!$available): ?><span class="unavailable">فعلاً موجود نیست</span><?php endif; ?><?php if($table): ?><button class="add" data-add="<?= $id ?>" data-name="<?= $h((string)$item['name']) ?>" data-price="<?= (int)$item['price'] ?>" <?= !$orderable?'disabled':'' ?>>افزودن</button><?php endif; ?></div></article>
<?php endforeach; ?>
</div></section>
<?php endforeach; ?>

<?php if($waiterEnabled): ?><section class="waiter"><strong>فراخوان گارسون</strong><div class="waiter-row"><?php if(!$table): ?><select id="waiterTable" aria-label="انتخاب میز"><option value="">میز را انتخاب کنید</option><?php foreach($publicTables as $t): if($t['ref']==='')continue; ?><option value="<?= $h($t['ref']) ?>"><?= $h($t['name']) ?></option><?php endforeach; ?></select><?php endif; ?><button id="waiterButton" type="button">خبرش کن</button></div></section><?php endif; ?>
</main>

<?php if($table): ?><div class="cart-bar hidden" id="cartBar"><span><b id="cartCount">۰</b> آیتم</span><button id="openCart" type="button">دیدن سبد</button></div>
<div class="cart-panel hidden" id="cartPanel"><section class="cart-box"><div class="cart-head"><h2>سبد سفارش</h2><button id="closeCart" type="button">×</button></div><div id="cartList"></div><div class="total"><span>جمع</span><span id="cartTotal">۰ تومان</span></div><button class="submit" id="submitOrder" type="button" <?= !$canOrder?'disabled':'' ?>>ثبت سفارش</button></section></div><?php endif; ?>
<script>
window.SOKNA_GUEST=<?= json_encode([
'installationId'=>$installationId,
'tableToken'=>$tableToken,
'canOrder'=>$canOrder,
'enqueueUrl'=>'/api/v1/guest/enqueue.php',
'resultUrl'=>'/api/v1/guest/result.php',
],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
</script>
<script src="/guest/app.js"></script>
</body></html>

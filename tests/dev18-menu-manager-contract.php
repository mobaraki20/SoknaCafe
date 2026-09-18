<?php
declare(strict_types=1);
require dirname(__DIR__).'/includes/functions.php';
function check(bool $ok,string $message):void{if(!$ok){fwrite(STDERR,"FAIL: $message\n");exit(1);}}
check(normalize_persian_search('سیب‌زمینی')===normalize_persian_search('سیب زمینی'),'hyphen/space normalization');
check(normalize_persian_search('سیب‌زمینی')===normalize_persian_search("سیب\u{200C}زمینی"),'ZWNJ normalization');
$root=dirname(__DIR__);
$items=file_get_contents($root.'/admin/items.php');
$itemForm=file_get_contents($root.'/admin/item_form.php');
$categoryForm=file_get_contents($root.'/admin/category_form.php');
$catalog=file_get_contents($root.'/includes/menu_catalog.php');
$js=file_get_contents($root.'/assets/js/items-management.js');
$quick=file_get_contents($root.'/assets/js/staff-quick-order.js');
$css=file_get_contents($root.'/assets/css/items-management.css');
check(str_contains($items,'item_tags its JOIN tags'),'admin search includes tags');
check(!str_contains($js,'scheduleSearch')&&!str_contains($js,'420'),'search does not auto reload during Persian typing');
check(str_contains($catalog,'function menu_catalog_set_item_memberships'),'explicit item membership owner exists');
check(str_contains($items,'data-quick-item-menu')&&str_contains($items,'data-item-menu-badge'),'quick edit and rows expose menu membership');
check(str_contains($itemForm,'name="menu_ids[]"')&&str_contains($itemForm,'menu_catalog_set_item_memberships'),'full editor owns menu membership');
check(str_contains($categoryForm,'DELETE FROM menu_items WHERE item_id IN'),'category membership prunes stale item placements');
check(str_contains($quick,'منوی فعال: <strong>'),'single active menu context remains visible in quick order');
check(str_contains($css,'.item-editor-backdrop{display:none}')&&str_contains($css,'.quick-item-flags label')&&str_contains($css,'min-height:44px'),'desktop drawer/touch target contract');
check(!preg_match('/\.item-filter-bar\{[^}]*position:sticky/s',$css),'filter bar does not obscure desktop content');
echo "dev18 menu manager contract PASS\n";

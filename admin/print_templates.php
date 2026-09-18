<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
require_login(['admin']);
require dirname(__DIR__) . '/includes/panel_layout.php';

$pdo = db();
if (!print_tables_available($pdo) || !print_templates_available($pdo)) {
    panel_header('قالب‌های چاپ','printing');
    echo '<div class="alert alert-error">ساختار قالب‌های چاپ روی دیتابیس نصب نشده است.</div>';
    panel_footer();
    exit;
}
$key = in_array((string)($_GET['key'] ?? $_POST['template_key'] ?? 'customer'),['customer','preparation'],true) ? (string)($_GET['key'] ?? $_POST['template_key'] ?? 'customer') : 'customer';
$userId = (int)(current_user()['id'] ?? 0);

function print_template_ensure_builtin_v2(PDO $pdo, string $key): void
{
    $default=print_template_defaults($key);$json=json_encode($default,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$hash=hash('sha256',$json);
    $stmt=$pdo->prepare('INSERT IGNORE INTO print_templates(template_key,name,template_version,origin,revision,definition_json,definition_sha256,active,active_guard,created_by_user_id) VALUES(?,?,?,\'builtin\',1,?,?,0,NULL,NULL)');
    $stmt->execute([$key,$default['name'],$default['version'],$json,$hash]);
}

function print_template_definition_from_post(string $key): array
{
    $defaults = print_template_defaults($key);
    $definition = $defaults;
    $definition['name'] = text_substr(trim((string)($_POST['name'] ?? $defaults['name'])),0,160);
    $definition['version'] = 'design-' . date('Ymd-His') . '-' . substr(bin2hex(random_bytes(3)),0,6);
    $definition['paper_width_mm'] = (int)($_POST['paper_width_mm'] ?? 80);
    foreach (['base_font_size','title_font_size','table_font_size','line_spacing','margin'] as $field) $definition[$field] = (int)en_digits((string)($_POST[$field] ?? $defaults[$field]));
    foreach (['show_actor','show_time','show_order_number','show_section_titles'] as $flag) $definition[$flag] = isset($_POST[$flag]);
    $definition['show_prices'] = $key === 'customer';
    $definition['footer'] = (string)($_POST['footer'] ?? $defaults['footer']);
    $design = $defaults['design'];
    foreach (['density','header_alignment','separator_style','item_layout'] as $field) $design[$field] = (string)($_POST['design'][$field] ?? $design[$field]);
    $order = json_decode((string)($_POST['section_order'] ?? '[]'),true);
    if (is_array($order)) $design['section_order'] = $order;
    foreach ($design['labels'] as $labelKey=>$value) $design['labels'][$labelKey] = (string)($_POST['labels'][$labelKey] ?? $value);
    $definition['design'] = $design;
    return print_template_validate($definition,$key);
}

function print_template_next_revision(PDO $pdo,string $key): int
{
    if(!$pdo->inTransaction())throw new LogicException('شماره revision باید داخل تراکنش ساخته شود.');
    $stmt=$pdo->prepare('SELECT revision FROM print_templates WHERE template_key=? ORDER BY id FOR UPDATE');$stmt->execute([$key]);$max=0;foreach($stmt->fetchAll(PDO::FETCH_COLUMN) as $revision)$max=max($max,(int)$revision);return $max+1;
}
function print_template_insert(PDO $pdo,string $key,array $definition,string $origin,bool $active,int $userId): int
{
    if(!in_array($origin,['builtin','imported','custom'],true))throw new RuntimeException('منشأ قالب معتبر نیست.');
    $revision=print_template_next_revision($pdo,$key);$json=json_encode($definition,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$hash=hash('sha256',$json);
    if($active)$pdo->prepare('UPDATE print_templates SET active=0,active_guard=NULL WHERE template_key=?')->execute([$key]);
    $pdo->prepare('INSERT INTO print_templates(template_key,name,template_version,origin,revision,definition_json,definition_sha256,active,active_guard,created_by_user_id) VALUES(?,?,?,?,?,?,?,?,?,?)')
        ->execute([$key,$definition['name'],$definition['version'],$origin,$revision,$json,$hash,$active?1:0,$active?$key:null,$userId?:null]);return (int)$pdo->lastInsertId();
}

function print_template_export_zip(array $definition): never
{
    if (!class_exists('ZipArchive')) throw new RuntimeException('ساخت ZIP روی سرور در دسترس نیست.');
    $key = (string)$definition['template_key'];
    $tmp = tempnam(sys_get_temp_dir(),'sokna-template-');
    if ($tmp === false) throw new RuntimeException('فایل موقت برای خروجی قالب ساخته نشد.');
    $zip = new ZipArchive();
    if ($zip->open($tmp,ZipArchive::OVERWRITE) !== true) { @unlink($tmp); throw new RuntimeException('بسته خروجی ساخته نشد.'); }
    $manifest = ['format'=>'sokna-print-template-package-v2','template_key'=>$key,'name'=>$definition['name'],'version'=>$definition['version'],'paper_width_mm'=>$definition['paper_width_mm']];
    $styles = $definition; unset($styles['name'],$styles['version'],$styles['template_key'],$styles['package_format']);
    $sample = print_template_sample_data($key,'default');
    $contract = $key === 'preparation' ? 'preparation-ticket-v2' : 'customer-receipt-v2';
    $zip->addFromString('manifest.json',json_encode($manifest,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    $zip->addFromString('template.xml','<sokna-print-template layout="'.$contract.'"/>');
    $zip->addFromString('styles.json',json_encode($styles,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    $zip->addFromString('sample-data.json',json_encode($sample,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    $zip->close();
    $filename = 'sokna-'.$key.'-template-'.$definition['version'].'.zip';
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    header('Content-Length: '.filesize($tmp));
    readfile($tmp); @unlink($tmp); exit;
}

foreach (['customer','preparation'] as $ensureKey) print_template_ensure_builtin_v2($pdo,$ensureKey);

if (isset($_GET['download'])) {
    $definition = print_template_active($pdo,$key);
    if ($_GET['download'] === 'zip') print_template_export_zip($definition);
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="sokna-'.$key.'-template-'.$definition['version'].'.json"');
    echo json_encode($definition,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? null);
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'save_design') {
            $definition=print_template_definition_from_post($key);$activate=isset($_POST['activate']);$pdo->beginTransaction();$pdo->query("SELECT id FROM print_templates WHERE template_key=".$pdo->quote($key)." ORDER BY id FOR UPDATE")->fetchAll();$id=print_template_insert($pdo,$key,$definition,'custom',$activate,$userId);audit_log_write_strict($pdo,'print_template_saved','print_template',$id,['template_key'=>$key,'version'=>$definition['version'],'origin'=>'custom','activated'=>$activate],$userId);$pdo->commit();flash('success',$activate?'قالب جدید ذخیره و فعال شد.':'قالب جدید به‌صورت پیش‌نویس ذخیره شد.');
        } elseif ($action === 'import_template') {
            $file=$_FILES['template_file']??null;if(!$file||($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new RuntimeException('فایل قالب را انتخاب کنید.');$tmp=(string)$file['tmp_name'];$name=strtolower((string)$file['name']);$uploadSize=(int)($file['size']??0);
            if($uploadSize<1||$uploadSize>1048576)throw new RuntimeException('حجم فایل قالب معتبر نیست.');
            if(str_ends_with($name,'.zip'))$definition=print_template_package_load($tmp,$key);else{if($uploadSize>262144)throw new RuntimeException('فایل JSON قالب بیش از حد مجاز است.');$raw=file_get_contents($tmp,false,null,0,262145);if(!is_string($raw)||strlen($raw)>262144)throw new RuntimeException('فایل JSON قالب بیش از حد مجاز است.');$decoded=json_decode($raw,true,128,JSON_THROW_ON_ERROR);if(!is_array($decoded)||array_is_list($decoded))throw new RuntimeException('ساختار قالب باید JSON object باشد.');$definition=print_template_validate($decoded,$key);}
            $sourceVersion=(string)$definition['version'];$definition['source_version']=$sourceVersion;$definition['version']='import-'.date('Ymd-His').'-'.substr(bin2hex(random_bytes(4)),0,8);$definition=print_template_validate($definition,$key);
            $pdo->beginTransaction();$pdo->query("SELECT id FROM print_templates WHERE template_key=".$pdo->quote($key)." ORDER BY id FOR UPDATE")->fetchAll();$id=print_template_insert($pdo,$key,$definition,'imported',true,$userId);audit_log_write_strict($pdo,'print_template_imported','print_template',$id,['template_key'=>$key,'version'=>$definition['version'],'source_version'=>$sourceVersion,'origin'=>'imported','package_format'=>$definition['package_format']],$userId);$pdo->commit();flash('success','قالب وارد و فعال شد. منشأ و نسخه فایل جدا از شناسه داخلی نگهداری می‌شود.');
        } elseif ($action === 'activate_template') {
            $id=(int)($_POST['template_id']??0);$st=$pdo->prepare('SELECT template_key FROM print_templates WHERE id=?');$st->execute([$id]);$rowKey=(string)($st->fetchColumn()?:'');if($rowKey!==$key)throw new RuntimeException('قالب انتخاب‌شده معتبر نیست.');
            $pdo->beginTransaction();$pdo->query("SELECT id FROM print_templates WHERE template_key=".$pdo->quote($key)." ORDER BY id FOR UPDATE")->fetchAll();$check=$pdo->prepare('SELECT id,name,active FROM print_templates WHERE id=? AND template_key=?');$check->execute([$id,$key]);$target=$check->fetch();if(!$target)throw new RuntimeException('قالب پیدا نشد.');if((int)$target['active']===1){$pdo->commit();flash('info','همین قالب از قبل فعال است.');}else{$pdo->prepare('UPDATE print_templates SET active=0,active_guard=NULL WHERE template_key=?')->execute([$key]);$pdo->prepare('UPDATE print_templates SET active=1,active_guard=? WHERE id=?')->execute([$key,$id]);audit_log_write_strict($pdo,'print_template_activated','print_template',$id,['template_key'=>$key,'name'=>(string)$target['name']],$userId);$pdo->commit();flash('success','قالب انتخاب‌شده فعال شد.');}
        } elseif ($action === 'delete_template') {
            $id=(int)($_POST['template_id']??0);$pdo->beginTransaction();$pdo->query("SELECT id FROM print_templates WHERE template_key=".$pdo->quote($key)." ORDER BY id FOR UPDATE")->fetchAll();$st=$pdo->prepare('SELECT * FROM print_templates WHERE id=? AND template_key=?');$st->execute([$id,$key]);$row=$st->fetch();if(!$row)throw new RuntimeException('قالب پیدا نشد.');
            $default=print_template_defaults($key);if((string)$row['origin']==='builtin'&&(string)$row['template_version']===(string)$default['version'])throw new RuntimeException('استاندارد جاری از کد قابل بازیابی است؛ برای کنارگذاشتن سفارشی‌ها «بازگشت به استاندارد» را بزنید.');
            if((int)$row['active']===1){print_template_ensure_builtin_v2($pdo,$key);$replacement=$pdo->prepare("SELECT id FROM print_templates WHERE template_key=? AND id<>? ORDER BY (origin='builtin' AND template_version=?) DESC,id DESC LIMIT 1");$replacement->execute([$key,$id,$default['version']]);$replacementId=(int)$replacement->fetchColumn();if($replacementId<1)throw new RuntimeException('قالب جایگزین امن پیدا نشد.');$pdo->prepare('UPDATE print_templates SET active=0,active_guard=NULL WHERE id=?')->execute([$id]);$pdo->prepare('UPDATE print_templates SET active=1,active_guard=? WHERE id=?')->execute([$key,$replacementId]);}
            $pdo->prepare('DELETE FROM print_templates WHERE id=?')->execute([$id]);audit_log_write_strict($pdo,'print_template_deleted','print_template',$id,['template_key'=>$key,'version'=>$row['template_version'],'origin'=>$row['origin'],'definition_sha256'=>$row['definition_sha256']],$userId);$pdo->commit();flash('success','قالب حذف شد و در صورت فعال‌بودن، جایگزین در همان تراکنش فعال شد.');
        } elseif ($action === 'reset_template') {
            $default=print_template_defaults($key);print_template_ensure_builtin_v2($pdo,$key);$pdo->beginTransaction();$pdo->query("SELECT id FROM print_templates WHERE template_key=".$pdo->quote($key)." ORDER BY id FOR UPDATE")->fetchAll();$pdo->prepare('UPDATE print_templates SET active=0,active_guard=NULL WHERE template_key=?')->execute([$key]);$st=$pdo->prepare('UPDATE print_templates SET active=1,active_guard=? WHERE template_key=? AND template_version=?');$st->execute([$key,$key,$default['version']]);if(!$st->rowCount())throw new RuntimeException('قالب استاندارد پیدا نشد.');$pdo->commit();audit_log_write('print_template_reset','print_template',$key,['active_version'=>$default['version']],$userId);flash('success','قالب استاندارد چاپ فعال شد.');
        } else throw new RuntimeException('عملیات قالب چاپ معتبر نیست.');
    } catch(Throwable $e) { if($pdo->inTransaction())$pdo->rollBack(); error_log('print template admin: '.$e->getMessage()); flash('error',safe_business_error_message($e,'عملیات قالب چاپ انجام نشد.')); }
    redirect('print_templates.php?key='.$key);
}

$active = print_template_active($pdo,$key);
$historyStmt=$pdo->prepare('SELECT pt.*,u.display_name created_by FROM print_templates pt LEFT JOIN users u ON u.id=pt.created_by_user_id WHERE pt.template_key=? ORDER BY pt.id DESC');$historyStmt->execute([$key]);$history=$historyStmt->fetchAll();
$originLabels=['builtin'=>'استاندارد سکنا','imported'=>'واردشده','custom'=>'سفارشی'];
$sampleScenarios = $key==='customer' ? ['default'=>print_template_sample_data($key,'default'),'discount'=>print_template_sample_data($key,'discount')] : ['default'=>print_template_sample_data($key,'default'),'adjustment'=>print_template_sample_data($key,'adjustment'),'cancel'=>print_template_sample_data($key,'cancel')];
$previewDestinationStmt=$pdo->prepare('SELECT destination_key,label,paper_width_mm,printable_width_mm FROM print_destinations WHERE active=1 AND destination_type=? ORDER BY destination_key');$previewDestinationStmt->execute([$key==='customer'?'customer':'preparation']);$previewDestinations=$previewDestinationStmt->fetchAll();
$sectionLabels = $key==='customer' ? ['brand'=>'هویت کافه','meta'=>'مشخصات سند','items'=>'اقلام','summary'=>'جمع مالی','settlement'=>'نحوه ثبت','footer'=>'پاورقی'] : ['status'=>'نوع فیش','meta'=>'میز و سفارش','items'=>'اقلام آماده‌سازی','notes'=>'یادداشت','footer'=>'پاورقی'];
panel_header('طراحی قالب‌های چاپ','printing');
?>
<div class="panel-page-flow">
<div class="toolbar"><div class="panel-copy-stack"><strong>قالب‌های چاپ</strong><small class="muted">قالب را پیش‌نمایش کن، فعال کن یا به‌صورت امن منتقل کن. فایل اجرایی داخل قالب پذیرفته نمی‌شود.</small></div><a class="btn btn-light" href="printing.php">بازگشت به چاپ و پرینترها</a></div>
<nav class="panel-subnav print-template-switch" aria-label="نوع قالب"><a class="<?= $key==='customer'?'is-active':'' ?>" href="?key=customer">رسید مشتری</a><a class="<?= $key==='preparation'?'is-active':'' ?>" href="?key=preparation">فیش آماده‌سازی</a></nav>
<div class="panel-helper-note print-template-compat"><strong>پیش‌نمایش و چاپ فیزیکی</strong><span>پیش‌نمایش دقیق از همان Renderer ویندوز و هندسه مقصد ساخته می‌شود. اگر برنامه چاپ محلی در دسترس نباشد، فقط پیش‌نمایش تقریبی و صریحاً برچسب‌خورده نمایش داده می‌شود؛ نتیجه نهایی را روی پرینتر واقعی نیز بررسی کنید.</span></div>

<div class="print-template-workspace">
<form method="post" class="card print-template-editor" data-print-template-form data-reorder-form><?= csrf_field() ?><input type="hidden" name="template_key" value="<?= e($key) ?>"><input type="hidden" name="section_order" data-reorder-output>
  <div class="card-head"><div><h2><?= $key==='customer'?'رسید مشتری':'فیش آماده‌سازی' ?></h2><small><?= e((string)$active['name']) ?> · <?= e((string)$active['version']) ?></small></div></div>
  <div class="card-body print-template-fields">
    <label class="full"><span>نام قالب</span><input class="form-control" name="name" maxlength="160" value="<?= e((string)$active['name']) ?>"></label>
    <label><span>عرض کاغذ</span><select class="form-control" name="paper_width_mm" data-choice-mode="compact" data-template-setting><option value="80" <?= (int)$active['paper_width_mm']===80?'selected':'' ?>>۸۰ میلی‌متر</option><option value="58" <?= (int)$active['paper_width_mm']===58?'selected':'' ?>>۵۸ میلی‌متر</option></select></label>
    <label><span>تراکم</span><select class="form-control" name="design[density]" data-choice-mode="compact" data-template-setting><option value="compact" <?= $active['design']['density']==='compact'?'selected':'' ?>>فشرده و خوانا</option><option value="comfortable" <?= $active['design']['density']==='comfortable'?'selected':'' ?>>با فاصله بیشتر</option></select></label><label><span>چیدمان سربرگ</span><select class="form-control" name="design[header_alignment]" data-choice-mode="compact" data-template-setting><option value="center" <?= $active['design']['header_alignment']==='center'?'selected':'' ?>>وسط</option><option value="right" <?= $active['design']['header_alignment']==='right'?'selected':'' ?>>راست</option></select></label>
    <label><span>اندازه متن پایه</span><input class="form-control" inputmode="numeric" enterkeyhint="next" name="base_font_size" value="<?= fa_digits((int)$active['base_font_size']) ?>" data-template-setting></label>
    <label><span>اندازه عنوان</span><input class="form-control" inputmode="numeric" enterkeyhint="next" name="title_font_size" value="<?= fa_digits((int)$active['title_font_size']) ?>" data-template-setting></label>
    <label><span>اندازه اقلام/جدول</span><input class="form-control" inputmode="numeric" enterkeyhint="next" name="table_font_size" value="<?= fa_digits((int)$active['table_font_size']) ?>" data-template-setting></label>
    <label><span>فاصله خطوط</span><input class="form-control" inputmode="numeric" enterkeyhint="next" name="line_spacing" value="<?= fa_digits((int)$active['line_spacing']) ?>" data-template-setting></label>
    <label><span>حاشیه چاپ</span><input class="form-control" inputmode="numeric" enterkeyhint="next" name="margin" value="<?= fa_digits((int)$active['margin']) ?>" data-template-setting></label>
    <label><span>چیدمان اقلام</span><select class="form-control" name="design[item_layout]" data-choice-mode="compact" data-template-setting><?php foreach(($key==='customer'?['columnar'=>'ستونی کامل (پیشنهادی برای ۸۰mm)','columnar-compact'=>'ستونی فشرده (مناسب ۵۸mm)','two-line'=>'دوخطی','responsive-receipt'=>'سازگار با قالب قبلی']:['quantity-first'=>'تعداد در اولویت']) as $value=>$label): ?><option value="<?= e($value) ?>" <?= $active['design']['item_layout']===$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
    <label><span>خط جداکننده</span><select class="form-control" name="design[separator_style]" data-choice-mode="compact" data-template-setting><?php foreach(['solid'=>'پیوسته','dashed'=>'خط‌چین','minimal'=>'حداقلی'] as $value=>$label): ?><option value="<?= e($value) ?>" <?= $active['design']['separator_style']===$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
    <fieldset class="full print-template-flags"><legend>اطلاعات قابل نمایش</legend><label><input type="checkbox" name="show_time" <?= $active['show_time']?'checked':'' ?> data-template-setting> زمان</label><label><input type="checkbox" name="show_actor" <?= $active['show_actor']?'checked':'' ?> data-template-setting> ثبت‌کننده</label><?php if($key==='preparation'): ?><label><input type="checkbox" name="show_order_number" <?= $active['show_order_number']?'checked':'' ?> data-template-setting> شماره سفارش</label><?php endif; ?><label><input type="checkbox" name="show_section_titles" <?= $active['show_section_titles']?'checked':'' ?> data-template-setting> عنوان بخش‌ها</label></fieldset>
    <div class="full"><strong class="form-section-title">ترتیب بخش‌ها</strong><div class="reorder-list print-template-section-order" data-reorder-list><?php foreach($active['design']['section_order'] as $section): $sectionLabel=(string)($sectionLabels[$section]??$section); ?><div class="reorder-row" draggable="true" data-reorder-id="<?= e((string)$section) ?>"><button class="reorder-handle" data-reorder-handle type="button" aria-label="گرفتن و جابه‌جایی <?= e($sectionLabel) ?>"><?= ui_icon('menu') ?></button><div class="reorder-copy"><strong><?= e($sectionLabel) ?></strong></div><div class="reorder-actions"><button class="btn btn-sm btn-light" type="button" data-reorder-up aria-label="انتقال <?= e($sectionLabel) ?> به بالا">↑</button><button class="btn btn-sm btn-light" type="button" data-reorder-down aria-label="انتقال <?= e($sectionLabel) ?> به پایین">↓</button></div></div><?php endforeach; ?></div></div>
    <div class="full print-template-labels"><strong class="form-section-title">متن‌های همین قالب</strong><div><?php foreach($active['design']['labels'] as $labelKey=>$value): ?><label><span><?= e($labelKey) ?></span><input class="form-control" name="labels[<?= e($labelKey) ?>]" value="<?= e((string)$value) ?>" maxlength="80" data-template-setting></label><?php endforeach; ?></div></div>
    <label class="full"><span>متن پایین فیش</span><textarea class="form-control" name="footer" maxlength="300" data-template-setting><?= e((string)$active['footer']) ?></textarea></label>
    <div class="full actions"><label class="check-line"><input type="checkbox" name="activate" checked><span>بعد از ذخیره فعال شود</span></label><button class="btn btn-primary" name="action" value="save_design">ذخیره نسخه جدید</button></div>
  </div>
</form>

<section class="card print-template-preview-card"><div class="card-head"><div><h2>پیش‌نمایش</h2><small>حالت دقیق همان bitmap پیش از Driver را نشان می‌دهد؛ آزمون کاغذ برای Driver/هد/کاغذ همچنان لازم است.</small></div><div class="print-preview-controls"><select class="form-control" data-preview-destination data-choice-mode="browse" aria-label="مقصد پیش‌نمایش"><option value="">انتخاب مقصد واقعی</option><?php foreach($previewDestinations as $d): ?><option value="<?= e((string)$d['destination_key']) ?>"><?= e((string)$d['label']) ?> · <?= fa_digits((int)$d['paper_width_mm']) ?>mm</option><?php endforeach; ?></select><select class="form-control" data-preview-width data-choice-mode="compact"><option value="80">۸۰ میلی‌متر</option><option value="58">۵۸ میلی‌متر</option></select><select class="form-control" data-preview-scenario data-choice-mode="compact"><?php foreach($sampleScenarios as $scenario=>$data): ?><option value="<?= e($scenario) ?>"><?= e($scenario==='default'?'نمونه عادی':($scenario==='discount'?'با تخفیف':($scenario==='adjustment'?'اصلاحیه':'لغو'))) ?></option><?php endforeach; ?></select></div></div><div class="card-body print-preview-stage"><div class="print-preview-mode" data-preview-mode>در حال آماده‌سازی پیش‌نمایش…</div><div class="thermal-preview" data-thermal-preview></div></div></section>
</div>

<section class="card print-template-transfer"><div class="card-head"><div><h2>انتقال قالب</h2><small>می‌توانی قالب را از فایل وارد کنی یا برای نگهداری و انتقال، خروجی بگیری.</small></div></div><div class="card-body print-template-transfer-grid"><form method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="template_key" value="<?= e($key) ?>"><label><span>فایل قالب</span><input class="form-control" type="file" name="template_file" accept=".json,.zip,application/json,application/zip" required></label><button class="btn btn-primary" name="action" value="import_template">واردکردن و فعال‌سازی</button></form><div class="actions"><a class="btn btn-light" href="?key=<?= e($key) ?>&download=json">دریافت فایل تنظیمات</a><a class="btn btn-light" href="?key=<?= e($key) ?>&download=zip">دریافت بسته کامل</a><form method="post"><?= csrf_field() ?><input type="hidden" name="template_key" value="<?= e($key) ?>"><button class="btn btn-outline" name="action" value="reset_template" data-click-confirm="تنظیمات فعلی این قالب کنار گذاشته می‌شود و قالب استاندارد چاپ فعال خواهد شد." data-confirm-title="بازگشت به قالب استاندارد؟" data-confirm-ok="بازگشت به استاندارد">بازگشت به استاندارد</button></form></div></div></section>

<section class="card print-template-history"><div class="card-head"><div><h2>نسخه‌های قالب</h2><small>نسخه فعال، نسخه‌های قبلی و امکان بازگشت بدون حذف تاریخچه.</small></div></div><div class="panel-list-card"><?php foreach($history as $row): $origin=(string)($row['origin']??'custom'); ?><article class="panel-list-row"><div class="panel-list-primary"><span><strong><?= e((string)$row['name']) ?></strong><small><bdi dir="ltr"><?= e((string)$row['template_version']) ?></bdi> · <?= e((string)($originLabels[$origin]??'سفارشی')) ?> · بازبینی <?= fa_digits((int)($row['revision']??1)) ?> · <?= e(format_jalali_compact((string)$row['created_at'])) ?><?= $row['created_by']?' · '.e((string)$row['created_by']):'' ?></small></span></div><div class="panel-list-secondary"><?php if((int)$row['active']===1): ?><span class="panel-status-badge is-ok">فعال</span><?php endif; ?></div><div class="panel-row-actions"><form method="post"><?= csrf_field() ?><input type="hidden" name="template_key" value="<?= e($key) ?>"><input type="hidden" name="template_id" value="<?= (int)$row['id'] ?>"><?php if((int)$row['active']!==1): ?><button class="btn btn-sm btn-light" name="action" value="activate_template">فعال‌سازی</button><?php endif; ?><?php $isCurrentStandard=$origin==='builtin'&&(string)$row['template_version']===(string)print_template_defaults($key)['version']; if(!$isCurrentStandard): ?><button class="btn btn-sm btn-outline" name="action" value="delete_template" data-click-confirm="این نسخه حذف می‌شود؛ اگر فعال باشد جایگزین اتمیک فعال خواهد شد." data-confirm-title="حذف نسخه قالب؟" data-confirm-ok="حذف نسخه" data-confirm-danger="1">حذف</button><?php endif; ?></form></div></article><?php endforeach; ?></div></section>
<script type="application/json" id="print-template-samples"><?= json_script($sampleScenarios) ?></script><script type="application/json" id="print-template-preview-destinations"><?= json_script($previewDestinations) ?></script><script>window.SOKNA_PRINT_BRIDGE_CAPABILITY_URL=<?= json_script(asset('api/print_bridge_capability.php')) ?>;</script>
</div>
<?php panel_footer('<script defer src="'.e(asset('assets/js/reorder-list.js')).'"></script><script defer src="'.e(asset('assets/js/print-template-designer.js')).'"></script>'); ?>

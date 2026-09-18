<?php
declare(strict_types=1);

/**
 * Canonical Sokna update center.
 *
 * This entry intentionally does not load bootstrap.php, so it remains
 * available during maintenance and after an application-file failure.
 */
if (!defined('SOKNA_UPDATER_ROOT') || !defined('SOKNA_UPDATER_ENGINE_DIR')) {
    http_response_code(500);
    exit('Updater loader is required.');
}

updater_retire_legacy_runtime();

sur_session_start();
header('Cache-Control: no-store, max-age=0');
header('Pragma: no-cache');

function uu_h(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function uu_fa(mixed $value): string { return strtr((string)$value, ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']); }
function uu_redirect(string $query = ''): never { header('Location: ./' . ($query !== '' ? '?' . ltrim($query, '?') : ''), true, 303); exit; }
function uu_flash(string $type, string $message): void { $_SESSION['sokna_updater_flash'] = ['type'=>$type,'message'=>$message]; }
function uu_take_flash(): ?array { $f=$_SESSION['sokna_updater_flash']??null; unset($_SESSION['sokna_updater_flash']); return is_array($f)?$f:null; }
function uu_json(array $data, int $status=200): never { http_response_code($status); header('Content-Type: application/json; charset=utf-8'); echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
function uu_maintenance_state(): array { $p=SOKNA_UPDATER_ROOT.'/storage/maintenance.json'; if(!is_file($p))return ['active'=>false]; $d=json_decode((string)@file_get_contents($p),true); return is_array($d)?$d+['active'=>true]:['active'=>true,'mode'=>'unknown','message'=>'سامانه در وضعیت بازیابی است.']; }

function uu_history_label(array $row): string {
    $code=(string)($row['event_code']??$row['action']??'');
    return match($code){
        'update_completed'=>'به‌روزرسانی موفق',
        'rollback_completed'=>'بازگشت موفق',
        'update_failed_before_apply'=>'توقف امن پیش از تغییر فایل‌ها',
        'update_recovery_required'=>'نیاز به بازیابی',
        'update_automatic_rollback'=>'بازگشت خودکار پس از خطا',
        'update_cancelled_before_apply'=>'لغو پیش از اعمال تغییرات',
        'data_emergency_restore'=>'بازگردانی اضطراری داده',
        'legacy_reset'=>'آزادسازی وضعیت قدیمی',
        default=>(!empty($row['success'])?'عملیات موفق':'عملیات ناموفق'),
    };
}
function uu_history_actor(array $row): string {
    $name=trim((string)($row['actor_display_name']??''));
    if($name!=='')return $name;
    $username=trim((string)($row['actor_username']??''));
    if($username!=='')return $username;
    $id=(int)($row['actor_user_id']??0);
    return $id>0?'مدیر #'.uu_fa($id):'—';
}

$latestJob = sur_latest_job();
$admin = updater_session_admin();
$jobAuthorized = $latestJob ? sur_is_authorized($latestJob) : false;

if (isset($_GET['api'])) {
    if (!$latestJob) uu_json(['success'=>false,'message'=>'عملیات فعالی پیدا نشد.'],404);
    if (!$admin && !$jobAuthorized) uu_json(['success'=>false,'message'=>'برای ادامه عملیات دوباره احراز هویت کنید.'],401);
    $data=json_decode((string)file_get_contents('php://input'),true); if(!is_array($data))$data=[];
    if (!sur_csrf_valid($data['csrf_token']??null)) uu_json(['success'=>false,'message'=>'نشست صفحه منقضی شده است. صفحه را تازه‌سازی کنید.'],419);
    $id=trim((string)($data['job_id']??''));
    if ($id!=='' && !hash_equals((string)$latestJob['id'],$id)) uu_json(['success'=>false,'message'=>'شناسه عملیات معتبر نیست.'],422);
    $actor=(int)($latestJob['actor_user_id']??0);
    if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
    try {
        $action=(string)($data['action']??'status');
        if($action==='status')$fresh=sru_job_read((string)$latestJob['id']);
        elseif($action==='step')$fresh=updater_step_job((string)$latestJob['id'],$actor);
        elseif($action==='abort'){
            if(($data['confirm']??false)!==true)throw new RuntimeException('تأیید توقف لازم است.');
            $fresh=updater_abort_job((string)$latestJob['id'],$actor);
        } elseif($action==='safe_unlock'){
            if(($data['confirm']??false)!==true)throw new RuntimeException('تأیید لغو امن لازم است.');
            $fresh=sur_safe_unlock((string)$latestJob['id']);
        } else throw new RuntimeException('عملیات شناخته‌شده نیست.');
        if(in_array((string)($fresh['status']??''),['completed','failed'],true))sur_clear_cookie();
        uu_json(['success'=>true,'job'=>sur_public_job($fresh),'legacy'=>updater_legacy_state()]);
    } catch(Throwable $e){uu_json(['success'=>false,'message'=>$e->getMessage()],422);}
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET')==='POST') {
    $action=(string)($_POST['action']??'');
    if(!sur_csrf_valid($_POST['csrf_token']??null)){uu_flash('error','نشست صفحه منقضی شده است. دوباره تلاش کنید.');uu_redirect();}
    try {
        if($action==='admin_login'){
            if(!updater_login_admin_account((string)($_POST['identifier']??''),(string)($_POST['password']??'')))throw new RuntimeException('نام کاربری یا رمز مدیر درست نیست.');
            uu_redirect();
        }
        if($action==='recovery_code'){
            if(!$latestJob||!sur_login_with_code((string)($_POST['recovery_code']??''),$latestJob))throw new RuntimeException('کد بازیابی معتبر نیست یا منقضی شده است.');
            uu_flash('success','دسترسی بازیابی فعال شد.');uu_redirect();
        }
        if($action==='logout'){updater_logout_admin();sur_clear_cookie();uu_redirect();}
        $admin=updater_session_admin();
        if(!$admin)throw new RuntimeException('برای این عملیات باید با حساب مدیر وارد شوید.');
        $actor=(int)$admin['id'];
        if($action==='legacy_reset'){
            if(!isset($_POST['confirm']))throw new RuntimeException('تیک تأیید آزادسازی لازم است.');
            updater_reset_legacy_state($actor);
            uu_flash('success','قفل و Jobهای Updater قدیمی بایگانی و سامانه آزاد شد. اطلاعات و دیتابیس تغییر نکردند.');uu_redirect();
        }
        if($action==='data_recovery_health'){
            $state=uu_maintenance_state();
            if(!in_array((string)($state['mode']??''),['restore','recovery_required'],true))throw new RuntimeException('وضعیت بازیابی داده فعال نیست.');
            $health=sur_data_health_check();
            if(!$health['ok'])throw new RuntimeException('بررسی سلامت پایه موفق نیست؛ قفل بازیابی باید باقی بماند.');
            uu_flash('success','بررسی پایه سلامت موفق بود؛ برای قفل recovery_required این نتیجه به‌تنهایی مجوز حذف دستی قفل نیست.');uu_redirect();
        }
        if($action==='data_recovery_restore'){
            if(!isset($_POST['confirm']))throw new RuntimeException('تأیید بازگشت اضطراری لازم است.');
            if(trim((string)($_POST['confirm_text']??''))!=='بازگشت اضطراری')throw new RuntimeException('برای تأیید، عبارت «بازگشت اضطراری» را دقیق وارد کنید.');
            $state=uu_maintenance_state();
            if(($state['mode']??'')!=='recovery_required')throw new RuntimeException('بازگشت اضطراری فقط در وضعیت recovery_required مجاز است.');
            if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
            sur_restore_emergency_data($state,$actor);
            sur_session_start();
            uu_flash('success','وضعیت قبل از Restore از پشتیبان اضطراری بازگردانده شد و بررسی سلامت موفق بود.');uu_redirect();
        }
        if($action==='upload'){
            $file=$_FILES['package']??null;
            if(!is_array($file)||(int)($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new RuntimeException('فایل ZIP به‌درستی دریافت نشد.');
            $name=(string)($file['name']??'release.zip');
            if(strtolower(pathinfo($name,PATHINFO_EXTENSION))!=='zip')throw new RuntimeException('فقط فایل ZIP استاندارد پذیرفته می‌شود.');
            $size=(int)($file['size']??0);if($size<100||$size>30*1024*1024)throw new RuntimeException('حجم بسته معتبر نیست.');
            $temp=updater_pending_dir().'/upload-'.bin2hex(random_bytes(12)).'.zip';
            $source=(string)($file['tmp_name']??'');
            if(!is_uploaded_file($source)||!move_uploaded_file($source,$temp))throw new RuntimeException('انتقال امن فایل آپلودی انجام نشد.');
            $pending=updater_prepare_package($temp,$name,$actor);
            $targetVersion=(string)($pending['validation']['manifest']['version']??'');
            uu_flash('success','بسته نسخه '.($targetVersion!==''?$targetVersion:'جدید').' کامل بررسی و آماده نصب شد؛ هنوز هیچ فایل زنده‌ای تغییر نکرده است.');uu_redirect('pending='.rawurlencode((string)$pending['id']));
        }
        if($action==='cancel_pending'){
            $id=trim((string)($_POST['pending_id']??''));updater_pending($id,$actor);updater_discard_pending($id);
            uu_flash('success','بسته مرحله‌بندی‌شده حذف شد.');uu_redirect();
        }
        if($action==='start_update'){
            if(!isset($_POST['confirm']))throw new RuntimeException('تیک تأیید Restore Point لازم است.');
            $id=trim((string)($_POST['pending_id']??''));$job=updater_start($id,$actor);
            if(!empty($job['_rescue_token']))updater_set_rescue_cookie((string)$job['id'],(string)$job['_rescue_token'],(string)$job['rescue_expires_at']);
            if(!empty($job['_rescue_code']))$_SESSION['sokna_updater_codes'][(string)$job['id']]=(string)$job['_rescue_code'];
            uu_flash('success','عملیات شروع شد. بستن مرورگر وضعیت را از بین نمی‌برد.');uu_redirect('job='.rawurlencode((string)$job['id']));
        }
        if($action==='step_job'){
            $id=trim((string)($_POST['job_id']??''));updater_step_job($id,$actor);uu_redirect('job='.rawurlencode($id));
        }
        if($action==='abort_job'){
            if(!isset($_POST['confirm']))throw new RuntimeException('تیک تأیید توقف لازم است.');
            $id=trim((string)($_POST['job_id']??''));updater_abort_job($id,$actor);uu_redirect('job='.rawurlencode($id));
        }
        if($action==='start_rollback'){
            if(!isset($_POST['confirm']))throw new RuntimeException('تأیید بازگشت لازم است.');
            $restoreId=trim((string)($_POST['restore_id']??''));
            $selected=null; foreach(updater_restore_points(10) as $point){if(hash_equals((string)($point['restore_id']??''),$restoreId)){$selected=$point;break;}}
            if(!$selected)throw new RuntimeException('نقطه بازگشت انتخاب‌شده پیدا نشد.');
            $restoreDb=!empty($selected['migration_applied'])&&!empty($selected['database_ready']);
            if($restoreDb && trim((string)($_POST['confirm_text']??''))!=='بازگشت')throw new RuntimeException('برای بازگشت دیتابیس، عبارت «بازگشت» را دقیق وارد کنید.');
            $job=updater_start_rollback($restoreId,$actor);
            uu_flash('success','بازگشت امن شروع شد.');uu_redirect('job='.rawurlencode((string)$job['id']));
        }
        throw new RuntimeException('عملیات فرم شناخته‌شده نیست.');
    } catch(Throwable $e){uu_flash('error',$e->getMessage());uu_redirect();}
}

$admin=updater_session_admin();
$latestJob=sur_latest_job();
$jobAuthorized=$latestJob?sur_is_authorized($latestJob):false;
$csrf=sur_csrf_token();
$flash=uu_take_flash();
$legacy=updater_legacy_state();
$pendingId=trim((string)($_GET['pending']??''));
$pending=null;
if($admin&&$pendingId!==''){try{$pending=updater_pending($pendingId,(int)$admin['id']);}catch(Throwable $e){$flash=['type'=>'error','message'=>$e->getMessage()];}}
$requestedJobId=trim((string)($_GET['job']??''));
$job=null;
if($latestJob&&($admin||$jobAuthorized)){
    if($requestedJobId===''||hash_equals((string)$latestJob['id'],$requestedJobId))$job=$latestJob;
    else{try{$job=sru_job_read($requestedJobId,$admin?(int)$admin['id']:null);}catch(Throwable){$job=$latestJob;}}
}
$public=$job?sur_public_job($job):null;
$recoveryCode='';
if($job&&$admin){$recoveryCode=(string)($_SESSION['sokna_updater_codes'][(string)$job['id']]??'');if($recoveryCode!=='')unset($_SESSION['sokna_updater_codes'][(string)$job['id']]);}
$history=$admin?updater_history(20):[];
$restorePoints=$admin?updater_restore_points(5):[];
$publicStatus=(string)($public['status']??'');
$operationActive=$public&&in_array($publicStatus,['running','recovery_required'],true);
$maintenanceActive=(bool)($legacy['maintenance_active']??false);
$canReturnToPanel=(bool)$admin&&!$maintenanceActive&&!$operationActive;
$statusLabels=['completed'=>'تکمیل‌شده','failed'=>'ناموفق','running'=>'در حال اجرا','recovery_required'=>'در حال بازیابی'];
$statusLabel=$statusLabels[$publicStatus]??'آماده';
$maintenanceStateV2=uu_maintenance_state();
$historyWriteReady=$admin?updater_history_write_ready():true;
?>
<!doctype html>
<html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="robots" content="noindex,nofollow"><title>مرکز به‌روزرسانی و بازیابی Sokna</title>
<style>
:root{--bg:#f6f2eb;--card:#fffdfa;--text:#28241f;--muted:#756d64;--line:#ddd4c9;--primary:#2f7566;--primary-soft:#e7f0ed;--danger:#a4433d;--danger-soft:#fff0ee;--warn:#8b641e;--warn-soft:#fff5df;--ok:#286144;--ok-soft:#eaf6ef;--shadow:0 12px 34px #3a302114}*{box-sizing:border-box}html{background:var(--bg)}body{margin:0;background:var(--bg);color:var(--text);font-family:Vazirmatn,Tahoma,Arial,sans-serif;line-height:1.8}.shell{width:min(780px,calc(100% - 24px));margin:max(12px,env(safe-area-inset-top)) auto calc(50px + env(safe-area-inset-bottom))}.top{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:14px}.brand{display:flex;gap:11px;align-items:center;min-width:0}.logo{width:44px;height:44px;display:grid;place-items:center;border-radius:14px;background:var(--primary-soft);color:var(--primary);font-size:23px;flex:none}.brand b{display:block;font-size:19px;line-height:1.45}.brand small{display:block;color:var(--muted);font-size:12px}.top-actions{display:flex;gap:6px;flex:none}.btn{display:inline-flex;align-items:center;justify-content:center;min-height:44px;border:0;border-radius:12px;padding:9px 14px;font:inherit;font-weight:800;cursor:pointer;background:#ebe6df;color:var(--text);text-decoration:none}.btn.primary{background:var(--primary);color:#fff}.btn.danger{background:var(--danger);color:#fff}.btn.outline{border:1px solid var(--line);background:#fff}.btn.subtle{background:transparent;color:var(--muted)}.btn.small{min-height:40px;padding:7px 11px;font-size:13px}.card{margin:11px 0;border:1px solid var(--line);border-radius:19px;background:var(--card);box-shadow:var(--shadow);overflow:hidden}.pad{padding:18px}.muted{color:var(--muted);font-size:13px}.notice{margin:10px 0;padding:11px 13px;border-radius:13px;background:#eaf4f1;color:#285f53;font-size:13px}.notice.error{background:var(--danger-soft);color:#8f352f}.notice.warn{background:var(--warn-soft);color:#725017}.notice.success{background:var(--ok-soft);color:var(--ok)}.health-strip{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:15px 16px;border:1px solid var(--line);border-radius:17px;background:var(--card)}.health-copy{display:grid;gap:2px}.health{display:flex;align-items:center;gap:7px;font-weight:900;color:var(--ok)}.health:before{content:"";width:9px;height:9px;border-radius:50%;background:#3a8a72}.health.attention{color:var(--warn)}.health.attention:before{background:#bd8724}.version-now{text-align:left}.version-now small{display:block;color:var(--muted);font-size:11px}.version-now b{font-size:17px}.field{display:grid;gap:6px;margin:11px 0}.field input,.field select{width:100%;min-height:48px;padding:11px 12px;border:1px solid var(--line);border-radius:12px;background:#fff;font:inherit}.field input:focus{outline:2px solid color-mix(in srgb,var(--primary) 35%,transparent);outline-offset:1px}.drop{display:grid;place-items:center;text-align:center;border:1.5px dashed #cbbfb1;border-radius:15px;padding:20px;background:#fbf8f3;cursor:pointer}.drop input{max-width:100%;margin-bottom:9px}.drop strong{font-size:15px}.section-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:12px}.section-head h1,.section-head h2{margin:0}.section-head h1{font-size:23px}.section-head h2{font-size:18px}.section-head p{margin:3px 0 0}.row{display:flex;gap:8px;flex-wrap:wrap;align-items:center}.check{display:flex;gap:8px;align-items:flex-start;margin:12px 0}.check input{margin-top:7px}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.metric{padding:11px 12px;border:1px solid var(--line);border-radius:13px;background:#fff}.metric small{display:block;color:var(--muted);font-size:11px}.metric b{font-size:16px}.metric code{display:block;direction:ltr;font-size:11px;overflow-wrap:anywhere}.job-focus{border-color:color-mix(in srgb,var(--primary) 26%,var(--line))}.job-status{display:flex;align-items:flex-start;justify-content:space-between;gap:10px}.version-flow{display:flex;align-items:center;gap:7px;flex-wrap:wrap;font-weight:900}.version-flow .to{font-size:12px;color:var(--muted)}.badge{padding:4px 9px;border-radius:999px;background:#eee8df;font-size:12px;font-weight:800;white-space:nowrap}.progress{height:10px;margin:15px 0 7px;border-radius:999px;background:#e5e0d8;overflow:hidden}.progress i{display:block;height:100%;background:var(--primary);transition:width .25s}.job-progress-meta{display:flex;align-items:flex-start;justify-content:space-between;gap:10px}.code{direction:ltr;padding:9px;border:1px solid #ead7aa;border-radius:11px;background:#fff5df;text-align:center;font:700 17px ui-monospace,monospace}.restore-list,.history-list{display:grid;gap:0}.restore-row,.history-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:12px;align-items:center;padding:12px 0;border-top:1px solid #eee7de}.restore-row:first-child,.history-row:first-child{border-top:0}.restore-copy,.history-copy{min-width:0}.restore-copy b,.history-copy b{display:block}.restore-meta,.history-meta{display:flex;align-items:center;gap:7px;flex-wrap:wrap;margin-top:3px;color:var(--muted);font-size:12px}.scope-pill{padding:2px 7px;border-radius:999px;background:#f2eee8}.scope-pill.danger{background:var(--danger-soft);color:#8f352f}.history-result.ok{color:var(--ok)}.history-result.bad{color:#8f352f}.actor{font-size:11px;color:var(--muted)}details.disclosure{border-top:1px solid var(--line);margin-top:14px;padding-top:11px}details.disclosure>summary{cursor:pointer;list-style:none;color:var(--primary);font-weight:850}details.disclosure>summary::-webkit-details-marker{display:none}.technical-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;margin-top:9px}.technical-grid div{padding:9px;border-radius:11px;background:#f6f2eb}.technical-grid small{display:block;color:var(--muted);font-size:11px}.risk{font-size:12px;color:#725017}.client-error{display:block;margin-top:5px;color:#9b332d;font-size:12px}.has-error input,.has-error .drop{outline:2px solid #c8564d55;outline-offset:2px}.hide,.hidden{display:none!important}.modal-layer{position:fixed;inset:0;z-index:500;display:grid;place-items:center;padding:16px;background:#171b18a8}.modal-layer.hidden{display:none}.modal{width:min(440px,100%);padding:18px;border-radius:19px;background:#fff;box-shadow:0 26px 80px #0005}.modal h2{margin:0 0 6px;font-size:19px}.modal p{margin:0 0 10px}.modal-actions{display:flex;gap:8px;justify-content:flex-start;margin-top:14px}.recovery-mode{border-color:#e4c978;background:#fffaf0}.recovery-mode .section-head h2{color:#725017}
@media(max-width:560px){.shell{width:min(100% - 16px,780px);margin-top:max(8px,env(safe-area-inset-top))}.top{align-items:center}.brand small{display:none}.brand b{font-size:16px}.logo{width:40px;height:40px}.top-actions .btn{font-size:0;width:42px;padding:0}.top-actions .btn:before{font-size:18px}.top-actions a:before{content:"←"}.pad{padding:15px}.health-strip{padding:13px}.grid{grid-template-columns:1fr}.restore-row,.history-row{grid-template-columns:1fr auto}.restore-row .btn{min-width:82px}.modal-actions{display:grid;grid-template-columns:1fr 1fr}.modal-actions .btn{width:100%}}
</style></head><body><main class="shell">
<header class="top"><div class="brand"><span class="logo" aria-hidden="true">↻</span><div><b>به‌روزرسانی و بازیابی</b><small>مرکز مستقل نصب و بازگشت امن سکنا</small></div></div><div class="top-actions"><?php if($canReturnToPanel): ?><a class="btn outline small" href="../maintenance.php" aria-label="بازگشت به پشتیبان‌گیری و بازیابی">بازگشت</a><?php endif; ?></div></header>
<?php if($flash): ?><div class="notice <?= uu_h((string)$flash['type']) ?>" role="status"><?= uu_h((string)$flash['message']) ?></div><?php endif; ?>

<?php if(!$admin&&!$jobAuthorized): ?>
<section class="card"><div class="pad"><div class="section-head"><div><h1>ورود مدیر</h1><p class="muted">برای نصب نسخه، بررسی عملیات یا بازگشت امن وارد شوید.</p></div></div><form method="post"><input type="hidden" name="csrf_token" value="<?= uu_h($csrf) ?>"><input type="hidden" name="action" value="admin_login"><label class="field"><span>نام کاربری مدیر</span><input name="identifier" autocomplete="username" required></label><label class="field"><span>رمز عبور</span><input type="password" name="password" autocomplete="current-password" required></label><button class="btn primary">ورود</button></form><?php if($latestJob): ?><details class="disclosure"><summary>ورود اضطراری با کد بازیابی عملیات</summary><p class="muted">فقط برای ادامه همان عملیات نیمه‌تمام؛ این کد جای رمز مدیر یا Restore Point نیست.</p><form method="post"><input type="hidden" name="csrf_token" value="<?= uu_h($csrf) ?>"><input type="hidden" name="action" value="recovery_code"><label class="field"><span>کد یک‌بارمصرف عملیات</span><input name="recovery_code" autocomplete="one-time-code" placeholder="ABCD-EFGH-JKLM" required></label><button class="btn outline">ورود اضطراری</button></form></details><?php endif; ?></div></section>
<?php else: ?>
<section class="health-strip" aria-label="خلاصه وضعیت"><div class="health-copy"><div class="health <?= ($maintenanceActive||$operationActive)?'attention':'' ?>"><?= $operationActive?'عملیات در حال اجرا':($maintenanceActive?'نیازمند بررسی':'سامانه سالم است') ?></div><?php if($publicStatus==='completed'): ?><small class="muted">آخرین عملیات با بررسی سلامت پایه پایان یافته است.</small><?php elseif(!$operationActive&&!$maintenanceActive): ?><small class="muted">مرکز بازیابی آماده است.</small><?php endif; ?></div><div class="version-now"><small>نسخه نصب‌شده</small><b dir="ltr"><?= uu_h(updater_current_version()) ?></b></div></section>
<?php if(!$historyWriteReady): ?><div class="notice warn"><strong>ثبت تاریخچه در دسترس نیست.</strong><br>عملیات را شروع نکنید تا دسترسی نوشتن پوشه Updater اصلاح شود؛ خود موتور خطای Audit را در لاگ Sanitized ثبت می‌کند.</div><?php endif; ?>

<?php if(!empty($maintenanceStateV2['active']) && in_array((string)($maintenanceStateV2['mode']??''),['restore','recovery_required'],true)): ?>
<section class="card recovery-mode"><div class="pad"><div class="section-head"><div><h2>بازیابی داده نیازمند رسیدگی است</h2><p class="muted"><?= uu_h((string)($maintenanceStateV2['message']??'سامانه تا تعیین تکلیف بازیابی قفل است.')) ?></p></div></div><div class="grid"><div class="metric"><small>حالت</small><b dir="ltr"><?= uu_h((string)($maintenanceStateV2['mode']??'—')) ?></b></div><div class="metric"><small>نقطه اضطراری</small><b><?= uu_h((string)($maintenanceStateV2['emergency_backup']??'—')) ?></b></div></div><?php if($admin): ?><div class="row" style="margin-top:12px"><form method="post"><input type="hidden" name="csrf_token" value="<?= uu_h($csrf) ?>"><button class="btn outline" name="action" value="data_recovery_health">بررسی سلامت پایه فعلی</button></form></div><?php endif; ?><?php if($admin && ($maintenanceStateV2['mode']??'')==='recovery_required' && !empty($maintenanceStateV2['emergency_backup'])): ?><details class="disclosure"><summary>بازگرداندن وضعیت قبل از Restore ناموفق</summary><p class="risk">این عملیات دیتابیس و فایل‌های آپلودی را به نقطه اضطراری قبل از Restore ناموفق بازمی‌گرداند. بررسی سلامت پایه پس از آن اجباری است.</p><form method="post"><input type="hidden" name="csrf_token" value="<?= uu_h($csrf) ?>"><label class="check"><input type="checkbox" name="confirm" required><span>دامنه بازگردانی اضطراری را می‌دانم.</span></label><label class="field"><span>برای تأیید بنویسید: بازگشت اضطراری</span><input name="confirm_text" autocomplete="off" required></label><button class="btn danger" name="action" value="data_recovery_restore">بازگرداندن نقطه اضطراری</button></form></details><?php endif; ?></div></section>
<?php endif; ?>

<?php if($admin&&($legacy['legacy_maintenance']||$legacy['legacy_job_count']>0)): ?><section class="card"><div class="pad"><div class="notice warn"><strong>باقی‌مانده Updater قدیمی شناسایی شد.</strong><br>آزادسازی فقط وضعیت قدیمی را بایگانی می‌کند و فایل‌ها/دیتابیس را تغییر نمی‌دهد.</div><form method="post"><input type="hidden" name="csrf_token" value="<?= uu_h($csrf) ?>"><label class="check"><input type="checkbox" name="confirm" required><span>آزادسازی ایمن را تأیید می‌کنم.</span></label><button class="btn danger" name="action" value="legacy_reset">آزادسازی وضعیت قدیمی</button></form></div></section><?php endif; ?>

<?php if($public): ?>
<?php if($operationActive): ?><section class="card job-focus <?= $publicStatus==='recovery_required'?'recovery-mode':'' ?>" id="jobCard"><div class="pad"><div class="job-status"><div><small class="muted"><?= ($public['mode']??'update')==='rollback'?'بازگشت امن':'به‌روزرسانی سامانه' ?></small><h2 class="version-flow"><span dir="ltr"><?= uu_h((string)$public['from_version']) ?></span><span class="to">به</span><span dir="ltr"><?= uu_h((string)$public['to_version']) ?></span></h2></div><span class="badge" id="badge"><?= uu_h($statusLabel) ?></span></div><div class="progress"><i id="bar" style="width:<?= (int)$public['progress'] ?>%"></i></div><div class="job-progress-meta"><b id="pct"><?= uu_fa((int)$public['progress']) ?>٪</b><span class="muted" id="message"><?= uu_h((string)$public['message']) ?></span></div><div class="notice error <?= empty($public['error'])?'hide':'' ?>" id="jobError"><?= uu_h((string)$public['error']) ?></div><?php if($recoveryCode!==''): ?><p class="muted">کد زیر فقط برای ورود دوباره به همین عملیات تا پایان آن است:</p><div class="code"><?= uu_h($recoveryCode) ?></div><?php endif; ?><div class="row" style="margin-top:12px"><form method="post"><input type="hidden" name="csrf_token" value="<?= uu_h($csrf) ?>"><input type="hidden" name="job_id" value="<?= uu_h((string)$public['id']) ?>"><button class="btn primary" name="action" value="step_job"><?= $publicStatus==='recovery_required'?'ادامه بازیابی':'بررسی و ادامه عملیات' ?></button></form><?php if($publicStatus==='running'): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= uu_h($csrf) ?>"><input type="hidden" name="job_id" value="<?= uu_h((string)$public['id']) ?>"><label class="check"><input type="checkbox" name="confirm" required><span>توقف و بازگشت خودکار را تأیید می‌کنم.</span></label><button class="btn danger" name="action" value="abort_job">توقف امن عملیات</button></form><?php endif; ?></div><p class="muted">بستن مرورگر Job را حذف نمی‌کند؛ ادامه مراحل با بازکردن دوباره همین مرکز قابل پیگیری است.</p></div></section>
<?php elseif($publicStatus==='completed'): ?><section class="card"><div class="pad"><div class="job-status"><div><small class="muted"><?= ($public['mode']??'update')==='rollback'?'بازگشت انجام شد':'آخرین به‌روزرسانی' ?></small><h2 class="version-flow"><span dir="ltr"><?= uu_h((string)$public['from_version']) ?></span><span class="to">به</span><span dir="ltr"><?= uu_h((string)$public['to_version']) ?></span></h2></div><span class="badge">تکمیل‌شده</span></div><div class="notice success">عملیات و بررسی سلامت پایه با موفقیت پایان یافت. Restore Point قبلی طبق سیاست نگهداری موتور حفظ می‌شود.</div><?php if($canReturnToPanel): ?><div class="row" style="margin-top:12px"><a class="btn primary" href="../maintenance.php">بازگشت به پشتیبان‌گیری و بازیابی</a></div><?php endif; ?></div></section>
<?php elseif($publicStatus==='failed'): ?><section class="card"><div class="pad"><div class="section-head"><div><h2>عملیات ناموفق پایان یافت</h2><p class="muted">وضعیت نهایی و مسیر بازیابی در زیر ثبت شده است.</p></div></div><div class="notice error"><?= uu_h((string)($public['error']??$public['message']??'خطای نامشخص')) ?></div></div></section><?php endif; ?>
<?php endif; ?>

<?php if($admin&&!$pending&&(!$public||!$operationActive)): ?><section class="card"><div class="pad"><div class="section-head"><div><h2>نصب نسخه جدید</h2><p class="muted">فقط بسته استاندارد انتشار Sokna را انتخاب کنید.</p></div></div><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?= uu_h($csrf) ?>"><label class="drop"><input type="file" name="package" accept=".zip,application/zip" required><strong>انتخاب فایل ZIP انتشار</strong><span class="muted">نسخه، صحت فایل‌ها و SHA-256، ساختار بسته، مسیرها، Syntax و فضای دیسک پیش از تغییر فایل زنده بررسی می‌شوند.</span></label><button class="btn primary" name="action" value="upload" style="margin-top:11px">بررسی و آماده‌سازی</button></form></div></section>
<?php elseif($admin&&$pending): $v=$pending['validation'];$m=$v['manifest']; ?><section class="card"><div class="pad"><div class="section-head"><div><small class="muted">بسته بررسی‌شده</small><h2>نسخه <span dir="ltr"><?= uu_h($m['version']) ?></span> آماده نصب است</h2></div><span class="badge">بررسی بسته موفق</span></div><div class="grid"><div class="metric"><small>نسخه فعلی → مقصد</small><b dir="ltr"><?= uu_h($m['current_version']) ?> → <?= uu_h($m['version']) ?></b></div><div class="metric"><small>تغییر دیتابیس</small><b><?= is_array($m['migration']??null)?'دارد':'ندارد' ?></b></div><div class="metric"><small>تغییر / حذف فایل</small><b><?= uu_fa(count($m['files'])) ?> / <?= uu_fa(count($m['delete'])) ?></b></div><div class="metric"><small>SHA-256 بسته</small><code><?= uu_h((string)($v['package_sha256']??'—')) ?></code></div></div><?php if($m['release_notes']): ?><p><?= nl2br(uu_h($m['release_notes'])) ?></p><?php endif; ?><div class="notice success">بسته فقط در فضای Stage قرار دارد و هنوز فایل زنده‌ای تغییر نکرده است.</div><form method="post"><input type="hidden" name="csrf_token" value="<?= uu_h($csrf) ?>"><input type="hidden" name="pending_id" value="<?= uu_h($pendingId) ?>"><label class="check"><input type="checkbox" name="confirm" required><span>ساخت Restore Point و توقف کوتاه عملیات نوشتنی را تأیید می‌کنم.</span></label><button class="btn primary" name="action" value="start_update">شروع به‌روزرسانی</button></form><form method="post" style="margin-top:8px"><input type="hidden" name="csrf_token" value="<?= uu_h($csrf) ?>"><input type="hidden" name="pending_id" value="<?= uu_h($pendingId) ?>"><button class="btn outline" name="action" value="cancel_pending">حذف بسته آماده‌شده</button></form></div></section><?php endif; ?>

<?php if($admin&&$restorePoints): ?><section class="card"><div class="pad"><div class="section-head"><div><h2>بازگشت امن</h2><p class="muted">مقصد را انتخاب کنید؛ هشدار و تأیید فقط هنگام شروع بازگشت نمایش داده می‌شود.</p></div></div><div class="restore-list"><?php foreach($restorePoints as $r): $restoreDb=!empty($r['migration_applied'])&&!empty($r['database_ready']); ?><article class="restore-row"><div class="restore-copy"><b class="version-flow"><span dir="ltr"><?= uu_h($r['to_version']) ?></span><span class="to">به</span><span dir="ltr"><?= uu_h($r['from_version']) ?></span></b><div class="restore-meta"><time class="human-time" datetime="<?= uu_h((string)$r['completed_at']) ?>"><?= uu_h((string)$r['completed_at']) ?></time><span><?= uu_fa((int)$r['file_count']) ?> فایل</span><span class="scope-pill <?= $restoreDb?'danger':'' ?>"><?= $restoreDb?'فایل‌ها + دیتابیس':'فقط فایل‌ها' ?></span></div></div><button class="btn outline small" type="button" data-rollback-open data-restore-id="<?= uu_h((string)$r['restore_id']) ?>" data-from="<?= uu_h((string)$r['to_version']) ?>" data-to="<?= uu_h((string)$r['from_version']) ?>" data-db="<?= $restoreDb?'1':'0' ?>">بازگشت</button></article><?php endforeach; ?></div></div></section><?php endif; ?>

<?php if($admin): ?><section class="card"><div class="pad"><div class="section-head"><div><h2>آخرین فعالیت‌ها</h2><p class="muted">سه رویداد اخیر برای تصمیم سریع؛ جزئیات بیشتر در ادامه.</p></div></div><?php if(!$history): ?><p class="muted">هنوز عملیاتی ثبت نشده است.</p><?php else: ?><div class="history-list"><?php foreach(array_slice($history,0,3) as $h): ?><article class="history-row"><div class="history-copy"><b class="history-result <?= !empty($h['success'])?'ok':'bad' ?>"><?= uu_h(uu_history_label($h)) ?></b><div class="history-meta"><span dir="ltr"><?= uu_h($h['from_version']??'—') ?> → <?= uu_h($h['to_version']??'—') ?></span><time class="human-time" datetime="<?= uu_h((string)($h['recorded_at']??'')) ?>"><?= uu_h((string)($h['recorded_at']??'')) ?></time></div><div class="actor">اجراکننده: <?= uu_h(uu_history_actor($h)) ?></div></div></article><?php endforeach; ?></div><?php if(count($history)>3): ?><details class="disclosure"><summary>مشاهده تاریخچه بیشتر</summary><div class="history-list"><?php foreach(array_slice($history,3) as $h): ?><article class="history-row"><div class="history-copy"><b class="history-result <?= !empty($h['success'])?'ok':'bad' ?>"><?= uu_h(uu_history_label($h)) ?></b><div class="history-meta"><span dir="ltr"><?= uu_h($h['from_version']??'—') ?> → <?= uu_h($h['to_version']??'—') ?></span><time class="human-time" datetime="<?= uu_h((string)($h['recorded_at']??'')) ?>"><?= uu_h((string)($h['recorded_at']??'')) ?></time></div><div class="actor">اجراکننده: <?= uu_h(uu_history_actor($h)) ?></div></div></article><?php endforeach; ?></div></details><?php endif; ?><?php endif; ?><details class="disclosure"><summary>جزئیات فنی موتور</summary><div class="technical-grid"><div><small>نسخه موتور فعال</small><b dir="ltr"><?= uu_h(SOKNA_UPDATER_RUNTIME_VERSION) ?></b></div><div><small>روش بازیابی</small><b>Restore Point + بازگردانی خودکار</b></div><div><small>بررسی سلامت</small><b>پایه؛ جایگزین UAT عملیاتی نیست</b></div><div><small>ثبت تاریخچه</small><b><?= $historyWriteReady?'آماده':'مختل' ?></b></div></div></details></div></section><?php endif; ?>
<?php endif; ?>
</main>

<div class="modal-layer hidden" id="rollbackDialog" role="dialog" aria-modal="true" aria-labelledby="rollbackTitle"><section class="modal"><h2 id="rollbackTitle">بازگشت به نسخه قبل؟</h2><p id="rollbackCopy" class="muted"></p><div class="notice warn hidden" id="rollbackDbWarning">این Restore Point دیتابیس را نیز به زمان قبل برمی‌گرداند؛ داده‌های ثبت‌شده بعد از آن ممکن است از بین بروند.</div><form method="post" id="rollbackForm"><input type="hidden" name="csrf_token" value="<?= uu_h($csrf) ?>"><input type="hidden" name="restore_id" id="rollbackRestoreId"><input type="hidden" name="confirm" value="1"><label class="field hidden" id="rollbackConfirmField"><span>برای تأیید بازگشت دیتابیس بنویسید: بازگشت</span><input name="confirm_text" id="rollbackConfirmText" autocomplete="off"></label><div class="modal-actions"><button class="btn danger" name="action" value="start_rollback" id="rollbackSubmit">شروع بازگشت</button><button class="btn outline" type="button" id="rollbackCancel">انصراف</button></div></form></section></div>
<script>
(()=>{
  const digits='۰۱۲۳۴۵۶۷۸۹';
  document.querySelectorAll('.human-time[datetime]').forEach(el=>{const raw=el.getAttribute('datetime');if(!raw)return;try{const d=new Date(raw);if(Number.isNaN(d.getTime()))return;el.textContent=new Intl.DateTimeFormat('fa-IR-u-ca-persian',{year:'numeric',month:'short',day:'numeric',hour:'2-digit',minute:'2-digit'}).format(d)}catch(_){}});
  const textFor=(el)=>{const v=el.validity||{};const label=el.closest('label');const name=(el.dataset.validationLabel||label?.querySelector('span')?.textContent||'این فیلد').trim();if(v.valueMissing)return name+' را تکمیل کنید.';if(v.typeMismatch)return 'مقدار واردشده برای «'+name+'» معتبر نیست.';return 'مقدار «'+name+'» معتبر نیست.'};
  const clear=(el)=>{el.removeAttribute('aria-invalid');const box=el.closest('.field,.check,.drop');if(!box)return;box.classList.remove('has-error');box.querySelector(':scope > .client-error')?.remove()};
  const show=(el)=>{const box=el.closest('.field,.check,.drop')||el.parentElement;if(!box)return;clear(el);el.setAttribute('aria-invalid','true');box.classList.add('has-error');const m=document.createElement('small');m.className='client-error';m.textContent=textFor(el);box.appendChild(m)};
  document.addEventListener('invalid',e=>{const el=e.target;if(!(el instanceof HTMLInputElement||el instanceof HTMLSelectElement||el instanceof HTMLTextAreaElement))return;e.preventDefault();show(el)},true);
  document.addEventListener('input',e=>{const el=e.target;if(el instanceof HTMLInputElement||el instanceof HTMLSelectElement||el instanceof HTMLTextAreaElement){if(el.validity.valid)clear(el)}},true);
  const dialog=document.getElementById('rollbackDialog'),rid=document.getElementById('rollbackRestoreId'),copy=document.getElementById('rollbackCopy'),warn=document.getElementById('rollbackDbWarning'),field=document.getElementById('rollbackConfirmField'),text=document.getElementById('rollbackConfirmText'),cancel=document.getElementById('rollbackCancel');let opener=null;
  const close=()=>{dialog?.classList.add('hidden');text&&(text.value='');opener?.focus?.();opener=null};
  document.querySelectorAll('[data-rollback-open]').forEach(btn=>btn.addEventListener('click',()=>{opener=btn;const db=btn.dataset.db==='1';rid.value=btn.dataset.restoreId||'';copy.textContent='نسخه '+(btn.dataset.from||'')+' به '+(btn.dataset.to||'')+' بازگردانده می‌شود.';warn.classList.toggle('hidden',!db);field.classList.toggle('hidden',!db);text.required=db;dialog.classList.remove('hidden');(db?text:document.getElementById('rollbackSubmit'))?.focus()}));
  cancel?.addEventListener('click',close);dialog?.addEventListener('click',e=>{if(e.target===dialog)close()});document.addEventListener('keydown',e=>{if(e.key==='Escape'&&!dialog?.classList.contains('hidden')){e.preventDefault();close()}});
})();
</script>
<?php if($public&&$operationActive): ?><script>
(()=>{const endpoint='./?api=1',csrf=<?= json_encode($csrf,JSON_UNESCAPED_SLASHES) ?>;let job=<?= json_encode($public,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>,busy=false,failures=0;const q=id=>document.getElementById(id);async function call(){const r=await fetch(endpoint,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json'},credentials:'same-origin',cache:'no-store',body:JSON.stringify({action:'step',job_id:job.id,csrf_token:csrf})});const d=await r.json().catch(()=>({}));if(!r.ok||!d.success)throw new Error(d.message||'ارتباط با موتور به‌روزرسانی انجام نشد.');return d.job}function render(n){job=n;q('bar').style.width=(n.progress||0)+'%';q('pct').textContent=String(n.progress||0).replace(/\d/g,d=>'۰۱۲۳۴۵۶۷۸۹'[d])+'٪';q('message').textContent=n.message||'';q('badge').textContent=n.status==='completed'?'تکمیل‌شده':n.status==='failed'?'ناموفق':n.status==='recovery_required'?'در حال بازیابی':'در حال اجرا';const e=q('jobError');e.textContent=n.error||'';e.classList.toggle('hide',!n.error);if(['completed','failed'].includes(n.status))setTimeout(()=>location.href='./?job='+encodeURIComponent(n.id),900)}async function tick(){if(busy||failures>=3||!['running','recovery_required'].includes(job.status))return;busy=true;try{render(await call());failures=0}catch(e){failures++;const b=q('jobError');b.textContent=e.message+' — دکمه ادامه عملیات برای تلاش دستی در دسترس است.';b.classList.remove('hide')}finally{busy=false;if(failures<3&&['running','recovery_required'].includes(job.status))setTimeout(tick,900)}}setTimeout(tick,500)})();
</script><?php endif; ?></body></html>

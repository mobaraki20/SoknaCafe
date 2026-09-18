<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
require_login(['admin']);
require dirname(__DIR__) . '/includes/panel_layout.php';
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf($_POST['csrf_token']??null);
    try{
        $action=(string)($_POST['action']??'save');
        if($action==='test'){
            accommodation_test_connection((int)current_user()['id']);
            flash('success','ارتباط امن اقامتگاه با موفقیت بررسی شد.');
        }else{
            $enabled=isset($_POST['accommodation_connection_enabled']);
            accommodation_save_connection_settings($enabled,(string)($_POST['accommodation_api_base_url']??''),(string)($_POST['accommodation_api_key']??''),(int)current_user()['id']);
            flash('success',$enabled?'اتصال اقامتگاه ذخیره شد.':'اتصال اقامتگاه خاموش شد؛ سوابق مالی حفظ می‌شوند.');
        }
    }catch(Throwable $e){error_log('accommodation settings: '.$e->getMessage());flash('error',safe_business_error_message($e,'عملیات اتصال اقامتگاه انجام نشد.'));}
    redirect('accommodation_settings.php');
}
panel_header('تنظیم اتصال اقامتگاه','accommodation_settings');
?>
<section class="card settings-section"><div class="card-head"><div><h2>اتصال به اقامتگاه</h2><small>تنظیمات فنی فقط برای مدیر یا فرد دارای مجوز نمایش داده می‌شود.</small></div><?php if(accommodation_live_operations_enabled()||accommodation_history_exists()): ?><a class="btn btn-light" href="accommodation.php">انتقال‌ها و بازیابی مالی</a><?php endif; ?></div><div class="card-body"><form method="post" class="form-grid"><?= csrf_field() ?>
<div class="form-group full feature-switch"><label><input type="checkbox" name="accommodation_connection_enabled" <?= accommodation_live_operations_enabled()?'checked':'' ?>> اتصال به اقامتگاه فعال باشد</label><small class="muted">در حالت خاموش فقط تماس زنده با اقامتگاه، جست‌وجوی رزرو و ثبت یا برگشت تازه متوقف می‌شود؛ سابقه مالی، هشدارهای ناسازگاری و بازیابی محلی همچنان در دسترس می‌مانند.</small></div>
<div class="form-group full"><label>آدرس اتصال اقامتگاه</label><input class="form-control ltr-input" dir="ltr" type="url" name="accommodation_api_base_url" value="<?= e(accommodation_api_base_url()) ?>" placeholder="https://stay.example.com" autocomplete="off"><small>فقط HTTPS پذیرفته می‌شود.</small></div>
<div class="form-group full"><label>کلید اتصال</label><input class="form-control ltr-input" dir="ltr" type="password" name="accommodation_api_key" value="" placeholder="<?= setting('accommodation_api_key_fingerprint')?'کلید ذخیره شده؛ برای حفظ آن خالی بگذار':'کلید را وارد کن' ?>" autocomplete="new-password"><small>برای امنیت، کلید کامل دوباره نمایش داده نمی‌شود؛ شناسه ثبت‌شده: <code><?= e(setting('accommodation_api_key_fingerprint')?:'ثبت نشده') ?></code></small></div>
<div class="form-group full"><label>دامنه این کلید</label><div class="alert alert-info">این کلید فقط عملیات زنده اتصال را کنترل می‌کند. سوابق انتقال، اسناد مالی و مسیرهای بازیابی حتی در حالت خاموش حفظ و قابل رسیدگی‌اند.</div></div>
<div class="form-group full actions"><button class="btn btn-primary" name="action" value="save">ذخیره اتصال</button><button class="btn btn-light" name="action" value="test" formnovalidate>آزمایش اتصال</button></div>
</form><div class="integration-health-grid"><div><span>آخرین اتصال موفق</span><strong><?= setting('accommodation_api_last_success_at')?e(format_jalali_compact(setting('accommodation_api_last_success_at'))):'ثبت نشده' ?></strong></div><div><span>آخرین خطا</span><strong class="<?= setting('accommodation_api_last_error')?'text-danger':'' ?>"><?= e(setting('accommodation_api_last_error')?:'خطایی ثبت نشده') ?></strong><?php if(setting('accommodation_api_last_error_at')): ?><small><?= e(format_jalali_compact(setting('accommodation_api_last_error_at'))) ?></small><?php endif; ?></div></div><div class="alert alert-info" style="margin-top:12px">کلید اتصال به‌صورت امن ذخیره می‌شود و پس از ثبت، مقدار کامل آن در رابط نمایش داده نمی‌شود.</div></div></section>
<?php panel_footer(); ?>

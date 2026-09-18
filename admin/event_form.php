<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
require_login(['admin']);
sokna_module_require('marketing');
require dirname(__DIR__) . '/includes/panel_layout.php';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$copyFrom = $id === 0 ? (int)($_GET['copy_from'] ?? 0) : 0;
$event = null;
$copySourceTitle = '';

if ($id) {
    $stmt = db()->prepare('SELECT * FROM events WHERE id=?');
    $stmt->execute([$id]);
    $event = $stmt->fetch() ?: null;
    if (!$event) render_recovery_error_page(404, 'رویداد پیدا نشد', 'ممکن است رویداد حذف شده باشد یا پیوند قدیمی باشد.', 'events.php', 'بازگشت به رویدادها');
} elseif ($copyFrom) {
    $stmt = db()->prepare('SELECT * FROM events WHERE id=?');
    $stmt->execute([$copyFrom]);
    $source = $stmt->fetch() ?: null;
    if (!$source) render_recovery_error_page(404, 'رویداد مبنا پیدا نشد', 'رویدادی که برای ساخت نسخه جدید انتخاب شده بود دیگر در دسترس نیست.', 'events.php', 'بازگشت به رویدادها');
    $copySourceTitle = (string)$source['title'];
    $copyBaseTitle = trim((string)preg_replace('/(?:\s*[—-]\s*نوبت جدید)+\s*$/u', '', $copySourceTitle));
    if ($copyBaseTitle === '') $copyBaseTitle = $copySourceTitle;
    $event = $source;
    $event['id'] = 0;
    $event['title'] = $copyBaseTitle . ' — نوبت جدید';
    $event['starts_at'] = null;
    $event['ends_at'] = null;
    $event['active'] = 0;
    $event['featured'] = 0;
    $event['cancelled_at'] = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? null);
    $oldImage = $event['image_path'] ?? null;
    $uploadedImage = null;
    try {
        $title = text_substr(trim((string)($_POST['title'] ?? '')), 0, 180);
        $short = text_substr(trim((string)($_POST['short_description'] ?? '')), 0, 500);
        $description = trim((string)($_POST['description'] ?? ''));
        $startDateJ = trim((string)($_POST['starts_date_j'] ?? ''));
        $endDateJ = trim((string)($_POST['ends_date_j'] ?? ''));
        $startTime = trim(en_digits((string)($_POST['starts_time'] ?? '')));
        $endTime = trim(en_digits((string)($_POST['ends_time'] ?? '')));
        $startsAt = parse_optional_jalali_datetime($startDateJ, $startTime, 'شروع رویداد');
        $endsAt = parse_optional_jalali_datetime($endDateJ, $endTime, 'پایان رویداد');
        $venue = text_substr(trim((string)($_POST['venue'] ?? '')), 0, 180);
        $admission = text_substr(trim((string)($_POST['admission_text'] ?? '')), 0, 180);
        $feeRaw = trim(en_digits((string)($_POST['fee_amount'] ?? '')));
        $feeDigits = preg_replace('/[\s,\x{060C}\x{066C}]/u', '', $feeRaw) ?? '';
        if ($feeRaw !== '' && ($feeDigits === '' || !ctype_digit($feeDigits))) throw new RuntimeException('هزینه را فقط به‌صورت عددی و به تومان وارد کن.');
        if ($feeDigits !== '' && (float)$feeDigits > 4294967295) throw new RuntimeException('مبلغ هزینه از محدوده قابل ثبت بیشتر است.');
        $feeAmount = $feeRaw === '' ? null : (int)$feeDigits;
        $capacityText = trim(en_digits((string)($_POST['capacity'] ?? '')));
        $capacity = $capacityText === '' ? null : max(0, (int)$capacityText);
        $registrationType = (string)($_POST['registration_type'] ?? 'none');
        $registrationValue = text_substr(trim((string)($_POST['registration_value'] ?? '')), 0, 500);
        $sort = (int)en_digits((string)($_POST['sort_order'] ?? 0));
        $active = isset($_POST['active']) ? 1 : 0;
        $featured = isset($_POST['featured']) ? 1 : 0;

        if ($title === '' || $startsAt === null || $endsAt === null) throw new RuntimeException('عنوان، زمان شروع و زمان پایان رو درست وارد کن.');
        if (strtotime($endsAt) <= strtotime($startsAt)) throw new RuntimeException('زمان پایان باید بعد از شروع باشه.');
        if (!in_array($registrationType, ['none','phone','link','whatsapp','in_person'], true)) throw new RuntimeException('روش ثبت‌نام معتبر نیست.');
        if ($registrationType === 'link' && safe_external_url($registrationValue) === '') throw new RuntimeException('برای ثبت‌نام لینکی، یک آدرس کامل و معتبر وارد کن.');
        if ($registrationType === 'phone' && preg_replace('/[^0-9+]/', '', en_digits($registrationValue)) === '') throw new RuntimeException('برای هماهنگی تلفنی، شماره تماس رو وارد کن.');
        if ($registrationType === 'whatsapp' && whatsapp_number(setting('whatsapp_number')) === '') throw new RuntimeException('برای ثبت‌نام واتس‌اپ، ابتدا شماره کاری واتس‌اپ را در تنظیمات عمومی ثبت کن.');

        $imageChoice = resolve_image_input($_FILES['image'] ?? [], $oldImage, $_POST);
        $image = $imageChoice['path'];
        $uploadedImage = $imageChoice['uploaded'];
        $imageChanged = $imageChoice['changed'];
        $wasExisting=$id>0;
        if ($id) {
            $stmt = db()->prepare('UPDATE events SET title=?,short_description=?,description=?,image_path=?,starts_at=?,ends_at=?,venue=?,admission_text=?,fee_amount=?,capacity=?,registration_type=?,registration_value=?,active=?,featured=?,sort_order=? WHERE id=?');
            $stmt->execute([$title,$short,$description,$image,$startsAt,$endsAt,$venue,$admission,$feeAmount,$capacity,$registrationType,$registrationValue,$active,$featured,$sort,$id]);
            flash('success', 'تغییرات رویداد ذخیره شد.');
        } else {
            $stmt = db()->prepare('INSERT INTO events(title,short_description,description,image_path,starts_at,ends_at,venue,admission_text,fee_amount,capacity,registration_type,registration_value,active,featured,sort_order) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([$title,$short,$description,$image,$startsAt,$endsAt,$venue,$admission,$feeAmount,$capacity,$registrationType,$registrationValue,$active,$featured,$sort]);
            $id=(int)db()->lastInsertId();
            flash('success', 'نوبت تازه رویداد به‌صورت ' . ($active ? 'منتشرشده' : 'پیش‌نویس') . ' ساخته شد.');
        }
        audit_log_write($wasExisting?'event.updated':'event.created','event',$id,['title'=>$title,'starts_at'=>$startsAt,'ends_at'=>$endsAt,'active'=>$active,'featured'=>$featured,'fee_amount'=>$feeAmount],(int)(current_user()['id']??0));
        if (($imageChanged ?? false) && $oldImage) delete_upload_path($oldImage);
        redirect('events.php');
    } catch (Throwable $e) {
        if ($uploadedImage) delete_upload_path($uploadedImage);
        error_log('event save: '.$e->getMessage());
        flash('error', safe_business_error_message($e, 'ذخیره رویداد انجام نشد.'));
        $event = array_merge($event ?? [], $_POST);
    }
}

$startSource = !empty($event['starts_at']) ? (string)$event['starts_at'] : null;
$endSource = !empty($event['ends_at']) ? (string)$event['ends_at'] : null;
if ($id && $startSource && !$endSource) $endSource = date('Y-m-d H:i:s', strtotime($startSource . ' +2 hours'));
$startDateJValue = (string)($_POST['starts_date_j'] ?? ($startSource ? jalali_date_input($startSource) : ''));
$endDateJValue = (string)($_POST['ends_date_j'] ?? ($endSource ? jalali_date_input($endSource) : ''));
$startTimeValue = (string)($_POST['starts_time'] ?? ($startSource ? date('H:i', strtotime($startSource)) : ''));
$endTimeValue = (string)($_POST['ends_time'] ?? ($endSource ? date('H:i', strtotime($endSource)) : ''));
$displayFeeAmount = event_display_fee_amount($event ?? []);
$displayAdmissionText = event_display_admission_text($event ?? []);
$title = $id ? 'ویرایش رویداد' : ($copyFrom ? 'ساخت نوبت جدید' : 'رویداد تازه');
panel_header($title, 'events');
?>
<?php if ($copyFrom): ?><div class="alert alert-info duplicate-context"><?= ui_icon('copy') ?><div><strong>ساخت نوبت تازه از «<?= e($copySourceTitle) ?>»</strong><p>تصویر و متن‌ها آماده‌اند؛ فقط تاریخ و ساعت تازه را وارد کن. ثبت‌نام‌ها و سوابق نوبت قبلی منتقل نمی‌شوند.</p></div></div><?php endif; ?>
<?php if ($id): $status = event_lifecycle_status($event); ?><div class="event-form-status"><span class="badge event-status-<?= e($status) ?>"><?= e(event_lifecycle_label($status)) ?></span><small><?= $status==='cancelled'?'این رویداد لغو شده و فقط از صفحه رویدادها قابل بازگردانی است.':'پس از زمان پایان، رویداد خودکار از منوی مهمان حذف و در همین صفحه زیر فیلتر «پایان‌یافته» نگهداری می‌شود.' ?></small></div><?php endif; ?>
<section class="card"><div class="card-body"><form method="post" enctype="multipart/form-data" class="form-grid"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
<div class="form-group full"><label>عنوان رویداد</label><input class="form-control" id="eventTitle" name="title" maxlength="180" value="<?= e($event['title'] ?? '') ?>" required></div>
<div class="form-group full"><label>معرفی کوتاه</label><textarea class="form-control" name="short_description" maxlength="500" placeholder="یک جمله کوتاه و صمیمی برای کارت رویداد"><?= e($event['short_description'] ?? '') ?></textarea></div>
<div class="form-group full"><label>توضیحات کامل</label><textarea class="form-control" name="description"><?= e($event['description'] ?? '') ?></textarea></div>
<div class="form-group"><label>شروع رویداد</label><div class="jalali-datetime-grid"><div class="jalali-date-control"><input class="form-control" id="eventStartDateJ" name="starts_date_j" data-jalali-date inputmode="none" value="<?= e($startDateJValue) ?>" placeholder="۱۴۰۵/۰۵/۰۸" aria-label="تاریخ شروع شمسی" required><button class="jalali-date-button" type="button" data-open-jalali="eventStartDateJ" aria-label="انتخاب تاریخ شروع از تقویم"><?= ui_icon('calendar') ?></button></div><input class="form-control ltr-input" dir="ltr" id="eventStartTime" type="time" name="starts_time" data-minute-step="5" step="300" value="<?= e($startTimeValue) ?>" aria-label="ساعت شروع" required></div><small class="muted">تاریخ را به تقویم شمسی وارد کن.</small></div>
<div class="form-group"><label>پایان رویداد</label><div class="jalali-datetime-grid"><div class="jalali-date-control"><input class="form-control" id="eventEndDateJ" name="ends_date_j" data-jalali-date inputmode="none" value="<?= e($endDateJValue) ?>" placeholder="۱۴۰۵/۰۵/۰۸" aria-label="تاریخ پایان شمسی" required><button class="jalali-date-button" type="button" data-open-jalali="eventEndDateJ" aria-label="انتخاب تاریخ پایان از تقویم"><?= ui_icon('calendar') ?></button></div><input class="form-control ltr-input" dir="ltr" type="time" name="ends_time" data-minute-step="5" step="300" value="<?= e($endTimeValue) ?>" aria-label="ساعت پایان" required></div><small class="muted">پس از این زمان، رویداد خودکار پایان‌یافته می‌شود.</small></div>
<div class="form-group"><label>محل برگزاری</label><input class="form-control" name="venue" maxlength="180" value="<?= e($event['venue'] ?? '') ?>" placeholder="مثلاً حیاط کافه"></div>
<div class="form-group"><label>هزینه شرکت در رویداد (تومان)</label><input class="form-control" name="fee_amount" inputmode="numeric" enterkeyhint="next" data-money-input value="<?= $displayFeeAmount!==null?e(money_input_display_value((string)$displayFeeAmount)):'' ?>" placeholder="مثلاً ۱٬۲۰۰٬۰۰۰"><small class="muted">برای رویداد رایگان عدد صفر وارد کن؛ اگر مبلغ هنوز مشخص نیست خالی بگذار.</small></div>
<div class="form-group"><label>توضیحات تکمیلی حضور یا هزینه</label><input class="form-control" name="admission_text" maxlength="180" value="<?= e($displayAdmissionText) ?>" placeholder="مثلاً مبلغ برای هر نفر و شامل پذیرایی است"><small class="muted">عدد مبلغ را اینجا ننویس؛ فقط جزئیات لازم برای مهمان را ثبت کن.</small></div>
<div class="form-group"><label>ظرفیت کل (اختیاری)</label><input class="form-control" type="text" inputmode="numeric" enterkeyhint="next" name="capacity" value="<?= e(fa_digits((string)($event['capacity'] ?? ''))) ?>"></div>
<div class="form-group"><label>روش حضور یا ثبت‌نام</label><select class="form-control" name="registration_type" data-choice-mode="compact"><?php foreach(['none'=>'بدون ثبت‌نام','whatsapp'=>'ثبت‌نام یا اطلاعات از واتس‌اپ','phone'=>'هماهنگی تلفنی','link'=>'لینک ثبت‌نام','in_person'=>'هماهنگی حضوری'] as $value=>$label): ?><option value="<?= e($value) ?>" <?= ($event['registration_type'] ?? 'none') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
<div class="form-group full"><label>لینک، شماره تماس یا پیام آماده واتس‌اپ</label><input class="form-control" name="registration_value" maxlength="500" value="<?= e($event['registration_value'] ?? '') ?>" placeholder="برای واتس‌اپ خالی بگذار تا پیام رویداد خودکار ساخته شود"><small class="muted">در حالت واتس‌اپ، شماره از تنظیمات عمومی مجموعه خوانده می‌شود.</small></div>
<div class="form-group"><label>ترتیب نمایش</label><input class="form-control" type="text" inputmode="numeric" enterkeyhint="done" name="sort_order" value="<?= e(fa_digits((string)($event['sort_order'] ?? 0))) ?>"></div>
<?= image_picker_html($event['image_path'] ?? null, 'تصویر رویداد', 'تصویر افقی ۱۲:۷ پیشنهاد می‌شود. از آلبوم سایت یا دستگاه انتخاب کن.') ?>
<div class="form-group full check-row"><label><input type="checkbox" name="active" <?= !isset($event['active']) || $event['active'] ? 'checked' : '' ?>> انتشار در منوی مهمان</label><label><input type="checkbox" name="featured" <?= !empty($event['featured']) ? 'checked' : '' ?>> برجسته در منو</label></div>
<div class="form-group full actions"><button class="btn btn-primary">ذخیره رویداد</button><a class="btn btn-light" href="events.php">بازگشت</a></div>
</form></div></section>
<?php panel_footer(); ?>

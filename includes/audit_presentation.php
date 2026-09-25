<?php
declare(strict_types=1);

function audit_details(array $row): array
{
    if(isset($row['details']) && is_array($row['details'])) return $row['details'];
    $raw=(string)($row['details_json']??'');
    if($raw==='')return [];
    try{$decoded=json_decode($raw,true,32,JSON_THROW_ON_ERROR);return is_array($decoded)?$decoded:[];}catch(Throwable){return [];}
}

function audit_detail_name(array $details): string
{
    foreach(['table_name','name','item_name','title'] as $key){$v=trim((string)($details[$key]??''));if($v!=='')return $v;}
    $after=$details['after']??null;
    if(is_array($after)){foreach(['name','table_name','item_name'] as $key){$v=trim((string)($after[$key]??''));if($v!=='')return $v;}}
    return '';
}


function audit_family(string $action): string
{
    if (str_starts_with($action,'settlement.') || str_starts_with($action,'invoice.') || str_starts_with($action,'subscriber.') || str_starts_with($action,'financial_period.')) return 'finance';
    if (str_starts_with($action,'order.') || str_starts_with($action,'table.') || str_starts_with($action,'waiter_call.') || str_starts_with($action,'preparation.')) return 'orders-tables';
    if (str_starts_with($action,'inventory.') || str_starts_with($action,'purchase.')) return 'inventory';
    if (str_starts_with($action,'menu.') || str_starts_with($action,'marketing.') || str_starts_with($action,'event.') || str_starts_with($action,'guest_message')) return 'menu-content';
    if (str_starts_with($action,'user.') || str_starts_with($action,'operations.') || str_starts_with($action,'print_template_') || str_starts_with($action,'print_agent_') || str_starts_with($action,'print_destination_')) return 'settings-access';
    if (str_starts_with($action,'center_') || str_starts_with($action,'push.') || str_starts_with($action,'backup.') || str_starts_with($action,'print_job_')) return 'connection-system';
    return 'other';
}

function audit_actor_label(array $row): string
{
    $snapshot=trim((string)($row['actor_display_name_snapshot']??''));
    if($snapshot!=='')return $snapshot;
    $live=trim((string)($row['display_name']??''));
    return $live!==''?$live:'کاربر حذف‌شده';
}

function audit_human_context(array $row): string
{
    $d=audit_details($row);$parts=[];
    $orderId=(int)($d['order_id']??0);if($orderId>0)$parts[]=order_display_label($orderId);
    $table=trim((string)($d['table_name']??''));if($table!=='')$parts[]='میز '.fa_digits($table);
    $invoice=(int)($d['invoice_number']??0);if($invoice>0)$parts[]='فاکتور '.fa_digits($invoice);
    return implode(' · ',array_values(array_unique($parts)));
}

function audit_human_summary(array $row): string
{
    $action=(string)($row['action']??'');
    $details=audit_details($row);
    $name=audit_detail_name($details);
    $suffix=$name!==''?' «'.$name.'»':'';
    if($action==='order.item_quantity_adjusted'){
        $item=trim((string)($details['item_name']??$name));$before=(int)($details['previous_quantity']??$details['before_quantity']??0);$after=(int)($details['new_quantity']??$details['after_quantity']??0);$order=(int)($details['order_id']??0);
        $subject=$item!==''?'«'.$item.'»':'یک آیتم';$orderText=$order>0?' در '.order_display_label($order):'';
        return 'تعداد '.$subject.$orderText.' از '.fa_digits($before).' به '.fa_digits($after).' تغییر کرد';
    }
    if($action==='invoice.discount_changed'){
        $before=(int)($details['before']??$details['previous_discount']??0);$after=(int)($details['after']??$details['new_discount']??0);
        return 'تخفیف حساب از '.toman($before).' به '.toman($after).' تغییر کرد';
    }
    if($action==='settlement.completed'){ $table=trim((string)($details['table_name']??'')); return $table!==''?'حساب میز '.fa_digits($table).' را تسویه کرد':'یک حساب را تسویه کرد'; }
    if($action==='print_template_activated' && $name!=='') return 'قالب چاپ «'.$name.'» را فعال کرد';
    $map=[
        'order.status_changed'=>'وضعیت یک سفارش را تغییر داد',
        'order.item_quantity_adjusted'=>'تعداد یک آیتم حساب را اصلاح کرد',
        'order.item_added_again'=>'آیتم تازه‌ای به حساب اضافه کرد',
        'guest_order_acceptance.changed'=>'پذیرش سفارش مهمان را تغییر داد',
        'order_acceptance.changed'=>'پذیرش سفارش آنلاین را تغییر داد',
        'settlement.completed'=>'یک حساب را تسویه کرد',
        'settlement.reversed'=>'یک تسویه را برگشت زد',
        'settlement.voided'=>'یک تسویه را باطل کرد',
        'settlement.invoice_reprinted'=>'فاکتور را دوباره چاپ کرد',
        'invoice.discount_changed'=>'تخفیف یک فاکتور را تغییر داد',
        'waiter_call.completed'=>'به فراخوان مهمان رسیدگی کرد',
        'preparation.claimed'=>'یک سفارش آماده‌سازی را گرفت',
        'preparation.adjustment_applied'=>'یک اصلاحیه آماده‌سازی را اعمال کرد',
        'table.session_moved'=>'حساب یک میز را منتقل کرد',
        'table.created'=>'میز'.$suffix.' را ساخت',
        'table.updated'=>'مشخصات میز'.$suffix.' را ویرایش کرد',
        'table.status_changed'=>'وضعیت یک میز را تغییر داد',
        'table.deleted'=>'میز'.$suffix.' را حذف کرد',
        'table.qr_rotated'=>'QR میز'.$suffix.' را جایگزین کرد',
        'table.qr_restored'=>'QR قبلی میز'.$suffix.' را برگرداند',
        'inventory.item_created'=>'کالای انبار'.$suffix.' را ساخت',
        'inventory.item_updated'=>'مشخصات کالای انبار'.$suffix.' را ویرایش کرد',
        'inventory.item_active_changed'=>'وضعیت یک کالای انبار را تغییر داد',
        'inventory.item_review_confirmed'=>'بازبینی کالای انبار'.$suffix.' را تأیید کرد',
        'inventory.category_created'=>'دسته انبار'.$suffix.' را ساخت',
        'inventory.category_updated'=>'نام دسته انبار را تغییر داد',
        'inventory.category_active_changed'=>'وضعیت دسته انبار'.$suffix.' را تغییر داد',
        'menu.definition_created'=>'منو'.$suffix.' را ساخت',
        'menu.definition_updated'=>'تنظیمات منو'.$suffix.' را ویرایش کرد',
        'menu.category_created'=>'دسته منو'.$suffix.' را ساخت',
        'menu.category_updated'=>'دسته منو'.$suffix.' را ویرایش کرد',
        'menu.category_active_changed'=>'وضعیت دسته منو'.$suffix.' را تغییر داد',
        'menu.category_deleted'=>'دسته منو'.$suffix.' را حذف کرد',
        'menu.item_created'=>'آیتم منو'.$suffix.' را ساخت',
        'menu.item_important_updated'=>'اطلاعات مهم آیتم منو'.$suffix.' را تغییر داد',
        'menu.item_orderability_changed'=>'وضعیت سفارش‌پذیری آیتم منو'.$suffix.' را تغییر داد',
        'menu.item_publication_changed'=>'انتشار آیتم منو'.$suffix.' را تغییر داد',
        'menu.items_bulk_updated'=>'یک تغییر گروهی روی آیتم‌های منو انجام داد',
        'menu.csv_import_applied'=>'ورود گروهی منو را اعمال کرد',
        'menu.tag_active_changed'=>'وضعیت برچسب'.$suffix.' را تغییر داد',
        'menu.tag_deleted'=>'برچسب'.$suffix.' را حذف کرد',
        'guest_messages.updated'=>'متن‌های تجربه مهمان را تغییر داد',
        'guest_messages.reset'=>'متن‌های تجربه مهمان را به پیش‌فرض برگرداند',
        'guest_message.reset'=>'یک متن تجربه مهمان را به پیش‌فرض برگرداند',
        'menu.category_order_changed'=>'چیدمان دسته‌بندی‌های منو را تغییر داد',
        'menu.item_order_changed'=>'چیدمان آیتم‌های یک دسته را تغییر داد',
        'marketing.selection_mode_changed'=>'روش نمایش کمپین‌ها را تغییر داد',
        'marketing.campaign_active_changed'=>'وضعیت کمپین'.$suffix.' را تغییر داد',
        'marketing.campaign_deleted'=>'کمپین'.$suffix.' را حذف کرد',
        'marketing.campaign_updated'=>'کمپین'.$suffix.' را ویرایش کرد',
        'marketing.campaign_created'=>'کمپین'.$suffix.' را ساخت',
        'event.publication_changed'=>'انتشار رویداد'.$suffix.' را تغییر داد',
        'event.featured_changed'=>'وضعیت پیشنهاد ویژه رویداد'.$suffix.' را تغییر داد',
        'event.cancelled'=>'رویداد'.$suffix.' را لغو کرد',
        'event.restored_to_draft'=>'رویداد'.$suffix.' را به پیش‌نویس برگرداند',
        'event.deleted'=>'رویداد'.$suffix.' را حذف کرد',
        'event.updated'=>'رویداد'.$suffix.' را ویرایش کرد',
        'event.created'=>'رویداد'.$suffix.' را ساخت',
        'print_agent_active_changed'=>'وضعیت عامل چاپ'.$suffix.' را تغییر داد',
        'print_template_saved'=>'نسخه جدید قالب چاپ'.$suffix.' را ذخیره کرد',
        'print_template_imported'=>'قالب چاپ'.$suffix.' را وارد کرد',
        'print_template_activated'=>'قالب چاپ'.$suffix.' را فعال کرد',
        'print_template_deleted'=>'قالب چاپ'.$suffix.' را حذف کرد',
        'print_template_reset'=>'قالب چاپ را به نسخه استاندارد برگرداند',
        'print_job_retried'=>'یک درخواست چاپ را دوباره به صف فرستاد',
        'print_job_marked_unknown'=>'نتیجه یک درخواست چاپ را نامشخص ثبت کرد',
        'print_job_cancelled'=>'یک درخواست چاپ را لغو کرد',
        'push.device_disabled'=>'دریافت اعلان روی یک دستگاه را غیرفعال کرد',
        'inventory.count_started'=>'شمارش انبار را شروع کرد',
        'inventory.count_cancelled'=>'شمارش انبار را لغو کرد',
        'inventory.count_finalized'=>'شمارش انبار را نهایی کرد',
        'inventory.recipe_version_created'=>'دستور مصرف انبار یک آیتم منو را تغییر داد',
        'inventory.recipe_removed'=>'دستور مصرف انبار یک آیتم منو را حذف کرد',
        'inventory.movement_created'=>'یک تغییر موجودی ثبت کرد',
        'supply.need_created'=>'یک نیاز تأمین ثبت کرد',
        'supply.need_updated'=>'مقدار یک نیاز تأمین را اصلاح کرد',
        'supply.need_received'=>'خرید را ثبت و مستقیم وارد انبار کرد',
        'supply.need_unavailable'=>'ثبت کرد که یک نیاز این بار تهیه نشد',
        'supply.need_cancelled'=>'یک نیاز تأمین را لغو کرد',
        'inventory.item_created_from_supply'=>'هنگام خرید، کالای جدید انبار ساخت',
        'subscriber.created'=>'مشتری جدید ساخت',
        'subscriber.updated'=>'مشخصات مشتری را تغییر داد',
        'subscriber.payment'=>'پرداخت مشتری را ثبت کرد',
        'subscriber.payment_reversed'=>'پرداخت مشتری را برگشت زد',
        'user.access_updated'=>'دسترسی یک کاربر را تغییر داد',
        'user.permissions_changed'=>'دسترسی یک کاربر را تغییر داد',
        'user.active_changed'=>'وضعیت یک کاربر را تغییر داد',
        'operations.business_time_changed'=>'ساعات کاری یا شیفت‌ها را تغییر داد',
        'financial_period.closed'=>'یک دوره مالی را بست',
        'expense.created'=>'یک هزینه عمومی ثبت کرد',
        'expense.reversed'=>'یک هزینه را برگشت زد',
        'expense.corrected'=>'یک هزینه را با حفظ تاریخچه اصلاح کرد',
        'backup.offserver_exported'=>'نسخه پشتیبان خارج از سرور گرفت',
        'center_pair_success'=>'اتصال مرکز سکنا را برقرار کرد',
        'center_pair_failed'=>'برای اتصال مرکز سکنا تلاش کرد',
        'center_probe_success'=>'اتصال مرکز سکنا را آزمایش کرد',
        'center_probe_failed'=>'آزمایش اتصال مرکز سکنا ناموفق بود',
        'center_handoff_started'=>'ورود به مرکز سکنا را شروع کرد',
    ];
    if(isset($map[$action])) return $map[$action];
    if(str_starts_with($action,'inventory.')||str_starts_with($action,'supply.')||str_starts_with($action,'purchase.'))return 'در انبار یا خرید تغییری ثبت کرد';
    if(str_starts_with($action,'print'))return 'در تنظیمات چاپ اقدامی انجام داد';
    if(str_starts_with($action,'subscriber.'))return 'در پرونده مشتری اقدامی انجام داد';
    if(str_starts_with($action,'expense.'))return 'در هزینه‌های کافه اقدامی انجام داد';
    if(str_starts_with($action,'table.'))return 'در مدیریت میزها تغییری ثبت کرد';
    if(str_starts_with($action,'settlement.'))return 'روی یک تسویه اقدامی انجام داد';
    if(str_starts_with($action,'order.'))return 'روی یک سفارش اقدامی انجام داد';
    return 'یک اقدام مدیریتی ثبت کرد';
}


function audit_human_detail_rows(array $row): array
{
    $action=(string)($row['action']??'');
    $d=audit_details($row);
    $rows=[];
    $add=static function(string $label, mixed $value) use (&$rows): void {
        if($value===null||$value==='')return;
        $rows[]=['label'=>$label,'value'=>(string)$value];
    };
    if($action==='order.item_quantity_adjusted'){
        $add('آیتم',trim((string)($d['item_name']??'')));
        $add('تعداد قبل',fa_digits((int)($d['previous_quantity']??0)));
        $add('تعداد بعد',fa_digits((int)($d['new_quantity']??0)));
        $reason=trim((string)($d['reason']??''));if($reason!=='')$add('دلیل',$reason);
        if(array_key_exists('prepared_removed_quantity',$d)&&$d['prepared_removed_quantity']!==null)$add('حذف‌شده آماده',fa_digits((int)$d['prepared_removed_quantity']).' عدد');
        if(array_key_exists('unprepared_removed_quantity',$d))$add('حذف‌شده آماده‌نشده',fa_digits((int)$d['unprepared_removed_quantity']).' عدد');
        if((int)($d['inventory_restored_quantity']??0)>0)$add('برگشت به موجودی',fa_digits((int)$d['inventory_restored_quantity']).' عدد');
    } elseif($action==='settlement.completed'){
        $add('فاکتور',trim((string)($d['invoice_number']??'')));
        $add('میز',trim((string)($d['table_name']??'')));
        $destination=(string)($d['destination']??'');
        if($destination!=='')$add('نحوه ثبت',function_exists('settlement_destination_label')?settlement_destination_label($destination):$destination);
        $add('جمع اقلام',toman((int)($d['subtotal']??0)));
        $add('تخفیف',toman((int)($d['discount']??0)));
        $add('مبلغ نهایی',toman((int)($d['total']??0)));
        if(array_key_exists('print_requested',$d))$add('چاپ فاکتور',!empty($d['print_requested'])?(!empty($d['print_queued'])?'درخواست شد و وارد صف شد':'درخواست شد؛ ورود به صف تأیید نشد'):'درخواست نشد');
    } elseif($action==='print_template_activated'){
        $add('قالب',trim((string)($d['name']??'')));
        $add('نوع سند',trim((string)($d['template_key']??'')));
    } elseif($action==='center_probe_success'){
        $add('نتیجه','موفق');$add('نسخه مرکز',trim((string)($d['version']??'')));
    } elseif($action==='center_probe_failed'){
        $map=['invalid_pairing_key'=>'کلید اتصال نامعتبر','local_failure'=>'خطای محلی یا دسترسی','unauthorized'=>'عدم احراز هویت','timeout'=>'پایان مهلت اتصال'];
        $reason=trim((string)($d['reason']??''));$add('نتیجه','ناموفق');if($reason!=='')$add('دلیل',$map[$reason]??$reason);
    } elseif($action==='center_pair_success'){
        $add('نتیجه','اتصال برقرار شد');$add('نسخه مرکز',trim((string)($d['version']??'')));
    } elseif($action==='center_pair_failed'){
        $add('نتیجه','اتصال برقرار نشد');$reason=trim((string)($d['reason']??''));if($reason!=='')$add('دلیل',$reason);
    }
    return $rows;
}

function audit_human_details_text(array $row): string
{
    return implode(' | ',array_map(static fn(array $item): string=>$item['label'].': '.$item['value'],audit_human_detail_rows($row)));
}

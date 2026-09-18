<?php

declare(strict_types=1);

// Extracted from includes/functions.php during pre-operational P2 owner cleanup.
// Keep behavior-compatible global function names; functions.php remains the public bootstrap aggregator.

function message_definitions(): array
{
    return [
        'general' => [
            ['key'=>'search_placeholder','label'=>'متن جست‌وجوی منو','default'=>'چی دوست داری بخوری؟','optional'=>false,'where'=>'داخل کادر جست‌وجوی منوی مهمان.'],
            ['key'=>'menu_empty','label'=>'منوی خالی','default'=>'هنوز چیزی به منو اضافه نشده.','optional'=>false,'where'=>'وقتی هیچ آیتم فعالی برای نمایش در منوی مهمان وجود ندارد.'],
            ['key'=>'no_results','label'=>'نتیجه جست‌وجو پیدا نشد','default'=>'چیزی با این عبارت پیدا نکردیم.','optional'=>false,'where'=>'وقتی جست‌وجوی مهمان نتیجه‌ای ندارد.'],
            ['key'=>'item_unavailable','label'=>'آیتم ناموجود','default'=>'فعلاً موجود نیست','optional'=>false,'where'=>'روی آیتمی که واقعاً ناموجود است؛ در منوی میز و منوی عمومی.'],
            ['key'=>'featured_badge','label'=>'نشان پیشنهاد کافه','default'=>'پیشنهاد کافه','optional'=>false,'where'=>'Badge روی آیتم پیشنهادی.'],
            ['key'=>'featured_title','label'=>'عنوان پیشنهادهای کافه','default'=>'پیشنهادهای کافه','optional'=>false,'where'=>'عنوان بخش پیشنهادهای منو.'],
            ['key'=>'more_sokna_title','label'=>'عنوان بیشتر از کافه','default'=>'بیشتر از سکنا','optional'=>false,'where'=>'عنوان بخش لینک‌های تکمیلی پایین منو.'],
            ['key'=>'menu_label_table','label'=>'زیرعنوان منوی میز','default'=>'منوی میز شما','optional'=>false,'where'=>'زیر نام کافه در QR میز.'],
            ['key'=>'menu_label_public','label'=>'زیرعنوان منوی عمومی','default'=>'منوی کافه','optional'=>false,'where'=>'زیر نام کافه در منوی عمومی.'],
            ['key'=>'invalid_qr','label'=>'QR نامعتبر','default'=>'این کد درست باز نشده؛ دوباره اسکنش کن.','optional'=>false,'editable'=>false,'where'=>'پیام سیستمی ثابت برای QR یا زمینه میز نامعتبر.'],
            ['key'=>'table_inactive','label'=>'میز هنوز فعال نشده','default'=>'این میز هنوز برای سفارش فعال نشده؛ یه لحظه دیگه دوباره امتحان کن.','optional'=>false,'where'=>'وقتی میز معتبر است اما هنوز نشست عملیاتی آماده سفارش نیست.'],
            ['key'=>'visit_duration','label'=>'مدت حضور','default'=>'از حضورت حدود {duration} گذشته','optional'=>true,'editable'=>false,'where'=>'رزرو داخلی؛ در Guest UI جاری مصرف نمی‌شود.','tokens'=>['duration']],
            ['key'=>'table_changed_question','label'=>'پرسش تغییر میز','default'=>'به نظر میاد میزت عوض شده. الان روی {table} نشستی؟','optional'=>false,'where'=>'هنگام تشخیص تغییر زمینه میز برای مهمان.','tokens'=>['table']],
            ['key'=>'page_expired','label'=>'قدیمی‌شدن صفحه','default'=>'صفحه قدیمی شده؛ یک بار تازه‌ش کن.','optional'=>false,'editable'=>false,'where'=>'پیام سیستمی ثابت برای نشست یا توکن منقضی.'],
        ],
        'cart' => [
            ['key'=>'cart_title','label'=>'عنوان مرور سفارش','default'=>'مرور سفارش','optional'=>false,'where'=>'عنوان بالای سبد/مرور سفارش مهمان.'],
            ['key'=>'cart_empty','label'=>'سبد خالی','default'=>'هنوز چیزی انتخاب نکردی.','optional'=>false,'where'=>'داخل سبد وقتی هنوز آیتمی انتخاب نشده است.'],
            ['key'=>'item_note_placeholder','label'=>'راهنمای توضیح هر آیتم','default'=>'مثلاً بدون شکر یا یخ کمتر','optional'=>true,'where'=>'داخل کادر یادداشت هر آیتم در سبد.'],
            ['key'=>'order_note_placeholder','label'=>'راهنمای توضیح کلی سفارش','default'=>'چیزی هست که دوست داری بدونیم؟','optional'=>true,'where'=>'داخل کادر یادداشت کلی سفارش.'],
            ['key'=>'cart_open','label'=>'دکمه مشاهده سبد','default'=>'دیدن سفارش','optional'=>false,'where'=>'دکمه بازکردن سبد در منوی مهمان.'],
            ['key'=>'submit_order','label'=>'دکمه ثبت سفارش','default'=>'ثبت سفارش','optional'=>false,'where'=>'دکمه نهایی برای سفارش تازه.'],
            ['key'=>'submit_order_add','label'=>'دکمه افزودن به سفارش در انتظار','default'=>'افزودن به {order}','optional'=>false,'where'=>'وقتی انتخاب‌های جدید به سفارش در انتظار همان دستگاه اضافه می‌شوند.','tokens'=>['order']],
            ['key'=>'submit_order_update','label'=>'دکمه ذخیره ویرایش سفارش','default'=>'ذخیره تغییرات {order}','optional'=>false,'where'=>'هنگام ویرایش صریح یک سفارش قابل ویرایش مهمان.','tokens'=>['order']],
            ['key'=>'sending_order','label'=>'هنگام ارسال سفارش','default'=>'داریم سفارشت رو می‌فرستیم…','optional'=>false,'where'=>'روی دکمه ثبت سفارش تا زمانی که ارسال در حال انجام است.'],
            ['key'=>'items_unavailable','label'=>'ناموجودشدن انتخاب‌ها','default'=>'این‌ها فعلاً در دسترس نیستن: {items}','optional'=>false,'where'=>'وقتی بعضی اقلام سبد پیش از ثبت دیگر قابل سفارش نیستند.','tokens'=>['items']],
            ['key'=>'prices_changed','label'=>'تغییر قیمت هنگام سفارش','default'=>'قیمت بعضی انتخاب‌ها عوض شده؛ منو رو تازه کردیم تا دوباره ببینیشون.','optional'=>false,'where'=>'وقتی قیمت آیتم از زمان افزودن به سبد تا ثبت سفارش تغییر کرده است.'],
            ['key'=>'fulfillment_takeaway','label'=>'برچسب سرو بیرون‌بر','default'=>'بیرون‌بر','optional'=>false,'where'=>'انتخاب نحوه سرو و نشان آیتم بیرون‌بر.'],
        ],
        'order' => [
            ['key'=>'order_received_title','label'=>'عنوان ثبت موفق','default'=>'سفارشت رسید 👌','optional'=>false,'where'=>'عنوان پنجره نتیجه بعد از ثبت موفق سفارش.'],
            ['key'=>'status_pending_approval','label'=>'وضعیت در انتظار تأیید کافه','default'=>'سفارش ثبت شد و منتظر تأیید کافه است.','optional'=>false,'where'=>'پیگیری سفارش وقتی سفارش هنوز باید توسط کافه تأیید شود.'],
            ['key'=>'status_new','label'=>'وضعیت سفارش تازه','default'=>'سفارش ثبت شد و منتظر تأیید کافه است.','optional'=>false,'where'=>'پیگیری سفارش تازه پیش از اضافه‌شدن به حساب میز.'],
            ['key'=>'status_accounted','label'=>'وضعیت تأییدشده','default'=>'سفارش تأیید و به حساب میز اضافه شد.','optional'=>false,'where'=>'پیگیری سفارش پس از اضافه‌شدن به حساب جاری میز.'],
            ['key'=>'status_completed','label'=>'وضعیت تسویه‌شده','default'=>'حساب میز تسویه و بسته شد.','optional'=>false,'where'=>'پیگیری سفارش پس از بسته‌شدن و تسویه حساب میز.'],
            ['key'=>'status_cancelled','label'=>'وضعیت لغو','default'=>'سفارش لغو شد.','optional'=>false,'where'=>'پیگیری سفارشی که لغو شده است.'],
            ['key'=>'tracking_unavailable','label'=>'خطای پیگیری وضعیت','default'=>'فعلاً نمی‌تونیم وضعیت رو تازه کنیم؛ سفارشت قبلاً ارسال شده.','optional'=>false,'where'=>'وقتی سفارش ثبت شده اما دریافت وضعیت تازه موقتاً ممکن نیست.'],
            ['key'=>'duplicate_order','label'=>'سفارش تکراری','default'=>'این سفارش قبلاً ثبت شده؛ دوباره نفرستادیم.','optional'=>false,'where'=>'وقتی سامانه درخواست تکراری ثبت سفارش را تشخیص می‌دهد.'],
            ['key'=>'order_failed','label'=>'خطای ثبت سفارش','default'=>'سفارشت ثبت نشد؛ دوباره امتحان کن.','optional'=>false,'where'=>'وقتی ثبت سفارش به‌طور قطعی ناموفق است.'],
            ['key'=>'post_order_social_cta','label'=>'دعوت بعد از ثبت سفارش','default'=>'حال‌وهوای سکنا رو استوری کن و پیجمون رو منشن کن','optional'=>true,'where'=>'لینک اینستاگرام داخل پنجره نتیجه سفارش، اگر این قابلیت فعال باشد.'],
        ],
        'waiter' => [
            ['key'=>'waiter_button','label'=>'متن دکمه فراخوان','default'=>'فراخوان گارسون','optional'=>false,'where'=>'دکمه فراخوان گارسون در تجربه مهمان.'],
            ['key'=>'waiter_question','label'=>'تأیید فراخوان','default'=>'گارسون رو صدا کنیم؟','optional'=>false,'where'=>'عنوان پنجره تأیید قبل از ارسال فراخوان.'],
            ['key'=>'waiter_sent','label'=>'بعد از ارسال فراخوان','default'=>'درخواستت رسید؛ یکی از همکارامون میاد سر میزت.','optional'=>false,'where'=>'بعد از ثبت موفق فراخوان گارسون.'],
            ['key'=>'waiter_accepted','label'=>'بعد از پذیرش فراخوان','default'=>'دیدیمش؛ تا چند لحظه دیگه میایم پیشت.','optional'=>false,'where'=>'وقتی یکی از کارکنان فراخوان را پذیرفته است.'],
            ['key'=>'waiter_done','label'=>'بعد از انجام فراخوان','default'=>'خوشحالیم که کنارت بودیم.','optional'=>true,'where'=>'پیام کوتاه پس از پایان فراخوان.'],
            ['key'=>'waiter_cancelled','label'=>'فراخوان لغوشده','default'=>'درخواست فراخوان بسته شد.','optional'=>true,'where'=>'وقتی فراخوان بسته یا لغو شده است.'],
            ['key'=>'waiter_connection_error','label'=>'خطای ارسال فراخوان','default'=>'هنوز مطمئن نیستیم درخواستت رسیده؛ دوباره امتحان کن.','optional'=>false,'where'=>'وقتی نتیجه ارسال فراخوان از شبکه قطعی نیست.'],
            ['key'=>'waiter_disabled','label'=>'فراخوان خاموش','default'=>'فراخوان گارسون فعلاً خاموشه.','optional'=>false,'where'=>'وقتی قابلیت فراخوان در آن لحظه در دسترس نیست.'],
            ['key'=>'waiter_button_sent','label'=>'وضعیت دکمه پس از فراخوان','default'=>'گارسون خبر شده','optional'=>false,'where'=>'برچسب دسترس‌پذیری دکمه زنگ بعد از ارسال فراخوان.'],
            ['key'=>'waiter_button_accepted','label'=>'وضعیت دکمه پس از پذیرش','default'=>'همکارمون در راهه','optional'=>false,'where'=>'برچسب دسترس‌پذیری دکمه زنگ بعد از پذیرش فراخوان.'],
            ['key'=>'waiter_explainer','label'=>'توضیح پنجره فراخوان','default'=>'فقط درخواست حضور گارسون فرستاده می‌شه.','optional'=>false,'where'=>'متن اولیه پنجره فراخوان در QR میز.'],
            ['key'=>'public_waiter_prompt','label'=>'راهنمای فراخوان در منوی عمومی','default'=>'میزت را انتخاب کن تا گارسون بداند کجا بیاید.','optional'=>false,'where'=>'راهنمای فراخوان در منوی عمومی، اگر این قابلیت فعال باشد.'],
        ],
        'operations' => [
            ['key'=>'ordering_pause_cafe','label'=>'توقف سفارش کل کافه','default'=>'پذیرش سفارش آنلاین موقتاً متوقف است. منو و قیمت‌ها همچنان قابل مشاهده‌اند.','optional'=>false,'where'=>'بالای منوی QR میز وقتی سفارش آنلاین کل کافه متوقف است.'],
            ['key'=>'ordering_pause_kitchen','label'=>'توقف سفارش آشپزخانه','default'=>'ثبت آنلاین سفارش‌های آشپزخانه فعلاً امکان‌پذیر نیست. نوشیدنی‌ها و اقلام بار همچنان قابل سفارش‌اند.','optional'=>false,'where'=>'منوی QR میز وقتی سفارش آنلاین آشپزخانه متوقف است.'],
            ['key'=>'ordering_pause_bar','label'=>'توقف سفارش بار','default'=>'ثبت آنلاین سفارش‌های بار فعلاً امکان‌پذیر نیست. غذاهای آشپزخانه همچنان قابل سفارش‌اند.','optional'=>false,'where'=>'منوی QR میز وقتی سفارش آنلاین بار متوقف است.'],
            ['key'=>'station_busy_badge','label'=>'نشان تأخیر روی آیتم','default'=>'آماده‌سازی با کمی تأخیر','optional'=>false,'where'=>'روی آیتم مربوط به بخش آماده‌سازی شلوغ.'],
            ['key'=>'station_busy_section','label'=>'پیام بالای دسته شلوغ','default'=>'بعضی آیتم‌های این بخش ممکنه کمی دیرتر آماده بشن.','optional'=>false,'where'=>'بالای دسته‌ای که بخش آماده‌سازی آن شلوغ اعلام شده است.'],
            ['key'=>'station_busy_cart','label'=>'هشدار سبد برای آیتم‌های شلوغ','default'=>'این انتخاب‌ها ممکنه با کمی تأخیر آماده بشن: {items}','optional'=>false,'where'=>'داخل سبد وقتی انتخاب‌های مهمان شامل آیتم‌های بخش شلوغ است.','tokens'=>['items']],
        ],
        'staff_notifications' => [
            ['key'=>'staff_pending_order_title','label'=>'عنوان اعلان سفارش منتظر','default'=>'سفارش جدید · {table}','optional'=>false,'where'=>'اعلان کارکنان هنگام ثبت سفارش مهمان و انتظار برای تأیید.','tokens'=>['table']],
            ['key'=>'staff_pending_order_body','label'=>'متن اعلان سفارش منتظر','default'=>'{order} منتظر تأیید است.','optional'=>false,'where'=>'متن اعلان کارکنان برای سفارش مهمان منتظر تأیید.','tokens'=>['order']],
            ['key'=>'staff_preparation_order_title','label'=>'عنوان اعلان آماده‌سازی','default'=>'آماده‌سازی جدید · {table}','optional'=>false,'where'=>'اعلان بار یا آشپزخانه پس از تأیید سفارش.','tokens'=>['table']],
            ['key'=>'staff_preparation_order_body','label'=>'متن اعلان آماده‌سازی','default'=>'{order} وارد صف آماده‌سازی شد.','optional'=>false,'where'=>'متن اعلان بار یا آشپزخانه برای کار تازه آماده‌سازی.','tokens'=>['order']],
        ],
        'events' => [
            ['key'=>'events_title','label'=>'عنوان رویدادها','default'=>'رویدادهای سکنا','optional'=>false,'where'=>'عنوان پنل رویدادهای منوی مهمان.'],
            ['key'=>'events_subtitle','label'=>'زیرعنوان رویدادها','default'=>'برنامه‌های پیش‌روی کافه رو اینجا ببین.','optional'=>true,'where'=>'زیر عنوان پنل رویدادها، در صورت فعال‌بودن متن.'],
        ],
    ];
}

function message_definition_map(): array
{
    static $map = null;
    if ($map !== null) return $map;
    $map = [];
    foreach (message_definitions() as $group => $rows) {
        foreach ($rows as $row) {
            $row['group'] = $group;
            $map[$row['key']] = $row;
        }
    }
    return $map;
}

function customer_message(string $key, array $vars = []): string
{
    $defs = message_definition_map();
    $definition = $defs[$key] ?? ['default'=>$key];
    $default = (string)($definition['default'] ?? $key);
    $missing = "__SOKNA_MESSAGE_MISSING__";
    $stored = setting('message.' . $key, $missing);
    $text = $stored === $missing ? $default : $stored;
    foreach ($vars as $name => $value) {
        $text = str_replace('{' . $name . '}', (string)$value, $text);
    }
    return $text;
}

function customer_message_enabled(string $key): bool
{
    $defs = message_definition_map();
    if (!isset($defs[$key])) return true;
    if (empty($defs[$key]['optional'])) return true;
    return setting_bool('message_enabled.' . $key, true);
}

function public_customer_messages(): array
{
    $messages = [];
    foreach (message_definition_map() as $key => $definition) {
        $messages[$key] = customer_message($key);
        $messages[$key . '_enabled'] = customer_message_enabled($key);
    }
    return $messages;
}

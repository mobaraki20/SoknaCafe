<?php
declare(strict_types=1);

function panel_header(string $title, string $section = '', array $bodyClasses = []): void
{
    $user = current_user();
    $role = $user['role'] ?? 'operator';
    $isAdmin = $role === 'admin';
    $base = app_base_url();
    $navSection = ['tags'=>'items','messages'=>'settings','push'=>'settings','accommodation_settings'=>'settings','center_settings'=>'settings','updater'=>'maintenance'][$section] ?? $section;

    $iconMap = [
        'dashboard'=>'dashboard','categories'=>'list','items'=>'coffee','tags'=>'tag','marketing'=>'megaphone',
        'transfer'=>'upload','events'=>'calendar','tables'=>'table','messages'=>'message','users'=>'users',
        'push'=>'device','analytics'=>'chart','operations_report'=>'operations','inventory_report'=>'archive','activity_report'=>'operations','invoices'=>'ticket','financial_periods'=>'calendar','expenses'=>'ticket',
        'maintenance'=>'backup','updater'=>'refresh','settings'=>'settings','modules'=>'list','printing'=>'device','operator'=>'operations','accommodation'=>'home',
        'waiter'=>'service','supply_needs'=>'list','shift'=>'clock','notification_preferences'=>'device','help'=>'info','accommodation_charge'=>'home','accommodation_settings'=>'settings','center_settings'=>'settings','personnel'=>'users','subscribers'=>'users','inventory'=>'archive','purchases'=>'list',
    ];
    $pageMetaMap = [
        'dashboard' => ['خلاصه مدیریت', 'شاخص‌های امروز، وضعیت سرویس‌ها و دسترسی‌های مدیریتی'],
        'categories' => ['ساختار منو', 'چیدمان دسته‌ها و مسیر پیدا کردن آیتم‌ها در منوی مهمان'],
        'items' => ['محتوای منو', 'مدیریت آیتم‌ها، قیمت، موجودی و جایگاه نمایش'],
        'tags' => ['نشانه‌گذاری منو', 'ساخت و مدیریت برچسب‌های قابل نمایش روی آیتم‌های منو'],
        'marketing' => ['رشد فروش', 'پیشنهادها و کمپین‌های قابل نمایش در منوی مهمان'],
        'transfer' => ['انتقال داده', 'ورود و خروجی کنترل‌شده اطلاعات منو'],
        'events' => ['رویدادها', 'برنامه‌ریزی، انتشار و مدیریت چرخه رویدادهای سکنا'],
        'messages' => ['زبان محصول', 'متن‌های مهمان، وضعیت سفارش و اعلان‌های نمایشی کارکنان'],
        'tables' => ['فضای سالن', 'میزها، محدوده‌ها، نشست مهمان و کدهای QR'],
        'operator' => ['کار روزانه', 'رسیدگی به میزها، سفارش‌ها، فراخوان‌ها و تسویه'],
        'waiter' => ['آماده‌سازی', 'سفارش‌های تأییدشده آشپزخانه و بار و ثبت «گرفتم»'],
        'push' => ['دستگاه‌های تیم', 'وضعیت اعلان‌ها و دستگاه‌های متصل به شیفت'],
        'notification_preferences' => ['اعلان‌های من', 'انتخاب Pushهای مرتبط با مسئولیت شما بدون حذف هیچ وظیفه‌ای از پنل'],
        'operations_report' => ['عملیات', 'جریان سفارش، فراخوان، نشست، استثناها و فعالیت تیم در بازه انتخابی'],
        'accommodation' => ['حساب اقامت', 'انتقال، پیگیری و برگشت هزینه‌های ثبت‌شده در حساب رزرو'],
        'accommodation_charge' => ['ثبت حساب اقامت', 'انتخاب رزرو فعال و انتقال مبلغ نهایی فاکتور'],
        'accommodation_settings' => ['تنظیم اتصال اقامتگاه', 'آدرس و کلید اتصال، همراه با وضعیت ارتباط'],
        'center_settings' => ['تنظیم اتصال مرکز سکنا', 'کلید اتصال و سلامت ارتباط با مرکز سکنا'],
        'personnel' => ['پرسنل و حقوق', 'ورود امن به مدیریت مرکزی پرسنل و حقوق مجموعه'],
        'inventory' => ['انبار', 'کنترل موجودی، ورود، ضایعات، شمارش و مصرف مواد'],
        'purchases' => ['خرید و تأمین', 'نیازهای واقعی تیم، کمبودهای پیشنهادی و ثبت مستقیم خرید در انبار'],
        'supply_needs' => ['درخواست خرید', 'ثبت و پیگیری اقلام موردنیاز آشپزخانه و بار برای خرید'],
        'analytics' => ['فروش و عملکرد', 'فروش، فاکتور، اقلام، میزها و روند عملکرد در بازه انتخابی'],
        'inventory_report' => ['انبار و سود', 'هزینه مواد، مصرف، ضایعات و سود ناخالص تقریبی'],
        'activity_report' => ['فعالیت کاربران', 'نمای خلاصه و انسانی از اقدام‌های ثبت‌شده کاربران'],
        'users' => ['تیم و دسترسی', 'حساب‌های شخصی و مسئولیت‌های روشن اعضای تیم'],
        'subscribers' => ['حساب مشتریان', 'ثبت بدهی فاکتورها، پرداخت‌ها و مانده هر مشتری'],
        'invoices' => ['آرشیو فاکتورها', 'جست‌وجو، مشاهده کامل و چاپ مجدد اسناد مالی'],
        'financial_periods' => ['دوره‌های مالی', 'سال مالی جاری، بستن دوره و گزارش قطعی هر سال'],
        'expenses' => ['هزینه‌های کافه', 'ثبت، اصلاح و برگشت هزینه‌های عمومی بدون حذف تاریخچه'],
        'maintenance' => ['پایداری سامانه', 'پشتیبان‌گیری، اعتبارسنجی و بازیابی نسخه‌های سالم'],
        'updater' => ['انتشار نسخه', 'بررسی و نصب کنترل‌شده بسته‌های رسمی سامانه'],
        'settings' => ['تنظیمات و ظاهر', 'هویت برند، قالب‌ها، فونت‌ها و تنظیمات پایه مجموعه'],
        'modules' => ['امکانات سامانه', 'وضعیت قابلیت‌های اختیاری و بخش‌های همیشه فعال سامانه'],
        'printing' => ['چاپ و پرینترها', 'سرویس چاپ داخلی، پرینتر هر مقصد، صف فیش و وضعیت خطاها'],
        'help' => ['راهنمای سکنا', 'پاسخ‌های کوتاه و عملی برای کارهای روزمره سامانه'],
    ];
    $pageGroupMap = [
        'dashboard'=>'خلاصه',
        'operator'=>'عملیات','waiter'=>'عملیات','tables'=>'عملیات',
        'items'=>'منو و مهمان','categories'=>'منو و مهمان','tags'=>'منو و مهمان','marketing'=>'منو و مهمان','events'=>'منو و مهمان','messages'=>'منو و مهمان','transfer'=>'منو و مهمان',
        'invoices'=>'مالی','subscribers'=>'مالی','financial_periods'=>'مالی','expenses'=>'مالی','tax'=>'مالی','accommodation'=>'مالی','accommodation_charge'=>'مالی',
        'operations_report'=>'گزارش‌ها','analytics'=>'گزارش‌ها','inventory_report'=>'گزارش‌ها','activity_report'=>'گزارش‌ها',
        'users'=>'سامانه و زیرساخت','printing'=>'سامانه و زیرساخت','settings'=>'سامانه و زیرساخت','modules'=>'سامانه و زیرساخت','maintenance'=>'سامانه و زیرساخت','updater'=>'سامانه و زیرساخت','push'=>'سامانه و زیرساخت','accommodation_settings'=>'سامانه و زیرساخت','center_settings'=>'سامانه و زیرساخت',
        'personnel'=>'مدیریت مجموعه','inventory'=>'مدیریت مجموعه','purchases'=>'مدیریت مجموعه','supply_needs'=>'عملیات',
        'notification_preferences'=>'حساب کاربری','help'=>'راهنما',
    ];
    $pageMeta = $pageMetaMap[$section] ?? ['سامانه سکنا', 'مدیریت یکپارچه تجربه مهمان و عملیات مجموعه'];
    $pageMeta[0] = $pageGroupMap[$section] ?? 'سامانه سکنا';
    $roleLabelMap = [
        'admin' => 'مدیر سامانه',
        'operator' => 'عضو تیم',
        'waiter' => 'عضو تیم',
        'staff' => 'عضو تیم',
    ];
    $roleIconMap = [
        'admin' => 'dashboard',
        'operator' => 'operations',
        'waiter' => 'service',
        'staff' => 'users',
    ];

    if ($isAdmin) {
        $navGroups = [
            'خلاصه' => [
                'dashboard' => [$base . '/admin/index.php', 'خلاصه مدیریت'],
            ],
            'عملیات' => [
                'operator' => [$base . '/operator/index.php', 'کار روزانه'],
                'waiter' => [$base . '/waiter/index.php', 'آماده‌سازی'],
                'tables' => [$base . '/admin/tables.php', 'میزها و QR'],
            ],
            'منو و مهمان' => [
                'items' => [$base . '/admin/items.php', 'آیتم‌های منو'],
                'categories' => [$base . '/admin/categories.php', 'دسته‌بندی‌ها'],
                'events' => [$base . '/admin/events.php', 'رویدادها'],
                'marketing' => [$base . '/admin/marketing.php', 'پیشنهادها و کمپین‌ها'],
            ],
            'مالی' => [
                'invoices' => [$base . '/admin/invoices.php', 'فاکتورها'],
                'subscribers' => [$base . '/admin/subscribers.php', 'مشتریان'],
                'financial_periods' => [$base . '/admin/financial_periods.php', 'دوره‌های مالی'],
                'expenses' => [$base . '/admin/expenses.php', 'هزینه‌های کافه'],
                'tax' => [$base . '/admin/tax.php', 'مالیات'],
            ],
            'گزارش‌ها' => [
                'analytics' => [$base . '/admin/analytics.php', 'فروش و عملکرد'],
                'operations_report' => [$base . '/admin/operations_report.php', 'عملیات'],
                'inventory_report' => [$base . '/admin/inventory_report.php', 'انبار و سود'],
                'activity_report' => [$base . '/admin/activity_report.php', 'فعالیت کاربران'],
            ],
            'مدیریت مجموعه' => [
                'inventory' => [$base . '/admin/inventory.php', 'انبار'],
                'purchases' => [$base . '/admin/purchases.php', 'خرید'],
            ],
            'سامانه و زیرساخت' => [
                'users' => [$base . '/admin/users.php', 'اعضای تیم'],
                'printing' => [$base . '/admin/printing.php', 'چاپ و پرینترها'],
                'settings' => [$base . '/admin/settings.php', 'تنظیمات'],
                'modules' => [$base . '/admin/modules.php', 'امکانات سامانه'],
                'maintenance' => [$base . '/admin/maintenance.php', 'پشتیبان و بازیابی'],
            ],
        ];
    } else {
        $navGroups = ['کار روزانه' => []];
        if (user_has_capability('orders_floor', $user)
            || user_has_capability('cashier_accounts', $user)
            || user_has_capability('shift_supervision', $user)) {
            $navGroups['کار روزانه']['operator'] = [$base . '/operator/index.php', 'کار روزانه'];
        }
        if (user_has_capability('preparation', $user)) {
            $navGroups['کار روزانه']['waiter'] = [$base . '/waiter/index.php', 'آماده‌سازی'];
        }
        if (user_has_capability('cashier_accounts', $user)) {
            $navGroups['کار روزانه']['subscribers'] = [$base . '/operator/subscribers.php', 'مشتریان'];
            $navGroups['کار روزانه']['invoices'] = [$base . '/operator/invoices.php', 'فاکتورها'];
        }
        if (sokna_module_enabled('inventory') && user_has_inventory_access($user)) {
            $navGroups['مدیریت مجموعه']['inventory'] = [$base . '/admin/inventory.php', 'انبار'];
        }
        if (sokna_module_enabled('supply') && user_can_manage_purchases($user)) {
            $navGroups['مدیریت مجموعه']['purchases'] = [$base . '/admin/purchases.php', 'خرید'];
        }
    }
    if ($isAdmin) {
        if (!sokna_module_enabled('inventory')) {
            unset($navGroups['مدیریت مجموعه']['inventory'], $navGroups['گزارش‌ها']['inventory_report']);
        } elseif (!sokna_module_runtime_ready('inventory')) {
            // Inventory stays visible so reconciliation can be completed, but stale stock must not
            // feed management reporting until a full count establishes a new trusted baseline.
            unset($navGroups['گزارش‌ها']['inventory_report']);
        }
        if (!sokna_module_enabled('supply')) unset($navGroups['مدیریت مجموعه']['purchases']);
        if (!sokna_module_enabled('marketing')) {
            unset($navGroups['منو و مهمان']['events'], $navGroups['منو و مهمان']['marketing']);
        }
        if (!sokna_module_enabled('reporting')) {
            unset($navGroups['گزارش‌ها']);
        }
    }
    if ($isAdmin && (accommodation_live_operations_enabled() || accommodation_history_exists())) {
        $navGroups['مالی']['accommodation'] = [$base . '/admin/accommodation.php', 'حساب اقامتگاه'];
    }
    // Center owns HR authorization; Cafe only mirrors its short-lived visibility hint.
    // Staff navigation is fail-closed: only an explicit allow may reveal Personnel & Payroll.
    // Admin keeps the management launcher visible; Center still authorizes the actual handoff.
    if (sokna_center_connection_enabled()) {
        if ($isAdmin) {
            if (!isset($navGroups['مدیریت مجموعه'])) $navGroups['مدیریت مجموعه'] = [];
            $navGroups['مدیریت مجموعه']['personnel'] = [$base . '/admin/personnel.php', 'پرسنل و حقوق', [
                'payroll_reminder_url'=>$base . '/api/sokna_center_payroll_reminder_count.php',
                'payroll_reminder_user'=>(int)($user['id'] ?? 0),
            ]];
        } else {
            $personnelState = sokna_center_personnel_access_state((int)($user['id'] ?? 0));
            if (($personnelState['state'] ?? '') === 'allow') {
                if (!isset($navGroups['مدیریت مجموعه'])) $navGroups['مدیریت مجموعه'] = [];
                $navGroups['مدیریت مجموعه']['personnel'] = [$base . '/admin/personnel.php', 'پرسنل و حقوق', [
                'payroll_reminder_url'=>$base . '/api/sokna_center_payroll_reminder_count.php',
                'payroll_reminder_user'=>(int)($user['id'] ?? 0),
            ]];
            } elseif (($personnelState['state'] ?? '') === 'unknown') {
                // Unknown/stale is not permission. Keep the launcher hidden while a non-blocking
                // refresh asks Center; only an explicit allow may reveal it.
                if (!isset($navGroups['مدیریت مجموعه'])) $navGroups['مدیریت مجموعه'] = [];
                $navGroups['مدیریت مجموعه']['personnel'] = [$base . '/admin/personnel.php', 'پرسنل و حقوق', [
                    'hidden'=>true,
                    'refresh'=>true,
                    'entitlement_url'=>$base . '/api/sokna_center_personnel_access.php',
                    'entitlement_unknown'=>true,
                    'payroll_reminder_url'=>$base . '/api/sokna_center_payroll_reminder_count.php',
                    'payroll_reminder_user'=>(int)($user['id'] ?? 0),
                ]];
            }
        }
    }
    if (($navGroups['مدیریت مجموعه'] ?? []) === []) unset($navGroups['مدیریت مجموعه']);

    $navBadges = [];
    try {
        if ($isAdmin || user_has_capability('orders_floor', $user)) {
            $attentionOrders = (int)db()->query("SELECT COUNT(*) FROM orders WHERE status IN('pending_approval','new')")->fetchColumn();
            $activeCalls = (int)db()->query("SELECT COUNT(*) FROM waiter_calls WHERE status IN('new','accepted')")->fetchColumn();
            $currentBusinessDate = business_current_date();
            $carryoverStmt = db()->prepare("SELECT COUNT(*) FROM table_sessions WHERE status IN('active','pending') AND business_date<?");
            $carryoverStmt->execute([$currentBusinessDate]);
            $carryoverSessions = (int)$carryoverStmt->fetchColumn();
            $navBadges['operator'] = $attentionOrders + $activeCalls + $carryoverSessions;
            $navBadges['waiter'] = $activeCalls;
        }
        if ($isAdmin && (accommodation_live_operations_enabled() || accommodation_history_exists())) {
            $navBadges['accommodation'] = accommodation_attention_count();
        }
        if (sokna_module_runtime_ready('inventory') && user_has_inventory_access($user)) {
            $inventoryAttention = (int)db()->query("SELECT COUNT(*) FROM inventory_items i LEFT JOIN inventory_balances b ON b.inventory_item_id=i.id WHERE i.active=1 AND (COALESCE(b.quantity_base,0)<0 OR (i.warning_threshold>0 AND COALESCE(b.quantity_base,0)<=i.warning_threshold))")->fetchColumn();
            $navBadges['inventory'] = $inventoryAttention;
        }
        if (sokna_module_enabled('supply') && user_can_manage_purchases($user)) {
            $navBadges['purchases'] = supply_purchase_attention_count(db());
        }
    } catch (Throwable) {
        $navBadges = [];
    }

    $font = ui_font();
    $showPublicMenu = $isAdmin || user_has_capability('orders_floor', $user);
    $logoPath = setting('logo_path');
    $primaryColor = valid_hex_color(setting('primary_color', '#365b4c'),'#365b4c');
    $accentColor = valid_hex_color(setting('accent_color', '#b85c38'),'#b85c38');
    $backgroundColor = valid_hex_color(setting('background_color', '#f7f3ec'),'#f7f3ec');
    $onPrimary = contrast_text_color($primaryColor);
    $onAccent = contrast_text_color($accentColor);
    $displayName = trim((string)($user['display_name'] ?? '')) ?: 'کاربر سکنا';
    $userInitial = function_exists('mb_substr') ? text_substr($displayName, 0, 1, 'UTF-8') : substr($displayName, 0, 1);
    $roleLabel = $roleLabelMap[$role] ?? 'عضو تیم';
    $findNavLink = static function (string $key) use ($navGroups): ?array {
        foreach ($navGroups as $links) {
            if (!isset($links[$key])) continue;
            $link = $links[$key];
            $meta = is_array($link[2] ?? null) ? $link[2] : [];
            if (!empty($meta['hidden'])) return null;
            return [(string)$link[0], (string)$link[1]];
        }
        return null;
    };
    $appNav = [];
    $homeKey = $isAdmin ? 'dashboard' : ($findNavLink('operator') ? 'operator' : ($findNavLink('waiter') ? 'waiter' : ''));
    if ($homeKey !== '' && ($homeLink = $findNavLink($homeKey))) $appNav[$homeKey] = [$homeLink[0], 'خانه', $iconMap[$homeKey] ?? 'dashboard'];
    if ($homeKey !== 'operator' && ($dailyLink = $findNavLink('operator'))) $appNav['operator'] = [$dailyLink[0], 'عملیات', $iconMap['operator']];
    $quickReturn=(string)($_SERVER['REQUEST_URI']??($base.'/operator/index.php'));
    if (staff_quick_order_allowed($user)) $appNav['quick_order'] = [asset('staff/quick-order.php').'?origin=app-nav&return='.rawurlencode($quickReturn), 'سفارش', 'plus'];
    if ($homeKey !== 'waiter' && ($prepLink = $findNavLink('waiter'))) $appNav['waiter'] = [$prepLink[0], 'آماده‌سازی', $iconMap['waiter']];
    ?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <script>try{if((window.matchMedia&&window.matchMedia('(display-mode: standalone)').matches)||window.navigator.standalone===true)document.documentElement.classList.add('pwa-standalone')}catch(_){}</script>
    <title><?= e($title) ?> | <?= e(setting('cafe_name', 'سامانه کافه')) ?></title>
    <?= favicon_head_tags() ?>
    <?php if(setting_bool('pwa_enabled',true)): ?><link rel="manifest" href="<?= e(asset('manifest.php?v=' . rawurlencode(app_release_version() . '.' . favicon_revision()))) ?>"><?php endif; ?>
    <?= ui_font_head($font) ?>
    <link rel="stylesheet" href="<?= e(asset('assets/css/tokens.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/scds-foundation.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/responsive.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/panel.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/panel-layout.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/panel-components.css')) ?>">
    <?php if($section === 'waiter'): ?><link rel="stylesheet" href="<?= e(asset('assets/css/staff-action-queue.css')) ?>"><?php endif; ?>
    <?php if($section === 'operator'): ?><link rel="stylesheet" href="<?= e(asset('assets/css/operator-live.css')) ?>"><?php endif; ?>
    <?php if($section === 'items'): ?><link rel="stylesheet" href="<?= e(asset('assets/css/items-management.css')) ?>"><?php endif; ?>
    <?php if(in_array($section,['items','categories','printing'],true)): ?><link rel="stylesheet" href="<?= e(asset('assets/css/reorder.css')) ?>"><?php endif; ?>
    <?php if(in_array($section,['inventory','purchases','supply_needs'],true)): ?><link rel="stylesheet" href="<?= e(asset('assets/css/inventory.css')) ?>"><?php endif; ?>
    <style>
        :root{--font-ui:<?= ui_font_family($font) ?>;--primary:<?= e($primaryColor) ?>;--primary-dark:<?= e($primaryColor) ?>;--accent:<?= e($accentColor) ?>;--app-bg:<?= e($backgroundColor) ?>;--on-primary:<?= e($onPrimary) ?>;--on-accent:<?= e($onAccent) ?>}
    </style>
</head>
<body class="panel-body font-<?= e($font) ?> panel-section-<?= e(preg_replace('/[^a-z0-9_-]+/i', '-', $section ?: 'general')) ?><?= $bodyClasses ? ' ' . e(implode(' ', array_values(array_filter($bodyClasses, static fn($class) => is_string($class) && preg_match('/^[a-z0-9_-]+$/i', $class))))) : '' ?>">
<a class="skip-link" href="#panelContent">رفتن به محتوای اصلی</a>
<div class="panel-shell">
    <aside class="sidebar" id="sidebar">
        <div class="brand-block">
            <div class="brand-mark"><?php if ($logoPath): ?><img src="<?= e(asset($logoPath)) ?>" alt=""><?php else: ?><?= ui_icon('coffee') ?><?php endif; ?></div>
            <div class="brand-copy"><strong><?= e(setting('cafe_name', 'کافه من')) ?></strong><small>سامانه عملیات و مهمان</small></div>
        </div>
        <div class="side-nav-groups">
            <?php $groupNumber=0; foreach ($navGroups as $groupTitle => $links): ?>
                <?php
                if (!$links) continue;
                $groupNumber++;
                $groupKey = 'nav-' . $groupNumber;
                $groupActive = array_key_exists($navSection, $links);
                $groupHasVisibleLink = false;
                foreach ($links as $navLink) {
                    $navMeta = is_array($navLink[2] ?? null) ? $navLink[2] : [];
                    if (empty($navMeta['hidden'])) { $groupHasVisibleLink = true; break; }
                }
                ?>
                <section class="side-nav-group <?= $groupActive ? 'is-open is-current' : '' ?>" data-nav-group="<?= e($groupKey) ?>"<?= $groupHasVisibleLink ? '' : ' hidden' ?><?= !$groupHasVisibleLink ? ' data-center-personnel-group' : '' ?>>
                    <button class="side-nav-group-toggle" type="button" aria-expanded="<?= $groupActive ? 'true' : 'false' ?>" aria-controls="sideNavLinks<?= $groupNumber ?>">
                        <span class="side-nav-group-title" id="sideNavGroup<?= $groupNumber ?>"><?= e($groupTitle) ?></span>
                        <span class="side-nav-group-chevron" aria-hidden="true"><?= ui_icon('chevron-left') ?></span>
                    </button>
                    <nav class="side-nav" id="sideNavLinks<?= $groupNumber ?>" aria-labelledby="sideNavGroup<?= $groupNumber ?>"<?= $groupActive ? '' : ' hidden' ?>>
                        <?php foreach ($links as $key => $navLink): ?>
                            <?php [$href,$label]=$navLink;$navMeta=is_array($navLink[2]??null)?$navLink[2]:[];$navHidden=!empty($navMeta['hidden']); ?>
                            <a class="<?= $navSection === $key ? 'active' : '' ?>" href="<?= e($href) ?>"<?= $navSection === $key ? ' aria-current="page"' : '' ?><?= $navHidden ? ' hidden' : '' ?><?php if($key==='personnel'): ?> data-center-personnel-link data-center-personnel-refresh="<?= !empty($navMeta['refresh'])?'1':'0' ?>" data-center-personnel-url="<?= e((string)($navMeta['entitlement_url']??'')) ?>" data-payroll-reminder-url="<?= e((string)($navMeta['payroll_reminder_url']??'')) ?>" data-payroll-reminder-user="<?= (int)($navMeta['payroll_reminder_user']??0) ?>"<?php endif; ?>><span class="side-nav-icon"><?= ui_icon($iconMap[$key] ?? 'list') ?></span><span class="side-nav-label"><?= e($label) ?></span><?php if ($key==='personnel'): ?><span class="side-nav-badge" data-payroll-reminder-count hidden aria-hidden="true"></span><?php elseif (($navBadges[$key] ?? 0) > 0): ?><span class="side-nav-badge" aria-label="<?= e(fa_digits((int)$navBadges[$key])) ?> مورد نیازمند توجه"><?= e(fa_digits((int)$navBadges[$key])) ?></span><?php endif; ?></a>
                        <?php endforeach; ?>
                    </nav>
                </section>
            <?php endforeach; ?>
        </div>
        <footer class="sidebar-user">
            <div class="sidebar-user-identity"><span class="sidebar-avatar" aria-hidden="true"><?= e($userInitial) ?></span><span class="sidebar-user-copy"><strong><?= e($displayName) ?></strong><small><?= e($roleLabel) ?></small></span></div>
            <nav class="sidebar-user-actions" aria-label="حساب کاربری"><?php if(user_has_capability('orders_floor',$user)||user_has_capability('preparation',$user)): ?><a href="<?= e($base . '/notification_preferences.php') ?>"><?= ui_icon('device') ?><span>اعلان‌های من</span></a><?php endif; ?><a href="<?= e($base . '/help.php') ?>"><?= ui_icon('info') ?><span>راهنما</span></a><a href="<?= e($base . '/logout.php') ?>"><?= ui_icon('logout') ?><span>خروج</span></a></nav>
        </footer>
    </aside>
    <button class="sidebar-backdrop" id="sidebarBackdrop" type="button" aria-label="بستن منو" tabindex="-1"></button>
    <main class="panel-main">
        <header class="panel-topbar">
            <button class="icon-btn mobile-menu" id="panelNavToggle" type="button" data-panel-nav-toggle aria-label="بازکردن منو" aria-controls="sidebar" aria-expanded="false"><?= ui_icon('menu') ?></button>
            <div class="panel-page-heading">
                <span class="panel-page-icon" aria-hidden="true"><?= ui_icon($iconMap[$section] ?? 'dashboard') ?></span>
                <div class="panel-page-copy"><span class="panel-eyebrow"><?= e($pageMeta[0]) ?></span><h1><?= e($title) ?></h1><p><?= e($pageMeta[1]) ?></p></div>
            </div>
            <div class="topbar-actions">
                <span class="panel-role-pill" title="<?= e($roleLabel) ?>"><?= ui_icon($roleIconMap[$role] ?? 'users') ?><span><?= e($displayName) ?></span></span>
                <time class="panel-clock" id="panelClock" datetime="<?= e((new DateTimeImmutable('now', new DateTimeZone(app_timezone())))->format(DATE_ATOM)) ?>" data-server-epoch="<?= time() ?>" data-timezone="<?= e(app_timezone()) ?>">
                    <strong class="panel-clock-time"><?= e(fa_digits(date('H:i'))) ?></strong>
                    <span class="panel-clock-date"><?= e(jalali_date_input()) ?></span>
                </time>
                <?php if(staff_quick_order_allowed($user)): ?><?php $quickReturn=(string)($_SERVER['REQUEST_URI']??($base.'/operator/index.php')); ?><a class="btn btn-primary quick-order-launch" aria-label="ثبت سریع سفارش" href="<?= e(asset('staff/quick-order.php').'?origin=global&return='.rawurlencode($quickReturn)) ?>"><?= ui_icon('plus') ?><span>ثبت سریع سفارش</span></a><?php endif; ?>
                <div class="topbar-tools" id="panelToolsMenu">
                    <button class="icon-btn topbar-tools-toggle" id="panelToolsToggle" type="button" aria-label="ابزارهای بیشتر" aria-haspopup="menu" aria-controls="panelToolsPopover" aria-expanded="false"><?= ui_icon('more') ?></button>
                    <div class="topbar-tools-menu hidden" id="panelToolsPopover" role="menu" aria-hidden="true">
                        <button class="topbar-tool hidden" id="pwaInstallButton" type="button" role="menuitem"><?= ui_icon('device') ?><span>نصب وب‌اپ</span></button>
                        <?php if($showPublicMenu): ?><a class="topbar-tool" role="menuitem" target="_blank" href="<?= e($base . '/menu/') ?>"><?= ui_icon('eye') ?><span>نمایش منوی مهمان</span></a><?php endif; ?><?php if(user_has_capability('orders_floor',$user)||user_has_capability('preparation',$user)): ?><button class="topbar-tool" id="waiterNotify" type="button" role="menuitem"><?= ui_icon('device') ?><span>اعلان این دستگاه</span></button><a class="topbar-tool" role="menuitem" href="<?= e($base . '/notification_preferences.php') ?>"><?= ui_icon('list') ?><span>تنظیم اعلان‌های من</span></a><button class="topbar-tool hidden" id="waiterNotifyTest" type="button" role="menuitem"><?= ui_icon('info') ?><span>آزمایش مسیر کامل اعلان</span></button><?php endif; ?>
                    </div>
                </div>
            </div>
        </header>
        <div class="panel-network-banner hidden" id="panelNetworkBanner" role="status" aria-live="polite"><?= ui_icon('warning') ?><span><strong>ارتباط قطع است</strong><small>اطلاعات روی سرور تا برگشت اتصال قابل تغییر نیست؛ صفحه را نبند.</small></span></div>
        <?php if($appNav): ?><nav class="app-bottom-nav" id="appBottomNav" aria-label="پیمایش اصلی اپ">
            <?php foreach($appNav as $key=>$entry): [$href,$label,$navIcon]=$entry; $isCurrent=($key===$navSection); ?><a href="<?= e($href) ?>"<?= $isCurrent?' class="is-active" aria-current="page"':'' ?>><span class="app-nav-icon"><?= ui_icon($navIcon) ?><?php if(($navBadges[$key]??0)>0): ?><b class="app-nav-badge"><?= e(fa_digits((int)$navBadges[$key])) ?></b><?php endif; ?></span><span><?= e($label) ?></span></a><?php endforeach; ?>
            <button type="button" data-panel-nav-toggle data-app-nav-more aria-controls="sidebar" aria-expanded="false"><span class="app-nav-icon"><?= ui_icon('more') ?></span><span>بیشتر</span></button>
        </nav><?php endif; ?>
        <script>window.SOKNA_ICON_SPRITE=<?= json_script(asset('assets/icons/ui-sprite.svg')) ?>;</script>
        <script src="<?= e(asset('assets/js/interaction-modality.js')) ?>"></script>
        <script src="<?= e(asset('assets/js/panel-shell.js')) ?>"></script>
        <script src="<?= e(asset('assets/js/panel-validation.js')) ?>"></script>
        <div class="panel-content" id="panelContent" tabindex="-1">
        <script src="<?= e(asset('assets/js/panel-choice.js')) ?>"></script>
        <?php if(in_array($section,['inventory','purchases','supply_needs'],true)): ?><script defer src="<?= e(asset('assets/js/inventory-form-flow.js')) ?>"></script><?php endif; ?>
        <script src="<?= e(asset('assets/js/panel-conditions.js')) ?>"></script>
        <script src="<?= e(asset('assets/js/panel-time-picker.js')) ?>"></script>
        <script src="<?= e(asset('assets/js/panel-jalali.js')) ?>"></script>
            <?= render_flashes() ?>
<?php
}


function panel_subnav(array $items, string $activeKey, string $label = 'بخش‌های مرتبط'): void
{
    if (!$items) return;
    ?>
    <nav class="panel-subnav" aria-label="<?= e($label) ?>">
        <?php foreach ($items as $key => [$href, $text]): ?>
            <a href="<?= e($href) ?>" class="<?= $key === $activeKey ? 'is-active' : '' ?>"<?= $key === $activeKey ? ' aria-current="page"' : '' ?>><?= e($text) ?></a>
        <?php endforeach; ?>
    </nav>
    <?php
}

function panel_footer(string $extraScripts = ''): void
{
    ?>
        </div>
    </main>
</div>
<div class="pwa-update-banner hidden" id="pwaUpdateBanner" role="status" aria-live="polite"><div><strong>نسخه جدید سکنا آماده است</strong><small>به‌روزرسانی فقط با انتخاب شما انجام می‌شود.</small></div><div class="pwa-update-actions"><button class="btn btn-primary btn-sm" type="button" id="pwaUpdateApply">به‌روزرسانی</button><button class="btn btn-light btn-sm" type="button" id="pwaUpdateLater">بعداً</button></div></div>
<div class="panel-confirm-layer hidden" id="panelConfirmLayer" role="dialog" aria-modal="true" aria-labelledby="panelConfirmTitle" aria-describedby="panelConfirmMessage" aria-hidden="true">
    <div class="panel-confirm-backdrop" data-panel-confirm-cancel aria-hidden="true"></div>
    <section class="panel-confirm-card">
        <div class="panel-confirm-icon" aria-hidden="true"><span data-panel-confirm-icon-info><?= ui_icon('info') ?></span><span class="hidden" data-panel-confirm-icon-danger><?= ui_icon('warning') ?></span></div>
        <div class="panel-confirm-copy"><h2 id="panelConfirmTitle">تأیید</h2><p id="panelConfirmMessage"></p></div>
        <div class="panel-confirm-actions">
            <button class="btn btn-primary" type="button" data-panel-confirm-ok>تأیید</button>
            <button class="btn btn-light" type="button" data-panel-confirm-cancel>انصراف</button>
        </div>
    </section>
</div>
<div id="panelToast" class="panel-toast hidden" role="status" aria-live="polite"></div>
<script>window.PWA_ENABLED=<?= setting_bool('pwa_enabled',true)?'true':'false' ?>;window.PWA_SW_URL=<?= setting_bool('pwa_enabled',true)?json_script(asset('service-worker.js')):"''" ?>;window.SOKNA_PUSH_DRAIN_URL=<?= json_script(asset('api/push_drain.php')) ?>;</script>
<script>window.SOKNA_PRINT_BRIDGE_CAPABILITY_URL=<?= json_script(asset('api/print_bridge_capability.php')) ?>;</script>
<script defer src="<?= e(asset('assets/js/push-runtime.js')) ?>"></script>
<script defer src="<?= e(asset('assets/js/panel-core.js')) ?>"></script>
<script defer src="<?= e(asset('assets/js/panel-menus.js')) ?>"></script>
<script defer src="<?= e(asset('assets/js/panel-media.js')) ?>"></script>
<script defer src="<?= e(asset('assets/js/panel-form-state.js')) ?>"></script>
<script defer src="<?= e(asset('assets/js/pwa.js')) ?>"></script>
<?php $panelUser=current_user(); if($panelUser && (user_has_capability('orders_floor',$panelUser)||user_has_capability('preparation',$panelUser))): ?><script>window.WAITER_PUSH_API=<?= json_script(asset('waiter/api_push.php')) ?>;</script><script defer src="<?= e(asset('assets/js/device-notifications.js')) ?>"></script><?php endif; ?>
<?= $extraScripts ?>
</body>
</html>
<?php
}

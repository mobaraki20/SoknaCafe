<?php
declare(strict_types=1);

/**
 * Sokna modular-monolith registry.
 *
 * Contract:
 * - one deployable PHP application and one database;
 * - every persistent table has exactly one domain owner;
 * - `depends_on` is the stable code/API dependency direction;
 * - `reads_from` documents intentional read-side coupling that is not table ownership;
 * - cross-domain writes must use the owning module's public contract;
 * - maintenance/install/update are explicit platform exceptions because they must inspect all domains.
 *
 * This is metadata-first. It is not a plugin loader and does not create service/network boundaries.
 */
function sokna_module_registry(): array
{
    static $registry = null;
    if (is_array($registry)) return $registry;

    $registry = [
        'platform' => [
            'label' => 'هسته سامانه',
            'type' => 'core',
            'required' => true,
            'depends_on' => [],
            'reads_from' => [],
            'owner' => 'bootstrap.php + includes/auth.php + includes/maintenance.php + includes/functions.php(shared aggregator) + includes/function_domains/* (incremental owners)',
            'owns_tables' => ['audit_log','schema_migrations','settings','users','user_capabilities'],
            'entrypoints' => ['index.php','install.php','login.php','logout.php','help.php','favicon.php','manifest.php','robots.php','sitemap.php','admin/index.php','admin/users.php','admin/settings.php','admin/modules.php','admin/messages.php','admin/maintenance.php','admin/update/index.php'],
            'capabilities' => ['shift_supervision'],
            'background_jobs' => ['tools/backup-worker.php'],
            'public_contracts' => ['db()','current_user()','require_login()','user_has_capability()','audit_log_write_strict()','customer_message()','public_customer_messages()','maintenance_*'],
        ],
        'relay' => [
            'label' => 'ارتباط امن Public',
            'type' => 'core',
            'required' => true,
            'depends_on' => ['platform'],
            'reads_from' => ['orders','finance','inventory','supply','subscribers','expenses','reporting'],
            'owner' => 'includes/relay_*.php + includes/deferred.php + tools/relay-worker.php + tools/relay-projection-worker.php + tools/deferred-worker.php',
            'owns_tables' => ['relay_processed_requests','deferred_work_receipts','deferred_review_items'],
            'entrypoints' => [],
            'capabilities' => [],
            'background_jobs' => ['tools/relay-worker.php','tools/relay-projection-worker.php','tools/remote-read-worker.php','tools/deferred-worker.php'],
            'public_contracts' => ['sokna_relay_* protocol/client/dispatch/projection contracts','remote read-model snapshot contracts','sokna_deferred_* isolated pending/review/reconcile contracts'],
        ],
        'menu' => [
            'label' => 'منو و مهمان',
            'type' => 'core',
            'required' => true,
            'depends_on' => ['platform'],
            'reads_from' => [],
            'owner' => 'includes/menu_catalog.php + admin/items.php + assets/js/menu.js',
            'owns_tables' => ['menus','menu_categories','menu_items','categories','items','tags','item_tags'],
            'entrypoints' => ['menu/index.php','about.php','admin/menu_form.php','admin/categories.php','admin/category_form.php','admin/items.php','admin/item_form.php','admin/tags.php','admin/tag_form.php','admin/menu_transfer.php','admin/guest_publish.php'],
            'capabilities' => [],
            'background_jobs' => ['tools/guest-availability-worker.php'],
            'public_contracts' => ['menu_catalog_snapshot()','menu_catalog_visible_menus()','guest_publish_* immutable snapshot contracts','menu item/category/tag read models'],
        ],
        'orders' => [
            'label' => 'سفارش و سرویس',
            'type' => 'core',
            'required' => true,
            'depends_on' => ['platform','menu'],
            'reads_from' => ['finance','inventory','printing','notifications'],
            'owner' => 'api/create_order.php + staff/api_quick_order.php + operator/ + waiter/',
            'owns_tables' => [
                'cafe_tables','table_sessions','table_session_clients','table_drafts','table_draft_items','orders','order_business_sequences','order_items','order_status_history',
                'waiter_calls','order_preparation_claims','preparation_adjustments','order_item_adjustments',
                'user_preparation_areas',
            ],
            'entrypoints' => [
                'api/create_order.php','api/guest_orders.php','api/order_status.php','api/table_context.php','api/waiter_call.php',
                'staff/quick-order.php','staff/api_quick_order.php','staff/api_table_draft.php','operator/index.php','operator/api_orders.php','operator/api_status.php',
                'operator/api_table_session.php','operator/api_waiter.php','operator/api_controls.php','waiter/index.php','waiter/api_feed.php','waiter/api_action.php',
                'admin/tables.php','admin/qr.php',
            ],
            'capabilities' => ['orders_floor','preparation','shift_supervision'],
            'background_jobs' => [],
            'public_contracts' => ['order/session state machine','table draft lifecycle/finalize contract','preparation adjustment contract','staff quick-order idempotency'],
        ],
        'finance' => [
            'label' => 'مالی و تسویه',
            'type' => 'core',
            'required' => true,
            'depends_on' => ['platform','orders'],
            'reads_from' => ['subscribers','accommodation','printing'],
            'owner' => 'includes/settlement.php + admin/invoices.php + admin/financial_periods.php',
            'owns_tables' => ['financial_periods','settlement_records','settlement_record_lines','invoice_discount_audit','financial_period_close_overrides'],
            'entrypoints' => ['admin/invoices.php','admin/financial_periods.php','operator/invoices.php','operator/api_bill.php','operator/api_settlements.php'],
            'capabilities' => ['cashier_accounts'],
            'background_jobs' => [],
            'public_contracts' => ['settlement_finalize_locked()','settlement reversal/void contract','financial period invoice sequence'],
        ],
        'inventory' => [
            'label' => 'انبار',
            'type' => 'operational',
            'required' => false,
            'toggleable' => true,
            'setting_key' => 'module.inventory.enabled',
            'default_enabled' => true,
            'depends_on' => ['platform','menu'],
            'runtime_ready' => 'inventory_module_runtime_ready',
            'lifecycle' => [
                'before_disable'=>'inventory_module_before_disable_locked',
                'after_disable'=>'inventory_module_after_disable_locked',
            ],
            'reads_from' => ['orders'],
            'owner' => 'includes/inventory.php + admin/inventory*.php',
            'owns_tables' => [
                'inventory_balances','inventory_categories','inventory_count_lines','inventory_count_sessions','inventory_items',
                'inventory_movements','inventory_order_events','inventory_purchase_units','inventory_recipe_components','inventory_recipe_versions',
            ],
            'entrypoints' => [
                'admin/inventory.php','admin/inventory_adjustment.php','admin/inventory_categories.php','admin/inventory_count.php',
                'admin/inventory_count_start.php','admin/inventory_item.php','admin/inventory_item_form.php','admin/inventory_items.php',
                'admin/inventory_opening.php','admin/inventory_receive.php','admin/inventory_review.php','admin/inventory_waste.php','api/inventory_kick.php',
            ],
            'capabilities' => ['inventory_view','inventory_cost_view','inventory_operations','inventory_finalize','inventory_manage'],
            'background_jobs' => ['tools/inventory-worker.php'],
            'public_contracts' => ['inventory_record_movement_locked()','inventory_create_unreviewed_item_locked()','inventory_count_update_line_locked()','inventory_process_pending_order_events()','inventory_* quantity/unit helpers'],
            'manager' => [
                'icon' => 'archive',
                'context' => 'عملیات و موجودی',
                'description' => 'موجودی، ورود و ضایعات، شمارش و مصرف مواد از فروش را کنترل می‌کند.',
                'active_note' => 'انبار فعال است و در صورت آماده‌بودن موجودی، مصرف فروش ثبت می‌شود.',
                'waiting_note' => 'انبار روشن شده است، اما موجودی قبلی پس از دوره خاموشی قابل اتکا نیست؛ یک شمارش کامل انجام دهید تا مصرف خودکار دوباره شروع شود.',
                'disabled_note' => 'انبار از کار روزانه کنار گذاشته شده است؛ سفارش، صندوق و چاپ مستقل ادامه می‌دهند.',
                'impacts' => [
                    'صفحه‌ها، عملیات پس‌زمینه و گزارش‌های وابسته به انبار از کار روزانه کنار گذاشته می‌شوند.',
                    'خرید و تأمین نیز چون به انبار وابسته است به‌صورت امن غیرفعال می‌شود.',
                    'داده‌ها و دستور مصرف مواد قبلی حذف نمی‌شوند؛ پس از فعال‌سازی دوباره یک شمارش کامل لازم است.',
                ],
                'links' => [
                    ['label'=>'انبار','href'=>'inventory.php'],
                ],
            ],
        ],
        'supply' => [
            'label' => 'خرید و تأمین',
            'type' => 'operational',
            'required' => false,
            'toggleable' => true,
            'setting_key' => 'module.supply.enabled',
            'default_enabled' => true,
            'depends_on' => ['platform','inventory'],
            'lifecycle' => [
                'before_disable'=>'supply_module_before_disable_locked',
                'disable_preflight'=>'supply_module_disable_preflight',
            ],
            'reads_from' => ['inventory','orders'],
            'owner' => 'modules/Supply/',
            'owns_tables' => ['inventory_supply_needs','inventory_supply_receipts','inventory_supply_receipt_allocations'],
            'entrypoints' => ['operator/supply-needs.php','admin/purchases.php','assets/js/supply-needs.js','assets/js/supply-purchases.js'],
            'capabilities' => ['preparation','inventory_operations','shift_supervision'],
            'background_jobs' => [],
            'public_contracts' => [
                'supply_request_upsert_locked()','supply_request_add_locked()','supply_mark_group_preparing_locked()','supply_return_group_from_preparing_locked()',
                'supply_receive_preparing_locked()','supply_purchase_groups()','supply_purchase_attention_count()',
            ],
            'manager' => [
                'icon' => 'cart',
                'context' => 'خرید روزانه',
                'description' => 'درخواست خرید، در حال خرید و تحویل را مدیریت می‌کند و برای ثبت تحویل به انبار وابسته است.',
                'active_note' => 'فرآیند خرید فعال است و تحویل واقعی به انبار ثبت می‌شود.',
                'disabled_note' => 'فرآیند درخواست خرید و تحویل از کار روزانه کنار گذاشته شده است.',
                'impacts' => [
                    'درخواست خرید و صفحه خرید از منوی تیم و مدیریت حذف می‌شوند.',
                    'هیچ نیاز یا تحویل تازه‌ای ثبت نمی‌شود و اطلاعات قبلی حذف نمی‌شوند.',
                    'انبار می‌تواند بدون خرید فعال بماند؛ اما خرید بدون انبار قابل فعال‌سازی نیست.',
                ],
                'links' => [
                    ['label'=>'خرید','href'=>'purchases.php'],
                ],
            ],
        ],
        'subscribers' => [
            'label' => 'حساب مشترکین',
            'type' => 'business',
            'required' => false,
            'depends_on' => ['platform','finance'],
            'reads_from' => ['orders'],
            'owner' => 'includes/subscribers.php + admin/subscribers.php',
            'owns_tables' => ['subscribers','subscriber_ledger'],
            'entrypoints' => ['admin/subscribers.php','operator/subscribers.php','operator/api_subscribers.php'],
            'capabilities' => ['cashier_accounts'],
            'background_jobs' => [],
            'public_contracts' => ['subscriber_ledger_append_locked()','subscriber balance/read models'],
        ],
        'expenses' => [
            'label' => 'هزینه‌های کافه',
            'type' => 'business',
            'required' => false,
            'depends_on' => ['platform','finance'],
            'reads_from' => ['finance'],
            'owner' => 'includes/expenses.php',
            'owns_tables' => ['expense_categories','expenses'],
            'entrypoints' => [],
            'capabilities' => [],
            'background_jobs' => [],
            'public_contracts' => ['expense_create_locked()','expense category read contract'],
        ],
        'marketing' => [
            'label' => 'کمپین‌ها و رویدادها',
            'type' => 'business',
            'required' => false,
            'toggleable' => true,
            'setting_key' => 'module.marketing.enabled',
            'default_enabled' => true,
            'depends_on' => ['platform','menu'],
            'reads_from' => ['menu'],
            'owner' => 'admin/marketing.php + admin/events.php',
            'owns_tables' => ['campaigns','events'],
            'entrypoints' => ['admin/marketing.php','admin/campaign_form.php','admin/events.php','admin/event_form.php'],
            'capabilities' => [],
            'background_jobs' => [],
            'public_contracts' => ['active campaign/event guest read models'],
            // Management UI metadata is intentionally kept in the registry so the page does not
            // grow module-specific conditionals as more capabilities become genuinely toggle-ready.
            'manager' => [
                'icon' => 'megaphone',
                'context' => 'منوی مهمان',
                'description' => 'کمپین‌های منوی مهمان و رویدادهای قابل نمایش را یکجا کنترل می‌کند.',
                'active_note' => 'قابلیت فعال است. نمایش هر کمپین یا رویداد همچنان از تنظیمات همان بخش کنترل می‌شود.',
                'disabled_note' => 'قابلیت خاموش است و از کار روزانه و منوی مهمان کنار گذاشته شده است.',
                'impacts' => [
                    'صفحات کمپین و رویداد از منوی مدیریت خارج می‌شوند.',
                    'در منوی مهمان کمپین یا رویدادی نمایش داده نمی‌شود.',
                    'خاموش‌کردن این قابلیت چیزی را حذف نمی‌کند.',
                ],
                'links' => [
                    ['label'=>'کمپین‌ها','href'=>'marketing.php'],
                    ['label'=>'رویدادها','href'=>'events.php'],
                ],
            ],
        ],
        'reporting' => [
            'label' => 'گزارش‌ها و تحلیل',
            'type' => 'business',
            'required' => false,
            'toggleable' => true,
            'setting_key' => 'module.reporting.enabled',
            'default_enabled' => true,
            'depends_on' => ['platform'],
            'reads_from' => ['menu','orders','finance','inventory','accommodation'],
            'owner' => 'includes/reporting.php + admin/*_report.php + admin/analytics.php',
            'owns_tables' => ['menu_metrics_daily','menu_search_terms_daily'],
            'entrypoints' => ['admin/analytics.php','admin/operations_report.php','admin/activity_report.php','admin/inventory_report.php','api/metric.php'],
            'capabilities' => [],
            'background_jobs' => [],
            'public_contracts' => ['read-only operational/financial report queries','menu metric aggregation'],
            'manager' => [
                'icon' => 'chart',
                'context' => 'مدیریت و تحلیل',
                'description' => 'گزارش‌های فروش، عملیات، انبار و فعالیت کاربران را همراه با آمار تجمیعی منوی مهمان کنترل می‌کند.',
                'active_note' => 'گزارش‌های مدیریتی در دسترس‌اند و آمار منوی مهمان طبق تنظیم همان بخش ثبت می‌شود.',
                'disabled_note' => 'گزارش‌های مدیریتی از کار روزانه کنار گذاشته شده‌اند و آمار تازه منوی مهمان ثبت نمی‌شود.',
                'impacts' => [
                    'چهار صفحه گزارش از منوی مدیریت و دسترسی مستقیم خارج می‌شوند.',
                    'ثبت آمار تجمیعی تازه از رفتار منوی مهمان متوقف می‌شود.',
                    'سفارش، مالی، انبار و سابقه ثبت اقدامات اصلی همچنان فعال می‌مانند و حذف نمی‌شوند.',
                ],
                'links' => [
                    ['label'=>'فروش و عملکرد','href'=>'analytics.php'],
                    ['label'=>'عملیات','href'=>'operations_report.php'],
                    ['label'=>'انبار و سود','href'=>'inventory_report.php'],
                    ['label'=>'فعالیت کاربران','href'=>'activity_report.php'],
                ],
            ],
        ],
        'accommodation' => [
            'label' => 'اتصال اقامتگاه',
            'type' => 'integration',
            'required' => false,
            'depends_on' => ['platform','finance','orders'],
            'reads_from' => ['finance','orders'],
            'owner' => 'includes/accommodation.php + includes/accommodation_transport.php + admin/accommodation*.php',
            'owns_tables' => ['accommodation_transfers'],
            'entrypoints' => ['admin/accommodation.php','admin/accommodation_settings.php','operator/api_accommodation.php'],
            'capabilities' => ['cashier_accounts','shift_supervision'],
            'background_jobs' => [],
            'control_mode' => 'connection',
            'public_contracts' => [
                'accommodation_live_operations_enabled()',
                'accommodation_prepare_transfer_for_table()',
                'accommodation_attempt_charge()',
                'accommodation_attempt_void()',
                'accommodation_attention_rows()',
                'accommodation_finalize_local_checkout()',
                'accommodation_finalize_local_reversal()',
            ],
        ],
        'personnel' => [
            'label' => 'پرسنل و حقوق',
            'type' => 'integration',
            'required' => false,
            'toggleable' => true,
            'setting_key' => 'module.personnel.enabled',
            'default_enabled' => true,
            'depends_on' => ['platform'],
            'reads_from' => ['platform'],
            'owner' => 'includes/sokna_center.php + admin/personnel.php',
            'owns_tables' => [],
            'entrypoints' => ['admin/personnel.php','admin/center_settings.php','api/sokna_center_users.php','api/sokna_center_personnel_access.php','api/sokna_center_payroll_reminder_count.php','center_return.php'],
            'capabilities' => [],
            'background_jobs' => [],
            'public_contracts' => ['Sokna Center signed handoff','personnel entitlement cache','payroll reminder count read','center_return.php remains a safe recovery redirect while disabled'],
            'manager' => [
                'icon' => 'users',
                'context' => 'اتصال مرکز سکنا',
                'description' => 'ورود امن به پرسنل و حقوق مرکز سکنا و یادآورهای حقوق را از داخل کافه کنترل می‌کند.',
                'active_note' => 'قابلیت فعال است. برای استفاده، اتصال مرکز سکنا نیز باید یک‌بار با کلید معتبر برقرار شده باشد.',
                'disabled_note' => 'قابلیت از پنل کافه کنار گذاشته شده و هیچ درخواست تازه‌ای به مرکز سکنا ارسال نمی‌شود.',
                'impacts' => [
                    'پرسنل و حقوق و یادآور عددی حقوق از منوی تیم حذف می‌شوند.',
                    'ورود امن و ارتباط پرسنلی کافه با مرکز سکنا متوقف می‌شود.',
                    'اطلاعات اتصال ذخیره‌شده پاک نمی‌شود و عملیات سفارش، صندوق، انبار و چاپ مستقل باقی می‌مانند.',
                ],
                'links' => [
                    ['label'=>'پرسنل و حقوق','href'=>'personnel.php'],
                    ['label'=>'تنظیم اتصال','href'=>'center_settings.php'],
                ],
            ],
        ],
        'printing' => [
            'label' => 'چاپ',
            'type' => 'platform_service',
            'required' => true,
            'depends_on' => ['platform'],
            'reads_from' => ['orders','finance','subscribers','accommodation'],
            'owner' => 'includes/printing.php + print-agent/v4/ + tools/print-runtime-worker.php (Runtime service supervision only)',
            'owns_tables' => ['print_agents','print_attempts','print_claim_requests','print_claim_reconciliations','print_destinations','print_jobs','print_templates'],
            'entrypoints' => ['admin/printing.php','admin/print_templates.php','api/print_bridge_capability.php','api/print_status_snapshot.php','print-agent/v4/api.php'],
            'capabilities' => [],
            'background_jobs' => ['SOKNA Runtime -> tools/print-runtime-worker.php -> installed Windows Print Agent service','tools/print-v4-staging-preflight.php'],
            'public_contracts' => ['print_enqueue_prep_order()','print enqueue/final/reprint contracts','Print API v4 claim/accept/start/report/renew/status + local wake capability'],
        ],
        'notifications' => [
            'label' => 'اعلان‌ها',
            'type' => 'platform_service',
            'required' => true,
            'depends_on' => ['platform'],
            'reads_from' => ['orders','menu'],
            'owner' => 'includes/push.php + tools/push-worker.php + SOKNA Runtime + service-worker.js',
            'owns_tables' => ['push_action_claims','push_delivery_log','push_event_deliveries','push_event_queue','push_subscriptions','user_notification_preferences'],
            'entrypoints' => ['notification_preferences.php','admin/push_devices.php','api/push_action.php','api/push_drain.php','api/push_kick.php','waiter/api_push.php','service-worker.js'],
            'capabilities' => ['orders_floor','preparation'],
            'background_jobs' => ['SOKNA Runtime -> tools/push-worker.php','opportunistic api/push_drain.php (latency accelerator/fallback)'],
            'public_contracts' => ['push_enqueue_event_tx()','push_process_queue()','signed notification action claim'],
        ],
    ];
    return $registry;
}

function sokna_module(string $key): ?array
{
    $registry = sokna_module_registry();
    return $registry[$key] ?? null;
}

function sokna_module_toggleable(string $key): bool
{
    $module = sokna_module($key);
    return is_array($module) && !empty($module['toggleable']) && empty($module['required']);
}

function sokna_module_setting_key(string $key): string
{
    $module = sokna_module($key);
    return is_array($module) ? trim((string)($module['setting_key'] ?? '')) : '';
}

function sokna_module_configured_enabled(string $key): bool
{
    $module = sokna_module($key);
    if (!$module) return false;
    if (!empty($module['required'])) return true;
    if (!sokna_module_toggleable($key)) return true;
    $default = (bool)($module['default_enabled'] ?? true);
    $settingKey = sokna_module_setting_key($key);
    if ($settingKey === '') return $default;
    return function_exists('setting_bool') ? setting_bool($settingKey, $default) : $default;
}

function sokna_module_enabled(string $key): bool
{
    $module = sokna_module($key);
    if (!$module || !sokna_module_configured_enabled($key)) return false;
    foreach ((array)($module['depends_on'] ?? []) as $dependency) {
        if (!sokna_module_runtime_ready((string)$dependency)) return false;
    }
    return true;
}

/** Effective runtime readiness may be stricter than visibility. Inventory remains visible
 * after re-enable so a full reconciliation count can be completed before consumption resumes. */
function sokna_module_runtime_ready(string $key): bool
{
    if (!sokna_module_enabled($key)) return false;
    $module = sokna_module($key) ?? [];
    $callback = trim((string)($module['runtime_ready'] ?? ''));
    if ($callback === '') return true;
    if (!function_exists($callback)) return false;
    return (bool)$callback();
}

function sokna_module_unready_dependencies(string $key): array
{
    $module = sokna_module($key);
    if (!$module) return [];
    $unready = [];
    foreach ((array)($module['depends_on'] ?? []) as $dependency) {
        $dependency = (string)$dependency;
        if (!sokna_module_runtime_ready($dependency)) $unready[] = $dependency;
    }
    return $unready;
}

function sokna_module_lifecycle_locked(PDO $pdo, string $key, string $stage, int $actorUserId): void
{
    $module = sokna_module($key) ?? [];
    $lifecycle = is_array($module['lifecycle'] ?? null) ? $module['lifecycle'] : [];
    $callback = trim((string)($lifecycle[$stage] ?? ''));
    if ($callback === '') return;
    if (!function_exists($callback)) throw new RuntimeException('قرارداد داخلی این قابلیت کامل بارگذاری نشده است.');
    $callback($pdo, $actorUserId);
}

function sokna_module_setting_state_locked(PDO $pdo, string $key): bool
{
    $module = sokna_module($key) ?? [];
    $settingKey = sokna_module_setting_key($key);
    if ($settingKey === '') return (bool)($module['default_enabled'] ?? true);
    $default = (bool)($module['default_enabled'] ?? true);
    $pdo->prepare('INSERT IGNORE INTO settings(setting_key,setting_value) VALUES(?,?)')->execute([$settingKey, $default ? '1' : '0']);
    $stmt = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key=? FOR UPDATE');
    $stmt->execute([$settingKey]);
    return ((string)$stmt->fetchColumn()) === '1';
}


/** Best-effort UI preflight for disabling a module. It never replaces locked lifecycle guards. */
function sokna_module_disable_preflight(string $key, array &$seen = []): array
{
    if (isset($seen[$key])) return [];
    $seen[$key] = true;
    $module = sokna_module($key) ?? [];
    $out = [];
    $lifecycle = is_array($module['lifecycle'] ?? null) ? $module['lifecycle'] : [];
    $callback = trim((string)($lifecycle['disable_preflight'] ?? ''));
    if ($callback !== '' && function_exists($callback)) {
        try {
            $rows = $callback();
            if (is_array($rows)) foreach ($rows as $row) if (is_array($row) && trim((string)($row['message'] ?? '')) !== '') $out[] = $row;
        } catch (Throwable $e) {
            error_log('module disable preflight ' . $key . ': ' . $e->getMessage());
        }
    }
    foreach (sokna_module_dependents($key) as $dependent) {
        if (!sokna_module_toggleable($dependent) || !sokna_module_configured_enabled($dependent)) continue;
        foreach (sokna_module_disable_preflight($dependent, $seen) as $row) $out[] = $row;
    }
    return $out;
}

function sokna_module_dependents(string $key): array
{
    $out = [];
    foreach (sokna_module_registry() as $candidate => $module) {
        if (in_array($key, array_map('strval', (array)($module['depends_on'] ?? [])), true)) $out[] = (string)$candidate;
    }
    return $out;
}

/**
 * Persist a real module toggle under a row lock. Call inside a transaction and clear the setting
 * cache after commit. `expectedEnabled` is an optimistic snapshot guard for stale admin tabs.
 */
function sokna_module_set_enabled_locked(PDO $pdo, string $key, bool $enabled, int $actorUserId, ?bool $expectedEnabled = null): bool
{
    $module = sokna_module($key);
    if (!$module || !sokna_module_toggleable($key)) throw new RuntimeException('این بخش قابل فعال یا غیرفعال‌کردن نیست.');

    $current = sokna_module_setting_state_locked($pdo, $key);
    if ($expectedEnabled !== null && $current !== $expectedEnabled) throw new RuntimeException('وضعیت این بخش در صفحه دیگری تغییر کرده است؛ صفحه را تازه کنید.');
    if ($current === $enabled) return false;

    if ($enabled) {
        $unready = sokna_module_unready_dependencies($key);
        if ($unready) {
            $labels = array_map(static fn(string $dep): string => (string)(sokna_module($dep)['label'] ?? $dep), $unready);
            throw new RuntimeException('ابتدا ' . implode(' و ', $labels) . ' را فعال و آماده کنید.');
        }
        sokna_module_lifecycle_locked($pdo, $key, 'before_enable', $actorUserId);
    } else {
        sokna_module_lifecycle_locked($pdo, $key, 'before_disable', $actorUserId);
        foreach (sokna_module_dependents($key) as $dependent) {
            $dependentModule = sokna_module($dependent) ?? [];
            if (!sokna_module_toggleable($dependent)) {
                if (!empty($dependentModule['required'])) throw new RuntimeException('این بخش وابستگی پایه سامانه است و فعلاً قابل غیرفعال‌کردن نیست.');
                continue;
            }
            if (sokna_module_setting_state_locked($pdo, $dependent)) {
                sokna_module_set_enabled_locked($pdo, $dependent, false, $actorUserId, null);
            }
        }
    }

    $settingKey = sokna_module_setting_key($key);
    if ($settingKey === '') throw new RuntimeException('تنظیم این بخش کامل تعریف نشده است.');
    $pdo->prepare('UPDATE settings SET setting_value=? WHERE setting_key=?')->execute([$enabled ? '1' : '0', $settingKey]);
    audit_log_write_strict($pdo, 'module.enabled_changed', 'module', $key, [
        'label'=>(string)($module['label'] ?? $key),
        'before'=>$current,
        'after'=>$enabled,
    ], $actorUserId);
    sokna_module_lifecycle_locked($pdo, $key, $enabled ? 'after_enable' : 'after_disable', $actorUserId);
    return true;
}

function sokna_module_require(string $key): void
{
    if (sokna_module_enabled($key)) return;
    render_recovery_error_page(404, 'این بخش فعال نیست', 'این قابلیت در تنظیمات سامانه غیرفعال شده است. از صفحه اصلی بخش کاری خود ادامه دهید.');
}

function sokna_module_require_runtime_ready(string $key): void
{
    sokna_module_require($key);
    if (sokna_module_runtime_ready($key)) return;
    render_recovery_error_page(409, 'این بخش هنوز آماده نیست', 'قابلیت فعال است، اما پیش‌نیاز عملیاتی آن کامل نشده است. وضعیت آماده‌سازی را در مدیریت امکانات بررسی کنید.');
}

function sokna_module_table_owners(): array
{
    $owners = [];
    foreach (sokna_module_registry() as $key => $module) {
        foreach ((array)($module['owns_tables'] ?? []) as $table) {
            $table = (string)$table;
            if ($table === '') continue;
            $owners[$table][] = $key;
        }
    }
    return $owners;
}

function sokna_module_table_owner(string $table): ?string
{
    $owners = sokna_module_table_owners()[$table] ?? [];
    return count($owners) === 1 ? (string)$owners[0] : null;
}

function sokna_module_dependency_errors(): array
{
    $registry = sokna_module_registry();
    $errors = [];
    foreach ($registry as $key => $module) {
        foreach (['depends_on','reads_from'] as $edgeType) {
            foreach ((array)($module[$edgeType] ?? []) as $dependency) {
                if (!isset($registry[$dependency])) $errors[] = $key . ' -> missing:' . $dependency . ' (' . $edgeType . ')';
                if ($dependency === $key) $errors[] = $key . ' -> self (' . $edgeType . ')';
            }
        }
    }

    // Only stable code/API dependencies are required to be acyclic. Read-side composition may
    // intentionally be bidirectional during this incremental modularization phase.
    $visiting = [];
    $visited = [];
    $walk = function (string $key) use (&$walk, &$visiting, &$visited, &$errors, $registry): void {
        if (isset($visited[$key])) return;
        if (isset($visiting[$key])) { $errors[] = 'cycle:' . $key; return; }
        $visiting[$key] = true;
        foreach ((array)($registry[$key]['depends_on'] ?? []) as $dep) if (isset($registry[$dep])) $walk($dep);
        unset($visiting[$key]);
        $visited[$key] = true;
    };
    foreach (array_keys($registry) as $key) $walk($key);

    foreach (sokna_module_table_owners() as $table => $owners) {
        if (count($owners) !== 1) $errors[] = 'table-owner:' . $table . '=' . implode(',', $owners);
    }
    return array_values(array_unique($errors));
}

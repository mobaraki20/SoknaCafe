<?php
declare(strict_types=1);

require_once __DIR__ . '/inventory_seed.php';

function inventory_reconciliation_required(): bool
{
    return setting_bool('inventory_reconciliation_required', false);
}

function inventory_reconciliation_required_locked(PDO $pdo): bool
{
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key='inventory_reconciliation_required' LIMIT 1 FOR UPDATE");
    $stmt->execute();
    return ((string)($stmt->fetchColumn() ?: '0')) === '1';
}

/** Runtime readiness is intentionally stricter than module visibility. After a long disable,
 * Inventory stays visible for reconciliation but order consumption remains stopped. */
function inventory_module_runtime_ready(): bool
{
    return inventory_initialized() && !inventory_reconciliation_required();
}

/** Transaction-scoped readiness. Locking the module switch serializes order/stock writes with
 * enable/disable so an event cannot appear just after the disable safety check has passed. */
function inventory_module_runtime_ready_locked(PDO $pdo): bool
{
    if (!$pdo->inTransaction()) throw new RuntimeException('بررسی عملیاتی انبار باید داخل تراکنش انجام شود.');
    if (!sokna_module_setting_state_locked($pdo, 'inventory')) return false;
    $stmt = $pdo->query("SELECT setting_key,setting_value FROM settings WHERE setting_key IN ('inventory_initialized','inventory_reconciliation_required')");
    $values = ['inventory_initialized'=>'0','inventory_reconciliation_required'=>'0'];
    foreach ($stmt->fetchAll() as $row) $values[(string)$row['setting_key']] = (string)$row['setting_value'];
    return $values['inventory_initialized'] === '1' && $values['inventory_reconciliation_required'] !== '1';
}

function inventory_module_configured_locked(PDO $pdo): bool
{
    if (!$pdo->inTransaction()) throw new RuntimeException('بررسی وضعیت انبار باید داخل تراکنش انجام شود.');
    return sokna_module_setting_state_locked($pdo, 'inventory');
}

/** Refuse disable while an inventory transaction stream or count is unresolved. */
function inventory_module_before_disable_locked(PDO $pdo, int $actorUserId): void
{
    $events = (int)$pdo->query("SELECT COUNT(*) FROM inventory_order_events WHERE status<>'done'")->fetchColumn();
    if ($events > 0) throw new RuntimeException('پیش از غیرفعال‌کردن انبار، همگام‌سازی‌های مصرف فروش را تعیین تکلیف کنید.');
    $counts = (int)$pdo->query("SELECT COUNT(*) FROM inventory_count_sessions WHERE status='draft'")->fetchColumn();
    if ($counts > 0) throw new RuntimeException('پیش از غیرفعال‌کردن انبار، شمارش باز را نهایی یا لغو کنید.');
}

/** Once a previously initialized inventory is disabled, its old balance is no longer trusted. */
function inventory_module_after_disable_locked(PDO $pdo, int $actorUserId): void
{
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key='inventory_initialized' LIMIT 1");
    $stmt->execute();
    $initialized = ((string)($stmt->fetchColumn() ?: '0')) === '1';
    $pdo->prepare("INSERT INTO settings(setting_key,setting_value) VALUES('inventory_reconciliation_required',?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)")
        ->execute([$initialized ? '1' : '0']);
    if ($initialized) {
        audit_log_write_strict($pdo, 'inventory.reconciliation_required', 'inventory', null, [
            'reason'=>'module_disabled',
        ], $actorUserId);
    }
}

function inventory_builtin_category_labels(): array
{
    return [
        'ingredient' => 'مواد اولیه',
        'ready_drink' => 'نوشیدنی آماده',
        'ready_food' => 'خوراکی آماده',
        'packaging' => 'بسته‌بندی و یک‌بارمصرف',
        'consumable' => 'ملزومات مصرفی',
    ];
}

/** Inventory category names are editable master data; item rows keep only the stable key. */
function inventory_category_labels(bool $activeOnly = false, ?PDO $pdo = null): array
{
    static $cache = [];
    $cacheKey = $activeOnly ? 'active' : 'all';
    if (isset($cache[$cacheKey])) return $cache[$cacheKey];
    try {
        $pdo ??= db();
        $sql = 'SELECT category_key,name FROM inventory_categories' . ($activeOnly ? ' WHERE active=1' : '') . ' ORDER BY sort_order,name,category_key';
        $rows = $pdo->query($sql)->fetchAll();
        if ($rows) {
            $labels = [];
            foreach ($rows as $row) $labels[(string)$row['category_key']] = (string)$row['name'];
            return $cache[$cacheKey] = $labels;
        }
    } catch (Throwable $e) {
        // Fresh install/updater may load helpers before the new table exists.
    }
    return $cache[$cacheKey] = inventory_builtin_category_labels();
}

function inventory_department_labels(): array
{
    return ['bar'=>'بار','kitchen'=>'آشپزخانه','shared'=>'مشترک'];
}

function inventory_base_unit_labels(): array
{
    return ['g'=>'گرم','ml'=>'میلی‌لیتر','count'=>'عدد'];
}

function inventory_movement_labels(): array
{
    return [
        'opening_balance'=>'موجودی اولیه',
        'purchase_receive'=>'ورود کالا',
        'recipe_consumption'=>'مصرف فروش',
        'waste'=>'ضایعات',
        'count_adjustment'=>'اصلاح شمارش',
        'purchase_return'=>'برگشت خرید',
        'quantity_correction'=>'اصلاح مقدار',
        'cost_adjustment'=>'اصلاح هزینه',
        'reversal'=>'برگشت/اصلاح جبرانی',
    ];
}

function inventory_review_labels(): array
{
    return ['ready'=>'آماده','needs_review'=>'نیاز به تأیید'];
}

function inventory_cost_status_labels(): array
{
    return [
        'known'=>'قیمت مشخص',
        'estimated'=>'برآوردی',
        'partial'=>'قیمت ناقص',
        'unknown'=>'قیمت نامشخص',
    ];
}

function inventory_normalize_department(string $value, bool $allowEmpty = false): ?string
{
    $value = trim($value);
    if ($allowEmpty && $value === '') return null;
    return isset(inventory_department_labels()[$value]) ? $value : 'shared';
}

function inventory_normalize_base_unit(string $value): string
{
    return isset(inventory_base_unit_labels()[$value]) ? $value : 'count';
}

function inventory_normalize_category(string $value): string
{
    $value = trim($value);
    return isset(inventory_category_labels(false)[$value]) ? $value : 'ingredient';
}

function inventory_seed_if_empty(PDO $pdo): array
{
    try {
        return inventory_seed_initial_catalog($pdo);
    } catch (Throwable $e) {
        error_log('Inventory seed: ' . $e->getMessage());
        return ['seeded'=>false,'items'=>0,'purchase_units'=>0];
    }
}

function inventory_format_quantity(int $quantity, string $unit): string
{
    $unit = inventory_normalize_base_unit($unit);
    $abs = abs($quantity);
    $sign = $quantity < 0 ? '−' : '';
    if ($unit === 'g' && $abs >= 1000) {
        $number = $abs / 1000;
        $text = abs($number - round($number)) < 0.0001 ? number_format($number, 0, '.', '') : rtrim(rtrim(number_format($number, 3, '.', ''), '0'), '.');
        return $sign . fa_digits($text) . ' کیلوگرم';
    }
    if ($unit === 'ml' && $abs >= 1000) {
        $number = $abs / 1000;
        $text = abs($number - round($number)) < 0.0001 ? number_format($number, 0, '.', '') : rtrim(rtrim(number_format($number, 3, '.', ''), '0'), '.');
        return $sign . fa_digits($text) . ' لیتر';
    }
    return $sign . fa_digits($abs) . ' ' . (inventory_base_unit_labels()[$unit] ?? $unit);
}

function inventory_parse_decimal(string|int|float|null $value): float
{
    $raw = trim((string)$value);
    if ($raw === '') return 0.0;
    $raw = str_replace(['٬',',','٫',' '], ['','','.',''], en_digits($raw));
    if (!preg_match('/^(?:\d+(?:\.\d+)?|\.\d+)$/', $raw)) throw new RuntimeException('مقدار عددی واردشده معتبر نیست.');
    $number = (float)$raw;
    if (!is_finite($number) || $number < 0) throw new RuntimeException('مقدار عددی واردشده معتبر نیست.');
    return $number;
}

function inventory_money_value(string|int|float|null $value): int
{
    $raw = trim((string)$value);
    if ($raw === '') return 0;
    $raw = str_replace(['٬',',',' '], '', en_digits($raw));
    if (!preg_match('/^\d+$/', $raw)) throw new RuntimeException('مبلغ واردشده معتبر نیست.');
    if (strlen($raw) > 18) throw new RuntimeException('مبلغ واردشده بیش از حد بزرگ است.');
    $number = (int)$raw;
    if ($number < 0) throw new RuntimeException('مبلغ واردشده معتبر نیست.');
    return $number;
}

function inventory_major_unit_label(string $baseUnit): string
{
    return match (inventory_normalize_base_unit($baseUnit)) {
        'g' => 'کیلوگرم',
        'ml' => 'لیتر',
        default => 'عدد',
    };
}

function inventory_major_to_base(string|int|float|null $value, string $baseUnit): int
{
    $number = inventory_parse_decimal($value);
    $unit = inventory_normalize_base_unit($baseUnit);
    if ($unit === 'count') {
        if (abs($number - round($number)) > 0.000001) throw new RuntimeException('مقدار کالای تعدادی باید عدد صحیح باشد.');
        return (int)round($number);
    }
    return (int)round($number * 1000);
}

function inventory_normalize_occurred_at(string $value,string $label='عملیات'): string
{
    $value=trim($value);
    $ts=strtotime($value);
    if($value===''||$ts===false)throw new RuntimeException('زمان '.$label.' معتبر نیست.');
    if($ts>time()+300)throw new RuntimeException('زمان '.$label.' نمی‌تواند در آینده باشد.');
    return date('Y-m-d H:i:s',$ts);
}

function inventory_optional_occurred_at(string $dateJ, string $time, string $label = 'وقوع عملیات'): ?string
{
    $dateJ = trim($dateJ);
    $time = trim(en_digits($time));
    if ($dateJ === '' && $time === '') return null;
    if ($dateJ === '' && $time !== '') throw new RuntimeException('برای ثبت ساعت دقیق، تاریخ ' . $label . ' را هم مشخص کن.');
    $gregorian = parse_jalali_date($dateJ);
    if (!$gregorian) throw new RuntimeException('تاریخ شمسی ' . $label . ' معتبر نیست.');
    if ($time !== '' && !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time)) throw new RuntimeException('ساعت ' . $label . ' معتبر نیست.');
    $timezone = new DateTimeZone(app_timezone());
    $now = new DateTimeImmutable('now', $timezone);
    if ($time === '') {
        // Date-only backdating is intentionally allowed for small-cafe operations. Use current time for today,
        // and a deterministic midpoint for historical days; metadata records that precision was date-only.
        $time = $gregorian === $now->format('Y-m-d') ? $now->format('H:i') : '12:00';
    }
    $occurred = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $gregorian . ' ' . $time, $timezone);
    if (!$occurred) throw new RuntimeException('زمان ' . $label . ' معتبر نیست.');
    if ($occurred > $now->modify('+5 minutes')) throw new RuntimeException('زمان عملیات نمی‌تواند در آینده باشد.');
    return $occurred->format('Y-m-d H:i:s');
}

function inventory_occurrence_precision(string $dateJ, string $time): string
{
    if (trim($dateJ) === '') return 'recorded_at';
    return trim($time) === '' ? 'date_only' : 'exact';
}

function inventory_purchase_unit_is_operational(array $unit): bool
{
    if ((int)($unit['active'] ?? 0) !== 1) return false;
    $mode = (string)($unit['conversion_mode'] ?? '');
    if ($mode === 'actual_quantity') return true;
    if ($mode !== 'fixed') return false;
    return (int)($unit['base_quantity'] ?? 0) > 0 && (string)($unit['review_status'] ?? '') === 'ready';
}

function inventory_operational_purchase_units(PDO $pdo, int $itemId): array
{
    return array_values(array_filter(inventory_purchase_units($pdo, $itemId, true), 'inventory_purchase_unit_is_operational'));
}

function inventory_adjustment_allowed_modes(string $movementType): array
{
    return match ($movementType) {
        'purchase_receive' => ['quantity','cost','return'],
        'opening_balance', 'waste' => ['quantity'],
        default => [],
    };
}

function inventory_base_to_major_value(int $quantity, string $baseUnit): string
{
    $unit = inventory_normalize_base_unit($baseUnit);
    if ($unit === 'count') return (string)$quantity;
    $value = $quantity / 1000;
    return rtrim(rtrim(number_format($value,3,'.',''),'0'),'.');
}

function inventory_format_major_quantity(int $quantity, string $baseUnit): string
{
    return fa_digits(inventory_base_to_major_value($quantity, $baseUnit)) . ' ' . inventory_major_unit_label($baseUnit);
}

/** Resolve a receiving/waste quantity and keep purchase-unit conversion snapshots together. */
function inventory_resolve_operation_quantity(PDO $pdo, int $itemId, int $purchaseUnitId, mixed $unitCountInput, mixed $actualMajorInput = null): array
{
    $item = inventory_item($pdo,$itemId);
    if (!$item || !(int)$item['active']) throw new RuntimeException('کالای انبار معتبر نیست.');
    $unitCount = inventory_parse_decimal($unitCountInput);
    if ($unitCount <= 0) throw new RuntimeException('مقدار عملیات باید بیشتر از صفر باشد.');
    if ($purchaseUnitId < 1) {
        $baseQty = inventory_major_to_base($unitCount,(string)$item['base_unit']);
        if ($baseQty < 1) throw new RuntimeException('مقدار عملیات معتبر نیست.');
        return [
            'quantity_base'=>$baseQty,
            'purchase_unit_id'=>null,
            'purchase_unit_name_snapshot'=>null,
            'purchase_unit_count'=>null,
            'conversion_base_quantity_snapshot'=>null,
            'conversion_mode'=>'base',
        ];
    }
    $stmt=$pdo->prepare('SELECT * FROM inventory_purchase_units WHERE id=? AND inventory_item_id=? AND active=1');
    $stmt->execute([$purchaseUnitId,$itemId]);
    $unit=$stmt->fetch();
    if(!$unit) throw new RuntimeException('واحد خرید معتبر نیست.');
    if(!inventory_purchase_unit_is_operational($unit)) throw new RuntimeException('این واحد خرید هنوز برای عملیات روزانه آماده استفاده نیست.');
    $mode=(string)$unit['conversion_mode'];
    $conversion=null;
    if($mode==='actual_quantity'){
        $baseQty=inventory_major_to_base($actualMajorInput,(string)$item['base_unit']);
        if($baseQty<1) throw new RuntimeException('مقدار واقعی این ورود را وارد کن.');
    }else{
        $conversion=(int)($unit['base_quantity']??0);
        if($conversion<1) throw new RuntimeException('تبدیل این واحد خرید هنوز کامل نشده است.');
        $baseQty=(int)round($unitCount*$conversion);
    }
    if($baseQty<1) throw new RuntimeException('مقدار عملیات معتبر نیست.');
    return [
        'quantity_base'=>$baseQty,
        'purchase_unit_id'=>(int)$unit['id'],
        'purchase_unit_name_snapshot'=>(string)$unit['name'],
        'purchase_unit_count'=>$unitCount,
        'conversion_base_quantity_snapshot'=>$conversion,
        'conversion_mode'=>$mode,
    ];
}

/**
 * Create an inventory master-data item that must be reviewed before it is treated as fully classified.
 * Cross-domain workflows (for example Supply) must call this owner contract instead of writing
 * inventory_items/inventory_balances directly. Caller owns the surrounding transaction.
 */
function inventory_create_unreviewed_item_locked(
    PDO $pdo,
    string $name,
    string $baseUnit,
    string $department,
    int $actorUserId,
    string $sourceLabel = 'external_workflow'
): int {
    $name = text_substr(trim($name), 0, 160);
    if ($name === '') throw new RuntimeException('نام کالای جدید مشخص نیست.');
    $baseUnit = inventory_normalize_base_unit($baseUnit);
    $department = inventory_normalize_department($department) ?? 'shared';
    $sourceLabel = text_substr(trim($sourceLabel), 0, 80) ?: 'external_workflow';
    $code = 'INV-' . strtoupper(bin2hex(random_bytes(5)));
    $reviewNote = 'این کالا از فرایند «' . $sourceLabel . '» ساخته شده است؛ دسته، واحد، حد هشدار و واحد خرید را بررسی کن.';

    $stmt = $pdo->prepare("INSERT INTO inventory_items(item_code,name,category,base_unit,default_department,warning_threshold,review_status,review_note,active,created_by_user_id) VALUES(?,?,'ingredient',?,?,0,'needs_review',?,1,?)");
    $stmt->execute([$code,$name,$baseUnit,$department,$reviewNote,$actorUserId]);
    $itemId = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT IGNORE INTO inventory_balances(inventory_item_id,quantity_base,average_unit_cost,cost_status) VALUES(?,0,NULL,'unknown')")->execute([$itemId]);
    audit_log_write_strict($pdo,'inventory.item_created_from_supply','inventory_item',$itemId,[
        'name'=>$name,'base_unit'=>$baseUnit,'department'=>$department,'review_status'=>'needs_review','source'=>$sourceLabel,
    ],$actorUserId);
    return $itemId;
}

function inventory_item(PDO $pdo, int $itemId, bool $forUpdate = false): ?array
{
    $sql = 'SELECT i.*,b.quantity_base,b.average_unit_cost,b.cost_status FROM inventory_items i LEFT JOIN inventory_balances b ON b.inventory_item_id=i.id WHERE i.id=?' . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$itemId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function inventory_purchase_units(PDO $pdo, int $itemId, bool $activeOnly = true): array
{
    $sql = 'SELECT * FROM inventory_purchase_units WHERE inventory_item_id=?' . ($activeOnly ? ' AND active=1' : '') . ' ORDER BY active DESC,id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$itemId]);
    return $stmt->fetchAll();
}

function inventory_balance_locked(PDO $pdo, int $itemId): array
{
    $pdo->prepare("INSERT IGNORE INTO inventory_balances(inventory_item_id,quantity_base,average_unit_cost,cost_status) VALUES(?,0,NULL,'unknown')")->execute([$itemId]);
    $stmt = $pdo->prepare('SELECT * FROM inventory_balances WHERE inventory_item_id=? FOR UPDATE');
    $stmt->execute([$itemId]);
    $row = $stmt->fetch();
    if (!$row) throw new RuntimeException('موجودی کالا قابل خواندن نیست.');
    return $row;
}

/**
 * Rebuild the current moving-average projection from the immutable ledger.
 * Quantity/cost corrections are folded into their original movement for replay so a
 * late correction cannot dump the whole historical difference onto today's stock.
 * This is intentionally a small-cafe projection, not an accounting revaluation engine.
 */
function inventory_projection_replay_rows(array $rows, ?string $throughOccurredAt = null): array
{
    $quantityCorrections = [];
    $costCorrections = [];
    foreach ($rows as $row) {
        $targetId = (int)($row['correction_of_id'] ?? 0);
        if ($targetId < 1) continue;
        if ((string)$row['movement_type'] === 'quantity_correction') {
            $quantityCorrections[$targetId] = ($quantityCorrections[$targetId] ?? 0) + (int)$row['quantity_base'];
        } elseif ((string)$row['movement_type'] === 'cost_adjustment') {
            $costCorrections[$targetId] = ($costCorrections[$targetId] ?? 0) + (int)($row['total_cost_delta'] ?? 0);
        }
    }

    $quantity = 0;
    $average = null;
    $costStatus = 'unknown';
    $movementUnitCosts = [];
    $movementCostStatuses = [];

    foreach ($rows as $row) {
        $type = (string)$row['movement_type'];
        if ($type === 'quantity_correction' || $type === 'cost_adjustment') continue;
        $occurredAt = (string)$row['occurred_at'];
        if ($throughOccurredAt !== null && $occurredAt > $throughOccurredAt) continue;

        $movementId = (int)$row['id'];
        $delta = (int)$row['quantity_base'] + (int)($quantityCorrections[$movementId] ?? 0);
        $oldQuantity = $quantity;
        $newQuantity = $oldQuantity + $delta;
        $unitCost = null;
        $movementStatus = 'unknown';

        if ($delta > 0 && in_array($type, ['purchase_receive','opening_balance'], true)) {
            $totalCost = $row['total_cost_delta'] === null ? null : (int)$row['total_cost_delta'];
            if (array_key_exists($movementId, $costCorrections)) $totalCost = ($totalCost ?? 0) + (int)$costCorrections[$movementId];
            if ($totalCost !== null && $totalCost < 0) throw new RuntimeException('اصلاح هزینه، ارزش یک ورود انبار را منفی کرده است.');
            if ($totalCost !== null) {
                $unitCost = abs($totalCost / max(1, $delta));
                if ($oldQuantity > 0 && $average !== null) {
                    $average = (($oldQuantity * $average) + $totalCost) / max(1, $newQuantity);
                    $costStatus = $costStatus === 'known' ? 'known' : 'partial';
                } elseif ($oldQuantity > 0) {
                    $average = $unitCost;
                    $costStatus = 'partial';
                } elseif ($newQuantity > 0) {
                    $average = $unitCost;
                    $costStatus = 'known';
                }
                $movementStatus = 'known';
            } else {
                $movementStatus = $average !== null ? 'estimated' : 'unknown';
                $costStatus = $average !== null ? 'partial' : 'unknown';
                $unitCost = $average;
            }
            $quantity = $newQuantity;
        } else {
            if ($type === 'reversal' && $delta > 0) {
                $targetId = (int)($row['reversal_of_id'] ?? 0);
                $unitCost = $targetId > 0 ? ($movementUnitCosts[$targetId] ?? null) : null;
                if ($unitCost === null && $row['unit_cost_snapshot'] !== null) $unitCost = (float)$row['unit_cost_snapshot'];
                if ($unitCost === null) $unitCost = $average;
                $movementStatus = $targetId > 0 ? ($movementCostStatuses[$targetId] ?? (string)$row['cost_status']) : (string)$row['cost_status'];
                if ($newQuantity > 0 && $unitCost !== null) {
                    $oldValue = $average !== null ? $oldQuantity * $average : 0.0;
                    $newValue = $oldValue + ($delta * $unitCost);
                    if ($newValue >= 0) {
                        $average = $newValue / $newQuantity;
                        $costStatus = $costStatus === 'known' && $movementStatus === 'known' ? 'known' : 'partial';
                    }
                }
            } else {
                // Ordinary consumption, waste, returns and count differences leave the
                // moving-average rate unchanged; their effective replay cost is the rate
                // that existed at that point in time.
                $unitCost = $average;
                $movementStatus = $unitCost === null ? 'unknown' : ($costStatus === 'known' ? 'known' : 'estimated');
            }
            $quantity = $newQuantity;
            if ($quantity <= 0 && $average === null) $costStatus = 'unknown';
        }

        $movementUnitCosts[$movementId] = $unitCost;
        $movementCostStatuses[$movementId] = $movementStatus;
    }

    return [
        'quantity_base'=>$quantity,
        'average_unit_cost'=>$average,
        'cost_status'=>$costStatus,
        'movement_unit_costs'=>$movementUnitCosts,
        'movement_cost_statuses'=>$movementCostStatuses,
    ];
}


function inventory_projection_replay_locked(PDO $pdo, int $itemId, ?string $throughOccurredAt = null): array
{
    if ($itemId < 1) throw new RuntimeException('کالای انبار معتبر نیست.');
    $stmt = $pdo->prepare('SELECT * FROM inventory_movements WHERE inventory_item_id=? ORDER BY occurred_at,id FOR UPDATE');
    $stmt->execute([$itemId]);
    return inventory_projection_replay_rows($stmt->fetchAll(),$throughOccurredAt);
}

function inventory_rebuild_projection_locked(PDO $pdo, int $itemId): array
{
    $projection = inventory_projection_replay_locked($pdo,$itemId);
    inventory_balance_locked($pdo,$itemId);
    $pdo->prepare('UPDATE inventory_balances SET quantity_base=?,average_unit_cost=?,cost_status=?,updated_at=NOW() WHERE inventory_item_id=?')
        ->execute([(int)$projection['quantity_base'],$projection['average_unit_cost'],(string)$projection['cost_status'],$itemId]);
    return $projection;
}

/**
 * Write one immutable inventory movement and update its balance projection.
 * Call inside an existing transaction whenever the caller needs atomic multi-step work.
 */
function inventory_record_movement_locked(PDO $pdo, array $data): int
{
    $itemId = (int)($data['item_id'] ?? 0);
    $type = trim((string)($data['movement_type'] ?? ''));
    $quantity = (int)($data['quantity_base'] ?? 0);
    if ($itemId < 1 || !isset(inventory_movement_labels()[$type])) throw new RuntimeException('حرکت انبار معتبر نیست.');
    if ($type !== 'cost_adjustment' && $quantity === 0) throw new RuntimeException('مقدار حرکت انبار نمی‌تواند صفر باشد.');
    $countMovement = in_array($type, ['opening_balance','count_adjustment'], true);
    if ($countMovement) {
        if (!inventory_module_configured_locked($pdo)) throw new RuntimeException('انبار در حال حاضر غیرفعال است.');
    } elseif (!inventory_module_runtime_ready_locked($pdo)) {
        throw new RuntimeException('انبار برای ثبت عملیات روزانه آماده نیست.');
    }

    $item = inventory_item($pdo, $itemId, true);
    if (!$item) throw new RuntimeException('کالای انبار پیدا نشد.');
    $baseUnit = inventory_normalize_base_unit((string)$item['base_unit']);
    $balance = inventory_balance_locked($pdo, $itemId);
    $oldQty = (int)$balance['quantity_base'];
    $oldAvg = $balance['average_unit_cost'] === null ? null : (float)$balance['average_unit_cost'];
    $oldCostStatus = (string)($balance['cost_status'] ?? 'unknown');
    $newQty = $oldQty + $quantity;
    $occurredAt = trim((string)($data['occurred_at'] ?? ''));
    $requiresChronologicalRebuild = in_array($type, ['quantity_correction','cost_adjustment'], true);
    $costBasisAverage = $oldAvg;
    $costBasisStatus = $oldCostStatus;
    if ($occurredAt !== '') {
        $latestStmt = $pdo->prepare('SELECT occurred_at FROM inventory_movements WHERE inventory_item_id=? ORDER BY occurred_at DESC,id DESC LIMIT 1');
        $latestStmt->execute([$itemId]);
        $latestOccurredAt = (string)($latestStmt->fetchColumn() ?: '');
        if ($latestOccurredAt !== '' && $occurredAt < $latestOccurredAt) {
            $requiresChronologicalRebuild = true;
            $historicalProjection = inventory_projection_replay_locked($pdo,$itemId,$occurredAt);
            $costBasisAverage = $historicalProjection['average_unit_cost'] === null ? null : (float)$historicalProjection['average_unit_cost'];
            $costBasisStatus = (string)$historicalProjection['cost_status'];
        }
    }

    $explicitUnitCost = array_key_exists('unit_cost_snapshot', $data) && $data['unit_cost_snapshot'] !== null
        ? max(0.0, (float)$data['unit_cost_snapshot']) : null;
    $explicitTotalCost = array_key_exists('total_cost_delta', $data) && $data['total_cost_delta'] !== null
        ? (int)$data['total_cost_delta'] : null;
    $lockCostBasis = !empty($data['lock_cost_basis']);
    $unitCost = $explicitUnitCost;
    if ($unitCost === null && $explicitTotalCost !== null && $quantity !== 0) $unitCost = abs($explicitTotalCost / $quantity);
    if ($unitCost === null && !$lockCostBasis && $costBasisAverage !== null) $unitCost = $costBasisAverage;

    $movementCostStatus = (string)($data['cost_status'] ?? ($explicitTotalCost !== null ? 'known' : ($unitCost !== null ? ($costBasisStatus === 'known' ? 'known' : 'estimated') : 'unknown')));
    if (!in_array($movementCostStatus, ['known','estimated','unknown'], true)) $movementCostStatus = 'unknown';
    $totalCostDelta = $explicitTotalCost;
    if ($totalCostDelta === null && $unitCost !== null && $movementCostStatus !== 'unknown') {
        $totalCostDelta = (int)round($quantity * $unitCost);
    }

    $newAvg = $oldAvg;
    $newCostStatus = $oldCostStatus;
    $revalueBalance = !empty($data['revalue_balance']);
    $costBearingInbound = $quantity > 0 && in_array($type, ['purchase_receive','opening_balance'], true);

    if ($type === 'cost_adjustment') {
        if ($explicitTotalCost === null) throw new RuntimeException('مبلغ اصلاح هزینه مشخص نیست.');
        // The correction belongs to the original receipt/opening movement. Current
        // average cost is rebuilt chronologically after insertion; never apply the
        // whole historical delta to only today's remaining quantity.
    } elseif ($costBearingInbound) {
        if ($explicitTotalCost !== null) {
            $receiptUnitCost = abs($explicitTotalCost / max(1, $quantity));
            $unitCost = $receiptUnitCost;
            if ($oldQty > 0 && $oldAvg !== null) {
                $newAvg = (($oldQty * $oldAvg) + $explicitTotalCost) / max(1, $newQty);
                $newCostStatus = $oldCostStatus === 'known' ? 'known' : 'partial';
            } elseif ($oldQty > 0) {
                // Existing positive stock has unknown historical cost. Use the new receipt
                // only as an estimate; never relabel the whole balance as fully known.
                $newAvg = $receiptUnitCost;
                $newCostStatus = 'partial';
            } elseif ($newQty > 0) {
                // Negative/zero stock must not distort the next valid cost basis.
                $newAvg = $receiptUnitCost;
                $newCostStatus = 'known';
            }
        } else {
            $newCostStatus = $oldAvg !== null ? 'partial' : 'unknown';
        }
    } elseif ($revalueBalance && $newQty > 0 && $totalCostDelta !== null) {
        $oldValue = $oldAvg !== null ? $oldQty * $oldAvg : 0.0;
        $newValue = $oldValue + $totalCostDelta;
        if ($newValue >= 0) $newAvg = $newValue / $newQty;
    } elseif ($newQty <= 0 && $oldAvg === null) {
        $newCostStatus = 'unknown';
    }

    $department = inventory_normalize_department((string)($data['department'] ?? ''), true);
    $idempotencyKey = trim((string)($data['idempotency_key'] ?? '')) ?: null;
    if ($idempotencyKey !== null) {
        $dup = $pdo->prepare('SELECT id FROM inventory_movements WHERE idempotency_key=? LIMIT 1');
        $dup->execute([$idempotencyKey]);
        $existingId = (int)($dup->fetchColumn() ?: 0);
        if ($existingId > 0) return $existingId;
    }

    $metadata = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];
    $stmt = $pdo->prepare('INSERT INTO inventory_movements(inventory_item_id,movement_type,quantity_base,base_unit,department,purchase_unit_id,purchase_unit_name_snapshot,purchase_unit_count,conversion_base_quantity_snapshot,unit_cost_snapshot,total_cost_delta,cost_status,source_type,source_id,correction_of_id,reversal_of_id,idempotency_key,metadata_json,note,actor_user_id,occurred_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,COALESCE(?,NOW()))');
    $stmt->execute([
        $itemId,
        $type,
        $quantity,
        $baseUnit,
        $department,
        !empty($data['purchase_unit_id']) ? (int)$data['purchase_unit_id'] : null,
        trim((string)($data['purchase_unit_name_snapshot'] ?? '')) ?: null,
        array_key_exists('purchase_unit_count', $data) && $data['purchase_unit_count'] !== null ? (float)$data['purchase_unit_count'] : null,
        array_key_exists('conversion_base_quantity_snapshot', $data) && $data['conversion_base_quantity_snapshot'] !== null ? (int)$data['conversion_base_quantity_snapshot'] : null,
        $unitCost,
        $totalCostDelta,
        $movementCostStatus,
        trim((string)($data['source_type'] ?? '')) ?: null,
        array_key_exists('source_id', $data) && $data['source_id'] !== null ? text_substr((string)$data['source_id'], 0, 100) : null,
        !empty($data['correction_of_id']) ? (int)$data['correction_of_id'] : null,
        !empty($data['reversal_of_id']) ? (int)$data['reversal_of_id'] : null,
        $idempotencyKey,
        $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : null,
        trim((string)($data['note'] ?? '')) ?: null,
        !empty($data['actor_user_id']) ? (int)$data['actor_user_id'] : null,
        $occurredAt !== '' ? $occurredAt : null,
    ]);
    $movementId = (int)$pdo->lastInsertId();

    $pdo->prepare('UPDATE inventory_balances SET quantity_base=?,average_unit_cost=?,cost_status=?,updated_at=NOW() WHERE inventory_item_id=?')
        ->execute([$newQty,$newAvg,$newCostStatus,$itemId]);
    if ($requiresChronologicalRebuild) {
        $projection = inventory_rebuild_projection_locked($pdo,$itemId);
        $newQty = (int)$projection['quantity_base'];
        $newAvg = $projection['average_unit_cost'] === null ? null : (float)$projection['average_unit_cost'];
        $newCostStatus = (string)$projection['cost_status'];
    }

    audit_log_write_strict($pdo,'inventory.movement_created','inventory_movement',$movementId,[
        'item_id'=>$itemId,
        'movement_type'=>$type,
        'quantity_delta'=>$quantity,
        'balance_before'=>$oldQty,
        'balance_after'=>$newQty,
        'department'=>$department,
        'cost_status'=>$movementCostStatus,
        'total_cost_delta'=>$totalCostDelta,
        'source_type'=>$data['source_type'] ?? null,
        'source_id'=>$data['source_id'] ?? null,
    ], !empty($data['actor_user_id']) ? (int)$data['actor_user_id'] : null);

    return $movementId;
}

function inventory_active_recipe_usage(PDO $pdo, int $inventoryItemId, int $limit = 5): array
{
    if ($inventoryItemId < 1) return [];
    $limit = max(1,min(20,$limit));
    $stmt = $pdo->prepare("SELECT DISTINCT mi.id,mi.name
        FROM inventory_recipe_components c
        JOIN inventory_recipe_versions rv ON rv.id=c.recipe_version_id AND rv.status='active'
        JOIN items mi ON mi.id=rv.menu_item_id AND mi.active=1
        WHERE c.inventory_item_id=?
        ORDER BY mi.name,mi.id
        LIMIT {$limit}");
    $stmt->execute([$inventoryItemId]);
    return $stmt->fetchAll();
}

function inventory_normalize_recipe_components(array $components): array
{
    $normalized = [];
    foreach ($components as $component) {
        if (!is_array($component)) throw new RuntimeException('اطلاعات مواد مصرفی معتبر نیست.');
        $inventoryItemId = (int)($component['inventory_item_id'] ?? 0);
        $quantity = (int)($component['quantity_base'] ?? 0);
        if ($inventoryItemId < 1 || $quantity < 1) throw new RuntimeException('برای هر ماده انبار، مقدار مصرف معتبر وارد کن.');
        if (isset($normalized[$inventoryItemId])) throw new RuntimeException('یک ماده انبار نمی‌تواند دو بار در فهرست مواد مصرفی ثبت شود.');
        $normalized[$inventoryItemId] = $quantity;
    }
    ksort($normalized);
    return $normalized;
}

function inventory_validate_recipe_components_locked(PDO $pdo, array $normalized): void
{
    if (!$normalized) return;
    $ids = array_map('intval',array_keys($normalized));
    $marks = implode(',',array_fill(0,count($ids),'?'));
    $stmt = $pdo->prepare("SELECT id,name,active FROM inventory_items WHERE id IN ($marks) ORDER BY id FOR UPDATE");
    $stmt->execute($ids);
    $rows = $stmt->fetchAll();
    if (count($rows) !== count($ids)) throw new RuntimeException('یکی از مواد انتخاب‌شده در انبار پیدا نشد.');
    $inactive = [];
    foreach ($rows as $row) if ((int)$row['active'] !== 1) $inactive[] = (string)$row['name'];
    if ($inactive) throw new RuntimeException('این ماده برای مصرف خودکار فعال نیست: '.implode('، ',array_slice($inactive,0,3)));
}

/** Current recipe cost preview. Recipe quantities are persistent; prices come from the current ledger projection. */
function inventory_recipe_cost_preview(PDO $pdo, array $components, int $salePrice = 0): array
{
    if (!$components) return ['component_count'=>0,'known_subtotal'=>0,'unknown_count'=>0,'estimated_count'=>0,'ratio'=>null];
    $normalized = inventory_normalize_recipe_components($components);
    $ids = array_map('intval',array_keys($normalized));
    if (!$ids) return ['component_count'=>0,'known_subtotal'=>0,'unknown_count'=>0,'estimated_count'=>0,'ratio'=>null];
    $marks = implode(',',array_fill(0,count($ids),'?'));
    $stmt = $pdo->prepare("SELECT i.id,b.average_unit_cost,COALESCE(b.cost_status,'unknown') cost_status FROM inventory_items i LEFT JOIN inventory_balances b ON b.inventory_item_id=i.id WHERE i.id IN ($marks)");
    $stmt->execute($ids);
    $costs=[];foreach($stmt->fetchAll() as $row)$costs[(int)$row['id']]=$row;
    $known=0;$unknown=0;$estimated=0;
    foreach($normalized as $itemId=>$quantity){$row=$costs[(int)$itemId]??null;if(!$row||$row['average_unit_cost']===null){$unknown++;continue;}$known+=(int)round((int)$quantity*(float)$row['average_unit_cost']);if((string)$row['cost_status']!=='known')$estimated++;}
    return [
        'component_count'=>count($normalized),'known_subtotal'=>$known,'unknown_count'=>$unknown,'estimated_count'=>$estimated,
        'ratio'=>$salePrice>0&&$unknown===0?($known/$salePrice*100):null,
    ];
}

function inventory_active_recipe(PDO $pdo, int $menuItemId): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM inventory_recipe_versions WHERE menu_item_id=? AND status='active' ORDER BY version_no DESC,id DESC LIMIT 1");
    $stmt->execute([$menuItemId]);
    $recipe = $stmt->fetch();
    if (!$recipe) return null;
    $components = $pdo->prepare("SELECT c.*,i.name inventory_item_name,i.base_unit,b.average_unit_cost,COALESCE(b.cost_status,'unknown') balance_cost_status FROM inventory_recipe_components c JOIN inventory_items i ON i.id=c.inventory_item_id LEFT JOIN inventory_balances b ON b.inventory_item_id=i.id WHERE c.recipe_version_id=? ORDER BY c.id");
    $components->execute([(int)$recipe['id']]);
    $recipe['components'] = $components->fetchAll();
    return $recipe;
}

/** Create a new immutable recipe version for a menu item. */
function inventory_save_recipe_locked(PDO $pdo, int $menuItemId, array $components, int $actorUserId): ?int
{
    $normalized = inventory_normalize_recipe_components($components);

    $current = inventory_active_recipe($pdo, $menuItemId);
    $currentNormalized = [];
    foreach ((array)($current['components'] ?? []) as $component) $currentNormalized[(int)$component['inventory_item_id']] = (int)$component['quantity_base'];
    ksort($currentNormalized);
    if ($normalized === $currentNormalized) return $current ? (int)$current['id'] : null;

    // Only a changed/new recipe is validated against today's active inventory
    // catalogue. Historical versions remain readable even if an old ingredient
    // is later retired.
    inventory_validate_recipe_components_locked($pdo,$normalized);

    if ($current) $pdo->prepare("UPDATE inventory_recipe_versions SET status='retired',retired_at=NOW() WHERE id=? AND status='active'")->execute([(int)$current['id']]);
    if (!$normalized) {
        audit_log_write('inventory.recipe_removed','menu_item',$menuItemId,['previous_recipe_id'=>$current['id'] ?? null],$actorUserId);
        return null;
    }

    $versionStmt = $pdo->prepare('SELECT COALESCE(MAX(version_no),0)+1 FROM inventory_recipe_versions WHERE menu_item_id=?');
    $versionStmt->execute([$menuItemId]);
    $versionNo = (int)$versionStmt->fetchColumn();
    $pdo->prepare("INSERT INTO inventory_recipe_versions(menu_item_id,version_no,status,created_by_user_id) VALUES(?,?,'active',?)")
        ->execute([$menuItemId,$versionNo,$actorUserId]);
    $recipeId = (int)$pdo->lastInsertId();
    $componentStmt = $pdo->prepare('INSERT INTO inventory_recipe_components(recipe_version_id,inventory_item_id,quantity_base) VALUES(?,?,?)');
    foreach ($normalized as $inventoryItemId=>$quantity) $componentStmt->execute([$recipeId,$inventoryItemId,$quantity]);
    audit_log_write('inventory.recipe_version_created','menu_item',$menuItemId,['recipe_id'=>$recipeId,'version_no'=>$versionNo,'components'=>$normalized],$actorUserId);
    return $recipeId;
}

/** Capture the exact recipe snapshot that exists when an order becomes accounted. */
function inventory_order_recipe_snapshot(PDO $pdo, int $orderId): array
{
    if ($orderId < 1) return [];
    $stmt = $pdo->prepare('SELECT oi.id,oi.item_id,oi.quantity,oi.preparation_station FROM order_items oi WHERE oi.order_id=? AND oi.item_id IS NOT NULL ORDER BY oi.id');
    $stmt->execute([$orderId]);
    $snapshot = [];
    foreach ($stmt->fetchAll() as $orderItem) {
        if (!preparation_station_requires_work((string)($orderItem['preparation_station'] ?? 'cold_bar'))) continue;
        $menuItemId = (int)$orderItem['item_id'];
        $recipe = inventory_active_recipe($pdo, $menuItemId);
        $components = [];
        foreach ((array)($recipe['components'] ?? []) as $component) {
            $components[] = [
                'recipe_component_id'=>(int)$component['id'],
                'inventory_item_id'=>(int)$component['inventory_item_id'],
                'quantity_base'=>(int)$component['quantity_base'],
                // Cost basis is captured when the order becomes accounted so a delayed
                // outbox worker can never price yesterday's sale using a later purchase.
                'unit_cost_snapshot'=>$component['average_unit_cost'] === null ? null : (float)$component['average_unit_cost'],
                'cost_status'=>(string)($component['balance_cost_status'] ?? 'unknown'),
            ];
        }
        $snapshot[] = [
            'order_item_id'=>(int)$orderItem['id'],
            'menu_item_id'=>$menuItemId,
            'order_quantity'=>max(0,(int)$orderItem['quantity']),
            'department'=>preparation_area_for_station((string)$orderItem['preparation_station']),
            'recipe_version_id'=>$recipe ? (int)$recipe['id'] : null,
            'recipe_version_no'=>$recipe ? (int)$recipe['version_no'] : null,
            'components'=>$components,
        ];
    }
    return $snapshot;
}

/** Queue an order-side inventory event in the same local DB transaction as the order. */
function inventory_enqueue_order_event_tx(PDO $pdo, string $eventType, int $orderId, int $actorUserId, array $payload = [], ?string $idempotencyKey = null): void
{
    if (!sokna_module_runtime_ready('inventory')) return;
    if ($orderId < 1 || !in_array($eventType, ['accounted','quantity_adjusted','cancelled','reaccounted'], true)) return;
    try {
        if (!inventory_module_runtime_ready_locked($pdo)) return;
        // Snapshot before commit so a later recipe edit can never rewrite historical consumption.
        if ($eventType === 'accounted' && !array_key_exists('recipe_snapshot', $payload)) {
            $payload['recipe_snapshot'] = inventory_order_recipe_snapshot($pdo, $orderId);
        }
        $idempotencyKey = $idempotencyKey ?: $eventType . ':order:' . $orderId . ':' . hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
        $stmt = $pdo->prepare("INSERT IGNORE INTO inventory_order_events(event_type,order_id,order_item_id,payload_json,idempotency_key,status,actor_user_id) VALUES(?,?,?,?,?,'pending',?)");
        $stmt->execute([
            $eventType,
            $orderId,
            !empty($payload['order_item_id']) ? (int)$payload['order_item_id'] : null,
            $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : null,
            text_substr($idempotencyKey, 0, 190),
            $actorUserId ?: null,
        ]);
    } catch (Throwable $e) {
        // Inventory must never make the protected ordering path depend on a secondary module.
        error_log('Inventory outbox enqueue: ' . $e->getMessage());
    }
}

function inventory_apply_order_consumption_locked(PDO $pdo, int $orderId, int $actorUserId, ?array $recipeSnapshot = null, ?string $occurredAt = null): int
{
    $recipeSnapshot ??= inventory_order_recipe_snapshot($pdo, $orderId);
    $created = 0;
    foreach ($recipeSnapshot as $orderItem) {
        if (!is_array($orderItem)) continue;
        $menuItemId = (int)($orderItem['menu_item_id'] ?? 0);
        $orderItemId = (int)($orderItem['order_item_id'] ?? 0);
        $orderQuantity = max(0, (int)($orderItem['order_quantity'] ?? 0));
        $recipeVersionId = (int)($orderItem['recipe_version_id'] ?? 0);
        if ($orderItemId < 1 || $orderQuantity < 1 || $recipeVersionId < 1) continue;
        $department = inventory_normalize_department((string)($orderItem['department'] ?? 'shared')) ?? 'shared';
        foreach ((array)($orderItem['components'] ?? []) as $component) {
            if (!is_array($component)) continue;
            $inventoryItemId = (int)($component['inventory_item_id'] ?? 0);
            $perUnit = max(0,(int)($component['quantity_base'] ?? 0));
            $componentId = (int)($component['recipe_component_id'] ?? 0);
            if ($inventoryItemId < 1 || $perUnit < 1) continue;
            $qty = -($perUnit * $orderQuantity);
            $componentKey = $componentId > 0 ? 'component:' . $componentId : 'item:' . $inventoryItemId;
            $key = 'inventory:recipe:order-item:' . $orderItemId . ':recipe:' . $recipeVersionId . ':' . $componentKey;
            $snapshotHasCostBasis = array_key_exists('unit_cost_snapshot',$component) || array_key_exists('cost_status',$component);
            $snapshotUnitCost = array_key_exists('unit_cost_snapshot',$component) && $component['unit_cost_snapshot'] !== null ? (float)$component['unit_cost_snapshot'] : null;
            $snapshotCostStatusRaw = (string)($component['cost_status'] ?? 'unknown');
            $snapshotCostStatus = $snapshotCostStatusRaw === 'known' ? 'known' : ($snapshotUnitCost !== null ? 'estimated' : 'unknown');
            inventory_record_movement_locked($pdo,[
                'item_id'=>$inventoryItemId,
                'movement_type'=>'recipe_consumption',
                'quantity_base'=>$qty,
                'department'=>$department,
                'source_type'=>'order_item',
                'source_id'=>$orderItemId,
                'idempotency_key'=>$key,
                'unit_cost_snapshot'=>$snapshotUnitCost,
                'cost_status'=>$snapshotCostStatus,
                'lock_cost_basis'=>$snapshotHasCostBasis,
                'occurred_at'=>$occurredAt,
                'metadata'=>[
                    'order_id'=>$orderId,
                    'order_item_id'=>$orderItemId,
                    'menu_item_id'=>$menuItemId,
                    'recipe_version_id'=>$recipeVersionId,
                    'recipe_version_no'=>(int)($orderItem['recipe_version_no'] ?? 0),
                    'recipe_component_id'=>$componentId ?: null,
                    'per_menu_item_quantity_base'=>$perUnit,
                    'order_quantity'=>$orderQuantity,
                ],
                'actor_user_id'=>$actorUserId,
            ]);
            $created++;
        }
    }
    return $created;
}

function inventory_adjust_order_item_consumption_locked(PDO $pdo, int $orderItemId, int $previousQuantity, int $newQuantity, int $adjustmentId, int $actorUserId, ?string $occurredAt = null, ?int $restoreQuantity = null): int
{
    if ($newQuantity >= $previousQuantity) return 0;
    $delta = $previousQuantity - $newQuantity;
    if ($restoreQuantity !== null) $delta = max(0, min($delta, $restoreQuantity));
    if ($delta < 1) return 0;
    $stmt = $pdo->prepare("SELECT m.* FROM inventory_movements m WHERE m.movement_type='recipe_consumption' AND m.source_type='order_item' AND m.source_id=? ORDER BY m.id FOR UPDATE");
    $stmt->execute([(string)$orderItemId]);
    $created = 0;
    foreach ($stmt->fetchAll() as $movement) {
        $metadata = json_decode((string)($movement['metadata_json'] ?? ''), true);
        $perUnit = max(0, (int)($metadata['per_menu_item_quantity_base'] ?? 0));
        if ($perUnit < 1) continue;
        $qty = $perUnit * $delta;
        $key = 'inventory:recipe-adjust:' . $adjustmentId . ':movement:' . (int)$movement['id'];
        inventory_record_movement_locked($pdo,[
            'item_id'=>(int)$movement['inventory_item_id'],
            'movement_type'=>'reversal',
            'quantity_base'=>$qty,
            'department'=>$movement['department'] ?: null,
            'unit_cost_snapshot'=>$movement['unit_cost_snapshot'] !== null ? (float)$movement['unit_cost_snapshot'] : null,
            'cost_status'=>(string)$movement['cost_status'],
            'lock_cost_basis'=>true,
            'reversal_of_id'=>(int)$movement['id'],
            'source_type'=>'order_item_adjustment',
            'source_id'=>$adjustmentId,
            'idempotency_key'=>$key,
            'occurred_at'=>$occurredAt,
            'revalue_balance'=>true,
            'metadata'=>['order_item_id'=>$orderItemId,'previous_quantity'=>$previousQuantity,'new_quantity'=>$newQuantity,'restored_quantity'=>$delta,'adjustment_id'=>$adjustmentId],
            'actor_user_id'=>$actorUserId,
        ]);
        $created++;
    }
    return $created;
}

function inventory_reverse_order_consumption_locked(PDO $pdo, int $orderId, int $actorUserId, string $eventKey, ?string $occurredAt = null): int
{
    $stmt = $pdo->prepare("SELECT m.* FROM inventory_movements m JOIN order_items oi ON oi.id=CAST(m.source_id AS UNSIGNED) WHERE m.movement_type='recipe_consumption' AND m.source_type='order_item' AND oi.order_id=? ORDER BY m.id FOR UPDATE");
    $stmt->execute([$orderId]);
    $created = 0;
    foreach ($stmt->fetchAll() as $movement) {
        $sumStmt = $pdo->prepare('SELECT COALESCE(SUM(quantity_base),0) FROM inventory_movements WHERE reversal_of_id=?');
        $sumStmt->execute([(int)$movement['id']]);
        $alreadyReversed = (int)$sumStmt->fetchColumn();
        $remaining = (int)$movement['quantity_base'] + $alreadyReversed;
        if ($remaining >= 0) continue;
        $qty = -$remaining;
        inventory_record_movement_locked($pdo,[
            'item_id'=>(int)$movement['inventory_item_id'],
            'movement_type'=>'reversal',
            'quantity_base'=>$qty,
            'department'=>$movement['department'] ?: null,
            'unit_cost_snapshot'=>$movement['unit_cost_snapshot'] !== null ? (float)$movement['unit_cost_snapshot'] : null,
            'cost_status'=>(string)$movement['cost_status'],
            'lock_cost_basis'=>true,
            'reversal_of_id'=>(int)$movement['id'],
            'source_type'=>'order_cancel',
            'source_id'=>$orderId,
            'idempotency_key'=>'inventory:order-cancel:' . $eventKey . ':movement:' . (int)$movement['id'],
            'occurred_at'=>$occurredAt,
            'revalue_balance'=>true,
            'metadata'=>['order_id'=>$orderId,'cancel_event'=>$eventKey],
            'actor_user_id'=>$actorUserId,
        ]);
        $created++;
    }
    return $created;
}

function inventory_reaccount_order_consumption_locked(PDO $pdo, int $orderId, int $actorUserId, string $eventKey, ?string $occurredAt = null): int
{
    // Prefer restoring the exact historical recipe snapshot instead of reading today's recipe.
    $stmt = $pdo->prepare("SELECT m.* FROM inventory_movements m JOIN order_items oi ON oi.id=CAST(m.source_id AS UNSIGNED) WHERE m.movement_type='recipe_consumption' AND m.source_type='order_item' AND oi.order_id=? ORDER BY m.id FOR UPDATE");
    $stmt->execute([$orderId]);
    $initial = $stmt->fetchAll();
    if (!$initial) {
        $eventStmt = $pdo->prepare("SELECT payload_json FROM inventory_order_events WHERE order_id=? AND event_type='accounted' ORDER BY id LIMIT 1");
        $eventStmt->execute([$orderId]);
        $payload = json_decode((string)($eventStmt->fetchColumn() ?: ''), true);
        if (is_array($payload) && is_array($payload['recipe_snapshot'] ?? null)) {
            return inventory_apply_order_consumption_locked($pdo,$orderId,$actorUserId,$payload['recipe_snapshot'],$occurredAt);
        }
        return inventory_apply_order_consumption_locked($pdo,$orderId,$actorUserId,null,$occurredAt);
    }
    $created = 0;
    foreach ($initial as $movement) {
        $sumStmt = $pdo->prepare('SELECT COALESCE(SUM(quantity_base),0) FROM inventory_movements WHERE reversal_of_id=?');
        $sumStmt->execute([(int)$movement['id']]);
        $reversed = (int)$sumStmt->fetchColumn();
        $net = (int)$movement['quantity_base'] + $reversed;
        if ($net < 0) continue; // already consumed in full or in part.
        $qty = (int)$movement['quantity_base'];
        inventory_record_movement_locked($pdo,[
            'item_id'=>(int)$movement['inventory_item_id'],
            'movement_type'=>'recipe_consumption',
            'quantity_base'=>$qty,
            'department'=>$movement['department'] ?: null,
            'unit_cost_snapshot'=>$movement['unit_cost_snapshot'] !== null ? (float)$movement['unit_cost_snapshot'] : null,
            'cost_status'=>(string)$movement['cost_status'],
            'lock_cost_basis'=>true,
            'source_type'=>'order_reaccount',
            'source_id'=>$orderId,
            'idempotency_key'=>'inventory:order-reaccount:' . $eventKey . ':movement:' . (int)$movement['id'],
            'occurred_at'=>$occurredAt,
            'metadata'=>['order_id'=>$orderId,'restores_movement_id'=>(int)$movement['id']],
            'actor_user_id'=>$actorUserId,
        ]);
        $created++;
    }
    return $created;
}

function inventory_process_order_event(int $eventId): bool
{
    if (!sokna_module_runtime_ready('inventory')) return false;
    $pdo = db();
    try {
        $pdo->beginTransaction();
        if (!inventory_module_runtime_ready_locked($pdo)) { $pdo->commit(); return false; }
        $stmt = $pdo->prepare('SELECT * FROM inventory_order_events WHERE id=? FOR UPDATE');
        $stmt->execute([$eventId]);
        $event = $stmt->fetch();
        if (!$event) { $pdo->rollBack(); return false; }
        if ((string)$event['status'] === 'done') { $pdo->commit(); return true; }
        if ((string)$event['status'] !== 'pending') { $pdo->commit(); return false; }

        // Events from the same order are a tiny ordered stream. A later adjustment may
        // never overtake the original accounted event or an earlier correction.
        $previous = $pdo->prepare("SELECT id FROM inventory_order_events WHERE order_id=? AND id<? AND status<>'done' ORDER BY id LIMIT 1 FOR UPDATE");
        $previous->execute([(int)$event['order_id'], $eventId]);
        if ($previous->fetchColumn()) { $pdo->commit(); return false; }

        $payload = json_decode((string)($event['payload_json'] ?? ''), true);
        if (!is_array($payload)) $payload = [];
        $actor = (int)($event['actor_user_id'] ?? 0);
        $type = (string)$event['event_type'];
        $effectiveAt = trim((string)($event['created_at'] ?? '')) ?: null;
        if ($type === 'accounted') {
            inventory_apply_order_consumption_locked($pdo,(int)$event['order_id'],$actor,is_array($payload['recipe_snapshot'] ?? null) ? $payload['recipe_snapshot'] : null,$effectiveAt);
        } elseif ($type === 'quantity_adjusted') {
            inventory_adjust_order_item_consumption_locked(
                $pdo,
                (int)($payload['order_item_id'] ?? $event['order_item_id'] ?? 0),
                (int)($payload['previous_quantity'] ?? 0),
                (int)($payload['new_quantity'] ?? 0),
                (int)($payload['adjustment_id'] ?? 0),
                $actor,
                $effectiveAt,
                array_key_exists('restore_quantity',$payload) ? (int)$payload['restore_quantity'] : null
            );
        } elseif ($type === 'cancelled') {
            inventory_reverse_order_consumption_locked($pdo,(int)$event['order_id'],$actor,(string)$event['idempotency_key'],$effectiveAt);
        } elseif ($type === 'reaccounted') {
            inventory_reaccount_order_consumption_locked($pdo,(int)$event['order_id'],$actor,(string)$event['idempotency_key'],$effectiveAt);
        }
        $pdo->prepare("UPDATE inventory_order_events SET status='done',attempt_count=attempt_count+1,last_error=NULL,processed_at=NOW() WHERE id=?")->execute([$eventId]);
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        try {
            $pdo->prepare("UPDATE inventory_order_events SET status=IF(attempt_count>=9,'failed','pending'),attempt_count=attempt_count+1,last_error=? WHERE id=? AND status='pending'")
                ->execute([text_substr($e->getMessage(),0,500),$eventId]);
        } catch (Throwable) {}
        error_log('Inventory event ' . $eventId . ': ' . $e->getMessage());
        return false;
    }
}

/** Process a bounded number of currently eligible events across all orders. */
function inventory_process_pending_order_events(int $limit = 30): array
{
    if (!sokna_module_runtime_ready('inventory')) return ['processed'=>0,'failed'=>0];
    $limit = max(1,min(100,$limit));
    try {
        $sql = "SELECT e.id FROM inventory_order_events e
                WHERE e.status='pending'
                  AND NOT EXISTS (
                    SELECT 1 FROM inventory_order_events p
                    WHERE p.order_id=e.order_id AND p.id<e.id AND p.status<>'done'
                  )
                ORDER BY e.id LIMIT " . $limit;
        $ids = db()->query($sql)->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable) {
        return ['processed'=>0,'failed'=>0];
    }
    $processed = 0; $failed = 0;
    foreach ($ids as $id) inventory_process_order_event((int)$id) ? $processed++ : $failed++;
    return ['processed'=>$processed,'failed'=>$failed];
}

/**
 * Normal small-cafe path: after the order transaction commits, immediately apply the
 * inventory events for that order. The durable outbox remains the recovery mechanism;
 * a daemon/cron worker is optional, not required for ordinary operation.
 */
function inventory_register_after_response_order(int $orderId): void
{
    if (!sokna_module_runtime_ready('inventory')) return;
    if ($orderId < 1) return;
    $GLOBALS['sokna_inventory_after_response_order_ids'] ??= [];
    $GLOBALS['sokna_inventory_after_response_order_ids'][$orderId] = $orderId;
}

function inventory_response_metadata(): array
{
    if (!sokna_module_runtime_ready('inventory')) return [];
    $ids = array_values(array_map('intval', $GLOBALS['sokna_inventory_after_response_order_ids'] ?? []));
    if (!$ids) return [];
    $orderId = (int)end($ids);
    if ($orderId < 1) return [];
    return ['kick'=>['url'=>asset('api/inventory_kick.php'),'order_id'=>$orderId]];
}

function inventory_after_response_drain(): void
{
    if (!sokna_module_runtime_ready('inventory')) return;
    $ids = array_values(array_map('intval', $GLOBALS['sokna_inventory_after_response_order_ids'] ?? []));
    foreach (array_slice($ids, -2) as $orderId) {
        try { inventory_process_order_events_for_order((int)$orderId, 5); }
        catch (Throwable $e) { error_log('inventory after-response drain: ' . $e->getMessage()); }
    }
}

function inventory_process_order_events_for_order(int $orderId, int $limit = 10): array
{
    if (!sokna_module_runtime_ready('inventory')) return ['processed'=>0,'failed'=>0];
    if ($orderId < 1) return ['processed'=>0,'failed'=>0];
    $limit = max(1,min(30,$limit));
    $processed = 0; $failed = 0;
    for ($i=0; $i<$limit; $i++) {
        try {
            $stmt = db()->prepare("SELECT e.id FROM inventory_order_events e
                WHERE e.order_id=? AND e.status='pending'
                  AND NOT EXISTS (
                    SELECT 1 FROM inventory_order_events p
                    WHERE p.order_id=e.order_id AND p.id<e.id AND p.status<>'done'
                  )
                ORDER BY e.id LIMIT 1");
            $stmt->execute([$orderId]);
            $eventId = (int)($stmt->fetchColumn() ?: 0);
        } catch (Throwable) {
            $failed++;
            break;
        }
        if ($eventId < 1) break;
        if (!inventory_process_order_event($eventId)) { $failed++; break; }
        $processed++;
    }
    return ['processed'=>$processed,'failed'=>$failed];
}

function inventory_order_event_backlog(?PDO $pdo = null): array
{
    try {
        $pdo ??= db();
        $row = $pdo->query("SELECT
                SUM(status='pending') pending_count,
                SUM(status='failed') failed_count,
                SUM(status='pending' AND created_at < DATE_SUB(NOW(), INTERVAL 2 MINUTE)) stale_pending_count,
                COUNT(DISTINCT CASE WHEN status='failed' OR (status='pending' AND created_at < DATE_SUB(NOW(), INTERVAL 2 MINUTE)) THEN order_id END) problem_order_count,
                MIN(CASE WHEN status='pending' THEN created_at END) oldest_pending_at
            FROM inventory_order_events")->fetch();
        return [
            'pending'=>(int)($row['pending_count'] ?? 0),
            'failed'=>(int)($row['failed_count'] ?? 0),
            'stale_pending'=>(int)($row['stale_pending_count'] ?? 0),
            'problem_orders'=>(int)($row['problem_order_count'] ?? 0),
            'oldest_pending_at'=>$row['oldest_pending_at'] ?? null,
        ];
    } catch (Throwable) {
        return ['pending'=>0,'failed'=>0,'stale_pending'=>0,'problem_orders'=>0,'oldest_pending_at'=>null];
    }
}

function inventory_retry_failed_order_events(?PDO $pdo = null): int
{
    if (!sokna_module_runtime_ready('inventory')) return 0;
    $pdo ??= db();
    $stmt = $pdo->prepare("UPDATE inventory_order_events SET status='pending',attempt_count=0,last_error=NULL,processed_at=NULL WHERE status='failed'");
    $stmt->execute();
    return $stmt->rowCount();
}

/** Return the single open count session, if any. Start serialization owns the invariant. */
function inventory_open_count_session(PDO $pdo, bool $forUpdate = false): ?array
{
    $sql = "SELECT s.*,
        (SELECT COUNT(*) FROM inventory_count_lines l WHERE l.session_id=s.id) total_lines,
        (SELECT COUNT(*) FROM inventory_count_lines l WHERE l.session_id=s.id AND l.actual_quantity IS NOT NULL) counted_lines
        FROM inventory_count_sessions s
        WHERE s.status='draft'
        ORDER BY s.id
        LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : '');
    return $pdo->query($sql)->fetch() ?: null;
}

function inventory_open_count_message(): string
{
    return 'یک شمارش دیگر هنوز باز است؛ ابتدا همان شمارش را ادامه بده یا لغو کن.';
}

function inventory_count_start(PDO $pdo, string $title, string $sessionType, int $actorUserId, string $scopeType = 'full', ?string $scopeCategoryKey = null): int
{
    if (!in_array($sessionType,['opening','periodic'],true)) $sessionType='periodic';
    if ($sessionType === 'opening') {
        $scopeType = 'full';
        $scopeCategoryKey = null;
    } else {
        $scopeType = in_array($scopeType,['full','category'],true) ? $scopeType : 'full';
        $scopeCategoryKey = $scopeType === 'category' ? text_substr(trim((string)$scopeCategoryKey),0,64) : null;
        if ($scopeType === 'category' && $scopeCategoryKey === '') throw new RuntimeException('دسته شمارش را انتخاب کن.');
    }

    $title = text_substr(trim($title),0,160);
    if ($title === '') $title = $sessionType === 'opening' ? 'موجودی اولیه' : 'شمارش دوره‌ای';

    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) $pdo->beginTransaction();
    try {
        if (!inventory_module_configured_locked($pdo)) throw new RuntimeException('انبار در حال حاضر غیرفعال است.');
        // A real row lock makes the one-open-count rule safe even when two users
        // start a count at nearly the same time. The settings row is only a mutex;
        // the count session/lines remain the business source of truth.
        $pdo->prepare("INSERT INTO settings(setting_key,setting_value) VALUES('inventory_count_start_guard','1') ON DUPLICATE KEY UPDATE setting_value=setting_value")->execute();
        $guard = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key='inventory_count_start_guard' FOR UPDATE");
        $guard->execute();
        $openCount = inventory_open_count_session($pdo,true);
        if ($openCount) throw new RuntimeException(inventory_open_count_message());

        $categoryName = null;
        if ($scopeType === 'category') {
            $category = $pdo->prepare('SELECT category_key,name,active FROM inventory_categories WHERE category_key=? FOR UPDATE');
            $category->execute([$scopeCategoryKey]);
            $categoryRow = $category->fetch();
            if (!$categoryRow || (int)$categoryRow['active'] !== 1) throw new RuntimeException('این دسته برای شروع شمارش قابل استفاده نیست.');
            $categoryName = (string)$categoryRow['name'];
        }

        $pdo->prepare("INSERT INTO inventory_count_sessions(title,session_type,scope_type,scope_category_key,status,snapshot_at,created_by_user_id) VALUES(?,?,?,?,'draft',NOW(),?)")
            ->execute([$title,$sessionType,$scopeType,$scopeCategoryKey,$actorUserId]);
        $sessionId = (int)$pdo->lastInsertId();

        $sql = "SELECT i.id,COALESCE(b.quantity_base,0) quantity_base,b.average_unit_cost
            FROM inventory_items i
            LEFT JOIN inventory_balances b ON b.inventory_item_id=i.id
            WHERE i.active=1";
        $params = [];
        if ($scopeType === 'category') {
            $sql .= ' AND i.category=?';
            $params[] = $scopeCategoryKey;
        }
        $sql .= ' ORDER BY i.category,i.name,i.id';
        $itemsStmt = $pdo->prepare($sql);
        $itemsStmt->execute($params);
        $items = $itemsStmt->fetchAll();
        if (!$items) throw new RuntimeException($scopeType === 'category' ? 'در این دسته کالای فعالی برای شمارش وجود ندارد.' : 'کالای فعالی برای شمارش وجود ندارد.');

        $line = $pdo->prepare('INSERT INTO inventory_count_lines(session_id,inventory_item_id,system_quantity_snapshot,unit_cost_snapshot,actual_quantity,actual_total_cost,difference_base) VALUES(?,?,?,?,?,NULL,NULL)');
        foreach ($items as $item) {
            // Opening is a one-time baseline. Periodic counts deliberately do not keep a
            // session-start stock snapshot: each line captures stock/cost when that item
            // is actually counted, so sales during a long count cannot corrupt final stock.
            $systemSnapshot = $sessionType === 'opening' ? (int)$item['quantity_base'] : 0;
            $unitCostSnapshot = $sessionType === 'opening' && $item['average_unit_cost'] !== null ? (float)$item['average_unit_cost'] : null;
            $line->execute([$sessionId,(int)$item['id'],$systemSnapshot,$unitCostSnapshot,null]);
        }
        audit_log_write_strict($pdo,'inventory.count_started','inventory_count_session',$sessionId,[
            'session_type'=>$sessionType,
            'title'=>$title,
            'scope_type'=>$scopeType,
            'scope_category_key'=>$scopeCategoryKey,
            'scope_category_name'=>$categoryName,
            'item_count'=>count($items),
        ],$actorUserId);
        if ($ownsTransaction) $pdo->commit();
        return $sessionId;
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/**
 * Canonical draft-line mutation for both Local UI and Deferred-safe ingress.
 * Finalization remains a separate Local-only command.
 */
function inventory_count_update_line_locked(
    PDO $pdo,
    int $sessionId,
    int $lineId,
    ?string $actualMajor,
    ?string $openingCostInput,
    string $note,
    int $actorUserId,
    ?string $expectedVersion=null
): array {
    $sessionStmt=$pdo->prepare('SELECT id,status,session_type FROM inventory_count_sessions WHERE id=? FOR UPDATE');
    $sessionStmt->execute([$sessionId]);$session=$sessionStmt->fetch();
    if(!$session||(string)$session['status']!=='draft')throw new RuntimeException('این شمارش دیگر قابل تغییر نیست.');
    $lineStmt=$pdo->prepare('SELECT l.*,i.base_unit FROM inventory_count_lines l JOIN inventory_items i ON i.id=l.inventory_item_id WHERE l.id=? AND l.session_id=? FOR UPDATE');
    $lineStmt->execute([$lineId,$sessionId]);$line=$lineStmt->fetch();
    if(!$line)throw new RuntimeException('قلم شمارش پیدا نشد.');

    $currentVersion=(string)$line['updated_at'];
    if($expectedVersion!==null&&$expectedVersion!==''&&!hash_equals($currentVersion,$expectedVersion)){
        throw new SoknaDeferredStateConflict('این قلم شمارش از زمان ثبت راه‌دور تغییر کرده است.');
    }

    $isOpening=(string)$session['session_type']==='opening';
    $raw=trim((string)$actualMajor);
    $actual=$raw===''?null:inventory_major_to_base($raw,(string)$line['base_unit']);
    $costRaw=trim((string)$openingCostInput);
    $openingCost=$isOpening&&$costRaw!==''?inventory_money_value($costRaw):null;
    $note=text_substr(trim($note),0,500);

    if($actual===null){
        if($isOpening){
            $pdo->prepare('UPDATE inventory_count_lines SET actual_quantity=NULL,actual_total_cost=NULL,note=?,difference_base=NULL,counted_by_user_id=NULL,counted_at=NULL WHERE id=? AND session_id=?')
                ->execute([$note?:null,$lineId,$sessionId]);
        }else{
            $pdo->prepare('UPDATE inventory_count_lines SET system_quantity_snapshot=0,unit_cost_snapshot=NULL,actual_quantity=NULL,actual_total_cost=NULL,note=?,difference_base=NULL,counted_by_user_id=NULL,counted_at=NULL WHERE id=? AND session_id=?')
                ->execute([$note?:null,$lineId,$sessionId]);
        }
    }elseif(!$isOpening&&$line['counted_at']===null){
        $balance=inventory_balance_locked($pdo,(int)$line['inventory_item_id']);
        $pdo->prepare('UPDATE inventory_count_lines SET system_quantity_snapshot=?,unit_cost_snapshot=?,actual_quantity=?,actual_total_cost=NULL,note=?,difference_base=NULL,counted_by_user_id=?,counted_at=NOW() WHERE id=? AND session_id=?')
            ->execute([(int)$balance['quantity_base'],$balance['average_unit_cost']!==null?(float)$balance['average_unit_cost']:null,$actual,$note?:null,$actorUserId,$lineId,$sessionId]);
    }else{
        $pdo->prepare('UPDATE inventory_count_lines SET actual_quantity=?,actual_total_cost=?,note=?,counted_by_user_id=?,counted_at=COALESCE(counted_at,NOW()) WHERE id=? AND session_id=?')
            ->execute([$actual,$openingCost,$note?:null,$actorUserId,$lineId,$sessionId]);
    }
    $fresh=$pdo->prepare('SELECT id,session_id,inventory_item_id,actual_quantity,system_quantity_snapshot,updated_at FROM inventory_count_lines WHERE id=?');
    $fresh->execute([$lineId]);$row=$fresh->fetch()?:[];
    return [
        'line_id'=>$lineId,'session_id'=>$sessionId,'actual_quantity'=>$row['actual_quantity']===null?null:(int)$row['actual_quantity'],
        'system_quantity_snapshot'=>(int)($row['system_quantity_snapshot']??0),'version'=>(string)($row['updated_at']??''),
    ];
}

function inventory_count_cancel_locked(PDO $pdo, int $sessionId, int $actorUserId): void
{
    $stmt = $pdo->prepare('SELECT * FROM inventory_count_sessions WHERE id=? FOR UPDATE');
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch();
    if (!$session) throw new RuntimeException('شمارش پیدا نشد.');
    if ((string)$session['status'] !== 'draft') throw new RuntimeException('فقط شمارش در حال انجام قابل لغو است.');
    $pdo->prepare("UPDATE inventory_count_sessions SET status='cancelled' WHERE id=?")->execute([$sessionId]);
    audit_log_write_strict($pdo,'inventory.count_cancelled','inventory_count_session',$sessionId,[
        'session_type'=>$session['session_type'],
        'title'=>$session['title'],
    ],$actorUserId);
}

function inventory_count_finalize_locked(PDO $pdo, int $sessionId, int $actorUserId): array
{
    if (!inventory_module_configured_locked($pdo)) throw new RuntimeException('انبار در حال حاضر غیرفعال است.');
    $stmt = $pdo->prepare('SELECT * FROM inventory_count_sessions WHERE id=? FOR UPDATE');
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch();
    if (!$session) throw new RuntimeException('شمارش پیدا نشد.');
    if ((string)$session['status'] !== 'draft') throw new RuntimeException('این شمارش قبلاً نهایی شده است.');
    $lines = $pdo->prepare('SELECT l.*,i.base_unit,i.name FROM inventory_count_lines l JOIN inventory_items i ON i.id=l.inventory_item_id WHERE l.session_id=? ORDER BY l.id FOR UPDATE');
    $lines->execute([$sessionId]);
    $rows = $lines->fetchAll();
    foreach ($rows as $row) {
        if ($row['actual_quantity'] === null) {
            $message = (string)$session['session_type'] === 'opening'
                ? 'قبل از نهایی‌سازی، موجودی اولیه را مرور کن تا اقلام خالی به صفر ثبت شوند.'
                : 'همه اقلام این شمارش باید مقدار واقعی داشته باشند؛ برای کالای بدون موجودی عدد صفر ثبت کن.';
            throw new RuntimeException($message);
        }
    }
    $movements = 0; $differenceCount = 0;
    $updateDifference = $pdo->prepare('UPDATE inventory_count_lines SET difference_base=? WHERE id=?');
    foreach ($rows as $row) {
        $actual = max(0,(int)$row['actual_quantity']);
        $system = (int)$row['system_quantity_snapshot'];
        $difference = $actual - $system;
        $updateDifference->execute([$difference,(int)$row['id']]);
        if ($difference === 0) continue;
        $differenceCount++;
        $type = (string)$session['session_type'] === 'opening' ? 'opening_balance' : 'count_adjustment';
        $openingCost = (string)$session['session_type'] === 'opening' && $row['actual_total_cost'] !== null ? max(0,(int)$row['actual_total_cost']) : null;
        inventory_record_movement_locked($pdo,[
            'item_id'=>(int)$row['inventory_item_id'],
            'movement_type'=>$type,
            'quantity_base'=>$difference,
            'unit_cost_snapshot'=>$openingCost === null && $row['unit_cost_snapshot']!==null?(float)$row['unit_cost_snapshot']:null,
            'total_cost_delta'=>$openingCost,
            'cost_status'=>$openingCost !== null ? 'known' : ($row['unit_cost_snapshot']!==null?'estimated':'unknown'),
            'source_type'=>'inventory_count_session',
            'source_id'=>$sessionId,
            'idempotency_key'=>'inventory:count:' . $sessionId . ':line:' . (int)$row['id'],
            'metadata'=>['count_line_id'=>(int)$row['id'],'system_quantity_snapshot'=>$system,'actual_quantity'=>$actual,'opening_total_cost'=>$openingCost],
            'actor_user_id'=>$actorUserId,
        ]);
        $movements++;
    }
    $pdo->prepare("UPDATE inventory_count_sessions SET status='finalized',finalized_by_user_id=?,finalized_at=NOW() WHERE id=?")
        ->execute([$actorUserId,$sessionId]);
    if ((string)$session['session_type'] === 'opening') {
        $pdo->prepare("INSERT INTO settings(setting_key,setting_value) VALUES('inventory_initialized','1') ON DUPLICATE KEY UPDATE setting_value='1'")->execute();
        $pdo->prepare("INSERT INTO settings(setting_key,setting_value) VALUES('inventory_reconciliation_required','0') ON DUPLICATE KEY UPDATE setting_value='0'")->execute();
    } elseif ((string)($session['scope_type'] ?? 'full') === 'full' && inventory_reconciliation_required_locked($pdo)) {
        $pdo->prepare("INSERT INTO settings(setting_key,setting_value) VALUES('inventory_reconciliation_required','0') ON DUPLICATE KEY UPDATE setting_value='0'")->execute();
        audit_log_write_strict($pdo,'inventory.reconciliation_completed','inventory_count_session',$sessionId,[
            'session_type'=>$session['session_type'],
            'scope_type'=>'full',
        ],$actorUserId);
    }
    audit_log_write_strict($pdo,'inventory.count_finalized','inventory_count_session',$sessionId,['session_type'=>$session['session_type'],'movement_count'=>$movements,'difference_count'=>$differenceCount],$actorUserId);
    return ['movements'=>$movements,'differences'=>$differenceCount];
}

function inventory_initialized(): bool
{
    return setting_bool('inventory_initialized', false);
}

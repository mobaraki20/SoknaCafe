<?php
declare(strict_types=1);

function print_worker_component_metadata(): array
{
    static $metadata = null;
    if (is_array($metadata)) return $metadata;
    $file = dirname(__DIR__) . '/runtime/print-worker/source/PROVENANCE.json';
    $version = '6.2.5';
    $sourceSha256 = '';
    if (is_file($file)) {
        $decoded = json_decode((string)@file_get_contents($file), true);
        if (is_array($decoded)) {
            $candidate = trim((string)($decoded['upstream_version'] ?? ''));
            if (preg_match('/^\d+\.\d+\.\d+$/', $candidate)) $version = $candidate;
            $sourceSha256 = trim((string)($decoded['upstream_archive_sha256'] ?? ''));
        }
    }
    return $metadata = [
        'version' => $version,
        'source_sha256' => $sourceSha256,
        'ownership' => 'sokna-local-internal',
    ];
}

/** Protocol-v4 compatibility alias. The binary is now an internal SOKNA component. */
function print_agent_recommended_version(): string
{
    return (string)print_worker_component_metadata()['version'];
}

function print_agent_minimum_version(): string
{
    return (string)print_worker_component_metadata()['version'];
}

function print_internal_worker_agent_id(PDO $pdo, bool $forUpdate = false): int
{
    $sql = "SELECT setting_value FROM settings WHERE setting_key='print_internal_worker_agent_id' LIMIT 1";
    if ($forUpdate) $sql .= ' FOR UPDATE';
    $value = trim((string)($pdo->query($sql)->fetchColumn() ?: ''));
    return ctype_digit($value) ? (int)$value : 0;
}

function print_internal_worker_agent(PDO $pdo, bool $forUpdate = false): ?array
{
    $agentId = print_internal_worker_agent_id($pdo, $forUpdate);
    if ($agentId < 1) return null;
    $sql = 'SELECT * FROM print_agents WHERE id=? AND active=1 AND retired_at IS NULL';
    if ($forUpdate) $sql .= ' FOR UPDATE';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$agentId]);
    return $stmt->fetch() ?: null;
}

function print_agent_needs_update(array $agent): bool
{
    $version = trim((string)($agent['agent_version'] ?? ''));
    return $version !== '' && version_compare($version, print_agent_recommended_version(), '<');
}

function print_database_time_to_utc(?string $databaseTime): ?string
{
    $databaseTime=trim((string)$databaseTime);if($databaseTime==='')return null;
    $zone=new DateTimeZone(date_default_timezone_get());
    $dt=DateTimeImmutable::createFromFormat('Y-m-d H:i:s',$databaseTime,$zone);
    if(!$dt){try{$dt=new DateTimeImmutable($databaseTime,$zone);}catch(Throwable){return null;}}
    return $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z');
}

/** Built-in destinations are defaults, not a closed allow-list. */
function print_destination_definitions(): array
{
    return [
        'customer_receipt' => ['label' => 'سند مشتری', 'type' => 'customer', 'default_queue' => ''],
        'prep_shared' => ['label' => 'آماده‌سازی مشترک', 'type' => 'preparation', 'default_queue' => ''],
    ];
}

function print_job_error_human(string $code, string $raw=''): string
{
    return match($code){
        'reservation_retry_exhausted'=>'سرویس چاپ داخلی یا پرینتر چند بار درخواست را نپذیرفت؛ اتصال و صف چاپ را بررسی کنید.',
        'destination_inactive'=>'مقصد چاپ غیرفعال است و درخواست تا رفع تنظیمات نگه داشته شده است.',
        'destination_unmapped'=>'برای این مقصد، پرینتر مشخص نشده است.',
        'agent_disabled'=>'سرویس چاپ داخلی این مقصد آماده نیست.',
        'preparation_area_unmapped'=>'بخش آماده‌سازی به مقصد چاپ مشخصی متصل نیست.',
        'recovery_hold'=>'درخواست چاپ روی سرویس چاپ ثبت شده، اما ادامه خودکار ممکن است چاپ تکراری ایجاد کند.',
        'reservation_expired'=>'سرویس چاپ درخواست را در زمان مقرر نپذیرفت.',
        'operator_unknown'=>'نتیجه چاپ مشخص نیست؛ وضعیت پرینتر را بررسی کنید.',
        'cancelled_by_admin'=>'درخواست توسط مدیر لغو شده است.',
        default=>trim($raw)!==''?'چاپ انجام نشد؛ جزئیات فنی را بررسی کنید.':'چاپ انجام نشد.',
    };
}

function print_job_status_labels(): array
{
    return [
        'pending' => 'در صف چاپ',
        'blocked' => 'نیازمند تنظیم',
        'reserved' => 'در انتظار پذیرش سرویس چاپ',
        'claimed' => 'تحویل به سرویس چاپ',
        'submitted' => 'ارسال به چاپگر انجام شد',
        'failed' => 'ناموفق',
        'unknown' => 'نتیجه نامشخص',
        'recovery_hold' => 'نیازمند بازیابی',
        'cancelled' => 'لغوشده',
    ];
}

function print_tables_available(?PDO $pdo = null): bool
{
    static $cached = null;
    if ($cached !== null) return $cached;
    try {
        $pdo ??= db();
        $stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('print_agents','print_destinations','print_jobs','print_attempts','print_claim_requests')");
        return $cached = ((int)$stmt->fetchColumn() === 5);
    } catch (Throwable) {
        return $cached = false;
    }
}

function print_templates_available(?PDO $pdo = null): bool
{
    static $cached = null;
    if ($cached !== null) return $cached;
    try {
        $pdo ??= db();
        $stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='print_templates'");
        return $cached = ((int)$stmt->fetchColumn() === 1);
    } catch (Throwable) {
        return $cached = false;
    }
}

function print_destination(PDO $pdo, string $key, bool $forUpdate = false): ?array
{
    $sql = "SELECT d.*,pa.name agent_name,pa.last_seen_at agent_last_seen_at,pa.active agent_active,
                   fa.name fallback_agent_name,fa.last_seen_at fallback_agent_last_seen_at,fa.active fallback_agent_active
            FROM print_destinations d
            LEFT JOIN print_agents pa ON pa.id=d.agent_id
            LEFT JOIN print_agents fa ON fa.id=d.fallback_agent_id
            WHERE d.destination_key=?";
    if ($forUpdate) $sql .= ' FOR UPDATE';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$key]);
    return $stmt->fetch() ?: null;
}

function print_destination_ready(PDO $pdo, string $key): bool
{
    // A destination is operational only when the canonical runtime-readiness
    // resolver can select a ready Primary/Fallback route.  Do not create a
    // second, mapping-only notion of readiness here.
    return print_destination_operational_route($pdo, $key) !== null;
}

function print_destination_block_reason(array $destination): ?string
{
    if ((int)($destination['active'] ?? 0) !== 1) return 'destination_inactive';
    $primaryMapped = !empty($destination['agent_id']) && trim((string)($destination['windows_queue_name'] ?? '')) !== '';
    $fallbackMapped = !empty($destination['fallback_agent_id']) && trim((string)($destination['fallback_windows_queue_name'] ?? '')) !== '';
    if ($primaryMapped && (int)($destination['agent_active'] ?? 0) === 1) return null;
    if ($fallbackMapped && (int)($destination['fallback_agent_active'] ?? 0) === 1) return null;
    if (!$primaryMapped && !$fallbackMapped) return 'destination_unmapped';
    return 'agent_disabled';
}

function print_agent_runtime_readiness(array $agent,int $heartbeatMaxAgeSeconds=45,int $discoveryMaxAgeSeconds=90): array
{
    if ((int)($agent['active'] ?? 0) !== 1) return ['eligible'=>false,'code'=>'inactive','message'=>'سرویس چاپ داخلی غیرفعال است','heartbeat_age_seconds'=>null,'discovery_age_seconds'=>null,'printer_discovery_at'=>null];
    if (!empty($agent['retired_at'])) return ['eligible'=>false,'code'=>'retired','message'=>'هویت سرویس چاپ داخلی بازنشسته شده است','heartbeat_age_seconds'=>null,'discovery_age_seconds'=>null,'printer_discovery_at'=>null];
    $heartbeatRaw=trim((string)($agent['last_heartbeat_at']??''));
    if($heartbeatRaw==='')return ['eligible'=>false,'code'=>'heartbeat_missing','message'=>'از سرویس چاپ داخلی هنوز Heartbeat معتبر دریافت نشده است','heartbeat_age_seconds'=>null,'discovery_age_seconds'=>null,'printer_discovery_at'=>null];
    $heartbeatTs=strtotime($heartbeatRaw);$heartbeatAge=$heartbeatTs===false?null:max(0,time()-$heartbeatTs);
    if($heartbeatAge===null||$heartbeatAge>$heartbeatMaxAgeSeconds)return ['eligible'=>false,'code'=>'heartbeat_stale','message'=>'آخرین Heartbeat سرویس چاپ داخلی تازه نیست','heartbeat_age_seconds'=>$heartbeatAge,'discovery_age_seconds'=>null,'printer_discovery_at'=>null];
    $health=json_decode((string)($agent['health_json']??'{}'),true);if(!is_array($health))$health=[];
    $discoveryRaw=trim((string)($health['printer_discovery_at']??''));
    if($discoveryRaw==='')return ['eligible'=>false,'code'=>'discovery_missing','message'=>'فهرست پرینترهای Windows هنوز دریافت نشده است','heartbeat_age_seconds'=>$heartbeatAge,'discovery_age_seconds'=>null,'printer_discovery_at'=>null];
    $discoveryTs=strtotime($discoveryRaw);$discoveryAge=$discoveryTs===false?null:max(0,time()-$discoveryTs);
    if($discoveryAge===null||$discoveryAge>$discoveryMaxAgeSeconds)return ['eligible'=>false,'code'=>'discovery_stale','message'=>'فهرست پرینترهای Windows تازه نیست','heartbeat_age_seconds'=>$heartbeatAge,'discovery_age_seconds'=>$discoveryAge,'printer_discovery_at'=>$discoveryRaw];
    $printers=json_decode((string)($agent['printers_json']??'[]'),true);
    if(!is_array($printers)||!array_is_list($printers))return ['eligible'=>false,'code'=>'inventory_invalid','message'=>'فهرست پرینترهای Windows معتبر نیست','heartbeat_age_seconds'=>$heartbeatAge,'discovery_age_seconds'=>$discoveryAge,'printer_discovery_at'=>$discoveryRaw];
    return ['eligible'=>true,'code'=>'ok','message'=>'سرویس چاپ و فهرست پرینترها تازه هستند','heartbeat_age_seconds'=>$heartbeatAge,'discovery_age_seconds'=>$discoveryAge,'printer_discovery_at'=>$discoveryRaw];
}

function print_agent_queue_readiness(array $agent, string $queueName, int $maxAgeSeconds = 45): array
{
    $queueName=trim($queueName);
    if($queueName==='')return ['known'=>false,'ready'=>false,'reason'=>'queue_unmapped','label'=>'پرینتر انتخاب نشده','printer_discovery_at'=>null];
    $runtime=print_agent_runtime_readiness($agent,$maxAgeSeconds,max(90,$maxAgeSeconds*2));
    if(!$runtime['eligible'])return ['known'=>false,'ready'=>false,'reason'=>$runtime['code'],'label'=>$runtime['message'],'printer_discovery_at'=>$runtime['printer_discovery_at']];
    $printers=json_decode((string)($agent['printers_json']??'[]'),true);
    foreach($printers as $printer){
        if(!is_array($printer)||strcasecmp(trim((string)($printer['name']??'')),$queueName)!==0)continue;
        if(bool_from_mixed($printer['paper_out']??false))return ['known'=>true,'ready'=>false,'reason'=>'queue_paper_out','label'=>'کاغذ تمام شده','printer_discovery_at'=>$runtime['printer_discovery_at']];
        if(bool_from_mixed($printer['paused']??false))return ['known'=>true,'ready'=>false,'reason'=>'queue_paused','label'=>'صف چاپ متوقف است','printer_discovery_at'=>$runtime['printer_discovery_at']];
        if(bool_from_mixed($printer['offline']??false))return ['known'=>true,'ready'=>false,'reason'=>'queue_offline','label'=>'صف چاپ آفلاین است','printer_discovery_at'=>$runtime['printer_discovery_at']];
        if(bool_from_mixed($printer['error']??false))return ['known'=>true,'ready'=>false,'reason'=>'queue_error','label'=>'صف چاپ خطا دارد','printer_discovery_at'=>$runtime['printer_discovery_at']];
        return ['known'=>true,'ready'=>true,'reason'=>'ok','label'=>'آماده','printer_discovery_at'=>$runtime['printer_discovery_at']];
    }
    return ['known'=>false,'ready'=>false,'reason'=>'queue_not_found','label'=>'پرینتر در Windows پیدا نشد','printer_discovery_at'=>$runtime['printer_discovery_at']];
}

function print_agent_queue_ready(array $agent, string $queueName, int $maxAgeSeconds = 45): bool
{
    return (bool)print_agent_queue_readiness($agent, $queueName, $maxAgeSeconds)['ready'];
}

/** @return array{role:string,agent_id:int,agent_name:string,windows_queue_name:string}|null */
function print_destination_operational_route(PDO $pdo, string $key): ?array
{
    $stmt = $pdo->prepare("SELECT d.*,pa.name primary_name,pa.active primary_active,pa.retired_at primary_retired_at,pa.last_seen_at primary_last_seen_at,pa.last_heartbeat_at primary_last_heartbeat_at,pa.health_json primary_health_json,pa.printers_json primary_printers_json,fa.name fallback_name,fa.active fallback_active,fa.retired_at fallback_retired_at,fa.last_seen_at fallback_last_seen_at,fa.last_heartbeat_at fallback_last_heartbeat_at,fa.health_json fallback_health_json,fa.printers_json fallback_printers_json FROM print_destinations d LEFT JOIN print_agents pa ON pa.id=d.agent_id LEFT JOIN print_agents fa ON fa.id=d.fallback_agent_id WHERE d.destination_key=? LIMIT 1");
    $stmt->execute([$key]);
    $destination = $stmt->fetch();
    if (!$destination || (int)($destination['active'] ?? 0) !== 1) return null;
    $routes = [
        ['role'=>'primary','agent_id'=>(int)($destination['agent_id'] ?? 0),'agent_name'=>(string)($destination['primary_name'] ?? ''),'windows_queue_name'=>(string)($destination['windows_queue_name'] ?? ''),'agent'=>['active'=>(int)($destination['primary_active'] ?? 0),'retired_at'=>$destination['primary_retired_at']??null,'last_seen_at'=>$destination['primary_last_seen_at']??null,'last_heartbeat_at'=>$destination['primary_last_heartbeat_at']??null,'health_json'=>$destination['primary_health_json']??'{}','printers_json'=>$destination['primary_printers_json']??'[]']],
        ['role'=>'fallback','agent_id'=>(int)($destination['fallback_agent_id'] ?? 0),'agent_name'=>(string)($destination['fallback_name'] ?? ''),'windows_queue_name'=>(string)($destination['fallback_windows_queue_name'] ?? ''),'agent'=>['active'=>(int)($destination['fallback_active'] ?? 0),'retired_at'=>$destination['fallback_retired_at']??null,'last_seen_at'=>$destination['fallback_last_seen_at']??null,'last_heartbeat_at'=>$destination['fallback_last_heartbeat_at']??null,'health_json'=>$destination['fallback_health_json']??'{}','printers_json'=>$destination['fallback_printers_json']??'[]']],
    ];
    foreach ($routes as $route) {
        if ($route['agent_id'] < 1 || trim($route['windows_queue_name']) === '') continue;
        if (print_agent_queue_ready($route['agent'], $route['windows_queue_name'])) {
            unset($route['agent']);
            return $route;
        }
    }
    return null;
}

function print_default_preparation_destination(PDO $pdo): ?array
{
    $stmt = $pdo->query("SELECT d.*,pa.active agent_active FROM print_destinations d LEFT JOIN print_agents pa ON pa.id=d.agent_id WHERE d.destination_type='preparation' ORDER BY (d.destination_key='prep_shared') DESC,d.active DESC,d.destination_key LIMIT 1");
    return $stmt->fetch() ?: null;
}

function print_content_sha256(string $payloadJson): string
{
    return hash('sha256', $payloadJson);
}

function print_any_preparation_destination_ready(PDO $pdo): bool
{
    if (!print_tables_available($pdo)) return false;
    $stmt = $pdo->query("SELECT d.destination_key FROM print_destinations d WHERE d.destination_type='preparation' ORDER BY d.active DESC,d.destination_key");
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $key) {
        if (print_destination_ready($pdo, (string)$key)) return true;
    }
    return false;
}

function print_destination_areas(array $destination): array
{
    if ((string)($destination['destination_type'] ?? '') !== 'preparation') return [];
    $areas = json_decode((string)($destination['preparation_areas_json'] ?? '[]'), true);
    if (!is_array($areas)) return [];
    $valid = [];
    foreach ($areas as $area) {
        $area = normalize_preparation_area((string)$area);
        $valid[$area] = true;
    }
    return array_keys($valid);
}

/** @return array<string,array> area => destination */
function print_preparation_area_destinations(PDO $pdo, bool $forUpdate = false): array
{
    $sql = "SELECT d.*,pa.active agent_active,fa.active fallback_agent_active
            FROM print_destinations d
            LEFT JOIN print_agents pa ON pa.id=d.agent_id
            LEFT JOIN print_agents fa ON fa.id=d.fallback_agent_id
            WHERE d.destination_type='preparation' ORDER BY d.active DESC,d.destination_key";
    if ($forUpdate) $sql .= ' FOR UPDATE';
    $rows = $pdo->query($sql)->fetchAll();
    $map = [];
    foreach ($rows as $row) {
        foreach (print_destination_areas($row) as $area) {
            if (!isset($map[$area])) $map[$area] = $row;
        }
    }
    return $map;
}

function print_public_token(): string
{
    return strtoupper(bin2hex(random_bytes(16)));
}

function print_template_design_defaults(string $templateKey): array
{
    $isPrep = $templateKey === 'preparation';
    return [
        'format' => 'sokna-print-design-v2',
        'layout_contract' => $isPrep ? 'preparation-ticket-v2' : 'customer-receipt-v2',
        'density' => 'compact',
        'font_stack' => 'Vazirmatn, Tahoma, "Segoe UI", sans-serif',
        'header_alignment' => 'center',
        'separator_style' => 'solid',
        'item_layout' => $isPrep ? 'quantity-first' : 'columnar',
        'section_order' => $isPrep
            ? ['status','meta','items','notes','footer']
            : ['brand','meta','items','summary','settlement','footer'],
        'labels' => $isPrep ? [
            'ticket_title'=>'فیش آماده‌سازی',
            'new_order'=>'سفارش جدید',
            'reprint'=>'چاپ مجدد',
            'adjustment'=>'اصلاح سفارش',
            'cancel'=>'لغو سفارش',
            'note'=>'یادداشت',
            'takeaway'=>'بیرون‌بر',
        ] : [
            'invoice'=>'فاکتور',
            'prebill'=>'صورتحساب',
            'items'=>'اقلام',
            'subtotal'=>'جمع اقلام',
            'discount'=>'تخفیف',
            'taxable'=>'مبلغ مشمول مالیات',
            'tax'=>'مالیات',
            'total'=>'جمع نهایی',
            'settlement'=>'نحوه ثبت',
        ],
    ];
}

function print_template_defaults(string $templateKey): array
{
    $isPrep = $templateKey === 'preparation';
    return [
        // Stable renderer payload contract; package_format/design carry the richer Template v2 metadata.
        'format' => 'sokna-print-template-v1',
        'package_format' => 'sokna-print-template-package-v2',
        'template_key' => $isPrep ? 'preparation' : 'customer',
        'layout_contract' => $isPrep ? 'preparation-compact-v1' : 'customer-receipt-v1',
        'name' => $isPrep ? 'قالب خوانای آماده‌سازی Sokna' : 'قالب خوانای سند مشتری Sokna',
        'version' => 'builtin-v2-2',
        'paper_width_mm' => 80,
        'base_font_size' => $isPrep ? 28 : 23,
        'title_font_size' => $isPrep ? 38 : 30,
        'table_font_size' => $isPrep ? 44 : 28,
        'line_spacing' => $isPrep ? 6 : 5,
        'margin' => $isPrep ? 10 : 9,
        'show_actor' => false,
        'show_time' => true,
        'show_order_number' => $isPrep,
        'show_section_titles' => true,
        'show_prices' => !$isPrep,
        'footer' => $isPrep ? '' : 'از همراهی شما سپاسگزاریم.',
        'design' => print_template_design_defaults($templateKey),
    ];
}

function print_template_validate_design(array $design, string $key): array
{
    $defaults = print_template_design_defaults($key);
    $clean = $defaults;
    $density = trim((string)($design['density'] ?? $defaults['density']));
    $clean['density'] = in_array($density,['compact','comfortable'],true) ? $density : 'compact';
    $align = trim((string)($design['header_alignment'] ?? $defaults['header_alignment']));
    $clean['header_alignment'] = in_array($align,['right','center'],true) ? $align : 'center';
    $separator = trim((string)($design['separator_style'] ?? $defaults['separator_style']));
    $clean['separator_style'] = in_array($separator,['solid','dashed','minimal'],true) ? $separator : 'solid';
    $layout = trim((string)($design['item_layout'] ?? $defaults['item_layout']));
    if ($key === 'preparation' && $layout === 'compact-list') $layout = 'quantity-first';
    $allowedLayouts = $key === 'preparation' ? ['quantity-first'] : ['columnar','columnar-compact','two-line','responsive-receipt'];
    $clean['item_layout'] = in_array($layout,$allowedLayouts,true) ? $layout : $defaults['item_layout'];
    // Agent 6 renderer and browser preview both prefer the machine-wide font stack in this order.
    $clean['font_stack'] = 'Vazirmatn, Tahoma, "Segoe UI", sans-serif';
    $allowedSections = $defaults['section_order'];
    $sections = array_values(array_unique(array_filter(array_map('strval',(array)($design['section_order'] ?? $allowedSections)),static fn(string $v): bool=>$v!=='')));
    if (count($sections) !== count($allowedSections) || array_diff($sections,$allowedSections) || array_diff($allowedSections,$sections)) $sections = $allowedSections;
    $clean['section_order'] = $sections;
    $labels = (array)($design['labels'] ?? []);
    foreach ($defaults['labels'] as $labelKey=>$defaultValue) {
        $value = text_substr(trim((string)($labels[$labelKey] ?? $defaultValue)),0,80);
        $clean['labels'][$labelKey] = $value !== '' ? $value : $defaultValue;
    }
    return $clean;
}

function print_template_validate(array $definition, ?string $expectedKey = null): array
{
    $format = trim((string)($definition['format'] ?? ''));
    if (!in_array($format,['sokna-print-template-v1','sokna-print-template-v2'],true)) throw new RuntimeException('فرمت بسته قالب پشتیبانی نمی‌شود.');
    $key = trim((string)($definition['template_key'] ?? ''));
    if (!in_array($key, ['preparation','customer'], true)) throw new RuntimeException('نوع قالب معتبر نیست.');
    if ($expectedKey !== null && $key !== $expectedKey) throw new RuntimeException('نوع قالب با بخش انتخاب‌شده هماهنگ نیست.');

    $defaults = print_template_defaults($key);
    $clean = $defaults;
    $expectedContract = $key === 'preparation' ? 'preparation-compact-v1' : 'customer-receipt-v1';
    $contract = trim((string)($definition['layout_contract'] ?? $expectedContract));
    if (!in_array($contract,[$expectedContract,$key === 'preparation' ? 'preparation-ticket-v2' : 'customer-receipt-v2'],true)) throw new RuntimeException('ساختار قالب با قرارداد این نوع سند هماهنگ نیست.');
    // Keep the stable renderer payload format while Template v2 metadata stays versioned separately.
    $clean['format'] = 'sokna-print-template-v1';
    $clean['package_format'] = 'sokna-print-template-package-v2';
    $clean['layout_contract'] = $expectedContract;
    $clean['name'] = text_substr(trim((string)($definition['name'] ?? $defaults['name'])), 0, 160);
    $clean['version'] = text_substr(trim((string)($definition['version'] ?? ('custom-' . date('YmdHis')))), 0, 40);
    $sourceVersion = text_substr(trim((string)($definition['source_version'] ?? '')), 0, 80);
    if ($sourceVersion !== '') $clean['source_version'] = $sourceVersion;
    if ($clean['name'] === '' || $clean['version'] === '') throw new RuntimeException('نام و نسخه قالب لازم است.');
    $paper = (int)($definition['paper_width_mm'] ?? 80);
    if (!in_array($paper, [58,80], true)) throw new RuntimeException('عرض قالب فقط ۵۸ یا ۸۰ میلی‌متر است.');
    $clean['paper_width_mm'] = $paper;
    $clean['base_font_size'] = max(18, min(42, (int)($definition['base_font_size'] ?? $defaults['base_font_size'])));
    $clean['title_font_size'] = max(22, min(60, (int)($definition['title_font_size'] ?? $defaults['title_font_size'])));
    $clean['table_font_size'] = max(24, min(72, (int)($definition['table_font_size'] ?? $defaults['table_font_size'])));
    $clean['line_spacing'] = max(2, min(20, (int)($definition['line_spacing'] ?? $defaults['line_spacing'])));
    $clean['margin'] = max(4, min(40, (int)($definition['margin'] ?? $defaults['margin'])));
    foreach (['show_actor','show_time','show_order_number','show_section_titles'] as $flag) $clean[$flag] = bool_from_mixed($definition[$flag] ?? $defaults[$flag]);
    // Prices on preparation tickets are deliberately forbidden; Agent payloads must keep preparation operational, not financial.
    $clean['show_prices'] = $key === 'customer' && bool_from_mixed($definition['show_prices'] ?? true);
    $footer = trim((string)($definition['footer'] ?? $defaults['footer']));
    if (preg_match('~https?://|javascript:|<script|<\?php~iu', $footer)) throw new RuntimeException('قالب نمی‌تواند کد یا نشانی خارجی داشته باشد.');
    $clean['footer'] = text_substr($footer, 0, 300);
    $clean['design'] = print_template_validate_design(is_array($definition['design'] ?? null) ? $definition['design'] : [],$key);
    return $clean;
}

function print_template_package_load(string $path, string $expectedKey): array
{
    if (!class_exists('ZipArchive')) throw new RuntimeException('پشتیبانی ZIP روی سرور فعال نیست؛ قالب JSON را وارد کنید.');
    $archiveSize=@filesize($path);
    if($archiveSize===false||$archiveSize<1||$archiveSize>1048576)throw new RuntimeException('حجم بسته قالب معتبر نیست.');
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) throw new RuntimeException('بسته قالب باز نشد.');
    try {
        $allowed = ['manifest.json','template.xml','styles.json','sample-data.json'];
        if($zip->numFiles!==count($allowed))throw new RuntimeException('بسته قالب باید دقیقاً شامل چهار فایل استاندارد Sokna باشد.');
        $files = [];$totalUncompressed=0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = str_replace('\\','/',trim((string)$zip->getNameIndex($i),'/'));
            if ($name === '' || str_contains($name,'..') || !in_array($name,$allowed,true) || array_key_exists($name,$files)) throw new RuntimeException('بسته قالب فقط باید شامل چهار فایل استاندارد یکتا Sokna باشد.');
            $stat=$zip->statIndex($i);
            if(!is_array($stat))throw new RuntimeException('اطلاعات یکی از فایل‌های بسته قالب قابل بررسی نیست.');
            $size=(int)($stat['size']??-1);$compressed=(int)($stat['comp_size']??-1);
            if($size<0||$size>262144||$compressed<0)throw new RuntimeException('اندازه فایل داخل بسته قالب بیش از حد مجاز است.');
            $totalUncompressed+=$size;if($totalUncompressed>786432)throw new RuntimeException('حجم بازشده بسته قالب بیش از حد مجاز است.');
            // Bound expansion before decompression. A generous ratio still rejects classic ZIP bombs.
            if($size>65536&&$compressed>0&&($size/$compressed)>100)throw new RuntimeException('نسبت فشرده‌سازی بسته قالب غیرعادی است.');
            $content = $zip->getFromIndex($i);
            if (!is_string($content) || strlen($content)!==$size || strlen($content) > 262144) throw new RuntimeException('یکی از فایل‌های بسته قالب معتبر نیست.');
            $files[$name] = $content;
        }
        foreach ($allowed as $required) if (!array_key_exists($required,$files)) throw new RuntimeException('فایل '.$required.' در بسته قالب وجود ندارد.');
        $manifest = json_decode($files['manifest.json'],true,64,JSON_THROW_ON_ERROR);
        $styles = json_decode($files['styles.json'],true,64,JSON_THROW_ON_ERROR);
        $sample = json_decode($files['sample-data.json'],true,64,JSON_THROW_ON_ERROR);
        if (!is_array($manifest) || !is_array($styles) || !is_array($sample)) throw new RuntimeException('ساختار JSON بسته قالب معتبر نیست.');
        if (!in_array(($manifest['format'] ?? ''),['sokna-print-template-package-v1','sokna-print-template-package-v2'],true)) throw new RuntimeException('فرمت بسته قالب پشتیبانی نمی‌شود.');
        if (($manifest['template_key'] ?? '') !== $expectedKey) throw new RuntimeException('نوع بسته با بخش انتخاب‌شده هماهنگ نیست.');
        $v1Contract = $expectedKey === 'preparation' ? 'preparation-compact-v1' : 'customer-receipt-v1';
        $v2Contract = $expectedKey === 'preparation' ? 'preparation-ticket-v2' : 'customer-receipt-v2';
        $xml = trim($files['template.xml']);
        if (!preg_match('~^<sokna-print-template\s+layout="(?:'.preg_quote($v1Contract,'~').'|'.preg_quote($v2Contract,'~').')"\s*/>$~u',$xml)) throw new RuntimeException('قرارداد ساختاری template.xml معتبر نیست.');
        $definition = $styles + [
            'format'=>'sokna-print-template-v2',
            'package_format'=>'sokna-print-template-package-v2',
            'template_key'=>$expectedKey,
            'layout_contract'=>$v2Contract,
            'name'=>(string)($manifest['name'] ?? ''),
            'version'=>(string)($manifest['version'] ?? ''),
            'paper_width_mm'=>$manifest['paper_width_mm'] ?? ($styles['paper_width_mm'] ?? 80),
        ];
        return print_template_validate($definition,$expectedKey);
    } finally {
        $zip->close();
    }
}

function print_template_sample_data(string $templateKey, string $scenario = 'default'): array
{
    if ($templateKey === 'preparation') {
        if ($scenario === 'adjustment') return [
            'document_kind'=>'preparation','title'=>'کافه سکنا','badge'=>'اصلاحیه آماده‌سازی','status_label'=>'اصلاح تعداد','table_name'=>'میز ۸','order_number'=>'۱۲۷','display_date'=>'۱۶ مرداد · ۱۴:۳۲','source_label'=>'ثبت کارکنان','actor_name'=>'مدیر کافه','customer_note'=>'بدون پیاز','sections'=>[['title'=>'آشپزخانه','items'=>[['name'=>'پاستا چیکن آلفردو','quantity'=>1,'previous_quantity'=>2,'note'=>'یک عدد کم شد','fulfillment_mode'=>'dine_in']]]]
        ];
        if ($scenario === 'cancel') return [
            'document_kind'=>'preparation','title'=>'کافه سکنا','badge'=>'لغو سفارش آماده‌سازی','status_label'=>'لغو کامل سفارش','table_name'=>'میز ۸','order_number'=>'۱۲۷','display_date'=>'۱۶ مرداد · ۱۴:۳۴','source_label'=>'ثبت کارکنان','actor_name'=>'مدیر کافه','customer_note'=>'این سفارش لغو شده است.','sections'=>[['title'=>'آشپزخانه','items'=>[['name'=>'پاستا چیکن آلفردو','quantity'=>2,'note'=>'','fulfillment_mode'=>'takeaway']]]]
        ];
        return [
            'document_kind'=>'preparation','title'=>'کافه سکنا','badge'=>'فیش آماده‌سازی — آشپزخانه','status_label'=>'سفارش جدید','table_name'=>'میز ۸','order_number'=>'۱۲۷','display_date'=>'۱۶ مرداد · ۱۴:۳۰','source_label'=>'سفارش مهمان','actor_name'=>'مدیر کافه','customer_note'=>'سس جدا باشد','sections'=>[['title'=>'آشپزخانه','items'=>[['name'=>'پاستا چیکن آلفردو','quantity'=>3,'note'=>'بدون قارچ','fulfillment_mode'=>'takeaway'],['name'=>'برگر مرغ','quantity'=>1,'note'=>'بدون پیاز','fulfillment_mode'=>'dine_in']]]]
        ];
    }
    $discount = $scenario === 'discount' ? 200000 : 0;
    return [
        'document_kind'=>'customer_final','title'=>'کافه سکنا','badge'=>'فاکتور نهایی','document_status'=>'پرداخت‌شده · تسویه','table_name'=>'میز ۸','invoice_number'=>'فاکتور ۲۸','display_date'=>'۱۶ مرداد ۱۴۰۵ · ۱۴:۳۶','actor_name'=>'مدیر کافه','settlement_label'=>'تسویه','settlement_party'=>null,
        'sections'=>[['title'=>'اقلام فاکتور','items'=>[
            ['name'=>'پاستا چیکن آلفردو','quantity'=>2,'unit_price'=>580000,'line_total'=>1160000],
            ['name'=>'آب دوغ خیار','quantity'=>1,'unit_price'=>260000,'line_total'=>260000],
            ['name'=>'رینگر','quantity'=>1,'unit_price'=>620000,'line_total'=>620000],
            ['name'=>'سرویس بیرون‌بر','quantity'=>1,'unit_price'=>15000,'line_total'=>15000],
        ]]],
        'subtotal'=>2055000,'discount'=>$discount,'total'=>2055000-$discount,'currency'=>'تومان','footer'=>'از همراهی شما سپاسگزاریم.'
    ];
}

function print_template_active(PDO $pdo, string $templateKey): array
{
    $defaults = print_template_defaults($templateKey);
    if (!print_templates_available($pdo)) return $defaults;
    try {
        $stmt = $pdo->prepare('SELECT definition_json FROM print_templates WHERE template_key=? AND active=1 ORDER BY id DESC LIMIT 1');
        $stmt->execute([$templateKey]);
        $json = $stmt->fetchColumn();
        if ($json === false) return $defaults;
        $definition = json_decode((string)$json, true, 512, JSON_THROW_ON_ERROR);
        return print_template_validate(is_array($definition) ? $definition : [], $templateKey);
    } catch (Throwable $e) {
        error_log('print template fallback: ' . $e->getMessage());
        return $defaults;
    }
}

function print_register_response_job(int $jobId,string $destinationKey,string $status): void
{
    if($jobId<1)return;
    $GLOBALS['sokna_print_response_jobs'][$jobId]=['job_id'=>$jobId,'destination_key'=>$destinationKey,'status'=>$status];
}

function print_response_metadata(): array
{
    $jobs=array_values((array)($GLOBALS['sokna_print_response_jobs']??[]));
    if(!$jobs)return [];
    try{
        $pdo=db();
        // Wake is only an accelerator for already committed work. If the business
        // transaction is still open, json_response must not advertise any Job yet.
        if($pdo->inTransaction())return [];
        $ids=array_values(array_unique(array_filter(array_map(static fn(array $j): int=>(int)($j['job_id']??0),$jobs),static fn(int $id): bool=>$id>0)));
        if(!$ids)return [];
        $ph=implode(',',array_fill(0,count($ids),'?'));
        $stmt=$pdo->prepare("SELECT id,destination_key,status FROM print_jobs WHERE id IN ($ph) AND status='pending'");
        $stmt->execute($ids);
        $committed=[];
        foreach($stmt->fetchAll() as $row)$committed[]=['job_id'=>(int)$row['id'],'destination_key'=>(string)$row['destination_key'],'status'=>(string)$row['status']];
        if(!$committed)return [];
        return [
            'protocol_version'=>1,
            'request_id'=>'wake-'.bin2hex(random_bytes(12)),
            'expires_at'=>(new DateTimeImmutable('+60 seconds',new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.v\Z'),
            'jobs'=>$committed,
        ];
    }catch(Throwable $e){
        error_log('print wake metadata unavailable: '.$e->getMessage());
        return [];
    }
}

function print_enqueue_job(
    PDO $pdo,
    string $jobType,
    string $destinationKey,
    array $payload,
    string $idempotencyKey,
    string $entityType,
    string|int|null $entityId,
    ?int $requestedByUserId = null,
    ?int $reprintOfId = null,
    bool $required = false,
    ?string $forcedBlockedReason = null,
    ?string $reprintReason = null,
    int $contractVersion = 4
): array {
    if (!print_tables_available($pdo)) throw new RuntimeException('ماژول چاپ نصب نشده است.');
    $destination = print_destination($pdo, $destinationKey);
    if (!$destination) throw new RuntimeException('مقصد چاپ معتبر نیست.');

    $templateKey = (string)($destination['destination_type'] ?? '') === 'customer' ? 'customer' : 'preparation';
    $payload['schema'] = 'sokna-print-document-v2';
    $payload['print_contract_version'] = $contractVersion;
    $payload['job_type'] = $jobType;
    $payload['destination_key'] = $destinationKey;
    if (!isset($payload['template']) || !is_array($payload['template'])) $payload['template'] = print_template_active($pdo, $templateKey);
    $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    if (strlen($payloadJson) > 262144) throw new RuntimeException('محتوای چاپ بیش از حد مجاز است.');
    $contentSha = print_content_sha256($payloadJson);
    $publicToken = print_public_token();
    $blockReason = $forcedBlockedReason ?: (print_destination_ready($pdo, $destinationKey) ? null : 'destination_unavailable');
    $status = $blockReason === null ? 'pending' : 'blocked';
    $safeKey = text_substr($idempotencyKey, 0, 190);
    $safeReason = $reprintReason !== null ? text_substr(trim($reprintReason), 0, 300) : null;

    try {
        $stmt = $pdo->prepare("INSERT INTO print_jobs(public_token,idempotency_key,contract_version,job_type,destination_key,required,status,blocked_reason,payload_json,content_sha256,entity_type,entity_id,requested_by_user_id,reprint_of_id,reprint_reason,next_attempt_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())");
        $stmt->execute([
            $publicToken,
            $safeKey,
            max(3, min(4, $contractVersion)),
            $jobType,
            $destinationKey,
            $required ? 1 : 0,
            $status,
            $blockReason,
            $payloadJson,
            $contentSha,
            $entityType,
            $entityId === null ? null : (string)$entityId,
            $requestedByUserId,
            $reprintOfId,
            $safeReason,
        ]);
        $id = (int)$pdo->lastInsertId();
        print_register_response_job($id,$destinationKey,$status);
        return [
            'queued' => $status === 'pending',
            'blocked' => $status === 'blocked',
            'id' => $id,
            'job_id' => $id,
            'public_token' => $publicToken,
            'destination_key' => $destinationKey,
            'status' => $status,
            'blocked_reason' => $blockReason,
            'required' => $required,
            'content_sha256' => $contentSha,
        ];
    } catch (PDOException $e) {
        if ((string)$e->getCode() !== '23000') throw $e;
        $existing = $pdo->prepare('SELECT id,public_token,status,destination_key,blocked_reason,required,content_sha256 FROM print_jobs WHERE idempotency_key=? LIMIT 1');
        $existing->execute([$safeKey]);
        $row = $existing->fetch();
        if (!$row) throw $e;
        return [
            'queued' => false,
            'duplicate' => true,
            'id' => (int)$row['id'],
            'job_id' => (int)$row['id'],
            'public_token' => (string)$row['public_token'],
            'status' => (string)$row['status'],
            'destination_key' => (string)$row['destination_key'],
            'blocked_reason' => $row['blocked_reason'] !== null ? (string)$row['blocked_reason'] : null,
            'required' => (int)$row['required'] === 1,
            'content_sha256' => (string)($row['content_sha256'] ?? ''),
        ];
    }
}

function print_job_destination_compatible(string $documentKind, string $jobType, array $destination, array $payload = []): bool
{
    $destType = (string)($destination['destination_type'] ?? '');
    $isPrep = $documentKind === 'preparation' || str_starts_with($jobType, 'prep_');
    if ($isPrep && $destType !== 'preparation') return false;
    if (!$isPrep && $destType !== 'customer') return false;
    if (!$isPrep) return true;
    $needed = [];
    foreach ((array)($payload['sections'] ?? []) as $section) {
        if (!is_array($section)) continue;
        $area = trim((string)($section['area_key'] ?? ''));
        if ($area !== '') $needed[$area] = true;
    }
    if (!$needed) return true;
    $mapped = array_fill_keys(print_destination_areas($destination), true);
    foreach (array_keys($needed) as $area) if (!isset($mapped[$area])) return false;
    return true;
}

function print_reconcile_blocked_jobs(PDO $pdo, ?string $destinationKey = null): int
{
    $params = [];
    $where = "j.status='blocked'";
    if ($destinationKey !== null && $destinationKey !== '') { $where .= ' AND j.destination_key=?'; $params[] = $destinationKey; }
    $stmt = $pdo->prepare("SELECT j.id,j.destination_key,j.job_type,j.blocked_reason,j.payload_json FROM print_jobs j WHERE $where ORDER BY j.id FOR UPDATE");
    $stmt->execute($params);
    $updated = 0;
    foreach ($stmt->fetchAll() as $job) {
        $reason = (string)($job['blocked_reason'] ?? '');
        $targetKey = (string)$job['destination_key'];
        $payload = json_decode((string)$job['payload_json'], true); if (!is_array($payload)) $payload = [];
        if ($reason === 'preparation_area_unmapped') {
            $areas = [];
            foreach ((array)($payload['sections'] ?? []) as $section) {
                if (!is_array($section)) continue;
                $area = trim((string)($section['area_key'] ?? ''));
                if ($area !== '') $areas[$area] = true;
            }
            if (!$areas) continue;
            $map = print_preparation_area_destinations($pdo, true);
            $destinations = [];
            foreach (array_keys($areas) as $area) {
                if (empty($map[$area])) { $destinations = []; break; }
                $destinations[(string)$map[$area]['destination_key']] = $map[$area];
            }
            if (count($destinations) !== 1) continue;
            $targetKey = (string)array_key_first($destinations);
        }
        $destination = print_destination($pdo, $targetKey, true);
        if (!$destination || !print_destination_ready($pdo, $targetKey)) continue;
        if (!print_job_destination_compatible((string)($payload['document_kind'] ?? ''), (string)($job['job_type'] ?? ''), $destination, $payload)) continue;
        $up = $pdo->prepare("UPDATE print_jobs SET destination_key=?,status='pending',blocked_reason=NULL,last_error_code=NULL,last_error=NULL,next_attempt_at=NOW() WHERE id=? AND status='blocked'");
        $up->execute([$targetKey,(int)$job['id']]);
        $updated += $up->rowCount();
    }
    return $updated;
}

function print_order_snapshot(PDO $pdo, int $orderId): array
{
    $stmt = $pdo->prepare("SELECT o.id,o.business_order_number,o.session_id,o.status,o.order_source,o.customer_note,o.created_at,o.accepted_at,t.name table_name,COALESCE(cu.display_name,au.display_name,'مهمان') actor_name FROM orders o JOIN cafe_tables t ON t.id=o.table_id LEFT JOIN users cu ON cu.id=o.created_by_user_id LEFT JOIN users au ON au.id=o.accepted_by_user_id WHERE o.id=? LIMIT 1");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    if (!$order) throw new RuntimeException('سفارش چاپ پیدا نشد.');
    $lineStmt = $pdo->prepare('SELECT id,item_name,quantity,ordered_quantity,item_note,fulfillment_mode,preparation_station,unit_price,line_total FROM order_items WHERE order_id=? ORDER BY id');
    $lineStmt->execute([$orderId]);
    $order['items'] = $lineStmt->fetchAll();
    return $order;
}

function print_prep_sections(array $items): array
{
    $groups = ['kitchen'=>[], 'bar'=>[]];
    foreach ($items as $item) {
        $station = normalize_preparation_station((string)($item['preparation_station'] ?? 'cold_bar'));
        if (!preparation_station_requires_work($station)) continue;
        $area = preparation_area_for_station($station);
        $fulfillmentMode = normalize_fulfillment_mode((string)($item['fulfillment_mode'] ?? 'dine_in'));
        $groups[$area][] = [
            'name' => (string)$item['item_name'] . ($fulfillmentMode === 'takeaway' ? ' · بیرون‌بر' : ''),
            'quantity' => (int)$item['quantity'],
            'previous_quantity' => isset($item['previous_quantity']) ? (int)$item['previous_quantity'] : null,
            'note' => trim((string)($item['item_note'] ?? $item['note'] ?? '')),
            'fulfillment_mode' => $fulfillmentMode,
        ];
    }
    $sections = [];
    foreach (preparation_operational_areas() as $area => $label) {
        if ($groups[$area] === []) continue;
        $sections[] = ['area_key'=>$area, 'title'=>$label, 'items'=>$groups[$area]];
    }
    return $sections;
}

/** Split only by configured physical destination. Bar hot/cold stay one operational section. */
function print_prep_destination_groups(PDO $pdo, array $items): array
{
    $areaDestinations = print_preparation_area_destinations($pdo);
    $defaultDestination = print_default_preparation_destination($pdo);
    $groups = [];
    foreach ($items as $item) {
        $station = normalize_preparation_station((string)($item['preparation_station'] ?? 'cold_bar'));
        if (!preparation_station_requires_work($station)) continue;
        $area = preparation_area_for_station($station);
        $destination = $areaDestinations[$area] ?? null;
        $forcedBlockedReason = null;
        $groupSuffix = '';
        if (!$destination) {
            if (!$defaultDestination) throw new RuntimeException('هیچ مقصد آماده‌سازی برای ثبت Print Intent وجود ندارد.');
            $destination = $defaultDestination;
            $forcedBlockedReason = 'preparation_area_unmapped';
            $groupSuffix = '#unmapped#' . $area;
        }
        $destinationKey = (string)$destination['destination_key'];
        $groupKey = $destinationKey . $groupSuffix;
        if (!isset($groups[$groupKey])) {
            $groups[$groupKey] = [
                'destination' => $destination,
                'destination_key' => $destinationKey,
                'blocked_reason' => $forcedBlockedReason,
                'items' => [],
            ];
        }
        $groups[$groupKey]['items'][] = $item;
    }
    return $groups;
}

function print_prep_payload(array $order, array $items, string $title, string $badge, ?string $note = null): array
{
    $createdAt = (string)($order['accepted_at'] ?: $order['created_at'] ?: date(DATE_ATOM));
    $sections = print_prep_sections($items);
    $areaLabel = count($sections) === 1 ? (string)($sections[0]['title'] ?? '') : '';
    return [
        'document_kind' => 'preparation',
        'title' => setting('cafe_name', 'کافه سکنا'),
        'badge' => trim($title . ($areaLabel !== '' ? ' — ' . $areaLabel : '')),
        'status_label' => $badge,
        'table_name' => (string)$order['table_name'],
        'order_number' => fa_digits(order_display_number($order)),
        'order_reference' => 'سفارش ' . fa_digits((string)order_display_number($order)),
        'created_at' => $createdAt,
        'display_date' => format_jalali_compact($createdAt),
        'source_label' => (string)($order['order_source'] ?? '') === 'staff' ? 'ثبت کارکنان' : 'سفارش مهمان',
        'actor_name' => (string)($order['actor_name'] ?? current_user()['display_name'] ?? 'کاربر'),
        'customer_note' => trim($note ?? (string)($order['customer_note'] ?? '')),
        'sections' => $sections,
        'show_prices' => false,
    ];
}

function print_prep_result(array $jobs): array
{
    $persisted = array_values(array_filter($jobs, static fn(array $job): bool => !empty($job['queued']) || !empty($job['blocked']) || !empty($job['duplicate'])));
    $first = $persisted[0] ?? null;
    return [
        'persisted' => $persisted !== [],
        'queued' => count(array_filter($persisted, static fn(array $job): bool => !empty($job['queued']))) > 0,
        'blocked' => count(array_filter($persisted, static fn(array $job): bool => !empty($job['blocked']) || (($job['status'] ?? '') === 'blocked'))) > 0,
        'jobs' => $jobs,
        'job_count' => count($persisted),
        'duplicate' => $persisted !== [] && count(array_filter($persisted, static fn(array $job): bool => !empty($job['duplicate']))) === count($persisted),
        'id' => $first['id'] ?? null,
        'job_id' => $first['job_id'] ?? null,
    ];
}

function print_enqueue_prep_order(PDO $pdo, int $orderId, ?int $actorUserId = null): array
{
    $order = print_order_snapshot($pdo, $orderId);
    $labels = print_template_active($pdo,'preparation')['design']['labels'] ?? [];
    $jobs = [];
    foreach (print_prep_destination_groups($pdo, $order['items']) as $groupKey => $group) {
        $key = (string)$group['destination_key'];
        $payload = print_prep_payload($order, $group['items'], (string)($labels['ticket_title'] ?? 'فیش آماده‌سازی'), (string)($labels['new_order'] ?? 'سفارش جدید'));
        $jobs[] = print_enqueue_job($pdo, 'prep_order', $key, $payload, 'prep.order.' . $orderId . '.accounted.v4.' . hash('sha256',$groupKey), 'order', $orderId, $actorUserId, null, true, $group['blocked_reason'] ?? null);
    }
    if ($order['items'] && !$jobs && array_filter($order['items'], static fn(array $item): bool => preparation_station_requires_work(normalize_preparation_station((string)($item['preparation_station'] ?? 'cold_bar'))))) {
        throw new RuntimeException('Print Intent آماده‌سازی ثبت نشد.');
    }
    return print_prep_result($jobs);
}

function print_enqueue_prep_reprint(PDO $pdo, int $orderId, int $actorUserId, ?string $requestId = null): array
{
    $order = print_order_snapshot($pdo, $orderId);
    if (!in_array((string)$order['status'], ['accounted','completed'], true)) throw new RuntimeException('فقط سفارش تأییدشده قابل چاپ مجدد است.');
    $labels = print_template_active($pdo,'preparation')['design']['labels'] ?? [];
    $requestId = preg_replace('/[^A-Za-z0-9._:-]/', '', trim((string)$requestId)) ?: bin2hex(random_bytes(12));
    $requestId = text_substr($requestId, 0, 96);
    $jobs = [];
    foreach (print_prep_destination_groups($pdo, $order['items']) as $groupKey => $group) {
        $key = (string)$group['destination_key'];
        $payload = print_prep_payload($order, $group['items'], (string)($labels['ticket_title'] ?? 'فیش آماده‌سازی'), (string)($labels['reprint'] ?? 'چاپ مجدد'));
        $payload['created_at'] = date(DATE_ATOM);
        $payload['request_id'] = $requestId;
        $jobs[] = print_enqueue_job($pdo, 'prep_reprint', $key, $payload, 'prep.reprint.' . $orderId . '.' . hash('sha256',$groupKey) . '.request.' . $requestId, 'order', $orderId, $actorUserId, null, false, $group['blocked_reason'] ?? null, 'درخواست چاپ مجدد آماده‌سازی');
    }
    $result = print_prep_result($jobs);
    if (!$result['persisted']) throw new RuntimeException('درخواست چاپ مجدد ثبت نشد.');
    audit_log_write('print.prep_reprinted', 'order', $orderId, ['print_job_ids'=>array_values(array_filter(array_column($jobs,'job_id')))], $actorUserId);
    return $result;
}

function print_enqueue_prep_adjustment(PDO $pdo, int $adjustmentId, array $item, int $previous, int $newQuantity, string $reason, int $actorUserId): array
{
    $labels = print_template_active($pdo,'preparation')['design']['labels'] ?? [];
    $item['previous_quantity'] = $previous;
    $item['quantity'] = $newQuantity;
    $item['item_note'] = $reason;
    $order = [
        'id'=>(int)$item['order_id'], 'table_name'=>(string)$item['table_name'], 'created_at'=>date(DATE_ATOM),
        'accepted_at'=>date(DATE_ATOM), 'order_source'=>'staff', 'actor_name'=>(string)(current_user()['display_name'] ?? 'کاربر'), 'customer_note'=>$reason,
    ];
    $groups = print_prep_destination_groups($pdo, [$item]);
    if (!$groups) return ['persisted'=>false,'queued'=>false,'reason'=>'no_preparation_work'];
    $jobs = [];
    foreach ($groups as $groupKey => $group) {
        $key = (string)$group['destination_key'];
        $payload = print_prep_payload($order, $group['items'], (string)($labels['adjustment'] ?? 'اصلاح سفارش'), $newQuantity === 0 ? (string)($labels['cancel'] ?? 'لغو سفارش') : (string)($labels['adjustment'] ?? 'اصلاح سفارش'), $reason);
        $jobs[] = print_enqueue_job($pdo, 'prep_adjustment', $key, $payload, 'prep.adjustment.' . $adjustmentId . '.' . hash('sha256',$groupKey), 'order_item_adjustment', $adjustmentId, $actorUserId, null, true, $group['blocked_reason'] ?? null);
    }
    return print_prep_result($jobs);
}

function print_enqueue_prep_cancel(PDO $pdo, int $orderId, int $historyId, int $actorUserId): array
{
    $order = print_order_snapshot($pdo, $orderId);
    $labels = print_template_active($pdo,'preparation')['design']['labels'] ?? [];
    $jobs = [];
    foreach (print_prep_destination_groups($pdo, $order['items']) as $groupKey => $group) {
        $key=(string)$group['destination_key'];
        $payload = print_prep_payload($order, $group['items'], (string)($labels['cancel'] ?? 'لغو سفارش'), (string)($labels['cancel'] ?? 'لغو سفارش'), 'این سفارش لغو شده است.');
        $payload['created_at'] = date(DATE_ATOM);
        $jobs[] = print_enqueue_job($pdo, 'prep_cancel', $key, $payload, 'prep.cancel.' . $orderId . '.' . $historyId . '.' . hash('sha256',$groupKey), 'order', $orderId, $actorUserId, null, true, $group['blocked_reason'] ?? null);
    }
    return print_prep_result($jobs);
}

function print_session_invoice_snapshot(PDO $pdo, int $sessionId): array
{
    $sessionStmt = $pdo->prepare("SELECT s.*,t.name table_name,COALESCE(u.display_name,'—') closed_by_name FROM table_sessions s JOIN cafe_tables t ON t.id=s.table_id LEFT JOIN users u ON u.id=s.closed_by_user_id WHERE s.id=? LIMIT 1");
    $sessionStmt->execute([$sessionId]);
    $session = $sessionStmt->fetch();
    if (!$session) throw new RuntimeException('حساب میز پیدا نشد.');
    $ordersStmt = $pdo->prepare("SELECT id,status,total_amount,created_at FROM orders WHERE session_id=? AND status IN('accounted','completed') ORDER BY created_at,id");
    $ordersStmt->execute([$sessionId]);
    $orders = $ordersStmt->fetchAll();
    if (!$orders) throw new RuntimeException('حساب تأییدشده‌ای برای چاپ وجود ندارد.');
    $ids=array_map('intval',array_column($orders,'id'));$ph=implode(',',array_fill(0,count($ids),'?'));
    $rawStmt=$pdo->prepare("SELECT oi.id order_item_id,oi.item_name,oi.quantity,oi.unit_price,oi.line_total,oi.tax_policy_snapshot,oi.tax_rate_bps_snapshot FROM order_items oi WHERE oi.order_id IN($ph) AND oi.quantity>0 ORDER BY oi.id");
    $rawStmt->execute($ids);$raw=$rawStmt->fetchAll();
    $subtotal=array_sum(array_map(static fn(array $o):int=>(int)$o['total_amount'],$orders));
    $discount=$session['checkout_discount']!==null?(int)$session['checkout_discount']:invoice_discount_amount($subtotal,(string)($session['discount_type']??''),(int)($session['discount_value']??0));
    $calc=tax_calculate_invoice_lines($raw,$discount);
    $items=[];$groups=[];
    foreach($raw as $row){$key=(string)$row['item_name']."\0".(string)$row['unit_price'];if(!isset($groups[$key]))$groups[$key]=['item_name'=>(string)$row['item_name'],'quantity'=>0,'unit_price'=>(int)$row['unit_price'],'line_total'=>0];$groups[$key]['quantity']+=(int)$row['quantity'];$groups[$key]['line_total']+=(int)$row['line_total'];}
    $items=array_values($groups);
    $taxable=$session['checkout_taxable']!==null?(int)$session['checkout_taxable']:(int)$calc['taxable'];
    $tax=$session['checkout_tax']!==null?(int)$session['checkout_tax']:(int)$calc['tax'];
    $total=$session['checkout_total']!==null?(int)$session['checkout_total']:(int)$calc['total'];
    $taxRates=[];
    foreach((array)$calc['lines'] as $line){$rate=(int)($line['tax_rate_bps_snapshot']??0);if((int)($line['invoice_tax_amount']??0)>0&&$rate>0)$taxRates[$rate]=true;}
    return ['session'=>$session,'items'=>$items,'subtotal'=>$subtotal,'discount'=>(int)$calc['discount'],'net'=>(int)$calc['net'],'taxable'=>$taxable,'tax'=>$tax,'tax_rates_bps'=>array_map('intval',array_keys($taxRates)),'total'=>$total];
}

function print_invoice_party(PDO $pdo, int $sessionId, string $destination): ?array
{
    if ($destination === 'accommodation') {
        $stmt = $pdo->prepare("SELECT guest_name_snapshot,room_name_snapshot,reservation_code,remote_transaction_id FROM accommodation_transfers WHERE session_id=? AND status IN('posted','void_pending','void_failed','voided') ORDER BY id DESC LIMIT 1");
        $stmt->execute([$sessionId]);
        $row = $stmt->fetch();
        return $row ? ['label'=>'مهمان اقامتگاه','name'=>(string)$row['guest_name_snapshot'],'detail'=>'اتاق ' . (string)$row['room_name_snapshot'],'reference'=>(string)($row['remote_transaction_id'] ?: $row['reservation_code'])] : null;
    }
    if ($destination === 'subscriber') {
        $stmt = $pdo->prepare("SELECT s.name,s.mobile,sl.balance_after FROM subscriber_ledger sl JOIN subscribers s ON s.id=sl.subscriber_id WHERE sl.table_session_id=? AND sl.entry_type='invoice' ORDER BY sl.id DESC LIMIT 1");
        $stmt->execute([$sessionId]);
        $row = $stmt->fetch();
        return $row ? ['label'=>'مشتری','name'=>(string)$row['name'],'detail'=>(string)$row['mobile'],'balance_after'=>(int)$row['balance_after']] : null;
    }
    return null;
}

function print_invoice_payload(PDO $pdo, int $sessionId, bool $final, ?string $settlementDestination = null, ?int $actorUserId = null, ?int $settlementId = null): array
{
    $customerTemplate = print_template_active($pdo,'customer');
    $customerLabels = $customerTemplate['design']['labels'] ?? [];
    $actorName = '—';
    if ($actorUserId) {
        $actorStmt = $pdo->prepare('SELECT display_name FROM users WHERE id=? LIMIT 1');
        $actorStmt->execute([$actorUserId]);
        $actorName = (string)($actorStmt->fetchColumn() ?: '—');
    }
    if ($final) {
        if (($settlementId ?? 0) < 1) throw new RuntimeException('شناسه دقیق رسید برای چاپ نهایی لازم است.');
        $recordStmt = $pdo->prepare("SELECT sr.*,COALESCE(u.display_name,'—') settlement_actor FROM settlement_records sr LEFT JOIN users u ON u.id=sr.actor_user_id WHERE sr.id=? AND sr.status='completed' LIMIT 1");
        $recordStmt->execute([(int)$settlementId]);
        $record = $recordStmt->fetch();
        if (!$record) throw new RuntimeException('رسید نهایی ثبت‌شده پیدا نشد.');
        $sessionId = (int)$record['session_id'];
        $snapshot = json_decode((string)$record['invoice_snapshot_json'], true);
        if (!is_array($snapshot)) throw new RuntimeException('اطلاعات ثبت‌شده فاکتور نهایی معتبر نیست.');
        $destination = (string)$record['destination'];
        $badges = [
            'direct' => 'فاکتور نهایی — تسویه',
            'accommodation' => 'فاکتور نهایی کافه — ثبت در حساب اقامتگاه',
            'subscriber' => 'فاکتور نهایی کافه — ثبت در حساب مشتری',
        ];
        $items = [];
        foreach ((array)($snapshot['items'] ?? []) as $index => $row) {
            $items[] = [
                'row_number' => $index + 1,
                'name' => (string)($row['name'] ?? ''),
                'quantity' => (int)($row['quantity'] ?? 0),
                'unit_price' => (int)($row['unit_price'] ?? 0),
                'line_total' => (int)($row['line_total'] ?? 0),
                'line_discount' => (int)($row['line_discount'] ?? 0),
                'line_net' => (int)($row['line_net'] ?? ($row['line_total'] ?? 0)),
                'taxable_amount' => (int)($row['taxable_amount'] ?? 0),
                'tax_rate_bps' => (int)($row['tax_rate_bps'] ?? 0),
                'tax_amount' => (int)($row['tax_amount'] ?? 0),
                'line_final' => (int)($row['line_final'] ?? ($row['line_total'] ?? 0)),
                'note' => $row['note'] ?? null,
            ];
        }
        $taxRates = [];
        foreach ((array)($snapshot['items'] ?? []) as $row) {
            $rate=(int)($row['tax_rate_bps'] ?? 0);
            if ((int)($row['tax_amount'] ?? 0) > 0 && $rate > 0) $taxRates[$rate]=true;
        }
        return [
            'document_kind' => 'customer_final',
            'title' => setting('cafe_name', 'کافه سکنا'),
            'badge' => (string)($customerLabels['invoice'] ?? 'فاکتور'),
            'document_status' => 'پرداخت‌شده · ' . settlement_destination_label($destination),
            'table_name' => (string)($snapshot['table_name'] ?? $record['table_name_snapshot']),
            'invoice_number' => financial_document_human_label((string)$record['invoice_number']),
            'invoice_reference' => (string)$record['invoice_number'],
            'created_at' => (string)($snapshot['issued_at'] ?? $record['settled_at']),
            'display_date' => format_jalali_compact((string)($snapshot['issued_at'] ?? $record['settled_at'])),
            'actor_name' => (string)($record['settlement_actor'] ?? $actorName),
            'settlement_label' => settlement_destination_label($destination),
            'settlement_party' => print_invoice_party($pdo, $sessionId, $destination),
            'sections' => [['title' => (string)($customerLabels['items'] ?? 'اقلام'), 'items' => $items]],
            'subtotal' => (int)($snapshot['subtotal'] ?? $record['subtotal']),
            'discount' => (int)($snapshot['discount'] ?? $record['discount']),
            'taxable' => (int)($snapshot['taxable'] ?? $record['taxable_amount'] ?? 0),
            'tax' => (int)($snapshot['tax'] ?? $record['tax_amount'] ?? 0),
            'tax_rates_bps' => array_map('intval',array_keys($taxRates)),
            'total' => (int)($snapshot['total'] ?? $record['total']),
            'currency' => 'تومان',
            'show_prices' => true,
            'footer' => (string)($customerTemplate['footer'] ?? 'از همراهی شما سپاسگزاریم.'),
        ];
    }

    $snapshot = print_session_invoice_snapshot($pdo, $sessionId);
    $session = $snapshot['session'];
    $items = [];
    foreach ($snapshot['items'] as $index => $row) {
        $items[] = [
            'row_number' => $index + 1,
            'name' => (string)$row['item_name'],
            'quantity' => (int)$row['quantity'],
            'unit_price' => (int)$row['unit_price'],
            'line_total' => (int)$row['line_total'],
        ];
    }
    return [
        'document_kind' => 'customer_bill',
        'title' => setting('cafe_name', 'کافه سکنا'),
        'badge' => (string)($customerLabels['prebill'] ?? 'صورتحساب'),
        'document_status' => 'جهت اطلاع — پرداخت‌نشده',
        'table_name' => (string)$session['table_name'],
        'invoice_number' => null,
        'created_at' => date(DATE_ATOM),
        'display_date' => format_jalali_compact(date(DATE_ATOM)),
        'actor_name' => $actorName,
        'settlement_label' => null,
        'settlement_party' => null,
        'sections' => [['title' => (string)($customerLabels['items'] ?? 'اقلام'), 'items' => $items]],
        'subtotal' => (int)$snapshot['subtotal'],
        'discount' => (int)$snapshot['discount'],
        'taxable' => (int)($snapshot['taxable'] ?? 0),
        'tax' => (int)($snapshot['tax'] ?? 0),
        'tax_rates_bps' => array_values(array_map('intval',(array)($snapshot['tax_rates_bps'] ?? []))),
        'total' => (int)$snapshot['total'],
        'currency' => 'تومان',
        'show_prices' => true,
        'footer' => 'مبلغ صورتحساب بر پایه سفارش‌های ثبت‌شده تا زمان چاپ است.',
    ];
}

function print_enqueue_prebill(PDO $pdo, int $sessionId, int $actorUserId, ?string $requestId = null): array
{
    $payload = print_invoice_payload($pdo, $sessionId, false, null, $actorUserId);
    $requestId = preg_replace('/[^A-Za-z0-9._:-]/', '', trim((string)$requestId)) ?: bin2hex(random_bytes(12));
    $payload['request_id'] = text_substr($requestId, 0, 96);
    return print_enqueue_job(
        $pdo,
        'customer_prebill',
        'customer_receipt',
        $payload,
        'prebill.session.' . $sessionId . '.request.' . text_substr($requestId, 0, 96),
        'table_session',
        $sessionId,
        $actorUserId
    );
}

function print_enqueue_final_invoice(PDO $pdo, int $settlementId, string $settlementDestination, int $actorUserId, bool $reprint = false, ?string $reprintIdempotencyKey = null): array
{
    if ($settlementId < 1) throw new RuntimeException('شناسه رسید برای چاپ نهایی معتبر نیست.');
    $payload = print_invoice_payload($pdo, 0, true, $settlementDestination, $actorUserId, $settlementId);
    if ($reprint) $payload['badge'] = 'چاپ مجدد — ' . $payload['badge'];
    $key = $reprint ? (trim((string)$reprintIdempotencyKey) ?: ('final.reprint.settlement.' . $settlementId . '.' . bin2hex(random_bytes(6)))) : 'final.settlement.' . $settlementId . '.v1';
    return print_enqueue_job($pdo, 'customer_final', 'customer_receipt', $payload, $key, 'settlement_record', $settlementId, $actorUserId);
}


function print_job_create_reprint(PDO $pdo, int $jobId, int $actorUserId, string $reason, ?string $destinationKey = null, ?string $actionRequestId = null): array
{
    $reason=text_substr(trim($reason),0,300);
    if($reason==='')throw new RuntimeException('دلیل چاپ مجدد لازم است.');
    $stmt=$pdo->prepare('SELECT * FROM print_jobs WHERE id=? FOR UPDATE');
    $stmt->execute([$jobId]);$job=$stmt->fetch();
    if(!$job)throw new RuntimeException('درخواست چاپ پیدا نشد.');
    if(!in_array((string)$job['status'],['submitted','unknown','recovery_hold','failed'],true))throw new RuntimeException('این درخواست در وضعیت قابل چاپ مجدد نیست.');
    $payload=json_decode((string)$job['payload_json'],true,512,JSON_THROW_ON_ERROR);
    if(!is_array($payload))throw new RuntimeException('اطلاعات ثبت‌شده چاپ معتبر نیست.');
    $payload['is_reprint']=true;
    $payload['reprint_of_job_id']=$jobId;
    $payload['reprint_reason']=$reason;
    $payload['reprint_label']='چاپ مجدد';
    $payload['badge']='چاپ مجدد — '.preg_replace('/^چاپ مجدد\s*—\s*/u','',(string)($payload['badge']??'فیش'));
    $destinationKey=$destinationKey?:((string)$job['destination_key']);
    $actionRequestId=preg_replace('/[^A-Za-z0-9._:-]/','',trim((string)$actionRequestId));
    if($actionRequestId==='')$actionRequestId=bin2hex(random_bytes(12));
    $actionRequestId=text_substr($actionRequestId,0,96);
    $new=print_enqueue_job(
        $pdo,(string)$job['job_type'],$destinationKey,$payload,
        'reprint.job.'.$jobId.'.action.'.$actionRequestId,
        (string)$job['entity_type'],$job['entity_id']!==null?(string)$job['entity_id']:null,
        $actorUserId,$jobId,(int)$job['required']===1,null,$reason,4
    );
    if(!empty($new['duplicate'])){
        $dupStmt=$pdo->prepare('SELECT destination_key,payload_json,reprint_of_id FROM print_jobs WHERE id=? LIMIT 1');
        $dupStmt->execute([(int)$new['job_id']]);$duplicate=$dupStmt->fetch();
        $duplicatePayload=$duplicate?json_decode((string)$duplicate['payload_json'],true):null;
        $sameAction=$duplicate
            && (int)($duplicate['reprint_of_id']??0)===$jobId
            && (string)($duplicate['destination_key']??'')===$destinationKey
            && is_array($duplicatePayload)
            && (string)($duplicatePayload['reprint_reason']??'')===$reason;
        if(!$sameAction)throw new RuntimeException('شناسه عملیات چاپ مجدد قبلاً با محتوای دیگری استفاده شده است.');
    }
    if(in_array((string)$job['status'],['unknown','recovery_hold','failed'],true)){
        $resolution=(string)$job['status']==='failed'?'safe_failed_reprint_created':'reprint_created';
        $pdo->prepare("UPDATE print_jobs SET resolution_state=?,resolved_at=COALESCE(resolved_at,NOW()),resolved_by_user_id=COALESCE(resolved_by_user_id,?),resolution_note=COALESCE(resolution_note,?) WHERE id=?")
            ->execute([$resolution,$actorUserId,$reason,$jobId]);
    }
    return $new;
}

function print_job_resolve_ambiguous(PDO $pdo,int $jobId,int $actorUserId,string $resolution,string $note=''): void
{
    if(!in_array($resolution,['human_confirmed_printed','human_confirmed_not_printed','human_no_longer_needed'],true))throw new RuntimeException('نتیجه انتخاب‌شده معتبر نیست.');
    $note=text_substr(trim($note),0,500);
    if($resolution==='human_no_longer_needed'&&$note==='')throw new RuntimeException('برای بستن چاپی که دیگر لازم نیست، دلیل کوتاهی ثبت کنید.');
    $stmt=$pdo->prepare("UPDATE print_jobs SET resolution_state=?,resolved_at=NOW(),resolved_by_user_id=?,resolution_note=? WHERE id=? AND status IN('unknown','recovery_hold') AND resolved_at IS NULL");
    $stmt->execute([$resolution,$actorUserId,$note?:null,$jobId]);
    if(!$stmt->rowCount())throw new RuntimeException('این وضعیت قبلاً تعیین تکلیف شده یا دیگر قابل تغییر نیست.');
}

/**
 * Cancel a print intent only while the server can still prove it has not crossed Accept.
 * reserved is safe to cancel because the Agent is forbidden to print before a successful
 * accept; row locks serialize this action against a concurrent accept request.
 */
function print_job_cancel_unprinted(PDO $pdo,int $jobId,int $actorUserId,string $reason=''): void
{
    if(!$pdo->inTransaction())throw new LogicException('لغو ایمن چاپ باید داخل تراکنش انجام شود.');
    $reason=text_substr(trim($reason),0,500);
    if($reason==='')$reason='درخواست چاپ دیگر لازم نیست و پیش از Accept توسط مدیر بسته شد.';
    $jobStmt=$pdo->prepare("SELECT id,status,resolved_at FROM print_jobs WHERE id=? FOR UPDATE");
    $jobStmt->execute([$jobId]);$job=$jobStmt->fetch();
    if(!$job)throw new RuntimeException('درخواست چاپ پیدا نشد.');
    $status=(string)$job['status'];
    if(!in_array($status,['pending','blocked','failed','reserved'],true))throw new RuntimeException('این درخواست از مرز پذیرش چاپ عبور کرده است؛ ابتدا نتیجه چاپ را تعیین تکلیف کنید.');

    if($status==='reserved'){
        $attemptStmt=$pdo->prepare("SELECT id,state,local_receipt_id,accepted_at,started_at,spooler_job_id,report_request_id FROM print_attempts WHERE job_id=? ORDER BY attempt_no DESC,id DESC LIMIT 1 FOR UPDATE");
        $attemptStmt->execute([$jobId]);$attempt=$attemptStmt->fetch();
        if(!$attempt || (string)$attempt['state']!=='reserved' || !empty($attempt['local_receipt_id']) || !empty($attempt['accepted_at']) || !empty($attempt['started_at']) || !empty($attempt['spooler_job_id']) || !empty($attempt['report_request_id'])){
            throw new RuntimeException('رزرو چاپ دیگر اثباتاً قبل از Accept نیست؛ لغو مستقیم برای جلوگیری از چاپ تکراری ممنوع است.');
        }
        $attemptUpdate=$pdo->prepare("UPDATE print_attempts SET state='cancelled',finished_at=NOW(),outcome='cancelled_by_admin',error_code='cancelled_by_admin',error_message=? WHERE id=? AND state='reserved'");
        $attemptUpdate->execute([$reason,(int)$attempt['id']]);
        if($attemptUpdate->rowCount()!==1)throw new RuntimeException('وضعیت Attempt هم‌زمان تغییر کرد؛ دوباره وضعیت چاپ را بررسی کنید.');
    }

    $stmt=$pdo->prepare("UPDATE print_jobs SET status='cancelled',blocked_reason=NULL,next_attempt_at=NULL,lease_expires_at=NULL,last_error_code='cancelled_by_admin',last_error=?,resolution_state='cancelled_unprinted',resolved_at=NOW(),resolved_by_user_id=?,resolution_note=? WHERE id=? AND status=?");
    $stmt->execute([$reason,$actorUserId,$reason,$jobId,$status]);
    if($stmt->rowCount()!==1)throw new RuntimeException('وضعیت چاپ هم‌زمان تغییر کرد؛ دوباره وضعیت چاپ را بررسی کنید.');
}

function print_job_stale_ownership_threshold_seconds(): int
{
    return 120;
}

function print_job_hold_stale_ownership(PDO $pdo,int $jobId,int $actorUserId,string $reason=''): string
{
    $reason=text_substr(trim($reason),0,300);
    if($reason==='')$reason='درخواست مدت زیادی روی سرویس چاپ بدون پیشرفت مانده و ادامه خودکار ممکن است چاپ تکراری ایجاد کند.';
    $jobStmt=$pdo->prepare("SELECT id,status,claimed_at,resolved_at,TIMESTAMPDIFF(SECOND,claimed_at,NOW()) claimed_age_seconds FROM print_jobs WHERE id=? FOR UPDATE");
    $jobStmt->execute([$jobId]);$job=$jobStmt->fetch();
    if(!$job||(string)$job['status']!=='claimed'||!empty($job['resolved_at']))throw new RuntimeException('فقط درخواست چاپِ تحویل‌شده به رایانه و تعیین‌تکلیف‌نشده قابل توقف است.');
    $claimedAge=$job['claimed_age_seconds']===null?-1:(int)$job['claimed_age_seconds'];
    if($claimedAge<print_job_stale_ownership_threshold_seconds())throw new RuntimeException('این درخواست هنوز در بازه طبیعی دریافت است؛ برای جلوگیری از قطع چاپ سالم کمی صبر کنید.');
    $attemptStmt=$pdo->prepare("SELECT id,state,started_at FROM print_attempts WHERE job_id=? AND state IN('claimed','started') ORDER BY attempt_no DESC,id DESC LIMIT 1 FOR UPDATE");
    $attemptStmt->execute([$jobId]);$attempt=$attemptStmt->fetch();
    if(!$attempt)throw new RuntimeException('تلاش فعال برای این درخواست چاپ پیدا نشد.');
    $attemptState=(string)$attempt['state'];
    $target=$attemptState==='started'?'unknown':'recovery_hold';
    $errorCode=$target==='unknown'?'admin_stale_started':'admin_stale_claimed';
    $pdo->prepare("UPDATE print_attempts SET state=?,finished_at=NOW(),outcome=?,error_code=?,error_message=? WHERE id=? AND state=?")
        ->execute([$target,$target,$errorCode,$reason,(int)$attempt['id'],$attemptState]);
    $pdo->prepare("UPDATE print_jobs SET status=?,next_attempt_at=NULL,last_error_code=?,last_error=? WHERE id=? AND status='claimed' AND resolved_at IS NULL")
        ->execute([$target,$errorCode,$reason,$jobId]);
    return $target;
}

function print_admin_action_id(string $value): string
{
    $value=trim($value);
    if(!preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{7,95}$/',$value))throw new RuntimeException('شناسه یکتای عملیات چاپ معتبر نیست.');
    return $value;
}

function print_admin_action_hash(string $action,array $payload): string
{
    ksort($payload,SORT_STRING);
    return hash('sha256',$action."\n".json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
}

function print_admin_action_is_replay(array $job,string $action,string $requestId,string $requestHash): bool
{
    $storedId=(string)($job['last_admin_action_id']??'');
    if($storedId==='')return false;
    if($storedId!==$requestId)return false;
    if((string)($job['last_admin_action_type']??'')!==$action || !hash_equals((string)($job['last_admin_action_hash']??''),$requestHash)){
        throw new RuntimeException('شناسه عملیات قبلاً با محتوای دیگری استفاده شده است.');
    }
    return true;
}

function print_job_safe_retry(PDO $pdo,int $jobId,int $actorUserId,string $reason,string $actionRequestId): void
{
    $reason=text_substr(trim($reason),0,300);
    if($reason==='')$reason='تلاش مجدد پس از خطای قطعی پیش از ارسال به چاپ';
    $actionRequestId=print_admin_action_id($actionRequestId);
    $requestHash=print_admin_action_hash('safe_retry',['job_id'=>$jobId,'reason'=>$reason]);
    $stmt=$pdo->prepare("SELECT status,resolved_at,last_error_code,retry_cycle,last_admin_action_id,last_admin_action_type,last_admin_action_hash FROM print_jobs WHERE id=? FOR UPDATE");$stmt->execute([$jobId]);$job=$stmt->fetch();
    if(!$job)throw new RuntimeException('درخواست چاپ پیدا نشد.');
    if(print_admin_action_is_replay($job,'safe_retry',$actionRequestId,$requestHash))return;
    if(!in_array((string)$job['status'],['failed','blocked'],true))throw new RuntimeException('تلاش دوباره فقط برای خطای قطعی پیش از ارسال یا مشکل تنظیمات قابل انجام است.');
    if((string)$job['status']==='blocked'){
        print_reconcile_blocked_jobs($pdo);
        $chk=$pdo->prepare('SELECT status FROM print_jobs WHERE id=?');$chk->execute([$jobId]);
        if((string)$chk->fetchColumn()==='blocked')throw new RuntimeException('ابتدا تنظیم مقصد یا سرویس چاپ داخلی را اصلاح کنید.');
        $pdo->prepare("UPDATE print_jobs SET last_admin_action_id=?,last_admin_action_type='safe_retry',last_admin_action_hash=? WHERE id=?")->execute([$actionRequestId,$requestHash,$jobId]);
        return;
    }
    $cycle=(int)$job['retry_cycle']+1;
    $pdo->prepare("UPDATE print_jobs SET retry_cycle=?,retry_cycle_started_at=NOW(),retry_cycle_started_by_user_id=?,status='pending',blocked_reason=NULL,claimed_by_agent_id=NULL,claimed_at=NULL,lease_expires_at=NULL,accepted_at=NULL,local_receipt_id=NULL,next_attempt_at=NOW(),last_error_code='manual_safe_retry',last_error=?,last_admin_action_id=?,last_admin_action_type='safe_retry',last_admin_action_hash=? WHERE id=? AND status='failed'")
        ->execute([$cycle,$actorUserId,$reason,$actionRequestId,$requestHash,$jobId]);
    audit_log_write_strict($pdo,'print_job_retry_cycle_started','print_job',$jobId,['retry_cycle'=>$cycle,'reason'=>$reason],$actorUserId);
}

function print_job_reroute_unprinted(PDO $pdo,int $jobId,string $destinationKey,int $actorUserId,string $reason,string $actionRequestId): void
{
    $reason=text_substr(trim($reason),0,300);if($reason==='')throw new RuntimeException('دلیل تغییر مسیر لازم است.');
    $actionRequestId=print_admin_action_id($actionRequestId);
    $requestHash=print_admin_action_hash('reroute',['job_id'=>$jobId,'destination_key'=>$destinationKey,'reason'=>$reason]);
    $dest=print_destination($pdo,$destinationKey,true);if(!$dest)throw new RuntimeException('مقصد چاپ معتبر نیست.');
    $stmt=$pdo->prepare("SELECT status,job_type,payload_json,retry_cycle,last_admin_action_id,last_admin_action_type,last_admin_action_hash FROM print_jobs WHERE id=? FOR UPDATE");$stmt->execute([$jobId]);$job=$stmt->fetch();
    if(!$job)throw new RuntimeException('درخواست چاپ پیدا نشد.');
    if(print_admin_action_is_replay($job,'reroute',$actionRequestId,$requestHash))return;
    if(!in_array((string)$job['status'],['pending','blocked','failed'],true))throw new RuntimeException('فقط درخواست اثباتاً چاپ‌نشده قابل تغییر مسیر است.');
    $payload=json_decode((string)$job['payload_json'],true);if(!is_array($payload))throw new RuntimeException('Snapshot چاپ معتبر نیست.');
    if(!print_job_destination_compatible((string)($payload['document_kind']??''),(string)$job['job_type'],$dest,$payload))throw new RuntimeException('مقصد انتخاب‌شده با نوع سند یا بخش آماده‌سازی سازگار نیست.');
    $blocked=print_destination_ready($pdo,$destinationKey)?null:'destination_unavailable';
    $cycle=(int)$job['retry_cycle'];$startsNewCycle=(string)$job['status']==='failed';
    if($startsNewCycle)$cycle++;
    $pdo->prepare("UPDATE print_jobs SET destination_key=?,retry_cycle=?,retry_cycle_started_at=IF(?,NOW(),retry_cycle_started_at),retry_cycle_started_by_user_id=IF(?,?,retry_cycle_started_by_user_id),status=?,blocked_reason=?,claimed_by_agent_id=NULL,claimed_at=NULL,lease_expires_at=NULL,accepted_at=NULL,local_receipt_id=NULL,next_attempt_at=NOW(),last_error_code='manual_reroute',last_error=?,last_admin_action_id=?,last_admin_action_type='reroute',last_admin_action_hash=? WHERE id=?")
        ->execute([$destinationKey,$cycle,$startsNewCycle?1:0,$startsNewCycle?1:0,$actorUserId,$blocked===null?'pending':'blocked',$blocked,$reason,$actionRequestId,$requestHash,$jobId]);
    audit_log_write_strict($pdo,'print_job_rerouted','print_job',$jobId,['destination_key'=>$destinationKey,'reason'=>$reason,'retry_cycle'=>$cycle],$actorUserId);
}

function print_agent_online(array $agent, int $thresholdSeconds = 45): bool
{
    if ((int)($agent['active'] ?? 0) !== 1 || !empty($agent['retired_at']) || empty($agent['last_heartbeat_at'])) return false;
    $timestamp = strtotime((string)$agent['last_heartbeat_at']);
    return $timestamp !== false && (time() - $timestamp) <= $thresholdSeconds;
}

function print_open_problem_sql(string $alias = 'j'): string
{
    $a = preg_replace('/[^A-Za-z0-9_]/','',$alias) ?: 'j';
    return "(($a.status='blocked') OR ($a.status IN('failed','unknown','recovery_hold') AND $a.resolved_at IS NULL) OR ($a.status IN('reserved','claimed') AND $a.claimed_at<DATE_SUB(NOW(),INTERVAL 2 MINUTE)))";
}

function print_queue_summary(?PDO $pdo = null): array
{
    $pdo ??= db();
    if (!print_tables_available($pdo)) return ['installed' => false, 'pending' => 0, 'problem' => 0, 'agent_online' => false];
    $problemSql=print_open_problem_sql('j');
    $counts = $pdo->query("SELECT SUM(j.status IN('pending','reserved','claimed')) pending_count,SUM($problemSql) problem_count FROM print_jobs j")->fetch() ?: [];
    $agent = $pdo->query('SELECT * FROM print_agents WHERE active=1 AND retired_at IS NULL ORDER BY last_seen_at DESC,id LIMIT 1')->fetch() ?: [];
    return [
        'installed' => true,
        'pending' => (int)($counts['pending_count'] ?? 0),
        'problem' => (int)($counts['problem_count'] ?? 0),
        'agent_online' => $agent ? print_agent_online($agent) : false,
        'agent_name' => (string)($agent['name'] ?? ''),
        'last_seen_at' => $agent['last_seen_at'] ?? null,
    ];
}


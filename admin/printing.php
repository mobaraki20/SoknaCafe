<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
require_login(['admin']);
require dirname(__DIR__) . '/includes/panel_layout.php';

$pdo = db();
$notice = '';
$error = '';
$issuedToken = '';
$agentRelease = print_agent_release_metadata();
$agentReleaseStale = in_array((string)($agentRelease['source'] ?? ''), ['cache-stale','fallback'], true);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_SESSION['printing_issued_token'])) { $issuedToken=(string)$_SESSION['printing_issued_token']; unset($_SESSION['printing_issued_token']); }
$tabInput = trim((string)($_GET['tab'] ?? $_POST['return_tab'] ?? ''));
$tabAliases = ['status'=>'overview','destinations'=>'settings','agents'=>'settings','templates'=>'settings'];
$tab = $tabAliases[$tabInput] ?? (in_array($tabInput,['overview','settings','diagnostics'],true) ? $tabInput : 'overview');

if (!print_tables_available($pdo)) {
    panel_header('چاپ و پرینترها', 'printing');
    echo '<div class="alert alert-error">ساختار چاپ هنوز روی دیتابیس نصب نشده است. بسته ارتقا را کامل اجرا کنید.</div>';
    panel_footer();
    exit;
}

function printing_agent_token(): string
{
    return bin2hex(random_bytes(24));
}

function printing_action_request_id(string $action,int $jobId): string
{
    $raw=trim((string)($_POST['action_request_id']??''));
    if(!preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{7,95}$/',$raw))throw new RuntimeException('شناسه عملیات چاپ معتبر نیست.');
    return $raw;
}

function printing_new_action_request_id(string $action,int $jobId): string
{
    return 'adm.'.preg_replace('/[^A-Za-z0-9._:-]/','',$action).'.'.$jobId.'.'.bin2hex(random_bytes(8));
}

function printing_redirect(string $tab): never
{
    header('Location: ' . asset('admin/printing.php?tab=' . rawurlencode($tab)), true, 303);
    exit;
}

function printing_preparation_areas_from_post(): array
{
    $areas = [];
    foreach ((array)($_POST['preparation_areas'] ?? []) as $area) {
        $area = normalize_preparation_area((string)$area);
        $areas[$area] = true;
    }
    return array_keys($areas);
}

function printing_queue_health(array $agent, string $queueName): array
{
    $health=print_agent_queue_readiness($agent,$queueName);
    return ['known'=>(bool)$health['known'],'ready'=>(bool)$health['ready'],'label'=>(string)$health['label'],'reason'=>(string)$health['reason'],'printer_discovery_at'=>$health['printer_discovery_at']??null];
}


function printing_queue_readiness_error(array $health,string $queueName,string $role): string
{
    $queueName=trim($queueName);$role=trim($role);
    return match((string)($health['reason']??'')){
        'heartbeat_missing'=>'از رایانه چاپ '.$role.' هنوز Heartbeat معتبر دریافت نشده است. برنامه چاپ را اجرا و اتصال آن را بررسی کنید.',
        'heartbeat_stale'=>'آخرین Heartbeat رایانه چاپ '.$role.' قدیمی است؛ اتصال Agent را بررسی کنید.',
        'discovery_missing'=>'رایانه چاپ '.$role.' متصل است، اما هنوز فهرست پرینترهای آن دریافت نشده است.',
        'discovery_stale'=>'رایانه چاپ '.$role.' متصل است، اما فهرست پرینترهای آن تازه نیست.',
        'queue_not_found'=>'صف چاپ «'.$queueName.'» در آخرین فهرست پرینترهای رایانه '.$role.' پیدا نشد.',
        'queue_offline'=>'صف چاپ «'.$queueName.'» روی رایانه '.$role.' آفلاین است.',
        'queue_paused'=>'صف چاپ «'.$queueName.'» روی رایانه '.$role.' متوقف است.',
        'queue_paper_out'=>'صف چاپ «'.$queueName.'» روی رایانه '.$role.' کاغذ ندارد.',
        'queue_error'=>'صف چاپ «'.$queueName.'» روی رایانه '.$role.' خطا گزارش می‌کند.',
        'retired'=>'رایانه چاپ '.$role.' بازنشسته شده و قابل استفاده در مسیر عملیاتی نیست.',
        default=>'رایانه/صف چاپ '.$role.' آماده نیست: '.(string)($health['label']??'وضعیت نامشخص'),
    };
}

function printing_agent_health(array $agent): array
{
    $online=print_agent_online($agent);
    $health=json_decode((string)($agent['health_json']??'{}'),true);
    if(!is_array($health))$health=[];
    $unknown=max(0,(int)($agent['local_unknown_count']??0));
    $failures=max(0,(int)($health['consecutive_api_failures']??0));
    $pendingReports=max(0,(int)($health['pending_report_count']??0));
    $authBlockedReports=max(0,(int)($health['auth_blocked_report_count']??0));
    $reconcileReports=max(0,(int)($health['reconciliation_report_count']??0));
    $configOk=bool_from_mixed($health['config_ok']??true);
    $workerOk=bool_from_mixed($health['worker_ok']??true);
    $lockOk=bool_from_mixed($health['instance_lock_ok']??true);
    if(!$online)return ['state'=>'unavailable','label'=>'آفلاین','detail'=>'ارتباط تازه‌ای از رایانه چاپ دریافت نشده','health'=>$health];
    if($unknown>0)return ['state'=>'attention','label'=>'نیازمند بررسی','detail'=>fa_digits($unknown).' وضعیت مبهم محلی نیازمند تصمیم است','health'=>$health];
    if($authBlockedReports>0||$reconcileReports>0)return ['state'=>'attention','label'=>'گزارش چاپ نیازمند رسیدگی','detail'=>fa_digits($authBlockedReports+$reconcileReports).' گزارش Agent هنوز ACK معتبر نگرفته است','health'=>$health];
    if(!$configOk||!$workerOk||!$lockOk)return ['state'=>'attention','label'=>'نیازمند بررسی','detail'=>'رایانه چاپ یک خطای داخلی یا پیکربندی گزارش کرده','health'=>$health];
    if($failures>0)return ['state'=>'degraded','label'=>'ارتباط ناپایدار','detail'=>fa_digits($failures).' خطای API متوالی گزارش شده','health'=>$health];
    if($pendingReports>0)return ['state'=>'degraded','label'=>'گزارش در انتظار ACK','detail'=>fa_digits($pendingReports).' گزارش نتیجه هنوز در outbox Agent مانده است','health'=>$health];
    return ['state'=>'healthy','label'=>'آماده','detail'=>'ارتباط و وضعیت داخلی رایانه چاپ عادی است','health'=>$health];
}

function printing_agent_health_class(string $state): string
{
    return match($state){'healthy'=>'is-ok','degraded','attention'=>'is-warn',default=>'is-off'};
}

function printing_validate_area_uniqueness(PDO $pdo, string $destinationKey, array $areas): void
{
    if(!$pdo->inTransaction())throw new LogicException('اعتبارسنجی هم‌زمانی مقصد باید داخل تراکنش انجام شود.');
    $pdo->query("SELECT destination_key FROM print_destinations WHERE destination_type='preparation' ORDER BY destination_key FOR UPDATE")->fetchAll();
    $stmt = $pdo->prepare("SELECT destination_key,label,preparation_areas_json FROM print_destinations WHERE destination_type='preparation' AND active=1 AND destination_key<>? ORDER BY destination_key");
    $stmt->execute([$destinationKey]);
    foreach ($stmt->fetchAll() as $row) {
        $overlap = array_values(array_intersect($areas, print_destination_areas($row)));
        if ($overlap) {
            $labels = preparation_operational_areas();$names = array_map(static fn(string $a): string => $labels[$a] ?? $a, $overlap);
            throw new RuntimeException('بخش ' . implode(' و ', $names) . ' هم‌اکنون به مقصد «' . (string)$row['label'] . '» متصل است.');
        }
    }
}

function printing_agent_unsettled_attempt_count(PDO $pdo,int $agentId,bool $forUpdate=false): int
{
    $sql="SELECT COUNT(*) FROM print_attempts a JOIN print_jobs j ON j.id=a.job_id WHERE a.agent_id=? AND (a.state IN('reserved','claimed','started') OR (a.state IN('unknown','recovery_hold') AND j.resolved_at IS NULL))";
    // COUNT itself cannot use FOR UPDATE portably; lock the matching rows first when requested.
    if($forUpdate){$lock=$pdo->prepare("SELECT a.id FROM print_attempts a JOIN print_jobs j ON j.id=a.job_id WHERE a.agent_id=? AND (a.state IN('reserved','claimed','started') OR (a.state IN('unknown','recovery_hold') AND j.resolved_at IS NULL)) ORDER BY a.id FOR UPDATE");$lock->execute([$agentId]);$rows=$lock->fetchAll();return count($rows);}
    $stmt=$pdo->prepare($sql);$stmt->execute([$agentId]);return (int)$stmt->fetchColumn();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? null);
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'save_checkout_print_default') {
            $value = isset($_POST['checkout_print_default']) ? '1' : '0';
            $pdo->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)')->execute(['checkout_print_default',$value]);
            $notice = 'رفتار پیش‌فرض چاپ سند مشتری ذخیره شد.';
            audit_log_write('checkout_print_default_updated', 'settings', 'checkout_print_default', ['enabled'=>$value==='1'], (int)current_user()['id']);
        } elseif ($action === 'create_agent') {
            $name = text_substr(trim((string)($_POST['name'] ?? '')), 0, 120);
            if ($name === '') throw new RuntimeException('نام رایانه چاپ را وارد کنید.');
            $issuedToken = printing_agent_token();
            $pdo->prepare('INSERT INTO print_agents(name,token_hash,token_hint,active) VALUES(?,?,?,1)')
                ->execute([$name, hash('sha256', $issuedToken), substr($issuedToken, -8)]);
            $notice = 'رایانه چاپ ثبت شد. کلید اتصال فقط همین بار نمایش داده می‌شود.';
            audit_log_write('print_agent_created', 'print_agent', (string)$pdo->lastInsertId(), ['name' => $name], (int)current_user()['id']);
        } elseif ($action === 'rotate_agent') {
            $id = (int)($_POST['agent_id'] ?? 0);if($id<1)throw new RuntimeException('رایانه چاپ معتبر نیست.');
            $pdo->beginTransaction();$lock=$pdo->prepare('SELECT id,name FROM print_agents WHERE id=? FOR UPDATE');$lock->execute([$id]);$row=$lock->fetch();if(!$row)throw new RuntimeException('رایانه چاپ پیدا نشد.');
            if(printing_agent_unsettled_attempt_count($pdo,$id,true)>0)throw new RuntimeException('این رایانه هنوز اجرای چاپ یا گزارش تعیین‌تکلیف‌نشده دارد؛ ابتدا کارهای چاپ باز را تعیین تکلیف کنید و سپس کلید را عوض کنید.');
            $issuedToken = printing_agent_token();
            $pdo->prepare('UPDATE print_agents SET token_hash=?,token_hint=?,last_error=NULL WHERE id=?')->execute([hash('sha256', $issuedToken), substr($issuedToken, -8), $id]);
            audit_log_write_strict($pdo,'print_agent_token_rotated','print_agent',(string)$id,['policy'=>'drain_before_rotation'],(int)current_user()['id']);
            $pdo->commit();$notice = 'کلید اتصال پس از Drain کامل تعویض شد. کلید قبلی دیگر کار نمی‌کند.';
        } elseif ($action === 'set_agent_active') {
            $id = (int)($_POST['agent_id'] ?? 0);$desiredRaw=(string)($_POST['desired_active']??'');
            if($id<1||!in_array($desiredRaw,['0','1'],true))throw new RuntimeException('وضعیت رایانه چاپ معتبر نیست.');$desired=(int)$desiredRaw;
            $pdo->beginTransaction();$stmt=$pdo->prepare('SELECT id,name,active,retired_at FROM print_agents WHERE id=? FOR UPDATE');$stmt->execute([$id]);$before=$stmt->fetch();if(!$before)throw new RuntimeException('رایانه چاپ پیدا نشد.');
            if($desired===1&&!empty($before['retired_at']))throw new RuntimeException('رایانه بازنشسته برای حفظ تاریخچه دوباره فعال نمی‌شود؛ رایانه جدید بسازید.');
            if($desired===0&&printing_agent_unsettled_attempt_count($pdo,$id,true)>0)throw new RuntimeException('این رایانه هنوز اجرای چاپ یا گزارش تعیین‌تکلیف‌نشده دارد؛ پیش از غیرفعال‌کردن، کارهای چاپ باز را تعیین تکلیف کنید.');
            if((int)$before['active']!==$desired){$pdo->prepare('UPDATE print_agents SET active=? WHERE id=?')->execute([$desired,$id]);audit_log_write_strict($pdo,'print_agent_active_changed','print_agent',(string)$id,['name'=>$before['name'],'active_before'=>(int)$before['active'],'active_after'=>$desired,'policy'=>'drain_before_disable'],(int)current_user()['id']);}
            $pdo->commit();$notice=$desired?'رایانه چاپ فعال شد.':'رایانه چاپ غیرفعال شد.';
        } elseif ($action === 'delete_agent') {
            $id=(int)($_POST['agent_id']??0);if($id<1)throw new RuntimeException('رایانه چاپ معتبر نیست.');$actor=(int)current_user()['id'];
            $pdo->beginTransaction();
            // Global route lock for the rare retirement operation keeps the same
            // destination -> agent lock order used by save/promote and closes the
            // race where a destination could bind this agent while it retires.
            $routeLock=$pdo->query('SELECT destination_key FROM print_destinations WHERE active=1 ORDER BY destination_key FOR UPDATE');$routeLock->fetchAll();
            $st=$pdo->prepare('SELECT * FROM print_agents WHERE id=? FOR UPDATE');$st->execute([$id]);$agentRow=$st->fetch();if(!$agentRow)throw new RuntimeException('رایانه چاپ پیدا نشد.');
            $live=$pdo->prepare("SELECT destination_key,label FROM print_destinations WHERE active=1 AND (agent_id=? OR fallback_agent_id=?) ORDER BY destination_key");$live->execute([$id,$id]);$liveRows=$live->fetchAll();
            if($liveRows){$names=implode('، ',array_map(static fn(array $row): string=>(string)$row['label'],$liveRows));throw new RuntimeException('این رایانه هنوز در مسیر زنده استفاده می‌شود: '.$names.'. ابتدا مسیرها را انتقال دهید.');}
            $openCount=printing_agent_unsettled_attempt_count($pdo,$id,true);if($openCount>0)throw new RuntimeException('این رایانه اجرای چاپ یا گزارش تعیین‌تکلیف‌نشده دارد؛ پیش از بازنشستگی همه کارهای چاپ باز را تعیین تکلیف کنید.');
            $refs=0;foreach([
                ['SELECT COUNT(*) FROM print_attempts WHERE agent_id=?'],['SELECT COUNT(*) FROM print_claim_requests WHERE agent_id=?']
            ] as $spec){$q=$pdo->prepare($spec[0]);$q->execute([$id]);$refs+=(int)$q->fetchColumn();}
            if($refs>0){$pdo->prepare('UPDATE print_agents SET active=0,retired_at=COALESCE(retired_at,NOW()),retired_by_user_id=COALESCE(retired_by_user_id,?) WHERE id=?')->execute([$actor,$id]);audit_log_write_strict($pdo,'print_agent_retired','print_agent',$id,['name'=>(string)$agentRow['name'],'historical_references'=>$refs],$actor);$notice='رایانه چاپ بازنشسته و به آرشیو منتقل شد؛ تاریخچه حفظ شده است.';}
            else{$pdo->prepare('DELETE FROM print_agents WHERE id=?')->execute([$id]);audit_log_write_strict($pdo,'print_agent_deleted','print_agent',$id,['name'=>(string)$agentRow['name']],$actor);$notice='تعریف استفاده‌نشده رایانه چاپ حذف شد.';}
            $pdo->commit();
        } elseif ($action === 'create_destination') {
            $label = text_substr(trim((string)($_POST['label'] ?? '')), 0, 160);
            if ($label === '') throw new RuntimeException('نام مقصد را وارد کنید.');
            $key = 'prep_' . substr(bin2hex(random_bytes(6)), 0, 12);
            $pdo->prepare("INSERT INTO print_destinations(destination_key,label,destination_type,preparation_areas_json,active,paper_width_mm,printable_width_mm,copies,layout_mode) VALUES(?,?,'preparation',JSON_ARRAY(),0,80,72.1,1,'combined')")
                ->execute([$key,$label]);
            $notice = 'مقصد آماده‌سازی ساخته شد. بخش‌ها و پرینتر آن را تنظیم کنید.';
            audit_log_write('print_destination_created','print_destination',$key,['label'=>$label],(int)current_user()['id']);
        } elseif ($action === 'delete_destination') {
            $key = text_substr(trim((string)($_POST['destination_key'] ?? '')), 0, 40);
            if (in_array($key, ['customer_receipt','prep_shared'], true)) throw new RuntimeException('مقصدهای سیستمی قابل حذف نیستند؛ فقط تنظیم یا غیرفعال می‌شوند.');
            $pdo->beginTransaction();
            $destination = print_destination($pdo, $key, true);
            if (!$destination || (string)($destination['destination_type'] ?? '') !== 'preparation') throw new RuntimeException('مقصد آماده‌سازی سفارشی پیدا نشد.');
            $jobs = $pdo->prepare('SELECT COUNT(*) FROM print_jobs WHERE destination_key=?');
            $jobs->execute([$key]);
            if ((int)$jobs->fetchColumn() > 0) throw new RuntimeException('این مقصد سابقه چاپ دارد و برای حفظ Audit حذف فیزیکی نمی‌شود؛ آن را غیرفعال کنید.');
            $stmt = $pdo->prepare('DELETE FROM print_destinations WHERE destination_key=?');
            $stmt->execute([$key]);
            if (!$stmt->rowCount()) throw new RuntimeException('مقصد چاپ پیدا نشد.');
            audit_log_write('print_destination_deleted','print_destination',$key,['label'=>(string)$destination['label']],(int)current_user()['id']);
            $pdo->commit();
            $notice = 'مقصد آماده‌سازی حذف شد.';
        } elseif ($action === 'promote_fallback') {
            $key=text_substr(trim((string)($_POST['destination_key']??'')),0,40);$actor=(int)current_user()['id'];
            $expectedPrimary=(int)($_POST['expected_primary_agent_id']??0);$expectedFallback=(int)($_POST['expected_fallback_agent_id']??0);
            $expectedPrimaryQueue=text_substr(trim((string)($_POST['expected_primary_queue']??'')),0,190);$expectedFallbackQueue=text_substr(trim((string)($_POST['expected_fallback_queue']??'')),0,190);
            $pdo->beginTransaction();
            $ds=$pdo->prepare('SELECT * FROM print_destinations WHERE destination_key=? FOR UPDATE');$ds->execute([$key]);$destination=$ds->fetch();if(!$destination)throw new RuntimeException('مقصد چاپ پیدا نشد.');
            if((int)($destination['agent_id']??0)!==$expectedPrimary||(int)($destination['fallback_agent_id']??0)!==$expectedFallback||trim((string)($destination['windows_queue_name']??''))!==$expectedPrimaryQueue||trim((string)($destination['fallback_windows_queue_name']??''))!==$expectedFallbackQueue)throw new RuntimeException('مسیر چاپ هم‌زمان تغییر کرده است؛ صفحه را تازه کنید و دوباره اقدام کنید.');
            if($expectedFallback<1||$expectedFallbackQueue==='')throw new RuntimeException('مسیر جایگزین معتبری برای Promote تعریف نشده است.');
            $ids=array_values(array_unique(array_filter([$expectedPrimary,$expectedFallback],static fn(int $v): bool=>$v>0)));sort($ids,SORT_NUMERIC);$locked=[];
            foreach($ids as $aid){$as=$pdo->prepare('SELECT * FROM print_agents WHERE id=? FOR UPDATE');$as->execute([$aid]);$row=$as->fetch();if($row)$locked[$aid]=$row;}
            $fallbackAgent=$locked[$expectedFallback]??null;if(!$fallbackAgent)throw new RuntimeException('رایانه جایگزین پیدا نشد.');
            $fallbackHealth=printing_queue_health($fallbackAgent,$expectedFallbackQueue);if(!$fallbackHealth['ready'])throw new RuntimeException(printing_queue_readiness_error($fallbackHealth,$expectedFallbackQueue,'جایگزین'));
            $newFallbackId=null;$newFallbackQueue=null;
            if($expectedPrimary>0&&isset($locked[$expectedPrimary])&&$expectedPrimary!==$expectedFallback&&$expectedPrimaryQueue!==''){$oldPrimaryHealth=printing_queue_health($locked[$expectedPrimary],$expectedPrimaryQueue);if($oldPrimaryHealth['ready']){$newFallbackId=$expectedPrimary;$newFallbackQueue=$expectedPrimaryQueue;}}
            $before=['agent_id'=>$expectedPrimary?:null,'windows_queue_name'=>$expectedPrimaryQueue?:null,'fallback_agent_id'=>$expectedFallback,'fallback_windows_queue_name'=>$expectedFallbackQueue];
            $after=['agent_id'=>$expectedFallback,'windows_queue_name'=>$expectedFallbackQueue,'fallback_agent_id'=>$newFallbackId,'fallback_windows_queue_name'=>$newFallbackQueue];
            $pdo->prepare('UPDATE print_destinations SET agent_id=?,windows_queue_name=?,fallback_agent_id=?,fallback_windows_queue_name=? WHERE destination_key=?')->execute([$expectedFallback,$expectedFallbackQueue,$newFallbackId,$newFallbackQueue,$key]);
            audit_log_write_strict($pdo,'print_destination_fallback_promoted','print_destination',$key,['before'=>$before,'after'=>$after,'policy'=>$newFallbackId?'old_primary_if_ready':'drop_unready_old_primary'], $actor);
            $pdo->commit();$notice='مسیر جایگزین با موفقیت به مسیر اصلی تبدیل شد. تغییر فقط برای کارهای جدید است.';
        } elseif ($action === 'save_destination') {
            $key=text_substr(trim((string)($_POST['destination_key']??'')),0,40);$pdo->beginTransaction();$destination=print_destination($pdo,$key,true);if(!$destination)throw new RuntimeException('مقصد چاپ معتبر نیست.');
            $type=(string)$destination['destination_type'];$label=text_substr(trim((string)($_POST['label']??$destination['label'])),0,160);if($label==='')throw new RuntimeException('نام مقصد لازم است.');
            $agentId=(int)($_POST['agent_id']??0);$fallbackAgentId=(int)($_POST['fallback_agent_id']??0);if($fallbackAgentId>0&&$fallbackAgentId===$agentId)throw new RuntimeException('پرینتر اصلی و جایگزین نباید روی یک رایانه چاپ باشند.');
            $queue=text_substr(trim((string)($_POST['windows_queue_name']??'')),0,190);$fallbackQueue=text_substr(trim((string)($_POST['fallback_windows_queue_name']??'')),0,190);$active=isset($_POST['active'])?1:0;$required=isset($_POST['required_for_operation'])?1:0;
            $paperRaw=en_digits(trim((string)($_POST['paper_width_mm']??'80')));if(!ctype_digit($paperRaw))throw new RuntimeException('عرض رول معتبر نیست.');$paper=(int)$paperRaw;if(!in_array($paper,[58,80],true))throw new RuntimeException('عرض رول فقط ۵۸ یا ۸۰ میلی‌متر است.');
            $printRaw=en_digits(trim((string)($_POST['printable_width_mm']??'')));if(!preg_match('/^\d+(?:\.\d{1,2})?$/',$printRaw))throw new RuntimeException('عرض قابل چاپ باید عدد معتبر باشد.');$printable=(float)$printRaw;if(!is_finite($printable)||$printable<20||$printable>$paper)throw new RuntimeException('عرض قابل چاپ باید مثبت و از عرض رول بیشتر نباشد.');
            $copiesRaw=en_digits(trim((string)($_POST['copies']??'1')));if(!ctype_digit($copiesRaw)||!in_array((int)$copiesRaw,[1,2,3],true))throw new RuntimeException('تعداد نسخه فقط ۱ تا ۳ است.');$copies=(int)$copiesRaw;
            $areas=$type==='preparation'?printing_preparation_areas_from_post():[];
            if($active&&$agentId<1&&$fallbackAgentId<1)throw new RuntimeException('برای فعال‌سازی، حداقل یک رایانه چاپ اصلی یا جایگزین انتخاب کنید.');
            if($agentId>0&&$queue==='')throw new RuntimeException('برای رایانه اصلی، صف چاپ ویندوز لازم است.');if($fallbackAgentId>0&&$fallbackQueue==='')throw new RuntimeException('برای رایانه جایگزین، صف چاپ ویندوز لازم است.');
            if($active&&$type==='preparation'&&!$areas)throw new RuntimeException('برای مقصد آماده‌سازی حداقل یک بخش انتخاب کنید.');if($active&&$type==='preparation')printing_validate_area_uniqueness($pdo,$key,$areas);
            $agentSpecs=[];if($agentId>0)$agentSpecs[$agentId]=[$queue,'اصلی'];if($fallbackAgentId>0)$agentSpecs[$fallbackAgentId]=[$fallbackQueue,'جایگزین'];ksort($agentSpecs,SORT_NUMERIC);
            foreach($agentSpecs as $aid=>[$qname,$role]){$as=$pdo->prepare('SELECT * FROM print_agents WHERE id=? AND active=1 AND retired_at IS NULL FOR UPDATE');$as->execute([(int)$aid]);$ar=$as->fetch();if(!$ar)throw new RuntimeException('رایانه چاپ '.$role.' فعال نیست.');$qh=printing_queue_health($ar,$qname);if(!$qh['ready'])throw new RuntimeException(printing_queue_readiness_error($qh,$qname,$role));}
            $pdo->prepare('UPDATE print_destinations SET label=?,preparation_areas_json=?,agent_id=?,windows_queue_name=?,active=?,required_for_operation=?,paper_width_mm=?,printable_width_mm=?,copies=?,layout_mode=?,fallback_agent_id=?,fallback_windows_queue_name=? WHERE destination_key=?')
                ->execute([$label,$type==='preparation'?json_encode($areas,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR):null,$agentId?:null,$queue?:null,$active,$required,$paper,$printable,$copies,'combined',$fallbackAgentId?:null,$fallbackQueue?:null,$key]);
            print_reconcile_blocked_jobs($pdo,$key);audit_log_write_strict($pdo,'print_destination_updated','print_destination',$key,['active'=>(bool)$active,'required'=>(bool)$required,'areas'=>$areas,'agent_id'=>$agentId?:null,'fallback_agent_id'=>$fallbackAgentId?:null],(int)current_user()['id']);$pdo->commit();$notice='تنظیمات مقصد چاپ ذخیره شد.';
        } elseif ($action === 'test_print') {
            $key = (string)($_POST['destination_key'] ?? '');
            $destination = print_destination($pdo,$key);
            if (!$destination || !print_destination_operational_route($pdo,$key)) throw new RuntimeException('این مقصد در حال حاضر رایانه و پرینتر آماده ندارد.');
            if ((string)$destination['destination_type'] === 'customer') {
                $payload = ['document_kind'=>'customer_final','title'=>setting('cafe_name','سکنا'),'badge'=>'چاپ آزمایشی سند مشتری','table_name'=>'میز آزمایشی','invoice_number'=>'TEST-1','created_at'=>date(DATE_ATOM),'actor_name'=>(string)current_user()['display_name'],'sections'=>[['title'=>'شرح حساب','items'=>[['row_number'=>1,'name'=>'آیس لاته','quantity'=>2,'unit_price'=>210000,'line_total'=>420000]]]],'subtotal'=>420000,'discount'=>0,'total'=>420000,'currency'=>'تومان','show_prices'=>true,'footer'=>'چاپ آزمایشی'];
            } else {
                $areas = print_destination_areas($destination);
                $sections = [];
                if (in_array('kitchen',$areas,true)) $sections[]=['area_key'=>'kitchen','title'=>'آشپزخانه','items'=>[['name'=>'پاستا آلفردو','quantity'=>1,'note'=>'بدون پیاز']]];
                if (in_array('bar',$areas,true)) $sections[]=['area_key'=>'bar','title'=>'بار','items'=>[['name'=>'آیس لاته','quantity'=>2,'note'=>'کم یخ']]];
                $payload = ['document_kind'=>'preparation','title'=>'فیش آماده‌سازی','badge'=>'چاپ آزمایشی','status_label'=>'آزمایشی','table_name'=>'میز آزمایشی','order_number'=>'TEST-1','created_at'=>date(DATE_ATOM),'actor_name'=>(string)current_user()['display_name'],'customer_note'=>'این فیش فقط برای آزمون پرینتر است.','sections'=>$sections,'show_prices'=>false];
            }
            print_enqueue_job($pdo,'test_print',$key,$payload,'test.' . $key . '.' . bin2hex(random_bytes(6)),'print_destination',$key,(int)current_user()['id']);
            $notice = 'فیش آزمایشی وارد صف چاپ شد.';
        } elseif ($action === 'retry_job') {
            $id=(int)($_POST['job_id']??0);$reason=trim((string)($_POST['reason']??''));
            $pdo->beginTransaction();print_job_safe_retry($pdo,$id,(int)current_user()['id'],$reason,printing_action_request_id('safe_retry',$id));$pdo->commit();
            $notice='درخواست برای تلاش دوباره آماده شد.';
        } elseif ($action === 'hold_stale_ownership') {
            $id=(int)($_POST['job_id']??0);$reason=trim((string)($_POST['reason']??''));
            $pdo->beginTransaction();$target=print_job_hold_stale_ownership($pdo,$id,(int)current_user()['id'],$reason);$pdo->commit();
            $notice=$target==='unknown'?'چاپ شروع شده بود اما نتیجه آن مشخص نیست؛ بررسی انسانی لازم است.':'درخواست برای جلوگیری از چاپ تکراری متوقف شد و نیاز به بررسی دارد.';
            audit_log_write('print_job_stale_ownership_held','print_job',$id,['state'=>$target,'reason'=>$reason],(int)current_user()['id']);
        } elseif ($action === 'cancel_job') {
            $id=(int)($_POST['job_id']??0);$reason=trim((string)($_POST['reason']??''));
            $pdo->beginTransaction();print_job_cancel_unprinted($pdo,$id,(int)current_user()['id'],$reason);$pdo->commit();
            $notice='درخواست چاپ پیش از پذیرش بسته شد و دیگر خودکار چاپ نمی‌شود.';audit_log_write('print_job_cancelled','print_job',$id,['reason'=>$reason],(int)current_user()['id']);
        } elseif ($action === 'reprint_job') {
            $id=(int)($_POST['job_id']??0);$reason=trim((string)($_POST['reason']??'چاپ مجدد به درخواست مدیر'));
            $pdo->beginTransaction();$newJob=print_job_create_reprint($pdo,$id,(int)current_user()['id'],$reason,null,printing_action_request_id('reprint',$id));$pdo->commit();
            $notice=!empty($newJob['duplicate'])?'همان درخواست چاپ مجدد قبلی بازیابی شد.':'چاپ مجدد به‌عنوان درخواست مستقل وارد صف شد.';if(empty($newJob['duplicate']))audit_log_write('print_job_reprint_created','print_job',(string)$newJob['job_id'],['reprint_of_id'=>$id,'reason'=>$reason],(int)current_user()['id']);
        } elseif ($action === 'resolve_unknown_printed') {
            $id=(int)($_POST['job_id']??0);$note=trim((string)($_POST['resolution_note']??''));
            $pdo->beginTransaction();print_job_resolve_ambiguous($pdo,$id,(int)current_user()['id'],'human_confirmed_printed',$note);$pdo->commit();
            $notice='وضعیت مبهم با تأیید انسانی تعیین تکلیف شد.';audit_log_write('print_job_unknown_confirmed_printed','print_job',$id,['note'=>$note],(int)current_user()['id']);
        } elseif ($action === 'resolve_unknown_reprint') {
            $id=(int)($_POST['job_id']??0);$reason=trim((string)($_POST['reason']??''));if($reason==='')throw new RuntimeException('دلیل چاپ مجدد لازم است.');
            $pdo->beginTransaction();$newJob=print_job_create_reprint($pdo,$id,(int)current_user()['id'],$reason,null,printing_action_request_id('resolve_unknown_reprint',$id));$pdo->commit();
            $notice=!empty($newJob['duplicate'])?'همان چاپ مجدد قبلی بازیابی شد.':'وضعیت مبهم تعیین تکلیف و چاپ مجدد مستقل ساخته شد.';if(empty($newJob['duplicate']))audit_log_write('print_job_unknown_reprint_created','print_job',(string)$newJob['job_id'],['reprint_of_id'=>$id,'reason'=>$reason],(int)current_user()['id']);
        } elseif ($action === 'resolve_unknown_no_longer_needed') {
            $id=(int)($_POST['job_id']??0);$note=trim((string)($_POST['resolution_note']??''));
            $pdo->beginTransaction();print_job_resolve_ambiguous($pdo,$id,(int)current_user()['id'],'human_no_longer_needed',$note);$pdo->commit();
            $notice='این چاپ بدون ایجاد چاپ مجدد بسته شد و صف مقصد آزاد شد.';audit_log_write('print_job_unknown_closed_no_longer_needed','print_job',$id,['note'=>$note],(int)current_user()['id']);
        } elseif ($action === 'reroute_job') {
            $id=(int)($_POST['job_id']??0);$destinationKey=(string)($_POST['new_destination_key']??'');$reason=trim((string)($_POST['reason']??''));
            $pdo->beginTransaction();print_job_reroute_unprinted($pdo,$id,$destinationKey,(int)current_user()['id'],$reason,printing_action_request_id('reroute',$id));$pdo->commit();
            $notice='مسیر درخواست چاپ تغییر کرد.';
        } else {
            throw new RuntimeException('عملیات چاپ شناخته نشد.');
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('printing admin action: '.$e->getMessage());
        $error = safe_business_error_message($e, 'این عملیات چاپ انجام نشد.');
    }
    if($issuedToken!=='') $_SESSION['printing_issued_token']=$issuedToken;
    if($notice!=='') flash('success',$notice);
    if($error!=='') flash('error',$error);
    printing_redirect($tab);
}

$allAgents = $pdo->query('SELECT * FROM print_agents ORDER BY id DESC')->fetchAll();
$agents=array_values(array_filter($allAgents,static fn(array $a): bool=>empty($a['retired_at'])));
$retiredAgents=array_values(array_filter($allAgents,static fn(array $a): bool=>!empty($a['retired_at'])));
$routeEligibleAgents=array_values(array_filter($agents,static fn(array $a): bool=>(int)($a['active']??0)===1));
$destinations = $pdo->query("SELECT d.*,pa.name agent_name,fa.name fallback_agent_name FROM print_destinations d LEFT JOIN print_agents pa ON pa.id=d.agent_id LEFT JOIN print_agents fa ON fa.id=d.fallback_agent_id ORDER BY FIELD(d.destination_type,'customer','preparation'),d.destination_key")->fetchAll();
$destinationJobCounts=[];foreach($pdo->query("SELECT destination_key,COUNT(*) job_count FROM print_jobs GROUP BY destination_key")->fetchAll() as $row){$destinationJobCounts[(string)$row['destination_key']]=(int)$row['job_count'];}
$queueBlockers=[];$blockerSql="SELECT j.id,j.destination_key,j.status,j.blocked_reason,j.last_error_code FROM print_jobs j JOIN (SELECT destination_key,MIN(id) first_id FROM print_jobs WHERE NOT(status IN('submitted','cancelled') OR (status IN('failed','unknown','recovery_hold') AND resolved_at IS NOT NULL)) GROUP BY destination_key) b ON b.first_id=j.id";foreach($pdo->query($blockerSql)->fetchAll() as $row){$queueBlockers[(string)$row['destination_key']]=$row;}
$jobSelect="SELECT j.*,d.label destination_label,u.display_name requested_by FROM print_jobs j LEFT JOIN print_destinations d ON d.destination_key=j.destination_key LEFT JOIN users u ON u.id=j.requested_by_user_id";
$problemSql=print_open_problem_sql('j');$problemCount=(int)$pdo->query("SELECT COUNT(*) FROM print_jobs j WHERE $problemSql")->fetchColumn();$problemPage=max(1,(int)($_GET['problem_page']??1));$problemPerPage=20;$problemPages=max(1,(int)ceil($problemCount/$problemPerPage));$problemPage=min($problemPage,$problemPages);$problemOffset=($problemPage-1)*$problemPerPage;
$problemJobs=$pdo->query($jobSelect." WHERE $problemSql ORDER BY j.id DESC LIMIT 20 OFFSET $problemOffset")->fetchAll();
$queueFilter=in_array((string)($_GET['queue_filter']??'all'),['all','waiting','submitted','cancelled'],true)?(string)($_GET['queue_filter']??'all'):'all';
$queuePage=max(1,(int)($_GET['queue_page']??1));$queuePerPage=20;$recentWhere="NOT ($problemSql)";
if($queueFilter==='waiting')$recentWhere.=" AND j.status IN('pending','reserved','claimed')";elseif($queueFilter==='submitted')$recentWhere.=" AND j.status='submitted'";elseif($queueFilter==='cancelled')$recentWhere.=" AND j.status='cancelled'";
$recentTotal=(int)$pdo->query("SELECT COUNT(*) FROM print_jobs j WHERE $recentWhere")->fetchColumn();$queuePages=max(1,(int)ceil($recentTotal/$queuePerPage));$queuePage=min($queuePage,$queuePages);$queueOffset=($queuePage-1)*$queuePerPage;$recentJobs=$pdo->query($jobSelect." WHERE $recentWhere ORDER BY j.id DESC LIMIT 20 OFFSET $queueOffset")->fetchAll();
$jobs=array_merge($problemJobs,$recentJobs);$attemptsByJob=[];$visibleJobIds=array_values(array_unique(array_map(static fn($r)=>(int)$r['id'],$jobs)));if($visibleJobIds){$ph=implode(',',array_fill(0,count($visibleJobIds),'?'));$ast=$pdo->prepare("SELECT a.*,pa.name agent_name FROM print_attempts a LEFT JOIN print_agents pa ON pa.id=a.agent_id WHERE a.job_id IN ($ph) ORDER BY a.job_id,a.attempt_no DESC");$ast->execute($visibleJobIds);foreach($ast->fetchAll() as $a){$attemptsByJob[(int)$a['job_id']][]=$a;}}
$printerLists=[];foreach($agents as $agent){$decoded=json_decode((string)($agent['printers_json']??'[]'),true);$printerLists[(int)$agent['id']]=is_array($decoded)?$decoded:[];}$statusLabels=print_job_status_labels();$summary=print_queue_summary($pdo);
$agentsById=[];$onlineAgents=0;foreach($agents as $agent){$agentsById[(int)$agent['id']]=$agent;if(print_agent_online($agent))$onlineAgents++;}
$readyDestinations=0;foreach($destinations as $destination){if((int)$destination['active']===1&&print_destination_operational_route($pdo,(string)$destination['destination_key']))$readyDestinations++;}
$requiredDestinations=array_values(array_filter($destinations,static fn(array $d): bool=>(int)($d['active']??0)===1&&(int)($d['required_for_operation']??1)===1));$readyRequired=0;foreach($requiredDestinations as $destination){if(print_destination_operational_route($pdo,(string)$destination['destination_key']))$readyRequired++;}
$printerData=[];foreach($printerLists as $agentId=>$list){$printerData[(string)$agentId]=array_values(array_map(static function($p):array{return ['name'=>(string)($p['name']??''),'default'=>(bool)($p['default']??false),'offline'=>(bool)($p['offline']??false),'status'=>(string)($p['status']??'')];},array_filter($list,static fn($p):bool=>is_array($p)&&trim((string)($p['name']??''))!=='')));}
$setupComplete=count($requiredDestinations)>0&&$readyRequired===count($requiredDestinations);if($tabInput===''&&!$setupComplete)$tab='settings';$agentUpdateCount=0;foreach($agents as $agent)if(print_agent_needs_update($agent))$agentUpdateCount++;
$overallHealthy=$setupComplete&&$problemCount===0;$overallClass=$overallHealthy?'is-ok':($problemCount>0||!$setupComplete?'is-warn':'is-off');$overallLabel=!$setupComplete?'راه‌اندازی مسیرهای ضروری چاپ کامل نشده':($problemCount>0?'چاپ نیازمند رسیدگی است':'چاپ آماده است');$lastSubmittedAt=(string)($pdo->query("SELECT COALESCE(MAX(submitted_at),'') FROM print_jobs WHERE status='submitted'")->fetchColumn()?:'');

$jobTitle=static function(array $job): string { return match((string)$job['job_type']){'customer_final'=>'سند مشتری','prep_order'=>'فیش آماده‌سازی','prep_reprint'=>'چاپ مجدد آماده‌سازی','prep_adjustment'=>'اصلاحیه آماده‌سازی','prep_cancel'=>'لغو آماده‌سازی','test_print'=>'چاپ آزمایشی',default=>'درخواست چاپ'}; };
$humanStatus=static function(array $job): string { return match((string)$job['status']){'submitted'=>'تحویل به صف چاپ رایانه','pending'=>'در صف چاپ','blocked'=>'نیازمند تنظیم','reserved'=>'رزرو موقت','claimed'=>'تحویل به رایانه چاپ','failed'=>'خطای پیش از ارسال','unknown'=>'نتیجه نامشخص','recovery_hold'=>'نیاز به بررسی','cancelled'=>'لغوشده',default=>'در حال بررسی'}; };
$attemptState=static function(string $state): string { return match($state){'reserved'=>'رزرو برای رایانه','claimed'=>'دریافت توسط رایانه','started'=>'شروع چاپ','submitted'=>'تحویل به صف چاپ رایانه','failed'=>'خطای پیش از ارسال','unknown'=>'نتیجه نامشخص','recovery_hold'=>'نیاز به بررسی','expired'=>'مهلت دریافت پایان یافت',default=>'وضعیت داخلی'}; };
$renderProblemJobs=static function(array $list) use($jobTitle,$humanStatus,$attemptState,$attemptsByJob,$destinations): void { foreach($list as $job){ $status=(string)$job['status']; $resolved=!empty($job['resolved_at']); ?>
  <article class="print5-job-row is-problem">
    <header><div><strong><?= e($jobTitle($job)) ?><?= $job['reprint_of_id']?' · چاپ مجدد':'' ?></strong><span><?= e(format_jalali_compact((string)$job['created_at'])) ?></span></div><span class="badge badge-failed"><?= e($humanStatus($job)) ?><?= $resolved?' · تعیین تکلیف‌شده':'' ?></span></header>
    <div class="print5-job-human-meta"><span><?= e((string)($job['destination_label']?:'مقصد چاپ')) ?></span><span><?= e((string)($job['requested_by']?:'خودکار')) ?></span><?php if((int)$job['attempt_count']>1): ?><span><?= fa_digits((int)$job['attempt_count']) ?> تلاش</span><?php endif; ?><?php if((int)($job['required']??0)===1): ?><span>چاپ الزامی</span><?php endif; ?></div>
    <?php if(!$resolved&&($job['last_error']||$status==='blocked')): ?><div class="print5-job-error"><?= e(print_job_error_human((string)($job['blocked_reason']?:$job['last_error_code']??''),(string)($job['last_error']??''))) ?></div><?php endif; ?>
    <div class="print5-job-actions">
      <?php if(in_array($status,['failed','blocked'],true)): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="return_tab" value="overview"><input type="hidden" name="action" value="retry_job"><input type="hidden" name="job_id" value="<?= (int)$job['id'] ?>"><input type="hidden" name="action_request_id" value="<?= e(printing_new_action_request_id('safe_retry',(int)$job['id'])) ?>"><input type="hidden" name="reason" value="تلاش مجدد پس از بررسی مدیر"><button class="btn btn-sm btn-primary">تلاش دوباره</button></form><?php endif; ?>
      <?php if(in_array($status,['unknown','recovery_hold'],true)&&!$resolved): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="return_tab" value="overview"><input type="hidden" name="action" value="resolve_unknown_printed"><input type="hidden" name="job_id" value="<?= (int)$job['id'] ?>"><button class="btn btn-sm btn-light">چاپ انجام شده است</button></form><form method="post" class="print5-reprint-reason" data-confirm="این کار چاپ مجدد ایجاد نمی‌کند و فقط وضعیت مبهم را می‌بندد. اگر برگه قبلاً وارد Spooler شده باشد ممکن است هنوز چاپ فیزیکی انجام شود." data-confirm-title="این چاپ دیگر لازم نیست؟" data-confirm-ok="بستن بدون چاپ مجدد"><?= csrf_field() ?><input type="hidden" name="return_tab" value="overview"><input type="hidden" name="action" value="resolve_unknown_no_longer_needed"><input type="hidden" name="job_id" value="<?= (int)$job['id'] ?>"><input class="form-control" name="resolution_note" required maxlength="500" placeholder="مثلاً سفارش دستی انجام شد"><button class="btn btn-sm btn-outline">دیگر نیاز به چاپ نیست</button></form><form method="post" class="print5-reprint-reason"><?= csrf_field() ?><input type="hidden" name="return_tab" value="overview"><input type="hidden" name="action" value="resolve_unknown_reprint"><input type="hidden" name="job_id" value="<?= (int)$job['id'] ?>"><input type="hidden" name="action_request_id" value="<?= e(printing_new_action_request_id('resolve_unknown_reprint',(int)$job['id'])) ?>"><input class="form-control" name="reason" required maxlength="300" placeholder="دلیل چاپ مجدد"><button class="btn btn-sm btn-primary">چاپ مجدد مستقل</button></form><?php endif; ?>
      <?php if($status==='claimed'&&!empty($job['claimed_at'])&&strtotime((string)$job['claimed_at'])<time()-print_job_stale_ownership_threshold_seconds()): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="return_tab" value="overview"><input type="hidden" name="action" value="hold_stale_ownership"><input type="hidden" name="job_id" value="<?= (int)$job['id'] ?>"><input type="hidden" name="reason" value="درخواست بیش از حد انتظار روی رایانه چاپ بدون پیشرفت مانده است"><button class="btn btn-sm btn-outline">توقف بازیابی خودکار</button></form><?php endif; ?>
    </div>
    <details class="print5-job-details"><summary>جزئیات و سابقه چاپ</summary><dl><dt>Job</dt><dd>#<?= fa_digits((int)$job['id']) ?></dd><dt>نوع</dt><dd dir="ltr"><?= e((string)$job['job_type']) ?></dd><dt>مقصد</dt><dd dir="ltr"><?= e((string)$job['destination_key']) ?></dd><dt>Hash</dt><dd dir="ltr"><?= e(substr((string)($job['content_sha256']??''),0,16)) ?>…</dd><?php if($job['last_error']): ?><dt>خطای فنی</dt><dd dir="ltr"><?= e((string)$job['last_error']) ?></dd><?php endif; ?></dl><?php $attempts=$attemptsByJob[(int)$job['id']]??[]; if($attempts): ?><div class="print5-attempt-timeline"><?php foreach($attempts as $a): ?><div><strong>تلاش <?= fa_digits((int)$a['attempt_no']) ?> · <?= e($attemptState((string)$a['state'])) ?></strong><span><?= e((string)($a['agent_name']?:'رایانه چاپ')) ?><?= $a['spooler_job_id']?' · شناسه چاپ ویندوز #'.e((string)$a['spooler_job_id']):'' ?></span></div><?php endforeach; ?></div><?php endif; ?><?php if(in_array($status,['pending','blocked','failed','reserved'],true)): ?><div class="print5-job-management"><?php if(in_array($status,['pending','blocked','failed'],true)): ?><form method="post" class="print5-reroute-form"><?= csrf_field() ?><input type="hidden" name="return_tab" value="overview"><input type="hidden" name="action" value="reroute_job"><input type="hidden" name="job_id" value="<?= (int)$job['id'] ?>"><input type="hidden" name="action_request_id" value="<?= e(printing_new_action_request_id('reroute',(int)$job['id'])) ?>"><select class="form-control" name="new_destination_key" data-choice-mode="browse" required><?php foreach($destinations as $d): ?><option value="<?= e((string)$d['destination_key']) ?>" <?= (string)$d['destination_key']===(string)$job['destination_key']?'selected':'' ?>><?= e((string)$d['label']) ?></option><?php endforeach; ?></select><input class="form-control" name="reason" required maxlength="300" placeholder="دلیل تغییر مسیر"><button class="btn btn-sm btn-light">تغییر مسیر</button></form><?php endif; ?><form method="post" data-confirm="این درخواست فقط اگر هنوز برای اجرا توسط رایانه چاپ پذیرفته نشده باشد بسته می‌شود و دیگر خودکار چاپ نخواهد شد." data-confirm-title="لغو درخواست چاپی که دیگر لازم نیست؟" data-confirm-ok="بستن درخواست" data-confirm-danger="1"><?= csrf_field() ?><input type="hidden" name="return_tab" value="overview"><input type="hidden" name="action" value="cancel_job"><input type="hidden" name="job_id" value="<?= (int)$job['id'] ?>"><input class="form-control" name="reason" maxlength="500" placeholder="اختیاری: مثلاً سفارش دستی انجام شد"><button class="btn btn-sm btn-outline-danger">دیگر نیاز به چاپ نیست</button></form></div><?php endif; ?></details>
  </article>
<?php }};
$renderRecentJobs=static function(array $list) use($jobTitle,$humanStatus,$queueBlockers): void { foreach($list as $job){ $status=(string)$job['status']; $stateClass=$status==='submitted'?'badge-posted':(in_array($status,['pending','reserved','claimed'],true)?'badge-pending':'badge-failed');$blocker=$queueBlockers[(string)$job['destination_key']]??null;$isBehind=$blocker&&in_array($status,['pending','reserved','claimed'],true)&&(int)$blocker['id']<(int)$job['id'];$blockerProblem=$isBehind&&in_array((string)$blocker['status'],['blocked','failed','unknown','recovery_hold'],true); ?>
  <article class="print5-activity-row"><span class="print5-activity-mark <?= e($stateClass) ?>" aria-hidden="true"></span><div><strong><?= e($jobTitle($job)) ?></strong><small><?= e((string)($job['destination_label']?:'مقصد چاپ')) ?> · <?= e(format_jalali_compact((string)$job['created_at'])) ?></small><?php if($isBehind): ?><small class="print5-activity-blocker <?= $blockerProblem?'is-problem':'' ?>"><?= $blockerProblem?'صف این مقصد توسط':'در انتظار' ?> Job #<?= fa_digits((int)$blocker['id']) ?> · <?= e($humanStatus($blocker)) ?></small><?php endif; ?></div><span class="badge <?= e($stateClass) ?>"><?= e($humanStatus($job)) ?></span></article>
<?php }};

panel_header('چاپ و پرینترها', 'printing');
?>

<?php if($issuedToken): ?>
<section class="print5-token" aria-labelledby="printTokenTitle"><div><strong id="printTokenTitle">کلید اتصال رایانه چاپ آماده است</strong><span>این کلید فقط همین بار نمایش داده می‌شود. آن را در برنامه چاپ ویندوز وارد کنید.</span></div><div class="print5-token-value"><code id="printAgentToken" dir="ltr"><?= e($issuedToken) ?></code><button class="btn btn-light" type="button" data-copy-print-token>کپی کلید</button></div></section>
<?php endif; ?>

<nav class="panel-primary-tabs print5-tabs" aria-label="بخش‌های چاپ">
  <a href="<?= e(asset('admin/printing.php?tab=overview')) ?>" class="<?= $tab==='overview'?'is-active':'' ?>" <?= $tab==='overview'?'aria-current="page"':'' ?>><?= ui_icon('dashboard') ?> نمای کلی<?php if($problemCount): ?><span class="print5-tab-count"><?= fa_digits($problemCount) ?></span><?php endif; ?></a>
  <a href="<?= e(asset('admin/printing.php?tab=settings')) ?>" class="<?= $tab==='settings'?'is-active':'' ?>" <?= $tab==='settings'?'aria-current="page"':'' ?>><?= ui_icon('settings') ?> تنظیمات چاپ</a>
  <a href="<?= e(asset('admin/printing.php?tab=diagnostics')) ?>" class="<?= $tab==='diagnostics'?'is-active':'' ?>" <?= $tab==='diagnostics'?'aria-current="page"':'' ?>><?= ui_icon('operations') ?> عیب‌یابی</a>
</nav>

<section class="print5-release-strip <?= $agentUpdateCount>0?'has-update':'' ?>">
  <div><span class="print5-status-dot <?= ($agentUpdateCount>0||$agentReleaseStale)?'is-warn':'is-ok' ?>" aria-hidden="true"></span><div><strong>برنامه چاپ ویندوز <?= e((string)$agentRelease['version']) ?></strong><small><?= $agentUpdateCount>0?fa_digits($agentUpdateCount).' رایانه نسخه قدیمی‌تری گزارش کرده است':($agentReleaseStale?'اطلاعات ذخیره‌شده آخرین نسخه پایدار؛ GitHub فعلاً پاسخ معتبر نداده است':'آخرین نسخه پایدار سرویس چاپ ویندوز منتشرشده در GitHub') ?></small></div></div>
  <div class="actions"><a class="btn btn-primary" href="<?= e(print_agent_download_url()) ?>"><?= ui_icon('download') ?> دانلود برنامه چاپ <?= e((string)$agentRelease['version']) ?></a><a class="btn btn-light" href="<?= e(print_agent_release_page_url()) ?>" target="_blank" rel="noopener noreferrer">جزئیات نسخه</a></div>
</section>

<?php if($tab==='overview'): ?>
<section class="print5-status-strip <?= e($overallClass) ?>" aria-label="وضعیت کلی چاپ" data-print-live-status data-snapshot-url="<?= e(asset('api/print_status_snapshot.php')) ?>">
  <div class="print5-status-main"><span class="print5-health-icon <?= e($overallClass) ?>" aria-hidden="true"></span><div><strong data-print-live-label><?= e($overallLabel) ?></strong><small data-print-live-last><?= $lastSubmittedAt!==''?'آخرین تحویل موفق به صف چاپ: '.e(format_jalali_compact($lastSubmittedAt)):'هنوز تحویل موفقی به صف چاپ در فعالیت اخیر ثبت نشده' ?></small></div></div>
  <div class="print5-status-facts"><span><b data-print-live-agents><?= fa_digits($onlineAgents) ?>/<?= fa_digits(count($agents)) ?></b> رایانه چاپ آنلاین</span><span><b data-print-live-destinations><?= fa_digits($readyDestinations) ?>/<?= fa_digits(count($destinations)) ?></b> مقصد آماده</span><span class="<?= $problemCount?'is-problem':'' ?>"><b data-print-live-problems><?= fa_digits($problemCount) ?></b> نیازمند رسیدگی</span></div>
  <a class="btn btn-light" href="<?= e(asset('admin/printing.php?tab=overview')) ?>"><?= ui_icon('refresh') ?> تازه‌سازی</a>
</section>

<?php if(!$setupComplete): ?><section class="print5-setup-callout"><div><?= ui_icon('warning') ?><div><strong>راه‌اندازی چاپ کامل نشده است</strong><span>ابتدا برنامه چاپ ویندوز را نصب و رایانه چاپ را متصل کنید و برای همه مقصدهای فعال، پرینتر ویندوز انتخاب کنید.</span></div></div><a class="btn btn-primary" href="<?= e(asset('admin/printing.php?tab=settings')) ?>">تکمیل تنظیمات</a></section><?php endif; ?>

<div class="print5-overview-grid">
  <main class="print5-overview-main">
    <section class="card print5-ops-card">
      <div class="card-head"><div class="panel-copy-stack"><strong>نیازمند رسیدگی</strong><small class="muted">ابهام یا خطاهای چاپ قبل از چاپ مجدد باید تعیین تکلیف شوند.</small></div><?php if($problemCount): ?><span class="badge badge-failed"><?= fa_digits($problemCount) ?></span><?php endif; ?></div>
      <div class="print5-job-list"><?php if($problemJobs): $renderProblemJobs($problemJobs); else: ?><div class="print5-all-clear"><?= ui_icon('check') ?><div><strong>مورد باز برای رسیدگی وجود ندارد</strong><span>صف چاپ در حال حاضر خطا یا وضعیت مبهم گزارش نمی‌کند.</span></div></div><?php endif; ?></div>
    </section>
    <section class="card print5-ops-card" id="print-activity">
      <div class="card-head"><div class="panel-copy-stack"><strong>فعالیت اخیر</strong><small class="muted">«تحویل به صف چاپ» تأیید خروج فیزیکی کاغذ نیست.</small></div><span class="muted"><?= fa_digits($recentTotal) ?> درخواست</span></div>
      <form method="get" class="print5-queue-filter"><input type="hidden" name="tab" value="overview"><label><span>وضعیت</span><select class="form-control" name="queue_filter" data-choice-mode="embedded"><option value="all" <?= $queueFilter==='all'?'selected':'' ?>>همه وضعیت‌ها</option><option value="waiting" <?= $queueFilter==='waiting'?'selected':'' ?>>در جریان</option><option value="submitted" <?= $queueFilter==='submitted'?'selected':'' ?>>تحویل به صف چاپ</option><option value="cancelled" <?= $queueFilter==='cancelled'?'selected':'' ?>>لغوشده</option></select></label><button class="btn btn-light">اعمال</button></form>
      <div class="print5-activity-list"><?php if($recentJobs): $renderRecentJobs($recentJobs); else: ?><div class="empty-state">درخواست چاپی برای این فیلتر وجود ندارد.</div><?php endif; ?></div>
      <?php if($queuePages>1): ?><nav class="panel-pagination" aria-label="صفحه‌های فعالیت چاپ"><a class="btn btn-sm btn-light" href="?<?= e(http_build_query(['tab'=>'overview','queue_filter'=>$queueFilter,'queue_page'=>max(1,$queuePage-1)])) ?>#print-activity" aria-disabled="<?= $queuePage<=1?'true':'false' ?>">قبلی</a><span>صفحه <?= fa_digits($queuePage) ?> از <?= fa_digits($queuePages) ?></span><a class="btn btn-sm btn-light" href="?<?= e(http_build_query(['tab'=>'overview','queue_filter'=>$queueFilter,'queue_page'=>min($queuePages,$queuePage+1)])) ?>#print-activity" aria-disabled="<?= $queuePage>=$queuePages?'true':'false' ?>">بعدی</a></nav><?php endif; ?>
    </section>
  </main>
  <aside class="print5-overview-side">
    <section class="card print5-infra-card"><div class="card-head"><div class="panel-copy-stack"><strong>زیرساخت چاپ</strong><small class="muted">خلاصه رایانه‌ها و مسیرهای چاپ</small></div><a class="panel-text-action" href="<?= e(asset('admin/printing.php?tab=settings')) ?>">مدیریت</a></div><div class="print5-infra-list">
      <?php if(!$agents): ?><div class="empty-state">رایانه چاپ متصل نشده است.</div><?php else: foreach($agents as $agent): $ah=printing_agent_health($agent); ?><div class="print5-infra-row"><span class="print5-status-dot <?= e(printing_agent_health_class((string)$ah['state'])) ?>"></span><div><strong><?= e((string)$agent['name']) ?></strong><small><?= e((string)($agent['hostname']?:'هنوز متصل نشده')) ?> · نسخه <?= e((string)($agent['agent_version']?:'—')) ?></small></div><span><?= e((string)$ah['label']) ?></span></div><?php endforeach; endif; ?>
    </div><div class="print5-infra-subhead">مقصدها</div><div class="print5-infra-list"><?php if(!$destinations): ?><div class="empty-state">مقصد چاپی تعریف نشده است.</div><?php else: foreach($destinations as $destination): $route=print_destination_operational_route($pdo,(string)$destination['destination_key']);$routeReady=$route!==null;$routeAgent=$routeReady?(string)$route['agent_name']:(string)($destination['agent_name']?:'بدون رایانه چاپ');$routeQueue=$routeReady?(string)$route['windows_queue_name']:(string)($destination['windows_queue_name']??'');$routeState=$routeReady?(($route['role']==='fallback'?'آماده · جایگزین':'آماده')):((int)$destination['active']===1?'نیازمند بررسی':'غیرفعال'); ?><div class="print5-infra-row"><span class="print5-status-dot <?= $routeReady?'is-ok':'is-warn' ?>"></span><div><strong><?= e((string)$destination['label']) ?></strong><small><?= e($routeAgent) ?> · <?= e($routeQueue?:'پرینتر انتخاب نشده') ?></small></div><span><?= e($routeState) ?></span></div><?php endforeach; endif; ?></div></section>
    <section class="card print5-side-actions"><a href="<?= e(asset('admin/printing.php?tab=diagnostics')) ?>"><?= ui_icon('operations') ?><div><strong>عیب‌یابی چاپ</strong><span>API، ارتباط رایانه چاپ، پرینتر و چاپ آزمایشی</span></div><?= ui_icon('chevron-left') ?></a><a href="<?= e(asset('admin/print_templates.php')) ?>"><?= ui_icon('print') ?><div><strong>قالب‌های چاپ</strong><span>پیش‌نمایش و نسخه‌بندی قالب‌ها</span></div><?= ui_icon('chevron-left') ?></a></section>
  </aside>
</div>

<?php elseif($tab==='settings'): ?>
<section class="card print5-section" id="print-agents"><div class="card-head"><div class="panel-copy-stack"><strong>رایانه‌های چاپ</strong><small class="muted">رایانه‌های چاپ را فقط هنگام نصب، تعویض کلید یا تغییر دسترسی مدیریت کنید.</small></div></div><div class="card-body">
  <details class="print5-create-panel" <?= !$agents?'open':'' ?>><summary><?= ui_icon('plus') ?> افزودن رایانه چاپ جدید</summary><form method="post" class="print5-create-agent"><?= csrf_field() ?><input type="hidden" name="action" value="create_agent"><input type="hidden" name="return_tab" value="settings"><label><span>نام قابل تشخیص</span><input class="form-control" name="name" value="صندوق سکنا" maxlength="120" placeholder="مثلاً صندوق اصلی" required></label><button class="btn btn-primary">ساخت کلید اتصال</button></form></details>
  <?php if(!$agents): ?><div class="empty-state print5-empty">هنوز رایانه چاپی تعریف نشده است. برنامه چاپ <?= e(print_agent_recommended_version()) ?> را نصب کنید و کلید اتصال ساخته‌شده را در برنامه وارد کنید.</div><?php else: ?><div class="print5-agent-list"><?php foreach($agents as $agent): $ah=printing_agent_health($agent);$list=$printerLists[(int)$agent['id']]??[]; ?><article class="print5-agent-row is-settings"><div class="print5-agent-main"><span class="print5-status-dot <?= e(printing_agent_health_class((string)$ah['state'])) ?>"></span><div><strong><?= e((string)$agent['name']) ?></strong><span><?= e((string)($agent['hostname']?:'هنوز به ویندوز متصل نشده')) ?></span></div></div><div class="print5-agent-facts"><div><small>وضعیت</small><strong><?= e((string)$ah['label']) ?></strong></div><div><small>نسخه</small><strong dir="ltr"><?= e((string)($agent['agent_version']?:'—')) ?></strong></div><div><small>پرینترها</small><strong><?= fa_digits(count($list)) ?></strong></div><div><small>آخرین ارتباط</small><strong><?= $agent['last_seen_at']?e(format_jalali_compact($agent['last_seen_at'])):'—' ?></strong></div></div><details class="print5-details"><summary>مدیریت رایانه چاپ</summary><div class="print5-detail-actions"><span class="muted">نشانه کلید: <b dir="ltr">…<?= e((string)$agent['token_hint']) ?></b><?= print_agent_needs_update($agent)?' · نسخه جدید برنامه چاپ آماده است':'' ?></span><div class="actions"><form method="post" data-confirm="کلید اتصال قبلی این رایانه بلافاصله باطل می‌شود." data-confirm-title="تعویض کلید اتصال؟" data-confirm-ok="تعویض کلید"><?= csrf_field() ?><input type="hidden" name="return_tab" value="settings"><input type="hidden" name="action" value="rotate_agent"><input type="hidden" name="agent_id" value="<?= (int)$agent['id'] ?>"><button class="btn btn-sm btn-light">تعویض کلید</button></form><form method="post"><?= csrf_field() ?><input type="hidden" name="return_tab" value="settings"><input type="hidden" name="action" value="set_agent_active"><input type="hidden" name="agent_id" value="<?= (int)$agent['id'] ?>"><input type="hidden" name="desired_active" value="<?= (int)$agent['active']===1?'0':'1' ?>"><button class="btn btn-sm btn-outline"><?= (int)$agent['active']?'غیرفعال‌کردن':'فعال‌کردن' ?></button></form><form method="post" data-confirm="این رایانه چاپ حذف می‌شود؛ مقصدهای وابسته تا انتخاب رایانه جدید آماده چاپ نخواهند بود." data-confirm-title="حذف رایانه چاپ؟" data-confirm-ok="حذف رایانه" data-confirm-danger="1"><?= csrf_field() ?><input type="hidden" name="return_tab" value="settings"><input type="hidden" name="action" value="delete_agent"><input type="hidden" name="agent_id" value="<?= (int)$agent['id'] ?>"><button class="btn btn-sm btn-outline-danger">حذف</button></form></div></div></details></article><?php endforeach; ?></div><?php endif; ?>
</div></section>

<?php if($retiredAgents): ?><section class="card print5-section" id="print-retired-agents"><div class="card-head"><div class="panel-copy-stack"><strong>آرشیو رایانه‌های چاپ</strong><small class="muted">این موارد فقط برای تاریخچه نمایش داده می‌شوند و قابل فعال‌سازی، تعویض کلید یا انتخاب در مسیر نیستند.</small></div></div><div class="card-body"><div class="print5-agent-list"><?php foreach($retiredAgents as $agent): ?><article class="print5-agent-row is-settings"><div class="print5-agent-main"><span class="print5-status-dot is-off"></span><div><strong><?= e((string)$agent['name']) ?></strong><span><?= e((string)($agent['hostname']?:'بدون hostname')) ?></span></div></div><div class="print5-agent-facts"><div><small>نسخه آخر</small><strong dir="ltr"><?= e((string)($agent['agent_version']?:'—')) ?></strong></div><div><small>بازنشستگی</small><strong><?= !empty($agent['retired_at'])?e(format_jalali_compact((string)$agent['retired_at'])):'—' ?></strong></div><div><small>آخرین Heartbeat</small><strong><?= !empty($agent['last_heartbeat_at'])?e(format_jalali_compact((string)$agent['last_heartbeat_at'])):'—' ?></strong></div><div><small>آخرین ارتباط</small><strong><?= !empty($agent['last_seen_at'])?e(format_jalali_compact((string)$agent['last_seen_at'])):'—' ?></strong></div></div></article><?php endforeach; ?></div></div></section><?php endif; ?>

<section class="card print5-section" id="print-destinations"><div class="card-head"><div class="panel-copy-stack"><strong>مسیرهای چاپ</strong><small class="muted">حالت عادی فقط خلاصه را نشان می‌دهد؛ برای تغییر رایانه یا پرینتر، همان مقصد را باز کنید.</small></div></div><div class="card-body"><div class="print5-destination-list">
<?php foreach($destinations as $destination): $key=(string)$destination['destination_key'];$type=(string)$destination['destination_type'];$areas=print_destination_areas($destination);$primaryId=(int)($destination['agent_id']??0);$currentQueue=(string)($destination['windows_queue_name']??'');$mapped=$primaryId>0&&$currentQueue!=='';$online=$primaryId>0&&isset($agentsById[$primaryId])&&print_agent_online($agentsById[$primaryId]);$queueHealth=$primaryId>0&&isset($agentsById[$primaryId])?printing_queue_health($agentsById[$primaryId],$currentQueue):['ready'=>false,'label'=>'رایانه چاپ انتخاب نشده'];$operationalRoute=print_destination_operational_route($pdo,$key);$destinationJobCount=$destinationJobCounts[$key]??0;$summaryAgent=$operationalRoute?(string)$operationalRoute['agent_name']:(string)($destination['agent_name']?:'بدون رایانه چاپ');$summaryQueue=$operationalRoute?(string)$operationalRoute['windows_queue_name']:$currentQueue;$state=(int)$destination['active']!==1?'غیرفعال':($operationalRoute?($operationalRoute['role']==='fallback'?'آماده از مسیر جایگزین':'آماده'):(!$mapped&&!((int)($destination['fallback_agent_id']??0)>0&&trim((string)($destination['fallback_windows_queue_name']??''))!=='')?'نیازمند انتخاب پرینتر':'رایانه/پرینتر آماده نیست')); ?>
<details class="print5-destination-editor"><summary><div><span class="print5-status-dot <?= $operationalRoute?'is-ok':'is-warn' ?>"></span><div><strong><?= e((string)$destination['label']) ?></strong><small><?= e($summaryAgent) ?> · <?= e($summaryQueue?:'پرینتر انتخاب نشده') ?></small></div></div><div><span class="print5-state <?= $operationalRoute?'is-ok':'is-warn' ?>"><?= e($state) ?></span><span class="print5-edit-label">ویرایش <?= ui_icon('chevron-down') ?></span></div></summary><?php if((int)($destination['fallback_agent_id']??0)>0&&trim((string)($destination['fallback_windows_queue_name']??''))!==''): ?><form method="post" class="print5-promote-fallback" data-print-mutation data-confirm="مسیر جایگزین به مسیر اصلی تبدیل می‌شود. سوابق چاپ قبلی بدون تغییر حفظ می‌شوند و این تغییر فقط برای کارهای جدید است." data-confirm-title="تبدیل مسیر جایگزین به اصلی؟" data-confirm-ok="تبدیل به اصلی"><?= csrf_field() ?><input type="hidden" name="return_tab" value="settings"><input type="hidden" name="action" value="promote_fallback"><input type="hidden" name="destination_key" value="<?= e($key) ?>"><input type="hidden" name="expected_primary_agent_id" value="<?= (int)($destination['agent_id']??0) ?>"><input type="hidden" name="expected_primary_queue" value="<?= e((string)($destination['windows_queue_name']??'')) ?>"><input type="hidden" name="expected_fallback_agent_id" value="<?= (int)($destination['fallback_agent_id']??0) ?>"><input type="hidden" name="expected_fallback_queue" value="<?= e((string)($destination['fallback_windows_queue_name']??'')) ?>"><button class="btn btn-light" type="submit">تبدیل مسیر جایگزین به اصلی</button></form><?php endif; ?><form method="post" class="print5-destination" data-print-destination><?= csrf_field() ?><input type="hidden" name="return_tab" value="settings"><input type="hidden" name="action" value="save_destination"><input type="hidden" name="destination_key" value="<?= e($key) ?>"><div class="print5-destination-fields"><div class="print5-pair" data-print-pair><label><span>رایانه چاپ</span><select class="form-control" name="agent_id" data-choice-mode="browse" data-print-agent-select="primary"><option value="0">انتخاب نشده</option><?php foreach($routeEligibleAgents as $agent): ?><option value="<?= (int)$agent['id'] ?>" <?= $primaryId===(int)$agent['id']?'selected':'' ?>><?= e((string)$agent['name']) ?><?= print_agent_online($agent)?' · آنلاین':' · آفلاین' ?></option><?php endforeach; ?></select></label></div><div class="print5-pair" data-print-pair><label><span>پرینتر ویندوز</span><select class="form-control" name="windows_queue_name" data-choice-mode="browse" data-print-printer-select="primary" data-current-printer="<?= e($currentQueue) ?>"><option value="">ابتدا رایانه چاپ را انتخاب کنید</option></select></label></div><label class="print5-active"><input type="checkbox" name="active" <?= (int)$destination['active']?'checked':'' ?>><span>این مقصد فعال باشد</span></label><label class="print5-active"><input type="checkbox" name="required_for_operation" <?= (int)($destination['required_for_operation']??1)?'checked':'' ?>><span>این مسیر برای سلامت عملیاتی ضروری است</span></label></div><details class="print5-destination-advanced"><summary>تنظیمات بیشتر مقصد</summary><div class="print5-advanced-fields"><label><span>نام مقصد</span><input class="form-control" name="label" value="<?= e((string)$destination['label']) ?>" maxlength="160" required></label><?php if($type==='preparation'): ?><fieldset class="print5-area-field"><legend>بخش‌های این مقصد</legend><div class="compact-area-grid"><?php foreach(preparation_operational_areas() as $area=>$areaLabel): ?><label><input type="checkbox" name="preparation_areas[]" value="<?= e($area) ?>" <?= in_array($area,$areas,true)?'checked':'' ?>><span><?= e($areaLabel) ?></span></label><?php endforeach; ?></div></fieldset><?php endif; ?><label><span>رایانه چاپ جایگزین</span><select class="form-control" name="fallback_agent_id" data-choice-mode="browse" data-print-agent-select="fallback"><option value="0">بدون جایگزین</option><?php foreach($routeEligibleAgents as $agent): ?><option value="<?= (int)$agent['id'] ?>" <?= (int)($destination['fallback_agent_id']??0)===(int)$agent['id']?'selected':'' ?>><?= e((string)$agent['name']) ?></option><?php endforeach; ?></select></label><label><span>پرینتر جایگزین</span><select class="form-control" name="fallback_windows_queue_name" data-choice-mode="browse" data-print-printer-select="fallback" data-current-printer="<?= e((string)($destination['fallback_windows_queue_name']??'')) ?>"><option value="">ابتدا رایانه جایگزین را انتخاب کنید</option></select></label><label><span>عرض رول</span><select class="form-control" name="paper_width_mm" data-choice-mode="compact"><option value="80" <?= (int)$destination['paper_width_mm']===80?'selected':'' ?>>۸۰ میلی‌متر</option><option value="58" <?= (int)$destination['paper_width_mm']===58?'selected':'' ?>>۵۸ میلی‌متر</option></select></label><label><span>عرض قابل چاپ</span><input class="form-control" name="printable_width_mm" inputmode="decimal" enterkeyhint="done" value="<?= e(numeric_input_display_value((string)$destination['printable_width_mm'])) ?>"></label><label><span>تعداد نسخه</span><select class="form-control" name="copies" data-choice-mode="compact"><?php foreach([1,2,3] as $copy): ?><option value="<?= $copy ?>" <?= (int)$destination['copies']===$copy?'selected':'' ?>><?= fa_digits($copy) ?></option><?php endforeach; ?></select></label></div></details><div class="print5-destination-footer"><span class="print5-destination-meta">رول <?= fa_digits((int)$destination['paper_width_mm']) ?> میلی‌متر · <?= fa_digits((int)$destination['copies']) ?> نسخه</span><div class="actions"><button class="btn btn-light" type="button" data-print-editor-close>انصراف</button><button class="btn btn-primary" type="submit">ذخیره تغییرات</button><?php if($type==='preparation'&&!in_array($key,['prep_shared'],true)): ?><?php if($destinationJobCount===0): ?><button class="btn btn-outline-danger" type="submit" name="action" value="delete_destination" form="delete-print-destination-<?= e($key) ?>">حذف مقصد</button><?php else: ?><button class="btn btn-outline-danger" type="button" disabled title="این مقصد سابقه چاپ دارد؛ برای حفظ Audit فقط غیرفعال می‌شود.">حذف مقصد</button><?php endif; ?><?php endif; ?></div></div></form><?php if($type==='preparation'&&!in_array($key,['prep_shared'],true)&&$destinationJobCount===0): ?><form id="delete-print-destination-<?= e($key) ?>" method="post" class="hidden" data-confirm="مقصد «<?= e((string)$destination['label']) ?>» حذف می‌شود. این عمل فقط چون هیچ سابقه چاپی ندارد مجاز است." data-confirm-title="حذف مقصد چاپ؟" data-confirm-ok="حذف مقصد" data-confirm-danger="1"><?= csrf_field() ?><input type="hidden" name="return_tab" value="settings"><input type="hidden" name="action" value="delete_destination"><input type="hidden" name="destination_key" value="<?= e($key) ?>"></form><?php endif; ?></details>
<?php endforeach; ?></div><details class="print5-create-destination"><summary><?= ui_icon('plus') ?> افزودن مقصد آماده‌سازی جدید</summary><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="return_tab" value="settings"><input type="hidden" name="action" value="create_destination"><label><span>نام مقصد</span><input class="form-control" name="label" placeholder="مثلاً آشپزخانه" maxlength="160" required></label><button class="btn btn-light">افزودن مقصد</button></form></details></div></section>

<details class="card print5-advanced"><summary><div><strong>تنظیمات پیشرفته چاپ</strong><span>پیش‌فرض چاپ هنگام تسویه، قالب‌ها و تنظیمات تخصصی</span></div><?= ui_icon('chevron-down') ?></summary><div class="card-body print5-advanced-body"><form method="post" class="print5-checkout-default"><?= csrf_field() ?><input type="hidden" name="return_tab" value="settings"><input type="hidden" name="action" value="save_checkout_print_default"><label class="check-line"><input type="checkbox" name="checkout_print_default" <?= setting_bool('checkout_print_default',true)?'checked':'' ?>><span>«نسخه مشتری چاپ شود» هنگام تسویه به‌صورت پیش‌فرض روشن باشد</span></label><button class="btn btn-primary">ذخیره</button></form><section class="print5-template-handoff"><div><strong>طراحی و نسخه‌بندی قالب‌ها</strong><span>پیش‌نمایش ۵۸/۸۰ میلی‌متر، ورود و خروجی امن و بازگشت به نسخه‌های قبلی در صفحه اختصاصی مدیریت می‌شود.</span></div><a class="btn btn-light" href="<?= e(asset('admin/print_templates.php')) ?>">مدیریت قالب‌های چاپ</a></section></div></details>

<?php else: ?>
<section class="print5-diagnostic-intro"><div><?= ui_icon('operations') ?><div><strong>عیب‌یابی فنی چاپ</strong><span>این صفحه وضعیت برنامه چاپ، API و رؤیت‌پذیری پرینتر و مسیر واقعی چاپ آزمایشی را نشان می‌دهد. برای Log کامل و Support Package از کنسول Windows Agent استفاده کنید.</span></div></div><a class="btn btn-light" href="<?= e(print_agent_release_page_url()) ?>" target="_blank" rel="noopener noreferrer">برنامه چاپ <?= e(print_agent_recommended_version()) ?></a></section>
<section class="card print5-section"><div class="card-head"><div class="panel-copy-stack"><strong>سلامت رایانه‌های چاپ</strong><small class="muted">Diagnostic details فقط اینجا نمایش داده می‌شوند تا نمای روزمره شلوغ نشود.</small></div></div><div class="card-body"><div class="print5-agent-list"><?php if(!$agents): ?><div class="empty-state">رایانه چاپ متصل نشده است.</div><?php else: foreach($agents as $agent): $ah=printing_agent_health($agent);$health=(array)$ah['health'];$lastAction=trim((string)($health['last_successful_action']??''));$lastApiSuccess=trim((string)($health['last_api_success_at']??''));$lastApiError=trim((string)($health['last_api_error_code']??''));$apiFailures=max(0,(int)($health['consecutive_api_failures']??0));$apiLatency=isset($health['last_api_latency_ms'])?(int)$health['last_api_latency_ms']:null; ?><article class="print5-agent-row is-diagnostic"><div class="print5-agent-main"><span class="print5-status-dot <?= e(printing_agent_health_class((string)$ah['state'])) ?>"></span><div><strong><?= e((string)$agent['name']) ?></strong><span><?= e((string)($agent['hostname']?:'هنوز متصل نشده')) ?> · نسخه <?= e((string)($agent['agent_version']?:'—')) ?></span></div></div><div class="panel-diagnostic-grid <?= in_array((string)$ah['state'],['degraded','attention','unavailable'],true)?'has-warning':'' ?>"><div class="panel-diagnostic-item is-primary"><span class="panel-diagnostic-label">وضعیت عملیاتی</span><strong><?= e((string)$ah['label']) ?></strong><small><?= e((string)$ah['detail']) ?></small></div><div class="panel-diagnostic-item"><span class="panel-diagnostic-label">آخرین API موفق</span><strong class="panel-diagnostic-value-small"><?= $lastApiSuccess!==''?e($lastApiSuccess):'—' ?></strong><small><?= $lastAction!==''?'Action: '.e($lastAction):'هنوز گزارش نشده' ?></small></div><div class="panel-diagnostic-item"><span class="panel-diagnostic-label">Transport</span><strong><?= $apiFailures>0?fa_digits($apiFailures).' خطای متوالی':'بدون خطای متوالی' ?></strong><small><?= $lastApiError!==''?'کد: '.e($lastApiError):($apiLatency!==null?'Latency: '.fa_digits($apiLatency).' ms':'جزئیات در Heartbeat بعدی') ?></small></div></div></article><?php endforeach; endif; ?></div></div></section>
<section class="card print5-section"><div class="card-head"><div class="panel-copy-stack"><strong>تست مسیرهای چاپ</strong><small class="muted">چاپ آزمایشی از Print API واقعی عبور می‌کند؛ تست مستقیم Windows جای این آزمون را نمی‌گیرد.</small></div></div><div class="card-body"><div class="print5-test-list"><?php foreach($destinations as $destination): $route=print_destination_operational_route($pdo,(string)$destination['destination_key']);$routeReady=$route!==null;$routeAgent=$routeReady?(string)$route['agent_name']:(string)($destination['agent_name']?:'بدون رایانه چاپ');$routeQueue=$routeReady?(string)$route['windows_queue_name']:(string)($destination['windows_queue_name']??'');$routeLabel=$routeReady?(($route['role']==='fallback'?'آماده از مسیر جایگزین':'آماده')):'رایانه/پرینتر آماده نیست'; ?><article class="print5-test-row"><div><span class="print5-status-dot <?= $routeReady?'is-ok':'is-warn' ?>"></span><div><strong><?= e((string)$destination['label']) ?></strong><small><?= e($routeAgent) ?> · <?= e($routeQueue?:'پرینتر انتخاب نشده') ?> · <?= e($routeLabel) ?></small></div></div><form method="post"><?= csrf_field() ?><input type="hidden" name="return_tab" value="diagnostics"><input type="hidden" name="action" value="test_print"><input type="hidden" name="destination_key" value="<?= e((string)$destination['destination_key']) ?>"><button class="btn btn-light" <?= !$routeReady?'disabled':'' ?>>چاپ آزمایشی</button></form></article><?php endforeach; ?></div></div></section>
<?php endif; ?>

<script type="application/json" id="printing-printers-data"><?= json_script($printerData) ?></script>
<?php panel_footer('<script defer src="'.e(asset('assets/js/printing-settings.js')).'"></script>'); ?>

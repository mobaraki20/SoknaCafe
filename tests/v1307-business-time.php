<?php
declare(strict_types=1);
$settings=[
    'business_day_cutoff'=>'04:00',
    'business_shifts_json'=>json_encode([
        ['key'=>'shift_1','label'=>'صبح','start'=>'08:00','end'=>'16:00','active'=>true],
        ['key'=>'shift_2','label'=>'عصر','start'=>'16:00','end'=>'01:00','active'=>true],
    ],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
];
function setting(string $key, string $default=''): string { global $settings; return (string)($settings[$key]??$default); }
function app_timezone(): string { return 'Asia/Tehran'; }
function en_digits(string $s): string { return strtr($s,['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9']); }
function text_substr(string $s,int $start,int $length): string { return substr($s,$start,$length); }
require dirname(__DIR__).'/includes/business_time.php';
$checks=0; function ok(bool $v,string $m): void { global $checks; $checks++; if(!$v)throw new RuntimeException($m); }

// Boundary semantics.
foreach([
 ['2026-08-09 23:59:59','2026-08-09','shift_2'],
 ['2026-08-10 00:00:00','2026-08-09','shift_2'],
 ['2026-08-10 00:59:59','2026-08-09','shift_2'],
 ['2026-08-10 01:00:00','2026-08-09','outside'],
 ['2026-08-10 03:59:59','2026-08-09','outside'],
 ['2026-08-10 04:00:00','2026-08-10','outside'],
 ['2026-08-10 08:00:00','2026-08-10','shift_1'],
 ['2026-08-10 16:00:00','2026-08-10','shift_2'],
] as [$ts,$date,$shift]) { $a=business_assignment($ts);ok($a['business_date']===$date&&$a['shift_key']===$shift,"boundary $ts"); }

// Single shift keeps report UI simple.
$settings['business_shifts_json']=json_encode([['key'=>'single_abc','label'=>'روزانه','start'=>'08:00','end'=>'01:00','active'=>true]],JSON_UNESCAPED_UNICODE);
$options=business_shift_filter_options(true);ok($options===['all'=>'کل روز'],'single shift must expose no redundant report filter');
$a=business_assignment('2026-08-10 00:30:00');ok($a['business_date']==='2026-08-09'&&$a['shift_key']==='single_abc','single shift may cross midnight but not cutoff');

// Carryover is business-day based, not arbitrary duration.
ok(business_session_is_carryover(['started_at'=>'2026-08-09 23:55:00','business_date'=>'2026-08-09'],'2026-08-10'),'previous business date must be carryover');
ok(!business_session_is_carryover(['started_at'=>'2026-08-10 04:01:00','business_date'=>'2026-08-10'],'2026-08-10'),'same business date must not be carryover');
$invalidSnapshotRejected=false;
try{business_session_is_carryover(['started_at'=>'2026-08-09 23:55:00','business_date'=>null],'2026-08-10');}catch(RuntimeException){$invalidSnapshotRejected=true;}
ok($invalidSnapshotRejected,'missing business-date snapshot must be rejected instead of reconstructed');

// New identities are opaque/stable and collision-safe for supplied reservations.
$k1=business_new_shift_key(['shift_deadbeef']);$k2=business_new_shift_key([$k1]);
ok((bool)preg_match('/^shift_[a-f0-9]{12}$/',$k1),'new shift key format');
ok($k1!==$k2,'new shift keys must differ');

printf("1.30.7 business-time runtime passed: %d checks.\n",$checks);

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
function en_digits(string $s): string { return strtr($s,['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']); }
function text_substr(string $s,int $start,int $length): string { return substr($s,$start,$length); }
require dirname(__DIR__).'/includes/business_time.php';

$checks=0;
function expect(bool $ok,string $msg): void { global $checks; $checks++; if(!$ok) throw new RuntimeException($msg); }
function assignment(string $ts): array { return business_assignment($ts); }

$a=assignment('2026-08-09 23:59:00'); expect($a['business_date']==='2026-08-09'&&$a['shift_key']==='shift_2','23:59 must stay in the same operational day / evening shift');
$a=assignment('2026-08-10 00:00:00'); expect($a['business_date']==='2026-08-09'&&$a['shift_key']==='shift_2','00:00 must belong to previous operational day / evening shift');
$a=assignment('2026-08-10 00:59:59'); expect($a['business_date']==='2026-08-09'&&$a['shift_key']==='shift_2','00:59 must belong to previous operational day / evening shift');
$a=assignment('2026-08-10 01:00:00'); expect($a['business_date']==='2026-08-09'&&$a['shift_key']==='outside','01:00 is outside configured shifts but remains previous operational day');
$a=assignment('2026-08-10 03:59:59'); expect($a['business_date']==='2026-08-09'&&$a['shift_key']==='outside','03:59 must remain previous operational day');
$a=assignment('2026-08-10 04:00:00'); expect($a['business_date']==='2026-08-10'&&$a['shift_key']==='outside','04:00 must start the new operational day');
$a=assignment('2026-08-10 07:59:59'); expect($a['business_date']==='2026-08-10'&&$a['shift_key']==='outside','before opening is outside shift');
$a=assignment('2026-08-10 08:00:00'); expect($a['business_date']==='2026-08-10'&&$a['shift_key']==='shift_1','08:00 must enter morning shift');
$a=assignment('2026-08-10 15:59:59'); expect($a['shift_key']==='shift_1','15:59 must be morning');
$a=assignment('2026-08-10 16:00:00'); expect($a['shift_key']==='shift_2','16:00 must enter evening shift');

[$start,$end]=business_day_bounds('2026-08-09');
expect($start->format('Y-m-d H:i:s')==='2026-08-09 04:00:00'&&$end->format('Y-m-d H:i:s')==='2026-08-10 04:00:00','business day bounds must be 04:00 to next 04:00 exclusive');

$threw=false;try{business_validate_configuration('04:00',[
 ['key'=>'a','label'=>'A','start'=>'08:00','end'=>'17:00','active'=>true],
 ['key'=>'b','label'=>'B','start'=>'16:00','end'=>'20:00','active'=>true],
]);}catch(RuntimeException){$threw=true;}expect($threw,'overlapping shifts must be rejected');

$threw=false;try{business_validate_configuration('04:00',[
 ['key'=>'a','label'=>'A','start'=>'03:00','end'=>'05:00','active'=>true],
]);}catch(RuntimeException){$threw=true;}expect($threw,'shift crossing operational-day cutoff must be rejected');

$threw=false;try{business_validate_configuration('04:00',[
 ['key'=>'a','label'=>'A','start'=>'08:00','end'=>'08:00','active'=>true],
]);}catch(RuntimeException){$threw=true;}expect($threw,'equal start/end must be rejected rather than becoming a 24-hour shift');

$threw=false;try{business_validate_configuration('04:00',[
 ['key'=>'a','label'=>'A','start'=>'05:00','end'=>'06:00','active'=>true],
 ['key'=>'b','label'=>'B','start'=>'06:00','end'=>'07:00','active'=>true],
 ['key'=>'c','label'=>'C','start'=>'07:00','end'=>'08:00','active'=>true],
 ['key'=>'d','label'=>'D','start'=>'08:00','end'=>'09:00','active'=>true],
]);}catch(RuntimeException){$threw=true;}expect($threw,'more than three active shifts must be rejected');

[$sql,$params]=business_snapshot_filter_sql('sr','2026-08-01','2026-08-09','shift_2');
expect(str_contains($sql,'sr.business_date>=?')&&str_contains($sql,'sr.business_shift_key=?')&&$params===['2026-08-01','2026-08-09','shift_2'],'snapshot report filter contract');

printf("Business-time runtime passed: %d checks.\n",$checks);

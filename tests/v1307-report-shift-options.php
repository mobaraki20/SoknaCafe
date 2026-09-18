<?php
declare(strict_types=1);
$settings=[];$fakeRows=[];
function setting(string $key,string $default=''): string { global $settings; return (string)($settings[$key]??$default); }
function app_timezone(): string { return 'Asia/Tehran'; }
function en_digits(string $s): string { return $s; }
function text_substr(string $s,int $start,int $length): string { return substr($s,$start,$length); }
final class FakeStmt implements IteratorAggregate { public function __construct(private array $rows){} public function execute(array $params=[]): bool{return true;} public function getIterator(): Traversable{return new ArrayIterator($this->rows);} }
final class FakePDO { public function prepare(string $sql): FakeStmt { global $fakeRows; return new FakeStmt($fakeRows); } public function query(string $sql): FakeStmt { global $fakeRows; return new FakeStmt($fakeRows); } }
function db(): FakePDO { static $db; return $db??=new FakePDO(); }
require dirname(__DIR__).'/includes/business_time.php';
$checks=0;function ok(bool $v,string $m):void{global $checks;$checks++;if(!$v)throw new RuntimeException($m);}

// Current single shift, no/matching history => no redundant filter.
$settings=['business_day_cutoff'=>'04:00','business_shifts_json'=>json_encode([['key'=>'single','label'=>'روزانه','start'=>'08:00','end'=>'01:00','active'=>true]],JSON_UNESCAPED_UNICODE)];
$fakeRows=[];ok(business_report_shift_options('2026-08-01','2026-08-10',true)===['all'=>'کل روز'],'single shift no history');
$fakeRows=[['business_shift_key'=>'single','business_shift_label'=>'روزانه','last_seen'=>'2026-08-10 00:30:00']];ok(business_report_shift_options('2026-08-01','2026-08-10',true)===['all'=>'کل روز'],'single shift matching history');

// Historical range with two retired identities must remain explorable/comparable.
$fakeRows=[
 ['business_shift_key'=>'retired_b','business_shift_label'=>'شب قدیم','last_seen'=>'2026-07-20 23:00:00'],
 ['business_shift_key'=>'retired_a','business_shift_label'=>'صبح قدیم','last_seen'=>'2026-07-20 15:00:00'],
];
$o=business_report_shift_options('2026-07-01','2026-07-31',true);
ok(($o['retired_a']??'')==='صبح قدیم'&&($o['retired_b']??'')==='شب قدیم'&&isset($o['compare']),'retired identities preserved');
$ids=business_report_shift_identities($o);ok(count($ids)===2&&!isset($ids['all'])&&!isset($ids['compare']),'pseudo options excluded');

// Current multi-shift always exposes configured identities; history can add retired ones.
$settings['business_shifts_json']=json_encode([
 ['key'=>'morning','label'=>'صبح','start'=>'08:00','end'=>'16:00','active'=>true],
 ['key'=>'evening','label'=>'عصر','start'=>'16:00','end'=>'01:00','active'=>true],
],JSON_UNESCAPED_UNICODE);
$fakeRows=[['business_shift_key'=>'old','business_shift_label'=>'قدیمی','last_seen'=>'2026-07-01 10:00:00']];
$o=business_report_shift_options('2026-07-01','2026-07-31',true);
ok(isset($o['morning'],$o['evening'],$o['old'],$o['compare']),'current and retired identities merge');

printf("1.30.7 report shift options passed: %d checks.\n",$checks);

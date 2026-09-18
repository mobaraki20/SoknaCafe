<?php
declare(strict_types=1);

final class SubscriberFakeStmt extends PDOStatement {
    public array $rows;
    public bool $throw;
    public array $executed=[];
    public function __construct(array $rows=[], bool $throw=false){$this->rows=$rows;$this->throw=$throw;}
    public function execute(?array $params=null): bool { if($this->throw) throw new RuntimeException('simulated settlement schema mismatch'); $this->executed=$params??[]; return true; }
    public function fetchAll(int $mode=PDO::FETCH_DEFAULT, mixed ...$args): array { return $this->rows; }
}
final class SubscriberFakePDO extends PDO {
    public array $rows; public bool $throw;
    public function __construct(array $rows=[], bool $throw=false){$this->rows=$rows;$this->throw=$throw;}
    public function prepare(string $query, array $options=[]): PDOStatement|false { return new SubscriberFakeStmt($this->rows,$this->throw); }
}
require dirname(__DIR__).'/includes/subscribers_page.php';

$checks=0; function ok(bool $v,string $m):void{global $checks;$checks++;if(!$v)throw new RuntimeException($m);}
$ledger=[
 ['id'=>10,'entry_type'=>'invoice','related_entry_id'=>null,'table_name'=>null],
 ['id'=>11,'entry_type'=>'invoice_reversal','related_entry_id'=>10,'table_name'=>null],
 ['id'=>12,'entry_type'=>'payment','related_entry_id'=>null,'table_name'=>null],
];
$meta=[[
 'settlement_id'=>90,'subscriber_ledger_entry_id'=>10,'invoice_number'=>'I-1405-000090','settled_at'=>'2026-08-15 10:00:00','destination'=>'subscriber','settlement_status'=>'completed','table_name_snapshot'=>'میز ۲','reversal_settlement_id'=>91,'reversal_invoice_number'=>'I-1405-000091'
]];
$out=subscriber_ledger_enrich_settlements(new SubscriberFakePDO($meta),$ledger);
ok((int)$out[0]['settlement_id']===90,'invoice receives settlement metadata');
ok((int)$out[1]['settlement_id']===90,'reversal maps through related original ledger id');
ok((string)$out[0]['table_name']==='میز ۲','snapshot table metadata applied');
ok($out[2]['settlement_id']===null,'non-settled ledger remains safe/null');

// Secondary schema failure must never throw and must leave a render-safe ledger.
$out=subscriber_ledger_enrich_settlements(new SubscriberFakePDO([],true),$ledger);
ok(count($out)===3,'enrichment failure preserves ledger rows');
foreach($out as $row){
    foreach(['settlement_id','invoice_number','settled_at','destination','settlement_status','reversal_settlement_id','reversal_invoice_number'] as $key){
        ok(array_key_exists($key,$row),"safe field {$key} initialized");
    }
}
printf("1.32.14 subscriber enrichment fail-soft PASS: %d checks.\n",$checks);

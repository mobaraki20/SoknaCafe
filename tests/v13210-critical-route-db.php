<?php
declare(strict_types=1);
/* Environment gate for the production-sensitive subscriber detail SQL.
 * Run with SOKNA_DB_TEST_DSN/mysql credentials in staging/CI. */
if (!extension_loaded('pdo_mysql')) {
    fwrite(STDERR, "UAT_REQUIRED: pdo_mysql is not installed; subscriber route DB runtime was not executed.\n");
    exit(getenv('SOKNA_REQUIRE_DB_ROUTE_GATE') === '1' ? 2 : 0);
}
$dsn=(string)getenv('SOKNA_DB_TEST_DSN');$user=(string)getenv('SOKNA_DB_TEST_USER');$pass=(string)getenv('SOKNA_DB_TEST_PASS');
if($dsn===''){
    fwrite(STDERR,"UAT_REQUIRED: SOKNA_DB_TEST_DSN is missing; subscriber route DB runtime was not executed.\n");
    exit(getenv('SOKNA_REQUIRE_DB_ROUTE_GATE') === '1' ? 3 : 0);
}
$pdo=new PDO($dsn,$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$subscriberId=(int)(getenv('SOKNA_DB_TEST_SUBSCRIBER_ID')?:0);
if($subscriberId<1)$subscriberId=(int)$pdo->query('SELECT id FROM subscribers ORDER BY id LIMIT 1')->fetchColumn();
if($subscriberId<1){fwrite(STDERR,"UAT_REQUIRED: test DB has no subscriber fixture.\n");exit(getenv('SOKNA_REQUIRE_DB_ROUTE_GATE')==='1'?4:0);}
$sql="SELECT l.id,orig_sr.id settlement_id,orig_sr.invoice_number,rev_sr.id reversal_settlement_id,rev_sr.invoice_number reversal_invoice_number FROM subscriber_ledger l LEFT JOIN (SELECT subscriber_ledger_entry_id,MIN(id) settlement_id FROM settlement_records WHERE subscriber_ledger_entry_id IS NOT NULL AND status='completed' AND reverses_settlement_id IS NULL GROUP BY subscriber_ledger_entry_id) orig_map ON orig_map.subscriber_ledger_entry_id=CASE WHEN l.entry_type='invoice_reversal' THEN l.related_entry_id ELSE l.id END LEFT JOIN settlement_records orig_sr ON orig_sr.id=orig_map.settlement_id LEFT JOIN settlement_records rev_sr ON rev_sr.reverses_settlement_id=orig_sr.id AND rev_sr.status='reversal' WHERE l.subscriber_id=? ORDER BY l.created_at DESC,l.id DESC LIMIT 30";
$st=$pdo->prepare($sql);$st->execute([$subscriberId]);$rows=$st->fetchAll();
$ids=array_column($rows,'id');if(count($ids)!==count(array_unique($ids)))throw new RuntimeException('Subscriber detail query duplicated ledger rows.');
echo "1.32.14 subscriber route DB runtime PASS (subscriber={$subscriberId}, rows=".count($rows).")\n";

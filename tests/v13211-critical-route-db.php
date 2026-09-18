<?php
declare(strict_types=1);
/* Promotion blocker: executes subscriber-profile shaped queries on real MySQL/MariaDB.
 * A final/promotion run MUST set SOKNA_REQUIRE_DB_ROUTE_GATE=1. */
$strict = getenv('SOKNA_REQUIRE_DB_ROUTE_GATE') === '1';
function env_block(string $message, int $code, bool $strict): never {
    fwrite(STDERR, ($strict ? 'BLOCKED: ' : 'UAT_REQUIRED: ') . $message . "\n");
    exit($strict ? $code : 0);
}
if (!extension_loaded('pdo_mysql')) env_block('pdo_mysql is not installed; subscriber route DB runtime was not executed.', 2, $strict);
$dsn=(string)getenv('SOKNA_DB_TEST_DSN');$user=(string)getenv('SOKNA_DB_TEST_USER');$pass=(string)getenv('SOKNA_DB_TEST_PASS');
if($dsn==='') env_block('SOKNA_DB_TEST_DSN is missing; subscriber route DB runtime was not executed.',3,$strict);
$pdo=new PDO($dsn,$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
$subscriberId=(int)(getenv('SOKNA_DB_TEST_SUBSCRIBER_ID')?:0);
if($subscriberId<1)$subscriberId=(int)$pdo->query('SELECT id FROM subscribers ORDER BY id LIMIT 1')->fetchColumn();
if($subscriberId<1) env_block('test DB has no subscriber fixture.',4,$strict);

// Primary query: must work without settlement joins and must not duplicate ledger rows.
$base="SELECT l.*,u.display_name actor_name,ts.table_id,t.name table_name,rev_l.id reversal_id
       FROM subscriber_ledger l
       LEFT JOIN users u ON u.id=l.actor_user_id
       LEFT JOIN table_sessions ts ON ts.id=l.table_session_id
       LEFT JOIN cafe_tables t ON t.id=ts.table_id
       LEFT JOIN subscriber_ledger rev_l ON rev_l.related_entry_id=l.id
       WHERE l.subscriber_id=?
       ORDER BY l.created_at DESC,l.id DESC LIMIT 30 OFFSET 0";
$st=$pdo->prepare($base);$st->execute([$subscriberId]);$rows=$st->fetchAll();
$ids=array_map('intval',array_column($rows,'id'));
if(count($ids)!==count(array_unique($ids))) throw new RuntimeException('Subscriber base route duplicated ledger rows.');

// Secondary settlement enrichment is tested separately; it may fail-soft in the route,
// but on a promotion database with the supported schema it must execute successfully.
$lookup=[];foreach($rows as $row){$target=(string)$row['entry_type']==='invoice_reversal'?(int)($row['related_entry_id']??0):(int)$row['id'];if($target>0)$lookup[$target]=true;}
if($lookup){
    $vals=array_keys($lookup);$marks=implode(',',array_fill(0,count($vals),'?'));
    $sql="SELECT sr.id settlement_id,sr.subscriber_ledger_entry_id,sr.invoice_number,sr.settled_at,sr.destination,sr.status settlement_status,sr.table_name_snapshot,rev.id reversal_settlement_id,rev.invoice_number reversal_invoice_number
          FROM settlement_records sr
          LEFT JOIN settlement_records rev ON rev.reverses_settlement_id=sr.id AND rev.status='reversal'
          WHERE sr.subscriber_ledger_entry_id IN ($marks) AND sr.status='completed' AND sr.reverses_settlement_id IS NULL ORDER BY sr.id ASC";
    $e=$pdo->prepare($sql);$e->execute($vals);$e->fetchAll();
}
echo "1.32.14 subscriber route MariaDB/MySQL runtime PASS (subscriber={$subscriberId}, rows=".count($rows).")\n";

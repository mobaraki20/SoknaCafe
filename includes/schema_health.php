<?php
declare(strict_types=1);

/**
 * Application-owned database health contract.
 *
 * Keep this file bootstrap-independent so the updater can validate the live
 * database even when the main application cannot be loaded. Checks are
 * read-only and represent schema invariants required by the currently shipped
 * application, not historical migrations.
 */
function sokna_schema_health_checks(PDO $pdo): array
{
    $dbName = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($dbName === '') {
        return ['database_selected' => false];
    }

    $checks = ['database_selected' => true];

    $column = $pdo->prepare(
        "SELECT DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA=? AND TABLE_NAME='settlement_records' AND COLUMN_NAME='request_id'
         LIMIT 1"
    );
    $column->execute([$dbName]);
    $row = $column->fetch(PDO::FETCH_ASSOC);
    $checks['settlement_request_id_column'] = is_array($row)
        && strtolower((string)($row['DATA_TYPE'] ?? '')) === 'varchar'
        && (int)($row['CHARACTER_MAXIMUM_LENGTH'] ?? 0) >= 96
        && strtoupper((string)($row['IS_NULLABLE'] ?? '')) === 'YES';

    $index = $pdo->prepare(
        "SELECT INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME
         FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA=? AND TABLE_NAME='settlement_records' AND INDEX_NAME='uq_settlement_request_id'
         ORDER BY SEQ_IN_INDEX"
    );
    $index->execute([$dbName]);
    $indexRows = $index->fetchAll(PDO::FETCH_ASSOC);
    $checks['settlement_request_id_unique_index'] = count($indexRows) === 1
        && (int)($indexRows[0]['NON_UNIQUE'] ?? 1) === 0
        && (int)($indexRows[0]['SEQ_IN_INDEX'] ?? 0) === 1
        && (string)($indexRows[0]['COLUMN_NAME'] ?? '') === 'request_id';

    $nullability = $pdo->prepare(
        "SELECT IS_NULLABLE
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?
         LIMIT 1"
    );
    $isNotNull = static function (PDOStatement $stmt, string $db, string $table, string $field): bool {
        $stmt->execute([$db, $table, $field]);
        $value = $stmt->fetchColumn();
        return is_string($value) && strtoupper($value) === 'NO';
    };

    $checks['cafe_table_number_not_null'] = $isNotNull($nullability, $dbName, 'cafe_tables', 'table_number');

    $tableExists = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=?");
    foreach (['menus','menu_categories','menu_items'] as $table) {
        $tableExists->execute([$dbName,$table]);
        $checks['menu_catalog_table_' . $table] = (int)$tableExists->fetchColumn() === 1;
    }
    $checks['menu_category_key_not_null'] = $isNotNull($nullability, $dbName, 'categories', 'category_key');
    $checks['menu_category_audience_not_null'] = $isNotNull($nullability, $dbName, 'categories', 'audience');
    $checks['menu_key_not_null'] = $isNotNull($nullability, $dbName, 'menus', 'menu_key');

    $menuIndexes = $pdo->prepare(
        "SELECT TABLE_NAME,INDEX_NAME,NON_UNIQUE,SEQ_IN_INDEX,COLUMN_NAME
         FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA=? AND ((TABLE_NAME='categories' AND INDEX_NAME='uq_categories_key') OR (TABLE_NAME='menus' AND INDEX_NAME='uq_menus_key'))
         ORDER BY TABLE_NAME,INDEX_NAME,SEQ_IN_INDEX"
    );
    $menuIndexes->execute([$dbName]);
    $menuIndexRows = $menuIndexes->fetchAll(PDO::FETCH_ASSOC);
    $checks['menu_category_key_unique_index'] = count(array_filter($menuIndexRows, static fn(array $r): bool => ($r['TABLE_NAME']??'')==='categories' && ($r['INDEX_NAME']??'')==='uq_categories_key' && (int)($r['NON_UNIQUE']??1)===0 && (int)($r['SEQ_IN_INDEX']??0)===1 && ($r['COLUMN_NAME']??'')==='category_key')) === 1;
    $checks['menu_key_unique_index'] = count(array_filter($menuIndexRows, static fn(array $r): bool => ($r['TABLE_NAME']??'')==='menus' && ($r['INDEX_NAME']??'')==='uq_menus_key' && (int)($r['NON_UNIQUE']??1)===0 && (int)($r['SEQ_IN_INDEX']??0)===1 && ($r['COLUMN_NAME']??'')==='menu_key')) === 1;

    foreach (['table_sessions', 'orders', 'waiter_calls', 'settlement_records'] as $table) {
        foreach (['business_date', 'business_shift_key', 'business_shift_label', 'business_cutoff_snapshot'] as $field) {
            $checks['business_snapshot_' . $table . '_' . $field . '_not_null'] = $isNotNull($nullability, $dbName, $table, $field);
        }
    }

    $requiredSettlementColumns = ['request_fingerprint','settlement_kind','closes_session','remaining_subtotal','remaining_discount','remaining_total','allocation_version'];
    $settlementColumns = $pdo->prepare(
        "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='settlement_records'"
    );
    $settlementColumns->execute([$dbName]);
    $settlementColumnNames = array_map('strval', $settlementColumns->fetchAll(PDO::FETCH_COLUMN));
    foreach ($requiredSettlementColumns as $field) {
        $checks['itemized_settlement_column_' . $field] = in_array($field, $settlementColumnNames, true);
    }

    $lineTable = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME='settlement_record_lines'"
    );
    $lineTable->execute([$dbName]);
    $checks['itemized_settlement_line_table'] = (int)$lineTable->fetchColumn() === 1;
    if ($checks['itemized_settlement_line_table']) {
        $lineColumns = $pdo->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='settlement_record_lines'");
        $lineColumns->execute([$dbName]);
        $lineColumnNames = array_map('strval', $lineColumns->fetchAll(PDO::FETCH_COLUMN));
        foreach (['settlement_id','order_item_id','quantity','gross_amount','discount_amount','net_amount'] as $field) {
            $checks['itemized_settlement_line_column_' . $field] = in_array($field, $lineColumnNames, true);
        }
    }

    $claimColumns=$pdo->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='print_claim_requests'");
    $claimColumns->execute([$dbName]);
    $claimColumnNames=array_map('strval',$claimColumns->fetchAll(PDO::FETCH_COLUMN));
    foreach(['response_snapshot_json'] as $field){
        $checks['print_claim_reconciliation_column_'.$field]=in_array($field,$claimColumnNames,true);
    }

    $tableExists->execute([$dbName,'print_claim_reconciliations']);
    $checks['print_claim_reconciliation_ledger_table']=(int)$tableExists->fetchColumn()===1;
    if($checks['print_claim_reconciliation_ledger_table']){
        $ledgerColumns=$pdo->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='print_claim_reconciliations'");
        $ledgerColumns->execute([$dbName]);$ledgerColumnNames=array_map('strval',$ledgerColumns->fetchAll(PDO::FETCH_COLUMN));
        foreach(['claim_request_row_id','agent_id','request_id','request_hash','old_attempt_id','replacement_attempt_id','evidence_json'] as $field){
            $checks['print_claim_reconciliation_ledger_column_'.$field]=in_array($field,$ledgerColumnNames,true);
        }
        $ledgerIndexes=$pdo->prepare("SELECT INDEX_NAME,NON_UNIQUE,SEQ_IN_INDEX,COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=? AND TABLE_NAME='print_claim_reconciliations' AND INDEX_NAME IN('uq_print_claim_reconciliation_request','uq_print_claim_reconciliation_attempt') ORDER BY INDEX_NAME,SEQ_IN_INDEX");
        $ledgerIndexes->execute([$dbName]);$ledgerIndexRows=$ledgerIndexes->fetchAll(PDO::FETCH_ASSOC);
        $ledgerShape=[];$ledgerUnique=[];foreach($ledgerIndexRows as $row){$name=(string)$row['INDEX_NAME'];$ledgerShape[$name][]=(string)$row['COLUMN_NAME'];$ledgerUnique[$name]=($ledgerUnique[$name]??true)&&(int)($row['NON_UNIQUE']??1)===0;}
        $checks['print_claim_reconciliation_ledger_unique_request']=($ledgerUnique['uq_print_claim_reconciliation_request']??false)&&($ledgerShape['uq_print_claim_reconciliation_request']??[])===['agent_id','request_id'];
        $checks['print_claim_reconciliation_ledger_unique_attempt']=($ledgerUnique['uq_print_claim_reconciliation_attempt']??false)&&($ledgerShape['uq_print_claim_reconciliation_attempt']??[])===['claim_request_row_id','old_attempt_id'];
    }

    return $checks;
}

<?php
declare(strict_types=1);

/** Seed the owner-reviewed inventory master catalog only when the inventory is empty. */
function inventory_seed_initial_catalog(PDO $pdo): array
{
    try {
        $count = (int)$pdo->query('SELECT COUNT(*) FROM inventory_items')->fetchColumn();
    } catch (Throwable) {
        return ['seeded'=>false,'items'=>0,'purchase_units'=>0];
    }
    if ($count > 0) return ['seeded'=>false,'items'=>$count,'purchase_units'=>0];

    $path = dirname(__DIR__) . '/database/inventory_seed.json';
    if (!is_file($path)) return ['seeded'=>false,'items'=>0,'purchase_units'=>0];
    $payload = json_decode((string)file_get_contents($path), true);
    $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];
    if (!$items) return ['seeded'=>false,'items'=>0,'purchase_units'=>0];

    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) $pdo->beginTransaction();
    try {
        // Recheck after the transaction starts. In a concurrent first-open race,
        // the unique item codes plus rollback keep the seed all-or-nothing.
        $count = (int)$pdo->query('SELECT COUNT(*) FROM inventory_items')->fetchColumn();
        if ($count > 0) {
            if ($ownsTransaction) $pdo->commit();
            return ['seeded'=>false,'items'=>$count,'purchase_units'=>0];
        }

        $itemStmt = $pdo->prepare('INSERT INTO inventory_items(item_code,name,category,base_unit,default_department,warning_threshold,review_status,review_note,active) VALUES(?,?,?,?,?,0,?,?,1)');
        $unitStmt = $pdo->prepare('INSERT INTO inventory_purchase_units(inventory_item_id,name,conversion_mode,base_quantity,review_status,note,active) VALUES(?,?,?,?,?,?,1)');
        $balanceStmt = $pdo->prepare("INSERT INTO inventory_balances(inventory_item_id,quantity_base,average_unit_cost,cost_status) VALUES(?,0,NULL,'unknown')");
        $itemCount = 0;
        $unitCount = 0;

        foreach ($items as $item) {
            if (!is_array($item)) continue;
            $code = trim((string)($item['code'] ?? ''));
            $name = trim((string)($item['name'] ?? ''));
            if ($code === '' || $name === '') continue;
            $itemStmt->execute([
                $code,
                $name,
                (string)($item['category'] ?? 'ingredient'),
                (string)($item['base_unit'] ?? 'count'),
                (string)($item['default_department'] ?? 'shared'),
                (string)($item['review_status'] ?? 'needs_review'),
                trim((string)($item['review_note'] ?? '')) ?: null,
            ]);
            $itemId = (int)$pdo->lastInsertId();
            $balanceStmt->execute([$itemId]);
            $itemCount++;

            foreach ((array)($item['purchase_units'] ?? []) as $unit) {
                if (!is_array($unit)) continue;
                $unitName = trim((string)($unit['name'] ?? ''));
                if ($unitName === '') continue;
                $mode = (string)($unit['conversion_mode'] ?? 'fixed');
                $baseQuantity = $unit['base_quantity'] ?? null;
                $unitStmt->execute([
                    $itemId,
                    $unitName,
                    $mode === 'actual_quantity' ? 'actual_quantity' : 'fixed',
                    $baseQuantity === null || $baseQuantity === '' ? null : max(1, (int)$baseQuantity),
                    (string)($unit['review_status'] ?? 'needs_review'),
                    trim((string)($unit['note'] ?? '')) ?: null,
                ]);
                $unitCount++;
            }
        }

        if ($ownsTransaction) $pdo->commit();
        return ['seeded'=>$itemCount > 0,'items'=>$itemCount,'purchase_units'=>$unitCount];
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

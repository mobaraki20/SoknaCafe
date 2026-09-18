<?php

declare(strict_types=1);

// Extracted from includes/functions.php during pre-operational P2 owner cleanup.
// Keep behavior-compatible global function names; functions.php remains the public bootstrap aggregator.

function menu_item_audit_snapshot(array $row): array
{
    return [
        'name'=>trim((string)($row['name'] ?? '')),
        'price'=>(int)($row['price'] ?? 0),
        'available'=>(int)($row['available'] ?? 0),
        'active'=>(int)($row['active'] ?? 0),
        'featured'=>(int)($row['featured'] ?? 0),
        'takeaway_allowed'=>(int)($row['takeaway_allowed'] ?? 1),
        'preparation_station'=>normalize_preparation_station((string)($row['preparation_station'] ?? 'other')),
    ];
}

function menu_item_important_changes(array $before, array $after): array
{
    $beforeSnapshot=menu_item_audit_snapshot($before);
    $afterSnapshot=menu_item_audit_snapshot($after);
    $changes=[];
    foreach($beforeSnapshot as $key=>$value){
        if($value!==$afterSnapshot[$key])$changes[$key]=['before'=>$value,'after'=>$afterSnapshot[$key]];
    }
    return $changes;
}

function audit_actor_display_snapshot(?int $actorUserId, ?PDO $pdo = null): ?string
{
    if (!$actorUserId) return null;
    $current = current_user();
    if ((int)($current['id'] ?? 0) === $actorUserId) {
        $name = trim((string)($current['display_name'] ?? ''));
        if ($name !== '') return text_substr($name, 0, 160);
    }
    try {
        $pdo ??= db();
        $stmt = $pdo->prepare('SELECT display_name FROM users WHERE id=? LIMIT 1');
        $stmt->execute([$actorUserId]);
        $name = trim((string)($stmt->fetchColumn() ?: ''));
        return $name !== '' ? text_substr($name, 0, 160) : null;
    } catch (Throwable) {
        return null;
    }
}

function audit_log_insert(PDO $pdo, string $action, string $entityType, string|int|null $entityId, array $details = [], ?int $actorUserId = null): void
{
    $current = current_user();
    $actorUserId ??= isset($current['id']) ? (int)$current['id'] : null;
    $actorSnapshot = audit_actor_display_snapshot($actorUserId, $pdo);
    $stmt = $pdo->prepare('INSERT INTO audit_log(actor_user_id,actor_display_name_snapshot,action,entity_type,entity_id,details_json,source_ip,user_agent) VALUES(?,?,?,?,?,?,?,?)');
    $stmt->execute([
        $actorUserId,
        $actorSnapshot,
        text_substr($action, 0, 80),
        text_substr($entityType, 0, 60),
        $entityId === null ? null : text_substr((string)$entityId, 0, 100),
        json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        text_substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null,
        text_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255) ?: null,
    ]);
}

function audit_log_write(string $action, string $entityType, string|int|null $entityId, array $details = [], ?int $actorUserId = null): void
{
    try {
        audit_log_insert(db(), $action, $entityType, $entityId, $details, $actorUserId);
    } catch (Throwable $e) {
        error_log('Audit write failed: ' . $e->getMessage());
    }
}

function audit_log_write_strict(PDO $pdo, string $action, string $entityType, string|int|null $entityId, array $details = [], ?int $actorUserId = null): void
{
    audit_log_insert($pdo, $action, $entityType, $entityId, $details, $actorUserId);
}

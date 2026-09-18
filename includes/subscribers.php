<?php
declare(strict_types=1);

function subscriber_mobile_normalize(string $mobile): string
{
    $digits = preg_replace('/\D+/', '', en_digits($mobile)) ?? '';
    if (str_starts_with($digits, '0098')) $digits = '0' . substr($digits, 4);
    elseif (str_starts_with($digits, '98')) $digits = '0' . substr($digits, 2);
    return text_substr($digits, 0, 20);
}

function subscriber_balance(PDO $pdo, int $subscriberId, bool $forUpdate = false): int
{
    $sql = 'SELECT balance_after FROM subscriber_ledger WHERE subscriber_id=? ORDER BY id DESC LIMIT 1';
    if ($forUpdate) $sql .= ' FOR UPDATE';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$subscriberId]);
    $value = $stmt->fetchColumn();
    return $value === false ? 0 : (int)$value;
}

function subscriber_find_active(PDO $pdo, string $query, int $limit = 20): array
{
    $query = trim($query);
    $limit = max(1, min(50, $limit));
    if ($query === '') return [];

    $mobile = subscriber_mobile_normalize($query);
    $hasDigits = $mobile !== '' && preg_match('/\d/', en_digits($query)) === 1;
    $select = "SELECT s.*,COALESCE((SELECT l.balance_after FROM subscriber_ledger l WHERE l.subscriber_id=s.id ORDER BY l.id DESC LIMIT 1),0) balance FROM subscribers s WHERE s.active=1";
    if ($hasDigits) {
        if (strlen($mobile) < 3) return [];
        $stmt = $pdo->prepare($select . " AND s.mobile_normalized LIKE ? ORDER BY (s.mobile_normalized=?) DESC,(s.mobile_normalized LIKE ?) DESC,s.name,s.id LIMIT {$limit}");
        $stmt->execute(['%' . $mobile . '%', $mobile, $mobile . '%']);
        return $stmt->fetchAll();
    }

    if (text_length($query) < 2) return [];
    $stmt = $pdo->prepare($select . " AND s.name LIKE ? ORDER BY (s.name=?) DESC,(s.name LIKE ?) DESC,s.name,s.id LIMIT {$limit}");
    $stmt->execute(['%' . $query . '%', $query, $query . '%']);
    return $stmt->fetchAll();
}

function subscriber_invoice_snapshot_locked(PDO $pdo, array $invoice, string $tableName, ?string $invoiceNumber = null): array
{
    $number = $invoiceNumber ?: ('I-' . (int)$invoice['session']['id']);
    $invoice['session']['table_name'] = $tableName;
    return settlement_invoice_snapshot_locked($pdo, $invoice, $number);
}

/** Caller must own a transaction. */
function subscriber_insert_ledger_locked(
    PDO $pdo,
    int $subscriberId,
    string $entryType,
    int $amountDelta,
    int $actorUserId,
    ?int $sessionId = null,
    ?int $relatedEntryId = null,
    ?string $reference = null,
    ?string $reason = null,
    ?array $invoiceSnapshot = null,
    ?int $financialPeriodId = null,
    ?string $idempotencyKey = null
): array {
    $subscriberStmt = $pdo->prepare('SELECT id,name,active FROM subscribers WHERE id=? FOR UPDATE');
    $subscriberStmt->execute([$subscriberId]);
    $subscriber = $subscriberStmt->fetch();
    if (!$subscriber) throw new RuntimeException('مشترک پیدا نشد.');

    $idempotencyKey = trim((string)$idempotencyKey) ?: null;
    if ($idempotencyKey !== null) {
        $duplicate = $pdo->prepare('SELECT * FROM subscriber_ledger WHERE idempotency_key=? LIMIT 1 FOR UPDATE');
        $duplicate->execute([text_substr($idempotencyKey, 0, 190)]);
        $existing = $duplicate->fetch();
        if ($existing) {
            if ((int)$existing['subscriber_id'] !== $subscriberId || (string)$existing['entry_type'] !== $entryType || (int)$existing['amount_delta'] !== $amountDelta) {
                throw new RuntimeException('شناسه درخواست با یک عملیات دیگر تداخل دارد. صفحه را تازه کنید.');
            }
            return [
                'id'=>(int)$existing['id'],
                'subscriber'=>$subscriber,
                'balance_before'=>(int)$existing['balance_after'] - (int)$existing['amount_delta'],
                'balance_after'=>(int)$existing['balance_after'],
                'amount_delta'=>(int)$existing['amount_delta'],
                'entry_type'=>(string)$existing['entry_type'],
                'idempotent'=>true,
            ];
        }
    }

    $current = subscriber_balance($pdo, $subscriberId, true);
    $next = $current + $amountDelta;
    if ($next < 0) throw new RuntimeException('مبلغ پرداخت از مانده حساب بیشتر است.');
    if ($entryType === 'invoice' && (int)$subscriber['active'] !== 1) throw new RuntimeException('این مشترک غیرفعال است.');

    if (($financialPeriodId ?? 0) < 1) $financialPeriodId = (int)financial_period_for_date_locked($pdo, business_current_date(), $actorUserId)['id'];

    $snapshotJson = $invoiceSnapshot === null ? null : json_encode($invoiceSnapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $stmt = $pdo->prepare('INSERT INTO subscriber_ledger(subscriber_id,financial_period_id,entry_type,amount_delta,balance_after,table_session_id,related_entry_id,reference,reason,invoice_snapshot_json,actor_user_id,idempotency_key) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)');
    $stmt->execute([
        $subscriberId,
        $financialPeriodId,
        $entryType,
        $amountDelta,
        $next,
        $sessionId,
        $relatedEntryId,
        $reference !== '' ? $reference : null,
        $reason !== '' ? $reason : null,
        $snapshotJson,
        $actorUserId,
        $idempotencyKey === null ? null : text_substr($idempotencyKey, 0, 190),
    ]);
    return [
        'id' => (int)$pdo->lastInsertId(),
        'subscriber' => $subscriber,
        'balance_before' => $current,
        'balance_after' => $next,
        'amount_delta' => $amountDelta,
        'entry_type' => $entryType,
        'idempotent' => false,
    ];
}

/** Caller must own a transaction. */
function subscriber_reverse_entry_locked(PDO $pdo, int $entryId, string $reason, int $actorUserId): array
{
    $reason = text_substr(trim($reason), 0, 300);
    if ($reason === '') throw new RuntimeException('دلیل برگشت را وارد کن.');
    $stmt = $pdo->prepare('SELECT * FROM subscriber_ledger WHERE id=? FOR UPDATE');
    $stmt->execute([$entryId]);
    $entry = $stmt->fetch();
    if (!$entry) throw new RuntimeException('سند مشترک پیدا نشد.');
    if (!in_array((string)$entry['entry_type'], ['invoice','payment'], true)) throw new RuntimeException('این سند قابل برگشت نیست.');
    $check = $pdo->prepare('SELECT id FROM subscriber_ledger WHERE related_entry_id=? LIMIT 1 FOR UPDATE');
    $check->execute([$entryId]);
    if ($existing = (int)($check->fetchColumn() ?: 0)) {
        return ['id'=>$existing,'idempotent'=>true,'subscriber_id'=>(int)$entry['subscriber_id']];
    }
    $reverseType = (string)$entry['entry_type'] === 'invoice' ? 'invoice_reversal' : 'payment_reversal';
    $result = subscriber_insert_ledger_locked(
        $pdo,
        (int)$entry['subscriber_id'],
        $reverseType,
        -((int)$entry['amount_delta']),
        $actorUserId,
        null,
        $entryId,
        (string)($entry['reference'] ?? ''),
        $reason,
        null
    );
    $result['idempotent'] = false;
    return $result;
}

function subscriber_entry_type_label(string $type): string
{
    return [
        'invoice' => 'فاکتور کافه',
        'payment' => 'پرداخت',
        'invoice_reversal' => 'برگشت فاکتور',
        'payment_reversal' => 'برگشت پرداخت',
    ][$type] ?? $type;
}

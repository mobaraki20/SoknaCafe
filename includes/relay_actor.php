<?php
declare(strict_types=1);

function sokna_relay_actor_id(string $projectionId): int
{
    return preg_match('/^user:(\d+)$/',$projectionId,$match)?(int)$match[1]:0;
}

function sokna_relay_actor_locked(PDO $pdo,string $projectionId): array
{
    $id=sokna_relay_actor_id($projectionId);
    if($id<1)throw new RuntimeException('هویت کاربر راه‌دور معتبر نیست.');
    $stmt=$pdo->prepare('SELECT id,username,display_name,role,active FROM users WHERE id=? FOR UPDATE');
    $stmt->execute([$id]);$user=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$user||(int)$user['active']!==1)throw new RuntimeException('حساب کاربری دیگر فعال نیست.');
    return $user;
}

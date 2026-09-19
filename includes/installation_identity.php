<?php
declare(strict_types=1);

require_once __DIR__ . '/observability.php';

const SOKNA_INSTALLATION_IDENTITY_FORMAT = 'sokna-installation-identity-v1';

function sokna_installation_identity_dir(): string
{
    return sokna_data_root() . DIRECTORY_SEPARATOR . 'identity';
}

function sokna_installation_identity_metadata_path(): string
{
    return sokna_installation_identity_dir() . DIRECTORY_SEPARATOR . 'installation.json';
}

function sokna_installation_identity_private_path(): string
{
    return sokna_installation_identity_dir() . DIRECTORY_SEPARATOR . 'installation.key';
}

function sokna_installation_identity_require_crypto(): void
{
    if (!function_exists('sodium_crypto_sign_keypair') || !function_exists('sodium_crypto_sign_publickey_from_secretkey')) {
        throw new RuntimeException('Sodium برای هویت نصب لازم است.');
    }
}

function sokna_installation_identity_validate(array $metadata, string $secretKey): array
{
    sokna_installation_identity_require_crypto();
    if (($metadata['format'] ?? '') !== SOKNA_INSTALLATION_IDENTITY_FORMAT) throw new RuntimeException('قالب هویت نصب معتبر نیست.');
    $id = trim((string)($metadata['installation_id'] ?? ''));
    if (!preg_match('/^inst_[a-f0-9]{32}$/', $id)) throw new RuntimeException('شناسه هویت نصب معتبر نیست.');
    $secret = base64_decode(trim($secretKey), true);
    if (!is_string($secret) || strlen($secret) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) throw new RuntimeException('کلید خصوصی هویت نصب معتبر نیست.');
    $public = sodium_crypto_sign_publickey_from_secretkey($secret);
    $publicB64 = base64_encode($public);
    $fingerprint = strtoupper(substr(hash('sha256', $public), 0, 20));
    if (!hash_equals((string)($metadata['public_key_b64'] ?? ''), $publicB64) || !hash_equals((string)($metadata['public_key_fingerprint'] ?? ''), $fingerprint)) {
        sodium_memzero($secret);
        throw new RuntimeException('هویت نصب با کلید خصوصی آن یکسان نیست.');
    }
    sodium_memzero($secret);
    return [
        'format'=>SOKNA_INSTALLATION_IDENTITY_FORMAT,
        'installation_id'=>$id,
        'public_key_b64'=>$publicB64,
        'public_key_fingerprint'=>$fingerprint,
        'created_at'=>(string)($metadata['created_at'] ?? ''),
    ];
}

function sokna_installation_identity_ensure(): array
{
    sokna_installation_identity_require_crypto();
    $dir = sokna_installation_identity_dir();
    if (!sokna_ensure_private_dir($dir)) throw new RuntimeException('پوشه خصوصی هویت نصب آماده نیست.');
    $metaPath = sokna_installation_identity_metadata_path();
    $privatePath = sokna_installation_identity_private_path();
    $hasMeta = is_file($metaPath);
    $hasPrivate = is_file($privatePath);
    if ($hasMeta xor $hasPrivate) throw new RuntimeException('هویت نصب ناقص است و نباید خودکار بازتولید شود.');
    if ($hasMeta && $hasPrivate) {
        $metadata = json_decode((string)file_get_contents($metaPath), true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($metadata)) throw new RuntimeException('هویت نصب قابل خواندن نیست.');
        return sokna_installation_identity_validate($metadata, (string)file_get_contents($privatePath));
    }

    $pair = sodium_crypto_sign_keypair();
    $secret = sodium_crypto_sign_secretkey($pair);
    $public = sodium_crypto_sign_publickey($pair);
    $metadata = [
        'format'=>SOKNA_INSTALLATION_IDENTITY_FORMAT,
        'installation_id'=>'inst_' . bin2hex(random_bytes(16)),
        'public_key_b64'=>base64_encode($public),
        'public_key_fingerprint'=>strtoupper(substr(hash('sha256', $public), 0, 20)),
        'created_at'=>gmdate('Y-m-d\\TH:i:s\\Z'),
    ];
    $metaTmp = $metaPath . '.tmp-' . bin2hex(random_bytes(4));
    $privateTmp = $privatePath . '.tmp-' . bin2hex(random_bytes(4));
    try {
        if (file_put_contents($privateTmp, base64_encode($secret), LOCK_EX) === false) throw new RuntimeException('کلید خصوصی هویت نصب ثبت نشد.');
        @chmod($privateTmp, 0600);
        $json = json_encode($metadata, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
        if (file_put_contents($metaTmp, $json, LOCK_EX) === false) throw new RuntimeException('Metadata هویت نصب ثبت نشد.');
        @chmod($metaTmp, 0640);
        if (!rename($privateTmp, $privatePath)) throw new RuntimeException('کلید خصوصی هویت نصب فعال نشد.');
        if (!rename($metaTmp, $metaPath)) { @unlink($privatePath); throw new RuntimeException('Metadata هویت نصب فعال نشد.'); }
        @chmod($privatePath, 0600);
        @chmod($metaPath, 0640);
    } catch (Throwable $e) {
        @unlink($privateTmp); @unlink($metaTmp);
        sodium_memzero($secret);
        throw $e;
    }
    sodium_memzero($secret);
    return $metadata;
}

function sokna_installation_identity_public_metadata(): array
{
    return sokna_installation_identity_ensure();
}

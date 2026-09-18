<?php
declare(strict_types=1);

function app_release_version(): string { return '1.36.4-dev.22-test'; }
require dirname(__DIR__) . '/includes/printing.php';

function phase5_need(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$temp = sys_get_temp_dir() . '/sokna-agent-release-' . bin2hex(random_bytes(5)) . '.json';
$body = json_encode([
    'tag_name' => 'v6.2.9',
    'draft' => false,
    'prerelease' => false,
    'published_at' => '2026-09-12T10:00:00Z',
    'assets' => [[
        'name' => 'Sokna-Print-Agent-6.2.9-Setup.exe',
        'state' => 'uploaded',
        'digest' => 'sha256:' . str_repeat('a', 64),
        'browser_download_url' => 'https://evil.example.invalid/ignored.exe',
    ]],
], JSON_UNESCAPED_SLASHES);

$calls = 0;
$fetch = static function (string $url) use (&$calls, $body): array {
    $calls++;
    phase5_need($url === 'https://api.github.com/repos/mobaraki20/Pagent/releases/latest', 'unexpected GitHub endpoint');
    return ['status' => 200, 'body' => (string)$body, 'error' => ''];
};

$meta = print_agent_release_metadata(true, $fetch, $temp);
phase5_need($meta['version'] === '6.2.9', 'latest stable version not resolved');
phase5_need($meta['tag'] === 'v6.2.9', 'release tag not preserved');
phase5_need($meta['setup_filename'] === 'Sokna-Print-Agent-6.2.9-Setup.exe', 'Setup asset name mismatch');
phase5_need($meta['download_url'] === 'https://github.com/mobaraki20/Pagent/releases/download/v6.2.9/Sokna-Print-Agent-6.2.9-Setup.exe', 'download URL must be derived from fixed trusted repo');
phase5_need($meta['asset_sha256'] === str_repeat('a', 64), 'GitHub asset digest not preserved');
phase5_need(is_file($temp), 'last-known-good cache not written');
phase5_need($calls === 1, 'unexpected fetch count');

// A fresh cache must avoid network completely.
$networkTouched = false;
$cached = print_agent_release_metadata(false, static function (string $url) use (&$networkTouched): array {
    $networkTouched = true;
    return ['status' => 500, 'body' => '', 'error' => 'should_not_run'];
}, $temp);
phase5_need(!$networkTouched, 'fresh cache unexpectedly touched GitHub');
phase5_need($cached['version'] === '6.2.9', 'fresh cache lost latest version');
phase5_need($cached['source'] === 'cache', 'fresh cache source not reported');

// Once stale, a failed refresh must retain the same last-known-good release.
$raw = json_decode((string)file_get_contents($temp), true);
$raw['checked_at'] = time() - print_agent_release_cache_ttl_seconds() - 10;
file_put_contents($temp, json_encode($raw, JSON_UNESCAPED_SLASHES));
$stale = print_agent_release_metadata(false, static fn(string $url): array => ['status' => 503, 'body' => '', 'error' => 'offline'], $temp);
phase5_need($stale['version'] === '6.2.9', 'stale LKG not retained on GitHub failure');
phase5_need($stale['source'] === 'cache-stale', 'stale cache state not exposed');

@unlink($temp);

// No cache + invalid release response must fail soft to the embedded official stable bootstrap.
$temp2 = sys_get_temp_dir() . '/sokna-agent-release-' . bin2hex(random_bytes(5)) . '.json';
$fallback = print_agent_release_metadata(true, static fn(string $url): array => [
    'status' => 200,
    'body' => json_encode(['tag_name'=>'v9.9.9','draft'=>false,'prerelease'=>false,'assets'=>[]]),
    'error' => '',
], $temp2);
phase5_need($fallback['version'] === '6.2.2', 'safe fallback version mismatch');
phase5_need($fallback['source'] === 'fallback', 'invalid release must use fallback');
@unlink($temp2);

echo "PASS dev22 phase5 agent release resolver\n";

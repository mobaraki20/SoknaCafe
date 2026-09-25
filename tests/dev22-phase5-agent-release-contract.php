<?php
declare(strict_types=1);

// Historical filename retained because older evidence references it. Phase7R superseded
// external GitHub release resolution: distribution now belongs to the internal SOKNA component.
function app_release_version(): string { return '1.36.4-dev.38-test'; }
require dirname(__DIR__) . '/includes/printing.php';

function phase5_need(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$meta = print_worker_component_metadata();
phase5_need(($meta['version'] ?? '') === '6.2.5', 'internal Print Worker baseline mismatch');
phase5_need(($meta['ownership'] ?? '') === 'sokna-local-internal', 'Print Worker ownership must be internal to SOKNA Local');
phase5_need(preg_match('/^[a-f0-9]{64}$/', (string)($meta['source_sha256'] ?? '')) === 1, 'internal source provenance SHA256 missing');
phase5_need(print_agent_recommended_version() === '6.2.5', 'recommended compatibility version must follow internal baseline');
phase5_need(print_agent_minimum_version() === '6.2.5', 'minimum compatibility version must follow internal baseline');
phase5_need(!function_exists('print_agent_release_metadata'), 'external GitHub release resolver must stay removed');
phase5_need(!function_exists('print_agent_download_url'), 'external Agent download helper must stay removed');

echo "PASS dev22 historical distribution contract migrated to internal Print Worker ownership\n";

#!/usr/bin/env python3
from pathlib import Path
R=Path(__file__).resolve().parents[1]
read=lambda p:(R/p).read_text(encoding='utf-8')
business=read('includes/accommodation.php')
transport=read('includes/accommodation_transport.php')
modules=read('includes/modules.php')
api_contract=read('tests/accommodation-api-contract.php')

checks={
 'transport owner exists': 'function accommodation_transport_request' in transport,
 'business owner requires transport owner': "require_once __DIR__ . '/accommodation_transport.php';" in business,
 'compatibility API delegates to transport': 'return accommodation_transport_request($action,$method,$parameters,$timeoutSeconds);' in business,
 'business owner has no HTTP engine': all(token not in business for token in ['curl_init','stream_context_create','SOKNA_ACCOMMODATION_TRANSPORT','CURLOPT_']),
 'transport owns test injection and HTTPS engines': all(token in transport for token in ['SOKNA_ACCOMMODATION_TRANSPORT','curl_init','stream_context_create','CURLOPT_SSL_VERIFYPEER','CURLOPT_SSL_VERIFYHOST']),
 'transport preserves auth and tracking headers': 'Authorization: Bearer ' in transport and 'X-Tracking-ID: ' in transport and 'X-Sokna-Tracking-ID:' in transport,
 'transport preserves release user-agent': "Sokna-Cafe/' . app_release_version()" in transport,
 'transport does not own business persistence': all(token not in transport for token in ['accommodation_transfers','settlement_finalize_locked','settlement_reopen_locked','INSERT INTO','UPDATE accommodation_','DELETE FROM']),
 'module registry names both owners': 'includes/accommodation.php + includes/accommodation_transport.php + admin/accommodation*.php' in modules,
 'existing contract still targets compatibility API': "accommodation_http_request('search'" in api_contract and 'accommodation_transport_request' not in api_contract,
}
failed=[name for name,ok in checks.items() if not ok]
for name,ok in checks.items(): print(('PASS' if ok else 'FAIL')+': '+name)
if failed: raise SystemExit('Phase 7B accommodation transport contract failed: '+', '.join(failed))
print(f'Phase 7B accommodation transport contract PASS: {len(checks)} checks.')

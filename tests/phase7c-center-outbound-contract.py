#!/usr/bin/env python3
from pathlib import Path
R=Path(__file__).resolve().parents[1]
read=lambda p:(R/p).read_text(encoding='utf-8')
center=read('includes/sokna_center.php')
projection=read('includes/sokna_center_projection.php')
runtime=read('includes/runtime.php')
modules=read('includes/modules.php')
legacy=read('api/sokna_center_users.php')

checks={
 'runtime owns outbound Center projection worker': "'center_projection' => [" in runtime and 'tools/center-projection-worker.php' in runtime,
 'projection is capability gated': 'sokna_center_outbound_user_projection_supported()' in projection and "'status'=>'unsupported'" in projection,
 'probe stores negotiated capability': 'sokna_center_store_remote_capabilities($data);' in center and 'user_projection_v1' in center,
 'projection uses only legacy allow-list columns': 'SELECT id,display_name,role,active,updated_at FROM users' in projection,
 'projection reuses canonical public mapper': 'sokna_center_directory_public_user($row)' in projection,
 'projection excludes credentials': all(x not in projection.lower() for x in ['password_hash','username','csrf_token','session_id']),
 'projection is versioned and idempotent by content hash': "'source_version'=>hash('sha256'" in projection and "'source_version'=>$sourceVersion" in projection,
 'outbound token is Cafe to Center S2S': all(x in projection for x in ["'issuer'=>'cafe'","'audience'=>'center'","'purpose'=>'user_projection'","'context'=>'CAFE'","'SOKNA-S2S'"]),
 'outbound endpoint is explicit': '/api/s2s/cafe_users_sync.php' in projection,
 'ack must match source version': "hash_equals((string)$projection['source_version'], $ack)" in projection,
 'legacy inbound directory remains compatibility fallback': 'sokna_center_verify_user_directory_request' in legacy and 'legacy inbound user-directory compatibility endpoint' in modules,
 'worker is not in request bootstrap path': 'center-projection-worker.php' not in read('bootstrap.php'),
 'personnel module owns projection worker': 'SOKNA Runtime -> tools/center-projection-worker.php' in modules,
}
failed=[n for n,v in checks.items() if not v]
for n,v in checks.items(): print(('PASS' if v else 'FAIL')+': '+n)
if failed: raise SystemExit('Phase 7C Center outbound contract failed: '+', '.join(failed))
print(f'Phase 7C Center outbound contract PASS: {len(checks)} checks.')

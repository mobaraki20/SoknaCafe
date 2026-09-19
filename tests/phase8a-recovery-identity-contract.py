#!/usr/bin/env python3
from pathlib import Path
R=Path(__file__).resolve().parents[1]
read=lambda p:(R/p).read_text(encoding='utf-8')
identity=read('includes/installation_identity.php')
maint=read('includes/maintenance.php')
schema=read('docs/architecture-migration-r2/SCHEMA_CHANGE_PLAN.md')

checks={
 'identity lives under private data root': "sokna_data_root() . DIRECTORY_SEPARATOR . 'identity'" in identity,
 'identity uses dedicated signing keypair': 'sodium_crypto_sign_keypair' in identity and 'SODIUM_CRYPTO_SIGN_SECRETKEYBYTES' in identity,
 'private key is separate from public metadata': "installation.key" in identity and "installation.json" in identity and "public_key_b64" in identity,
 'partial identity fails closed': 'if ($hasMeta xor $hasPrivate)' in identity and 'نباید خودکار بازتولید شود' in identity,
 'backup still preserves app key for integration-secret decryption': "maintenance_add_string($archive, $appKey, 'system/app.key'" in maint,
 'backup adds recovery metadata': "'recovery_metadata'=>maintenance_recovery_metadata()" in maint,
 'recovery metadata explicitly forbids private identity cloning': "'private_identity_cloned'=>false" in maint,
 'recovery metadata carries public binding without secret': "'shared_secret_fingerprint'" in maint and "'public_host'" in maint,
 'recovery metadata carries media/theme references': "'media_theme'=>[" in maint and "'menu_theme'" in maint and "'logo_path'" in maint,
 'recovery metadata carries integration references': "'integrations'=>[" in maint and "'secret_fingerprint'" in maint,
 'private identity is never archived': "system/installation.key" not in maint and "installation.key', $entries" not in maint,
 'phase8 schema forbids cloning old private key': 'Old private key' in schema and 'clone' in schema,
}
failed=[n for n,v in checks.items() if not v]
for n,v in checks.items(): print(('PASS' if v else 'FAIL')+': '+n)
if failed: raise SystemExit('Phase 8A recovery identity contract failed: '+', '.join(failed))
print(f'Phase 8A recovery identity contract PASS: {len(checks)} checks.')

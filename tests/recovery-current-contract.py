#!/usr/bin/env python3
from pathlib import Path
import subprocess, tempfile, json, time, os
ROOT=Path(__file__).resolve().parents[1]
read=lambda p:(ROOT/p).read_text(encoding='utf-8')
maint=read('includes/maintenance.php'); admin=read('admin/maintenance.php')
runtime=read('includes/updater_engine/1.5.3/runtime.php'); console=read('includes/updater_engine/1.5.3/console.php')
for needle in ['operations.lock','CAFE-SQL-FRAMED-V2','maintenance_backup_sidecar_path','maintenance_list_backups(bool $validate = true)','recovery_required','recovery_point','maintenance_estimated_data_bytes']:
    assert needle in maint, needle
assert 'maintenance_page_release_session' in admin and 'session_write_close()' in admin
assert "maintenance_list_backups(false)" in admin, 'page GET must use lightweight backup list'
assert 'sur_restore_emergency_data' in runtime
assert "'data_recovery_state' => $dataRecoveryState" in runtime
assert "!$dataRecoveryState" in runtime and 'legacy_maintenance' in runtime
assert 'بازگرداندن وضعیت قبل از Restore' in console and 'بازگشت اضطراری' in console
# Runtime load contract: hundreds of fake backup names with sidecars must not open archives when validate=false.
with tempfile.TemporaryDirectory() as td:
    root=Path(td)
    b=root/'storage'/'backups'; b.mkdir(parents=True)
    (root/'VERSION.txt').write_text('1.30.2')
    for i in range(250):
        name=f"sokna-backup-20260808-{120000+i:06d}-deadbeef.tar.gz"
        p=b/name; p.write_bytes(b'not-an-archive')
        meta={'format':'sokna-backup-meta-v2','name':name,'size':14,'manifest':{'format':'sokna-backup-v3','type':'backup','version':'1.30.2','portable_app_identity':True},'valid':None}
        (b/(name+'.meta.json')).write_text(json.dumps(meta))
    php=f"define('SOKNA_MAINTENANCE_ROOT',{json.dumps(str(root))}); require {json.dumps(str(ROOT/'includes/maintenance.php'))}; $t=microtime(true); $x=maintenance_list_backups(false); echo count($x).'|'.(microtime(true)-$t);"
    out=subprocess.check_output(['php','-r',php],text=True)
    count,elapsed=out.strip().split('|'); assert int(count)==250
    assert float(elapsed)<1.0, elapsed
print('Recovery current contracts passed: fail-closed state, independent recovery, session release, lightweight list.')

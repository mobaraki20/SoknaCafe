#!/usr/bin/env python3
from pathlib import Path
import json, subprocess, tempfile, time

ROOT = Path(__file__).resolve().parents[1]
maint = (ROOT/'includes/maintenance.php').read_text(encoding='utf-8')
page = (ROOT/'admin/maintenance.php').read_text(encoding='utf-8')
dash = (ROOT/'admin/index.php').read_text(encoding='utf-8')
install = (ROOT/'install.php').read_text(encoding='utf-8')

assert 'const MAINTENANCE_AUTO_BACKUP_HOURS = 6;' in maint
assert 'const MAINTENANCE_BACKUP_KEEP = 28;' in maint
assert 'const MAINTENANCE_WORKER_HEARTBEAT_HOURS = 12;' in maint
assert 'const MAINTENANCE_OFFSERVER_REMINDER_DAYS = 7;' in maint
assert 'backup_retention_count' not in page
assert 'backup_retention_count' not in install
assert 'سیاست نگهداری' not in page
assert 'دانلود امن آخرین پشتیبان سالم' in page
assert '۲۸ نسخه اخیر' in page and 'هر ۶ ساعت' in page
assert 'بازیابی اطلاعات' in page
assert 'مرکز به‌روزرسانی و بازیابی' in page
assert 'maintenance_record_offserver_export' in maint
assert 'maintenance_backup_worker_status' in maint
assert 'maintenance_record_backup_worker_run' in maint
assert "'backup.offserver_exported'" in maint
assert "if ($action === 'export_secure')" in page
assert 'MAINTENANCE_SECURE_BACKUP_FORMAT' in maint and 'sodium_crypto_secretstream_xchacha20poly1305' in maint
assert '?download=' not in page
assert 'connection_status() === CONNECTION_NORMAL' in page
assert 'نسخه خارج از سرور به‌روز نیست.' in dash
assert 'پشتیبان خودکار' in page and '۲۸ نسخه اخیر روی سرور نگهداری می‌شود' in page
assert 'maintenance_offserver_reminder_bootstrap();' in dash

# Final on-disk backup must pass exact validation before it is marked valid; pruning happens only after the validated creator returns.
unlocked_i = maint.index('function maintenance_create_backup_unlocked')
rename_i = maint.index("rename($tarPath . '.gz', $finalPath)", unlocked_i)
validate_i = maint.index('maintenance_validate_archive($finalPath)', rename_i)
sidecar_i = maint.index("maintenance_write_backup_sidecar($finalPath,$validated['manifest'],true)", validate_i)
assert rename_i < validate_i < sidecar_i
wrapper_i = maint.index('function maintenance_create_backup(?int $actorUserId')
wrapper_end = maint.index('function maintenance_open_archive', wrapper_i)
wrapper = maint[wrapper_i:wrapper_end]
assert "maintenance_create_backup_unlocked('backup',$actorUserId)" in wrapper and 'maintenance_prune_backups();' in wrapper

# Product retention owns one user backup stream; recovery points are internal and capped separately.
prune_start = maint.index('function maintenance_prune_backups')
prune_end = maint.index('function maintenance_setting_write', prune_start)
prune_body = maint[prune_start:prune_end]
assert "sokna-backup-*.tar.gz" in prune_body
assert "cafe-backup-" not in maint
assert 'latestKnownGood' in prune_body
assert 'MAINTENANCE_RECOVERY_POINT_KEEP' in prune_body


def make_backup(root: Path, kind: str, idx: int, valid, mtime: int):
    bdir = root/'storage'/'backups'
    bdir.mkdir(parents=True, exist_ok=True)
    name = (f'sokna-backup-20260809-{120000+idx:06d}-deadbeef.tar.gz' if kind=='backup' else f'sokna-recovery-point-20260809-{120000+idx:06d}-deadbeef.tar.gz')
    p = bdir/name
    p.write_bytes(b'x')
    p.touch()
    import os
    os.utime(p, (mtime, mtime))
    meta = {
        'format':'sokna-backup-meta-v2', 'name':name, 'size':1,
        'modified_at':'2026-08-09T12:00:00+03:30',
        'manifest':{'format':'sokna-backup-v3','type':('backup' if kind=='backup' else 'recovery_point'),'version':'1.30.5','portable_app_identity':True},
        'valid':valid
    }
    (bdir/(name+'.meta.json')).write_text(json.dumps(meta), encoding='utf-8')
    return p


def run_prune(root: Path):
    php = (
        f"define('SOKNA_MAINTENANCE_ROOT',{json.dumps(str(root))});"
        f"require {json.dumps(str(ROOT/'includes/functions.php'))};"
        f"require {json.dumps(str(ROOT/'includes/maintenance.php'))};"
        "maintenance_prune_backups();"
    )
    subprocess.check_call(['php','-r',php])

# Normal case: four six-hour recovery points per day for seven days, plus five internal recovery points.
with tempfile.TemporaryDirectory() as td:
    root=Path(td); now=int(time.time())
    for i in range(34): make_backup(root,'backup',i,True,now-i)
    for i in range(7): make_backup(root,'recovery_point',i,True,now-i)
    run_prune(root)
    bdir=root/'storage'/'backups'
    assert len(list(bdir.glob('sokna-backup-*.tar.gz')))==28
    assert len(list(bdir.glob('sokna-recovery-point-*.tar.gz')))==5

# Safety case: if the newest 28 are not known-good, preserve the older last-known-good as one extra safety file.
with tempfile.TemporaryDirectory() as td:
    root=Path(td); now=int(time.time())
    paths=[]
    for i in range(34):
        valid = True if i==30 else False
        paths.append(make_backup(root,'backup',i,valid,now-i))
    known_good=paths[30]
    run_prune(root)
    remaining=list((root/'storage'/'backups').glob('sokna-backup-*.tar.gz'))
    assert len(remaining)==29, [p.name for p in remaining]
    assert known_good.exists()

print('Backup manager contracts passed: six-hour cadence, 7-day retention, LKG protection, final validation, encrypted off-server export tracking, and manager-first UI contracts.')

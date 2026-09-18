#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
read=lambda p:(ROOT/p).read_text(encoding='utf-8')
maint=read('includes/maintenance.php'); page=read('admin/maintenance.php'); login=read('login.php')
assert 'function maintenance_current_app_key' in maint
assert 'function maintenance_config_set_app_key' in maint
assert "maintenance_add_string($archive, $appKey, 'system/app.key'" in maint
assert "'format'=>'sokna-backup-v3'" in maint and "'portable_app_identity'=>true" in maint
assert "maintenance_config_set_app_key($targetKey)" in maint
assert "maintenance_config_set_app_key($fallbackKey)" in maint
assert "'center_secret_decryptable'" in maint and "'accommodation_secret_decryptable'" in maint
assert 'function maintenance_import_backup_file' in maint
assert 'function maintenance_stage_backup_file' in maint and ".tar.gz'" in maint[maint.index('function maintenance_stage_backup_file'):maint.index('/** Import one portable backup')]
assert 'maintenance_secure_backup_is_file($sourcePath)' in maint
assert 'maintenance_secure_backup_decrypt_file($sourcePath, $stage, $passphrase)' in maint
assert 'maintenance_stage_backup_file($sourcePath)' in maint
assert "if ($action === 'upload')" in page and 'name="backup_file"' in page and 'enctype="multipart/form-data"' in page
assert "($b['manifest']['format'] ?? '') === 'sokna-backup-v3'" in page
assert "logout_user();" in page and "../login.php?restored=1" in page and "بازیابی با موفقیت کامل شد" in login
assert "empty($manifest['portable_app_identity'])" in maint
# One public backup creator remains; recovery points are internal rollback state only.
create=maint[maint.index('function maintenance_create_backup(?int $actorUserId'):maint.index('function maintenance_open_archive')]
assert "maintenance_create_backup_unlocked('backup',$actorUserId)" in create
assert "['quick','emergency']" not in maint and 'cafe-backup-' not in maint
assert 'function maintenance_safe_recovery_point_name' in maint
assert "maintenance_recovery_point_path((string)$recoveryPoint['name'])" in maint
print('dev17 portable backup contract PASS')

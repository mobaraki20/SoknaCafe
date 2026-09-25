#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
contract=(ROOT/'docs/architecture-migration-r2/PHASE8B_PACKAGE_OWNERSHIP_FA.md').read_text(encoding='utf-8')
accept=(ROOT/'docs/architecture-migration-r2/WINDOWS_INSTALLER_ACCEPTANCE_FA.md').read_text(encoding='utf-8')
installer=ROOT/'installer/windows'
texts=''
if installer.exists():
    for p in installer.rglob('*'):
        if p.is_file() and p.suffix.lower() in {'.wxs','.wixproj','.props','.targets','.ps1','.md'}:
            texts += '\n' + p.read_text(encoding='utf-8-sig', errors='replace')
checks={
    'live app explicitly updater-owned': 'Updater تنها owner فایل‌های **Live application payload**' in contract,
    'MSI live-tree harvest forbidden': 'MSI نباید Live application tree' in contract,
    'business data preserved by default': 'Business Data' in contract and 'به‌صورت پیش‌فرض پاک' in contract,
    'repair cannot downgrade updater-owned app': 'بازگرداندن seed قدیمی' in contract and 'ممنوع است' in contract,
    'print worker remains internal': 'هیچ Pagent MSI/Setup مستقل' in contract,
    'web runtime lifecycle not moved into local runtime': 'Runtime مالک lifecycle Apache/PHP نیست' in contract,
    'acceptance supersedes separate pagent chain': 'Pagent مستقل در Chain ممنوع است' in accept,
    'WiX toolchain pin recorded': 'WiX Toolset 7.0.0' in accept,
    'source does not auto-accept WiX EULA': '<AcceptEula>' not in texts and '-acceptEula' not in texts,
}
# Once authoring exists, guard against obvious architectural regressions.
if texts:
    checks.update({
        'installer authoring does not mention separate Pagent setup': 'Pagent-' not in texts and 'PrintAgentSetup' not in texts,
        'installer authoring identifies updater ownership': 'Updater' in texts or 'updater' in texts,
    })
failed=[k for k,v in checks.items() if not v]
for k,v in checks.items(): print(('PASS' if v else 'FAIL')+': '+k)
if failed: raise SystemExit('Phase 8B package ownership contract failed: '+', '.join(failed))
print(f'Phase 8B package ownership contract PASS: {len(checks)} checks.')

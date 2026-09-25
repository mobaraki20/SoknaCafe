from pathlib import Path

root=Path(__file__).resolve().parents[1]
deploy=(root/'installer/windows/scripts/deploy-seed.ps1').read_text(encoding='utf-8-sig')
setup=(root/'runtime/windows/setup-sokna.ps1').read_text(encoding='utf-8-sig')
prepare=(root/'installer/windows/scripts/prepare-shell-payload.ps1').read_text(encoding='utf-8-sig')

def need(cond,msg):
    if not cond: raise AssertionError(msg)
    print('PASS:',msg)

need('[switch]$PreflightOnly' in setup and "$Mode -eq 'Validate' -or $PreflightOnly" in setup, 'canonical Windows setup supports mutation-free preflight for New/Recover')
need("'installer\\windows\\scripts\\deploy-seed.ps1'" in prepare, 'seed deployer is part of installer-owned shell payload')
need("payload-manifest.json" in deploy and 'Test-ManifestFile' in deploy and 'Get-FileHash' in deploy, 'deployer verifies installer manifest size/hash before use')
need('$actual.Count -ne $seen.Count' in deploy and 'unmanifested file' in deploy, 'deployer rejects any shell file that is outside the verified manifest exact set')
need("ownership -ne 'msi-shell-cache-only'" in deploy and "live_app_owner -ne 'sokna-updater'" in deploy, 'deployer enforces MSI/updater ownership contract')
need('Expand-SoknaSeedSecure' in deploy and 'path traversal' in deploy and 'IsPathRooted' in deploy, 'seed extraction rejects rooted/traversal entries')
need("$stagedVersion -ne [string]$manifest.app_version" in deploy, 'seed version must match verified payload manifest')
need("'tools\\setup-machine.php'" in deploy and "'runtime\\sokna-runtime.php'" in deploy and "'database\\schema.sql'" in deploy, 'seed must contain canonical setup/runtime/schema owners')
need("'-PreflightOnly'" in deploy and deploy.index("'-PreflightOnly'") < deploy.index('[IO.Directory]::CreateDirectory($AppRoot)'), 'canonical preflight runs before application target creation')
need('@(0,3010)' in deploy and '$preflightExit -eq 3010' in deploy and '3010' in deploy[deploy.index('$finalExit='):], 'deployer preserves Windows reboot-required exit code 3010 without mutating after preflight')
need(deploy.count('Assert-EmptyInstallTarget $AppRoot') >= 2, 'empty target is rechecked immediately before deployment')
need("Invoke-SetupChild (Join-Path $AppRoot 'runtime\\windows\\setup-sokna.ps1')" in deploy, 'deployer delegates final mutation to canonical Windows setup owner')
need("'--mode='" not in deploy.lower() and 'admin_password' not in deploy and 'db.pass' not in deploy, 'seed deployer does not put business secrets on command line or reimplement business config')
need("@(0,20,21,3010)" in deploy and "if($finalExit -in @(20,21,3010)){exit $finalExit}" in deploy, 'seed deployer preserves canonical post-commit Apache/HTTPS exit codes for Setup Host/UI')
need("-not(Test-Path -LiteralPath (Join-Path $AppRoot 'config.php'))" in deploy and "-not(Test-Path -LiteralPath (Join-Path $AppRoot 'install.lock'))" in deploy, 'rollback deletes target only before persisted business setup markers')
need('Remove only paths that originated from this verified seed' in deploy and '$seedFiles=' in deploy and '$seedDirs=' in deploy, 'rollback removes only seed-owned paths and preserves unknown target content')
need("ValidateSet('New','Recover')" in deploy and 'Repair' not in deploy.split('param(',1)[1].split(')',1)[0], 'seed deployer cannot be used as Repair path')
print('Phase 8B seed deployment contract PASS.')

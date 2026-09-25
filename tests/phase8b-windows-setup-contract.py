#!/usr/bin/env python3
from pathlib import Path

ROOT=Path(__file__).resolve().parents[1]
read=lambda p:(ROOT/p).read_text(encoding='utf-8')

setup=read('includes/setup_install.php')
web=read('install.php')
cli=read('tools/setup-machine.php')
ps=read('runtime/windows/setup-sokna.ps1')
support=read('runtime/windows/setup-support.psm1')
host=read('runtime/windows/SoknaRuntimeService.cs')
maint=read('includes/maintenance.php')
readme=read('runtime/README_FA.md')

checks={
    'web install delegates to canonical setup owner':
        "require_once __DIR__ . '/includes/setup_install.php';" in web
        and 'sokna_setup_fresh_install($input,$root)' in web,

    'web install no longer owns schema or default seed':
        'CREATE TEMPORARY TABLE' not in web
        and 'sync_default_menu_seed' not in web,

    'setup owner supports new and recover validation':
        "sokna_setup_require_valid($input,'new')" in setup
        and "sokna_setup_require_valid($input,'recover')" in setup,

    'fresh install still requires empty database and privilege probe':
        'sokna_setup_assert_empty_database($pdo)' in setup
        and 'sokna_setup_probe_privileges($pdo)' in setup,

    'fresh install owns schema and canonical seed':
        'sokna_setup_apply_schema' in setup
        and 'sokna_setup_seed_new' in setup
        and 'sync_default_menu_seed($pdo, true)' in setup,

    'recover target remains uninstalled and empty':
        'sokna_setup_prepare_recovery_target' in setup
        and 'Recover فقط روی مقصد نصب‌نشده مجاز است.' in setup
        and 'sokna_setup_assert_empty_database($pdo)' in setup,

    'machine recovery bypass is narrowly empty-target only':
        'maintenance_restore_archive_to_empty_target' in maint
        and "query('SHOW TABLES')" in maint
        and 'maintenance_machine_recovery_compatibility' in maint,

    'machine recovery still requires exact app version':
        "$version !== maintenance_version()" in maint,

    'machine recovery failure remains fail closed':
        "'recovery_required'" in maint
        and "'fresh_target'=>true" in maint,

    'setup CLI reads sensitive values from files rather than secret arguments':
        "'config-file'" in cli
        and "'passphrase-file'" in cli
        and 'file_get_contents($configFile)' in cli
        and 'file_get_contents($passphraseFile)' in cli
        and '--db-pass=' not in cli
        and '--password=' not in cli,

    'recover creates fresh installation identity before business restore':
        cli.index('sokna_installation_identity_ensure()')
        < cli.index('maintenance_restore_external_backup_to_empty_target'),

    'service host is a real Windows ServiceBase host':
        'class SoknaRuntimeService : ServiceBase' in host
        and 'ServiceBase.Run(service)' in host,

    'service host launches only canonical runtime entrypoint':
        'Path.Combine(appRoot, "runtime", "sokna-runtime.php")' in host
        and 'SOKNA_DATA_DIR' in host,

    'service host does not duplicate business state':
        all(token not in host for token in [
            'push_event_queue','print_jobs','orders','inventory_','PDO','MySQL'
        ]),

    'Windows setup installs Service Host instead of php as SCM binary':
        'SoknaRuntimeService.exe' in ps
        and 'Install-RuntimeService' in ps
        and "'binPath='" in ps,

    'Windows setup has non-mutating hosted validation':
        "$Mode -eq 'Validate'" in ps
        and '--self-test' in ps,

    'Windows setup performs broad preflight before mutation':
        'Test-SoknaPendingReboot' in ps
        and 'Get-SoknaFreeBytes' in ps
        and 'MinimumFreeBytes' in ps
        and 'minimum_version_id' in ps
        and 'required_extensions' in ps
        and 'prerequisites.json' in ps
        and 'RequireWebServerPreflight' in ps
        and 'Get-SoknaTcpListenerOwners' in ps
        and "SOKNA_SETUP_REBOOT_REQUIRED" in ps,

    'Windows setup owns bundled internal Print Worker':
        'PrintWorkerBundle' in ps
        and 'Install-PrintWorkerComponent' in ps
        and 'SoknaPrintWorker' in ps
        and 'PrintAgentSetup' not in ps
        and 'PrintAgentSha256' not in ps,

    'runtime docs point to Phase 8B setup owner':
        'runtime/windows/setup-sokna.ps1' in readme
        and 'Canonical Windows setup owner' in readme,

    'secret-bearing setup inputs require private ACL':
        'Assert-SoknaPrivateInputFile' in support
        and '$SetupConfigFile = Assert-SoknaPrivateInputFile' in ps
        and '$RecoveryPassphraseFile = Assert-SoknaPrivateInputFile' in ps,

    'canonical setup accepts shared correlation session':
        'SOKNA_SETUP_SESSION_ID' in ps
        and "^[a-fA-F0-9]{32}$" in ps,

    'support bundle is structured, allowlisted and redacted':
        'Write-SoknaSupportSnapshot' in support
        and 'Get-SoknaServiceDiagnostic' in support
        and 'Get-SoknaPrintHealthDiagnostic' in support
        and 'Copy-SoknaSanitizedDiagnosticLog' in support
        and "'components.json'" in ps
        and 'SOKNA_BURN_LOG_PATH' in ps
        and 'SOKNA_MSI_LOG_PATH' in ps
        and 'Compress-Archive -LiteralPath $supportFiles' in ps
        and 'Compress-Archive -Path $session' not in ps,

    'Windows Repair gate protects updater-owned live payload':
        'live-updater-owned.txt' in read('tests/phase8b-windows-setup-runtime.ps1')
        and 'Repair rewrote updater-owned VERSION.txt' in read('tests/phase8b-windows-setup-runtime.ps1')
        and 'Failed Repair changed updater-owned live application payload' in read('tests/phase8b-windows-setup-runtime.ps1'),
}

failed=[name for name,ok in checks.items() if not ok]
for name,ok in checks.items():
    print(('PASS' if ok else 'FAIL')+': '+name)
if failed:
    raise SystemExit('Phase 8B Windows setup contract failed: '+', '.join(failed))
print(f'Phase 8B Windows setup contract PASS: {len(checks)} checks.')

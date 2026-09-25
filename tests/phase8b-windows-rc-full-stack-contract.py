from pathlib import Path
root=Path(__file__).resolve().parents[1]
script=(root/'tests/phase8b-windows-rc-full-stack.ps1').read_text(encoding='utf-8-sig')
workflow=(root/'.github/workflows/sokna-ci.yml').read_text(encoding='utf-8')
deploy=(root/'installer/windows/scripts/deploy-seed.ps1').read_text(encoding='utf-8-sig')

def need(cond,msg):
    if not cond: raise AssertionError(msg)
    print('PASS:',msg)

need("$env:GITHUB_ACTIONS -ne 'true'" in script and 'AllowDisposableMachine' in script,'full-stack acceptance refuses ordinary non-disposable Windows machines')
need('verify-prerequisite-bundle.ps1' in script and "Find-Artifact $manifest 'php'" in script and "Find-Artifact $manifest 'apache'" in script and "Find-Artifact $manifest 'mariadb'" in script and "Find-Artifact $manifest 'vc_runtime'" in script,'full-stack acceptance consumes the frozen verified prerequisite bundle')
need('php8apache2_4.dll' in script and 'pdo_mysql' in script and 'sodium' in script and 'mbstring' in script,'full-stack acceptance proves the real PHP TS/Apache extension surface')
need('\\"' not in script,'full-stack PowerShell does not use invalid C-style quote escaping')
need('msiexec.exe' in script and 'SERVICENAME=' in script and 'DATADIR=' in script and 'ADDLOCAL=DBInstance,Client,MYSQLSERVER,SharedLibraries' in script,'full-stack acceptance provisions a disposable real MariaDB Windows service from the frozen MSI')
need("@('-Mode','New'" in script and "@(20)" in script and 'Start-Apache' in script and "'-Mode','Repair'" in script,'New acceptance proves the external Apache reload boundary followed by Repair/HTTPS health')
need('backup-worker.php' in script and "@('-Mode','Recover'" in script and '$recoveredIdentity -ne $newIdentity' in script and "setting_key='cafe_name'" in script,'full-stack acceptance proves Recovery Set business restore without cloning machine identity')
need("@(0,20,21,3010)" in deploy and "if($finalExit -in @(20,21,3010)){exit $finalExit}" in deploy,'seed deployer propagates canonical post-commit health/reboot result codes')
need('run_windows_rc_full_stack:' in workflow and 'windows-rc-full-stack:' in workflow and 'phase8b-windows-rc-full-stack.ps1' in workflow,'full-stack acceptance is wired as an explicit Windows release-engineering CI job')
need("release-lock.json" in workflow[workflow.index('windows-rc-full-stack:'):],'full-stack RC job requires a committed frozen release lock before it can run')
print('Phase 8B Windows RC full-stack contract PASS.')

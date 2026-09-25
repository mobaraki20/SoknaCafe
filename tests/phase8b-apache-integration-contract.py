#!/usr/bin/env python3
from pathlib import Path
root=Path(__file__).resolve().parents[1]
read=lambda p:(root/p).read_text(encoding='utf-8-sig')
script=read('runtime/windows/configure-apache.ps1')
template=read('runtime/windows/apache/sokna-local-https.conf.template')
setup=read('runtime/windows/setup-sokna.ps1')
login=read('login.php')
prepare=read('installer/windows/scripts/prepare-shell-payload.ps1')
host=read('installer/windows/setup-host/Program.cs')
workflow=read('.github/workflows/sokna-ci.yml')

def need(c,m):
    if not c: raise AssertionError(m)
    print('PASS:',m)

need('{{SOKNA_WEB_ROOT}}' in template and '{{SOKNA_DATA_ROOT}}' in template and '{{SOKNA_HOSTNAME}}' in template and '{{SOKNA_HTTPS_PORT}}' in template,'Apache template has explicit WebRoot/DataRoot/host/port tokens')
need('Listen ' not in template,'SOKNA vhost does not take ownership of Apache listener lifecycle')
need('DocumentRoot "{{SOKNA_WEB_ROOT}}"' in template and '<Directory "{{SOKNA_DATA_ROOT}}">' in template and 'Require all denied' in template,'Apache template serves only WebRoot and explicitly denies DataRoot')
need('HTTPD_ROOT' in script and 'SERVER_CONFIG_FILE' in script and "('-V')" in script,'Apache owner discovers shared config from Apache itself instead of guessing paths')
need("GetFileName($exeDir).Equals('bin'" in script and 'executable-adjacent roots' in script and '$reportedExists -and $adjacentExists' in script,'relocated Apache is anchored to selected bin/httpd.exe and ambiguous ownership fails closed')
need('ssl_module' in script and 'headers_module' in script and 'authz_core_module' in script,'Apache owner requires modules used by the managed vhost')
need('Assert-DisjointRoots $AppRoot $DataRoot' in script,'AppRoot/WebRoot and DataRoot overlap is rejected')
need('# BEGIN SOKNA LOCAL MANAGED INCLUDE v1' in script and '# SOKNA-MANAGED-APACHE-VHOST v1' in script,'shared Apache edits use explicit SOKNA ownership markers')
need("'sokna-local.conf'" in script and 'Existing sokna-local.conf is not owned by SOKNA' in script,'SOKNA refuses to overwrite an unmanaged same-name include')
need('httpd.candidate.conf' in script and "@('-t','-f',$candidateMain)" in script,'candidate Apache config is syntax-checked before shared config mutation')
need('[IO.File]::ReadAllBytes($mainBackup)' in script and 'Remove-Item -LiteralPath $managedInclude' in script,'Apache mutation has explicit rollback for main config and managed include')
need('reload_required=[bool]($mainChanged -or $includeChanged)' in script and "lifecycle_owner='external'" in script and 'if(-not $result.changed)' in script,'Apache integration reports reload only for actual byte changes and converges idempotently')
for forbidden in ['Start-Service','Stop-Service',"'-k','restart'","'-k','graceful'",'Restart-Service']:
    need(forbidden not in script,f'Apache owner does not perform lifecycle action: {forbidden}')
need('configure-apache.ps1' in setup and '-ValidateOnly' in setup and "$stage = 'web-server-config'" in setup,'canonical Windows setup validates then applies the Apache owner')
need('web_server_reload_required' in setup,'setup summary exposes external Apache reload requirement')
need('Test-SoknaLocalWebHealth' in setup and 'SOKNA_SETUP_WEB_RELOAD_REQUIRED' in setup and '$exitCode = 20' in setup and 'SOKNA_SETUP_WEB_HEALTH' in setup and '$exitCode = 21' in setup,'setup cannot report final success until external Apache reload and local HTTPS health are proven')
need("header('X-Sokna-Runtime: ' . ($soknaRuntimeReady ? 'php-ready' : 'php-incompatible'));" in login and "$response.Headers['X-Sokna-Runtime']" in setup and "$runtimeHeader -ne 'php-ready'" in setup and "$body -match '<\\?php'" in setup,'local HTTPS health proves PHP execution and rejects exposed PHP source before setup success')
need('configure-apache.ps1' in prepare and 'sokna-local-https.conf.template' in prepare,'installer shell carries Apache integration owner and template')
need('configure-apache.ps1' in host and 'sokna-local-https.conf.template' in host,'Setup Host exact-shell verification requires Apache owner files')
need('phase8b-apache-integration-contract.py' in workflow,'Apache integration contract is wired into CI')
print('Phase 8B Apache integration contract PASS.')

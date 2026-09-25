#!/usr/bin/env python3
import json
from pathlib import Path
root=Path(__file__).resolve().parents[1]
manifest=json.loads((root/'runtime/windows/prerequisites.json').read_text(encoding='utf-8'))
setup=(root/'runtime/windows/setup-sokna.ps1').read_text(encoding='utf-8-sig')
support=(root/'runtime/windows/setup-support.psm1').read_text(encoding='utf-8-sig')
prepare=(root/'installer/windows/scripts/prepare-shell-payload.ps1').read_text(encoding='utf-8-sig')
host=(root/'installer/windows/setup-host/Program.cs').read_text(encoding='utf-8')

def need(c,m):
    if not c: raise AssertionError(m)
    print('PASS:',m)

need(manifest.get('format')=='sokna-windows-prerequisites-v1' and manifest.get('schema_version')==1,'prerequisite manifest is versioned')
need(manifest.get('automatic_download_allowed') is False and manifest.get('acquisition_policy')=='preinstalled-or-release-verified-offline-bundle','end-user prerequisite download stays forbidden while verified offline cache is supported')
need(manifest['php']['minimum_version_id']==80200 and manifest['php']['architecture']=='x64','PHP minimum and architecture are canonical manifest data')
need(set(manifest['php']['required_extensions'])=={'pdo_mysql','fileinfo','openssl','sodium','mbstring'},'required PHP extensions are canonical manifest data')
need(manifest['web_server']['lifecycle_owner']=='external' and manifest['database']['lifecycle_owner']=='external' and manifest['openssl']['lifecycle_owner']=='external','SOKNA validates but does not own web/database/OpenSSL lifecycle')
need("Join-Path $PSScriptRoot 'prerequisites.json'" in setup and 'minimum_version_id' in setup and 'required_extensions' in setup,'Windows preflight consumes prerequisite manifest rather than duplicating PHP requirements')
need('Resolve-SoknaWebServerExecutable $WebServerExe @($prerequisites.web_server.executable_names)' in setup and '$ExecutableNames' in support,'web server executable candidates come from prerequisite manifest')
need("'runtime\\windows\\prerequisites.json'" in prepare and '"prerequisites.json"' in host,'prerequisite manifest is present in both shell and live application flows')
need(all(x not in setup for x in ['Invoke-WebRequest','Start-BitsTransfer','curl.exe']) and all(x not in support for x in ['Invoke-WebRequest','Start-BitsTransfer','curl.exe']),'runtime setup cannot silently download prerequisites')
need(manifest['database']['validation_owner']=='tools/setup-machine.php' and manifest['database']['php_driver']=='pdo_mysql','database readiness remains with canonical setup/business preflight')
need(manifest['offline_bundle']['format']=='sokna-windows-prerequisite-bundle-v1' and manifest['offline_bundle']['automatic_install'] is False and manifest['offline_bundle']['shared_dependency_owner']=='external','offline prerequisite cache does not take ownership of shared dependencies')
print('Phase 8B prerequisite contract PASS: 11 checks.')

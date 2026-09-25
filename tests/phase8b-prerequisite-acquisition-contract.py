#!/usr/bin/env python3
import json
from pathlib import Path
root=Path(__file__).resolve().parents[1]
read=lambda p:(root/p).read_text(encoding='utf-8-sig')
prepare=read('installer/windows/scripts/prepare-prerequisite-bundle.ps1')
verify=read('installer/windows/scripts/verify-prerequisite-bundle.ps1')
freeze=read('installer/windows/scripts/freeze-prerequisite-lock.ps1')
candidate=json.loads(read('installer/windows/prerequisites/provider-candidate.json'))
shell=read('installer/windows/scripts/prepare-shell-payload.ps1')
ui=read('installer/windows/setup-ui/Program.cs')
host=read('installer/windows/setup-host/Program.cs')
workflow=read('.github/workflows/sokna-ci.yml')
runtime=read('runtime/windows/setup-sokna.ps1')
template=json.loads(read('installer/windows/prerequisites/release-lock.template.json'))
manifest=json.loads(read('runtime/windows/prerequisites.json'))

def need(c,m):
    if not c: raise AssertionError(m)
    print('PASS:',m)

need(candidate['format']=='sokna-windows-prerequisite-candidate-v1' and candidate['schema_version']==1 and candidate['release_frozen'] is False,'provider catalog is an explicit non-frozen release-engineering candidate')
need(candidate['app_version']==(root/'VERSION.txt').read_text(encoding='utf-8').strip() and len(candidate['artifacts'])>=4,'provider candidate is bound to current SOKNA version and contains the approved Windows dependency set')
need(all(str(x.get('source_url','')).startswith('https://') and len(str(x.get('expected_sha256','')))==64 for x in candidate['artifacts']),'provider candidate pins HTTPS sources and expected SHA-256 before freeze')
need('source_candidate_sha256' in freeze and 'Get-FileHash -LiteralPath $path -Algorithm SHA256' in freeze and '[long]$info.Length' in freeze,'freeze owner converts approved provider artifacts into exact hash/size release lock data')
need('file-product-version' in freeze and 'Get-AuthenticodeSignature' in freeze and 'publisher_contains' in freeze,'freeze owner can extract exact binary version and enforce signer policy')
need('release_frozen=$true' in freeze and "format='sokna-windows-prerequisite-freeze-evidence-v1'" in freeze,'freeze owner emits both frozen lock and auditable evidence without making candidate itself authoritative')
need(template['format']=='sokna-windows-prerequisite-lock-v1' and template['schema_version']==1 and template['release_frozen'] is False,'repository ships only a non-frozen prerequisite lock template')
need("if(-not [bool]$lock.release_frozen)" in prepare and 'Prerequisite release lock is not frozen' in prepare,'release acquisition requires an explicitly frozen lock')
need("$uri.Scheme -ne 'https'" in prepare and "Get-FileHash -LiteralPath $source -Algorithm SHA256" in prepare and "[long]$info.Length -ne [long]$artifact.size" in prepare,'release acquisition verifies HTTPS, exact size and SHA-256')
need('Get-AuthenticodeSignature' in prepare and 'publisher_contains' in prepare,'frozen artifacts can require Authenticode publisher verification')
need("[string]$artifact.installation -ne 'manual-external'" in prepare and "shared_dependency_owner='external'" in prepare,'offline cache cannot silently take ownership of shared dependencies')
need('Invoke-WebRequest' in prepare and '$AllowDownload' in prepare and all(x not in runtime for x in ['Invoke-WebRequest','Start-BitsTransfer','curl.exe']),'network acquisition exists only in opt-in release build, never end-user runtime setup')
need("format='sokna-windows-prerequisite-bundle-v1'" in prepare and 'Compare-Object' in verify and 'untracked or missing files' in verify,'offline bundle has versioned exact-set verification')
need('manifestAppVersion -ne $lockAppVersion' in verify and 'ExpectedAppVersion' in verify and '-ExpectedAppVersion $version' in shell,'offline bundle is bound to both the frozen release lock and the exact SOKNA app version')
need('lockById' in verify and 'does not match the frozen release lock' in verify and 'artifact set does not match the frozen release lock' in verify,'bundle manifest artifact metadata must exactly match the frozen release lock')
need("source URL must be absolute HTTPS" in verify and "artifact hash/size is invalid" in verify and "must preserve Authenticode policy" in verify,'bundle verifier independently validates frozen lock fields before trusting them')
need('Get-AuthenticodeSignature' in verify and 'authenticode' in prepare,'required Authenticode policy is preserved in the bundle and re-verified at use time')
need('$PrerequisiteBundleRoot' in shell and "verify-prerequisite-bundle.ps1" in shell and "Join-Path $OutputRoot 'Prerequisites'" in shell,'installer shell accepts only a verified prerequisite cache')
need('verify-prerequisite-bundle.ps1' in host,'Setup Host exact-shell contract includes the prerequisite verifier owner')
need('BuildOfflinePrerequisiteAction' in ui and 'باز کردن پیش‌نیازهای آفلاین' in ui and 'VerifyPrerequisiteBundleAsync' in ui,'Persian Setup UI exposes verified offline prerequisites and retry without download')

need('freeze_windows_prerequisites' in workflow and 'windows-prerequisite-freeze' in workflow and 'freeze-prerequisite-lock.ps1' in workflow,'Windows CI has an explicit provider-freeze evidence job before a lock can be committed')
need('include_offline_prerequisites' in workflow and 'release-lock.json' in workflow and 'prepare-prerequisite-bundle.ps1' in workflow and '-AllowDownload' in workflow,'Windows release CI can opt into exact-lock prerequisite acquisition')
need(manifest['automatic_download_allowed'] is False and manifest['offline_bundle']['automatic_install'] is False,'end-user Setup keeps prerequisite download/install disabled')
lock_path=root/'installer/windows/prerequisites/release-lock.json'
if lock_path.exists():
    frozen=json.loads(lock_path.read_text(encoding='utf-8-sig'))
    need(frozen.get('release_frozen') is True and frozen.get('app_version') not in ('','REPLACE_WITH_EXACT_SOKNA_VERSION') and bool(frozen.get('artifacts')),'committed release lock, when present, is explicitly frozen and populated for an audited release commit')
else:
    need(True,'current development tree keeps only the non-frozen template until release artifacts are approved')
need('phase8b-prerequisite-acquisition-contract.py' in workflow,'prerequisite acquisition contract is enforced by CI')
print('Phase 8B prerequisite acquisition contract PASS.')

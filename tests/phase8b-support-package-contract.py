#!/usr/bin/env python3
from pathlib import Path
root=Path(__file__).resolve().parents[1]
setup=(root/'runtime/windows/setup-sokna.ps1').read_text(encoding='utf-8-sig')
support=(root/'runtime/windows/setup-support.psm1').read_text(encoding='utf-8-sig')
runtime=(root/'tests/phase8b-windows-setup-runtime.ps1').read_text(encoding='utf-8-sig')
prepare=(root/'installer/windows/scripts/prepare-shell-payload.ps1').read_text(encoding='utf-8-sig')
collector=(root/'installer/windows/scripts/collect-support.ps1').read_text(encoding='utf-8-sig')
package=(root/'installer/windows/wix/Package.wxs').read_text(encoding='utf-8')
bundle=(root/'installer/windows/wix/Bundle.wxs').read_text(encoding='utf-8')
workflow=(root/'.github/workflows/sokna-ci.yml').read_text(encoding='utf-8')

def need(c,m):
    if not c: raise AssertionError(m)
    print('PASS:',m)

need("'summary.json'" in setup and "'events.jsonl'" in setup and "'components.json'" in setup,'support package has explicit core allowlist')
need('Compress-Archive -LiteralPath $supportFiles' in setup and 'Compress-Archive -Path $session' not in setup,'support package archives allowlisted files only, never session/app/data trees')
need("SOKNA_BURN_LOG_PATH" in setup and "SOKNA_MSI_LOG_PATH" in setup and 'Copy-SoknaSanitizedDiagnosticLog' in setup,'Burn/MSI logs are optional sanitized inputs, never recursive collection')
need('Get-SoknaServiceDiagnostic' in support and 'Get-SoknaSafeFileVersion' in support and 'Get-SoknaPrintHealthDiagnostic' in support,'component diagnostics are generated from explicit safe projections')
for field in ['agent_version','local_backlog_count','local_unknown_count','pending_report_count','reconciliation_report_count','printer_discovery_fresh','transport_state','coordinator_state','claim_reconciliation_required','claim_conflict_count']:
    need(field in support,f'print health allowlist contains {field}')
need('MaxBytes=2097152' in support,'optional diagnostic logs have a bounded size')
need('Protect-SoknaLog $text' in support,'optional external logs are redacted before packaging')
need("@('summary.json','events.jsonl','components.json','burn.log','msi.log')" in runtime,'Windows runtime acceptance asserts explicit support filename allowlist')
need("secret-canary-123|other-secret" in runtime,'Windows runtime acceptance checks redaction canaries')
need("live-updater-owned.txt" in runtime and 'Failed Repair changed updater-owned live application payload' in runtime,'Windows runtime acceptance protects updater-owned live payload during failed Repair')

need('collect-support.ps1' in prepare,'support collector is part of the installer-owned verified shell payload')
need('SoknaSupportShortcut' in package and 'collect-support.ps1' in package,'Start Menu exposes the Persian support collector instead of a parallel diagnostic owner')
need('<Log Prefix="SOKNA-Cafe-Setup" PathVariable="SoknaBundleLog"' in bundle and 'LogPathVariable="SoknaMsiLog"' in bundle,'Burn and MSI author explicit log paths for support correlation')
need("Get-ChildItem -LiteralPath $env:TEMP -Directory -Filter 'SOKNA-setup-*'" in collector and 'Copy-SoknaSanitizedDiagnosticLog' in collector,'collector reads only recent setup/log evidence through the sanitizer')
need('phase8b-support-package-contract.py' in workflow and 'Phase 8B support package contract failed' in workflow,'support package contract is enforced by Windows CI')
print('Phase 8B support package contract PASS.')

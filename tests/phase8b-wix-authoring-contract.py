#!/usr/bin/env python3
from pathlib import Path
from xml.etree import ElementTree as ET
ROOT=Path(__file__).resolve().parents[1]
base=ROOT/'installer/windows'
files={
 'package':base/'wix/Package.wxs', 'bundle':base/'wix/Bundle.wxs',
 'msiproj':base/'wix/SoknaShell.wixproj','bundleproj':base/'wix/SoknaBundle.wixproj',
 'prepare':base/'scripts/prepare-shell-payload.ps1','build':base/'scripts/build-installer.ps1',
 'readme':base/'README_FA.md','icon':base/'assets/Sokna.ico','collector':base/'scripts/collect-support.ps1'
}
for name,p in files.items():
    if not p.exists(): raise SystemExit(f'Missing Windows installer source: {name} -> {p}')
for k in ('package','bundle','msiproj','bundleproj'): ET.parse(files[k])
package=files['package'].read_text(encoding='utf-8-sig')
bundle=files['bundle'].read_text(encoding='utf-8-sig')
msiproj=files['msiproj'].read_text(encoding='utf-8-sig')
bundleproj=files['bundleproj'].read_text(encoding='utf-8-sig')
prepare=files['prepare'].read_text(encoding='utf-8-sig')
build=files['build'].read_text(encoding='utf-8-sig')
readme=files['readme'].read_text(encoding='utf-8-sig')
collector=files['collector'].read_text(encoding='utf-8-sig')
workflow=(ROOT/'.github/workflows/sokna-ci.yml').read_text(encoding='utf-8')
package_runtime=(ROOT/'tests/phase8b-windows-installer-package-runtime.ps1').read_text(encoding='utf-8-sig')
checks={
 'WiX 7 toolchain pinned': 'WixToolset.Sdk/7.0.0' in msiproj and 'WixToolset.Sdk/7.0.0' in bundleproj and 'RequiredVersion="7.0.0"' in package and 'RequiredVersion="7.0.0"' in bundle,
 'MSI is x64 per-machine shell': '<InstallerPlatform>x64</InstallerPlatform>' in msiproj and 'Scope="perMachine"' in package,
 'MSI harvests only named shell bind path': '!(bindpath.Shell)\\**' in package and 'live application tree is deliberately NOT harvested' in package,
 'MSI exposes branded ARP icon': 'ARPPRODUCTICON' in package and 'SoknaProductIcon' in package,
 'Desktop and Start shortcuts open local URL': 'DesktopFolder' in package and 'ProgramMenuFolder' in package and package.count('https://sokna.local/') >= 3,
 'shortcut launch does not elevate browser': 'Target="[WindowsFolder]explorer.exe"' in package,
 'bundle chains SOKNA MSI and no external Pagent': 'MsiPackage Id="SoknaShellMsi"' in bundle and 'Pagent' not in bundle and 'PrintAgent' not in bundle,
 'seed creation excludes mutable/local-only roots': all(x in prepare for x in ["'.git'","'storage'","'uploads'","'installer'","'config.php'","'install.lock'"]),
 'seed is explicitly cache not live MSI payload': 'SoknaAppPayload.zip' in prepare and "live_app_owner='sokna-updater'" in prepare,
 'build maps app version to MSI-safe version': '$build=$patch*1000+$dev' in build and '65535' in build,
 'WiX EULA acceptance is explicit opt-in': '[switch]$AcceptWix7Eula' in build and "if($AcceptWix7Eula)" in build and '-p:AcceptEula=wix7' in build,
 'README marks authoring non-production': 'Production-ready نیست' in readme,
 'bundle uses stable installer log prefix and package log variable': '<Log Prefix="SOKNA-Cafe-Setup"' in bundle and 'LogPathVariable="SoknaMsiLog"' in bundle,
 'support collector is bundled and explicitly allowlisted': 'collect-support.ps1' in prepare and 'SOKNA-setup-*' in collector and 'SOKNA-Cafe-Setup*.log' in collector and 'Compress-Archive -LiteralPath $files' in collector,
 'Start menu exposes non-elevated Persian support action': 'SoknaSupportShortcut' in package and 'گزارش پشتیبانی سکنا' in package and 'powershell.exe' in package and 'requireAdministrator' not in package,
 'Windows CI runs real MSI/Burn package acceptance before upload': 'phase8b-windows-installer-package-runtime.ps1' in workflow and '/fa' in package_runtime and "'-uninstall'" in package_runtime,
 'WiX package identity is stable for major upgrades': 'Id="SOKNA.Cafe.Local.InstallerShell"' in package and 'RequiredVersion="7.0.0"' in package,
 'Windows CI exercises a synthetic predecessor major upgrade': 'upgrade-baseline.json' in workflow and 'SOKNA_PREVIOUS_MSI' in workflow and 'PreviousMsiPath' in package_runtime and 'MSI major upgrade modified updater-owned live payload' in package_runtime,
 'package acceptance protects updater live payload and business data': 'updater-owned-live-payload-must-survive' in package_runtime and 'business-data-must-survive' in package_runtime and 'MSI Repair modified updater-owned live payload' in package_runtime,
}
failed=[k for k,v in checks.items() if not v]
for k,v in checks.items(): print(('PASS' if v else 'FAIL')+': '+k)
if failed: raise SystemExit('Phase 8B WiX authoring contract failed: '+', '.join(failed))
print(f'Phase 8B WiX authoring contract PASS: {len(checks)} checks.')

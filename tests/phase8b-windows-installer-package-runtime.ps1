param(
    [Parameter(Mandatory=$true)][string]$MsiPath,
    [Parameter(Mandatory=$true)][string]$BundlePath,
    [string]$PreviousMsiPath = ''
)
$ErrorActionPreference='Stop'
$repo = Split-Path -Parent $PSScriptRoot
Import-Module (Join-Path $repo 'runtime/windows/setup-support.psm1') -DisableNameChecking -Force
$MsiPath=[IO.Path]::GetFullPath($MsiPath)
$BundlePath=[IO.Path]::GetFullPath($BundlePath)
if($PreviousMsiPath){$PreviousMsiPath=[IO.Path]::GetFullPath($PreviousMsiPath)}
if(-not(Test-Path -LiteralPath $MsiPath -PathType Leaf)){throw 'MSI artifact missing.'}
if(-not(Test-Path -LiteralPath $BundlePath -PathType Leaf)){throw 'Burn artifact missing.'}
if($PreviousMsiPath -and -not(Test-Path -LiteralPath $PreviousMsiPath -PathType Leaf)){throw 'Previous MSI artifact missing.'}
$root=Join-Path $env:RUNNER_TEMP ('sokna-package-acceptance-'+[guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $root -Force|Out-Null
$installRoot=Join-Path $env:ProgramFiles 'SOKNA\Cafe Local'
$shellRoot=Join-Path $installRoot 'Installer'
$liveRoot=Join-Path $root 'updater-live-app'
$dataRoot=Join-Path $env:ProgramData 'SOKNA'
$liveMarker=Join-Path $liveRoot 'phase8b-package-live-owner.marker'
$dataMarker=Join-Path $dataRoot 'phase8b-package-business-data.marker'
$msiProductCode=''
function Assert([bool]$ok,[string]$message){if(-not $ok){throw $message}}
function Run([string]$file,[string[]]$arguments,[int[]]$success=@(0,3010)){
    $r=Invoke-SoknaProcess -File $file -Arguments $arguments -SuccessCodes $success -TimeoutSeconds 300
    return $r.ExitCode
}
function Find-MsiRegistration {
    foreach($base in @('HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\Uninstall','HKLM:\SOFTWARE\WOW6432Node\Microsoft\Windows\CurrentVersion\Uninstall')){
        if(-not(Test-Path $base)){continue}
        foreach($item in Get-ChildItem $base -ErrorAction SilentlyContinue){
            $p=Get-ItemProperty $item.PSPath -ErrorAction SilentlyContinue
            if($p.DisplayName -eq 'SOKNA Cafe Local'){return [pscustomobject]@{Code=$item.PSChildName;Props=$p}}
        }
    }
    return $null
}
function Find-BundleRegistration {
    foreach($base in @('HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\Uninstall','HKLM:\SOFTWARE\WOW6432Node\Microsoft\Windows\CurrentVersion\Uninstall')){
        if(-not(Test-Path $base)){continue}
        foreach($item in Get-ChildItem $base -ErrorAction SilentlyContinue){
            $p=Get-ItemProperty $item.PSPath -ErrorAction SilentlyContinue
            if($p.DisplayName -eq 'SOKNA Cafe Setup'){return [pscustomobject]@{Code=$item.PSChildName;Props=$p}}
        }
    }
    return $null
}
function Find-Shortcut([string]$Name){
    $candidates=@(
        (Join-Path ([Environment]::GetFolderPath('Desktop')) ($Name+'.lnk')),
        (Join-Path $env:PUBLIC ('Desktop\'+$Name+'.lnk')),
        (Join-Path ([Environment]::GetFolderPath('Programs')) ('SOKNA\'+$Name+'.lnk')),
        (Join-Path $env:ProgramData ('Microsoft\Windows\Start Menu\Programs\SOKNA\'+$Name+'.lnk'))
    )|Select-Object -Unique
    foreach($path in $candidates){if(Test-Path -LiteralPath $path -PathType Leaf){return $path}}
    return ''
}
function Get-ShortcutTarget([string]$Path){
    $shell=New-Object -ComObject WScript.Shell
    try{return [string]$shell.CreateShortcut($Path).TargetPath}
    finally{[Runtime.InteropServices.Marshal]::FinalReleaseComObject($shell)|Out-Null}
}
try{
    New-Item -ItemType Directory -Path $liveRoot -Force|Out-Null
    New-Item -ItemType Directory -Path $dataRoot -Force|Out-Null
    [IO.File]::WriteAllText($liveMarker,'updater-owned-live-payload-must-survive')
    [IO.File]::WriteAllText($dataMarker,'business-data-must-survive')
    $liveHash=(Get-FileHash $liveMarker -Algorithm SHA256).Hash
    $dataHash=(Get-FileHash $dataMarker -Algorithm SHA256).Hash

    if($PreviousMsiPath){
        $previousLog=Join-Path $root 'msi-previous-install.log'
        Run "$env:SystemRoot\System32\msiexec.exe" @('/i',$PreviousMsiPath,'/qn','/norestart','/l*v',$previousLog)|Out-Null
        $previousReg=Find-MsiRegistration
        Assert ($null -ne $previousReg) 'Synthetic predecessor MSI did not register before major-upgrade acceptance.'
        $beforeUpgradeCount=@(Find-MsiRegistration).Count
        Assert ($beforeUpgradeCount -eq 1) 'Synthetic predecessor created an ambiguous MSI registration set.'
    }
    $msiLog=Join-Path $root 'msi-install.log'
    Run "$env:SystemRoot\System32\msiexec.exe" @('/i',$MsiPath,'/qn','/norestart','/l*v',$msiLog)|Out-Null
    Assert (Test-Path -LiteralPath $shellRoot -PathType Container) 'MSI did not install the immutable shell.'
    $reg=Find-MsiRegistration
    Assert ($null -ne $reg) 'MSI is not registered in Installed apps/ARP.'
    $msiProductCode=[string]$reg.Code
    Assert ([string]$reg.Props.DisplayVersion -ne '') 'ARP version is empty.'
    Assert ([string]$reg.Props.Publisher -eq 'SOKNA') 'ARP publisher is incorrect.'
    if($PreviousMsiPath){
        Assert ((Get-FileHash $liveMarker -Algorithm SHA256).Hash -eq $liveHash) 'MSI major upgrade modified updater-owned live payload.'
        Assert ((Get-FileHash $dataMarker -Algorithm SHA256).Hash -eq $dataHash) 'MSI major upgrade modified business data.'
        $appVersion=(Get-ItemProperty 'HKLM:\Software\SOKNA\Cafe Local' -Name AppVersion -ErrorAction Stop).AppVersion
        $expected=(Get-Content (Join-Path $repo 'VERSION.txt') -Raw).Trim()
        Assert ([string]$appVersion -eq $expected) 'MSI major upgrade did not replace installer-shell metadata with current SOKNA version.'
    }
    Assert ((Find-Shortcut 'SOKNA Cafe') -ne '') 'Application shortcut missing after install.'
    $setupUi=Join-Path $shellRoot 'SoknaSetupUi.exe'
    Assert (Test-Path -LiteralPath $setupUi -PathType Leaf) 'Canonical Setup UI missing from installer shell.'
    $setupUiShortcut=Find-Shortcut 'راه‌اندازی و تعمیر سکنا'
    Assert ($setupUiShortcut -ne '') 'Persian Setup UI shortcut missing after install.'
    Assert ([IO.Path]::GetFullPath((Get-ShortcutTarget $setupUiShortcut)) -eq [IO.Path]::GetFullPath($setupUi)) 'Setup UI shortcut target is not the canonical installer-owned executable.'
    $supportShortcut=Find-Shortcut 'گزارش پشتیبانی سکنا'
    Assert ($supportShortcut -ne '') 'Persian support shortcut missing after install.'

    $collector=Join-Path $shellRoot 'collect-support.ps1'
    Assert (Test-Path -LiteralPath $collector -PathType Leaf) 'Support collector missing from installer shell.'
    $collectorOut=Join-Path $root 'collector.zip'
    Run (Get-Command powershell.exe).Source @('-NoProfile','-ExecutionPolicy','Bypass','-File',$collector,'-OutputPath',$collectorOut,'-AppRoot',$liveRoot,'-DataRoot',$dataRoot)|Out-Null
    Assert (Test-Path -LiteralPath $collectorOut -PathType Leaf) 'One-step support collector did not create a ZIP.'

    # Real Windows Installer Repair: remove installer-owned resources and shortcuts.
    Remove-Item -LiteralPath $collector -Force
    Remove-Item -LiteralPath $setupUi -Force
    Remove-Item -LiteralPath $supportShortcut -Force
    Remove-Item -LiteralPath $setupUiShortcut -Force
    $repairLog=Join-Path $root 'msi-repair.log'
    Run "$env:SystemRoot\System32\msiexec.exe" @('/fa',$msiProductCode,'/qn','/norestart','/l*v',$repairLog)|Out-Null
    Assert (Test-Path -LiteralPath $collector -PathType Leaf) 'MSI Repair did not restore installer-owned collector.'
    Assert (Test-Path -LiteralPath $setupUi -PathType Leaf) 'MSI Repair did not restore canonical Setup UI.'
    Assert ((Find-Shortcut 'راه‌اندازی و تعمیر سکنا') -ne '') 'MSI Repair did not restore Setup UI shortcut.'
    Assert ((Find-Shortcut 'گزارش پشتیبانی سکنا') -ne '') 'MSI Repair did not restore support shortcut.'
    Assert ((Get-FileHash $liveMarker -Algorithm SHA256).Hash -eq $liveHash) 'MSI Repair modified updater-owned live payload.'
    Assert ((Get-FileHash $dataMarker -Algorithm SHA256).Hash -eq $dataHash) 'MSI Repair modified business data.'

    $uninstallLog=Join-Path $root 'msi-uninstall.log'
    Run "$env:SystemRoot\System32\msiexec.exe" @('/x',$msiProductCode,'/qn','/norestart','/l*v',$uninstallLog)|Out-Null
    Assert ($null -eq (Find-MsiRegistration)) 'MSI remained registered after uninstall.'
    Assert ((Get-FileHash $liveMarker -Algorithm SHA256).Hash -eq $liveHash) 'MSI Uninstall removed/changed updater-owned live payload.'
    Assert ((Get-FileHash $dataMarker -Algorithm SHA256).Hash -eq $dataHash) 'MSI Uninstall removed/changed business data.'

    # Burn must also install and uninstall the same MSI chain successfully.
    $burnInstallLog=Join-Path $root 'burn-install.log'
    Run $BundlePath @('-quiet','-norestart','-log',$burnInstallLog)|Out-Null
    Assert ($null -ne (Find-MsiRegistration)) 'Burn did not install the SOKNA MSI chain.'
    $bundleReg=Find-BundleRegistration
    Assert ($null -ne $bundleReg) 'Burn bundle is not registered in Installed apps/ARP.'
    $burnUninstallLog=Join-Path $root 'burn-uninstall.log'
    Run $BundlePath @('-uninstall','-quiet','-norestart','-log',$burnUninstallLog)|Out-Null
    Assert ($null -eq (Find-MsiRegistration)) 'Burn uninstall left the SOKNA MSI installed.'
    Assert ((Get-FileHash $liveMarker -Algorithm SHA256).Hash -eq $liveHash) 'Burn uninstall changed updater-owned live payload.'
    Assert ((Get-FileHash $dataMarker -Algorithm SHA256).Hash -eq $dataHash) 'Burn uninstall changed business data.'
    Write-Host 'Phase 8B package runtime PASS: MSI/Burn install, optional major-upgrade, ARP, shortcuts, Repair, non-downgrade and data-preserving uninstall.'
} finally {
    try{
        $reg=Find-MsiRegistration
        if($reg){Run "$env:SystemRoot\System32\msiexec.exe" @('/x',[string]$reg.Code,'/qn','/norestart')|Out-Null}
    }catch{}
    try{
        $bundle=Find-BundleRegistration
        if($bundle){Run $BundlePath @('-uninstall','-quiet','-norestart')|Out-Null}
    }catch{}
    Remove-Item -LiteralPath $liveMarker -Force -ErrorAction SilentlyContinue
    Remove-Item -LiteralPath $dataMarker -Force -ErrorAction SilentlyContinue
    Remove-Item -LiteralPath $root -Recurse -Force -ErrorAction SilentlyContinue
}

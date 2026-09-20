param([Parameter(Mandatory=$true)][string]$Installer, [Parameter(Mandatory=$true)][string]$PhpExe, [Parameter(Mandatory=$true)][string]$OpenSslExe)
$ErrorActionPreference = 'Stop'
$repo = Split-Path -Parent $PSScriptRoot
Import-Module (Join-Path $repo 'runtime/windows/setup-support.psm1') -DisableNameChecking -Force
$root = New-SoknaPrivateDirectory (Join-Path $env:TEMP ('sokna-installer-' + [guid]::NewGuid().ToString('N')))
$platform = Join-Path $env:ProgramFiles ('SOKNA Platform Test ' + [guid]::NewGuid().ToString('N'))
$app = Join-Path $root 'app'
$data = Join-Path $root 'private-data'
$registry = 'HKLM:\SOFTWARE\SOKNA\PlatformPreview'
$arp = 'HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\Uninstall\{D577EAA8-1B19-45C6-9FB0-91008FD349E3}_is1'
$desktop = Join-Path ([Environment]::GetFolderPath('CommonDesktopDirectory')) 'SOKNA.url'
$start = Join-Path ([Environment]::GetFolderPath('CommonPrograms')) 'SOKNA'
$caThumb = ''
function Assert([bool]$OK,[string]$Message) { if (-not $OK) { throw $Message } }
function Install([string]$Exe,[string[]]$Extra=@()) {
    Invoke-SoknaProcess $Exe (@('/VERYSILENT','/SUPPRESSMSGBOXES','/NORESTART',"/DIR=$platform") + $Extra) -TimeoutSeconds 300 | Out-Null
}
try {
    Assert (-not (Get-Service SoknaRuntime -ErrorAction SilentlyContinue)) 'Disposable runner must not have a Runtime service'
    Assert (-not (Test-Path $registry)) 'Disposable runner must not have platform registration'
    Assert (-not (Test-Path $desktop)) 'Do not overwrite an existing desktop shortcut'
    Assert (-not (Test-Path $start)) 'Do not overwrite an existing Start group'
    New-Item -ItemType Directory (Join-Path $app 'runtime') -Force | Out-Null
    [IO.File]::WriteAllText((Join-Path $app 'runtime/sokna-runtime.php'), '<?php if(in_array("--self-check",$argv,true))exit(0);while(true){sleep(1);}')
    [IO.File]::WriteAllText((Join-Path $app 'config.php'), '<?php /* preserve fixture secret */')
    [IO.File]::WriteAllText((Join-Path $app 'install.lock'), 'preserve-lock')
    [IO.File]::WriteAllText((Join-Path $app 'VERSION.txt'), 'application-A')
    $configHash = (Get-FileHash (Join-Path $app 'config.php')).Hash
    $extra = @("/AppRoot=$app","/DataRoot=$data","/PhpExe=$PhpExe","/OpenSslExe=$OpenSslExe",'/Hostname=sokna.local')
    # Missing prerequisite fails before registration or platform extraction.
    $failed = $false
    try { Install $Installer @("/AppRoot=$app","/DataRoot=$data","/PhpExe=$root\missing.exe","/OpenSslExe=$OpenSslExe") } catch { $failed=$true }
    Assert $failed 'Missing prerequisite reported successful install'
    Assert (-not (Test-Path $arp)) 'Failed preflight registered a product'
    Assert (-not (Test-Path $platform)) 'Failed preflight extracted platform files'
    Install $Installer $extra
    $caFile = Join-Path $data 'secrets/tls/local-ca.crt.pem'
    $caThumb = (New-Object Security.Cryptography.X509Certificates.X509Certificate2($caFile)).Thumbprint
    Assert ((Get-Service SoknaRuntime).Status -eq 'Running') 'Installer did not start Runtime'
    Assert (Test-Path $arp) 'Installed apps entry missing'
    $entry = Get-ItemProperty $arp
    Assert ($entry.DisplayName -eq 'SOKNA Platform Preview') 'Wrong ARP display name'
    Assert ($entry.ModifyPath -match 'maintenance\\Setup.exe') 'ARP Modify is not the cached repair installer'
    Assert (Test-Path $desktop) 'Desktop shortcut missing'
    Assert (Test-Path (Join-Path $start 'SOKNA.url')) 'Start shortcut missing'
    Assert ((Get-Content $desktop -Raw) -match 'https://sokna.local/') 'Shortcut points at the wrong URL'
    $tlsHash = (Get-FileHash (Join-Path $data 'secrets/tls/local-ca.key.pem')).Hash
    # Simulate a later application update, then repair with the original installer.
    [IO.File]::WriteAllText((Join-Path $app 'VERSION.txt'), 'application-B')
    [IO.File]::WriteAllText((Join-Path $data 'business-sentinel.txt'), 'preserve-data')
    Remove-Item (Join-Path $platform 'setup-support.psm1')
    Remove-Item $desktop
    $cache = Join-Path $platform 'maintenance/Setup.exe'
    Install $cache @('/REPAIR')
    Assert (Test-Path (Join-Path $platform 'setup-support.psm1')) 'Repair did not restore a missing platform file'
    Assert (Test-Path $desktop) 'Repair did not restore desktop shortcut'
    Assert ((Get-Content (Join-Path $app 'VERSION.txt') -Raw) -eq 'application-B') 'Repair downgraded application version'
    Assert ((Get-FileHash (Join-Path $app 'config.php')).Hash -eq $configHash) 'Repair changed application config'
    Assert ((Get-FileHash (Join-Path $data 'secrets/tls/local-ca.key.pem')).Hash -eq $tlsHash) 'Repair changed TLS identity'
    # Wrong service owner must block removal and leave integration intact.
    $sc = "$env:SystemRoot\System32\sc.exe"
    $image = (Get-ItemProperty 'HKLM:\SYSTEM\CurrentControlSet\Services\SoknaRuntime').ImagePath
    Invoke-SoknaProcess $sc @('config','SoknaRuntime','binPath=','C:\foreign-service.exe') | Out-Null
    $uninstall = Join-Path $platform 'unins000.exe'
    $blocked=$false
    try { Invoke-SoknaProcess $uninstall @('/VERYSILENT','/SUPPRESSMSGBOXES','/NORESTART') | Out-Null } catch { $blocked=$true }
    Assert $blocked 'Uninstall ignored a service ownership mismatch'
    Assert (Test-Path $arp) 'Blocked uninstall removed product registration'
    Invoke-SoknaProcess $sc @('config','SoknaRuntime','binPath=',$image) | Out-Null
    Invoke-SoknaProcess $uninstall @('/VERYSILENT','/SUPPRESSMSGBOXES','/NORESTART') -TimeoutSeconds 180 | Out-Null
    Assert (-not (Get-Service SoknaRuntime -ErrorAction SilentlyContinue)) 'Uninstall left the owned service behind'
    Assert (-not (Test-Path $arp)) 'Uninstall left ARP registration behind'
    Assert (-not (Test-Path $desktop)) 'Uninstall left desktop shortcut behind'
    Assert (-not (Test-Path (Join-Path $platform 'setup-sokna.ps1'))) 'Uninstall left platform executable scripts behind'
    Assert ((Get-FileHash (Join-Path $app 'config.php')).Hash -eq $configHash) 'Uninstall changed app config'
    Assert ((Get-Content (Join-Path $data 'business-sentinel.txt') -Raw) -eq 'preserve-data') 'Uninstall deleted business data'
    Assert ((Get-FileHash (Join-Path $data 'secrets/tls/local-ca.key.pem')).Hash -eq $tlsHash) 'Uninstall deleted private TLS identity'
    Write-Host 'Inno lifecycle PASS: preflight, ARP, shortcuts, cached Repair, application preservation, ownership refusal, uninstall/data preservation. Fixture test only; no HTTP/DB/UAT claim.'
} finally {
    # This test only runs on a disposable CI machine. Preserve diagnostics until artifact upload.
    if (Get-Service SoknaRuntime -ErrorAction SilentlyContinue) {
        Stop-Service SoknaRuntime -ErrorAction SilentlyContinue
        & "$env:SystemRoot\System32\sc.exe" delete SoknaRuntime | Out-Null
    }
    if (-not $caThumb -and (Test-Path (Join-Path $data 'secrets/tls/local-ca.crt.pem'))) {
        $caThumb = (New-Object Security.Cryptography.X509Certificates.X509Certificate2((Join-Path $data 'secrets/tls/local-ca.crt.pem'))).Thumbprint
    }
    if ($caThumb) { Remove-Item "Cert:\LocalMachine\Root\$caThumb" -ErrorAction SilentlyContinue }
    Remove-Item $root -Recurse -Force -ErrorAction SilentlyContinue
}

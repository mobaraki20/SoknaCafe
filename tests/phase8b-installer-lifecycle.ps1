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
$installAttempt = 0
function Assert([bool]$OK,[string]$Message) { if (-not $OK) { throw $Message } }
function Install([string]$Exe,[string[]]$Extra=@()) {
    $script:installAttempt++
    $log = Join-Path $root ('install-' + $script:installAttempt + '.log')
    Write-Host ('Installer lifecycle attempt ' + $script:installAttempt)
    Invoke-SoknaProcess $Exe (@('/VERYSILENT','/SUPPRESSMSGBOXES','/NORESTART',"/DIR=$platform","/LOG=$log") + $Extra) -TimeoutSeconds 300 | Out-Null
}
function Assert-InstallerBundle([string]$LogFile) {
    $nativeLog = Get-Content -LiteralPath $LogFile -Raw
    $paths = [regex]::Matches($nativeLog, '"support_bundle"\s*:\s*("[^"\r\n]+")')
    Assert ($paths.Count -gt 0) 'Native installer did not report a support bundle path'
    $zip = ConvertFrom-Json $paths[$paths.Count - 1].Groups[1].Value
    $expanded = Join-Path $root ([guid]::NewGuid().ToString('N'))
    Expand-Archive -LiteralPath $zip -DestinationPath $expanded
    $expected = @('summary.json','events.jsonl','components.json','installer-snapshot.log' | Sort-Object)
    # Inspect archive entry names directly: Windows may expand a TEMP 8.3 path
    # to its long form in FileInfo.FullName, so substring offsets are unreliable.
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    $archive = [IO.Compression.ZipFile]::OpenRead($zip)
    try { $actual = @($archive.Entries | ForEach-Object { $_.FullName } | Sort-Object) }
    finally { $archive.Dispose() }
    Assert (($actual -join '|') -eq ($expected -join '|')) ('Native bundle allowlist mismatch; entries: ' + ($actual -join ', '))
    $report = Get-Content (Join-Path $expanded 'summary.json') -Raw | ConvertFrom-Json
    Assert ($report.installer_log_status -eq 'included') 'Native log snapshot unavailable'
    Assert ((Get-Item (Join-Path $expanded 'installer-snapshot.log')).Length -gt 0) 'Native log snapshot empty'
}
try {
    # This is the platform preview lifecycle fixture. HTTPS is proven independently by
    # phase8b-apache-integration-runtime.ps1 and phase8b-windows-rc-full-stack.ps1.
    Assert (-not (Get-Service SoknaRuntime -ErrorAction SilentlyContinue)) 'Disposable runner must not have a Runtime service'
    Assert (-not (Test-Path $registry)) 'Disposable runner must not have platform registration'
    Assert (-not (Test-Path $desktop)) 'Do not overwrite an existing desktop shortcut'
    Assert (-not (Test-Path $start)) 'Do not overwrite an existing Start group'
    New-Item -ItemType Directory (Join-Path $app 'runtime') -Force | Out-Null
    [IO.File]::WriteAllText((Join-Path $app 'runtime/sokna-runtime.php'), '<?php if(in_array("--self-check",$argv,true))exit(0);while(true){sleep(1);}')
    [IO.File]::WriteAllText((Join-Path $app 'config.php'), '<?php /* preserve fixture secret */')
    [IO.File]::WriteAllText((Join-Path $app 'install.lock'), 'preserve-lock')
    [IO.File]::WriteAllText((Join-Path $app 'VERSION.txt'), 'application-A')
    # This packaging fixture has no business database.  Seed an existing internal
    # Print Worker pairing so Repair proves preservation instead of taking the
    # DB-owned missing-pairing regeneration path.
    $workerData = Join-Path $data 'print-worker'
    New-SoknaPrivateDirectory $workerData | Out-Null
    [IO.File]::WriteAllText((Join-Path $workerData 'config.json'), '{"server_base_url":"http://127.0.0.1:9","agent_name":"Inno lifecycle fixture"}')
    [IO.File]::WriteAllBytes((Join-Path $workerData 'secret.dat'), [byte[]](1,2,3,4,5,6,7,8))
    $configHash = (Get-FileHash (Join-Path $app 'config.php')).Hash
    $extra = @("/AppRoot=$app","/DataRoot=$data","/PhpExe=$PhpExe","/OpenSslExe=$OpenSslExe",'/Hostname=sokna.local')
    # Missing prerequisite fails before registration or platform extraction.
    $failed = $false
    try { Install $Installer @("/AppRoot=$app","/DataRoot=$data","/PhpExe=$root\missing.exe","/OpenSslExe=$OpenSslExe") } catch { $failed=$true }
    Assert $failed 'Missing prerequisite reported successful install'
    Assert-InstallerBundle (Join-Path $root 'install-1.log')
    Assert (-not (Test-Path $arp)) 'Failed preflight registered a product'
    Assert (-not (Test-Path $platform)) 'Failed preflight extracted platform files'
    Install $Installer $extra
    Assert-InstallerBundle (Join-Path $root 'install-2.log')
    $caFile = Join-Path $data 'secrets/tls/local-ca.crt.pem'
    $caThumb = (New-Object Security.Cryptography.X509Certificates.X509Certificate2($caFile)).Thumbprint
    Assert ((Get-Service SoknaRuntime).Status -eq 'Running') 'Installer did not start Runtime'
    Assert (Test-Path $arp) 'Installed apps entry missing'
    $entry = Get-ItemProperty $arp
    Assert ($entry.DisplayName -eq 'SOKNA Platform Preview') ('Wrong ARP display name: ' + $entry.DisplayName)
    Assert ($entry.DisplayVersion -eq '0.1.0') 'Wrong ARP version'
    Assert ($entry.Publisher -eq 'SOKNA') 'Wrong ARP publisher'
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
    Assert-InstallerBundle (Join-Path $root 'install-3.log')
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
    # Fixture logs contain no real credentials. Emit evidence even when a GUI child has no stdout.
    Get-ChildItem $root -Filter '*.log' -File | ForEach-Object { Write-Host $_.Name; Get-Content $_.FullName -Tail 100 | Write-Host }
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

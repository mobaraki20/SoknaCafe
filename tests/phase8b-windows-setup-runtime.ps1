param([Parameter(Mandatory=$true)][string]$PhpExe, [Parameter(Mandatory=$true)][string]$ServiceHostExe, [Parameter(Mandatory=$true)][string]$OpenSslExe)
$ErrorActionPreference = 'Stop'
$repo = Split-Path -Parent $PSScriptRoot
Import-Module (Join-Path $repo 'runtime/windows/setup-support.psm1') -DisableNameChecking -Force
$root = Join-Path $env:TEMP ('SOKNA setup آزمون ' + [guid]::NewGuid().ToString('N'))
New-SoknaPrivateDirectory $root | Out-Null
$setup = Join-Path $repo 'runtime/windows/setup-sokna.ps1'
$ps = (Get-Command powershell.exe).Source
$sessions = @()
$ownsService = $false
$ownsPrintWorker = $false
$caThumb = ''
function Assert([bool]$Condition, [string]$Message) { if (-not $Condition) { throw $Message } }
function Run-Setup([string[]]$Extra, [int]$Expected = 0) {
    $r = Invoke-SoknaProcess -File $ps -Arguments (@('-NoProfile','-ExecutionPolicy','Bypass','-File',$setup,'-PhpExe',$PhpExe,'-ServiceHostExe',$ServiceHostExe) + $Extra) -SuccessCodes @($Expected) -TimeoutSeconds 180
    $s = $r.Output | ConvertFrom-Json
    $script:sessions += $s.diagnostics_directory
    Assert ($s.exit_code -eq $Expected) 'Wrong structured exit status'
    Assert (Test-Path (Join-Path $s.diagnostics_directory 'support.zip')) 'Support ZIP missing'
    return $s
}
try {
    Assert (-not (Get-Service SoknaRuntime -ErrorAction SilentlyContinue)) 'Test requires a disposable runner without SoknaRuntime'
    Assert (-not (Get-Service SoknaPrintWorker -ErrorAction SilentlyContinue)) 'Test requires a disposable runner without SoknaPrintWorker'
    # Real Windows process argument round trip: whitespace, Persian, quotes, trailing slash.
    $probe = Join-Path $root 'args.php'
    [IO.File]::WriteAllText($probe, '<?php echo json_encode(array_slice($argv,1));')
    $values = @('two words','فارسی','embedded"quote','C:\space dir\')
    $r = Invoke-SoknaProcess $PhpExe (@($probe) + $values)
    $actual = ConvertFrom-Json -InputObject $r.Output
    Assert ($actual.Count -eq $values.Count) 'Native argument count changed'
    for ($i=0; $i -lt $values.Count; $i++) {
        Assert ([string]$actual[$i] -ceq $values[$i]) ('Native argument mismatch at index ' + $i)
    }

    # Exercise occupied-port discovery with a real listener; $PID is read-only.
    $listener = New-Object Net.Sockets.TcpListener([Net.IPAddress]::Loopback, 0)
    try {
        $listener.Start()
        $probePort = ([Net.IPEndPoint]$listener.LocalEndpoint).Port
        $owners = @(Get-SoknaTcpListenerOwners $probePort)
        Assert (@($owners | Where-Object { $_.pid -eq $PID }).Count -eq 1) 'Listening process owner was not discovered'
    } finally { $listener.Stop() }

    Add-SoknaSecret 'secret-canary-123'
    Assert ((Protect-SoknaLog 'failed secret-canary-123 token=other-secret') -notmatch 'secret-canary|other-secret') 'Secret redaction failed'
    $acl = Get-Acl $root
    Assert $acl.AreAccessRulesProtected 'Private directory inherits access rules'
    $allowed = @('S-1-5-18','S-1-5-32-544',[Security.Principal.WindowsIdentity]::GetCurrent().User.Value)
    foreach ($rule in $acl.Access) {
        Assert ($allowed -contains $rule.IdentityReference.Translate([Security.Principal.SecurityIdentifier]).Value) 'Private directory grants an unexpected identity'
    }
    $validateData = Join-Path $root 'validate-must-not-create'
    $s = Run-Setup @('-Mode','Validate','-AppRoot',$repo,'-DataRoot',$validateData,'-SkipHttps','-SkipService')
    Assert (-not (Test-Path $validateData)) 'Validate changed target data root'
    $s = Run-Setup @('-Mode','New','-AppRoot',$repo,'-DataRoot',$validateData,'-OpenSslExe',(Join-Path $root 'missing.exe')) 2
    Assert ($s.stage -eq 'preflight') 'Missing dependency not caught in preflight'
    Assert (-not (Test-Path $validateData)) 'Failed preflight changed target data root'
    $malformed = Join-Path $root 'malformed.json'
    [IO.File]::WriteAllText($malformed, '{"admin_password":"do-not-log-malformed-canary",broken')
    $s = Run-Setup @('-Mode','New','-AppRoot',$repo,'-DataRoot',$validateData,'-SetupConfigFile',$malformed,'-SkipHttps','-SkipService') 2
    Assert (($s | ConvertTo-Json -Depth 8) -notmatch 'do-not-log-malformed-canary') 'Malformed JSON error leaked a secret'
    Assert ((Get-Content (Join-Path $s.diagnostics_directory 'events.jsonl') -Raw) -notmatch 'do-not-log-malformed-canary') 'Malformed JSON log leaked a secret'
    Assert (-not (Test-Path $validateData)) 'Malformed input changed target data root'


    $app = Join-Path $root 'app'
    $data = Join-Path $root 'data'
    New-Item -ItemType Directory -Force (Join-Path $app 'runtime') | Out-Null
    [IO.File]::WriteAllText((Join-Path $app 'runtime/sokna-runtime.php'), '<?php if(in_array("--self-check",$argv,true)){exit(0);} file_put_contents(__DIR__."/started-".getmypid().".txt",json_encode($argv)); if(!in_array("--child",$argv,true)){$child=proc_open([PHP_BINARY,__FILE__,"--child"],[0=>["file","NUL","r"],1=>["file",__DIR__."/child.log","a"],2=>["file",__DIR__."/child.log","a"]],$pipes);file_put_contents(__DIR__."/spawn-result.txt",is_resource($child)?"started":"failed");} while(true){sleep(1);}')
    [IO.File]::WriteAllText((Join-Path $app 'config.php'), '<?php /* secret-canary-123 */')
    [IO.File]::WriteAllText((Join-Path $app 'install.lock'), 'preserve-lock')
    [IO.File]::WriteAllText((Join-Path $app 'VERSION.txt'), 'fixture-updater-owned')
    [IO.File]::WriteAllText((Join-Path $app 'live-updater-owned.txt'), 'must-survive-msi-repair-byte-for-byte')
    # The generic SCM Repair fixture has no business database. Seed an existing local
    # Print Worker pairing so this test verifies that healthy Repair preserves it rather
    # than invoking the DB-owned missing-pairing recovery path.
    $workerData = Join-Path $data 'print-worker'
    New-SoknaPrivateDirectory $workerData | Out-Null
    [IO.File]::WriteAllText((Join-Path $workerData 'config.json'), '{"server_base_url":"http://127.0.0.1:9","agent_name":"CI fixture"}')
    [IO.File]::WriteAllBytes((Join-Path $workerData 'secret.dat'), [byte[]](1,2,3,4,5,6,7,8))
    $workerConfigHash = (Get-FileHash (Join-Path $workerData 'config.json')).Hash
    $workerSecretHash = (Get-FileHash (Join-Path $workerData 'secret.dat')).Hash
    $configHash = (Get-FileHash (Join-Path $app 'config.php')).Hash
    $versionHash = (Get-FileHash (Join-Path $app 'VERSION.txt')).Hash
    $liveOwnerHash = (Get-FileHash (Join-Path $app 'live-updater-owned.txt')).Hash
    $repairArguments = @('-Mode','Repair','-AppRoot',$app,'-DataRoot',$data,'-SkipHttps')
    $ownsService = $true
    $s = Run-Setup $repairArguments
    $ownsPrintWorker = $true
    Assert ((Get-Service SoknaRuntime).Status -eq 'Running') 'Real SCM service did not start'
    Assert ((Get-Service SoknaPrintWorker).Status -eq 'Running') 'Internal Print Worker SCM service did not start'
    $before = (Get-ItemProperty 'HKLM:\SYSTEM\CurrentControlSet\Services\SoknaRuntime').ImagePath
    $s = Run-Setup $repairArguments
    Assert ((Get-ItemProperty 'HKLM:\SYSTEM\CurrentControlSet\Services\SoknaRuntime').ImagePath -eq $before) 'Repair changed service configuration'
    Assert ((Get-FileHash (Join-Path $app 'config.php')).Hash -eq $configHash) 'Repair changed application config/secret'
    Assert ((Get-FileHash (Join-Path $workerData 'config.json')).Hash -eq $workerConfigHash) 'Healthy Repair replaced Print Worker config'
    Assert ((Get-FileHash (Join-Path $workerData 'secret.dat')).Hash -eq $workerSecretHash) 'Healthy Repair rotated Print Worker secret'
    Assert ((Get-FileHash (Join-Path $app 'VERSION.txt')).Hash -eq $versionHash) 'Repair rewrote updater-owned VERSION.txt'
    Assert ((Get-FileHash (Join-Path $app 'live-updater-owned.txt')).Hash -eq $liveOwnerHash) 'Repair rewrote updater-owned live application payload'
    for ($attempt=0; $attempt -lt 20; $attempt++) {
        # SYSTEM-owned processes can have a null CIM CommandLine; identify our
        # fixture by its own PID markers, including previous generations.
        $children = @(Get-ChildItem (Join-Path $app 'runtime') -Filter 'started-*.txt' | ForEach-Object {
            $fixturePid = [int]($_.BaseName -replace '^started-','')
            Get-Process -Id $fixturePid -ErrorAction SilentlyContinue
        })
        if ($children.Count -eq 2) { break }
        Start-Sleep -Milliseconds 100
    }
    if ($children.Count -ne 2) {
        Write-Host ('Fixture process count=' + $children.Count)
        $children | Select-Object Id,ProcessName | ConvertTo-Json -Compress | Write-Host
        Get-ChildItem (Join-Path $app 'runtime') -File | Where-Object { $_.Extension -in @('.txt','.log') } | ForEach-Object { Write-Host $_.Name; Get-Content $_.FullName | Write-Host }
        Get-Content (Join-Path $data 'logs/runtime-service-host.log') | Write-Host
    }
    Assert ($children.Count -eq 2) 'Repair must leave exactly one runtime and one fixture worker'

    # Inject an SCM start failure AFTER replacing the installed binary.
    $badSource = Join-Path $root 'FailService.cs'
    [IO.File]::WriteAllText($badSource, 'using System; using System.ServiceProcess; public class FailService:ServiceBase { public FailService(){ServiceName="SoknaRuntime";} protected override void OnStart(string[] a){throw new Exception("injected start failure");} public static int Main(string[] a){if(Array.IndexOf(a,"--self-test")>=0)return 0; ServiceBase.Run(new FailService());return 0;} }')
    $badExe = Join-Path $root 'FailService.exe'
    $csc = "$env:WINDIR\Microsoft.NET\Framework64\v4.0.30319\csc.exe"
    Invoke-SoknaProcess $csc @('/nologo','/target:exe','/reference:System.ServiceProcess.dll',"/out:$badExe",$badSource) | Out-Null
    $goodHash = (Get-FileHash (Join-Path $data 'bin/SoknaRuntimeService.exe')).Hash
    $burnLog = Join-Path $root 'burn-source.log'
    $msiLog = Join-Path $root 'msi-source.log'
    [IO.File]::WriteAllText($burnLog, 'burn diagnostic password=secret-canary-123')
    [IO.File]::WriteAllText($msiLog, 'msi diagnostic token=other-secret')
    $env:SOKNA_BURN_LOG_PATH = $burnLog
    $env:SOKNA_MSI_LOG_PATH = $msiLog
    $goodHost = $ServiceHostExe
    $ServiceHostExe = $badExe
    $s = Run-Setup $repairArguments 2
    $ServiceHostExe = $goodHost
    Assert ((Get-FileHash (Join-Path $data 'bin/SoknaRuntimeService.exe')).Hash -eq $goodHash) 'Failed repair did not restore original host binary'
    Assert ((Get-Service SoknaRuntime).Status -eq 'Running') 'Failed repair did not restore running service'
    Assert ((Get-FileHash (Join-Path $app 'VERSION.txt')).Hash -eq $versionHash) 'Failed Repair downgraded updater-owned VERSION.txt'
    Assert ((Get-FileHash (Join-Path $app 'live-updater-owned.txt')).Hash -eq $liveOwnerHash) 'Failed Repair changed updater-owned live application payload'
    $bundle = Join-Path $root 'expanded-diagnostics'
    Expand-Archive (Join-Path $s.diagnostics_directory 'support.zip') $bundle
    $files = @(Get-ChildItem $bundle -File)
    $allowedSupportFiles = @('summary.json','events.jsonl','components.json','burn.log','msi.log')
    Assert ($files.Count -ge 3 -and $files.Count -le $allowedSupportFiles.Count) 'Support bundle file count is outside the explicit allowlist'
    foreach ($file in $files) { Assert ($allowedSupportFiles -contains $file.Name) ('Support bundle contains unexpected file: ' + $file.Name) }
    Assert (Test-Path (Join-Path $bundle 'components.json')) 'Support bundle lacks structured component/service diagnostics'
    Assert (Test-Path (Join-Path $bundle 'burn.log')) 'Support bundle did not include sanitized Burn diagnostics'
    Assert (Test-Path (Join-Path $bundle 'msi.log')) 'Support bundle did not include sanitized MSI diagnostics'
    $components = Get-Content (Join-Path $bundle 'components.json') -Raw | ConvertFrom-Json
    Assert ($components.schema_version -eq 1) 'Component diagnostic schema is invalid'
    Assert ($components.runtime_service.name -eq 'SoknaRuntime') 'Runtime service diagnostic missing'
    Assert ($components.print_worker_service.name -eq 'SoknaPrintWorker') 'Print Worker diagnostic missing'
    Assert ((Get-Content (Join-Path $bundle '*') -Raw | Out-String) -notmatch 'secret-canary-123|other-secret') 'Support bundle leaked secret'

    # Read a live installer log, redact credentials, and retain the original failure.
    $installLog = Join-Path $root 'native-installer.log'
    [IO.File]::WriteAllText($installLog, "Installer evidence marker`r`npassword=installer-secret-canary")
    $heldLog = [IO.File]::Open($installLog,[IO.FileMode]::Open,[IO.FileAccess]::ReadWrite,[IO.FileShare]::ReadWrite)
    try {
        $s = Run-Setup @('-Mode','Validate','-AppRoot',$app,'-DataRoot',$data,'-OpenSslExe',(Join-Path $root 'missing.exe'),'-InstallerLogFile',$installLog) 2
    } finally { $heldLog.Dispose() }
    Assert ($s.installer_log_status -eq 'included') 'Live installer log was not captured'
    Assert ($s.support_bundle_status -eq 'created') 'Combined support ZIP missing'
    $combined = Join-Path $root 'combined-diagnostics'
    Expand-Archive $s.support_bundle $combined
    Assert (@(Get-ChildItem $combined -File).Count -eq 3) 'Combined ZIP must contain only three allowlisted files'
    $log = Get-Content (Join-Path $combined 'installer-snapshot.log') -Raw
    Assert ($log -match 'Installer evidence marker' -and $log -notmatch 'installer-secret-canary') 'Installer evidence lost or credential leaked'
    $s = Run-Setup @('-Mode','Validate','-AppRoot',$app,'-DataRoot',$data,'-OpenSslExe',(Join-Path $root 'missing.exe'),'-InstallerLogFile',(Join-Path $root 'missing.log')) 2
    Assert ($s.installer_log_status -eq 'unavailable' -and $s.error_code -eq 'SOKNA_SETUP_PREFLIGHT') 'Missing log replaced the primary failure'

    # TLS repeat/repair must preserve exact bytes, including CA and server private keys.
    $tls = Join-Path $root 'tls-data'
    $provision = Join-Path $repo 'runtime/windows/provision-local-https.ps1'
    & $provision -OpenSslExe $OpenSslExe -DataRoot $tls -Hostname 'sokna.local' | Out-Null
    $caFile = Join-Path $tls 'secrets/tls/local-ca.crt.pem'
    $caThumb = (New-Object Security.Cryptography.X509Certificates.X509Certificate2($caFile)).Thumbprint
    $hashes = @{}
    Get-ChildItem (Join-Path $tls 'secrets/tls') -File | ForEach-Object { $hashes[$_.Name] = (Get-FileHash $_.FullName).Hash }
    & $provision -OpenSslExe $OpenSslExe -DataRoot $tls -Hostname 'sokna.local' | Out-Null
    foreach ($name in $hashes.Keys) { Assert ((Get-FileHash (Join-Path $tls "secrets/tls/$name")).Hash -eq $hashes[$name]) 'Repair rotated an existing TLS identity' }
    Remove-Item (Join-Path $tls 'secrets/tls/server.crt.pem')
    $blocked = $false
    try { & $provision -OpenSslExe $OpenSslExe -DataRoot $tls -Hostname 'sokna.local' | Out-Null } catch { $blocked = $true }
    Assert $blocked 'Partial TLS identity was silently regenerated'
    Assert ((Get-FileHash (Join-Path $tls 'secrets/tls/local-ca.key.pem')).Hash -eq $hashes['local-ca.key.pem']) 'Partial identity check changed CA key'
    Write-Host 'Phase 8B Windows setup runtime PASS: quoting, private ACL, diagnostics, preflight, real SCM repair/rollback and TLS identity preservation.'
} finally {
    Remove-Item Env:SOKNA_BURN_LOG_PATH -ErrorAction SilentlyContinue
    Remove-Item Env:SOKNA_MSI_LOG_PATH -ErrorAction SilentlyContinue
    if ($ownsPrintWorker) {
        if (Get-Service SoknaPrintWorker -ErrorAction SilentlyContinue) {
            Stop-Service SoknaPrintWorker -ErrorAction SilentlyContinue
            try { Wait-SoknaService 'SoknaPrintWorker' 'Stopped' } catch { }
            & "$env:SystemRoot\System32\sc.exe" delete SoknaPrintWorker | Out-Null
        }
        Remove-Item 'HKLM:\SOFTWARE\Sokna\Local\PrintWorker' -Recurse -Force -ErrorAction SilentlyContinue
    }
    if ($ownsService) {
        if (Get-Service SoknaRuntime -ErrorAction SilentlyContinue) {
            Stop-Service SoknaRuntime -ErrorAction SilentlyContinue
            try { Wait-SoknaService 'SoknaRuntime' 'Stopped' } catch { }
            & "$env:SystemRoot\System32\sc.exe" delete SoknaRuntime | Out-Null
        }
    }
    if ($caThumb) { Remove-Item "Cert:\LocalMachine\Root\$caThumb" -ErrorAction SilentlyContinue }
    foreach ($session in $sessions) { if ($session) { Remove-Item -Recurse -Force $session -ErrorAction SilentlyContinue } }
    Remove-Item -LiteralPath $root -Recurse -Force -ErrorAction SilentlyContinue
}

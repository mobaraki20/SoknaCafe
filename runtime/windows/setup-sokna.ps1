param(
    [ValidateSet('New','Recover','Repair','Validate')][string]$Mode = 'Validate',
    [string]$AppRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path,
    [Parameter(Mandatory=$true)][string]$PhpExe,
    [string]$OpenSslExe = '',
    [string]$ServiceHostExe = '',
    [string]$DataRoot = "$env:ProgramData\SOKNA",
    [string]$Hostname = 'sokna.local',
    [string]$SetupConfigFile = '',
    [string]$RecoveryFile = '',
    [string]$RecoveryPassphraseFile = '',
    [string]$PrintAgentSetup = '',
    [string]$PrintAgentSha256 = '',
    [switch]$SkipHttps,
    [switch]$SkipService
)
$ErrorActionPreference = 'Stop'
Import-Module (Join-Path $PSScriptRoot 'setup-support.psm1') -Force
$ServiceName = 'SoknaRuntime'
$sessionId = [guid]::NewGuid().ToString('N')
$session = ''
$stage = 'diagnostics'
$privateConfig = ''
$privatePassphrase = ''
$exitCode = 2
$oldDataDir = $env:SOKNA_DATA_DIR
$summary = [ordered]@{ session_id=$sessionId; mode=$Mode; ok=$false; started_utc=[DateTime]::UtcNow.ToString('o'); stage=$stage; version='unknown'; reboot_required=$false; business_setup_committed=$false }

function Write-SetupEvent([string]$Name, [string]$Message) {
    if ($session) {
        [ordered]@{ utc=[DateTime]::UtcNow.ToString('o'); session_id=$sessionId; stage=$Name; message=(Protect-SoknaLog $Message) } |
            ConvertTo-Json -Compress | Add-Content -LiteralPath (Join-Path $session 'events.jsonl') -Encoding UTF8
    }
}

function Assert-File([string]$Path, [string]$Label) {
    if (-not $Path -or -not (Test-Path -LiteralPath $Path -PathType Leaf)) { throw "$Label file was not found." }
    Assert-SoknaSafePath $Path
}

function Copy-PrivateInput([string]$Path, [string]$Name) {
    # Session ACL is already restricted before any secret bytes are written.
    $destination = Join-Path $session ($Name + '.private')
    [IO.File]::WriteAllBytes($destination, [IO.File]::ReadAllBytes($Path))
    return $destination
}

function Get-RuntimeBinPath([string]$HostExe) {
    ((@($HostExe,'--php',$PhpExe,'--app-root',$AppRoot,'--data-root',$DataRoot) | ForEach-Object { ConvertTo-SoknaArgument $_ }) -join ' ')
}

function Install-RuntimeService([string]$Candidate) {
    $binDir = New-SoknaPrivateDirectory (Join-Path $DataRoot 'bin')
    $hostExe = Join-Path $binDir 'SoknaRuntimeService.exe'
    $existing = Get-Service -Name $ServiceName -ErrorAction SilentlyContinue
    $wasRunning = $existing -and $existing.Status -eq 'Running'
    $backup = Join-Path $session 'previous-service.exe.private'
    $hadBinary = Test-Path -LiteralPath $hostExe
    $created = $false
    $sc = "$env:SystemRoot\System32\sc.exe"
    if ($hadBinary) { Copy-Item -LiteralPath $hostExe -Destination $backup }
    try {
        if ($existing -and $existing.Status -ne 'Stopped') {
            Stop-Service -Name $ServiceName -ErrorAction Stop
            Wait-SoknaService $ServiceName 'Stopped'
        }
        Copy-Item -LiteralPath $Candidate -Destination $hostExe -Force
        if (-not $existing) {
            Invoke-SoknaProcess $sc @('create',$ServiceName,'binPath=',(Get-RuntimeBinPath $hostExe),'start=','auto','DisplayName=','SOKNA Local Runtime') | Out-Null
            $created = $true
            Invoke-SoknaProcess $sc @('description',$ServiceName,'SOKNA Local Runtime process supervisor') | Out-Null
            Invoke-SoknaProcess $sc @('failure',$ServiceName,'reset=','86400','actions=','restart/5000/restart/15000/restart/30000') | Out-Null
        }
        Start-Service -Name $ServiceName
        Wait-SoknaService $ServiceName 'Running'
        Write-SetupEvent 'runtime-service' 'Service reached RUNNING; application health is a separate gate.'
    } catch {
        $failure = $_
        try {
            $current = Get-Service -Name $ServiceName -ErrorAction SilentlyContinue
            if ($current -and $current.Status -ne 'Stopped') { Stop-Service $ServiceName; Wait-SoknaService $ServiceName 'Stopped' }
            if ($hadBinary) { Copy-Item -LiteralPath $backup -Destination $hostExe -Force }
            elseif (Test-Path -LiteralPath $hostExe) { Remove-Item -LiteralPath $hostExe -Force }
            if ($created) { Invoke-SoknaProcess $sc @('delete',$ServiceName) | Out-Null }
            elseif ($wasRunning) { Start-Service $ServiceName; Wait-SoknaService $ServiceName 'Running' }
            Write-SetupEvent 'service-rollback' 'Previous service binary and running state restored.'
        } catch { Write-SetupEvent 'service-rollback-failed' $_.Exception.Message }
        throw $failure
    } finally { Remove-Item -LiteralPath $backup -Force -ErrorAction SilentlyContinue }
}

try {
    # Available even when preflight fails before ProgramData is writable.
    $session = New-SoknaPrivateDirectory (Join-Path $env:TEMP ('SOKNA-setup-' + $sessionId))
    $stage = 'preflight'
    Write-SetupEvent $stage 'Preflight started; no application mutation yet.'
    $AppRoot = [IO.Path]::GetFullPath($AppRoot).TrimEnd('\')
    $DataRoot = [IO.Path]::GetFullPath($DataRoot).TrimEnd('\')
    Assert-SoknaSafePath $AppRoot
    Assert-SoknaSafePath $DataRoot
    Assert-File $PhpExe 'PHP executable'
    Assert-File (Join-Path $AppRoot 'runtime\sokna-runtime.php') 'Runtime entrypoint'
    if ($Hostname -notmatch '^(?=.{1,253}$)[a-z0-9]+(?:[.-][a-z0-9]+)*$') { throw 'Invalid local hostname.' }
    if (Test-Path (Join-Path $AppRoot 'VERSION.txt')) { $summary.version = (Get-Content (Join-Path $AppRoot 'VERSION.txt') -Raw).Trim() }
    $isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
    if ($Mode -ne 'Validate' -and -not $isAdmin) { throw 'Administrator privileges are required.' }
    if (-not $SkipHttps) { Assert-File $OpenSslExe 'OpenSSL executable'; Invoke-SoknaProcess $OpenSslExe @('version') | Out-Null }
    if (-not $ServiceHostExe) { $ServiceHostExe = Join-Path $PSScriptRoot 'bin\SoknaRuntimeService.exe' }
    Assert-File $ServiceHostExe 'Prebuilt Runtime Service Host (build it in CI)'
    $phpCheck = 'if(PHP_VERSION_ID<80200){fwrite(STDERR,"PHP 8.2+ required");exit(2);}foreach(["pdo_mysql","fileinfo","openssl","sodium","mbstring"] as $e){if(!extension_loaded($e)){fwrite(STDERR,"Missing PHP extension: ".$e);exit(2);}}'
    Invoke-SoknaProcess $PhpExe @('-r',$phpCheck) | Out-Null
    # Validate touches only this private session, never the target data root or SCM.
    Invoke-SoknaProcess $ServiceHostExe @('--self-test','--php',$PhpExe,'--app-root',$AppRoot,'--data-root',(Join-Path $session 'host-validation')) | Out-Null
    if ($PrintAgentSetup) {
        Assert-File $PrintAgentSetup 'Print Agent Setup'
        if ((Split-Path -Leaf $PrintAgentSetup) -notmatch '^Sokna-Print-Agent-\d+\.\d+\.\d+-Setup\.exe$') { throw 'Print Agent requires a versioned stable Setup asset.' }
        if ($PrintAgentSha256 -notmatch '^[a-fA-F0-9]{64}$' -or (Get-FileHash -LiteralPath $PrintAgentSetup -Algorithm SHA256).Hash -ne $PrintAgentSha256) { throw 'Print Agent payload SHA256 is missing or mismatched; use the trusted release manifest.' }
    }
    $existing = Get-Service -Name $ServiceName -ErrorAction SilentlyContinue
    if ($Mode -in @('New','Recover') -and $existing) { throw 'Runtime service already exists; use Repair with the installed paths.' }
    if ($Mode -eq 'Repair') {
        Assert-File (Join-Path $AppRoot 'config.php') 'Installed configuration'
        Assert-File (Join-Path $AppRoot 'install.lock') 'Installation lock'
        if ($existing) {
            $imagePath = (Get-ItemProperty "HKLM:\SYSTEM\CurrentControlSet\Services\$ServiceName").ImagePath
            $expected = Get-RuntimeBinPath (Join-Path $DataRoot 'bin\SoknaRuntimeService.exe')
            if ($imagePath -ne $expected) { throw 'Service ownership/path mismatch; repair with the original installation paths. Existing service was not changed.' }
        }
    }
    if ($Mode -in @('New','Recover')) {
        Assert-File $SetupConfigFile 'Setup config'
        if ((Get-Item -LiteralPath $SetupConfigFile).Length -gt 1MB) { throw 'Setup config is too large.' }
        $inputConfig = Get-Content -LiteralPath $SetupConfigFile -Raw | ConvertFrom-Json
        # Register secrets before invoking any child process that can echo an error.
        foreach ($value in @($inputConfig.db.pass,$inputConfig.admin_password,$inputConfig.relay.shared_secret)) { if ($value) { Add-SoknaSecret ([string]$value) } }
        if ($inputConfig.data_dir -and [IO.Path]::GetFullPath($inputConfig.data_dir).TrimEnd('\') -ne $DataRoot) { throw 'Config data_dir does not match DataRoot.' }
        $inputConfig | Add-Member -Force NoteProperty data_dir $DataRoot
        $inputConfig | Add-Member -Force NoteProperty local_hostname $Hostname
        $privateConfig = Join-Path $session 'setup-config.private'
        [IO.File]::WriteAllText($privateConfig, ($inputConfig | ConvertTo-Json -Depth 20), (New-Object Text.UTF8Encoding($false)))
        if ($Mode -eq 'Recover') {
            Assert-File $RecoveryFile 'Recovery Set'
            if ($RecoveryPassphraseFile) {
                Assert-File $RecoveryPassphraseFile 'Recovery passphrase'
                if ((Get-Item -LiteralPath $RecoveryPassphraseFile).Length -gt 8192) { throw 'Recovery passphrase is too large.' }
                Add-SoknaSecret ([IO.File]::ReadAllText($RecoveryPassphraseFile).TrimEnd("`r","`n"))
                $privatePassphrase = Copy-PrivateInput $RecoveryPassphraseFile 'recovery-passphrase'
            }
        }
        Invoke-SoknaProcess $PhpExe @((Join-Path $AppRoot 'tools\setup-machine.php'),"--mode=$($Mode.ToLower())","--config-file=$privateConfig",'--validate-only') | Out-Null
    }
    Write-SetupEvent $stage 'Prerequisite and target checks passed.'
    if ($Mode -eq 'Validate') { $summary.ok = $true; $exitCode = 0 }
    else {
        $env:SOKNA_DATA_DIR = $DataRoot
        if ($Mode -in @('New','Recover')) {
            $stage = 'application-setup'
            $arguments = @((Join-Path $AppRoot 'tools\setup-machine.php'),"--mode=$($Mode.ToLower())","--config-file=$privateConfig")
            if ($Mode -eq 'Recover') {
                $arguments += "--recovery-file=$RecoveryFile"
                if ($privatePassphrase) { $arguments += "--passphrase-file=$privatePassphrase" }
            }
            Invoke-SoknaProcess -File $PhpExe -Arguments $arguments -TimeoutSeconds 1800 | Out-Null
            $summary.business_setup_committed = $true
            Write-SetupEvent $stage 'Application owner completed; later failures require Repair, not re-running New/Recover.'
        }
        $stage = 'https'
        if (-not $SkipHttps) { & (Join-Path $PSScriptRoot 'provision-local-https.ps1') -OpenSslExe $OpenSslExe -DataRoot $DataRoot -Hostname $Hostname | Out-Null }
        $stage = 'runtime-service'
        if (-not $SkipService) { Install-RuntimeService $ServiceHostExe }
        $stage = 'print-agent'
        if ($PrintAgentSetup) {
            $agent = Invoke-SoknaProcess -File $PrintAgentSetup -Arguments @() -SuccessCodes @(0,3010) -TimeoutSeconds 1800
            $summary.reboot_required = $agent.ExitCode -eq 3010
        }
        $stage = 'runtime-self-check'
        Invoke-SoknaProcess $PhpExe @((Join-Path $AppRoot 'runtime\sokna-runtime.php'),'--self-check') | Out-Null
        $summary.ok = $true
        $summary.runtime_service = $(if ($SkipService) { 'skipped' } else { 'running' })
        $summary.https = $(if ($SkipHttps) { 'skipped' } else { 'provisioned' })
        $summary.health_scope = 'runtime-self-check; HTTP/database/printer end-to-end acceptance remains required'
        $exitCode = $(if ($summary.reboot_required) { 3010 } else { 0 })
    }
} catch {
    $summary.error_code = 'SOKNA_SETUP_' + $stage.Replace('-','_').ToUpperInvariant()
    $summary.error = Protect-SoknaLog $_.Exception.Message
    $summary.message_fa = 'نصب یا تعمیر کامل نشد. مرحله خطا و گزارش تشخیص را بررسی کنید.'
    $summary.next_action = $(if ($summary.business_setup_committed) { 'Business setup completed: preserve data and use Repair after fixing the reported prerequisite.' } else { 'Fix the reported error. If application setup had started, inspect target state before retrying.' })
    Write-SetupEvent $stage $summary.error
} finally {
    $env:SOKNA_DATA_DIR = $oldDataDir
    foreach ($file in @($privateConfig,$privatePassphrase)) { if ($file) { Remove-Item -LiteralPath $file -Force -ErrorAction SilentlyContinue } }
    $summary.stage = $stage
    $summary.exit_code = $exitCode
    $summary.finished_utc = [DateTime]::UtcNow.ToString('o')
    if ($session) {
        # Explicit allowlist: never recursively archive setup inputs, keys or backups.
        $summary.diagnostics_directory = $session
        $summary | ConvertTo-Json -Depth 8 | Set-Content -LiteralPath (Join-Path $session 'summary.json') -Encoding UTF8
        try {
            Compress-Archive -LiteralPath @((Join-Path $session 'summary.json'),(Join-Path $session 'events.jsonl')) -DestinationPath (Join-Path $session 'support.zip') -Force
        } catch { [Console]::Error.WriteLine('Support ZIP could not be created; summary.json and events.jsonl remain available.') }
    }
    $summary | ConvertTo-Json -Depth 8
}
exit $exitCode

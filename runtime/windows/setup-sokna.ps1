param(
    [ValidateSet('New','Recover','Repair','Validate')][string]$Mode = 'Validate',
    [string]$AppRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path,
    [Parameter(Mandatory=$true)][string]$PhpExe,
    [string]$OpenSslExe = '',
    [string]$DataRoot = "$env:ProgramData\SOKNA",
    [string]$Hostname = 'sokna.local',
    [string]$SetupConfigFile = '',
    [string]$RecoveryFile = '',
    [string]$RecoveryPassphraseFile = '',
    [string]$PrintAgentSetup = '',
    [switch]$SkipHttps,
    [switch]$SkipService
)

$ErrorActionPreference = 'Stop'
$ServiceName = 'SoknaRuntime'

function Assert-Admin {
    $principal = [Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()
    if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
        throw 'Administrator privileges are required.'
    }
}

function Find-Csc {
    $candidates = @(
        "$env:WINDIR\Microsoft.NET\Framework64\v4.0.30319\csc.exe",
        "$env:WINDIR\Microsoft.NET\Framework\v4.0.30319\csc.exe"
    )
    foreach ($candidate in $candidates) {
        if (Test-Path -LiteralPath $candidate -PathType Leaf) { return $candidate }
    }
    throw '.NET Framework C# compiler was not found.'
}

function Build-ServiceHost([string]$OutputPath) {
    $source = Join-Path $AppRoot 'runtime\windows\SoknaRuntimeService.cs'
    if (-not (Test-Path -LiteralPath $source -PathType Leaf)) {
        throw 'Runtime Service Host source is missing.'
    }
    New-Item -ItemType Directory -Force -Path (Split-Path -Parent $OutputPath) | Out-Null
    $csc = Find-Csc
    & $csc /nologo /target:exe /reference:System.ServiceProcess.dll "/out:$OutputPath" $source
    if ($LASTEXITCODE -ne 0 -or -not (Test-Path -LiteralPath $OutputPath -PathType Leaf)) {
        throw 'Runtime Service Host build failed.'
    }
}

function Invoke-Sc([string[]]$Arguments, [switch]$AllowFailure) {
    $process = Start-Process -FilePath "$env:SystemRoot\System32\sc.exe" -ArgumentList $Arguments -Wait -PassThru -NoNewWindow
    if (-not $AllowFailure -and $process.ExitCode -ne 0) {
        throw "sc.exe failed: $($Arguments -join ' ')"
    }
    return $process.ExitCode
}

function Install-RuntimeService([string]$HostExe) {
    $exists = (Invoke-Sc @('query',$ServiceName) -AllowFailure) -eq 0
    if ($exists -and $Mode -ne 'Repair') {
        throw 'SoknaRuntime service already exists; use Repair explicitly.'
    }
    if ($exists) {
        Invoke-Sc @('stop',$ServiceName) -AllowFailure | Out-Null
        Start-Sleep -Seconds 1
        Invoke-Sc @('delete',$ServiceName) | Out-Null
        Start-Sleep -Seconds 1
    }

    $quote = [char]34
    $binPath = $quote + $HostExe + $quote +
        ' --php ' + $quote + $PhpExe + $quote +
        ' --app-root ' + $quote + $AppRoot + $quote +
        ' --data-root ' + $quote + $DataRoot + $quote

    Invoke-Sc @('create',$ServiceName,'binPath=',$binPath,'start=','auto','DisplayName=','SOKNA Local Runtime') | Out-Null
    Invoke-Sc @('description',$ServiceName,'SOKNA Local Runtime process supervisor') | Out-Null
    Invoke-Sc @('failure',$ServiceName,'reset=','86400','actions=','restart/5000/restart/15000/restart/30000') | Out-Null
    Invoke-Sc @('start',$ServiceName) | Out-Null

    for ($i=0; $i -lt 20; $i++) {
        $output = & "$env:SystemRoot\System32\sc.exe" query $ServiceName | Out-String
        if ($output -match 'STATE\s*:\s*4\s+RUNNING') { return }
        Start-Sleep -Milliseconds 500
    }
    throw 'SoknaRuntime service did not reach RUNNING.'
}

function Copy-PrivateInput([string]$Path, [string]$Name) {
    if (-not (Test-Path -LiteralPath $Path -PathType Leaf)) {
        throw "$Name file was not found."
    }
    $dir = Join-Path $DataRoot 'setup'
    New-Item -ItemType Directory -Force -Path $dir | Out-Null
    $destination = Join-Path $dir ($Name + '-' + [guid]::NewGuid().ToString('N') + '.tmp')
    Copy-Item -LiteralPath $Path -Destination $destination
    return $destination
}

if (-not (Test-Path -LiteralPath $PhpExe -PathType Leaf)) {
    throw 'PHP executable was not found.'
}
if (-not (Test-Path -LiteralPath (Join-Path $AppRoot 'runtime\sokna-runtime.php') -PathType Leaf)) {
    throw 'SOKNA Runtime entrypoint is missing.'
}

if ($Mode -eq 'Validate') {
    $tempExe = Join-Path $env:TEMP ('SoknaRuntimeService-' + [guid]::NewGuid().ToString('N') + '.exe')
    $validateData = Join-Path $env:TEMP ('SOKNA-validate-' + [guid]::NewGuid().ToString('N'))
    try {
        Build-ServiceHost $tempExe
        & $tempExe --self-test --php $PhpExe --app-root $AppRoot --data-root $validateData
        if ($LASTEXITCODE -ne 0) { throw 'Runtime Service Host self-test failed.' }
        [pscustomobject]@{
            ok = $true
            mode = 'Validate'
            service_host = 'compiled'
            runtime_entrypoint = 'valid'
        } | ConvertTo-Json -Compress
        exit 0
    } finally {
        Remove-Item -Force -ErrorAction SilentlyContinue $tempExe
        Remove-Item -Recurse -Force -ErrorAction SilentlyContinue $validateData
    }
}

Assert-Admin
New-Item -ItemType Directory -Force -Path $DataRoot | Out-Null
$env:SOKNA_DATA_DIR = $DataRoot
$privateConfig = ''
$privatePassphrase = ''

try {
    if ($Mode -in @('New','Recover')) {
        if ($SetupConfigFile -eq '') { throw 'SetupConfigFile is required.' }
        $privateConfig = Copy-PrivateInput $SetupConfigFile 'setup-config'
        $arguments = @(
            (Join-Path $AppRoot 'tools\setup-machine.php'),
            "--mode=$($Mode.ToLower())",
            "--config-file=$privateConfig"
        )

        if ($Mode -eq 'Recover') {
            if ($RecoveryFile -eq '') { throw 'RecoveryFile is required in Recover mode.' }
            $arguments += "--recovery-file=$RecoveryFile"
            if ($RecoveryPassphraseFile -ne '') {
                $privatePassphrase = Copy-PrivateInput $RecoveryPassphraseFile 'recovery-passphrase'
                $arguments += "--passphrase-file=$privatePassphrase"
            }
        }

        & $PhpExe @arguments
        if ($LASTEXITCODE -ne 0) { throw 'SOKNA application setup/recovery failed.' }
    }

    if (-not $SkipHttps) {
        if ($OpenSslExe -eq '') { throw 'OpenSslExe is required unless SkipHttps is used.' }
        & (Join-Path $AppRoot 'runtime\windows\provision-local-https.ps1') -OpenSslExe $OpenSslExe -DataRoot $DataRoot -Hostname $Hostname | Out-Null
    }

    $hostExe = Join-Path $DataRoot 'bin\SoknaRuntimeService.exe'
    Build-ServiceHost $hostExe
    & $hostExe --self-test --php $PhpExe --app-root $AppRoot --data-root $DataRoot
    if ($LASTEXITCODE -ne 0) { throw 'Runtime Service Host self-test failed.' }
    if (-not $SkipService) { Install-RuntimeService $hostExe }

    $printStatus = 'not_requested'
    if ($PrintAgentSetup -ne '') {
        if (-not (Test-Path -LiteralPath $PrintAgentSetup -PathType Leaf)) {
            throw 'Print Agent Setup file was not found.'
        }
        if ((Split-Path -Leaf $PrintAgentSetup) -notmatch '^Sokna-Print-Agent-\d+\.\d+\.\d+-Setup\.exe$') {
            throw 'Print Agent Setup filename is not a versioned stable asset.'
        }
        $process = Start-Process -FilePath $PrintAgentSetup -Wait -PassThru
        if ($process.ExitCode -ne 0) { throw 'Print Agent Setup did not complete successfully.' }
        $printStatus = 'installer_completed'
    }

    [pscustomobject]@{
        ok = $true
        mode = $Mode
        data_root = $DataRoot
        hostname = $Hostname
        runtime_service = $(if ($SkipService) { 'skipped' } else { 'running' })
        https = $(if ($SkipHttps) { 'skipped' } else { 'provisioned' })
        print_agent = $printStatus
        optional_setup = [ordered]@{
            public_pairing = 'secure relay block in setup config or admin pairing'
            offsite_backup = 'first encrypted SKB export after login'
            push = 'VAPID/device enrollment from notification settings'
        }
    } | ConvertTo-Json -Depth 4 -Compress
} finally {
    if ($privateConfig -ne '') { Remove-Item -Force -ErrorAction SilentlyContinue $privateConfig }
    if ($privatePassphrase -ne '') { Remove-Item -Force -ErrorAction SilentlyContinue $privatePassphrase }
}

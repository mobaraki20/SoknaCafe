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

    $app = Join-Path $root 'app'
    $data = Join-Path $root 'data'
    New-Item -ItemType Directory -Force (Join-Path $app 'runtime') | Out-Null
    [IO.File]::WriteAllText((Join-Path $app 'runtime/sokna-runtime.php'), '<?php if(in_array("--self-check",$argv,true)){exit(0);} if(!in_array("--child",$argv,true)){$child=proc_open([PHP_BINARY,__FILE__,"--child"],[], $pipes);} while(true){sleep(1);}')
    [IO.File]::WriteAllText((Join-Path $app 'config.php'), '<?php /* secret-canary-123 */')
    [IO.File]::WriteAllText((Join-Path $app 'install.lock'), 'preserve-lock')
    [IO.File]::WriteAllText((Join-Path $app 'VERSION.txt'), 'fixture')
    $configHash = (Get-FileHash (Join-Path $app 'config.php')).Hash
    $repairArguments = @('-Mode','Repair','-AppRoot',$app,'-DataRoot',$data,'-SkipHttps')
    $ownsService = $true
    $s = Run-Setup $repairArguments
    Assert ((Get-Service SoknaRuntime).Status -eq 'Running') 'Real SCM service did not start'
    $before = (Get-ItemProperty 'HKLM:\SYSTEM\CurrentControlSet\Services\SoknaRuntime').ImagePath
    $s = Run-Setup $repairArguments
    Assert ((Get-ItemProperty 'HKLM:\SYSTEM\CurrentControlSet\Services\SoknaRuntime').ImagePath -eq $before) 'Repair changed service configuration'
    Assert ((Get-FileHash (Join-Path $app 'config.php')).Hash -eq $configHash) 'Repair changed config/secret'
    for ($attempt=0; $attempt -lt 20; $attempt++) {
        $children = @(Get-CimInstance Win32_Process -Filter "Name='php.exe'" | Where-Object { $_.CommandLine -and $_.CommandLine.Contains($app) })
        if ($children.Count -eq 2) { break }
        Start-Sleep -Milliseconds 100
    }
    Assert ($children.Count -eq 2) 'Repair left orphan runtime processes'

    # Inject an SCM start failure AFTER replacing the installed binary.
    $badSource = Join-Path $root 'FailService.cs'
    [IO.File]::WriteAllText($badSource, 'using System; using System.ServiceProcess; public class FailService:ServiceBase { public FailService(){ServiceName="SoknaRuntime";} protected override void OnStart(string[] a){throw new Exception("injected start failure");} public static int Main(string[] a){if(Array.IndexOf(a,"--self-test")>=0)return 0; ServiceBase.Run(new FailService());return 0;} }')
    $badExe = Join-Path $root 'FailService.exe'
    $csc = "$env:WINDIR\Microsoft.NET\Framework64\v4.0.30319\csc.exe"
    Invoke-SoknaProcess $csc @('/nologo','/target:exe','/reference:System.ServiceProcess.dll',"/out:$badExe",$badSource) | Out-Null
    $goodHash = (Get-FileHash (Join-Path $data 'bin/SoknaRuntimeService.exe')).Hash
    $goodHost = $ServiceHostExe
    $ServiceHostExe = $badExe
    $s = Run-Setup $repairArguments 2
    $ServiceHostExe = $goodHost
    Assert ((Get-FileHash (Join-Path $data 'bin/SoknaRuntimeService.exe')).Hash -eq $goodHash) 'Failed repair did not restore original host binary'
    Assert ((Get-Service SoknaRuntime).Status -eq 'Running') 'Failed repair did not restore running service'
    $bundle = Join-Path $root 'expanded-diagnostics'
    Expand-Archive (Join-Path $s.diagnostics_directory 'support.zip') $bundle
    $files = @(Get-ChildItem $bundle -File)
    Assert ($files.Count -eq 2) 'Support bundle contains non-allowlisted files'
    Assert ((Get-Content (Join-Path $bundle '*') -Raw | Out-String) -notmatch 'secret-canary-123') 'Support bundle leaked secret'

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

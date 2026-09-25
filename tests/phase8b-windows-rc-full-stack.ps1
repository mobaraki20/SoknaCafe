param(
    [Parameter(Mandatory=$true)][string]$PrerequisiteBundleRoot,
    [Parameter(Mandatory=$true)][string]$ShellPayloadRoot,
    [string]$RepoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path,
    [switch]$AllowDisposableMachine
)
$ErrorActionPreference='Stop'
[Console]::OutputEncoding = New-Object Text.UTF8Encoding($false)

# This harness mutates machine-wide Windows services and installs/uninstalls the frozen
# MariaDB/VC prerequisites. It is release-engineering evidence only; it is never invoked
# by the customer installer and refuses ordinary developer machines by default.
if($env:OS -ne 'Windows_NT'){throw 'Windows RC full-stack acceptance requires Windows.'}
if($env:GITHUB_ACTIONS -ne 'true' -and -not $AllowDisposableMachine){throw 'RC full-stack acceptance is restricted to a disposable Windows machine.'}

$RepoRoot=[IO.Path]::GetFullPath($RepoRoot).TrimEnd('\')
$PrerequisiteBundleRoot=[IO.Path]::GetFullPath($PrerequisiteBundleRoot).TrimEnd('\')
$ShellPayloadRoot=[IO.Path]::GetFullPath($ShellPayloadRoot).TrimEnd('\')
Import-Module (Join-Path $RepoRoot 'runtime\windows\setup-support.psm1') -DisableNameChecking -Force

function Assert([bool]$Condition,[string]$Message){if(-not $Condition){throw $Message}}
function Find-Artifact($Manifest,[string]$Dependency){
    $matches=@($Manifest.artifacts | Where-Object { [string]$_.dependency -eq $Dependency })
    if($matches.Count -ne 1){throw "Expected exactly one frozen $Dependency artifact in the prerequisite bundle."}
    $path=Join-Path $PrerequisiteBundleRoot ([string]$matches[0].filename)
    if(-not(Test-Path -LiteralPath $path -PathType Leaf)){throw "Frozen prerequisite artifact is missing: $Dependency"}
    return $path
}
function Get-FreePort {
    $listener=[Net.Sockets.TcpListener]::new([Net.IPAddress]::Loopback,0)
    try{$listener.Start();return ([Net.IPEndPoint]$listener.LocalEndpoint).Port}finally{$listener.Stop()}
}
function Wait-Tcp([int]$Port,[int]$Seconds=30){
    $until=[DateTime]::UtcNow.AddSeconds($Seconds)
    while([DateTime]::UtcNow -lt $until){
        $client=New-Object Net.Sockets.TcpClient
        try{$task=$client.ConnectAsync('127.0.0.1',$Port);if($task.Wait(700) -and $client.Connected){return}}catch{}finally{$client.Dispose()}
        Start-Sleep -Milliseconds 250
    }
    throw "TCP listener did not become ready on 127.0.0.1:$Port"
}
function Stop-And-Delete-Service([string]$Name){
    $svc=Get-Service -Name $Name -ErrorAction SilentlyContinue
    if(-not $svc){return}
    if($svc.Status -ne 'Stopped'){
        Stop-Service -Name $Name -Force -ErrorAction SilentlyContinue
        try{Wait-SoknaService $Name 'Stopped'}catch{}
    }
    & "$env:SystemRoot\System32\sc.exe" delete $Name | Out-Null
    for($i=0;$i -lt 40 -and (Get-Service -Name $Name -ErrorAction SilentlyContinue);$i++){Start-Sleep -Milliseconds 250}
    if(Get-Service -Name $Name -ErrorAction SilentlyContinue){throw "Disposable service could not be deleted: $Name"}
}
function Set-PrivateJson([string]$Directory,[string]$Name,[object]$Value){
    New-SoknaPrivateDirectory $Directory | Out-Null
    $path=Join-Path $Directory $Name
    [IO.File]::WriteAllText($path,($Value|ConvertTo-Json -Depth 20),(New-Object Text.UTF8Encoding($false)))
    return $path
}
function Invoke-ScriptExit([string]$Script,[string[]]$Arguments,[int[]]$Allowed){
    $ps="$env:SystemRoot\System32\WindowsPowerShell\v1.0\powershell.exe"
    $r=Invoke-SoknaProcess -File $ps -Arguments (@('-NoProfile','-ExecutionPolicy','Bypass','-File',$Script)+$Arguments) -SuccessCodes $Allowed -TimeoutSeconds 2100
    return [ordered]@{code=[int]$r.ExitCode;output=[string]$r.Output;error=[string]$r.Error}
}
function Ensure-PHP([string]$Zip,[string]$Root){
    Expand-Archive -LiteralPath $Zip -DestinationPath $Root -Force
    $php=Join-Path $Root 'php.exe'
    Assert (Test-Path -LiteralPath $php -PathType Leaf) 'Frozen PHP archive did not contain php.exe.'
    $template=Join-Path $Root 'php.ini-production'
    Assert (Test-Path -LiteralPath $template -PathType Leaf) 'Frozen PHP archive did not contain php.ini-production.'
    $ini=Get-Content -LiteralPath $template -Raw
    $ini=[regex]::Replace($ini,'(?m)^\s*;?\s*extension_dir\s*=.*$','extension_dir = "ext"',1)
    foreach($ext in @('fileinfo','mbstring','openssl','pdo_mysql','sodium')){
        $pattern='(?m)^\s*;\s*extension\s*=\s*'+[regex]::Escape($ext)+'\s*$'
        if($ini -match $pattern){$ini=[regex]::Replace($ini,$pattern,'extension='+$ext,1)}
        elseif($ini -notmatch ('(?m)^\s*extension\s*=\s*'+[regex]::Escape($ext)+'\s*$')){$ini+="`r`nextension=$ext"}
    }
    $ini+="`r`ndate.timezone=Asia/Tehran`r`n"
    [IO.File]::WriteAllText((Join-Path $Root 'php.ini'),$ini,(New-Object Text.UTF8Encoding($false)))
    $probe=Invoke-SoknaProcess $php @('-r','echo json_encode([PHP_VERSION,PHP_INT_SIZE,get_loaded_extensions()]);')
    $info=$probe.Output|ConvertFrom-Json
    Assert ([int]$info[1] -eq 8) 'Frozen PHP runtime is not x64.'
    foreach($ext in @('pdo_mysql','fileinfo','openssl','sodium','mbstring')){Assert (@($info[2]) -contains $ext) "Frozen PHP runtime is missing required extension: $ext"}
    return $php
}
function Ensure-Apache([string]$Zip,[string]$Root,[string]$PhpRoot,[int]$HttpPort,[int]$HttpsPort){
    Expand-Archive -LiteralPath $Zip -DestinationPath $Root -Force
    $httpd=(Get-ChildItem -LiteralPath $Root -Filter httpd.exe -File -Recurse | Select-Object -First 1).FullName
    Assert (-not [string]::IsNullOrWhiteSpace($httpd)) 'Frozen Apache archive did not contain httpd.exe.'
    $apacheRoot=[IO.Directory]::GetParent([IO.Path]::GetDirectoryName($httpd)).FullName
    $conf=Join-Path $apacheRoot 'conf\httpd.conf'
    Assert (Test-Path -LiteralPath $conf -PathType Leaf) 'Frozen Apache archive did not contain conf/httpd.conf.'
    $text=Get-Content -LiteralPath $conf -Raw
    $rootForward=$apacheRoot.Replace('\','/')
    if($text -match '(?m)^\s*Define\s+SRVROOT\s+"[^"]*"'){$text=[regex]::Replace($text,'(?m)^\s*Define\s+SRVROOT\s+"[^"]*"',('Define SRVROOT "'+$rootForward+'"'),1)}
    else{$text=('Define SRVROOT "{0}"' -f $rootForward)+"`r`n"+$text}
    $text=[regex]::Replace($text,'(?m)^\s*Listen\s+[^\r\n]+$',('Listen 127.0.0.1:'+$HttpPort),1)
    foreach($module in @('ssl_module modules/mod_ssl.so','socache_shmcb_module modules/mod_socache_shmcb.so','headers_module modules/mod_headers.so')){
        $parts=$module.Split(' ',2);$name=$parts[0];$file=$parts[1]
        $pattern='(?m)^\s*#\s*LoadModule\s+'+[regex]::Escape($name)+'\s+'+[regex]::Escape($file)+'\s*$'
        if($text -match $pattern){$text=[regex]::Replace($text,$pattern,('LoadModule '+$module),1)}
        elseif($text -notmatch ('(?m)^\s*LoadModule\s+'+[regex]::Escape($name)+'\s+')){$text+="`r`nLoadModule $module"}
    }
    $phpModule=Join-Path $PhpRoot 'php8apache2_4.dll'
    Assert (Test-Path -LiteralPath $phpModule -PathType Leaf) 'Frozen PHP TS archive did not contain php8apache2_4.dll.'
    $phpForward=$PhpRoot.Replace('\','/')
    $moduleForward=$phpModule.Replace('\','/')
    $append=@(
        '# SOKNA RC acceptance PHP owner',
        ('LoadModule php_module "{0}"' -f $moduleForward),
        'AddHandler application/x-httpd-php .php',
        ('PHPIniDir "{0}"' -f $phpForward),
        ('Listen 127.0.0.1:{0}' -f $HttpsPort),
        ('ServerName localhost:{0}' -f $HttpPort)
    ) -join "`r`n"
    $text+="`r`n$append`r`n"
    [IO.File]::WriteAllText($conf,$text,(New-Object Text.UTF8Encoding($false)))
    Invoke-SoknaProcess $httpd @('-t','-f',$conf) | Out-Null
    $openssl=Join-Path ([IO.Path]::GetDirectoryName($httpd)) 'openssl.exe'
    Assert (Test-Path -LiteralPath $openssl -PathType Leaf) 'Frozen Apache archive did not provide bin/openssl.exe.'
    return [ordered]@{exe=$httpd;root=$apacheRoot;config=$conf;openssl=$openssl}
}
function Start-Apache($Apache,[int]$Port){
    # Apache is deliberately controlled only by this disposable test harness, not by SOKNA Setup.
    $p=Start-Process -FilePath $Apache.exe -ArgumentList @('-f',$Apache.config) -PassThru -WindowStyle Hidden
    Wait-Tcp $Port 30
    return $p
}
function Restart-Apache($Apache,[int]$Port){
    Invoke-SoknaProcess $Apache.exe @('-k','restart','-f',$Apache.config) -TimeoutSeconds 60 | Out-Null
    Wait-Tcp $Port 30
}
function Stop-Apache($Apache){
    if($null -eq $Apache){return}
    try{Invoke-SoknaProcess $Apache.exe @('-k','shutdown','-f',$Apache.config) -SuccessCodes @(0,1) -TimeoutSeconds 30 | Out-Null}catch{}
    Start-Sleep -Milliseconds 500
    Get-Process httpd -ErrorAction SilentlyContinue | Where-Object { $_.Path -and $_.Path.StartsWith($Apache.root,[StringComparison]::OrdinalIgnoreCase) } | Stop-Process -Force -ErrorAction SilentlyContinue
}
function Invoke-Maria([string]$Client,[int]$Port,[string]$Password,[string]$Sql){
    # Password is disposable CI-only data. Never use this helper with production credentials.
    Invoke-SoknaProcess $Client @('-h127.0.0.1',"-P$Port",'-uroot',"-p$Password",'--batch','--skip-column-names','-e',$Sql) -TimeoutSeconds 60
}
function Read-InstallId([string]$DataRoot){
    $path=Join-Path $DataRoot 'identity\installation.json'
    Assert (Test-Path -LiteralPath $path -PathType Leaf) 'Installation identity metadata is missing.'
    return [string]((Get-Content -LiteralPath $path -Raw|ConvertFrom-Json).installation_id)
}

$version=(Get-Content (Join-Path $RepoRoot 'VERSION.txt') -Raw).Trim()
& (Join-Path $RepoRoot 'installer\windows\scripts\verify-prerequisite-bundle.ps1') -BundleRoot $PrerequisiteBundleRoot -ExpectedAppVersion $version
# The verifier is a throwing PowerShell script, not a native exit-code producer.
$manifest=Get-Content (Join-Path $PrerequisiteBundleRoot 'bundle-manifest.json') -Raw|ConvertFrom-Json
$phpZip=Find-Artifact $manifest 'php'
$apacheZip=Find-Artifact $manifest 'apache'
$mariaMsi=Find-Artifact $manifest 'mariadb'
$vcExe=Find-Artifact $manifest 'vc_runtime'

$root=Join-Path $env:RUNNER_TEMP ('sokna-rc-full-stack-'+[guid]::NewGuid().ToString('N'))
New-SoknaPrivateDirectory $root|Out-Null
$phpRoot=Join-Path $root 'php'
$apacheStage=Join-Path $root 'apache'
$mariaInstall=Join-Path $root 'MariaDB'
$mariaData=Join-Path $root 'MariaDB-data'
$private=Join-Path $root 'private'
$appNew=Join-Path $root 'app-new'
$dataNew=Join-Path $root 'data-new'
$appRecover=Join-Path $root 'app-recover'
$dataRecover=Join-Path $root 'data-recover'
$recoverySet=Join-Path $root 'recovery-set.tar.gz'
$dbPort=Get-FreePort
$httpPort=Get-FreePort
$httpsPort=443
$mariaService='SoknaCiMariaDB'+[guid]::NewGuid().ToString('N').Substring(0,8)
$dbPassword='SoknaCiRoot-'+[guid]::NewGuid().ToString('N')
$adminPassword='SoknaCiAdmin-'+[guid]::NewGuid().ToString('N')
Add-SoknaSecret $dbPassword
Add-SoknaSecret $adminPassword
$dbNew='sokna_rc_new_'+[guid]::NewGuid().ToString('N').Substring(0,8)
$dbRecover='sokna_rc_recover_'+[guid]::NewGuid().ToString('N').Substring(0,8)
$apache=$null
$mariaInstalled=$false

try{
    Assert (-not(Get-Service SoknaRuntime -ErrorAction SilentlyContinue)) 'Disposable runner already has SoknaRuntime; refusing destructive RC acceptance.'
    Assert (-not(Get-Service SoknaPrintWorker -ErrorAction SilentlyContinue)) 'Disposable runner already has SoknaPrintWorker; refusing destructive RC acceptance.'
    Assert (@(Get-NetTCPConnection -State Listen -LocalPort $httpsPort -ErrorAction SilentlyContinue).Count -eq 0) 'Disposable runner already uses TCP/443; RC acceptance requires the canonical HTTPS port.'

    $vc=Start-Process -FilePath $vcExe -ArgumentList @('/install','/quiet','/norestart') -Wait -PassThru
    if(@(0,1638,3010) -notcontains [int]$vc.ExitCode){throw "Frozen VC runtime installer failed: $($vc.ExitCode)"}
    if([int]$vc.ExitCode -eq 3010){throw 'Frozen VC runtime requested reboot; RC acceptance must be rerun on a fresh/rebooted Windows runner.'}

    $phpExe=Ensure-PHP $phpZip $phpRoot
    $apache=Ensure-Apache $apacheZip $apacheStage $phpRoot $httpPort $httpsPort

    $msiLog=Join-Path $root 'mariadb-install.log'
    $mariaArgs=@('/i',$mariaMsi,'/qn','/norestart','/l*v',$msiLog,("INSTALLDIR=$mariaInstall"),("DATADIR=$mariaData"),("PORT=$dbPort"),("PASSWORD=$dbPassword"),("SERVICENAME=$mariaService"),'STDCONFIG=1','ADDLOCAL=DBInstance,Client,MYSQLSERVER,SharedLibraries')
    $maria=Invoke-SoknaProcess -File 'msiexec.exe' -Arguments $mariaArgs -SuccessCodes @(0,3010) -TimeoutSeconds 600
    $mariaInstalled=$true
    if([int]$maria.ExitCode -eq 3010){throw 'Frozen MariaDB MSI requested reboot; RC acceptance must be rerun on a fresh/rebooted Windows runner.'}
    Wait-SoknaService $mariaService 'Running'
    $mariaClient=Join-Path $mariaInstall 'bin\mariadb.exe'
    Assert (Test-Path -LiteralPath $mariaClient -PathType Leaf) 'MariaDB client was not installed from the frozen MSI.'
    Wait-Tcp $dbPort 40
    Invoke-Maria $mariaClient $dbPort $dbPassword "CREATE DATABASE ``$dbNew`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE ``$dbRecover`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" | Out-Null

    $newConfig=Set-PrivateJson $private 'new.json' ([ordered]@{
        db=[ordered]@{host='127.0.0.1';port=[string]$dbPort;name=$dbNew;user='root';pass=$dbPassword};
        app_url='https://sokna.local';cafe_name='SOKNA RC Acceptance';admin_user='admin';admin_password=$adminPassword;table_count=4;
        data_dir=$dataNew;relay=[ordered]@{enabled=$false}
    })
    $deploy=Join-Path $ShellPayloadRoot 'deploy-seed.ps1'
    Assert (Test-Path -LiteralPath $deploy -PathType Leaf) 'Verified shell payload is missing deploy-seed.ps1.'
    $common=@('-ShellRoot',$ShellPayloadRoot,'-AppRoot',$appNew,'-DataRoot',$dataNew,'-PhpExe',$phpExe,'-OpenSslExe',$apache.openssl,'-WebServerExe',$apache.exe,'-Hostname','sokna.local','-RequireWebServerPreflight')
    $newRun=Invoke-ScriptExit $deploy (@('-Mode','New','-SetupConfigFile',$newConfig)+$common) @(20)
    Assert ($newRun.code -eq 20) 'New deployment did not stop at the expected external Apache reload boundary.'
    Assert (Test-Path -LiteralPath (Join-Path $appNew 'config.php')) 'New deployment did not commit canonical business configuration.'
    Assert (Test-Path -LiteralPath (Join-Path $appNew 'install.lock')) 'New deployment did not write install.lock.'

    $apacheProcess=Start-Apache $apache $httpsPort
    $repair=Join-Path $appNew 'runtime\windows\setup-sokna.ps1'
    $repairRun=Invoke-ScriptExit $repair @('-Mode','Repair','-AppRoot',$appNew,'-DataRoot',$dataNew,'-PhpExe',$phpExe,'-OpenSslExe',$apache.openssl,'-WebServerExe',$apache.exe,'-ServiceHostExe',(Join-Path $ShellPayloadRoot 'SoknaRuntimeService.exe'),'-PrintWorkerBundle',(Join-Path $ShellPayloadRoot 'print-worker'),'-Hostname','sokna.local','-RequireWebServerPreflight') @(0)
    Assert ($repairRun.code -eq 0) 'Repair did not close New-install HTTPS health after external Apache start.'
    $newIdentity=Read-InstallId $dataNew

    $backup=Invoke-SoknaProcess $phpExe @((Join-Path $appNew 'tools\backup-worker.php'),'--if-stale-hours=1') -TimeoutSeconds 600
    $backupPath=(Get-ChildItem (Join-Path $appNew 'storage\backups') -Filter 'sokna-backup-*.tar.gz' -File | Sort-Object LastWriteTimeUtc -Descending | Select-Object -First 1).FullName
    Assert (-not [string]::IsNullOrWhiteSpace($backupPath)) 'New installation did not produce a machine Recovery Set.'
    Copy-Item -LiteralPath $backupPath -Destination $recoverySet -Force

    Stop-And-Delete-Service 'SoknaRuntime'
    Stop-And-Delete-Service 'SoknaPrintWorker'
    Remove-Item 'HKLM:\SOFTWARE\Sokna\Local\PrintWorker' -Recurse -Force -ErrorAction SilentlyContinue
    Remove-Item -LiteralPath $appNew -Recurse -Force
    Remove-Item -LiteralPath $dataNew -Recurse -Force

    $recoverConfig=Set-PrivateJson $private 'recover.json' ([ordered]@{
        db=[ordered]@{host='127.0.0.1';port=[string]$dbPort;name=$dbRecover;user='root';pass=$dbPassword};
        app_url='https://sokna.local';data_dir=$dataRecover;relay=[ordered]@{enabled=$false}
    })
    $recoverRun=Invoke-ScriptExit $deploy (@('-Mode','Recover','-SetupConfigFile',$recoverConfig,'-RecoveryFile',$recoverySet)+@('-ShellRoot',$ShellPayloadRoot,'-AppRoot',$appRecover,'-DataRoot',$dataRecover,'-PhpExe',$phpExe,'-OpenSslExe',$apache.openssl,'-WebServerExe',$apache.exe,'-Hostname','sokna.local','-RequireWebServerPreflight')) @(20)
    Assert ($recoverRun.code -eq 20) 'Recover deployment did not stop at the expected external Apache reload boundary.'
    Restart-Apache $apache $httpsPort
    $recoverRepair=Invoke-ScriptExit (Join-Path $appRecover 'runtime\windows\setup-sokna.ps1') @('-Mode','Repair','-AppRoot',$appRecover,'-DataRoot',$dataRecover,'-PhpExe',$phpExe,'-OpenSslExe',$apache.openssl,'-WebServerExe',$apache.exe,'-ServiceHostExe',(Join-Path $ShellPayloadRoot 'SoknaRuntimeService.exe'),'-PrintWorkerBundle',(Join-Path $ShellPayloadRoot 'print-worker'),'-Hostname','sokna.local','-RequireWebServerPreflight') @(0)
    Assert ($recoverRepair.code -eq 0) 'Repair did not close recovered-machine HTTPS health after external Apache restart.'
    $recoveredIdentity=Read-InstallId $dataRecover
    Assert ($recoveredIdentity -ne $newIdentity) 'Machine Recovery cloned the source installation identity.'

    $cafe=(Invoke-Maria $mariaClient $dbPort $dbPassword "SELECT setting_value FROM ``$dbRecover``.settings WHERE setting_key='cafe_name' LIMIT 1;").Output.Trim()
    Assert ($cafe -eq 'SOKNA RC Acceptance') 'Machine Recovery did not restore canonical business data.'
    $tables=[int]((Invoke-Maria $mariaClient $dbPort $dbPassword "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$dbRecover';").Output.Trim())
    Assert ($tables -gt 50) 'Machine Recovery did not restore the full SOKNA schema.'

    Write-Host "Phase 8B Windows RC full-stack PASS: frozen providers -> New -> Apache reload -> Repair/HTTPS -> Recovery Set -> Recover -> Repair/HTTPS ($version)."
}
finally{
    Stop-And-Delete-Service 'SoknaRuntime'
    Stop-And-Delete-Service 'SoknaPrintWorker'
    Remove-Item 'HKLM:\SOFTWARE\Sokna\Local\PrintWorker' -Recurse -Force -ErrorAction SilentlyContinue
    Stop-Apache $apache
    if($mariaInstalled){
        try{Stop-Service -Name $mariaService -Force -ErrorAction SilentlyContinue}catch{}
        try{
            $u=Invoke-SoknaProcess -File 'msiexec.exe' -Arguments @('/i',$mariaMsi,'REMOVE=ALL','CLEANUPDATA=1','/qn','/norestart') -SuccessCodes @(0,1605,1614,3010) -TimeoutSeconds 600
            if([int]$u.ExitCode -eq 3010){Write-Warning 'MariaDB cleanup requested reboot on the disposable runner.'}
        }catch{Write-Warning ('MariaDB cleanup failed: '+$_.Exception.Message)}
    }
    try{Stop-And-Delete-Service $mariaService}catch{}
    Remove-Item -LiteralPath $root -Recurse -Force -ErrorAction SilentlyContinue
}

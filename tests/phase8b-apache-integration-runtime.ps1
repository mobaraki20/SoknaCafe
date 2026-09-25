$ErrorActionPreference='Stop'
$repo=Split-Path -Parent $PSScriptRoot
$root=Join-Path $env:TEMP ('SOKNA-apache-runtime-'+[guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $root -Force|Out-Null
function Assert([bool]$ok,[string]$message){if(-not $ok){throw $message}}
try{
    $apacheRoot=Join-Path $root 'Apache24'
    $confDir=Join-Path $apacheRoot 'conf'
    New-Item -ItemType Directory -Path $confDir -Force|Out-Null
    $main=Join-Path $confDir 'httpd.conf'
    [IO.File]::WriteAllText($main,"ServerRoot `"$($apacheRoot.Replace('\','/'))`"`r`n# external Apache fixture`r`n")

    $fakeSource=Join-Path $root 'FakeApache.cs'
    $fakeExe=Join-Path $root 'httpd.exe'
    @'
using System;
using System.IO;
using System.Text.RegularExpressions;
public static class FakeApache {
  public static int Main(string[] args) {
    var root=Environment.GetEnvironmentVariable("FAKE_APACHE_ROOT") ?? "";
    if(Array.IndexOf(args,"-V")>=0) {
      Console.WriteLine("Server version: Apache/2.4.62 (Win64)");
      Console.WriteLine(" -D HTTPD_ROOT=\""+root.Replace('\\','/')+"\"");
      Console.WriteLine(" -D SERVER_CONFIG_FILE=\"conf/httpd.conf\"");
      return 0;
    }
    if(Array.IndexOf(args,"-M")>=0) {
      Console.WriteLine(" ssl_module (shared)");
      Console.WriteLine(" headers_module (shared)");
      Console.WriteLine(" authz_core_module (shared)");
      return 0;
    }
    if(Array.IndexOf(args,"-v")>=0) { Console.WriteLine("Apache/2.4.62 (Win64)"); return 0; }
    if(Array.IndexOf(args,"-t")>=0) {
      var i=Array.IndexOf(args,"-f");
      if(i<0 || i+1>=args.Length || !File.Exists(args[i+1])) return 2;
      var cfg=Path.GetFullPath(args[i+1]);
      var real=Path.GetFullPath(Path.Combine(root,"conf","httpd.conf"));
      if(Environment.GetEnvironmentVariable("FAKE_APACHE_FAIL_REAL") == "1" && String.Equals(cfg,real,StringComparison.OrdinalIgnoreCase)) return 3;
      var text=File.ReadAllText(cfg);
      var m=Regex.Match(text,"(?m)^Include \\\"([^\\\"]+)\\\"$");
      if(m.Success && !File.Exists(m.Groups[1].Value.Replace('/','\\'))) return 4;
      Console.WriteLine("Syntax OK");
      return 0;
    }
    return 0;
  }
}
'@ | Set-Content -LiteralPath $fakeSource -Encoding UTF8
    $csc="$env:WINDIR\Microsoft.NET\Framework64\v4.0.30319\csc.exe"
    & $csc /nologo /target:exe "/out:$fakeExe" $fakeSource
    if($LASTEXITCODE -ne 0){throw 'Fake Apache fixture did not compile.'}
    $env:FAKE_APACHE_ROOT=$apacheRoot

    $app=Join-Path $root 'live app فارسی'
    $data=Join-Path $root 'business data'
    New-Item -ItemType Directory -Path $app -Force|Out-Null
    New-Item -ItemType Directory -Path $data -Force|Out-Null
    $script=Join-Path $repo 'runtime\windows\configure-apache.ps1'
    $template=Join-Path $repo 'runtime\windows\apache\sokna-local-https.conf.template'

    # Validate-only before TLS exists must discover/config-check but must not mutate Apache.
    $before=(Get-FileHash $main -Algorithm SHA256).Hash
    $probe=& $script -WebServerExe $fakeExe -AppRoot $app -DataRoot $data -Hostname 'sokna.local' -TemplatePath $template -ValidateOnly | ConvertFrom-Json
    Assert ($probe.validate_only -and -not $probe.tls_ready) 'Validate-only did not report TLS-not-ready state.'
    Assert ((Get-FileHash $main -Algorithm SHA256).Hash -eq $before) 'Validate-only changed shared Apache config.'
    Assert (-not(Test-Path (Join-Path $confDir 'sokna-local.conf'))) 'Validate-only created managed Apache include.'

    $tls=Join-Path $data 'secrets\tls'
    New-Item -ItemType Directory -Path $tls -Force|Out-Null
    [IO.File]::WriteAllText((Join-Path $tls 'server.crt.pem'),'fixture cert')
    [IO.File]::WriteAllText((Join-Path $tls 'server.key.pem'),'fixture key')

    $result=& $script -WebServerExe $fakeExe -AppRoot $app -DataRoot $data -Hostname 'sokna.local' -TemplatePath $template | ConvertFrom-Json
    Assert ($result.changed -and $result.reload_required) 'Apply did not report managed config change/reload requirement.'
    Assert ($result.lifecycle_owner -eq 'external') 'Apache lifecycle ownership changed unexpectedly.'
    $include=Join-Path $confDir 'sokna-local.conf'
    Assert (Test-Path $include) 'Managed Apache include was not created.'
    $mainText=Get-Content $main -Raw
    $includeText=Get-Content $include -Raw
    Assert (([regex]::Matches($mainText,'# BEGIN SOKNA LOCAL MANAGED INCLUDE v1')).Count -eq 1) 'Managed block is missing or duplicated.'
    Assert ($includeText -match [regex]::Escape($app.Replace('\','/'))) 'Managed vhost does not point to selected AppRoot/WebRoot.'
    Assert ($includeText -match [regex]::Escape($data.Replace('\','/'))) 'Managed vhost does not deny selected DataRoot.'
    Assert ($includeText -notmatch '(?m)^\s*Listen\s+') 'Managed vhost took ownership of Apache listener lifecycle.'

    # Reapply is idempotent.
    $mainHash=(Get-FileHash $main -Algorithm SHA256).Hash
    $includeHash=(Get-FileHash $include -Algorithm SHA256).Hash
    $repeat=& $script -WebServerExe $fakeExe -AppRoot $app -DataRoot $data -Hostname 'sokna.local' -TemplatePath $template | ConvertFrom-Json
    Assert (-not $repeat.changed -and -not $repeat.reload_required) 'Repeat apply did not converge to a no-change/no-reload result.'
    Assert ((Get-FileHash $main -Algorithm SHA256).Hash -eq $mainHash) 'Repeat apply changed Apache main config bytes.'
    Assert ((Get-FileHash $include -Algorithm SHA256).Hash -eq $includeHash) 'Repeat apply changed managed include bytes.'

    # Failure after shared-file mutation must restore both files byte-for-byte.
    $env:FAKE_APACHE_FAIL_REAL='1'
    $failed=$false
    try{& $script -WebServerExe $fakeExe -AppRoot $app -DataRoot $data -Hostname 'changed.local' -TemplatePath $template | Out-Null}catch{$failed=$true}
    Remove-Item Env:FAKE_APACHE_FAIL_REAL -ErrorAction SilentlyContinue
    Assert $failed 'Injected Apache validation failure did not fail closed.'
    Assert ((Get-FileHash $main -Algorithm SHA256).Hash -eq $mainHash) 'Apache rollback did not restore main config.'
    Assert ((Get-FileHash $include -Algorithm SHA256).Hash -eq $includeHash) 'Apache rollback did not restore managed include.'

    # Web/data overlap is forbidden even during validate-only.
    $overlapFailed=$false
    try{& $script -WebServerExe $fakeExe -AppRoot $app -DataRoot (Join-Path $app 'data') -Hostname 'sokna.local' -TemplatePath $template -ValidateOnly | Out-Null}catch{$overlapFailed=$true}
    Assert $overlapFailed 'Overlapping WebRoot/DataRoot was accepted.'

    Write-Host 'Phase 8B Apache integration runtime PASS: no-mutation validate, managed include, idempotency, rollback and root separation.'
}finally{
    Remove-Item Env:FAKE_APACHE_ROOT -ErrorAction SilentlyContinue
    Remove-Item Env:FAKE_APACHE_FAIL_REAL -ErrorAction SilentlyContinue
    Remove-Item -LiteralPath $root -Recurse -Force -ErrorAction SilentlyContinue
}

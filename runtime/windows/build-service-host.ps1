param([Parameter(Mandatory=$true)][string]$OutputPath)
$ErrorActionPreference = 'Stop'
Import-Module (Join-Path $PSScriptRoot 'setup-support.psm1') -DisableNameChecking -Force
$csc = @("$env:WINDIR\Microsoft.NET\Framework64\v4.0.30319\csc.exe", "$env:WINDIR\Microsoft.NET\Framework\v4.0.30319\csc.exe") | Where-Object { Test-Path -LiteralPath $_ } | Select-Object -First 1
if (-not $csc) { throw '.NET Framework C# compiler was not found on the build machine.' }
[IO.Directory]::CreateDirectory((Split-Path -Parent ([IO.Path]::GetFullPath($OutputPath)))) | Out-Null
Invoke-SoknaProcess -File $csc -Arguments @('/nologo','/target:exe','/reference:System.ServiceProcess.dll',"/out:$OutputPath",(Join-Path $PSScriptRoot 'SoknaRuntimeService.cs')) | Out-Null
if (-not (Test-Path -LiteralPath $OutputPath -PathType Leaf)) { throw 'Runtime Service Host build failed.' }

param(
    [Parameter(Mandatory=$true)][ValidateSet('Validate','Repair','RemovePlatform')][string]$Operation,
    [string]$AppRoot='', [string]$DataRoot='', [string]$PhpExe='', [string]$OpenSslExe='', [string]$Hostname='',
    [string]$InstallerLogFile='',
    [Parameter(Mandatory=$true)][string]$ResultFile
)
$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding = New-Object Text.UTF8Encoding($false)
Import-Module (Join-Path $PSScriptRoot 'setup-support.psm1') -DisableNameChecking -Force
$resultCode = 2
try {
    if (-not [Environment]::Is64BitProcess) { throw 'Platform maintenance requires 64-bit PowerShell; run the x64 installer.' }
    if ($Operation -ne 'Validate') {
        # Installer writes machine-wide registration in an administrator-owned key.
        # Never load an elevated execution plan from a user-writable config file.
        $target = Get-ItemProperty 'HKLM:\SOFTWARE\SOKNA\PlatformPreview'
        $AppRoot=$target.AppRoot; $DataRoot=$target.DataRoot; $PhpExe=$target.PhpExe
        $OpenSslExe=$target.OpenSslExe; $Hostname=$target.Hostname
    }
    foreach ($path in @($AppRoot,$DataRoot,$PhpExe,$OpenSslExe)) {
        if (-not $path -or -not [IO.Path]::IsPathRooted($path)) { throw 'Select absolute paths for the existing application and prerequisites.' }
        Assert-SoknaSafePath $path
    }
    if ($Hostname -notmatch '^(?=.{1,253}$)[a-z0-9]+(?:[.-][a-z0-9]+)*$') { throw 'Invalid local hostname.' }
    $childArgs = @('-NoProfile','-NonInteractive','-ExecutionPolicy','Bypass','-File',(Join-Path $PSScriptRoot 'setup-sokna.ps1'),
        '-Mode',$Operation,'-AppRoot',$AppRoot,'-DataRoot',$DataRoot,'-PhpExe',$PhpExe,'-OpenSslExe',$OpenSslExe,
        '-Hostname',$Hostname,'-ServiceHostExe',(Join-Path $PSScriptRoot 'bin\SoknaRuntimeService.exe'))
    if ($InstallerLogFile) { $childArgs += @('-InstallerLogFile',$InstallerLogFile) }
    if ($Operation -eq 'Validate') { $childArgs += '-ValidateRepair' }
    $ps = "$env:SystemRoot\System32\WindowsPowerShell\v1.0\powershell.exe"
    $child = Invoke-SoknaProcess $ps $childArgs -SuccessCodes @(0,2,3010) -TimeoutSeconds 300
    $summary = $child.Output | ConvertFrom-Json
    $resultCode = $child.ExitCode
    if ($resultCode -in @(0,3010) -and -not $summary.ok) { throw 'Setup owner returned an inconsistent result.' }
    # Result contains only the owner's sanitized summary, never raw child stderr.
    [IO.File]::WriteAllText($ResultFile, ($summary | ConvertTo-Json -Depth 8), (New-Object Text.UTF8Encoding($true)))
} catch {
    $resultCode = 2
    $message = Protect-SoknaLog $_.Exception.Message
    [IO.File]::WriteAllText($ResultFile, $message, (New-Object Text.UTF8Encoding($true)))
}
exit $resultCode

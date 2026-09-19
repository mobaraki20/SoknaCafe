# Shared Windows setup mechanics; no business/backup state lives here.
Set-StrictMode -Version 2
$ErrorActionPreference = 'Stop'
$script:Redactions = @()

function Assert-SoknaSafePath([string]$Path) {
    $cursor = [IO.Path]::GetFullPath($Path)
    while ($cursor) {
        if (Test-Path -LiteralPath $cursor) {
            if ((Get-Item -Force -LiteralPath $cursor).Attributes -band [IO.FileAttributes]::ReparsePoint) {
                throw 'Setup refuses a reparse-point path.'
            }
        }
        $parent = [IO.Directory]::GetParent($cursor)
        if ($null -eq $parent) { break }
        $cursor = $parent.FullName
    }
}

function New-SoknaPrivateDirectory([string]$Path) {
    Assert-SoknaSafePath $Path
    [IO.Directory]::CreateDirectory($Path) | Out-Null
    $acl = New-Object Security.AccessControl.DirectorySecurity
    $acl.SetAccessRuleProtection($true, $false)
    $sids = @('S-1-5-18', 'S-1-5-32-544', [Security.Principal.WindowsIdentity]::GetCurrent().User.Value) | Select-Object -Unique
    foreach ($sid in $sids) {
        $identity = New-Object Security.Principal.SecurityIdentifier($sid)
        $rule = New-Object Security.AccessControl.FileSystemAccessRule($identity, 'FullControl', 'ContainerInherit,ObjectInherit', 'None', 'Allow')
        $acl.AddAccessRule($rule)
    }
    Set-Acl -LiteralPath $Path -AclObject $acl
    return $Path
}

function Add-SoknaSecret([string]$Value) {
    if ($Value) { $script:Redactions += $Value }
}

function Protect-SoknaLog([string]$Text) {
    foreach ($value in ($script:Redactions | Sort-Object Length -Descending | Select-Object -Unique)) {
        $Text = $Text.Replace($value, '[REDACTED]')
    }
    $Text = [regex]::Replace($Text, '(?i)(password|passphrase|shared_secret|token|authorization|app[._]key)(\s*[=:]\s*)[^\s,;]+', '$1$2[REDACTED]')
    return $Text
}

function ConvertTo-SoknaArgument([string]$Value) {
    # Windows CommandLineToArgvW/C runtime quoting, including trailing backslashes.
    '"' + [regex]::Replace([regex]::Replace($Value, '(\\*)"', '$1$1\"'), '(\\+)$', '$1$1') + '"'
}

function Invoke-SoknaProcess {
    param([string]$File, [string[]]$Arguments, [int[]]$SuccessCodes = @(0), [int]$TimeoutSeconds = 120)
    $info = New-Object Diagnostics.ProcessStartInfo
    $info.FileName = $File
    $info.Arguments = ($Arguments | ForEach-Object { ConvertTo-SoknaArgument $_ }) -join ' '
    $info.UseShellExecute = $false
    $info.CreateNoWindow = $true
    $info.RedirectStandardOutput = $true
    $info.RedirectStandardError = $true
    $info.StandardOutputEncoding = New-Object Text.UTF8Encoding($false)
    $info.StandardErrorEncoding = New-Object Text.UTF8Encoding($false)
    $process = New-Object Diagnostics.Process
    $process.StartInfo = $info
    try {
        if (-not $process.Start()) { throw 'Child process could not start.' }
        $stdout = $process.StandardOutput.ReadToEndAsync()
        $stderr = $process.StandardError.ReadToEndAsync()
        if (-not $process.WaitForExit($TimeoutSeconds * 1000)) {
            # Do not allow a timed-out restore to continue silently in the background.
            & "$env:SystemRoot\System32\taskkill.exe" /PID $process.Id /T /F 2>&1 | Out-Null
            $process.WaitForExit(10000) | Out-Null
            throw 'Child process timed out; inspect the setup session before retrying.'
        }
        $result = [pscustomobject]@{ ExitCode = $process.ExitCode; Output = $stdout.Result; Error = $stderr.Result }
        if ($SuccessCodes -notcontains $result.ExitCode) {
            $detail = Protect-SoknaLog ($result.Error + ' ' + $result.Output)
            throw "Child exit code $($result.ExitCode): $detail"
        }
        return $result
    } finally { $process.Dispose() }
}

function Wait-SoknaService([string]$Name, [string]$Status) {
    $service = New-Object ServiceProcess.ServiceController($Name)
    try { $service.WaitForStatus([ServiceProcess.ServiceControllerStatus]::$Status, [TimeSpan]::FromSeconds(30)) }
    finally { $service.Dispose() }
}

Export-ModuleMember -Function *-Sokna*

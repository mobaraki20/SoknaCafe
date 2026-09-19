param(
    [Parameter(Mandatory=$true)][string]$OpenSslExe,
    [string]$DataRoot = "$env:ProgramData\SOKNA",
    [string]$Hostname = 'sokna.local'
)
$ErrorActionPreference = 'Stop'
Import-Module (Join-Path $PSScriptRoot 'setup-support.psm1')
if (-not ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) { throw 'Administrator privileges are required.' }
if (-not (Test-Path -LiteralPath $OpenSslExe -PathType Leaf)) { throw 'OpenSSL executable was not found.' }
if ($Hostname -notmatch '^(?=.{1,253}$)[a-z0-9]+(?:[.-][a-z0-9]+)*$') { throw 'Invalid local hostname.' }
$Secrets = Join-Path $DataRoot 'secrets\tls'
Assert-SoknaSafePath $Secrets
$CaKey = Join-Path $Secrets 'local-ca.key.pem'
$CaCert = Join-Path $Secrets 'local-ca.crt.pem'
$Key = Join-Path $Secrets 'server.key.pem'
$Cert = Join-Path $Secrets 'server.crt.pem'
$required = @($CaKey,$CaCert,$Key,$Cert)
$present = @($required | Where-Object { Test-Path -LiteralPath $_ -PathType Leaf }).Count
if ($present -gt 0 -and $present -ne $required.Count) { throw 'Partial TLS identity: preserve existing files and recover them explicitly. No key was regenerated.' }
$Hosts = "$env:SystemRoot\System32\drivers\etc\hosts"
$Lines = @(Get-Content -LiteralPath $Hosts)
foreach ($line in $Lines) {
    $mapping = ($line -split '#',2)[0].Trim() -split '\s+'
    if ($mapping.Count -gt 1 -and $mapping[1..($mapping.Count-1)] -contains $Hostname -and $mapping[0] -ne '127.0.0.1') {
        throw 'Local hostname has a conflicting hosts mapping; no hosts entry was removed.'
    }
}

function Assert-TlsIdentity([string]$Directory) {
    $ca = Join-Path $Directory 'local-ca.crt.pem'
    $caPrivate = Join-Path $Directory 'local-ca.key.pem'
    $server = Join-Path $Directory 'server.crt.pem'
    $serverPrivate = Join-Path $Directory 'server.key.pem'
    Invoke-SoknaProcess $OpenSslExe @('verify','-CAfile',$ca,'-verify_hostname',$Hostname,$server) | Out-Null
    foreach ($pair in @(@($ca,$caPrivate),@($server,$serverPrivate))) {
        Invoke-SoknaProcess $OpenSslExe @('x509','-in',$pair[0],'-checkend','0','-noout') | Out-Null
        $public = (Invoke-SoknaProcess $OpenSslExe @('x509','-in',$pair[0],'-pubkey','-noout')).Output.Trim()
        $privatePublic = (Invoke-SoknaProcess $OpenSslExe @('pkey','-in',$pair[1],'-pubout')).Output.Trim()
        if ($public -ne $privatePublic) { throw 'TLS certificate/private key mismatch. Existing identity was preserved.' }
    }
}

if ($present -eq $required.Count) {
    Assert-TlsIdentity $Secrets
    New-SoknaPrivateDirectory $Secrets | Out-Null
} else {
    # Build a complete validated identity in a private sibling; never overwrite old keys.
    $parent = New-SoknaPrivateDirectory (Join-Path $DataRoot 'secrets')
    $staging = New-SoknaPrivateDirectory (Join-Path $parent ('tls-staging-' + [guid]::NewGuid().ToString('N')))
    try {
        $Cfg = Join-Path $staging 'openssl-sokna.cnf'
@"
[req]
distinguished_name=dn
prompt=no
req_extensions=v3_req
[dn]
CN=$Hostname
[v3_req]
subjectAltName=@alt_names
extendedKeyUsage=serverAuth
keyUsage=digitalSignature,keyEncipherment
[alt_names]
DNS.1=$Hostname
DNS.2=localhost
IP.1=127.0.0.1
IP.2=::1
"@ | Set-Content -LiteralPath $Cfg -Encoding ascii
        $stagedCaKey = Join-Path $staging 'local-ca.key.pem'
        $stagedCa = Join-Path $staging 'local-ca.crt.pem'
        $stagedKey = Join-Path $staging 'server.key.pem'
        $csr = Join-Path $staging 'server.csr.pem'
        $stagedCert = Join-Path $staging 'server.crt.pem'
        Invoke-SoknaProcess $OpenSslExe @('genrsa','-out',$stagedCaKey,'3072') | Out-Null
        Invoke-SoknaProcess $OpenSslExe @('req','-x509','-new','-key',$stagedCaKey,'-sha256','-days','3650','-subj','/CN=SOKNA Local CA','-out',$stagedCa) | Out-Null
        Invoke-SoknaProcess $OpenSslExe @('genrsa','-out',$stagedKey,'2048') | Out-Null
        Invoke-SoknaProcess $OpenSslExe @('req','-new','-key',$stagedKey,'-out',$csr,'-config',$Cfg) | Out-Null
        Invoke-SoknaProcess $OpenSslExe @('x509','-req','-in',$csr,'-CA',$stagedCa,'-CAkey',$stagedCaKey,'-CAcreateserial','-out',$stagedCert,'-days','825','-sha256','-extensions','v3_req','-extfile',$Cfg) | Out-Null
        Assert-TlsIdentity $staging
        if (Test-Path -LiteralPath $Secrets) {
            if (@(Get-ChildItem -LiteralPath $Secrets -Force).Count -gt 0) { throw 'TLS directory is not empty; explicit recovery is required.' }
            Remove-Item -LiteralPath $Secrets
        }
        Move-Item -LiteralPath $staging -Destination $Secrets
    } finally { if (Test-Path -LiteralPath $staging) { Remove-Item -LiteralPath $staging -Recurse -Force } }
}
Import-Certificate -FilePath $CaCert -CertStoreLocation 'Cert:\LocalMachine\Root' | Out-Null
$found = $false
foreach ($line in $Lines) {
    $mapping = ($line -split '#',2)[0].Trim() -split '\s+'
    if ($mapping.Count -gt 1 -and $mapping[0] -eq '127.0.0.1' -and $mapping[1..($mapping.Count-1)] -contains $Hostname) { $found = $true }
}
if (-not $found) { Add-Content -LiteralPath $Hosts -Value "`r`n127.0.0.1`t$Hostname`t# SOKNA managed" -Encoding ascii }
[pscustomobject]@{ hostname=$Hostname; certificate=$Cert; private_key=$Key; ca_certificate=$CaCert; data_root=$DataRoot; identity_reused=($present -eq 4) } | ConvertTo-Json -Depth 3

param(
    [Parameter(Mandatory=$true)][string]$OpenSslExe,
    [string]$DataRoot = "$env:ProgramData\SOKNA",
    [string]$Hostname = "sokna.local"
)
$ErrorActionPreference = 'Stop'
if (-not ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    throw 'Administrator privileges are required.'
}
if (-not (Test-Path -LiteralPath $OpenSslExe)) { throw 'OpenSSL executable was not found.' }
if ($Hostname -notmatch '^[a-z0-9.-]+$') { throw 'Invalid local hostname.' }

$Secrets = Join-Path $DataRoot 'secrets\tls'
New-Item -ItemType Directory -Force -Path $Secrets | Out-Null
$Acl = Get-Acl $Secrets
$Acl.SetAccessRuleProtection($true,$false)
$Admins = New-Object System.Security.AccessControl.FileSystemAccessRule('BUILTIN\Administrators','FullControl','ContainerInherit,ObjectInherit','None','Allow')
$System = New-Object System.Security.AccessControl.FileSystemAccessRule('NT AUTHORITY\SYSTEM','FullControl','ContainerInherit,ObjectInherit','None','Allow')
$Acl.SetAccessRule($Admins); $Acl.AddAccessRule($System); Set-Acl -Path $Secrets -AclObject $Acl

$Cfg = Join-Path $Secrets 'openssl-sokna.cnf'
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

$CaKey=Join-Path $Secrets 'local-ca.key.pem'; $CaCert=Join-Path $Secrets 'local-ca.crt.pem'
$Key=Join-Path $Secrets 'server.key.pem'; $Csr=Join-Path $Secrets 'server.csr.pem'; $Cert=Join-Path $Secrets 'server.crt.pem'
& $OpenSslExe genrsa -out $CaKey 3072 | Out-Null
& $OpenSslExe req -x509 -new -key $CaKey -sha256 -days 3650 -subj '/CN=SOKNA Local CA' -out $CaCert | Out-Null
& $OpenSslExe genrsa -out $Key 2048 | Out-Null
& $OpenSslExe req -new -key $Key -out $Csr -config $Cfg | Out-Null
& $OpenSslExe x509 -req -in $Csr -CA $CaCert -CAkey $CaKey -CAcreateserial -out $Cert -days 825 -sha256 -extensions v3_req -extfile $Cfg | Out-Null
if ($LASTEXITCODE -ne 0) { throw 'OpenSSL certificate generation failed.' }
Import-Certificate -FilePath $CaCert -CertStoreLocation 'Cert:\LocalMachine\Root' | Out-Null

$Hosts = "$env:SystemRoot\System32\drivers\etc\hosts"
$Lines = @(Get-Content -LiteralPath $Hosts -ErrorAction SilentlyContinue | Where-Object { $_ -notmatch "\s$([regex]::Escape($Hostname))(\s|$)" })
$Lines += "127.0.0.1`t$Hostname`t# SOKNA managed"
$Temp = "$Hosts.sokna.tmp"
$Lines | Set-Content -LiteralPath $Temp -Encoding ascii
Move-Item -Force -LiteralPath $Temp -Destination $Hosts

[pscustomobject]@{ hostname=$Hostname; certificate=$Cert; private_key=$Key; ca_certificate=$CaCert; data_root=$DataRoot } | ConvertTo-Json -Depth 3

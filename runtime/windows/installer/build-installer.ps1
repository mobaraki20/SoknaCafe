param([Parameter(Mandatory=$true)][string]$OutputDirectory)
$ErrorActionPreference = 'Stop'
$windows = Split-Path -Parent $PSScriptRoot
$repo = [IO.Path]::GetFullPath((Join-Path $windows '..\..'))
Import-Module (Join-Path $windows 'setup-support.psm1') -DisableNameChecking -Force
$tool = Get-Content (Join-Path $PSScriptRoot 'toolchain.json') -Raw | ConvertFrom-Json
$work = New-SoknaPrivateDirectory (Join-Path $env:TEMP ('sokna-build-' + [guid]::NewGuid().ToString('N')))
try {
    $download = Join-Path $work 'inno.exe'
    Invoke-WebRequest -Uri $tool.url -OutFile $download -UseBasicParsing
    if ((Get-FileHash $download -Algorithm SHA256).Hash -ne $tool.sha256) { throw 'Inno compiler download hash mismatch.' }
    $signature = Get-AuthenticodeSignature $download
    if ($signature.Status -ne 'Valid' -or $signature.SignerCertificate.GetNameInfo([Security.Cryptography.X509Certificates.X509NameType]::SimpleName,$false) -ne $tool.publisher) {
        throw 'Inno compiler publisher signature verification failed.'
    }
    $compilerDir = Join-Path $work 'compiler'
    Invoke-SoknaProcess $download @('/VERYSILENT','/SUPPRESSMSGBOXES','/NORESTART',"/DIR=$compilerDir") | Out-Null
    $compiler = Join-Path $compilerDir 'ISCC.exe'
    if (-not (Test-Path $compiler)) { throw 'Pinned compiler was not installed.' }
    $OutputDirectory = [IO.Path]::GetFullPath($OutputDirectory)
    [IO.Directory]::CreateDirectory($OutputDirectory) | Out-Null
    $hostFile = Join-Path $work 'SoknaRuntimeService.exe'
    & (Join-Path $windows 'build-service-host.ps1') -OutputPath $hostFile
    $worker = Join-Path $windows 'bin\print-worker'
    if (-not (Test-Path (Join-Path $worker 'component-manifest.json'))) { throw 'Build the internal Print Worker before packaging.' }
    $workerArchive = Join-Path $work 'print-worker.zip'
    Compress-Archive -Path (Join-Path $worker '*') -DestinationPath $workerArchive
    # ICO container embeds the existing brand PNGs unchanged; no new artwork.
    $icon = Join-Path $work 'sokna.ico'
    $pictures = @(32,192) | ForEach-Object { [pscustomobject]@{ Size=$_; Bytes=[IO.File]::ReadAllBytes((Join-Path $repo "assets/icons/favicon-$_.png")) } }
    $stream = [IO.File]::Create($icon)
    $writer = New-Object IO.BinaryWriter($stream)
    try {
        $writer.Write([uint16]0); $writer.Write([uint16]1); $writer.Write([uint16]$pictures.Count)
        $offset = 6 + 16 * $pictures.Count
        foreach ($picture in $pictures) {
            $writer.Write([byte]$picture.Size); $writer.Write([byte]$picture.Size)
            $writer.Write([byte]0); $writer.Write([byte]0); $writer.Write([uint16]1); $writer.Write([uint16]32)
            $writer.Write([uint32]$picture.Bytes.Length); $writer.Write([uint32]$offset)
            $offset += $picture.Bytes.Length
        }
        foreach ($picture in $pictures) { $writer.Write([byte[]]$picture.Bytes) }
    } finally { $writer.Dispose(); $stream.Dispose() }
    Invoke-SoknaProcess $compiler @("/DSourceRoot=$windows","/DHostFile=$hostFile","/DIconFile=$icon","/DWorkerArchive=$workerArchive","/O$OutputDirectory",(Join-Path $PSScriptRoot 'platform.iss')) -TimeoutSeconds 300 | Out-Null
    Copy-Item (Join-Path $compilerDir 'License.txt') (Join-Path $OutputDirectory 'INNO-LICENSE.txt')
    $source = (& git -C $repo rev-parse HEAD).Trim()
    if ($LASTEXITCODE -ne 0) { throw 'Cannot determine installer source commit.' }
    $artifact = Get-Item (Join-Path $OutputDirectory 'SOKNA-Platform-Preview-0.1.0-Setup.exe')
    [ordered]@{
        format='sokna-platform-preview-v1'; version='0.1.0'; application_version=(Get-Content (Join-Path $repo 'VERSION.txt') -Raw).Trim()
        source_commit=$source; compiler_version=$tool.version; compiler_sha256=$tool.sha256
        artifact=$artifact.Name; sha256=(Get-FileHash $artifact.FullName -Algorithm SHA256).Hash.ToLowerInvariant()
        signing='unsigned-test-only'; scope='existing configured application: platform install, repair, uninstall; not clean-machine setup'
    } | ConvertTo-Json | Set-Content (Join-Path $OutputDirectory 'artifact-manifest.json') -Encoding UTF8
} finally { Remove-Item -LiteralPath $work -Recurse -Force -ErrorAction SilentlyContinue }

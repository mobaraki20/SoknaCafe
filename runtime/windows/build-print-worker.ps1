param(
    [string]$Configuration = 'Release',
    [string]$Runtime = 'win-x64',
    [string]$OutputRoot = (Join-Path $PSScriptRoot 'bin\print-worker')
)
$ErrorActionPreference = 'Stop'
$source = (Resolve-Path (Join-Path $PSScriptRoot '..\print-worker\source')).Path
$dotnet = (Get-Command dotnet -ErrorAction Stop).Source
$sdk = (& $dotnet --version).Trim()
if (-not $sdk.StartsWith('10.')) { throw ".NET 10 SDK required for SOKNA Print Worker; found $sdk" }

[xml]$props = Get-Content (Join-Path $source 'Directory.Build.props') -Raw
$baselineVersion = [string]$props.Project.PropertyGroup.SoknaAgentVersion
if ($baselineVersion -ne '6.2.5') { throw "Unexpected internal Print Worker baseline: $baselineVersion" }

$solution = Join-Path $source 'Sokna.PrintWorker.slnx'
$staging = Join-Path ([IO.Path]::GetTempPath()) ('sokna-print-worker-build-' + [guid]::NewGuid().ToString('N'))
try {
    New-Item $staging -ItemType Directory -Force | Out-Null
    Push-Location $source
    try {
        & $dotnet restore $solution
        if ($LASTEXITCODE -ne 0) { throw 'Print Worker restore failed.' }
        & $dotnet build $solution -c $Configuration --no-restore
        if ($LASTEXITCODE -ne 0) { throw 'Print Worker build failed.' }
        foreach ($testProject in @(
            'tests\Sokna.PrintAgent.Tests\Sokna.PrintAgent.Tests.csproj',
            'tests\Sokna.PrintAgent.Worker.Tests\Sokna.PrintAgent.Worker.Tests.csproj'
        )) {
            & $dotnet run --project (Join-Path $source $testProject) -c $Configuration --no-build
            if ($LASTEXITCODE -ne 0) { throw "Print Worker test failed: $testProject" }
        }

        foreach ($component in @('Service','Worker')) {
            $projectName = "Sokna.PrintAgent.$component"
            $project = Join-Path $source "src\$projectName\$projectName.csproj"
            $destination = Join-Path $staging $component
            & $dotnet restore $project -r $Runtime
            if ($LASTEXITCODE -ne 0) { throw "Runtime restore failed: $projectName" }
            & $dotnet publish $project -c $Configuration -r $Runtime --self-contained true --no-restore -o $destination
            if ($LASTEXITCODE -ne 0) { throw "Publish failed: $projectName" }
            if (-not (Test-Path (Join-Path $destination "$projectName.exe") -PathType Leaf)) { throw "Published executable missing: $projectName" }
        }
    } finally { Pop-Location }

    $manifest = @()
    Get-ChildItem $staging -File -Recurse | Sort-Object FullName | ForEach-Object {
        $manifest += [ordered]@{
            path = [IO.Path]::GetRelativePath($staging,$_.FullName).Replace('\','/')
            size = $_.Length
            sha256 = (Get-FileHash $_.FullName -Algorithm SHA256).Hash.ToLowerInvariant()
        }
    }
    [ordered]@{
        format = 'sokna-internal-print-worker-v1'
        upstream_baseline = $baselineVersion
        runtime = $Runtime
        dotnet_sdk = $sdk
        built_at_utc = [DateTime]::UtcNow.ToString('o')
        files = $manifest
    } | ConvertTo-Json -Depth 8 | Set-Content (Join-Path $staging 'component-manifest.json') -Encoding utf8NoBOM

    Remove-Item $OutputRoot -Recurse -Force -ErrorAction SilentlyContinue
    New-Item (Split-Path -Parent $OutputRoot) -ItemType Directory -Force | Out-Null
    Move-Item $staging $OutputRoot
    $staging = ''
    Write-Host "SOKNA internal Print Worker built: $OutputRoot" -ForegroundColor Green
} finally {
    if ($staging -and (Test-Path $staging)) { Remove-Item $staging -Recurse -Force -ErrorAction SilentlyContinue }
}

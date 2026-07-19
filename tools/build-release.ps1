[CmdletBinding()]
param(
    [switch]$OpenOutputFolder,
    [switch]$SkipPhpValidation
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Write-Step {
    param([string]$Message)
    Write-Host ""
    Write-Host "==> $Message" -ForegroundColor Cyan
}

function Stop-Build {
    param([string]$Message)
    Write-Host ""
    Write-Host "BUILD FAILED: $Message" -ForegroundColor Red
    exit 1
}


function Find-PhpExecutable {
    # First use PHP when it is already available from Windows PATH.
    $pathCommand = Get-Command php.exe -ErrorAction SilentlyContinue
    if (-not $pathCommand) { $pathCommand = Get-Command php -ErrorAction SilentlyContinue }
    if ($pathCommand -and $pathCommand.Source) {
        return $pathCommand.Source
    }

    # Local normally stores its PHP services under one of these folders.
    $searchRoots = New-Object System.Collections.Generic.List[string]
    if ($env:APPDATA) {
        $searchRoots.Add((Join-Path $env:APPDATA 'Local\lightning-services'))
    }
    if ($env:LOCALAPPDATA) {
        $searchRoots.Add((Join-Path $env:LOCALAPPDATA 'Programs\Local\resources\extraResources\lightning-services'))
        $searchRoots.Add((Join-Path $env:LOCALAPPDATA 'Local\lightning-services'))
    }

    $localPhpCandidates = New-Object System.Collections.Generic.List[System.IO.FileInfo]
    foreach ($root in ($searchRoots | Select-Object -Unique)) {
        if (-not (Test-Path -LiteralPath $root -PathType Container)) { continue }
        Get-ChildItem -LiteralPath $root -Directory -Filter 'php-*' -ErrorAction SilentlyContinue | ForEach-Object {
            $win64Php = Join-Path $_.FullName 'bin\win64\php.exe'
            $binPhp = Join-Path $_.FullName 'bin\php.exe'
            if (Test-Path -LiteralPath $win64Php -PathType Leaf) {
                $localPhpCandidates.Add((Get-Item -LiteralPath $win64Php))
            }
            if (Test-Path -LiteralPath $binPhp -PathType Leaf) {
                $localPhpCandidates.Add((Get-Item -LiteralPath $binPhp))
            }
        }
    }

    if ($localPhpCandidates.Count -gt 0) {
        # Prefer the newest installed Local PHP service.
        return ($localPhpCandidates | Sort-Object LastWriteTimeUtc -Descending | Select-Object -First 1).FullName
    }

    # Also support common standalone Windows PHP installations.
    $directCandidates = @(
        'C:\xampp\php\php.exe',
        'C:\php\php.exe'
    )
    foreach ($candidate in $directCandidates) {
        if (Test-Path -LiteralPath $candidate -PathType Leaf) { return $candidate }
    }

    $recursiveRoots = @('C:\laragon\bin\php')
    foreach ($root in $recursiveRoots) {
        if (-not (Test-Path -LiteralPath $root -PathType Container)) { continue }
        $candidate = Get-ChildItem -LiteralPath $root -Recurse -File -Filter 'php.exe' -ErrorAction SilentlyContinue |
            Sort-Object LastWriteTimeUtc -Descending |
            Select-Object -First 1
        if ($candidate) { return $candidate.FullName }
    }

    return $null
}

function Get-RelativePath {
    param([string]$BasePath, [string]$TargetPath)
    $baseUri = New-Object System.Uri(($BasePath.TrimEnd('\') + '\'))
    $targetUri = New-Object System.Uri($TargetPath)
    [System.Uri]::UnescapeDataString($baseUri.MakeRelativeUri($targetUri).ToString().Replace('/', '\'))
}

try {
    $scriptDirectory = Split-Path -Parent $MyInvocation.MyCommand.Path
    $projectRoot = (Resolve-Path (Join-Path $scriptDirectory '..')).Path
    $versionFile = Join-Path $projectRoot 'BUILD_VERSION'
    $releaseDirectory = Join-Path $projectRoot 'releases'

    Write-Step 'Checking required build files'

    if (-not (Test-Path -LiteralPath $versionFile -PathType Leaf)) {
        Stop-Build "BUILD_VERSION was not found: $versionFile"
    }

    $version = (Get-Content -LiteralPath $versionFile -Raw).Trim()
    if ($version -notmatch '^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$') {
        Stop-Build "BUILD_VERSION '$version' is not valid. Example: 10.4.11"
    }

    $excludedRoots = @(
        (Join-Path $projectRoot '.git'),
        (Join-Path $projectRoot 'releases'),
        (Join-Path $projectRoot 'tools')
    )

    $pluginCandidates = @(Get-ChildItem -LiteralPath $projectRoot -Recurse -File -Filter 'elev8-os.php' | Where-Object {
        $candidate = $_.FullName
        -not ($excludedRoots | Where-Object { $candidate.StartsWith($_, [System.StringComparison]::OrdinalIgnoreCase) })
    })

    if ($pluginCandidates.Count -eq 0) {
        Stop-Build "Could not find elev8-os.php inside: $projectRoot"
    }
    if ($pluginCandidates.Count -gt 1) {
        Write-Host 'Multiple plugin entry files were found:' -ForegroundColor Yellow
        $pluginCandidates | ForEach-Object { Write-Host " - $($_.FullName)" -ForegroundColor Yellow }
        Stop-Build 'Keep only one active Elev8 OS source folder in the repository.'
    }

    $pluginFile = $pluginCandidates[0].FullName
    $pluginSourceRoot = $pluginCandidates[0].Directory.FullName
    $buildTimestamp = Get-Date
    $buildId = $buildTimestamp.ToString('yyyyMMdd-HHmmss')
    $buildDateIso = $buildTimestamp.ToUniversalTime().ToString('yyyy-MM-ddTHH:mm:ssZ')
    $temporaryRoot = Join-Path $env:TEMP ("elev8-os-release-" + $buildId)
    $stagingPluginRoot = Join-Path $temporaryRoot 'elev8-os'
    $zipName = "elev8-os-$version-$buildId.zip"
    $zipPath = Join-Path $releaseDirectory $zipName
    $manifestOutputPath = Join-Path $releaseDirectory ("elev8-os-$version-$buildId-manifest.json")
    $checksumOutputPath = "$zipPath.sha256"

    Write-Host "Repository:    $projectRoot"
    Write-Host "Plugin source: $pluginSourceRoot"
    Write-Host "Version:       $version"
    Write-Host "Build:         $buildId"

    Write-Step 'Stamping the plugin version'

    $sourceLines = [System.IO.File]::ReadAllLines($pluginFile)
    $updatedLines = New-Object System.Collections.Generic.List[string]
    $headerFound = $false
    $constantFound = $false

    foreach ($line in $sourceLines) {
        if ($line -match '^\s*\*\s*Version:\s*') {
            $prefix = $line.Substring(0, $line.IndexOf('Version:'))
            $updatedLines.Add($prefix + 'Version: ' + $version)
            $headerFound = $true
            continue
        }

        if ($line -match "^\s*define\(\s*'ELEV8_OS_VERSION'\s*,") {
            $indent = ([regex]::Match($line, '^\s*')).Value
            $updatedLines.Add($indent + "define('ELEV8_OS_VERSION', '$version');")
            $constantFound = $true
            continue
        }

        $updatedLines.Add($line)
    }

    if (-not $headerFound) {
        Stop-Build 'The WordPress Version header was not found in elev8-os.php.'
    }
    if (-not $constantFound) {
        Stop-Build 'The ELEV8_OS_VERSION constant was not found in elev8-os.php.'
    }

    $verificationText = $updatedLines -join "`r`n"
    $escapedVersion = [regex]::Escape($version)
    if ($verificationText -notmatch "(?m)^\s*\*\s*Version:\s*$escapedVersion\s*$") {
        Stop-Build 'Plugin header verification failed before writing.'
    }
    if ($verificationText -notmatch "define\(\s*'ELEV8_OS_VERSION'\s*,\s*'$escapedVersion'\s*\);") {
        Stop-Build 'Version constant verification failed before writing.'
    }

    [System.IO.File]::WriteAllText($pluginFile, $verificationText + "`r`n", (New-Object System.Text.UTF8Encoding($false)))
    Write-Host 'Plugin version stamp verified.' -ForegroundColor Green

    $phpFiles = @(Get-ChildItem -LiteralPath $pluginSourceRoot -Recurse -File -Filter '*.php' | Where-Object {
        $_.FullName -notmatch '[\\/]vendor[\\/]'
    })
    if ($phpFiles.Count -eq 0) {
        Stop-Build 'No PHP files were found.'
    }

    $phpValidationStatus = 'SKIPPED'
    $phpExecutable = 'Unavailable'

    if (-not $SkipPhpValidation) {
        Write-Step "Validating $($phpFiles.Count) PHP files"
        $phpExecutable = Find-PhpExecutable
        if (-not $phpExecutable) {
            Stop-Build 'PHP CLI could not be found. Open Local once so its PHP service is installed, then run the builder again.'
        }
        Write-Host "PHP executable: $phpExecutable"
        foreach ($file in $phpFiles) {
            $validationOutput = & $phpExecutable -l $file.FullName 2>&1
            if ($LASTEXITCODE -ne 0) {
                Write-Host ($validationOutput -join "`r`n") -ForegroundColor Red
                Stop-Build "PHP syntax failed: $($file.FullName)"
            }
        }
        $phpValidationStatus = 'PASS'
        Write-Host 'PHP syntax validation passed.' -ForegroundColor Green
    }

    Write-Step 'Reading Git information'
    $gitBranch = 'Unavailable'
    $gitCommit = 'Unavailable'
    $gitDirty = $null
    $gitCommand = Get-Command git.exe -ErrorAction SilentlyContinue
    if (-not $gitCommand) { $gitCommand = Get-Command git -ErrorAction SilentlyContinue }

    if ($gitCommand -and (Test-Path -LiteralPath (Join-Path $projectRoot '.git'))) {
        Push-Location $projectRoot
        try {
            $value = & $gitCommand.Source rev-parse --abbrev-ref HEAD 2>$null
            if ($LASTEXITCODE -eq 0 -and $value) { $gitBranch = ($value | Select-Object -First 1).Trim() }
            $value = & $gitCommand.Source rev-parse HEAD 2>$null
            if ($LASTEXITCODE -eq 0 -and $value) { $gitCommit = ($value | Select-Object -First 1).Trim() }
            $value = & $gitCommand.Source status --porcelain 2>$null
            if ($LASTEXITCODE -eq 0) { $gitDirty = [bool]$value }
        }
        finally { Pop-Location }
    }

    Write-Step 'Preparing release package'
    if (Test-Path -LiteralPath $temporaryRoot) { Remove-Item -LiteralPath $temporaryRoot -Recurse -Force }
    New-Item -ItemType Directory -Path $stagingPluginRoot -Force | Out-Null
    New-Item -ItemType Directory -Path $releaseDirectory -Force | Out-Null

    $excludedNames = @('.git', '.github', '.idea', '.vscode', 'node_modules', 'vendor', 'releases', 'tools', 'BUILD_VERSION', 'Build Elev8 OS Release.bat', 'release-manifest.json')
    Get-ChildItem -LiteralPath $pluginSourceRoot -Force | Where-Object { $excludedNames -notcontains $_.Name } | ForEach-Object {
        Copy-Item -LiteralPath $_.FullName -Destination $stagingPluginRoot -Recurse -Force
    }

    $stagedFiles = @(Get-ChildItem -LiteralPath $stagingPluginRoot -Recurse -File)
    if ($stagedFiles.Count -eq 0) { Stop-Build 'The release staging folder is empty.' }

    Write-Step 'Generating release manifest'
    $manifestFiles = foreach ($file in ($stagedFiles | Sort-Object FullName)) {
        [ordered]@{
            path = (Get-RelativePath $stagingPluginRoot $file.FullName).Replace('\', '/')
            bytes = $file.Length
            sha256 = (Get-FileHash -LiteralPath $file.FullName -Algorithm SHA256).Hash.ToLowerInvariant()
        }
    }

    $manifest = [ordered]@{
        product = 'Elev8 OS'
        version = $version
        build = $buildId
        builtAtUtc = $buildDateIso
        package = $zipName
        git = [ordered]@{ branch = $gitBranch; commit = $gitCommit; workingTreeDirty = $gitDirty }
        validation = [ordered]@{ phpSyntax = $phpValidationStatus; phpFileCount = $phpFiles.Count; phpExecutable = $phpExecutable }
        packageContents = [ordered]@{ fileCount = $stagedFiles.Count + 1; totalBytesBeforeManifest = ($stagedFiles | Measure-Object Length -Sum).Sum }
        files = @($manifestFiles)
    }

    $manifestJson = $manifest | ConvertTo-Json -Depth 8
    [System.IO.File]::WriteAllText((Join-Path $stagingPluginRoot 'release-manifest.json'), $manifestJson, (New-Object System.Text.UTF8Encoding($false)))
    [System.IO.File]::WriteAllText($manifestOutputPath, $manifestJson, (New-Object System.Text.UTF8Encoding($false)))

    Write-Step 'Packaging release ZIP'
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    if (Test-Path -LiteralPath $zipPath) { Remove-Item -LiteralPath $zipPath -Force }
    [System.IO.Compression.ZipFile]::CreateFromDirectory($temporaryRoot, $zipPath, [System.IO.Compression.CompressionLevel]::Optimal, $false)
    if (-not (Test-Path -LiteralPath $zipPath -PathType Leaf)) { Stop-Build 'ZIP packaging did not create a file.' }

    Write-Step 'Generating SHA-256 checksum'
    $zipHash = (Get-FileHash -LiteralPath $zipPath -Algorithm SHA256).Hash.ToLowerInvariant()
    [System.IO.File]::WriteAllText($checksumOutputPath, "$zipHash  $zipName`r`n", (New-Object System.Text.UTF8Encoding($false)))

    Write-Host ""
    Write-Host '============================================' -ForegroundColor Green
    Write-Host 'Elev8 OS Release Built' -ForegroundColor Green
    Write-Host '============================================' -ForegroundColor Green
    Write-Host "Version:        $version"
    Write-Host "Build:          $buildId"
    Write-Host "Git branch:     $gitBranch"
    Write-Host "Git commit:     $gitCommit"
    Write-Host "PHP validation: $phpValidationStatus ($($phpFiles.Count) files)"
    Write-Host "ZIP:            $zipPath"
    Write-Host "Manifest:       $manifestOutputPath"
    Write-Host "Checksum:       $checksumOutputPath"
    Write-Host "SHA-256:        $zipHash"
    Write-Host 'Ready for Local testing.' -ForegroundColor Green
    Write-Host '============================================' -ForegroundColor Green

    if ($OpenOutputFolder) { Start-Process explorer.exe -ArgumentList ('"' + $releaseDirectory + '"') }
    if (Test-Path -LiteralPath $temporaryRoot) { Remove-Item -LiteralPath $temporaryRoot -Recurse -Force }
    exit 0
}
catch {
    Write-Host ""
    Write-Host 'UNEXPECTED BUILD ERROR' -ForegroundColor Red
    Write-Host $_.Exception.Message -ForegroundColor Red
    if ($_.InvocationInfo -and $_.InvocationInfo.PositionMessage) { Write-Host $_.InvocationInfo.PositionMessage -ForegroundColor DarkRed }
    exit 1
}

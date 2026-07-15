param(
    [switch]$OpenOutputFolder
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$RepositoryRoot = Split-Path -Parent $PSScriptRoot
$PluginSource = Join-Path $RepositoryRoot 'plugin\elev8-os'
$PluginMainFile = Join-Path $PluginSource 'elev8-os.php'
$OutputDirectory = Join-Path $RepositoryRoot 'releases'

function Write-Step {
    param([string]$Message)
    Write-Host ""
    Write-Host "==> $Message" -ForegroundColor Cyan
}

function Find-PhpExecutable {
    $command = Get-Command php -ErrorAction SilentlyContinue
    if ($null -ne $command) {
        return $command.Source
    }

    $searchRoots = @(
        (Join-Path $env:APPDATA 'Local\lightning-services'),
        (Join-Path $env:LOCALAPPDATA 'Programs\Local'),
        (Join-Path $env:LOCALAPPDATA 'Local'),
        'C:\Program Files\Local',
        'C:\Program Files (x86)\Local'
    ) | Where-Object { $_ -and (Test-Path $_) }

    foreach ($searchRoot in $searchRoots) {
        $candidate = Get-ChildItem `
            -Path $searchRoot `
            -Filter 'php.exe' `
            -File `
            -Recurse `
            -ErrorAction SilentlyContinue |
            Sort-Object LastWriteTime -Descending |
            Select-Object -First 1

        if ($null -ne $candidate) {
            return $candidate.FullName
        }
    }

    throw @"
PHP could not be located.

Open Local, start the Elev8 OS Development site, and run this builder again.
The builder requires PHP so every plugin PHP file can be syntax-checked before packaging.
"@
}

function Get-PluginVersion {
    param([string]$MainFile)

    $content = Get-Content -Path $MainFile -Raw
    $match = [regex]::Match($content, '(?im)^\s*\*\s*Version:\s*([^\r\n]+)')

    if ($match.Success) {
        $version = $match.Groups[1].Value.Trim()
        if ($version -match '^[0-9A-Za-z._-]+$') {
            return $version
        }
    }

    return 'dev'
}

function Test-PhpFiles {
    param(
        [string]$PhpExecutable,
        [string]$SourceDirectory
    )

    $phpFiles = Get-ChildItem -Path $SourceDirectory -Filter '*.php' -File -Recurse
    if ($phpFiles.Count -eq 0) {
        throw "No PHP files were found in $SourceDirectory"
    }

    $failures = @()

    foreach ($file in $phpFiles) {
        Write-Host ("Checking {0}" -f $file.FullName)
        $output = & $PhpExecutable -l $file.FullName 2>&1

        if ($LASTEXITCODE -ne 0) {
            $failures += [pscustomobject]@{
                File = $file.FullName
                Output = ($output -join [Environment]::NewLine)
            }
        }
    }

    if ($failures.Count -gt 0) {
        Write-Host ""
        Write-Host "PHP syntax errors were found:" -ForegroundColor Red

        foreach ($failure in $failures) {
            Write-Host ""
            Write-Host $failure.File -ForegroundColor Red
            Write-Host $failure.Output
        }

        throw "Release stopped because PHP syntax validation failed."
    }

    Write-Host ("All {0} PHP files passed syntax validation." -f $phpFiles.Count) -ForegroundColor Green
}

function Copy-PluginSource {
    param(
        [string]$Source,
        [string]$Destination
    )

    New-Item -ItemType Directory -Path $Destination -Force | Out-Null

    $excludedDirectoryNames = @(
        '.git',
        '.github',
        '.idea',
        '.vscode',
        'node_modules',
        'tests',
        'releases'
    )

    Get-ChildItem -Path $Source -Force | ForEach-Object {
        if ($_.PSIsContainer -and $excludedDirectoryNames -contains $_.Name) {
            return
        }

        Copy-Item -Path $_.FullName -Destination $Destination -Recurse -Force
    }
}

Write-Host ""
Write-Host "Elev8 OS Release Builder" -ForegroundColor Green
Write-Host "Repository: $RepositoryRoot"

if (-not (Test-Path $PluginSource -PathType Container)) {
    throw "Plugin source folder not found: $PluginSource"
}

if (-not (Test-Path $PluginMainFile -PathType Leaf)) {
    throw "Plugin main file not found: $PluginMainFile"
}

Write-Step "Locating PHP"
$phpExecutable = Find-PhpExecutable
Write-Host "Using PHP: $phpExecutable"

Write-Step "Checking PHP syntax"
Test-PhpFiles -PhpExecutable $phpExecutable -SourceDirectory $PluginSource

$version = Get-PluginVersion -MainFile $PluginMainFile
$timestamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$zipName = "elev8-os-$version-$timestamp.zip"
$zipPath = Join-Path $OutputDirectory $zipName
$tempRoot = Join-Path ([System.IO.Path]::GetTempPath()) ("elev8-os-release-" + [guid]::NewGuid().ToString('N'))
$tempPlugin = Join-Path $tempRoot 'elev8-os'

try {
    Write-Step "Preparing clean release files"
    New-Item -ItemType Directory -Path $OutputDirectory -Force | Out-Null
    New-Item -ItemType Directory -Path $tempRoot -Force | Out-Null
    Copy-PluginSource -Source $PluginSource -Destination $tempPlugin

    if (Test-Path $zipPath) {
        Remove-Item $zipPath -Force
    }

    Write-Step "Creating WordPress ZIP"
    Compress-Archive -Path $tempPlugin -DestinationPath $zipPath -CompressionLevel Optimal -Force

    if (-not (Test-Path $zipPath -PathType Leaf)) {
        throw "The ZIP file was not created."
    }

    Add-Type -AssemblyName System.IO.Compression.FileSystem
    $archive = [System.IO.Compression.ZipFile]::OpenRead($zipPath)

    try {
        $requiredEntry = $archive.Entries |
            Where-Object { $_.FullName -replace '\\', '/' -eq 'elev8-os/elev8-os.php' } |
            Select-Object -First 1

        if ($null -eq $requiredEntry) {
            throw "ZIP validation failed: elev8-os/elev8-os.php is missing."
        }

        if ($archive.Entries.Count -lt 2) {
            throw "ZIP validation failed: the archive does not contain the complete plugin."
        }
    }
    finally {
        $archive.Dispose()
    }

    $hash = (Get-FileHash -Path $zipPath -Algorithm SHA256).Hash.ToLowerInvariant()

    Write-Host ""
    Write-Host "============================================" -ForegroundColor Green
    Write-Host "Release built successfully!" -ForegroundColor Green
    Write-Host ""
    Write-Host "ZIP:"
    Write-Host $zipPath -ForegroundColor Yellow
    Write-Host ""
    Write-Host "SHA-256:"
    Write-Host $hash
    Write-Host "============================================" -ForegroundColor Green

    if ($OpenOutputFolder) {
        Start-Process explorer.exe -ArgumentList "/select,`"$zipPath`""
    }
}
finally {
    if (Test-Path $tempRoot) {
        Remove-Item $tempRoot -Recurse -Force -ErrorAction SilentlyContinue
    }
}

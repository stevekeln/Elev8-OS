param(
    [switch]$OpenOutputFolder
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$RepositoryRoot = Split-Path -Parent $PSScriptRoot
$PluginSource = Join-Path $RepositoryRoot 'plugin\elev8-os'
$PluginMainFile = Join-Path $PluginSource 'elev8-os.php'
$OutputDirectory = Join-Path $RepositoryRoot 'releases'
$StagingRoot = Join-Path $env:TEMP ('elev8-os-release-' + [guid]::NewGuid().ToString('N'))
$StagedPlugin = Join-Path $StagingRoot 'elev8-os'

function Write-Step {
    param([string]$Message)

    Write-Host ''
    Write-Host ('==> ' + $Message) -ForegroundColor Cyan
}

function Assert-PathExists {
    param(
        [string]$Path,
        [string]$Description,
        [switch]$Directory
    )

    if ($Directory) {
        if (-not (Test-Path -LiteralPath $Path -PathType Container)) {
            throw "$Description was not found: $Path"
        }

        return
    }

    if (-not (Test-Path -LiteralPath $Path -PathType Leaf)) {
        throw "$Description was not found: $Path"
    }
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
    ) | Where-Object { $_ -and (Test-Path -LiteralPath $_ -PathType Container) }

    foreach ($searchRoot in $searchRoots) {
        $candidate = Get-ChildItem `
            -LiteralPath $searchRoot `
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

Start the Elev8 OS Development site in Local and run the builder again.
The builder requires PHP to syntax-check every plugin PHP file.
"@
}

function Get-PluginVersion {
    param([string]$MainFile)

    $content = Get-Content -LiteralPath $MainFile -Raw
    $match = [regex]::Match($content, '(?im)^\s*\*\s*Version:\s*([^\r\n]+)')

    if ($match.Success) {
        $version = $match.Groups[1].Value.Trim()

        if ($version -match '^[0-9A-Za-z._-]+$') {
            return $version
        }
    }

    return 'dev'
}

function Test-PhpSyntax {
    param(
        [string]$PhpExecutable,
        [string]$SourceDirectory
    )

    $phpFiles = @(Get-ChildItem -LiteralPath $SourceDirectory -Filter '*.php' -File -Recurse)

    if ($phpFiles.Count -eq 0) {
        throw "No PHP files were found in $SourceDirectory"
    }

    $failures = @()

    foreach ($file in $phpFiles) {
        Write-Host ('Checking ' + $file.FullName)
        $output = & $PhpExecutable -l $file.FullName 2>&1

        if ($LASTEXITCODE -ne 0) {
            $failures += [pscustomobject]@{
                File = $file.FullName
                Output = ($output -join [Environment]::NewLine)
            }
        }
    }

    if ($failures.Count -gt 0) {
        Write-Host ''
        Write-Host 'PHP syntax errors were found:' -ForegroundColor Red

        foreach ($failure in $failures) {
            Write-Host ''
            Write-Host $failure.File -ForegroundColor Red
            Write-Host $failure.Output
        }

        throw 'Release stopped because PHP syntax validation failed.'
    }

    Write-Host ("All {0} PHP files passed syntax validation." -f $phpFiles.Count) -ForegroundColor Green
}

function Test-ExcludedRelativePath {
    param([string]$RelativePath)

    $normalized = $RelativePath.Replace('\', '/')
    $parts = $normalized.Split('/')

    $excludedNames = @(
        '.git',
        '.github',
        '.idea',
        '.vscode',
        'node_modules',
        'tests',
        'releases'
    )

    foreach ($part in $parts) {
        if ($excludedNames -contains $part) {
            return $true
        }
    }

    return $false
}

function Copy-PluginToStaging {
    param(
        [string]$SourceDirectory,
        [string]$DestinationDirectory
    )

    New-Item -ItemType Directory -Path $DestinationDirectory -Force | Out-Null

    $files = @(Get-ChildItem -LiteralPath $SourceDirectory -File -Recurse -Force)

    foreach ($file in $files) {
        $relativePath = $file.FullName.Substring($SourceDirectory.Length).TrimStart('\', '/')

        if (Test-ExcludedRelativePath -RelativePath $relativePath) {
            continue
        }

        $destination = Join-Path $DestinationDirectory $relativePath
        $destinationParent = Split-Path -Parent $destination

        if (-not (Test-Path -LiteralPath $destinationParent -PathType Container)) {
            New-Item -ItemType Directory -Path $destinationParent -Force | Out-Null
        }

        Copy-Item -LiteralPath $file.FullName -Destination $destination -Force
    }
}

function Assert-StagingStructure {
    param([string]$StagedDirectory)

    $requiredFiles = @(
        'elev8-os.php',
        'includes\class-elev8-os-loader.php',
        'includes\class-elev8-os.php'
    )

    $requiredDirectories = @(
        'assets',
        'includes'
    )

    foreach ($requiredFile in $requiredFiles) {
        Assert-PathExists `
            -Path (Join-Path $StagedDirectory $requiredFile) `
            -Description ('Required staged file ' + $requiredFile)
    }

    foreach ($requiredDirectory in $requiredDirectories) {
        Assert-PathExists `
            -Path (Join-Path $StagedDirectory $requiredDirectory) `
            -Description ('Required staged directory ' + $requiredDirectory) `
            -Directory
    }
}

function New-WordPressCompatibleZip {
    param(
        [string]$StagedDirectory,
        [string]$DestinationZip
    )

    Add-Type -AssemblyName System.IO.Compression
    Add-Type -AssemblyName System.IO.Compression.FileSystem

    if (Test-Path -LiteralPath $DestinationZip) {
        Remove-Item -LiteralPath $DestinationZip -Force
    }

    $zipStream = [System.IO.File]::Open(
        $DestinationZip,
        [System.IO.FileMode]::CreateNew,
        [System.IO.FileAccess]::ReadWrite,
        [System.IO.FileShare]::None
    )

    try {
        $archive = New-Object System.IO.Compression.ZipArchive(
            $zipStream,
            [System.IO.Compression.ZipArchiveMode]::Create,
            $false
        )

        try {
            $files = @(Get-ChildItem -LiteralPath $StagedDirectory -File -Recurse -Force)

            if ($files.Count -eq 0) {
                throw 'The staging folder contains no files.'
            }

            foreach ($file in $files) {
                $relativePath = $file.FullName.Substring($StagedDirectory.Length).TrimStart('\', '/')
                $entryName = 'elev8-os/' + $relativePath.Replace('\', '/')

                [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
                    $archive,
                    $file.FullName,
                    $entryName,
                    [System.IO.Compression.CompressionLevel]::Optimal
                ) | Out-Null
            }
        }
        finally {
            $archive.Dispose()
        }
    }
    finally {
        $zipStream.Dispose()
    }
}

function Test-ZipArchive {
    param([string]$ZipPath)

    Add-Type -AssemblyName System.IO.Compression.FileSystem

    $archive = [System.IO.Compression.ZipFile]::OpenRead($ZipPath)

    try {
        $entryNames = @($archive.Entries | ForEach-Object { $_.FullName })

        $requiredEntries = @(
            'elev8-os/elev8-os.php',
            'elev8-os/includes/class-elev8-os-loader.php',
            'elev8-os/includes/class-elev8-os.php'
        )

        foreach ($requiredEntry in $requiredEntries) {
            if ($entryNames -notcontains $requiredEntry) {
                throw "ZIP validation failed. Missing: $requiredEntry"
            }
        }

        $invalidBackslashEntry = $entryNames |
            Where-Object { $_.Contains('\') } |
            Select-Object -First 1

        if ($null -ne $invalidBackslashEntry) {
            throw "ZIP validation failed. Windows-style path found: $invalidBackslashEntry"
        }

        $invalidRootEntry = $entryNames |
            Where-Object { -not $_.StartsWith('elev8-os/') } |
            Select-Object -First 1

        if ($null -ne $invalidRootEntry) {
            throw "ZIP validation failed. File outside elev8-os/: $invalidRootEntry"
        }

        $directoryEntry = $archive.Entries |
            Where-Object { $_.FullName.EndsWith('/') -or $_.FullName.EndsWith('\') } |
            Select-Object -First 1

        if ($null -ne $directoryEntry) {
            throw "ZIP validation failed. Explicit directory entry found: $($directoryEntry.FullName)"
        }

        if ($archive.Entries.Count -lt 3) {
            throw 'ZIP validation failed. The archive appears incomplete.'
        }
    }
    finally {
        $archive.Dispose()
    }
}

Write-Host ''
Write-Host 'Elev8 OS Release Builder' -ForegroundColor Green
Write-Host ('Repository: ' + $RepositoryRoot)

try {
    Assert-PathExists -Path $PluginSource -Description 'Plugin source directory' -Directory
    Assert-PathExists -Path $PluginMainFile -Description 'Plugin main file'

    Write-Step 'Locating PHP'
    $phpExecutable = Find-PhpExecutable
    Write-Host ('Using PHP: ' + $phpExecutable)

    Write-Step 'Checking PHP syntax'
    Test-PhpSyntax -PhpExecutable $phpExecutable -SourceDirectory $PluginSource

    Write-Step 'Creating clean staging folder'
    New-Item -ItemType Directory -Path $StagingRoot -Force | Out-Null
    Copy-PluginToStaging -SourceDirectory $PluginSource -DestinationDirectory $StagedPlugin
    Assert-StagingStructure -StagedDirectory $StagedPlugin

    $version = Get-PluginVersion -MainFile $PluginMainFile
    $timestamp = Get-Date -Format 'yyyyMMdd-HHmmss'
    $zipName = "elev8-os-$version-$timestamp.zip"
    $zipPath = Join-Path $OutputDirectory $zipName

    Write-Step 'Creating WordPress-compatible ZIP'
    New-Item -ItemType Directory -Path $OutputDirectory -Force | Out-Null
    New-WordPressCompatibleZip -StagedDirectory $StagedPlugin -DestinationZip $zipPath

    Assert-PathExists -Path $zipPath -Description 'Release ZIP'

    Write-Step 'Validating ZIP structure'
    Test-ZipArchive -ZipPath $zipPath

    $hash = (Get-FileHash -LiteralPath $zipPath -Algorithm SHA256).Hash.ToLowerInvariant()

    Write-Host ''
    Write-Host '============================================' -ForegroundColor Green
    Write-Host 'Release built successfully!' -ForegroundColor Green
    Write-Host ''
    Write-Host 'ZIP:'
    Write-Host $zipPath -ForegroundColor Yellow
    Write-Host ''
    Write-Host 'SHA-256:'
    Write-Host $hash
    Write-Host '============================================' -ForegroundColor Green

    if ($OpenOutputFolder) {
        Start-Process explorer.exe -ArgumentList "/select,`"$zipPath`""
    }
}
finally {
    if (Test-Path -LiteralPath $StagingRoot) {
        Remove-Item -LiteralPath $StagingRoot -Recurse -Force -ErrorAction SilentlyContinue
    }
}

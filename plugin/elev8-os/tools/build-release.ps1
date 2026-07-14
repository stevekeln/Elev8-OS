[CmdletBinding()]
param(
    [string]$RepositoryRoot = "",
    [switch]$AllowDirty,
    [switch]$OpenOutputFolder
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

function Write-Step { param([string]$Message) Write-Host ""; Write-Host "==> $Message" -ForegroundColor Cyan }
function Stop-Build { param([string]$Message) Write-Host ""; Write-Host "BUILD STOPPED" -ForegroundColor Red; Write-Host $Message -ForegroundColor Red; Write-Host ""; exit 1 }
function Get-CommandPath { param([string]$CommandName) $command = Get-Command $CommandName -ErrorAction SilentlyContinue; if ($null -eq $command) { return $null }; return $command.Source }
function Invoke-Checked { param([string]$FilePath,[string[]]$Arguments,[string]$FailureMessage) $output = & $FilePath @Arguments 2>&1; if ($LASTEXITCODE -ne 0) { $details = ($output | Out-String).Trim(); if ($details) { Stop-Build "$FailureMessage`n`n$details" }; Stop-Build $FailureMessage }; return $output }
function Get-PluginVersion { param([string]$PluginFile) $content = Get-Content -LiteralPath $PluginFile -Raw; $match = [regex]::Match($content, '(?im)^\s*\*\s*Version:\s*([0-9A-Za-z\.\-\+]+)\s*$'); if (-not $match.Success) { Stop-Build "Could not find a valid WordPress Version header in:`n$PluginFile" }; return $match.Groups[1].Value.Trim() }
function Get-SafeBranchName { param([string]$BranchName) return ($BranchName -replace '[^A-Za-z0-9._-]', '-') }
function Get-RelativePathSafe { param([string]$BasePath,[string]$FullPath) $baseUri = New-Object System.Uri(($BasePath.TrimEnd('\') + '\')); $fullUri = New-Object System.Uri($FullPath); return [System.Uri]::UnescapeDataString($baseUri.MakeRelativeUri($fullUri).ToString().Replace('/', '\')) }

try {
    Write-Host ""; Write-Host "Elev8 OS Release Builder" -ForegroundColor Green; Write-Host "Commercial-quality validation and packaging" -ForegroundColor DarkGray
    if ([string]::IsNullOrWhiteSpace($RepositoryRoot)) { $scriptDirectory = Split-Path -Parent $MyInvocation.MyCommand.Path; $RepositoryRoot = Split-Path -Parent $scriptDirectory }
    $RepositoryRoot = [System.IO.Path]::GetFullPath($RepositoryRoot)
    $PluginRoot = Join-Path $RepositoryRoot "plugin\elev8-os"
    $PluginMainFile = Join-Path $PluginRoot "elev8-os.php"
    $ConfigPath = Join-Path $RepositoryRoot "tools\release-config.json"

    Write-Step "Checking repository structure"
    if (-not (Test-Path -LiteralPath $RepositoryRoot -PathType Container)) { Stop-Build "Repository folder does not exist:`n$RepositoryRoot" }
    if (-not (Test-Path -LiteralPath (Join-Path $RepositoryRoot ".git") -PathType Container)) { Stop-Build "This does not appear to be the Elev8 OS Git repository:`n$RepositoryRoot" }
    if (-not (Test-Path -LiteralPath $PluginRoot -PathType Container)) { Stop-Build "Plugin folder is missing:`n$PluginRoot" }
    if (-not (Test-Path -LiteralPath $PluginMainFile -PathType Leaf)) { Stop-Build "Main plugin file is missing:`n$PluginMainFile" }
    if (-not (Test-Path -LiteralPath $ConfigPath -PathType Leaf)) { Stop-Build "Release configuration is missing:`n$ConfigPath" }
    $config = Get-Content -LiteralPath $ConfigPath -Raw | ConvertFrom-Json

    Write-Step "Checking required software"
    $gitPath = Get-CommandPath "git"; if (-not $gitPath) { Stop-Build "Git was not found. Install Git for Windows or make sure Git is available in PATH." }
    $phpPath = Get-CommandPath "php"; if (-not $phpPath) { Stop-Build "PHP was not found. The release was not built because PHP syntax checks could not run." }

    Write-Step "Reading Git branch and commit"
    Push-Location $RepositoryRoot
    try {
        $branch = (Invoke-Checked $gitPath @("branch", "--show-current") "Could not determine the current Git branch." | Out-String).Trim()
        $commit = (Invoke-Checked $gitPath @("rev-parse", "--short=12", "HEAD") "Could not determine the current Git commit." | Out-String).Trim()
        $status = (& $gitPath status --porcelain 2>&1 | Out-String).Trim()
        if ([string]::IsNullOrWhiteSpace($branch)) { Stop-Build "Git is in a detached HEAD state. Switch to a named branch before building a release." }
        $allowedBranch = $false; foreach ($pattern in $config.allowed_branch_patterns) { if ($branch -like $pattern) { $allowedBranch = $true; break } }
        if (-not $allowedBranch) { Stop-Build "The current branch '$branch' is not allowed by release-config.json." }
        if (-not $AllowDirty -and -not [string]::IsNullOrWhiteSpace($status)) { Stop-Build "GitHub Desktop has uncommitted changes. Commit or discard them before building.`n`n$status" }
    } finally { Pop-Location }
    Write-Host "Branch: $branch"; Write-Host "Commit: $commit"

    Write-Step "Checking required plugin files"
    $missingFiles = New-Object System.Collections.Generic.List[string]
    foreach ($relativePath in $config.required_files) { $fullPath = Join-Path $PluginRoot ([string]$relativePath); if (-not (Test-Path -LiteralPath $fullPath -PathType Leaf)) { $missingFiles.Add([string]$relativePath) } }
    if ($missingFiles.Count -gt 0) { Stop-Build ("Required plugin files are missing:`n- " + ($missingFiles -join "`n- ")) }

    Write-Step "Checking for forbidden files inside the plugin"
    $forbiddenFound = New-Object System.Collections.Generic.List[string]
    foreach ($pattern in $config.forbidden_plugin_patterns) { $matches = Get-ChildItem -LiteralPath $PluginRoot -Recurse -Force -File | Where-Object { $_.Name -like $pattern }; foreach ($match in $matches) { $forbiddenFound.Add((Get-RelativePathSafe $PluginRoot $match.FullName)) } }
    if ($forbiddenFound.Count -gt 0) { Stop-Build ("Files that should not be packaged were found inside the plugin:`n- " + (($forbiddenFound | Sort-Object -Unique) -join "`n- ")) }

    Write-Step "Running PHP syntax checks"
    $phpFiles = Get-ChildItem -LiteralPath $PluginRoot -Recurse -File -Filter "*.php" | Sort-Object FullName
    if ($phpFiles.Count -eq 0) { Stop-Build "No PHP files were found in the plugin." }
    $syntaxFailures = New-Object System.Collections.Generic.List[string]
    foreach ($phpFile in $phpFiles) { $result = & $phpPath -l $phpFile.FullName 2>&1; if ($LASTEXITCODE -ne 0) { $relative = Get-RelativePathSafe $PluginRoot $phpFile.FullName; $syntaxFailures.Add("$relative`n$($result | Out-String)") } }
    if ($syntaxFailures.Count -gt 0) { Stop-Build ("PHP syntax errors were found:`n`n" + ($syntaxFailures -join "`n")) }
    Write-Host "Checked $($phpFiles.Count) PHP files successfully." -ForegroundColor Green

    Write-Step "Reading plugin version"
    $version = Get-PluginVersion $PluginMainFile; $safeBranch = Get-SafeBranchName $branch; $timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
    $repositoryParent = Split-Path -Parent $RepositoryRoot
    $outputRoot = if ([string]::IsNullOrWhiteSpace([string]$config.output_directory)) { Join-Path $repositoryParent "Elev8-OS-Releases" } elseif ([System.IO.Path]::IsPathRooted([string]$config.output_directory)) { [string]$config.output_directory } else { Join-Path $repositoryParent ([string]$config.output_directory) }
    $outputRoot = [System.IO.Path]::GetFullPath($outputRoot); New-Item -ItemType Directory -Path $outputRoot -Force | Out-Null
    $releaseBaseName = "elev8-os-v$version-$safeBranch-$timestamp"; $zipPath = Join-Path $outputRoot "$releaseBaseName.zip"; $manifestPath = Join-Path $outputRoot "$releaseBaseName-manifest.json"
    $tempRoot = Join-Path ([System.IO.Path]::GetTempPath()) ("elev8-os-release-" + [guid]::NewGuid().ToString("N")); $tempPluginRoot = Join-Path $tempRoot "elev8-os"

    Write-Step "Copying plugin to a clean temporary build folder"
    New-Item -ItemType Directory -Path $tempPluginRoot -Force | Out-Null
    $excludeDirectories = @($config.exclude_directories); $excludeFiles = @($config.exclude_files)
    Get-ChildItem -LiteralPath $PluginRoot -Recurse -Force | ForEach-Object {
        $relative = Get-RelativePathSafe $PluginRoot $_.FullName; $segments = $relative -split '\\'; $skip = $false
        foreach ($segment in $segments) { if ($excludeDirectories -contains $segment) { $skip = $true; break } }
        if (-not $skip -and -not $_.PSIsContainer) { foreach ($filePattern in $excludeFiles) { if ($_.Name -like $filePattern) { $skip = $true; break } } }
        if ($skip) { return }
        $destination = Join-Path $tempPluginRoot $relative
        if ($_.PSIsContainer) { New-Item -ItemType Directory -Path $destination -Force | Out-Null } else { $destinationDirectory = Split-Path -Parent $destination; New-Item -ItemType Directory -Path $destinationDirectory -Force | Out-Null; Copy-Item -LiteralPath $_.FullName -Destination $destination -Force }
    }

    Write-Step "Building WordPress plugin ZIP"
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    [System.IO.Compression.ZipFile]::CreateFromDirectory($tempRoot,$zipPath,[System.IO.Compression.CompressionLevel]::Optimal,$false)
    if (-not (Test-Path -LiteralPath $zipPath -PathType Leaf)) { Stop-Build "The ZIP file was not created." }

    Write-Step "Verifying ZIP structure"
    $archive = [System.IO.Compression.ZipFile]::OpenRead($zipPath)
    try {
        $entryNames = @($archive.Entries | ForEach-Object { $_.FullName.Replace('\', '/') })
        foreach ($requiredEntry in @("elev8-os/elev8-os.php","elev8-os/includes/class-elev8-os-loader.php")) { if ($entryNames -notcontains $requiredEntry) { Stop-Build "The ZIP structure is invalid. Missing entry:`n$requiredEntry" } }
        $badRootEntries = $entryNames | Where-Object { $_ -ne "" -and -not $_.StartsWith("elev8-os/") }
        if ($badRootEntries.Count -gt 0) { Stop-Build ("The ZIP contains files outside the elev8-os folder:`n- " + ($badRootEntries -join "`n- ")) }
    } finally { $archive.Dispose() }

    $zipHash = (Get-FileHash -LiteralPath $zipPath -Algorithm SHA256).Hash.ToLowerInvariant(); $zipSize = (Get-Item -LiteralPath $zipPath).Length
    $manifest = [ordered]@{ product="Elev8 OS"; version=$version; branch=$branch; commit=$commit; built_at_local=(Get-Date).ToString("o"); zip_file=[System.IO.Path]::GetFileName($zipPath); zip_size_bytes=$zipSize; sha256=$zipHash; php_files_checked=$phpFiles.Count; repository_root=$RepositoryRoot; plugin_root=$PluginRoot }
    $manifest | ConvertTo-Json -Depth 5 | Set-Content -LiteralPath $manifestPath -Encoding UTF8
    Remove-Item -LiteralPath $tempRoot -Recurse -Force -ErrorAction SilentlyContinue

    Write-Host ""; Write-Host "RELEASE BUILD PASSED" -ForegroundColor Green; Write-Host "Version: $version"; Write-Host "Branch:  $branch"; Write-Host "Commit:  $commit"; Write-Host "ZIP:     $zipPath"; Write-Host "SHA256:  $zipHash" -ForegroundColor DarkGray; Write-Host ""; Write-Host "Upload the ZIP through WordPress Plugins -> Add New -> Upload Plugin." -ForegroundColor Yellow; Write-Host "Keep the manifest beside the ZIP as the permanent release record." -ForegroundColor DarkGray; Write-Host ""
    if ($OpenOutputFolder) { Start-Process explorer.exe $outputRoot }
    exit 0
} catch { Stop-Build $_.Exception.Message }

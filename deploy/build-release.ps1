[CmdletBinding()]
param(
    [switch]$SkipAssetBuild,
    [string]$OutputDir = 'dist\release'
)

# Builds public_html.zip and chef-private-setup.zip from an explicit allowlist.
# Entries are sorted and use a fixed timestamp, so equal inputs give equal archive hashes.
# Run deploy/export-install-sql.ps1 first. This script uploads nothing.

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$PSNativeCommandArgumentPassing = 'Standard'

Add-Type -AssemblyName System.IO.Compression

$repoRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$outputRoot = [System.IO.Path]::GetFullPath((Join-Path $repoRoot $OutputDir))
$buildRoot = Join-Path $outputRoot 'build'
$fixedTime = [DateTimeOffset]::new(2026, 10, 19, 0, 0, 0, [TimeSpan]::Zero)
$schemaVersion = [regex]::Match((Get-Content -Raw (Join-Path $repoRoot 'database\lib.php')), "CHEF_SCHEMA_VERSION = '(\d+)'").Groups[1].Value
$installSql = Join-Path $buildRoot "sql\chef-install-$schemaVersion.sql"

function Get-Sha256([string]$Path) {
    return (Get-FileHash -LiteralPath $Path -Algorithm SHA256).Hash.ToLowerInvariant()
}

function Repo([string]$Relative) {
    $path = Join-Path $repoRoot $Relative
    if (-not [System.IO.File]::Exists($path)) { throw "Required release file is missing: $Relative" }
    return $path
}

# 1. Local asset build. Hostinger never runs Node.
if (-not $SkipAssetBuild) {
    & node (Repo 'chef-workflow\scripts\build-guest.mjs')
    if ($LASTEXITCODE -ne 0) { throw 'Guest build failed.' }
    & npm.cmd run --silent build:admin-css
    if ($LASTEXITCODE -ne 0) { throw 'Admin CSS build failed.' }
}

# 2. Guest baseline gate.
$baseline = Get-Content -Raw (Repo 'chef-workflow\docs\APPROVED_BASELINE.json') | ConvertFrom-Json
$expectedGuest = ($baseline.current_revision.files | Where-Object { $_.relative_path -eq 'public_html/index.html' }).sha256
$guestHash = Get-Sha256 (Repo 'public_html\index.html')
if ($guestHash -ne $expectedGuest -or $guestHash -ne (Get-Sha256 (Repo 'index.html'))) {
    throw 'public_html/index.html does not match current_revision in APPROVED_BASELINE.json.'
}
if (-not [System.IO.File]::Exists($installSql)) { throw 'Installation SQL is missing. Run deploy/export-install-sql.ps1.' }

# 3. Third-party notice for the compiled admin stylesheet.
New-Item -ItemType Directory -Force $buildRoot | Out-Null
$tailwindPackage = Get-Content -Raw (Repo 'node_modules\tailwindcss\package.json') | ConvertFrom-Json
$notice = Join-Path $buildRoot 'THIRD_PARTY_NOTICES.txt'
$noticeText = @(
    'Chef A La Carte third-party notices',
    '',
    "admin/assets/admin.css contains generated output of Tailwind CSS $($tailwindPackage.version).",
    'Source: https://github.com/tailwindlabs/tailwindcss',
    '',
    (Get-Content -Raw (Repo 'node_modules\tailwindcss\LICENSE')).Trim()
) -join "`n"
[System.IO.File]::WriteAllText($notice, $noticeText + "`n", [System.Text.UTF8Encoding]::new($false))

# 4. Explicit allowlists. Published path => source path.
$adminPhp = @(
    'branches', 'bulk-prices', 'campaign', 'categories', 'category-order', 'config', 'feedbacks', 'footer',
    'functions', 'header', 'import-products', 'index', 'login', 'logout', 'media-service', 'private-image',
    'product-order', 'products', 'security', 'translate-service'
)
$actualAdminPhp = @(Get-ChildItem -LiteralPath (Join-Path $repoRoot 'admin') -Filter '*.php' -File | ForEach-Object BaseName | Sort-Object)
if ((Compare-Object $actualAdminPhp ($adminPhp | Sort-Object)) -ne $null) {
    throw 'admin/*.php differs from the release allowlist. Review the new or removed file.'
}

$public = [ordered]@{
    'index.html' = Repo 'public_html\index.html'
    '404.html' = Repo 'public_html\404.html'
    '.htaccess' = Repo 'public_html\.htaccess'
    'media/.htaccess' = Repo 'media\.htaccess'
    'database/lib.php' = Repo 'database\lib.php'
    'admin/.htaccess' = Repo 'admin\.htaccess'
    'admin/api/menu.php' = Repo 'admin\api\menu.php'
    'admin/api/feedback.php' = Repo 'admin\api\feedback.php'
    'admin/api/translate.php' = Repo 'admin\api\translate.php'
    'admin/translate-service.php' = Repo 'admin\translate-service.php'
    'admin/assets/admin.css' = Repo 'admin\assets\admin.css'
    'admin/assets/admin.js' = Repo 'admin\assets\admin.js'
    'admin/assets/chef-logo.svg' = Repo 'admin\assets\chef-logo.svg'
    'admin/assets/THIRD_PARTY_NOTICES.txt' = $notice
}
foreach ($name in $adminPhp) { $public["admin/$name.php"] = Repo "admin\$name.php" }

$private = [ordered]@{
    'config.production.example.php' = Repo 'deploy\release\config.production.example.php'
    'INSTALL_TR.md' = Repo 'deploy\release\INSTALL_TR.md'
    'THIRD_PARTY_NOTICES.txt' = $notice
    'database/cli.php' = Repo 'database\cli.php'
    'database/lib.php' = Repo 'database\lib.php'
    'database/media-cli.php' = Repo 'database\media-cli.php'
    'database/recover-owner.php' = Repo 'database\recover-owner.php'
    'database/seed-data.php' = Repo 'database\seed-data.php'
    # The CLI tools load these two files through dirname(__DIR__) . '/admin/...'.
    'admin/security.php' = Repo 'admin\security.php'
    'admin/media-service.php' = Repo 'admin\media-service.php'
    "sql/chef-install-$schemaVersion.sql" = $installSql
}
foreach ($migration in Get-ChildItem -LiteralPath (Join-Path $repoRoot 'database\migrations') -Filter '*.php' -File) {
    $private["database/migrations/$($migration.Name)"] = $migration.FullName
}

# 5. Path rules for the Linux server: ASCII, forward slashes, no case collisions.
foreach ($set in @($public, $private)) {
    $lower = [System.Collections.Generic.HashSet[string]]::new()
    foreach ($key in $set.Keys) {
        if ($key -notmatch '^[A-Za-z0-9._/-]+$' -or $key.Contains('..') -or $key.StartsWith('/')) { throw "Unsafe release path: $key" }
        if (-not $lower.Add($key.ToLowerInvariant())) { throw "Case collision in release paths: $key" }
    }
}

function New-DeterministicZip([string]$Path, [System.Collections.Specialized.OrderedDictionary]$Entries) {
    if ([System.IO.File]::Exists($Path)) { [System.IO.File]::Delete($Path) }
    $stream = [System.IO.File]::Open($Path, [System.IO.FileMode]::CreateNew)
    try {
        $zip = [System.IO.Compression.ZipArchive]::new($stream, [System.IO.Compression.ZipArchiveMode]::Create)
        try {
            foreach ($key in ($Entries.Keys | Sort-Object { $_ } -CaseSensitive)) {
                $entry = $zip.CreateEntry($key, [System.IO.Compression.CompressionLevel]::Optimal)
                $entry.LastWriteTime = $fixedTime
                # Unix mode 0644 for regular files.
                $entry.ExternalAttributes = (0x81A4 -shl 16)
                $output = $entry.Open()
                try {
                    $bytes = [System.IO.File]::ReadAllBytes($Entries[$key])
                    $output.Write($bytes, 0, $bytes.Length)
                }
                finally { $output.Dispose() }
            }
        }
        finally { $zip.Dispose() }
    }
    finally { $stream.Dispose() }
}

New-Item -ItemType Directory -Force $outputRoot | Out-Null
$publicZip = Join-Path $outputRoot 'public_html.zip'
$privateZip = Join-Path $outputRoot 'chef-private-setup.zip'
New-DeterministicZip $publicZip $public
New-DeterministicZip $privateZip $private
Copy-Item -LiteralPath (Repo 'deploy\release\INSTALL_TR.md') -Destination (Join-Path $outputRoot 'INSTALL_TR.md') -Force

# 6. Content scan. Fail on test, credential, or private-data markers.
$forbiddenNames = '(?i)(^|/)(tests?|node_modules|chef-workflow|\.git|\.agents|\.impeccable|\.vscode|evidence|uploads|menuler)(/|$)|\.credentials$|\.env$|\.zip$|\.har$|^config\.php$'
$forbiddenText = @('CHEF_TEST_', 'admin.credentials', 'u220042353', 'BEGIN PRIVATE KEY', 'BEGIN OPENSSH', '@gmail.com', 'ChefPass2026')
$credentialFile = Join-Path ([System.IO.Path]::GetTempPath()) 'chef-task05\private\admin.credentials'
$testSecrets = @()
if ([System.IO.File]::Exists($credentialFile)) {
    $json = Get-Content -Raw -LiteralPath $credentialFile | ConvertFrom-Json
    foreach ($property in $json.PSObject.Properties) {
        if ($property.Value -is [string] -and $property.Name -like '*password*' -and $property.Value.Length -ge 8) { $testSecrets += $property.Value }
    }
}
$report = foreach ($set in @(@{ Name = 'public_html.zip'; Entries = $public }, @{ Name = 'chef-private-setup.zip'; Entries = $private })) {
    foreach ($key in $set.Entries.Keys) {
        if ($key -match $forbiddenNames) { throw "Forbidden path in $($set.Name): $key" }
        $text = [System.IO.File]::ReadAllText($set.Entries[$key])
        foreach ($marker in $forbiddenText + $testSecrets) {
            if ($text.Contains($marker)) { throw "Forbidden content marker in $($set.Name): $key" }
        }
    }
    [pscustomobject]@{ Archive = $set.Name; Entries = $set.Entries.Count }
}

$result = [ordered]@{
    schemaVersion = $schemaVersion
    guestIndexSha256 = $guestHash
    testSecretMarkersChecked = $testSecrets.Count
    archives = @(
        [ordered]@{ file = 'public_html.zip'; entries = $public.Count; sha256 = Get-Sha256 $publicZip; bytes = (Get-Item $publicZip).Length }
        [ordered]@{ file = 'chef-private-setup.zip'; entries = $private.Count; sha256 = Get-Sha256 $privateZip; bytes = (Get-Item $privateZip).Length }
        [ordered]@{ file = 'INSTALL_TR.md'; entries = 1; sha256 = Get-Sha256 (Join-Path $outputRoot 'INSTALL_TR.md'); bytes = (Get-Item (Join-Path $outputRoot 'INSTALL_TR.md')).Length }
    )
    files = [ordered]@{
        public = [ordered]@{}
        private = [ordered]@{}
    }
}
foreach ($key in ($public.Keys | Sort-Object { $_ } -CaseSensitive)) { $result.files.public[$key] = Get-Sha256 $public[$key] }
foreach ($key in ($private.Keys | Sort-Object { $_ } -CaseSensitive)) { $result.files.private[$key] = Get-Sha256 $private[$key] }
$result | ConvertTo-Json -Depth 5 | Set-Content -LiteralPath (Join-Path $buildRoot 'build-info.json') -Encoding utf8NoBOM
$result.archives | ForEach-Object { [pscustomobject]$_ } | Format-Table -AutoSize | Out-String | Write-Host

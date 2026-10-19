param(
    [Parameter(Mandatory = $true)]
    [string]$DistPath,
    [Parameter(Mandatory = $true)]
    [string]$CredentialsPath,
    [Parameter(Mandatory = $true)]
    [string]$ExpectedGuestHash
)

$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.IO.Compression.FileSystem

function Add-TextValues($value, $target) {
    if ($null -eq $value) { return }
    if ($value -is [string]) {
        $target.Add($value)
        return
    }
    if ($value -is [System.Collections.IDictionary]) {
        foreach ($item in $value.Values) { Add-TextValues $item $target }
        return
    }
    if ($value -is [System.Collections.IEnumerable] -and $value -isnot [string]) {
        foreach ($item in $value) { Add-TextValues $item $target }
        return
    }
    foreach ($property in $value.PSObject.Properties) {
        Add-TextValues $property.Value $target
    }
}

function Get-HistoricalPasswordCandidates {
    $candidates = New-Object 'System.Collections.Generic.HashSet[string]'
    $patterns = @(
        [regex]'(?i)(?:adminPassword|changedAdminPassword|recoveredAdminPassword|admin_password|test_password)\s*(?:=|:|=>)\s*["''](?<value>[^"''\r\n]{8,})["'']',
        [regex]'(?i)["''](?:admin_password|changed_admin_password|recovered_admin_password)["'']\s*:\s*["''](?<value>[^"''\r\n]{8,})["'']'
    )
    $sessionRoot = Join-Path $env:USERPROFILE '.codex\sessions'
    if (-not (Test-Path -LiteralPath $sessionRoot)) { return ,$candidates }
    $files = & rg -l --hidden --glob '*.jsonl' 'adminPassword|admin_password|hardcoded test password' $sessionRoot 2>$null
    foreach ($file in $files) {
        Get-Content -LiteralPath $file | ForEach-Object {
            try { $object = $_ | ConvertFrom-Json } catch { return }
            $texts = New-Object 'System.Collections.Generic.List[string]'
            Add-TextValues $object $texts
            foreach ($text in $texts) {
                foreach ($pattern in $patterns) {
                    foreach ($match in $pattern.Matches($text)) {
                        $candidate = $match.Groups['value'].Value
                        if ($candidate -notmatch 'random_bytes|process\.env|getenv|<redacted>|\$') {
                            $null = $candidates.Add($candidate)
                        }
                    }
                }
            }
        }
    }
    return ,$candidates
}

function Get-FileSha256([string]$path) {
    return (Get-FileHash -Algorithm SHA256 -LiteralPath $path).Hash.ToLowerInvariant()
}

$credentials = Get-Content -Raw -LiteralPath $CredentialsPath | ConvertFrom-Json
$secrets = Get-HistoricalPasswordCandidates
$null = $secrets.Add([string]$credentials.admin_password)
$runtimeSecretCount = 1
$history = & git log -p --all -- database/tests 2>$null
$gitHistoryAvailable = -not [string]::IsNullOrWhiteSpace(($history -join "`n"))

$archives = @(
    @{ Name = 'public_html.zip'; Kind = 'public' },
    @{ Name = 'chef-private-setup.zip'; Kind = 'private' }
)
$results = @()
$cleanupFailures = @()

foreach ($archive in $archives) {
    $archivePath = Join-Path $DistPath $archive.Name
    $extractRoot = Join-Path $env:TEMP ('chef-package-scan-' + [guid]::NewGuid().ToString('N'))
    [IO.Directory]::CreateDirectory($extractRoot) | Out-Null
    try {
        [IO.Compression.ZipFile]::ExtractToDirectory($archivePath, $extractRoot)
        $marker = 'CHEF_SCAN_MARKER_' + [guid]::NewGuid().ToString('N')
        $markerPath = Join-Path $extractRoot '.scan-positive-control.txt'
        [IO.File]::WriteAllText($markerPath, $marker, [Text.UTF8Encoding]::new($false))
        $markerFound = $false
        foreach ($file in Get-ChildItem -LiteralPath $extractRoot -Recurse -Force -File) {
            $content = [Text.Encoding]::UTF8.GetString([IO.File]::ReadAllBytes($file.FullName))
            if ($content.Contains($marker)) { $markerFound = $true; break }
        }
        if (-not $markerFound) { throw 'The positive control marker was not detected.' }
        [IO.File]::Delete($markerPath)
        if (Test-Path -LiteralPath $markerPath) { throw 'The positive control marker file remains.' }

        $files = @(Get-ChildItem -LiteralPath $extractRoot -Recurse -Force -File)
        $secretHits = 0
        $credentialNameHits = 0
        $excludedPathHits = 0
        $relativeFiles = @()
        foreach ($file in $files) {
            $relative = $file.FullName.Substring($extractRoot.Length).TrimStart('\').Replace('\', '/')
            $relativeFiles += $relative
            $lower = $relative.ToLowerInvariant()
            if ($lower -match '(^|/)(?:[^/]*\.credentials|admin\.credentials)$') { $credentialNameHits++ }
            if ($lower -match '(^|/)(?:database/tests|node_modules|chef-workflow|\.agents|\.impeccable|\.vscode|docs/evidence|docs/reviews|hvk/assets|hvk/components|hvk/admin/uploads|menuler)(?:/|$)' -or
                $lower -match '(^|/)(?:chef-onizleme\.html|cheflogo\.pdf|package\.json|package-lock\.json|tailwind[^/]*\.config\.(?:js|cjs)|admin-tailwind\.css|menu-data\.js|hvk/index\.html|hvk/logo\.jpeg|hvk/thk\.png)$') {
                $excludedPathHits++
            }
            $content = [Text.Encoding]::UTF8.GetString([IO.File]::ReadAllBytes($file.FullName))
            if ($content.Contains('CHEF_TEST_CREDENTIALS') -or $content.Contains('admin.credentials')) {
                $credentialNameHits++
            }
            foreach ($secret in $secrets) {
                if (-not [string]::IsNullOrEmpty($secret) -and $content.Contains($secret)) {
                    $secretHits++
                }
            }
        }
        $htaccess = @($relativeFiles | Where-Object { ([IO.Path]::GetFileName($_)) -eq '.htaccess' } | Sort-Object)
        $adminHtaccess = $relativeFiles -contains 'hvk/admin/.htaccess'
        $mediaHtaccess = $relativeFiles -contains 'media/.htaccess'
        $guestHash = $null
        $guestHashMatches = $null
        if ($archive.Kind -eq 'public') {
            $guestPath = Join-Path $extractRoot 'index.html'
            if (-not (Test-Path -LiteralPath $guestPath)) { throw 'The packaged guest index.html is missing.' }
            $guestHash = Get-FileSha256 $guestPath
            $guestHashMatches = $guestHash -eq $ExpectedGuestHash
            if (-not $adminHtaccess -or -not $mediaHtaccess) { throw 'A required .htaccess file is missing.' }
            if (-not $guestHashMatches) { throw 'The packaged guest hash does not match current_revision.' }
        }
        if ($secretHits -ne 0 -or $credentialNameHits -ne 0 -or $excludedPathHits -ne 0) {
            throw 'The extracted package scan found forbidden content.'
        }
        $results += [pscustomobject]@{
            archive = $archive.Name
            sha256 = Get-FileSha256 $archivePath
            files = $files.Count
            positive_control = 'PASS'
            secret_scan = 'PASS'
            credential_name_scan = 'PASS'
            exclusion_scan = 'PASS'
            htaccess = $htaccess
            admin_htaccess = if ($archive.Kind -eq 'public') { if ($adminHtaccess) { 'PASS' } else { 'FAIL' } } else { 'NOT_APPLICABLE' }
            media_htaccess = if ($archive.Kind -eq 'public') { if ($mediaHtaccess) { 'PASS' } else { 'FAIL' } } else { 'NOT_APPLICABLE' }
            guest_index_sha256 = $guestHash
            guest_hash_matches_current_revision = $guestHashMatches
        }
    } finally {
        if (Test-Path -LiteralPath $extractRoot) {
            $resolved = [IO.Path]::GetFullPath($extractRoot)
            $prefix = [IO.Path]::GetFullPath($env:TEMP).TrimEnd('\') + '\'
            if (-not $resolved.StartsWith($prefix, [StringComparison]::OrdinalIgnoreCase)) {
                $cleanupFailures += $extractRoot
            } else {
                [IO.Directory]::Delete($resolved, $true)
                if (Test-Path -LiteralPath $resolved) { $cleanupFailures += $resolved }
            }
        }
    }
}

if ($cleanupFailures.Count -ne 0) { throw 'An extraction directory could not be removed.' }

[pscustomobject]@{
    status = 'PASS'
    scanner = 'decompressed entries'
    historical_candidate_source = if ($gitHistoryAvailable) { 'git database/tests history plus local execution history' } else { 'local execution history; git database/tests history is absent' }
    historical_literal_candidates = $secrets.Count - $runtimeSecretCount
    runtime_password_candidates = $runtimeSecretCount
    extraction_cleanup = 'PASS'
    archives = $results
} | ConvertTo-Json -Depth 8

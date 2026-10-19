[CmdletBinding()]
param()

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$repoRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$stagingRoot = [System.IO.Path]::GetFullPath((Join-Path $repoRoot 'dist\hostinger-staging'))
$archivePaths = @(
    (Join-Path $stagingRoot 'public_html.zip'),
    (Join-Path $stagingRoot 'chef-private-setup.zip')
)

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

function Get-NormalizedEntryName {
    param([Parameter(Mandatory)][string]$EntryName)

    $normalized = $EntryName.Replace([char]92, [char]47)
    if ($normalized.StartsWith('/') -or $normalized -match '^[A-Za-z]:' -or $normalized.Contains([char]0)) {
        throw 'Arşiv mutlak veya geçersiz bir giriş yolu içeriyor.'
    }
    $segments = @($normalized.Split('/') | Where-Object { $_ -ne '' -and $_ -ne '.' })
    if ($segments.Count -eq 0 -or '..' -in $segments) {
        throw 'Arşiv traversal veya boş bir giriş yolu içeriyor.'
    }
    return $segments -join '/'
}

function Convert-StagingArchive {
    param([Parameter(Mandatory)][string]$ArchivePath)

    $resolvedArchive = [System.IO.Path]::GetFullPath($ArchivePath)
    if (-not $resolvedArchive.StartsWith($stagingRoot + [System.IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) {
        throw 'Arşiv staging paket dizininin dışında.'
    }
    if (-not [System.IO.File]::Exists($resolvedArchive)) {
        throw "Arşiv bulunamadı: $([System.IO.Path]::GetFileName($resolvedArchive))"
    }

    $temporaryPath = "$resolvedArchive.$([Guid]::NewGuid().ToString('N')).tmp"
    $source = $null
    $destinationStream = $null
    $destination = $null
    try {
        $source = [System.IO.Compression.ZipFile]::OpenRead($resolvedArchive)
        $destinationStream = [System.IO.FileStream]::new(
            $temporaryPath,
            [System.IO.FileMode]::CreateNew,
            [System.IO.FileAccess]::ReadWrite,
            [System.IO.FileShare]::None
        )
        $destination = [System.IO.Compression.ZipArchive]::new(
            $destinationStream,
            [System.IO.Compression.ZipArchiveMode]::Create,
            $true
        )
        $names = [System.Collections.Generic.HashSet[string]]::new([StringComparer]::Ordinal)
        foreach ($sourceEntry in @($source.Entries)) {
            $entryName = Get-NormalizedEntryName -EntryName $sourceEntry.FullName
            if (-not $names.Add($entryName)) {
                throw 'Arşiv normalize edildikten sonra tekrar eden bir giriş yolu içeriyor.'
            }
            $destinationEntry = $destination.CreateEntry($entryName, [System.IO.Compression.CompressionLevel]::Optimal)
            $destinationEntry.LastWriteTime = $sourceEntry.LastWriteTime
            $destinationEntry.ExternalAttributes = $sourceEntry.ExternalAttributes
            $inputStream = $sourceEntry.Open()
            $outputStream = $destinationEntry.Open()
            try {
                $inputStream.CopyTo($outputStream)
            }
            finally {
                $outputStream.Dispose()
                $inputStream.Dispose()
            }
        }
        $entryCount = $names.Count
        $destination.Dispose()
        $destination = $null
        $destinationStream.Dispose()
        $destinationStream = $null
        $source.Dispose()
        $source = $null

        $verification = [System.IO.Compression.ZipFile]::OpenRead($temporaryPath)
        try {
            $verifiedEntries = @($verification.Entries)
            $unsafeEntries = @($verifiedEntries | Where-Object {
                $_.FullName.Contains([char]92) -or
                $_.FullName.StartsWith('/') -or
                $_.FullName -match '^[A-Za-z]:' -or
                '..' -in @($_.FullName.Split('/'))
            })
            if ($verifiedEntries.Count -ne $entryCount -or $unsafeEntries.Count -ne 0) {
                throw 'Normalize edilmiş arşiv doğrulaması başarısız.'
            }
        }
        finally {
            $verification.Dispose()
        }

        [System.IO.File]::Move($temporaryPath, $resolvedArchive, $true)
        return [pscustomobject]@{
            Archive = [System.IO.Path]::GetFileName($resolvedArchive)
            Entries = $entryCount
            Sha256 = (Get-FileHash -LiteralPath $resolvedArchive -Algorithm SHA256).Hash.ToLowerInvariant()
        }
    }
    finally {
        if ($null -ne $destination) { $destination.Dispose() }
        if ($null -ne $destinationStream) { $destinationStream.Dispose() }
        if ($null -ne $source) { $source.Dispose() }
        if ([System.IO.File]::Exists($temporaryPath)) { [System.IO.File]::Delete($temporaryPath) }
    }
}

$results = @(foreach ($archivePath in $archivePaths) {
    Convert-StagingArchive -ArchivePath $archivePath
})
$results | ConvertTo-Json -Depth 3

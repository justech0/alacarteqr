[CmdletBinding()]
param(
    [string]$PhpExe = 'C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe',
    [string]$MariaDbImage = 'mariadb:11.8.9',
    [int]$Port = 33317
)

# Builds the phpMyAdmin installation SQL from the real PHP migrations and the Chef seed.
# It uses a disposable MariaDB container. It never connects to a hosted database.

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$PSNativeCommandArgumentPassing = 'Standard'

$repoRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$schemaVersion = [regex]::Match((Get-Content -Raw (Join-Path $repoRoot 'database\lib.php')), "CHEF_SCHEMA_VERSION = '(\d+)'").Groups[1].Value
if ($schemaVersion -eq '') { throw 'CHEF_SCHEMA_VERSION was not found.' }
$outputDir = Join-Path $repoRoot 'dist\release\build\sql'
$outputFile = Join-Path $outputDir "chef-install-$schemaVersion.sql"
$container = 'chef-release-sql-' + [Guid]::NewGuid().ToString('N').Substring(0, 8)
$rootPassword = [Convert]::ToHexString([System.Security.Cryptography.RandomNumberGenerator]::GetBytes(24)).ToLowerInvariant()
$workDir = Join-Path ([System.IO.Path]::GetTempPath()) $container
New-Item -ItemType Directory -Force $workDir, $outputDir | Out-Null

function Invoke-Checked {
    param([string]$File, [string[]]$Arguments, [hashtable]$Environment = @{})
    $saved = @{}
    foreach ($key in $Environment.Keys) { $saved[$key] = [Environment]::GetEnvironmentVariable($key); [Environment]::SetEnvironmentVariable($key, $Environment[$key]) }
    try {
        $output = & $File @Arguments 2>&1
        if ($LASTEXITCODE -ne 0) { throw "$([System.IO.Path]::GetFileName($File)) failed: $($output -join ' ')" }
        return $output
    }
    finally {
        foreach ($key in $saved.Keys) { [Environment]::SetEnvironmentVariable($key, $saved[$key]) }
    }
}

try {
    Invoke-Checked docker @('run', '-d', '--rm', '--name', $container, '-e', "MARIADB_ROOT_PASSWORD=$rootPassword", '-p', "127.0.0.1:${Port}:3306", $MariaDbImage) | Out-Null
    # The entrypoint runs a temporary server first. Retry until the final server accepts the root password over TCP.
    $ready = $false
    for ($i = 0; $i -lt 90; $i++) {
        & docker exec $container sh -c 'mariadb -h127.0.0.1 -uroot -p"$MARIADB_ROOT_PASSWORD" -e "CREATE DATABASE chef_release CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"' 2>$null | Out-Null
        if ($LASTEXITCODE -eq 0) { $ready = $true; break }
        Start-Sleep -Milliseconds 1000
    }
    if (-not $ready) { throw 'The disposable MariaDB container did not start.' }

    $configPath = Join-Path $workDir 'config.php'
    $dsn = "mysql:host=127.0.0.1;port=$Port;dbname=chef_release;charset=utf8mb4"
    Set-Content -LiteralPath $configPath -Encoding utf8NoBOM -Value @"
<?php
return ['database' => ['dsn' => '$dsn', 'username' => 'root', 'password' => '$rootPassword']];
"@
    $env = @{ CHEF_DB_CONFIG = $configPath }
    $cli = Join-Path $repoRoot 'database\cli.php'
    Invoke-Checked $PhpExe @($cli, 'migrate') $env | Out-Null
    Invoke-Checked $PhpExe @($cli, 'seed') $env | Out-Null
    Invoke-Checked $PhpExe @($cli, 'verify') $env | Out-Null

    # Login throttles and managed uploads hold runtime state only. Export their structure without rows.
    $dumpCommand = 'mariadb-dump -uroot -p"$MARIADB_ROOT_PASSWORD" --single-transaction --skip-dump-date --skip-comments --no-tablespaces --skip-add-locks --skip-triggers --default-character-set=utf8mb4 --ignore-table-data=chef_release.admin_login_throttles --ignore-table-data=chef_release.managed_uploads chef_release'
    $dump = Invoke-Checked docker @('exec', $container, 'sh', '-c', $dumpCommand)
    $lines = @($dump | ForEach-Object { [string]$_ } | Where-Object { $_ -notmatch '^/\*M!999999\\- enable the sandbox mode \*/' })
    $body = ($lines -join "`n")
    foreach ($forbidden in @('uca1400', 'utf8mb4_0900', 'DEFINER=')) {
        if ($body.Contains($forbidden)) { throw "The dump contains an incompatible token: $forbidden" }
    }
    if ($body -match '(?im)^INSERT INTO `admins`') { throw 'The dump contains administrator rows.' }

    $header = @(
        "-- Chef A La Carte installation SQL. Schema version $schemaVersion.",
        '-- Source: database/migrations and database/seed-data.php through database/cli.php.',
        '-- Import into a new, empty database only. This file drops no existing table.',
        '-- It contains no administrator, feedback, or upload rows.',
        'SET NAMES utf8mb4;',
        ''
    ) -join "`n"
    # Remove DROP TABLE statements so that an import into a populated database fails instead of deleting data.
    $body = [regex]::Replace($body, '(?m)^DROP TABLE IF EXISTS `[^`]+`;\r?\n', '')
    [System.IO.File]::WriteAllText($outputFile, $header + $body.TrimEnd() + "`n", [System.Text.UTF8Encoding]::new($false))
    [pscustomobject]@{
        File = (Resolve-Path -Relative $outputFile)
        SchemaVersion = $schemaVersion
        Engine = $MariaDbImage
        Sha256 = (Get-FileHash -LiteralPath $outputFile -Algorithm SHA256).Hash.ToLowerInvariant()
    } | ConvertTo-Json
}
finally {
    & docker rm -f $container 2>$null | Out-Null
    if (Test-Path -LiteralPath $workDir) { Remove-Item -LiteralPath $workDir -Recurse -Force }
}

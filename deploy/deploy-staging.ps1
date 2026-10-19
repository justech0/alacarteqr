[CmdletBinding()]
param(
    [switch]$LocalValidationOnly,
    [switch]$ConfirmDocumentRoot,
    [switch]$SkipOwner,
    [switch]$ResumeStep6,
    [string]$ExistingBackupPath,
    [string]$ExpectedBackupSha256
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
if ($PSVersionTable.PSVersion.Major -lt 7) {
    throw 'Bu betik PowerShell 7 veya daha yeni bir sürüm gerektirir.'
}
$PSNativeCommandArgumentPassing = 'Standard'

$TargetHost = '82.198.227.148'
$SshPort = 65002
$SshUser = 'u220042353'
$SiteHost = 'taslak.bilincreklam.com'
$DatabaseName = 'u220042353_chefala'
$DatabaseUser = 'u220042353_chefala'
$RemotePrivateRoot = '/home/u220042353/chef-private'
$RemoteUploadRoot = '/home/u220042353/.chef-staging-deploy'
$RemoteConfigPath = "$RemotePrivateRoot/config.php"
$RemoteHelperPath = "$RemoteUploadRoot/config-helper.php"
$KeyPath = Join-Path $HOME '.ssh\chef-hostinger-staging_ed25519'
$RepoRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$PublicArchive = Join-Path $RepoRoot 'dist\hostinger-staging\public_html.zip'
$PrivateArchive = Join-Path $RepoRoot 'dist\hostinger-staging\chef-private-setup.zip'
$ReadonlyCheck = Join-Path $RepoRoot 'database\tests\readonly-check.php'
$ReviewPath = Join-Path $RepoRoot 'docs\reviews\staging-deploy.md'
$ProjectPath = Join-Path $RepoRoot 'PROJECT.md'
$RemoteTarget = "$SshUser@$TargetHost"
$Script:CurrentStep = 0
$Script:RemotePhp = 'php'
$Script:DocumentRoot = $null
$Script:FailureMessage = $null
$Script:KeyReady = $false
$Script:HarnessScenario = [Environment]::GetEnvironmentVariable('CHEF_LOCAL_HARNESS_SCENARIO', 'Process')
$Script:HarnessRoot = [Environment]::GetEnvironmentVariable('CHEF_LOCAL_HARNESS_ROOT', 'Process')
$Script:UseFakeNativeCommands = -not [string]::IsNullOrWhiteSpace($Script:HarnessScenario)

if ($ResumeStep6) {
    if ([string]::IsNullOrWhiteSpace($ExistingBackupPath) -or $ExpectedBackupSha256 -notmatch '^[a-fA-F0-9]{64}$') {
        throw 'ResumeStep6 için mevcut backup yolu ve SHA-256 değeri gereklidir.'
    }
    if ($ExistingBackupPath -notmatch '^/home/u220042353/backups/[A-Za-z0-9._-]+\.tar\.gz$') {
        throw 'Mevcut backup yolu izin verilen staging backup dizininin dışında.'
    }
    $ExpectedBackupSha256 = $ExpectedBackupSha256.ToLowerInvariant()
}
elseif (-not [string]::IsNullOrWhiteSpace($ExistingBackupPath) -or -not [string]::IsNullOrWhiteSpace($ExpectedBackupSha256)) {
    throw 'Backup resume parametreleri yalnız ResumeStep6 ile kullanılabilir.'
}

if ($Script:UseFakeNativeCommands) {
    $allowedHarnessScenarios = @('pass', 'resume-pass', 'missing-module', 'no-missing-module', 'empty-document-root', 'curl-503', 'curl-test-executes')
    if ($Script:HarnessScenario -notin $allowedHarnessScenarios -or [string]::IsNullOrWhiteSpace($Script:HarnessRoot)) {
        throw 'Geçersiz yerel harness yapılandırması.'
    }
    $resolvedHarnessRoot = [System.IO.Path]::GetFullPath($Script:HarnessRoot)
    $resolvedSystemTemporary = [System.IO.Path]::GetFullPath([System.IO.Path]::GetTempPath())
    if (-not $resolvedHarnessRoot.StartsWith($resolvedSystemTemporary, [StringComparison]::OrdinalIgnoreCase)) {
        throw 'Yerel harness dizini sistem geçici dizini içinde olmalıdır.'
    }
    $KeyPath = Join-Path $resolvedHarnessRoot '.ssh\chef-hostinger-staging_ed25519'
    $ReviewPath = Join-Path $resolvedHarnessRoot 'docs\reviews\staging-deploy.md'
    $ProjectPath = Join-Path $resolvedHarnessRoot 'PROJECT.md'
}

$Script:Steps = @{
    1 = [ordered]@{ Name = 'SSH anahtarı'; Status = 'NOT RUN'; Detail = 'Çalıştırılmadı.' }
    2 = [ordered]@{ Name = 'PHP ve uzantılar'; Status = 'NOT RUN'; Detail = 'Çalıştırılmadı.' }
    3 = [ordered]@{ Name = 'Belge kökü'; Status = 'NOT RUN'; Detail = 'Çalıştırılmadı.' }
    4 = [ordered]@{ Name = 'Veritabanı adı'; Status = 'NOT RUN'; Detail = 'Çalıştırılmadı.' }
    5 = [ordered]@{ Name = 'Uzak yedek'; Status = 'NOT RUN'; Detail = 'Çalıştırılmadı.' }
    6 = [ordered]@{ Name = 'Arşiv aktarımı'; Status = 'NOT RUN'; Detail = 'Çalıştırılmadı.' }
    7 = [ordered]@{ Name = '.htaccess kontrolü'; Status = 'NOT RUN'; Detail = 'Çalıştırılmadı.' }
    8 = [ordered]@{ Name = 'Özel yapılandırma'; Status = 'NOT RUN'; Detail = 'Çalıştırılmadı.' }
    9 = [ordered]@{ Name = 'CHEF_CONFIG_FILE'; Status = 'NOT RUN'; Detail = 'Çalıştırılmadı.' }
    10 = [ordered]@{ Name = 'Migration ve hazırlık'; Status = 'NOT RUN'; Detail = 'Çalıştırılmadı.' }
    11 = [ordered]@{ Name = 'Sahip kurtarma'; Status = 'NOT RUN'; Detail = 'Çalıştırılmadı.' }
    12 = [ordered]@{ Name = 'Medya cleanup cron'; Status = 'NOT RUN'; Detail = 'Çalıştırılmadı.' }
    13 = [ordered]@{ Name = 'HTTPS kontrolleri'; Status = 'NOT RUN'; Detail = 'Çalıştırılmadı.' }
    14 = [ordered]@{ Name = 'Readonly kontrolü'; Status = 'NOT RUN'; Detail = 'Çalıştırılmadı.' }
    15 = [ordered]@{ Name = 'Raporlama'; Status = 'NOT RUN'; Detail = 'Çalıştırılmadı.' }
}

function Set-StepResult {
    param(
        [Parameter(Mandatory)][int]$Number,
        [Parameter(Mandatory)][ValidateSet('PASS', 'FAIL', 'MANUAL', 'NOT RUN')][string]$Status,
        [Parameter(Mandatory)][string]$Detail
    )

    $Script:Steps[$Number].Status = $Status
    $Script:Steps[$Number].Detail = $Detail
}

function Start-Step {
    param([Parameter(Mandatory)][int]$Number)

    $Script:CurrentStep = $Number
    Write-Host "[$Number/15] $($Script:Steps[$Number].Name)"
}

function Invoke-NativeCapture {
    param(
        [Parameter(Mandatory)][string]$Executable,
        [Parameter(Mandatory)][AllowEmptyString()][string[]]$ArgumentList,
        [AllowNull()][AllowEmptyString()][string]$StandardInput = $null
    )

    if ($Script:UseFakeNativeCommands -and $Executable -in @('ssh.exe', 'scp.exe', 'curl.exe')) {
        $fakeCommand = Get-Command $Executable -CommandType Function -ErrorAction Stop
        return & $fakeCommand -ArgumentList $ArgumentList -StandardInput $StandardInput
    }

    $startInfo = [System.Diagnostics.ProcessStartInfo]::new()
    $startInfo.FileName = $Executable
    $startInfo.UseShellExecute = $false
    $startInfo.CreateNoWindow = $true
    $startInfo.RedirectStandardOutput = $true
    $startInfo.RedirectStandardError = $true
    $startInfo.RedirectStandardInput = $null -ne $StandardInput
    foreach ($argument in $ArgumentList) {
        $startInfo.ArgumentList.Add($argument)
    }

    $process = [System.Diagnostics.Process]::new()
    $process.StartInfo = $startInfo
    try {
        if (-not $process.Start()) {
            throw "Yerel komut başlatılamadı: $Executable"
        }
        $stdoutTask = $process.StandardOutput.ReadToEndAsync()
        $stderrTask = $process.StandardError.ReadToEndAsync()
        if ($null -ne $StandardInput) {
            $process.StandardInput.Write($StandardInput)
            $process.StandardInput.Close()
        }
        $process.WaitForExit()
        $stdout = $stdoutTask.GetAwaiter().GetResult()
        $stderr = $stderrTask.GetAwaiter().GetResult()
        $text = $stdout
        if ($stderr.Length -gt 0) {
            if ($text.Length -gt 0 -and -not $text.EndsWith([Environment]::NewLine, [StringComparison]::Ordinal)) {
                $text += [Environment]::NewLine
            }
            $text += $stderr
        }
        $trimmedStdout = $stdout.TrimEnd("`r", "`n")
        $trimmedStderr = $stderr.TrimEnd("`r", "`n")
        $trimmedText = $text.TrimEnd("`r", "`n")
        $output = if ($trimmedText.Length -eq 0) { @() } else { @($trimmedText -split '\r?\n') }
        return [pscustomobject]@{
            ExitCode = $process.ExitCode
            Output = $output
            Text = $trimmedText
            Stdout = $trimmedStdout
            Stderr = $trimmedStderr
        }
    }
    finally {
        $process.Dispose()
    }
}

function Get-SshArguments {
    param([Parameter(Mandatory)][string]$RemoteCommand)

    return @(
        '-i', $KeyPath,
        '-p', [string]$SshPort,
        '-o', 'BatchMode=yes',
        '-o', 'IdentitiesOnly=yes',
        '-o', 'StrictHostKeyChecking=accept-new',
        $RemoteTarget,
        $RemoteCommand
    )
}

function Invoke-SshCapture {
    param([Parameter(Mandatory)][string]$RemoteCommand)

    return Invoke-NativeCapture -Executable 'ssh.exe' -ArgumentList (Get-SshArguments -RemoteCommand $RemoteCommand)
}

function Invoke-SshWithInput {
    param(
        [Parameter(Mandatory)][string]$RemoteCommand,
        [Parameter(Mandatory)][string]$InputText
    )

    $sshArguments = Get-SshArguments -RemoteCommand $RemoteCommand
    return Invoke-NativeCapture -Executable 'ssh.exe' -ArgumentList $sshArguments -StandardInput $InputText
}

function Invoke-Scp {
    param(
        [Parameter(Mandatory)][string]$LocalPath,
        [Parameter(Mandatory)][string]$RemotePath
    )

    return Invoke-NativeCapture -Executable 'scp.exe' -ArgumentList @(
        '-i', $KeyPath,
        '-P', [string]$SshPort,
        '-o', 'BatchMode=yes',
        '-o', 'IdentitiesOnly=yes',
        '-o', 'StrictHostKeyChecking=accept-new',
        $LocalPath,
        "${RemoteTarget}:$RemotePath"
    )
}

function New-SshAskPassHelper {
    param([Parameter(Mandatory)][string]$Path)

    $content = '@echo off' + "`r`n" +
        'pwsh.exe -NoProfile -NonInteractive -Command "[Console]::Out.Write([Environment]::GetEnvironmentVariable(''CHEF_SSH_PASSWORD'', ''Process''))"' + "`r`n"
    [System.IO.File]::WriteAllText($Path, $content, [System.Text.ASCIIEncoding]::new())
}

function Get-ManualKeyInstallCommand {
    param([Parameter(Mandatory)][string]$InstallCommand)

    $encodedCommand = [Convert]::ToBase64String([System.Text.Encoding]::UTF8.GetBytes($InstallCommand))
    $remoteCommand = "printf '%s' '$encodedCommand' | base64 -d | sh"
    return "ssh.exe -tt -p $SshPort -o NumberOfPasswordPrompts=1 -o PubkeyAuthentication=no -o PreferredAuthentications=password -o StrictHostKeyChecking=accept-new $RemoteTarget `"$remoteCommand`""
}

function Assert-NativeSuccess {
    param(
        [Parameter(Mandatory)]$Result,
        [Parameter(Mandatory)][string]$Message
    )

    if ($Result.ExitCode -ne 0) {
        throw $Message
    }
}

function Assert-SafeRemotePath {
    param([Parameter(Mandatory)][string]$Path)

    if ($Path -notmatch '^/[A-Za-z0-9._/-]+$' -or $Path.Contains('/../') -or $Path.EndsWith('/..')) {
        throw 'Uzak sunucu güvenli olmayan bir yol döndürdü.'
    }
}

function Get-SafeZipArchiveInfo {
    param([Parameter(Mandatory)][string]$Path)

    Add-Type -AssemblyName System.IO.Compression.FileSystem
    $archive = [System.IO.Compression.ZipFile]::OpenRead($Path)
    try {
        $entries = @($archive.Entries)
        $names = [System.Collections.Generic.HashSet[string]]::new([StringComparer]::Ordinal)
        foreach ($entry in $entries) {
            $name = $entry.FullName
            $segments = @($name.Split('/') | Where-Object { $_ -ne '' -and $_ -ne '.' })
            if ($name.Contains([char]92) -or $name.StartsWith('/') -or $name -match '^[A-Za-z]:' -or
                $name.Contains([char]0) -or $segments.Count -eq 0 -or '..' -in $segments) {
                throw "Güvensiz ZIP giriş yolu: $([System.IO.Path]::GetFileName($Path))"
            }
            $normalized = $segments -join '/'
            if (-not $names.Add($normalized)) {
                throw "Tekrarlanan ZIP giriş yolu: $([System.IO.Path]::GetFileName($Path))"
            }
        }
        return [pscustomobject]@{
            Entries = $entries.Count
            Sha256 = (Get-FileHash -LiteralPath $Path -Algorithm SHA256).Hash.ToLowerInvariant()
        }
    }
    finally {
        $archive.Dispose()
    }
}

function Get-SanitizedNativeFailure {
    param(
        [Parameter(Mandatory)][string]$Label,
        [Parameter(Mandatory)]$Result
    )

    $stderr = if ($Result.PSObject.Properties.Name -contains 'Stderr') { [string]$Result.Stderr } else { '' }
    if ([string]::IsNullOrWhiteSpace($stderr)) {
        $stderr = 'stderr boş'
    }
    $stderr = $stderr.Replace("`r", ' ').Replace("`n", ' ')
    $stderr = [regex]::Replace($stderr, '/home/u220042353/[^\s:]+\.zip', '<archive>')
    $stderr = [regex]::Replace($stderr, '/home/u220042353(?:/[A-Za-z0-9._-]+)+', '~/<path>')
    $stderr = [regex]::Replace($stderr, '[\x00-\x1F\x7F]', ' ').Trim()
    if ($stderr.Length -gt 400) {
        $stderr = $stderr.Substring(0, 400)
    }
    return "$Label exit=$($Result.ExitCode); stderr=$stderr"
}

function Get-PlainText {
    param([Parameter(Mandatory)][Security.SecureString]$SecureValue)

    $pointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($SecureValue)
    try {
        return [Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer)
    }
    finally {
        [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer)
    }
}

function Invoke-HttpRequest {
    param([Parameter(Mandatory)][string]$Url)

    $bodyPath = Join-Path ([System.IO.Path]::GetTempPath()) ("chef-curl-{0}.tmp" -f [Guid]::NewGuid().ToString('N'))
    try {
        $result = Invoke-NativeCapture -Executable 'curl.exe' -ArgumentList @(
            '--silent', '--show-error', '--location',
            '--output', $bodyPath,
            '--write-out', '%{http_code}',
            '--connect-timeout', '20',
            '--max-time', '60',
            $Url
        )
        $body = if (Test-Path -LiteralPath $bodyPath) { [System.IO.File]::ReadAllText($bodyPath) } else { '' }
        return [pscustomobject]@{
            ExitCode = $result.ExitCode
            StatusCode = if ($result.Text -match '(\d{3})\s*$') { [int]$Matches[1] } else { 0 }
            Body = $body
        }
    }
    finally {
        if (Test-Path -LiteralPath $bodyPath) {
            Remove-Item -LiteralPath $bodyPath -Force
        }
    }
}

function Write-DeploymentRecords {
    param([Parameter(Mandatory)][ValidateSet('PASS', 'FAIL')][string]$OverallStatus)

    $timestamp = [DateTimeOffset]::Now.ToString('yyyy-MM-dd HH:mm:ss zzz')
    $rows = foreach ($number in ($Script:Steps.Keys | Sort-Object)) {
        $step = $Script:Steps[$number]
        $detail = ([string]$step.Detail).Replace('|', '\|').Replace("`r", ' ').Replace("`n", ' ')
        "| $number | $($step.Name) | $($step.Status) | $detail |"
    }

    $review = @"
# Staging deployment review

- Tarih: $timestamp
- Hedef: https://$SiteHost
- Sonuç: **$OverallStatus**
- Kapsam: Hostinger staging kurulumu. Gizli değer kaydedilmedi.

| Adım | Kontrol | Sonuç | Kanıt özeti |
|---:|---|---|---|
$($rows -join [Environment]::NewLine)

## Notlar

- Bu rapor parola, özel anahtar, yapılandırma içeriği veya oturum verisi içermez.
- Task 12 durumu bu işlem tarafından değiştirilmez.
- Staging testleri bitince hPanel Remote MySQL kaydı kaldırılmalıdır.
"@

    $reviewDirectory = Split-Path -Parent $ReviewPath
    if (-not (Test-Path -LiteralPath $reviewDirectory)) {
        New-Item -ItemType Directory -Path $reviewDirectory | Out-Null
    }
    [System.IO.File]::WriteAllText($ReviewPath, $review, [System.Text.UTF8Encoding]::new($false))

    $project = [System.IO.File]::ReadAllText($ProjectPath)
    $block = @"
<!-- staging-deploy:start -->
Staging deploy sonucu: $OverallStatus. Son çalışma: $timestamp.
Kanıt: docs/reviews/staging-deploy.md.
Task 12 durumu değiştirilmedi. Staging sonrası Remote MySQL kaydı kaldırılmalıdır.
<!-- staging-deploy:end -->
"@
    $pattern = '(?s)<!-- staging-deploy:start -->.*?<!-- staging-deploy:end -->'
    if ([regex]::IsMatch($project, $pattern)) {
        $project = [regex]::Replace($project, $pattern, [System.Text.RegularExpressions.MatchEvaluator]{ param($match) $block }, 1)
    }
    elseif ($project -match '(?m)^## Task queue\s*$') {
        $project = [regex]::Replace($project, '(?m)^## Task queue\s*$', "$block`r`n`r`n## Task queue", 1)
    }
    else {
        $project = $project.TrimEnd() + "`r`n`r`n$block`r`n"
    }
    [System.IO.File]::WriteAllText($ProjectPath, $project, [System.Text.UTF8Encoding]::new($false))
}

function Show-ResultTable {
    $table = foreach ($number in ($Script:Steps.Keys | Sort-Object)) {
        [pscustomobject]@{
            Step = $number
            Check = $Script:Steps[$number].Name
            Result = $Script:Steps[$number].Status
        }
    }
    $table | Format-Table -AutoSize
}

function Show-PrivateLogTail {
    if (-not $Script:KeyReady) {
        Write-Host 'Özel günlük okunamadı: SSH anahtar bağlantısı hazır değil.'
        return
    }

    $tailResult = Invoke-SshCapture -RemoteCommand "test -f $RemotePrivateRoot/logs/app.log && tail -n 20 $RemotePrivateRoot/logs/app.log"
    if ($tailResult.ExitCode -eq 0) {
        Write-Host 'Özel günlüğün son 20 satırı:'
        Write-Host $tailResult.Text
    }
    else {
        Write-Host 'Özel günlük bulunamadı veya okunamadı.'
    }
}

# CONFIG_HELPER_START
$ConfigHelperPhp = @'
<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli' || count($argv) !== 8) {
    fwrite(STDERR, "FAIL\n");
    exit(2);
}

[$script, $action, $host, $databaseName, $databaseUser, $documentRoot, $privateRoot, $configPath] = $argv;
$password = rtrim((string)stream_get_contents(STDIN), "\r\n");

function fail_config(): never
{
    fwrite(STDERR, "FAIL\n");
    exit(1);
}

try {
    if (!in_array($action, ['test', 'write'], true) || $password === '') {
        fail_config();
    }
    foreach ([$documentRoot, $privateRoot, $configPath] as $path) {
        if (!str_starts_with($path, '/') || str_contains($path, "\0") || str_contains($path, '/../')) {
            fail_config();
        }
    }
    $documentReal = realpath($documentRoot);
    $privateReal = realpath($privateRoot);
    if ($documentReal === false || $privateReal === false || str_starts_with($privateReal . '/', rtrim($documentReal, '/') . '/')) {
        fail_config();
    }

    $dsn = sprintf('mysql:host=%s;port=3306;dbname=%s;charset=utf8mb4', $host, $databaseName);
    $pdo = new PDO($dsn, $databaseUser, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->query('SELECT 1')->fetchColumn();
    $pdo = null;

    if ($action === 'test') {
        fwrite(STDOUT, "PASS\n");
        exit(0);
    }

    $existing = [];
    if (is_file($configPath) && !is_link($configPath)) {
        $loaded = require $configPath;
        if (is_array($loaded)) {
            $existing = $loaded;
        }
    } elseif (file_exists($configPath) || is_link($configPath)) {
        fail_config();
    }

    $examplePath = $privateRoot . '/database/config.example.php';
    if (!is_file($examplePath) || is_link($examplePath)) {
        fail_config();
    }
    $config = require $examplePath;
    if (!is_array($config)) {
        fail_config();
    }

    $hmacKey = $existing['security']['throttle']['hmac_key'] ?? '';
    if (!is_string($hmacKey) || strlen($hmacKey) < 32) {
        $hmacKey = bin2hex(random_bytes(32));
    }
    $dummyHash = $existing['security']['throttle']['dummy_password_hash'] ?? '';
    if (!is_string($dummyHash) || password_get_info($dummyHash)['algo'] === null) {
        $dummyHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
    }
    unset($existing);

    $directories = [
        $privateRoot,
        $privateRoot . '/logs',
        $privateRoot . '/media',
        $privateRoot . '/tmp',
        $privateRoot . '/tmp/media',
    ];
    foreach ($directories as $directory) {
        if (!is_dir($directory) && !mkdir($directory, 0700, true)) {
            fail_config();
        }
        if (is_link($directory) || !chmod($directory, 0700)) {
            fail_config();
        }
    }

    $logPath = $privateRoot . '/logs/app.log';
    if (is_link($logPath) || (file_exists($logPath) && !is_file($logPath))) {
        fail_config();
    }
    if (!file_exists($logPath)) {
        $handle = fopen($logPath, 'x');
        if ($handle === false) {
            fail_config();
        }
        fclose($handle);
    }
    if (!chmod($logPath, 0600)) {
        fail_config();
    }

    $config['database'] = ['dsn' => $dsn, 'username' => $databaseUser, 'password' => $password];
    $config['migration_database'] = ['dsn' => $dsn, 'username' => $databaseUser, 'password' => $password];
    $config['application'] = ['logo_url' => 'assets/chef-logo.svg'];
    $config['security'] = [
        'environment' => 'production',
        'trusted_proxies' => [],
        'session' => [
            'name' => 'CHEFADMIN', 'path' => '/hvk/admin', 'domain' => '', 'secure' => true,
            'idle_seconds' => 1800, 'absolute_seconds' => 28800,
        ],
        'throttle' => [
            'hmac_key' => $hmacKey, 'dummy_password_hash' => $dummyHash,
            'window_seconds' => 900, 'account_limit' => 5, 'address_limit' => 10,
            'block_seconds' => 900, 'stale_seconds' => 172800,
        ],
    ];
    $config['logging'] = ['file' => $logPath];
    $config['media'] = [
        'public_document_root' => $documentReal,
        'public_root' => $documentReal . '/media',
        'private_root' => $privateReal . '/media',
        'temporary_root' => $privateReal . '/tmp/media',
        'legacy_public_root' => $documentReal . '/hvk/admin/uploads',
        'public_base_url' => '/media',
        'max_bytes' => 4194304,
        'request_overhead_bytes' => 1048576,
        'max_pixels' => 12000000,
        'main_max_dimension' => 1200,
        'thumbnail_max_dimension' => 320,
        'webp_quality' => 80,
        'operation_ttl_seconds' => 3600,
    ];

    $contents = "<?php\ndeclare(strict_types=1);\n\nreturn " . var_export($config, true) . ";\n";
    $temporary = tempnam(dirname($configPath), '.config-');
    if ($temporary === false || file_put_contents($temporary, $contents, LOCK_EX) !== strlen($contents)) {
        fail_config();
    }
    if (!chmod($temporary, 0600) || !rename($temporary, $configPath) || !chmod($configPath, 0600)) {
        @unlink($temporary);
        fail_config();
    }
    $password = str_repeat("\0", strlen($password));
    unset($password, $contents, $config);
    fwrite(STDOUT, "PASS\n");
} catch (Throwable) {
    if (isset($password) && is_string($password)) {
        $password = str_repeat("\0", strlen($password));
    }
    fail_config();
}
'@
# CONFIG_HELPER_END

function New-HarnessNativeResult {
    param(
        [Parameter(Mandatory)][int]$ExitCode,
        [AllowEmptyCollection()][string[]]$Output = @(),
        [AllowEmptyString()][string]$Stderr = ''
    )

    $outputLines = @($Output)
    $stdout = $outputLines -join [Environment]::NewLine
    $text = $stdout
    if (-not [string]::IsNullOrEmpty($Stderr)) {
        if (-not [string]::IsNullOrEmpty($text)) { $text += [Environment]::NewLine }
        $text += $Stderr
    }
    return [pscustomobject]@{
        ExitCode = $ExitCode
        Output = $outputLines
        Text = $text
        Stdout = $stdout
        Stderr = $Stderr
    }
}

function Invoke-HarnessSsh {
    param(
        [Parameter(Mandatory)][AllowEmptyCollection()][string[]]$ArgumentList,
        [AllowNull()][AllowEmptyString()][string]$StandardInput = $null
    )

    $arguments = @($ArgumentList)
    if ($arguments.Count -eq 0) {
        return New-HarnessNativeResult -ExitCode 2 -Output @('missing remote command')
    }
    $remoteCommand = $arguments[-1]

    if ($remoteCommand -eq 'true') {
        return New-HarnessNativeResult -ExitCode 0
    }
    if ($remoteCommand -eq 'command -v php && command -v unzip') {
        return New-HarnessNativeResult -ExitCode 0 -Output @('/usr/bin/php', '/usr/bin/unzip')
    }
    if ($remoteCommand -eq 'ls /opt/alt 2>/dev/null || true') {
        $listing = if ($Script:HarnessScenario -eq 'no-missing-module') { @('php84') } else { @('php83', 'php84') }
        return New-HarnessNativeResult -ExitCode 0 -Output $listing
    }
    if ($remoteCommand -like 'for candidate in /opt/alt/php83*') {
        if ($Script:HarnessScenario -eq 'no-missing-module') {
            return New-HarnessNativeResult -ExitCode 1
        }
        return New-HarnessNativeResult -ExitCode 0 -Output @('/opt/alt/php83/usr/bin/php')
    }
    if ($remoteCommand -match '^(?<php>/[A-Za-z0-9._/-]+/php) -v$') {
        $version = if ($Matches.php -like '/opt/alt/php83/*') { 'PHP 8.3.33 (cli)' } else { 'PHP 8.4.26 (cli)' }
        return New-HarnessNativeResult -ExitCode 0 -Output @($version)
    }
    if ($remoteCommand -match '^/[A-Za-z0-9._/-]+/php -m$') {
        $modules = @('Core', 'PDO', 'pdo_mysql', 'mbstring', 'fileinfo')
        if ($Script:HarnessScenario -ne 'missing-module') {
            $modules += 'gd'
        }
        return New-HarnessNativeResult -ExitCode 0 -Output $modules
    }
    if ($remoteCommand -match 'function_exists\("imagewebp"\)') {
        return New-HarnessNativeResult -ExitCode 0
    }
    if ($remoteCommand -eq 'ls -la "$HOME/domains"') {
        return New-HarnessNativeResult -ExitCode 0 -Output @('drwx------ taslak.bilincreklam.com')
    }
    if ($remoteCommand -match "printf 'DOMAIN_ROOT=%s") {
        if ($Script:HarnessScenario -eq 'empty-document-root') {
            return New-HarnessNativeResult -ExitCode 0 -Output @(
                'DOMAIN_ROOT=/home/u220042353/domains/taslak.bilincreklam.com',
                'DOCUMENT_ROOT='
            )
        }
        return New-HarnessNativeResult -ExitCode 0 -Output @(
            'DOMAIN_ROOT=/home/u220042353/domains/taslak.bilincreklam.com',
            'DOCUMENT_ROOT=/home/u220042353/domains/taslak.bilincreklam.com/public_html'
        )
    }
    if ($remoteCommand -match 'BACKUP_SHA256=') {
        return New-HarnessNativeResult -ExitCode 0 -Output @(
            'BACKUP=/home/u220042353/backups/staging-harness.tar.gz',
            'BACKUP_SHA256=aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'
        )
    }
    if ($remoteCommand -match "printf 'BACKUP=%s") {
        return New-HarnessNativeResult -ExitCode 0 -Output @('BACKUP=/home/u220042353/backups/staging-harness.tar.gz')
    }
    if ($remoteCommand -match 'TEST_PUBLIC_EXIT=%s') {
        return New-HarnessNativeResult -ExitCode 0 -Output @(
            'TEST_PUBLIC_EXIT=0',
            'TEST_PRIVATE_EXIT=0',
            'VERIFY_COUNTS=33,9'
        )
    }
    if ($remoteCommand -match 'PUBLIC_MATCH=') {
        return New-HarnessNativeResult -ExitCode 0 -Output @('PUBLIC_MATCH=33')
    }
    if ($remoteCommand -match '/config-helper\.php (test|write) ') {
        return New-HarnessNativeResult -ExitCode 0 -Output @('PASS')
    }
    if ($remoteCommand -match 'readonly-check\.php" row-counts') {
        return New-HarnessNativeResult -ExitCode 0 -Output @('{"check":"row-counts","status":"PASS"}')
    }
    if ($remoteCommand -match 'tail -n 20 .*/logs/app\.log') {
        return New-HarnessNativeResult -ExitCode 0 -Output @('HARNESS_PRIVATE_LOG')
    }
    return New-HarnessNativeResult -ExitCode 0
}

function Invoke-HarnessScp {
    param(
        [Parameter(Mandatory)][AllowEmptyCollection()][string[]]$ArgumentList,
        [AllowNull()][AllowEmptyString()][string]$StandardInput = $null
    )

    return New-HarnessNativeResult -ExitCode 0
}

function Invoke-HarnessCurl {
    param(
        [Parameter(Mandatory)][AllowEmptyCollection()][string[]]$ArgumentList,
        [AllowNull()][AllowEmptyString()][string]$StandardInput = $null
    )

    $arguments = @($ArgumentList)
    $outputIndex = [Array]::IndexOf($arguments, '--output')
    if ($outputIndex -lt 0 -or $outputIndex + 1 -ge $arguments.Count) {
        return New-HarnessNativeResult -ExitCode 2 -Output @('000')
    }
    $bodyPath = $arguments[$outputIndex + 1]
    $url = $arguments[-1]
    $statusCode = 200
    $body = '<html>admin</html>'

    if ($url -like '*/hvk/admin/login.php' -and $Script:HarnessScenario -eq 'curl-503') {
        $statusCode = 503
        $body = 'Service unavailable'
    }
    elseif ($url -like '*/media/*') {
        $statusCode = 403
        $body = 'Forbidden'
        if ($Script:HarnessScenario -eq 'curl-test-executes' -and $url -notlike '*/media/') {
            $statusCode = 200
            $body = 'EXECUTED'
        }
    }

    [System.IO.File]::WriteAllText($bodyPath, $body, [System.Text.UTF8Encoding]::new($false))
    return New-HarnessNativeResult -ExitCode 0 -Output @([string]$statusCode)
}

function Enable-LocalHarnessFakes {
    function script:ssh.exe {
        param(
            [Parameter(Mandatory)][AllowEmptyCollection()][string[]]$ArgumentList,
            [AllowNull()][AllowEmptyString()][string]$StandardInput = $null
        )
        return Invoke-HarnessSsh -ArgumentList $ArgumentList -StandardInput $StandardInput
    }

    function script:scp.exe {
        param(
            [Parameter(Mandatory)][AllowEmptyCollection()][string[]]$ArgumentList,
            [AllowNull()][AllowEmptyString()][string]$StandardInput = $null
        )
        return Invoke-HarnessScp -ArgumentList $ArgumentList -StandardInput $StandardInput
    }

    function script:curl.exe {
        param(
            [Parameter(Mandatory)][AllowEmptyCollection()][string[]]$ArgumentList,
            [AllowNull()][AllowEmptyString()][string]$StandardInput = $null
        )
        return Invoke-HarnessCurl -ArgumentList $ArgumentList -StandardInput $StandardInput
    }
}

function Invoke-LocalValidation {
    $results = [System.Collections.Generic.List[object]]::new()
    $temporaryRoot = Join-Path ([System.IO.Path]::GetTempPath()) ("chef-deploy-local-validation-{0}" -f [Guid]::NewGuid().ToString('N'))
    $originalReviewPath = $Script:ReviewPath
    $originalProjectPath = $Script:ProjectPath

    function Add-LocalResult {
        param(
            [Parameter(Mandatory)][ValidateSet('PASS', 'FAIL')][string]$Status,
            [Parameter(Mandatory)][string]$Check,
            [Parameter(Mandatory)][string]$Command
        )

        $results.Add([pscustomobject]@{ Status = $Status; Check = $Check; Command = $Command })
    }

    try {
        if ($PSVersionTable.PSVersion.Major -lt 7) {
            throw 'Yerel doğrulama PowerShell 7 ile çalıştırılmalıdır.'
        }
        New-Item -ItemType Directory -Path $temporaryRoot | Out-Null

        $expectedNames = @{
            1 = 'SSH anahtarı'
            2 = 'PHP ve uzantılar'
            3 = 'Belge kökü'
            4 = 'Veritabanı adı'
            5 = 'Uzak yedek'
            6 = 'Arşiv aktarımı'
            7 = '.htaccess kontrolü'
            8 = 'Özel yapılandırma'
            9 = 'CHEF_CONFIG_FILE'
            10 = 'Migration ve hazırlık'
            11 = 'Sahip kurtarma'
            12 = 'Medya cleanup cron'
            13 = 'HTTPS kontrolleri'
            14 = 'Readonly kontrolü'
            15 = 'Raporlama'
        }
        foreach ($number in 1..15) {
            $startOutput = [string](Start-Step -Number $number 6>&1)
            Set-StepResult -Number $number -Status PASS -Detail "Yerel adım doğrulaması $number."
            if ($Script:CurrentStep -ne $number -or $Script:Steps[$number].Name -ne $expectedNames[$number] -or $startOutput -notlike "*$($expectedNames[$number])*" ) {
                throw "Adım eşlemesi başarısız: $number"
            }
        }
        Add-LocalResult PASS '1-15 adım anahtarları, adları, Start-Step ve Set-StepResult' 'pwsh -NoProfile -File deploy/deploy-staging.ps1 -LocalValidationOnly'

        $renderedTable = Show-ResultTable | Out-String
        $lastNamePosition = -1
        foreach ($number in 1..15) {
            $namePosition = $renderedTable.IndexOf($expectedNames[$number], [StringComparison]::Ordinal)
            if ($namePosition -le $lastNamePosition) {
                throw "Show-ResultTable sırası başarısız: $number"
            }
            $lastNamePosition = $namePosition
        }
        Add-LocalResult PASS 'Show-ResultTable sıralı 15 adım çıktısı' 'Show-ResultTable | Out-String'

        $pwshPath = (Get-Command pwsh.exe -ErrorAction Stop).Source
        $argumentProbePath = Join-Path $temporaryRoot 'argument-probe.ps1'
        $argumentProbe = @'
param(
    [Parameter(Position = 0)][AllowEmptyString()][string]$First,
    [Parameter(Position = 1)][string]$Second
)
if ($First -ne '' -or $Second -ne 'tail') { exit 9 }
Write-Output 'EMPTY_ARGUMENT_PASS'
'@
        [System.IO.File]::WriteAllText($argumentProbePath, $argumentProbe, [System.Text.UTF8Encoding]::new($false))
        $argumentResult = Invoke-NativeCapture -Executable $pwshPath -ArgumentList @(
            '-NoProfile', '-NonInteractive', '-File', $argumentProbePath, '', 'tail'
        )
        if ($argumentResult.ExitCode -ne 0 -or $argumentResult.Text.Trim() -ne 'EMPTY_ARGUMENT_PASS') {
            throw 'Invoke-NativeCapture boş dize argümanını korumadı.'
        }
        Add-LocalResult PASS 'Invoke-NativeCapture boş dize argümanı' 'Invoke-NativeCapture pwsh.exe ... "" tail'

        $streamResult = Invoke-NativeCapture -Executable $pwshPath -ArgumentList @(
            '-NoProfile', '-NonInteractive', '-Command',
            '[Console]::Out.Write("STDOUT_PROBE"); [Console]::Error.Write("STDERR_PROBE"); exit 7'
        )
        if ($streamResult.ExitCode -ne 7 -or $streamResult.Stdout -ne 'STDOUT_PROBE' -or $streamResult.Stderr -ne 'STDERR_PROBE') {
            throw 'Invoke-NativeCapture stdout ve stderr akışlarını ayrı tutmadı.'
        }
        Add-LocalResult PASS 'Invoke-NativeCapture stdout ve stderr ayrımı' 'Invoke-NativeCapture pwsh.exe -Command <stdout/stderr probe>'

        $askPassProbePath = Join-Path $temporaryRoot 'askpass-probe.cmd'
        $askPassMarker = 'LOCAL_ASKPASS_MARKER'
        try {
            [Environment]::SetEnvironmentVariable('CHEF_SSH_PASSWORD', $askPassMarker, 'Process')
            New-SshAskPassHelper -Path $askPassProbePath
            $askPassProbe = Invoke-NativeCapture -Executable 'cmd.exe' -ArgumentList @('/d', '/c', $askPassProbePath)
            if ($askPassProbe.ExitCode -ne 0 -or $askPassProbe.Text.Trim() -ne $askPassMarker) {
                throw 'SSH_ASKPASS yardımcısı ortam değişkenini güvenli biçimde okuyamadı.'
            }
        }
        finally {
            [Environment]::SetEnvironmentVariable('CHEF_SSH_PASSWORD', $null, 'Process')
            if (Test-Path -LiteralPath $askPassProbePath) {
                Remove-Item -LiteralPath $askPassProbePath -Force
            }
        }
        Add-LocalResult PASS 'SSH_ASKPASS yardımcısı ve kullanım sonrası silme' 'cmd.exe /d /c <temp-askpass-helper>'

        $temporaryKey = Join-Path $temporaryRoot 'empty-passphrase-ed25519'
        $keyCreate = Invoke-NativeCapture -Executable 'ssh-keygen.exe' -ArgumentList @(
            '-q', '-t', 'ed25519', '-f', $temporaryKey, '-N', '', '-C', 'chef-local-validation'
        )
        if ($keyCreate.ExitCode -ne 0 -or -not (Test-Path -LiteralPath $temporaryKey -PathType Leaf)) {
            throw 'PowerShell 7 boş passphrase değerini ssh-keygen komutuna aktaramadı.'
        }
        $keyRead = Invoke-NativeCapture -Executable 'ssh-keygen.exe' -ArgumentList @(
            '-y', '-P', '', '-f', $temporaryKey
        )
        if ($keyRead.ExitCode -ne 0 -or [string]::IsNullOrWhiteSpace($keyRead.Text)) {
            throw 'Geçici SSH anahtarı boş passphrase ile açılamadı.'
        }
        Add-LocalResult PASS 'PowerShell 7 ile ssh-keygen boş passphrase aktarımı' 'ssh-keygen -q -t ed25519 -f <temp> -N ""'

        $Script:ProjectPath = Join-Path $temporaryRoot 'PROJECT.md'
        $Script:ReviewPath = Join-Path $temporaryRoot 'docs\reviews\staging-deploy.md'
        Copy-Item -LiteralPath $originalProjectPath -Destination $Script:ProjectPath
        Write-DeploymentRecords -OverallStatus PASS
        $review = [System.IO.File]::ReadAllText($Script:ReviewPath)
        $rowMatches = [regex]::Matches($review, '(?m)^\| (?<number>\d+) \|')
        $rowNumbers = @($rowMatches | ForEach-Object { [int]$_.Groups['number'].Value })
        if ($rowNumbers.Count -ne 15 -or ($rowNumbers -join ',') -ne ((1..15) -join ',')) {
            throw 'Deployment review 15 adımı doğru sırada içermiyor.'
        }
        $projectCopy = [System.IO.File]::ReadAllText($Script:ProjectPath)
        if ($projectCopy -notmatch '(?s)<!-- staging-deploy:start -->.*?<!-- staging-deploy:end -->') {
            throw 'Geçici PROJECT.md deployment kaydını içermiyor.'
        }
        Add-LocalResult PASS 'Write-DeploymentRecords geçici PROJECT ve 15 sıralı review satırı' 'Write-DeploymentRecords -OverallStatus PASS'

        $scriptText = [System.IO.File]::ReadAllText($PSCommandPath)
        $scriptLines = @($scriptText -split '\r?\n')
        $countLengthVariables = @(foreach ($line in $scriptLines) {
            foreach ($match in @([regex]::Matches($line, '\$(?<name>[A-Za-z_][A-Za-z0-9_]*)\.(Count|Length)\b'))) {
                $match.Groups['name'].Value
            }
        })
        $pipelineAssignments = @($scriptLines | Where-Object { $_ -match '^\s*\$(?<name>[A-Za-z_][A-Za-z0-9_]*)\s*=.*\|' })
        $unsafePipelineAssignments = @(foreach ($line in $pipelineAssignments) {
            if ($line -match '^\s*\$(?<name>[A-Za-z_][A-Za-z0-9_]*)\s*=\s*(?<value>.*)$' -and
                $Matches.name -in $countLengthVariables -and $Matches.value -notmatch '^@\(') {
                $line
            }
        })
        if ($countLengthVariables.Count -eq 0 -or $unsafePipelineAssignments.Count -gt 0) {
            throw 'Pipeline Count/Length denetimi başarısız oldu.'
        }
        Add-LocalResult PASS 'Pipeline sonuçları Count veya Length öncesinde diziye sarıldı' 'Select-String deploy/deploy-staging.ps1 -Pattern ''\.(Count|Length)\b|= .*\|'''

        Add-Type -AssemblyName System.IO.Compression
        $unsafeArchiveCases = @(
            @{ File = 'backslash.zip'; Entry = 'folder\file.txt' },
            @{ File = 'traversal.zip'; Entry = '../escape.txt' }
        )
        foreach ($unsafeCase in $unsafeArchiveCases) {
            $unsafeArchivePath = Join-Path $temporaryRoot $unsafeCase.File
            $unsafeStream = [System.IO.FileStream]::new($unsafeArchivePath, [System.IO.FileMode]::CreateNew)
            $unsafeArchive = [System.IO.Compression.ZipArchive]::new($unsafeStream, [System.IO.Compression.ZipArchiveMode]::Create)
            try {
                $unsafeEntry = $unsafeArchive.CreateEntry($unsafeCase.Entry)
                $unsafeWriter = [System.IO.StreamWriter]::new($unsafeEntry.Open())
                try { $unsafeWriter.Write('probe') } finally { $unsafeWriter.Dispose() }
            }
            finally {
                $unsafeArchive.Dispose()
                $unsafeStream.Dispose()
            }
            $rejected = $false
            try { $null = Get-SafeZipArchiveInfo -Path $unsafeArchivePath } catch { $rejected = $true }
            if (-not $rejected) {
                throw "Güvensiz ZIP preflight tarafından reddedilmedi: $($unsafeCase.File)"
            }
        }
        Add-LocalResult PASS 'ZIP preflight ters eğik çizgi ve traversal yollarını reddetti' 'Get-SafeZipArchiveInfo <unsafe-probe.zip>'

        $scenarioExpectations = [ordered]@{
            'pass' = @{ ExitCode = 1; FailedStep = 0; Output = 'PHP 8.3.33' }
            'resume-pass' = @{ ExitCode = 1; FailedStep = 0; Output = 'BACKUP_SHA256=' }
            'missing-module' = @{ ExitCode = 1; FailedStep = 2; Output = 'gd' }
            'no-missing-module' = @{ ExitCode = 1; FailedStep = 0; Output = 'PHP 8.4.26' }
            'empty-document-root' = @{ ExitCode = 1; FailedStep = 3; Output = $null }
            'curl-503' = @{ ExitCode = 1; FailedStep = 13; Output = '503' }
            'curl-test-executes' = @{ ExitCode = 1; FailedStep = 13; Output = 'upload-test.php' }
        }
        foreach ($scenario in $scenarioExpectations.Keys) {
            $scenarioRoot = Join-Path $temporaryRoot $scenario
            New-Item -ItemType Directory -Path $scenarioRoot | Out-Null
            Copy-Item -LiteralPath $originalProjectPath -Destination (Join-Path $scenarioRoot 'PROJECT.md')
            try {
                [Environment]::SetEnvironmentVariable('CHEF_LOCAL_HARNESS_SCENARIO', $scenario, 'Process')
                [Environment]::SetEnvironmentVariable('CHEF_LOCAL_HARNESS_ROOT', $scenarioRoot, 'Process')
                [Environment]::SetEnvironmentVariable('CHEF_DB_PASSWORD', 'LOCAL_HARNESS_ONLY', 'Process')
                $scenarioArguments = @(
                    '-NoProfile', '-NonInteractive', '-File', $PSCommandPath,
                    '-ConfirmDocumentRoot', '-SkipOwner'
                )
                if ($scenario -eq 'resume-pass') {
                    $scenarioArguments += @(
                        '-ResumeStep6',
                        '-ExistingBackupPath', '/home/u220042353/backups/staging-harness.tar.gz',
                        '-ExpectedBackupSha256', ('a' * 64)
                    )
                }
                $scenarioResult = Invoke-NativeCapture -Executable $pwshPath -ArgumentList $scenarioArguments
            }
            finally {
                [Environment]::SetEnvironmentVariable('CHEF_LOCAL_HARNESS_SCENARIO', $null, 'Process')
                [Environment]::SetEnvironmentVariable('CHEF_LOCAL_HARNESS_ROOT', $null, 'Process')
                [Environment]::SetEnvironmentVariable('CHEF_DB_PASSWORD', $null, 'Process')
            }

            $harnessResultPath = Join-Path $scenarioRoot 'harness-result.json'
            if (-not (Test-Path -LiteralPath $harnessResultPath -PathType Leaf)) {
                throw "Harness sonuç dosyası eksik: $scenario"
            }
            $harnessResult = [System.IO.File]::ReadAllText($harnessResultPath) | ConvertFrom-Json
            $harnessSteps = @($harnessResult.steps)
            $harnessStepNumbers = @($harnessSteps | ForEach-Object { [int]$_.number })
            $expectation = $scenarioExpectations[$scenario]
            if ($scenarioResult.ExitCode -ne $expectation.ExitCode -or $harnessSteps.Count -ne 15 -or ($harnessStepNumbers -join ',') -ne ((1..15) -join ',')) {
                throw "Harness çıkış veya 15 satır doğrulaması başarısız: $scenario"
            }
            if ($expectation.FailedStep -gt 0 -and $harnessSteps[$expectation.FailedStep - 1].status -ne 'FAIL') {
                throw "Harness beklenen adımı FAIL yapmadı: $scenario"
            }
            if ($expectation.FailedStep -gt 0) {
                $notRunSteps = @($harnessSteps | Where-Object {
                    $_.number -gt $expectation.FailedStep -and $_.number -lt 15 -and $_.status -ne 'NOT RUN'
                })
                if ($notRunSteps.Count -gt 0) {
                    throw "Harness atlanan adımları NOT RUN yapmadı: $scenario"
                }
            }
            if ($expectation.FailedStep -eq 0 -and @($harnessSteps | Where-Object { $_.status -eq 'FAIL' }).Count -gt 0) {
                throw "Harness PASS senaryosunda FAIL üretti: $scenario"
            }
            if ($null -ne $expectation.Output -and $scenarioResult.Text -notlike "*$($expectation.Output)*") {
                throw "Harness beklenen çıktıyı üretmedi: $scenario"
            }
            $scenarioReview = [System.IO.File]::ReadAllText((Join-Path $scenarioRoot 'docs\reviews\staging-deploy.md'))
            $scenarioRows = @([regex]::Matches($scenarioReview, '(?m)^\| (?<number>\d+) \|'))
            if ($scenarioRows.Count -ne 15) {
                throw "Harness sonuç tablosu 15 satır değil: $scenario"
            }
            Add-LocalResult PASS "Adım 2-14 sahte akış senaryosu: $scenario" "pwsh -File deploy/deploy-staging.ps1 -ConfirmDocumentRoot -SkipOwner # fake $scenario"
        }
    }
    catch {
        Add-LocalResult FAIL 'Yerel doğrulama' ([string]$_.Exception.Message)
    }
    finally {
        $Script:ReviewPath = $originalReviewPath
        $Script:ProjectPath = $originalProjectPath
        if (Test-Path -LiteralPath $temporaryRoot) {
            $resolvedTemporary = [System.IO.Path]::GetFullPath($temporaryRoot)
            $systemTemporary = [System.IO.Path]::GetFullPath([System.IO.Path]::GetTempPath())
            if ($resolvedTemporary.StartsWith($systemTemporary, [StringComparison]::OrdinalIgnoreCase)) {
                Remove-Item -LiteralPath $resolvedTemporary -Recurse -Force
            }
        }
    }

    $results | Format-Table -AutoSize | Out-Host
    if ($results.Status -contains 'FAIL') {
        return 1
    }
    return 0
}

if ($Script:UseFakeNativeCommands) {
    Enable-LocalHarnessFakes
}

if ($LocalValidationOnly) {
    exit (Invoke-LocalValidation)
}

$exitCode = 1
$overallStatus = 'FAIL'
$localTemporaryRoot = Join-Path ([System.IO.Path]::GetTempPath()) ("chef-deploy-{0}" -f [Guid]::NewGuid().ToString('N'))
$configHelperLocal = Join-Path $localTemporaryRoot 'config-helper.php'
$httpTestRoot = Join-Path $localTemporaryRoot 'http-tests'
$publicArchiveInfo = $null
$privateArchiveInfo = $null

try {
    foreach ($requiredFile in @($PublicArchive, $PrivateArchive, $ReadonlyCheck, $ProjectPath)) {
        if (-not (Test-Path -LiteralPath $requiredFile -PathType Leaf)) {
            throw "Gerekli yerel dosya bulunamadı: $([System.IO.Path]::GetFileName($requiredFile))"
        }
    }
    $publicArchiveInfo = Get-SafeZipArchiveInfo -Path $PublicArchive
    $privateArchiveInfo = Get-SafeZipArchiveInfo -Path $PrivateArchive
    if ($publicArchiveInfo.Entries -ne 33 -or $privateArchiveInfo.Entries -ne 9) {
        throw 'Staging ZIP giriş sayıları beklenen 33 ve 9 değerleriyle eşleşmiyor.'
    }
    foreach ($command in @('ssh.exe', 'scp.exe', 'ssh-keygen.exe', 'curl.exe')) {
        if (-not (Get-Command $command -ErrorAction SilentlyContinue)) {
            throw "Gerekli komut bulunamadı: $command"
        }
    }
    New-Item -ItemType Directory -Path $localTemporaryRoot | Out-Null
    [System.IO.File]::WriteAllText($configHelperLocal, $ConfigHelperPhp, [System.Text.UTF8Encoding]::new($false))

    Start-Step 1
    try {
        $keyDirectory = Split-Path -Parent $KeyPath
        if (-not (Test-Path -LiteralPath $keyDirectory)) {
            New-Item -ItemType Directory -Path $keyDirectory | Out-Null
        }
        if (-not (Test-Path -LiteralPath $KeyPath -PathType Leaf)) {
            $keyResult = Invoke-NativeCapture -Executable 'ssh-keygen.exe' -ArgumentList @(
                '-q', '-t', 'ed25519', '-f', $KeyPath, '-N', '', '-C', 'chef-staging-deploy'
            )
            Assert-NativeSuccess $keyResult 'SSH anahtarı oluşturulamadı.'
        }
        if (-not (Test-Path -LiteralPath "$KeyPath.pub" -PathType Leaf)) {
            throw 'SSH açık anahtarı bulunamadı.'
        }
        $keyCheck = Invoke-SshCapture -RemoteCommand 'true'
        if ($keyCheck.ExitCode -ne 0) {
            $publicKeyBytes = [System.IO.File]::ReadAllBytes("$KeyPath.pub")
            $publicKeyBase64 = [Convert]::ToBase64String($publicKeyBytes)
            $installCommand = @'
umask 077
mkdir -p "$HOME/.ssh"
touch "$HOME/.ssh/authorized_keys"
chmod 700 "$HOME/.ssh"
chmod 600 "$HOME/.ssh/authorized_keys"
key="$(printf '%s' '__PUBLIC_KEY_BASE64__' | base64 -d)"
grep -qxF -- "$key" "$HOME/.ssh/authorized_keys" || printf '%s\n' "$key" >> "$HOME/.ssh/authorized_keys"
unset key
'@.Replace('__PUBLIC_KEY_BASE64__', $publicKeyBase64)
            $manualKeyCommand = Get-ManualKeyInstallCommand -InstallCommand $installCommand
            if ([string]::IsNullOrEmpty([Environment]::GetEnvironmentVariable('CHEF_SSH_PASSWORD', 'Process'))) {
                Write-Host "Manuel anahtar kurulum komutu: $manualKeyCommand"
                throw 'CHEF_SSH_PASSWORD bulunamadı. Manuel anahtar kurulumu gerekir.'
            }

            $askPassPath = Join-Path $localTemporaryRoot 'chef-ssh-askpass.cmd'
            $previousAskPass = [Environment]::GetEnvironmentVariable('SSH_ASKPASS', 'Process')
            $previousAskPassRequirement = [Environment]::GetEnvironmentVariable('SSH_ASKPASS_REQUIRE', 'Process')
            $previousDisplay = [Environment]::GetEnvironmentVariable('DISPLAY', 'Process')
            $installResult = $null
            try {
                New-SshAskPassHelper -Path $askPassPath
                [Environment]::SetEnvironmentVariable('SSH_ASKPASS', $askPassPath, 'Process')
                [Environment]::SetEnvironmentVariable('SSH_ASKPASS_REQUIRE', 'force', 'Process')
                [Environment]::SetEnvironmentVariable('DISPLAY', 'chef-staging-askpass', 'Process')
                $installResult = Invoke-NativeCapture -Executable 'ssh.exe' -ArgumentList @(
                    '-T', '-p', [string]$SshPort,
                    '-o', 'NumberOfPasswordPrompts=1',
                    '-o', 'PubkeyAuthentication=no',
                    '-o', 'PreferredAuthentications=password',
                    '-o', 'StrictHostKeyChecking=accept-new',
                    $RemoteTarget, $installCommand
                )
            }
            finally {
                [Environment]::SetEnvironmentVariable('CHEF_SSH_PASSWORD', $null, 'Process')
                [Environment]::SetEnvironmentVariable('SSH_ASKPASS', $previousAskPass, 'Process')
                [Environment]::SetEnvironmentVariable('SSH_ASKPASS_REQUIRE', $previousAskPassRequirement, 'Process')
                [Environment]::SetEnvironmentVariable('DISPLAY', $previousDisplay, 'Process')
                if (Test-Path -LiteralPath $askPassPath) {
                    Remove-Item -LiteralPath $askPassPath -Force
                }
            }
            if ($null -eq $installResult -or $installResult.ExitCode -ne 0) {
                Write-Host "Manuel anahtar kurulum komutu: $manualKeyCommand"
                throw 'SSH_ASKPASS ile açık anahtar kurulamadı. Manuel anahtar kurulumu gerekir.'
            }
        }
        $keyCheck = Invoke-SshCapture -RemoteCommand 'true'
        Assert-NativeSuccess $keyCheck 'SSH anahtarıyla bağlantı doğrulanamadı.'
        $Script:KeyReady = $true
        Set-StepResult 1 PASS 'Anahtar doğrulandı. Sonraki bağlantılar anahtarı kullandı.'
    }
    finally {
        [Environment]::SetEnvironmentVariable('CHEF_SSH_PASSWORD', $null, 'Process')
    }

    Start-Step 2
    $phpPathResult = Invoke-SshCapture -RemoteCommand 'command -v php && command -v unzip'
    Assert-NativeSuccess $phpPathResult 'Uzak php veya unzip komutu bulunamadı.'
    $remoteCommands = @($phpPathResult.Output | Where-Object { $_ -match '^/' })
    if ($remoteCommands.Count -lt 2) {
        throw 'Uzak php veya unzip yolu doğrulanamadı.'
    }
    $defaultRemotePhp = $remoteCommands[0].Trim()
    Assert-SafeRemotePath $defaultRemotePhp

    $altPhpListing = Invoke-SshCapture -RemoteCommand 'ls /opt/alt 2>/dev/null || true'
    Assert-NativeSuccess $altPhpListing 'Uzak /opt/alt dizini denetlenemedi.'
    $php83ProbeCommand = @'
for candidate in /opt/alt/php83/usr/bin/php /opt/alt/php83/root/usr/bin/php /opt/alt/php83/bin/php /opt/alt/php83*/usr/bin/php /opt/alt/php83*/root/usr/bin/php; do
    if [ -x "$candidate" ]; then
        printf '%s\n' "$candidate"
        exit 0
    fi
done
exit 1
'@
    $php83Probe = Invoke-SshCapture -RemoteCommand $php83ProbeCommand
    $php83Candidates = @($php83Probe.Output | Where-Object { $_ -match '^/opt/alt/[A-Za-z0-9._/-]+/php$' })
    if ($php83Probe.ExitCode -eq 0 -and $php83Candidates.Count -gt 0) {
        $Script:RemotePhp = $php83Candidates[0].Trim()
        Assert-SafeRemotePath $Script:RemotePhp
    }
    else {
        $Script:RemotePhp = $defaultRemotePhp
        Write-Warning 'PHP 8.3 CLI bulunamadı. Varsayılan CLI kullanılıyor. Proje PHP 8.3.33 üzerinde test edildi.'
    }

    $phpVersion = Invoke-SshCapture -RemoteCommand "$Script:RemotePhp -v"
    Assert-NativeSuccess $phpVersion 'Uzak PHP sürümü okunamadı.'
    Write-Host 'CLI PHP sürümü:'
    Write-Host $phpVersion.Text
    $phpModules = Invoke-SshCapture -RemoteCommand "$Script:RemotePhp -m"
    Assert-NativeSuccess $phpModules 'Uzak PHP modülleri okunamadı.'
    $moduleNames = @($phpModules.Output | ForEach-Object { $_.Trim().ToLowerInvariant() })
    $missingModules = @(@('pdo_mysql', 'mbstring', 'fileinfo', 'gd') | Where-Object { $_ -notin $moduleNames })
    if ($missingModules.Count -gt 0) {
        Write-Host "Eksik PHP modülleri: $($missingModules -join ', ')"
        throw "Eksik PHP modülü: $($missingModules -join ', ')"
    }
    Write-Host 'Eksik PHP modülleri: yok.'
    $webpCommand = "$Script:RemotePhp -r 'exit(function_exists(`"imagewebp`") ? 0 : 1);'"
    $webpResult = Invoke-SshCapture -RemoteCommand $webpCommand
    Assert-NativeSuccess $webpResult 'CLI PHP imagewebp işlevini sağlamıyor.'
    Write-Host 'CLI PHP imagewebp: kullanılabilir.'
    Set-StepResult 2 PASS 'CLI PHP sürümü yazdırıldı. Gerekli modüller ve imagewebp bulundu.'

    Start-Step 3
    $domainListing = Invoke-SshCapture -RemoteCommand 'ls -la "$HOME/domains"'
    Assert-NativeSuccess $domainListing 'Uzak domains dizini listelenemedi.'
    Write-Host $domainListing.Text
    $discoverCommand = @'
set -eu
domain_root="$(readlink -f "$HOME/domains/taslak.bilincreklam.com")"
[ -d "$domain_root" ]
document_root=""
if [ -d "$domain_root/public_html" ]; then
    document_root="$(readlink -f "$domain_root/public_html")"
else
    document_root="$(find "$domain_root" -maxdepth 2 -type d -name public_html -print -quit)"
    [ -n "$document_root" ]
    document_root="$(readlink -f "$document_root")"
fi
printf 'DOMAIN_ROOT=%s\nDOCUMENT_ROOT=%s\n' "$domain_root" "$document_root"
'@
    $discovery = Invoke-SshCapture -RemoteCommand $discoverCommand
    Assert-NativeSuccess $discovery 'Staging belge kökü bulunamadı.'
    $domainRootLine = $discovery.Output | Where-Object { $_ -like 'DOMAIN_ROOT=*' } | Select-Object -First 1
    $documentRootLine = $discovery.Output | Where-Object { $_ -like 'DOCUMENT_ROOT=*' } | Select-Object -First 1
    if (-not $domainRootLine -or -not $documentRootLine) {
        throw 'Staging belge kökü çıktısı doğrulanamadı.'
    }
    $domainRoot = ([string]$domainRootLine).Substring('DOMAIN_ROOT='.Length).Trim()
    $Script:DocumentRoot = ([string]$documentRootLine).Substring('DOCUMENT_ROOT='.Length).Trim()
    Assert-SafeRemotePath $domainRoot
    Assert-SafeRemotePath $Script:DocumentRoot
    if (-not $Script:DocumentRoot.StartsWith($domainRoot.TrimEnd('/') + '/', [StringComparison]::Ordinal)) {
        throw 'Belge kökü hedef alan adı dizininin dışında.'
    }
    Write-Host "Bulunan belge kökü: $Script:DocumentRoot"
    if (-not $ConfirmDocumentRoot) {
        $confirmation = Read-Host 'Bu belge kökünü onaylamak için EVET yazın'
        if ($confirmation -cne 'EVET') {
            throw 'Belge kökü kullanıcı tarafından onaylanmadı.'
        }
    }
    Set-StepResult 3 PASS 'Belge kökü alan adı dizini içinde doğrulandı ve onaylandı.'

    Start-Step 4
    if ($DatabaseName -match '(?i)havakuvvetleri') {
        throw 'Yasaklı veritabanı adı algılandı.'
    }
    Set-StepResult 4 PASS 'Staging veritabanı adı yasaklı eski adı içermiyor.'

    Start-Step 5
    if ($ResumeStep6) {
        $backupCommand = @'
set -eu
backup="__BACKUP_PATH__"
[ ! -L "$backup" ]
[ -f "$backup" ]
[ -r "$backup" ]
actual="$(sha256sum "$backup" | awk '{print $1}')"
[ "$actual" = "__BACKUP_SHA__" ]
printf 'BACKUP=%s\nBACKUP_SHA256=%s\n' "$backup" "$actual"
'@.Replace('__BACKUP_PATH__', $ExistingBackupPath).Replace('__BACKUP_SHA__', $ExpectedBackupSha256)
        $backup = Invoke-SshCapture -RemoteCommand $backupCommand
        if ($backup.ExitCode -ne 0) {
            throw (Get-SanitizedNativeFailure -Label 'existing-backup-check' -Result $backup)
        }
        Write-Host $backup.Stdout
        Set-StepResult 5 PASS 'Mevcut staging backup yolu ve SHA-256 değeri doğrulandı. Yeni backup oluşturulmadı.'
    }
    else {
        $backupCommand = @'
set -eu
mkdir -p "$HOME/backups"
timestamp="$(date -u +%Y%m%dT%H%M%SZ)"
archive="$HOME/backups/staging-$timestamp.tar.gz"
tar -czf "$archive" -C "__DOCUMENT_ROOT__" .
printf 'BACKUP=%s\n' "$archive"
'@.Replace('__DOCUMENT_ROOT__', $Script:DocumentRoot)
        $backup = Invoke-SshCapture -RemoteCommand $backupCommand
        if ($backup.ExitCode -ne 0) {
            throw (Get-SanitizedNativeFailure -Label 'backup-create' -Result $backup)
        }
        $backupLine = $backup.Output | Where-Object { $_ -like 'BACKUP=*' } | Select-Object -First 1
        if (-not $backupLine) {
            throw 'Belge kökü yedeği doğrulanamadı.'
        }
        Write-Host ([string]$backupLine)
        Set-StepResult 5 PASS 'Belge kökü overwrite öncesinde zaman damgalı arşive yedeklendi.'
    }

    Start-Step 6
    $prepareUpload = Invoke-SshCapture -RemoteCommand "set -eu; [ ! -L $RemoteUploadRoot ]; [ ! -L $RemotePrivateRoot ]; umask 077; mkdir -p $RemoteUploadRoot $RemotePrivateRoot; chmod 700 $RemoteUploadRoot $RemotePrivateRoot"
    if ($prepareUpload.ExitCode -ne 0) {
        throw (Get-SanitizedNativeFailure -Label 'prepare-upload-root' -Result $prepareUpload)
    }
    $publicTransfer = Invoke-Scp $PublicArchive "$RemoteUploadRoot/public_html.zip"
    if ($publicTransfer.ExitCode -ne 0) {
        throw (Get-SanitizedNativeFailure -Label 'scp-public-archive' -Result $publicTransfer)
    }
    $privateTransfer = Invoke-Scp $PrivateArchive "$RemoteUploadRoot/chef-private-setup.zip"
    if ($privateTransfer.ExitCode -ne 0) {
        throw (Get-SanitizedNativeFailure -Label 'scp-private-archive' -Result $privateTransfer)
    }

    $verifyName = "verify-$($publicArchiveInfo.Sha256.Substring(0, 12))-$($privateArchiveInfo.Sha256.Substring(0, 12))"
    $testExtractCommand = @'
set -u
upload="__UPLOAD_ROOT__"
verify="$upload/__VERIFY_NAME__"
case "$verify" in "$upload"/verify-[a-f0-9-]*) ;; *) exit 90 ;; esac
[ ! -L "$upload" ]
[ ! -L "$verify" ]
rm -rf -- "$verify"
umask 077
mkdir -p "$verify/public" "$verify/private"
cleanup() { rm -rf -- "$verify"; }
trap cleanup EXIT HUP INT TERM
public_archive="$upload/public_html.zip"
private_archive="$upload/chef-private-setup.zip"
public_hash="$(sha256sum "$public_archive" | awk '{print $1}')"
private_hash="$(sha256sum "$private_archive" | awk '{print $1}')"
[ "$public_hash" = "__PUBLIC_SHA__" ]
[ "$private_hash" = "__PRIVATE_SHA__" ]
unzip -oq "$public_archive" -d "$verify/public" >"$verify/public.stdout" 2>"$verify/public.stderr"
public_code=$?
printf 'TEST_PUBLIC_EXIT=%s\n' "$public_code"
if [ "$public_code" -ne 0 ]; then cat "$verify/public.stderr" >&2; exit "$public_code"; fi
unzip -oq "$private_archive" -d "$verify/private" >"$verify/private.stdout" 2>"$verify/private.stderr"
private_code=$?
printf 'TEST_PRIVATE_EXIT=%s\n' "$private_code"
if [ "$private_code" -ne 0 ]; then cat "$verify/private.stderr" >&2; exit "$private_code"; fi
set -e
[ "$(find "$verify/public" -type f | wc -l | tr -d ' ')" = '33' ]
[ "$(find "$verify/private" -type f | wc -l | tr -d ' ')" = '9' ]
[ -f "$verify/public/media/.htaccess" ]
[ -f "$verify/public/hvk/admin/.htaccess" ]
[ -z "$(find "$verify" -type l -print -quit)" ]
python3 - "$public_archive" "$verify/public" "$private_archive" "$verify/private" <<'PY'
import hashlib, os, sys, zipfile
counts = []
for archive_path, root in ((sys.argv[1], sys.argv[2]), (sys.argv[3], sys.argv[4])):
    count = 0
    with zipfile.ZipFile(archive_path) as archive:
        for entry in archive.infolist():
            name = entry.filename
            parts = [part for part in name.split('/') if part not in ('', '.')]
            if '\\' in name or name.startswith('/') or '..' in parts:
                raise SystemExit(31)
            target = os.path.join(root, *parts)
            if not os.path.isfile(target) or os.path.islink(target):
                raise SystemExit(32)
            expected = hashlib.sha256(archive.read(entry)).digest()
            with open(target, 'rb') as handle:
                actual = hashlib.sha256(handle.read()).digest()
            if actual != expected:
                raise SystemExit(33)
            count += 1
    counts.append(count)
print(f'VERIFY_COUNTS={counts[0]},{counts[1]}')
PY
verify_code=$?
if [ "$verify_code" -ne 0 ]; then exit "$verify_code"; fi
cleanup
trap - EXIT HUP INT TERM
'@.Replace('__UPLOAD_ROOT__', $RemoteUploadRoot).Replace('__VERIFY_NAME__', $verifyName).Replace('__PUBLIC_SHA__', $publicArchiveInfo.Sha256).Replace('__PRIVATE_SHA__', $privateArchiveInfo.Sha256)
    $testExtract = Invoke-SshCapture -RemoteCommand $testExtractCommand
    if ($testExtract.ExitCode -ne 0) {
        throw (Get-SanitizedNativeFailure -Label 'private-test-extraction' -Result $testExtract)
    }
    if ($testExtract.Stdout -notmatch 'TEST_PUBLIC_EXIT=0' -or $testExtract.Stdout -notmatch 'TEST_PRIVATE_EXIT=0' -or
        $testExtract.Stdout -notmatch 'VERIFY_COUNTS=33,9') {
        throw 'Özel test extraction beklenen doğrulama işaretlerini üretmedi.'
    }

    if ($ResumeStep6) {
        $publicMatchCommand = @'
python3 - "__PUBLIC_ARCHIVE__" "__DOCUMENT_ROOT__" <<'PY'
import hashlib, os, sys, zipfile
archive_path, root = sys.argv[1:]
count = 0
with zipfile.ZipFile(archive_path) as archive:
    for entry in archive.infolist():
        parts = [part for part in entry.filename.split('/') if part not in ('', '.')]
        if '\\' in entry.filename or entry.filename.startswith('/') or '..' in parts:
            raise SystemExit(41)
        target = os.path.join(root, *parts)
        if not os.path.isfile(target) or os.path.islink(target):
            raise SystemExit(42)
        expected = hashlib.sha256(archive.read(entry)).digest()
        with open(target, 'rb') as handle:
            actual = hashlib.sha256(handle.read()).digest()
        if actual != expected:
            raise SystemExit(43)
        count += 1
print(f'PUBLIC_MATCH={count}')
PY
'@.Replace('__PUBLIC_ARCHIVE__', "$RemoteUploadRoot/public_html.zip").Replace('__DOCUMENT_ROOT__', $Script:DocumentRoot)
        $publicActivation = Invoke-SshCapture -RemoteCommand $publicMatchCommand
        if ($publicActivation.ExitCode -ne 0 -or $publicActivation.Stdout -notmatch 'PUBLIC_MATCH=33') {
            throw (Get-SanitizedNativeFailure -Label 'resume-public-match' -Result $publicActivation)
        }
    }
    else {
        $publicActivation = Invoke-SshCapture -RemoteCommand "unzip -oq $RemoteUploadRoot/public_html.zip -d $($Script:DocumentRoot)"
        if ($publicActivation.ExitCode -ne 0) {
            throw (Get-SanitizedNativeFailure -Label 'public-archive-extraction' -Result $publicActivation)
        }
    }

    $privateGuard = if ($ResumeStep6) { "[ -z `"`$(find $RemotePrivateRoot -mindepth 1 -print -quit)`" ] && " } else { '' }
    $privateActivation = Invoke-SshCapture -RemoteCommand "$privateGuard unzip -oq $RemoteUploadRoot/chef-private-setup.zip -d $RemotePrivateRoot"
    if ($privateActivation.ExitCode -ne 0) {
        throw (Get-SanitizedNativeFailure -Label 'private-archive-extraction' -Result $privateActivation)
    }
    $privatePermissions = Invoke-SshCapture -RemoteCommand "chmod 700 $RemotePrivateRoot"
    if ($privatePermissions.ExitCode -ne 0) {
        throw (Get-SanitizedNativeFailure -Label 'private-root-permissions' -Result $privatePermissions)
    }
    $activationMode = if ($ResumeStep6) { 'Public dosyalar hash ile doğrulandı; tekrar yazılmadı.' } else { 'Public arşiv çıkarıldı.' }
    Set-StepResult 6 PASS "Transferler ve özel test extraction exit=0. $activationMode Private arşiv çıkarıldı."

    Start-Step 7
    $htaccessCommand = "test -f $($Script:DocumentRoot)/media/.htaccess && test -f $($Script:DocumentRoot)/hvk/admin/.htaccess"
    $htaccess = Invoke-SshCapture -RemoteCommand $htaccessCommand
    Assert-NativeSuccess $htaccess 'Gerekli media veya admin .htaccess dosyası eksik.'
    Set-StepResult 7 PASS 'Public media ve admin .htaccess dosyaları bulundu.'

    Start-Step 8
    Assert-NativeSuccess (Invoke-Scp $configHelperLocal $RemoteHelperPath) 'Yapılandırma yardımcısı yüklenemedi.'
    $securePassword = $null
    $plainPassword = $null
    try {
        $environmentPassword = [Environment]::GetEnvironmentVariable('CHEF_DB_PASSWORD', 'Process')
        if ([string]::IsNullOrEmpty($environmentPassword)) {
            $securePassword = Read-Host 'Staging veritabanı parolası' -AsSecureString
            $plainPassword = Get-PlainText $securePassword
        }
        else {
            $plainPassword = $environmentPassword
            $environmentPassword = $null
            [Environment]::SetEnvironmentVariable('CHEF_DB_PASSWORD', $null, 'Process')
        }
        if ([string]::IsNullOrEmpty($plainPassword)) {
            throw 'Veritabanı parolası boş olamaz.'
        }
        $selectedDatabaseHost = $null
        foreach ($candidateHost in @('127.0.0.1', 'localhost')) {
            $testCommand = "$Script:RemotePhp $RemoteHelperPath test $candidateHost $DatabaseName $DatabaseUser $($Script:DocumentRoot) $RemotePrivateRoot $RemoteConfigPath"
            $testResult = Invoke-SshWithInput -RemoteCommand $testCommand -InputText $plainPassword
            if ($testResult.ExitCode -eq 0 -and $testResult.Text.Trim() -eq 'PASS') {
                $selectedDatabaseHost = $candidateHost
                break
            }
        }
        if (-not $selectedDatabaseHost) {
            throw 'Veritabanı bağlantısı 127.0.0.1 ve localhost ile başarısız oldu.'
        }
        $writeCommand = "$Script:RemotePhp $RemoteHelperPath write $selectedDatabaseHost $DatabaseName $DatabaseUser $($Script:DocumentRoot) $RemotePrivateRoot $RemoteConfigPath"
        $writeResult = Invoke-SshWithInput -RemoteCommand $writeCommand -InputText $plainPassword
        if ($writeResult.ExitCode -ne 0 -or $writeResult.Text.Trim() -ne 'PASS') {
            throw 'Özel yapılandırma güvenli biçimde oluşturulamadı.'
        }
    }
    finally {
        [Environment]::SetEnvironmentVariable('CHEF_DB_PASSWORD', $null, 'Process')
        $environmentPassword = $null
        $plainPassword = $null
        if ($securePassword) {
            $securePassword.Dispose()
            $securePassword = $null
        }
        [GC]::Collect()
        [GC]::WaitForPendingFinalizers()
        $null = Invoke-SshCapture -RemoteCommand "rm -f $RemoteHelperPath"
    }
    $privatePermissions = Invoke-SshCapture -RemoteCommand "find $RemotePrivateRoot -type d -exec chmod 700 {} + && chmod 600 $RemoteConfigPath"
    Assert-NativeSuccess $privatePermissions 'Özel dizin izinleri uygulanamadı.'
    Set-StepResult 8 PASS 'Veritabanı bağlantısı doğrulandı. Özel yapılandırma ve izinler oluşturuldu.'

    Start-Step 9
    $setEnvCommand = @'
set -eu
target="__DOCUMENT_ROOT__/.htaccess"
[ ! -L "$target" ]
temporary="$(mktemp "__UPLOAD_ROOT__/htaccess.XXXXXX")"
trap 'rm -f "$temporary"' EXIT
if [ -f "$target" ]; then
    awk '!/^[[:space:]]*SetEnv[[:space:]]+CHEF_CONFIG_FILE([[:space:]]|$)/' "$target" > "$temporary"
fi
printf '\nSetEnv CHEF_CONFIG_FILE __CONFIG_PATH__\n' >> "$temporary"
chmod 0644 "$temporary"
mv -f "$temporary" "$target"
trap - EXIT
'@.Replace('__DOCUMENT_ROOT__', $Script:DocumentRoot).Replace('__UPLOAD_ROOT__', $RemoteUploadRoot).Replace('__CONFIG_PATH__', $RemoteConfigPath)
    $setEnv = Invoke-SshCapture -RemoteCommand $setEnvCommand
    Assert-NativeSuccess $setEnv 'Public .htaccess yapılandırması güncellenemedi.'
    Set-StepResult 9 PASS 'CHEF_CONFIG_FILE satırı ilgisiz kurallar korunarak tekilleştirildi.'

    Start-Step 10
    $databaseCommand = @'
set -eu
mkdir -p "__DOCUMENT_ROOT__/hvk/admin/uploads"
mkdir -p "__PRIVATE_ROOT__/hvk/admin"
chmod 700 "__PRIVATE_ROOT__/hvk" "__PRIVATE_ROOT__/hvk/admin"
cp "__DOCUMENT_ROOT__/hvk/admin/security.php" "__PRIVATE_ROOT__/hvk/admin/security.php"
cp "__DOCUMENT_ROOT__/hvk/admin/media-service.php" "__PRIVATE_ROOT__/hvk/admin/media-service.php"
chmod 0600 "__PRIVATE_ROOT__/hvk/admin/security.php" "__PRIVATE_ROOT__/hvk/admin/media-service.php"
CHEF_DB_CONFIG=__CONFIG_PATH__ __PHP__ "__PRIVATE_ROOT__/database/cli.php" migrate
CHEF_DB_CONFIG=__CONFIG_PATH__ __PHP__ "__PRIVATE_ROOT__/database/cli.php" seed
hardening="__DOCUMENT_ROOT__/media/.htaccess"
release_hardening="__UPLOAD_ROOT__/media.htaccess.release"
cp "$hardening" "$release_hardening"
restore_hardening() {
    cp "$release_hardening" "$hardening"
    chmod 0644 "$hardening"
}
trap restore_hardening EXIT
rm -f "$hardening"
CHEF_DB_CONFIG=__CONFIG_PATH__ __PHP__ "__PRIVATE_ROOT__/database/media-cli.php" prepare
restore_hardening
trap - EXIT
'@.Replace('__DOCUMENT_ROOT__', $Script:DocumentRoot).Replace('__PRIVATE_ROOT__', $RemotePrivateRoot).Replace('__CONFIG_PATH__', $RemoteConfigPath).Replace('__UPLOAD_ROOT__', $RemoteUploadRoot).Replace('__PHP__', $Script:RemotePhp)
    $databaseResult = Invoke-SshCapture -RemoteCommand $databaseCommand
    Assert-NativeSuccess $databaseResult 'Migration, seed veya medya hazırlığı başarısız oldu.'
    Set-StepResult 10 PASS 'Migration, seed ve media-cli prepare başarıyla tamamlandı.'

    Start-Step 11
    $recoveryCommand = "DOCUMENT_ROOT=$($Script:DocumentRoot) CHEF_CONFIG_FILE=$RemoteConfigPath $Script:RemotePhp -d variables_order=EGPCS $RemotePrivateRoot/database/recover-owner.php"
    if ($SkipOwner) {
        $ownerCommand = "ssh.exe -tt -i `"$KeyPath`" -p $SshPort -o BatchMode=yes -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new $RemoteTarget `"$recoveryCommand`""
        Write-Host "Owner oluşturma komutu: $ownerCommand"
        Set-StepResult 11 MANUAL 'Owner kurtarma kullanıcı tarafından interaktif SSH oturumunda çalıştırılmalıdır.'
    }
    else {
        Write-Host 'Sahip kullanıcı adını ve yeni parolayı uzak terminal istemine girin.'
        & ssh.exe -tt -i $KeyPath -p $SshPort -o BatchMode=yes -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new $RemoteTarget $recoveryCommand
        if ($LASTEXITCODE -ne 0) {
            throw 'Sahip kurtarma işlemi başarısız oldu.'
        }
        Set-StepResult 11 PASS 'Sahip kurtarma aracı interaktif SSH oturumunda tamamlandı.'
    }

    Start-Step 12
    $cronLine = "*/5 * * * * CHEF_DB_CONFIG=$RemoteConfigPath DOCUMENT_ROOT=$($Script:DocumentRoot) $Script:RemotePhp $RemotePrivateRoot/database/media-cli.php cleanup >/dev/null 2>&1"
    $cronCommand = @'
set -eu
if ! command -v crontab >/dev/null 2>&1; then
    exit 3
fi
temporary="$(mktemp)"
trap 'rm -f "$temporary"' EXIT
crontab -l 2>/dev/null | grep -Fv '# chef-media-cleanup' | grep -Fv '__PRIVATE_ROOT__/database/media-cli.php cleanup' > "$temporary" || true
printf '%s\n%s\n' '# chef-media-cleanup' '__CRON_LINE__' >> "$temporary"
crontab "$temporary"
'@.Replace('__PRIVATE_ROOT__', $RemotePrivateRoot).Replace('__CRON_LINE__', $cronLine)
    $cronResult = Invoke-SshCapture -RemoteCommand $cronCommand
    if ($cronResult.ExitCode -eq 3) {
        Write-Host 'Hostinger cron paneline şu satırı ekleyin:'
        Write-Host $cronLine
        Set-StepResult 12 PASS 'crontab yok. Hostinger cron paneli için kesin satır yazdırıldı.'
    }
    elseif ($cronResult.ExitCode -eq 0) {
        Set-StepResult 12 PASS 'Beş dakikalık cleanup cron kaydı tekilleştirilerek kuruldu.'
    }
    else {
        throw 'Medya cleanup cron kaydı kurulamadı.'
    }

    Start-Step 13
    $adminResponse = Invoke-HttpRequest -Url "https://$SiteHost/hvk/admin/login.php"
    if ($adminResponse.StatusCode -eq 503) {
        throw 'Admin giriş sayfası 503 döndürdü. LiteSpeed SetEnv görünürlüğünü kontrol edin.'
    }
    if ($adminResponse.ExitCode -ne 0 -or $adminResponse.StatusCode -ne 200) {
        throw 'Admin giriş sayfası HTTPS üzerinden 200 döndürmedi.'
    }
    if ($adminResponse.Body -match 'Warning:|Fatal error') {
        throw 'Admin giriş yanıtı PHP hata metni içeriyor.'
    }

    New-Item -ItemType Directory -Path $httpTestRoot | Out-Null
    $testFileNames = @('upload-test.php', 'image.php.jpg', 'upload-test.phtml', 'upload-test.phar')
    foreach ($fileName in $testFileNames) {
        [System.IO.File]::WriteAllText((Join-Path $httpTestRoot $fileName), "<?php echo 'EXECUTED';", [System.Text.UTF8Encoding]::new($false))
    }
    $remoteMediaRoot = "$($Script:DocumentRoot)/media"
    try {
        foreach ($fileName in $testFileNames) {
            Assert-NativeSuccess (Invoke-Scp (Join-Path $httpTestRoot $fileName) "$remoteMediaRoot/$fileName") "Yürütme test dosyası yüklenemedi: $fileName"
        }
        foreach ($fileName in $testFileNames) {
            $response = Invoke-HttpRequest -Url "https://$SiteHost/media/$fileName"
            if ($response.ExitCode -ne 0) {
                throw "Yürütme testi HTTP isteği başarısız: $fileName"
            }
            if ($response.Body -match 'EXECUTED') {
                throw "Public medya yürütme koruması başarısız: $fileName"
            }
        }
        $listingResponse = Invoke-HttpRequest -Url "https://$SiteHost/media/"
        if ($listingResponse.ExitCode -ne 0) {
            throw 'Public medya dizini HTTP kontrolü başarısız.'
        }
        if ($listingResponse.Body -match '(?i)<title>\s*Index of|Directory listing for|upload-test\.php') {
            throw 'Public medya dizin listesi erişilebilir.'
        }
    }
    finally {
        $null = Invoke-SshCapture -RemoteCommand "rm -f $remoteMediaRoot/upload-test.php $remoteMediaRoot/image.php.jpg $remoteMediaRoot/upload-test.phtml $remoteMediaRoot/upload-test.phar"
    }
    Set-StepResult 13 PASS 'Admin 200 döndürdü; PHP hata metni, upload yürütmesi ve dizin listesi görülmedi.'

    Start-Step 14
    $remoteReadonlyPath = "$($Script:DocumentRoot)/database/tests/readonly-check.php"
    $prepareReadonly = Invoke-SshCapture -RemoteCommand "mkdir -p $($Script:DocumentRoot)/database/tests && chmod 700 $($Script:DocumentRoot)/database/tests"
    Assert-NativeSuccess $prepareReadonly 'Readonly kontrol dizini hazırlanamadı.'
    try {
        Assert-NativeSuccess (Invoke-Scp $ReadonlyCheck $remoteReadonlyPath) 'Readonly kontrol dosyası yüklenemedi.'
    $readonlyCommand = @'
set -eu
credentials="$(CHEF_CONFIG_FILE=__CONFIG_PATH__ __PHP__ -r '$c=require getenv("CHEF_CONFIG_FILE"); echo json_encode(["readonly"=>$c["database"]], JSON_THROW_ON_ERROR);')"
printf '%s' "$credentials" | CHEF_CONFIG_FILE=__CONFIG_PATH__ CHEF_TEST_CREDENTIALS=/dev/stdin __PHP__ "__READONLY_PATH__" row-counts
unset credentials
'@.Replace('__CONFIG_PATH__', $RemoteConfigPath).Replace('__PHP__', $Script:RemotePhp).Replace('__READONLY_PATH__', $remoteReadonlyPath)
        $readonlyResult = Invoke-SshCapture -RemoteCommand $readonlyCommand
        Assert-NativeSuccess $readonlyResult 'Readonly kontrolü başarısız oldu.'
        if ($readonlyResult.Text -notmatch '"check"\s*:\s*"row-counts"') {
            throw 'Readonly kontrolü beklenen sınırlı JSON yanıtını üretmedi.'
        }
    }
    finally {
        $null = Invoke-SshCapture -RemoteCommand "rm -f $remoteReadonlyPath; rmdir $($Script:DocumentRoot)/database/tests 2>/dev/null || true"
    }
    Set-StepResult 14 PASS 'Readonly row-counts kontrolü bir kez çalıştı ve sınırlı JSON üretti.'

    $incompleteRequiredSteps = @(1..14 | Where-Object { $Script:Steps[$_].Status -ne 'PASS' })
    if ($incompleteRequiredSteps.Count -eq 0) {
        $overallStatus = 'PASS'
        $exitCode = 0
    }
    else {
        $overallStatus = 'FAIL'
        $exitCode = 1
    }
}
catch {
    $Script:FailureMessage = [string]$_.Exception.Message
    if ($Script:CurrentStep -ge 1 -and $Script:CurrentStep -le 14) {
        Set-StepResult $Script:CurrentStep FAIL $Script:FailureMessage
    }
    Write-Error "Staging kurulumu durduruldu: $Script:FailureMessage" -ErrorAction Continue
}
finally {
    [Environment]::SetEnvironmentVariable('CHEF_SSH_PASSWORD', $null, 'Process')
    [Environment]::SetEnvironmentVariable('CHEF_DB_PASSWORD', $null, 'Process')
    if (Test-Path -LiteralPath $localTemporaryRoot) {
        $resolvedTemporary = [System.IO.Path]::GetFullPath($localTemporaryRoot)
        $systemTemporary = [System.IO.Path]::GetFullPath([System.IO.Path]::GetTempPath())
        if ($resolvedTemporary.StartsWith($systemTemporary, [StringComparison]::OrdinalIgnoreCase)) {
            Remove-Item -LiteralPath $resolvedTemporary -Recurse -Force -ErrorAction SilentlyContinue
        }
    }

    try {
        Set-StepResult 15 PASS 'Gizli değer içermeyen review ve PROJECT kaydı yazıldı.'
        Write-DeploymentRecords -OverallStatus $overallStatus
    }
    catch {
        Set-StepResult 15 FAIL 'Yerel review veya PROJECT kaydı yazılamadı.'
        $overallStatus = 'FAIL'
        $exitCode = 1
        Write-Error 'Yerel deployment raporu yazılamadı.' -ErrorAction Continue
    }

    if ($Script:UseFakeNativeCommands) {
        $harnessSteps = @(foreach ($number in ($Script:Steps.Keys | Sort-Object)) {
            [ordered]@{
                number = $number
                name = $Script:Steps[$number].Name
                status = $Script:Steps[$number].Status
                detail = $Script:Steps[$number].Detail
            }
        })
        $harnessResult = [ordered]@{
            scenario = $Script:HarnessScenario
            overallStatus = $overallStatus
            exitCode = $exitCode
            steps = $harnessSteps
        } | ConvertTo-Json -Depth 5
        [System.IO.File]::WriteAllText(
            (Join-Path $Script:HarnessRoot 'harness-result.json'),
            $harnessResult,
            [System.Text.UTF8Encoding]::new($false)
        )
    }

    Show-ResultTable
    if ($overallStatus -eq 'FAIL') {
        Show-PrivateLogTail
    }
    Write-Host "Genel sonuç: $overallStatus"
    Write-Host 'Staging testleri bitince hPanel Remote MySQL kaydını kaldırın.'
}

exit $exitCode

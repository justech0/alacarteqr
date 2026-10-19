# Local Development Environment

Verified: 2026-10-13T17:25:00+03:00.

## Runtimes and Tools

| Tool | Version / Location | Status | Details |
|---|---|---|---|
| OS | Windows (win32 x64) | VERIFIED | Windows 10/11 |
| Shell | PowerShell | VERIFIED | Default IDE shell |
| Git | 2.56.0.windows.2 | VERIFIED | Branch: feature/chef-redesign |
| Remote | origin | CONFIGURED | https://github.com/justech0/alacarteqr.git |
| Node.js | v26.8.1 | VERIFIED | Local Node runtime |
| npm | 11.19.0 | VERIFIED | Accessible via npm.cmd |
| PHP | 8.3.33 (cli) | VERIFIED | C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe |
| PDO MySQL | Module active | VERIFIED | Loaded in PHP 8.3.33 |
| fileinfo | Module active | VERIFIED | Loaded in PHP 8.3.33 |
| mbstring | Module active | VERIFIED | Loaded in PHP 8.3.33 |
| GD | Module active | VERIFIED | Loaded in PHP 8.3.33 |
| Database | MySQL 8.4.3 | ACTIVE | mysqld running (PID 5792, 6924) |
| Laragon Web Server | Apache (httpd) | ACTIVE | Ports 80 & 443 (PID 2060, 39880) |
| Laragon Document Root | C:\laragon\www | CONFIGURED | Default Laragon root |
| Laragon Project Link | C:\laragon\www\alacart | LINKED | Junction to C:\Users\Enes\Desktop\Alacart |
| Project Root | C:\Users\Enes\Desktop\Alacart | ACTIVE | Workspace root |
| Release Staging Root | C:\Users\Enes\Desktop\Alacart\public_html | PREPARED | Document root for release |

## Client and Model

- Client: Antigravity IDE
- Model: Gemini 3.8 Flash (High)
- Authentication: Client session active (tokens and private credentials protected)

## Preview Server

- Node Preview Command: `npx http-server . -p 8080 -c-1 --cors`
- Node Preview URL: `http://127.0.0.1:8080/index.html` (HTTP 200 OK)
- Laragon Apache URL: `http://localhost/alacart/index.html` (HTTP 200 OK)
- Frozen Baseline URL: `http://127.0.0.1:8080/chef-onizleme.html` (HTTP 200 OK)
- Server Status: Both preview routes verified and responsive

## Blockers and Pending Items

- Global PATH: PHP is not in system PATH; commands use full Laragon path or Laragon shell.
- Database Import: Legacy database `u220042353_havakuvvetleri.sql` deferred to Task 02.
- Codex discovery of newly installed skills marked pending until verified in Codex.

## Admin test access

- Admin URL: `http://127.0.0.1:38082/admin/login.php`.
- Launch command: `set CHEF_CONFIG_FILE=%TEMP%\chef-task05\private\config.php && C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe -S 127.0.0.1:38082 -t C:\Users\Enes\Desktop\Alacart`.
- Credentials file: `%TEMP%\chef-task05\private\admin.credentials`.
- The file contains the generated local administrator credentials. Do not publish it.
- Gemini must use this access contract during Task 06.
- Recreate access with `database/tests/setup-auth-test.php` when the disposable database changes.

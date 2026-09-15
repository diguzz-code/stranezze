$ErrorActionPreference = 'Stop'
$env:Path = [Environment]::GetEnvironmentVariable('Path', 'Machine') + ';' + [Environment]::GetEnvironmentVariable('Path', 'User')
$php = Get-Command php -CommandType Application -ErrorAction SilentlyContinue
if (-not $php) {
    $phpPath = where.exe php | Select-Object -First 1
    if ($phpPath) { $php = Get-Command $phpPath -CommandType Application }
}
if (-not $php) { throw 'PHP non trovato nel PATH. Apri un nuovo terminale dopo l installazione.' }
$phpDirectory = Split-Path $php.Source
$extensionDirectory = Join-Path $phpDirectory 'ext'
if (-not (Test-Path (Join-Path $extensionDirectory 'php_pdo_sqlite.dll'))) {
    throw "php_pdo_sqlite.dll non trovato in $extensionDirectory"
}
& $php.Source -c (Join-Path $PWD 'php.ini') -d "extension_dir=$extensionDirectory" (Join-Path $PWD 'database/init.php')
& $php.Source -c (Join-Path $PWD 'php.ini') -d "extension_dir=$extensionDirectory" -S 127.0.0.1:8000 router.php

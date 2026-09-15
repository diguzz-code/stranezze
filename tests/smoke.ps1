$ErrorActionPreference = 'Stop'
$env:Path = [Environment]::GetEnvironmentVariable('Path', 'Machine') + ';' + [Environment]::GetEnvironmentVariable('Path', 'User')
$php = Get-Command php -CommandType Application -ErrorAction SilentlyContinue
if (-not $php) {
    $phpPath = where.exe php | Select-Object -First 1
    if ($phpPath) { $php = Get-Command $phpPath -CommandType Application }
}
if (-not $php) { throw 'PHP non trovato nel PATH' }
$phpDirectory = Split-Path $php.Source
$extensionDirectory = Join-Path $phpDirectory 'ext'
& $php.Source -c (Join-Path $PWD 'php.ini') -d "extension_dir=$extensionDirectory" database/init.php | Out-Host
$server = Start-Process -FilePath $php.Source -WorkingDirectory $PWD -ArgumentList @('-c', (Join-Path $PWD 'php.ini'), '-d', "extension_dir=$extensionDirectory", '-S', '127.0.0.1:8099', 'router.php') -PassThru -WindowStyle Hidden
try {
    $ready = $false
    1..1000 | ForEach-Object {
        if (-not $ready) {
            try {
                Invoke-WebRequest -Uri 'http://127.0.0.1:8099/api?action=session' -UseBasicParsing | Out-Null
                $ready = $true
            } catch {
                # Il server PHP puo impiegare un istante ad aprire la porta.
            }
        }
    }
    if (-not $ready) { throw 'Server PHP non raggiungibile' }
    $webSession = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $login = Invoke-RestMethod -Uri 'http://127.0.0.1:8099/api?action=login' -Method Post -ContentType 'application/json' -Body $loginBody -WebSession $webSession
    $csrfHeaders = @{ 'X-CSRF-Token' = $login.csrf_token }
    $payload = @{ title = 'Test <script>'; content = 'Contenuto di prova'; category = 'altro'; observed_on = (Get-Date -Format 'yyyy-MM-dd'); place = ''; is_favorite = $false } | ConvertTo-Json
    $created = Invoke-RestMethod -Uri 'http://127.0.0.1:8099/api' -Method Post -ContentType 'application/json' -Headers $csrfHeaders -Body $payload -WebSession $webSession
    if (-not $created.item) { throw 'Creazione fallita' }
    $items = Invoke-RestMethod -Uri 'http://127.0.0.1:8099/api' -WebSession $webSession
    if ($items.items.Count -lt 1) { throw 'Elenco vuoto dopo la creazione' }
    $found = Invoke-RestMethod -Uri 'http://127.0.0.1:8099/api?q=%3Cscript%3E' -WebSession $webSession
    if ($found.items.Count -lt 1) { throw 'Ricerca fallita' }
    Write-Output 'Smoke test API superato.'
} finally {
    Stop-Process -Id $server.Id -Force -ErrorAction SilentlyContinue
}

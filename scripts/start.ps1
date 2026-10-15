$ErrorActionPreference = 'Stop'
$taskRoot = Split-Path -Parent $PSScriptRoot
Push-Location -LiteralPath $taskRoot
try {
    if (-not (Test-Path -LiteralPath 'webapp/.env')) { Copy-Item -LiteralPath 'webapp/.env.example' -Destination 'webapp/.env' }
    if (-not (Test-Path -LiteralPath 'webapp/database/database.sqlite')) { New-Item -ItemType File -Path 'webapp/database/database.sqlite' | Out-Null }
    docker compose run --rm app composer install --no-interaction --prefer-dist
    if ($LASTEXITCODE -ne 0) { throw 'A PHP-függőségek telepítése nem sikerült.' }
    $taskEnvText = Get-Content -LiteralPath 'webapp/.env' -Raw
    if ($taskEnvText -notmatch '(?m)^APP_KEY=.+$') {
        docker compose run --rm app php artisan key:generate --no-interaction
        if ($LASTEXITCODE -ne 0) { throw 'A titkosítási kulcs létrehozása nem sikerült.' }
    }
    Push-Location -LiteralPath 'webapp'
    try {
        npm ci --no-audit --no-fund
        if ($LASTEXITCODE -ne 0) { throw 'A felületi függőségek telepítése nem sikerült.' }
        npm run build
        if ($LASTEXITCODE -ne 0) { throw 'A felület fordítása nem sikerült.' }
    } finally { Pop-Location }
    docker compose run --rm app php artisan migrate --seed --force --no-interaction
    if ($LASTEXITCODE -ne 0) { throw 'Az adatbázis előkészítése nem sikerült.' }
    docker compose run --rm app php artisan buksza:push-keys
    if ($LASTEXITCODE -ne 0) { throw 'Az értesítési kulcs előkészítése nem sikerült.' }
    docker compose up -d app web scheduler
    if ($LASTEXITCODE -ne 0) { throw 'Az alkalmazás indítása nem sikerült.' }
    Write-Host 'Buksza: http://localhost:18080'
} finally { Pop-Location }

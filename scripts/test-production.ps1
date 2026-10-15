$ErrorActionPreference = 'Stop'
$taskRoot = Split-Path -Parent $PSScriptRoot
Push-Location -LiteralPath $taskRoot
try {
    $taskState = Join-Path $taskRoot ('test-results/production-' + [Guid]::NewGuid().ToString('N'))
    New-Item -ItemType Directory -Path (Join-Path $taskState 'data') -Force | Out-Null
    New-Item -ItemType File -Path (Join-Path $taskState 'data/qa.sqlite') | Out-Null
    New-Item -ItemType Directory -Path (Join-Path $taskState 'status') | Out-Null
    # Synthetic operational reports belong only to this disposable QA project.
    $taskTimestamp = [DateTime]::UtcNow.ToString("yyyy-MM-dd'T'HH:mm:ss'Z'")
    $taskReport = @{version=1;status='succeeded';trigger='manual';started_at=$taskTimestamp;finished_at=$taskTimestamp;bytes=4096} | ConvertTo-Json -Compress
    foreach ($taskReportName in @('backup.json','last-success.json')) {
        [IO.File]::WriteAllText((Join-Path $taskState "status/$taskReportName"), $taskReport, [Text.UTF8Encoding]::new($false))
    }
    $taskKey = [Convert]::ToBase64String([Security.Cryptography.RandomNumberGenerator]::GetBytes(32))
    $taskEnvironment = @"
APP_NAME=Buksza
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:$taskKey
APP_URL=https://localhost:18443
BUKSZA_SETUP_TOKEN=installer-test-token
APP_TIMEZONE=Europe/Budapest
APP_LOCALE=hu
DB_CONNECTION=sqlite
DB_DATABASE=/data/qa.sqlite
SESSION_DRIVER=database
SESSION_COOKIE=buksza_installer_qa
SESSION_SECURE_COOKIE=true
SESSION_ENCRYPT=true
CACHE_STORE=database
LOG_CHANNEL=stderr
"@
    [IO.File]::WriteAllText((Join-Path $taskState 'app.env'), $taskEnvironment.Replace("`r`n", "`n") + "`n", [Text.UTF8Encoding]::new($false))
    $env:BUKSZA_HOME = $taskState.Replace('\','/')
    $env:BUKSZA_DOMAIN = 'localhost'
    $env:BUKSZA_RELEASE = 'installer-qa'
    $env:BUKSZA_QA_CONFIG = (Join-Path $taskRoot 'webapp/tests/deployment').Replace('\','/')
    $taskCompose = @('compose', '-p', 'buksza-installer-qa', '-f', 'docker/production/compose.yaml', '-f', 'webapp/tests/deployment/compose.yaml')
    docker @taskCompose run --rm --no-deps app php artisan migrate --seed --force --no-interaction
    if ($LASTEXITCODE -ne 0) { throw 'Production QA migration failed.' }
    docker @taskCompose run --rm --no-deps app php artisan buksza:push-keys
    if ($LASTEXITCODE -ne 0) { throw 'Production QA push key generation failed.' }
    docker @taskCompose up -d app web scheduler caddy
    if ($LASTEXITCODE -ne 0) { throw 'Production QA startup failed.' }
    Push-Location -LiteralPath 'webapp'
    try {
        node tests/deployment/verify.mjs
        if ($LASTEXITCODE -ne 0) { throw 'Production QA browser verification failed.' }
    } finally { Pop-Location }
} finally {
    if ($taskCompose) { docker @taskCompose down --volumes }
    Pop-Location
}

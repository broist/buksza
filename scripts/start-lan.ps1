param(
    [Parameter(Mandatory = $true)]
    [System.Net.IPAddress]$Address
)

$ErrorActionPreference = 'Stop'
if ($Address.AddressFamily -ne [System.Net.Sockets.AddressFamily]::InterNetwork -or
    $Address.ToString() -eq '0.0.0.0' -or
    [System.Net.IPAddress]::IsLoopback($Address)) {
    throw 'A gép helyi hálózati IPv4-címét add meg.'
}
if (-not (Get-NetIPAddress -AddressFamily IPv4 -IPAddress $Address.ToString() -ErrorAction SilentlyContinue)) {
    throw 'Ez az IP-cím nem tartozik a géphez.'
}

$taskRoot = Split-Path -Parent $PSScriptRoot
$taskPreviousIp = $env:KASSZA_LAN_IP
Push-Location -LiteralPath $taskRoot
try {
    $env:KASSZA_LAN_IP = $Address.ToString()
    docker compose -f compose.yaml -f compose.lan.yaml up -d app web scheduler
    if ($LASTEXITCODE -ne 0) { throw 'A helyi hálózati elérés indítása nem sikerült.' }
    Write-Host "Telefonon, ugyanazon a hálózaton: http://${Address}:18080"
    Write-Host 'Ezen a gépen: http://localhost:18080'
} finally {
    $env:KASSZA_LAN_IP = $taskPreviousIp
    Pop-Location
}

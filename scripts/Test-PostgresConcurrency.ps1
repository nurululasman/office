param(
    [string]$EnvironmentFile = ".env"
)

$ErrorActionPreference = "Stop"
$officeRoot = Split-Path -Parent $PSScriptRoot
$officeEnvironmentPath = Join-Path $officeRoot $EnvironmentFile

if (-not (Test-Path -LiteralPath $officeEnvironmentPath)) {
    throw "Environment file tidak ditemukan: $officeEnvironmentPath"
}

$officeDatabaseKeys = @("DB_CONNECTION", "DB_HOST", "DB_PORT", "DB_DATABASE", "DB_USERNAME", "DB_PASSWORD")
$officeEnvironmentLines = Get-Content -LiteralPath $officeEnvironmentPath

foreach ($officeDatabaseKey in $officeDatabaseKeys) {
    $officeLine = $officeEnvironmentLines | Where-Object { $_ -match "^$officeDatabaseKey=" } | Select-Object -First 1
    if (-not $officeLine) {
        throw "$officeDatabaseKey wajib tersedia di $EnvironmentFile"
    }

    $officeValue = $officeLine.Substring($officeDatabaseKey.Length + 1).Trim('"')
    Set-Item -Path "Env:$officeDatabaseKey" -Value $officeValue
}

if ($env:DB_CONNECTION -ne "pgsql") {
    throw "OFF-0206 wajib dijalankan pada PostgreSQL; DB_CONNECTION saat ini: $env:DB_CONNECTION"
}

$env:OFFICE_RUN_PG_CONCURRENCY_TESTS = "true"

Push-Location $officeRoot
try {
    php artisan config:clear
    if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

    php artisan test --testsuite=Integration
    exit $LASTEXITCODE
}
finally {
    Pop-Location
}

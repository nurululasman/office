param(
    [Parameter(Mandatory = $true)]
    [string]$Manifest,
    [switch]$Apply
)

$ErrorActionPreference = 'Stop'

php artisan test tests/Feature/InitialDataCutoverTest.php
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

php artisan office:initial-data:apply $Manifest
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

if ($Apply) {
    php artisan office:initial-data:apply $Manifest --apply
    if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
}

exit 0

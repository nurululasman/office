param(
    [switch]$Production
)

$ErrorActionPreference = 'Stop'

php artisan test tests/Feature/BackupOperationsTest.php
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

php artisan office:operations:check
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

if ($Production) {
    php artisan office:backup:check --production
} else {
    php artisan office:backup:check
}
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

php artisan schedule:list
exit $LASTEXITCODE

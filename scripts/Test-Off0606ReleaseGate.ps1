param(
    [Parameter(Mandatory = $true)]
    [string]$BaseUrl
)

$ErrorActionPreference = 'Stop'

php artisan office:release:check --production
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

php artisan office:smoke --url=$BaseUrl
exit $LASTEXITCODE

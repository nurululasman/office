param(
    [string]$EnvironmentFile = ".env",
    [switch]$SkipChrome
)

$ErrorActionPreference = "Stop"
$officeRoot = Split-Path -Parent $PSScriptRoot
$startedAt = Get-Date

function Invoke-Gate {
    param([string]$Name, [scriptblock]$Action)

    Write-Host "[$Name] starting"
    $gateStartedAt = Get-Date
    & $Action
    if ($LASTEXITCODE -ne 0) {
        throw "Gate $Name gagal dengan exit code $LASTEXITCODE."
    }
    $duration = [math]::Round(((Get-Date) - $gateStartedAt).TotalSeconds, 2)
    Write-Host "[$Name] passed in ${duration}s"
}

Push-Location $officeRoot
try {
    Invoke-Gate "regression" { php artisan test }
    Invoke-Gate "postgres-concurrency" {
        powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts\Test-PostgresConcurrency.ps1 -EnvironmentFile $EnvironmentFile
    }

    if (-not $SkipChrome) {
        $previousPdfGate = $env:OFFICE_RUN_PDF_RENDERER_TESTS
        try {
            $env:OFFICE_RUN_PDF_RENDERER_TESTS = "true"
            Invoke-Gate "chrome-pdf" {
                php artisan test tests\Feature\QuotationPdfGenerationTest.php --filter=real_chrome_renderer
            }
        }
        finally {
            $env:OFFICE_RUN_PDF_RENDERER_TESTS = $previousPdfGate
        }
    }

    $totalDuration = [math]::Round(((Get-Date) - $startedAt).TotalSeconds, 2)
    Write-Host "OFF-0601 release gate passed in ${totalDuration}s."
}
finally {
    Pop-Location
}

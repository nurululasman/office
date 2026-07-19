param(
    [string]$BaseUrl,
    [switch]$SkipReleaseGate,
    [switch]$RequireBackupEvidence
)

$ErrorActionPreference = 'Stop'

function Invoke-Gate {
    param([string]$Label, [scriptblock]$Command)

    Write-Host "[RUN] $Label"
    & $Command
    if ($LASTEXITCODE -ne 0) {
        throw "$Label gagal dengan exit code $LASTEXITCODE."
    }
    Write-Host "[PASS] $Label"
}

if (-not $SkipReleaseGate) {
    Invoke-Gate 'OFF-0601 release regression' {
        powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts\Test-Off0601ReleaseGate.ps1
    }
}

Invoke-Gate 'Security configuration' { php artisan office:security:check }
Invoke-Gate 'Operational dependencies' { php artisan office:operations:check }

if ($RequireBackupEvidence) {
    Invoke-Gate 'Production backup and restore evidence' { php artisan office:backup:check --production }
}

Invoke-Gate 'Migration status' { php artisan migrate:status }

if ($BaseUrl) {
    $target = $BaseUrl.TrimEnd('/')
    foreach ($path in @('/health/live', '/health/ready', '/auth/login')) {
        $response = Invoke-WebRequest -Uri "$target$path" -MaximumRedirection 0 -SkipHttpErrorCheck
        if ($path -like '/health/*' -and $response.StatusCode -ne 200) {
            throw "$path mengembalikan HTTP $($response.StatusCode)."
        }
        if ($path -eq '/auth/login' -and $response.StatusCode -notin @(301, 302, 303, 307, 308)) {
            throw "$path tidak mengarahkan pengguna ke SSO (HTTP $($response.StatusCode))."
        }
        Write-Host "[PASS] $path HTTP $($response.StatusCode)"
    }
}

Write-Host '[PASS] OFF-0604 automated UAT preflight selesai.'

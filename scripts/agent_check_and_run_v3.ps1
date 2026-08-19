<#
agent_check_and_run_v3.ps1
Versión avanzada: reintentos, descarga utils, métrica de imágenes, compresión, rotación de logs y manifest JSON.
#>
param(
    [string]$ConfigPath = "$env:USERPROFILE\Desktop\pruebablender\scripts\agent_config.json"
)

function Read-Config { param($p); if (Test-Path $p) { return (Get-Content $p -Raw | ConvertFrom-Json) } ; return $null }
$config = Read-Config $ConfigPath
if (-not $config) { Write-Error "No config encontrado en $ConfigPath"; exit 1 }

$repo = $config.RepoRoot
$examplesDir = $config.ExamplesDir
$logsDir = Join-Path $repo "docs\agent_logs"
New-Item -ItemType Directory -Path $logsDir -Force | Out-Null
$experimentsLog = Join-Path $logsDir "experiments.log"
$experimentsJson = Join-Path $logsDir "experiments.json"
$summaryMd = Join-Path $logsDir "summary.md"

function Rotate-LogIfNeeded {
    param($path, $maxMB)
    if (-not (Test-Path $path)) { return }
    $sizeMB = (Get-Item $path).Length / 1MB
    if ($sizeMB -gt $maxMB) {
        $bak = "$path.$((Get-Date).ToString('yyyyMMdd_HHmmss')).bak"
        Move-Item -Path $path -Destination $bak -Force
        New-Item -Path $path -ItemType File -Force | Out-Null
        Write-Host "Rotated log to $bak"
    }
}
Rotate-LogIfNeeded -path $experimentsLog -maxMB $config.LogRotateMB

# Ensure utils
$utilsUrl = $config.UtilsRepoRaw
$utilsDest = Join-Path $examplesDir "utils.py"
function Invoke-WithRetry($script, $attempts) {
    $i=0; do { try { & $script; return $true } catch { $i++; Start-Sleep -Seconds (2*$i) } } while ($i -lt $attempts)
    return $false
}
if (-not (Test-Path $utilsDest)) {
    Write-Host "Descargando utils.py..."
    $ok = $false
    for ($i=1; $i -le $config.Retries; $i++) {
        try { Invoke-WebRequest -Uri $utilsUrl -OutFile $utilsDest -UseBasicParsing -ErrorAction Stop; $ok=$true; break } catch { Write-Host "Intento $i fallo: $($_.Exception.Message)"; Start-Sleep -Seconds (2*$i) }
    }
    if (-not $ok) { Write-Host "No se pudo obtener utils.py; algunos scripts fallarán." }
}

# Find blender
function Find-Blender { param($paths) foreach ($p in $paths) { if (-not $p) { continue }; if (Test-Path $p -PathType Leaf -and (Split-Path $p -Leaf).ToLower() -eq 'blender.exe') { return (Resolve-Path $p).Path }; if (Test-Path $p -PathType Container) { $f = Get-ChildItem -Path $p -Recurse -Filter blender.exe -ErrorAction SilentlyContinue | Select-Object -First 1; if ($f) { return $f.FullName } } }; $cmd = (Get-Command blender -ErrorAction SilentlyContinue).Source; if ($cmd) { return $cmd } ; return $null }
$blender = Find-Blender $config.BlenderPaths
if (-not $blender) { Write-Error "No se encontró blender.exe"; exit 0 }
Write-Host "Usando Blender: $blender"

# Enumerar scripts
$scripts = Get-ChildItem -Path $examplesDir -Filter "*.py" -File | Where-Object { $_.Name -notmatch '^utils' } | Sort-Object Name
if ($scripts.Count -eq 0) { Write-Host "No hay scripts"; exit 0 }

$results = @()
foreach ($s in $scripts) {
    $name = $s.Name
    $timestamp = (Get-Date).ToString('yyyyMMdd_HHmmss')
    $outDir = Join-Path $examplesDir ("output\" + $s.BaseName + "_" + $timestamp)
    New-Item -ItemType Directory -Path $outDir -Force | Out-Null
    $outImg = Join-Path $outDir ($s.BaseName + ".png")

    $attempt=0; $exitCode = -1; $status = 'ERROR'; $lastStdErr = ''
    while ($attempt -lt $config.Retries) {
        $attempt++
        Write-Host "[$name] intento $attempt/$($config.Retries)"
        $args = "--background --python `"$($s.FullName)`" -- `"$outImg`" $($config.DefaultResolution) $($config.DefaultSamples)"
        $proc = Start-Process -FilePath $blender -ArgumentList $args -NoNewWindow -PassThru -RedirectStandardOutput (Join-Path $outDir "stdout.txt") -RedirectStandardError (Join-Path $outDir "stderr.txt") -Wait
        $exitCode = $proc.ExitCode
        $stderr = Get-Content -Path (Join-Path $outDir "stderr.txt") -ErrorAction SilentlyContinue -Raw
        $stdout = Get-Content -Path (Join-Path $outDir "stdout.txt") -ErrorAction SilentlyContinue -Raw
        if ($exitCode -eq 0) { $status='OK'; break } else { Write-Host "Salida con código $exitCode; stderr: $stderr" }
    }

    # compute image metrics if image exists
    $metrics = $null
    if (Test-Path $outImg) {
        $metricsJson = & "$(Join-Path $repo 'scripts\compute_image_metrics.ps1')" -ImagePath $outImg 2>$null
        try { $metrics = (ConvertFrom-Json $metricsJson) } catch { $metrics = $metricsJson }
    }

    # append to JSON manifest
    $entry = @{ timestamp=(Get-Date -Format o); script=$name; blender=$blender; exitCode=$exitCode; status=$status; outDir=$outDir; metrics=$metrics }
    $entry | ConvertTo-Json -Depth 6 | Out-File -FilePath (Join-Path $outDir "result.json") -Encoding utf8

    # append to experiments.log TSV
    $logLine = "$(Get-Date -Format o)`t$name`t$blender`t$exitCode`t$outDir`t$status`" + "`t" + ((if ($null -ne $metrics) { ('mse=' + ($metrics.mse -as [string])) } else { '' }))
    Add-Content -Path $experimentsLog -Value $logLine

    # append to experiments.json (ndjson)
    Add-Content -Path $experimentsJson -Value (ConvertTo-Json $entry -Depth 6)

    # compress outputs
    try { & "$(Join-Path $repo 'scripts\compress_outputs.ps1')" -TargetDir $outDir | Out-Null } catch {}

    $results += $entry
}

# write summary.md
$sum = @()
$sum += "# Agent run summary — $(Get-Date -Format o)"
foreach ($r in $results) { $sum += "- $($r.script): $($r.status) (exit $($r.exitCode)) — $($r.outDir)" }
$sum | Out-File -FilePath $summaryMd -Encoding utf8
Write-Host "V3 run complete. Summary at $summaryMd"
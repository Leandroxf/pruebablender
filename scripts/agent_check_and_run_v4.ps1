<#
agent_check_and_run_v4.ps1
Versión v4: ejecución paralela controlada (MaxConcurrency), reintentos por job, metrics post-processing y notificación webhook opcional.
Uso: powershell -ExecutionPolicy Bypass -File scripts\agent_check_and_run_v4.ps1
#>
param(
    [string]$ConfigPath = "$env:USERPROFILE\Desktop\pruebablender\scripts\agent_config.json"
)

function Read-Config { param($p); return (Get-Content $p -Raw | ConvertFrom-Json) }
$config = Read-Config $ConfigPath
$repo = $config.RepoRoot
$examplesDir = $config.ExamplesDir
$logsDir = Join-Path $repo "docs\agent_logs"
New-Item -ItemType Directory -Path $logsDir -Force | Out-Null
$experimentsLog = Join-Path $logsDir "experiments.log"
$experimentsJson = Join-Path $logsDir "experiments.json"
$summaryMd = Join-Path $logsDir "summary.md"

# Find blender (reuse simple strategy)
function Find-Blender { param($paths) foreach ($p in $paths) { if (-not $p) { continue }; if (Test-Path $p -PathType Leaf -and (Split-Path $p -Leaf).ToLower() -eq 'blender.exe') { return (Resolve-Path $p).Path }; if (Test-Path $p -PathType Container) { $f = Get-ChildItem -Path $p -Recurse -Filter blender.exe -ErrorAction SilentlyContinue | Select-Object -First 1; if ($f) { return $f.FullName } } }; $cmd = (Get-Command blender -ErrorAction SilentlyContinue).Source; if ($cmd) { return $cmd } ; return $null }
$blender = Find-Blender $config.BlenderPaths
if (-not $blender) { Write-Error "blender.exe no encontrado"; exit 0 }
Write-Host "Blender: $blender"

# Prepare scripts list
$scripts = Get-ChildItem -Path $examplesDir -Filter "*.py" -File | Where-Object { $_.Name -notmatch '^utils' } | Sort-Object Name
if ($scripts.Count -eq 0) { Write-Host "No scripts encontrados"; exit 0 }

$maxConcurrency = [int]$config.MaxConcurrency
$jobs = @()
$results = @()

function Run-ScriptJob {
    param($scriptPath, $outDir, $resolution, $samples, $retries, $blenderExe)
    $scriptName = Split-Path $scriptPath -Leaf
    $attempt = 0; $exitCode = -1
    while ($attempt -lt $retries) {
        $attempt++
        $outImg = Join-Path $outDir ($([IO.Path]::GetFileNameWithoutExtension($scriptName)) + ".png")
        $stdOutFile = Join-Path $outDir "stdout.txt"
        $stdErrFile = Join-Path $outDir "stderr.txt"
        $args = "--background --python `"$scriptPath`" -- `"$outImg`" $resolution $samples"
        $proc = Start-Process -FilePath $blenderExe -ArgumentList $args -NoNewWindow -PassThru -RedirectStandardOutput $stdOutFile -RedirectStandardError $stdErrFile -Wait
        $exitCode = $proc.ExitCode
        if ($exitCode -eq 0) { break }
        Start-Sleep -Seconds (5 * $attempt)
    }
    return @{ script = $scriptName; exitCode = $exitCode; outDir = $outDir; stdout = $stdOutFile; stderr = $stdErrFile }
}

# launch with throttling
foreach ($s in $scripts) {
    while ((Get-Job -State Running).Count -ge $maxConcurrency) { Start-Sleep -Seconds 1 }
    $timestamp = (Get-Date).ToString('yyyyMMdd_HHmmss')
    $outDir = Join-Path $examplesDir ("output\" + $s.BaseName + "_" + $timestamp)
    New-Item -ItemType Directory -Path $outDir -Force | Out-Null
    $job = Start-Job -ArgumentList $s.FullName, $outDir, $config.DefaultResolution, $config.DefaultSamples, $config.Retries, $blender -ScriptBlock {
        param($scriptPath, $outDir, $resolution, $samples, $retries, $blenderExe)
        Import-Module -Name PSFramework -ErrorAction SilentlyContinue
        & $using:RunScript # placeholder
    }
    # Because scriptblocks can't easily reference local Run-ScriptJob, store meta and run a wrapper
    $job = Start-Job -ScriptBlock {
        param($scriptPath, $outDir, $resolution, $samples, $retries, $blenderExe)
        function RunInner {
            param($scriptPath,$outDir,$resolution,$samples,$retries,$blenderExe)
            $attempt=0;$exit= -1
            while ($attempt -lt $retries) {
                $attempt++
                $outImg = Join-Path $outDir ((Split-Path $scriptPath -Leaf).Replace('.py','.png'))
                $stdOutFile = Join-Path $outDir "stdout.txt"
                $stdErrFile = Join-Path $outDir "stderr.txt"
                $args = "--background --python `"$scriptPath`" -- `"$outImg`" $resolution $samples"
                $proc = Start-Process -FilePath $blenderExe -ArgumentList $args -NoNewWindow -PassThru -RedirectStandardOutput $stdOutFile -RedirectStandardError $stdErrFile -Wait
                $exit = $proc.ExitCode
                if ($exit -eq 0) { return @{ script=(Split-Path $scriptPath -Leaf); exitCode=$exit; outDir=$outDir; stdout=$stdOutFile; stderr=$stdErrFile } }
                Start-Sleep -Seconds (5 * $attempt)
            }
            return @{ script=(Split-Path $scriptPath -Leaf); exitCode=$exit; outDir=$outDir; stdout=$stdOutFile; stderr=$stdErrFile }
        }
        RunInner $args[0] $args[1] $args[2] $args[3] $args[4] $args[5]
    } -ArgumentList $s.FullName, $outDir, $config.DefaultResolution, $config.DefaultSamples, $config.Retries, $blender
    $jobs += $job
}

# wait and collect
Wait-Job -Job $jobs
$jobResults = Receive-Job -Job $jobs

foreach ($jr in $jobResults) {
    $entry = @{ timestamp=(Get-Date -Format o); script=$jr.script; exitCode=$jr.exitCode; outDir=$jr.outDir; status= (if ($jr.exitCode -eq 0) { 'OK' } else { 'ERROR' }) }
    $json = $entry | ConvertTo-Json -Depth 6
    Add-Content -Path $experimentsJson -Value $json
    $logLine = "$(Get-Date -Format o)`t$($entry.script)`t$($entry.exitCode)`t$($entry.outDir)`t$($entry.status)"
    Add-Content -Path $experimentsLog -Value $logLine
    # post webhook if configured
    if ($config.WebhookUrl -and $config.WebhookUrl -ne '') {
        try { Invoke-RestMethod -Uri $config.WebhookUrl -Method Post -Body ($json) -ContentType 'application/json' -ErrorAction SilentlyContinue } catch {}
    }
}
# summary
$sum = @(); $sum += "# Agent run summary — $(Get-Date -Format o)"; foreach ($jr in $jobResults) { $sum += "- $($jr.script): $(if ($jr.exitCode -eq 0){'OK'}else{'ERROR'}) -> $($jr.outDir)" }
$sum | Out-File -FilePath $summaryMd -Encoding utf8
Write-Host "v4 run complete. Summary: $summaryMd"

<#
agent_check_and_run_v2.ps1
Versión mejorada de agent_check_and_run: lectura de configuración, logging por experimento, captura de stdout/stderr a archivos, comprobación/descarga de utils.py, y resumen Markdown.
#>
param(
    [string]$ConfigPath = "$env:USERPROFILE\Desktop\pruebablender\scripts\agent_config.json"
)

function Read-Config {
    param([string]$path)
    if (Test-Path $path) {
        try { return (Get-Content $path -Raw | ConvertFrom-Json) } catch { return $null }
    }
    return $null
}

$config = Read-Config -path $ConfigPath
if (-not $config) {
    Write-Host "No config found at $ConfigPath — usando valores por defecto"
    $config = [PSCustomObject]@{
        RepoRoot = "$env:USERPROFILE\Desktop\pruebablender";
        ExamplesDir = "$env:USERPROFILE\Desktop\pruebablender\examples";
        MinMinutesBetweenRuns = 60;
        BlenderPaths = @("$env:USERPROFILE\Desktop\pruebablender\blender", "C:\\Program Files\\Blender Foundation", "C:\\Program Files (x86)\\Blender Foundation", "$env:USERPROFILE\AppData\Local\Programs")
        DefaultResolution = 100;
        DefaultSamples = 32;
        UtilsRepoRaw = "https://raw.githubusercontent.com/yuki-koyama/blender-cli-rendering/master/utils.py"
    }
}

$repo = $config.RepoRoot
$examplesDir = $config.ExamplesDir
$logsDir = Join-Path $repo "docs\agent_logs"
New-Item -ItemType Directory -Path $logsDir -Force | Out-Null
$experimentsLog = Join-Path $logsDir "experiments.log"
$summaryMd = Join-Path $logsDir "summary.md"

# Throttle
if (Test-Path $experimentsLog) {
    $lastLine = Get-Content $experimentsLog -Tail 1
    if ($lastLine) {
        try {
            $lastTime = [datetime]::Parse($lastLine.Split("`t")[0])
            if ((Get-Date) - $lastTime -lt (New-TimeSpan -Minutes $config.MinMinutesBetweenRuns)) {
                Write-Host "Última ejecución demasiado reciente. Saliendo."; exit 0
            }
        } catch { }
    }
}

function Find-BlenderExe {
    param([string[]]$roots)
    foreach ($r in $roots) {
        if (-not $r) { continue }
        if (Test-Path $r -PathType Leaf -and (Split-Path $r -Leaf).ToLower() -eq 'blender.exe') { return (Resolve-Path $r).Path }
        if (Test-Path $r -PathType Container) {
            $found = Get-ChildItem -Path $r -Recurse -Filter blender.exe -ErrorAction SilentlyContinue | Sort-Object LastWriteTime -Descending | Select-Object -First 1
            if ($found) { return $found.FullName }
        }
    }
    # try PATH
    $which = (Get-Command blender -ErrorAction SilentlyContinue).Source
    if ($which) { return $which }
    return $null
}

$blenderExe = Find-BlenderExe -roots $config.BlenderPaths
if (-not $blenderExe) { Write-Host "No se encontró Blender. Saliendo."; exit 0 }
Write-Host "Usando Blender: $blenderExe"

# Asegurar utils.py en examplesDir (necesario para scripts descargados)
$utilsPath = Join-Path $examplesDir "utils.py"
if (-not (Test-Path $utilsPath)) {
    Write-Host "utils.py no encontrado en $examplesDir — intentando descargar desde $($config.UtilsRepoRaw)"
    try {
        Invoke-WebRequest -Uri $config.UtilsRepoRaw -OutFile $utilsPath -UseBasicParsing -ErrorAction Stop
        Write-Host "utils.py descargado a: $utilsPath"
    } catch {
        Write-Host "No se pudo descargar utils.py: $_.Exception.Message"
    }
} else { Write-Host "utils.py presente: $utilsPath" }

# Enumerar scripts
$scriptFiles = Get-ChildItem -Path $examplesDir -Filter "*.py" -File | Where-Object { $_.Name -notmatch '^utils' } | Sort-Object Name
if ($scriptFiles.Count -eq 0) { Write-Host "No hay scripts en $examplesDir"; exit 0 }

$runResults = @()
foreach ($s in $scriptFiles) {
    $scriptName = $s.Name
    $timestamp = (Get-Date).ToString('yyyyMMdd_HHmmss')
    $outDirScript = Join-Path $examplesDir ("output\" + $s.BaseName + "_" + $timestamp)
    New-Item -ItemType Directory -Path $outDirScript -Force | Out-Null
    $outFile = Join-Path $outDirScript ($s.BaseName + ".png")

    $args = "--background --python `"$($s.FullName)`" -- `"$outFile`" $($config.DefaultResolution) $($config.DefaultSamples)"

    $stdoutFile = Join-Path $outDirScript "stdout.txt"
    $stderrFile = Join-Path $outDirScript "stderr.txt"

    Write-Host "Ejecutando: $scriptName -> $outFile"

    $procInfo = New-Object System.Diagnostics.ProcessStartInfo
    $procInfo.FileName = $blenderExe
    $procInfo.Arguments = $args
    $procInfo.RedirectStandardOutput = $true
    $procInfo.RedirectStandardError = $true
    $procInfo.UseShellExecute = $false
    $procInfo.CreateNoWindow = $true

    $proc = New-Object System.Diagnostics.Process
    $proc.StartInfo = $procInfo
    $proc.Start() | Out-Null
    $stdOut = $proc.StandardOutput.ReadToEnd()
    $stdErr = $proc.StandardError.ReadToEnd()
    $proc.WaitForExit()
    $exitCode = $proc.ExitCode

    Set-Content -Path $stdoutFile -Value $stdOut -Encoding UTF8
    Set-Content -Path $stderrFile -Value $stdErr -Encoding UTF8

    $status = if ($exitCode -eq 0) { 'OK' } else { 'ERROR' }
    $logLine = "$(Get-Date -Format o)`t$scriptName`t$blenderExe`t$exitCode`t$outDirScript`t$status`t$stderrFile`t$stdoutFile"
    Add-Content -Path $experimentsLog -Value $logLine

    $runResults += [PSCustomObject]@{ Script=$scriptName; ExitCode=$exitCode; Status=$status; OutDir=$outDirScript; StdErr=$stdErr }
}

# Actualizar resumen Markdown
$summary = @()
$summary += "# Agent run summary — $(Get-Date -Format o)"
$summary += ""
foreach ($r in $runResults) {
    $summary += "- **$($r.Script)**: $($r.Status) (Exit $($r.ExitCode)) — output: $($r.OutDir)"
}
$summary | Out-File -FilePath $summaryMd -Encoding UTF8
Write-Host "Resumen escrito en: $summaryMd"
Write-Host "Ejecución v2 completada."

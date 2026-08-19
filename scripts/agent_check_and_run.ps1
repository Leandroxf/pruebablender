<#
agent_check_and_run.ps1
Busca un ejecutable de Blender en rutas conocidas y, si lo encuentra, ejecuta los scripts en examples/ para generar outputs y registrar experimentos.
Diseñado para Windows; ejecutar periódicamente (p.ej. como tarea programada) o manualmente.
#>
param(
    [int]$MinMinutesBetweenRuns = 60
)

$repo = "$env:USERPROFILE\Desktop\pruebablender"
$examplesDir = Join-Path $repo "examples"
$logsDir = Join-Path $repo "docs\agent_logs"
New-Item -ItemType Directory -Path $logsDir -Force | Out-Null
$experimentsLog = Join-Path $logsDir "experiments.log"

# Evitar ejecuciones muy frecuentes
if (Test-Path $experimentsLog) {
    $lastLine = Get-Content $experimentsLog -Tail 1
    if ($lastLine) {
        try {
            $lastTime = [datetime]::Parse($lastLine.Split("\t")[0])
            if ((Get-Date) - $lastTime -lt (New-TimeSpan -Minutes $MinMinutesBetweenRuns)) {
                Write-Host "Última ejecución fue hace menos de $MinMinutesBetweenRuns minutos. Saliendo."
                exit 0
            }
        } catch {
            # si parse falla, seguir
        }
    }
}

# Rutas candidatas para buscar blender.exe
$candidateRoots = @(
    Join-Path $repo "blender",
    "C:\\Program Files\\Blender Foundation",
    "C:\\Program Files (x86)\\Blender Foundation",
    "$env:USERPROFILE\AppData\Local\Programs",
    (Get-Command blender -ErrorAction SilentlyContinue | Select-Object -ExpandProperty Source -ErrorAction SilentlyContinue) 
) | Where-Object { $_ }

function Find-BlenderExe {
    param([string[]]$roots)
    foreach ($r in $roots) {
        if (-not $r) { continue }
        if (Test-Path $r -PathType Container) {
            $found = Get-ChildItem -Path $r -Recurse -Filter blender.exe -ErrorAction SilentlyContinue | Sort-Object LastWriteTime -Descending | Select-Object -First 1
            if ($found) { return $found.FullName }
        } else {
            # if $r is a file path
            if (Test-Path $r -PathType Leaf -and (Split-Path $r -Leaf).ToLower() -eq 'blender.exe') { return (Resolve-Path $r).Path }
        }
    }
    return $null
}

$blenderExe = Find-BlenderExe -roots $candidateRoots
if (-not $blenderExe) {
    Write-Host "No se encontró blender.exe en rutas candidatas. Saliendo."; exit 0
}
Write-Host "Blender encontrado en: $blenderExe"

# Preparar lista de scripts a ejecutar
$scriptFiles = Get-ChildItem -Path $examplesDir -Filter "*.py" -File | Where-Object { $_.Name -notmatch '^utils' } | Sort-Object Name
if ($scriptFiles.Count -eq 0) { Write-Host "No hay scripts .py en $examplesDir"; exit 0 }

foreach ($s in $scriptFiles) {
    $scriptName = $s.Name
    $timestamp = (Get-Date).ToString('yyyyMMdd_HHmmss')
    $outDir = Join-Path $examplesDir "output"
    $outDirScript = Join-Path $outDir ($s.BaseName + "_" + $timestamp)
    New-Item -ItemType Directory -Path $outDirScript -Force | Out-Null

    # Construir ruta de salida (archivo) para scripts que esperan <output>
    $outFile = Join-Path $outDirScript ($s.BaseName + ".png")

    # Comando y argumentos
    $args = "--background --python `"$($s.FullName)`" -- `"$outFile`" 100 32"
    Write-Host "Ejecutando script: $scriptName -> salida: $outFile"

    $startInfo = New-Object System.Diagnostics.ProcessStartInfo
    $startInfo.FileName = $blenderExe
    $startInfo.Arguments = $args
    $startInfo.RedirectStandardOutput = $true
    $startInfo.RedirectStandardError = $true
    $startInfo.UseShellExecute = $false
    $startInfo.CreateNoWindow = $true

    $proc = New-Object System.Diagnostics.Process
    $proc.StartInfo = $startInfo
    $proc.Start() | Out-Null
    $stdout = $proc.StandardOutput.ReadToEnd()
    $stderr = $proc.StandardError.ReadToEnd()
    $proc.WaitForExit()
    $exitCode = $proc.ExitCode

    # Registrar experimento
    $logLine = "$(Get-Date -Format o)\t$scriptName\t$blenderExe\t$exitCode\t$($outDirScript)\t$(if ($stderr) { 'ERR' } else { 'OK' })\t$($stderr -replace "\r|\n"," | ")\t$($stdout -replace "\r|\n"," | ")"
    Add-Content -Path $experimentsLog -Value $logLine

    Write-Host "Script $scriptName finalizado con código $exitCode. Registro añadido."
}

Write-Host "Ejecución automática completada."
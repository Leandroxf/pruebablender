<# run_agent_wrapper.ps1
Wrapper para ejecutar una versión del agente (v3 o v4) con modo dry-run y logging.
Uso: .\run_agent_wrapper.ps1 -Version v3 -DryRun
#>
param(
    [ValidateSet('v3','v4')][string]$Version = 'v3',
    [Switch]$DryRun,
    [Switch]$VerboseRun
)

$repo = Split-Path -Parent $MyInvocation.MyCommand.Definition
Set-Location -Path $repo

$scriptToRun = switch ($Version) {
    'v3' { Join-Path $repo 'scripts\agent_check_and_run_v3.ps1' }
    'v4' { Join-Path $repo 'scripts\agent_check_and_run_v4.ps1' }
}

if (-not (Test-Path $scriptToRun)) { Write-Error "Script seleccionado no existe: $scriptToRun"; exit 1 }

Write-Host "Seleccionado: $Version -> $scriptToRun"
Write-Host "DryRun: $($DryRun.IsPresent)"

$cmd = "powershell -NoProfile -ExecutionPolicy Bypass -File `"$scriptToRun`""
Write-Host "Comando: $cmd"

if ($DryRun) { Write-Host "DryRun activado — no se ejecutará."; exit 0 }

if ($VerboseRun) { Write-Host "Ejecutando con verbosidad..." }

# Ejecutar
& powershell -NoProfile -ExecutionPolicy Bypass -File $scriptToRun
$rc = $LASTEXITCODE
if ($rc -ne 0) { Write-Error "La ejecución devolvió código $rc" } else { Write-Host "Ejecución completada con éxito." }

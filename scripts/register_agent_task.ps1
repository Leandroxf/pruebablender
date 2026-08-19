<#
register_agent_task.ps1
Registra una tarea programada de Windows (usuario actual) para ejecutar agent_check_and_run.ps1 cada hora.
Ejecutar con privilegios de usuario normal. No se ejecuta automáticamente al crear el script: debes ejecutarlo manualmente.
#>
param(
    [string]$RepoRoot = "$env:USERPROFILE\Desktop\pruebablender",
    [string]$TaskName = "PruebaBlender-AgentRunner",
    [int]$EveryMinutes = 60
)

$script = Join-Path $RepoRoot "scripts\agent_check_and_run.ps1"
if (-not (Test-Path $script)) { Write-Error "No se encontró $script"; exit 1 }

$action = New-ScheduledTaskAction -Execute 'PowerShell.exe' -Argument "-NoProfile -WindowStyle Hidden -ExecutionPolicy Bypass -File `"$script`""
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date) -RepetitionInterval (New-TimeSpan -Minutes $EveryMinutes) -RepetitionDuration ([TimeSpan]::MaxValue)
$principal = New-ScheduledTaskPrincipal -UserId $env:USERNAME -LogonType S4U -RunLevel Limited

$task = New-ScheduledTask -Action $action -Trigger $trigger -Principal $principal -Settings (New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable)

try {
    Register-ScheduledTask -TaskName $TaskName -InputObject $task -Force
    Write-Host "Tarea programada '$TaskName' registrada para ejecutarse cada $EveryMinutes minutos."
} catch {
    Write-Error "Fallo al registrar la tarea: $_"
}

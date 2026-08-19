<#
run_example.ps1
Ejecuta un script de ejemplo con un binario de Blender específico.
Uso:
  .\run_example.ps1 -BlenderExe "C:\ruta\a\blender.exe" -Script "scripts\generate_simple_scene.py"
Si no se especifica Script, usa scripts\generate_simple_scene.py por defecto.
#>
param(
    [Parameter(Mandatory=$true)]
    [string]$BlenderExe,
    [string]$Script = "scripts\generate_simple_scene.py"
)

# Resolver rutas relativas respecto a la carpeta del repo (suponiendo se ejecuta desde scripts)
$repoRoot = Resolve-Path (Join-Path (Split-Path -Parent $MyInvocation.MyCommand.Definition) "..")
$scriptPath = Join-Path $repoRoot $Script

if (-not (Test-Path $BlenderExe)) {
    Write-Error "No se encontró blender.exe en: $BlenderExe"
    exit 1
}
if (-not (Test-Path $scriptPath)) {
    Write-Error "No se encontró el script: $scriptPath"
    exit 1
}

Write-Host "Ejecutando: $BlenderExe --background --python $scriptPath"
& $BlenderExe --background --python $scriptPath

if ($LASTEXITCODE -ne 0) {
    Write-Error "La ejecución devolvió código $LASTEXITCODE"
} else {
    Write-Host "Ejecución finalizada correctamente. Revisa scripts\output para resultados."
}

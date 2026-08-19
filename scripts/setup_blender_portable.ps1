<#
setup_blender_portable.ps1
Descarga y extrae una versión portable de Blender a una carpeta destino.
Uso rápido:
  .\setup_blender_portable.ps1 -Version 4.1.2 -Destination "$env:USERPROFILE\Desktop\pruebablender\blender"
#>
param(
    [string]$Version = "4.1.2",
    [string]$Destination = "$env:USERPROFILE\Desktop\pruebablender\blender"
)

$major, $minor, $patch = $Version.Split('.')
$rel = "Blender$($major).$($minor)"
$url = "https://download.blender.org/release/$rel/blender-$Version-windows-x64.zip"
$tempZip = Join-Path $env:TEMP "blender-$Version.zip"

Write-Host "Downloading Blender $Version from $url"
Invoke-WebRequest -Uri $url -OutFile $tempZip -UseBasicParsing

Write-Host "Extracting to $Destination"
New-Item -ItemType Directory -Path $Destination -Force | Out-Null
Expand-Archive -Path $tempZip -DestinationPath $Destination -Force

# Find extracted folder (blender-<version>)
$extracted = Get-ChildItem -Path $Destination -Directory | Where-Object { $_.Name -like "blender-*" } | Select-Object -First 1
if ($null -eq $extracted) {
    Write-Host "No se encontró la carpeta extraída en $Destination. Contenido:"; Get-ChildItem -Path $Destination
    exit 1
}

$blenderExe = Join-Path $extracted.FullName "blender.exe"
Write-Host "Blender instalado en: $($extracted.FullName)"
Write-Host "Ejecutable: $blenderExe"
Write-Host "Para ejecutar un script de ejemplo, use el script run_example.ps1 o ejecute:`n  "$blenderExe" --background --python scripts\generate_simple_scene.py"

# Cleanup
Remove-Item -Path $tempZip -Force -ErrorAction SilentlyContinue
Write-Host "Listo. No se modificó PATH del sistema. Puedes mover o eliminar la carpeta si lo deseas."
<#
download_utils.ps1
Descarga utils.py desde el repo remoto y lo guarda en examples/ (si existe).
Uso: .\download_utils.ps1 -TargetDir "C:\Users\Leandro\Desktop\pruebablender\examples" -Url "https://raw.githubusercontent.com/yuki-koyama/blender-cli-rendering/master/utils.py"
#>
param(
    [string]$TargetDir = "$env:USERPROFILE\Desktop\pruebablender\examples",
    [string]$Url = "https://raw.githubusercontent.com/yuki-koyama/blender-cli-rendering/master/utils.py"
)
if (-not (Test-Path $TargetDir)) { Write-Host "TargetDir no existe: $TargetDir"; exit 1 }
$dest = Join-Path $TargetDir "utils.py"
try {
    Invoke-WebRequest -Uri $Url -OutFile $dest -UseBasicParsing -ErrorAction Stop
    Write-Host "utils.py descargado a: $dest"
} catch {
    Write-Error "Fallo al descargar utils.py: $_"
}

param(
    [Parameter(Mandatory=$true)][string]$TargetDir
)
if (-not (Test-Path $TargetDir)) { Write-Error "No existe: $TargetDir"; exit 1 }
$zip = "$TargetDir.zip"
if (Test-Path $zip) { Remove-Item $zip -Force }
Compress-Archive -Path (Join-Path $TargetDir "*") -DestinationPath $zip -Force
Write-Output $zip

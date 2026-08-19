param(
    [Parameter(Mandatory=$true)][string]$ImagePath,
    [string]$CompareWith
)

Add-Type -AssemblyName System.Drawing
function Get-Image-Stats {
    param([string]$path)
    if (-not (Test-Path $path)) { return $null }
    $img = [System.Drawing.Image]::FromFile($path)
    $width = $img.Width
    $height = $img.Height
    # compute mean RGB roughly by sampling pixels
    $bmp = New-Object System.Drawing.Bitmap $img
    $sumR = 0; $sumG = 0; $sumB = 0; $count = 0
    for ($x = 0; $x -lt $bmp.Width; $x += [math]::Max(1,[int]($bmp.Width/50))) {
        for ($y = 0; $y -lt $bmp.Height; $y += [math]::Max(1,[int]($bmp.Height/50))) {
            $c = $bmp.GetPixel($x,$y)
            $sumR += $c.R; $sumG += $c.G; $sumB += $c.B; $count++
        }
    }
    $bmp.Dispose()
    $img.Dispose()
    return @{ width=$width; height=$height; mean_r=($sumR/$count); mean_g=($sumG/$count); mean_b=($sumB/$count); samples=$count }
}

$stats = Get-Image-Stats -path $ImagePath
$result = @{ image = $ImagePath; stats = $stats }
if ($CompareWith -and (Test-Path $CompareWith)) {
    # compute simple MSE between downsampled pixels
    $imgA = New-Object System.Drawing.Bitmap([System.Drawing.Image]::FromFile($ImagePath))
    $imgB = New-Object System.Drawing.Bitmap([System.Drawing.Image]::FromFile($CompareWith))
    $w = [math]::Min($imgA.Width,$imgB.Width); $h = [math]::Min($imgA.Height,$imgB.Height)
    $stepX = [math]::Max(1,[int]($w/50)); $stepY = [math]::Max(1,[int]($h/50))
    $mse = 0.0; $n=0
    for ($x=0; $x -lt $w; $x+=$stepX) {
        for ($y=0; $y -lt $h; $y+=$stepY) {
            $ca = $imgA.GetPixel($x,$y); $cb = $imgB.GetPixel($x,$y)
            $dr = $ca.R - $cb.R; $dg = $ca.G - $cb.G; $db = $ca.B - $cb.B
            $mse += ($dr*$dr + $dg*$dg + $db*$db)/3.0
            $n++
        }
    }
    $imgA.Dispose(); $imgB.Dispose()
    $result.mse = ($mse / $n)
}

$result | ConvertTo-Json -Depth 4 | Out-File -FilePath (Join-Path (Split-Path $ImagePath) "image_metrics.json") -Encoding utf8
Write-Output (ConvertTo-Json $result -Depth 4)

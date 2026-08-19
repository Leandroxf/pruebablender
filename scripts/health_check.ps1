<# health_check.ps1
Comprueba el estado mínimo del sistema para ejecutar el agente: blender, Node, espacio en disco, utils.py.
Salida: imprime report y código 0 si OK, 1 si faltan requisitos.
#>

function Find-Blender {
    $cand = @(
        "$env:USERPROFILE\Desktop\pruebablender\blender",
        'C:\Program Files\Blender Foundation',
        'C:\Program Files (x86)\Blender Foundation'
    )
    foreach ($p in $cand) {
        if (Test-Path $p -PathType Leaf) { return (Resolve-Path $p).Path }
        if (Test-Path $p -PathType Container) { $f = Get-ChildItem -Path $p -Recurse -Filter blender.exe -ErrorAction SilentlyContinue | Select-Object -First 1; if ($f) { return $f.FullName } }
    }
    $cmd = (Get-Command blender -ErrorAction SilentlyContinue).Source
    return $cmd
}

$report = @()
$exitCode = 0

# Blender
$b = Find-Blender
if ($b) { $report += "Blender: FOUND -> $b" } else { $report += "Blender: MISSING"; $exitCode = 1 }

# Node (portable)
$repo = Split-Path -Parent $MyInvocation.MyCommand.Definition
$nodeCandidate = Get-ChildItem -Path (Join-Path $repo '..\third_party') -Directory -ErrorAction SilentlyContinue | Where-Object { $_.Name -like 'node-v*' } | Select-Object -First 1
$nodeExe = $null
if ($nodeCandidate) { $possible = Join-Path $nodeCandidate.FullName 'node.exe'; if (Test-Path $possible) { $nodeExe = $possible } }
if (-not $nodeExe) { $nodeExe = (Get-Command node -ErrorAction SilentlyContinue).Source }
if ($nodeExe) { $report += "Node: FOUND -> $nodeExe" } else { $report += "Node: MISSING (recommended for some tools)" }

# utils.py
$utils = Join-Path $repo '..\examples\utils.py'
if (Test-Path $utils) { $report += "utils.py: FOUND" } else { $report += "utils.py: MISSING (run download_utils.ps1)" }

# Disk space (free on C:)
$drive = Get-PSDrive -Name C -ErrorAction SilentlyContinue
if ($drive) { $freeGB = [math]::Round($drive.Free / 1GB,2); $report += "C: free space: ${freeGB}GB"; if ($freeGB -lt 2) { $report += "WARNING: less than 2GB free" } }

# Execution policy
$policy = Get-ExecutionPolicy -Scope Process
$report += "ExecutionPolicy (Process): $policy"

$report | ForEach-Object { Write-Host $_ }
exit $exitCode

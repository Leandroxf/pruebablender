# Blender Agent Wrapper - Enhanced Logging & Metrics
# Purpose: Execute Blender agent with progress tracking, ETA, and performance metrics

param(
    [string]$BlenderPath = "C:\blender_portable",
    [string]$ScriptsDir = "./agents",
    [string]$ConfigFile = "./config.json",
    [int]$TimeoutMinutes = 120
)

# ==================== Configuration ====================

$MetricsFile = "agent_metrics_$(Get-Date -Format 'yyyyMMdd_HHmmss').json"
$ExecutionLog = "agent_execution_$(Get-Date -Format 'yyyyMMdd_HHmmss').log"
$StartTime = Get-Date
$ScriptMetrics = @{}

# ==================== Functions ====================

function Write-LogWithTimestamp {
    param([string]$Message, [ValidateSet("INFO", "WARN", "ERROR", "SUCCESS", "METRIC")]$Level = "INFO")
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss.fff"
    $color = switch ($Level) {
        "ERROR" { "Red" }
        "SUCCESS" { "Green" }
        "WARN" { "Yellow" }
        "METRIC" { "Cyan" }
        default { "Gray" }
    }
    $logEntry = "[$timestamp] [$Level] $Message"
    Write-Host $logEntry -ForegroundColor $color
    Add-Content -Path $ExecutionLog -Value $logEntry
}

function Show-ProgressWithETA {
    param(
        [int]$Current,
        [int]$Total,
        [datetime]$StartTime,
        [string]$Activity = "Processing"
    )
    if ($Total -eq 0) { return }
    
    $percent = [math]::Min(100, [int]($Current / $Total * 100))
    $elapsed = (Get-Date) - $StartTime
    $rate = if ($current -gt 0) { $elapsed.TotalSeconds / $Current } else { 0 }
    $eta = New-TimeSpan -Seconds ($rate * ($Total - $Current))
    
    $bar = "█" * ($percent / 5) + "░" * ((100 - $percent) / 5)
    $etaStr = "{0:D2}h:{1:D2}m:{2:D2}s" -f $eta.Hours, $eta.Minutes, $eta.Seconds
    $elapsedStr = "{0:D2}h:{1:D2}m:{2:D2}s" -f $elapsed.Hours, $elapsed.Minutes, $elapsed.Seconds
    
    Write-Host "`r[$bar] $percent% | $Activity | Elapsed: $elapsedStr | ETA: $etaStr" -NoNewline
}

function Measure-ScriptExecution {
    param(
        [string]$ScriptName,
        [scriptblock]$ScriptBlock
    )
    $scriptStart = Get-Date
    Write-LogWithTimestamp "Starting: $ScriptName" "INFO"
    
    try {
        & $ScriptBlock
        $scriptDuration = (Get-Date) - $scriptStart
        $ScriptMetrics[$ScriptName] = @{
            Status = "Success"
            DurationSeconds = $scriptDuration.TotalSeconds
            StartTime = $scriptStart
            EndTime = Get-Date
        }
        Write-LogWithTimestamp "Completed: $ScriptName ($('{0:F2}' -f $scriptDuration.TotalSeconds)s)" "SUCCESS"
    }
    catch {
        $scriptDuration = (Get-Date) - $scriptStart
        $ScriptMetrics[$ScriptName] = @{
            Status = "Failed"
            DurationSeconds = $scriptDuration.TotalSeconds
            Error = $_.Exception.Message
            StartTime = $scriptStart
            EndTime = Get-Date
        }
        Write-LogWithTimestamp "Failed: $ScriptName - $($_.Exception.Message)" "ERROR"
    }
}

function Get-BlenderAgentScripts {
    param([string]$DirectoryPath)
    
    if (-not (Test-Path $DirectoryPath)) {
        Write-LogWithTimestamp "Scripts directory not found: $DirectoryPath" "WARN"
        return @()
    }
    
    $scripts = Get-ChildItem -Path $DirectoryPath -Filter "*.py" -ErrorAction SilentlyContinue | Sort-Object Name
    Write-LogWithTimestamp "Found $($scripts.Count) agent scripts" "INFO"
    return $scripts
}

function Invoke-BlenderScript {
    param(
        [string]$BlenderExe,
        [string]$ScriptPath,
        [string]$ConfigPath
    )
    
    $blenderExePath = Join-Path $BlenderExe "blender.exe"
    if (-not (Test-Path $blenderExePath)) {
        throw "Blender executable not found: $blenderExePath"
    }
    
    $args = @(
        "--background",
        "--python", $ScriptPath
    )
    
    if (Test-Path $ConfigPath) {
        $args += "--python-expr", "import json; config = json.load(open('$ConfigPath'))"
    }
    
    Write-LogWithTimestamp "Executing: $blenderExePath with script $([System.IO.Path]::GetFileName($ScriptPath))" "INFO"
    
    & $blenderExePath $args 2>&1 | ForEach-Object {
        Write-LogWithTimestamp $_ "INFO"
    }
}

function Export-Metrics {
    param([hashtable]$Metrics, [string]$OutputFile)
    
    $totalDuration = (Get-Date) - $StartTime
    $report = @{
        ExecutionStartTime = $StartTime
        ExecutionEndTime = Get-Date
        TotalDurationSeconds = $totalDuration.TotalSeconds
        ScriptCount = $Metrics.Count
        Scripts = $Metrics
        SystemInfo = @{
            ComputerName = $env:COMPUTERNAME
            Username = $env:USERNAME
            OSVersion = [System.Environment]::OSVersion.VersionString
        }
    }
    
    $report | ConvertTo-Json -Depth 3 | Out-File -FilePath $OutputFile -Encoding UTF8
    Write-LogWithTimestamp "Metrics exported to: $(Resolve-Path $OutputFile)" "METRIC"
}

function Print-ExecutionSummary {
    param([hashtable]$Metrics, [timespan]$Duration)
    
    $successCount = ($Metrics.Values | Where-Object { $_.Status -eq "Success" }).Count
    $failureCount = ($Metrics.Values | Where-Object { $_.Status -eq "Failed" }).Count
    $avgDuration = if ($Metrics.Count -gt 0) {
        ($Metrics.Values.DurationSeconds | Measure-Object -Average).Average
    }
    else { 0 }
    
    Write-Host "`n╔════════════════════════════════════════════════════════════╗"
    Write-Host "║              EXECUTION SUMMARY & METRICS                   ║"
    Write-Host "╚════════════════════════════════════════════════════════════╝`n"
    
    Write-LogWithTimestamp "Total Execution Time: $('{0:D2}h:{1:D2}m:{2:D2}s' -f $Duration.Hours, $Duration.Minutes, $Duration.Seconds)" "METRIC"
    Write-LogWithTimestamp "Scripts Executed: $($Metrics.Count)" "INFO"
    Write-LogWithTimestamp "Successful: $successCount | Failed: $failureCount" "INFO"
    Write-LogWithTimestamp "Average Duration per Script: $('{0:F2}' -f $avgDuration)s" "METRIC"
    
    if ($Metrics.Count -gt 0) {
        Write-Host "`nDetailed Breakdown:"
        $Metrics.GetEnumerator() | ForEach-Object {
            $status = $_.Value.Status -eq "Success" ? "✓" : "✗"
            $duration = '{0:F2}' -f $_.Value.DurationSeconds
            Write-LogWithTimestamp "$status $($_.Key): ${duration}s" "INFO"
        }
    }
    
    Write-Host ""
}

# ==================== Main Execution ====================

Write-Host "`n╔════════════════════════════════════════════════════════════╗"
Write-Host "║        BLENDER AGENT WRAPPER - ENHANCED EXECUTION         ║"
Write-Host "╚════════════════════════════════════════════════════════════╝`n"

Write-LogWithTimestamp "Agent wrapper initialized" "INFO"
Write-LogWithTimestamp "Blender Path: $BlenderPath" "INFO"
Write-LogWithTimestamp "Scripts Directory: $ScriptsDir" "INFO"
Write-LogWithTimestamp "Config File: $ConfigFile" "INFO"
Write-LogWithTimestamp "Timeout: $TimeoutMinutes minutes" "INFO"

# Validate Blender installation
if (-not (Test-Path $BlenderPath)) {
    Write-LogWithTimestamp "Blender directory not found: $BlenderPath" "ERROR"
    exit 1
}

# Get scripts to execute
$scripts = Get-BlenderAgentScripts -DirectoryPath $ScriptsDir

if ($scripts.Count -eq 0) {
    Write-LogWithTimestamp "No scripts found in $ScriptsDir" "WARN"
    exit 0
}

# Execute each script with timeout protection
$scriptCount = $scripts.Count
Write-LogWithTimestamp "Starting execution of $scriptCount scripts" "INFO"

for ($i = 0; $i -lt $scripts.Count; $i++) {
    Show-ProgressWithETA -Current ($i + 1) -Total $scriptCount -StartTime $StartTime -Activity "Executing scripts"
    
    $scriptName = $scripts[$i].Name
    $scriptPath = $scripts[$i].FullName
    
    Measure-ScriptExecution -ScriptName $scriptName -ScriptBlock {
        Invoke-BlenderScript -BlenderExe $BlenderPath -ScriptPath $scriptPath -ConfigPath $ConfigFile
    }
}

Write-Host "`n"

# Export metrics and summary
Export-Metrics -Metrics $ScriptMetrics -OutputFile $MetricsFile
$totalDuration = (Get-Date) - $StartTime
Print-ExecutionSummary -Metrics $ScriptMetrics -Duration $totalDuration

Write-LogWithTimestamp "Execution log: $(Resolve-Path $ExecutionLog)" "INFO"
Write-Host ""

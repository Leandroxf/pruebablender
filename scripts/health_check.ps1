# Blender Agent Health Check - With Diagnostics & Hints
# Purpose: Comprehensive health check with actionable hints for resolution

param(
    [string]$BlenderPath = "C:\blender_portable",
    [string]$ScriptsDir = "./agents",
    [switch]$Verbose
)

# ==================== Configuration ====================

$HealthCheckLog = "health_check_$(Get-Date -Format 'yyyyMMdd_HHmmss').log"
$HealthStatus = @{
    Passed = 0
    Failed = 0
    Warnings = 0
    Hints = @()
}

# ==================== Functions ====================

function Write-CheckResult {
    param(
        [string]$CheckName,
        [bool]$Passed,
        [string]$Message = "",
        [string[]]$Hints = @()
    )
    
    $status = $Passed ? "✓ PASS" : "✗ FAIL"
    $color = $Passed ? "Green" : "Red"
    $logEntry = "[$status] $CheckName"
    
    if ($Message) {
        $logEntry += " - $Message"
    }
    
    Write-Host $logEntry -ForegroundColor $color
    Add-Content -Path $HealthCheckLog -Value $logEntry
    
    if ($Passed) {
        $HealthStatus.Passed++
    }
    else {
        $HealthStatus.Failed++
        if ($Hints.Count -gt 0) {
            $HealthStatus.Hints += @{
                Check = $CheckName
                Messages = $Hints
            }
        }
    }
}

function Write-Hint {
    param([string]$Message)
    Write-Host "  💡 Hint: $Message" -ForegroundColor Yellow
    Add-Content -Path $HealthCheckLog -Value "  Hint: $Message"
}

function Write-Warning {
    param([string]$Message)
    Write-Host "  ⚠ Warning: $Message" -ForegroundColor Yellow
    Add-Content -Path $HealthCheckLog -Value "  Warning: $Message"
    $HealthStatus.Warnings++
}

function Test-AdminPrivileges {
    $currentUser = [Security.Principal.WindowsIdentity]::GetCurrent()
    $principal = New-Object Security.Principal.WindowsPrincipal($currentUser)
    return $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
}

function Test-BlenderInstallation {
    $blenderExe = Join-Path $BlenderPath "blender.exe"
    $pythonDir = Join-Path $BlenderPath "python"
    $librariesDir = Join-Path $BlenderPath "lib"
    
    $checks = @{
        "blender.exe exists" = Test-Path $blenderExe
        "python directory exists" = Test-Path $pythonDir
        "libraries directory exists" = Test-Path $librariesDir
    }
    
    $allExist = $checks.Values | ForEach-Object { $_ } | Measure-Object -Sum | Select-Object -ExpandProperty Sum
    $passed = $allExist -eq $checks.Count
    
    $message = "$($checks.Count - $allExist) missing components out of $($checks.Count)"
    Write-CheckResult -CheckName "Blender Installation" -Passed $passed -Message $message
    
    if (-not $passed) {
        Write-Hint "Run setup_blender_portable.ps1 to install Blender"
        Write-Hint "Expected location: $BlenderPath"
        $checks.GetEnumerator() | Where-Object { -not $_.Value } | ForEach-Object {
            Write-Hint "Missing: $($_.Key)"
        }
    }
    
    return $passed
}

function Test-PythonEnvironment {
    $pythonExe = Join-Path $BlenderPath "python\bin\python.exe"
    
    if (-not (Test-Path $pythonExe)) {
        Write-CheckResult -CheckName "Python Environment" -Passed $false -Message "Python executable not found"
        Write-Hint "Ensure Blender is properly installed with Python support"
        Write-Hint "Reinstall Blender from blender.org or run setup_blender_portable.ps1"
        return $false
    }
    
    try {
        $pythonVersion = & $pythonExe --version 2>&1
        Write-CheckResult -CheckName "Python Environment" -Passed $true -Message $pythonVersion
        return $true
    }
    catch {
        Write-CheckResult -CheckName "Python Environment" -Passed $false -Message "Could not verify Python version"
        Write-Hint "Check Python installation: $pythonExe"
        return $false
    }
}

function Test-ScriptsDirectory {
    $passed = Test-Path $ScriptsDir
    $fileCount = if ($passed) { @(Get-ChildItem -Path $ScriptsDir -Filter "*.py" -ErrorAction SilentlyContinue).Count } else { 0 }
    
    $message = if ($passed) { "Found $fileCount Python scripts" } else { "Directory does not exist" }
    Write-CheckResult -CheckName "Scripts Directory" -Passed $passed -Message $message
    
    if (-not $passed) {
        Write-Hint "Create scripts directory at: $ScriptsDir"
        Write-Hint "Add your Blender agent scripts (.py files) to this directory"
        Write-Hint "Example: $ScriptsDir\my_agent.py"
    }
    
    if ($passed -and $fileCount -eq 0) {
        Write-Hint "No Python scripts found in $ScriptsDir"
        Write-Hint "Add your Blender agent Python scripts to enable agent execution"
    }
    
    return $passed
}

function Test-DiskSpace {
    try {
        $drive = (Get-Item $BlenderPath -ErrorAction SilentlyContinue).PSDrive.Name
        if (-not $drive) {
            Write-Hint "Could not determine drive for $BlenderPath"
            return $true
        }
        
        $volume = Get-Volume -DriveLetter $drive -ErrorAction SilentlyContinue
        if ($null -eq $volume) {
            Write-Warning "Could not retrieve volume information"
            return $true
        }
        
        $availableGB = $volume.SizeRemaining / 1GB
        $requiredGB = 5
        $passed = $availableGB -ge $requiredGB
        
        $message = "Available: $([math]::Round($availableGB, 2))GB (Required: ${requiredGB}GB)"
        Write-CheckResult -CheckName "Disk Space" -Passed $passed -Message $message
        
        if (-not $passed) {
            Write-Hint "Free up disk space on drive $drive"
            Write-Hint "Current available: $([math]::Round($availableGB, 2))GB"
            Write-Hint "Minimum required: ${requiredGB}GB"
        }
        
        return $passed
    }
    catch {
        Write-Warning "Error checking disk space: $_"
        return $true
    }
}

function Test-NetworkAccess {
    $testUrls = @(
        "https://www.github.com",
        "https://api.github.com"
    )
    
    $reachable = 0
    foreach ($url in $testUrls) {
        try {
            $response = Invoke-WebRequest -Uri $url -UseBasicParsing -TimeoutSec 5 -ErrorAction SilentlyContinue
            if ($response.StatusCode -eq 200) { $reachable++ }
        }
        catch { }
    }
    
    $passed = $reachable -ge 1
    $message = "Reachable: $reachable/$($testUrls.Count) endpoints"
    Write-CheckResult -CheckName "Network Access" -Passed $passed -Message $message
    
    if (-not $passed) {
        Write-Hint "Check your internet connection"
        Write-Hint "If behind a proxy, configure PowerShell to use it"
        Write-Hint "Network access is needed for Blender updates and remote operations"
    }
    
    return $passed
}

function Test-PowerShellVersion {
    $version = $PSVersionTable.PSVersion
    $passed = $version.Major -ge 5
    
    $message = "PowerShell $($version.Major).$($version.Minor)"
    Write-CheckResult -CheckName "PowerShell Version" -Passed $passed -Message $message
    
    if (-not $passed) {
        Write-Hint "Consider upgrading to PowerShell 5.0 or later"
        Write-Hint "Download from: https://docs.microsoft.com/en-us/powershell/scripting/install/installing-powershell-on-windows"
    }
    
    return $passed
}

function Test-RequiredCommandsAvailable {
    $commands = @("git", "python")
    $missing = @()
    
    foreach ($cmd in $commands) {
        $found = $null -ne (Get-Command $cmd -ErrorAction SilentlyContinue)
        if (-not $found) {
            $missing += $cmd
        }
    }
    
    $passed = $missing.Count -eq 0
    $message = if ($passed) { "All required commands available" } else { "Missing: $($missing -join ', ')" }
    
    Write-CheckResult -CheckName "Required Commands" -Passed $passed -Message $message
    
    if (-not $passed) {
        if ($missing -contains "git") {
            Write-Hint "Install Git: https://git-scm.com/download/win"
        }
        if ($missing -contains "python") {
            Write-Hint "Use Blender's bundled Python or install Python: https://www.python.org"
        }
    }
    
    return $passed
}

function Test-AdminRequirement {
    $isAdmin = Test-AdminPrivileges
    
    if (-not $isAdmin) {
        Write-CheckResult -CheckName "Administrator Privileges" -Passed $false -Message "Running as non-admin user"
        Write-Hint "Some operations require Administrator privileges"
        Write-Hint "Run PowerShell as Administrator: Right-click 'Windows PowerShell' → 'Run as Administrator'"
        Write-Hint "Or use: Start-Process powershell -Verb RunAs"
    }
    else {
        Write-CheckResult -CheckName "Administrator Privileges" -Passed $true -Message "Running with admin privileges"
    }
    
    return $isAdmin
}

function Show-DetailedReport {
    param([hashtable]$Status)
    
    if ($Status.Hints.Count -gt 0) {
        Write-Host "`n╔════════════════════════════════════════════════════════════╗"
        Write-Host "║                   RESOLUTION HINTS                         ║"
        Write-Host "╚════════════════════════════════════════════════════════════╝`n"
        
        $Status.Hints | ForEach-Object {
            Write-Host "For: $($_.Check)" -ForegroundColor Cyan
            $_.Messages | ForEach-Object {
                Write-Host "  💡 $_"
            }
            Write-Host ""
        }
    }
}

function Show-HealthSummary {
    param([hashtable]$Status)
    
    Write-Host "`n╔════════════════════════════════════════════════════════════╗"
    Write-Host "║                  HEALTH CHECK SUMMARY                      ║"
    Write-Host "╚════════════════════════════════════════════════════════════╝`n"
    
    $totalChecks = $Status.Passed + $Status.Failed
    $passPercentage = if ($totalChecks -gt 0) { [int]($Status.Passed / $totalChecks * 100) } else { 0 }
    
    $summaryColor = if ($Status.Failed -eq 0) { "Green" } else { "Yellow" }
    Write-Host "Status: " -NoNewline
    Write-Host "Passed: $($Status.Passed) | Failed: $($Status.Failed) | Warnings: $($Status.Warnings)" -ForegroundColor $summaryColor
    Write-Host "Overall Health: $passPercentage%`n"
    
    if ($Status.Failed -eq 0) {
        Write-Host "✓ All checks passed. Your Blender agent environment is healthy!`n" -ForegroundColor Green
    }
    else {
        Write-Host "✗ Some checks failed. See hints above to resolve issues.`n" -ForegroundColor Red
    }
}

# ==================== Main Execution ====================

Write-Host "`n╔════════════════════════════════════════════════════════════╗"
Write-Host "║      BLENDER AGENT HEALTH CHECK - COMPREHENSIVE SCAN       ║"
Write-Host "╚════════════════════════════════════════════════════════════╝`n"

Add-Content -Path $HealthCheckLog -Value "Health Check Started: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
Add-Content -Path $HealthCheckLog -Value "Configuration: BlenderPath=$BlenderPath, ScriptsDir=$ScriptsDir`n"

# Run all checks
Test-AdminRequirement | Out-Null
Test-PowerShellVersion | Out-Null
Test-BlenderInstallation | Out-Null
Test-PythonEnvironment | Out-Null
Test-ScriptsDirectory | Out-Null
Test-DiskSpace | Out-Null
Test-NetworkAccess | Out-Null
Test-RequiredCommandsAvailable | Out-Null

# Show results
Show-DetailedReport -Status $HealthStatus
Show-HealthSummary -Status $HealthStatus

Write-Host "Health check log: $(Resolve-Path $HealthCheckLog)`n"
Add-Content -Path $HealthCheckLog -Value "`nHealth Check Completed: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"

# Exit with appropriate code
exit $HealthStatus.Failed

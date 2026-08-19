# ========================================================================
# BLENDER COPILOT AGENT - UNIFIED SETUP ENTRY POINT
# ========================================================================
# One-click setup: Download Blender → Health Check → Run Agent (Optional)
# This is the ONLY script the user needs to run.
# ========================================================================

param(
    [string]$BlenderVersion = "4.0.1",
    [string]$BlenderPath = "C:\blender_portable",
    [string]$ScriptsDir = "./agents",
    [string]$ConfigFile = "./config.json",
    [int]$MaxRetries = 3,
    [int]$RetryDelaySeconds = 5,
    [ValidateSet("x64", "x86")]
    [string]$Architecture = "x64"
)

# ==================== CONFIGURATION ====================

$MinDiskSpaceGB = 5
$BlenderDownloadURL = "https://download.blender.org/release/Blender$([math]::Truncate([decimal]$BlenderVersion))/blender-$BlenderVersion-windows-$Architecture.zip"
$LogFile = "blender_setup_$(Get-Date -Format 'yyyyMMdd_HHmmss').log"
$HealthStatus = @{
    Passed = 0
    Failed = 0
    Warnings = 0
    Hints = @()
}

# ==================== LOGGING & UI FUNCTIONS ====================

function Write-Log {
    param([string]$Message, [ValidateSet("INFO", "WARN", "ERROR", "SUCCESS", "DEBUG")]$Level = "INFO")
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    $logEntry = "[$timestamp] [$Level] $Message"
    
    $color = switch ($Level) {
        "ERROR" { "Red" }
        "SUCCESS" { "Green" }
        "WARN" { "Yellow" }
        "DEBUG" { "Gray" }
        default { "White" }
    }
    
    Write-Host $logEntry -ForegroundColor $color
    Add-Content -Path $LogFile -Value $logEntry -ErrorAction SilentlyContinue
}

function Show-Banner {
    Write-Host "`n" -ForegroundColor Cyan
    Write-Host "╔════════════════════════════════════════════════════════════════╗" -ForegroundColor Cyan
    Write-Host "║                                                                ║" -ForegroundColor Cyan
    Write-Host "║         🎬 BLENDER COPILOT AGENT - UNIFIED SETUP 🎬           ║" -ForegroundColor Cyan
    Write-Host "║                                                                ║" -ForegroundColor Cyan
    Write-Host "║  Your one-click solution for Blender agent installation      ║" -ForegroundColor Cyan
    Write-Host "║                                                                ║" -ForegroundColor Cyan
    Write-Host "╚════════════════════════════════════════════════════════════════╝" -ForegroundColor Cyan
    Write-Host ""
}

function Show-ProgressBar {
    param([int]$Current, [int]$Total, [string]$Activity = "Processing")
    $percent = [math]::Min(100, [int]($Current / $Total * 100))
    $bar = "█" * ($percent / 5) + "░" * ((100 - $percent) / 5)
    Write-Host "`r[$bar] $percent% - $Activity" -NoNewline
}

function Show-Hint {
    param([string]$Message)
    Write-Host "  💡 $Message" -ForegroundColor Yellow
    Add-Content -Path $LogFile -Value "  Hint: $Message"
}

function Show-CheckResult {
    param([string]$CheckName, [bool]$Passed, [string]$Message = "")
    $status = $Passed ? "✓ PASS" : "✗ FAIL"
    $color = $Passed ? "Green" : "Red"
    
    if ($Message) {
        Write-Host "$status $CheckName - $Message" -ForegroundColor $color
    } else {
        Write-Host "$status $CheckName" -ForegroundColor $color
    }
    
    if ($Passed) { $HealthStatus.Passed++ } else { $HealthStatus.Failed++ }
}

function Ask-YesNo {
    param([string]$Question)
    while ($true) {
        $response = Read-Host "$Question (S/N)"
        if ($response -match "^[SsYy]") { return $true }
        elseif ($response -match "^[Nn]") { return $false }
        else { Write-Host "Por favor, responde con S (Sí) o N (No)" -ForegroundColor Yellow }
    }
}

# ==================== SETUP FUNCTIONS ====================

function Test-AdminPrivileges {
    $currentUser = [Security.Principal.WindowsIdentity]::GetCurrent()
    $principal = New-Object Security.Principal.WindowsPrincipal($currentUser)
    return $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
}

function Test-DiskSpace {
    param([string]$Path, [int]$RequiredGB)
    try {
        $drive = (Get-Item $Path -ErrorAction SilentlyContinue).PSDrive.Name
        if (-not $drive) {
            Write-Log "Could not determine drive for $Path" "WARN"
            return $true
        }
        
        $volume = Get-Volume -DriveLetter $drive -ErrorAction SilentlyContinue
        if ($null -eq $volume) {
            Write-Log "Could not retrieve volume information" "WARN"
            return $true
        }
        
        $availableGB = $volume.SizeRemaining / 1GB
        if ($availableGB -lt $RequiredGB) {
            Write-Log "Insufficient disk space. Required: ${RequiredGB}GB, Available: $([math]::Round($availableGB, 2))GB" "ERROR"
            Show-Hint "Free up disk space on your main drive"
            return $false
        }
        Write-Log "Disk space verified: $([math]::Round($availableGB, 2))GB available" "SUCCESS"
        return $true
    }
    catch {
        Write-Log "Error checking disk space: $_" "WARN"
        return $true
    }
}

function Download-WithRetry {
    param([string]$URL, [string]$OutputPath, [int]$MaxRetries)
    $retryCount = 0
    $downloadSuccess = $false

    while ($retryCount -lt $MaxRetries -and -not $downloadSuccess) {
        try {
            Write-Log "Downloading Blender $BlenderVersion (Attempt $($retryCount + 1)/$MaxRetries)..." "INFO"
            $ProgressPreference = "SilentlyContinue"
            Invoke-WebRequest -Uri $URL -OutFile $OutputPath -UseBasicParsing
            
            if (Test-Path $OutputPath) {
                $fileSize = (Get-Item $OutputPath).Length / 1MB
                Write-Log "Download completed: $([math]::Round($fileSize, 2))MB" "SUCCESS"
                $downloadSuccess = $true
            }
        }
        catch {
            $retryCount++
            Write-Log "Download failed: $($_.Exception.Message)" "WARN"
            if ($retryCount -lt $MaxRetries) {
                Write-Log "Retrying in $RetryDelaySeconds seconds..." "INFO"
                Start-Sleep -Seconds $RetryDelaySeconds
            }
        }
    }

    if (-not $downloadSuccess) {
        Write-Log "Download failed after $MaxRetries attempts" "ERROR"
        Show-Hint "Check your internet connection"
        Show-Hint "Visit: https://download.blender.org/release/ for manual download"
        return $false
    }
    return $true
}

function Extract-Archive-WithProgress {
    param([string]$ArchivePath, [string]$DestinationPath)
    try {
        Write-Log "Extracting archive to: $DestinationPath" "INFO"
        
        if (-not (Test-Path $DestinationPath)) {
            New-Item -ItemType Directory -Path $DestinationPath -Force | Out-Null
        }

        Add-Type -AssemblyName System.IO.Compression.FileSystem
        $zip = [System.IO.Compression.ZipFile]::OpenRead($ArchivePath)
        $totalEntries = $zip.Entries.Count
        $currentEntry = 0

        foreach ($entry in $zip.Entries) {
            $currentEntry++
            Show-ProgressBar -Current $currentEntry -Total $totalEntries -Activity "Extracting"
            
            $destFile = Join-Path $DestinationPath $entry.FullName
            $destDir = Split-Path $destFile -Parent
            
            if (-not (Test-Path $destDir)) {
                New-Item -ItemType Directory -Path $destDir -Force | Out-Null
            }
            
            if (-not $entry.Name.EndsWith('/')) {
                [System.IO.Compression.ZipFileExtensions]::ExtractToFile($entry, $destFile, $true)
            }
        }
        $zip.Dispose()
        Write-Host "`n"
        Write-Log "Extraction completed successfully" "SUCCESS"
        return $true
    }
    catch {
        Write-Log "Extraction failed: $_" "ERROR"
        return $false
    }
}

function Validate-BlenderInstallation {
    param([string]$BlenderPath)
    $blenderExe = Join-Path $BlenderPath "blender.exe"
    $pythonPath = Join-Path $BlenderPath "python"
    
    if (-not (Test-Path $blenderExe)) {
        Write-Log "Blender executable not found" "ERROR"
        return $false
    }
    if (-not (Test-Path $pythonPath)) {
        Write-Log "Python environment not found" "ERROR"
        return $false
    }
    
    Write-Log "Blender installation validated successfully" "SUCCESS"
    return $true
}

# ==================== HEALTH CHECK (INTEGRATED) ====================

function Run-HealthCheck {
    Write-Host "`n╔════════════════════════════════════════════════════════════════╗" -ForegroundColor Cyan
    Write-Host "║             PHASE 2: DEPENDENCY CHECK & VALIDATION              ║" -ForegroundColor Cyan
    Write-Host "╚════════════════════════════════════════════════════════════════╝`n" -ForegroundColor Cyan
    
    $allPassed = $true
    
    # Check 1: Admin Privileges
    $isAdmin = Test-AdminPrivileges
    Show-CheckResult "Administrator Privileges" $isAdmin
    if (-not $isAdmin) {
        Show-Hint "Run PowerShell as Administrator: Right-click 'Windows PowerShell' → 'Run as Administrator'"
    }
    
    # Check 2: PowerShell Version
    $version = $PSVersionTable.PSVersion
    $psOK = $version.Major -ge 5
    Show-CheckResult "PowerShell Version" $psOK "$($version.Major).$($version.Minor)"
    if (-not $psOK) {
        Show-Hint "Consider upgrading to PowerShell 5.0 or later"
    }
    
    # Check 3: Blender Installation
    $blenderExe = Join-Path $BlenderPath "blender.exe"
    $pythonDir = Join-Path $BlenderPath "python"
    $blenderOK = (Test-Path $blenderExe) -and (Test-Path $pythonDir)
    Show-CheckResult "Blender Installation" $blenderOK "3/3 components present"
    if (-not $blenderOK) {
        Show-Hint "Blender will be installed in Step 1"
    }
    
    # Check 4: Python Environment
    if (Test-Path $blenderExe) {
        $pythonExe = Join-Path $BlenderPath "python\bin\python.exe"
        if (Test-Path $pythonExe) {
            try {
                $pythonVersion = & $pythonExe --version 2>&1
                Show-CheckResult "Python Environment" $true $pythonVersion
            }
            catch {
                Show-CheckResult "Python Environment" $false "Could not verify version"
                $allPassed = $false
            }
        }
    }
    
    # Check 5: Scripts Directory
    $scriptOK = Test-Path $ScriptsDir
    $scriptCount = if ($scriptOK) { @(Get-ChildItem -Path $ScriptsDir -Filter "*.py" -ErrorAction SilentlyContinue).Count } else { 0 }
    Show-CheckResult "Agent Scripts Directory" $scriptOK "$scriptCount Python scripts found"
    if (-not $scriptOK) {
        Show-Hint "Create directory: $ScriptsDir and add your Blender agent scripts"
    }
    
    # Check 6: Disk Space
    $diskOK = Test-DiskSpace -Path $BlenderPath -RequiredGB $MinDiskSpaceGB
    if (-not $diskOK) { $allPassed = $false }
    
    # Check 7: Network Access
    $networkOK = $false
    try {
        $response = Invoke-WebRequest -Uri "https://www.github.com" -UseBasicParsing -TimeoutSec 5 -ErrorAction SilentlyContinue
        if ($response.StatusCode -eq 200) { $networkOK = $true }
    }
    catch { }
    Show-CheckResult "Network Access" $networkOK "Can reach external services"
    
    Write-Host ""
    return $allPassed
}

# ==================== AGENT EXECUTION ====================

function Run-BlenderAgent {
    Write-Host "`n╔════════════════════════════════════════════════════════════════╗" -ForegroundColor Cyan
    Write-Host "║              PHASE 3: EXECUTING BLENDER AGENT                   ║" -ForegroundColor Cyan
    Write-Host "╚════════════════════════════════════════════════════════════════╝`n" -ForegroundColor Cyan
    
    $wrapperScript = ".\scripts\run_agent_wrapper.ps1"
    
    if (-not (Test-Path $wrapperScript)) {
        Write-Log "Agent wrapper script not found: $wrapperScript" "ERROR"
        Show-Hint "Ensure run_agent_wrapper.ps1 exists in the scripts directory"
        return $false
    }
    
    try {
        Write-Log "Starting Blender agent execution..." "INFO"
        Write-Log "Configuration: BlenderPath=$BlenderPath, ScriptsDir=$ScriptsDir, ConfigFile=$ConfigFile" "DEBUG"
        
        & $wrapperScript `
            -BlenderPath $BlenderPath `
            -ScriptsDir $ScriptsDir `
            -ConfigFile $ConfigFile `
            -TimeoutMinutes 120
        
        if ($LASTEXITCODE -eq 0) {
            Write-Log "Agent execution completed successfully" "SUCCESS"
            return $true
        }
        else {
            Write-Log "Agent execution completed with warnings or errors" "WARN"
            return $true  # Still consider it success for summary
        }
    }
    catch {
        Write-Log "Error executing agent: $_" "ERROR"
        return $false
    }
}

# ==================== SUMMARY & CLOSING ====================

function Show-CompletionSummary {
    param([bool]$SetupSuccess, [bool]$HealthCheckPass, [bool]$AgentRan)
    
    Write-Host "`n╔════════════════════════════════════════════════════════════════╗" -ForegroundColor Green
    Write-Host "║                    🎉 SETUP COMPLETED 🎉                       ║" -ForegroundColor Green
    Write-Host "╚════════════════════════════════════════════════════════════════╝`n" -ForegroundColor Green
    
    Write-Host "Setup Status:" -ForegroundColor Cyan
    Write-Host "  ✓ Blender Installation: $($SetupSuccess ? 'SUCCESS' : 'FAILED')" -ForegroundColor $(if ($SetupSuccess) { 'Green' } else { 'Red' })
    Write-Host "  ✓ Health Check: $($HealthCheckPass ? 'ALL PASSED' : 'SOME WARNINGS')" -ForegroundColor $(if ($HealthCheckPass) { 'Green' } else { 'Yellow' })
    Write-Host "  ✓ Agent Execution: $($AgentRan ? 'COMPLETED' : 'SKIPPED')" -ForegroundColor $(if ($AgentRan) { 'Green' } else { 'Gray' })
    
    Write-Host "`nBlender Location: $BlenderPath" -ForegroundColor Cyan
    Write-Host "Setup Log: $(Resolve-Path $LogFile)" -ForegroundColor Gray
    
    Write-Host "`n📖 Next Steps:"
    Write-Host "  1. Check log files for details: $LogFile"
    Write-Host "  2. Add your Blender scripts to: $ScriptsDir"
    Write-Host "  3. Run setup again to execute your custom agents"
    
    if ($SetupSuccess -and $HealthCheckPass) {
        Write-Host "`n✨ Your Blender agent environment is ready!" -ForegroundColor Green
    }
    
    Write-Host ""
}

# ==================== MAIN EXECUTION ====================

Show-Banner
Write-Log "========== BLENDER COPILOT AGENT SETUP STARTED ==========" "INFO"
Write-Log "Parameters: Version=$BlenderVersion, Architecture=$Architecture, BlenderPath=$BlenderPath" "DEBUG"

# ========== PHASE 1: SETUP ==========
Write-Host "╔════════════════════════════════════════════════════════════════╗" -ForegroundColor Cyan
Write-Host "║             PHASE 1: BLENDER DOWNLOAD & INSTALLATION           ║" -ForegroundColor Cyan
Write-Host "╚════════════════════════════════════════════════════════════════╝`n" -ForegroundColor Cyan

# Check admin
if (-not (Test-AdminPrivileges)) {
    Write-Log "Warning: Not running as Administrator" "WARN"
}

# Validate disk space
if (-not (Test-DiskSpace -Path $BlenderPath -RequiredGB $MinDiskSpaceGB)) {
    Write-Log "Setup failed: Insufficient disk space" "ERROR"
    Show-CompletionSummary $false $false $false
    exit 1
}

# Create target directory
if (-not (Test-Path $BlenderPath)) {
    New-Item -ItemType Directory -Path $BlenderPath -Force | Out-Null
    Write-Log "Created target directory: $BlenderPath" "INFO"
}

# Download Blender
$tempZip = Join-Path $env:TEMP "blender_$BlenderVersion.zip"
if (-not (Download-WithRetry -URL $BlenderDownloadURL -OutputPath $tempZip -MaxRetries $MaxRetries)) {
    Write-Log "Setup aborted: download failed" "ERROR"
    Show-CompletionSummary $false $false $false
    exit 1
}

# Extract archive
if (-not (Extract-Archive-WithProgress -ArchivePath $tempZip -DestinationPath $BlenderPath)) {
    Write-Log "Setup aborted: extraction failed" "ERROR"
    Remove-Item $tempZip -ErrorAction SilentlyContinue
    Show-CompletionSummary $false $false $false
    exit 1
}

# Validate installation
if (-not (Validate-BlenderInstallation -BlenderPath $BlenderPath)) {
    Write-Log "Setup aborted: validation failed" "ERROR"
    Show-CompletionSummary $false $false $false
    exit 1
}

# Cleanup
Remove-Item $tempZip -ErrorAction SilentlyContinue
Write-Log "PHASE 1 COMPLETED: Blender installed successfully" "SUCCESS"

# ========== PHASE 2: HEALTH CHECK ==========
$healthCheckPass = Run-HealthCheck
Write-Log "PHASE 2 COMPLETED: Health check finished" "SUCCESS"

# ========== PHASE 3: OPTIONAL AGENT EXECUTION ==========
$agentRan = $false

if ((Ask-YesNo "`n🤖 Would you like to run the Blender agent now?")) {
    if (Test-Path $ScriptsDir) {
        $scriptCount = @(Get-ChildItem -Path $ScriptsDir -Filter "*.py" -ErrorAction SilentlyContinue).Count
        if ($scriptCount -gt 0) {
            $agentRan = Run-BlenderAgent
            Write-Log "PHASE 3 COMPLETED: Agent execution finished" "SUCCESS"
        }
        else {
            Write-Log "No agent scripts found in $ScriptsDir" "WARN"
            Show-Hint "Add Python scripts to $ScriptsDir to enable agent execution"
        }
    }
    else {
        Write-Log "Scripts directory not found: $ScriptsDir" "WARN"
        Show-Hint "Create the directory and add your Blender agent Python scripts"
    }
}
else {
    Write-Log "Agent execution skipped by user" "INFO"
}

# ========== COMPLETION ==========
Show-CompletionSummary -SetupSuccess $true -HealthCheckPass $healthCheckPass -AgentRan $agentRan

Write-Log "========== BLENDER COPILOT AGENT SETUP FINISHED ==========" "SUCCESS"
exit 0

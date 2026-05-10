$intervalSeconds = 30
$commitMessagePrefix = "Auto-commit: Workspace changes"

Set-Location -Path $PSScriptRoot

$disableFlag = Join-Path $PSScriptRoot ".autopush.disabled"
if (Test-Path $disableFlag) {
    Write-Host "Auto-push is temporarily disabled (.autopush.disabled found)." -ForegroundColor Yellow
    Write-Host "Remove the flag file to re-enable: $disableFlag" -ForegroundColor Gray
    exit 0
}

$currentBranch = (git rev-parse --abbrev-ref HEAD 2>$null).Trim()
if (-not $currentBranch) {
    Write-Host "Unable to detect current git branch. Exiting." -ForegroundColor Red
    exit 1
}

Write-Host "========================================================="
Write-Host "  MicroFin Auto-Push Service Started"
Write-Host "  Checking for changes every $intervalSeconds seconds..."
Write-Host "  Remote: origin | Branch: $currentBranch"
Write-Host "========================================================="

while ($true) {
    # Check if there are changes
    $gitStatus = git status --porcelain
    if ($gitStatus) {
        $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
        Write-Host "[$timestamp] Changes detected. Committing and pushing..." -ForegroundColor DarkYellow
        
        git add .
        $stagedStatus = git diff --cached --name-only
        if (-not $stagedStatus) {
            Write-Host "[$timestamp] No staged changes after add. Skipping commit." -ForegroundColor Gray
            Start-Sleep -Seconds $intervalSeconds
            continue
        }

        $commitMessage = "$commitMessagePrefix ($timestamp)"
        git commit -m "$commitMessage" | Out-Null

        if ($LASTEXITCODE -ne 0) {
            Write-Host "[$timestamp] Commit failed. Will retry next cycle." -ForegroundColor Red
            Start-Sleep -Seconds $intervalSeconds
            continue
        }

        git push -u origin $currentBranch | Out-Null
        if ($LASTEXITCODE -eq 0) {
            Write-Host "[$timestamp] Push complete." -ForegroundColor Green
        } else {
            Write-Host "[$timestamp] Push failed. Check git auth/remote status." -ForegroundColor Red
        }
    }
    
    Start-Sleep -Seconds $intervalSeconds
}

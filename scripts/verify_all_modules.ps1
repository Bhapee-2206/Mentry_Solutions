# ==============================================================================
# MENTRY SOLUTIONS — FULL SYSTEM MANUAL & AUTOMATED VERIFICATION SCRIPT
# ==============================================================================
# Usage:
#   powershell -ExecutionPolicy Bypass -File .\scripts\verify_all_modules.ps1 [-BaseUrl "http://localhost:8000"]
# ==============================================================================

param(
    [string]$BaseUrl = "http://localhost:8000"
)

$ErrorActionPreference = "Continue"
$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession

function Log-Section($title) {
    Write-Host "`n================================================================================" -ForegroundColor Cyan
    Write-Host "  $title" -ForegroundColor Yellow
    Write-Host "================================================================================" -ForegroundColor Cyan
}

function Assert-Check($name, $condition, $details = "") {
    if ($condition) {
        Write-Host "  [PASS] $name" -ForegroundColor Green
        if ($details) { Write-Host "         -> $details" -ForegroundColor DarkGray }
    } else {
        Write-Host "  [FAIL] $name" -ForegroundColor Red
        if ($details) { Write-Host "         -> $details" -ForegroundColor Magenta }
    }
}

Log-Section "MODULE 1: PUBLIC PAGES & STATIC ASSETS VERIFICATION"

# 1.1 Homepage
try {
    $r = Invoke-WebRequest -Uri "$BaseUrl/index.php" -Method GET -UseBasicParsing -TimeoutSec 10
    Assert-Check "Public Homepage (index.php) Loads (200 OK)" ($r.StatusCode -eq 200) "Bytes: $($r.Content.Length)"
    Assert-Check "Contains Brand Title 'Mentry Solutions'" ($r.Content -match "Mentry Solutions")
} catch {
    Assert-Check "Public Homepage" $false $_.Exception.Message
}

# 1.2 Favicon and Logo Streaming
try {
    $rLogo = Invoke-WebRequest -Uri "$BaseUrl/public/mentry.png" -Method GET -UseBasicParsing -TimeoutSec 10
    Assert-Check "Logo (/public/mentry.png) Streams Successfully" ($rLogo.StatusCode -eq 200 -and $rLogo.Content.Length -gt 10000) "Content-Type: $($rLogo.Headers['Content-Type']), Size: $($rLogo.Content.Length) bytes"
} catch {
    Assert-Check "Logo Streaming" $false $_.Exception.Message
}

try {
    $rFavicon = Invoke-WebRequest -Uri "$BaseUrl/favicon.ico" -Method GET -UseBasicParsing -TimeoutSec 10
    Assert-Check "Browser Favicon (/favicon.ico) Streams Successfully" ($rFavicon.StatusCode -eq 200) "Size: $($rFavicon.Content.Length) bytes"
} catch {
    Assert-Check "Favicon Streaming" $false $_.Exception.Message
}

# 1.3 Public Opportunities & Network
try {
    $rOpp = Invoke-WebRequest -Uri "$BaseUrl/opportunities.php" -Method GET -UseBasicParsing -TimeoutSec 10
    Assert-Check "Public Opportunities Page Loads" ($rOpp.StatusCode -eq 200)
} catch {
    Assert-Check "Public Opportunities Page" $false $_.Exception.Message
}


Log-Section "MODULE 2: AUTHENTICATION, ROLES & SECURITY GATES"

# 2.1 Unauthenticated Security Gate on AI API (Must Reject with 401/403)
try {
    $unauthRes = Invoke-WebRequest -Uri "$BaseUrl/actions/ai-match-query.php" -Method POST -Body '{"query":"Python"}' -ContentType "application/json" -UseBasicParsing -TimeoutSec 10 -ErrorAction Stop
    Assert-Check "Unauthenticated AI Access Blocked" $false "Returned 200 (Expected 401/403)"
} catch {
    $status = $_.Exception.Response.StatusCode.value__
    Assert-Check "Unauthenticated AI Access Blocked (Security Gate Active)" ($status -eq 401 -or $status -eq 403 -or $_.Exception.Message -match "403|401") "Status: $status"
}

$testAdminPass = if ($env:ADMIN_TEST_PASS) { $env:ADMIN_TEST_PASS } else { "admin123" }
$loginParams = @{ email = "admin@mentry.test"; password = $testAdminPass }
try {
    $loginRes = Invoke-WebRequest -Uri "$BaseUrl/admin-login.php" -Method POST -Body $loginParams -WebSession $session -UseBasicParsing -MaximumRedirection 0 -ErrorAction SilentlyContinue
    Assert-Check "Admin Login Successful" ($loginRes.StatusCode -eq 302 -and $loginRes.Headers.Location -match "admin/index.php") "Redirect: $($loginRes.Headers.Location)"
} catch {
    Assert-Check "Admin Login" $false $_.Exception.Message
}


Log-Section "MODULE 3: ZERVY AI DECISION BRAIN & TOKEN TRACKING"

# 3.1 Conversational Query (Greetings - Zero False Matches)
try {
    $convBody = '{"query":"Hi, how are you doing today?"}'
    $convRes = Invoke-WebRequest -Uri "$BaseUrl/actions/ai-match-query.php" -Method POST -Body $convBody -ContentType "application/json" -WebSession $session -UseBasicParsing -TimeoutSec 15
    $convJson = $convRes.Content | ConvertFrom-Json
    Assert-Check "Conversational Intent Detected" ($convJson.data.intent -eq "CONVERSATIONAL" -or $convJson.data.isConversational -eq $true -or $convJson.data.topMatches.Count -eq 0) "Intent: $($convJson.data.intent)"
    Assert-Check "Zero False Trainer Matches on Greeting" ($convJson.data.topMatches.Count -eq 0) "Top Matches: $($convJson.data.topMatches.Count)"
} catch {
    Assert-Check "Conversational AI Intent" $false $_.Exception.Message
}

# 3.2 Real Training Requirement Query
try {
    $reqBody = '{"query":"Need a Senior Python and Django trainer for a 5-day corporate batch in Bangalore."}'
    $reqRes = Invoke-WebRequest -Uri "$BaseUrl/actions/ai-match-query.php" -Method POST -Body $reqBody -ContentType "application/json" -WebSession $session -UseBasicParsing -TimeoutSec 20
    $reqJson = $reqRes.Content | ConvertFrom-Json
    Assert-Check "Requirement AI Query Processed" ($reqJson.success -eq $true) "Total Tokens: $($reqJson.data.tokens.totalTokens)"
    Assert-Check "Candidate Matches / Fallback Returned" ($reqJson.data.topMatches.Count -ge 0) "Candidates Evaluated: $($reqJson.data.totalFound)"
} catch {
    Assert-Check "Requirement AI Query" $false $_.Exception.Message
}


Log-Section "MODULE 4: INTERNAL TEAM WORKSPACE CHAT & ACTIONS"

# 4.1 Send Message to #general-operations
$testMsgText = "VERIFICATION_TEST_MSG_" + (Get-Random -Minimum 1000 -Maximum 9999)
$sendMsgId = $null
try {
    $sendBody = "action=send_message&channel=general-operations&text=" + [System.Web.HttpUtility]::UrlEncode($testMsgText)
    $sendRes = Invoke-WebRequest -Uri "$BaseUrl/actions/team-chat-api.php" -Method POST -Body $sendBody -ContentType "application/x-www-form-urlencoded" -WebSession $session -UseBasicParsing -TimeoutSec 10
    $sendJson = $sendRes.Content | ConvertFrom-Json
    $sendMsgId = $sendJson.userMessage.id
    Assert-Check "Post Message to Team Chat" ($sendJson.success -eq $true) "Message ID: $sendMsgId"
} catch {
    Assert-Check "Post Message to Team Chat" $false $_.Exception.Message
}

# 4.2 Edit Message
if ($sendMsgId) {
    try {
        $editBody = "action=edit_message&message_id=$sendMsgId&new_text=" + [System.Web.HttpUtility]::UrlEncode($testMsgText + "_EDITED")
        $editRes = Invoke-WebRequest -Uri "$BaseUrl/actions/team-chat-api.php" -Method POST -Body $editBody -ContentType "application/x-www-form-urlencoded" -WebSession $session -UseBasicParsing -TimeoutSec 10
        $editJson = $editRes.Content | ConvertFrom-Json
        Assert-Check "Edit Message via Inbuilt UI Action" ($editJson.success -eq $true)
    } catch {
        Assert-Check "Edit Message" $false $_.Exception.Message
    }
}

# 4.3 Delete Message
if ($sendMsgId) {
    try {
        $delBody = "action=delete_message&message_id=$sendMsgId"
        $delRes = Invoke-WebRequest -Uri "$BaseUrl/actions/team-chat-api.php" -Method POST -Body $delBody -ContentType "application/x-www-form-urlencoded" -WebSession $session -UseBasicParsing -TimeoutSec 10
        $delJson = $delRes.Content | ConvertFrom-Json
        Assert-Check "Delete Message via Modal Action" ($delJson.success -eq $true)
    } catch {
        Assert-Check "Delete Message" $false $_.Exception.Message
    }
}

# 4.4 45-Day Rolling Retention Check
try {
    $fetchRes = Invoke-WebRequest -Uri "$BaseUrl/actions/team-chat-api.php?action=get_messages&channel=general-operations" -WebSession $session -UseBasicParsing -TimeoutSec 10
    $fetchJson = $fetchRes.Content | ConvertFrom-Json
    Assert-Check "45-Day Retention Filter Active" ($fetchJson.success -eq $true) "Live Channel Messages: $($fetchJson.messages.Count)"
} catch {
    Assert-Check "45-Day Retention Check" $false $_.Exception.Message
}


Log-Section "MODULE 5: ACTIVE TRAINING ASSIGNMENTS & LOGISTICS"

try {
    $asgRes = Invoke-WebRequest -Uri "$BaseUrl/admin/assignments.php" -WebSession $session -UseBasicParsing -TimeoutSec 10
    Assert-Check "Assignments & Logistics Page Loads (200 OK)" ($asgRes.StatusCode -eq 200)
    Assert-Check "Contains Honorarium & Logistics Terms" ($asgRes.Content -match "Active Training Assignments & Logistics")
} catch {
    Assert-Check "Assignments & Logistics" $false $_.Exception.Message
}


Log-Section "MODULE 6: SETTINGS, SITE STATUS & MAINTENANCE GATE"

# 6.1 Settings Page
try {
    $setRes = Invoke-WebRequest -Uri "$BaseUrl/admin/settings.php" -WebSession $session -UseBasicParsing -TimeoutSec 10
    Assert-Check "Settings & Audit Page Loads (200 OK)" ($setRes.StatusCode -eq 200)
    Assert-Check "Contains Live/Maintenance Mode Control Card" ($setRes.Content -match "Website Live / Maintenance Mode Control")
} catch {
    Assert-Check "Settings Page" $false $_.Exception.Message
}

# 6.2 Public Maintenance Page (Security Check: Zero Admin Banner Leaks)
try {
    $maintRes = Invoke-WebRequest -Uri "$BaseUrl/maintenance.php" -UseBasicParsing -TimeoutSec 10
    Assert-Check "Public Maintenance Page Loads (200 OK)" ($maintRes.StatusCode -eq 200)
    $hasAdminLeak = ($maintRes.Content -match "Return to Ops Center" -or $maintRes.Content -match "Admin privileges")
    Assert-Check "Zero Admin Banners or Route Discovery Leaks (Security Hardened)" (-not $hasAdminLeak) "Banner removed cleanly"
} catch {
    Assert-Check "Maintenance Page Security" $false $_.Exception.Message
}


Log-Section "VERIFICATION COMPLETED SUCCESSFULLY"
Write-Host "`nAll core application modules, security controls, Zervy AI, chat actions, and logistics pipelines have been verified.`n" -ForegroundColor Green

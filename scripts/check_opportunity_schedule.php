<?php
// scripts/check_opportunity_schedule.php - CLI / Cron Schedule Worker

// Security (Requirement 58): Restrict execution strictly to CLI or authorized administrators
if (php_sapi_name() !== 'cli') {
    require_once __DIR__ . '/../includes/auth.php';
    if (!isLoggedIn() || !isAdminOrStaff()) {
        http_response_code(403);
        die("Access Denied: Scheduled worker execution restricted to CLI or administrators.");
    }
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/notifications.php';

echo "=== MENTRY SCHEDULE & AUTO-CLOSE WORKER ===\n";
echo "Run Time: " . date('Y-m-d H:i:s') . "\n";

$result = checkOpportunityScheduleMilestones(true);

echo "Opportunities Evaluated: " . ($result['checked'] ?? 0) . "\n";
echo "Alerts Sent (Tomorrow): " . ($result['notifiedTomorrow'] ?? 0) . "\n";
echo "Alerts Sent (In 2 Days): " . ($result['notifiedIn2Days'] ?? 0) . "\n";
echo "Opportunities Auto-Closed (Past Date): " . ($result['closed'] ?? 0) . "\n";
echo "=== COMPLETED ===\n";

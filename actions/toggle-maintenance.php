<?php
// actions/toggle-maintenance.php - Toggle Maintenance Mode (Admin & Staff Only)
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/maintenance.php';

requireAdminOrStaff();

$newStatus = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'GET') {
    $currentConfig = getMaintenanceConfig();
    $currentStatus = !empty($currentConfig['maintenance_mode']);
    
    // Toggle status or set explicit value if provided
    if (isset($_POST['active'])) {
        $newStatus = in_array($_POST['active'], ['1', 'true', true, 1], true);
    } elseif (isset($_GET['active'])) {
        $newStatus = in_array($_GET['active'], ['1', 'true', true, 1], true);
    } else {
        $newStatus = !$currentStatus;
    }

    $message = isset($_POST['message']) && trim($_POST['message']) !== '' ? trim($_POST['message']) : null;
    $estimatedReturn = isset($_POST['estimated_return']) && trim($_POST['estimated_return']) !== '' ? trim($_POST['estimated_return']) : null;

    setMaintenanceMode($newStatus, $message, $estimatedReturn);
}

// Return JSON if AJAX request
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'maintenance_mode' => $newStatus]);
    exit();
}

$redirect = $_POST['return_url'] ?? $_GET['return_url'] ?? $_SERVER['HTTP_REFERER'] ?? '/admin/index.php';
header("Location: " . $redirect);
exit();

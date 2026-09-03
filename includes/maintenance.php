<?php
// includes/maintenance.php - Maintenance & Work-in-Progress System Controller
require_once __DIR__ . '/auth.php';

function getMaintenanceConfig() {
    $configFile = __DIR__ . '/../config/system_status.json';
    if (!file_exists($configFile)) {
        return [
            'maintenance_mode' => false,
            'message' => 'Platform upgrades in progress.',
            'estimated_return' => 'Soon',
            'updated_at' => date('Y-m-d H:i:s')
        ];
    }
    $content = @file_get_contents($configFile);
    $data = json_decode($content, true);
    return is_array($data) ? $data : ['maintenance_mode' => false];
}

function isMaintenanceActive() {
    $config = getMaintenanceConfig();
    if (empty($config['maintenance_mode'])) {
        return false;
    }

    // Check if secret bypass key provided: ?bypass=mentry2026
    if (isset($_GET['bypass']) && $_GET['bypass'] === 'mentry2026') {
        $_SESSION['maintenance_bypass'] = true;
    }
    if (!empty($_SESSION['maintenance_bypass'])) {
        return false;
    }

    // CRITICAL: Admins and Staff ALWAYS bypass maintenance mode
    if (isAdminOrStaff()) {
        return false;
    }

    return true;
}

function setMaintenanceMode($active, $message = null, $estimatedReturn = null) {
    $configFile = __DIR__ . '/../config/system_status.json';
    $config = getMaintenanceConfig();

    $config['maintenance_mode'] = (bool)$active;
    if ($message !== null) $config['message'] = $message;
    if ($estimatedReturn !== null) $config['estimated_return'] = $estimatedReturn;
    $config['updated_at'] = date('Y-m-d H:i:s');

    @file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));
    return $config;
}

function checkMaintenanceGate() {
    $exemptFiles = ['maintenance.php', 'admin-login.php', 'logout.php', 'toggle-maintenance.php'];
    $currentScript = basename($_SERVER['PHP_SELF']);

    if (in_array($currentScript, $exemptFiles)) {
        return;
    }

    if (isMaintenanceActive()) {
        header("Location: /maintenance.php");
        exit();
    }
}

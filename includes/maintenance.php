<?php
// includes/maintenance.php - Maintenance & Work-in-Progress System Controller
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

function getMaintenanceConfig($forceReload = false) {
    static $memoryCache = null;
    if ($forceReload) {
        $memoryCache = null;
    }
    if ($memoryCache !== null) {
        return $memoryCache;
    }

    $defaultConfig = [
        'maintenance_mode' => false,
        'message' => 'We are currently conducting scheduled platform upgrades to optimize campus trainer sourcing and matchmaking.',
        'estimated_return' => 'Within 1 hour',
        'updated_at' => date('Y-m-d H:i:s')
    ];

    // 1. Check Supabase-backed SystemConfig collection first
    try {
        $col = getCollection("SystemConfig");
        if ($col) {
            $doc = $col->findOne(['key' => 'maintenance']);
            if ($doc && isset($doc['maintenance_mode'])) {
                $memoryCache = [
                    'maintenance_mode' => (bool)$doc['maintenance_mode'],
                    'message' => !empty($doc['message']) ? $doc['message'] : $defaultConfig['message'],
                    'estimated_return' => !empty($doc['estimated_return']) ? $doc['estimated_return'] : $defaultConfig['estimated_return'],
                    'updated_at' => $doc['updated_at'] ?? date('Y-m-d H:i:s')
                ];
                return $memoryCache;
            }
        }
    } catch (\Throwable $e) {
        // Fallback to local files
    }

    // 2. Fallback to /tmp on serverless environments
    $tmpFile = sys_get_temp_dir() . '/system_status.json';
    if (file_exists($tmpFile)) {
        $content = @file_get_contents($tmpFile);
        $data = json_decode($content, true);
        if (is_array($data) && isset($data['maintenance_mode'])) {
            $memoryCache = array_merge($defaultConfig, $data);
            return $memoryCache;
        }
    }

    // 3. Fallback to config directory
    $configFile = __DIR__ . '/../config/system_status.json';
    if (file_exists($configFile)) {
        $content = @file_get_contents($configFile);
        $data = json_decode($content, true);
        if (is_array($data) && isset($data['maintenance_mode'])) {
            $memoryCache = array_merge($defaultConfig, $data);
            return $memoryCache;
        }
    }

    $memoryCache = $defaultConfig;
    return $memoryCache;
}

function isMaintenanceActive() {
    $config = getMaintenanceConfig();
    if (empty($config['maintenance_mode'])) {
        return false;
    }

    // Secret URL bypass: ?bypass=mentry2026
    if (isset($_GET['bypass']) && $_GET['bypass'] === 'mentry2026') {
        $_SESSION['maintenance_bypass'] = true;
    }
    if (!empty($_SESSION['maintenance_bypass'])) {
        return false;
    }

    // Direct role check in session
    $role = $_SESSION['user']['role'] ?? ($_SESSION['admin_user']['role'] ?? ($_SESSION['role'] ?? ''));
    if (in_array($role, ['ADMIN', 'SUPER_ADMIN', 'STAFF'])) {
        return false;
    }

    // CRITICAL: Admins and Staff ALWAYS bypass maintenance mode
    if (isAdminOrStaff()) {
        return false;
    }

    return true;
}

function setMaintenanceMode($active, $message = null, $estimatedReturn = null) {
    $current = getMaintenanceConfig(true);
    $activeBool = (bool)$active;

    $updatedData = [
        'key' => 'maintenance',
        '_id' => 'maintenance_mode_config',
        'maintenance_mode' => $activeBool,
        'message' => $message !== null ? $message : ($current['message'] ?? 'We are currently conducting scheduled platform upgrades to optimize campus trainer sourcing and matchmaking.'),
        'estimated_return' => $estimatedReturn !== null ? $estimatedReturn : ($current['estimated_return'] ?? 'Within 1 hour'),
        'updated_at' => date('Y-m-d H:i:s')
    ];

    // 1. Persist to DB Collection (syncs to Supabase cloud)
    try {
        $col = getCollection("SystemConfig");
        if ($col) {
            $col->updateOne(
                ['key' => 'maintenance'],
                ['$set' => $updatedData],
                ['upsert' => true]
            );
        }
    } catch (\Throwable $e) {
        error_log("Failed to save maintenance mode in DB: " . $e->getMessage());
    }

    // 2. Persist to /tmp on serverless hosts
    $tmpFile = sys_get_temp_dir() . '/system_status.json';
    @file_put_contents($tmpFile, json_encode($updatedData, JSON_PRETTY_PRINT));

    // 3. Persist to local config folder if writable
    $configFile = __DIR__ . '/../config/system_status.json';
    @file_put_contents($configFile, json_encode($updatedData, JSON_PRETTY_PRINT));

    // Force refresh cache
    getMaintenanceConfig(true);

    return $updatedData;
}

function checkMaintenanceGate() {
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $script = basename($_SERVER['PHP_SELF'] ?? '');

    // Exempt endpoints
    $exemptFiles = ['maintenance.php', 'admin-login.php', 'logout.php', 'toggle-maintenance.php'];
    if (in_array($script, $exemptFiles, true)) {
        return;
    }

    // Exempt all /admin/ routes for authorized command operations
    if (strpos($uri, '/admin/') === 0 || strpos($uri, '/actions/toggle-maintenance') === 0) {
        return;
    }

    // Exempt static files (images, css, js, fonts)
    if (preg_match('/\.(?:png|jpg|jpeg|gif|svg|ico|css|js|woff|woff2|ttf|pdf|webp)$/i', $uri)) {
        return;
    }

    if (isMaintenanceActive()) {
        if (!headers_sent()) {
            header("Location: /maintenance.php");
            exit();
        } else {
            echo "<script>window.location.href='/maintenance.php';</script>";
            exit();
        }
    }
}

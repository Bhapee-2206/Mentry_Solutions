<?php
// includes/auth.php - Secure Authentication & Role-Based Access Control

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

function isHttpsRequest() {
    if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) {
        return true;
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
        return true;
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on') {
        return true;
    }
    if (!empty($_SERVER['HTTP_FRONT_END_HTTPS']) && strtolower($_SERVER['HTTP_FRONT_END_HTTPS']) !== 'off') {
        return true;
    }
    return false;
}

function getAuthSecret() {
    $secret = getenv('JWT_SECRET') ?: ($_ENV['JWT_SECRET'] ?? ($_SERVER['JWT_SECRET'] ?? ''));
    if (empty($secret)) {
        $envPath = __DIR__ . '/../.env';
        if (file_exists($envPath)) {
            $lines = @file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines) {
                foreach ($lines as $l) {
                    if (strpos(trim($l), 'JWT_SECRET=') === 0) {
                        $secret = trim(substr(trim($l), strlen('JWT_SECRET=')), '"\'');
                    }
                }
            }
        }
    }
    return !empty($secret) ? $secret : 'mentry-persistent-auth-secret-key-2026-prod';
}

function setPersistentSessionCookie(array $userData, $rememberDays = 30) {
    if (empty($userData['id'])) return;
    $payload = [
        'id' => (string)$userData['id'],
        'email' => $userData['email'] ?? '',
        'name' => $userData['name'] ?? '',
        'role' => $userData['role'] ?? 'TRAINER',
        'avatar' => $userData['avatar'] ?? '',
        'trainerCode' => $userData['trainerCode'] ?? '',
        'mentryId' => $userData['mentryId'] ?? '',
        'issued_at' => time()
    ];
    $json = json_encode($payload);
    $b64 = rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    $sig = hash_hmac('sha256', $b64, getAuthSecret());
    $token = $b64 . '.' . $sig;
    
    $expire = time() + ($rememberDays * 86400);
    $isSecure = isHttpsRequest();
    
    // Omit 'domain' completely so PHP never emits an invalid 'Domain=;' header
    $cookieOptions = [
        'expires' => $expire,
        'path' => '/',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax'
    ];
    setcookie('mentry_session_token', $token, $cookieOptions);
    $_COOKIE['mentry_session_token'] = $token;
}

function clearPersistentSessionCookie() {
    $isSecure = isHttpsRequest();
    $cookieOptions = [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax'
    ];
    setcookie('mentry_session_token', '', $cookieOptions);
    unset($_COOKIE['mentry_session_token']);
}

function restoreSessionFromCookie() {
    if (!empty($_SESSION['user']['id'])) {
        return $_SESSION['user'];
    }
    if (empty($_COOKIE['mentry_session_token'])) {
        return null;
    }
    $token = $_COOKIE['mentry_session_token'];
    $parts = explode('.', $token);
    if (count($parts) !== 2) {
        return null;
    }
    list($b64, $sig) = $parts;
    $expectedSig = hash_hmac('sha256', $b64, getAuthSecret());
    if (!hash_equals($expectedSig, $sig)) {
        return null;
    }
    $json = base64_decode(strtr($b64, '-_', '+/'));
    if (!$json) {
        return null;
    }
    $data = json_decode($json, true);
    if (!is_array($data) || empty($data['id'])) {
        return null;
    }
    if (isset($data['issued_at']) && (time() - $data['issued_at']) > (30 * 86400)) {
        clearPersistentSessionCookie();
        return null;
    }
    $_SESSION['user'] = [
        'id' => (string)$data['id'],
        'email' => $data['email'] ?? '',
        'name' => $data['name'] ?? '',
        'role' => $data['role'] ?? 'TRAINER',
        'avatar' => $data['avatar'] ?? ('https://ui-avatars.com/api/?name=' . urlencode($data['name'] ?? 'User')),
        'trainerCode' => $data['trainerCode'] ?? '',
        'mentryId' => $data['mentryId'] ?? ''
    ];
    return $_SESSION['user'];
}

// Auto-restore session immediately on file load if session is empty
if (empty($_SESSION['user']) || empty($_SESSION['user']['id'])) {
    restoreSessionFromCookie();
}


function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function getCurrentUser() {
    if (empty($_SESSION['user']) || empty($_SESSION['user']['id'])) {
        restoreSessionFromCookie();
    }
    return $_SESSION['user'] ?? null;
}

function isLoggedIn() {
    $user = getCurrentUser();
    return !empty($user) && !empty($user['id']);
}

function isTrainer() {
    $user = getCurrentUser();
    if (!$user) return false;
    return ($user['role'] === 'TRAINER' || $user['role'] === 'ADMIN' || $user['role'] === 'SUPER_ADMIN');
}

function isVendor() {
    $user = getCurrentUser();
    if (!$user) return false;
    return ($user['role'] === 'VENDOR' || $user['role'] === 'COLLEGE' || $user['role'] === 'ADMIN' || $user['role'] === 'SUPER_ADMIN');
}

function isAdmin() {
    $user = getCurrentUser();
    if (!$user) return false;
    return ($user['role'] === 'ADMIN' || $user['role'] === 'SUPER_ADMIN');
}

function isStaff() {
    $user = getCurrentUser();
    if (!$user) return false;
    return ($user['role'] === 'STAFF');
}

function isAdminOrStaff() {
    $user = getCurrentUser();
    if (!$user) return false;
    return in_array($user['role'], ['ADMIN', 'SUPER_ADMIN', 'STAFF']);
}

/**
 * Sends strict anti-cache headers to prevent browser caching of sensitive auth forms,
 * bfcache restores, and back-navigation form resubmission (Alt + Left Arrow).
 */
function sendAntiCacheHeaders() {
    if (!headers_sent()) {
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0, post-check=0, pre-check=0");
        header("Pragma: no-cache");
        header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");
    }
}

function requireAuth() {
    sendAntiCacheHeaders();
    if (!isLoggedIn()) {
        header("Location: /login.php");
        exit();
    }
}

function requireTrainer() {
    sendAntiCacheHeaders();
    if (!isLoggedIn()) {
        header("Location: /login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
        exit();
    }
    
    $user = getCurrentUser();
    if ($user['role'] !== 'TRAINER' && $user['role'] !== 'ADMIN' && $user['role'] !== 'SUPER_ADMIN') {
        header("Location: /login.php?error=trainer_required");
        exit();
    }
}

function requireVendor() {
    sendAntiCacheHeaders();
    if (!isLoggedIn()) {
        header("Location: /vendor-login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
        exit();
    }
    
    $user = getCurrentUser();
    if ($user['role'] !== 'VENDOR' && $user['role'] !== 'COLLEGE' && $user['role'] !== 'ADMIN' && $user['role'] !== 'SUPER_ADMIN') {
        header("Location: /vendor-login.php?error=vendor_required");
        exit();
    }
}

function requireAdmin() {
    sendAntiCacheHeaders();
    if (!isLoggedIn()) {
        header("Location: /admin-login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
        exit();
    }
    
    if (!isAdmin()) {
        header("Location: /admin-login.php?error=unauthorized");
        exit();
    }
}

function requireAdminOrStaff() {
    sendAntiCacheHeaders();
    if (!isLoggedIn()) {
        header("Location: /admin-login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
        exit();
    }
    
    if (!isAdminOrStaff()) {
        header("Location: /admin-login.php?error=unauthorized");
        exit();
    }
}

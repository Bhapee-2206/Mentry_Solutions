<?php
// includes/auth.php - Secure Authentication & Role-Based Access Control

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';

function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function getCurrentUser() {
    return $_SESSION['user'] ?? null;
}

function isLoggedIn() {
    return isset($_SESSION['user']) && !empty($_SESSION['user']['id']);
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

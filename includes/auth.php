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

function requireAuth() {
    if (!isLoggedIn()) {
        header("Location: /login.php");
        exit();
    }
}

function requireTrainer() {
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
    if (!isLoggedIn()) {
        header("Location: /admin-login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
        exit();
    }
    
    if (!isAdminOrStaff()) {
        header("Location: /admin-login.php?error=unauthorized");
        exit();
    }
}

<?php
// includes/auth.php - Secure Authentication & Role-Based Access Control

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

if (session_status() === PHP_SESSION_NONE) {
    $isHttps = isHttpsRequest();
    @ini_set('session.cookie_httponly', '1');
    @ini_set('session.use_only_cookies', '1');
    if ($isHttps) {
        @ini_set('session.cookie_secure', '1');
    }
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'cookie_secure' => $isHttps
    ]);
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/maintenance.php';

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
    if (!headers_sent()) {
        setcookie('mentry_session_token', $token, $cookieOptions);
    }
    $_COOKIE['mentry_session_token'] = $token;
}

function issuePersistentSessionCookie(array $userData, $rememberDays = 30) {
    setPersistentSessionCookie($userData, $rememberDays);
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
    if (!headers_sent()) {
        setcookie('mentry_session_token', '', $cookieOptions);
    }
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

    $restoredAvatar = $data['avatar'] ?? '';
    if (empty($restoredAvatar) || strpos($restoredAvatar, 'ui-avatars.com') !== false || strpos($restoredAvatar, 'avatar.vercel.sh') !== false) {
        $liveAvatar = getLiveUserAvatar((string)$data['id']);
        if (!empty($liveAvatar)) {
            $restoredAvatar = $liveAvatar;
        }
    }
    if (empty($restoredAvatar)) {
        $restoredAvatar = 'https://ui-avatars.com/api/?name=' . urlencode($data['name'] ?? 'User');
    }

    $_SESSION['user'] = [
        'id' => (string)$data['id'],
        'email' => $data['email'] ?? '',
        'name' => $data['name'] ?? '',
        'role' => $data['role'] ?? 'TRAINER',
        'avatar' => $restoredAvatar,
        'trainerCode' => $data['trainerCode'] ?? '',
        'mentryId' => $data['mentryId'] ?? ''
    ];
    return $_SESSION['user'];
}

/**
 * Resolves the freshest saved avatar from DB for persistent across refreshes
 */
function getLiveUserAvatar(string $userId): ?string {
    if (empty($userId)) return null;
    try {
        $idQueries = [(string)$userId];
        if (preg_match('/^[a-f\d]{24}$/i', $userId)) {
            try { $idQueries[] = new MongoDB\BSON\ObjectId($userId); } catch (\Throwable $e) {}
        }

        // 1. Check Trainer collection by userId or _id
        $trainerCol = getCollection("Trainer");
        if ($trainerCol) {
            $tr = $trainerCol->findOne([
                '$or' => [
                    ['userId' => ['$in' => $idQueries]],
                    ['_id' => ['$in' => $idQueries]]
                ]
            ]);
            if ($tr && !empty($tr['avatar']) && strpos($tr['avatar'], 'ui-avatars.com') === false && strpos($tr['avatar'], 'avatar.vercel.sh') === false) {
                return (string)$tr['avatar'];
            }
        }

        // 2. Check User collection by _id
        $userCol = getCollection("User");
        if ($userCol) {
            $u = $userCol->findOne(['_id' => ['$in' => $idQueries]]);
            if ($u && !empty($u['avatar']) && strpos($u['avatar'], 'ui-avatars.com') === false && strpos($u['avatar'], 'avatar.vercel.sh') === false) {
                return (string)$u['avatar'];
            }
        }
    } catch (\Throwable $e) {}
    return null;
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
    $user = $_SESSION['user'] ?? null;
    if (!$user || empty($user['id'])) {
        return null;
    }

    // Live avatar synchronization: if session avatar is empty or placeholder, check DB for uploaded avatar
    if (empty($user['avatar']) || strpos($user['avatar'], 'ui-avatars.com') !== false || strpos($user['avatar'], 'avatar.vercel.sh') !== false) {
        $liveAvatar = getLiveUserAvatar((string)$user['id']);
        if (!empty($liveAvatar)) {
            $user['avatar'] = $liveAvatar;
            $_SESSION['user']['avatar'] = $liveAvatar;
            setPersistentSessionCookie($_SESSION['user']);
        }
    }

    // Live suspension check: If trainer account is suspended, invalidate session immediately!
    if (($user['role'] ?? '') === 'TRAINER') {
        $isSuspended = false;
        if (($user['status'] ?? '') === 'SUSPENDED' || !empty($user['isSuspended'])) {
            $isSuspended = true;
        } else {
            $trainerCol = getCollection("Trainer");
            if ($trainerCol) {
                $tQuery = ['userId' => (string)$user['id']];
                if (preg_match('/^[a-f\d]{24}$/i', (string)$user['id'])) {
                    $tQuery = ['$or' => [['userId' => (string)$user['id']], ['userId' => new MongoDB\BSON\ObjectId((string)$user['id'])]]];
                }
                $tr = $trainerCol->findOne($tQuery);
                if ($tr && ($tr['status'] ?? '') === 'SUSPENDED') {
                    $isSuspended = true;
                }
            }
            if (!$isSuspended) {
                $userCol = getCollection("User");
                if ($userCol) {
                    $uQuery = ['_id' => (string)$user['id']];
                    if (preg_match('/^[a-f\d]{24}$/i', (string)$user['id'])) {
                        $uQuery = ['$or' => [['_id' => (string)$user['id']], ['_id' => new MongoDB\BSON\ObjectId((string)$user['id'])]]];
                    }
                    $u = $userCol->findOne($uQuery);
                    if ($u && (($u['status'] ?? '') === 'SUSPENDED' || !empty($u['isSuspended']))) {
                        $isSuspended = true;
                    }
                }
            }
        }

        if ($isSuspended) {
            $_SESSION = [];
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_destroy();
            }
            clearPersistentSessionCookie();
            return null;
        }
    }

    return $user;
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
    checkMaintenanceGate();
    if (!isLoggedIn()) {
        header("Location: /login.php");
        exit();
    }
}

function requireTrainer() {
    sendAntiCacheHeaders();
    checkMaintenanceGate();
    $user = getCurrentUser();
    if (!$user || empty($user['id'])) {
        header("Location: /login.php?error=suspended");
        exit();
    }
    
    if ($user['role'] !== 'TRAINER' && $user['role'] !== 'ADMIN' && $user['role'] !== 'SUPER_ADMIN') {
        header("Location: /login.php?error=trainer_required");
        exit();
    }
}

function requireVendor() {
    sendAntiCacheHeaders();
    checkMaintenanceGate();
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

/**
 * Check if the given account/email is temporarily locked out due to excessive failed password attempts.
 * Max attempts: 5. Lockout duration: 15 minutes (900 seconds).
 *
 * @param string $email User email address
 * @return array ['isLocked' => bool, 'minutesLeft' => int, 'secondsLeft' => int, 'attempts' => int, 'message' => string]
 */
function checkLoginRateLimit($email) {
    $email = strtolower(trim($email));
    if (empty($email)) {
        return ['isLocked' => false, 'attempts' => 0, 'minutesLeft' => 0, 'secondsLeft' => 0, 'message' => ''];
    }

    $lockCol = getCollection("LoginAttempt");
    if (!$lockCol) {
        return ['isLocked' => false, 'attempts' => 0, 'minutesLeft' => 0, 'secondsLeft' => 0, 'message' => ''];
    }

    $now = time();
    $record = $lockCol->findOne(['email' => $email]);
    if (!$record) {
        return ['isLocked' => false, 'attempts' => 0, 'minutesLeft' => 0, 'secondsLeft' => 0, 'message' => ''];
    }

    $lockedUntil = null;
    if (isset($record['lockedUntil'])) {
        $lu = $record['lockedUntil'];
        if ($lu instanceof MongoDB\BSON\UTCDateTime) {
            $lockedUntil = (int)round($lu->toDateTime()->getTimestamp());
        } elseif (is_numeric($lu)) {
            $lockedUntil = ($lu > 20000000000) ? (int)round($lu / 1000) : (int)$lu;
        } elseif (is_string($lu)) {
            $lockedUntil = is_numeric($lu) ? (($lu > 20000000000) ? (int)round($lu / 1000) : (int)$lu) : strtotime($lu);
        }
    }

    if ($lockedUntil && $now < $lockedUntil) {
        $secondsLeft = $lockedUntil - $now;
        $minutesLeft = max(1, (int)ceil($secondsLeft / 60));
        return [
            'isLocked' => true,
            'attempts' => (int)($record['attempts'] ?? 5),
            'minutesLeft' => $minutesLeft,
            'secondsLeft' => $secondsLeft,
            'message' => "Account temporarily locked due to excessive failed attempts. Please try again in {$minutesLeft} minute" . ($minutesLeft > 1 ? 's' : '') . " or reset your password."
        ];
    }

    // If lockout has expired, reset attempt count
    if ($lockedUntil && $now >= $lockedUntil) {
        $lockCol->updateOne(
            ['email' => $email],
            ['$set' => [
                'attempts' => 0,
                'lockedUntil' => null,
                'updatedAt' => new MongoDB\BSON\UTCDateTime()
            ]]
        );
        return ['isLocked' => false, 'attempts' => 0, 'minutesLeft' => 0, 'secondsLeft' => 0, 'message' => ''];
    }

    return [
        'isLocked' => false,
        'attempts' => (int)($record['attempts'] ?? 0),
        'minutesLeft' => 0,
        'secondsLeft' => 0,
        'message' => ''
    ];
}

/**
 * Record a failed password attempt for an account/email.
 * Increments attempt count and locks for 15 minutes if 5 attempts reached.
 *
 * @param string $email User email address
 * @return array ['isLocked' => bool, 'attempts' => int, 'remaining' => int, 'message' => string]
 */
function recordFailedLoginAttempt($email) {
    $email = strtolower(trim($email));
    if (empty($email)) {
        return ['isLocked' => false, 'attempts' => 1, 'remaining' => 4, 'message' => 'Invalid password.'];
    }

    $lockCol = getCollection("LoginAttempt");
    $maxAttempts = 5;
    $lockoutDuration = 900; // 15 minutes
    $now = time();

    $record = $lockCol ? $lockCol->findOne(['email' => $email]) : null;
    $currentAttempts = $record ? (int)($record['attempts'] ?? 0) : 0;
    $newAttempts = $currentAttempts + 1;

    $isLocked = ($newAttempts >= $maxAttempts);
    $lockedUntil = $isLocked ? new MongoDB\BSON\UTCDateTime(($now + $lockoutDuration) * 1000) : null;

    if ($lockCol) {
        $lockCol->updateOne(
            ['email' => $email],
            ['$set' => [
                'email' => $email,
                'attempts' => $newAttempts,
                'lastAttemptAt' => new MongoDB\BSON\UTCDateTime($now * 1000),
                'lockedUntil' => $lockedUntil,
                'updatedAt' => new MongoDB\BSON\UTCDateTime()
            ]],
            ['upsert' => true]
        );
    }

    // Also reflect on User document if exists
    $userCol = getCollection("User");
    if ($userCol) {
        $userCol->updateOne(
            ['email' => new MongoDB\BSON\Regex('^' . preg_quote($email) . '$', 'i')],
            ['$set' => [
                'failedLoginAttempts' => $newAttempts,
                'lockedUntil' => $lockedUntil,
                'updatedAt' => new MongoDB\BSON\UTCDateTime()
            ]]
        );
    }

    if ($isLocked) {
        return [
            'isLocked' => true,
            'attempts' => $newAttempts,
            'remaining' => 0,
            'message' => "Too many failed attempts (5/5). For your security, this account has been locked for 15 minutes. You can reset your password or try again later."
        ];
    } else {
        $remaining = $maxAttempts - $newAttempts;
        $warning = ($remaining <= 2) ? " Warning: {$remaining} attempt" . ($remaining === 1 ? '' : 's') . " remaining before account lockout." : "";
        return [
            'isLocked' => false,
            'attempts' => $newAttempts,
            'remaining' => $remaining,
            'message' => "Invalid email or password.{$warning}"
        ];
    }
}

/**
 * Reset failed login attempts upon successful authentication
 *
 * @param string $email
 */
function resetLoginAttempts($email) {
    $email = strtolower(trim($email));
    if (empty($email)) return;

    $lockCol = getCollection("LoginAttempt");
    if ($lockCol) {
        $lockCol->deleteOne(['email' => $email]);
    }

    $userCol = getCollection("User");
    if ($userCol) {
        $userCol->updateOne(
            ['email' => new MongoDB\BSON\Regex('^' . preg_quote($email) . '$', 'i')],
            ['$set' => [
                'failedLoginAttempts' => 0,
                'lockedUntil' => null,
                'updatedAt' => new MongoDB\BSON\UTCDateTime()
            ]]
        );
    }
}


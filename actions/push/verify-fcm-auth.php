<?php
// actions/push/verify-fcm-auth.php - Diagnostic FCM HTTP v1 Authentication Verifier
// Tests Google OAuth2 JWT assertion exchange with Google without exposing credentials.

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/push/NativeFcmConfig.php';
require_once __DIR__ . '/../../includes/push/NativeFcmService.php';

$isConfigured = NativeFcmConfig::isConfigured();
$projectId = NativeFcmConfig::getProjectId();
$clientEmail = NativeFcmConfig::getClientEmail();
$privateKey = NativeFcmConfig::getPrivateKey();

// Mask client email safely
$maskedEmail = 'not_set';
if (!empty($clientEmail)) {
    $parts = explode('@', $clientEmail);
    $userPart = $parts[0] ?? '';
    $domainPart = $parts[1] ?? '';
    $maskedUser = strlen($userPart) > 6 ? substr($userPart, 0, 5) . '...' . substr($userPart, -3) : $userPart;
    $maskedEmail = $maskedUser . '@' . $domainPart;
}

$privateKeyValid = false;
$privateKeyFormat = 'none';
if (!empty($privateKey)) {
    $res = @openssl_pkey_get_private($privateKey);
    if ($res !== false) {
        $privateKeyValid = true;
        $privateKeyFormat = 'valid_rsa_key';
    } else {
        $privateKeyFormat = 'invalid_format: ' . (openssl_error_string() ?: 'parse error');
    }
}

$oauth2Result = [
    'tested' => false,
    'success' => false,
    'error' => null,
    'tokenType' => null
];

if ($isConfigured && $privateKeyValid) {
    try {
        $token = NativeFcmService::getAccessToken();
        if (!empty($token)) {
            $oauth2Result['tested'] = true;
            $oauth2Result['success'] = true;
            // Report only safe token prefix e.g. ya29.
            $oauth2Result['tokenType'] = substr($token, 0, 5) . '...';
        }
    } catch (\Throwable $e) {
        $oauth2Result['tested'] = true;
        $oauth2Result['success'] = false;
        $oauth2Result['error'] = $e->getMessage();
    }
}

$allKeys = array_unique(array_merge(array_keys($_SERVER), array_keys($_ENV)));
$fcmRelatedKeys = array_values(array_filter($allKeys, function($k) {
    return stripos($k, 'fcm') !== false || stripos($k, 'firebase') !== false;
}));

echo json_encode([
    'timestamp' => date('c'),
    'environment' => getenv('VERCEL') ? 'vercel_production' : 'local',
    'detectedFcmKeys' => $fcmRelatedKeys,
    'fcmConfigured' => $isConfigured,
    'projectId' => $projectId ?: 'missing',
    'clientEmailMasked' => $maskedEmail,
    'privateKeyPresent' => !empty($privateKey),
    'privateKeyValid' => $privateKeyValid,
    'privateKeyFormat' => $privateKeyFormat,
    'googleOAuth2' => $oauth2Result
], JSON_PRETTY_PRINT);

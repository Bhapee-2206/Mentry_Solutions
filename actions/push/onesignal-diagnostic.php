<?php
// Temporary Admin/Staff-only OneSignal authentication diagnostic.
header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    @session_start();
}

require_once __DIR__ . '/../../includes/auth.php';

if (!isAdminOrStaff()) {
    http_response_code(403);
    echo json_encode(['error' => 'Admin or Staff access required.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit();
}

$appId = trim((string)(getenv('ONESIGNAL_APP_ID') ?: ($_ENV['ONESIGNAL_APP_ID'] ?? ($_SERVER['ONESIGNAL_APP_ID'] ?? ''))));
$apiKey = trim(trim((string)(getenv('ONESIGNAL_REST_API_KEY') ?: ($_ENV['ONESIGNAL_REST_API_KEY'] ?? ($_SERVER['ONESIGNAL_REST_API_KEY'] ?? '')))), "\"' \t\n\r");
$endpoint = 'https://api.onesignal.com/notifications';
$payload = json_encode([
    'app_id' => $appId,
    'include_aliases' => ['external_id' => ['6a99b5baf1624b69330f560e']],
    'target_channel' => 'push',
    'headings' => ['en' => 'Mentry OneSignal diagnostic'],
    'contents' => ['en' => 'Temporary authentication test.'],
    'ttl' => 300
], JSON_UNESCAPED_SLASHES);

if ($appId === '' || $apiKey === '') {
    http_response_code(500);
    echo json_encode([
        'httpStatus' => 0,
        'response' => ['error' => 'Required OneSignal runtime configuration is missing.'],
        'appId' => $appId,
        'apiKeyLength' => strlen($apiKey),
        'reachedOneSignal' => false,
        'deploymentCommitSha' => getenv('VERCEL_GIT_COMMIT_SHA') ?: 'unavailable'
    ]);
    exit();
}

$curl = curl_init($endpoint);
curl_setopt_array($curl, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => [
        'Authorization: Key ' . $apiKey,
        'Content-Type: application/json'
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_SSL_VERIFYPEER => true
]);
$response = curl_exec($curl);
$httpStatus = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
$curlError = curl_error($curl);
curl_close($curl);

$decoded = json_decode($response ?: '', true);
if (!is_array($decoded)) {
    $decoded = ['message' => 'Non-JSON response from OneSignal.'];
}
$sanitized = [];
foreach (['errors', 'warnings', 'id', 'recipients', 'external_id'] as $key) {
    if (array_key_exists($key, $decoded)) {
        $sanitized[$key] = $decoded[$key];
    }
}
if (empty($sanitized)) {
    $sanitized = ['message' => $httpStatus > 0 ? 'OneSignal returned a response without reportable fields.' : 'No response received from OneSignal.'];
}

echo json_encode([
    'httpStatus' => $httpStatus,
    'response' => $sanitized,
    'appId' => $appId,
    'apiKeyLength' => strlen($apiKey),
    'reachedOneSignal' => $response !== false && $httpStatus > 0,
    'curlError' => $curlError !== '' ? 'Transport error' : null,
    'deploymentCommitSha' => getenv('VERCEL_GIT_COMMIT_SHA') ?: 'unavailable'
], JSON_UNESCAPED_SLASHES);
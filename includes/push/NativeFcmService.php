<?php
// includes/push/NativeFcmService.php - Pure FCM HTTP v1 Notification Dispatcher
// Uses Google OAuth2 Service Account JWT assertion (RFC 7519 RS256 via native openssl).
// Strictly independent from browser Web Push / VAPID. Never exposes private keys or full tokens.

require_once __DIR__ . '/NativeFcmConfig.php';
require_once __DIR__ . '/NativePushTokenRepository.php';

class NativeFcmService {
    private static ?string $cachedAccessToken = null;
    private static int $accessTokenExpiresAt = 0;

    /**
     * Generate or return cached Google OAuth2 Access Token for FCM HTTP v1.
     */
    public static function getAccessToken(): string {
        $now = time();
        if (self::$cachedAccessToken !== null && ($now + 300) < self::$accessTokenExpiresAt) {
            return self::$cachedAccessToken;
        }

        if (!NativeFcmConfig::isConfigured()) {
            throw new \RuntimeException('FCM service account credentials not configured.');
        }

        $clientEmail = NativeFcmConfig::getClientEmail();
        $privateKey = NativeFcmConfig::getPrivateKey();

        // 1. JWT Header
        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT'
        ];

        // 2. JWT Claim Set (RFC 7519)
        $claims = [
            'iss' => $clientEmail,
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'exp' => $now + 3600,
            'iat' => $now
        ];

        $encodedHeader = self::base64UrlEncode(json_encode($header));
        $encodedClaims = self::base64UrlEncode(json_encode($claims));
        $signatureInput = $encodedHeader . '.' . $encodedClaims;

        // 3. Sign using RS256 with Service Account Private Key
        $signature = '';
        $signSuccess = @openssl_sign($signatureInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        if (!$signSuccess) {
            $errorMsg = openssl_error_string() ?: 'OpenSSL signature failure';
            throw new \RuntimeException('Failed to sign Google OAuth2 assertion: ' . $errorMsg);
        }

        $assertion = $signatureInput . '.' . self::base64UrlEncode($signature);

        // 4. Exchange JWT Assertion for Google OAuth2 Bearer Access Token
        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion
            ]),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded'
            ],
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new \RuntimeException('Google OAuth2 token endpoint network error: ' . $curlError);
        }

        $data = json_decode($response ?: '', true);
        if ($httpCode !== 200 || empty($data['access_token'])) {
            $errDetail = $data['error_description'] ?? ($data['error'] ?? 'HTTP ' . $httpCode);
            throw new \RuntimeException('Google OAuth2 token request failed: ' . $errDetail);
        }

        self::$cachedAccessToken = $data['access_token'];
        self::$accessTokenExpiresAt = $now + (int)($data['expires_in'] ?? 3600);

        return self::$cachedAccessToken;
    }

    /**
     * Send notification to a single FCM device registration token.
     *
     * @param string $fcmToken Valid FCM registration token
     * @param array $payload Notification data: ['id' => ..., 'title' => ..., 'body' => ..., 'url' => ..., 'type' => ...]
     * @param string $userId Optional associated user ID for logging
     * @return array Standard result: [ 'accepted' => bool, 'statusCode' => int, 'messageId' => string, 'reason' => string ]
     */
    public static function sendToToken(string $fcmToken, array $payload, string $userId = ''): array {
        $fcmToken = trim($fcmToken);
        if (!NativePushTokenRepository::isValidToken($fcmToken)) {
            return [
                'accepted' => false,
                'statusCode' => 400,
                'messageId' => '',
                'reason' => 'Invalid FCM registration token format.'
            ];
        }

        try {
            $accessToken = self::getAccessToken();
            $projectId = NativeFcmConfig::getProjectId();
        } catch (\Throwable $e) {
            return [
                'accepted' => false,
                'statusCode' => 500,
                'messageId' => '',
                'reason' => 'FCM Authentication Error: ' . $e->getMessage()
            ];
        }

        $notificationId = (string)($payload['id'] ?? ('mentry_' . bin2hex(random_bytes(6))));
        $title = (string)($payload['title'] ?? 'Mentry Solutions');
        $body = (string)($payload['body'] ?? ($payload['message'] ?? 'You have a new update.'));
        $url = (string)($payload['url'] ?? ($payload['link'] ?? '/trainer/notifications.php'));
        $type = (string)($payload['type'] ?? 'GENERAL');

        // FCM HTTP v1 formatted payload
        $fcmPayload = [
            'message' => [
                'token' => $fcmToken,
                'notification' => [
                    'title' => $title,
                    'body' => $body
                ],
                'data' => [
                    'notificationId' => $notificationId,
                    'type' => $type,
                    'url' => $url,
                    'click_action' => 'OPEN_MENTRY_URL'
                ],
                'android' => [
                    'priority' => 'high',
                    'notification' => [
                        'channel_id' => 'mentry_alerts',
                        'default_sound' => true,
                        'notification_priority' => 'PRIORITY_HIGH',
                        'click_action' => 'OPEN_MENTRY_URL'
                    ]
                ]
            ]
        ];

        $apiUrl = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($fcmPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json; UTF-8'
            ],
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $rawResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $tokenHash = hash('sha256', $fcmToken);

        if ($curlError) {
            $reason = 'Transport connection error: ' . $curlError;
            NativePushTokenRepository::recordFailure($fcmToken, $reason, false);
            self::recordLog($userId, $tokenHash, 500, '', $reason, false);
            return [
                'accepted' => false,
                'statusCode' => 500,
                'messageId' => '',
                'reason' => $reason
            ];
        }

        $resJson = json_decode($rawResponse ?: '', true);
        $accepted = ($httpCode === 200 && !empty($resJson['name']));
        $messageId = $resJson['name'] ?? '';
        $errorCode = $resJson['error']['status'] ?? ($resJson['error']['message'] ?? '');
        $reason = $accepted ? 'Accepted by FCM' : ($errorCode ?: 'Rejected with HTTP ' . $httpCode);

        // Status classification according to FCM HTTP v1 standards
        if ($accepted) {
            NativePushTokenRepository::recordSuccess($fcmToken);
        } else {
            // UNREGISTERED or 404 indicates token is expired / app uninstalled
            $isUnregistered = ($httpCode === 404 || strpos($errorCode, 'UNREGISTERED') !== false || strpos($errorCode, 'NOT_FOUND') !== false);
            $isInvalid = ($httpCode === 400 && strpos($errorCode, 'INVALID_ARGUMENT') !== false);

            if ($isUnregistered || $isInvalid) {
                NativePushTokenRepository::deactivateToken($fcmToken, $errorCode ?: 'FCM_' . $httpCode);
            } else {
                NativePushTokenRepository::recordFailure($fcmToken, $reason, false);
            }
        }

        self::recordLog($userId, $tokenHash, $httpCode, $messageId, $reason, $accepted);

        return [
            'accepted' => $accepted,
            'statusCode' => $httpCode,
            'messageId' => $messageId,
            'reason' => $reason,
            'tokenHash' => substr($tokenHash, 0, 16)
        ];
    }

    /**
     * Send notification to all active Android FCM tokens for a user.
     *
     * @param string $userId
     * @param array $payload
     * @return array [ 'sent' => bool, 'acceptedCount' => int, 'failedCount' => int, 'tokenCount' => int, 'results' => array ]
     */
    public static function sendToUser(string $userId, array $payload): array {
        $tokens = NativePushTokenRepository::findActiveForUser($userId);
        if (empty($tokens)) {
            return [
                'sent' => false,
                'acceptedCount' => 0,
                'failedCount' => 0,
                'tokenCount' => 0,
                'results' => []
            ];
        }

        $acceptedCount = 0;
        $failedCount = 0;
        $results = [];

        foreach ($tokens as $tDoc) {
            $fcmToken = is_array($tDoc) ? ($tDoc['fcmToken'] ?? '') : ($tDoc->fcmToken ?? '');
            if (empty($fcmToken)) continue;

            $res = self::sendToToken($fcmToken, $payload, $userId);
            if ($res['accepted']) {
                $acceptedCount++;
            } else {
                $failedCount++;
            }
            $results[] = $res;
        }

        return [
            'sent' => ($acceptedCount > 0),
            'acceptedCount' => $acceptedCount,
            'failedCount' => $failedCount,
            'tokenCount' => count($tokens),
            'results' => $results
        ];
    }

    /**
     * Safe server-side diagnostic logging (NEVER logs full tokens or secrets).
     */
    private static function recordLog(string $userId, string $tokenHash, int $httpStatus, string $messageId, string $reason, bool $accepted): void {
        try {
            if (function_exists('getCollection')) {
                $logCol = getCollection("NativePushTransportLog");
                if ($logCol) {
                    $logCol->insertOne([
                        'timestamp' => new \MongoDB\BSON\UTCDateTime(),
                        'isoTimestamp' => date('c'),
                        'userId' => (string)$userId,
                        'tokenHash' => substr($tokenHash, 0, 16),
                        'tokenFullHash' => $tokenHash,
                        'httpStatus' => $httpStatus,
                        'messageId' => $messageId,
                        'reason' => cleanString($reason, 200),
                        'accepted' => $accepted
                    ]);
                }
            }
        } catch (\Throwable $e) {}
    }

    private static function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}

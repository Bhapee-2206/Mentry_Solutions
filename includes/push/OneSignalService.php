<?php
// includes/push/OneSignalService.php - Pure OneSignal REST API Client
// Dispatches notifications to trainers via OneSignal external_id.
// Redundant FCM, APNs, background wake-up, battery optimization handled automatically.

class OneSignalService {
    private const API_URL = 'https://api.onesignal.com/notifications';
    private const DEFAULT_APP_ID = 'e2443de9-128c-4e5f-a964-03260aa8c627';

    public static function getAppId(): string {
        $env = getenv('ONESIGNAL_APP_ID') ?: ($_ENV['ONESIGNAL_APP_ID'] ?? ($_SERVER['ONESIGNAL_APP_ID'] ?? ''));
        if (empty($env)) {
            // Search all env vars
            foreach (array_merge($_SERVER, $_ENV) as $k => $v) {
                if (stripos($k, 'ONESIGNAL') !== false && stripos($k, 'APP_ID') !== false && !empty($v)) {
                    $env = $v;
                    break;
                }
            }
        }
        return !empty($env) ? trim((string)$env) : self::DEFAULT_APP_ID;
    }

    public static function getApiKeySource(): string {
        $candidates = ['ONESIGNAL_REST_API_KEY', 'ONESIGNAL_API_KEY', 'ONESIGNAL_KEY', 'ONESIGNAL_SECRET'];
        foreach ($candidates as $c) {
            $val = getenv($c) ?: ($_ENV[$c] ?? ($_SERVER[$c] ?? ''));
            if (!empty($val)) return $c;
        }
        foreach (array_merge($_SERVER, $_ENV) as $k => $v) {
            if (stripos($k, 'ONESIGNAL') !== false && (stripos($k, 'API_KEY') !== false || stripos($k, 'KEY') !== false) && stripos($k, 'APP_ID') === false && !empty($v)) {
                return (string)$k;
            }
        }
        return 'NONE';
    }

    public static function getApiKey(): string {
        $appId = self::getAppId();
        // Direct checks for common names
        $candidates = ['ONESIGNAL_REST_API_KEY', 'ONESIGNAL_API_KEY', 'ONESIGNAL_KEY', 'ONESIGNAL_SECRET'];
        foreach ($candidates as $c) {
            $val = getenv($c) ?: ($_ENV[$c] ?? ($_SERVER[$c] ?? ''));
            if (!empty($val)) {
                $cleaned = trim(trim((string)$val), "\"' \t\n\r");
                // CRITICAL SAFETY CHECK: NEVER use the App ID as the REST API key
                if ($cleaned !== $appId && strlen($cleaned) > 10) {
                    return $cleaned;
                }
            }
        }
        // Fallback: scan all environment variables for ONESIGNAL + API_KEY / KEY
        foreach (array_merge($_SERVER, $_ENV) as $k => $v) {
            if (stripos($k, 'ONESIGNAL') !== false && (stripos($k, 'API_KEY') !== false || stripos($k, 'KEY') !== false) && stripos($k, 'APP_ID') === false && !empty($v)) {
                $cleaned = trim(trim((string)$v), "\"' \t\n\r");
                if ($cleaned !== $appId && strlen($cleaned) > 10) {
                    return $cleaned;
                }
            }
        }
        return '';
    }

    public static function isConfigured(): bool {
        return !empty(self::getAppId()) && !empty(self::getApiKey());
    }

    /**
     * Send push notification to a specific trainer/user by their Mentry User ID (external_id).
     *
     * @param string $userId Mentry User._id
     * @param array $payload ['id' => ..., 'title' => ..., 'body' => ..., 'url' => ..., 'type' => ...]
     * @param string $urgency 'high' | 'normal'
     * @return array
     */
    public static function sendToUser(string $userId, array $payload, string $urgency = 'high'): array {
        return self::sendToUsers([(string)$userId], $payload, $urgency);
    }

    /**
     * Send push notification to multiple trainers/users by their Mentry User IDs.
     *
     * @param array $userIds List of Mentry User._ids
     * @param array $payload
     * @param string $urgency
     * @return array
     */
    public static function sendToUsers(array $userIds, array $payload, string $urgency = 'high'): array {
        $appId = self::getAppId();
        $apiKey = self::getApiKey();

        if (empty($apiKey)) {
            return [
                'sent' => false,
                'accepted' => false,
                'statusCode' => 500,
                'reason' => 'ONESIGNAL_REST_API_KEY is not configured in server environment.',
                'recipientCount' => 0,
                'results' => []
            ];
        }

        // Clean and expand user IDs to include both User ID and Trainer ID aliases
        $cleanIds = array_values(array_unique(array_filter(array_map('strval', $userIds))));
        if (function_exists('getCollection')) {
            try {
                $trCol = getCollection("Trainer");
                if ($trCol) {
                    $expanded = [];
                    foreach ($cleanIds as $id) {
                        $expanded[] = $id;
                        $matches = [$id];
                        try { $matches[] = new \MongoDB\BSON\ObjectId($id); } catch (\Throwable $e) {}
                        $t = $trCol->findOne([
                            '$or' => [
                                ['_id' => ['$in' => $matches]],
                                ['userId' => ['$in' => $matches]],
                                ['user_id' => ['$in' => $matches]]
                            ]
                        ]);
                        if ($t) {
                            if (!empty($t['userId'])) $expanded[] = (string)$t['userId'];
                            $expanded[] = (string)$t['_id'];
                        }
                    }
                    $cleanIds = array_values(array_unique(array_filter(array_map('strval', $expanded))));
                }
            } catch (\Throwable $e) {}
        }
        if (empty($cleanIds)) {
            return [
                'sent' => false,
                'accepted' => false,
                'statusCode' => 400,
                'reason' => 'No valid user IDs specified for OneSignal dispatch.',
                'recipientCount' => 0,
                'results' => []
            ];
        }

        $title = !empty($payload['title']) ? (string)$payload['title'] : 'Mentry';
        $body = !empty($payload['body']) ? (string)$payload['body'] : 'You have a new update.';
        $url = !empty($payload['url']) ? (string)$payload['url'] : '/trainer/notifications.php';
        $notifId = !empty($payload['id']) ? (string)$payload['id'] : ('onesignal_' . bin2hex(random_bytes(6)));

        // Absolute URL for browser actions
        $origin = 'https://mentry-solutions.vercel.app';
        if (!empty($_SERVER['HTTP_HOST'])) {
            $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
            $origin = $proto . $_SERVER['HTTP_HOST'];
        }
        $fullUrl = str_starts_with($url, 'http') ? $url : ($origin . '/' . ltrim($url, '/'));
        $iconUrl = $origin . '/public/push-icon.png';

        // Target by OneSignal External ID (v16 uses include_aliases)
        $bodyData = [
            'app_id' => $appId,
            'include_aliases' => [
                'external_id' => $cleanIds
            ],
            'target_channel' => 'push',
            'headings' => [
                'en' => $title
            ],
            'contents' => [
                'en' => $body
            ],
            'url' => $fullUrl,
            'web_url' => $fullUrl,
            'chrome_web_icon' => $iconUrl,
            'chrome_web_badge' => $iconUrl,
            'priority' => 10, // Max priority for immediate delivery
            'ttl' => 86400, // 24 hours
            'data' => [
                'id' => $notifId,
                'type' => $payload['type'] ?? 'GENERAL',
                'url' => $url,
                'mentryNotifId' => $notifId
            ]
        ];

        return self::executePost($bodyData, $apiKey);
    }

    /**
     * Send broadcast notification to all subscribed devices
     */
    public static function sendBroadcast(array $payload, string $urgency = 'high'): array {
        $appId = self::getAppId();
        $apiKey = self::getApiKey();

        if (empty($apiKey)) {
            return ['sent' => false, 'reason' => 'ONESIGNAL_REST_API_KEY missing'];
        }

        $title = $payload['title'] ?? 'Mentry';
        $body = $payload['body'] ?? 'Broadcast alert';
        $url = $payload['url'] ?? '/';

        $bodyData = [
            'app_id' => $appId,
            'included_segments' => ['Total Subscriptions'],
            'target_channel' => 'push',
            'headings' => ['en' => $title],
            'contents' => ['en' => $body],
            'url' => $url,
            'priority' => 10,
            'ttl' => 86400
        ];

        return self::executePost($bodyData, $apiKey);
    }

    /**
     * Diagnostic credential verifier: queries https://api.onesignal.com/apps/{app_id}
     * Returns exact app status, name, and valid auth prefix.
     */
    public static function verifyCredentials(): array {
        $appId = self::getAppId();
        $apiKey = self::getApiKey();

        if (empty($apiKey)) {
            return ['valid' => false, 'error' => 'OneSignal REST API Key is not set.'];
        }

        $prefixes = ['Key ', 'Bearer ', 'Basic '];
        $lastResult = null;

        foreach ($prefixes as $p) {
            $ch = curl_init('https://api.onesignal.com/notifications?app_id=' . urlencode($appId) . '&limit=1');
            curl_setopt_array($ch, [
                CURLOPT_HTTPGET => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: ' . $p . $apiKey
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr = curl_error($ch);
            curl_close($ch);

            $decoded = json_decode($response ?: '', true);

            if ($httpCode >= 200 && $httpCode < 300) {
                return [
                    'valid' => true,
                    'authPrefix' => trim($p),
                    'endpoint' => 'GET /notifications',
                    'appId' => $appId,
                    'httpCode' => $httpCode,
                    'notificationsCount' => $decoded['total_count'] ?? 0,
                    'raw' => $decoded
                ];
            }

            $lastResult = [
                'valid' => false,
                'prefix' => trim($p),
                'endpoint' => 'GET /notifications',
                'httpCode' => $httpCode,
                'curlErr' => $curlErr,
                'raw' => $decoded ?: $response
            ];
        }

        return $lastResult ?: ['valid' => false, 'error' => 'Unable to verify credentials.'];
    }

    /**
     * Internal cURL POST executor to OneSignal REST API
     * Automatically attempts standard auth prefixes (Key, Bearer, Basic) if 401 is encountered.
     */
    private static function executePost(array $data, string $apiKey): array {
        $apiKey = trim(trim($apiKey), "\"' \t\n\r");
        $jsonPayload = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // Try standard OneSignal auth prefixes
        $prefixes = ['Key ', 'Bearer ', 'Basic '];
        $lastResponse = null;
        $lastHttpCode = 0;
        $lastCurlErr = '';

        foreach ($prefixes as $prefix) {
            $ch = curl_init(self::API_URL);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $jsonPayload,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json; charset=utf-8',
                    'Authorization: ' . $prefix . $apiKey
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_SSL_VERIFYPEER => true
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr = curl_error($ch);
            curl_close($ch);

            $lastResponse = $response;
            $lastHttpCode = $httpCode;
            $lastCurlErr = $curlErr;

            // If success or non-401 error (e.g. 400 Bad Request, 200 OK), no need to try other auth prefixes
            if ($httpCode !== 401) {
                break;
            }
        }

        if ($lastResponse === false) {
            return [
                'sent' => false,
                'accepted' => false,
                'statusCode' => 500,
                'reason' => 'cURL error connecting to OneSignal: ' . $lastCurlErr,
                'rawResponse' => null
            ];
        }

        $decoded = json_decode($lastResponse, true);
        $isOk = ($lastHttpCode >= 200 && $lastHttpCode < 300);
        $recipients = $decoded['recipients'] ?? 0;
        $oneSignalId = $decoded['id'] ?? null;
        $errors = $decoded['errors'] ?? null;

        $errorMsg = '';
        if (!empty($errors)) {
            $errorMsg = is_array($errors) ? implode('; ', array_map(fn($k, $v) => "$k: " . (is_array($v) ? json_encode($v) : $v), array_keys($errors), $errors)) : (string)$errors;
        }

        return [
            'sent' => $isOk && empty($errors),
            'accepted' => $isOk,
            'statusCode' => $lastHttpCode,
            'oneSignalId' => $oneSignalId,
            'recipients' => $recipients,
            'reason' => $isOk ? ("Accepted by OneSignal (recipients: $recipients)") : ("OneSignal error HTTP $lastHttpCode: $errorMsg"),
            'errors' => $errors,
            'rawResponse' => $decoded
        ];
    }
}

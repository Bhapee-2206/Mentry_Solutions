<?php
// includes/PushNotificationService.php - Native RFC 8291 / RFC 8292 WebPush VAPID Engine for Mentry

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

class PushNotificationService {
    private static $configFile = __DIR__ . '/../config/vapid.json';
    private static $vapidKeys = null;
    private static $opensslConf = null;

    /**
     * Locate or create valid openssl.cnf on Windows/Linux/Vercel
     */
    private static function getOpenSslConf(): ?string {
        if (self::$opensslConf !== null && !empty(self::$opensslConf) && @file_exists(self::$opensslConf)) {
            return self::$opensslConf;
        }

        $paths = [
            getenv('OPENSSL_CONF'),
            'C:/xampp/php/extras/ssl/openssl.cnf',
            'C:/xampp/apache/bin/openssl.cnf',
            '/etc/ssl/openssl.cnf',
            '/usr/lib/ssl/openssl.cnf',
            '/usr/local/ssl/openssl.cnf',
            '/etc/pki/tls/openssl.cnf',
            '/opt/homebrew/etc/openssl@3/openssl.cnf'
        ];

        foreach ($paths as $p) {
            if (!empty($p) && @file_exists($p)) {
                self::$opensslConf = $p;
                @putenv("OPENSSL_CONF={$p}");
                return $p;
            }
        }

        // On serverless/cloud environments (e.g. Vercel, AWS Lambda, Docker) where no openssl.cnf exists:
        // Automatically create a minimal, valid config in the temp directory so openssl_pkey_new never fails.
        // The config MUST include all sections referenced for EC key generation to work.
        try {
            $tempConf = sys_get_temp_dir() . '/mentry_openssl.cnf';
            $minimalConfig = implode("\n", [
                'HOME = .',
                'openssl_conf = openssl_init',
                '',
                '[openssl_init]',
                'providers = provider_sect',
                '',
                '[provider_sect]',
                'default = default_sect',
                '',
                '[default_sect]',
                'activate = 1',
                '',
                '[req]',
                'distinguished_name = req_distinguished_name',
                '',
                '[req_distinguished_name]',
                ''
            ]);
            // Always rewrite to ensure latest config format
            @file_put_contents($tempConf, $minimalConfig);
            if (@file_exists($tempConf)) {
                self::$opensslConf = $tempConf;
                @putenv("OPENSSL_CONF={$tempConf}");
                return $tempConf;
            }
        } catch (\Throwable $e) {}

        self::$opensslConf = '';
        return null;
    }

    /**
     * Base64URL safe encode
     */
    public static function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64URL safe decode
     */
    public static function base64UrlDecode(string $data): string {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($data)) % 4));
    }

    /**
     * Initialize or load persistent VAPID keys
     */
    public static function initKeys(): array {
        if (self::$vapidKeys !== null) {
            return self::$vapidKeys;
        }

        if (file_exists(self::$configFile)) {
            $data = json_decode(file_get_contents(self::$configFile), true);
            if (!empty($data['publicKey']) && !empty($data['privateKey']) && !empty($data['privateKeyPem'])) {
                self::$vapidKeys = $data;
                return self::$vapidKeys;
            }
        }

        // Check environment variables / .env file
        $envPub = getenv('VAPID_PUBLIC_KEY') ?: ($_ENV['VAPID_PUBLIC_KEY'] ?? ($_SERVER['VAPID_PUBLIC_KEY'] ?? ''));
        $envPriv = getenv('VAPID_PRIVATE_KEY') ?: ($_ENV['VAPID_PRIVATE_KEY'] ?? ($_SERVER['VAPID_PRIVATE_KEY'] ?? ''));
        $envSub = getenv('VAPID_SUBJECT') ?: ($_ENV['VAPID_SUBJECT'] ?? ($_SERVER['VAPID_SUBJECT'] ?? 'mailto:support@mentrysolutions.com'));

        if (empty($envPub) || empty($envPriv)) {
            $envPath = __DIR__ . '/../.env';
            if (file_exists($envPath)) {
                $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (str_starts_with($line, '#')) continue;
                    if (str_starts_with($line, 'VAPID_PUBLIC_KEY=')) {
                        $envPub = trim(trim(substr($line, strlen('VAPID_PUBLIC_KEY='))), '"\'');
                    }
                    if (str_starts_with($line, 'VAPID_PRIVATE_KEY=')) {
                        $envPriv = trim(trim(substr($line, strlen('VAPID_PRIVATE_KEY='))), '"\'');
                    }
                    if (str_starts_with($line, 'VAPID_SUBJECT=')) {
                        $envSub = trim(trim(substr($line, strlen('VAPID_SUBJECT='))), '"\'');
                    }
                }
            }
        }

        if (!empty($envPub) && !empty($envPriv)) {
            $keys = [
                'publicKey' => $envPub,
                'privateKey' => $envPriv,
                'subject' => $envSub ?: 'mailto:support@mentrysolutions.com',
                'createdAt' => date('c')
            ];
            // If private key PEM can be exported or generated
            @file_put_contents(self::$configFile, json_encode($keys, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            self::$vapidKeys = $keys;
            return self::$vapidKeys;
        }

        // Generate new EC P-256 keypair
        $cnf = self::getOpenSslConf();
        $args = [
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ];
        if (!empty($cnf)) {
            $args['config'] = $cnf;
        }

        $res = @openssl_pkey_new($args);
        if (!$res) {
            // Retry without config
            $res = @openssl_pkey_new([
                'curve_name' => 'prime256v1',
                'private_key_type' => OPENSSL_KEYTYPE_EC,
            ]);
        }
        if (!$res) {
            $sslErr = openssl_error_string() ?: 'Unknown';
            error_log("PushNotificationService: VAPID key generation failed: {$sslErr}");
            throw new Exception("Notification system temporarily unavailable. Please try again later.");
        }

        $details = openssl_pkey_get_details($res);
        if (!$details || empty($details['ec'])) {
            error_log("PushNotificationService: openssl_pkey_get_details failed for VAPID key");
            throw new Exception("Notification system temporarily unavailable. Please try again later.");
        }
        $x = str_pad($details['ec']['x'], 32, "\x00", STR_PAD_LEFT);
        $y = str_pad($details['ec']['y'], 32, "\x00", STR_PAD_LEFT);
        $d = str_pad($details['ec']['d'], 32, "\x00", STR_PAD_LEFT);

        $pubBinary = "\x04" . $x . $y;
        $publicKey = self::base64UrlEncode($pubBinary);
        $privateKey = self::base64UrlEncode($d);

        openssl_pkey_export($res, $privPem, null, !empty($cnf) ? ['config' => $cnf] : null);

        $keys = [
            'publicKey' => $publicKey,
            'privateKey' => $privateKey,
            'privateKeyPem' => $privPem,
            'subject' => 'mailto:support@mentry.in',
            'createdAt' => date('c')
        ];

        @file_put_contents(self::$configFile, json_encode($keys, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        self::$vapidKeys = $keys;
        return self::$vapidKeys;
    }

    /**
     * Get VAPID Public Key for client subscription
     */
    public static function getPublicKey(): string {
        $keys = self::initKeys();
        return $keys['publicKey'];
    }

    /**
     * Convert DER signature from openssl_sign to IEEE P1363 (64 bytes: 32 R + 32 S)
     */
    private static function derToP1363(string $der): string {
        $pos = 2;
        if (ord($der[1]) & 0x80) {
            $pos += (ord($der[1]) & 0x7f);
        }

        // First integer (R)
        $pos++;
        $rLen = ord($der[$pos++]);
        $r = substr($der, $pos, $rLen);
        $pos += $rLen;

        // Second integer (S)
        $pos++;
        $sLen = ord($der[$pos++]);
        $s = substr($der, $pos, $sLen);

        $r = ltrim($r, "\x00");
        $s = ltrim($s, "\x00");

        return str_pad($r, 32, "\x00", STR_PAD_LEFT) . str_pad($s, 32, "\x00", STR_PAD_LEFT);
    }

    /**
     * Convert uncompressed binary EC public key point (65 bytes) to PEM format
     */
    private static function ecPublicKeyToPem(string $binaryPoint): string {
        $derHeader = pack('H*', '3059301306072a8648ce3d020106082a8648ce3d030107034200');
        $der = $derHeader . $binaryPoint;
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    /**
     * Generate VAPID Authorization header for a given push endpoint URL (RFC 8292)
     */
    public static function getVapidAuthHeader(string $endpointUrl): array {
        $keys = self::initKeys();
        $parts = parse_url($endpointUrl);
        $aud = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '');

        $jwtHeader = self::base64UrlEncode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $jwtClaims = self::base64UrlEncode(json_encode([
            'aud' => $aud,
            'exp' => time() + 86400,
            'sub' => $keys['subject'] ?? 'mailto:support@mentry.in'
        ]));
        $jwtUnsigned = $jwtHeader . '.' . $jwtClaims;

        $privKeyObj = openssl_pkey_get_private($keys['privateKeyPem']);
        if (!$privKeyObj) {
            throw new Exception("Invalid VAPID private key");
        }

        $derSig = '';
        openssl_sign($jwtUnsigned, $derSig, $privKeyObj, OPENSSL_ALGO_SHA256);
        $rawSig = self::derToP1363($derSig);
        $jwt = $jwtUnsigned . '.' . self::base64UrlEncode($rawSig);

        return [
            'Authorization' => "vapid t={$jwt}, k={$keys['publicKey']}",
            'Crypto-Key' => "p256ecdsa={$keys['publicKey']}"
        ];
    }

    /**
     * Encrypt a plaintext message for a client subscription using RFC 8291 (aes128gcm)
     */
    public static function encryptPayload(string $payload, string $clientP256dhBase64, string $clientAuthBase64): string {
        $cnf = self::getOpenSslConf();
        $clientPubBinary = self::base64UrlDecode($clientP256dhBase64);
        $clientAuth = self::base64UrlDecode($clientAuthBase64);

        if (strlen($clientPubBinary) !== 65 || ord($clientPubBinary[0]) !== 4) {
            throw new Exception("Invalid client p256dh key: must be 65-byte uncompressed point");
        }
        if (strlen($clientAuth) < 16) {
            throw new Exception("Invalid client auth secret: must be at least 16 bytes");
        }

        // 1. Generate server ephemeral keypair
        $args = [
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ];
        if (!empty($cnf)) $args['config'] = $cnf;

        $serverRes = @openssl_pkey_new($args);
        if (!$serverRes) {
            // Retry without config in case the config itself is causing the issue
            $serverRes = @openssl_pkey_new([
                'curve_name' => 'prime256v1',
                'private_key_type' => OPENSSL_KEYTYPE_EC,
            ]);
        }
        if (!$serverRes) {
            $sslErr = openssl_error_string() ?: 'Unknown OpenSSL error';
            error_log("PushNotificationService: openssl_pkey_new failed for ephemeral key: {$sslErr}");
            throw new Exception("Notification encryption unavailable on this server. Please contact support.");
        }

        $serverDetails = openssl_pkey_get_details($serverRes);
        if (!$serverDetails || empty($serverDetails['ec'])) {
            error_log("PushNotificationService: openssl_pkey_get_details failed for ephemeral key");
            throw new Exception("Notification encryption unavailable on this server. Please contact support.");
        }
        $sx = str_pad($serverDetails['ec']['x'], 32, "\x00", STR_PAD_LEFT);
        $sy = str_pad($serverDetails['ec']['y'], 32, "\x00", STR_PAD_LEFT);
        $serverPubBinary = "\x04" . $sx . $sy;

        // 2. Derive shared secret using ECDH
        $clientPem = self::ecPublicKeyToPem($clientPubBinary);
        $clientKeyObj = openssl_pkey_get_public($clientPem);
        if (!$clientKeyObj) {
            throw new Exception("Could not parse client public key point into PEM");
        }

        $sharedSecret = openssl_pkey_derive($clientKeyObj, $serverRes, 256);
        if (!$sharedSecret) {
            throw new Exception("ECDH key derivation failed: " . openssl_error_string());
        }

        // 3. HKDF key derivation according to RFC 8291
        $salt = random_bytes(16);
        $keyInfo = "WebPush: info\0" . $clientPubBinary . $serverPubBinary;
        $ikm = hash_hkdf('sha256', $sharedSecret, 32, $keyInfo, $clientAuth);

        $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);

        // 4. AES-128-GCM encrypt
        $record = $payload . "\x02"; // padding delimiter
        $tag = '';
        $ciphertext = openssl_encrypt($record, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);

        // 5. Construct RFC 8291 binary body
        $recordSize = pack('N', 4096);
        $idLen = pack('C', strlen($serverPubBinary));
        return $salt . $recordSize . $idLen . $serverPubBinary . $ciphertext . $tag;
    }

    /**
     * Send Web Push notification to a single PushSubscription record
     *
     * @param array $sub PushSubscription document
     * @param array $payloadData Notification structure (title, body, url, id, etc.)
     * @param string $priority 'urgent', 'high', 'normal', 'low'
     * @return array Result of delivery
     */
    public static function sendToSubscription(array $sub, array $payloadData, string $priority = 'high'): array {
        $endpoint = $sub['endpoint'] ?? '';
        $p256dh = $sub['p256dh'] ?? ($sub['keys']['p256dh'] ?? '');
        $auth = $sub['auth'] ?? ($sub['keys']['auth'] ?? '');

        if (empty($endpoint) || empty($p256dh) || empty($auth)) {
            return ['success' => false, 'error' => 'Missing subscription credentials', 'statusCode' => 0];
        }

        // Validate subscription key lengths - p256dh should be ~87 chars base64url, auth ~22 chars
        // Corrupted/test subscriptions with short keys will crash the encryption
        if (strlen($p256dh) < 20 || strlen($auth) < 10) {
            // Auto-deactivate corrupted subscription to prevent future errors
            self::deactivateSubscription($endpoint, 'INVALID_KEYS_TOO_SHORT', true);
            return [
                'success' => false,
                'error' => 'Invalid subscription keys (corrupted or test data). Subscription deactivated.',
                'statusCode' => 0,
                'isDead' => true,
                'endpoint' => $endpoint,
                'device' => $sub['device'] ?? 'Device'
            ];
        }

        if (!empty($sub['isDead']) || (isset($sub['isActive']) && $sub['isActive'] === false)) {
            return [
                'success' => false,
                'error' => 'Subscription marked inactive/dead. Awaiting device renewal.',
                'statusCode' => 410,
                'isDead' => true,
                'endpoint' => $endpoint,
                'device' => $sub['device'] ?? 'Device'
            ];
        }

        try {
            // Standardize notification payload for Service Worker
            $notifId = $payloadData['id'] ?? ($payloadData['notification_id'] ?? ('notif_' . substr(md5(($payloadData['title'] ?? '') . microtime()), 0, 10)));
            $targetUrl = $payloadData['url'] ?? ($payloadData['link'] ?? '/');
            $appBase = function_exists('getAppBaseUrl') ? getAppBaseUrl() : '';
            if (strpos($targetUrl, '/') === 0 && !empty($appBase) && strpos($targetUrl, $appBase) !== 0) {
                $targetUrl = $appBase . $targetUrl;
            }

            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
            $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
            if (isset($_SERVER['SERVER_PORT']) && !in_array((int)$_SERVER['SERVER_PORT'], [80, 443]) && strpos($host, ':') === false) {
                $host .= ':' . $_SERVER['SERVER_PORT'];
            }
            $fullAppUrl = (defined('APP_URL') && APP_URL) ? rtrim(APP_URL, '/') : ($protocol . $host . $appBase);
            $iconUrl = $fullAppUrl . '/public/icon-192.png';
            $badgeUrl = $fullAppUrl . '/public/badge-96.png';

            // DUPLICATE PROTECTION: Ensure the same event is never pushed twice to the same device
            $logCol = getCollection("PushDeliveryLog");
            if ($logCol && !empty($notifId) && !str_starts_with($notifId, 'test_')) {
                $existingLog = $logCol->findOne([
                    'endpoint' => substr($endpoint, 0, 80) . '...',
                    'notificationId' => $notifId,
                    'success' => true
                ]);
                if ($existingLog) {
                    return [
                        'success' => true,
                        'duplicate' => true,
                        'message' => 'Duplicate push suppressed: already delivered to this device.',
                        'endpoint' => $endpoint,
                        'statusCode' => 200,
                        'device' => $sub['device'] ?? 'Device'
                    ];
                }
            }

            $jsonPayload = json_encode([
                'notification_id' => $notifId,
                'id' => $notifId,
                'type' => $payloadData['type'] ?? 'GENERAL',
                'title' => $payloadData['title'] ?? 'Mentry Alert',
                'body' => $payloadData['body'] ?? ($payloadData['message'] ?? ''),
                'url' => $targetUrl,
                'link' => $targetUrl,
                'opportunity_id' => $payloadData['opportunity_id'] ?? ($payloadData['opportunityId'] ?? null),
                'icon' => $iconUrl,
                'badge' => $badgeUrl,
                'tag' => 'mentry-' . $notifId,
                'priority' => $priority,
                'timestamp' => time() * 1000,
                'data' => array_merge([
                    'notification_id' => $notifId,
                    'id' => $notifId,
                    'type' => $payloadData['type'] ?? 'GENERAL',
                    'url' => $targetUrl,
                    'opportunity_id' => $payloadData['opportunity_id'] ?? ($payloadData['opportunityId'] ?? null),
                    'timestamp' => time() * 1000
                ], $payloadData['data'] ?? [])
            ], JSON_UNESCAPED_SLASHES);

            $encryptedBody = self::encryptPayload($jsonPayload, $p256dh, $auth);
            $vapidHeaders = self::getVapidAuthHeader($endpoint);

            $urgency = ($priority === 'urgent' || $priority === 'high') ? 'high' : 'normal';

            // RFC 8292 standard: Authorization header with 'vapid t=..., k=...' MUST NOT include redundant Crypto-Key header
            $headers = [
                'Content-Type: application/octet-stream',
                'Content-Encoding: aes128gcm',
                'TTL: 86400',
                'Urgency: ' . $urgency,
                'Authorization: ' . $vapidHeaders['Authorization']
            ];

            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $encryptedBody,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_TIMEOUT => 12,
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                CURLOPT_TCP_NODELAY => 1,
                CURLOPT_SSL_VERIFYPEER => true
            ]);

            $response = curl_exec($ch);
            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            $isSuccess = ($statusCode >= 200 && $statusCode < 300);

            // Accurate delivery status codes according to W3C Web Push & FCM
            $deliveryStatus = 'PUSH_FAILED';
            if ($isSuccess) {
                $deliveryStatus = 'PUSH_ACCEPTED';
            } elseif ($statusCode === 404 || $statusCode === 410) {
                $deliveryStatus = 'SUBSCRIPTION_EXPIRED';
            } elseif ($statusCode === 401 || $statusCode === 403) {
                $deliveryStatus = 'CREDENTIALS_REJECTED';
            }

            // Only permanently deactivate on HTTP 404 or 410 (expired/revoked subscription)
            // HTTP 400 is a payload/header format issue and must NOT kill the trainer's device subscription
            if ($statusCode === 404 || $statusCode === 410) {
                self::deactivateSubscription($endpoint, 'PUSH_GATEWAY_EXPIRED_HTTP_' . $statusCode, true);
            } elseif ($statusCode === 401 || $statusCode === 403) {
                error_log("PushNotificationService: Gateway rejected VAPID authorization (HTTP {$statusCode}). Please verify VAPID key pair.");
            } elseif ($isSuccess) {
                self::markSubscriptionUsed($endpoint);
            }

            // Log delivery attempt with structured status
            self::logDelivery([
                'endpoint' => substr($endpoint, 0, 80) . '...',
                'userId' => $sub['userId'] ?? null,
                'notificationId' => $notifId,
                'title' => $payloadData['title'] ?? '',
                'statusCode' => $statusCode,
                'deliveryStatus' => $deliveryStatus,
                'success' => $isSuccess,
                'error' => $curlError ?: ($isSuccess ? null : substr((string)$response, 0, 200)),
                'sentAt' => new MongoDB\BSON\UTCDateTime()
            ]);

            return [
                'success' => $isSuccess,
                'statusCode' => $statusCode,
                'deliveryStatus' => $deliveryStatus,
                'response' => (string)$response,
                'error' => $curlError ?: ($isSuccess ? null : trim((string)$response)),
                'endpoint' => $endpoint,
                'isDead' => in_array($statusCode, [400, 401, 403, 404, 410]),
                'device' => $sub['device'] ?? 'Device'
            ];
        } catch (\Throwable $e) {
            error_log("PushNotificationService error: " . $e->getMessage());
            // NEVER expose raw exception messages — they may contain OpenSSL internals, file paths, etc.
            return [
                'success' => false,
                'error' => 'Push delivery failed. The notification was saved and will be visible in-app.',
                'statusCode' => 500,
                'endpoint' => $endpoint,
                'device' => $sub['device'] ?? 'Device'
            ];
        }
    }

    /**
     * Dispatch notification to all active devices of a user
     */
    public static function sendToUser(string $userId, array $payloadData, string $priority = 'high'): array {
        $subCol = getCollection("PushSubscription");
        if (!$subCol) {
            return ['total' => 0, 'sent' => 0, 'failed' => 0];
        }

        $filter = [
            'isActive' => ['$ne' => false],
            'isDead' => ['$ne' => true],
            '$or' => [
                ['userId' => $userId],
                ['userId' => (string)$userId]
            ]
        ];

        $subs = $subCol->find($filter)->toArray();
        $results = ['total' => count($subs), 'sent' => 0, 'failed' => 0, 'deactivated' => 0];

        foreach ($subs as $s) {
            $res = self::sendToSubscription($s, $payloadData, $priority);
            if (!empty($res['success'])) {
                $results['sent']++;
            } else {
                $results['failed']++;
                if (in_array($res['statusCode'] ?? 0, [400, 401, 403, 404, 410])) {
                    $results['deactivated']++;
                }
            }
        }

        return $results;
    }

    /**
     * Dispatch notification to multiple users
     */
    public static function sendToUsers(array $userIds, array $payloadData, string $priority = 'high'): array {
        $results = ['users' => count($userIds), 'totalSent' => 0, 'totalFailed' => 0];
        foreach ($userIds as $uid) {
            $uRes = self::sendToUser((string)$uid, $payloadData, $priority);
            $results['totalSent'] += $uRes['sent'];
            $results['totalFailed'] += $uRes['failed'];
        }
        return $results;
    }

    /**
     * Deactivate dead/expired/rejected subscription
     */
    public static function deactivateSubscription(string $endpoint, string $reason = 'HTTP_EXPIRED', bool $isDead = true): void {
        try {
            $subCol = getCollection("PushSubscription");
            if ($subCol) {
                $subCol->updateOne(
                    ['endpoint' => $endpoint],
                    ['$set' => [
                        'isActive' => false,
                        'isDead' => $isDead,
                        'deactivatedAt' => new MongoDB\BSON\UTCDateTime(),
                        'deactivationReason' => $reason
                    ]]
                );
            }
        } catch (\Throwable $e) {}
    }


    /**
     * Update last used timestamp of successful delivery
     */
    private static function markSubscriptionUsed(string $endpoint): void {
        try {
            $subCol = getCollection("PushSubscription");
            if ($subCol) {
                $subCol->updateOne(
                    ['endpoint' => $endpoint],
                    ['$set' => [
                        'isActive' => true,
                        'lastUsedAt' => new MongoDB\BSON\UTCDateTime()
                    ]]
                );
            }
        } catch (\Throwable $e) {}
    }

    /**
     * Append to delivery audit log
     */
    private static function logDelivery(array $logData): void {
        try {
            $logCol = getCollection("PushDeliveryLog");
            if ($logCol) {
                $logCol->insertOne($logData);
            }
        } catch (\Throwable $e) {}
    }
}

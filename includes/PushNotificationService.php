<?php
// includes/PushNotificationService.php - Native RFC 8291 / RFC 8292 WebPush VAPID Engine for Mentry

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

class PushNotificationService {
    private static $configFile = __DIR__ . '/../config/vapid.json';
    private static $vapidKeys = null;
    private static $opensslConf = null;

    /**
     * Locate valid openssl.cnf on Windows/Linux
     */
    private static function getOpenSslConf(): ?string {
        if (self::$opensslConf !== null) return self::$opensslConf;

        $paths = [
            getenv('OPENSSL_CONF'),
            'C:/xampp/php/extras/ssl/openssl.cnf',
            'C:/xampp/apache/bin/openssl.cnf',
            '/etc/ssl/openssl.cnf',
            '/usr/lib/ssl/openssl.cnf'
        ];

        foreach ($paths as $p) {
            if (!empty($p) && file_exists($p)) {
                self::$opensslConf = $p;
                putenv("OPENSSL_CONF={$p}");
                return $p;
            }
        }
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

        // Generate new EC P-256 keypair
        $cnf = self::getOpenSslConf();
        $args = [
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ];
        if (!empty($cnf)) {
            $args['config'] = $cnf;
        }

        $res = openssl_pkey_new($args);
        if (!$res) {
            throw new Exception("Failed to generate VAPID keys: " . openssl_error_string());
        }

        $details = openssl_pkey_get_details($res);
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

        $serverRes = openssl_pkey_new($args);
        $serverDetails = openssl_pkey_get_details($serverRes);
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
        $context = "WebPush: info\0" . $clientPubBinary . $serverPubBinary;
        $prk = hash_hkdf('sha256', $sharedSecret, 32, "Content-Encoding: auth\0", $clientAuth);
        $ikm = hash_hkdf('sha256', $prk, 32, $context, '');

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

        try {
            // Standardize notification payload for Service Worker
            $notifId = $payloadData['id'] ?? ($payloadData['notification_id'] ?? ('notif_' . substr(md5(($payloadData['title'] ?? '') . microtime()), 0, 10)));
            $targetUrl = $payloadData['url'] ?? ($payloadData['link'] ?? '/');
            $appBase = function_exists('getAppBaseUrl') ? getAppBaseUrl() : '';
            if (strpos($targetUrl, '/') === 0 && !empty($appBase) && strpos($targetUrl, $appBase) !== 0) {
                $targetUrl = $appBase . $targetUrl;
            }

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
                        'statusCode' => 200
                    ];
                }
            }

            $jsonPayload = json_encode([
                'id' => $notifId,
                'title' => $payloadData['title'] ?? 'Mentry Alert',
                'body' => $payloadData['body'] ?? ($payloadData['message'] ?? ''),
                'icon' => $appBase . '/public/icon-192.png',
                'badge' => $appBase . '/public/icon-192.png',
                'url' => $targetUrl,
                'tag' => 'mentry-' . $notifId,
                'type' => $payloadData['type'] ?? 'GENERAL',
                'priority' => $priority,
                'timestamp' => time() * 1000,
                'data' => array_merge([
                    'id' => $notifId,
                    'url' => $targetUrl,
                    'timestamp' => time() * 1000
                ], $payloadData['data'] ?? [])
            ], JSON_UNESCAPED_SLASHES);

            $encryptedBody = self::encryptPayload($jsonPayload, $p256dh, $auth);
            $vapidHeaders = self::getVapidAuthHeader($endpoint);

            $urgency = ($priority === 'urgent' || $priority === 'high') ? 'high' : 'normal';

            $headers = [
                'Content-Type: application/octet-stream',
                'Content-Encoding: aes128gcm',
                'TTL: 86400',
                'Urgency: ' . $urgency,
                'Authorization: ' . $vapidHeaders['Authorization'],
                'Crypto-Key: ' . $vapidHeaders['Crypto-Key']
            ];

            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $encryptedBody,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true
            ]);

            $response = curl_exec($ch);
            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            $isSuccess = ($statusCode >= 200 && $statusCode < 300);

            // Handle dead/expired/rejected subscriptions (400, 401, 403, 404, 410)
            if (in_array($statusCode, [400, 401, 403, 404, 410])) {
                $reason = ($statusCode === 403 || $statusCode === 401)
                    ? 'PUSH_GATEWAY_CREDENTIALS_REJECTED_HTTP_' . $statusCode
                    : 'PUSH_GATEWAY_EXPIRED_HTTP_' . $statusCode;
                self::deactivateSubscription($endpoint, $reason);
            } elseif ($isSuccess) {
                self::markSubscriptionUsed($endpoint);
            }

            // Log delivery attempt
            self::logDelivery([
                'endpoint' => substr($endpoint, 0, 80) . '...',
                'userId' => $sub['userId'] ?? null,
                'notificationId' => $notifId,
                'title' => $payloadData['title'] ?? '',
                'statusCode' => $statusCode,
                'success' => $isSuccess,
                'error' => $curlError ?: ($isSuccess ? null : substr((string)$response, 0, 200)),
                'sentAt' => new MongoDB\BSON\UTCDateTime()
            ]);

            return [
                'success' => $isSuccess,
                'statusCode' => $statusCode,
                'response' => $response,
                'endpoint' => $endpoint
            ];
        } catch (\Throwable $e) {
            error_log("PushNotificationService error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage(), 'statusCode' => 500];
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
                if (in_array($res['statusCode'] ?? 0, [404, 410])) {
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
    public static function deactivateSubscription(string $endpoint, string $reason = 'HTTP_EXPIRED'): void {
        try {
            $subCol = getCollection("PushSubscription");
            if ($subCol) {
                $subCol->updateOne(
                    ['endpoint' => $endpoint],
                    ['$set' => [
                        'isActive' => false,
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

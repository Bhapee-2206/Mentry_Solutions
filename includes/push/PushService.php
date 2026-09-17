<?php
// includes/push/PushService.php - Pure Web Push Transport Engine
// Responsibilities: sendToSubscription(), sendToUser(), sendToUsers() using minishlink/web-push.
// No HTML, no browser JS, no receipt polling, no visibility logic.
// A push accepted by the push gateway remains successful regardless of subsequent database logging.

require_once __DIR__ . '/PushConfig.php';
require_once __DIR__ . '/PushSubscriptionRepository.php';

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

class PushService {
    private static ?WebPush $webPush = null;

    /**
     * Get or initialize WebPush client
     */
    private static function getWebPush(): WebPush {
        if (self::$webPush !== null) {
            return self::$webPush;
        }

        $auth = PushConfig::getVapidAuth();
        $options = [
            'TTL' => 86400, // 24 hours standard TTL
            'urgency' => 'high',
            'topic' => 'mentry-alert',
            'timeout' => 15,
            'client_options' => [
                'verify' => true,
                'timeout' => 15,
                'connect_timeout' => 5
            ]
        ];

        self::$webPush = new WebPush($auth, $options);
        return self::$webPush;
    }

    /**
     * Send Web Push notification to a single subscription document.
     *
     * @param array|object $subDoc Subscription document from PushSubscriptionRepository
     * @param array $payload Notification payload: ['id' => ..., 'title' => ..., 'body' => ..., 'url' => ..., 'type' => ...]
     * @param string $urgency 'high' | 'normal' | 'low'
     * @return array Standard result: [
     *   'accepted' => bool,
     *   'statusCode' => int,
     *   'reason' => string,
     *   'expired' => bool,
     *   'endpoint' => string
     * ]
     */
    public static function sendToSubscription($subDoc, array $payload, string $urgency = 'high'): array {
        $endpoint = '';
        $p256dh = '';
        $auth = '';

        if (is_array($subDoc)) {
            $endpoint = $subDoc['endpoint'] ?? '';
            $p256dh = $subDoc['p256dh'] ?? ($subDoc['keys']['p256dh'] ?? '');
            $auth = $subDoc['auth'] ?? ($subDoc['keys']['auth'] ?? '');
        } elseif (is_object($subDoc)) {
            $endpoint = $subDoc->endpoint ?? '';
            $p256dh = $subDoc->p256dh ?? ($subDoc->keys->p256dh ?? '');
            $auth = $subDoc->auth ?? ($subDoc->keys->auth ?? '');
        }

        if (empty($endpoint) || empty($p256dh) || empty($auth)) {
            return [
                'accepted' => false,
                'statusCode' => 400,
                'reason' => 'Malformed subscription keys or endpoint.',
                'expired' => false,
                'endpoint' => $endpoint
            ];
        }

        $notificationId = (string)($payload['id'] ?? ('mentry_' . bin2hex(random_bytes(6))));
        $title = (string)($payload['title'] ?? 'Mentry Solutions');
        $body = (string)($payload['body'] ?? ($payload['message'] ?? 'You have a new update.'));
        $url = (string)($payload['url'] ?? ($payload['link'] ?? '/'));
        $type = (string)($payload['type'] ?? 'GENERAL');

        // Construct clean payload JSON (RFC 8291 payload)
        $cleanPayload = json_encode([
            'id' => $notificationId,
            'title' => $title,
            'body' => $body,
            'url' => $url,
            'type' => $type
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        try {
            $webPush = self::getWebPush();
            $subscription = Subscription::create([
                'endpoint' => $endpoint,
                'keys' => [
                    'p256dh' => $p256dh,
                    'auth' => $auth
                ]
            ]);

            $options = [
                'urgency' => $urgency,
                'TTL' => 86400
            ];

            $webPush->queueNotification($subscription, $cleanPayload, $options);
            $reports = iterator_to_array($webPush->flush());

            if (empty($reports)) {
                return [
                    'accepted' => false,
                    'statusCode' => 500,
                    'reason' => 'No response report returned by push service.',
                    'expired' => false,
                    'endpoint' => $endpoint
                ];
            }

            $report = $reports[0];
            $response = $report->getResponse();
            $statusCode = $response ? $response->getStatusCode() : 0;
            $isSuccess = $report->isSuccess();
            $reason = $report->getReason() ?: ($isSuccess ? 'Accepted by push service' : 'Rejected');
            $isSubscriptionExpired = $report->isSubscriptionExpired();

            // Treat standard push gateway acceptance codes (200 OK, 201 Created, 202 Accepted) as successful
            $accepted = $isSuccess || ($statusCode >= 200 && $statusCode <= 202);

            // Accurate HTTP classification and repository health update
            if ($accepted) {
                // Guaranteed: A push accepted by push service remains successful even if DB logging fails
                PushSubscriptionRepository::recordSuccess($endpoint, $statusCode ?: 201);
            } else {
                if ($statusCode === 404 || $statusCode === 410 || $isSubscriptionExpired) {
                    // Subscription expired / dead -> deactivate
                    PushSubscriptionRepository::recordFailure($endpoint, $statusCode, 'SUBSCRIPTION_EXPIRED_HTTP_' . $statusCode, true);
                } elseif ($statusCode === 401 || $statusCode === 403) {
                    // VAPID authentication error -> keep active, report config failure
                    PushSubscriptionRepository::recordFailure($endpoint, $statusCode, 'VAPID_AUTH_FAILED_HTTP_' . $statusCode, false);
                } else {
                    // 5xx or transient transport error -> keep active
                    PushSubscriptionRepository::recordFailure($endpoint, $statusCode ?: 500, $reason, false);
                }
            }

            $finalStatusCode = $statusCode ?: ($accepted ? 201 : 500);

            // Step 5: Capture safe push service details
            $safeHeaders = [];
            if ($response) {
                foreach (['date', 'content-length', 'content-type', 'location', 'x-content-type-options'] as $hName) {
                    if ($response->hasHeader($hName)) {
                        $safeHeaders[$hName] = $response->getHeaderLine($hName);
                    }
                }
            }
            $endpointHost = parse_url($endpoint, PHP_URL_HOST) ?: 'unknown-host';
            $audience = (parse_url($endpoint, PHP_URL_SCHEME) ?: 'https') . '://' . $endpointHost;

            $userIdStr = '';
            if (is_array($subDoc)) {
                $userIdStr = (string)($subDoc['userId'] ?? ($subDoc['trainerId'] ?? 'unknown'));
            } elseif (is_object($subDoc)) {
                $userIdStr = (string)($subDoc->userId ?? ($subDoc->trainerId ?? 'unknown'));
            }
            self::recordServerLog($userIdStr, $endpoint, $finalStatusCode, $reason, $accepted, $audience, $urgency, $safeHeaders);

            return [
                'accepted' => $accepted,
                'statusCode' => $finalStatusCode,
                'reason' => $reason,
                'expired' => ($statusCode === 404 || $statusCode === 410 || $isSubscriptionExpired),
                'endpoint' => $endpoint,
                'endpointHost' => $endpointHost,
                'audience' => $audience,
                'urgency' => $urgency,
                'ttl' => 86400,
                'safeHeaders' => $safeHeaders
            ];

        } catch (\Throwable $e) {
            $reason = $e->getMessage();
            PushSubscriptionRepository::recordFailure($endpoint, 500, 'EXCEPTION: ' . $reason, false);

            $endpointHost = parse_url($endpoint, PHP_URL_HOST) ?: 'unknown-host';
            $audience = (parse_url($endpoint, PHP_URL_SCHEME) ?: 'https') . '://' . $endpointHost;

            $userIdStr = '';
            if (is_array($subDoc)) {
                $userIdStr = (string)($subDoc['userId'] ?? ($subDoc['trainerId'] ?? 'unknown'));
            } elseif (is_object($subDoc)) {
                $userIdStr = (string)($subDoc->userId ?? ($subDoc->trainerId ?? 'unknown'));
            }
            self::recordServerLog($userIdStr, $endpoint, 500, 'EXCEPTION: ' . $reason, false, $audience, $urgency, []);

            return [
                'accepted' => false,
                'statusCode' => 500,
                'reason' => 'Network/Transport exception: ' . $reason,
                'expired' => false,
                'endpoint' => $endpoint,
                'endpointHost' => $endpointHost,
                'audience' => $audience,
                'urgency' => $urgency,
                'ttl' => 86400,
                'safeHeaders' => []
            ];
        }
    }

    /**
     * Temporary server-side diagnostic logging (Requirement 12 & Step 5)
     * Records: user ID, subscription ID/hash, push HTTP status, timestamp, endpoint hostname, audience, TTL, urgency.
     * NEVER logs private keys, auth tokens, or p256dh values.
     */
    private static function recordServerLog(string $userId, string $endpoint, int $httpStatus, string $reason, bool $accepted, string $audience = '', string $urgency = 'high', array $safeHeaders = []): void {
        $endpointHost = parse_url($endpoint, PHP_URL_HOST) ?: 'unknown-host';
        $subHash = hash('sha256', $endpoint);
        $timestamp = date('c');

        $logData = [
            'timestamp' => $timestamp,
            'userId' => $userId,
            'subscriptionHash' => substr($subHash, 0, 16),
            'subscriptionFullHash' => $subHash,
            'httpStatus' => $httpStatus,
            'endpointHostname' => $endpointHost,
            'audience' => $audience,
            'urgency' => $urgency,
            'ttl' => 86400,
            'safeHeaders' => $safeHeaders,
            'reason' => $reason,
            'accepted' => $accepted
        ];

        error_log('[Mentry Push Diagnostic Log] ' . json_encode($logData));

        try {
            if (function_exists('getCollection')) {
                $logCol = getCollection("PushTransportLog");
                if ($logCol) {
                    $logCol->insertOne([
                        'timestamp' => new \MongoDB\BSON\UTCDateTime(),
                        'isoTimestamp' => $timestamp,
                        'userId' => $userId,
                        'subscriptionHash' => substr($subHash, 0, 16),
                        'subscriptionFullHash' => $subHash,
                        'httpStatus' => $httpStatus,
                        'endpointHostname' => $endpointHost,
                        'audience' => $audience,
                        'urgency' => $urgency,
                        'ttl' => 86400,
                        'safeHeaders' => $safeHeaders,
                        'reason' => $reason,
                        'accepted' => $accepted
                    ]);
                }
            }
        } catch (\Throwable $e) {}
    }

    /**
     * Send Web Push notification to all active devices of a user.
     *
     * @param string $userId
     * @param array $payload
     * @param string $urgency
     * @return array [
     *   'sent' => bool,
     *   'acceptedCount' => int,
     *   'failedCount' => int,
     *   'subscriptionCount' => int,
     *   'results' => array
     * ]
     */
    public static function sendToUser(string $userId, array $payload, string $urgency = 'high'): array {
        $subscriptions = PushSubscriptionRepository::findActiveForUser($userId);
        if (empty($subscriptions)) {
            return [
                'sent' => false,
                'acceptedCount' => 0,
                'failedCount' => 0,
                'subscriptionCount' => 0,
                'results' => []
            ];
        }

        $acceptedCount = 0;
        $failedCount = 0;
        $results = [];

        foreach ($subscriptions as $sub) {
            $res = self::sendToSubscription($sub, $payload, $urgency);
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
            'subscriptionCount' => count($subscriptions),
            'results' => $results
        ];
    }

    /**
     * Send Web Push notification to multiple users.
     */
    public static function sendToUsers(array $userIds, array $payload, string $urgency = 'high'): array {
        $summary = [
            'totalUsers' => count($userIds),
            'acceptedTotal' => 0,
            'failedTotal' => 0,
            'subscriptionsDispatched' => 0
        ];

        foreach ($userIds as $uid) {
            if (empty($uid)) continue;
            $res = self::sendToUser((string)$uid, $payload, $urgency);
            $summary['acceptedTotal'] += $res['acceptedCount'];
            $summary['failedTotal'] += $res['failedCount'];
            $summary['subscriptionsDispatched'] += $res['subscriptionCount'];
        }

        return $summary;
    }
}

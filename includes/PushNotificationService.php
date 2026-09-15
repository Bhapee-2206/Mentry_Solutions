<?php
// includes/PushNotificationService.php - Clean Adapter delegating to includes/push/ module
// Retained strictly for backwards-compatibility with any lingering call sites.

require_once __DIR__ . '/push/PushConfig.php';
require_once __DIR__ . '/push/PushSubscriptionRepository.php';
require_once __DIR__ . '/push/PushService.php';

class PushNotificationService {
    public static function getPublicKey(): string {
        return PushConfig::getPublicKey();
    }

    public static function initKeys(): array {
        return PushConfig::load();
    }

    public static function isValidPushEndpoint(string $endpoint): bool {
        return PushSubscriptionRepository::isValidEndpoint($endpoint);
    }

    public static function sendToSubscription($subscription, array $payloadData, string $priority = 'high'): array {
        $res = PushService::sendToSubscription($subscription, $payloadData, $priority);
        return [
            'success' => $res['accepted'],
            'accepted' => $res['accepted'],
            'statusCode' => $res['statusCode'],
            'reason' => $res['reason'],
            'deliveryStatus' => $res['accepted'] ? 'accepted' : 'failed',
            'endpoint' => $res['endpoint']
        ];
    }

    public static function sendToUser(string $userId, array $payloadData, string $priority = 'high'): array {
        $res = PushService::sendToUser($userId, $payloadData, $priority);
        return [
            'success' => $res['sent'],
            'acceptedCount' => $res['acceptedCount'],
            'failedCount' => $res['failedCount'],
            'subscriptionCount' => $res['subscriptionCount']
        ];
    }
}

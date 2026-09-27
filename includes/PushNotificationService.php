<?php
// includes/PushNotificationService.php - Retired Push Notification Adapter
// Retained strictly as a safe no-op stub for backwards-compatibility.

class PushNotificationService {
    public static function getPublicKey(): string {
        return '';
    }

    public static function initKeys(): array {
        return [];
    }

    public static function isValidPushEndpoint(string $endpoint): bool {
        return false;
    }

    public static function sendToSubscription($subscription, array $payloadData, string $priority = 'high'): array {
        return [
            'success' => false,
            'accepted' => false,
            'statusCode' => 200,
            'reason' => 'Push notifications retired in favor of In-App notifications.',
            'deliveryStatus' => 'retired',
            'endpoint' => ''
        ];
    }

    public static function sendToUser(string $userId, array $payloadData, string $priority = 'high'): array {
        return [
            'success' => false,
            'sent' => false,
            'acceptedCount' => 0,
            'failedCount' => 0,
            'subscriptionCount' => 0
        ];
    }
}

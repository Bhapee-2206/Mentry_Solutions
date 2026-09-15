<?php
// includes/push/PushConfig.php - Pure VAPID Configuration Loader & Validator
// Responsibilities: Load VAPID credentials, validate them, expose public key safely.
// NEVER expose the private key to browser JavaScript. Never regenerate keys at runtime.

require_once __DIR__ . '/../helpers.php';

use Minishlink\WebPush\VAPID;

class PushConfig {
    private static ?array $config = null;
    private static string $configFile = __DIR__ . '/../../config/vapid.json';

    /**
     * Load and validate VAPID configuration.
     * Sources: Environment variables -> .env file -> config/vapid.json fallback.
     */
    public static function load(): array {
        if (self::$config !== null) {
            return self::$config;
        }

        $pub = getenv('VAPID_PUBLIC_KEY') ?: ($_ENV['VAPID_PUBLIC_KEY'] ?? ($_SERVER['VAPID_PUBLIC_KEY'] ?? ''));
        $priv = getenv('VAPID_PRIVATE_KEY') ?: ($_ENV['VAPID_PRIVATE_KEY'] ?? ($_SERVER['VAPID_PRIVATE_KEY'] ?? ''));
        $sub = getenv('VAPID_SUBJECT') ?: ($_ENV['VAPID_SUBJECT'] ?? ($_SERVER['VAPID_SUBJECT'] ?? ''));

        if (!empty($pub)) $pub = trim($pub, "\"' \t\n\r");
        if (!empty($priv)) $priv = trim($priv, "\"' \t\n\r");
        if (!empty($sub)) $sub = trim($sub, "\"' \t\n\r");

        // Try reading .env if env vars aren't injected into process
        if (empty($pub) || empty($priv)) {
            $envPath = __DIR__ . '/../../.env';
            if (file_exists($envPath)) {
                $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                if (is_array($lines)) {
                    foreach ($lines as $line) {
                        $line = trim($line);
                        if (str_starts_with($line, '#')) continue;
                        if (str_starts_with($line, 'VAPID_PUBLIC_KEY=')) {
                            $pub = trim(trim(substr($line, strlen('VAPID_PUBLIC_KEY='))), "\"' \t\n\r");
                        }
                        if (str_starts_with($line, 'VAPID_PRIVATE_KEY=')) {
                            $priv = trim(trim(substr($line, strlen('VAPID_PRIVATE_KEY='))), "\"' \t\n\r");
                        }
                        if (str_starts_with($line, 'VAPID_SUBJECT=')) {
                            $sub = trim(trim(substr($line, strlen('VAPID_SUBJECT='))), "\"' \t\n\r");
                        }
                    }
                }
            }
        }

        // Fallback: config/vapid.json
        if ((empty($pub) || empty($priv)) && file_exists(self::$configFile)) {
            $fileData = json_decode(file_get_contents(self::$configFile) ?: '', true);
            if (is_array($fileData)) {
                if (empty($pub) && !empty($fileData['publicKey'])) {
                    $pub = trim($fileData['publicKey'], "\"' \t\n\r");
                }
                if (empty($priv) && !empty($fileData['privateKey'])) {
                    $priv = trim($fileData['privateKey'], "\"' \t\n\r");
                }
                if (empty($sub) && !empty($fileData['subject'])) {
                    $sub = trim($fileData['subject'], "\"' \t\n\r");
                }
            }
        }

        if (empty($sub)) {
            $sub = 'mailto:support@mentrysolutions.com';
        }

        if (empty($pub) || empty($priv)) {
            throw new \RuntimeException('VAPID keys not configured. Set VAPID_PUBLIC_KEY and VAPID_PRIVATE_KEY.');
        }

        // Validate VAPID key structure using minishlink/web-push
        $validation = VAPID::validate([
            'subject' => $sub,
            'publicKey' => $pub,
            'privateKey' => $priv
        ]);

        if ($validation !== true && is_array($validation)) {
            throw new \RuntimeException('Invalid VAPID credentials: ' . json_encode($validation));
        }

        self::$config = [
            'publicKey' => $pub,
            'privateKey' => $priv,
            'subject' => $sub
        ];

        return self::$config;
    }

    /**
     * Safely expose the public key for browser clients.
     * NEVER returns the private key.
     */
    public static function getPublicKey(): string {
        $cfg = self::load();
        return $cfg['publicKey'];
    }

    /**
     * Get the full VAPID array required by Minishlink\WebPush\WebPush.
     * Internal server-side use only.
     */
    public static function getVapidAuth(): array {
        $cfg = self::load();
        return [
            'VAPID' => [
                'subject' => $cfg['subject'],
                'publicKey' => $cfg['publicKey'],
                'privateKey' => $cfg['privateKey'],
            ]
        ];
    }
}

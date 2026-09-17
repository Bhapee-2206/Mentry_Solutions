<?php
// includes/push/NativeFcmConfig.php - Secure FCM HTTP v1 Configuration Loader
// Reads Google Service Account credentials from environment variables, .env, or config/firebase_credentials.json.
// NEVER exposes private keys or secret values to client JavaScript.

class NativeFcmConfig {
    private static ?array $credentials = null;
    private static string $configFile = __DIR__ . '/../../config/firebase_credentials.json';

    /**
     * Load Google Service Account credentials for FCM HTTP v1.
     * Looks for:
     * 1. Environment variables (FCM_PROJECT_ID, FCM_CLIENT_EMAIL, FCM_PRIVATE_KEY)
     * 2. Direct JSON string in FCM_SERVICE_ACCOUNT_JSON
     * 3. .env file keys
     * 4. config/firebase_credentials.json file (local fallback)
     */
    public static function load(): array {
        if (self::$credentials !== null) {
            return self::$credentials;
        }

        $projectId = getenv('FCM_PROJECT_ID') ?: ($_ENV['FCM_PROJECT_ID'] ?? ($_SERVER['FCM_PROJECT_ID'] ?? ''));
        $clientEmail = getenv('FCM_CLIENT_EMAIL') ?: ($_ENV['FCM_CLIENT_EMAIL'] ?? ($_SERVER['FCM_CLIENT_EMAIL'] ?? ''));
        $privateKey = getenv('FCM_PRIVATE_KEY') ?: ($_ENV['FCM_PRIVATE_KEY'] ?? ($_SERVER['FCM_PRIVATE_KEY'] ?? ''));

        // Check for full JSON payload in single env var (common in Vercel / serverless deployments)
        $fullJson = getenv('FCM_SERVICE_ACCOUNT_JSON') ?: ($_ENV['FCM_SERVICE_ACCOUNT_JSON'] ?? ($_SERVER['FCM_SERVICE_ACCOUNT_JSON'] ?? ''));
        if (!empty($fullJson)) {
            $rawJson = trim($fullJson);
            if ((str_starts_with($rawJson, "'") && str_ends_with($rawJson, "'")) ||
                (str_starts_with($rawJson, '"') && str_ends_with($rawJson, '"') && !str_contains(substr($rawJson, 1, -1), '"'))) {
                $rawJson = substr($rawJson, 1, -1);
            }
            $parsed = json_decode($rawJson, true);
            if (!is_array($parsed)) {
                $parsed = json_decode(stripslashes($rawJson), true);
            }
            if (!is_array($parsed)) {
                $b64 = base64_decode($rawJson, true);
                if ($b64) {
                    $parsed = json_decode($b64, true);
                }
            }
            if (is_array($parsed)) {
                $projectId = $projectId ?: ($parsed['project_id'] ?? '');
                $clientEmail = $clientEmail ?: ($parsed['client_email'] ?? '');
                $privateKey = $privateKey ?: ($parsed['private_key'] ?? '');
            } else {
                // User may have pasted the private key string into FCM_SERVICE_ACCOUNT_JSON
                if (str_contains($rawJson, 'MIIEv') || str_contains($rawJson, 'PRIVATE KEY')) {
                    $cleanKey = $rawJson;
                    if (!str_contains($cleanKey, 'BEGIN PRIVATE KEY')) {
                        $cleanKey = "-----BEGIN PRIVATE KEY-----\n" . trim($cleanKey) . "\n-----END PRIVATE KEY-----\n";
                    }
                    $privateKey = $privateKey ?: $cleanKey;
                }
            }
        }

        // Fallback to android/app/google-services.json for project_id if not yet set
        if (empty($projectId)) {
            $gsPath = __DIR__ . '/../../android/app/google-services.json';
            if (file_exists($gsPath)) {
                $gs = json_decode(file_get_contents($gsPath) ?: '', true);
                if (!empty($gs['project_info']['project_id'])) {
                    $projectId = (string)$gs['project_info']['project_id'];
                }
            }
        }

        // Fallback to project service account email if not set
        if (empty($clientEmail) && !empty($projectId)) {
            $clientEmail = "firebase-adminsdk-fbsvc@{$projectId}.iam.gserviceaccount.com";
        }

        // Try reading from .env file if env vars are empty
        if (empty($projectId) || empty($clientEmail) || empty($privateKey)) {
            $envPath = __DIR__ . '/../../.env';
            if (file_exists($envPath)) {
                $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                if (is_array($lines)) {
                    foreach ($lines as $line) {
                        $line = trim($line);
                        if (str_starts_with($line, '#')) continue;
                        if (str_starts_with($line, 'FCM_PROJECT_ID=')) {
                            $projectId = trim(trim(substr($line, strlen('FCM_PROJECT_ID='))), "\"' \t\n\r");
                        }
                        if (str_starts_with($line, 'FCM_CLIENT_EMAIL=')) {
                            $clientEmail = trim(trim(substr($line, strlen('FCM_CLIENT_EMAIL='))), "\"' \t\n\r");
                        }
                        if (str_starts_with($line, 'FCM_PRIVATE_KEY=')) {
                            $rawKey = trim(substr($line, strlen('FCM_PRIVATE_KEY=')), "\"' \t\n\r");
                            $privateKey = str_replace('\n', "\n", $rawKey);
                        }
                    }
                }
            }
        }

        // Try reading from config/firebase_credentials.json file
        if ((empty($projectId) || empty($clientEmail) || empty($privateKey)) && file_exists(self::$configFile)) {
            $fileContent = file_get_contents(self::$configFile);
            $parsed = json_decode($fileContent ?: '', true);
            if (is_array($parsed)) {
                $projectId = $projectId ?: ($parsed['project_id'] ?? '');
                $clientEmail = $clientEmail ?: ($parsed['client_email'] ?? '');
                $privateKey = $privateKey ?: ($parsed['private_key'] ?? '');
            }
        }

        if (!empty($privateKey)) {
            // Normalize newline sequences in private key
            $privateKey = str_replace('\n', "\n", $privateKey);
        }

        self::$credentials = [
            'projectId' => trim((string)$projectId),
            'clientEmail' => trim((string)$clientEmail),
            'privateKey' => trim((string)$privateKey)
        ];

        return self::$credentials;
    }

    /**
     * Check if FCM service account configuration is available and valid.
     */
    public static function isConfigured(): bool {
        $cfg = self::load();
        return !empty($cfg['projectId']) && !empty($cfg['clientEmail']) && !empty($cfg['privateKey']);
    }

    /**
     * Get the Google Cloud / Firebase Project ID.
     */
    public static function getProjectId(): string {
        $cfg = self::load();
        return $cfg['projectId'];
    }

    /**
     * Get the service account client email.
     */
    public static function getClientEmail(): string {
        $cfg = self::load();
        return $cfg['clientEmail'];
    }

    /**
     * Get the service account private key (internal server-side use only).
     */
    public static function getPrivateKey(): string {
        $cfg = self::load();
        return $cfg['privateKey'];
    }
}

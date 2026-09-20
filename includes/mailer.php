<?php
// includes/mailer.php - Robust SMTP Client for Google Workspace / Gmail & Resilient Mailer
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

class MentryMailer {
    private $host;
    private $port;
    private $username;
    private $password;
    private $fromName;
    private $fromEmail;
    private $timeout = 15;

    public function __construct() {
        $envFile = __DIR__ . '/../.env';
        $env = [];
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) continue;
                if (strpos($line, '=') !== false) {
                    list($key, $val) = explode('=', $line, 2);
                    $env[trim($key)] = trim(trim($val), '"\'');
                }
            }
        }

        $resolveConfig = function($key, $default = '') use ($env) {
            $val = getenv($key);
            if ($val !== false && trim((string)$val) !== '') return trim((string)$val);
            if (!empty($_ENV[$key]) && trim((string)$_ENV[$key]) !== '') return trim((string)$_ENV[$key]);
            if (!empty($_SERVER[$key]) && trim((string)$_SERVER[$key]) !== '') return trim((string)$_SERVER[$key]);
            if (!empty($env[$key]) && trim((string)$env[$key]) !== '') return trim((string)$env[$key]);
            return $default;
        };

        $this->host = $resolveConfig('SMTP_HOST', 'smtp.gmail.com');
        $portVal = (int)$resolveConfig('SMTP_PORT', '465');
        $this->port = ($portVal > 0) ? $portVal : 465;
        $this->username = $resolveConfig('SMTP_USER', '');
        $passRaw = $resolveConfig('SMTP_PASS', '');
        $this->password = preg_replace('/\s+/', '', $passRaw);
        $this->fromName = $resolveConfig('SMTP_FROM_NAME', 'Mentry Solutions');
        $this->fromEmail = $resolveConfig('SMTP_FROM_EMAIL', $this->username ?: 'support@mentry.solutions');
        $this->timeout = 10; // Generous timeout to allow SSL/TLS handshake on cloud networks
    }

    private function getResponse($socket) {
        $response = "";
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) === " ") break;
        }
        return $response;
    }

    private function sendCommand($socket, $cmd) {
        fputs($socket, $cmd . "\r\n");
        return $this->getResponse($socket);
    }

    /**
     * Encode header value strictly when containing non-ASCII characters.
     * Pure ASCII headers MUST NOT be base64-encoded as doing so triggers Bayesian/evasion spam filters.
     */
    private function encodeHeader(string $str): string {
        $str = trim($str);
        if ($str === '') return '';
        if (preg_match('/^[\x20-\x7E]+$/', $str) && strpos($str, '=?') === false) {
            return $str;
        }
        return '=?UTF-8?B?' . base64_encode($str) . '?=';
    }

    /**
     * Format email address RFC 5322 compliant with clean quoting.
     */
    private function formatAddress(string $name, string $email): string {
        $name = trim($name);
        $email = trim($email);
        if ($name === '') {
            return '<' . $email . '>';
        }
        if (preg_match('/^[\x20-\x7E]+$/', $name)) {
            $clean = str_replace(['"', '\\'], '', $name);
            return '"' . $clean . '" <' . $email . '>';
        }
        return '=?UTF-8?B?' . base64_encode($name) . '?= <' . $email . '>';
    }

    private function attemptSmtpSend(int $port, string $toEmail, string $toName, string $subject, string $htmlContent, string $plainText) {
        $errno = 0;
        $errstr = '';

        $connectHost = $this->host;
        if ($port === 465) {
            $connectHost = 'ssl://' . $this->host;
        }

        $sslOpts = [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false
        ];
        $caCert = __DIR__ . '/cacert.pem';
        if (file_exists($caCert)) {
            $sslOpts['cafile'] = $caCert;
        }
        $context = stream_context_create(['ssl' => $sslOpts]);

        $socket = @stream_socket_client($connectHost . ':' . $port, $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT, $context);

        if (!$socket) {
            throw new Exception("Could not connect to SMTP host ({$this->host}:{$port}): $errstr ($errno)");
        }

        stream_set_timeout($socket, $this->timeout);
        $greeting = $this->getResponse($socket);

        // Send valid client hostname for EHLO (never claim to be Google's mail.gmail.com server)
        $clientHost = !empty($_SERVER['HTTP_HOST']) ? preg_replace('/:[0-9]+$/', '', $_SERVER['HTTP_HOST']) : '';
        if (empty($clientHost) || !preg_match('/^[a-zA-Z0-9\.\-]+$/', $clientHost) || $clientHost === 'localhost' || $clientHost === '127.0.0.1') {
            $clientHost = 'mentry-solutions.vercel.app';
        }
        $this->sendCommand($socket, "EHLO " . $clientHost);

        if ($port === 587) {
            $tlsRes = $this->sendCommand($socket, "STARTTLS");
            if (substr($tlsRes, 0, 3) !== '220') {
                throw new Exception("STARTTLS failed: " . $tlsRes);
            }

            $cryptoMethod = STREAM_CRYPTO_METHOD_TLS_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
                $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
            }

            if (!@stream_socket_enable_crypto($socket, true, $cryptoMethod)) {
                throw new Exception("Failed to enable TLS encryption on SMTP stream.");
            }

            $this->sendCommand($socket, "EHLO " . $clientHost);
        }

        // Authenticate
        $authRes = $this->sendCommand($socket, "AUTH LOGIN");
        if (substr($authRes, 0, 3) !== '334') {
            throw new Exception("AUTH LOGIN rejected: " . $authRes);
        }

        $userRes = $this->sendCommand($socket, base64_encode($this->username));
        if (substr($userRes, 0, 3) !== '334') {
            throw new Exception("Username rejected: " . $userRes);
        }

        $passRes = $this->sendCommand($socket, base64_encode($this->password));
        if (substr($passRes, 0, 3) !== '235') {
            throw new Exception("Password authentication failed: " . $passRes);
        }

        // Mail From & Rcpt To
        $this->sendCommand($socket, "MAIL FROM: <" . $this->fromEmail . ">");
        $rcptRes = $this->sendCommand($socket, "RCPT TO: <" . $toEmail . ">");
        if (substr($rcptRes, 0, 3) !== '250') {
            throw new Exception("Recipient rejected: " . $rcptRes);
        }

        // Send DATA
        $dataRes = $this->sendCommand($socket, "DATA");
        if (substr($dataRes, 0, 3) !== '354') {
            throw new Exception("DATA command rejected: " . $dataRes);
        }

        $senderDomain = substr(strrchr($this->fromEmail, "@"), 1) ?: 'gmail.com';
        $msgId = sprintf("<%s.%s@%s>", bin2hex(random_bytes(10)), time(), $senderDomain);
        $boundary = "mentry_b1_" . md5(uniqid((string)time(), true));
        
        // Clean, standard RFC 5322 headers - strictly NO spam triggers (no Auto-Submitted, X-Priority, etc.)
        $headers = [];
        $headers[] = "Date: " . date('r');
        $headers[] = "To: " . $this->formatAddress($toName, $toEmail);
        $headers[] = "From: " . $this->formatAddress($this->fromName, $this->fromEmail);
        $headers[] = "Reply-To: " . $this->formatAddress($this->fromName, $this->fromEmail);
        $headers[] = "Subject: " . $this->encodeHeader($subject);
        $headers[] = "Message-ID: " . $msgId;
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-Type: multipart/alternative; boundary=\"" . $boundary . "\"";

        $body = "--" . $boundary . "\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
        $body .= quoted_printable_encode($plainText) . "\r\n";

        $body .= "--" . $boundary . "\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
        $body .= quoted_printable_encode($htmlContent) . "\r\n";

        $body .= "--" . $boundary . "--\r\n";

        $message = implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.";
        $sendRes = $this->sendCommand($socket, $message);

        $this->sendCommand($socket, "QUIT");
        @fclose($socket);

        return $sendRes;
    }

    public function send($toEmail, $toName, $subject, $htmlContent, $plainText = '', $meta = []) {
        if (empty($plainText)) {
            $plainText = strip_tags(str_replace(['<br>', '<br/>', '</p>'], "\n", $htmlContent));
        }

        $logEntry = [
            'to' => $toEmail,
            'toName' => $toName,
            'subject' => $subject,
            'plainText' => $plainText,
            'meta' => $meta,
            'timestamp' => new MongoDB\BSON\UTCDateTime(),
            'status' => 'PENDING'
        ];

        // 1. Try Primary Port (465 SSL or configured port)
        $primaryPort = ($this->port === 587) ? 465 : $this->port;
        $fallbackPort = ($primaryPort === 465) ? 587 : 465;
        $lastError = '';

        try {
            $sendRes = $this->attemptSmtpSend($primaryPort, $toEmail, $toName, $subject, $htmlContent, $plainText);
            $logEntry['status'] = 'SENT';
            $logEntry['response'] = $sendRes;
            $logEntry['portUsed'] = $primaryPort;
            $this->logEmail($logEntry);
            return ['success' => true, 'message' => 'Email dispatched successfully.'];
        } catch (\Throwable $e1) {
            $lastError = $e1->getMessage();
            error_log("MentryMailer Port {$primaryPort} error: " . $lastError);

            // 2. Try Fallback Port
            try {
                $sendRes = $this->attemptSmtpSend($fallbackPort, $toEmail, $toName, $subject, $htmlContent, $plainText);
                $logEntry['status'] = 'SENT';
                $logEntry['response'] = $sendRes;
                $logEntry['portUsed'] = $fallbackPort;
                $this->logEmail($logEntry);
                return ['success' => true, 'message' => 'Email dispatched successfully via fallback port.'];
            } catch (\Throwable $e2) {
                $lastError = $e2->getMessage();
                error_log("MentryMailer Port {$fallbackPort} error: " . $lastError);
            }
        }

        // 3. Try native mail() function as third fallback
        if (function_exists('mail')) {
            try {
                $encodedSubject = $this->encodeHeader($subject);
                $fromAddress = $this->formatAddress($this->fromName, $this->fromEmail);
                $headers = "MIME-Version: 1.0\r\n" .
                           "Content-Type: text/html; charset=UTF-8\r\n" .
                           "From: " . $fromAddress . "\r\n" .
                           "Reply-To: " . $fromAddress . "\r\n";
                $mailSent = @mail($toEmail, $encodedSubject, $htmlContent, $headers);
                if ($mailSent) {
                    $logEntry['status'] = 'SENT_NATIVE';
                    $this->logEmail($logEntry);
                    return ['success' => true, 'message' => 'Email dispatched via host mail server.'];
                }
            } catch (\Throwable $e3) {
                error_log("MentryMailer native mail error: " . $e3->getMessage());
            }
        }

        // Both ports failed (likely blocked on serverless host)
        $logEntry['status'] = 'FALLBACK_LOGGED';
        $logEntry['error'] = $lastError;
        $this->logEmail($logEntry);

        return ['success' => false, 'error' => $lastError, 'fallback' => true];
    }

    private function logEmail($data) {
        try {
            $col = getCollection("EmailLog");
            if ($col) {
                $col->insertOne($data);
            }
        } catch (Exception $e) {
            error_log("EmailLog error: " . $e->getMessage());
        }
    }
}

// Global Helper Functions
function sendMentryEmail($toEmail, $toName, $subject, $htmlBody, $plainText = '', $meta = []) {
    $emailType = strtoupper(trim((string)($meta['type'] ?? 'GENERAL')));
    $allowedTypes = [
        'PASSWORD_RESET',
        'ACCOUNT_CONFIRMATION',
        'OPPORTUNITY_MATCH',
        'TRAINER_MATCHED',
        'TRAINER_ASSIGNED',
        'TRAINER_SELECTED',
        'APPLICATION_RECEIVED',
        'APPLICATION_ACCEPTED',
        'APPLICATION_REJECTED',
        'SCHEDULE_CHANGED',
        'PROGRAM_POSTPONED',
        'PROGRAM_REOPENED',
        'PROGRAM_COMPLETED',
        'WORK_ORDER_CONFIRMATION',
        'ADMIN_NOTIFICATION',
        'TEST_EMAIL'
    ];

    if (!in_array($emailType, $allowedTypes, true)) {
        return [
            'success' => false,
            'suppressed' => true,
            'message' => 'Email type not authorized for automated delivery.'
        ];
    }

    // Idempotency check: prevent duplicate sends for identical event key
    $idempotencyKey = $meta['idempotencyKey'] ?? null;
    if (!empty($idempotencyKey)) {
        $logCol = getCollection("EmailLog");
        if ($logCol) {
            $existing = $logCol->findOne([
                'idempotencyKey' => (string)$idempotencyKey,
                'status' => ['$in' => ['SENT', 'SENT_NATIVE']]
            ]);
            if ($existing) {
                return [
                    'success' => true,
                    'deduplicated' => true,
                    'message' => 'Email already delivered for this business event.'
                ];
            }
        }
    }

    $mailer = new MentryMailer();
    return $mailer->send($toEmail, $toName, $subject, $htmlBody, $plainText, $meta);
}

function sendPasswordResetEmail($toEmail, $toName, $code, $resetLink) {
    $baseUrl = function_exists('getAppUrl') ? getAppUrl() : 'https://mentry-solutions.vercel.app';
    $cleanResetLink = preg_replace('#^https?://[^/]+#i', $baseUrl, $resetLink);

    $subject = $code . " is your Mentry verification code";
    $plainText = "Hello " . $toName . ",\n\n" .
                 "Your one-time security verification code is: " . $code . "\n\n" .
                 "Enter this code to verify your account and set a new password on Mentry Solutions.\n\n" .
                 "Security Notice: This verification code is confidential and expires in 30 minutes. Do not share this code with anyone.\n\n" .
                 "Direct Reset Link:\n" .
                 $cleanResetLink . "\n\n" .
                 "If you did not make this request, you can safely ignore this message. Your password will remain unchanged.\n\n" .
                 "---\n" .
                 "Mentry Solutions • Managed Corporate Trainer Network\n" .
                 $baseUrl . "\n" .
                 "Official Support: mentry.training@gmail.com\n";

    $html = '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Mentry Security Verification</title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; margin: 0; padding: 24px; color: #1e293b; }
            .container { max-width: 540px; margin: 0 auto; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; overflow: hidden; }
            .header { background: #070D18; padding: 28px 24px; text-align: center; border-bottom: 3px solid #FE5E04; }
            .content { padding: 32px 28px; }
            .otp-box { background: #fff7ed; border: 1px solid #fdba74; border-radius: 12px; padding: 20px; text-align: center; margin: 24px 0; }
            .otp-code { font-size: 32px; font-weight: 800; letter-spacing: 6px; color: #FE5E04; font-family: monospace; }
            .btn { display: inline-block; background-color: #FE5E04; color: #ffffff !important; font-weight: 700; font-size: 13px; padding: 12px 26px; border-radius: 10px; text-decoration: none; margin: 12px 0; }
            .footer { background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 20px; text-align: center; font-size: 11px; color: #64748b; line-height: 1.5; }
        </style>
    </head>
    <body style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; margin: 0; padding: 24px; color: #1e293b;">
        <table width="100%" border="0" cellspacing="0" cellpadding="0" style="background-color: #f8fafc;">
            <tr>
                <td align="center" style="padding: 24px 0;">
                    <table width="100%" border="0" cellspacing="0" cellpadding="0" style="max-width: 540px; background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; overflow: hidden;">
                        <tr>
                            <td style="background-color: #070D18; padding: 28px 24px; text-align: center; border-bottom: 3px solid #FE5E04;">
                                <div style="font-size: 20px; font-weight: 800; color: #ffffff; letter-spacing: 0.5px;">MENTRY SOLUTIONS</div>
                                <div style="font-size: 11px; color: #94a3b8; margin-top: 4px; font-weight: 600;">Managed Corporate & College Trainer Network</div>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 32px 28px; color: #1e293b;">
                                <h2 style="margin: 0 0 16px 0; font-size: 18px; font-weight: 800; color: #0f172a;">Password Reset Verification</h2>
                                <p style="margin: 0 0 16px 0; font-size: 13px; line-height: 1.6; color: #475569;">Hello ' . htmlspecialchars($toName) . ',</p>
                                <p style="margin: 0 0 20px 0; font-size: 13px; line-height: 1.6; color: #475569;">We received a request to reset the password for your Mentry Solutions account. Use the verification code below to complete this request:</p>
                                
                                <div style="background-color: #fff7ed; border: 1px solid #fdba74; border-radius: 12px; padding: 20px; text-align: center; margin: 24px 0;">
                                    <div style="font-size: 11px; font-weight: 700; color: #9a3412; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px;">One-Time Security Code</div>
                                    <div style="font-size: 32px; font-weight: 800; letter-spacing: 6px; color: #FE5E04; font-family: monospace;">' . htmlspecialchars($code) . '</div>
                                    <div style="font-size: 11px; color: #c2410c; margin-top: 8px;">Valid for 30 minutes • Confidential</div>
                                </div>

                                <div style="text-align: center; margin: 24px 0;">
                                    <a href="' . htmlspecialchars($cleanResetLink) . '" style="display: inline-block; background-color: #FE5E04; color: #ffffff; font-weight: 700; font-size: 13px; padding: 12px 28px; border-radius: 10px; text-decoration: none;">Set New Password &rarr;</a>
                                </div>

                                <p style="margin: 20px 0 0 0; font-size: 11px; line-height: 1.6; color: #94a3b8;">If you did not request this password reset, please disregard this email. Your password will remain unchanged.</p>
                            </td>
                        </tr>
                        <tr>
                            <td style="background-color: #f8fafc; border-top: 1px solid #e2e8f0; padding: 20px 24px; text-align: center; font-size: 11px; color: #64748b; line-height: 1.6;">
                                <strong style="color: #334155;">Mentry Solutions</strong> • Managed Corporate Trainer Network<br>
                                <a href="' . htmlspecialchars($baseUrl) . '" style="color: #64748b; text-decoration: none;">' . htmlspecialchars(preg_replace('#^https?://#', '', $baseUrl)) . '</a> • Official Support: <a href="mailto:mentry.training@gmail.com" style="color: #FE5E04; text-decoration: none;">mentry.training@gmail.com</a>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>
    ';
    return sendMentryEmail($toEmail, $toName, $subject, $html, $plainText, ['code' => $code, 'type' => 'PASSWORD_RESET']);
}

/**
 * Dispatches notification email to a matching candidate for a new training opportunity.
 */
function sendOpportunityMatchNotificationEmail($user, $trainer, $opp, $score = 0) {
    if (empty($user['email'])) return false;

    $baseUrl = function_exists('getAppUrl') ? getAppUrl() : 'https://mentry-solutions.vercel.app';
    $oppId = (string)($opp['_id'] ?? '');
    $oppCode = function_exists('getMentryCode') ? getMentryCode('OPPORTUNITY', $opp) : ($opp['jobId'] ?? $oppId);
    $oppUrl = $baseUrl . '/opportunity-details.php?id=' . urlencode($oppCode);

    $userName = $user['name'] ?? 'Trainer';
    $oppTitle = $opp['title'] ?? 'Technical Training Assignment';
    $location = ($opp['city'] ?? 'India') . ', ' . ($opp['state'] ?? '');
    $dates = !empty($opp['endDate']) ? formatDate($opp['startDate']) . ' – ' . formatDate($opp['endDate']) : formatDate($opp['startDate']);
    $rate = formatINR($opp['dailyRateMin'] ?? 0) . ' – ' . formatINR($opp['dailyRateMax'] ?? 0) . ' / day';
    $duration = function_exists('formatOpportunityDuration') ? formatOpportunityDuration($opp) : ($opp['durationDays'] ?? 5) . ' Days';

    $subject = "Mentry Solutions — New Training Opportunity: " . $oppTitle;

    $plainText = "Hello {$userName},\n\n" .
                 "A new training opportunity matching your profile is available on Mentry Solutions.\n\n" .
                 "Program: {$oppTitle}\n" .
                 "Location: {$location}\n" .
                 "Dates: {$dates} ({$duration})\n" .
                 "Remuneration: {$rate}\n\n" .
                 "View Opportunity & Apply:\n{$oppUrl}\n\n" .
                 "Regards,\nMentry Solutions\nOfficial Trainer Network\n";

    $html = '
    <div style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; padding: 24px; color: #1e293b;">
        <div style="max-width: 560px; margin: 0 auto; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; overflow: hidden;">
            <div style="background-color: #070D18; padding: 24px; text-align: center; border-bottom: 3px solid #FE5E04;">
                <div style="font-size: 20px; font-weight: 800; color: #ffffff;">MENTRY SOLUTIONS</div>
                <div style="font-size: 11px; color: #94a3b8; margin-top: 4px;">Verified Trainer Opportunity Alert</div>
            </div>
            <div style="padding: 28px 24px;">
                <p style="font-size: 14px; margin: 0 0 16px 0;">Hello <strong>' . htmlspecialchars($userName) . '</strong>,</p>
                <p style="font-size: 13px; color: #475569; margin: 0 0 20px 0; line-height: 1.6;">A new training opportunity matching your verified technical profile has just been published. Please review the program schedule and remuneration below:</p>
                
                <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px; margin: 16px 0;">
                    <h3 style="margin: 0 0 12px 0; font-size: 16px; color: #0f172a;">' . htmlspecialchars($oppTitle) . '</h3>
                    <table style="width: 100%; font-size: 12px; line-height: 1.8; color: #334155;">
                        <tr><td style="width: 110px; font-weight: bold; color: #64748b;">LOCATION:</td><td>' . htmlspecialchars($location) . ' (' . htmlspecialchars($opp['mode'] ?? 'OFFLINE') . ')</td></tr>
                        <tr><td style="font-weight: bold; color: #64748b;">DATES:</td><td>' . htmlspecialchars($dates) . ' (' . htmlspecialchars($duration) . ')</td></tr>
                        <tr><td style="font-weight: bold; color: #64748b;">REMUNERATION:</td><td style="font-weight: bold; color: #2563eb;">' . htmlspecialchars($rate) . '</td></tr>
                    </table>
                </div>

                <div style="text-align: center; margin: 24px 0;">
                    <a href="' . htmlspecialchars($oppUrl) . '" style="display: inline-block; background-color: #FE5E04; color: #ffffff; font-weight: 700; font-size: 13px; padding: 12px 28px; border-radius: 10px; text-decoration: none;">View Opportunity & Apply &rarr;</a>
                </div>
            </div>
            <div style="background-color: #f8fafc; border-top: 1px solid #e2e8f0; padding: 16px; text-align: center; font-size: 11px; color: #64748b;">
                Mentry Solutions • Managed Trainer Network • <a href="' . htmlspecialchars($baseUrl) . '" style="color: #64748b;">mentry-solutions.vercel.app</a>
            </div>
        </div>
    </div>';

    $userId = (string)($user['_id'] ?? ($user['id'] ?? ''));
    return sendMentryEmail($user['email'], $userName, $subject, $html, $plainText, [
        'type' => 'OPPORTUNITY_MATCH',
        'opportunityId' => $oppId,
        'userId' => $userId,
        'idempotencyKey' => 'match_email_' . $userId . '_' . $oppId
    ]);
}

/**
 * Generates resolved template data for Trainer Confirmation / Work Order Email.
 * Returns subject, html, plainText, and variables map.
 */
function generateWorkOrderEmailData($opp, $trainer, $user = null, $assignment = [], $isRevision = false, $customPaymentTerms = null, $customEmergency = null): array {
    $baseUrl = function_exists('getAppUrl') ? getAppUrl() : 'https://mentry-solutions.vercel.app';
    $oppId = (string)($opp['_id'] ?? '');
    $oppCode = function_exists('getMentryCode') ? getMentryCode('OPPORTUNITY', $opp) : ($opp['jobId'] ?? $oppId);
    $oppUrl = $baseUrl . '/opportunity-details.php?id=' . urlencode($oppCode);
    $portalUrl = $baseUrl . '/trainer/assignments.php';

    $trainerName = trim($user['name'] ?? ($trainer['name'] ?? 'Faculty Trainer'));
    $trainerEmail = trim($user['email'] ?? ($trainer['email'] ?? ''));
    $courseTitle = trim($opp['title'] ?? 'Technical Training Assignment');
    $collegeName = trim($opp['collegeName'] ?? ($opp['institution'] ?? 'Campus Partner Institution'));
    $location = trim(($opp['city'] ?? 'India') . ', ' . ($opp['state'] ?? ''));
    $state = trim($opp['state'] ?? 'India');
    $mode = strtoupper(trim($opp['mode'] ?? 'OFFLINE'));
    $startDate = formatDate($opp['startDate'] ?? null);
    $endDate = !empty($opp['endDate']) ? formatDate($opp['endDate']) : $startDate;
    $workingDays = function_exists('formatOpportunityDuration') ? formatOpportunityDuration($opp) : ($opp['durationDays'] ?? 5) . ' Working Days';
    $rate = formatINR($assignment['agreedDailyRate'] ?? ($opp['dailyRateMin'] ?? ($opp['dailyRateMax'] ?? 5000))) . ' / day';
    $companyEmail = 'mentry.training@gmail.com';

    $paymentTerms = $customPaymentTerms ?: "1. Professional fees will be disbursed upon successful completion of the training assignment and submission of final attendance/feedback reports.\n2. Invoices must be submitted through the Mentry Solutions Trainer Portal or via email within 3 business days of program conclusion.\n3. Standard TDS and statutory deductions apply in accordance with Government of India regulations.\n4. In the event of unscheduled discontinuation or unexcused absence before completion, remuneration will be evaluated on a prorated basis subject to administrative review.";

    $emergencyContacts = $customEmergency ?: "Operations Desk: +91 98400 12345\nHR / Faculty Coordinator: +91 98400 67890\nEmail: {$companyEmail}";

    $subjectPrefix = $isRevision ? "Revised Work Order Confirmation: " : "New Work Order Confirmation: ";
    $subject = $subjectPrefix . $courseTitle . " | Mentry Solutions";

    // Text version
    $plainText = "Dear {$trainerName},\n\n" .
                 "Greetings from Mentry Solutions.\n\n" .
                 "This is to confirm your engagement as a Freelancer Technical Trainer for the upcoming training assignment.\n\n" .
                 "Please review the work order details below:\n\n" .
                 "### WORK ORDER DETAILS\n" .
                 "Course: {$courseTitle}\n" .
                 "College: {$collegeName}\n" .
                 "Location: {$location}\n" .
                 "Mode: {$mode}\n" .
                 "From Date: {$startDate}\n" .
                 "To Date: {$endDate}\n" .
                 "Working Days: {$workingDays}\n" .
                 "Budget / Day: {$rate}\n\n" .
                 "### SCOPE OF WORK\n" .
                 "- Deliver technical training sessions as per the agreed schedule and curriculum\n" .
                 "- Ensure high-quality content delivery and learner engagement\n" .
                 "- Maintain professionalism throughout the training assignment\n\n" .
                 "### GROOMING & DRESS CODE\n" .
                 "- Trainers are expected to follow a formal and professional dress code during training sessions\n" .
                 "- Proper grooming and presentable attire are mandatory where applicable based on client expectations\n\n" .
                 "### PAYMENT TERMS\n" .
                 "{$paymentTerms}\n\n" .
                 "### GENERAL TERMS\n" .
                 "- Punctuality and adherence to the training schedule are mandatory\n" .
                 "- Confidentiality of client and training materials must be maintained\n" .
                 "- Any deviation from agreed terms should be informed in advance\n\n" .
                 "Kindly reply to this email with your acceptance and confirmation of the above terms.\n\n" .
                 "We look forward to working with you and wish you a successful training assignment.\n\n" .
                 "### ADDITIONAL DETAILS\n" .
                 "The end date of the project may be extended based on college holidays, schedule changes, or other approved requirements.\n" .
                 "Please log in to the Mentry Solutions trainer portal for complete details and to manage your training assignments:\n" .
                 "{$portalUrl}\n\n" .
                 "### EMERGENCY CONTACTS\n" .
                 "{$emergencyContacts}\n\n" .
                 "Regards,\n" .
                 "Mentry Solutions\n" .
                 "Trainer Network & Professional Training Services\n" .
                 "Email: {$companyEmail}\n" .
                 "Website: {$baseUrl}/\n";

    // Responsive, mobile-friendly inline CSS HTML
    $html = '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>' . htmlspecialchars($subject) . '</title>
    </head>
    <body style="margin: 0; padding: 24px; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; background-color: #f1f5f9; color: #1e293b;">
        <table width="100%" border="0" cellspacing="0" cellpadding="0">
            <tr>
                <td align="center">
                    <table width="100%" border="0" cellspacing="0" cellpadding="0" style="max-width: 620px; background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
                        <!-- Header -->
                        <tr>
                            <td style="background-color: #070D18; padding: 28px 24px; text-align: center; border-bottom: 3px solid #FE5E04;">
                                <div style="font-size: 22px; font-weight: 900; color: #ffffff; letter-spacing: 0.5px;">MENTRY SOLUTIONS</div>
                                <div style="font-size: 11px; font-weight: 700; color: #FE5E04; text-transform: uppercase; letter-spacing: 1px; margin-top: 4px;">
                                    ' . ($isRevision ? 'Revised Work Order Confirmation' : 'Work Order Confirmation') . '
                                </div>
                            </td>
                        </tr>

                        <!-- Body Content -->
                        <tr>
                            <td style="padding: 32px 28px; line-height: 1.6; font-size: 13px; color: #334155;">
                                <p style="margin: 0 0 16px 0; font-size: 14px;">Dear <strong>' . htmlspecialchars($trainerName) . '</strong>,</p>
                                <p style="margin: 0 0 20px 0;">Greetings from <strong>Mentry Solutions</strong>.</p>
                                <p style="margin: 0 0 24px 0;">This is to confirm your engagement as a <strong>Freelancer Technical Trainer</strong> for the upcoming training assignment. Please review the official work order details below:</p>

                                <!-- Work Order Details Table -->
                                <table width="100%" border="0" cellspacing="0" cellpadding="0" style="border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; margin-bottom: 24px; font-size: 12px;">
                                    <tr style="background-color: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                                        <td colspan="2" style="padding: 12px 16px; font-weight: 800; color: #0f172a; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #e2e8f0;">
                                            📋 WORK ORDER DETAILS
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 10px 16px; font-weight: 700; color: #64748b; width: 140px; border-bottom: 1px solid #f1f5f9;">Course:</td>
                                        <td style="padding: 10px 16px; font-weight: 800; color: #0f172a; border-bottom: 1px solid #f1f5f9;">' . htmlspecialchars($courseTitle) . '</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 10px 16px; font-weight: 700; color: #64748b; border-bottom: 1px solid #f1f5f9;">College / Partner:</td>
                                        <td style="padding: 10px 16px; color: #1e293b; border-bottom: 1px solid #f1f5f9;">' . htmlspecialchars($collegeName) . '</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 10px 16px; font-weight: 700; color: #64748b; border-bottom: 1px solid #f1f5f9;">Location & Mode:</td>
                                        <td style="padding: 10px 16px; color: #1e293b; border-bottom: 1px solid #f1f5f9;">' . htmlspecialchars($location) . ' (' . htmlspecialchars($mode) . ')</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 10px 16px; font-weight: 700; color: #64748b; border-bottom: 1px solid #f1f5f9;">Program Schedule:</td>
                                        <td style="padding: 10px 16px; font-weight: 700; color: #0f172a; border-bottom: 1px solid #f1f5f9;">' . htmlspecialchars($startDate) . ' to ' . htmlspecialchars($endDate) . ' (' . htmlspecialchars($workingDays) . ')</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 10px 16px; font-weight: 700; color: #64748b;">Budget / Honorarium:</td>
                                        <td style="padding: 10px 16px; font-weight: 800; color: #2563eb; font-size: 14px;">' . htmlspecialchars($rate) . '</td>
                                    </tr>
                                </table>

                                <!-- Scope of Work -->
                                <div style="margin-bottom: 24px;">
                                    <h4 style="margin: 0 0 8px 0; font-size: 13px; font-weight: 800; color: #0f172a; text-transform: uppercase;">Scope of Work</h4>
                                    <ul style="margin: 0; padding-left: 20px; color: #475569;">
                                        <li style="margin-bottom: 4px;">Deliver technical training sessions as per the agreed schedule and curriculum.</li>
                                        <li style="margin-bottom: 4px;">Ensure high-quality content delivery, interactive hands-on coding, and active learner engagement.</li>
                                        <li>Maintain the highest standards of academic rigor and professional conduct throughout the training assignment.</li>
                                    </ul>
                                </div>

                                <!-- Grooming & Dress Code -->
                                <div style="margin-bottom: 24px;">
                                    <h4 style="margin: 0 0 8px 0; font-size: 13px; font-weight: 800; color: #0f172a; text-transform: uppercase;">Grooming & Dress Code</h4>
                                    <ul style="margin: 0; padding-left: 20px; color: #475569;">
                                        <li style="margin-bottom: 4px;">Trainers are expected to follow a formal, neat, and professional dress code during campus training sessions.</li>
                                        <li>Proper grooming and presentable professional attire are mandatory in alignment with institutional expectations.</li>
                                    </ul>
                                </div>

                                <!-- Payment Terms -->
                                <div style="margin-bottom: 24px;">
                                    <h4 style="margin: 0 0 8px 0; font-size: 13px; font-weight: 800; color: #0f172a; text-transform: uppercase;">Payment Terms</h4>
                                    <div style="background-color: #f8fafc; border-left: 3px solid #FE5E04; padding: 12px 16px; font-size: 12px; color: #475569; white-space: pre-line;">' . htmlspecialchars($paymentTerms) . '</div>
                                </div>

                                <!-- General Terms -->
                                <div style="margin-bottom: 24px;">
                                    <h4 style="margin: 0 0 8px 0; font-size: 13px; font-weight: 800; color: #0f172a; text-transform: uppercase;">General Terms</h4>
                                    <ul style="margin: 0; padding-left: 20px; color: #475569;">
                                        <li style="margin-bottom: 4px;">Punctuality and strict adherence to the institution\'s training timetable are mandatory.</li>
                                        <li style="margin-bottom: 4px;">Confidentiality of client, institution, and curriculum materials must be maintained at all times.</li>
                                        <li>Any unforeseen deviation, emergency, or schedule change must be communicated immediately to Mentry Operations.</li>
                                    </ul>
                                </div>

                                <p style="background-color: #eff6ff; border: 1px solid #bfdbfe; border-radius: 10px; padding: 14px; font-size: 12px; color: #1e40af; margin-bottom: 24px;">
                                    <strong>Action Required:</strong> Kindly reply to this email with your formal acceptance and confirmation of the above terms.
                                </p>

                                <!-- Additional Details -->
                                <div style="margin-bottom: 24px; font-size: 12px; color: #64748b;">
                                    <h4 style="margin: 0 0 6px 0; font-size: 12px; font-weight: 800; color: #334155; text-transform: uppercase;">Additional Details</h4>
                                    <p style="margin: 0 0 8px 0;">The end date of the assignment may be extended based on college holidays, institutional schedule adjustments, or other approved requirements.</p>
                                    <p style="margin: 0;">Access your full assignment roster, student batch size, and logistics via the <a href="' . htmlspecialchars($portalUrl) . '" style="color: #FE5E04; font-weight: 700; text-decoration: none;">Mentry Trainer Portal</a>.</p>
                                </div>

                                <!-- Emergency Contacts -->
                                <div style="border-top: 1px solid #e2e8f0; padding-top: 16px; margin-bottom: 24px; font-size: 12px; color: #64748b;">
                                    <strong style="color: #334155; display: block; margin-bottom: 6px;">Emergency & Operations Contacts:</strong>
                                    <div style="white-space: pre-line;">' . htmlspecialchars($emergencyContacts) . '</div>
                                </div>

                                <div style="margin-top: 32px; font-size: 13px;">
                                    Regards,<br>
                                    <strong style="color: #0f172a;">Mentry Solutions</strong><br>
                                    <span style="font-size: 11px; color: #64748b;">Trainer Network & Professional Training Services</span><br>
                                    <span style="font-size: 11px; color: #64748b;">Email: ' . htmlspecialchars($companyEmail) . ' • Website: <a href="' . htmlspecialchars($baseUrl) . '" style="color: #64748b;">' . htmlspecialchars(preg_replace('#^https?://#', '', $baseUrl)) . '</a></span>
                                </div>
                            </td>
                        </tr>

                        <!-- Footer -->
                        <tr>
                            <td style="background-color: #f8fafc; border-top: 1px solid #e2e8f0; padding: 18px 24px; text-align: center; font-size: 11px; color: #64748b; line-height: 1.5;">
                                This is an official engagement document generated by Mentry Solutions.<br>
                                © ' . date('Y') . ' Mentry Solutions. All rights reserved.
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>
    ';

    return [
        'subject' => $subject,
        'html' => $html,
        'plainText' => $plainText,
        'variables' => [
            '[TRAINER_NAME]' => $trainerName,
            '[TRAINER_EMAIL]' => $trainerEmail,
            '[COURSE_TITLE]' => $courseTitle,
            '[COLLEGE_NAME]' => $collegeName,
            '[LOCATION]' => $location,
            '[STATE]' => $state,
            '[MODE]' => $mode,
            '[START_DATE]' => $startDate,
            '[END_DATE]' => $endDate,
            '[WORKING_DAYS]' => $workingDays,
            '[TRAINER_RATE]' => $rate,
            '[OPPORTUNITY_ID]' => $oppCode,
            '[OPPORTUNITY_URL]' => $oppUrl,
            '[PORTAL_URL]' => $portalUrl,
            '[MENTRY_WEBSITE]' => $baseUrl . '/',
            '[COMPANY_EMAIL]' => $companyEmail,
            '[EMERGENCY_CONTACTS]' => $emergencyContacts
        ]
    ];
}

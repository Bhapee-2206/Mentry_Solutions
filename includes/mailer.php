<?php
// includes/mailer.php - Robust SMTP Client for Google Workspace / Gmail & Resilient Mailer
require_once __DIR__ . '/db.php';

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

        $this->host = $env['SMTP_HOST'] ?? getenv('SMTP_HOST') ?: 'smtp.gmail.com';
        $this->port = (int)($env['SMTP_PORT'] ?? getenv('SMTP_PORT') ?: 587);
        $this->username = trim($env['SMTP_USER'] ?? getenv('SMTP_USER') ?: 'mentry.training@gmail.com');
        $this->password = preg_replace('/\s+/', '', $env['SMTP_PASS'] ?? getenv('SMTP_PASS') ?: '');
        $this->fromName = $env['SMTP_FROM_NAME'] ?? getenv('SMTP_FROM_NAME') ?: 'Mentry Solutions';
        $this->fromEmail = trim($env['SMTP_FROM_EMAIL'] ?? getenv('SMTP_FROM_EMAIL') ?: 'mentry.training@gmail.com');
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

        try {
            $errno = 0;
            $errstr = '';

            $connectHost = $this->host;
            if ($this->port === 465) {
                $connectHost = 'ssl://' . $this->host;
            }

            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ]);

            $socket = @stream_socket_client($connectHost . ':' . $this->port, $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT, $context);

            if (!$socket) {
                throw new Exception("Could not connect to SMTP host ({$this->host}:{$this->port}): $errstr ($errno)");
            }

            stream_set_timeout($socket, $this->timeout);
            $greeting = $this->getResponse($socket);

            $hostname = gethostname() ?: 'localhost';
            $this->sendCommand($socket, "EHLO " . $hostname);

            if ($this->port === 587) {
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

                $this->sendCommand($socket, "EHLO " . $hostname);
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

            $msgId = sprintf("<%s.%s@%s>", bin2hex(random_bytes(12)), time(), 'mentry.solutions');
            $boundary = "b1_" . md5(uniqid((string)time(), true));
            
            $headers = [];
            $headers[] = "Message-ID: " . $msgId;
            $headers[] = "Date: " . date('r');
            $headers[] = "From: =?UTF-8?B?" . base64_encode($this->fromName) . "?= <" . $this->fromEmail . ">";
            $headers[] = "Reply-To: <" . $this->fromEmail . ">";
            $headers[] = "To: =?UTF-8?B?" . base64_encode($toName) . "?= <" . $toEmail . ">";
            $headers[] = "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=";
            $headers[] = "MIME-Version: 1.0";
            $headers[] = "Content-Type: multipart/alternative; boundary=\"" . $boundary . "\"";
            $headers[] = "X-Mailer: Mentry-Network/2.0";
            $headers[] = "Auto-Submitted: auto-generated";
            $headers[] = "List-Unsubscribe: <mailto:" . $this->fromEmail . "?subject=Unsubscribe>";

            $body = "--" . $boundary . "\r\n";
            $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $body .= chunk_split(base64_encode($plainText)) . "\r\n";

            $body .= "--" . $boundary . "\r\n";
            $body .= "Content-Type: text/html; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $body .= chunk_split(base64_encode($htmlContent)) . "\r\n";

            $body .= "--" . $boundary . "--\r\n";

            $message = implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.";
            $sendRes = $this->sendCommand($socket, $message);

            $this->sendCommand($socket, "QUIT");
            fclose($socket);

            $logEntry['status'] = 'SENT';
            $logEntry['response'] = $sendRes;
            $this->logEmail($logEntry);

            return ['success' => true, 'message' => 'Email dispatched successfully.'];
        } catch (Exception $e) {
            $logEntry['status'] = 'FALLBACK_LOGGED';
            $logEntry['error'] = $e->getMessage();
            $this->logEmail($logEntry);

            error_log("MentryMailer Exception: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage(), 'fallback' => true];
        }
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
    $mailer = new MentryMailer();
    return $mailer->send($toEmail, $toName, $subject, $htmlBody, $plainText, $meta);
}

function sendPasswordResetEmail($toEmail, $toName, $code, $resetLink) {
    $subject = "Mentry Solutions: Verification Code";
    $html = '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
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
    <body>
        <div class="container">
            <div class="header">
                <h1 style="color: #ffffff; margin: 0; font-size: 20px; font-weight: 800;">Mentry Solutions</h1>
                <p style="color: #FE5E04; margin: 4px 0 0 0; font-size: 11px; font-weight: 700; text-transform: uppercase;">Account Security Notification</p>
            </div>
            <div class="content">
                <h2 style="font-size: 16px; margin-top: 0; color: #0f172a;">Password Verification Code</h2>
                <p style="font-size: 13px; line-height: 1.6; color: #475569;">
                    Hello ' . htmlspecialchars($toName) . ',<br>
                    A password recovery request was received for your registered Mentry account. Please use the verification code below to complete this process:
                </p>
                <div class="otp-box">
                    <div style="font-size: 11px; text-transform: uppercase; font-weight: 700; color: #c2410c; margin-bottom: 6px;">One-Time Verification Code</div>
                    <div class="otp-code">' . htmlspecialchars($code) . '</div>
                    <div style="font-size: 11px; color: #9a3412; margin-top: 6px;">Valid for 30 minutes</div>
                </div>
                <div style="text-align: center; margin: 18px 0;">
                    <a href="' . htmlspecialchars($resetLink) . '" class="btn">Confirm Password Reset</a>
                </div>
                <p style="font-size: 11px; color: #64748b; line-height: 1.5;">
                    If you did not initiate this request, no further action is necessary. Your password remains unchanged.
                </p>
            </div>
            <div class="footer">
                Mentry Solutions • Managed Trainer Network<br>
                Bangalore, Karnataka, India • Contact: <a href="mailto:mentry.training@gmail.com" style="color: #FE5E04; text-decoration: none;">mentry.training@gmail.com</a>
            </div>
        </div>
    </body>
    </html>
    ';
    return sendMentryEmail($toEmail, $toName, $subject, $html, '', ['code' => $code, 'type' => 'PASSWORD_RESET']);
}

function sendOpportunityMatchEmail($toEmail, $toName, $opp) {
    $subject = "Training Assignment Notice: " . ($opp['title'] ?? 'Campus Workshop');
    $domain = htmlspecialchars($opp['domain'] ?? 'Technology');
    $city = htmlspecialchars($opp['city'] ?? 'India');
    $rateMin = number_format($opp['dailyRateMin'] ?? 5000);
    $rateMax = number_format($opp['dailyRateMax'] ?? 7000);
    $oppId = (string)($opp['_id'] ?? '');

    $html = '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <title>Campus Training Schedule Notice</title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; margin: 0; padding: 24px; color: #1e293b; }
            .container { max-width: 560px; margin: 0 auto; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; overflow: hidden; }
            .header { background: #070D18; padding: 26px 24px; text-align: center; border-bottom: 3px solid #FE5E04; }
            .content { padding: 28px 24px; }
            .card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px; margin: 16px 0; }
            .btn { display: inline-block; background-color: #FE5E04; color: #ffffff !important; font-weight: 700; font-size: 13px; padding: 12px 24px; border-radius: 10px; text-decoration: none; }
            .footer { background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 18px; text-align: center; font-size: 11px; color: #64748b; line-height: 1.5; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1 style="color: #ffffff; margin: 0; font-size: 20px; font-weight: 800;">Mentry Solutions</h1>
                <p style="color: #FE5E04; margin: 4px 0 0 0; font-size: 11px; font-weight: 700; text-transform: uppercase;">Faculty Assignment Notification</p>
            </div>
            <div class="content">
                <p style="font-size: 14px; margin-top: 0; color: #334155;">
                    Dear <strong>' . htmlspecialchars($toName) . '</strong>,
                </p>
                <p style="font-size: 13px; color: #475569; line-height: 1.6;">
                    A new academic training requirement matching your subject specialization has been published on the Mentry portal.
                </p>

                <div class="card">
                    <div style="font-size: 11px; font-weight: 700; color: #FE5E04; text-transform: uppercase; margin-bottom: 4px;">' . $domain . ' • ' . htmlspecialchars($opp['mode'] ?? 'OFFLINE') . '</div>
                    <h3 style="margin: 0 0 8px 0; font-size: 15px; color: #0f172a;">' . htmlspecialchars($opp['title']) . '</h3>
                    <div style="font-size: 12px; color: #64748b; margin-bottom: 6px;"><strong>Location:</strong> ' . $city . ' • <strong>Duration:</strong> ' . htmlspecialchars($opp['durationDays'] ?? 5) . ' Days</div>
                    <div style="font-size: 12px; color: #64748b;"><strong>Honorarium Range:</strong> ₹' . $rateMin . ' – ₹' . $rateMax . ' / day</div>
                </div>

                <div style="text-align: center; margin: 20px 0 10px 0;">
                    <a href="http://localhost/opportunity-details.php?id=' . $oppId . '" class="btn">Review Requirement Details</a>
                </div>
            </div>
            <div class="footer">
                You received this notice because your trainer profile is registered for ' . $domain . ' curriculum assignments.<br>
                Mentry Solutions • Managed Trainer Network • <a href="mailto:mentry.training@gmail.com" style="color: #FE5E04; text-decoration: none;">mentry.training@gmail.com</a>
            </div>
        </div>
    </body>
    </html>
    ';
    return sendMentryEmail($toEmail, $toName, $subject, $html, '', ['oppId' => $oppId, 'type' => 'OPPORTUNITY_MATCH']);
}

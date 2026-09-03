<?php
// actions/contact-candidate.php - Send Direct Contact/Invitation Email to Candidate
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mailer.php';

requireAdminOrStaff();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $trainerId = $_POST['trainerId'] ?? '';
    $opportunityId = $_POST['opportunityId'] ?? '';
    $customMessage = trim($_POST['message'] ?? '');

    $trainerCol = getCollection("Trainer");
    $userCol = getCollection("User");
    $oppCol = getCollection("Opportunity");

    $trainer = null;
    $candidateUser = null;
    $opp = null;

    try {
        if (!empty($trainerId)) {
            $trainer = $trainerCol->findOne(['_id' => new MongoDB\BSON\ObjectId($trainerId)]);
            if ($trainer && !empty($trainer['userId'])) {
                $candidateUser = $userCol->findOne(['_id' => new MongoDB\BSON\ObjectId((string)$trainer['userId'])]);
            }
        }
        if (!empty($opportunityId)) {
            $opp = $oppCol->findOne(['_id' => new MongoDB\BSON\ObjectId($opportunityId)]);
        }
    } catch (Exception $e) {}

    if ($candidateUser && !empty($candidateUser['email']) && $opp) {
        $sender = getCurrentUser();
        $senderName = $sender['name'] ?? 'Mentry Operations Team';
        $senderRole = $sender['role'] ?? 'Staff';

        $subject = "Faculty Assignment Invitation: " . $opp['title'];

        $html = '
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="utf-8">
            <title>Faculty Assignment Invitation</title>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; margin: 0; padding: 24px; color: #1e293b; }
                .container { max-width: 560px; margin: 0 auto; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; overflow: hidden; }
                .header { background: #070D18; padding: 26px 24px; text-align: center; border-bottom: 3px solid #FE5E04; }
                .content { padding: 30px 24px; }
                .quote-box { background: #fff7ed; border-left: 4px solid #FE5E04; padding: 14px 18px; border-radius: 8px; margin: 18px 0; color: #431407; font-size: 13px; }
                .job-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px; margin: 18px 0; }
                .btn { display: inline-block; background-color: #FE5E04; color: #ffffff !important; font-weight: 700; font-size: 13px; padding: 12px 24px; border-radius: 10px; text-decoration: none; }
                .footer { background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 18px; text-align: center; font-size: 11px; color: #64748b; line-height: 1.5; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1 style="color: #ffffff; margin: 0; font-size: 20px; font-weight: 800;">Mentry Solutions</h1>
                    <p style="color: #FE5E04; margin: 4px 0 0 0; font-size: 11px; font-weight: 700; text-transform: uppercase;">Faculty Training Delivery</p>
                </div>
                <div class="content">
                    <p style="font-size: 14px; margin-top: 0; color: #1e293b;">
                        Dear <strong>' . htmlspecialchars($candidateUser['name']) . '</strong>,
                    </p>
                    <p style="font-size: 13px; color: #475569; line-height: 1.6;">
                        Our academic operations team has identified your credentials as a direct match for the upcoming college training program:
                    </p>

                    <div class="job-box">
                        <div style="font-size: 11px; font-weight: 700; color: #FE5E04; text-transform: uppercase; margin-bottom: 4px;">' . htmlspecialchars($opp['domain'] ?? 'Technology') . ' • ' . htmlspecialchars($opp['mode'] ?? 'OFFLINE') . '</div>
                        <h3 style="margin: 0 0 6px 0; font-size: 15px; color: #0f172a;">' . htmlspecialchars($opp['title']) . '</h3>
                        <p style="margin: 0 0 6px 0; font-size: 12px; color: #64748b;"><strong>Campus:</strong> ' . htmlspecialchars($opp['city']) . ', ' . htmlspecialchars($opp['state']) . ' • <strong>Duration:</strong> ' . htmlspecialchars($opp['durationDays']) . ' Days</p>
                        <p style="margin: 0; font-size: 12px; color: #64748b;"><strong>Honorarium Range:</strong> ₹' . number_format($opp['dailyRateMin']) . ' – ₹' . number_format($opp['dailyRateMax']) . ' / day</p>
                    </div>

                    ' . (!empty($customMessage) ? '
                    <div style="font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 4px;">Note from ' . htmlspecialchars($senderName) . ' (' . htmlspecialchars($senderRole) . '):</div>
                    <div class="quote-box">
                        ' . nl2br(htmlspecialchars($customMessage)) . '
                    </div>
                    ' : '') . '

                    <div style="text-align: center; margin: 22px 0 10px 0;">
                        <a href="http://localhost/opportunity-details.php?id=' . $opportunityId . '" class="btn">Review Assignment Details</a>
                    </div>
                </div>
                <div class="footer">
                    Sent on behalf of Mentry Operations Team by ' . htmlspecialchars($senderName) . '.<br>
                    Mentry Solutions • Managed Trainer Network • <a href="mailto:mentry.training@gmail.com" style="color: #FE5E04; text-decoration: none;">mentry.training@gmail.com</a>
                </div>
            </div>
        </body>
        </html>
        ';

        sendMentryEmail($candidateUser['email'], $candidateUser['name'], $subject, $html);

        // Also add in-app notification
        $notifCol = getCollection("Notification");
        if ($notifCol) {
            $notifCol->insertOne([
                'userId' => (string)$candidateUser['_id'],
                'trainerId' => $trainerId,
                'opportunityId' => $opportunityId,
                'type' => 'DIRECT_INVITATION',
                'title' => "Direct Invitation: {$opp['title']}",
                'message' => "{$senderName} from Operations has directly reached out to invite you for this assignment.",
                'link' => '/opportunity-details.php?id=' . $opportunityId,
                'read' => false,
                'createdAt' => new MongoDB\BSON\UTCDateTime()
            ]);
        }
    }
}

$redirect = $_SERVER['HTTP_REFERER'] ?? '/admin/opportunities.php';
header("Location: " . $redirect . (strpos($redirect, '?') !== false ? '&contacted=1' : '?contacted=1'));
exit();

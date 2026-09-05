<?php
// includes/notifications.php - Automated Match Notifications & Dispatch Engine
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/matching_engine.php';
require_once __DIR__ . '/mailer.php';

/**
 * Automatically evaluate trainers and send notifications to those matching the new opportunity
 */
function notifyMatchingTrainersForOpportunity($opportunityId) {
    $oppCol = getCollection("Opportunity");
    $notifCol = getCollection("Notification");
    $userCol = getCollection("User");

    if (!$oppCol || !$notifCol) {
        return ['count' => 0, 'trainers' => []];
    }

    try {
        $opp = $oppCol->findOne(['_id' => new MongoDB\BSON\ObjectId($opportunityId)]);
    } catch (Exception $e) {
        return ['count' => 0, 'error' => $e->getMessage()];
    }

    if (!$opp) return ['count' => 0, 'error' => 'Opportunity not found'];

    // Get ranked matching trainers
    $matchedCandidates = MatchingEngine::getRankedCandidatesForOpportunity($opp, 20);

    $notifiedCount = 0;
    $notifiedNames = [];

    foreach ($matchedCandidates as $cand) {
        $score = $cand['score'];
        $trainer = $cand['trainer'];
        $user = $cand['user'];

        // Only notify if match score is >= 50% or domain matches
        if ($score >= 50 || !empty($cand['match']['isDomainMatch'])) {
            $trainerUserId = (string)($trainer['userId'] ?? '');
            $userEmail = $user['email'] ?? '';
            $userName = $user['name'] ?? 'Trainer';

            if (!empty($trainerUserId)) {
                // Check if already notified
                $existingNotif = $notifCol->findOne([
                    'userId' => $trainerUserId,
                    'opportunityId' => (string)$opportunityId
                ]);

                if (!$existingNotif) {
                    $notifCol->insertOne([
                        'userId' => $trainerUserId,
                        'trainerId' => (string)$trainer['_id'],
                        'opportunityId' => (string)$opportunityId,
                        'type' => 'OPPORTUNITY_MATCH',
                        'title' => 'New ' . ($opp['domain'] ?? 'Tech') . ' Match: ' . $opp['title'],
                        'message' => "Your profile scored {$score}% match for this {$opp['durationDays']}-day assignment in {$opp['city']}. Expected remuneration: " . formatINR($opp['dailyRateMin']) . " - " . formatINR($opp['dailyRateMax']) . "/day.",
                        'matchScore' => $score,
                        'link' => '/opportunity-details.php?id=' . (string)$opportunityId,
                        'read' => false,
                        'createdAt' => new MongoDB\BSON\UTCDateTime()
                    ]);

                    $notifiedCount++;
                    $notifiedNames[] = $userName;

                    // Send email notification via Mentry Google SMTP
                    if (!empty($userEmail)) {
                        @sendOpportunityMatchEmail($userEmail, $userName, $opp);
                    }
                }
            }
        }
    }

    return [
        'count' => $notifiedCount,
        'trainers' => $notifiedNames
    ];
}

/**
 * Dispatch an administrative / operational notification to all admins and staff
 * 
 * @param string $type e.g. 'NEW_APPLICATION', 'NEW_TRAINER', 'NEW_REQUIREMENT', 'NEW_VENDOR', 'NEW_DEMAND', 'INQUIRY'
 * @param string $title Short descriptive title
 * @param string $message Detailed description/context
 * @param string $link Destination URL in admin panel
 * @param array $metadata Additional context array
 * @return bool
 */
function notifyAdmin($type, $title, $message, $link = '', $metadata = []) {
    try {
        $notifCol = getCollection("Notification");
        if (!$notifCol) return false;

        $notifDoc = [
            'recipientRole' => 'ADMIN',
            'isAdminAlert' => true,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'link' => $link,
            'metadata' => $metadata,
            'read' => false,
            'createdAt' => new MongoDB\BSON\UTCDateTime()
        ];

        $notifCol->insertOne($notifDoc);
        return true;
    } catch (\Throwable $e) {
        error_log("Failed to dispatch admin notification: " . $e->getMessage());
        return false;
    }
}

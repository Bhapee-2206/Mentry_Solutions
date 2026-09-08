<?php
// includes/notifications.php - Automated Match Notifications & Dispatch Engine
date_default_timezone_set('Asia/Kolkata');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
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

                    // Send email notification to top 3 matching trainers to avoid request timeout
                    if ($notifiedCount <= 3 && !empty($userEmail)) {
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
 * @param string $type e.g. 'NEW_APPLICATION', 'NEW_TRAINER', 'NEW_REQUIREMENT', 'NEW_VENDOR', 'NEW_DEMAND', 'OPPORTUNITY_STARTING_SOON', 'OPPORTUNITY_AUTO_CLOSED'
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
            'isStaffAlert' => true,
            'targetRoles' => ['ADMIN', 'SUPER_ADMIN', 'STAFF'],
            'type' => $type,
            'opportunityId' => $metadata['opportunityId'] ?? null,
            'milestone' => $metadata['milestone'] ?? null,
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

/**
 * Extract Unix timestamp from opportunity start date in any stored format
 *
 * @param array|object $opp Opportunity document
 * @return int|null Timestamp or null if unparseable
 */
function getOpportunityStartTimestamp($opp) {
    if (empty($opp)) return null;
    $startDate = $opp['startDate'] ?? null;
    if ($startDate instanceof MongoDB\BSON\UTCDateTime) {
        return round($startDate->toDateTime()->getTimestamp());
    } elseif (is_numeric($startDate)) {
        return ($startDate > 20000000000) ? round($startDate / 1000) : (int)$startDate;
    } elseif (is_string($startDate) && !empty($startDate)) {
        if (is_numeric($startDate)) {
            return ($startDate > 20000000000) ? round($startDate / 1000) : (int)$startDate;
        } else {
            $parsed = strtotime($startDate);
            if ($parsed !== false) return $parsed;
        }
    }
    return null;
}

/**
 * Check upcoming opportunity milestones (tomorrow and day after tomorrow) and auto-close expired opportunities.
 * - If start date is tomorrow (T+1): Send urgent notification to admin and staff.
 * - If start date is day after tomorrow (T+2): Send reminder notification to admin and staff.
 * - If start date has passed (T < 0): Auto-close the opportunity and notify admin & staff.
 *
 * @param bool $force Force check regardless of in-process cache throttle
 * @return array Summary of operations
 */
function checkOpportunityScheduleMilestones($force = false) {
    static $alreadyRunInProcess = false;
    if ($alreadyRunInProcess && !$force) {
        return ['checked' => 0, 'notifiedTomorrow' => 0, 'notifiedIn2Days' => 0, 'closed' => 0];
    }
    $alreadyRunInProcess = true;

    $oppCol = getCollection("Opportunity");
    $notifCol = getCollection("Notification");
    if (!$oppCol || !$notifCol) {
        return ['checked' => 0, 'notifiedTomorrow' => 0, 'notifiedIn2Days' => 0, 'closed' => 0];
    }

    $now = time();
    $todayMidnight = strtotime(date('Y-m-d', $now));
    $todayDateStr = date('Y-m-d', $now);
    $stats = [
        'checked' => 0,
        'notifiedTomorrow' => 0,
        'notifiedIn2Days' => 0,
        'closed' => 0
    ];

    try {
        // Find opportunities that are not permanently cancelled or completed
        $activeOpps = $oppCol->find([
            'status' => ['$nin' => ['CANCELLED', 'COMPLETED']]
        ])->toArray();

        foreach ($activeOpps as $opp) {
            $stats['checked']++;
            $oppId = (string)$opp['_id'];
            $title = $opp['title'] ?? 'Training Opportunity';
            $city = $opp['city'] ?? 'Campus';
            $status = strtoupper($opp['status'] ?? 'PUBLISHED');
            $isAssigned = !empty($opp['assignedTrainerId']) || $status === 'MATCHED';

            $startTs = getOpportunityStartTimestamp($opp);
            if (!$startTs) continue;

            $startDateStr = date('Y-m-d', $startTs);
            $startDateMidnight = strtotime($startDateStr);
            $diffDays = (int)round(($startDateMidnight - $todayMidnight) / 86400);
            $dateFormatted = date('M j, Y', $startTs);

            // Cutoff: 18:00 (6:00 PM) on the eve (day before) of scheduled start date
            $closeCutoffTs = strtotime($startDateStr . ' 18:00:00 -1 day');
            $isPastCutoff = ($now >= $closeCutoffTs) || ($todayDateStr >= $startDateStr);

            // CASE 1: Cutoff has passed AND opportunity is unassigned
            // The opportunity must be closed on the evening of the day before start date
            if ($isPastCutoff && !$isAssigned) {
                if ($status === 'PUBLISHED') {
                    $oppCol->updateOne(
                        ['_id' => $opp['_id']],
                        ['$set' => [
                            'status' => 'CLOSED',
                            'closedAt' => new MongoDB\BSON\UTCDateTime(),
                            'autoClosedReason' => 'START_DATE_PASSED',
                            'updatedAt' => new MongoDB\BSON\UTCDateTime()
                        ]]
                    );

                    // Notify admin and staff if not already notified
                    $existingAutoCloseNotif = $notifCol->findOne([
                        'type' => 'OPPORTUNITY_AUTO_CLOSED',
                        '$or' => [
                            ['opportunityId' => $oppId],
                            ['metadata.opportunityId' => $oppId]
                        ]
                    ]);

                    if (!$existingAutoCloseNotif) {
                        notifyAdmin(
                            'OPPORTUNITY_AUTO_CLOSED',
                            "Opportunity Closed: {$title} (Start Date Cutoff Passed)",
                            "The opportunity '{$title}' in {$city} scheduled for {$dateFormatted} has been automatically closed because no trainer was assigned prior to the reporting eve cutoff.",
                            "/admin/opportunity-view.php?id=" . $oppId,
                            [
                                'opportunityId' => $oppId,
                                'jobId' => $opp['jobId'] ?? $oppId,
                                'title' => $title,
                                'startDate' => $startDateStr,
                                'autoClosedReason' => 'START_DATE_PASSED'
                            ]
                        );
                    }
                    $stats['closed']++;
                }
                continue;
            }

            // For upcoming reminders, skip already closed opportunities
            if ($status === 'CLOSED') {
                continue;
            }

            // CASE 2: Starts TOMORROW (diffDays === 1)
            // Only alert if still within active window before the 18:00 eve cutoff
            if ($diffDays === 1 && $now < $closeCutoffTs) {
                $existingNotif = $notifCol->findOne([
                    'type' => 'OPPORTUNITY_STARTING_SOON',
                    '$or' => [
                        ['opportunityId' => $oppId, 'milestone' => 'TOMORROW'],
                        ['metadata.opportunityId' => $oppId, 'metadata.milestone' => 'TOMORROW']
                    ]
                ]);

                if (!$existingNotif) {
                    $urgencyPrefix = $isAssigned 
                        ? "Confirmed Training Starts Tomorrow!" 
                        : "Urgent: Unassigned Opportunity Starts Tomorrow!";
                    $detailMsg = $isAssigned
                        ? "Confirmed training program '{$title}' in {$city} commences tomorrow ({$dateFormatted}). Please verify faculty travel, lodging, and campus reporting schedule."
                        : "Training opportunity '{$title}' in {$city} is scheduled to start tomorrow ({$dateFormatted}). No faculty is assigned yet — please review matching trainers before the 6:00 PM cutoff!";

                    notifyAdmin(
                        'OPPORTUNITY_STARTING_SOON',
                        $urgencyPrefix,
                        $detailMsg,
                        "/admin/opportunity-view.php?id=" . $oppId,
                        [
                            'opportunityId' => $oppId,
                            'jobId' => $opp['jobId'] ?? $oppId,
                            'title' => $title,
                            'milestone' => 'TOMORROW',
                            'diffDays' => 1,
                            'isAssigned' => $isAssigned,
                            'startDate' => $startDateStr
                        ]
                    );
                    $stats['notifiedTomorrow']++;
                }
            }

            // CASE 3: Starts DAY AFTER TOMORROW (diffDays === 2)
            if ($diffDays === 2) {
                $existingNotif = $notifCol->findOne([
                    'type' => 'OPPORTUNITY_STARTING_SOON',
                    '$or' => [
                        ['opportunityId' => $oppId, 'milestone' => 'IN_2_DAYS'],
                        ['metadata.opportunityId' => $oppId, 'metadata.milestone' => 'IN_2_DAYS']
                    ]
                ]);

                if (!$existingNotif) {
                    $remindPrefix = $isAssigned 
                        ? "Reminder: Training Starts in 2 Days" 
                        : "Reminder: Opportunity Starts in 2 Days (Unassigned)";
                    $detailMsg = $isAssigned
                        ? "Training engagement '{$title}' in {$city} starts on {$dateFormatted} (in 2 days). Ensure course materials and logistics are finalized."
                        : "Opportunity '{$title}' in {$city} starts on {$dateFormatted} (in 2 days) and still requires faculty assignment.";

                    notifyAdmin(
                        'OPPORTUNITY_STARTING_SOON',
                        $remindPrefix,
                        $detailMsg,
                        "/admin/opportunity-view.php?id=" . $oppId,
                        [
                            'opportunityId' => $oppId,
                            'jobId' => $opp['jobId'] ?? $oppId,
                            'title' => $title,
                            'milestone' => 'IN_2_DAYS',
                            'diffDays' => 2,
                            'isAssigned' => $isAssigned,
                            'startDate' => date('Y-m-d', $startTs)
                        ]
                    );
                    $stats['notifiedIn2Days']++;
                }
            }
        }
    } catch (\Throwable $e) {
        error_log("Error in checkOpportunityScheduleMilestones: " . $e->getMessage());
    }

    return $stats;
}

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

            // Check trainer's notification preferences from settings
            $trainerPrefs = $trainer['notificationPreferences'] ?? ($user['notificationPreferences'] ?? null);
            if ($trainerPrefs && isset($trainerPrefs['all']) && !$trainerPrefs['all']) {
                continue; // Master notifications toggled OFF by trainer!
            }

            if (!empty($trainerUserId)) {
                $dedupNotifId = 'match_' . $trainerUserId . '_' . (string)$opportunityId;
                // Check if already notified using deterministic ID or query
                $existingNotif = $notifCol->findOne([
                    '$or' => [
                        ['_id' => $dedupNotifId],
                        ['userId' => $trainerUserId, 'opportunityId' => (string)$opportunityId]
                    ]
                ]);

                if (!$existingNotif) {
                    $insertRes = $notifCol->insertOne([
                        '_id' => $dedupNotifId,
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
                    $notifId = $dedupNotifId;

                    $notifiedCount++;
                    $notifiedNames[] = $userName;

                    // Dispatch transactional email to matching trainer
                    try {
                        if (function_exists('sendOpportunityMatchNotificationEmail')) {
                            sendOpportunityMatchNotificationEmail($user, $trainer, $opp, $score);
                        }
                    } catch (\Throwable $e) {
                        error_log("Failed to send match email: " . $e->getMessage());
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
        // Respect Admin notification master toggle from platform settings
        $configCol = getCollection("SystemConfig");
        if ($configCol) {
            $cfg = $configCol->findOne(['key' => 'notification_settings']);
            if ($cfg && isset($cfg['master_enabled']) && !$cfg['master_enabled']) {
                return false; // Master admin notifications globally disabled
            }
        }

        $notifCol = getCollection("Notification");
        if (!$notifCol) return false;

        $idempotencyKey = $metadata['idempotencyKey'] ?? (!empty($metadata['opportunityId']) && !empty($metadata['milestone']) ? 'admin_' . $type . '_' . $metadata['opportunityId'] . '_' . $metadata['milestone'] : (!empty($metadata['opportunityId']) && $type === 'OPPORTUNITY_AUTO_CLOSED' ? 'admin_' . $type . '_' . $metadata['opportunityId'] : null));

        if ($idempotencyKey) {
            $existing = $notifCol->findOne(['_id' => $idempotencyKey]);
            if ($existing) {
                return true; // Already recorded idempotently, prevent duplicate creation
            }
        }

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
        if ($idempotencyKey) {
            $notifDoc['_id'] = $idempotencyKey;
        }

        $insertRes = $notifCol->insertOne($notifDoc);
        $adminNotifId = (string)$insertRes->getInsertedId();
        @dispatchWebPushNotification(
            ['userRole' => ['$in' => ['ADMIN', 'SUPER_ADMIN', 'STAFF']]], 
            $title, 
            $message, 
            $link,
            ['id' => $adminNotifId, 'type' => $type]
        );
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

        $today = function_exists('getTodayISTDate') ? getTodayISTDate() : date('Y-m-d');

        foreach ($activeOpps as $opp) {
            $stats['checked']++;
            $oppId = (string)$opp['_id'];
            $title = $opp['title'] ?? 'Training Opportunity';
            $city = $opp['city'] ?? 'Campus';
            $status = strtoupper($opp['status'] ?? 'PUBLISHED');
            $isFullyStaffed = function_exists('isOpportunityFullyStaffed') ? isOpportunityFullyStaffed($opp) : (!empty($opp['assignedTrainerId']) || $status === 'MATCHED');

            // CASE 0: Program Completed (endDate < today in IST)
            $endStr = function_exists('normalizeDateToISTString') ? normalizeDateToISTString($opp['endDate'] ?? null) : null;
            if ($endStr && $endStr < $today) {
                if ($status !== 'COMPLETED') {
                    $oppCol->updateOne(
                        ['_id' => $opp['_id']],
                        ['$set' => [
                            'status' => 'COMPLETED',
                            'completedAt' => new MongoDB\BSON\UTCDateTime(),
                            'updatedAt' => new MongoDB\BSON\UTCDateTime()
                        ]]
                    );

                    $existingCompletedNotif = $notifCol->findOne([
                        'type' => 'PROGRAM_COMPLETED',
                        '$or' => [
                            ['opportunityId' => $oppId],
                            ['metadata.opportunityId' => $oppId]
                        ]
                    ]);
                    if (!$existingCompletedNotif) {
                        notifyAdmin(
                            'PROGRAM_COMPLETED',
                            "Program Completed: {$title}",
                            "The training program '{$title}' in {$city} scheduled through " . formatDate($opp['endDate'] ?? null) . " has successfully concluded.",
                            "/admin/opportunity-view.php?id=" . $oppId,
                            [
                                'opportunityId' => $oppId,
                                'jobId' => $opp['jobId'] ?? $oppId,
                                'title' => $title,
                                'endDate' => $endStr
                            ]
                        );
                    }
                }
                continue;
            }

            $startTs = getOpportunityStartTimestamp($opp);
            if (!$startTs) continue;

            $startDateStr = date('Y-m-d', $startTs);
            $startDateMidnight = strtotime($startDateStr);
            $diffDays = (int)round(($startDateMidnight - $todayMidnight) / 86400);
            $dateFormatted = date('M j, Y', $startTs);

            // Cutoff: 18:00 (6:00 PM) on the eve (day before) of scheduled start date
            $closeCutoffTs = strtotime($startDateStr . ' 18:00:00 -1 day');
            $isPastCutoff = ($now >= $closeCutoffTs) || ($todayDateStr >= $startDateStr);

            // CASE 1: Cutoff has passed AND opportunity has no assigned trainers
            if ($isPastCutoff && !$isFullyStaffed) {
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

/**
 * Safe retired push notification stub.
 * Push notifications have been decommissioned per architecture migration.
 * Always returns true without attempting external network calls or web push dispatch.
 */
function dispatchWebPushNotification(array $filter, string $title, string $body, string $url = '/', array $extraData = []) {
    // Push notifications retired in favor of In-App + Transactional Email
    return true;
}

/**
 * Creates or updates an administrative Work Order Confirmation DRAFT for an assigned trainer.
 * Per requirement, this generates a DRAFT and NEVER sends automatically without explicit admin review.
 *
 * @param string $opportunityId
 * @param string $trainerId
 * @param array|null $adminUser
 * @param bool $isRevised
 * @return array Draft details
 */
function createWorkOrderDraft($opportunityId, $trainerId, $adminUser = null, $isRevised = false) {
    $oppCol = getCollection("Opportunity");
    $trainerCol = getCollection("Trainer");
    $userCol = getCollection("User");
    $confCol = getCollection("TrainerConfirmation");

    if (!$oppCol || !$trainerCol || !$confCol) {
        return ['success' => false, 'message' => 'Database connection unavailable'];
    }

    try {
        $opp = $oppCol->findOne(['_id' => new MongoDB\BSON\ObjectId((string)$opportunityId)]);
    } catch (\Throwable $e) {
        $opp = $oppCol->findOne(['_id' => (string)$opportunityId]);
    }

    try {
        $trainer = $trainerCol->findOne(['_id' => new MongoDB\BSON\ObjectId((string)$trainerId)]);
    } catch (\Throwable $e) {
        $trainer = $trainerCol->findOne(['_id' => (string)$trainerId]);
    }

    if (!$opp || !$trainer) {
        return ['success' => false, 'message' => 'Opportunity or Trainer not found'];
    }

    $user = null;
    $trainerUserId = $trainer['userId'] ?? null;
    if ($trainerUserId && $userCol) {
        try {
            $user = $userCol->findOne(['_id' => new MongoDB\BSON\ObjectId((string)$trainerUserId)]);
        } catch (\Throwable $e) {
            $user = $userCol->findOne(['_id' => (string)$trainerUserId]);
        }
    }

    $trainerEmail = trim($user['email'] ?? ($trainer['email'] ?? ''));
    if (empty($trainerEmail)) {
        return ['success' => false, 'message' => 'Trainer registered email not found'];
    }

    require_once __DIR__ . '/mailer.php';
    $draftData = generateWorkOrderEmailData($opp, $trainer, $user, $isRevised);

    $oppIdStr = (string)($opp['_id'] ?? $opportunityId);
    $trainerIdStr = (string)($trainer['_id'] ?? $trainerId);

    // If an existing draft already exists, update it; otherwise insert
    $existing = $confCol->findOne([
        'opportunityId' => $oppIdStr,
        'trainerId' => $trainerIdStr,
        'status' => 'DRAFT'
    ]);

    $doc = [
        'opportunityId' => $oppIdStr,
        'trainerId' => $trainerIdStr,
        'trainerEmail' => $trainerEmail,
        'trainerName' => $trainer['name'] ?? ($user['name'] ?? 'Trainer'),
        'subject' => $draftData['subject'],
        'html' => $draftData['html'],
        'plainText' => $draftData['plainText'],
        'variables' => $draftData['variables'],
        'isRevised' => $isRevised,
        'status' => 'DRAFT',
        'updatedAt' => new MongoDB\BSON\UTCDateTime()
    ];

    if ($adminUser) {
        $doc['updatedBy'] = [
            'id' => (string)($adminUser['_id'] ?? ($adminUser['id'] ?? '')),
            'name' => $adminUser['name'] ?? ($adminUser['username'] ?? 'Admin'),
            'email' => $adminUser['email'] ?? ''
        ];
    }

    if ($existing) {
        $confCol->updateOne(['_id' => $existing['_id']], ['$set' => $doc]);
        $draftId = (string)$existing['_id'];
    } else {
        $doc['createdAt'] = new MongoDB\BSON\UTCDateTime();
        $ins = $confCol->insertOne($doc);
        $draftId = (string)$ins->getInsertedId();
    }

    return [
        'success' => true,
        'draftId' => $draftId,
        'subject' => $draftData['subject'],
        'to' => $trainerEmail
    ];
}

/**
 * Dispatches in-app notification and email when a trainer is assigned to an opportunity.
 * Also prepares the Work Order Draft for administrator review.
 */
function notifyTrainerAssigned($trainerId, $opportunityId, $asgData = []) {
    $oppCol = getCollection("Opportunity");
    $trainerCol = getCollection("Trainer");
    $userCol = getCollection("User");
    $notifCol = getCollection("Notification");

    if (!$oppCol || !$trainerCol) return false;

    try {
        $opp = $oppCol->findOne(['_id' => new MongoDB\BSON\ObjectId((string)$opportunityId)]);
    } catch (\Throwable $e) {
        $opp = $oppCol->findOne(['_id' => (string)$opportunityId]);
    }

    try {
        $trainer = $trainerCol->findOne(['_id' => new MongoDB\BSON\ObjectId((string)$trainerId)]);
    } catch (\Throwable $e) {
        $trainer = $trainerCol->findOne(['_id' => (string)$trainerId]);
    }

    if (!$opp || !$trainer) return false;

    $user = null;
    $trainerUserId = (string)($trainer['userId'] ?? '');
    if (!empty($trainerUserId) && $userCol) {
        try {
            $user = $userCol->findOne(['_id' => new MongoDB\BSON\ObjectId($trainerUserId)]);
        } catch (\Throwable $e) {
            $user = $userCol->findOne(['_id' => $trainerUserId]);
        }
    }

    $oppTitle = trim($opp['title'] ?? 'Training Opportunity');
    $collegeName = trim($opp['collegeName'] ?? '');
    $collegeSuffix = !empty($collegeName) ? " at {$collegeName}" : "";
    $cityStr = !empty($opp['city']) ? " in {$opp['city']}" : "";
    $datesStr = "";
    if (!empty($opp['startDate'])) {
        $datesStr = " (" . formatDate($opp['startDate']) . (!empty($opp['endDate']) ? " – " . formatDate($opp['endDate']) : "") . ")";
    }

    // 1. Create In-App Notification (Permanent History)
    if ($notifCol && !empty($trainerUserId)) {
        $notifCol->insertOne([
            'userId' => $trainerUserId,
            'trainerId' => (string)$trainerId,
            'opportunityId' => (string)$opportunityId,
            'type' => 'TRAINER_ASSIGNED',
            'title' => "🎯 Assignment Confirmed: {$oppTitle}{$collegeSuffix}",
            'message' => "You have been officially confirmed and assigned for {$oppTitle}{$collegeSuffix}{$cityStr}{$datesStr}. Your work order draft is being processed by operations.",
            'link' => '/trainer/assignments.php',
            'read' => false,
            'createdAt' => new MongoDB\BSON\UTCDateTime()
        ]);
    }

    // 2. Prepare Work Order Email DRAFT (DO NOT SEND AUTOMATICALLY)
    try {
        createWorkOrderDraft($opportunityId, $trainerId, $_SESSION['user'] ?? null);
    } catch (\Throwable $e) {
        error_log("Failed to create work order draft: " . $e->getMessage());
    }

    // 3. Dispatch transactional assignment alert email
    $toEmail = trim($user['email'] ?? ($trainer['email'] ?? ''));
    $toName = $trainer['name'] ?? ($user['name'] ?? 'Trainer');
    if (!empty($toEmail)) {
        require_once __DIR__ . '/mailer.php';
        $subject = "Assignment Confirmed: {$oppTitle} | Mentry Solutions";
        $body = "<p>Dear {$toName},</p>"
              . "<p>You have been officially assigned as faculty trainer for <strong>" . htmlspecialchars($oppTitle) . "</strong>{$collegeSuffix}{$cityStr}{$datesStr}.</p>"
              . "<p>Our operations team is preparing your official engagement work order and logistics schedule. You can view your assignment details in the <a href=\"https://mentry-solutions.vercel.app/trainer/assignments.php\">Mentry Trainer Portal</a>.</p>"
              . "<p>Regards,<br><strong>Mentry Solutions Operations</strong></p>";

        try {
            sendMentryEmail($toEmail, $toName, $subject, $body, 'TRAINER_ASSIGNED', [
                'opportunityId' => (string)$opportunityId,
                'trainerId' => (string)$trainerId
            ]);
        } catch (\Throwable $e) {
            error_log("Failed to send assignment alert email: " . $e->getMessage());
        }
    }

    return true;
}

/**
 * Dispatches in-app notification and email when an application status changes (e.g. ACCEPTED, REJECTED).
 */
function notifyApplicationStatusChanged($applicationId, $status, $adminNotes = '') {
    $appCol = getCollection("Application");
    $oppCol = getCollection("Opportunity");
    $trainerCol = getCollection("Trainer");
    $userCol = getCollection("User");
    $notifCol = getCollection("Notification");

    if (!$appCol || !$oppCol || !$trainerCol) return false;

    try {
        $app = $appCol->findOne(['_id' => new MongoDB\BSON\ObjectId((string)$applicationId)]);
    } catch (\Throwable $e) {
        $app = $appCol->findOne(['_id' => (string)$applicationId]);
    }
    if (!$app) return false;

    $trainerId = (string)($app['trainerId'] ?? '');
    $oppId = (string)($app['opportunityId'] ?? '');

    try {
        $opp = $oppCol->findOne(['_id' => new MongoDB\BSON\ObjectId($oppId)]);
    } catch (\Throwable $e) {
        $opp = $oppCol->findOne(['_id' => $oppId]);
    }

    try {
        $trainer = $trainerCol->findOne(['_id' => new MongoDB\BSON\ObjectId($trainerId)]);
    } catch (\Throwable $e) {
        $trainer = $trainerCol->findOne(['_id' => $trainerId]);
    }

    if (!$opp || !$trainer) return false;

    $user = null;
    $trainerUserId = (string)($trainer['userId'] ?? '');
    if (!empty($trainerUserId) && $userCol) {
        try {
            $user = $userCol->findOne(['_id' => new MongoDB\BSON\ObjectId($trainerUserId)]);
        } catch (\Throwable $e) {
            $user = $userCol->findOne(['_id' => $trainerUserId]);
        }
    }

    $toEmail = trim($user['email'] ?? ($trainer['email'] ?? ''));
    $toName = $trainer['name'] ?? ($user['name'] ?? 'Trainer');
    $oppTitle = trim($opp['title'] ?? 'Training Opportunity');
    $collegeName = trim($opp['collegeName'] ?? '');
    $collegeSuffix = !empty($collegeName) ? " at {$collegeName}" : "";

    $statusUpper = strtoupper($status);
    $notifTitle = '';
    $notifMsg = '';
    $emailSubject = '';
    $emailBody = '';
    $emailType = 'APPLICATION_' . $statusUpper;

    if ($statusUpper === 'ACCEPTED') {
        $notifTitle = "🎉 Application Accepted: {$oppTitle}{$collegeSuffix}";
        $notifMsg = "Congratulations! Your application for {$oppTitle}{$collegeSuffix} has been ACCEPTED. Your engagement itinerary is available in your assignments.";
        $emailSubject = "Application Accepted: {$oppTitle} | Mentry Solutions";
        $emailBody = "<p>Dear {$toName},</p>"
                   . "<p>Congratulations! Your application for <strong>" . htmlspecialchars($oppTitle) . "</strong>{$collegeSuffix} has been <strong>ACCEPTED</strong>.</p>"
                   . (!empty($adminNotes) ? "<p><em>Note from Mentry Operations:</em> " . htmlspecialchars($adminNotes) . "</p>" : "")
                   . "<p>Please log in to the <a href=\"https://mentry-solutions.vercel.app/trainer/assignments.php\">Mentry Trainer Portal</a> to review your assignment itinerary.</p>"
                   . "<p>Regards,<br><strong>Mentry Solutions</strong></p>";

        // Prepare Work Order Draft
        try {
            createWorkOrderDraft($oppId, $trainerId, $_SESSION['user'] ?? null);
        } catch (\Throwable $e) {
            error_log("Failed to create work order draft on application acceptance: " . $e->getMessage());
        }
    } elseif ($statusUpper === 'REJECTED') {
        $notifTitle = "Application Update: {$oppTitle}";
        $notifMsg = "Your application for {$oppTitle}{$collegeSuffix} was not selected this time. New matching opportunities are available on your feed.";
        $emailSubject = "Application Update: {$oppTitle} | Mentry Solutions";
        $emailBody = "<p>Dear {$toName},</p>"
                   . "<p>Thank you for expressing interest in <strong>" . htmlspecialchars($oppTitle) . "</strong>{$collegeSuffix}.</p>"
                   . "<p>For this specific batch, another profile was selected. However, your profile is actively matched with upcoming training requirements across our network.</p>"
                   . (!empty($adminNotes) ? "<p><em>Note from Admin:</em> " . htmlspecialchars($adminNotes) . "</p>" : "")
                   . "<p>View new open opportunities here: <a href=\"https://mentry-solutions.vercel.app/trainer/opportunities.php\">Browse Opportunities</a>.</p>"
                   . "<p>Regards,<br><strong>Mentry Solutions</strong></p>";
    } else {
        $notifTitle = "Application Status Update: {$oppTitle}";
        $notifMsg = "Your application for {$oppTitle}{$collegeSuffix} status is now {$status}.";
    }

    // 1. In-app notification
    if ($notifCol && !empty($trainerUserId) && !empty($notifTitle)) {
        $notifCol->insertOne([
            'userId' => $trainerUserId,
            'trainerId' => $trainerId,
            'opportunityId' => $oppId,
            'applicationId' => (string)$applicationId,
            'type' => 'APPLICATION_' . $statusUpper,
            'title' => $notifTitle,
            'message' => $notifMsg,
            'link' => ($statusUpper === 'ACCEPTED') ? '/trainer/assignments.php' : '/trainer/applications.php',
            'read' => false,
            'createdAt' => new MongoDB\BSON\UTCDateTime()
        ]);
    }

    // 2. Email notification
    if (!empty($toEmail) && !empty($emailSubject)) {
        require_once __DIR__ . '/mailer.php';
        try {
            sendMentryEmail($toEmail, $toName, $emailSubject, $emailBody, $emailType, [
                'applicationId' => (string)$applicationId,
                'opportunityId' => $oppId,
                'trainerId' => $trainerId
            ]);
        } catch (\Throwable $e) {
            error_log("Failed to send application status email: " . $e->getMessage());
        }
    }

    return true;
}

/**
 * Dispatches in-app and email notifications when a program is postponed or closed.
 */
function notifyProgramPostponedOrClosed($opportunityId, $reason = '') {
    $oppCol = getCollection("Opportunity");
    $trainerCol = getCollection("Trainer");
    $userCol = getCollection("User");
    $notifCol = getCollection("Notification");
    $asgCol = getCollection("Assignment");

    if (!$oppCol) return false;

    try {
        $opp = $oppCol->findOne(['_id' => new MongoDB\BSON\ObjectId((string)$opportunityId)]);
    } catch (\Throwable $e) {
        $opp = $oppCol->findOne(['_id' => (string)$opportunityId]);
    }
    if (!$opp) return false;

    $oppTitle = trim($opp['title'] ?? 'Training Program');
    $collegeName = trim($opp['collegeName'] ?? '');
    $collegeSuffix = !empty($collegeName) ? " at {$collegeName}" : "";

    // Find all assigned or applied trainers
    $trainerIds = [];
    if (!empty($opp['assignedTrainerIds']) && is_array($opp['assignedTrainerIds'])) {
        foreach ($opp['assignedTrainerIds'] as $tid) $trainerIds[] = (string)$tid;
    } elseif (!empty($opp['assignedTrainerId'])) {
        $trainerIds[] = (string)$opp['assignedTrainerId'];
    }

    $asgs = $asgCol ? $asgCol->find([
        'opportunityId' => (string)$opportunityId,
        'status' => ['$in' => ['SCHEDULED', 'IN_PROGRESS', 'CONFIRMED', 'ASSIGNED']]
    ])->toArray() : [];
    foreach ($asgs as $a) {
        if (!empty($a['trainerId'])) $trainerIds[] = (string)$a['trainerId'];
    }
    $trainerIds = array_values(array_unique($trainerIds));

    foreach ($trainerIds as $tId) {
        try {
            $trainer = $trainerCol ? $trainerCol->findOne(['_id' => new MongoDB\BSON\ObjectId($tId)]) : null;
        } catch (\Throwable $e) {
            $trainer = $trainerCol ? $trainerCol->findOne(['_id' => $tId]) : null;
        }
        if (!$trainer) continue;

        $trainerUserId = (string)($trainer['userId'] ?? '');
        $user = null;
        if (!empty($trainerUserId) && $userCol) {
            try {
                $user = $userCol->findOne(['_id' => new MongoDB\BSON\ObjectId($trainerUserId)]);
            } catch (\Throwable $e) {
                $user = $userCol->findOne(['_id' => $trainerUserId]);
            }
        }

        $toEmail = trim($user['email'] ?? ($trainer['email'] ?? ''));
        $toName = $trainer['name'] ?? ($user['name'] ?? 'Trainer');

        // 1. In-app notification
        if ($notifCol && !empty($trainerUserId)) {
            $notifCol->insertOne([
                'userId' => $trainerUserId,
                'trainerId' => $tId,
                'opportunityId' => (string)$opportunityId,
                'type' => 'PROGRAM_POSTPONED',
                'title' => "Training Program Postponed: {$oppTitle}",
                'message' => "Your training program {$oppTitle}{$collegeSuffix} has been postponed. Please check the updated schedule or wait for revised dates.",
                'link' => '/trainer/assignments.php',
                'read' => false,
                'createdAt' => new MongoDB\BSON\UTCDateTime()
            ]);
        }

        // 2. Email notification
        if (!empty($toEmail)) {
            require_once __DIR__ . '/mailer.php';
            $subject = "Training Program Postponed: {$oppTitle} | Mentry Solutions";
            $body = "<p>Dear {$toName},</p>"
                  . "<p>This is to inform you that the training program <strong>" . htmlspecialchars($oppTitle) . "</strong>{$collegeSuffix} has been postponed.</p>"
                  . (!empty($reason) ? "<p><em>Details:</em> " . htmlspecialchars($reason) . "</p>" : "")
                  . "<p>Mentry Operations will notify you as soon as the revised schedule is finalized.</p>"
                  . "<p>Regards,<br><strong>Mentry Solutions Operations</strong></p>";

            try {
                sendMentryEmail($toEmail, $toName, $subject, $body, 'PROGRAM_POSTPONED', [
                    'opportunityId' => (string)$opportunityId,
                    'trainerId' => $tId
                ]);
            } catch (\Throwable $e) {
                error_log("Failed to send postponement email: " . $e->getMessage());
            }
        }
    }

    return true;
}

/**
 * Dispatches in-app and email notifications when a program is reopened with new future dates.
 */
function notifyProgramReopened($opportunityId) {
    $oppCol = getCollection("Opportunity");
    $trainerCol = getCollection("Trainer");
    $userCol = getCollection("User");
    $notifCol = getCollection("Notification");
    $asgCol = getCollection("Assignment");

    if (!$oppCol) return false;

    try {
        $opp = $oppCol->findOne(['_id' => new MongoDB\BSON\ObjectId((string)$opportunityId)]);
    } catch (\Throwable $e) {
        $opp = $oppCol->findOne(['_id' => (string)$opportunityId]);
    }
    if (!$opp) return false;

    $oppTitle = trim($opp['title'] ?? 'Training Program');
    $collegeName = trim($opp['collegeName'] ?? '');
    $collegeSuffix = !empty($collegeName) ? " at {$collegeName}" : "";
    $datesStr = "";
    if (!empty($opp['startDate'])) {
        $datesStr = " (" . formatDate($opp['startDate']) . (!empty($opp['endDate']) ? " – " . formatDate($opp['endDate']) : "") . ")";
    }

    // Notify any previously assigned trainers
    $trainerIds = [];
    if (!empty($opp['assignedTrainerIds']) && is_array($opp['assignedTrainerIds'])) {
        foreach ($opp['assignedTrainerIds'] as $tid) $trainerIds[] = (string)$tid;
    } elseif (!empty($opp['assignedTrainerId'])) {
        $trainerIds[] = (string)$opp['assignedTrainerId'];
    }

    foreach ($trainerIds as $tId) {
        try {
            $trainer = $trainerCol ? $trainerCol->findOne(['_id' => new MongoDB\BSON\ObjectId($tId)]) : null;
        } catch (\Throwable $e) {
            $trainer = $trainerCol ? $trainerCol->findOne(['_id' => $tId]) : null;
        }
        if (!$trainer) continue;

        $trainerUserId = (string)($trainer['userId'] ?? '');
        $user = null;
        if (!empty($trainerUserId) && $userCol) {
            try {
                $user = $userCol->findOne(['_id' => new MongoDB\BSON\ObjectId($trainerUserId)]);
            } catch (\Throwable $e) {
                $user = $userCol->findOne(['_id' => $trainerUserId]);
            }
        }

        $toEmail = trim($user['email'] ?? ($trainer['email'] ?? ''));
        $toName = $trainer['name'] ?? ($user['name'] ?? 'Trainer');

        // 1. In-app notification
        if ($notifCol && !empty($trainerUserId)) {
            $notifCol->insertOne([
                'userId' => $trainerUserId,
                'trainerId' => $tId,
                'opportunityId' => (string)$opportunityId,
                'type' => 'PROGRAM_REOPENED',
                'title' => "Training Program Reopened: {$oppTitle}",
                'message' => "The training program {$oppTitle}{$collegeSuffix} has been reopened with updated schedule{$datesStr}. Please review the new dates.",
                'link' => '/trainer/assignments.php',
                'read' => false,
                'createdAt' => new MongoDB\BSON\UTCDateTime()
            ]);
        }

        // 2. Email notification
        if (!empty($toEmail)) {
            require_once __DIR__ . '/mailer.php';
            $subject = "Program Reopened: {$oppTitle} | Mentry Solutions";
            $body = "<p>Dear {$toName},</p>"
                  . "<p>The training program <strong>" . htmlspecialchars($oppTitle) . "</strong>{$collegeSuffix} has been reopened with the following schedule: <strong>{$datesStr}</strong>.</p>"
                  . "<p>Please log in to your <a href=\"https://mentry-solutions.vercel.app/trainer/assignments.php\">Mentry Trainer Portal</a> to review details.</p>"
                  . "<p>Regards,<br><strong>Mentry Solutions</strong></p>";

            try {
                sendMentryEmail($toEmail, $toName, $subject, $body, 'PROGRAM_REOPENED', [
                    'opportunityId' => (string)$opportunityId,
                    'trainerId' => $tId
                ]);
            } catch (\Throwable $e) {
                error_log("Failed to send reopened email: " . $e->getMessage());
            }
        }
    }

    return true;
}

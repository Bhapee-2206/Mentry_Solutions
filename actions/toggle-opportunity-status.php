<?php
// actions/toggle-opportunity-status.php - Toggle or set opportunity status (e.g. CLOSE / REOPEN)
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdminOrStaff();
requireCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $opportunityId = trim($_POST['opportunityId'] ?? ($_POST['id'] ?? ''));
    $action = trim(strtolower($_POST['action'] ?? ''));
    $targetStatus = trim(strtoupper($_POST['status'] ?? ''));

    if (!empty($opportunityId)) {
        $oppCol = getCollection("Opportunity");
        $opp = null;
        if ($oppCol) {
            try {
                $opp = $oppCol->findOne(['_id' => new MongoDB\BSON\ObjectId($opportunityId)]);
            } catch (\Throwable $e) {
                $opp = $oppCol->findOne(['_id' => $opportunityId]);
            }
        }

        if ($opp && $oppCol) {
            $currentStatus = strtoupper($opp['status'] ?? 'PUBLISHED');
            $newStatus = $currentStatus;

            if ($action === 'close') {
                $newStatus = 'CLOSED';
            } elseif ($action === 'reopen') {
                $newStatus = 'PUBLISHED';
            } elseif ($action === 'toggle') {
                $newStatus = ($currentStatus === 'CLOSED' || $currentStatus === 'MATCHED') ? 'PUBLISHED' : 'CLOSED';
            } elseif (!empty($targetStatus)) {
                $newStatus = $targetStatus;
            }

            require_once __DIR__ . '/../includes/helpers.php';
            require_once __DIR__ . '/../includes/notifications.php';

            // REOPEN VALIDATION RULE:
            // An administrator must NEVER be able to reopen an opportunity with a past start date.
            if ($newStatus === 'PUBLISHED') {
                $today = getTodayISTDate();
                $startStr = normalizeDateToISTString($opp['startDate'] ?? null);
                $endStr = normalizeDateToISTString($opp['endDate'] ?? null);

                if (!$startStr || $startStr < $today || ($endStr && $endStr < $today)) {
                    $_SESSION['flash_error'] = "This opportunity has past program dates. Update the program dates to today or a future date before reopening.";
                    header("Location: /admin/opportunity-edit.php?id=" . urlencode($opportunityId));
                    exit();
                }
            }

            $updateData = [
                'status' => $newStatus,
                'updatedAt' => new MongoDB\BSON\UTCDateTime()
            ];

            if ($newStatus === 'CLOSED') {
                $updateData['closedAt'] = new MongoDB\BSON\UTCDateTime();
                $updateData['autoClosedReason'] = 'ADMIN_MANUAL_CLOSE';
                $_SESSION['flash_success'] = "Opportunity has been closed to new applications.";

                if (function_exists('notifyProgramPostponedOrClosed')) {
                    notifyProgramPostponedOrClosed($opportunityId, 'Program marked as closed or postponed.');
                }
            } elseif ($newStatus === 'PUBLISHED') {
                $updateData['closedAt'] = null;
                $updateData['autoClosedReason'] = null;
                $updateData['reopenedAt'] = new MongoDB\BSON\UTCDateTime();

                // If all positions were previously filled, expand quota by 1 so new candidates can apply
                $trainersNeeded = max(1, (int)($opp['trainersNeeded'] ?? 1));
                $asgCol = getCollection("Assignment");
                $oppQueryIds = [(string)$opportunityId];
                try { $oppQueryIds[] = new MongoDB\BSON\ObjectId((string)$opportunityId); } catch (\Throwable $e) {}
                $activeAsgs = $asgCol ? $asgCol->find([
                    'opportunityId' => ['$in' => $oppQueryIds],
                    'status' => ['$in' => ['SCHEDULED', 'IN_PROGRESS', 'CONFIRMED', 'ASSIGNED', 'ACCEPTED']]
                ])->toArray() : [];
                $uniqueActiveTrainers = [];
                foreach ($activeAsgs as $aa) {
                    if (!empty($aa['trainerId'])) {
                        $uniqueActiveTrainers[(string)$aa['trainerId']] = true;
                    }
                }
                if (!empty($opp['assignedTrainerIds']) && is_array($opp['assignedTrainerIds'])) {
                    foreach ($opp['assignedTrainerIds'] as $atid) {
                        if (!empty($atid)) $uniqueActiveTrainers[(string)$atid] = true;
                    }
                } elseif (!empty($opp['assignedTrainerId'])) {
                    $uniqueActiveTrainers[(string)$opp['assignedTrainerId']] = true;
                }
                $assignedCount = count($uniqueActiveTrainers);

                if ($assignedCount >= $trainersNeeded) {
                    $updateData['trainersNeeded'] = $assignedCount + 1;
                }

                // Notify assigned trainers that program has been reopened with new schedule
                if (function_exists('notifyProgramReopened')) {
                    notifyProgramReopened($opportunityId);
                }

                // Notify matching trainers of reopened opportunity
                if (function_exists('notifyMatchingTrainersForOpportunity')) {
                    notifyMatchingTrainersForOpportunity($opportunityId);
                }

                $_SESSION['flash_success'] = "Opportunity '" . htmlspecialchars($opp['title'] ?? 'Opportunity') . "' reopened successfully and is now active for trainer applications.";
            }

            try {
                $oppCol->updateOne(
                    ['_id' => new MongoDB\BSON\ObjectId($opportunityId)],
                    ['$set' => $updateData]
                );
            } catch (\Throwable $e) {
                $oppCol->updateOne(
                    ['_id' => $opportunityId],
                    ['$set' => $updateData]
                );
            }
        }
    }
}

$referer = $_SERVER['HTTP_REFERER'] ?? (!empty($opportunityId) ? '/admin/opportunity-view.php?id=' . urlencode($opportunityId) : '/admin/opportunities.php');
header("Location: " . $referer);
exit();

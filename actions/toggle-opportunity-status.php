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

            $updateData = [
                'status' => $newStatus,
                'updatedAt' => new MongoDB\BSON\UTCDateTime()
            ];

            if ($newStatus === 'CLOSED') {
                $updateData['closedAt'] = new MongoDB\BSON\UTCDateTime();
                $updateData['autoClosedReason'] = 'ADMIN_MANUAL_CLOSE';
                $_SESSION['flash_success'] = "Opportunity has been closed to new applications.";
            } elseif ($newStatus === 'PUBLISHED') {
                $updateData['closedAt'] = null;
                $updateData['autoClosedReason'] = null;
                $updateData['reopenedAt'] = new MongoDB\BSON\UTCDateTime();

                // If start date was in the past, roll dates forward so opportunity is immediately live and visible
                $startTs = getOpportunityStartTimestamp($opp);
                if ($startTs && strtotime(date('Y-m-d', $startTs)) < strtotime(date('Y-m-d'))) {
                    $durationDays = max(1, (int)($opp['durationDays'] ?? 5));
                    $newStartTs = strtotime('today 09:00:00');
                    $newEndTs = strtotime("+{$durationDays} days", $newStartTs);
                    $updateData['startDate'] = new MongoDB\BSON\UTCDateTime($newStartTs * 1000);
                    $updateData['endDate'] = new MongoDB\BSON\UTCDateTime($newEndTs * 1000);
                }

                // If all positions were previously filled, expand quota by 1 so new candidates can apply
                $trainersNeeded = max(1, (int)($opp['trainersNeeded'] ?? 1));
                $assignedCount = 0;
                if (!empty($opp['assignedTrainerIds']) && is_array($opp['assignedTrainerIds'])) {
                    $assignedCount = count($opp['assignedTrainerIds']);
                } elseif (!empty($opp['assignedTrainerId'])) {
                    $assignedCount = 1;
                }
                if ($assignedCount >= $trainersNeeded) {
                    $updateData['trainersNeeded'] = $assignedCount + 1;
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

<?php
// actions/toggle-opportunity-status.php - Toggle or set opportunity status (e.g. CLOSE / REOPEN)
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdminOrStaff();

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

            if ($newStatus === 'PUBLISHED') {
                require_once __DIR__ . '/../includes/notifications.php';
                $startTs = getOpportunityStartTimestamp($opp);
                if ($startTs && strtotime(date('Y-m-d', $startTs)) < strtotime(date('Y-m-d'))) {
                    $referer = $_SERVER['HTTP_REFERER'] ?? ('/admin/opportunity-view.php?id=' . urlencode($opportunityId));
                    $separator = (strpos($referer, '?') !== false) ? '&' : '?';
                    header("Location: " . $referer . $separator . "error=" . urlencode("Cannot reopen opportunity because its start date (" . date('M j, Y', $startTs) . ") has passed. Please edit the opportunity and set a future start date first."));
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
            } elseif ($newStatus === 'PUBLISHED') {
                $updateData['closedAt'] = null;
                $updateData['autoClosedReason'] = null;
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

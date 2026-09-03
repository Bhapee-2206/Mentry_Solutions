<?php
// actions/delete-opportunity.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? '';
    if (!empty($id)) {
        $oppCol = getCollection("Opportunity");
        $appCol = getCollection("Application");
        $asgCol = getCollection("Assignment");

        if ($oppCol) {
            try {
                $oppCol->deleteOne(['_id' => new MongoDB\BSON\ObjectId($id)]);
                if ($appCol) {
                    $appCol->deleteMany(['opportunityId' => $id]);
                }
                if ($asgCol) {
                    $asgCol->deleteMany(['opportunityId' => $id]);
                }
            } catch (Exception $e) {}
        }
    }
}

header("Location: /admin/opportunities.php");
exit();

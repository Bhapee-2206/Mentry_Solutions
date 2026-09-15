<?php
// actions/push/record-receipt.php - Push Receipt Diagnostic Endpoint
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (empty($data)) {
        echo json_encode(['success' => false, 'error' => 'No JSON data']);
        exit;
    }

    $receipt = [
        'testId' => (string)($data['testId'] ?? ''),
        'receivedAt' => (string)($data['receivedAt'] ?? date('c')),
        'showNotificationSuccess' => !empty($data['showNotificationSuccess']),
        'showNotificationError' => (string)($data['showNotificationError'] ?? ''),
        'swScope' => (string)($data['swScope'] ?? ''),
        'scriptURL' => (string)($data['scriptURL'] ?? ''),
        'createdAt' => new \MongoDB\BSON\UTCDateTime()
    ];

    try {
        $col = getCollection("PushReceiptLog");
        if ($col) {
            $col->insertOne($receipt);
        }
    } catch (\Throwable $e) {
        error_log('[Mentry Receipt Log Error] ' . $e->getMessage());
    }

    echo json_encode(['success' => true, 'recorded' => true]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $testId = trim($_GET['testId'] ?? '');
    $col = getCollection("PushReceiptLog");
    $result = null;

    if ($col) {
        if (!empty($testId)) {
            $result = $col->findOne(['testId' => $testId], ['sort' => ['_id' => -1]]);
        } else {
            $result = $col->findOne([], ['sort' => ['_id' => -1]]);
        }
    }

    if ($result) {
        echo json_encode([
            'success' => true,
            'found' => true,
            'receipt' => [
                'testId' => $result['testId'] ?? '',
                'receivedAt' => $result['receivedAt'] ?? '',
                'showNotificationSuccess' => !empty($result['showNotificationSuccess']),
                'showNotificationError' => $result['showNotificationError'] ?? '',
                'swScope' => $result['swScope'] ?? '',
                'scriptURL' => $result['scriptURL'] ?? ''
            ]
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'found' => false,
            'message' => 'No push receipt recorded yet' . (!empty($testId) ? " for testId $testId" : '')
        ]);
    }
    exit;
}

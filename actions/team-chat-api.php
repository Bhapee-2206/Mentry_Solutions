<?php
// actions/team-chat-api.php - Internal Admin/Staff Workspace Chat API with File Attachments & Explicit AI Extraction

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ai_agent.php';

header('Content-Type: application/json');

// Strict Role Authorization (Only Admin & Staff allowed)
if (!isLoggedIn() || !isAdminOrStaff()) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Access Denied: Internal Team Chat is restricted to authenticated Admin and Staff.'
    ]);
    exit();
}

$currentUser = getCurrentUser();
$chatFile = __DIR__ . '/../config/team_chat_messages.json';

// Helper to get local chat messages
function getChatMessages($file) {
    if (file_exists($file)) {
        $data = @json_decode(file_get_contents($file), true);
        if (is_array($data)) return $data;
    }
    return [
        [
            'id' => 'msg_init_1',
            'senderId' => '65e000000000000000000001',
            'senderName' => 'Operations Director (Admin 1)',
            'senderRole' => 'ADMIN',
            'text' => 'Welcome to the internal Mentry Operations Workspace. Use this channel for program logistics, trainer coordination, and document sharing.',
            'attachment' => null,
            'isAI' => false,
            'timestamp' => date('Y-m-d H:i:s', strtotime('-2 hours'))
        ],
        [
            'id' => 'msg_init_2',
            'senderId' => '65e000000000000000000003',
            'senderName' => 'Operations Coordinator (Staff 1)',
            'senderRole' => 'STAFF',
            'text' => 'Campus placement schedule for Bangalore and Chennai institutes has been synchronized. Ready for faculty assignments.',
            'attachment' => null,
            'isAI' => false,
            'timestamp' => date('Y-m-d H:i:s', strtotime('-1 hour'))
        ]
    ];
}

function saveChatMessages($file, $messages) {
    @file_put_contents($file, json_encode($messages, JSON_PRETTY_PRINT));
}

$action = $_GET['action'] ?? ($_POST['action'] ?? 'get_messages');

// 1. GET MESSAGES
if ($action === 'get_messages') {
    $messages = getChatMessages($chatFile);
    echo json_encode([
        'success' => true,
        'messages' => $messages,
        'currentUser' => [
            'id' => $currentUser['id'] ?? '',
            'name' => $currentUser['name'] ?? 'User',
            'role' => $currentUser['role'] ?? 'STAFF'
        ]
    ]);
    exit();
}

// 2. SEND MESSAGE / UPLOAD FILE
if ($action === 'send_message') {
    $text = trim($_POST['text'] ?? '');
    $attachment = null;

    // Handle file attachment if present
    if (!empty($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../public/uploads/chat/';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0777, true);
        }

        $fileName = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $_FILES['file']['name']);
        $uniqueName = time() . '_' . $fileName;
        $targetPath = $uploadDir . $uniqueName;

        if (move_uploaded_file($_FILES['file']['tmp_name'], $targetPath)) {
            $attachment = [
                'name' => $fileName,
                'url' => '/public/uploads/chat/' . $uniqueName,
                'type' => $_FILES['file']['type'],
                'size' => round($_FILES['file']['size'] / 1024, 1) . ' KB'
            ];
        }
    }

    if (empty($text) && empty($attachment)) {
        echo json_encode(['success' => false, 'message' => 'Message or file required.']);
        exit();
    }

    $messages = getChatMessages($chatFile);
    $newMsgId = 'msg_' . uniqid();

    $userMsg = [
        'id' => $newMsgId,
        'senderId' => $currentUser['id'] ?? '',
        'senderName' => $currentUser['name'] ?? 'Admin/Staff',
        'senderRole' => $currentUser['role'] ?? 'STAFF',
        'text' => $text,
        'attachment' => $attachment,
        'isAI' => false,
        'timestamp' => date('Y-m-d H:i:s')
    ];

    $messages[] = $userMsg;
    $aiResponseMsg = null;

    // Check for explicit @AI mention in text
    if (preg_match('/@ai\b/i', $text)) {
        $aiQuery = preg_replace('/@ai\b/i', '', $text);
        $aiQuery = trim($aiQuery);
        
        if (!empty($aiQuery)) {
            $aiMatch = AIAgent::processRequirementQuery($aiQuery);
            if ($aiMatch['success']) {
                $topMatches = $aiMatch['data']['topMatches'] ?? [];
                $aiSummary = "🤖 **Mentor AI Recommendation** for requirement: *\"{$aiQuery}\"*\n\n";
                if (!empty($topMatches)) {
                    foreach (array_slice($topMatches, 0, 3) as $i => $m) {
                        $aiSummary .= ($i + 1) . ". **{$m['name']}** ({$m['matchScore']}% Match) — {$m['headline']}\n   *Why:* {$m['whyRecommended']}\n";
                    }
                } else {
                    $aiSummary .= "No immediate strong matches found in active database. Try broadening skill constraints.";
                }

                $aiMsg = [
                    'id' => 'msg_' . uniqid(),
                    'senderId' => 'ai_assistant',
                    'senderName' => 'Mentor AI (Assistant)',
                    'senderRole' => 'AI_AGENT',
                    'text' => $aiSummary,
                    'aiData' => $aiMatch['data'],
                    'attachment' => null,
                    'isAI' => true,
                    'timestamp' => date('Y-m-d H:i:s')
                ];
                $messages[] = $aiMsg;
                $aiResponseMsg = $aiMsg;
            }
        }
    }

    saveChatMessages($chatFile, $messages);

    echo json_encode([
        'success' => true,
        'message' => $userMsg,
        'aiMessage' => $aiResponseMsg,
        'allMessages' => $messages
    ]);
    exit();
}

// 3. EXPLICIT "ASK AI ON MESSAGE"
if ($action === 'ask_ai_on_message') {
    $msgId = $_POST['messageId'] ?? '';
    $messages = getChatMessages($chatFile);
    $targetMsg = null;

    foreach ($messages as $m) {
        if ($m['id'] === $msgId) {
            $targetMsg = $m;
            break;
        }
    }

    if (!$targetMsg || empty($targetMsg['text'])) {
        echo json_encode(['success' => false, 'message' => 'Selected message content is empty.']);
        exit();
    }

    $aiMatch = AIAgent::processRequirementQuery($targetMsg['text']);
    
    if ($aiMatch['success']) {
        $topMatches = $aiMatch['data']['topMatches'] ?? [];
        $aiSummary = "🤖 **Mentor AI Extracted Requirement Analysis** from message by *{$targetMsg['senderName']}*:\n\n";
        
        if (!empty($topMatches)) {
            foreach (array_slice($topMatches, 0, 3) as $i => $m) {
                $aiSummary .= ($i + 1) . ". **{$m['name']}** ({$m['matchScore']}% Match) — {$m['headline']}\n   *Reason:* {$m['whyRecommended']}\n";
            }
        } else {
            $aiSummary .= "No direct matching trainers found in repository.";
        }

        $aiMsg = [
            'id' => 'msg_' . uniqid(),
            'senderId' => 'ai_assistant',
            'senderName' => 'Mentor AI (Assistant)',
            'senderRole' => 'AI_AGENT',
            'text' => $aiSummary,
            'aiData' => $aiMatch['data'],
            'attachment' => null,
            'isAI' => true,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        $messages[] = $aiMsg;
        saveChatMessages($chatFile, $messages);

        echo json_encode([
            'success' => true,
            'aiMessage' => $aiMsg,
            'allMessages' => $messages
        ]);
        exit();
    } else {
        echo json_encode(['success' => false, 'message' => 'Could not extract requirement.']);
        exit();
    }
}

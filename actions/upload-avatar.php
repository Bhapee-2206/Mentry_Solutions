<?php
// actions/upload-avatar.php - Upload Profile Photo with Strict 2MB Limit
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireAuth();

$currentUser = getCurrentUser();
$userId = $currentUser['id'];

$MAX_PHOTO_SIZE = 2 * 1024 * 1024; // 2MB strict limit

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar'])) {
    $file = $_FILES['avatar'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['avatar_error'] = "Upload failed with error code: " . $file['error'];
    } elseif ($file['size'] > $MAX_PHOTO_SIZE) {
        $_SESSION['avatar_error'] = "File size exceeds limit. Profile photos must be 2MB or smaller (Your file: " . round($file['size'] / (1024 * 1024), 2) . "MB).";
    } else {
        $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        // Verify valid extension and MIME image check
        $imageInfo = @getimagesize($file['tmp_name']);
        if (!in_array($ext, $allowedExts) || $imageInfo === false) {
            $_SESSION['avatar_error'] = "Invalid image file format. Only JPG, PNG, and WebP images are permitted.";
        } else {
            $uploadDir = __DIR__ . '/../public/uploads/avatars/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $fileName = 'avatar_' . $userId . '_' . time() . '.' . $ext;
            $targetPath = $uploadDir . $fileName;

            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                $avatarUrl = '/public/uploads/avatars/' . $fileName;

                $userCol = getCollection("User");
                if ($userCol) {
                    $updateData = [
                        'avatar' => $avatarUrl,
                        'updatedAt' => new MongoDB\BSON\UTCDateTime()
                    ];

                    if ($currentUser['role'] === 'VENDOR' || $currentUser['role'] === 'COLLEGE') {
                        $updateData['logo'] = $avatarUrl;
                    }

                    $userCol->updateOne(
                        ['_id' => new MongoDB\BSON\ObjectId($userId)],
                        ['$set' => $updateData]
                    );

                    $_SESSION['user']['avatar'] = $avatarUrl;
                    $_SESSION['avatar_success'] = "Profile photo updated successfully!";
                }
            } else {
                $_SESSION['avatar_error'] = "Failed to save the image to server storage.";
            }
        }
    }
}

$redirect = $_SERVER['HTTP_REFERER'] ?? '/trainer/profile.php';
header("Location: " . $redirect);
exit();

<?php
// actions/upload-avatar.php - Upload Profile Photo with Strict 2MB Limit
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireAuth();

$currentUser = getCurrentUser();
$isAdminOrStaff = in_array($currentUser['role'] ?? '', ['ADMIN', 'SUPER_ADMIN', 'STAFF']);
$userId = ($isAdminOrStaff && !empty($_POST['targetUserId'])) ? $_POST['targetUserId'] : $currentUser['id'];

$MAX_PHOTO_SIZE = 2 * 1024 * 1024; // 2MB strict limit

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Direct Avatar Image URL update
    if (!empty($_POST['avatarUrl'])) {
        $avatarUrl = trim($_POST['avatarUrl']);
        if (filter_var($avatarUrl, FILTER_VALIDATE_URL) || strpos($avatarUrl, '/public/') === 0) {
            $userCol = getCollection("User");
            if ($userCol) {
                $userCol->updateOne(
                    ['_id' => new MongoDB\BSON\ObjectId($userId)],
                    ['$set' => [
                        'avatar' => $avatarUrl,
                        'updatedAt' => new MongoDB\BSON\UTCDateTime()
                    ]]
                );
                $trainerCol = getCollection("Trainer");
                if ($trainerCol) {
                    $trainerCol->updateOne(
                        ['userId' => (string)$userId],
                        ['$set' => ['avatar' => $avatarUrl, 'updatedAt' => new MongoDB\BSON\UTCDateTime()]]
                    );
                }
                if ($userId === $currentUser['id']) {
                    $_SESSION['user']['avatar'] = $avatarUrl;
                }
                $_SESSION['avatar_success'] = "Profile photo updated successfully!";
            }
        } else {
            $_SESSION['avatar_error'] = "Invalid photo URL format.";
        }
    }
    // 2. File upload
    elseif (isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
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

                        $trainerCol = getCollection("Trainer");
                        if ($trainerCol) {
                            $trainerCol->updateOne(
                                ['userId' => (string)$userId],
                                ['$set' => ['avatar' => $avatarUrl, 'updatedAt' => new MongoDB\BSON\UTCDateTime()]]
                            );
                        }

                        if ($userId === $currentUser['id']) {
                            $_SESSION['user']['avatar'] = $avatarUrl;
                        }
                        $_SESSION['avatar_success'] = "Profile photo updated successfully!";
                    }
                } else {
                    $_SESSION['avatar_error'] = "Failed to save the image to server storage.";
                }
            }
        }
    }
}

$redirect = $_SERVER['HTTP_REFERER'] ?? '/trainer/profile.php';
header("Location: " . $redirect);
exit();

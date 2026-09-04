<?php
// actions/upload-document.php
ob_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

// Check if user is logged in
$currentUser = getCurrentUser();
if (!$currentUser) {
    header("Location: /login.php");
    exit();
}

$isAdminOrStaff = in_array($currentUser['role'] ?? '', ['ADMIN', 'SUPER_ADMIN', 'STAFF']);
$trainerId = $_POST['trainerId'] ?? '';
$title = trim($_POST['title'] ?? 'Resume / CV');
$docType = $_POST['type'] ?? 'RESUME';

// If not admin or staff, verify trainer is modifying their own profile
$trainerCol = getCollection("Trainer");
if (!$isAdminOrStaff) {
    $trainer = $trainerCol ? $trainerCol->findOne(['userId' => $currentUser['id']]) : null;
    if (!$trainer) {
        die("Trainer profile not found.");
    }
    $trainerId = (string)$trainer['_id'];
}

$MAX_DOC_SIZE = 5 * 1024 * 1024; // 5MB strict limit

if (!empty($trainerId) && isset($_FILES['document'])) {
    if ($_FILES['document']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['upload_error'] = "File upload failed with error code: " . $_FILES['document']['error'];
    } else {
        $file = $_FILES['document'];

        if ($file['size'] > $MAX_DOC_SIZE) {
            $_SESSION['upload_error'] = "File size exceeds limit. Maximum allowed size for resumes/PDFs is 5MB (Your file is " . round($file['size'] / (1024 * 1024), 2) . "MB).";
        } else {
            $allowedExtensions = ['pdf', 'doc', 'docx', 'png', 'jpg', 'jpeg'];
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (in_array($extension, $allowedExtensions)) {
                $fileName = 'doc_' . $trainerId . '_' . time() . '_' . rand(100, 999) . '.' . $extension;

                // Run resume parser on the temporary file before moving it (temporary file is always readable in /tmp)
                $parseResult = [];
                if ($docType === 'RESUME') {
                    require_once __DIR__ . '/../includes/resume_parser.php';
                    try {
                        $parseResult = ResumeSkillParser::processAndSaveTrainerResume($trainerId, $file['tmp_name']);
                    } catch (\Throwable $e) {
                        error_log("Resume parsing warning: " . $e->getMessage());
                    }
                }

                // Universal Cloud/Local storage upload (works across read-only Vercel/serverless and local XAMPP)
                $uploadRes = uploadFileToCloudOrLocal($file['tmp_name'], $fileName, 'documents', $file['type'] ?? 'application/pdf');

                if ($uploadRes['success']) {
                    $finalFileUrl = $uploadRes['url'];

                    $docCol = getCollection("Document");
                    if ($docCol) {
                        $docCol->insertOne([
                            'trainerId' => $trainerId,
                            'title' => $title,
                            'type' => $docType,
                            'fileUrl' => $finalFileUrl,
                            'originalName' => $file['name'],
                            'fileSize' => $file['size'],
                            'mimeType' => $file['type'] ?? 'application/pdf',
                            'status' => 'VERIFIED',
                            'uploadedAt' => new MongoDB\BSON\UTCDateTime(),
                            'uploadedBy' => $currentUser['id']
                        ]);
                    }

                    // Also update trainer's resumeUrl if type is RESUME and run AI/heuristic Skill Extraction
                    if ($docType === 'RESUME' && $trainerCol) {
                        $trainerCol->updateOne(
                            ['_id' => new MongoDB\BSON\ObjectId($trainerId)],
                            ['$set' => [
                                'resumeUrl' => $finalFileUrl,
                                'updatedAt' => new MongoDB\BSON\UTCDateTime()
                            ]]
                        );

                        if (!empty($parseResult['skills'])) {
                            $_SESSION['resume_skills_extracted'] = count($parseResult['skills']);
                            $_SESSION['extracted_skills_list'] = array_column($parseResult['skills'], 'name');
                        }
                    }
                } else {
                    $_SESSION['upload_error'] = $uploadRes['error'] ?? "Failed to save uploaded document to storage.";
                }
            } else {
                $_SESSION['upload_error'] = "Invalid file type. Allowed formats: PDF, DOC, DOCX, PNG, JPG, JPEG.";
            }
        }
    }
}

$redirectUrl = $_SERVER['HTTP_REFERER'] ?? ($isAdminOrStaff ? '/admin/trainer-view.php?id=' . $trainerId : '/trainer/documents.php');
while (ob_get_level()) { ob_end_clean(); }
header("Location: " . $redirectUrl);
exit();


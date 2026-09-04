<?php
// actions/upload-document.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

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
                $uploadDir = __DIR__ . '/../public/uploads/documents/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $fileName = 'doc_' . $trainerId . '_' . time() . '_' . rand(100, 999) . '.' . $extension;
                $targetPath = $uploadDir . $fileName;

                if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                    $docCol = getCollection("Document");
                    if ($docCol) {
                        $docCol->insertOne([
                            'trainerId' => $trainerId,
                            'title' => $title,
                            'type' => $docType,
                            'fileUrl' => '/public/uploads/documents/' . $fileName,
                            'originalName' => $file['name'],
                            'fileSize' => $file['size'],
                            'mimeType' => $file['type'],
                            'status' => 'VERIFIED',
                            'uploadedAt' => new MongoDB\BSON\UTCDateTime(),
                            'uploadedBy' => $currentUser['id']
                        ]);
                    }

                    // Also update trainer's resumeUrl if type is RESUME and run AI/heuristic Skill Extraction
                    if ($docType === 'RESUME' && $trainerCol) {
                        require_once __DIR__ . '/../includes/resume_parser.php';
                        $parseResult = ResumeSkillParser::processAndSaveTrainerResume($trainerId, $targetPath);

                        $trainerCol->updateOne(
                            ['_id' => new MongoDB\BSON\ObjectId($trainerId)],
                            ['$set' => [
                                'resumeUrl' => '/public/uploads/documents/' . $fileName,
                                'updatedAt' => new MongoDB\BSON\UTCDateTime()
                            ]]
                        );

                        if (!empty($parseResult['skills'])) {
                            $_SESSION['resume_skills_extracted'] = count($parseResult['skills']);
                            $_SESSION['extracted_skills_list'] = array_column($parseResult['skills'], 'name');
                        }
                    }
                } else {
                    $_SESSION['upload_error'] = "Failed to move uploaded document to storage.";
                }
            } else {
                $_SESSION['upload_error'] = "Invalid file type. Allowed formats: PDF, DOC, DOCX, PNG, JPG, JPEG.";
            }
        }
    }
}

$redirectUrl = $_SERVER['HTTP_REFERER'] ?? ($isAdminOrStaff ? '/admin/trainer-view.php?id=' . $trainerId : '/trainer/documents.php');
header("Location: " . $redirectUrl);
exit();

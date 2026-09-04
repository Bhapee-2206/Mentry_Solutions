<?php
// actions/update-trainer.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdminOrStaff();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $trainerId = $_POST['trainerId'] ?? '';
    $actionType = $_POST['action_type'] ?? 'update_status';

    if (!empty($trainerId)) {
        $trainerCol = getCollection("Trainer");
        $userCol = getCollection("User");
        $skillCol = getCollection("Skill");
        $expCol = getCollection("Experience");
        $docCol = getCollection("Document");

        $trainer = $trainerCol ? $trainerCol->findOne(['_id' => new MongoDB\BSON\ObjectId($trainerId)]) : null;
        if (!$trainer) {
            header("Location: /admin/trainers.php");
            exit();
        }

        $userId = (string)$trainer['userId'];

        if ($actionType === 'update_status') {
            $status = $_POST['status'] ?? 'APPROVED';
            $rejectionReason = trim($_POST['rejectionReason'] ?? '');

            $trainerCol->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($trainerId)],
                ['$set' => [
                    'status' => $status,
                    'rejectionReason' => $rejectionReason,
                    'updatedAt' => new MongoDB\BSON\UTCDateTime()
                ]]
            );
        } elseif ($actionType === 'edit_profile') {
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $professionalTitle = trim($_POST['professionalTitle'] ?? '');
            $primaryDomain = trim($_POST['primaryDomain'] ?? '');
            $currentCity = trim($_POST['currentCity'] ?? '');
            $currentState = trim($_POST['currentState'] ?? '');
            $totalExperienceYears = (int)($_POST['totalExperienceYears'] ?? 0);
            $collegeExperienceYears = (int)($_POST['collegeExperienceYears'] ?? 0);
            $dailyRateINR = (float)($_POST['dailyRateINR'] ?? 0);
            $travelPreference = trim($_POST['travelPreference'] ?? 'PAN_INDIA');
            $adminNotes = trim($_POST['adminNotes'] ?? '');
            $adminRating = (float)($_POST['adminRating'] ?? 5.0);
            $status = trim($_POST['status'] ?? ($trainer['status'] ?? 'APPROVED'));

            // Update user table
            if ($userCol && !empty($userId)) {
                $userUpdate = [];
                if (!empty($name)) $userUpdate['name'] = $name;
                if (!empty($email)) {
                    $email = strtolower(trim($email));
                    $duplicate = $userCol->findOne([
                        'email' => new MongoDB\BSON\Regex('^' . preg_quote($email) . '$', 'i'),
                        '_id' => ['$ne' => new MongoDB\BSON\ObjectId($userId)]
                    ]);
                    if (!$duplicate) {
                        $userUpdate['email'] = $email;
                    }
                }
                if (!empty($phone)) $userUpdate['phone'] = $phone;
                if (!empty($userUpdate)) {
                    $userUpdate['updatedAt'] = new MongoDB\BSON\UTCDateTime();
                    $userCol->updateOne(['_id' => new MongoDB\BSON\ObjectId($userId)], ['$set' => $userUpdate]);
                }
            }

            // Update trainer document
            $trainerSet = [
                'professionalTitle' => $professionalTitle,
                'primaryDomain' => $primaryDomain,
                'currentCity' => $currentCity,
                'currentState' => $currentState,
                'totalExperienceYears' => $totalExperienceYears,
                'collegeExperienceYears' => $collegeExperienceYears,
                'dailyRateINR' => $dailyRateINR,
                'travelPreference' => $travelPreference,
                'adminNotes' => $adminNotes,
                'adminRating' => $adminRating,
                'status' => $status,
                'updatedAt' => new MongoDB\BSON\UTCDateTime()
            ];
            if (!empty($name)) $trainerSet['name'] = $name;
            if (!empty($email)) $trainerSet['email'] = $email;
            if (!empty($phone)) $trainerSet['phone'] = $phone;

            $trainerCol->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($trainerId)],
                ['$set' => $trainerSet]
            );
        } elseif ($actionType === 'update_admin_notes') {
            $adminNotes = trim($_POST['adminNotes'] ?? '');
            $adminRating = isset($_POST['adminRating']) ? (float)$_POST['adminRating'] : null;
            $setData = [
                'adminNotes' => $adminNotes,
                'updatedAt' => new MongoDB\BSON\UTCDateTime()
            ];
            if ($adminRating !== null) {
                $setData['adminRating'] = $adminRating;
            }
            $trainerCol->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($trainerId)],
                ['$set' => $setData]
            );
        } elseif ($actionType === 'add_skill') {
            $skillName = trim($_POST['skillName'] ?? '');
            $yearsOfExp = (int)($_POST['yearsOfExperience'] ?? 3);
            if (!empty($skillName) && $skillCol) {
                $skillCol->insertOne([
                    'trainerId' => $trainerId,
                    'name' => $skillName,
                    'yearsOfExperience' => $yearsOfExp,
                    'isVerified' => true,
                    'createdAt' => new MongoDB\BSON\UTCDateTime()
                ]);
            }
        } elseif ($actionType === 'delete_skill') {
            $skillId = $_POST['skillId'] ?? '';
            if (!empty($skillId) && $skillCol) {
                try {
                    $skillCol->deleteOne(['_id' => new MongoDB\BSON\ObjectId($skillId)]);
                } catch (Exception $e) {}
            }
        } elseif ($actionType === 'add_experience') {
            $organization = trim($_POST['organization'] ?? '');
            $role = trim($_POST['role'] ?? '');
            $studentsTrained = (int)($_POST['studentsTrained'] ?? 100);
            $startDate = trim($_POST['startDate'] ?? '');
            $endDate = trim($_POST['endDate'] ?? '');
            $description = trim($_POST['description'] ?? '');

            if (!empty($organization) && $expCol) {
                $expCol->insertOne([
                    'trainerId' => $trainerId,
                    'organization' => $organization,
                    'role' => $role,
                    'studentsTrained' => $studentsTrained,
                    'startDate' => !empty($startDate) ? new MongoDB\BSON\UTCDateTime(strtotime($startDate) * 1000) : null,
                    'endDate' => !empty($endDate) ? new MongoDB\BSON\UTCDateTime(strtotime($endDate) * 1000) : null,
                    'description' => $description,
                    'createdAt' => new MongoDB\BSON\UTCDateTime()
                ]);
            }
        } elseif ($actionType === 'delete_experience') {
            $experienceId = $_POST['experienceId'] ?? '';
            if (!empty($experienceId) && $expCol) {
                try {
                    $expCol->deleteOne(['_id' => new MongoDB\BSON\ObjectId($experienceId)]);
                } catch (Exception $e) {}
            }
        } elseif ($actionType === 'delete_document') {
            $docId = $_POST['docId'] ?? '';
            if (!empty($docId) && $docCol) {
                try {
                    $docCol->deleteOne(['_id' => new MongoDB\BSON\ObjectId($docId)]);
                } catch (Exception $e) {}
            }
        }
    }
}

header("Location: " . ($_SERVER['HTTP_REFERER'] ?? '/admin/trainers.php'));
exit();

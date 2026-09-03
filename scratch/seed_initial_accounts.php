<?php
// scratch/seed_initial_accounts.php - Seed 2 Admin and 2 Staff accounts
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

function seedInitialAccounts() {
    $userCol = getCollection("User");
    if (!$userCol) {
        return ["success" => false, "error" => "Database connection unavailable"];
    }

    $accounts = [
        [
            'email' => 'admin@mentry.test',
            'name' => 'Operations Director (Admin 1)',
            'password' => 'admin123',
            'role' => 'ADMIN',
            'phone' => '+91 98450 00001',
            'status' => 'ACTIVE'
        ],
        [
            'email' => 'admin2@mentry.test',
            'name' => 'Lead Administrator (Admin 2)',
            'password' => 'admin123',
            'role' => 'ADMIN',
            'phone' => '+91 98450 00002',
            'status' => 'ACTIVE'
        ],
        [
            'email' => 'staff1@mentry.test',
            'name' => 'Operations Coordinator (Staff 1)',
            'password' => 'staff123',
            'role' => 'STAFF',
            'phone' => '+91 98450 00003',
            'status' => 'ACTIVE'
        ],
        [
            'email' => 'staff2@mentry.test',
            'name' => 'Talent Sourcing Specialist (Staff 2)',
            'password' => 'staff123',
            'role' => 'STAFF',
            'phone' => '+91 98450 00004',
            'status' => 'ACTIVE'
        ]
    ];

    $results = [];

    foreach ($accounts as $acc) {
        $existing = $userCol->findOne(['email' => $acc['email']]);
        if (!$existing) {
            $userCol->insertOne([
                'name' => $acc['name'],
                'email' => $acc['email'],
                'password' => hashPassword($acc['password']),
                'role' => $acc['role'],
                'phone' => $acc['phone'],
                'status' => $acc['status'],
                'avatar' => 'https://avatar.vercel.sh/' . urlencode($acc['name']) . '.png',
                'createdAt' => new MongoDB\BSON\UTCDateTime(),
                'updatedAt' => new MongoDB\BSON\UTCDateTime()
            ]);
            $results[] = "Created: {$acc['name']} ({$acc['email']}) [{$acc['role']}]";
        } else {
            // Update role and password if needed to ensure access
            $userCol->updateOne(
                ['_id' => $existing['_id']],
                ['$set' => [
                    'name' => $acc['name'],
                    'role' => $acc['role'],
                    'status' => 'ACTIVE',
                    'updatedAt' => new MongoDB\BSON\UTCDateTime()
                ]]
            );
            $results[] = "Updated: {$acc['name']} ({$acc['email']}) [{$acc['role']}]";
        }
    }

    return ["success" => true, "details" => $results];
}

// If run from CLI or web directly:
if (php_sapi_name() === 'cli' || !empty($_GET['run'])) {
    $res = seedInitialAccounts();
    echo json_encode($res, JSON_PRETTY_PRINT);
}

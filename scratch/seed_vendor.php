<?php
// scratch/seed_vendor.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

echo "Seeding Vendor demo user & sample requests...\n";

$userCol = getCollection("User");
$reqCol = getCollection("VendorRequest");

if (!$userCol || !$reqCol) {
    die("Database connection failed.\n");
}

// 1. Create or update vendor@mentry.test
$vendorUser = $userCol->findOne(['email' => 'vendor@mentry.test']);
if (!$vendorUser) {
    $insertResult = $userCol->insertOne([
        'name' => 'Rajesh Sharma',
        'organizationName' => 'Apex EdTech Staffing Solutions',
        'organizationType' => 'STAFFING_VENDOR',
        'email' => 'vendor@mentry.test',
        'phone' => '+91 98450 12345',
        'password' => hashPassword('vendor123'),
        'role' => 'VENDOR',
        'city' => 'Bengaluru',
        'state' => 'Karnataka',
        'website' => 'https://apexedtech.example.com',
        'status' => 'ACTIVE',
        'createdAt' => new MongoDB\BSON\UTCDateTime(),
        'updatedAt' => new MongoDB\BSON\UTCDateTime()
    ]);
    $vendorId = (string)$insertResult->getInsertedId();
    echo "Created demo vendor user with ID: $vendorId\n";
} else {
    $vendorId = (string)$vendorUser['_id'];
    echo "Demo vendor user exists with ID: $vendorId\n";
}

// 2. Add sample private requests if none exist for this vendor
$existingReqs = $reqCol->countDocuments(['vendorId' => $vendorId]);
if ($existingReqs === 0) {
    $reqCol->insertOne([
        'vendorId' => $vendorId,
        'vendorName' => 'Apex EdTech Staffing Solutions',
        'vendorContactEmail' => 'vendor@mentry.test',
        'vendorContactPhone' => '+91 98450 12345',
        'title' => '5-Day AWS Cloud Architecture & DevOps Hands-on Campus Bootcamp',
        'institutionName' => 'Dayananda Sagar College of Engineering',
        'domain' => 'Cloud',
        'mode' => 'OFFLINE',
        'city' => 'Bengaluru',
        'state' => 'Karnataka',
        'startDate' => new MongoDB\BSON\UTCDateTime(strtotime('+10 days') * 1000),
        'durationDays' => 5,
        'studentCount' => 140,
        'budgetPerDay' => 10000, // Offered client budget: 10,000/day
        'skillsRequired' => json_encode(['AWS Certified', 'Docker', 'Kubernetes', 'CI/CD Pipelines', 'Terraform']),
        'description' => 'Hands-on AWS infrastructure workshop for 7th semester CSE & ISE students. Need real lab exercises on EC2, VPC, S3, IAM, and automated container deployment.',
        'accommodationDetails' => 'Campus VIP Guest House Suite with meals provided',
        'travelDetails' => 'Local AC cab pickup & drop provided',
        'status' => 'PENDING_ADMIN_REVIEW', // STRICTLY PRIVATE
        'adminContacted' => false,
        'adminNotes' => '',
        'createdAt' => new MongoDB\BSON\UTCDateTime(),
        'updatedAt' => new MongoDB\BSON\UTCDateTime()
    ]);

    $reqCol->insertOne([
        'vendorId' => $vendorId,
        'vendorName' => 'Apex EdTech Staffing Solutions',
        'vendorContactEmail' => 'vendor@mentry.test',
        'vendorContactPhone' => '+91 98450 12345',
        'title' => '3-Day Placement Specific DSA & Problem Solving in Java',
        'institutionName' => 'PES University',
        'domain' => 'Programming',
        'mode' => 'OFFLINE',
        'city' => 'Bengaluru',
        'state' => 'Karnataka',
        'startDate' => new MongoDB\BSON\UTCDateTime(strtotime('+14 days') * 1000),
        'durationDays' => 3,
        'studentCount' => 220,
        'budgetPerDay' => 9000,
        'skillsRequired' => json_encode(['Java 17', 'Data Structures', 'LeetCode Mediums', 'Dynamic Programming']),
        'description' => 'Intensive placement training focusing on FAANG and Tier-1 product company coding interview questions.',
        'accommodationDetails' => 'Provided on-campus',
        'travelDetails' => 'Reimbursed on actuals',
        'status' => 'UNDER_DISCUSSION',
        'adminContacted' => true,
        'adminNotes' => 'Spoke with coordinator Rajesh, confirmed lab timings 9:00 AM to 5:00 PM.',
        'createdAt' => new MongoDB\BSON\UTCDateTime(),
        'updatedAt' => new MongoDB\BSON\UTCDateTime()
    ]);

    echo "Inserted 2 sample private vendor requirements.\n";
} else {
    echo "Vendor already has $existingReqs requirements.\n";
}

echo "Seeding completed successfully.\n";

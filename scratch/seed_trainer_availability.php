<?php
// scratch/seed_trainer_availability.php
require_once __DIR__ . '/../includes/db.php';

echo "Updating trainer demo availability statuses...\n";

$trainerCol = getCollection("Trainer");
if (!$trainerCol) {
    die("Database connection failed.\n");
}

$trainers = $trainerCol->find()->toArray();
$count = count($trainers);
echo "Found $count trainers in database.\n";

$schedules = [
    [
        'status' => 'AVAILABLE_NOW',
        'fromDate' => null,
        'notes' => 'Ready for immediate on-campus and virtual bootcamps across South India',
        'mobility' => 'PAN_INDIA'
    ],
    [
        'status' => 'FREE_FROM_DATE',
        'fromDate' => new MongoDB\BSON\UTCDateTime(strtotime('+8 days') * 1000),
        'notes' => 'Finishing corporate placement sprint; free for new batches starting next week',
        'mobility' => 'PAN_INDIA'
    ],
    [
        'status' => 'BUSY_ON_ASSIGNMENT',
        'fromDate' => new MongoDB\BSON\UTCDateTime(strtotime('+12 days') * 1000),
        'notes' => 'Currently delivering on-campus Java Full Stack workshop; free after completion',
        'mobility' => 'STATE_ONLY'
    ],
    [
        'status' => 'AVAILABLE_NOW',
        'fromDate' => null,
        'notes' => 'Available for 5-day placement problem solving & DSA sprints',
        'mobility' => 'PAN_INDIA'
    ]
];

$i = 0;
foreach ($trainers as $t) {
    $sched = $schedules[$i % count($schedules)];
    $trainerCol->updateOne(
        ['_id' => $t['_id']],
        ['$set' => [
            'availabilityStatus' => $sched['status'],
            'availableFromDate' => $sched['fromDate'],
            'availabilityNotes' => $sched['notes'],
            'travelPreference' => $sched['mobility'],
            'availabilityUpdatedAt' => new MongoDB\BSON\UTCDateTime()
        ]]
    );
    echo "Updated trainer " . (string)$t['_id'] . " to " . $sched['status'] . "\n";
    $i++;
}

echo "All trainers seeded with availability schedules successfully.\n";

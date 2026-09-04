<?php
// scripts/migrate_to_atlas.php - Full One-Click Sync from Local JSON Collections to MongoDB Atlas
require_once __DIR__ . '/../vendor/autoload.php';

header('Content-Type: text/plain; charset=utf-8');

$envPath = __DIR__ . '/../.env';
$uri = '';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (strpos($line, 'DATABASE_URL=') === 0) {
            $uri = trim(substr($line, strlen('DATABASE_URL=')), '"\'');
        }
    }
}

if (empty($uri)) {
    echo "ERROR: DATABASE_URL not found in .env\n";
    exit(1);
}

echo "========================================================\n";
echo " MENTRY SOLUTIONS - MONGODB ATLAS SYNCHRONIZATION ENGINE \n";
echo "========================================================\n\n";

$caFile = __DIR__ . '/../includes/cacert.pem';
$driverOpts = [
    'serverSelectionTimeoutMS' => 8000,
    'tls' => true,
    'tlsAllowInvalidCertificates' => true
];
if (file_exists($caFile)) {
    $driverOpts['tlsCAFile'] = realpath($caFile);
}

try {
    echo "Connecting to MongoDB Atlas Cluster...\n";
    $client = new MongoDB\Client($uri, [], $driverOpts);
    $cmd = new MongoDB\Driver\Command(['ping' => 1]);
    $client->getManager()->executeCommand('mentry_solutions', $cmd);
    echo "Connected successfully to MongoDB Atlas!\n\n";

    $db = $client->selectDatabase('mentry_solutions');
    $dataDir = __DIR__ . '/../data/collections';
    $files = glob($dataDir . '/*.json');

    $totalSynced = 0;

    foreach ($files as $file) {
        $colName = basename($file, '.json');
        $raw = file_get_contents($file);
        $docs = json_decode($raw, true);

        if (!is_array($docs) || empty($docs)) {
            echo "Skipping $colName (empty or invalid JSON)\n";
            continue;
        }

        $col = $db->selectCollection($colName);
        $count = 0;

        foreach ($docs as $doc) {
            if (!isset($doc['_id'])) {
                continue;
            }

            // Convert string _id to ObjectId if valid 24-char hex
            $filterId = $doc['_id'];
            if (is_string($filterId) && preg_match('/^[a-f0-9]{24}$/i', $filterId)) {
                try {
                    $doc['_id'] = new MongoDB\BSON\ObjectId($filterId);
                } catch (\Throwable $e) {}
            }

            // Upsert document
            $col->replaceOne(
                ['_id' => $doc['_id']],
                $doc,
                ['upsert' => true]
            );
            $count++;
            $totalSynced++;
        }

        echo "Synced $colName: $count documents successfully into Atlas\n";
    }

    echo "\n========================================================\n";
    echo "All data synchronized to MongoDB Atlas: $totalSynced documents!\n";
    echo "========================================================\n";

} catch (\Throwable $e) {
    echo "FAILED to connect/sync to MongoDB Atlas:\n";
    echo $e->getMessage() . "\n\n";
    echo "ACTION REQUIRED IN MONGODB ATLAS DASHBOARD:\n";
    echo "1. Go to https://cloud.mongodb.com\n";
    echo "2. Navigate to 'Network Access' (under Security)\n";
    echo "3. Click 'Add IP Address'\n";
    echo "4. Add '0.0.0.0/0' (Allow Access from Anywhere) or add your current public IP.\n";
    echo "5. Once added, re-run this script (visit http://localhost:8000/scripts/migrate_to_atlas.php) to sync everything instantly!\n";
}

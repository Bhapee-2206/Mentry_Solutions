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
$uriOptions = [
    'serverSelectionTimeoutMS' => 8000,
    'tls' => true,
    'tlsAllowInvalidCertificates' => true
];
$driverOpts = [];
if (file_exists($caFile)) {
    $uriOptions['tlsCAFile'] = realpath($caFile);
    $driverOpts['ca_file'] = realpath($caFile);
}

// Check if running in browser or CLI
$isCli = (php_sapi_name() === 'cli');
if (!$isCli) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>MongoDB Atlas Sync</title>';
    echo '<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">';
    echo '</head><body class="bg-gray-900 text-gray-100 min-h-screen p-8"><div class="max-w-3xl mx-auto bg-gray-800 p-8 rounded-2xl shadow-2xl border border-gray-700">';
    echo '<h1 class="text-2xl font-bold mb-4 flex items-center gap-2"><span class="text-emerald-400">⚡ Mentry Solutions</span> &mdash; Atlas Sync Engine</h1>';
} else {
    echo "========================================================\n";
    echo " MENTRY SOLUTIONS - MONGODB ATLAS SYNCHRONIZATION ENGINE \n";
    echo "========================================================\n\n";
}

try {
    if (!$isCli) echo '<p class="text-yellow-300 font-mono mb-2">Connecting to MongoDB Atlas Cluster...</p>';
    else echo "Connecting to MongoDB Atlas Cluster...\n";

    $dbName = 'mentry';
    if (preg_match('#cluster0[^\/]*\/([a-zA-Z0-9_\-]+)(\?|$)#', $uri, $m)) {
        $dbName = $m[1];
    }
    $client = new MongoDB\Client($uri, $uriOptions, $driverOpts);
    $cmd = new MongoDB\Driver\Command(['ping' => 1]);
    $client->getManager()->executeCommand($dbName, $cmd);

    if (!$isCli) {
        echo '<div class="p-4 bg-emerald-900/50 border border-emerald-500/50 rounded-xl text-emerald-300 font-semibold mb-6">';
        echo " Connected successfully to MongoDB Atlas database: <strong class='text-white underline'>$dbName</strong>";
        echo '</div><div class="space-y-2 font-mono text-sm">';
    } else {
        echo "Connected successfully to MongoDB Atlas ($dbName)!\n\n";
    }

    $db = $client->selectDatabase($dbName);
    $dataDir = __DIR__ . '/../data/collections';
    $files = glob($dataDir . '/*.json');

    $totalSynced = 0;

    foreach ($files as $file) {
        $colName = basename($file, '.json');
        $raw = file_get_contents($file);
        $docs = json_decode($raw, true);

        if (!is_array($docs) || empty($docs)) {
            if (!$isCli) echo "<div class='text-gray-500'>Skipping $colName (empty)</div>";
            else echo "Skipping $colName (empty or invalid JSON)\n";
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

        if (!$isCli) {
            echo "<div class='text-emerald-400'>&#10003; Synced <strong class='text-white'>$colName</strong>: $count documents</div>";
        } else {
            echo "Synced $colName: $count documents successfully into Atlas\n";
        }
    }

    if (!$isCli) {
        echo '</div>';
        echo "<div class='mt-8 p-6 bg-gradient-to-r from-emerald-600 to-teal-600 rounded-xl text-white font-bold text-center text-lg shadow-lg'>";
        echo "&#127881; SUCCESS! All $totalSynced documents have been populated into MongoDB Atlas database '$dbName'!";
        echo "</div>";
        echo "<div class='mt-4 text-center'><a href='/login.php' class='text-indigo-400 underline hover:text-indigo-300'>Return to Sign In</a></div>";
        echo "</div></body></html>";
    } else {
        echo "\n========================================================\n";
        echo "All data synchronized to MongoDB Atlas: $totalSynced documents!\n";
        echo "========================================================\n";
    }

} catch (\Throwable $e) {
    if (!$isCli) {
        echo '<div class="p-4 bg-red-900/60 border border-red-500/80 rounded-xl text-red-200 mb-6">';
        echo '<p class="font-bold text-lg mb-2">&#9888; Connection Failed to MongoDB Atlas</p>';
        echo '<p class="font-mono text-xs bg-red-950/80 p-3 rounded border border-red-800 text-red-300 break-all mb-4">' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<div class="bg-gray-900/80 p-4 rounded-lg border border-gray-700 text-sm space-y-2 text-gray-200">';
        echo '<p class="font-bold text-amber-400 uppercase tracking-wide">Action Required in MongoDB Atlas:</p>';
        echo '<p>Atlas rejected the connection with TLS alert internal error (Alert 80) because your IP address is not whitelisted yet in the Atlas Firewall.</p>';
        echo '<ol class="list-decimal list-inside space-y-1 text-gray-300 mt-2">';
        echo '<li>Open <a href="https://cloud.mongodb.com" target="_blank" class="text-blue-400 underline font-semibold">MongoDB Atlas Cloud Dashboard</a></li>';
        echo '<li>In the left sidebar under <strong>Security</strong>, click <strong>Network Access</strong></li>';
        echo '<li>Click the green <strong>+ Add IP Address</strong> button</li>';
        echo '<li>Click <strong>ALLOW ACCESS FROM ANYWHERE</strong> (adds <code class="bg-gray-800 px-2 py-0.5 rounded text-amber-300">0.0.0.0/0</code>) or enter your current IP, then click <strong>Confirm</strong></li>';
        echo '<li>Wait 15-30 seconds for Atlas to update status to "Active", then <a href="javascript:location.reload()" class="text-emerald-400 underline font-bold">click here to refresh and sync</a></li>';
        echo '</ol>';
        echo '</div></div></div></body></html>';
    } else {
        echo "FAILED to connect/sync to MongoDB Atlas:\n";
        echo $e->getMessage() . "\n\n";
        echo "ACTION REQUIRED IN MONGODB ATLAS DASHBOARD:\n";
        echo "1. Go to https://cloud.mongodb.com\n";
        echo "2. Navigate to 'Network Access' (under Security)\n";
        echo "3. Click 'Add IP Address'\n";
        echo "4. Add '0.0.0.0/0' (Allow Access from Anywhere) or add your current public IP.\n";
        echo "5. Once added, re-run this script to sync everything instantly!\n";
    }
}

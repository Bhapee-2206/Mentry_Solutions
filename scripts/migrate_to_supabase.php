<?php
/**
 * scripts/migrate_to_supabase.php
 * Automated Synchronization & Migration Engine for Supabase Cloud Database
 */

$envFile = __DIR__ . '/../.env';
$env = [];
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $val) = explode('=', $line, 2);
            $env[trim($key)] = trim(trim($val), '"\'');
        }
    }
}

// Handle Form POST to update Supabase URL
$message = '';
$messageType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['supabase_url'])) {
    $input = trim($_POST['supabase_url']);
    
    // Check if user accidentally pasted an API key (starts with sb_secret_ or sb_publishable_)
    if (strpos($input, 'sb_secret_') === 0 || strpos($input, 'sb_publishable_') === 0) {
        $message = "You entered your API Key instead of your Project URL. Your API key is already saved! Please check your browser address bar or Project Settings > API for the Project URL (e.g., https://abcdefghijklmnopqrst.supabase.co).";
        $messageType = "error";
    } else {
        $extractedRef = '';
        // Check if user pasted full dashboard URL: https://supabase.com/dashboard/project/abcdefghijklmnopqrst/...
        if (preg_match('#supabase\.com/dashboard/project/([a-z0-9]+)#i', $input, $m)) {
            $extractedRef = $m[1];
            $newUrl = "https://" . $extractedRef . ".supabase.co";
        } elseif (preg_match('#^https?://([a-z0-9\-]+)\.supabase\.co#i', $input, $m)) {
            $newUrl = rtrim($input, '/');
        } elseif (preg_match('/^[a-z0-9]{15,30}$/i', $input)) {
            // User entered just the project reference code
            $newUrl = "https://" . strtolower($input) . ".supabase.co";
        } else {
            $newUrl = rtrim($input, '/');
        }

        if (!empty($newUrl)) {
            // Update .env file
            $envContent = file_get_contents($envFile);
            if (strpos($envContent, 'SUPABASE_URL=') !== false) {
                $envContent = preg_replace('/SUPABASE_URL=.*$/m', 'SUPABASE_URL="' . $newUrl . '"', $envContent);
            } else {
                $envContent .= "\nSUPABASE_URL=\"" . $newUrl . "\"\n";
            }
            file_put_contents($envFile, $envContent);
            $env['SUPABASE_URL'] = $newUrl;
            $message = "Supabase Project URL updated successfully to: " . htmlspecialchars($newUrl);
            $messageType = "success";
        }
    }
}

$supabaseUrl = rtrim($env['SUPABASE_URL'] ?? getenv('SUPABASE_URL') ?: '', '/');
$supabaseKey = $env['SUPABASE_KEY'] ?? getenv('SUPABASE_KEY') ?: '';
$dbName = $env['SUPABASE_DB_NAME'] ?? getenv('SUPABASE_DB_NAME') ?: 'postgres';
$dbPass = $env['SUPABASE_DB_PASS'] ?? getenv('SUPABASE_DB_PASS') ?: '';

$dataDir = __DIR__ . '/../data/collections';
$jsonFiles = glob($dataDir . '/*.json');

// Helper to execute Supabase PostgREST requests
function supabaseRequest($url, $method = 'GET', $data = null, $key = '') {
    $ch = curl_init($url);
    $headers = [
        'apikey: ' . $key,
        'Authorization: Bearer ' . $key,
        'Content-Type: application/json',
        'Prefer: resolution=merge-duplicates,return=minimal'
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($data) ? $data : json_encode($data));
        }
    } elseif ($method !== 'GET') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($data) ? $data : json_encode($data));
        }
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    return ['code' => $httpCode, 'response' => $response, 'error' => $error];
}

$syncTriggered = isset($_GET['action']) && $_GET['action'] === 'sync';
$syncResults = [];
$totalSynced = 0;
$syncError = null;

if ($syncTriggered && !empty($supabaseUrl) && !empty($supabaseKey)) {
    // 1. Test connection to Supabase
    $test = supabaseRequest($supabaseUrl . '/rest/v1/', 'GET', null, $supabaseKey);
    if ($test['code'] === 0) {
        $syncError = "Could not reach Supabase at $supabaseUrl. cURL Error: " . $test['error'];
    } else {
        // Prepare sync for each collection
        foreach ($jsonFiles as $file) {
            $colName = basename($file, '.json');
            $raw = file_get_contents($file);
            $docs = json_decode($raw, true);

            if (!is_array($docs) || empty($docs)) {
                $syncResults[$colName] = ['count' => 0, 'status' => 'skipped'];
                continue;
            }

            // Sync to generic document store table: mentry_documents
            $batchPayload = [];
            foreach ($docs as $doc) {
                $docId = $doc['_id'] ?? (string)($doc['id'] ?? uniqid());
                $batchPayload[] = [
                    'collection' => $colName,
                    'id' => (string)$docId,
                    'data' => $doc,
                    'updated_at' => date('c')
                ];
            }

            // Attempt batch upsert
            $upsertUrl = $supabaseUrl . '/rest/v1/mentry_documents?on_conflict=collection,id';
            $res = supabaseRequest($upsertUrl, 'POST', $batchPayload, $supabaseKey);

            if ($res['code'] >= 200 && $res['code'] < 300) {
                $count = count($batchPayload);
                $totalSynced += $count;
                $syncResults[$colName] = ['count' => $count, 'status' => 'success'];
            } else {
                $syncResults[$colName] = [
                    'count' => 0,
                    'status' => 'error',
                    'httpCode' => $res['code'],
                    'message' => $res['response']
                ];
            }
        }
    }

    if (isset($_GET['format']) && $_GET['format'] === 'json') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $syncError === null,
            'totalSynced' => $totalSynced,
            'error' => $syncError,
            'results' => $syncResults
        ], JSON_PRETTY_PRINT);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supabase Cloud Database Synchronization | Mentry Solutions</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        pre, code { font-family: 'JetBrains Mono', monospace; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen py-10 px-4">
    <div class="max-w-4xl mx-auto space-y-6">

        <!-- Header -->
        <div class="bg-gradient-to-r from-emerald-900/40 via-teal-900/30 to-slate-900 border border-emerald-500/30 rounded-3xl p-8 shadow-2xl relative overflow-hidden">
            <div class="absolute -right-12 -top-12 w-48 h-48 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>
            <div class="flex items-center justify-between flex-wrap gap-4 relative z-10">
                <div class="flex items-center gap-4">
                    <div class="w-14 h-14 rounded-2xl bg-emerald-500/20 border border-emerald-400/40 flex items-center justify-center text-emerald-400 shadow-inner">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"></path>
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">Supabase Sync Engine</h1>
                        <p class="text-emerald-400 font-medium text-sm mt-0.5">Database: <span class="text-white font-mono font-bold"><?= htmlspecialchars($dbName) ?></span> &bull; Production Storage</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 mr-2 animate-pulse"></span>
                        Key Loaded: <?= substr($supabaseKey, 0, 14) ?>...
                    </span>
                </div>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="p-4 rounded-2xl <?= $messageType === 'error' ? 'bg-red-950/80 border border-red-500/80 text-red-200' : 'bg-emerald-950/60 border border-emerald-500/50 text-emerald-300' ?> font-medium text-sm">
                <?= $messageType === 'error' ? '&#9888; ' : '&#10003; ' ?><?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- Setup Step 1: Project URL -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-bold text-white flex items-center gap-2">
                    <span class="w-7 h-7 rounded-lg bg-emerald-500/20 text-emerald-400 font-mono text-sm flex items-center justify-center">1</span>
                    Supabase Project Connection URL
                </h2>
                <?php if (!empty($supabaseUrl)): ?>
                    <span class="text-xs text-emerald-400 bg-emerald-500/10 px-2.5 py-1 rounded-md border border-emerald-500/20 font-mono font-medium">Configured</span>
                <?php else: ?>
                    <span class="text-xs text-amber-400 bg-amber-500/10 px-2.5 py-1 rounded-md border border-amber-500/20 font-mono font-medium">URL Needed</span>
                <?php endif; ?>
            </div>

            <form method="POST" class="space-y-3">
                <div>
                    <label class="block text-xs font-medium text-slate-400 uppercase tracking-wider mb-1.5">Supabase Project URL or Reference ID</label>
                    <div class="flex gap-2">
                        <input type="text" name="supabase_url" value="<?= htmlspecialchars($supabaseUrl) ?>" placeholder="https://xyzprojectref.supabase.co or xyzprojectref" 
                            class="flex-1 bg-slate-950 border border-slate-700 rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-emerald-500 font-mono">
                        <button type="submit" class="bg-emerald-600 hover:bg-emerald-500 text-white px-5 py-2.5 rounded-xl font-semibold text-sm transition-all shadow-lg shadow-emerald-900/30">
                            Save URL
                        </button>
                    </div>
                </div>
                <p class="text-xs text-slate-400">
                    Find this in your Supabase Dashboard: <span class="text-slate-300 font-medium">Project Settings &rarr; API &rarr; Project URL</span> (e.g. <code class="text-emerald-400">https://xxxx.supabase.co</code>).
                </p>
            </form>
        </div>

        <!-- Setup Step 2: Supabase SQL Table Creator -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-bold text-white flex items-center gap-2">
                    <span class="w-7 h-7 rounded-lg bg-teal-500/20 text-teal-400 font-mono text-sm flex items-center justify-center">2</span>
                    Initialize Table in Supabase SQL Editor
                </h2>
                <button onclick="copySQL()" id="copyBtn" class="text-xs bg-slate-800 hover:bg-slate-700 text-slate-200 px-3 py-1.5 rounded-lg border border-slate-700 transition">
                    Copy SQL Query
                </button>
            </div>
            <p class="text-sm text-slate-300">
                Go to <a href="https://supabase.com/dashboard" target="_blank" class="text-emerald-400 underline font-semibold">Supabase Dashboard</a> &rarr; <strong>SQL Editor</strong> &rarr; <strong>New Query</strong>, paste this snippet and click <strong>Run</strong>:
            </p>
            <div class="relative">
                <pre id="sqlSnippet" class="bg-slate-950 text-emerald-300 text-xs p-4 rounded-xl border border-slate-800 overflow-x-auto leading-relaxed">CREATE TABLE IF NOT EXISTS public.mentry_documents (
    collection VARCHAR(100) NOT NULL,
    id VARCHAR(100) NOT NULL,
    data JSONB NOT NULL,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW(),
    PRIMARY KEY (collection, id)
);

CREATE INDEX IF NOT EXISTS idx_mentry_collection ON public.mentry_documents(collection);

-- Enable Row Level Security & Allow Service Role / Backend access
ALTER TABLE public.mentry_documents ENABLE ROW LEVEL SECURITY;
CREATE POLICY "Allow all access to service role" ON public.mentry_documents
    FOR ALL USING (true) WITH CHECK (true);</pre>
            </div>
        </div>

        <!-- Setup Step 3: Run Sync -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl space-y-4">
            <div class="flex items-center justify-between flex-wrap gap-3">
                <h2 class="text-lg font-bold text-white flex items-center gap-2">
                    <span class="w-7 h-7 rounded-lg bg-indigo-500/20 text-indigo-400 font-mono text-sm flex items-center justify-center">3</span>
                    Sync Local Data into Supabase
                </h2>
                <?php if (!empty($supabaseUrl)): ?>
                    <a href="?action=sync" class="bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white px-6 py-2.5 rounded-xl font-bold text-sm shadow-lg shadow-emerald-900/40 transition-all flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        Start Supabase Synchronization
                    </a>
                <?php else: ?>
                    <button disabled class="bg-slate-800 text-slate-500 px-6 py-2.5 rounded-xl font-bold text-sm cursor-not-allowed">
                        Enter Project URL in Step 1 First
                    </button>
                <?php endif; ?>
            </div>

            <?php if ($syncError): ?>
                <div class="p-4 bg-red-950/70 border border-red-500/60 rounded-xl text-red-200 text-sm">
                    <strong>Error:</strong> <?= htmlspecialchars($syncError) ?>
                </div>
            <?php endif; ?>

            <?php if ($syncTriggered && !empty($syncResults)): ?>
                <div class="border border-slate-800 rounded-xl overflow-hidden divide-y divide-slate-800 mt-4">
                    <div class="bg-slate-950 px-4 py-3 text-xs font-bold uppercase tracking-wider text-slate-400 flex justify-between">
                        <span>Collection Name</span>
                        <span>Sync Status</span>
                    </div>
                    <?php foreach ($syncResults as $name => $res): ?>
                        <div class="px-4 py-3 flex items-center justify-between text-sm hover:bg-slate-800/40">
                            <span class="font-mono font-medium text-slate-200"><?= htmlspecialchars($name) ?></span>
                            <?php if ($res['status'] === 'success'): ?>
                                <span class="inline-flex items-center text-xs font-semibold text-emerald-400 bg-emerald-500/10 px-2.5 py-1 rounded border border-emerald-500/20">
                                    &#10003; <?= $res['count'] ?> documents synced
                                </span>
                            <?php elseif ($res['status'] === 'skipped'): ?>
                                <span class="text-xs text-slate-500">Skipped (empty)</span>
                            <?php else: ?>
                                <span class="text-xs text-red-400 bg-red-500/10 px-2.5 py-1 rounded border border-red-500/20">
                                    Table missing (Run SQL in Step 2)
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($totalSynced > 0): ?>
                    <div class="p-4 bg-emerald-950/80 border border-emerald-500/80 rounded-xl text-emerald-300 font-bold text-center">
                        &#127881; SUCCESS! Synchronized <?= $totalSynced ?> documents into Supabase!
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <div class="pt-4 border-t border-slate-800 flex items-center justify-between text-xs text-slate-400">
                <span>Available Local Collections: <?= count($jsonFiles) ?></span>
                <a href="/login.php" class="text-emerald-400 underline hover:text-emerald-300 font-medium">Back to Application</a>
            </div>
        </div>

    </div>

    <script>
        function copySQL() {
            const sql = document.getElementById('sqlSnippet').innerText;
            navigator.clipboard.writeText(sql).then(() => {
                const btn = document.getElementById('copyBtn');
                btn.innerText = 'Copied!';
                btn.classList.add('bg-emerald-700', 'text-white');
                setTimeout(() => {
                    btn.innerText = 'Copy SQL Query';
                    btn.classList.remove('bg-emerald-700', 'text-white');
                }, 2000);
            });
        }
    </script>
</body>
</html>

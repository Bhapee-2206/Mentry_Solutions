<?php
// admin/downloads.php - Production Download Analytics & Real-Time Tracking Dashboard
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireAdminOrStaff();

$pageTitle = 'Download Analytics & Logs';
$currentUser = getCurrentUser();

$logCol = getCollection("DownloadLog");
$trainerCol = getCollection("Trainer");

// -------------------------------------------------------------
// Filters & Query Parameters
// -------------------------------------------------------------
$search = trim($_GET['q'] ?? '');
$filterDate = trim($_GET['date_range'] ?? '30_days');
$filterType = trim($_GET['type'] ?? '');
$filterTrainer = trim($_GET['trainer'] ?? '');
$filterRole = trim($_GET['role'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$skip = ($page - 1) * $limit;

// -------------------------------------------------------------
// Date Ranges Calculation
// -------------------------------------------------------------
$now = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
$todayStart = (clone $now)->setTime(0, 0, 0);
$weekStart = (clone $now)->modify('-7 days')->setTime(0, 0, 0);
$monthStart = (clone $now)->modify('first day of this month')->setTime(0, 0, 0);
$thirtyDaysAgo = (clone $now)->modify('-30 days')->setTime(0, 0, 0);

// -------------------------------------------------------------
// Top Summary Metrics
// -------------------------------------------------------------
$totalDownloads = 0;
$todayDownloads = 0;
$weekDownloads = 0;
$monthDownloads = 0;
$uniqueUsersCount = 0;
$distinctDocsCount = 0;
$profileDownloadsCount = 0;
$docDownloadsCount = 0;

if ($logCol) {
    $allSuccessfulLogs = $logCol->find(['success' => true])->toArray();
    $totalDownloads = count($allSuccessfulLogs);

    $uniqueUsers = [];
    $uniqueDocs = [];

    foreach ($allSuccessfulLogs as $l) {
        $logTime = 0;
        if (isset($l['createdAt'])) {
            if ($l['createdAt'] instanceof MongoDB\BSON\UTCDateTime) {
                $logTime = (int)($l['createdAt']->toDateTime()->getTimestamp());
            } elseif (is_numeric($l['createdAt'])) {
                $logTime = (int)$l['createdAt'];
            } elseif (is_string($l['createdAt'])) {
                $logTime = strtotime($l['createdAt']);
            }
        }

        if ($logTime >= $todayStart->getTimestamp()) {
            $todayDownloads++;
        }
        if ($logTime >= $weekStart->getTimestamp()) {
            $weekDownloads++;
        }
        if ($logTime >= $monthStart->getTimestamp()) {
            $monthDownloads++;
        }

        if (!empty($l['downloadedByUserId'])) {
            $uniqueUsers[$l['downloadedByUserId']] = true;
        }
        if (!empty($l['documentId']) || !empty($l['fileName'])) {
            $dKey = !empty($l['documentId']) ? $l['documentId'] : $l['fileName'];
            $uniqueDocs[$dKey] = true;
        }

        $dType = strtolower($l['documentType'] ?? '');
        if ($dType === 'profile' || $dType === 'photo') {
            $profileDownloadsCount++;
        } else {
            $docDownloadsCount++;
        }
    }

    $uniqueUsersCount = count($uniqueUsers);
    $distinctDocsCount = count($uniqueDocs);
}

// -------------------------------------------------------------
// 30-Day Activity Breakdown for Chart
// -------------------------------------------------------------
$dailyCounts = [];
for ($i = 29; $i >= 0; $i--) {
    $d = (clone $now)->modify("-{$i} days");
    $dayKey = $d->format('Y-m-d');
    $dailyCounts[$dayKey] = [
        'label' => $d->format('d M'),
        'count' => 0
    ];
}

if ($logCol) {
    foreach ($allSuccessfulLogs as $l) {
        $logDate = '';
        if (isset($l['createdAt'])) {
            if ($l['createdAt'] instanceof MongoDB\BSON\UTCDateTime) {
                $logDate = $l['createdAt']->toDateTime()->setTimezone(new DateTimeZone('Asia/Kolkata'))->format('Y-m-d');
            } elseif (is_numeric($l['createdAt'])) {
                $logDate = date('Y-m-d', (int)$l['createdAt']);
            } elseif (is_string($l['createdAt'])) {
                $logDate = date('Y-m-d', strtotime($l['createdAt']));
            }
        }
        if (isset($dailyCounts[$logDate])) {
            $dailyCounts[$logDate]['count']++;
        }
    }
}

$maxDailyCount = 1;
foreach ($dailyCounts as $dayData) {
    if ($dayData['count'] > $maxDailyCount) {
        $maxDailyCount = $dayData['count'];
    }
}

// -------------------------------------------------------------
// Top Downloaded Documents Aggregation
// -------------------------------------------------------------
$topDocsMap = [];
if ($logCol) {
    foreach ($allSuccessfulLogs as $l) {
        $idKey = !empty($l['documentId']) ? $l['documentId'] : ($l['fileName'] ?? 'unknown');
        if (!isset($topDocsMap[$idKey])) {
            $topDocsMap[$idKey] = [
                'documentId' => $l['documentId'] ?? '',
                'fileName' => $l['fileName'] ?? 'Document',
                'documentType' => $l['documentType'] ?? 'Document',
                'trainerName' => $l['trainerName'] ?? 'N/A',
                'count' => 0,
                'lastDownloaded' => 0
            ];
        }
        $topDocsMap[$idKey]['count']++;

        $timeVal = 0;
        if (isset($l['createdAt'])) {
            if ($l['createdAt'] instanceof MongoDB\BSON\UTCDateTime) {
                $timeVal = $l['createdAt']->toDateTime()->getTimestamp();
            } else {
                $timeVal = is_numeric($l['createdAt']) ? (int)$l['createdAt'] : strtotime((string)$l['createdAt']);
            }
        }
        if ($timeVal > $topDocsMap[$idKey]['lastDownloaded']) {
            $topDocsMap[$idKey]['lastDownloaded'] = $timeVal;
        }
    }
}

usort($topDocsMap, function($a, $b) {
    return $b['count'] <=> $a['count'];
});
$topDocs = array_slice($topDocsMap, 0, 10);

// -------------------------------------------------------------
// Filtered Recent Downloads Table
// -------------------------------------------------------------
$filteredLogs = [];
$totalFilteredCount = 0;

if ($logCol) {
    $query = ['success' => true];

    if ($filterDate === 'today') {
        $query['createdAt'] = ['$gte' => new MongoDB\BSON\UTCDateTime($todayStart->getTimestamp() * 1000)];
    } elseif ($filterDate === '7_days') {
        $query['createdAt'] = ['$gte' => new MongoDB\BSON\UTCDateTime($weekStart->getTimestamp() * 1000)];
    } elseif ($filterDate === '30_days') {
        $query['createdAt'] = ['$gte' => new MongoDB\BSON\UTCDateTime($thirtyDaysAgo->getTimestamp() * 1000)];
    } elseif ($filterDate === 'this_month') {
        $query['createdAt'] = ['$gte' => new MongoDB\BSON\UTCDateTime($monthStart->getTimestamp() * 1000)];
    }

    if (!empty($filterType)) {
        $query['documentType'] = $filterType;
    }
    if (!empty($filterRole)) {
        $query['downloadedByRole'] = $filterRole;
    }
    if (!empty($filterTrainer)) {
        $query['$or'] = [
            ['trainerName' => ['$regex' => preg_quote($filterTrainer, '/'), '$options' => 'i']],
            ['trainerId' => $filterTrainer]
        ];
    }

    if (!empty($search)) {
        $sRegex = ['$regex' => preg_quote($search, '/'), '$options' => 'i'];
        $query['$or'] = [
            ['fileName' => $sRegex],
            ['trainerName' => $sRegex],
            ['downloadedByName' => $sRegex],
            ['documentType' => $sRegex]
        ];
    }

    $allMatching = $logCol->find($query, ['sort' => ['createdAt' => -1]])->toArray();
    $totalFilteredCount = count($allMatching);
    $filteredLogs = array_slice($allMatching, $skip, $limit);
}

// Trainer dropdown options for filter
$trainersOptions = [];
if ($trainerCol) {
    $trainersCursor = $trainerCol->find([], ['sort' => ['name' => 1], 'limit' => 60]);
    foreach ($trainersCursor as $t) {
        $tName = $t['name'] ?? 'Trainer';
        if (!empty($tName)) {
            $trainersOptions[] = $tName;
        }
    }
    $trainersOptions = array_unique($trainersOptions);
}

require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="max-w-7xl mx-auto space-y-8 pb-16">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <div class="w-10 h-10 rounded-2xl bg-orange-500/10 text-[#FE5E04] flex items-center justify-center">
                    <span class="material-symbols-outlined text-[24px]">download</span>
                </div>
                <div>
                    <h1 class="text-2xl font-black text-slate-900 tracking-tight">Download Analytics & Tracking</h1>
                    <p class="text-xs text-slate-500 mt-0.5">Authoritative audit of all server-side document, resume, and trainer dossier downloads.</p>
                </div>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-xs text-slate-600 bg-white border border-slate-200 px-3.5 py-2 rounded-xl font-bold flex items-center gap-2 shadow-xs">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>Active Tracking: <strong class="text-emerald-700 font-extrabold">Online</strong></span>
            </span>
        </div>
    </div>

    <!-- 8 Summary Cards Grid -->
    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-4 gap-4">
        <!-- Total Downloads -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-extrabold uppercase text-slate-400 tracking-wider">Total Downloads</span>
                <span class="material-symbols-outlined text-slate-400 text-lg">folder_zip</span>
            </div>
            <p class="text-2xl sm:text-3xl font-black text-slate-900 mt-2"><?= number_format($totalDownloads) ?></p>
            <p class="text-[11px] font-bold text-slate-400 mt-1">All-time successful</p>
        </div>

        <!-- Today -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-extrabold uppercase text-emerald-600 tracking-wider">Today</span>
                <span class="material-symbols-outlined text-emerald-500 text-lg">today</span>
            </div>
            <p class="text-2xl sm:text-3xl font-black text-slate-900 mt-2"><?= number_format($todayDownloads) ?></p>
            <p class="text-[11px] font-bold text-emerald-600 mt-1">Since midnight</p>
        </div>

        <!-- This Week -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-extrabold uppercase text-blue-600 tracking-wider">This Week</span>
                <span class="material-symbols-outlined text-blue-500 text-lg">date_range</span>
            </div>
            <p class="text-2xl sm:text-3xl font-black text-slate-900 mt-2"><?= number_format($weekDownloads) ?></p>
            <p class="text-[11px] font-bold text-blue-600 mt-1">Past 7 days</p>
        </div>

        <!-- This Month -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-extrabold uppercase text-purple-600 tracking-wider">This Month</span>
                <span class="material-symbols-outlined text-purple-500 text-lg">calendar_month</span>
            </div>
            <p class="text-2xl sm:text-3xl font-black text-slate-900 mt-2"><?= number_format($monthDownloads) ?></p>
            <p class="text-[11px] font-bold text-purple-600 mt-1">Current calendar month</p>
        </div>

        <!-- Unique Users -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-extrabold uppercase text-slate-400 tracking-wider">Unique Users</span>
                <span class="material-symbols-outlined text-slate-400 text-lg">group</span>
            </div>
            <p class="text-2xl sm:text-3xl font-black text-slate-900 mt-2"><?= number_format($uniqueUsersCount) ?></p>
            <p class="text-[11px] font-bold text-slate-400 mt-1">Distinct downloaders</p>
        </div>

        <!-- Documents Downloaded -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-extrabold uppercase text-slate-400 tracking-wider">Unique Files</span>
                <span class="material-symbols-outlined text-slate-400 text-lg">description</span>
            </div>
            <p class="text-2xl sm:text-3xl font-black text-slate-900 mt-2"><?= number_format($distinctDocsCount) ?></p>
            <p class="text-[11px] font-bold text-slate-400 mt-1">Distinct documents</p>
        </div>

        <!-- Trainer Profiles -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-extrabold uppercase text-amber-600 tracking-wider">Trainer Profiles</span>
                <span class="material-symbols-outlined text-amber-500 text-lg">badge</span>
            </div>
            <p class="text-2xl sm:text-3xl font-black text-slate-900 mt-2"><?= number_format($profileDownloadsCount) ?></p>
            <p class="text-[11px] font-bold text-amber-600 mt-1">Dossiers & headshots</p>
        </div>

        <!-- Documents -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-extrabold uppercase text-cyan-600 tracking-wider">Documents</span>
                <span class="material-symbols-outlined text-cyan-500 text-lg">file_present</span>
            </div>
            <p class="text-2xl sm:text-3xl font-black text-slate-900 mt-2"><?= number_format($docDownloadsCount) ?></p>
            <p class="text-[11px] font-bold text-cyan-600 mt-1">Resumes & certificates</p>
        </div>
    </div>

    <!-- Downloads Activity: Last 30 Days Bar Chart -->
    <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div>
                <h2 class="text-base font-extrabold text-slate-900">Downloads — Last 30 Days</h2>
                <p class="text-xs text-slate-500">Daily successful download requests processed by the server</p>
            </div>
            <span class="text-xs font-bold text-slate-500 bg-slate-50 border border-slate-200 px-3 py-1 rounded-xl self-start sm:self-auto">
                Peak: <strong class="text-slate-900"><?= $maxDailyCount ?></strong> / day
            </span>
        </div>

        <!-- Interactive Bar Chart -->
        <div class="pt-4 pb-2">
            <div class="h-44 flex items-end gap-1 sm:gap-2 px-1 border-b border-slate-200 pb-2">
                <?php foreach ($dailyCounts as $dayKey => $dayData): 
                    $heightPercent = ($maxDailyCount > 0) ? max(6, round(($dayData['count'] / $maxDailyCount) * 100)) : 6;
                    $isZero = ($dayData['count'] === 0);
                ?>
                    <div class="flex-1 flex flex-col items-center justify-end h-full group relative cursor-pointer">
                        <!-- Tooltip -->
                        <div class="opacity-0 group-hover:opacity-100 transition-opacity absolute bottom-full mb-2 z-20 pointer-events-none bg-slate-900 text-white text-[10px] font-bold py-1 px-2.5 rounded-lg whitespace-nowrap shadow-md">
                            <span><?= $dayData['label'] ?>: <?= $dayData['count'] ?> downloads</span>
                        </div>
                        <!-- Bar -->
                        <div class="w-full rounded-t-md transition-all duration-200 <?= $isZero ? 'bg-slate-100 group-hover:bg-slate-200' : 'bg-gradient-to-t from-orange-500 to-[#FE5E04] group-hover:from-orange-600 group-hover:to-orange-500 shadow-xs' ?>" style="height: <?= $heightPercent ?>%;"></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <!-- X Axis Labels (Sampled) -->
            <div class="flex justify-between text-[10px] font-extrabold text-slate-400 mt-2 px-1">
                <?php 
                $keys = array_keys($dailyCounts);
                $firstKey = reset($keys);
                $midKey = $keys[(int)(count($keys) / 2)];
                $lastKey = end($keys);
                ?>
                <span><?= $dailyCounts[$firstKey]['label'] ?></span>
                <span><?= $dailyCounts[$midKey]['label'] ?></span>
                <span><?= $dailyCounts[$lastKey]['label'] ?></span>
            </div>
        </div>
    </div>

    <!-- Top Downloaded Documents Table -->
    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-xs">
        <div class="p-5 border-b border-slate-200 flex items-center justify-between">
            <div>
                <h2 class="text-base font-extrabold text-slate-900">Top Downloaded Documents</h2>
                <p class="text-xs text-slate-500">Most requested files ranked by total download volume</p>
            </div>
            <span class="text-xs font-bold text-slate-500 bg-slate-50 border border-slate-200 px-3 py-1 rounded-xl">
                Top <?= count($topDocs) ?> Files
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-extrabold uppercase text-slate-400 tracking-wider">
                        <th class="py-3 px-4 w-12 text-center">Rank</th>
                        <th class="py-3 px-4">Document</th>
                        <th class="py-3 px-4">Type</th>
                        <th class="py-3 px-4">Trainer</th>
                        <th class="py-3 px-4 text-right">Downloads</th>
                        <th class="py-3 px-4 text-right">Last Downloaded</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($topDocs)): ?>
                        <tr>
                            <td colspan="6" class="py-8 text-center text-slate-400 font-bold">
                                No download events recorded yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($topDocs as $index => $doc): 
                            $rank = $index + 1;
                            $badgeClass = ($rank === 1) ? 'bg-amber-500 text-white font-black' : (($rank === 2) ? 'bg-slate-400 text-white font-black' : (($rank === 3) ? 'bg-amber-700 text-white font-black' : 'bg-slate-100 text-slate-600 font-bold'));
                        ?>
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="py-3.5 px-4 text-center">
                                    <span class="w-6 h-6 rounded-full inline-flex items-center justify-center text-[11px] <?= $badgeClass ?>">
                                        <?= $rank ?>
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 font-extrabold text-slate-900 max-w-[280px] truncate">
                                    <?= htmlspecialchars($doc['fileName']) ?>
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase bg-slate-100 text-slate-700 border border-slate-200">
                                        <?= htmlspecialchars($doc['documentType']) ?>
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 font-bold text-slate-700">
                                    <?= htmlspecialchars($doc['trainerName'] ?: 'N/A') ?>
                                </td>
                                <td class="py-3.5 px-4 text-right font-black text-slate-900">
                                    <?= number_format($doc['count']) ?>
                                </td>
                                <td class="py-3.5 px-4 text-right text-slate-500 font-medium">
                                    <?= $doc['lastDownloaded'] ? date('d M Y, h:i A', $doc['lastDownloaded']) : 'N/A' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Filters & Search Toolbar -->
    <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-xs space-y-4">
        <form method="GET" action="/admin/downloads.php" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3">
            <!-- Search -->
            <div class="md:col-span-2 relative">
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search file, trainer, or downloader..." class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs font-semibold text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#FE5E04]/20 focus:border-[#FE5E04]">
            </div>

            <!-- Date Range -->
            <div>
                <select name="date_range" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#FE5E04]/20 focus:border-[#FE5E04]">
                    <option value="all" <?= $filterDate === 'all' ? 'selected' : '' ?>>All Time</option>
                    <option value="today" <?= $filterDate === 'today' ? 'selected' : '' ?>>Today</option>
                    <option value="7_days" <?= $filterDate === '7_days' ? 'selected' : '' ?>>Last 7 Days</option>
                    <option value="30_days" <?= $filterDate === '30_days' ? 'selected' : '' ?>>Last 30 Days</option>
                    <option value="this_month" <?= $filterDate === 'this_month' ? 'selected' : '' ?>>This Month</option>
                </select>
            </div>

            <!-- Document Type -->
            <div>
                <select name="type" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#FE5E04]/20 focus:border-[#FE5E04]">
                    <option value="">All Document Types</option>
                    <option value="Resume" <?= $filterType === 'Resume' ? 'selected' : '' ?>>Resume</option>
                    <option value="Profile" <?= $filterType === 'Profile' ? 'selected' : '' ?>>Trainer Profile</option>
                    <option value="Certificate" <?= $filterType === 'Certificate' ? 'selected' : '' ?>>Certificate</option>
                    <option value="Aadhar" <?= $filterType === 'Aadhar' ? 'selected' : '' ?>>Aadhar Card</option>
                    <option value="Mark Sheet" <?= $filterType === 'Mark Sheet' ? 'selected' : '' ?>>Mark Sheet</option>
                    <option value="Photo" <?= $filterType === 'Photo' ? 'selected' : '' ?>>Headshot Photo</option>
                    <option value="Document" <?= $filterType === 'Document' ? 'selected' : '' ?>>General Document</option>
                </select>
            </div>

            <!-- Role / Actions -->
            <div class="flex gap-2">
                <select name="role" class="flex-1 bg-slate-50 border border-slate-200 rounded-xl px-2.5 py-2.5 text-xs font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#FE5E04]/20 focus:border-[#FE5E04]">
                    <option value="">All Roles</option>
                    <option value="ADMIN" <?= $filterRole === 'ADMIN' ? 'selected' : '' ?>>Admin</option>
                    <option value="STAFF" <?= $filterRole === 'STAFF' ? 'selected' : '' ?>>Staff</option>
                    <option value="COLLEGE" <?= $filterRole === 'COLLEGE' ? 'selected' : '' ?>>College</option>
                    <option value="VENDOR" <?= $filterRole === 'VENDOR' ? 'selected' : '' ?>>Vendor</option>
                    <option value="TRAINER" <?= $filterRole === 'TRAINER' ? 'selected' : '' ?>>Trainer</option>
                </select>

                <button type="submit" class="bg-[#FE5E04] hover:bg-[#E04E00] text-white font-bold text-xs px-4 py-2.5 rounded-xl transition-colors shrink-0">
                    Filter
                </button>
                <?php if (!empty($search) || $filterDate !== '30_days' || !empty($filterType) || !empty($filterRole) || !empty($filterTrainer)): ?>
                    <a href="/admin/downloads.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs p-2.5 rounded-xl transition-colors flex items-center justify-center shrink-0" title="Reset Filters">
                        <span class="material-symbols-outlined text-base">restart_alt</span>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Recent Downloads Log Table -->
    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-xs space-y-3">
        <div class="p-5 border-b border-slate-200 flex items-center justify-between">
            <div>
                <h2 class="text-base font-extrabold text-slate-900">Recent Download Logs</h2>
                <p class="text-xs text-slate-500">Chronological stream of server-side download executions (<?= number_format($totalFilteredCount) ?> records found)</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-extrabold uppercase text-slate-400 tracking-wider">
                        <th class="py-3 px-4">Time</th>
                        <th class="py-3 px-4">File Name</th>
                        <th class="py-3 px-4">Type</th>
                        <th class="py-3 px-4">Trainer</th>
                        <th class="py-3 px-4">Downloaded By</th>
                        <th class="py-3 px-4">Role</th>
                        <th class="py-3 px-4 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($filteredLogs)): ?>
                        <tr>
                            <td colspan="7" class="py-10 text-center text-slate-400 font-bold">
                                No download events matched your query.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($filteredLogs as $log): 
                            $logTimeStr = 'N/A';
                            if (isset($log['createdAt'])) {
                                if ($log['createdAt'] instanceof MongoDB\BSON\UTCDateTime) {
                                    $logTimeStr = $log['createdAt']->toDateTime()->setTimezone(new DateTimeZone('Asia/Kolkata'))->format('d M, h:i:s A');
                                } elseif (is_numeric($log['createdAt'])) {
                                    $logTimeStr = date('d M, h:i:s A', (int)$log['createdAt']);
                                } else {
                                    $logTimeStr = date('d M, h:i:s A', strtotime((string)$log['createdAt']));
                                }
                            }
                            $rRole = strtoupper($log['downloadedByRole'] ?? 'USER');
                            $roleBadge = ($rRole === 'ADMIN') ? 'bg-orange-100 text-[#FE5E04] border-orange-200' : (($rRole === 'STAFF') ? 'bg-blue-100 text-blue-800 border-blue-200' : (($rRole === 'COLLEGE') ? 'bg-purple-100 text-purple-800 border-purple-200' : 'bg-slate-100 text-slate-700 border-slate-200'));
                        ?>
                            <tr class="hover:bg-slate-50/80 transition-colors cursor-pointer" onclick='openDownloadModal(<?= json_encode($log) ?>)'>
                                <td class="py-3.5 px-4 text-slate-500 font-medium whitespace-nowrap">
                                    <?= $logTimeStr ?>
                                </td>
                                <td class="py-3.5 px-4 font-bold text-slate-900 max-w-[240px] truncate">
                                    <?= htmlspecialchars($log['fileName'] ?? 'Document') ?>
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase bg-slate-100 text-slate-700 border border-slate-200">
                                        <?= htmlspecialchars($log['documentType'] ?? 'Document') ?>
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 font-semibold text-slate-700 truncate max-w-[180px]">
                                    <?= htmlspecialchars($log['trainerName'] ?: 'N/A') ?>
                                </td>
                                <td class="py-3.5 px-4 font-bold text-slate-800 truncate max-w-[160px]">
                                    <?= htmlspecialchars($log['downloadedByName'] ?? 'User') ?>
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase border <?= $roleBadge ?>">
                                        <?= $rRole ?>
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-center">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase bg-emerald-100 text-emerald-800 border border-emerald-200">
                                        SUCCESS
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php 
        $totalPages = max(1, ceil($totalFilteredCount / $limit));
        if ($totalPages > 1): 
        ?>
            <div class="p-4 border-t border-slate-200 flex items-center justify-between text-xs">
                <span class="text-slate-500 font-semibold">
                    Showing <?= $skip + 1 ?> to <?= min($skip + $limit, $totalFilteredCount) ?> of <?= number_format($totalFilteredCount) ?>
                </span>
                <div class="flex items-center gap-1.5">
                    <?php if ($page > 1): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 font-bold text-slate-700 transition-colors">
                            Prev
                        </a>
                    <?php endif; ?>
                    <span class="px-3 py-1.5 rounded-lg bg-[#FE5E04]/10 text-[#FE5E04] font-black">
                        Page <?= $page ?> of <?= $totalPages ?>
                    </span>
                    <?php if ($page < $totalPages): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 font-bold text-slate-700 transition-colors">
                            Next
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Detailed Download Record Modal -->
<div id="downloadDetailModal" class="hidden fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 space-y-4 animate-in fade-in zoom-in duration-150">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-[#FE5E04]">analytics</span>
                <h3 class="font-extrabold text-base text-slate-900">Download Event Audit</h3>
            </div>
            <button type="button" onclick="closeDownloadModal()" class="p-1 text-slate-400 hover:text-slate-600 rounded-lg">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <div class="space-y-3 text-xs">
            <div class="grid grid-cols-3 gap-2 py-1 border-b border-slate-50">
                <span class="font-bold text-slate-400">Download ID</span>
                <span id="mDlId" class="col-span-2 font-mono font-bold text-slate-800"></span>
            </div>
            <div class="grid grid-cols-3 gap-2 py-1 border-b border-slate-50">
                <span class="font-bold text-slate-400">File Name</span>
                <span id="mFileName" class="col-span-2 font-extrabold text-slate-900 break-all"></span>
            </div>
            <div class="grid grid-cols-3 gap-2 py-1 border-b border-slate-50">
                <span class="font-bold text-slate-400">Document Type</span>
                <span id="mDocType" class="col-span-2 font-semibold text-slate-800"></span>
            </div>
            <div class="grid grid-cols-3 gap-2 py-1 border-b border-slate-50">
                <span class="font-bold text-slate-400">Trainer</span>
                <span id="mTrainer" class="col-span-2 font-bold text-slate-800"></span>
            </div>
            <div class="grid grid-cols-3 gap-2 py-1 border-b border-slate-50">
                <span class="font-bold text-slate-400">Downloaded By</span>
                <span id="mDownloader" class="col-span-2 font-bold text-slate-900"></span>
            </div>
            <div class="grid grid-cols-3 gap-2 py-1 border-b border-slate-50">
                <span class="font-bold text-slate-400">User Role</span>
                <span id="mRole" class="col-span-2 font-black uppercase text-[#FE5E04]"></span>
            </div>
            <div class="grid grid-cols-3 gap-2 py-1 border-b border-slate-50">
                <span class="font-bold text-slate-400">Source Stream</span>
                <span id="mSource" class="col-span-2 font-mono text-slate-600"></span>
            </div>
            <div class="grid grid-cols-3 gap-2 py-1 border-b border-slate-50">
                <span class="font-bold text-slate-400">Execution Status</span>
                <span class="col-span-2 font-black text-emerald-600">SUCCESS (HTTP 200 Stream)</span>
            </div>
            <div class="grid grid-cols-3 gap-2 py-1">
                <span class="font-bold text-slate-400">Document ID</span>
                <span id="mDocId" class="col-span-2 font-mono text-slate-500 break-all"></span>
            </div>
        </div>

        <div class="pt-3 flex justify-end">
            <button type="button" onclick="closeDownloadModal()" class="bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold px-4 py-2 rounded-xl text-xs transition-colors">
                Close
            </button>
        </div>
    </div>
</div>

<script>
function openDownloadModal(log) {
    document.getElementById('mDlId').textContent = log.downloadId || log._id || 'N/A';
    document.getElementById('mFileName').textContent = log.fileName || 'Document';
    document.getElementById('mDocType').textContent = log.documentType || 'Document';
    document.getElementById('mTrainer').textContent = log.trainerName || 'N/A';
    document.getElementById('mDownloader').textContent = log.downloadedByName || 'User';
    document.getElementById('mRole').textContent = log.downloadedByRole || 'USER';
    document.getElementById('mSource').textContent = log.source || 'web';
    document.getElementById('mDocId').textContent = log.documentId || 'N/A';
    document.getElementById('downloadDetailModal').classList.remove('hidden');
}

function closeDownloadModal() {
    document.getElementById('downloadDetailModal').classList.add('hidden');
}
</script>

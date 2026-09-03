<?php
// trainer/includes/sidebar.php
require_once __DIR__ . '/../../includes/auth.php';
requireTrainer();

$user = getCurrentUser();
$currentPage = basename($_SERVER['PHP_SELF']);

$navItems = [
    ['label' => 'Dashboard', 'href' => '/trainer/dashboard.php', 'icon' => 'space_dashboard'],
    ['label' => 'My Profile', 'href' => '/trainer/profile.php', 'icon' => 'person'],
    ['label' => 'My Expertise', 'href' => '/trainer/expertise.php', 'icon' => 'psychology'],
    ['label' => 'My Experience', 'href' => '/trainer/experience.php', 'icon' => 'work_history'],
    ['label' => 'My Documents', 'href' => '/trainer/documents.php', 'icon' => 'description'],
    ['label' => 'Opportunities', 'href' => '/trainer/opportunities.php', 'icon' => 'search'],
    ['label' => 'My Applications', 'href' => '/trainer/applications.php', 'icon' => 'assignment_turned_in'],
    ['label' => 'Assignments', 'href' => '/trainer/assignments.php', 'icon' => 'event_available'],
    ['label' => 'Notifications', 'href' => '/trainer/notifications.php', 'icon' => 'notifications'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' | ' : '' ?>Trainer Portal - Mentry</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400; vertical-align: middle; }
        .material-symbols-outlined.fill { font-variation-settings: 'FILL' 1; }
    </style>
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen flex antialiased">

<!-- Desktop Sticky Sidebar -->
<aside class="bg-[#0B1526] text-slate-300 h-screen w-64 shadow-xl flex-col shrink-0 hidden md:flex sticky top-0 z-40 border-r border-slate-800/80 py-6 select-none">
    <!-- Header Branding -->
    <div class="px-6 mb-6">
        <a href="/index.php" class="flex items-center gap-3 group">
            <div class="bg-white p-1 rounded-xl shadow-md shrink-0">
                <img src="/public/mentry.png" alt="Mentry" class="h-8 w-auto object-contain rounded-lg">
            </div>
            <div>
                <h1 class="font-extrabold text-base text-white leading-tight">Mentry Portal</h1>
                <p class="text-[11px] font-medium text-slate-400">Trainer Workspace</p>
            </div>
        </a>
    </div>

    <!-- Quick Action -->
    <div class="px-4 mb-5">
        <a href="/trainer/opportunities.php" class="w-full bg-[#FE5E04] hover:bg-[#E04E00] text-white text-xs font-bold py-2.5 px-4 rounded-xl transition-all flex items-center justify-center gap-2 shadow-md shadow-orange-500/20">
            <span class="material-symbols-outlined text-[18px]">search</span>
            Browse Openings
        </a>
    </div>

    <!-- Navigation Links -->
    <div class="flex-1 overflow-y-auto space-y-1 px-3">
        <?php foreach ($navItems as $item): 
            $isActive = ($currentPage === basename($item['href']));
        ?>
            <a href="<?= $item['href'] ?>" class="rounded-xl flex items-center px-3.5 py-2.5 transition-all text-xs font-semibold <?= $isActive ? 'bg-[#FE5E04]/15 text-[#FE5E04] border border-[#FE5E04]/30' : 'text-slate-400 hover:bg-slate-800/60 hover:text-white' ?>">
                <span class="material-symbols-outlined mr-3 text-[18px] <?= $isActive ? 'text-[#FE5E04] fill' : 'text-slate-400' ?>">
                    <?= $item['icon'] ?>
                </span>
                <span class="flex-1 truncate"><?= $item['label'] ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Bottom Settings & Logout -->
    <div class="mt-auto pt-4 border-t border-slate-800/80 px-3 space-y-1">
        <a href="/trainer/settings.php" class="rounded-xl flex items-center px-3.5 py-2.5 transition-all text-xs font-semibold text-slate-400 hover:bg-slate-800/60 hover:text-white">
            <span class="material-symbols-outlined mr-3 text-[18px]">settings</span>
            <span>Settings</span>
        </a>
        <a href="/logout.php" class="rounded-xl flex items-center px-3.5 py-2.5 transition-all text-xs font-semibold text-rose-400 hover:bg-rose-950/40 hover:text-rose-300">
            <span class="material-symbols-outlined mr-3 text-[18px]">logout</span>
            <span>Sign Out</span>
        </a>
    </div>
</aside>

<!-- Main Workspace Canvas -->
<div class="flex-1 flex flex-col min-w-0 h-screen overflow-y-auto">
    <!-- Top Bar -->
    <header class="bg-white border-b border-slate-200 px-6 py-4 flex items-center justify-between sticky top-0 z-30 shadow-xs">
        <div class="flex items-center gap-3">
            <a href="/index.php" class="md:hidden">
                <img src="/public/mentry.png" alt="Mentry" class="h-7 w-auto">
            </a>
            <span class="font-bold text-slate-900 text-sm hidden sm:inline">Trainer Workspace</span>
        </div>
        <div class="flex items-center gap-4">
            <span class="text-xs font-medium text-slate-500">Logged in as <strong><?= htmlspecialchars($user['name']) ?></strong></span>
            <a href="/logout.php" class="text-xs text-rose-600 font-bold hover:underline">Logout</a>
        </div>
    </header>

    <main class="flex-1 w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 md:py-10 space-y-8">

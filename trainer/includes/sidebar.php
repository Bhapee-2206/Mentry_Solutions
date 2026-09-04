<?php
// trainer/includes/sidebar.php
require_once __DIR__ . '/../../includes/auth.php';
requireTrainer();

$user = getCurrentUser();
$currentPage = basename($_SERVER['PHP_SELF']);

$navItems = [
    ['label' => 'Dashboard', 'href' => '/trainer/dashboard.php', 'icon' => 'space_dashboard'],
    ['label' => 'My Profile', 'href' => '/trainer/profile.php', 'icon' => 'person'],
    ['label' => 'Expertise & Experience', 'href' => '/trainer/expertise.php', 'icon' => 'psychology'],
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
    
    <!-- Favicon & Brand Icons -->
    <link rel="icon" type="image/png" href="/public/mentry.png">
    <link rel="shortcut icon" type="image/png" href="/public/mentry.png">
    <link rel="apple-touch-icon" href="/public/mentry.png">

    <link rel="dns-prefetch" href="//fonts.googleapis.com">
    <link rel="dns-prefetch" href="//fonts.gstatic.com">
    <link rel="dns-prefetch" href="//cdn.tailwindcss.com">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

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
<?php require_once __DIR__ . '/../../includes/loading_screen.php'; ?>

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
        <a href="/index.php" class="rounded-xl flex items-center px-3.5 py-2.5 transition-all text-xs font-semibold text-slate-400 hover:bg-slate-800/60 hover:text-white">
            <span class="material-symbols-outlined mr-3 text-[18px]">home</span>
            <span>Website Home</span>
        </a>
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
    <?php $isAdminViewing = isAdminOrStaff(); ?>
    <!-- Top Bar -->
    <header class="bg-white border-b border-slate-200 px-4 sm:px-6 py-3.5 flex items-center justify-between sticky top-0 z-30 shadow-xs">
        <div class="flex items-center gap-3">
            <a href="/index.php" class="md:hidden">
                <img src="/public/mentry.png" alt="Mentry" class="h-7 w-auto">
            </a>
            <div class="flex items-center gap-2">
                <span class="font-bold text-slate-900 text-sm hidden sm:inline"><?= isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Trainer Workspace' ?></span>
                <?php if ($isAdminViewing): ?>
                    <span class="inline-flex items-center gap-1 text-[10px] font-black uppercase tracking-wider px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 border border-amber-200" title="Viewing as administrator">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                        Admin View
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <?php if ($isAdminViewing): ?>
                <a href="/admin/index.php" class="hidden sm:inline-flex items-center gap-1 text-xs font-bold text-slate-600 hover:text-slate-900 bg-slate-100 hover:bg-slate-200 px-3 py-1.5 rounded-xl transition-colors">
                    <span class="material-symbols-outlined text-sm">shield_person</span>
                    Return to Admin
                </a>
            <?php endif; ?>

            <!-- Trainer Profile Pill -->
            <a href="/trainer/profile.php" class="flex items-center gap-2.5 p-1 sm:pr-3 rounded-full hover:bg-slate-50 transition-colors border border-transparent hover:border-slate-200">
                <img src="<?= htmlspecialchars(getUserAvatar($user, 72)) ?>" alt="<?= htmlspecialchars($user['name']) ?>" class="w-8 h-8 rounded-full object-cover border border-slate-200 shadow-2xs">
                <div class="hidden sm:block text-left">
                    <p class="text-xs font-bold text-slate-900 leading-tight truncate max-w-[140px]"><?= htmlspecialchars($user['name']) ?></p>
                    <p class="text-[10px] text-slate-400 font-medium leading-tight"><?= $isAdminViewing ? 'Admin Linked Profile' : 'Trainer Profile' ?></p>
                </div>
            </a>

            <a href="/logout.php" class="p-1.5 rounded-xl text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors" title="Sign Out">
                <span class="material-symbols-outlined text-[20px]">logout</span>
            </a>
        </div>
    </header>

    <?php if ($isAdminViewing): ?>
    <!-- One-Time Admin Workspace Notice Modal -->
    <div id="adminTrainerNoticeModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs transition-opacity">
        <div class="bg-white rounded-3xl max-w-md w-full shadow-2xl border border-slate-200 overflow-hidden">
            <div class="p-6 text-center space-y-4">
                <div class="w-14 h-14 mx-auto rounded-2xl bg-amber-50 border border-amber-200 text-amber-600 flex items-center justify-center shadow-xs">
                    <span class="material-symbols-outlined text-3xl">admin_panel_settings</span>
                </div>
                <div>
                    <span class="inline-flex items-center gap-1 text-[10px] font-black uppercase tracking-wider px-2.5 py-0.5 rounded-full bg-amber-100 text-amber-800 mb-2">
                        Admin Preview Mode
                    </span>
                    <h3 class="text-lg font-black text-slate-900">Trainer Workspace Notice</h3>
                    <p class="text-xs text-slate-600 mt-2 leading-relaxed">
                        You are viewing the Trainer Portal with privileged administrative access as <strong><?= htmlspecialchars($user['name']) ?></strong>.
                    </p>
                    <p class="text-[11px] text-slate-400 mt-1">
                        Profile updates, skill submissions, and document uploads performed here will directly update your linked trainer record.
                    </p>
                </div>
                <div class="flex flex-col sm:flex-row gap-2 pt-2">
                    <button type="button" onclick="dismissAdminTrainerNotice()" class="flex-1 bg-[#FE5E04] hover:bg-[#E04E00] text-white text-xs font-bold py-2.5 px-4 rounded-xl transition-all shadow-md shadow-orange-500/20 cursor-pointer">
                        Got it, Continue
                    </button>
                    <a href="/admin/index.php" class="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold py-2.5 px-4 rounded-xl transition-all flex items-center justify-center gap-1">
                        <span class="material-symbols-outlined text-sm">arrow_back</span>
                        Admin Console
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function() {
        try {
            if (!sessionStorage.getItem('mentry_admin_trainer_notice_seen')) {
                const modal = document.getElementById('adminTrainerNoticeModal');
                if (modal) {
                    modal.classList.remove('hidden');
                }
            }
        } catch(e) {}
    })();

    function dismissAdminTrainerNotice() {
        try {
            sessionStorage.setItem('mentry_admin_trainer_notice_seen', '1');
        } catch(e) {}
        const modal = document.getElementById('adminTrainerNoticeModal');
        if (modal) {
            modal.classList.add('hidden');
        }
    }
    </script>
    <?php endif; ?>

    <main class="flex-1 w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 md:py-10 space-y-8">

<?php
// admin/includes/sidebar.php
require_once __DIR__ . '/../../includes/auth.php';
requireAdminOrStaff();

$adminUser = getCurrentUser();
$currentPage = basename($_SERVER['PHP_SELF']);
$isStaffUser = isStaff();

$navItems = [
    ['label' => 'Dashboard', 'href' => '/admin/index.php', 'icon' => 'space_dashboard'],
    ['label' => 'Zervy AI Assistant', 'href' => '/admin/ai-assistant.php', 'icon' => 'smart_toy'],
    ['label' => 'Team Workspace Chat', 'href' => '/admin/team-chat.php', 'icon' => 'forum'],
    ['label' => 'Vendor Demands', 'href' => '/admin/vendor-requests.php', 'icon' => 'storefront'],
    ['label' => 'Trainers Directory', 'href' => '/admin/trainers.php', 'icon' => 'group'],
    ['label' => 'Opportunities', 'href' => '/admin/opportunities.php', 'icon' => 'work'],
    ['label' => 'Applications', 'href' => '/admin/applications.php', 'icon' => 'assignment'],
    ['label' => 'Assignments', 'href' => '/admin/assignments.php', 'icon' => 'event_available'],
    ['label' => 'College Intake', 'href' => '/admin/requirements.php', 'icon' => 'school'],
    ['label' => 'Team & Staff', 'href' => '/admin/staff.php', 'icon' => 'badge'],
    ['label' => 'Notification Logs', 'href' => '/admin/notifications.php', 'icon' => 'campaign'],
    ['label' => 'Settings & Audit', 'href' => '/admin/settings.php', 'icon' => 'settings'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' | ' : '' ?>Command Center - Mentry</title>
    
    <!-- Favicon & Brand Icons -->
    <link rel="icon" type="image/png" href="/public/mentry.png">
    <link rel="shortcut icon" type="image/png" href="/public/mentry.png">
    <link rel="apple-touch-icon" href="/public/mentry.png">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            500: '#FE5E04',
                            600: '#E04E00',
                            700: '#C23E00'
                        }
                    }
                }
            }
        }
    </script>
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
<aside class="bg-[#070D18] text-slate-300 h-screen w-64 shadow-xl flex-col shrink-0 hidden md:flex sticky top-0 z-40 border-r border-slate-800/90 py-6 select-none">
    <!-- Header Branding -->
    <div class="px-6 mb-6">
        <a href="/admin/index.php" class="flex items-center gap-3 group">
            <div class="bg-white p-1 rounded-xl shadow-md shrink-0">
                <img src="/public/mentry.png" alt="Mentry" class="h-8 w-auto object-contain rounded-lg">
            </div>
            <div>
                <h1 class="font-extrabold text-base text-white leading-tight">Mentry Ops</h1>
                <p class="text-[11px] font-bold text-[#FE5E04]">
                    <?= $isStaffUser ? 'Staff Command' : 'Admin Command Center' ?>
                </p>
            </div>
        </a>
    </div>

    <!-- Quick Action -->
    <div class="px-4 mb-5">
        <a href="/admin/opportunity-create.php" class="w-full bg-[#FE5E04] hover:bg-[#E04E00] text-white text-xs font-bold py-2.5 px-4 rounded-xl transition-all flex items-center justify-center gap-2 shadow-md shadow-orange-500/20">
            <span class="material-symbols-outlined text-[18px]">add</span>
            New Opportunity
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

    <!-- Live Database Status Indicator -->
    <div class="px-4 mb-2">
        <div class="px-3.5 py-2.5 rounded-2xl bg-slate-900/90 border border-slate-800/90 flex items-center justify-between shadow-xs">
            <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                <span class="text-[11px] font-bold text-slate-200">Database</span>
            </div>
            <span class="text-[10px] font-black uppercase tracking-wider text-emerald-400 bg-emerald-950/80 border border-emerald-800/80 px-2 py-0.5 rounded-md">
                Connected
            </span>
        </div>
    </div>

    <!-- Admin User Info & Logout -->
    <div class="mt-auto pt-4 border-t border-slate-800/80 px-4 space-y-3">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-[#FE5E04]/20 border border-[#FE5E04]/40 text-[#FE5E04] font-bold flex items-center justify-center text-xs">
                <?= substr($adminUser['name'] ?? 'U', 0, 1) ?>
            </div>
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-1.5">
                    <p class="text-xs font-bold text-white truncate"><?= htmlspecialchars($adminUser['name'] ?? 'User') ?></p>
                    <span class="text-[9px] font-extrabold uppercase px-1.5 py-0.2 rounded <?= ($adminUser['role'] ?? '') === 'ADMIN' ? 'bg-[#FE5E04] text-white' : 'bg-blue-600 text-white' ?>">
                        <?= htmlspecialchars($adminUser['role'] ?? 'STAFF') ?>
                    </span>
                </div>
                <p class="text-[10px] text-slate-400 truncate"><?= htmlspecialchars($adminUser['email'] ?? '') ?></p>
            </div>
        </div>

        <div class="space-y-1">
            <a href="/index.php" class="w-full text-left rounded-xl flex items-center px-3.5 py-2 transition-all text-xs font-semibold text-slate-400 hover:bg-slate-800/60 hover:text-white">
                <span class="material-symbols-outlined mr-3 text-[18px]">home</span>
                <span>Website Home</span>
            </a>
            <a href="/logout.php" class="w-full text-left rounded-xl flex items-center px-3.5 py-2 transition-all text-xs font-semibold text-rose-400 hover:bg-rose-950/40 hover:text-rose-300">
                <span class="material-symbols-outlined mr-3 text-[18px]">logout</span>
                <span>Sign Out</span>
            </a>
        </div>
    </div>
</aside>

<!-- Mobile Drawer Backdrop Overlay -->
<div id="adminMobileDrawerOverlay" onclick="toggleMobileAdminNav()" class="hidden fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs md:hidden transition-opacity"></div>

<!-- Mobile Off-Canvas Sidebar Drawer -->
<aside id="adminMobileDrawer" class="fixed top-0 left-0 bottom-0 w-72 bg-[#070D18] text-slate-300 shadow-2xl z-50 flex flex-col py-6 border-r border-slate-800/90 -translate-x-full transition-transform duration-300 ease-in-out md:hidden select-none">
    <!-- Header with Close Button -->
    <div class="px-6 mb-6 flex items-center justify-between">
        <a href="/admin/index.php" class="flex items-center gap-3">
            <div class="bg-white p-1 rounded-xl shadow-md shrink-0">
                <img src="/public/mentry.png" alt="Mentry" class="h-8 w-auto object-contain rounded-lg">
            </div>
            <div>
                <h1 class="font-extrabold text-base text-white leading-tight">Mentry Ops</h1>
                <p class="text-[11px] font-bold text-[#FE5E04]">
                    <?= $isStaffUser ? 'Staff Command' : 'Admin Command Center' ?>
                </p>
            </div>
        </a>
        <button type="button" onclick="toggleMobileAdminNav()" class="p-1.5 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 cursor-pointer">
            <span class="material-symbols-outlined text-2xl">close</span>
        </button>
    </div>

    <!-- Quick Action -->
    <div class="px-4 mb-5">
        <a href="/admin/opportunity-create.php" class="w-full bg-[#FE5E04] hover:bg-[#E04E00] text-white text-xs font-bold py-2.5 px-4 rounded-xl transition-all flex items-center justify-center gap-2 shadow-md shadow-orange-500/20">
            <span class="material-symbols-outlined text-[18px]">add</span>
            New Opportunity
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

    <!-- Admin User Info & Logout -->
    <div class="mt-auto pt-4 border-t border-slate-800/80 px-4 space-y-3">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-[#FE5E04]/20 border border-[#FE5E04]/40 text-[#FE5E04] font-bold flex items-center justify-center text-xs">
                <?= substr($adminUser['name'] ?? 'U', 0, 1) ?>
            </div>
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-1.5">
                    <p class="text-xs font-bold text-white truncate"><?= htmlspecialchars($adminUser['name'] ?? 'User') ?></p>
                    <span class="text-[9px] font-extrabold uppercase px-1.5 py-0.2 rounded <?= ($adminUser['role'] ?? '') === 'ADMIN' ? 'bg-[#FE5E04] text-white' : 'bg-blue-600 text-white' ?>">
                        <?= htmlspecialchars($adminUser['role'] ?? 'STAFF') ?>
                    </span>
                </div>
                <p class="text-[10px] text-slate-400 truncate"><?= htmlspecialchars($adminUser['email'] ?? '') ?></p>
            </div>
        </div>

        <a href="/logout.php" class="w-full text-left rounded-xl flex items-center px-3.5 py-2 transition-all text-xs font-semibold text-rose-400 hover:bg-rose-950/40 hover:text-rose-300">
            <span class="material-symbols-outlined mr-3 text-[18px]">logout</span>
            <span>Sign Out</span>
        </a>
    </div>
</aside>

<!-- Main Admin Canvas -->
<div class="flex-1 flex flex-col min-w-0 h-screen overflow-y-auto overflow-x-hidden">
    <!-- Top Bar -->
    <header class="bg-white border-b border-slate-200 px-4 sm:px-6 py-3.5 flex items-center justify-between sticky top-0 z-30 shadow-xs">
        <div class="flex items-center gap-2.5 sm:gap-3 min-w-0">
            <!-- Mobile Drawer Hamburger Toggle Button -->
            <button type="button" onclick="toggleMobileAdminNav()" class="p-2 -ml-1 rounded-xl text-slate-700 hover:bg-slate-100 md:hidden cursor-pointer" title="Open navigation menu">
                <span class="material-symbols-outlined text-2xl">menu</span>
            </button>

            <a href="/admin/index.php" class="md:hidden flex items-center gap-2 shrink-0">
                <img src="/public/mentry.png" alt="Mentry" class="h-7 w-auto object-contain">
                <span class="font-black text-sm text-slate-900">Mentry</span>
            </a>
            <span class="font-bold text-slate-900 text-sm hidden md:inline truncate">
                <?= $isStaffUser ? 'Operations Staff Dashboard' : 'Admin Command Center' ?>
            </span>
        </div>

        <div class="flex items-center gap-2 sm:gap-3 shrink-0">
            <div class="hidden md:flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-slate-100 border border-slate-200 text-[11px] font-semibold text-slate-700" title="Live Database Connection Active">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>Database: <strong class="text-emerald-700 font-bold">Connected</strong></span>
            </div>
            <a href="/admin/requirements.php" class="text-xs font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 px-3 py-1.5 rounded-lg transition-colors hidden sm:inline">
                College Intake
            </a>
            <a href="/admin/opportunity-create.php" class="bg-[#FE5E04] hover:bg-[#E04E00] text-white text-xs font-bold px-3 sm:px-3.5 py-1.5 rounded-lg shadow-xs transition-colors whitespace-nowrap flex items-center gap-1">
                <span class="material-symbols-outlined text-[16px]">add</span>
                <span>New Opp</span>
            </a>
        </div>
    </header>

    <script>
    function toggleMobileAdminNav() {
        const drawer = document.getElementById('adminMobileDrawer');
        const overlay = document.getElementById('adminMobileDrawerOverlay');
        const isClosed = drawer.classList.contains('-translate-x-full');
        if (isClosed) {
            drawer.classList.remove('-translate-x-full');
            overlay.classList.remove('hidden');
        } else {
            drawer.classList.add('-translate-x-full');
            overlay.classList.add('hidden');
        }
    }
    </script>

    <main class="flex-1 w-full max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 py-4 sm:py-6 md:py-8 space-y-6 overflow-x-hidden">

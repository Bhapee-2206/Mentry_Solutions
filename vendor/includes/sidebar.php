<?php
// vendor/includes/sidebar.php
require_once __DIR__ . '/../../includes/auth.php';
requireVendor();

$user = getCurrentUser();
$currentPage = basename($_SERVER['PHP_SELF']);

$navItems = [
    ['label' => 'Dashboard', 'href' => '/vendor/dashboard.php', 'icon' => 'space_dashboard'],
    ['label' => 'Post Job Request', 'href' => '/vendor/request-create.php', 'icon' => 'post_add'],
    ['label' => 'My Requirements', 'href' => '/vendor/requests.php', 'icon' => 'list_alt'],
    ['label' => 'Campus Deliveries', 'href' => '/vendor/assignments.php', 'icon' => 'event_available'],
    ['label' => 'Organization Profile', 'href' => '/vendor/profile.php', 'icon' => 'corporate_fare'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' | ' : '' ?>Vendor & College Partner Portal - Mentry</title>
    <!-- Favicon & Brand Icons -->
    <link rel="icon" type="image/png" href="/public/mentry.png?v=2">
    <link rel="shortcut icon" href="/favicon.ico?v=2">
    <link rel="apple-touch-icon" href="/public/mentry.png?v=2">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <style>
        html, body {
            max-width: 100%;
            overflow-x: hidden;
        }
        *, *::before, *::after {
            box-sizing: border-box;
        }
        body { font-family: 'Inter', sans-serif; }
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400; vertical-align: middle; }
        .material-symbols-outlined.fill { font-variation-settings: 'FILL' 1; }
    </style>
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen flex antialiased w-full max-w-full overflow-x-hidden">

<!-- Desktop Sticky Sidebar -->
<aside class="bg-[#0D1527] text-slate-300 h-screen w-64 shadow-xl flex-col shrink-0 hidden md:flex sticky top-0 z-40 border-r border-slate-800/80 py-6 select-none">
    <!-- Header Branding -->
    <div class="px-6 mb-6">
        <a href="/vendor/dashboard.php" class="flex items-center gap-3 group">
            <div class="bg-white p-1 rounded-xl shadow-md shrink-0">
                <img src="/public/mentry.png" alt="Mentry" class="h-8 w-auto object-contain rounded-lg">
            </div>
            <div>
                <h1 class="font-extrabold text-base text-white leading-tight">Partner Portal</h1>
                <p class="text-[11px] font-medium text-indigo-400 truncate max-w-[140px]"><?= htmlspecialchars($user['organizationName'] ?? 'Vendor / College') ?></p>
            </div>
        </a>
    </div>

    <!-- Quick Action: Post New Requirement -->
    <div class="px-4 mb-5">
        <a href="/vendor/request-create.php" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold py-2.5 px-4 rounded-xl transition-all flex items-center justify-center gap-2 shadow-md">
            <span class="material-symbols-outlined text-[18px]">add_circle</span>
            Post Job Request
        </a>
    </div>

    <!-- Navigation Links -->
    <div class="flex-1 overflow-y-auto space-y-1 px-3">
        <?php foreach ($navItems as $item): 
            $isActive = ($currentPage === basename($item['href']));
        ?>
            <a href="<?= $item['href'] ?>" class="rounded-xl flex items-center px-3.5 py-2.5 transition-all text-xs font-semibold <?= $isActive ? 'bg-indigo-600/20 text-indigo-400 border border-indigo-500/30' : 'text-slate-400 hover:bg-slate-800/60 hover:text-white' ?>">
                <span class="material-symbols-outlined mr-3 text-[18px] <?= $isActive ? 'text-indigo-400 fill' : 'text-slate-400' ?>">
                    <?= $item['icon'] ?>
                </span>
                <span class="flex-1 truncate"><?= $item['label'] ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Bottom User Info & Logout -->
    <div class="mt-auto pt-4 border-t border-slate-800/80 px-4 space-y-3">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-full bg-indigo-950 border border-indigo-800 text-indigo-400 font-bold flex items-center justify-center text-xs">
                <?= substr($user['name'] ?? 'V', 0, 1) ?>
            </div>
            <div class="min-w-0 flex-1">
                <p class="text-xs font-bold text-white truncate"><?= htmlspecialchars($user['name']) ?></p>
                <p class="text-[10px] text-slate-400 truncate"><?= htmlspecialchars($user['email']) ?></p>
            </div>
        </div>

        <a href="/logout.php" class="w-full text-left rounded-xl flex items-center px-3.5 py-2 transition-all text-xs font-semibold text-rose-400 hover:bg-rose-950/40 hover:text-rose-300">
            <span class="material-symbols-outlined mr-3 text-[18px]">logout</span>
            <span>Sign Out</span>
        </a>
    </div>
</aside>

<!-- Mobile Navigation Drawer -->
<div id="vendorMobileDrawer" class="hidden fixed inset-0 z-50 md:hidden">
    <div id="vendorDrawerBackdrop" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity"></div>
    <div class="fixed inset-y-0 left-0 max-w-xs w-full bg-[#0D1527] text-slate-300 shadow-2xl flex flex-col py-6 px-4 z-10 select-none overflow-y-auto">
        <div class="flex items-center justify-between px-2 mb-6">
            <a href="/vendor/dashboard.php" class="flex items-center gap-2.5">
                <div class="bg-white p-1 rounded-xl shadow-md shrink-0">
                    <img src="/public/mentry.png" alt="Mentry" class="h-7 w-auto object-contain rounded-lg">
                </div>
                <div>
                    <h2 class="font-extrabold text-sm text-white leading-tight">Partner Portal</h2>
                    <p class="text-[10px] font-medium text-indigo-400 truncate max-w-[120px]"><?= htmlspecialchars($user['organizationName'] ?? 'Partner') ?></p>
                </div>
            </a>
            <button id="vendorDrawerCloseBtn" type="button" aria-label="Close menu" class="p-1.5 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 cursor-pointer">
                <span class="material-symbols-outlined text-2xl">close</span>
            </button>
        </div>

        <div class="mb-4">
            <a href="/vendor/request-create.php" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold py-2.5 px-4 rounded-xl transition-all flex items-center justify-center gap-2 shadow-md">
                <span class="material-symbols-outlined text-[18px]">add_circle</span>
                Post Job Request
            </a>
        </div>

        <div class="flex-1 space-y-1">
            <?php foreach ($navItems as $item): 
                $isActive = ($currentPage === basename($item['href']));
            ?>
                <a href="<?= $item['href'] ?>" class="rounded-xl flex items-center px-3.5 py-2.5 transition-all text-xs font-semibold <?= $isActive ? 'bg-indigo-600/20 text-indigo-400 border border-indigo-500/30' : 'text-slate-400 hover:bg-slate-800/60 hover:text-white' ?>">
                    <span class="material-symbols-outlined mr-3 text-[18px] <?= $isActive ? 'text-indigo-400 fill' : 'text-slate-400' ?>">
                        <?= $item['icon'] ?>
                    </span>
                    <span class="flex-1 truncate"><?= $item['label'] ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="pt-4 border-t border-slate-800/80 space-y-1">
            <a href="/index.php" class="rounded-xl flex items-center px-3.5 py-2.5 transition-all text-xs font-semibold text-slate-400 hover:bg-slate-800/60 hover:text-white">
                <span class="material-symbols-outlined mr-3 text-[18px]">home</span>
                <span>Website Home</span>
            </a>
            <a href="/logout.php" class="rounded-xl flex items-center px-3.5 py-2.5 transition-all text-xs font-semibold text-rose-400 hover:bg-rose-950/40 hover:text-rose-300">
                <span class="material-symbols-outlined mr-3 text-[18px]">logout</span>
                <span>Sign Out</span>
            </a>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const openBtn = document.getElementById('vendorMobileMenuBtn');
    const closeBtn = document.getElementById('vendorDrawerCloseBtn');
    const backdrop = document.getElementById('vendorDrawerBackdrop');
    const drawer = document.getElementById('vendorMobileDrawer');

    function openDrawer() { if (drawer) drawer.classList.remove('hidden'); }
    function closeDrawer() { if (drawer) drawer.classList.add('hidden'); }

    if (openBtn) openBtn.addEventListener('click', openDrawer);
    if (closeBtn) closeBtn.addEventListener('click', closeDrawer);
    if (backdrop) backdrop.addEventListener('click', closeDrawer);
});
</script>

<!-- Main Canvas -->
<div class="flex-1 flex flex-col min-w-0 w-full max-w-full h-screen overflow-y-auto overflow-x-hidden">
    <!-- Top Bar -->
    <header class="bg-white border-b border-slate-200 px-3.5 sm:px-6 py-3.5 flex items-center justify-between sticky top-0 z-30 shadow-xs">
        <div class="flex items-center gap-2.5">
            <button id="vendorMobileMenuBtn" type="button" aria-label="Open navigation menu" class="md:hidden p-2 rounded-xl text-slate-700 hover:bg-slate-100 hover:text-slate-900 cursor-pointer focus:outline-none focus:ring-2 focus:ring-indigo-500/30">
                <span class="material-symbols-outlined text-2xl">menu</span>
            </button>
            <a href="/vendor/dashboard.php" class="md:hidden flex items-center">
                <img src="/public/mentry.png" alt="Mentry" class="h-7 w-auto">
            </a>
            <span class="font-bold text-slate-900 text-sm hidden sm:inline">Partner Workspace</span>
            <span class="bg-indigo-50 text-indigo-700 text-[10px] sm:text-[11px] font-extrabold px-2.5 py-0.5 rounded-full border border-indigo-100">Client Portal</span>
        </div>
        <div class="flex items-center gap-2 sm:gap-3">
            <a href="/vendor/request-create.php" class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold px-3 sm:px-3.5 py-1.5 rounded-xl shadow-xs transition-colors flex items-center gap-1 shrink-0">
                <span class="material-symbols-outlined text-[16px]">add</span>
                <span class="hidden sm:inline">New Request</span>
                <span class="sm:hidden">Request</span>
            </a>
            <a href="/logout.php" class="text-xs text-rose-600 font-bold hover:underline shrink-0">Sign Out</a>
        </div>
    </header>

    <main class="flex-1 w-full max-w-7xl mx-auto px-3.5 sm:px-6 lg:px-8 py-6 md:py-10 space-y-6 md:space-y-8 min-w-0">

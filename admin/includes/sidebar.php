<?php
// admin/includes/sidebar.php
require_once __DIR__ . '/../../includes/auth.php';
requireAdminOrStaff();

$adminUser = getCurrentUser();
$currentPage = basename($_SERVER['PHP_SELF']);
$isStaffUser = isStaff();

$navItems = [
    ['label' => 'Dashboard', 'href' => '/admin/index.php', 'icon' => 'space_dashboard'],
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
<div class="flex-1 flex flex-col min-w-0 h-screen overflow-y-auto">
    <!-- Top Bar -->
    <header class="bg-white border-b border-slate-200 px-6 py-4 flex items-center justify-between sticky top-0 z-30 shadow-xs">
        <div class="flex items-center gap-3">
            <a href="/admin/index.php" class="md:hidden">
                <img src="/public/mentry.png" alt="Mentry" class="h-7 w-auto">
            </a>
            <span class="font-bold text-slate-900 text-sm hidden sm:inline">
                <?= $isStaffUser ? 'Operations Staff Dashboard' : 'Admin Command Center' ?>
            </span>
        </div>
        <div class="flex items-center gap-3">
            <!-- 1-Click Maintenance Mode Toggle -->
            <?php
            require_once __DIR__ . '/../../includes/maintenance.php';
            $maintConfig = getMaintenanceConfig();
            $isMaintActive = !empty($maintConfig['maintenance_mode']);
            ?>
            <form action="/actions/toggle-maintenance.php" method="POST" class="inline" onsubmit="return confirm('<?= $isMaintActive ? "Turn OFF Maintenance Mode and make website live for everyone?" : "Turn ON Maintenance Mode? Public visitors will be redirected to the Work in Progress page." ?>');">
                <input type="hidden" name="active" value="<?= $isMaintActive ? '0' : '1' ?>">
                <input type="hidden" name="return_url" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/admin/index.php') ?>">
                <?php if ($isMaintActive): ?>
                    <button type="submit" class="inline-flex items-center gap-1.5 bg-orange-100 hover:bg-orange-200 border border-[#FE5E04] text-[#FE5E04] text-xs font-black px-3 py-1.5 rounded-xl transition-colors shadow-xs cursor-pointer" title="Public visitors see Work in Progress. Click to turn off.">
                        <span class="w-2 h-2 rounded-full bg-[#FE5E04] animate-ping"></span>
                        Work in Progress: ACTIVE
                    </button>
                <?php else: ?>
                    <button type="submit" class="inline-flex items-center gap-1.5 bg-emerald-50 hover:bg-emerald-100 border border-emerald-300 text-emerald-800 text-xs font-bold px-3 py-1.5 rounded-xl transition-colors shadow-2xs cursor-pointer" title="Website is live. Click to activate maintenance mode.">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        Site Live
                    </button>
                <?php endif; ?>
            </form>

            <a href="/admin/requirements.php" class="text-xs font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 px-3 py-1.5 rounded-lg transition-colors hidden sm:inline">
                College Intake
            </a>
            <a href="/admin/opportunity-create.php" class="bg-[#FE5E04] hover:bg-[#E04E00] text-white text-xs font-bold px-3.5 py-1.5 rounded-lg shadow-xs transition-colors">
                + New Opp
            </a>
        </div>
    </header>

    <main class="flex-1 w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 md:py-10 space-y-8">

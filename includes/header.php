<?php
// includes/header.php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/maintenance.php';

// Gate public pages if maintenance mode is enabled
checkMaintenanceGate();

$currentUser = getCurrentUser();
$currentPage = basename($_SERVER['PHP_SELF']);

$unreadNotifs = 0;
if ($currentUser) {
    try {
        $notifCol = getCollection("Notification");
        if ($notifCol) {
            $unreadNotifs = $notifCol->countDocuments([
                'userId' => $currentUser['id'],
                'read' => false
            ]);
        }
    } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' | ' : '' ?>Mentry Solutions - Managed Trainer Network</title>
    
    <!-- Favicon & Brand Icons -->
    <link rel="icon" type="image/png" href="/public/mentry.png">
    <link rel="shortcut icon" type="image/png" href="/public/mentry.png">
    <link rel="apple-touch-icon" href="/public/mentry.png">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#fff7ed',
                            100: '#ffedd5',
                            200: '#fed7aa',
                            300: '#fdba74',
                            400: '#fb923c',
                            500: '#FE5E04',
                            600: '#e04e00',
                            700: '#c23e00',
                            800: '#9a3412',
                            950: '#431407'
                        }
                    },
                    boxShadow: {
                        card: "0 2px 10px -2px rgba(0, 0, 0, 0.04), 0 1px 3px -1px rgba(0, 0, 0, 0.02)",
                        "card-hover": "0 14px 30px -4px rgba(254, 94, 4, 0.12), 0 4px 12px -2px rgba(15, 23, 42, 0.04)"
                    }
                }
            }
        }
    </script>
    
    <!-- Google Fonts & Material Symbols -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: #ffffff;
            color: #0f172a;
            -webkit-font-smoothing: antialiased;
        }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
            line-height: 1;
            display: inline-block;
        }
        .material-symbols-outlined.fill, .icon-fill {
            font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        .mesh-bg {
            background-color: #ffffff;
            background-image: 
                radial-gradient(at 10% 10%, rgba(254, 94, 4, 0.06) 0px, transparent 50%),
                radial-gradient(at 90% 0%, rgba(251, 146, 60, 0.05) 0px, transparent 50%),
                radial-gradient(at 50% 50%, rgba(248, 250, 252, 0.7) 0px, transparent 50%),
                radial-gradient(at 80% 90%, rgba(254, 94, 4, 0.04) 0px, transparent 50%);
        }
    </style>
</head>
<body class="min-h-screen flex flex-col bg-white text-slate-900">
<?php require_once __DIR__ . '/loading_screen.php'; ?>

<!-- Navigation Header -->
<header class="sticky top-0 z-50 w-full bg-white/95 backdrop-blur-md border-b border-slate-200/80 shadow-xs transition-all">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-20">
            <!-- Brand Logo with mentry.png -->
            <a href="/index.php" class="flex items-center gap-3 group">
                <img src="/public/mentry.png" alt="Mentry Solutions Logo" class="h-11 w-auto object-contain rounded-lg transition-transform group-hover:scale-105">
                <div class="hidden sm:block">
                    <div class="flex items-center gap-1.5">
                        <span class="font-extrabold text-lg text-slate-900 tracking-tight leading-none group-hover:text-[#FE5E04] transition-colors">
                            Mentry Solutions
                        </span>
                    </div>
                    <span class="text-[11px] font-medium text-slate-500 tracking-wide block mt-0.5">
                        Managed Trainer Network
                    </span>
                </div>
            </a>

            <!-- Desktop Nav Links -->
            <nav class="hidden lg:flex items-center gap-1">
                <a href="/index.php" class="px-3 py-2 rounded-xl text-xs sm:text-sm font-semibold transition-all flex items-center gap-1.5 <?= ($currentPage === 'index.php' || $currentPage === '') ? 'text-[#FE5E04] bg-orange-50 font-bold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' ?>">
                    <span class="material-symbols-outlined text-[18px]">home</span>
                    <span>Home</span>
                </a>
                <a href="/opportunities.php" class="px-3 py-2 rounded-xl text-xs sm:text-sm font-semibold transition-all flex items-center gap-1.5 <?= $currentPage === 'opportunities.php' ? 'text-[#FE5E04] bg-orange-50 font-bold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' ?>">
                    <span class="material-symbols-outlined text-[18px]">work</span>
                    <span>Opportunities</span>
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse ml-0.5"></span>
                </a>
                <a href="/trainer-network.php" class="px-3 py-2 rounded-xl text-xs sm:text-sm font-semibold transition-all flex items-center gap-1.5 <?= $currentPage === 'trainer-network.php' ? 'text-[#FE5E04] bg-orange-50 font-bold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' ?>">
                    <span class="material-symbols-outlined text-[18px]">groups</span>
                    <span>Trainer Network</span>
                </a>
                <a href="/how-it-works.php" class="px-3 py-2 rounded-xl text-xs sm:text-sm font-semibold transition-all flex items-center gap-1.5 <?= $currentPage === 'how-it-works.php' ? 'text-[#FE5E04] bg-orange-50 font-bold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' ?>">
                    <span class="material-symbols-outlined text-[18px]">help</span>
                    <span>How It Works</span>
                </a>
                <a href="/submit-requirement.php" class="px-3 py-2 rounded-xl text-xs sm:text-sm font-semibold transition-all flex items-center gap-1.5 <?= $currentPage === 'submit-requirement.php' ? 'text-[#FE5E04] bg-orange-50 font-bold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' ?>">
                    <span class="material-symbols-outlined text-[18px]">school</span>
                    <span>For Colleges</span>
                </a>
                <a href="/about.php" class="px-3 py-2 rounded-xl text-xs sm:text-sm font-semibold transition-all flex items-center gap-1.5 <?= $currentPage === 'about.php' ? 'text-[#FE5E04] bg-orange-50 font-bold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' ?>">
                    <span class="material-symbols-outlined text-[18px]">info</span>
                    <span>About</span>
                </a>
                <a href="/contact.php" class="px-3 py-2 rounded-xl text-xs sm:text-sm font-semibold transition-all flex items-center gap-1.5 <?= $currentPage === 'contact.php' ? 'text-[#FE5E04] bg-orange-50 font-bold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' ?>">
                    <span class="material-symbols-outlined text-[18px]">mail</span>
                    <span>Contact</span>
                </a>
            </nav>

            <!-- Action CTAs -->
            <div class="hidden lg:flex items-center gap-2.5">
                <?php 
                $isHomePage = ($currentPage === 'index.php' || $currentPage === '' || $currentPage === '/');
                if ($currentUser && !$isHomePage): 
                    $dashboardUrl = '/trainer/dashboard.php';
                    if (in_array($currentUser['role'], ['ADMIN', 'SUPER_ADMIN', 'STAFF'])) {
                        $dashboardUrl = '/admin/index.php';
                    } elseif ($currentUser['role'] === 'VENDOR' || $currentUser['role'] === 'COLLEGE') {
                        $dashboardUrl = '/vendor/dashboard.php';
                    }
                ?>
                    <!-- Notifications Bell Badge -->
                    <a href="<?= in_array($currentUser['role'], ['ADMIN', 'STAFF']) ? '/admin/notifications.php' : '/trainer/notifications.php' ?>" class="relative p-2 text-slate-600 hover:text-[#FE5E04] hover:bg-orange-50 rounded-xl transition-colors">
                        <span class="material-symbols-outlined text-[22px]">notifications</span>
                        <?php if ($unreadNotifs > 0): ?>
                            <span class="absolute top-1.5 right-1.5 w-4 h-4 bg-[#FE5E04] text-white text-[10px] font-black rounded-full flex items-center justify-center">
                                <?= min(9, $unreadNotifs) ?>
                            </span>
                        <?php endif; ?>
                    </a>

                    <a href="<?= $dashboardUrl ?>" class="bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold px-4 py-2.5 rounded-xl transition-all shadow-sm flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px]">space_dashboard</span>
                        <span><?= in_array($currentUser['role'], ['ADMIN', 'STAFF']) ? 'Operations Center' : 'Dashboard' ?></span>
                    </a>
                <?php else: ?>
                    <a href="/login.php" class="text-slate-700 hover:text-[#FE5E04] text-xs font-bold px-3.5 py-2.5 rounded-xl border border-slate-200 hover:border-orange-300 hover:bg-orange-50/40 transition-all flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[17px]">login</span>
                        <span>Trainer Login</span>
                    </a>

                    <a href="/vendor-login.php" class="bg-[#FE5E04] hover:bg-[#E04E00] text-white text-xs font-bold px-4 py-2.5 rounded-xl shadow-md shadow-orange-500/25 hover:shadow-orange-500/40 transition-all flex items-center gap-1.5 hover:-translate-y-0.5">
                        <span class="material-symbols-outlined text-[17px]">business</span>
                        <span>College / Vendor Login</span>
                    </a>
                <?php endif; ?>
            </div>

            <!-- Mobile menu toggle -->
            <div class="flex lg:hidden items-center gap-2">
                <button id="mobileMenuToggleBtn" type="button" aria-label="Toggle navigation menu" class="p-2 rounded-xl text-slate-700 hover:bg-slate-100 cursor-pointer">
                    <span class="material-symbols-outlined text-2xl">menu</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile Dropdown -->
    <div id="mobileMenu" class="hidden lg:hidden bg-white border-b border-slate-200 px-4 pt-3 pb-6 space-y-1 shadow-xl">
        <a href="/index.php" class="flex items-center gap-2.5 px-4 py-2.5 rounded-xl text-sm font-semibold <?= ($currentPage === 'index.php' || $currentPage === '') ? 'text-[#FE5E04] bg-orange-50 font-bold' : 'text-slate-700 hover:bg-slate-50' ?>">
            <span class="material-symbols-outlined text-lg">home</span>
            <span>Home</span>
        </a>
        <a href="/opportunities.php" class="flex items-center gap-2.5 px-4 py-2.5 rounded-xl text-sm font-semibold <?= $currentPage === 'opportunities.php' ? 'text-[#FE5E04] bg-orange-50 font-bold' : 'text-slate-700 hover:bg-slate-50' ?>">
            <span class="material-symbols-outlined text-lg">work</span>
            <span>Opportunities</span>
        </a>
        <a href="/trainer-network.php" class="flex items-center gap-2.5 px-4 py-2.5 rounded-xl text-sm font-semibold <?= $currentPage === 'trainer-network.php' ? 'text-[#FE5E04] bg-orange-50 font-bold' : 'text-slate-700 hover:bg-slate-50' ?>">
            <span class="material-symbols-outlined text-lg">groups</span>
            <span>Trainer Network</span>
        </a>
        <a href="/how-it-works.php" class="flex items-center gap-2.5 px-4 py-2.5 rounded-xl text-sm font-semibold <?= $currentPage === 'how-it-works.php' ? 'text-[#FE5E04] bg-orange-50 font-bold' : 'text-slate-700 hover:bg-slate-50' ?>">
            <span class="material-symbols-outlined text-lg">help</span>
            <span>How It Works</span>
        </a>
        <a href="/submit-requirement.php" class="flex items-center gap-2.5 px-4 py-2.5 rounded-xl text-sm font-semibold <?= $currentPage === 'submit-requirement.php' ? 'text-[#FE5E04] bg-orange-50 font-bold' : 'text-slate-700 hover:bg-slate-50' ?>">
            <span class="material-symbols-outlined text-lg">school</span>
            <span>For Colleges</span>
        </a>
        <a href="/about.php" class="flex items-center gap-2.5 px-4 py-2.5 rounded-xl text-sm font-semibold <?= $currentPage === 'about.php' ? 'text-[#FE5E04] bg-orange-50 font-bold' : 'text-slate-700 hover:bg-slate-50' ?>">
            <span class="material-symbols-outlined text-lg">info</span>
            <span>About</span>
        </a>
        <a href="/contact.php" class="flex items-center gap-2.5 px-4 py-2.5 rounded-xl text-sm font-semibold <?= $currentPage === 'contact.php' ? 'text-[#FE5E04] bg-orange-50 font-bold' : 'text-slate-700 hover:bg-slate-50' ?>">
            <span class="material-symbols-outlined text-lg">mail</span>
            <span>Contact</span>
        </a>
        <div class="pt-2 border-t border-slate-100 flex flex-col gap-2">
            <?php if ($currentUser && !$isHomePage): ?>
                <a href="<?= in_array($currentUser['role'], ['ADMIN', 'STAFF']) ? '/admin/index.php' : '/trainer/dashboard.php' ?>" class="w-full bg-slate-900 text-white font-bold text-center py-2.5 rounded-xl text-sm">Go to Dashboard</a>
            <?php else: ?>
                <a href="/login.php" class="w-full border border-slate-200 hover:border-orange-300 text-slate-800 hover:text-[#FE5E04] font-bold text-center py-2.5 rounded-xl text-sm transition-colors flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">login</span>
                    <span>Trainer Login</span>
                </a>
                <a href="/vendor-login.php" class="w-full bg-[#FE5E04] hover:bg-[#E04E00] text-white font-bold text-center py-2.5 rounded-xl text-sm shadow-md transition-all flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">business</span>
                    <span>College / Vendor Login</span>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <script>
    (function() {
        const toggleBtn = document.getElementById('mobileMenuToggleBtn');
        const menu = document.getElementById('mobileMenu');

        if (toggleBtn && menu) {
            toggleBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                menu.classList.toggle('hidden');
            });

            // When user clicks anywhere except the hamburger toggle, close the menu
            document.addEventListener('click', function(e) {
                if (!menu.classList.contains('hidden')) {
                    if (!toggleBtn.contains(e.target) && !menu.contains(e.target)) {
                        menu.classList.add('hidden');
                    }
                }
            });

            // Also close menu when any link inside it is clicked
            menu.querySelectorAll('a').forEach(function(link) {
                link.addEventListener('click', function() {
                    menu.classList.add('hidden');
                });
            });
        }
    })();
    </script>
</header>

<main class="flex-grow">

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
            if (in_array($currentUser['role'] ?? '', ['ADMIN', 'STAFF'])) {
                $unreadNotifs = $notifCol->countDocuments([
                    '$or' => [
                        ['isAdminAlert' => true, 'read' => false],
                        ['recipientRole' => 'ADMIN', 'read' => false],
                        ['userId' => $currentUser['id'] ?? '', 'read' => false]
                    ]
                ]);
            } else {
                $unreadNotifs = $notifCol->countDocuments([
                    'userId' => $currentUser['id'],
                    'read' => false
                ]);
            }
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

    <!-- Search Engine & AI Discovery Metadata -->
    <meta name="description" content="Mentry Solutions is India's premier AI-powered managed trainer network. We connect verified technical trainers with engineering colleges, universities, and corporate institutions for on-campus and remote technical workshops, placement training, and bootcamps.">
    <meta name="keywords" content="technical trainer network, campus placement trainer, IT faculty hiring, college training vendors, corporate trainer India, Python DSA trainer, DevOps trainer, AI ML bootcamps, Chennai technical trainers, Bangalore campus training">
    <meta name="author" content="Mentry Solutions">
    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
    <link rel="canonical" href="https://mentry-solutions.vercel.app<?= htmlspecialchars(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH)) ?>">

    <!-- Geographic & Regional Metadata for AI Search (Gemini, ChatGPT, Perplexity, Google SGE) -->
    <meta name="geo.region" content="IN-TN">
    <meta name="geo.placename" content="Chennai, Tamil Nadu, India">
    <meta name="geo.position" content="13.0827;80.2707">
    <meta name="ICBM" content="13.0827, 80.2707">
    <meta name="country" content="India">
    <meta name="coverage" content="India, South India, Tamil Nadu, Karnataka, Telangana, Kerala, Andhra Pradesh, Maharashtra">

    <!-- Open Graph (Facebook, LinkedIn, AI Social Previews) -->
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Mentry Solutions">
    <meta property="og:title" content="<?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' | ' : '' ?>Mentry Solutions - Managed Trainer Network">
    <meta property="og:description" content="India's premier AI-powered technical trainer network and faculty deployment platform for engineering colleges, universities, and enterprises.">
    <meta property="og:url" content="https://mentry-solutions.vercel.app<?= htmlspecialchars(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH)) ?>">
    <meta property="og:image" content="https://mentry-solutions.vercel.app/public/mentry.png">
    <meta property="og:locale" content="en_IN">

    <!-- Twitter / X Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' | ' : '' ?>Mentry Solutions">
    <meta name="twitter:description" content="Empowering colleges and institutions with verified technical faculty, campus bootcamps, and curriculum delivery across India.">
    <meta name="twitter:image" content="https://mentry-solutions.vercel.app/public/mentry.png">

    <!-- Schema.org JSON-LD Structured Data for AI & Search Engine Rich Snippets -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@graph": [
        {
          "@type": "EducationalOrganization",
          "@id": "https://mentry-solutions.vercel.app/#organization",
          "name": "Mentry Solutions",
          "alternateName": ["Mentry", "Mentry Managed Trainer Network", "Mentry India"],
          "url": "https://mentry-solutions.vercel.app",
          "logo": {
            "@type": "ImageObject",
            "url": "https://mentry-solutions.vercel.app/public/mentry.png"
          },
          "description": "India's premier AI-driven technical trainer network and managed faculty deployment ecosystem connecting vetted industry instructors with universities, colleges, and corporations.",
          "address": {
            "@type": "PostalAddress",
            "addressLocality": "Chennai",
            "addressRegion": "Tamil Nadu",
            "addressCountry": "IN"
          },
          "geo": {
            "@type": "GeoCoordinates",
            "latitude": "13.0827",
            "longitude": "80.2707"
          },
          "areaServed": [
            {"@type": "AdministrativeArea", "name": "Tamil Nadu"},
            {"@type": "AdministrativeArea", "name": "Karnataka"},
            {"@type": "AdministrativeArea", "name": "Telangana"},
            {"@type": "AdministrativeArea", "name": "Kerala"},
            {"@type": "AdministrativeArea", "name": "Andhra Pradesh"},
            {"@type": "AdministrativeArea", "name": "Maharashtra"},
            {"@type": "Country", "name": "India"}
          ],
          "knowsAbout": [
            "Technical Training Delivery",
            "Python Data Structures & Algorithms",
            "Full Stack Web Development",
            "Cloud Computing and DevOps",
            "Artificial Intelligence & Machine Learning",
            "Cybersecurity & Ethical Hacking",
            "Campus Placement Preparation",
            "Corporate Upskilling"
          ],
          "contactPoint": {
            "@type": "ContactPoint",
            "email": "mentry.training@gmail.com",
            "contactType": "customer service",
            "areaServed": "IN",
            "availableLanguage": ["English", "Tamil", "Hindi"]
          }
        },
        {
          "@type": "WebSite",
          "@id": "https://mentry-solutions.vercel.app/#website",
          "url": "https://mentry-solutions.vercel.app",
          "name": "Mentry Solutions",
          "publisher": {
            "@id": "https://mentry-solutions.vercel.app/#organization"
          }
        }
      ]
    }
    </script>

    <!-- Favicon & Brand Icons -->
    <link rel="icon" type="image/png" href="/public/icon-192.png?v=3">
    <link rel="shortcut icon" href="/favicon.ico?v=2">
    <link rel="apple-touch-icon" href="/public/icon-192.png?v=3">

    <!-- Progressive Web App (PWA) Manifest & Standalone App Capabilities -->
    <link rel="manifest" href="/manifest.json?v=3">
    <meta name="theme-color" content="#FFFFFF">
    <meta name="background-color" content="#FFFFFF">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Mentry">

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
        html, body {
            max-width: 100%;
            overflow-x: clip;
        }
        *, *::before, *::after {
            box-sizing: border-box;
        }
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
<body class="min-h-screen flex flex-col bg-white text-slate-900 w-full max-w-full overflow-x-clip">
<?php if (file_exists(__DIR__ . '/loading_screen.php')) include __DIR__ . '/loading_screen.php'; ?>

<?php 
$headerMaint = getMaintenanceConfig();
if (!empty($headerMaint['maintenance_mode']) && isAdminOrStaff()):
?>
    <div class="bg-gradient-to-r from-orange-600 to-amber-600 text-white text-xs font-bold px-4 py-2.5 flex flex-wrap items-center justify-between gap-2 shadow-md z-[100] sticky top-0">
        <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-[18px] animate-pulse">engineering</span>
            <span><strong>MAINTENANCE MODE IS ACTIVE:</strong> Public visitors, trainers, and colleges are currently blocked and seeing the maintenance page. You are viewing with Administrator bypass.</span>
        </div>
        <div class="flex items-center gap-2.5 shrink-0">
            <a href="/maintenance.php?preview=1" target="_blank" class="bg-white/20 hover:bg-white/30 text-white px-3 py-1 rounded-xl transition-colors inline-flex items-center gap-1">
                <span>Preview Public View</span>
                <span class="material-symbols-outlined text-[14px]">open_in_new</span>
            </a>
            <a href="/admin/settings.php" class="bg-white text-orange-700 hover:bg-orange-50 px-3 py-1 rounded-xl transition-colors font-black">
                Manage Mode
            </a>
        </div>
    </div>
<?php endif; ?>

<!-- Navigation Header -->
<header class="sticky top-0 z-50 w-full px-2.5 sm:px-6 lg:px-8 pt-2.5 sm:pt-4 pb-2 transition-all">
    <div class="max-w-[1420px] mx-auto">
        <div class="bg-white border border-slate-200/90 rounded-2xl lg:rounded-full shadow-[0_4px_25px_rgba(0,0,0,0.06)] px-2.5 sm:px-5 lg:px-6 py-2 sm:py-2.5 transition-all">
            <div class="flex items-center justify-between gap-1.5 sm:gap-2 lg:gap-3">
                <!-- Brand Logo (Mobile: MENTRY only, Desktop: MENTRY Solutions / Managed Trainer Network) -->
                <div class="flex items-center gap-2 sm:gap-3 shrink-0">
                    <a href="/index.php" class="flex items-center gap-2 sm:gap-2.5 group shrink-0">
                        <img src="/public/mentry.png" alt="Mentry Solutions Logo" class="h-7 sm:h-9 w-auto object-contain transition-transform group-hover:scale-105 shrink-0">
                        <!-- Mobile screen view (< sm): Just "MENTRY" -->
                        <span class="sm:hidden font-black text-[14px] tracking-wide text-slate-900 leading-none group-hover:text-[#FE5E04] transition-colors">
                            MENTRY
                        </span>
                        <!-- Tablet / Desktop view (sm and up): Full brand name and subtitle -->
                        <div class="hidden sm:block">
                            <span class="font-extrabold text-[14px] sm:text-[15px] text-slate-900 tracking-tight leading-none block group-hover:text-[#FE5E04] transition-colors">
                                <span class="font-black">MENTRY</span> Solutions
                            </span>
                            <span class="text-[9.5px] sm:text-[11px] font-medium text-slate-500 tracking-normal block mt-1 leading-none">
                                Managed Trainer Network
                            </span>
                        </div>
                    </a>
                    <div class="hidden lg:block h-6 w-px bg-slate-200 ml-3 mr-1"></div>
                </div>

                <!-- Desktop Nav Links (Matches screenshot with icons and soft orange pill active state) -->
                <nav class="hidden lg:flex items-center gap-0.5 xl:gap-1">
                    <a href="/index.php" class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs transition-all <?= ($currentPage === 'index.php' || $currentPage === '') ? 'text-[#FE5E04] bg-[#FFF2EB] font-bold shadow-2xs' : 'text-slate-600 font-semibold hover:text-slate-900 hover:bg-slate-50' ?>">
                        <span class="material-symbols-outlined text-[17px] <?= ($currentPage === 'index.php' || $currentPage === '') ? 'text-[#FE5E04]' : 'text-slate-500' ?>" <?= ($currentPage === 'index.php' || $currentPage === '') ? 'style="font-variation-settings: \'FILL\' 1;"' : '' ?>>home</span>
                        <span>Home</span>
                    </a>
                    <a href="/opportunities.php" class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs transition-all <?= $currentPage === 'opportunities.php' ? 'text-[#FE5E04] bg-[#FFF2EB] font-bold shadow-2xs' : 'text-slate-600 font-semibold hover:text-slate-900 hover:bg-slate-50' ?>">
                        <span class="material-symbols-outlined text-[17px] <?= $currentPage === 'opportunities.php' ? 'text-[#FE5E04]' : 'text-slate-500' ?>" <?= $currentPage === 'opportunities.php' ? 'style="font-variation-settings: \'FILL\' 1;"' : '' ?>>work</span>
                        <span>Opportunities</span>
                    </a>
                    <a href="/trainer-network.php" class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs transition-all <?= $currentPage === 'trainer-network.php' ? 'text-[#FE5E04] bg-[#FFF2EB] font-bold shadow-2xs' : 'text-slate-600 font-semibold hover:text-slate-900 hover:bg-slate-50' ?>">
                        <span class="material-symbols-outlined text-[17px] <?= $currentPage === 'trainer-network.php' ? 'text-[#FE5E04]' : 'text-slate-500' ?>" <?= $currentPage === 'trainer-network.php' ? 'style="font-variation-settings: \'FILL\' 1;"' : '' ?>>groups</span>
                        <span>Trainer Network</span>
                    </a>
                    <a href="/how-it-works.php" class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs transition-all <?= $currentPage === 'how-it-works.php' ? 'text-[#FE5E04] bg-[#FFF2EB] font-bold shadow-2xs' : 'text-slate-600 font-semibold hover:text-slate-900 hover:bg-slate-50' ?>">
                        <span class="material-symbols-outlined text-[17px] <?= $currentPage === 'how-it-works.php' ? 'text-[#FE5E04]' : 'text-slate-500' ?>" <?= $currentPage === 'how-it-works.php' ? 'style="font-variation-settings: \'FILL\' 1;"' : '' ?>>help</span>
                        <span>How It Works</span>
                    </a>
                    <a href="/submit-requirement.php" class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs transition-all <?= $currentPage === 'submit-requirement.php' ? 'text-[#FE5E04] bg-[#FFF2EB] font-bold shadow-2xs' : 'text-slate-600 font-semibold hover:text-slate-900 hover:bg-slate-50' ?>">
                        <span class="material-symbols-outlined text-[17px] <?= $currentPage === 'submit-requirement.php' ? 'text-[#FE5E04]' : 'text-slate-500' ?>" <?= $currentPage === 'submit-requirement.php' ? 'style="font-variation-settings: \'FILL\' 1;"' : '' ?>>school</span>
                        <span>For Colleges</span>
                    </a>
                    <a href="/about.php" class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs transition-all <?= $currentPage === 'about.php' ? 'text-[#FE5E04] bg-[#FFF2EB] font-bold shadow-2xs' : 'text-slate-600 font-semibold hover:text-slate-900 hover:bg-slate-50' ?>">
                        <span class="material-symbols-outlined text-[17px] <?= $currentPage === 'about.php' ? 'text-[#FE5E04]' : 'text-slate-500' ?>" <?= $currentPage === 'about.php' ? 'style="font-variation-settings: \'FILL\' 1;"' : '' ?>>info</span>
                        <span>About</span>
                    </a>
                    <a href="/contact.php" class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs transition-all <?= $currentPage === 'contact.php' ? 'text-[#FE5E04] bg-[#FFF2EB] font-bold shadow-2xs' : 'text-slate-600 font-semibold hover:text-slate-900 hover:bg-slate-50' ?>">
                        <span class="material-symbols-outlined text-[17px] <?= $currentPage === 'contact.php' ? 'text-[#FE5E04]' : 'text-slate-500' ?>" <?= $currentPage === 'contact.php' ? 'style="font-variation-settings: \'FILL\' 1;"' : '' ?>>mail</span>
                        <span>Contact</span>
                    </a>
                </nav>

                <!-- Action CTAs (Download, Notification Bell with badge, Navy Dashboard button, Mobile Menu) -->
                <div class="flex items-center gap-1.5 sm:gap-2.5 shrink-0">
                    <div class="hidden lg:block h-6 w-px bg-slate-200 mr-1.5"></div>

                    <!-- Download App Button -->
                    <button type="button" onclick="window.promptPWAInstall()" class="w-8 h-8 sm:w-10 sm:h-10 rounded-xl sm:rounded-2xl text-slate-700 hover:text-[#FE5E04] bg-white hover:bg-orange-50/70 border border-slate-200 hover:border-orange-300 transition-all cursor-pointer shrink-0 shadow-2xs flex items-center justify-center" title="Download & Install Mentry App (Add to Home Screen)">
                        <span class="material-symbols-outlined text-[18px] sm:text-[20px] text-[#FE5E04]">download</span>
                    </button>

                    <?php 
                    if ($currentUser): 
                        $dashboardUrl = '/trainer/dashboard.php';
                        if (in_array($currentUser['role'], ['ADMIN', 'SUPER_ADMIN', 'STAFF'])) {
                            $dashboardUrl = '/admin/index.php';
                        } elseif ($currentUser['role'] === 'VENDOR' || $currentUser['role'] === 'COLLEGE') {
                            $dashboardUrl = '/vendor/dashboard.php';
                        }
                    ?>
                        <!-- Notifications Bell Badge -->
                        <a href="<?= in_array($currentUser['role'], ['ADMIN', 'STAFF']) ? '/admin/notifications.php' : '/trainer/notifications.php' ?>" class="relative w-8 h-8 sm:w-10 sm:h-10 text-slate-700 hover:text-[#FE5E04] bg-white border border-slate-200 hover:border-orange-300 rounded-xl sm:rounded-2xl transition-all shadow-2xs flex items-center justify-center shrink-0" title="Notifications">
                            <span class="material-symbols-outlined text-[18px] sm:text-[20px]">notifications</span>
                            <?php if ($unreadNotifs > 0): ?>
                                <span class="absolute -top-1 -right-1 min-w-[16px] sm:min-w-[18px] h-4 sm:h-[18px] px-1 bg-[#FE5E04] text-white text-[9px] sm:text-[10px] font-black rounded-full flex items-center justify-center shadow-xs leading-none">
                                    <?= min(99, $unreadNotifs) ?>
                                </span>
                            <?php endif; ?>
                        </a>

                        <!-- Dashboard Button -->
                        <a href="<?= $dashboardUrl ?>" class="bg-[#182A4A] hover:bg-[#0F1B30] text-white text-[11px] sm:text-xs font-bold px-2.5 sm:px-4 py-1.5 sm:py-2.5 rounded-xl sm:rounded-2xl transition-all shadow-sm flex items-center gap-1.5 sm:gap-2 shrink-0">
                            <span class="material-symbols-outlined text-[16px] sm:text-[18px]">space_dashboard</span>
                            <span>Dashboard</span>
                        </a>
                    <?php else: ?>
                        <!-- Trainer Login: Outline blue button -->
                        <a href="/login.php" class="hidden sm:inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold border border-blue-400 text-blue-600 bg-white hover:bg-blue-50/70 hover:border-blue-500 transition-all shrink-0">
                            <span class="material-symbols-outlined text-[16px] text-blue-600">login</span>
                            <span>Trainer Login</span>
                        </a>

                        <!-- College / Vendor Login: Solid brand orange pill with apartment & chevron icons -->
                        <a href="/vendor-login.php" class="inline-flex items-center gap-1 sm:gap-2 px-2.5 sm:px-3.5 py-1.5 sm:py-2 rounded-xl text-xs font-bold bg-[#FE5E04] hover:bg-[#E04E00] text-white shadow-sm hover:shadow transition-all shrink-0">
                            <span class="material-symbols-outlined text-[16px] text-white shrink-0">apartment</span>
                            <span class="text-left text-[11px] leading-tight font-bold shrink-0 hidden xs:inline">
                                College /<br>Vendor Login
                            </span>
                            <span class="text-[11px] font-bold xs:hidden shrink-0">Login</span>
                            <span class="material-symbols-outlined text-[14px] text-white shrink-0">chevron_right</span>
                        </a>
                    <?php endif; ?>

                    <!-- Mobile menu toggle -->
                    <div class="flex lg:hidden items-center shrink-0">
                        <button id="mobileMenuToggleBtn" type="button" aria-label="Toggle navigation menu" class="w-8 h-8 sm:w-10 sm:h-10 rounded-xl bg-slate-100/90 hover:bg-slate-200 text-slate-800 transition-colors cursor-pointer flex items-center justify-center shrink-0">
                            <span class="material-symbols-outlined text-[20px] sm:text-[23px]">menu</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mobile Menu Dropdown (Matches Screenshot 2: Clean Card Items with Chevrons and Action Buttons) -->
        <div id="mobileMenu" class="hidden lg:hidden mt-2.5 bg-white border border-slate-200/90 rounded-3xl p-3 sm:p-4 space-y-2 shadow-2xl transition-all">
            <!-- Home -->
            <a href="/index.php" class="flex items-center justify-between p-2.5 sm:p-3 rounded-2xl transition-all <?= ($currentPage === 'index.php' || $currentPage === '') ? 'bg-[#FFF3EC]' : 'bg-white hover:bg-slate-50 border border-slate-100' ?>">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl flex items-center justify-center shrink-0 <?= ($currentPage === 'index.php' || $currentPage === '') ? 'bg-[#FFE4D6] text-[#FE5E04]' : 'bg-slate-100 text-slate-700' ?>">
                        <span class="material-symbols-outlined text-[20px] sm:text-[22px]" <?= ($currentPage === 'index.php' || $currentPage === '') ? 'style="font-variation-settings: \'FILL\' 1;"' : '' ?>>home</span>
                    </div>
                    <span class="text-sm sm:text-[15px] <?= ($currentPage === 'index.php' || $currentPage === '') ? 'font-extrabold text-[#FE5E04]' : 'font-bold text-slate-800' ?>">Home</span>
                </div>
                <span class="material-symbols-outlined text-[18px] sm:text-[20px] <?= ($currentPage === 'index.php' || $currentPage === '') ? 'text-[#FE5E04]' : 'text-slate-400' ?>">chevron_right</span>
            </a>

            <!-- Opportunities -->
            <a href="/opportunities.php" class="flex items-center justify-between p-2.5 sm:p-3 rounded-2xl transition-all <?= $currentPage === 'opportunities.php' ? 'bg-[#FFF3EC]' : 'bg-white hover:bg-slate-50 border border-slate-100' ?>">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl flex items-center justify-center shrink-0 <?= $currentPage === 'opportunities.php' ? 'bg-[#FFE4D6] text-[#FE5E04]' : 'bg-slate-100 text-slate-700' ?>">
                        <span class="material-symbols-outlined text-[20px] sm:text-[22px]" <?= $currentPage === 'opportunities.php' ? 'style="font-variation-settings: \'FILL\' 1;"' : '' ?>>business_center</span>
                    </div>
                    <span class="text-sm sm:text-[15px] <?= $currentPage === 'opportunities.php' ? 'font-extrabold text-[#FE5E04]' : 'font-bold text-slate-800' ?>">Opportunities</span>
                </div>
                <span class="material-symbols-outlined text-[18px] sm:text-[20px] <?= $currentPage === 'opportunities.php' ? 'text-[#FE5E04]' : 'text-slate-400' ?>">chevron_right</span>
            </a>

            <!-- Trainer Network -->
            <a href="/trainer-network.php" class="flex items-center justify-between p-2.5 sm:p-3 rounded-2xl transition-all <?= $currentPage === 'trainer-network.php' ? 'bg-[#FFF3EC]' : 'bg-white hover:bg-slate-50 border border-slate-100' ?>">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl flex items-center justify-center shrink-0 <?= $currentPage === 'trainer-network.php' ? 'bg-[#FFE4D6] text-[#FE5E04]' : 'bg-slate-100 text-slate-700' ?>">
                        <span class="material-symbols-outlined text-[20px] sm:text-[22px]" <?= $currentPage === 'trainer-network.php' ? 'style="font-variation-settings: \'FILL\' 1;"' : '' ?>>groups</span>
                    </div>
                    <span class="text-sm sm:text-[15px] <?= $currentPage === 'trainer-network.php' ? 'font-extrabold text-[#FE5E04]' : 'font-bold text-slate-800' ?>">Trainer Network</span>
                </div>
                <span class="material-symbols-outlined text-[18px] sm:text-[20px] <?= $currentPage === 'trainer-network.php' ? 'text-[#FE5E04]' : 'text-slate-400' ?>">chevron_right</span>
            </a>

            <!-- How It Works -->
            <a href="/how-it-works.php" class="flex items-center justify-between p-2.5 sm:p-3 rounded-2xl transition-all <?= $currentPage === 'how-it-works.php' ? 'bg-[#FFF3EC]' : 'bg-white hover:bg-slate-50 border border-slate-100' ?>">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl flex items-center justify-center shrink-0 <?= $currentPage === 'how-it-works.php' ? 'bg-[#FFE4D6] text-[#FE5E04]' : 'bg-slate-100 text-slate-700' ?>">
                        <span class="material-symbols-outlined text-[20px] sm:text-[22px]" <?= $currentPage === 'how-it-works.php' ? 'style="font-variation-settings: \'FILL\' 1;"' : '' ?>>help</span>
                    </div>
                    <span class="text-sm sm:text-[15px] <?= $currentPage === 'how-it-works.php' ? 'font-extrabold text-[#FE5E04]' : 'font-bold text-slate-800' ?>">How It Works</span>
                </div>
                <span class="material-symbols-outlined text-[18px] sm:text-[20px] <?= $currentPage === 'how-it-works.php' ? 'text-[#FE5E04]' : 'text-slate-400' ?>">chevron_right</span>
            </a>

            <!-- For Colleges -->
            <a href="/submit-requirement.php" class="flex items-center justify-between p-2.5 sm:p-3 rounded-2xl transition-all <?= $currentPage === 'submit-requirement.php' ? 'bg-[#FFF3EC]' : 'bg-white hover:bg-slate-50 border border-slate-100' ?>">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl flex items-center justify-center shrink-0 <?= $currentPage === 'submit-requirement.php' ? 'bg-[#FFE4D6] text-[#FE5E04]' : 'bg-slate-100 text-slate-700' ?>">
                        <span class="material-symbols-outlined text-[20px] sm:text-[22px]" <?= $currentPage === 'submit-requirement.php' ? 'style="font-variation-settings: \'FILL\' 1;"' : '' ?>>school</span>
                    </div>
                    <span class="text-sm sm:text-[15px] <?= $currentPage === 'submit-requirement.php' ? 'font-extrabold text-[#FE5E04]' : 'font-bold text-slate-800' ?>">For Colleges</span>
                </div>
                <span class="material-symbols-outlined text-[18px] sm:text-[20px] <?= $currentPage === 'submit-requirement.php' ? 'text-[#FE5E04]' : 'text-slate-400' ?>">chevron_right</span>
            </a>

            <!-- About -->
            <a href="/about.php" class="flex items-center justify-between p-2.5 sm:p-3 rounded-2xl transition-all <?= $currentPage === 'about.php' ? 'bg-[#FFF3EC]' : 'bg-white hover:bg-slate-50 border border-slate-100' ?>">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl flex items-center justify-center shrink-0 <?= $currentPage === 'about.php' ? 'bg-[#FFE4D6] text-[#FE5E04]' : 'bg-slate-100 text-slate-700' ?>">
                        <span class="material-symbols-outlined text-[20px] sm:text-[22px]" <?= $currentPage === 'about.php' ? 'style="font-variation-settings: \'FILL\' 1;"' : '' ?>>info</span>
                    </div>
                    <span class="text-sm sm:text-[15px] <?= $currentPage === 'about.php' ? 'font-extrabold text-[#FE5E04]' : 'font-bold text-slate-800' ?>">About</span>
                </div>
                <span class="material-symbols-outlined text-[18px] sm:text-[20px] <?= $currentPage === 'about.php' ? 'text-[#FE5E04]' : 'text-slate-400' ?>">chevron_right</span>
            </a>

            <!-- Contact -->
            <a href="/contact.php" class="flex items-center justify-between p-2.5 sm:p-3 rounded-2xl transition-all <?= $currentPage === 'contact.php' ? 'bg-[#FFF3EC]' : 'bg-white hover:bg-slate-50 border border-slate-100' ?>">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl flex items-center justify-center shrink-0 <?= $currentPage === 'contact.php' ? 'bg-[#FFE4D6] text-[#FE5E04]' : 'bg-slate-100 text-slate-700' ?>">
                        <span class="material-symbols-outlined text-[20px] sm:text-[22px]" <?= $currentPage === 'contact.php' ? 'style="font-variation-settings: \'FILL\' 1;"' : '' ?>>mail</span>
                    </div>
                    <span class="text-sm sm:text-[15px] <?= $currentPage === 'contact.php' ? 'font-extrabold text-[#FE5E04]' : 'font-bold text-slate-800' ?>">Contact</span>
                </div>
                <span class="material-symbols-outlined text-[18px] sm:text-[20px] <?= $currentPage === 'contact.php' ? 'text-[#FE5E04]' : 'text-slate-400' ?>">chevron_right</span>
            </a>

            <!-- Prominent Bottom Action Buttons matching Screenshot 2 -->
            <div class="pt-2 space-y-2">
                <!-- Download Mentry App (Add to Home Screen) -->
                <button type="button" onclick="window.promptPWAInstall(); document.getElementById('mobileMenu').classList.add('hidden');" class="w-full flex items-center justify-between p-3 sm:p-3.5 rounded-2xl bg-[#0B1526] hover:bg-[#132238] text-white transition-all shadow-sm cursor-pointer">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl bg-[#182A4A] flex items-center justify-center text-[#FE5E04] shrink-0">
                            <span class="material-symbols-outlined text-[20px] sm:text-[22px]">download</span>
                        </div>
                        <span class="text-[13px] sm:text-[14px] font-bold text-white text-left">Download Mentry App (Add to Home Screen)</span>
                    </div>
                    <span class="material-symbols-outlined text-white/80 text-[18px] sm:text-[20px] shrink-0">chevron_right</span>
                </button>

                <!-- Go to Dashboard (When Logged In) -->
                <?php if ($currentUser): ?>
                    <a href="<?= $dashboardUrl ?>" class="w-full flex items-center justify-between p-3 sm:p-3.5 rounded-2xl bg-gradient-to-r from-[#FE5E04] via-[#FF6A00] to-[#FF7D1A] text-white transition-all shadow-md shadow-orange-500/20 cursor-pointer">
                        <div class="flex items-center gap-3">
                            <img src="<?= htmlspecialchars(getUserAvatar($currentUser, 64)) ?>" 
                                 alt="<?= htmlspecialchars($currentUser['name'] ?? 'User') ?>" 
                                 class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl object-cover border border-white/30 shrink-0 bg-white/20 shadow-xs"
                                 style="object-position: center 15%;"
                                 referrerpolicy="no-referrer"
                                 loading="lazy"
                                 onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=<?= urlencode($currentUser['name'] ?? 'User') ?>&background=FE5E04&color=fff&size=64';">
                            <div class="text-left leading-tight">
                                <span class="text-sm sm:text-[15px] font-bold text-white block">Go to Dashboard</span>
                                <span class="text-[11px] text-white/80 font-medium truncate max-w-[170px] block"><?= htmlspecialchars($currentUser['name'] ?? '') ?></span>
                            </div>
                        </div>
                        <span class="material-symbols-outlined text-white text-[18px] sm:text-[20px] shrink-0">chevron_right</span>
                    </a>
                <?php else: ?>
                    <!-- College / Vendor Login (Solid Brand Orange Gradient) -->
                    <a href="/vendor-login.php" class="w-full flex items-center justify-between p-3 sm:p-3.5 rounded-2xl bg-gradient-to-r from-[#FE5E04] via-[#FF6A00] to-[#FF7D1A] text-white transition-all shadow-md shadow-orange-500/20 cursor-pointer">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl bg-white/20 flex items-center justify-center text-white shrink-0">
                                <span class="material-symbols-outlined text-[20px] sm:text-[22px]">apartment</span>
                            </div>
                            <span class="text-sm sm:text-[15px] font-bold text-white">College / Vendor Login</span>
                        </div>
                        <span class="material-symbols-outlined text-white text-[18px] sm:text-[20px] shrink-0">chevron_right</span>
                    </a>

                    <!-- Trainer Login (Crisp Border Card) -->
                    <a href="/login.php" class="w-full flex items-center justify-between p-3 sm:p-3.5 rounded-2xl bg-white hover:bg-slate-50 border border-blue-200 text-blue-700 transition-all cursor-pointer">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl bg-blue-50 flex items-center justify-center text-blue-600 shrink-0">
                                <span class="material-symbols-outlined text-[20px] sm:text-[22px]">login</span>
                            </div>
                            <span class="text-sm sm:text-[15px] font-bold text-blue-700">Trainer Login</span>
                        </div>
                        <span class="material-symbols-outlined text-blue-400 text-[18px] sm:text-[20px] shrink-0">chevron_right</span>
                    </a>
                <?php endif; ?>
            </div>
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

<?php require_once __DIR__ . '/download_loader.php'; ?>
<?php if (file_exists(__DIR__ . '/pwa_install_prompt.php')) include __DIR__ . '/pwa_install_prompt.php'; ?>
<?php if (file_exists(__DIR__ . '/offline_popup.php')) include __DIR__ . '/offline_popup.php'; ?>

<main class="flex-grow">

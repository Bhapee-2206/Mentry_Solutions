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
    <link rel="icon" type="image/png" href="/public/mentry.png?v=2">
    <link rel="shortcut icon" href="/favicon.ico?v=2">
    <link rel="apple-touch-icon" href="/public/mentry.png?v=2">

    <!-- Progressive Web App (PWA) Manifest & Standalone App Capabilities -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#FE5E04">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
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
<header class="sticky top-0 z-50 w-full px-3 sm:px-6 lg:px-8 pt-3 sm:pt-4 pb-2 transition-all">
    <div class="max-w-[1400px] mx-auto">
        <div class="bg-white/95 backdrop-blur-md border border-slate-200/90 rounded-2xl lg:rounded-full shadow-[0_4px_25px_rgba(0,0,0,0.06)] px-4 sm:px-5 lg:px-6 py-2.5 sm:py-3 transition-all">
            <div class="flex items-center justify-between">
                <!-- Brand Logo with mentry.png -->
                <div class="flex items-center gap-2 sm:gap-3 shrink-0">
                    <a href="/index.php" class="flex items-center gap-2.5 sm:gap-3 group">
                        <img src="/public/mentry.png" alt="Mentry Solutions Logo" class="h-9 sm:h-10 w-auto object-contain transition-transform group-hover:scale-105">
                        <div class="hidden sm:block">
                            <span class="font-extrabold text-base sm:text-[17px] text-slate-900 tracking-tight leading-none block group-hover:text-[#FE5E04] transition-colors">
                                Mentry Solutions
                            </span>
                            <span class="text-[10px] sm:text-[11px] font-medium text-slate-500 tracking-normal block mt-0.5">
                                Managed Trainer Network
                            </span>
                        </div>
                    </a>
                    <div class="hidden xl:block h-7 w-px bg-slate-200 mx-1 lg:mx-2"></div>
                </div>

                <!-- Desktop Nav Links -->
                <nav class="hidden lg:flex items-center gap-0.5 xl:gap-1.5">
                    <a href="/index.php" class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold transition-all <?= ($currentPage === 'index.php' || $currentPage === '') ? 'text-[#FE5E04] bg-[#FFF3EC] font-bold shadow-2xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' ?>">
                        <span class="material-symbols-outlined text-[18px] <?= ($currentPage === 'index.php' || $currentPage === '') ? 'text-[#FE5E04]' : 'text-slate-500' ?>" <?= ($currentPage === 'index.php' || $currentPage === '') ? 'style="font-variation-settings: \'FILL\' 1;"' : '' ?>>home</span>
                        <span>Home</span>
                    </a>
                    <a href="/opportunities.php" class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold transition-all <?= $currentPage === 'opportunities.php' ? 'text-[#FE5E04] bg-[#FFF3EC] font-bold shadow-2xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' ?>">
                        <span class="material-symbols-outlined text-[18px] <?= $currentPage === 'opportunities.php' ? 'text-[#FE5E04]' : 'text-slate-500' ?>" <?= $currentPage === 'opportunities.php' ? 'style="font-variation-settings: \'FILL\' 1;"' : '' ?>>work</span>
                        <span>Opportunities</span>
                    </a>
                    <a href="/trainer-network.php" class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold transition-all <?= $currentPage === 'trainer-network.php' ? 'text-[#FE5E04] bg-[#FFF3EC] font-bold shadow-2xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' ?>">
                        <span class="material-symbols-outlined text-[18px] <?= $currentPage === 'trainer-network.php' ? 'text-[#FE5E04]' : 'text-slate-500' ?>" <?= $currentPage === 'trainer-network.php' ? 'style="font-variation-settings: \'FILL\' 1;"' : '' ?>>groups</span>
                        <span>Trainer Network</span>
                    </a>
                    <a href="/how-it-works.php" class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold transition-all <?= $currentPage === 'how-it-works.php' ? 'text-[#FE5E04] bg-[#FFF3EC] font-bold shadow-2xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' ?>">
                        <span class="material-symbols-outlined text-[18px] <?= $currentPage === 'how-it-works.php' ? 'text-[#FE5E04]' : 'text-slate-500' ?>" <?= $currentPage === 'how-it-works.php' ? 'style="font-variation-settings: \'FILL\' 1;"' : '' ?>>help</span>
                        <span>How It Works</span>
                    </a>
                    <a href="/submit-requirement.php" class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold transition-all <?= $currentPage === 'submit-requirement.php' ? 'text-[#FE5E04] bg-[#FFF3EC] font-bold shadow-2xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' ?>">
                        <span class="material-symbols-outlined text-[18px] <?= $currentPage === 'submit-requirement.php' ? 'text-[#FE5E04]' : 'text-slate-500' ?>" <?= $currentPage === 'submit-requirement.php' ? 'style="font-variation-settings: \'FILL\' 1;"' : '' ?>>school</span>
                        <span>For Colleges</span>
                    </a>
                    <a href="/about.php" class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold transition-all <?= $currentPage === 'about.php' ? 'text-[#FE5E04] bg-[#FFF3EC] font-bold shadow-2xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' ?>">
                        <span class="material-symbols-outlined text-[18px] <?= $currentPage === 'about.php' ? 'text-[#FE5E04]' : 'text-slate-500' ?>" <?= $currentPage === 'about.php' ? 'style="font-variation-settings: \'FILL\' 1;"' : '' ?>>info</span>
                        <span>About</span>
                    </a>
                    <a href="/contact.php" class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold transition-all <?= $currentPage === 'contact.php' ? 'text-[#FE5E04] bg-[#FFF3EC] font-bold shadow-2xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' ?>">
                        <span class="material-symbols-outlined text-[18px] <?= $currentPage === 'contact.php' ? 'text-[#FE5E04]' : 'text-slate-500' ?>" <?= $currentPage === 'contact.php' ? 'style="font-variation-settings: \'FILL\' 1;"' : '' ?>>mail</span>
                        <span>Contact</span>
                    </a>
                </nav>

                <!-- Action CTAs -->
                <div class="flex items-center gap-2 sm:gap-2.5 shrink-0">
                    <div class="hidden xl:block h-7 w-px bg-slate-200 mx-1"></div>

                    <!-- Download App Button -->
                    <button type="button" onclick="window.promptPWAInstall()" class="inline-flex items-center gap-1.5 px-2.5 sm:px-3 py-2 rounded-xl text-xs font-bold text-slate-700 hover:text-[#FE5E04] bg-white hover:bg-orange-50/70 border border-slate-200 hover:border-orange-300 transition-all cursor-pointer shrink-0 shadow-2xs" title="Download & Install Mentry App (Add to Home Screen)">
                        <span class="material-symbols-outlined text-[17px] text-[#FE5E04]">download</span>
                        <span class="hidden 2xl:inline font-bold">Download</span>
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
                        <a href="<?= in_array($currentUser['role'], ['ADMIN', 'STAFF']) ? '/admin/notifications.php' : '/trainer/notifications.php' ?>" class="relative p-2 text-slate-600 hover:text-[#FE5E04] hover:bg-orange-50 rounded-xl transition-colors" title="Notifications">
                            <span class="material-symbols-outlined text-[20px]">notifications</span>
                            <?php if ($unreadNotifs > 0): ?>
                                <span class="absolute top-1.5 right-1.5 w-4 h-4 bg-[#FE5E04] text-white text-[10px] font-black rounded-full flex items-center justify-center">
                                    <?= min(9, $unreadNotifs) ?>
                                </span>
                            <?php endif; ?>
                        </a>

                        <a href="<?= $dashboardUrl ?>" class="bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold px-3.5 py-2 rounded-xl transition-all shadow-sm flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-[17px]">space_dashboard</span>
                            <span><?= in_array($currentUser['role'], ['ADMIN', 'STAFF']) ? 'Operations Center' : 'Dashboard' ?></span>
                        </a>
                    <?php else: ?>
                        <!-- Trainer Login: Outline blue button -->
                        <a href="/login.php" class="hidden sm:inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold border border-blue-400 text-blue-600 bg-white hover:bg-blue-50/70 hover:border-blue-500 transition-all shrink-0">
                            <span class="material-symbols-outlined text-[16px] text-blue-600">login</span>
                            <span>Trainer Login</span>
                        </a>

                        <!-- College / Vendor Login: Solid brand orange pill with apartment & chevron icons -->
                        <a href="/vendor-login.php" class="inline-flex items-center gap-2 px-3.5 py-1.5 sm:py-2 rounded-xl text-xs font-bold bg-[#FE5E04] hover:bg-[#E04E00] text-white shadow-sm hover:shadow transition-all shrink-0">
                            <span class="material-symbols-outlined text-[18px] text-white shrink-0">apartment</span>
                            <span class="text-left text-[11px] leading-tight font-bold shrink-0">
                                College /<br>Vendor Login
                            </span>
                            <span class="material-symbols-outlined text-[16px] text-white shrink-0">chevron_right</span>
                        </a>
                    <?php endif; ?>

                    <!-- Mobile menu toggle -->
                    <div class="flex lg:hidden items-center gap-1.5">
                        <button id="mobileMenuToggleBtn" type="button" aria-label="Toggle navigation menu" class="p-2 rounded-xl text-slate-700 hover:bg-slate-100 cursor-pointer">
                            <span class="material-symbols-outlined text-2xl">menu</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mobile Dropdown -->
        <div id="mobileMenu" class="hidden lg:hidden mt-2 bg-white border border-slate-200/90 rounded-2xl p-4 space-y-1 shadow-xl">
            <a href="/index.php" class="flex items-center gap-2.5 px-3.5 py-2 rounded-xl text-sm font-semibold <?= ($currentPage === 'index.php' || $currentPage === '') ? 'text-[#FE5E04] bg-[#FFF3EC] font-bold' : 'text-slate-700 hover:bg-slate-50' ?>">
                <span class="material-symbols-outlined text-lg">home</span>
                <span>Home</span>
            </a>
            <a href="/opportunities.php" class="flex items-center gap-2.5 px-3.5 py-2 rounded-xl text-sm font-semibold <?= $currentPage === 'opportunities.php' ? 'text-[#FE5E04] bg-[#FFF3EC] font-bold' : 'text-slate-700 hover:bg-slate-50' ?>">
                <span class="material-symbols-outlined text-lg">work</span>
                <span>Opportunities</span>
            </a>
            <a href="/trainer-network.php" class="flex items-center gap-2.5 px-3.5 py-2 rounded-xl text-sm font-semibold <?= $currentPage === 'trainer-network.php' ? 'text-[#FE5E04] bg-[#FFF3EC] font-bold' : 'text-slate-700 hover:bg-slate-50' ?>">
                <span class="material-symbols-outlined text-lg">groups</span>
                <span>Trainer Network</span>
            </a>
            <a href="/how-it-works.php" class="flex items-center gap-2.5 px-3.5 py-2 rounded-xl text-sm font-semibold <?= $currentPage === 'how-it-works.php' ? 'text-[#FE5E04] bg-[#FFF3EC] font-bold' : 'text-slate-700 hover:bg-slate-50' ?>">
                <span class="material-symbols-outlined text-lg">help</span>
                <span>How It Works</span>
            </a>
            <a href="/submit-requirement.php" class="flex items-center gap-2.5 px-3.5 py-2 rounded-xl text-sm font-semibold <?= $currentPage === 'submit-requirement.php' ? 'text-[#FE5E04] bg-[#FFF3EC] font-bold' : 'text-slate-700 hover:bg-slate-50' ?>">
                <span class="material-symbols-outlined text-lg">school</span>
                <span>For Colleges</span>
            </a>
            <a href="/about.php" class="flex items-center gap-2.5 px-3.5 py-2 rounded-xl text-sm font-semibold <?= $currentPage === 'about.php' ? 'text-[#FE5E04] bg-[#FFF3EC] font-bold' : 'text-slate-700 hover:bg-slate-50' ?>">
                <span class="material-symbols-outlined text-lg">info</span>
                <span>About</span>
            </a>
            <a href="/contact.php" class="flex items-center gap-2.5 px-3.5 py-2 rounded-xl text-sm font-semibold <?= $currentPage === 'contact.php' ? 'text-[#FE5E04] bg-[#FFF3EC] font-bold' : 'text-slate-700 hover:bg-slate-50' ?>">
                <span class="material-symbols-outlined text-lg">mail</span>
                <span>Contact</span>
            </a>
            <div class="pt-2.5 border-t border-slate-100 flex flex-col gap-2">
                <button type="button" onclick="window.promptPWAInstall(); document.getElementById('mobileMenu').classList.add('hidden');" class="w-full bg-slate-900 text-white font-bold text-center py-2.5 rounded-xl text-sm transition-all flex items-center justify-center gap-2 border border-slate-800">
                    <span class="material-symbols-outlined text-[18px] text-[#FE5E04]">download</span>
                    <span>Download Mentry App (Add to Home Screen)</span>
                </button>
                <?php if ($currentUser): ?>
                    <a href="<?= in_array($currentUser['role'], ['ADMIN', 'STAFF']) ? '/admin/index.php' : '/trainer/dashboard.php' ?>" class="w-full bg-[#FE5E04] text-white font-bold text-center py-2.5 rounded-xl text-sm">Go to Dashboard</a>
                <?php else: ?>
                    <a href="/login.php" class="w-full border border-blue-400 text-blue-600 hover:bg-blue-50/60 font-bold text-center py-2.5 rounded-xl text-sm transition-colors flex items-center justify-center gap-2">
                        <span class="material-symbols-outlined text-[18px]">login</span>
                        <span>Trainer Login</span>
                    </a>
                    <a href="/vendor-login.php" class="w-full bg-[#FE5E04] hover:bg-[#E04E00] text-white font-bold text-center py-2.5 rounded-xl text-sm shadow-md transition-all flex items-center justify-center gap-2">
                        <span class="material-symbols-outlined text-[18px]">apartment</span>
                        <span>College / Vendor Login</span>
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

<main class="flex-grow">

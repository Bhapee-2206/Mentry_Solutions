<?php
/*
 * Mentry Solutions — PWA Splash Screen
 * Single-file PHP version.
 */
$redirect_url = '/index.php?source=pwa';
$redirect_delay = 2600; // milliseconds
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#ffffff">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<title>Mentry Solutions</title>

<!-- Tailwind CSS -->
<script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="m-0 p-0 w-full h-full overflow-hidden bg-[#F8FBFF] select-none font-sans">

<main class="fixed inset-0 z-[99999] bg-gradient-to-b from-[#FFFFFF] via-[#F8FBFF] to-[#EFF6FF] flex flex-col items-center justify-between overflow-hidden" aria-label="Mentry Solutions loading screen">
    <!-- Ambient glowing light orbs -->
    <div class="absolute -top-20 -left-20 w-80 h-80 sm:w-96 sm:h-96 rounded-full bg-blue-100/50 blur-3xl pointer-events-none"></div>
    <div class="absolute top-1/4 -right-16 w-72 h-72 rounded-full bg-sky-100/60 blur-3xl pointer-events-none"></div>
    <div class="absolute inset-0 m-auto w-80 h-80 sm:w-96 sm:h-96 rounded-full bg-blue-50/40 blur-2xl pointer-events-none"></div>

    <!-- Central Hero Branding & Spinner -->
    <div class="relative z-10 flex-1 flex flex-col items-center justify-center px-6 text-center max-w-md w-full min-h-0 pt-6 pb-2">
        <!-- Authentic Mentry PNG Logo -->
        <div class="relative flex items-center justify-center mb-2">
            <img src="/public/mentry-emblem.png" alt="Mentry Logo" class="w-28 h-28 sm:w-36 sm:h-36 object-contain drop-shadow-md">
        </div>

        <!-- "Mentry Solutions" Title -->
        <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight text-[#0B1526] mt-1 mb-2">
            Mentry <span class="text-[#0284C7]">Solutions</span>
        </h1>

        <!-- Subtitle with side divider rules (Perfect horizontal alignment) -->
        <div class="flex items-center justify-center gap-3 w-full max-w-[320px] sm:max-w-sm px-2 mb-6">
            <div class="h-0.5 bg-[#A9C8E8] flex-1 rounded-full"></div>
            <div class="text-center shrink-0">
                <p class="text-xs sm:text-[13px] font-semibold text-[#38557F] tracking-wide leading-tight">Managed Trainer Network</p>
                <p class="text-xs sm:text-[13px] font-semibold text-[#38557F] tracking-wide leading-tight">& Professional Training Services</p>
            </div>
            <div class="h-0.5 bg-[#A9C8E8] flex-1 rounded-full"></div>
        </div>

        <!-- Circular Spinner & Loading Text (Sitting comfortably on clean background, never covered by waves) -->
        <div class="flex flex-col items-center justify-center gap-3 mt-3">
            <div class="w-11 h-11 sm:w-12 sm:h-12 rounded-full border-[4px] border-[#DBEEFF] border-t-[#087CFF] border-r-[#55C8F4] animate-spin"></div>
            <span class="text-xs sm:text-sm font-semibold text-[#48688F] tracking-[0.2em] uppercase">Loading...</span>
        </div>
    </div>

    <!-- Layered Bottom Wave Graphics with Motto -->
    <div class="relative w-full z-10 overflow-hidden leading-none select-none pointer-events-none mt-auto">
        <svg class="w-full h-32 sm:h-44 md:h-52 block" viewBox="0 0 1000 280" preserveAspectRatio="none">
            <defs>
                <linearGradient id="waveCyanGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                    <stop offset="0%" stop-color="#0284C7" />
                    <stop offset="50%" stop-color="#0077F5" />
                    <stop offset="100%" stop-color="#005BDB" />
                </linearGradient>
                <linearGradient id="waveRoyalGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stop-color="#0052CC" />
                    <stop offset="60%" stop-color="#003D99" />
                    <stop offset="100%" stop-color="#082352" />
                </linearGradient>
                <linearGradient id="waveNavyGrad" x1="0%" y1="0%" x2="50%" y2="100%">
                    <stop offset="0%" stop-color="#082352" />
                    <stop offset="40%" stop-color="#051838" />
                    <stop offset="100%" stop-color="#030E24" />
                </linearGradient>
            </defs>

            <path d="M0,130 C220,70 440,220 720,150 C860,110 950,150 1000,130 L1000,280 L0,280 Z" fill="url(#waveCyanGrad)" opacity="0.92"></path>
            <path d="M0,165 C250,110 490,240 770,180 C890,150 960,180 1000,165 L1000,280 L0,280 Z" fill="url(#waveRoyalGrad)" opacity="0.96"></path>
            <path d="M0,200 C280,150 530,265 830,215 C920,195 970,215 1000,205 L1000,280 L0,280 Z" fill="url(#waveNavyGrad)"></path>
        </svg>

        <div class="absolute bottom-4 sm:bottom-6 inset-x-0 text-center px-4">
            <p class="text-[10px] sm:text-[11px] font-bold text-white/95 tracking-[0.25em] uppercase drop-shadow-sm">
                LEARN &nbsp;&nbsp;•&nbsp;&nbsp; TEACH &nbsp;&nbsp;•&nbsp;&nbsp; GROW &nbsp;&nbsp;TOGETHER
            </p>
        </div>
    </div>
</main>

<?php if (!empty($redirect_url)): ?>
<script>
setTimeout(function(){
    window.location.href = <?= json_encode($redirect_url) ?>;
}, <?= (int)$redirect_delay ?>);
</script>
<?php endif; ?>

</body>
</html>

<?php
// 500.php - Custom 500 Internal Server Error Page
http_response_code(500);
$pageTitle = "Internal Server Error (500)";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 - System Temporary Error | Mentry Solutions</title>
    <!-- Favicon & Brand Icons -->
    <link rel="icon" type="image/png" href="/public/mentry.png?v=2">
    <link rel="shortcut icon" href="/favicon.ico?v=2">
    <link rel="apple-touch-icon" href="/public/mentry.png?v=2">
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .mesh-gradient {
            background: radial-gradient(circle at 50% 20%, rgba(254, 94, 4, 0.15) 0%, transparent 60%),
                        radial-gradient(circle at 80% 80%, rgba(15, 23, 42, 0.8) 0%, transparent 50%),
                        #070D18;
        }
    </style>
</head>
<body class="min-h-screen mesh-gradient text-slate-100 flex flex-col justify-between selection:bg-[#FE5E04] selection:text-white">

    <!-- Top Navigation Header -->
    <header class="w-full border-b border-slate-800/80 bg-slate-900/60 backdrop-blur-xl px-6 py-4">
        <div class="max-w-6xl mx-auto flex items-center justify-between">
            <a href="/index.php" class="flex items-center gap-3 group">
                <div class="bg-white p-1 rounded-xl shadow-md group-hover:scale-105 transition-transform">
                    <img src="/public/mentry.png" alt="Mentry" class="h-8 w-auto">
                </div>
                <div>
                    <span class="font-extrabold text-base text-white tracking-tight block">Mentry Solutions</span>
                    <span class="text-[10px] font-medium text-slate-400">Managed Trainer Network</span>
                </div>
            </a>

            <div class="flex items-center gap-3">
                <a href="/index.php" class="text-xs font-bold text-slate-300 hover:text-white transition-colors hidden sm:inline">
                    Homepage
                </a>
                <a href="/contact.php" class="bg-[#FE5E04] hover:bg-[#E04E00] text-white font-bold text-xs px-4 py-2 rounded-xl transition-all shadow-md shadow-orange-500/20">
                    Contact Support
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col items-center justify-center px-4 py-12 text-center max-w-2xl mx-auto space-y-8">
        <!-- 500 Badge & Glowing Number -->
        <div class="space-y-2">
            <span class="inline-flex items-center gap-1.5 bg-rose-500/15 text-rose-400 border border-rose-500/30 text-xs font-black uppercase tracking-widest px-4 py-1.5 rounded-full">
                <span class="w-2 h-2 rounded-full bg-rose-500 animate-ping"></span>
                Error 500 • System Encountered an Issue
            </span>

            <div class="relative py-4">
                <h1 class="text-8xl sm:text-9xl font-black tracking-tighter text-transparent bg-clip-text bg-gradient-to-b from-white via-slate-200 to-slate-500 drop-shadow-2xl select-none">
                    5<span class="text-[#FE5E04]">0</span>0
                </h1>
                <div class="absolute inset-0 -z-10 blur-3xl bg-rose-500/20 rounded-full"></div>
            </div>
        </div>

        <div class="space-y-3">
            <h2 class="text-2xl sm:text-3xl font-black text-white tracking-tight">
                Temporary Processing Hiccup
            </h2>
            <p class="text-sm text-slate-400 max-w-lg mx-auto leading-relaxed">
                Our automated systems have logged this event. Our technical operations team has been notified. Please try refreshing or returning to the homepage.
            </p>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-wrap items-center justify-center gap-3 w-full pt-2">
            <a href="javascript:location.reload()" class="bg-white hover:bg-slate-100 text-slate-900 font-bold text-xs px-5 py-3 rounded-xl transition-all flex items-center gap-2 shadow-sm">
                <span class="material-symbols-outlined text-base">refresh</span>
                <span>Try Again</span>
            </a>
            <a href="/index.php" class="bg-slate-800 hover:bg-slate-700 text-white font-bold text-xs px-5 py-3 rounded-xl transition-all border border-slate-700 flex items-center gap-2">
                <span class="material-symbols-outlined text-base">home</span>
                <span>Return to Home</span>
            </a>
            <a href="/contact.php" class="bg-[#FE5E04] hover:bg-[#E04E00] text-white font-bold text-xs px-5 py-3 rounded-xl transition-all shadow-md shadow-orange-500/20 flex items-center gap-2">
                <span class="material-symbols-outlined text-base">support_agent</span>
                <span>Report Issue</span>
            </a>
        </div>
    </main>

    <!-- Footer -->
    <footer class="w-full border-t border-slate-800/80 py-6 text-center text-xs text-slate-500">
        <p>&copy; <?= date('Y') ?> Mentry Solutions. Managed Trainer Network. All rights reserved.</p>
    </footer>

</body>
</html>

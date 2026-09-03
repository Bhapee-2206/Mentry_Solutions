<?php
// 404.php - Custom 404 Error Page
http_response_code(404);
$pageTitle = "Page Not Found (404)";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found | Mentry Solutions</title>
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
                <a href="/opportunities.php" class="text-xs font-bold text-slate-300 hover:text-white transition-colors hidden sm:inline">
                    Browse Opportunities
                </a>
                <a href="/login.php" class="bg-slate-800 hover:bg-slate-700 text-white font-bold text-xs px-4 py-2 rounded-xl transition-all border border-slate-700">
                    Trainer Login
                </a>
                <a href="/register.php" class="bg-[#FE5E04] hover:bg-[#E04E00] text-white font-bold text-xs px-4 py-2 rounded-xl transition-all shadow-md shadow-orange-500/20">
                    Join Network
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col items-center justify-center px-4 py-12 text-center max-w-2xl mx-auto space-y-8">
        <!-- 404 Badge & Glowing Number -->
        <div class="space-y-2">
            <span class="inline-flex items-center gap-1.5 bg-[#FE5E04]/15 text-[#FE5E04] border border-[#FE5E04]/30 text-xs font-black uppercase tracking-widest px-4 py-1.5 rounded-full">
                <span class="w-2 h-2 rounded-full bg-[#FE5E04] animate-ping"></span>
                Error 404 • Resource Not Found
            </span>

            <div class="relative py-4">
                <h1 class="text-8xl sm:text-9xl font-black tracking-tighter text-transparent bg-clip-text bg-gradient-to-b from-white via-slate-200 to-slate-500 drop-shadow-2xl select-none">
                    4<span class="text-[#FE5E04]">0</span>4
                </h1>
                <div class="absolute inset-0 -z-10 blur-3xl bg-[#FE5E04]/20 rounded-full"></div>
            </div>
        </div>

        <div class="space-y-3">
            <h2 class="text-2xl sm:text-3xl font-black text-white tracking-tight">
                Lost in the Campus Trainer Network?
            </h2>
            <p class="text-sm text-slate-400 max-w-lg mx-auto leading-relaxed">
                The page, faculty requirement, or dossier you're trying to reach doesn't exist, has been concluded, or requires authorized sign in.
            </p>
        </div>

        <!-- Search Bar -->
        <form action="/opportunities.php" method="GET" class="w-full max-w-md mx-auto">
            <div class="relative flex items-center">
                <span class="material-symbols-outlined absolute left-4 text-slate-400 text-xl">search</span>
                <input type="text" name="search" placeholder="Search workshops, Python, VLSI, Cloud..." class="w-full bg-slate-900/90 border border-slate-700/90 rounded-2xl py-3.5 pl-12 pr-28 text-xs text-white placeholder-slate-500 outline-none focus:border-[#FE5E04] focus:ring-2 focus:ring-[#FE5E04]/20 transition-all">
                <button type="submit" class="absolute right-2 bg-[#FE5E04] hover:bg-[#E04E00] text-white font-bold text-xs px-4 py-2 rounded-xl transition-all shadow-xs">
                    Search
                </button>
            </div>
        </form>

        <!-- Quick Help Destination Cards -->
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 w-full text-left pt-2">
            <a href="/index.php" class="p-4 rounded-2xl bg-slate-900/70 hover:bg-slate-800 border border-slate-800 hover:border-slate-700 transition-all group">
                <div class="w-8 h-8 rounded-xl bg-orange-500/10 text-[#FE5E04] flex items-center justify-center mb-2 group-hover:scale-110 transition-transform">
                    <span class="material-symbols-outlined text-lg">home</span>
                </div>
                <h4 class="font-bold text-xs text-white">Homepage</h4>
                <p class="text-[10px] text-slate-400 mt-0.5">Return to public portal</p>
            </a>

            <a href="/opportunities.php" class="p-4 rounded-2xl bg-slate-900/70 hover:bg-slate-800 border border-slate-800 hover:border-slate-700 transition-all group">
                <div class="w-8 h-8 rounded-xl bg-blue-500/10 text-blue-400 flex items-center justify-center mb-2 group-hover:scale-110 transition-transform">
                    <span class="material-symbols-outlined text-lg">work</span>
                </div>
                <h4 class="font-bold text-xs text-white">Opportunities</h4>
                <p class="text-[10px] text-slate-400 mt-0.5">Explore active workshops</p>
            </a>

            <a href="/contact.php" class="col-span-2 sm:col-span-1 p-4 rounded-2xl bg-slate-900/70 hover:bg-slate-800 border border-slate-800 hover:border-slate-700 transition-all group">
                <div class="w-8 h-8 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center mb-2 group-hover:scale-110 transition-transform">
                    <span class="material-symbols-outlined text-lg">support_agent</span>
                </div>
                <h4 class="font-bold text-xs text-white">Support & Contact</h4>
                <p class="text-[10px] text-slate-400 mt-0.5">Reach Mentry operations</p>
            </a>
        </div>
    </main>

    <!-- Footer -->
    <footer class="w-full border-t border-slate-800/80 py-6 text-center text-xs text-slate-500">
        <p>&copy; <?= date('Y') ?> Mentry Solutions. Managed Trainer Network. All rights reserved.</p>
    </footer>

</body>
</html>

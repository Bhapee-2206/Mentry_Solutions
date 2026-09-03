<?php
// maintenance.php - Work in Progress & System Maintenance Page (Light Theme with Dynamic Animations)
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/maintenance.php';

$config = getMaintenanceConfig();
$isStaffOrAdmin = isAdminOrStaff();

// If maintenance is turned off and not admin/staff, redirect to homepage
if (!$config['maintenance_mode'] && !$isStaffOrAdmin) {
    header("Location: /index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scheduled Upgrades & Optimization | Mentry Solutions</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#FFF7ED',
                            100: '#FFEDD5',
                            500: '#FE5E04',
                            600: '#E04E00',
                            700: '#C23E00'
                        }
                    },
                    keyframes: {
                        floatSlow: {
                            '0%, 100%': { transform: 'translateY(0px) rotate(0deg)' },
                            '50%': { transform: 'translateY(-10px) rotate(1.5deg)' }
                        },
                        serverBob: {
                            '0%, 100%': { transform: 'translateY(0px)' },
                            '50%': { transform: 'translateY(-6px)' }
                        },
                        cableWave: {
                            '0%, 100%': { transform: 'rotate(0deg)' },
                            '50%': { transform: 'rotate(-4deg)' }
                        },
                        shimmer: {
                            '0%': { backgroundPosition: '-200% 0' },
                            '100%': { backgroundPosition: '200% 0' }
                        }
                    },
                    animation: {
                        'float-slow': 'floatSlow 4s ease-in-out infinite',
                        'server-bob': 'serverBob 2.5s ease-in-out infinite',
                        'cable-wave': 'cableWave 3s ease-in-out infinite'
                    }
                }
            }
        }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', 'Inter', sans-serif; }
        
        .light-mesh {
            background-color: #FBFBFC;
            background-image: 
                radial-gradient(at 10% 10%, rgba(254, 94, 4, 0.08) 0px, transparent 50%),
                radial-gradient(at 90% 15%, rgba(59, 130, 246, 0.07) 0px, transparent 45%),
                radial-gradient(at 50% 90%, rgba(245, 158, 11, 0.08) 0px, transparent 50%);
        }

        .dot-pattern {
            background-image: radial-gradient(#CBD5E1 1.2px, transparent 1.2px);
            background-size: 24px 24px;
        }

        .led-blink-fast {
            animation: blinkFast 1.2s infinite ease-in-out;
        }
        .led-blink-slow {
            animation: blinkSlow 2s infinite ease-in-out;
        }
        @keyframes blinkFast {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.3; transform: scale(0.85); }
        }
        @keyframes blinkSlow {
            0%, 100% { opacity: 0.4; }
            50% { opacity: 1; }
        }
    </style>
</head>
<body class="min-h-screen light-mesh text-slate-800 flex flex-col justify-between selection:bg-[#FE5E04] selection:text-white relative overflow-x-hidden">

    <!-- Subtle Background Dot Grid -->
    <div class="absolute inset-0 dot-pattern opacity-40 pointer-events-none -z-10"></div>

    <?php if ($isStaffOrAdmin): ?>
        <!-- Admin Bypass Notice Banner -->
        <div class="w-full bg-[#FE5E04] text-white py-2.5 px-4 text-xs font-bold shadow-sm relative z-50">
            <div class="max-w-6xl mx-auto flex flex-wrap items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-sm bg-white/20 p-1 rounded-md">lock_open</span>
                    <span>Work in Progress Mode is currently <strong>ACTIVE</strong> for visitors. You have Admin privileges.</span>
                </div>
                <a href="/admin/index.php" class="bg-white text-[#FE5E04] px-3 py-1 rounded-lg hover:bg-orange-50 transition-colors font-extrabold flex items-center gap-1 shadow-xs">
                    Return to Ops Center →
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Top Header -->
    <header class="w-full border-b border-slate-200/80 bg-white/80 backdrop-blur-xl px-6 py-4 sticky top-0 z-40 shadow-xs">
        <div class="max-w-6xl mx-auto flex items-center justify-between">
            <a href="/index.php" class="flex items-center gap-3 group">
                <div class="bg-white p-1 rounded-xl shadow-sm border border-slate-200/80 group-hover:shadow-md transition-all">
                    <img src="/public/mentry.png" alt="Mentry" class="h-8 w-auto">
                </div>
                <div>
                    <span class="font-black text-base text-slate-900 tracking-tight block">Mentry Solutions</span>
                    <span class="text-[11px] font-semibold text-slate-500">India's Premier Managed Trainer Network</span>
                </div>
            </a>

            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-2 bg-amber-50 text-amber-800 border border-amber-200 text-xs font-black px-3.5 py-1.5 rounded-full shadow-xs">
                    <span class="w-2 h-2 rounded-full bg-amber-500 animate-ping"></span>
                    <span>Work In Progress</span>
                </span>
            </div>
        </div>
    </header>

    <!-- Main Content Canvas -->
    <main class="flex-1 max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10 md:py-16 flex flex-col lg:flex-row items-center justify-center gap-12 lg:gap-16">
        
        <!-- Left Column: Animated Server Engineer Illustration -->
        <div class="w-full max-w-md lg:max-w-lg shrink-0 flex justify-center relative">
            
            <!-- Warm Backdrop Canvas Card -->
            <div class="relative w-full max-w-[380px] bg-gradient-to-b from-[#F59E0B] via-[#EA580C] to-[#C2410C] rounded-[40px] p-6 sm:p-8 shadow-2xl shadow-orange-500/20 overflow-hidden group">
                
                <!-- Background Decorative Rings -->
                <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full border-8 border-white/10 pointer-events-none animate-pulse"></div>
                <div class="absolute -left-12 -bottom-12 w-52 h-52 rounded-full border-8 border-white/10 pointer-events-none"></div>

                <!-- Animated Character & Server Rack Graphic (SVG) -->
                <div class="relative z-10 flex justify-center items-center py-4">
                    <svg viewBox="0 0 320 380" class="w-full h-auto drop-shadow-xl animate-float-slow select-none" fill="none" xmlns="http://www.w3.org/2000/svg">
                        
                        <!-- Floating Energy Particles -->
                        <circle cx="50" cy="80" r="4" fill="#FEF08A" class="animate-ping" style="animation-duration: 3s;" />
                        <circle cx="280" cy="140" r="5" fill="#FFFFFF" opacity="0.8" />
                        <circle cx="40" cy="240" r="3" fill="#FED7AA" />
                        
                        <!-- Walking Engineer Body -->
                        <!-- Legs & Shoes -->
                        <path d="M120 220 L75 340 L50 340" stroke="#0F172A" stroke-width="26" stroke-linecap="round" stroke-linejoin="round" />
                        <path d="M140 220 L195 340 L225 340" stroke="#0F172A" stroke-width="26" stroke-linecap="round" stroke-linejoin="round" />
                        <!-- Green Shoes -->
                        <path d="M38 340 H68 C74 340 78 346 76 352 L74 358 H34 C30 358 28 350 32 344 Z" fill="#059669" />
                        <path d="M210 340 H240 C246 340 250 346 248 352 L246 358 H206 C202 358 200 350 204 344 Z" fill="#059669" />

                        <!-- Torso (Star Pattern Sweater) -->
                        <path d="M100 135 C100 120 145 120 155 135 L170 230 L95 230 Z" fill="#FEF3C7" stroke="#0F172A" stroke-width="4" stroke-linejoin="round" />
                        <!-- Green Star Motifs on Sweater -->
                        <polygon points="120,150 123,158 132,158 125,163 128,171 120,166 112,171 115,163 108,158 117,158" fill="#15803D" />
                        <polygon points="145,185 147,192 154,192 148,196 150,203 145,199 140,203 142,196 136,192 143,192" fill="#15803D" />
                        <polygon points="110,195 112,201 118,201 113,205 115,211 110,207 105,211 107,205 102,201 108,201" fill="#15803D" />

                        <!-- Head & Glasses -->
                        <circle cx="102" cy="118" r="18" fill="#FDBA74" stroke="#0F172A" stroke-width="3" />
                        <!-- Black Hair -->
                        <path d="M86 116 C86 98 118 96 118 112 C114 104 96 104 92 118 Z" fill="#0F172A" />
                        <!-- Eye & Glasses -->
                        <circle cx="108" cy="116" r="4" fill="none" stroke="#0F172A" stroke-width="2.5" />
                        <circle cx="109" cy="116" r="1.5" fill="#0F172A" />
                        <path d="M104 116 H100" stroke="#0F172A" stroke-width="2" />

                        <!-- Arms Carrying Servers -->
                        <path d="M130 145 Q160 170 190 175" stroke="#FDBA74" stroke-width="14" stroke-linecap="round" fill="none" />
                        <path d="M110 145 Q150 195 200 185" stroke="#FDBA74" stroke-width="14" stroke-linecap="round" fill="none" />

                        <!-- 3 Stacked Blue Server Racks (Animated Bobbing) -->
                        <g class="animate-server-bob">
                            <!-- Server 3 (Top Rack) -->
                            <g transform="translate(115, 65) rotate(-14)">
                                <rect x="0" y="0" width="115" height="38" rx="8" fill="#1D4ED8" stroke="#0F172A" stroke-width="3.5" />
                                <rect x="8" y="8" width="5" height="22" rx="2" fill="#FBBF24" />
                                <rect x="16" y="8" width="5" height="22" rx="2" fill="#FBBF24" />
                                <rect x="24" y="8" width="5" height="22" rx="2" fill="#FBBF24" />
                                <line x1="38" y1="12" x2="60" y2="12" stroke="#60A5FA" stroke-width="2" stroke-linecap="round" />
                                <line x1="38" y1="19" x2="55" y2="19" stroke="#60A5FA" stroke-width="2" stroke-linecap="round" />
                                <line x1="38" y1="26" x2="65" y2="26" stroke="#60A5FA" stroke-width="2" stroke-linecap="round" />
                                <!-- LEDs -->
                                <circle cx="78" cy="19" r="4" fill="#EF4444" class="led-blink-fast" />
                                <circle cx="94" cy="19" r="7" fill="#FFFFFF" class="led-blink-slow" />
                                <!-- Power Socket -->
                                <rect x="113" y="14" width="7" height="10" rx="2" fill="#0F172A" />
                            </g>

                            <!-- Server 2 (Middle Rack) -->
                            <g transform="translate(125, 108) rotate(-12)">
                                <rect x="0" y="0" width="120" height="40" rx="8" fill="#2563EB" stroke="#0F172A" stroke-width="3.5" />
                                <rect x="10" y="9" width="5" height="22" rx="2" fill="#FBBF24" />
                                <rect x="18" y="9" width="5" height="22" rx="2" fill="#FBBF24" />
                                <rect x="26" y="9" width="5" height="22" rx="2" fill="#FBBF24" />
                                <circle cx="85" cy="20" r="4.5" fill="#EF4444" class="led-blink-slow" />
                                <circle cx="102" cy="20" r="7.5" fill="#FFFFFF" class="led-blink-fast" />
                            </g>

                            <!-- Server 1 (Bottom Rack) -->
                            <g transform="translate(138, 152) rotate(-10)">
                                <rect x="0" y="0" width="125" height="42" rx="8" fill="#1E40AF" stroke="#0F172A" stroke-width="3.5" />
                                <line x1="12" y1="14" x2="42" y2="14" stroke="#93C5FD" stroke-width="3" stroke-linecap="round" />
                                <line x1="12" y1="22" x2="36" y2="22" stroke="#93C5FD" stroke-width="3" stroke-linecap="round" />
                                <line x1="12" y1="30" x2="48" y2="30" stroke="#93C5FD" stroke-width="3" stroke-linecap="round" />
                                <circle cx="88" cy="21" r="5" fill="#EF4444" class="led-blink-fast" />
                                <circle cx="106" cy="21" r="8" fill="#FFFFFF" class="led-blink-slow" />
                            </g>
                        </g>

                        <!-- Trailing Power Cable with Physics Wave -->
                        <g class="animate-cable-wave origin-top-right">
                            <path d="M236 82 C275 85 285 130 270 160 C250 200 290 230 260 275 C240 305 270 330 255 375" 
                                  stroke="#0F172A" stroke-width="4.5" fill="none" stroke-linecap="round" />
                            <circle cx="255" cy="375" r="4" fill="#FEF08A" />
                        </g>

                    </svg>
                </div>

                <!-- Floating Bottom Status Pill -->
                <div class="mt-2 bg-white/95 backdrop-blur-md rounded-2xl p-3 flex items-center justify-between shadow-lg border border-white/50 text-slate-800">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-[#FE5E04] text-xl animate-spin" style="animation-duration: 6s;">sync</span>
                        <div>
                            <p class="text-[11px] font-extrabold leading-tight text-slate-900">Live Infrastructure Upgrade</p>
                            <p class="text-[10px] text-slate-500 font-medium">Auto-deploying matchmaking cluster</p>
                        </div>
                    </div>
                    <span class="text-[10px] font-black uppercase tracking-wider px-2 py-0.5 rounded-full bg-orange-100 text-[#FE5E04]">
                        98.4%
                    </span>
                </div>

            </div>
        </div>

        <!-- Right Column: Clean Light Information Card -->
        <div class="w-full max-w-xl space-y-6 text-left">
            
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-orange-50 border border-orange-200 text-[#FE5E04] text-xs font-black tracking-wide shadow-2xs">
                <span class="w-2 h-2 rounded-full bg-[#FE5E04] animate-pulse"></span>
                <span>PLATFORM ENHANCEMENT IN PROGRESS</span>
            </div>

            <div class="space-y-3">
                <h1 class="text-3xl sm:text-4xl md:text-5xl font-black text-slate-900 tracking-tight leading-[1.15]">
                    Upgrading Your <span class="text-[#FE5E04] underline decoration-orange-200 decoration-4 underline-offset-4">Trainer & Campus</span> Experience
                </h1>
                <p class="text-sm sm:text-base text-slate-600 leading-relaxed font-medium">
                    <?= htmlspecialchars($config['message'] ?? 'We are optimizing our trainer matchmaking engine and college requirement pipeline.') ?>
                </p>
            </div>

            <!-- Modern Progress Status Box -->
            <div class="bg-white border border-slate-200/90 rounded-3xl p-6 shadow-card hover:shadow-card-hover transition-all space-y-4">
                <div class="flex items-center justify-between text-xs font-bold">
                    <div class="flex items-center gap-2 text-slate-700">
                        <span class="material-symbols-outlined text-base text-blue-600">hourglass_top</span>
                        <span>Estimated Return Time</span>
                    </div>
                    <span class="text-[#FE5E04] font-black bg-orange-50 border border-orange-200 px-2.5 py-1 rounded-lg">
                        <?= htmlspecialchars($config['estimated_return'] ?? 'Within 1 Hour') ?>
                    </span>
                </div>

                <!-- Animated Striped Progress Bar -->
                <div class="space-y-1.5">
                    <div class="w-full bg-slate-100 rounded-full h-3.5 p-0.5 border border-slate-200/60 overflow-hidden relative">
                        <div class="bg-gradient-to-r from-[#FE5E04] via-amber-400 to-[#FE5E04] h-full rounded-full animate-pulse transition-all duration-1000 shadow-sm" style="width: 82%"></div>
                    </div>
                    <div class="flex justify-between text-[11px] font-semibold text-slate-400">
                        <span>Database migration & cache warm-up</span>
                        <span class="font-bold text-slate-700">Phase 3 of 4</span>
                    </div>
                </div>
            </div>

            <!-- Direct Support & Urgent Inquiries -->
            <div class="bg-slate-50 border border-slate-200/80 rounded-3xl p-6 space-y-3.5">
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500">Immediate Requirements or Urgent Inquiries?</h3>
                    <p class="text-xs text-slate-600 mt-0.5">Our operations desk is actively processing trainer deployments via direct channels.</p>
                </div>

                <div class="flex flex-wrap items-center gap-3 pt-1">
                    <a href="mailto:mentry.training@gmail.com" class="bg-white hover:bg-slate-50 text-slate-800 text-xs font-bold px-4 py-2.5 rounded-xl border border-slate-200 transition-all flex items-center gap-2 shadow-xs hover:border-[#FE5E04] hover:text-[#FE5E04] group">
                        <span class="material-symbols-outlined text-base text-[#FE5E04] group-hover:scale-110 transition-transform">mail</span>
                        mentry.training@gmail.com
                    </a>
                    
                    <a href="https://wa.me/919845012345?text=Hello%20Mentry%20Team!%20I%20have%20an%20urgent%20training%20inquiry." target="_blank" class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold px-4 py-2.5 rounded-xl transition-all flex items-center gap-2 shadow-md shadow-emerald-600/20 hover:scale-[1.02]">
                        <span class="material-symbols-outlined text-base">chat</span>
                        WhatsApp Urgent Desk
                    </a>
                </div>
            </div>

        </div>

    </main>

    <!-- Footer -->
    <footer class="w-full border-t border-slate-200/90 bg-white/90 py-5 text-center text-xs text-slate-500">
        <div class="max-w-6xl mx-auto px-6 flex flex-col sm:flex-row items-center justify-between gap-3">
            <p>&copy; <?= date('Y') ?> <strong>Mentry Solutions</strong>. India's Premier Managed Trainer Network. All rights reserved.</p>
            <p class="text-slate-400 text-[11px]">Bangalore • Chennai • Hyderabad • Pune • NCR</p>
        </div>
    </footer>

</body>
</html>

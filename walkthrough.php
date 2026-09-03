<?php
// walkthrough.php - Client Demo & Video Walkthrough Player
$pageTitle = "Video Walkthrough & Platform Demo";
require_once __DIR__ . '/includes/header.php';
?>

<div class="bg-slate-900 text-white py-14 md:py-18 relative overflow-hidden">
    <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,_var(--tw-gradient-stops))] from-blue-600/20 via-transparent to-transparent"></div>
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 text-center space-y-4">
        <span class="inline-flex items-center gap-1.5 bg-blue-500/10 text-blue-400 text-xs font-bold uppercase tracking-wider px-3.5 py-1 rounded-full border border-blue-500/20">
            <span class="material-symbols-outlined text-[15px]">smart_display</span>
            Official Video Presentation
        </span>
        <h1 class="text-3xl md:text-5xl font-black tracking-tight">Mentry Platform Video Walkthrough</h1>
        <p class="text-sm md:text-base text-slate-400 max-w-2xl mx-auto">
            High-definition, end-to-end recording covering the Public Portals, Trainer Workspace, and Admin Command Center.
        </p>

        <!-- Download & Direct File Actions -->
        <div class="pt-3 flex flex-wrap items-center justify-center gap-3">
            <a href="/public/mentry_walkthrough_demo.webp?v=<?= time() ?>" download="mentry_platform_walkthrough.webp" class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-sm px-6 py-3 rounded-2xl shadow-lg hover:shadow-blue-500/25 transition-all flex items-center gap-2">
                <span class="material-symbols-outlined text-lg">download</span>
                Download Video File (14.3 MB)
            </a>
            <a href="/public/mentry_walkthrough_demo.webp?v=<?= time() ?>" target="_blank" class="bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 font-bold text-sm px-6 py-3 rounded-2xl transition-all flex items-center gap-2">
                <span class="material-symbols-outlined text-lg">fullscreen</span>
                Open Fullscreen
            </a>
        </div>
    </div>
</div>

<!-- Main Video Player Section -->
<div class="bg-slate-950 py-12 md:py-16">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
        
        <!-- Video Screen Container -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-3 md:p-6 shadow-2xl space-y-4">
            <div class="flex items-center justify-between px-2 text-xs text-slate-400">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span class="font-bold text-slate-200">Live Recording Stream</span>
                </div>
                <span class="bg-slate-800 px-3 py-1 rounded-full text-[11px] font-mono text-slate-300">Format: Animated WebP • 1080p • 14.3 MB</span>
            </div>

            <!-- Video Frame -->
            <div class="rounded-2xl overflow-hidden border border-slate-800/90 bg-black shadow-inner flex items-center justify-center">
                <img src="/public/mentry_walkthrough_demo.webp?v=<?= time() ?>" alt="Mentry Platform Walkthrough Recording" class="w-full h-auto object-contain max-h-[80vh] block">
            </div>
        </div>

        <!-- Video Scene Breakdown Grid -->
        <div class="space-y-4 pt-4">
            <h3 class="text-lg font-bold text-white flex items-center gap-2">
                <span class="material-symbols-outlined text-blue-500">list_alt</span>
                Scenes & Modules Covered in this Recording
            </h3>

            <div class="grid md:grid-cols-3 gap-6 text-slate-300">
                <!-- Scene 1 -->
                <div class="bg-slate-900/80 border border-slate-800 p-6 rounded-2xl space-y-3">
                    <div class="w-10 h-10 rounded-xl bg-blue-500/10 text-blue-400 flex items-center justify-center font-black">
                        1
                    </div>
                    <h4 class="font-bold text-white text-base">Public Portals & Intake</h4>
                    <ul class="text-xs text-slate-400 space-y-1.5 list-disc list-inside">
                        <li>Homepage with Domain Grid</li>
                        <li>Opportunities Feed & Details</li>
                        <li>College Training Intake Form</li>
                        <li>DPDP Act 2023 Privacy Policy</li>
                        <li>Terms of Service & Honorarium SLAs</li>
                    </ul>
                </div>

                <!-- Scene 2 -->
                <div class="bg-slate-900/80 border border-slate-800 p-6 rounded-2xl space-y-3">
                    <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center font-black">
                        2
                    </div>
                    <h4 class="font-bold text-white text-base">Trainer Workspace</h4>
                    <ul class="text-xs text-slate-400 space-y-1.5 list-disc list-inside">
                        <li>Trainer Portal Sign In (Autofill)</li>
                        <li>Faculty Dashboard Metrics</li>
                        <li>Daily Billing Rate & Mobility Settings</li>
                        <li>Technical Skills Stack & Experience</li>
                        <li>Document & Resume Management</li>
                        <li>Confirmed Assignments & Logistics</li>
                    </ul>
                </div>

                <!-- Scene 3 -->
                <div class="bg-slate-900/80 border border-slate-800 p-6 rounded-2xl space-y-3">
                    <div class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-400 flex items-center justify-center font-black">
                        3
                    </div>
                    <h4 class="font-bold text-white text-base">Admin Command Center</h4>
                    <ul class="text-xs text-slate-400 space-y-1.5 list-disc list-inside">
                        <li>Executive Dashboard & KPI Tiles</li>
                        <li>1-Click College Requirement Converter</li>
                        <li>Opportunity Create, Edit & Direct Assign</li>
                        <li>Trainers Directory & Approval Flow</li>
                        <li>Printable ATS CV Generator (Print to PDF)</li>
                        <li>Campus Logistics (Guest House & Travel)</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

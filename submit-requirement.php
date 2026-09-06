<?php
// submit-requirement.php - What We Do For Colleges & Higher Education Institutions
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';

$pageTitle = "For Colleges - Institutional Corporate Training & Placement Solutions";
require_once __DIR__ . '/includes/header.php';
?>

<div class="bg-slate-50/60 min-h-screen py-10 sm:py-16 w-full max-w-full overflow-x-hidden">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-12 sm:space-y-16 min-w-0">
        
        <!-- Hero Section -->
        <div class="text-center space-y-4 max-w-3xl mx-auto">
            <span class="inline-flex items-center gap-1.5 text-[#FE5E04] font-extrabold text-xs uppercase tracking-wider bg-orange-50 px-4 py-1.5 rounded-full border border-orange-200/80 shadow-xs">
                <span class="material-symbols-outlined text-[16px]">school</span>
                Institutional Academic Solutions
            </span>
            <h1 class="text-3xl sm:text-5xl font-black text-slate-950 tracking-tight leading-tight">
                Empowering Colleges with India's Premier Corporate Trainer Network
            </h1>
            <p class="text-sm sm:text-base text-slate-600 leading-relaxed">
                Mentry Solutions bridges academia and the fast-moving tech industry. We partner directly with Engineering Colleges, Autonomous Universities, and Technical Institutions to deliver hands-on, placement-driven training by practicing software leaders.
            </p>
            <div class="pt-2 flex flex-wrap items-center justify-center gap-3">
                <a href="/vendor-register.php?type=COLLEGE" class="bg-[#FE5E04] hover:bg-[#E04E00] text-white font-extrabold text-xs sm:text-sm px-6 py-3.5 rounded-xl shadow-md shadow-orange-500/20 hover:shadow-orange-500/35 transition-all flex items-center gap-2">
                    <span>Register Your College</span>
                    <span class="material-symbols-outlined text-base">arrow_forward</span>
                </a>
                <a href="/vendor-login.php" class="bg-white border border-slate-200 text-slate-700 hover:text-slate-900 hover:border-slate-300 font-bold text-xs sm:text-sm px-5 py-3.5 rounded-xl shadow-xs transition-all flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-base">login</span>
                    <span>College Portal Login</span>
                </a>
            </div>
        </div>

        <!-- Impact Metrics Bar -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 sm:gap-6">
            <div class="bg-white p-5 sm:p-6 rounded-2xl border border-slate-200/90 shadow-card text-center min-w-0">
                <div class="text-3xl sm:text-4xl font-black text-slate-900 tracking-tight">500+</div>
                <div class="text-[11px] sm:text-xs font-bold uppercase text-slate-400 mt-1">Verified Corporate Trainers</div>
            </div>
            <div class="bg-white p-5 sm:p-6 rounded-2xl border border-slate-200/90 shadow-card text-center min-w-0">
                <div class="text-3xl sm:text-4xl font-black text-[#FE5E04] tracking-tight">40+</div>
                <div class="text-[11px] sm:text-xs font-bold uppercase text-slate-400 mt-1">Technical Stacks & Domains</div>
            </div>
            <div class="bg-white p-5 sm:p-6 rounded-2xl border border-slate-200/90 shadow-card text-center min-w-0">
                <div class="text-3xl sm:text-4xl font-black text-emerald-600 tracking-tight">92%+</div>
                <div class="text-[11px] sm:text-xs font-bold uppercase text-slate-400 mt-1">Placement Conversion Rate</div>
            </div>
            <div class="bg-white p-5 sm:p-6 rounded-2xl border border-slate-200/90 shadow-card text-center min-w-0">
                <div class="text-3xl sm:text-4xl font-black text-indigo-600 tracking-tight">100%</div>
                <div class="text-[11px] sm:text-xs font-bold uppercase text-slate-400 mt-1">Daily Progress Analytics</div>
            </div>
        </div>

        <!-- What We Do For Colleges Section -->
        <div class="space-y-8">
            <div class="text-center space-y-2 max-w-2xl mx-auto">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Comprehensive Solutions</span>
                <h2 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">What Mentry Solutions Does For Colleges</h2>
                <p class="text-xs sm:text-sm text-slate-500">We take complete ownership of student skilling, trainer matching, lab curriculum, and post-training outcomes.</p>
            </div>

            <div class="grid md:grid-cols-3 gap-6">
                <!-- 1. Full Stack & Tech Bootcamps -->
                <div class="bg-white rounded-2xl sm:rounded-3xl p-6 sm:p-7 border border-slate-200/90 shadow-card hover:shadow-card-hover transition-all flex flex-col justify-between">
                    <div class="space-y-3.5">
                        <div class="w-12 h-12 rounded-2xl bg-orange-50 text-[#FE5E04] flex items-center justify-center border border-orange-100">
                            <span class="material-symbols-outlined text-2xl">code_blocks</span>
                        </div>
                        <h3 class="text-lg font-black text-slate-900">Industry-Aligned Tech Bootcamps</h3>
                        <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                            Practical, hands-on training across modern stacks: Full Stack Java, Python, MERN, Cloud Computing (AWS/Azure), Generative AI/ML, DevOps, and Cyber Security.
                        </p>
                    </div>
                    <ul class="mt-4 pt-4 border-t border-slate-100 text-xs text-slate-500 space-y-1.5 font-medium">
                        <li class="flex items-center gap-2"><span class="material-symbols-outlined text-emerald-600 text-sm">check_circle</span> Live coding & sandbox environments</li>
                        <li class="flex items-center gap-2"><span class="material-symbols-outlined text-emerald-600 text-sm">check_circle</span> Industry updated curriculum</li>
                    </ul>
                </div>

                <!-- 2. Campus Placement Readiness (CRT) -->
                <div class="bg-white rounded-2xl sm:rounded-3xl p-6 sm:p-7 border border-slate-200/90 shadow-card hover:shadow-card-hover transition-all flex flex-col justify-between">
                    <div class="space-y-3.5">
                        <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center border border-indigo-100">
                            <span class="material-symbols-outlined text-2xl">workspace_premium</span>
                        </div>
                        <h3 class="text-lg font-black text-slate-900">Campus Placement Acceleration</h3>
                        <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                            Rigorous training designed specifically for campus recruitment drives. Covers Data Structures & Algorithms, Aptitude, Technical Interviews, and Soft Skills.
                        </p>
                    </div>
                    <ul class="mt-4 pt-4 border-t border-slate-100 text-xs text-slate-500 space-y-1.5 font-medium">
                        <li class="flex items-center gap-2"><span class="material-symbols-outlined text-emerald-600 text-sm">check_circle</span> Mock Technical & HR interviews</li>
                        <li class="flex items-center gap-2"><span class="material-symbols-outlined text-emerald-600 text-sm">check_circle</span> LeetCode & HackerRank coding drills</li>
                    </ul>
                </div>

                <!-- 3. Hands-on Capstone Projects -->
                <div class="bg-white rounded-2xl sm:rounded-3xl p-6 sm:p-7 border border-slate-200/90 shadow-card hover:shadow-card-hover transition-all flex flex-col justify-between">
                    <div class="space-y-3.5">
                        <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center border border-emerald-100">
                            <span class="material-symbols-outlined text-2xl">rocket_launch</span>
                        </div>
                        <h3 class="text-lg font-black text-slate-900">Live Capstone Projects</h3>
                        <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                            Students build and deploy real-world enterprise applications following industry Git workflows, CI/CD pipelines, and cloud hosting—not just textbook theory.
                        </p>
                    </div>
                    <ul class="mt-4 pt-4 border-t border-slate-100 text-xs text-slate-500 space-y-1.5 font-medium">
                        <li class="flex items-center gap-2"><span class="material-symbols-outlined text-emerald-600 text-sm">check_circle</span> Verifiable GitHub portfolio projects</li>
                        <li class="flex items-center gap-2"><span class="material-symbols-outlined text-emerald-600 text-sm">check_circle</span> Agile sprint-based delivery</li>
                    </ul>
                </div>

                <!-- 4. Vetted Senior Practitioners -->
                <div class="bg-white rounded-2xl sm:rounded-3xl p-6 sm:p-7 border border-slate-200/90 shadow-card hover:shadow-card-hover transition-all flex flex-col justify-between">
                    <div class="space-y-3.5">
                        <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center border border-amber-100">
                            <span class="material-symbols-outlined text-2xl">verified_user</span>
                        </div>
                        <h3 class="text-lg font-black text-slate-900">100% Vetted Corporate Faculty</h3>
                        <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                            Every trainer undergoes multi-round technical screening, background checks, and pedagogy assessments. Only the top 5% of industry instructors are deployed.
                        </p>
                    </div>
                    <ul class="mt-4 pt-4 border-t border-slate-100 text-xs text-slate-500 space-y-1.5 font-medium">
                        <li class="flex items-center gap-2"><span class="material-symbols-outlined text-emerald-600 text-sm">check_circle</span> 5+ to 15+ years software experience</li>
                        <li class="flex items-center gap-2"><span class="material-symbols-outlined text-emerald-600 text-sm">check_circle</span> Native regional & English fluency</li>
                    </ul>
                </div>

                <!-- 5. End-to-End Operational Governance -->
                <div class="bg-white rounded-2xl sm:rounded-3xl p-6 sm:p-7 border border-slate-200/90 shadow-card hover:shadow-card-hover transition-all flex flex-col justify-between">
                    <div class="space-y-3.5">
                        <div class="w-12 h-12 rounded-2xl bg-cyan-50 text-cyan-600 flex items-center justify-center border border-cyan-100">
                            <span class="material-symbols-outlined text-2xl">monitoring</span>
                        </div>
                        <h3 class="text-lg font-black text-slate-900">Dedicated Program Management</h3>
                        <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                            A designated Mentry operations manager handles instructor coordination, student attendance logs, daily syllabus milestones, and continuous institutional feedback.
                        </p>
                    </div>
                    <ul class="mt-4 pt-4 border-t border-slate-100 text-xs text-slate-500 space-y-1.5 font-medium">
                        <li class="flex items-center gap-2"><span class="material-symbols-outlined text-emerald-600 text-sm">check_circle</span> Zero management hassle for college HODs</li>
                        <li class="flex items-center gap-2"><span class="material-symbols-outlined text-emerald-600 text-sm">check_circle</span> On-demand replacement guarantee</li>
                    </ul>
                </div>

                <!-- 6. Institutional Analytics & Certification -->
                <div class="bg-white rounded-2xl sm:rounded-3xl p-6 sm:p-7 border border-slate-200/90 shadow-card hover:shadow-card-hover transition-all flex flex-col justify-between">
                    <div class="space-y-3.5">
                        <div class="w-12 h-12 rounded-2xl bg-purple-50 text-purple-600 flex items-center justify-center border border-purple-100">
                            <span class="material-symbols-outlined text-2xl">analytics</span>
                        </div>
                        <h3 class="text-lg font-black text-slate-900">Student Analytics & Certificates</h3>
                        <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                            At course completion, colleges receive detailed student performance scorecards, batch skill matrices, and co-branded verifiable digital certificates.
                        </p>
                    </div>
                    <ul class="mt-4 pt-4 border-t border-slate-100 text-xs text-slate-500 space-y-1.5 font-medium">
                        <li class="flex items-center gap-2"><span class="material-symbols-outlined text-emerald-600 text-sm">check_circle</span> Verifiable digital certificates with QR</li>
                        <li class="flex items-center gap-2"><span class="material-symbols-outlined text-emerald-600 text-sm">check_circle</span> Dean / Principal executive summary</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Flexible Engagement Models -->
        <div class="bg-white rounded-3xl p-8 sm:p-12 border border-slate-200/90 shadow-card space-y-6">
            <div class="text-center max-w-xl mx-auto space-y-2">
                <span class="text-xs font-bold uppercase text-[#FE5E04]">Flexible Formats</span>
                <h2 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Training Delivery Modes</h2>
                <p class="text-xs sm:text-sm text-slate-500">Customized according to your academic calendar and campus lab infrastructure.</p>
            </div>

            <div class="grid sm:grid-cols-3 gap-6 pt-4">
                <div class="bg-slate-50 rounded-2xl p-6 border border-slate-100 text-center space-y-3">
                    <div class="w-12 h-12 rounded-xl bg-orange-100 text-[#FE5E04] flex items-center justify-center mx-auto">
                        <span class="material-symbols-outlined text-2xl">location_city</span>
                    </div>
                    <h4 class="font-black text-slate-900 text-base">On-Campus Immersion</h4>
                    <p class="text-xs text-slate-600 leading-relaxed">
                        Full-time 5 to 30 days intensive physical bootcamps conducted right inside your campus computer labs with daily face-to-face mentorship.
                    </p>
                </div>

                <div class="bg-slate-50 rounded-2xl p-6 border border-slate-100 text-center space-y-3">
                    <div class="w-12 h-12 rounded-xl bg-indigo-100 text-indigo-600 flex items-center justify-center mx-auto">
                        <span class="material-symbols-outlined text-2xl">laptop_chromebook</span>
                    </div>
                    <h4 class="font-black text-slate-900 text-base">Virtual Interactive (VILT)</h4>
                    <p class="text-xs text-slate-600 leading-relaxed">
                        High-engagement live online classrooms with cloud coding sandboxes, recorded lecture backups, and 24/7 student doubt clearing.
                    </p>
                </div>

                <div class="bg-slate-50 rounded-2xl p-6 border border-slate-100 text-center space-y-3">
                    <div class="w-12 h-12 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center mx-auto">
                        <span class="material-symbols-outlined text-2xl">calendar_month</span>
                    </div>
                    <h4 class="font-black text-slate-900 text-base">Weekend / Semester Elective</h4>
                    <p class="text-xs text-slate-600 leading-relaxed">
                        Structured 60-to-120 hour credit-based programs integrated into your autonomous academic curriculum without disrupting regular classes.
                    </p>
                </div>
            </div>
        </div>

        <!-- 4 Steps Partnership Workflow -->
        <div class="space-y-6">
            <div class="text-center space-y-2 max-w-xl mx-auto">
                <span class="text-xs font-bold uppercase text-slate-500">How It Works</span>
                <h2 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">4 Easy Steps to Partner With Mentry</h2>
            </div>

            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
                <div class="bg-white p-6 rounded-2xl border border-slate-200/90 shadow-card text-center space-y-3">
                    <div class="w-10 h-10 rounded-full bg-slate-900 text-white font-black text-sm flex items-center justify-center mx-auto">1</div>
                    <h4 class="font-bold text-slate-900 text-sm">Register College</h4>
                    <p class="text-xs text-slate-500">Create your institutional partner account in under 60 seconds.</p>
                </div>

                <div class="bg-white p-6 rounded-2xl border border-slate-200/90 shadow-card text-center space-y-3">
                    <div class="w-10 h-10 rounded-full bg-slate-900 text-white font-black text-sm flex items-center justify-center mx-auto">2</div>
                    <h4 class="font-bold text-slate-900 text-sm">Define Requirements</h4>
                    <p class="text-xs text-slate-500">Specify tech stack, student batch strength, duration, and target dates.</p>
                </div>

                <div class="bg-white p-6 rounded-2xl border border-slate-200/90 shadow-card text-center space-y-3">
                    <div class="w-10 h-10 rounded-full bg-slate-900 text-white font-black text-sm flex items-center justify-center mx-auto">3</div>
                    <h4 class="font-bold text-slate-900 text-sm">Trainer Matching</h4>
                    <p class="text-xs text-slate-500">Review shortlisted senior corporate trainers and finalized curriculum syllabus.</p>
                </div>

                <div class="bg-white p-6 rounded-2xl border border-slate-200/90 shadow-card text-center space-y-3">
                    <div class="w-10 h-10 rounded-full bg-[#FE5E04] text-white font-black text-sm flex items-center justify-center mx-auto">4</div>
                    <h4 class="font-bold text-slate-900 text-sm">Campus Execution</h4>
                    <p class="text-xs text-slate-500">Seamless delivery, live attendance reporting, and placement-ready students.</p>
                </div>
            </div>
        </div>

        <!-- Call To Action Section at the Bottom (DOWN A REGISTER BUTTON) -->
        <div class="bg-gradient-to-br from-slate-900 via-slate-950 to-slate-900 rounded-3xl p-8 sm:p-14 text-white text-center shadow-2xl relative overflow-hidden space-y-6">
            <div class="absolute -top-24 -right-24 w-72 h-72 bg-[#FE5E04]/20 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute -bottom-24 -left-24 w-72 h-72 bg-blue-600/20 rounded-full blur-3xl pointer-events-none"></div>

            <div class="relative z-10 max-w-2xl mx-auto space-y-4">
                <span class="inline-flex items-center gap-1.5 text-orange-400 font-extrabold text-xs uppercase tracking-wider bg-white/10 px-4 py-1.5 rounded-full border border-white/15">
                    <span class="material-symbols-outlined text-[15px]">verified</span>
                    Join 60+ Partner Institutions Across India
                </span>
                <h2 class="text-3xl sm:text-4xl font-black tracking-tight leading-tight">
                    Ready to Transform Your Campus Placements?
                </h2>
                <p class="text-xs sm:text-sm text-slate-300 leading-relaxed">
                    Register your institution with Mentry Solutions today. Submit private training demands, review vetted senior corporate trainers, and track batch performance directly from your dedicated College Partner Portal.
                </p>
                
                <div class="pt-4 flex flex-col sm:flex-row items-center justify-center gap-4">
                    <a href="/vendor-register.php?type=COLLEGE" class="w-full sm:w-auto bg-[#FE5E04] hover:bg-[#e04e00] text-white font-extrabold text-sm sm:text-base px-8 sm:px-12 py-4 rounded-2xl shadow-xl shadow-orange-500/30 transition-all transform hover:-translate-y-1 flex items-center justify-center gap-2.5">
                        <span>Register Your College / Institution</span>
                        <span class="material-symbols-outlined text-xl">arrow_forward</span>
                    </a>
                </div>

                <div class="pt-2 text-xs text-slate-400">
                    Already registered? 
                    <a href="/vendor-login.php" class="text-white hover:text-[#FE5E04] font-bold underline transition-colors ml-1">
                        Sign In to College Partner Portal
                    </a>
                </div>
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

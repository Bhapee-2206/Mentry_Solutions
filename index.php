<?php
// index.php - Public Homepage
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

$pageTitle = "India's Premier Managed Trainer Network";

// Query MongoDB directly
$totalOpportunities = 35;
$totalTrainers = 19;
$featuredOpportunities = [];
$categories = [];
$testimonials = [];

try {
    $opportunityCol = getCollection("Opportunity");
    $trainerCol = getCollection("Trainer");
    $categoryCol = getCollection("TrainingCategory");
    $testimonialCol = getCollection("Testimonial");

    if ($opportunityCol) {
        $totalOpportunities = $opportunityCol->countDocuments(['status' => 'PUBLISHED']);
        $featuredOpportunities = $opportunityCol->find(
            ['status' => 'PUBLISHED'],
            ['limit' => 3, 'sort' => ['createdAt' => -1]]
        )->toArray();
    }

    if ($trainerCol) {
        $totalTrainers = $trainerCol->countDocuments(['status' => 'APPROVED']);
    }

    if ($categoryCol) {
        $categories = $categoryCol->find(
            ['active' => true],
            ['sort' => ['order' => 1]]
        )->toArray();
    }

    if ($testimonialCol) {
        $testimonials = $testimonialCol->find(
            ['featured' => true],
            ['limit' => 3]
        )->toArray();
    }
} catch (Throwable $e) {
    error_log("MongoDB Connection/Query Error: " . $e->getMessage());
}

if (empty($categories)) {
    $categories = [
        ['name' => 'Programming', 'icon' => 'terminal'],
        ['name' => 'Data Science', 'icon' => 'monitoring'],
        ['name' => 'Cloud', 'icon' => 'cloud'],
        ['name' => 'VLSI', 'icon' => 'memory'],
        ['name' => 'Cybersecurity', 'icon' => 'security'],
        ['name' => 'Aptitude', 'icon' => 'calculate'],
        ['name' => 'Soft Skills', 'icon' => 'record_voice_over'],
        ['name' => 'Management', 'icon' => 'groups']
    ];
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="space-y-20 md:space-y-28 pb-20 bg-white text-slate-900 overflow-hidden">
    <!-- 1. Hero Section with Modern Mesh Gradient -->
    <section class="relative pt-12 pb-20 md:pt-20 md:pb-28 border-b border-slate-100 mesh-bg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid lg:grid-cols-12 gap-12 lg:gap-8 items-center">
                <!-- Left 7 cols: Content -->
                <div class="lg:col-span-7 space-y-6 text-left">
                    <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-white border border-slate-200 shadow-xs hover:border-orange-300 hover:shadow-sm transition-all group select-none">
                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-orange-50 text-[#FE5E04] border border-orange-200/60 shrink-0">
                            <span class="material-symbols-outlined text-[14px]">verified</span>
                        </span>
                        <span class="text-xs font-bold text-slate-900 tracking-tight">India's Premier</span>
                        <span class="text-slate-300 font-light">|</span>
                        <span class="text-xs font-medium text-slate-600">Managed Trainer Network</span>
                        <span class="material-symbols-outlined text-[15px] text-[#FE5E04] transition-transform group-hover:translate-x-0.5">chevron_right</span>
                    </div>

                    <h1 class="text-4xl sm:text-5xl lg:text-6xl font-black text-slate-950 tracking-tight leading-[1.12]">
                        Your Expertise. <br />
                        <span class="bg-clip-text text-transparent bg-gradient-to-r from-[#FE5E04] via-[#F97316] to-[#C23E00]">
                            Your Next College Training Opportunity.
                        </span>
                    </h1>

                    <p class="text-base sm:text-lg text-slate-600 leading-relaxed max-w-2xl font-normal">
                        Connect with top engineering colleges and universities across India for high-impact freelance training. Guaranteed daily rates, travel logistics managed, and zero bidding fees.
                    </p>

                    <!-- CTAs -->
                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3.5 pt-2">
                        <a href="/register.php" class="bg-[#FE5E04] hover:bg-[#E04E00] text-white font-bold text-sm px-7 py-3.5 rounded-xl shadow-lg shadow-orange-500/25 hover:shadow-orange-500/40 transition-all flex items-center justify-center gap-2 hover:-translate-y-0.5">
                            Join as a Trainer
                            <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                        </a>

                        <a href="/opportunities.php" class="bg-white hover:bg-orange-50/50 text-slate-800 border border-slate-300 hover:border-orange-300 font-bold text-sm px-6 py-3.5 rounded-xl transition-all shadow-xs flex items-center justify-center gap-2">
                            <span class="material-symbols-outlined text-[#FE5E04] text-[18px]">search</span>
                            Browse Opportunities
                        </a>

                        <a href="/submit-requirement.php" class="text-xs font-semibold text-slate-500 hover:text-[#FE5E04] text-center py-2 sm:pl-2">
                            Are you a College? Submit Requirement →
                        </a>
                    </div>

                    <!-- 3 Metric Stat Counter Pill -->
                    <div class="grid grid-cols-3 gap-6 pt-6 border-t border-slate-200/80">
                        <div>
                            <span class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight block">
                                <?= $totalOpportunities > 0 ? ($totalOpportunities + 25) : "45+" ?>
                            </span>
                            <span class="text-xs font-semibold text-slate-500">Active College Openings</span>
                        </div>
                        <div>
                            <span class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight block">
                                <?= $totalTrainers > 0 ? ($totalTrainers * 25) : "500+" ?>
                            </span>
                            <span class="text-xs font-semibold text-slate-500">Vetted Industry Trainers</span>
                        </div>
                        <div>
                            <span class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight block">
                                ₹4K – ₹12K
                            </span>
                            <span class="text-xs font-semibold text-slate-500">Daily Remuneration Range</span>
                        </div>
                    </div>
                </div>

                <!-- Right 5 cols: Interactive Floating Card Showcase -->
                <div class="lg:col-span-5 relative">
                    <div class="relative mx-auto max-w-md bg-white rounded-3xl p-6 shadow-2xl border border-slate-200/90 space-y-5">
                        <!-- Header of Card -->
                        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-lg bg-[#FE5E04] text-white flex items-center justify-center font-bold text-sm shadow-md shadow-orange-500/20">
                                    M
                                </div>
                                <div>
                                    <h4 class="font-bold text-xs text-slate-900">Featured Campus Match</h4>
                                    <p class="text-[10px] text-slate-400">Bangalore Engineering College</p>
                                </div>
                            </div>
                            <span class="text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 px-2.5 py-0.5 rounded-full flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-ping"></span>
                                96% Match
                            </span>
                        </div>

                        <!-- Assignment Preview -->
                        <div class="space-y-2">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-[#FE5E04] bg-orange-50 px-2.5 py-0.5 rounded-full border border-orange-200/60">
                                Python Full Stack Bootcamp
                            </span>
                            <h3 class="font-bold text-base text-slate-900 leading-snug">
                                10-Day Pre-Placement Technical Training for 120 Engineers
                            </h3>
                            <p class="text-xs text-slate-500">
                                Electronic City Campus, Bangalore • On-Campus Offline
                            </p>
                        </div>

                        <!-- Remuneration Strip -->
                        <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-200/80 flex items-center justify-between">
                            <div>
                                <span class="text-[10px] font-bold text-slate-400 uppercase">Remuneration</span>
                                <p class="text-base font-extrabold text-[#FE5E04]">₹7,500 / Day</p>
                            </div>
                            <div class="text-right">
                                <span class="text-[10px] font-bold text-slate-400 uppercase">Duration</span>
                                <p class="text-xs font-bold text-slate-800">10 Days (₹75,000)</p>
                            </div>
                        </div>

                        <!-- Logistics Badges -->
                        <div class="flex items-center gap-2 text-[11px] text-slate-600 font-medium">
                            <span class="bg-slate-100 px-2.5 py-1 rounded-md">✓ Travel Included</span>
                            <span class="bg-slate-100 px-2.5 py-1 rounded-md">✓ Guest House Stay</span>
                            <span class="bg-slate-100 px-2.5 py-1 rounded-md">✓ Campus Dining</span>
                        </div>

                        <a href="/register.php" class="w-full bg-slate-900 hover:bg-[#FE5E04] text-white font-bold text-xs py-3 rounded-xl text-center block transition-colors shadow-md">
                            Claim This Opportunity →
                        </a>
                    </div>

                    <!-- Floating Trust Badge -->
                    
                </div>
            </div>
        </div>
    </section>

    <!-- 2. Value Proposition Bento -->
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-2xl mx-auto mb-12 space-y-2">
            <span class="text-blue-600 font-bold text-xs uppercase tracking-wider bg-blue-50 px-3 py-1 rounded-full border border-blue-100">
                Managed Partnership
            </span>
            <h2 class="text-3xl md:text-4xl font-extrabold text-slate-950 tracking-tight">
                Designed for India's Academic Training Ecosystem
            </h2>
            <p class="text-slate-600 text-sm md:text-base">
                Mentry is not a freelance gig marketplace. We are a managed professional network ensuring quality training delivery and guaranteed trainer honorariums.
            </p>
        </div>

        <div class="grid md:grid-cols-2 gap-8">
            <!-- For Trainers Card -->
            <div class="bg-white rounded-3xl p-8 md:p-10 border border-slate-200/90 shadow-card hover:shadow-card-hover transition-all duration-300 relative overflow-hidden group">
                <div class="w-14 h-14 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
                    <span class="material-symbols-outlined text-3xl">school</span>
                </div>
                <h3 class="text-2xl font-extrabold text-slate-900 mb-3">
                    For Freelance Trainers
                </h3>
                <p class="text-slate-600 text-sm leading-relaxed mb-6">
                    Access curated offline and online training assignments from engineering colleges and universities across India. Manage applications and assignments in one dedicated portal.
                </p>
                <ul class="space-y-3 mb-8 text-sm text-slate-700">
                    <li class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-emerald-600 text-lg">check_circle</span>
                        Guaranteed daily rates (₹3,500 – ₹12,000+/day) with no bidding
                    </li>
                    <li class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-emerald-600 text-lg">check_circle</span>
                        Smart matching alerts whenever an assignment matches your domain
                    </li>
                    <li class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-emerald-600 text-lg">check_circle</span>
                        Campus travel, accommodation, and food logistics coordinated
                    </li>
                </ul>
                <a href="/register.php" class="inline-flex items-center gap-2 bg-[#FE5E04] hover:bg-[#E04E00] text-white font-bold text-xs px-6 py-3 rounded-xl shadow-md shadow-orange-500/20 transition-all">
                    Join Trainer Network <span class="material-symbols-outlined text-sm">arrow_forward</span>
                </a>
            </div>

            <!-- For Colleges Card -->
            <div class="bg-white rounded-3xl p-8 md:p-10 border border-slate-200/90 shadow-card hover:shadow-card-hover transition-all duration-300 relative overflow-hidden group">
                <div class="w-14 h-14 rounded-2xl bg-orange-50 text-[#FE5E04] flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
                    <span class="material-symbols-outlined text-3xl">apartment</span>
                </div>
                <h3 class="text-2xl font-extrabold text-slate-900 mb-3">
                    For Colleges & Institutions
                </h3>
                <p class="text-slate-600 text-sm leading-relaxed mb-6">
                    Source verified industry practitioners for placement bootcamps, technical workshops, and faculty development programs. Quick matching with 100% syllabus alignment.
                </p>
                <ul class="space-y-3 mb-8 text-sm text-slate-700">
                    <li class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-emerald-600 text-lg">check_circle</span>
                        Pre-vetted trainers with proven college student satisfaction scores
                    </li>
                    <li class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-emerald-600 text-lg">check_circle</span>
                        Rapid 24-hour trainer matching and replacement guarantee
                    </li>
                    <li class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-emerald-600 text-lg">check_circle</span>
                        Complete curriculum coverage from Python/Java to VLSI and Aptitude
                    </li>
                </ul>
                <a href="/submit-requirement.php" class="inline-flex items-center gap-2 bg-slate-900 hover:bg-[#FE5E04] text-white font-bold text-xs px-6 py-3 rounded-xl shadow-md transition-colors">
                    Submit Training Requirement <span class="material-symbols-outlined text-sm">arrow_forward</span>
                </a>
            </div>
        </div>
    </section>

    <!-- 3. Training Categories Grid -->
    <section class="bg-slate-50/70 py-16 border-y border-slate-200/70">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row md:items-end justify-between mb-10 gap-4">
                <div>
                    <span class="text-[#FE5E04] font-bold text-xs uppercase tracking-wider">Curriculum Domains</span>
                    <h2 class="text-2xl md:text-3xl font-black text-slate-950 mt-1">
                        Training Categories
                    </h2>
                    <p class="text-slate-600 text-sm mt-1">
                        Specialized training programs delivered by certified subject matter experts.
                    </p>
                </div>
                <a href="/opportunities.php" class="text-[#FE5E04] hover:underline font-bold text-sm inline-flex items-center gap-1 shrink-0">
                    View all assignments <span class="material-symbols-outlined text-base">arrow_forward</span>
                </a>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
                <?php foreach ($categories as $cat): ?>
                    <a href="/opportunities.php?domain=<?= urlencode($cat['name']) ?>" class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs hover:shadow-card-hover hover:border-[#FE5E04] transition-all group flex flex-col justify-between h-36">
                        <div class="w-10 h-10 rounded-xl bg-slate-50 flex items-center justify-center text-slate-700 group-hover:bg-[#FE5E04] group-hover:text-white transition-colors">
                            <span class="material-symbols-outlined text-xl"><?= htmlspecialchars($cat['icon'] ?? 'code') ?></span>
                        </div>
                        <div>
                            <h4 class="font-bold text-sm text-slate-900 group-hover:text-[#FE5E04] transition-colors leading-tight">
                                <?= htmlspecialchars($cat['name']) ?>
                            </h4>
                            <p class="text-[11px] text-slate-400 mt-0.5">Explore openings</p>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- 4. End-to-End How It Works (6 Steps) -->
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-2xl mx-auto mb-14 space-y-2">
            <span class="text-blue-600 font-bold text-xs uppercase tracking-wider bg-blue-50 px-3 py-1 rounded-full border border-blue-100">
                Streamlined Execution
            </span>
            <h2 class="text-3xl md:text-4xl font-extrabold text-slate-950 tracking-tight">
                How Mentry Operates
            </h2>
            <p class="text-slate-600 text-sm">
                A seamless managed training lifecycle connecting academic requirements with vetted trainers.
            </p>
        </div>

        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php
            $steps = [
                ['step' => '01', 'title' => 'Requirement Intake', 'desc' => 'Colleges submit their syllabus, batch size, target start dates, and location.', 'icon' => 'assignment'],
                ['step' => '02', 'title' => 'Opportunity Published', 'desc' => 'Mentry creates the structured opportunity with guaranteed daily rates in INR.', 'icon' => 'post_add'],
                ['step' => '03', 'title' => 'Automated Matching', 'desc' => 'Our matching engine identifies verified trainers based on skills, location, and experience.', 'icon' => 'hub'],
                ['step' => '04', 'title' => 'Trainers Apply', 'desc' => 'Matched trainers receive instant notifications and apply with one click.', 'icon' => 'touch_app'],
                ['step' => '05', 'title' => 'Selection & Logistics', 'desc' => 'Mentry confirms trainer selection, guest house accommodation, and campus logistics.', 'icon' => 'verified_user'],
                ['step' => '06', 'title' => 'Delivery & Prompt Payout', 'desc' => 'Trainer delivers high-impact on-campus training with prompt honorarium disbursement.', 'icon' => 'military_tech']
            ];
            foreach ($steps as $st): ?>
                <div class="bg-white p-7 rounded-3xl border border-slate-200/90 shadow-card flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-5">
                            <div class="w-11 h-11 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                                <span class="material-symbols-outlined text-2xl"><?= $st['icon'] ?></span>
                            </div>
                            <span class="font-mono font-black text-2xl text-slate-200"><?= $st['step'] ?></span>
                        </div>
                        <h3 class="font-bold text-base text-slate-900 mb-2"><?= $st['title'] ?></h3>
                        <p class="text-xs text-slate-600 leading-relaxed"><?= $st['desc'] ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- 5. Live Featured Opportunities -->
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col md:flex-row md:items-end justify-between mb-8 gap-4">
            <div>
                <span class="text-blue-600 font-bold text-xs uppercase tracking-wider">Live Openings</span>
                <h2 class="text-2xl md:text-3xl font-black text-slate-950 mt-1">
                    Featured Training Opportunities
                </h2>
                <p class="text-slate-600 text-sm mt-1">
                    Explore college assignments across Bangalore, Chennai, Pune, Coimbatore, Hyderabad, and more.
                </p>
            </div>
            <a href="/opportunities.php" class="text-blue-600 hover:text-blue-700 font-bold text-sm inline-flex items-center gap-1 shrink-0">
                View all <?= $totalOpportunities ?> opportunities <span class="material-symbols-outlined text-base">arrow_forward</span>
            </a>
        </div>

        <div class="space-y-4">
            <?php foreach ($featuredOpportunities as $opp): 
                $skills = is_string($opp['skillsRequired']) ? json_decode($opp['skillsRequired'], true) : (array)$opp['skillsRequired'];
                if (!$skills) $skills = explode(',', (string)$opp['skillsRequired']);
                $oppId = (string)$opp['_id'];
            ?>
                <div class="bg-white rounded-2xl border border-slate-200/90 p-6 md:p-7 shadow-card hover:shadow-card-hover hover:border-blue-400 transition-all duration-300 group">
                    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-5">
                        <div class="space-y-3 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex items-center gap-1.5 bg-blue-50 text-blue-700 font-bold text-[11px] px-3 py-1 rounded-full border border-blue-200/60 uppercase">
                                    <span class="w-1.5 h-1.5 rounded-full bg-blue-600"></span>
                                    <?= htmlspecialchars($opp['mode']) ?>
                                </span>
                                <span class="bg-slate-100 text-slate-700 font-semibold text-[11px] px-2.5 py-1 rounded-full uppercase">
                                    <?= htmlspecialchars(str_replace('_', ' ', $opp['trainingType'] ?? 'COLLEGE')) ?>
                                </span>
                                <span class="text-[11px] font-mono font-medium text-slate-400">
                                    ID: <?= htmlspecialchars($opp['jobId'] ?? $oppId) ?>
                                </span>
                                <span class="text-[11px] font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-md">
                                    Active Opening
                                </span>
                            </div>

                            <a href="/opportunity-details.php?id=<?= $oppId ?>" class="block">
                                <h3 class="font-bold text-lg md:text-xl text-slate-900 group-hover:text-blue-600 transition-colors leading-snug">
                                    <?= htmlspecialchars($opp['title']) ?>
                                </h3>
                            </a>

                            <div class="flex flex-wrap items-center gap-y-1 gap-x-4 text-xs text-slate-500 font-medium">
                                <span class="flex items-center gap-1 text-slate-700 font-semibold">
                                    <span class="material-symbols-outlined text-blue-600 text-base">location_on</span>
                                    <?= htmlspecialchars($opp['city']) ?>, <?= htmlspecialchars($opp['state']) ?>
                                </span>
                                <span>•</span>
                                <span class="flex items-center gap-1">
                                    <span class="material-symbols-outlined text-slate-400 text-base">calendar_today</span>
                                    Starts <?= formatDate($opp['startDate']) ?>
                                </span>
                                <span>•</span>
                                <span><?= htmlspecialchars($opp['durationDays']) ?> Working Days</span>
                                <span>•</span>
                                <span><?= htmlspecialchars($opp['minExperienceYears']) ?>+ Yrs Exp</span>
                            </div>

                            <div class="flex flex-wrap gap-1.5 pt-1">
                                <?php foreach (array_slice($skills, 0, 5) as $skill): ?>
                                    <span class="bg-slate-50 text-slate-700 font-medium text-xs px-2.5 py-1 rounded-md border border-slate-200/80">
                                        <?= htmlspecialchars(trim($skill)) ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="shrink-0 flex lg:flex-col items-center lg:items-end justify-between lg:justify-center gap-4 pt-4 lg:pt-0 border-t lg:border-t-0 border-slate-100">
                            <div class="text-left lg:text-right">
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Daily Honorarium</span>
                                <p class="font-extrabold text-xl md:text-2xl text-blue-700">
                                    <?= formatINR($opp['dailyRateMin']) ?> – <?= formatINR($opp['dailyRateMax']) ?>
                                </p>
                                <p class="text-[11px] text-slate-500">Per Day • Guaranteed Payout</p>
                            </div>

                            <div class="flex items-center gap-2">
                                <a href="/opportunity-details.php?id=<?= $oppId ?>" class="px-4 py-2.5 rounded-xl border border-slate-200 hover:border-slate-300 text-slate-700 hover:bg-slate-50 text-xs font-bold transition-all">
                                    View Details
                                </a>
                                <a href="/opportunity-details.php?id=<?= $oppId ?>" class="bg-slate-900 hover:bg-blue-600 text-white text-xs font-bold px-5 py-2.5 rounded-xl transition-all shadow-sm flex items-center gap-1 group-hover:bg-blue-600">
                                    Apply Now
                                    <span class="material-symbols-outlined text-base">arrow_forward</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- 6. Testimonials -->
    <section class="bg-slate-50/80 py-16 border-y border-slate-200/80">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-2xl mx-auto mb-12 space-y-2">
                <span class="text-blue-600 font-bold text-xs uppercase tracking-wider">Verified Reviews</span>
                <h2 class="text-2xl md:text-3xl font-black text-slate-950">
                    Trusted by Trainers & Institutions
                </h2>
            </div>

            <div class="grid md:grid-cols-3 gap-6">
                <?php foreach ($testimonials as $t): ?>
                    <div class="bg-white p-7 rounded-3xl border border-slate-200/90 shadow-card flex flex-col justify-between space-y-6">
                        <div>
                            <div class="flex items-center gap-1 text-amber-500 mb-4">
                                <?php for ($i = 0; $i < ($t['rating'] ?? 5); $i++): ?>
                                    <span class="material-symbols-outlined text-sm icon-fill">star</span>
                                <?php endfor; ?>
                            </div>
                            <p class="text-xs text-slate-600 italic leading-relaxed">
                                "<?= htmlspecialchars($t['quote']) ?>"
                            </p>
                        </div>
                        <div class="flex items-center gap-3 pt-4 border-t border-slate-100">
                            <div class="w-10 h-10 rounded-full bg-blue-100 text-blue-700 font-bold flex items-center justify-center text-sm">
                                <?= htmlspecialchars(substr($t['authorName'] ?? 'T', 0, 1)) ?>
                            </div>
                            <div>
                                <h4 class="font-bold text-sm text-slate-900"><?= htmlspecialchars($t['authorName']) ?></h4>
                                <p class="text-[11px] text-slate-500"><?= htmlspecialchars($t['role']) ?></p>
                                <?php if (!empty($t['institution'])): ?>
                                    <p class="text-[11px] text-blue-600 font-medium"><?= htmlspecialchars($t['institution']) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- 7. Executive Dark CTA Banner -->
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-[#060D17] text-white rounded-3xl p-8 md:p-14 relative overflow-hidden shadow-2xl border border-slate-800">
            <div class="absolute -right-10 -bottom-10 w-96 h-96 bg-blue-600/15 rounded-full blur-3xl pointer-events-none"></div>
            <div class="relative z-10 max-w-2xl space-y-6">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-blue-950 text-blue-400 text-xs font-bold border border-blue-800/60">
                    Transform Your Training Career
                </div>
                <h2 class="text-3xl md:text-4xl font-extrabold text-white leading-tight tracking-tight">
                    Ready to Deliver High-Impact Training Across India?
                </h2>
                <p class="text-slate-300 text-sm md:text-base leading-relaxed">
                    Join Mentry's verified freelance trainer network today. Access guaranteed daily rate college assignments, build your professional brand, and streamline your training calendar.
                </p>
                <div class="flex flex-col sm:flex-row gap-3.5 pt-2">
                    <a href="/register.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-sm px-8 py-3.5 rounded-xl text-center transition-all shadow-lg shadow-blue-600/30">
                        Join Trainer Network
                    </a>
                    <a href="/submit-requirement.php" class="bg-white/10 hover:bg-white/20 text-white border border-white/20 font-bold text-sm px-8 py-3.5 rounded-xl text-center transition-all">
                        Submit College Requirement
                    </a>
                </div>
            </div>
        </div>
    </section>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

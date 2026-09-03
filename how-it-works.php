<?php
// how-it-works.php
$pageTitle = "How Mentry Works";
require_once __DIR__ . '/includes/header.php';
?>

<div class="bg-white py-16 md:py-24">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-16">
        <div class="text-center space-y-3">
            <span class="text-blue-600 font-bold text-xs uppercase tracking-wider bg-blue-50 px-3.5 py-1.5 rounded-full border border-blue-100">
                Transparent Execution
            </span>
            <h1 class="text-3xl sm:text-5xl font-black text-slate-950 tracking-tight">
                How Mentry Solutions Operates
            </h1>
            <p class="text-slate-600 text-sm md:text-base max-w-2xl mx-auto leading-relaxed">
                A managed lifecycle connecting academic placement needs with vetted freelance technical trainers across India.
            </p>
        </div>

        <div class="space-y-8">
            <?php
            $detailedSteps = [
                ['num' => '01', 'title' => 'College Intake & Syllabus Alignment', 'desc' => 'Colleges submit their syllabus, batch size, target start dates, and campus location. Our academic panel reviews prerequisites to ensure complete syllabus feasibility.'],
                ['num' => '02', 'title' => 'Structured Opportunity Publication', 'desc' => 'Mentry structures the opportunity with clear deliverables, duration, guaranteed honorariums in INR (₹4,000–₹12,000/day), and campus logistics.'],
                ['num' => '03', 'title' => 'Intelligent Trainer Matching', 'desc' => 'Our matching algorithm analyzes verified trainer profiles by primary domain, college experience, past student ratings, and travel preferences.'],
                ['num' => '04', 'title' => 'Trainer Application & Acceptance', 'desc' => 'Matched trainers receive priority alerts and confirm their availability with 1 click.'],
                ['num' => '05', 'title' => 'Campus Delivery & Quality Monitoring', 'desc' => 'Trainers deliver hands-on technical workshops with daily attendance, lab code reviews, and student feedback tracking.'],
                ['num' => '06', 'title' => 'Prompt Honorarium Payout', 'desc' => 'Mentry disburses trainer honorariums promptly upon program completion with zero platform commission deductions.']
            ];
            foreach ($detailedSteps as $ds): ?>
                <div class="bg-white p-8 rounded-3xl border border-slate-200/90 shadow-card flex flex-col md:flex-row items-start gap-6">
                    <span class="font-mono font-black text-3xl text-blue-600 bg-blue-50 px-4 py-2 rounded-2xl shrink-0">
                        <?= $ds['num'] ?>
                    </span>
                    <div class="space-y-2">
                        <h3 class="text-xl font-bold text-slate-900"><?= $ds['title'] ?></h3>
                        <p class="text-sm text-slate-600 leading-relaxed"><?= $ds['desc'] ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="text-center pt-8">
            <a href="/register.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-sm px-8 py-3.5 rounded-xl shadow-lg transition-all inline-block">
                Join the Trainer Network Today →
            </a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

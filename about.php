<?php
// about.php
$pageTitle = "About Mentry Solutions";
require_once __DIR__ . '/includes/header.php';
?>

<div class="bg-white py-16 md:py-24">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-12">
        <div class="text-center space-y-3">
            <span class="text-blue-600 font-bold text-xs uppercase tracking-wider bg-blue-50 px-3.5 py-1.5 rounded-full border border-blue-100">
                Company Mission
            </span>
            <h1 class="text-3xl sm:text-5xl font-black text-slate-950 tracking-tight">
                About Mentry Solutions
            </h1>
            <p class="text-slate-600 text-sm md:text-base max-w-2xl mx-auto leading-relaxed">
                Bridging the critical gap between industry technical expectations and academic college curriculums across India.
            </p>
        </div>

        <div class="bg-slate-50 p-8 md:p-10 rounded-3xl border border-slate-200/80 space-y-6 text-sm text-slate-700 leading-relaxed">
            <p>
                <strong>Mentry Solutions</strong> was founded with a singular mission: to democratize access to world-class technical, engineering, and professional soft-skills training for universities and engineering colleges across Tier-1, Tier-2, and Tier-3 Indian cities.
            </p>
            <p>
                Colleges often struggle to find verified, high-quality industry practitioners who can communicate effectively with undergraduate engineers. At the same time, skilled freelance trainers face inconsistent assignments, unpaid bidding platforms, and delayed payments.
            </p>
            <p>
                Mentry serves as the <strong>managed operational backbone</strong>: ensuring 100% syllabus alignment, verified trainer credentials, coordinated campus travel & accommodation logistics, and guaranteed prompt payments.
            </p>
        </div>

        <div class="grid sm:grid-cols-3 gap-6 text-center">
            <div class="p-6 bg-white border border-slate-200 rounded-2xl shadow-card">
                <span class="text-3xl font-black text-blue-600 block">500+</span>
                <span class="text-xs font-semibold text-slate-500 mt-1 block">Vetted Trainers</span>
            </div>
            <div class="p-6 bg-white border border-slate-200 rounded-2xl shadow-card">
                <span class="text-3xl font-black text-blue-600 block">120+</span>
                <span class="text-xs font-semibold text-slate-500 mt-1 block">Colleges Impacted</span>
            </div>
            <div class="p-6 bg-white border border-slate-200 rounded-2xl shadow-card">
                <span class="text-3xl font-black text-blue-600 block">28</span>
                <span class="text-xs font-semibold text-slate-500 mt-1 block">States & Union Territories</span>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

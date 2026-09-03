<?php
// trainer-network.php
$pageTitle = "Why Join Mentry Trainer Network";
require_once __DIR__ . '/includes/header.php';
?>

<div class="bg-white py-16 md:py-24">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-16">
        <div class="text-center space-y-3">
            <span class="text-blue-600 font-bold text-xs uppercase tracking-wider bg-blue-50 px-3.5 py-1.5 rounded-full border border-blue-100">
                For Industry & Freelance Trainers
            </span>
            <h1 class="text-3xl sm:text-5xl font-black text-slate-950 tracking-tight">
                Empowering India's Best Corporate & Academic Trainers
            </h1>
            <p class="text-slate-600 text-sm md:text-base max-w-2xl mx-auto leading-relaxed">
                Unlock high-paying college assignments, eliminate unpaid bidding, and build your reputation across leading institutions.
            </p>
        </div>

        <div class="grid md:grid-cols-3 gap-6">
            <div class="bg-white p-7 rounded-3xl border border-slate-200/90 shadow-card space-y-4">
                <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center">
                    <span class="material-symbols-outlined text-2xl">payments</span>
                </div>
                <h3 class="font-extrabold text-lg text-slate-900">Guaranteed Daily Rates</h3>
                <p class="text-xs text-slate-600 leading-relaxed">
                    Earn between ₹3,500 and ₹12,000+ per day depending on technology and experience. No bidding wars or price slashing.
                </p>
            </div>

            <div class="bg-white p-7 rounded-3xl border border-slate-200/90 shadow-card space-y-4">
                <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center">
                    <span class="material-symbols-outlined text-2xl">travel_explore</span>
                </div>
                <h3 class="font-extrabold text-lg text-slate-900">Coordinated Logistics</h3>
                <p class="text-xs text-slate-600 leading-relaxed">
                    Campus guest house accommodation, meal passes, and flight/train travel bookings managed seamlessly by Mentry.
                </p>
            </div>

            <div class="bg-white p-7 rounded-3xl border border-slate-200/90 shadow-card space-y-4">
                <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center">
                    <span class="material-symbols-outlined text-2xl">verified</span>
                </div>
                <h3 class="font-extrabold text-lg text-slate-900">Verified Professional Profile</h3>
                <p class="text-xs text-slate-600 leading-relaxed">
                    Build a verified dossier with verified student feedback scores, digital certifications, and top college recommendations.
                </p>
            </div>
        </div>

        <div class="bg-slate-900 text-white rounded-3xl p-8 md:p-12 text-center space-y-6">
            <h2 class="text-2xl md:text-3xl font-bold">Join 500+ Verified Trainers Across India</h2>
            <p class="text-slate-400 text-sm max-w-xl mx-auto">Registration takes less than 3 minutes. Our academic panel verifies profiles within 24 hours.</p>
            <a href="/register.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs px-8 py-3.5 rounded-xl inline-block shadow-lg">
                Create Trainer Account Now →
            </a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

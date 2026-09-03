<?php
// includes/footer.php
?>
</main>

<footer class="bg-[#060D17] text-slate-300 border-t border-slate-800/80 pt-16 pb-12 antialiased">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-10 pb-12 border-b border-slate-800/80">
            <!-- Brand Col with mentry.png logo -->
            <div class="lg:col-span-2 space-y-5">
                <a href="/index.php" class="flex items-center gap-3 group">
                    <div class="bg-white p-1 rounded-xl shadow-lg inline-block">
                        <img src="/public/mentry.png" alt="Mentry Solutions Logo" class="h-10 w-auto object-contain rounded-lg">
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-extrabold text-lg text-white tracking-tight">
                                Mentry Solutions
                            </span>
                            <span class="text-[10px] font-black uppercase tracking-wider text-[#FE5E04] bg-[#FE5E04]/10 px-2 py-0.5 rounded-full border border-[#FE5E04]/30">
                                Network
                            </span>
                        </div>
                        <span class="text-xs text-slate-400">
                            Managed Professional Trainer Platform
                        </span>
                    </div>
                </a>

                <p class="text-sm text-slate-400 leading-relaxed max-w-sm">
                    India's premier managed trainer network connecting verified technical, engineering, and soft-skills trainers with colleges, academic institutions, and organizations.
                </p>

                <div class="space-y-2 text-xs">
                    <div class="flex items-center gap-2 text-slate-300">
                        <span class="material-symbols-outlined text-[#FE5E04] text-base">mail</span>
                        <span>Official Business Email:</span>
                        <a href="mailto:mentry.training@gmail.com" class="text-[#FE5E04] hover:underline font-semibold">
                            mentry.training@gmail.com
                        </a>
                    </div>
                    <div class="flex items-center gap-2 text-[#FE5E04] pt-1">
                        <span class="w-2 h-2 rounded-full bg-[#FE5E04] animate-ping"></span>
                        <span class="font-medium text-[11px]">Active Pan-India Offline & Hybrid Network</span>
                    </div>
                </div>
            </div>

            <!-- Network Links -->
            <div class="space-y-4">
                <h4 class="text-xs font-bold uppercase tracking-wider text-white">
                    Trainer Network
                </h4>
                <ul class="space-y-2.5 text-sm">
                    <li><a href="/opportunities.php" class="text-slate-400 hover:text-white transition-colors">Training Opportunities</a></li>
                    <li><a href="/register.php" class="text-slate-400 hover:text-white transition-colors">Join as a Trainer</a></li>
                    <li><a href="/trainer-network.php" class="text-slate-400 hover:text-white transition-colors">Why Join Network</a></li>
                    <li><a href="/how-it-works.php" class="text-slate-400 hover:text-white transition-colors">How Mentry Works</a></li>
                    <li><a href="/login.php" class="text-slate-400 hover:text-white transition-colors">Trainer Portal Login</a></li>
                </ul>
            </div>

            <!-- For Institutions -->
            <div class="space-y-4">
                <h4 class="text-xs font-bold uppercase tracking-wider text-white">
                    For Colleges & Orgs
                </h4>
                <ul class="space-y-2.5 text-sm">
                    <li><a href="/vendor-login.php" class="text-indigo-400 hover:text-white font-semibold transition-colors flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">storefront</span> Partner Portal</a></li>
                    <li><a href="/vendor-register.php" class="text-slate-400 hover:text-white transition-colors">Register as Vendor/College</a></li>
                    <li><a href="/submit-requirement.php" class="text-slate-400 hover:text-white transition-colors">Submit Requirement</a></li>
                    <li><a href="/about.php" class="text-slate-400 hover:text-white transition-colors">About Mentry</a></li>
                    <li><a href="/contact.php" class="text-slate-400 hover:text-white transition-colors">Contact & Support</a></li>
                    <li><a href="/privacy.php" class="text-slate-400 hover:text-white transition-colors">Privacy Policy</a></li>
                    <li><a href="/terms.php" class="text-slate-400 hover:text-white transition-colors">Terms of Service</a></li>
                </ul>
            </div>

            <!-- Major Training Hubs -->
            <div class="space-y-4">
                <h4 class="text-xs font-bold uppercase tracking-wider text-white">
                    Training Hubs in India
                </h4>
                <div class="flex flex-wrap gap-1.5 text-xs">
                    <?php
                    $hubs = ["Bangalore", "Chennai", "Coimbatore", "Hyderabad", "Pune", "Mumbai", "Delhi NCR", "Kochi", "Salem", "Trichy", "Madurai", "Mysore"];
                    foreach ($hubs as $hub): ?>
                        <span class="bg-slate-900 border border-slate-800 text-slate-300 px-2.5 py-1 rounded-md text-[11px]">
                            <?= htmlspecialchars($hub) ?>
                        </span>
                    <?php endforeach; ?>
                </div>
                <p class="text-[11px] text-slate-500 pt-1">
                    Delivering on-campus faculty development, placement training, and student hackathons across Tier-1/2/3 colleges.
                </p>
            </div>
        </div>

        <!-- Bottom Strip -->
        <div class="pt-8 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-slate-500">
            <p>© <?= date('Y') ?> Mentry Solutions. All rights reserved.</p>
            <div class="flex items-center gap-6">
                <a href="/privacy.php" class="hover:text-slate-400 transition-colors">Privacy Policy</a>
                <a href="/terms.php" class="hover:text-slate-400 transition-colors">Terms</a>
                <a href="/contact.php" class="hover:text-slate-400 transition-colors">Support</a>
            </div>
        </div>
    </div>
</footer>

</body>
</html>

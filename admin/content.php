<?php
// admin/content.php - CMS Management
$pageTitle = "Content & CMS";
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/includes/sidebar.php';

$testimonialCol = getCollection("Testimonial");
$categoryCol = getCollection("TrainingCategory");

$testimonials = $testimonialCol ? $testimonialCol->find([])->toArray() : [];
$categories = $categoryCol ? $categoryCol->find([], ['sort' => ['order' => 1]])->toArray() : [];
?>

<div class="space-y-8">
    <div>
        <h1 class="text-2xl md:text-3xl font-black text-slate-900 tracking-tight">Content & Curriculum CMS</h1>
        <p class="text-xs md:text-sm text-slate-500 mt-0.5">Manage training domain categories, verified testimonials, and platform copy.</p>
    </div>

    <!-- Categories Grid -->
    <div class="bg-white rounded-3xl border border-slate-200/90 shadow-card p-6 space-y-4">
        <h3 class="font-bold text-base text-slate-900">Training Categories (<?= count($categories) ?>)</h3>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
            <?php foreach ($categories as $cat): ?>
                <div class="p-4 rounded-2xl border border-slate-100 bg-slate-50 flex items-center gap-3">
                    <span class="material-symbols-outlined text-blue-600 text-xl"><?= htmlspecialchars($cat['icon'] ?? 'code') ?></span>
                    <span class="font-bold text-xs text-slate-800 truncate"><?= htmlspecialchars($cat['name']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Testimonials List -->
    <div class="bg-white rounded-3xl border border-slate-200/90 shadow-card p-6 space-y-4">
        <h3 class="font-bold text-base text-slate-900">Verified Platform Testimonials (<?= count($testimonials) ?>)</h3>
        <div class="grid md:grid-cols-3 gap-4">
            <?php foreach ($testimonials as $t): ?>
                <div class="p-5 rounded-2xl border border-slate-100 bg-slate-50 space-y-3">
                    <p class="text-xs text-slate-600 italic">"<?= htmlspecialchars($t['quote']) ?>"</p>
                    <div class="pt-2 border-t border-slate-200/60">
                        <p class="font-bold text-xs text-slate-900"><?= htmlspecialchars($t['authorName']) ?></p>
                        <p class="text-[10px] text-slate-500"><?= htmlspecialchars($t['role']) ?> • <?= htmlspecialchars($t['institution'] ?? '') ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

</main>
</div>
</body>
</html>

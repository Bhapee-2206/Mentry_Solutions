<?php
// trainer/opportunities.php
$pageTitle = "Training Opportunities Feed";
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/includes/sidebar.php';

$opportunityCol = getCollection("Opportunity");
$applicationCol = getCollection("Application");
$trainerCol = getCollection("Trainer");

$trainer = $trainerCol ? $trainerCol->findOne(['userId' => $user['id']]) : null;
$trainerId = $trainer ? (string)$trainer['_id'] : '';

$opportunities = $opportunityCol ? $opportunityCol->find(
    ['status' => 'PUBLISHED'],
    ['sort' => ['createdAt' => -1]]
)->toArray() : [];

$appliedOppIds = [];
if ($applicationCol && $trainerId) {
    $myApps = $applicationCol->find(['trainerId' => $trainerId])->toArray();
    foreach ($myApps as $ma) {
        $appliedOppIds[] = (string)$ma['opportunityId'];
    }
}
?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Active Training Opportunities</h1>
            <p class="text-xs text-slate-500 mt-0.5">Explore open college assignments matching your domain and apply instantly.</p>
        </div>
    </div>

<?php
$skillCol = getCollection("Skill");
$mySkills = ($skillCol && $trainerId) ? $skillCol->find(['trainerId' => $trainerId])->toArray() : [];
require_once __DIR__ . '/../includes/matching_engine.php';
?>

    <div class="space-y-4">
        <?php foreach ($opportunities as $opp): 
            $oppId = (string)$opp['_id'];
            $hasApplied = in_array($oppId, $appliedOppIds);
            $skills = is_string($opp['skillsRequired']) ? json_decode($opp['skillsRequired'], true) : (array)$opp['skillsRequired'];
            if (!$skills) $skills = explode(',', (string)$opp['skillsRequired']);

            $match = ($trainer) ? MatchingEngine::evaluateMatch($opp, $trainer, $mySkills) : ['score' => 75];
            $matchScore = $match['score'] ?? 75;
        ?>
            <div class="bg-white rounded-2xl border border-slate-200/90 p-6 shadow-card hover:shadow-card-hover transition-all flex flex-col lg:flex-row lg:items-center justify-between gap-5">
                <div class="space-y-2 flex-1">
                    <div class="flex items-center gap-2">
                        <span class="bg-orange-50 text-[#FE5E04] font-bold text-[10px] px-2.5 py-0.5 rounded-full uppercase"><?= htmlspecialchars($opp['mode']) ?></span>
                        
                        <!-- Personalized Match Badge -->
                        <span class="inline-flex items-center gap-0.5 text-[10px] font-black px-2.5 py-0.5 rounded-full <?= $matchScore >= 80 ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-orange-50 text-[#FE5E04] border border-orange-200' ?>">
                            <span class="material-symbols-outlined text-[12px]">bolt</span>
                            <?= $matchScore ?>% Match
                        </span>

                        <span class="text-[11px] font-mono text-slate-400">ID: <?= htmlspecialchars($opp['jobId'] ?? $oppId) ?></span>
                        <?php if ($hasApplied): ?>
                            <span class="bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] font-bold px-2 py-0.5 rounded-full">Applied</span>
                        <?php endif; ?>
                    </div>
                    <a href="/opportunity-details.php?id=<?= $oppId ?>" class="font-bold text-base text-slate-900 hover:text-[#FE5E04] transition-colors block">
                        <?= htmlspecialchars($opp['title']) ?>
                    </a>
                    <p class="text-xs text-slate-500">
                        <?= htmlspecialchars($opp['city']) ?>, <?= htmlspecialchars($opp['state']) ?> • <?= htmlspecialchars($opp['durationDays']) ?> Days • Starts <?= formatDate($opp['startDate']) ?>
                    </p>
                    <div class="flex flex-wrap gap-1.5 pt-1">
                        <?php foreach (array_slice($skills, 0, 4) as $s): ?>
                            <span class="bg-slate-50 text-slate-700 text-[11px] px-2 py-0.5 rounded border border-slate-200"><?= htmlspecialchars(trim($s)) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="shrink-0 flex lg:flex-col items-center lg:items-end justify-between gap-3 pt-3 lg:pt-0 border-t lg:border-t-0 border-slate-100">
                    <div class="text-left lg:text-right">
                        <span class="text-[10px] text-slate-400 font-bold uppercase block">Remuneration</span>
                        <p class="font-black text-lg text-[#FE5E04]"><?= formatINR($opp['dailyRateMin']) ?> – <?= formatINR($opp['dailyRateMax']) ?> / day</p>
                    </div>

                    <?php if ($hasApplied): ?>
                        <a href="/trainer/applications.php" class="bg-slate-100 text-slate-700 text-xs font-bold px-4 py-2 rounded-xl hover:bg-slate-200 transition-colors">View Application</a>
                    <?php else: ?>
                        <a href="/opportunity-details.php?id=<?= $oppId ?>" class="bg-[#FE5E04] hover:bg-[#E04E00] text-white text-xs font-bold px-5 py-2 rounded-xl transition-all shadow-md shadow-orange-500/20">
                            View & Apply
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

</main>
</div>
</body>
</html>

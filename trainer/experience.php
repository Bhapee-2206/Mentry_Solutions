<?php
// trainer/experience.php
$pageTitle = "My Teaching Experience";
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/includes/sidebar.php';

$expCol = getCollection("Experience");
$trainerCol = getCollection("Trainer");
$trainer = $trainerCol ? $trainerCol->findOne(['userId' => $user['id']]) : null;
$trainerId = $trainer ? (string)$trainer['_id'] : '';

$experiences = $expCol ? $expCol->find(['trainerId' => $trainerId])->toArray() : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['addExp'])) {
    $organization = trim($_POST['organization'] ?? '');
    $role = trim($_POST['role'] ?? '');
    $type = trim($_POST['type'] ?? 'COLLEGE_TRAINING');
    $studentsTrained = (int)($_POST['studentsTrained'] ?? 100);
    $description = trim($_POST['description'] ?? '');

    if (!empty($organization) && !empty($role) && $expCol && $trainerId) {
        $expCol->insertOne([
            'trainerId' => $trainerId,
            'organization' => $organization,
            'role' => $role,
            'experienceType' => $type,
            'studentsTrained' => $studentsTrained,
            'description' => $description,
            'startDate' => new MongoDB\BSON\UTCDateTime(),
            'createdAt' => new MongoDB\BSON\UTCDateTime(),
            'updatedAt' => new MongoDB\BSON\UTCDateTime()
        ]);
        $experiences = $expCol->find(['trainerId' => $trainerId])->toArray();
    }
}
?>

<div class="max-w-4xl mx-auto space-y-6">
    <div>
        <h1 class="text-2xl font-black text-slate-900 tracking-tight">College & Industry Experience</h1>
        <p class="text-xs text-slate-500 mt-0.5">Record past university workshops, college placement bootcamps, and corporate training programs.</p>
    </div>

    <!-- Add Experience Form -->
    <form method="POST" action="/trainer/experience.php" class="bg-white p-6 rounded-3xl border border-slate-200/90 shadow-card space-y-4">
        <input type="hidden" name="addExp" value="1">
        <h3 class="font-bold text-sm text-slate-900">Add Past Training Engagement</h3>
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">College / Organization *</label>
                <input type="text" name="organization" required placeholder="e.g. PES University, SRM Institute" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none">
            </div>
            <div>
                <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Role / Subject Handled *</label>
                <input type="text" name="role" required placeholder="e.g. Lead Python & DSA Placement Trainer" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none">
            </div>
            <div>
                <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Engagement Type</label>
                <select name="type" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-xs outline-none">
                    <option value="COLLEGE_TRAINING">College Placement Training</option>
                    <option value="BOOTCAMP">Technical Bootcamp / Hackathon</option>
                    <option value="CORPORATE_TRAINING">Corporate Upskilling</option>
                    <option value="FACULTY_DEVELOPMENT">Faculty Development Program (FDP)</option>
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Approx Students Trained</label>
                <input type="number" name="studentsTrained" value="120" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-xs outline-none">
            </div>
        </div>

        <div>
            <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Description / Key Highlights</label>
            <textarea name="description" rows="2" placeholder="Topics covered, lab assignments, feedback rating..." class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none"></textarea>
        </div>

        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs px-6 py-2.5 rounded-xl shadow-xs transition-colors">
            Save Experience
        </button>
    </form>

    <!-- Experience List -->
    <div class="bg-white rounded-3xl border border-slate-200/90 shadow-card p-6 space-y-4">
        <h3 class="font-bold text-base text-slate-900">Recorded Engagements (<?= count($experiences) ?>)</h3>
        <?php if (empty($experiences)): ?>
            <p class="text-xs text-slate-400">No past engagements recorded yet. Add your college workshops above.</p>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($experiences as $e): ?>
                    <div class="p-5 rounded-2xl border border-slate-100 bg-slate-50 space-y-2">
                        <div class="flex items-center justify-between">
                            <h4 class="font-bold text-sm text-slate-900"><?= htmlspecialchars($e['organization']) ?></h4>
                            <span class="bg-blue-50 text-blue-700 text-[10px] font-bold px-2 py-0.5 rounded uppercase">
                                <?= htmlspecialchars(str_replace('_', ' ', $e['experienceType'] ?? 'COLLEGE')) ?>
                            </span>
                        </div>
                        <p class="text-xs font-semibold text-slate-700"><?= htmlspecialchars($e['role']) ?></p>
                        <?php if (!empty($e['studentsTrained'])): ?>
                            <span class="text-[11px] text-emerald-700 font-bold bg-emerald-50 px-2 py-0.5 rounded inline-block">
                                <?= htmlspecialchars($e['studentsTrained']) ?> Students Trained
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($e['description'])): ?>
                            <p class="text-xs text-slate-500 leading-relaxed"><?= htmlspecialchars($e['description']) ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

</main>
</div>
</body>
</html>

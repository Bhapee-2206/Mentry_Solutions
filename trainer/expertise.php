<?php
// trainer/expertise.php
$pageTitle = "My Technical Expertise";
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/includes/sidebar.php';

$skillCol = getCollection("Skill");
$trainerCol = getCollection("Trainer");
$trainer = $trainerCol ? $trainerCol->findOne(['userId' => $user['id']]) : null;
$trainerId = $trainer ? (string)$trainer['_id'] : '';

$skills = $skillCol ? $skillCol->find(['trainerId' => $trainerId])->toArray() : [];

$added = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['addSkill'])) {
    $name = trim($_POST['skillName'] ?? '');
    $category = trim($_POST['category'] ?? 'Languages');
    $level = trim($_POST['level'] ?? 'ADVANCED');
    $years = (int)($_POST['yearsOfExperience'] ?? 3);

    if (!empty($name) && $skillCol && $trainerId) {
        $skillCol->insertOne([
            'trainerId' => $trainerId,
            'name' => $name,
            'category' => $category,
            'proficiencyLevel' => $level,
            'yearsOfExperience' => $years,
            'verified' => true,
            'createdAt' => new MongoDB\BSON\UTCDateTime(),
            'updatedAt' => new MongoDB\BSON\UTCDateTime()
        ]);
        $skills = $skillCol->find(['trainerId' => $trainerId])->toArray();
        $added = true;
    }
}
?>

<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Technical Expertise & Skills</h1>
            <p class="text-xs text-slate-500 mt-0.5">Manage your core programming languages, frameworks, and tools for college matching.</p>
        </div>
    </div>

    <!-- Add Skill Form -->
    <form method="POST" action="/trainer/expertise.php" class="bg-white p-6 rounded-3xl border border-slate-200/90 shadow-card space-y-4">
        <input type="hidden" name="addSkill" value="1">
        <h3 class="font-bold text-sm text-slate-900">Add New Technology Skill</h3>
        <div class="grid sm:grid-cols-4 gap-3">
            <div>
                <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Technology / Tool *</label>
                <input type="text" name="skillName" required placeholder="e.g. Python, Docker, React" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none">
            </div>
            <div>
                <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Category</label>
                <select name="category" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-xs outline-none">
                    <option value="Languages">Programming Languages</option>
                    <option value="Frameworks">Frameworks & Libraries</option>
                    <option value="Cloud">Cloud & Infrastructure</option>
                    <option value="Database">Databases & Big Data</option>
                    <option value="AI">AI & Machine Learning</option>
                    <option value="Hardware">Embedded & VLSI</option>
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Proficiency</label>
                <select name="level" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-xs outline-none">
                    <option value="EXPERT">Expert (Production & Training)</option>
                    <option value="ADVANCED">Advanced</option>
                    <option value="INTERMEDIATE">Intermediate</option>
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Exp (Years)</label>
                <div class="flex gap-2">
                    <input type="number" name="yearsOfExperience" value="3" min="1" max="25" class="w-20 bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-xs outline-none">
                    <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-xs transition-colors">Add</button>
                </div>
            </div>
        </div>
    </form>

    <!-- Skills List -->
    <div class="bg-white rounded-3xl border border-slate-200/90 shadow-card p-6 space-y-4">
        <h3 class="font-bold text-base text-slate-900">Your Verified Skill Stack (<?= count($skills) ?>)</h3>
        <?php if (empty($skills)): ?>
            <p class="text-xs text-slate-400">No specific skills listed yet. Add your main technologies above.</p>
        <?php else: ?>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
                <?php foreach ($skills as $s): ?>
                    <div class="p-4 rounded-2xl border border-slate-100 bg-slate-50 flex items-center justify-between">
                        <div>
                            <p class="font-bold text-xs text-slate-900"><?= htmlspecialchars($s['name']) ?></p>
                            <p class="text-[10px] text-slate-400"><?= htmlspecialchars($s['category'] ?? 'Tech') ?> • <?= htmlspecialchars($s['yearsOfExperience'] ?? 3) ?> Yrs</p>
                        </div>
                        <span class="bg-blue-50 text-blue-700 text-[10px] font-bold px-2 py-0.5 rounded-full border border-blue-200">
                            <?= htmlspecialchars($s['proficiencyLevel'] ?? 'ADVANCED') ?>
                        </span>
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

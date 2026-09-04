<?php
// trainer/expertise.php - Unified Technical Expertise & Teaching Experience
$pageTitle = "My Expertise & Experience";
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/includes/sidebar.php';

$skillCol = getCollection("Skill");
$expCol = getCollection("Experience");
$trainerCol = getCollection("Trainer");
$trainer = $trainerCol ? $trainerCol->findOne(['userId' => $user['id']]) : null;
$trainerId = $trainer ? (string)$trainer['_id'] : '';

$message = null;
$error = null;

// Handle Add Skill
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
        $message = "Skill '{$name}' added to your profile.";
    } else {
        $error = "Please provide a valid technology skill name.";
    }
}

// Handle Add Experience
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
        $message = "Training engagement at '{$organization}' recorded.";
    } else {
        $error = "Please fill in all mandatory experience fields.";
    }
}

$skills = $skillCol && $trainerId ? $skillCol->find(['trainerId' => $trainerId])->toArray() : [];
$experiences = $expCol && $trainerId ? $expCol->find(['trainerId' => $trainerId])->toArray() : [];
?>

<div class="max-w-5xl mx-auto space-y-8">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
        <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Expertise & Experience</h1>
            <p class="text-xs text-slate-500 mt-0.5">Manage your verified technology stack and historical college workshop delivery records.</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-xs font-bold text-slate-500">Mentry ID:</span>
            <span class="font-mono text-xs font-black text-[#FE5E04] bg-orange-50 border border-orange-200 px-3 py-1 rounded-xl">
                <?= htmlspecialchars(getMentryCode('TRAINER', $trainer ?? $user)) ?>
            </span>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-2xl text-xs font-bold flex items-center gap-2">
            <span class="material-symbols-outlined text-base">check_circle</span>
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-2xl text-xs font-bold flex items-center gap-2">
            <span class="material-symbols-outlined text-base">error</span>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- SECTION 1: Technical Expertise & Verified Skills -->
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold">
                    <span class="material-symbols-outlined text-lg">code</span>
                </div>
                <h2 class="text-base font-extrabold text-slate-900">Technical Skill Stack (<?= count($skills) ?>)</h2>
            </div>
            <span class="text-[11px] font-bold text-slate-400 uppercase">Part 1 of 2</span>
        </div>

        <!-- Add Skill Form -->
        <form method="POST" action="/trainer/expertise.php" class="bg-white p-6 rounded-3xl border border-slate-200/90 shadow-card space-y-4">
            <input type="hidden" name="addSkill" value="1">
            <h3 class="font-bold text-xs text-slate-700 uppercase">Add Technology Skill</h3>
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
                        <option value="Aptitude">Quantitative & Verbal Aptitude</option>
                        <option value="Soft Skills">Soft Skills & Communication</option>
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
                        <input type="number" name="yearsOfExperience" value="3" min="1" max="30" class="w-20 bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-xs outline-none font-bold">
                        <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-xs transition-colors">
                            + Add
                        </button>
                    </div>
                </div>
            </div>
        </form>

        <!-- Skills List -->
        <div class="bg-white rounded-3xl border border-slate-200/90 shadow-card p-6 space-y-4">
            <?php if (empty($skills)): ?>
                <p class="text-xs text-slate-400 py-2">No technical skills added yet. Add your primary curriculum topics above or upload a resume in Documents for automated extraction.</p>
            <?php else: ?>
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    <?php foreach ($skills as $s): ?>
                        <div class="p-3.5 rounded-2xl border border-slate-100 bg-slate-50 flex items-center justify-between">
                            <div>
                                <p class="font-bold text-xs text-slate-900"><?= htmlspecialchars($s['name']) ?></p>
                                <p class="text-[10px] text-slate-400"><?= htmlspecialchars($s['category'] ?? 'Tech') ?> • <?= htmlspecialchars($s['yearsOfExperience'] ?? 3) ?> Yrs</p>
                            </div>
                            <span class="bg-blue-50 text-blue-700 text-[10px] font-bold px-2.5 py-0.5 rounded-full border border-blue-200">
                                <?= htmlspecialchars($s['proficiencyLevel'] ?? 'ADVANCED') ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- SECTION 2: Teaching Experience & Past Engagements -->
    <div class="space-y-4 pt-4 border-t border-slate-200/80">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-xl bg-orange-50 text-[#FE5E04] flex items-center justify-center font-bold">
                    <span class="material-symbols-outlined text-lg">work_history</span>
                </div>
                <h2 class="text-base font-extrabold text-slate-900">College & Industry Engagements (<?= count($experiences) ?>)</h2>
            </div>
            <span class="text-[11px] font-bold text-slate-400 uppercase">Part 2 of 2</span>
        </div>

        <!-- Add Experience Form -->
        <form method="POST" action="/trainer/expertise.php" class="bg-white p-6 rounded-3xl border border-slate-200/90 shadow-card space-y-4">
            <input type="hidden" name="addExp" value="1">
            <h3 class="font-bold text-xs text-slate-700 uppercase">Add Past Training Engagement</h3>
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
                <textarea name="description" rows="2" placeholder="Topics covered, hands-on lab projects, student feedback rating..." class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none"></textarea>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="bg-[#FE5E04] hover:bg-[#E04E00] text-white font-bold text-xs px-6 py-2.5 rounded-xl shadow-xs transition-colors">
                    Save Training Engagement
                </button>
            </div>
        </form>

        <!-- Experience List -->
        <div class="bg-white rounded-3xl border border-slate-200/90 shadow-card p-6 space-y-4">
            <?php if (empty($experiences)): ?>
                <p class="text-xs text-slate-400 py-2">No past training records added yet. Record your past university and corporate sessions above.</p>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($experiences as $e): 
                        $orgName = $e['organization'] ?? ($e['company'] ?? ($e['institution'] ?? ($e['college'] ?? '')));
                    ?>
                        <div class="p-4 sm:p-5 rounded-2xl border border-slate-100 bg-slate-50 space-y-2">
                            <div class="flex items-center justify-between">
                                <h4 class="font-bold text-sm text-slate-900"><?= htmlspecialchars(!empty($orgName) ? $orgName : 'Corporate / Campus Engagement') ?></h4>
                                <span class="bg-orange-50 text-[#FE5E04] text-[10px] font-bold px-2.5 py-0.5 rounded-full border border-orange-200 uppercase">
                                    <?= htmlspecialchars(str_replace('_', ' ', $e['experienceType'] ?? 'COLLEGE')) ?>
                                </span>
                            </div>
                            <p class="text-xs font-semibold text-slate-700"><?= htmlspecialchars($e['role'] ?? 'Technical Trainer') ?></p>
                            <?php if (!empty($e['studentsTrained'])): ?>
                                <span class="text-[11px] text-emerald-700 font-bold bg-emerald-50 px-2.5 py-0.5 rounded-lg inline-block border border-emerald-200/60">
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
</div>

</main>
</div>
</body>
</html>

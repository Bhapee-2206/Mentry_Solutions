<?php
// trainer/profile.php
$pageTitle = "My Trainer Profile";
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/includes/sidebar.php';

$trainerCol = getCollection("Trainer");
$trainer = $trainerCol ? $trainerCol->findOne(['userId' => $user['id']]) : null;

$saved = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $professionalTitle = trim($_POST['professionalTitle'] ?? '');
    $primaryDomain = trim($_POST['primaryDomain'] ?? '');
    $currentCity = trim($_POST['currentCity'] ?? '');
    $currentState = trim($_POST['currentState'] ?? '');
    $dailyRateINR = (float)($_POST['dailyRateINR'] ?? 6000);
    $travelPreference = trim($_POST['travelPreference'] ?? 'PAN_INDIA');
    $bio = trim($_POST['bio'] ?? '');

    if ($trainerCol) {
        $trainerCode = $trainer['trainerCode'] ?? ($trainer['mentryId'] ?? ($user['trainerCode'] ?? ''));
        if (empty($trainerCode)) {
            $trainerCode = getNextSequentialMentryId('TRAINER');
        }

        $setData = [
            'userId' => (string)$user['id'],
            'trainerCode' => $trainerCode,
            'mentryId' => $trainerCode,
            'professionalTitle' => $professionalTitle,
            'primaryDomain' => $primaryDomain,
            'currentCity' => $currentCity,
            'currentState' => $currentState,
            'dailyRateINR' => $dailyRateINR,
            'travelPreference' => $travelPreference,
            'bio' => $bio,
            'status' => $trainer['status'] ?? 'PENDING_APPROVAL',
            'updatedAt' => new MongoDB\BSON\UTCDateTime()
        ];
        if (!empty($user['name'])) $setData['name'] = $user['name'];
        if (!empty($user['email'])) $setData['email'] = $user['email'];
        if (!empty($user['phone'])) $setData['phone'] = $user['phone'];
        if (!empty($user['avatar'])) $setData['avatar'] = $user['avatar'];

        $filter = !empty($trainer['_id']) ? ['_id' => $trainer['_id']] : ['userId' => (string)$user['id']];
        $trainerCol->updateOne(
            $filter,
            [
                '$set' => $setData,
                '$setOnInsert' => [
                    'createdAt' => new MongoDB\BSON\UTCDateTime(),
                    'joinedAt' => new MongoDB\BSON\UTCDateTime(),
                    'availabilityStatus' => 'AVAILABLE_NOW',
                    'verified' => false,
                    'skills' => []
                ]
            ],
            ['upsert' => true]
        );
        $trainer = $trainerCol->findOne(['userId' => (string)$user['id']]);

        // Keep User document in sync
        $userCol = getCollection("User");
        if ($userCol) {
            try {
                $userCol->updateOne(
                    ['_id' => new MongoDB\BSON\ObjectId((string)$user['id'])],
                    ['$set' => [
                        'trainerCode' => $trainerCode,
                        'mentryId' => $trainerCode
                    ]]
                );
            } catch (\Throwable $e) {}
        }
        $saved = true;
    }
}

$avatarSuccess = $_SESSION['avatar_success'] ?? null;
$avatarError = $_SESSION['avatar_error'] ?? null;
unset($_SESSION['avatar_success'], $_SESSION['avatar_error']);
?>

<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Trainer Profile</h1>
            <p class="text-xs text-slate-500 mt-0.5">Manage your professional credentials, expected rates, and travel availability.</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-xs font-bold text-slate-500">Official Mentry ID:</span>
            <span class="font-mono text-xs font-black text-[#FE5E04] bg-orange-50 border border-orange-200 px-3 py-1.5 rounded-xl shadow-2xs">
                <?= htmlspecialchars(getMentryCode('TRAINER', $trainer ?? $user)) ?>
            </span>
        </div>
    </div>

    <?php if ($saved): ?>
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-2xl text-xs font-bold">
            Profile updated successfully.
        </div>
    <?php endif; ?>

    <?php if ($avatarSuccess): ?>
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-2xl text-xs font-bold">
            <?= htmlspecialchars($avatarSuccess) ?>
        </div>
    <?php endif; ?>

    <?php if ($avatarError): ?>
        <div class="bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-2xl text-xs font-bold">
            <?= htmlspecialchars($avatarError) ?>
        </div>
    <?php endif; ?>

    <!-- Profile Photo Upload Card -->
    <div class="bg-white p-6 sm:p-8 rounded-3xl border border-slate-200/90 shadow-card">
        <div class="flex flex-col sm:flex-row items-center gap-6">
            <div class="relative group">
                <img src="<?= htmlspecialchars(getUserAvatar($user, 200)) ?>" class="w-24 h-24 rounded-3xl object-cover border-2 border-slate-200 shadow-md">
            </div>

            <div class="space-y-2 flex-1 text-center sm:text-left">
                <div class="flex flex-wrap items-center justify-center sm:justify-start gap-2">
                    <h3 class="font-bold text-sm text-slate-900">Profile Photo</h3>
                    <span class="bg-orange-50 text-[#FE5E04] text-[10px] font-extrabold px-2.5 py-0.5 rounded-full border border-orange-200">
                        Max 2MB Limit
                    </span>
                </div>
                <p class="text-xs text-slate-500">Upload a professional headshot for your college trainer dossier. JPG, PNG, or WebP format.</p>

                <form action="/actions/upload-avatar.php" method="POST" enctype="multipart/form-data" class="flex flex-col sm:flex-row items-center gap-2.5 pt-1">
                    <input type="file" name="avatar" required accept="image/jpeg,image/png,image/webp" class="text-xs text-slate-600 bg-slate-50 border border-slate-200 rounded-xl p-2 file:mr-2 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:text-[11px] file:font-bold file:bg-[#FE5E04]/10 file:text-[#FE5E04] hover:file:bg-[#FE5E04]/20 cursor-pointer">
                    <button type="submit" class="bg-[#FE5E04] hover:bg-[#E04E00] text-white font-bold text-xs px-4 py-2.5 rounded-xl shadow-xs transition-colors flex items-center gap-1 shrink-0">
                        <span class="material-symbols-outlined text-[16px]">upload</span>
                        Upload Photo
                    </button>
                </form>
            </div>
        </div>
    </div>

    <form method="POST" action="/trainer/profile.php" class="bg-white p-8 rounded-3xl border border-slate-200/90 shadow-card space-y-6">
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Full Name</label>
                <input type="text" disabled value="<?= htmlspecialchars($user['name']) ?>" class="w-full bg-slate-100 border border-slate-200 rounded-xl p-3 text-xs text-slate-500 font-semibold cursor-not-allowed">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Registered Email</label>
                <input type="email" disabled value="<?= htmlspecialchars($user['email']) ?>" class="w-full bg-slate-100 border border-slate-200 rounded-xl p-3 text-xs text-slate-500 font-semibold cursor-not-allowed">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Professional Title *</label>
                <input type="text" name="professionalTitle" required value="<?= htmlspecialchars($trainer['professionalTitle'] ?? '') ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Primary Domain *</label>
                <select name="primaryDomain" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none">
                    <?php
                    $domains = ["Programming", "Data Science", "Cloud", "Database", "VLSI", "Cybersecurity", "Aptitude", "Soft Skills", "Management"];
                    foreach ($domains as $d): ?>
                        <option value="<?= $d ?>" <?= ($trainer['primaryDomain'] ?? '') === $d ? 'selected' : '' ?>><?= $d ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Base City</label>
                <input type="text" name="currentCity" value="<?= htmlspecialchars($trainer['currentCity'] ?? '') ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Base State</label>
                <input type="text" name="currentState" value="<?= htmlspecialchars($trainer['currentState'] ?? '') ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Daily Rate (₹/Day)</label>
                <input type="number" name="dailyRateINR" value="<?= htmlspecialchars($trainer['dailyRateINR'] ?? 6000) ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none font-bold text-blue-700">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Travel Preference</label>
                <select name="travelPreference" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none">
                    <option value="PAN_INDIA" <?= ($trainer['travelPreference'] ?? '') === 'PAN_INDIA' ? 'selected' : '' ?>>Pan-India Offline Travel</option>
                    <option value="SOUTH_INDIA" <?= ($trainer['travelPreference'] ?? '') === 'SOUTH_INDIA' ? 'selected' : '' ?>>South India Only</option>
                    <option value="LOCAL_CITY" <?= ($trainer['travelPreference'] ?? '') === 'LOCAL_CITY' ? 'selected' : '' ?>>Local City Only</option>
                    <option value="ONLINE_ONLY" <?= ($trainer['travelPreference'] ?? '') === 'ONLINE_ONLY' ? 'selected' : '' ?>>Virtual / Online Only</option>
                </select>
            </div>
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Professional Bio</label>
            <textarea name="bio" rows="4" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none"><?= htmlspecialchars($trainer['bio'] ?? '') ?></textarea>
        </div>

        <div class="pt-2 flex justify-end">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs px-8 py-3 rounded-xl shadow-md transition-all">
                Save Profile Changes
            </button>
        </div>
    </form>
</div>

</main>
</div>
</body>
</html>

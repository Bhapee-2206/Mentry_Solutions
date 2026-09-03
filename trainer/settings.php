<?php
// trainer/settings.php
$pageTitle = "Account Settings";
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/includes/sidebar.php';

$updated = false;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPass = $_POST['currentPassword'] ?? '';
    $newPass = $_POST['newPassword'] ?? '';

    if (empty($currentPass) || empty($newPass)) {
        $error = "Both current and new passwords are required.";
    } else {
        $userCol = getCollection("User");
        $dbUser = $userCol ? $userCol->findOne(['_id' => new MongoDB\BSON\ObjectId($user['id'])]) : null;

        if (!$dbUser || !verifyPassword($currentPass, $dbUser['password'])) {
            $error = "Current password is incorrect.";
        } else {
            $userCol->updateOne(
                ['_id' => $dbUser['_id']],
                ['$set' => ['password' => hashPassword($newPass), 'updatedAt' => new MongoDB\BSON\UTCDateTime()]]
            );
            $updated = true;
        }
    }
}
?>

<div class="max-w-3xl mx-auto space-y-6">
    <div>
        <h1 class="text-2xl font-black text-slate-900 tracking-tight">Account & Security Settings</h1>
        <p class="text-xs text-slate-500 mt-0.5">Manage your login password and account credentials.</p>
    </div>

    <?php if ($updated): ?>
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-2xl text-xs font-bold">
            Password updated successfully!
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-2xl text-xs font-bold">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="/trainer/settings.php" class="bg-white p-8 rounded-3xl border border-slate-200/90 shadow-card space-y-4">
        <h3 class="font-bold text-sm text-slate-900 border-b border-slate-100 pb-2">Change Password</h3>
        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Current Password</label>
            <input type="password" name="currentPassword" required placeholder="••••••••" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">New Password</label>
            <input type="password" name="newPassword" required placeholder="••••••••" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none">
        </div>
        <div class="pt-2">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs px-6 py-3 rounded-xl shadow-xs transition-colors">
                Update Password
            </button>
        </div>
    </form>
</div>

</main>
</div>
</body>
</html>

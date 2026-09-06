<?php
// vendor/profile.php - Vendor Profile & Billing Details
$pageTitle = "Organization Profile";
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/locations.php';
require_once __DIR__ . '/includes/sidebar.php';

$vendorId = $user['id'];
$userCol = getCollection("User");
$dbUser = $userCol ? $userCol->findOne(['_id' => new MongoDB\BSON\ObjectId($vendorId)]) : null;

$success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $organizationName = trim($_POST['organizationName'] ?? '');
    $contactPerson = trim($_POST['contactPerson'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? 'Tamil Nadu');
    list($city, $state) = normalizeIndiaLocation($city, $state);
    $website = trim($_POST['website'] ?? '');
    $billingAddress = trim($_POST['billingAddress'] ?? '');
    $gstNumber = trim($_POST['gstNumber'] ?? '');

    if ($userCol) {
        $userCol->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($vendorId)],
            ['$set' => [
                'organizationName' => $organizationName,
                'name' => $contactPerson,
                'phone' => $phone,
                'city' => $city,
                'state' => $state,
                'website' => $website,
                'billingAddress' => $billingAddress,
                'gstNumber' => $gstNumber,
                'updatedAt' => new MongoDB\BSON\UTCDateTime()
            ]]
        );

        $_SESSION['user']['name'] = $contactPerson;
        $_SESSION['user']['organizationName'] = $organizationName;
        $dbUser = $userCol->findOne(['_id' => new MongoDB\BSON\ObjectId($vendorId)]);
        $success = true;
    }
}
?>

<div class="max-w-4xl mx-auto space-y-6">
    <div>
        <h1 class="text-2xl md:text-3xl font-black text-slate-900 tracking-tight">Organization Profile & Billing</h1>
        <p class="text-xs md:text-sm text-slate-500 mt-1">Manage institutional credentials, billing addresses, and primary coordinators.</p>
    </div>

    <?php if ($success): ?>
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-2xl text-xs font-bold flex items-center gap-2">
            <span class="material-symbols-outlined text-base">check_circle</span>
            Profile information updated successfully!
        </div>
    <?php endif; ?>

    <?php 
    $avatarSuccess = $_SESSION['avatar_success'] ?? null;
    $avatarError = $_SESSION['avatar_error'] ?? null;
    unset($_SESSION['avatar_success'], $_SESSION['avatar_error']);
    ?>

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

    <!-- Organization Logo / Photo Card -->
    <div class="bg-white p-6 sm:p-8 rounded-3xl border border-slate-200/90 shadow-card">
        <div class="flex flex-col sm:flex-row items-center gap-6">
            <img src="<?= htmlspecialchars($dbUser['avatar'] ?? $dbUser['logo'] ?? "https://avatar.vercel.sh/" . urlencode($dbUser['name'] ?? 'V') . ".png") ?>" class="w-20 h-20 rounded-2xl object-cover border border-slate-200 shadow-sm">
            <div class="space-y-2 flex-1 text-center sm:text-left">
                <div class="flex flex-wrap items-center justify-center sm:justify-start gap-2">
                    <h3 class="font-bold text-sm text-slate-900">Institution Logo / Representative Photo</h3>
                    <span class="bg-orange-50 text-[#FE5E04] text-[10px] font-bold px-2 py-0.5 rounded border border-orange-200">Max 2MB</span>
                </div>
                <p class="text-xs text-slate-500">Official logo or coordinator photo shown on campus workshop postings.</p>

                <form action="/actions/upload-avatar.php" method="POST" enctype="multipart/form-data" class="flex flex-col sm:flex-row items-center gap-2.5 pt-1">
                    <input type="file" name="avatar" required accept="image/jpeg,image/png,image/webp" class="text-xs text-slate-600 bg-slate-50 border border-slate-200 rounded-xl p-2 file:mr-2 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:text-[11px] file:font-bold file:bg-[#FE5E04]/10 file:text-[#FE5E04] hover:file:bg-[#FE5E04]/20 cursor-pointer">
                    <button type="submit" class="bg-[#FE5E04] hover:bg-[#E04E00] text-white font-bold text-xs px-4 py-2.5 rounded-xl shadow-xs transition-colors flex items-center gap-1 shrink-0">
                        <span class="material-symbols-outlined text-[16px]">upload</span>
                        Upload Logo
                    </button>
                </form>
            </div>
        </div>
    </div>

    <form method="POST" action="/vendor/profile.php" class="bg-white p-8 rounded-3xl border border-slate-200/90 shadow-card space-y-6">
        <div class="grid sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2">
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Organization / College Name</label>
                <input type="text" name="organizationName" required value="<?= htmlspecialchars($dbUser['organizationName'] ?? ($dbUser['name'] ?? '')) ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs font-bold text-slate-900 outline-none focus:bg-white">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Primary Coordinator Name</label>
                <input type="text" name="contactPerson" required value="<?= htmlspecialchars($dbUser['name'] ?? '') ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs outline-none focus:bg-white">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Official Work Email (Non-editable)</label>
                <input type="email" disabled value="<?= htmlspecialchars($dbUser['email'] ?? '') ?>" class="w-full bg-slate-100 border border-slate-200 rounded-xl p-3 text-xs text-slate-500 cursor-not-allowed">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Contact Phone Number</label>
                <input type="text" name="phone" value="<?= htmlspecialchars($dbUser['phone'] ?? '') ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs outline-none focus:bg-white">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Website / Portal URL</label>
                <input type="url" name="website" value="<?= htmlspecialchars($dbUser['website'] ?? '') ?>" placeholder="https://company.com" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs outline-none focus:bg-white">
            </div>

            <div class="sm:col-span-2">
                <?= renderStateDistrictSelectors('state', 'city', $dbUser['state'] ?? 'Tamil Nadu', $dbUser['city'] ?? '', false, 'State', 'District / City', 'focus:ring-indigo-500/20') ?>
            </div>

            <div class="sm:col-span-2">
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Official GSTIN / Tax Identification</label>
                <input type="text" name="gstNumber" value="<?= htmlspecialchars($dbUser['gstNumber'] ?? '') ?>" placeholder="29ABCDE1234F1Z5" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs outline-none focus:bg-white font-mono uppercase">
            </div>

            <div class="sm:col-span-2">
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Official Billing / Campus Address</label>
                <textarea name="billingAddress" rows="3" placeholder="Full institutional address for contract invoicing..." class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs outline-none focus:bg-white"><?= htmlspecialchars($dbUser['billingAddress'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="flex justify-end pt-2">
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs px-8 py-3 rounded-xl shadow-md transition-all">
                Save Organization Profile
            </button>
        </div>
    </form>
</div>

</main>
</div>
</body>
</html>

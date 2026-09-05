<?php
// vendor-register.php - Vendor & College Registration
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';

sendAntiCacheHeaders();

if (isLoggedIn()) {
    $currUser = getCurrentUser();
    if ($currUser['role'] === 'VENDOR' || $currUser['role'] === 'COLLEGE') {
        header("Location: /vendor-dashboard.php");
        exit();
    } elseif ($currUser['role'] === 'ADMIN' || $currUser['role'] === 'SUPER_ADMIN') {
        header("Location: /admin/trainers.php");
        exit();
    }
}

$error = null;
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $organizationName = trim($_POST['organizationName'] ?? '');
    $organizationType = trim($_POST['organizationType'] ?? 'STAFFING_VENDOR');
    $contactPerson = trim($_POST['contactPerson'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? 'Karnataka');
    $website = trim($_POST['website'] ?? '');

    $personErr = validateNameInput($contactPerson, 'Contact person name');
    $emailErr = validateEmailInput($email);
    $phoneErr = validatePhoneInput($phone, 'Phone number');

    if (empty($organizationName)) {
        $error = "Organization / College Name is required.";
    } elseif ($personErr) {
        $error = $personErr;
    } elseif ($emailErr) {
        $error = $emailErr;
    } elseif ($phoneErr) {
        $error = $phoneErr;
    } elseif (empty($password) || strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } else {
        $userCol = getCollection("User");
        $existing = $userCol ? $userCol->findOne(['email' => new MongoDB\BSON\Regex('^' . preg_quote($email) . '$', 'i')]) : null;

        if ($existing) {
            $error = "An account with this email address already exists. Each email can only create one account.";
            $errorExistingEmail = $email;
        } else {
            $vendorCode = getNextSequentialMentryId($organizationType === 'COLLEGE' ? 'COLLEGE' : 'VENDOR');

            $userInsert = $userCol->insertOne([
                'vendorCode' => $vendorCode,
                'mentryId' => $vendorCode,
                'name' => $contactPerson,
                'organizationName' => $organizationName,
                'organizationType' => $organizationType,
                'email' => $email,
                'phone' => $phone,
                'password' => hashPassword($password),
                'role' => ($organizationType === 'COLLEGE' ? 'COLLEGE' : 'VENDOR'),
                'city' => $city,
                'state' => $state,
                'website' => $website,
                'status' => 'ACTIVE',
                'createdAt' => new MongoDB\BSON\UTCDateTime(),
                'updatedAt' => new MongoDB\BSON\UTCDateTime()
            ]);

            $success = true;

            // Dispatch real-time Admin Notification
            require_once __DIR__ . '/includes/notifications.php';
            notifyAdmin(
                'NEW_VENDOR',
                "New Partner Registered: {$organizationName}",
                "{$organizationName} registered as {$organizationType} partner ({$vendorCode}). Contact: {$contactPerson} (Mobile: {$phone}, Email: {$email}) in {$city}, {$state}.",
                "/admin/vendor-requests.php",
                [
                    'vendorCode' => $vendorCode,
                    'organizationName' => $organizationName,
                    'organizationType' => $organizationType,
                    'contactPerson' => $contactPerson,
                    'phone' => $phone,
                    'email' => $email
                ]
            );
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register as Partner / Vendor | Mentry Solutions</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .mesh-bg {
            background-color: #f8fafc;
            background-image: 
                radial-gradient(at 10% 10%, rgba(99, 102, 241, 0.08) 0px, transparent 50%),
                radial-gradient(at 90% 90%, rgba(37, 99, 235, 0.07) 0px, transparent 50%);
        }
    </style>
</head>
<body class="min-h-screen mesh-bg py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-2xl mx-auto space-y-6">
        <!-- Logo & Title -->
        <div class="text-center space-y-3">
            <a href="/index.php" class="inline-block group">
                <div class="bg-white p-1 rounded-2xl shadow-sm border border-slate-100 inline-block group-hover:scale-105 transition-transform">
                    <img src="/public/mentry.png" alt="Mentry Solutions" class="h-12 w-auto mx-auto object-contain">
                </div>
            </a>
            <div>
                <span class="inline-flex items-center gap-1 bg-indigo-50 text-indigo-700 text-[11px] font-extrabold uppercase px-2.5 py-0.5 rounded-full border border-indigo-200/80 mb-2">
                    <span class="material-symbols-outlined text-[14px]">corporate_fare</span>
                    Partner Registration
                </span>
                <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Register as College or Vendor Partner</h1>
                <p class="text-xs sm:text-sm text-slate-500 mt-1 max-w-lg mx-auto">Access India's vetted trainer network, submit private syllabus demands, and manage on-campus deliveries.</p>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="bg-white rounded-3xl p-8 border border-emerald-200 shadow-xl text-center space-y-4">
                <div class="w-16 h-16 bg-emerald-50 text-emerald-600 rounded-full flex items-center justify-center mx-auto">
                    <span class="material-symbols-outlined text-3xl">check_circle</span>
                </div>
                <h3 class="text-xl font-black text-slate-900">Partner Account Created!</h3>
                <p class="text-xs text-slate-600 max-w-md mx-auto">Your institutional partner profile is active. You can now submit private job requirements for administrator review and trainer matching.</p>
                <div class="pt-2">
                    <a href="/vendor-login.php" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs px-8 py-3 rounded-xl shadow-md transition-all inline-flex items-center gap-2">
                        Sign In to Partner Portal
                        <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                    </a>
                </div>
            </div>
        <?php else: ?>
            <?php if ($error): ?>
                <div class="bg-rose-50 border border-rose-200 text-rose-800 p-4 rounded-2xl text-xs space-y-2.5">
                    <div class="flex items-center gap-2 font-bold text-rose-900">
                        <span class="material-symbols-outlined text-rose-600 text-lg">info</span>
                        <span>Account Notice</span>
                    </div>
                    <p class="text-rose-700 leading-relaxed font-medium">
                        <?= htmlspecialchars($error) ?>
                    </p>
                    <?php if (!empty($errorExistingEmail)): ?>
                        <div class="flex flex-wrap items-center gap-2 pt-1">
                            <a href="/vendor-login.php?email=<?= urlencode($errorExistingEmail) ?>" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-3.5 py-1.5 rounded-xl shadow-xs transition-all inline-flex items-center gap-1">
                                <span>Sign In to Partner Portal</span>
                                <span class="material-symbols-outlined text-[14px]">arrow_forward</span>
                            </a>
                            <a href="/forgot-password.php?email=<?= urlencode($errorExistingEmail) ?>" class="bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 font-bold px-3 py-1.5 rounded-xl transition-all">
                                Reset Password
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="/vendor-register.php" autocomplete="off" class="bg-white rounded-3xl border border-slate-200/90 shadow-xl p-8 space-y-6">
                <div class="grid sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Organization / College / Company Name *</label>
                        <input type="text" name="organizationName" required placeholder="e.g. Apex EdTech Solutions / RV Engineering College" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-indigo-500/20 outline-none font-bold text-slate-900">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Partner Type *</label>
                        <select name="organizationType" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs outline-none">
                            <option value="STAFFING_VENDOR">Staffing & Recruitment Vendor</option>
                            <option value="EDTECH_CLIENT">EdTech / Training Company</option>
                            <option value="COLLEGE">College / University Placement Cell</option>
                            <option value="CORPORATE">Corporate Enterprise</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Primary Contact Person *</label>
                        <input type="text" name="contactPerson" required placeholder="e.g. Rajesh Kumar" pattern="[a-zA-Z\s\.\'-]{2,50}" title="Name can only contain letters, spaces, dots, or hyphens (no numbers allowed)" oninput="this.value = this.value.replace(/[0-9]/g, '')" value="<?= htmlspecialchars($_POST['contactPerson'] ?? '') ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-indigo-500/20 outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Official Work Email *</label>
                        <input type="email" name="email" required placeholder="partner@company.com" pattern="^[a-zA-Z0-9._%+-]+@(?!gmail\.co$)(?!yahoo\.co$)(?!hotmail\.co$)(?!outlook\.co$)[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$" title="Please enter a valid work email address (.co domain is not permitted for this provider)" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-indigo-500/20 outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Contact Phone Number *</label>
                        <input type="tel" name="phone" required placeholder="+91 98765 43210" pattern="^(?:\+91[\s\-]?)?[6-9]\d{4}[\s\-]?\d{5}$" title="Please enter a valid 10-digit mobile number excluding country code (e.g. 9876543210 or +91 98765 43210)" oninput="this.value = this.value.replace(/[^0-9+\s\-]/g, '')" maxlength="16" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-indigo-500/20 outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">City *</label>
                        <input type="text" name="city" required placeholder="e.g. Bengaluru" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-indigo-500/20 outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">State *</label>
                        <input type="text" name="state" required value="Karnataka" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-indigo-500/20 outline-none">
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Website / Portal URL</label>
                        <input type="url" name="website" placeholder="https://example.com" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-indigo-500/20 outline-none">
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Create Account Password *</label>
                        <div class="relative">
                            <input type="password" id="vendorPassword" name="password" required placeholder="••••••••" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 pr-11 text-xs focus:bg-white focus:ring-2 focus:ring-indigo-500/20 outline-none">
                            <button type="button" onclick="togglePasswordVisibility('vendorPassword', this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 focus:outline-none p-1" aria-label="Toggle password visibility">
                                <span class="material-symbols-outlined text-[18px] select-none">visibility</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="pt-2 flex items-center justify-between border-t border-slate-100">
                    <a href="/vendor-login.php" class="text-xs text-slate-500 hover:text-indigo-600 font-semibold">
                        Already registered? Sign In
                    </a>
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs px-8 py-3.5 rounded-xl shadow-md transition-all flex items-center gap-1.5">
                        <span>Register Partner Profile</span>
                        <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <script>
    function togglePasswordVisibility(inputId, btn) {
        const input = document.getElementById(inputId);
        if (!input) return;
        const icon = btn.querySelector('.material-symbols-outlined');
        if (input.type === 'password') {
            input.type = 'text';
            if (icon) icon.textContent = 'visibility_off';
        } else {
            input.type = 'password';
            if (icon) icon.textContent = 'visibility';
        }
    }

    window.addEventListener('pageshow', function(event) {
        if (event.persisted || (window.performance && window.performance.navigation && window.performance.navigation.type === 2)) {
            window.location.replace(window.location.href);
        }
    });
    </script>
</body>
</html>

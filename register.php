<?php
// register.php - Trainer Registration
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';

sendAntiCacheHeaders();

if (isLoggedIn()) {
    $currUser = getCurrentUser();
    if ($currUser['role'] === 'TRAINER') {
        header("Location: /trainer/dashboard.php");
        exit();
    } elseif ($currUser['role'] === 'ADMIN' || $currUser['role'] === 'SUPER_ADMIN') {
        header("Location: /admin/trainers.php");
        exit();
    }
}

$error = null;
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $primaryDomain = trim($_POST['primaryDomain'] ?? 'Programming');
    $currentCity = trim($_POST['currentCity'] ?? '');

    $nameErr = validateNameInput($name, 'Full Name');
    $emailErr = validateEmailInput($email);
    $phoneErr = validatePhoneInput($phone, 'Mobile number');

    if ($nameErr) {
        $error = $nameErr;
    } elseif ($emailErr) {
        $error = $emailErr;
    } elseif ($phoneErr) {
        $error = $phoneErr;
    } elseif (empty($password) || empty($currentCity)) {
        $error = "Please fill in all mandatory fields including password and city.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } else {
        $userCol = getCollection("User");
        $existing = $userCol ? $userCol->findOne(['email' => new MongoDB\BSON\Regex('^' . preg_quote($email) . '$', 'i')]) : null;

        if ($existing) {
            $error = "An account with this email address already exists. Each email can only create one account.";
            $errorExistingEmail = $email;
        } else {
            $trainerCode = getNextSequentialMentryId('TRAINER');

            $userInsert = $userCol->insertOne([
                'name' => $name,
                'email' => $email,
                'password' => hashPassword($password),
                'phone' => $phone,
                'role' => 'TRAINER',
                'trainerCode' => $trainerCode,
                'mentryId' => $trainerCode,
                'createdAt' => new MongoDB\BSON\UTCDateTime(),
                'updatedAt' => new MongoDB\BSON\UTCDateTime()
            ]);

            $userId = (string)$userInsert->getInsertedId();
            $title = $primaryDomain . ' Technical Trainer';

            $trainerCol = getCollection("Trainer");
            $trainerInsert = $trainerCol->insertOne([
                'userId' => $userId,
                'trainerCode' => $trainerCode,
                'mentryId' => $trainerCode,
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'status' => 'PENDING_APPROVAL',
                'availabilityStatus' => 'AVAILABLE_NOW',
                'professionalTitle' => $title,
                'primaryDomain' => $primaryDomain,
                'currentCity' => $currentCity,
                'currentState' => 'India',
                'totalExperienceYears' => 3,
                'collegeExperienceYears' => 1,
                'dailyRateINR' => 6000,
                'travelPreference' => 'PAN_INDIA',
                'bio' => '',
                'profileCompletion' => 50,
                'joinedAt' => new MongoDB\BSON\UTCDateTime(),
                'createdAt' => new MongoDB\BSON\UTCDateTime(),
                'updatedAt' => new MongoDB\BSON\UTCDateTime()
            ]);

            $trainerId = (string)$trainerInsert->getInsertedId();

            // Dispatch real-time Admin Notification
            require_once __DIR__ . '/includes/notifications.php';
            notifyAdmin(
                'NEW_TRAINER',
                "New Trainer Registered: {$name}",
                "{$name} ({$trainerCode}) registered as a trainer specializing in {$primaryDomain} from {$currentCity} (Mobile: {$phone}).",
                "/admin/trainer-view.php?id=" . $trainerId,
                [
                    'trainerId' => $trainerId,
                    'trainerCode' => $trainerCode,
                    'userId' => $userId,
                    'domain' => $primaryDomain,
                    'city' => $currentCity,
                    'phone' => $phone
                ]
            );

            // Auto-login into session immediately
            $_SESSION['user'] = [
                'id' => $userId,
                'email' => $email,
                'name' => $name,
                'role' => 'TRAINER',
                'avatar' => null,
                'trainerCode' => $trainerCode,
                'mentryId' => $trainerCode,
                'trainerId' => $trainerId,
                'status' => 'PENDING_APPROVAL'
            ];

            setPersistentSessionCookie($_SESSION['user']);

            header("Location: /trainer/dashboard.php?new_signup=1");
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join Trainer Network | Mentry Solutions</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .mesh-bg {
            background-color: #f8fafc;
            background-image: 
                radial-gradient(at 10% 10%, rgba(37, 99, 235, 0.08) 0px, transparent 50%),
                radial-gradient(at 90% 0%, rgba(14, 165, 233, 0.07) 0px, transparent 50%);
        }
    </style>
</head>
<body class="min-h-screen mesh-bg py-12 px-4 flex flex-col justify-center items-center">
    <div class="max-w-xl w-full bg-white border border-slate-200 rounded-3xl p-8 sm:p-10 shadow-2xl space-y-6">
        <div class="text-center space-y-2">
            <a href="/index.php" class="inline-block">
                <img src="/public/mentry.png" alt="Mentry Solutions" class="h-12 w-auto mx-auto object-contain">
            </a>
            <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Join India's Trainer Network</h1>
            <p class="text-xs text-slate-500">Fast 30-second sign up. You can complete your full dossier inside your dashboard.</p>
        </div>

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
                        <a href="/login.php?email=<?= urlencode($errorExistingEmail) ?>" class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-3.5 py-1.5 rounded-xl shadow-xs transition-all inline-flex items-center gap-1">
                            <span>Sign In to Your Account</span>
                            <span class="material-symbols-outlined text-[14px]">arrow_forward</span>
                        </a>
                        <a href="/forgot-password.php?email=<?= urlencode($errorExistingEmail) ?>" class="bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 font-bold px-3 py-1.5 rounded-xl transition-all">
                            Reset Password
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="/register.php" class="space-y-4" autocomplete="off">
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Full Name *</label>
                <input type="text" name="name" required placeholder="e.g. Ramesh Kumar" pattern="[a-zA-Z\s\.\'-]{2,50}" title="Name can only contain letters, spaces, dots, or hyphens (no numbers allowed)" oninput="this.value = this.value.replace(/[0-9]/g, '')" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none">
            </div>

            <div class="grid sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Email Address *</label>
                    <input type="email" name="email" required placeholder="ramesh@example.com" pattern="^[a-zA-Z0-9._%+-]+@(?!gmail\.co$)(?!yahoo\.co$)(?!hotmail\.co$)(?!outlook\.co$)[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$" title="Please enter a valid email address (.co domain is not permitted for this provider)" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">WhatsApp / Phone *</label>
                    <input type="tel" name="phone" required placeholder="+91 98765 43210" pattern="^(?:\+91[\s\-]?)?[6-9]\d{4}[\s\-]?\d{5}$" title="Please enter a valid 10-digit mobile number excluding country code (e.g. 9876543210 or +91 98765 43210)" oninput="this.value = this.value.replace(/[^0-9+\s\-]/g, '')" maxlength="16" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Create Password *</label>
                <div class="relative">
                    <input type="password" id="registerPassword" name="password" required placeholder="••••••••" autocomplete="new-password" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 pr-11 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none">
                    <button type="button" onclick="togglePasswordVisibility('registerPassword', this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 focus:outline-none p-1" aria-label="Toggle password visibility">
                        <span class="material-symbols-outlined text-[18px] select-none">visibility</span>
                    </button>
                </div>
            </div>

            <div class="grid sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Primary Domain *</label>
                    <select name="primaryDomain" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none font-medium">
                        <option value="Programming">Programming & Software</option>
                        <option value="Data Science">Data Science & AI/ML</option>
                        <option value="Cloud">Cloud & DevOps</option>
                        <option value="VLSI">VLSI & Embedded Systems</option>
                        <option value="Cybersecurity">Cybersecurity</option>
                        <option value="Aptitude">Aptitude & Placement Reasoning</option>
                        <option value="Soft Skills">Soft Skills & Personality Development</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Current City *</label>
                    <input type="text" name="currentCity" required placeholder="e.g. Bangalore, Chennai" value="<?= htmlspecialchars($_POST['currentCity'] ?? '') ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none">
                </div>
            </div>

            <div class="pt-2">
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold text-sm py-3.5 rounded-xl transition-all shadow-md flex items-center justify-center gap-2">
                    Create Trainer Account
                    <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                </button>
            </div>
        </form>

        <div class="pt-2 border-t border-slate-100 text-center text-xs text-slate-500">
            Already registered? <a href="/login.php" class="text-blue-600 font-bold hover:underline">Login to Portal</a>
        </div>
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

    // Prevent browser bfcache redo / restoring stale form states on Alt + Left Arrow
    window.addEventListener('pageshow', function(event) {
        if (event.persisted || (window.performance && window.performance.navigation && window.performance.navigation.type === 2)) {
            window.location.replace(window.location.href);
        }
    });
    </script>
</body>
</html>

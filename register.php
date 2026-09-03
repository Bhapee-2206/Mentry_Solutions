<?php
// register.php - Trainer Registration
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$error = null;
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $professionalTitle = trim($_POST['professionalTitle'] ?? '');
    $primaryDomain = trim($_POST['primaryDomain'] ?? 'Programming');
    $currentCity = trim($_POST['currentCity'] ?? '');
    $currentState = trim($_POST['currentState'] ?? 'Karnataka');
    $totalExperienceYears = (int)($_POST['totalExperienceYears'] ?? 3);
    $collegeExperienceYears = (int)($_POST['collegeExperienceYears'] ?? 1);
    $dailyRateINR = (float)($_POST['dailyRateINR'] ?? 6000);
    $travelPreference = trim($_POST['travelPreference'] ?? 'PAN_INDIA');
    $bio = trim($_POST['bio'] ?? '');

    if (empty($name) || empty($email) || empty($password) || empty($phone) || empty($professionalTitle)) {
        $error = "Please fill in all mandatory fields.";
    } else {
        $userCol = getCollection("User");
        $existing = $userCol ? $userCol->findOne(['email' => $email]) : null;

        if ($existing) {
            $error = "An account with this email already exists. Please log in.";
        } else {
            $userInsert = $userCol->insertOne([
                'name' => $name,
                'email' => $email,
                'password' => hashPassword($password),
                'phone' => $phone,
                'role' => 'TRAINER',
                'createdAt' => new MongoDB\BSON\UTCDateTime(),
                'updatedAt' => new MongoDB\BSON\UTCDateTime()
            ]);

            $userId = (string)$userInsert->getInsertedId();

            $trainerCol = getCollection("Trainer");
            $trainerCol->insertOne([
                'userId' => $userId,
                'status' => 'PENDING_APPROVAL',
                'professionalTitle' => $professionalTitle,
                'primaryDomain' => $primaryDomain,
                'currentCity' => $currentCity,
                'currentState' => $currentState,
                'totalExperienceYears' => $totalExperienceYears,
                'collegeExperienceYears' => $collegeExperienceYears,
                'dailyRateINR' => $dailyRateINR,
                'travelPreference' => $travelPreference,
                'bio' => $bio,
                'profileCompletion' => 85,
                'joinedAt' => new MongoDB\BSON\UTCDateTime(),
                'createdAt' => new MongoDB\BSON\UTCDateTime(),
                'updatedAt' => new MongoDB\BSON\UTCDateTime()
            ]);

            $success = true;
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
    <div class="max-w-2xl w-full bg-white border border-slate-200 rounded-3xl p-8 sm:p-10 shadow-2xl space-y-6">
        <div class="text-center space-y-2">
            <a href="/index.php" class="inline-block">
                <img src="/public/mentry.png" alt="Mentry Solutions" class="h-12 w-auto mx-auto object-contain">
            </a>
            <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Join India's Trainer Network</h1>
            <p class="text-xs text-slate-500">Deliver college placement training programs with guaranteed daily honorariums.</p>
        </div>

        <?php if ($success): ?>
            <div class="bg-emerald-50 border border-emerald-200 rounded-3xl p-8 text-center space-y-4">
                <span class="material-symbols-outlined text-4xl text-emerald-600">verified</span>
                <h3 class="text-xl font-bold text-emerald-950">Registration Complete!</h3>
                <p class="text-xs text-emerald-800 leading-relaxed max-w-md mx-auto">
                    Your trainer account has been created. Our academic verification panel will review your credentials and notify you upon profile activation.
                </p>
                <div class="pt-2">
                    <a href="/login.php" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-xs px-6 py-3 rounded-xl inline-block shadow-md">
                        Login to Trainer Portal
                    </a>
                </div>
            </div>
        <?php else: ?>
            <?php if ($error): ?>
                <div class="bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-2xl text-xs font-bold">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="/register.php" class="space-y-6">
                <!-- Section 1: Account Info -->
                <div class="space-y-4">
                    <h3 class="text-xs font-bold uppercase text-slate-400 border-b border-slate-100 pb-2">1. Personal & Contact Details</h3>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Full Name *</label>
                            <input type="text" name="name" required placeholder="e.g. Ramesh Kumar" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Email ID *</label>
                            <input type="email" name="email" required placeholder="ramesh@example.com" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Phone / WhatsApp *</label>
                            <input type="tel" name="phone" required placeholder="+91 98765 43210" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Create Password *</label>
                            <input type="password" name="password" required placeholder="••••••••" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none">
                        </div>
                    </div>
                </div>

                <!-- Section 2: Professional Details -->
                <div class="space-y-4">
                    <h3 class="text-xs font-bold uppercase text-slate-400 border-b border-slate-100 pb-2">2. Expertise & Training Experience</h3>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Professional Title *</label>
                            <input type="text" name="professionalTitle" required placeholder="e.g. Senior Full Stack Java & DevOps Trainer" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none">
                        </div>
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
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Total Industry Exp (Years)</label>
                            <input type="number" name="totalExperienceYears" value="5" min="0" max="40" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">College Training Exp (Years)</label>
                            <input type="number" name="collegeExperienceYears" value="2" min="0" max="40" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Base City *</label>
                            <input type="text" name="currentCity" required placeholder="e.g. Bangalore, Chennai, Pune" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Expected Daily Rate (₹/Day)</label>
                            <input type="number" name="dailyRateINR" value="6000" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none">
                        </div>
                    </div>
                </div>

                <!-- Section 3: Bio -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Professional Bio & Teaching Style</label>
                    <textarea name="bio" rows="3" placeholder="Briefly describe past college batches handled, certifications held, and your hands-on teaching approach..." class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none"></textarea>
                </div>

                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold text-sm py-4 rounded-xl transition-all shadow-md flex items-center justify-center gap-2">
                    Submit Trainer Application
                    <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                </button>
            </form>

            <div class="pt-4 border-t border-slate-100 text-center text-xs text-slate-500">
                Already registered? <a href="/login.php" class="text-blue-600 font-bold hover:underline">Login to Portal</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

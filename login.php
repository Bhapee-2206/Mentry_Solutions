<?php
// login.php - Trainer Portal Login
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$alreadyLoggedInAdmin = false;
$alreadyLoggedInTrainer = false;

if (isLoggedIn()) {
    $user = getCurrentUser();
    if ($user['role'] === 'TRAINER') {
        header("Location: /trainer/dashboard.php");
        exit();
    } elseif ($user['role'] === 'ADMIN' || $user['role'] === 'SUPER_ADMIN') {
        $alreadyLoggedInAdmin = true;
    }
}

$error = null;
$infoMessage = null;

if (isset($_GET['error']) && $_GET['error'] === 'trainer_required') {
    $error = "Please sign in with a verified Trainer account to access the Trainer Portal.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = "Email and password are required.";
    } else {
        $userCol = getCollection("User");
        $user = $userCol ? $userCol->findOne(['email' => $email]) : null;

        // Built-in demo trainer credentials fallback
        $demoTrainerAccounts = [
            'trainer@mentry.test' => ['pass' => 'trainer123', 'name' => 'Rajesh Verma (Senior DevOps Architect)', 'trainerId' => '65e000000000000000000021'],
            'rajesh.verma@example.com' => ['pass' => 'trainer123', 'name' => 'Rajesh Verma', 'trainerId' => '65e000000000000000000021'],
            'priya.sharma@example.com' => ['pass' => 'trainer123', 'name' => 'Dr. Priya Sharma', 'trainerId' => '65e000000000000000000022'],
        ];

        if ((!$user || !isset($user['password'])) && isset($demoTrainerAccounts[$email])) {
            $demo = $demoTrainerAccounts[$email];
            if ($password === $demo['pass']) {
                $user = [
                    '_id' => '65e000000000000000000020',
                    'email' => $email,
                    'name' => $demo['name'],
                    'role' => 'TRAINER',
                    'avatar' => 'https://avatar.vercel.sh/' . urlencode($demo['name']) . '.png'
                ];
            }
        }

        if (!$user || (isset($user['password']) && !verifyPassword($password, $user['password']))) {
            $error = "Invalid email or password. Please verify your credentials.";
        } elseif ($user['role'] === 'ADMIN' || $user['role'] === 'SUPER_ADMIN') {
            $error = "This account has Administrator privileges. Please sign in via the <a href='/admin-login.php' class='underline font-bold hover:text-blue-700'>Admin Command Center</a>.";
        } else {
            // Fetch trainer profile
            $trainerCol = getCollection("Trainer");
            $trainer = $trainerCol ? $trainerCol->findOne(['userId' => (string)$user['_id']]) : null;

            $_SESSION['user'] = [
                'id' => (string)$user['_id'],
                'email' => $user['email'],
                'name' => $user['name'],
                'role' => $user['role'] ?? 'TRAINER',
                'avatar' => $user['avatar'] ?? null,
                'trainerId' => $trainer ? (string)$trainer['_id'] : null,
                'status' => $trainer['status'] ?? 'PENDING_APPROVAL'
            ];

            $redirect = $_GET['redirect'] ?? '/trainer/dashboard.php';
            // Sanitize redirect
            if (strpos($redirect, '/admin') === 0) {
                $redirect = '/trainer/dashboard.php';
            }
            header("Location: " . $redirect);
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
    <title>Trainer Portal Login | Mentry Solutions</title>
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
                radial-gradient(at 10% 10%, rgba(37, 99, 235, 0.08) 0px, transparent 50%),
                radial-gradient(at 90% 0%, rgba(14, 165, 233, 0.07) 0px, transparent 50%);
        }
    </style>
</head>
<body class="min-h-screen mesh-bg flex flex-col justify-center items-center px-4 py-12">
    <div class="max-w-md w-full bg-white border border-slate-200 rounded-3xl p-8 sm:p-10 shadow-2xl space-y-6">
        <!-- Logo & Header -->
        <div class="text-center space-y-3">
            <a href="/index.php" class="inline-block group">
                <div class="bg-white p-1 rounded-2xl shadow-sm border border-slate-100 inline-block group-hover:scale-105 transition-transform">
                    <img src="/public/mentry.png" alt="Mentry Solutions" class="h-12 w-auto mx-auto object-contain">
                </div>
            </a>
            <div>
                <span class="inline-flex items-center gap-1 bg-blue-50 text-blue-700 text-[11px] font-extrabold uppercase px-2.5 py-0.5 rounded-full border border-blue-200/80 mb-2">
                    <span class="material-symbols-outlined text-[14px]">psychology</span>
                    Trainer Portal
                </span>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">Faculty & Trainer Sign In</h1>
                <p class="text-xs text-slate-500 mt-1">Access your college applications, verified resume, and active schedules.</p>
            </div>
        </div>

        <?php if ($alreadyLoggedInAdmin): ?>
            <div class="p-4 bg-amber-50 border border-amber-200 rounded-2xl text-xs space-y-2">
                <p class="font-bold text-amber-900 flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-amber-600 text-base">admin_panel_settings</span>
                    Currently logged in as Administrator
                </p>
                <p class="text-amber-800 text-[11px]">You are active as <strong class="font-bold"><?= htmlspecialchars($_SESSION['user']['email']) ?></strong>.</p>
                <div class="flex items-center gap-2 pt-1">
                    <a href="/admin/index.php" class="bg-amber-600 hover:bg-amber-700 text-white font-bold px-3 py-1.5 rounded-xl shadow-xs transition-colors">
                        Go to Admin Dashboard
                    </a>
                    <a href="/logout.php" class="bg-white border border-amber-300 text-amber-900 font-bold px-3 py-1.5 rounded-xl hover:bg-amber-100 transition-colors">
                        Sign Out
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Quick Demo Fill Pill -->
        <div class="p-3.5 bg-blue-50/80 border border-blue-200/80 rounded-2xl flex items-center justify-between text-xs">
            <div>
                <span class="font-bold text-blue-900 block">Trainer Demo Credentials</span>
                <span class="text-slate-600 text-[11px]">trainer@mentry.test • trainer123</span>
            </div>
            <button type="button" onclick="document.getElementById('emailInput').value='trainer@mentry.test'; document.getElementById('passInput').value='trainer123';" class="text-xs font-bold text-blue-700 bg-white border border-blue-200 px-3 py-1.5 rounded-lg shadow-xs hover:bg-blue-600 hover:text-white transition-colors">
                Auto-fill
            </button>
        </div>

        <?php if ($error): ?>
            <div class="bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-2xl text-xs font-bold leading-relaxed">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="/login.php<?= isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : '' ?>" class="space-y-4">
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Registered Trainer Email</label>
                <input type="email" id="emailInput" name="email" required placeholder="trainer@example.com" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-sm focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none text-slate-900 font-medium">
            </div>

            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="block text-xs font-bold text-slate-700 uppercase">Password</label>
                    <a href="/forgot-password.php" class="text-xs text-blue-600 hover:underline font-semibold">Forgot?</a>
                </div>
                <input type="password" id="passInput" name="password" required placeholder="••••••••" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-sm focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none text-slate-900">
            </div>

            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold text-sm py-3.5 rounded-xl transition-all shadow-md flex items-center justify-center gap-2">
                <span>Sign In to Trainer Portal</span>
                <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
            </button>
        </form>

        <div class="pt-4 border-t border-slate-100 flex flex-col gap-2 text-center text-xs">
            <p class="text-slate-500">
                New technical or soft-skills trainer? 
                <a href="/register.php" class="font-bold text-blue-600 hover:underline">Apply to Join Network</a>
            </p>
        </div>
    </div>
</body>
</html>

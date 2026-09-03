<?php
// admin-login.php - Admin & Staff Command Center Login
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/scratch/seed_initial_accounts.php';

$userCol = getCollection("User");

if (isLoggedIn() && isAdminOrStaff()) {
    header("Location: /admin/index.php");
    exit();
}

$error = null;

if (isset($_GET['error']) && $_GET['error'] === 'unauthorized') {
    $error = "Access Restricted: You must log in with an authorized Administrator or Staff account to access the Operations Center.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = "Email and password are required.";
    } else {
        $user = $userCol ? $userCol->findOne(['email' => $email]) : null;

        // Built-in demo credentials fallback (ensures immediate access online and offline)
        $demoAdminAccounts = [
            'admin@mentry.test' => ['pass' => 'admin123', 'name' => 'Operations Director (Admin 1)', 'role' => 'ADMIN', 'id' => '65e000000000000000000001'],
            'admin2@mentry.test' => ['pass' => 'admin123', 'name' => 'Lead Administrator (Admin 2)', 'role' => 'ADMIN', 'id' => '65e000000000000000000002'],
            'staff1@mentry.test' => ['pass' => 'staff123', 'name' => 'Operations Coordinator (Staff 1)', 'role' => 'STAFF', 'id' => '65e000000000000000000003'],
            'staff2@mentry.test' => ['pass' => 'staff123', 'name' => 'Talent Sourcing Specialist (Staff 2)', 'role' => 'STAFF', 'id' => '65e000000000000000000004'],
        ];

        if ((!$user || !isset($user['password'])) && isset($demoAdminAccounts[$email])) {
            $demo = $demoAdminAccounts[$email];
            if ($password === $demo['pass']) {
                $user = [
                    '_id' => $demo['id'],
                    'email' => $email,
                    'name' => $demo['name'],
                    'role' => $demo['role'],
                    'avatar' => 'https://avatar.vercel.sh/' . urlencode($demo['name']) . '.png'
                ];
            }
        }

        if (!$user || (isset($user['password']) && !verifyPassword($password, $user['password']))) {
            $error = "Invalid credentials. Please check your email and password.";
        } elseif (!in_array($user['role'], ['ADMIN', 'SUPER_ADMIN', 'STAFF'])) {
            $error = "Access Restricted: This account is registered as a <strong>{$user['role']}</strong>. Please use the appropriate portal to sign in.";
        } else {
            $_SESSION['user'] = [
                'id' => (string)$user['_id'],
                'email' => $user['email'],
                'name' => $user['name'],
                'role' => $user['role'],
                'avatar' => $user['avatar'] ?? ('https://avatar.vercel.sh/' . urlencode($user['name']) . '.png')
            ];

            $redirect = $_GET['redirect'] ?? '/admin/index.php';
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
    <title>Operations Command Login (Admin & Staff) | Mentry Solutions</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            500: '#FE5E04',
                            600: '#E04E00',
                            700: '#C23E00'
                        }
                    }
                }
            }
        }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .mesh-bg {
            background-color: #080D1A;
            background-image: 
                radial-gradient(at 10% 10%, rgba(254, 94, 4, 0.2) 0px, transparent 55%),
                radial-gradient(at 90% 90%, rgba(30, 41, 59, 0.4) 0px, transparent 60%);
        }
    </style>
</head>
<body class="min-h-screen mesh-bg flex flex-col justify-center items-center px-4 py-12 text-slate-100">
    <div class="max-w-lg w-full bg-slate-900/90 backdrop-blur-2xl border border-slate-800 rounded-3xl p-8 sm:p-10 shadow-2xl space-y-6">
        <!-- Logo & Header -->
        <div class="text-center space-y-3">
            <a href="/index.php" class="inline-block group">
                <div class="bg-white p-1.5 rounded-2xl shadow-md inline-block group-hover:scale-105 transition-transform">
                    <img src="/public/mentry.png" alt="Mentry Solutions" class="h-12 w-auto mx-auto object-contain">
                </div>
            </a>
            <div>
                <span class="inline-flex items-center gap-1 bg-[#FE5E04]/10 text-[#FE5E04] text-[11px] font-black uppercase tracking-wider px-3 py-1 rounded-full border border-[#FE5E04]/30 mb-2">
                    <span class="material-symbols-outlined text-[14px]">shield</span>
                    Operations & Staff Command
                </span>
                <h1 class="text-2xl font-black text-white tracking-tight">Admin & Staff Portal</h1>
                <p class="text-xs text-slate-400 mt-1">Authorized personnel login for job matching, trainer assignments, and logistics.</p>
            </div>
        </div>

        <!-- Quick 1-Click Fill Credentials for 2 Admin & 2 Staff -->
        <div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-4 space-y-2.5">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 flex items-center gap-1">
                    <span class="material-symbols-outlined text-[14px] text-[#FE5E04]">bolt</span>
                    Quick Initial Start Accounts
                </span>
                <span class="text-[10px] text-slate-500">Click to autofill</span>
            </div>
            <div class="grid grid-cols-2 gap-2 text-left">
                <button type="button" onclick="fillCreds('admin@mentry.test', 'admin123')" class="p-2 bg-slate-900/90 hover:bg-[#FE5E04]/20 border border-slate-700 hover:border-[#FE5E04]/40 rounded-xl text-left transition-all group">
                    <span class="text-[10px] font-extrabold text-[#FE5E04] block">ADMIN 1</span>
                    <span class="text-xs font-semibold text-white block truncate">admin@mentry.test</span>
                    <span class="text-[10px] text-slate-400">Pass: admin123</span>
                </button>
                <button type="button" onclick="fillCreds('admin2@mentry.test', 'admin123')" class="p-2 bg-slate-900/90 hover:bg-[#FE5E04]/20 border border-slate-700 hover:border-[#FE5E04]/40 rounded-xl text-left transition-all group">
                    <span class="text-[10px] font-extrabold text-[#FE5E04] block">ADMIN 2</span>
                    <span class="text-xs font-semibold text-white block truncate">admin2@mentry.test</span>
                    <span class="text-[10px] text-slate-400">Pass: admin123</span>
                </button>
                <button type="button" onclick="fillCreds('staff1@mentry.test', 'staff123')" class="p-2 bg-slate-900/90 hover:bg-blue-500/20 border border-slate-700 hover:border-blue-500/40 rounded-xl text-left transition-all group">
                    <span class="text-[10px] font-extrabold text-blue-400 block">STAFF 1</span>
                    <span class="text-xs font-semibold text-white block truncate">staff1@mentry.test</span>
                    <span class="text-[10px] text-slate-400">Pass: staff123</span>
                </button>
                <button type="button" onclick="fillCreds('staff2@mentry.test', 'staff123')" class="p-2 bg-slate-900/90 hover:bg-blue-500/20 border border-slate-700 hover:border-blue-500/40 rounded-xl text-left transition-all group">
                    <span class="text-[10px] font-extrabold text-blue-400 block">STAFF 2</span>
                    <span class="text-xs font-semibold text-white block truncate">staff2@mentry.test</span>
                    <span class="text-[10px] text-slate-400">Pass: staff123</span>
                </button>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="bg-rose-950/80 border border-rose-800 text-rose-300 px-4 py-3 rounded-2xl text-xs font-medium leading-relaxed">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="/admin-login.php<?= isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : '' ?>" class="space-y-4">
            <div>
                <label class="block text-xs font-bold text-slate-300 uppercase mb-1.5">Official Email ID</label>
                <input type="email" id="emailInput" name="email" required placeholder="admin@mentry.test" class="w-full bg-slate-800/90 border border-slate-700 rounded-xl p-3 text-sm focus:bg-slate-800 focus:border-[#FE5E04] focus:ring-2 focus:ring-[#FE5E04]/20 outline-none text-white font-medium transition-all">
            </div>

            <div>
                <div class="flex justify-between items-center mb-1.5">
                    <label class="text-xs font-bold text-slate-300 uppercase">Security Password</label>
                    <a href="/forgot-password.php" class="text-[11px] text-[#FE5E04] hover:underline">Forgot password?</a>
                </div>
                <input type="password" id="passInput" name="password" required placeholder="••••••••" class="w-full bg-slate-800/90 border border-slate-700 rounded-xl p-3 text-sm focus:bg-slate-800 focus:border-[#FE5E04] focus:ring-2 focus:ring-[#FE5E04]/20 outline-none text-white transition-all">
            </div>

            <button type="submit" class="w-full bg-[#FE5E04] hover:bg-[#E04E00] text-white font-black text-sm py-3.5 rounded-xl transition-all shadow-lg shadow-orange-500/25 flex items-center justify-center gap-2">
                <span>Enter Operations Command</span>
                <span class="material-symbols-outlined text-[18px]">lock_open</span>
            </button>
        </form>

        <div class="pt-4 border-t border-slate-800 text-center text-xs space-y-2">
            <p class="text-slate-400">
                Trainer looking to take on campus workshops?
                <a href="/login.php" class="font-bold text-[#FE5E04] hover:underline">Trainer Portal Sign In →</a>
            </p>
            <p>
                <a href="/index.php" class="text-slate-500 hover:text-slate-300 text-[11px]">← Return to Public Website</a>
            </p>
        </div>
    </div>

    <script>
        function fillCreds(email, pass) {
            document.getElementById('emailInput').value = email;
            document.getElementById('passInput').value = pass;
        }
    </script>
</body>
</html>

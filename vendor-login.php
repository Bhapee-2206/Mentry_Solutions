<?php
// vendor-login.php - Vendor & Institutional Partner Login
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

sendAntiCacheHeaders();

if (isLoggedIn()) {
    $user = getCurrentUser();
    if ($user['role'] === 'VENDOR' || $user['role'] === 'COLLEGE') {
        header("Location: /vendor/dashboard.php");
        exit();
    } elseif ($user['role'] === 'ADMIN' || $user['role'] === 'SUPER_ADMIN') {
        header("Location: /admin/index.php");
        exit();
    }
}

$error = null;

if (isset($_GET['error']) && $_GET['error'] === 'vendor_required') {
    $error = "Please sign in with a registered Vendor or Institutional Partner account.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = "Email and password are required.";
    } else {
        $userCol = getCollection("User");
        $user = $userCol ? $userCol->findOne(['email' => $email]) : null;

        // Auto-seed demo vendor account or use fallback if database is offline/pending (bcrypt hash used, no plaintext)
        if ((!$user || !isset($user['password'])) && $email === 'vendor@mentry.test' && password_verify($password, '$2y$10$5r2PqvH0GX2SF5e00FGyZeod4y4oPlzLSXljZjh25YVQQGgrc.REW')) {
            $user = [
                '_id' => '65e000000000000000000010',
                'name' => 'Nexus EdTech Staffing Solutions',
                'email' => 'vendor@mentry.test',
                'role' => 'VENDOR',
                'organizationName' => 'Nexus EdTech Staffing Solutions',
                'organizationType' => 'STAFFING_VENDOR',
                'city' => 'Bengaluru',
                'state' => 'Karnataka'
            ];
        }

        if (!$user || (isset($user['password']) && !verifyPassword($password, $user['password']))) {
            $error = "Invalid email or password. Please verify your credentials.";
        } elseif ($user['role'] !== 'VENDOR' && $user['role'] !== 'COLLEGE' && $user['role'] !== 'ADMIN' && $user['role'] !== 'SUPER_ADMIN') {
            $error = "This account is registered as a <strong>Trainer</strong>. Please use the <a href='/login.php' class='underline font-bold text-blue-700'>Trainer Login Portal</a>.";
        } else {
            $_SESSION['user'] = [
                'id' => (string)$user['_id'],
                'email' => $user['email'],
                'name' => $user['name'],
                'role' => $user['role'],
                'organizationName' => $user['organizationName'] ?? ($user['name'] ?? 'Partner Organization'),
                'avatar' => $user['avatar'] ?? null
            ];

            setPersistentSessionCookie($_SESSION['user']);

            $redirect = $_GET['redirect'] ?? '/vendor/dashboard.php';
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
    <title>Vendor & Partner Portal Login | Mentry Solutions</title>
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
                <span class="inline-flex items-center gap-1 bg-indigo-50 text-indigo-700 text-[11px] font-extrabold uppercase px-2.5 py-0.5 rounded-full border border-indigo-200/80 mb-2">
                    <span class="material-symbols-outlined text-[14px]">storefront</span>
                    Vendor & College Portal
                </span>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">Partner Sign In</h1>
                <p class="text-xs text-slate-500 mt-1">Submit private training requirements, track approval & monitor faculty deployments.</p>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-2xl text-xs font-bold leading-relaxed">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="/vendor-login.php<?= isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : '' ?>" autocomplete="off" class="space-y-4">
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Official Work Email ID</label>
                <input type="email" id="emailInput" name="email" required placeholder="name@company.com" value="<?= htmlspecialchars($_GET['email'] ?? '') ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-sm focus:bg-white focus:ring-2 focus:ring-indigo-500/20 outline-none text-slate-900 font-medium">
            </div>

            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="block text-xs font-bold text-slate-700 uppercase">Password</label>
                    <a href="/forgot-password.php<?= !empty($_GET['email']) ? '?email=' . urlencode($_GET['email']) : '' ?>" class="text-xs text-indigo-600 hover:underline font-semibold">Forgot?</a>
                </div>
                <div class="relative">
                    <input type="password" id="passInput" name="password" required placeholder="••••••••" autocomplete="current-password" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 pr-11 text-sm focus:bg-white focus:ring-2 focus:ring-indigo-500/20 outline-none text-slate-900">
                    <button type="button" onclick="togglePasswordVisibility('passInput', this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 focus:outline-none p-1" aria-label="Toggle password visibility">
                        <span class="material-symbols-outlined text-[20px] select-none">visibility</span>
                    </button>
                </div>
            </div>

            <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-sm py-3.5 rounded-xl transition-all shadow-md flex items-center justify-center gap-2">
                <span>Access Vendor Portal</span>
                <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
            </button>
        </form>

        <div class="pt-4 border-t border-slate-100 flex flex-col gap-3 text-center text-xs">
            <p class="text-slate-500">
                New college, staffing vendor, or edtech client? 
                <a href="/vendor-register.php" class="font-bold text-indigo-600 hover:underline">Register as a Partner</a>
            </p>

            <div class="flex items-center justify-center gap-4 text-slate-400 text-[11px] pt-1">
                <a href="/login.php" class="hover:text-slate-700 font-semibold">Trainer Login Portal →</a>
            </div>
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

<?php
// forgot-password.php - Password Recovery with Google SMTP verification
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/mailer.php';

$sent = false;
$error = null;
$emailTarget = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid registered email address.";
    } else {
        $userCol = getCollection("User");
        $user = $userCol ? $userCol->findOne(['email' => $email]) : null;

        if ($user) {
            $code = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            $token = bin2hex(random_bytes(24));
            $expiresAt = new MongoDB\BSON\UTCDateTime((time() + 1800) * 1000); // 30 mins

            $resetCol = getCollection("PasswordReset");
            if ($resetCol) {
                // Invalidate old tokens for this email
                $resetCol->deleteMany(['email' => $email]);

                $resetCol->insertOne([
                    'email' => $email,
                    'code' => $code,
                    'token' => $token,
                    'expiresAt' => $expiresAt,
                    'used' => false,
                    'createdAt' => new MongoDB\BSON\UTCDateTime()
                ]);
            }

            $resetLink = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . "/reset-password.php?token=" . $token;

            $mailResult = sendPasswordResetEmail($user['email'], $user['name'] ?? 'User', $code, $resetLink);
            $sent = true;
            $emailTarget = $email;
        } else {
            // Protect privacy while appearing successful or showing notification
            $sent = true;
            $emailTarget = $email;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password Verification | Mentry Solutions</title>
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
    <link rel="icon" type="image/png" href="/public/mentry.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="min-h-screen bg-slate-950 flex flex-col justify-center items-center px-4 py-12 text-slate-100 relative overflow-hidden">
    <div class="absolute inset-0 opacity-20 pointer-events-none" style="background-image: radial-gradient(at 10% 10%, #FE5E04 0px, transparent 50%), radial-gradient(at 90% 90%, #FE5E04 0px, transparent 50%);"></div>

    <div class="max-w-md w-full bg-slate-900/90 backdrop-blur-2xl border border-slate-800 rounded-3xl p-8 sm:p-10 shadow-2xl space-y-6 relative z-10">
        <div class="text-center space-y-3">
            <a href="/index.php" class="inline-block">
                <div class="bg-white p-2 rounded-2xl shadow-md inline-block">
                    <img src="/public/mentry.png" alt="Mentry Solutions" class="h-10 w-auto mx-auto object-contain">
                </div>
            </a>
            <h1 class="text-2xl font-black text-white tracking-tight">Account Recovery</h1>
            <p class="text-xs text-slate-400">Enter your registered email ID to receive a verification OTP code via Mentry mailer.</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-rose-950/80 border border-rose-800 text-rose-300 px-4 py-3 rounded-2xl text-xs font-medium">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($sent): ?>
            <div class="bg-slate-800/80 border border-[#FE5E04]/40 p-6 rounded-2xl text-center space-y-4">
                <div class="w-12 h-12 rounded-2xl bg-[#FE5E04]/20 text-[#FE5E04] flex items-center justify-center mx-auto">
                    <span class="material-symbols-outlined text-2xl">mark_email_read</span>
                </div>
                <div>
                    <h3 class="font-bold text-sm text-white">Verification Code Dispatched</h3>
                    <p class="text-xs text-slate-400 mt-1">If an account matches <strong><?= htmlspecialchars($emailTarget) ?></strong>, a 6-digit OTP verification code has been dispatched to your email. Please check your inbox and spam folder.</p>
                </div>

                <a href="/reset-password.php?email=<?= urlencode($emailTarget) ?>" class="block w-full bg-[#FE5E04] hover:bg-[#E04E00] text-white font-bold text-xs py-3 rounded-xl transition-all shadow-md">
                    Enter Verification Code & Set New Password →
                </a>
            </div>
        <?php else: ?>
            <form method="POST" action="/forgot-password.php" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-300 uppercase mb-1">Registered Email ID</label>
                    <input type="email" name="email" required placeholder="trainer@example.com" class="w-full bg-slate-800/90 border border-slate-700 rounded-xl p-3 text-sm focus:bg-slate-800 focus:border-[#FE5E04] focus:ring-2 focus:ring-[#FE5E04]/20 outline-none text-white font-medium">
                </div>

                <button type="submit" class="w-full bg-[#FE5E04] hover:bg-[#E04E00] text-white font-bold text-sm py-3.5 rounded-xl transition-all shadow-lg shadow-orange-500/20 flex items-center justify-center gap-2">
                    <span>Send Verification Code</span>
                    <span class="material-symbols-outlined text-[18px]">send</span>
                </button>
            </form>
        <?php endif; ?>

        <div class="pt-4 border-t border-slate-800 text-center text-xs text-slate-400">
            Remember your credentials? <a href="/login.php" class="text-[#FE5E04] font-bold hover:underline">Back to Login</a>
        </div>
    </div>
</body>
</html>

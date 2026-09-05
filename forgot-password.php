<?php
// forgot-password.php - Unified, Premium Account Recovery & Password Reset System
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/mailer.php';

$step = 1; // 1 = Enter Email, 2 = Verify Code & Set Password, 3 = Success
$error = null;
$notice = null;
$emailTarget = strtolower(trim($_GET['email'] ?? $_POST['email'] ?? ''));
$smtpDeliveryFailed = false;
$devFallbackCode = null;

// STEP 2 SUBMISSION: Verify Code & Update Password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_password') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $code = trim($_POST['code'] ?? '');
    $newPassword = $_POST['newPassword'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';
    $emailTarget = $email;
    $step = 2;

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please provide a valid registered email address.";
    } elseif (empty($code) || strlen($code) < 4) {
        $error = "Please enter the 6-digit verification code.";
    } elseif (empty($newPassword) || strlen($newPassword) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "The new passwords do not match. Please re-enter.";
    } else {
        $resetCol = getCollection("PasswordReset");
        $userCol = getCollection("User");

        $record = $resetCol ? $resetCol->findOne([
            'email' => $email,
            'code' => $code,
            'used' => false
        ]) : null;

        // Fallback check if case variation in email
        if (!$record && $resetCol) {
            $record = $resetCol->findOne([
                'email' => new MongoDB\BSON\Regex('^' . preg_quote($email) . '$', 'i'),
                'code' => $code,
                'used' => false
            ]);
        }

        if (!$record) {
            $error = "Invalid or expired verification code. Please check your code or request a new one.";
        } else {
            // Check expiry (30 mins)
            $expired = false;
            if (isset($record['expiresAt']) && $record['expiresAt'] instanceof MongoDB\BSON\UTCDateTime) {
                if (time() > ($record['expiresAt']->toDateTime()->getTimestamp())) {
                    $expired = true;
                }
            }

            if ($expired) {
                $error = "This verification code has expired (valid for 30 minutes). Please request a new code.";
            } else {
                // Update User Password
                $userCol->updateOne(
                    ['email' => new MongoDB\BSON\Regex('^' . preg_quote($email) . '$', 'i')],
                    ['$set' => [
                        'password' => hashPassword($newPassword),
                        'updatedAt' => new MongoDB\BSON\UTCDateTime()
                    ]]
                );

                // Mark reset token used
                $resetCol->updateOne(['_id' => $record['_id']], ['$set' => ['used' => true]]);

                $step = 3; // Success!
            }
        }
    }
}
// STEP 1 SUBMISSION: Request Verification Code
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $emailTarget = $email;

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid registered email address.";
        $step = 1;
    } else {
        $userCol = getCollection("User");
        $user = $userCol ? $userCol->findOne([
            'email' => new MongoDB\BSON\Regex('^' . preg_quote($email) . '$', 'i')
        ]) : null;

        if ($user) {
            $code = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            $token = bin2hex(random_bytes(24));
            $expiresAt = new MongoDB\BSON\UTCDateTime((time() + 1800) * 1000); // 30 mins

            $resetCol = getCollection("PasswordReset");
            if ($resetCol) {
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

            $resetLink = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . "/reset-password.php?token=" . $token . "&email=" . urlencode($email);

            // Attempt SMTP dispatch
            $mailResult = sendPasswordResetEmail($user['email'], $user['name'] ?? 'User', $code, $resetLink);

            if (!empty($mailResult['success'])) {
                $step = 2;
            } elseif (!empty($mailResult['fallback'])) {
                $step = 2;
                $infoMessage = "Verification code generated and queued. Please check your inbox or use code if received.";
            } else {
                $error = "Failed to send verification email to " . htmlspecialchars($email) . ". " . 
                         (isset($mailResult['error']) ? htmlspecialchars($mailResult['error']) : 'Please check SMTP settings in .env.');
                $step = 1;
            }
        } else {
            // Non-existent email
            $error = "No account found matching this email address. Please verify your email or register.";
            $step = 1;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Recovery & Password Reset | Mentry Solutions</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="icon" type="image/png" href="/public/mentry.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .mesh-bg {
            background-color: #f8fafc;
            background-image: 
                radial-gradient(at 10% 10%, rgba(254, 94, 4, 0.08) 0px, transparent 50%),
                radial-gradient(at 90% 90%, rgba(37, 99, 235, 0.07) 0px, transparent 50%);
        }
    </style>
</head>
<body class="min-h-screen mesh-bg flex flex-col justify-center items-center px-4 py-12 text-slate-800">

    <div class="max-w-md w-full bg-white/95 backdrop-blur-xl border border-slate-200/90 rounded-3xl p-8 sm:p-10 shadow-2xl space-y-6 relative">
        
        <!-- Header & Logo -->
        <div class="text-center space-y-3">
            <a href="/index.php" class="inline-block group">
                <div class="bg-white p-2 rounded-2xl shadow-xs border border-slate-100 inline-block group-hover:scale-105 transition-transform">
                    <img src="/public/mentry.png" alt="Mentry Solutions" class="h-10 w-auto mx-auto object-contain">
                </div>
            </a>
            <div>
                <span class="inline-flex items-center gap-1 bg-orange-50 text-orange-700 text-[11px] font-extrabold uppercase px-2.5 py-0.5 rounded-full border border-orange-200 mb-1.5">
                    <span class="material-symbols-outlined text-[13px]">lock_reset</span>
                    Account Security
                </span>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">Password Recovery</h1>
                <p class="text-xs text-slate-500 mt-1">
                    <?php if ($step === 1): ?>
                        Enter your registered email to receive a 6-digit verification code.
                    <?php elseif ($step === 2): ?>
                        Enter the verification code and choose your new password.
                    <?php else: ?>
                        Your password has been successfully updated.
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <!-- Progress Steps -->
        <div class="flex items-center justify-center gap-2 text-xs font-bold pt-1">
            <div class="flex items-center gap-1.5 <?= $step >= 1 ? 'text-[#FE5E04]' : 'text-slate-400' ?>">
                <span class="w-5 h-5 rounded-full flex items-center justify-center text-[10px] <?= $step >= 1 ? 'bg-[#FE5E04] text-white' : 'bg-slate-200 text-slate-600' ?>">1</span>
                <span>Email</span>
            </div>
            <span class="text-slate-300">———</span>
            <div class="flex items-center gap-1.5 <?= $step >= 2 ? 'text-[#FE5E04]' : 'text-slate-400' ?>">
                <span class="w-5 h-5 rounded-full flex items-center justify-center text-[10px] <?= $step >= 2 ? 'bg-[#FE5E04] text-white' : 'bg-slate-200 text-slate-600' ?>">2</span>
                <span>Verify & Reset</span>
            </div>
            <span class="text-slate-300">———</span>
            <div class="flex items-center gap-1.5 <?= $step >= 3 ? 'text-emerald-600' : 'text-slate-400' ?>">
                <span class="w-5 h-5 rounded-full flex items-center justify-center text-[10px] <?= $step >= 3 ? 'bg-emerald-600 text-white' : 'bg-slate-200 text-slate-600' ?>">3</span>
                <span>Done</span>
            </div>
        </div>

        <!-- Error Alert -->
        <?php if ($error): ?>
            <div class="bg-rose-50 border border-rose-200 text-rose-800 p-3.5 rounded-2xl text-xs flex items-start gap-2">
                <span class="material-symbols-outlined text-rose-600 text-base shrink-0 mt-0.5">error</span>
                <div class="font-medium leading-relaxed"><?= htmlspecialchars($error) ?></div>
            </div>
        <?php endif; ?>

        <!-- STEP 1: ENTER EMAIL -->
        <?php if ($step === 1): ?>
            <form method="POST" action="/forgot-password.php" class="space-y-4" autocomplete="off">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Registered Email Address</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-lg pointer-events-none">mail</span>
                        <input type="email" name="email" required value="<?= htmlspecialchars($emailTarget) ?>" placeholder="trainer@example.com" class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 pl-11 pr-3 text-sm focus:bg-white focus:ring-2 focus:ring-orange-500/20 focus:border-[#FE5E04] outline-none text-slate-900 font-medium">
                    </div>
                </div>

                <button type="submit" class="w-full bg-[#FE5E04] hover:bg-[#E04E00] text-white font-bold text-sm py-3.5 rounded-xl transition-all shadow-md shadow-orange-500/20 flex items-center justify-center gap-2">
                    <span>Send Verification Code</span>
                    <span class="material-symbols-outlined text-[18px]">send</span>
                </button>
            </form>

        <!-- STEP 2: ENTER OTP & NEW PASSWORD -->
        <?php elseif ($step === 2): ?>

            <div class="bg-emerald-50 border border-emerald-200 text-emerald-900 p-4 rounded-2xl text-xs flex items-center gap-3">
                <span class="material-symbols-outlined text-emerald-600 text-2xl shrink-0">mark_email_read</span>
                <div>
                    <span class="font-bold text-emerald-950 text-sm block">Verification Code Sent</span>
                    <span class="text-xs text-emerald-800">We have sent a 6-digit verification code to <strong><?= htmlspecialchars($emailTarget) ?></strong>. Please check your inbox and spam folder.</span>
                </div>
            </div>

            <form method="POST" action="/forgot-password.php" class="space-y-4" autocomplete="off">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="email" value="<?= htmlspecialchars($emailTarget) ?>">

                <div class="flex items-center justify-between text-xs px-1">
                    <span class="text-slate-500">Account: <strong><?= htmlspecialchars($emailTarget) ?></strong></span>
                    <a href="/forgot-password.php" class="text-blue-600 font-bold hover:underline">Change Email</a>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">6-Digit Verification Code</label>
                    <input type="text" id="otpInput" name="code" required maxlength="6" placeholder="e.g. 123456" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-center text-xl tracking-[0.3em] font-mono font-black focus:bg-white focus:ring-2 focus:ring-orange-500/20 focus:border-[#FE5E04] outline-none text-[#FE5E04]">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">New Password</label>
                    <div class="relative">
                        <input type="password" id="newPassInput" name="newPassword" required placeholder="Minimum 6 characters" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 pr-11 text-sm focus:bg-white focus:ring-2 focus:ring-orange-500/20 focus:border-[#FE5E04] outline-none text-slate-900">
                        <button type="button" onclick="togglePasswordVisibility('newPassInput', this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 focus:outline-none p-1" aria-label="Toggle password visibility">
                            <span class="material-symbols-outlined text-[20px] select-none">visibility</span>
                        </button>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Confirm New Password</label>
                    <div class="relative">
                        <input type="password" id="confirmPassInput" name="confirmPassword" required placeholder="Re-enter your new password" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 pr-11 text-sm focus:bg-white focus:ring-2 focus:ring-orange-500/20 focus:border-[#FE5E04] outline-none text-slate-900">
                        <button type="button" onclick="togglePasswordVisibility('confirmPassInput', this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 focus:outline-none p-1" aria-label="Toggle password visibility">
                            <span class="material-symbols-outlined text-[20px] select-none">visibility</span>
                        </button>
                    </div>
                </div>

                <button type="submit" class="w-full bg-[#FE5E04] hover:bg-[#E04E00] text-white font-bold text-sm py-3.5 rounded-xl transition-all shadow-md shadow-orange-500/20 flex items-center justify-center gap-2">
                    <span>Update Password & Save</span>
                    <span class="material-symbols-outlined text-[18px]">verified</span>
                </button>
            </form>

        <!-- STEP 3: SUCCESS STATE -->
        <?php elseif ($step === 3): ?>
            <div class="text-center space-y-4 py-3">
                <div class="w-16 h-16 bg-emerald-50 text-emerald-600 border border-emerald-200 rounded-full flex items-center justify-center mx-auto shadow-xs">
                    <span class="material-symbols-outlined text-3xl">check_circle</span>
                </div>
                <div>
                    <h3 class="text-lg font-black text-slate-900">Password Updated Successfully</h3>
                    <p class="text-xs text-slate-500 mt-1 max-w-xs mx-auto">
                        Your account password has been reset securely. You can now sign in with your updated credentials.
                    </p>
                </div>
                <div class="pt-2 flex flex-col gap-2">
                    <a href="/login.php?email=<?= urlencode($emailTarget) ?>" class="w-full bg-[#FE5E04] hover:bg-[#E04E00] text-white font-bold text-xs py-3 rounded-xl transition-all shadow-md flex items-center justify-center gap-1.5">
                        <span>Sign In to Trainer Portal</span>
                        <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                    </a>
                    <a href="/vendor-login.php?email=<?= urlencode($emailTarget) ?>" class="w-full bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-bold text-xs py-2.5 rounded-xl transition-colors border border-indigo-200/80">
                        Sign In as College / Vendor Partner
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Footer Navigation -->
        <div class="pt-4 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
            <a href="/login.php" class="hover:text-slate-800 font-semibold flex items-center gap-1">
                <span class="material-symbols-outlined text-sm">arrow_back</span>
                <span>Back to Login</span>
            </a>
            <a href="/index.php" class="text-slate-400 hover:text-slate-600 text-[11px]">
                Public Home
            </a>
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

    // Auto-focus OTP input when entering step 2
    window.addEventListener('DOMContentLoaded', () => {
        const otp = document.getElementById('otpInput');
        if (otp) otp.focus();
    });
    </script>
</body>
</html>

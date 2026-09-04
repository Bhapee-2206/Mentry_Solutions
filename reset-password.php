<?php
// reset-password.php - Verify OTP / Token & Reset Password with Matching Modern UI/UX
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$token = trim($_GET['token'] ?? '');
$prefilledEmail = strtolower(trim($_GET['email'] ?? ''));
$success = false;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $code = trim($_POST['code'] ?? '');
    $postToken = trim($_POST['token'] ?? '');
    $newPassword = $_POST['newPassword'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';

    if (empty($newPassword) || strlen($newPassword) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "Passwords do not match. Please re-enter.";
    } else {
        $resetCol = getCollection("PasswordReset");
        $userCol = getCollection("User");

        $query = ['used' => false];
        if (!empty($postToken) && !empty($code)) {
            $query['$or'] = [
                ['token' => $postToken],
                ['email' => $email, 'code' => $code]
            ];
        } elseif (!empty($postToken)) {
            $query['token'] = $postToken;
        } else {
            $query['email'] = new MongoDB\BSON\Regex('^' . preg_quote($email) . '$', 'i');
            $query['code'] = $code;
        }

        $resetRecord = $resetCol ? $resetCol->findOne($query) : null;

        if (!$resetRecord) {
            $error = "Invalid or expired verification code / reset link.";
        } else {
            $recordTime = null;
            if (isset($resetRecord['expiresAt']) && $resetRecord['expiresAt'] instanceof MongoDB\BSON\UTCDateTime) {
                $recordTime = $resetRecord['expiresAt']->toDateTime()->getTimestamp();
            }
            if ($recordTime && time() > $recordTime) {
                $error = "This verification code has expired. Please request a new one.";
            } else {
                // Update User password
                $targetEmail = $resetRecord['email'];
                $userCol->updateOne(
                    ['email' => new MongoDB\BSON\Regex('^' . preg_quote($targetEmail) . '$', 'i')],
                    ['$set' => [
                        'password' => hashPassword($newPassword),
                        'updatedAt' => new MongoDB\BSON\UTCDateTime()
                    ]]
                );

                // Mark reset token as used
                $resetCol->updateOne(['_id' => $resetRecord['_id']], ['$set' => ['used' => true]]);
                $success = true;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set New Password | Mentry Solutions</title>
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
        <div class="text-center space-y-3">
            <a href="/index.php" class="inline-block group">
                <div class="bg-white p-2 rounded-2xl shadow-xs border border-slate-100 inline-block group-hover:scale-105 transition-transform">
                    <img src="/public/mentry.png" alt="Mentry Solutions" class="h-10 w-auto mx-auto object-contain">
                </div>
            </a>
            <div>
                <span class="inline-flex items-center gap-1 bg-orange-50 text-orange-700 text-[11px] font-extrabold uppercase px-2.5 py-0.5 rounded-full border border-orange-200 mb-1.5">
                    <span class="material-symbols-outlined text-[13px]">key</span>
                    Security Credentials
                </span>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">Create New Password</h1>
                <p class="text-xs text-slate-500 mt-1">Set a strong new password for your verified Mentry account.</p>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="bg-rose-50 border border-rose-200 text-rose-800 p-3.5 rounded-2xl text-xs flex items-start gap-2">
                <span class="material-symbols-outlined text-rose-600 text-base shrink-0 mt-0.5">error</span>
                <div class="font-medium leading-relaxed"><?= htmlspecialchars($error) ?></div>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="text-center space-y-4 py-3">
                <div class="w-16 h-16 bg-emerald-50 text-emerald-600 border border-emerald-200 rounded-full flex items-center justify-center mx-auto shadow-xs">
                    <span class="material-symbols-outlined text-3xl">check_circle</span>
                </div>
                <div>
                    <h3 class="text-lg font-black text-slate-900">Password Changed Successfully</h3>
                    <p class="text-xs text-slate-500 mt-1 max-w-xs mx-auto">
                        Your password has been updated. You can now log into your account using your new credentials.
                    </p>
                </div>
                <div class="pt-2 flex flex-col gap-2">
                    <a href="/login.php<?= !empty($prefilledEmail) ? '?email=' . urlencode($prefilledEmail) : '' ?>" class="w-full bg-[#FE5E04] hover:bg-[#E04E00] text-white font-bold text-xs py-3 rounded-xl transition-all shadow-md flex items-center justify-center gap-1.5">
                        <span>Sign In to Trainer Portal</span>
                        <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                    </a>
                    <a href="/vendor-login.php<?= !empty($prefilledEmail) ? '?email=' . urlencode($prefilledEmail) : '' ?>" class="w-full bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-bold text-xs py-2.5 rounded-xl transition-colors border border-indigo-200/80">
                        Sign In as College / Vendor Partner
                    </a>
                    <a href="/admin-login.php" class="w-full bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs py-2.5 rounded-xl transition-colors border border-slate-200">
                        Sign In to Operations Console
                    </a>
                </div>
            </div>
        <?php else: ?>
            <form method="POST" action="/reset-password.php" class="space-y-4" autocomplete="off">
                <?php if (!empty($token)): ?>
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <?php else: ?>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Registered Email</label>
                        <input type="email" name="email" required value="<?= htmlspecialchars($prefilledEmail) ?>" placeholder="your-email@example.com" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-sm focus:bg-white focus:ring-2 focus:ring-orange-500/20 focus:border-[#FE5E04] outline-none text-slate-900 font-medium">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">6-Digit Verification Code</label>
                        <input type="text" name="code" required maxlength="6" placeholder="e.g. 123456" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-center text-xl tracking-[0.3em] font-mono font-black focus:bg-white focus:ring-2 focus:ring-orange-500/20 focus:border-[#FE5E04] outline-none text-[#FE5E04]">
                    </div>
                <?php endif; ?>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">New Password</label>
                    <div class="relative">
                        <input type="password" id="resetNewPass" name="newPassword" required placeholder="Minimum 6 characters" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 pr-11 text-sm focus:bg-white focus:ring-2 focus:ring-orange-500/20 focus:border-[#FE5E04] outline-none text-slate-900">
                        <button type="button" onclick="togglePasswordVisibility('resetNewPass', this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 focus:outline-none p-1" aria-label="Toggle password visibility">
                            <span class="material-symbols-outlined text-[20px] select-none">visibility</span>
                        </button>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Confirm New Password</label>
                    <div class="relative">
                        <input type="password" id="resetConfirmPass" name="confirmPassword" required placeholder="Re-type new password" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 pr-11 text-sm focus:bg-white focus:ring-2 focus:ring-orange-500/20 focus:border-[#FE5E04] outline-none text-slate-900">
                        <button type="button" onclick="togglePasswordVisibility('resetConfirmPass', this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 focus:outline-none p-1" aria-label="Toggle password visibility">
                            <span class="material-symbols-outlined text-[20px] select-none">visibility</span>
                        </button>
                    </div>
                </div>

                <button type="submit" class="w-full bg-[#FE5E04] hover:bg-[#E04E00] text-white font-bold text-sm py-3.5 rounded-xl transition-all shadow-md shadow-orange-500/20 flex items-center justify-center gap-2">
                    <span>Update Password & Save</span>
                    <span class="material-symbols-outlined text-[18px]">verified</span>
                </button>
            </form>
        <?php endif; ?>

        <div class="pt-4 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
            <a href="/login.php" class="hover:text-slate-800 font-semibold flex items-center gap-1">
                <span class="material-symbols-outlined text-sm">arrow_back</span>
                <span>Back to Login</span>
            </a>
            <a href="/forgot-password.php" class="text-[#FE5E04] font-bold hover:underline">
                Request New Code
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
    </script>
</body>
</html>

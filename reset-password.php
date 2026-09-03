<?php
// reset-password.php - Verify OTP / Token & Reset Password
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$token = trim($_GET['token'] ?? '');
$prefilledEmail = trim($_GET['email'] ?? '');
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
        $error = "Passwords do not match.";
    } else {
        $resetCol = getCollection("PasswordReset");
        $userCol = getCollection("User");

        $query = ['used' => false];
        if (!empty($postToken)) {
            $query['token'] = $postToken;
        } else {
            $query['email'] = $email;
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
                $updateRes = $userCol->updateOne(
                    ['email' => $targetEmail],
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="min-h-screen bg-slate-950 flex flex-col justify-center items-center px-4 py-12 text-slate-100">
    <div class="max-w-md w-full bg-slate-900/90 backdrop-blur-2xl border border-slate-800 rounded-3xl p-8 sm:p-10 shadow-2xl space-y-6">
        <div class="text-center space-y-3">
            <a href="/index.php" class="inline-block">
                <div class="bg-white p-2 rounded-2xl shadow-md inline-block">
                    <img src="/public/mentry.png" alt="Mentry Solutions" class="h-10 w-auto mx-auto object-contain">
                </div>
            </a>
            <h1 class="text-2xl font-black text-white tracking-tight">Create New Password</h1>
            <p class="text-xs text-slate-400">Enter your verification details to reset your account password.</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-rose-950/80 border border-rose-800 text-rose-300 px-4 py-3 rounded-2xl text-xs font-medium">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="bg-emerald-950/80 border border-emerald-800 text-emerald-300 p-6 rounded-2xl text-center space-y-4">
                <div class="w-12 h-12 rounded-2xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center mx-auto">
                    <span class="material-symbols-outlined text-2xl">check_circle</span>
                </div>
                <div>
                    <h3 class="font-bold text-sm text-white">Password Changed Successfully</h3>
                    <p class="text-xs text-slate-400 mt-1">Your password has been updated. You can now log into your account.</p>
                </div>
                <div class="flex gap-2 justify-center">
                    <a href="/login.php" class="bg-[#FE5E04] hover:bg-[#E04E00] text-white font-bold text-xs px-5 py-2.5 rounded-xl transition-all shadow-md">
                        Trainer Login
                    </a>
                    <a href="/admin-login.php" class="bg-slate-800 hover:bg-slate-700 text-white font-bold text-xs px-5 py-2.5 rounded-xl transition-all border border-slate-700">
                        Admin / Staff Login
                    </a>
                </div>
            </div>
        <?php else: ?>
            <form method="POST" action="/reset-password.php" class="space-y-4">
                <?php if (!empty($token)): ?>
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <?php else: ?>
                    <div>
                        <label class="block text-xs font-bold text-slate-300 uppercase mb-1">Registered Email</label>
                        <input type="email" name="email" required value="<?= htmlspecialchars($prefilledEmail) ?>" placeholder="your-email@example.com" class="w-full bg-slate-800/90 border border-slate-700 rounded-xl p-3 text-sm focus:border-[#FE5E04] outline-none text-white">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-300 uppercase mb-1">6-Digit Verification OTP Code</label>
                        <input type="text" name="code" required maxlength="6" placeholder="e.g. 123456" class="w-full bg-slate-800/90 border border-slate-700 rounded-xl p-3 text-center text-lg tracking-widest font-mono font-black focus:border-[#FE5E04] outline-none text-[#FE5E04]">
                    </div>
                <?php endif; ?>

                <div>
                    <label class="block text-xs font-bold text-slate-300 uppercase mb-1">New Password</label>
                    <input type="password" name="newPassword" required placeholder="Minimum 6 characters" class="w-full bg-slate-800/90 border border-slate-700 rounded-xl p-3 text-sm focus:border-[#FE5E04] outline-none text-white">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-300 uppercase mb-1">Confirm New Password</label>
                    <input type="password" name="confirmPassword" required placeholder="Re-type new password" class="w-full bg-slate-800/90 border border-slate-700 rounded-xl p-3 text-sm focus:border-[#FE5E04] outline-none text-white">
                </div>

                <button type="submit" class="w-full bg-[#FE5E04] hover:bg-[#E04E00] text-white font-bold text-sm py-3.5 rounded-xl transition-all shadow-lg shadow-orange-500/20">
                    Update Password & Sign In
                </button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>

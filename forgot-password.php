<?php
// forgot-password.php - Unified, Premium Account Recovery & Password Reset System
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/mailer.php';

$step = 1; // 1 = Enter Email, 2 = Verify OTP & Reset, 3 = Success
$otpVerified = false;
$error = null;
$notice = null;
$emailTarget = strtolower(trim($_GET['email'] ?? $_POST['email'] ?? ''));

// ACTION: VERIFY OTP CODE (First verification step requested by user)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_otp') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $code = trim($_POST['code'] ?? '');
    $emailTarget = $email;
    $step = 2;

    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
              || (isset($_POST['ajax']) && $_POST['ajax'] === '1')
              || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

    $resetCol = getCollection("PasswordReset");
    $record = $resetCol ? $resetCol->findOne([
        'email' => new MongoDB\BSON\Regex('^' . preg_quote($email) . '$', 'i'),
        'code' => $code,
        'used' => false
    ]) : null;

    $isValid = false;
    $msg = '';

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = "Please provide a valid registered email address.";
    } elseif (empty($code) || strlen($code) < 4) {
        $msg = "Please enter the 6-digit verification code.";
    } elseif (!$record) {
        $msg = "Invalid verification code. Please check the code in your email or request a new one.";
    } else {
        $expired = false;
        if (isset($record['expiresAt']) && $record['expiresAt'] instanceof MongoDB\BSON\UTCDateTime) {
            if (time() > ($record['expiresAt']->toDateTime()->getTimestamp())) {
                $expired = true;
            }
        }

        if ($expired) {
            $msg = "This verification code has expired (valid for 30 minutes). Please request a new code.";
        } else {
            $isValid = true;
            $msg = "Verification code confirmed successfully.";
        }
    }

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $isValid,
            'message' => $msg,
            'code' => $code,
            'email' => $email
        ]);
        exit();
    } else {
        if ($isValid) {
            $otpVerified = true;
            $notice = $msg;
        } else {
            $otpVerified = false;
            $error = $msg;
        }
    }
}

// STEP 2 SUBMISSION: Verify Code & Update Password
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_password') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $code = trim($_POST['code'] ?? '');
    $newPassword = $_POST['newPassword'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';
    $emailTarget = $email;
    $step = 2;
    $otpVerified = true;

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
            $otpVerified = false;
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
                $otpVerified = false;
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

                // Fetch updated user and establish session automatically
                $authenticatedUser = $userCol->findOne(['email' => new MongoDB\BSON\Regex('^' . preg_quote($email) . '$', 'i')]);
                if ($authenticatedUser) {
                    $sessionPayload = [
                        'id' => (string)$authenticatedUser['_id'],
                        'email' => $authenticatedUser['email'],
                        'name' => $authenticatedUser['name'] ?? 'User',
                        'role' => $authenticatedUser['role'] ?? 'TRAINER',
                        'avatar' => $authenticatedUser['avatar'] ?? '',
                        'trainerCode' => $authenticatedUser['trainerCode'] ?? '',
                        'mentryId' => $authenticatedUser['mentryId'] ?? '',
                        'organizationName' => $authenticatedUser['organizationName'] ?? ''
                    ];
                    $_SESSION['user'] = $sessionPayload;
                    issuePersistentSessionCookie($sessionPayload, 30);

                    $userRole = strtoupper($sessionPayload['role'] ?? 'TRAINER');
                    if ($userRole === 'COLLEGE' || $userRole === 'VENDOR') {
                        $redirectUrl = '/vendor/dashboard.php';
                        $roleBadgeName = 'College / Vendor Partner';
                        $dashboardBtnText = 'Proceed to Partner Dashboard';
                        $btnStyle = 'bg-indigo-600 hover:bg-indigo-700 shadow-indigo-500/20';
                    } elseif ($userRole === 'ADMIN' || $userRole === 'SUPER_ADMIN' || $userRole === 'STAFF') {
                        $redirectUrl = '/admin/index.php';
                        $roleBadgeName = 'Administrator';
                        $dashboardBtnText = 'Proceed to Admin Command Center';
                        $btnStyle = 'bg-slate-900 hover:bg-slate-800 shadow-slate-900/20';
                    } else {
                        $redirectUrl = '/trainer/dashboard.php';
                        $roleBadgeName = 'Technical Trainer';
                        $dashboardBtnText = 'Proceed to Trainer Dashboard';
                        $btnStyle = 'bg-[#FE5E04] hover:bg-[#E04E00] shadow-orange-500/20';
                    }
                }

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

            $appBaseUrl = function_exists('getAppUrl') ? getAppUrl() : 'https://mentry-solutions.vercel.app';
            $resetLink = rtrim($appBaseUrl, '/') . "/reset-password.php?token=" . $token . "&email=" . urlencode($email);

            // Attempt email dispatch strictly via SMTP
            $mailResult = sendPasswordResetEmail($user['email'], $user['name'] ?? 'User', $code, $resetLink);

            if (!empty($mailResult['success'])) {
                $step = 2;
                $notice = "We have dispatched a 6-digit verification code to <strong>" . htmlspecialchars($email) . "</strong>. Please check your inbox and spam folder.";
            } else {
                $step = 1;
                $mailErrDetail = !empty($mailResult['error']) ? ' (' . htmlspecialchars($mailResult['error']) . ')' : '';
                $error = "Unable to send verification email to " . htmlspecialchars($email) . "{$mailErrDetail}. Please verify that your email address is correct and try again.";
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
    <!-- Favicon & Brand Icons -->
    <link rel="icon" type="image/png" href="/public/mentry.png?v=2">
    <link rel="shortcut icon" href="/favicon.ico?v=2">
    <link rel="apple-touch-icon" href="/public/mentry.png?v=2">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <style>
        html, body {
            max-width: 100%;
            overflow-x: hidden;
        }
        *, *::before, *::after {
            box-sizing: border-box;
        }
        body { font-family: 'Inter', sans-serif; }
        .mesh-bg {
            background-color: #f8fafc;
            background-image: 
                radial-gradient(at 10% 10%, rgba(254, 94, 4, 0.08) 0px, transparent 50%),
                radial-gradient(at 90% 90%, rgba(37, 99, 235, 0.07) 0px, transparent 50%);
        }
    </style>
</head>
<body class="min-h-screen mesh-bg flex flex-col justify-center items-center px-3.5 sm:px-4 py-8 sm:py-12 text-slate-800 w-full max-w-full overflow-x-hidden">

    <div class="max-w-md w-full bg-white/95 backdrop-blur-xl border border-slate-200/90 rounded-2xl sm:rounded-3xl p-5 sm:p-10 shadow-2xl space-y-6 relative min-w-0">
        
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
                <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Password Recovery</h1>
                <p class="text-xs text-slate-500 mt-1 break-words">
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
        <div class="flex items-center justify-center gap-1 sm:gap-2 text-[11px] sm:text-xs font-bold pt-1 flex-wrap min-w-0">
            <div class="flex items-center gap-1 <?= $step >= 1 ? 'text-[#FE5E04]' : 'text-slate-400' ?>">
                <span class="w-5 h-5 rounded-full flex items-center justify-center text-[10px] <?= $step >= 1 ? 'bg-[#FE5E04] text-white' : 'bg-slate-200 text-slate-600' ?>">1</span>
                <span>Email</span>
            </div>
            <span class="text-slate-300">——</span>
            <div id="stepBadge2" class="flex items-center gap-1 <?= ($step === 2 && !$otpVerified) ? 'text-[#FE5E04]' : ($otpVerified || $step > 2 ? 'text-emerald-600' : 'text-slate-400') ?>">
                <span id="stepNum2" class="w-5 h-5 rounded-full flex items-center justify-center text-[10px] <?= ($step === 2 && !$otpVerified) ? 'bg-[#FE5E04] text-white' : ($otpVerified || $step > 2 ? 'bg-emerald-600 text-white' : 'bg-slate-200 text-slate-600') ?>">
                    <?= ($otpVerified || $step > 2) ? '✓' : '2' ?>
                </span>
                <span>Verify OTP</span>
            </div>
            <span class="text-slate-300">——</span>
            <div id="stepBadge3" class="flex items-center gap-1 <?= ($step === 2 && $otpVerified) ? 'text-[#FE5E04]' : ($step >= 3 ? 'text-emerald-600' : 'text-slate-400') ?>">
                <span id="stepNum3" class="w-5 h-5 rounded-full flex items-center justify-center text-[10px] <?= ($step === 2 && $otpVerified) ? 'bg-[#FE5E04] text-white' : ($step >= 3 ? 'bg-emerald-600 text-white' : 'bg-slate-200 text-slate-600') ?>">
                    <?= $step >= 3 ? '✓' : '3' ?>
                </span>
                <span>Set Password</span>
            </div>
            <span class="text-slate-300">——</span>
            <div class="flex items-center gap-1 <?= $step >= 3 ? 'text-emerald-600' : 'text-slate-400' ?>">
                <span class="w-5 h-5 rounded-full flex items-center justify-center text-[10px] <?= $step >= 3 ? 'bg-emerald-600 text-white' : 'bg-slate-200 text-slate-600' ?>">4</span>
                <span>Done</span>
            </div>
        </div>

        <!-- Error Alert -->
        <div id="dynamicErrorBox" class="<?= $error ? 'flex' : 'hidden' ?> bg-rose-50 border border-rose-200 text-rose-800 p-3.5 rounded-2xl text-xs items-start gap-2 min-w-0">
            <span class="material-symbols-outlined text-rose-600 text-base shrink-0 mt-0.5">error</span>
            <div id="dynamicErrorMsg" class="font-medium leading-relaxed break-words min-w-0 flex-1"><?= htmlspecialchars($error ?? '') ?></div>
        </div>

        <!-- Success Alert -->
        <div id="dynamicSuccessBox" class="<?= ($notice && $step === 2 && $otpVerified) ? 'flex' : 'hidden' ?> bg-emerald-50 border border-emerald-200 text-emerald-900 p-3.5 rounded-2xl text-xs items-start gap-2 min-w-0">
            <span class="material-symbols-outlined text-emerald-600 text-base shrink-0 mt-0.5">check_circle</span>
            <div id="dynamicSuccessMsg" class="font-bold leading-relaxed break-words min-w-0 flex-1"><?= htmlspecialchars($notice ?? 'Verification code verified successfully!') ?></div>
        </div>

        <!-- STEP 1: ENTER EMAIL -->
        <?php if ($step === 1): ?>
            <form method="POST" action="/forgot-password.php" onsubmit="showAuthLoading('Sending Verification Code...', 'We are emailing your 6-digit security code. Please wait.');" class="space-y-4" autocomplete="off">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Registered Email Address</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-lg pointer-events-none">mail</span>
                        <input type="email" name="email" required value="<?= htmlspecialchars($emailTarget) ?>" placeholder="trainer@example.com" class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 pl-11 pr-3 text-sm focus:bg-white focus:ring-2 focus:ring-orange-500/20 focus:border-[#FE5E04] outline-none text-slate-900 font-medium">
                    </div>
                </div>

                <button type="submit" class="w-full bg-[#FE5E04] hover:bg-[#E04E00] text-white font-bold text-sm py-3.5 rounded-xl transition-all shadow-md shadow-orange-500/20 flex items-center justify-center gap-2 cursor-pointer">
                    <span>Send Verification Code</span>
                    <span class="material-symbols-outlined text-[18px]">send</span>
                </button>
            </form>

        <!-- STEP 2: VERIFY OTP FIRST, THEN REVEAL PASSWORD FIELDS -->
        <?php elseif ($step === 2): ?>

            <div id="codeDispatchedCard" class="<?= $otpVerified ? 'hidden' : 'flex' ?> bg-emerald-50 border border-emerald-200 text-emerald-900 p-3.5 sm:p-4 rounded-2xl text-xs items-start sm:items-center gap-2.5 sm:gap-3 min-w-0">
                <span class="material-symbols-outlined text-emerald-600 text-2xl shrink-0 mt-0.5 sm:mt-0">mark_email_read</span>
                <div class="min-w-0 flex-1 break-words">
                    <span class="font-bold text-emerald-950 text-sm block">Verification Code Sent</span>
                    <span class="text-xs text-emerald-800 break-words"><?= $notice ?? ('We have sent a 6-digit verification code to <strong>' . htmlspecialchars($emailTarget) . '</strong>. Please check your inbox.') ?></span>
                </div>
            </div>

            <form id="resetForm" method="POST" action="/forgot-password.php" onsubmit="return handleResetFormSubmit(event);" class="space-y-4" autocomplete="off">
                <input type="hidden" id="formAction" name="action" value="<?= $otpVerified ? 'reset_password' : 'verify_otp' ?>">
                <input type="hidden" id="emailInput" name="email" value="<?= htmlspecialchars($emailTarget) ?>">

                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1 text-xs px-1 min-w-0">
                    <span class="text-slate-500 truncate">Account: <strong class="text-slate-800 break-all"><?= htmlspecialchars($emailTarget) ?></strong></span>
                    <a href="/forgot-password.php" class="text-blue-600 font-bold hover:underline shrink-0">Change Email</a>
                </div>

                <!-- OTP Input Section -->
                <div class="space-y-1.5">
                    <div class="flex items-center justify-between">
                        <label class="block text-xs font-bold text-slate-700 uppercase">6-Digit Verification Code</label>
                        <span id="otpStatusBadge" class="<?= $otpVerified ? 'inline-flex' : 'hidden' ?> items-center gap-1 text-[11px] font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-200">
                            <span class="material-symbols-outlined text-xs">verified</span>
                            <span>Verified</span>
                        </span>
                    </div>
                    <div class="relative">
                        <input type="text" id="otpInput" name="code" required maxlength="6" <?= $otpVerified ? 'readonly' : '' ?> value="<?= htmlspecialchars($_POST['code'] ?? '') ?>" placeholder="e.g. 123456" class="w-full bg-slate-50 border <?= $otpVerified ? 'border-emerald-300 bg-emerald-50/40 text-emerald-800 font-extrabold' : 'border-slate-200 text-[#FE5E04]' ?> rounded-xl p-3 text-center text-2xl tracking-[0.35em] font-mono font-black focus:bg-white focus:ring-2 focus:ring-orange-500/20 focus:border-[#FE5E04] outline-none transition-colors">
                    </div>
                </div>

                <!-- VERIFY OTP BUTTON (Shown initially before password change) -->
                <div id="verifyOtpBtnContainer" class="<?= $otpVerified ? 'hidden' : 'block' ?> pt-1">
                    <button type="button" id="verifyOtpBtn" onclick="verifyOtpCode()" class="w-full bg-[#FE5E04] hover:bg-[#E04E00] text-white font-bold text-sm py-3.5 rounded-xl transition-all shadow-md shadow-orange-500/20 flex items-center justify-center gap-2 cursor-pointer">
                        <span>Verify OTP Code</span>
                        <span class="material-symbols-outlined text-[18px]">verified_user</span>
                    </button>
                    <div class="flex items-center justify-center mt-3">
                        <button type="button" onclick="resendOtpCode()" class="text-xs font-bold text-slate-500 hover:text-slate-800 flex items-center gap-1 transition-colors cursor-pointer">
                            <span class="material-symbols-outlined text-sm">replay</span>
                            <span>Didn't receive code? Resend</span>
                        </button>
                    </div>
                </div>

                <!-- PASSWORD FIELDS CONTAINER (Revealed ONLY after OTP is verified) -->
                <div id="passwordFieldsContainer" class="<?= $otpVerified ? 'block' : 'hidden' ?> space-y-4 pt-2 border-t border-slate-100 animate-in fade-in slide-in-from-top-3 duration-300">
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-xs font-bold text-slate-700 uppercase">New Password</label>
                            <span id="resetPassHelper" class="text-[11px] font-bold text-slate-400">Min 6 characters</span>
                        </div>
                        <div class="relative">
                            <input type="password" id="newPassInput" name="newPassword" <?= $otpVerified ? 'required' : '' ?> minlength="6" placeholder="Enter minimum 6 characters" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 pr-11 text-sm focus:bg-white focus:ring-2 focus:ring-orange-500/20 focus:border-[#FE5E04] outline-none text-slate-900 font-medium">
                            <button type="button" onclick="togglePasswordVisibility('newPassInput', this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 focus:outline-none p-1 cursor-pointer" aria-label="Toggle password visibility">
                                <span class="material-symbols-outlined text-[20px] select-none">visibility</span>
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Confirm New Password</label>
                        <div class="relative">
                            <input type="password" id="confirmPassInput" name="confirmPassword" <?= $otpVerified ? 'required' : '' ?> placeholder="Re-enter your new password" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 pr-11 text-sm focus:bg-white focus:ring-2 focus:ring-orange-500/20 focus:border-[#FE5E04] outline-none text-slate-900 font-medium">
                            <button type="button" onclick="togglePasswordVisibility('confirmPassInput', this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 focus:outline-none p-1 cursor-pointer" aria-label="Toggle password visibility">
                                <span class="material-symbols-outlined text-[20px] select-none">visibility</span>
                            </button>
                        </div>
                    </div>

                    <button type="submit" id="savePasswordBtn" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm py-3.5 rounded-xl transition-all shadow-md shadow-emerald-600/20 flex items-center justify-center gap-2 cursor-pointer">
                        <span>Update Password & Save</span>
                        <span class="material-symbols-outlined text-[18px]">lock_reset</span>
                    </button>
                </div>
            </form>

        <!-- STEP 3: SUCCESS STATE -->
        <?php elseif ($step === 3): ?>
            <div class="text-center space-y-4 py-3">
                <div class="w-16 h-16 bg-emerald-50 text-emerald-600 border border-emerald-200 rounded-full flex items-center justify-center mx-auto shadow-xs animate-in zoom-in duration-300">
                    <span class="material-symbols-outlined text-3xl">check_circle</span>
                </div>
                <div>
                    <h3 class="text-lg font-black text-slate-900">Password Updated Successfully</h3>
                    <p class="text-xs text-slate-500 mt-1 max-w-xs mx-auto">
                        Your account password has been updated and your session is verified. You do not need to re-enter your password.
                    </p>
                    <div class="mt-2 inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-slate-100 text-slate-700 text-xs font-bold border border-slate-200">
                        <span class="material-symbols-outlined text-xs text-emerald-600">verified_user</span>
                        <span><?= htmlspecialchars($roleBadgeName ?? 'Verified Account') ?>: <?= htmlspecialchars($authenticatedUser['name'] ?? $emailTarget) ?></span>
                    </div>
                </div>

                <div class="pt-2 flex flex-col gap-2.5">
                    <a id="proceedBtn" href="<?= htmlspecialchars($redirectUrl ?? '/trainer/dashboard.php') ?>" class="w-full <?= $btnStyle ?? 'bg-[#FE5E04] hover:bg-[#E04E00]' ?> text-white font-bold text-xs py-3.5 rounded-xl transition-all shadow-md flex items-center justify-center gap-2">
                        <span><?= htmlspecialchars($dashboardBtnText ?? 'Proceed to Dashboard') ?></span>
                        <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                    </a>
                    <p class="text-[11px] text-slate-400 font-medium">
                        Redirecting automatically in <span id="redirectTimer" class="font-bold text-slate-700">3</span> seconds...
                    </p>
                </div>
            </div>
            <script>
                // Auto-redirect to verified dashboard after 3 seconds
                let timeLeft = 3;
                const timerEl = document.getElementById('redirectTimer');
                const targetUrl = <?= json_encode($redirectUrl ?? '/trainer/dashboard.php') ?>;
                const interval = setInterval(() => {
                    timeLeft--;
                    if (timerEl) timerEl.textContent = timeLeft;
                    if (timeLeft <= 0) {
                        clearInterval(interval);
                        window.location.href = targetUrl;
                    }
                }, 1000);
            </script>
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

    <!-- UNIVERSAL LOADING SCREEN OVERLAY (Mobile & Desktop Responsive) -->
    <div id="authLoadingOverlay" class="hidden fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex flex-col items-center justify-center p-4">
        <div class="bg-white rounded-3xl p-6 sm:p-8 shadow-2xl flex flex-col items-center max-w-xs w-full text-center border border-slate-100 animate-in fade-in zoom-in-95 duration-150">
            <div class="relative mb-4">
                <div class="w-14 h-14 border-4 border-orange-100 border-t-[#FE5E04] rounded-full animate-spin"></div>
                <div class="absolute inset-0 flex items-center justify-center">
                    <span class="material-symbols-outlined text-[#FE5E04] text-xl">lock_reset</span>
                </div>
            </div>
            <h4 id="authLoadingTitle" class="text-sm sm:text-base font-black text-slate-900">Processing...</h4>
            <p id="authLoadingSubtitle" class="text-xs text-slate-500 mt-1">Please wait while we verify your request.</p>
        </div>
    </div>

    <script>
    function showAuthLoading(title, subtitle) {
        const overlay = document.getElementById('authLoadingOverlay');
        if (overlay) {
            if (title) document.getElementById('authLoadingTitle').textContent = title;
            if (subtitle) document.getElementById('authLoadingSubtitle').textContent = subtitle;
            overlay.classList.remove('hidden');
            overlay.classList.add('flex');
            document.body.classList.add('overflow-hidden');
        }
    }

    function hideAuthLoading() {
        const overlay = document.getElementById('authLoadingOverlay');
        if (overlay) {
            overlay.classList.remove('flex');
            overlay.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }
    }

    function showError(msg) {
        const errBox = document.getElementById('dynamicErrorBox');
        const errMsg = document.getElementById('dynamicErrorMsg');
        if (errBox && errMsg) {
            errMsg.textContent = msg;
            errBox.classList.remove('hidden');
            errBox.classList.add('flex');
        }
    }

    function clearError() {
        const errBox = document.getElementById('dynamicErrorBox');
        if (errBox) {
            errBox.classList.remove('flex');
            errBox.classList.add('hidden');
        }
    }

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

    // Step 2 Interactive OTP Verification
    async function verifyOtpCode() {
        clearError();
        const otpInput = document.getElementById('otpInput');
        const emailInput = document.getElementById('emailInput');
        const code = otpInput ? otpInput.value.trim() : '';
        const email = emailInput ? emailInput.value.trim() : '';

        if (!code || code.length < 4) {
            showError("Please enter the 6-digit verification code.");
            if (otpInput) otpInput.focus();
            return;
        }

        showAuthLoading('Verifying Code...', 'Checking your 6-digit one-time code.');

        try {
            const formData = new FormData();
            formData.append('action', 'verify_otp');
            formData.append('email', email);
            formData.append('code', code);
            formData.append('ajax', '1');

            const resp = await fetch('/forgot-password.php', {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: formData
            });

            const data = await resp.json();
            hideAuthLoading();

            if (data && data.success) {
                // Advance UI to password fields
                otpInput.setAttribute('readonly', 'readonly');
                otpInput.className = "w-full bg-emerald-50/40 border border-emerald-300 rounded-xl p-3 text-center text-2xl tracking-[0.35em] font-mono font-black text-emerald-800 outline-none";
                
                const statusBadge = document.getElementById('otpStatusBadge');
                if (statusBadge) statusBadge.classList.remove('hidden');

                const verifyBtnBox = document.getElementById('verifyOtpBtnContainer');
                if (verifyBtnBox) verifyBtnBox.classList.add('hidden');

                const codeCard = document.getElementById('codeDispatchedCard');
                if (codeCard) codeCard.classList.add('hidden');

                const successBox = document.getElementById('dynamicSuccessBox');
                const successMsg = document.getElementById('dynamicSuccessMsg');
                if (successBox && successMsg) {
                    successMsg.textContent = "✓ Code verified! Please enter and confirm your new password.";
                    successBox.classList.remove('hidden');
                    successBox.classList.add('flex');
                }

                // Update Step badges in header
                const stepNum2 = document.getElementById('stepNum2');
                if (stepNum2) {
                    stepNum2.textContent = '✓';
                    stepNum2.className = 'w-5 h-5 rounded-full flex items-center justify-center text-[10px] bg-emerald-600 text-white';
                }
                const stepBadge2 = document.getElementById('stepBadge2');
                if (stepBadge2) stepBadge2.className = 'flex items-center gap-1 text-emerald-600';

                const stepNum3 = document.getElementById('stepNum3');
                if (stepNum3) stepNum3.className = 'w-5 h-5 rounded-full flex items-center justify-center text-[10px] bg-[#FE5E04] text-white';
                const stepBadge3 = document.getElementById('stepBadge3');
                if (stepBadge3) stepBadge3.className = 'flex items-center gap-1 text-[#FE5E04]';

                // Reveal password inputs
                const passContainer = document.getElementById('passwordFieldsContainer');
                if (passContainer) passContainer.classList.remove('hidden');

                const formAction = document.getElementById('formAction');
                if (formAction) formAction.value = 'reset_password';

                const newPassInput = document.getElementById('newPassInput');
                const confirmPassInput = document.getElementById('confirmPassInput');
                if (newPassInput) {
                    newPassInput.setAttribute('required', 'required');
                    setTimeout(() => newPassInput.focus(), 150);
                }
                if (confirmPassInput) confirmPassInput.setAttribute('required', 'required');

            } else {
                showError((data && data.message) ? data.message : "Invalid or expired verification code. Please check your code.");
                if (otpInput) otpInput.focus();
            }
        } catch (err) {
            hideAuthLoading();
            // Fallback to standard form submit
            const resetForm = document.getElementById('resetForm');
            if (resetForm) {
                const formAction = document.getElementById('formAction');
                if (formAction) formAction.value = 'verify_otp';
                showAuthLoading('Verifying Code...', 'Validating code on server.');
                resetForm.submit();
            }
        }
    }

    // Handle final reset password submission
    function handleResetFormSubmit(e) {
        clearError();
        const formAction = document.getElementById('formAction');
        if (formAction && formAction.value === 'verify_otp') {
            e.preventDefault();
            verifyOtpCode();
            return false;
        }

        const newPass = document.getElementById('newPassInput')?.value || '';
        const confirmPass = document.getElementById('confirmPassInput')?.value || '';

        if (!newPass || newPass.length < 6) {
            e.preventDefault();
            showError("Password must be at least 6 characters long.");
            document.getElementById('newPassInput')?.focus();
            return false;
        }

        if (newPass !== confirmPass) {
            e.preventDefault();
            showError("The new passwords do not match. Please re-enter.");
            document.getElementById('confirmPassInput')?.focus();
            return false;
        }

        showAuthLoading('Securing Account...', 'Encrypting new password and establishing your session.');
        return true;
    }

    // Resend Code handler
    async function resendOtpCode() {
        clearError();
        const email = document.getElementById('emailInput')?.value || '';
        if (!email) return;

        showAuthLoading('Resending Code...', 'Dispatching a fresh 6-digit verification code to your email.');
        
        // Submit standard email request
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/forgot-password.php';
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'email';
        input.value = email;
        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
    }

    // Auto-focus OTP on load
    window.addEventListener('DOMContentLoaded', () => {
        const otp = document.getElementById('otpInput');
        if (otp && !otp.readOnly) otp.focus();
    });
    </script>
</body>
</html>

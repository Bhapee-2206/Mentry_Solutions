<?php
// contact.php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';

$sent = false;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();

    // 1. Honeypot check (bots fill in hidden field)
    if (!empty($_POST['website'])) {
        // Pretend to succeed to trap bots
        $sent = true;
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');

        // 2. Input validation
        $nameErr = validateNameInput($name, 'Your Name');
        $emailErr = validateEmailInput($email);

        if ($nameErr) {
            $error = $nameErr;
        } elseif ($emailErr) {
            $error = $emailErr;
        } elseif (empty($message) || strlen($message) < 10) {
            $error = "Please provide a detailed message (minimum 10 characters).";
        } elseif (strlen($message) > 4000) {
            $error = "Message is too long (maximum 4000 characters).";
        } else {
            // 3. IP and Email Rate Limiting (max 5 per 15 min per IP/email)
            $clientIp = getClientIp();
            $rateKey = 'contact_rate_' . md5($clientIp . '|' . $email);
            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }
            $history = $_SESSION[$rateKey] ?? [];
            $now = time();
            $history = array_filter($history, function($ts) use ($now) { return ($now - $ts) < 900; });
            if (count($history) >= 5) {
                $error = "You have submitted multiple inquiries recently. Please wait a few minutes before submitting another message.";
            } else {
                $history[] = $now;
                $_SESSION[$rateKey] = $history;

                // 4. Duplicate submission check (prevent double submit within 15 min)
                $inquiryCol = getCollection("ContactInquiry");
                $isDuplicate = false;
                if ($inquiryCol) {
                    $recentDuplicate = $inquiryCol->findOne([
                        'email' => $email,
                        'message' => $message,
                        'createdAt' => ['$gte' => new MongoDB\BSON\UTCDateTime((time() - 900) * 1000)]
                    ]);
                    if ($recentDuplicate) {
                        $isDuplicate = true;
                    }
                }

                if (!$isDuplicate && $inquiryCol) {
                    $inquiryCol->insertOne([
                        'name' => cleanString($name, 100),
                        'email' => $email,
                        'subject' => cleanString($subject ?: 'General Inquiry', 150),
                        'message' => cleanString($message, 4000),
                        'status' => 'NEW',
                        'clientIp' => $clientIp,
                        'createdAt' => new MongoDB\BSON\UTCDateTime()
                    ]);

                    // Dispatch real-time Admin Notification
                    try {
                        require_once __DIR__ . '/includes/notifications.php';
                        notifyAdmin(
                            'INQUIRY',
                            "New Contact Inquiry: " . cleanString($name, 50),
                            cleanString($name, 50) . " ({$email}) sent message: " . (strlen($message) > 100 ? substr($message, 0, 100) . '...' : $message),
                            "/admin/notifications.php"
                        );
                    } catch (\Throwable $ne) {
                        logAppError('contact_notify_admin', $ne, ['email' => $email]);
                    }
                }

                $sent = true;
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="bg-slate-50/50 py-16 md:py-24">
    <div class="w-full max-w-full px-4 sm:px-6 md:px-8 lg:px-10 xl:px-12 2xl:px-16 space-y-12">
        <div class="text-center space-y-3">
            <span class="text-blue-600 font-bold text-xs uppercase tracking-wider bg-blue-50 px-3.5 py-1.5 rounded-full border border-blue-100">
                Get In Touch
            </span>
            <h1 class="text-3xl sm:text-5xl font-black text-slate-950 tracking-tight">
                Contact Mentry Solutions
            </h1>
            <p class="text-slate-600 text-sm md:text-base max-w-xl mx-auto leading-relaxed">
                Have questions about college partnerships, trainer verification, or billing? Our team is here to assist.
            </p>
        </div>

        <div class="grid md:grid-cols-3 gap-8">
            <div class="space-y-6">
                <div class="bg-white p-6 rounded-3xl border border-slate-200/90 shadow-card space-y-2">
                    <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center mb-3">
                        <span class="material-symbols-outlined text-xl">mail</span>
                    </div>
                    <h3 class="font-bold text-sm text-slate-900">Official Support Email</h3>
                    <a href="mailto:mentry.training@gmail.com" class="text-xs text-blue-600 font-semibold underline block">
                        mentry.training@gmail.com
                    </a>
                </div>

                <div class="bg-white p-6 rounded-3xl border border-slate-200/90 shadow-card space-y-2">
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center mb-3">
                        <span class="material-symbols-outlined text-xl">location_on</span>
                    </div>
                    <h3 class="font-bold text-sm text-slate-900">Headquarters</h3>
                    <p class="text-xs text-slate-500 leading-relaxed">
                        Bangalore, Karnataka, India • Serving Pan-India Colleges
                    </p>
                </div>
            </div>

            <div class="md:col-span-2">
                <?php if ($sent): ?>
                    <div class="bg-emerald-50 border border-emerald-200 rounded-3xl p-8 text-center space-y-3">
                        <span class="material-symbols-outlined text-4xl text-emerald-600">check_circle</span>
                        <h3 class="font-bold text-lg text-emerald-950">Message Received!</h3>
                        <p class="text-xs text-emerald-800">Our coordinator will respond via email within 2-4 hours.</p>
                    </div>
                <?php else: ?>
                    <?php if ($error): ?>
                        <div class="mb-4 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-semibold flex items-center gap-2">
                            <span class="material-symbols-outlined text-rose-600 text-sm">error</span>
                            <span><?= htmlspecialchars($error) ?></span>
                        </div>
                    <?php endif; ?>
                    <form method="POST" action="/contact.php" class="bg-white p-8 rounded-3xl border border-slate-200/90 shadow-card space-y-4">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
                        <!-- Honeypot anti-spam field -->
                        <div style="position: absolute; left: -9999px; top: -9999px;" aria-hidden="true">
                            <input type="text" name="website" tabindex="-1" autocomplete="off">
                        </div>
                        <div class="grid sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Your Name *</label>
                                <input type="text" name="name" required class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Email Address *</label>
                                <input type="email" name="email" required class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Subject</label>
                            <input type="text" name="subject" placeholder="Trainer onboarding / College partnership inquiry" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Message</label>
                            <textarea name="message" rows="4" required placeholder="How can we assist your training objectives?" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-blue-500/20 outline-none"></textarea>
                        </div>

                        <button type="submit" class="w-full bg-slate-900 hover:bg-blue-600 text-white font-bold text-xs py-3.5 rounded-xl transition-all shadow-md">
                            Send Message
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

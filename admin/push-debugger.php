<?php
// admin/push-debugger.php - Notification Diagnostics (In-App & Transactional Email Architecture)
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/mailer.php';

requireAdminOrStaff();

$pageTitle = 'Notification Diagnostics (In-App & Email)';
$currentUser = getCurrentUser();

$notifCol = getCollection("Notification");
$emailLogCol = getCollection("EmailLog");
$confCol = getCollection("TrainerConfirmation");
$userCol = getCollection("User");

// Test email dispatch action
$testEmailResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_test_email') {
    requireCsrfToken();
    $targetEmail = trim($_POST['target_email'] ?? ($currentUser['email'] ?? ''));
    if (!empty($targetEmail) && filter_var($targetEmail, FILTER_VALIDATE_EMAIL)) {
        try {
            $mailer = new MentryMailer();
            $testSubject = "Mentry Solutions — Diagnostics Test Email [" . date('d M Y H:i:s') . "]";
            $testBody = "<p>Hello " . htmlspecialchars($currentUser['name'] ?? 'Admin') . ",</p>"
                      . "<p>This is a technical diagnostic verification email confirming that the Mentry Solutions transactional SMTP email subsystem is operating normally.</p>"
                      . "<p><strong>Timestamp:</strong> " . date('r') . "<br>"
                      . "<strong>Server Timezone:</strong> Asia/Kolkata (IST)</p>"
                      . "<p>Regards,<br><strong>Mentry Solutions Platform Engineering</strong></p>";
            $res = $mailer->send($targetEmail, $currentUser['name'] ?? 'Admin', $testSubject, $testBody, '', ['type' => 'TEST_EMAIL']);
            if ($res['success']) {
                $_SESSION['flash_success'] = "Test email dispatched successfully to " . htmlspecialchars($targetEmail);
            } else {
                $_SESSION['flash_error'] = "Test email dispatch failed: " . htmlspecialchars($res['message'] ?? 'Unknown error');
            }
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = "Test email failed: " . htmlspecialchars($e->getMessage());
        }
    } else {
        $_SESSION['flash_error'] = "Invalid target email address.";
    }
    header("Location: /admin/push-debugger.php");
    exit();
}

// Diagnostic Metrics
$totalInAppCount = $notifCol ? $notifCol->countDocuments([]) : 0;
$unreadInAppCount = $notifCol ? $notifCol->countDocuments(['read' => false]) : 0;

$totalEmailsLogged = $emailLogCol ? $emailLogCol->countDocuments([]) : 0;
$totalEmailsSent = $emailLogCol ? $emailLogCol->countDocuments(['status' => 'SENT']) : 0;
$totalEmailsFailed = $emailLogCol ? $emailLogCol->countDocuments(['status' => 'FAILED']) : 0;

$totalWorkOrders = $confCol ? $confCol->countDocuments([]) : 0;
$sentWorkOrders = $confCol ? $confCol->countDocuments(['status' => 'SENT']) : 0;
$draftWorkOrders = $confCol ? $confCol->countDocuments(['status' => 'DRAFT']) : 0;

$latestEmail = $emailLogCol ? $emailLogCol->findOne([], ['sort' => ['timestamp' => -1]]) : null;

// Safe SMTP status without exposing passwords
$smtpHost = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
$smtpPort = getenv('SMTP_PORT') ?: '465 / 587';
$smtpFrom = getenv('SMTP_FROM_EMAIL') ?: 'mentry.training@gmail.com';
$smtpAuthSet = !empty(getenv('SMTP_PASSWORD')) || !empty(getenv('SMTP_PASS'));

// Recent Email Logs
$recentEmailLogs = $emailLogCol ? $emailLogCol->find([], ['sort' => ['timestamp' => -1], 'limit' => 10])->toArray() : [];

// Recent In-App Notifications
$recentNotifications = $notifCol ? $notifCol->find([], ['sort' => ['createdAt' => -1], 'limit' => 10])->toArray() : [];

require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="space-y-8 max-w-6xl mx-auto pb-16">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 mb-1">
                <a href="/admin/dashboard.php" class="hover:text-slate-800">Command Center</a>
                <span>/</span>
                <span class="text-slate-800">Notification Diagnostics</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight flex items-center gap-3">
                <span class="p-2 rounded-2xl bg-blue-50 text-blue-600 inline-flex items-center justify-center">
                    <span class="material-symbols-outlined text-2xl">notifications_active</span>
                </span>
                Notification Diagnostics (In-App & Email)
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-1">Real-time status of database in-app notifications, SMTP email delivery, and work order audit records.</p>
        </div>

        <a href="/admin/dashboard.php" class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 rounded-xl text-xs font-bold transition shadow-xs">
            <span class="material-symbols-outlined text-base">arrow_back</span>
            Dashboard
        </a>
    </div>

    <!-- Alert / Flash Messages -->
    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-semibold flex items-center justify-between shadow-xs">
            <div class="flex items-center gap-2.5">
                <span class="material-symbols-outlined text-emerald-600">check_circle</span>
                <span><?= htmlspecialchars($_SESSION['flash_success']) ?></span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-emerald-600 hover:text-emerald-800"><span class="material-symbols-outlined text-sm">close</span></button>
        </div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-semibold flex items-center justify-between shadow-xs">
            <div class="flex items-center gap-2.5">
                <span class="material-symbols-outlined text-rose-600">error</span>
                <span><?= htmlspecialchars($_SESSION['flash_error']) ?></span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-rose-600 hover:text-rose-800"><span class="material-symbols-outlined text-sm">close</span></button>
        </div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <!-- Architecture Migration Banner -->
    <div class="bg-gradient-to-r from-slate-900 to-slate-800 text-white rounded-3xl p-6 sm:p-8 shadow-card space-y-3">
        <div class="flex items-center gap-3">
            <span class="w-10 h-10 rounded-2xl bg-emerald-500/20 border border-emerald-500/30 flex items-center justify-center text-emerald-400">
                <span class="material-symbols-outlined text-2xl">verified</span>
            </span>
            <div>
                <h3 class="text-base font-extrabold text-white">Streamlined Notification Architecture Active</h3>
                <p class="text-xs text-slate-300">Push notification systems (Web Push & Native FCM) have been decommissioned. In-App Notifications and Transactional SMTP Email are the primary communication channels.</p>
            </div>
        </div>
    </div>

    <!-- Diagnostic Health Status Grid -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <!-- In-App Status -->
        <div class="bg-white rounded-3xl border border-slate-200/90 p-6 shadow-card space-y-2">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-extrabold uppercase text-slate-400 tracking-wider">In-App Notifications</span>
                <span class="bg-emerald-100 text-emerald-800 text-[10px] font-black px-2 py-0.5 rounded-full uppercase">Active</span>
            </div>
            <div class="text-2xl font-black text-slate-900"><?= number_format($totalInAppCount) ?></div>
            <p class="text-[11px] text-slate-500"><?= number_format($unreadInAppCount) ?> unread across all users</p>
        </div>

        <!-- Email Configuration -->
        <div class="bg-white rounded-3xl border border-slate-200/90 p-6 shadow-card space-y-2">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-extrabold uppercase text-slate-400 tracking-wider">SMTP Email Engine</span>
                <span class="bg-emerald-100 text-emerald-800 text-[10px] font-black px-2 py-0.5 rounded-full uppercase">Configured</span>
            </div>
            <div class="text-lg font-black text-slate-900 truncate" title="<?= htmlspecialchars($smtpHost) ?>"><?= htmlspecialchars($smtpHost) ?></div>
            <p class="text-[11px] text-slate-500">From: <?= htmlspecialchars($smtpFrom) ?></p>
        </div>

        <!-- Email Delivery Success -->
        <div class="bg-white rounded-3xl border border-slate-200/90 p-6 shadow-card space-y-2">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-extrabold uppercase text-slate-400 tracking-wider">Email Delivery</span>
                <?php if ($totalEmailsFailed > 0): ?>
                    <span class="bg-amber-100 text-amber-800 text-[10px] font-black px-2 py-0.5 rounded-full uppercase">Notice</span>
                <?php else: ?>
                    <span class="bg-emerald-100 text-emerald-800 text-[10px] font-black px-2 py-0.5 rounded-full uppercase">100% OK</span>
                <?php endif; ?>
            </div>
            <div class="text-2xl font-black text-emerald-700"><?= number_format($totalEmailsSent) ?> <span class="text-xs font-normal text-slate-400">sent</span></div>
            <p class="text-[11px] text-slate-500"><?= number_format($totalEmailsFailed) ?> failed / <?= number_format($totalEmailsLogged) ?> total events</p>
        </div>

        <!-- Work Orders -->
        <div class="bg-white rounded-3xl border border-slate-200/90 p-6 shadow-card space-y-2">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-extrabold uppercase text-slate-400 tracking-wider">Work Order System</span>
                <span class="bg-purple-100 text-purple-800 text-[10px] font-black px-2 py-0.5 rounded-full uppercase">Operational</span>
            </div>
            <div class="text-2xl font-black text-purple-700"><?= number_format($sentWorkOrders) ?> <span class="text-xs font-normal text-slate-400">sent</span></div>
            <p class="text-[11px] text-slate-500"><?= number_format($draftWorkOrders) ?> pending review / <?= number_format($totalWorkOrders) ?> total</p>
        </div>
    </div>

    <!-- Quick Test Dispatcher -->
    <div class="bg-white rounded-3xl border border-slate-200/90 p-6 sm:p-8 shadow-card space-y-4">
        <div>
            <h3 class="text-base font-black text-slate-900 flex items-center gap-2">
                <span class="material-symbols-outlined text-[#FE5E04]">send</span>
                Test Transactional Email Dispatcher
            </h3>
            <p class="text-xs text-slate-500">Send an immediate test message through the configured SMTP pipeline to verify deliverability.</p>
        </div>

        <form method="POST" class="flex flex-col sm:flex-row items-center gap-3">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
            <input type="hidden" name="action" value="send_test_email">
            <input type="email" name="target_email" value="<?= htmlspecialchars($currentUser['email'] ?? 'admin@mentry.co') ?>" required
                   class="w-full sm:max-w-md px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#FE5E04]"
                   placeholder="Enter destination email address">
            <button type="submit" class="w-full sm:w-auto px-5 py-2.5 bg-[#FE5E04] hover:bg-[#e05202] text-white rounded-xl text-xs font-black transition shadow-xs flex items-center justify-center gap-2">
                <span class="material-symbols-outlined text-sm">mail</span>
                Send Test Email
            </button>
        </form>
    </div>

    <!-- Recent Email Transport Logs -->
    <div class="bg-white rounded-3xl border border-slate-200/90 p-6 sm:p-8 shadow-card space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <div>
                <h3 class="text-base font-black text-slate-900 flex items-center gap-2">
                    <span class="material-symbols-outlined text-blue-600">outgoing_mail</span>
                    Recent Email Transport Logs
                </h3>
                <p class="text-xs text-slate-500">Chronological history of recent transactional emails processed by the server.</p>
            </div>
            <span class="text-xs font-bold text-slate-400">Total: <?= number_format($totalEmailsLogged) ?></span>
        </div>

        <?php if (empty($recentEmailLogs)): ?>
            <p class="text-xs text-slate-400 py-4 italic">No email events recorded yet.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-100 text-slate-400 uppercase font-black tracking-wider text-[10px]">
                            <th class="py-2.5 px-3">Status</th>
                            <th class="py-2.5 px-3">Recipient</th>
                            <th class="py-2.5 px-3">Subject</th>
                            <th class="py-2.5 px-3">Port</th>
                            <th class="py-2.5 px-3">Timestamp</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium">
                        <?php foreach ($recentEmailLogs as $log): 
                            $st = $log['status'] ?? 'SENT';
                        ?>
                            <tr class="hover:bg-slate-50/60">
                                <td class="py-3 px-3">
                                    <?php if ($st === 'SENT'): ?>
                                        <span class="bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded-full text-[10px] font-black uppercase">Sent</span>
                                    <?php else: ?>
                                        <span class="bg-rose-100 text-rose-800 px-2 py-0.5 rounded-full text-[10px] font-black uppercase">Failed</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-3 font-mono text-[11px] text-slate-700">
                                    <?= htmlspecialchars($log['to'] ?? '') ?>
                                </td>
                                <td class="py-3 px-3 text-slate-900 max-w-xs truncate">
                                    <?= htmlspecialchars($log['subject'] ?? '') ?>
                                </td>
                                <td class="py-3 px-3 font-mono text-[11px] text-slate-500">
                                    <?= htmlspecialchars($log['portUsed'] ?? '465') ?>
                                </td>
                                <td class="py-3 px-3 text-slate-400 font-mono text-[11px]">
                                    <?= formatDate($log['timestamp'] ?? null) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Recent In-App Notification Stream -->
    <div class="bg-white rounded-3xl border border-slate-200/90 p-6 sm:p-8 shadow-card space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <div>
                <h3 class="text-base font-black text-slate-900 flex items-center gap-2">
                    <span class="material-symbols-outlined text-amber-500">notifications</span>
                    Recent In-App Notification Stream
                </h3>
                <p class="text-xs text-slate-500">Permanent database records stored in MongoDB Notification collection.</p>
            </div>
            <span class="text-xs font-bold text-slate-400">Total: <?= number_format($totalInAppCount) ?></span>
        </div>

        <?php if (empty($recentNotifications)): ?>
            <p class="text-xs text-slate-400 py-4 italic">No in-app notifications generated yet.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-100 text-slate-400 uppercase font-black tracking-wider text-[10px]">
                            <th class="py-2.5 px-3">Type</th>
                            <th class="py-2.5 px-3">Title</th>
                            <th class="py-2.5 px-3">Message</th>
                            <th class="py-2.5 px-3">Created</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium">
                        <?php foreach ($recentNotifications as $notif): ?>
                            <tr class="hover:bg-slate-50/60">
                                <td class="py-3 px-3 font-bold text-slate-700">
                                    <span class="bg-slate-100 text-slate-800 px-2 py-0.5 rounded text-[10px] font-extrabold uppercase">
                                        <?= htmlspecialchars($notif['type'] ?? 'GENERAL') ?>
                                    </span>
                                </td>
                                <td class="py-3 px-3 font-bold text-slate-900 max-w-xs truncate">
                                    <?= htmlspecialchars($notif['title'] ?? '') ?>
                                </td>
                                <td class="py-3 px-3 text-slate-600 max-w-md truncate">
                                    <?= htmlspecialchars($notif['message'] ?? '') ?>
                                </td>
                                <td class="py-3 px-3 text-slate-400 font-mono text-[11px]">
                                    <?= formatDate($notif['createdAt'] ?? null) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

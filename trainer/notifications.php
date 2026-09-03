<?php
// trainer/notifications.php
$pageTitle = "Notifications";
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/includes/sidebar.php';

$notifCol = getCollection("Notification");
$notifications = $notifCol ? $notifCol->find(
    ['userId' => $user['id']],
    ['sort' => ['createdAt' => -1], 'limit' => 20]
)->toArray() : [];
?>

<div class="max-w-4xl mx-auto space-y-6">
    <div>
        <h1 class="text-2xl font-black text-slate-900 tracking-tight">Notifications & Match Alerts</h1>
        <p class="text-xs text-slate-500 mt-0.5">Stay informed about new college requirements, shortlist updates, and selection announcements.</p>
    </div>

    <div class="bg-white rounded-3xl border border-slate-200/90 shadow-card divide-y divide-slate-100 overflow-hidden">
        <?php if (empty($notifications)): ?>
            <div class="p-12 text-center text-xs text-slate-400">
                You're all caught up! No unread notifications.
            </div>
        <?php else: ?>
            <?php foreach ($notifications as $n): ?>
                <div class="p-5 flex items-start gap-4 hover:bg-slate-50/60 transition-colors">
                    <div class="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-lg">campaign</span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h4 class="font-bold text-xs text-slate-900"><?= htmlspecialchars($n['title'] ?? 'Notification') ?></h4>
                        <p class="text-xs text-slate-600 mt-0.5 leading-relaxed"><?= htmlspecialchars($n['message'] ?? '') ?></p>
                        <span class="text-[10px] text-slate-400 mt-1 block"><?= formatRelativeTime($n['createdAt'] ?? null) ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

</main>
</div>
</body>
</html>

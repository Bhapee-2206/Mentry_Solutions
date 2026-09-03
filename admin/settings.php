<?php
// admin/settings.php - Operational Audit & Settings
$pageTitle = "Settings & Audit";
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/includes/sidebar.php';

$auditCol = getCollection("AuditLog");
$logs = $auditCol ? $auditCol->find([], ['limit' => 20, 'sort' => ['createdAt' => -1]])->toArray() : [];
?>

<div class="space-y-8">
    <div>
        <h1 class="text-2xl md:text-3xl font-black text-slate-900 tracking-tight">Platform Settings & Audit Stream</h1>
        <p class="text-xs md:text-sm text-slate-500 mt-0.5">Review operational security logs, MongoDB database stats, and environment configuration.</p>
    </div>

    <!-- System Stat Strip -->
    <div class="grid sm:grid-cols-3 gap-4">
        <div class="bg-white p-5 rounded-2xl border border-slate-200/90 shadow-card">
            <span class="text-xs text-slate-400 font-bold uppercase block">Database Engine</span>
            <p class="font-extrabold text-sm text-slate-900 mt-1">MongoDB Atlas (Cluster 0)</p>
            <span class="text-[11px] text-emerald-600 font-semibold mt-0.5 block">● Connected via PHP Driver</span>
        </div>
        <div class="bg-white p-5 rounded-2xl border border-slate-200/90 shadow-card">
            <span class="text-xs text-slate-400 font-bold uppercase block">PHP Server Runtime</span>
            <p class="font-extrabold text-sm text-slate-900 mt-1">PHP <?= phpversion() ?></p>
            <span class="text-[11px] text-blue-600 font-semibold mt-0.5 block">XAMPP / Apache Compatible</span>
        </div>
        <div class="bg-white p-5 rounded-2xl border border-slate-200/90 shadow-card">
            <span class="text-xs text-slate-400 font-bold uppercase block">Support Routing</span>
            <p class="font-extrabold text-sm text-slate-900 mt-1">mentry.training@gmail.com</p>
            <span class="text-[11px] text-slate-400 font-semibold mt-0.5 block">Active Business Inbox</span>
        </div>
    </div>

    <!-- Audit Logs -->
    <div class="bg-white rounded-3xl border border-slate-200/90 shadow-card p-6 space-y-4">
        <h3 class="font-bold text-base text-slate-900">Recent Operational Activity</h3>
        <?php if (empty($logs)): ?>
            <div class="p-8 text-center text-xs text-slate-400">
                Audit logs are clean. System running normally.
            </div>
        <?php else: ?>
            <div class="divide-y divide-slate-100">
                <?php foreach ($logs as $lg): ?>
                    <div class="py-3 flex items-center justify-between text-xs">
                        <div>
                            <span class="font-bold text-slate-900"><?= htmlspecialchars($lg['action'] ?? 'SYSTEM_EVENT') ?></span>
                            <p class="text-[11px] text-slate-500"><?= htmlspecialchars($lg['details'] ?? '') ?></p>
                        </div>
                        <span class="text-slate-400"><?= formatRelativeTime($lg['createdAt'] ?? null) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

</main>
</div>
</body>
</html>

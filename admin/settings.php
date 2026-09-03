<?php
// admin/settings.php - Operational Audit & Settings
$pageTitle = "Settings & Audit";
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/maintenance.php';
require_once __DIR__ . '/includes/sidebar.php';

$auditCol = getCollection("AuditLog");
$logs = $auditCol ? $auditCol->find([], ['limit' => 20, 'sort' => ['createdAt' => -1]])->toArray() : [];

$maintConfig = getMaintenanceConfig();
$isMaintActive = !empty($maintConfig['maintenance_mode']);
?>

<div class="space-y-6 max-w-full overflow-hidden">
    <div>
        <h1 class="text-xl sm:text-2xl md:text-3xl font-black text-slate-900 tracking-tight">Platform Settings & Audit Stream</h1>
        <p class="text-xs md:text-sm text-slate-500 mt-0.5">Control live website status, review operational security logs, and environment configuration.</p>
    </div>

    <!-- Website Status & Maintenance Mode Card -->
    <div class="bg-white p-5 sm:p-6 rounded-3xl border border-slate-200/90 shadow-card space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-100">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-[#FE5E04]">toggle_on</span>
                    <h3 class="font-extrabold text-sm sm:text-base text-slate-900">Website Live / Maintenance Mode Control</h3>
                </div>
                <p class="text-xs text-slate-500">
                    When Work in Progress mode is ACTIVE, public visitors see the maintenance page while authenticated Admins and Staff can continue operating.
                </p>
            </div>

            <form action="/actions/toggle-maintenance.php" method="POST" class="shrink-0" onsubmit="return confirm('<?= $isMaintActive ? "Turn OFF Maintenance Mode and make the website live for everyone?" : "Turn ON Maintenance Mode? Public visitors will be redirected to Work in Progress." ?>');">
                <input type="hidden" name="active" value="<?= $isMaintActive ? '0' : '1' ?>">
                <input type="hidden" name="return_url" value="/admin/settings.php">
                
                <?php if ($isMaintActive): ?>
                    <button type="submit" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-[#FE5E04] hover:bg-[#E04E00] text-white text-xs font-bold px-5 py-3 rounded-2xl transition-all shadow-md shadow-orange-500/20 cursor-pointer">
                        <span class="w-2.5 h-2.5 rounded-full bg-white animate-ping"></span>
                        <span>Mode: Work in Progress (ACTIVE) — Click to Make Live</span>
                    </button>
                <?php else: ?>
                    <button type="submit" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-emerald-50 hover:bg-emerald-100 border border-emerald-300 text-emerald-800 text-xs font-bold px-5 py-3 rounded-2xl transition-all shadow-2xs cursor-pointer">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                        <span>Site Status: LIVE (Normal) — Click to Enable Maintenance</span>
                    </button>
                <?php endif; ?>
            </form>
        </div>

        <div class="flex flex-wrap items-center gap-3 text-xs text-slate-500">
            <span class="font-bold text-slate-700">Preview Page:</span>
            <a href="/maintenance.php" target="_blank" class="text-[#FE5E04] font-semibold hover:underline inline-flex items-center gap-1">
                <span>View Maintenance Light Page</span>
                <span class="material-symbols-outlined text-[14px]">open_in_new</span>
            </a>
        </div>
    </div>

    <!-- System Stat Strip -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <div class="bg-white p-5 rounded-2xl border border-slate-200/90 shadow-card">
            <span class="text-xs text-slate-400 font-bold uppercase block">Database Engine</span>
            <p class="font-extrabold text-sm text-slate-900 mt-1 truncate">MongoDB Atlas (Cluster 0)</p>
            <span class="text-[11px] text-emerald-600 font-semibold mt-0.5 block">● Connected via PHP Driver</span>
        </div>
        <div class="bg-white p-5 rounded-2xl border border-slate-200/90 shadow-card">
            <span class="text-xs text-slate-400 font-bold uppercase block">PHP Server Runtime</span>
            <p class="font-extrabold text-sm text-slate-900 mt-1">PHP <?= phpversion() ?></p>
            <span class="text-[11px] text-blue-600 font-semibold mt-0.5 block">XAMPP / Apache Compatible</span>
        </div>
        <div class="bg-white p-5 rounded-2xl border border-slate-200/90 shadow-card sm:col-span-2 lg:col-span-1">
            <span class="text-xs text-slate-400 font-bold uppercase block">Support Routing</span>
            <p class="font-extrabold text-sm text-slate-900 mt-1 truncate">mentry.training@gmail.com</p>
            <span class="text-[11px] text-slate-400 font-semibold mt-0.5 block">Active Business Inbox</span>
        </div>
    </div>

    <!-- Audit Logs -->
    <div class="bg-white rounded-3xl border border-slate-200/90 shadow-card p-5 sm:p-6 space-y-4">
        <h3 class="font-bold text-base text-slate-900">Recent Operational Activity</h3>
        <?php if (empty($logs)): ?>
            <div class="p-8 text-center text-xs text-slate-400">
                Audit logs are clean. System running normally.
            </div>
        <?php else: ?>
            <div class="divide-y divide-slate-100 overflow-x-auto">
                <?php foreach ($logs as $lg): ?>
                    <div class="py-3 flex items-center justify-between text-xs gap-4 min-w-[280px]">
                        <div>
                            <span class="font-bold text-slate-900"><?= htmlspecialchars($lg['action'] ?? 'SYSTEM_EVENT') ?></span>
                            <p class="text-[11px] text-slate-500"><?= htmlspecialchars($lg['details'] ?? '') ?></p>
                        </div>
                        <span class="text-slate-400 shrink-0"><?= formatRelativeTime($lg['createdAt'] ?? null) ?></span>
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

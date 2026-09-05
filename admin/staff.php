<?php
// admin/staff.php - Team & Staff Management (Editable Operations Team & Staff Directory)
$pageTitle = "Team & Staff Operations";
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/includes/sidebar.php';

$userCol = getCollection("User");
$customStaffFile = __DIR__ . '/../config/custom_staff.json';

// Helper to load persistent custom staff overrides
function getCustomStaffData($customStaffFile) {
    if (file_exists($customStaffFile)) {
        $data = @json_decode(file_get_contents($customStaffFile), true);
        if (is_array($data)) return $data;
    }
    return [];
}

// Helper to save persistent custom staff overrides
function saveCustomStaffData($customStaffFile, $data) {
    @file_put_contents($customStaffFile, json_encode($data, JSON_PRETTY_PRINT));
}

// Default Base Team Members
$defaultTeam = [
    [
        'id' => 'admin_1',
        'name' => 'Operations Director (Admin 1)',
        'email' => 'admin@mentry.test',
        'role' => 'ADMIN',
        'phone' => '+91 98450 00001',
        'status' => 'ACTIVE',
        'avatar' => 'https://avatar.vercel.sh/Operations%20Director.png'
    ],
    [
        'id' => 'admin_2',
        'name' => 'Lead Administrator (Admin 2)',
        'email' => 'admin2@mentry.test',
        'role' => 'ADMIN',
        'phone' => '+91 98450 00002',
        'status' => 'ACTIVE',
        'avatar' => 'https://avatar.vercel.sh/Lead%20Administrator.png'
    ],
    [
        'id' => 'staff_1',
        'name' => 'Operations Coordinator (Staff 1)',
        'email' => 'staff1@mentry.test',
        'role' => 'STAFF',
        'phone' => '+91 98450 00003',
        'status' => 'ACTIVE',
        'avatar' => 'https://avatar.vercel.sh/Operations%20Coordinator.png'
    ],
    [
        'id' => 'staff_2',
        'name' => 'Talent Sourcing Specialist (Staff 2)',
        'email' => 'staff2@mentry.test',
        'role' => 'STAFF',
        'phone' => '+91 98450 00004',
        'status' => 'ACTIVE',
        'avatar' => 'https://avatar.vercel.sh/Talent%20Sourcing.png'
    ]
];

$successMsg = null;
$errorMsg = null;

// Initialize custom staff file if not present
$customStaff = getCustomStaffData($customStaffFile);
if (empty($customStaff)) {
    $customStaff = $defaultTeam;
    saveCustomStaffData($customStaffFile, $customStaff);
}

// 1. Handle CREATE STAFF
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['createStaff'])) {
    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'STAFF';
    $phone = trim($_POST['phone'] ?? '');
    $avatar = trim($_POST['avatar'] ?? '');

    $nameErr = validateNameInput($name, 'Staff name');
    $emailErr = validateEmailInput($email);
    $phoneErr = !empty($phone) ? validatePhoneInput($phone, 'Contact phone') : null;

    if ($nameErr) {
        $errorMsg = $nameErr;
    } elseif ($emailErr) {
        $errorMsg = $emailErr;
    } elseif ($phoneErr) {
        $errorMsg = $phoneErr;
    } elseif (empty($password)) {
        $errorMsg = "Password is required.";
    } else {
        $exists = false;
        foreach ($customStaff as $m) {
            if (strtolower($m['email']) === $email) {
                $exists = true;
                break;
            }
        }

        if (!$exists && $userCol) {
            $existingUser = $userCol->findOne(['email' => new MongoDB\BSON\Regex('^' . preg_quote($email) . '$', 'i')]);
            if ($existingUser) {
                $exists = true;
            }
        }

        if ($exists) {
            $errorMsg = "An account with this email address already exists in directory.";
        } else {
            $newId = 'staff_' . uniqid();
            if (empty($avatar)) {
                $avatar = 'https://avatar.vercel.sh/' . urlencode($name) . '.png';
            }

            $newMember = [
                'id' => $newId,
                'name' => $name,
                'email' => $email,
                'role' => $role,
                'phone' => $phone,
                'status' => 'ACTIVE',
                'avatar' => $avatar
            ];

            $customStaff[] = $newMember;
            saveCustomStaffData($customStaffFile, $customStaff);

            // Also insert into MongoDB User collection if available
            try {
                if ($userCol) {
                    $userCol->insertOne([
                        'name' => $name,
                        'email' => $email,
                        'password' => hashPassword($password),
                        'role' => $role,
                        'phone' => $phone,
                        'status' => 'ACTIVE',
                        'avatar' => $avatar,
                        'createdAt' => new MongoDB\BSON\UTCDateTime(),
                        'updatedAt' => new MongoDB\BSON\UTCDateTime()
                    ]);
                }
            } catch (\Throwable $e) {}

            $successMsg = "Successfully created new {$role} account: {$name}";
        }
    }
}

// 2. Handle UPDATE STAFF
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['updateStaff'])) {
    $targetEmail = strtolower(trim($_POST['target_email'] ?? ''));
    $name = trim($_POST['name'] ?? '');
    $newEmail = strtolower(trim($_POST['email'] ?? ''));
    $role = $_POST['role'] ?? 'STAFF';
    $phone = trim($_POST['phone'] ?? '');
    $status = $_POST['status'] ?? 'ACTIVE';
    $password = trim($_POST['password'] ?? '');
    $avatar = trim($_POST['avatar'] ?? '');

    $nameErr = validateNameInput($name, 'Staff name');
    $emailErr = validateEmailInput($newEmail);
    $phoneErr = !empty($phone) ? validatePhoneInput($phone, 'Contact phone') : null;

    if ($nameErr) {
        $errorMsg = $nameErr;
    } elseif ($emailErr) {
        $errorMsg = $emailErr;
    } elseif ($phoneErr) {
        $errorMsg = $phoneErr;
    } else {
        $updated = false;
        foreach ($customStaff as &$m) {
            if (strtolower($m['email']) === $targetEmail) {
                $m['name'] = $name;
                $m['email'] = $newEmail;
                $m['role'] = $role;
                $m['phone'] = $phone;
                $m['status'] = $status;
                if (!empty($avatar)) {
                    $m['avatar'] = $avatar;
                }
                $updated = true;
                break;
            }
        }
        unset($m);

        if ($updated) {
            saveCustomStaffData($customStaffFile, $customStaff);

            // Also update in MongoDB User collection if available
            try {
                if ($userCol) {
                    $setFields = [
                        'name' => $name,
                        'email' => $newEmail,
                        'role' => $role,
                        'phone' => $phone,
                        'status' => $status,
                        'updatedAt' => new MongoDB\BSON\UTCDateTime()
                    ];
                    if (!empty($avatar)) {
                        $setFields['avatar'] = $avatar;
                    }
                    if (!empty($password)) {
                        $setFields['password'] = hashPassword($password);
                    }
                    $userCol->updateOne(['email' => $targetEmail], ['$set' => $setFields]);
                }
            } catch (\Throwable $e) {}

            $successMsg = "Successfully updated member profile: {$name}";
        } else {
            $errorMsg = "Member record could not be found for update.";
        }
    }
}

// 3. Handle DELETE STAFF
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deleteStaff'])) {
    $targetEmail = strtolower(trim($_POST['delete_email'] ?? ''));
    $currentEmail = strtolower($adminUser['email'] ?? '');

    if ($targetEmail === $currentEmail) {
        $errorMsg = "Security notice: You cannot delete your currently active session account.";
    } else {
        $newList = [];
        $deleted = false;
        foreach ($customStaff as $m) {
            if (strtolower($m['email']) === $targetEmail) {
                $deleted = true;
            } else {
                $newList[] = $m;
            }
        }

        if ($deleted) {
            $customStaff = $newList;
            saveCustomStaffData($customStaffFile, $customStaff);

            try {
                if ($userCol) {
                    $userCol->deleteOne(['email' => $targetEmail]);
                }
            } catch (\Throwable $e) {}

            $successMsg = "Team member removed from directory successfully.";
        }
    }
}

// Prepare final team list
$teamMembers = $customStaff;
?>

<div class="max-w-7xl mx-auto space-y-6">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Operations Team & Staff Directory</h1>
            <p class="text-xs text-slate-500 mt-0.5">Manage, edit roles, and assign authorized Administrators and Staff members overseeing college workshops.</p>
        </div>
        <button type="button" onclick="document.getElementById('addStaffCard').classList.toggle('hidden')" class="bg-[#FE5E04] hover:bg-[#E04E00] text-white font-bold text-xs px-4 py-2.5 rounded-xl transition-all shadow-md shadow-orange-500/20 flex items-center gap-1.5 cursor-pointer">
            <span class="material-symbols-outlined text-[16px]">person_add</span>
            Add Staff Member
        </button>
    </div>

    <?php if ($successMsg): ?>
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-2xl text-xs font-bold flex items-center gap-2 shadow-xs animate-fadeIn">
            <span class="material-symbols-outlined text-base text-emerald-600">check_circle</span>
            <span><?= htmlspecialchars($successMsg) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($errorMsg): ?>
        <div class="bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-2xl text-xs font-bold flex items-center gap-2 shadow-xs animate-fadeIn">
            <span class="material-symbols-outlined text-base text-rose-600">error</span>
            <span><?= htmlspecialchars($errorMsg) ?></span>
        </div>
    <?php endif; ?>

    <!-- Add Staff Card (collapsible) -->
    <div id="addStaffCard" class="hidden bg-white p-6 rounded-3xl border border-slate-200/90 shadow-card space-y-4 animate-fadeIn">
        <div class="flex items-center justify-between">
            <h3 class="font-bold text-sm text-slate-900 flex items-center gap-2">
                <span class="material-symbols-outlined text-[#FE5E04] text-lg">person_add</span>
                Create New Team Account
            </h3>
            <button type="button" onclick="document.getElementById('addStaffCard').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 text-xs">
                ✕ Close
            </button>
        </div>
        <form method="POST" action="/admin/staff.php" class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
            <input type="hidden" name="createStaff" value="1">
            <div>
                <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Full Name *</label>
                <input type="text" name="name" required placeholder="e.g. Sumanth Kumar" pattern="[a-zA-Z\s\.\'-]{2,50}" title="Name can only contain letters, spaces, dots, or hyphens (no numbers allowed)" oninput="this.value = this.value.replace(/[0-9]/g, '')" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-xs outline-none focus:border-[#FE5E04]">
            </div>
            <div>
                <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Official Email *</label>
                <input type="email" name="email" required placeholder="e.g. staff3@mentry.test" pattern="^[a-zA-Z0-9._%+-]+@(?!gmail\.co$)(?!yahoo\.co$)(?!hotmail\.co$)(?!outlook\.co$)[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$" title="Please enter a valid email address (.co domain is not permitted for this provider)" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-xs outline-none focus:border-[#FE5E04]">
            </div>
            <div>
                <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Login Password *</label>
                <input type="password" name="password" required placeholder="••••••••" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-xs outline-none focus:border-[#FE5E04]">
            </div>
            <div>
                <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Role *</label>
                <select name="role" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-xs outline-none focus:border-[#FE5E04]">
                    <option value="STAFF">Operations Staff</option>
                    <option value="ADMIN">Administrator</option>
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Contact Phone</label>
                <input type="text" name="phone" placeholder="+91 98..." class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-xs outline-none focus:border-[#FE5E04]">
            </div>
            <div>
                <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Custom Avatar URL (Optional)</label>
                <input type="text" name="avatar" placeholder="https://..." class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-xs outline-none focus:border-[#FE5E04]">
            </div>
            <div class="sm:col-span-2 lg:col-span-3 flex justify-end pt-2">
                <button type="submit" class="bg-[#FE5E04] hover:bg-[#E04E00] text-white font-bold text-xs px-6 py-2.5 rounded-xl transition-all shadow-md cursor-pointer">
                    Save New Account
                </button>
            </div>
        </form>
    </div>

    <!-- Team Members Grid (Fully Editable) -->
    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <?php foreach ($teamMembers as $idx => $tm): 
            $roleColor = ($tm['role'] === 'ADMIN' || $tm['role'] === 'SUPER_ADMIN') ? 'bg-[#FE5E04]/10 text-[#FE5E04] border-[#FE5E04]/30' : 'bg-blue-50 text-blue-700 border-blue-200';
            $isActive = ($tm['status'] ?? 'ACTIVE') === 'ACTIVE';
            $avatarUrl = $tm['avatar'] ?? ("https://avatar.vercel.sh/" . urlencode($tm['name']) . ".png");
        ?>
            <div class="bg-white p-5 rounded-3xl border border-slate-200/90 shadow-card flex flex-col justify-between space-y-4 hover:shadow-card-hover transition-all group relative">
                
                <div class="flex items-start gap-3">
                    <img src="<?= htmlspecialchars($avatarUrl) ?>" class="w-12 h-12 rounded-2xl object-cover border border-slate-200 shadow-2xs">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-1.5">
                            <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-full border <?= $roleColor ?>">
                                <?= htmlspecialchars($tm['role']) ?>
                            </span>
                        </div>
                        <h4 class="font-bold text-xs text-slate-900 truncate mt-1" title="<?= htmlspecialchars($tm['name']) ?>"><?= htmlspecialchars($tm['name']) ?></h4>
                        <p class="text-[11px] text-slate-500 truncate"><?= htmlspecialchars($tm['email']) ?></p>
                    </div>
                </div>

                <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-[11px]">
                    <span class="text-slate-400 font-medium truncate max-w-[120px]"><?= htmlspecialchars($tm['phone'] ?? '+91 ...') ?></span>
                    <?php if ($isActive): ?>
                        <span class="inline-flex items-center gap-1 text-emerald-600 font-bold">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Active
                        </span>
                    <?php else: ?>
                        <span class="inline-flex items-center gap-1 text-slate-400 font-bold">
                            <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span> Inactive
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Action Toolbar: Edit & Delete -->
                <div class="pt-2 flex items-center gap-2">
                    <button type="button" onclick="openEditModal(<?= htmlspecialchars(json_encode($tm), ENT_QUOTES, 'UTF-8') ?>)" class="flex-1 bg-slate-50 hover:bg-orange-50 hover:text-[#FE5E04] border border-slate-200 text-slate-700 font-bold text-[11px] py-1.5 rounded-xl transition-all flex items-center justify-center gap-1 cursor-pointer">
                        <span class="material-symbols-outlined text-[14px]">edit</span>
                        Edit
                    </button>

                    <form method="POST" action="/admin/staff.php" onsubmit="return confirm('Are you sure you want to remove <?= htmlspecialchars(addslashes($tm['name'])) ?> from the directory?');" class="inline">
                        <input type="hidden" name="deleteStaff" value="1">
                        <input type="hidden" name="delete_email" value="<?= htmlspecialchars($tm['email']) ?>">
                        <button type="submit" class="p-1.5 rounded-xl bg-slate-50 hover:bg-rose-50 text-slate-400 hover:text-rose-600 border border-slate-200 transition-colors cursor-pointer" title="Delete member">
                            <span class="material-symbols-outlined text-[14px]">delete</span>
                        </button>
                    </form>
                </div>

            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- EDIT STAFF MODAL DIALOG -->
<div id="editModal" class="hidden fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl border border-slate-200 shadow-2xl max-w-lg w-full p-6 space-y-5 animate-fadeIn">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-[#FE5E04] text-xl">edit_note</span>
                <h3 class="font-extrabold text-base text-slate-900">Edit Team Member</h3>
            </div>
            <button type="button" onclick="closeEditModal()" class="text-slate-400 hover:text-slate-600 text-lg leading-none cursor-pointer">
                ✕
            </button>
        </div>

        <form method="POST" action="/admin/staff.php" class="space-y-4">
            <input type="hidden" name="updateStaff" value="1">
            <input type="hidden" id="edit_target_email" name="target_email" value="">

            <div class="grid sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Full Name *</label>
                    <input type="text" id="edit_name" name="name" required class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-xs outline-none focus:border-[#FE5E04]">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Official Email *</label>
                    <input type="email" id="edit_email" name="email" required class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-xs outline-none focus:border-[#FE5E04]">
                </div>
            </div>

            <div class="grid sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Role</label>
                    <select id="edit_role" name="role" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-xs outline-none focus:border-[#FE5E04]">
                        <option value="STAFF">Operations Staff</option>
                        <option value="ADMIN">Administrator</option>
                        <option value="SUPER_ADMIN">Super Administrator</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Status</label>
                    <select id="edit_status" name="status" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-xs outline-none focus:border-[#FE5E04]">
                        <option value="ACTIVE">Active</option>
                        <option value="INACTIVE">Inactive / On Leave</option>
                    </select>
                </div>
            </div>

            <div class="grid sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Phone Number</label>
                    <input type="text" id="edit_phone" name="phone" placeholder="+91 98..." class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-xs outline-none focus:border-[#FE5E04]">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Reset Password (Optional)</label>
                    <input type="password" name="password" placeholder="Leave blank to keep current" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-xs outline-none focus:border-[#FE5E04]">
                </div>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Profile Avatar URL (Optional)</label>
                <input type="text" id="edit_avatar" name="avatar" placeholder="https://..." class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-xs outline-none focus:border-[#FE5E04]">
            </div>

            <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                <button type="button" onclick="closeEditModal()" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100 transition-colors cursor-pointer">
                    Cancel
                </button>
                <button type="submit" class="bg-[#FE5E04] hover:bg-[#E04E00] text-white font-bold text-xs px-5 py-2.5 rounded-xl transition-all shadow-md cursor-pointer">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(member) {
    document.getElementById('edit_target_email').value = member.email || '';
    document.getElementById('edit_name').value = member.name || '';
    document.getElementById('edit_email').value = member.email || '';
    document.getElementById('edit_role').value = member.role || 'STAFF';
    document.getElementById('edit_status').value = member.status || 'ACTIVE';
    document.getElementById('edit_phone').value = member.phone || '';
    document.getElementById('edit_avatar').value = member.avatar || '';
    document.getElementById('editModal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
}
</script>

</main>
</div>
</body>
</html>

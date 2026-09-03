<?php
// trainer/documents.php
$pageTitle = "My Documents & Certifications";
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/includes/sidebar.php';

$docCol = getCollection("Document");
$trainerCol = getCollection("Trainer");
$trainer = $trainerCol ? $trainerCol->findOne(['userId' => $user['id']]) : null;
$trainerId = $trainer ? (string)$trainer['_id'] : '';

$documents = $docCol ? $docCol->find(['trainerId' => $trainerId], ['sort' => ['uploadedAt' => -1]])->toArray() : [];

$newSkillsExtracted = $_SESSION['resume_skills_extracted'] ?? null;
$extractedSkillsList = $_SESSION['extracted_skills_list'] ?? [];
unset($_SESSION['resume_skills_extracted']);
unset($_SESSION['extracted_skills_list']);

$currentExtractedSkills = $trainer['extractedSkills'] ?? [];

$uploadError = $_SESSION['upload_error'] ?? null;
unset($_SESSION['upload_error']);
?>

<div class="max-w-4xl mx-auto space-y-6">
    <div>
        <h1 class="text-2xl font-black text-slate-900 tracking-tight">Documents & Resume Skill Parser</h1>
        <p class="text-xs text-slate-500 mt-0.5">Upload your comprehensive CV/resume. Our engine automatically parses your skills to match college requirements.</p>
    </div>

    <?php if ($uploadError): ?>
        <div class="bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-2xl text-xs font-bold">
            <?= htmlspecialchars($uploadError) ?>
        </div>
    <?php endif; ?>

    <?php if ($newSkillsExtracted): ?>
        <div class="bg-gradient-to-r from-orange-500/10 via-amber-500/10 to-orange-500/5 border-2 border-[#FE5E04] rounded-3xl p-6 shadow-lg shadow-orange-500/5 space-y-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-[#FE5E04] text-white flex items-center justify-center font-bold shadow-md shadow-orange-500/20">
                    <span class="material-symbols-outlined text-2xl">auto_awesome</span>
                </div>
                <div>
                    <h3 class="font-extrabold text-sm text-slate-900">Success! Extracted <?= htmlspecialchars($newSkillsExtracted) ?> Skills from Resume</h3>
                    <p class="text-xs text-slate-600">Your profile domain, experience, and verified skill stack have been automatically updated.</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2 pt-2">
                <?php foreach ($extractedSkillsList as $sk): ?>
                    <span class="inline-flex items-center gap-1 bg-white text-[#FE5E04] font-bold text-xs px-3 py-1 rounded-full border border-[#FE5E04]/30 shadow-xs">
                        <span class="material-symbols-outlined text-[14px]">check</span>
                        <?= htmlspecialchars($sk) ?>
                    </span>
                <?php endforeach; ?>
            </div>
            <div class="pt-2 text-right">
                <a href="/trainer/expertise.php" class="inline-flex items-center gap-1 text-xs font-bold text-[#FE5E04] hover:underline">
                    View & Manage Skill Stack →
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- AI Extracted Skills Banner if trainer already has parsed skills -->
    <?php if (!empty($currentExtractedSkills) && !$newSkillsExtracted): ?>
        <div class="bg-white rounded-3xl border border-slate-200/90 shadow-card p-6 space-y-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-[#FE5E04] text-xl">psychology</span>
                    <h3 class="font-bold text-sm text-slate-900">AI Parsed Resume Skills (<?= count($currentExtractedSkills) ?>)</h3>
                </div>
                <a href="/trainer/expertise.php" class="text-xs font-bold text-[#FE5E04] hover:underline">Edit Skills</a>
            </div>
            <div class="flex flex-wrap gap-1.5">
                <?php foreach ($currentExtractedSkills as $sk): ?>
                    <span class="bg-orange-50 text-[#FE5E04] text-xs font-semibold px-3 py-1 rounded-xl border border-orange-200/60">
                        <?= htmlspecialchars($sk) ?>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Upload Card Form -->
    <form action="/actions/upload-document.php" method="POST" enctype="multipart/form-data" class="bg-white rounded-3xl border border-slate-200/90 shadow-card p-8 space-y-4">
        <input type="hidden" name="trainerId" value="<?= $trainerId ?>">

        <div class="flex items-center gap-3 border-b border-slate-100 pb-3">
            <div class="w-10 h-10 bg-orange-50 text-[#FE5E04] rounded-xl flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-2xl">upload_file</span>
            </div>
            <div>
                <h3 class="font-bold text-sm text-slate-900">Upload Professional Resume / Certification</h3>
                <p class="text-[11px] text-slate-500">PDF, DOCX, or Image (Strict Max 5MB Limit) • Automatic skill recognition enabled</p>
            </div>
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Document Title *</label>
                <input type="text" name="title" required value="Technical Trainer Resume - <?= htmlspecialchars($user['name']) ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-xs outline-none focus:bg-white focus:border-[#FE5E04]">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Document Type *</label>
                <select name="type" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-xs outline-none focus:bg-white focus:border-[#FE5E04]">
                    <option value="RESUME">Resume / Comprehensive CV (Skill Auto-Extract)</option>
                    <option value="CERTIFICATE">Industry Certificate</option>
                    <option value="GOVT_ID">Government Identification</option>
                </select>
            </div>

            <div class="sm:col-span-2">
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Select File (PDF / DOCX) *</label>
                <input type="file" name="document" required accept=".pdf,.docx,.doc,.txt" class="w-full text-xs text-slate-600 bg-slate-50 border border-slate-200 rounded-xl p-2.5 file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-[#FE5E04]/10 file:text-[#FE5E04] hover:file:bg-[#FE5E04]/20 cursor-pointer">
            </div>
        </div>

        <div class="flex justify-end pt-2">
            <button type="submit" class="bg-[#FE5E04] hover:bg-[#E04E00] text-white font-bold text-xs px-6 py-2.5 rounded-xl shadow-md shadow-orange-500/20 transition-all flex items-center gap-1.5">
                <span class="material-symbols-outlined text-[16px]">upload</span>
                Upload & Auto-Extract Skills
            </button>
        </div>
    </form>

    <!-- Document List -->
    <div class="bg-white rounded-3xl border border-slate-200/90 shadow-card p-6 space-y-4">
        <h3 class="font-bold text-base text-slate-900">Uploaded Documents (<?= count($documents) ?>)</h3>
        <?php if (empty($documents)): ?>
            <div class="p-6 text-center text-xs text-slate-400">
                No documents uploaded yet. Upload your resume to expedite verified college matching.
            </div>
        <?php else: ?>
            <div class="space-y-2">
                <?php foreach ($documents as $d): ?>
                    <div class="p-4 rounded-2xl border border-slate-100 bg-slate-50 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-[#FE5E04] text-2xl">description</span>
                            <div>
                                <p class="font-bold text-xs text-slate-900"><?= htmlspecialchars($d['title'] ?? 'Document') ?></p>
                                <p class="text-[10px] text-slate-400"><?= htmlspecialchars($d['type'] ?? 'RESUME') ?> • <?= formatDate($d['uploadedAt'] ?? null) ?></p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="bg-emerald-50 text-emerald-700 text-[10px] font-bold px-2.5 py-0.5 rounded-full border border-emerald-200">
                                Verified
                            </span>
                            <?php if (!empty($d['fileUrl'])): ?>
                                <a href="<?= htmlspecialchars($d['fileUrl']) ?>" target="_blank" download class="p-1.5 text-slate-600 hover:text-[#FE5E04] hover:bg-orange-50 rounded-lg transition-colors">
                                    <span class="material-symbols-outlined text-[18px]">download</span>
                                </a>
                            <?php endif; ?>
                        </div>
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

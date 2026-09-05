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
        <?php else: 
            $cleanTrainerName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', trim($user['name'] ?? 'Trainer'));
            $cleanTrainerCode = preg_replace('/[^a-zA-Z0-9_\-]/', '_', trim(getMentryCode('TRAINER', $trainer ?? $user)));
            $trainerDocPrefix = $cleanTrainerName . '_' . $cleanTrainerCode;
        ?>
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
                            <?php if (!empty($d['fileUrl'])): 
                                $docExt = strtolower(pathinfo($d['fileUrl'] ?? '', PATHINFO_EXTENSION)) ?: 'pdf';
                                $cleanDocTitle = preg_replace('/[^a-zA-Z0-9_\-]/', '_', trim($d['title'] ?? ($d['type'] ?? 'Document')));
                                $docDownloadName = $cleanTrainerName . '_' . $cleanDocTitle . '_' . $cleanTrainerCode . '.' . $docExt;
                                $docDownloadUrl = '/actions/download-document.php?url=' . urlencode($d['fileUrl'] ?? '') . '&filename=' . urlencode($docDownloadName);
                            ?>
                                <button type="button" onclick="openDocumentPreview('<?= htmlspecialchars($d['fileUrl']) ?>', '<?= htmlspecialchars(addslashes($d['title'] ?? 'Document')) ?>', '<?= htmlspecialchars(addslashes($docDownloadName)) ?>')" class="bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs px-3 py-1.5 rounded-xl transition-all shadow-xs flex items-center gap-1 cursor-pointer">
                                    <span class="material-symbols-outlined text-[15px]">visibility</span>
                                    Preview
                                </button>
                                <a href="<?= htmlspecialchars($docDownloadUrl) ?>" download="<?= htmlspecialchars($docDownloadName) ?>" class="p-1.5 text-slate-600 hover:text-[#FE5E04] hover:bg-orange-50 rounded-lg transition-colors" title="Download <?= htmlspecialchars($docDownloadName) ?>">
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

<!-- In-Browser Document Preview Modal (No Download Required) -->
<div id="documentPreviewModal" class="hidden fixed inset-0 z-50 bg-slate-900/70 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-5xl w-full h-[90vh] flex flex-col shadow-2xl overflow-hidden border border-slate-200">
        <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between bg-white shrink-0">
            <div class="flex items-center gap-2.5">
                <span class="material-symbols-outlined text-[#FE5E04] text-2xl">description</span>
                <div>
                    <h3 id="previewDocTitle" class="font-bold text-sm text-slate-900">Document Preview</h3>
                    <p class="text-[11px] text-slate-500">In-browser document viewer</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a id="previewDocDownloadLink" href="#" target="_blank" download class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold px-3 py-1.5 rounded-xl transition-colors flex items-center gap-1">
                    <span class="material-symbols-outlined text-[15px]">download</span>
                    Download
                </a>
                <button type="button" onclick="closeDocumentPreview()" class="p-1.5 text-slate-400 hover:text-slate-700 rounded-lg">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
        </div>
        <div class="flex-1 bg-slate-100 p-2 overflow-hidden relative">
            <iframe id="previewDocIframe" src="" class="w-full h-full rounded-2xl border-0 bg-white"></iframe>
        </div>
    </div>
</div>

<script>
function openDocumentPreview(url, title, downloadFilename) {
    const modal = document.getElementById('documentPreviewModal');
    const iframe = document.getElementById('previewDocIframe');
    const titleEl = document.getElementById('previewDocTitle');
    const downloadEl = document.getElementById('previewDocDownloadLink');
    if (titleEl) titleEl.textContent = title || 'Document Preview';
    if (downloadEl) {
        const dlName = downloadFilename || 'document';
        downloadEl.href = '/actions/download-document.php?url=' + encodeURIComponent(url) + '&filename=' + encodeURIComponent(dlName);
        downloadEl.setAttribute('download', dlName);
        downloadEl.setAttribute('title', 'Download ' + dlName);
    }
    const previewUrl = '/actions/preview-doc.php?url=' + encodeURIComponent(url) + '&title=' + encodeURIComponent(title || 'Document');
    if (iframe) iframe.src = previewUrl;
    if (modal) modal.classList.remove('hidden');
}

function closeDocumentPreview() {
    const modal = document.getElementById('documentPreviewModal');
    const iframe = document.getElementById('previewDocIframe');
    if (iframe) iframe.src = 'about:blank';
    if (modal) modal.classList.add('hidden');
}
</script>

</main>
</div>
</body>
</html>

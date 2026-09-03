<?php
// admin/team-chat.php - Internal Workspace Team Chat for Admin & Staff (with File Attachments & Explicit AI Extraction)
$pageTitle = "Internal Team Workspace Chat";
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/includes/sidebar.php';

// Strict backend role enforcement
requireAdminOrStaff();
$currentUser = getCurrentUser();
?>

<div class="max-w-7xl mx-auto h-[calc(100vh-140px)] flex flex-col md:flex-row gap-4">
    
    <!-- Left Sidebar: Operations Team Members -->
    <div class="w-full md:w-72 bg-white rounded-3xl border border-slate-200/90 shadow-card p-5 flex flex-col shrink-0">
        <div class="pb-4 border-b border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-[#FE5E04]">forum</span>
                <h2 class="font-extrabold text-sm text-slate-900">Operations Hub</h2>
            </div>
            <span class="inline-flex items-center gap-1 text-[10px] font-extrabold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-full">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                Secure Workspace
            </span>
        </div>

        <div class="py-3">
            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-2">Team Members & Channels:</span>
            
            <div class="space-y-1.5">
                <div class="bg-orange-50/70 border border-orange-200/80 rounded-2xl p-3 flex items-center gap-3 cursor-pointer">
                    <div class="w-9 h-9 rounded-xl bg-[#FE5E04] text-white flex items-center justify-center font-bold text-xs shadow-xs">
                        #
                    </div>
                    <div class="min-w-0 flex-1">
                        <h4 class="font-bold text-xs text-slate-900 truncate">#general-operations</h4>
                        <p class="text-[10px] text-slate-500 truncate">Campus & faculty logistics</p>
                    </div>
                </div>

                <div class="p-2.5 rounded-2xl hover:bg-slate-50 transition-colors flex items-center gap-3 cursor-pointer" onclick="insertAiMention()">
                    <div class="w-9 h-9 rounded-xl bg-slate-900 text-[#FE5E04] flex items-center justify-center font-bold text-xs">
                        <span class="material-symbols-outlined text-sm">smart_toy</span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <h4 class="font-bold text-xs text-slate-900 truncate">Mentor AI Bot</h4>
                        <p class="text-[10px] text-slate-400 truncate">Type @AI in chat to match</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Authorized Active Team List -->
        <div class="mt-auto pt-4 border-t border-slate-100">
            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-2">Authorized Staff (4)</span>
            <div class="space-y-2 text-xs">
                <div class="flex items-center justify-between text-slate-600">
                    <span class="truncate">🛡️ Admin 1 (Director)</span>
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                </div>
                <div class="flex items-center justify-between text-slate-600">
                    <span class="truncate">🛡️ Admin 2 (Lead Ops)</span>
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                </div>
                <div class="flex items-center justify-between text-slate-600">
                    <span class="truncate">💼 Staff 1 (Coordinator)</span>
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                </div>
                <div class="flex items-center justify-between text-slate-600">
                    <span class="truncate">💼 Staff 2 (Sourcing)</span>
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Center: Main Chat Thread & Input Canvas -->
    <div class="flex-1 bg-white rounded-3xl border border-slate-200/90 shadow-card flex flex-col overflow-hidden">
        
        <!-- Chat Header Bar -->
        <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/60 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-white border border-slate-200 shadow-xs flex items-center justify-center text-[#FE5E04] font-black text-sm">
                    #
                </div>
                <div>
                    <h3 class="font-extrabold text-sm text-slate-900">#general-operations</h3>
                    <p class="text-[11px] text-slate-500">Internal coordination for training schedules, files & AI requirement discovery</p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <button type="button" onclick="insertAiMention()" class="bg-orange-50 hover:bg-orange-100 border border-orange-200 text-[#FE5E04] text-xs font-bold px-3 py-1.5 rounded-xl transition-colors flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-[15px]">smart_toy</span>
                    <span>@AI Match</span>
                </button>
            </div>
        </div>

        <!-- Chat Message Stream -->
        <div id="chatMessageStream" class="flex-1 p-6 overflow-y-auto space-y-4 bg-[#FAFBFD]">
            <div class="text-center py-6 text-xs text-slate-400">Loading workspace messages...</div>
        </div>

        <!-- Chat Input & File Attachment Toolbar -->
        <div class="p-4 border-t border-slate-200 bg-white space-y-2">
            
            <!-- File Attachment Selected Preview Strip -->
            <div id="filePreviewStrip" class="hidden bg-slate-50 border border-slate-200 rounded-xl p-2 px-3 flex items-center justify-between text-xs animate-fadeIn">
                <div class="flex items-center gap-2 truncate">
                    <span class="material-symbols-outlined text-blue-600 text-base">attach_file</span>
                    <span id="fileNameDisplay" class="font-bold text-slate-800 truncate"></span>
                </div>
                <button type="button" onclick="clearSelectedFile()" class="text-slate-400 hover:text-rose-600 text-xs font-bold">✕ Remove</button>
            </div>

            <form id="chatMessageForm" onsubmit="handleSendMessage(event)" class="flex items-center gap-2">
                
                <!-- Hidden file input -->
                <input type="file" id="chatFileInput" onchange="handleFileSelected(this)" class="hidden">

                <!-- Attachment Button -->
                <button type="button" onclick="document.getElementById('chatFileInput').click()" class="p-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 transition-colors cursor-pointer" title="Attach file or document">
                    <span class="material-symbols-outlined text-[20px]">attach_file</span>
                </button>

                <!-- Message Input -->
                <input type="text" id="chatTextInput" autocomplete="off" placeholder="Message operations team or type @AI find trainers for requirement..." class="flex-1 bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-xs sm:text-sm outline-none focus:border-[#FE5E04] focus:bg-white transition-all shadow-inner">

                <!-- Send Button -->
                <button type="submit" id="sendBtn" class="bg-[#FE5E04] hover:bg-[#E04E00] text-white px-5 py-3 rounded-2xl font-bold text-xs flex items-center gap-1.5 transition-all shadow-md cursor-pointer disabled:opacity-50">
                    <span>Send</span>
                    <span class="material-symbols-outlined text-base">send</span>
                </button>
            </form>
        </div>
    </div>
</div>

<script>
let currentUserId = '<?= $currentUser['id'] ?? '' ?>';
let currentUserName = '<?= addslashes($currentUser['name'] ?? 'User') ?>';
let selectedFile = null;

async function loadChatMessages() {
    try {
        const res = await fetch('/actions/team-chat-api.php?action=get_messages');
        const data = await res.json();
        if (data.success && data.messages) {
            renderMessages(data.messages);
        }
    } catch (e) {
        console.error("Chat polling error", e);
    }
}

function renderMessages(messages) {
    const stream = document.getElementById('chatMessageStream');
    
    stream.innerHTML = messages.map(msg => {
        const isSelf = msg.senderId === currentUserId;
        const isAI = msg.isAI || msg.senderRole === 'AI_AGENT';

        if (isAI) {
            return `
                <div class="flex items-start gap-3 max-w-2xl bg-white border border-orange-200/80 rounded-3xl p-4 shadow-xs animate-fadeIn">
                    <div class="w-8 h-8 rounded-xl bg-[#FE5E04] text-white flex items-center justify-center shrink-0 shadow-xs">
                        <span class="material-symbols-outlined text-base">smart_toy</span>
                    </div>
                    <div class="space-y-1.5 flex-1 min-w-0">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-1.5">
                                <span class="font-extrabold text-xs text-slate-900">Mentor AI Assistant</span>
                                <span class="text-[9px] font-black uppercase px-1.5 py-0.2 rounded bg-orange-100 text-[#FE5E04]">AI Matching Engine</span>
                            </div>
                            <span class="text-[10px] text-slate-400">${escapeHtml(msg.timestamp.split(' ')[1] || '')}</span>
                        </div>
                        <div class="text-xs text-slate-800 leading-relaxed whitespace-pre-line font-medium bg-slate-50/70 p-3 rounded-2xl border border-slate-100">
                            ${formatAiMessageText(msg.text)}
                        </div>
                        <div class="pt-1 flex items-center gap-2">
                            <a href="/admin/ai-assistant.php" class="text-[11px] font-bold text-[#FE5E04] hover:underline flex items-center gap-1">
                                Open in AI Discovery Center →
                            </a>
                        </div>
                    </div>
                </div>
            `;
        }

        return `
            <div class="flex items-start gap-3 ${isSelf ? 'justify-end' : 'justify-start'} group animate-fadeIn">
                ${!isSelf ? `
                    <div class="w-8 h-8 rounded-xl bg-slate-800 text-white flex items-center justify-center text-xs font-bold shrink-0">
                        ${escapeHtml(msg.senderName.charAt(0))}
                    </div>
                ` : ''}

                <div class="space-y-1 max-w-lg">
                    <div class="flex items-center gap-2 ${isSelf ? 'justify-end' : 'justify-start'}">
                        <span class="font-bold text-[11px] text-slate-700">${escapeHtml(msg.senderName)}</span>
                        <span class="text-[9px] font-extrabold uppercase px-1.5 py-0.2 rounded ${msg.senderRole === 'ADMIN' ? 'bg-orange-100 text-[#FE5E04]' : 'bg-blue-100 text-blue-700'}">${escapeHtml(msg.senderRole)}</span>
                        <span class="text-[10px] text-slate-400">${escapeHtml(msg.timestamp.split(' ')[1] || '')}</span>
                    </div>

                    <div class="p-3.5 rounded-2xl text-xs sm:text-sm leading-relaxed ${isSelf ? 'bg-[#0F172A] text-white rounded-tr-xs' : 'bg-white text-slate-800 border border-slate-200/90 rounded-tl-xs shadow-xs'}">
                        ${msg.text ? `<p class="whitespace-pre-line">${escapeHtml(msg.text)}</p>` : ''}
                        
                        ${msg.attachment ? `
                            <div class="mt-2 pt-2 border-t ${isSelf ? 'border-slate-700' : 'border-slate-100'} flex items-center justify-between gap-3">
                                <a href="${escapeHtml(msg.attachment.url)}" target="_blank" class="inline-flex items-center gap-1.5 font-bold ${isSelf ? 'text-[#FE5E04] hover:underline' : 'text-blue-600 hover:underline'} truncate text-xs">
                                    <span class="material-symbols-outlined text-base">description</span>
                                    <span class="truncate">${escapeHtml(msg.attachment.name)}</span>
                                </a>
                                <span class="text-[10px] text-slate-400 shrink-0">(${escapeHtml(msg.attachment.size)})</span>
                            </div>
                        ` : ''}
                    </div>

                    <!-- Explicit "Ask AI to Match" Action on Individual Message -->
                    ${msg.text && !msg.text.includes('@AI') ? `
                        <div class="opacity-0 group-hover:opacity-100 transition-opacity flex items-center gap-2 ${isSelf ? 'justify-end' : 'justify-start'}">
                            <button type="button" onclick="askAiOnMessage('${escapeHtml(msg.id)}')" class="text-[10px] font-bold text-slate-500 hover:text-[#FE5E04] flex items-center gap-1 py-0.5 cursor-pointer">
                                <span class="material-symbols-outlined text-[13px]">smart_toy</span>
                                <span>Ask AI to Match Trainers</span>
                            </button>
                        </div>
                    ` : ''}
                </div>

                ${isSelf ? `
                    <div class="w-8 h-8 rounded-xl bg-[#FE5E04] text-white flex items-center justify-center text-xs font-bold shrink-0">
                        ${escapeHtml(currentUserName.charAt(0))}
                    </div>
                ` : ''}
            </div>
        `;
    }).join('');

    stream.scrollTop = stream.scrollHeight;
}

function handleFileSelected(input) {
    if (input.files && input.files[0]) {
        selectedFile = input.files[0];
        document.getElementById('fileNameDisplay').innerText = selectedFile.name + ` (${Math.round(selectedFile.size / 1024)} KB)`;
        document.getElementById('filePreviewStrip').classList.remove('hidden');
    }
}

function clearSelectedFile() {
    selectedFile = null;
    document.getElementById('chatFileInput').value = '';
    document.getElementById('filePreviewStrip').classList.add('hidden');
}

function insertAiMention() {
    const input = document.getElementById('chatTextInput');
    input.value = '@AI find trainers for ';
    input.focus();
}

async function handleSendMessage(e) {
    e.preventDefault();
    const textInput = document.getElementById('chatTextInput');
    const text = textInput.value.trim();

    if (!text && !selectedFile) return;

    const btn = document.getElementById('sendBtn');
    btn.disabled = true;

    const formData = new FormData();
    formData.append('action', 'send_message');
    formData.append('text', text);
    if (selectedFile) {
        formData.append('file', selectedFile);
    }

    try {
        const response = await fetch('/actions/team-chat-api.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();
        if (data.success && data.allMessages) {
            textInput.value = '';
            clearSelectedFile();
            renderMessages(data.allMessages);
        } else {
            alert(data.message || 'Failed to send message.');
        }
    } catch (e) {
        alert('Network error while sending message.');
    } finally {
        btn.disabled = false;
    }
}

async function askAiOnMessage(messageId) {
    const formData = new FormData();
    formData.append('action', 'ask_ai_on_message');
    formData.append('messageId', messageId);

    try {
        const response = await fetch('/actions/team-chat-api.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();
        if (data.success && data.allMessages) {
            renderMessages(data.allMessages);
        } else {
            alert(data.message || 'Failed to extract AI requirement.');
        }
    } catch (e) {
        alert('Network error while requesting AI matching.');
    }
}

function formatAiMessageText(text) {
    if (!text) return '';
    return text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
               .replace(/\*(.*?)\*/g, '<em>$1</em>');
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// Initial load & Polling
loadChatMessages();
setInterval(loadChatMessages, 5000);
</script>

</main>
</div>
</body>
</html>

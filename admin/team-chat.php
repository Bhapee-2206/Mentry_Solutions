<?php
// admin/team-chat.php - Internal Workspace Team Chat with Zervy Agent, Edit, Delete, Clear Chat & File Sharing
$pageTitle = "Internal Team Workspace Chat";
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/ai_agent.php';
require_once __DIR__ . '/includes/sidebar.php';

// Strict backend role enforcement
requireAdminOrStaff();
$currentUser = getCurrentUser();
$tokenMetrics = AIAgent::getTokenMetrics();
$isAdminUser = isAdmin();
?>

<div class="max-w-7xl mx-auto h-[calc(100vh-140px)] flex flex-col md:flex-row gap-4">
    
    <!-- Left Sidebar: Operations Team Members & Token Stats -->
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

        <div class="py-3 space-y-3">
            <div>
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-2">Channels & Agent:</span>
                
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
                            <h4 class="font-bold text-xs text-slate-900 truncate">Zervy AI Bot</h4>
                            <p class="text-[10px] text-slate-400 truncate">Type @Zervy in chat</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Token Stats Mini Widget in Chat -->
            <div class="bg-slate-50 border border-slate-200 rounded-2xl p-3 space-y-1">
                <div class="flex items-center justify-between text-[10px] font-extrabold text-slate-500 uppercase tracking-wider">
                    <span>Zervy Tokens</span>
                    <span class="text-[#FE5E04] font-mono">⚡ LIVE</span>
                </div>
                <div class="flex items-baseline justify-between">
                    <span id="chatTokensCounter" class="text-xs font-black text-slate-900"><?= number_format($tokenMetrics['grandTotalTokens'] ?? 0) ?> Tokens</span>
                    <span class="text-[10px] text-emerald-600 font-bold">$<?= number_format($tokenMetrics['estimatedCostUsd'] ?? 0.0, 4) ?></span>
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
        
        <!-- Chat Header Bar with Clear Chat and Actions -->
        <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/60 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-white border border-slate-200 shadow-xs flex items-center justify-center text-[#FE5E04] font-black text-sm">
                    #
                </div>
                <div>
                    <h3 class="font-extrabold text-sm text-slate-900">#general-operations</h3>
                    <p class="text-[11px] text-slate-500">Internal coordination for training schedules, files & Zervy AI discovery</p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <button type="button" onclick="confirmClearChat()" class="bg-white hover:bg-rose-50 border border-slate-200 hover:border-rose-200 text-slate-600 hover:text-rose-600 text-xs font-bold px-3 py-1.5 rounded-xl transition-all flex items-center gap-1 cursor-pointer" title="Clear all messages in channel">
                    <span class="material-symbols-outlined text-[15px]">delete_sweep</span>
                    <span>Clear Chat</span>
                </button>
                <button type="button" onclick="insertAiMention()" class="bg-orange-50 hover:bg-orange-100 border border-orange-200 text-[#FE5E04] text-xs font-bold px-3 py-1.5 rounded-xl transition-colors flex items-center gap-1.5 cursor-pointer">
                    <span class="material-symbols-outlined text-[15px]">smart_toy</span>
                    <span>@Zervy Ask</span>
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
                <button type="button" onclick="clearSelectedFile()" class="text-slate-400 hover:text-rose-600 text-xs font-bold cursor-pointer">✕ Remove</button>
            </div>

            <form id="chatMessageForm" onsubmit="handleSendMessage(event)" class="flex items-center gap-2">
                
                <!-- Hidden file input -->
                <input type="file" id="chatFileInput" onchange="handleFileSelected(this)" class="hidden">

                <!-- Attachment Button -->
                <button type="button" onclick="document.getElementById('chatFileInput').click()" class="p-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 transition-colors cursor-pointer" title="Attach file or document">
                    <span class="material-symbols-outlined text-[20px]">attach_file</span>
                </button>

                <!-- Message Input -->
                <input type="text" id="chatTextInput" autocomplete="off" placeholder="Message operations team or type @Zervy what is python training syllabus..." class="flex-1 bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-xs sm:text-sm outline-none focus:border-[#FE5E04] focus:bg-white transition-all shadow-inner">

                <!-- Send Button -->
                <button type="submit" id="sendBtn" class="bg-[#FE5E04] hover:bg-[#E04E00] text-white px-5 py-3 rounded-2xl font-bold text-xs flex items-center gap-1.5 transition-all shadow-md cursor-pointer disabled:opacity-50">
                    <span>Send</span>
                    <span class="material-symbols-outlined text-base">send</span>
                </button>
            </form>
        </div>
    </div>
</div>

<!-- EDIT MESSAGE MODAL -->
<div id="editMessageModal" class="hidden fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl border border-slate-200 shadow-2xl max-w-lg w-full p-6 space-y-4 animate-fadeIn">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-[#FE5E04]">edit</span>
                <h3 class="font-extrabold text-sm text-slate-900">Edit Message</h3>
            </div>
            <button type="button" onclick="closeEditModal()" class="text-slate-400 hover:text-slate-600 text-lg cursor-pointer">✕</button>
        </div>

        <form onsubmit="submitEditMessage(event)" class="space-y-4">
            <input type="hidden" id="editModalMsgId">
            <textarea id="editModalTextInput" rows="4" required class="w-full bg-slate-50 border border-slate-200 rounded-2xl p-3 text-xs sm:text-sm outline-none focus:border-[#FE5E04] focus:bg-white transition-all shadow-inner"></textarea>

            <div class="flex items-center justify-end gap-2.5 pt-2">
                <button type="button" onclick="closeEditModal()" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100 transition-colors cursor-pointer">Cancel</button>
                <button type="submit" class="bg-[#FE5E04] hover:bg-[#E04E00] text-white font-bold text-xs px-5 py-2 rounded-xl transition-all shadow-md cursor-pointer">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- CLEAR CHAT CONFIRMATION MODAL -->
<div id="clearChatModal" class="hidden fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl border border-slate-200 shadow-2xl max-w-md w-full p-6 space-y-4 animate-fadeIn">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center font-bold">
                <span class="material-symbols-outlined">delete_sweep</span>
            </div>
            <div>
                <h3 class="font-extrabold text-sm text-slate-900">Clear Operations Chat</h3>
                <p class="text-xs text-slate-500">Permanently delete messages in #general-operations</p>
            </div>
        </div>

        <p class="text-xs text-slate-600 bg-slate-50 p-3.5 rounded-2xl border border-slate-200 leading-relaxed">
            Are you sure you want to clear this workspace chat channel? All team discussions and uploaded file references will be wiped for a fresh start.
        </p>

        <div class="flex items-center justify-end gap-2.5 pt-2">
            <button type="button" onclick="closeClearChatModal()" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100 transition-colors cursor-pointer">Cancel</button>
            <button type="button" id="confirmClearBtn" onclick="executeClearChat()" class="bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs px-5 py-2 rounded-xl transition-all shadow-md flex items-center gap-1.5 cursor-pointer">
                <span class="material-symbols-outlined text-[16px]">delete_sweep</span>
                <span>Yes, Clear All</span>
            </button>
        </div>
    </div>
</div>

<script>
let currentUserId = '<?= $currentUser['id'] ?? '' ?>';
let currentUserName = '<?= addslashes($currentUser['name'] ?? 'User') ?>';
let isUserAdmin = <?= $isAdminUser ? 'true' : 'false' ?>;
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
    
    if (!messages || messages.length === 0) {
        stream.innerHTML = `
            <div class="h-full flex flex-col items-center justify-center text-center p-8 space-y-3 py-16 animate-fadeIn">
                <div class="w-14 h-14 rounded-3xl bg-slate-100 text-slate-400 flex items-center justify-center">
                    <span class="material-symbols-outlined text-3xl text-slate-400">forum</span>
                </div>
                <div class="space-y-1">
                    <h4 class="font-extrabold text-sm text-slate-800">#general-operations is empty</h4>
                    <p class="text-xs text-slate-400 max-w-sm">No messages in this channel yet. Type a message below or mention @Zervy to search trainers.</p>
                </div>
                <button type="button" onclick="insertAiMention()" class="bg-orange-50 hover:bg-orange-100 border border-orange-200 text-[#FE5E04] text-xs font-bold px-4 py-2 rounded-xl transition-colors inline-flex items-center gap-1.5 cursor-pointer">
                    <span class="material-symbols-outlined text-[15px]">smart_toy</span>
                    <span>Ask Zervy a Question</span>
                </button>
            </div>
        `;
        return;
    }

    stream.innerHTML = messages.map(msg => {
        const isSelf = msg.senderId === currentUserId;
        const isAI = msg.isAI || msg.senderRole === 'AI_AGENT';
        const canEdit = isSelf || isUserAdmin;

        if (isAI) {
            const tokenBadge = msg.tokenStats ? `<span class="inline-flex items-center gap-1 font-mono text-[9px] bg-orange-100 text-[#FE5E04] font-bold px-1.5 py-0.2 rounded">⚡ ${msg.tokenStats.totalTokens} Tokens</span>` : '';

            return `
                <div class="flex items-start gap-3 max-w-2xl bg-white border border-orange-200/80 rounded-3xl p-4 shadow-xs animate-fadeIn group">
                    <div class="w-8 h-8 rounded-xl bg-[#FE5E04] text-white flex items-center justify-center shrink-0 shadow-xs">
                        <span class="material-symbols-outlined text-base">smart_toy</span>
                    </div>
                    <div class="space-y-1.5 flex-1 min-w-0">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-1.5">
                                <span class="font-extrabold text-xs text-slate-900">Zervy (AI Assistant)</span>
                                <span class="text-[9px] font-black uppercase px-1.5 py-0.2 rounded bg-orange-100 text-[#FE5E04]">AI Engine</span>
                                ${tokenBadge}
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] text-slate-400">${escapeHtml(msg.timestamp.split(' ')[1] || '')}</span>
                                ${isUserAdmin ? `
                                    <button type="button" onclick="deleteMessage('${escapeHtml(msg.id)}')" class="opacity-0 group-hover:opacity-100 text-slate-400 hover:text-rose-600 transition-opacity text-xs" title="Delete AI message">
                                        <span class="material-symbols-outlined text-[15px]">delete</span>
                                    </button>
                                ` : ''}
                            </div>
                        </div>
                        <div class="text-xs text-slate-800 leading-relaxed whitespace-pre-line font-medium bg-slate-50/70 p-3 rounded-2xl border border-slate-100">
                            ${formatAiMessageText(msg.text)}
                        </div>
                        <div class="pt-1 flex items-center gap-2">
                            <a href="/admin/ai-assistant.php" class="text-[11px] font-bold text-[#FE5E04] hover:underline flex items-center gap-1">
                                Open in Zervy AI Center →
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
                        ${msg.isEdited ? `<span class="text-[9px] text-slate-400 italic">(edited)</span>` : ''}
                    </div>

                    <div class="p-3.5 rounded-2xl text-xs sm:text-sm leading-relaxed relative ${isSelf ? 'bg-[#0F172A] text-white rounded-tr-xs' : 'bg-white text-slate-800 border border-slate-200/90 rounded-tl-xs shadow-xs'}">
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

                    <!-- Message Actions: Edit, Delete, Ask Zervy -->
                    <div class="opacity-0 group-hover:opacity-100 transition-opacity flex items-center gap-2.5 ${isSelf ? 'justify-end' : 'justify-start'} text-[11px]">
                        ${canEdit ? `
                            <button type="button" onclick="openEditModal('${escapeHtml(msg.id)}', \`${escapeJsString(msg.text || '')}\`)" class="text-slate-400 hover:text-blue-600 flex items-center gap-1 cursor-pointer">
                                <span class="material-symbols-outlined text-[13px]">edit</span>
                                <span>Edit</span>
                            </button>
                            <button type="button" onclick="deleteMessage('${escapeHtml(msg.id)}')" class="text-slate-400 hover:text-rose-600 flex items-center gap-1 cursor-pointer">
                                <span class="material-symbols-outlined text-[13px]">delete</span>
                                <span>Delete</span>
                            </button>
                        ` : ''}

                        ${msg.text && !msg.text.includes('@') ? `
                            <button type="button" onclick="askAiOnMessage('${escapeHtml(msg.id)}')" class="font-bold text-slate-500 hover:text-[#FE5E04] flex items-center gap-1 cursor-pointer">
                                <span class="material-symbols-outlined text-[13px]">smart_toy</span>
                                <span>Ask Zervy</span>
                            </button>
                        ` : ''}
                    </div>
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
    input.value = '@Zervy ';
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

function openEditModal(msgId, currentText) {
    document.getElementById('editModalMsgId').value = msgId;
    document.getElementById('editModalTextInput').value = currentText;
    document.getElementById('editMessageModal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('editMessageModal').classList.add('hidden');
}

async function submitEditMessage(e) {
    e.preventDefault();
    const msgId = document.getElementById('editModalMsgId').value;
    const newText = document.getElementById('editModalTextInput').value.trim();

    if (!msgId || !newText) return;

    const formData = new FormData();
    formData.append('action', 'edit_message');
    formData.append('messageId', msgId);
    formData.append('text', newText);

    try {
        const response = await fetch('/actions/team-chat-api.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();
        if (data.success && data.allMessages) {
            closeEditModal();
            renderMessages(data.allMessages);
        } else {
            alert(data.message || 'Failed to edit message.');
        }
    } catch (e) {
        alert('Network error while editing message.');
    }
}

async function deleteMessage(msgId) {
    if (!confirm('Are you sure you want to delete this message?')) return;

    const formData = new FormData();
    formData.append('action', 'delete_message');
    formData.append('messageId', msgId);

    try {
        const response = await fetch('/actions/team-chat-api.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();
        if (data.success && data.allMessages) {
            renderMessages(data.allMessages);
        } else {
            alert(data.message || 'Failed to delete message.');
        }
    } catch (e) {
        alert('Network error while deleting message.');
    }
}

function confirmClearChat() {
    document.getElementById('clearChatModal').classList.remove('hidden');
}

function closeClearChatModal() {
    document.getElementById('clearChatModal').classList.add('hidden');
}

async function executeClearChat() {
    const btn = document.getElementById('confirmClearBtn');
    btn.disabled = true;
    btn.innerText = 'Clearing...';

    const formData = new FormData();
    formData.append('action', 'clear_chat');

    try {
        const response = await fetch('/actions/team-chat-api.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();
        if (data.success && data.allMessages !== undefined) {
            closeClearChatModal();
            renderMessages(data.allMessages);
        } else {
            alert(data.message || 'Failed to clear chat.');
        }
    } catch (e) {
        alert('Network error while clearing chat.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<span class="material-symbols-outlined text-[16px]">delete_sweep</span><span>Yes, Clear All</span>';
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
            alert(data.message || 'Could not process with Zervy.');
        }
    } catch (e) {
        alert('Network error while requesting Zervy AI matching.');
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

function escapeJsString(str) {
    if (!str) return '';
    return str.replace(/\\/g, '\\\\').replace(/`/g, '\\`').replace(/\$/g, '\\$');
}

// Initial load & Polling
loadChatMessages();
setInterval(loadChatMessages, 5000);
</script>

</main>
</div>
</body>
</html>

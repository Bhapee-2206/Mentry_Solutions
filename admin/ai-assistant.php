<?php
// admin/ai-assistant.php - Zervy: Dedicated AI Trainer Match Assistant & Token Calculator (Admin & Staff Only)
$pageTitle = "Zervy — Smart Trainer Discovery";
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/ai_agent.php';
require_once __DIR__ . '/includes/sidebar.php';

// Strict backend role enforcement
requireAdminOrStaff();
$user = getCurrentUser();
$tokenMetrics = AIAgent::getTokenMetrics();
?>

<div class="max-w-7xl mx-auto space-y-6">
    
    <!-- Top Header Banner with Live Token Calculator -->
    <div class="bg-gradient-to-r from-slate-900 via-slate-800 to-slate-900 text-white p-6 sm:p-8 rounded-3xl border border-slate-800 shadow-xl flex flex-col lg:flex-row items-start lg:items-center justify-between gap-6 relative overflow-hidden">
        <div class="relative z-10 space-y-2 max-w-2xl">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-[#FE5E04]/20 border border-[#FE5E04]/40 text-[#FE5E04] text-xs font-black tracking-wide">
                <span class="material-symbols-outlined text-sm animate-spin" style="animation-duration: 8s;">smart_toy</span>
                <span>ZERVY AI AGENT & TOKEN ENGINE</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-white">Zervy AI Assistant</h1>
            <p class="text-xs sm:text-sm text-slate-300 leading-relaxed">
                Analyze natural-language training requirements, search verified trainer resumes, and receive ranked, factually-grounded recommendations with real-time token tracking.
            </p>
        </div>

        <!-- Token Calculator & Action Controls -->
        <div class="relative z-10 flex flex-wrap items-center gap-3">
            
            <!-- Real-Time Token Calculator Pill -->
            <div class="bg-slate-800/90 border border-slate-700/80 rounded-2xl p-3 px-4 flex items-center gap-3.5 shadow-inner">
                <div class="w-9 h-9 rounded-xl bg-[#FE5E04]/20 text-[#FE5E04] flex items-center justify-center">
                    <span class="material-symbols-outlined text-lg">bolt</span>
                </div>
                <div>
                    <div class="flex items-center gap-1.5">
                        <span class="text-[10px] font-extrabold uppercase text-slate-400 tracking-wider">Token Calculator</span>
                        <span id="tokenModelBadge" class="text-[9px] font-black px-1.5 py-0.2 rounded bg-slate-700 text-slate-300">Gemini 1.5</span>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <span id="grandTotalTokensDisplay" class="text-sm font-black text-white"><?= number_format($tokenMetrics['grandTotalTokens'] ?? 0) ?></span>
                        <span class="text-[10px] text-emerald-400 font-bold" id="grandTotalCostDisplay">$<?= number_format($tokenMetrics['estimatedCostUsd'] ?? 0.0, 4) ?> est.</span>
                    </div>
                </div>
            </div>

            <button type="button" onclick="openStructuredRequirementModal()" class="bg-[#FE5E04] hover:bg-[#E04E00] text-white text-xs font-bold px-4 py-3 rounded-2xl transition-all shadow-md shadow-orange-500/20 flex items-center gap-2 cursor-pointer">
                <span class="material-symbols-outlined text-base">tune</span>
                Structured Matcher
            </button>
            <a href="/admin/team-chat.php" class="bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold px-4 py-3 rounded-2xl border border-slate-700 transition-all flex items-center gap-2">
                <span class="material-symbols-outlined text-base">forum</span>
                Team Chat
            </a>
        </div>

        <!-- Decorative background glow -->
        <div class="absolute right-0 top-0 w-96 h-96 bg-[#FE5E04]/10 rounded-full blur-3xl pointer-events-none"></div>
    </div>

    <!-- Main Requirement Input Canvas -->
    <div class="bg-white rounded-3xl border border-slate-200/90 p-6 shadow-card space-y-4">
        <div class="flex items-center justify-between">
            <label for="aiQueryInput" class="text-xs font-extrabold uppercase tracking-wider text-slate-700 flex items-center gap-2">
                <span class="material-symbols-outlined text-[#FE5E04] text-lg">search_spark</span>
                Ask Zervy / Describe Training Requirement
            </label>
            <div id="liveQueryTokensBadge" class="hidden text-[11px] bg-slate-100 border border-slate-200 text-slate-700 px-2.5 py-0.5 rounded-full font-bold">
                Last Query: <span id="lastQueryTokensCount" class="text-[#FE5E04]">0</span> tokens
            </div>
        </div>

        <div class="relative">
            <textarea id="aiQueryInput" rows="3" placeholder="e.g. Need a Python trainer for a 5-day corporate training program in Bangalore. Trainer should have experience with Python, Django, and REST APIs for enterprise delivery..." class="w-full bg-slate-50 border border-slate-200 rounded-2xl p-4 text-xs sm:text-sm text-slate-800 placeholder-slate-400 outline-none focus:border-[#FE5E04] focus:bg-white transition-all shadow-inner"></textarea>
            
            <div class="absolute right-3 bottom-3 flex items-center gap-2">
                <button type="button" id="searchAiBtn" onclick="runAiTrainerSearch()" class="bg-[#FE5E04] hover:bg-[#E04E00] text-white text-xs font-bold px-5 py-2.5 rounded-xl transition-all shadow-md flex items-center gap-2 cursor-pointer disabled:opacity-50">
                    <span class="material-symbols-outlined text-base">psychology</span>
                    <span>Ask Zervy</span>
                </button>
            </div>
        </div>

        <!-- Quick Prompt Starter Pills -->
        <div class="space-y-2 pt-1">
            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Quick Starter Prompts:</span>
            <div class="flex flex-wrap gap-2 text-xs">
                <button type="button" onclick="setQueryAndRun('Need a Python trainer for a 5-day corporate training program in Bangalore with experience in Python, Django, and REST APIs.')" class="bg-slate-50 hover:bg-orange-50 hover:text-[#FE5E04] hover:border-orange-200 border border-slate-200 text-slate-600 px-3 py-1.5 rounded-xl transition-all text-left">
                    🐍 Python & Django Corporate in Bangalore
                </button>
                <button type="button" onclick="setQueryAndRun('Looking for a Full-Stack React, Node.js, and TypeScript architect for on-campus bootcamp in Chennai.')" class="bg-slate-50 hover:bg-orange-50 hover:text-[#FE5E04] hover:border-orange-200 border border-slate-200 text-slate-600 px-3 py-1.5 rounded-xl transition-all text-left">
                    ⚛️ Full-Stack React & Node in Chennai
                </button>
                <button type="button" onclick="setQueryAndRun('Require Senior Cloud & DevOps Trainer for AWS, Docker, Kubernetes, and CI/CD pipelines.')" class="bg-slate-50 hover:bg-orange-50 hover:text-[#FE5E04] hover:border-orange-200 border border-slate-200 text-slate-600 px-3 py-1.5 rounded-xl transition-all text-left">
                    ☁️ AWS, Docker & Kubernetes DevOps
                </button>
                <button type="button" onclick="setQueryAndRun('Find Data Science and Generative AI faculty for Machine Learning, Deep Learning, and LLM workshops.')" class="bg-slate-50 hover:bg-orange-50 hover:text-[#FE5E04] hover:border-orange-200 border border-slate-200 text-slate-600 px-3 py-1.5 rounded-xl transition-all text-left">
                    🤖 GenAI, Deep Learning & LLM Specialist
                </button>
            </div>
        </div>
    </div>

    <!-- Active Requirement Breakdown & Token Stats Strip -->
    <div id="aiRequirementContainer" class="hidden space-y-3"></div>

    <!-- Comparison Toolbar (Floats when 2+ trainers are selected) -->
    <div id="compareToolbar" class="hidden sticky top-20 z-20 bg-slate-900 text-white px-6 py-3.5 rounded-2xl shadow-xl flex items-center justify-between border border-slate-700 animate-fadeIn">
        <div class="flex items-center gap-3">
            <span class="material-symbols-outlined text-[#FE5E04]">compare_arrows</span>
            <div>
                <p class="text-xs font-bold text-white"><span id="selectedCompareCount">0</span> Trainers Selected for Comparison</p>
                <p class="text-[10px] text-slate-400">Generate side-by-side factual matrix</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" onclick="clearSelectedTrainers()" class="text-xs text-slate-400 hover:text-white px-3 py-1">Clear</button>
            <button type="button" onclick="runTrainerComparison()" class="bg-[#FE5E04] hover:bg-[#E04E00] text-white text-xs font-bold px-4 py-2 rounded-xl transition-all shadow-md flex items-center gap-1.5">
                <span class="material-symbols-outlined text-[16px]">view_column</span>
                Compare Selected
            </button>
        </div>
    </div>

    <!-- Results Section -->
    <div id="resultsSection" class="space-y-4">
        <!-- Empty Initial State -->
        <div id="emptyState" class="bg-white rounded-3xl border border-slate-200/90 p-12 text-center space-y-4 shadow-card">
            <div class="w-16 h-16 rounded-2xl bg-orange-50 border border-orange-200 text-[#FE5E04] flex items-center justify-center mx-auto shadow-inner">
                <span class="material-symbols-outlined text-3xl">smart_toy</span>
            </div>
            <div class="space-y-1 max-w-md mx-auto">
                <h3 class="font-extrabold text-base text-slate-900">How can Zervy help you discover the right trainer?</h3>
                <p class="text-xs text-slate-500 leading-relaxed">
                    Enter training parameters above or click any starter prompt. Zervy analyzes verified profile skills, parsed resumes, and past campus workshop delivery.
                </p>
            </div>
        </div>

        <!-- Loading State -->
        <div id="loadingState" class="hidden bg-white rounded-3xl border border-slate-200/90 p-12 text-center space-y-4 shadow-card">
            <div class="w-12 h-12 rounded-full border-4 border-slate-200 border-t-[#FE5E04] animate-spin mx-auto"></div>
            <p class="text-xs font-bold text-slate-700">Zervy is analyzing requirement & evaluating candidate database...</p>
        </div>

        <!-- Dynamic Results Grid -->
        <div id="resultsGrid" class="hidden grid md:grid-cols-2 gap-4"></div>
    </div>
</div>

<!-- STRUCTURED REQUIREMENT MODAL -->
<div id="structuredModal" class="hidden fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl border border-slate-200 shadow-2xl max-w-xl w-full p-6 space-y-5 animate-fadeIn max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-[#FE5E04] text-xl">tune</span>
                <h3 class="font-extrabold text-base text-slate-900">Structured Requirement Matcher</h3>
            </div>
            <button type="button" onclick="closeStructuredRequirementModal()" class="text-slate-400 hover:text-slate-600 text-lg leading-none cursor-pointer">✕</button>
        </div>

        <form id="structuredForm" onsubmit="submitStructuredRequirement(event)" class="space-y-3.5">
            <div>
                <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Program / Workshop Title *</label>
                <input type="text" id="str_title" required placeholder="e.g. 5-Day Python & Django Corporate Training" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-xs outline-none focus:border-[#FE5E04]">
            </div>

            <div class="grid sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Primary Domain *</label>
                    <select id="str_domain" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-xs outline-none focus:border-[#FE5E04]">
                        <option value="Programming">Programming & Frameworks</option>
                        <option value="Cloud & DevOps">Cloud & DevOps</option>
                        <option value="Data Science & AI">Data Science & AI / ML</option>
                        <option value="Full-Stack Development">Full-Stack Web & Mobile</option>
                        <option value="Cybersecurity">Cybersecurity & Networking</option>
                        <option value="VLSI & Embedded">VLSI & Embedded Systems</option>
                        <option value="Aptitude & Soft Skills">Aptitude & Soft Skills</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Target Location</label>
                    <input type="text" id="str_location" placeholder="e.g. Bangalore or Any" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-xs outline-none focus:border-[#FE5E04]">
                </div>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Required Skills & Tools (Comma Separated) *</label>
                <input type="text" id="str_skills" required placeholder="e.g. Python, Django, REST API, PostgreSQL, Docker" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-xs outline-none focus:border-[#FE5E04]">
            </div>

            <div class="grid sm:grid-cols-3 gap-3">
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Delivery Mode</label>
                    <select id="str_mode" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-xs outline-none focus:border-[#FE5E04]">
                        <option value="ALL">Any Mode</option>
                        <option value="OFFLINE">Offline (On-Campus)</option>
                        <option value="ONLINE">Online (Virtual)</option>
                        <option value="HYBRID">Hybrid</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Min Experience</label>
                    <select id="str_exp" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-xs outline-none focus:border-[#FE5E04]">
                        <option value="0">Any Experience</option>
                        <option value="3">3+ Years</option>
                        <option value="5" selected>5+ Years</option>
                        <option value="8">8+ Years</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Duration</label>
                    <input type="text" id="str_duration" placeholder="e.g. 5 Days" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-xs outline-none focus:border-[#FE5E04]">
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                <button type="button" onclick="closeStructuredRequirementModal()" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100 transition-colors cursor-pointer">Cancel</button>
                <button type="submit" class="bg-[#FE5E04] hover:bg-[#E04E00] text-white font-bold text-xs px-5 py-2.5 rounded-xl transition-all shadow-md cursor-pointer">
                    Match Suitable Trainers
                </button>
            </div>
        </form>
    </div>
</div>

<!-- TRAINER COMPARISON MODAL -->
<div id="compareModal" class="hidden fixed inset-0 z-50 bg-slate-900/70 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl border border-slate-200 shadow-2xl max-w-4xl w-full p-6 space-y-5 animate-fadeIn max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-[#FE5E04] text-2xl">compare_arrows</span>
                <div>
                    <h3 class="font-extrabold text-base text-slate-900">Side-by-Side Trainer Comparison</h3>
                    <p class="text-[11px] text-slate-500">Zervy AI factual breakdown against active requirement</p>
                </div>
            </div>
            <button type="button" onclick="closeCompareModal()" class="text-slate-400 hover:text-slate-600 text-lg leading-none cursor-pointer">✕</button>
        </div>

        <div id="compareContent" class="space-y-4">
            <div class="p-8 text-center text-xs text-slate-400">Loading comparison...</div>
        </div>
    </div>
</div>

<script>
let selectedTrainers = new Set();
let lastSearchResult = null;

function setQueryAndRun(text) {
    document.getElementById('aiQueryInput').value = text;
    runAiTrainerSearch();
}

async function runAiTrainerSearch() {
    const query = document.getElementById('aiQueryInput').value.trim();
    if (!query) {
        alert('Please enter a requirement or question for Zervy.');
        return;
    }

    const btn = document.getElementById('searchAiBtn');
    btn.disabled = true;
    document.getElementById('emptyState').classList.add('hidden');
    document.getElementById('resultsGrid').classList.add('hidden');
    document.getElementById('aiRequirementContainer').classList.add('hidden');
    document.getElementById('loadingState').classList.remove('hidden');

    try {
        const response = await fetch('/actions/ai-match-query.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ query: query })
        });

        const res = await response.json();
        document.getElementById('loadingState').classList.add('hidden');

        if (res.success) {
            // Update Token Calculator stats
            if (res.tokenStats) {
                updateTokenUI(res.tokenStats);
            }

            if (res.isConversational) {
                renderConversationalResult(res.conversationalMessage);
            } else if (res.data) {
                lastSearchResult = res.data;
                renderAiResults(res.data, res.source, res.tokenStats);
            }
        } else {
            alert(res.message || res.error || 'Failed to process AI search.');
            document.getElementById('emptyState').classList.remove('hidden');
        }
    } catch (e) {
        document.getElementById('loadingState').classList.add('hidden');
        document.getElementById('emptyState').classList.remove('hidden');
        alert('Network error while contacting Zervy AI Assistant.');
    } finally {
        btn.disabled = false;
    }
}

function updateTokenUI(tokens) {
    if (!tokens) return;
    document.getElementById('liveQueryTokensBadge').classList.remove('hidden');
    document.getElementById('lastQueryTokensCount').innerText = tokens.totalTokens;
    if (tokens.cumulativeTokens) {
        document.getElementById('grandTotalTokensDisplay').innerText = Number(tokens.cumulativeTokens).toLocaleString();
    }
    if (tokens.cumulativeCostUsd) {
        document.getElementById('grandTotalCostDisplay').innerText = tokens.cumulativeCostUsd + ' est.';
    }
}

function renderConversationalResult(message) {
    const reqContainer = document.getElementById('aiRequirementContainer');
    reqContainer.innerHTML = `
        <div class="bg-orange-50 border border-orange-200/90 rounded-2xl p-4 flex items-start gap-3 text-xs animate-fadeIn">
            <div class="w-8 h-8 rounded-xl bg-[#FE5E04] text-white flex items-center justify-center shrink-0 shadow-xs">
                <span class="material-symbols-outlined text-base">smart_toy</span>
            </div>
            <div class="space-y-1 flex-1">
                <span class="font-extrabold text-slate-900 block">Zervy Assistant</span>
                <p class="text-slate-700 leading-relaxed whitespace-pre-line font-medium">${formatAiMessageText(message)}</p>
            </div>
        </div>
    `;
    reqContainer.classList.remove('hidden');
    document.getElementById('resultsGrid').classList.add('hidden');
}

function renderAiResults(data, source, tokenStats) {
    const reqContainer = document.getElementById('aiRequirementContainer');
    const req = data.understoodRequirement || {};

    let tokenPill = tokenStats ? `<span class="inline-flex items-center gap-1 font-mono text-[10px] bg-slate-200/80 text-slate-700 px-2 py-0.5 rounded font-bold">⚡ ${tokenStats.totalTokens} Tokens (${tokenStats.promptTokens} in / ${tokenStats.completionTokens} out)</span>` : '';

    let reqHtml = `
        <div class="bg-slate-50 border border-slate-200/90 rounded-2xl p-4 flex flex-wrap items-center justify-between gap-3 text-xs">
            <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                <span class="font-extrabold text-slate-900">Understood Requirement:</span>
                <span class="text-slate-600">${escapeHtml(req.topic || 'General')}</span>
                ${req.location ? `<span class="bg-white border border-slate-200 px-2 py-0.5 rounded-md font-bold text-slate-700">${escapeHtml(req.location)}</span>` : ''}
                ${req.mode ? `<span class="bg-blue-50 text-blue-700 px-2 py-0.5 rounded-md font-bold">${escapeHtml(req.mode)}</span>` : ''}
            </div>
            <div class="flex items-center gap-2">
                ${tokenPill}
                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">${source === 'gemini-ai' ? 'Google Gemini 1.5' : 'Deterministic Match Layer'}</span>
            </div>
        </div>
    `;

    if (data.clarification) {
        reqHtml += `
            <div class="bg-amber-50 border border-amber-200 rounded-2xl p-3.5 flex items-center gap-2 text-xs font-bold text-amber-800 animate-fadeIn">
                <span class="material-symbols-outlined text-amber-600 text-lg">help</span>
                <span>${escapeHtml(data.clarification)}</span>
            </div>
        `;
    }

    reqContainer.innerHTML = reqHtml;
    reqContainer.classList.remove('hidden');

    const grid = document.getElementById('resultsGrid');
    const matches = data.topMatches || [];

    if (matches.length === 0) {
        grid.innerHTML = `
            <div class="col-span-2 p-12 bg-white rounded-3xl border border-slate-200 text-center text-xs text-slate-500">
                No matching trainers found. Try broadening the skills or location criteria.
            </div>
        `;
    } else {
        grid.innerHTML = matches.map((m, idx) => {
            const isChecked = selectedTrainers.has(m.trainerId);
            const scoreColor = m.matchScore >= 90 ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : (m.matchScore >= 75 ? 'bg-blue-50 text-blue-800 border-blue-200' : 'bg-amber-50 text-amber-800 border-amber-200');

            return `
                <div class="bg-white rounded-3xl border border-slate-200/90 shadow-card p-6 space-y-4 hover:shadow-card-hover transition-all flex flex-col justify-between group">
                    <div class="space-y-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <span class="text-[10px] font-black uppercase text-slate-400">#${idx + 1} Best Match</span>
                                <h3 class="font-extrabold text-base text-slate-900">${escapeHtml(m.name)}</h3>
                                <p class="text-xs text-slate-500 font-medium">${escapeHtml(m.headline || 'Verified Trainer')}</p>
                            </div>
                            <div class="text-right">
                                <span class="inline-flex items-center gap-1 text-xs font-black px-2.5 py-1 rounded-full border ${scoreColor}">
                                    ${m.matchScore}% Match
                                </span>
                                <span class="text-[10px] block text-slate-400 mt-0.5 font-bold">${escapeHtml(m.confidence || 'High')} Confidence</span>
                            </div>
                        </div>

                        <!-- Skills matched tags -->
                        <div class="space-y-1">
                            <span class="text-[10px] font-bold uppercase text-slate-400 tracking-wider">Matching Skills:</span>
                            <div class="flex flex-wrap gap-1.5">
                                ${(m.matchingSkills || []).map(sk => `<span class="bg-emerald-50 text-emerald-800 border border-emerald-200 text-[11px] font-bold px-2 py-0.5 rounded-lg">${escapeHtml(sk)}</span>`).join('')}
                            </div>
                        </div>

                        <!-- Experience & History -->
                        <div class="grid grid-cols-2 gap-2 pt-2 border-t border-slate-100 text-[11px] text-slate-600">
                            <div>
                                <span class="text-slate-400 block font-bold">Experience:</span>
                                <span>${escapeHtml(String(m.relevantExperienceYears || '5'))}+ Years</span>
                            </div>
                            <div>
                                <span class="text-slate-400 block font-bold">Track Record:</span>
                                <span class="truncate block">${escapeHtml(m.relevantTrainings || '10+ Programs')}</span>
                            </div>
                        </div>

                        <!-- Why Recommended Factual Box -->
                        <div class="bg-orange-50/70 border border-orange-200/80 rounded-2xl p-3 space-y-1">
                            <div class="flex items-center gap-1.5 text-[#FE5E04] text-[11px] font-bold uppercase tracking-wide">
                                <span class="material-symbols-outlined text-[15px]">verified</span>
                                <span>Why Recommended:</span>
                            </div>
                            <p class="text-xs text-slate-700 leading-relaxed font-medium">
                                ${escapeHtml(m.whyRecommended || 'Verified domain expert matching required skills.')}
                            </p>
                        </div>
                    </div>

                    <!-- Card Actions -->
                    <div class="pt-3 border-t border-slate-100 flex items-center justify-between gap-2">
                        <label class="flex items-center gap-1.5 text-xs font-bold text-slate-700 cursor-pointer select-none">
                            <input type="checkbox" onchange="toggleSelectTrainer('${escapeHtml(m.trainerId)}', this.checked)" ${isChecked ? 'checked' : ''} class="w-4 h-4 rounded text-[#FE5E04] focus:ring-[#FE5E04]">
                            <span>Compare</span>
                        </label>

                        <div class="flex items-center gap-2">
                            <a href="/admin/trainer-view.php?id=${encodeURIComponent(m.trainerId)}" class="bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold text-xs px-3 py-1.5 rounded-xl transition-colors">
                                View Profile
                            </a>
                            <button type="button" onclick="alert('Resume download ready for ${escapeHtml(m.name)}.')" class="bg-[#FE5E04] hover:bg-[#E04E00] text-white font-bold text-xs px-3 py-1.5 rounded-xl transition-all shadow-xs">
                                Resume
                            </button>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }

    grid.classList.remove('hidden');
}

function toggleSelectTrainer(id, checked) {
    if (checked) {
        selectedTrainers.add(id);
    } else {
        selectedTrainers.delete(id);
    }
    updateCompareToolbar();
}

function clearSelectedTrainers() {
    selectedTrainers.clear();
    document.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
    updateCompareToolbar();
}

function updateCompareToolbar() {
    const toolbar = document.getElementById('compareToolbar');
    const countEl = document.getElementById('selectedCompareCount');
    countEl.innerText = selectedTrainers.size;

    if (selectedTrainers.size >= 2) {
        toolbar.classList.remove('hidden');
    } else {
        toolbar.classList.add('hidden');
    }
}

async function runTrainerComparison() {
    const ids = Array.from(selectedTrainers);
    const query = document.getElementById('aiQueryInput').value.trim() || 'General Technical Training';

    document.getElementById('compareModal').classList.remove('hidden');
    const content = document.getElementById('compareContent');
    content.innerHTML = '<div class="p-12 text-center text-xs text-slate-500"><div class="w-8 h-8 rounded-full border-2 border-slate-300 border-t-[#FE5E04] animate-spin mx-auto mb-2"></div>Generating Zervy AI factual comparison matrix...</div>';

    try {
        const response = await fetch('/actions/compare-trainers-api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ trainerIds: ids, requirementText: query })
        });

        const res = await response.json();
        if (res.success && res.comparison) {
            if (res.tokenStats) {
                updateTokenUI(res.tokenStats);
            }

            const cmp = res.comparison;
            const matrix = cmp.comparisonMatrix || [];
            const trainers = res.trainers || [];

            let html = `
                <div class="bg-orange-50 border border-orange-200 rounded-2xl p-4 space-y-1">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-[#FE5E04]">Comparison Summary</span>
                    <p class="text-xs text-slate-700 leading-relaxed font-medium">${escapeHtml(cmp.summary || '')}</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left border-collapse">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50">
                                <th class="p-3 font-bold text-slate-500 uppercase tracking-wider">Evaluation Category</th>
                                ${trainers.map(t => `<th class="p-3 font-extrabold text-slate-900">${escapeHtml(t.name)}</th>`).join('')}
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            ${matrix.map(row => `
                                <tr>
                                    <td class="p-3 font-bold text-slate-700 bg-slate-50/50">${escapeHtml(row.category)}</td>
                                    ${trainers.map(t => {
                                        const val = row.values[t.name] || row.values[t.id] || row.values[t._id] || 'Aligned';
                                        return `<td class="p-3 text-slate-600 font-medium">${escapeHtml(String(val))}</td>`;
                                    }).join('')}
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `;

            if (cmp.recommendedChoice) {
                html += `
                    <div class="bg-emerald-50 border border-emerald-200 rounded-2xl p-4 flex items-start gap-3">
                        <span class="material-symbols-outlined text-emerald-600 text-xl">recommend</span>
                        <div class="space-y-0.5">
                            <span class="text-[11px] font-black uppercase text-emerald-800">Zervy Recommended Choice: ${escapeHtml(cmp.recommendedChoice.trainerName || '')}</span>
                            <p class="text-xs text-slate-700 leading-relaxed font-medium">${escapeHtml(cmp.recommendedChoice.reason || '')}</p>
                        </div>
                    </div>
                `;
            }

            content.innerHTML = html;
        } else {
            content.innerHTML = `<div class="p-8 text-center text-xs text-rose-600 font-bold">${escapeHtml(res.message || 'Comparison failed.')}</div>`;
        }
    } catch (e) {
        content.innerHTML = '<div class="p-8 text-center text-xs text-rose-600 font-bold">Network error while comparing trainers.</div>';
    }
}

function openStructuredRequirementModal() {
    document.getElementById('structuredModal').classList.remove('hidden');
}

function closeStructuredRequirementModal() {
    document.getElementById('structuredModal').classList.add('hidden');
}

function closeCompareModal() {
    document.getElementById('compareModal').classList.add('hidden');
}

function submitStructuredRequirement(e) {
    e.preventDefault();
    const title = document.getElementById('str_title').value;
    const domain = document.getElementById('str_domain').value;
    const location = document.getElementById('str_location').value;
    const skills = document.getElementById('str_skills').value;
    const mode = document.getElementById('str_mode').value;
    const exp = document.getElementById('str_exp').value;
    const duration = document.getElementById('str_duration').value;

    const formattedQuery = `Requirement for ${title}. Domain: ${domain}. Required Skills: ${skills}. Location: ${location || 'Any'}. Delivery Mode: ${mode}. Minimum Experience: ${exp} years. Duration: ${duration || 'Standard'}.`;

    closeStructuredRequirementModal();
    setQueryAndRun(formattedQuery);
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
</script>

</main>
</div>
</body>
</html>

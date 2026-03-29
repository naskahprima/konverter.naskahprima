<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between flex-wrap gap-2">
            <div>
                <h2 class="text-xl font-bold text-gray-900" id="statusLabel">{{ $conversion->statusLabel() }}</h2>
                <p class="text-sm text-gray-400 mt-0.5">Konversi #{{ $conversion->id }} · {{ $conversion->created_at->format('d M Y, H:i') }}</p>
            </div>
            @if($conversion->status === 'completed')
                <a href="{{ route('conversions.result', $conversion->id) }}"
                    class="inline-flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold px-4 py-2 rounded-xl transition">
                    ⬇️ Download Jurnal
                </a>
            @endif
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

            {{-- Processing Bar --}}
            <div id="processingBar" class="{{ in_array($conversion->status, ['pending','analyzing','converting']) ? '' : 'hidden' }}">
                <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                    <div class="h-full bg-blue-500 rounded-full animate-pulse" style="width:100%;background:linear-gradient(90deg,#3b82f6,#60a5fa,#3b82f6);background-size:200%;animation:shimmer 1.5s infinite;"></div>
                </div>
                <style>@keyframes shimmer{0%{background-position:200%}100%{background-position:-200%}}</style>
            </div>

            {{-- ✅ COMPLETED --}}
            @if($conversion->status === 'completed')
                <div class="bg-green-50 border border-green-200 rounded-2xl p-6 text-center">
                    <div class="text-4xl mb-2">🎉</div>
                    <h3 class="font-bold text-green-800 text-lg mb-1">Jurnal Siap Didownload!</h3>
                    <p class="text-green-700 text-sm mb-4">AI sudah selesai mengkonversi naskahmu.</p>
                    <a href="{{ route('conversions.result', $conversion->id) }}"
                        class="inline-flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white font-bold px-6 py-2.5 rounded-xl transition text-sm">
                        ⬇️ Download Jurnal (.docx)
                    </a>
                </div>
            @endif

            {{-- ❌ FAILED --}}
            @if($conversion->status === 'failed')
                <div class="bg-red-50 border border-red-200 rounded-2xl p-5">
                    <p class="font-semibold text-red-800 mb-1">❌ Proses Gagal</p>
                    <p class="text-red-700 text-sm mb-3">{{ $conversion->error_message }}</p>
                    <a href="{{ route('conversions.create') }}" class="inline-flex items-center gap-1 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold px-4 py-2 rounded-xl transition">
                        🔄 Coba Lagi
                    </a>
                </div>
            @endif

            {{-- 🚫 REJECTED (scope mismatch) --}}
            @if($conversion->status === 'rejected')
                @php
                    $scopePct      = $conversion->getScopeMatchPercentage();
                    $alternatives  = $conversion->getAlternativeJournals();
                    $rejReason     = $conversion->getRejectionReason();
                @endphp
                <div class="bg-red-50 border-2 border-red-200 rounded-2xl overflow-hidden">
                    {{-- Header --}}
                    <div class="bg-red-600 px-6 py-4 text-white">
                        <div class="flex items-center gap-3">
                            <span class="text-3xl">🚫</span>
                            <div>
                                <h3 class="font-bold text-lg">Naskah Tidak Sesuai Scope Jurnal</h3>
                                <p class="text-red-100 text-sm">Tingkat kecocokan: <strong>{{ $scopePct }}%</strong> (minimum: 70%)</p>
                            </div>
                        </div>
                    </div>

                    {{-- Progress bar scope --}}
                    <div class="px-6 pt-4">
                        <div class="flex items-center justify-between text-xs text-gray-500 mb-1">
                            <span>Scope match</span>
                            <span class="font-semibold text-red-600">{{ $scopePct }}% / 70% minimum</span>
                        </div>
                        <div class="h-2.5 bg-gray-200 rounded-full overflow-hidden">
                            <div class="h-full rounded-full transition-all" style="width:{{ min($scopePct, 100) }}%; background: {{ $scopePct < 50 ? '#ef4444' : '#f97316' }}"></div>
                        </div>
                        <div class="flex justify-end mt-0.5">
                            <div class="w-px h-3 bg-gray-400" style="margin-right: {{ 100 - 70 }}%"></div>
                        </div>
                    </div>

                    {{-- Reason --}}
                    <div class="px-6 py-4">
                        <h4 class="font-semibold text-gray-900 mb-2 text-sm">📋 Alasan Penolakan</h4>
                        <p class="text-sm text-gray-700 leading-relaxed">{{ $rejReason }}</p>
                    </div>

                    {{-- Token refund notice --}}
                    <div class="mx-6 mb-4 bg-green-50 border border-green-200 rounded-xl px-4 py-3 flex items-center gap-2">
                        <span class="text-green-600">🪙</span>
                        <span class="text-xs text-green-800 font-medium">Token kamu sudah dikembalikan secara otomatis.</span>
                    </div>

                    {{-- Alternative journals --}}
                    @if(!empty($alternatives))
                        <div class="px-6 pb-4">
                            <h4 class="font-semibold text-gray-900 mb-3 text-sm">💡 Jurnal yang Lebih Cocok</h4>
                            <div class="space-y-2">
                                @foreach($alternatives as $i => $alt)
                                    @php
                                        $name   = is_array($alt) ? ($alt['name'] ?? $alt['journal'] ?? "Jurnal ".($i+1)) : $alt;
                                        $reason = is_array($alt) ? ($alt['reason'] ?? '') : '';
                                    @endphp
                                    <div class="flex items-start gap-3 bg-white border border-gray-100 rounded-xl px-4 py-3">
                                        <span class="w-6 h-6 rounded-full bg-blue-100 text-blue-700 text-xs font-bold flex items-center justify-center flex-shrink-0 mt-0.5">{{ $i+1 }}</span>
                                        <div>
                                            <p class="text-sm font-semibold text-gray-900">{{ $name }}</p>
                                            @if($reason)<p class="text-xs text-gray-500 mt-0.5">{{ $reason }}</p>@endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- CTA --}}
                    <div class="px-6 pb-6 flex gap-3">
                        <a href="{{ route('conversions.create') }}"
                            class="flex-1 text-center bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 rounded-xl text-sm transition">
                            🔄 Coba Jurnal Lain
                        </a>
                        <a href="{{ route('conversions.index') }}"
                            class="flex-1 text-center bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-2.5 rounded-xl text-sm transition">
                            📋 Riwayat
                        </a>
                    </div>
                </div>
            @endif

            {{-- Chat Log --}}
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="px-5 py-3.5 border-b border-gray-50 flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-blue-500 animate-pulse {{ in_array($conversion->status, ['analyzing','converting']) ? '' : 'hidden' }}" id="liveIndicator"></span>
                    <h3 class="font-semibold text-sm text-gray-700">💬 Log Proses AI</h3>
                </div>
                <div class="p-4 space-y-3 max-h-96 overflow-y-auto" id="chatMessages">
                    @foreach($conversion->messages as $msg)
                        @include('conversions._message', ['msg' => $msg])
                    @endforeach

                    @if($conversion->isProcessing())
                        <div id="typingIndicator" class="flex items-start gap-2">
                            <span class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-sm flex-shrink-0">🤖</span>
                            <div class="bg-gray-50 rounded-2xl rounded-tl-none px-4 py-2.5 text-sm text-gray-500">
                                <span class="inline-flex gap-1"><span class="animate-bounce" style="animation-delay:0ms">⏳</span> AI sedang bekerja...</span>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Fallback: Author Guide --}}
            <div id="authorGuideFallback" class="{{ $conversion->author_guide_fallback && in_array($conversion->status,['waiting_fallback','awaiting_qa']) ? '' : 'hidden' }} bg-amber-50 border border-amber-200 rounded-2xl p-5">
                <h3 class="font-semibold text-amber-900 mb-3">📤 Upload Author Guide Manual</h3>
                <form id="fallbackGuideForm" class="space-y-3">
                    @csrf
                    <label class="flex flex-col items-center border-2 border-dashed border-amber-300 rounded-xl p-5 text-center cursor-pointer hover:bg-amber-100 transition">
                        <input type="file" name="fallback_files[]" id="guideFileInput" accept=".pdf,.doc,.docx" class="sr-only">
                        <span class="text-2xl mb-1">📄</span>
                        <span class="text-sm font-medium text-amber-800">Upload Author Guide (PDF/DOC/DOCX)</span>
                        <span id="guideFileName" class="text-xs text-amber-600 mt-1"></span>
                    </label>
                    <button type="submit" class="w-full bg-amber-500 hover:bg-amber-600 text-white font-semibold py-2.5 rounded-xl text-sm transition">
                        ▶️ Lanjutkan Analisis
                    </button>
                </form>
            </div>

            {{-- Fallback: Archive --}}
            <div id="archiveFallback" class="{{ $conversion->archive_fallback && !$conversion->author_guide_fallback && in_array($conversion->status,['waiting_fallback','awaiting_qa']) ? '' : 'hidden' }} bg-amber-50 border border-amber-200 rounded-2xl p-5">
                <h3 class="font-semibold text-amber-900 mb-1">📤 Upload Contoh Artikel Lolos</h3>
                <p class="text-sm text-amber-700 mb-3">Upload 3–5 PDF artikel yang sudah lolos di jurnal target ini.</p>
                <form id="fallbackArchiveForm" class="space-y-3">
                    @csrf
                    <label class="flex flex-col items-center border-2 border-dashed border-amber-300 rounded-xl p-5 text-center cursor-pointer hover:bg-amber-100 transition">
                        <input type="file" name="fallback_files[]" id="archiveFileInput" accept=".pdf" multiple class="sr-only">
                        <span class="text-2xl mb-1">📚</span>
                        <span class="text-sm font-medium text-amber-800">Upload artikel-artikel lolos (bisa multiple)</span>
                        <span id="archiveFileNames" class="text-xs text-amber-600 mt-1"></span>
                    </label>
                    <div class="flex gap-2">
                        <button type="submit" class="flex-1 bg-amber-500 hover:bg-amber-600 text-white font-semibold py-2.5 rounded-xl text-sm transition">▶️ Lanjutkan</button>
                        <button type="button" onclick="skipArchive()" class="flex-1 bg-white border border-amber-300 text-amber-700 font-semibold py-2.5 rounded-xl text-sm hover:bg-amber-50 transition">Lewati →</button>
                    </div>
                </form>
            </div>

            {{-- ✨ Q&A Section — Modern UI --}}
            <div id="qaSection" class="{{ $conversion->status === 'awaiting_qa' ? '' : 'hidden' }}">

                {{-- Scope match badge --}}
                @php
                    $pct = $conversion->getScopeMatchPercentage();
                    $scopeColor = $pct >= 75 ? 'green' : 'amber';
                @endphp
                <div class="bg-{{ $scopeColor }}-50 border border-{{ $scopeColor }}-200 rounded-xl px-4 py-3 flex items-center gap-3 mb-4">
                    <span class="text-2xl">{{ $pct >= 75 ? '✅' : '⚠️' }}</span>
                    <div class="flex-1">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-sm font-semibold text-{{ $scopeColor }}-800">Scope Match</span>
                            <span class="text-sm font-bold text-{{ $scopeColor }}-700">{{ $pct }}%</span>
                        </div>
                        <div class="h-1.5 bg-{{ $scopeColor }}-200 rounded-full overflow-hidden">
                            <div class="h-full bg-{{ $scopeColor }}-500 rounded-full" style="width:{{ min($pct, 100) }}%"></div>
                        </div>
                    </div>
                </div>

                {{-- Title Picker --}}
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden mb-4">
                    <div class="px-5 py-4 border-b border-gray-50">
                        <h3 class="font-semibold text-gray-900">🎯 Pilih Judul Artikel</h3>
                        <p class="text-xs text-gray-400 mt-0.5">Judul sudah disesuaikan dengan Author Guide jurnal target</p>
                    </div>
                    <div class="p-4 space-y-2" id="titleOptions">
                        @foreach($conversion->title_recommendations ?? [] as $i => $title)
                            <label class="title-option flex gap-3 p-4 border-2 {{ $i === 0 ? 'border-blue-500 bg-blue-50' : 'border-gray-100 bg-gray-50' }} rounded-xl cursor-pointer hover:border-blue-400 hover:bg-blue-50 transition group">
                                <div class="flex-shrink-0 mt-0.5">
                                    <div class="w-5 h-5 rounded-full border-2 {{ $i === 0 ? 'border-blue-500 bg-blue-500' : 'border-gray-300 group-hover:border-blue-400' }} flex items-center justify-center transition">
                                        <div class="w-2 h-2 rounded-full bg-white {{ $i === 0 ? 'opacity-100' : 'opacity-0' }} title-dot transition"></div>
                                    </div>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <input type="radio" name="selected_title" value="{{ $title['title'] }}" {{ $i === 0 ? 'checked' : '' }} class="sr-only">
                                    <p class="text-sm font-semibold text-gray-900 leading-snug">{{ $title['title'] }}</p>
                                    <p class="text-xs text-gray-500 mt-1">💡 {{ $title['reason'] }}</p>
                                </div>
                            </label>
                        @endforeach
                    </div>
                    {{-- Custom title --}}
                    <div class="px-4 pb-4">
                        <div class="border border-dashed border-gray-200 rounded-xl px-4 py-3">
                            <label class="block text-xs font-medium text-gray-500 mb-1">✏️ Atau tulis judul sendiri</label>
                            <input type="text" id="customTitle"
                                class="w-full text-sm bg-transparent outline-none text-gray-800 placeholder-gray-300"
                                placeholder="Tulis judul kustommu di sini...">
                        </div>
                    </div>
                </div>

                {{-- Smart Q&A --}}
                @if(!empty($conversion->qa_questions))
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden mb-4">
                        <div class="px-5 py-4 border-b border-gray-50">
                            <h3 class="font-semibold text-gray-900">💬 Pertanyaan AI</h3>
                            <p class="text-xs text-gray-400 mt-0.5">Jawab untuk hasil konversi yang lebih akurat (opsional)</p>
                        </div>
                        <div class="p-4 space-y-4">
                            @foreach($conversion->qa_questions as $i => $q)
                                <div class="space-y-1.5">
                                    <label class="block text-sm font-medium text-gray-800">
                                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-blue-100 text-blue-700 text-xs font-bold mr-1.5">{{ $i+1 }}</span>
                                        {{ $q['question'] }}
                                    </label>
                                    @if(!empty($q['why_needed']))
                                        <p class="text-xs text-gray-400 pl-7">💡 {{ $q['why_needed'] }}</p>
                                    @endif
                                    <textarea name="answers[{{ $q['question'] }}]" rows="2"
                                        class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none bg-gray-50 focus:bg-white transition"
                                        placeholder="Jawaban kamu..."></textarea>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Submit CTA --}}
                <button id="qaSubmitBtn" onclick="submitQa()"
                    class="w-full bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 disabled:opacity-50 text-white font-bold py-4 rounded-2xl transition flex items-center justify-center gap-2 text-sm shadow-lg shadow-blue-200">
                    ✍️ Mulai Konversi Jurnal
                </button>
                <p class="text-center text-xs text-gray-400 mt-2">AI akan menulis ulang naskahmu sesuai Author Guide jurnal target</p>
            </div>

        </div>
    </div>

    <script>
    const conversionId = {{ $conversion->id }};
    const CSRF = '{{ csrf_token() }}';
    let lastMsgId = {{ $conversion->messages->last()?->id ?? 0 }};
    let polling   = {{ $conversion->isProcessing() ? 'true' : 'false' }};

    const chatEl = document.getElementById('chatMessages');

    function scrollChat() { chatEl.scrollTop = chatEl.scrollHeight; }
    scrollChat();

    // ── Polling ──────────────────────────────────────────────────────────────
    async function poll() {
        try {
            const res  = await fetch(`/conversions/${conversionId}/poll`);
            const data = await res.json();

            document.getElementById('statusLabel').textContent = data.status_label;
            appendMessages(data.messages);

            if (['pending','analyzing','converting'].includes(data.status)) {
                setTimeout(poll, 3000);
            } else {
                document.getElementById('processingBar')?.classList.add('hidden');
                document.getElementById('typingIndicator')?.remove();
                document.getElementById('liveIndicator')?.classList.add('hidden');
                handleTerminalStatus(data);
            }
        } catch(e) { setTimeout(poll, 5000); }
    }

    function appendMessages(messages) {
        let added = false;
        (messages || []).forEach(msg => {
            if (msg.id <= lastMsgId) return;
            lastMsgId = msg.id;
            document.getElementById('typingIndicator')?.remove();
            chatEl.insertAdjacentHTML('beforeend', buildMsgHtml(msg));
            added = true;
        });
        if (added) scrollChat();
    }

    function buildMsgHtml(msg) {
        if (msg.role === 'system') {
            return `<div class="text-center"><span class="text-xs text-gray-400 bg-gray-50 px-3 py-1 rounded-full">${esc(msg.content)}</span></div>`;
        }
        const isAi = msg.role === 'ai';
        const bgClass = {
            'success':          'bg-green-50 border border-green-200',
            'error':            'bg-red-50 border border-red-200',
            'rejection':        'bg-red-50 border border-red-200',
            'fallback_request': 'bg-amber-50 border border-amber-200',
            'diagnosis':        'bg-blue-50 border border-blue-100',
        }[msg.type] || (isAi ? 'bg-gray-50' : 'bg-blue-100');

        const flex  = isAi ? 'flex-row' : 'flex-row-reverse';
        const round = isAi ? 'rounded-tl-none' : 'rounded-tr-none';

        return `<div class="flex items-start gap-2 ${flex}">
            <span class="w-8 h-8 rounded-full ${isAi?'bg-blue-100':'bg-green-100'} flex items-center justify-center text-sm flex-shrink-0">${isAi?'🤖':'👤'}</span>
            <div class="max-w-xs sm:max-w-sm">
                <div class="${bgClass} rounded-2xl ${round} px-4 py-2.5 text-sm text-gray-800 whitespace-pre-wrap leading-relaxed">${esc(msg.content)}</div>
                <p class="text-xs text-gray-400 mt-1 ${isAi?'':'text-right'}">${msg.created_at}</p>
            </div>
        </div>`;
    }

    function handleTerminalStatus(data) {
        if (data.status === 'completed') {
            setTimeout(() => window.location.href = `/conversions/${conversionId}/result`, 1500);
        }
        if (data.status === 'failed') { location.reload(); }
        if (data.status === 'rejected') {
            // Reload to show the rejection UI
            setTimeout(() => location.reload(), 1000);
        }
        if (data.status === 'waiting_fallback') {
            if (data.author_guide_fallback) document.getElementById('authorGuideFallback')?.classList.remove('hidden');
            else if (data.archive_fallback)  document.getElementById('archiveFallback')?.classList.remove('hidden');
        }
        if (data.status === 'awaiting_qa') {
            document.getElementById('qaSection')?.classList.remove('hidden');
        }
    }

    if (polling) poll();

    // ── Fallback Forms ────────────────────────────────────────────────────────
    async function submitFallback(form, type) {
        const fd = new FormData(form);
        fd.append('fallback_type', type);
        fd.append('_token', CSRF);
        const res  = await fetch(`/conversions/${conversionId}/fallback`, { method:'POST', body:fd, headers:{'X-CSRF-TOKEN':CSRF} });
        const data = await res.json();
        if (data.success) {
            form.closest('[id$="Fallback"]')?.classList.add('hidden');
            document.getElementById('processingBar')?.classList.remove('hidden');
            polling = true;
            poll();
        }
    }

    document.getElementById('fallbackGuideForm')?.addEventListener('submit', e => { e.preventDefault(); submitFallback(e.target,'author_guide_manual'); });
    document.getElementById('fallbackArchiveForm')?.addEventListener('submit', e => { e.preventDefault(); submitFallback(e.target,'archive_manual'); });
    document.getElementById('guideFileInput')?.addEventListener('change', function() { document.getElementById('guideFileName').textContent = this.files[0]?.name || ''; });
    document.getElementById('archiveFileInput')?.addEventListener('change', function() { document.getElementById('archiveFileNames').textContent = Array.from(this.files).map(f=>f.name).join(', '); });

    function skipArchive() {
        document.getElementById('archiveFallback')?.classList.add('hidden');
        document.getElementById('processingBar')?.classList.remove('hidden');
        polling = true;
        poll();
    }

    // ── QA Submit ─────────────────────────────────────────────────────────────
    async function submitQa() {
        const btn = document.getElementById('qaSubmitBtn');
        btn.disabled = true;
        btn.innerHTML = '⏳ Memulai konversi...';

        const custom       = document.getElementById('customTitle')?.value.trim();
        const checkedRadio = document.querySelector('input[name="selected_title"]:checked');

        const body = {
            _token:         CSRF,
            selected_title: custom || checkedRadio?.value || '',
            answers:        {},
        };

        document.querySelectorAll('textarea[name^="answers["]').forEach(el => {
            const key = el.name.slice(8, -1);
            if (el.value.trim()) body.answers[key] = el.value.trim();
        });

        const res  = await fetch(`/conversions/${conversionId}/qa`, {
            method:  'POST',
            headers: {'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},
            body:    JSON.stringify(body),
        });
        const data = await res.json();
        if (data.success) {
            document.getElementById('qaSection')?.classList.add('hidden');
            document.getElementById('processingBar')?.classList.remove('hidden');
            polling = true;
            poll();
        } else {
            btn.disabled = false;
            btn.innerHTML = '✍️ Mulai Konversi Jurnal';
        }
    }

    // ── Title option highlight ────────────────────────────────────────────────
    document.querySelectorAll('.title-option').forEach(card => {
        card.addEventListener('click', () => {
            // Clear all
            document.querySelectorAll('.title-option').forEach(c => {
                c.classList.remove('border-blue-500', 'bg-blue-50');
                c.classList.add('border-gray-100', 'bg-gray-50');
                c.querySelector('.title-dot')?.classList.add('opacity-0');
                c.querySelector('.w-5')?.classList.remove('border-blue-500','bg-blue-500');
                c.querySelector('.w-5')?.classList.add('border-gray-300');
            });
            // Select this one
            card.classList.add('border-blue-500', 'bg-blue-50');
            card.classList.remove('border-gray-100', 'bg-gray-50');
            card.querySelector('.title-dot')?.classList.remove('opacity-0');
            card.querySelector('.w-5')?.classList.add('border-blue-500','bg-blue-500');
            card.querySelector('.w-5')?.classList.remove('border-gray-300');
            // Check radio
            const radio = card.querySelector('input[type=radio]');
            if (radio) radio.checked = true;
            // Clear custom title
            const customEl = document.getElementById('customTitle');
            if (customEl) customEl.value = '';
        });
    });

    function esc(str) {
        const d = document.createElement('div');
        d.appendChild(document.createTextNode(str || ''));
        return d.innerHTML;
    }
    </script>
</x-app-layout>
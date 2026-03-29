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
                    <div class="h-full bg-blue-500 rounded-full animate-pulse" style="width:100%;background:linear-gradient(90deg,#3b82f6,#60a5fa,#3b82f6);background-size:200%;animation:shimmer 1.5s infinite;">
                    </div>
                </div>
                <style>@keyframes shimmer{0%{background-position:200%}100%{background-position:-200%}}</style>
            </div>

            {{-- Completed Banner --}}
            @if($conversion->status === 'completed')
                <div class="bg-green-50 border border-green-200 rounded-2xl p-6 text-center">
                    <div class="text-4xl mb-2">🎉</div>
                    <h3 class="font-bold text-green-800 text-lg mb-1">Jurnal Siap Didownload!</h3>
                    <p class="text-green-700 text-sm mb-4">AI sudah selesai mengkonversi skripsimu.</p>
                    <a href="{{ route('conversions.result', $conversion->id) }}"
                        class="inline-flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white font-bold px-6 py-2.5 rounded-xl transition text-sm">
                        ⬇️ Download Jurnal (.docx)
                    </a>
                </div>
            @endif

            {{-- Failed Banner --}}
            @if($conversion->status === 'failed')
                <div class="bg-red-50 border border-red-200 rounded-2xl p-5">
                    <p class="font-semibold text-red-800 mb-1">❌ Proses Gagal</p>
                    <p class="text-red-700 text-sm mb-3">{{ $conversion->error_message }}</p>
                    <a href="{{ route('conversions.create') }}" class="inline-flex items-center gap-1 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold px-4 py-2 rounded-xl transition">
                        🔄 Coba Lagi
                    </a>
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
                                <span class="inline-flex gap-1">
                                    <span class="animate-bounce" style="animation-delay:0ms">⏳</span>
                                    AI sedang bekerja...
                                </span>
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
                <h3 class="font-semibold text-amber-900 mb-1">📤 Upload Contoh Jurnal Lolos</h3>
                <p class="text-sm text-amber-700 mb-3">Upload 3–5 PDF jurnal yang sudah lolos di jurnal target ini.</p>
                <form id="fallbackArchiveForm" class="space-y-3">
                    @csrf
                    <label class="flex flex-col items-center border-2 border-dashed border-amber-300 rounded-xl p-5 text-center cursor-pointer hover:bg-amber-100 transition">
                        <input type="file" name="fallback_files[]" id="archiveFileInput" accept=".pdf" multiple class="sr-only">
                        <span class="text-2xl mb-1">📚</span>
                        <span class="text-sm font-medium text-amber-800">Upload jurnal-jurnal lolos (bisa multiple)</span>
                        <span id="archiveFileNames" class="text-xs text-amber-600 mt-1"></span>
                    </label>
                    <div class="flex gap-2">
                        <button type="submit" class="flex-1 bg-amber-500 hover:bg-amber-600 text-white font-semibold py-2.5 rounded-xl text-sm transition">
                            ▶️ Lanjutkan
                        </button>
                        <button type="button" onclick="skipArchive()" class="flex-1 bg-white border border-amber-300 text-amber-700 font-semibold py-2.5 rounded-xl text-sm hover:bg-amber-50 transition">
                            Lewati →
                        </button>
                    </div>
                </form>
            </div>

            {{-- Q&A Section --}}
            <div id="qaSection" class="{{ $conversion->status === 'awaiting_qa' ? '' : 'hidden' }} bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                <h3 class="font-semibold text-gray-900 mb-4">🎯 Pilih Judul & Jawab Pertanyaan AI</h3>
                <form id="qaForm" class="space-y-5">
                    @csrf
                    {{-- Title Options --}}
                    <div>
                        <p class="text-sm font-semibold text-gray-700 mb-2">Pilih judul yang paling kamu suka: <span class="text-red-500">*</span></p>
                        <div class="space-y-2" id="titleOptions">
                            @foreach($conversion->title_recommendations ?? [] as $i => $title)
                                <label class="flex gap-3 p-3.5 border-2 {{ $i === 0 ? 'border-blue-400 bg-blue-50' : 'border-gray-200' }} rounded-xl cursor-pointer hover:border-blue-400 hover:bg-blue-50 transition title-option">
                                    <input type="radio" name="selected_title" value="{{ $title['title'] }}" {{ $i === 0 ? 'checked' : '' }} class="mt-0.5 accent-blue-600">
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">{{ $title['title'] }}</p>
                                        <p class="text-xs text-gray-500 mt-0.5">💡 {{ $title['reason'] }}</p>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                        <div class="mt-2">
                            <input type="text" id="customTitle" placeholder="...atau tulis judul sendiri di sini"
                                class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                    </div>

                    {{-- Smart Questions --}}
                    @if(!empty($conversion->qa_questions))
                        <div class="space-y-3">
                            <p class="text-sm font-semibold text-gray-700">Pertanyaan AI <span class="font-normal text-gray-400">(jawab kalau bisa)</span></p>
                            @foreach($conversion->qa_questions as $i => $q)
                                <div>
                                    <label class="block text-sm text-gray-700 mb-1">
                                        {{ $i+1 }}. {{ $q['question'] }}
                                        <span class="block text-xs text-gray-400 mt-0.5">💡 {{ $q['why_needed'] ?? '' }}</span>
                                    </label>
                                    <textarea name="answers[{{ $q['question'] }}]" rows="2"
                                        class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"
                                        placeholder="Jawaban kamu..."></textarea>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <button type="submit" id="qaSubmitBtn"
                        class="w-full bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white font-bold py-3 rounded-xl transition text-sm">
                        ✍️ Mulai Konversi Jurnal!
                    </button>
                </form>
            </div>

        </div>
    </div>

    <script>
    const conversionId = {{ $conversion->id }};
    const CSRF = '{{ csrf_token() }}';
    let lastMsgId = {{ $conversion->messages->last()?->id ?? 0 }};
    let polling   = {{ $conversion->isProcessing() ? 'true' : 'false' }};

    const chatEl  = document.getElementById('chatMessages');

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
        const avatar = isAi ? '🤖' : '👤';
        const bgClass = {
            'success':'bg-green-50 border border-green-200',
            'error':'bg-red-50 border border-red-200',
            'fallback_request':'bg-amber-50 border border-amber-200',
            'diagnosis':'bg-blue-50 border border-blue-100',
        }[msg.type] || (isAi ? 'bg-gray-50' : 'bg-blue-100');

        const flex = isAi ? 'flex-row' : 'flex-row-reverse';
        const round = isAi ? 'rounded-tl-none' : 'rounded-tr-none';

        return `<div class="flex items-start gap-2 ${flex}">
            <span class="w-8 h-8 rounded-full ${isAi?'bg-blue-100':'bg-green-100'} flex items-center justify-center text-sm flex-shrink-0">${avatar}</span>
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

    document.getElementById('guideFileInput')?.addEventListener('change', function() {
        document.getElementById('guideFileName').textContent = this.files[0]?.name || '';
    });
    document.getElementById('archiveFileInput')?.addEventListener('change', function() {
        document.getElementById('archiveFileNames').textContent = Array.from(this.files).map(f=>f.name).join(', ');
    });

    function skipArchive() {
        document.getElementById('archiveFallback')?.classList.add('hidden');
        document.getElementById('processingBar')?.classList.remove('hidden');
        polling = true;
        poll();
    }

    // ── QA Form ───────────────────────────────────────────────────────────────
    document.getElementById('qaForm')?.addEventListener('submit', async e => {
        e.preventDefault();
        const btn = document.getElementById('qaSubmitBtn');
        btn.disabled = true;
        btn.textContent = '⏳ Memulai konversi...';

        const fd    = new FormData(e.target);
        const custom = document.getElementById('customTitle')?.value.trim();
        const body = {
            _token: CSRF,
            selected_title: custom || fd.get('selected_title'),
            answers: {},
        };
        for (const [k,v] of fd.entries()) {
            if (k.startsWith('answers[')) body.answers[k.slice(8,-1)] = v;
        }

        const res  = await fetch(`/conversions/${conversionId}/qa`, {
            method:'POST',
            headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},
            body:JSON.stringify(body),
        });
        const data = await res.json();
        if (data.success) {
            document.getElementById('qaSection')?.classList.add('hidden');
            document.getElementById('processingBar')?.classList.remove('hidden');
            polling = true;
            poll();
        } else {
            btn.disabled = false;
            btn.textContent = '✍️ Mulai Konversi Jurnal!';
        }
    });

    // ── Title option highlight ────────────────────────────────────────────────
    document.querySelectorAll('.title-option input[type=radio]').forEach(r => {
        r.addEventListener('change', () => {
            document.querySelectorAll('.title-option').forEach(el => {
                el.classList.remove('border-blue-400','bg-blue-50');
                el.classList.add('border-gray-200');
            });
            r.closest('.title-option').classList.add('border-blue-400','bg-blue-50');
            r.closest('.title-option').classList.remove('border-gray-200');
        });
    });

    function esc(str) {
        const d = document.createElement('div');
        d.appendChild(document.createTextNode(str || ''));
        return d.innerHTML;
    }
    </script>
</x-app-layout>

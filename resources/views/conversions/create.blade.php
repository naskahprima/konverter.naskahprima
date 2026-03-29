<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-bold text-gray-900">🎓 Konversi Skripsi → Jurnal</h2>
            <span class="inline-flex items-center gap-1.5 bg-blue-50 text-blue-700 text-sm font-semibold px-3 py-1 rounded-full border border-blue-200">
                🪙 {{ Auth::user()->token_balance }} token tersisa
            </span>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">

            @if($errors->any())
                <div class="mb-5 bg-red-50 border border-red-200 text-red-700 rounded-xl p-4 text-sm">
                    <p class="font-semibold mb-1">⚠️ Ada yang perlu diperbaiki:</p>
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif

            {{-- Flow Indicator --}}
            <div class="mb-6 flex items-center bg-white rounded-xl border border-gray-100 shadow-sm px-5 py-3 gap-1">
                @foreach(['Upload','AI Analisis','Diagnosis','Q&A','Download'] as $i => $step)
                    <div class="flex items-center gap-1.5">
                        <span class="w-5 h-5 rounded-full {{ $i === 0 ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-400' }} text-xs font-bold flex items-center justify-center">{{ $i+1 }}</span>
                        <span class="text-xs {{ $i === 0 ? 'font-semibold text-gray-800' : 'text-gray-400' }} hidden sm:block">{{ $step }}</span>
                    </div>
                    @if($i < 4)<div class="flex-1 h-px bg-gray-200 mx-1"></div>@endif
                @endforeach
            </div>

            <form action="{{ route('conversions.store') }}" method="POST" enctype="multipart/form-data" id="conversionForm">
                @csrf

                {{-- Step 1: Skripsi --}}
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-4">
                    <div class="flex items-center gap-3 mb-4">
                        <span class="w-7 h-7 rounded-full bg-blue-600 text-white text-xs font-bold flex items-center justify-center">1</span>
                        <h3 class="font-semibold text-gray-900">Upload Skripsi</h3>
                    </div>
                    <label id="skripsiArea" class="relative flex flex-col items-center justify-center border-2 border-dashed border-gray-200 rounded-xl p-8 text-center cursor-pointer transition-all hover:border-blue-400 hover:bg-blue-50 group">
                        <input type="file" name="skripsi" id="skripsiInput" accept=".pdf,.doc,.docx" class="sr-only">
                        <span id="skripsiIcon" class="text-4xl mb-2">📄</span>
                        <span id="skripsiText" class="text-sm font-medium text-gray-600 group-hover:text-blue-600">Klik atau drag file skripsi di sini</span>
                        <span id="skripsiSub" class="text-xs text-gray-400 mt-1">PDF, DOC, DOCX — Maks 20MB</span>
                    </label>
                </div>

                {{-- Step 2: Template --}}
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-4">
                    <div class="flex items-center gap-3 mb-4">
                        <span class="w-7 h-7 rounded-full bg-blue-600 text-white text-xs font-bold flex items-center justify-center">2</span>
                        <h3 class="font-semibold text-gray-900">Upload Template Jurnal</h3>
                    </div>
                    <div class="bg-blue-50 border border-blue-100 rounded-xl p-3 mb-4 text-xs text-blue-800 space-y-0.5">
                        <p class="font-semibold">📌 Cara dapat template:</p>
                        <p>Buka website jurnal → cari <em>Author Guidelines / Download Template</em> → download .docx → upload di sini.</p>
                    </div>
                    <label id="templateArea" class="relative flex flex-col items-center justify-center border-2 border-dashed border-gray-200 rounded-xl p-8 text-center cursor-pointer transition-all hover:border-blue-400 hover:bg-blue-50 group">
                        <input type="file" name="template" id="templateInput" accept=".doc,.docx" class="sr-only">
                        <span id="templateIcon" class="text-4xl mb-2">📋</span>
                        <span id="templateText" class="text-sm font-medium text-gray-600 group-hover:text-blue-600">Klik atau drag template jurnal di sini</span>
                        <span id="templateSub" class="text-xs text-gray-400 mt-1">DOC, DOCX — Maks 10MB</span>
                    </label>
                </div>

                {{-- Step 3: URLs --}}
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-4">
                    <div class="flex items-center gap-3 mb-4">
                        <span class="w-7 h-7 rounded-full bg-blue-600 text-white text-xs font-bold flex items-center justify-center">3</span>
                        <h3 class="font-semibold text-gray-900">Link Jurnal Target</h3>
                    </div>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                URL Author Guide <span class="text-gray-400 font-normal text-xs">(opsional tapi dianjurkan)</span>
                            </label>
                            <input type="url" name="author_guide_url"
                                class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                placeholder="https://journal.com/author-guidelines"
                                value="{{ old('author_guide_url') }}">
                            <p class="text-xs text-gray-400 mt-1">Kalau tidak bisa diakses AI, nanti kamu diminta upload manual.</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                URL Contoh Jurnal Lolos <span class="text-gray-400 font-normal text-xs">(1 URL per baris)</span>
                            </label>
                            <textarea name="archive_urls" rows="3"
                                class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
                                placeholder="https://journal.com/article/1234&#10;https://journal.com/article/5678">{{ old('archive_urls') }}</textarea>
                            <p class="text-xs text-gray-400 mt-1">💡 Makin banyak contoh, makin AI paham pola yang disukai editor.</p>
                        </div>
                    </div>
                </div>

                {{-- Token Notice --}}
                <div class="flex items-start gap-2 bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 mb-4 text-xs text-amber-800">
                    <span class="text-base">🪙</span>
                    <span>Proses ini menggunakan <strong>1 token</strong> (sisa: {{ $tokenBalance }}). Pastikan semua file sudah benar sebelum submit.</span>
                </div>

                <button type="submit" id="submitBtn"
                    class="w-full bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white font-bold py-3.5 rounded-xl transition flex items-center justify-center gap-2 text-sm">
                    🚀 Mulai Konversi — Gunakan 1 Token
                </button>
            </form>
        </div>
    </div>

    <script>
    function setupDrop(inputId, areaId, iconId, textId, subId) {
        const input = document.getElementById(inputId);
        const area  = document.getElementById(areaId);

        function onFile(file) {
            document.getElementById(iconId).textContent = '✅';
            document.getElementById(textId).textContent = file.name;
            document.getElementById(subId).textContent  = (file.size/1048576).toFixed(1) + ' MB';
            area.classList.add('border-blue-400','bg-blue-50');
        }

        input.addEventListener('change', () => input.files[0] && onFile(input.files[0]));

        area.addEventListener('dragover', e => { e.preventDefault(); area.classList.add('border-blue-400','bg-blue-50'); });
        area.addEventListener('dragleave', () => area.classList.remove('bg-blue-50'));
        area.addEventListener('drop', e => {
            e.preventDefault();
            if (e.dataTransfer.files[0]) { input.files = e.dataTransfer.files; onFile(e.dataTransfer.files[0]); }
        });
    }

    setupDrop('skripsiInput',  'skripsiArea',  'skripsiIcon',  'skripsiText',  'skripsiSub');
    setupDrop('templateInput', 'templateArea', 'templateIcon', 'templateText', 'templateSub');

    document.getElementById('conversionForm').addEventListener('submit', () => {
        const btn = document.getElementById('submitBtn');
        btn.disabled = true;
        btn.textContent = '⏳ Mengupload file...';
    });
    </script>
</x-app-layout>

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-bold text-gray-900">✅ Jurnal Siap Download</h2>
            <a href="{{ route('conversions.index') }}" class="text-sm text-gray-400 hover:text-gray-600">← Riwayat</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

            {{-- Success Card --}}
            <div class="bg-gradient-to-b from-green-50 to-white border border-green-200 rounded-2xl p-8 text-center">
                <div class="text-5xl mb-3">🎉</div>
                <h3 class="text-xl font-bold text-green-800 mb-1">Jurnal Siap Didownload!</h3>
                <p class="text-green-700 text-sm mb-5">AI selesai mengkonversi skripsimu sesuai Author Guide jurnal target.</p>
                <a href="{{ route('conversions.download', $conversion->id) }}"
                    class="inline-flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white font-bold px-8 py-3 rounded-xl transition shadow-sm shadow-green-200 text-sm">
                    ⬇️ Download Jurnal (.docx)
                </a>
            </div>

            {{-- Info Grid --}}
            <div class="grid grid-cols-2 gap-3">
                <div class="bg-white border border-gray-100 rounded-xl p-4 text-center shadow-sm">
                    <div class="text-2xl mb-1">🆔</div>
                    <p class="text-xs text-gray-400">ID Konversi</p>
                    <p class="font-semibold text-gray-800">#{{ $conversion->id }}</p>
                </div>
                <div class="bg-white border border-gray-100 rounded-xl p-4 text-center shadow-sm">
                    <div class="text-2xl mb-1">📅</div>
                    <p class="text-xs text-gray-400">Selesai</p>
                    <p class="font-semibold text-gray-800">{{ $conversion->updated_at->format('d M Y, H:i') }}</p>
                </div>
                <div class="bg-white border border-gray-100 rounded-xl p-4 text-center shadow-sm">
                    <div class="text-2xl mb-1">
                        {{ match($conversion->scope_match ?? '') { 'match'=>'✅','partial'=>'⚠️','mismatch'=>'❌',default=>'❓' } }}
                    </div>
                    <p class="text-xs text-gray-400">Scope Match</p>
                    <p class="font-semibold text-gray-800">{{ ucfirst($conversion->scope_match ?? '-') }}</p>
                </div>
                <div class="bg-white border border-gray-100 rounded-xl p-4 text-center shadow-sm">
                    <div class="text-2xl mb-1">📝</div>
                    <p class="text-xs text-gray-400">Format</p>
                    <p class="font-semibold text-gray-800">Word (.docx)</p>
                </div>
            </div>

            {{-- Submission Checklist --}}
            @if(!empty($checklist))
                <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm">
                    <h3 class="font-semibold text-gray-900 mb-3">📋 Submission Checklist</h3>
                    <div class="space-y-2">
                        @foreach($checklist as $item)
                            <div class="flex items-start gap-2 text-sm {{ str_starts_with($item,'⚠️') ? 'text-amber-700' : 'text-gray-700' }}">
                                <span class="mt-0.5 flex-shrink-0">{{ substr($item,0,4) }}</span>
                                <span>{{ substr($item,4) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Tips --}}
            <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm">
                <h3 class="font-semibold text-gray-900 mb-3">💡 Tips Sebelum Submit</h3>
                <div class="space-y-2 text-sm text-gray-600">
                    @foreach([
                        '📖 Baca ulang seluruh naskah — AI hebat tapi bukan sempurna.',
                        '📝 Update referensi kalau ada yang perlu ditambah/diperbarui.',
                        '📊 Cek tabel & gambar — pastikan format sesuai panduan jurnal.',
                        '🔤 Periksa konsistensi nama, singkatan, dan terminologi.',
                        '🗑️ Hapus halaman Submission Checklist dari file sebelum submit!',
                    ] as $tip)
                        <div class="flex items-start gap-2">
                            <span class="flex-shrink-0">{{ substr($tip,0,4) }}</span>
                            <span>{{ substr($tip,4) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Action Links --}}
            <div class="flex items-center justify-center gap-4 text-sm">
                <a href="{{ route('conversions.download', $conversion->id) }}" class="text-blue-600 hover:underline">⬇️ Download lagi</a>
                <span class="text-gray-300">·</span>
                <a href="{{ route('conversions.create') }}" class="text-blue-600 hover:underline">🆕 Konversi baru</a>
                <span class="text-gray-300">·</span>
                <a href="{{ route('conversions.index') }}" class="text-blue-600 hover:underline">📋 Riwayat</a>
            </div>

        </div>
    </div>
</x-app-layout>

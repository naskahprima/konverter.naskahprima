<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-bold text-gray-900">📋 Riwayat Konversi</h2>
            <a href="{{ route('conversions.create') }}"
                class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-4 py-2 rounded-xl transition">
                + Konversi Baru
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">

            {{-- Token Balance --}}
            <div class="bg-white border border-gray-100 rounded-2xl p-5 mb-6 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Token kamu</p>
                    <p class="text-2xl font-bold text-gray-900">🪙 {{ Auth::user()->token_balance }}</p>
                    <p class="text-xs text-gray-400 mt-0.5">1 token = 1 konversi skripsi</p>
                </div>
                <a href="{{ route('conversions.create') }}"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-5 py-2.5 rounded-xl text-sm transition">
                    Mulai Konversi →
                </a>
            </div>

            {{-- Conversion List --}}
            @if($conversions->isEmpty())
                <div class="bg-white border border-gray-100 rounded-2xl p-12 text-center shadow-sm">
                    <div class="text-5xl mb-3">📄</div>
                    <h3 class="font-semibold text-gray-700 mb-1">Belum ada konversi</h3>
                    <p class="text-sm text-gray-400 mb-4">Upload skripsi pertamamu dan biarkan AI yang kerja!</p>
                    <a href="{{ route('conversions.create') }}" class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold px-5 py-2.5 rounded-xl text-sm transition">
                        🚀 Mulai Konversi Pertama
                    </a>
                </div>
            @else
                <div class="space-y-3">
                    @foreach($conversions as $conv)
                        @php
                            $statusColor = match($conv->status) {
                                'completed'       => 'bg-green-50 text-green-700 border-green-200',
                                'failed'          => 'bg-red-50 text-red-700 border-red-200',
                                'awaiting_qa','waiting_fallback' => 'bg-amber-50 text-amber-700 border-amber-200',
                                default           => 'bg-blue-50 text-blue-700 border-blue-200',
                            };
                        @endphp
                        <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm hover:shadow-md transition">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="text-xs font-medium text-gray-400">#{{ $conv->id }}</span>
                                        <span class="inline-flex text-xs font-medium px-2 py-0.5 rounded-full border {{ $statusColor }}">
                                            {{ $conv->statusLabel() }}
                                        </span>
                                    </div>
                                    <p class="text-sm text-gray-500">{{ $conv->created_at->format('d M Y, H:i') }}</p>
                                </div>
                                <div class="flex items-center gap-2 flex-shrink-0">
                                    @if($conv->status === 'completed')
                                        <a href="{{ route('conversions.download', $conv->id) }}"
                                            class="text-xs font-semibold bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded-lg transition">
                                            ⬇️ Download
                                        </a>
                                    @endif
                                    <a href="{{ route('conversions.show', $conv->id) }}"
                                        class="text-xs font-semibold bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-1.5 rounded-lg transition">
                                        Detail →
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Pagination --}}
                @if($conversions->hasPages())
                    <div class="mt-6">{{ $conversions->links() }}</div>
                @endif
            @endif

        </div>
    </div>
</x-app-layout>

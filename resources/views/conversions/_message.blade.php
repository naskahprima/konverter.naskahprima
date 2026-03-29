@if($msg->role === 'system')
    <div class="text-center">
        <span class="text-xs text-gray-400 bg-gray-50 px-3 py-1 rounded-full">{{ $msg->content }}</span>
    </div>
@else
    @php
        $isAi = $msg->role === 'ai';
        $bgClass = match($msg->type) {
            'success'          => 'bg-green-50 border border-green-200',
            'error'            => 'bg-red-50 border border-red-200',
            'fallback_request' => 'bg-amber-50 border border-amber-200',
            'diagnosis'        => 'bg-blue-50 border border-blue-100',
            default            => $isAi ? 'bg-gray-50' : 'bg-blue-100',
        };
    @endphp
    <div class="flex items-start gap-2 {{ $isAi ? '' : 'flex-row-reverse' }}">
        <span class="w-8 h-8 rounded-full {{ $isAi ? 'bg-blue-100' : 'bg-green-100' }} flex items-center justify-center text-sm flex-shrink-0">
            {{ $isAi ? '🤖' : '👤' }}
        </span>
        <div class="max-w-xs sm:max-w-sm">
            <div class="{{ $bgClass }} rounded-2xl {{ $isAi ? 'rounded-tl-none' : 'rounded-tr-none' }} px-4 py-2.5 text-sm text-gray-800 whitespace-pre-wrap leading-relaxed">{{ $msg->content }}</div>
            <p class="text-xs text-gray-400 mt-1 {{ $isAi ? '' : 'text-right' }}">{{ $msg->created_at->format('H:i') }}</p>
        </div>
    </div>
@endif

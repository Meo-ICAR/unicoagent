@php($messages = $getRecord()->messages)

<div style="display:flex;flex-direction:column;gap:.375rem;padding:1rem;border-radius:.75rem;background:#efeae2;max-height:36rem;overflow-y:auto">
    @forelse ($messages as $message)
        @php($out = $message->direction === 'out')
        <div style="display:flex;justify-content:{{ $out ? 'flex-start' : 'flex-end' }}">
            <div style="max-width:75%;padding:.375rem .625rem;border-radius:.5rem;background:{{ $out ? '#ffffff' : '#d9fdd3' }};color:#111b21;box-shadow:0 1px .5px rgba(11,20,26,.13);font-size:.875rem">
                <div style="white-space:pre-line;word-break:break-word">{{ $message->body }}</div>
                <div style="text-align:right;font-size:.6875rem;color:#667781;margin-top:.125rem">{{ $message->created_at->format('d/m H:i') }}</div>
            </div>
        </div>
    @empty
        <div style="text-align:center;color:#667781;font-size:.875rem">Nessun messaggio registrato per questa conversazione.</div>
    @endforelse
</div>

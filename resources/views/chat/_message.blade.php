@php $mine = $m->user_id === auth()->id(); @endphp
<li data-id="{{ $m->id }}" class="flex {{ $mine ? 'justify-end' : 'justify-start' }} group">
    <div class="max-w-[75%] rounded-2xl px-3 py-2 {{ $mine ? 'bg-indigo-600 text-white rounded-br-sm' : 'bg-slate-100 rounded-bl-sm' }}">
        <p class="whitespace-pre-line break-words text-sm">{{ $m->body }}</p>
        <div class="flex items-center gap-2 mt-0.5 text-[11px] {{ $mine ? 'text-indigo-100' : 'text-slate-500' }}">
            <time datetime="{{ $m->created_at->toIso8601String() }}">{{ $m->created_at->format('H:i') }}</time>
            @if ($mine)
                <button type="button" data-delete="{{ $m->id }}" class="opacity-0 group-hover:opacity-100 focus:opacity-100 underline">delete</button>
            @endif
        </div>
    </div>
</li>

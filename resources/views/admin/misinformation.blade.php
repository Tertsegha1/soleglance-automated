<x-app-layout title="Misinformation review">
    @include('admin._health_nav')
    <p class="text-sm text-slate-600 mb-4 max-w-3xl">Public posts the model scored as likely false health claims. Confirming shows your correction under the post; nothing is removed automatically. Each decision is used to retrain the model.</p>
    <div class="flex gap-2 mb-4 text-sm">
        @foreach (['pending', 'confirmed', 'dismissed', 'all'] as $s)
            <a href="{{ route('admin.misinformation.index', ['status' => $s]) }}" class="rounded px-3 py-1 border {{ $status === $s ? 'bg-slate-900 text-white' : 'bg-white' }}">{{ ucfirst($s) }}</a>
        @endforeach
    </div>
    <x-card>
        @forelse ($reviews as $r)
            <div class="p-4 border-b last:border-b-0" x-data="{ confirm: false }">
                <div class="flex flex-wrap gap-2 text-sm text-slate-500">
                    <a href="{{ route('posts.show', $r->post) }}" class="font-medium text-slate-900 hover:underline">{{ $r->post->user->name }}</a>
                    <span>score {{ number_format($r->score, 2) }}</span>
                    <span>{{ $r->status }}{{ $r->reviewer ? ' by ' . $r->reviewer->name : '' }}</span>
                </div>
                <p class="mt-1 whitespace-pre-line break-words">{{ \Illuminate\Support\Str::limit($r->post->body, 600) }}</p>
                @if ($r->correction)<p class="mt-2 text-sm text-emerald-800">Correction: {{ $r->correction }}</p>@endif
                <div class="mt-2 flex gap-3">
                    <button type="button" @click="confirm = !confirm" class="text-sm text-rose-700 hover:underline">Confirm as false…</button>
                    <form method="POST" action="{{ route('admin.misinformation.update', $r) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="dismissed"><button class="text-sm text-slate-600 hover:underline">Not misinformation</button></form>
                </div>
                <form x-show="confirm" x-cloak method="POST" action="{{ route('admin.misinformation.update', $r) }}" class="mt-2 grid sm:grid-cols-3 gap-2">
                    @csrf @method('PATCH')
                    <input type="hidden" name="status" value="confirmed">
                    <input name="correction" required maxlength="1000" placeholder="Short factual correction shown to readers" class="sm:col-span-2 rounded border-slate-300 text-sm">
                    <input name="source_url" type="url" placeholder="Source (WHO, NCDC, NIH…)" class="rounded border-slate-300 text-sm">
                    <x-primary-button class="sm:col-span-3 justify-center">Confirm and publish correction</x-primary-button>
                </form>
            </div>
        @empty
            <x-empty>Nothing to review.</x-empty>
        @endforelse
    </x-card>
    <div class="mt-4">{{ $reviews->links() }}</div>
</x-app-layout>

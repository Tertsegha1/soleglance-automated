<x-app-layout title="Sentiment analyser">
    <div class="max-w-2xl mx-auto space-y-4">
        <x-card class="p-5">
            <h1 class="text-xl font-semibold">Sentiment analyser</h1>
            <p class="text-sm text-slate-500 mt-1">Paste any text in English, Nigerian Pidgin, Urdu or Roman Urdu to see whether it reads as positive, neutral or negative.</p>
            <form method="POST" action="{{ route('sentiment.analyse') }}" class="mt-4 space-y-3">
                @csrf
                <label for="text" class="sr-only">Text to analyse</label>
                <textarea id="text" name="text" rows="5" required maxlength="2000" class="w-full rounded-lg border-slate-300" dir="auto">{{ old('text', $text) }}</textarea>
                <x-primary-button>Analyse</x-primary-button>
            </form>
        </x-card>

        @if (!empty($error))
            <x-card class="p-4 text-sm text-rose-700">{{ $error }}</x-card>
        @endif

        @if ($result)
            <x-card class="p-5">
                <div class="flex items-center gap-3">
                    <x-sentiment-badge :sentiment="\App\Enums\Sentiment::from($result['label'])" class="text-sm" />
                    <span class="text-sm text-slate-500">confidence {{ number_format($result['score'] * 100, 1) }}% &middot; language: {{ ['en' => 'English', 'ur' => 'Urdu', 'ur-Latn' => 'Roman Urdu', 'pcm' => 'Nigerian Pidgin'][$result['language']] ?? $result['language'] }}</span>
                </div>
                <dl class="mt-4 space-y-2">
                    @foreach (['positive' => 'bg-emerald-500', 'neutral' => 'bg-slate-400', 'negative' => 'bg-rose-500'] as $label => $color)
                        <div class="flex items-center gap-3 text-sm">
                            <dt class="w-20">{{ ucfirst($label) }}</dt>
                            <dd class="flex-1 h-2 bg-slate-100 rounded-full overflow-hidden"><div class="h-full {{ $color }}" style="width: {{ 100 * ($result['scores'][$label] ?? 0) }}%"></div></dd>
                            <dd class="w-14 text-right tabular-nums">{{ number_format(100 * ($result['scores'][$label] ?? 0), 1) }}%</dd>
                        </div>
                    @endforeach
                </dl>
                <p class="text-xs text-slate-500 mt-4">Model: {{ $result['model'] }}. Automatic sentiment estimates can be wrong, especially for sarcasm and mixed-language text.</p>
            </x-card>
        @endif
    </div>
</x-app-layout>

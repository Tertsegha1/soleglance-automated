<x-app-layout title="Feed">
    <div class="grid lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-4">
            @include('posts._composer')

            @forelse ($posts as $post)
                <x-post-card :post="$post" />
            @empty
                <x-card><x-empty>Your feed is empty. <a class="text-indigo-600 underline" href="{{ route('friends.index') }}">Find friends</a> or write your first post.</x-empty></x-card>
            @endforelse

            {{ $posts->links() }}
        </div>

        <aside class="space-y-4">
            <x-card class="p-4">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="font-semibold">Trending now</h2>
                    <a href="{{ route('trending.index') }}" class="text-sm text-indigo-600 hover:underline">See all</a>
                </div>
                @forelse ($trends as $trend)
                    <a href="{{ route('trending.show', ['q' => $trend->term]) }}" class="block py-2 border-t first:border-t-0 hover:bg-slate-50 -mx-2 px-2 rounded">
                        <div class="flex justify-between text-sm">
                            <span class="font-medium">{{ $trend->term }}</span>
                            <span class="text-slate-500">{{ $trend->mentions }} mentions</span>
                        </div>
                        @if ($trend->category)<div class="text-xs text-slate-500">{{ $trend->category->label() }}</div>@endif
                        <x-sentiment-bar class="mt-1" :positive="$trend->positive" :neutral="$trend->neutral" :negative="$trend->negative" />
                    </a>
                @empty
                    <p class="text-sm text-slate-500">No trends computed yet.</p>
                @endforelse
            </x-card>

            <x-card class="p-4 text-sm">
                <h2 class="font-semibold mb-2">Explore</h2>
                <ul class="space-y-1">
                    <li><a class="text-indigo-600 hover:underline" href="{{ route('sentiment.index') }}">Analyse the sentiment of any text</a></li>
                    <li><a class="text-indigo-600 hover:underline" href="{{ route('reports.index') }}">Download a topic report (PDF)</a></li>
                    <li><a class="text-indigo-600 hover:underline" href="{{ route('search', ['type' => 'news']) }}">Search the news</a></li>
                </ul>
            </x-card>
        </aside>
    </div>
</x-app-layout>

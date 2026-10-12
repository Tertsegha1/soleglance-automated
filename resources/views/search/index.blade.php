<x-app-layout title="Search">
    <x-card class="p-4 mb-6">
        <form method="get" class="grid sm:grid-cols-6 gap-3 items-end">
            <div class="sm:col-span-2">
                <x-input-label for="q" value="Keywords" />
                <x-text-input id="q" name="q" type="search" class="mt-1 w-full" :value="$q" required />
            </div>
            <div>
                <x-input-label for="type" value="Show" />
                <select id="type" name="type" class="mt-1 w-full rounded-md border-slate-300">
                    @foreach (['all' => 'Everything', 'people' => 'People', 'posts' => 'Posts', 'news' => 'News'] as $value => $label)
                        <option value="{{ $value }}" @selected($type === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-input-label for="from" value="From" />
                <x-text-input id="from" name="from" type="date" class="mt-1 w-full" :value="$filters['from'] ?? ''" />
            </div>
            <div>
                <x-input-label for="to" value="To" />
                <x-text-input id="to" name="to" type="date" class="mt-1 w-full" :value="$filters['to'] ?? ''" />
            </div>
            <div>
                <x-input-label for="country" value="Region (ISO code)" />
                <x-text-input id="country" name="country" maxlength="2" placeholder="e.g. PK" class="mt-1 w-full uppercase" :value="$filters['country'] ?? ''" />
            </div>
            <div class="sm:col-span-6 flex gap-3">
                <x-primary-button>Search</x-primary-button>
                @if ($q !== '')
                    <a href="{{ route('trending.show', array_filter(['q' => $q, 'from' => $filters['from'] ?? null, 'to' => $filters['to'] ?? null, 'country' => $filters['country'] ?? null])) }}" class="text-sm text-indigo-600 hover:underline self-center">View sentiment analysis for "{{ $q }}" &rarr;</a>
                @endif
            </div>
        </form>
    </x-card>

    @if ($q === '')
        <x-empty>Enter a keyword to search people, posts and news.</x-empty>
    @else
        <div class="grid lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-4">
                @if (in_array($type, ['all', 'posts']))
                    <h2 class="font-semibold">Posts</h2>
                    @forelse ($posts as $post)
                        <x-post-card :post="$post" />
                    @empty
                        <x-card><x-empty>No posts match.</x-empty></x-card>
                    @endforelse
                @endif
                @if (in_array($type, ['all', 'news']))
                    <h2 class="font-semibold pt-2">News</h2>
                    @forelse ($articles as $article)
                        @include('trending._article', ['article' => $article])
                    @empty
                        <x-card><x-empty>No news articles match.</x-empty></x-card>
                    @endforelse
                @endif
            </div>
            @if (in_array($type, ['all', 'people']))
                <aside>
                    <x-card class="p-4">
                        <h2 class="font-semibold mb-3">People</h2>
                        @forelse ($people as $person)
                            <a href="{{ $person->profileUrl() }}" class="flex items-center gap-3 py-2 hover:bg-slate-50 rounded -mx-2 px-2">
                                <x-avatar :user="$person" class="h-9 w-9" />
                                <div><div class="font-medium text-sm">{{ $person->name }}</div><div class="text-xs text-slate-500">{{ '@' . $person->username }}</div></div>
                            </a>
                        @empty
                            <p class="text-sm text-slate-500">No people match.</p>
                        @endforelse
                    </x-card>
                </aside>
            @endif
        </div>
    @endif
</x-app-layout>

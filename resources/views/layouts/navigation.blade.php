@php
    $unread = auth()->user()->unreadNotifications()->count();
    $links = [
        ['feed', 'Feed'],
        ['trending.index', 'Trending'],
        ['health.index', 'Health'],
        ['chat.index', 'Messages'],
        ['friends.index', 'Friends'],
    ];
@endphp
<nav x-data="{ open: false }" class="bg-white border-b border-slate-200 sticky top-0 z-30">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16 gap-4">
            <div class="flex items-center gap-6">
                <a href="{{ route('feed') }}" class="flex items-center gap-2 font-semibold text-lg text-indigo-700">
                    <x-application-logo class="h-8 w-8" />
                    <span class="hidden sm:inline">SoleGlance</span>
                </a>
                <div class="hidden md:flex gap-1">
                    @foreach ($links as [$route, $label])
                        <a href="{{ route($route) }}"
                           class="px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs(str_replace('.index', '', $route) . '*') ? 'bg-indigo-50 text-indigo-700' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">{{ $label }}</a>
                    @endforeach
                </div>
            </div>

            <form action="{{ route('search') }}" method="get" class="hidden sm:flex flex-1 max-w-sm items-center" role="search">
                <label for="nav-search" class="sr-only">Search</label>
                <input id="nav-search" name="q" value="{{ request('q') }}" type="search" placeholder="Search people, posts, news…"
                       class="w-full rounded-full border-slate-300 bg-slate-50 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            </form>

            <div class="flex items-center gap-2">
                <a href="{{ route('notifications.index') }}" class="relative p-2 rounded-full text-slate-500 hover:bg-slate-100" aria-label="Notifications ({{ $unread }} unread)">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0v1a3 3 0 1 1-6 0v-1m6 0H9"/></svg>
                    @if ($unread)
                        <span class="absolute -top-0.5 -right-0.5 min-w-5 h-5 px-1 rounded-full bg-rose-600 text-white text-xs leading-5 text-center">{{ $unread > 99 ? '99+' : $unread }}</span>
                    @endif
                </a>

                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="flex items-center gap-2 p-1 rounded-full hover:bg-slate-100" aria-label="Account menu">
                            <x-avatar :user="auth()->user()" class="h-8 w-8" />
                        </button>
                    </x-slot>
                    <x-slot name="content">
                        <div class="px-4 py-2 text-xs text-slate-500 border-b">Signed in as <strong>{{ '@' . auth()->user()->username }}</strong></div>
                        <x-dropdown-link :href="auth()->user()->profileUrl()">My profile</x-dropdown-link>
                        <x-dropdown-link :href="route('sentiment.index')">Sentiment analyser</x-dropdown-link>
                        <x-dropdown-link :href="route('reports.index')">Reports</x-dropdown-link>
                        <x-dropdown-link :href="route('profile.edit')">Settings</x-dropdown-link>
                        @can('health')
                            <x-dropdown-link :href="route('admin.health-alerts.index')">Health alerts</x-dropdown-link>
                        @endcan
                        @can('admin')
                            <x-dropdown-link :href="route('admin.dashboard')">Admin</x-dropdown-link>
                        @endcan
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">Log out</x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>

                <button @click="open = ! open" class="md:hidden p-2 rounded-md text-slate-500 hover:bg-slate-100" aria-label="Toggle menu">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24"><path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/><path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>
    </div>

    <div :class="{'block': open, 'hidden': ! open}" class="hidden md:hidden border-t border-slate-200 pb-3">
        <form action="{{ route('search') }}" method="get" class="px-4 pt-3" role="search">
            <input name="q" type="search" placeholder="Search…" class="w-full rounded-full border-slate-300 text-sm">
        </form>
        @foreach ($links as [$route, $label])
            <x-responsive-nav-link :href="route($route)" :active="request()->routeIs($route)">{{ $label }}</x-responsive-nav-link>
        @endforeach
    </div>
</nav>

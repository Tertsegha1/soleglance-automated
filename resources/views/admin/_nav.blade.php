<div class="flex flex-wrap gap-2 mb-6">
    @foreach (['admin.dashboard' => 'Overview', 'admin.users.index' => 'Users', 'admin.posts.index' => 'Posts', 'admin.news.index' => 'News sources', 'admin.audit' => 'Audit log', 'admin.models' => 'Models'] as $route => $label)
        <a href="{{ route($route) }}" class="rounded-full px-3 py-1 text-sm border {{ request()->routeIs($route) ? 'bg-slate-900 text-white border-slate-900' : 'bg-white hover:bg-slate-50' }}">{{ $label }}</a>
    @endforeach
</div>

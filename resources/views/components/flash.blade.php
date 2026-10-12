@if (session('status'))
    <div role="status" class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
@endif
@if ($errors->any() && !request()->routeIs('login', 'register', 'profile.*', 'password.*'))
    <div role="alert" class="mb-4 rounded-lg bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-800">
        <ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

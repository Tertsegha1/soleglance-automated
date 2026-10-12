@props(['user'])
<img src="{{ $user->avatarUrl() }}" alt="" {{ $attributes->merge(['class' => 'rounded-full object-cover bg-slate-200 shrink-0']) }} loading="lazy">

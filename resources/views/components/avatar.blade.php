@props(['user', 'size' => 'md'])
@php
    $sizes = ['sm' => 'size-7 text-[11px]', 'md' => 'size-9 text-xs', 'lg' => 'size-12 text-base', 'xl' => 'size-[72px] text-[26px]'];
    $url = $user?->avatarUrl();
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex shrink-0 items-center justify-center overflow-hidden rounded-full font-extrabold text-white '.$sizes[$size]]) }}
      @unless ($url) style="background: {{ $user ? 'hsl('.$user->avatarHue().' 45% 38%)' : 'var(--muted)' }}" @endunless
      title="{{ $user?->name ?? 'Ehemaliges Mitglied' }}">
    @if ($url)
        <img src="{{ $url }}" alt="" class="size-full object-cover" loading="lazy">
    @else
        {{ $user?->initials() ?: '?' }}
    @endif
</span>

@props(['user', 'size' => 'md'])
@php
    $sizes = ['sm' => 'size-7 text-[11px]', 'md' => 'size-9 text-xs', 'lg' => 'size-12 text-base'];
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex shrink-0 items-center justify-center rounded-full bg-accent-soft font-extrabold text-accent-ink '.$sizes[$size]]) }} title="{{ $user?->name ?? 'Ehemaliges Mitglied' }}">
    {{ $user?->initials() ?: '?' }}
</span>

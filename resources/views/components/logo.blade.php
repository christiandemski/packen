<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5 text-lg font-extrabold tracking-tight']) }}>
    <span class="inline-flex size-9 items-center justify-center rounded-chip bg-accent text-white">
        <x-icon name="luggage" size="20" />
    </span>
    {{ config('app.name') }}
</span>

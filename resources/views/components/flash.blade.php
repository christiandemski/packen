@if (session('status'))
    <div role="status" class="mb-5 flex items-center gap-2.5 rounded-chip bg-accent-soft px-4 py-3 text-sm font-bold text-accent-ink">
        <x-icon name="check" size="18" />
        <span>{{ session('status') }}</span>
    </div>
@endif

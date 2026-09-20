@if (session('status'))
    <div role="status"
         class="animate-rise mb-6 flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50/90 px-4 py-3 text-sm text-emerald-800 shadow-sm dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-200">
        <x-icon name="check" class="mt-0.5 size-4 shrink-0" />
        <p>{{ session('status') }}</p>
    </div>
@endif

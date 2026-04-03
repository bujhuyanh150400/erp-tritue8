<div class="space-y-6">
    <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-4 dark:border-amber-900/40 dark:bg-amber-950/20">

        <div class="flex items-center gap-2 text-sm text-amber-700 dark:text-amber-300">
            <x-heroicon-s-star class="w-4 h-4 text-yellow-500"/>
            <span>Tổng sao hiện có</span>
        </div>

        <div class="mt-1 text-3xl font-semibold text-amber-600 dark:text-amber-200">
            {{ number_format($totalPoints, 0, ',', '.') }} sao
        </div>
    </div>

    {{ $this->table }}
</div>

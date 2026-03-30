<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-lg font-medium tracking-tight">Hiệu suất (KPI)</h2>
        <div class="flex items-center gap-2">
            <label for="selectedMonth" class="text-sm font-medium text-gray-700 dark:text-gray-200">Tháng:</label>
            <input
                type="month"
                id="selectedMonth"
                wire:model.live="selectedMonth"
                class="rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
            >
        </div>
    </div>

    @if(!empty($warnings))
        <div class="space-y-2">
            @foreach($warnings as $warning)
                <div class="flex items-center gap-x-3 rounded-lg px-4 py-3 text-sm {{ $warning['type'] === 'danger' ? 'bg-red-50 text-red-600 dark:bg-red-900/30 dark:text-red-400' : 'bg-amber-50 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400' }}">
                    <x-heroicon-m-exclamation-triangle class="h-5 w-5" />
                    <p>{{ $warning['message'] }}</p>
                </div>
            @endforeach
        </div>
    @endif

    @livewire(\App\Filament\Resources\Teachers\Widgets\TeacherKpiStatsOverview::class, [
        'record' => $record,
        'selectedMonth' => $selectedMonth,
    ], key('stats-' . $selectedMonth))

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        @livewire(\App\Filament\Resources\Teachers\Widgets\TeacherAttendanceChart::class, [
            'record' => $record,
            'selectedMonth' => $selectedMonth,
        ], key('chart-' . $selectedMonth))

        {{-- Có thể thêm biểu đồ khác ở đây (ví dụ: điểm TB qua các tháng) --}}
    </div>
</div>

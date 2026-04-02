<x-filament-panels::page>
    {{ $this->form }}

    {{-- Danh sách loại lịch --}}
    <div
        class="flex flex-wrap w-fit gap-4 p-4 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
        @foreach(\App\Constants\ScheduleType::getLabelsAndColors() as $type)
            <div class="flex items-center gap-2">
                {{-- Ô vuông hiển thị màu --}}
                <div
                    class="w-4 h-4 rounded-full"
                    style="background-color: {{ $type['color'] }}"
                ></div>
                {{-- Nhãn của loại lịch --}}
                <span class="text-sm font-medium text-gray-600 dark:text-gray-300">{{ $type['label'] }}</span>
            </div>
        @endforeach
    </div>

    @livewire(\App\Filament\Widgets\AdminCalendarWidget::class)
</x-filament-panels::page>

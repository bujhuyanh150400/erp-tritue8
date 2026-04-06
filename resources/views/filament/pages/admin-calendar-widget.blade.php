<div x-data="{ activeTeachers: [] }"
     @update-teachers.window="activeTeachers = $event.detail.data">

    {{-- 1. BẢNG CHÚ THÍCH (Vẽ bằng Alpine.js) --}}
    {{-- Lớp x-cloak giúp ẩn thẻ này đi khi Alpine chưa load xong, x-show để tự động hiện khi có data --}}
    <div x-cloak
         x-show="activeTeachers.length > 0"
         class="mb-4 p-3 bg-white dark:bg-gray-900 rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">

        <div class="flex flex-wrap items-center gap-4">
            <span class="text-sm font-bold text-gray-500 dark:text-gray-400">
                Phân màu giáo viên:
            </span>

            {{-- Vòng lặp của Alpine.js --}}
            <template x-for="teacher in activeTeachers" :key="teacher.name">
                <div class="flex items-center gap-2">
                    <div class="w-4 h-4 rounded shadow-inner"
                         :style="`background-color: ${teacher.color};`"></div>
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-200"
                          x-text="teacher.name">
                    </span>
                </div>
            </template>
        </div>
    </div>

    {{-- 2. NHÚNG LỊCH GỐC CỦA PLUGIN VÀO --}}
    <div wire:ignore
         wire:loading.class="pointer-events-none opacity-70"
         wire:target="onEventClick">
        @include('filament-fullcalendar::fullcalendar')
    </div>

    <div wire:loading.flex
         wire:target="onEventClick"
         class="fixed inset-0 z-9999 flex-col items-center justify-center bg-gray-900/50 backdrop-blur-sm transition-opacity">

        {{-- Dùng luôn Icon Loading xoay xoay chuẩn của Filament cho đồng bộ --}}
        <x-filament::loading-indicator class="w-12 h-12 text-primary-400" />

        <p class="mt-4 text-base font-medium text-white">
            Chờ 1 chút...
        </p>
    </div>
</div>

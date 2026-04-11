@php
    $currentPoints = $getState() ?? 0;
    $studentId = $getRecord()['student_id'];
    $isLocked = $this->record->isLocked();
@endphp

<div class="flex items-center justify-center">
    <div @class([
        'inline-flex items-stretch rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden',
        'opacity-50 pointer-events-none' => $isLocked,
    ])>
        {{-- Nút TRỪ --}}
        <button
            type="button"
            wire:click.stop="quickMinusPoints('{{ $studentId }}')"
            wire:loading.attr="disabled" {{-- Khóa nút khi đang load --}}
            class="relative flex items-center justify-center px-3 py-2 bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-400 hover:bg-danger-50 hover:text-danger-600 dark:hover:bg-danger-900/20 transition-colors border-r border-gray-200 dark:border-gray-700 disabled:opacity-70"
        >
            {{-- Icon mặc định: ẩn khi function quickMinusPoints đang chạy --}}
            <x-heroicon-m-minus wire:loading.remove wire:target="quickMinusPoints('{{ $studentId }}')" class="w-4 h-4" />

            {{-- Icon Loading: chỉ hiện khi function quickMinusPoints đang chạy --}}
            <x-filament::loading-indicator wire:loading wire:target="quickMinusPoints('{{ $studentId }}')" class="w-4 h-4 text-danger-500" />
        </button>

        {{-- Hiển thị ĐIỂM --}}
        <div class="flex flex-col items-center justify-center px-4 py-1 bg-gray-50/50 dark:bg-gray-900/50 min-w-[70px]">
            <span class="text-sm font-black text-gray-900 dark:text-white leading-none">
                {{ $currentPoints }}
            </span>
            <span class="text-[9px] font-bold uppercase tracking-tighter text-warning-600 dark:text-warning-400 mt-0.5">
                SAO
            </span>
        </div>

        {{-- Nút CỘNG --}}
        <button
            type="button"
            wire:click.stop="quickPlusPoints('{{ $studentId }}')"
            wire:loading.attr="disabled"
            class="relative flex items-center justify-center px-3 py-2 bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-400 hover:bg-success-50 hover:text-success-600 dark:hover:bg-success-900/20 transition-colors disabled:opacity-70"
        >
            {{-- Icon mặc định: ẩn khi function quickPlusPoints đang chạy --}}
            <x-heroicon-m-plus wire:loading.remove wire:target="quickPlusPoints('{{ $studentId }}')" class="w-4 h-4" />

            {{-- Icon Loading: chỉ hiện khi function quickPlusPoints đang chạy --}}
            <x-filament::loading-indicator wire:loading wire:target="quickPlusPoints('{{ $studentId }}')" class="w-4 h-4 text-success-500" />
        </button>
    </div>
</div>

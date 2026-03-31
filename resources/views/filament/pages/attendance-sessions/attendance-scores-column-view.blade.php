{{-- resources/views/filament/tables/columns/attendance-scores.blade.php --}}
@php
    $scores = collect($getState() ?? []);
    $limit = 2;
    $hasMore = $scores->count() > $limit;
    $visibleScores = $scores->take($limit);

    // Tạo một Key duy nhất dựa trên ID học sinh và số lượng điểm
    // Khi số lượng điểm thay đổi, wire:key thay đổi -> Alpine sẽ được khởi tạo lại
    $uniqueKey = 'scores-' . $getRecord()['student_id'] . '-' . $scores->count();
@endphp

<div
    x-data="{ open: false, selectedScore: null }"
    wire:key="{{ $uniqueKey }}"
    class="flex flex-wrap gap-1 justify-center px-2 py-1"
>
    @if($scores->isEmpty())
        <span class="text-gray-400 italic text-xs">Chưa có điểm</span>
    @else
        {{-- Badge thu gọn --}}
        @foreach($visibleScores as $score)
            <button
                type="button"
                @click="open = true; selectedScore = @js($score)"
                class="flex items-center gap-1 border border-primary-200 bg-primary-50 dark:bg-primary-900/30 px-2 py-0.5 rounded text-xs hover:border-primary-500 transition-colors cursor-pointer"
            >
                <span class="font-bold text-primary-700 dark:text-primary-400">{{ $score['exam_name'] }}:</span>
                <span class="font-black text-primary-900 dark:text-white">{{ $score['score'] }}</span>
            </button>
        @endforeach

        {{-- Nút +N --}}
        @if($hasMore)
            <button
                type="button"
                @click="open = true; selectedScore = null"
                class="px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-800 text-[10px] font-bold text-gray-600 dark:text-gray-400 hover:bg-gray-200 transition-colors cursor-pointer"
            >
                +{{ $scores->count() - $limit }}
            </button>
        @endif

        {{-- MODAL CHI TIẾT --}}
        <template x-teleport="body">
            <div
                x-show="open"
                x-transition.opacity
                class="fixed inset-0 z-[9999] flex items-center justify-center bg-gray-900/60 backdrop-blur-sm p-4"
                style="display: none;"
                @keydown.escape.window="open = false"
            >
                <div
                    @click.away="open = false"
                    class="bg-white dark:bg-gray-900 w-full max-w-lg rounded-xl shadow-2xl border border-gray-200 dark:border-gray-700 overflow-hidden"
                >
                    {{-- Header --}}
                    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/50">
                        <h3 class="text-sm font-bold text-gray-700 dark:text-gray-200 flex items-center gap-2">
                            <x-filament::icon icon="heroicon-m-academic-cap" class="h-4 w-4 text-primary-500" />
                            Chi tiết điểm: {{ $getRecord()['student_name'] }}
                        </h3>
                        <button @click="open = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-white">
                            <x-filament::icon icon="heroicon-m-x-mark" class="h-5 w-5" />
                        </button>
                    </div>

                    {{-- Body --}}
                    <div class="p-4 max-h-[70vh] overflow-y-auto space-y-3">
                        {{-- Quan trọng: Dùng trực tiếp @js($scores) trong x-for để luôn lấy data mới nhất từ Livewire --}}
                        <template x-for="(s, index) in @js($scores)" :key="index">
                            <div
                                :class="selectedScore && selectedScore.exam_slot === s.exam_slot ? 'ring-2 ring-primary-500 bg-primary-50/30' : 'bg-gray-50 dark:bg-gray-800/40'"
                                class="p-3 rounded-lg border border-gray-100 dark:border-gray-800 transition-all"
                            >
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-xs font-bold uppercase tracking-wider text-primary-600 dark:text-primary-400" x-text="s.exam_name"></span>
                                    <div class="flex items-baseline gap-1">
                                        <span class="text-xl font-black text-gray-900 dark:text-white" x-text="s.score"></span>
                                        <span class="text-xs text-gray-500" x-text="'/' + (s.max_score || 10)"></span>
                                    </div>
                                </div>

                                <div x-show="s.note" class="flex gap-2 items-start p-2 rounded bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 mt-2">
                                    <x-filament::icon icon="heroicon-m-chat-bubble-bottom-center-text" class="h-4 w-4 text-gray-400 mt-0.5" />
                                    <p class="text-xs text-gray-600 dark:text-gray-400 italic" x-text="s.note"></p>
                                </div>
                            </div>
                        </template>
                    </div>

                    <div class="px-4 py-3 bg-gray-50 dark:bg-gray-800/50 text-right">
                        <x-filament::button color="gray" size="sm" @click="open = false">
                            Đóng
                        </x-filament::button>
                    </div>
                </div>
            </div>
        </template>
    @endif
</div>

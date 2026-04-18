@php
    $scores = collect($getState() ?? []);
    $record = $getRecord();
    $recordId = data_get($record, 'id', 'record');
    $studentId = data_get($record, 'student_id', 'student');
    $uniqueKey = 'student-report-scores-' . $recordId . '-' . $studentId . '-' . $scores->count();
@endphp

<div
    x-data="{
        open: false,
        selectedScore: null
    }"
    wire:key="{{ $uniqueKey }}"
    class="flex flex-wrap gap-1 justify-center px-2 py-1"
>
    @if($scores->isEmpty())
        <span class="text-gray-400 italic text-xs">Chưa có điểm</span>
    @else
        @foreach($scores as $score)
            <button
                type="button"
                @click="open = true; selectedScore = @js($score)"
                class="flex items-center gap-1.5 border border-primary-200 bg-primary-50 dark:bg-primary-900/30 px-2 py-1 rounded text-xs hover:border-primary-500 hover:bg-primary-100 transition-all cursor-pointer group"
            >
                <div class="flex flex-col items-start leading-tight">
                    <span class="font-bold text-primary-700 dark:text-primary-400 text-[10px] uppercase">{{ $score['exam_name'] }}</span>
                    <span class="font-black text-primary-900 dark:text-white text-sm">{{ $score['score'] }}</span>
                </div>

                @if(!empty($score['note']))
                    <x-filament::icon
                        icon="heroicon-m-chat-bubble-left-right"
                        class="h-3 w-3 text-primary-400 group-hover:text-primary-600"
                        title="Có ghi chú"
                    />
                @endif
            </button>
        @endforeach

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
                    class="bg-white dark:bg-gray-900 w-full max-w-md rounded-xl shadow-2xl border border-gray-200 dark:border-gray-700 overflow-hidden"
                >
                    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/50">
                        <h3 class="text-sm font-bold text-gray-700 dark:text-gray-200 flex items-center gap-2">
                            <x-filament::icon icon="heroicon-m-document-check" class="h-4 w-4 text-primary-500" />
                            Chi tiết đầu điểm
                        </h3>
                        <button @click="open = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-white">
                            <x-filament::icon icon="heroicon-m-x-mark" class="h-5 w-5" />
                        </button>
                    </div>

                    <div class="p-6" x-show="selectedScore">
                        <div class="text-center mb-6">
                            <p class="text-xs font-bold uppercase tracking-[0.2em] text-gray-500 mb-1" x-text="selectedScore?.exam_name"></p>
                            <div class="flex items-center justify-center gap-2">
                                <span class="text-5xl font-black text-primary-600 dark:text-primary-400" x-text="selectedScore?.score"></span>
                                <div class="text-left leading-none">
                                    <span class="text-lg font-bold text-gray-400" x-text="'/ ' + (selectedScore?.max_score || 10)"></span>
                                    <p class="text-[10px] text-gray-400 font-medium">ĐIỂM SỐ</p>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Nhận xét của giáo viên</label>
                            <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-800/50 border border-dashed border-gray-200 dark:border-gray-700">
                                <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed"
                                   x-text="selectedScore?.note ? selectedScore.note : 'Không có ghi chú cho đầu điểm này.'"></p>
                            </div>
                        </div>
                    </div>

                    <div class="px-4 py-3 bg-gray-50 dark:bg-gray-800/50 text-center border-t border-gray-100 dark:border-gray-800">
                        <x-filament::button color="gray" size="sm" @click="open = false" class="w-full">
                            Đóng
                        </x-filament::button>
                    </div>
                </div>
            </div>
        </template>
    @endif
</div>


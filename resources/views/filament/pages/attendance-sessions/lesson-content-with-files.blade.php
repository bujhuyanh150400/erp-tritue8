<div>
    <div class="prose dark:prose-invert max-w-none prose-sm sm:prose-base text-gray-800 dark:text-gray-200">
        {!! $state ?? '<span class="text-gray-400 italic">Chưa có nội dung nội dung bài giảng...</span>' !!}
    </div>

    @if(!empty($files))
        <div class="mt-6 pt-4 border-t border-gray-100 dark:border-gray-800">
            <div class="flex items-center gap-2 mb-3">
                <x-heroicon-m-paper-clip class="w-4 h-4 text-gray-400" />
                <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Tài liệu đính kèm ({{ count($files) }})</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                @foreach($files as $file)
                    @php
                        $url = \Illuminate\Support\Facades\Storage::url($file);
                        $name = basename($file);
                        $ext = pathinfo($name, PATHINFO_EXTENSION);

                        // Xác định icon dựa trên đuôi file
                        $icon = match(strtolower($ext)) {
                            'pdf' => 'heroicon-m-document-text',
                            'jpg', 'jpeg', 'png', 'gif', 'svg' => 'heroicon-m-photo',
                            'doc', 'docx' => 'heroicon-m-document-minus',
                            'xls', 'xlsx' => 'heroicon-m-table-cells',
                            default => 'heroicon-m-document',
                        };

                        $colorClass = match(strtolower($ext)) {
                            'pdf' => 'text-red-500',
                            'jpg', 'jpeg', 'png' => 'text-blue-500',
                            'xls', 'xlsx' => 'text-green-500',
                            default => 'text-gray-500',
                        };
                    @endphp

                    <a href="{{ $url }}"
                       target="_blank"
                       class="group flex items-center gap-3 p-2.5 bg-white dark:bg-gray-800/50 border border-gray-200 dark:border-gray-700 rounded-xl hover:border-primary-500 dark:hover:border-primary-400 hover:shadow-sm transition-all duration-200"
                    >
                        <div class="shrink-0 w-10 h-10 flex items-center justify-center rounded-lg bg-gray-50 dark:bg-gray-800 group-hover:bg-primary-50 dark:group-hover:bg-primary-900/30 transition-colors">
                            @svg($icon, ['class' => "w-5 h-5 {$colorClass}"])
                        </div>

                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300 truncate group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">
                                {{ $name }}
                            </p>
                            <p class="text-[10px] text-gray-400 uppercase font-bold tracking-tighter">
                                {{ $ext }} File
                            </p>
                        </div>

                        <div class="shrink-0 opacity-0 group-hover:opacity-100 transition-opacity pr-2">
                            <x-heroicon-m-arrow-down-tray class="w-4 h-4 text-primary-500" />
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</div>

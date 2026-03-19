@vite(['resources/css/app.css'])
<div>
    <div class="mb-6 flex items-center gap-4">
        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Chọn tháng báo cáo:</label>
        <input type="month" wire:model.live="selectedMonth"
               class="rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white">
    </div>

    <div class="space-y-6">
        @forelse ($reportData as $data)
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl p-5 shadow-sm">
                <div class="border-b border-gray-200 dark:border-gray-800 pb-3 mb-4 flex justify-between items-center">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">
                        Môn: {{ $data['info']->subject_name }}
                    </h3>
                    <span class="text-sm text-gray-500">
                        Lớp: <span class="font-medium text-gray-900 dark:text-white">{{ $data['info']->class_name }}</span>
                        | GV: <span class="font-medium text-gray-900 dark:text-white">{{ $data['info']->teacher_name }}</span>
                    </span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-4">
                    <div class="bg-gray-50 dark:bg-gray-800/50 p-4 rounded-lg">
                        <h4 class="text-sm font-semibold mb-2 text-gray-700 dark:text-gray-300">Thống kê điểm danh</h4>
                        <div class="flex justify-between text-sm">
                            <span>Tổng số buổi: <b>{{ $data['stats']->tong_buoi ?? 0 }}</b></span>
                            <span class="text-green-600">Có mặt: <b>{{ $data['stats']->co_mat ?? 0 }}</b></span>
                            <span class="text-blue-600">Điểm TB: <b>{{ $data['stats']->diem_tb ?? 'N/A' }}</b></span>
                        </div>
                    </div>

                    <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg border border-blue-100 dark:border-blue-800">
                        <h4 class="text-sm font-semibold mb-1 text-blue-800 dark:text-blue-300">Nhận xét của Giáo viên</h4>
                        @if($data['review'])
                            <p class="text-sm text-gray-700 dark:text-gray-300 italic">"{{ $data['review']->content }}"</p>
                            <span class="text-xs text-gray-500 mt-2 block">
                                Trạng thái: {{ $data['review']->status }} | Ngày gửi: {{ \Carbon\Carbon::parse($data['review']->submitted_at)->format('d/m/Y') }}
                            </span>
                        @else
                            <p class="text-sm text-gray-500 italic">Chưa có nhận xét cho tháng này.</p>
                        @endif
                    </div>
                </div>

                @if($data['scores']->isNotEmpty())
                    <div class="overflow-hidden border border-gray-200 dark:border-gray-700 rounded-lg">
                        <table class="w-full text-sm text-left">
                            <thead class="bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                            <tr>
                                <th class="px-4 py-2">Ngày học</th>
                                <th class="px-4 py-2">Tên bài kiểm tra</th>
                                <th class="px-4 py-2">Điểm</th>
                                <th class="px-4 py-2">Ghi chú</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($data['scores'] as $score)
                                <tr class="border-t border-gray-200 dark:border-gray-700">
                                    <td class="px-4 py-2">{{ \Carbon\Carbon::parse($score->session_date)->format('d/m/Y') }}</td>
                                    <td class="px-4 py-2 font-medium">{{ $score->exam_name }}</td>
                                    <td class="px-4 py-2 text-primary-600 font-bold">{{ $score->score }} / {{ $score->max_score }}</td>
                                    <td class="px-4 py-2 text-gray-500">{{ $score->note }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-sm text-gray-500 text-center py-2">Không có điểm nào trong tháng này.</p>
                @endif
            </div>
        @empty
            <div class="text-center py-8 text-gray-500">
                Học sinh này hiện chưa được xếp vào lớp học nào.
            </div>
        @endforelse
    </div>
</div>

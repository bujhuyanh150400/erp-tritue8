@php
    $student = $detailData['student'] ?? null;
    $items = collect($detailData['items'] ?? []);
    $totals = $detailData['totals'] ?? [];
@endphp

<div class="space-y-5">
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="text-sm text-gray-500">Học sinh</div>
            <div class="mt-2 text-xl font-semibold text-gray-900">{{ $student?->full_name ?? '-' }}</div>
            <div class="mt-1 text-sm text-gray-500">Tháng {{ $detailData['month'] ?? '-' }}</div>
        </div>
        <div class="rounded-2xl border border-violet-200 bg-violet-50 p-5 shadow-sm">
            <div class="text-sm text-violet-700">Khối</div>
            <div class="mt-2 text-xl font-semibold text-violet-950">
                {{ $items->pluck('grade_levels')->filter()->unique()->implode(', ') ?: '-' }}
            </div>
        </div>
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 shadow-sm">
            <div class="text-sm text-amber-700">Tổng số buổi / Buổi có mặt</div>
            <div class="mt-2 text-xl font-semibold text-amber-950">
                {{ number_format((int) ($totals['total_sessions'] ?? 0), 0, ',', '.') }}
                /
                {{ number_format((int) ($totals['attended_sessions'] ?? 0), 0, ',', '.') }}
            </div>
        </div>
        <div class="rounded-2xl border border-sky-200 bg-sky-50 p-5 shadow-sm">
            <div class="text-sm text-sky-700">Tổng học phí</div>
            <div class="mt-2 text-xl font-semibold text-sky-950">
                {{ number_format((int) ($totals['total_study_fee'] ?? 0), 0, ',', '.') }}đ
            </div>
            <div class="mt-1 text-sm text-sky-700">
                Nợ cũ: {{ number_format((int) ($totals['previous_debt'] ?? 0), 0, ',', '.') }}đ
            </div>
        </div>
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm">
            <div class="text-sm text-emerald-700">Tổng phải thu</div>
            <div class="mt-2 text-xl font-semibold text-emerald-950">
                {{ number_format((int) ($totals['total_amount'] ?? 0), 0, ',', '.') }}đ
            </div>
            <div class="mt-1 text-sm text-emerald-700">
                Còn lại: {{ number_format((int) ($totals['remaining_amount'] ?? 0), 0, ',', '.') }}đ
            </div>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-200 px-6 py-4">
            <h3 class="text-base font-semibold text-gray-900">Chi tiết môn học</h3>
            <p class="mt-1 text-sm text-gray-500">Tổng hợp theo các môn học sinh đang học trong tháng này.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Môn học</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Khối</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Lớp</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Giáo viên</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Tổng buổi / Có mặt</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Tiền môn học</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Nợ cũ</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Tổng phải thu</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                @forelse($items as $item)
                    <tr>
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $item['subject_name'] }}</td>
                        <td class="px-4 py-3 text-gray-700">{{ $item['grade_levels'] ?: '-' }}</td>
                        <td class="px-4 py-3 text-gray-700">{{ $item['class_names'] ?: '-' }}</td>
                        <td class="px-4 py-3 text-gray-700">{{ $item['teacher_names'] ?: '-' }}</td>
                        <td class="px-4 py-3 text-gray-700">
                            {{ number_format((int) $item['total_sessions'], 0, ',', '.') }}
                            /
                            {{ number_format((int) $item['attended_sessions'], 0, ',', '.') }}
                        </td>
                        <td class="px-4 py-3 text-gray-700">{{ number_format((int) $item['total_study_fee'], 0, ',', '.') }}đ</td>
                        <td class="px-4 py-3 text-gray-700">{{ number_format((int) $item['previous_debt'], 0, ',', '.') }}đ</td>
                        <td class="px-4 py-3 font-semibold text-gray-900">{{ number_format((int) $item['total_amount'], 0, ',', '.') }}đ</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-6 text-center text-gray-500">Không có dữ liệu môn học trong tháng này.</td>
                    </tr>
                @endforelse
                </tbody>
                <tfoot class="bg-gray-50">
                <tr>
                    <td colspan="4" class="px-4 py-3 font-semibold text-gray-900">Tổng cộng</td>
                    <td class="px-4 py-3 font-semibold text-gray-900">
                        {{ number_format((int) ($totals['total_sessions'] ?? 0), 0, ',', '.') }}
                        /
                        {{ number_format((int) ($totals['attended_sessions'] ?? 0), 0, ',', '.') }}
                    </td>
                    <td class="px-4 py-3 font-semibold text-gray-900">{{ number_format((int) ($totals['total_study_fee'] ?? 0), 0, ',', '.') }}đ</td>
                    <td class="px-4 py-3 font-semibold text-gray-900">{{ number_format((int) ($totals['previous_debt'] ?? 0), 0, ',', '.') }}đ</td>
                    <td class="px-4 py-3 font-semibold text-gray-900">{{ number_format((int) ($totals['total_amount'] ?? 0), 0, ',', '.') }}đ</td>
                </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-3">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="text-sm text-gray-500">Đã thanh toán</div>
            <div class="mt-2 text-lg font-semibold text-emerald-700">
                {{ number_format((int) ($totals['paid_amount'] ?? 0), 0, ',', '.') }}đ
            </div>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="text-sm text-gray-500">Còn lại</div>
            <div class="mt-2 text-lg font-semibold text-rose-700">
                {{ number_format((int) ($totals['remaining_amount'] ?? 0), 0, ',', '.') }}đ
            </div>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="text-sm text-gray-500">Số môn đang học</div>
            <div class="mt-2 text-lg font-semibold text-gray-900">
                {{ number_format($items->count(), 0, ',', '.') }}
            </div>
        </div>
    </div>
</div>

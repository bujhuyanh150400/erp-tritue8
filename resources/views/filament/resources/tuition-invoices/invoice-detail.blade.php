<div class="space-y-4 text-sm">
    <div class="grid grid-cols-2 gap-4">
        <div><strong>Số HĐ:</strong> {{ $record->invoice_number }}</div>
        <div><strong>Tháng:</strong> {{ $record->month }}</div>
        <div><strong>Học sinh:</strong> {{ $record->student->full_name }}</div>
        <div><strong>Lớp:</strong> {{ $record->class->name }}</div>
        <div><strong>Giáo viên:</strong> {{ $record->class->teacher?->full_name ?? '-' }}</div>
        <div><strong>Trạng thái:</strong> {{ $record->status->label() }}</div>
        <div><strong>Tổng buổi:</strong> {{ $record->total_sessions }}</div>
        <div><strong>Buổi có mặt:</strong> {{ $record->attended_sessions }}</div>
        <div><strong>Học phí:</strong> {{ number_format((int) $record->total_study_fee, 0, ',', '.') }}đ</div>
        <div><strong>Giảm trừ:</strong> {{ number_format((int) $record->discount_amount, 0, ',', '.') }}đ</div>
        <div><strong>Nợ cũ:</strong> {{ number_format((int) $record->previous_debt, 0, ',', '.') }}đ</div>
        <div><strong>Tổng phải thu:</strong> {{ number_format((int) $record->total_amount, 0, ',', '.') }}đ</div>
        <div><strong>Đã thanh toán:</strong> {{ number_format((int) $record->paid_amount, 0, ',', '.') }}đ</div>
        <div><strong>Còn lại:</strong> {{ number_format($record->getRemainingAmount(), 0, ',', '.') }}đ</div>
    </div>

    <div>
        <strong>Ghi chú:</strong>
        <div class="mt-1 rounded-lg border border-gray-200 p-3">
            {{ $record->note ?: 'Không có ghi chú.' }}
        </div>
    </div>

    <div>
        <strong>Lịch sử thanh toán:</strong>
        <div class="mt-2 overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead>
                <tr>
                    <th class="px-3 py-2 text-left">Thời gian</th>
                    <th class="px-3 py-2 text-left">Số tiền</th>
                    <th class="px-3 py-2 text-left">Phương thức</th>
                    <th class="px-3 py-2 text-left">Người xử lý</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                @forelse($record->logs->where('is_cancelled', false) as $log)
                    <tr>
                        <td class="px-3 py-2">{{ $log->paid_at?->format('d/m/Y H:i') }}</td>
                        <td class="px-3 py-2">{{ number_format((int) $log->amount, 0, ',', '.') }}đ</td>
                        <td class="px-3 py-2">{{ $log->payment_method?->label() ?? '-' }}</td>
                        <td class="px-3 py-2">{{ $log->changedBy?->name ?? '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-3 py-3 text-center text-gray-500">Chưa có lịch sử thanh toán</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

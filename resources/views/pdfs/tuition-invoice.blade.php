<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>{{ $invoice->invoice_number }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #111827;
            line-height: 1.45;
        }
        .header {
            margin-bottom: 20px;
        }
        .title {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 6px;
        }
        .subtitle {
            color: #6b7280;
        }
        .section {
            margin-top: 20px;
        }
        .grid {
            width: 100%;
            border-collapse: collapse;
        }
        .grid td {
            width: 50%;
            padding: 6px 0;
            vertical-align: top;
        }
        .label {
            font-weight: 700;
        }
        .summary,
        .logs {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .summary th,
        .summary td,
        .logs th,
        .logs td {
            border: 1px solid #d1d5db;
            padding: 8px 10px;
            text-align: left;
        }
        .summary th,
        .logs th {
            background: #f3f4f6;
            font-weight: 700;
        }
        .note {
            border: 1px solid #d1d5db;
            padding: 10px;
            min-height: 48px;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">Hóa Đơn Học Phí</div>
        <div class="subtitle">Mã hóa đơn: {{ $invoice->invoice_number }}</div>
    </div>

    <table class="grid">
        <tr>
            <td><span class="label">Học sinh:</span> {{ $invoice->student->full_name }}</td>
            <td><span class="label">Tháng:</span> {{ $invoice->month }}</td>
        </tr>
        <tr>
            <td><span class="label">Lớp:</span> {{ $invoice->class->name }}</td>
            <td><span class="label">Giáo viên:</span> {{ $invoice->class->teacher?->full_name ?? '-' }}</td>
        </tr>
        <tr>
            <td><span class="label">Tổng buổi:</span> {{ $invoice->total_sessions }}</td>
            <td><span class="label">Buổi có mặt:</span> {{ $invoice->attended_sessions }}</td>
        </tr>
        <tr>
            <td><span class="label">Trạng thái:</span> {{ $invoice->status->label() }}</td>
            <td><span class="label">Ngày xuất:</span> {{ now()->format('d/m/Y H:i') }}</td>
        </tr>
    </table>

    <div class="section">
        <table class="summary">
            <thead>
                <tr>
                    <th>Học phí</th>
                    <th>Nợ cũ</th>
                    <th>Tổng phải thu</th>
                    <th>Đã thanh toán</th>
                    <th>Còn lại</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ number_format((int) $invoice->total_study_fee, 0, ',', '.') }}đ</td>
                    <td>{{ number_format((int) $invoice->previous_debt, 0, ',', '.') }}đ</td>
                    <td>{{ number_format((int) $invoice->total_amount, 0, ',', '.') }}đ</td>
                    <td>{{ number_format((int) $invoice->paid_amount, 0, ',', '.') }}đ</td>
                    <td>{{ number_format($invoice->getRemainingAmount(), 0, ',', '.') }}đ</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="section">
        <div class="label">Ghi chú</div>
        <div class="note">{{ $invoice->note ?: 'Không có ghi chú.' }}</div>
    </div>

    <div class="section">
        <div class="label">Lịch sử thanh toán</div>
        <table class="logs">
            <thead>
                <tr>
                    <th>Thời gian</th>
                    <th>Số tiền</th>
                    <th>Phương thức</th>
                    <th>Người xử lý</th>
                </tr>
            </thead>
            <tbody>
                @forelse($invoice->logs->where('is_cancelled', false) as $log)
                    <tr>
                        <td>{{ $log->paid_at?->format('d/m/Y H:i') }}</td>
                        <td>{{ number_format((int) $log->amount, 0, ',', '.') }}đ</td>
                        <td>{{ $log->payment_method?->label() ?? '-' }}</td>
                        <td>{{ $log->changedBy?->name ?? '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">Chưa có lịch sử thanh toán</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</body>
</html>

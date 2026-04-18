<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>{{ $pdfData['document_number'] }}</title>
    <style>
        @page { margin: 18px; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #1f2937;
            line-height: 1.35;
            background: #ffffff;
        }
        .sheet {
            border: 1px solid #d5deea;
            border-radius: 16px;
            overflow: hidden;
        }
        .header {
            background: #0f3d75;
            color: #fff;
            padding: 14px 18px 12px;
            border-bottom: 4px solid #f3c623;
        }
        .header-table,
        .info-table,
        .subject-table,
        .footer-table {
            width: 100%;
            border-collapse: collapse;
        }
        .logo-cell {
            width: 72px;
            vertical-align: top;
        }
        .logo-box {
            width: 58px;
            height: 58px;
            border-radius: 50%;
            background: #fff;
            padding: 4px;
            text-align: center;
        }
        .logo-box img {
            width: 50px;
            height: 50px;
            object-fit: contain;
        }
        .center-name {
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.2px;
        }
        .main-title {
            margin-top: 3px;
            font-size: 30px;
            line-height: 1.05;
            font-weight: 800;
            color: #ffdf53;
        }
        .sub-title {
            margin-top: 3px;
            font-size: 12px;
            font-style: italic;
            color: #dbeafe;
        }
        .content {
            padding: 10px 14px 14px;
            background: #fff;
        }
        .cards {
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px 8px;
            margin: 0 -8px 8px;
        }
        .card {
            border: 1px solid #d7e1ee;
            border-radius: 12px;
            padding: 10px 12px;
            vertical-align: top;
            background: #fff;
        }
        .card-title {
            font-size: 13px;
            font-weight: 800;
            color: #0f3d75;
            margin-bottom: 6px;
            text-transform: uppercase;
        }
        .meta-row {
            margin-bottom: 4px;
        }
        .meta-label {
            display: inline-block;
            min-width: 48px;
            color: #6b7280;
        }
        .meta-value {
            font-weight: 600;
        }
        .subject-table th {
            background: #0f3d75;
            color: #fff;
            font-weight: 700;
            padding: 8px 8px;
            border: 1px solid #cfd8e3;
            text-align: left;
        }
        .subject-table td {
            padding: 8px 8px;
            border: 1px solid #d8e0ea;
            background: #fff;
        }
        .subject-table .num {
            text-align: right;
            white-space: nowrap;
        }
        .subject-table .center {
            text-align: center;
        }
        .totals-line {
            margin: 8px 0 10px;
            text-align: right;
            font-weight: 800;
            font-size: 18px;
            color: #e11d48;
        }
        .totals-line span {
            color: #ef4444;
        }
        .footer-card {
            border: 1px solid #d7e1ee;
            border-radius: 12px;
            overflow: hidden;
            background: #fff;
        }
        .footer-card .footer-title {
            background: #0f3d75;
            color: #fff;
            font-weight: 800;
            padding: 8px 10px;
            font-size: 12px;
            text-transform: uppercase;
        }
        .debt-table {
            width: 100%;
            border-collapse: collapse;
        }
        .debt-table th,
        .debt-table td {
            border: 1px solid #d8e0ea;
            padding: 7px 8px;
        }
        .debt-table th {
            background: #f8fafc;
            text-align: left;
            color: #475569;
        }
        .debt-table .num {
            text-align: right;
            white-space: nowrap;
        }
        .highlight-card {
            background: #0f3d75;
            color: #fff;
            border-radius: 14px;
            padding: 12px 14px;
            text-align: center;
            margin-bottom: 10px;
        }
        .highlight-card .label {
            font-size: 11px;
            color: #cbd5e1;
            text-transform: uppercase;
        }
        .highlight-card .value {
            margin-top: 6px;
            font-size: 28px;
            color: #ffdf53;
            font-weight: 800;
        }
        .payment-note {
            border: 1px dashed #cbd5e1;
            border-radius: 12px;
            padding: 10px 12px;
            min-height: 88px;
        }
        .payment-note .line {
            margin-bottom: 4px;
        }
        .muted {
            color: #6b7280;
        }
        .small {
            font-size: 11px;
        }
    </style>
</head>
<body>
@php
    $student = $pdfData['student'];
    $totals = $pdfData['totals'] ?? [];
    $rows = collect($pdfData['subject_rows'] ?? []);
    $logs = collect($pdfData['logs'] ?? []);
    $logoPath = $pdfData['logo_path'] ?? null;
@endphp

<div class="sheet">
    <div class="header">
        <table class="header-table">
            <tr>
                <td class="logo-cell">
                    <div class="logo-box">
                        @if($logoPath && file_exists($logoPath))
                            <img src="{{ $logoPath }}" alt="Logo">
                        @endif
                    </div>
                </td>
                <td>
                    <div class="center-name">TRUNG TÂM PHÁT TRIỂN TƯ DUY - TRÍ TUỆ 8+</div>
                    <div class="main-title">PHIẾU THU HỌC PHÍ</div>
                    <div class="sub-title">Tháng {{ $pdfData['display_month'] }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="content">
        <table class="cards">
            <tr>
                <td class="card" width="52%">
                    <div class="card-title">Học sinh</div>
                    <div class="meta-row"><span class="meta-label">Họ tên:</span> <span class="meta-value">{{ $student->full_name }}</span></div>
                    <div class="meta-row"><span class="meta-label">Khối:</span> <span class="meta-value">{{ $pdfData['grade_display'] }}</span></div>
                    <div class="meta-row"><span class="meta-label">Mã HS:</span> <span class="meta-value">HS{{ $student->id }}</span></div>
                </td>
                <td class="card">
                    <div class="card-title">Thanh toán</div>
                    <div class="meta-row"><span class="meta-label">Người nhận:</span> <span class="meta-value">{{ $pdfData['issuer_name'] }}</span></div>
                    <div class="meta-row"><span class="meta-label">HT:</span> <span class="meta-value">{{ $pdfData['export_payment_method']?->label() ?? '-' }}</span></div>
                    <div class="meta-row"><span class="meta-label">Ngày:</span> <span class="meta-value">{{ now()->format('d/m/Y') }}</span></div>
                </td>
            </tr>
        </table>

        <table class="subject-table">
            <thead>
            <tr>
                <th>Môn học</th>
                <th>Lớp</th>
                <th class="center">Buổi</th>
                <th class="num">Đơn giá</th>
                <th class="num">Thành tiền</th>
            </tr>
            </thead>
            <tbody>
            @foreach($rows as $row)
                <tr>
                    <td>{{ $row['subject_name'] }}</td>
                    <td>{{ $row['class_names'] }}</td>
                    <td class="center">{{ $row['sessions'] }}</td>
                    <td class="num">{{ number_format((int) $row['unit_price'], 0, ',', '.') }}</td>
                    <td class="num">{{ number_format((int) $row['study_fee'], 0, ',', '.') }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>

        <div class="totals-line">
            <span>TỔNG THÁNG NÀY:</span>
            {{ number_format((int) ($totals['total_study_fee'] ?? 0), 0, ',', '.') }} đ
        </div>

        <table class="footer-table">
            <tr>
                <td width="56%" style="vertical-align: top; padding-right: 10px;">
                    <div class="footer-card">
                        <div class="footer-title">Nợ lũy kế các tháng trước</div>
                        <table class="debt-table">
                            <thead>
                            <tr>
                                <th>Tháng</th>
                                <th class="num">Số tiền</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr>
                                <td>{{ $pdfData['display_month'] }}</td>
                                <td class="num">{{ number_format((int) ($totals['previous_debt'] ?? 0), 0, ',', '.') }} đ</td>
                            </tr>
                            <tr>
                                <td><strong>TỔNG NỢ LŨY KẾ</strong></td>
                                <td class="num"><strong>{{ number_format((int) ($totals['previous_debt'] ?? 0), 0, ',', '.') }} đ</strong></td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </td>
                <td style="vertical-align: top;">
                    <div class="highlight-card">
                        <div class="label">Tổng phải thu</div>
                        <div class="value">{{ number_format((int) ($totals['total_amount'] ?? 0), 0, ',', '.') }} đ</div>
                    </div>

                    <div class="payment-note">
                        <div class="line"><strong>Đã thanh toán:</strong> {{ number_format((int) ($totals['paid_amount'] ?? 0), 0, ',', '.') }} đ</div>
                        <div class="line"><strong>Còn lại:</strong> {{ number_format((int) ($totals['remaining_amount'] ?? 0), 0, ',', '.') }} đ</div>
                        <div class="line"><strong>Số môn:</strong> {{ number_format((int) ($pdfData['subject_count'] ?? 0), 0, ',', '.') }}</div>
                        <div class="line"><strong>Tổng buổi / Có mặt:</strong>
                            {{ number_format((int) ($totals['total_sessions'] ?? 0), 0, ',', '.') }}
                            /
                            {{ number_format((int) ($totals['attended_sessions'] ?? 0), 0, ',', '.') }}
                        </div>
                        <div class="line small muted" style="margin-top: 8px;">
                            {{ $student->full_name }}{!! $student->parent_phone ? ' - ' . e($student->parent_phone) : '' !!}
                        </div>
                    </div>
                </td>
            </tr>
        </table>
    </div>
</div>
</body>
</html>

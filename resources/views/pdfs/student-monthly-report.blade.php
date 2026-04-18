<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Báo cáo học tập tháng {{ $monthLabel }}</title>
    <style>
        @font-face {
            font-family: 'ReportSans';
            font-style: normal;
            font-weight: 400;
            src: url('{{ base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans.ttf') }}') format('truetype');
        }

        @font-face {
            font-family: 'ReportSans';
            font-style: normal;
            font-weight: 700;
            src: url('{{ base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf') }}') format('truetype');
        }

        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'ReportSans',Arial,sans-serif;font-size:12px;color:#1e293b;background:#dde3ef;padding:24px}
        .page-wrap{background:#fff;border-radius:18px;overflow:hidden;border:1px solid #e2e8f0;max-width:820px;margin:0 auto;box-shadow:0 16px 48px rgba(0,0,0,.15)}
        .header-band{background:#3b4fd8;padding:22px 26px 20px}
        .logo-box{width:56px;height:56px;border-radius:14px;background:rgba(255,255,255,.18);border:2px solid rgba(255,255,255,.38);text-align:center;line-height:56px}
        .logo-box img{width:38px;height:38px;object-fit:contain;vertical-align:middle}
        .header-table{width:100%;border-collapse:collapse}
        .header-table td{border:0;padding:0;vertical-align:middle}
        .sys-name{font-size:10px;color:rgba(255,255,255,.65);font-weight:700;letter-spacing:1px;margin-bottom:3px}
        .main-title{font-size:20px;font-weight:700;color:#fff;margin-bottom:3px}
        .sub{font-size:10px;color:rgba(255,255,255,.6)}
        .month-badge{background:rgba(255,255,255,.15);border:1.5px solid rgba(255,255,255,.35);border-radius:20px;padding:8px 16px;color:#fff;font-size:12px;font-weight:700;text-align:center;white-space:nowrap}
        .accent{height:5px;background:#f59e0b}
        .content{padding:20px 24px}
        .sec-block{margin-bottom:18px}
        .sec-head{border-left:4px solid #3b4fd8;padding-left:10px;margin-bottom:10px}
        .sec-head.green{border-color:#10b981}
        .sec-head.amber{border-color:#f59e0b}
        .sec-head.purple{border-color:#8b5cf6}
        .sec-title{font-size:13px;font-weight:700;color:#0f172a}
        .student-card{background:#f0fdf4;border:1.5px solid #bbf7d0;border-radius:14px;padding:14px}
        .student-card table td{border:0;padding:0}
        .avatar-circle{width:68px;height:68px;border-radius:34px;background:#bbf7d0;border:3px solid #4ade80;color:#166534;font-size:24px;font-weight:700;text-align:center;line-height:68px}
        .student-name{font-size:17px;font-weight:700;color:#14532d;margin-bottom:8px}
        .info-pill{display:inline-block;background:#fff;border:1px solid #86efac;border-radius:20px;padding:3px 10px;font-size:10px;color:#166534;white-space:nowrap;margin-right:5px;margin-bottom:5px}
        .class-badge{display:inline-block;background:#dbeafe;border:1px solid #93c5fd;border-radius:20px;padding:3px 9px;margin-right:4px;margin-top:4px;font-size:10px;font-weight:700;color:#1e40af}
        .stat-wrap{width:100%;border-collapse:separate;border-spacing:8px 0;margin-left:-8px}
        .stat-card{border-radius:12px;padding:12px 10px;text-align:center;vertical-align:top;border:1.5px solid transparent}
        .sc-blue{background:#eff6ff;border-color:#bfdbfe}.sc-green{background:#f0fdf4;border-color:#bbf7d0}.sc-red{background:#fff1f2;border-color:#fecdd3}.sc-amber{background:#fffbeb;border-color:#fde68a}.sc-purple{background:#faf5ff;border-color:#e9d5ff}
        .stat-val{font-size:22px;font-weight:700;line-height:1;margin-bottom:3px}
        .sc-blue .stat-val{color:#1d4ed8}.sc-green .stat-val{color:#15803d}.sc-red .stat-val{color:#be123c}.sc-amber .stat-val{color:#92400e}.sc-purple .stat-val{color:#6d28d9}
        .stat-lbl{font-size:9.5px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.4px}
        .prog-bg{background:#e2e8f0;border-radius:99px;height:8px;overflow:hidden;margin-top:7px}
        .prog-fill{height:8px;border-radius:99px;background:#22c55e}
        .tl-item{background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:12px;padding:11px 13px;margin-bottom:8px}
        .tl-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:7px}
        .date-badge{display:inline-block;background:#e0f2fe;border:1px solid #7dd3fc;border-radius:99px;padding:3px 10px;font-size:10px;font-weight:700;color:#0369a1}
        .type-badge{display:inline-block;background:#f1f5f9;border:1px solid #cbd5e1;border-radius:99px;padding:3px 8px;font-size:9px;font-weight:700;color:#475569;margin-left:5px}
        .status-pill{display:inline-block;border-radius:99px;padding:3px 10px;font-size:10px;font-weight:700}
        .sp-green{background:#dcfce7;border:1px solid #86efac;color:#166534}
        .sp-yellow{background:#fef9c3;border:1px solid #fde047;color:#713f12}
        .sp-red{background:#fee2e2;border:1px solid #fca5a5;color:#991b1b}
        .sp-gray{background:#f1f5f9;border:1px solid #cbd5e1;color:#475569}
        .tl-row{font-size:10.5px;color:#475569;margin-bottom:3px}
        .tl-row b{color:#1e293b}
        .score-chip{display:inline-block;background:#eff6ff;border:1px solid #93c5fd;border-radius:8px;padding:2px 7px;font-size:10px;font-weight:700;color:#1e3a8a;margin-right:4px}
        .score-chip.high{background:#dcfce7;border-color:#86efac;color:#14532d}
        .reward-badge{display:inline-block;background:#fef9c3;border:1px solid #fde047;border-radius:99px;padding:2px 8px;font-size:10px;font-weight:700;color:#713f12}
        .report-card{background:#fff;border:1.5px solid #c7d2fe;border-radius:14px;margin-bottom:10px;overflow:hidden}
        .rc-top{background:#eef2ff;border-bottom:1.5px solid #c7d2fe;padding:10px 14px;display:flex;justify-content:space-between;align-items:center}
        .rc-top.green-t{background:#f0fdf4;border-color:#bbf7d0}.rc-green{border-color:#bbf7d0}
        .rc-top.amber-t{background:#fffbeb;border-color:#fde68a}.rc-amber{border-color:#fde68a}
        .rc-top.pink-t{background:#fff0f7;border-color:#fbcfe8}.rc-pink{border-color:#fbcfe8}
        .rc-name{font-size:13px;font-weight:700;color:#1e1b4b;margin-bottom:2px}
        .rc-meta{font-size:10px;color:#64748b}
        .rsp{display:inline-block;border-radius:99px;padding:4px 12px;font-size:10px;font-weight:700;white-space:nowrap}
        .rsp-green{background:#dcfce7;border:1.5px solid #86efac;color:#166534}
        .rsp-yellow{background:#fef9c3;border:1.5px solid #fde047;color:#713f12}
        .rsp-red{background:#fee2e2;border:1.5px solid #fca5a5;color:#991b1b}
        .rsp-gray{background:#f1f5f9;border:1.5px solid #cbd5e1;color:#475569}
        .rc-body{padding:12px 14px}
        .comment-box{background:#eef2ff;border:1.5px dashed #a5b4fc;border-radius:10px;padding:10px 12px}
        .comment-lbl{font-size:9.5px;font-weight:700;color:#6366f1;text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px}
        .comment-txt{font-size:11px;color:#312e81;line-height:1.55}
        .comment-txt.muted{color:#94a3b8;font-style:italic}
        .rc-meta-row{font-size:10px;color:#64748b;margin-bottom:6px}
        .empty-box{background:#f8fafc;border:1.5px dashed #cbd5e1;border-radius:12px;padding:14px;color:#64748b;font-size:11px}
        .footer-band{background:#1e293b;padding:14px 24px;margin-top:22px;display:flex;justify-content:space-between;align-items:center}
        .footer-l{font-size:10px;color:rgba(255,255,255,.55);line-height:1.7}
        .footer-l b{color:rgba(255,255,255,.85);font-size:11px}
        .footer-r{text-align:right;font-size:10px;color:rgba(255,255,255,.45)}
        .footer-seal{display:inline-block;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.18);border-radius:20px;padding:3px 10px;color:rgba(255,255,255,.55);font-size:9px;margin-top:4px}
    </style>
</head>
@php
    $appName = config('app.name', 'ERP TriTue8');
    $logoPath = public_path('assets/images/logo.png');
    $logoSrc = file_exists($logoPath) ? $logoPath : null;
    $toAsciiLower = static fn ($value) => \Illuminate\Support\Str::of((string) $value)->ascii()->lower()->value();

    $initials = collect(explode(' ', (string) $student->full_name))
        ->filter()
        ->take(2)
        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');

    $totalSessions = (int) ($stats['total_sessions'] ?? 0);
    $totalPresent = (int) ($stats['total_present'] ?? 0);
    $totalAbsent = (int) ($stats['total_absent'] ?? 0);
    $participationRate = max(0, min(100, (float) ($stats['participation_rate'] ?? 0)));
    $averageScore = number_format((float) ($stats['average_score'] ?? 0), 2);
@endphp
<body>
<div class="page-wrap">
    <div class="header-band">
        <table class="header-table">
            <tr>
                <td width="70">
                    <div class="logo-box">
                        @if($logoSrc)
                            <img src="{{ $logoSrc }}" alt="logo">
                        @endif
                    </div>
                </td>
                <td style="padding-left:14px">
                    <div class="sys-name">{{ mb_strtoupper($appName) }} - HỆ THỐNG QUẢN LÝ HỌC TẬP</div>
                    <div class="main-title">Báo cáo học tập tháng {{ $monthLabel }}</div>
                    <div class="sub">Ngày xuất: {{ $exportedAt }}</div>
                </td>
                <td width="110" align="right">
                    <div class="month-badge">THÁNG<br>{{ $monthLabel }}</div>
                </td>
            </tr>
        </table>
    </div>
    <div class="accent"></div>

    <div class="content">
        <div class="sec-block">
            <div class="sec-head green"><span class="sec-title">Thông tin học sinh</span></div>
            <div class="student-card">
                <table width="100%" style="border-collapse:collapse">
                    <tr>
                        <td width="82" style="vertical-align:middle">
                            <div class="avatar-circle">{{ $initials ?: 'HS' }}</div>
                        </td>
                        <td style="vertical-align:middle;padding-left:14px">
                            <div class="student-name">{{ $student->full_name ?? '-' }}</div>
                            <span class="info-pill">Khối: <b>{{ $student->grade_level?->label() ?? '-' }}</b></span>
                            <span class="info-pill">Mã HS: <b>{{ $student->user_id ?? '-' }}</b></span>
                            <span class="info-pill">Ngày sinh: <b>{{ $student->dob?->format('d/m/Y') ?? '-' }}</b></span>
                            <div style="margin-top:8px">
                                @forelse($classNames as $className)
                                    <span class="class-badge">{{ $className }}</span>
                                @empty
                                    <span class="class-badge">Chưa có lớp trong tháng</span>
                                @endforelse
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="sec-block">
            <div class="sec-head amber"><span class="sec-title">Tổng quan tháng {{ $monthLabel }}</span></div>
            <table class="stat-wrap">
                <tr>
                    <td width="20%"><div class="stat-card sc-blue"><div class="stat-val">{{ $totalSessions }}</div><div class="stat-lbl">Tổng buổi</div></div></td>
                    <td width="20%"><div class="stat-card sc-green"><div class="stat-val">{{ $totalPresent }}</div><div class="stat-lbl">Có mặt</div></div></td>
                    <td width="20%"><div class="stat-card sc-red"><div class="stat-val">{{ $totalAbsent }}</div><div class="stat-lbl">Vắng mặt</div></div></td>
                    <td width="20%">
                        <div class="stat-card sc-amber">
                            <div class="stat-val">{{ number_format($participationRate, 2) }}%</div>
                            <div class="stat-lbl">Chuyên cần</div>
                            <div class="prog-bg"><div class="prog-fill" style="width:{{ $participationRate }}%"></div></div>
                        </div>
                    </td>
                    <td width="20%"><div class="stat-card sc-purple"><div class="stat-val">{{ $averageScore }}</div><div class="stat-lbl">Điểm TB</div></div></td>
                </tr>
            </table>
        </div>

        <div class="sec-block">
            <div class="sec-head"><span class="sec-title">Lịch sử học tập</span></div>
            @forelse($historyRows as $row)
                @php
                    $status = (string) ($row['attendance_status'] ?? '-');
                    $statusAscii = $toAsciiLower($status);
                    $statusClass = 'sp-gray';
                    if (str_contains($statusAscii, 'co mat')) {
                        $statusClass = 'sp-green';
                    } elseif (str_contains($statusAscii, 'di muon')) {
                        $statusClass = 'sp-yellow';
                    } elseif (str_contains($statusAscii, 'vang')) {
                        $statusClass = 'sp-red';
                    }
                    $scores = is_array($row['scores'] ?? null) ? $row['scores'] : [];
                @endphp
                <div class="tl-item">
                    <div class="tl-top">
                        <div>
                            <span class="date-badge">{{ $row['date_weekday'] ?? '-' }}</span>
                            <span class="type-badge">{{ $row['session_type'] ?? '-' }}</span>
                        </div>
                        <span class="status-pill {{ $statusClass }}">{{ $status }}</span>
                    </div>
                    <div class="tl-row"><b>Giờ học:</b> {{ $row['time_range'] ?? '-' }}</div>
                    <div class="tl-row"><b>Lớp:</b> {{ $row['class_info'] ?? '-' }}</div>
                    <div class="tl-row"><b>Ghi chú:</b> {{ $row['private_note'] ?? '-' }}</div>
                    <div class="tl-row"><b>Lý do vắng:</b> {{ $row['absent_reason'] ?? '-' }}</div>
                    <div class="tl-row">
                        <b>Điểm:</b>
                        @if(empty($scores))
                            <span style="color:#94a3b8">Không có đầu điểm</span>
                        @else
                            @foreach($scores as $score)
                                @php
                                    $scoreValue = (float) ($score['score'] ?? 0);
                                    $maxScore = (float) ($score['max_score'] ?? 10);
                                    $isHigh = $maxScore > 0 && ($scoreValue / $maxScore) >= 0.8;
                                    $formattedScoreValue = rtrim(rtrim(number_format($scoreValue, 2, '.', ''), '0'), '.');
                                    $formattedMaxScore = rtrim(rtrim(number_format($maxScore, 2, '.', ''), '0'), '.');
                                @endphp
                                <span class="score-chip {{ $isHigh ? 'high' : '' }}">
                                    {{ $score['exam_name'] ?? 'Bài kiểm tra' }}: {{ $formattedScoreValue }}/{{ $formattedMaxScore }}
                                </span>
                            @endforeach
                        @endif
                    </div>
                    <div class="tl-row"><b>Điểm thưởng:</b> <span class="reward-badge">{{ $row['reward_points'] ?? '0' }}</span></div>
                </div>
            @empty
                <div class="empty-box">Không có dữ liệu học tập trong tháng này.</div>
            @endforelse
        </div>

        <div class="sec-block">
            <div class="sec-head purple"><span class="sec-title">Nhận xét theo lớp</span></div>
            @forelse($classReports as $report)
                @php
                    $statusColor = (string) ($report['status_color'] ?? 'gray');
                    $statusClass = 'rsp-gray';
                    $cardClass = '';
                    $topClass = '';
                    $hasReport = (bool) ($report['has_report'] ?? false);

                    if ($statusColor === 'success') {
                        $statusClass = 'rsp-green';
                        $cardClass = 'rc-green';
                        $topClass = 'green-t';
                    } elseif ($statusColor === 'warning') {
                        $statusClass = 'rsp-yellow';
                        $cardClass = 'rc-amber';
                        $topClass = 'amber-t';
                    } elseif ($statusColor === 'danger') {
                        $statusClass = 'rsp-red';
                        $cardClass = 'rc-pink';
                        $topClass = 'pink-t';
                    }

                    $content = (string) ($report['content'] ?? '-');
                    $statusLabel = trim((string) ($report['status_label'] ?? ''));
                    if ($statusLabel === '' || ! $hasReport) {
                        $statusLabel = 'Chưa báo cáo';
                    }
                @endphp
                <div class="report-card {{ $cardClass }}">
                    <div class="rc-top {{ $topClass }}">
                        <div>
                            <div class="rc-name">{{ $report['class_name'] ?? '-' }}</div>
                            <div class="rc-meta"><b>GV:</b> {{ $report['teacher_name'] ?? '-' }}</div>
                        </div>
                        <span class="rsp {{ $statusClass }}">{{ $statusLabel }}</span>
                    </div>
                    <div class="rc-body">
                        <div class="rc-meta-row">
                            <b>Nộp lúc:</b> {{ $report['submitted_at'] ?? '-' }}
                            &nbsp;·&nbsp;
                            <b>Xem xét:</b> {{ $report['reviewed_at'] ?? '-' }}
                        </div>
                        <div class="rc-meta-row"><b>Lý do từ chối:</b> {{ $report['reject_reason'] ?? '-' }}</div>
                        <div class="comment-box">
                            <div class="comment-lbl">Nhận xét của giáo viên</div>
                            <div class="comment-txt {{ ($content === '-' || trim($content) === '') ? 'muted' : '' }}">
                                {!! trim($content) !== '' ? nl2br(e($content)) : '-' !!}
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="empty-box">Không có báo cáo theo lớp trong tháng này.</div>
            @endforelse
        </div>
    </div>

    <div class="footer-band">
        <div class="footer-l">
            <b>{{ $appName }}</b><br>
            Báo cáo tháng {{ $monthLabel }} - {{ $student->full_name ?? '-' }}<br>
            Tài liệu được tạo tự động bởi hệ thống
        </div>
        <div class="footer-r">
            Xuất ngày: {{ $exportedAt }}<br>
            <span class="footer-seal">Tài liệu nội bộ</span>
        </div>
    </div>
</div>
</body>
</html>

<?php

namespace App\Services;

use App\Constants\NotificationChannel;
use App\Constants\NotificationSendStatus;
use App\Constants\NotificationType;
use App\Constants\AttendanceStatus;
use App\Constants\FeeType;
use App\Constants\GradeLevel;
use App\Constants\InvoiceStatus;
use App\Constants\PaymentMethod;
use App\Core\Logs\Logging;
use App\Core\Services\BaseService;
use App\Core\Services\ServiceException;
use App\Core\Services\ServiceReturn;
use App\Models\Student;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Notification;
use App\Models\TuitionInvoice;
use App\Repositories\AttendanceRecordRepository;
use App\Repositories\AttendanceSessionRepository;
use App\Repositories\ClassEnrollmentRepository;
use App\Repositories\ScheduleInstanceRepository;
use App\Repositories\TuitionInvoiceLogRepository;
use App\Repositories\TuitionInvoiceRepository;
use App\Repositories\UserLogRepository;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use ZipArchive;

class TuitionInvoiceService extends BaseService
{
    public function __construct(
        protected AttendanceSessionRepository $attendanceSessionRepository,
        protected ScheduleInstanceRepository $scheduleInstanceRepository,
        protected AttendanceRecordRepository $attendanceRecordRepository,
        protected ClassEnrollmentRepository $classEnrollmentRepository,
        protected TuitionInvoiceRepository $tuitionInvoiceRepository,
        protected TuitionInvoiceLogRepository $tuitionInvoiceLogRepository,
        protected UserLogRepository $userLogRepository,
    ) {}

    public function generateMonthlyInvoices(string $month): ServiceReturn
    {
        return $this->execute(function () use ($month) {
            $monthDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
            $monthEnd = $monthDate->copy()->endOfMonth();

            $classIds = $this->attendanceSessionRepository->getLockedClassIdsInMonth($monthDate, $monthEnd);

            if ($classIds->isEmpty()) {
                throw new ServiceException("Tháng {$month} chưa có buổi điểm danh nào ở trạng thái khóa.");
            }

            $createdCount = 0;
            $skippedCount = 0;

            foreach ($classIds as $classId) {
                $enrollments = $this->classEnrollmentRepository->getEnrollmentsForBillingMonth(
                    (int) $classId,
                    $monthDate,
                    $monthEnd
                );

                foreach ($enrollments as $enrollment) {
                    $studentId = (int) $enrollment->student_id;

                    if ($this->tuitionInvoiceRepository->existsForStudentClassMonth($studentId, (int) $classId, $month)) {
                        $skippedCount++;

                        continue;
                    }

                    $attendanceRows = $this->attendanceRecordRepository->getFeeCountedAttendancesByStudentClassInMonth(
                        $studentId,
                        (int) $classId,
                        $monthDate,
                        $monthEnd
                    );

                    $totalStudyFee = 0;

                    foreach ($attendanceRows as $attendanceRow) {
                        $sessionDate = Carbon::parse($attendanceRow->session_date);
                        $totalStudyFee += $this->resolveAttendanceRowFee(
                            enrollment: $enrollment,
                            classId: (int) $classId,
                            studentId: $studentId,
                            attendanceRow: $attendanceRow,
                            sessionDate: $sessionDate,
                        );
                    }

                    $previousInvoice = $this->tuitionInvoiceRepository->findPreviousMonthInvoice(
                        $studentId,
                        (int) $classId,
                        $monthDate->copy()->subMonth()->format('Y-m')
                    );

                    $previousDebt = max((int) ($previousInvoice?->getRemainingAmount() ?? 0), 0);
                    $totalAmount = $totalStudyFee + $previousDebt;

                    $this->tuitionInvoiceRepository->create([
                        'invoice_number' => $this->tuitionInvoiceRepository->getNextInvoiceNumber($month),
                        'student_id' => $studentId,
                        'class_id' => (int) $classId,
                        'month' => $month,
                        'total_sessions' => $attendanceRows->pluck('schedule_instance_id')->unique()->count(),
                        'attended_sessions' => $attendanceRows
                            ->filter(function ($attendanceRow) {
                                $status = $attendanceRow->status;

                                if ($status instanceof AttendanceStatus) {
                                    return in_array($status, [
                                        AttendanceStatus::Present,
                                        AttendanceStatus::Late,
                                    ], true);
                                }

                                return in_array((int) $status, [
                                    AttendanceStatus::Present->value,
                                    AttendanceStatus::Late->value,
                                ], true);
                            })
                            ->pluck('schedule_instance_id')
                            ->unique()
                            ->count(),
                        'total_study_fee' => $totalStudyFee,
                        'previous_debt' => $previousDebt,
                        'total_amount' => $totalAmount,
                        'paid_amount' => 0,
                        'status' => InvoiceStatus::Unpaid,
                        'is_locked' => false,
                        'note' => null,
                    ]);

                    $createdCount++;
                }
            }

            if ($createdCount === 0) {
                if ($skippedCount > 0) {
                    throw new ServiceException("Tháng {$month} không có hóa đơn mới để tạo. Dữ liệu hiện có đã được giữ nguyên.");
                }

                throw new ServiceException("Không có dữ liệu phù hợp để tạo hóa đơn tháng {$month}.");
            }

            $adminName = auth()->user()?->name
                ?? auth()->user()?->username
                ?? auth()->user()?->email
                ?? 'Hệ thống';

            $this->logAction(
                'generate_tuition_invoices',
                "Admin {$adminName} tạo hóa đơn học phí tháng {$monthDate->format('m/Y')} lúc " . now()->format('d/m/Y H:i:s')
            );

            return ServiceReturn::success([
                'created_count' => $createdCount,
                'skipped_count' => $skippedCount,
                'month' => $month,
            ], $skippedCount > 0
                ? "Đã tạo {$createdCount} hóa đơn mới, bỏ qua {$skippedCount} hóa đơn đã tồn tại của tháng {$monthDate->format('m/Y')}"
                : "Đã tạo {$createdCount} hóa đơn học phí tháng {$monthDate->format('m/Y')}");
        }, useTransaction: true);
    }

    public function recordPayment(TuitionInvoice $invoice, array $data): ServiceReturn
    {
        return $this->execute(function () use ($invoice, $data) {
            $amount = (int) ($data['amount'] ?? 0);

            if ($amount <= 0) {
                throw new ServiceException('Số tiền thanh toán phải lớn hơn 0.');
            }

            $invoice = $this->applyPayment($invoice, $amount, $data);
            $adminName = auth()->user()?->name
                ?? auth()->user()?->username
                ?? auth()->user()?->email
                ?? 'Hệ thống';

            $this->logAction(
                'record_tuition_payment',
                "Admin {$adminName} ghi nhận thanh toán HĐ {$invoice->invoice_number} số tiền "
                . number_format($amount, 0, ',', '.') . 'đ lúc ' . now()->format('d/m/Y H:i:s')
            );

            return ServiceReturn::success($invoice, 'Ghi nhận thanh toán thành công');
        }, useTransaction: true);
    }

    public function recordBulkPayment(EloquentCollection $invoices, array $data): ServiceReturn
    {
        return $this->execute(function () use ($invoices, $data) {
            $payableInvoices = $this->resolveBulkInvoices($invoices)->filter(
                fn (TuitionInvoice $invoice) => $invoice->getRemainingAmount() > 0 && ! $invoice->is_locked
            );

            if ($payableInvoices->isEmpty()) {
                throw new ServiceException('Không có hóa đơn nào hợp lệ để thanh toán.');
            }

            $amount = (int) ($data['amount'] ?? 0);

            if ($amount <= 0) {
                throw new ServiceException('Số tiền thanh toán phải lớn hơn 0.');
            }

            $totalRemaining = (int) $payableInvoices->sum(fn (TuitionInvoice $invoice) => $invoice->getRemainingAmount());

            if ($amount > $totalRemaining) {
                throw new ServiceException('Số tiền thanh toán không được lớn hơn tổng số tiền còn lại.');
            }

            $processedCount = 0;
            $totalPaid = 0;
            $remainingToAllocate = $amount;

            foreach ($payableInvoices as $invoice) {
                if ($remainingToAllocate <= 0) {
                    break;
                }

                $paymentAmount = min($invoice->getRemainingAmount(), $remainingToAllocate);

                $this->applyPayment($invoice, $paymentAmount, $data);
                $processedCount++;
                $totalPaid += $paymentAmount;
                $remainingToAllocate -= $paymentAmount;
            }

            $this->logAction(
                'record_bulk_tuition_payment',
                "Thanh toán hàng loạt {$processedCount} hóa đơn, tổng tiền " . number_format($totalPaid, 0, ',', '.') . 'đ'
            );

            return ServiceReturn::success([
                'processed_count' => $processedCount,
                'total_paid' => $totalPaid,
            ], "Đã thanh toán {$processedCount} hóa đơn");
        }, useTransaction: true);
    }

    public function getStudentMonthlyInvoiceDetail(TuitionInvoice $invoice): ServiceReturn
    {
        return $this->execute(function () use ($invoice) {
            $invoice->loadMissing('student.user');

            $monthlyInvoices = $this->tuitionInvoiceRepository->getStudentInvoicesForMonth(
                (int) $invoice->student_id,
                $invoice->month
            );

            if ($monthlyInvoices->isEmpty()) {
                throw new ServiceException('Không tìm thấy dữ liệu học phí của học sinh trong tháng này.');
            }

            $subjectItems = $monthlyInvoices
                ->groupBy(fn ($item) => $item->subject_name ?: 'Chưa gán môn học')
                ->map(function ($items, $subjectName) {
                    $firstItem = $items->first();

                    return [
                        'subject_name' => $subjectName,
                        'class_names' => $items->pluck('class_name')->filter()->unique()->implode(', '),
                        'grade_levels' => $items
                            ->pluck('class_grade_level')
                            ->filter(fn ($level) => $level !== null)
                            ->map(fn ($level) => GradeLevel::tryFrom((int) $level)?->label())
                            ->filter()
                            ->unique()
                            ->implode(', '),
                        'teacher_names' => $items->pluck('teacher_name')->filter()->unique()->implode(', '),
                        'total_sessions' => (int) $items->sum('total_sessions'),
                        'attended_sessions' => (int) $items->sum('attended_sessions'),
                        'total_study_fee' => (int) $items->sum('total_study_fee'),
                        'previous_debt' => (int) $items->sum('previous_debt'),
                        'total_amount' => (int) $items->sum('total_amount'),
                        'paid_amount' => (int) $items->sum('paid_amount'),
                        'remaining_amount' => (int) $items->sum(fn (TuitionInvoice $item) => $item->getRemainingAmount()),
                        'payment_method' => $firstItem?->payment_method,
                    ];
                })
                ->values();

            $totals = [
                'total_sessions' => (int) $subjectItems->sum('total_sessions'),
                'attended_sessions' => (int) $subjectItems->sum('attended_sessions'),
                'total_study_fee' => (int) $subjectItems->sum('total_study_fee'),
                'previous_debt' => (int) $subjectItems->sum('previous_debt'),
                'total_amount' => (int) $subjectItems->sum('total_amount'),
                'paid_amount' => (int) $subjectItems->sum('paid_amount'),
                'remaining_amount' => (int) $subjectItems->sum('remaining_amount'),
            ];

            return ServiceReturn::success([
                'invoice' => $invoice->refresh(),
                'student' => $invoice->student,
                'month' => $invoice->month,
                'items' => $subjectItems,
                'totals' => $totals,
            ]);
        });
    }

    public function updateInvoice(TuitionInvoice $invoice, array $data): ServiceReturn
    {
        return $this->execute(function () use ($invoice, $data) {
            if (! $invoice->canEdit()) {
                throw new ServiceException('Hóa đơn đã khóa, không thể chỉnh sửa trực tiếp.');
            }

            $previousDebt = max((int) ($data['previous_debt'] ?? 0), 0);
            $totalAmount = (int) $invoice->total_study_fee + $previousDebt;

            if ($totalAmount < (int) $invoice->paid_amount) {
                throw new ServiceException('Nợ cũ không hợp lệ vì nhỏ hơn số tiền đã thanh toán.');
            }

            $oldValues = [
                'previous_debt' => (int) $invoice->previous_debt,
                'note' => (string) ($invoice->note ?? ''),
            ];

            $invoice->update([
                'previous_debt' => $previousDebt,
                'note' => $data['note'] ?? null,
                'total_amount' => $totalAmount,
            ]);

            $invoice = $this->tuitionInvoiceRepository->syncStatus($invoice->refresh());
            $this->logAction('update_tuition_invoice', $this->buildInvoiceUpdateLog($invoice, $oldValues));

            return ServiceReturn::success($invoice, 'Cập nhật hóa đơn thành công');
        }, useTransaction: true);
    }

    public function cancelPaymentLog(TuitionInvoice $invoice, int $logId, string $cancelReason): ServiceReturn
    {
        return $this->execute(function () use ($invoice, $logId, $cancelReason) {
            $paymentLog = $this->tuitionInvoiceLogRepository->find($logId);

            if (! $paymentLog || (int) $paymentLog->invoice_id !== (int) $invoice->id) {
                throw new ServiceException('Lần thanh toán không tồn tại hoặc không thuộc hóa đơn này.');
            }

            if ($paymentLog->is_cancelled) {
                throw new ServiceException('Lần thanh toán này đã được hủy trước đó.');
            }

            $paymentLog->update([
                'is_cancelled' => true,
                'cancelled_at' => now(),
                'cancel_reason' => $cancelReason,
            ]);

            $latestActiveLog = $invoice->logs()
                ->where('is_cancelled', false)
                ->whereKeyNot($paymentLog->id)
                ->orderByDesc('paid_at')
                ->orderByDesc('id')
                ->first();

            $invoice->update([
                'paid_amount' => max((int) $invoice->paid_amount - (int) $paymentLog->amount, 0),
                'payment_method' => $latestActiveLog?->payment_method?->value,
            ]);

            $invoice = $this->tuitionInvoiceRepository->syncStatus($invoice->refresh());

            $adminName = auth()->user()?->name
                ?? auth()->user()?->username
                ?? auth()->user()?->email
                ?? 'Hệ thống';

            $this->logAction(
                'cancel_tuition_payment',
                "Admin {$adminName} hủy thanh toán HĐ {$invoice->invoice_number} lúc "
                . now()->format('d/m/Y H:i:s') . ", lý do: {$cancelReason}"
            );

            return ServiceReturn::success($invoice, 'Hủy thanh toán thành công');
        }, useTransaction: true);
    }

    public function sendReminder(TuitionInvoice $invoice): ServiceReturn
    {
        return $this->execute(function () use ($invoice) {
            $this->createInvoiceNotification(
                $invoice,
                'Nhắc đóng học phí',
                "Hóa đơn {$invoice->invoice_number} còn " . number_format($invoice->getRemainingAmount(), 0, ',', '.') . 'đ cần thanh toán.'
            );

            $this->logAction('remind_tuition_invoice', "Nhắc đóng học phí {$invoice->invoice_number}");

            return ServiceReturn::success($invoice, 'Đã tạo nhắc đóng học phí');
        }, useTransaction: true);
    }

    public function sendBulkReminder(EloquentCollection $records): ServiceReturn
    {
        return $this->execute(function () use ($records) {
            $invoices = $this->resolveBulkInvoices($records)
                ->filter(fn (TuitionInvoice $invoice) => $invoice->getRemainingAmount() > 0 && ! $invoice->is_locked)
                ->values();

            if ($invoices->isEmpty()) {
                throw new ServiceException('Không có hóa đơn hợp lệ để nhắc đóng học phí.');
            }

            foreach ($invoices as $invoice) {
                $this->createInvoiceNotification(
                    $invoice,
                    'Nhắc đóng học phí',
                    "Hóa đơn {$invoice->invoice_number} còn " . number_format($invoice->getRemainingAmount(), 0, ',', '.') . 'đ cần thanh toán.'
                );
            }

            $this->logAction('remind_bulk_tuition_invoice', "Nhắc đóng học phí {$invoices->count()} hóa đơn");

            return ServiceReturn::success([
                'processed_count' => $invoices->count(),
            ], "Đã tạo nhắc đóng học phí cho {$invoices->count()} hóa đơn");
        }, useTransaction: true);
    }

    public function sendReceipt(TuitionInvoice $invoice): ServiceReturn
    {
        return $this->execute(function () use ($invoice) {
            $this->createInvoiceNotification(
                $invoice,
                'Gửi phiếu thu học phí',
                "Phiếu thu cho hóa đơn {$invoice->invoice_number} đã được tạo. Đã thanh toán: " . number_format((int) $invoice->paid_amount, 0, ',', '.') . 'đ.'
            );

            $this->logAction('send_tuition_receipt', "Gửi phiếu thu cho hóa đơn {$invoice->invoice_number}");

            return ServiceReturn::success($invoice, 'Đã gửi phiếu thu');
        }, useTransaction: true);
    }

    public function exportInvoice(TuitionInvoice $invoice): ServiceReturn
    {
        return $this->execute(function () use ($invoice) {
            $this->logAction('export_tuition_invoice', "Xuất hóa đơn {$invoice->invoice_number}");

            return ServiceReturn::success($invoice->refresh(), 'Đã xử lý yêu cầu xuất PDF');
        }, useTransaction: true);
    }

    public function downloadInvoicePdf(TuitionInvoice $invoice): Response
    {
        $pdfData = $this->buildMonthlyPdfData($invoice);

        $this->logAction('export_tuition_invoice', "Xuất PDF hóa đơn {$pdfData['document_number']}");

        return response()->streamDownload(
            fn () => print($this->renderInvoicePdfContent($pdfData)),
            Str::slug($pdfData['file_name']) . '.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }

    public function prepareBulkInvoiceZip(EloquentCollection $invoices, int $paymentMethod): ServiceReturn
    {
        return $this->execute(function () use ($invoices, $paymentMethod) {
            $selectedPaymentMethod = PaymentMethod::tryFrom($paymentMethod);

            if (! $selectedPaymentMethod) {
                throw new ServiceException('Phương thức xuất hóa đơn không hợp lệ.');
            }

            $exportableRecords = $invoices
                ->filter(fn ($record) => filled($record->student_id) && filled($record->month))
                ->unique(fn ($record) => $record->student_id . '|' . $record->month)
                ->values();

            if ($exportableRecords->isEmpty()) {
                throw new ServiceException('Không có hóa đơn nào hợp lệ để xuất.');
            }

            if (! class_exists(ZipArchive::class)) {
                throw new ServiceException('Máy chủ chưa hỗ trợ ZipArchive để xuất file ZIP.');
            }

            $directory = storage_path('app/temp/tuition-invoice-exports');

            if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
                throw new RuntimeException("Không thể tạo thư mục tạm tại {$directory}.");
            }

            $methodSlug = str($selectedPaymentMethod->label())->slug('-');
            $fileName = 'hoa-don-hoc-phi-' . $methodSlug . '-' . now()->format('Ymd-His') . '-' . Str::random(6) . '.zip';
            $filePath = $directory . DIRECTORY_SEPARATOR . $fileName;
            $zip = new ZipArchive();

            if ($zip->open($filePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Không thể khởi tạo file ZIP để xuất hóa đơn.');
            }

            try {
                foreach ($exportableRecords as $record) {
                    $pdfData = $this->buildMonthlyPdfData($record, $selectedPaymentMethod);

                    $zip->addFromString(
                        Str::slug($pdfData['file_name']) . '.pdf',
                        $this->renderInvoicePdfContent($pdfData)
                    );
                }
            } catch (\Throwable $e) {
                $zip->close();

                if (file_exists($filePath)) {
                    @unlink($filePath);
                }

                throw $e;
            }

            $zip->close();

            $this->logAction(
                'export_bulk_tuition_invoice',
                'Xuất ZIP ' . $exportableRecords->count() . ' phiếu thu học phí theo phương thức '
                . $selectedPaymentMethod->label() . ' lúc ' . now()->format('d/m/Y H:i:s')
            );

            return ServiceReturn::success([
                'file_name' => $fileName,
                'file_path' => $filePath,
                'count' => $exportableRecords->count(),
            ], "Đã chuẩn bị {$exportableRecords->count()} phiếu thu {$selectedPaymentMethod->label()} để tải về");
        });
    }

    public function downloadPreparedZip(array $payload): BinaryFileResponse
    {
        return response()->download(
            $payload['file_path'],
            $payload['file_name'],
            ['Content-Type' => 'application/zip']
        )->deleteFileAfterSend(true);
    }

    protected function createInvoiceNotification(TuitionInvoice $invoice, string $title, string $content): void
    {
        Notification::create([
            'user_id' => $invoice->student->user_id,
            'title' => $title,
            'content' => $content,
            'type' => NotificationType::Tuition,
            'is_read' => false,
            'channel' => NotificationChannel::Email,
            'send_status' => NotificationSendStatus::Pending,
            'sent_at' => null,
            'is_urgent' => false,
            'reference_type' => TuitionInvoice::class,
            'reference_id' => $invoice->id,
        ]);
    }

    protected function applyPayment(TuitionInvoice $invoice, int $amount, array $data): TuitionInvoice
    {
        if ($invoice->is_locked) {
            throw new ServiceException('Hóa đơn đã khóa, không thể ghi nhận thanh toán.');
        }

        if ($amount > $invoice->getRemainingAmount()) {
            throw new ServiceException('Số tiền thanh toán không được lớn hơn số tiền còn lại.');
        }

        $this->tuitionInvoiceLogRepository->create([
            'invoice_id' => $invoice->id,
            'amount' => $amount,
            'paid_at' => now(),
            'note' => $data['note'] ?? null,
            'is_cancelled' => false,
            'payment_method' => $data['payment_method'],
            'changed_by' => auth()->id(),
        ]);

        $invoice->update([
            'paid_amount' => (int) $invoice->paid_amount + $amount,
            'payment_method' => $data['payment_method'],
        ]);

        return $this->tuitionInvoiceRepository->syncStatus($invoice->refresh());
    }

    protected function buildInvoiceUpdateLog(TuitionInvoice $invoice, array $oldValues): string
    {
        $adminName = auth()->user()?->name
            ?? auth()->user()?->username
            ?? auth()->user()?->email
            ?? 'Hệ thống';

        $changes = collect([
            ['label' => 'previous_debt', 'old' => (int) $oldValues['previous_debt'], 'new' => (int) $invoice->previous_debt],
            ['label' => 'note', 'old' => (string) $oldValues['note'], 'new' => (string) ($invoice->note ?? '')],
        ])
            ->filter(fn (array $change) => $change['old'] !== $change['new'])
            ->map(fn (array $change) => "{$change['label']} [{$change['old']} -> {$change['new']}]")
            ->implode(', ');

        if ($changes === '') {
            $changes = 'không có thay đổi dữ liệu';
        }

        return "Admin {$adminName} sửa HĐ {$invoice->invoice_number} {$changes} lúc " . now()->format('d/m/Y H:i:s');
    }

    protected function logAction(string $action, string $description): void
    {
        if (! auth()->id()) {
            return;
        }

        $this->userLogRepository->log(auth()->id(), $action, $description);
        Logging::userActivity($action, $description, auth()->id());
    }

    protected function renderInvoicePdfContent(array $pdfData): string
    {
        return Pdf::loadView('pdfs.tuition-invoice', [
            'pdfData' => $pdfData,
        ])
            ->setPaper('a4')
            ->output();
    }

    protected function buildMonthlyPdfData(TuitionInvoice $invoice, ?PaymentMethod $exportPaymentMethod = null): array
    {
        $detailResult = $this->getStudentMonthlyInvoiceDetail($invoice);

        if ($detailResult->isError()) {
            throw new ServiceException($detailResult->getMessage());
        }

        $detailData = $detailResult->getData();
        $student = $detailData['student'];
        $month = $detailData['month'];
        $items = collect($detailData['items'] ?? []);
        $totals = $detailData['totals'] ?? [];
        $monthlyInvoices = $this->tuitionInvoiceRepository
            ->getStudentInvoicesForMonth((int) $invoice->student_id, $month)
            ->loadMissing(['student.user', 'class.teacher', 'logs.changedBy']);

        $logs = $monthlyInvoices
            ->flatMap(fn (TuitionInvoice $monthlyInvoice) => $monthlyInvoice->logs->where('is_cancelled', false))
            ->sortBy([
                ['paid_at', 'asc'],
                ['id', 'asc'],
            ])
            ->values();

        $displayMonth = Carbon::createFromFormat('Y-m', $month)->format('m/Y');
        $documentNumber = 'PT-' . str_replace('-', '', $month) . '-' . $student->id;
        $gradeDisplay = $items->pluck('grade_levels')->filter()->unique()->implode(', ');

        $subjectRows = $items->map(function (array $item) {
            $sessions = max((int) ($item['total_sessions'] ?? 0), 0);
            $studyFee = (int) ($item['total_study_fee'] ?? 0);

            return [
                'subject_name' => $item['subject_name'] ?? '-',
                'class_names' => $item['class_names'] ?: '-',
                'sessions' => $sessions,
                'unit_price' => $sessions > 0 ? (int) round($studyFee / $sessions) : $studyFee,
                'study_fee' => $studyFee,
                'previous_debt' => (int) ($item['previous_debt'] ?? 0),
                'total_amount' => (int) ($item['total_amount'] ?? 0),
                'teacher_names' => $item['teacher_names'] ?: '-',
                'grade_levels' => $item['grade_levels'] ?: '-',
            ];
        })->values();

        return [
            'document_number' => $documentNumber,
            'file_name' => 'phieu-thu-' . $student->full_name . '-' . $month,
            'logo_path' => public_path('assets/images/logo.png'),
            'student' => $student,
            'month' => $month,
            'display_month' => $displayMonth,
            'grade_display' => $gradeDisplay ?: '-',
            'subject_count' => $subjectRows->count(),
            'subject_rows' => $subjectRows,
            'totals' => $totals,
            'logs' => $logs,
            'export_payment_method' => $exportPaymentMethod,
            'issuer_name' => auth()->user()?->name ?? auth()->user()?->username ?? auth()->user()?->email ?? 'Hệ thống',
        ];
    }

    protected function resolveBulkInvoices(EloquentCollection $records): EloquentCollection
    {
        $pairs = $records
            ->map(function ($record) {
                $studentId = (int) ($record->student_id ?? 0);
                $month = (string) ($record->month ?? '');

                if ($studentId <= 0 || $month === '') {
                    return null;
                }

                return [
                    'student_id' => $studentId,
                    'month' => $month,
                ];
            })
            ->filter()
            ->unique(fn (array $pair) => $pair['student_id'] . '|' . $pair['month'])
            ->values()
            ->all();

        return $this->tuitionInvoiceRepository->getInvoicesForStudentMonthPairs($pairs);
    }

    protected function resolveAttendanceRowFee(
        $enrollment,
        int $classId,
        int $studentId,
        object $attendanceRow,
        Carbon $sessionDate
    ): int {
        if ($attendanceRow->participant_fee_amount !== null) {
            return (int) $attendanceRow->participant_fee_amount;
        }

        $feeType = isset($attendanceRow->fee_type) ? FeeType::tryFrom((int) $attendanceRow->fee_type) : null;

        if ($feeType === FeeType::Free) {
            return 0;
        }

        if ($attendanceRow->custom_fee_per_session !== null) {
            return (int) $attendanceRow->custom_fee_per_session;
        }

        $effectiveEnrollment = $this->classEnrollmentRepository->findEffectiveEnrollmentForDate(
            $classId,
            $studentId,
            $sessionDate
        );

        return $effectiveEnrollment?->getEffectiveFeeForDate($sessionDate)
            ?? (int) $enrollment->class->base_fee_per_session;
    }
}

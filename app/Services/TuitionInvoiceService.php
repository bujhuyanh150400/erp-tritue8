<?php

namespace App\Services;

use App\Constants\NotificationChannel;
use App\Constants\NotificationSendStatus;
use App\Constants\NotificationType;
use App\Constants\InvoiceStatus;
use App\Core\Logs\Logging;
use App\Core\Services\BaseService;
use App\Core\Services\ServiceException;
use App\Core\Services\ServiceReturn;
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
use Symfony\Component\HttpFoundation\Response;

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

            if ($this->tuitionInvoiceRepository->existsForMonth($month)) {
                throw new ServiceException("Tháng {$month} đã có hóa đơn học phí, không thể tạo lại.");
            }

            $classIds = $this->attendanceSessionRepository->getLockedClassIdsInMonth($monthDate, $monthEnd);

            if ($classIds->isEmpty()) {
                throw new ServiceException("Tháng {$month} chưa có buổi điểm danh nào ở trạng thái khóa.");
            }

            $createdCount = 0;

            foreach ($classIds as $classId) {
                $totalSessions = $this->scheduleInstanceRepository->countBillableSessionsByClassInMonth(
                    (int) $classId,
                    $monthDate,
                    $monthEnd
                );

                $enrollments = $this->classEnrollmentRepository->getEnrollmentsForBillingMonth(
                    (int) $classId,
                    $monthDate,
                    $monthEnd
                );

                foreach ($enrollments as $enrollment) {
                    $studentId = (int) $enrollment->student_id;
                    $attendanceRows = $this->attendanceRecordRepository->getFeeCountedAttendancesByStudentClassInMonth(
                        $studentId,
                        (int) $classId,
                        $monthDate,
                        $monthEnd
                    );

                    $totalStudyFee = 0;

                    foreach ($attendanceRows as $attendanceRow) {
                        $sessionDate = Carbon::parse($attendanceRow->session_date);
                        $effectiveEnrollment = $this->classEnrollmentRepository->findEffectiveEnrollmentForDate(
                            (int) $classId,
                            $studentId,
                            $sessionDate
                        );

                        $totalStudyFee += $effectiveEnrollment?->getEffectiveFeeForDate($sessionDate)
                            ?? (int) $enrollment->class->base_fee_per_session;
                    }

                    $previousInvoice = $this->tuitionInvoiceRepository->findPreviousMonthInvoice(
                        $studentId,
                        (int) $classId,
                        $monthDate->copy()->subMonth()->format('Y-m')
                    );

                    $previousDebt = max((int) ($previousInvoice?->getRemainingAmount() ?? 0), 0);
                    $totalAmount = $totalStudyFee + $previousDebt;

                    $invoice = $this->tuitionInvoiceRepository->create([
                        'invoice_number' => $this->tuitionInvoiceRepository->getNextInvoiceNumber($month),
                        'student_id' => $studentId,
                        'class_id' => (int) $classId,
                        'month' => $month,
                        'total_sessions' => $totalSessions,
                        'attended_sessions' => $attendanceRows->count(),
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
                'month' => $month,
            ], "Đã tạo {$createdCount} hóa đơn học phí tháng {$monthDate->format('m/Y')}");
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
            $payableInvoices = $invoices->filter(
                fn (TuitionInvoice $invoice) => $invoice->getRemainingAmount() > 0 && ! $invoice->is_locked
            );

            if ($payableInvoices->isEmpty()) {
                throw new ServiceException('Không có hóa đơn nào hợp lệ để thanh toán.');
            }

            $processedCount = 0;
            $totalPaid = 0;

            foreach ($payableInvoices as $invoice) {
                $amount = $invoice->getRemainingAmount();
                $this->applyPayment($invoice, $amount, $data);
                $processedCount++;
                $totalPaid += $amount;
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

            $invoice->update([
                'paid_amount' => max((int) $invoice->paid_amount - (int) $paymentLog->amount, 0),
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
        $invoice->loadMissing([
            'student.user',
            'class.teacher',
            'logs.changedBy',
        ]);

        $this->logAction('export_tuition_invoice', "Xuất PDF hóa đơn {$invoice->invoice_number}");

        return Pdf::loadView('pdfs.tuition-invoice', [
            'invoice' => $invoice,
        ])
            ->setPaper('a4')
            ->download($invoice->invoice_number . '.pdf');
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
}

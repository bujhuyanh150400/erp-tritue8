<?php

namespace App\Services;

use App\Constants\NotificationChannel;
use App\Constants\NotificationSendStatus;
use App\Constants\NotificationType;
use App\Core\Logs\Logging;
use App\Core\Services\BaseService;
use App\Core\Services\ServiceException;
use App\Core\Services\ServiceReturn;
use App\Models\Notification;
use App\Models\TuitionInvoice;
use App\Repositories\TuitionInvoiceLogRepository;
use App\Repositories\TuitionInvoiceRepository;
use App\Repositories\UserLogRepository;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class TuitionInvoiceService extends BaseService
{
    public function __construct(
        protected TuitionInvoiceRepository $tuitionInvoiceRepository,
        protected TuitionInvoiceLogRepository $tuitionInvoiceLogRepository,
        protected UserLogRepository $userLogRepository,
    ) {}

    public function recordPayment(TuitionInvoice $invoice, array $data): ServiceReturn
    {
        return $this->execute(function () use ($invoice, $data) {
            $amount = (int) ($data['amount'] ?? 0);

            if ($amount <= 0) {
                throw new ServiceException('Số tiền thanh toán phải lớn hơn 0.');
            }

            $invoice = $this->applyPayment($invoice, $amount, $data);
            $this->logAction('record_tuition_payment', "Ghi nhận thanh toán học phí {$invoice->invoice_number}");

            return ServiceReturn::success($invoice, 'Ghi nhận thanh toán thành công');
        }, useTransaction: true);
    }

    public function recordBulkPayment(EloquentCollection $invoices, array $data): ServiceReturn
    {
        return $this->execute(function () use ($invoices, $data) {
            $payableInvoices = $invoices->filter(fn (TuitionInvoice $invoice) => $invoice->getRemainingAmount() > 0);

            if ($payableInvoices->isEmpty()) {
                throw new ServiceException('Không có hóa đơn nào còn nợ để thanh toán.');
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

            $discountAmount = max((int) ($data['discount_amount'] ?? 0), 0);
            $totalAmount = (int) $invoice->total_study_fee - $discountAmount + (int) $invoice->previous_debt;

            if ($totalAmount < (int) $invoice->paid_amount) {
                throw new ServiceException('Giảm trừ không hợp lệ vì nhỏ hơn số tiền đã thanh toán.');
            }

            $invoice->update([
                'discount_amount' => $discountAmount,
                'note' => $data['note'] ?? null,
                'total_amount' => $totalAmount,
            ]);

            $invoice = $this->tuitionInvoiceRepository->syncStatus($invoice->refresh());
            $this->logAction('update_tuition_invoice', "Cập nhật hóa đơn học phí {$invoice->invoice_number}");

            return ServiceReturn::success($invoice, 'Cập nhật hóa đơn thành công');
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
        if ($amount > $invoice->getRemainingAmount()) {
            throw new ServiceException('Số tiền thanh toán không được lớn hơn số tiền còn lại.');
        }

        $this->tuitionInvoiceLogRepository->create([
            'invoice_id' => $invoice->id,
            'amount' => $amount,
            'paid_at' => $data['paid_at'],
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

    protected function logAction(string $action, string $description): void
    {
        if (! auth()->id()) {
            return;
        }

        $this->userLogRepository->log(auth()->id(), $action, $description);
        Logging::userActivity($action, $description, auth()->id());
    }
}

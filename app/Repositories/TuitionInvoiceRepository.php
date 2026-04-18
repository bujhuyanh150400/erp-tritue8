<?php

namespace App\Repositories;

use App\Constants\InvoiceStatus;
use App\Constants\PaymentMethod;
use App\Core\Repository\BaseRepository;
use App\Models\TuitionInvoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class TuitionInvoiceRepository extends BaseRepository
{
    public function getModel(): string
    {
        return TuitionInvoice::class;
    }

    protected function getBaseQuery(): Builder
    {
        return $this->query()
            ->from('tuition_invoices')
            ->join('students', 'tuition_invoices.student_id', '=', 'students.id')
            ->join('classes', 'tuition_invoices.class_id', '=', 'classes.id');
    }

    public function getListingQuery(): Builder
    {
        return $this->getBaseQuery()
            ->leftJoin('teachers', 'classes.teacher_id', '=', 'teachers.id')
            ->select([
                'tuition_invoices.*',
                'students.full_name as student_name',
                'classes.name as class_name',
                'classes.grade_level as class_grade_level',
                'teachers.full_name as teacher_name',
                DB::raw('(tuition_invoices.total_amount - tuition_invoices.paid_amount) as remaining_amount'),
                DB::raw("(
                    SELECT til.payment_method
                    FROM tuition_invoice_logs as til
                    WHERE til.invoice_id = tuition_invoices.id
                        AND til.is_cancelled = false
                    ORDER BY til.paid_at DESC, til.id DESC
                    LIMIT 1
                ) as latest_payment_method"),
            ]);
    }

    public function applyFilters(Builder $query, array $filters = []): Builder
    {
        $month = $filters['month'] ?? now()->format('Y-m');
        $query->where('tuition_invoices.month', $month);

        if (filled($filters['status'])) {
            $query->where('tuition_invoices.status', $filters['status']);
        }

        if (filled($filters['class_id'])) {
            $query->where('tuition_invoices.class_id', $filters['class_id']);
        }

        if (filled($filters['teacher_id'])) {
            $query->where('classes.teacher_id', $filters['teacher_id']);
        }

        if (filled($filters['grade_level'])) {
            $query->where('classes.grade_level', $filters['grade_level']);
        }

        if (filled($filters['payment_method'])) {
            $query->whereExists(function ($sub) use ($filters) {
                $sub->selectRaw(1)
                    ->from('tuition_invoice_logs as til')
                    ->whereColumn('til.invoice_id', 'tuition_invoices.id')
                    ->where('til.is_cancelled', false)
                    ->where('til.payment_method', $filters['payment_method']);
            });
        }

        return $query;
    }

    public function getSummaryStats(array $filters = []): object
    {
        $invoiceSummary = $this->applyFilters($this->getBaseQuery(), $filters)
            ->selectRaw('
                COUNT(DISTINCT CASE WHEN (tuition_invoices.total_amount - tuition_invoices.paid_amount) <= 0 THEN tuition_invoices.student_id END) as paid_student_count,
                COUNT(DISTINCT CASE WHEN (tuition_invoices.total_amount - tuition_invoices.paid_amount) > 0 THEN tuition_invoices.student_id END) as unpaid_student_count,
                COALESCE(SUM(tuition_invoices.paid_amount), 0) as total_paid_amount,
                COALESCE(SUM(tuition_invoices.total_amount - tuition_invoices.paid_amount), 0) as total_unpaid_amount
            ')
            ->first();

        $filteredInvoiceIds = $this->applyFilters($this->getBaseQuery(), $filters)
            ->select('tuition_invoices.id');

        $paymentSummary = DB::table('tuition_invoice_logs')
            ->where('is_cancelled', false)
            ->whereIn('invoice_id', $filteredInvoiceIds)
            ->selectRaw(
                '
                COALESCE(SUM(CASE WHEN payment_method = ? THEN amount ELSE 0 END), 0) as total_cash_amount,
                COALESCE(SUM(CASE WHEN payment_method = ? THEN amount ELSE 0 END), 0) as total_bank_transfer_amount
                ',
                [
                    PaymentMethod::Cash->value,
                    PaymentMethod::BankTransfer->value,
                ]
            )
            ->first();

        return (object) [
            'paid_student_count' => (int) ($invoiceSummary->paid_student_count ?? 0),
            'unpaid_student_count' => (int) ($invoiceSummary->unpaid_student_count ?? 0),
            'total_paid_amount' => (int) ($invoiceSummary->total_paid_amount ?? 0),
            'total_unpaid_amount' => (int) ($invoiceSummary->total_unpaid_amount ?? 0),
            'total_cash_amount' => (int) ($paymentSummary->total_cash_amount ?? 0),
            'total_bank_transfer_amount' => (int) ($paymentSummary->total_bank_transfer_amount ?? 0),
        ];
    }

    public function syncStatus(TuitionInvoice $invoice): TuitionInvoice
    {
        $remaining = max((int) $invoice->total_amount - (int) $invoice->paid_amount, 0);

        $status = match (true) {
            $invoice->paid_amount <= 0 => InvoiceStatus::Unpaid,
            $remaining <= 0 => InvoiceStatus::Paid,
            default => InvoiceStatus::PartiallyPaid,
        };

        $invoice->update([
            'status' => $status,
        ]);

        return $invoice->refresh();
    }

    public function existsForMonth(string $month): bool
    {
        return $this->query()
            ->where('month', $month)
            ->exists();
    }

    public function findPreviousMonthInvoice(int $studentId, int $classId, string $month): ?TuitionInvoice
    {
        return $this->query()
            ->where('student_id', $studentId)
            ->where('class_id', $classId)
            ->where('month', $month)
            ->first();
    }

    public function getNextInvoiceNumber(string $month): string
    {
        $prefix = 'HP-' . str_replace('-', '', $month) . '-';

        $lastNumber = $this->query()
            ->where('invoice_number', 'like', $prefix . '%')
            ->lockForUpdate()
            ->pluck('invoice_number')
            ->map(fn (string $invoiceNumber) => (int) substr($invoiceNumber, strlen($prefix)))
            ->max() ?? 0;

        return $prefix . str_pad((string) ($lastNumber + 1), 4, '0', STR_PAD_LEFT);
    }
}

<?php

namespace App\Repositories;

use App\Constants\InvoiceStatus;
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
        $query = $this->applyFilters($this->getBaseQuery(), $filters);

        return $query->selectRaw('
                COALESCE(SUM(tuition_invoices.total_study_fee), 0) as total_study_fee,
                COALESCE(SUM(tuition_invoices.previous_debt), 0) as total_previous_debt,
                COALESCE(SUM(tuition_invoices.total_amount - tuition_invoices.paid_amount), 0) as total_remaining
            ')
            ->first();
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

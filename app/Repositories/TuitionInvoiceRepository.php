<?php

namespace App\Repositories;

use App\Constants\GradeLevel;
use App\Constants\InvoiceStatus;
use App\Constants\PaymentMethod;
use App\Core\Repository\BaseRepository;
use App\Models\TuitionInvoice;
use Illuminate\Database\Eloquent\Collection;
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
            ->groupBy('tuition_invoices.student_id', 'students.full_name', 'tuition_invoices.month')
            ->select([
                DB::raw('MIN(tuition_invoices.id) as id'),
                'tuition_invoices.student_id',
                'students.full_name as student_name',
                'tuition_invoices.month',
                DB::raw('MAX(tuition_invoices.class_id) as class_id'),
                DB::raw('MAX(classes.name) as class_name'),
                DB::raw('MAX(classes.grade_level) as class_grade_level'),
                DB::raw('MAX(teachers.full_name) as teacher_name'),
                DB::raw('COUNT(tuition_invoices.id) as invoice_count'),
                DB::raw('SUM(tuition_invoices.total_sessions) as total_sessions'),
                DB::raw('SUM(tuition_invoices.attended_sessions) as attended_sessions'),
                DB::raw('SUM(tuition_invoices.total_study_fee) as total_study_fee'),
                DB::raw('SUM(tuition_invoices.previous_debt) as previous_debt'),
                DB::raw('SUM(tuition_invoices.total_amount) as total_amount'),
                DB::raw('SUM(tuition_invoices.paid_amount) as paid_amount'),
                DB::raw('MAX(CASE WHEN tuition_invoices.is_locked THEN 1 ELSE 0 END) as is_locked'),
                DB::raw('
                    CASE
                        WHEN COUNT(DISTINCT CASE WHEN tuition_invoices.payment_method IS NOT NULL THEN tuition_invoices.payment_method END) = 1
                            THEN MAX(tuition_invoices.payment_method)
                        ELSE NULL
                    END as payment_method
                '),
                DB::raw('COUNT(DISTINCT CASE WHEN tuition_invoices.payment_method IS NOT NULL THEN tuition_invoices.payment_method END) as payment_method_count'),
                DB::raw('
                    CASE
                        WHEN COALESCE(SUM(tuition_invoices.paid_amount), 0) <= 0 THEN 0
                        WHEN COALESCE(SUM(tuition_invoices.total_amount - tuition_invoices.paid_amount), 0) <= 0 THEN 2
                        ELSE 1
                    END as status
                '),
                DB::raw('SUM(tuition_invoices.total_amount - tuition_invoices.paid_amount) as remaining_amount'),
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
            $query->where('tuition_invoices.payment_method', $filters['payment_method']);
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

    public function existsForStudentClassMonth(int $studentId, int $classId, string $month): bool
    {
        return $this->query()
            ->where('student_id', $studentId)
            ->where('class_id', $classId)
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

    public function getStudentInvoicesForMonth(int $studentId, string $month): Collection
    {
        return $this->query()
            ->from('tuition_invoices')
            ->join('classes', 'tuition_invoices.class_id', '=', 'classes.id')
            ->leftJoin('subjects', 'classes.subject_id', '=', 'subjects.id')
            ->leftJoin('teachers', 'classes.teacher_id', '=', 'teachers.id')
            ->where('tuition_invoices.student_id', $studentId)
            ->where('tuition_invoices.month', $month)
            ->orderBy('subjects.name')
            ->orderBy('classes.name')
            ->select([
                'tuition_invoices.*',
                'classes.name as class_name',
                'classes.grade_level as class_grade_level',
                'subjects.name as subject_name',
                'teachers.full_name as teacher_name',
            ])
            ->get();
    }

    public function getInvoicesForStudentMonthPairs(array $pairs): Collection
    {
        if ($pairs === []) {
            return new Collection();
        }

        return $this->query()
            ->where(function (Builder $query) use ($pairs) {
                foreach ($pairs as $pair) {
                    $query->orWhere(function (Builder $subQuery) use ($pair) {
                        $subQuery->where('student_id', $pair['student_id'])
                            ->where('month', $pair['month']);
                    });
                }
            })
            ->get();
    }
}

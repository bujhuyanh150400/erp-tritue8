<?php

use App\Models\TuitionInvoice;
use App\Models\Student;
use App\Services\StudentReportService;
use App\Services\TuitionInvoiceService;
use Illuminate\Support\Facades\Route;

// Tạo route mặc định để redirect đến dashboard
Route::get('/', function () {
    return redirect()->to(\Filament\Pages\Dashboard::getUrl());
});

Route::middleware('auth')->get('/tuition-invoices/{invoice}/pdf', function (TuitionInvoice $invoice, TuitionInvoiceService $service) {
    return $service->downloadInvoicePdf($invoice);
})->name('tuition-invoices.pdf');

Route::middleware('auth')->get('/student-reports/{student}/monthly-export', function (
    Student $student,
    StudentReportService $service
) {
    $month = (string) request()->query('month', now()->format('Y-m'));
    $result = $service->exportMonthlyReportPdf($student, $month);

    if ($result->isError()) {
        abort(422, $result->getMessage());
    }

    $data = $result->getData();

    return $data['pdf']->download($data['file_name']);
})->name('student-reports.monthly-export');

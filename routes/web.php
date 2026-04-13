<?php

use App\Models\TuitionInvoice;
use App\Services\TuitionInvoiceService;
use Illuminate\Support\Facades\Route;

// Tạo route mặc định để redirect đến dashboard
Route::get('/', function () {
    return redirect()->to(\Filament\Pages\Dashboard::getUrl());
});

Route::middleware('auth')->get('/tuition-invoices/{invoice}/pdf', function (TuitionInvoice $invoice, TuitionInvoiceService $service) {
    return $service->downloadInvoicePdf($invoice);
})->name('tuition-invoices.pdf');

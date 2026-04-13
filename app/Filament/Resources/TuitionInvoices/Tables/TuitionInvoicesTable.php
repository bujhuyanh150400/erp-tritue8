<?php

namespace App\Filament\Resources\TuitionInvoices\Tables;

use App\Constants\GradeLevel;
use App\Constants\InvoiceStatus;
use App\Constants\PaymentMethod;
use App\Filament\Components\CustomSelect;
use App\Models\TuitionInvoice;
use App\Repositories\TuitionInvoiceLogRepository;
use App\Repositories\TuitionInvoiceRepository;
use App\Services\ClassService;
use App\Services\TeacherService;
use App\Services\TuitionInvoiceService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Grid;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Support\RawJs;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class TuitionInvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query, TuitionInvoiceRepository $repository) {
                return $repository->getListingQuery();
            })
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('Số HĐ')
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('student_name')
                    ->label('Học sinh')
                    ->searchable(),

                TextColumn::make('class_name')
                    ->label('Lớp')
                    ->searchable()
                    ->description(fn (TuitionInvoice $record) => $record->teacher_name ?: null),

                TextColumn::make('month')
                    ->label('Tháng')
                    ->badge(),

                TextColumn::make('total_sessions')
                    ->label('Tổng buổi'),

                TextColumn::make('attended_sessions')
                    ->label('Buổi có mặt'),

                TextColumn::make('total_study_fee')
                    ->label('Học phí')
                    ->money('VND'),

                TextColumn::make('previous_debt')
                    ->label('Nợ cũ')
                    ->money('VND'),

                TextColumn::make('total_amount')
                    ->label('Tổng phải thu')
                    ->money('VND'),

                TextColumn::make('paid_amount')
                    ->label('Đã thanh toán')
                    ->money('VND'),

                TextColumn::make('remaining_amount')
                    ->label('Còn lại')
                    ->money('VND')
                    ->color(fn ($state) => ((int) $state > 0) ? 'danger' : 'success'),

                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn (InvoiceStatus $state) => $state->label())
                    ->color(fn (InvoiceStatus $state) => match ($state) {
                        InvoiceStatus::Unpaid => 'danger',
                        InvoiceStatus::PartiallyPaid => 'warning',
                        InvoiceStatus::Paid => 'success',
                        InvoiceStatus::Cancelled => 'gray',
                    }),
            ])
            ->filters([
                Filter::make('filters')
                    ->columns(6)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('month')
                            ->label('Tháng')
                            ->type('month')
                            ->required()
                            ->default(now()->format('Y-m')),

                        Select::make('status')
                            ->label('Trạng thái')
                            ->options(InvoiceStatus::options())
                            ->placeholder('Tất cả'),

                        CustomSelect::make('class_id')
                            ->label('Lớp')
                            ->placeholder('Tất cả lớp')
                            ->getOptionSelectService(ClassService::class),

                        CustomSelect::make('teacher_id')
                            ->label('Giáo viên')
                            ->placeholder('Tất cả giáo viên')
                            ->getOptionSelectService(TeacherService::class),

                        Select::make('grade_level')
                            ->label('Khối')
                            ->options(GradeLevel::options())
                            ->placeholder('Tất cả khối'),

                        Select::make('payment_method')
                            ->label('Phương thức thanh toán')
                            ->options(PaymentMethod::options())
                            ->placeholder('Tất cả'),
                    ])
                    ->query(function (Builder $query, array $data, TuitionInvoiceRepository $repository): Builder {
                        return $repository->applyFilters($query, $data);
                    }),
            ], layout: FiltersLayout::AboveContent)
            ->defaultSort('month', 'desc')
            ->recordActions([
                ActionGroup::make([
                    Action::make('view_detail')
                        ->label('Xem chi tiết')
                        ->icon(Heroicon::DocumentText)
                        ->modalHeading(fn (TuitionInvoice $record) => 'Chi tiết hóa đơn ' . $record->invoice_number)
                        ->modalContent(fn (TuitionInvoice $record) => view('filament.resources.tuition-invoices.invoice-detail', [
                            'record' => $record->load(['student.user', 'class.teacher', 'logs.changedBy']),
                        ])),

                    Action::make('record_payment')
                        ->label('Thanh toán')
                        ->icon(Heroicon::Banknotes)
                        ->color('success')
                        ->hidden(fn (TuitionInvoice $record) => $record->getRemainingAmount() <= 0 || $record->is_locked)
                        ->schema([
                            Grid::make(2)
                                ->schema([
                                    Placeholder::make('student_info')
                                        ->label('Học sinh')
                                        ->content(fn (TuitionInvoice $record) => $record->student_name ?? $record->student?->full_name ?? '-'),
                                    Placeholder::make('class_info')
                                        ->label('Lớp')
                                        ->content(fn (TuitionInvoice $record) => $record->class_name ?? $record->class?->name ?? '-'),
                                    Placeholder::make('month_info')
                                        ->label('Tháng')
                                        ->content(fn (TuitionInvoice $record) => $record->month),
                                    Placeholder::make('total_amount')
                                        ->label('Tổng phải thu')
                                        ->content(fn (TuitionInvoice $record) => number_format((int) $record->total_amount, 0, ',', '.') . 'đ'),
                                    Placeholder::make('paid_amount')
                                        ->label('Đã thanh toán')
                                        ->content(fn (TuitionInvoice $record) => number_format((int) $record->paid_amount, 0, ',', '.') . 'đ'),
                                    Placeholder::make('remaining_amount')
                                        ->label('Còn lại')
                                        ->content(fn (TuitionInvoice $record) => number_format($record->getRemainingAmount(), 0, ',', '.') . 'đ'),
                                ])
                                ->columnSpanFull(),
                            TextInput::make('amount')
                                ->label('Số tiền thanh toán')
                                ->required()
                                ->minValue(1)
                                ->suffix('đ')
                                ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                                ->stripCharacters(['.'])
                                ->dehydrateStateUsing(fn ($state) => (int) str_replace('.', '', (string) $state))
                                ->columnSpanFull(),
                            Select::make('payment_method')
                                ->label('Phương thức')
                                ->options(PaymentMethod::options())
                                ->required()
                                ->columnSpanFull(),
                            Textarea::make('note')
                                ->label('Ghi chú')
                                ->columnSpanFull(),
                        ])
                        ->action(function (TuitionInvoice $record, array $data) {
                            $result = app(TuitionInvoiceService::class)->recordPayment($record, $data);

                            if ($result->isError()) {
                                Notification::make()->danger()->title('Lỗi')->body($result->getMessage())->send();
                                throw new Halt();
                            }

                            Notification::make()->success()->title($result->getMessage())->send();
                        }),

                    Action::make('cancel_payment')
                        ->label('Hủy thanh toán')
                        ->icon(Heroicon::ArrowUturnLeft)
                        ->color('danger')
                        ->hidden(fn (TuitionInvoice $record) => $record->logs()->where('is_cancelled', false)->count() === 0)
                        ->schema([
                            Select::make('log_id')
                                ->label('Lần thanh toán')
                                ->options(function (TuitionInvoice $record) {
                                    return app(TuitionInvoiceLogRepository::class)
                                        ->getActiveLogsForInvoice($record->id)
                                        ->mapWithKeys(fn ($log) => [
                                            $log->id => $log->paid_at?->format('d/m/Y H:i') . ' - ' . number_format((int) $log->amount, 0, ',', '.') . 'đ',
                                        ]);
                                })
                                ->required()
                                ->live(),
                            Grid::make(2)
                                ->schema([
                                    Placeholder::make('selected_amount')
                                        ->label('Số tiền')
                                        ->content(function (Get $get, TuitionInvoice $record) {
                                            $selectedLog = $record->logs()->find((int) ($get('log_id') ?? 0));

                                            return $selectedLog
                                                ? number_format((int) $selectedLog->amount, 0, ',', '.') . 'đ'
                                                : '-';
                                        }),
                                    Placeholder::make('selected_paid_at')
                                        ->label('Ngày thanh toán')
                                        ->content(function (Get $get, TuitionInvoice $record) {
                                            $selectedLog = $record->logs()->find((int) ($get('log_id') ?? 0));

                                            return $selectedLog?->paid_at?->format('d/m/Y H:i') ?? '-';
                                        }),
                                ])
                                ->columnSpanFull(),
                            Textarea::make('cancel_reason')
                                ->label('Lý do hủy')
                                ->required()
                                ->columnSpanFull(),
                        ])
                        ->action(function (TuitionInvoice $record, array $data) {
                            $result = app(TuitionInvoiceService::class)->cancelPaymentLog(
                                $record,
                                (int) $data['log_id'],
                                $data['cancel_reason']
                            );

                            if ($result->isError()) {
                                Notification::make()->danger()->title('Lỗi')->body($result->getMessage())->send();
                                throw new Halt();
                            }

                            Notification::make()->success()->title($result->getMessage())->send();
                        }),

                    Action::make('edit_invoice')
                        ->label('Chỉnh sửa')
                        ->icon(Heroicon::PencilSquare)
                        ->color('warning')
                        ->hidden(fn (TuitionInvoice $record) => $record->is_locked)
                        ->fillForm(fn (TuitionInvoice $record) => [
                            'previous_debt' => (int) $record->previous_debt,
                            'note' => $record->note,
                        ])
                        ->schema([
                            TextInput::make('previous_debt')
                                ->label('Nợ cũ')
                                ->default(0)
                                ->minValue(0)
                                ->suffix('đ')
                                ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                                ->stripCharacters(['.'])
                                ->dehydrateStateUsing(fn ($state) => (int) str_replace('.', '', (string) $state)),
                            Textarea::make('note')
                                ->label('Ghi chú'),
                        ])
                        ->action(function (TuitionInvoice $record, array $data) {
                            $result = app(TuitionInvoiceService::class)->updateInvoice($record, $data);

                            if ($result->isError()) {
                                Notification::make()->danger()->title('Lỗi')->body($result->getMessage())->send();
                                throw new Halt();
                            }

                            Notification::make()->success()->title($result->getMessage())->send();
                        }),

                    Action::make('remind_payment')
                        ->label('Nhắc đóng học phí')
                        ->icon(Heroicon::BellAlert)
                        ->hidden(fn (TuitionInvoice $record) => $record->getRemainingAmount() <= 0 || $record->is_locked)
                        ->action(function (TuitionInvoice $record) {
                            $result = app(TuitionInvoiceService::class)->sendReminder($record);

                            Notification::make()
                                ->title($result->isSuccess() ? $result->getMessage() : 'Lỗi')
                                ->body($result->isError() ? $result->getMessage() : null)
                                ->color($result->isSuccess() ? 'success' : 'danger')
                                ->send();
                        }),

//                    Action::make('send_receipt')
//                        ->label('Gửi phiếu thu')
//                        ->icon(Heroicon::PaperAirplane)
//                        ->action(function (TuitionInvoice $record) {
//                            $result = app(TuitionInvoiceService::class)->sendReceipt($record);
//
//                            Notification::make()
//                                ->title($result->isSuccess() ? $result->getMessage() : 'Lỗi')
//                                ->body($result->isError() ? $result->getMessage() : null)
//                                ->color($result->isSuccess() ? 'success' : 'danger')
//                                ->send();
//                        }),

                    Action::make('export_pdf')
                        ->label('Xuất PDF')
                        ->icon(Heroicon::DocumentArrowDown)
                        ->url(fn (TuitionInvoice $record) => route('tuition-invoices.pdf', ['invoice' => $record]))
                        ->openUrlInNewTab(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('record_bulk_payment')
                        ->label('Thanh toán hàng loạt')
                        ->icon(Heroicon::Banknotes)
                        ->color('success')
                        ->modalHeading('Thanh toán nhiều hóa đơn')
                        ->modalDescription('Hệ thống sẽ thanh toán toàn bộ số tiền còn nợ của các hóa đơn đã chọn.')
                        ->schema([
                            Select::make('payment_method')
                                ->label('Phương thức')
                                ->options(PaymentMethod::options())
                                ->required(),
                            Textarea::make('note')
                                ->label('Ghi chú'),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $result = app(TuitionInvoiceService::class)->recordBulkPayment($records, $data);

                            if ($result->isError()) {
                                Notification::make()->danger()->title('Lỗi')->body($result->getMessage())->send();
                                throw new Halt();
                            }

                            Notification::make()
                                ->success()
                                ->title($result->getMessage())
                                ->body('Hệ thống đã thanh toán hết số tiền còn nợ của các hóa đơn đã chọn.')
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }
}

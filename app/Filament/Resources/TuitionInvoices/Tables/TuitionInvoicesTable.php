<?php

namespace App\Filament\Resources\TuitionInvoices\Tables;

use App\Constants\GradeLevel;
use App\Constants\InvoiceStatus;
use App\Constants\PaymentMethod;
use App\Filament\Components\CustomSelect;
use App\Models\TuitionInvoice;
use App\Repositories\TuitionInvoiceRepository;
use App\Services\ClassService;
use App\Services\TeacherService;
use App\Services\TuitionInvoiceService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
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

                TextColumn::make('discount_amount')
                    ->label('Giảm trừ')
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
                        ->hidden(fn (TuitionInvoice $record) => $record->getRemainingAmount() <= 0)
                        ->schema([
                            Placeholder::make('remaining_amount')
                                ->label('Số tiền còn nợ')
                                ->content(fn (TuitionInvoice $record) => number_format($record->getRemainingAmount(), 0, ',', '.') . 'đ'),
                            TextInput::make('amount')
                                ->label('Số tiền thanh toán')
                                ->required()
                                ->minValue(1)
                                ->suffix('đ')
                                ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                                ->stripCharacters(['.'])
                                ->dehydrateStateUsing(fn ($state) => (int) str_replace('.', '', (string) $state)),
                            Select::make('payment_method')
                                ->label('Phương thức')
                                ->options(PaymentMethod::options())
                                ->required(),
                            DateTimePicker::make('paid_at')
                                ->label('Thời gian thanh toán')
                                ->default(now())
                                ->required(),
                            Textarea::make('note')
                                ->label('Ghi chú'),
                        ])
                        ->action(function (TuitionInvoice $record, array $data) {
                            $result = app(TuitionInvoiceService::class)->recordPayment($record, $data);

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
                        ->fillForm(fn (TuitionInvoice $record) => [
                            'discount_amount' => (int) $record->discount_amount,
                            'note' => $record->note,
                        ])
                        ->schema([
                            TextInput::make('discount_amount')
                                ->label('Giảm trừ')
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
                        ->hidden(fn (TuitionInvoice $record) => $record->getRemainingAmount() <= 0)
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
                        ->action(function (TuitionInvoice $record) {
                            $result = app(TuitionInvoiceService::class)->exportInvoice($record);

                            Notification::make()
                                ->title($result->isSuccess() ? $result->getMessage() : 'Lỗi')
                                ->body($result->isError() ? $result->getMessage() : 'Chức năng xuất file PDF chưa được tích hợp thư viện tạo file.')
                                ->color($result->isSuccess() ? 'success' : 'danger')
                                ->send();
                        }),
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
                            DateTimePicker::make('paid_at')
                                ->label('Thời gian thanh toán')
                                ->default(now())
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

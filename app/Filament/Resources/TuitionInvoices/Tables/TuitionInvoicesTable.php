<?php

namespace App\Filament\Resources\TuitionInvoices\Tables;

use App\Constants\GradeLevel;
use App\Constants\InvoiceStatus;
use App\Constants\PaymentMethod;
use App\Filament\Components\CustomSelect;
use App\Filament\Resources\TuitionInvoices\TuitionInvoiceResource;
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
use Illuminate\Support\HtmlString;

class TuitionInvoicesTable
{
    protected static array $monthlyDetailCache = [];

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query, TuitionInvoiceRepository $repository) {
                return $repository->getListingQuery();
            })
            ->checkIfRecordIsSelectableUsing(fn (TuitionInvoice $record): bool => $record->getRemainingAmount() > 0)
            ->columns([
                TextColumn::make('student_name')
                    ->label('Danh sách học sinh cần thanh toán tháng này')
                    ->searchable()
                    ->weight('bold')
                    ->description(function (TuitionInvoice $record) {
                        $gradeLevel = filled($record->class_grade_level)
                            ? GradeLevel::tryFrom((int) $record->class_grade_level)?->label()
                            : null;

                        return $gradeLevel ? 'Khối ' . $gradeLevel : null;
                    }),

                TextColumn::make('session_summary')
                    ->label('Tổng số buổi / Buổi có mặt')
                    ->state(fn (TuitionInvoice $record) => (int) $record->total_sessions . ' / ' . (int) $record->attended_sessions)
                    ->badge()
                    ->color('gray'),

                TextColumn::make('total_study_fee')
                    ->label('Tổng học phí')
                    ->money('VND'),

                TextColumn::make('previous_debt')
                    ->label('Nợ cũ chưa đóng')
                    ->money('VND'),

                TextColumn::make('total_amount')
                    ->label('Tổng phải thu')
                    ->money('VND'),

                TextColumn::make('status')
                    ->label('Trạng thái thanh toán')
                    ->badge()
                    ->formatStateUsing(fn (InvoiceStatus $state) => $state->label())
                    ->color(fn (InvoiceStatus $state) => match ($state) {
                        InvoiceStatus::Unpaid => 'danger',
                        InvoiceStatus::PartiallyPaid => 'warning',
                        InvoiceStatus::Paid => 'success',
                        InvoiceStatus::Cancelled => 'gray',
                    }),

                TextColumn::make('payment_method')
                    ->label('Hình thức thanh toán')
                    ->formatStateUsing(function ($state, TuitionInvoice $record) {
                        if ((int) ($record->payment_method_count ?? 0) > 1) {
                            return 'Nhiều phương thức';
                        }

                        if (blank($state)) {
                            return (int) $record->paid_amount > 0 ? 'Đã thanh toán, chưa chọn phương thức' : 'Chưa thanh toán';
                        }

                        $paymentMethod = $state instanceof PaymentMethod
                            ? $state
                            : PaymentMethod::tryFrom((int) $state);

                        return $paymentMethod?->label() ?? '-';
                    })
                    ->badge()
                    ->color(function ($state) {
                        $paymentMethod = $state instanceof PaymentMethod
                            ? $state
                            : (filled($state) ? PaymentMethod::tryFrom((int) $state) : null);

                        return match ($paymentMethod) {
                            PaymentMethod::Cash => 'warning',
                            PaymentMethod::BankTransfer => 'info',
                            default => 'gray',
                        };
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
            ->defaultKeySort(false)
            ->recordActions([
                ActionGroup::make([
                    Action::make('view_detail')
                        ->label('Xem chi tiết')
                        ->icon(Heroicon::DocumentText)
                        ->url(fn (TuitionInvoice $record) => TuitionInvoiceResource::getUrl('view', ['record' => $record])),

                    Action::make('record_payment')
                        ->label('Thanh toán')
                        ->icon(Heroicon::Banknotes)
                        ->color('success')
                        ->modalWidth('7xl')
                        ->hidden(fn (TuitionInvoice $record) => $record->getRemainingAmount() <= 0 || $record->is_locked)
                        ->schema([
                            Placeholder::make('monthly_invoice_overview')
                                ->hiddenLabel()
                                ->content(function (TuitionInvoice $record) {
                                    return new HtmlString(
                                        view('filament.resources.tuition-invoices.partials.payment-overview', [
                                            'detailData' => self::getMonthlyInvoiceDetailData($record),
                                        ])->render()
                                    );
                                })
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
                            $service = app(TuitionInvoiceService::class);
                            $summary = self::getMonthlyInvoiceSummary($record);

                            $result = (int) ($summary['subject_count'] ?? 0) > 1
                                ? $service->recordBulkPayment(new Collection([$record]), $data)
                                : $service->recordPayment($record, $data);

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
                        ->hidden(fn (TuitionInvoice $record) => (int) ($record->invoice_count ?? 1) > 1 || $record->logs()->where('is_cancelled', false)->count() === 0)
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
                        ->hidden(fn (TuitionInvoice $record) => (int) ($record->invoice_count ?? 1) > 1 || $record->is_locked)
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
                        ->hidden(fn (TuitionInvoice $record) => (int) ($record->invoice_count ?? 1) > 1 || $record->getRemainingAmount() <= 0 || $record->is_locked)
                        ->action(function (TuitionInvoice $record) {
                            $result = app(TuitionInvoiceService::class)->sendReminder($record);

                            Notification::make()
                                ->title($result->isSuccess() ? $result->getMessage() : 'Lỗi')
                                ->body($result->isError() ? $result->getMessage() : null)
                                ->color($result->isSuccess() ? 'success' : 'danger')
                                ->send();
                        }),

                    Action::make('remind_month_payment')
                        ->label('Nhắc đóng học phí')
                        ->icon(Heroicon::BellAlert)
                        ->hidden(fn (TuitionInvoice $record) => (int) ($record->invoice_count ?? 1) <= 1 || $record->getRemainingAmount() <= 0 || $record->is_locked)
                        ->action(function (TuitionInvoice $record) {
                            $result = app(TuitionInvoiceService::class)->sendBulkReminder(new Collection([$record]));

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
                        ->hidden()
                        ->url(fn (TuitionInvoice $record) => route('tuition-invoices.pdf', ['invoice' => $record]))
                        ->openUrlInNewTab(),

                    Action::make('export_month_zip')
                        ->label('Xuất PDF')
                        ->icon(Heroicon::DocumentArrowDown)
                        ->color('info')
                        ->hidden(fn (TuitionInvoice $record) => false)
                        ->schema([
                            Select::make('payment_method')
                                ->label('Phương thức xuất')
                                ->options(PaymentMethod::options())
                                ->required(),
                        ])
                        ->action(function (TuitionInvoice $record, array $data) {
                            $service = app(TuitionInvoiceService::class);
                            $result = $service->prepareBulkInvoiceZip(new Collection([$record]), (int) $data['payment_method']);

                            if ($result->isError()) {
                                Notification::make()->danger()->title('Lỗi')->body($result->getMessage())->send();
                                throw new Halt();
                            }

                            return $service->downloadPreparedZip($result->getData());
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
                    BulkAction::make('export_bulk_pdf')
                        ->label('Xuất hóa đơn hàng loạt')
                        ->icon(Heroicon::ArchiveBoxArrowDown)
                        ->color('info')
                        ->requiresConfirmation()
                        ->modalHeading('Xuất hóa đơn học phí hàng loạt')
                        ->modalDescription('Hệ thống sẽ tạo một file ZIP chứa PDF của tất cả hóa đơn đã chọn.')
                        ->schema([
                            Select::make('payment_method')
                                ->label('Phương thức xuất')
                                ->options(PaymentMethod::options())
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $service = app(TuitionInvoiceService::class);
                            $result = $service->prepareBulkInvoiceZip($records, (int) $data['payment_method']);

                            if ($result->isError()) {
                                Notification::make()->danger()->title('Lỗi')->body($result->getMessage())->send();
                                throw new Halt();
                            }

                            return $service->downloadPreparedZip($result->getData());
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    protected static function getMonthlyInvoiceSummary(TuitionInvoice $record): array
    {
        $data = self::getMonthlyInvoiceDetailData($record);
        $items = collect($data['items'] ?? []);
        $totals = $data['totals'] ?? [];

        return [
            'subject_count' => $items->count(),
            'total_sessions' => (int) ($totals['total_sessions'] ?? 0),
            'attended_sessions' => (int) ($totals['attended_sessions'] ?? 0),
            'total_study_fee' => (int) ($totals['total_study_fee'] ?? 0),
            'previous_debt' => (int) ($totals['previous_debt'] ?? 0),
            'total_amount' => (int) ($totals['total_amount'] ?? 0),
            'paid_amount' => (int) ($totals['paid_amount'] ?? 0),
            'remaining_amount' => (int) ($totals['remaining_amount'] ?? 0),
        ];
    }

    protected static function getMonthlyInvoiceDetailData(TuitionInvoice $record): array
    {
        $cacheKey = (string) $record->student_id . '|' . (string) $record->month;

        if (isset(self::$monthlyDetailCache[$cacheKey])) {
            return self::$monthlyDetailCache[$cacheKey];
        }

        $result = app(TuitionInvoiceService::class)->getStudentMonthlyInvoiceDetail($record);

        return self::$monthlyDetailCache[$cacheKey] = $result->isSuccess() ? $result->getData() : [];
    }
}

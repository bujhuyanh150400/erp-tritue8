<?php


namespace App\Filament\Resources\Teachers\Tables;

use App\Constants\EmployeeStatus;
use App\Core\Helpers\BankInfo;
use App\Filament\Components\CustomSelect;
use App\Models\Teacher;
use App\Repositories\TeacherRepository;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TeachersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query, TeacherRepository $teacherRepo) {
                return $teacherRepo->getListingQuery($query);
            })
            ->columns([
                TextColumn::make('id_display')
                    ->label('Giáo viên')
                    ->icon(Heroicon::AcademicCap)
                    ->description(fn(Teacher $record) => "ID: {$record->user_id}")
                    ->state(fn(Teacher $record) => $record->full_name),

                TextColumn::make('phone')
                    ->label('Số điện thoại')
                    ->icon(Heroicon::Phone),

                TextColumn::make('email')
                    ->label('Email')
                    ->icon(Heroicon::Envelope),

                TextColumn::make('joined_at')
                    ->label('Ngày vào làm')
                    ->date('d/m/Y'),

                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->formatStateUsing(fn(EmployeeStatus $state) => $state->label())
                    ->badge(),

                TextColumn::make('bank_info')
                    ->label('Thông tin ngân hàng')
                    ->icon(Heroicon::CreditCard)
                    ->state(fn($record) => $record->bank_account_holder)
                    ->description(function ($record) {
                        $bank = BankInfo::getBankByBin($record->bank_bin);
                        $bankName = $bank['short_name'] ?? 'Unknown';
                        return "{$bankName} - {$record->bank_account_number}";
                 }),


                IconColumn::make('user.is_active')
                    ->label('Trạng thái TK')
                    ->alignCenter()
                    ->boolean()
                    ->trueIcon(Heroicon::CheckCircle)
                    ->falseIcon(Heroicon::XCircle),
            ])
            ->filters(
                filters: [
                    Filter::make('filters')
                        ->columns(4)
                        ->columnSpanFull()
                        ->schema([
                            TextInput::make('keyword')
                                ->placeholder('Tìm theo tên, SĐT, email...')
                                ->label('Tìm kiếm'),

                            Select::make('status')
                                ->label('Trạng thái')
                                ->options(EmployeeStatus::options())
                                ->searchable(),

                            // Nếu sau này có filter theo môn dạy
//                            CustomSelect::make('subject_id')
//                                ->label('Môn dạy')
//                                ->getOptionSelectService(SubjectService::class),

                            Select::make('is_active')
                                ->label('Trạng thái tài khoản')
                                ->options([
                                    1 => 'Đang hoạt động',
                                    0 => 'Đã khóa',
                                ])
                                ->placeholder('Tất cả')
                                ->default(null),

                            // Subject filter
                            Select::make('subject_id')
                                ->label('Môn dạy')
                                ->options(fn() => \App\Models\Subject::pluck('name', 'id')->toArray())
                                ->searchable(),
                        ])
                        ->query(function (Builder $query, array $data, TeacherRepository $teacherRepo): Builder {
                            return $teacherRepo->setFilters($query, $data);
                        }),
                ],
                layout: FiltersLayout::AboveContent
            )
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),

                    Action::make('toggle_active')
                        ->label(fn($record) => $record->user->is_active ? 'Khóa' : 'Mở khóa')
                        ->icon(fn($record) => $record->user->is_active ? Heroicon::LockClosed : Heroicon::LockOpen)
                        ->color(fn($record) => $record->user->is_active ? 'danger' : 'success')
                        ->requiresConfirmation()
                        ->action(fn($record) => $record->user->update([
                            'is_active' => !$record->user->is_active
                        ])),
                ]),
            ]);
    }
}

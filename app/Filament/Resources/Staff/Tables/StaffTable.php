<?php


namespace App\Filament\Resources\Staff\Tables;

use App\Constants\EmployeeStatus;
use App\Constants\StaffRoleType;
use App\Models\Staff;
use App\Repositories\StaffRepository;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
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

class StaffTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query, StaffRepository $repo) {
                return $repo->getListingQuery($query);
            })

            // ========================
            // COLUMNS
            // ========================
            ->columns([

                TextColumn::make('full_name')
                    ->label('Nhân viên')
                    ->description(fn(Staff $record) => "ID: {$record->user_id}"),

                TextColumn::make('phone')
                    ->label('SĐT')
                    ->icon(Heroicon::Phone),

                TextColumn::make('role_type')
                    ->label('Chức vụ')
                    ->formatStateUsing(fn($state) => $state?->label()),

                TextColumn::make('salary_type')
                    ->label('Hình thức lương'),

                TextColumn::make('salary_amount')
                    ->label('Mức lương')
                    ->formatStateUsing(fn($state) => $state ? number_format($state) : null),

                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->formatStateUsing(fn(EmployeeStatus $state) => $state->label())
                    ->badge(),

                IconColumn::make('is_active')
                    ->label('TK')
                    ->boolean()
                    ->trueIcon(Heroicon::CheckCircle)
                    ->falseIcon(Heroicon::XCircle)
                    ->alignCenter(),
            ])

            // ========================
            // FILTER
            // ========================
            ->filters([
                Filter::make('filters')
                    ->columns(4)
                    ->columnSpanFull()
                    ->schema([

                        TextInput::make('keyword')
                            ->label('Tìm kiếm')
                            ->placeholder('Tên, SĐT, ID...'),

                        Select::make('role_type')
                            ->label('Chức vụ')
                            ->options(StaffRoleType::options())
                            ->searchable(),

                        Select::make('status')
                            ->label('Trạng thái')
                            ->options(EmployeeStatus::options()),

                        Select::make('is_active')
                            ->label('Trạng thái tài khoản')
                            ->options([
                                1 => 'Đang hoạt động',
                                0 => 'Đã khóa',
                            ])
                            ->placeholder('Tất cả')
                            ->default(null),
                    ])
                    ->query(function (Builder $query, array $data, StaffRepository $repo) {
                        return $repo->setFilters($query, $data);
                    }),
            ], layout: FiltersLayout::AboveContent)

            // ========================
            // ACTIONS
            // ========================
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
                        ])
                        ),
                ]),
            ]);
    }
}

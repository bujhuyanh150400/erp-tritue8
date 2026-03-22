<?php

namespace App\Filament\Resources\Rooms\Tables;

use App\Constants\RoomStatus;
use App\Filament\Components\CommonAction;
use App\Models\Room;
use App\Models\Subject;
use App\Repositories\RoomRepository;
use App\Repositories\SubjectRepository;
use App\Services\RoomService;
use App\Services\SubjectService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RoomsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query, RoomRepository $roomRepo) {
                return $roomRepo->getListingQuery($query);
            })
            ->columns([
                TextColumn::make('name')
                    ->label('Tên phòng')
                    ->weight('bold'),

                TextColumn::make('capacity')
                    ->label('Sức chứa')
                    ->numeric(),

                TextColumn::make('active_classes_count')
                    ->label('Số lớp đang dùng')
                    ->badge()
                    ->formatStateUsing(fn($state) => ($state ?? 0) . ' lớp')
                    ->color('info'),

                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn(RoomStatus $state) => $state->label())
                    ->color(fn (RoomStatus $state): string => match ($state) {
                        RoomStatus::Active => 'success',
                        RoomStatus::Locked => 'danger',
                        RoomStatus::Maintenance => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('note')
                    ->label('Ghi chú')
                    ->limit(50)
                    ->tooltip(function ($column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 50 ? $state : null;
                    }),
            ])
            ->filters(filters: [
                Filter::make('filters')
                    ->columns(5)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('keyword')
                            ->placeholder('Tìm kiếm theo tên, ID, Tên môn học,...')
                            ->label('Tìm kiếm'),

                        Select::make('status')
                            ->label('Trạng thái')
                            ->native(false)
                            ->placeholder('Tất cả')
                            ->options(RoomStatus::options()),
                    ])
                    ->query(function (Builder $query, array $data, RoomRepository $roomRepo): Builder {
                        return $roomRepo->setFilters($query, $data);
                    }),
            ], layout: FiltersLayout::AboveContent)
            ->recordActions([
                ActionGroup::make([
                    CommonAction::editAction(),

                    Action::make('view_schedule')
                        ->label('Xem lịch phòng')
                        ->icon(Heroicon::CalendarDays)
                        ->color('info'),

                    Action::make('toggle_lock')
                        ->label(fn (Room $record) => $record->status === RoomStatus::Active ? 'Tạm khóa' : 'Mở khóa')
                        ->icon(fn (Room $record) => $record->status === RoomStatus::Active ? Heroicon::LockClosed : Heroicon::LockOpen)
                        ->color(fn (Room $record) => $record->status === RoomStatus::Active ? 'danger' : 'success')
                        ->visible(fn (Room $record) => $record->status === RoomStatus::Active || $record->status === RoomStatus::Locked)
                        ->requiresConfirmation()
                        ->modalHeading(fn (Room $record) => $record->status === RoomStatus::Active ? 'Xác nhận khóa phòng' : 'Xác nhận mở khóa phòng')
                        ->action(function (Room $record, RoomService $roomService) {
                            $result = $roomService->toggleLock($record);
                            if ($result->isError()) {
                                Notification::make()
                                    ->danger()
                                    ->title('Lỗi thao tác')
                                    ->body($result->getMessage())
                                    ->send();
                                return;
                            }

                            Notification::make()
                                ->success()
                                ->title('Thành công')
                                ->body('Đã cập nhật trạng thái phòng học.')
                                ->send();
                        }),
                ])
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

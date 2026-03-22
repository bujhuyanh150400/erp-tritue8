<?php

namespace App\Filament\Resources\Subjects\Tables;

use App\Filament\Components\CommonAction;
use App\Models\Subject;
use App\Repositories\SubjectRepository;
use App\Services\SubjectService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SubjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query, SubjectRepository $studentRepo) {
                return $studentRepo->getListingQuery($query);
            })
            ->columns([
                TextColumn::make('name')
                    ->label('Tên môn học')
                    ->weight('bold'),

                TextColumn::make('description')
                    ->label('Mô tả')
                    ->limit(50)
                    ->tooltip(function ($column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 50 ? $state : null;
                    }),

                TextColumn::make('active_classes_count')
                    ->label('Số lớp đang dùng')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn($state) => ($state ?? 0) . ' lớp')
                    ->sortable(),

                IconColumn::make('user.is_active')
                    ->label('Trạng thái')
                    ->alignCenter()
                    ->boolean()
                    ->trueIcon(Heroicon::CheckCircle)
                    ->falseIcon(Heroicon::XCircle),
            ])
            ->filters(
                filters: [
                    Filter::make('filters')
                        ->columns(5)
                        ->columnSpanFull()
                        ->schema([
                            TextInput::make('keyword')
                                ->placeholder('Tìm kiếm theo tên, ID, Tên môn học,...')
                                ->label('Tìm kiếm'),

                            Select::make('is_active')
                                ->label('Trạng thái')
                                ->native(false)
                                ->placeholder('Tất cả')
                                ->options([
                                    true => 'Đang hoạt động',
                                    false => 'Không hoạt động',
                                ]),
                        ])
                        ->query(function (Builder $query, array $data, SubjectRepository $studentRepo): Builder {
                            return $studentRepo->setFilters($query, $data);
                        }),
                ],
                layout: FiltersLayout::AboveContent
            )

            ->recordActions([
                ActionGroup::make([
                    CommonAction::editAction(),
                    Action::make('toggle_active')
                        ->label(fn($record) => $record->is_active ? 'Khóa' : 'Mở khóa')
                        ->icon(fn($record) => $record->is_active ? Heroicon::LockClosed : Heroicon::LockOpen)
                        ->color(fn($record) => $record->is_active ? 'danger' : 'success')
                        ->requiresConfirmation()
                        ->modalHeading(fn($record) => $record->is_active ? 'Xác nhận khóa môn học' : 'Xác nhận mở khóa môn học')
                        ->action(function (Subject $record, SubjectService $subjectService) {
                            $result = $subjectService->toggleActive($record);
                            if ($result->isError()){
                                Notification::make()
                                    ->danger()
                                    ->title('Lỗi thao tác')
                                    ->body($result->getMessage())
                                    ->send();
                            }else{
                                Notification::make()
                                    ->success()
                                    ->title('Thành công')
                                    ->body('Đã cập nhật trạng thái môn học.')
                                    ->send();
                            }
                        }),
                ])
            ]);
    }
}

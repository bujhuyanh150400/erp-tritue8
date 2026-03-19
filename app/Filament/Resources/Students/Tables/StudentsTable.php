<?php

namespace App\Filament\Resources\Students\Tables;

use App\Constants\Gender;
use App\Constants\GradeLevel;
use App\Filament\Components\CustomSelect;
use App\Models\Student;
use App\Repositories\StudentRepository;
use App\Services\SubjectService;
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

class StudentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query, StudentRepository $studentRepo) {
                return $studentRepo->getListingQuery($query);
            })
            ->columns([
                TextColumn::make('id_display')
                    ->label('Học sinh')
                    ->description(fn(Student $record) => "ID: {$record->user_id}")
                    ->state(fn(Student $record) => $record->full_name),

                TextColumn::make('dob')
                    ->label('Ngày sinh')
                    ->date('d/m/Y'),

                TextColumn::make('gender')
                    ->label('Giới tính')
                    ->formatStateUsing(fn(Gender $state) => $state->label()),

                TextColumn::make('grade_level')
                    ->label('Khối')
                    ->formatStateUsing(fn(GradeLevel $state) => $state->label())
                    ->badge(),

                TextColumn::make('parent_info')
                    ->label('Phụ huynh')
                    ->state(fn(Student $record) => $record->parent_name)
                    ->icon(Heroicon::User)
                    ->iconColor('gray')
                    ->description(fn(Student $record) => "SĐT: {$record->parent_phone}"),

                TextColumn::make('subject_names')
                    ->label('Môn đang học')
                    ->badge()
                    ->separator(',')
                    ->color('info')
                    ->placeholder('Chưa vào lớp'),

                TextColumn::make('total_stars')
                    ->label('Số sao')
                    ->icon(Heroicon::Star)
                    ->iconColor('warning')
                    ->sortable()
                    ->alignCenter(),

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
                                ->placeholder('Tìm kiếm theo tên, ID, SĐT Phụ huynh,...')
                                ->label('Tìm kiếm'),
                            Select::make('grade_level')
                                ->label('Khối')
                                ->searchable()
                                ->options(GradeLevel::options()),
                            CustomSelect::make('subject_id')
                                ->label('Môn học')
                                ->placeholder("Chọn môn học")
                                ->noOptionsMessage("Không tìm thấy môn học nào.")
                                ->getOptionSelectService(SubjectService::class),
//                SelectFilter::make('class_id')
//                    ->label('Lớp')
//                    ->searchable()
//                    ->preload(),


//                SelectFilter::make('teacher_id')
//                    ->label('Giáo viên')
//                    ->options(fn () => User::where('role', 'teacher')->pluck('username', 'id'))
//                    ->searchable(),
                        ])
                        ->query(function (Builder $query, array $data, StudentRepository $studentRepo): Builder {
                            return $studentRepo->setFilters($query, $data);
                        }),
                ],
                layout: FiltersLayout::AboveContent
            )
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    Action::make('reward_history')
                        ->label('Lịch sử đổi thưởng')
                        ->icon('heroicon-o-gift'),
                    Action::make('toggle_active')
                        ->label(fn($record) => $record->user->is_active ? 'Khóa' : 'Mở khóa')
                        ->icon(fn($record) => $record->user->is_active ? Heroicon::LockClosed : Heroicon::LockOpen)
                        ->color(fn($record) => $record->user->is_active ? 'danger' : 'success')
                        ->requiresConfirmation()
                        ->action(fn($record) => $record->user->update(['is_active' => !$record->user->is_active])),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

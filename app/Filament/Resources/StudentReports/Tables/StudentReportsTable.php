<?php

namespace App\Filament\Resources\StudentReports\Tables;

use App\Constants\GradeLevel;
use App\Filament\Components\CustomSelect;
use App\Filament\Resources\StudentReports\StudentReportResource;
use App\Models\Student;
use App\Repositories\StudentRepository;
use App\Services\ClassService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StudentReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query, StudentRepository $studentRepo) {
                return $studentRepo->getListingQuery($query);
            })
            ->columns([
                TextColumn::make('full_name')
                    ->label('Tên học sinh'),

                TextColumn::make('parent_info')
                    ->label('Thông tin phụ huynh')
                    ->state(fn (Student $record) => $record->parent_name)
                    ->icon(Heroicon::User)
                    ->iconColor('gray')
                    ->description(fn (Student $record) => "SĐT: {$record->parent_phone}"),

                TextColumn::make('grade_level')
                    ->label('Khối')
                    ->formatStateUsing(fn (GradeLevel $state) => $state->label())
                    ->badge(),

                TextColumn::make('activeClassEnrollments.class.name')
                    ->label('Lớp đang học')
                    ->badge()
                    ->separator(','),

                IconColumn::make('has_monthly_report')
                    ->label('Đã có báo cáo tháng')
                    ->boolean()
                    ->trueIcon(Heroicon::CheckCircle)
                    ->falseIcon(Heroicon::XCircle)
                    ->state(fn (Student $record) => (bool) ($record->has_monthly_report ?? false)),
            ])
            ->filters(
                filters: [
                    Filter::make('filters')
                        ->columns(4)
                        ->columnSpanFull()
                        ->schema([
                            TextInput::make('month')
                                ->label('Tháng')
                                ->type('month')
                                ->required()
                                ->default(now()->format('Y-m')),

                            TextInput::make('keyword')
                                ->label('Theo keyword')
                                ->placeholder('Tên học sinh, SĐT phụ huynh...'),

                            CustomSelect::make('class_id')
                                ->label('Theo lớp')
                                ->getOptionSelectService(ClassService::class),

                            Select::make('grade_level')
                                ->label('Theo khối')
                                ->searchable()
                                ->options(GradeLevel::options()),
                        ])
                        ->query(function (Builder $query, array $data, StudentRepository $studentRepo): Builder {
                            return $studentRepo->setStudentReportFilters($query, $data);
                        }),
                ],
                layout: FiltersLayout::AboveContent
            )
            ->recordUrl(function (Student $record, $livewire): string {
                $month = (string) (
                    data_get($livewire, 'tableFilters.filters.month')
                    ?? request()->input('tableFilters.filters.month')
                    ?? now()->format('Y-m')
                );

                return StudentReportResource::getUrl('view', [
                    'record' => $record,
                    'month' => $month,
                ]);
            })
            ->recordActions([])
            ->toolbarActions([]);
    }
}

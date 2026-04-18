<?php

namespace App\Filament\Resources\StudentReports;

use App\Filament\Navigation\NavigationGroup;
use App\Filament\Resources\StudentReports\Pages\ListStudentReports;
use App\Filament\Resources\StudentReports\Pages\ViewStudentReport;
use App\Filament\Resources\StudentReports\Schemas\StudentReportInfolist;
use App\Filament\Resources\StudentReports\Tables\StudentReportsTable;
use App\Models\Student;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class StudentReportResource extends Resource
{
    protected static ?string $model = Student::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::PresentationChartBar;

    protected static string | UnitEnum | null $navigationGroup = NavigationGroup::REPORT;


    protected static ?string $navigationLabel = 'Báo cáo học sinh';

    protected static ?string $modelLabel = 'Báo cáo học sinh';

    public static function infolist(Schema $schema): Schema
    {
        return StudentReportInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StudentReportsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStudentReports::route('/'),
            'view' => ViewStudentReport::route('/{record}'),
        ];
    }
}

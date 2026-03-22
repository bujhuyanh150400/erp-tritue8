<?php

namespace App\Filament\Resources\Teachers;

use App\Filament\Navigation\NavigationGroup;
use App\Filament\Resources\Teachers\Pages\CreateTeacher;
use App\Filament\Resources\Teachers\Pages\EditTeacher;
use App\Filament\Resources\Teachers\Pages\ListTeacher;
use App\Filament\Resources\Teachers\Pages\ViewTeacher;
use App\Filament\Resources\Teachers\Schemas\TeacherForm;
use App\Filament\Resources\Teachers\Schemas\TeacherInfolist;
use App\Filament\Resources\Teachers\Tables\TeachersTable;
use App\Models\Teacher;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TeacherResource extends Resource
{
    protected static ?string $model = Teacher::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::AcademicCap;

    protected static string | UnitEnum | null $navigationGroup = NavigationGroup::USER;
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationLabel = 'Giáo viên';

    protected static ?string $modelLabel = 'Giáo viên';


    public static function form(Schema $schema): Schema
    {
        return TeacherForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TeachersTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TeacherInfolist::configure($schema);
    }
    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTeacher::route('/'),
            'create' => CreateTeacher::route('/create'),
            'edit' => EditTeacher::route('/{record}/edit'),
            'view' => ViewTeacher::route('/{record}'),
        ];
    }
}

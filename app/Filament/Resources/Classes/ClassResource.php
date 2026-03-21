<?php

namespace App\Filament\Resources\Classes;

use App\Filament\Resources\Classes\Pages\CreateClass;
use App\Filament\Resources\Classes\Pages\EditClass;
use App\Filament\Resources\Classes\Pages\ListClasses;
use App\Filament\Resources\Classes\Pages\ViewClass;
use App\Filament\Resources\Classes\Schemas\ClassForm;
use App\Filament\Resources\Classes\Schemas\ClassInfolist;
use App\Filament\Resources\Classes\Tables\ClassesTable;
use App\Models\SchoolClass;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use App\Filament\Navigation\NavigationGroup;

class ClassResource extends Resource
{
    protected static ?string $model = SchoolClass::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::AcademicCap;
    protected static string|null|\UnitEnum $navigationGroup = NavigationGroup::EDUCATION;

    protected static ?string $navigationLabel = 'Lớp học';
    protected static ?string $pluralModelLabel = 'lớp học';
    protected static ?string $modelLabel = 'lớp học';

    public static function form(Schema $schema): Schema
    {
        return ClassForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ClassesTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ClassInfolist::configure($schema);
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
            'index' => ListClasses::route('/'),
            'create' => CreateClass::route('/create'),
            'edit' => EditClass::route('/{record}/edit'),
            'view' => ViewClass::route('/{record}'),
        ];
    }
}

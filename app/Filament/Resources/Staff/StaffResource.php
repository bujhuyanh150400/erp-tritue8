<?php

namespace App\Filament\Resources\Staff;

use App\Models\Staff;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use App\Filament\Resources\Staff\Schemas\StaffForm;
use App\Filament\Resources\Staff\Schemas\StaffInfolist;
use App\Filament\Resources\Staff\Tables\StaffTable;
use App\Filament\Resources\Staff\Pages\ListStaff;
use App\Filament\Resources\Staff\Pages\CreateStaff;
use App\Filament\Resources\Staff\Pages\EditStaff;
use App\Filament\Resources\Staff\Pages\ViewStaff;
use App\Filament\Navigation\NavigationGroup;
use UnitEnum;
use BackedEnum;


class StaffResource extends Resource
{
    protected static ?string $model = Staff::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::UserGroup;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::USER;
    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Nhân viên';
    protected static ?string $pluralModelLabel = 'nhân viên';
    protected static ?string $modelLabel = 'nhân viên';

    public static function form(Schema $schema): Schema
    {
        return StaffForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StaffTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return StaffInfolist::configure($schema);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStaff::route('/'),
            'create' => CreateStaff::route('/create'),
            'edit' => EditStaff::route('/{record}/edit'),
            'view' => ViewStaff::route('/{record}'),
        ];
    }
}

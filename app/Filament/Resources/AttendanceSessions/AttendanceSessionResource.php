<?php

namespace App\Filament\Resources\AttendanceSessions;

use App\Filament\Navigation\NavigationGroup;
use App\Filament\Resources\AttendanceSessions\Pages\ListAttendanceSessions;
use App\Filament\Resources\AttendanceSessions\Pages\ViewAttendanceSession;
use App\Filament\Resources\AttendanceSessions\Schemas\AttendanceSessionInfolist;
use App\Filament\Resources\AttendanceSessions\Tables\AttendanceSessionTable;
use App\Models\AttendanceSession;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AttendanceSessionResource extends Resource
{
    protected static ?string $model = AttendanceSession::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ClipboardDocumentCheck;

    protected static string | UnitEnum | null $navigationGroup = NavigationGroup::EDUCATION;

    protected static ?string $navigationLabel = 'Điểm danh';

    protected static ?string $modelLabel = 'buổi điểm danh';

    protected static ?string $pluralModelLabel = 'buổi điểm danh';

    public static function table(Table $table): Table
    {
        return AttendanceSessionTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AttendanceSessionInfolist::configure($schema);
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
            'index' => ListAttendanceSessions::route('/'),
            'view' => ViewAttendanceSession::route('/{record}'),
        ];
    }
}

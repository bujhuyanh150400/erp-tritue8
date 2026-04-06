<?php

namespace App\Filament\Resources\RewardItemResource;

use App\Filament\Navigation\NavigationGroup;
use App\Filament\Resources\RewardItemResource\Pages\CreateRewardItem;
use App\Filament\Resources\RewardItemResource\Pages\EditRewardItem;
use App\Filament\Resources\RewardItemResource\Pages\ListRewardItems;
use App\Filament\Resources\RewardItemResource\Schemas\RewardItemForm;
use App\Filament\Resources\RewardItemResource\Tables\RewardItemsTable;
use App\Models\RewardItem;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use BackedEnum;
use UnitEnum;

class RewardItemResource extends Resource
{
    protected static ?string $model = RewardItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Gift; // Đã sửa thành Heroicon::Gift
    protected static string | UnitEnum | null $navigationGroup = NavigationGroup::EDUCATION; // Đã sửa thành NavigationGroup::SETTING
    protected static ?int $navigationSort = 10;
    protected static ?string $navigationLabel = 'Danh mục phần thưởng';
    protected static ?string $modelLabel = 'Phần thưởng';

    public static function form(Schema $schema): Schema
    {
        return RewardItemForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RewardItemsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRewardItems::route('/'),
            'create' => CreateRewardItem::route('/create'),
            'edit' => EditRewardItem::route('/{record}/edit'),
        ];
    }
}

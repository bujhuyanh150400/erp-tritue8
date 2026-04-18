<?php

namespace App\Filament\Resources\TuitionInvoices;

use App\Filament\Navigation\NavigationGroup;
use App\Filament\Resources\TuitionInvoices\Pages\ListTuitionInvoices;
use App\Filament\Resources\TuitionInvoices\Pages\ViewTuitionInvoice;
use App\Filament\Resources\TuitionInvoices\Tables\TuitionInvoicesTable;
use App\Models\TuitionInvoice;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TuitionInvoiceResource extends Resource
{
    protected static ?string $model = TuitionInvoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Banknotes;
    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::FINANCE;
    protected static ?string $navigationLabel = 'Hóa đơn học phí';
    protected static ?string $modelLabel = 'Hóa đơn học phí';
    protected static ?int $navigationSort = 1;

    public static function table(Table $table): Table
    {
        return TuitionInvoicesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTuitionInvoices::route('/'),
            'view' => ViewTuitionInvoice::route('/{record}'),
        ];
    }
}

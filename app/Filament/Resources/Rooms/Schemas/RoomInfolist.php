<?php

namespace App\Filament\Resources\Rooms\Schemas;

use App\Constants\RoomStatus;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RoomInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Thông tin phòng học')
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('name')
                                ->label('Tên phòng'),
                            TextEntry::make('capacity')
                                ->label('Sức chứa'),
                            TextEntry::make('status')
                                ->label('Trạng thái')
                                ->badge()
                                ->formatStateUsing(fn (RoomStatus $state): string => $state->label())
                                ->color(fn (RoomStatus $state): string => match ($state) {
                                    RoomStatus::Active => 'success',
                                    RoomStatus::Locked => 'danger',
                                    RoomStatus::Maintenance => 'warning',
                                    default => 'gray',
                                }),
                        ]),
                        TextEntry::make('note')
                            ->label('Ghi chú')
                            ->columnSpanFull(),
                    ])
            ]);
    }
}

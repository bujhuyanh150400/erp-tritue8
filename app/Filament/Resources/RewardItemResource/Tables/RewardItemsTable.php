<?php

namespace App\Filament\Resources\RewardItemResource\Tables;

use App\Models\RewardItem;
use App\Services\RewardItemService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RewardItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Tên phần thưởng')
                    ->icon(Heroicon::OutlinedGift)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('points_required')
                    ->label('Điểm sao cần đổi')
                    ->badge()
                    ->color('warning')
                    ->icon(Heroicon::OutlinedStar)
                    ->sortable(),

                TextColumn::make('reward_type')
                    ->label('Loại')
                    ->badge()
                    ->icon(Heroicon::OutlinedCube)
                    ->formatStateUsing(fn ($state) => $state->label()),

                IconColumn::make('is_active')
                    ->label('Trạng thái')
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedCheckCircle)
                    ->falseIcon(Heroicon::OutlinedXCircle)
                    ->sortable(),
            ])
            ->defaultSort('points_required', 'asc')
            ->actions([
                EditAction::make()
                    ->icon(Heroicon::OutlinedPencil),

                Action::make('toggleStatus')
                    ->label(fn ($record) => $record->is_active ? 'Tắt' : 'Bật')
                    ->icon(fn ($record) => $record->is_active
                        ? Heroicon::OutlinedNoSymbol
                        : Heroicon::OutlinedCheckCircle
                    )
                    ->color(fn ($record) => $record->is_active ? 'danger' : 'success')
                    ->action(function ($record, $livewire) {
                        $service = app(RewardItemService::class);
                        $result = $service->toggleStatus($record->id);

                        if ($result->isSuccess()) {
                            Notification::make()
                                ->title('Thành công')
                                ->body($result->getMessage())
                                ->success()
                                ->send();

                            $livewire->dispatch('refresh');
                        } else {
                            Notification::make()
                                ->title('Lỗi')
                                ->body($result->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ]);
    }
}

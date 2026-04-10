<?php

namespace App\Filament\Resources\RewardItemResource\Schemas;

use App\Constants\RewardType;
use App\Services\RewardItemService;
use Filament\Support\RawJs;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class RewardItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Tên phần thưởng')
                    ->required()
                    ->maxLength(255),

                TextInput::make('points_required')
                    ->label('Điểm sao cần đổi')
                    ->numeric()
                    ->required()
                    ->minValue(1)
                    ->afterStateHydrated(function ($component, $state, $record) {
                        if (!$record) return;

                        $service = app(RewardItemService::class);
                        $hasRedemptions = $service->hasRedemptions($record->id);

                        if ($hasRedemptions) {
                            $component->disabled(true);
                            $component->helperText('Không thể đổi điểm khi đã có học sinh đổi quà.');
                        }
                    }),

                Select::make('reward_type')
                    ->label('Loại phần thưởng')
                    ->required()
                    ->options(RewardType::options())
                    ->live()
                    ->afterStateHydrated(function ($component, $state, $record) {
                        if (!$record) return;

                        $service = app(RewardItemService::class);
                        $hasRedemptions = $service->hasRedemptions($record->id);

                        if ($hasRedemptions) {
                            $component->disabled(true);
                            $component->helperText('Không thể đổi loại phần thưởng khi đã có học sinh đổi quà.');
                        }
                    }),

                TextInput::make('discount_amount')
                    ->label('Giá trị giảm học phí')
                    ->suffix('đ')
                    ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                    ->stripCharacters(['.'])
                    ->dehydrateStateUsing(fn ($state) => blank($state) ? null : (int) str_replace('.', '', (string) $state))
                    ->visible(fn ($get) => (int) $get('reward_type') === RewardType::Discount->value)
                    ->required(fn ($get) => (int) $get('reward_type') === RewardType::Discount->value)
                    ->afterStateHydrated(function ($component, $state, $record) {
                        if (!$record) return;

                        $service = app(RewardItemService::class);
                        $hasRedemptions = $service->hasRedemptions($record->id);

                        if ($hasRedemptions) {
                            $component->disabled(true);
                            $component->helperText('Không thể đổi giá trị giảm khi đã có học sinh đổi quà.');
                        }
                    }),

                Textarea::make('note')
                    ->label('Ghi chú')
                    ->columnSpanFull(),

                Toggle::make('is_active')
                    ->label('Trạng thái hoạt động')
                    ->default(true),
            ]);
    }
}

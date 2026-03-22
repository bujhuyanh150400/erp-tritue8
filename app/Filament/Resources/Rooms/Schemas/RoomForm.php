<?php

namespace App\Filament\Resources\Rooms\Schemas;

use App\Constants\RoomStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RoomForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columnSpanFull()
                    ->compact()
                    ->schema([
                        TextInput::make('name')
                            ->label('Tên phòng')
                            ->helperText('Tên phòng phải duy nhất')
                            ->required()
                            ->unique(table: 'rooms', column: 'name', ignoreRecord: true)
                            ->validationMessages([
                                'required' => 'Tên phòng không được để trống',
                                'unique' => 'Tên phòng đã tồn tại',
                                'min' => 'Tên phòng tối thiểu 4 ký tự',
                            ])
                            ->extraAttributes([
                                'required' => false
                            ]),

                        TextInput::make('capacity')
                            ->label('Sức chứa')
                            ->helperText('Số lượng học viên tối đa của phòng')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->validationMessages([
                                'required' => 'Vui lòng nhập sức chứa',
                                'numeric' => 'Sức chứa phải là số',
                                'min' => 'Sức chứa phải lớn hơn 0',
                            ])
                            ->extraAttributes([
                                'required' => false
                            ]),

                        Select::make('status')
                            ->label('Trạng thái')
                            ->native(false)
                            ->options(RoomStatus::options())
                            ->default(RoomStatus::Active)
                            ->required()
                            ->disabled(fn($livewire) => $livewire instanceof EditRecord)
                            ->validationMessages([
                                'required' => 'Vui lòng chọn trạng thái',
                            ]),

                        Textarea::make('note')
                            ->label('Ghi chú')
                            ->placeholder('Nhập ghi chú nếu có')
                            ->columnSpanFull(),

                    ]),
            ]);
    }
}

<?php

namespace App\Filament\Resources\Subjects\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SubjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->compact()
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->label('Tên môn học')
                            ->required()
                            ->maxLength(255)
                            // Chặn trùng lặp ngay từ giao diện (tùy chọn thêm để UX tốt hơn)
                            ->unique(table: 'subjects', column: 'name', ignoreRecord: true)
                            ->validationMessages([
                                'unique' => 'Tên môn học này đã tồn tại trong hệ thống.',
                            ]),
                        Toggle::make('is_active')
                            ->label('Hoạt động')
                            ->default(true)
                            ->inline(false),
                        Textarea::make('description')
                            ->label('Mô tả môn học')
                            ->maxLength(2000)
                            ->columnSpanFull()
                            ->rows(4),
                    ])
            ]);
    }
}

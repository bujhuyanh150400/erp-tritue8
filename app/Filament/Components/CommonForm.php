<?php

namespace App\Filament\Components;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class CommonForm
{

    /**
     * Tạo trường nhập tên đăng nhập (User Name).
     * @return TextInput
     */
    public static function userNameInput()
    {
        return TextInput::make('user_name')
            ->label('Tên đăng nhập')
            ->helperText("Tên đăng nhập không thể thay đổi sau khi tạo.")
            ->maxLength(255)
            ->minLength(6)
            ->required()
            ->disabled(fn($livewire) => $livewire instanceof EditRecord)
            ->unique(
                table: 'users',
                column: 'username',
                ignorable: fn($record) => $record?->user
            )
            ->validationMessages([
                'unique' => 'Tên đăng nhập đã tồn tại, vui lòng thử lại.',
            ]);
    }

    /**
     * Tạo trường nhập mật khẩu với tùy chọn thay đổi tên trường.
     * @param string $make
     * @return TextInput
     */
    public static function passwordInput(string $make = "password")
    {
        return TextInput::make($make)
            ->label(fn ($livewire) => $livewire instanceof EditRecord ? 'Đổi mật khẩu' : 'Mật khẩu')
            ->placeholder(fn ($livewire) => $livewire instanceof EditRecord ? 'Để trống nếu không muốn đổi' : 'Nhập mật khẩu mới')
            ->helperText('Mật khẩu phải có ít nhất 8 ký tự, bao gồm chữ hoa, chữ thường và số.')
            ->password()
            ->revealable()
            ->required(fn($livewire): bool => $livewire instanceof CreateRecord)
            ->rule(
                Password::min(8)
                    ->letters()
                    ->numbers()
                    ->mixedCase()
            )
            ->validationMessages([
                'min' => 'Mật khẩu phải có ít nhất :min ký tự.',
                'mixed' => 'Mật khẩu phải bao gồm cả chữ hoa và chữ thường.',
                'letters' => 'Mật khẩu phải chứa ít nhất một chữ cái.',
            ])
            // Nút Random Password
            ->suffixAction(
                Action::make('generate_password')
                    ->icon(Heroicon::ArrowPath)
                    ->tooltip('Tạo mật khẩu ngẫu nhiên')
                    ->action(function (Set $set)  use ($make) {
                        $set($make, Str::password(length: 8, letters: true, numbers: true, symbols: false));
                    })
            );
    }

}

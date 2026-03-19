<?php

namespace App\Filament\Resources\Teachers\Schemas;

use App\Constants\EmployeeStatus;
use App\Core\Helpers\BankInfo;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class TeacherForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // CỘT TRÁI: THÔNG TIN TÀI KHOẢN
                Section::make('Thông tin tài khoản')
                    ->compact()
                    ->schema([
                        TextInput::make('full_name')
                            ->label('Họ và tên')
                            ->helperText('Nhập họ tên giáo viên, sẽ tự động tạo username')
                            ->required()
                            ->maxLength(255)
                            ->live(debounce: 500)
                            ->afterStateUpdated(function (Get $get, Set $set, ?string $state, $livewire) {
                                if ($livewire instanceof EditRecord) {
                                    return;
                                }
                                $base = 'gv_' . Str::slug($state, '');
                                $set('user_name', $base);
                            }),

                        TextInput::make('user_name')
                            ->label('Tên đăng nhập')
                            ->required()
                            ->disabled(fn($livewire) => $livewire instanceof EditRecord)
                            ->unique(
                                table: 'users',
                                column: 'username',
                                ignorable: fn($record) => $record?->user
                            )
                            ->validationMessages([
                                'unique' => 'Tên đăng nhập đã tồn tại, vui lòng thử lại.',
                            ]),

                        TextInput::make('password')
                            ->label(fn($livewire) => $livewire instanceof EditRecord ? 'Đổi mật khẩu' : 'Mật khẩu')
                            ->password()
                            ->revealable()
                            ->required(fn($livewire): bool => $livewire instanceof CreateRecord)
                            ->rule(
                                Password::min(8)
                                    ->letters()
                                    ->mixedCase()
                            )
                            ->validationMessages([
                                'min' => 'Mật khẩu phải có ít nhất :min ký tự.',
                                'mixed' => 'Phải có chữ hoa và chữ thường.',
                                'letters' => 'Phải có ít nhất một chữ cái.',
                            ])
                            ->dehydrateStateUsing(fn ($state) => filled($state) ? bcrypt($state) : null)
                            ->dehydrated(fn ($state) => filled($state))
                            ->suffixAction(
                                Action::make('generate_password')
                                    ->icon(Heroicon::ArrowPath)
                                    ->tooltip('Tạo mật khẩu ngẫu nhiên')
                                    ->action(function (Set $set) {
                                        $set('password', Str::password(length: 8, letters: true, numbers: true, symbols: false));
                                    })
                            ),
                    ]),

                // CỘT PHẢI: THÔNG TIN GIÁO VIÊN
                Section::make('Thông tin giáo viên')
                    ->compact()
                    ->schema([
                        TextInput::make('phone')
                            ->label('Số điện thoại')
                            ->tel()
                            ->required()
                            ->regex('/^0[0-9]{9,10}$/')
                            ->unique(
                                table: 'teachers',
                                column: 'phone',
                                ignorable: fn($record) => $record
                            )
                            ->validationMessages([
                                'regex' => 'Số điện thoại không hợp lệ.',
                                'unique' => 'Số điện thoại đã tồn tại.',
                            ]),

                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->unique(
                                table: 'teachers',
                                column: 'email',
                                ignorable: fn($record) => $record
                            )
                            ->validationMessages([
                                'unique' => 'Email đã tồn tại.',
                            ]),

                        TextInput::make('address')
                            ->label('Địa chỉ')
                            ->required()
                            ->columnSpanFull(),

                        Select::make('bank_bin')
                            ->label('Ngân hàng')
                            ->required()
                            ->searchable()
                            ->options(BankInfo::options()),

                        TextInput::make('bank_account_number')
                            ->label('Số tài khoản')
                            ->regex('/^[0-9]+$/')
                            ->validationMessages([
                                'regex' => 'Số tài khoản chỉ được chứa số.',
                            ]),

                        TextInput::make('bank_account_holder')
                            ->label('Chủ tài khoản'),

                        Select::make('status')
                            ->label('Trạng thái')
                            ->options(EmployeeStatus::options())
                            ->default(EmployeeStatus::Active)
                            ->required(),

                        DatePicker::make('joined_at')
                            ->label('Ngày vào làm')
                            ->required()
                            ->displayFormat('d/m/Y'),
                    ]),
            ]);
    }
}

<?php


namespace App\Filament\Resources\Staff\Schemas;

use App\Constants\EmployeeStatus;
use App\Constants\StaffRoleType;
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

class StaffForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                // ==========================================
                // CỘT TRÁI: TÀI KHOẢN
                // ==========================================
                Section::make('Thông tin tài khoản')
                    ->compact()
                    ->schema([

                        TextInput::make('full_name')
                            ->label('Họ và tên')
                            ->required()
                            ->live(debounce: 500)
                            ->afterStateUpdated(function (Get $get, Set $set, ?string $state, $livewire) {

                                if ($livewire instanceof EditRecord) {
                                    return;
                                }

                                $set('user_name', 'nv_' . Str::slug($state, ''));
                            }),

                        TextInput::make('user_name')
                            ->label('Tên đăng nhập')
                            ->required()
                            ->disabled(fn($livewire) => $livewire instanceof EditRecord)
                            ->unique(
                                table: 'users',
                                column: 'username',
                                ignorable: fn($record) => $record?->user
                            ),

                        TextInput::make('password')
                            ->label(fn($livewire) => $livewire instanceof EditRecord ? 'Đổi mật khẩu' : 'Mật khẩu')
                            ->password()
                            ->revealable()
                            ->required(fn($livewire) => $livewire instanceof CreateRecord)
                            ->rule(
                                Password::min(8)->letters()->mixedCase()
                            )
                            ->dehydrateStateUsing(fn($state) => filled($state) ? bcrypt($state) : null)
                            ->dehydrated(fn($state) => filled($state))
                            ->suffixAction(
                                Action::make('generate_password')
                                    ->icon(Heroicon::ArrowPath)
                                    ->tooltip('Tạo mật khẩu ngẫu nhiên')
                                    ->action(fn(Set $set) => $set('password', Str::password(8))
                                    )
                            ),

                        TextInput::make('phone')
                            ->label('Số điện thoại')
                            ->required()
                            ->regex('/^0[0-9]{9,10}$/'),
                    ]),

                // ==========================================
                // CỘT PHẢI: NHÂN VIÊN
                // ==========================================
                Section::make('Thông tin nhân viên')
                    ->compact()
                    ->schema([

                        Select::make('role_type')
                            ->label('Chức vụ')
                            ->options(StaffRoleType::options())
                            ->required(),

                        Select::make('status')
                            ->label('Trạng thái')
                            ->options(EmployeeStatus::options())
                            ->required(),

                        DatePicker::make('joined_at')
                            ->label('Ngày vào làm')
                            ->required(),

                        Select::make('bank_bin')
                            ->label('Ngân hàng')
                            ->required()
                            ->searchable()
                            ->options(BankInfo::options()),


                        TextInput::make('bank_account_number')
                            ->label('Số tài khoản')
                            ->regex('/^[0-9]+$/'),

                        TextInput::make('bank_account_holder')
                            ->label('Chủ tài khoản'),
                    ]),
            ]);
    }
}

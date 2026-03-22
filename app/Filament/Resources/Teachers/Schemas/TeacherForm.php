<?php

namespace App\Filament\Resources\Teachers\Schemas;

use App\Constants\EmployeeStatus;
use App\Constants\Gender;
use App\Constants\GradeLevel;
use App\Core\Helpers\BankInfo;
use App\Filament\Components\CommonForm;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class TeacherForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // THÔNG TIN TÀI KHOẢN
                Section::make('Thông tin tài khoản')
                    ->compact()
                    ->schema([
                        CommonForm::userNameInput(),
                        CommonForm::passwordInput(),
                    ]),

                Tabs::make('Thông tin giáo viên')
                    ->tabs([
                        Tab::make('Thông tin cá nhân')
                            ->icon(Heroicon::User)
                            ->iconPosition(IconPosition::After)
                            ->schema([
                                TextInput::make('full_name')
                                    ->label('Họ và tên')
                                    ->helperText('Nhập họ tên giáo viên, sẽ tự động tạo username')
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Vui lòng nhập họ tên.',
                                    ])
                                    ->maxLength(255)
                                    ->live(debounce: 500)
                                    ->afterStateUpdated(function (Get $get, Set $set, ?string $state, $livewire) {
                                        if ($livewire instanceof EditRecord) {
                                            return;
                                        }
                                        $base = 'gv_' . Str::slug($state, '');
                                        $set('user_name', $base);
                                    }),

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
                                        'required' => 'Vui lòng nhập số điện thoại.',
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
                                        'required' => 'Vui lòng nhập email.',
                                        'email' => 'Email không hợp lệ.',
                                        'unique' => 'Email đã tồn tại.',
                                    ]),

                                TextInput::make('address')
                                    ->label('Địa chỉ')
                                    ->required()
                                    ->columnSpanFull()
                                    ->validationMessages([
                                        'required' => 'Vui lòng nhập địa chỉ.',
                                    ]),

                                Select::make('status')
                                    ->label('Trạng thái')
                                    ->native(false)
                                    ->required()
                                    ->options(EmployeeStatus::options())
                                    ->default(EmployeeStatus::Active)
                                    ->validationMessages([
                                        'required' => 'Vui lòng chọn trạng thái.',
                                    ]),
                                DatePicker::make('joined_at')
                                    ->label('Ngày vào làm')
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Vui lòng chọn ngày vào làm.',
                                    ])
                                    ->displayFormat('d/m/Y'),
                            ]),

                        Tab::make('Thông tin ngân hàng')
                            ->icon(Heroicon::Banknotes)
                            ->iconPosition(IconPosition::After)
                            ->schema([
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
                            ]),
                    ]),
            ]);
    }
}

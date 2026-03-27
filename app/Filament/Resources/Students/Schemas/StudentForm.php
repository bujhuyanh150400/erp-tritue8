<?php

namespace App\Filament\Resources\Students\Schemas;

use App\Constants\Gender;
use App\Constants\GradeLevel;
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
use function Pest\Laravel\instance;

class StudentForm
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

                Tabs::make('Thông tin học sinh')
                    ->tabs([
                        Tab::make('Thông tin cá nhân')
                            ->icon(Heroicon::User)
                            ->iconPosition(IconPosition::After)
                            ->schema([
                                TextInput::make('full_name')
                                    ->label('Họ và tên')
                                    ->helperText('Hãy nhập đầy đủ họ và tên của học sinh, ví dụ: Nguyễn Văn A, khi nhập tên có thể tự động tạo tên đăng nhập')
                                    ->required()
                                    ->maxLength(255)
                                    // Bật live để khi gõ xong tên sẽ tự tạo username
                                    ->live(debounce: 500)
                                    ->afterStateUpdated(function (Get $get, Set $set, ?string $state, $livewire) {
                                        if ($livewire instanceof EditRecord) {
                                            return;
                                        }
                                        $slug = Str::slug($state, '');
                                        $set('user_name', 'hs_' . $slug);
                                    })
                                    ->validationMessages([
                                        'required' => 'Vui lòng nhập họ và tên'
                                    ]),
                                DatePicker::make('dob')
                                    ->label('Ngày sinh')
                                    ->required()
                                    ->displayFormat('d/m/Y')
                                    ->validationMessages([
                                        'required' => 'Vui lòng nhập ngày sinh'
                                    ]),


                                Select::make('gender')
                                    ->label('Giới tính')
                                    ->required()
                                    ->searchable()
                                    ->options(Gender::options())
                                    ->validationMessages([
                                        'required' => 'Vui lòng chọn giới tính'
                                    ]),

                                Select::make('grade_level')
                                    ->label('Khối')
                                    ->required()
                                    ->searchable()
                                    ->options(GradeLevel::options())
                                    ->validationMessages([
                                        'required' => 'Vui lòng chọn khối'
                                    ]),

                            ]),
                        Tab::make('Thông tin phụ huynh')
                            ->icon(Heroicon::OutlinedPhone)
                            ->iconPosition(IconPosition::After)
                            ->schema([
                                TextInput::make('parent_name')
                                    ->label('Tên phụ huynh')
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Vui lòng nhập tên phụ huynh'
                                    ]),

                                TextInput::make('parent_phone')
                                    ->label('SĐT Phụ huynh')
                                    ->tel()
                                    ->required()
                                    ->regex('/^[0-9]{10,11}$/')
                                    ->validationMessages([
                                        'regex' => 'Số điện thoại phải có 10-11 chữ số.',
                                        'required' => 'Vui lòng nhập số điện thoại'
                                    ]),

                                TextInput::make('address')
                                    ->label('Địa chỉ')
                                    ->required()
                                    ->columnSpanFull()
                                    ->validationMessages([
                                        'required' => 'Vui lòng nhập địa chỉ'
                                    ]),


                                Textarea::make('note')
                                    ->label('Ghi chú')
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}

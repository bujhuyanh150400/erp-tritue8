<?php

namespace App\Filament\Resources\Students\Schemas;

use App\Constants\Gender;
use App\Constants\GradeLevel;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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
use function Pest\Laravel\instance;

class StudentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // CỘT TRÁI: THÔNG TIN TÀI KHOẢN (Tạo thành 1 block riêng cho đẹp)
                Section::make('Thông tin tài khoản')
                    ->compact()
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
                            }),

                        TextInput::make('user_name')
                            ->label('Tên đăng nhập')
                            ->required()
                            ->disabled(fn($livewire) => $livewire instanceof EditRecord)
                            ->unique(
                                table: 'users',
                                column: 'username',
                                // Bỏ qua bản ghi User hiện tại (nếu đang ở trang Edit) thay vì Student
                                ignorable: fn ($record) => $record?->user
                            )
                            ->validationMessages([
                                'unique' => 'Tên đăng nhập này đã tồn tại, vui lòng thêm số (vd: hs_nguyenvana1)',
                            ]),

                        TextInput::make('password')
                            ->label(fn ($livewire) => $livewire instanceof EditRecord ? 'Đổi mật khẩu' : 'Mật khẩu')
                            ->helperText(fn ($livewire) => $livewire instanceof EditRecord ? 'Để trống nếu không muốn đổi' : 'Mật khẩu phải có ít nhất 8 ký tự, bao gồm chữ hoa, chữ thường, số và ký tự đặc biệt.')
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
                                'mixed' => 'Mật khẩu phải bao gồm cả chữ hoa và chữ thường.',
                                'letters' => 'Mật khẩu phải chứa ít nhất một chữ cái.',
                            ])
                            // Nút Random Password
                            ->suffixAction(
                                Action::make('generate_password')
                                    ->icon(Heroicon::ArrowPath)
                                    ->tooltip('Tạo mật khẩu ngẫu nhiên')
                                    ->action(function (Set $set) {
                                        $set('password', Str::password(length: 8, letters: true, numbers: true, symbols: false));
                                    })
                            ),
                    ]),

                // CỘT PHẢI: THÔNG TIN HỌC SINH
                Section::make('Thông tin học sinh')
                    ->compact()
                    ->schema([
                        DatePicker::make('dob')
                            ->label('Ngày sinh')
                            ->required()
                            ->displayFormat('d/m/Y'),

                        Select::make('gender')
                            ->label('Giới tính')
                            ->required()
                            ->searchable()
                            ->options(Gender::options()), // Hoặc dùng Enum: Gender::class

                        Select::make('grade_level')
                            ->label('Khối')
                            ->required()
                            ->searchable()
                            ->options(GradeLevel::options()),

                        TextInput::make('parent_name')
                            ->label('Tên phụ huynh')
                            ->required(),

                        TextInput::make('parent_phone')
                            ->label('SĐT Phụ huynh')
                            ->tel()
                            ->required()
                            ->regex('/^[0-9]{10,11}$/')
                            ->validationMessages([
                                'regex' => 'Số điện thoại phải có 10-11 chữ số.',
                            ]),

                        TextInput::make('address')
                            ->label('Địa chỉ')
                            ->required()
                            ->columnSpanFull(),

                        Textarea::make('note')
                            ->label('Ghi chú')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}

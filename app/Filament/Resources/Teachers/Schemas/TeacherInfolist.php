<?php


namespace App\Filament\Resources\Teachers\Schemas;

use App\Constants\ClassStatus;
use App\Core\Helpers\BankInfo;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use App\Constants\EmployeeStatus;
use Filament\Infolists\Components\RepeatableEntry;

class TeacherInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Chi tiết giáo viên')
                    ->columnSpanFull()
                    ->tabs([
                        // ==========================================
                        // TAB 1: THÔNG TIN CÁ NHÂN
                        // ==========================================
                        Tabs\Tab::make('Thông tin cá nhân')
                            ->icon('heroicon-m-user')
                            ->schema([
                                Grid::make(3)->schema([
                                    Section::make('Tài khoản')
                                        ->columnSpan(1)
                                        ->schema([
                                            TextEntry::make('user.username')
                                                ->label('Tên đăng nhập'),

                                            IconEntry::make('user.is_active')
                                                ->label('Trạng thái')
                                                ->boolean(),

                                            TextEntry::make('user.last_login_at')
                                                ->label('Đăng nhập lần cuối')
                                                ->dateTime('d/m/Y H:i'),
                                        ]),

                                    Section::make('Hồ sơ giáo viên')
                                        ->columnSpan(2)
                                        ->columns(2)
                                        ->schema([
                                            TextEntry::make('full_name')
                                                ->label('Họ và tên')
                                                ->weight('bold'),

                                            TextEntry::make('phone')
                                                ->label('Số điện thoại')
                                                ->icon(Heroicon::Phone),

                                            TextEntry::make('email')
                                                ->label('Email')
                                                ->icon(Heroicon::Envelope),

                                            TextEntry::make('joined_at')
                                                ->label('Ngày vào làm')
                                                ->icon(Heroicon::Calendar)
                                                ->date('d/m/Y'),

                                            TextEntry::make('status')
                                                ->label('Trạng thái')
                                                ->badge()
                                                ->formatStateUsing(fn ($state) => $state->label()),

                                            TextEntry::make('address')
                                                ->label('Địa chỉ')
                                                ->icon(Heroicon::MapPin)
                                                ->columnSpanFull(),
                                        ]),
                                ])
                            ]),

                        // ==========================================
                        // TAB 2: THÔNG TIN NGÂN HÀNG
                        // ==========================================
                        Tabs\Tab::make('Thông tin ngân hàng')
                            ->icon('heroicon-m-credit-card')
                            ->schema([
                                Section::make('Tài khoản ngân hàng')
                                    ->schema([
                                        Grid::make([
                                            'md' => 3,
                                        ])->schema([

                                            TextEntry::make('bank_bin')
                                                ->label('Ngân hàng')
                                                ->badge()
                                                ->color('warning')
                                                ->icon('heroicon-m-building-library')
                                                ->formatStateUsing(function ($state) {
                                                    $bank = BankInfo::getBankByBin($state);
                                                    return $bank['short_name'] ?? '-';
                                                })
                                                ->tooltip(function ($state) {
                                                    $bank = BankInfo::getBankByBin($state);
                                                    return $bank['name'] ?? '';
                                                }),

                                            TextEntry::make('bank_account_holder')
                                                ->label('Chủ tài khoản')
                                                ->weight('bold')
                                                ->formatStateUsing(fn ($state) => strtoupper($state))
                                                ->placeholder('-'),

                                            TextEntry::make('bank_account_number')
                                                ->label('Số tài khoản')
                                                ->badge()
                                                ->color('gray')
                                                ->copyable()
                                                ->copyMessage('Đã copy')
                                                ->formatStateUsing(fn ($state) =>
                                                chunk_split($state, 4, ' ')
                                                )
                                                ->placeholder('-'),

                                        ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tabs\Tab::make('Lớp đang dạy')
                            ->icon('heroicon-m-academic-cap')
                            ->schema([
                                Section::make('Danh sách lớp')
                                    ->schema([

                                        RepeatableEntry::make('classes')
                                            ->label('Lớp đang dạy')
                                            ->state(fn ($record) =>
                                            $record->classes()
                                                ->with('subject')
                                                ->where('status', ClassStatus::Active->value)
                                                ->get()
                                            )
                                            ->schema([
                                                TextEntry::make('name')
                                                    ->label('Lớp')
                                                    ->hiddenLabel()
                                                    ->badge()
                                                    ->color('primary')
                                                    ->weight('bold'),

                                                TextEntry::make('subject.name')
                                                    ->label('Môn')
                                                    ->hiddenLabel()
                                                    ->badge()
                                                    ->color('black'),

                                                TextEntry::make('status')
                                                    ->label('Trạng thái')
                                                    ->hiddenLabel()
                                                    ->badge()
                                                    ->color(fn ($state) => match ($state) {
                                                        ClassStatus::Active => 'success',
                                                        ClassStatus::Suspended => 'warning',
                                                        ClassStatus::Ended => 'gray',
                                                    })
                                                    ->formatStateUsing(fn ($state) => $state->label())
                                            ])
                                            ->columns(3)
                                            ->columnSpanFull(),

                                    ]),
                            ]),
                        // ==========================================
                        // TAB 3: TỔNG QUAN
                        // ==========================================
                        Tabs\Tab::make('Tổng quan')
                            ->icon('heroicon-m-chart-bar')
                            ->schema([
                                Section::make('Thông tin hệ thống')
                                    ->schema([
                                        TextEntry::make('created_at')
                                            ->label('Ngày tạo')
                                            ->dateTime('d/m/Y H:i'),

                                        TextEntry::make('updated_at')
                                            ->label('Cập nhật lần cuối')
                                            ->dateTime('d/m/Y H:i'),
                                    ]),
                            ]),
                    ])
            ]);
    }
}

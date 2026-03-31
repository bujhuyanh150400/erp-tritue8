<?php

namespace App\Filament\Resources\Teachers\Schemas;

use App\Constants\ClassStatus;
use App\Core\Helpers\BankInfo;
use App\Filament\Resources\Teachers\Components\TeacherKpiOverview;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use App\Constants\EmployeeStatus;

class TeacherInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Chi tiết giáo viên')
                    ->columnSpanFull()
                    ->contained(false)
                    ->persistTabInQueryString()
                    ->tabs([
                        // TAB 1: THÔNG TIN CÁ NHÂN
                        Tabs\Tab::make('Thông tin cá nhân')
                            ->icon('heroicon-m-user')
                            ->schema([
                                Grid::make(3)->schema([

                                    // CỘT 1: TÀI KHOẢN (1/3)
                                    Section::make('Tài khoản')
                                        ->columnSpan(1)
                                        ->compact()
                                        ->schema([
                                            TextEntry::make('user.username')
                                                ->label('Tên đăng nhập')
                                                ->icon('heroicon-m-at-symbol'),

                                            IconEntry::make('user.is_active')
                                                ->label('Trạng thái')
                                                ->boolean(),
                                        ]),

                                    // CỘT 2: HỒ SƠ GIÁO VIÊN (2/3)
                                    Section::make('Hồ sơ giáo viên')
                                        ->columnSpan(2)
                                        ->compact()
                                        ->columns(2) // Chia đôi thông tin cá nhân bên trên
                                        ->schema([
                                            TextEntry::make('full_name')
                                                ->label('Họ và tên')
                                                ->weight('bold')
                                                ->icon(Heroicon::User)
                                                ->color('primary'),

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
                                                ->color(fn ($state) => match ($state) {
                                                    EmployeeStatus::Active => 'success',   // xanh
                                                    EmployeeStatus::Inactive => 'danger',     // đỏ
                                                    default => 'success',
                                                })
                                                ->icon(Heroicon::CheckCircle)
                                                ->formatStateUsing(fn ($state) => $state->label()),

                                            TextEntry::make('address')
                                                ->label('Địa chỉ')
                                                ->icon('heroicon-m-map-pin'),

                                            // ==========================================
                                            // Ô BOX NGÂN HÀNG (Nằm trong Hồ sơ giáo viên)
                                            // ==========================================
                                            Grid::make(2)
                                                ->columnSpanFull()
                                                ->schema([
                                                    TextEntry::make('bank_bin')
                                                        ->label('Ngân hàng')
                                                        ->icon(Heroicon::Banknotes)
                                                        ->badge()
                                                        ->color('warning')
                                                        ->formatStateUsing(fn ($state) => BankInfo::getBankByBin($state)['short_name'] ?? '-'),

                                                    TextEntry::make('bank_account_holder')
                                                        ->label('Chủ tài khoản')
                                                        ->weight('bold')
                                                        ->icon(Heroicon::User)
                                                        ->formatStateUsing(fn ($state) => strtoupper($state)),

                                                    TextEntry::make('bank_account_number')
                                                        ->label('Số tài khoản')
                                                        ->copyable()
                                                        ->fontFamily('mono')
                                                        ->columnSpanFull()
                                                        ->icon(Heroicon::CreditCard)
                                                        ->badge()
                                                        ->formatStateUsing(fn ($state) => chunk_split($state, 4, ' ')),
                                                ]),
                                        ]),
                                ]),
                            ]),

                        // TAB 2: Lớp đang dạy
                        Tabs\Tab::make('Lớp đang dạy')
                            ->icon('heroicon-m-academic-cap')
                            ->lazy()
                            ->schema([
                                RepeatableEntry::make('classes')
                                    ->hiddenLabel()
                                    ->state(fn ($record) => $record->classes()
                                        ->with('subject')
                                        ->where('status', ClassStatus::Active->value)
                                        ->get()
                                    )
                                    ->grid(3) // Chia 3 cột ngang
                                    ->schema([
                                        // Dùng Section nhỏ hoặc bọc Grid để tạo khung cho từng lớp
                                        Grid::make(1)
                                            ->schema([
                                                TextEntry::make('name')
                                                    ->label('Tên lớp') // Hiện nhãn để biết đây là tên
                                                    ->weight('bold')
                                                    ->color('primary')
                                                    ->icon('heroicon-m-hashtag'),

                                                TextEntry::make('subject.name')
                                                    ->label('Môn học')
                                                    ->badge()
                                                    ->color('gray'),

                                                TextEntry::make('status')
                                                    ->label('Trạng thái')
                                                    ->badge()
                                                    ->color(fn ($state) => match ($state) {
                                                        ClassStatus::Active => 'success',
                                                        ClassStatus::Suspended => 'warning',
                                                        ClassStatus::Ended => 'gray',
                                                        default => 'gray',
                                                    })
                                                    ->formatStateUsing(fn ($state) => $state->label()),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        // TAB 3: HIỆU SUẤT (KPI)
                        Tabs\Tab::make('Hiệu suất (KPI)')
                            ->icon('heroicon-m-presentation-chart-line')
                            ->schema([
                                Livewire::make(TeacherKpiOverview::class)->lazy(),
                            ]),
                    ]),
            ]);
    }
}

<?php

namespace App\Filament\Resources\Staff\Schemas;

use App\Constants\StaffRoleType;
use App\Constants\EmployeeStatus;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;

class StaffInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Chi tiết nhân viên')
                    ->columnSpanFull()
                    ->tabs([

                        // ==========================================
                        // TAB 1: THÔNG TIN CÁ NHÂN
                        // ==========================================
                        Tabs\Tab::make('Thông tin cá nhân')
                            ->icon('heroicon-m-user')
                            ->schema([
                                Grid::make(3)->schema([

                                    // ===== TÀI KHOẢN =====
                                    Section::make('Tài khoản')
                                        ->columnSpan(1)
                                        ->schema([
                                            TextEntry::make('user.username')
                                                ->label('Tên đăng nhập')
                                                ->wrap(false),

                                            IconEntry::make('user.is_active')
                                                ->label('Trạng thái')
                                                ->boolean(),

                                        ]),

                                    // ===== NHÂN VIÊN =====
                                    Section::make('Nhân viên')
                                        ->columnSpan(2)
                                        ->columns(2)
                                        ->schema([

                                            TextEntry::make('full_name')
                                                ->label('Họ tên')
                                                ->weight('bold'),

                                            TextEntry::make('phone')
                                                ->label('SĐT')
                                                ->icon(Heroicon::Phone)
                                                ->wrap(false),

                                            TextEntry::make('role_type')
                                                ->label('Chức vụ')
                                                ->badge()
                                                ->formatStateUsing(fn ($state) => $state?->label()),

                                            TextEntry::make('status')
                                                ->label('Trạng thái')
                                                ->badge()
                                                ->formatStateUsing(fn ($state) => $state?->label()),

                                            TextEntry::make('joined_at')
                                                ->label('Ngày vào làm')
                                                ->icon(Heroicon::Calendar)
                                                ->date('d/m/Y'),

                                        ]),
                                ])
                            ]),

                        // ==========================================
                        // TAB 2: NGÂN HÀNG
                        // ==========================================
                        Tabs\Tab::make('Ngân hàng')
                            ->icon('heroicon-m-banknotes')
                            ->schema([
                                Section::make('Thông tin ngân hàng')
                                    ->columns(2)
                                    ->schema([
                                        TextEntry::make('bank_name')
                                            ->label('Ngân hàng'),

                                        TextEntry::make('bank_account_number')
                                            ->label('Số tài khoản'),

                                        TextEntry::make('bank_account_holder')
                                            ->label('Chủ tài khoản')
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        // ==========================================
                        // TAB 3: CA LÀM VIỆC (placeholder)
                        // ==========================================
                        Tabs\Tab::make('Ca làm việc')
                            ->icon('heroicon-m-calendar-days')
                            ->schema([
                                Section::make('Danh sách ca làm')
                                    ->schema([
                                        TextEntry::make('note')
                                            ->label('Chưa có dữ liệu')
                                            ->state('Chưa tích hợp module ca làm'),
                                    ]),
                            ]),

                        // ==========================================
                        // TAB 4: LƯƠNG
                        // ==========================================
                        Tabs\Tab::make('Lương')
                            ->icon('heroicon-m-currency-dollar')
                            ->schema([
                                Section::make('Thông tin lương')
                                    ->schema([
                                        TextEntry::make('salary_info')
                                            ->label('Thông tin')
                                            ->size(TextSize::Large)
                                            ->state('Xem tại module tài chính'),
                                    ]),
                            ]),
                    ])
            ]);
    }
}

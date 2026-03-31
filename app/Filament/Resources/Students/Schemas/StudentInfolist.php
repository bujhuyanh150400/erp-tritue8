<?php

namespace App\Filament\Resources\Students\Schemas;

use App\Constants\Gender;
use App\Constants\GradeLevel;
use App\Filament\Resources\Students\Components\StudentMonthlyReport;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;

class StudentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Chi tiết học sinh')
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
                                            TextEntry::make('user.username')->label('Tên đăng nhập'),
                                            IconEntry::make('user.is_active')
                                                ->label('Trạng thái')
                                                ->boolean(),
                                        ]),

                                    Section::make('Hồ sơ học sinh')
                                        ->columnSpan(2)
                                        ->columns(2)
                                        ->schema([
                                            TextEntry::make('full_name')
                                                ->label('Họ và tên')
                                                ->weight('bold'),
                                            TextEntry::make('dob')
                                                ->label('Ngày sinh')
                                                ->icon(Heroicon::Calendar)
                                                ->date('d/m/Y'),
                                            TextEntry::make('gender')
                                                ->label('Giới tính')
                                                ->badge()
                                                ->formatStateUsing(fn (Gender $state): string => $state->label()),
                                            TextEntry::make('grade_level')
                                                ->label('Khối')
                                                ->badge()
                                                ->formatStateUsing(fn (GradeLevel $state): string => $state->label()),
                                            TextEntry::make('parent_name')
                                                ->icon(Heroicon::User)
                                                ->label('Tên Phụ huynh'),
                                            TextEntry::make('parent_phone')
                                                ->icon(Heroicon::Phone)
                                                ->label('SĐT Phụ huynh'),
                                            TextEntry::make('address')
                                                ->icon(Heroicon::MapPin)
                                                ->label('Địa chỉ')
                                                ->columnSpanFull(),
                                            TextEntry::make('note')
                                                ->icon(Heroicon::ChatBubbleOvalLeft)
                                                ->label('Ghi chú')
                                                ->columnSpanFull(),
                                        ]),
                                ])
                            ]),

                        // ==========================================
                        // TAB 2: BÁO CÁO THEO MÔN (Động theo tháng)
                        // ==========================================
                        Tabs\Tab::make('Báo cáo học tập')
                            ->icon('heroicon-m-chart-bar')
                            ->schema([
                                Livewire::make(StudentMonthlyReport::class)
                            ]),

                        // ==========================================
                        // TAB 3: SAO THƯỞNG & LỊCH SỬ
                        // ==========================================
                        Tabs\Tab::make('Sao & Thưởng')
                            ->icon('heroicon-m-star')
                            ->schema([
                                Section::make('Tổng quan sao')
                                    ->schema([
                                        TextEntry::make('total_stars')
                                            ->label('Tổng sao hiện tại')
                                            ->size(TextSize::Large)
                                            ->color('warning')
                                            ->icon(Heroicon::Star)
                                            // Tự động tính tổng điểm từ bảng reward_points
                                            ->state(fn ($record) => $record->rewardPoints()->sum('amount')),
                                    ]),
                            ]),
                    ])
            ]);
    }
}

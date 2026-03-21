<?php


namespace App\Filament\Resources\Teachers\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;
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
                                        Grid::make(2)->schema([
                                            TextEntry::make('bank_bin')
                                                ->label('Mã ngân hàng'),

                                            TextEntry::make('bank_account_holder')
                                                ->label('Chủ tài khoản'),

                                            TextEntry::make('bank_account_number')
                                                ->label('Số tài khoản')
                                                ->copyable(),
                                        ]),
                                    ]),
                            ]),

                        Tabs\Tab::make('Lớp đang dạy')
                            ->icon('heroicon-m-academic-cap')
                            ->schema([
                                Section::make('Danh sách lớp')
                                    ->schema([
                                        TextEntry::make('classes')
                                            ->label('')
                                            ->state(function ($record) {
                                                return \DB::table('classes')
                                                    ->join('subjects', 'classes.subject_id', '=', 'subjects.id')
                                                    ->where('classes.teacher_id', $record->id)
                                                    ->where('classes.status', 1)
                                                    ->selectRaw("classes.name || ' - ' || subjects.name as text")
                                                    ->pluck('text')
                                                    ->implode("\n");
                                            })
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

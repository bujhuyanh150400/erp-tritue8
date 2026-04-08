<?php

namespace App\Filament\Resources\Classes\Actions;

use App\Constants\FeeType;
use App\Filament\Components\CustomSelect;
use App\Models\ScheduleInstance;
use App\Services\ClassScheduleService;
use App\Services\RoomService;
use App\Services\TeacherService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class EditScheduleInstanceAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Chỉnh sửa')
            ->color('warning')
            ->visible(function (ScheduleInstance $record) {
                return $record->canEditingInstance();
            })
            ->icon(Heroicon::PencilSquare)
            ->fillForm(function (ScheduleInstance $record) {
                return [
                    'date' => $record->date,
                    'start_time' => $record->start_time,
                    'end_time' => $record->end_time,
                    'room_id' => $record->room_id,
                    'teacher_id' => $record->teacher_id,
                    'fee_type' => $record->fee_type,
                    'custom_fee_per_session' => $record->custom_fee_per_session,
                    'custom_salary' => $record->custom_salary,
                ];
            })
            ->schema(fn(ScheduleInstance $record) => [
                Section::make('Thời gian & Địa điểm')
                    ->compact()
                    ->schema([
                        DatePicker::make('date')
                            ->label('Ngày học')
                            ->displayFormat('d/m/Y')
                            ->default(now())
                            ->native(false)
                            ->rules([
                                Rule::date()->afterOrEqual($record->date),
                            ])
                            ->validationMessages([
                                'after_or_equal' => 'Ngày học bù phải sau ngày báo nghỉ (' . Carbon::parse($record->date)->format('d/m/Y') . ').',
                            ])
                            ->required(),

                        Grid::make(2)->schema([
                            TimePicker::make('start_time')
                                ->label('Bắt đầu')
                                ->seconds(false)
                                ->native(false)
                                ->required()
                                // Ràng buộc để end_time có thể tham chiếu đến field này trong Closure
                                ->live(),

                            TimePicker::make('end_time')
                                ->label('Kết thúc')
                                ->seconds(false)
                                ->native(false)
                                ->required()
                                // 2. Validate: end_time phải lớn hơn start_time
                                ->rules([
                                    function (Get $get) {
                                        return function (string $attribute, $value, \Closure $fail) use ($get) {
                                            $startTime = $get('start_time');

                                            // Chỉ validate nếu cả 2 trường đều đã có giá trị
                                            if ($startTime && $value) {
                                                $start = Carbon::parse($startTime);
                                                $end = Carbon::parse($value);

                                                if ($end->lessThanOrEqualTo($start)) {
                                                    $fail('Giờ kết thúc phải lớn hơn giờ bắt đầu.');
                                                }
                                            }
                                        };
                                    },
                                ]),
                        ])->columnSpan(1),

                        CustomSelect::make('room_id')
                            ->label('Phòng học')
                            ->helperText("(Bỏ trống để dùng phòng mặc định của lớp)")
                            ->getOptionSelectService(RoomService::class),

                        CustomSelect::make('teacher_id')
                            ->label('Giáo viên')
                            ->helperText("(Bỏ trống để dùng GV mặc định của lớp)")
                            ->getOptionSelectService(TeacherService::class),
                    ]),
                Section::make('Tài chính')
                    ->compact()
                    ->schema([
                        Radio::make('fee_type')
                            ->label('Học phí học sinh')
                            ->options(FeeType::class)
                            ->default(FeeType::Normal)
                            ->inline()
                            ->live(),

                        TextInput::make('custom_fee_per_session')
                            ->label('Học phí tùy chỉnh (VNĐ)')
                            ->numeric()
                            ->required()
                            ->visible(fn(Get $get) => $get('fee_type') == FeeType::Custom),

                        TextInput::make('custom_salary')
                            ->label('Lương GV ca này (VNĐ)')
                            ->helperText('Để trống sẽ tính theo lương chuẩn của giáo viên.')
                            ->numeric(),

                    ]),
            ])
            ->action(function (array $data, ScheduleInstance $record, ClassScheduleService $scheduleService) {
                $result = $scheduleService->updateInstance($record, $data);
                if ($result->isError()) {
                    Notification::make()
                        ->title('Cập nhật lịch học thất bại')
                        ->body($result->getMessage())
                        ->danger()
                        ->send();
                    throw new Halt();
                }
                Notification::make()->success()->title('Cập nhật lịch học thành công')->send();
            });
    }
}

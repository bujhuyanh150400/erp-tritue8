<?php

namespace App\Filament\Resources\AttendanceSessions\Traits;

use App\Constants\AttendanceSessionStatus;
use App\Constants\AttendanceStatus;
use App\Services\AttendanceService;
use App\Services\RewardRedemptionService;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Livewire\Attributes\On;

/**
 * Trait chứa các action chung để cập nhật dữ liệu trên UI trong bảng điểm danh học sinh
 */
trait AttendanceStudentTableActions
{
    /**
     * Danh sách học sinh đã được điểm danh
     * @var array
     */
    public array $attendanceList = [];

    #[On('refresh-table-ui')]
    public function refreshTableUi(): void
    {
        // Không làm gì cả, chỉ để Livewire chạy lại lifecycle
    }

    /**
     * Cập nhật dữ liệu trên UI của một dòng học sinh
     * @param int $studentId
     * @param array $newData
     * @param bool $shouldRefresh
     * @return void
     */
    protected function updateStudentRowOnUI(int $studentId, array $newData, bool $shouldRefresh = true): void
    {
        if (isset($this->attendanceList[$studentId])) {
            foreach ($newData as $key => $value) {
                $this->attendanceList[$studentId][$key] = $value;
            }

            if ($shouldRefresh) {
                $this->dispatch('refresh-table-ui');
            }
        }
    }

    /**
     * Lấy dữ liệu dòng học sinh theo ID
     * @param int $studentId
     * @return array
     */
    protected function getStudentRow(int $studentId): array
    {
        return $this->attendanceList[$studentId] ?? [];
    }

    /**
     * Cập nhật dữ liệu điểm danh của một học sinh
     * @param AttendanceService $service
     * @param int $studentId
     * @param AttendanceStatus $status
     * @param array $data
     * @param bool $shouldRefresh
     * @return void
     * @throws Halt
     */
    protected function updateAttendanceDataForStudentByStatus(
        AttendanceService $service,
        int               $studentId,
        AttendanceStatus  $status,
        array             $data = [],
        bool              $shouldRefresh = true, // Thêm flag này
    ): void
    {
        $result = $service->markStudentAttendance(
            session: $this->record,
            studentId: $studentId,
            status: $status,
            data: $data
        );

        if ($result->isSuccess()) {
            // Logic switch giữ nguyên, chỉ truyền thêm $shouldRefresh vào hàm UI
            $payload = match ($status) {
                AttendanceStatus::Present => [
                    'attendance_status' => AttendanceStatus::Present,
                    'is_fee_counted' => true,
                    'check_in_time' => Carbon::parse($result->getData()->check_in_time)->format('H:i'),
                    'reason_absent' => null,
                ],
                AttendanceStatus::Late => [
                    'attendance_status' => AttendanceStatus::Late,
                    'is_fee_counted' => true, // Thường đi muộn vẫn tính phí, bạn check lại nhé
                    'check_in_time' => $result->getData()->check_in_time,
                    'reason_absent' => null,
                ],
                AttendanceStatus::AbsentExcused => [
                    'attendance_status' => AttendanceStatus::AbsentExcused,
                    'is_fee_counted' => false,
                    'check_in_time' => null,
                    'reason_absent' => $result->getData()->reason_absent,
                    'attendance_scores' => [],
                ],
                AttendanceStatus::Absent => [
                    'attendance_status' => AttendanceStatus::Absent,
                    'is_fee_counted' => false,
                    'check_in_time' => null,
                    'reason_absent' => null,
                    'attendance_scores' => [],
                ],
                default => [],
            };

            $this->updateStudentRowOnUI($studentId, $payload, $shouldRefresh);
        } else {
            // Nếu là Bulk Action, có thể bạn không muốn throw Halt để tránh dừng cả vòng lặp
            Notification::make()->danger()->title('Lỗi')->body($result->getMessage())->send();
            throw new Halt();
        }
    }

    /**
     * Tạo các action điểm danh cho một học sinh
     */
    protected function actionAttendance(bool $isBulk = false)
    {
        // Xác định class và group class dựa trên tham số
        $actionClass = $isBulk ? BulkAction::class : Action::class;

        // Định nghĩa danh sách các nút điểm danh
        $actions = [
            // 1. CÓ MẶT
            $actionClass::make('mark_present')
                ->label('Có mặt')
                ->icon(Heroicon::CheckCircle)
                ->color('success')
                ->when($isBulk, fn($action) => $action->deselectRecordsAfterCompletion())
                ->action(
                    $isBulk
                        ? fn($records, AttendanceService $service) => $this->handleHybridAction(true, $records, $service, AttendanceStatus::Present)
                        : fn(array $record, AttendanceService $service) => $this->handleHybridAction(false, $record, $service, AttendanceStatus::Present)
                ),

            // 2. ĐI MUỘN
            $actionClass::make('mark_late')
                ->label('Đi muộn')
                ->icon(Heroicon::Clock)
                ->color('warning')
                ->form([
                    TimePicker::make('check_in_time')
                        ->label('Giờ đến lớp')
                        ->required()
                        ->format('H:i')->native(false)
                        ->default(Carbon::now()->format('H:i')),
                ])
                ->when($isBulk, fn($action) => $action->deselectRecordsAfterCompletion())
                ->action(
                    $isBulk
                        ? fn($records, array $data, AttendanceService $service) => $this->handleHybridAction(true, $records, $service, AttendanceStatus::Late, $data)
                        : fn(array $record, array $data, AttendanceService $service) => $this->handleHybridAction(false, $record, $service, AttendanceStatus::Late, $data)
                ),

            // 3. VẮNG CÓ PHÉP
            $actionClass::make('mark_excused')
                ->label('Vắng có phép')
                ->icon(Heroicon::DocumentText)
                ->color('info')
                ->form([
                    Textarea::make('reason_absent')->label('Lý do')->required(),
                ])
                ->when($isBulk, fn($action) => $action->deselectRecordsAfterCompletion())
                ->action(
                    $isBulk
                        ? fn($records, array $data, AttendanceService $service) => $this->handleHybridAction(true, $records, $service, AttendanceStatus::AbsentExcused, $data)
                        : fn(array $record, array $data, AttendanceService $service) => $this->handleHybridAction(false, $record, $service, AttendanceStatus::AbsentExcused, $data)
                ),

            // 4. VẮNG KHÔNG PHÉP
            $actionClass::make('mark_unexcused')
                ->label('Vắng không phép')
                ->icon(Heroicon::XCircle)
                ->color('danger')
                ->when($isBulk, fn($action) => $action->deselectRecordsAfterCompletion())
                ->action(
                    $isBulk
                        ? fn($records, AttendanceService $service) => $this->handleHybridAction(true, $records, $service, AttendanceStatus::Absent)
                        : fn(array $record, AttendanceService $service) => $this->handleHybridAction(false, $record, $service, AttendanceStatus::Absent)
                ),
        ];

        // Trả về group tương ứng
        return $isBulk
            ? BulkActionGroup::make($actions)
                ->visible(fn(): bool => $this->record->isDraft())
                ->label('Điểm danh hàng loạt')
            : ActionGroup::make($actions)
                ->visible(fn(): bool => $this->record->isDraft())
                ->label('Điểm danh')
                ->icon(Heroicon::CheckCircle)
                ->button()
                ->color('success');
    }

    /**
     * Tạo action chấm điểm cho một học sinh
     * @return Action
     */
    protected function actionSaveScore()
    {
        return Action::make('input_scores')
            ->label('Chấm điểm')
            ->color('primary')
            ->icon(Heroicon::OutlinedAcademicCap)
            ->modalHeading("Điểm danh")
            // Chỉ cho phép chấm nếu học sinh CÓ MẶT tại lớp
            ->visible(fn(array $record) => $record['attendance_status'] !== AttendanceStatus::Draft && $this->record->isDraft())
            // Nạp dữ liệu từ mảng scores trên RAM vào Form
            ->fillForm(fn(array $record) => [
                // Nếu scores rỗng, trả về mảng chứa 1 item mặc định để Repeater bắt được
                'attendance_scores' => $record['attendance_scores'],
            ])
            ->schema([
                Repeater::make('attendance_scores')
                    ->hiddenLabel()
                    ->compact()
                    ->schema([

                        Grid::make(3)->schema([
                            TextInput::make('exam_name')
                                ->label('Tên bài')
                                ->required()
                                ->placeholder('BTVN, Kiểm tra miệng...'),
                            TextInput::make('score')
                                ->label('Điểm')
                                ->numeric()
                                ->live(onBlur: true)
                                ->prefix('Điểm số:')
                                ->suffix('/10')
                                ->minValue(0)
                                ->required()
                                ->rules([
                                    fn(Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get) {
                                        if (is_numeric($value) && $value > 10) {
                                            $fail("Điểm số không được lớn hơn thang điểm 10.");
                                        }
                                    },
                                ]),
                            Textarea::make('note')
                                ->label('Ghi chú (nếu có)'),
                        ]),
                    ])
                    ->minItems(1)
                    ->addActionLabel('Thêm đầu điểm mới')
                    ->collapsible()
            ])
            ->action(function (array $data, array $record, AttendanceService $service) {
                // Lưu DB qua Service (Bạn viết hàm lưu tương tự như đã bàn)
                $result = $service->saveStudentScores(
                    session: $this->record,
                    studentId: $record['student_id'],
                    scoresData: $data['attendance_scores'],
                );
                if ($result->isSuccess()) {
                    // Cập nhật lại RAM và UI
                    $this->updateStudentRowOnUI($record['student_id'], [
                        'attendance_scores' => $data['attendance_scores']
                    ]);
                    Notification::make()->title('Đã lưu bảng điểm!')->success()->send();
                } else {
                    Notification::make()->title("Lỗi lưu bảng điểm")
                        ->body($result->getMessage())->danger()->send();
                    throw new Halt();
                }
            });
    }

    /**
     * Hàm phụ trợ xử lý logic lặp cho Bulk hoặc chạy đơn cho Single
     */
    protected function handleHybridAction(bool $isBulk, $recordOrRecords, $service, $status, $data = []): void
    {
        if ($isBulk) {
            // Chế độ hàng loạt: Lặp qua Collection
            foreach ($recordOrRecords as $record) {
                $this->updateAttendanceDataForStudentByStatus($service, $record['student_id'], $status, $data, false);
            }
            // Sau khi lặp xong mới refresh UI 1 lần duy nhất
            $this->dispatch('refresh-table-ui');
            Notification::make()->title('Đã cập nhật hàng loạt')->success()->send();
        } else {
            // Chế độ đơn lẻ
            if (! $isBulk) {
                $studentId = $recordOrRecords['student_id'] ?? null;
                if (! $studentId) {
                    Notification::make()->danger()->title('Không tìm thấy học sinh để cập nhật điểm danh')->send();
                    throw new Halt();
                }

                $this->updateAttendanceDataForStudentByStatus($service, $studentId, $status, $data, true);
            }
        }
    }

    /**
     * Hàm cộng 1 sao cho học sinh
     * @param string $studentId ID học sinh
     * @throws Halt
     */
    public function quickPlusPoints(string $studentId)
    {
        $result = $this->attendanceService->updateStudentRewardPoints(
            session: $this->record,
            studentId: $studentId,
            amount: 1
        );
        if ($result->isError()) {
            Notification::make()
                ->title("Lỗi cập nhật điểm thưởng")
                ->body($result->getMessage())
                ->danger()
                ->send();
            throw new Halt();
        }
        Notification::make()->title("Đã cộng 1 sao")->success()->send();
        $this->updateStudentRowOnUI($studentId, [
            'total_reward_points' => $this->getStudentRow($studentId)['total_reward_points'] + 1
        ]);
    }

    /**
     * Hàm trừ 1 sao cho học sinh
     * @param string $studentId ID học sinh
     * @return void
     * @throws Halt
     */
    public function quickMinusPoints(string $studentId)
    {
        $result = $this->attendanceService->updateStudentRewardPoints(
            session: $this->record,
            studentId: $studentId,
            amount: -1
        );
        if ($result->isError()) {
            Notification::make()
                ->title("Lỗi cập nhật điểm thưởng")
                ->body($result->getMessage())
                ->danger()
                ->send();
            throw new Halt();
        }
        Notification::make()->title("Đã trừ 1 sao")->success()->send();
        $this->updateStudentRowOnUI($studentId, [
            'total_reward_points' => $this->getStudentRow($studentId)['total_reward_points'] - 1
        ]);
    }

    /**
     * Hàm tạo ActionGroup cho thưởng tùy chỉnh
     */
    protected function actionCustomRewards(): Action
    {
        return Action::make('custom_reward')
            ->label('Thưởng tùy chỉnh...')
            ->icon(Heroicon::Gift)
            ->schema([
                TextInput::make('amount')
                    ->label('Số lượng sao')
                    ->numeric()
                    ->default(5)
                    ->required(),
                TextInput::make('reason')
                    ->label('Lý do thưởng')
                    ->placeholder('VD: Phát biểu xuất sắc, Làm bài tập đầy đủ...'),
            ])
            ->action(function (array $data, array $record, AttendanceService $service) {
                $amount = (int) $data['amount'];
                $newPoints = $record['total_reward_points'] + $amount;

                $result = $service->updateStudentRewardPoints($this->record, $record['student_id'], $amount, $data['reason']);
                if ($result->isError()) {
                    Notification::make()->title("Lỗi cập nhật điểm thưởng")
                        ->body($result->getMessage())->danger()->send();
                    throw new Halt();
                }
                $this->updateStudentRowOnUI($record['student_id'], [
                    'total_reward_points' => $newPoints
                ]);
                Notification::make()->title("Đã thưởng {$amount} sao cho học sinh")->success()->send();
            });
    }

    /**
     * Hàm tạo Action cho đổi thưởng
     * @return Action
     */
    protected function actionRedeemRewards(): Action
    {
     return  Action::make('redeem_reward')
         ->label('Đổi thưởng')
         ->icon(Heroicon::Gift)
         ->color('info')
         ->modalHeading(fn (array $record) => 'Đổi thưởng cho ' . $record['student_name'])
         ->modalDescription(fn (array $record) => 'Học sinh hiện có ' . $record['total_reward_points'] . ' sao.')
         ->schema([
             Select::make('reward_item_id')
                 ->label('Chọn phần thưởng')
                 ->required()
                 ->searchable()
                 ->options(function (array $record) {
                     $service = app(RewardRedemptionService::class);
                     $result = $service->getCatalogForRedemption($record['student_id']);

                     if ($result->isError()) {
                         return [];
                     }

                     return collect($result->getData()['items'] ?? [])
                         ->mapWithKeys(fn (array $item) => [$item['id'] => $item['label']])
                         ->all();
                 }),
         ])
         ->action(function (array $data, array $record) {
             $service = app(RewardRedemptionService::class);
             $result = $service->redeemForStudent($record['student_id'], (int) $data['reward_item_id']);

             if ($result->isError()) {
                 Notification::make()->title('Lỗi đổi thưởng')
                     ->body($result->getMessage())->danger()->send();
                 throw new Halt();
             }

             $this->updateStudentRowOnUI($record['student_id'], [
                 'total_reward_points' => $result->getData()['remaining_points'] ?? $record['total_reward_points'],
             ]);

             Notification::make()->title('Đổi thưởng thành công')->success()->send();
         });
    }

    /**
     * Hàm tạo Action cho ghi chú riêng
     * @return Action
     */
    protected function actionPrivateNote()
    {
        return Action::make('private_note')
            ->label('Ghi chú riêng')
            ->icon(Heroicon::LockClosed)
            ->color('gray')
            ->modalHeading(fn (array $record) => "Ghi chú nội bộ: " . $record['student_name'])
            ->modalWidth('sm')
            ->fillForm(fn (array $record) => [
                'private_note' => $record['private_note'],
            ])
            ->visible(fn(): bool => $this->record->isDraft())
            ->schema([
                Textarea::make('private_note')
                    ->label('Nội dung ghi chú')
                    ->helperText('Chỉ giáo viên và quản lý nhìn thấy...')
                    ->rows(4),
            ])
            ->action(function (array $data, array $record, AttendanceService $service) {
                // Gọi service lưu vào DB
                $result = $service->updatePrivateNoteStudent(
                    session: $this->record,
                    studentId: $record['student_id'],
                    note: $data['private_note']);
                if ($result->isError()) {
                    Notification::make()->title("Lỗi cập nhật ghi chú")
                        ->body($result->getMessage())->danger()->send();
                    throw new Halt();
                }
                // Cập nhật RAM
                $this->updateStudentRowOnUI($record['student_id'], [
                    'private_note' => $data['private_note']
                ]);
                Notification::make()->title('Đã lưu ghi chú nội bộ')->success()->send();
            });
    }

    /**
     * Hàm tạo Action cho nhập điểm cả lớp
     * @return Action
     */
    public function actionBulkSaveScore(): Action
    {
        return Action::make('bulkSaveScore')
            ->label('Nhập điểm cả lớp')
            ->icon('heroicon-m-clipboard-document-check')
            ->color('primary')
            ->modalHeading('Nhập điểm nhanh cho cả lớp')
            ->modalWidth(Width::FourExtraLarge) // Form rộng để dễ nhập liệu
            ->modalDescription('Nhập Tên bài kiểm tra, sau đó điền điểm cho các học sinh. Bỏ trống nếu học sinh đó không có điểm.')
            ->visible(fn(): bool => $this->record->isDraft())
            ->schema([
                TextInput::make('exam_name')
                    ->label('Tên đầu điểm / Bài kiểm tra')
                    ->required()
                    ->placeholder('VD: Bài tập về nhà, Kiểm tra 15 phút...')
                    ->columnSpanFull(),

                Repeater::make('students')
                    ->label('Danh sách học sinh')
                    ->hiddenLabel()
                    ->compact()
                    ->schema([
                        Grid::make(12)->schema([
                            Hidden::make('student_id'),
                            Hidden::make('disabled'),
                            TextInput::make('student_name')
                                ->label('Học sinh')
                                ->disabled() // Không cho sửa tên
                                ->columnSpan(4),

                            TextInput::make('score')
                                ->label('Điểm')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(10)
                                ->placeholder('--')
                                ->columnSpan(3)
                                ->disabled(fn (Get $get): bool => (bool) $get('disabled'))
                                ->hintIcon(fn (Get $get) => $get('disabled') ? 'heroicon-m-lock-closed' : null)
                                ->hintColor('danger')
                                ->helperText(fn (Get $get) => $get('disabled') ? 'Học sinh này chưa điểm danh, không thể nhập điểm.' : "Bỏ trống nếu học sinh này không có điểm"),

                            TextInput::make('note')
                                ->label('Ghi chú')
                                ->placeholder('Nhận xét (không bắt buộc)')
                                ->columnSpan(5)
                                ->disabled(fn (Get $get): bool => (bool) $get('disabled'))
                                ->hintIcon(fn (Get $get) => $get('disabled') ? 'heroicon-m-lock-closed' : null)
                                ->hintColor('danger'),
                        ])
                    ])
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false)
                    ->columnSpanFull()
                    ->extraAttributes(['class' => 'border-none shadow-none bg-transparent']),
            ])
            // ẢO THUẬT Ở ĐÂY: Tự động fill danh sách học sinh vào Repeater khi mở Modal
            ->fillForm(function () {
                $studentsData = [];

                // Giả sử $this->attendanceList là mảng chứa danh sách học sinh đang hiển thị
                foreach ($this->attendanceList as $student) {
                    $studentsData[] = [
                        'student_id'   => (string)$student['student_id'],
                        'student_name' => $student['student_name'],
                        'score'        => null,
                        'note'         => null,
                        'disabled'     => $student['attendance_status'] === AttendanceStatus::Draft,
                    ];
                }

                return [
                    'exam_name' => null,
                    'students'  => $studentsData,
                ];
            })
            ->action(function (array $data, AttendanceService $service) {
                $result = $service->bulkSaveScores(
                    session: $this->record,
                    examName: $data['exam_name'],
                    studentsData: $data['students'],
                );

                if ($result->isSuccess()) {
                    Notification::make()
                        ->success()
                        ->title('Thành công')
                        ->body('Đã lưu điểm cho cả lớp.')
                        ->send();

//                    // Cập nhật lại UI
//                    foreach ($data['students'] as $studentData) {
//                        if (!$studentData['disabled']) {
//                            $this->updateStudentRowOnUI($studentData['student_id'], [
//                                'attendance_scores' => array_merge(
//                                    $this->getStudentRow($studentData['student_id'])['attendance_scores'] ?? [],
//                                    [[
//                                        'exam_name' => $data['exam_name'],
//                                        'score' => $studentData['score'],
//                                        'note' => $studentData['note'],
//                                    ]]
//                                )
//                            ]);
//                        }
//                    }

                    $this->mount();
                } else {
                    Notification::make()
                        ->danger()
                        ->title('Lỗi')
                        ->body($result->getMessage())
                        ->send();

                    throw new \Filament\Support\Exceptions\Halt();
                }
            });
    }
}

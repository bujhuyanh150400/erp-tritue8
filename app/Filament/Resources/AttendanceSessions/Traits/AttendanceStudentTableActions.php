<?php

namespace App\Filament\Resources\AttendanceSessions\Traits;

use App\Constants\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Services\AttendanceService;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
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
                ->action(fn($recordOrRecords, AttendanceService $service) => $this->handleHybridAction($isBulk, $recordOrRecords, $service, AttendanceStatus::Present)),

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
                ->action(fn($recordOrRecords, array $data, AttendanceService $service) => $this->handleHybridAction($isBulk, $recordOrRecords, $service, AttendanceStatus::Late, $data)),

            // 3. VẮNG CÓ PHÉP
            $actionClass::make('mark_excused')
                ->label('Vắng có phép')
                ->icon(Heroicon::DocumentText)
                ->color('info')
                ->form([
                    Textarea::make('reason_absent')->label('Lý do')->required(),
                ])
                ->when($isBulk, fn($action) => $action->deselectRecordsAfterCompletion())
                ->action(fn($recordOrRecords, array $data, AttendanceService $service) => $this->handleHybridAction($isBulk, $recordOrRecords, $service, AttendanceStatus::AbsentExcused, $data)),

            // 4. VẮNG KHÔNG PHÉP
            $actionClass::make('mark_unexcused')
                ->label('Vắng không phép')
                ->icon(Heroicon::XCircle)
                ->color('danger')
                ->when($isBulk, fn($action) => $action->deselectRecordsAfterCompletion())
                ->action(fn($recordOrRecords, AttendanceService $service) => $this->handleHybridAction($isBulk, $recordOrRecords, $service, AttendanceStatus::Absent)),
        ];

        // Trả về group tương ứng
        return $isBulk
            ? BulkActionGroup::make($actions)->label('Điểm danh hàng loạt')
            : ActionGroup::make($actions)->label('Điểm danh')->icon(Heroicon::CheckCircle)->color('success')->button();
    }


    /**
     * Tạo action chấm điểm cho một học sinh
     * @return Action
     */
    protected function actionSaveScore()
    {
        return Action::make('input_scores')
            ->label('Chấm điểm')
            ->button()
            ->icon(Heroicon::OutlinedAcademicCap)
            ->modalHeading("Điểm danh")
            // Chỉ cho phép chấm nếu học sinh CÓ MẶT tại lớp
            ->visible(fn(array $record) => $record['attendance_status']->statusPresentInAttendance())
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
                        TextInput::make('exam_name')
                            ->label('Tên bài')
                            ->required()
                            ->placeholder('BTVN, Kiểm tra miệng...'),
                        Grid::make(2)->schema([
                            TextInput::make('score')
                                ->label('Điểm')
                                ->numeric()
                                ->live(onBlur: true)
                                ->minValue(0)
                                ->required()
                                // Sử dụng closure để lấy giá trị của trường max_score
                                ->rules([
                                    fn(Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get) {
                                        $maxScore = $get('max_score');
                                        if (is_numeric($value) && is_numeric($maxScore) && $value > $maxScore) {
                                            $fail("Điểm số không được lớn hơn thang điểm ({$maxScore}).");
                                        }
                                    },
                                ]),
                            TextInput::make('max_score')
                                ->label('Thang điểm')
                                ->numeric()
                                ->default(10)
                                ->required()
                                ->live()
                        ]),
                        Textarea::make('note')
                            ->label('Ghi chú (nếu có)'),
                    ])
                    ->minItems(1)
                    ->addActionLabel('Thêm đầu điểm mới')
                    ->collapsible()
            ])
            ->action(function (array $data, array $record, AttendanceService $service) {
                // Lưu DB qua Service (Bạn viết hàm lưu tương tự như đã bàn)
                $result = $service->saveStudentScores(
                    sessionId: $this->record->id,
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
    private function handleHybridAction(bool $isBulk, $recordOrRecords, $service, $status, $data = []): void
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
            $this->updateAttendanceDataForStudentByStatus($service, $recordOrRecords['student_id'], $status, $data, true);
            Notification::make()->title('Đã cập nhật điểm danh')->success()->send();
        }
    }
}

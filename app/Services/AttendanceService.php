<?php

namespace App\Services;

use App\Constants\AttendanceSessionStatus;
use App\Constants\AttendanceStatus;
use App\Constants\ScheduleStatus;
use App\Constants\ScheduleType;
use App\Core\Logs\Logging;
use App\Core\Services\BaseService;
use App\Core\Services\ServiceException;
use App\Core\Services\ServiceReturn;
use App\Models\AttendanceSession;
use App\Models\ScheduleInstance;
use App\Repositories\AttendanceRecordRepository;
use App\Repositories\AttendanceSessionRepository;
use App\Repositories\ClassEnrollmentRepository;
use App\Repositories\ScoreRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use function PHPUnit\Framework\callback;

class AttendanceService extends BaseService
{
    public function __construct(
        protected AttendanceSessionRepository $attendanceSessionRepository,
        protected AttendanceRecordRepository  $attendanceRecordRepository,
        protected ClassEnrollmentRepository   $classEnrollmentRepository,
        protected ScoreRepository             $scoreRepository,
    )
    {
    }

    /**
     * Bắt đầu hoặc lấy Phiên điểm danh hiện tại
     * @param ScheduleInstance $si
     * @return ServiceReturn
     */
    public function startOrGetSession(ScheduleInstance $si): ServiceReturn
    {
        return $this->execute(
            callback: function () use ($si) {
                // 1. Nếu đã có session thì bỏ qua validation, trả về luôn để navigate
                if ($si->attendanceSession) {
                    return ServiceReturn::success(data: $si->attendanceSession);
                }

                $user = Auth::user();

                // ==========================================
                // 2. VALIDATION (Kiểm tra các chốt chặn)
                // ==========================================

                // 2.1 Trạng thái buổi học
                if (in_array($si->status, [ScheduleStatus::Cancelled, ScheduleStatus::Rescheduled])) {
                    throw new ServiceException("Không thể điểm danh cho buổi học đã bị hủy hoặc dời lịch.");
                }

                // 2.2 Thời gian cho phép (Chặn mở điểm danh cho tương lai)
                if (Carbon::parse($si->date)->isFuture()) {
                    throw new ServiceException("Chưa đến ngày học. Không thể mở điểm danh trước để đảm bảo sĩ số chính xác.");
                }

                // 2.3 Loại lịch học
                if ($si->schedule_type === ScheduleType::Holiday) {
                    throw new ServiceException("Đây là ngày nghỉ lễ, không yêu cầu điểm danh.");
                }

                // 2.4 Kiểm tra phân quyền (Admin full quyền, Giáo viên phải đúng lớp)
                // Giả sử role Admin là 1 (hoặc gọi hàm isAdmin() tùy cấu trúc user của bạn)
                if (!$user->isAdmin() && $si->teacher_id !== $user->teacher?->id) {
                    throw new ServiceException("Bạn không phải giáo viên phụ trách của buổi học này.");
                }

                // ==========================================
                // 3. THỰC THI TẠO MỚI (Atomic)
                // ==========================================

                // firstOrCreate bản thân nó đã là một thao tác an toàn với database
                $session = $this->attendanceSessionRepository->query()->firstOrCreate(
                    [
                        'schedule_instance_id' => $si->id,
                    ],
                    [
                        'class_id' => $si->class_id,
                        'teacher_id' => $si->teacher_id,
                        'session_date' => $si->date,
                        'status' => AttendanceSessionStatus::Draft->value // Hoặc dùng Enum AttendanceSessionStatus::Draft->value
                    ]
                );

                // Ghi Log hoạt động
                Logging::userActivity(
                    action: 'Bắt đầu điểm danh',
                    description: "Khởi tạo phiên điểm danh cho lớp {$si->class->name} ngày " . Carbon::parse($si->date)->format('d/m/Y')
                );

                return ServiceReturn::success(data: $session);
            },
            useTransaction: true // Bảo toàn tính nguyên tử
        );
    }

    /**
     * Lấy danh sách học sinh đang học tại thời điểm diễn ra buổi học
     * @param AttendanceSession $session
     * @return ServiceReturn
     */
    public function getStudentListForAttendance(AttendanceSession $session): ServiceReturn
    {
        return $this->execute(
            callback: function () use ($session) {
                $dateStr = \Carbon\Carbon::parse($session->session_date)->toDateString();

                $enrollments = $this->classEnrollmentRepository->getStudentListForAttendance($session->id, $session->class_id, $dateStr);

                // 2. Map (Làm phẳng) dữ liệu Eloquent Model thành mảng cho Filament Repeater
                // Đồng thời Sort theo tên học sinh
                $studentsList = $enrollments->map(function ($enrollment) {
                    // Lấy học sinh từ enrollment
                    $student = $enrollment->student;

                    // Lấy record điểm danh của buổi này
                    $record = $student->attendanceRecords->first();

                    $scoresData = $record && !$record->scores->isEmpty() ?  $record->scores->map(function ($s) {
                        return [
                            'id' => $s->id,
                            'exam_slot' => $s->exam_slot,
                            'exam_name' => $s->exam_name ?? "Đầu điểm {$s->exam_slot}",
                            'score' => (float)$s->score,
                            'max_score' => (float)$s->max_score,
                            'note' => $s->note ?? '',
                        ];
                    })->toArray() : [];

                    return [
                        'student_id' => $student->id,
                        'student_name' => $student->full_name,
                        'record_id' => $record?->id,
                        'attendance_status' => $record?->status ?? AttendanceStatus::Draft,
                        'is_fee_counted' => $record?->is_fee_counted ?? true,
                        'check_in_time' => $record?->check_in_time,
                        'teacher_comment' => $record?->teacher_comment,
                        'private_note' => $record?->private_note,
                        'total_reward_points' => $student->total_reward_points ?? 0,
                        'reason_absent' => $record?->reason_absent ?? '',
                        'attendance_scores' => $scoresData,
                    ];
                });

                // Sắp xếp theo tên học sinh (Sort trên RAM) và trả về mảng
                return $studentsList->sortBy('student_name')->values()->toArray();
            }
        );
    }

    /**
     * Cập nhật thông tin buổi học
     * @param AttendanceSession $session
     * @param array $data
     * @return ServiceReturn
     * @throws \Throwable
     */
    public function updateSessionInfo(AttendanceSession $session, array $data): ServiceReturn
    {
        return $this->execute(
            callback: function () use ($session, $data) {
                $dataUpdate = collect($data)->only([
                    'lesson_content',
                    'homework',
                    'next_session_note',
                    'general_note'
                ])->toArray();
                $session->update($dataUpdate);
                return ServiceReturn::success($session);
            }
        );
    }

    /**
     * Đánh dấu điểm danh cho học sinh
     * @param AttendanceSession $session
     * @param int $studentId
     * @param AttendanceStatus $status
     * @param array $data
     * @return ServiceReturn
     */
    public function markStudentAttendance(AttendanceSession $session, int $studentId, AttendanceStatus $status, array $data = []): ServiceReturn
    {
        return $this->execute(
            callback: function () use ($session, $studentId, $status, $data) {
                // VALIDATE THỜI GIAN ĐI MUỘN (Nếu có nhập)
                if ($status === AttendanceStatus::Late && !empty($data['check_in_time'])) {
                    $inputTime = Carbon::parse($data['check_in_time']);
                    $startTime = Carbon::parse($session->scheduleInstance->start_time);
                    $endTime = Carbon::parse($session->scheduleInstance->end_time);

                    if ($inputTime->gt($endTime)) {
                        throw new ServiceException('Giờ đến lớp (' . $inputTime->format('H:i') . ') không được vượt quá giờ kết thúc của lớp (' . $endTime->format('H:i') . ').');
                    }

                    if ($inputTime->lt($startTime)) {
                        throw new ServiceException('Giờ đi muộn không hợp lý vì sớm hơn cả giờ bắt đầu lớp (' . $startTime->format('H:i') . '). Vui lòng chọn "Có mặt".');
                    }
                }
                // Xác định dữ liệu cần lưu
                $recordData = match ($status) {
                    AttendanceStatus::Present => [
                        'status' => $status,
                        'is_fee_counted' => true,
                        'check_in_time' => Carbon::parse($session->scheduleInstance->start_time)->format('H:i:s'),
                        'reason_absent' => null,
                    ],
                    AttendanceStatus::Late => [
                        'status' => $status,
                        'is_fee_counted' => true,
                        'check_in_time' => !empty($data['check_in_time']) ?
                            Carbon::parse($data['check_in_time'])->format('H:i:s') :
                            Carbon::parse($session->scheduleInstance->start_time)->addMinutes(30)->format('H:i:s'), // Mặc định là 30 phút sau giờ bắt đầu
                        'reason_absent' => null,
                    ],
                    AttendanceStatus::AbsentExcused => [
                        'status' => $status,
                        'is_fee_counted' => false,
                        'check_in_time' => null,
                        'reason_absent' => $data['reason_absent'] ?? null,
                    ],
                    AttendanceStatus::Absent => [
                        'status' => $status,
                        'is_fee_counted' => false,
                        'check_in_time' => null,
                        'reason_absent' => null,
                    ],
                    AttendanceStatus::Draft =>
                    throw new ServiceException('Trạng thái không hợp lệ'),
                };


                // Lưu vào DB qua Repo
                $record = $this->attendanceRecordRepository->query()->updateOrCreate([
                    'session_id' => $session->id,
                    'student_id' => $studentId,
                ], $recordData);

                //Ràng buộc: Xóa sạch điểm số nếu Vắng mặt (Có phép / Không phép)
                if (!$status->statusPresentInAttendance()) {
                    $this->scoreRepository->deleteScoresByAttendanceRecord($record->id);
                }

                // Trả về data mới để Component cập nhật lên RAM
                return $record;
            },
            useTransaction: true
        );
    }

    /**
     * Lưu danh sách điểm của một học sinh trong một buổi học
     * @param int $sessionId
     * @param int $studentId
     * @param array $scoresData
     * @return ServiceReturn
     * @throws \Throwable
     */
    public function saveStudentScores(int $sessionId, int $studentId, array $scoresData): ServiceReturn
    {
        return $this->execute(
            callback: function () use ($sessionId, $studentId, $scoresData) {
                // Tìm bản ghi điểm danh
                $attendanceRecord = $this->attendanceRecordRepository->query()
                    ->where('session_id', $sessionId)
                    ->where('student_id', $studentId)
                    ->whereIn('status', AttendanceStatus::presentStatus()) // Chỉ lấy các trạng thái có mặt trong buổi học
                    ->first();

                if (!$attendanceRecord) {
                    throw new ServiceException("Học sinh này chưa được điểm danh hoặc vắng mặt. Vui lòng điểm danh trước khi nhập điểm.");
                }

                // 2. Thực hiện Upsert (Cập nhật hoặc Tạo mới) dựa trên exam_slot
                $activeSlots = [];

                foreach ($scoresData as $index => $item) {
                    // Chúng ta dùng index + 1 của mảng Repeater làm exam_slot (1, 2, 3...)
                    $slot = $index + 1;
                    $activeSlots[] = $slot;
                    $this->scoreRepository->query()->updateOrCreate(
                        [
                            'attendance_record_id' => $attendanceRecord->id,
                            'exam_slot'            => $slot,
                        ],
                        [
                            'exam_name' => $item['exam_name'] ?? "Đầu điểm {$slot}",
                            'score'     => $item['score'],
                            'max_score' => $item['max_score'] ?? 10,
                            'note'      => $item['note'] ?? null,
                        ]
                    );
                }

                // 3. Dọn rác (Delete)
                // Nếu GV xóa bớt hàng trong Repeater, ta phải xóa các slot tương ứng trong DB
                $this->scoreRepository->query()
                    ->where('attendance_record_id', $attendanceRecord->id)
                    ->whereNotIn('exam_slot', $activeSlots)
                    ->delete();

                return true;
            },
            useTransaction: true // Luôn dùng Transaction khi loop lưu nhiều record
        );
    }
}

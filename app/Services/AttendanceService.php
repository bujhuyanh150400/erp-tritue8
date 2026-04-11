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
use App\Repositories\RewardPointRepository;
use App\Repositories\ScheduleInstanceRepository;
use App\Repositories\ScoreRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use function PHPUnit\Framework\callback;

class AttendanceService extends BaseService
{
    public function __construct(
        protected ScheduleInstanceRepository  $scheduleInstanceRepository,
        protected AttendanceSessionRepository $attendanceSessionRepository,
        protected AttendanceRecordRepository  $attendanceRecordRepository,
        protected ClassEnrollmentRepository   $classEnrollmentRepository,
        protected ScoreRepository             $scoreRepository,
        protected RewardPointRepository       $rewardPointRepository,
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

                // 3. THỰC THI TẠO MỚI (Atomic)
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
                $dateStr = Carbon::parse($session->session_date)->toDateString();

                $enrollments = $this->classEnrollmentRepository->getStudentListForAttendance($session->id, $session->class_id, $dateStr);

                // 2. Map (Làm phẳng) dữ liệu Eloquent Model thành mảng cho Filament Repeater
                // Đồng thời Sort theo tên học sinh
                $studentsList = $enrollments->map(function ($enrollment) {
                    // Lấy học sinh từ enrollment
                    $student = $enrollment->student;

                    // Lấy record điểm danh của buổi này
                    $record = $student->attendanceRecords->first();

                    $scoresData = $record && !$record->scores->isEmpty() ? $record->scores->map(function ($s) {
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
                if (!$session->isDraft()) {
                    throw new ServiceException("Buổi học này không thể cập nhật thông tin vì đã được chốt sổ.");
                }
                $dataUpdate = collect($data)->only([
                    'lesson_content',
                    'lesson_content_files',

                    'homework',
                    'homework_files',

                    'general_note',
                    'general_note_files',

                ])->toArray();

                $session->update($dataUpdate);
                // Ghi Log hệ thống
                Logging::userActivity(
                    action: 'Cập nhật thông tin buổi học',
                    description: "Cập nhật thông tin buổi học cho lớp {$session->class->name} "
                );
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
                if (!$session->isDraft()) {
                    throw new ServiceException("Buổi học này không thể đánh dấu điểm danh vì đã được chốt sổ.");
                }
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
                // Ghi Log hệ thống
                Logging::userActivity(
                    action: 'Đánh dấu điểm danh',
                    description: "Đánh dấu {$status->label()} cho học sinh {$record->student->name} (ID: {$record->student->id})",
                );
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
    public function saveStudentScores(AttendanceSession $session, int $studentId, array $scoresData): ServiceReturn
    {
        return $this->execute(
            callback: function () use ($session, $studentId, $scoresData) {
                if (!$session->isDraft()) {
                    throw new ServiceException("Buổi học này không thể cập nhật điểm số vì đã được chốt sổ.");
                }
                // Tìm bản ghi điểm danh
                $attendanceRecord = $this->attendanceRecordRepository->query()
                    ->where('session_id', $session->id)
                    ->where('student_id', $studentId)
                    ->first();

                if (!$attendanceRecord) {
                    throw new ServiceException("Học sinh này chưa chốt điểm danh. Vui lòng chốt điểm danh trước khi nhập điểm.");
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
                            'exam_slot' => $slot,
                        ],
                        [
                            'exam_name' => $item['exam_name'] ?? "Đầu điểm {$slot}",
                            'score' => $item['score'],
                            'max_score' => 10, // Mặc định là 10 điểm
                            'note' => $item['note'] ?? null,
                        ]
                    );
                }

                // 3. Dọn rác (Delete)
                // Nếu GV xóa bớt hàng trong Repeater, ta phải xóa các slot tương ứng trong DB
                $this->scoreRepository->query()
                    ->where('attendance_record_id', $attendanceRecord->id)
                    ->whereNotIn('exam_slot', $activeSlots)
                    ->delete();

                // Ghi Log hệ thống
                Logging::userActivity(
                    action: 'Nhập điểm',
                    description: "Nhập điểm cho học sinh {$attendanceRecord->student->name} (ID: {$attendanceRecord->student->id})",
                );
                return true;
            },
            useTransaction: true // Luôn dùng Transaction khi loop lưu nhiều record
        );
    }

    /**
     * Cập nhật điểm thưởng của một học sinh trong một buổi học
     * @param int $sessionId
     * @param int $studentId
     * @param int $amount
     * @param string|null $reason
     * @return ServiceReturn
     */
    public function updateStudentRewardPoints(AttendanceSession $session, int $studentId, int $amount, ?string $reason = null): ServiceReturn
    {
        return $this->execute(
            callback: function () use ($session, $studentId, $amount, $reason) {
                if (!$session->isDraft()) {
                    throw new ServiceException("Buổi học này không thể cập nhật điểm thưởng vì đã được chốt sổ.");
                }
                $reward = $this->rewardPointRepository->query()->create([
                    'student_id' => $studentId,
                    'session_id' => $session->id,
                    'amount' => $amount,
                    'reason' => $reason ?? ($amount > 0 ? 'Thưởng trong giờ' : 'Trừ điểm thái độ'),
                    'awarded_by' => auth()->user()->id,
                ]);
                Logging::userActivity(
                    action: 'Cập nhật điểm thưởng',
                    description: "Cập nhật điểm thưởng cho học sinh {$reward->student->name} (ID: {$reward->student->id})",
                );
                return $reward;
            });
    }

    /**
     * Cập nhật ghi chú nội bộ (Private Note) cho học sinh
     * @param int $sessionId
     * @param int $studentId
     * @param string $note
     * @return ServiceReturn
     * @throws \Throwable
     */
    public function updatePrivateNoteStudent(AttendanceSession $session, int $studentId, string $note): ServiceReturn
    {
        return $this->execute(callback: function () use ($session, $studentId, $note) {
            if (!$session->isDraft()) {
                throw new ServiceException("Buổi học này không thể cập nhật ghi chú nội bộ vì đã được chốt sổ.");
            }
            $record = $this->attendanceRecordRepository->query()->updateOrCreate(
                [
                    'session_id' => $session->id,
                    'student_id' => $studentId,
                ],
                [
                    'private_note' => $note,
                ]
            );
            Logging::userActivity(
                action: 'Ghi chú nội bộ',
                description: "Ghi chú nội bộ cho học sinh {$record->student->name} (ID: {$record->student->id})",
            );
            return $record;
        });
    }

    /**
     * Chốt sổ buổi học
     * @param int $sessionId
     * @return ServiceReturn
     * @throws \Throwable
     */
    public function completeSession(int $sessionId): ServiceReturn
    {
        return $this->execute(
            callback: function () use ($sessionId) {
                $session = $this->attendanceSessionRepository->findById($sessionId);

                if (!$session) {
                    throw new ServiceException("Không tìm thấy buổi học.");
                }

                if (!$session->isDraft()) {
                    throw new ServiceException("Buổi học này đã được chốt sổ từ trước.");
                }
                // Validation: Đảm bảo TẤT CẢ học sinh đã được điểm danh
                $dateStr = Carbon::parse($session->session_date)->toDateString();
                $totalStudentInClass = $this->classEnrollmentRepository->countTotalStudentPresent($session->class_id, $dateStr);
                $markedStudentsCount = $this->attendanceRecordRepository->query()
                    ->where('session_id', $sessionId)
                    ->count();
                if ($markedStudentsCount < $totalStudentInClass) {
                    $unmarkedCount = $totalStudentInClass - $markedStudentsCount;
                    throw new ServiceException(
                        "Lỗi chốt sổ: Lớp có {$totalStudentInClass} học sinh nhưng mới điểm danh {$markedStudentsCount} em. " .
                        "Vẫn còn {$unmarkedCount} học sinh chưa được xác nhận trạng thái."
                    );
                }

                // Cập nhật attendance_sessions
                $session->update([
                    'status' => AttendanceSessionStatus::Completed,
                    'completed_at' => Carbon::now(),
                ]);

                // Cập nhật schedule_instances (Lịch học)
                $this->scheduleInstanceRepository->query()
                    ->where('id', $session->schedule_instance_id)
                    ->update(['status' => ScheduleStatus::Completed]);

                // TODO: Queue Gửi tin nhắn Zalo/SMS cho Phụ huynh và Lớp (G6)
                // SendParentNotificationsJob::dispatch($sessionId);
                // SendClassNotificationsJob::dispatch($sessionId);
                // 6. Ghi Log hệ thống
                Logging::userActivity(
                    action: 'Chốt sổ buổi học',
                    description: "Chốt sổ buổi học {$session->session_date} (ID: {$sessionId})",
                );
                return true;
            },
            useTransaction: true,
        );
    }

    /**
     * Mở lại buổi đã hoàn thành (Sensitive action - Admin only).
     *
     * @param int $sessionId
     * @return ServiceReturn
     * @throws \Throwable
     */
    public function reopenCompletedSession(int $sessionId): ServiceReturn
    {
        return $this->execute(
            callback: function () use ($sessionId) {
                $user = Auth::user();

                if (!$user?->isAdmin()) {
                    throw new ServiceException('Bạn không có quyền thực hiện thao tác này.');
                }

                /** @var AttendanceSession|null $session */
                $session = $this->attendanceSessionRepository->findById(
                    id: $sessionId,
                    relations: ['scheduleInstance', 'class', 'teacher'],
                );

                if (!$session) {
                    throw new ServiceException('Không tìm thấy buổi điểm danh.');
                }

                if ($session->status !== AttendanceSessionStatus::Completed) {
                    throw new ServiceException('Chỉ có thể mở lại buổi đang ở trạng thái Hoàn thành.');
                }

                if (!$session->scheduleInstance) {
                    throw new ServiceException('Không tìm thấy lịch học tương ứng của buổi điểm danh.');
                }

                // 1) Reopen attendance session
                $session->update([
                    'status' => AttendanceSessionStatus::Draft,
                    'completed_at' => null,
                    'locked_at' => null,
                ]);

                // 2) Reopen schedule instance
                $session->scheduleInstance->update([
                    'status' => ScheduleStatus::Upcoming,
                ]);

                // 3) Audit trail (file log)
                $sessionDate = Carbon::parse($session->session_date)->format('Y-m-d');
                Logging::userActivity(
                    action: 'Mở chốt sổ buổi học',
                    description: "Admin {$user->username} đã mở chốt sổ buổi điểm danh ID {$session->id} (ngày {$sessionDate}) vào lúc "
                    . Carbon::now()->format('Y-m-d H:i:s')
                    . ". ScheduleInstance ID: {$session->schedule_instance_id}.",
                );
                return true;
            },
            useTransaction: true,
        );
    }

    /**
     * Lưu điểm số cho buổi học (dạng bulk)
     * @param AttendanceSession $session
     * @param string $examName
     * @param array $studentsData
     * @return ServiceReturn
     * @throws \Throwable
     */
    public function bulkSaveScores(AttendanceSession $session, string $examName, array $studentsData): ServiceReturn
    {
        return $this->execute(
            callback: function () use ($session, $examName, $studentsData) {
                if (!$session->isDraft()) {
                    throw new ServiceException("Buổi học này không thể cập nhật điểm số vì đã được chốt sổ.");
                }

                // Lấy tất cả bản ghi điểm danh của buổi học này, index theo student_id cho nhanh
                $attendanceRecords = $this->attendanceRecordRepository->query()
                    ->where('session_id', $session->id)
                    ->get()
                    ->keyBy('student_id');

                // Lấy danh sách các ID của bản ghi điểm danh
                $attendanceRecordIds = $attendanceRecords->pluck('id')->toArray();

                // Lấy MAX(exam_slot) của TẤT CẢ học sinh trong 1 câu query duy nhất
                // Kết quả trả về dạng mảng: [attendance_record_id => max_slot_hiện_tại]
                $maxSlots = [];
                if (!empty($attendanceRecordIds)) {
                    $maxSlots = $this->scoreRepository->query()
                        ->whereIn('attendance_record_id', $attendanceRecordIds)
                        ->selectRaw('attendance_record_id, MAX(exam_slot) as max_slot')
                        ->groupBy('attendance_record_id')
                        ->pluck('max_slot', 'attendance_record_id')
                        ->toArray();
                }

                $inserts = [];
                $countSaved = 0;
                $now = now();
                // Duyệt qua danh sách gửi lên và chuẩn bị mảng dữ liệ
                foreach ($studentsData as $data) {
                    $studentId = $data['student_id'] ?? null;
                    $score = $data['score'] ?? null;

                    if ($score === null || $score === '' || !$studentId || !isset($attendanceRecords[$studentId])) {
                        continue;
                    }

                    $record = $attendanceRecords[$studentId];
                    $recordId = $record->id;

                    // Tính slot tiếp theo dựa vào mảng maxSlots đã query ở trên
                    // Nếu học sinh chưa có điểm nào (chưa có trong maxSlots), mặc định là 0 -> nextSlot = 1
                    $currentMax = $maxSlots[$recordId] ?? 0;
                    $nextSlot = $currentMax + 1;

                    // Cập nhật lại max slot trong mảng bộ nhớ (đề phòng 1 học sinh được gửi 2 lần trong mảng $studentsData)
                    $maxSlots[$recordId] = $nextSlot;

                    // Đưa vào mảng chờ Insert
                    $inserts[] = [
                        'attendance_record_id' => $recordId,
                        'exam_slot' => $nextSlot,
                        'exam_name' => $examName,
                        'score' => $score,
                        'max_score' => 10,
                        'note' => $data['note'] ?? null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    $countSaved++;
                }
                if ($countSaved === 0) {
                    throw new ServiceException("Vui lòng nhập điểm cho ít nhất một học sinh.");
                }

                // Bulk Insert tất cả điểm vào Database bằng 1 câu query duy nhất
                if (!empty($inserts)) {
                    $this->scoreRepository->query()->insert($inserts);
                }

                // Ghi Log
                Logging::userActivity(
                    action: 'Nhập điểm hàng loạt',
                    description: "Nhập điểm ({$examName}) cho {$countSaved} học sinh lớp {$session->class->name}",
                );

                return true;
            },
            useTransaction: true
        );
    }
}

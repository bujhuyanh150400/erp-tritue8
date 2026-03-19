<?php

namespace App\Services;

use App\Constants\UserRole;
use App\Core\DTOs\FilterDTO;
use App\Core\Logs\Logging;
use App\Core\Services\BaseService;
use App\Core\Services\ServiceException;
use App\Core\Services\ServiceReturn;
use App\Models\Student;
use App\Repositories\StudentRepository;
use App\Repositories\UserRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;

class StudentService extends BaseService
{
    public function __construct(
        protected StudentRepository $studentRepository,
        protected UserRepository    $userRepository
    )
    {
    }


    /**
     * Tạo hồ sơ học sinh mới
     * @param array $data
     * @throws \Throwable
     */
    public function createStudent(array $data): ServiceReturn
    {
        return $this->execute(
            callback: function () use ($data) {
                $user = $this->userRepository->findByUsername($data['user_name']);

                if ($user) {
                    throw new ServiceException('Có tài khoản đã dùng với tên đăng nhập này, vui lòng chọn tên đăng nhập khác.');
                }

                $user = $this->userRepository->query()->create([
                    'username' => $data['user_name'],
                    'password' => Hash::make($data['password']),
                    'role' => UserRole::Student,
                    'is_active' => true,
                ]);

                $student = $this->studentRepository->create([
                    'user_id' => $user->id,
                    'full_name' => $data['full_name'],
                    'dob' => $data['dob'],
                    'gender' => $data['gender'],
                    'grade_level' => $data['grade_level'],
                    'parent_name' => $data['parent_name'],
                    'parent_phone' => $data['parent_phone'],
                    'address' => $data['address'],
                    'note' => $data['note'] ?? null,
                ]);
                Logging::userActivity(
                    action: 'Tạo học sinh',
                    description: 'Tạo hồ sơ học sinh ' . $student->fullname
                );

                return ServiceReturn::success(
                   data: $student
                );
            },
            useTransaction: true
        );
    }


    /**
     * Cập nhật thông tin học sinh
     * @throws \Throwable
     */
    public function updateStudent(Student $record, array $data): ServiceReturn
    {
        return $this->execute(
            callback: function () use ($record, $data) {
                // Cập nhật mật khẩu nếu có
                if (!empty($data['password'])) {
                    $user = $record->user;
                    $user->password = Hash::make($data['password']);
                    $user->save();
                }
                $studentData = [
                    'full_name' => $data['full_name'],
                    'dob' => $data['dob'],
                    'gender' => $data['gender'],
                    'grade_level' => $data['grade_level'],
                    'parent_name' => $data['parent_name'],
                    'parent_phone' => $data['parent_phone'],
                    'address' => $data['address'],
                    'note' => $data['note'] ?? null,
                    'updated_at' => now(),
                ];
                $student = $this->studentRepository->updateById($record->id, $studentData);
                if (!$student) {
                    throw new ServiceException('Cập nhật học sinh thất bại.');
                }
                Logging::userActivity(
                    action: 'Cập nhật học sinh',
                    description: 'Cập nhật hồ sơ học sinh ' . $record->full_name
                );

                return ServiceReturn::success(
                    data: $student
                );
            },
            useTransaction: true
        );
    }
}

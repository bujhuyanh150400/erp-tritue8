# Database use: Postgres

# các bảng mặc dịnh của laravel

- sessions
- cache
- cache_locks
- jobs
- job_batches
- failed_jobs
- personal_access_tokens
- password_reset_tokens

# Sơ đồ nhóm bảng và phụ thuộc

- G1 – Auth & Users & Profile
    + Phụ thuộc: ---
- G2 – Học vụ
    + Phụ thuộc: G1
- G3 – Lịch học
    + Phụ thuộc: G1, G2
- G4 – Điểm danh & Điểm số
    + Phụ thuộc: G1, G2, G3
- G5 – Tài chính
    + Phụ thuộc: G1, G2, G3, G4, G5
- G6 - Hệ thống
    + Phụ thuộc: ---

# ---G1: Auth & Users & Profile

## users

    # note
    -  Quản lý tài khoản, vai trò, phân quyền

    # cấu trúc
    - id (unsigned bigint auto increment)
    - username (varchar(50) unique) - Tên đăng nhập, phải là duy nhất
    - password (varchar(255)) - Mật khẩu, phải được mã hóa
    - role (unsigned tinyint default 0) - Vai trò, lưu trữ trong UserRole
    - is_active (boolean default true) - Trạng thái hoạt động
    - created_at (timestamp) - Thời gian tạo
    - updated_at (timestamp) - Thời gian cập nhật
    
    # index 
    - index(role)

## students

    # note
    - Quản lý hồ sơ học sinh

    # cấu trúc
    - id (unsigned bigint auto increment)
    - user_id (unsigned bigint) - ID người dùng, khóa ngoại tham chiếu đến users.id
    - full_name (varchar(255)) - Họ và tên đầy đủ
    - dob (date) - Ngày sinh
    - gender (unsigned tinyint) - Giới tính, lưu trong Gender
    - grade_level (unsigned tinyint) - Học kỳ, lưu trong GradeLevel
    - parent_name (varchar(255)) - Tên bố mẹ
    - parent_phone (varchar(20)) - Số điện thoại bố mẹ
    - address (text) - Địa chỉ
    - zalo_id (varchar(255) nullable) - ID Zalo
    - note (text) - Ghi chú
    - created_at (timestamp) - Thời gian tạo
    - updated_at (timestamp) - Thời gian cập nhật

## teachers

    # note
    - Quản lý hồ sơ giáo viên

    # cấu trúc
    - id (unsigned bigint auto increment)
    - user_id (unsigned bigint) - ID người dùng, khóa ngoại tham chiếu đến users.id
    - full_name (varchar(255)) - Họ và tên đầy đủ
    - phone (varchar(20)) - Số điện thoại
    - email (varchar(255)) - Email
    - address (text) - Địa chỉ
    - bank_bin (varchar(20)) - Mã ngân hàng
    - bank_name (varchar(100)) - Tên ngân hàng 
    - bank_account_number (varchar(30)) - Số tài khoản ngân hàng
    - bank_account_holder (varchar(100)) - Chủ tài khoản ngân hàng
    - status (unsigned tinyint) - Trạng thái, lưu trong EmployeeStatus
    - joined_at (date) - Ngày bắt đầu làm việc
    - created_at (timestamp) - Thời gian tạo
    - updated_at (timestamp) - Thời gian cập nhật

## staff

    # note
    - Quản lý nhân viên (lễ tân, kế toán...)

    # cấu trúc
    - id (unsigned bigint auto increment)
    - user_id (unsigned bigint) - ID người dùng, khóa ngoại tham chiếu đến users.id
    - full_name (varchar(255)) - Họ và tên đầy đủ
    - phone (varchar(20)) - Số điện thoại
    - role_type (unsigned tinyint) - Chức vụ, lưu trong StaffRoleType
    - bank_bin (varchar(20)) - Mã ngân hàng
    - bank_name (varchar(100)) - Tên ngân hàng 
    - bank_account_number (varchar(30)) - Số tài khoản ngân hàng
    - bank_account_holder (varchar(100)) - Chủ tài khoản ngân hàng
    - status (unsigned tinyint) - Trạng thái, lưu trong EmployeeStatus
    - joined_at (date) - Ngày bắt đầu làm việc
    - created_at (timestamp) - Thời gian tạo
    - updated_at (timestamp) - Thời gian cập nhật

# --- G2: Học vụ

## subjects

    # note
    - Quản lý môn học

    # cấu trúc
    - id (unsigned bigint auto increment)
    - name (varchar(50)) - Tên môn học
    - description (text) - Mô tả
    - is_active (boolean default true) - Trạng thái hoạt động
    - created_at (timestamp) - Thời gian tạo
    - updated_at (timestamp) - Thời gian cập nhật

## rooms

    # note
    - Quản lý phòng học

    # cấu trúc
    - id (unsigned bigint auto increment)
    - name (varchar(50) unique) - Tên phòng học 
    - capacity (unsigned tinyint default 0) - Số lượng ghế
    - note (text) - Ghi chú
    - status (unsigned tinyint) - Trạng thái, lưu trong RoomStatus
    - created_at (timestamp) - Thời gian tạo
    - updated_at (timestamp) - Thời gian cập nhật

## classes

    # note
    - Quản lý lớp học

    # cấu trúc
    - id (unsigned bigint auto increment)
    - code (varchar(50) unique) - Mã lớp
    - name (varchar(50)) - Tên lớp
    - subject_id (unsigned bigint) - ID môn học, khóa ngoại tham chiếu đến subjects.id
    - teacher_id (unsigned bigint) - ID giáo viên, khóa ngoại tham chiếu đến teachers.id
    - grade_level (unsigned tinyint) - Học kỳ, lưu trong GradeLevel
    - base_fee_per_session decimal(10,0) - Học phí cơ bản/buổi của lớp. HS có thể có giá riêng
    - teacher_salary_per_session decimal(10,0) - Lương GV cơ bản/buổi cho lớp này
    - max_students (unsigned tinyint default 0) - Số HS tối đa (để cảnh báo khi đầy)
    - status (unsigned tinyint default 0) - Trạng thái, lưu trong ClassStatus
    - start_at (date) - Ngày khai giảng
    - end_at (date nullable) - Ngày kết thúc khai giảng

    - created_at (timestamp) - Thời gian tạo
    - updated_at (timestamp) - Thời gian cập nhật

    # index
    - foreign(teacher_id) references teachers(id)
    - foreign(subject_id) references subjects(id)
    - index(grade_level)
    - index(teacher_id)
    - index(status)

## class_enrollments

    # note
    - Quản lý đăng ký lớp học

    # cấu trúc
    - id (unsigned bigint auto increment)
    - class_id (unsigned bigint) - ID lớp học, khóa ngoại tham chiếu đến classes.id
    - student_id (unsigned bigint) - ID học sinh, khóa ngoại tham chiếu đến students.id
    - fee_per_session decimal(10,0) - Học phí mỗi buổi, nếu khác với lớp. NULL nếu bằng lớp
    - fee_effective_from (date nullable) -Áp dụng từ ngày nào. Cho phép nhiều record cùng class+student với ngày khác nhau
    - fee_effective_to (date nullable) - Áp dụng đến ngày nào. NULL nếu áp dụng đến cuối lớp
    - enrolled_at (timestamp) - Ngày đăng ký — dùng để bỏ qua buổi trước ngày này
    - left_at (date nullable) - Ngày rời khỏi lớp — NULL nếu vẫn trong lớp
    - note (text) - Ghi chú
    - created_at (timestamp) - Thời gian tạo
    - updated_at (timestamp) - Thời gian cập nhật

    #index
    - foreign(class_id) references classes(id)
    - foreign(student_id) references students(id)
    - index(class_id, student_id)
    - index(student_id)

## monthly_reports

    # note
    - Quản lý báo cáo nhận xét của giáo viên
    
    # Cấu trúc
    - id (unsigned bigint auto increment)
    - teacher_id (unsigned bigint) - ID giáo viên, khóa ngoại tham chiếu đến teachers.id
    - class_id (unsigned bigint) - ID lớp học, khóa ngoại tham chiếu đến classes.id
    - student_id (unsigned bigint) - ID học sinh, khóa ngoại tham chiếu đến students.id
    - month (varchar(7)) - Tháng báo cáo
    - status (unsigned tinyint default 0) - Trạng thái, lưu trong ReportStatus
    - content (text) - Nội dung báo cáo
    - submitted_at (timestamp nullable) - Thời gian nộp
    - reviewed_at (timestamp nullable) - Thời gian xem xét
    - reviewed_by (unsigned bigint nullable) - ID người xem xét (admin), khóa ngoại tham chiếu đến users.id
    - reject_reason (text nullable) - Lý do từ chối (nếu có)
    - created_at (timestamp) - Thời gian tạo
    - updated_at (timestamp) - Thời gian cập nhật

# --- G3: Lịch học

## class_schedule_templates

    # note
    - Quản lý Lịch học cố định

    # Cấu trúc
    - id (unsigned bigint auto increment)
    - class_id (unsigned bigint) - ID lớp học, khóa ngoại tham chiếu đến classes.id
    - day_of_week (tinyint unsigned not null) - Ngày trong tuần, lưu trong DayOfWeek
    - start_time (time not null) - Giờ bắt đầu, '17:00:00'
    - end_time (time not null) - Giờ kết thúc, '19:00:00'
    - room_id (unsigned bigint) - ID phòng học, khóa ngoại tham chiếu đến rooms.id
    - teacher_id (unsigned bigint) - ID giáo viên, khóa ngoại tham chiếu đến teachers.id
    - start_date (date not null) - Ngày bắt đầu áp dụng
    - end_date (date nullable) - Ngày kết thúc áp dụng
    - created_by (unsigned bigint) - ID người tạo, khóa ngoại tham chiếu đến users.id
    - created_at (datetime not null default current_timestamp) - Thời gian tạo
    - updated_at (datetime not null default current_timestamp) - Thời gian cập nhật

    # index
    - foreign(class_id) references classes(id)
    - foreign(room_id) references rooms(id)
    - foreign(teacher_id) references teachers(id)
    - foreign(created_by) references users(id)
    - index(class_id, end_date)

## schedule_instances

    # note
    - Quản lý Lịch học chi tiết
    - Đây là bảng trung tâm của toàn bộ hệ thống. Mọi module đều join về bảng này.

    # cấu trúc
    - id (unsigned bigint auto increment)
    - class_id (unsigned bigint) - ID lớp học, khóa ngoại tham chiếu đến classes.id
    - template_id (unsigned bigint) - ID mẫu lịch học, khóa ngoại tham chiếu đến class_schedule_templates.id
    - date (date) - Ngày học
    - start_time (time) - Giờ bắt đầu
    - end_time (time) - Giờ kết thúc
    - room_id (unsigned bigint) - ID phòng học, khóa ngoại tham chiếu đến rooms.id
    - teacher_id (unsigned bigint) - ID giáo viên, khóa ngoại tham chiếu đến teachers.id
    - original_teacher_id (unsigned bigint) - ID giáo viên gốc, khóa ngoại tham chiếu đến teachers.id
    - teacher_salary_snapshot (decimal(10,0)) - Lương giáo viên tại thời điểm tạo
    - custom_salary (decimal(10,0)) - Lương giáo viên tùy chỉnh
    - schedule_type (unsigned tinyint) - Loại lịch học, lưu trong ScheduleType
    - status (unsigned tinyint) - Trạng thái lịch học, lưu trong ScheduleStatus
    - linked_makeup_for (unsigned bigint) - ID lịch học được bù, khóa ngoại tham chiếu đến schedule_instances.id
    - fee_type (unsigned tinyint) - Loại học phí, lưu trong FeeType
    - custom_fee_per_session (decimal(10,0)) - Học phí tùy chỉnh
    - note (text) - Ghi chú
    - created_by (unsigned bigint) - ID người tạo, khóa ngoại tham chiếu đến users.id
    - created_at (datetime not null default current_timestamp) - Thời gian tạo
    - updated_at (datetime not null default current_timestamp) - Thời gian cập nhật

    # index
    - foreign(class_id) references classes(id)
    - foreign(template_id) references class_schedule_templates(id)
    - foreign(room_id) references rooms(id)
    - foreign(teacher_id) references teachers(id)
    - foreign(original_teacher_id) references teachers(id)
    - foreign(linked_makeup_for) references schedule_instances(id)
    - foreign(created_by) references users(id)
    - index(class_id, date)
    - index(room_id, date, start_time, end_time)
    - index(teacher_id, date, start_time, end_time)
    - index(schedule_type, date)  

## schedule_change_requests

    # note
    - Quản lý yêu cầu thay đổi lịch học

    # cấu trúc
    - id (unsigned bigint auto increment)
    - schedule_instance_id (unsigned bigint) - ID lịch học, khóa ngoại tham chiếu đến schedule_instances.id
    - requested_by (unsigned bigint) - ID GV yêu cầu, khóa ngoại tham chiếu đến teachers.id
    - proposed_date (date) - Ngày thay đổi
    - proposed_start_time (time) - Giờ bắt đầu thay đổi
    - proposed_end_time (time) - Giờ kết thúc thay đổi
    - proposed_room_id (unsigned bigint, nullable) - ID phòng thay đổi, khóa ngoại tham chiếu đến rooms.id
    - proposed_teacher_id (unsigned bigint, nullable) - ID giáo viên thay đổi, khóa ngoại tham chiếu đến teachers.id
    - reason (text) - Lý do thay đổi
    - status (unsigned tinyint default 0) - Trạng thái, lưu trong ScheduleChangeStatus
    - reviewed_by (unsigned bigint, nullable) - ID người phê duyệt (Admin hoặc GV), khóa ngoại tham chiếu đến users.id
    - reviewed_at (datetime, nullable) - Thời gian phê duyệt
    - rejected_reason (text, nullable) - Lý do từ chối

    - created_at (datetime not null default current_timestamp) - Thời gian tạo
    - updated_at (datetime not null default current_timestamp) - Thời gian cập nhật

# --- G4: Điểm danh & Điểm số

## attendance_sessions

    # note
    - Quản lý phiên điểm danh
    - Mỗi buổi học có 1 attendance_session tương ứng. Tách riêng để không làm nặng schedule_instances.

    # cấu trúc
    - id (unsigned bigint auto increment)
    - schedule_instance_id (unsigned bigint) - ID lịch học, khóa ngoại tham chiếu đến schedule_instances.id
    - class_id (unsigned bigint) - ID lớp học, khóa ngoại tham chiếu đến classes.id
    - teacher_id (unsigned bigint) - ID giáo viên, khóa ngoại tham chiếu đến teachers.id
    - session_date (date) - Ngày học
    - lesson_content (text, nullable) - Nội dung bài học
    - homework (text, nullable) - BTVN (chỉ mang tính chất note)
    - next_session_note (text, nullable) - Nhắc buổi sau
    - general_note (text, nullable) - Ghi chú chung
    - status (unsigned tinyint default 0) - Trạng thái, lưu trong AttendanceSessionStatus
    - completed_at (datetime, nullable) - Thời gian hoàn thành
    - locked_at (datetime, nullable) - Thời gian chốt tháng
    - created_at (datetime not null default current_timestamp) - Thời gian tạo
    - updated_at (datetime not null default current_timestamp) - Thời gian cập nhật

    # index
    - foreign(schedule_instance_id) references schedule_instances(id)
    - foreign(class_id) references classes(id)
    - foreign(teacher_id) references teachers(id)
    - index(class_id, session_date)
    - index(status)

## attendance_records

    # note
    - Bảng ghi nhận điểm danh từng học sinh trong từng buổi học.

    # cấu trúc
    - id (unsigned bigint auto increment)
    - session_id (unsigned bigint) - ID phiên điểm danh, khóa ngoại tham chiếu đến attendance_sessions.id
    - student_id (unsigned bigint) - ID học sinh, khóa ngoại tham chiếu đến students.id
    - status (unsigned tinyint) - Trạng thái, lưu trong AttendanceStatus
    - check_in_time (time nullable) - Giờ check-in thực tế
    - is_fee_counted (boolean) - Có tính vào học phí không
    - teacher_comment (text nullable) - Nhận xét cá nhân GV
    - private_note (text nullable) - Nhắc riêng gửi phụ huynh
    - created_at (datetime not null default current_timestamp) - Thời gian tạo
    - updated_at (datetime not null default current_timestamp on update current_timestamp) - Thời gian cập nhật

    # index
    - foreign(session_id) references attendance_sessions(id)
    - foreign(student_id) references students(id)
    - unique(session_id, student_id)
    - index(student_id)

## scores

    # note
    - Bảng ghi nhận điểm số từng học sinh trong từng buổi học.

    # cấu trúc
    - id (unsigned bigint auto increment)
    - attendance_record_id (unsigned bigint) - ID phiên điểm danh, khóa ngoại tham chiếu đến attendance_records.id
    - exam_slot (unsigned tinyint) - Vị trí bài kiểm tra, 1 hoặc 2 (tối đa 2 bài/buổi)
    - exam_name (varchar(100) nullable) - Tên bài kiểm tra
    - score (decimal(5,2) nullable) - Điểm số
    - max_score (decimal(5,2) not null default 10) - Điểm tối đa
    - note (text nullable) - Ghi chú
    - created_at (datetime not null default current_timestamp) - Thời gian tạo
    - updated_at (datetime not null default current_timestamp on update current_timestamp) - Thời gian cập nhật

    # index
    - foreign(attendance_record_id) references attendance_records(id)
    - unique(attendance_record_id, exam_slot)
    - index(exam_slot)

## reward_points

    # note
    - Bảng ghi nhận điểm thưởng từng học sinh trong từng buổi học.

    # cấu trúc
    - id (unsigned bigint auto increment)
    - student_id (unsigned bigint) - ID học sinh, khóa ngoại tham chiếu đến students.id
    - session_id (unsigned bigint nullable) - ID phiên điểm danh, khóa ngoại tham chiếu đến attendance_sessions.id
    - amount (int not null) - Số điểm thưởng, dương = cộng, âm = trừ
    - reason (varchar(255) nullable) - Lý do
    - awarded_by (unsigned bigint not null) - ID người tạo, khóa ngoại tham chiếu đến users.id
    - created_at (datetime not null default current_timestamp) - Thời gian tạo
    - updated_at (datetime not null default current_timestamp on update current_timestamp) - Thời gian cập nhật

    # index
    - foreign(student_id) references students(id)
    - foreign(session_id) references attendance_sessions(id)
    - foreign(awarded_by) references users(id)
    - index(student_id)

## reward_items

    # note
    - Bảng ghi nhận danh mục phần thưởng.

    # cấu trúc
    - id (unsigned bigint auto increment)
    - name (varchar(100) not null) - Tên phần thưởng
    - points_required (int unsigned not null) - Số điểm cần để đổi
    - reward_type (unsigned tinyint) - Loại phần thưởng, lưu trong RewardType
    - discount_amount (decimal(10,0) nullable) - Số tiền giảm nếu type=discount
    - is_active (boolean not null default true) - Có hoạt động không
    - created_at (datetime not null default current_timestamp) - Thời gian tạo
    - updated_at (datetime not null default current_timestamp on update current_timestamp) - Thời gian cập nhật

    # index
    - index(reward_type)
    - index(is_active)

## reward_redemptions

    # note
    - Bảng ghi nhận lịch sử đổi thưởng.

    # cấu trúc
    - id (unsigned bigint auto increment)
    - student_id (unsigned bigint) - ID học sinh, khóa ngoại tham chiếu đến students.id
    - reward_item_id (unsigned bigint) - ID phần thưởng, khóa ngoại tham chiếu đến reward_items.id
    - points_spent (int unsigned not null) - Số điểm đã đổi
    - redeemed_at (datetime not null default current_timestamp) - Thời gian đổi
    - processed_by (unsigned bigint not null) - ID người xử lý, khóa ngoại tham chiếu đến users.id
    - invoice_id (unsigned bigint nullable) - ID hóa đơn nếu type=discount, khóa ngoại tham chiếu đến tuition_invoices.id
    - created_at (datetime not null default current_timestamp) - Thời gian tạo
    - updated_at (datetime not null default current_timestamp on update current_timestamp) - Thời gian cập nhật

    # index
    - foreign(student_id) references students(id)
    - foreign(reward_item_id) references reward_items(id)
    - foreign(processed_by) references users(id)
    - foreign(invoice_id) references tuition_invoices(id)

# --- G5: Nhân sự - tài chính

## teacher_salary_configs

    # note
    - Cấu hình lương giáo viên

    # cấu trúc
    - id (unsigned bigint auto increment)
    - teacher_id (unsigned bigint) - ID giáo viên, khóa ngoại tham chiếu đến teachers.id
    - class_id (unsigned bigint) - ID lớp học, khóa ngoại tham chiếu đến classes.id
    - salary_per_session decimal(10,0) - Lương mỗi buổi
    - effective_from (date) - Ngày bắt đầu áp dụng
    - effective_to (date nullable) - Ngày kết thúc áp dụng
    - created_at (timestamp) - Thời gian tạo
    - updated_at (timestamp) - Thời gian cập nhật

    # index
    - foreign(teacher_id) references teachers(id)
    - foreign(class_id) references classes(id)
    - index(teacher_id, class_id)

## staff_shifts

    # note
    - Bảng ghi nhận ca làm việc của nhân viên

    # cấu trúc
    - id (unsigned bigint auto increment)
    - staff_id (unsigned bigint) - ID nhân viên, khóa ngoại tham chiếu đến staff.id
    - shift_date (date) - Ngày làm việc
    - check_in_time (datetime) - Giờ check-in
    - check_out_time (datetime) - Giờ check-out
    - total_hours (decimal(4,2)) - Tổng giờ làm việc
    - hourly_rate_snapshot (decimal(10,0)) - Lương giờ tại thời điểm tạo
    - total_salary (decimal(10,0)) - Tổng lương
    - status (unsigned tinyint) - Trạng thái, lưu trong ShiftStatus
    - note (text nullable) - Ghi chú
    - created_at (timestamp) - Thời gian tạo
    - updated_at (timestamp) - Thời gian cập nhật

    # index
    - foreign(staff_id) references staff(id)
    - index(staff_id, shift_date)

## staff_salary_configs

    # note
    - Cấu hình lương nhân viên

    # cấu trúc
    - id (unsigned bigint auto increment)
    - staff_id (unsigned bigint) - ID nhân viên, khóa ngoại tham chiếu đến staff.id
    - salary_type (unsigned tinyint) - Loại lương, lưu trong SalaryType
    - salary_amount (decimal(10,0)) - Số tiền lương
    - effective_from (date) - Ngày bắt đầu áp dụng
    - effective_to (date nullable) - Ngày kết thúc áp dụng
    - created_at (timestamp) - Thời gian tạo
    - updated_at (timestamp) - Thời gian cập nhật

## tuition_invoices

    # note
    - Bảng ghi nhận hóa đơn học phí

    # cấu trúc
    - id (unsigned bigint auto increment)
    - invoice_number (varchar(20) not null) - Số hóa đơn
    - student_id (unsigned bigint) - ID học sinh, khóa ngoại tham chiếu đến students.id
    - class_id (unsigned bigint) - ID lớp học, khóa ngoại tham chiếu đến classes.id  Hóa đơn theo từng lớp (HS học 2 lớp = 2 hóa đơn)
    - month (varchar(7)) - Tháng lập hóa đơn
    - total_sessions (int) - Tổng số buổi học
    - attended_sessions (int) - Số buổi đã học
    - total_study_fee (decimal(10,0)) - Tổng học phí
    - discount_amount (decimal(10,0), default 0) - Số tiền giảm
    - previous_debt (decimal(10,0), default 0) - Số tiền nợ trước đó
    - total_amount (decimal(10,0)) - Tổng số tiền = total_study_fee - discount_amount + previous_debt
    - paid_amount (decimal(10,0), default 0) - Số tiền đã thanh toán
    - status (unsigned tinyint) - Trạng thái, lưu trong InvoiceStatus
    - is_locked (boolean) - TRUE sau khi chốt tháng. Không cho sửa nữa
    - note (text nullable) - Ghi chú
    - created_at (timestamp) - Thời gian tạo
    - updated_at (timestamp) - Thời gian cập nhật

    # index
    - foreign(student_id) references students(id)
    - foreign(class_id) references classes.id
    - unique(student_id, class_id, month)

## tuition_invoice_logs

    # note
    - Bảng ghi chi tiết các lần thay đổi của hóa đơn học phí

    # cấu trúc
    - id (unsigned bigint auto increment)
    - invoice_id (unsigned bigint) - ID hóa đơn, khóa ngoại tham chiếu đến tuition_invoices.id
    - amount (decimal(10,0)) - Số tiền thay đổi
    - paid_at (datetime) - Thời gian thanh toán
    - note (text nullable) - Ghi chú
    - is_cancelled (boolean) - TRUE nếu là hủy thanh toán
    - cancelled_at (datetime, nullable) - Thời gian hủy
    - cancel_reason (text, nullable) - Lý do hủy
    - changed_by (unsigned bigint) - ID người thay đổi, khóa ngoại tham chiếu đến users.id
    - created_at (timestamp) - Thời gian tạo
    - updated_at (timestamp) - Thời gian cập nhật

    # index
    - foreign(invoice_id) references tuition_invoices(id)
    - foreign(changed_by) references users(id)

## teacher_salary_invoices

    # note
    - Bảng ghi nhận hóa đơn lương giáo viên

    # cấu trúc
    - id (unsigned bigint auto increment)
    - teacher_id (unsigned bigint) - ID giáo viên, khóa ngoại tham chiếu đến teachers.id
    - class_id (unsigned bigint) - ID lớp học, khóa ngoại tham chiếu đến classes.id  Hóa đơn theo từng lớp
    - month (varchar(7)) - Tháng lập hóa đơn
    - total_sessions (int) - Tổng số buổi học
    - bonus (decimal(10,0), default 0) - Thưởng
    - penalty (decimal(10,0), default 0) - Phạt
    - total_amount (decimal(10,0)) - Tổng số tiền
    - paid_amount (decimal(10,0), default 0) - Số tiền đã thanh toán
    - status (unsigned tinyint) - Trạng thái, lưu trong InvoiceStatus
    - is_locked (boolean) - TRUE sau khi chốt tháng. Không cho sửa nữa
    - note (text nullable) - Ghi chú
    - created_at (timestamp) - Thời gian tạo
    - updated_at (timestamp) - Thời gian cập nhật

    # index
    - foreign(teacher_id) references teachers(id)
    - foreign(class_id) references classes.id
    - unique(teacher_id, class_id, month) 

## teacher_salary_invoice_logs

    # note
    - Bảng ghi chi tiết các lần thay đổi của hóa đơn lương giáo viên

    # cấu trúc
    - id (unsigned bigint auto increment)
    - invoice_id (unsigned bigint) - ID hóa đơn, khóa ngoại tham chiếu đến teacher_salary_invoices.id
    - amount (decimal(10,0)) - Số tiền thay đổi
    - paid_at (datetime) - Thời gian thanh toán
    - note (text nullable) - Ghi chú
    - is_cancelled (boolean) - TRUE nếu là hủy thanh toán
    - cancelled_at (datetime, nullable) - Thời gian hủy
    - cancel_reason (text, nullable) - Lý do hủy
    - changed_by (unsigned bigint) - ID người thay đổi, khóa ngoại tham chiếu đến users.id
    - created_at (timestamp) - Thời gian tạo
    - updated_at (timestamp) - Thời gian cập nhật

    # index
    - foreign(invoice_id) references teacher_salary_invoices.id
    - foreign(changed_by) references users(id)

## staff_salary_invoices

    # note
    - Bảng ghi nhận hóa đơn lương nhân viên

    # cấu trúc
    - id (unsigned bigint auto increment)
    - staff_id (unsigned bigint) - ID nhân viên, khóa ngoại tham chiếu đến staff.id
    - month (varchar(7)) - Tháng lập hóa đơn

    - base_salary (decimal(10,0)) - Lương cơ bản
    - bonus (decimal(10,0) default 0) - Thưởng
    - penalty (decimal(10,0) default 0) - Phạt
    - advance_amount (decimal(10,0) default 0) - Số tiền đã ứng
    - total_amount (decimal(10,0)) - Tổng số tiền = base_salary + bonus - penalty - advance_amount
    - paid_amount (decimal(10,0), default 0) - Số tiền đã thanh toán
    - status (unsigned tinyint) - Trạng thái, lưu trong InvoiceStatus
    - is_locked (boolean) - TRUE sau khi chốt tháng. Không cho sửa nữa
    - note (text nullable) - Ghi chú
    - created_at (timestamp) - Thời gian tạo
    - updated_at (timestamp) - Thời gian cập nhật

    # index
    - foreign(staff_id) references staff.id
    - unique(staff_id, month) 

## staff_salary_invoice_logs

    # note
    - Bảng ghi chi tiết các lần thay đổi của hóa đơn lương nhân viên

    # cấu trúc
    - id (unsigned bigint auto increment)
    - invoice_id (unsigned bigint) - ID hóa đơn, khóa ngoại tham chiếu đến staff_salary_invoices.id
    - amount (decimal(10,0)) - Số tiền thay đổi
    - paid_at (datetime) - Thời gian thanh toán
    - note (text nullable) - Ghi chú
    - is_cancelled (boolean) - TRUE nếu là hủy thanh toán
    - cancelled_at (datetime, nullable) - Thời gian hủy
    - cancel_reason (text, nullable) - Lý do hủy
    - changed_by (unsigned bigint) - ID người thay đổi, khóa ngoại tham chiếu đến users.id
    - created_at (timestamp) - Thời gian tạo
    - updated_at (timestamp) - Thời gian cập nhật

    # index
    - foreign(invoice_id) references staff_salary_invoices.id
    - foreign(changed_by) references users(id)

## expense_categories

    # note
    - Danh mục chi phí vận hành

    # cấu trúc
    - id (unsigned bigint auto increment)
    - name (varchar(255) not null) - Tên danh mục
    - description (text nullable) - Mô tả
    - created_at (timestamp) - Thời gian tạo
    - updated_at (timestamp) - Thời gian cập nhật

    # index
    - unique(name)

## expense_invoices

    # note
    - Bảng ghi nhận chi phí 

    # cấu trúc
    - id (unsigned bigint auto increment)
    - category_id (unsigned bigint) - ID danh mục, khóa ngoại tham chiếu đến expense_categories.id
    - title (varchar(255) not null) - Tiêu đề chi phí
    - status (unsigned tinyint) - Trạng thái, lưu trong InvoiceStatus
    - month (varchar(7)) - Tháng lập hóa đơn
    - amount (decimal(10,0)) - Số tiền
    - paid_at (datetime) - Thời gian thanh toán
    - note (text nullable) - Ghi chú
    - changed_by (unsigned bigint) - ID người thay đổi, khóa ngoại tham chiếu đến users.id
    - payment_method (unsigned tinyint) - Phương thức thanh toán, lưu trong PaymentMethod
    - created_by (unsigned bigint) - ID người tạo, khóa ngoại tham chiếu đến users.id
    - is_recurring (boolean) - TRUE nếu là chi phí tái lặp

    - created_at (timestamp) - Thời gian tạo
    - updated_at (timestamp) - Thời gian cập nhật

    # index
    - foreign(category_id) references expense_categories.id
    - foreign(changed_by) references users(id)

# --- G6: Hệ thống

## user_logs

    # note
    - Bảng ghi nhận lịch sử hoạt động của người dùng

    # cấu trúc
    - id (unsigned bigint auto increment)
    - user_id (unsigned bigint) - ID người dùng, khóa ngoại tham chiếu đến users.id
    - action (varchar(255) not null) - Hành động
    - description (text nullable) - Mô tả
    - created_at (timestamp) - Thời gian tạo
    - updated_at (timestamp) - Thời gian cập nhật

    # index
    - foreign(user_id) references users.id

## notifications

    # note
    - Bảng lưu trữ các thông báo gửi cho người dùng

    # cấu trúc
    - id (unsigned bigint auto increment)
    - user_id (unsigned bigint) - ID người nhận, khóa ngoại tham chiếu đến users.id
    - title (varchar(255)) - Tiêu đề thông báo
    - content (text) - Nội dung thông báo
    - type (unsigned tinyint) - Loại thông báo, lưu trong NotificationType
    - is_read (boolean) - TRUE nếu đã đọc
    - read_at (timestamp, nullable) - Thời gian đọc
    - channel (unsigned tinyint) - Chânnel thông báo, lưu trong NotificationChannel
    - send_status (unsigned tinyint) - Trạng thái gửi, lưu trong NotificationSendStatus
    - sent_at (timestamp, nullable) - Thời gian gửi
    - is_urgent (boolean) - TRUE nếu là thông báo quan trọng
    - reference (morph) - Tham chiếu đến model khác

    - created_at (timestamp) - Thời gian tạo
    - updated_at (timestamp) - Thời gian cập nhật

    # index
    - foreign(user_id) references users.id
    

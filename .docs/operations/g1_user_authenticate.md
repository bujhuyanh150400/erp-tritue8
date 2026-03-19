# Phân tích nghiệp vụ — G1: User & Authentication

---

## Tổng quan

`users` là bảng **auth duy nhất** — mọi role đều đăng nhập qua đây.  
`students`, `teachers`, `staff` là bảng **profile** — lưu thông tin nghiệp vụ, không phải thông tin đăng nhập.

Quan hệ:

```
users (1) ──── (0..1) teachers
users (1) ──── (0..1) staff
users (1) ──── (0..1) students
```

### Trạng thái tài khoản — 2 tầng độc lập

| Tầng      | Cột                                | Ý nghĩa                                |
|-----------|------------------------------------|----------------------------------------|
| Auth      | `users.is_active`                  | Có được đăng nhập không                |
| Nghiệp vụ | `teachers.status` / `staff.status` | Có đang hoạt động trong hệ thống không |

---

### Bảo mật dữ liệu nhạy cảm

```
* Thông tin ngân hàng
- Chỉ Admin được xem và sửa (bank_account_number, bank_account_holder).
- Trả về profile cho chính GV / NV → mask số TK: ****1234.

* Password
- Lưu dạng bcrypt hash, không bao giờ trả về trong response.
```

### Các edge case cần xử lý

| Tình huống                          | Xử lý                                                     |
|-------------------------------------|-----------------------------------------------------------|
| Transaction tạo GV lỗi giữa chừng   | Rollback toàn bộ                                          |
| Username bị trùng                   | Báo lỗi ngay khi nhập, gợi ý username khác                |
| Admin tự khóa mình                  | Chặn ở tầng backend, không phụ thuộc UI                   |
| GV nghỉ việc còn buổi dạy tương lai | Cảnh báo trước khi khóa, admin phải xử lý lịch dạy trước  |
| Học sinh chuyển khối (lên lớp)      | Cập nhật students.grade_level, không tạo user mới         |
| Đăng nhập sai nhiều lần             | Không yêu cầu trong spec, bỏ qua ở giai đoạn này          |

---

## Định nghĩa Enum

```
UserRole:
  0 = Admin
  1 = Teacher
  2 = Staff
  3 = Student - Parent  ← dùng chung cho cả phụ huynh

StaffRoleType:
  0 = Receptionist  ← lễ tân

EmployeeStatus:
  0 = Active      — đang hoạt động
  1 = Inactive    — tạm ngưng (nghỉ thai sản, phép dài...)
  2 = Terminated  — đã nghỉ việc hẳn

Gender:
  0 = Male
  1 = Female
  2 = Other

GradeLevel:
  6 | 7 | 8 | 9 | 10 | 11 | 12  — lưu số nguyên trực tiếp
```

> **Student/Parent dùng chung 1 tài khoản.**  
> Phụ huynh có 2 con → 2 tài khoản riêng, mỗi tài khoản gắn với 1 học sinh.

**Ràng buộc transaction:**  
Nếu bất kỳ bước nào lỗi → rollback toàn bộ.  
Không được tồn tại `users` mà không có profile tương ứng.

---

## Luồng Đăng nhập

```
Validate:
  - username (required)
  - password (required, min 8 ký tự, 1 chữ hoa, 1 chữ thường, 1 số, 1 ký tự đặc biệt)

Kiểm tra:
  1. SELECT * FROM users WHERE username = ?
  2. Hash verify password
  3. users.is_active = true

Nếu pass:
  - Auth::login($user) — tạo session phía server (guard web)
  - INSERT user_logs (user_id, action = 'login', created_at = now())
  - Redirect về dashboard theo role

Nếu fail:
  - Trả về lỗi qua session flash: "Tên đăng nhập hoặc mật khẩu không đúng"
  - Không phân biệt sai username hay sai password (bảo mật)
  - Nếu users.is_active = false → "Tài khoản đã bị khóa, liên hệ admin"
```

## Luồng Đăng xuất

```
POST /logout
  - INSERT user_logs (user_id, action = 'logout', created_at = now())
  - Auth::logout()
  - $request->session()->invalidate()
  - $request->session()->regenerateToken()
  - Redirect về trang login
```

---

## Luồng Đổi mật khẩu

### Tự đổi (user đang đăng nhập)

```
POST /change-password
  ← old_password + new_password + confirm_password

Kiểm tra:
  1. Hash verify old_password với users.password
  2. new_password != old_password
  3. new_password đủ độ phức tạp (min 8 ký tự)
  4. confirm_password == new_password

Nếu pass:
  - UPDATE users SET password = bcrypt(new_password) WHERE id = ?
  - INSERT user_logs (action = 'change_password')
```

### Admin reset mật khẩu

```
Trong các màn chỉnh sửa (teacher, staff, student):
  - Admin nhập password mới cho user đó
  - Nếu có nhập → UPDATE users SET password = bcrypt(new_password) WHERE id = ?
  - INSERT user_logs (action = 'reset_password', description = 'admin X reset PW user Y')

→ Admin không được xem mật khẩu hiện tại — chỉ ghi đè mới.
```

---

## Luồng Khóa / Mở khóa tài khoản

```
Admin bấm "Khóa tài khoản"

Kiểm tra:
  - users.id != Auth::id()  ← không được tự khóa mình

Nếu pass:
  - UPDATE users SET is_active = false WHERE id = ?
  - DELETE FROM sessions WHERE user_id = ?  ← kick user ra ngay lập tức
  - INSERT user_logs (action = 'lock_account', description = 'admin X khóa user Y')

Admin bấm "Mở khóa":
  - UPDATE users SET is_active = true WHERE id = ?
  - INSERT user_logs (action = 'unlock_account')
```

---

## Học sinh

### Danh sách

```
Tìm kiếm nhanh:
  - Họ tên    → WHERE students.full_name ILIKE '%keyword%'
  - Mã HS     → OR users.id::text ILIKE '%keyword%'
                  JOIN users ON students.user_id = users.id
  - SĐT PH    → OR students.parent_phone ILIKE '%keyword%'

Bộ lọc:
  - Khối               → WHERE students.grade_level = ?
  - Lớp                → WHERE EXISTS (
                            SELECT 1 FROM class_enrollments
                            WHERE student_id = students.id
                              AND class_id = ? AND left_at IS NULL
                          )
  - Môn học            → WHERE EXISTS (
                            SELECT 1 FROM class_enrollments ce
                            JOIN classes ON ce.class_id = classes.id
                            WHERE ce.student_id = students.id
                              AND classes.subject_id = ? AND ce.left_at IS NULL
                          )
  - Giáo viên          → WHERE EXISTS (
                            SELECT 1 FROM class_enrollments ce
                            JOIN classes ON ce.class_id = classes.id
                            WHERE ce.student_id = students.id
                              AND classes.teacher_id = ? AND ce.left_at IS NULL
                          )

  - Trạng thái tài khoản → WHERE users.is_active = true/false
                              JOIN users ON students.user_id = users.id

Hiển thị tổng → COUNT(students.id)
Nút: [Xóa bộ lọc]

Bảng hiển thị:
  - ID + Họ tên + Ngày sinh → users.id, students.full_name, students.dob
                               JOIN users ON students.user_id = users.id
  - Giới tính               → students.gender
  - Khối                    → students.grade_level
  - Môn đang học            → GROUP_CONCAT(subjects.name)
                               JOIN class_enrollments ce ON ce.student_id = students.id
                                 AND ce.left_at IS NULL
                               JOIN classes ON ce.class_id = classes.id
                               JOIN subjects ON classes.subject_id = subjects.id
                               Hiển thị dạng tag: [Toán 8] [Văn 8]
  - Tên PH + SĐT PH         → students.parent_name, students.parent_phone
  - Trạng thái tài khoản    → users.is_active
  - Số sao                  → SUM(reward_points.amount)
                               JOIN reward_points ON student_id = students.id
  - Action:
      + Xem chi tiết
      + Chỉnh sửa thông tin
      + Xem lịch sử đổi thưởng (popup)
      + Khóa / Mở khóa tài khoản
```

### Tạo Học sinh

```
1. Admin nhập:
    - full_name    - Họ và tên đầy đủ (required)
    - user_name    - Tên đăng nhập (required) — tự render vd: hs_nguyenvana
    - password     - Mật khẩu (required, min 8 ký tự...) — có nút random
    - dob          - Ngày sinh (required)
    - gender       - Giới tính Gender (required)
    - grade_level  - Khối GradeLevel (required)
    - parent_name  - Tên bố mẹ (required)
    - parent_phone - Số điện thoại bố mẹ (required)
    - address      - Địa chỉ (required)
    - note         - Ghi chú (optional)

2. Validation:
    - username unique: SELECT COUNT(*) FROM users WHERE username = ? = 0

3. Service (transaction):
    - INSERT users (username, password = bcrypt(?), role = 3, is_active = true)
    - INSERT students (user_id = users.id, full_name, dob, gender, ...)

4. INSERT user_logs (action = 'create_student', description = 'admin X tạo HS Y lúc Z')
```

### Sửa Học sinh

```
1. Admin chọn học sinh cần sửa
   → SELECT students.*, users.username, users.is_active
     FROM students
     JOIN users ON students.user_id = users.id
     WHERE students.id = ?

2. Admin sửa:
    - full_name, dob, gender, grade_level,
      parent_name, parent_phone, address, note
    - password (optional) — nếu nhập → reset password

3. Service:
    - SELECT student_id FROM students WHERE id = ? → lấy user_id
    - UPDATE users SET password = bcrypt(?) WHERE id = user_id  (nếu có password mới)
    - UPDATE students SET full_name = ?, dob = ?, ... WHERE id = ?

4. INSERT user_logs (action = 'update_student', description = 'admin X sửa HS Y lúc Z')
```

### Trang chi tiết Học sinh

```
Tab 1 — Thông tin cá nhân:
  → SELECT students.*, users.username, users.is_active, users.last_login_at
    FROM students
    JOIN users ON students.user_id = users.id
    WHERE students.id = ?

  Hiển thị: họ tên, ngày sinh, giới tính, khối, địa chỉ,
             tên PH, SĐT PH, zalo_id, ghi chú,
             username, trạng thái tài khoản

Tab 2 — Báo cáo theo môn:
  Lấy danh sách môn HS đang học:
  → SELECT DISTINCT subjects.id, subjects.name,
           classes.id as class_id, classes.name as class_name,
           teachers.full_name as teacher_name,
           ce.enrolled_at
    FROM class_enrollments ce
    JOIN classes ON ce.class_id = classes.id
    JOIN subjects ON classes.subject_id = subjects.id
    JOIN teachers ON classes.teacher_id = teachers.id
    WHERE ce.student_id = ? AND ce.left_at IS NULL

  Mỗi môn là 1 block. Trong mỗi block, lấy theo tháng đang xem:

  Thống kê tháng:
  → SELECT
      COUNT(*) as tong_buoi,
      COUNT(*) FILTER (WHERE ar.status IN (present, late)) as co_mat,
      ROUND(AVG(sc.score), 2) as diem_tb
    FROM attendance_records ar
    JOIN attendance_sessions as_sess ON ar.session_id = as_sess.id
    LEFT JOIN scores sc ON sc.attendance_record_id = ar.id
    WHERE ar.student_id = ?
      AND as_sess.class_id = class_id_của_môn
      AND as_sess.session_date BETWEEN [đầu tháng] AND [cuối tháng]

  Bảng điểm:
  → SELECT as_sess.session_date, sc.exam_name, sc.score, sc.max_score, sc.note
    FROM scores sc
    JOIN attendance_records ar ON sc.attendance_record_id = ar.id
    JOIN attendance_sessions as_sess ON ar.session_id = as_sess.id
    WHERE ar.student_id = ?
      AND as_sess.class_id = class_id_của_môn
      AND as_sess.session_date BETWEEN [đầu tháng] AND [cuối tháng]
    ORDER BY as_sess.session_date

  Nhận xét GV + trạng thái báo cáo:
  → SELECT mr.content, mr.status, mr.submitted_at, mr.reject_reason
    FROM monthly_reports mr
    WHERE mr.student_id = ?
      AND mr.class_id = class_id_của_môn
      AND mr.month = 'YYYY-MM'

Tab 3 — Sao thưởng:
  Tổng sao hiện tại:
  → SELECT SUM(amount) FROM reward_points WHERE student_id = ?

  Lịch sử cộng/trừ sao:
  → SELECT rp.created_at, rp.amount, rp.reason, users.username as nguoi_tao
    FROM reward_points rp
    JOIN users ON rp.awarded_by = users.id
    WHERE rp.student_id = ?
    ORDER BY rp.created_at DESC

  Lịch sử đổi thưởng:
  → SELECT rr.redeemed_at, ri.name as ten_thuong,
           rr.points_spent, users.username as nguoi_xu_ly
    FROM reward_redemptions rr
    JOIN reward_items ri ON rr.reward_item_id = ri.id
    JOIN users ON rr.processed_by = users.id
    WHERE rr.student_id = ?
    ORDER BY rr.redeemed_at DESC
```

---

## Giáo viên

### Danh sách

```
Tìm kiếm nhanh:
  - Mã GV   → WHERE users.id::text ILIKE '%keyword%'
               JOIN users ON teachers.user_id = users.id
  - Họ tên  → OR teachers.full_name ILIKE '%keyword%'
  - SĐT     → OR teachers.phone ILIKE '%keyword%'

Bộ lọc:
  - Trạng thái nghiệp vụ → WHERE teachers.status = ?
  - Trạng thái tài khoản → WHERE users.is_active = true/false
                             JOIN users ON teachers.user_id = users.id
  - Môn dạy              → WHERE EXISTS (
                               SELECT 1 FROM classes
                               WHERE classes.teacher_id = teachers.id
                                 AND classes.subject_id = ?
                                 AND classes.status = Active
                             )

Hiển thị tổng → COUNT(teachers.id)
Nút: [Xóa bộ lọc]

Bảng hiển thị:
  - Họ tên              → teachers.full_name
  - SĐT                 → teachers.phone
  - Môn dạy             → GROUP_CONCAT(DISTINCT subjects.name)
                           JOIN classes ON classes.teacher_id = teachers.id
                             AND classes.status = Active
                           JOIN subjects ON classes.subject_id = subjects.id
  - Số lớp đang dạy     → COUNT(classes.id)
                           WHERE classes.teacher_id = teachers.id
                             AND classes.status = Active
  - Trạng thái nghiệp vụ→ teachers.status
  - Trạng thái tài khoản→ users.is_active
                           JOIN users ON teachers.user_id = users.id
  - Action:
      + Xem chi tiết
      + Xem lịch dạy
      + Xem bảng lương
      + Chỉnh sửa
      + Khóa / Mở khóa tài khoản
```

### Tạo Giáo viên

```
1. Admin nhập:
    - full_name           - Họ và tên (required)
    - user_name           - Tên đăng nhập (required) — tự render vd: gv_nguyenvana
    - password            - Mật khẩu (required, min 8 ký tự...) — có nút random
    - phone               - Số điện thoại (required)
    - email               - Email (required)
    - address             - Địa chỉ (required)
    - bank_name           - Tên ngân hàng (required)
    - bank_account_number - Số tài khoản (required)
    - bank_account_holder - Chủ tài khoản (required)
    - status              - Trạng thái EmployeeStatus (required)
    - joined_at           - Ngày vào làm (required)

2. Validation:
    - username unique: SELECT COUNT(*) FROM users WHERE username = ? = 0

3. Service (transaction):
    - INSERT users (username, password = bcrypt(?), role = 1, is_active = true)
    - INSERT teachers (user_id = users.id, full_name, phone, ...)

4. INSERT user_logs (action = 'create_teacher', description = 'admin X tạo GV Y lúc Z')
```

### Sửa Giáo viên

```
1. Admin chọn GV cần sửa
   → SELECT teachers.*, users.username, users.is_active, users.last_login_at
     FROM teachers
     JOIN users ON teachers.user_id = users.id
     WHERE teachers.id = ?

2. Admin sửa:
    - full_name, phone, email, address,
      bank_name, bank_account_number, bank_account_holder,
      status, joined_at
    - password (optional) — nếu nhập → reset password

3. Service:
    - UPDATE users SET password = bcrypt(?) WHERE id = user_id  (nếu có password mới)
    - UPDATE teachers SET full_name = ?, phone = ?, ... WHERE id = ?

4. INSERT user_logs (action = 'update_teacher', description = 'admin X sửa GV Y lúc Z')
```

### Trang chi tiết Giáo viên

```
Tab 1 — Thông tin cá nhân:
  → SELECT teachers.*, users.username, users.is_active, users.last_login_at
    FROM teachers
    JOIN users ON teachers.user_id = users.id
    WHERE teachers.id = ?

  Thông tin ngân hàng:
    - Admin xem: bank_name, bank_account_number, bank_account_holder (đầy đủ)
    - Chính GV xem: bank_account_number mask → ****1234

Tab 2 — Lớp đang dạy:
  Bảng lớp:
  → SELECT
      classes.id, classes.name, classes.code,
      subjects.name as ten_mon,
      classes.grade_level,
      classes.teacher_salary_per_session as luong_buoi,
      COUNT(ce.id) as si_so
    FROM classes
    JOIN subjects ON classes.subject_id = subjects.id
    LEFT JOIN class_enrollments ce ON ce.class_id = classes.id
      AND ce.left_at IS NULL
    WHERE classes.teacher_id = ?
      AND classes.status = Active
    GROUP BY classes.id, subjects.name

  Lịch dạy tổng hợp theo tuần:
  → SELECT
      cst.day_of_week, cst.start_time, cst.end_time,
      classes.name as ten_lop,
      rooms.name as phong
    FROM class_schedule_templates cst
    JOIN classes ON cst.class_id = classes.id
    JOIN rooms ON cst.room_id = rooms.id
    WHERE cst.teacher_id = ?
      AND (cst.end_date IS NULL OR cst.end_date >= today)
      AND classes.status = Active
    ORDER BY cst.day_of_week, cst.start_time

Tab 3 — Hiệu suất (KPI): (tính theo tháng hiện tại)

  Tổng số lớp đang dạy:
  → SELECT COUNT(*) FROM classes
    WHERE teacher_id = ? AND status = Active

  Tổng buổi dạy tháng:
  → SELECT COUNT(*) FROM attendance_sessions
    WHERE teacher_id = ?
      AND session_date BETWEEN [đầu tháng] AND [cuối tháng]
      AND status = completed

  Tỷ lệ chuyên cần TB các lớp:
  → SELECT
      ROUND(
        COUNT(*) FILTER (WHERE ar.status IN (present, late)) * 100.0
        / NULLIF(COUNT(*), 0), 1
      ) as ty_le
    FROM attendance_records ar
    JOIN attendance_sessions as_sess ON ar.session_id = as_sess.id
    WHERE as_sess.teacher_id = ?
      AND as_sess.session_date BETWEEN [đầu tháng] AND [cuối tháng]

  Tỷ lệ nộp báo cáo đúng hạn:
  → SELECT
      COUNT(*) FILTER (WHERE status != Draft) as da_nop,
      COUNT(*) as tong
    FROM monthly_reports
    WHERE teacher_id = ? AND month = 'YYYY-MM'
    (đúng hạn = submitted trước ngày deadline do trung tâm quy định)

  Tỷ lệ báo cáo được duyệt ngay:
  → SELECT
      COUNT(*) FILTER (WHERE status = Approved) as duyet_ngay,
      COUNT(*) FILTER (WHERE status IN (Approved, Rejected)) as da_review
    FROM monthly_reports
    WHERE teacher_id = ? AND month = 'YYYY-MM'

  Điểm TB toàn bộ lớp:
  → SELECT ROUND(AVG(sc.score), 2)
    FROM scores sc
    JOIN attendance_records ar ON sc.attendance_record_id = ar.id
    JOIN attendance_sessions as_sess ON ar.session_id = as_sess.id
    WHERE as_sess.teacher_id = ?
      AND as_sess.session_date BETWEEN [đầu tháng] AND [cuối tháng]

  Cảnh báo tự động:
    - Chưa nộp báo cáo tháng:
        SELECT COUNT(*) FROM monthly_reports
        WHERE teacher_id = ? AND month = 'YYYY-MM' AND status = Draft = 0
        → badge đỏ nếu đã qua deadline mà vẫn Draft
    - Tỷ lệ chuyên cần < 70% → cảnh báo vàng
```

---

## Nhân viên

### Danh sách

```
Tìm kiếm nhanh:
  - Mã NV   → WHERE users.id::text ILIKE '%keyword%'
               JOIN users ON staff.user_id = users.id
  - Họ tên  → OR staff.full_name ILIKE '%keyword%'
  - SĐT     → OR staff.phone ILIKE '%keyword%'

Bộ lọc:
  - Chức vụ              → WHERE staff.role_type = ?
  - Trạng thái nghiệp vụ → WHERE staff.status = ?
  - Trạng thái tài khoản → WHERE users.is_active = true/false
                             JOIN users ON staff.user_id = users.id

Hiển thị tổng → COUNT(staff.id)
Nút: [Xóa bộ lọc]

Bảng hiển thị:
  - Họ tên               → staff.full_name
  - SĐT                  → staff.phone
  - Chức vụ              → staff.role_type
  - Hình thức lương      → staff_salary_configs.salary_type
                            JOIN staff_salary_configs ON staff_id = staff.id
                            WHERE effective_to IS NULL  ← lấy config hiện hành
  - Mức lương            → staff_salary_configs.salary_amount (config hiện hành)
  - Trạng thái nghiệp vụ → staff.status
  - Trạng thái tài khoản → users.is_active
                            JOIN users ON staff.user_id = users.id
  - Action:
      + Xem chi tiết
      + Xem ca làm việc
      + Xem bảng lương
      + Chỉnh sửa
      + Khóa / Mở khóa tài khoản
```

### Tạo Nhân viên

```
1. Admin nhập:
    - full_name           - Họ và tên (required)
    - user_name           - Tên đăng nhập (required) — tự render vd: nv_nguyenvana
    - password            - Mật khẩu (required, min 8 ký tự...) — có nút random
    - phone               - Số điện thoại (required)
    - role_type           - Chức vụ StaffRoleType (required)
    - bank_name           - Tên ngân hàng (required)
    - bank_account_number - Số tài khoản (required)
    - bank_account_holder - Chủ tài khoản (required)
    - status              - Trạng thái EmployeeStatus (required)
    - joined_at           - Ngày vào làm (required)

2. Validation:
    - username unique: SELECT COUNT(*) FROM users WHERE username = ? = 0

3. Service (transaction):
    - INSERT users (username, password = bcrypt(?), role = 2, is_active = true)
    - INSERT staff (user_id = users.id, full_name, phone, role_type, ...)

4. INSERT user_logs (action = 'create_staff', description = 'admin X tạo NV Y lúc Z')
```

### Sửa Nhân viên

```
1. Admin chọn NV cần sửa
   → SELECT staff.*, users.username, users.is_active, users.last_login_at
     FROM staff
     JOIN users ON staff.user_id = users.id
     WHERE staff.id = ?

2. Admin sửa:
    - full_name, phone, role_type,
      bank_name, bank_account_number, bank_account_holder,
      status, joined_at
    - password (optional) — nếu nhập → reset password

3. Service:
    - UPDATE users SET password = bcrypt(?) WHERE id = user_id  (nếu có password mới)
    - UPDATE staff SET full_name = ?, phone = ?, ... WHERE id = ?

4. INSERT user_logs (action = 'update_staff', description = 'admin X sửa NV Y lúc Z')
```

### Trang chi tiết Nhân viên

```
Tab 1 — Thông tin cá nhân:
  → SELECT staff.*, users.username, users.is_active, users.last_login_at
    FROM staff
    JOIN users ON staff.user_id = users.id
    WHERE staff.id = ?

  Thông tin ngân hàng: chỉ Admin xem được (đầy đủ)

Tab 2 — Ca làm việc:
  Bộ lọc tháng (default: tháng hiện tại)

  → SELECT
      ss.shift_date,
      ss.check_in_time, ss.check_out_time,
      ss.total_hours,
      ss.hourly_rate_snapshot,
      ss.total_salary,
      ss.status,
      ss.note
    FROM staff_shifts ss
    WHERE ss.staff_id = ?
      AND ss.shift_date BETWEEN [đầu tháng] AND [cuối tháng]
    ORDER BY ss.shift_date

  Tổng giờ tháng + Tổng lương tạm tính:
  → SELECT SUM(total_hours), SUM(total_salary)
    FROM staff_shifts
    WHERE staff_id = ?
      AND shift_date BETWEEN [đầu tháng] AND [cuối tháng]
      AND status != cancelled

  Nút [Thêm ca thủ công]:
    Admin nhập: shift_date, check_in_time, check_out_time, note
    Hệ thống tự tính:
      total_hours = check_out - check_in
      hourly_rate_snapshot = (SELECT salary_amount FROM staff_salary_configs
                              WHERE staff_id = ? AND salary_type = hourly
                                AND effective_from <= shift_date
                                AND (effective_to IS NULL OR effective_to >= shift_date)
                              LIMIT 1)
      total_salary = total_hours * hourly_rate_snapshot
    INSERT staff_shifts
    INSERT user_logs (action = 'add_shift_manual')

Tab 3 — Lương:
  Link sang module Tài chính / Phiếu lương
  → Xem staff_salary_invoices WHERE staff_id = ?
```

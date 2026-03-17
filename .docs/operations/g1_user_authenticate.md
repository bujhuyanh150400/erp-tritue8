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
- Chỉ Admin được xem và sửa (`bank_account_number`, `bank_account_holder`).
- API trả về profile cho **chính GV** → mask: `****1234`.

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
| Học sinh chuyển khối (lên lớp)      | Cập nhật `students.grade_level`, không tạo user mới       |
| Đăng nhập sai nhiều lần             | Không yêu cầu trong spec, bỏ qua ở giai đoạn này          |

---

## Định nghĩa Role

```
UserRole:
  0 = Admin
  1 = Teacher
  2 = Staff
  3 = Student - Parent  ← dùng chung cho cả phụ huynh
```

> **Student/Parent dùng chung 1 tài khoản.**  
> Phụ huynh có 2 con → 2 tài khoản riêng, mỗi tài khoản gắn với 1 học sinh.

```
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

**Ràng buộc transaction:**  
Nếu bất kỳ bước nào lỗi → rollback toàn bộ.  
Không được tồn tại `users` mà không có profile tương ứng.

---

## Luồng Đăng nhập

```
Validate: 
    - username (required)
    - password (required, min: 8 ký tự, 1 chữ hoa, 1 chữ thường, 1 số, 1 ký tự đặc biệt)

Kiểm tra:
  1. username tồn tại
  2. password khớp (bcrypt verify)
  3. users.is_active = true

Nếu pass:
  - tạo session phía server (guard web)
  - user_logs: user X đăng nhập lúc Z
  - Redirect về dashboard

Nếu fail:
  - Trả về lỗi qua session flash (vd: "Tên đăng nhập hoặc mật khẩu không đúng")
  - Không phân biệt sai username hay sai password (bảo mật)
  - Nếu is_active = false → "Tài khoản đã bị khóa, liên hệ admin"
```

## Luồng Đăng xuất

```
POST /logout
  - Ghi user_logs: user X đăng xuất lúc Z
  - logout session web
  - hủy session phía server
  - regenerate CSRF token
  - Redirect về trang login
```

---

## Luồng Đổi mật khẩu

### Tự đổi (user đang đăng nhập)

```
POST /change-password
  ← old_password + new_password + confirm_password

Kiểm tra:
  1. old_password khớp với hash hiện tại
  2. new_password != old_password
  3. new_password đủ độ phức tạp (min 8 ký tự)
  4. confirm_password == new_password

Nếu pass:
  - Cập nhật password hash
```

### Admin reset mật khẩu

```
Trong các màn chỉnh sửa (teacher, staff, student)
- admin có thể tự nhập mật khẩu mới để đổi mật khẩu
- chi tiết nghiệp vụ sẽ được ghi lại trong các mô tả dưới

→ Admin **không được xem** mật khẩu hiện tại — chỉ sinh mới.
```

---

## Luồng Khóa / Mở khóa tài khoản

```
Admin bấm "Khóa tài khoản"

Kiểm tra:
  → Không được tự khóa tài khoản của chính mình

Nếu pass:
  → users.is_active = false
  → DELETE sessions WHERE user_id = ?  ← kick user ra ngay lập tức
  → Ghi user_logs: ai khóa, lúc nào

Admin bấm "Mở khóa"
  → users.is_active = true
  → Ghi user_logs
```

---

## Học sinh

### Danh sách

```
Tìm kiếm nhanh (full-text):
  - Họ tên học sinh
  - Mã học sinh (user.id)
  - SĐT phụ huynh
 
Bộ lọc:
  - Tháng              — lọc dữ liệu báo cáo theo tháng
  - Khối               — grade_level
  - Lớp                — class_id
  - Môn học            — subject_id
  - Giáo viên          — teacher_id
  - Tình trạng báo cáo: Tất cả | Chưa nộp | Đã nộp chờ duyệt | Đã duyệt
  - Trạng thái tài khoản: Tất cả | Hoạt động | Đã khóa
 
Hiển thị tổng số học sinh tìm thấy
Nút: [Xóa bộ lọc]

Bảng hiển thị:
  - Học sinh: chứa - id, tên, ngày sinh
  - Giới tính
  - Khối
  - Môn đang học (triển khai sau)
  - Phụ huynh: chứa - tên, SĐT
  - Tình trạng tài khoản
  - Số sao
  - Tình trạng báo cáo
  - Action
      + Xem chi tiết
      + Chỉnh sửa thông tin
      + Xem lịch sử đổi thưởng (popup)
      + Khóa / Mở khóa tài khoản

Lưu ý:
  - "Môn đang học" hiển thị dạng tag vì 1 HS học nhiều môn
    vd: [Toán 8] [Văn 8] [Anh 8]
  - "Số sao" = tổng reward_points hiện tại
  - "Tình trạng báo cáo" theo tháng đang lọc
  - "Tài khoản" hiển thị: Hoạt động / Đã khóa
```

### Tạo Học sinh

```
1. Admin nhập: 
    - full_name        - Họ và tên đầy đủ (required)
    - user_name        - Tên đăng nhập (required) - tự render vd: hs_nguyenvana
    - password         - Mật khẩu (required, min: 8 ký tự, 1 chữ hoa, 1 chữ thường,
                         1 số, 1 ký tự đặc biệt) — có nút random để tạo password tạm thời
    - dob              - Ngày sinh (required)
    - gender           - Giới tính Gender (required)
    - grade_level      - Khối GradeLevel (required)
    - parent_name      - Tên bố mẹ (required)
    - parent_phone     - Số điện thoại bố mẹ (required)
    - address          - Địa chỉ (required)
    - note             - Ghi chú (optional)

2. Service:
    - Tạo user (role = UserRole.Student, username và password)
    - Tạo student (user_id = users.id)

3. Ghi user_logs: admin X tạo tài khoản học sinh Y lúc Z
```

### Sửa Học sinh

```
1. Admin chọn học sinh cần sửa

2. Admin sửa lại các thông tin: 
    - full_name        - Họ và tên đầy đủ (required)
    - password         - Mật khẩu mới (optional) — có nút random
                         Nếu nhập → reset password thành password mới
    - dob              - Ngày sinh (required)
    - gender           - Giới tính Gender (required)
    - grade_level      - Khối GradeLevel (required)
    - parent_name      - Tên bố mẹ (required)
    - parent_phone     - Số điện thoại bố mẹ (required)
    - address          - Địa chỉ (required)
    - note             - Ghi chú (optional)

3. Service:
    - Tìm student theo user_id
    - update password nếu có nhập mới
    - Update student

4. Ghi user_logs: admin X sửa tài khoản học sinh Y lúc Z
```

### Trang chi tiết

```
Tab 1 — Thông tin cá nhân:
  Họ tên, ngày sinh, giới tính, khối, địa chỉ
  Tên PH, SĐT PH, Zalo ID, ghi chú
  Tài khoản: username, trạng thái tài khoản
 
Tab 2 — Báo cáo theo môn:
  Mỗi môn là 1 block độc lập, gồm:
    - Ngày đăng ký môn + giáo viên phụ trách
    - Thống kê tháng: tổng buổi | buổi có mặt | tỷ lệ | điểm TB
    - Bảng điểm: Ngày | Tên bài | Điểm | Ghi chú
    - Nhận xét GV tháng + trạng thái báo cáo (Chưa nộp / Chờ duyệt / Đã duyệt)
 
Tab 3 — Sao thưởng:
  Tổng sao hiện tại
  Lịch sử: Ngày | Loại (cộng/trừ) | Số sao | Lý do
  Lịch sử đổi thưởng: Ngày | Phần thưởng | Sao đã dùng
```

---

## Giáo viên

### Danh sách

```
Tìm kiếm nhanh:
  - Mã giáo viên (user.id)
  - Họ tên giáo viên
  - Số điện thoại
 
Bộ lọc:
  - Trạng thái nghiệp vụ: EmployeeStatus
  - Trạng thái tài khoản: Tất cả | Hoạt động | Đã khóa
  - Môn dạy — subject_id
 
Hiển thị tổng số giáo viên tìm thấy
Nút: [Xóa bộ lọc]

Bảng hiển thị:
  - Họ tên
  - SĐT
  - Môn dạy
  - Số lớp đang dạy
  - Trạng thái nghiệp vụ
  - Trạng thái tài khoản
  - Action
      + Xem chi tiết
      + Xem lịch dạy
      + Xem bảng lương
      + Chỉnh sửa
      + Khóa / Mở khóa tài khoản
```

### Tạo Giáo viên

```
1. Admin nhập: 
    - full_name            - Họ và tên đầy đủ (required)
    - user_name            - Tên đăng nhập (required) - tự render vd: gv_nguyenvana
    - password             - Mật khẩu (required, min: 8 ký tự, 1 chữ hoa, 1 chữ thường,
                             1 số, 1 ký tự đặc biệt) — có nút random
    - phone                - Số điện thoại (required)
    - email                - Email (required)
    - address              - Địa chỉ (required)
    - bank_name            - Tên ngân hàng (required)
    - bank_account_number  - Số tài khoản ngân hàng (required)
    - bank_account_holder  - Chủ tài khoản ngân hàng (required)
    - status               - Trạng thái EmployeeStatus (required)
    - joined_at            - Ngày vào làm (required)

2. Service:
    - Tạo user (role = UserRole.Teacher, username và password)
    - Tạo teacher (user_id = users.id)

3. Ghi user_logs: admin X tạo tài khoản giáo viên Y lúc Z
```

### Sửa Giáo viên

```
1. Admin chọn giáo viên cần sửa

2. Admin sửa lại các thông tin:
    - full_name            - Họ và tên đầy đủ (required)
    - password             - Mật khẩu mới (optional) — có nút random
                             Nếu nhập → reset password thành password mới
    - phone                - Số điện thoại (required)
    - email                - Email (required)
    - address              - Địa chỉ (required)
    - bank_name            - Tên ngân hàng (required)
    - bank_account_number  - Số tài khoản ngân hàng (required)
    - bank_account_holder  - Chủ tài khoản ngân hàng (required)
    - status               - Trạng thái EmployeeStatus (required)
    - joined_at            - Ngày vào làm (required)

3. Service:
    - Tìm teacher theo user_id
    - update password nếu có nhập mới
    - Update teacher

4. Ghi user_logs: admin X sửa tài khoản giáo viên Y lúc Z
```

### Trang chi tiết

```
Tab 1 — Thông tin cá nhân:
  Họ tên, SĐT, email, địa chỉ, ngày vào làm
  Thông tin ngân hàng:
    - Admin xem: đầy đủ
    - Chính GV xem: mask số TK → ****1234
  Tài khoản: username, trạng thái, last_login_at
 
Tab 2 — Lớp đang dạy:
  Bảng: Lớp | Môn | Khối | Lịch học | Lương/buổi | Sĩ số
  Lịch dạy tổng hợp theo tuần:
    Thứ 2: 17:00–19:00 | Toán 8A | Phòng 101
    Thứ 4: 18:00–20:00 | Toán 9B | Phòng 202
 
Tab 3 — Hiệu suất (KPI):
  - Tổng số lớp đang dạy
  - Tổng buổi dạy tháng hiện tại
  - Tỷ lệ chuyên cần TB các lớp
  - Tỷ lệ nộp báo cáo đúng hạn
  - Tỷ lệ báo cáo được duyệt ngay (không bị yêu cầu chỉnh sửa)
  - Điểm TB toàn bộ lớp
 
  Cảnh báo tự động:
    - Chưa nộp báo cáo tháng → badge đỏ
    - Tỷ lệ chuyên cần < 70% → cảnh báo vàng
```

---

## Nhân viên

### Danh sách

```
Tìm kiếm nhanh:
  - Mã nhân viên (user.id)
  - Họ tên nhân viên
  - Số điện thoại

Bộ lọc:
  - Chức vụ (role_type): Tất cả | Lễ tân
  - Trạng thái nghiệp vụ: EmployeeStatus
  - Trạng thái tài khoản: Tất cả | Hoạt động | Đã khóa

Hiển thị tổng số nhân viên tìm thấy
Nút: [Xóa bộ lọc]

Bảng hiển thị:
  - Họ tên
  - SĐT
  - Chức vụ (role_type)
  - Hình thức lương (salary_type: theo giờ / cố định)
  - Mức lương (salary_amount hiện hành)
  - Trạng thái nghiệp vụ
  - Trạng thái tài khoản
  - Action
      + Xem chi tiết
      + Xem ca làm việc
      + Xem bảng lương
      + Chỉnh sửa
      + Khóa / Mở khóa tài khoản
```

### Tạo Nhân viên

```
1. Admin nhập:
    - full_name            - Họ và tên đầy đủ (required)
    - user_name            - Tên đăng nhập (required) - tự render vd: nv_nguyenvana
    - password             - Mật khẩu (required, min: 8 ký tự, 1 chữ hoa, 1 chữ thường,
                             1 số, 1 ký tự đặc biệt) — có nút random
    - phone                - Số điện thoại (required)
    - role_type            - Chức vụ StaffRoleType (required)
    - bank_name            - Tên ngân hàng (required)
    - bank_account_number  - Số tài khoản ngân hàng (required)
    - bank_account_holder  - Chủ tài khoản ngân hàng (required)
    - status               - Trạng thái EmployeeStatus (required)
    - joined_at            - Ngày vào làm (required)

2. Service:
    - Tạo user (role = UserRole.Staff, username và password)
    - Tạo staff (user_id = users.id, role_type)

3. Ghi user_logs: admin X tạo tài khoản nhân viên Y lúc Z
```

### Sửa Nhân viên

```
1. Admin chọn nhân viên cần sửa

2. Admin sửa lại các thông tin:
    - full_name            - Họ và tên đầy đủ (required)
    - password             - Mật khẩu mới (optional) — có nút random
                             Nếu nhập → reset password thành password mới
    - phone                - Số điện thoại (required)
    - role_type            - Chức vụ StaffRoleType (required)
    - bank_name            - Tên ngân hàng (required)
    - bank_account_number  - Số tài khoản ngân hàng (required)
    - bank_account_holder  - Chủ tài khoản ngân hàng (required)
    - status               - Trạng thái EmployeeStatus (required)
    - joined_at            - Ngày vào làm (required)

3. Service:
    - Tìm staff theo user_id
    - update password nếu có nhập mới
    - Update staff

4. Ghi user_logs: admin X sửa tài khoản nhân viên Y lúc Z
```

### Trang chi tiết

```
Tab 1 — Thông tin cá nhân:
  Họ tên, SĐT, chức vụ, ngày vào làm
  Thông tin ngân hàng (chỉ Admin xem đầy đủ)
  Tài khoản: username, trạng thái, last_login_at

Tab 2 — Ca làm việc:
  Bộ lọc: tháng
  Bảng: Ngày | Giờ vào | Giờ ra | Tổng giờ | Đơn giá | Thành tiền | Trạng thái
  Tổng giờ tháng + Tổng lương tạm tính hiển thị cuối bảng
  Nút [Thêm ca thủ công] — dành cho trường hợp quên chấm công

Tab 3 — Lương:
  Link sang module Tài chính / Phiếu lương
```

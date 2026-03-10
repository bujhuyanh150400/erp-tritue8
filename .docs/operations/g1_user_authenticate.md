# Phân tích nghiệp vụ — G1: User & Authentication

---

## 1. Tổng quan

`users` là bảng **auth duy nhất** — mọi role đều đăng nhập qua đây.  
`students`, `teachers`, `staff` là bảng **profile** — lưu thông tin nghiệp vụ, không phải thông tin đăng nhập.

Quan hệ:
```
users (1) ──── (0..1) teachers
users (1) ──── (0..1) staff
users (1) ──── (0..1) students
```

---

## 2. Định nghĩa Role

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
```

---

## 3. Luồng Đăng nhập

```
POST /login

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
  - Redirect về trang tương ứng theo role

Nếu fail:
  - Trả về lỗi qua session flash (vd: "Tên đăng nhập hoặc mật khẩu không đúng")
  - Không phân biệt sai username hay sai password (bảo mật)
  - Nếu is_active = false → "Tài khoản đã bị khóa, liên hệ admin"
```

---

## 4. Luồng Đăng xuất

```
POST /logout
  - Ghi user_logs: user X đăng xuất lúc Z
  - logout session web
  - hủy session phía server
  - regenerate CSRF token
  - Redirect về trang login
```

---

## 5. Luồng Tạo tài khoản (Admin thực hiện)

> Không có tự đăng ký. Mọi tài khoản đều do Admin tạo.

### 5.1 Tạo Giáo viên

```
1. Admin nhập: 
    - full_name - Họ và tên đầy đủ (required)
    - phone - Số điện thoại (required)
    - email - Email (required)
    - address - Địa chỉ (required)
    - bank_name - Tên ngân hàng (required)
    - bank_account_number - Số tài khoản ngân hàng  (required)
    - bank_account_holder - Chủ tài khoản ngân hàng (required)
    - status - Trạng thái StudentStatus

2. Hệ thống gợi ý username tự động (vd: gv_nguyenvana)
3. Hệ thống sinh password tạm thời
4. Transaction:
   INSERT users  (role = 1, password_changed_at = NULL)
   INSERT teachers (user_id = ...)
5. Thông báo lại thông tin đăng nhập qua response (username, password tạm thời)
6. Ghi user_logs: admin X tạo tài khoản giáo viên Y lúc Z
```

### 5.2 Tạo Học sinh

```
1. Admin nhập: 
    - full_name - Họ và tên đầy đủ (required)
    - dob (date) - Ngày sinh (required)
    - gender  - Giới tính Gender (required)
    - grade_level - Học kỳ GradeLevel (required)
    - parent_name  - Tên bố mẹ (required)
    - parent_phone  - Số điện thoại bố mẹ (required)
    - address - Địa chỉ (required)
    - note - Ghi chú (optional)

2. Hệ thống gợi ý username tự động (vd: hs_nguyenvana)
3. Hệ thống sinh password tạm thời
4. Transaction:
   INSERT users  (role = 3, password_changed_at = NULL)
   INSERT students (user_id = ...)
5. Thông báo lại thông tin đăng nhập qua response (username, password tạm thời)
6. Ghi user_logs: admin X tạo tài khoản học sinh Y lúc Z
```

### 5.3 Tạo Nhân viên (Lễ tân)

```
1. Admin nhập: họ tên, SĐT, ngày vào làm
2. Hệ thống gợi ý username tự động (vd: emp_nguyenvana) và password tạm thời
3. Transaction:
   INSERT users  (role = 2, password_changed_at = NULL)
   INSERT staff  (user_id = ..., role_type = 0)
3. Thông báo lại thông tin đăng nhập qua response (username, password tạm thời)
4. Ghi user_logs: admin X tạo tài khoản nhân viên Y lúc Z
```

**Ràng buộc transaction:**  
Nếu bất kỳ bước nào lỗi → rollback toàn bộ.  
Không được tồn tại `users` mà không có profile tương ứng.

---

## 6. Luồng Đổi mật khẩu

### 6.1 Tự đổi (user đang đăng nhập)

```
POST /change-password
  ← old_password + new_password + confirm_password

Kiểm tra:
  1. old_password khớp với hash hiện tại
  2. new_password != old_password
  3. new_password đủ độ phức tạp (min 8 ký tự)
  4. confirm_password == new_password

Nếu pass:
  → Cập nhật password hash
  → Cập nhật password_changed_at = now()
```

### 6.2 Admin reset mật khẩu

```
Admin bấm "Reset mật khẩu" tại trang user

→ Hệ thống sinh password tạm mới
→ Cập nhật password hash
→ SET password_changed_at = NULL  ← đánh dấu là mật khẩu tạm
→ Revoke toàn bộ token của user đó
→ Ghi user_logs: admin X reset mật khẩu user Y lúc Z
```

> Admin **không được xem** mật khẩu hiện tại — chỉ sinh mới.

---

## 7. Luồng Khóa / Mở khóa tài khoản

```
Admin bấm "Khóa tài khoản"

Kiểm tra:
  → Không được tự khóa tài khoản của chính mình

Nếu pass:
  → users.is_active = false
  → Revoke toàn bộ token của user đó (đăng xuất ngay lập tức)
  → Ghi user_logs: ai khóa, lúc nào

Admin bấm "Mở khóa"
  → users.is_active = true
  → Ghi user_logs
```

---

## 8. Trạng thái tài khoản — 2 tầng độc lập

| Tầng | Cột | Ý nghĩa |
|---|---|---|
| Auth | `users.is_active` | Có được đăng nhập không |
| Nghiệp vụ | `teachers.status` / `staff.status` | Có đang hoạt động trong hệ thống không |

Ví dụ minh họa:

| Tình huống | users.is_active | teachers.status |
|---|---|---|
| GV đang dạy bình thường | true | active |
| GV nghỉ thai sản | true | inactive — vẫn đăng nhập xem lịch sử được |
| GV nghỉ việc hẳn | false | terminated — không đăng nhập được, dữ liệu giữ nguyên |

---

## 9. Bảo mật dữ liệu nhạy cảm

### Thông tin ngân hàng
- Chỉ Admin được xem và sửa (`bank_account_number`, `bank_account_holder`).
- API trả về profile cho **chính GV** → mask: `****1234`.
- Mỗi lần Admin xem/sửa thông tin ngân hàng → ghi `user_logs`.

### Password
- Lưu dạng bcrypt hash, không bao giờ trả về trong response.
- `password_changed_at = NULL` → tài khoản đang dùng mật khẩu tạm → hiển thị cảnh báo trên UI.


## 10. Các edge case cần xử lý

| Tình huống | Xử lý |
|---|---|
| Transaction tạo GV lỗi giữa chừng | Rollback toàn bộ |
| Username bị trùng | Báo lỗi ngay khi nhập, gợi ý username khác |
| Admin tự khóa mình | Chặn ở tầng backend, không phụ thuộc UI |
| GV nghỉ việc còn buổi dạy tương lai | Cảnh báo trước khi khóa, admin phải xử lý lịch dạy trước |
| Học sinh chuyển khối (lên lớp) | Cập nhật `students.grade_level`, không tạo user mới |
| Đăng nhập sai nhiều lần | Không yêu cầu trong spec, bỏ qua ở giai đoạn này |

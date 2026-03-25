# ERP TriTue8 - Project Structure & Business Flow Instructions

## 1) Muc tieu tai lieu
Tai lieu nay tong hop nhanh:
- Cau truc source code hien tai.
- Kien truc layer va vai tro tung thanh phan.
- Luong nghiep vu (business flow) theo module G1 -> G6.
- Thu tu trien khai/deploy va quy tac van hanh khi phat trien tinh nang moi.

## 2) Tong quan cong nghe
- Backend: Laravel (PHP).
- Admin UI: Filament v5 (route `/` redirect vao Filament Dashboard).
- DB: PostgreSQL (theo `.docs/database.md`).
- Frontend asset: Vite + resources (`resources/css`, `resources/js`, `resources/views`).

## 3) Project structure (thuc te trong repo)

### 3.1 Thu muc goc
- `app/`: domain code chinh.
- `bootstrap/`, `config/`, `public/`, `storage/`, `tests/`, `vendor/`: cau truc Laravel chuan.
- `database/migrations/`: migration theo module nghiep vu.
- `routes/web.php`: route web, hien tai redirect dashboard Filament.
- `.docs/`: tai lieu nghiep vu va database.

### 3.2 Cau truc `app/`
- `app/Models`: Eloquent model theo bang DB (User, Student, Teacher, SchoolClass, ScheduleInstance, TuitionInvoice, ...).
- `app/Constants`: enum-like constants (UserRole, ClassStatus, ScheduleType, InvoiceStatus, ...).
- `app/Repositories`: truy cap du lieu theo repository pattern.
- `app/Services`: xu ly business logic theo tung module (AuthService, StudentService, ClassService, ...).
- `app/Filament/Resources`: CRUD/UI layer cho admin theo module:
  - `Students`, `Teachers`, `Staff`, `Subjects`, `Rooms`, `Classes`.
- `app/Interface` + `app/Core`: abstraction/base classes, helper, request, traits, logs.

### 3.3 Kien truc layer de lam viec
Flow khuyen nghi khi phat trien:
1. Filament Resource/Page (UI action)
2. Service (rule nghiep vu)
3. Repository (query/transaction)
4. Model + DB

Khong nen dat query nghiep vu phuc tap truc tiep trong Resource/Page.

## 4) Mapping migration theo module nghiep vu
- G1 Auth & Profile: `users`, `students`, `teachers`, `staff`.
- G2 Hoc vu: `subjects`, `rooms`, `classes`, `class_enrollments`, `monthly_reports`.
- G3 Lich hoc: `class_schedule_templates`, `schedule_instances`, `schedule_change_requests`.
- G4 Diem danh & Diem so: `attendance_sessions`, `attendance_records`, `scores`, `reward_points`, `reward_items`, `reward_redemptions`.
- G5 Tai chinh - Nhan su: `tuition_invoices`, `tuition_invoice_logs`, `teacher_salary_*`, `staff_salary_*`, `expense_*`.
- G6 He thong: `user_logs`, `notifications`.

## 5) Business flow instructions (tong hop)

## 5.1 G1 - Auth, User, Profile
Flow chinh:
1. Tao user dang nhap trong `users` (username unique, password hash, role).
2. Tao profile tuong ung theo role (`students`/`teachers`/`staff`) trong cung transaction.
3. Login chi check `users` + `is_active`.
4. Khoa/mo khoa tai khoan qua `users.is_active`; co log `user_logs`.

Quy tac:
- `users` la auth table duy nhat.
- Profile table khong thay the auth.
- Moi thao tac quan trong phai ghi `user_logs`.

## 5.2 G2 - Hoc vu (mon, phong, lop, dang ky)
Flow chinh:
1. Tao mon hoc (`subjects`) va phong hoc (`rooms`).
2. Tao lop (`classes`) gan mon + giao vien + hoc phi/luong co ban.
3. Them hoc sinh vao lop qua `class_enrollments` (co `enrolled_at`, `left_at`, fee override theo giai doan).
4. Quan ly bao cao thang giao vien theo `monthly_reports`.

Quy tac:
- Han che xoa cung: uu tien deactivate/status change.
- `class_enrollments` la nguon su that cho lich su hoc cua hoc sinh trong lop.

## 5.3 G3 - Lich hoc
Flow chuan:
1. Tao lich co dinh trong `class_schedule_templates`.
2. Sinh buoi hoc thuc te vao `schedule_instances`.
3. Dieu phoi thay doi lich (huy, doi phong, doi giao vien, hoc bu, extra) tren `schedule_instances`.
4. Giao vien gui de xuat doi lich qua `schedule_change_requests`, Admin duyet/tu choi.

Quy tac:
- `schedule_instances` la bang trung tam de join voi diem danh, hoc phi, luong.
- Kiem tra xung dot lich: phong, giao vien (hard block), hoc sinh (canh bao theo rule).

## 5.4 G4 - Diem danh, diem so, thuong
Flow chuan:
1. Moi `schedule_instance` co the tao 1 `attendance_session`.
2. Trong session, tao `attendance_records` cho tung hoc sinh.
3. Nhap diem qua `scores` gan voi tung `attendance_record`.
4. Cong/tru sao qua `reward_points`, doi qua `reward_redemptions` + `reward_items`.

Quy tac:
- Diem danh phai theo buoi hoc thuc te (khong tach roi schedule).
- Logic tinh hoc phi/bao cao can dua vao trang thai diem danh + `is_fee_counted`.

## 5.5 G5 - Tai chinh va luong
Flow chuan:
1. Hoc phi hoc sinh: sinh `tuition_invoices` theo thang (student + class + month unique).
2. Luong giao vien: `teacher_salary_invoices` theo thang.
3. Luong nhan vien: `staff_salary_invoices` theo thang, input tu shift/config.
4. Moi bien dong thanh toan ghi vao bang `*_invoice_logs`.
5. Quan ly chi phi van hanh qua `expense_categories`, `expense_invoices`.

Quy tac:
- Sau khi chot thang (`is_locked = true`), khong sua invoice truc tiep.
- Dieu chinh thanh toan qua log de audit duoc.

## 5.6 G6 - He thong
Flow chinh:
1. Luu audit action user trong `user_logs`.
2. Luu va gui thong bao qua `notifications`.

Quy tac:
- Tinh nang tac dong du lieu quan trong phai co log.
- Notification nen gan reference den nghiep vu (morph/reference).

## 6) Quy trinh dev de giu dung architecture
Khi them/chinh sua tinh nang:
1. Cap nhat/tao migration (neu doi schema).
2. Cap nhat Model + relation + casts.
3. Them/sua Repository query.
4. Them/sua Service cho business rule.
5. Noi vao Filament Resource/Page/Action.
6. Ghi `user_logs` cho action quan trong.
7. Bo sung test (`tests/`) cho rule quan trong.

## 7) Uu tien mo rong tiep theo
Theo hien trang code va docs, uu tien tiep theo nen la:
1. Hoan thien Filament Resource cho cac module G3-G6 (neu chua day du giao dien).
2. Chuan hoa transaction + validation xung dot lich trong Service.
3. Bo sung test integration cho luong: dang ky hoc sinh -> lich hoc -> diem danh -> tao invoice.

---
Nguon tong hop:
- `.docs/database.md`
- `.docs/operations/g1_user_authenticate.md`
- `.docs/operations/g2_hoc_vu.md`
- `.docs/operations/g3_lich_hoc.md`
- Cau truc source hien tai trong `app/`, `database/migrations/`, `routes/`.

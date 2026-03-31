# Phân tích nghiệp vụ — G5: Tài chính

Bao gồm: Học phí, Lương giáo viên, Lương nhân viên, Chi phí vận hành, Công nợ, Báo cáo tài chính

---

## Tổng quan

G5 là module tài chính tổng hợp. Dữ liệu đầu vào đến từ G4 (điểm danh) và G3 (lịch dạy), đầu ra là các hóa đơn và báo cáo.

Thứ tự phụ thuộc:
```
attendance_sessions (G4) — căn cứ tính học phí + lương GV
        │
        ▼
tuition_invoices      — học phí từng HS từng lớp từng tháng
teacher_salary_invoices — lương GV từng lớp từng tháng
staff_salary_invoices — lương NV từng tháng
expense_invoices      — chi phí vận hành
```

### Quy tắc hệ thống

| Quy tắc | Chi tiết |
|---|---|
| 1 HS + 1 lớp + 1 tháng = 1 hóa đơn học phí | unique(student_id, class_id, month) |
| 1 GV + 1 lớp + 1 tháng = 1 hóa đơn lương | unique(teacher_id, class_id, month) |
| 1 NV + 1 tháng = 1 hóa đơn lương | unique(staff_id, month) |
| Hóa đơn đã lock → không sửa | is_locked = true |
| Không xóa thanh toán | Chỉ đánh dấu is_cancelled = true |
| Lương từ phiếu lương tự động tạo expense | Không nhập tay chi phí lương |

### Các edge case cần xử lý

| Tình huống | Xử lý |
|---|---|
| HS học 2 lớp | 2 hóa đơn riêng biệt, lọc theo giáo viên phải lọc đúng lớp |
| GV dạy thay buổi nào đó | custom_salary trong schedule_instances, không dùng salary_config |
| Đổi học phí giữa tháng | Tính theo giai đoạn fee_effective_from/to |
| HS nghỉ giữa tháng | Chỉ tính buổi có is_fee_counted = true trước left_at |
| Hủy thanh toán nhầm | is_cancelled = true, cập nhật lại paid_amount, không xóa record |
| Chốt tháng rồi phát hiện sai | Cần quyền Admin để mở khóa |

---

## Định nghĩa Enum

```
InvoiceStatus:
  0 = unpaid    — chưa thanh toán
  1 = partial   — thanh toán một phần
  2 = paid      — đã thanh toán đủ
  3 = cancelled — đã hủy

PaymentMethod:
  0 = cash          — tiền mặt
  1 = bank_transfer — chuyển khoản

SalaryType:
  0 = hourly — theo giờ (lễ tân)
  1 = fixed  — cố định tháng

ShiftStatus:
  0 = open      — ca đang mở (chưa check-out)
  1 = confirmed — đã xác nhận
  2 = locked    — đã chốt lương tháng
```

---

## Học phí

### Danh sách hóa đơn học phí

```
Bộ lọc:
  - Tháng (required, default: tháng hiện tại)
    → WHERE ti.month = 'YYYY-MM'
  - Trạng thái: Tất cả | Chưa thanh toán | Đã thanh toán | Thanh toán một phần
    → WHERE ti.status = ?
  - Lớp      → WHERE ti.class_id = ?
  - Giáo viên→ WHERE classes.teacher_id = ?
               JOIN classes ON ti.class_id = classes.id
    Lưu ý: Lọc theo GV chỉ lấy hóa đơn của lớp do GV đó dạy
            KHÔNG cộng lớp của GV khác dù cùng học sinh
  - Khối     → WHERE classes.grade_level = ?
  - Phương thức thanh toán:
    → WHERE EXISTS (
        SELECT 1 FROM tuition_invoice_logs
        WHERE invoice_id = ti.id
          AND is_cancelled = false
          AND payment_method = ?
      )

Hiển thị tổng hợp đầu trang:
  → SELECT
      SUM(ti.total_study_fee)   as tong_hoc_phi_thang,
      SUM(ti.previous_debt)     as tong_no_cu,
      SUM(ti.total_amount - ti.paid_amount) as tong_phai_thu
    FROM tuition_invoices ti
    [+ bộ lọc hiện tại]

Bảng hiển thị:
  - Số HĐ         → ti.invoice_number
  - Học sinh       → students.full_name
                      JOIN students ON ti.student_id = students.id
  - Lớp            → classes.name
                      JOIN classes ON ti.class_id = classes.id
  - Tháng          → ti.month
  - Tổng buổi      → ti.total_sessions
  - Buổi có mặt    → ti.attended_sessions
  - Học phí        → ti.total_study_fee
  - Giảm trừ       → ti.discount_amount
  - Nợ cũ          → ti.previous_debt
  - Tổng phải thu  → ti.total_amount
  - Đã thanh toán  → ti.paid_amount
  - Còn lại        → ti.total_amount - ti.paid_amount
  - Trạng thái     → ti.status
  - Số lần xuất    → ti.export_count (nếu có)
  - Action:
      + Xem chi tiết
      + Thanh toán
      + Chỉnh sửa (discount, ghi chú)
      + Nhắc đóng học phí
      + Gửi phiếu thu
      + Xuất PDF
```

### Tạo hóa đơn học phí tháng

```
Admin bấm "Tạo hóa đơn tháng [MM/YYYY]"

Điều kiện:
  - Tháng đó đã có attendance_sessions locked
    → SELECT COUNT(*) FROM attendance_sessions
      WHERE class_id = ? AND EXTRACT(MONTH FROM session_date) = ?
        AND status = locked

Với mỗi học sinh trong mỗi lớp, tính:

  total_sessions:
    → SELECT COUNT(DISTINCT si.id)
      FROM schedule_instances si
      JOIN attendance_sessions as_sess ON as_sess.schedule_instance_id = si.id
      WHERE si.class_id = ?
        AND EXTRACT(MONTH FROM si.date) = ?
        AND si.status != cancelled

  attended_sessions (buổi tính tiền):
    → SELECT COUNT(ar.id)
      FROM attendance_records ar
      JOIN attendance_sessions as_sess ON ar.session_id = as_sess.id
      JOIN schedule_instances si ON as_sess.schedule_instance_id = si.id
      WHERE ar.student_id = ?
        AND si.class_id = ?
        AND ar.is_fee_counted = true
        AND EXTRACT(MONTH FROM as_sess.session_date) = ?

  total_study_fee (tính theo giai đoạn học phí):
    → Duyệt từng buổi có is_fee_counted = true
      Mỗi buổi ngày X:
        SELECT COALESCE(ce.fee_per_session, classes.base_fee_per_session)
        FROM class_enrollments ce
        JOIN classes ON ce.class_id = classes.id
        WHERE ce.class_id = ? AND ce.student_id = ?
          AND ce.fee_effective_from <= X
          AND (ce.fee_effective_to IS NULL OR ce.fee_effective_to >= X)
        LIMIT 1
      → Cộng dồn từng buổi

  discount_amount:
    → SELECT SUM(ri.discount_amount)
      FROM reward_redemptions rr
      JOIN reward_items ri ON rr.reward_item_id = ri.id
      WHERE rr.student_id = ?
        AND ri.reward_type = discount
        AND rr.invoice_id IS NULL  ← chưa gắn vào hóa đơn nào
        AND EXTRACT(MONTH FROM rr.redeemed_at) = ?

  previous_debt:
    → Lấy từ hóa đơn tháng trước:
      SELECT (total_amount - paid_amount) as no_cu
      FROM tuition_invoices
      WHERE student_id = ? AND class_id = ?
        AND month = 'YYYY-MM trước'
      → Nếu âm (đã trả dư) → 0

  total_amount = total_study_fee - discount_amount + previous_debt

Service:
  INSERT tuition_invoices (
    invoice_number = [sinh tự động: HP-YYYYMM-XXXX],
    student_id, class_id, month,
    total_sessions, attended_sessions,
    total_study_fee, discount_amount, previous_debt,
    total_amount,
    paid_amount = 0,
    status = unpaid,
    is_locked = false
  )

  UPDATE reward_redemptions SET invoice_id = ti.id
  WHERE student_id = ? AND invoice_id IS NULL
    AND reward_type = discount
    AND EXTRACT(MONTH FROM redeemed_at) = ?

Ghi user_logs: admin X tạo hóa đơn học phí tháng MM/YYYY lúc Z
```

### Thanh toán học phí

```
Admin bấm "Thanh toán" tại dòng hóa đơn

Mở popup:
  Hiển thị:
    - Tên HS, lớp, tháng
    - Tổng phải thu: ti.total_amount
    - Đã thanh toán: ti.paid_amount
    - Còn lại: ti.total_amount - ti.paid_amount

  Form:
    - amount          (required) — số tiền thanh toán lần này
    - payment_method  (required) — cash / bank_transfer
    - note            (optional)

  Validation:
    - amount > 0
    - amount <= (ti.total_amount - ti.paid_amount)
    - ti.is_locked = false

Service (transaction):
  1. INSERT tuition_invoice_logs (
       invoice_id,
       amount,
       paid_at = now(),
       note,
       is_cancelled = false,
       changed_by = Auth::id(),
       payment_method
     )

  2. UPDATE tuition_invoices
       SET paid_amount = paid_amount + amount,
           status = CASE
             WHEN paid_amount + amount >= total_amount THEN paid
             ELSE partial
           END
       WHERE id = ?

Ghi user_logs: admin X ghi nhận thanh toán HĐ Y số tiền Z lúc T
```

### Hủy thanh toán

```
Admin chọn lần thanh toán cụ thể trong lịch sử → bấm "Hủy"

Mở popup xác nhận:
  - Hiển thị: số tiền, ngày thanh toán
  - Form: cancel_reason (required)

Service (transaction):
  1. UPDATE tuition_invoice_logs
       SET is_cancelled = true,
           cancelled_at = now(),
           cancel_reason = ?
       WHERE id = ?

  2. UPDATE tuition_invoices
       SET paid_amount = paid_amount - log.amount,
           status = CASE
             WHEN paid_amount - log.amount <= 0 THEN unpaid
             ELSE partial
           END
       WHERE id = ?

Ghi user_logs: admin X hủy thanh toán HĐ Y lúc Z, lý do: [reason]
```

### Chỉnh sửa hóa đơn

```
Điều kiện: ti.is_locked = false

Cho phép sửa:
  - discount_amount  — giảm trừ thủ công
  - previous_debt    — điều chỉnh nợ cũ
  - note             — ghi chú

Không cho sửa:
  - total_study_fee  — hệ thống tự tính từ điểm danh
  - attended_sessions, total_sessions

Sau khi sửa:
  Tự tính lại: total_amount = total_study_fee - discount_amount + previous_debt
  Cập nhật status nếu cần

Ghi user_logs: admin X sửa HĐ Y [field cũ → field mới] lúc Z
```

### Công nợ

```
Trang công nợ tổng hợp:

Bộ lọc:
  - Tháng      → WHERE ti.month = ?
  - Lớp        → WHERE ti.class_id = ?
  - Giáo viên  → WHERE classes.teacher_id = ?
  - Khối       → WHERE classes.grade_level = ?
  - Trạng thái: Còn nợ | Đã đủ

Hiển thị tổng hợp đầu trang:
  → SELECT
      COUNT(DISTINCT ti.student_id) as so_hs_dang_no,
      SUM(ti.total_amount - ti.paid_amount) as tong_no_theo_bo_loc
    FROM tuition_invoices ti
    WHERE ti.total_amount > ti.paid_amount
    [+ bộ lọc]

Bảng hiển thị:
  - Học sinh        → students.full_name
  - Lớp             → classes.name
  - Tổng phải thu   → ti.total_amount
  - Đã thu          → ti.paid_amount
  - Nợ tháng này    → ti.total_amount - ti.paid_amount
  - Nợ cũ           → ti.previous_debt
  - Tổng nợ         → (ti.total_amount - ti.paid_amount) + nợ của các tháng trước
  - Số tháng nợ     → COUNT tháng liên tiếp có (total_amount > paid_amount)
                       Nếu > 2 tháng → highlight đỏ
  - Action: Nhắc nợ | Thanh toán

Nhắc nợ (gửi tin):
  Nội dung tự động:
    "Trung tâm xin thông báo học phí tháng [MM/YYYY] của em [tên HS]
     tại lớp [tên lớp] hiện còn [số tiền]. Kính mong phụ huynh hoàn tất sớm."
  Gửi qua: SMS / Zalo (G6)
```

---

## Lương Giáo viên

### Cấu hình lương GV

```
Admin thiết lập lương cho từng GV theo từng lớp:

Danh sách cấu hình:
  → SELECT
      tsc.id, tsc.salary_per_session,
      tsc.effective_from, tsc.effective_to,
      teachers.full_name, classes.name as ten_lop
    FROM teacher_salary_configs tsc
    JOIN teachers ON tsc.teacher_id = teachers.id
    JOIN classes ON tsc.class_id = classes.id
    WHERE tsc.teacher_id = ?
    ORDER BY tsc.effective_from DESC

Tạo / cập nhật cấu hình:
  Form:
    - teacher_id      (required)
    - class_id        (required)
    - salary_per_session (required)
    - effective_from  (required)
    - effective_to    (optional)

  Service:
    - UPDATE teacher_salary_configs
        SET effective_to = effective_from_mới - 1
        WHERE teacher_id = ? AND class_id = ? AND effective_to IS NULL
    - INSERT teacher_salary_configs (...)

  Lưu ý: Khi tính lương buổi ngày X:
    → SELECT salary_per_session FROM teacher_salary_configs
      WHERE teacher_id = ? AND class_id = ?
        AND effective_from <= X
        AND (effective_to IS NULL OR effective_to >= X)
      LIMIT 1
      -- fallback: classes.teacher_salary_per_session
```

### Danh sách hóa đơn lương GV

```
Bộ lọc:
  - Tháng     (required, default: tháng hiện tại)
    → WHERE tsi.month = ?
  - Trạng thái → WHERE tsi.status = ?
  - Giáo viên  → WHERE tsi.teacher_id = ?

Hiển thị tổng hợp đầu trang:
  → SELECT
      SUM(tsi.total_amount) as tong_luong_phai_tra,
      SUM(tsi.paid_amount)  as tong_da_thanh_toan,
      SUM(tsi.total_amount - tsi.paid_amount) as tong_con_lai
    FROM teacher_salary_invoices tsi
    WHERE tsi.month = ?

Bảng hiển thị:
  - Giáo viên      → teachers.full_name
                      JOIN teachers ON tsi.teacher_id = teachers.id
  - Lớp            → classes.name
                      JOIN classes ON tsi.class_id = classes.id
  - Tháng          → tsi.month
  - Tổng buổi dạy  → tsi.total_sessions
  - Thưởng         → tsi.bonus
  - Phạt           → tsi.penalty
  - Tổng lương     → tsi.total_amount
  - Đã trả         → tsi.paid_amount
  - Còn lại        → tsi.total_amount - tsi.paid_amount
  - Trạng thái     → tsi.status
  - Action:
      + Xem chi tiết buổi dạy
      + Thanh toán lương
      + Điều chỉnh thưởng/phạt
      + Xuất phiếu lương
```

### Tạo hóa đơn lương GV tháng

```
Admin bấm "Tính lương GV tháng [MM/YYYY]"

Với mỗi GV × mỗi lớp, tính:

  total_sessions:
    → SELECT COUNT(*)
      FROM attendance_sessions as_sess
      WHERE as_sess.teacher_id = ?
        AND as_sess.class_id = ?
        AND EXTRACT(MONTH FROM as_sess.session_date) = ?
        AND as_sess.status = locked   ← chỉ tính buổi đã chốt

  total_amount:
    → SELECT SUM(
        COALESCE(si.custom_salary,  ← ưu tiên lương dạy thay nếu có
          si.teacher_salary_snapshot)  ← fallback: snapshot lúc tạo buổi
      )
      FROM schedule_instances si
      JOIN attendance_sessions as_sess ON as_sess.schedule_instance_id = si.id
      WHERE si.teacher_id = ?
        AND si.class_id = ?
        AND EXTRACT(MONTH FROM si.date) = ?
        AND as_sess.status = locked

Service:
  INSERT teacher_salary_invoices (
    teacher_id, class_id, month,
    total_sessions,
    bonus = 0, penalty = 0,
    total_amount,
    paid_amount = 0,
    status = unpaid,
    is_locked = false
  )

Ghi user_logs: admin X tạo HĐ lương GV Y tháng MM/YYYY lúc Z
```

### Thanh toán lương GV

```
Admin bấm "Thanh toán lương" tại dòng hóa đơn

Mở popup:
  Form:
    - amount         (required, default: tsi.total_amount - tsi.paid_amount)
    - payment_method (required) — cash / bank_transfer
    - note           (optional)

Service (transaction):
  1. INSERT teacher_salary_invoice_logs (
       invoice_id, amount, paid_at = now(),
       note, is_cancelled = false,
       changed_by = Auth::id()
     )

  2. UPDATE teacher_salary_invoices
       SET paid_amount = paid_amount + amount,
           status = CASE
             WHEN paid_amount + amount >= total_amount THEN paid
             ELSE partial
           END
       WHERE id = ?

  3. Tự động tạo expense record:
     INSERT expense_invoices (
       category_id = [category "Lương giáo viên"],
       title = 'Lương GV [tên GV] tháng [MM/YYYY]',
       amount,
       month,
       status = paid,
       paid_at = now(),
       payment_method,
       created_by = Auth::id()
     )

Ghi user_logs: admin X thanh toán lương GV Y số tiền Z lúc T
```

### Điều chỉnh thưởng / phạt GV

```
Form:
  - bonus   (optional) — số tiền thưởng thêm
  - penalty (optional) — số tiền phạt
  - note    (required nếu có thay đổi)

Điều kiện: tsi.is_locked = false

Service:
  UPDATE teacher_salary_invoices
    SET bonus = ?,
        penalty = ?,
        total_amount = (SELECT SUM lương buổi) + bonus - penalty
    WHERE id = ?

Ghi user_logs: admin X điều chỉnh thưởng/phạt lương GV Y lúc Z
```

---

## Lương Nhân viên

### Chấm công (Lễ tân tự check-in/out)

```
Nhân viên đăng nhập → vào trang chấm công

Hiển thị hôm nay:
  → SELECT * FROM staff_shifts
    WHERE staff_id = ? AND shift_date = today
    ORDER BY check_in_time DESC
    LIMIT 1

Nếu chưa check-in (hoặc ca mới):
  Nút [Bắt đầu ca làm]
  Service:
    INSERT staff_shifts (
      staff_id,
      shift_date = today,
      check_in_time = now(),
      hourly_rate_snapshot = (
        SELECT salary_amount FROM staff_salary_configs
        WHERE staff_id = ?
          AND salary_type = hourly
          AND effective_from <= today
          AND (effective_to IS NULL OR effective_to >= today)
        LIMIT 1
      ),
      status = open
    )

Nếu đang có ca mở:
  Hiển thị: Giờ vào [HH:mm] | Đã làm [X giờ]
  Nút [Kết thúc ca]
  Service:
    UPDATE staff_shifts
      SET check_out_time = now(),
          total_hours = EXTRACT(EPOCH FROM (now() - check_in_time)) / 3600,
          total_salary = total_hours * hourly_rate_snapshot,
          status = confirmed
      WHERE id = ? AND status = open

Xử lý quên check-out (ca mở > 6 giờ):
  → Cảnh báo Admin: "NV X có ca chưa check-out từ [HH:mm]"
  → Admin đóng ca thủ công
```

### Admin thêm ca thủ công

```
Form:
  - staff_id        (required)
  - shift_date      (required)
  - check_in_time   (required)
  - check_out_time  (required)
  - note            (required) — lý do nhập thủ công

Validation:
  - check_out_time > check_in_time
  - Không có ca nào đang open cùng ngày cùng NV
    → SELECT COUNT(*) FROM staff_shifts
      WHERE staff_id = ? AND shift_date = ? AND status = open = 0

Service:
  hourly_rate = SELECT salary_amount FROM staff_salary_configs WHERE ...
  total_hours = (check_out - check_in) in hours
  INSERT staff_shifts (
    staff_id, shift_date,
    check_in_time, check_out_time,
    total_hours,
    hourly_rate_snapshot = hourly_rate,
    total_salary = total_hours * hourly_rate,
    status = confirmed,
    note
  )

Ghi user_logs: admin X thêm ca thủ công NV Y ngày Z lúc T
```

### Danh sách hóa đơn lương NV

```
Bộ lọc:
  - Tháng     (required, default: tháng hiện tại)
    → WHERE ssi.month = ?
  - Nhân viên → WHERE ssi.staff_id = ?
  - Trạng thái→ WHERE ssi.status = ?

Hiển thị tổng hợp đầu trang:
  → SELECT
      SUM(ssi.total_amount) as tong_luong_phai_tra,
      SUM(ssi.paid_amount)  as tong_da_tra,
      SUM(ssi.total_amount - ssi.paid_amount) as con_lai
    FROM staff_salary_invoices ssi
    WHERE ssi.month = ?

Bảng hiển thị:
  - Nhân viên      → staff.full_name
                      JOIN staff ON ssi.staff_id = staff.id
  - Tháng          → ssi.month
  - Lương cơ bản   → ssi.base_salary
  - Thưởng         → ssi.bonus
  - Phạt           → ssi.penalty
  - Tạm ứng        → ssi.advance_amount
  - Tổng lương     → ssi.total_amount
  - Đã trả         → ssi.paid_amount
  - Còn lại        → ssi.total_amount - ssi.paid_amount
  - Trạng thái     → ssi.status
  - Action:
      + Xem chi tiết ca làm
      + Thanh toán lương
      + Xuất phiếu lương
      + Chốt lương tháng
```

### Tạo hóa đơn lương NV tháng

```
Admin bấm "Tính lương NV tháng [MM/YYYY]"

Với mỗi nhân viên:

  base_salary (nếu lương theo giờ):
    → SELECT SUM(ss.total_salary)
      FROM staff_shifts ss
      WHERE ss.staff_id = ?
        AND ss.shift_date BETWEEN [đầu tháng] AND [cuối tháng]
        AND ss.status = confirmed

  base_salary (nếu lương cố định):
    → SELECT salary_amount FROM staff_salary_configs
      WHERE staff_id = ?
        AND salary_type = fixed
        AND effective_from <= [cuối tháng]
        AND (effective_to IS NULL OR effective_to >= [đầu tháng])
      LIMIT 1

  total_amount = base_salary + bonus - penalty - advance_amount

Service:
  INSERT staff_salary_invoices (
    staff_id, month,
    base_salary, bonus = 0, penalty = 0, advance_amount = 0,
    total_amount = base_salary,
    paid_amount = 0,
    status = unpaid,
    is_locked = false
  )

Ghi user_logs: admin X tạo HĐ lương NV Y tháng MM/YYYY lúc Z
```

### Chốt lương tháng (NV)

```
Admin bấm "Chốt lương tháng"

Service:
  1. UPDATE staff_shifts
       SET status = locked
       WHERE staff_id = ?
         AND shift_date BETWEEN [đầu tháng] AND [cuối tháng]
         AND status = confirmed

  2. UPDATE staff_salary_invoices
       SET is_locked = true
       WHERE staff_id = ? AND month = ?

Sau khi chốt:
  - Không cho sửa ca làm việc tháng đó
  - Không cho sửa hóa đơn lương
```

---

## Chi phí vận hành

### Danh mục chi phí

```
Danh sách mặc định (seeded):
  Điện | Nước | Internet | Văn phòng phẩm | Thuê mặt bằng
  Marketing | Sửa chữa | Chi phí khác

Admin có thể thêm danh mục mới:
  INSERT expense_categories (name, description)

Bảng hiển thị:
  → SELECT ec.name, ec.description,
           COUNT(ei.id) as so_hoa_don
    FROM expense_categories ec
    LEFT JOIN expense_invoices ei ON ei.category_id = ec.id
    GROUP BY ec.id
```

### Danh sách chi phí

```
Bộ lọc:
  - Tháng           → WHERE ei.month = ?
  - Loại chi phí    → WHERE ei.category_id = ?
  - Trạng thái      → WHERE ei.status = ?
  - Phương thức     → WHERE ei.payment_method = ?

Hiển thị tổng chi đầu trang:
  → SELECT SUM(ei.amount) as tong_chi
    FROM expense_invoices ei
    WHERE [bộ lọc hiện tại]

Bảng hiển thị:
  - Tiêu đề         → ei.title
  - Loại            → expense_categories.name
                       JOIN expense_categories ON ei.category_id = expense_categories.id
  - Tháng           → ei.month
  - Số tiền         → ei.amount
  - Ngày thanh toán → ei.paid_at
  - Phương thức     → ei.payment_method
  - Trạng thái      → ei.status
  - Tái lặp         → ei.is_recurring
  - Người tạo       → users.username
                       JOIN users ON ei.created_by = users.id
  - Action: Chỉnh sửa | Xóa (nếu chưa paid)
```

### Thêm chi phí

```
Form:
  - category_id  (required)
  - title        (required)
  - amount       (required, > 0)
  - month        (required, default: tháng hiện tại)
  - payment_method (required)
  - paid_at      (optional) — ngày thanh toán thực tế
  - note         (optional)
  - is_recurring (boolean, default: false)
    Nếu true → tháng sau tự tạo bản nháp với cùng thông tin

Validation:
  - Không cho nhập chi phí loại lương thủ công
    → category phải khác "Lương giáo viên", "Lương nhân viên"
    (2 loại này do hệ thống tự tạo khi thanh toán lương)

Service:
  INSERT expense_invoices (
    category_id, title, amount, month,
    payment_method, paid_at,
    status = CASE WHEN paid_at IS NOT NULL THEN paid ELSE unpaid END,
    note, is_recurring,
    created_by = Auth::id(),
    changed_by = Auth::id()
  )

Ghi user_logs: admin X thêm chi phí [title] tháng MM/YYYY lúc Z
```

---

## Báo cáo tài chính

### Tổng quan tháng

```
Bộ lọc: Tháng (default: tháng hiện tại)

Doanh thu:
  → SELECT SUM(ti.total_study_fee) as doanh_thu_hoc_phi
    FROM tuition_invoices ti WHERE ti.month = ?

Chi phí:
  → SELECT ec.name, SUM(ei.amount) as tong_chi
    FROM expense_invoices ei
    JOIN expense_categories ec ON ei.category_id = ec.id
    WHERE ei.month = ?
    GROUP BY ec.id

  Trong đó lương chiếm:
    Lương GV: SUM(teacher_salary_invoices.total_amount) WHERE month = ?
    Lương NV: SUM(staff_salary_invoices.total_amount) WHERE month = ?
    Chi phí khác: SUM(expense_invoices.amount) WHERE month = ?
                    AND category không phải lương

Lợi nhuận tháng:
  = Doanh thu - Tổng chi phí

Tỷ lệ cảnh báo:
  → Nếu Tổng chi / Doanh thu > 85% → cảnh báo "Biên lợi nhuận thấp"
  → Tỷ lệ lương NV+GV / Doanh thu
  → Tỷ lệ chi phí cố định / Doanh thu
```

### Biểu đồ

```
Biểu đồ cột theo loại chi phí (tháng hiện tại):
  → SELECT ec.name, SUM(ei.amount)
    FROM expense_invoices ei JOIN expense_categories ec ON ...
    WHERE ei.month = ? GROUP BY ec.id

Biểu đồ tròn cơ cấu chi:
  Tỷ trọng từng loại / Tổng chi

Biểu đồ đường xu hướng (3/6/12 tháng):
  → SELECT ei.month, SUM(ei.amount) as tong_chi,
           SUM(ti.total_study_fee) as doanh_thu
    FROM expense_invoices ei
    CROSS JOIN (SELECT month, SUM(total_study_fee) as total_study_fee
                FROM tuition_invoices GROUP BY month) ti_agg ON ti_agg.month = ei.month
    WHERE ei.month BETWEEN [X tháng trước] AND [tháng hiện tại]
    GROUP BY ei.month
    ORDER BY ei.month
```

---

## Phân quyền trong module này

| Chức năng | Admin | Teacher | Staff | Student |
|---|---|---|---|---|
| Xem / tạo hóa đơn học phí | ✅ | ❌ | ❌ | Chỉ của mình |
| Thanh toán học phí | ✅ | ❌ | ❌ | ❌ |
| Xem công nợ | ✅ | ❌ | ❌ | ❌ |
| Cấu hình lương GV | ✅ | ❌ | ❌ | ❌ |
| Xem / thanh toán lương GV | ✅ | Chỉ của mình | ❌ | ❌ |
| Chấm công (check-in/out) | ✅ | ❌ | Chỉ của mình | ❌ |
| Xem / thanh toán lương NV | ✅ | ❌ | Chỉ của mình | ❌ |
| Quản lý chi phí vận hành | ✅ | ❌ | ❌ | ❌ |
| Xem báo cáo tài chính | ✅ | ❌ | ❌ | ❌ |
| Chốt lương / khóa hóa đơn | ✅ | ❌ | ❌ | ❌ |

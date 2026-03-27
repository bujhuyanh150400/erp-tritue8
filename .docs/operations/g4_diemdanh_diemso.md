# Phân tích nghiệp vụ — G4: Điểm danh & Điểm số

Bao gồm: Điểm danh buổi học, Nhập điểm, Điểm thưởng, Đổi thưởng, Danh mục phần thưởng

---

## Tổng quan

G4 là module tác nghiệp chính của giáo viên trong mỗi buổi học. Mỗi `schedule_instance` (G3) khi bắt đầu điểm danh sẽ sinh ra 1 `attendance_session` tương ứng.

Thứ tự phụ thuộc:
```
schedule_instances (G3)
        │
        ▼ 1 buổi học → 1 phiên điểm danh
attendance_sessions
        │
        ▼ 1 phiên → N học sinh
attendance_records ──► scores (tối đa 2 bài/buổi)
                   ──► reward_points (cộng/trừ sao)
```

### Quy tắc hệ thống

| Quy tắc | Chi tiết |
|---|---|
| 1 buổi học = 1 attendance_session | Không tạo 2 session cho cùng 1 schedule_instance |
| HS vắng → không cho nhập điểm | is_fee_counted = false tự động |
| Buổi phải Hoàn thành mới tính tiền | status = completed → mới tạo hóa đơn |
| Buổi đã Locked → không sửa | locked_at != NULL → readonly hoàn toàn |
| GV chỉ điểm danh buổi của mình | teacher_id = GV đang đăng nhập |
| Điểm thưởng lưu theo ledger | Mỗi lần cộng/trừ là 1 record, không update tổng |

### Các edge case cần xử lý

| Tình huống | Xử lý |
|---|---|
| GV bấm điểm danh buổi chưa tới | Cho phép, ngày mặc định = hôm nay, GV có thể sửa |
| GV điểm danh nhầm ngày | Cho sửa khi status = draft |
| HS thêm vào lớp sau ngày buổi học | Không xuất hiện trong danh sách buổi đó |
| Đổi thưởng loại discount khi chưa có hóa đơn | Ghi nhận, gắn invoice_id khi hóa đơn được tạo |
| Mở lại buổi đã Hoàn thành để sửa | Cho phép, status = draft lại, cần ghi log |
| Chốt tháng → lock toàn bộ session | locked_at = now(), không cho sửa bất kỳ field nào |

---

## Định nghĩa Enum

```
AttendanceSessionStatus:
  0 = draft      — nháp, GV đang nhập
  1 = completed  — hoàn thành, tính tiền + cho gửi tin
  2 = locked     — đã chốt tháng, không sửa được

AttendanceStatus:
  0 = present  — có mặt
  1 = late     — đi muộn
  2 = excused  — vắng có phép
  3 = absent   — vắng không phép

RewardType:
  0 = physical  — phần thưởng vật chất (bút, vở...)
  1 = privilege — đặc quyền (miễn kiểm tra miệng...)
  2 = discount  — giảm học phí (gắn vào tuition_invoices)
```

---

## Điểm danh buổi học

### Bắt đầu / Mở buổi điểm danh

```
GV vào lớp → chọn buổi học
- Nếu đã có attendance_session → bấm "Xem điểm danh"
- Nếu chưa có → bấm "Bắt đầu điểm danh"

Kiểm tra buổi đã có attendance_session chưa:
  → SELECT id FROM attendance_sessions
    WHERE schedule_instance_id = ?
  
  - Nếu đã có → Navigate thẳng vào session đó
  - Nếu chưa có → Tạo mới:
  
  - Validation:
  - Kiểm tra quyền:
      admin - full access
      teacher - schedule_instances.teacher_id = Auth::user()->teacher->id
      -> Nếu không khớp: "Bạn không phải giáo viên phụ trách buổi này"
  - Ngày buổi học session_date
    + session_date
    
    
    INSERT attendance_sessions (
      schedule_instance_id,
      class_id   = si.class_id,
      teacher_id = si.teacher_id,
      session_date = si.date,   ← mặc định, GV có thể sửa nếu cần
      status     = draft,
      created_at = now()
    )

    Tự động tạo attendance_records cho toàn bộ HS trong lớp:
      → SELECT students.id, students.full_name
        FROM class_enrollments ce
        JOIN students ON ce.student_id = students.id
        WHERE ce.class_id = ?
          AND ce.enrolled_at <= si.date   ← chỉ lấy HS đã vào lớp trước buổi này
          AND (ce.left_at IS NULL OR ce.left_at >= si.date)  ← chưa nghỉ
        ORDER BY students.full_name

      INSERT attendance_records (
        session_id, student_id,
        status     = present,   ← mặc định có mặt
        is_fee_counted = true,
        created_at = now()
      ) for each student
```

### Trang điểm danh (màn hình chính của GV)

```
Header buổi học:
  → SELECT
      as_sess.session_date, as_sess.status,
      classes.name as ten_lop, subjects.name as ten_mon,
      teachers.full_name as ten_gv,
      rooms.name as phong,
      si.schedule_type, si.start_time, si.end_time
    FROM attendance_sessions as_sess
    JOIN schedule_instances si ON as_sess.schedule_instance_id = si.id
    JOIN classes ON as_sess.class_id = classes.id
    JOIN subjects ON classes.subject_id = subjects.id
    JOIN teachers ON as_sess.teacher_id = teachers.id
    JOIN rooms ON si.room_id = rooms.id
    WHERE as_sess.id = ?

Khung nội dung chung (áp dụng toàn lớp):
  - lesson_content   → as_sess.lesson_content (text)
  - homework         → as_sess.homework (text)
  - next_session_note→ as_sess.next_session_note (text)
  - general_note     → as_sess.general_note (text)

Bài kiểm tra trong buổi (tối đa 2):
  Thông tin chung cho cả lớp (không lưu riêng cho từng HS ở đây):
  - exam_slot_1_name → ghi nhớ tạm trên UI, lưu vào scores.exam_name khi nhập điểm
  - exam_slot_2_name → tương tự
  - max_score_1, max_score_2 (default: 10)

Bảng học sinh:
  → SELECT
      ar.id as record_id,
      students.id as student_id,
      students.full_name,
      ar.status as attendance_status,
      ar.check_in_time,
      ar.is_fee_counted,
      ar.teacher_comment,
      ar.private_note,
      sc1.score as diem_1, sc1.exam_name as ten_bai_1,
      sc2.score as diem_2, sc2.exam_name as ten_bai_2,
      SUM(rp.amount) as tong_sao_hien_tai
    FROM attendance_records ar
    JOIN students ON ar.student_id = students.id
    LEFT JOIN scores sc1 ON sc1.attendance_record_id = ar.id AND sc1.exam_slot = 1
    LEFT JOIN scores sc2 ON sc2.attendance_record_id = ar.id AND sc2.exam_slot = 2
    LEFT JOIN reward_points rp ON rp.student_id = students.id
    WHERE ar.session_id = ?
    GROUP BY ar.id, students.id, sc1.id, sc2.id
    ORDER BY students.full_name
```

### Điểm danh từng học sinh

```
GV chọn trạng thái cho từng HS (radio button):
  0 = Có mặt | 1 = Đi muộn | 2 = Vắng có phép | 3 = Vắng không phép

Nếu Có mặt hoặc Đi muộn:
  - check_in_time = now() (tự ghi, GV có thể sửa)
  - is_fee_counted = true

Nếu Vắng (excused / absent):
  - is_fee_counted = false
  - Khóa ô nhập điểm của HS đó

Service:
  UPDATE attendance_records
    SET status = ?,
        check_in_time = ?,
        is_fee_counted = ?
    WHERE id = ? AND session_id = ?
    ← Chỉ cho sửa khi attendance_sessions.status = draft
```

### Nhập điểm kiểm tra

```
Chỉ nhập được khi attendance_records.status IN (present, late)
Nếu vắng → ô điểm bị disabled

Mỗi HS có 2 slot điểm:

Slot 1:
  - Nếu chưa có scores record với exam_slot = 1:
      INSERT scores (attendance_record_id, exam_slot = 1, exam_name, score, max_score, note)
  - Nếu đã có:
      UPDATE scores SET score = ?, note = ? WHERE attendance_record_id = ? AND exam_slot = 1

Slot 2: tương tự với exam_slot = 2

Nếu không có kiểm tra → để trống (scores record không tạo)
```

### Điểm thưởng (Sao)

```
GV cộng/trừ sao cho từng HS trong buổi:

Form:
  - amount (int, dương = cộng, âm = trừ)
  - reason (varchar, optional)

Service:
  INSERT reward_points (
    student_id,
    session_id = as_sess.id,
    amount     = ?,
    reason     = ?,
    awarded_by = Auth::id()
  )
  ← Không UPDATE tổng, luôn INSERT record mới

Tổng sao hiện tại (real-time):
  → SELECT SUM(amount) FROM reward_points WHERE student_id = ?

Lịch sử sao trong buổi này:
  → SELECT rp.amount, rp.reason, rp.created_at
    FROM reward_points rp
    WHERE rp.student_id = ? AND rp.session_id = ?
    ORDER BY rp.created_at DESC
```

### Nhắc riêng (Private note)

```
GV nhập ghi chú riêng cho từng HS:
  - Nội dung này sẽ được chèn vào tin nhắn cá nhân gửi phụ huynh

Service:
  UPDATE attendance_records
    SET private_note = ?, teacher_comment = ?
    WHERE id = ?
```

### Hoàn thành buổi học

```
GV bấm "Hoàn thành & Gửi thông báo"

Validation:
  - Tất cả HS phải có trạng thái điểm danh (không được để null)
    → SELECT COUNT(*) FROM attendance_records
      WHERE session_id = ? AND status IS NULL = 0

Service (transaction):
  1. UPDATE attendance_sessions
       SET status = completed,
           completed_at = now()
       WHERE id = ?

  2. UPDATE schedule_instances
       SET status = completed
       WHERE id = as_sess.schedule_instance_id

  3. Cập nhật is_fee_counted theo trạng thái:
     UPDATE attendance_records
       SET is_fee_counted = CASE
         WHEN status IN (present, late) THEN true
         ELSE false
       END
       WHERE session_id = ?

  4. Gửi tin nhắn cá nhân đến từng phụ huynh (G6):
     Mỗi phụ huynh nhận tin gồm:
       - Ngày, môn, tình trạng đi học
       - Nội dung bài học, BTVN
       - Điểm kiểm tra 1, 2 (nếu có)
       - Điểm thưởng hôm nay + tổng hiện tại
       - Nhắc riêng (private_note)

  5. Gửi tin nhắn chung lớp (G6):
       - Nội dung bài, BTVN, nhắc buổi sau
       - Không gửi thông tin cá nhân

Ghi user_logs: GV X hoàn thành điểm danh lớp Y buổi Z lúc T
```

### Mở lại buổi đã Hoàn thành

```
Điều kiện: status = completed AND locked_at IS NULL

GV (hoặc Admin) bấm "Mở lại để sửa"

Service:
  UPDATE attendance_sessions
    SET status = draft,
        completed_at = NULL
    WHERE id = ? AND locked_at IS NULL

Ghi user_logs: X mở lại buổi điểm danh Y lúc Z
```

---

## Đổi thưởng

### Danh mục phần thưởng

```
Danh sách hiển thị cho GV khi bấm "Đổi thưởng" trong buổi điểm danh:

  → SELECT ri.id, ri.name, ri.points_required,
           ri.reward_type, ri.discount_amount
    FROM reward_items ri
    WHERE ri.is_active = true
    ORDER BY ri.points_required ASC

  Bảng: Tên phần thưởng | Điểm cần | Loại | Giá trị giảm (nếu discount)
```

### Quản lý danh mục phần thưởng (Admin)

```
Danh sách:
  → SELECT * FROM reward_items ORDER BY points_required ASC

  Bảng:
    - Tên phần thưởng  → reward_items.name
    - Điểm cần đổi     → reward_items.points_required
    - Loại             → reward_items.reward_type
    - Giá trị giảm     → reward_items.discount_amount (nếu type = discount)
    - Trạng thái       → reward_items.is_active
    - Action: Chỉnh sửa | Bật/Tắt

Tạo phần thưởng:
  Form:
    - name             (required)
    - points_required  (required, > 0)
    - reward_type      (required)
    - discount_amount  (required nếu reward_type = discount)
    - is_active        (default: true)
  Service: INSERT reward_items

Sửa phần thưởng:
  Service: UPDATE reward_items
  Không cho sửa nếu đã có reward_redemptions dùng item này
    → SELECT COUNT(*) FROM reward_redemptions WHERE reward_item_id = ? > 0
    → Chỉ cho đổi is_active, không cho đổi points_required
```

### Đổi thưởng cho học sinh

```
GV bấm "Đổi thưởng" tại dòng HS trong buổi điểm danh

Hiển thị:
  - Tổng sao hiện tại:
      → SELECT SUM(amount) FROM reward_points WHERE student_id = ?
  - Danh sách phần thưởng đang active

GV chọn phần thưởng

Validation:
  - Kiểm tra đủ điểm:
      SUM(reward_points.amount) WHERE student_id = ? >= reward_items.points_required
      → Nếu không đủ: "Học sinh chỉ có X sao, cần Y sao"

Service (transaction):
  1. INSERT reward_redemptions (
       student_id,
       reward_item_id,
       points_spent  = reward_items.points_required,
       redeemed_at   = now(),
       processed_by  = Auth::id(),
       invoice_id    = NULL  ← gắn sau nếu type = discount
     )

  2. INSERT reward_points (
       student_id,
       session_id  = as_sess.id,
       amount      = -(reward_items.points_required),  ← âm = trừ điểm
       reason      = 'Đổi thưởng: ' + reward_items.name,
       awarded_by  = Auth::id()
     )

  3. Nếu reward_type = discount:
       Ghi nhận discount_amount để áp vào tuition_invoices tháng này
       Khi tạo hóa đơn → UPDATE reward_redemptions SET invoice_id = ?

Ghi user_logs: GV X đổi thưởng [tên thưởng] cho HS Y lúc Z
```

### Lịch sử đổi thưởng

```
Xem lịch sử đổi thưởng của học sinh:

  → SELECT
      rr.redeemed_at,
      ri.name as ten_thuong,
      ri.reward_type,
      rr.points_spent,
      u.username as nguoi_xu_ly
    FROM reward_redemptions rr
    JOIN reward_items ri ON rr.reward_item_id = ri.id
    JOIN users u ON rr.processed_by = u.id
    WHERE rr.student_id = ?
    ORDER BY rr.redeemed_at DESC

  Bảng: Ngày | Phần thưởng | Loại | Sao đã dùng | Người xử lý
```

---

## Chốt tháng (Lock buổi học)

```
Admin bấm "Chốt tháng" cho lớp (hoặc toàn bộ lớp)

Điều kiện:
  - Tất cả buổi trong tháng phải có status = completed
    → SELECT COUNT(*) FROM attendance_sessions
      WHERE class_id = ? AND MONTH(session_date) = ? AND YEAR(session_date) = ?
        AND status = draft
      → Nếu > 0: "Còn X buổi chưa hoàn thành điểm danh"

Service:
  UPDATE attendance_sessions
    SET status = locked,
        locked_at = now()
    WHERE class_id = ?
      AND EXTRACT(MONTH FROM session_date) = ?
      AND EXTRACT(YEAR FROM session_date) = ?
      AND status = completed

Sau khi lock:
  - Không sửa được bất kỳ field nào trong attendance_sessions
  - Không sửa attendance_records (điểm danh, điểm số, sao thưởng)
  - Dùng làm căn cứ tính hóa đơn học phí tháng (G5)

Ghi user_logs: admin X chốt tháng MM/YYYY lớp Y lúc Z
```

---

## Phân quyền trong module này

| Chức năng | Admin | Teacher | Staff | Student |
|---|---|---|---|---|
| Bắt đầu / mở buổi điểm danh | ✅ | Chỉ buổi của mình | ❌ | ❌ |
| Nhập điểm danh | ✅ | Chỉ buổi của mình | ❌ | ❌ |
| Nhập điểm kiểm tra | ✅ | Chỉ buổi của mình | ❌ | ❌ |
| Cộng / trừ sao thưởng | ✅ | Chỉ lớp của mình | ❌ | ❌ |
| Đổi thưởng cho HS | ✅ | Chỉ lớp của mình | ❌ | ❌ |
| Quản lý danh mục phần thưởng | ✅ | ❌ | ❌ | ❌ |
| Xem điểm danh / điểm số | ✅ | Chỉ lớp của mình | ❌ | Chỉ của mình |
| Mở lại buổi đã hoàn thành | ✅ | Chỉ buổi của mình (nếu chưa lock) | ❌ | ❌ |
| Chốt tháng | ✅ | ❌ | ❌ | ❌ |

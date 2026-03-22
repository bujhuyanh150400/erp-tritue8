# Phân tích nghiệp vụ — G3: Lịch học

Bao gồm: Lịch tổng hợp (Calendar), Lịch cố định (Template), Buổi học thực tế (Instance), Học bù / Tăng cường, Nghỉ lễ, Đổi giáo viên dạy thay, Đề xuất đổi lịch (GV)

---

## Tổng quan

G3 xây dựng trên mô hình **Template + Instance**:

```
class_schedule_templates  — lịch cố định (quy tắc lặp theo tuần)
        │
        ▼ sinh ra
schedule_instances        — buổi học thực tế (từng ngày cụ thể)
        │
        ▼ kích hoạt
attendance_sessions (G4)  — phiên điểm danh
```

**Nguyên tắc cốt lõi:**
- Lịch cố định = template, **không lưu trực tiếp theo ngày**
- Buổi học thực tế = instance, được sinh từ template hoặc tạo thủ công (học bù, tăng cường)
- Phòng học và giáo viên gắn theo **từng buổi**, không gắn cố định vào lớp
- Mọi thay đổi phải có ngày bắt đầu áp dụng, không ghi đè lịch sử

### Quy tắc hệ thống

| Quy tắc | Chi tiết |
|---|---|
| Không sửa buổi đã diễn ra | Chỉ buổi có status = scheduled mới được sửa |
| Không xóa học bù / tăng cường / nghỉ lễ khi đổi template | Chỉ xóa buổi chính tương lai |
| Phòng thuộc từng buổi | Không thuộc lớp |
| Snapshot lương GV | Lấy tại thời điểm tạo instance, không thay đổi sau |

### Các edge case cần xử lý

| Tình huống | Xử lý |
|---|---|
| Tạo lịch trùng phòng | Không cho lưu, hiển thị buổi đang trùng |
| Tạo lịch trùng GV | Không cho lưu |
| Tạo lịch trùng HS (học nhiều lớp) | Cảnh báo, không chặn cứng |
| Đổi template khi đã có buổi tương lai | Xóa buổi chính chưa diễn ra, sinh lại theo template mới |
| Học bù cho buổi đã hủy | linked_makeup_for trỏ đến instance đã cancelled |
| GV đề xuất đổi lịch bị trùng | Hệ thống kiểm tra trước khi duyệt |

---

## Định nghĩa Enum

```
ScheduleType:
  0 = main     — buổi học chính (sinh từ template)
  1 = makeup   — học bù
  2 = extra    — tăng cường
  3 = holiday  — nghỉ lễ

ScheduleStatus:
  0 = scheduled  — chưa diễn ra
  1 = completed  — đã hoàn thành (có attendance_session)
  2 = cancelled  — đã hủy

FeeType:
  0 = normal  — tính học phí bình thường
  1 = free    — miễn phí
  2 = custom  — học phí riêng (custom_fee_per_session)

DayOfWeek:
  1 = Thứ 2 | 2 = Thứ 3 | 3 = Thứ 4 | 4 = Thứ 5
  5 = Thứ 6 | 6 = Thứ 7 | 7 = Chủ nhật

ScheduleChangeStatus:
  0 = pending   — chờ admin duyệt
  1 = approved  — đã duyệt
  2 = rejected  — đã từ chối
```

---

## Lịch tổng hợp (Calendar View — Admin)

### Giao diện & Chế độ xem

```
Chế độ xem:
  - Theo ngày
  - Theo tuần (mặc định)
  - Theo tháng
  - Toàn màn hình

Khung giờ hiển thị: 06:00 – 22:00
Hiển thị dạng lưới thời gian (Google Calendar style)
Hỗ trợ kéo thả để đổi giờ (chỉ Admin)
```

### Bộ lọc lịch tổng hợp

```
Bộ lọc:
  - Giáo viên   → WHERE si.teacher_id = ?
  - Môn học     → WHERE classes.subject_id = ?
                   JOIN classes ON si.class_id = classes.id
  - Phòng học   → WHERE si.room_id = ?
  - Loại lịch   → WHERE si.schedule_type = ?
  - Trạng thái  → WHERE si.status = ?
  - Khối lớp    → WHERE classes.grade_level = ?
  - Khoảng thời gian → WHERE si.date BETWEEN ? AND ?
  - Chỉ lớp đang Active → WHERE classes.status = Active

Có thể chọn nhiều điều kiện cùng lúc
```

### Hiển thị mỗi block lịch

```
Mỗi schedule_instance hiển thị trên calendar:

→ SELECT
    si.id, si.date, si.start_time, si.end_time,
    si.schedule_type, si.status,
    classes.name as ten_lop,
    classes.grade_level,
    teachers.full_name as ten_gv,
    rooms.name as phong,
    COUNT(ce.id) as si_so
  FROM schedule_instances si
  JOIN classes ON si.class_id = classes.id
  JOIN teachers ON si.teacher_id = teachers.id
  JOIN rooms ON si.room_id = rooms.id
  LEFT JOIN class_enrollments ce ON ce.class_id = si.class_id
    AND ce.left_at IS NULL
  WHERE si.date BETWEEN [range hiện tại]
    AND [bộ lọc đang chọn]
  GROUP BY si.id, classes.id, teachers.id, rooms.id

Màu sắc theo schedule_type:
  main    → xanh
  makeup  → cam
  extra   → tím
  holiday → đỏ
  cancelled → xám

Hover vào block → popup chi tiết đầy đủ
```

### Popup chi tiết buổi học

```
→ SELECT
    si.*,
    classes.name, classes.code,
    subjects.name as ten_mon,
    t_current.full_name as ten_gv_hien_tai,
    t_original.full_name as ten_gv_goc,
    rooms.name as phong,
    si.teacher_salary_snapshot,
    si.custom_salary,
    si.fee_type,
    si.custom_fee_per_session,
    si.note,
    COUNT(ce.id) as si_so,
    si_makeup.date as ngay_bu  ← nếu linked_makeup_for != null
  FROM schedule_instances si
  JOIN classes ON si.class_id = classes.id
  JOIN subjects ON classes.subject_id = subjects.id
  JOIN teachers t_current ON si.teacher_id = t_current.id
  LEFT JOIN teachers t_original ON si.original_teacher_id = t_original.id
  JOIN rooms ON si.room_id = rooms.id
  LEFT JOIN class_enrollments ce ON ce.class_id = si.class_id AND ce.left_at IS NULL
  LEFT JOIN schedule_instances si_makeup ON si.linked_makeup_for = si_makeup.id
  WHERE si.id = ?
```

---

## Lịch cố định (Template)

### Tạo lịch cố định

```
Admin nhập cho lớp:
  - class_id     (required) — chọn lớp
  - day_of_week  (required) — thứ trong tuần (DayOfWeek)
  - start_time   (required) — giờ bắt đầu
  - end_time     (required) — giờ kết thúc
  - room_id      (required) — phòng học
  - teacher_id   (required) — giáo viên
  - start_date   (required) — ngày bắt đầu áp dụng
  - end_date     (optional) — ngày kết thúc (NULL = vô thời hạn)

Validation — kiểm tra trùng phòng:
  → SELECT si.date, si.start_time, si.end_time, classes.name
    FROM schedule_instances si
    JOIN classes ON si.class_id = classes.id
    WHERE si.room_id = room_id_mới
      AND EXTRACT(DOW FROM si.date) = day_of_week_mới  ← PostgreSQL DOW
      AND si.date >= start_date
      AND si.status != cancelled
      AND (si.start_time < end_time_mới AND si.end_time > start_time_mới)
    → Nếu có: "Phòng đã có lớp [X] từ [HH:mm]–[HH:mm]"

Validation — kiểm tra trùng GV:
  → SELECT si.date, classes.name
    FROM schedule_instances si
    JOIN classes ON si.class_id = classes.id
    WHERE si.teacher_id = teacher_id_mới
      AND EXTRACT(DOW FROM si.date) = day_of_week_mới
      AND si.date >= start_date
      AND si.status != cancelled
      AND (si.start_time < end_time_mới AND si.end_time > start_time_mới)
    → Nếu có: "Giáo viên đã có lớp [X] giờ này"

Nếu pass:
  Service:
    1. INSERT class_schedule_templates (
         class_id, day_of_week, start_time, end_time,
         room_id, teacher_id, start_date, end_date = NULL,
         created_by = Auth::id()
       )
    2. Sinh schedule_instances từ template:
         Duyệt từng ngày có day_of_week = template.day_of_week
         Từ start_date đến end_date (hoặc đến cuối năm học nếu NULL)
         INSERT schedule_instances (
           class_id, template_id = template.id,
           date, start_time, end_time,
           room_id, teacher_id,
           original_teacher_id = teacher_id,
           teacher_salary_snapshot = (
             SELECT salary_per_session FROM teacher_salary_configs
             WHERE teacher_id = ? AND class_id = ?
               AND effective_from <= date
               AND (effective_to IS NULL OR effective_to >= date)
             LIMIT 1
             -- fallback: classes.teacher_salary_per_session
           ),
           schedule_type = main,
           status = scheduled,
           created_by = Auth::id()
         )

Ghi user_logs: admin X tạo lịch cố định lớp Y lúc Z
```

### Thay đổi lịch cố định

```
Admin chọn "Áp dụng từ ngày …" khi đổi lịch

Nhập:
  - apply_from   (required) — ngày bắt đầu áp dụng lịch mới
  - day_of_week, start_time, end_time, room_id, teacher_id (mới)

Validation: tương tự tạo mới (kiểm tra trùng từ apply_from trở đi)

Service (transaction):
  1. UPDATE class_schedule_templates
       SET end_date = apply_from - 1 ngày
       WHERE class_id = ? AND end_date IS NULL
       ← đóng template cũ

  2. INSERT class_schedule_templates (mới, start_date = apply_from)

  3. DELETE schedule_instances
       WHERE class_id = ?
         AND date >= apply_from
         AND schedule_type = main      ← chỉ xóa buổi chính
         AND status = scheduled        ← chỉ xóa buổi chưa diễn ra
     ← KHÔNG xóa: makeup, extra, holiday

  4. Sinh lại schedule_instances từ template mới
     (tương tự tạo mới, từ apply_from trở đi)

Ghi user_logs: admin X đổi lịch cố định lớp Y từ ngày Z lúc T
```

---

## Thêm Học bù / Tăng cường

### Tạo học bù / tăng cường

```
Admin nhập:
  - class_id       (required) — 1 lớp hoặc nhiều lớp
  - student_ids    (optional) — chọn riêng từng HS (nếu chỉ một nhóm)
  - date           (required) — ngày học
  - start_time     (required)
  - end_time       (required)
  - room_id        (required)
  - teacher_id     (required)
  - schedule_type  (required) — makeup hoặc extra
  - linked_makeup_for (optional) — buổi nào đang bù (nếu là makeup)

  Tài chính:
  - fee_type (required):
      0 = normal  — tính học phí như bình thường
      1 = free    — miễn phí
      2 = custom  — nhập học phí riêng
  - custom_fee_per_session (nếu fee_type = custom)

  Lương GV:
  - custom_salary (optional) — nhập cố định cho buổi này
                               NULL = dùng teacher_salary_snapshot

Validation — kiểm tra trùng phòng:
  → SELECT si.start_time, si.end_time, classes.name
    FROM schedule_instances si
    JOIN classes ON si.class_id = classes.id
    WHERE si.room_id = ?
      AND si.date = ?
      AND si.status != cancelled
      AND (si.start_time < end_time_mới AND si.end_time > start_time_mới)

Validation — kiểm tra trùng GV:
  → SELECT si.date, classes.name
    FROM schedule_instances si
    JOIN classes ON si.class_id = classes.id
    WHERE si.teacher_id = ?
      AND si.date = ?
      AND si.status != cancelled
      AND (si.start_time < end_time_mới AND si.end_time > start_time_mới)

Validation — kiểm tra trùng HS (cảnh báo):
  → SELECT students.full_name, classes.name as ten_lop_khac
    FROM class_enrollments ce
    JOIN students ON ce.student_id = students.id
    JOIN schedule_instances si ON si.class_id = ce.class_id
    WHERE ce.class_id = class_id_mới
      AND ce.left_at IS NULL
      AND si.date = date_mới
      AND si.status != cancelled
      AND (si.start_time < end_time_mới AND si.end_time > start_time_mới)
      AND si.class_id != class_id_mới

Service:
  INSERT schedule_instances (
    class_id, template_id = NULL,
    date, start_time, end_time, room_id, teacher_id,
    original_teacher_id = teacher_id,
    teacher_salary_snapshot = [...],
    custom_salary,
    schedule_type = makeup/extra,
    status = scheduled,
    linked_makeup_for,
    fee_type, custom_fee_per_session,
    created_by = Auth::id()
  )

Ghi user_logs: admin X tạo buổi [makeup/extra] lớp Y ngày Z lúc T
```

---

## Lịch nghỉ

### Nghỉ lễ / Nghỉ hàng loạt

```
Admin nhập:
  - date_from    (required)
  - date_to      (required)
  - apply_to     — tất cả lớp hoặc chọn class_ids cụ thể
  - auto_makeup  (boolean) — có tự động tạo học bù không

Nếu apply_to = tất cả:
  → SELECT id FROM classes WHERE status = Active

Service (transaction):
  1. UPDATE schedule_instances
       SET status = cancelled
       WHERE class_id IN (danh sách lớp)
         AND date BETWEEN date_from AND date_to
         AND schedule_type = main
         AND status = scheduled

  2. Nếu auto_makeup = true:
     → Gợi ý ngày trống:
         SELECT proposed_date FROM [logic tìm slot trống]
         Xem mục "Gợi ý slot học bù" bên dưới
     → Admin xác nhận → INSERT schedule_instances (schedule_type = makeup, linked_makeup_for = ...)

Ghi user_logs
```

### Hủy / Dời buổi riêng lẻ

```
Admin click vào buổi học trên calendar → chọn action:

[Dời sang ngày khác]:
  Nhập: date_mới, start_time_mới, end_time_mới, room_id_mới (optional)
  Validation: kiểm tra trùng phòng + GV tại slot mới
  Service:
    UPDATE schedule_instances
      SET date = ?, start_time = ?, end_time = ?, room_id = ?
      WHERE id = ? AND status = scheduled

[Hủy buổi]:
  Hỏi: có tính tiền không?
  - Nếu không tính → is_fee_counted = false cho attendance_records liên quan
  Service:
    UPDATE schedule_instances SET status = cancelled WHERE id = ?

[Chuyển thành học bù]:
  Service:
    UPDATE schedule_instances SET schedule_type = makeup WHERE id = ?
    → Giữ nguyên ngày/giờ, chỉ đổi type

[Nhân đôi buổi]:
  Service:
    INSERT schedule_instances (copy toàn bộ thông tin, tạo instance mới)
    → Thường dùng khi cần thêm 1 buổi học bù cùng ngày giờ cho nhóm khác

Ghi user_logs cho mọi action
```

---

## Đổi giáo viên dạy thay (trên từng buổi)

```
Admin click vào buổi học → bấm "Đổi giáo viên"

Nhập:
  - teacher_id_mới  (required)
  - custom_salary   (optional) — lương dạy thay riêng

Validation — kiểm tra trùng lịch GV mới:
  → SELECT classes.name, si2.start_time, si2.end_time
    FROM schedule_instances si2
    JOIN classes ON si2.class_id = classes.id
    WHERE si2.teacher_id = teacher_id_mới
      AND si2.date = buổi_hiện_tại.date
      AND si2.id != buổi_hiện_tại.id
      AND si2.status != cancelled
      AND (si2.start_time < buổi_hiện_tại.end_time
           AND si2.end_time > buổi_hiện_tại.start_time)

Nếu pass:
  Service:
    UPDATE schedule_instances
      SET teacher_id = teacher_id_mới,
          original_teacher_id = (nếu chưa có) = teacher_id_cũ,
          custom_salary = ?
      WHERE id = ?

Ghi user_logs: admin X đổi GV buổi [ngày] lớp Y từ [GV cũ] sang [GV mới] lúc Z
```

---

## Kiểm tra trùng lịch

Dùng chung cho mọi luồng tạo/sửa lịch.

### Trùng phòng

```
→ SELECT si.date, si.start_time, si.end_time, classes.name as ten_lop
  FROM schedule_instances si
  JOIN classes ON si.class_id = classes.id
  WHERE si.room_id = ?
    AND si.date = ?
    AND si.id != current_id (nếu đang sửa)
    AND si.status != cancelled
    AND si.start_time < end_time_mới
    AND si.end_time > start_time_mới
→ Nếu có: KHÔNG cho lưu — "Phòng đã có lớp [X] từ [HH:mm]–[HH:mm]"
```

### Trùng giáo viên

```
→ SELECT si.date, si.start_time, si.end_time, classes.name
  FROM schedule_instances si
  JOIN classes ON si.class_id = classes.id
  WHERE si.teacher_id = ?
    AND si.date = ?
    AND si.id != current_id
    AND si.status != cancelled
    AND si.start_time < end_time_mới
    AND si.end_time > start_time_mới
→ Nếu có: KHÔNG cho lưu — "Giáo viên đang có lớp [X] giờ này"
```

### Trùng học sinh

```
→ SELECT students.full_name, classes.name as ten_lop_khac
  FROM schedule_instances si
  JOIN class_enrollments ce ON ce.class_id = si.class_id AND ce.left_at IS NULL
  JOIN students ON ce.student_id = students.id
  WHERE si.date = ?
    AND si.class_id != class_id_mới
    AND si.status != cancelled
    AND si.start_time < end_time_mới
    AND si.end_time > start_time_mới
    AND students.id IN (
      SELECT student_id FROM class_enrollments
      WHERE class_id = class_id_mới AND left_at IS NULL
    )
→ Cảnh báo (không chặn cứng) — "X học sinh bị trùng lịch"
```

---

## Gợi ý slot học bù (dùng cho cả Admin và GV đề xuất)

```
Input: class_id, date_range muốn học bù

Hệ thống tìm slot hợp lệ — một slot hợp lệ khi:
  1. GV không có lịch trùng
  2. Không trùng lịch bất kỳ HS nào trong lớp
  3. Có phòng trống
  4. Không phải ngày nghỉ lễ toàn trung tâm

Query tìm phòng trống trong slot:
  → SELECT rooms.id, rooms.name, rooms.capacity
    FROM rooms
    WHERE rooms.status = Active
      AND rooms.capacity >= (
        SELECT COUNT(*) FROM class_enrollments
        WHERE class_id = ? AND left_at IS NULL
      )
      AND rooms.id NOT IN (
        SELECT room_id FROM schedule_instances
        WHERE date = slot_date
          AND status != cancelled
          AND start_time < slot_end_time
          AND end_time > slot_start_time
      )
    ORDER BY rooms.capacity ASC  ← ưu tiên phòng vừa đủ sĩ số

Hiển thị:
  Ngày 15/03 | 17:30–19:00 | Phòng A3 (Trống)
  Ngày 17/03 | 19:00–20:30 | Phòng B1 (Trống)
```

---

## Đề xuất đổi lịch (GV thực hiện)

### GV tạo đề xuất

```
GV bấm "Đề xuất đổi lịch" tại buổi học của mình
(chỉ được đề xuất buổi có schedule_instances.teacher_id = GV đang đăng nhập)

Nhập:
  - proposed_date       (required)
  - proposed_start_time (required)
  - proposed_end_time   (required)
  - proposed_room_id    (optional)
  - reason              (required)

Service:
  INSERT schedule_change_requests (
    schedule_instance_id,
    requested_by = teachers.id (của GV đang đăng nhập),
    proposed_date, proposed_start_time, proposed_end_time,
    proposed_room_id,
    reason,
    status = pending
  )

Ghi user_logs: GV X tạo đề xuất đổi lịch buổi Y lúc Z
```

### Admin xử lý đề xuất

```
Danh sách đề xuất chờ duyệt:
  → SELECT
      scr.*,
      t.full_name as ten_gv,
      classes.name as ten_lop,
      si.date as ngay_cu, si.start_time as gio_cu, si.end_time as gio_cu_end,
      r_cu.name as phong_cu,
      r_moi.name as phong_moi
    FROM schedule_change_requests scr
    JOIN teachers t ON scr.requested_by = t.id
    JOIN schedule_instances si ON scr.schedule_instance_id = si.id
    JOIN classes ON si.class_id = classes.id
    LEFT JOIN rooms r_cu ON si.room_id = r_cu.id
    LEFT JOIN rooms r_moi ON scr.proposed_room_id = r_moi.id
    WHERE scr.status = pending
    ORDER BY scr.created_at

[Duyệt]:
  Validation: kiểm tra trùng phòng + GV tại slot mới đề xuất
  Service:
    1. UPDATE schedule_instances
         SET date = scr.proposed_date,
             start_time = scr.proposed_start_time,
             end_time = scr.proposed_end_time,
             room_id = COALESCE(scr.proposed_room_id, si.room_id)
         WHERE id = scr.schedule_instance_id

    2. UPDATE schedule_change_requests
         SET status = approved,
             reviewed_by = Auth::id(),
             reviewed_at = now()
         WHERE id = ?

    3. Gửi thông báo đến học sinh lớp đó (G6)

  Ghi user_logs

[Từ chối]:
  Nhập: rejected_reason (required)
  Service:
    UPDATE schedule_change_requests
      SET status = rejected,
          reviewed_by = Auth::id(),
          reviewed_at = now(),
          rejected_reason = ?
      WHERE id = ?
    Gửi thông báo cho GV
  Ghi user_logs
```

---

## Lịch học — Phân quyền Giáo viên

```
GV xem lịch tổng hợp:
  - Xem đầy đủ buổi của mình:
      WHERE si.teacher_id = teachers.id_của_GV

  - Xem toàn trung tâm (chế độ read-only, để biết phòng trống):
      Chỉ hiển thị: tên lớp, phòng, thời gian, loại buổi
      KHÔNG hiển thị: học phí, lương, danh sách HS lớp khác

Bộ lọc của GV:
  - Lớp mình dạy     → WHERE si.class_id IN (SELECT class_id FROM classes WHERE teacher_id = ?)
  - Khoảng thời gian → WHERE si.date BETWEEN ? AND ?
  - Loại buổi        → WHERE si.schedule_type = ?
  - Phòng            → WHERE si.room_id = ?
  - Trạng thái       → WHERE si.status = ?

GV KHÔNG được:
  - Tạo / sửa / xóa lịch trực tiếp
  - Thay đổi phòng, giờ, GV
  - Chỉ được: đề xuất đổi lịch (schedule_change_requests)
```

---

## Lịch học — Phân quyền Học sinh / Phụ huynh

```
HS chỉ thấy lịch của lớp mình đang học:
  → SELECT si.date, si.start_time, si.end_time,
           si.schedule_type, si.status,
           classes.name, subjects.name,
           teachers.full_name,
           rooms.name
    FROM schedule_instances si
    JOIN classes ON si.class_id = classes.id
    JOIN subjects ON classes.subject_id = subjects.id
    JOIN teachers ON si.teacher_id = teachers.id
    JOIN rooms ON si.room_id = rooms.id
    WHERE si.class_id IN (
      SELECT class_id FROM class_enrollments
      WHERE student_id = students.id_của_HS
        AND left_at IS NULL
    )
    AND si.status != cancelled
    ORDER BY si.date, si.start_time

Hiển thị mỗi buổi:
  - Tên lớp, môn, giáo viên, phòng, thời gian, loại buổi
  - Nếu linked_makeup_for != NULL → "Buổi học bù cho ngày [X]"
  - Nếu có BTVN → badge riêng (join attendance_sessions.homework IS NOT NULL)
```

---

## Log lịch sử thay đổi

```
Mọi thay đổi trên schedule_instances đều ghi vào user_logs:

INSERT user_logs (
  user_id = Auth::id(),
  action  = 'schedule_[create/update/cancel/...]',
  description = JSON {
    schedule_instance_id,
    class_id,
    old_values: { date, room_id, teacher_id, status, ... },
    new_values: { ... },
    reason
  },
  created_at = now()
)

Admin xem lịch sử:
  → SELECT ul.created_at, u.username, ul.action, ul.description
    FROM user_logs ul
    JOIN users u ON ul.user_id = u.id
    WHERE ul.action LIKE 'schedule_%'
      AND ul.description->>'schedule_instance_id' = ?
    ORDER BY ul.created_at DESC
```

---

## Phân quyền trong module này

| Chức năng | Admin | Teacher | Staff | Student |
|---|---|---|---|---|
| Xem lịch tổng hợp toàn trung tâm | ✅ | ✅ (giới hạn cột) | ✅ (giới hạn) | ❌ |
| Tạo / sửa lịch cố định | ✅ | ❌ | ❌ | ❌ |
| Tạo học bù / tăng cường | ✅ | ❌ | ❌ | ❌ |
| Hủy / dời buổi học | ✅ | ❌ | ❌ | ❌ |
| Đổi GV dạy thay | ✅ | ❌ | ❌ | ❌ |
| Đề xuất đổi lịch | ❌ | ✅ (lớp mình) | ❌ | ❌ |
| Duyệt đề xuất đổi lịch | ✅ | ❌ | ❌ | ❌ |
| Xem lịch của mình | ✅ | ✅ | ✅ (ca làm) | ✅ (lớp mình) |
| Xem học phí / lương trong lịch | ✅ | ❌ | ❌ | ❌ |

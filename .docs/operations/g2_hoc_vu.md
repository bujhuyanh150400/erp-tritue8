# Phân tích nghiệp vụ — G2: Học vụ

Bao gồm: Môn học, Phòng học, Lớp học, Đăng ký học sinh, Báo cáo tháng

---

## Tổng quan

G2 là nền tảng học vụ của toàn hệ thống. Hầu hết các module khác (lịch học, điểm danh, tài chính) đều phụ thuộc vào dữ liệu được tạo ở đây.

Thứ tự phụ thuộc:
```
subjects ──┐
           ├──► classes ──► class_enrollments
teachers ──┘
rooms  (gắn theo từng buổi học ở G3, không gắn vào lớp)
```

### Quy tắc hệ thống

| Quy tắc | Chi tiết |
|---|---|
| Không xóa subject nếu đã có lớp dùng | Chỉ được deactivate |
| Không xóa room nếu đã có lịch học dùng | Chỉ được khóa |
| Không xóa class nếu đã có dữ liệu | Chỉ được đổi trạng thái |
| Không xóa enrollment | Chỉ được set `left_at` |
| Phòng học gắn theo từng buổi | Không gắn cố định vào lớp |

### Các edge case cần xử lý

| Tình huống | Xử lý |
|---|---|
| Deactivate subject đang có lớp Active | Không cho, cảnh báo danh sách lớp đang dùng |
| Giảm capacity phòng < sĩ số lớp đang dùng | Không cho lưu, báo lớp nào bị ảnh hưởng |
| Thêm HS vào lớp đã Ended | Không cho phép |
| Thêm HS vào lớp đang Suspended | Cảnh báo, admin confirm mới cho |
| HS đăng ký lại lớp đã từng học | Tạo enrollment record mới, giữ lịch sử cũ |
| Đổi GV lớp khi GV mới bị trùng lịch | Cảnh báo trùng lịch, không cho lưu |
| Nhân bản lớp trùng code | Báo lỗi, gợi ý code khác |
| GV nộp báo cáo sau khi tháng đã chốt tài chính | Vẫn cho nộp, báo cáo độc lập với tài chính |

---

## Định nghĩa Enum

```
ClassStatus:
  0 = Active     — đang hoạt động
  1 = Suspended  — tạm ngưng
  2 = Ended      — đã kết thúc

RoomStatus:
  0 = Active      — đang hoạt động
  1 = Locked      — tạm khóa
  2 = Maintenance — đang bảo trì

ReportStatus:
  0 = Draft      — nháp, GV chưa nộp
  1 = Submitted  — đã nộp, chờ admin duyệt
  2 = Approved   — đã duyệt, khóa không cho sửa
  3 = Rejected   — bị từ chối, GV được sửa lại và nộp lại
```

---

## Môn học

### Các bảng liên quan
```
- subjects: Môn học
- classes: Lớp học
- class_enrollments: Đăng ký học sinh
```

### Danh sách

```

Tìm kiếm nhanh: (keyword)
  - Tên môn học → WHERE subjects.name ILIKE '%keyword%'
  - Id môn học → WHERE subjects.id = ?

Bộ lọc:
  - Trạng thái: (is_active)
    Tất cả | Đang hoạt động | Không hoạt động
    → WHERE subjects.is_active = true/false

Nút: [Xóa bộ lọc]

Bảng hiển thị:
  - Tên môn học      → subjects.name
  - Mô tả            → subjects.description
  - Số lớp đang dùng 
    → COUNT(classes.id)
      JOIN classes ON classes.subject_id = subjects.id
       WHERE classes.status = ClassStatus.Active
  - Trạng thái       → subjects.is_active
  - Action
      + Chỉnh sửa
      + Bật / Tắt hoạt động 
        - Tìm subject theo id
        - Kiểm tra môn học có đang được dùng bởi lớp nào không
        -> nếu có trả ra lỗi "Môn học đang được dùng bởi X lớp đang hoạt động, ko thể tắt hoạt động"
        - Nếu is_active = true:
        → UPDATE subjects SET is_active = false
        - Nếu is_active = false:
        → UPDATE subjects SET is_active = true
                
Type resource:
- id: string;
- name: string;
- description: string | null;
- classes_count: number;
- is_active: boolean;

```

### Tạo môn học

```
Form:
    - name        - Tên môn học (required, unique)
    - description - Mô tả (optional)
    - is_active   - Trạng thái (default: true)

Validation:
    - name: required | string | max: 255 
    - description: nullable | string | max: 2000
    - is_active: required | boolean
Service:
    -  Không cho trùng tên
      → SELECT COUNT(*) FROM subjects WHERE name = ? = 0
      -> nếu trùng -> trả ra lỗi "Tên môn học đã tồn tại"
    - INSERT subjects
    - Ghi user_logs: admin X tạo môn học Y lúc Z
```

### Sửa môn học

```
Trả ra form sửa môn học
    - Admin chọn môn học cần sửa
    - Trả về resource bao gồm:
        - id: string;
        - name: string;
        - description: string | null;
        - is_active: boolean;

Form:
    - name        - Tên môn học (required, unique)
    - description - Mô tả (optional)
    - is_active   - Trạng thái (default: true)
    
Validation:
    - id: required | numeric | exists: subjects,id
    - name: required | string | max: 255 
    - description: nullable | string | max: 2000
    - is_active: required | boolean

Service:
    -  Không cho trùng tên với môn khác ngoài id hiện tại
      → SELECT COUNT(*) FROM subjects WHERE name = ? AND id != current_id = ?
      -> nếu trùng -> trả ra lỗi "Tên môn học đã tồn tại"
    - Nếu is_active = false:
      → SELECT COUNT(*) FROM classes
         WHERE subject_id = ? AND status = Active
        Nếu > 0 → "Môn học đang được dùng bởi X lớp đang hoạt động"
    - UPDATE subjects
    - Ghi user_logs: admin X sửa môn học Y lúc Z
```

### Xóa môn học
```
Admin chọn môn học cần xóa

Service:
    - Check không có lớp nào đang dùng môn học này:
      → SELECT COUNT(*) FROM classes
         WHERE subject_id = ? AND status = Active
        Nếu > 0 → "Môn học đang được dùng bởi X lớp đang hoạt động, không thể xóa"
    - DELETE subjects
    - Ghi user_logs: admin X xóa môn học Y lúc Z
```


---

## Phòng học

### Danh sách

```
Tìm kiếm nhanh:
  - Tên phòng → WHERE rooms.name ILIKE '%keyword%'

Bộ lọc:
  - Trạng thái: Tất cả | Hoạt động | Tạm khóa | Bảo trì
    → WHERE rooms.status = ?

Hiển thị tổng số phòng tìm thấy → COUNT(rooms.id)
Nút: [Xóa bộ lọc]

Bảng hiển thị:
  - Tên phòng             → rooms.name
  - Sức chứa              → rooms.capacity
  - Số lớp đang hoạt động → COUNT DISTINCT class_schedule_templates.class_id
                             JOIN class_schedule_templates ON room_id = rooms.id
                               AND (end_date IS NULL OR end_date >= today)
                             JOIN classes ON classes.id = class_id
                               AND classes.status = Active
  - Trạng thái            → rooms.status
  - Ghi chú               → rooms.note
  - Action
      + Xem lịch phòng
      + Chỉnh sửa
      + Khóa / Mở khóa
```

### Tạo phòng

```
1. Admin nhập:
    - name     - Tên phòng (required, unique)
    - capacity - Sức chứa (required, > 0)
    - status   - Trạng thái (default: Active)
    - note     - Ghi chú (optional)

2. Validation:
    - Không cho trùng tên
      → SELECT COUNT(*) FROM rooms WHERE name = ? = 0
    - capacity > 0

3. Service:
    - INSERT rooms

4. Ghi user_logs: admin X tạo phòng Y lúc Z
```

### Sửa phòng

```
1. Admin chọn phòng cần sửa

2. Admin sửa:
    - name     - Tên phòng (required, unique)
    - capacity - Sức chứa (required, > 0)
    - status   - Trạng thái
    - note     - Ghi chú (optional)

3. Validation:
    - Không cho trùng tên
      → SELECT COUNT(*) FROM rooms WHERE name = ? AND id != current_id = 0

    - Nếu giảm capacity:
      → SELECT classes.name, COUNT(ce.id) as si_so
         FROM class_schedule_templates cst
         JOIN classes ON classes.id = cst.class_id
         JOIN class_enrollments ce ON ce.class_id = classes.id
           AND ce.left_at IS NULL
         WHERE cst.room_id = ?
           AND (cst.end_date IS NULL OR cst.end_date >= today)
         GROUP BY classes.id
         HAVING si_so > capacity_mới
        → "Phòng đang có lớp [X] với [Y] học sinh, không thể giảm xuống [Z] chỗ"

    - Nếu đổi status → Locked / Maintenance:
      + Kiểm tra không có lớp nào đang diễn ra ở phòng này
      SELECT COUNT(*) FROM class_schedule_templates
         WHERE room_id = ?
           AND (end_date IS NULL OR end_date >= today)
        Nếu > 0 -> Lock: Không thể khóa phòng khi có lớp đang diễn ra ở phòng này
                -> Maintenance: lịch cũ giữ nguyên, chỉ chặn tạo mới
      

4. Service:
    - UPDATE rooms

5. Ghi user_logs: admin X sửa phòng Y lúc Z
```

### Trang chi tiết phòng

```
Thông tin chung:
  → SELECT * FROM rooms WHERE id = ?

Danh sách lớp đang sử dụng phòng:
  → SELECT classes.name,
           cst.day_of_week, cst.start_time, cst.end_time,
           COUNT(ce.id) as si_so
    FROM class_schedule_templates cst
    JOIN classes ON classes.id = cst.class_id
    LEFT JOIN class_enrollments ce ON ce.class_id = classes.id
      AND ce.left_at IS NULL
    WHERE cst.room_id = ?
      AND (cst.end_date IS NULL OR cst.end_date >= today)
      AND classes.status = Active
    GROUP BY classes.id, cst.id
  Bảng: Lớp | Thứ | Giờ | Sĩ số

Lịch phòng theo tuần (để kiểm tra khung giờ trống):
  → SELECT si.date, si.start_time, si.end_time, classes.name
    FROM schedule_instances si
    JOIN classes ON classes.id = si.class_id
    WHERE si.room_id = ?
      AND si.date BETWEEN [đầu tuần] AND [cuối tuần]
      AND si.status != cancelled
    ORDER BY si.date, si.start_time
```



---

## Lớp học

### Danh sách

```
Tìm kiếm nhanh:
  - Tên lớp  → WHERE classes.name ILIKE '%keyword%'
  - Mã lớp   → OR classes.code ILIKE '%keyword%'
  - Tên GV   → OR teachers.full_name ILIKE '%keyword%'
               (cần JOIN teachers ON classes.teacher_id = teachers.id)

Bộ lọc:
  - Trạng thái → WHERE classes.status = ?
  - Môn học    → WHERE classes.subject_id = ?
  - Khối       → WHERE classes.grade_level = ?
  - Giáo viên  → WHERE classes.teacher_id = ?

Hiển thị tổng số lớp tìm thấy → COUNT(classes.id)
Nút: [Xóa bộ lọc]

Bảng hiển thị:
  - Mã lớp         → classes.code
  - Tên lớp        → classes.name
  - Môn học        → subjects.name
                      JOIN subjects ON classes.subject_id = subjects.id
  - Khối           → classes.grade_level
  - Giáo viên      → teachers.full_name
                      JOIN teachers ON classes.teacher_id = teachers.id
  - Số học sinh    → COUNT(class_enrollments.id)
                      JOIN class_enrollments ON class_id = classes.id
                      WHERE left_at IS NULL
  - Học phí/buổi  → classes.base_fee_per_session
  - Lương GV/buổi → classes.teacher_salary_per_session
  - Lịch học      → GROUP lại từ class_schedule_templates
                      WHERE class_id = classes.id
                        AND (end_date IS NULL OR end_date >= today)
                      Hiển thị tóm tắt: "T2, T4 | 17:00–19:00"
  - Trạng thái    → classes.status
  - Action
      + Xem chi tiết
      + Điểm danh
      + Danh sách học sinh
      + Chỉnh sửa
      + Tạm ngưng / Kết thúc / Nhân bản
```

### Tạo lớp

```
Form:
    - code                       - Mã lớp (required, unique)
        + Mã lớp sẽ tự động tạo theo tên lớp và khối
    - name                       - Tên lớp (required)
    - subject_id                 - Môn học (required)
                                   → SELECT id, name FROM subjects WHERE is_active = true
    - teacher_id                 - Giáo viên (required)
                                   → SELECT id, full_name FROM teachers WHERE status = Active
    - grade_level                - Khối (required, 6–12)
    - base_fee_per_session       - Học phí cơ bản/buổi (required)
    - teacher_salary_per_session - Lương GV cơ bản/buổi (required)
    - max_students               - Sĩ số tối đa (required, > 0)
    - start_at                   - Ngày khai giảng (required)
    - end_at                     - Ngày kết thúc (optional)

Validation:
    - code unique: SELECT COUNT(*) FROM classes WHERE code = ? = 0
    - subject active: SELECT is_active FROM subjects WHERE id = ? = true
    - teacher active: SELECT status FROM teachers WHERE id = ? = Active

Service:
    - INSERT classes (status = Active)

Ghi user_logs: admin X tạo lớp Y lúc Z
```

### Sửa lớp

```
Form:
    - code                       - Mã lớp (Không thể sửa)
    - name                       - Tên lớp (required)
    - subject_id                 - Môn học (required)
                                   → SELECT id, name FROM subjects WHERE is_active = true
    - teacher_id                 - Giáo viên (Không thể sửa)
    - grade_level                - Khối (required, 6–12)
    - base_fee_per_session       - Học phí cơ bản/buổi (required)
    - teacher_salary_per_session - Lương GV cơ bản/buổi (required)
    - max_students               - Sĩ số tối đa (required, > 0)
    - start_at                   - Ngày khai giảng (Không thể sửa)
    - end_at                     - Ngày kết thúc (optional)

Validation:
    - Không cho sửa subject_id nếu đã có buổi học:
      → SELECT COUNT(*) FROM schedule_instances WHERE class_id = ? > 0
    - max_students >= số HS active:
      → SELECT COUNT(*) FROM class_enrollments
         WHERE class_id = ? AND left_at IS NULL

Service:
    - UPDATE classes

Ghi user_logs: admin X sửa lớp Y lúc Z
```

### Đổi giáo viên phụ trách

```
Nhập:
  - teacher_id mới
    → SELECT id, full_name FROM teachers WHERE status = Active

Validation — kiểm tra trùng lịch GV mới:
  → SELECT si.date, si.start_time, si.end_time, c.name as ten_lop
    FROM schedule_instances si
    JOIN classes c ON c.id = si.class_id
    WHERE si.teacher_id = teacher_id_mới
      AND si.date >= today
      AND si.status != cancelled
      AND EXISTS (
        SELECT 1 FROM schedule_instances si2
        WHERE si2.class_id = class_id_hiện_tại
          AND si2.date = si.date
          AND si2.start_time < si.end_time
          AND si2.end_time > si.start_time
      )
  → Nếu có kết quả: hiển thị danh sách buổi trùng, không cho lưu

Nếu pass:
  - UPDATE classes SET teacher_id = teacher_id_mới
  - UPDATE schedule_instances
      SET teacher_id = teacher_id_mới
      WHERE class_id = ? AND date >= today AND status = scheduled

Ghi user_logs: admin X đổi GV lớp Y từ [GV cũ] sang [GV mới] lúc Z
```

### Đổi trạng thái lớp

```
Active → Suspended:
  - UPDATE classes SET status = Suspended
  - UPDATE schedule_instances SET status = cancelled
    WHERE class_id = ? AND date >= today AND status = scheduled
  - Ghi user_logs

Suspended → Active:
  - UPDATE classes SET status = Active
  - Ghi user_logs

Active / Suspended → Ended:
  - UPDATE classes SET status = Ended, end_at = today (nếu chưa có)
  - UPDATE class_enrollments SET left_at = today
    WHERE class_id = ? AND left_at IS NULL
  - Ghi user_logs

Ràng buộc không cho xóa:
  → SELECT COUNT(*) FROM attendance_sessions WHERE class_id = ? > 0
     OR COUNT(*) FROM tuition_invoices WHERE class_id = ? > 0
  → Không cho xóa, chỉ được đổi trạng thái
```
### Thêm học sinh vào lớp

```
List các học sinh chưa đăng ký trong lớp:
  → SELECT students.id, students.full_name, students.grade_level
    FROM students
    JOIN users ON students.user_id = users.id
    WHERE users.is_active = true
      AND students.full_name ILIKE '%keyword%'
      AND students.id NOT IN (
        SELECT student_id FROM class_enrollments
        WHERE class_id = ? AND left_at IS NULL
      )

Nhập:
  - students (required) - Danh sách học sinh (array of student_id)
  - enrolled_at     (required, default: today)
  - fee_per_session (optional) — NULL = dùng classes.base_fee_per_session
  - note            (optional)

Validation:
  1. Chưa có enrollment active:
       SELECT COUNT(*) FROM class_enrollments
       WHERE class_id = ? AND student_id = ? AND left_at IS NULL = 0

  2. Lớp Active:
       SELECT status FROM classes WHERE id = ? = Active

  3. Kiểm tra sĩ số lớp:
  - Lấy sĩ số hiện tại:
       SELECT COUNT(*) FROM class_enrollments
       WHERE class_id = ? AND left_at IS NULL
  - So sánh với students.count + sĩ số lớp hiện tại <= classes.max_students
  - Báo lỗi nếu vượt quá sĩ số lớp

  4. Kiểm tra trùng lịch:
       SELECT si.date, si.start_time, si.end_time, c2.name as ten_lop_khac
       FROM schedule_instances si
       JOIN class_enrollments ce ON ce.student_id = ?
         AND ce.class_id = si.class_id
         AND ce.left_at IS NULL
       JOIN classes c2 ON c2.id = si.class_id
       JOIN class_schedule_templates cst_new
         ON cst_new.class_id = class_id_mới
         AND cst_new.day_of_week = EXTRACT(DOW FROM si.date)
         AND cst_new.start_time < si.end_time
         AND cst_new.end_time > si.start_time
       WHERE si.date >= enrolled_at AND si.status != cancelled

Service:
  - INSERT class_enrollments (
      class_id, student_id, enrolled_at,
      fee_per_session,
      fee_effective_from = enrolled_at (nếu có fee_per_session),
      fee_effective_to = NULL
    )

Ghi user_logs: admin X thêm HS Y vào lớp Z lúc T
```

### Trang chi tiết lớp

#### Tab 1 — Thông tin lớp:
```
    - Tên lớp
    - Mã lớp
    - Môn học
    - Giáo viên
    - Khối
    - Học phí cơ bản/buổi
    - Lương GV cơ bản/buổi
    - Sĩ số tối đa
    - Ngày khai giảng
    - Ngày kết thúc (nếu có)
  → SELECT classes.*, subjects.name as ten_mon, teachers.full_name as ten_gv
    FROM classes
    JOIN subjects ON classes.subject_id = subjects.id
    JOIN teachers ON classes.teacher_id = teachers.id
    WHERE classes.id = ?
```

#### Tab 2 — Danh sách học sinh:
```
  - Danh sách hiển thị
    + Họ tên HS
    + Ngày vào lớp
    + Học phí/buổi
    + Tổng buổi có mặt
    + Tổng buổi nghỉ
  → SELECT
      students.id, students.full_name,
      ce.enrolled_at,
      ce.fee_per_session,
      ce.left_at,
      COUNT(ar.id) FILTER (WHERE ar.status IN (present, late))  as total_present, // Số buổi có mặt
      COUNT(ar.id) FILTER (WHERE ar.status IN (excused, absent)) as total_absent, // Số buổi nghỉ
      [tổng tiền tháng: tính riêng theo logic học phí]
    FROM class_enrollments ce
    JOIN students ON ce.student_id = students.id
    LEFT JOIN attendance_sessions as_sess ON as_sess.class_id = ce.class_id
    LEFT JOIN attendance_records ar ON ar.session_id = as_sess.id
      AND ar.student_id = ce.student_id
    WHERE ce.class_id = ?
      AND ce.left_at IS NULL
    GROUP BY students.id, ce.id

  Action mỗi dòng: Thiết lập học phí riêng | Chuyển lớp | Cho nghỉ
```

##### Action mỗi dòng:
- Thiết lập học phí riêng:
```
    Mở popup cho học sinh đó
    Hiển thị lịch sử học phí hiện tại:
      → SELECT ce.fee_per_session, ce.fee_effective_from, ce.fee_effective_to
        FROM class_enrollments ce
        WHERE ce.class_id = ? AND ce.student_id = ?
        ORDER BY ce.fee_effective_from ASC
      Bảng: Học phí/buổi | Từ ngày | Đến ngày
    Form nhập giai đoạn mới:
      - fee_per_session    (required) — học phí mới
      - fee_effective_from (required, default: today) — áp dụng từ ngày
    Validation:
      - fee_effective_from >= enrolled_at của record gốc
      - fee_effective_from > fee_effective_from của record hiện hành
    Service:
      1. UPDATE class_enrollments
           SET fee_effective_to = fee_effective_from_mới - 1 ngày
           WHERE class_id = ? AND student_id = ?
             AND fee_effective_to IS NULL
             AND fee_per_session IS NOT NULL
    
      2. INSERT class_enrollments (
           class_id, student_id,
           fee_per_session = mới,
           fee_effective_from = ngày mới,
           fee_effective_to = NULL,
           enrolled_at = (lấy từ record gốc đầu tiên WHERE class_id=? AND student_id=? ORDER BY enrolled_at ASC LIMIT 1),
           left_at = NULL
         )
    
    Ghi user_logs: admin X đổi học phí HS Y lớp Z từ [giá cũ] sang [giá mới] lúc T
```
- Chuyển lớp:
```
Hiển thị thông tin hiện tại:
      - Tên HS, lớp hiện tại, ngày vào lớp
    
    Form:
      - class_id_mới (required) — dropdown chọn lớp
        → SELECT id, name, subjects.name as ten_mon, grade_level
          FROM classes
          JOIN subjects ON subject_id = subjects.id
          WHERE classes.status = Active
            AND classes.id != class_id_hiện_tại
      - left_at (required, default: today) — ngày rời lớp cũ
      - fee_per_session (optional) — học phí tại lớp mới (NULL = dùng base của lớp mới)
      - note (optional)
    
    Validation:
      - Lớp mới phải Active
      - left_at >= ce.enrolled_at (không rời trước ngày vào)
      - Kiểm tra trùng lịch tại lớp mới:
          SELECT si.date, si.start_time, si.end_time, classes.name as lop_trung
          FROM schedule_instances si
          JOIN class_schedule_templates cst_new ON cst_new.class_id = class_id_mới
            AND cst_new.day_of_week = EXTRACT(DOW FROM si.date)
            AND cst_new.start_time < si.end_time
            AND cst_new.end_time > si.start_time
          JOIN class_enrollments ce_existing
            ON ce_existing.student_id = student_id
            AND ce_existing.class_id = si.class_id
            AND ce_existing.left_at IS NULL
            AND ce_existing.class_id != class_id_hiện_tại
          WHERE si.date >= left_at AND si.status != cancelled
          → Cảnh báo nếu có trùng (không chặn cứng)
    
    Service (transaction):
      1. UPDATE class_enrollments SET left_at = ?
         WHERE class_id = class_cũ AND student_id = ? AND left_at IS NULL
    
      2. INSERT class_enrollments (
           class_id = class_mới,
           student_id,
           enrolled_at = left_at,
           fee_per_session,
           fee_effective_from = left_at (nếu có fee_per_session),
           left_at = NULL
         )
    
    Ghi user_logs: admin X chuyển HS Y từ lớp Z sang lớp T lúc U
```
- Cho nghỉ: 
```
    Mở popup xác nhận cho học sinh đó
    Hiển thị:
      - Tên HS
      - Lớp hiện tại
      - Cảnh báo nếu HS còn hóa đơn chưa thanh toán:
          SELECT COUNT(*) FROM tuition_invoices
          WHERE student_id = ? AND class_id = ? AND status != paid
          → "HS còn X hóa đơn chưa thanh toán"
    
    Form:
      - left_at (required, default: today) — ngày nghỉ
    
    Validation:
      - left_at >= ce.enrolled_at
    
    Service:
      - UPDATE class_enrollments SET left_at = ?
        WHERE class_id = ? AND student_id = ? AND left_at IS NULL
    
    Lưu ý sau khi nghỉ:
      - Các attendance_records có session_date > left_at:
          is_fee_counted = false khi tính hóa đơn tháng
      - HS vẫn được đăng ký lại lớp này sau (INSERT enrollment record mới)
      - Không xóa lịch sử điểm danh / điểm số đã có
    
    Ghi user_logs: admin X cho HS Y nghỉ lớp Z từ ngày T lúc U
  
  Nút Header [Thêm học sinh vào lớp]
```


#### Tab 3 — Lịch sử buổi học:
```
  → SELECT
      si.date, si.schedule_type, si.status,
      r.name as phong,
      t.full_name as giao_vien,
      as_sess.general_note,
      COUNT(ar.id) FILTER (WHERE ar.status IN (present, late)) as so_hs_co_mat
    FROM schedule_instances si
    LEFT JOIN rooms r ON si.room_id = r.id
    LEFT JOIN teachers t ON si.teacher_id = t.id
    LEFT JOIN attendance_sessions as_sess ON as_sess.schedule_instance_id = si.id
    LEFT JOIN attendance_records ar ON ar.session_id = as_sess.id
    WHERE si.class_id = ?
    GROUP BY si.id, r.id, t.id, as_sess.id
    ORDER BY si.date DESC

  Action mỗi dòng: Xem điểm danh | Tạo buổi bù | Hủy buổi | Đổi phòng học
```
##### Action mỗi dòng:
- Xem điểm danh
```
Điều kiện hiển thị:
  - attendance_sessions tồn tại cho buổi này (as_sess.id IS NOT NULL)
  - Nếu chưa có → nút "Bắt đầu điểm danh" thay thế (dẫn sang G4)

Click → Navigate sang trang điểm danh của buổi đó
  → /attendance-sessions/{as_sess.id}
  (Chi tiết xử lý ở G4)
```
- Tạo buổi bù
```
Điều kiện hiển thị:
  - si.status = cancelled HOẶC admin muốn thêm buổi bù thủ công

Mở popup

Hiển thị thông tin buổi gốc:
  - Ngày bị hủy, lớp, giáo viên

Form:
  - date           (required) — ngày học bù
  - start_time     (required, default: si.start_time)
  - end_time       (required, default: si.end_time)
  - room_id        (required)
    → SELECT id, name FROM rooms WHERE status = Active
  - teacher_id     (required, default: si.teacher_id)
    → SELECT id, full_name FROM teachers WHERE status = Active
  - fee_type       (required, default: normal)
      0 = normal | 1 = free | 2 = custom
  - custom_fee_per_session (hiện ra nếu fee_type = custom)
  - custom_salary  (optional) — lương GV buổi bù này

Validation:
  - Kiểm tra trùng phòng:
      SELECT si2.start_time, si2.end_time, classes.name
      FROM schedule_instances si2
      JOIN classes ON si2.class_id = classes.id
      WHERE si2.room_id = ? AND si2.date = ?
        AND si2.status != cancelled
        AND si2.start_time < end_time_mới
        AND si2.end_time > start_time_mới
      → Nếu có: "Phòng đã có lớp [X] từ [HH:mm]–[HH:mm]"

  - Kiểm tra trùng GV:
      SELECT si2.start_time, si2.end_time, classes.name
      FROM schedule_instances si2
      JOIN classes ON si2.class_id = classes.id
      WHERE si2.teacher_id = ? AND si2.date = ?
        AND si2.status != cancelled
        AND si2.start_time < end_time_mới
        AND si2.end_time > start_time_mới
      → Nếu có: "Giáo viên đang có lớp [X] giờ này"

Service:
  INSERT schedule_instances (
    class_id = si.class_id,
    template_id = NULL,
    date, start_time, end_time,
    room_id, teacher_id,
    original_teacher_id = teacher_id,
    teacher_salary_snapshot = (
      SELECT COALESCE(
        (SELECT salary_per_session FROM teacher_salary_configs
         WHERE teacher_id = ? AND class_id = ?
           AND effective_from <= date
           AND (effective_to IS NULL OR effective_to >= date)
         LIMIT 1),
        (SELECT teacher_salary_per_session FROM classes WHERE id = ?)
      )
    ),
    custom_salary,
    schedule_type = makeup,
    status = scheduled,
    linked_makeup_for = si.id,  ← trỏ về buổi gốc bị hủy
    fee_type, custom_fee_per_session,
    created_by = Auth::id()
  )

Ghi user_logs: admin X tạo buổi bù lớp Y ngày Z cho buổi [ngày gốc] lúc T
```
- Hủy buổi
```
Điều kiện hiển thị:
  - si.status = scheduled (chưa diễn ra)

Mở popup xác nhận

Hiển thị:
  - Ngày học, giờ, phòng, giáo viên
  - Cảnh báo nếu buổi diễn ra trong vòng 6 giờ:
      → "Buổi học sắp diễn ra, bạn có muốn gửi thông báo khẩn không?"
      → Checkbox [✓] Gửi thông báo khẩn cho học sinh

Form:
  - reason (optional) — lý do hủy
  - is_fee_counted (boolean, default: false)
      "Có tính tiền buổi này không?"

Service:
  1. UPDATE schedule_instances SET status = cancelled WHERE id = ?

  2. Nếu is_fee_counted = false:
     UPDATE attendance_records SET is_fee_counted = false
     WHERE session_id IN (
       SELECT id FROM attendance_sessions WHERE schedule_instance_id = ?
     )
     ← áp dụng nếu đã có attendance_session (điểm danh rồi mới hủy)

  3. Nếu checkbox gửi thông báo khẩn → trigger notification (G6)

Ghi user_logs: admin X hủy buổi [ngày] lớp Y lúc Z, lý do: [reason]
```
- Đổi phòng học


#### Tab 4 — Báo cáo nhanh:
  → Tổng doanh thu tháng:
      SELECT SUM(total_study_fee) FROM tuition_invoices
      WHERE class_id = ? AND month = 'YYYY-MM'

  → Tổng lương GV tháng:
      SELECT SUM(total_amount) FROM teacher_salary_invoices
      WHERE class_id = ? AND month = 'YYYY-MM'

  → Tỷ lệ chuyên cần:
      SELECT
        COUNT(*) FILTER (WHERE ar.status IN (present, late)) * 100.0
        / NULLIF(COUNT(*), 0) as ty_le
      FROM attendance_records ar
      JOIN attendance_sessions as_sess ON ar.session_id = as_sess.id
      WHERE as_sess.class_id = ?
        AND as_sess.session_date BETWEEN [đầu tháng] AND [cuối tháng]

  → HS nghỉ > 2 buổi tháng:
      SELECT students.full_name, COUNT(*) as so_buoi_nghi
      FROM attendance_records ar
      JOIN students ON ar.student_id = students.id
      JOIN attendance_sessions as_sess ON ar.session_id = as_sess.id
      WHERE as_sess.class_id = ?
        AND ar.status IN (excused, absent)
        AND as_sess.session_date BETWEEN [đầu tháng] AND [cuối tháng]
      GROUP BY students.id
      HAVING COUNT(*) > 2
```


---


### Cho học sinh nghỉ học

```
Nhập:
  - left_at (required, default: today)

Service:
  - UPDATE class_enrollments SET left_at = ?
    WHERE class_id = ? AND student_id = ? AND left_at IS NULL

Lưu ý:
  - Attendance_records sau left_at: is_fee_counted = false khi tính hóa đơn
  - Có thể đăng ký lại → INSERT enrollment record mới

Ghi user_logs: admin X cho HS Y nghỉ lớp Z từ ngày T lúc U
```

---

## Báo cáo tháng

### Danh sách (Admin xem)

```
Bộ lọc:
  - Tháng      (required, default: tháng hiện tại)
    → WHERE monthly_reports.month = 'YYYY-MM'
  - Lớp        → WHERE monthly_reports.class_id = ?
  - Giáo viên  → WHERE monthly_reports.teacher_id = ?
  - Trạng thái → WHERE monthly_reports.status = ?

Bảng hiển thị:
  - Giáo viên  → teachers.full_name
                  JOIN teachers ON monthly_reports.teacher_id = teachers.id
  - Lớp        → classes.name
                  JOIN classes ON monthly_reports.class_id = classes.id
  - Học sinh   → students.full_name
                  JOIN students ON monthly_reports.student_id = students.id
  - Tháng      → monthly_reports.month
  - Trạng thái → monthly_reports.status
  - Ngày nộp   → monthly_reports.submitted_at
  - Người duyệt→ users.username
                  JOIN users ON monthly_reports.reviewed_by = users.id
  - Action: Xem nội dung | Duyệt | Yêu cầu chỉnh sửa
```

### Giáo viên nhập và nộp báo cáo

```
GV vào lớp → chọn tháng

Lấy danh sách HS trong lớp tháng đó:
  → SELECT
      students.id, students.full_name,
      mr.id as report_id, mr.content, mr.status, mr.reject_reason
    FROM class_enrollments ce
    JOIN students ON ce.student_id = students.id
    LEFT JOIN monthly_reports mr
      ON mr.class_id = ce.class_id
     AND mr.student_id = students.id
     AND mr.teacher_id = ?        ← GV đang đăng nhập
     AND mr.month = 'YYYY-MM'
    WHERE ce.class_id = ?
      AND ce.enrolled_at <= last_day_of_month
      AND (ce.left_at IS NULL OR ce.left_at >= first_day_of_month)

Lưu nháp:
  - Nếu chưa có report → INSERT monthly_reports (status = Draft)
  - Nếu đã có Draft    → UPDATE content
  - submitted_at = NULL

Khi bấm "Nộp báo cáo":
  Validation: content NOT NULL AND TRIM(content) != ''
  - UPDATE monthly_reports
      SET status = Submitted, submitted_at = now()
      WHERE teacher_id = ? AND class_id = ? AND month = ?
        AND status IN (Draft, Rejected)

Ràng buộc:
  - UNIQUE(teacher_id, class_id, student_id, month)
  - status = Submitted → không cho sửa (chỉ trừ khi Rejected)
```

### Admin duyệt báo cáo

```
Duyệt:
  - UPDATE monthly_reports
      SET status = Approved,
          reviewed_at = now(),
          reviewed_by = admin_id   ← users.id của admin đang đăng nhập
    WHERE id = ?

Yêu cầu chỉnh sửa:
  Nhập: reject_reason (required, không được để trống)
  - UPDATE monthly_reports
      SET status = Rejected,
          reviewed_at = now(),
          reviewed_by = admin_id,
          reject_reason = ?
    WHERE id = ?

Lưu ý: Sau khi Approved → không được hủy duyệt
Ghi user_logs cho cả 2 action
```

### Giáo viên chỉnh sửa sau khi bị từ chối

```
Điều kiện hiển thị nút sửa:
  monthly_reports.status = Rejected
  AND monthly_reports.teacher_id = GV đang đăng nhập

GV thấy: reject_reason (đọc từ monthly_reports.reject_reason)
Cho phép sửa: content

Khi nộp lại:
  - UPDATE monthly_reports
      SET status = Submitted,
          content = nội_dung_mới,
          submitted_at = now(),
          reviewed_at = NULL,
          reviewed_by = NULL,
          reject_reason = NULL
    WHERE id = ? AND status = Rejected
```

---

## Phân quyền trong module này

| Chức năng | Admin | Teacher | Staff | Student |
|---|---|---|---|---|
| Quản lý môn học | ✅ | ❌ | ❌ | ❌ |
| Quản lý phòng học | ✅ | ❌ | ❌ | ❌ |
| Tạo / sửa / đổi trạng thái lớp | ✅ | ❌ | ❌ | ❌ |
| Xem danh sách lớp | ✅ | Chỉ lớp mình | ❌ | Chỉ lớp mình |
| Thêm / chuyển / cho nghỉ HS | ✅ | ❌ | ❌ | ❌ |
| Xem danh sách HS trong lớp | ✅ | Chỉ lớp mình | ❌ | ❌ |
| Thiết lập học phí riêng | ✅ | ❌ | ❌ | ❌ |
| Nhập báo cáo tháng | ❌ | Chỉ lớp mình | ❌ | ❌ |
| Duyệt báo cáo tháng | ✅ | ❌ | ❌ | ❌ |
| Xem báo cáo tháng | ✅ | Chỉ lớp mình | ❌ | Chỉ của mình |

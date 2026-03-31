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
**Ngữ cảnh:** Giáo viên vào lớp và chọn một buổi học cụ thể trên lịch để bắt đầu điểm danh.

**Luồng xử lý (Flow):**
1. Hệ thống kiểm tra buổi học đã có `attendance_session` (Phiên điểm danh) hay chưa.
2. Nếu ĐÃ CÓ → Chuyển hướng thẳng vào Màn hình Chi tiết Điểm danh.
3. Nếu CHƯA CÓ → Tiến hành kiểm tra các điều kiện (Validation) và tạo mới.

**Ràng buộc (Validation):**
- **Trạng thái lịch học:** Chỉ cho phép mở điểm danh nếu trạng thái của buổi học (`schedule_instances.status`) 
là `Upcoming` hoặc `Completed`. Chặn nếu đã bị `Cancelled` hoặc `Rescheduled`.

- **Thời gian:** Chỉ cho phép mở phiên nếu ngày học nhỏ hơn hoặc bằng ngày hiện tại (`date <= today`). 
**Tuyệt đối không** cho phép mở điểm danh cho các buổi học trong tương lai để bảo toàn tính chính xác của sĩ số lớp.

- **Phân quyền:** - Admin: Toàn quyền truy cập.
  - Giáo viên: Chỉ được mở điểm danh nếu là giáo viên phụ trách của buổi học đó.
  
- **Loại lịch học:** Cho phép điểm danh với lịch Học chính, Học bù, Tăng cường. Chặn điểm danh nếu là lịch Nghỉ lễ (`Holiday`).

**Thực thi tạo mới (Action):**
- Sử dụng cơ chế Atomic (`firstOrCreate` hoặc Transaction) để tạo duy nhất 1 bản ghi `attendance_sessions`.
- **Lưu ý kiến trúc:** Chỉ tạo "Vỏ" Session. KHÔNG tự động sinh (bulk insert) danh sách học sinh vào bảng `attendance_records` lúc này để tránh rác dữ liệu và xử lý được bài toán học sinh mới nhập học phút chót.
```

### Trang điểm danh (màn hình chính của GV)

#### 1. Thông tin buổi học & Nội dung chung
```
*Hiển thị các thông tin chung của buổi học và cho phép giáo viên chỉnh sửa ghi chú.*

**Dữ liệu hiển thị:**
- **Thông tin lớp & Nhân sự:** 
    + Tên Lớp
    + Tên Môn học
    + Giáo viên phụ trách
    + Tên Phòng học.
- **Thời gian & Trạng thái:** 
    + Ngày học
    + Khung giờ học
    + Loại lịch
    + Trạng thái phiên (Đang mở / Đã chốt).
- **Nội dung:** 
    + Nội dung bài giảng
    + Bài tập về nhà
    + Dặn dò buổi sau
    + Ghi chú nội bộ.

**Thao tác:** - Nút "Sửa thông tin": Mở popup để giáo viên nhập nhanh nội dung bài giảng, Bài tập về nhà, dặn dò, ghi chú nội bộ.
```


#### 2. Bảng học sinh
```
*Hiển thị danh sách lớp thực tế tại thời điểm diễn ra buổi học và bộ công cụ nhập liệu.*

Logic lấy danh sách (Data Fetching):
- Chỉ lấy các học sinh có trạng thái **đang theo học** tại lớp đó vào đúng ngày diễn ra buổi học (`enrolled_at <= session_date` và `left_at >= session_date` hoặc `null`).
- Tự động map với dữ liệu điểm danh cũ (nếu giáo viên đã từng lưu trước đó).
- Lấy tổng số Sao hiện tại của học sinh để hiển thị.

Giao diện nhập liệu (Table & Search):
- Thanh tìm kiếm: Lọc nhanh học sinh theo tên.
-  bao gồm:
  - Tên học sinh (In đậm) & Tổng số sao tích lũy.
  - Trạng thái điểm danh : Có mặt | Đi muộn | Vắng có phép | Vắng không phép.

- Action:
  - Nút "Điểm danh"

```


### Điểm danh từng học sinh

```
## 1. Có mặt (Present)
- **Action:** Click trực tiếp (Cá nhân hoặc Hàng loạt).
- **Validate:** Không có.
- **Service:** Lưu `is_fee_counted = true`, `check_in_time` = giờ bắt đầu lớp.

## 2. Đi muộn (Late)
- **Action:** Mở Form nhập giờ đến.
- **Validate:** Giờ nhập vào phải >= `start_time` và <= `end_time` của lớp học.
- **Service:** Lưu `is_fee_counted = false`, `check_in_time` = giờ GV nhập từ form.

## 3. Vắng có phép (Absent Excused)
- **Action:** Mở Form nhập lý do xin nghỉ.
- **Validate:** Bắt buộc nhập `reason_absent`.
- **Service:** Lưu `is_fee_counted = false`, `check_in_time = null`, lưu `reason_absent`. Đồng thời **xóa bản ghi điểm** của HS này trong bảng `scores` (nếu có).

## 4. Vắng không phép (Absent)
- **Action:** Click trực tiếp.
- **Validate:** Không có.
- **Service:** Lưu `is_fee_counted = false`, `check_in_time = null`, `reason_absent = null`. Đồng thời **xóa bản ghi điểm** của HS này trong bảng `scores` (nếu có).
```

### Nhập điểm kiểm tra

```
## 1. Thao tác (Action)
- **Hình thức:** Mở Modal chuyên sâu thông qua Action đơn lẻ trên từng dòng học sinh.
- **Trạng thái hiển thị:** - **Ẩn/Hiện:** Nút "Chấm điểm" chỉ khả dụng khi học sinh có trạng thái **Có mặt (Present)** hoặc **Đi muộn (Late)**.
    - **Khóa (Disabled):** Tự động vô hiệu hóa nếu học sinh **Vắng mặt** (Có phép hoặc Không phép).
- **Giao diện Form:**  nhập nhiều đầu điểm cùng lúc (BTVN, Kiểm tra miệng, Test 15p...).

## 2. Ràng buộc & Kiểm soát (Validate)
- **Tên bài (exam_name):** Bắt buộc nhập (Required), mặc định gợi ý "BTVN".
- **Điểm số (score):** Bắt buộc nhập, định dạng số (Numeric), giới hạn từ `0` đến `max_score`.
- **Thang điểm (max_score):** Mặc định là `10`.
- **Ràng buộc logic:** - Phải tồn tại bản ghi điểm danh (`attendance_records`) trước khi lưu điểm.
    - Một học sinh không được có hai đầu điểm trùng `exam_slot` trong cùng một buổi học.

## 3. Xử lý hệ thống (Service Logic)
- **Cơ chế Đồng bộ (Sync):** - Dựa trên vị trí hàng trong Repeater để định danh `exam_slot` (1, 2, 3...).
    - **Update/Create:** Cập nhật nếu đã có điểm tại slot đó, hoặc tạo mới nếu chưa có.
    - **Delete (Dọn rác):** Nếu giáo viên xóa một hàng trong Repeater, hệ thống phải thực hiện `delete` bản ghi tương ứng trong database để tránh dữ liệu thừa.
- **Tính toàn vẹn:** Tất cả thao tác lưu danh sách điểm phải nằm trong **Database Transaction**.

## 4. Hiển thị & Phản hồi (UI/UX)
- **Thu gọn (Overview):** Trên bảng chính chỉ hiển thị tối đa 2 đầu điểm quan trọng nhất (Badge). Các điểm còn lại được thu gọn dưới dạng nút `+N`.
- **Chi tiết (Deep Dive):** Khi click vào Badge hoặc nút `+N`, sử dụng **Alpine.js Modal** để hiển thị toàn bộ danh sách điểm kèm theo **Ghi chú (Note)** chi tiết mà không tải lại trang.
- **Real-time:** Cập nhật mảng RAM (`attendance_scores`) ngay sau khi lưu và trigger sự kiện `refresh-table-ui` để đồng bộ giao diện lập tức.
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

  4. Gửi tin nhắn cá nhân đến từng phụ huynh (G6): -> để sau
     Mỗi phụ huynh nhận tin gồm:
       - Ngày, môn, tình trạng đi học
       - Nội dung bài học, BTVN
       - Điểm kiểm tra 1, 2 (nếu có)
       - Điểm thưởng hôm nay + tổng hiện tại
       - Nhắc riêng (private_note)

  5. Gửi tin nhắn chung lớp (G6): -> để sau
       - Nội dung bài, BTVN, nhắc buổi sau
       - Không gửi thông tin cá nhân

Ghi user_logs: GV X hoàn thành điểm danh lớp Y buổi Z lúc T
```


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
    - Trạng thái       → reward_items.is_active
    - Action: Chỉnh sửa | Bật/Tắt

Tạo phần thưởng:
  Form:
    - name             (required)
    - points_required  (required, > 0)
    - reward_type      (required)
    - note             (optional)
    - is_active        (default: true)
  Service: INSERT reward_items

Sửa phần thưởng:
  Service: UPDATE reward_items
  Không cho sửa nếu đã có reward_redemptions dùng item này
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

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

### Trang điểm danh

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
  - Nút "Khen thưởng"
  - Nút "Chấm điểm"
  - Nút "Ghi chú riêng"
```

##### Điểm danh từng học sinh

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

##### Nhập điểm kiểm tra

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

##### Điểm thưởng (Sao)

```
## 1. Thưởng/Phạt nhanh
- **Action:** Nút bấm trực tiếp (+1 sao hoặc -1 sao).
- **Mục đích:** Tuyên dương hoặc nhắc nhở tức thì trong lúc giảng dạy mà không làm gián đoạn bài học.
- **Service:** Tạo bản ghi vào bảng `reward_points` với `amount` tương ứng và lý do mặc định (VD: "Thưởng trong giờ học").

## 2. Thưởng tùy chỉnh (Custom Reward)
- **Action:** Mở Modal nhập số điểm và nội dung (Lý do thưởng).
- **Validate:** Số điểm phải là số nguyên (Integer).
- **Service:** Lưu bản ghi vào DB gắn với `student_id` và `session_id` hiện tại.

## 3. Xử lý dữ liệu (Data Flow)
- **Database:** Lưu vào bảng `reward_points` để làm lịch sử (Log).
- **State Management (RAM):** - Lấy giá trị `total_reward_points` hiện có từ mảng RAM.
    - Cộng/trừ giá trị mới vào và gọi `updateStudentRowOnUI`.
- **Hiển thị:** Cột "Điểm thưởng" hiển thị tổng số điểm tích lũy của học sinh kèm hiệu ứng màu sắc nổi bật.
```

##### Nhắc riêng (Private note)

```
## 1. Bản chất dữ liệu
- **Tính riêng tư:** Chỉ hiển thị cho Giáo viên và Admin. Tuyệt đối không xuất hiện trong báo cáo gửi Phụ huynh/Học sinh.
- **Hình thức:** Lưu trữ theo từng buổi học (Session)

## 2. Thao tác (Action)
- **Hình thức:** Mở Modal nhập liệu.
- **Dữ liệu:** Một đoạn văn bản tự do (Textarea).

## 3. Xử lý Logic (Service)
- **Lưu trữ:** Cập nhật trực tiếp vào trường `private_note` trong bảng `attendance_records`.
- **Cập nhật UI:** Hiển thị một icon "Note" nhỏ màu xám trên bảng nếu dòng đó đã có ghi chú, giúp giáo viên nhận biết em nào có lưu ý mà không cần mở modal.
```

##### Hoàn thành buổi học

```
## 1. Thao tác (UI/UX Action)
- **Trạng thái hiển thị:** Nút chỉ xuất hiện (Visible) khi buổi học đang ở trạng thái chưa hoàn thành (Draft/Ongoing).
- **Xác nhận (Confirmation):** Bắt buộc hiển thị Modal cảnh báo xác nhận trước khi thực thi để tránh giáo viên bấm nhầm, gây ảnh hưởng đến hệ thống tính phí và gửi tin nhắn.

## 2. Ràng buộc & Kiểm soát (Validation)
- **Tuyệt đối không bỏ sót:** Bắt buộc 100% học sinh trong lớp phải được xác nhận trạng thái (Có mặt / Đi muộn / Vắng...).
- **Thuật toán đối chiếu:**
  1. Lấy **Sĩ số lớp thực tế** tại thời điểm diễn ra buổi học (từ bảng `class_enrollments`).
  2. Đếm **Số lượng học sinh đã điểm danh** (bản ghi có `status` khác Null).
  3. Nếu `(Đã điểm danh) < (Sĩ số lớp)` -> Báo lỗi chặn lại, hiển thị rõ số lượng học sinh còn sót.
  4. Nếu `(Đã điểm danh) > (Sĩ số lớp)` -> Cảnh báo dữ liệu bất thường (vượt sĩ số).

## 3. Xử lý hệ thống (Service Logic)
Toàn bộ chuỗi hành động dưới đây phải được bọc trong **Database Transaction** để đảm bảo tính toàn vẹn (ACID):
- **Cập nhật Buổi học:** Đổi `status` của `attendance_sessions` thành `Completed` và lưu thời gian chốt sổ (`completed_at`).
- **Cập nhật Lịch học:** Đổi `status` của `schedule_instances` (nếu có liên kết) thành `Completed`.
- **Ghi Log (Audit):** Lưu lại lịch sử hoạt động bằng hệ thống Logging (Giáo viên X chốt sổ buổi Y lúc Z) để truy vết khi có khiếu nại.

## 4. Hệ thống thông báo (Notification/Queue)
*Lưu ý: Không gửi tin nhắn trực tiếp trong luồng này để tránh timeout.*
- **Đẩy vào Hàng đợi (Job Queue):** Kích hoạt các Jobs chạy ngầm để gửi thông báo (Zalo/SMS).
- **Luồng tin nhắn dự kiến (G6):**
  - Nhắn riêng cho Phụ huynh: Báo cáo đi học, bài tập, điểm số, điểm thưởng, ghi chú riêng.
  - Nhắn chung cho Lớp: Nội dung bài học, dặn dò buổi sau.
```

### Mở lại buổi đã hoàn thành 
```
## 1. Mục đích & Bản chất
- **Mục đích:** Xử lý các trường hợp ngoại lệ khi Giáo viên bấm chốt sổ nhầm, quên nhập điểm, hoặc điểm danh sai cần đính chính lại dữ liệu.
- **Bản chất:** Đây là một thao tác **nhạy cảm (Sensitive Action)** vì nó trực tiếp mở lại khả năng can thiệp vào các dữ liệu liên quan đến tài chính (Học phí) và tính lương (KPI Giáo viên).

## 2. Phân quyền & Thao tác (UI/UX)
- Vị trí: Action Header 
- Phân quyền (Authorization):
    + Chỉ hiển thị cho tài khoản có Role là `Admin`
- Trạng thái hiển thị (Visibility): Chỉ xuất hiện khi buổi học đang ở trạng thái `Completed`.
- Bảo mật thao tác:** Yêu cầu Modal xác nhận (Confirmation) với cảnh báo đỏ: *"Việc mở chốt sổ sẽ cho phép thay đổi dữ liệu điểm danh và điểm số, có thể ảnh hưởng đến học phí đã tính. Bạn có chắc chắn?"*

## 3. Xử lý hệ thống (Service Logic)
- Cập nhật Buổi học:** Chuyển `status` của `attendance_sessions` từ `AttendanceSessionStatus.Completed` về lại `AttendanceSessionStatus.Draft`.
- Cập nhật Lịch học:** Chuyển `status` của `schedule_instances` về lại `ScheduleStatus.Upcoming`.
- Ghi Log (Audit Trail - BẮT BUỘC):** Phải lưu lại lịch sử chi tiết vào `user_logs`: *Admin X đã mở chốt sổ buổi học Y vào lúc Z.* Việc này để truy cứu trách nhiệm nếu có khiếu nại về sửa điểm/sửa điểm danh gian lận.

## 4. Lưu ý Vận hành (Operational Impacts)
- Dữ liệu Phụ huynh: Nếu hệ thống (Queue) đã lỡ gửi tin nhắn báo cáo Zalo/SMS cho phụ huynh ở lúc "Chốt sổ", hệ thống **không thể thu hồi** tin nhắn này. Do đó, sau khi mở chốt và sửa lỗi, trung tâm cần có quy trình nhắn tin thủ công hoặc gửi lại thông báo đính chính nếu cần thiết.
- Tính toán lại (Recalculation): Việc mở chốt sổ không làm thay đổi trực tiếp học phí, nhưng khi Admin chốt sổ lại lần 2, hệ thống sẽ thực hiện lại bước "Final Sweep" (quét và tính lại `is_fee_counted`), đảm bảo số liệu tài chính cuối cùng luôn đúng với trạng thái điểm danh thực tế.
```

### Chốt tháng (Lock buổi học)

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

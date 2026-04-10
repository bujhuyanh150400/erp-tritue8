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
## 1. Mục đích & Bản chất
- **Bản chất:** Là bước chuyển giao dữ liệu từ  Đào tạo  sang Tài chính
- **Mục đích:** Đóng băng toàn bộ hoạt động học thuật của một lớp trong một tháng cụ thể, làm căn cứ gốc (Single Source of Truth) để xuất hóa đơn học phí (G5) và tính lương nhân sự.

## 2. Ràng buộc & Kiểm duyệt (Validation)
Hệ thống phải thực hiện 2 lớp kiểm tra trước khi cho phép chốt tháng:

- **Kiểm tra 1: Chống "Tháng rỗng" (Zero Sessions)**
  - Hệ thống đếm tổng số buổi học có phát sinh trong tháng của lớp.
  - *Nếu Tổng = 0:* Báo lỗi `"Lớp không có buổi học nào trong tháng MM/YYYY"` (Ngăn chặn việc khóa nhầm tháng nghỉ Tết/Dịch bệnh).

- **Kiểm tra 2: Đảm bảo hoàn tất 100% (Strict Completion)**
  - *Truy vấn:* Tìm số lượng buổi học trong tháng có trạng thái `NOT IN ('completed', 'locked', 'cancelled')`.
  - *Xử lý:* Nếu tồn tại dù chỉ 1 buổi (Draft, Ongoing, v.v.), hệ thống ném ngoại lệ (Exception): `"Còn X buổi học trong tháng chưa được chốt sổ. Vui lòng yêu cầu giáo viên hoàn thành trước khi chốt tháng."`

*Lưu ý: Các buổi học bị hủy (`cancelled`) được phép bỏ qua trong quá trình đối chiếu.*

## 3. Xử lý hệ thống (Service Logic)
Toàn bộ luồng dữ liệu chạy trong **Database Transaction**:
- **Cập nhật dữ liệu:**
  - Lệnh `UPDATE` tìm tất cả các buổi học của lớp trong tháng đó có `status = 'completed'` và đổi thành `locked`.
  - Cập nhật thời gian chốt: `locked_at = now()`.
- **Bulk Action (Chốt hàng loạt nhiều lớp):**
  - Kế toán có thể chọn nhiều lớp cùng lúc.
  - Hệ thống áp dụng cơ chế **"Bỏ qua lỗi" (Skip on Error)**: Chỉ khóa các lớp đủ điều kiện. Các lớp chưa chốt sổ xong sẽ bị bỏ qua.
  - Trả về kết quả: *"Đã chốt thành công 8/10 lớp. Các lớp chưa đủ điều kiện: Toán 9, Anh 10."*
- **Audit Log:** Ghi nhận lịch sử vào `user_logs`: *[Kế toán X] đã chốt tháng [MM/YYYY] cho lớp [Y] vào lúc [Z]*.

## 4. Tác động hệ thống sau khi khóa (Post-Lock)
- **Vô hiệu hóa UI:** Toàn bộ nút "Sửa", "Xóa", "Chốt sổ" trên danh sách buổi học của tháng đó bị ẩn (Hidden/Disabled) đối với Giáo viên.
- **Đóng băng chi tiết:** Các bản ghi `attendance_records` thuộc tháng này không thể thao tác:
  - Không thay đổi trạng thái điểm danh.
  - Không sửa/xóa/thêm điểm kiểm tra.
  - Không cộng/trừ điểm thưởng (sao).
- **Kích hoạt luồng Tài chính:** Module Học phí/Hóa đơn chính thức được quyền truy xuất dữ liệu `is_fee_counted` của tháng này để lên Bill cho phụ huynh.

## 5. Xử lý ngoại lệ: Mở khóa tháng (Unlock Month)
- **Phân quyền:** Action này BẮT BUỘC chỉ hiển thị với vai trò `Super Admin` hoặc `Accountant Manager` (Kế toán trưởng).
- **Thao tác:** Mở khóa các buổi học (`locked` -> `completed`).
- **Cảnh báo (Modal):** *"Việc mở khóa có thể làm sai lệch báo cáo doanh thu và hóa đơn đã xuất. Bạn có chắc chắn muốn thực hiện?"*
- Bắt buộc ghi Log hệ thống khi thực hiện hành động này.
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
## 1. Danh sách (List View)
- **Truy vấn:** Lấy danh sách quà tặng, ưu tiên hiển thị theo số điểm từ thấp đến cao để dễ nhìn.
  `SELECT * FROM reward_items ORDER BY points_required ASC`
- **Cột hiển thị:**
  - Tên phần thưởng (name)
  - Điểm cần đổi (points_required)
  - Loại phần thưởng (reward_type)
  - Trạng thái (is_active: Đang áp dụng / Tạm ngưng)
- **Thao tác (Action):** Chỉnh sửa | Bật/Tắt trạng thái (Toggle Active).

## 2. Tạo phần thưởng (Create)
- **Form đầu vào:**
  - `name`: Text (Bắt buộc).
  - `points_required`: Integer (Bắt buộc, > 0).
  - `reward_type`: Select (Bắt buộc).
  - `note`: Textarea (Tùy chọn).
  - `is_active`: Boolean (Mặc định: true).
- **Service logic:** Thực hiện `INSERT` dữ liệu vào bảng `reward_items`.
    
## 3. Sửa phần thưởng (Update)
- **Service logic:** Thực hiện `UPDATE` bảng `reward_items`.
- **Ràng buộc bảo vệ dữ liệu (Validation):**
  - Trước khi update, hệ thống kiểm tra ID của phần thưởng này đã tồn tại trong bảng `reward_redemptions` (Lịch sử đổi quà) hay chưa.
  - **Nếu ĐÃ CÓ người đổi:** Tuyệt đối không cho phép sửa trường `points_required` (để bảo vệ lịch sử trừ điểm). Chỉ cho phép cập nhật `name`, `note` và trạng thái `is_active`.
```

### Đổi thưởng cho học sinh

```
## 1. Giao diện & Hiển thị (UI/UX)
- **Vị trí thao tác:** Action "Đổi thưởng" đặt tại tab "Sao & thưởng (trong màn chi tiết học sinh)".
- **Thông tin khởi tạo Form:**
  - **Tổng sao hiện có:** Truy vấn `SELECT SUM(amount) FROM reward_points WHERE student_id = ?`.
  - **Danh sách quà tặng:** Dropdown hiển thị các phần thưởng hợp lệ (`is_active = true`).
  - Gợi ý: Có thể vô hiệu hóa (disabled) các món quà mà điểm yêu cầu lớn hơn tổng sao hiện tại của học sinh để tránh click nhầm.

## 2. Kiểm duyệt & Ràng buộc (Validation)
- **Kiểm tra số dư điểm:**
  - So sánh: Tổng sao của học sinh >= `points_required` của món quà được chọn.
  - Lỗi (Exception): 
    + Nếu không đủ sao, chặn giao dịch và báo lỗi: `"Học sinh chỉ có [X] sao, cần [Y] sao để đổi [Tên món quà]."`.

## 3. Xử lý hệ thống (Service Logic)
Toàn bộ luồng xử lý phải được bọc trong **Database Transaction**:

- Bước 1: Ghi nhận lịch sử đổi quà
  - Thực hiện `INSERT` vào bảng `reward_redemptions`.
  - Dữ liệu chuẩn bị: 
    + `student_id`, `reward_item_id`, `points_spent` (lấy từ giá trị `points_required` của item tại thời điểm này), 
    + Thời gian đổi
    + người xử lý (`Auth::id()`).

- Bước 2: Cấn trừ số dư điểm
  - Thực hiện `INSERT` vào bảng `reward_points` một bản ghi mang giá trị **ÂM**.
    > amount      = -(reward_items.points_required),  ← âm = trừ điểm
    > reason      = 'Đổi thưởng: ' + reward_items.name,
    > awarded_by  = Auth::id()
    > session_id  = null (vi là đổi thưởng)

- **Bước 3: Ghi Log hệ thống**
  - Ghi nhận vào `user_logs`: *Giáo viên [X] đã đổi thưởng [Tên quà] cho Học sinh [Y] vào lúc [Z].*

```

### Lịch sử đổi thưởng

```
## 1. Giao diện & Vị trí (UI/UX)
- Vị trí: 
    > Đặt ở trang chi tiết Hồ sơ học sinh, Tab "Sao & thưởng (trong màn chi tiết học sinh)".
    > Thành 1 component hiển thị lịch sử đổi quà của học sinh.
- Phân trang (Pagination): Áp dụng phân trang  để tối ưu hiệu năng nếu lịch sử đổi quà của học sinh quá dài.

## 2. Truy vấn dữ liệu (Query Logic)
- **Truy vấn lõi (Core SQL):**
  ```sql
  SELECT
      rr.redeemed_at,
      ri.name as ten_thuong,
      ri.reward_type,
      rr.points_spent,
      u.name as nguoi_xu_ly,   -- Dùng name thay vì username để hiển thị thân thiện hơn
      rr.invoice_id            -- Bổ sung để check trạng thái kế toán
  FROM reward_redemptions rr
  JOIN reward_items ri ON rr.reward_item_id = ri.id
  JOIN users u ON rr.processed_by = u.id
  WHERE rr.student_id = ?
  ORDER BY rr.redeemed_at DESC
```

### Lịch sử cộng/trừ sao
```
## 1. Giao diện & Vị trí (UI/UX)
- Vị trí: 
    > Trong Tab "Lịch sử đổi thưởng" trong trang Chi tiết Học sinh (Student View Page).
    > Thành 1 component hiển thị
    > Ngay phía trên bảng lịch sử đổi thưởng, nên có một Section nhỏ hiển thị: Tổng sao hiện tại (Current Balance) = `SUM(amount)`.

## 2. Truy vấn dữ liệu (Query Logic)
- **Truy vấn lõi (Core SQL):**
  Hệ thống truy xuất toàn bộ giao dịch (cả cộng và trừ) từ bảng `reward_points`. Dùng `LEFT JOIN` với bảng buổi học (nếu sao được tặng trong lớp).

  ```sql
  SELECT
      rp.created_at AS thoi_gian,
      rp.amount AS bien_dong,
      rp.reason AS ly_do,
      u.name AS nguoi_thuc_hien,
      sess.session_date AS buoi_hoc_lien_quan
  FROM reward_points rp
  LEFT JOIN users u ON rp.awarded_by = u.id
  LEFT JOIN attendance_sessions sess ON rp.session_id = sess.id
  WHERE rp.student_id = ?
  ORDER BY rp.created_at DESC

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

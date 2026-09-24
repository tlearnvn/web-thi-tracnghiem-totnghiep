# Lịch sử thay đổi

Mọi thay đổi đáng chú ý của dự án được ghi lại tại đây (theo tinh thần [Keep a Changelog](https://keepachangelog.com/vi/1.1.0/)).

Số phiên bản có dạng `MAJOR.MINOR.PATCH` và nằm trong tệp [`VERSION`](VERSION):

- **PATCH** tự tăng mỗi lần commit có thay đổi mã nguồn (hook `.githooks/pre-commit`, bật bằng `git config core.hooksPath .githooks`).
- **MINOR / MAJOR** tăng thủ công khi phát hành: `sh tools/bump-version.sh minor && git add VERSION`.
- Cấu trúc CSDL có số phiên bản riêng (`Schema::VERSION`) và **tự nâng cấp** ở lần truy cập đầu tiên sau khi cập nhật mã nguồn.

---

## 1.0.7 – 24/09/2026

### Tài liệu
- Hướng dẫn sử dụng: thêm mục riêng cho **Phần II – đúng/sai** và **Phần III – trả lời ngắn** trên phiếu: cách chọn / tô, gõ đáp số trực tiếp, bảng ví dụ cách tô, các cảnh báo sai quy cách và cách sửa, cách tính điểm, rà soát trước khi nộp, cách đọc trang xem lại, giáo viên nhập đáp án Phần II / III (hủy ý, điểm riêng, nhiều đáp án), giao diện điện thoại – kèm 9 ảnh minh họa mới.

### Sửa lỗi giao diện
- Trang xem lại bài: câu Phần III tô sai quy cách không còn hiện **lặp hai lần** cùng một cảnh báo (lý do chỉ ghi ở dòng *Đáp án*).

## 1.0.6 – 24/09/2026

### Sửa lỗi
- **Giám thị** được phân công nay mở được trang chi tiết ca thi, *Giám sát trực tiếp* và *Màn hình trình chiếu* (trước đây bị báo "không có quyền"). Người không có quyền xem kết quả sẽ không thấy điểm, nút *Kết quả* và liên kết *Chi tiết bài làm* trên bảng giám sát.
- **Đề PDF không hiển thị trên trình duyệt đời cũ** như Chrome / Edge 109 (bản cuối cho Windows 7): thêm `assets/js/compat.js` bổ sung `Promise.withResolvers` và `ArrayBuffer.prototype.transfer` / `transferToFixedLength` cho cả luồng chính lẫn worker của PDF.js. Đã kiểm thử phòng thi (đề được bảo vệ, lưu bài, mất mạng, tải lại, nộp bài) và trang xem lại trên Chromium 109.
- Mục *Kiểm tra máy* ở phòng chờ kiểm tra trình duyệt kỹ hơn và **nạp thử bộ hiển thị đề**; báo rõ khi trình duyệt quá cũ.
- Thống kê: tỉ lệ "đạt từ 5 / từ 8 điểm" tính trên điểm thật (trước đây lấy theo phổ điểm làm tròn 0,5 nên bài 4,75 điểm bị tính là đạt).
- Nhật ký bài làm ghi đúng **"Giám thị hủy bài – cho thi lại từ đầu"** (trước đây ghi nhầm là "Mở lại bài").
- Trang chi tiết ca thi không còn báo lỗi quyền khi giáo viên xem ca của lớp mình dùng đề của giáo viên khác; tiêu đề đề thi chỉ có liên kết khi được xem đề.
- Trang đề thi chỉ liệt kê các ca thi người dùng được xem; nút *Kết quả* / *Giám sát* trong danh sách hiện theo quyền của từng ca; số bài đã nộp không tính bài đã hủy.
- Nhãn cài đặt "Số lần sai tối đa của một địa chỉ IP" ghi đúng khoảng thời gian tính (bằng *thời gian tạm khóa*, không phải cố định 15 phút).

### Bảo mật
- Chặn truy cập web vào thư mục `docs/` (tài liệu, đề & lời giải mẫu) trên Apache (`docs/.htaccess`), IIS (`web.config`) và mẫu cấu hình Nginx.

### Tài liệu
- Đối chiếu README và hướng dẫn sử dụng với mã nguồn: sửa mẫu cấu hình Nginx (bỏ khối `types {}` làm hỏng kiểu MIME), bổ sung yêu cầu `SimpleXML`, trình duyệt hỗ trợ, bảng vai trò có *Cán bộ quản lý*, cách đặt tên đăng nhập / mật khẩu khi nhập Excel, quy ước đáp án Phần II theo dòng (`1a`…`1d`), cách tính điểm (trừ điểm chỉ ở Phần I, các chế độ Phần II, chế độ theo tỉ lệ), ngưỡng đọc chỉ số phân tích câu hỏi, phạm vi áp dụng của cài đặt, sơ đồ trạng thái bài làm và quan hệ dữ liệu, tên nút trên giao diện.

## 1.0.5 – 24/09/2026

### Tài liệu
- `README.md`: giới thiệu tính năng, bộ ảnh minh họa, hướng dẫn cài đặt (Apache / Nginx / chạy thử trên máy), sơ đồ quy trình, kiến trúc, quan hệ dữ liệu (ER), tuần tự lưu bài, vòng đời bài làm, bảng xử lý sự cố, cách tính điểm, bảo mật, sao lưu.
- `docs/HUONG-DAN-SU-DUNG.md`: hướng dẫn sử dụng chi tiết cho quản trị, giáo viên, giám thị và học sinh (13 mục, kèm sơ đồ và câu hỏi thường gặp).
- `docs/images/`: ảnh chụp màn hình minh họa (máy tính, điện thoại, giao diện tối, tình huống sự cố).
- `docs/samples/`: tệp Excel mẫu để nhập học sinh, giáo viên và đáp án môn Toán.
- Thêm `LICENSE` (MIT) và giấy phép bộ biểu tượng Lucide (`assets/vendor/lucide/LICENSE`).

### Sửa lỗi giao diện
- Hai thẻ nằm cạnh nhau trong lưới bị lệch 20 px (thẻ bên phải thấp hơn) và các thẻ xếp chồng trong cột cách nhau gấp đôi – nay thẳng hàng, cách đều 20 px trên mọi trang.
- Lớp tiện ích khoảng cách (`mt-*`) không có tác dụng trên danh sách thông tin (phòng chờ: dòng "Bắt đầu / Kết thúc" dính sát bảng cấu trúc đề).
- Trang tạo ca thi: các ô "Số lần vi phạm tối đa", "Khi vượt quá", "Tính giờ"… ở cột phải xếp một cột, không còn bị cắt chữ.
- Hộp thoại chỉ hiện phần chân khi có nút; hộp thoại lời giải có nút **Đã hiểu** (trước đây hiện một dải chân trống).
- Trang Tổng quan: ô đếm ghi rõ **Đang làm, có kết nối** (chỉ tính học sinh đang trực tuyến), tránh hiểu nhầm với số bài chưa nộp.

## 1.0.4 – 24/09/2026

### Cải tiến
- Đầu trang phòng thi gọn hơn trên màn hình nhỏ: ẩn logo, rút gọn đồng hồ và trạng thái lưu.
- Bài bị giám thị thu hiển thị trạng thái **Bị thu bài**; trang xem lại ghi rõ lý do thu bài.

### Sửa lỗi
- Đáp án Phần I có chữ `D` bị đổi nhầm thành `Đ` trong tệp Excel và trang thống kê.

## 1.0.3 – 24/09/2026

### Thêm mới
- **Kết quả**: bảng điểm ca thi (kể cả học sinh vắng), chi tiết bài làm từng câu kèm nhật ký sự kiện, chấm tự luận (lưu & chuyển bài kế tiếp), chấm lại, in phiếu trả lời bản lưu.
- **Xuất Excel 4 trang**: kết quả, chi tiết từng câu (tô màu đúng/sai), thống kê, phân tích câu hỏi.
- **Thống kê**: phổ điểm, xếp loại, theo lớp, theo phần, mức độ, chủ đề; **phân tích câu hỏi** (độ khó p, độ phân biệt D, tương quan điểm–câu r, phân bố phương án nhiễu, hệ số Cronbach α); danh sách học sinh điểm cao / cần hỗ trợ.
- **Cài đặt**: tên hệ thống, chân trang (copyright), màu chủ đạo, logo & favicon (lưu trong CSDL), mặc định thi cử, xếp loại, bảo mật, chế độ bảo trì.
- **Sao lưu** `.tnbak` dạng luồng dùng chung cho SQLite ↔ MySQL; **phục hồi** tải lên theo khúc và chạy nhiều bước ngắn; tải nguyên tệp SQLite (`VACUUM INTO`).
- **Nhật ký** hoạt động / lỗi / đăng nhập (xuất Excel); trang thông tin hệ thống, dọn dẹp, tối ưu CSDL, xóa dữ liệu mẫu; thông báo; `cron.php` (tùy chọn, chạy bằng dòng lệnh hoặc URL có mã).

## 1.0.2 – 24/09/2026

### Thêm mới
- **Cổng học sinh**: bài thi đang mở / sắp tới / đã làm, phòng chờ có quy chế và kiểm tra máy, trang kết quả theo từng phần, lịch sử, luyện tập.
- **Phòng thi**: đề PDF được bảo vệ + phiếu trả lời mẫu 2025; lưu tự động có số thứ tự chống ghi đè; bản sao dự phòng trên máy; làm tiếp khi mất mạng; đồng hồ theo giờ máy chủ; tự nộp khi hết giờ; đăng nhập lại ngay trong phòng thi; chặn mở nhiều tab; ghi nhận rời màn hình; tạm dừng / khóa; tin nhắn của giám thị.
- **Xem lại bài**: tô đúng/sai, đáp án, lời giải từng câu (công thức KaTeX), PDF lời giải.
- **Giám sát trực tiếp**: bảng theo dõi, cảnh báo cần xử lý, cộng / bớt giờ, tạm dừng, mở khóa thiết bị (thu hồi mã máy cũ), mở khóa vi phạm, thu bài, mở lại, hủy bài cho thi lại, nhắn tin, màn hình trình chiếu mã phòng thi.

### CSDL
- Cấu trúc phiên bản 2: thêm cột `attempts.device_prev` (tự nâng cấp).

## 1.0.1 – 24/09/2026

### Thêm mới
- Quản lý học sinh / giáo viên / lớp / tài khoản / vai trò; nhập từ Excel có xem trước.
- Quản lý môn thi, đề thi, mã đề; trình soạn đáp án kèm xem PDF.
- Tạo ca thi; API phòng thi (lưu bài, khóa thiết bị, PDF làm rối theo khóa riêng của bài làm).
- Cổng học sinh bản đầu (danh sách ca thi, phòng chờ, kết quả, lịch sử).

## 1.0.0 – 24/09/2026

### Khởi tạo
- Lõi PHP thuần: định tuyến, lớp CSDL dùng chung SQLite / MySQL, phiên đăng nhập lưu trong CSDL, kho tệp (PDF, logo) lưu theo khúc ngay trong CSDL để **không tăng inode**.
- Phân quyền theo vai trò, chống dò mật khẩu, CSRF, nhật ký thao tác / lỗi.
- Thư viện đọc / ghi ZIP và Excel `.xlsx` không cần extension `zip`.
- Định dạng đề thi 2025; bộ chấm điểm (theo Bộ GD&ĐT, tùy chỉnh, theo tỉ lệ).
- Nhập đáp án từ Excel (3 kiểu bảng) và JSON.
- Nghiệp vụ ca thi / bài làm: giờ máy chủ, tạm dừng, cộng giờ, tự thu bài.
- Trình cài đặt (chọn SQLite hoặc MySQL), dữ liệu mẫu (đề Toán minh họa có PDF và lời giải).
- Giao diện đăng nhập, bố cục trang quản trị và cổng học sinh.
- Tự tăng phiên bản khi commit.

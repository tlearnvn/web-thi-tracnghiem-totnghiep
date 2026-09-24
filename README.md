<div align="center">

<img src="assets/img/logo.svg" width="96" alt="Logo">

# Hệ thống thi trắc nghiệm trực tuyến – định dạng tốt nghiệp THPT 2025

**Học sinh làm bài thi trên máy tính: bên trái là đề PDF, bên phải là phiếu trả lời giống mẫu của Bộ GD&ĐT.**
Viết bằng PHP thuần, chạy được trên shared hosting, lưu toàn bộ dữ liệu vào **một tệp SQLite** hoặc **MySQL**.

![PHP](https://img.shields.io/badge/PHP-8.0%2B-777bb4?logo=php&logoColor=white)
![SQLite](https://img.shields.io/badge/SQLite-1%20t%E1%BB%87p-003b57?logo=sqlite&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL%20%2F%20MariaDB-h%E1%BB%97%20tr%E1%BB%A3-4479a1?logo=mysql&logoColor=white)
![No Composer](https://img.shields.io/badge/kh%C3%B4ng%20c%E1%BA%A7n-Composer%20%2F%20Node-16a34a)
![Timezone](https://img.shields.io/badge/gi%E1%BB%9D-UTC%2B7-f59e0b)
![License](https://img.shields.io/badge/gi%E1%BA%A5y%20ph%C3%A9p-MIT-blue)

[Tính năng](#-tính-năng) · [Ảnh minh họa](#-ảnh-minh-họa) · [Cài đặt](#-cài-đặt) · [Sơ đồ hệ thống](#-kiến-trúc--sơ-đồ) · [Xử lý sự cố](#-xử-lý-sự-cố-trong-giờ-thi) · [📘 Hướng dẫn sử dụng](docs/HUONG-DAN-SU-DUNG.md)

<img src="docs/images/06-phong-thi.jpg" alt="Phòng thi: đề PDF bên trái, phiếu trả lời bên phải" width="100%">

</div>

---

## ✨ Tính năng

| | Nhóm | Chi tiết |
|---|---|---|
| 📝 | **Đúng định dạng 2025** | Phần I (nhiều lựa chọn A/B/C/D), Phần II (đúng/sai 4 ý a–d), Phần III (trả lời ngắn tô số, dấu “−”, dấu phẩy), tự luận tùy chọn. Có sẵn cấu trúc 18 môn: Toán, Văn, Lí, Hóa, Sinh, Sử, Địa, GDKT&PL, Tin, Công nghệ (Công nghiệp / Nông nghiệp), 7 ngoại ngữ. |
| 🖥️ | **Phòng thi 2 cột** | Đề PDF (trái) + phiếu trả lời mẫu của Bộ (phải), **kéo thanh chia mượt**, thu gọn từng bên; trên điện thoại tự chuyển thành 2 tab. Phím tắt A/B/C/D, đánh dấu câu (F), gõ trực tiếp đáp số Phần III. |
| 🔒 | **Đề xem được – không tải được** | PDF hiển thị bằng canvas (PDF.js), dữ liệu **làm rối theo khóa riêng của từng bài làm**, không mở được đường dẫn trực tiếp, chặn in / lưu / chuột phải, **in chìm họ tên + SBD** lên trang đề. |
| 💾 | **Không mất bài** | Lưu tự động sau mỗi lần tô (có số thứ tự chống ghi đè), bản dự phòng trên máy, **làm tiếp khi mất mạng**, tự đồng bộ khi có mạng, đồng hồ theo giờ máy chủ, tự thu bài khi hết giờ (có thời gian ân hạn). |
| 🛟 | **Xử lý sự cố** | Máy hỏng → đăng nhập máy khác + giám thị *mở khóa thiết bị*; hết phiên đăng nhập → đăng nhập lại ngay trong phòng thi; mở nhiều tab → chặn ghi đè; tạm dừng cả phòng / từng em; cộng giờ; mở lại bài nộp nhầm; hủy bài cho thi lại. |
| 👀 | **Giám sát trực tiếp** | Bảng theo dõi từng học sinh (đang làm, mất kết nối, số câu, thời gian còn lại, rời màn hình…), cảnh báo cần xử lý, nhắn tin cả phòng / riêng, màn hình trình chiếu mã phòng thi. |
| 🧮 | **Chấm điểm linh hoạt** | Theo quy định của Bộ 2025 (Phần II: 0,1 / 0,25 / 0,5 / 1 điểm), tùy chỉnh điểm từng phần (Phần II theo bảng / theo từng ý / chỉ khi đúng cả 4 ý), trừ điểm câu sai Phần I, quy đổi thang điểm, theo tỉ lệ; điểm riêng từng câu, hủy câu, nhiều đáp án chấp nhận; chấm lại hàng loạt; chấm tự luận. |
| 📊 | **Thống kê & phân tích** | Phổ điểm, xếp loại, so sánh lớp, theo phần / mức độ / chủ đề; **phân tích câu hỏi** (độ khó p, độ phân biệt D, tương quan r, phương án nhiễu, Cronbach α). Xuất **Excel 4 trang**. |
| 📥 | **Nhập dữ liệu** | Học sinh, giáo viên từ **Excel** (tự nhận cột, xem trước, chuẩn hóa họ tên, tạo tài khoản & in phiếu); đáp án, mã đề, lời giải từ **Excel hoặc JSON** (3 kiểu bảng thường gặp). Lời giải hỗ trợ công thức toán (KaTeX). |
| 👥 | **Phân quyền đầy đủ** | Quản trị, cán bộ quản lý, giáo viên (chỉ thấy lớp/đề của mình), giám thị (chỉ ca được phân công), học sinh + vai trò tùy chỉnh với ma trận quyền. |
| 🗄️ | **1 tệp SQLite hoặc MySQL** | Chọn lúc cài đặt. **Mọi thứ nằm trong CSDL**: đề PDF, logo, phiên đăng nhập, nhật ký → **không tăng inode** trên hosting. Sao lưu `.tnbak` chuyển đổi qua lại SQLite ↔ MySQL. |
| 🎨 | **Tùy biến** | Tên hệ thống, logo, favicon, chân trang (copyright), màu chủ đạo, giao diện sáng/tối. Phiên bản **tự tăng** mỗi lần commit mã nguồn (git hook). |
| 🕖 | **UTC+7 & shared hosting** | Giờ Việt Nam ở mọi nơi, cấu hình sẵn thời gian chạy dài (không bị ngắt khi nhập/xuất), tải tệp theo từng khúc 512 KB (vượt giới hạn upload của hosting), không cần cron. |

## 📸 Ảnh minh họa

| Đăng nhập | Cổng học sinh | Phòng chờ & quy định |
|---|---|---|
| ![](docs/images/01-dang-nhap.jpg) | ![](docs/images/04-cong-hoc-sinh.jpg) | ![](docs/images/05-phong-cho.jpg) |
| **Phòng thi – giao diện tối** | **Trên điện thoại** | **Xác nhận nộp bài** |
| ![](docs/images/06b-phong-thi-toi.jpg) | ![](docs/images/07-phong-thi-dien-thoai.jpg) | ![](docs/images/08-nop-bai.jpg) |
| **Kết quả** | **Xem lại & lời giải** | **Giám sát trực tiếp** |
| ![](docs/images/09-ket-qua.jpg) | ![](docs/images/10-xem-lai.jpg) | ![](docs/images/11-giam-sat.jpg) |
| **Mất mạng vẫn làm tiếp** | **Khóa thiết bị** | **Nhập đáp án cạnh đề** |
| ![](docs/images/12b-mat-mang.jpg) | ![](docs/images/12a-khoa-thiet-bi.jpg) | ![](docs/images/13-nhap-dap-an.jpg) |
| **Bảng điểm** | **Thống kê** | **Phân tích câu hỏi** |
| ![](docs/images/16-bang-diem.jpg) | ![](docs/images/17-thong-ke.jpg) | [![](docs/images/18b-phan-tich-cau-hoi-gon.jpg)](docs/images/18-phan-tich-cau-hoi.jpg) |
| **Nhập học sinh từ Excel** | **Cài đặt hệ thống** | **Sao lưu & phục hồi** |
| ![](docs/images/15-nhap-hoc-sinh.jpg) | ![](docs/images/19-cai-dat-he-thong.jpg) | ![](docs/images/20-sao-luu.jpg) |

## 🚀 Cài đặt

### Yêu cầu

| Thành phần | Yêu cầu |
|---|---|
| PHP | **8.0 trở lên** (khuyên dùng 8.1+; đã kiểm thử trên PHP 8.4), tiện ích `pdo` + `pdo_sqlite` hoặc `pdo_mysql`, `mbstring`, `zlib`, `SimpleXML` (khuyên có `intl`, `opcache`) – trình cài đặt tự kiểm tra |
| CSDL | **SQLite 3** (1 tệp, không cần cấu hình) **hoặc** MySQL 5.7+ / MariaDB 10.3+ (đã kiểm thử SQLite 3.45 và MariaDB 10.11) |
| Máy chủ web | Apache / LiteSpeed (có sẵn `.htaccess`), Nginx (xem mẫu bên dưới), IIS (có sẵn `web.config`) |
| Trình duyệt | **Chrome / Edge 109 trở lên** (đã kiểm thử trên Chromium 109 – bản cuối cho Windows 7 – và Chromium 141), Firefox, Safari bản mới. Trang *phòng chờ* tự kiểm tra trình duyệt và nạp thử bộ hiển thị đề trước khi vào thi |

### Các bước (shared hosting / cPanel)

1. **Tải mã nguồn** (nút *Code → Download ZIP*) và giải nén vào thư mục web, ví dụ `public_html/thi/`.
2. Mở trình duyệt: `https://ten-mien-cua-ban/thi/install.php`.
3. Trình cài đặt tự **kiểm tra máy chủ**, sau đó chọn loại CSDL:
   - **SQLite** – để mặc định là xong (tệp được đặt trong `storage/` với tên ngẫu nhiên, đã chặn truy cập từ web);
   - **MySQL** – nhập máy chủ, tên CSDL, tài khoản (bấm *Kiểm tra kết nối*), có thể đặt tiền tố bảng.
4. Nhập **tên đơn vị**, tài khoản **quản trị**. Ô **Tạo dữ liệu mẫu để dùng thử** được chọn sẵn (2 lớp, 20 học sinh, đề Toán minh họa có PDF + đáp án + lời giải) – bỏ chọn nếu cài để thi thật.
5. Bấm **Cài đặt** → đăng nhập. Sau khi dùng thử, vào *Thông tin hệ thống → Xóa dữ liệu mẫu*.

<details>
<summary><b>Ảnh màn hình trình cài đặt</b></summary>

<img src="docs/images/02-cai-dat.jpg" width="640" alt="Trình cài đặt: kiểm tra máy chủ, chọn SQLite hoặc MySQL, thông tin đơn vị và tài khoản quản trị">

</details>

<details>
<summary><b>Tài khoản mẫu</b> (khi chọn tạo dữ liệu mẫu)</summary>

| Vai trò | Tài khoản | Mật khẩu |
|---|---|---|
| Giáo viên | `gv.toan` | `123456` |
| Giám thị (được phân công ca thi mẫu) | `giamthi` | `123456` |
| Học sinh | `hs12a101` … `hs12a110`, `hs12a201` … `hs12a210` | `123456` |

</details>

<details>
<summary><b>Cấu hình Nginx</b></summary>

```nginx
server {
    listen 80;
    server_name thi.truong.edu.vn;
    root /var/www/thi;
    index index.php;
    client_max_body_size 64m;

    # Chặn thư mục & tệp nhạy cảm (CSDL SQLite, cấu hình, mã nguồn, tài liệu)
    location ~ ^/(app|storage|tools|docs)(/|$) { return 404; }
    location ~ /\.(?!well-known) { deny all; }
    location ~* \.(sqlite|sqlite3|db|log|ini|sh|md|lock|bak|sql)$ { deny all; }

    location / { try_files $uri $uri/ /index.php?$query_string; }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_read_timeout 300;
    }

    location ~* \.(css|js|woff2|svg|png|jpg|webp|wasm)$ { expires 30d; add_header Cache-Control "public"; }
}
```
Không khai báo khối `types { … }` bên trong `server` – nó **thay thế toàn bộ** `mime.types` (CSS/JS sẽ sai kiểu và trang hỏng). Nếu Nginx đời cũ chưa có dòng `application/wasm wasm;`, hãy thêm vào `/etc/nginx/mime.types`.
</details>

<details>
<summary><b>Chạy thử trên máy cá nhân</b></summary>

```bash
git clone https://github.com/tlearnvn/web-thi-tracnghiem-totnghiep.git
cd web-thi-tracnghiem-totnghiep
php -S 127.0.0.1:8080 -t .
# mở http://127.0.0.1:8080/install.php
```
Máy chủ tích hợp của PHP chỉ dùng để thử nghiệm; khi thi thật hãy dùng Apache / Nginx / LiteSpeed.
</details>

> **Cron là tùy chọn.** Bài quá giờ được tự thu mỗi khi có người mở các trang liên quan (phòng thi, cổng học sinh, tổng quan, ca thi, giám sát, kết quả, thống kê). Nếu hosting có cron, đặt `php /duong-dan/cron.php` mỗi 5 phút (hoặc gọi URL có mã bí mật hiển thị ở *Thông tin hệ thống*).

## 🧭 Quy trình tổ chức một kỳ thi

```mermaid
flowchart LR
    A["👤 Nhập học sinh<br/>từ Excel"] --> B["📄 Tạo đề thi<br/>chọn môn → cấu trúc 2025"]
    B --> C["⬆️ Tải PDF từng mã đề"]
    C --> D["🔑 Nhập đáp án<br/>Excel / JSON / soạn tay<br/>(+ lời giải)"]
    D --> E["🗓️ Tạo ca thi<br/>lớp · giờ · mã phòng · tùy chọn"]
    E --> F["👀 Giám sát trực tiếp<br/>xử lý sự cố"]
    F --> G["✅ Chấm tự động<br/>chấm tự luận"]
    G --> H["📊 Thống kê · phân tích câu hỏi<br/>xuất Excel · công bố điểm"]
```

## 🏗 Kiến trúc & sơ đồ

```mermaid
flowchart TB
    subgraph Client["Trình duyệt"]
        HS["🎓 Học sinh<br/>exam.js · answersheet.js · pdfviewer.js (PDF.js)"]
        GV["🧑‍🏫 Giáo viên / Giám thị / Quản trị<br/>app.js · monitor.js · Chart.js"]
    end
    subgraph Server["PHP 8 – không framework, không Composer"]
        FC["index.php<br/>(front controller)"] --> R["Router + phân quyền + CSRF"]
        R --> CT["Controllers<br/>Students · Exams · Sessions · ExamRoom · Monitor<br/>Results · Stats · Settings · Backup · Logs"]
        CT --> LB["Nghiệp vụ<br/>Scoring · Attempts · Sessions · Stats<br/>KeyImporter · PeopleImporter · Backup"]
        LB --> CORE["Lõi<br/>Database (PDO) · DbSessionHandler · FileStore · Logger<br/>ZIP / XLSX thuần PHP"]
    end
    subgraph DB["CSDL – chọn 1 lúc cài đặt"]
        SQ[("🗃️ 1 tệp SQLite<br/>(WAL)")]
        MY[("🐬 MySQL / MariaDB")]
    end
    HS -- "JSON: state · save · ping · event · submit" --> FC
    HS -- "PDF làm rối theo khóa của bài làm" --> FC
    GV -- "HTML + JSON" --> FC
    CORE --> SQ
    CORE -.-> MY
```

**Mọi thứ trong một CSDL** – kể cả tệp: tệp PDF / logo được chia thành khối 512 KB lưu trong bảng `file_chunks`, phiên đăng nhập trong `web_sessions`, nhật ký trong `audit_logs` / `error_logs`. Thư mục `storage/` chỉ chứa `config.php` (cùng tệp SQLite và 2 tệp tạm `-wal` / `-shm` khi dùng SQLite) – **số tệp không tăng theo dữ liệu**.

### Mô hình dữ liệu chính

```mermaid
erDiagram
    CLASSES |o--o{ USERS : "có học sinh"
    USERS ||--o{ CLASS_TEACHERS : "dạy"
    CLASSES ||--o{ CLASS_TEACHERS : ""
    SUBJECTS |o--o{ EXAMS : "môn"
    EXAMS ||--o{ EXAM_VARIANTS : "mã đề"
    EXAM_VARIANTS ||--o{ EXAM_KEYS : "đáp án từng câu"
    EXAM_VARIANTS }o--o| FILES : "PDF đề / lời giải"
    FILES ||--o{ FILE_CHUNKS : "khối 512 KB"
    EXAMS ||--o{ EXAM_SESSIONS : "ca thi"
    EXAM_SESSIONS ||--o{ SESSION_TARGETS : "lớp / HS dự thi"
    EXAM_SESSIONS ||--o{ SESSION_STAFF : "giám thị"
    EXAM_SESSIONS ||--o{ SESSION_MESSAGES : "thông báo"
    EXAM_SESSIONS ||--o{ ATTEMPTS : "bài làm"
    USERS ||--o{ ATTEMPTS : "làm bài"
    ATTEMPTS ||--o{ ATTEMPT_EVENTS : "nhật ký từng thao tác"
    ATTEMPTS {
        int id
        string status "in_progress / submitted / expired / forced / voided"
        int deadline_at "hạn nộp theo giờ máy chủ"
        text answers "JSON phiếu trả lời"
        int seq "số thứ tự lần lưu"
        string device_token "khóa thiết bị"
        float score
    }
```

### Luồng làm bài & lưu tự động

```mermaid
sequenceDiagram
    autonumber
    participant HS as Học sinh
    participant JS as Trình duyệt (exam.js)
    participant LS as Bộ nhớ máy (localStorage)
    participant SV as Máy chủ PHP
    participant DB as CSDL
    HS->>SV: Vào phòng chờ, nhập mã phòng, bấm "Bắt đầu"
    SV->>DB: Tạo bài làm, chọn mã đề, tính hạn nộp, cấp mã thiết bị
    JS->>SV: GET state (đáp án đã lưu, giờ máy chủ, cấu hình)
    SV-->>JS: answers · deadline · PDF key
    JS->>SV: Tải PDF (làm rối riêng cho bài này) → vẽ canvas + in chìm
    loop Mỗi lần tô / đổi đáp án
        HS->>JS: Tô ô tròn
        JS->>LS: Ghi bản dự phòng (seq + 1)
        JS->>SV: POST save {seq, answers} (gom 1,2 giây)
        SV->>DB: Chỉ ghi khi seq mới hơn (chống ghi đè do mạng chậm)
        SV-->>JS: Đã lưu · giờ còn lại · tin nhắn giám thị
    end
    loop Nhịp tim 20 giây
        JS->>SV: ping → đồng bộ giờ, trạng thái tạm dừng / khóa, tin nhắn
    end
    alt Học sinh bấm Nộp bài
        JS->>SV: submit (xác nhận danh sách câu chưa làm)
    else Hết giờ
        JS->>SV: ping kiểm tra lại (có thể vừa được cộng giờ) → submit auto
    else Mất mạng lúc hết giờ
        SV->>DB: Hết thời gian ân hạn → tự thu bằng bản đã lưu gần nhất
    end
    SV->>DB: Chấm điểm, lưu kết quả
    SV-->>HS: Trang kết quả (điểm từng phần theo chính sách ca thi)
```

### Trạng thái bài làm

```mermaid
stateDiagram-v2
    direction LR
    DangLam: Đang làm (in_progress)
    TamDung: Tạm dừng / bị khóa (giờ đứng yên)
    DaNop: Đã nộp (submitted)
    HetGio: Hết giờ – tự thu (expired)
    BiThu: Bị thu bài (forced)
    Huy: Đã hủy – cho thi lại (voided)
    [*] --> DangLam: Bắt đầu
    DangLam --> TamDung: Tạm dừng / vi phạm
    TamDung --> DangLam: Tiếp tục / mở khóa
    DangLam --> DaNop: Học sinh nộp
    DangLam --> HetGio: Hết giờ + ân hạn
    DangLam --> BiThu: Thu bài / vi phạm / kết thúc ca
    TamDung --> BiThu: Thu bài / kết thúc ca
    DaNop --> DangLam: Mở lại (nộp nhầm)
    HetGio --> DangLam: Mở lại + cộng giờ
    BiThu --> DangLam: Mở lại
    DangLam --> Huy: Hủy bài
    DaNop --> Huy: Hủy bài
    HetGio --> Huy: Hủy bài
    BiThu --> Huy: Hủy bài
    Huy --> [*]
```

## 🛟 Xử lý sự cố trong giờ thi

| Tình huống | Hệ thống tự xử lý | Giám thị cần làm |
|---|---|---|
| **Mất mạng** giữa giờ | Học sinh vẫn tô bình thường, bài lưu vào máy, báo “Mất mạng · đã lưu trên máy”, tự gửi khi có mạng | Không cần; nếu kéo dài, xem cảnh báo “mất kết nối” trên bảng giám sát |
| **Tải lại trang / tắt nhầm trình duyệt** | Vào lại là thấy nguyên bài; câu chưa kịp gửi được khôi phục từ bản dự phòng | Không cần |
| **Máy hỏng, mất điện** | Bài đã lưu trên máy chủ; máy mới bị chặn cho đến khi được mở khóa (trừ khi ca thi bật *Tự nhận lại cùng máy* và máy mới cùng IP + cùng loại trình duyệt/hệ điều hành – khi đó bài tự chuyển sang) | Cho học sinh sang máy khác đăng nhập → bấm **Mở khóa thiết bị** (máy cũ bị thu hồi quyền) |
| **Mất điện cả phòng / sự cố chung** | — | **Tạm dừng cả phòng**: thời gian của mọi học sinh đứng yên; khi ổn định bấm **Tiếp tục ca thi** |
| **Hết phiên đăng nhập** (mạng chập chờn lâu) | Hộp đăng nhập lại hiện ngay trong phòng thi; bài làm giữ nguyên, đồng hồ vẫn chạy trong lúc đăng nhập lại | Không cần |
| **Mở bài thi ở 2 tab** | Tab cũ tự dừng để không ghi đè | Không cần (có ghi nhật ký) |
| **Hết giờ đúng lúc mất mạng** | Chờ thêm thời gian ân hạn (mặc định 90 giây) rồi tự thu bằng bản lưu gần nhất | Có thể **Mở lại bài** + cộng phút nếu cần |
| **Nộp nhầm** | — | **Mở lại bài** (có thể cộng thêm phút) |
| **Rời màn hình nhiều lần** | Cảnh báo, ghi nhận; tùy cấu hình: chỉ ghi nhận / tạm khóa / tự thu bài | Xác minh rồi **Mở khóa vi phạm** |
| **Học sinh cần làm lại từ đầu** | — | **Hủy bài & cho thi lại** (bài cũ vẫn lưu để đối chiếu) |

Mọi thao tác đều được ghi vào **nhật ký bài làm** (thời gian, IP, thiết bị, từng lần tô) để giải quyết khiếu nại.

## 🧮 Cách tính điểm theo quy định 2025

| Phần | Cách chấm | Toán | Lí · Hóa · Sinh · Địa | Sử · GDKT&PL · Tin · Công nghệ | Ngoại ngữ |
|---|---|---|---|---|---|
| **I** – nhiều lựa chọn | 0,25 đ/câu | 12 câu = 3 đ | 18 câu = 4,5 đ | 24 câu = 6 đ | 40 câu = 10 đ |
| **II** – đúng/sai | đúng 1 ý 0,1 đ · 2 ý 0,25 đ · 3 ý 0,5 đ · 4 ý 1 đ | 4 câu = 4 đ | 4 câu = 4 đ | 4 câu = 4 đ | — |
| **III** – trả lời ngắn | Toán 0,5 đ/câu, môn khác 0,25 đ/câu | 6 câu = 3 đ | 6 câu = 1,5 đ | — | — |

Ngoài ra có thể: đặt điểm riêng từng câu, **hủy câu** (tính đúng cho mọi thí sinh), chấp nhận **nhiều đáp án** (`1,5|1,50`), so sánh Phần III theo giá trị số (`0,5` = `0,50`) hoặc đúng từng ký tự, trừ điểm câu sai Phần I, Phần II tính theo từng ý hoặc chỉ khi đúng cả 4 ý, quy về thang điểm bất kỳ, làm tròn theo bước.

## 🔐 Bảo mật

- Mật khẩu băm **bcrypt**; chống dò mật khẩu theo tài khoản và theo IP; bắt đổi mật khẩu lần đầu (tùy chọn).
- Mọi yêu cầu POST kiểm tra **CSRF**; cookie `HttpOnly`, `SameSite`; tự bật `Secure` khi chạy HTTPS.
- Truy vấn dùng tham số (PDO prepared statements); giao diện thoát ký tự HTML ở mọi nơi.
- Thư mục `app/`, `storage/`, `tools/`, `docs/` và tệp CSDL bị chặn truy cập từ web (`.htaccess`, `web.config`, mẫu Nginx).
- Giáo viên chỉ thấy **lớp được phân công** và **đề của mình / được chia sẻ**; giám thị chỉ xem & điều khiển **ca được phân công** (không thấy điểm nếu vai trò không có quyền xem kết quả).
- Đề thi PDF: không có đường dẫn tải trực tiếp, dữ liệu được làm rối riêng cho từng bài làm, in chìm tên & SBD.

> ⚠️ Không hệ thống web nào chặn tuyệt đối việc chụp màn hình hoặc dùng thiết bị khác. Hệ thống **hạn chế và ghi nhận** (in chìm, rời màn hình, nhiều tab, đổi máy) để giám thị xử lý.

## 💾 Sao lưu & chuyển đổi CSDL

- **Sao lưu** → tệp `.tnbak` (toàn bộ dữ liệu, gồm cả PDF, logo), tải thẳng về máy, không lưu trên hosting.
- **Phục hồi** → tải lên theo từng khúc, chạy nhiều bước ngắn (không vướng giới hạn thời gian của hosting).
- **SQLite ↔ MySQL**: sao lưu ở hệ thống cũ → cài mới với loại CSDL kia (trên cùng hosting: xóa `storage/config.php` trước để mở lại trình cài đặt) → phục hồi. Đã kiểm thử khớp 100% số bản ghi và mã băm tệp.
- Với SQLite còn có thể tải nguyên tệp `.sqlite` (ảnh chụp nhất quán bằng `VACUUM INTO`).

## 🗂 Cấu trúc mã nguồn

```
├── index.php            # Front controller (mọi trang đi qua đây)
├── install.php          # Trình cài đặt (SQLite / MySQL)
├── cron.php             # Tác vụ định kỳ (tùy chọn)
├── VERSION              # Số phiên bản – tự tăng khi commit
├── app/
│   ├── Core/            # Database, Router, Auth, Session (lưu CSDL), FileStore, Schema, Logger…
│   ├── Lib/             # Scoring, Attempts, Sessions, Stats, Backup, KeyImporter, Xlsx*, Zip*…
│   ├── Controllers/     # 25 controller: Students, Exams, Sessions, ExamRoom, Monitor, Results, Stats…
│   └── Views/           # Giao diện PHP (layout quản trị, cổng học sinh, phòng thi)
├── assets/
│   ├── css/             # app.css (hệ thống thiết kế), exam.css (phòng thi & phiếu trả lời)
│   ├── js/              # app.js, exam.js, answersheet.js, pdfviewer.js (+ compat.js cho trình duyệt cũ), monitor.js…
│   └── vendor/          # PDF.js, Chart.js, KaTeX, phông Be Vietnam Pro (tự lưu trữ, không CDN)
├── docs/                # Hướng dẫn sử dụng, ảnh minh họa, tệp mẫu (Excel, JSON, PDF) – không phục vụ qua web
├── storage/             # config.php + tệp SQLite (bị chặn truy cập từ web)
└── tools/               # bump-version.sh
```

## 🧑‍💻 Phát triển

```bash
git config core.hooksPath .githooks     # bật hook tự tăng phiên bản (PATCH) mỗi lần commit mã nguồn
sh tools/bump-version.sh minor && git add VERSION   # tăng MINOR/MAJOR thủ công khi phát hành
```

- Không dùng framework, Composer hay bước build: sửa tệp PHP/CSS/JS là chạy ngay.
- Cấu trúc CSDL khai báo một lần trong `app/Core/Schema.php`, tự sinh DDL cho cả SQLite và MySQL; khi thêm cột, tăng `Schema::VERSION` và thêm hàm nâng cấp – hệ thống **tự nâng cấp** ở lần truy cập kế tiếp.
- Mọi chuỗi giao diện bằng tiếng Việt; giờ hiển thị luôn là **UTC+7** (`Asia/Ho_Chi_Minh`).

## 📄 Giấy phép

Phát hành theo giấy phép [MIT](LICENSE). Thư viện kèm theo: [PDF.js](https://mozilla.github.io/pdf.js/) (Apache-2.0), [Chart.js](https://www.chartjs.org/) (MIT), [KaTeX](https://katex.org/) (MIT), biểu tượng [Lucide](https://lucide.dev/) (ISC), phông [Be Vietnam Pro](https://fonts.google.com/specimen/Be+Vietnam+Pro) (OFL).

<div align="center">

**📘 Xem [Hướng dẫn sử dụng chi tiết](docs/HUONG-DAN-SU-DUNG.md)** · Lịch sử thay đổi: [CHANGELOG](CHANGELOG.md)

</div>

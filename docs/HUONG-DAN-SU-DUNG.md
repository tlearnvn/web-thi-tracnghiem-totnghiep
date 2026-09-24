# 📘 Hướng dẫn sử dụng – Hệ thống thi trắc nghiệm trực tuyến (định dạng 2025)

> Tài liệu dành cho **quản trị viên, giáo viên, giám thị và học sinh**. Mọi thời gian trong hệ thống là **giờ Việt Nam (UTC+7)**.
> Quay lại [README](../README.md) để xem phần cài đặt và kiến trúc.
> Bản PDF để in hoặc gửi cho giáo viên, học sinh: [HUONG-DAN-SU-DUNG.pdf](HUONG-DAN-SU-DUNG.pdf).

## Mục lục

1. [Vai trò & quyền hạn](#1-vai-trò--quyền-hạn)
2. [Bắt đầu nhanh trong 10 bước](#2-bắt-đầu-nhanh-trong-10-bước)
3. [Lớp học, học sinh, giáo viên](#3-lớp-học-học-sinh-giáo-viên)
4. [Môn thi & định dạng đề](#4-môn-thi--định-dạng-đề)
5. [Đề thi, mã đề, đáp án & lời giải](#5-đề-thi-mã-đề-đáp-án--lời-giải)
6. [Tạo ca thi](#6-tạo-ca-thi)
7. [Giám sát trong giờ thi & xử lý sự cố](#7-giám-sát-trong-giờ-thi--xử-lý-sự-cố)
8. [Dành cho học sinh: làm bài thi](#8-dành-cho-học-sinh-làm-bài-thi)
   - [Phần II – Trắc nghiệm đúng / sai](#83-phần-ii--trắc-nghiệm-đúng--sai)
   - [Phần III – Trắc nghiệm trả lời ngắn](#84-phần-iii--trắc-nghiệm-trả-lời-ngắn)
   - [Trước khi nộp: rà soát Phần II & III](#85-trước-khi-nộp-rà-soát-phần-ii--iii)
9. [Kết quả, chấm tự luận, in phiếu, xuất Excel](#9-kết-quả-chấm-tự-luận-in-phiếu-xuất-excel)
10. [Thống kê & phân tích câu hỏi](#10-thống-kê--phân-tích-câu-hỏi)
11. [Cài đặt hệ thống, thông báo](#11-cài-đặt-hệ-thống-thông-báo)
12. [Sao lưu, phục hồi, bảo trì](#12-sao-lưu-phục-hồi-bảo-trì)
13. [Câu hỏi thường gặp](#13-câu-hỏi-thường-gặp)

---

## 1. Vai trò & quyền hạn

Hệ thống có sẵn 5 vai trò (quyền mặc định, quản trị có thể chỉnh):

| Chức năng | Quản trị | Cán bộ quản lý | Giáo viên | Giám thị | Học sinh |
|---|:---:|:---:|:---:|:---:|:---:|
| Trang tổng quan | ✅ | ✅ | ✅ | ✅ | trang *Bài thi của em* |
| Đăng thông báo | ✅ | ✅ | ✅ | — | xem thông báo |
| Học sinh | toàn trường | xem toàn trường | lớp được phân công (thêm, sửa, nhập Excel, cấp lại mật khẩu) | — | — |
| Lớp học | thêm, sửa, phân công | xem | xem | — | — |
| Giáo viên, tài khoản, phân quyền | ✅ | xem giáo viên | — | — | — |
| Môn thi & định dạng đề | ✅ | — | — | — | — |
| Đề thi, mã đề, đáp án | tất cả | xem đề được chia sẻ | đề của mình; đề được chia sẻ chỉ **xem & dùng** | — | — |
| Tạo / sửa ca thi | tất cả | tất cả | ca của mình | — | — |
| Giám sát, cộng giờ, mở khóa, thu bài | ✅ | mọi ca | ca của mình / được phân công | ca được phân công (không thấy điểm) | — |
| Kết quả, chấm tự luận, chấm lại | ✅ | xem mọi kết quả | ca / lớp của mình | — | xem điểm của mình |
| Thống kê, xuất Excel | ✅ | ✅ | ca / lớp của mình | — | — |
| Nhật ký hệ thống | ✅ | ✅ | — | — | — |
| Cài đặt, sao lưu & phục hồi | ✅ | — | — | — | — |
| Làm bài thi, luyện tập | — | — | — | — | ✅ |

Quản trị viên có thể **tạo vai trò mới** (ví dụ *Tổ trưởng chuyên môn*, *Cán bộ khảo thí*) và tick từng quyền: **Tài khoản & phân quyền → Ma trận phân quyền** (trang *Phân quyền theo vai trò*) → **Thêm vai trò**.

---

## 2. Bắt đầu nhanh trong 10 bước

```mermaid
flowchart TD
    S1["1. Cài đặt (install.php)"] --> S2["2. Cài đặt → tên đơn vị, logo, chân trang"]
    S2 --> S3["3. Lớp học → tạo lớp"]
    S3 --> S4["4. Học sinh → Nhập từ Excel → in phiếu tài khoản"]
    S4 --> S5["5. Giáo viên → Nhập / thêm, phân công lớp"]
    S5 --> S6["6. Đề thi → Tạo đề (chọn môn: cấu trúc 2025 tự điền)"]
    S6 --> S7["7. Tải PDF từng mã đề + nhập đáp án & lời giải"]
    S7 --> S8["8. Ca thi → Tạo ca: lớp, giờ, mã phòng, quy định"]
    S8 --> S9["9. Giờ thi: Giám sát trực tiếp + màn hình trình chiếu"]
    S9 --> S10["10. Kết quả → Thống kê → Xuất Excel → Công bố điểm"]
```

> 💡 Ở trình cài đặt, ô **Tạo dữ liệu mẫu để dùng thử** được chọn sẵn: có ngay 2 lớp, 20 học sinh, đề Toán minh họa (PDF + đáp án + lời giải), một ca thi đang mở và một ca luyện tập để thử toàn bộ quy trình. Bỏ chọn nếu cài để thi thật (hoặc sau này vào *Thông tin hệ thống → Xóa dữ liệu mẫu*).

![Trang tổng quan của quản trị](images/03-tong-quan.jpg)

---

## 3. Lớp học, học sinh, giáo viên

### 3.1. Lớp học
**Quản lý → Lớp học → Thêm lớp**: tên lớp (`12A1`), khối, năm học, giáo viên chủ nhiệm, các giáo viên bộ môn. Giáo viên được phân công sẽ thấy học sinh, kết quả của lớp đó.

### 3.2. Nhập học sinh từ Excel

![Nhập học sinh](images/15-nhap-hoc-sinh.jpg)

1. **Học sinh → Nhập từ Excel**. Tải *tệp mẫu* nếu cần ([mẫu tại đây](samples/mau-nhap-hoc-sinh.xlsx)).
2. Chọn tệp `.xlsx` hoặc `.csv` → hệ thống **tự nhận dạng cột** (không cần đúng thứ tự) và hiển thị **bản xem trước**: dòng lỗi, trùng tài khoản, lớp mới sẽ được tạo.
3. Chọn cách đặt **tên đăng nhập** (*Dùng Mã học sinh* hoặc *Tạo từ họ tên*, vd `annv`, `annv2`…; nếu tệp có cột *Tên đăng nhập* thì dùng cột đó) và **mật khẩu** (6 chữ số ngẫu nhiên / 8 ký tự chữ + số ngẫu nhiên / theo ngày sinh `ddmmyyyy` / giống tên đăng nhập / một mật khẩu chung); có thể bắt học sinh đổi mật khẩu khi đăng nhập lần đầu.
4. Bấm **Xác nhận nhập N dòng** → **Xem & in N tài khoản vừa cấp** → **Tải Excel** hoặc **In phiếu tài khoản** để cắt phát cho học sinh. Danh sách mật khẩu chỉ lưu tạm **2 giờ** – hãy tải / in ngay.

| Cột được nhận dạng | Tên cột có thể dùng |
|---|---|
| Họ và tên \* | `Họ và tên`, `Họ tên`, hoặc 2 cột `Họ đệm` + `Tên` |
| Mã học sinh / SBD | `Mã HS`, `Mã học sinh`, `SBD`, `Số báo danh`, `Mã định danh` |
| Lớp | `Lớp`, `Tên lớp` (lớp chưa có sẽ được tạo) |
| Ngày sinh | `Ngày sinh` – nhận `dd/mm/yyyy`, `yyyy-mm-dd`, ô ngày của Excel |
| Giới tính | `Giới tính`, `Nữ` (đánh dấu x) |
| Tên đăng nhập, mật khẩu, email, điện thoại, ghi chú | (tùy chọn) |

✔️ Họ tên được **chuẩn hóa tự động** (`lê hoàng  châu` → `Lê Hoàng Châu`), sắp xếp **theo tên** đúng kiểu Việt Nam.

### 3.3. Giáo viên
**Quản lý → Giáo viên**: thêm từng người hoặc nhập Excel ([mẫu](samples/mau-nhap-giao-vien.xlsx)) với các cột *Họ và tên, Mã GV, Môn, Lớp chủ nhiệm, Lớp dạy, Vai trò…*

### 3.4. Tài khoản
- **Cấp lại mật khẩu**, **khóa / mở khóa tài khoản** ngay trong danh sách học sinh (từng em hoặc chọn nhiều em); **Buộc đăng xuất** khỏi mọi thiết bị ở *Tài khoản & phân quyền* (quản trị).
- Có thể bắt học sinh **đổi mật khẩu lần đầu** đăng nhập.

---

## 4. Môn thi & định dạng đề

**Tổ chức thi → Môn thi & định dạng** có sẵn cấu trúc đề thi tốt nghiệp từ năm 2025:

| Môn | Phần I (0,25 đ/câu) | Phần II (tối đa 1 đ/câu) | Phần III | Thời gian |
|---|---|---|---|---|
| Toán | 12 câu | 4 câu | 6 câu × 0,5 đ | 90 phút |
| Vật lí, Hóa học, Sinh học, Địa lí | 18 câu | 4 câu | 6 câu × 0,25 đ | 50 phút |
| Lịch sử, GDKT&PL, Tin học, Công nghệ | 24 câu | 4 câu | — | 50 phút |
| Tiếng Anh, Pháp, Trung, Nhật, Hàn, Đức, Nga | 40 câu | — | — | 50 phút |
| Ngữ văn | — | — | — (7 câu **tự luận**: Đọc hiểu 4 đ + Viết 6 đ, giáo viên chấm) | 120 phút |

Có thể sửa số câu, thêm **câu tự luận**, đổi **độ dài ô trả lời ngắn** (mặc định 4 ký tự như phiếu của Bộ), và chọn **cách tính điểm**:

| Cách tính | Mô tả |
|---|---|
| **Theo quy định Bộ GD&ĐT 2025** | Phần I 0,25 đ; Phần II đúng 1/2/3/4 ý = 0,1/0,25/0,5/1 đ; Phần III 0,5 đ (Toán) hoặc 0,25 đ |
| **Tùy chỉnh** | Tự đặt điểm từng phần, **trừ điểm câu sai Phần I**, Phần II theo bảng riêng / theo từng ý / chỉ tính khi đúng cả 4 ý, quy về thang điểm bất kỳ |
| **Theo tỉ lệ** | Điểm = số câu/ý đúng ÷ tổng số câu/ý × (thang điểm − điểm tối đa phần tự luận) + điểm tự luận do giáo viên chấm |

Tùy chọn chung: **làm tròn** (0,01 / 0,05 / 0,1 / 0,25 / 0,5 / 1), **không cho điểm âm**, so sánh Phần III **theo giá trị số** (`0,5` = `0,50`) hoặc **đúng từng ký tự**.

---

## 5. Đề thi, mã đề, đáp án & lời giải

![Trang đề thi](images/14-de-thi.jpg)

### 5.1. Tạo đề & tải PDF
1. **Đề thi & đáp án → Tạo đề thi**: chọn môn (cấu trúc tự điền), tên đề, thời gian, có **chia sẻ** cho giáo viên khác không.
2. Thêm **mã đề** (`0101`, `0102`…) – hoặc nhập đáp án nhiều mã đề một lần, hệ thống tự tạo mã đề.
3. Mỗi mã đề: **Tải PDF đề** và (tùy chọn) **PDF lời giải**. Tệp được tải theo từng khúc 512 KB nên **không vướng giới hạn tải lên của hosting**, và được **lưu trong CSDL**.

> Có thể tổ chức thi **đề giấy**: không tải PDF, học sinh chỉ tô phiếu trả lời trên máy.

### 5.2. Nhập đáp án

![Soạn đáp án cạnh đề](images/13-nhap-dap-an.jpg)

**Cách 1 – Soạn trực tiếp**: bấm **Đề & đáp án** ở mã đề → màn hình 2 cột (đề PDF | phiếu) → bấm ô tròn để chọn đáp án đúng. Biểu tượng ✏️ ở mỗi câu để nhập **lời giải chi tiết, điểm riêng, mức độ, chủ đề, câu gốc** hoặc **hủy câu**. Nút **Nhập nhanh** cho phép gõ `ABCDBACDABCD` cho cả Phần I. Phím tắt `Ctrl + S` để lưu.

Với **Phần II** và **Phần III** trên màn hình soạn đáp án:

| Phần | Cách nhập đáp án trên phiếu | Trong ô ✏️ của từng câu |
|---|---|---|
| **II** – đúng/sai | Bấm ô **Đúng** / **Sai** cho đủ 4 ý a–d (thiếu ý nào thì câu đó coi như chưa có đáp án) | Ô *Đáp án* gõ 4 ký tự `DSDD`; dấu `*` ở vị trí nào là **hủy ý** đó (vd `DS*D` – ý c tính đúng cho mọi em); *điểm riêng* của câu (bảng 0,1/0,25/0,5/1 được nhân theo tỉ lệ); hủy cả câu; mức độ, chủ đề, lời giải |
| **III** – trả lời ngắn | Gõ đáp số vào ô của câu (`-1,5`, `0.17` tự đổi thành `0,17`); nhiều đáp án chấp nhận: `1,5\|1,50` | Điểm riêng, **hủy câu**, mức độ, chủ đề, lời giải |

![Soạn đáp án Phần II](images/32-phan-2-nhap-dap-an.jpg)

![Soạn đáp án Phần III](images/33-phan-3-nhap-dap-an.jpg)

**Cách 2 – Excel** ([mẫu](samples/mau-dap-an-toan.xlsx)). Hệ thống tự nhận dạng 3 kiểu bảng:

| Kiểu | Hình dạng | Ví dụ |
|---|---|---|
| **A. Dọc** – mỗi dòng 1 câu | `Mã đề · Phần · Câu · Đáp án · Điểm · Mức độ · Chủ đề · Câu gốc · Lời giải` | `0101 · I · 1 · A` / `0101 · II · 1 · ĐSĐĐ` / `0101 · III · 1 · -1,5` |
| **B. Ngang** – mỗi dòng 1 mã đề | `Mã đề · I.1 · I.2 · … · II.1 · … · III.6` | `0101 · A · B · … · ĐSĐĐ · … · 0,17` |
| **C. Bảng** – mỗi cột 1 mã đề (kiểu phần mềm trộn đề xuất ra) | `Câu · 0101 · 0102 · 0103…` | `1 · A · C · B` |

Quy ước đáp án:
- **Phần I**: `A`/`B`/`C`/`D`; nhiều đáp án đúng: `AB`; hủy câu: `*`.
- **Phần II**: 4 ký tự theo ý a–d: `Đ`/`D`/`1`/`T` = đúng, `S`/`0`/`F` = sai → ví dụ `ĐSĐĐ`, `1011`, `TFTT`. Hoặc 4 dòng riêng: cột *Câu* ghi `1a`, `1b`, `1c`, `1d`, đáp án `Đ` / `S`.
- **Phần III**: số thập phân dùng dấu phẩy hoặc chấm (`-1,5`, `0.17`); nhiều đáp án chấp nhận: `1,5|1,50`.

**Cách 3 – JSON** ([ví dụ đầy đủ](samples/dap-an-mau-toan.json)), dạng gọn:

```json
{
  "variants": [
    { "code": "0101",
      "p1": "ABCDBACDABCD",
      "p2": ["DSDD", "DDSS", "DDSD", "DDDS"],
      "p3": ["4", "6", "3", "8", "0,17", "-2"],
      "explanations": { "p1": { "1": "Ta có $\\int (3x^2+1)dx = x^3+x+C$. Chọn **A**." } } }
  ]
}
```

Sau khi nhập, hệ thống hiển thị **bản xem trước**: số câu mỗi mã đề, câu thiếu, đáp án sai quy cách, cảnh báo đánh số lại.

### 5.3. Lời giải chi tiết
Lời giải viết dạng văn bản có định dạng đơn giản: `**in đậm**`, `*nghiêng*`, danh sách `- …`, ảnh `![](https://…)`, **công thức toán** `$x^2$`, `$$\int_0^1 f(x)dx$$` (hiển thị bằng KaTeX). Học sinh xem ở trang **Xem lại bài** nếu ca thi cho phép.

---

## 6. Tạo ca thi

![Tạo ca thi](images/23-tao-ca-thi.jpg)

**Ca thi → Tạo ca thi** (hoặc *Tạo ca luyện tập* – học sinh làm lại nhiều lần, xem điểm & lời giải ngay):

| Nhóm | Tùy chọn |
|---|---|
| Cơ bản | Đề thi, tên ca, phòng thi, **mã vào phòng** (giám thị đọc tại chỗ), lớp dự thi + học sinh thêm lẻ theo tên đăng nhập / mã, giám thị phụ trách |
| Thời gian | Giờ mở – giờ đóng, thời gian làm bài (mặc định theo đề), **cho vào muộn tối đa X phút**, chính sách: *không vượt giờ đóng ca* hoặc *luôn đủ thời gian*, thời gian ân hạn khi hết giờ |
| Mã đề | Ngẫu nhiên cân bằng số lượng / lần lượt / cố định một mã |
| Lượt làm | Số lượt tối đa (0 = không giới hạn), lấy điểm lần **cao nhất / mới nhất / đầu tiên** |
| Chống gian lận | Khóa thiết bị, tự nhận lại cùng máy, ghi nhận rời màn hình, bắt buộc toàn màn hình, số lần rời màn hình tối đa → *ghi nhận / tạm khóa / tự thu bài*, in chìm đề, chống tải PDF, nộp bài sớm nhất sau X phút |
| Kết quả | Khi nào học sinh **xem điểm** (ngay / sau ca thi / khi công bố / không), khi nào **xem lại bài**, có hiện đáp án / lời giải / PDF lời giải không |

Trong trang ca thi có các nút: **Bắt đầu ngay**, **Tạm dừng cả phòng** / **Tiếp tục ca thi**, **Cộng giờ cả phòng**, **Gửi thông báo**, **Kết thúc & thu bài**, **Công bố điểm**.

---

## 7. Giám sát trong giờ thi & xử lý sự cố

![Giám sát trực tiếp](images/11-giam-sat.jpg)

Mở **Ca thi → Giám sát** (tự cập nhật vài giây/lần):
- Số học sinh **đang làm (có kết nối)**, **mất kết nối**, **chưa vào**, **đã nộp**, **có rời màn hình**.
- Hộp **cảnh báo cần xử lý**: máy khác đang cố vào bài (nút *Mở khóa thiết bị*), bị khóa do vi phạm (nút *Mở khóa*), mất kết nối quá 2 phút.
- **Giám thị được phân công** mở được trang này, điều khiển ca và xử lý sự cố; cột điểm chỉ hiện với người có quyền xem kết quả.
- Mỗi học sinh: tiến độ, thời gian còn lại, số lần rời màn hình, thiết bị & IP, điểm (khi nộp); menu **⋮** để *cộng / bớt giờ, nhắn tin riêng, tạm dừng / tiếp tục, mở khóa thiết bị, mở khóa vi phạm, thu bài, mở lại bài, hủy bài cho thi lại*.
- Chọn nhiều học sinh để thao tác hàng loạt; lọc *Cần xử lý*; tìm theo tên / SBD.
- **Màn hình trình chiếu** (nút góc trên): đồng hồ giờ máy chủ, **mã vào phòng**, thời gian còn lại của ca – chiếu lên máy chiếu cho cả phòng.

![Màn hình trình chiếu](images/22-trinh-chieu.jpg)

### Sơ đồ xử lý sự cố

```mermaid
flowchart TD
    Q{"Học sinh báo sự cố"} -->|"Máy treo / hỏng / mất điện"| A["Cho sang máy khác<br/>đăng nhập lại"]
    A --> A2{"Màn hình báo<br/>'đang làm trên máy khác'?"}
    A2 -->|Có| A3["Giám sát → ⋮ → <b>Mở khóa thiết bị</b><br/>(máy cũ bị thu hồi quyền)"]
    A2 -->|"Không (khóa thiết bị tắt, hoặc<br/>tự nhận lại: cùng IP + cùng trình duyệt)"| A4["Làm tiếp ngay – bài đã lưu"]
    A3 --> A4
    Q -->|"Mất mạng cả phòng"| B["<b>Tạm dừng cả phòng</b><br/>→ giờ của mọi em đứng yên"]
    B --> B2["Có mạng lại → <b>Tiếp tục ca thi</b>"]
    Q -->|"Mất mạng 1 máy"| C["Cứ làm tiếp – bài lưu trên máy,<br/>tự gửi khi có mạng"]
    Q -->|"Bấm nộp nhầm"| D["⋮ → <b>Mở lại bài</b> (+ phút)"]
    Q -->|"Bị khóa do rời màn hình"| E["Xác minh → ⋮ → <b>Mở khóa vi phạm</b>"]
    Q -->|"Mất thời gian do sự cố"| F["⋮ → <b>Cộng giờ</b> cho em đó"]
    Q -->|"Cần thi lại từ đầu"| G["⋮ → <b>Hủy bài & cho thi lại</b>"]
```

> 🔎 Mọi thao tác của học sinh và giám thị đều nằm trong **nhật ký bài làm** (*Kết quả → Chi tiết bài làm*): lúc vào thi, từng lần tô / đổi đáp án, rời màn hình, đổi máy, được cộng giờ… dùng khi giải quyết khiếu nại.

---

## 8. Dành cho học sinh: làm bài thi

### 8.1. Trước giờ thi
1. Đăng nhập bằng tài khoản được phát.

   ![Trang đăng nhập](images/01-dang-nhap.jpg)

2. Trang **Bài thi của em** hiện các bài đang mở, sắp diễn ra (đếm ngược), đã làm; thông báo của nhà trường nằm ngay đầu trang.

   ![Trang Bài thi của em](images/04-cong-hoc-sinh.jpg)

3. Bấm **Vào phòng thi** → đọc **quy định phòng thi**, xem **Kiểm tra máy** (trình duyệt – có nạp thử bộ hiển thị đề, kết nối, đồng hồ) → nhập **mã vào phòng** (nếu có) → tick cam kết → **Bắt đầu làm bài**. Nếu mục *Trình duyệt hỗ trợ phòng thi* báo đỏ, hãy dùng **Chrome / Edge 109 trở lên** (hoặc Firefox, Safari bản mới).

   ![Phòng chờ](images/05-phong-cho.jpg)

### 8.2. Trong giờ thi

![Phòng thi: đề PDF bên trái, phiếu trả lời bên phải](images/06-phong-thi.jpg)

Màn hình phòng thi gồm **đề PDF** (trái) và **phiếu trả lời** (phải); đầu trang có đồng hồ đếm ngược theo giờ máy chủ, tiến độ làm bài, trạng thái lưu và nút **Nộp bài**.

| Thao tác | Cách làm |
|---|---|
| Chọn đáp án Phần I | Bấm ô tròn A/B/C/D (bấm lại để bỏ). Bàn phím: `A` `B` `C` `D` hoặc `1`–`4`, `↑` `↓` chuyển câu |
| Phần II | Mỗi ý a), b), c), d) chọn **Đúng** hoặc **Sai** – chi tiết ở [mục 8.3](#83-phần-ii--trắc-nghiệm-đúng--sai) |
| Phần III | Tô từ trái sang phải: dấu `−` chỉ ở cột đầu, dấu phẩy ở cột 2 hoặc 3; hoặc **bấm vào dãy ô kết quả để gõ** (ví dụ `-1,5`). Tô sai quy cách sẽ có cảnh báo đỏ – chi tiết ở [mục 8.4](#84-phần-iii--trắc-nghiệm-trả-lời-ngắn) |
| Đánh dấu xem lại | Biểu tượng 🚩 hoặc phím `F`; lọc *Chưa làm* / 🚩 ở đầu phiếu |
| Đổi độ rộng 2 cột | Kéo thanh chia giữa (phím `←` `→`), bấm đúp để về mặc định, nút `«` `»` để thu gọn |
| Phóng to đề | `Ctrl` + lăn chuột trên đề, hoặc chọn tỉ lệ; nút 🌙 đọc đề nền tối |
| Giao diện tối | Nút ☀️ góc trên phải đổi cả phòng thi sang nền tối, dịu mắt khi làm bài lâu |
| Trạng thái lưu | Góc trên: *Đã lưu* · *Đang lưu* · *Mất mạng · đã lưu trên máy* |
| Nộp bài | Nút **Nộp bài** → hệ thống liệt kê câu chưa làm, câu đánh dấu, câu tô sai quy cách → xác nhận |

![Giao diện tối của phòng thi](images/06b-phong-thi-toi.jpg)

![Nộp bài](images/08-nop-bai.jpg)

Trên **điện thoại / máy tính bảng**, đề và phiếu chuyển thành 2 tab *Đề thi* – *Phiếu trả lời*:

![Điện thoại](images/07-phong-thi-dien-thoai.jpg)

### 8.3. Phần II – Trắc nghiệm đúng / sai

![Phần II khi làm bài: câu 1 đủ 4 ý, câu 2 mới chọn 2 ý, câu 3 có đánh dấu, câu 4 chưa làm](images/26-phan-2-lam-bai.jpg)

Mỗi câu gồm **4 ý a), b), c), d)**; mỗi ý có 2 ô **Đúng** và **Sai** – bố cục giống phiếu trả lời của Bộ.

| Tính năng | Cách dùng / ý nghĩa |
|---|---|
| Chọn đáp án | Bấm ô *Đúng* hoặc *Sai* ở từng ý. Bấm ô còn lại để đổi; bấm lại ô đang chọn để **bỏ chọn**. Làm ý nào trước cũng được |
| Lưu tự động | Mỗi lần bấm đều được lưu (góc trên hiện *Đã lưu*) |
| Câu chưa đủ 4 ý | Có **dấu chấm cam** sau số câu (*Câu 2 ·* trong ảnh); ô **II.2** trên thanh số câu chuyển **màu cam** và được đếm vào bộ lọc *Chưa làm* |
| Đánh dấu xem lại | Biểu tượng cờ ở góc câu (hoặc phím `F` khi đang chọn câu) – khung câu viền cam (*Câu 3* trong ảnh) |
| Bộ đếm của phần | Góc phải tiêu đề: số câu đã làm / tổng số câu (vd `3/4`) |

![Thanh số câu phía trên phiếu](images/28-thanh-so-cau.jpg)

*Thanh số câu phía trên phiếu: xanh = đã làm, **cam** = câu đúng/sai chưa đủ 4 ý, gạch cam dưới ô = có đánh dấu, trắng = chưa làm. Bấm vào một ô để nhảy tới câu đó; nút* Chưa làm *và* 🚩 *để lọc.*

**Cách tính điểm theo quy định 2025** – dựa trên **số ý đúng** trong mỗi câu:

| Số ý đúng trong câu | 0 | 1 | 2 | 3 | 4 |
|---|:---:|:---:|:---:|:---:|:---:|
| Điểm | 0 | 0,1 | 0,25 | 0,5 | **1** |

> **Ví dụ:** đáp án `ĐSĐĐ`; em chọn a) Đúng, b) Đúng, c) Đúng, d) bỏ trống → đúng ý a và c (2 ý) → **0,25 điểm**. Ý bỏ trống không bao giờ được tính đúng, vì vậy hãy chọn **đủ cả 4 ý**.

Giáo viên có thể đổi cách tính Phần II (*theo từng ý* hoặc *chỉ cho điểm khi đúng cả 4 ý*), đặt điểm riêng cho một câu, hoặc **hủy một ý** – ý đó được tính đúng cho mọi thí sinh (xem [mục 4](#4-môn-thi--định-dạng-đề) và [mục 5.2](#52-nhập-đáp-án)).

### 8.4. Phần III – Trắc nghiệm trả lời ngắn

![Phần III khi làm bài: tô 4, −1,5, 0,17; câu 3 tô sai quy cách; câu 4 đang gõ trực tiếp](images/27-phan-3-lam-bai.jpg)

Mỗi câu có **dãy 4 ô kết quả** (hiện đáp số đang tô) và **lưới ô tròn 4 cột** giống phiếu của Bộ:

| Hàng ô tròn | Tô ở cột | Ý nghĩa |
|---|---|---|
| `−` | chỉ cột 1 | dấu âm |
| `,` | cột 2 hoặc cột 3 | dấu phẩy thập phân |
| `0` … `9` | cả 4 cột | chữ số |

**Hai cách ghi đáp số:**

1. **Tô ô tròn** – mỗi cột một ký tự, **tô từ trái sang phải**, các cột thừa bên phải để trống. Bấm lại ô đang tô để bỏ. Tô dấu phẩy ở cột khác thì dấu phẩy cũ tự bỏ (mỗi đáp số chỉ có một dấu phẩy).
2. **Gõ trực tiếp** – bấm vào dãy ô kết quả (hoặc dùng `Tab` tới câu rồi gõ số) → hiện ô nhập (*Câu 4* trong ảnh) → gõ `-1,5`; gõ dấu chấm `.` cũng được, hệ thống tự đổi thành dấu phẩy → các ô tròn **tự tô theo**. Nhấn `Enter`, `Tab` hoặc bấm ra ngoài để xong. Ô nhập chỉ nhận chữ số, dấu `−`, dấu phẩy, tối đa 4 ký tự.

**Ví dụ cách tô** (mỗi ô là một cột):

| Đáp số | Cột 1 | Cột 2 | Cột 3 | Cột 4 |
|---|:---:|:---:|:---:|:---:|
| `4` | 4 | | | |
| `12` | 1 | 2 | | |
| `-2` | − | 2 | | |
| `0,17` | 0 | , | 1 | 7 |
| `-1,5` | − | 1 | , | 5 |
| `2025` | 2 | 0 | 2 | 5 |
| `0,5` (không tô `,5`) | 0 | , | 5 | |

**Cảnh báo tô sai quy cách** – chữ đỏ hiện ngay dưới câu, dãy ô kết quả viền đỏ (*Câu 3* trong ảnh). Câu tô sai quy cách **không được tính điểm**, nên cần sửa trước khi nộp:

| Thông báo | Nguyên nhân – cách sửa |
|---|---|
| Bỏ trống ô ở giữa – phải tô từ trái sang phải | Có cột trống xen giữa (vd tô `1`, bỏ trống, tô `5`) → dồn các ký tự sang trái |
| Dấu "−" chỉ ở cột đầu tiên | Dấu âm đặt sau chữ số (khi gõ) → ghi dấu âm ở đầu |
| Chỉ được tô một dấu phẩy | Có 2 dấu phẩy (khi gõ) → giữ một |
| Dấu phẩy chỉ ở cột 2 hoặc 3 | Ví dụ gõ `,5` → ghi `0,5` |
| Thiếu chữ số sau dấu phẩy | Ví dụ `5,` → bỏ dấu phẩy hoặc ghi thêm chữ số |
| Chưa có chữ số | Mới tô dấu `−` hoặc dấu phẩy |

**Cách tính điểm:** đúng đáp số mới có điểm – **Toán 0,5 điểm/câu**; Vật lí, Hóa học, Sinh học, Địa lí **0,25 điểm/câu**. Mặc định so sánh **theo giá trị số** nên `0,5` = `0,50` và `-2` = `-2,0`. Giáo viên có thể chuyển sang so sánh *đúng từng ký tự*, chấp nhận nhiều đáp án (`1,5|1,50`) hoặc hủy câu.

Trên **điện thoại / máy tính bảng**, mỗi hàng hiện 1–2 câu cho dễ bấm; bấm vào dãy ô kết quả sẽ hiện **bàn phím số** để gõ đáp số:

![Phần II và Phần III trên điện thoại](images/34-phan-2-3-dien-thoai.jpg)

### 8.5. Trước khi nộp: rà soát Phần II & III

![Hộp xác nhận nộp bài liệt kê câu chưa đủ 4 ý và câu tô sai quy cách](images/29-nop-bai-canh-bao.jpg)

Bấm **Nộp bài**, hệ thống liệt kê riêng từng nhóm để em kiểm tra lần cuối:
- **Câu chưa làm**;
- **Câu đúng/sai chưa chọn đủ 4 ý** (Phần II);
- **Câu trả lời ngắn tô chưa đúng quy cách** (Phần III – *sẽ không được tính điểm*);
- **Câu đã đánh dấu** xem lại.

Bấm vào số câu trong danh sách để quay lại sửa (*Làm tiếp*). Nếu ca thi bật *Xác nhận khi nộp bài* (mặc định), nút **Nộp bài** chỉ bấm được sau 3 giây đếm ngược để tránh bấm nhầm.

### 8.6. Khi có sự cố – em cần nhớ
- **Mất mạng**: cứ làm tiếp, bài lưu trên máy và tự gửi khi có mạng.
- **Máy treo, mất điện**: báo giám thị, đăng nhập lại (ở máy khác nếu cần) → vào lại bài thi → bài vẫn còn.
- **Hiện hộp đăng nhập lại**: nhập mật khẩu là làm tiếp – bài làm vẫn còn nguyên, nhưng **đồng hồ vẫn chạy** trong lúc đăng nhập nên hãy nhập nhanh.
- **Không rời khỏi màn hình làm bài** (chuyển tab, mở ứng dụng khác) – mọi lần rời đi đều bị ghi nhận.
- Hết giờ, bài **tự động nộp**.

Khi vào bài từ một máy khác mà ca thi đang **khóa thiết bị**, màn hình báo *Bài thi đang được làm trên máy khác* kèm họ tên, SBD, mã bài để đọc cho giám thị. Giám thị bấm **Mở khóa thiết bị** là em làm tiếp được – hệ thống tự thử lại mỗi 6 giây, không cần tải lại trang:

![Bài thi đang được làm trên máy khác](images/12a-khoa-thiet-bi.jpg)

Khi mất mạng, thanh báo đỏ hiện ở đầu màn hình và trạng thái lưu đổi thành *Mất mạng · đã lưu trên máy*; em cứ làm tiếp, bài được lưu trên máy và tự gửi khi có mạng trở lại:

![Mất mạng vẫn làm tiếp](images/12b-mat-mang.jpg)

### 8.7. Sau khi nộp
- Trang **Kết quả**: điểm, xếp loại, điểm từng phần, số câu đúng (nếu ca thi cho xem).

  ![Trang kết quả](images/09-ket-qua.jpg)

- **Xem lại bài**: đề bên trái, phiếu bên phải tô **xanh** (đúng) / **đỏ** (sai), viền xanh là đáp án đúng, bấm 💡 để xem **lời giải chi tiết**.
- **Luyện tập**: làm lại nhiều lần, hệ thống lưu điểm cao nhất; **Kết quả & lịch sử** lưu toàn bộ bài đã làm.

![Xem lại bài](images/10-xem-lai.jpg)

**Xem lại Phần II** – từng ý được chấm riêng: chữ a), b), c), d) **xanh** là ý đúng, **đỏ** là ý sai hoặc bỏ trống; ô có **viền xanh** là đáp án; ô em chọn tô **xanh** (đúng) hoặc **đỏ** (sai). Góc mỗi câu ghi *số ý đúng · điểm* (vd `2/4 ý · 0,25đ`), góc phần ghi tổng điểm Phần II.

![Xem lại Phần II](images/30-phan-2-xem-lai.jpg)

**Xem lại Phần III** – khung câu **xanh** (đúng) / **đỏ** (sai), các ô em tô cũng đổi màu theo; dưới mỗi câu ghi **Đáp án** (nhiều đáp án chấp nhận thì hiện *… hoặc …*), kèm lý do nếu tô sai quy cách hoặc *Chưa trả lời*.

![Xem lại Phần III](images/31-phan-3-xem-lai.jpg)

---

## 9. Kết quả, chấm tự luận, in phiếu, xuất Excel

![Bảng điểm](images/16-bang-diem.jpg)

- **Kết quả & chấm bài → chọn ca thi**: bảng điểm cả lớp **kể cả học sinh vắng**, điểm từng phần, thời gian làm, số lần rời màn hình; lọc *Đã nộp / Vắng / Đang làm / Chờ chấm*; sắp theo danh sách hoặc theo điểm.
- **Chi tiết bài làm**: đáp án – học sinh chọn – đúng/sai từng câu, thời gian & thiết bị, **nhật ký đầy đủ**; nút *Xem lại bài* (giống màn hình học sinh), *In phiếu*, *Chấm lại*, *Mở lại*, *Hủy bài*.

![Chi tiết bài làm](images/24-chi-tiet-bai-lam.jpg)

- **Chấm tự luận**: nhập điểm từng câu (có hướng dẫn chấm), nhận xét → **Lưu & chấm bài tiếp** để chuyển nhanh sang bài chờ chấm kế tiếp.
- **Chấm lại** (toàn ca hoặc bài chọn): khi lưu hoặc nhập lại đáp án, hệ thống **tự chấm lại** các bài đã nộp; dùng nút này sau khi đổi cách tính điểm hoặc muốn chắc chắn.
- **In phiếu trả lời (bản lưu)**: mỗi học sinh 1 trang A4, có hoặc không tô đúng/sai – dùng lưu hồ sơ hoặc trả bài.

![In phiếu](images/21-in-phieu.jpg)

- **Xuất Excel** gồm 4 trang:
  1. **Kết quả** – SBD, họ tên, ngày sinh, lớp, mã đề, thời gian, điểm từng phần, tổng, xếp loại (có lọc, cố định dòng tiêu đề);
  2. **Chi tiết từng câu** – đáp án của từng mã đề và lựa chọn của từng học sinh, tô xanh/đỏ;
  3. **Thống kê** – chỉ số, xếp loại, phổ điểm, theo lớp;
  4. **Phân tích câu hỏi** – p, D, r, phân bố lựa chọn, nhận xét.
- **Công bố điểm**: khi ca thi đặt chế độ *xem điểm khi giáo viên công bố*.

---

## 10. Thống kê & phân tích câu hỏi

![Thống kê](images/17-thong-ke.jpg)

**Thống kê & phân tích → chọn ca thi** (lọc theo lớp):
- Điểm trung bình, trung vị, độ lệch chuẩn, tỉ lệ ≥ 5 và ≥ 8 (tính trên điểm thật), **độ tin cậy của đề (Cronbach α)** – tính riêng từng mã đề (cần ít nhất 5 bài), ô tổng hợp là trung bình các mã đề.
- **Phổ điểm** theo mức 0,5 điểm (giống phổ điểm Bộ công bố), **xếp loại**, **so sánh lớp**, tỉ lệ điểm theo **từng phần**, theo **mức độ nhận thức** và **chủ đề** (nếu nhập kèm đáp án).
- Danh sách **học sinh điểm cao** và **cần hỗ trợ thêm**.

![Phân tích câu hỏi](images/18-phan-tich-cau-hoi.jpg)

### Đọc bảng phân tích câu hỏi

| Chỉ số | Ý nghĩa | Cách đọc |
|---|---|---|
| **p** – độ khó | Tỉ lệ học sinh làm đúng (Phần II tính theo tỉ lệ ý đúng) | ≥ 0,8 dễ · 0,6–0,8 trung bình dễ · 0,4–0,6 trung bình · 0,2–0,4 khó · < 0,2 rất khó |
| **D** – độ phân biệt | Tỉ lệ đúng của 27% học sinh điểm cao **trừ** 27% điểm thấp | ≥ 0,4 *phân biệt tốt* · 0,2–0,4 chấp nhận được · < 0,2 *phân biệt kém* · **âm → nghi sai đáp án** |
| **r** – tương quan điểm câu | Câu làm đúng có đi cùng tổng điểm cao không | ≥ 0,3 tốt; gần 0 hoặc âm cần xem lại |
| **Phân bố lựa chọn** | Phần I: % chọn A/B/C/D/bỏ trống (ô xanh là đáp án); Phần II: % đúng từng ý a–d; Phần III: các đáp số hay gặp kèm số lượt | Phương án nhiễu hút nhiều hơn đáp án → kiểm tra lại đáp án / đề |
| **Cronbach α** | Độ nhất quán nội tại của đề (theo từng mã đề) | ≥ 0,9 rất cao · 0,8–0,9 cao · 0,7–0,8 chấp nhận được · 0,6–0,7 hơi thấp · < 0,6 thấp |

```mermaid
flowchart LR
    X["Câu có D < 0"] --> Y{"Đáp án có đúng không?"}
    Y -->|"Sai đáp án"| Z["Sửa đáp án → <b>Chấm lại</b> toàn ca"]
    Y -->|"Đề có lỗi / 2 đáp án"| W["<b>Hủy câu</b> (tính đúng cho mọi em)<br/>hoặc chấp nhận nhiều đáp án → Chấm lại"]
```

---

## 11. Cài đặt hệ thống, thông báo

![Cài đặt](images/19-cai-dat-he-thong.jpg)

**Hệ thống → Cài đặt**:

| Nhóm | Nội dung |
|---|---|
| Nhận diện & giao diện | Tên hệ thống, tên ngắn cạnh logo, tên đơn vị, cơ quan chủ quản, **chân trang / bản quyền** (dùng `{year}`, `{org}`, liên kết `[chữ](https://…)`), **màu chủ đạo**, tiêu đề & thông báo trang đăng nhập, **logo** và **favicon** (ảnh được thu nhỏ trên trình duyệt rồi lưu vào CSDL) |
| Mặc định thi cử | Giá trị mặc định **cho ca thi tạo mới** (ca đã tạo giữ cài đặt riêng, sửa trong từng ca): xem điểm, xem lại bài, khóa thiết bị, tự nhận lại cùng máy, toàn màn hình, số lần rời màn hình, in chìm, chống tải tệp đề, thời gian ân hạn. Riêng **độ trễ lưu tự động** và **nhịp kiểm tra kết nối** áp dụng ngay cho mọi ca |
| Xếp loại | Ngưỡng Giỏi / Khá / Trung bình / Yếu (thang 10, tự quy đổi) |
| Bảo mật | Số lần sai mật khẩu, thời gian tạm khóa, giới hạn sai theo IP trong cùng khoảng thời gian đó (phòng máy chung IP nên để cao), thời gian giữ phiên, độ dài mật khẩu tối thiểu, học sinh tự đổi mật khẩu |
| Bảo trì | Bật chế độ bảo trì (chỉ quản trị đăng nhập được) và nội dung thông báo |

**Quản lý → Thông báo**: đăng thông báo cho *mọi người / học sinh / giáo viên / một lớp*, ghim lên đầu, đặt thời gian hiển thị. Học sinh thấy ở trang chủ, giáo viên thấy ở trang tổng quan.

---

## 12. Sao lưu, phục hồi, bảo trì

![Sao lưu](images/20-sao-luu.jpg)

- **Sao lưu**: *Hệ thống → Sao lưu & phục hồi → Tải bản sao lưu (.tnbak)* – chứa **toàn bộ dữ liệu** (tài khoản, đề PDF, đáp án, bài làm, cài đặt, logo, tùy chọn kèm nhật ký). Nên tải sau mỗi đợt thi.
- Với SQLite: **Tải tệp .sqlite** (mở được bằng *DB Browser for SQLite*).
- **Phục hồi**: chọn tệp `.tnbak` → kiểm tra thông tin bản sao lưu → gõ `PHỤC HỒI` để xác nhận → theo dõi thanh tiến trình. Sau khi xong, mọi người phải đăng nhập lại.
- **Chuyển SQLite ↔ MySQL / đổi hosting**: sao lưu ở hệ thống cũ → cài mới (chọn loại CSDL mong muốn; nếu cài lại trên cùng hosting thì xóa `storage/config.php` trước để mở lại trình cài đặt) → phục hồi.

**Hệ thống → Thông tin hệ thống**: kiểm tra phiên bản PHP, tiện ích, giới hạn của hosting, dung lượng CSDL, số người trực tuyến; các nút **Dọn dẹp** (tải lên dở dang, tệp không dùng, phiên hết hạn, nhật ký cũ), **Tối ưu CSDL**, **Xóa dữ liệu mẫu**; lệnh / đường dẫn **cron** (tùy chọn).

![Thông tin hệ thống](images/25-he-thong.jpg)

**Hệ thống → Nhật ký hệ thống**: hoạt động của người dùng (lọc theo loại, người, ngày; xuất Excel), lỗi hệ thống (có mã tra cứu hiển thị cho người dùng khi gặp lỗi), lịch sử đăng nhập.

---

## 13. Câu hỏi thường gặp

<details>
<summary><b>Học sinh có tải được đề PDF về không?</b></summary>

Không có đường dẫn tải trực tiếp: dữ liệu PDF chỉ được gửi cho đúng phòng thi của bài làm, **được làm rối bằng khóa riêng của bài làm**, vẽ lên canvas (không có lớp chữ để bôi đen/sao chép), chặn in, lưu trang, chuột phải; trang đề có **in chìm họ tên + SBD**. Tuy nhiên không trang web nào chặn được việc chụp ảnh màn hình bằng điện thoại – hình mờ giúp truy ra nguồn nếu đề bị phát tán.
</details>

<details>
<summary><b>Đồng hồ máy học sinh sai giờ thì sao?</b></summary>

Không ảnh hưởng. Thời gian làm bài luôn tính theo **giờ máy chủ** (UTC+7); trình duyệt tự đồng bộ ở mỗi lần kết nối. Hạn nộp được máy chủ kiểm tra, học sinh không thể kéo dài.
</details>

<details>
<summary><b>Bao nhiêu học sinh có thể thi cùng lúc?</b></summary>

Phụ thuộc hosting. Mỗi học sinh gửi 1 yêu cầu nhỏ khi tô (gom 1,2 giây) và 1 nhịp kiểm tra mỗi 20 giây. SQLite ở chế độ WAL phù hợp vài trăm học sinh cùng lúc trên hosting thông thường; nhiều hơn nên dùng **MySQL** và tăng *nhịp kiểm tra kết nối* lên 30 giây trong Cài đặt.
</details>

<details>
<summary><b>Phòng máy dùng chung một địa chỉ IP, có vấn đề gì không?</b></summary>

Có 2 điểm cần chỉnh: tăng **giới hạn đăng nhập sai theo IP** trong *Cài đặt → Bảo mật* (vì cả phòng dùng chung IP); và nếu muốn khóa thiết bị thật chặt thì **tắt “Tự nhận lại cùng máy”** (vì các máy trong phòng cùng IP, cùng trình duyệt). Tùy chọn trong *Cài đặt → Mặc định thi cử* chỉ áp dụng cho ca tạo mới – với ca đã tạo, vào **Sửa ca thi** để tắt.
</details>

<details>
<summary><b>Hosting giới hạn số tệp (inode) – hệ thống có tạo nhiều tệp không?</b></summary>

Không. Đề PDF, logo, phiên đăng nhập, nhật ký, bản phục hồi tải lên… đều lưu **trong CSDL**. Thư mục `storage/` chỉ có `config.php`, `.htaccess`, `index.html` (và tệp SQLite cùng 2 tệp tạm `-wal` / `-shm` nếu chọn SQLite) – số tệp không tăng theo dữ liệu. Trang *Thông tin hệ thống* hiển thị số tệp trong `storage/`.
</details>

<details>
<summary><b>Quên mật khẩu quản trị?</b></summary>

Nhờ một tài khoản quản trị khác cấp lại. Nếu không còn ai: dùng *DB Browser for SQLite* / phpMyAdmin, cập nhật cột `password_hash` của tài khoản admin bằng chuỗi sinh từ lệnh `php -r "echo password_hash('MatKhauMoi@123', PASSWORD_BCRYPT);"`.
</details>

<details>
<summary><b>Làm sao cập nhật lên phiên bản mới?</b></summary>

Sao lưu `.tnbak` → chép đè mã nguồn mới (giữ nguyên thư mục `storage/`) → mở trang bất kỳ: hệ thống **tự nâng cấp cấu trúc CSDL**. Số phiên bản hiện ở chân trang và trang đăng nhập.
</details>

<details>
<summary><b>Muốn xem lỗi chi tiết khi phát triển?</b></summary>

Trong `storage/config.php` đổi `'debug' => true`. Khi chạy thật hãy để `false`: người dùng chỉ thấy **mã tra cứu lỗi**, chi tiết nằm ở *Nhật ký → Lỗi hệ thống*.
</details>

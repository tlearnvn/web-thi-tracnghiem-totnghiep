<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\FileStore;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Settings;
use App\Lib\Sessions;

/** Cài đặt hệ thống: nhận diện (tên, logo, chân trang, màu), mặc định thi cử, xếp loại, bảo mật, bảo trì. */
final class SettingsController extends Controller
{
    /** Khai báo các trường theo nhóm: [kiểu, nhãn, gợi ý, tuỳ chọn…] */
    public static function fields(): array
    {
        return [
            'brand' => [
                'site_name' => ['text', 'Tên hệ thống', 'Hiển thị ở tiêu đề trình duyệt và trang đăng nhập.', 'max' => 150, 'required' => true],
                'site_short_name' => ['text', 'Tên ngắn (cạnh logo)', 'Ví dụ: THI TRẮC NGHIỆM, PHÒNG THI ONLINE.', 'max' => 40, 'required' => true],
                'org_name' => ['text', 'Tên đơn vị', 'Ví dụ: Trường THPT Nguyễn Du.', 'max' => 150, 'required' => true],
                'org_parent' => ['text', 'Cơ quan chủ quản', 'Ví dụ: Sở Giáo dục và Đào tạo TP. Hồ Chí Minh.', 'max' => 150],
                'footer_text' => ['textarea', 'Chân trang / bản quyền', 'Dùng {year} = năm hiện tại, {org} = tên đơn vị, [chữ](https://…) để chèn liên kết.', 'max' => 500],
                'primary_color' => ['color', 'Màu chủ đạo', 'Màu nút bấm, liên kết, điểm nhấn của giao diện.'],
                'login_title' => ['text', 'Tiêu đề trang đăng nhập', '', 'max' => 150],
                'login_subtitle' => ['textarea', 'Mô tả trang đăng nhập', '', 'max' => 400],
                'login_notice' => ['textarea', 'Thông báo nổi bật ở trang đăng nhập', 'Để trống nếu không cần. Ví dụ: lịch thi, hướng dẫn lấy tài khoản.', 'max' => 600],
            ],
            'exam' => [
                'exam_show_score' => ['select', 'Học sinh được xem điểm', 'Mặc định cho ca thi mới (có thể đổi riêng từng ca).', 'options' => Sessions::SCORE_POLICIES],
                'exam_allow_review' => ['select', 'Cho xem lại bài làm & lời giải', '', 'options' => Sessions::REVIEW_POLICIES],
                'exam_device_lock' => ['bool', 'Khóa thiết bị', 'Mỗi bài thi chỉ làm trên một máy; đổi máy cần giám thị mở khóa.'],
                'exam_auto_reclaim' => ['bool', 'Tự nhận lại bài trên cùng máy', 'Cùng địa chỉ IP và trình duyệt thì tự nhận lại (khi lỡ xóa cookie, đổi tab ẩn danh…). Tắt nếu cả phòng máy dùng chung một IP công cộng và cần khóa chặt.'],
                'exam_require_fullscreen' => ['bool', 'Bắt buộc toàn màn hình', 'Thoát toàn màn hình bị tính là rời bài thi.'],
                'exam_max_violations' => ['int', 'Số lần rời màn hình tối đa', '0 = không giới hạn (chỉ ghi nhận).', 'min' => 0, 'max' => 100],
                'exam_violation_action' => ['select', 'Khi vượt quá số lần cho phép', '', 'options' => Sessions::VIOLATION_ACTIONS],
                'exam_watermark' => ['bool', 'In chìm họ tên & SBD lên đề', 'Hạn chế chụp màn hình phát tán đề.'],
                'exam_protect_pdf' => ['bool', 'Mã hóa dữ liệu đề khi tải', 'Tệp PDF được làm rối theo từng bài làm, không mở trực tiếp được.'],
                'exam_grace_seconds' => ['int', 'Thời gian ân hạn khi hết giờ (giây)', 'Chờ thêm để bài làm kịp gửi về khi mạng chậm.', 'min' => 15, 'max' => 1800],
                'exam_autosave_ms' => ['int', 'Độ trễ lưu tự động (mili giây)', 'Gom các lần tô liên tiếp để giảm tải máy chủ. Khuyến nghị 800–2000.', 'min' => 500, 'max' => 10000],
                'exam_heartbeat_seconds' => ['int', 'Nhịp kiểm tra kết nối (giây)', 'Chu kỳ cập nhật giờ, tin nhắn, trạng thái tạm dừng. Hosting yếu nên để 20–30.', 'min' => 8, 'max' => 120],
            ],
            'grade' => [
                'grade_excellent' => ['float', 'Giỏi: từ', 'Theo thang điểm 10 (tự quy đổi với thang khác).', 'min' => 0, 'max' => 10],
                'grade_good' => ['float', 'Khá: từ', '', 'min' => 0, 'max' => 10],
                'grade_average' => ['float', 'Trung bình: từ', '', 'min' => 0, 'max' => 10],
                'grade_weak' => ['float', 'Yếu: từ', 'Dưới mức này là Kém.', 'min' => 0, 'max' => 10],
            ],
            'security' => [
                'login_max_attempts' => ['int', 'Số lần nhập sai mật khẩu tối đa', 'Vượt quá sẽ tạm khóa đăng nhập tài khoản đó.', 'min' => 3, 'max' => 50],
                'login_lock_minutes' => ['int', 'Thời gian tạm khóa (phút)', '', 'min' => 1, 'max' => 120],
                'login_ip_max_attempts' => ['int', 'Số lần sai tối đa của một địa chỉ IP', 'Tính trong khoảng "thời gian tạm khóa" ở trên. Phòng máy dùng chung IP nên đặt cao (≥ 100).', 'min' => 10, 'max' => 5000],
                'session_lifetime_hours' => ['int', 'Phiên đăng nhập tồn tại (giờ)', 'Tính từ lần hoạt động cuối. Nên ≥ thời gian ca thi dài nhất.', 'min' => 1, 'max' => 72],
                'password_min_length' => ['int', 'Độ dài mật khẩu tối thiểu', '', 'min' => 4, 'max' => 32],
                'student_can_change_password' => ['bool', 'Học sinh được tự đổi mật khẩu', ''],
                'remember_login' => ['bool', 'Ghi nhớ đăng nhập sau khi đóng trình duyệt', 'Không nên bật ở phòng máy dùng chung.'],
            ],
            'maintenance' => [
                'maintenance' => ['bool', 'Bật chế độ bảo trì', 'Chỉ quản trị viên đăng nhập được. Học sinh đang thi sẽ không lưu được bài – chỉ bật khi không có ca thi.'],
                'maintenance_message' => ['textarea', 'Thông báo bảo trì', '', 'max' => 400],
            ],
        ];
    }

    public const GROUPS = [
        'brand' => ['Nhận diện & giao diện', 'palette'],
        'exam' => ['Mặc định thi cử', 'clipboard-list'],
        'grade' => ['Xếp loại', 'award'],
        'security' => ['Bảo mật', 'shield-check'],
        'maintenance' => ['Bảo trì', 'wrench'],
    ];

    public function index(): void
    {
        $this->authorize('settings.manage');
        $this->render('settings/index', [
            'title' => 'Cài đặt hệ thống',
            'fields' => self::fields(),
            'values' => Settings::all(),
            'logo' => (int) Settings::get('logo_file_id', 0) ? FileStore::info((int) Settings::get('logo_file_id', 0)) : null,
            'favicon' => (int) Settings::get('favicon_file_id', 0) ? FileStore::info((int) Settings::get('favicon_file_id', 0)) : null,
        ]);
    }

    public function save(): void
    {
        $this->authorize('settings.manage');
        $this->requirePost();
        $group = Request::str('group');
        $all = self::fields();
        if (!isset($all[$group])) {
            throw new HttpException(400, 'Nhóm cài đặt không hợp lệ.');
        }
        $values = [];
        $errors = [];
        foreach ($all[$group] as $key => $f) {
            $type = $f[0];
            $raw = Request::post($key, null);
            switch ($type) {
                case 'bool':
                    $values[$key] = Request::bool($key) ? 1 : 0;
                    break;
                case 'int':
                    $v = is_numeric($raw) ? (int) $raw : null;
                    if ($v === null) {
                        $errors[] = $f[1] . ': cần là số nguyên.';
                        continue 2;
                    }
                    $values[$key] = max($f['min'] ?? PHP_INT_MIN, min($f['max'] ?? PHP_INT_MAX, $v));
                    break;
                case 'float':
                    $v = Request::float($key);
                    if ($v === null) {
                        $errors[] = $f[1] . ': cần là số.';
                        continue 2;
                    }
                    $values[$key] = max($f['min'] ?? -INF, min($f['max'] ?? INF, round($v, 2)));
                    break;
                case 'select':
                    $v = (string) $raw;
                    $values[$key] = isset($f['options'][$v]) ? $v : array_key_first($f['options']);
                    break;
                case 'color':
                    $v = strtolower(trim((string) $raw));
                    if (!preg_match('/^#[0-9a-f]{6}$/', $v)) {
                        $errors[] = $f[1] . ': mã màu không hợp lệ (dạng #2563eb).';
                        continue 2;
                    }
                    $values[$key] = $v;
                    break;
                default:
                    $v = trim(str_replace("\r\n", "\n", (string) $raw));
                    if (!empty($f['required']) && $v === '') {
                        $errors[] = $f[1] . ' không được để trống.';
                        continue 2;
                    }
                    $values[$key] = mb_substr($v, 0, $f['max'] ?? 1000);
            }
        }
        if ($group === 'grade') {
            if (!($values['grade_excellent'] > $values['grade_good'] && $values['grade_good'] > $values['grade_average'] && $values['grade_average'] > $values['grade_weak'])) {
                $errors[] = 'Ngưỡng xếp loại phải giảm dần: Giỏi > Khá > Trung bình > Yếu.';
            }
        }
        if ($errors) {
            $this->flash('danger', implode(' ', $errors));
            \App\Core\Response::redirect(url('settings') . '#' . $group);
            return;
        }
        Settings::setMany($values);
        Logger::audit('settings.update', 'settings', null, ['group' => $group, 'keys' => array_keys($values)]);
        $this->flash('success', 'Đã lưu cài đặt "' . self::GROUPS[$group][0] . '".');
        \App\Core\Response::redirect(url('settings') . '#' . $group);
    }

    /**
     * Tải logo / biểu tượng trang: trình duyệt đã thu nhỏ & chuyển sang PNG, gửi dạng base64 (không tạo tệp tạm trên máy chủ).
     * Ảnh được lưu trong CSDL.
     */
    public function logo(): void
    {
        $this->authorize('settings.manage');
        $this->requirePost();
        $kind = Request::str('kind') === 'favicon' ? 'favicon' : 'logo';
        $data = (string) Request::post('data', '');
        if (Request::bool('remove')) {
            FileStore::delete((int) Settings::get($kind . '_file_id', 0));
            Settings::setMany([$kind . '_file_id' => 0, $kind . '_version' => (string) time()]);
            Logger::audit('settings.' . $kind . '_remove', 'settings');
            $this->ok(['message' => $kind === 'logo' ? 'Đã dùng lại logo mặc định.' : 'Đã bỏ biểu tượng riêng.', 'url' => $kind === 'logo' ? logo_url() : favicon_url()]);
            return;
        }
        if (!preg_match('#^data:image/(png|webp|jpeg);base64,([A-Za-z0-9+/=]+)$#', $data, $m)) {
            $this->fail('Dữ liệu ảnh không hợp lệ.');
            return;
        }
        $bin = base64_decode($m[2], true);
        if ($bin === false || strlen($bin) < 60 || strlen($bin) > 2 * 1024 * 1024) {
            $this->fail('Ảnh quá lớn hoặc hỏng (tối đa 2 MB).');
            return;
        }
        $info = @getimagesizefromstring($bin);
        $mimes = ['png' => 'image/png', 'webp' => 'image/webp', 'jpeg' => 'image/jpeg'];
        if (!$info || ($info['mime'] ?? '') !== $mimes[$m[1]] || $info[0] < 16 || $info[1] < 16 || $info[0] > 2048 || $info[1] > 2048) {
            $this->fail('Không nhận dạng được ảnh (cần PNG / JPG / WEBP, từ 16 đến 2048 px).');
            return;
        }
        $old = (int) Settings::get($kind . '_file_id', 0);
        $id = FileStore::putString($bin, $kind . '.' . ($m[1] === 'jpeg' ? 'jpg' : $m[1]), $mimes[$m[1]], $kind, (int) Auth::id(), ['w' => $info[0], 'h' => $info[1]]);
        Settings::setMany([$kind . '_file_id' => $id, $kind . '_version' => (string) time()]);
        if ($old && $old !== $id) {
            FileStore::delete($old);
        }
        Logger::audit('settings.' . $kind, 'file', $id, ['w' => $info[0], 'h' => $info[1]]);
        $this->ok(['message' => $kind === 'logo' ? 'Đã cập nhật logo đơn vị.' : 'Đã cập nhật biểu tượng trang.', 'url' => $kind === 'logo' ? logo_url() : favicon_url()]);
    }
}

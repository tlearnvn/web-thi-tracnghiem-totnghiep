<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Lib\Seb;
use App\Lib\Sessions;

/** Safe Exam Browser: tải tệp cấu hình của ca thi, xác minh qua JavaScript API, trang thoát SEB. */
final class SebController extends Controller
{
    /** SEB tải tệp cấu hình mà không kèm đăng nhập -> bảo vệ bằng mã ký trong liên kết. */
    protected array $public = ['config', 'quit'];

    private function session(int $sid): array
    {
        $s = $sid > 0 ? $this->db->one('SELECT * FROM {exam_sessions} WHERE id = ?', [$sid]) : null;
        if (!$s) {
            throw new HttpException(404, 'Không tìm thấy ca thi.');
        }
        return $s;
    }

    /** Tệp .seb của ca thi (mở bằng liên kết sebs://… hoặc tải về rồi bấm đúp). */
    public function config(): void
    {
        $sid = Request::int('sid');
        if (!hash_equals(Seb::token($sid), Request::str('k'))) {
            throw new HttpException(404, 'Liên kết cấu hình không hợp lệ.');
        }
        $s = $this->session($sid);
        $o = Sessions::options($s);
        if (!in_array($o['seb'], ['config', 'browser'], true)) {
            throw new HttpException(404, 'Ca thi này không dùng tệp cấu hình Safe Exam Browser của hệ thống.');
        }
        $xml = Seb::plist(Seb::settings($s, $o));
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        header('Content-Type: application/seb');
        header('Content-Disposition: attachment; filename="' . Seb::fileName($s) . '"');
        header('Content-Length: ' . strlen($xml));
        header('Cache-Control: private, no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');
        echo $xml;
    }

    /**
     * macOS / iOS: SEB không gắn được header vào yêu cầu -> trang phòng chờ đọc
     * SafeExamBrowser.security.configKey (mã băm của URL trang + Config Key) và gửi lên đây.
     */
    public function verify(): void
    {
        $this->requirePost();
        $u = $this->user();
        $s = $this->session(Request::int('sid'));
        if (!Sessions::isTarget($s, $u)) {
            throw new HttpException(404, 'Không tìm thấy ca thi dành cho em.');
        }
        $o = Sessions::options($s);
        if (!in_array($o['seb'], ['config', 'keys'], true)) {
            $this->ok(['verified' => true]);
            return;
        }
        if (Seb::version() === null) {
            $this->fail(Seb::reasonText('not_seb'), 403, ['code' => 'seb_required']);
            return;
        }
        if (!Seb::verifyJs($s, $o, Request::str('url'), Request::str('ck'), Request::str('bek'))) {
            $this->fail(Seb::reasonText('bad_key'), 403, ['code' => 'seb_bad_key']);
            return;
        }
        Seb::remember((int) $s['id']);
        $this->ok(['verified' => true]);
    }

    /** SEB tự thoát khi mở địa chỉ này (quitURL trong tệp cấu hình); trang chỉ hiện nếu SEB chưa thoát. */
    public function quit(): void
    {
        $this->render('student/seb-quit', ['title' => 'Thoát Safe Exam Browser'], 'bare');
    }
}

<?php

namespace App\Core;

/**
 * Nhật ký thao tác & lỗi – lưu trong CSDL (không tạo file log trên hosting).
 */
final class Logger
{
    private static int $errorCount = 0;

    public const ACTIONS = [
        'auth.login' => 'Đăng nhập',
        'auth.logout' => 'Đăng xuất',
        'auth.password' => 'Đổi mật khẩu',
        'user.create' => 'Tạo tài khoản',
        'user.update' => 'Cập nhật tài khoản',
        'user.delete' => 'Xóa tài khoản',
        'user.import' => 'Nhập danh sách từ Excel',
        'user.reset_password' => 'Cấp lại mật khẩu',
        'user.lock' => 'Khóa / mở khóa tài khoản',
        'role.update' => 'Cập nhật phân quyền',
        'class.create' => 'Tạo lớp',
        'class.update' => 'Cập nhật lớp',
        'class.delete' => 'Xóa lớp',
        'subject.update' => 'Cập nhật môn thi',
        'exam.create' => 'Tạo đề thi',
        'exam.update' => 'Cập nhật đề thi',
        'exam.delete' => 'Xóa đề thi',
        'exam.key_import' => 'Nhập đáp án',
        'exam.key_update' => 'Sửa đáp án',
        'exam.pdf_upload' => 'Tải lên file PDF đề',
        'variant.create' => 'Thêm mã đề',
        'variant.delete' => 'Xóa mã đề',
        'session.create' => 'Tạo ca thi',
        'session.update' => 'Cập nhật ca thi',
        'session.delete' => 'Xóa ca thi',
        'session.control' => 'Điều khiển ca thi',
        'attempt.start' => 'Bắt đầu làm bài',
        'attempt.submit' => 'Nộp bài',
        'attempt.control' => 'Can thiệp bài làm',
        'attempt.grade' => 'Chấm điểm',
        'attempt.rescore' => 'Chấm lại',
        'settings.update' => 'Cập nhật cấu hình',
        'backup.download' => 'Tải bản sao lưu',
        'backup.restore' => 'Phục hồi dữ liệu',
        'announcement.save' => 'Lưu thông báo',
        'announcement.create' => 'Tạo thông báo',
        'announcement.update' => 'Sửa thông báo',
        'announcement.delete' => 'Xóa thông báo',
        'session.rescore' => 'Chấm lại ca thi',
        'results.export' => 'Xuất Excel kết quả',
        'monitor.add_time' => 'Cộng / bớt giờ',
        'monitor.pause' => 'Tạm dừng bài làm',
        'monitor.resume' => 'Cho tiếp tục làm bài',
        'monitor.unlock_violation' => 'Mở khóa vi phạm',
        'monitor.unlock_device' => 'Mở khóa thiết bị',
        'monitor.force_submit' => 'Giám thị thu bài',
        'monitor.reopen' => 'Mở lại bài làm',
        'monitor.void' => 'Hủy bài cho thi lại',
        'monitor.delete' => 'Xóa bài làm',
        'monitor.message' => 'Nhắn tin học sinh',
        'settings.logo' => 'Đổi logo',
        'settings.favicon' => 'Đổi biểu tượng trang',
        'settings.logo_remove' => 'Bỏ logo riêng',
        'settings.favicon_remove' => 'Bỏ biểu tượng riêng',
        'backup.sqlite' => 'Tải tệp CSDL SQLite',
        'backup.restore_start' => 'Bắt đầu phục hồi dữ liệu',
        'system.cleanup' => 'Dọn dẹp hệ thống',
        'system.remove_demo' => 'Xóa dữ liệu mẫu',
        'system.optimize' => 'Tối ưu CSDL',
        'logs.clear_errors' => 'Xóa nhật ký lỗi',
    ];

    public static function label(string $action): string
    {
        return self::ACTIONS[$action] ?? $action;
    }

    public static function audit(string $action, ?string $targetType = null, ?int $targetId = null, $details = null): void
    {
        try {
            $uid = null;
            if (isset($_SESSION['_uid'])) {
                $uid = (int) $_SESSION['_uid'];
            }
            App::db()->insert('audit_logs', [
                'user_id' => $uid,
                'action' => $action,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'details' => $details === null ? null : (is_string($details) ? $details : json_enc($details)),
                'ip' => client_ip(),
                'user_agent' => user_agent(),
                'created_at' => time(),
            ]);
        } catch (\Throwable $e) {
            // không để lỗi ghi log làm hỏng thao tác chính
        }
    }

    public static function error(\Throwable $e, string $level = 'error'): string
    {
        $ref = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        if (self::$errorCount++ > 20) {
            return $ref;
        }
        try {
            App::db()->insert('error_logs', [
                'ref' => $ref,
                'level' => $level,
                'message' => get_class($e) . ': ' . $e->getMessage(),
                'file' => substr(str_replace(BASE_PATH, '', $e->getFile()), 0, 255),
                'line' => $e->getLine(),
                'url' => substr((string) ($_SERVER['REQUEST_URI'] ?? ''), 0, 255),
                'user_id' => isset($_SESSION['_uid']) ? (int) $_SESSION['_uid'] : null,
                'trace' => substr($e->getTraceAsString(), 0, 20000),
                'created_at' => time(),
            ]);
        } catch (\Throwable $e2) {
            // CSDL không ghi được: ghi tạm vào một file duy nhất (có giới hạn dung lượng)
            $f = STORAGE_PATH . '/error.log';
            if (!is_file($f) || filesize($f) < 2 * 1048576) {
                @file_put_contents($f, date('c') . ' [' . $ref . '] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . "\n", FILE_APPEND);
            }
        }
        return $ref;
    }

    public static function warning(string $message, string $file = '', int $line = 0): void
    {
        if (self::$errorCount++ > 20) {
            return;
        }
        try {
            App::db()->insert('error_logs', [
                'ref' => null,
                'level' => 'warning',
                'message' => $message,
                'file' => substr(str_replace(BASE_PATH, '', $file), 0, 255),
                'line' => $line,
                'url' => substr((string) ($_SERVER['REQUEST_URI'] ?? ''), 0, 255),
                'user_id' => isset($_SESSION['_uid']) ? (int) $_SESSION['_uid'] : null,
                'trace' => null,
                'created_at' => time(),
            ]);
        } catch (\Throwable $e) {
            // bỏ qua
        }
    }

    /** Dọn nhật ký cũ để CSDL không phình to. */
    public static function prune(): void
    {
        $db = App::db();
        $now = time();
        $db->run('DELETE FROM {audit_logs} WHERE created_at < ?', [$now - 365 * 86400]);
        $db->run('DELETE FROM {error_logs} WHERE created_at < ?', [$now - 60 * 86400]);
        $db->run('DELETE FROM {login_attempts} WHERE created_at < ?', [$now - 7 * 86400]);
    }
}

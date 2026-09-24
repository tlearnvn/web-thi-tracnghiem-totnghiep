<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Blob;
use App\Core\Controller;
use App\Core\FileStore;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Settings;
use App\Lib\Backup;

/** Sao lưu & phục hồi toàn bộ dữ liệu (kể cả đề thi PDF, logo… trong CSDL). */
final class BackupController extends Controller
{
    /** Bước phục hồi được xác thực bằng mã phiên phục hồi (tài khoản có thể thay đổi khi bảng người dùng được phục hồi). */
    protected array $public = ['restoreStep'];

    public function index(): void
    {
        $this->authorize('backup.manage');
        $db = $this->db;
        $counts = [];
        foreach (['users', 'classes', 'exams', 'exam_variants', 'exam_sessions', 'attempts', 'files'] as $t) {
            $counts[$t] = (int) $db->value('SELECT COUNT(*) FROM {' . $t . '}');
        }
        $this->render('backup/index', [
            'title' => 'Sao lưu & phục hồi',
            'info' => $db->info(),
            'counts' => $counts,
            'filesSize' => FileStore::totalSize(),
            'lastBackup' => (int) Settings::get('last_backup_at', 0),
            'lastRestore' => (int) Settings::get('last_restore_at', 0),
            'isSqlite' => $db->isSqlite(),
        ]);
    }

    /** Tải bản sao lưu .tnbak (ghi thẳng ra trình duyệt, không tạo tệp trên máy chủ). */
    public function download(): void
    {
        $this->authorize('backup.manage');
        @set_time_limit(0);
        @ignore_user_abort(false);
        Session::release();
        $withLogs = Request::bool('logs');
        $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower(\App\Lib\Text::unaccent((string) Settings::get('org_name', 'thi')))) ?: 'thi';
        $name = 'sao-luu-' . trim($slug, '-') . '-' . date('Ymd-His') . '.tnbak';
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        Response::downloadHeaders($name, 'application/octet-stream');
        header('X-Accel-Buffering: no');
        $out = fopen('php://output', 'wb');
        $stats = Backup::write($out, $withLogs);
        fclose($out);
        try {
            Settings::set('last_backup_at', time());
            Logger::audit('backup.download', 'system', null, ['rows' => $stats['rows'], 'bytes' => $stats['bytes'], 'logs' => $withLogs]);
        } catch (\Throwable $e) {
            // bỏ qua
        }
    }

    /** Tải nguyên tệp CSDL SQLite (ảnh chụp nhất quán bằng VACUUM INTO). */
    public function sqlite(): void
    {
        $this->authorize('backup.manage');
        if (!$this->db->isSqlite()) {
            throw new HttpException(404);
        }
        @set_time_limit(0);
        Session::release();
        $src = \App\Core\Database::resolveSqlitePath((string) \App\Core\App::config('db.path', ''));
        $tmp = dirname($src) . '/.snapshot-' . random_token(6) . '.sqlite';
        $path = $src;
        try {
            $this->db->pdo->exec('VACUUM INTO ' . $this->db->pdo->quote($tmp));
            $path = $tmp;
        } catch (\Throwable $e) {
            // SQLite cũ: chốt WAL rồi gửi tệp gốc
            try {
                $this->db->pdo->exec('PRAGMA wal_checkpoint(TRUNCATE)');
            } catch (\Throwable $e2) {
            }
        }
        register_shutdown_function(static function () use ($tmp) {
            if (is_file($tmp)) {
                @unlink($tmp);
            }
        });
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        Response::downloadHeaders('csdl-' . date('Ymd-His') . '.sqlite', 'application/vnd.sqlite3', (int) filesize($path));
        $fh = fopen($path, 'rb');
        while ($fh && !feof($fh)) {
            echo fread($fh, 1048576);
            @flush();
        }
        if ($fh) {
            fclose($fh);
        }
        Logger::audit('backup.sqlite', 'system');
    }

    // ------------------------------------------------------------------ Phục hồi: tải lên theo khúc

    public function uploadInit(): void
    {
        $this->authorize('backup.manage');
        $this->requirePost();
        $size = Request::int('size');
        if ($size < 16) {
            $this->fail('Tệp trống.');
            return;
        }
        $token = random_token(16);
        $this->db->run('DELETE FROM {restore_chunks} WHERE created_at < ?', [time() - 86400]);
        $old = $_SESSION['_restore']['token'] ?? null;
        if ($old) {
            $this->db->run('DELETE FROM {restore_chunks} WHERE token = ?', [$old]);
        }
        $_SESSION['_restore'] = ['token' => $token, 'size' => $size, 'name' => mb_substr(Request::str('name'), 0, 200), 'uid' => Auth::id(), 'go' => false];
        $this->ok(['token' => $token, 'chunk' => Backup::CHUNK]);
    }

    public function uploadChunk(): void
    {
        $this->authorize('backup.manage');
        $this->requirePost();
        $job = $_SESSION['_restore'] ?? null;
        Session::release();
        if (!$job || Request::str('token') !== $job['token']) {
            $this->fail('Phiên tải lên không hợp lệ, vui lòng chọn lại tệp.', 409);
            return;
        }
        $seq = Request::int('seq', -1);
        $data = (string) file_get_contents('php://input');
        $expected = min(Backup::CHUNK, $job['size'] - $seq * Backup::CHUNK);
        if ($seq < 0 || $expected <= 0 || strlen($data) !== $expected) {
            $this->fail('Khúc dữ liệu không hợp lệ (' . strlen($data) . '/' . $expected . ' byte).', 422);
            return;
        }
        $this->db->run('DELETE FROM {restore_chunks} WHERE token = ? AND seq = ?', [$job['token'], $seq]);
        $this->db->insert('restore_chunks', ['token' => $job['token'], 'seq' => $seq, 'data' => new Blob($data), 'created_at' => time()]);
        $this->ok(['seq' => $seq]);
    }

    public function uploadFinish(): void
    {
        $this->authorize('backup.manage');
        $this->requirePost();
        $job = $_SESSION['_restore'] ?? null;
        if (!$job || Request::str('token') !== $job['token']) {
            $this->fail('Phiên tải lên không hợp lệ.', 409);
            return;
        }
        $need = (int) ceil($job['size'] / Backup::CHUNK);
        $got = (int) $this->db->value('SELECT COUNT(*) FROM {restore_chunks} WHERE token = ?', [$job['token']]);
        if ($got !== $need) {
            $this->fail('Tệp tải lên chưa đủ (' . $got . '/' . $need . ' khúc). Vui lòng thử lại.');
            return;
        }
        try {
            $meta = Backup::inspect($job['token'], (int) $job['size']);
        } catch (\Throwable $e) {
            $this->db->run('DELETE FROM {restore_chunks} WHERE token = ?', [$job['token']]);
            unset($_SESSION['_restore']);
            $this->fail($e->getMessage());
            return;
        }
        $_SESSION['_restore']['meta'] = $meta;
        $this->ok(['meta' => $meta, 'created' => fmt_dt($meta['created_at'] ?? 0, 'H:i:s d/m/Y')]);
    }

    /** Xác nhận phục hồi (xóa dữ liệu hiện tại và thay bằng bản sao lưu). */
    public function restoreStart(): void
    {
        $this->authorize('backup.manage');
        $this->requirePost();
        $job = $_SESSION['_restore'] ?? null;
        if (!$job || empty($job['meta']) || Request::str('token') !== $job['token']) {
            $this->fail('Chưa có tệp sao lưu hợp lệ.', 409);
            return;
        }
        if (mb_strtoupper(trim(Request::str('confirm'))) !== 'PHỤC HỒI') {
            $this->fail('Gõ đúng chữ PHỤC HỒI để xác nhận.');
            return;
        }
        Logger::audit('backup.restore_start', 'system', null, ['file' => $job['name'], 'from' => $job['meta']['version'] ?? '']);
        $_SESSION['_restore'] += ['offset' => 0, 'rows' => 0, 'frames' => 0, 'table' => null, 'cols' => [], 'bin' => [], 'keep' => [], 'tables' => [], 'maint' => 0, 'cleared' => false, 'done' => false];
        $_SESSION['_restore']['go'] = true;
        @set_time_limit(0);
        $this->ok(['message' => 'Bắt đầu phục hồi…']);
    }

    /** Một bước phục hồi (gọi lặp lại từ trình duyệt đến khi xong). */
    public function restoreStep(): void
    {
        $this->requirePost();
        $job = $_SESSION['_restore'] ?? null;
        if (!$job || empty($job['go']) || !hash_equals((string) $job['token'], Request::str('token'))) {
            $this->fail('Không có tiến trình phục hồi nào đang chạy.', 409);
            return;
        }
        @set_time_limit(0);
        @ignore_user_abort(true);
        try {
            Backup::step($job, 8.0);
        } catch (\Throwable $e) {
            $_SESSION['_restore'] = $job;
            $_SESSION['_restore']['go'] = false;
            Logger::error($e);
            $this->fail('Phục hồi bị lỗi: ' . $e->getMessage() . ' Dữ liệu có thể chưa đầy đủ – hãy chạy phục hồi lại từ đầu.', 500);
            return;
        }
        $progress = $job['size'] > 0 ? min(1, $job['offset'] / $job['size']) : 1;
        if ($job['done']) {
            // Mọi phiên đăng nhập đã bị xóa: đăng xuất phiên hiện tại, yêu cầu đăng nhập lại bằng tài khoản trong bản sao lưu
            $_SESSION = [];
            Session::flash('success', 'Phục hồi dữ liệu thành công (' . number_format($job['rows'], 0, ',', '.') . ' bản ghi). Vui lòng đăng nhập lại.');
            $this->ok(['done' => true, 'progress' => 1, 'rows' => $job['rows'], 'redirect' => url('login')]);
            return;
        }
        $_SESSION['_restore'] = $job;
        $this->ok(['done' => false, 'progress' => $progress, 'rows' => $job['rows'], 'table' => $job['table']]);
    }

    public function cancel(): void
    {
        $this->authorize('backup.manage');
        $this->requirePost();
        $job = $_SESSION['_restore'] ?? null;
        if ($job && empty($job['go'])) {
            $this->db->run('DELETE FROM {restore_chunks} WHERE token = ?', [$job['token']]);
            unset($_SESSION['_restore']);
        }
        $this->ok(['message' => 'Đã hủy.']);
    }
}

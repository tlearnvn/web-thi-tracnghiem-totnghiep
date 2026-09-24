<?php

namespace App\Controllers;

use App\Core\App;
use App\Core\Controller;
use App\Core\DbSessionHandler;
use App\Core\FileStore;
use App\Core\Logger;
use App\Core\Schema;
use App\Core\Session;
use App\Core\Settings;
use App\Lib\Attempts;

/** Thông tin hệ thống, kiểm tra môi trường hosting, dọn dẹp, tối ưu CSDL, xóa dữ liệu mẫu. */
final class SystemController extends Controller
{
    public function index(): void
    {
        $this->authorize('settings.manage');
        $db = $this->db;
        $tables = [];
        foreach (array_keys(Schema::tables()) as $t) {
            try {
                $tables[$t] = (int) $db->value('SELECT COUNT(*) FROM {' . $t . '}');
            } catch (\Throwable $e) {
                $tables[$t] = null;
            }
        }
        $now = time();
        $storageFiles = 0;
        foreach ((array) @scandir(STORAGE_PATH) as $f) {
            if ($f !== '.' && $f !== '..') {
                $storageFiles++;
            }
        }
        $ini = static fn(string $k) => (string) ini_get($k);
        $exts = [];
        foreach (['pdo_sqlite' => 'SQLite', 'pdo_mysql' => 'MySQL', 'mbstring' => 'Chuỗi Unicode', 'zlib' => 'Nén dữ liệu', 'json' => 'JSON', 'openssl' => 'Mã hóa', 'intl' => 'Sắp xếp tiếng Việt (tuỳ chọn)', 'gd' => 'Xử lý ảnh (tuỳ chọn)', 'fileinfo' => 'Nhận dạng tệp (tuỳ chọn)', 'opcache' => 'Tăng tốc PHP (khuyến nghị)'] as $ext => $label) {
            $exts[$ext] = [$label, extension_loaded($ext) || ($ext === 'opcache' && function_exists('opcache_get_status'))];
        }
        $this->render('system/index', [
            'title' => 'Thông tin hệ thống',
            'db' => $db->info(),
            'tables' => $tables,
            'php' => [
                'version' => PHP_VERSION,
                'sapi' => PHP_SAPI,
                'os' => PHP_OS_FAMILY,
                'memory_limit' => $ini('memory_limit'),
                'max_execution_time' => $ini('max_execution_time'),
                'upload_max_filesize' => $ini('upload_max_filesize'),
                'post_max_size' => $ini('post_max_size'),
                'timezone' => date_default_timezone_get(),
                'session_save' => 'CSDL (' . ($db->isSqlite() ? 'SQLite' : 'MySQL') . ')',
            ],
            'exts' => $exts,
            'now' => $now,
            'online' => (int) $db->value('SELECT COUNT(DISTINCT user_id) FROM {web_sessions} WHERE user_id IS NOT NULL AND last_activity > ?', [$now - 300]),
            'doing' => (int) $db->value("SELECT COUNT(*) FROM {attempts} WHERE status = 'in_progress' AND last_seen_at > ?", [$now - 120]),
            'sessionsCount' => (int) $db->value('SELECT COUNT(*) FROM {web_sessions}'),
            'filesSize' => FileStore::totalSize(),
            'storageFiles' => $storageFiles,
            'storageWritable' => is_writable(STORAGE_PATH),
            'configPerm' => @fileperms(STORAGE_PATH . '/config.php'),
            'cronLast' => (int) Settings::get('cron_last_run', 0),
            'installedAt' => App::config('installed_at', ''),
            'demo' => Settings::get('demo_data', null),
            'baseUrl' => preg_replace('~index\.php$~', '', absolute_url('')),
        ]);
    }

    /** Dọn dẹp: tải lên dở dang, phiên hết hạn, nhật ký cũ, bài làm quá hạn chưa thu. */
    public function cleanup(): void
    {
        $this->authorize('settings.manage');
        $this->requirePost();
        $db = $this->db;
        $now = time();
        $stale = (int) $db->value("SELECT COUNT(*) FROM {files} WHERE status = 'uploading' AND updated_at < ?", [$now - 86400]);
        FileStore::purgeStale();
        // Tệp đề/lời giải không còn gắn với mã đề nào (mã đề đã xóa)
        $orphans = $db->column(
            "SELECT f.id FROM {files} f WHERE f.purpose IN ('exam_pdf','solution_pdf') AND f.status = 'ready' AND f.updated_at < ?
               AND NOT EXISTS (SELECT 1 FROM {exam_variants} v WHERE v.pdf_file_id = f.id OR v.solution_file_id = f.id)",
            [$now - 3600]
        );
        foreach ($orphans as $id) {
            FileStore::delete((int) $id);
        }
        $sessions = DbSessionHandler::cleanup($db, Session::lifetime());
        $db->run('DELETE FROM {restore_chunks} WHERE created_at < ?', [$now - 86400]);
        Logger::prune();
        $final = Attempts::finalizeExpired();
        Logger::audit('system.cleanup', 'system', null, ['stale' => $stale, 'orphans' => count($orphans), 'sessions' => $sessions, 'finalized' => $final]);
        $this->flash('success', 'Đã dọn dẹp: ' . $stale . ' lượt tải lên dở, ' . count($orphans) . ' tệp không dùng, ' . $sessions . ' phiên hết hạn, thu ' . $final . ' bài quá giờ.');
        $this->redirect('system');
    }

    /** Tối ưu CSDL: SQLite chạy VACUUM (thu gọn tệp), MySQL chạy OPTIMIZE TABLE. */
    public function optimize(): void
    {
        $this->authorize('settings.manage');
        $this->requirePost();
        @set_time_limit(0);
        $db = $this->db;
        $before = (int) ($db->info()['size'] ?? 0);
        $running = (int) $db->value("SELECT COUNT(*) FROM {attempts} WHERE status = 'in_progress' AND last_seen_at > ?", [time() - 300]);
        if ($running > 0) {
            $this->flash('warning', 'Đang có ' . $running . ' học sinh làm bài – hãy tối ưu CSDL khi không có ca thi.');
            $this->redirect('system');
            return;
        }
        try {
            if ($db->isSqlite()) {
                $db->pdo->exec('PRAGMA wal_checkpoint(TRUNCATE)');
                $db->pdo->exec('VACUUM');
                $db->pdo->exec('PRAGMA optimize');
            } else {
                foreach (array_keys(Schema::tables()) as $t) {
                    $db->pdo->query('OPTIMIZE TABLE ' . $db->table($t))->fetchAll();
                }
            }
        } catch (\Throwable $e) {
            $this->flash('danger', 'Không tối ưu được: ' . $e->getMessage());
            $this->redirect('system');
            return;
        }
        $after = (int) ($db->info()['size'] ?? 0);
        Logger::audit('system.optimize', 'system', null, ['before' => $before, 'after' => $after]);
        $this->flash('success', 'Đã tối ưu CSDL: ' . fmt_bytes($before) . ' → ' . fmt_bytes($after) . '.');
        $this->redirect('system');
    }

    /** Xóa dữ liệu mẫu tạo lúc cài đặt (giữ lại lớp/đề đã được dùng cho dữ liệu thật). */
    public function removeDemo(): void
    {
        $this->authorize('settings.manage');
        $this->requirePost();
        $demo = Settings::get('demo_data', null);
        if (!is_array($demo)) {
            $this->flash('info', 'Không còn dữ liệu mẫu để xóa.');
            $this->redirect('system');
            return;
        }
        $db = $this->db;
        $ids = static fn(string $k) => array_values(array_filter(array_map('intval', (array) ($demo[$k] ?? []))));
        $kept = [];
        $db->transaction(function () use ($db, $ids, &$kept) {
            $users = $ids('users');
            $sessions = $ids('sessions');
            // Bài làm trong ca thi mẫu và của tài khoản mẫu
            $attemptIds = [];
            if ($sessions) {
                $attemptIds = array_merge($attemptIds, $db->column('SELECT id FROM {attempts} WHERE session_id IN ' . $db->in($sessions), $sessions));
            }
            if ($users) {
                $attemptIds = array_merge($attemptIds, $db->column('SELECT id FROM {attempts} WHERE user_id IN ' . $db->in($users), $users));
            }
            foreach (array_chunk(array_unique(array_map('intval', $attemptIds)), 300) as $chunk) {
                $db->run('DELETE FROM {attempt_events} WHERE attempt_id IN ' . $db->in($chunk), $chunk);
                $db->run('DELETE FROM {attempts} WHERE id IN ' . $db->in($chunk), $chunk);
            }
            foreach ($sessions as $sid) {
                foreach (['session_targets', 'session_staff', 'session_messages'] as $t) {
                    $db->run('DELETE FROM {' . $t . '} WHERE session_id = ?', [$sid]);
                }
                $db->run('DELETE FROM {exam_sessions} WHERE id = ?', [$sid]);
            }
            foreach ($ids('exams') as $eid) {
                if ($db->value('SELECT 1 FROM {exam_sessions} WHERE exam_id = ?', [$eid])) {
                    $kept[] = 'đề #' . $eid . ' (đang dùng cho ca thi khác)';
                    continue;
                }
                foreach ($db->all('SELECT id, pdf_file_id, solution_file_id FROM {exam_variants} WHERE exam_id = ?', [$eid]) as $v) {
                    $db->run('DELETE FROM {exam_keys} WHERE variant_id = ?', [(int) $v['id']]);
                    FileStore::delete((int) $v['pdf_file_id']);
                    FileStore::delete((int) $v['solution_file_id']);
                }
                $db->run('DELETE FROM {exam_variants} WHERE exam_id = ?', [$eid]);
                $db->run('DELETE FROM {exams} WHERE id = ?', [$eid]);
            }
            foreach ($ids('announcements') as $aid) {
                $db->run('DELETE FROM {announcements} WHERE id = ?', [$aid]);
            }
            foreach ($users as $uid) {
                $db->run('DELETE FROM {class_teachers} WHERE user_id = ?', [$uid]);
                $db->run('DELETE FROM {session_staff} WHERE user_id = ?', [$uid]);
                $db->run('DELETE FROM {web_sessions} WHERE user_id = ?', [$uid]);
                $db->run('UPDATE {classes} SET homeroom_teacher_id = NULL WHERE homeroom_teacher_id = ?', [$uid]);
                $db->run('DELETE FROM {users} WHERE id = ?', [$uid]);
            }
            foreach ($ids('classes') as $cid) {
                if ($db->value('SELECT 1 FROM {users} WHERE class_id = ?', [$cid]) || $db->value('SELECT 1 FROM {session_targets} WHERE class_id = ?', [$cid])) {
                    $kept[] = 'lớp #' . $cid . ' (đã có học sinh / ca thi thật)';
                    continue;
                }
                $db->run('DELETE FROM {class_teachers} WHERE class_id = ?', [$cid]);
                $db->run('DELETE FROM {classes} WHERE id = ?', [$cid]);
            }
            Settings::set('demo_data', null);
        });
        Logger::audit('system.remove_demo', 'system', null, ['kept' => $kept]);
        $this->flash('success', 'Đã xóa dữ liệu mẫu.' . ($kept ? ' Giữ lại: ' . implode('; ', $kept) . '.' : ''));
        $this->redirect('system');
    }
}

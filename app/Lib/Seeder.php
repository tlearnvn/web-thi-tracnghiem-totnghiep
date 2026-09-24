<?php

namespace App\Lib;

use App\Core\App;
use App\Core\Auth;
use App\Core\Database;
use App\Core\FileStore;
use App\Core\Permissions;
use App\Core\Settings;

/**
 * Dữ liệu khởi tạo: vai trò, môn thi theo định dạng 2025, dữ liệu mẫu để dùng thử.
 */
final class Seeder
{
    public static function roles(Database $db): void
    {
        $i = 0;
        foreach (Permissions::defaultRoles() as $code => $r) {
            if ($db->value('SELECT 1 FROM {roles} WHERE code = ?', [$code])) {
                continue;
            }
            $db->insert('roles', [
                'code' => $code,
                'name' => $r['name'],
                'kind' => $r['kind'],
                'description' => $r['description'],
                'permissions' => json_enc($r['permissions']),
                'is_system' => 1,
                'sort_order' => $i++,
                'created_at' => time(),
                'updated_at' => time(),
            ]);
        }
    }

    public static function subjects(Database $db): void
    {
        $i = 0;
        foreach (ExamFormat::PRESETS as $code => $p) {
            $i++;
            if ($db->value('SELECT 1 FROM {subjects} WHERE code = ?', [$code])) {
                continue;
            }
            $db->insert('subjects', [
                'code' => $code,
                'name' => $p['name'],
                'short_name' => $p['short'],
                'duration' => $p['duration'],
                'structure' => json_enc(ExamFormat::structureFromPreset($p)),
                'scoring' => json_enc(ExamFormat::scoringFromPreset($p)),
                'color' => $p['color'],
                'sort_order' => $i,
                'is_active' => 1,
                'created_at' => time(),
                'updated_at' => time(),
            ]);
        }
    }

    public static function createUser(Database $db, array $u): int
    {
        $now = time();
        $name = Text::normalizeName($u['full_name']);
        return $db->insert('users', [
            'username' => Auth::normalizeUsername($u['username']),
            'password_hash' => Auth::hash($u['password']),
            'role' => $u['role'],
            'full_name' => $name,
            'code' => $u['code'] ?? null,
            'class_id' => $u['class_id'] ?? null,
            'gender' => $u['gender'] ?? null,
            'birthday' => $u['birthday'] ?? null,
            'email' => $u['email'] ?? null,
            'phone' => $u['phone'] ?? null,
            'subject_id' => $u['subject_id'] ?? null,
            'status' => 'active',
            'must_change_password' => (int) ($u['must_change_password'] ?? 0),
            'note' => $u['note'] ?? null,
            'search_text' => Text::searchText($name, $u['username'], $u['code'] ?? ''),
            'sort_key' => Text::sortKey($name),
            'created_by' => $u['created_by'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** Dữ liệu mẫu: 2 lớp, 1 giáo viên, 20 học sinh, 1 đề Toán minh họa có PDF, 1 ca thi đang mở. */
    public static function demo(Database $db, int $adminId): array
    {
        $now = time();
        $year = (string) Settings::get('default_school_year', Settings::guessSchoolYear());
        $subjectId = (int) $db->value('SELECT id FROM {subjects} WHERE code = ?', ['TOAN']);
        $engId = (int) $db->value('SELECT id FROM {subjects} WHERE code = ?', ['TIENGANH']);

        $teacherId = self::createUser($db, [
            'username' => 'gv.toan', 'password' => '123456', 'role' => 'teacher', 'full_name' => 'Nguyễn Thị Minh Hoa',
            'code' => 'GV001', 'email' => 'hoa.nguyen@example.edu.vn', 'subject_id' => $subjectId, 'gender' => 'Nữ', 'created_by' => $adminId,
        ]);
        self::createUser($db, [
            'username' => 'giamthi', 'password' => '123456', 'role' => 'proctor', 'full_name' => 'Trần Văn Bình',
            'code' => 'GV002', 'gender' => 'Nam', 'created_by' => $adminId,
        ]);

        $classes = [];
        foreach (['12A1', '12A2'] as $cn) {
            $classes[$cn] = $db->insert('classes', [
                'name' => $cn, 'grade' => 12, 'school_year' => $year,
                'homeroom_teacher_id' => $cn === '12A1' ? $teacherId : null,
                'description' => 'Lớp mẫu', 'status' => 'active', 'sort_key' => Text::collate($cn),
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $db->insert('class_teachers', ['class_id' => $classes[$cn], 'user_id' => $teacherId, 'subject_id' => $subjectId, 'created_at' => $now]);
        }

        $names = [
            'Nguyễn Hoàng An', 'Trần Minh Anh', 'Lê Ngọc Bảo', 'Phạm Gia Bình', 'Hoàng Thu Chi', 'Vũ Đức Dũng', 'Đặng Thùy Dương',
            'Bùi Quốc Đạt', 'Đỗ Hương Giang', 'Ngô Thanh Hà', 'Dương Minh Hiếu', 'Lý Khánh Huyền', 'Mai Đăng Khoa', 'Trịnh Bảo Lâm',
            'Phan Thảo Linh', 'Hồ Đức Mạnh', 'Tạ Phương Nam', 'Cao Yến Nhi', 'Lương Tấn Phát', 'Vương Như Quỳnh',
        ];
        $k = 0;
        foreach ($names as $i => $name) {
            $cn = $i < 10 ? '12A1' : '12A2';
            $k++;
            $code = sprintf('0100%04d', $k);
            self::createUser($db, [
                'username' => 'hs' . strtolower($cn) . sprintf('%02d', ($i % 10) + 1),
                'password' => '123456',
                'role' => 'student',
                'full_name' => $name,
                'code' => $code,
                'class_id' => $classes[$cn],
                'gender' => in_array($i, [1, 4, 6, 8, 9, 11, 14, 17, 19], true) ? 'Nữ' : 'Nam',
                'birthday' => sprintf('2008-%02d-%02d', ($i % 12) + 1, ($i * 3 % 27) + 1),
                'created_by' => $adminId,
            ]);
        }

        // ---- Đề Toán minh họa ----
        $preset = ExamFormat::PRESETS['TOAN'];
        $examId = $db->insert('exams', [
            'title' => 'Đề thi thử tốt nghiệp THPT – Môn Toán (định dạng 2025)',
            'subject_id' => $subjectId, 'grade' => 12,
            'description' => 'Đề mẫu gồm 12 câu trắc nghiệm, 4 câu đúng/sai, 6 câu trả lời ngắn. Có lời giải chi tiết.',
            'duration' => 90,
            'structure' => json_enc(ExamFormat::structureFromPreset($preset)),
            'scoring' => json_enc(ExamFormat::scoringFromPreset($preset)),
            'is_shared' => 1, 'status' => 'ready', 'created_by' => $teacherId,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $pdfPath = BASE_PATH . '/docs/samples/de-mau-toan-0101.pdf';
        $pdfId = is_file($pdfPath) ? FileStore::putUploaded($pdfPath, 'de-mau-toan-0101.pdf', 'application/pdf', 'exam_pdf', $teacherId) : null;
        $solPath = BASE_PATH . '/docs/samples/loi-giai-toan-0101.pdf';
        $solId = is_file($solPath) ? FileStore::putUploaded($solPath, 'loi-giai-toan-0101.pdf', 'application/pdf', 'solution_pdf', $teacherId) : null;
        foreach (array_filter([$pdfId, $solId]) as $fid) {
            $pages = \App\Controllers\FilesController::countPdfPages((int) $fid);
            if ($pages) {
                $db->update('files', ['meta' => json_enc(['pages' => $pages])], 'id = ?', [(int) $fid]);
            }
        }
        $variantId = $db->insert('exam_variants', [
            'exam_id' => $examId, 'code' => '0101', 'pdf_file_id' => $pdfId, 'solution_file_id' => $solId,
            'note' => 'Mã đề mẫu', 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $keyFile = BASE_PATH . '/docs/samples/dap-an-mau-toan.json';
        if (is_file($keyFile)) {
            $parsed = KeyImporter::parse((string) file_get_contents($keyFile), 'dap-an-mau-toan.json', ExamFormat::structureFromPreset($preset));
            foreach ($parsed['variants'] as $v) {
                if ($v['code'] === '0101') {
                    KeyImporter::save($db, $variantId, $v['questions']);
                }
            }
        }

        // ---- Đề Tiếng Anh (chỉ phần I – 40 câu) để minh họa mẫu phiếu 40 câu ----
        $engPreset = ExamFormat::PRESETS['TIENGANH'];
        $engExam = $db->insert('exams', [
            'title' => 'Kiểm tra định kỳ – Tiếng Anh 12 (40 câu)',
            'subject_id' => $engId, 'grade' => 12, 'description' => 'Minh họa phiếu 40 câu trắc nghiệm (chưa có file PDF).',
            'duration' => 50,
            'structure' => json_enc(ExamFormat::structureFromPreset($engPreset)),
            'scoring' => json_enc(ExamFormat::scoringFromPreset($engPreset)),
            'is_shared' => 0, 'status' => 'draft', 'created_by' => $teacherId, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $db->insert('exam_variants', ['exam_id' => $engExam, 'code' => '201', 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now]);

        // ---- Ca thi đang mở + ca luyện tập ----
        $sessionId = $db->insert('exam_sessions', [
            'exam_id' => $examId, 'name' => 'Thi thử tốt nghiệp lần 1 – Toán 12', 'mode' => 'exam',
            'start_at' => $now - 600, 'end_at' => $now + 7 * 86400, 'duration' => 90,
            'access_code' => null, 'variant_mode' => 'random', 'max_attempts' => 1, 'late_join' => 0,
            'opts' => json_enc(Sessions::defaultOptions('exam')), 'status' => 'active', 'room' => 'Phòng máy 1',
            'created_by' => $teacherId, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $practiceId = $db->insert('exam_sessions', [
            'exam_id' => $examId, 'name' => 'Luyện tập – Đề minh họa Toán 2025', 'mode' => 'practice',
            'start_at' => $now - 600, 'end_at' => null, 'duration' => 90,
            'access_code' => null, 'variant_mode' => 'random', 'max_attempts' => 0, 'late_join' => 0,
            'opts' => json_enc(Sessions::defaultOptions('practice')), 'status' => 'active', 'room' => null,
            'created_by' => $teacherId, 'created_at' => $now, 'updated_at' => $now,
        ]);
        foreach ([$sessionId, $practiceId] as $sid) {
            foreach ($classes as $cid) {
                $db->insert('session_targets', ['session_id' => $sid, 'class_id' => $cid, 'user_id' => null]);
            }
        }
        $proctorId = (int) $db->value('SELECT id FROM {users} WHERE username = ?', ['giamthi']);
        $db->insert('session_staff', ['session_id' => $sessionId, 'user_id' => $proctorId, 'role' => 'proctor']);

        $db->insert('announcements', [
            'title' => 'Lịch thi thử tốt nghiệp lần 1',
            'body' => "Các em học sinh khối 12 tham gia thi thử môn Toán trên hệ thống.\nHãy đăng nhập đúng tài khoản được cấp, kiểm tra kết nối mạng trước giờ thi.",
            'audience' => 'all', 'class_id' => null, 'is_pinned' => 1, 'starts_at' => null, 'ends_at' => null,
            'created_by' => $adminId, 'created_at' => $now, 'updated_at' => $now,
        ]);

        return [
            'teacher' => 'gv.toan / 123456',
            'proctor' => 'giamthi / 123456',
            'student' => 'hs12a101 … hs12a110, hs12a201 … hs12a210 / 123456',
        ];
    }
}

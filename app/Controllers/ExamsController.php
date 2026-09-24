<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\FileStore;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Scope;
use App\Lib\Attempts;
use App\Lib\ExamFormat;
use App\Lib\KeyImporter;
use App\Lib\Scoring;
use App\Lib\Text;

final class ExamsController extends Controller
{
    public const STATUSES = ['draft' => ['Nháp', 'default'], 'ready' => ['Sẵn sàng', 'success'], 'archived' => ['Lưu trữ', 'default']];

    /** Đọc cấu trúc đề & cách tính điểm từ biểu mẫu (dùng chung cho Môn thi). */
    public static function readFormatFromRequest(): array
    {
        $essay = [];
        $labels = Request::arr('essay_label');
        $points = Request::arr('essay_points');
        $hints = Request::arr('essay_hint');
        foreach ($labels as $i => $l) {
            $p = str_replace(',', '.', (string) ($points[$i] ?? '1'));
            $essay[] = ['label' => (string) $l, 'points' => is_numeric($p) ? (float) $p : 1.0, 'hint' => (string) ($hints[$i] ?? '')];
        }
        $structure = ExamFormat::normalizeStructure([
            'p1' => Request::int('p1'), 'p2' => Request::int('p2'), 'p3' => Request::int('p3'),
            'p3_len' => Request::int('p3_len', 4), 'essay' => $essay,
        ]);
        $scoring = Scoring::normalize([
            'scheme' => Request::str('scheme', 'moet2025'),
            'p1_point' => Request::str('p1_point', '0.25'),
            'p1_penalty' => Request::str('p1_penalty', '0'),
            'p2_mode' => Request::str('p2_mode', 'table'),
            'p2_table' => [0, Request::str('p2_t1', '0.1'), Request::str('p2_t2', '0.25'), Request::str('p2_t3', '0.5'), Request::str('p2_t4', '1')],
            'p2_item_point' => Request::str('p2_item_point', '0.25'),
            'p2_all_point' => Request::str('p2_all_point', '1'),
            'p3_point' => Request::str('p3_point', '0.25'),
            'p3_compare' => Request::str('p3_compare', 'numeric'),
            'max_score' => Request::str('max_score', '10'),
            'scale' => Request::bool('scale') ? 1 : 0,
            'rounding' => Request::str('rounding', '0.01'),
            'round_mode' => Request::str('round_mode', 'round'),
            'min_zero' => Request::bool('min_zero') ? 1 : 0,
        ]);
        return [$structure, $scoring];
    }

    public static function examFor(int $id, string $need = 'view'): array
    {
        $db = \App\Core\App::db();
        $e = $id > 0 ? $db->one('SELECT * FROM {exams} WHERE id = ?', [$id]) : null;
        if (!$e) {
            throw new HttpException(404, 'Không tìm thấy đề thi.');
        }
        $acc = Scope::examAccess($e);
        if ($acc === 'none' || ($need === 'manage' && $acc !== 'manage')) {
            throw new HttpException(403, $need === 'manage' ? 'Bạn chỉ được xem đề này (đề của giáo viên khác được chia sẻ).' : 'Bạn không có quyền với đề thi này.');
        }
        $e['_access'] = $acc;
        $e['_structure'] = ExamFormat::normalizeStructure($e['structure']);
        $e['_scoring'] = Scoring::normalize($e['scoring']);
        return $e;
    }

    public function index(): void
    {
        $this->authorize('exams.view', 'exams.manage', 'exams.manage_all');
        $where = [];
        $params = [];
        if (!Auth::can('exams.manage_all')) {
            $where[] = '(e.created_by = ? OR e.is_shared = 1)';
            $params[] = (int) Auth::id();
        }
        $own = Request::str('own');
        if ($own === 'mine') {
            $where[] = 'e.created_by = ?';
            $params[] = (int) Auth::id();
        } elseif ($own === 'shared') {
            $where[] = 'e.is_shared = 1';
        }
        if ($sid = Request::int('subject_id')) {
            $where[] = 'e.subject_id = ?';
            $params[] = $sid;
        }
        $status = Request::str('status');
        if (isset(self::STATUSES[$status])) {
            $where[] = 'e.status = ?';
            $params[] = $status;
        } elseif ($status !== 'all') {
            $where[] = "e.status <> 'archived'";
        }
        $q = Request::str('q');
        if ($q !== '') {
            $where[] = 'LOWER(e.title) LIKE ?';
            $params[] = '%' . mb_strtolower($q) . '%';
        }
        $rows = $this->db->all(
            'SELECT e.*, s.name AS subject_name, s.color AS subject_color, s.short_name AS subject_short, u.full_name AS owner_name,
                    (SELECT COUNT(*) FROM {exam_variants} v WHERE v.exam_id = e.id) AS variants,
                    (SELECT COUNT(*) FROM {exam_variants} v WHERE v.exam_id = e.id AND v.pdf_file_id IS NOT NULL) AS with_pdf,
                    (SELECT COUNT(*) FROM {exam_keys} k JOIN {exam_variants} v ON v.id = k.variant_id WHERE v.exam_id = e.id AND k.answer IS NOT NULL) AS keys_filled,
                    (SELECT COUNT(*) FROM {exam_sessions} x WHERE x.exam_id = e.id) AS sessions,
                    (SELECT COUNT(*) FROM {attempts} a WHERE a.exam_id = e.id) AS attempts
             FROM {exams} e LEFT JOIN {subjects} s ON s.id = e.subject_id LEFT JOIN {users} u ON u.id = e.created_by
             ' . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY e.updated_at DESC',
            $params
        );
        $this->render('exams/index', [
            'title' => 'Đề thi & đáp án',
            'crumbs' => ['Đề thi' => null],
            'rows' => $rows,
            'subjects' => $this->db->all('SELECT id, name FROM {subjects} ORDER BY sort_order'),
            'canCreate' => can('exams.manage', 'exams.manage_all'),
        ]);
    }

    private function subjectsJson(): array
    {
        $out = [];
        foreach ($this->db->all('SELECT * FROM {subjects} WHERE is_active = 1 ORDER BY sort_order, name') as $s) {
            $out[] = ['id' => (int) $s['id'], 'name' => $s['name'], 'duration' => (int) $s['duration'], 'structure' => ExamFormat::normalizeStructure($s['structure']), 'scoring' => Scoring::normalize($s['scoring']), 'color' => $s['color']];
        }
        return $out;
    }

    public function create(): void
    {
        $this->authorize('exams.manage', 'exams.manage_all');
        $subjects = $this->subjectsJson();
        $first = null;
        $sid = Request::int('subject_id');
        foreach ($subjects as $s) {
            if (($sid && $s['id'] === $sid) || (!$sid && !$first)) {
                $first = $s;
            }
        }
        $first = $first ?? ['id' => null, 'duration' => 50, 'structure' => ExamFormat::structureFromPreset(['p1' => 40]), 'scoring' => Scoring::defaults()];
        $this->render('exams/form', [
            'title' => 'Tạo đề thi',
            'crumbs' => ['Đề thi' => url('exams'), 'Tạo đề' => null],
            'e' => ['id' => 0, 'title' => '', 'subject_id' => $first['id'], 'grade' => 12, 'description' => '', 'duration' => $first['duration'], 'is_shared' => 0, 'status' => 'draft'],
            'structure' => $first['structure'],
            'scoring' => $first['scoring'],
            'subjects' => $subjects,
            'locked' => false,
            'codes' => '0101',
        ]);
    }

    public function edit(): void
    {
        $this->authorize('exams.manage', 'exams.manage_all');
        $e = self::examFor(Request::int('id'), 'manage');
        $this->render('exams/form', [
            'title' => 'Sửa đề thi',
            'crumbs' => ['Đề thi' => url('exams'), $e['title'] => url('exams/view', ['id' => $e['id']]), 'Sửa' => null],
            'e' => $e,
            'structure' => $e['_structure'],
            'scoring' => $e['_scoring'],
            'subjects' => $this->subjectsJson(),
            'locked' => (bool) $this->db->value('SELECT 1 FROM {attempts} WHERE exam_id = ?', [(int) $e['id']]),
            'codes' => null,
        ]);
    }

    public function save(): void
    {
        $this->authorize('exams.manage', 'exams.manage_all');
        $this->requirePost();
        $id = Request::int('id');
        $cur = $id ? self::examFor($id, 'manage') : null;
        $title = trim(Request::str('title'));
        if ($title === '') {
            $this->flash('danger', 'Vui lòng nhập tên đề thi.');
            $this->back('exams');
            return;
        }
        [$structure, $scoring] = self::readFormatFromRequest();
        if (ExamFormat::totalQuestions($structure) === 0) {
            $this->flash('danger', 'Đề thi cần có ít nhất một câu hỏi.');
            $this->back('exams');
            return;
        }
        $data = [
            'title' => mb_substr($title, 0, 255),
            'subject_id' => Request::int('subject_id') ?: null,
            'grade' => Request::int('grade') ?: null,
            'description' => Request::str('description') ?: null,
            'duration' => max(0, min(600, Request::int('duration', 50))),
            'structure' => json_enc($structure),
            'scoring' => json_enc($scoring),
            'is_shared' => Request::bool('is_shared') ? 1 : 0,
            'status' => isset(self::STATUSES[Request::str('status')]) ? Request::str('status') : 'draft',
            'updated_at' => time(),
        ];
        if ($cur) {
            $this->db->update('exams', $data, 'id = ?', [(int) $cur['id']]);
            $id = (int) $cur['id'];
            Attempts::forgetExam($id);
            if (Request::bool('rescore')) {
                $n = Attempts::rescore(array_map('intval', $this->db->column("SELECT id FROM {attempts} WHERE exam_id = ? AND status <> 'in_progress'", [$id])));
                $this->flash('info', 'Đã chấm lại ' . $n . ' bài làm theo cách tính điểm mới.');
            }
        } else {
            $data['created_by'] = Auth::id();
            $data['created_at'] = time();
            $id = $this->db->insert('exams', $data);
            $codes = array_filter(array_map(static fn($c) => KeyImporter::cleanCode($c), preg_split('/[\s,;]+/', Request::str('codes', '0101'))));
            $i = 0;
            foreach (array_unique($codes ?: ['0101']) as $code) {
                $this->db->insert('exam_variants', ['exam_id' => $id, 'code' => $code, 'sort_order' => ++$i, 'created_at' => time(), 'updated_at' => time()]);
            }
        }
        Logger::audit($cur ? 'exam.update' : 'exam.create', 'exam', $id, ['title' => $title]);
        $this->flash('success', $cur ? 'Đã lưu đề thi.' : 'Đã tạo đề thi. Tiếp theo: tải tệp PDF và nhập đáp án cho từng mã đề.');
        $this->redirect('exams/view', ['id' => $id]);
    }

    public function view(): void
    {
        $this->authorize('exams.view', 'exams.manage', 'exams.manage_all');
        $e = self::examFor(Request::int('id'));
        $variants = $this->db->all(
            'SELECT v.*, f.name AS pdf_name, f.size AS pdf_size, f.meta AS pdf_meta, sf.name AS sol_name, sf.size AS sol_size,
                    (SELECT COUNT(*) FROM {exam_keys} k WHERE k.variant_id = v.id AND k.answer IS NOT NULL AND k.part < 4) AS answered,
                    (SELECT COUNT(*) FROM {exam_keys} k WHERE k.variant_id = v.id AND k.explanation IS NOT NULL) AS explained,
                    (SELECT COUNT(*) FROM {attempts} a WHERE a.variant_id = v.id) AS attempts
             FROM {exam_variants} v LEFT JOIN {files} f ON f.id = v.pdf_file_id LEFT JOIN {files} sf ON sf.id = v.solution_file_id
             WHERE v.exam_id = ? ORDER BY v.sort_order, v.code',
            [(int) $e['id']]
        );
        $sessions = $this->db->all(
            "SELECT s.*, (SELECT COUNT(*) FROM {attempts} a WHERE a.session_id = s.id AND a.status <> 'in_progress') AS done FROM {exam_sessions} s WHERE s.exam_id = ? ORDER BY s.created_at DESC",
            [(int) $e['id']]
        );
        $this->render('exams/view', [
            'title' => $e['title'],
            'crumbs' => ['Đề thi' => url('exams'), $e['title'] => null],
            'e' => $e,
            'subject' => $e['subject_id'] ? $this->db->one('SELECT * FROM {subjects} WHERE id = ?', [(int) $e['subject_id']]) : null,
            'owner' => $e['created_by'] ? $this->db->one('SELECT full_name FROM {users} WHERE id = ?', [(int) $e['created_by']]) : null,
            'variants' => $variants,
            'sessions' => $sessions,
            'expected' => $e['_structure']['p1'] + $e['_structure']['p2'] + $e['_structure']['p3'],
            'canManage' => $e['_access'] === 'manage',
        ]);
    }

    public function delete(): void
    {
        $this->authorize('exams.manage', 'exams.manage_all');
        $this->requirePost();
        $e = self::examFor(Request::int('id'), 'manage');
        if ((int) $this->db->value('SELECT COUNT(*) FROM {exam_sessions} WHERE exam_id = ?', [(int) $e['id']]) > 0) {
            $this->flash('danger', 'Đề đã được dùng cho ca thi. Hãy xóa các ca thi trước, hoặc chuyển đề sang trạng thái "Lưu trữ".');
            $this->back('exams');
            return;
        }
        $this->db->transaction(function () use ($e) {
            foreach ($this->db->all('SELECT * FROM {exam_variants} WHERE exam_id = ?', [(int) $e['id']]) as $v) {
                FileStore::delete($v['pdf_file_id'] ? (int) $v['pdf_file_id'] : null);
                FileStore::delete($v['solution_file_id'] ? (int) $v['solution_file_id'] : null);
                $this->db->run('DELETE FROM {exam_keys} WHERE variant_id = ?', [(int) $v['id']]);
            }
            $this->db->run('DELETE FROM {exam_variants} WHERE exam_id = ?', [(int) $e['id']]);
            $this->db->run('DELETE FROM {exams} WHERE id = ?', [(int) $e['id']]);
        });
        Logger::audit('exam.delete', 'exam', (int) $e['id'], ['title' => $e['title']]);
        $this->flash('success', 'Đã xóa đề thi "' . $e['title'] . '".');
        $this->redirect('exams');
    }

    /** Nhân bản đề (kèm mã đề, tệp PDF, đáp án). */
    public function duplicate(): void
    {
        $this->authorize('exams.manage', 'exams.manage_all');
        $this->requirePost();
        $e = self::examFor(Request::int('id'));
        $newId = $this->db->transaction(function () use ($e) {
            $now = time();
            $id = $this->db->insert('exams', [
                'title' => mb_substr($e['title'] . ' (bản sao)', 0, 255), 'subject_id' => $e['subject_id'], 'grade' => $e['grade'],
                'description' => $e['description'], 'duration' => $e['duration'], 'structure' => $e['structure'], 'scoring' => $e['scoring'],
                'is_shared' => 0, 'status' => 'draft', 'created_by' => Auth::id(), 'created_at' => $now, 'updated_at' => $now,
            ]);
            foreach ($this->db->all('SELECT * FROM {exam_variants} WHERE exam_id = ?', [(int) $e['id']]) as $v) {
                $pdf = $v['pdf_file_id'] ? $this->copyFile((int) $v['pdf_file_id']) : null;
                $sol = $v['solution_file_id'] ? $this->copyFile((int) $v['solution_file_id']) : null;
                $vid = $this->db->insert('exam_variants', ['exam_id' => $id, 'code' => $v['code'], 'pdf_file_id' => $pdf, 'solution_file_id' => $sol, 'note' => $v['note'], 'sort_order' => $v['sort_order'], 'created_at' => $now, 'updated_at' => $now]);
                foreach ($this->db->all('SELECT * FROM {exam_keys} WHERE variant_id = ?', [(int) $v['id']]) as $k) {
                    unset($k['id']);
                    $k['variant_id'] = $vid;
                    $this->db->insert('exam_keys', $k);
                }
            }
            return $id;
        });
        Logger::audit('exam.create', 'exam', $newId, ['copy_of' => (int) $e['id']]);
        $this->flash('success', 'Đã nhân bản đề thi.');
        $this->redirect('exams/view', ['id' => $newId]);
    }

    private function copyFile(int $fileId): ?int
    {
        $f = FileStore::info($fileId);
        if (!$f) {
            return null;
        }
        return FileStore::putString(FileStore::contents($fileId), $f['name'], $f['mime'], $f['purpose'], (int) Auth::id(), json_dec($f['meta'], []));
    }

    // ------------------------------------------------------------ Đáp án: nhập từ Excel / JSON

    public function keyTemplate(): void
    {
        $e = self::examFor(Request::int('id'));
        $codes = $this->db->column('SELECT code FROM {exam_variants} WHERE exam_id = ? ORDER BY sort_order, code', [(int) $e['id']]);
        if (Request::str('format') === 'json') {
            $json = KeyImporter::sampleJson($e['_structure'], $codes);
            Response::downloadHeaders('mau-dap-an-' . Text::slug($e['title']) . '.json', 'application/json', strlen($json));
            echo $json;
            return;
        }
        $data = KeyImporter::templateXlsx($e['_structure'], $codes, $e['title']);
        Response::downloadHeaders('mau-dap-an-' . Text::slug($e['title']) . '.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', strlen($data));
        echo $data;
    }

    /** Tải đáp án hiện có ra Excel (để chỉnh sửa rồi nhập lại). */
    public function keyExport(): void
    {
        $e = self::examFor(Request::int('id'));
        $x = new \App\Lib\XlsxWriter();
        $h = $x->style(['bold' => true, 'fill' => '1D4ED8', 'color' => 'FFFFFF', 'border' => true, 'align' => 'center', 'wrap' => true]);
        $t = $x->style(['border' => true]);
        $w = $x->style(['border' => true, 'wrap' => true, 'valign' => 'top']);
        $s = $x->sheet('Đáp án');
        $s->widths([10, 8, 7, 12, 8, 12, 24, 9, 80]);
        $s->row(['Mã đề', 'Phần', 'Câu', 'Đáp án', 'Điểm', 'Mức độ', 'Chủ đề', 'Câu gốc', 'Lời giải'], $h, [], 28);
        $roman = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV'];
        foreach ($this->db->all('SELECT v.code, k.* FROM {exam_keys} k JOIN {exam_variants} v ON v.id = k.variant_id WHERE v.exam_id = ? ORDER BY v.sort_order, v.code, k.part, k.num', [(int) $e['id']]) as $k) {
            $ans = (string) $k['answer'];
            if ((int) $k['part'] === 2) {
                $ans = strtr($ans, ['D' => 'Đ']);
            }
            $s->row([(string) $k['code'], $roman[(int) $k['part']] ?? '', (int) $k['num'], (int) $k['is_void'] ? '*' : $ans, $k['points'] !== null ? (float) $k['points'] : '', (string) $k['level'], (string) $k['topic'], (string) $k['origin'], (string) $k['explanation']], $t, [8 => $w]);
        }
        $s->freeze('A2');
        $x->download('dap-an-' . Text::slug($e['title']) . '.xlsx');
    }

    public function importKey(): void
    {
        $this->requirePost();
        @set_time_limit(300);
        $e = self::examFor(Request::int('id'), 'manage');
        $f = Request::file('file');
        if (!$f || ($err = Request::uploadError($f))) {
            $this->fail($f ? (string) $err : 'Vui lòng chọn tệp đáp án (.xlsx, .csv hoặc .json).');
            return;
        }
        $variants = $this->db->all('SELECT * FROM {exam_variants} WHERE exam_id = ? ORDER BY sort_order, code', [(int) $e['id']]);
        $single = Request::int('variant_id');
        $defaultCode = null;
        foreach ($variants as $v) {
            if ((int) $v['id'] === $single) {
                $defaultCode = $v['code'];
            }
        }
        if (!$defaultCode && count($variants) === 1) {
            $defaultCode = $variants[0]['code'];
        }
        $parsed = KeyImporter::parse((string) file_get_contents($f['tmp_name']), (string) $f['name'], $e['_structure'], $defaultCode, Request::int('sheet'));
        $replace = Request::str('mode', 'replace') !== 'merge';
        $createMissing = Request::bool('create_missing');
        $commit = Request::bool('commit') && !$parsed['errors'];
        $plan = [];
        foreach ($parsed['variants'] as $pv) {
            $match = null;
            foreach ($variants as $v) {
                if ($v['code'] === $pv['code'] || (ctype_digit($v['code']) && ctype_digit($pv['code']) && (int) $v['code'] === (int) $pv['code'])) {
                    $match = $v;
                    break;
                }
            }
            if ($single && $defaultCode && count($parsed['variants']) === 1 && $pv['code'] === 'CHUNG') {
                foreach ($variants as $v) {
                    if ((int) $v['id'] === $single) {
                        $match = $v;
                    }
                }
            }
            $plan[] = [
                'code' => $pv['code'],
                'variant' => $match ? $match['code'] : null,
                'action' => $match ? 'update' : ($createMissing ? 'create' : 'skip'),
                'answered' => $pv['answered'],
                'explained' => $pv['explained'],
                'questions' => count($pv['questions']),
                '_q' => $pv['questions'],
                '_v' => $match,
            ];
        }
        $rescored = 0;
        if ($commit) {
            $affected = [];
            $this->db->transaction(function () use (&$plan, $e, $replace, &$affected) {
                $order = (int) $this->db->value('SELECT COALESCE(MAX(sort_order), 0) FROM {exam_variants} WHERE exam_id = ?', [(int) $e['id']]);
                foreach ($plan as &$p) {
                    if ($p['action'] === 'skip') {
                        continue;
                    }
                    $vid = $p['_v'] ? (int) $p['_v']['id'] : $this->db->insert('exam_variants', ['exam_id' => (int) $e['id'], 'code' => $p['code'], 'sort_order' => ++$order, 'created_at' => time(), 'updated_at' => time()]);
                    KeyImporter::save($this->db, $vid, $p['_q'], $replace);
                    Attempts::forgetKeys($vid);
                    $affected[] = $vid;
                }
                unset($p);
                $this->db->update('exams', ['updated_at' => time()], 'id = ?', [(int) $e['id']]);
            });
            if ($affected) {
                $ids = array_map('intval', $this->db->column("SELECT id FROM {attempts} WHERE status <> 'in_progress' AND variant_id IN " . $this->db->in($affected), $affected));
                $rescored = $ids ? Attempts::rescore($ids) : 0;
            }
            Logger::audit('exam.key_import', 'exam', (int) $e['id'], ['variants' => count($affected), 'rescored' => $rescored]);
        }
        foreach ($plan as &$p) {
            unset($p['_q'], $p['_v']);
        }
        unset($p);
        $this->ok([
            'committed' => $commit,
            'format' => $parsed['format'],
            'sheets' => $parsed['sheets'],
            'plan' => $plan,
            'errors' => $parsed['errors'],
            'warnings' => $parsed['warnings'],
            'expected' => $e['_structure']['p1'] + $e['_structure']['p2'] + $e['_structure']['p3'],
            'rescored' => $rescored,
        ]);
    }
}

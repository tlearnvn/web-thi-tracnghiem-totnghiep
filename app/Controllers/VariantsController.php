<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\FileStore;
use App\Core\Logger;
use App\Core\Request;
use App\Lib\Attempts;
use App\Lib\KeyFormat;
use App\Lib\KeyImporter;

/** Mã đề: thêm / xóa, trang xem đề + nhập đáp án trực tiếp trên phiếu. */
final class VariantsController extends Controller
{
    public function create(): void
    {
        $this->requirePost();
        $e = ExamsController::examFor(Request::int('exam_id'), 'manage');
        $code = KeyImporter::cleanCode(Request::str('code'));
        if ($code === '') {
            $this->fail('Mã đề chỉ gồm chữ, số, dấu chấm hoặc gạch.');
            return;
        }
        if ($this->db->value('SELECT 1 FROM {exam_variants} WHERE exam_id = ? AND code = ?', [(int) $e['id'], $code])) {
            $this->fail('Mã đề ' . $code . ' đã có.');
            return;
        }
        $order = (int) $this->db->value('SELECT COALESCE(MAX(sort_order), 0) FROM {exam_variants} WHERE exam_id = ?', [(int) $e['id']]);
        $id = $this->db->insert('exam_variants', ['exam_id' => (int) $e['id'], 'code' => $code, 'sort_order' => $order + 1, 'created_at' => time(), 'updated_at' => time()]);
        Logger::audit('variant.create', 'variant', $id, ['exam' => (int) $e['id'], 'code' => $code]);
        $this->ok(['id' => $id]);
    }

    public function delete(): void
    {
        $this->requirePost();
        $v = $this->findOr404('exam_variants', Request::int('id'));
        $e = ExamsController::examFor((int) $v['exam_id'], 'manage');
        if ((int) $this->db->value('SELECT COUNT(*) FROM {attempts} WHERE variant_id = ?', [(int) $v['id']]) > 0) {
            $this->flash('danger', 'Mã đề ' . $v['code'] . ' đã có bài làm, không thể xóa.');
            $this->redirect('exams/view', ['id' => $e['id']]);
            return;
        }
        $this->db->transaction(function () use ($v) {
            FileStore::delete($v['pdf_file_id'] ? (int) $v['pdf_file_id'] : null);
            FileStore::delete($v['solution_file_id'] ? (int) $v['solution_file_id'] : null);
            $this->db->run('DELETE FROM {exam_keys} WHERE variant_id = ?', [(int) $v['id']]);
            $this->db->run('DELETE FROM {exam_variants} WHERE id = ?', [(int) $v['id']]);
        });
        Logger::audit('variant.delete', 'variant', (int) $v['id'], ['code' => $v['code']]);
        $this->flash('success', 'Đã xóa mã đề ' . $v['code'] . '.');
        $this->redirect('exams/view', ['id' => $e['id']]);
    }

    public function removeFile(): void
    {
        $this->requirePost();
        $v = $this->findOr404('exam_variants', Request::int('id'));
        $e = ExamsController::examFor((int) $v['exam_id'], 'manage');
        $col = Request::str('kind') === 'solution' ? 'solution_file_id' : 'pdf_file_id';
        FileStore::delete($v[$col] ? (int) $v[$col] : null);
        $this->db->update('exam_variants', [$col => null, 'updated_at' => time()], 'id = ?', [(int) $v['id']]);
        $this->flash('success', 'Đã gỡ tệp.');
        $this->redirect('exams/view', ['id' => $e['id']]);
    }

    /** Trang xem đề PDF + nhập / sửa đáp án trên phiếu. */
    public function key(): void
    {
        $this->requireStaff();
        $v = $this->findOr404('exam_variants', Request::int('id'));
        $e = ExamsController::examFor((int) $v['exam_id']);
        $answers = ['p1' => new \stdClass(), 'p2' => new \stdClass(), 'p3' => new \stdClass(), 'e' => new \stdClass()];
        $details = [];
        foreach (Attempts::keys((int) $v['id'], true) as $qid => $k) {
            [$p, $n] = explode('.', $qid);
            if ($k['answer'] !== null && $p !== 'e') {
                $answers[$p]->{$n} = $k['answer'];
            }
            $details[$qid] = [
                'points' => $k['points'] !== null ? (float) $k['points'] : null,
                'level' => $k['level'], 'topic' => $k['topic'], 'origin' => $k['origin'],
                'explanation' => $k['explanation'], 'is_void' => (int) $k['is_void'], 'answer' => $k['answer'],
            ];
        }
        $subject = $e['subject_id'] ? $this->db->one('SELECT name FROM {subjects} WHERE id = ?', [(int) $e['subject_id']]) : null;
        $this->render('variants/key', [
            'title' => 'Mã đề ' . $v['code'] . ' – ' . $e['title'],
            'crumbs' => ['Đề thi' => url('exams'), str_limit($e['title'], 40) => url('exams/view', ['id' => $e['id']]), 'Mã đề ' . $v['code'] => null],
            'wide' => true,
            'v' => $v,
            'e' => $e,
            'subject' => $subject['name'] ?? '',
            'answers' => $answers,
            'details' => $details,
            'others' => $this->db->all('SELECT id, code, pdf_file_id FROM {exam_variants} WHERE exam_id = ? ORDER BY sort_order, code', [(int) $e['id']]),
            'canManage' => $e['_access'] === 'manage',
        ]);
    }

    /** Lưu đáp án từ trang nhập trực tiếp (JSON). */
    public function saveKey(): void
    {
        $this->requirePost();
        $v = $this->findOr404('exam_variants', Request::int('variant_id'));
        $e = ExamsController::examFor((int) $v['exam_id'], 'manage');
        $st = $e['_structure'];
        $limits = [1 => $st['p1'], 2 => $st['p2'], 3 => $st['p3'], 4 => count($st['essay'])];
        $rows = [];
        $errors = [];
        foreach (Request::arr('keys') as $k) {
            $part = (int) ($k['part'] ?? 0);
            $num = (int) ($k['num'] ?? 0);
            if (!isset($limits[$part]) || $num < 1 || $num > $limits[$part]) {
                continue;
            }
            $raw = trim((string) ($k['answer'] ?? ''));
            $ans = null;
            if ($raw !== '' && $part < 4) {
                if ($part === 2 && preg_match('/^[DS_*]{4}$/', strtoupper($raw))) {
                    $ans = strtoupper($raw);
                    if (strpos($ans, '_') !== false) {
                        $errors[] = 'Phần II câu ' . $num . ': còn ý chưa chọn Đúng/Sai.';
                        $ans = null;
                    }
                } else {
                    $ans = $part === 1 ? KeyFormat::p1($raw) : ($part === 2 ? KeyFormat::p2($raw) : KeyFormat::p3($raw));
                    if ($ans === null) {
                        $errors[] = ['', 'Phần I', 'Phần II', 'Phần III'][$part] . ' câu ' . $num . ': đáp án "' . $raw . '" không hợp lệ.';
                    }
                }
            }
            $pts = $k['points'] ?? null;
            $pts = ($pts === null || $pts === '') ? null : (is_numeric(str_replace(',', '.', (string) $pts)) ? (float) str_replace(',', '.', (string) $pts) : null);
            $exp = trim((string) ($k['explanation'] ?? ''));
            $row = [
                'part' => $part, 'num' => $num, 'answer' => $ans, 'points' => $pts,
                'level' => KeyImporter::normLevel($k['level'] ?? null),
                'topic' => ($t = trim((string) ($k['topic'] ?? ''))) !== '' ? mb_substr($t, 0, 150) : null,
                'origin' => ($o = trim((string) ($k['origin'] ?? ''))) !== '' ? mb_substr($o, 0, 20) : null,
                'explanation' => $exp !== '' ? mb_substr($exp, 0, 60000) : null,
                'is_void' => !empty($k['is_void']) ? 1 : 0,
            ];
            if ($row['answer'] === null && $row['explanation'] === null && $row['points'] === null && !$row['is_void'] && $row['level'] === null && $row['topic'] === null && $row['origin'] === null) {
                continue;
            }
            $rows[] = $row;
        }
        if ($errors) {
            $this->fail(implode(' ', array_slice($errors, 0, 8)), 422);
            return;
        }
        $n = KeyImporter::save($this->db, (int) $v['id'], $rows, true);
        Attempts::forgetKeys((int) $v['id']);
        $ids = array_map('intval', $this->db->column("SELECT id FROM {attempts} WHERE variant_id = ? AND status <> 'in_progress'", [(int) $v['id']]));
        $rescored = $ids ? Attempts::rescore($ids) : 0;
        $this->db->update('exams', ['updated_at' => time()], 'id = ?', [(int) $e['id']]);
        Logger::audit('exam.key_update', 'variant', (int) $v['id'], ['count' => $n, 'rescored' => $rescored]);
        $this->ok(['saved' => $n, 'rescored' => $rescored, 'message' => 'Đã lưu ' . $n . ' câu' . ($rescored ? ', chấm lại ' . $rescored . ' bài làm' : '') . '.']);
    }
}

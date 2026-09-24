<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Logger;
use App\Core\Request;
use App\Lib\ExamFormat;
use App\Lib\Scoring;
use App\Lib\Text;

final class SubjectsController extends Controller
{
    public function index(): void
    {
        $this->authorize('subjects.manage');
        $rows = $this->db->all('SELECT s.*, (SELECT COUNT(*) FROM {exams} e WHERE e.subject_id = s.id) AS exams FROM {subjects} s ORDER BY s.sort_order, s.name');
        $this->render('subjects/index', ['title' => 'Môn thi & định dạng đề', 'crumbs' => ['Môn thi' => null], 'rows' => $rows]);
    }

    public function edit(): void
    {
        $this->authorize('subjects.manage');
        $id = Request::int('id');
        $s = $id ? $this->findOr404('subjects', $id) : ['id' => 0, 'code' => '', 'name' => '', 'short_name' => '', 'duration' => 50, 'structure' => json_enc(ExamFormat::structureFromPreset(['p1' => 40])), 'scoring' => json_enc(Scoring::defaults()), 'color' => '#2563eb', 'sort_order' => 99, 'is_active' => 1];
        $this->render('subjects/form', [
            'title' => $id ? 'Sửa môn ' . $s['name'] : 'Thêm môn thi',
            'crumbs' => ['Môn thi' => url('subjects'), $id ? $s['name'] : 'Thêm mới' => null],
            's' => $s,
            'structure' => ExamFormat::normalizeStructure($s['structure']),
            'scoring' => Scoring::normalize($s['scoring']),
            'preset' => ExamFormat::PRESETS[$s['code']] ?? null,
        ]);
    }

    public function save(): void
    {
        $this->authorize('subjects.manage');
        $this->requirePost();
        $id = Request::int('id');
        $cur = $id ? $this->findOr404('subjects', $id) : null;
        $name = trim(Request::str('name'));
        $code = strtoupper(Text::slug(Request::str('code') ?: $name, ''));
        if ($name === '' || $code === '') {
            $this->flash('danger', 'Vui lòng nhập tên môn.');
            $this->back('subjects');
            return;
        }
        if ($this->db->value('SELECT 1 FROM {subjects} WHERE code = ?' . ($cur ? ' AND id <> ' . (int) $cur['id'] : ''), [$code])) {
            $this->flash('danger', 'Mã môn "' . $code . '" đã tồn tại.');
            $this->back('subjects');
            return;
        }
        [$structure, $scoring] = ExamsController::readFormatFromRequest();
        $data = [
            'code' => mb_substr($code, 0, 30), 'name' => mb_substr($name, 0, 100), 'short_name' => mb_substr(Request::str('short_name'), 0, 30) ?: null,
            'duration' => max(0, min(600, Request::int('duration', 50))), 'structure' => json_enc($structure), 'scoring' => json_enc($scoring),
            'color' => preg_match('/^#[0-9a-f]{6}$/i', Request::str('color')) ? Request::str('color') : '#2563eb',
            'sort_order' => Request::int('sort_order', 99), 'is_active' => Request::bool('is_active') ? 1 : 0, 'updated_at' => time(),
        ];
        if ($cur) {
            $this->db->update('subjects', $data, 'id = ?', [(int) $cur['id']]);
        } else {
            $data['created_at'] = time();
            $this->db->insert('subjects', $data);
        }
        Logger::audit('subject.update', 'subject', $cur ? (int) $cur['id'] : null, ['code' => $code]);
        $this->flash('success', 'Đã lưu môn ' . $name . '.');
        $this->redirect('subjects');
    }

    /** Khôi phục định dạng chuẩn 2025 cho các môn có sẵn. */
    public function reset(): void
    {
        $this->authorize('subjects.manage');
        $this->requirePost();
        $n = 0;
        foreach (ExamFormat::PRESETS as $code => $p) {
            $n += $this->db->update('subjects', [
                'duration' => $p['duration'],
                'structure' => json_enc(ExamFormat::structureFromPreset($p)),
                'scoring' => json_enc(ExamFormat::scoringFromPreset($p)),
                'updated_at' => time(),
            ], 'code = ?', [$code]);
        }
        \App\Lib\Seeder::subjects($this->db);
        Logger::audit('subject.update', 'subject', null, ['reset' => $n]);
        $this->flash('success', 'Đã khôi phục định dạng đề thi tốt nghiệp từ năm 2025 cho ' . $n . ' môn.');
        $this->redirect('subjects');
    }
}

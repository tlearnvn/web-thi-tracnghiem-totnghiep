<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Scope;

/** Thông báo cho học sinh / cán bộ (hiện ở trang chủ học sinh, bảng điều khiển). */
final class AnnouncementsController extends Controller
{
    public const AUDIENCES = [
        'all' => 'Tất cả mọi người',
        'students' => 'Tất cả học sinh',
        'staff' => 'Giáo viên & cán bộ',
        'class' => 'Một lớp cụ thể',
    ];

    public function index(): void
    {
        $this->authorize('announcements.manage');
        $pager = new Paginator((int) $this->db->value('SELECT COUNT(*) FROM {announcements}'), 20);
        $rows = $this->db->all(
            'SELECT a.*, c.name AS class_name, u.full_name AS author FROM {announcements} a LEFT JOIN {classes} c ON c.id = a.class_id LEFT JOIN {users} u ON u.id = a.created_by
             ORDER BY a.is_pinned DESC, a.created_at DESC' . $pager->sqlLimit()
        );
        $this->render('announcements/index', ['title' => 'Thông báo', 'rows' => $rows, 'pager' => $pager]);
    }

    private function classOptions(): array
    {
        $p = [];
        return $this->db->all("SELECT id, name FROM {classes} c WHERE status = 'active' AND " . Scope::classFilterSql('c.id', $p) . ' ORDER BY grade DESC, sort_key, name', $p);
    }

    public function create(): void
    {
        $this->authorize('announcements.manage');
        $this->render('announcements/form', [
            'title' => 'Tạo thông báo',
            'crumbs' => ['Thông báo' => url('announcements'), 'Tạo mới' => null],
            'a' => ['id' => 0, 'title' => '', 'body' => '', 'audience' => 'students', 'class_id' => null, 'is_pinned' => 0, 'starts_at' => null, 'ends_at' => null],
            'classes' => $this->classOptions(),
        ]);
    }

    public function edit(): void
    {
        $this->authorize('announcements.manage');
        $a = $this->find(Request::int('id'));
        $this->render('announcements/form', [
            'title' => 'Sửa thông báo',
            'crumbs' => ['Thông báo' => url('announcements'), 'Sửa' => null],
            'a' => $a,
            'classes' => $this->classOptions(),
        ]);
    }

    private function find(int $id): array
    {
        $a = $id > 0 ? $this->db->one('SELECT * FROM {announcements} WHERE id = ?', [$id]) : null;
        if (!$a) {
            throw new HttpException(404, 'Không tìm thấy thông báo.');
        }
        if ($a['class_id'] && !Scope::canAccessClass((int) $a['class_id'])) {
            throw new HttpException(403);
        }
        return $a;
    }

    public function save(): void
    {
        $this->authorize('announcements.manage');
        $this->requirePost();
        $id = Request::int('id');
        $cur = $id ? $this->find($id) : null;
        $title = mb_substr(Request::str('title'), 0, 255);
        $audience = Request::str('audience');
        $classId = Request::int('class_id') ?: null;
        $starts = parse_local_datetime(Request::str('starts_at'));
        $ends = parse_local_datetime(Request::str('ends_at'));
        $errors = [];
        if ($title === '') {
            $errors[] = 'Nhập tiêu đề thông báo.';
        }
        if (!isset(self::AUDIENCES[$audience])) {
            $audience = 'all';
        }
        if ($audience === 'class' && (!$classId || !Scope::canAccessClass($classId))) {
            $errors[] = 'Chọn lớp nhận thông báo.';
        }
        if ($starts && $ends && $ends <= $starts) {
            $errors[] = 'Thời gian kết thúc phải sau thời gian bắt đầu.';
        }
        if ($errors) {
            \App\Core\Session::setOld($_POST);
            $this->flash('danger', implode(' ', $errors));
            $this->redirect($id ? 'announcements/edit' : 'announcements/create', $id ? ['id' => $id] : []);
            return;
        }
        $data = [
            'title' => $title,
            'body' => mb_substr(str_replace("\r\n", "\n", (string) Request::post('body', '')), 0, 5000),
            'audience' => $audience,
            'class_id' => $audience === 'class' ? $classId : null,
            'is_pinned' => Request::bool('is_pinned') ? 1 : 0,
            'starts_at' => $starts,
            'ends_at' => $ends,
            'updated_at' => time(),
        ];
        if ($cur) {
            $this->db->update('announcements', $data, 'id = ?', [$id]);
        } else {
            $data['created_by'] = Auth::id();
            $data['created_at'] = time();
            $id = $this->db->insert('announcements', $data);
        }
        \App\Core\Session::clearOld();
        Logger::audit($cur ? 'announcement.update' : 'announcement.create', 'announcement', $id, ['title' => $title]);
        $this->flash('success', 'Đã lưu thông báo.');
        $this->redirect('announcements');
    }

    public function delete(): void
    {
        $this->authorize('announcements.manage');
        $this->requirePost();
        $a = $this->find(Request::int('id'));
        $this->db->run('DELETE FROM {announcements} WHERE id = ?', [(int) $a['id']]);
        Logger::audit('announcement.delete', 'announcement', (int) $a['id'], ['title' => $a['title']]);
        $this->flash('success', 'Đã xóa thông báo.');
        $this->redirect('announcements');
    }
}

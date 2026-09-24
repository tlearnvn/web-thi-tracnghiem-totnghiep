<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Logger;
use App\Core\Paginator;
use App\Core\Request;
use App\Lib\XlsxWriter;

/** Nhật ký hệ thống: hoạt động của người dùng, lỗi, lịch sử đăng nhập (tất cả lưu trong CSDL). */
final class LogsController extends Controller
{
    private function auditWhere(array &$params): string
    {
        $where = ['1=1'];
        $q = mb_strtolower(Request::str('q'));
        if ($q !== '') {
            $where[] = '(LOWER(l.action) LIKE ? OR LOWER(l.details) LIKE ? OR LOWER(u.full_name) LIKE ? OR LOWER(u.username) LIKE ? OR l.ip LIKE ?)';
            array_push($params, '%' . $q . '%', '%' . $q . '%', '%' . $q . '%', '%' . $q . '%', '%' . $q . '%');
        }
        $group = Request::str('group');
        if ($group !== '' && preg_match('/^[a-z_]+$/', $group)) {
            $where[] = 'l.action LIKE ?';
            $params[] = $group . '.%';
        }
        $from = parse_local_datetime(Request::str('from') !== '' ? Request::str('from') . 'T00:00' : '');
        $to = parse_local_datetime(Request::str('to') !== '' ? Request::str('to') . 'T23:59' : '');
        if ($from) {
            $where[] = 'l.created_at >= ?';
            $params[] = $from;
        }
        if ($to) {
            $where[] = 'l.created_at <= ?';
            $params[] = $to + 59;
        }
        if (Request::int('user')) {
            $where[] = 'l.user_id = ?';
            $params[] = Request::int('user');
        }
        return implode(' AND ', $where);
    }

    public function index(): void
    {
        $this->authorize('logs.view');
        $tab = Request::str('tab') ?: 'audit';
        $data = ['title' => 'Nhật ký hệ thống', 'tab' => $tab];
        if ($tab === 'errors') {
            $pager = new Paginator((int) $this->db->value('SELECT COUNT(*) FROM {error_logs}'), 30);
            $data['rows'] = $this->db->all('SELECT e.*, u.full_name FROM {error_logs} e LEFT JOIN {users} u ON u.id = e.user_id ORDER BY e.id DESC' . $pager->sqlLimit());
        } elseif ($tab === 'logins') {
            $params = [];
            $where = '1=1';
            $q = Request::str('q');
            if ($q !== '') {
                $where = '(username LIKE ? OR ip LIKE ?)';
                $params = ['%' . $q . '%', '%' . $q . '%'];
            }
            $pager = new Paginator((int) $this->db->value('SELECT COUNT(*) FROM {login_attempts} WHERE ' . $where, $params), 50);
            $data['rows'] = $this->db->all('SELECT * FROM {login_attempts} WHERE ' . $where . ' ORDER BY id DESC' . $pager->sqlLimit(), $params);
        } else {
            $params = [];
            $where = $this->auditWhere($params);
            $pager = new Paginator((int) $this->db->value('SELECT COUNT(*) FROM {audit_logs} l LEFT JOIN {users} u ON u.id = l.user_id WHERE ' . $where, $params), 50);
            $data['rows'] = $this->db->all('SELECT l.*, u.full_name, u.username FROM {audit_logs} l LEFT JOIN {users} u ON u.id = l.user_id WHERE ' . $where . ' ORDER BY l.id DESC' . $pager->sqlLimit(), $params);
        }
        $data['pager'] = $pager;
        $data['counts'] = [
            'audit' => (int) $this->db->value('SELECT COUNT(*) FROM {audit_logs}'),
            'errors' => (int) $this->db->value('SELECT COUNT(*) FROM {error_logs}'),
            'errors24' => (int) $this->db->value('SELECT COUNT(*) FROM {error_logs} WHERE created_at > ?', [time() - 86400]),
            'logins' => (int) $this->db->value('SELECT COUNT(*) FROM {login_attempts}'),
            'failed24' => (int) $this->db->value('SELECT COUNT(*) FROM {login_attempts} WHERE success = 0 AND created_at > ?', [time() - 86400]),
        ];
        $this->render('logs/index', $data);
    }

    public function export(): void
    {
        $this->authorize('logs.view');
        $params = [];
        $where = $this->auditWhere($params);
        $rows = $this->db->all('SELECT l.*, u.full_name, u.username FROM {audit_logs} l LEFT JOIN {users} u ON u.id = l.user_id WHERE ' . $where . ' ORDER BY l.id DESC LIMIT 50000', $params);
        $x = new XlsxWriter();
        $h = $x->style(['bold' => true, 'fill' => '1D4ED8', 'color' => 'FFFFFF', 'border' => true, 'align' => 'center']);
        $t = $x->style(['border' => true]);
        $s = $x->sheet('Nhật ký');
        $s->widths([18, 24, 16, 28, 16, 60, 16]);
        $s->row(['NHẬT KÝ HOẠT ĐỘNG – ' . mb_strtoupper((string) setting('org_name'))], $x->style(['bold' => true, 'size' => 14]));
        $s->row(['Xuất lúc ' . date('H:i d/m/Y') . ' (UTC+7) · ' . count($rows) . ' dòng'], $x->style(['italic' => true, 'color' => '64748B']));
        $s->row(['Thời gian', 'Người thực hiện', 'Tài khoản', 'Thao tác', 'Đối tượng', 'Chi tiết', 'IP'], $h);
        foreach ($rows as $r) {
            $s->row([fmt_dt($r['created_at'], 'd/m/Y H:i:s'), (string) ($r['full_name'] ?? '—'), (string) ($r['username'] ?? ''), Logger::label($r['action']),
                trim(($r['target_type'] ?? '') . ' #' . ($r['target_id'] ?? ''), ' #'), (string) $r['details'], (string) $r['ip']], $t);
        }
        $s->freeze('A4');
        $x->download('nhat-ky-' . date('Ymd-Hi') . '.xlsx');
    }

    public function clearErrors(): void
    {
        $this->authorize('logs.view');
        $this->requirePost();
        $n = $this->db->run('DELETE FROM {error_logs}')->rowCount();
        Logger::audit('logs.clear_errors', 'system', null, ['count' => $n]);
        $this->flash('success', 'Đã xóa ' . $n . ' dòng nhật ký lỗi.');
        $this->redirect('logs', ['tab' => 'errors']);
    }
}

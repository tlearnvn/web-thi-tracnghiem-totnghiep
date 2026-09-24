<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\FileStore;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\Request;
use App\Lib\KeyImporter;

/**
 * Tải tệp lên theo từng khúc 512 KB (vượt giới hạn upload_max_filesize của hosting),
 * ghi thẳng vào CSDL – không tạo tệp trên đĩa.
 */
final class FilesController extends Controller
{
    private const PURPOSES = [
        'exam_pdf' => ['exams.manage', 'exams.manage_all'],
        'solution_pdf' => ['exams.manage', 'exams.manage_all'],
    ];
    private const MAX_SIZE = 80 * 1048576;

    public function uploadInit(): void
    {
        $this->requirePost();
        $purpose = Request::str('purpose');
        if (!isset(self::PURPOSES[$purpose])) {
            throw new HttpException(400, 'Loại tệp không hợp lệ.');
        }
        $this->authorize(...self::PURPOSES[$purpose]);
        $size = Request::int('size');
        $name = mb_substr(trim(Request::str('name')), 0, 200) ?: 'tep.pdf';
        if ($size <= 0) {
            $this->fail('Tệp rỗng.');
            return;
        }
        if ($size > self::MAX_SIZE) {
            $this->fail('Tệp quá lớn (tối đa ' . fmt_bytes(self::MAX_SIZE) . ').');
            return;
        }
        if (!preg_match('/\.pdf$/i', $name)) {
            $this->fail('Chỉ nhận tệp PDF.');
            return;
        }
        FileStore::purgeStale();
        $id = FileStore::beginUpload($name, 'application/pdf', $size, $purpose, (int) Auth::id());
        $this->ok(['id' => $id, 'chunk' => FileStore::CHUNK]);
    }

    private function pending(int $id): array
    {
        $f = FileStore::info($id);
        if (!$f || $f['status'] !== 'uploading' || (int) $f['owner_id'] !== (int) Auth::id()) {
            throw new HttpException(404, 'Phiên tải lên không tồn tại hoặc đã hết hạn.');
        }
        return $f;
    }

    public function uploadChunk(): void
    {
        $this->requirePost();
        $f = $this->pending(Request::int('id'));
        $seq = (int) ($_GET['seq'] ?? -1);
        if ($seq < 0 || $seq >= (int) $f['chunk_count']) {
            throw new HttpException(400, 'Thứ tự khúc tệp không hợp lệ.');
        }
        $data = (string) file_get_contents('php://input');
        $expected = $seq === (int) $f['chunk_count'] - 1 ? (int) $f['size'] - $seq * FileStore::CHUNK : FileStore::CHUNK;
        if (strlen($data) !== $expected) {
            throw new HttpException(400, 'Khúc tệp bị thiếu dữ liệu (' . strlen($data) . '/' . $expected . ' byte), đang thử lại…');
        }
        if ($seq === 0 && strncmp($data, '%PDF-', 5) !== 0) {
            FileStore::delete((int) $f['id']);
            throw new HttpException(422, 'Tệp không phải PDF hợp lệ.');
        }
        FileStore::putChunk((int) $f['id'], $seq, $data);
        $this->ok(['seq' => $seq]);
    }

    public function uploadFinish(): void
    {
        $this->requirePost();
        $f = $this->pending(Request::int('id'));
        try {
            $f = FileStore::finishUpload((int) $f['id']);
        } catch (\RuntimeException $e) {
            $this->fail($e->getMessage());
            return;
        }
        $pages = self::countPdfPages((int) $f['id']);
        if ($pages) {
            $this->db->update('files', ['meta' => json_enc(['pages' => $pages])], 'id = ?', [(int) $f['id']]);
        }
        $attach = Request::str('type');
        $result = ['id' => (int) $f['id'], 'size' => (int) $f['size'], 'pages' => $pages];
        if ($attach === 'variant') {
            $result['variant_id'] = $this->attachToVariant($f);
        }
        $this->ok($result);
    }

    /** Gắn tệp vào mã đề (tạo mã đề mới nếu cần) và xóa tệp cũ. */
    private function attachToVariant(array $f): int
    {
        $kind = Request::str('kind') === 'solution' ? 'solution_file_id' : 'pdf_file_id';
        $vid = Request::int('variant_id');
        if ($vid) {
            $v = $this->findOr404('exam_variants', $vid);
            $exam = ExamsController::examFor((int) $v['exam_id'], 'manage');
        } else {
            $exam = ExamsController::examFor(Request::int('exam_id'), 'manage');
            $code = KeyImporter::cleanCode(Request::str('code'));
            if ($code === '') {
                throw new HttpException(400, 'Không xác định được mã đề.');
            }
            $v = $this->db->one('SELECT * FROM {exam_variants} WHERE exam_id = ? AND code = ?', [(int) $exam['id'], $code]);
            if (!$v) {
                foreach ($this->db->all('SELECT * FROM {exam_variants} WHERE exam_id = ?', [(int) $exam['id']]) as $row) {
                    if (ctype_digit($row['code']) && ctype_digit($code) && (int) $row['code'] === (int) $code) {
                        $v = $row;
                    }
                }
            }
            if (!$v) {
                $order = (int) $this->db->value('SELECT COALESCE(MAX(sort_order), 0) FROM {exam_variants} WHERE exam_id = ?', [(int) $exam['id']]);
                $id = $this->db->insert('exam_variants', ['exam_id' => (int) $exam['id'], 'code' => $code, 'sort_order' => $order + 1, 'created_at' => time(), 'updated_at' => time()]);
                $v = $this->db->one('SELECT * FROM {exam_variants} WHERE id = ?', [$id]);
            }
        }
        $old = $v[$kind] ? (int) $v[$kind] : null;
        $this->db->update('exam_variants', [$kind => (int) $f['id'], 'updated_at' => time()], 'id = ?', [(int) $v['id']]);
        $this->db->update('files', ['purpose' => $kind === 'pdf_file_id' ? 'exam_pdf' : 'solution_pdf'], 'id = ?', [(int) $f['id']]);
        if ($old && $old !== (int) $f['id']) {
            FileStore::delete($old);
        }
        $this->db->update('exams', ['updated_at' => time()], 'id = ?', [(int) $exam['id']]);
        Logger::audit('exam.pdf_upload', 'variant', (int) $v['id'], ['file' => $f['name'], 'kind' => $kind]);
        return (int) $v['id'];
    }

    /** Đếm số trang PDF (ước lượng nhanh, không cần thư viện). */
    public static function countPdfPages(int $fileId): int
    {
        $data = FileStore::contents($fileId);
        if (preg_match_all('/\/Type\s*\/Pages\b[^>]*?\/Count\s+(\d+)/s', $data, $m)) {
            return (int) max($m[1]);
        }
        return (int) preg_match_all('/\/Type\s*\/Page\b(?!s)/', $data);
    }

    /** Xem tệp PDF (dành cho cán bộ có quyền với đề). */
    public function pdf(): void
    {
        $this->requireStaff();
        $v = $this->findOr404('exam_variants', Request::int('variant_id'));
        ExamsController::examFor((int) $v['exam_id']);
        $fid = Request::str('kind') === 'solution' ? (int) $v['solution_file_id'] : (int) $v['pdf_file_id'];
        $f = FileStore::info($fid);
        if (!$f || $f['status'] !== 'ready') {
            throw new HttpException(404, 'Mã đề chưa có tệp PDF.');
        }
        \App\Core\Session::release();
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        header('Content-Type: application/pdf');
        header('Content-Length: ' . (int) $f['size']);
        header('X-File-Size: ' . (int) $f['size']);
        header('Content-Disposition: inline; filename="de-' . preg_replace('/[^A-Za-z0-9]/', '', (string) $v['code']) . '.pdf"');
        header('Cache-Control: private, no-store');
        FileStore::stream($fid);
    }
}

<?php
use App\Lib\ExamFormat;
use App\Lib\Scoring;
?>
<div class="page-head">
  <div><h1>Môn thi & định dạng đề</h1><div class="sub">Cấu trúc mặc định khi tạo đề mới. Đã nạp sẵn định dạng đề thi tốt nghiệp THPT từ năm 2025.</div></div>
  <div class="actions">
    <button class="btn" data-post="<?= e(url('subjects/reset')) ?>" data-confirm="Khôi phục cấu trúc & cách tính điểm chuẩn 2025 cho các môn mặc định? (Đề thi đã tạo không bị ảnh hưởng)"><?= icon('rotate-ccw') ?> Khôi phục chuẩn 2025</button>
    <a class="btn btn-primary" href="<?= e(url('subjects/edit')) ?>"><?= icon('plus') ?> Thêm môn</a>
  </div>
</div>
<div class="alert alert-info mb-3"><?= icon('info') ?><div>
  <div class="alert-title">Định dạng đề thi tốt nghiệp THPT từ năm 2025</div>
  Phần I: trắc nghiệm 4 lựa chọn (0,25đ/câu) · Phần II: đúng/sai 4 ý (đúng 1 ý 0,1đ – 2 ý 0,25đ – 3 ý 0,5đ – 4 ý 1đ) · Phần III: trả lời ngắn (Toán 0,5đ; Lí, Hóa, Sinh, Địa 0,25đ/câu) · Ngữ văn: tự luận.
</div></div>
<div class="card">
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Môn</th><th>Thời gian</th><th>Cấu trúc</th><th class="hide-sm">Cách tính điểm</th><th class="num">Điểm tối đa</th><th class="center">Đề thi</th><th class="col-actions"></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r):
        $st = ExamFormat::normalizeStructure($r['structure']);
        $sc = Scoring::normalize($r['scoring']); ?>
      <tr<?= (int) $r['is_active'] ? '' : ' style="opacity:.55"' ?>>
        <td><div class="person"><span class="tile-icon" style="--tile-color:<?= e($r['color']) ?>;width:38px;height:38px;border-radius:11px;font-size:11px"><?= e(mb_substr((string) ($r['short_name'] ?: $r['name']), 0, 4)) ?></span><div><div class="name"><?= e($r['name']) ?></div><div class="sub mono"><?= e($r['code']) ?></div></div></div></td>
        <td><?= (int) $r['duration'] ?> phút</td>
        <td class="text-sm"><?= e(ExamFormat::describe($st)) ?></td>
        <td class="hide-sm text-sm text-muted" style="max-width:360px"><?= e(Scoring::describe($sc, $st)) ?></td>
        <td class="num fw-700"><?= e(fmt_num(Scoring::maxScore($st, $sc))) ?></td>
        <td class="center"><?= (int) $r['exams'] ?></td>
        <td class="col-actions"><a class="btn btn-sm btn-ghost" href="<?= e(url('subjects/edit', ['id' => $r['id']])) ?>"><?= icon('square-pen') ?> Sửa</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>

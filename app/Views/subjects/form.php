<div class="page-head"><div><h1><?= (int) $s['id'] ? 'Sửa môn ' . e($s['name']) : 'Thêm môn thi' ?></h1><div class="sub">Cấu trúc và cách tính điểm ở đây được dùng làm mặc định khi tạo đề mới của môn.</div></div></div>
<form method="post" action="<?= e(url('subjects/save')) ?>" class="grid grid-sidebar">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
  <div>
    <?= \App\Core\View::partial('partials/format_editor', ['structure' => $structure, 'scoring' => $scoring]) ?>
  </div>
  <div class="stack" style="gap:20px">
    <div class="card">
      <div class="card-head"><h3><?= icon('book-open') ?> Thông tin môn</h3></div>
      <div class="card-body stack">
        <div class="field"><label>Tên môn <span class="req">*</span></label><input class="input" name="name" value="<?= e($s['name']) ?>" required></div>
        <div class="form-grid">
          <div class="field"><label>Mã môn</label><input class="input mono" name="code" value="<?= e($s['code']) ?>" <?= (int) $s['id'] ? 'readonly' : '' ?>></div>
          <div class="field"><label>Tên ngắn</label><input class="input" name="short_name" value="<?= e($s['short_name']) ?>"></div>
          <div class="field"><label>Thời gian (phút)</label><input class="input" type="number" name="duration" value="<?= (int) $s['duration'] ?>" min="0"></div>
          <div class="field"><label>Thứ tự</label><input class="input" type="number" name="sort_order" value="<?= (int) $s['sort_order'] ?>"></div>
        </div>
        <div class="field"><label>Màu nhận diện</label><div class="color-input"><input type="color" name="color" value="<?= e($s['color'] ?: '#2563eb') ?>"><span class="text-sm text-muted">Hiển thị trên thẻ ca thi, biểu đồ</span></div></div>
        <label class="switch"><input type="checkbox" name="is_active" value="1"<?= checked((int) $s['is_active']) ?>><span class="track"></span>Đang sử dụng</label>
        <?php if ($preset): ?><div class="callout text-sm">Chuẩn 2025: <?= (int) $preset['p1'] ?> câu TN · <?= (int) $preset['p2'] ?> câu Đ/S · <?= (int) $preset['p3'] ?> câu TLN · <?= (int) $preset['duration'] ?> phút<?= !empty($preset['essay']) ? ' · tự luận' : '' ?></div><?php endif; ?>
      </div>
      <div class="card-foot"><a class="btn btn-ghost" href="<?= e(url('subjects')) ?>">Hủy</a><button class="btn btn-primary"><?= icon('save') ?> Lưu môn</button></div>
    </div>
  </div>
</form>
